<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Support\PlatformException;
use PDO;

final class ProtectedResourceAuthorizer
{
    public function __construct(
        private readonly PDO $database,
        private readonly ScopeAuthorizer $authorizer,
        private readonly EntitlementService $entitlements,
    ) {
    }

    /** @return array{allowed:bool,reason:string,resource_version_id:?string,object_id:?string,download_ttl_seconds:?int} */
    public function decide(string $userId, string $workspaceId, string $resourceId): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT resource.owner_user_id, resource.visibility, resource.lifecycle_status, resource.current_version_no,
       scope.id AS resource_scope_id, policy.target_scope_id, policy.requires_entitlement,
       policy.download_ttl_seconds, version.id AS resource_version_id, version.object_id,
       object_record.status AS object_status
FROM content_resources resource
JOIN rbac_scopes scope ON scope.scope_type = 'resource' AND scope.entity_id = resource.id
 AND scope.workspace_id = resource.workspace_id AND scope.archived_at IS NULL
LEFT JOIN content_access_policies policy ON policy.resource_id = resource.id AND policy.workspace_id = resource.workspace_id
LEFT JOIN content_resource_versions version ON version.resource_id = resource.id AND version.workspace_id = resource.workspace_id
 AND version.version_no = resource.current_version_no AND version.status = 'approved'
LEFT JOIN content_objects object_record ON object_record.id = version.object_id AND object_record.workspace_id = version.workspace_id
WHERE resource.id = :resource AND resource.workspace_id = :workspace
  AND resource.archived_at IS NULL AND resource.deleted_at IS NULL
LIMIT 1
SQL);
        $query->execute(['resource' => $resourceId, 'workspace' => $workspaceId]);
        $row = $query->fetch();
        if ($row === false || $row['lifecycle_status'] !== 'published') {
            return ['allowed' => false, 'reason' => 'resource_unavailable', 'resource_version_id' => null, 'object_id' => null, 'download_ttl_seconds' => null];
        }
        if ($row['visibility'] === 'private' && !hash_equals((string) $row['owner_user_id'], $userId)) {
            return ['allowed' => false, 'reason' => 'resource_private', 'resource_version_id' => null, 'object_id' => null, 'download_ttl_seconds' => null];
        }
        $permission = $this->authorizer->decide($userId, 'resource.view', 'resource', (string) $row['resource_scope_id'], $workspaceId);
        if (!$permission->allowed) {
            return ['allowed' => false, 'reason' => 'rbac_denied', 'resource_version_id' => null, 'object_id' => null, 'download_ttl_seconds' => null];
        }
        if ($row['resource_version_id'] === null) {
            return ['allowed' => false, 'reason' => 'resource_version_unavailable', 'resource_version_id' => null, 'object_id' => null, 'download_ttl_seconds' => null];
        }
        if ($row['requires_entitlement'] !== null && (bool) $row['requires_entitlement']
            && !$this->entitlements->has($userId, $workspaceId, (string) $row['target_scope_id'])) {
            return ['allowed' => false, 'reason' => 'entitlement_required', 'resource_version_id' => null, 'object_id' => null, 'download_ttl_seconds' => null];
        }
        if ($row['object_id'] !== null && $row['object_status'] !== 'verified') {
            throw new PlatformException('object_not_verified', 'Protected object is not ready for delivery.', 409);
        }
        return [
            'allowed' => true, 'reason' => 'authorized',
            'resource_version_id' => $row['resource_version_id'] === null ? null : (string) $row['resource_version_id'],
            'object_id' => $row['object_id'] === null ? null : (string) $row['object_id'],
            'download_ttl_seconds' => $row['download_ttl_seconds'] === null ? 300 : (int) $row['download_ttl_seconds'],
        ];
    }
}
