<?php

declare(strict_types=1);

namespace Fanoos\Tests\Integration;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Content\CustomPracticeService;
use Fanoos\Platform\Content\ExamQuestionRateGuard;
use Fanoos\Platform\Content\ExamService;
use Fanoos\Platform\Core\ClassProvisioningService;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;

/**
 * آزمون دلخواه: built only from questions the student may already open,
 * filtered the way they asked, runnable like any exam -- and theirs alone.
 */
final class CustomPracticeTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $suffix = substr(str_replace('-', '', Uuid::v7()), -10);
        $fixture = $this->fixture($suffix);
        $exams = $this->exams();
        $custom = $this->custom();
        [$workspace, $student, $classmate, $course] = [$fixture['workspace'], $fixture['student'], $fixture['classmate'], $fixture['course']];

        // Two free exams (6 cardiology + 4 respiratory questions) and one behind a purchase.
        $free1 = $this->publish($fixture, $course, 'A', array_merge($this->questions('a', 'قلب', 6), $this->questions('b', 'ریه', 2)));
        $this->publish($fixture, $course, 'B', $this->questions('c', 'ریه', 2));
        $this->publish($fixture, $course, 'P', $this->questions('p', 'قلب', 5), true);

        $options = $custom->options($student, $workspace, [$course]);
        $this->assert($options['total'] === 10, 'Questions behind a purchase were offered: ' . $options['total']);
        $this->assert($options['unseen'] === 10 && $options['wrong'] === 0, 'A student with no history has everything unseen.');
        $byTopic = array_column($options['topics'], 'total', 'topic');
        $this->assert(($byTopic['قلب'] ?? 0) === 6 && ($byTopic['ریه'] ?? 0) === 4, 'Topic counts are wrong.');

        // Build, then run it like any exam.
        $built = $custom->create($student, $workspace, ['course_ids' => [$course], 'topics' => ['قلب'], 'count' => 5, 'source' => 'all', 'time_limit_minutes' => 10]);
        $this->assert($built['question_count'] === 5, 'The exam does not have the requested size.');
        $attempt = $exams->startAttempt($student, $workspace, $built['assessment_id']);
        $read = $exams->readQuestion($student, $workspace, $attempt['attempt_id'], 1);
        $this->assert(str_starts_with((string) $read['question']['id'], 'a'), 'A question outside the chosen topic was drawn.');

        // It is theirs: out of every catalogue, invisible and unstartable for anyone else.
        $this->assert(!in_array($built['assessment_id'], array_column($exams->catalog($student, $workspace), 'id'), true), 'A custom exam appeared in the catalogue.');
        $courseRow = array_values(array_filter($exams->catalogCourses($student, $workspace), static fn (array $row): bool => $row['course_id'] === null));
        $this->assert($courseRow === [], 'A custom exam was counted in the course index.');
        $this->assert($exams->catalogEntry($student, $workspace, $built['assessment_id']) !== null, 'The owner cannot open their own custom exam.');
        $this->assert($exams->catalogEntry($classmate, $workspace, $built['assessment_id']) === null, 'A classmate could see a custom exam.');
        $this->expectCode('assessment_not_found', fn () => $exams->startAttempt($classmate, $workspace, $built['assessment_id']));
        $this->assert(array_column($custom->mine($student, $workspace), 'id') === [$built['assessment_id']], 'The owner\'s list is wrong.');
        $this->assert($custom->mine($classmate, $workspace) === [], 'Another student\'s list shows someone else\'s exam.');

        // Answer all five wrong: they become "wrong", the rest stay "unseen".
        $answers = [];
        foreach (json_decode((string) $this->definition($built['assessment_id']), true)['questions'] as $question) {
            $answers[$question['id']] = ($question['answer'] + 1) % count($question['choices']);
        }
        $exams->submitAttempt($student, $workspace, $attempt['attempt_id'], 1, $answers);
        $after = $custom->options($student, $workspace, [$course]);
        $this->assert($after['wrong'] === 5 && $after['unseen'] === 5, 'History did not move questions into wrong/unseen: ' . json_encode([$after['wrong'], $after['unseen']]));
        $retry = $custom->create($student, $workspace, ['course_ids' => [$course], 'count' => 5, 'source' => 'wrong']);
        $retryIds = array_column(json_decode((string) $this->definition($retry['assessment_id']), true)['questions'], 'id');
        sort($retryIds);
        $wrongIds = array_keys($answers);
        sort($wrongIds);
        $this->assert($retryIds === $wrongIds, 'A "wrong only" exam drew something else.');

        $this->expectCode('custom_practice_too_few', fn () => $custom->create($student, $workspace, ['course_ids' => [$course], 'topics' => ['ریه'], 'count' => 5, 'source' => 'wrong']));
        $this->expectCode('custom_practice_count_invalid', fn () => $custom->create($student, $workspace, ['course_ids' => [$course], 'count' => 500, 'source' => 'all']));
        unset($free1);

        return $this->assertions;
    }

    /** @return list<array<string, mixed>> */
    private function questions(string $prefix, string $topic, int $count): array
    {
        $questions = [];
        for ($i = 1; $i <= $count; $i++) {
            $questions[] = ['id' => $prefix . $i . substr(str_replace('-', '', Uuid::v7()), -8), 'prompt' => "سؤال {$prefix}{$i}؟", 'choices' => ['یک', 'دو', 'سه'], 'answer' => 0, 'topic' => $topic];
        }

        return $questions;
    }

    /** @param array<string, string> $fixture @param list<array<string, mixed>> $questions */
    private function publish(array $fixture, string $course, string $title, array $questions, bool $paid = false): string
    {
        $exams = $this->exams();
        $created = $exams->createAssessment($fixture['manager'], $fixture['workspace'], 'دلخواه ' . $title, ['questions' => $questions], [
            'course_id' => $course, 'requires_entitlement' => $paid,
        ]);
        $exams->submitForReview($fixture['manager'], $fixture['workspace'], $created['assessment_id'], $created['version_id']);
        $exams->reviewVersion($fixture['reviewer'], $fixture['workspace'], $created['assessment_id'], $created['version_id'], 'approved');
        $exams->publishVersion($fixture['manager'], $fixture['workspace'], $created['assessment_id'], $created['version_id']);

        return $created['assessment_id'];
    }

    private function definition(string $assessmentId): string
    {
        $query = $this->database->prepare('SELECT version.definition_json FROM exam_assessment_versions version JOIN exam_assessments a ON a.id = version.assessment_id AND a.current_version_no = version.version_no WHERE a.id = :id');
        $query->execute(['id' => $assessmentId]);

        return (string) $query->fetchColumn();
    }

    /** @return array<string, string> */
    private function fixture(string $suffix): array
    {
        $owner = $this->user('Custom Owner');
        $this->database->prepare("INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at) SELECT :id, :user, id, '00000000-0000-7000-8000-000000000001', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6) FROM rbac_role_templates WHERE role_key = 'platform-super-admin'")
            ->execute(['id' => Uuid::v7(), 'user' => $owner]);
        $workspace = (new ClassProvisioningService($this->database, $this->access(), new AuditLogger($this->database)))->createClass($owner, [
            'country' => ['code' => 'ZZ', 'name' => 'Fixture Country'],
            'province' => ['name' => 'Custom Province ' . $suffix],
            'city' => ['name' => 'Custom City ' . $suffix],
            'institution' => ['name' => 'Custom University ' . $suffix],
            'faculty' => ['name' => 'Custom Faculty ' . $suffix],
            'program' => ['name' => 'Custom Program ' . $suffix, 'degree_level' => 'professional-doctorate'],
            'cohort' => ['entry_year' => 5904, 'label' => 'Custom Cohort ' . $suffix],
            'workspace' => ['name' => 'Custom Library ' . $suffix],
        ])['workspace_id'];
        $course = Uuid::v7();
        $this->database->prepare("INSERT INTO academic_courses (id, workspace_id, course_code, title, status, version, created_at, updated_at) VALUES (:id, :workspace, :code, 'قلب و ریه — تست', 'active', 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => $course, 'workspace' => $workspace, 'code' => 'custom-' . $suffix]);

        return [
            'workspace' => $workspace,
            'course' => $course,
            'manager' => $this->member($workspace, 'content-manager'),
            'reviewer' => $this->member($workspace, 'content-reviewer'),
            'student' => $this->member($workspace, 'student'),
            'classmate' => $this->member($workspace, 'student'),
        ];
    }

    private function member(string $workspace, string $roleKey): string
    {
        $user = $this->user('Custom ' . $roleKey);
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
        $audit = new AuditLogger($this->database);

        return new ExamService($this->database, $this->access(), new ScopeAuthorizer($this->database), new EntitlementService($this->database, $this->access(), $audit), $audit, new ExamQuestionRateGuard($this->database, 1000.0, 1.0));
    }

    private function custom(): CustomPracticeService
    {
        $audit = new AuditLogger($this->database);

        return new CustomPracticeService($this->database, $this->access(), new EntitlementService($this->database, $this->access(), $audit), $audit);
    }

    private function access(): AccessGate
    {
        return new AccessGate($this->database, new ScopeAuthorizer($this->database));
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
        throw new RuntimeException("Expected {$code}.");
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
