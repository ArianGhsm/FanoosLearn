<?php

declare(strict_types=1);

namespace Fanoos\Tests\Integration;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Content\ExamQuestionRateGuard;
use Fanoos\Platform\Content\ExamService;
use Fanoos\Platform\Core\ClassProvisioningService;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;

/**
 * آزمون زمان‌دار (docs/product/01_FRONT_DOOR.md exam runner work, Part 3): a
 * time limit on the assessment produces a deadline fixed on the attempt at
 * start, never moved by resuming, and enforced server-side -- a browser
 * timer only mirrors what is checked here. A test that only checks the
 * client timer would prove nothing; this one drives ExamService directly
 * with a controlled `$now`, past and before the deadline.
 */
final class ExamTimedAttemptTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $this->assertDeadlineIsFixedAtStartAndSurvivesResume();
        $this->assertSaveProgressIsRefusedAfterTheDeadline();
        $this->assertLateSubmissionIsRefusedAndClosesWithLastSavedAnswers();
        $this->assertOnTimeSubmissionIsUnaffected();
        $this->assertAnUntimedAssessmentNeverExpires();

        return $this->assertions;
    }

    private function assertDeadlineIsFixedAtStartAndSurvivesResume(): void
    {
        $fixture = $this->fixture('fixed-' . $this->suffix(), 10);
        $exams = $this->exams();

        $started = $exams->startAttempt($fixture['student'], $fixture['workspace'], $fixture['assessment_id']);
        $this->assert($started['deadline_at'] !== null, 'A timed assessment did not produce a deadline.');
        $deadline = strtotime((string) $started['deadline_at']);
        $this->assert($deadline !== false, 'The returned deadline is not a parseable timestamp.');
        // A generous tolerance: this compares PHP's clock to the database
        // server's UTC_TIMESTAMP(6), which the deadline is actually computed
        // from, so it only needs to catch a real bug (wrong unit, wrong
        // sign), not any clock/latency skew between the two.
        $this->assert(abs($deadline - (time() + 600)) < 30, 'The deadline was not ~10 minutes from the attempt start.');

        // Resuming must return the exact same deadline, not a freshly
        // computed one -- the same rule as mode being fixed at start.
        $resumed = $exams->startAttempt($fixture['student'], $fixture['workspace'], $fixture['assessment_id']);
        $this->assert($resumed['resumed'] === true, 'Expected the attempt to resume.');
        $this->assert($resumed['deadline_at'] === $started['deadline_at'], 'Resuming recomputed the deadline instead of keeping the one fixed at start.');
    }

    private function assertSaveProgressIsRefusedAfterTheDeadline(): void
    {
        $fixture = $this->fixture('save-' . $this->suffix(), 10);
        $exams = $this->exams();
        // Must be real wall-clock time, not an arbitrary epoch: the deadline
        // itself is computed from the database server's own UTC_TIMESTAMP(6)
        // at attempt start (see startAttempt()'s comment on why), so $now
        // has to land in the same clock to be "before" or "after" it.
        $now = time();
        $started = $exams->startAttempt($fixture['student'], $fixture['workspace'], $fixture['assessment_id']);

        // Comfortably inside the window: an ordinary save works.
        $saved = $exams->saveProgress($fixture['student'], $fixture['workspace'], $started['attempt_id'], 1, ['q1' => 0], $now + 60);
        $this->assert($saved['revision'] === 2, 'An on-time save inside the deadline was unexpectedly refused.');

        // 10 minutes = 600 seconds after start; well past it now.
        $this->expectCode('attempt_deadline_passed', fn () => $exams->saveProgress(
            $fixture['student'], $fixture['workspace'], $started['attempt_id'], 2, ['q1' => 1], $now + 700,
        ));

        // The refusal must not have silently written the late answer. Decode
        // rather than substring-match: exam_attempts.answers_json is a
        // native MySQL JSON column, which re-serializes on storage (a space
        // after ":", for one) -- comparing raw text against the exact bytes
        // this code happened to json_encode would be testing MySQL's
        // formatting, not the refusal.
        $answers = $this->database->prepare('SELECT answers_json, revision FROM exam_attempts WHERE id = :attempt');
        $answers->execute(['attempt' => $started['attempt_id']]);
        $row = $answers->fetch();
        $this->assert((int) $row['revision'] === 2, 'A refused late save still advanced the revision.');
        $storedAnswers = json_decode((string) $row['answers_json'], true, 16, JSON_THROW_ON_ERROR);
        $this->assert(($storedAnswers['q1'] ?? null) === 0, 'A refused late save overwrote the last legitimately saved answer.');
    }

    private function assertLateSubmissionIsRefusedAndClosesWithLastSavedAnswers(): void
    {
        $fixture = $this->fixture('submit-' . $this->suffix(), 10);
        $exams = $this->exams();
        $now = time();
        $started = $exams->startAttempt($fixture['student'], $fixture['workspace'], $fixture['assessment_id']);

        // The student answers both questions correctly before time runs out...
        $exams->saveProgress($fixture['student'], $fixture['workspace'], $started['attempt_id'], 1, ['q1' => 1, 'q2' => 0], $now + 30);

        // ...but the submit request itself arrives after the deadline (a slow
        // network, a laptop closed and reopened -- the scenario this exists
        // for). It must be refused, not silently scored as if it were on time.
        $this->expectCode('attempt_deadline_passed', fn () => $exams->submitAttempt(
            $fixture['student'], $fixture['workspace'], $started['attempt_id'], 2,
            // A late attempt to change an answer on the way out -- must be
            // ignored entirely, not scored.
            ['q1' => 0, 'q2' => 1], $now + 700,
        ));

        // Yet the attempt must not be stuck forever: it was closed and
        // scored using the answers legitimately saved before time ran out.
        $result = $this->database->prepare('SELECT attempt.status, result.correct_count, result.question_count FROM exam_attempts attempt JOIN exam_attempt_results result ON result.attempt_id = attempt.id WHERE attempt.id = :attempt');
        $result->execute(['attempt' => $started['attempt_id']]);
        $row = $result->fetch();
        $this->assert($row !== false, 'The expired attempt was never closed at all.');
        $this->assert($row['status'] === 'scored', 'The expired attempt was not closed.');
        $this->assert((int) $row['correct_count'] === 2, 'The expired attempt was not scored from the last legitimately saved answers.');
    }

    private function assertOnTimeSubmissionIsUnaffected(): void
    {
        $fixture = $this->fixture('ontime-' . $this->suffix(), 30);
        $exams = $this->exams();
        $now = time();
        $started = $exams->startAttempt($fixture['student'], $fixture['workspace'], $fixture['assessment_id']);

        $submitted = $exams->submitAttempt($fixture['student'], $fixture['workspace'], $started['attempt_id'], 1, ['q1' => 1, 'q2' => 0], $now + 5);
        $this->assert($submitted['status'] === 'scored', 'An on-time submission within the time limit was refused.');
        $this->assert($submitted['correct_count'] === 2, 'An on-time submission did not score the submitted answers.');
    }

    private function assertAnUntimedAssessmentNeverExpires(): void
    {
        $fixture = $this->fixture('untimed-' . $this->suffix(), null);
        $exams = $this->exams();
        $started = $exams->startAttempt($fixture['student'], $fixture['workspace'], $fixture['assessment_id']);
        $this->assert($started['deadline_at'] === null, 'An assessment with no time limit produced a deadline.');

        // Far in the future: an untimed attempt is never refused.
        $submitted = $exams->submitAttempt($fixture['student'], $fixture['workspace'], $started['attempt_id'], 1, ['q1' => 1], 2_000_000_000);
        $this->assert($submitted['status'] === 'scored', 'An untimed attempt was refused as if it had a deadline.');
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

    private function exams(): ExamService
    {
        $audit = new AuditLogger($this->database);
        $authorizer = new ScopeAuthorizer($this->database);
        $access = new AccessGate($this->database, $authorizer);
        $entitlements = new EntitlementService($this->database, $access, $audit);

        return new ExamService($this->database, $access, $authorizer, $entitlements, $audit, new ExamQuestionRateGuard($this->database, 1000.0, 1.0));
    }

    /** @return array<string, mixed> */
    private function fixture(string $suffix, ?int $timeLimitMinutes): array
    {
        $owner = $this->platformSuperAdmin('Timed Owner ' . $suffix);
        $class = (new ClassProvisioningService($this->database, $this->accessGate(), new AuditLogger($this->database)))->createClass($owner, [
            'country' => ['code' => 'ZZ', 'name' => 'Fixture Country'],
            'province' => ['name' => 'Fixture Province ' . $suffix],
            'city' => ['name' => 'Fixture City ' . $suffix],
            'institution' => ['name' => 'Fixture University ' . $suffix],
            'faculty' => ['name' => 'Fixture Faculty ' . $suffix],
            'program' => ['name' => 'Fixture Program ' . $suffix, 'degree_level' => 'professional-doctorate'],
            'cohort' => ['entry_year' => 5900, 'label' => 'Fixture Cohort ' . $suffix],
            'workspace' => ['name' => 'Fixture Timed Class ' . $suffix],
        ]);
        $workspace = $class['workspace_id'];
        $manager = $this->assignRole($workspace, 'Timed Manager ' . $suffix, 'content-manager');
        $reviewer = $this->assignRole($workspace, 'Timed Reviewer ' . $suffix, 'content-reviewer');
        $student = $this->assignRole($workspace, 'Timed Student ' . $suffix, 'student');

        $manage = $this->exams();
        $definition = ['questions' => [
            ['id' => 'q1', 'prompt' => 'دو بعلاوه دو؟', 'choices' => ['سه', 'چهار'], 'answer' => 1],
            ['id' => 'q2', 'prompt' => 'اولین گزینه را انتخاب کن.', 'choices' => ['اول', 'دوم'], 'answer' => 0],
        ]];
        $metadata = ['max_attempts' => 20];
        if ($timeLimitMinutes !== null) {
            $metadata['time_limit_minutes'] = $timeLimitMinutes;
        }
        $assessment = $manage->createAssessment($manager, $workspace, 'آزمون زمان‌دار ' . $suffix, $definition, $metadata);
        $manage->submitForReview($manager, $workspace, $assessment['assessment_id'], $assessment['version_id']);
        $manage->reviewVersion($reviewer, $workspace, $assessment['assessment_id'], $assessment['version_id'], 'approved');
        $manage->publishVersion($manager, $workspace, $assessment['assessment_id'], $assessment['version_id']);

        return ['owner' => $owner, 'workspace' => $workspace, 'student' => $student, 'assessment_id' => $assessment['assessment_id']];
    }

    private function assignRole(string $workspaceId, string $displayName, string $roleKey): string
    {
        $userId = Uuid::v7();
        $this->database->prepare("INSERT INTO iam_users (id, display_name, status, locale, created_at, updated_at) VALUES (:id, :name, 'active', 'fa-IR', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => $userId, 'name' => $displayName]);
        $this->database->prepare("INSERT INTO tenant_workspace_memberships (id, workspace_id, user_id, status, joined_at, created_at, updated_at) VALUES (:id, :workspace, :user, 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => Uuid::v7(), 'workspace' => $workspaceId, 'user' => $userId]);
        $scope = $this->workspaceScopeId($workspaceId);
        $this->database->prepare(<<<'SQL'
INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at)
SELECT :id, :user, role.id, :scope, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
FROM rbac_role_templates role WHERE role.role_key = :role
SQL)->execute(['id' => Uuid::v7(), 'user' => $userId, 'scope' => $scope, 'role' => $roleKey]);

        return $userId;
    }

    private function workspaceScopeId(string $workspaceId): string
    {
        $scope = $this->database->prepare("SELECT id FROM rbac_scopes WHERE scope_type = 'workspace' AND entity_id = :entity_workspace AND workspace_id = :workspace");
        $scope->execute(['entity_workspace' => $workspaceId, 'workspace' => $workspaceId]);
        $id = $scope->fetchColumn();
        if ($id === false) {
            throw new RuntimeException('Fixture workspace scope was not found.');
        }

        return (string) $id;
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

    private function expectCode(string $code, callable $operation): void
    {
        try {
            $operation();
        } catch (PlatformException $error) {
            $this->assert($error->errorCode === $code, "Expected {$code}, received {$error->errorCode}.");

            return;
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
