<?php

declare(strict_types=1);

namespace Fanoos\Tests\Core;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Core\ClassProvisioningService;
use Fanoos\Platform\Core\WorkspacePlatformService;
use Fanoos\Platform\Messaging\ChannelSubjectProtector;
use Fanoos\Platform\Messaging\MessagingLinkService;
use Fanoos\Platform\Onboarding\ClassMembershipService;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Representative appointment (WorkspacePlatformService::assignRepresentative,
 * already shipped and tested by tests/Integration/CorePlatformTest.php) plus
 * approval, the genuinely new capability: a representative closing a pending
 * tenant_workspace_role_upgrade_requests row and moving the member from
 * 'workspace-limited-member' to the full 'student' role, atomically.
 */
final class RepresentativeApprovalTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $this->assertAppointedRepresentativeResolvesViaNormalAuthorization();
        $this->assertAppointingTwiceIsIdempotent();
        $this->assertAppointingNonMemberIsRefused();
        $this->assertApproveClosesRequestAndPromotesRoleAtomically();
        $this->assertFailureMidApprovalLeavesNeitherApplied();
        $this->assertRepresentativeOfOneClassCannotTouchAnothersRequests();
        $this->assertLimitedMemberCannotApproveAnything();
        $this->assertDeclinedRequestIsTerminalAndCannotThenBeApproved();
        $this->assertApprovalGrantsAccessTheLimitedRoleDidNotHave();

        return $this->assertions;
    }

    private function assertAppointedRepresentativeResolvesViaNormalAuthorization(): void
    {
        $suffix = $this->suffix();
        $owner = $this->platformSuperAdmin('Rep Appoint Owner ' . $suffix);
        $fixture = $this->provisionFixtureClass($owner, $suffix, 5001);
        $protector = $this->protector();
        $subject = 'tg-rep-' . $suffix;
        $memberUserId = $this->joinAsMember($protector, $subject, $fixture, '+989130000' . substr($suffix, 0, 3));

        $platform = $this->workspacePlatform();
        $assignmentId = $platform->assignRepresentative($owner, $fixture['workspace_id'], $memberUserId);
        $this->assert($assignmentId !== '', 'Appointing a representative did not return an assignment id.');
        $this->assert($this->hasActiveRole($memberUserId, $fixture['workspace_id'], 'cohort-representative'), 'Appointed member was not granted cohort-representative.');

        $access = $this->accessGate();
        $this->assert($access->workspace($memberUserId, $fixture['workspace_id'], 'membership.approve')->allowed, 'Newly appointed representative could not resolve membership.approve through the normal authorization path.');
    }

    private function assertAppointingTwiceIsIdempotent(): void
    {
        $suffix = $this->suffix();
        $owner = $this->platformSuperAdmin('Rep Appoint Twice Owner ' . $suffix);
        $fixture = $this->provisionFixtureClass($owner, $suffix, 5002);
        $protector = $this->protector();
        $memberUserId = $this->joinAsMember($protector, 'tg-reptwice-' . $suffix, $fixture, '+989130100' . substr($suffix, 0, 3));

        $platform = $this->workspacePlatform();
        $platform->assignRepresentative($owner, $fixture['workspace_id'], $memberUserId);
        $platform->assignRepresentative($owner, $fixture['workspace_id'], $memberUserId);

        $count = $this->database->prepare(<<<'SQL'
SELECT COUNT(*) FROM rbac_role_assignments assignment
JOIN rbac_role_templates role ON role.id = assignment.role_template_id AND role.role_key = 'cohort-representative'
JOIN rbac_scopes scope ON scope.id = assignment.scope_id AND scope.workspace_id = :workspace
WHERE assignment.user_id = :user
SQL);
        $count->execute(['workspace' => $fixture['workspace_id'], 'user' => $memberUserId]);
        $this->assert((int) $count->fetchColumn() === 1, 'Appointing the same representative twice created a second role assignment.');
    }

    private function assertAppointingNonMemberIsRefused(): void
    {
        $suffix = $this->suffix();
        $owner = $this->platformSuperAdmin('Rep Non Member Owner ' . $suffix);
        $fixture = $this->provisionFixtureClass($owner, $suffix, 5003);
        $stranger = $this->plainUser('Non Member ' . $suffix);

        $platform = $this->workspacePlatform();
        $this->expectCode('member_not_found', fn () => $platform->assignRepresentative($owner, $fixture['workspace_id'], $stranger));
    }

    private function assertApproveClosesRequestAndPromotesRoleAtomically(): void
    {
        $suffix = $this->suffix();
        $owner = $this->platformSuperAdmin('Approve Owner ' . $suffix);
        $fixture = $this->provisionFixtureClass($owner, $suffix, 5004);
        $protector = $this->protector();

        $memberSubject = 'tg-approvee-' . $suffix;
        $memberUserId = $this->joinAsMember($protector, $memberSubject, $fixture, '+989131000' . substr($suffix, 0, 3));
        $repSubject = 'tg-approver-' . $suffix;
        $repUserId = $this->joinAsMember($protector, $repSubject, $fixture, '+989131100' . substr($suffix, 0, 3));
        $this->workspacePlatform()->assignRepresentative($owner, $fixture['workspace_id'], $repUserId);

        $membership = $this->classMembership($protector);
        $requested = $membership->requestUpgrade('telegram', $memberSubject, $fixture['workspace_id']);
        $this->assert($requested['status'] === 'pending', 'Upgrade request was not recorded as pending.');
        $requestId = $this->requestIdFor($fixture['workspace_id'], $memberUserId);

        $pending = $membership->pendingUpgradeRequests('telegram', $repSubject, $fixture['workspace_id']);
        $this->assert(count(array_filter($pending, static fn ($row) => $row['request_id'] === $requestId)) === 1, 'Representative pending-requests view did not list the request.');

        $approved = $membership->approveUpgradeRequest('telegram', $repSubject, $fixture['workspace_id'], $requestId);
        $this->assert($approved['status'] === 'approved' && $approved['already'] === false, 'Approval did not report a fresh approved status.');

        $this->assert($this->hasActiveRole($memberUserId, $fixture['workspace_id'], 'student'), 'Approved member was not granted the full student role.');
        $this->assert(!$this->hasActiveRole($memberUserId, $fixture['workspace_id'], 'workspace-limited-member'), 'Approved member still holds an active limited-role assignment.');

        $status = $this->database->prepare('SELECT status, resolved_by_user_id FROM tenant_workspace_role_upgrade_requests WHERE id = :id');
        $status->execute(['id' => $requestId]);
        $row = $status->fetch();
        $this->assert($row['status'] === 'approved', 'Upgrade request row was not marked approved.');
        $this->assert($row['resolved_by_user_id'] === $repUserId, 'Upgrade request was not recorded as resolved by the approving representative.');

        // Idempotent re-approval: same terminal state, no duplicate side effects.
        $again = $membership->approveUpgradeRequest('telegram', $repSubject, $fixture['workspace_id'], $requestId);
        $this->assert($again['status'] === 'approved' && $again['already'] === true, 'Re-approving an already-approved request did not report already=true.');
    }

    /**
     * The role template lookup is temporarily broken (a global row is
     * renamed and restored in a finally block) so the second half of
     * approveUpgradeRequest() genuinely throws mid-transaction, after the
     * request-status UPDATE has already run in the same PHP call. Both
     * writes share one Transaction::run() closure, so if either write did
     * not roll back with the other, this test fails.
     */
    private function assertFailureMidApprovalLeavesNeitherApplied(): void
    {
        $suffix = $this->suffix();
        $owner = $this->platformSuperAdmin('Atomic Owner ' . $suffix);
        $fixture = $this->provisionFixtureClass($owner, $suffix, 5005);
        $protector = $this->protector();

        $memberSubject = 'tg-atomic-member-' . $suffix;
        $memberUserId = $this->joinAsMember($protector, $memberSubject, $fixture, '+989132000' . substr($suffix, 0, 3));
        $repSubject = 'tg-atomic-rep-' . $suffix;
        $repUserId = $this->joinAsMember($protector, $repSubject, $fixture, '+989132100' . substr($suffix, 0, 3));
        $this->workspacePlatform()->assignRepresentative($owner, $fixture['workspace_id'], $repUserId);

        $membership = $this->classMembership($protector);
        $membership->requestUpgrade('telegram', $memberSubject, $fixture['workspace_id']);
        $requestId = $this->requestIdFor($fixture['workspace_id'], $memberUserId);

        $this->database->prepare("UPDATE rbac_role_templates SET role_key = 'student__disabled_for_atomicity_test' WHERE role_key = 'student'")->execute();
        try {
            $this->expectCode('role_template_missing', fn () => $membership->approveUpgradeRequest('telegram', $repSubject, $fixture['workspace_id'], $requestId));
        } finally {
            $this->database->prepare("UPDATE rbac_role_templates SET role_key = 'student' WHERE role_key = 'student__disabled_for_atomicity_test'")->execute();
        }

        $status = $this->database->prepare('SELECT status, resolved_by_user_id FROM tenant_workspace_role_upgrade_requests WHERE id = :id');
        $status->execute(['id' => $requestId]);
        $row = $status->fetch();
        $this->assert($row['status'] === 'pending', 'A failed approval left the upgrade request in a non-pending state -- request half applied.');
        $this->assert($row['resolved_by_user_id'] === null, 'A failed approval recorded a resolver despite rolling back.');
        $this->assert($this->hasActiveRole($memberUserId, $fixture['workspace_id'], 'workspace-limited-member'), 'A failed approval revoked the limited role despite rolling back -- role half applied.');
        $this->assert(!$this->hasActiveRole($memberUserId, $fixture['workspace_id'], 'student'), 'A failed approval granted the student role despite rolling back.');
    }

    private function assertRepresentativeOfOneClassCannotTouchAnothersRequests(): void
    {
        $suffix = $this->suffix();
        $owner = $this->platformSuperAdmin('Isolation Owner ' . $suffix);
        $fixtureA = $this->provisionFixtureClass($owner, $suffix . '-a', 5006);
        $fixtureB = $this->provisionFixtureClass($owner, $suffix . '-b', 5007);
        $protector = $this->protector();

        $memberASubject = 'tg-iso-member-a-' . $suffix;
        $memberAUserId = $this->joinAsMember($protector, $memberASubject, $fixtureA, '+989133000' . substr($suffix, 0, 3));
        $repBSubject = 'tg-iso-rep-b-' . $suffix;
        $repBUserId = $this->joinAsMember($protector, $repBSubject, $fixtureB, '+989133100' . substr($suffix, 0, 3));
        $this->workspacePlatform()->assignRepresentative($owner, $fixtureB['workspace_id'], $repBUserId);

        $membership = $this->classMembership($protector);
        $membership->requestUpgrade('telegram', $memberASubject, $fixtureA['workspace_id']);
        $requestIdInA = $this->requestIdFor($fixtureA['workspace_id'], $memberAUserId);

        // Representative of B is not even a member of A, so every entry
        // point refuses before the membership.approve check is reached.
        $this->expectCode('workspace_forbidden', fn () => $membership->pendingUpgradeRequests('telegram', $repBSubject, $fixtureA['workspace_id']));
        $this->expectCode('workspace_forbidden', fn () => $membership->approveUpgradeRequest('telegram', $repBSubject, $fixtureA['workspace_id'], $requestIdInA));
        $this->expectCode('workspace_forbidden', fn () => $membership->declineUpgradeRequest('telegram', $repBSubject, $fixtureA['workspace_id'], $requestIdInA));

        // Even if B's representative were (hypothetically) an active member
        // of A too, a request id from A can never be approved through B's
        // workspace_id -- lockUpgradeRequest filters by workspace_id.
        $this->expectCode('upgrade_request_not_found', fn () => $membership->approveUpgradeRequest('telegram', $repBSubject, $fixtureB['workspace_id'], $requestIdInA));
    }

    private function assertLimitedMemberCannotApproveAnything(): void
    {
        $suffix = $this->suffix();
        $owner = $this->platformSuperAdmin('Limited Cannot Approve Owner ' . $suffix);
        $fixture = $this->provisionFixtureClass($owner, $suffix, 5008);
        $protector = $this->protector();

        $memberSubject = 'tg-limited-' . $suffix;
        $memberUserId = $this->joinAsMember($protector, $memberSubject, $fixture, '+989134000' . substr($suffix, 0, 3));
        $membership = $this->classMembership($protector);
        $membership->requestUpgrade('telegram', $memberSubject, $fixture['workspace_id']);
        $requestId = $this->requestIdFor($fixture['workspace_id'], $memberUserId);

        // The requester is an active member of their own workspace, so
        // requireActiveMember succeeds; only the RBAC permission check
        // should stop a limited member from approving -- including their
        // own request.
        $this->expectCode('forbidden', fn () => $membership->pendingUpgradeRequests('telegram', $memberSubject, $fixture['workspace_id']));
        $this->expectCode('forbidden', fn () => $membership->approveUpgradeRequest('telegram', $memberSubject, $fixture['workspace_id'], $requestId));
    }

    private function assertDeclinedRequestIsTerminalAndCannotThenBeApproved(): void
    {
        $suffix = $this->suffix();
        $owner = $this->platformSuperAdmin('Decline Owner ' . $suffix);
        $fixture = $this->provisionFixtureClass($owner, $suffix, 5009);
        $protector = $this->protector();

        $memberSubject = 'tg-declinee-' . $suffix;
        $memberUserId = $this->joinAsMember($protector, $memberSubject, $fixture, '+989135000' . substr($suffix, 0, 3));
        $repSubject = 'tg-decliner-' . $suffix;
        $repUserId = $this->joinAsMember($protector, $repSubject, $fixture, '+989135100' . substr($suffix, 0, 3));
        $this->workspacePlatform()->assignRepresentative($owner, $fixture['workspace_id'], $repUserId);

        $membership = $this->classMembership($protector);
        $membership->requestUpgrade('telegram', $memberSubject, $fixture['workspace_id']);
        $requestId = $this->requestIdFor($fixture['workspace_id'], $memberUserId);

        $declined = $membership->declineUpgradeRequest('telegram', $repSubject, $fixture['workspace_id'], $requestId);
        $this->assert($declined['status'] === 'declined' && $declined['already'] === false, 'Decline did not report a fresh declined status.');

        $this->expectCode('upgrade_request_not_pending', fn () => $membership->approveUpgradeRequest('telegram', $repSubject, $fixture['workspace_id'], $requestId));
        $this->assert(!$this->hasActiveRole($memberUserId, $fixture['workspace_id'], 'student'), 'A declined-then-approve-attempted request still granted the full role.');

        $againDeclined = $membership->declineUpgradeRequest('telegram', $repSubject, $fixture['workspace_id'], $requestId);
        $this->assert($againDeclined['already'] === true, 'Re-declining an already-declined request did not report already=true.');
    }

    private function assertApprovalGrantsAccessTheLimitedRoleDidNotHave(): void
    {
        $suffix = $this->suffix();
        $owner = $this->platformSuperAdmin('Tier Proof Owner ' . $suffix);
        $fixture = $this->provisionFixtureClass($owner, $suffix, 5010);
        $protector = $this->protector();

        $memberSubject = 'tg-tierproof-' . $suffix;
        $memberUserId = $this->joinAsMember($protector, $memberSubject, $fixture, '+989136000' . substr($suffix, 0, 3));
        $repSubject = 'tg-tierproof-rep-' . $suffix;
        $repUserId = $this->joinAsMember($protector, $repSubject, $fixture, '+989136100' . substr($suffix, 0, 3));
        $this->workspacePlatform()->assignRepresentative($owner, $fixture['workspace_id'], $repUserId);

        $access = $this->accessGate();
        $this->assert(!$access->workspace($memberUserId, $fixture['workspace_id'], 'academic.view')->allowed, 'Limited role could already reach academic.view before approval.');
        $this->assert(!$access->workspace($memberUserId, $fixture['workspace_id'], 'grade.view_self')->allowed, 'Limited role could already reach grade.view_self before approval.');

        $membership = $this->classMembership($protector);
        $membership->requestUpgrade('telegram', $memberSubject, $fixture['workspace_id']);
        $requestId = $this->requestIdFor($fixture['workspace_id'], $memberUserId);
        $membership->approveUpgradeRequest('telegram', $repSubject, $fixture['workspace_id'], $requestId);

        $this->assert($access->workspace($memberUserId, $fixture['workspace_id'], 'academic.view')->allowed, 'Approved member still cannot reach academic.view -- the tier did not move.');
        $this->assert($access->workspace($memberUserId, $fixture['workspace_id'], 'grade.view_self')->allowed, 'Approved member still cannot reach grade.view_self -- the tier did not move.');
    }

    // -- fixtures and small helpers ------------------------------------------

    private function suffix(): string
    {
        return substr(str_replace('-', '', Uuid::v7()), -10);
    }

    private function accessGate(): AccessGate
    {
        return new AccessGate($this->database, new ScopeAuthorizer($this->database));
    }

    private function provisioning(): ClassProvisioningService
    {
        return new ClassProvisioningService($this->database, $this->accessGate(), new AuditLogger($this->database));
    }

    private function workspacePlatform(): WorkspacePlatformService
    {
        return new WorkspacePlatformService($this->database, $this->accessGate(), new AuditLogger($this->database));
    }

    private function protector(): ChannelSubjectProtector
    {
        return new ChannelSubjectProtector(str_repeat('r', 32));
    }

    private function classMembership(ChannelSubjectProtector $protector): ClassMembershipService
    {
        $links = new MessagingLinkService($this->database, new AuditLogger($this->database), $protector);
        return new ClassMembershipService($this->database, new AuditLogger($this->database), $protector, $links, $this->accessGate());
    }

    /** @return array<string,mixed> */
    private function provisionFixtureClass(string $owner, string $suffix, int $entryYear): array
    {
        return $this->provisioning()->createClass($owner, [
            'country' => ['code' => 'ZZ', 'name' => 'Fixture Country'],
            'province' => ['name' => 'Fixture Province ' . $suffix],
            'city' => ['name' => 'Fixture City ' . $suffix],
            'institution' => ['name' => 'Fixture University ' . $suffix],
            'faculty' => ['name' => 'Fixture Faculty ' . $suffix],
            'program' => ['name' => 'Fixture Program ' . $suffix, 'degree_level' => 'professional-doctorate'],
            'cohort' => ['entry_year' => $entryYear, 'label' => 'Fixture Cohort ' . $suffix],
            'workspace' => ['name' => 'Fixture Class ' . $suffix],
        ]);
    }

    /** @param array<string,mixed> $fixture */
    private function joinAsMember(ChannelSubjectProtector $protector, string $subject, array $fixture, string $phone): string
    {
        $this->markPhoneVerified($protector, 'telegram', $subject, $phone);
        $membership = $this->classMembership($protector);
        $joined = $membership->join('telegram', $subject, $fixture['program_id'], $this->entryYearFor($fixture));
        if ($joined['status'] !== 'joined') {
            throw new RuntimeException('Fixture join did not resolve to a class.');
        }
        return $this->userIdForPhone($phone);
    }

    /** @param array<string,mixed> $fixture */
    private function entryYearFor(array $fixture): int
    {
        $year = $this->database->prepare('SELECT entry_year FROM directory_cohorts WHERE id = :id');
        $year->execute(['id' => $fixture['cohort_id']]);
        return (int) $year->fetchColumn();
    }

    private function markPhoneVerified(ChannelSubjectProtector $protector, string $platform, string $subject, string $phone): void
    {
        $subjectDigest = $protector->digest('onboarding:' . $platform, $subject);
        $phoneDigest = $protector->digest('phone', $phone);
        $phoneCiphertext = $protector->encrypt('phone', $phone);
        $statement = $this->database->prepare(<<<'SQL'
INSERT INTO onboarding_verified_phones (platform, subject_digest, phone_digest, phone_ciphertext, verified_at, updated_at)
VALUES (:platform, :subject_digest, :phone_digest, :phone_ciphertext, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL);
        $statement->bindValue(':platform', $platform);
        $statement->bindValue(':subject_digest', $subjectDigest, PDO::PARAM_LOB);
        $statement->bindValue(':phone_digest', $phoneDigest, PDO::PARAM_LOB);
        $statement->bindValue(':phone_ciphertext', $phoneCiphertext, PDO::PARAM_LOB);
        $statement->execute();
    }

    private function userIdForPhone(string $phone): string
    {
        $query = $this->database->prepare("SELECT user_id FROM iam_user_identifiers WHERE identifier_type = 'phone' AND normalized_value = :phone");
        $query->execute(['phone' => $phone]);
        $userId = $query->fetchColumn();
        if ($userId === false) {
            throw new RuntimeException('Expected a canonical account to exist for the verified phone.');
        }
        return (string) $userId;
    }

    private function requestIdFor(string $workspaceId, string $userId): string
    {
        $query = $this->database->prepare('SELECT id FROM tenant_workspace_role_upgrade_requests WHERE workspace_id = :workspace AND user_id = :user');
        $query->execute(['workspace' => $workspaceId, 'user' => $userId]);
        $id = $query->fetchColumn();
        if ($id === false) {
            throw new RuntimeException('Expected an upgrade request row to exist.');
        }
        return (string) $id;
    }

    private function hasActiveRole(string $userId, string $workspaceId, string $roleKey): bool
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT 1
FROM rbac_role_assignments assignment
JOIN rbac_role_templates role ON role.id = assignment.role_template_id AND role.role_key = :role
JOIN rbac_scopes scope ON scope.id = assignment.scope_id AND scope.scope_type = 'workspace' AND scope.workspace_id = :workspace
WHERE assignment.user_id = :user AND assignment.revoked_at IS NULL
SQL);
        $query->execute(['role' => $roleKey, 'workspace' => $workspaceId, 'user' => $userId]);
        return $query->fetchColumn() !== false;
    }

    private function platformSuperAdmin(string $displayName): string
    {
        $id = Uuid::v7();
        $this->database->prepare("INSERT INTO iam_users (id, display_name, status, locale, created_at, updated_at) VALUES (:id, :name, 'active', 'fa-IR', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => $id, 'name' => $displayName]);
        $role = $this->database->prepare("SELECT id FROM rbac_role_templates WHERE role_key = 'platform-super-admin' LIMIT 1");
        $role->execute();
        $roleId = $role->fetchColumn();
        if ($roleId === false) {
            throw new RuntimeException('Role template is missing: platform-super-admin');
        }
        $this->database->prepare('INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at) VALUES (:id, :user, :role, :scope, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))')
            ->execute(['id' => Uuid::v7(), 'user' => $id, 'role' => $roleId, 'scope' => '00000000-0000-7000-8000-000000000001']);
        return $id;
    }

    private function plainUser(string $displayName): string
    {
        $id = Uuid::v7();
        $this->database->prepare("INSERT INTO iam_users (id, display_name, status, locale, created_at, updated_at) VALUES (:id, :name, 'active', 'fa-IR', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => $id, 'name' => $displayName]);
        return $id;
    }

    private function expectCode(string $code, callable $operation): void
    {
        ++$this->assertions;
        try {
            $operation();
        } catch (PlatformException $error) {
            if ($error->errorCode === $code) {
                return;
            }
            throw new RuntimeException("Expected {$code}, got {$error->errorCode}: {$error->getMessage()}");
        } catch (Throwable $error) {
            throw new RuntimeException("Expected PlatformException {$code}, got " . get_class($error) . ': ' . $error->getMessage());
        }
        throw new RuntimeException("Expected PlatformException {$code}.");
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
