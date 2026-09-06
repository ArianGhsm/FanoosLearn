<?php

declare(strict_types=1);

namespace Fanoos\Platform\Entitlements;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;

final class EntitlementService
{
    public function __construct(
        private readonly PDO $database,
        private readonly AccessGate $access,
        private readonly AuditLogger $audit,
    ) {
    }

    public function has(string $userId, string $workspaceId, string $targetScopeId): bool
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT 1
FROM entitlement_grants grant_record
JOIN tenant_workspace_memberships membership
  ON membership.workspace_id = grant_record.workspace_id AND membership.user_id = grant_record.subject_user_id
WHERE grant_record.workspace_id = :workspace AND grant_record.subject_user_id = :user
  AND grant_record.target_scope_id = :scope AND grant_record.revoked_at IS NULL
  AND grant_record.valid_from <= UTC_TIMESTAMP(6)
  AND (grant_record.valid_until IS NULL OR grant_record.valid_until > UTC_TIMESTAMP(6))
  AND membership.status = 'active' AND (membership.ended_at IS NULL OR membership.ended_at > UTC_TIMESTAMP(6))
LIMIT 1
SQL);
        $query->execute(['workspace' => $workspaceId, 'user' => $userId, 'scope' => $targetScopeId]);
        return $query->fetchColumn() !== false;
    }

    public function grantByAdministrator(
        string $actorUserId,
        string $workspaceId,
        string $subjectUserId,
        string $targetScopeId,
        string $reason,
    ): string {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'entitlement.grant');
        return $this->grant($workspaceId, $subjectUserId, $targetScopeId, 'administrator', null, $actorUserId, $reason);
    }

    public function grantFromOrder(string $workspaceId, string $subjectUserId, string $targetScopeId, string $orderId): string
    {
        return $this->grant($workspaceId, $subjectUserId, $targetScopeId, 'order', $orderId, null, 'verified_payment');
    }

    public function revoke(string $actorUserId, string $workspaceId, string $grantId, string $reason): void
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'entitlement.grant');
        if (trim($reason) === '') {
            throw new PlatformException('revoke_reason_required', 'A revoke reason is required.', 422);
        }
        $update = $this->database->prepare(<<<'SQL'
UPDATE entitlement_grants
SET revoked_at = UTC_TIMESTAMP(6), revoke_reason = :reason, version = version + 1
WHERE id = :id AND workspace_id = :workspace AND revoked_at IS NULL
SQL);
        $update->execute(['reason' => trim($reason), 'id' => $grantId, 'workspace' => $workspaceId]);
        if ($update->rowCount() !== 1) {
            throw new PlatformException('entitlement_not_found', 'Active entitlement was not found.', 404);
        }
        $this->audit->record($workspaceId, $actorUserId, 'entitlement.revoke', 'entitlement_grant', $grantId, 'success', ['reason' => trim($reason)]);
    }

    private function grant(
        string $workspaceId,
        string $subjectUserId,
        string $targetScopeId,
        string $sourceType,
        ?string $sourceId,
        ?string $actorUserId,
        string $reason,
    ): string {
        $keyMaterial = implode('|', [$workspaceId, $subjectUserId, $targetScopeId, $sourceType, $sourceId ?? $reason]);
        $grantKey = hash('sha256', $keyMaterial, true);
        $existing = $this->database->prepare('SELECT id, revoked_at FROM entitlement_grants WHERE grant_key = :key FOR UPDATE');
        $existing->bindValue(':key', $grantKey, PDO::PARAM_LOB);
        $existing->execute();
        $row = $existing->fetch();
        if ($row !== false) {
            if ($row['revoked_at'] !== null) {
                $restore = $this->database->prepare('UPDATE entitlement_grants SET revoked_at = NULL, revoke_reason = NULL, valid_from = UTC_TIMESTAMP(6), valid_until = NULL, version = version + 1 WHERE id = :id');
                $restore->execute(['id' => $row['id']]);
            }
            return (string) $row['id'];
        }

        $membership = $this->database->prepare("SELECT 1 FROM tenant_workspace_memberships WHERE workspace_id = :workspace AND user_id = :user AND status = 'active' LIMIT 1");
        $membership->execute(['workspace' => $workspaceId, 'user' => $subjectUserId]);
        if ($membership->fetchColumn() === false) {
            throw new PlatformException('entitlement_subject_not_member', 'Entitlement subject is not an active workspace member.', 422);
        }
        $id = Uuid::v7();
        $insert = $this->database->prepare(<<<'SQL'
INSERT INTO entitlement_grants (
    id, grant_key, workspace_id, subject_user_id, target_scope_id,
    source_type, source_id, version, valid_from, created_at
) VALUES (:id, :grant_key, :workspace, :subject, :scope, :source_type, :source_id, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL);
        $insert->bindValue(':id', $id);
        $insert->bindValue(':grant_key', $grantKey, PDO::PARAM_LOB);
        $insert->bindValue(':workspace', $workspaceId);
        $insert->bindValue(':subject', $subjectUserId);
        $insert->bindValue(':scope', $targetScopeId);
        $insert->bindValue(':source_type', $sourceType);
        $insert->bindValue(':source_id', $sourceId);
        $insert->execute();
        $this->audit->record($workspaceId, $actorUserId, 'entitlement.grant', 'entitlement_grant', $id, 'success', ['source_type' => $sourceType, 'reason' => $reason]);
        return $id;
    }
}
