<?php

declare(strict_types=1);

namespace Fanoos\Tests\Core;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Core\ClassProvisioningService;
use Fanoos\Platform\Messaging\ChannelSubjectProtector;
use Fanoos\Platform\Messaging\MessagingLinkService;
use Fanoos\Platform\Onboarding\ClassMembershipService;
use Fanoos\Platform\Onboarding\DirectoryReadService;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;

final class ClassMembershipTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $this->assertJoinCreatesLimitedMembershipIdempotently();
        $this->assertJoinRequiresVerifiedPhone();
        $this->assertJoinReportsClassNotFoundWithoutThrowing();
        $this->assertJoinableCohortsByProgram();
        $this->assertUpgradeRequestIsIdempotentAndRequiresMembership();
        $this->assertClassCreationRequestIsIdempotent();
        $this->assertTenantIsolationAcrossPrograms();

        return $this->assertions;
    }

    private function assertJoinCreatesLimitedMembershipIdempotently(): void
    {
        $suffix = substr(str_replace('-', '', Uuid::v7()), -10);
        $owner = $this->platformSuperAdmin('Membership Owner ' . $suffix);
        $provisioning = $this->provisioning();
        $result = $this->provisionFixtureClass($provisioning, $owner, $suffix, 1460);
        $programId = $result['program_id'];
        $workspaceId = $result['workspace_id'];

        $protector = $this->protector();
        $subject = 'tg-join-' . $suffix;
        $phone = '+989120000' . substr($suffix, 0, 3);
        $this->markPhoneVerified($protector, 'telegram', $subject, $phone);

        $membership = $this->service($protector);
        $joined = $membership->join('telegram', $subject, $programId, 1460);
        $this->assert($joined['status'] === 'joined', 'Join with a matching class did not report status=joined.');
        $this->assert($joined['workspace_id'] === $workspaceId, 'Join resolved to a different workspace than the provisioned one.');
        $this->assert($joined['already_member'] === false, 'First join call did not report a new membership.');

        $this->assertSingleRow('tenant_workspace_memberships', 'workspace_id', $workspaceId, 'user_id');
        $userId = $this->userIdForPhone($phone);
        $this->assert($this->hasRole($userId, $workspaceId, 'workspace-limited-member'), 'Joined member was not granted the limited role.');
        $this->assert(!$this->hasRole($userId, $workspaceId, 'student'), 'Joined member was granted the full student role directly.');

        $again = $membership->join('telegram', $subject, $programId, 1460);
        $this->assert($again['already_member'] === true, 'Re-joining the same class did not report an existing membership.');
        $count = $this->database->prepare('SELECT COUNT(*) FROM tenant_workspace_memberships WHERE workspace_id = :w AND user_id = :u');
        $count->execute(['w' => $workspaceId, 'u' => $userId]);
        $this->assert((int) $count->fetchColumn() === 1, 'Re-joining created a second membership row.');

        $roleCount = $this->database->prepare('SELECT COUNT(*) FROM rbac_role_assignments WHERE user_id = :u');
        $roleCount->execute(['u' => $userId]);
        $this->assert((int) $roleCount->fetchColumn() === 1, 'Re-joining created a duplicate role assignment.');
    }

    private function assertJoinRequiresVerifiedPhone(): void
    {
        $suffix = substr(str_replace('-', '', Uuid::v7()), -10);
        $owner = $this->platformSuperAdmin('Membership Owner Unverified ' . $suffix);
        $result = $this->provisionFixtureClass($this->provisioning(), $owner, $suffix, 1461);

        $membership = $this->service($this->protector());
        $this->expectCode('onboarding_phone_not_verified', fn () => $membership->join('telegram', 'tg-unverified-' . $suffix, $result['program_id'], 1461));
    }

    /** No live class for (program, entry_year) is an honest reported status, not a thrown exception -- and the
     * account is still created/linked so it can be reused by requestClassCreation(). */
    private function assertJoinReportsClassNotFoundWithoutThrowing(): void
    {
        $suffix = substr(str_replace('-', '', Uuid::v7()), -10);
        $owner = $this->platformSuperAdmin('No Match Owner ' . $suffix);
        $result = $this->provisionFixtureClass($this->provisioning(), $owner, $suffix, 1466);
        $protector = $this->protector();
        $subject = 'tg-nowork-' . $suffix;
        $phone = '+989121110' . substr($suffix, 0, 3);
        $this->markPhoneVerified($protector, 'telegram', $subject, $phone);

        $membership = $this->service($protector);
        // Same program, but a year with no cohort at all: no class matches.
        $joined = $membership->join('telegram', $subject, $result['program_id'], 1999);
        $this->assert($joined['status'] === 'class_not_found', 'Join with no matching cohort/workspace did not report class_not_found.');
        $this->assert($joined['workspace_id'] === null, 'class_not_found join reported a workspace id anyway.');

        $userId = $this->userIdForPhone($phone);
        $membershipCount = $this->database->prepare('SELECT COUNT(*) FROM tenant_workspace_memberships WHERE user_id = :u');
        $membershipCount->execute(['u' => $userId]);
        $this->assert((int) $membershipCount->fetchColumn() === 0, 'class_not_found join created a membership anyway.');
    }

    private function assertJoinableCohortsByProgram(): void
    {
        $suffix = substr(str_replace('-', '', Uuid::v7()), -10);
        $owner = $this->platformSuperAdmin('Directory Cohorts Owner ' . $suffix);
        $result = $this->provisionFixtureClass($this->provisioning(), $owner, $suffix, 1462);

        $directory = new DirectoryReadService($this->database);
        $page = $directory->joinableCohortsByProgram($result['program_id']);
        $found = false;
        foreach ($page['items'] as $item) {
            if ((string) $item['id'] === $result['cohort_id']) {
                $found = true;
                $this->assert((string) $item['workspace_id'] === $result['workspace_id'], 'Joinable cohort listing returned a mismatched workspace id.');
            }
        }
        $this->assert($found, 'Joinable cohort listing did not surface the freshly provisioned class.');
    }

    private function assertUpgradeRequestIsIdempotentAndRequiresMembership(): void
    {
        $suffix = substr(str_replace('-', '', Uuid::v7()), -10);
        $owner = $this->platformSuperAdmin('Upgrade Owner ' . $suffix);
        $result = $this->provisionFixtureClass($this->provisioning(), $owner, $suffix, 1463);
        $protector = $this->protector();
        $subject = 'tg-upgrade-' . $suffix;
        $this->markPhoneVerified($protector, 'telegram', $subject, '+989122220' . substr($suffix, 0, 3));
        $membership = $this->service($protector);
        $membership->join('telegram', $subject, $result['program_id'], 1463);

        $first = $membership->requestUpgrade('telegram', $subject, $result['workspace_id']);
        $this->assert($first['status'] === 'pending' && $first['created'] === true, 'First upgrade request was not recorded as newly created and pending.');
        $second = $membership->requestUpgrade('telegram', $subject, $result['workspace_id']);
        $this->assert($second['created'] === false, 'Repeated upgrade request created a second row.');

        $count = $this->database->prepare('SELECT COUNT(*) FROM tenant_workspace_role_upgrade_requests WHERE workspace_id = :w');
        $count->execute(['w' => $result['workspace_id']]);
        $this->assert((int) $count->fetchColumn() === 1, 'Idempotent upgrade requests produced more than one row.');

        $strangerSubject = 'tg-stranger-' . $suffix;
        $this->expectCode('messaging_link_required', fn () => $membership->requestUpgrade('telegram', $strangerSubject, $result['workspace_id']));
    }

    private function assertClassCreationRequestIsIdempotent(): void
    {
        $suffix = substr(str_replace('-', '', Uuid::v7()), -10);
        $owner = $this->platformSuperAdmin('Creation Request Owner ' . $suffix);
        $result = $this->provisionFixtureClass($this->provisioning(), $owner, $suffix, 1467);
        $protector = $this->protector();
        $subject = 'tg-creation-' . $suffix;
        $this->markPhoneVerified($protector, 'telegram', $subject, '+989124440' . substr($suffix, 0, 3));
        $membership = $this->service($protector);

        $first = $membership->requestClassCreation('telegram', $subject, $result['program_id'], 1998);
        $this->assert($first['status'] === 'pending' && $first['created'] === true, 'First class creation request was not recorded as newly created and pending.');
        $second = $membership->requestClassCreation('telegram', $subject, $result['program_id'], 1998);
        $this->assert($second['created'] === false, 'Repeated class creation request created a second row.');

        $count = $this->database->prepare('SELECT COUNT(*) FROM class_creation_requests WHERE program_id = :p AND entry_year = 1998');
        $count->execute(['p' => $result['program_id']]);
        $this->assert((int) $count->fetchColumn() === 1, 'Idempotent class creation requests produced more than one row.');

        $raw = $this->database->query('SELECT id FROM iam_user_identifiers ORDER BY created_at DESC LIMIT 1')->fetchColumn();
        $this->assert($raw !== false, 'Class creation request did not create/find a canonical account.');
    }

    private function assertTenantIsolationAcrossPrograms(): void
    {
        $suffix = substr(str_replace('-', '', Uuid::v7()), -10);
        $owner = $this->platformSuperAdmin('Isolation Owner ' . $suffix);
        $provisioning = $this->provisioning();
        $sideA = $this->provisionFixtureClass($provisioning, $owner, $suffix . '-a', 1464);
        $sideB = $this->provisionFixtureClass($provisioning, $owner, $suffix . '-b', 1465);

        $directory = new DirectoryReadService($this->database);
        $pageA = $directory->joinableCohortsByProgram($sideA['program_id']);
        foreach ($pageA['items'] as $item) {
            $this->assert((string) $item['workspace_id'] !== $sideB['workspace_id'], 'Program A cohort listing leaked program B workspace.');
        }

        $protector = $this->protector();
        $subject = 'tg-isolated-' . $suffix;
        $this->markPhoneVerified($protector, 'telegram', $subject, '+989123330' . substr($suffix, 0, 3));
        $membership = $this->service($protector);
        $membership->join('telegram', $subject, $sideA['program_id'], 1464);

        $this->expectCode('workspace_forbidden', fn () => $membership->requestUpgrade('telegram', $subject, $sideB['workspace_id']));
    }

    private function provisioning(): ClassProvisioningService
    {
        $access = new AccessGate($this->database, new ScopeAuthorizer($this->database));
        return new ClassProvisioningService($this->database, $access, new AuditLogger($this->database));
    }

    private function protector(): ChannelSubjectProtector
    {
        return new ChannelSubjectProtector(str_repeat('j', 32));
    }

    private function service(ChannelSubjectProtector $protector): ClassMembershipService
    {
        $links = new MessagingLinkService($this->database, new AuditLogger($this->database), $protector);
        $access = new AccessGate($this->database, new ScopeAuthorizer($this->database));
        return new ClassMembershipService($this->database, new AuditLogger($this->database), $protector, $links, $access);
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

    private function hasRole(string $userId, string $workspaceId, string $roleKey): bool
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT 1
FROM rbac_role_assignments assignment
JOIN rbac_role_templates role ON role.id = assignment.role_template_id AND role.role_key = :role
JOIN rbac_scopes scope ON scope.id = assignment.scope_id AND scope.scope_type = 'workspace' AND scope.workspace_id = :workspace
WHERE assignment.user_id = :user
SQL);
        $query->execute(['role' => $roleKey, 'workspace' => $workspaceId, 'user' => $userId]);
        return $query->fetchColumn() !== false;
    }

    /** @return array<string,mixed> */
    private function provisionFixtureClass(ClassProvisioningService $service, string $owner, string $suffix, int $entryYear): array
    {
        return $service->createClass($owner, [
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

    private function assertSingleRow(string $table, string $whereColumn, string $whereValue, string $countDistinctColumn): void
    {
        $query = $this->database->prepare("SELECT COUNT(DISTINCT {$countDistinctColumn}) FROM {$table} WHERE {$whereColumn} = :value");
        $query->execute(['value' => $whereValue]);
        $this->assert((int) $query->fetchColumn() === 1, "Expected exactly one distinct {$countDistinctColumn} in {$table} where {$whereColumn} = {$whereValue}.");
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
