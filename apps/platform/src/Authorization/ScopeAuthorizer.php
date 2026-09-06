<?php

declare(strict_types=1);

namespace Fanoos\Platform\Authorization;

use JsonException;
use PDO;

final class ScopeAuthorizer
{
    public const POLICY_VERSION = 'scope-rbac-v1';

    public function __construct(private readonly PDO $database)
    {
    }

    public function decide(
        string $userId,
        string $permissionKey,
        string $scopeType,
        string $scopeId,
        ?string $workspaceId,
    ): AuthorizationDecision {
        $user = $this->database->prepare(
            "SELECT id FROM iam_users WHERE id = :id AND status = 'active' AND deleted_at IS NULL",
        );
        $user->execute(['id' => $userId]);
        if ($user->fetchColumn() === false) {
            return AuthorizationDecision::deny('actor_inactive');
        }

        $scopeQuery = $this->database->prepare(<<<'SQL'
SELECT id, scope_type, entity_id, workspace_id
FROM rbac_scopes
WHERE id = :id AND archived_at IS NULL
SQL);
        $scopeQuery->execute(['id' => $scopeId]);
        $scope = $scopeQuery->fetch();
        if ($scope === false) {
            return AuthorizationDecision::deny('scope_not_found');
        }

        if ($scope['scope_type'] !== $scopeType) {
            return AuthorizationDecision::deny('scope_type_mismatch');
        }

        $canonicalWorkspaceId = $scope['workspace_id'] === null ? null : (string) $scope['workspace_id'];
        if ($canonicalWorkspaceId !== $workspaceId) {
            return AuthorizationDecision::deny('workspace_scope_mismatch');
        }

        $ancestorScopeIds = $this->canonicalAncestorScopeIds(
            $scopeType,
            (string) $scope['entity_id'],
            $scopeId,
            $workspaceId,
        );
        if ($ancestorScopeIds === []) {
            return AuthorizationDecision::deny('scope_hierarchy_mismatch');
        }

        $ancestorParameters = [];
        $ancestorPlaceholders = [];
        foreach ($ancestorScopeIds as $index => $ancestorScopeId) {
            $parameter = 'ancestor_' . $index;
            $ancestorPlaceholders[] = ':' . $parameter;
            $ancestorParameters[$parameter] = $ancestorScopeId;
        }

        $assignments = $this->database->prepare(sprintf(<<<'SQL'
SELECT
    assignment.id AS assignment_id,
    role.allowed_scope_types,
    role.requires_workspace_membership,
    assignment_scope.scope_type AS assignment_scope_type
FROM rbac_role_assignments assignment
JOIN rbac_scopes assignment_scope ON assignment_scope.id = assignment.scope_id
JOIN rbac_role_templates role ON role.id = assignment.role_template_id
JOIN rbac_role_permissions role_permission ON role_permission.role_template_id = role.id
JOIN rbac_permissions permission ON permission.id = role_permission.permission_id
WHERE assignment.user_id = :user_id
  AND assignment.scope_id IN (%s)
  AND assignment_scope.archived_at IS NULL
  AND permission.permission_key = :permission_key
  AND role.status = 'active'
  AND assignment.revoked_at IS NULL
  AND assignment.valid_from <= UTC_TIMESTAMP(6)
  AND (assignment.valid_until IS NULL OR assignment.valid_until > UTC_TIMESTAMP(6))
SQL, implode(', ', $ancestorPlaceholders)));
        $assignments->execute(array_merge($ancestorParameters, [
            'user_id' => $userId,
            'permission_key' => $permissionKey,
        ]));

        $membershipChecked = false;
        $hasMembership = false;
        $membershipMissing = false;

        while (($assignment = $assignments->fetch()) !== false) {
            try {
                $allowedScopeTypes = json_decode(
                    (string) $assignment['allowed_scope_types'],
                    true,
                    16,
                    JSON_THROW_ON_ERROR,
                );
            } catch (JsonException) {
                continue;
            }

            if (!is_array($allowedScopeTypes) || !in_array($assignment['assignment_scope_type'], $allowedScopeTypes, true)) {
                continue;
            }

            if ((bool) $assignment['requires_workspace_membership'] && $workspaceId !== null) {
                if (!$membershipChecked) {
                    $hasMembership = $this->hasActiveMembership($userId, $workspaceId);
                    $membershipChecked = true;
                }
                if (!$hasMembership) {
                    $membershipMissing = true;
                    continue;
                }
            }

            return new AuthorizationDecision(
                true,
                'granted_by_scoped_role',
                self::POLICY_VERSION,
                [(string) $assignment['assignment_id']],
            );
        }

        return AuthorizationDecision::deny($membershipMissing ? 'active_membership_required' : 'permission_not_granted');
    }

    /** @return list<string> */
    private function canonicalAncestorScopeIds(
        string $scopeType,
        string $entityId,
        string $targetScopeId,
        ?string $workspaceId,
    ): array {
        $entities = [];

        if (in_array($scopeType, ['course_offering', 'resource', 'assessment'], true)) {
            if ($workspaceId === null || !$this->childBelongsToWorkspace($scopeType, $entityId, $workspaceId)) {
                return [];
            }
            $entities[$scopeType] = $entityId;
            $entities += $this->workspaceHierarchy($workspaceId);
        } elseif ($scopeType === 'workspace') {
            if ($workspaceId === null || $entityId !== $workspaceId) {
                return [];
            }
            $entities = $this->workspaceHierarchy($workspaceId);
        } elseif ($scopeType === 'platform') {
            if ($workspaceId !== null || $targetScopeId !== '00000000-0000-7000-8000-000000000001') {
                return [];
            }
        } else {
            if ($workspaceId !== null) {
                return [];
            }
            $entities = $this->directoryHierarchy($scopeType, $entityId);
        }

        if ($scopeType !== 'platform' && $entities === []) {
            return [];
        }

        $entities['platform'] = '00000000-0000-7000-8000-000000000001';
        $scopeIds = [];
        $findScope = $this->database->prepare(<<<'SQL'
SELECT id
FROM rbac_scopes
WHERE scope_type = :scope_type
  AND entity_id = :entity_id
  AND workspace_id <=> :workspace_id
  AND archived_at IS NULL
SQL);

        foreach ($entities as $type => $id) {
            $scopeWorkspaceId = in_array($type, ['workspace', 'course_offering', 'resource', 'assessment'], true)
                ? $workspaceId
                : null;
            $findScope->execute([
                'scope_type' => $type,
                'entity_id' => $id,
                'workspace_id' => $scopeWorkspaceId,
            ]);
            $found = $findScope->fetchColumn();
            if ($found !== false) {
                $scopeIds[] = (string) $found;
            }
        }

        return in_array($targetScopeId, $scopeIds, true) ? array_values(array_unique($scopeIds)) : [];
    }

    /** @return array<string, string> */
    private function workspaceHierarchy(string $workspaceId): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT
    workspace.id AS workspace,
    cohort.id AS cohort,
    program.id AS program,
    faculty.id AS faculty,
    institution.id AS institution
FROM tenant_workspaces workspace
JOIN directory_cohorts cohort ON cohort.id = workspace.cohort_id
JOIN directory_programs program ON program.id = cohort.program_id
JOIN directory_faculties faculty ON faculty.id = program.faculty_id
JOIN directory_institutions institution ON institution.id = faculty.institution_id
WHERE workspace.id = :workspace_id
  AND workspace.status = 'active'
  AND workspace.archived_at IS NULL
SQL);
        $query->execute(['workspace_id' => $workspaceId]);
        $row = $query->fetch();

        return $row === false ? [] : array_map('strval', $row);
    }

    /** @return array<string, string> */
    private function directoryHierarchy(string $scopeType, string $entityId): array
    {
        $queries = [
            'institution' => 'SELECT institution.id AS institution FROM directory_institutions institution WHERE institution.id = :entity_id',
            'faculty' => 'SELECT faculty.id AS faculty, institution.id AS institution FROM directory_faculties faculty JOIN directory_institutions institution ON institution.id = faculty.institution_id WHERE faculty.id = :entity_id',
            'program' => 'SELECT program.id AS program, faculty.id AS faculty, institution.id AS institution FROM directory_programs program JOIN directory_faculties faculty ON faculty.id = program.faculty_id JOIN directory_institutions institution ON institution.id = faculty.institution_id WHERE program.id = :entity_id',
            'cohort' => 'SELECT cohort.id AS cohort, program.id AS program, faculty.id AS faculty, institution.id AS institution FROM directory_cohorts cohort JOIN directory_programs program ON program.id = cohort.program_id JOIN directory_faculties faculty ON faculty.id = program.faculty_id JOIN directory_institutions institution ON institution.id = faculty.institution_id WHERE cohort.id = :entity_id',
        ];

        if (!isset($queries[$scopeType])) {
            return [];
        }

        $query = $this->database->prepare($queries[$scopeType]);
        $query->execute(['entity_id' => $entityId]);
        $row = $query->fetch();

        return $row === false ? [] : array_map('strval', $row);
    }

    private function childBelongsToWorkspace(string $scopeType, string $entityId, string $workspaceId): bool
    {
        $tables = [
            'course_offering' => 'academic_course_offerings',
            'resource' => 'content_resources',
            'assessment' => 'exam_assessments',
        ];
        $query = $this->database->prepare(sprintf(
            'SELECT 1 FROM %s WHERE id = :entity_id AND workspace_id = :workspace_id LIMIT 1',
            $tables[$scopeType],
        ));
        $query->execute(['entity_id' => $entityId, 'workspace_id' => $workspaceId]);

        return $query->fetchColumn() !== false;
    }

    private function hasActiveMembership(string $userId, string $workspaceId): bool
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT 1
FROM tenant_workspace_memberships
WHERE user_id = :user_id
  AND workspace_id = :workspace_id
  AND status = 'active'
  AND (ended_at IS NULL OR ended_at > UTC_TIMESTAMP(6))
LIMIT 1
SQL);
        $query->execute(['user_id' => $userId, 'workspace_id' => $workspaceId]);

        return $query->fetchColumn() !== false;
    }
}
