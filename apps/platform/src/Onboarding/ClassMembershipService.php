<?php

declare(strict_types=1);

namespace Fanoos\Platform\Onboarding;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Messaging\ChannelSubjectProtector;
use Fanoos\Platform\Messaging\MessagingLinkService;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Transaction;
use Fanoos\Platform\Support\Uuid;
use PDO;

/**
 * Turns a completed join wizard into a real membership, records the request
 * to be upgraded out of it, and -- when no class matches at all -- records
 * the seam for capability 5 (a creation request to the owners) without
 * building the owner-facing side of it.
 *
 * Tiered access (docs/product/01_FRONT_DOOR.md #9): joining is open, privilege
 * is what needs approval. A student who verified their phone becomes a member
 * immediately, at 'workspace-limited-member' (seeded in
 * database/seeds/0008_workspace_limited_member_role.sql) rather than the full
 * 'student' role -- enough to buy things and receive notifications, nothing
 * that reaches into class-internal material. This replaces an earlier design
 * where joining created a pending request instead of a membership; that
 * design is gone, not merely superseded in code comments elsewhere.
 *
 * The workspace boundary: everything up to and including phone verification
 * (OnboardingPhoneVerificationService) is pre-workspace -- a (platform,
 * subject) with no canonical account at all. requireVerifiedAccount() is
 * where that boundary is actually crossed: it is the first place in the
 * onboarding flow that creates an iam_users row and a messaging_links row.
 * This happens on join() *and* on requestClassCreation() -- a verified phone
 * becomes a real account whether or not a matching class exists yet, because
 * the identity and the class are independent facts. Only a matching class
 * additionally creates a tenant_workspace_memberships row; everything after
 * that point is an ordinary linked, workspace-scoped identity like any other
 * bot user.
 *
 * Identity: an iam_users account is found-or-created keyed by the verified
 * phone through iam_user_identifiers (identifier_type='phone'), the same
 * table AuthService already uses for phone-based accounts -- reusing it here
 * means a student who already has a site account with this phone number joins
 * onto that account instead of getting a duplicate. This is a deliberately
 * different pattern from the digest+ciphertext protection
 * OnboardingPhoneVerificationService uses for the ephemeral OTP challenge:
 * once a phone is verified and about to become a durable account identifier,
 * it belongs in the same place every other verified account phone lives.
 */
final class ClassMembershipService
{
    private const PLATFORMS = ['telegram', 'bale'];
    private const LIMITED_ROLE_KEY = 'workspace-limited-member';

    public function __construct(
        private readonly PDO $database,
        private readonly AuditLogger $audit,
        private readonly ChannelSubjectProtector $subjects,
        private readonly MessagingLinkService $links,
    ) {
    }

    /**
     * Resolves the class from (program, entry_year) and either creates the
     * limited membership or, if no live class matches yet, reports that
     * honestly. The account is found-or-created and linked either way: a
     * verified phone with no matching class today is still a real, usable
     * account (it can still call requestClassCreation()), it just isn't a
     * member of anything.
     *
     * @return array{status:string,workspace_id:?string,workspace_name:?string,already_member:bool}
     */
    public function join(string $platform, string $subject, string $programId, int $entryYear): array
    {
        $platform = $this->platform($platform);
        $userId = $this->requireVerifiedAccount($platform, $subject);

        return Transaction::run($this->database, function () use ($platform, $userId, $programId, $entryYear): array {
            $workspace = $this->database->prepare(<<<'SQL'
SELECT workspace.id, workspace.name
FROM directory_cohorts cohort
JOIN tenant_workspaces workspace ON workspace.cohort_id = cohort.id
 AND workspace.status = 'active' AND workspace.archived_at IS NULL
WHERE cohort.program_id = :program AND cohort.entry_year = :year
 AND cohort.status = 'active' AND cohort.archived_at IS NULL
LIMIT 1
SQL);
            $workspace->execute(['program' => $programId, 'year' => $entryYear]);
            $workspaceRow = $workspace->fetch();
            if ($workspaceRow === false) {
                return ['status' => 'class_not_found', 'workspace_id' => null, 'workspace_name' => null, 'already_member' => false];
            }
            $workspaceId = (string) $workspaceRow['id'];

            $existingMembership = $this->database->prepare('SELECT id FROM tenant_workspace_memberships WHERE workspace_id = :workspace AND user_id = :user FOR UPDATE');
            $existingMembership->execute(['workspace' => $workspaceId, 'user' => $userId]);
            $alreadyMember = $existingMembership->fetchColumn() !== false;

            if (!$alreadyMember) {
                $insert = $this->database->prepare(<<<'SQL'
INSERT INTO tenant_workspace_memberships (id, workspace_id, user_id, status, joined_at, version, created_at, updated_at)
VALUES (:id, :workspace, :user, 'active', UTC_TIMESTAMP(6), 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL);
                $insert->execute(['id' => Uuid::v7(), 'workspace' => $workspaceId, 'user' => $userId]);
            }

            $this->ensureLimitedRole($userId, $workspaceId);

            $this->audit->record($workspaceId, $userId, 'onboarding.join', 'tenant_workspace_membership', $workspaceId, 'success', [
                'platform' => $platform,
                'already_member' => $alreadyMember,
            ]);

            return [
                'status' => 'joined',
                'workspace_id' => $workspaceId,
                'workspace_name' => (string) $workspaceRow['name'],
                'already_member' => $alreadyMember,
            ];
        });
    }

    /** @return array{status:string,created:bool} */
    public function requestUpgrade(string $platform, string $subject, string $workspaceId): array
    {
        $platform = $this->platform($platform);
        $resolved = $this->links->resolve($platform, $subject);
        if ($resolved === null) {
            throw new PlatformException('messaging_link_required', 'Messaging account is not linked.', 403);
        }
        $userId = $resolved['user_id'];

        return Transaction::run($this->database, function () use ($userId, $workspaceId): array {
            $membership = $this->database->prepare("SELECT 1 FROM tenant_workspace_memberships WHERE workspace_id = :workspace AND user_id = :user AND status = 'active'");
            $membership->execute(['workspace' => $workspaceId, 'user' => $userId]);
            if ($membership->fetchColumn() === false) {
                throw new PlatformException('workspace_forbidden', 'Linked account is not an active member of this workspace.', 403);
            }

            $existing = $this->database->prepare('SELECT status FROM tenant_workspace_role_upgrade_requests WHERE workspace_id = :workspace AND user_id = :user FOR UPDATE');
            $existing->execute(['workspace' => $workspaceId, 'user' => $userId]);
            $existingStatus = $existing->fetchColumn();
            if ($existingStatus !== false) {
                return ['status' => (string) $existingStatus, 'created' => false];
            }

            $insert = $this->database->prepare(<<<'SQL'
INSERT INTO tenant_workspace_role_upgrade_requests (id, workspace_id, user_id, status, requested_at, created_at, updated_at)
VALUES (:id, :workspace, :user, 'pending', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL);
            $insert->execute(['id' => Uuid::v7(), 'workspace' => $workspaceId, 'user' => $userId]);

            $this->audit->record($workspaceId, $userId, 'onboarding.upgrade_request', 'tenant_workspace_role_upgrade_request', $workspaceId, 'success', []);

            return ['status' => 'pending', 'created' => true];
        });
    }

    /**
     * Capability 5's seam: records that a verified person asked for a
     * program+entry_year that has no live class, so it isn't lost. Nothing
     * here notifies an owner or creates anything beyond the row itself --
     * that consumption side is a separate, later capability.
     *
     * @return array{status:string,created:bool}
     */
    public function requestClassCreation(string $platform, string $subject, string $programId, int $entryYear): array
    {
        $platform = $this->platform($platform);
        $userId = $this->requireVerifiedAccount($platform, $subject);

        return Transaction::run($this->database, function () use ($userId, $programId, $entryYear): array {
            $existing = $this->database->prepare('SELECT status FROM class_creation_requests WHERE user_id = :user AND program_id = :program AND entry_year = :year FOR UPDATE');
            $existing->execute(['user' => $userId, 'program' => $programId, 'year' => $entryYear]);
            $existingStatus = $existing->fetchColumn();
            if ($existingStatus !== false) {
                return ['status' => (string) $existingStatus, 'created' => false];
            }

            $insert = $this->database->prepare(<<<'SQL'
INSERT INTO class_creation_requests (id, user_id, program_id, entry_year, status, requested_at, created_at, updated_at)
VALUES (:id, :user, :program, :year, 'pending', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL);
            $insert->execute(['id' => Uuid::v7(), 'user' => $userId, 'program' => $programId, 'year' => $entryYear]);

            $this->audit->record(null, $userId, 'onboarding.class_creation_request', 'class_creation_request', $programId, 'success', ['entry_year' => $entryYear]);

            return ['status' => 'pending', 'created' => true];
        });
    }

    private function requireVerifiedAccount(string $platform, string $subject): string
    {
        $subjectDigest = $this->subjects->digest('onboarding:' . $platform, $subject);

        $verified = $this->database->prepare('SELECT phone_ciphertext FROM onboarding_verified_phones WHERE platform = :platform AND subject_digest = :digest');
        $verified->bindValue(':platform', $platform);
        $verified->bindValue(':digest', $subjectDigest, PDO::PARAM_LOB);
        $verified->execute();
        $phoneCiphertext = $verified->fetchColumn();
        if ($phoneCiphertext === false) {
            throw new PlatformException('onboarding_phone_not_verified', 'Phone number must be verified first.', 403);
        }
        $phone = $this->subjects->decrypt('phone', (string) $phoneCiphertext);

        return Transaction::run($this->database, function () use ($platform, $subject, $phone): string {
            $userId = $this->findOrCreateUserByPhone($phone);
            $this->links->establishLink($userId, $platform, $subject);
            return $userId;
        });
    }

    private function findOrCreateUserByPhone(string $phone): string
    {
        $existing = $this->database->prepare("SELECT user_id FROM iam_user_identifiers WHERE identifier_type = 'phone' AND normalized_value = :phone AND revoked_at IS NULL FOR UPDATE");
        $existing->execute(['phone' => $phone]);
        $userId = $existing->fetchColumn();
        if ($userId !== false) {
            return (string) $userId;
        }

        $userId = Uuid::v7();
        $this->database->prepare(<<<'SQL'
INSERT INTO iam_users (id, display_name, status, locale, created_at, updated_at)
VALUES (:id, :name, 'active', 'fa-IR', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL)->execute(['id' => $userId, 'name' => 'دانشجو']);

        $this->database->prepare(<<<'SQL'
INSERT INTO iam_user_identifiers (id, user_id, identifier_type, normalized_value, is_verified, verified_at, created_at)
VALUES (:id, :user, 'phone', :phone, TRUE, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL)->execute(['id' => Uuid::v7(), 'user' => $userId, 'phone' => $phone]);

        return $userId;
    }

    private function ensureLimitedRole(string $userId, string $workspaceId): void
    {
        $scope = $this->database->prepare("SELECT id FROM rbac_scopes WHERE scope_type = 'workspace' AND entity_id = :workspace");
        $scope->execute(['workspace' => $workspaceId]);
        $scopeId = $scope->fetchColumn();
        if ($scopeId === false) {
            throw new PlatformException('workspace_scope_missing', 'Workspace authorization scope was not found.', 500);
        }

        $role = $this->database->prepare('SELECT id FROM rbac_role_templates WHERE role_key = :role');
        $role->execute(['role' => self::LIMITED_ROLE_KEY]);
        $roleId = $role->fetchColumn();
        if ($roleId === false) {
            throw new PlatformException('role_template_missing', 'Limited member role template was not found.', 500);
        }

        $this->database->prepare(<<<'SQL'
INSERT IGNORE INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at)
VALUES (:id, :user, :role, :scope, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL)->execute(['id' => Uuid::v7(), 'user' => $userId, 'role' => $roleId, 'scope' => $scopeId]);
    }

    private function platform(string $platform): string
    {
        $platform = strtolower(trim($platform));
        if (!in_array($platform, self::PLATFORMS, true)) {
            throw new PlatformException('onboarding_platform_invalid', 'Onboarding platform is not supported.', 422);
        }
        return $platform;
    }
}
