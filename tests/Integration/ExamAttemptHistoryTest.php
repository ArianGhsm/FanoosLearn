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
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;

/**
 * تاریخچه‌ی تلاش‌ها (docs/product/01_FRONT_DOOR.md exam runner work, Part 3):
 * a student's own past scored attempts at one assessment, newest first, and
 * nothing else -- not an in-progress attempt, not another assessment's
 * attempts, not another student's.
 */
final class ExamAttemptHistoryTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $this->assertHistoryListsOwnScoredAttemptsNewestFirst();
        $this->assertInProgressAttemptIsExcluded();
        $this->assertAnotherStudentsAttemptsDoNotLeak();
        $this->assertAnotherAssessmentsAttemptsDoNotLeak();

        return $this->assertions;
    }

    private function assertHistoryListsOwnScoredAttemptsNewestFirst(): void
    {
        $suffix = $this->suffix();
        $workspace = $this->workspace('history-' . $suffix);
        $exams = $this->exams();
        $assessmentId = $this->assessment($workspace, 'History-' . $suffix, [
            ['id' => 'q1', 'prompt' => 'دو بعلاوه دو؟', 'choices' => ['سه', 'چهار'], 'answer' => 1],
            ['id' => 'q2', 'prompt' => 'اول را انتخاب کن.', 'choices' => ['اول', 'دوم'], 'answer' => 0],
        ], maxAttempts: 5);

        $first = $exams->startAttempt($workspace['student'], $workspace['workspace'], $assessmentId, 'assessment');
        $exams->submitAttempt($workspace['student'], $workspace['workspace'], $first['attempt_id'], 1, ['q1' => 0, 'q2' => 0]);
        $second = $exams->startAttempt($workspace['student'], $workspace['workspace'], $assessmentId, 'practice');
        $exams->submitAttempt($workspace['student'], $workspace['workspace'], $second['attempt_id'], 1, ['q1' => 1, 'q2' => 0]);

        $history = $exams->attemptHistory($workspace['student'], $workspace['workspace'], $assessmentId);
        $this->assert(count($history) === 2, 'History did not contain both scored attempts.');
        // Newest first: the second (practice, 2/2) attempt comes before the
        // first (assessment, 1/2) one.
        $this->assert($history[0]['attempt_id'] === $second['attempt_id'], 'History was not ordered newest first.');
        $this->assert($history[0]['mode'] === 'practice', 'History did not carry the attempt mode through.');
        $this->assert($history[0]['correct_count'] === 2 && $history[0]['question_count'] === 2, 'History did not carry the correct score through.');
        $this->assert($history[0]['submitted_at'] !== null, 'History did not carry a submission date through.');
        $this->assert($history[1]['attempt_id'] === $first['attempt_id']);
        $this->assert($history[1]['correct_count'] === 1, 'History did not score the first attempt correctly.');
    }

    private function assertInProgressAttemptIsExcluded(): void
    {
        $suffix = $this->suffix();
        $workspace = $this->workspace('inprogress-' . $suffix);
        $exams = $this->exams();
        $assessmentId = $this->assessment($workspace, 'InProgress-' . $suffix, [
            ['id' => 'q1', 'prompt' => 'دو بعلاوه دو؟', 'choices' => ['سه', 'چهار'], 'answer' => 1],
        ]);
        $attempt = $exams->startAttempt($workspace['student'], $workspace['workspace'], $assessmentId);
        $exams->saveProgress($workspace['student'], $workspace['workspace'], $attempt['attempt_id'], 1, ['q1' => 0]);

        $history = $exams->attemptHistory($workspace['student'], $workspace['workspace'], $assessmentId);
        $this->assert($history === [], 'An in-progress attempt appeared in the scored-attempt history.');
    }

    private function assertAnotherStudentsAttemptsDoNotLeak(): void
    {
        $suffix = $this->suffix();
        $workspace = $this->workspace('cross-student-' . $suffix);
        $otherStudent = $this->assignRole($workspace['workspace'], 'Other History Student ' . $suffix, 'student');
        $exams = $this->exams();
        $assessmentId = $this->assessment($workspace, 'CrossStudent-' . $suffix, [
            ['id' => 'q1', 'prompt' => 'دو بعلاوه دو؟', 'choices' => ['سه', 'چهار'], 'answer' => 1],
        ]);
        $attempt = $exams->startAttempt($otherStudent, $workspace['workspace'], $assessmentId);
        $exams->submitAttempt($otherStudent, $workspace['workspace'], $attempt['attempt_id'], 1, ['q1' => 1]);

        $history = $exams->attemptHistory($workspace['student'], $workspace['workspace'], $assessmentId);
        $this->assert($history === [], "Another student's attempt leaked into this student's history.");
    }

    private function assertAnotherAssessmentsAttemptsDoNotLeak(): void
    {
        $suffix = $this->suffix();
        $workspace = $this->workspace('cross-assessment-' . $suffix);
        $exams = $this->exams();
        $assessmentA = $this->assessment($workspace, 'A-' . $suffix, [
            ['id' => 'q1', 'prompt' => 'دو بعلاوه دو؟', 'choices' => ['سه', 'چهار'], 'answer' => 1],
        ]);
        $assessmentB = $this->assessment($workspace, 'B-' . $suffix, [
            ['id' => 'q1', 'prompt' => 'سه بعلاوه سه؟', 'choices' => ['پنج', 'شش'], 'answer' => 1],
        ]);
        $attemptA = $exams->startAttempt($workspace['student'], $workspace['workspace'], $assessmentA);
        $exams->submitAttempt($workspace['student'], $workspace['workspace'], $attemptA['attempt_id'], 1, ['q1' => 1]);

        $historyForB = $exams->attemptHistory($workspace['student'], $workspace['workspace'], $assessmentB);
        $this->assert($historyForB === [], "Assessment A's attempt leaked into assessment B's history.");
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

    /** @return array{owner:string,workspace:string,manager:string,reviewer:string,student:string} */
    private function workspace(string $suffix): array
    {
        $owner = $this->platformSuperAdmin('History Owner ' . $suffix);
        $class = (new ClassProvisioningService($this->database, $this->accessGate(), new AuditLogger($this->database)))->createClass($owner, [
            'country' => ['code' => 'ZZ', 'name' => 'Fixture Country'],
            'province' => ['name' => 'Fixture Province ' . $suffix],
            'city' => ['name' => 'Fixture City ' . $suffix],
            'institution' => ['name' => 'Fixture University ' . $suffix],
            'faculty' => ['name' => 'Fixture Faculty ' . $suffix],
            'program' => ['name' => 'Fixture Program ' . $suffix, 'degree_level' => 'professional-doctorate'],
            'cohort' => ['entry_year' => 5900, 'label' => 'Fixture Cohort ' . $suffix],
            'workspace' => ['name' => 'Fixture History Class ' . $suffix],
        ]);
        $workspaceId = $class['workspace_id'];

        return [
            'owner' => $owner,
            'workspace' => $workspaceId,
            'manager' => $this->assignRole($workspaceId, 'History Manager ' . $suffix, 'content-manager'),
            'reviewer' => $this->assignRole($workspaceId, 'History Reviewer ' . $suffix, 'content-reviewer'),
            'student' => $this->assignRole($workspaceId, 'History Student ' . $suffix, 'student'),
        ];
    }

    /**
     * @param array<string, mixed> $workspace
     * @param list<array<string, mixed>> $questions
     */
    private function assessment(array $workspace, string $title, array $questions, int $maxAttempts = 10): string
    {
        $exams = $this->exams();
        $assessment = $exams->createAssessment($workspace['manager'], $workspace['workspace'], 'آزمون ' . $title, ['questions' => $questions], ['max_attempts' => $maxAttempts]);
        $exams->submitForReview($workspace['manager'], $workspace['workspace'], $assessment['assessment_id'], $assessment['version_id']);
        $exams->reviewVersion($workspace['reviewer'], $workspace['workspace'], $assessment['assessment_id'], $assessment['version_id'], 'approved');
        $exams->publishVersion($workspace['manager'], $workspace['workspace'], $assessment['assessment_id'], $assessment['version_id']);

        return $assessment['assessment_id'];
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

    private function assert(bool $condition, string $message = ''): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message !== '' ? $message : 'Assertion failed.');
        }
    }
}
