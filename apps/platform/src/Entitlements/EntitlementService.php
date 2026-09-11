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
        return $this->status($userId, $workspaceId, $targetScopeId) === 'active';
    }

    /**
     * Return the server-owned access state for one scope. Payment state is
     * intentionally not consulted here: a paid order is not itself access.
     */
    public function status(string $userId, string $workspaceId, string $targetScopeId): string
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT
    MAX(CASE WHEN grant_record.revoked_at IS NULL
              AND grant_record.valid_from <= UTC_TIMESTAMP(6)
              AND (grant_record.valid_until IS NULL OR grant_record.valid_until > UTC_TIMESTAMP(6))
              AND membership.status = 'active'
              AND (membership.ended_at IS NULL OR membership.ended_at > UTC_TIMESTAMP(6))
             THEN 1 ELSE 0 END) AS has_active,
    MAX(CASE WHEN grant_record.revoked_at IS NOT NULL THEN 1 ELSE 0 END) AS has_revoked,
    MAX(CASE WHEN grant_record.revoked_at IS NULL
              AND grant_record.valid_until IS NOT NULL
              AND grant_record.valid_until <= UTC_TIMESTAMP(6)
             THEN 1 ELSE 0 END) AS has_expired
FROM entitlement_grants grant_record
LEFT JOIN tenant_workspace_memberships membership
  ON membership.workspace_id = grant_record.workspace_id AND membership.user_id = grant_record.subject_user_id
WHERE grant_record.workspace_id = :workspace
  AND grant_record.subject_user_id = :user
  AND grant_record.target_scope_id = :scope
SQL);
        $query->execute(['workspace' => $workspaceId, 'user' => $userId, 'scope' => $targetScopeId]);
        $row = $query->fetch() ?: [];
        if ((int) ($row['has_active'] ?? 0) === 1) {
            return 'active';
        }
        if ((int) ($row['has_revoked'] ?? 0) === 1) {
            return 'revoked';
        }
        if ((int) ($row['has_expired'] ?? 0) === 1) {
            return 'expired';
        }

        return 'none';
    }

    /** @return list<array<string, mixed>> */
    public function library(string $actorUserId, string $workspaceId): array
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'commerce.purchase');
        $query = $this->database->prepare(<<<'SQL'
SELECT grant_record.id AS grant_id, grant_record.target_scope_id,
       grant_record.source_type, grant_record.valid_from, grant_record.valid_until,
       grant_record.revoked_at, grant_record.created_at, scope.scope_type,
       resource.title AS resource_title,
       resource_type.type_key AS resource_type,
       course.title AS course_title
FROM entitlement_grants grant_record
JOIN rbac_scopes scope ON scope.id = grant_record.target_scope_id
 AND scope.workspace_id = grant_record.workspace_id AND scope.archived_at IS NULL
LEFT JOIN content_resources resource
  ON scope.scope_type = 'resource' AND resource.id = scope.entity_id
 AND resource.workspace_id = scope.workspace_id AND resource.deleted_at IS NULL
LEFT JOIN content_resource_types resource_type ON resource_type.id = resource.resource_type_id
LEFT JOIN academic_course_offerings offering
  ON scope.scope_type = 'course_offering' AND offering.id = scope.entity_id
 AND offering.workspace_id = scope.workspace_id
LEFT JOIN academic_courses course ON course.id = offering.course_id
 AND course.workspace_id = offering.workspace_id
WHERE grant_record.workspace_id = :workspace AND grant_record.subject_user_id = :user
ORDER BY grant_record.created_at DESC, grant_record.id DESC
LIMIT 200
SQL);
        $query->execute(['workspace' => $workspaceId, 'user' => $actorUserId]);
        $rows = [];
        foreach ($query->fetchAll() as $row) {
            $status = $this->rowStatus($row);
            $scopeType = (string) $row['scope_type'];
            $rows[] = [
                'grant_id' => (string) $row['grant_id'],
                'status' => $status,
                'scope_label' => $this->scopeLabel($scopeType),
                'resource_title' => $row['resource_title'] === null ? null : (string) $row['resource_title'],
                'resource_type_label' => $this->resourceTypeLabel($row['resource_type'] === null ? null : (string) $row['resource_type']),
                'course_title' => $row['course_title'] === null ? null : (string) $row['course_title'],
                'source_label' => $this->sourceLabel((string) $row['source_type']),
                'valid_from' => (string) $row['valid_from'],
                'valid_until' => $row['valid_until'] === null ? null : (string) $row['valid_until'],
                'revoked_at' => $row['revoked_at'] === null ? null : (string) $row['revoked_at'],
            ];
        }

        return $rows;
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

    /** @param array<string, mixed> $row */
    private function rowStatus(array $row): string
    {
        if ($row['revoked_at'] !== null) {
            return 'revoked';
        }
        if ($row['valid_from'] > gmdate('Y-m-d H:i:s.u')) {
            return 'pending';
        }
        if ($row['valid_until'] !== null && $row['valid_until'] <= gmdate('Y-m-d H:i:s.u')) {
            return 'expired';
        }

        return 'active';
    }

    private function scopeLabel(string $scopeType): string
    {
        return match ($scopeType) {
            'resource' => 'محتوای آموزشی',
            'course_offering' => 'درس',
            'assessment' => 'آزمون',
            'workspace' => 'فضای آموزشی',
            default => 'دسترسی آموزشی',
        };
    }

    private function resourceTypeLabel(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        return [
            'lecture_note' => 'جزوه / یادداشت کامل', 'discipline_note' => 'یادداشت ساختاریافته',
            'summary' => 'خلاصه', 'question_bank' => 'بانک سؤال', 'past_exam' => 'آزمون گذشته',
            'flashcards' => 'فلش‌کارت', 'audio' => 'صوت', 'transcript' => 'متن پیاده‌سازی‌شده',
            'slide_reference' => 'اسلاید / مرجع',
        ][$type] ?? 'محتوای آموزشی';
    }

    private function sourceLabel(string $source): string
    {
        return match ($source) {
            'order' => 'خرید',
            'administrator' => 'اعطای مدیر',
            'policy' => 'سیاست دسترسی',
            'migration' => 'انتقال',
            default => 'منبع دسترسی',
        };
    }
}
