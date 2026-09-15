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
 * مرور اشتباه‌ها (docs/product/01_FRONT_DOOR.md exam runner work, Part 3):
 * the review must contain exactly the questions this student has answered
 * wrong, in this workspace -- never a question left blank, never a
 * question fixed on a later attempt, never another student's mistake, and
 * never a mistake made in a different workspace.
 */
final class ExamMistakesReviewTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $this->assertReviewContainsExactlyWrongAnsweredQuestions();
        $this->assertRetakingAndFixingAMistakeClearsIt();
        $this->assertLeavingItBlankOnARetakeDoesNotEraseAnOlderMistake();
        $this->assertAnotherStudentsMistakesDoNotLeak();
        $this->assertMistakesFromAnotherWorkspaceDoNotLeak();
        $this->assertAnInProgressAttemptContributesNothing();

        return $this->assertions;
    }

    private function assertReviewContainsExactlyWrongAnsweredQuestions(): void
    {
        $suffix = $this->suffix();
        $workspace = $this->workspace('mixed-' . $suffix);
        $exams = $this->exams();

        // Assessment A: q1 wrong, q2 right.
        $a = $this->assessment($workspace, 'A-' . $suffix, [
            ['id' => 'q1', 'prompt' => 'دو بعلاوه دو؟', 'choices' => ['سه', 'چهار'], 'answer' => 1, 'explanation' => 'چهار است.'],
            ['id' => 'q2', 'prompt' => 'اول را انتخاب کن.', 'choices' => ['اول', 'دوم'], 'answer' => 0],
        ]);
        // Assessment B: q3 wrong, q4 left blank.
        $b = $this->assessment($workspace, 'B-' . $suffix, [
            ['id' => 'q3', 'prompt' => 'سه بعلاوه سه؟', 'choices' => ['پنج', 'شش'], 'answer' => 1, 'explanation' => 'شش است.'],
            ['id' => 'q4', 'prompt' => 'دوم را انتخاب کن.', 'choices' => ['اول', 'دوم'], 'answer' => 1],
        ]);

        $attemptA = $exams->startAttempt($workspace['student'], $workspace['workspace'], $a);
        $exams->submitAttempt($workspace['student'], $workspace['workspace'], $attemptA['attempt_id'], 1, ['q1' => 0, 'q2' => 0]);
        $attemptB = $exams->startAttempt($workspace['student'], $workspace['workspace'], $b);
        $exams->submitAttempt($workspace['student'], $workspace['workspace'], $attemptB['attempt_id'], 1, ['q3' => 0]);

        $review = $exams->mistakesReview($workspace['student'], $workspace['workspace']);
        $ids = array_column($review['questions'], 'question_id');
        sort($ids);
        $this->assert($ids === ['q1', 'q3'], 'Review did not contain exactly the wrong-and-answered questions: got [' . implode(',', $ids) . '].');
        $this->assert($review['count'] === 2, 'Review count did not match its own question list.');

        $q1 = $review['questions'][array_search('q1', array_column($review['questions'], 'question_id'), true)];
        $this->assert($q1['correct'] === 1, 'Review did not return the correct choice index.');
        $this->assert($q1['explanation'] === 'چهار است.', 'Review did not carry the explanation through.');
        $this->assert($q1['assessment_id'] === $a, 'Review did not identify which assessment the question came from.');
    }

    private function assertRetakingAndFixingAMistakeClearsIt(): void
    {
        $suffix = $this->suffix();
        $workspace = $this->workspace('retake-' . $suffix);
        $exams = $this->exams();
        $a = $this->assessment($workspace, 'Retake-' . $suffix, [
            ['id' => 'q1', 'prompt' => 'دو بعلاوه دو؟', 'choices' => ['سه', 'چهار'], 'answer' => 1],
        ], maxAttempts: 5);

        $first = $exams->startAttempt($workspace['student'], $workspace['workspace'], $a);
        $exams->submitAttempt($workspace['student'], $workspace['workspace'], $first['attempt_id'], 1, ['q1' => 0]);
        $reviewAfterFirst = $exams->mistakesReview($workspace['student'], $workspace['workspace']);
        $this->assert(array_column($reviewAfterFirst['questions'], 'question_id') === ['q1'], 'The first wrong attempt did not show up in the review.');

        // Retake and get it right this time.
        $second = $exams->startAttempt($workspace['student'], $workspace['workspace'], $a);
        $exams->submitAttempt($workspace['student'], $workspace['workspace'], $second['attempt_id'], 1, ['q1' => 1]);
        $reviewAfterFix = $exams->mistakesReview($workspace['student'], $workspace['workspace']);
        $this->assert($reviewAfterFix['questions'] === [], 'Fixing a mistake on a later attempt did not clear it from the review.');
    }

    private function assertLeavingItBlankOnARetakeDoesNotEraseAnOlderMistake(): void
    {
        $suffix = $this->suffix();
        $workspace = $this->workspace('blank-retake-' . $suffix);
        $exams = $this->exams();
        $a = $this->assessment($workspace, 'BlankRetake-' . $suffix, [
            ['id' => 'q1', 'prompt' => 'دو بعلاوه دو؟', 'choices' => ['سه', 'چهار'], 'answer' => 1],
            ['id' => 'q2', 'prompt' => 'اول را انتخاب کن.', 'choices' => ['اول', 'دوم'], 'answer' => 0],
        ], maxAttempts: 5);

        $first = $exams->startAttempt($workspace['student'], $workspace['workspace'], $a);
        $exams->submitAttempt($workspace['student'], $workspace['workspace'], $first['attempt_id'], 1, ['q1' => 0, 'q2' => 0]);

        // Retake, but this time leave q1 blank entirely (only answer q2).
        // Leaving it blank is not the same as answering it correctly, so
        // the still-wrong verdict from the first attempt must keep showing.
        $second = $exams->startAttempt($workspace['student'], $workspace['workspace'], $a);
        $exams->submitAttempt($workspace['student'], $workspace['workspace'], $second['attempt_id'], 1, ['q2' => 0]);

        $review = $exams->mistakesReview($workspace['student'], $workspace['workspace']);
        $this->assert(array_column($review['questions'], 'question_id') === ['q1'], 'Leaving a mistake blank on a retake erased it from the review instead of keeping the older wrong verdict.');
    }

    private function assertAnotherStudentsMistakesDoNotLeak(): void
    {
        $suffix = $this->suffix();
        $workspace = $this->workspace('cross-student-' . $suffix);
        $otherStudent = $this->assignRole($workspace['workspace'], 'Other Student ' . $suffix, 'student');
        $exams = $this->exams();
        $a = $this->assessment($workspace, 'CrossStudent-' . $suffix, [
            ['id' => 'q1', 'prompt' => 'دو بعلاوه دو؟', 'choices' => ['سه', 'چهار'], 'answer' => 1],
        ]);

        $attempt = $exams->startAttempt($otherStudent, $workspace['workspace'], $a);
        $exams->submitAttempt($otherStudent, $workspace['workspace'], $attempt['attempt_id'], 1, ['q1' => 0]);

        $review = $exams->mistakesReview($workspace['student'], $workspace['workspace']);
        $this->assert($review['questions'] === [], "Another student's mistake leaked into this student's review.");
    }

    private function assertMistakesFromAnotherWorkspaceDoNotLeak(): void
    {
        $suffix = $this->suffix();
        $workspaceOne = $this->workspace('ws1-' . $suffix);
        $exams = $this->exams();
        $a = $this->assessment($workspaceOne, 'WS1-' . $suffix, [
            ['id' => 'q1', 'prompt' => 'دو بعلاوه دو؟', 'choices' => ['سه', 'چهار'], 'answer' => 1],
        ]);
        $attempt = $exams->startAttempt($workspaceOne['student'], $workspaceOne['workspace'], $a);
        $exams->submitAttempt($workspaceOne['student'], $workspaceOne['workspace'], $attempt['attempt_id'], 1, ['q1' => 0]);

        // The same person is also a legitimate member of a second, unrelated
        // workspace -- multi-class membership is a real, supported case --
        // so this is a query-scoping test, not merely an RBAC one: their
        // mistake in the first workspace must not appear when they ask for
        // a review scoped to the second, even though they are entitled to
        // read a review there at all.
        $workspaceTwo = $this->workspace('ws2-' . $suffix);
        $this->assignExistingUserRole($workspaceOne['student'], $workspaceTwo['workspace'], 'student');
        $review = $exams->mistakesReview($workspaceOne['student'], $workspaceTwo['workspace']);
        $this->assert($review['questions'] === [], 'A mistake from another workspace leaked across workspace boundaries.');
    }

    private function assertAnInProgressAttemptContributesNothing(): void
    {
        $suffix = $this->suffix();
        $workspace = $this->workspace('inprogress-' . $suffix);
        $exams = $this->exams();
        $a = $this->assessment($workspace, 'InProgress-' . $suffix, [
            ['id' => 'q1', 'prompt' => 'دو بعلاوه دو؟', 'choices' => ['سه', 'چهار'], 'answer' => 1],
        ]);
        $attempt = $exams->startAttempt($workspace['student'], $workspace['workspace'], $a);
        $exams->saveProgress($workspace['student'], $workspace['workspace'], $attempt['attempt_id'], 1, ['q1' => 0]);

        $review = $exams->mistakesReview($workspace['student'], $workspace['workspace']);
        $this->assert($review['questions'] === [], 'An in-progress (unscored) attempt contributed to the mistakes review.');
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
        $owner = $this->platformSuperAdmin('Mistakes Owner ' . $suffix);
        $class = (new ClassProvisioningService($this->database, $this->accessGate(), new AuditLogger($this->database)))->createClass($owner, [
            'country' => ['code' => 'ZZ', 'name' => 'Fixture Country'],
            'province' => ['name' => 'Fixture Province ' . $suffix],
            'city' => ['name' => 'Fixture City ' . $suffix],
            'institution' => ['name' => 'Fixture University ' . $suffix],
            'faculty' => ['name' => 'Fixture Faculty ' . $suffix],
            'program' => ['name' => 'Fixture Program ' . $suffix, 'degree_level' => 'professional-doctorate'],
            'cohort' => ['entry_year' => 5900, 'label' => 'Fixture Cohort ' . $suffix],
            'workspace' => ['name' => 'Fixture Mistakes Class ' . $suffix],
        ]);
        $workspaceId = $class['workspace_id'];

        return [
            'owner' => $owner,
            'workspace' => $workspaceId,
            'manager' => $this->assignRole($workspaceId, 'Mistakes Manager ' . $suffix, 'content-manager'),
            'reviewer' => $this->assignRole($workspaceId, 'Mistakes Reviewer ' . $suffix, 'content-reviewer'),
            'student' => $this->assignRole($workspaceId, 'Mistakes Student ' . $suffix, 'student'),
        ];
    }

    /**
     * Publishes a two-question(ish) assessment in the given workspace fixture
     * and returns its id.
     *
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
        $this->assignExistingUserRole($userId, $workspaceId, $roleKey);

        return $userId;
    }

    /** Membership in a second workspace for a person who already exists -- multi-class membership. */
    private function assignExistingUserRole(string $userId, string $workspaceId, string $roleKey): void
    {
        $this->database->prepare("INSERT INTO tenant_workspace_memberships (id, workspace_id, user_id, status, joined_at, created_at, updated_at) VALUES (:id, :workspace, :user, 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => Uuid::v7(), 'workspace' => $workspaceId, 'user' => $userId]);
        $scope = $this->workspaceScopeId($workspaceId);
        $this->database->prepare(<<<'SQL'
INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at)
SELECT :id, :user, role.id, :scope, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
FROM rbac_role_templates role WHERE role.role_key = :role
SQL)->execute(['id' => Uuid::v7(), 'user' => $userId, 'scope' => $scope, 'role' => $roleKey]);
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

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
