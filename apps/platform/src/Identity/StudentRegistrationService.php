<?php

declare(strict_types=1);

namespace Fanoos\Platform\Identity;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\TextNormalizer;
use Fanoos\Platform\Support\Transaction;
use Fanoos\Platform\Support\Uuid;
use PDO;
use PDOException;

/**
 * Website sign-up: a username and password, the same profile questions the
 * bot's join wizard asks, and membership of the student's discipline library.
 *
 * A phone number is not asked for. The owner's decision: signing up must not
 * depend on an SMS arriving, and a phone can be added to the account later.
 *
 * The discipline library is what makes a field's exams reach every student
 * of that field (see migration 0024): joining it is the whole of "your
 * field's exams are now open to you", done with the ordinary membership and
 * 'student' role, so the catalogue and attempt code needs no second path.
 */
final class StudentRegistrationService
{
    public const ENTRY_TERMS = ['first', 'second'];
    public const COURSE_TYPES = ['daily', 'tuition', 'international'];

    private const USERNAME_PATTERN = '/^[a-z][a-z0-9_.]{2,31}$/';
    private const WINDOW_SECONDS = 3600;
    private const MAX_PER_WINDOW = 5;
    private const MIN_ENTRY_YEAR = 1380;
    private const MAX_ENTRY_YEAR = 1420;

    public function __construct(
        private readonly PDO $database,
        private readonly AuthService $auth,
        private readonly PasswordHasher $passwordHasher,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return list<array{id:string,code:string,name:string,has_library:bool}> */
    public function disciplines(): array
    {
        $rows = $this->database->query(<<<'SQL'
SELECT id, code, name, library_workspace_id IS NOT NULL AS has_library
FROM academic_disciplines
WHERE status = 'active'
ORDER BY sort_order, name, id
SQL)->fetchAll();

        return array_map(static fn (array $row): array => [
            'id' => (string) $row['id'],
            'code' => (string) $row['code'],
            'name' => (string) $row['name'],
            'has_library' => (bool) $row['has_library'],
        ], $rows);
    }

    /**
     * Creates the account, signs it in, and opens its discipline library.
     *
     * @param array<string, mixed> $input
     * @param array<string, scalar|null> $client
     */
    public function register(array $input, string $source, array $client = []): AuthenticatedSession
    {
        $fields = $this->validate($input);
        $sourceDigest = hash('sha256', 'register:' . trim($source), true);

        $result = Transaction::run($this->database, function () use ($fields, $sourceDigest, $client): array|PlatformException {
            if (!$this->consumeRegistrationSlot($sourceDigest)) {
                return new PlatformException('registration_throttled', 'Too many sign-ups from this network. Try again later.', 429);
            }

            $discipline = $this->database->prepare("SELECT id, library_workspace_id FROM academic_disciplines WHERE id = :id AND status = 'active'");
            $discipline->execute(['id' => $fields['discipline_id']]);
            $disciplineRow = $discipline->fetch();
            if ($disciplineRow === false) {
                return new PlatformException('discipline_not_found', 'The chosen field of study was not found.', 422);
            }
            if ($fields['institution_id'] !== null) {
                $institution = $this->database->prepare("SELECT 1 FROM directory_institutions WHERE id = :id AND status = 'active' AND archived_at IS NULL");
                $institution->execute(['id' => $fields['institution_id']]);
                if ($institution->fetchColumn() === false) {
                    return new PlatformException('institution_not_found', 'The chosen university was not found.', 422);
                }
            }

            $userId = Uuid::v7();
            $this->database->prepare(<<<'SQL'
INSERT INTO iam_users (id, display_name, status, locale, created_at, updated_at)
VALUES (:id, :name, 'active', 'fa-IR', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL)->execute(['id' => $userId, 'name' => mb_substr($fields['first_name'] . ' ' . $fields['last_name'], 0, 160)]);

            // 'external' is the identifier type AuthService::login() gives a
            // plain name, which is what a username is; the owner's own login
            // is the same type. The unique (type, value) key is what decides
            // "taken" -- checking first and inserting after would race.
            try {
                $this->database->prepare(<<<'SQL'
INSERT INTO iam_user_identifiers (id, user_id, identifier_type, normalized_value, is_verified, verified_at, created_at)
VALUES (:id, :user, 'external', :username, FALSE, NULL, UTC_TIMESTAMP(6))
SQL)->execute(['id' => Uuid::v7(), 'user' => $userId, 'username' => $fields['username']]);
            } catch (PDOException $error) {
                if ((string) $error->getCode() === '23000') {
                    // Thrown, not returned: returning would commit the iam_users row
                    // above as an account nobody can sign in to.
                    throw new PlatformException('username_taken', 'This username is already taken.', 409);
                }
                throw $error;
            }

            $this->database->prepare(<<<'SQL'
INSERT INTO iam_authenticators (id, user_id, authenticator_type, secret_digest, metadata_json, created_at)
VALUES (:id, :user, 'password', :digest, JSON_OBJECT(), UTC_TIMESTAMP(6))
SQL)->execute(['id' => Uuid::v7(), 'user' => $userId, 'digest' => $this->passwordHasher->hash($fields['password'])]);

            $this->database->prepare(<<<'SQL'
INSERT INTO iam_student_profiles (
    user_id, first_name, last_name, discipline_id, institution_id, entry_year,
    entry_term, course_type, student_number, created_at, updated_at
) VALUES (
    :user, :first, :last, :discipline, :institution, :year,
    :term, :course, :number, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
)
SQL)->execute([
                'user' => $userId,
                'first' => $fields['first_name'],
                'last' => $fields['last_name'],
                'discipline' => $fields['discipline_id'],
                'institution' => $fields['institution_id'],
                'year' => $fields['entry_year'],
                'term' => $fields['entry_term'],
                'course' => $fields['course_type'],
                'number' => $fields['student_number'],
            ]);

            $libraryId = $disciplineRow['library_workspace_id'] !== null ? (string) $disciplineRow['library_workspace_id'] : null;
            if ($libraryId !== null) {
                $this->enrol($userId, $libraryId);
            }

            $session = $this->auth->establishSession($userId, $client);
            $this->audit->record($libraryId, $userId, 'auth.register', 'iam_user', $userId, 'success', [
                'discipline_id' => $fields['discipline_id'],
                'library_joined' => $libraryId !== null,
            ]);

            return ['session' => $session, 'library' => $libraryId];
        });

        if ($result instanceof PlatformException) {
            throw $result;
        }
        if ($result['library'] !== null) {
            $this->auth->selectWorkspace($result['session'], $result['library']);
        }

        return $result['session'];
    }

    /**
     * The signed-in student's profile, or null for an account that never
     * signed up on the website (the owner, a bot-only account).
     *
     * @return array<string, mixed>|null
     */
    public function profile(string $userId): ?array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT profile.first_name, profile.last_name, profile.entry_year, profile.entry_term,
       profile.course_type, profile.student_number,
       discipline.id AS discipline_id, discipline.name AS discipline_name,
       discipline.library_workspace_id,
       institution.id AS institution_id, institution.name AS institution_name
FROM iam_student_profiles profile
JOIN academic_disciplines discipline ON discipline.id = profile.discipline_id
LEFT JOIN directory_institutions institution ON institution.id = profile.institution_id
WHERE profile.user_id = :user
SQL);
        $query->execute(['user' => $userId]);
        $row = $query->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Enrols every profiled student of a discipline into its library. Run
     * once when an owner provisions a library for a discipline that already
     * has students, who signed up while it had none. Idempotent.
     */
    public function enrolDisciplineStudents(string $disciplineId): int
    {
        return Transaction::run($this->database, function () use ($disciplineId): int {
            $library = $this->database->prepare('SELECT library_workspace_id FROM academic_disciplines WHERE id = :id FOR UPDATE');
            $library->execute(['id' => $disciplineId]);
            $libraryId = $library->fetchColumn();
            if (!is_string($libraryId) || $libraryId === '') {
                throw new PlatformException('discipline_library_missing', 'This discipline has no library workspace.', 409);
            }
            $students = $this->database->prepare('SELECT user_id FROM iam_student_profiles WHERE discipline_id = :id');
            $students->execute(['id' => $disciplineId]);
            $count = 0;
            foreach ($students->fetchAll(PDO::FETCH_COLUMN) as $userId) {
                $this->enrol((string) $userId, $libraryId);
                ++$count;
            }

            return $count;
        });
    }

    /** Membership plus the 'student' role -- which carries exam.take -- at the library's scope. */
    private function enrol(string $userId, string $workspaceId): void
    {
        $this->database->prepare(<<<'SQL'
INSERT INTO tenant_workspace_memberships (id, workspace_id, user_id, status, joined_at, version, created_at, updated_at)
SELECT :id, :workspace, :user, 'active', UTC_TIMESTAMP(6), 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM tenant_workspace_memberships existing
    WHERE existing.workspace_id = :workspace_check AND existing.user_id = :user_check
)
SQL)->execute([
            'id' => Uuid::v7(),
            'workspace' => $workspaceId,
            'user' => $userId,
            'workspace_check' => $workspaceId,
            'user_check' => $userId,
        ]);

        $scope = $this->database->prepare("SELECT id FROM rbac_scopes WHERE scope_type = 'workspace' AND entity_id = :workspace");
        $scope->execute(['workspace' => $workspaceId]);
        $scopeId = $scope->fetchColumn();
        $role = $this->database->prepare("SELECT id FROM rbac_role_templates WHERE role_key = 'student' AND status = 'active'");
        $role->execute();
        $roleId = $role->fetchColumn();
        if ($scopeId === false || $roleId === false) {
            throw new PlatformException('library_scope_missing', 'The discipline library is not fully provisioned.', 500);
        }

        $existing = $this->database->prepare('SELECT id FROM rbac_role_assignments WHERE user_id = :user AND role_template_id = :role AND scope_id = :scope AND revoked_at IS NULL');
        $existing->execute(['user' => $userId, 'role' => $roleId, 'scope' => $scopeId]);
        if ($existing->fetchColumn() !== false) {
            return;
        }
        $this->database->prepare(<<<'SQL'
INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at)
VALUES (:id, :user, :role, :scope, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL)->execute(['id' => Uuid::v7(), 'user' => $userId, 'role' => $roleId, 'scope' => $scopeId]);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{username:string,password:string,first_name:string,last_name:string,discipline_id:string,institution_id:?string,entry_year:?int,entry_term:?string,course_type:?string,student_number:?string}
     */
    private function validate(array $input): array
    {
        $username = TextNormalizer::normalize((string) ($input['username'] ?? ''));
        if (preg_match(self::USERNAME_PATTERN, $username) !== 1) {
            throw new PlatformException('username_invalid', 'Username must be 3-32 English letters, digits, dot or underscore, starting with a letter.', 422);
        }
        $password = (string) ($input['password'] ?? '');
        if (mb_strlen($password) < 8 || mb_strlen($password) > 128) {
            throw new PlatformException('password_invalid', 'Password must be between 8 and 128 characters.', 422);
        }

        $first = self::cleanName($input['first_name'] ?? '');
        $last = self::cleanName($input['last_name'] ?? '');
        if ($first === '' || $last === '') {
            throw new PlatformException('name_required', 'First and last name are required.', 422);
        }

        $disciplineId = trim((string) ($input['discipline_id'] ?? ''));
        if (preg_match('/^[0-9a-f-]{36}$/', $disciplineId) !== 1) {
            throw new PlatformException('discipline_required', 'A field of study is required.', 422);
        }
        $institutionId = trim((string) ($input['institution_id'] ?? ''));
        if ($institutionId !== '' && preg_match('/^[0-9a-f-]{36}$/', $institutionId) !== 1) {
            throw new PlatformException('institution_invalid', 'University is invalid.', 422);
        }

        $year = TextNormalizer::normalize((string) ($input['entry_year'] ?? ''));
        $entryYear = null;
        if ($year !== '') {
            if (preg_match('/^\d{4}$/', $year) !== 1 || (int) $year < self::MIN_ENTRY_YEAR || (int) $year > self::MAX_ENTRY_YEAR) {
                throw new PlatformException('entry_year_invalid', 'Entry year is invalid.', 422);
            }
            $entryYear = (int) $year;
        }

        $term = trim((string) ($input['entry_term'] ?? ''));
        if ($term !== '' && !in_array($term, self::ENTRY_TERMS, true)) {
            throw new PlatformException('entry_term_invalid', 'Entry term is invalid.', 422);
        }
        $course = trim((string) ($input['course_type'] ?? ''));
        if ($course !== '' && !in_array($course, self::COURSE_TYPES, true)) {
            throw new PlatformException('course_type_invalid', 'Course type is invalid.', 422);
        }
        $number = TextNormalizer::normalize((string) ($input['student_number'] ?? ''));
        if ($number !== '' && preg_match('/^[0-9]{4,20}$/', $number) !== 1) {
            throw new PlatformException('student_number_invalid', 'Student number must be digits only.', 422);
        }

        return [
            'username' => $username,
            'password' => $password,
            'first_name' => $first,
            'last_name' => $last,
            'discipline_id' => $disciplineId,
            'institution_id' => $institutionId === '' ? null : $institutionId,
            'entry_year' => $entryYear,
            'entry_term' => $term === '' ? null : $term,
            'course_type' => $course === '' ? null : $course,
            'student_number' => $number === '' ? null : $number,
        ];
    }

    private static function cleanName(mixed $value): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
        // Letters, spaces, ZWNJ and the few marks names really use.
        if ($name === '' || mb_strlen($name) > 80 || preg_match('/^[\p{L}\p{M}\x{200C} .\'-]+$/u', $name) !== 1) {
            return '';
        }

        return $name;
    }

    /** One row per source; written on every attempt so a refusal cannot be rolled back with the transaction. */
    private function consumeRegistrationSlot(string $sourceDigest): bool
    {
        $read = $this->database->prepare('SELECT window_started_at, registration_count FROM iam_registration_rate_guards WHERE source_digest = :source FOR UPDATE');
        $read->bindValue(':source', $sourceDigest, PDO::PARAM_LOB);
        $read->execute();
        $row = $read->fetch();

        $now = time();
        $windowStart = $row === false ? $now : (int) strtotime((string) $row['window_started_at'] . ' UTC');
        $count = $row === false ? 0 : (int) $row['registration_count'];
        if ($now - $windowStart >= self::WINDOW_SECONDS) {
            $windowStart = $now;
            $count = 0;
        }
        if ($count >= self::MAX_PER_WINDOW) {
            return false;
        }

        $write = $this->database->prepare(<<<'SQL'
INSERT INTO iam_registration_rate_guards (source_digest, window_started_at, registration_count, updated_at)
VALUES (:source, :window, :count, UTC_TIMESTAMP(6))
ON DUPLICATE KEY UPDATE window_started_at = :window_update, registration_count = :count_update, updated_at = UTC_TIMESTAMP(6)
SQL);
        $write->bindValue(':source', $sourceDigest, PDO::PARAM_LOB);
        $write->bindValue(':window', gmdate('Y-m-d H:i:s', $windowStart));
        $write->bindValue(':count', $count + 1, PDO::PARAM_INT);
        $write->bindValue(':window_update', gmdate('Y-m-d H:i:s', $windowStart));
        $write->bindValue(':count_update', $count + 1, PDO::PARAM_INT);
        $write->execute();

        return true;
    }
}
