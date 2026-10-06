<?php

declare(strict_types=1);

namespace Fanoos\Platform\Identity;

use PDO;

/**
 * The one definition of "an owner of the installation": a live role
 * assignment on the platform scope. AuthService::isPlatformOperator() and
 * any service without an AuthService (ExamService, for one) ask this, so
 * the answer cannot drift between them.
 */
final class PlatformOperators
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function isOperator(string $userId): bool
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT 1
FROM rbac_role_assignments assignment
JOIN rbac_scopes scope ON scope.id = assignment.scope_id
JOIN rbac_role_templates role ON role.id = assignment.role_template_id
WHERE assignment.user_id = :user
  AND scope.scope_type = 'platform'
  AND scope.archived_at IS NULL
  AND role.status = 'active'
  AND assignment.revoked_at IS NULL
  AND assignment.valid_from <= UTC_TIMESTAMP(6)
  AND (assignment.valid_until IS NULL OR assignment.valid_until > UTC_TIMESTAMP(6))
LIMIT 1
SQL);
        $query->execute(['user' => $userId]);

        return $query->fetchColumn() !== false;
    }
}
