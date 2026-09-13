<?php

declare(strict_types=1);

namespace Fanoos\Tests\Core;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Core\ClassProvisioningService;
use Fanoos\Platform\Core\InstitutionTermService;
use Fanoos\Platform\Core\WorkspacePlatformService;
use Fanoos\Platform\Messaging\ChannelSubjectProtector;
use Fanoos\Platform\Messaging\MessagingLinkService;
use Fanoos\Platform\Onboarding\ClassMembershipService;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

/**
 * Term dates (docs/product/01_FRONT_DOOR.md #4): an owner sets a term once
 * per institution, and every currently active class under it gets the same
 * dates without anyone retyping them. academic_terms stays keyed
 * (workspace_id, term_key) throughout -- this only ever materialises rows
 * there, it never changes what reads or joins them.
 */
final class InstitutionTermServiceTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $this->assertSettingTermMaterialisesIntoEveryActiveClassAndNoneOutside();
        $this->assertReapplyingIsIdempotent();
        $this->assertClassCreatedAfterTermExistsInheritsIt();
        $this->assertOverrideSurvivesLaterInstitutionWideEdit();
        $this->assertNonOwnerCannotSetInstitutionTerm();
        $this->assertRepresentativeOfOneClassCannotTouchAnothersDates();
        $this->assertInvalidRangeIsRefusedBeforeAnythingIsWritten();
        $this->assertFailureMidApplyLeavesNeitherInstitutionRowNorMaterialisedRowsChanged();

        return $this->assertions;
    }

    private function assertSettingTermMaterialisesIntoEveryActiveClassAndNoneOutside(): void
    {
        $suffix = $this->suffix();
        $owner = $this->platformSuperAdmin('Materialize Owner ' . $suffix);
        $institution = 'inst-a-' . $suffix;
        $classA1 = $this->provisionFixtureClass($owner, $institution, 'a1-' . $suffix, 5001);
        $classA2 = $this->provisionFixtureClass($owner, $institution, 'a2-' . $suffix, 5002);
        $otherInstitution = 'inst-b-' . $suffix;
        $classB1 = $this->provisionFixtureClass($owner, $otherInstitution, 'b1-' . $suffix, 5003);
        $this->assert($classA1['institution_id'] === $classA2['institution_id'], 'Fixture setup did not reuse the same institution for both A classes.');
        $this->assert($classA1['institution_id'] !== $classB1['institution_id'], 'Fixture setup accidentally reused the same institution across A and B.');

        $service = $this->terms();
        $result = $service->setInstitutionTerm(
            $owner, $classA1['institution_id'], 'fall-' . $suffix, 'Fall ' . $suffix, '2026-09-01', '2027-01-15', 'planned',
        );
        $this->assert($result['applied_count'] === 2, 'Setting an institution term did not apply to both active classes under it.');
        $this->assert($result['skipped_count'] === 0, 'Setting a fresh institution term unexpectedly skipped a class.');

        foreach ([$classA1, $classA2] as $class) {
            $row = $this->academicTermRow($class['workspace_id'], 'fall-' . $suffix);
            $this->assert($row !== null, 'A class under the institution did not receive the materialised term.');
            $this->assert($row['starts_on'] === '2026-09-01', 'Materialised term did not carry the institution start date.');
            $this->assert($row['ends_on'] === '2027-01-15', 'Materialised term did not carry the institution end date.');
            $this->assert($row['origin'] === 'inherited', 'Materialised term was not marked inherited.');
            $this->assert($row['institution_term_id'] === $result['institution_term_id'], 'Materialised term did not link back to the institution term.');
        }

        $this->assert($this->academicTermRow($classB1['workspace_id'], 'fall-' . $suffix) === null, 'A class under a different institution received the term anyway.');
    }

    private function assertReapplyingIsIdempotent(): void
    {
        $suffix = $this->suffix();
        $owner = $this->platformSuperAdmin('Idempotent Owner ' . $suffix);
        $institution = 'inst-idem-' . $suffix;
        $class = $this->provisionFixtureClass($owner, $institution, 'c-' . $suffix, 5101);

        $service = $this->terms();
        $service->setInstitutionTerm($owner, $class['institution_id'], 'spring-' . $suffix, 'Spring ' . $suffix, '2027-02-01', '2027-06-15', 'planned');
        $service->setInstitutionTerm($owner, $class['institution_id'], 'spring-' . $suffix, 'Spring ' . $suffix . ' Renamed', '2027-02-05', '2027-06-20', 'active');

        $count = $this->database->prepare("SELECT COUNT(*) FROM institution_terms WHERE institution_id = :institution AND term_key = :key");
        $count->execute(['institution' => $class['institution_id'], 'key' => 'spring-' . $suffix]);
        $this->assert((int) $count->fetchColumn() === 1, 'Re-applying an institution term created a duplicate institution_terms row.');

        $termCount = $this->database->prepare('SELECT COUNT(*) FROM academic_terms WHERE workspace_id = :workspace AND term_key = :key');
        $termCount->execute(['workspace' => $class['workspace_id'], 'key' => 'spring-' . $suffix]);
        $this->assert((int) $termCount->fetchColumn() === 1, 'Re-applying an institution term created a duplicate academic_terms row.');

        $row = $this->academicTermRow($class['workspace_id'], 'spring-' . $suffix);
        $this->assert($row['ends_on'] === '2027-06-20', 'Re-applying an institution term did not update the materialised dates.');
    }

    private function assertClassCreatedAfterTermExistsInheritsIt(): void
    {
        $suffix = $this->suffix();
        $owner = $this->platformSuperAdmin('Inherit Owner ' . $suffix);
        $institution = 'inst-inherit-' . $suffix;
        $firstClass = $this->provisionFixtureClass($owner, $institution, 'first-' . $suffix, 5201);

        $service = $this->terms();
        $service->setInstitutionTerm($owner, $firstClass['institution_id'], 'summer-' . $suffix, 'Summer ' . $suffix, '2027-07-01', '2027-08-20', 'planned');

        // A class created after the term already exists -- the hook
        // ClassCreationRequestService::approveGroup() and the /classes HTTP
        // route both call after ClassProvisioningService::createClass()
        // succeeds.
        $laterClass = $this->provisionFixtureClass($owner, $institution, 'later-' . $suffix, 5202);
        $service->materializeCurrentTermsIntoWorkspace($laterClass['workspace_id'], $laterClass['institution_id']);

        $row = $this->academicTermRow($laterClass['workspace_id'], 'summer-' . $suffix);
        $this->assert($row !== null, 'A class created after the institution term existed did not inherit it.');
        $this->assert($row['origin'] === 'inherited', 'A newly inherited term was not marked inherited.');
    }

    private function assertOverrideSurvivesLaterInstitutionWideEdit(): void
    {
        $suffix = $this->suffix();
        $owner = $this->platformSuperAdmin('Override Owner ' . $suffix);
        $repSubject = 'tg-term-rep-' . $suffix;
        $institution = 'inst-override-' . $suffix;
        $class = $this->provisionFixtureClass($owner, $institution, 'ov-' . $suffix, 5301);
        $protector = $this->protector();
        $repUserId = $this->joinAsMember($protector, $repSubject, $class);
        $this->workspacePlatform()->assignRepresentative($owner, $class['workspace_id'], $repUserId);

        $service = $this->terms();
        $service->setInstitutionTerm($owner, $class['institution_id'], 'winter-' . $suffix, 'Winter ' . $suffix, '2027-11-01', '2028-02-01', 'planned');

        $override = $service->overrideClassTerm(
            $repUserId, $class['workspace_id'], 'winter-' . $suffix, 'Winter ' . $suffix . ' (delayed)', '2027-11-15', '2028-02-15', 'planned',
        );
        $this->assert($override['created'] === false, 'Overriding an already-materialised term reported creating a new row.');
        $overridden = $this->academicTermRow($class['workspace_id'], 'winter-' . $suffix);
        $this->assert($overridden['origin'] === 'override', 'Override did not mark the row as override.');
        $this->assert($overridden['starts_on'] === '2027-11-15', 'Override did not take effect.');

        // A later institution-wide edit must not clobber the override.
        $service->setInstitutionTerm($owner, $class['institution_id'], 'winter-' . $suffix, 'Winter ' . $suffix, '2027-11-01', '2028-02-01', 'planned');
        $stillOverridden = $this->academicTermRow($class['workspace_id'], 'winter-' . $suffix);
        $this->assert($stillOverridden['origin'] === 'override', 'A later institution-wide edit changed the origin away from override.');
        $this->assert($stillOverridden['starts_on'] === '2027-11-15', 'A later institution-wide edit clobbered the representative override.');
        $this->assert($stillOverridden['ends_on'] === '2028-02-15', 'A later institution-wide edit clobbered the representative override end date.');
    }

    private function assertNonOwnerCannotSetInstitutionTerm(): void
    {
        $suffix = $this->suffix();
        $owner = $this->platformSuperAdmin('Stranger Fixture Owner ' . $suffix);
        $institution = 'inst-stranger-' . $suffix;
        $class = $this->provisionFixtureClass($owner, $institution, 'str-' . $suffix, 5401);
        $stranger = $this->plainUser('Stranger ' . $suffix);

        $service = $this->terms();
        $this->expectCode('forbidden', fn () => $service->setInstitutionTerm(
            $stranger, $class['institution_id'], 'x-' . $suffix, 'X', '2027-01-01', '2027-06-01', 'planned',
        ));
        $this->expectCode('forbidden', fn () => $service->listInstitutions($stranger));
    }

    private function assertRepresentativeOfOneClassCannotTouchAnothersDates(): void
    {
        $suffix = $this->suffix();
        $owner = $this->platformSuperAdmin('Isolation Owner ' . $suffix);
        $institution = 'inst-iso-' . $suffix;
        $classA = $this->provisionFixtureClass($owner, $institution, 'iso-a-' . $suffix, 5501);
        $classB = $this->provisionFixtureClass($owner, $institution, 'iso-b-' . $suffix, 5502);
        $protector = $this->protector();
        $repBSubject = 'tg-iso-repb-' . $suffix;
        $repBUserId = $this->joinAsMember($protector, $repBSubject, $classB);
        $this->workspacePlatform()->assignRepresentative($owner, $classB['workspace_id'], $repBUserId);

        $service = $this->terms();
        $this->expectCode('forbidden', fn () => $service->overrideClassTerm(
            $repBUserId, $classA['workspace_id'], 'x-' . $suffix, 'X', '2027-01-01', '2027-06-01', 'planned',
        ));
        $this->expectCode('forbidden', fn () => $service->listWorkspaceTerms($repBUserId, $classA['workspace_id']));

        $ownClassView = $service->listWorkspaceTerms($repBUserId, $classB['workspace_id']);
        $this->assert($ownClassView['can_override'] === true, 'Representative of their own class was not signalled can_override.');
    }

    private function assertInvalidRangeIsRefusedBeforeAnythingIsWritten(): void
    {
        $suffix = $this->suffix();
        $owner = $this->platformSuperAdmin('Range Owner ' . $suffix);
        $institution = 'inst-range-' . $suffix;
        $class = $this->provisionFixtureClass($owner, $institution, 'range-' . $suffix, 5601);

        $service = $this->terms();
        $this->expectCode('invalid_term_range', fn () => $service->setInstitutionTerm(
            $owner, $class['institution_id'], 'bad-' . $suffix, 'Bad', '2027-06-01', '2027-01-01', 'planned',
        ));
        $count = $this->database->prepare('SELECT COUNT(*) FROM institution_terms WHERE institution_id = :institution AND term_key = :key');
        $count->execute(['institution' => $class['institution_id'], 'key' => 'bad-' . $suffix]);
        $this->assert((int) $count->fetchColumn() === 0, 'An invalid date range was written before being refused.');

        $protector = $this->protector();
        $repSubject = 'tg-range-rep-' . $suffix;
        $repUserId = $this->joinAsMember($protector, $repSubject, $class);
        $this->workspacePlatform()->assignRepresentative($owner, $class['workspace_id'], $repUserId);
        $this->expectCode('invalid_term_range', fn () => $service->overrideClassTerm(
            $repUserId, $class['workspace_id'], 'bad-' . $suffix, 'Bad', '2027-06-01', '2027-01-01', 'planned',
        ));
        $this->assert($this->academicTermRow($class['workspace_id'], 'bad-' . $suffix) === null, 'An invalid override date range was written before being refused.');
    }

    /**
     * Fault injection is a PDOStatement subclass installed via
     * PDO::ATTR_STATEMENT_CLASS, exactly like
     * tests/Core/ClassCreationRequestServiceTest.php's atomicity test --
     * never a schema mutation (DDL causes an implicit commit in MySQL,
     * defeating the very rollback under test). It throws once, only for the
     * INSERT that materialises the term into the second class, after the
     * institution_terms row and the first class's academic_terms row have
     * already been written in the same PHP call.
     */
    private function assertFailureMidApplyLeavesNeitherInstitutionRowNorMaterialisedRowsChanged(): void
    {
        $suffix = $this->suffix();
        $owner = $this->platformSuperAdmin('Atomic Owner ' . $suffix);
        $institution = 'inst-atomic-' . $suffix;
        $classA = $this->provisionFixtureClass($owner, $institution, 'atomic-a-' . $suffix, 5701);
        $classB = $this->provisionFixtureClass($owner, $institution, 'atomic-b-' . $suffix, 5702);

        $service = $this->terms();
        $this->database->setAttribute(PDO::ATTR_STATEMENT_CLASS, [ThrowingPdoStatement::class, []]);
        ThrowingPdoStatement::arm('INSERT INTO academic_terms');
        $threw = false;
        try {
            $service->setInstitutionTerm($owner, $classA['institution_id'], 'atomic-' . $suffix, 'Atomic', '2028-01-01', '2028-06-01', 'planned');
        } catch (Throwable) {
            $threw = true;
        } finally {
            ThrowingPdoStatement::disarm();
            $this->database->setAttribute(PDO::ATTR_STATEMENT_CLASS, [PDOStatement::class]);
        }
        $this->assert($threw, 'Expected the mid-apply failure to throw.');

        $institutionCount = $this->database->prepare('SELECT COUNT(*) FROM institution_terms WHERE institution_id = :institution AND term_key = :key');
        $institutionCount->execute(['institution' => $classA['institution_id'], 'key' => 'atomic-' . $suffix]);
        $this->assert((int) $institutionCount->fetchColumn() === 0, 'A failed apply left the institution_terms row in place despite rolling back.');

        $this->assert($this->academicTermRow($classA['workspace_id'], 'atomic-' . $suffix) === null, 'A failed apply left a materialised term on the first class despite rolling back.');
        $this->assert($this->academicTermRow($classB['workspace_id'], 'atomic-' . $suffix) === null, 'A failed apply left a materialised term on the second class despite rolling back.');
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

    private function terms(): InstitutionTermService
    {
        return new InstitutionTermService($this->database, $this->accessGate(), new AuditLogger($this->database));
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

    /**
     * Creates a class under a chosen institution identity: the same
     * $institutionSuffix across calls resolves to the same institution
     * (find-or-create by name/code, same as ClassProvisioningService always
     * does), while $classSuffix varies the faculty/program/cohort/workspace
     * so each call is a distinct class.
     *
     * @return array<string,mixed>
     */
    private function provisionFixtureClass(string $owner, string $institutionSuffix, string $classSuffix, int $entryYear): array
    {
        return $this->provisioning()->createClass($owner, [
            'country' => ['code' => 'ZZ', 'name' => 'Fixture Country'],
            'province' => ['name' => 'Fixture Province ' . $institutionSuffix],
            'city' => ['name' => 'Fixture City ' . $institutionSuffix],
            'institution' => ['name' => 'Fixture University ' . $institutionSuffix],
            'faculty' => ['name' => 'Fixture Faculty ' . $classSuffix],
            'program' => ['name' => 'Fixture Program ' . $classSuffix, 'degree_level' => 'professional-doctorate'],
            'cohort' => ['entry_year' => $entryYear, 'label' => 'Fixture Cohort ' . $classSuffix],
            'workspace' => ['name' => 'Fixture Class ' . $classSuffix],
        ]);
    }

    /** @param array<string,mixed> $fixture */
    private function joinAsMember(ChannelSubjectProtector $protector, string $subject, array $fixture): string
    {
        // Uuid::v7() is time-ordered: its leading hex is a millisecond timestamp,
        // so two fixtures created in the same millisecond produced the SAME
        // phone, resolved to one user, and the second link violated
        // uq_messaging_links_user_platform. Use randomness, not the clock.
        $phone = '+9891' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        $this->markPhoneVerified($protector, 'telegram', $subject, $phone);
        $membership = $this->classMembership($protector);
        $entryYear = $this->entryYearFor($fixture);
        $joined = $membership->join('telegram', $subject, $fixture['program_id'], $entryYear);
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

    /** @return array<string,mixed>|null */
    private function academicTermRow(string $workspaceId, string $termKey): ?array
    {
        $query = $this->database->prepare('SELECT * FROM academic_terms WHERE workspace_id = :workspace AND term_key = :key');
        $query->execute(['workspace' => $workspaceId, 'key' => $termKey]);
        $row = $query->fetch();
        return $row === false ? null : $row;
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
