<?php

declare(strict_types=1);

namespace Fanoos\Tests\Core;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Core\ClassCreationRequestService;
use Fanoos\Platform\Core\ClassProvisioningService;
use Fanoos\Platform\Messaging\ChannelSubjectProtector;
use Fanoos\Platform\Messaging\MessagingLinkService;
use Fanoos\Platform\Onboarding\ClassMembershipService;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;
use Throwable;

/**
 * The owner-facing consumption side of capability 5
 * (docs/product/01_FRONT_DOOR.md #9): ClassMembershipService::requestClassCreation()
 * already writes class_creation_requests rows (covered by ClassMembershipTest);
 * this exercises ClassCreationRequestService, the first thing that reads and
 * acts on them.
 */
final class ClassCreationRequestServiceTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $this->assertListingGroupsSeveralRequestsIntoOneRowWithCount();
        $this->assertApprovingCreatesClassAndClosesEveryRequestInGroup();
        $this->assertFailureMidApprovalLeavesNeitherClassNorStatusChange();
        $this->assertDecliningIsTerminalAndCannotThenBeApproved();
        $this->assertNonOwnerIsRefusedListApprovalAndDecline();
        $this->assertApprovingRequestForAlreadyExistingClassResolvesWithoutDuplicate();

        return $this->assertions;
    }

    private function assertListingGroupsSeveralRequestsIntoOneRowWithCount(): void
    {
        $suffix = $this->suffix();
        $owner = $this->platformSuperAdmin('List Owner ' . $suffix);
        $fixture = $this->provisionFixtureProgram($owner, $suffix, 4001);
        $entryYear = 7001;
        $protector = $this->protector();

        $this->requestClassCreation($protector, 'tg-listreq-a-' . $suffix, $fixture['program_id'], $entryYear, '+989140000' . substr($suffix, 0, 3));
        $this->requestClassCreation($protector, 'tg-listreq-b-' . $suffix, $fixture['program_id'], $entryYear, '+989140100' . substr($suffix, 0, 3));
        $this->requestClassCreation($protector, 'tg-listreq-c-' . $suffix, $fixture['program_id'], $entryYear, '+989140200' . substr($suffix, 0, 3));
        // A different entry year on the same program must not be folded into the same group.
        $this->requestClassCreation($protector, 'tg-listreq-d-' . $suffix, $fixture['program_id'], 7002, '+989140300' . substr($suffix, 0, 3));

        $service = $this->requests();
        $page = $service->listPendingGroups($owner, 50, null);
        $group = null;
        foreach ($page['items'] as $item) {
            if ((string) $item['program_id'] === $fixture['program_id'] && (int) $item['entry_year'] === $entryYear) {
                $group = $item;
            }
        }
        $this->assert($group !== null, 'Listing did not surface the pending group at all.');
        $this->assert((int) $group['demand_count'] === 3, 'Three requests for the same identity were not folded into one group with count 3.');
        $this->assert($group['institution_name'] === $fixture['institution'], 'Listing did not resolve the institution label.');
        $this->assert($group['faculty_name'] === $fixture['faculty'], 'Listing did not resolve the faculty label.');
        $this->assert($group['program_name'] === $fixture['program'], 'Listing did not resolve the program label.');
        $this->assert(!empty($group['earliest_requested_at']), 'Listing did not report when the earliest request arrived.');
    }

    private function assertApprovingCreatesClassAndClosesEveryRequestInGroup(): void
    {
        $suffix = $this->suffix();
        $owner = $this->platformSuperAdmin('Approve Group Owner ' . $suffix);
        $fixture = $this->provisionFixtureProgram($owner, $suffix, 4002);
        $entryYear = 7101;
        $protector = $this->protector();

        $this->requestClassCreation($protector, 'tg-apprgrp-a-' . $suffix, $fixture['program_id'], $entryYear, '+989141000' . substr($suffix, 0, 3));
        $this->requestClassCreation($protector, 'tg-apprgrp-b-' . $suffix, $fixture['program_id'], $entryYear, '+989141100' . substr($suffix, 0, 3));
        $this->requestClassCreation($protector, 'tg-apprgrp-c-' . $suffix, $fixture['program_id'], $entryYear, '+989141200' . substr($suffix, 0, 3));

        $service = $this->requests();
        $result = $service->approveGroup($owner, $fixture['program_id'], $entryYear, 'Cohort Label ' . $suffix, 'Class Name ' . $suffix);
        $this->assert($result['workspace_created'] === true, 'Approving a fresh identity did not report a newly created class.');
        $this->assert($result['resolved_count'] === 3, 'Approval did not report resolving all three pending requests.');

        $workspace = $this->database->prepare(<<<'SQL'
SELECT workspace.id, workspace.name FROM tenant_workspaces workspace
JOIN directory_cohorts cohort ON cohort.id = workspace.cohort_id
WHERE cohort.program_id = :program AND cohort.entry_year = :year
SQL);
        $workspace->execute(['program' => $fixture['program_id'], 'year' => $entryYear]);
        $row = $workspace->fetch();
        $this->assert($row !== false && (string) $row['id'] === $result['workspace_id'], 'Approval did not create the class the group asked for.');
        $this->assert($row['name'] === 'Class Name ' . $suffix, 'Approval did not use the owner-supplied class display name.');

        $statuses = $this->database->prepare('SELECT status, resolved_by_user_id, resolved_at FROM class_creation_requests WHERE program_id = :program AND entry_year = :year');
        $statuses->execute(['program' => $fixture['program_id'], 'year' => $entryYear]);
        $rows = $statuses->fetchAll();
        $this->assert(count($rows) === 3, 'Expected three request rows for the approved identity.');
        foreach ($rows as $requestRow) {
            $this->assert($requestRow['status'] === 'created', 'Approval left a request row not marked created.');
            $this->assert($requestRow['resolved_by_user_id'] === $owner, 'Approval did not record the approving owner as resolver.');
            $this->assert($requestRow['resolved_at'] !== null, 'Approval did not stamp resolved_at.');
        }
    }

    /**
     * The status CHECK constraint is temporarily narrowed so the
     * status-closing UPDATE genuinely fails after ClassProvisioningService
     * has already written the cohort/workspace rows in the same PHP call --
     * both share one Transaction::run() closure, so if either half were not
     * rolled back with the other, this test fails.
     */
    private function assertFailureMidApprovalLeavesNeitherClassNorStatusChange(): void
    {
        $suffix = $this->suffix();
        $owner = $this->platformSuperAdmin('Atomic Owner ' . $suffix);
        $fixture = $this->provisionFixtureProgram($owner, $suffix, 4003);
        $entryYear = 7201;
        $protector = $this->protector();

        $this->requestClassCreation($protector, 'tg-atomic-a-' . $suffix, $fixture['program_id'], $entryYear, '+989142000' . substr($suffix, 0, 3));
        $this->requestClassCreation($protector, 'tg-atomic-b-' . $suffix, $fixture['program_id'], $entryYear, '+989142100' . substr($suffix, 0, 3));

        $service = $this->requests();
        $this->database->exec("ALTER TABLE class_creation_requests DROP CHECK chk_class_creation_requests_status");
        $this->database->exec("ALTER TABLE class_creation_requests ADD CONSTRAINT chk_class_creation_requests_status CHECK (status IN ('pending', 'declined'))");
        $threw = false;
        try {
            $service->approveGroup($owner, $fixture['program_id'], $entryYear, 'Atomic Cohort ' . $suffix, 'Atomic Class ' . $suffix);
        } catch (Throwable) {
            $threw = true;
        } finally {
            $this->database->exec("ALTER TABLE class_creation_requests DROP CHECK chk_class_creation_requests_status");
            $this->database->exec("ALTER TABLE class_creation_requests ADD CONSTRAINT chk_class_creation_requests_status CHECK (status IN ('pending', 'created', 'declined'))");
        }
        $this->assert($threw, 'Expected the mid-approval failure to throw.');

        $statuses = $this->database->prepare('SELECT status, resolved_by_user_id, resolved_at FROM class_creation_requests WHERE program_id = :program AND entry_year = :year');
        $statuses->execute(['program' => $fixture['program_id'], 'year' => $entryYear]);
        foreach ($statuses->fetchAll() as $row) {
            $this->assert($row['status'] === 'pending', 'A failed approval left a request in a non-pending state -- status half applied.');
            $this->assert($row['resolved_by_user_id'] === null, 'A failed approval recorded a resolver despite rolling back.');
            $this->assert($row['resolved_at'] === null, 'A failed approval stamped resolved_at despite rolling back.');
        }

        $cohort = $this->database->prepare('SELECT COUNT(*) FROM directory_cohorts WHERE program_id = :program AND entry_year = :year');
        $cohort->execute(['program' => $fixture['program_id'], 'year' => $entryYear]);
        $this->assert((int) $cohort->fetchColumn() === 0, 'A failed approval left the new cohort in place despite rolling back -- class half applied.');
    }

    private function assertDecliningIsTerminalAndCannotThenBeApproved(): void
    {
        $suffix = $this->suffix();
        $owner = $this->platformSuperAdmin('Decline Owner ' . $suffix);
        $fixture = $this->provisionFixtureProgram($owner, $suffix, 4004);
        $entryYear = 7301;
        $protector = $this->protector();

        $this->requestClassCreation($protector, 'tg-decl-a-' . $suffix, $fixture['program_id'], $entryYear, '+989143000' . substr($suffix, 0, 3));
        $this->requestClassCreation($protector, 'tg-decl-b-' . $suffix, $fixture['program_id'], $entryYear, '+989143100' . substr($suffix, 0, 3));

        $service = $this->requests();
        $declined = $service->declineGroup($owner, $fixture['program_id'], $entryYear);
        $this->assert($declined['declined_count'] === 2, 'Decline did not report resolving both pending requests.');

        $statuses = $this->database->prepare('SELECT status, resolved_by_user_id FROM class_creation_requests WHERE program_id = :program AND entry_year = :year');
        $statuses->execute(['program' => $fixture['program_id'], 'year' => $entryYear]);
        foreach ($statuses->fetchAll() as $row) {
            $this->assert($row['status'] === 'declined', 'Decline did not mark a request row declined.');
            $this->assert($row['resolved_by_user_id'] === $owner, 'Decline did not record the declining owner as resolver.');
        }

        $this->expectCode('class_creation_request_not_found', fn () => $service->approveGroup($owner, $fixture['program_id'], $entryYear, 'x', 'y'));

        $workspace = $this->database->prepare(<<<'SQL'
SELECT COUNT(*) FROM tenant_workspaces workspace
JOIN directory_cohorts cohort ON cohort.id = workspace.cohort_id
WHERE cohort.program_id = :program AND cohort.entry_year = :year
SQL);
        $workspace->execute(['program' => $fixture['program_id'], 'year' => $entryYear]);
        $this->assert((int) $workspace->fetchColumn() === 0, 'A declined-then-approve-attempted group still ended up with a class.');
    }

    private function assertNonOwnerIsRefusedListApprovalAndDecline(): void
    {
        $suffix = $this->suffix();
        $owner = $this->platformSuperAdmin('Stranger Fixture Owner ' . $suffix);
        $fixture = $this->provisionFixtureProgram($owner, $suffix, 4005);
        $entryYear = 7401;
        $protector = $this->protector();
        $this->requestClassCreation($protector, 'tg-stranger-' . $suffix, $fixture['program_id'], $entryYear, '+989144000' . substr($suffix, 0, 3));

        $stranger = $this->plainUser('Stranger ' . $suffix);
        $service = $this->requests();

        $this->expectCode('forbidden', fn () => $service->listPendingGroups($stranger));
        $this->expectCode('forbidden', fn () => $service->approveGroup($stranger, $fixture['program_id'], $entryYear, 'x', 'y'));
        $this->expectCode('forbidden', fn () => $service->declineGroup($stranger, $fixture['program_id'], $entryYear));

        $status = $this->database->prepare('SELECT status FROM class_creation_requests WHERE program_id = :program AND entry_year = :year');
        $status->execute(['program' => $fixture['program_id'], 'year' => $entryYear]);
        $this->assert($status->fetchColumn() === 'pending', 'A refused non-owner call somehow still changed the request status.');
    }

    private function assertApprovingRequestForAlreadyExistingClassResolvesWithoutDuplicate(): void
    {
        $suffix = $this->suffix();
        $owner = $this->platformSuperAdmin('Manual Overlap Owner ' . $suffix);
        $fixture = $this->provisionFixtureProgram($owner, $suffix, 4006);
        $entryYear = 7501;
        $protector = $this->protector();

        $this->requestClassCreation($protector, 'tg-overlap-a-' . $suffix, $fixture['program_id'], $entryYear, '+989145000' . substr($suffix, 0, 3));
        $this->requestClassCreation($protector, 'tg-overlap-b-' . $suffix, $fixture['program_id'], $entryYear, '+989145100' . substr($suffix, 0, 3));

        // Someone creates the exact same class manually, through the normal
        // owner class wizard path, before the pending requests are reviewed.
        $manual = $this->provisioning()->createClass($owner, [
            'country' => ['code' => 'ZZ', 'name' => 'Fixture Country'],
            'province' => ['name' => $fixture['province']],
            'city' => ['name' => $fixture['city']],
            'institution' => ['name' => $fixture['institution']],
            'faculty' => ['name' => $fixture['faculty']],
            'program' => ['name' => $fixture['program'], 'degree_level' => 'professional-doctorate'],
            'cohort' => ['entry_year' => $entryYear, 'label' => 'Manually Created Cohort ' . $suffix],
            'workspace' => ['name' => 'Manually Created Class ' . $suffix],
        ]);
        $this->assert($manual['workspace_created'] === true, 'Fixture setup did not actually create the manual class.');

        $service = $this->requests();
        $result = $service->approveGroup($owner, $fixture['program_id'], $entryYear, 'Ignored Cohort Label', 'Ignored Class Name');
        $this->assert($result['workspace_created'] === false, 'Approval created a duplicate class instead of reusing the manually created one.');
        $this->assert($result['workspace_id'] === $manual['workspace_id'], 'Approval resolved to a different workspace than the one already manually created.');
        $this->assert($result['resolved_count'] === 2, 'Approval did not still close both pending requests for the now-existing class.');

        $count = $this->database->prepare(<<<'SQL'
SELECT COUNT(*) FROM tenant_workspaces workspace
JOIN directory_cohorts cohort ON cohort.id = workspace.cohort_id
WHERE cohort.program_id = :program AND cohort.entry_year = :year
SQL);
        $count->execute(['program' => $fixture['program_id'], 'year' => $entryYear]);
        $this->assert((int) $count->fetchColumn() === 1, 'More than one class ended up existing for the same identity.');

        $statuses = $this->database->prepare('SELECT status FROM class_creation_requests WHERE program_id = :program AND entry_year = :year');
        $statuses->execute(['program' => $fixture['program_id'], 'year' => $entryYear]);
        foreach ($statuses->fetchAll() as $row) {
            $this->assert($row['status'] === 'created', 'A request against an already-existing class was not resolved as created.');
        }
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

    private function requests(): ClassCreationRequestService
    {
        return new ClassCreationRequestService($this->database, $this->accessGate(), new AuditLogger($this->database), $this->provisioning());
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

    /**
     * Creates the directory chain (institution/faculty/program) plus one
     * baseline class at $baselineEntryYear, purely so the program exists for
     * requests to reference -- the entry years used by the actual test
     * scenarios are always a different year with no cohort/workspace yet.
     *
     * @return array<string,mixed>
     */
    private function provisionFixtureProgram(string $owner, string $suffix, int $baselineEntryYear): array
    {
        $names = [
            'province' => 'Fixture Province ' . $suffix,
            'city' => 'Fixture City ' . $suffix,
            'institution' => 'Fixture University ' . $suffix,
            'faculty' => 'Fixture Faculty ' . $suffix,
            'program' => 'Fixture Program ' . $suffix,
        ];
        $result = $this->provisioning()->createClass($owner, [
            'country' => ['code' => 'ZZ', 'name' => 'Fixture Country'],
            'province' => ['name' => $names['province']],
            'city' => ['name' => $names['city']],
            'institution' => ['name' => $names['institution']],
            'faculty' => ['name' => $names['faculty']],
            'program' => ['name' => $names['program'], 'degree_level' => 'professional-doctorate'],
            'cohort' => ['entry_year' => $baselineEntryYear, 'label' => 'Fixture Baseline Cohort ' . $suffix],
            'workspace' => ['name' => 'Fixture Baseline Class ' . $suffix],
        ]);
        return $result + $names;
    }

    private function requestClassCreation(ChannelSubjectProtector $protector, string $subject, string $programId, int $entryYear, string $phone): void
    {
        $this->markPhoneVerified($protector, 'telegram', $subject, $phone);
        $result = $this->classMembership($protector)->requestClassCreation('telegram', $subject, $programId, $entryYear);
        if ($result['status'] !== 'pending') {
            throw new RuntimeException('Fixture class-creation request did not land in pending status.');
        }
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
