<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Storage\FilesystemObjectStore;
use Fanoos\Platform\Storage\ObjectAddress;
use Fanoos\Platform\Storage\UploadInspector;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Transaction;
use Fanoos\Platform\Support\Uuid;
use PDO;

final class ContentUploadService
{
    public function __construct(
        private readonly PDO $database,
        private readonly AccessGate $access,
        private readonly UploadInspector $inspector,
        private readonly FilesystemObjectStore $store,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return array{resource_id:string,version_id:string,version_no:int,object_id:string,storage_key:string,status:string} */
    public function addUploadedVersion(
        string $actorUserId,
        string $workspaceId,
        string $resourceId,
        string $sourcePath,
        string $clientName,
        string $classification = 'private',
    ): array {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'resource.create');
        if (!in_array($classification, ['private', 'protected', 'public'], true)) {
            throw new PlatformException('object_classification_invalid', 'Object classification is invalid.', 422);
        }
        if ($classification === 'protected') {
            $this->access->requireWorkspace($actorUserId, $workspaceId, 'resource.manage_protected');
        }
        if ($classification === 'public') {
            $this->access->requireWorkspace($actorUserId, $workspaceId, 'resource.publish');
        }

        $upload = $this->inspector->inspect($sourcePath, $clientName);
        $objectId = Uuid::v7();
        $versionId = Uuid::v7();
        $address = new ObjectAddress($workspaceId, $objectId, $versionId, $classification);
        $storageKey = $this->store->put($address, $upload);

        return Transaction::run($this->database, function () use (
            $actorUserId, $workspaceId, $resourceId, $classification, $upload,
            $objectId, $versionId, $storageKey,
        ): array {
            $resource = $this->database->prepare(<<<'SQL'
SELECT resource.id,
       (SELECT COALESCE(MAX(version.version_no), 0)
        FROM content_resource_versions version
        WHERE version.resource_id = resource.id AND version.workspace_id = resource.workspace_id) AS latest_version_no
FROM content_resources resource
WHERE resource.id = :resource AND resource.workspace_id = :workspace
  AND resource.deleted_at IS NULL AND resource.archived_at IS NULL
FOR UPDATE
SQL);
            $resource->execute(['resource' => $resourceId, 'workspace' => $workspaceId]);
            $row = $resource->fetch();
            if ($row === false) {
                throw new PlatformException('resource_not_found', 'Resource was not found.', 404);
            }
            $versionNo = (int) $row['latest_version_no'] + 1;
            $this->execute(<<<'SQL'
INSERT INTO content_objects (
    id, workspace_id, storage_adapter, storage_key, original_name, detected_mime,
    byte_size, checksum_sha256, classification, status, created_at, verified_at
) VALUES (
    :id, :workspace, 'filesystem', :storage_key, :name, :mime,
    :bytes, :checksum, :classification, 'verified', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
)
SQL, [
                'id' => $objectId, 'workspace' => $workspaceId, 'storage_key' => $storageKey,
                'name' => $upload->displayName, 'mime' => $upload->detectedMime, 'bytes' => $upload->bytes,
                'checksum' => hex2bin($upload->sha256), 'classification' => $classification,
            ]);
            $this->execute(<<<'SQL'
INSERT INTO content_resource_versions (
    id, workspace_id, resource_id, version_no, object_id, created_by_user_id,
    source_kind, content_json, checksum_sha256, status, created_at
) VALUES (
    :id, :workspace, :resource, :version_no, :object, :creator,
    'uploaded', NULL, :checksum, 'draft', UTC_TIMESTAMP(6)
)
SQL, [
                'id' => $versionId, 'workspace' => $workspaceId, 'resource' => $resourceId,
                'version_no' => $versionNo, 'object' => $objectId, 'creator' => $actorUserId,
                'checksum' => hex2bin($upload->sha256),
            ]);
            $this->execute('UPDATE content_resources SET version = version + 1, updated_at = UTC_TIMESTAMP(6) WHERE id = :resource AND workspace_id = :workspace', ['resource' => $resourceId, 'workspace' => $workspaceId]);
            $this->audit->record($workspaceId, $actorUserId, 'content.version.uploaded', 'content_resource_version', $versionId, 'success', [
                'resource_id' => $resourceId,
                'object_id' => $objectId,
                'classification' => $classification,
                'mime' => $upload->detectedMime,
                'bytes' => $upload->bytes,
            ]);

            return [
                'resource_id' => $resourceId, 'version_id' => $versionId,
                'version_no' => $versionNo, 'object_id' => $objectId,
                'storage_key' => $storageKey, 'status' => 'draft',
            ];
        });
    }

    /** @return array{resource_id:string,version_id:string,version_no:int,object_id:string,status:string} */
    public function addExistingPrivatePdfVersion(
        string $actorUserId,
        string $workspaceId,
        string $resourceId,
        string $editionKey,
        string $sha256,
    ): array {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'resource.create');
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'resource.manage_protected');
        if (!preg_match('/^[a-z0-9-]{2,120}@[a-z0-9-]{2,24}$/', $editionKey)) {
            throw new PlatformException('reference_edition_key_invalid', 'Reference edition key is invalid.', 422);
        }
        if (!preg_match('/^[a-f0-9]{64}$/i', $sha256)) {
            throw new PlatformException('object_checksum_invalid', 'Object checksum is invalid.', 422);
        }
        $checksum = hex2bin($sha256);
        if (!is_string($checksum)) {
            throw new PlatformException('object_checksum_invalid', 'Object checksum is invalid.', 422);
        }

        return Transaction::run($this->database, function () use (
            $actorUserId, $workspaceId, $resourceId, $editionKey, $checksum,
        ): array {
            $object = $this->database->prepare(<<<'SQL'
SELECT id, checksum_sha256, byte_size
FROM content_objects
WHERE workspace_id = :workspace
  AND checksum_sha256 = :checksum
  AND detected_mime = 'application/pdf'
  AND classification = 'private'
  AND status = 'verified'
ORDER BY created_at, id
LIMIT 1
FOR UPDATE
SQL);
            $object->bindValue(':workspace', $workspaceId);
            $object->bindValue(':checksum', $checksum, PDO::PARAM_LOB);
            $object->execute();
            $objectRow = $object->fetch();
            if ($objectRow === false) {
                throw new PlatformException('private_pdf_object_not_found', 'A verified private PDF with this checksum was not found in the workspace.', 404);
            }

            $resource = $this->database->prepare(<<<'SQL'
SELECT resource.id, metadata.topic, metadata.format_key, metadata.access_level,
       (SELECT COALESCE(MAX(version.version_no), 0)
        FROM content_resource_versions version
        WHERE version.resource_id = resource.id AND version.workspace_id = resource.workspace_id) AS latest_version_no
FROM content_resources resource
JOIN content_resource_metadata metadata
  ON metadata.resource_id = resource.id AND metadata.workspace_id = resource.workspace_id
WHERE resource.id = :resource AND resource.workspace_id = :workspace
  AND resource.deleted_at IS NULL AND resource.archived_at IS NULL
FOR UPDATE
SQL);
            $resource->execute(['resource' => $resourceId, 'workspace' => $workspaceId]);
            $resourceRow = $resource->fetch();
            if ($resourceRow === false) {
                throw new PlatformException('resource_not_found', 'Resource was not found.', 404);
            }
            if ($resourceRow['topic'] !== $editionKey || $resourceRow['format_key'] !== 'reference_pdf'
                || $resourceRow['access_level'] !== 'private') {
                throw new PlatformException('reference_pdf_invariants_failed', 'Existing private PDFs can only be reused for their matching private reference resource.', 409);
            }

            $versionId = Uuid::v7();
            $versionNo = (int) $resourceRow['latest_version_no'] + 1;
            $insert = $this->database->prepare(<<<'SQL'
INSERT INTO content_resource_versions (
    id, workspace_id, resource_id, version_no, object_id, created_by_user_id,
    source_kind, content_json, checksum_sha256, status, created_at
) VALUES (
    :id, :workspace, :resource, :version_no, :object, :creator,
    'uploaded', NULL, :checksum, 'draft', UTC_TIMESTAMP(6)
)
SQL);
            $insert->bindValue(':id', $versionId);
            $insert->bindValue(':workspace', $workspaceId);
            $insert->bindValue(':resource', $resourceId);
            $insert->bindValue(':version_no', $versionNo, PDO::PARAM_INT);
            $insert->bindValue(':object', (string) $objectRow['id']);
            $insert->bindValue(':creator', $actorUserId);
            $insert->bindValue(':checksum', $objectRow['checksum_sha256'], PDO::PARAM_LOB);
            $insert->execute();
            $this->execute(
                'UPDATE content_resources SET version = version + 1, updated_at = UTC_TIMESTAMP(6) WHERE id = :resource AND workspace_id = :workspace',
                ['resource' => $resourceId, 'workspace' => $workspaceId],
            );
            $this->audit->record($workspaceId, $actorUserId, 'content.version.reused', 'content_resource_version', $versionId, 'success', [
                'resource_id' => $resourceId,
                'object_id' => (string) $objectRow['id'],
                'classification' => 'private',
                'mime' => 'application/pdf',
                'bytes' => (int) $objectRow['byte_size'],
            ]);

            return [
                'resource_id' => $resourceId,
                'version_id' => $versionId,
                'version_no' => $versionNo,
                'object_id' => (string) $objectRow['id'],
                'status' => 'draft',
            ];
        });
    }

    /** @param array<string, scalar|null> $parameters */
    private function execute(string $sql, array $parameters): void
    {
        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);
    }
}
