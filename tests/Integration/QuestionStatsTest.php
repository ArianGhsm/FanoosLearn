<?php

declare(strict_types=1);

namespace Fanoos\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Content\CustomPracticeService;
use Fanoos\Platform\Content\ExamQuestionRateGuard;
use Fanoos\Platform\Content\ExamService;
use Fanoos\Platform\Content\ProgressService;
use Fanoos\Platform\Content\QuestionStatsRecorder;
use Fanoos\Platform\Core\ClassProvisioningService;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;

/**
 * آمار هر سؤال و داشبورد پیشرفت: the counters kept at scoring, what the
 * runner shows from them, and the progress read built on them.
 */
final class QuestionStatsTest
{
    private int $assertions = 0;
    private array $fixture = [];

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $this->streakArithmetic();

        $this->fixture = $this->fixture(substr(str_replace('-', '', Uuid::v7()), -10));
        $f = $this->fixture;
        $exams = $this->exams();
        $ws = $f['workspace'];

        $a = $this->publish($f['course'], 'A', [
            ['id' => 'q1', 'prompt' => 'یک؟', 'choices' => ['درست', 'غلط', 'غلط'], 'answer' => 0, 'topic' => 'قلب'],
            ['id' => 'q2', 'prompt' => 'دو؟', 'choices' => ['غلط', 'درست'], 'answer' => 1, 'topic' => 'ریه'],
            ['id' => 'q3', 'prompt' => 'سه؟', 'choices' => ['درست', 'غلط'], 'answer' => 0, 'topic' => 'قلب'],
        ]);
        $kidney = [];
        for ($i = 1; $i <= 5; $i++) {
            $kidney[] = ['id' => 'k' . $i, 'prompt' => "کلیه {$i}؟", 'choices' => ['درست', 'غلط'], 'answer' => 0, 'topic' => 'کلیه'];
        }
        $b = $this->publish($f['course2'], 'B', $kidney);

        // Three students sit A. q1: right, right, wrong. q2: wrong, wrong, blank.
        $this->sit($f['s1'], $a, ['q1' => 0, 'q2' => 0]);
        $this->sit($f['s2'], $a, ['q1' => 0, 'q2' => 0]);
        $this->sit($f['s3'], $a, ['q1' => 1]);

        // A fourth student, mid-exam, sees how the others did -- never their own zero as a claim.
        $attempt = $exams->startAttempt($f['s4'], $ws, $a);
        $read = $this->readById($f['s4'], $attempt['attempt_id'], 'q1', 3);
        $stats = $read['question']['stats'];
        $this->assert($stats['peer_answered'] === 3 && $stats['peer_correct_percent'] === 67, 'Peer share of q1 is wrong: ' . json_encode($stats));
        $this->assert($stats['answered'] === 0 && $stats['last_correct'] === null && $stats['last_answered_at'] === null, 'A student who never answered got a record.');
        $this->assert(!array_key_exists('answer', $read['question']), 'Stats must not carry the answer.');
        $this->assert(!array_key_exists('bank', $read['question']), 'An authored question claimed bank facts.');
        $q2 = $this->readById($f['s4'], $attempt['attempt_id'], 'q2', 3)['question']['stats'];
        $this->assert($q2['peer_answered'] === null && $q2['peer_correct_percent'] === null, 'A blank was counted, or a share was shown below the minimum.');

        // q1 answered wrong with choice 1 by the fourth: now 2 x choice 0, 2 x choice 1.
        $exams->submitAttempt($f['s4'], $ws, $attempt['attempt_id'], 1, ['q1' => 1]);
        $review = $this->reviewById($f['s4'], $attempt['attempt_id'], 'q1', 3);
        $this->assert($review['choice_shares'] === ['answered' => 4, 'percent' => [50, 50, 0]], 'Choice shares are wrong: ' . json_encode($review['choice_shares']));
        $this->assert($review['stats']['answered'] === 1 && $review['stats']['correct'] === 0 && $review['stats']['last_correct'] === false, 'The review does not carry the student\'s own record.');

        // Other people exclude oneself: s1 sees three others on q1, two of them wrong.
        $own = (new QuestionStatsRecorder($this->database))->forQuestion($ws, $f['s1'], 'q1');
        $this->assert($own['peer_answered'] === 3 && $own['peer_correct_percent'] === 33 && $own['answered'] === 1 && $own['last_correct'] === true, 'Own answers leaked into the peer share: ' . json_encode($own));

        // Learning mode: a question whose answer was revealed first is not counted.
        $learning = $exams->startAttempt($f['s1'], $ws, $a, 'learning');
        $q2Position = $this->positionOf($f['s1'], $learning['attempt_id'], 'q2', 3);
        $exams->revealQuestion($f['s1'], $ws, $learning['attempt_id'], $q2Position);
        $exams->submitAttempt($f['s1'], $ws, $learning['attempt_id'], 1, ['q1' => 0, 'q2' => 1]);
        $own = (new QuestionStatsRecorder($this->database))->forQuestion($ws, $f['s1'], 'q2');
        $this->assert($own['answered'] === 1 && $own['last_correct'] === false, 'A revealed answer was counted as the student\'s own.');
        $own = (new QuestionStatsRecorder($this->database))->forQuestion($ws, $f['s1'], 'q1');
        $this->assert($own['answered'] === 2 && $own['correct'] === 2, 'An unrevealed answer in learning mode was not counted.');

        // پاسخ سفید: s3 left q2 blank -- counted as a blank, never as an answer.
        $own = (new QuestionStatsRecorder($this->database))->forQuestion($ws, $f['s3'], 'q2');
        $this->assert($own['blank'] === 1 && $own['answered'] === 0 && $own['last_correct'] === null, 'A blank was not counted on its own: ' . json_encode($own));
        $this->assert((new QuestionStatsRecorder($this->database))->forQuestion($ws, $f['s1'], 'q2')['blank'] === 0, 'An answered question was counted as blank.');

        // A weak topic needs five answers: s2 gets all of B wrong.
        $this->sit($f['s2'], $b, ['k1' => 1, 'k2' => 1, 'k3' => 1, 'k4' => 1, 'k5' => 1]);

        // A custom exam files each question under the course it came from.
        $custom = (new CustomPracticeService($this->database, $this->access(), $this->entitlements(), new AuditLogger($this->database)))
            ->create($f['s3'], $ws, ['course_ids' => [$f['course2']], 'count' => 5, 'source' => 'all']);
        $this->sit($f['s3'], $custom['assessment_id'], ['k1' => 0, 'k2' => 0]);
        $course = $this->database->prepare("SELECT course_id FROM exam_question_user_stats WHERE workspace_id = :workspace AND user_id = :user AND question_key = 'k1'");
        $course->execute(['workspace' => $ws, 'user' => $f['s3']]);
        $this->assert($course->fetchColumn() === $f['course2'], 'A custom exam answer was not filed under its source course.');

        // The counters are derived: a rebuild from the attempts gives the same numbers.
        $before = $this->snapshot();
        (new QuestionStatsRecorder($this->database))->rebuild($ws);
        $this->assert($this->snapshot() === $before, 'Rebuilding the counters from the attempts changed them.');

        $this->progressRead();

        return $this->assertions;
    }

    private function progressRead(): void
    {
        $f = $this->fixture;
        $progress = new ProgressService($this->database, $this->access());

        $s1 = $progress->progress($f['s1'], $f['workspace']);
        $this->assert($s1['totals']['attempts'] === 2 && $s1['totals']['answers'] === 4, 'Attempt/answer totals are wrong: ' . json_encode($s1['totals']));
        $this->assert($s1['totals']['correct_percent'] === 75, 'Correct share is wrong: ' . json_encode($s1['totals']));
        $this->assert($s1['totals']['questions_seen'] === 2 && $s1['totals']['questions_mastered'] === 1, 'Seen/mastered are wrong: ' . json_encode($s1['totals']));
        $this->assert($s1['streak']['current'] === 1 && $s1['streak']['today'] === true, 'Today\'s study did not start a streak.');
        $this->assert(count($s1['activity']) === ProgressService::ACTIVITY_DAYS && end($s1['activity'])['answered'] === 4, 'Today is missing from the activity grid.');
        $this->assert(count($s1['recent']) === 2 && $s1['recent'][0]['mode'] === 'learning', 'Recent attempts are not newest first.');
        $this->assert(count($s1['courses']) === 1 && $s1['courses'][0]['course_id'] === $f['course']
            && $s1['courses'][0]['questions_seen'] === 2 && $s1['courses'][0]['questions_total'] === 3, 'Course row is wrong: ' . json_encode($s1['courses']));

        $s2 = $progress->progress($f['s2'], $f['workspace']);
        $this->assert(array_column($s2['weak_topics'], 'topic') === ['کلیه'] && $s2['weak_topics'][0]['correct_percent'] === 0, 'Weak topics are wrong: ' . json_encode($s2['weak_topics']));

        $nobody = $progress->progress($f['s5'], $f['workspace']);
        $this->assert($nobody['totals']['attempts'] === 0 && $nobody['totals']['correct_percent'] === null && $nobody['courses'] === [], 'A student with nothing done got numbers.');
    }

    private function streakArithmetic(): void
    {
        $tz = new DateTimeZone('Asia/Tehran');
        $today = new DateTimeImmutable('2026-09-28', $tz);
        $this->assert(ProgressService::streak(['2026-09-26', '2026-09-27'], $today) === ['current' => 2, 'best' => 2, 'today' => false], 'Yesterday must keep a streak alive.');
        $this->assert(ProgressService::streak(['2026-09-20', '2026-09-21', '2026-09-22', '2026-09-28'], $today) === ['current' => 1, 'best' => 3, 'today' => true], 'Streak arithmetic is wrong.');
        $this->assert(ProgressService::streak(['2026-09-25'], $today)['current'] === 0, 'A gap must end a streak.');
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function snapshot(): array
    {
        $out = [];
        foreach ([
            'exam_question_stats' => 'SELECT question_key, answered_count, correct_count FROM exam_question_stats WHERE workspace_id = :workspace ORDER BY question_key',
            'exam_question_choice_stats' => 'SELECT question_key, choice_index, picked_count FROM exam_question_choice_stats WHERE workspace_id = :workspace ORDER BY question_key, choice_index',
            'exam_question_user_stats' => 'SELECT user_id, question_key, course_id, topic, answered_count, correct_count, last_correct FROM exam_question_user_stats WHERE workspace_id = :workspace ORDER BY user_id, question_key',
            'exam_question_user_blanks' => 'SELECT user_id, question_key, blank_count FROM exam_question_user_blanks WHERE workspace_id = :workspace ORDER BY user_id, question_key',
        ] as $table => $sql) {
            $query = $this->database->prepare($sql);
            $query->execute(['workspace' => $this->fixture['workspace']]);
            $out[$table] = $query->fetchAll(PDO::FETCH_ASSOC);
        }

        return $out;
    }

    /** @param array<string, int> $answers */
    private function sit(string $user, string $assessment, array $answers): void
    {
        $exams = $this->exams();
        $attempt = $exams->startAttempt($user, $this->fixture['workspace'], $assessment);
        $exams->submitAttempt($user, $this->fixture['workspace'], $attempt['attempt_id'], 1, $answers);
    }

    /** @return array<string, mixed> */
    private function readById(string $user, string $attemptId, string $questionId, int $count): array
    {
        return $this->exams()->readQuestion($user, $this->fixture['workspace'], $attemptId, $this->positionOf($user, $attemptId, $questionId, $count));
    }

    private function positionOf(string $user, string $attemptId, string $questionId, int $count): int
    {
        for ($position = 1; $position <= $count; $position++) {
            if ($this->exams()->readQuestion($user, $this->fixture['workspace'], $attemptId, $position)['question']['id'] === $questionId) {
                return $position;
            }
        }
        throw new RuntimeException("Question {$questionId} is not in the attempt.");
    }

    /** @return array<string, mixed> */
    private function reviewById(string $user, string $attemptId, string $questionId, int $count): array
    {
        for ($position = 1; $position <= $count; $position++) {
            $entry = $this->exams()->attemptReviewQuestion($user, $this->fixture['workspace'], $attemptId, $position);
            if ($entry['question_id'] === $questionId) {
                return $entry;
            }
        }
        throw new RuntimeException("Question {$questionId} is not in the review.");
    }

    /** @param list<array<string, mixed>> $questions */
    private function publish(string $course, string $title, array $questions): string
    {
        $f = $this->fixture;
        $exams = $this->exams();
        $created = $exams->createAssessment($f['manager'], $f['workspace'], 'آمار ' . $title, ['questions' => $questions], ['course_id' => $course, 'max_attempts' => 10]);
        $exams->submitForReview($f['manager'], $f['workspace'], $created['assessment_id'], $created['version_id']);
        $exams->reviewVersion($f['reviewer'], $f['workspace'], $created['assessment_id'], $created['version_id'], 'approved');
        $exams->publishVersion($f['manager'], $f['workspace'], $created['assessment_id'], $created['version_id']);

        return $created['assessment_id'];
    }

    /** @return array<string, string> */
    private function fixture(string $suffix): array
    {
        $owner = $this->user('Stats Owner');
        $this->database->prepare("INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at) SELECT :id, :user, id, '00000000-0000-7000-8000-000000000001', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6) FROM rbac_role_templates WHERE role_key = 'platform-super-admin'")
            ->execute(['id' => Uuid::v7(), 'user' => $owner]);
        $workspace = (new ClassProvisioningService($this->database, $this->access(), new AuditLogger($this->database)))->createClass($owner, [
            'country' => ['code' => 'ZZ', 'name' => 'Fixture Country'],
            'province' => ['name' => 'Stats Province ' . $suffix],
            'city' => ['name' => 'Stats City ' . $suffix],
            'institution' => ['name' => 'Stats University ' . $suffix],
            'faculty' => ['name' => 'Stats Faculty ' . $suffix],
            'program' => ['name' => 'Stats Program ' . $suffix, 'degree_level' => 'professional-doctorate'],
            'cohort' => ['entry_year' => 5905, 'label' => 'Stats Cohort ' . $suffix],
            'workspace' => ['name' => 'Stats Class ' . $suffix],
        ])['workspace_id'];

        $fixture = ['workspace' => $workspace];
        foreach (['course' => 'قلب و ریه', 'course2' => 'کلیه'] as $key => $title) {
            $fixture[$key] = Uuid::v7();
            $this->database->prepare("INSERT INTO academic_courses (id, workspace_id, course_code, title, status, version, created_at, updated_at) VALUES (:id, :workspace, :code, :title, 'active', 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
                ->execute(['id' => $fixture[$key], 'workspace' => $workspace, 'code' => $key . '-' . $suffix, 'title' => $title]);
        }
        $fixture['manager'] = $this->member($workspace, 'content-manager');
        $fixture['reviewer'] = $this->member($workspace, 'content-reviewer');
        foreach (['s1', 's2', 's3', 's4', 's5'] as $student) {
            $fixture[$student] = $this->member($workspace, 'student');
        }

        return $fixture;
    }

    private function member(string $workspace, string $roleKey): string
    {
        $user = $this->user('Stats ' . $roleKey);
        $this->database->prepare("INSERT INTO tenant_workspace_memberships (id, workspace_id, user_id, status, joined_at, created_at, updated_at) VALUES (:id, :workspace, :user, 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => Uuid::v7(), 'workspace' => $workspace, 'user' => $user]);
        $this->database->prepare(<<<'SQL'
INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at)
SELECT :id, :user, role.id, scope.id, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
FROM rbac_role_templates role
JOIN rbac_scopes scope ON scope.scope_type = 'workspace' AND scope.entity_id = :workspace AND scope.workspace_id = :workspace_check
WHERE role.role_key = :role
SQL)->execute(['id' => Uuid::v7(), 'user' => $user, 'workspace' => $workspace, 'workspace_check' => $workspace, 'role' => $roleKey]);

        return $user;
    }

    private function user(string $name): string
    {
        $id = Uuid::v7();
        $this->database->prepare("INSERT INTO iam_users (id, display_name, status, locale, created_at, updated_at) VALUES (:id, :name, 'active', 'fa-IR', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => $id, 'name' => $name]);

        return $id;
    }

    private function exams(): ExamService
    {
        return new ExamService($this->database, $this->access(), new ScopeAuthorizer($this->database), $this->entitlements(), new AuditLogger($this->database), new ExamQuestionRateGuard($this->database, 1000.0, 1.0));
    }

    private function entitlements(): EntitlementService
    {
        return new EntitlementService($this->database, $this->access(), new AuditLogger($this->database));
    }

    private function access(): AccessGate
    {
        return new AccessGate($this->database, new ScopeAuthorizer($this->database));
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
