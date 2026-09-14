<?php

declare(strict_types=1);

namespace Fanoos\Platform\Identity;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\TextNormalizer;
use Fanoos\Platform\Support\Transaction;
use Fanoos\Platform\Support\Uuid;
use PDO;

final class AuthService
{
    public function __construct(
        private readonly PDO $database,
        private readonly PasswordHasher $passwordHasher,
        private readonly AuditLogger $audit,
        private readonly int $sessionLifetimeSeconds = 43200,
        private readonly int $maximumFailures = 5,
        private readonly int $lockSeconds = 900,
    ) {
    }

    /** @param array<string, scalar|null> $client */
    public function login(string $identifier, string $password, string $source, array $client = []): AuthenticatedSession
    {
        [$identifierType, $normalized] = $this->normalizeIdentifier($identifier);
        $identifierDigest = hash('sha256', $identifierType . ':' . $normalized, true);
        $sourceDigest = hash('sha256', trim($source), true);

        $result = Transaction::run($this->database, function () use (
            $identifierType,
            $normalized,
            $identifierDigest,
            $sourceDigest,
            $password,
            $client,
        ): AuthenticatedSession|PlatformException {
            $attempt = $this->database->prepare(<<<'SQL'
SELECT failure_count, locked_until
FROM iam_login_attempts
WHERE identifier_digest = :identifier AND source_digest = :source
FOR UPDATE
SQL);
            $attempt->bindValue(':identifier', $identifierDigest, PDO::PARAM_LOB);
            $attempt->bindValue(':source', $sourceDigest, PDO::PARAM_LOB);
            $attempt->execute();
            $attemptRow = $attempt->fetch();
            if ($attemptRow !== false && $attemptRow['locked_until'] !== null && strtotime((string) $attemptRow['locked_until'] . ' UTC') > time()) {
                throw new PlatformException('login_throttled', 'Too many login attempts. Try again later.', 429);
            }

            $query = $this->database->prepare(<<<'SQL'
SELECT user.id AS user_id, authenticator.id AS authenticator_id, authenticator.secret_digest
FROM iam_user_identifiers identifier
JOIN iam_users user ON user.id = identifier.user_id
JOIN iam_authenticators authenticator ON authenticator.user_id = user.id
WHERE identifier.identifier_type = :identifier_type
  AND identifier.normalized_value = :normalized_value
  AND identifier.revoked_at IS NULL
  AND user.status = 'active'
  AND user.deleted_at IS NULL
  AND authenticator.authenticator_type = 'password'
  AND authenticator.revoked_at IS NULL
ORDER BY authenticator.created_at DESC
LIMIT 1
FOR UPDATE
SQL);
            $query->execute(['identifier_type' => $identifierType, 'normalized_value' => $normalized]);
            $account = $query->fetch();
            $valid = $account !== false && $this->passwordHasher->verify($password, (string) $account['secret_digest']);
            if (!$valid) {
                $this->recordFailure($identifierDigest, $sourceDigest, $attemptRow === false ? 0 : (int) $attemptRow['failure_count']);
                $this->audit->record(null, null, 'auth.login', 'account', null, 'denied', ['reason' => 'invalid_credentials']);
                return new PlatformException('invalid_credentials', 'The identifier or password is incorrect.', 401);
            }

            $clearAttempts = $this->database->prepare('DELETE FROM iam_login_attempts WHERE identifier_digest = :identifier AND source_digest = :source');
            $clearAttempts->bindValue(':identifier', $identifierDigest, PDO::PARAM_LOB);
            $clearAttempts->bindValue(':source', $sourceDigest, PDO::PARAM_LOB);
            $clearAttempts->execute();

            $secretDigest = (string) $account['secret_digest'];
            if ($this->passwordHasher->needsRehash($secretDigest)) {
                $rehash = $this->database->prepare('UPDATE iam_authenticators SET secret_digest = :digest, last_used_at = UTC_TIMESTAMP(6) WHERE id = :id');
                $rehash->execute(['digest' => $this->passwordHasher->hash($password), 'id' => $account['authenticator_id']]);
            } else {
                $touch = $this->database->prepare('UPDATE iam_authenticators SET last_used_at = UTC_TIMESTAMP(6) WHERE id = :id');
                $touch->execute(['id' => $account['authenticator_id']]);
            }

            $session = $this->establishSession((string) $account['user_id'], $client);
            $this->audit->record(null, (string) $account['user_id'], 'auth.login', 'session', $session->sessionId);
            return $session;
        });

        if ($result instanceof PlatformException) {
            throw $result;
        }
        return $result;
    }

    /**
     * Inserts a new iam_sessions row for an already-identified user and
     * returns it as an AuthenticatedSession. Shared by login() (after
     * password verification) and OwnerRecoveryService::redeem() (after
     * consuming a recovery token) so the two never drift into two slightly
     * different session shapes. Must be called from inside the caller's own
     * Transaction::run -- it does not open one itself.
     *
     * @param array<string, scalar|null> $client
     */
    public function establishSession(string $userId, array $client = []): AuthenticatedSession
    {
        $token = self::randomToken();
        $csrf = self::randomToken();
        $sessionId = Uuid::v7();
        $expiresAt = gmdate('Y-m-d H:i:s.u', time() + $this->sessionLifetimeSeconds);
        $session = $this->database->prepare(<<<'SQL'
INSERT INTO iam_sessions (
    id, user_id, selected_workspace_id, token_digest, csrf_token_digest,
    created_at, last_seen_at, expires_at, revoked_at, client_json
) VALUES (
    :id, :user_id, NULL, :token_digest, :csrf_digest,
    UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), :expires_at, NULL, :client_json
)
SQL);
        $session->bindValue(':id', $sessionId);
        $session->bindValue(':user_id', $userId);
        $session->bindValue(':token_digest', hash('sha256', $token, true), PDO::PARAM_LOB);
        $session->bindValue(':csrf_digest', hash('sha256', $csrf, true), PDO::PARAM_LOB);
        $session->bindValue(':expires_at', $expiresAt);
        $session->bindValue(':client_json', json_encode($client, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $session->execute();

        return new AuthenticatedSession($sessionId, $userId, $token, $csrf, null, $expiresAt);
    }

    public function authenticate(string $token): AuthenticatedSession
    {
        if ($token === '') {
            throw new PlatformException('unauthenticated', 'Authentication is required.', 401);
        }
        $query = $this->database->prepare(<<<'SQL'
SELECT session.id, session.user_id, session.selected_workspace_id, session.csrf_token_digest, session.expires_at
FROM iam_sessions session
JOIN iam_users user ON user.id = session.user_id
WHERE session.token_digest = :digest
  AND session.revoked_at IS NULL
  AND session.expires_at > UTC_TIMESTAMP(6)
  AND user.status = 'active'
  AND user.deleted_at IS NULL
LIMIT 1
SQL);
        $query->bindValue(':digest', hash('sha256', $token, true), PDO::PARAM_LOB);
        $query->execute();
        $row = $query->fetch();
        if ($row === false) {
            throw new PlatformException('unauthenticated', 'The session is invalid or expired.', 401);
        }
        $touch = $this->database->prepare('UPDATE iam_sessions SET last_seen_at = UTC_TIMESTAMP(6) WHERE id = :id AND last_seen_at < UTC_TIMESTAMP(6) - INTERVAL 1 MINUTE');
        $touch->execute(['id' => $row['id']]);

        return new AuthenticatedSession(
            (string) $row['id'],
            (string) $row['user_id'],
            $token,
            '',
            $row['selected_workspace_id'] === null ? null : (string) $row['selected_workspace_id'],
            (string) $row['expires_at'],
        );
    }

    public function requireCsrf(AuthenticatedSession $session, string $csrfToken): void
    {
        $query = $this->database->prepare('SELECT csrf_token_digest FROM iam_sessions WHERE id = :id AND revoked_at IS NULL');
        $query->execute(['id' => $session->sessionId]);
        $digest = $query->fetchColumn();
        if (!is_string($digest) || $csrfToken === '' || !hash_equals($digest, hash('sha256', $csrfToken, true))) {
            throw new PlatformException('csrf_failed', 'The CSRF token is invalid.', 403);
        }
    }

    public function logout(AuthenticatedSession $session): void
    {
        $statement = $this->database->prepare('UPDATE iam_sessions SET revoked_at = UTC_TIMESTAMP(6) WHERE id = :id AND revoked_at IS NULL');
        $statement->execute(['id' => $session->sessionId]);
        $this->audit->record(null, $session->userId, 'auth.logout', 'session', $session->sessionId);
    }

    /** @return array<string, mixed> */
    public function account(AuthenticatedSession $session): array
    {
        $user = $this->database->prepare('SELECT id, display_name, status, locale FROM iam_users WHERE id = :id AND deleted_at IS NULL');
        $user->execute(['id' => $session->userId]);
        $account = $user->fetch();
        if ($account === false) {
            throw new PlatformException('account_not_found', 'Account was not found.', 404);
        }

        // A platform operator is authorized in every workspace (the scope
        // chain adds the platform scope as an ancestor of everything), but
        // until now they could not *reach* one: this listing was
        // membership-only, so an owner with every permission in the system
        // still had nothing to select. Owners see every active workspace;
        // everyone else sees only the ones they belong to.
        $isPlatformOperator = $this->isPlatformOperator($session->userId);

        $workspaces = $this->database->prepare(<<<'SQL'
SELECT membership.id AS membership_id, workspace.id, workspace.slug, workspace.name, workspace.timezone_name,
       membership.status, membership.joined_at, membership.ended_at,
       cohort.id AS cohort_id, program.id AS program_id, faculty.id AS faculty_id,
       institution.id AS institution_id,
       institution.name AS institution_name, faculty.name AS faculty_name,
       program.name AS program_name, cohort.label AS cohort_label
FROM tenant_workspaces workspace
LEFT JOIN tenant_workspace_memberships membership
       ON membership.workspace_id = workspace.id
      AND membership.user_id = :user_id
      AND membership.status = 'active'
      AND (membership.ended_at IS NULL OR membership.ended_at > UTC_TIMESTAMP(6))
JOIN directory_cohorts cohort ON cohort.id = workspace.cohort_id
JOIN directory_programs program ON program.id = cohort.program_id
JOIN directory_faculties faculty ON faculty.id = program.faculty_id
JOIN directory_institutions institution ON institution.id = faculty.institution_id
WHERE workspace.status = 'active'
  AND workspace.archived_at IS NULL
  AND (membership.id IS NOT NULL OR :is_platform_operator = 1)
ORDER BY workspace.name, workspace.id
SQL);
        $workspaces->execute([
            'user_id' => $session->userId,
            'is_platform_operator' => $isPlatformOperator ? 1 : 0,
        ]);

        $workspaceRows = $workspaces->fetchAll();
        $selectedWorkspaceId = $session->selectedWorkspaceId;
        if ($selectedWorkspaceId !== null && !array_filter(
            $workspaceRows,
            static fn (array $workspace): bool => (string) $workspace['id'] === $selectedWorkspaceId,
        )) {
            $selectedWorkspaceId = null;
        }
        foreach ($workspaceRows as &$workspace) {
            $context = $this->workspaceRoleContext($session->userId, (string) $workspace['id']);
            $workspace['role_keys'] = $context['role_keys'];
            $workspace['permission_keys'] = $context['permission_keys'];
            $workspace['is_selected'] = $selectedWorkspaceId === (string) $workspace['id'];
        }
        unset($workspace);

        return ['user' => $account, 'selected_workspace_id' => $selectedWorkspaceId, 'workspaces' => $workspaceRows];
    }

    /**
     * Whether this account holds a live role assignment on the platform
     * scope -- the owners of the installation.
     *
     * Deliberately a role-assignment check rather than a hardcoded user id
     * or a role-key match: an owner is whoever has been granted the platform
     * scope, which is the same fact `ScopeAuthorizer` already decides
     * against, so the two cannot drift apart.
     *
     * Public so any other service that needs "is this account an owner of
     * the installation" (OwnerRecoveryService, for one) asks this exact
     * question rather than writing a second definition that can drift from
     * it -- a role-key match or a hardcoded user id would both be that
     * second definition.
     */
    public function isPlatformOperator(string $userId): bool
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

    public function selectWorkspace(AuthenticatedSession $session, string $workspaceId): void
    {
        $membership = $this->database->prepare(<<<'SQL'
SELECT 1 FROM tenant_workspace_memberships membership
JOIN tenant_workspaces workspace ON workspace.id = membership.workspace_id
WHERE membership.user_id = :user AND membership.workspace_id = :workspace
  AND membership.status = 'active'
  AND (membership.ended_at IS NULL OR membership.ended_at > UTC_TIMESTAMP(6))
  AND workspace.status = 'active'
  AND workspace.archived_at IS NULL
LIMIT 1
SQL);
        $membership->execute(['user' => $session->userId, 'workspace' => $workspaceId]);
        if ($membership->fetchColumn() === false && !$this->isPlatformOperator($session->userId)) {
            throw new PlatformException('workspace_forbidden', 'The account is not an active member of this workspace.', 403);
        }
        $update = $this->database->prepare('UPDATE iam_sessions SET selected_workspace_id = :workspace WHERE id = :session AND user_id = :user AND revoked_at IS NULL');
        $update->execute(['workspace' => $workspaceId, 'session' => $session->sessionId, 'user' => $session->userId]);
    }

    /**
     * Sets the caller's own password -- the only way one is ever set. The
     * caller types it, once, into their own authenticated browser session;
     * nothing upstream of this method (a recovery token, an operator, a
     * script) ever carries the plaintext value or reads it back.
     *
     * Revokes the previous 'password' authenticator (if any) and inserts a
     * new one rather than updating the row in place, so a leaked or
     * mis-issued old digest cannot verify again even if something retained
     * it -- and so this also doubles as the "no web login yet" path: an
     * account with no password authenticator simply has nothing to revoke.
     */
    public function setPassword(AuthenticatedSession $session, string $newPassword): void
    {
        if (mb_strlen($newPassword) < 8 || mb_strlen($newPassword) > 128) {
            throw new PlatformException('password_invalid', 'Password must be between 8 and 128 characters.', 422);
        }
        Transaction::run($this->database, function () use ($session, $newPassword): void {
            $revoke = $this->database->prepare(<<<'SQL'
UPDATE iam_authenticators
SET revoked_at = UTC_TIMESTAMP(6)
WHERE user_id = :user AND authenticator_type = 'password' AND revoked_at IS NULL
SQL);
            $revoke->execute(['user' => $session->userId]);

            $insert = $this->database->prepare(<<<'SQL'
INSERT INTO iam_authenticators (id, user_id, authenticator_type, secret_digest, metadata_json, created_at)
VALUES (:id, :user, 'password', :digest, JSON_OBJECT(), UTC_TIMESTAMP(6))
SQL);
            $insert->execute([
                'id' => Uuid::v7(),
                'user' => $session->userId,
                'digest' => $this->passwordHasher->hash($newPassword),
            ]);

            $this->audit->record(null, $session->userId, 'auth.password.set', 'iam_authenticator', null, 'success');
        });
    }

    /** @return array{role_keys:list<string>,permission_keys:list<string>} */
    private function workspaceRoleContext(string $userId, string $workspaceId): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT DISTINCT role.role_key, permission.permission_key
FROM rbac_role_assignments assignment
JOIN rbac_role_templates role ON role.id = assignment.role_template_id
JOIN rbac_role_permissions role_permission ON role_permission.role_template_id = role.id
JOIN rbac_permissions permission ON permission.id = role_permission.permission_id
JOIN rbac_scopes assigned_scope ON assigned_scope.id = assignment.scope_id
JOIN tenant_workspaces workspace ON workspace.id = :workspace
JOIN directory_cohorts cohort ON cohort.id = workspace.cohort_id
JOIN directory_programs program ON program.id = cohort.program_id
JOIN directory_faculties faculty ON faculty.id = program.faculty_id
JOIN directory_institutions institution ON institution.id = faculty.institution_id
WHERE assignment.user_id = :user_id
  AND role.status = 'active'
  AND assignment.revoked_at IS NULL
  AND assignment.valid_from <= UTC_TIMESTAMP(6)
  AND (assignment.valid_until IS NULL OR assignment.valid_until > UTC_TIMESTAMP(6))
  AND assigned_scope.archived_at IS NULL
  AND JSON_CONTAINS(role.allowed_scope_types, JSON_QUOTE(assigned_scope.scope_type))
  AND (
      (assigned_scope.scope_type = 'platform' AND assigned_scope.entity_id = '00000000-0000-7000-8000-000000000001')
      OR (assigned_scope.scope_type = 'institution' AND assigned_scope.entity_id = institution.id)
      OR (assigned_scope.scope_type = 'faculty' AND assigned_scope.entity_id = faculty.id)
      OR (assigned_scope.scope_type = 'program' AND assigned_scope.entity_id = program.id)
      OR (assigned_scope.scope_type = 'cohort' AND assigned_scope.entity_id = cohort.id)
      OR (assigned_scope.scope_type = 'workspace' AND assigned_scope.entity_id = workspace.id AND assigned_scope.workspace_id = workspace.id)
  )
ORDER BY role.role_key, permission.permission_key
SQL);
        $query->execute(['user_id' => $userId, 'workspace' => $workspaceId]);

        $roles = [];
        $permissions = [];
        while (($row = $query->fetch()) !== false) {
            $role = (string) $row['role_key'];
            $permission = (string) $row['permission_key'];
            if (!in_array($role, $roles, true)) {
                $roles[] = $role;
            }
            if (!in_array($permission, $permissions, true)) {
                $permissions[] = $permission;
            }
        }

        return ['role_keys' => $roles, 'permission_keys' => $permissions];
    }

    /** @return array{0:string,1:string} */
    private function normalizeIdentifier(string $identifier): array
    {
        $normalized = TextNormalizer::normalize($identifier);
        if ($normalized === '') {
            throw new PlatformException('invalid_identifier', 'An identifier is required.', 422);
        }
        $type = filter_var($normalized, FILTER_VALIDATE_EMAIL) !== false
            ? 'email'
            : (preg_match('/^\+?[0-9]{8,15}$/', $normalized) ? 'phone' : 'external');

        return [$type, $normalized];
    }

    private function recordFailure(string $identifierDigest, string $sourceDigest, int $currentFailures): void
    {
        $failures = $currentFailures + 1;
        $lockedUntil = $failures >= $this->maximumFailures
            ? gmdate('Y-m-d H:i:s', time() + $this->lockSeconds)
            : null;
        $statement = $this->database->prepare(<<<'SQL'
INSERT INTO iam_login_attempts (
    identifier_digest, source_digest, failure_count, window_started_at, locked_until, last_attempt_at
) VALUES (:identifier, :source, :failures, UTC_TIMESTAMP(6), :locked_until, UTC_TIMESTAMP(6))
ON DUPLICATE KEY UPDATE
    failure_count = :failures_update,
    locked_until = :locked_until_update,
    last_attempt_at = UTC_TIMESTAMP(6)
SQL);
        $statement->bindValue(':identifier', $identifierDigest, PDO::PARAM_LOB);
        $statement->bindValue(':source', $sourceDigest, PDO::PARAM_LOB);
        $statement->bindValue(':failures', $failures, PDO::PARAM_INT);
        $statement->bindValue(':locked_until', $lockedUntil);
        $statement->bindValue(':failures_update', $failures, PDO::PARAM_INT);
        $statement->bindValue(':locked_until_update', $lockedUntil);
        $statement->execute();
    }

    private static function randomToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
