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
 * The three attempt modes (docs/product/01_FRONT_DOOR.md's exam runner
 * work): a student's chosen mode is fixed for the life of the attempt and
 * survives resume, practice and learning both reach the explanation on
 * demand through revealQuestion(), and assessment is refused a reveal in any
 * form. ExamQuestionPacingTest already covers the rate guard and audit trail
 * these reveals share; this file covers the mode rules themselves.
 */
final class ExamAttemptModeTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $this->assertModeIsFixedAtStartAndSurvivesResume();
        $this->assertPracticeReachesExplanationOnDemand();
        $this->assertLearningStillReveals();
        $this->assertAssessmentIsRefusedAnyReveal();
        $this->assertUnknownModeIsRejected();

        return $this->assertions;
    }

    private function assertModeIsFixedAtStartAndSurvivesResume(): void
    {
        $fixture = $this->fixture('fixed-' . $this->suffix());
        $exams = $this->exams();

        $started = $exams->startAttempt($fixture['student'], $fixture['workspace'], $fixture['assessment_id'], 'practice');
        $this->assert($started['mode'] === 'practice', 'Attempt did not start in the requested mode.');
        $this->assert($started['resumed'] === false, 'A brand-new attempt was reported as resumed.');

        // Calling start again while the same attempt is still in progress
        // must resume it -- and the mode it resumes at is the one it began
        // with, never whatever mode this second call asked for.
        $resumed = $exams->startAttempt($fixture['student'], $fixture['workspace'], $fixture['assessment_id'], 'assessment');
        $this->assert($resumed['resumed'] === true, 'A resumable in-progress attempt was not reported as resumed.');
        $this->assert($resumed['attempt_id'] === $started['attempt_id'], 'Resuming produced a different attempt instead of the existing one.');
        $this->assert($resumed['mode'] === 'practice', 'Resuming an attempt silently changed its mode.');
    }

    private function assertPracticeReachesExplanationOnDemand(): void
    {
        $fixture = $this->fixture('practice-' . $this->suffix());
        $exams = $this->exams();
        $attempt = $exams->startAttempt($fixture['student'], $fixture['workspace'], $fixture['assessment_id'], 'practice');

        $reveal = $exams->revealQuestion($fixture['student'], $fixture['workspace'], $attempt['attempt_id'], 1, 1_700_000_300);
        $this->assert($reveal['answer'] === 1, 'Practice reveal did not return the correct choice index.');
        $this->assert($reveal['explanation'] === 'پاسخ چهار است.', 'Practice reveal did not reach the explanation on demand.');
        $this->assert($reveal['revealed'] === [1], 'Practice reveal was not recorded on the attempt.');

        // Revealing the same position again must not duplicate the record.
        $exams->revealQuestion($fixture['student'], $fixture['workspace'], $attempt['attempt_id'], 1, 1_700_000_301);
        $revealedAgain = $this->revealedPositions($attempt['attempt_id']);
        $this->assert($revealedAgain === [1], 'Re-revealing the same position duplicated the revealed-positions record.');
    }

    private function assertLearningStillReveals(): void
    {
        $fixture = $this->fixture('learning-' . $this->suffix());
        $exams = $this->exams();
        $attempt = $exams->startAttempt($fixture['student'], $fixture['workspace'], $fixture['assessment_id'], 'learning');

        $reveal = $exams->revealQuestion($fixture['student'], $fixture['workspace'], $attempt['attempt_id'], 1, 1_700_000_400);
        $this->assert($reveal['answer'] === 1, 'Learning reveal regressed: wrong answer index.');
        $this->assert($reveal['explanation'] === 'پاسخ چهار است.', 'Learning reveal regressed: explanation missing.');
    }

    private function assertAssessmentIsRefusedAnyReveal(): void
    {
        $fixture = $this->fixture('assessment-' . $this->suffix());
        $exams = $this->exams();
        $attempt = $exams->startAttempt($fixture['student'], $fixture['workspace'], $fixture['assessment_id'], 'assessment');

        $this->expectCode('attempt_not_learning', fn () => $exams->revealQuestion(
            $fixture['student'], $fixture['workspace'], $attempt['attempt_id'], 1, 1_700_000_500,
        ));
    }

    private function assertUnknownModeIsRejected(): void
    {
        $fixture = $this->fixture('invalid-' . $this->suffix());
        $exams = $this->exams();

        $this->expectCode('attempt_mode_invalid', fn () => $exams->startAttempt(
            $fixture['student'], $fixture['workspace'], $fixture['assessment_id'], 'speedrun',
        ));
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
    private function fixture(string $suffix): array
    {
        $owner = $this->platformSuperAdmin('Mode Owner ' . $suffix);
        $class = (new ClassProvisioningService($this->database, $this->accessGate(), new AuditLogger($this->database)))->createClass($owner, [
            'country' => ['code' => 'ZZ', 'name' => 'Fixture Country'],
            'province' => ['name' => 'Fixture Province ' . $suffix],
            'city' => ['name' => 'Fixture City ' . $suffix],
            'institution' => ['name' => 'Fixture University ' . $suffix],
            'faculty' => ['name' => 'Fixture Faculty ' . $suffix],
            'program' => ['name' => 'Fixture Program ' . $suffix, 'degree_level' => 'professional-doctorate'],
            'cohort' => ['entry_year' => 5900, 'label' => 'Fixture Cohort ' . $suffix],
            'workspace' => ['name' => 'Fixture Mode Class ' . $suffix],
        ]);
        $workspace = $class['workspace_id'];
        $manager = $this->assignRole($workspace, 'Mode Manager ' . $suffix, 'content-manager');
        $reviewer = $this->assignRole($workspace, 'Mode Reviewer ' . $suffix, 'content-reviewer');
        $student = $this->assignRole($workspace, 'Mode Student ' . $suffix, 'student');

        $manage = $this->exams();
        $definition = ['questions' => [
            ['id' => 'q1', 'prompt' => 'دو بعلاوه دو؟', 'choices' => ['سه', 'چهار'], 'answer' => 1, 'explanation' => 'پاسخ چهار است.'],
            ['id' => 'q2', 'prompt' => 'اولین گزینه را انتخاب کن.', 'choices' => ['اول', 'دوم'], 'answer' => 0],
        ]];
        $assessment = $manage->createAssessment($manager, $workspace, 'آزمون حالت‌ها ' . $suffix, $definition, ['max_attempts' => 5]);
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

    /** @return list<int> */
    private function revealedPositions(string $attemptId): array
    {
        $query = $this->database->prepare('SELECT revealed_json FROM exam_attempts WHERE id = :attempt');
        $query->execute(['attempt' => $attemptId]);
        $json = $query->fetchColumn();
        if ($json === false) {
            throw new RuntimeException('Attempt was not found.');
        }
        $decoded = json_decode((string) $json, true, 16, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? array_map('intval', $decoded) : [];
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
