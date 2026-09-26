<?php

declare(strict_types=1);

namespace Fanoos\Tests\Core;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Core\ClassProvisioningService;
use Fanoos\Platform\Identity\AuthService;
use Fanoos\Platform\Identity\PasswordHasher;
use Fanoos\Platform\Identity\StudentRegistrationService;
use Fanoos\Platform\Messaging\ChannelSubjectProtector;
use Fanoos\Platform\Messaging\MessagingLinkService;
use Fanoos\Platform\Onboarding\ClassMembershipService;
use Fanoos\Platform\Onboarding\DirectoryReadService;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;

/**
 * Website sign-up and discipline libraries: an account made with a username
 * and password and no phone, which can sign in, and which lands in its
 * field's library with the role that opens that library's exams.
 */
final class StudentRegistrationTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $this->assertSignUpOpensTheDisciplineLibrary();
        $this->assertATakenUsernameLeavesNoOrphanAccount();
        $this->assertInvalidInputIsRefusedBeforeAnyWrite();
        $this->assertStudentsWhoSignedUpBeforeTheLibraryAreEnrolledLater();
        $this->assertALibraryIsNeverOfferedAsAClass();
        $this->assertSignUpIsThrottledPerSource();

        return $this->assertions;
    }

    private function assertSignUpOpensTheDisciplineLibrary(): void
    {
        $suffix = $this->suffix();
        $library = $this->fixtureClass($suffix, 1402)['workspace_id'];
        $discipline = $this->discipline($suffix, $library);

        $session = $this->service()->register($this->input($suffix, $discipline), 'src-' . $suffix);
        $userId = $session->userId;

        $this->assert($this->hasRole($userId, $library, 'student'), 'A new student was not given the student role in their library.');
        $selected = $this->database->prepare('SELECT selected_workspace_id FROM iam_sessions WHERE id = :id');
        $selected->execute(['id' => $session->sessionId]);
        $this->assert($selected->fetchColumn() === $library, 'Sign-up did not land the student in their library.');

        $phones = $this->database->prepare("SELECT COUNT(*) FROM iam_user_identifiers WHERE user_id = :user AND identifier_type = 'phone'");
        $phones->execute(['user' => $userId]);
        $this->assert((int) $phones->fetchColumn() === 0, 'Sign-up recorded a phone although none was asked for.');

        $profile = $this->service()->profile($userId);
        $this->assert(($profile['discipline_id'] ?? null) === $discipline && ($profile['entry_year'] ?? null) == 1402, 'The profile was not stored.');

        // The account signs in with what it signed up with -- username in any case.
        $login = $this->auth()->login('User_' . $suffix, 'correct horse 42', 'login-' . $suffix);
        $this->assert($login->userId === $userId, 'The new account could not sign in with its username and password.');
    }

    private function assertATakenUsernameLeavesNoOrphanAccount(): void
    {
        $suffix = $this->suffix();
        $discipline = $this->discipline($suffix, null);
        $this->service()->register($this->input($suffix, $discipline), 'src-a-' . $suffix);
        $before = (int) $this->database->query('SELECT COUNT(*) FROM iam_users')->fetchColumn();

        $this->expectCode('username_taken', fn () => $this->service()->register(
            array_replace($this->input($suffix, $discipline), ['first_name' => 'دیگری']),
            'src-b-' . $suffix,
        ));
        $after = (int) $this->database->query('SELECT COUNT(*) FROM iam_users')->fetchColumn();
        $this->assert($after === $before, 'A refused sign-up left an account row behind.');
    }

    private function assertInvalidInputIsRefusedBeforeAnyWrite(): void
    {
        $suffix = $this->suffix();
        $discipline = $this->discipline($suffix, null);
        $base = $this->input($suffix, $discipline);
        foreach ([
            'username_invalid' => ['username' => '1abc'],
            'password_invalid' => ['password' => 'short'],
            'name_required' => ['last_name' => '<b>'],
            'entry_year_invalid' => ['entry_year' => '2024'],
            'course_type_invalid' => ['course_type' => 'evening'],
            'student_number_invalid' => ['student_number' => '12ab'],
        ] as $code => $override) {
            $this->expectCode($code, fn () => $this->service()->register(array_replace($base, $override), 'src-' . $code . $suffix));
        }
        $this->expectCode('discipline_not_found', fn () => $this->service()->register(
            array_replace($base, ['discipline_id' => Uuid::v7()]),
            'src-nd-' . $suffix,
        ));
    }

    private function assertStudentsWhoSignedUpBeforeTheLibraryAreEnrolledLater(): void
    {
        $suffix = $this->suffix();
        $discipline = $this->discipline($suffix, null);
        $session = $this->service()->register($this->input($suffix, $discipline), 'src-' . $suffix);

        $memberships = $this->database->prepare('SELECT COUNT(*) FROM tenant_workspace_memberships WHERE user_id = :user');
        $memberships->execute(['user' => $session->userId]);
        $this->assert((int) $memberships->fetchColumn() === 0, 'A discipline with no library still produced a membership.');

        $library = $this->fixtureClass($suffix, 1403)['workspace_id'];
        $this->database->prepare('UPDATE academic_disciplines SET library_workspace_id = :w WHERE id = :d')->execute(['w' => $library, 'd' => $discipline]);
        $this->assert($this->service()->enrolDisciplineStudents($discipline) === 1, 'The earlier student was not counted.');
        $this->assert($this->hasRole($session->userId, $library, 'student'), 'The earlier student was not enrolled in the new library.');
        $this->service()->enrolDisciplineStudents($discipline);
        $memberships->execute(['user' => $session->userId]);
        $this->assert((int) $memberships->fetchColumn() === 1, 'Enrolling twice duplicated a membership.');
    }

    private function assertALibraryIsNeverOfferedAsAClass(): void
    {
        $suffix = $this->suffix();
        $class = $this->fixtureClass($suffix, 1404);
        $this->discipline($suffix, $class['workspace_id']);

        $page = (new DirectoryReadService($this->database))->joinableCohortsByProgram($class['program_id']);
        $this->assert($page['items'] === [], 'A discipline library was listed as a joinable class.');

        $protector = new ChannelSubjectProtector(str_repeat('r', 32));
        $subject = 'tg-lib-' . $suffix;
        $phone = '+989123' . substr(preg_replace('/\D/', '', $suffix) . '000000', 0, 6);
        $statement = $this->database->prepare(<<<'SQL'
INSERT INTO onboarding_verified_phones (platform, subject_digest, phone_digest, phone_ciphertext, verified_at, updated_at)
VALUES ('telegram', :subject, :digest, :cipher, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL);
        $statement->bindValue(':subject', $protector->digest('onboarding:telegram', $subject), PDO::PARAM_LOB);
        $statement->bindValue(':digest', $protector->digest('phone', $phone), PDO::PARAM_LOB);
        $statement->bindValue(':cipher', $protector->encrypt('phone', $phone), PDO::PARAM_LOB);
        $statement->execute();
        $links = new MessagingLinkService($this->database, new AuditLogger($this->database), $protector);
        $membership = new ClassMembershipService($this->database, new AuditLogger($this->database), $protector, $links, $this->access());
        $joined = $membership->join('telegram', $subject, $class['program_id'], 1404);
        $this->assert($joined['status'] === 'class_not_found', 'The class-join wizard joined a discipline library.');
    }

    private function assertSignUpIsThrottledPerSource(): void
    {
        $suffix = $this->suffix();
        $discipline = $this->discipline($suffix, null);
        $source = 'burst-' . $suffix;
        for ($i = 0; $i < 5; $i++) {
            $this->service()->register(array_replace($this->input($suffix, $discipline), ['username' => "burst{$i}_" . $suffix]), $source);
        }
        $this->expectCode('registration_throttled', fn () => $this->service()->register(
            array_replace($this->input($suffix, $discipline), ['username' => 'burst9_' . $suffix]),
            $source,
        ));
        // Another network is unaffected.
        $this->service()->register(array_replace($this->input($suffix, $discipline), ['username' => 'other_' . $suffix]), 'elsewhere-' . $suffix);
        ++$this->assertions;
    }

    /** @return array<string, string> */
    private function input(string $suffix, string $disciplineId): array
    {
        return [
            'username' => 'user_' . $suffix,
            'password' => 'correct horse 42',
            'first_name' => 'آرین',
            'last_name' => 'قاسم‌پور',
            'discipline_id' => $disciplineId,
            'entry_year' => '۱۴۰۲',
            'entry_term' => 'first',
            'course_type' => 'daily',
            'student_number' => '401123456',
        ];
    }

    private function discipline(string $suffix, ?string $libraryWorkspaceId): string
    {
        $id = Uuid::v7();
        $this->database->prepare(<<<'SQL'
INSERT INTO academic_disciplines (id, code, name, library_workspace_id, status, sort_order, created_at, updated_at)
VALUES (:id, :code, :name, :library, 'active', 0, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL)->execute(['id' => $id, 'code' => 'test-' . $suffix, 'name' => 'رشته ' . $suffix, 'library' => $libraryWorkspaceId]);

        return $id;
    }

    /** @return array<string, mixed> */
    private function fixtureClass(string $suffix, int $entryYear): array
    {
        $owner = Uuid::v7();
        $this->database->prepare("INSERT INTO iam_users (id, display_name, status, locale, created_at, updated_at) VALUES (:id, 'Registration Owner', 'active', 'fa-IR', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => $owner]);
        $role = $this->database->query("SELECT id FROM rbac_role_templates WHERE role_key = 'platform-super-admin' LIMIT 1")->fetchColumn();
        $this->database->prepare('INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at) VALUES (:id, :user, :role, :scope, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))')
            ->execute(['id' => Uuid::v7(), 'user' => $owner, 'role' => $role, 'scope' => '00000000-0000-7000-8000-000000000001']);

        $tag = $suffix . '-' . $entryYear;
        return (new ClassProvisioningService($this->database, $this->access(), new AuditLogger($this->database)))->createClass($owner, [
            'country' => ['code' => 'ZZ', 'name' => 'Fixture Country'],
            'province' => ['name' => 'Registration Province ' . $tag],
            'city' => ['name' => 'Registration City ' . $tag],
            'institution' => ['name' => 'Registration University ' . $tag],
            'faculty' => ['name' => 'Registration Faculty ' . $tag],
            'program' => ['name' => 'Registration Program ' . $tag, 'degree_level' => 'professional-doctorate'],
            'cohort' => ['entry_year' => $entryYear, 'label' => 'Registration Cohort ' . $tag],
            'workspace' => ['name' => 'Registration Library ' . $tag],
        ]);
    }

    private function hasRole(string $userId, string $workspaceId, string $roleKey): bool
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

    private function service(): StudentRegistrationService
    {
        $audit = new AuditLogger($this->database);

        return new StudentRegistrationService($this->database, $this->auth(), new PasswordHasher(), $audit);
    }

    private function auth(): AuthService
    {
        return new AuthService($this->database, new PasswordHasher(), new AuditLogger($this->database));
    }

    private function access(): AccessGate
    {
        return new AccessGate($this->database, new ScopeAuthorizer($this->database));
    }

    private function suffix(): string
    {
        return substr(str_replace('-', '', Uuid::v7()), -10);
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
