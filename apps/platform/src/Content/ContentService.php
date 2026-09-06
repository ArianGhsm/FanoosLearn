<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Transaction;
use Fanoos\Platform\Support\Uuid;
use PDO;

final class ContentService
{
    private const SOURCE_KINDS = ['authored', 'uploaded', 'imported', 'forked', 'generated'];

    public function __construct(
        private readonly PDO $database,
        private readonly AccessGate $access,
        private readonly ProtectedResourceAuthorizer $authorizer,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $metadata
     * @return array{resource_id:string,version_id:string,version_no:int,status:string}
     */
    public function createResource(
        string $actorUserId,
        string $workspaceId,
        string $typeKey,
        string $title,
        array $payload,
        array $metadata = [],
        string $sourceKind = 'authored',
    ): array {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'resource.create');
        $title = $this->boundedText($title, 255, 'resource_title_invalid');
        $description = $this->optionalText($metadata['description'] ?? null, 5000, 'resource_description_invalid');
        $formatKey = $this->key((string) ($metadata['format_key'] ?? 'standard'), 'format_key_invalid');
        $accessLevel = (string) ($metadata['access_level'] ?? 'workspace');
        if (!in_array($accessLevel, ['private', 'workspace', 'entitled'], true)) {
            throw new PlatformException('access_level_invalid', 'Resource access level is invalid.', 422);
        }
        if (!in_array($sourceKind, self::SOURCE_KINDS, true)) {
            throw new PlatformException('source_kind_invalid', 'Resource source kind is invalid.', 422);
        }
        $typeId = $this->resourceTypeId($typeKey);
        $targetScopeId = $this->targetScope($workspaceId, $metadata['target_scope_id'] ?? null);
        $academic = $this->academicMetadata($workspaceId, $metadata);
        $json = ContentPayload::encode($payload);
        $checksum = hash('sha256', $json, true);
        $resourceId = Uuid::v7();
        $versionId = Uuid::v7();
        $scopeId = Uuid::v7();
        $visibility = $accessLevel === 'private' ? 'private' : ($accessLevel === 'entitled' ? 'restricted' : 'workspace');

        Transaction::run($this->database, function () use (
            $actorUserId, $workspaceId, $typeKey, $typeId, $title, $description, $formatKey, $accessLevel,
            $targetScopeId, $academic, $json, $checksum, $resourceId, $versionId, $scopeId,
            $visibility, $sourceKind,
        ): void {
            $this->execute(<<<'SQL'
INSERT INTO content_resources (
    id, workspace_id, resource_type_id, owner_user_id, title, description, visibility,
    lifecycle_status, current_version_no, version, created_at, updated_at
) VALUES (
    :id, :workspace, :type, :owner, :title, :description, :visibility,
    'draft', 0, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
)
SQL, [
                'id' => $resourceId, 'workspace' => $workspaceId, 'type' => $typeId,
                'owner' => $actorUserId, 'title' => $title, 'description' => $description,
                'visibility' => $visibility,
            ]);
            $this->execute(<<<'SQL'
INSERT INTO content_resource_versions (
    id, workspace_id, resource_id, version_no, created_by_user_id, source_kind,
    source_reference, content_json, checksum_sha256, status, created_at
) VALUES (
    :id, :workspace, :resource, 1, :creator, :source_kind,
    :source_reference, :content, :checksum, 'draft', UTC_TIMESTAMP(6)
)
SQL, [
                'id' => $versionId, 'workspace' => $workspaceId, 'resource' => $resourceId,
                'creator' => $actorUserId, 'source_kind' => $sourceKind,
                'source_reference' => $this->sourceReference($sourceKind, $metadata),
                'content' => $json, 'checksum' => $checksum,
            ]);
            $this->execute(<<<'SQL'
INSERT INTO content_resource_metadata (
    workspace_id, resource_id, term_id, course_id, session_id, topic,
    professor_name, format_key, access_level, created_at, updated_at
) VALUES (
    :workspace, :resource, :term, :course, :session, :topic,
    :professor, :format_key, :access_level, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
)
SQL, [
                'workspace' => $workspaceId, 'resource' => $resourceId,
                'term' => $academic['term_id'], 'course' => $academic['course_id'],
                'session' => $academic['session_id'], 'topic' => $academic['topic'],
                'professor' => $academic['professor_name'], 'format_key' => $formatKey,
                'access_level' => $accessLevel,
            ]);
            $workspaceScope = $this->workspaceScopeId($workspaceId);
            $this->execute(<<<'SQL'
INSERT INTO rbac_scopes (id, scope_type, entity_id, workspace_id, parent_scope_id, created_at)
VALUES (:id, 'resource', :resource, :workspace, :parent, UTC_TIMESTAMP(6))
SQL, ['id' => $scopeId, 'resource' => $resourceId, 'workspace' => $workspaceId, 'parent' => $workspaceScope]);
            $this->execute(<<<'SQL'
INSERT INTO content_access_policies (
    workspace_id, resource_id, target_scope_id, requires_entitlement, download_ttl_seconds, updated_at
) VALUES (:workspace, :resource, :scope, :requires_entitlement, :ttl, UTC_TIMESTAMP(6))
SQL, [
                'workspace' => $workspaceId, 'resource' => $resourceId, 'scope' => $targetScopeId,
                'requires_entitlement' => $accessLevel === 'entitled' ? 1 : 0,
                'ttl' => $this->downloadTtl($metadata['download_ttl_seconds'] ?? 300),
            ]);
            $this->bindResource($workspaceId, $resourceId, $academic);
            $this->audit->record($workspaceId, $actorUserId, 'content.resource.created', 'content_resource', $resourceId, 'success', [
                'type_key' => $typeKey,
                'format_key' => $formatKey,
                'access_level' => $accessLevel,
                'source_kind' => $sourceKind,
            ]);
        });

        return ['resource_id' => $resourceId, 'version_id' => $versionId, 'version_no' => 1, 'status' => 'draft'];
    }

    /** @param array<string, mixed> $payload @return array{resource_id:string,version_id:string,version_no:int,status:string} */
    public function addStructuredVersion(
        string $actorUserId,
        string $workspaceId,
        string $resourceId,
        array $payload,
        string $sourceKind = 'authored',
        ?string $sourceReference = null,
    ): array {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'resource.create');
        if (!in_array($sourceKind, self::SOURCE_KINDS, true)) {
            throw new PlatformException('source_kind_invalid', 'Resource source kind is invalid.', 422);
        }
        $json = ContentPayload::encode($payload);
        $checksum = hash('sha256', $json, true);

        return Transaction::run($this->database, function () use ($actorUserId, $workspaceId, $resourceId, $json, $checksum, $sourceKind, $sourceReference): array {
            $resource = $this->lockResource($workspaceId, $resourceId);
            $versionNo = (int) $resource['latest_version_no'] + 1;
            $versionId = Uuid::v7();
            $this->execute(<<<'SQL'
INSERT INTO content_resource_versions (
    id, workspace_id, resource_id, version_no, created_by_user_id, source_kind,
    source_reference, content_json, checksum_sha256, status, created_at
) VALUES (
    :id, :workspace, :resource, :version_no, :creator, :source_kind,
    :source_reference, :content, :checksum, 'draft', UTC_TIMESTAMP(6)
)
SQL, [
                'id' => $versionId, 'workspace' => $workspaceId, 'resource' => $resourceId,
                'version_no' => $versionNo, 'creator' => $actorUserId, 'source_kind' => $sourceKind,
                'source_reference' => $sourceReference, 'content' => $json, 'checksum' => $checksum,
            ]);
            $this->execute('UPDATE content_resources SET version = version + 1, updated_at = UTC_TIMESTAMP(6) WHERE id = :id AND workspace_id = :workspace', ['id' => $resourceId, 'workspace' => $workspaceId]);
            $this->audit->record($workspaceId, $actorUserId, 'content.version.created', 'content_resource_version', $versionId, 'success', ['resource_id' => $resourceId, 'version_no' => $versionNo, 'source_kind' => $sourceKind]);

            return ['resource_id' => $resourceId, 'version_id' => $versionId, 'version_no' => $versionNo, 'status' => 'draft'];
        });
    }

    /** @param array<string, mixed> $metadata */
    public function updateMetadata(string $actorUserId, string $workspaceId, string $resourceId, string $title, array $metadata): void
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'resource.create');
        $title = $this->boundedText($title, 255, 'resource_title_invalid');
        $description = $this->optionalText($metadata['description'] ?? null, 5000, 'resource_description_invalid');
        $formatKey = $this->key((string) ($metadata['format_key'] ?? 'standard'), 'format_key_invalid');
        $accessLevel = (string) ($metadata['access_level'] ?? 'workspace');
        if (!in_array($accessLevel, ['private', 'workspace', 'entitled'], true)) {
            throw new PlatformException('access_level_invalid', 'Resource access level is invalid.', 422);
        }
        $targetScopeId = $this->targetScope($workspaceId, $metadata['target_scope_id'] ?? null);
        $academic = $this->academicMetadata($workspaceId, $metadata);
        $visibility = $accessLevel === 'private' ? 'private' : ($accessLevel === 'entitled' ? 'restricted' : 'workspace');

        Transaction::run($this->database, function () use (
            $actorUserId, $workspaceId, $resourceId, $title, $description, $formatKey,
            $accessLevel, $targetScopeId, $academic, $visibility, $metadata,
        ): void {
            $this->lockResource($workspaceId, $resourceId);
            $this->execute(<<<'SQL'
UPDATE content_resources
SET title = :title, description = :description, visibility = :visibility,
    updated_at = UTC_TIMESTAMP(6), version = version + 1
WHERE id = :resource AND workspace_id = :workspace
SQL, [
                'title' => $title, 'description' => $description, 'visibility' => $visibility,
                'resource' => $resourceId, 'workspace' => $workspaceId,
            ]);
            $this->execute(<<<'SQL'
UPDATE content_resource_metadata
SET term_id = :term, course_id = :course, session_id = :session, topic = :topic,
    professor_name = :professor, format_key = :format_key, access_level = :access_level,
    updated_at = UTC_TIMESTAMP(6)
WHERE resource_id = :resource AND workspace_id = :workspace
SQL, [
                'term' => $academic['term_id'], 'course' => $academic['course_id'],
                'session' => $academic['session_id'], 'topic' => $academic['topic'],
                'professor' => $academic['professor_name'], 'format_key' => $formatKey,
                'access_level' => $accessLevel, 'resource' => $resourceId, 'workspace' => $workspaceId,
            ]);
            $this->execute(<<<'SQL'
UPDATE content_access_policies
SET target_scope_id = :scope, requires_entitlement = :entitled,
    download_ttl_seconds = :ttl, updated_at = UTC_TIMESTAMP(6)
WHERE resource_id = :resource AND workspace_id = :workspace
SQL, [
                'scope' => $targetScopeId, 'entitled' => $accessLevel === 'entitled' ? 1 : 0,
                'ttl' => $this->downloadTtl($metadata['download_ttl_seconds'] ?? 300),
                'resource' => $resourceId, 'workspace' => $workspaceId,
            ]);
            $this->updateBinding($workspaceId, $resourceId, $academic);
            $this->audit->record($workspaceId, $actorUserId, 'content.resource.metadata_updated', 'content_resource', $resourceId, 'success', [
                'format_key' => $formatKey, 'access_level' => $accessLevel,
            ]);
        });
    }

    public function submitForReview(string $actorUserId, string $workspaceId, string $resourceId, string $versionId): void
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'resource.create');
        Transaction::run($this->database, function () use ($actorUserId, $workspaceId, $resourceId, $versionId): void {
            $version = $this->version($workspaceId, $resourceId, $versionId, true);
            if (!in_array($version['status'], ['draft', 'rejected'], true)) {
                throw new PlatformException('version_state_conflict', 'Only a draft or rejected version can enter review.', 409);
            }
            $this->execute("UPDATE content_resource_versions SET status = 'review' WHERE id = :version AND resource_id = :resource AND workspace_id = :workspace", ['version' => $versionId, 'resource' => $resourceId, 'workspace' => $workspaceId]);
            $this->execute("UPDATE content_resources SET lifecycle_status = IF(current_version_no = 0, 'review', lifecycle_status), updated_at = UTC_TIMESTAMP(6), version = version + 1 WHERE id = :resource AND workspace_id = :workspace", ['resource' => $resourceId, 'workspace' => $workspaceId]);
            $this->audit->record($workspaceId, $actorUserId, 'content.version.review_requested', 'content_resource_version', $versionId, 'success', ['resource_id' => $resourceId]);
        });
    }

    public function reviewVersion(string $actorUserId, string $workspaceId, string $resourceId, string $versionId, string $decision, ?string $note = null): void
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'resource.review');
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new PlatformException('review_decision_invalid', 'Review decision is invalid.', 422);
        }
        $note = $this->optionalText($note, 1000, 'review_note_invalid');
        Transaction::run($this->database, function () use ($actorUserId, $workspaceId, $resourceId, $versionId, $decision, $note): void {
            $version = $this->version($workspaceId, $resourceId, $versionId, true);
            if ($version['status'] !== 'review') {
                throw new PlatformException('version_state_conflict', 'Version is not awaiting review.', 409);
            }
            if (hash_equals((string) $version['created_by_user_id'], $actorUserId)) {
                throw new PlatformException('self_review_forbidden', 'A version creator cannot approve their own work.', 403);
            }
            $this->execute(<<<'SQL'
INSERT INTO content_version_reviews (
    id, workspace_id, resource_id, resource_version_id, reviewer_user_id, decision, note, decided_at
) VALUES (:id, :workspace, :resource, :version, :reviewer, :decision, :note, UTC_TIMESTAMP(6))
SQL, ['id' => Uuid::v7(), 'workspace' => $workspaceId, 'resource' => $resourceId, 'version' => $versionId, 'reviewer' => $actorUserId, 'decision' => $decision, 'note' => $note]);
            $this->execute("UPDATE content_resource_versions SET status = :decision, reviewed_at = UTC_TIMESTAMP(6) WHERE id = :version AND resource_id = :resource AND workspace_id = :workspace", ['decision' => $decision, 'version' => $versionId, 'resource' => $resourceId, 'workspace' => $workspaceId]);
            if ($decision === 'rejected') {
                $this->execute("UPDATE content_resources SET lifecycle_status = IF(current_version_no = 0, 'draft', lifecycle_status), updated_at = UTC_TIMESTAMP(6), version = version + 1 WHERE id = :resource AND workspace_id = :workspace", ['resource' => $resourceId, 'workspace' => $workspaceId]);
            }
            $this->audit->record($workspaceId, $actorUserId, 'content.version.' . $decision, 'content_resource_version', $versionId, 'success', ['resource_id' => $resourceId]);
        });
    }

    public function publishVersion(string $actorUserId, string $workspaceId, string $resourceId, string $versionId): void
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'resource.publish');
        Transaction::run($this->database, function () use ($actorUserId, $workspaceId, $resourceId, $versionId): void {
            $version = $this->version($workspaceId, $resourceId, $versionId, true);
            if ($version['status'] !== 'approved') {
                throw new PlatformException('version_not_approved', 'Only an approved version can be published.', 409);
            }
            $approval = $this->database->prepare("SELECT 1 FROM content_version_reviews WHERE resource_version_id = :version AND decision = 'approved' LIMIT 1");
            $approval->execute(['version' => $versionId]);
            if ($approval->fetchColumn() === false) {
                throw new PlatformException('review_evidence_missing', 'Approval evidence is missing.', 409);
            }
            $this->execute(<<<'SQL'
UPDATE content_resources
SET current_version_no = :version_no, lifecycle_status = 'published', updated_at = UTC_TIMESTAMP(6), version = version + 1
WHERE id = :resource AND workspace_id = :workspace
SQL, ['version_no' => $version['version_no'], 'resource' => $resourceId, 'workspace' => $workspaceId]);
            $this->outbox($workspaceId, 'content_resource', $resourceId, 'content.resource.published', [
                'resource_id' => $resourceId,
                'resource_version_id' => $versionId,
                'version_no' => (int) $version['version_no'],
            ]);
            $this->audit->record($workspaceId, $actorUserId, 'content.version.published', 'content_resource_version', $versionId, 'success', ['resource_id' => $resourceId, 'version_no' => (int) $version['version_no']]);
        });
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $metadata
     * @return array{resource_id:string,version_id:string,version_no:int,status:string}
     */
    public function deriveResource(
        string $actorUserId,
        string $workspaceId,
        string $sourceResourceId,
        string $sourceVersionId,
        string $outputTypeKey,
        string $title,
        string $transformationKey,
        array $payload,
        array $metadata = [],
        string $producer = 'operator',
    ): array {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'resource.create');
        $transformationKey = $this->key($transformationKey, 'transformation_key_invalid');
        $producer = $this->key($producer, 'producer_key_invalid');
        return Transaction::run($this->database, function () use (
            $actorUserId, $workspaceId, $sourceResourceId, $sourceVersionId, $outputTypeKey,
            $title, $transformationKey, $payload, $metadata, $producer,
        ): array {
            $source = $this->version($workspaceId, $sourceResourceId, $sourceVersionId, true);
            if ($source['status'] !== 'approved') {
                throw new PlatformException('source_version_not_approved', 'Derived products require an approved source version.', 409);
            }
            $metadata['source_reference'] = 'derived:' . $sourceVersionId;
            $output = $this->createResource($actorUserId, $workspaceId, $outputTypeKey, $title, $payload, $metadata, 'generated');
            $this->execute(<<<'SQL'
INSERT INTO content_derivations (
    id, workspace_id, source_resource_id, source_version_id, output_resource_id,
    output_version_id, transformation_key, producer, created_by_user_id, created_at
) VALUES (
    :id, :workspace, :source_resource, :source_version, :output_resource,
    :output_version, :transformation, :producer, :creator, UTC_TIMESTAMP(6)
)
SQL, [
                'id' => Uuid::v7(), 'workspace' => $workspaceId,
                'source_resource' => $sourceResourceId, 'source_version' => $sourceVersionId,
                'output_resource' => $output['resource_id'], 'output_version' => $output['version_id'],
                'transformation' => $transformationKey, 'producer' => $producer, 'creator' => $actorUserId,
            ]);

            return $output;
        });
    }

    /** @param array<string, string> $filters @return list<array<string, mixed>> */
    public function library(string $actorUserId, string $workspaceId, array $filters = []): array
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'resource.view');
        $canManage = $this->access->workspace($actorUserId, $workspaceId, 'resource.create')->allowed;
        $where = ['resource.workspace_id = :workspace', 'resource.deleted_at IS NULL'];
        $parameters = ['workspace' => $workspaceId];
        if (!$canManage) {
            $where[] = "resource.lifecycle_status = 'published'";
        }
        if (($filters['type'] ?? '') !== '') {
            $where[] = 'type.type_key = :type';
            $parameters['type'] = $filters['type'];
        }
        if (($filters['course_id'] ?? '') !== '') {
            $where[] = 'metadata.course_id = :course';
            $parameters['course'] = $filters['course_id'];
        }
        if ($canManage && ($filters['status'] ?? '') !== '') {
            $where[] = 'resource.lifecycle_status = :status';
            $parameters['status'] = $filters['status'];
        }
        if (($filters['q'] ?? '') !== '') {
            $query = trim($filters['q']);
            if (mb_strlen($query) < 2 || mb_strlen($query) > 120) {
                throw new PlatformException('search_query_invalid', 'Search query length is invalid.', 422);
            }
            $where[] = '(resource.title LIKE :query_title OR resource.description LIKE :query_description OR metadata.topic LIKE :query_topic)';
            $parameters['query_title'] = '%' . $query . '%';
            $parameters['query_description'] = '%' . $query . '%';
            $parameters['query_topic'] = '%' . $query . '%';
        }
        $orders = ['newest' => 'resource.updated_at DESC', 'oldest' => 'resource.updated_at ASC', 'title' => 'resource.title ASC'];
        $order = $orders[$filters['sort'] ?? 'newest'] ?? $orders['newest'];
        $statement = $this->database->prepare(sprintf(<<<'SQL'
SELECT resource.id, resource.title, resource.description, resource.visibility,
       resource.lifecycle_status, resource.current_version_no, resource.updated_at,
       type.type_key, metadata.term_id, metadata.course_id, metadata.session_id,
       metadata.topic, metadata.professor_name, metadata.format_key, metadata.access_level
FROM content_resources resource
JOIN content_resource_types type ON type.id = resource.resource_type_id
JOIN content_resource_metadata metadata ON metadata.resource_id = resource.id AND metadata.workspace_id = resource.workspace_id
WHERE %s
ORDER BY %s
LIMIT 100
SQL, implode(' AND ', $where), $order));
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed> */
    public function view(string $actorUserId, string $workspaceId, string $resourceId): array
    {
        $decision = $this->authorizer->decide($actorUserId, $workspaceId, $resourceId);
        if (!$decision['allowed']) {
            throw new PlatformException('resource_access_denied', 'Resource access was denied.', 403);
        }
        $statement = $this->database->prepare(<<<'SQL'
SELECT resource.id, resource.title, resource.description, resource.lifecycle_status,
       type.type_key, version.id AS version_id, version.version_no, version.content_json,
       metadata.term_id, metadata.course_id, metadata.session_id, metadata.topic,
       metadata.professor_name, metadata.format_key, metadata.access_level
FROM content_resources resource
JOIN content_resource_types type ON type.id = resource.resource_type_id
JOIN content_resource_versions version ON version.resource_id = resource.id
 AND version.workspace_id = resource.workspace_id AND version.version_no = resource.current_version_no
JOIN content_resource_metadata metadata ON metadata.resource_id = resource.id AND metadata.workspace_id = resource.workspace_id
WHERE resource.id = :resource AND resource.workspace_id = :workspace
SQL);
        $statement->execute(['resource' => $resourceId, 'workspace' => $workspaceId]);
        $row = $statement->fetch();
        if ($row === false) {
            throw new PlatformException('resource_not_found', 'Resource was not found.', 404);
        }
        $row['content'] = $row['content_json'] === null ? null : json_decode((string) $row['content_json'], true, 64, JSON_THROW_ON_ERROR);
        unset($row['content_json']);

        return $row;
    }

    /** @return array<string, mixed> */
    private function lockResource(string $workspaceId, string $resourceId): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT resource.id,
       (SELECT COALESCE(MAX(version.version_no), 0)
        FROM content_resource_versions version
        WHERE version.resource_id = resource.id AND version.workspace_id = resource.workspace_id) AS latest_version_no
FROM content_resources resource
WHERE resource.id = :resource AND resource.workspace_id = :workspace
  AND resource.deleted_at IS NULL AND resource.archived_at IS NULL
FOR UPDATE
SQL);
        $query->execute(['resource' => $resourceId, 'workspace' => $workspaceId]);
        $row = $query->fetch();
        if ($row === false) {
            throw new PlatformException('resource_not_found', 'Resource was not found.', 404);
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function version(string $workspaceId, string $resourceId, string $versionId, bool $lock = false): array
    {
        $lockClause = $lock ? 'FOR UPDATE' : '';
        $query = $this->database->prepare(<<<SQL
SELECT id, version_no, status, created_by_user_id
FROM content_resource_versions
WHERE id = :version AND resource_id = :resource AND workspace_id = :workspace
{$lockClause}
SQL);
        $query->execute(['version' => $versionId, 'resource' => $resourceId, 'workspace' => $workspaceId]);
        $row = $query->fetch();
        if ($row === false) {
            throw new PlatformException('resource_version_not_found', 'Resource version was not found.', 404);
        }

        return $row;
    }

    private function resourceTypeId(string $typeKey): string
    {
        $typeKey = $this->key($typeKey, 'resource_type_invalid');
        $query = $this->database->prepare("SELECT id FROM content_resource_types WHERE type_key = :type AND status = 'active'");
        $query->execute(['type' => $typeKey]);
        $id = $query->fetchColumn();
        if ($id === false) {
            throw new PlatformException('resource_type_not_found', 'Resource type is unavailable.', 422);
        }

        return (string) $id;
    }

    /** @param array<string, mixed> $metadata @return array{term_id:?string,course_id:?string,session_id:?string,topic:?string,professor_name:?string} */
    private function academicMetadata(string $workspaceId, array $metadata): array
    {
        $term = $this->optionalUuid($metadata['term_id'] ?? null, 'term_id_invalid');
        $course = $this->optionalUuid($metadata['course_id'] ?? null, 'course_id_invalid');
        $session = $this->optionalUuid($metadata['session_id'] ?? null, 'session_id_invalid');
        if ($term !== null) {
            $this->assertWorkspaceEntity('academic_terms', $term, $workspaceId, 'term_not_found');
        }
        if ($course !== null) {
            $this->assertWorkspaceEntity('academic_courses', $course, $workspaceId, 'course_not_found');
        }
        if ($session !== null) {
            $query = $this->database->prepare(<<<'SQL'
SELECT offering.course_id, offering.term_id
FROM academic_course_sessions session
JOIN academic_course_offerings offering ON offering.id = session.offering_id AND offering.workspace_id = session.workspace_id
WHERE session.id = :session AND session.workspace_id = :workspace
SQL);
            $query->execute(['session' => $session, 'workspace' => $workspaceId]);
            $parent = $query->fetch();
            if ($parent === false || ($course !== null && !hash_equals((string) $parent['course_id'], $course)) || ($term !== null && !hash_equals((string) $parent['term_id'], $term))) {
                throw new PlatformException('session_metadata_mismatch', 'Session metadata does not belong to the selected course/term.', 422);
            }
        }

        return [
            'term_id' => $term, 'course_id' => $course, 'session_id' => $session,
            'topic' => $this->optionalText($metadata['topic'] ?? null, 200, 'topic_invalid'),
            'professor_name' => $this->optionalText($metadata['professor_name'] ?? null, 200, 'professor_name_invalid'),
        ];
    }

    /** @param array{term_id:?string,course_id:?string,session_id:?string,topic:?string,professor_name:?string} $academic */
    private function bindResource(string $workspaceId, string $resourceId, array $academic): void
    {
        $bindingType = 'workspace';
        $target = $workspaceId;
        $course = null;
        $session = null;
        if ($academic['session_id'] !== null) {
            $bindingType = 'course_session';
            $target = $academic['session_id'];
            $session = $academic['session_id'];
        } elseif ($academic['course_id'] !== null) {
            $bindingType = 'course';
            $target = $academic['course_id'];
            $course = $academic['course_id'];
        }
        $this->execute(<<<'SQL'
INSERT INTO content_resource_bindings (
    id, workspace_id, resource_id, binding_type, target_entity_id,
    course_id, offering_id, session_id, created_at
) VALUES (
    :id, :workspace, :resource, :binding_type, :target,
    :course, NULL, :session, UTC_TIMESTAMP(6)
)
SQL, [
            'id' => Uuid::v7(), 'workspace' => $workspaceId, 'resource' => $resourceId,
            'binding_type' => $bindingType, 'target' => $target, 'course' => $course, 'session' => $session,
        ]);
    }

    /** @param array{term_id:?string,course_id:?string,session_id:?string,topic:?string,professor_name:?string} $academic */
    private function updateBinding(string $workspaceId, string $resourceId, array $academic): void
    {
        $bindingType = 'workspace';
        $target = $workspaceId;
        $course = null;
        $session = null;
        if ($academic['session_id'] !== null) {
            $bindingType = 'course_session';
            $target = $academic['session_id'];
            $session = $academic['session_id'];
        } elseif ($academic['course_id'] !== null) {
            $bindingType = 'course';
            $target = $academic['course_id'];
            $course = $academic['course_id'];
        }
        $this->execute(<<<'SQL'
UPDATE content_resource_bindings
SET binding_type = :binding_type, target_entity_id = :target, course_id = :course,
    offering_id = NULL, session_id = :session, archived_at = NULL
WHERE resource_id = :resource AND workspace_id = :workspace AND archived_at IS NULL
SQL, [
            'binding_type' => $bindingType, 'target' => $target, 'course' => $course,
            'session' => $session, 'resource' => $resourceId, 'workspace' => $workspaceId,
        ]);
    }

    private function workspaceScopeId(string $workspaceId): string
    {
        $query = $this->database->prepare("SELECT id FROM rbac_scopes WHERE scope_type = 'workspace' AND entity_id = :entity_workspace AND workspace_id = :workspace AND archived_at IS NULL");
        $query->execute(['entity_workspace' => $workspaceId, 'workspace' => $workspaceId]);
        $id = $query->fetchColumn();
        if ($id === false) {
            throw new PlatformException('workspace_scope_missing', 'Workspace scope is unavailable.', 409);
        }

        return (string) $id;
    }

    private function targetScope(string $workspaceId, mixed $value): string
    {
        $scopeId = $value === null || $value === '' ? $this->workspaceScopeId($workspaceId) : $this->optionalUuid($value, 'target_scope_invalid');
        $query = $this->database->prepare('SELECT id FROM rbac_scopes WHERE id = :id AND workspace_id = :workspace AND archived_at IS NULL');
        $query->execute(['id' => $scopeId, 'workspace' => $workspaceId]);
        if ($query->fetchColumn() === false) {
            throw new PlatformException('target_scope_not_found', 'Target entitlement scope does not belong to this workspace.', 422);
        }

        return (string) $scopeId;
    }

    private function assertWorkspaceEntity(string $table, string $id, string $workspaceId, string $code): void
    {
        $allowed = ['academic_terms', 'academic_courses'];
        if (!in_array($table, $allowed, true)) {
            throw new \LogicException('Unsupported content metadata entity.');
        }
        $query = $this->database->prepare("SELECT 1 FROM {$table} WHERE id = :id AND workspace_id = :workspace LIMIT 1");
        $query->execute(['id' => $id, 'workspace' => $workspaceId]);
        if ($query->fetchColumn() === false) {
            throw new PlatformException($code, 'Academic metadata does not belong to this workspace.', 422);
        }
    }

    private function sourceReference(string $sourceKind, array $metadata): ?string
    {
        if (!in_array($sourceKind, ['imported', 'generated', 'forked'], true)) {
            return null;
        }

        return $this->optionalText($metadata['source_reference'] ?? null, 255, 'source_reference_invalid');
    }

    private function downloadTtl(mixed $value): int
    {
        $ttl = filter_var($value, FILTER_VALIDATE_INT);
        if ($ttl === false || $ttl < 30 || $ttl > 900) {
            throw new PlatformException('download_ttl_invalid', 'Download TTL must be between 30 and 900 seconds.', 422);
        }

        return $ttl;
    }

    /** @param array<string, scalar|null> $payload */
    private function outbox(string $workspaceId, string $aggregateType, string $aggregateId, string $eventType, array $payload): void
    {
        $this->execute(<<<'SQL'
INSERT INTO outbox_events (
    id, scope_type, workspace_id, aggregate_type, aggregate_id, event_type,
    payload_json, occurred_at, available_at
) VALUES (
    :id, 'workspace', :workspace, :aggregate_type, :aggregate_id, :event_type,
    :payload, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
)
SQL, [
            'id' => Uuid::v7(), 'workspace' => $workspaceId, 'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId, 'event_type' => $eventType,
            'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
    }

    private function key(string $value, string $code): string
    {
        $value = strtolower(trim($value));
        if (!preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $value)) {
            throw new PlatformException($code, 'Key format is invalid.', 422);
        }

        return $value;
    }

    private function boundedText(string $value, int $maximum, string $code): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $maximum) {
            throw new PlatformException($code, 'Text value is outside the permitted range.', 422);
        }

        return $value;
    }

    private function optionalText(mixed $value, int $maximum, string $code): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new PlatformException($code, 'Text value is invalid.', 422);
        }

        return $this->boundedText($value, $maximum, $code);
    }

    private function optionalUuid(mixed $value, string $code): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value)) {
            throw new PlatformException($code, 'Identifier must be a UUID.', 422);
        }

        return strtolower($value);
    }

    /** @param array<string, scalar|null> $parameters */
    private function execute(string $sql, array $parameters): void
    {
        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);
    }
}
