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
 * Server-paced single-question exam reads (docs/product/01_FRONT_DOOR.md's
 * question-bank bulk-export protection). ContentEngineTest already covers
 * the shape of startAttempt/readQuestion/attemptReviewQuestion, cross-user
 * and cross-workspace refusal, resume ordering and shuffle-invariant
 * scoring. This file covers what that scenario does not: the rate guard
 * itself (burst exhaustion and that the refusal survives -- the same
 * Transaction::run/commit-before-throw lesson
 * OnboardingPhoneVerificationTest already exercises for the OTP attempts
 * counter), the audit trail's content, and that entitlement is rechecked on
 * every read, not only at attempt start.
 */
final class ExamQuestionPacingTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $this->assertRateLimitEngagesAndCounterSurvivesRefusal();
        $this->assertAuditRecordsReadsWithoutQuestionOrExplanationText();
        $this->assertEntitlementIsRecheckedOnEveryRead();
        $this->assertStartAttemptReturnsExactlyOneQuestionAndConsumesAPacingToken();
        $this->assertStartAttemptDegradesGracefullyWhenPacingIsExhausted();

        return $this->assertions;
    }

    private function assertRateLimitEngagesAndCounterSurvivesRefusal(): void
    {
        $fixture = $this->fixture('rate-' . $this->suffix(), 5);
        $exams = $this->exams(burstCapacity: 2.0, refillSecondsPerToken: 1000.0);
        $attempt = $exams->startAttempt($fixture['student'], $fixture['workspace'], $fixture['assessment_id']);

        // Re-reading a question already seen (e.g. paging back) still spends a
        // token -- there is no "already read" exemption -- so exhausting the
        // burst only needs valid, in-range positions, never an out-of-range
        // one (which would refuse with question_position_invalid before the
        // rate guard is even consulted).
        $now = 1_700_000_000;
        $exams->readQuestion($fixture['student'], $fixture['workspace'], $attempt['attempt_id'], 1, $now);
        $exams->readQuestion($fixture['student'], $fixture['workspace'], $attempt['attempt_id'], 2, $now);

        $this->expectCode('question_read_rate_limited', fn () => $exams->readQuestion($fixture['student'], $fixture['workspace'], $attempt['attempt_id'], 1, $now));
        $tokensAfterFirstRefusal = $this->tokensRemaining($fixture['student']);
        $this->assert($tokensAfterFirstRefusal < 1.0, 'Burst was not exhausted after the configured number of reads.');

        // The same instant, again: if the refusal's own write had rolled back
        // (thrown from inside Transaction::run instead of after it), the
        // second call would recompute from a stale/unwritten row and could
        // wrongly succeed. It must refuse again, and the persisted counter
        // must not have silently reset to the full burst.
        $this->expectCode('question_read_rate_limited', fn () => $exams->readQuestion($fixture['student'], $fixture['workspace'], $attempt['attempt_id'], 1, $now));
        $tokensAfterSecondRefusal = $this->tokensRemaining($fixture['student']);
        $this->assert($tokensAfterSecondRefusal < 1.0, 'The rate-limit counter was reset by a refusal instead of surviving it.');
        $this->assert(abs($tokensAfterSecondRefusal - $tokensAfterFirstRefusal) < 0.0001, 'The rate-limit counter drifted across refusals at the same instant instead of staying stable.');
    }

    private function assertAuditRecordsReadsWithoutQuestionOrExplanationText(): void
    {
        $fixture = $this->fixture('audit-' . $this->suffix(), 5);
        $exams = $this->exams();
        $attempt = $exams->startAttempt($fixture['student'], $fixture['workspace'], $fixture['assessment_id']);
        $now = 1_700_000_100;
        $read = $exams->readQuestion($fixture['student'], $fixture['workspace'], $attempt['attempt_id'], 1, $now);

        $auditRow = $this->latestAudit('exam.question.read', $attempt['attempt_id']);
        $this->assert($auditRow !== null, 'A question read did not produce an audit row.');
        $this->assert($auditRow['actor_id'] === $fixture['student'] && $auditRow['workspace_id'] === $fixture['workspace'], 'Audit row did not attribute the read to the reading user/workspace.');
        $this->assert(!str_contains($auditRow['metadata_json'], $this->promptText('q1')) && !str_contains($auditRow['metadata_json'], $this->promptText('q2')), 'Audit metadata leaked question text.');
        $this->assert(str_contains($auditRow['metadata_json'], (string) $read['question']['id']), 'Audit metadata did not record which question (by stable id) was read.');

        // Submit and read both reviewed questions' explanations, same
        // requirement for the review-side audit trail. Whichever position
        // holds q1 (the only question with an explanation) is the
        // meaningful check; find it rather than assuming an order.
        $submitted = $exams->submitAttempt($fixture['student'], $fixture['workspace'], $attempt['attempt_id'], 1, [$read['question']['id'] => 0]);
        $this->assert($submitted['status'] === 'scored', 'Fixture attempt did not reach scored state.');
        $q1ExplanationLeaked = false;
        for ($position = 1; $position <= 2; $position++) {
            $explanationRead = $exams->attemptReviewQuestion($fixture['student'], $fixture['workspace'], $attempt['attempt_id'], $position, $now);
            $explanationAudit = $this->latestAudit('exam.explanation.read', $attempt['attempt_id']);
            $this->assert($explanationAudit !== null, 'An explanation read did not produce an audit row.');
            $this->assert($explanationRead['question_id'] !== '', 'Explanation read did not identify which question it covered.');
            if ($explanationRead['question_id'] === 'q1' && str_contains($explanationAudit['metadata_json'], $this->explanationText('q1'))) {
                $q1ExplanationLeaked = true;
            }
        }
        $this->assert(!$q1ExplanationLeaked, 'Audit metadata leaked explanation text.');
    }

    private function assertEntitlementIsRecheckedOnEveryRead(): void
    {
        $fixture = $this->fixture('entitlement-' . $this->suffix(), 3, requiresEntitlement: true);
        $entitlements = new EntitlementService($this->database, $this->accessGate(), new AuditLogger($this->database));
        $scope = $this->workspaceScopeId($fixture['workspace']);
        $entitlements->grantByAdministrator($fixture['owner'], $fixture['workspace'], $fixture['student'], $scope, 'fixture grant');

        $exams = $this->exams();
        $attempt = $exams->startAttempt($fixture['student'], $fixture['workspace'], $fixture['assessment_id']);
        $first = $exams->readQuestion($fixture['student'], $fixture['workspace'], $attempt['attempt_id'], 1, 1_700_000_200);
        $this->assert(($first['question']['id'] ?? '') !== '', 'An entitled read unexpectedly failed.');

        $grantId = $this->database->prepare('SELECT id FROM entitlement_grants WHERE workspace_id = :workspace AND subject_user_id = :user AND target_scope_id = :scope LIMIT 1');
        $grantId->execute(['workspace' => $fixture['workspace'], 'user' => $fixture['student'], 'scope' => $scope]);
        $entitlements->revoke($fixture['owner'], $fixture['workspace'], (string) $grantId->fetchColumn(), 'fixture revoke');

        // The attempt is still in_progress and was started while entitled,
        // but access is re-evaluated on every question read -- a revoked
        // entitlement must stop further reads immediately, not only block a
        // brand-new startAttempt.
        $this->expectCode('entitlement_required', fn () => $exams->readQuestion($fixture['student'], $fixture['workspace'], $attempt['attempt_id'], 2, 1_700_000_201));
    }

    /**
     * ExamService::startAttempt()'s bonus first-question (perf/exam-load-time):
     * a fresh attempt start returns position 1 already shaped exactly like
     * readQuestion()'s own output (never `answer`/`explanation`), never more
     * than that one question, and spends exactly one pacing token from the
     * same shared budget readQuestion() draws from -- while the idempotent
     * resume path (starting an attempt that is already in progress) returns
     * no bonus question and spends none at all.
     */
    private function assertStartAttemptReturnsExactlyOneQuestionAndConsumesAPacingToken(): void
    {
        $fixture = $this->fixture('start-bonus-' . $this->suffix(), 5);
        $exams = $this->exams(burstCapacity: 2.0, refillSecondsPerToken: 1000.0);
        $now = 1_700_000_300;

        $attempt = $exams->startAttempt($fixture['student'], $fixture['workspace'], $fixture['assessment_id'], 'assessment', $now);

        $this->assert($attempt['resumed'] === false, 'Fixture attempt was not recognised as a fresh start.');
        $this->assert(array_key_exists('first_question', $attempt), 'A fresh attempt start did not include first_question.');
        $first = $attempt['first_question'];
        $this->assert(is_array($first) && ($first['id'] ?? '') !== '', 'first_question was missing or malformed.');
        $this->assert(array_key_exists('prompt', $first) && array_key_exists('choices', $first), 'first_question did not carry the safe question shape.');
        foreach (['answer', 'explanation'] as $leak) {
            $this->assert(!array_key_exists($leak, $first), "first_question leaked {$leak}, violating readQuestion()'s never-before-submission guarantee.");
        }
        // Exactly one question: no other question-bearing key snuck into the response.
        $this->assert(!array_key_exists('questions', $attempt) && !array_key_exists('first_questions', $attempt), 'startAttempt returned more than a single bonus question.');

        $tokensAfterFreshStart = $this->tokensRemaining($fixture['student']);
        $this->assert(abs($tokensAfterFreshStart - 1.0) < 0.0001, 'A fresh attempt start did not spend exactly one pacing token from the 2.0 burst.');

        // Re-starting the same (still in-progress) attempt is the idempotent
        // resume path: it must return without first_question and without
        // touching the pacing budget at all.
        $resumed = $exams->startAttempt($fixture['student'], $fixture['workspace'], $fixture['assessment_id'], 'assessment', $now);
        $this->assert($resumed['resumed'] === true, 'Second start of the same in-progress attempt was not recognised as a resume.');
        $this->assert(!array_key_exists('first_question', $resumed), 'The idempotent resume path must never return a bonus question.');
        $tokensAfterResume = $this->tokensRemaining($fixture['student']);
        $this->assert(abs($tokensAfterResume - $tokensAfterFreshStart) < 0.0001, 'The idempotent resume path spent a pacing token; it must spend exactly zero.');

        // Same shape readQuestion() itself would produce for position 1 of
        // this attempt, confirming the two call sites did not drift apart --
        // they share the one safeQuestion() shaping method.
        $read = $exams->readQuestion($fixture['student'], $fixture['workspace'], $attempt['attempt_id'], 1, $now + 1000);
        $this->assert($read['question'] === $first, "startAttempt()'s bonus question did not match readQuestion()'s own shape for position 1.");
    }

    /**
     * A pacing guard that is already exhausted when an attempt starts must
     * not fail attempt creation -- it degrades to the same two-request
     * behaviour the client already falls back to (see runner.js's
     * QuestionWindow.load(1) fallback), never a 429 on starting.
     */
    private function assertStartAttemptDegradesGracefullyWhenPacingIsExhausted(): void
    {
        $fixture = $this->fixture('start-exhausted-' . $this->suffix(), 5);
        // Below one token and effectively no refill: even the very first
        // consume() call inside startAttempt() refuses, simulating a guard
        // already spent by prior reads.
        $exams = $this->exams(burstCapacity: 0.5, refillSecondsPerToken: 1000.0);
        $now = 1_700_000_400;

        $attempt = $exams->startAttempt($fixture['student'], $fixture['workspace'], $fixture['assessment_id'], 'assessment', $now);

        $this->assert($attempt['status'] === 'in_progress', 'Attempt creation must succeed even when the pacing guard refuses the bonus question.');
        $this->assert(!array_key_exists('first_question', $attempt), 'A refused pacing guard must omit first_question rather than fail attempt creation or fabricate one anyway.');
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

    private function exams(float $burstCapacity = 6.0, float $refillSecondsPerToken = 20.0): ExamService
    {
        $audit = new AuditLogger($this->database);
        $authorizer = new ScopeAuthorizer($this->database);
        $access = new AccessGate($this->database, $authorizer);
        $entitlements = new EntitlementService($this->database, $access, $audit);

        return new ExamService($this->database, $access, $authorizer, $entitlements, $audit, new ExamQuestionRateGuard($this->database, $burstCapacity, $refillSecondsPerToken));
    }

    private function promptText(string $questionId): string
    {
        return $questionId === 'q1' ? 'دو بعلاوه دو؟' : 'اولین گزینه را انتخاب کن.';
    }

    private function explanationText(string $questionId): string
    {
        return $questionId === 'q1' ? 'پاسخ چهار است.' : '';
    }

    /**
     * Provisions a fresh workspace, an owner/manager/reviewer/student and one
     * published two-question assessment, and returns the ids a test needs.
     *
     * @return array<string, mixed>
     */
    private function fixture(string $suffix, int $maxAttempts, bool $requiresEntitlement = false): array
    {
        $owner = $this->platformSuperAdmin('Pacing Owner ' . $suffix);
        $class = (new ClassProvisioningService($this->database, $this->accessGate(), new AuditLogger($this->database)))->createClass($owner, [
            'country' => ['code' => 'ZZ', 'name' => 'Fixture Country'],
            'province' => ['name' => 'Fixture Province ' . $suffix],
            'city' => ['name' => 'Fixture City ' . $suffix],
            'institution' => ['name' => 'Fixture University ' . $suffix],
            'faculty' => ['name' => 'Fixture Faculty ' . $suffix],
            'program' => ['name' => 'Fixture Program ' . $suffix, 'degree_level' => 'professional-doctorate'],
            'cohort' => ['entry_year' => 5900, 'label' => 'Fixture Cohort ' . $suffix],
            'workspace' => ['name' => 'Fixture Pacing Class ' . $suffix],
        ]);
        $workspace = $class['workspace_id'];
        $manager = $this->assignRole($workspace, 'Pacing Manager ' . $suffix, 'content-manager');
        $reviewer = $this->assignRole($workspace, 'Pacing Reviewer ' . $suffix, 'content-reviewer');
        $student = $this->assignRole($workspace, 'Pacing Student ' . $suffix, 'student');

        $manage = new ExamService($this->database, $this->accessGate(), new ScopeAuthorizer($this->database), new EntitlementService($this->database, $this->accessGate(), new AuditLogger($this->database)), new AuditLogger($this->database), new ExamQuestionRateGuard($this->database, 1000.0, 1.0));
        $definition = ['questions' => [
            ['id' => 'q1', 'prompt' => $this->promptText('q1'), 'choices' => ['سه', 'چهار'], 'answer' => 1, 'explanation' => $this->explanationText('q1')],
            ['id' => 'q2', 'prompt' => $this->promptText('q2'), 'choices' => ['اول', 'دوم'], 'answer' => 0],
        ]];
        $assessment = $manage->createAssessment($manager, $workspace, 'آزمون سرعت‌گیری ' . $suffix, $definition, [
            'requires_entitlement' => $requiresEntitlement,
            'max_attempts' => $maxAttempts,
        ]);
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

    private function tokensRemaining(string $userId): float
    {
        $query = $this->database->prepare('SELECT tokens_remaining FROM exam_question_read_rate_guards WHERE user_id = :user');
        $query->execute(['user' => $userId]);
        $value = $query->fetchColumn();
        if ($value === false) {
            throw new RuntimeException('Rate guard row was not persisted.');
        }

        return (float) $value;
    }

    /** @return array{actor_id:string,workspace_id:string,metadata_json:string}|null */
    private function latestAudit(string $action, string $subjectId): ?array
    {
        $query = $this->database->prepare('SELECT actor_id, workspace_id, metadata_json FROM audit_events WHERE action = :action AND subject_id = :subject ORDER BY occurred_at DESC LIMIT 1');
        $query->execute(['action' => $action, 'subject' => $subjectId]);
        $row = $query->fetch();

        return $row === false ? null : $row;
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
