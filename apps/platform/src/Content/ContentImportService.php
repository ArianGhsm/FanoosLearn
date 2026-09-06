<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Migration\LegacyIdMap;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Transaction;
use Fanoos\Platform\Support\Uuid;
use PDO;

final class ContentImportService
{
    public function __construct(
        private readonly PDO $database,
        private readonly AccessGate $access,
        private readonly ContentService $content,
        private readonly LegacyIdMap $legacyIds,
        private readonly AuditLogger $audit,
        private readonly string $sourceHmacKey,
    ) {
        if (strlen($sourceHmacKey) < 16) {
            throw new \RuntimeException('Content import HMAC key must contain at least 16 bytes.');
        }
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array{batch_id:string,status:string,item_count:int,created_count:int,updated_count:int,duplicate_count:int,replayed:bool}
     */
    public function import(
        string $actorUserId,
        string $workspaceId,
        string $sourceKey,
        string $importKey,
        array $manifest,
    ): array {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'resource.create');
        if (($manifest['schema_version'] ?? null) !== 1 || !is_array($manifest['items'] ?? null) || !array_is_list($manifest['items'])) {
            throw new PlatformException('content_manifest_invalid', 'Content manifest schema is invalid.', 422);
        }
        if (count($manifest['items']) < 1 || count($manifest['items']) > 5000) {
            throw new PlatformException('content_manifest_size_invalid', 'Content manifest must contain between 1 and 5000 items.', 422);
        }
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.:-]{1,159}$/', $importKey)) {
            throw new PlatformException('content_import_key_invalid', 'Import key format is invalid.', 422);
        }
        $source = $this->database->prepare("SELECT id FROM migration_source_systems WHERE source_key = :source AND mode = 'read_only' AND retired_at IS NULL");
        $source->execute(['source' => $sourceKey]);
        $sourceSystemId = $source->fetchColumn();
        if ($sourceSystemId === false) {
            throw new PlatformException('migration_source_not_found', 'Read-only migration source is not registered.', 422);
        }
        $manifestJson = ContentPayload::encode($manifest);
        $manifestDigest = hash('sha256', $manifestJson, true);

        return Transaction::run($this->database, function () use (
            $actorUserId, $workspaceId, $sourceSystemId, $importKey, $manifest,
            $manifestDigest,
        ): array {
            $existingBatch = $this->database->prepare(<<<'SQL'
SELECT id, HEX(manifest_digest) AS manifest_digest, status, item_count,
       created_count, updated_count, duplicate_count
FROM content_import_batches
WHERE workspace_id = :workspace AND source_system_id = :source AND import_key = :import_key
FOR UPDATE
SQL);
            $existingBatch->execute(['workspace' => $workspaceId, 'source' => $sourceSystemId, 'import_key' => $importKey]);
            $existing = $existingBatch->fetch();
            if ($existing !== false) {
                if (!hash_equals(strtolower((string) $existing['manifest_digest']), bin2hex($manifestDigest))) {
                    throw new PlatformException('content_import_key_conflict', 'Import key was already used for another manifest.', 409);
                }
                if ($existing['status'] !== 'completed') {
                    throw new PlatformException('content_import_incomplete', 'Existing content import did not complete.', 409);
                }

                return $this->batchResult($existing, true);
            }

            $batchId = Uuid::v7();
            $this->execute(<<<'SQL'
INSERT INTO content_import_batches (
    id, workspace_id, source_system_id, import_key, manifest_digest, status,
    item_count, imported_by_user_id, started_at
) VALUES (
    :id, :workspace, :source, :import_key, :digest, 'running',
    :items, :actor, UTC_TIMESTAMP(6)
)
SQL, [
                'id' => $batchId, 'workspace' => $workspaceId, 'source' => $sourceSystemId,
                'import_key' => $importKey, 'digest' => $manifestDigest,
                'items' => count($manifest['items']), 'actor' => $actorUserId,
            ]);
            $counts = ['created' => 0, 'updated' => 0, 'duplicate' => 0];
            foreach ($manifest['items'] as $index => $item) {
                if (!is_array($item)) {
                    throw new PlatformException('content_import_item_invalid', "Content import item {$index} is invalid.", 422);
                }
                $sourceItemKey = $item['source_key'] ?? null;
                $typeKey = $item['type'] ?? null;
                $title = $item['title'] ?? null;
                $payload = $item['content'] ?? null;
                $metadata = $item['metadata'] ?? [];
                if (!is_string($sourceItemKey) || $sourceItemKey === '' || strlen($sourceItemKey) > 500
                    || !is_string($typeKey) || !is_string($title) || !is_array($payload) || !is_array($metadata)) {
                    throw new PlatformException('content_import_item_invalid', "Content import item {$index} has invalid fields.", 422);
                }
                $sourceDigest = hash_hmac('sha256', $sourceItemKey, $this->sourceHmacKey, true);
                $fingerprintJson = ContentPayload::encode([
                    'type' => $typeKey, 'title' => $title, 'metadata' => $metadata, 'content' => $payload,
                ]);
                $contentDigest = hash('sha256', $fingerprintJson, true);
                $currentQuery = $this->database->prepare(<<<'SQL'
SELECT item.id, item.resource_id, item.resource_version_id, item.content_digest, type.type_key
FROM content_import_items item
JOIN content_resources resource ON resource.id = item.resource_id AND resource.workspace_id = item.workspace_id
JOIN content_resource_types type ON type.id = resource.resource_type_id
WHERE item.workspace_id = :workspace AND item.source_system_id = :source
  AND item.source_item_digest = :source_item
FOR UPDATE
SQL);
                $currentQuery->bindValue(':workspace', $workspaceId);
                $currentQuery->bindValue(':source', $sourceSystemId);
                $currentQuery->bindValue(':source_item', $sourceDigest, PDO::PARAM_LOB);
                $currentQuery->execute();
                $current = $currentQuery->fetch();

                if ($current === false) {
                    $metadata['source_reference'] = 'import:' . substr(bin2hex($sourceDigest), 0, 20);
                    $created = $this->content->createResource($actorUserId, $workspaceId, $typeKey, $title, $payload, $metadata, 'imported');
                    $resourceId = $created['resource_id'];
                    $versionId = $created['version_id'];
                    $outcome = 'created';
                    $this->execute(<<<'SQL'
INSERT INTO content_import_items (
    id, workspace_id, source_system_id, batch_id, source_item_digest, content_digest,
    resource_id, resource_version_id, outcome, imported_at
) VALUES (
    :id, :workspace, :source, :batch, :source_item, :content_digest,
    :resource, :version, :outcome, UTC_TIMESTAMP(6)
)
SQL, [
                        'id' => Uuid::v7(), 'workspace' => $workspaceId, 'source' => $sourceSystemId,
                        'batch' => $batchId, 'source_item' => $sourceDigest, 'content_digest' => $contentDigest,
                        'resource' => $resourceId, 'version' => $versionId, 'outcome' => $outcome,
                    ]);
                    $this->legacyIds->remember((string) $sourceSystemId, 'content_resource', $sourceItemKey, 'content_resource', $resourceId);
                } elseif (hash_equals((string) $current['content_digest'], $contentDigest)) {
                    $resourceId = (string) $current['resource_id'];
                    $versionId = (string) $current['resource_version_id'];
                    $outcome = 'duplicate';
                    $this->execute(<<<'SQL'
UPDATE content_import_items
SET batch_id = :batch, outcome = 'duplicate', imported_at = UTC_TIMESTAMP(6)
WHERE id = :id
SQL, ['batch' => $batchId, 'id' => $current['id']]);
                    $this->legacyIds->remember((string) $sourceSystemId, 'content_resource', $sourceItemKey, 'content_resource', $resourceId);
                } else {
                    if (!hash_equals((string) $current['type_key'], $typeKey)) {
                        throw new PlatformException('content_import_type_conflict', 'An imported source item cannot change resource type.', 409);
                    }
                    $metadata['source_reference'] = 'import:' . substr(bin2hex($sourceDigest), 0, 20);
                    $this->content->updateMetadata($actorUserId, $workspaceId, (string) $current['resource_id'], $title, $metadata);
                    $updated = $this->content->addStructuredVersion(
                        $actorUserId,
                        $workspaceId,
                        (string) $current['resource_id'],
                        $payload,
                        'imported',
                        (string) $metadata['source_reference'],
                    );
                    $resourceId = $updated['resource_id'];
                    $versionId = $updated['version_id'];
                    $outcome = 'updated';
                    $this->execute(<<<'SQL'
UPDATE content_import_items
SET batch_id = :batch, content_digest = :content_digest, resource_version_id = :version,
    outcome = 'updated', imported_at = UTC_TIMESTAMP(6)
WHERE id = :id
SQL, ['batch' => $batchId, 'content_digest' => $contentDigest, 'version' => $versionId, 'id' => $current['id']]);
                }
                ++$counts[$outcome];
                $this->execute(<<<'SQL'
INSERT INTO content_import_results (
    id, workspace_id, source_system_id, batch_id, source_item_digest,
    resource_id, resource_version_id, outcome, created_at
) VALUES (
    :id, :workspace, :source, :batch, :source_item,
    :resource, :version, :outcome, UTC_TIMESTAMP(6)
)
SQL, [
                    'id' => Uuid::v7(), 'workspace' => $workspaceId, 'source' => $sourceSystemId,
                    'batch' => $batchId, 'source_item' => $sourceDigest,
                    'resource' => $resourceId, 'version' => $versionId, 'outcome' => $outcome,
                ]);
            }
            $this->execute(<<<'SQL'
UPDATE content_import_batches
SET status = 'completed', created_count = :created, updated_count = :updated,
    duplicate_count = :duplicate, completed_at = UTC_TIMESTAMP(6)
WHERE id = :id
SQL, ['created' => $counts['created'], 'updated' => $counts['updated'], 'duplicate' => $counts['duplicate'], 'id' => $batchId]);
            $this->audit->record($workspaceId, $actorUserId, 'content.import.completed', 'content_import_batch', $batchId, 'success', [
                'item_count' => count($manifest['items']),
                'created_count' => $counts['created'],
                'updated_count' => $counts['updated'],
                'duplicate_count' => $counts['duplicate'],
            ]);

            return [
                'batch_id' => $batchId, 'status' => 'completed', 'item_count' => count($manifest['items']),
                'created_count' => $counts['created'], 'updated_count' => $counts['updated'],
                'duplicate_count' => $counts['duplicate'], 'replayed' => false,
            ];
        });
    }

    /** @param array<string, mixed> $row @return array{batch_id:string,status:string,item_count:int,created_count:int,updated_count:int,duplicate_count:int,replayed:bool} */
    private function batchResult(array $row, bool $replayed): array
    {
        return [
            'batch_id' => (string) $row['id'], 'status' => (string) $row['status'],
            'item_count' => (int) $row['item_count'], 'created_count' => (int) $row['created_count'],
            'updated_count' => (int) $row['updated_count'], 'duplicate_count' => (int) $row['duplicate_count'],
            'replayed' => $replayed,
        ];
    }

    /** @param array<string, scalar|null> $parameters */
    private function execute(string $sql, array $parameters): void
    {
        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);
    }
}
