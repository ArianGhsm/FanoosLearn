<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Transaction;
use Fanoos\Platform\Support\Uuid;
use PDO;

final class ExamService
{
    public function __construct(
        private readonly PDO $database,
        private readonly AccessGate $access,
        private readonly ScopeAuthorizer $authorizer,
        private readonly EntitlementService $entitlements,
        private readonly AuditLogger $audit,
        private readonly ExamQuestionRateGuard $questionRateGuard,
    ) {
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $metadata
     * @return array{assessment_id:string,version_id:string,version_no:int,status:string}
     */
    public function createAssessment(
        string $actorUserId,
        string $workspaceId,
        string $title,
        array $definition,
        array $metadata = [],
    ): array {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'exam.manage');
        $title = $this->text($title, 200, 'assessment_title_invalid');
        $requestedKind = (string) ($metadata['assessment_kind'] ?? 'practice');
        if (!in_array($requestedKind, ['practice', 'quiz', 'mock_exam', 'past_exam'], true)) {
            throw new PlatformException('assessment_kind_invalid', 'Assessment kind is invalid.', 422);
        }
        // Keep the original CHECK-compatible kind for older databases while
        // exposing quiz as a first-class catalog variant through the additive
        // assessment_variant column.
        $kind = $requestedKind === 'quiz' ? 'practice' : $requestedKind;
        $variant = $requestedKind === 'quiz' ? 'quiz' : null;
        $definitionJson = $this->definition($definition);
        $academic = $this->academic($workspaceId, $metadata);
        $scopeId = $this->targetScope($workspaceId, $metadata['target_scope_id'] ?? null);
        $maxAttempts = filter_var($metadata['max_attempts'] ?? 3, FILTER_VALIDATE_INT);
        if ($maxAttempts === false || $maxAttempts < 1 || $maxAttempts > 100) {
            throw new PlatformException('max_attempts_invalid', 'Maximum attempts must be between 1 and 100.', 422);
        }
        $timeLimitMinutes = $this->timeLimitMinutes($metadata['time_limit_minutes'] ?? null);
        $assessmentId = Uuid::v7();
        $versionId = Uuid::v7();
        $assessmentScopeId = Uuid::v7();

        Transaction::run($this->database, function () use (
            $actorUserId, $workspaceId, $title, $kind, $variant, $definitionJson, $academic,
            $scopeId, $maxAttempts, $timeLimitMinutes, $assessmentId, $versionId, $assessmentScopeId, $metadata,
        ): void {
            $this->execute(<<<'SQL'
INSERT INTO exam_assessments (
    id, workspace_id, offering_id, title, status, current_version_no, created_at, updated_at
) VALUES (:id, :workspace, :offering, :title, 'draft', 0, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL, ['id' => $assessmentId, 'workspace' => $workspaceId, 'offering' => $academic['offering_id'], 'title' => $title]);
            $this->insertVersion($workspaceId, $assessmentId, $versionId, 1, $actorUserId, $definitionJson);
            $this->execute(<<<'SQL'
INSERT INTO exam_assessment_metadata (
    workspace_id, assessment_id, assessment_kind, assessment_variant, course_id, source_resource_id, created_at, updated_at
) VALUES (:workspace, :assessment, :kind, :variant, :course, :source, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL, [
                'workspace' => $workspaceId, 'assessment' => $assessmentId, 'kind' => $kind, 'variant' => $variant,
                'course' => $academic['course_id'], 'source' => $academic['source_resource_id'],
            ]);
            $this->execute(<<<'SQL'
INSERT INTO exam_access_policies (
    workspace_id, assessment_id, target_scope_id, requires_entitlement, max_attempts, time_limit_minutes, updated_at
) VALUES (:workspace, :assessment, :scope, :entitled, :max_attempts, :time_limit_minutes, UTC_TIMESTAMP(6))
SQL, [
                'workspace' => $workspaceId, 'assessment' => $assessmentId, 'scope' => $scopeId,
                'entitled' => (bool) ($metadata['requires_entitlement'] ?? false) ? 1 : 0,
                'max_attempts' => $maxAttempts, 'time_limit_minutes' => $timeLimitMinutes,
            ]);
            $workspaceScope = $this->workspaceScope($workspaceId);
            $this->execute(<<<'SQL'
INSERT INTO rbac_scopes (id, scope_type, entity_id, workspace_id, parent_scope_id, created_at)
VALUES (:id, 'assessment', :assessment, :workspace, :parent, UTC_TIMESTAMP(6))
SQL, ['id' => $assessmentScopeId, 'assessment' => $assessmentId, 'workspace' => $workspaceId, 'parent' => $workspaceScope]);
            $this->audit->record($workspaceId, $actorUserId, 'exam.assessment.created', 'exam_assessment', $assessmentId, 'success', ['kind' => $variant ?? $kind, 'version_no' => 1]);
        });

        return ['assessment_id' => $assessmentId, 'version_id' => $versionId, 'version_no' => 1, 'status' => 'draft'];
    }

    /** @param array<string, mixed> $definition @return array{assessment_id:string,version_id:string,version_no:int,status:string} */
    public function addVersion(string $actorUserId, string $workspaceId, string $assessmentId, array $definition): array
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'exam.manage');
        $definitionJson = $this->definition($definition);

        return Transaction::run($this->database, function () use ($actorUserId, $workspaceId, $assessmentId, $definitionJson): array {
            $assessment = $this->database->prepare(<<<'SQL'
SELECT assessment.id,
       (SELECT COALESCE(MAX(version.version_no), 0) FROM exam_assessment_versions version
        WHERE version.assessment_id = assessment.id AND version.workspace_id = assessment.workspace_id) AS latest_version_no
FROM exam_assessments assessment
WHERE assessment.id = :assessment AND assessment.workspace_id = :workspace AND assessment.archived_at IS NULL
FOR UPDATE
SQL);
            $assessment->execute(['assessment' => $assessmentId, 'workspace' => $workspaceId]);
            $row = $assessment->fetch();
            if ($row === false) {
                throw new PlatformException('assessment_not_found', 'Assessment was not found.', 404);
            }
            $versionNo = (int) $row['latest_version_no'] + 1;
            $versionId = Uuid::v7();
            $this->insertVersion($workspaceId, $assessmentId, $versionId, $versionNo, $actorUserId, $definitionJson);
            $this->execute('UPDATE exam_assessments SET updated_at = UTC_TIMESTAMP(6) WHERE id = :assessment AND workspace_id = :workspace', ['assessment' => $assessmentId, 'workspace' => $workspaceId]);
            $this->audit->record($workspaceId, $actorUserId, 'exam.version.created', 'exam_assessment_version', $versionId, 'success', ['assessment_id' => $assessmentId, 'version_no' => $versionNo]);

            return ['assessment_id' => $assessmentId, 'version_id' => $versionId, 'version_no' => $versionNo, 'status' => 'draft'];
        });
    }

    public function submitForReview(string $actorUserId, string $workspaceId, string $assessmentId, string $versionId): void
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'exam.manage');
        Transaction::run($this->database, function () use ($actorUserId, $workspaceId, $assessmentId, $versionId): void {
            $state = $this->versionState($workspaceId, $assessmentId, $versionId, true);
            if (!in_array($state['status'], ['draft', 'rejected'], true)) {
                throw new PlatformException('exam_version_state_conflict', 'Assessment version cannot enter review.', 409);
            }
            $this->execute("UPDATE exam_version_states SET status = 'review', updated_at = UTC_TIMESTAMP(6) WHERE workspace_id = :workspace AND assessment_version_id = :version", ['workspace' => $workspaceId, 'version' => $versionId]);
            $this->execute("UPDATE exam_assessments SET status = IF(current_version_no = 0, 'review', status), updated_at = UTC_TIMESTAMP(6) WHERE id = :assessment AND workspace_id = :workspace", ['assessment' => $assessmentId, 'workspace' => $workspaceId]);
            $this->audit->record($workspaceId, $actorUserId, 'exam.version.review_requested', 'exam_assessment_version', $versionId, 'success', ['assessment_id' => $assessmentId]);
        });
    }

    public function reviewVersion(string $actorUserId, string $workspaceId, string $assessmentId, string $versionId, string $decision, ?string $note = null): void
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'exam.review');
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new PlatformException('review_decision_invalid', 'Review decision is invalid.', 422);
        }
        $note = $note === null ? null : $this->text($note, 1000, 'review_note_invalid');
        Transaction::run($this->database, function () use ($actorUserId, $workspaceId, $assessmentId, $versionId, $decision, $note): void {
            $state = $this->versionState($workspaceId, $assessmentId, $versionId, true);
            if ($state['status'] !== 'review') {
                throw new PlatformException('exam_version_state_conflict', 'Assessment version is not awaiting review.', 409);
            }
            if (hash_equals((string) $state['created_by_user_id'], $actorUserId)) {
                throw new PlatformException('self_review_forbidden', 'An assessment creator cannot approve their own work.', 403);
            }
            $this->execute(<<<'SQL'
INSERT INTO exam_version_reviews (
    id, workspace_id, assessment_id, assessment_version_id, reviewer_user_id, decision, note, decided_at
) VALUES (:id, :workspace, :assessment, :version, :reviewer, :decision, :note, UTC_TIMESTAMP(6))
SQL, [
                'id' => Uuid::v7(), 'workspace' => $workspaceId, 'assessment' => $assessmentId,
                'version' => $versionId, 'reviewer' => $actorUserId, 'decision' => $decision, 'note' => $note,
            ]);
            $this->execute('UPDATE exam_version_states SET status = :decision, updated_at = UTC_TIMESTAMP(6) WHERE workspace_id = :workspace AND assessment_version_id = :version', ['decision' => $decision, 'workspace' => $workspaceId, 'version' => $versionId]);
            if ($decision === 'rejected') {
                $this->execute("UPDATE exam_assessments SET status = IF(current_version_no = 0, 'draft', status), updated_at = UTC_TIMESTAMP(6) WHERE id = :assessment AND workspace_id = :workspace", ['assessment' => $assessmentId, 'workspace' => $workspaceId]);
            }
            $this->audit->record($workspaceId, $actorUserId, 'exam.version.' . $decision, 'exam_assessment_version', $versionId, 'success', ['assessment_id' => $assessmentId]);
        });
    }

    public function publishVersion(string $actorUserId, string $workspaceId, string $assessmentId, string $versionId): void
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'exam.manage');
        Transaction::run($this->database, function () use ($actorUserId, $workspaceId, $assessmentId, $versionId): void {
            $state = $this->versionState($workspaceId, $assessmentId, $versionId, true);
            if ($state['status'] !== 'approved') {
                throw new PlatformException('exam_version_not_approved', 'Only an approved assessment version can be published.', 409);
            }
            $approval = $this->database->prepare("SELECT 1 FROM exam_version_reviews WHERE assessment_version_id = :version AND decision = 'approved' LIMIT 1");
            $approval->execute(['version' => $versionId]);
            if ($approval->fetchColumn() === false) {
                throw new PlatformException('review_evidence_missing', 'Assessment approval evidence is missing.', 409);
            }
            $this->execute(<<<'SQL'
UPDATE exam_assessments
SET current_version_no = :version_no, status = 'published', updated_at = UTC_TIMESTAMP(6)
WHERE id = :assessment AND workspace_id = :workspace
SQL, ['version_no' => $state['version_no'], 'assessment' => $assessmentId, 'workspace' => $workspaceId]);
            $this->outbox($workspaceId, 'exam_assessment', $assessmentId, 'exam.assessment.published', [
                'assessment_id' => $assessmentId,
                'assessment_version_id' => $versionId,
                'version_no' => (int) $state['version_no'],
            ]);
            $this->audit->record($workspaceId, $actorUserId, 'exam.version.published', 'exam_assessment_version', $versionId, 'success', ['assessment_id' => $assessmentId, 'version_no' => (int) $state['version_no']]);
        });
    }

    /** @return list<array<string, mixed>> */
    public function catalog(string $actorUserId, string $workspaceId, ?string $courseId = null, ?string $kind = null): array
    {
        $where = [];
        $parameters = [];
        if ($courseId !== null && $courseId !== '') {
            $where[] = 'metadata.course_id = :course';
            $parameters['course'] = $courseId;
        }
        if ($kind !== null && $kind !== '') {
            $where[] = 'COALESCE(metadata.assessment_variant, metadata.assessment_kind) = :kind';
            $parameters['kind'] = $kind;
        }

        return $this->catalogRows($actorUserId, $workspaceId, $where, $parameters, 100);
    }

    /**
     * One assessment's catalogue-shaped row, for the exam page to embed
     * server-side instead of the runner fetching the whole catalogue just to
     * find the one it already knows the id of (see ExamAttemptPage). Reuses
     * catalog()'s own query/authorization/row-shape rather than fetching
     * every assessment and filtering in PHP, which would just move the same
     * inefficiency server-side.
     *
     * @return array<string, mixed>|null
     */
    public function catalogEntry(string $userId, string $workspaceId, string $assessmentId): ?array
    {
        $rows = $this->catalogRows($userId, $workspaceId, ['assessment.id = :entry_assessment'], ['entry_assessment' => $assessmentId], 1);

        return $rows[0] ?? null;
    }

    /**
     * Shared catalogue query behind catalog() and catalogEntry(): same
     * authorization, shape and row-mapping, scoped by whichever extra WHERE
     * clauses and row limit the caller adds.
     *
     * @param list<string> $extraWhere
     * @param array<string, mixed> $extraParameters
     * @return list<array<string, mixed>>
     */
    private function catalogRows(string $actorUserId, string $workspaceId, array $extraWhere, array $extraParameters, int $limit): array
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'exam.take');
        $where = array_merge(
            ["assessment.workspace_id = :workspace", "assessment.status = 'published'", 'assessment.archived_at IS NULL'],
            $extraWhere,
        );
        $parameters = array_merge(['workspace' => $workspaceId], $extraParameters);
        $query = $this->database->prepare(sprintf(<<<'SQL'
SELECT assessment.id, assessment.title, assessment.current_version_no,
       COALESCE(metadata.assessment_variant, metadata.assessment_kind) AS assessment_kind,
       metadata.assessment_kind AS storage_assessment_kind, metadata.assessment_variant,
       metadata.course_id, metadata.source_resource_id,
       course.course_code, course.title AS course_title,
       term.id AS term_id, term.term_key, term.name AS term_name,
       policy.requires_entitlement, policy.max_attempts, policy.time_limit_minutes,
       (SELECT COUNT(*) FROM exam_attempts attempt_count
        WHERE attempt_count.workspace_id = assessment.workspace_id
          AND attempt_count.assessment_id = assessment.id
          AND attempt_count.user_id = :catalog_user_count
          AND attempt_count.status IN ('submitted', 'scored')) AS attempts_used,
       (SELECT active_attempt.id FROM exam_attempts active_attempt
        WHERE active_attempt.workspace_id = assessment.workspace_id
          AND active_attempt.assessment_id = assessment.id
          AND active_attempt.user_id = :catalog_user_active
          AND active_attempt.status = 'in_progress'
        ORDER BY active_attempt.started_at DESC
        LIMIT 1) AS active_attempt_id,
       (SELECT active_attempt.revision FROM exam_attempts active_attempt
        WHERE active_attempt.workspace_id = assessment.workspace_id
          AND active_attempt.assessment_id = assessment.id
          AND active_attempt.user_id = :catalog_user_revision
          AND active_attempt.status = 'in_progress'
        ORDER BY active_attempt.started_at DESC
        LIMIT 1) AS active_attempt_revision,
       (SELECT active_attempt.status FROM exam_attempts active_attempt
        WHERE active_attempt.workspace_id = assessment.workspace_id
          AND active_attempt.assessment_id = assessment.id
          AND active_attempt.user_id = :catalog_user_status
          AND active_attempt.status = 'in_progress'
        ORDER BY active_attempt.started_at DESC
        LIMIT 1) AS active_attempt_status
FROM exam_assessments assessment
JOIN exam_assessment_metadata metadata ON metadata.assessment_id = assessment.id AND metadata.workspace_id = assessment.workspace_id
JOIN exam_access_policies policy ON policy.assessment_id = assessment.id AND policy.workspace_id = assessment.workspace_id
LEFT JOIN academic_course_offerings offering ON offering.id = assessment.offering_id AND offering.workspace_id = assessment.workspace_id
LEFT JOIN academic_terms term ON term.id = offering.term_id AND term.workspace_id = offering.workspace_id
LEFT JOIN academic_courses course ON course.id = metadata.course_id AND course.workspace_id = assessment.workspace_id
WHERE %s
  AND (assessment.offering_id IS NULL OR (offering.status <> 'archived' AND offering.archived_at IS NULL))
  AND (assessment.offering_id IS NULL OR (term.status <> 'archived' AND term.archived_at IS NULL))
  AND (metadata.course_id IS NULL OR (course.status = 'active' AND course.archived_at IS NULL))
ORDER BY assessment.updated_at DESC
LIMIT %d
SQL, implode(' AND ', $where), $limit));
        $parameters['catalog_user_count'] = $actorUserId;
        $parameters['catalog_user_active'] = $actorUserId;
        $parameters['catalog_user_revision'] = $actorUserId;
        $parameters['catalog_user_status'] = $actorUserId;
        $query->execute($parameters);

        return $query->fetchAll();
    }

    /**
     * مرور اشتباه‌ها: every question this student has answered incorrectly
     * across their own scored attempts in this workspace, deduplicated by
     * (assessment, question) -- the most recently scored attempt wins when
     * the same question was answered wrong more than once.
     *
     * Nothing here widens what a student can already see: every field
     * returned (prompt, choices, correct answer, explanation) is the same
     * review content attemptReviewQuestion() already serves for a scored
     * attempt the caller owns -- this only aggregates it across attempts
     * instead of within one, so it is read directly rather than paced like
     * an in-progress question, which this is not.
     *
     * A question left blank does not count as "answered wrong": the review
     * only includes an entry the student actually chose an incorrect
     * option for.
     *
     * @return array{questions:list<array<string,mixed>>,count:int}
     */
    public function mistakesReview(string $userId, string $workspaceId): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        $query = $this->database->prepare(<<<'SQL'
SELECT attempt.assessment_id, assessment.title AS assessment_title,
       version.definition_json, result.review_json
FROM exam_attempts attempt
JOIN exam_attempt_results result ON result.attempt_id = attempt.id AND result.workspace_id = attempt.workspace_id
JOIN exam_assessment_versions version ON version.id = attempt.assessment_version_id
 AND version.assessment_id = attempt.assessment_id AND version.workspace_id = attempt.workspace_id
JOIN exam_assessments assessment ON assessment.id = attempt.assessment_id AND assessment.workspace_id = attempt.workspace_id
WHERE attempt.workspace_id = :workspace AND attempt.user_id = :user AND attempt.status = 'scored'
ORDER BY attempt.submitted_at DESC
SQL);
        $query->execute(['workspace' => $workspaceId, 'user' => $userId]);

        // Newest attempt first, and the first (= most recent) time a given
        // question was actually *answered* settles it -- so retaking an
        // assessment and getting a question right the second time clears it
        // from the review, it does not merely add another entry alongside
        // the old wrong one. A blank entry on a later attempt must not
        // settle anything: leaving a question blank on a retake is not the
        // same as answering it correctly, so it must not erase an earlier
        // wrong answer either -- the most recent *answered* outcome is what
        // decides, skipping blanks as if that attempt never touched it.
        $seen = [];
        $questions = [];
        while (($row = $query->fetch()) !== false) {
            $review = json_decode((string) $row['review_json'], true, 64, JSON_THROW_ON_ERROR);
            $definition = null;
            foreach ($review as $entry) {
                if ($entry['selected'] === null) {
                    continue;
                }
                $key = $row['assessment_id'] . ':' . $entry['id'];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                if ($entry['is_correct'] !== false) {
                    continue;
                }
                $definition ??= json_decode((string) $row['definition_json'], true, 64, JSON_THROW_ON_ERROR);
                try {
                    $question = $this->questionById($definition, (string) $entry['id']);
                } catch (PlatformException) {
                    // The question no longer exists in this version (edited
                    // out since); skip it rather than failing the whole
                    // review over one stale reference.
                    continue;
                }
                $questions[$key] = [
                    'assessment_id' => (string) $row['assessment_id'],
                    'assessment_title' => (string) $row['assessment_title'],
                    'question_id' => (string) $entry['id'],
                    'prompt' => $question['prompt'],
                    'choices' => $question['choices'],
                    'correct' => $question['answer'],
                    'explanation' => $question['explanation'] ?? null,
                ];
            }
        }

        return ['questions' => array_values($questions), 'count' => count($questions)];
    }

    /** @return array<string, mixed> */
    public function startAttempt(string $userId, string $workspaceId, string $assessmentId, string $mode = 'assessment', ?int $now = null): array
    {
        if (!in_array($mode, ['assessment', 'learning', 'practice'], true)) {
            throw new PlatformException('attempt_mode_invalid', 'Attempt mode is invalid.', 422);
        }
        $assessment = $this->publishedAssessment($workspaceId, $assessmentId);
        $decision = $this->authorizer->decide($userId, 'exam.take', 'assessment', (string) $assessment['scope_id'], $workspaceId);
        if (!$decision->allowed) {
            throw new PlatformException('assessment_access_denied', 'Assessment access was denied.', 403);
        }
        if ((bool) $assessment['requires_entitlement'] && !$this->entitlements->has($userId, $workspaceId, (string) $assessment['target_scope_id'])) {
            throw new PlatformException('entitlement_required', 'An active entitlement is required for this assessment.', 403);
        }
        return Transaction::run($this->database, function () use ($userId, $workspaceId, $assessmentId, $assessment, $mode, $now): array {
        // Serialize starts per assessment so two concurrent clicks cannot create
        // two open attempts for the same published version.
        $lock = $this->database->prepare('SELECT id FROM exam_assessments WHERE id = :assessment AND workspace_id = :workspace FOR UPDATE');
        $lock->execute(['assessment' => $assessmentId, 'workspace' => $workspaceId]);
        if ($lock->fetchColumn() === false) {
            throw new PlatformException('assessment_not_found', 'Published assessment was not found.', 404);
        }
        $existing = $this->database->prepare(<<<'SQL'
SELECT attempt.id, attempt.revision, attempt.status, attempt.mode, attempt.answers_json, attempt.deadline_at,
       version.definition_json
FROM exam_attempts attempt
JOIN exam_assessment_versions version ON version.id = attempt.assessment_version_id
 AND version.assessment_id = attempt.assessment_id AND version.workspace_id = attempt.workspace_id
WHERE attempt.workspace_id = :workspace AND attempt.assessment_id = :assessment
  AND attempt.assessment_version_id = :version AND attempt.user_id = :user
  AND attempt.status = 'in_progress'
ORDER BY attempt.started_at DESC
LIMIT 1
SQL);
        $existing->execute([
            'workspace' => $workspaceId, 'assessment' => $assessmentId,
            'version' => $assessment['version_id'], 'user' => $userId,
        ]);
        $existingAttempt = $existing->fetch();
        if ($existingAttempt !== false) {
            $definition = json_decode((string) $existingAttempt['definition_json'], true, 64, JSON_THROW_ON_ERROR);
            return [
                'attempt_id' => (string) $existingAttempt['id'], 'revision' => (int) $existingAttempt['revision'],
                'status' => 'in_progress', 'assessment_id' => $assessmentId, 'title' => (string) $assessment['title'],
                'answers' => json_decode((string) ($existingAttempt['answers_json'] ?? '{}'), true, 64, JSON_THROW_ON_ERROR),
                'resumed' => true, 'question_count' => count($definition['questions']),
                // The mode is fixed when the attempt starts; resuming never
                // silently promotes a practice run into a real sitting. The
                // deadline is exactly as fixed: it was set once, when this
                // attempt first started, and resuming never extends it.
                'mode' => (string) $existingAttempt['mode'],
                'deadline_at' => $this->isoTimestamp($existingAttempt['deadline_at']),
            ];
        }
        $attempts = $this->database->prepare("SELECT COUNT(*) FROM exam_attempts WHERE workspace_id = :workspace AND assessment_id = :assessment AND user_id = :user AND status IN ('submitted', 'scored')");
        $attempts->execute(['workspace' => $workspaceId, 'assessment' => $assessmentId, 'user' => $userId]);
        if ((int) $attempts->fetchColumn() >= (int) $assessment['max_attempts']) {
            throw new PlatformException('attempt_limit_reached', 'Assessment attempt limit has been reached.', 409);
        }
        $attemptId = Uuid::v7();
        // Computed in the same INSERT, from the same UTC_TIMESTAMP(6) as
        // started_at, so the deadline can never drift from when the attempt
        // actually began -- a PHP-side "now" could skew against it under
        // clock difference or query latency.
        $timeLimitMinutes = $assessment['time_limit_minutes'] === null ? null : (int) $assessment['time_limit_minutes'];
        $deadlineExpression = $timeLimitMinutes === null ? 'NULL' : ('UTC_TIMESTAMP(6) + INTERVAL ' . $timeLimitMinutes . ' MINUTE');
        $this->execute(<<<SQL
INSERT INTO exam_attempts (
    id, workspace_id, assessment_id, assessment_version_id, user_id,
    status, mode, revision, answers_json, revealed_json, started_at, deadline_at
) VALUES (
    :id, :workspace, :assessment, :version, :user,
    'in_progress', :mode, 1, JSON_OBJECT(), JSON_ARRAY(), UTC_TIMESTAMP(6), {$deadlineExpression}
)
SQL, [
            'id' => $attemptId, 'workspace' => $workspaceId, 'assessment' => $assessmentId,
            'version' => $assessment['version_id'], 'user' => $userId, 'mode' => $mode,
        ]);
        $deadline = $this->database->prepare('SELECT deadline_at FROM exam_attempts WHERE id = :attempt AND workspace_id = :workspace');
        $deadline->execute(['attempt' => $attemptId, 'workspace' => $workspaceId]);
        $definition = json_decode((string) $assessment['definition_json'], true, 64, JSON_THROW_ON_ERROR);
        $this->audit->record($workspaceId, $userId, 'exam.attempt.started', 'exam_attempt', $attemptId, 'success', ['assessment_id' => $assessmentId, 'version_id' => $assessment['version_id']]);

        $response = [
            'attempt_id' => $attemptId, 'revision' => 1, 'status' => 'in_progress',
            'assessment_id' => $assessmentId, 'title' => (string) $assessment['title'],
            'answers' => [], 'resumed' => false, 'mode' => $mode,
            'question_count' => count($definition['questions']),
            'deadline_at' => $this->isoTimestamp($deadline->fetchColumn()),
        ];

        // Bonus: collapse the client's start-then-read round trip into this
        // one response by handing back position 1 already shaped, on the
        // same pacing budget as any other question read -- one token, spent
        // only for a genuinely new attempt (never on the idempotent-resume
        // branch above, which returns before this point). A refused guard
        // must not fail attempt creation: the client already falls back to
        // its normal GET .../questions/1 when `first_question` is absent, so
        // this degrades to exactly today's two-request behaviour rather than
        // an error.
        if ($this->questionRateGuard->consume($userId, $now)) {
            $questionIds = array_map(static fn (array $question): string => (string) $question['id'], $definition['questions']);
            $order = ExamAttemptShuffle::questionOrder($attemptId, $questionIds);
            $firstQuestionId = $order[0];
            $this->audit->record($workspaceId, $userId, 'exam.question.read', 'exam_attempt', $attemptId, 'success', [
                'question_id' => $firstQuestionId, 'position' => 1,
            ]);
            $response['first_question'] = $this->safeQuestion($this->questionById($definition, $firstQuestionId));
        }

        return $response;
        });
    }

    /**
     * Reveals the answer and explanation for one question of a learning
     * attempt, before it is submitted.
     *
     * This is what makes learning and practice mode possible without handing
     * the paper over: the reveal is per question, only inside an attempt the
     * caller owns, only when that attempt was started in a mode that reveals
     * (learning or practice -- never assessment), paced by the same token
     * bucket as every other read, and audited. The alternative -- shipping
     * answers alongside the questions so the page can reveal them itself --
     * would put the whole answer key in the browser for anyone who opens
     * developer tools, which is precisely the bulk extraction the pacing
     * work exists to prevent.
     *
     * Each reveal is recorded against the attempt so the report can say the
     * answer was seen first. A revealed question still scores by what the
     * student chose; what changes is that the score is no longer presented
     * as if it were earned blind.
     *
     * @return array{position:int,question_count:int,question_id:string,answer:int,explanation:?string,revealed:list<int>}
     */
    public function revealQuestion(string $userId, string $workspaceId, string $attemptId, int $position, ?int $now = null): array
    {
        $outcome = Transaction::run($this->database, function () use ($userId, $workspaceId, $attemptId, $position, $now): array {
            $attempt = $this->attempt($userId, $workspaceId, $attemptId, true);
            if ($attempt['status'] !== 'in_progress') {
                throw new PlatformException('attempt_not_in_progress', 'Only an in-progress attempt can reveal an answer.', 409);
            }
            if (!in_array((string) $attempt['mode'], ['learning', 'practice'], true)) {
                // A real sitting cannot become a practice run halfway through:
                // assessment attempts never reveal, in any form, before
                // submission.
                throw new PlatformException('attempt_not_learning', 'This attempt was not started in a mode that reveals answers.', 409);
            }
            $assessment = $this->publishedAssessment($workspaceId, (string) $attempt['assessment_id']);
            $decision = $this->authorizer->decide($userId, 'exam.take', 'assessment', (string) $assessment['scope_id'], $workspaceId);
            if (!$decision->allowed) {
                throw new PlatformException('assessment_access_denied', 'Assessment access was denied.', 403);
            }
            if ((bool) $assessment['requires_entitlement'] && !$this->entitlements->has($userId, $workspaceId, (string) $assessment['target_scope_id'])) {
                throw new PlatformException('entitlement_required', 'An active entitlement is required for this assessment.', 403);
            }

            $definition = json_decode((string) $attempt['definition_json'], true, 64, JSON_THROW_ON_ERROR);
            $questionIds = array_map(static fn (array $question): string => (string) $question['id'], $definition['questions']);
            $questionCount = count($questionIds);
            if ($position < 1 || $position > $questionCount) {
                throw new PlatformException('question_position_invalid', 'Question position is out of range.', 422);
            }

            // Everything above is a pure read. From here a refusal must not
            // throw until after this transaction commits -- see
            // ExamQuestionRateGuard's docblock.
            if (!$this->questionRateGuard->consume($userId, $now)) {
                return ['allowed' => false];
            }

            $order = ExamAttemptShuffle::questionOrder($attemptId, $questionIds);
            $questionId = $order[$position - 1];
            $question = $this->questionById($definition, $questionId);

            $revealed = json_decode((string) ($attempt['revealed_json'] ?? '[]'), true, 16, JSON_THROW_ON_ERROR);
            $revealed = is_array($revealed) ? array_values(array_unique(array_map('intval', $revealed))) : [];
            if (!in_array($position, $revealed, true)) {
                $revealed[] = $position;
                sort($revealed);
                $this->execute(
                    'UPDATE exam_attempts SET revealed_json = :revealed WHERE id = :attempt AND workspace_id = :workspace AND user_id = :user',
                    ['revealed' => json_encode($revealed, JSON_THROW_ON_ERROR), 'attempt' => $attemptId, 'workspace' => $workspaceId, 'user' => $userId],
                );
            }

            $this->audit->record($workspaceId, $userId, 'exam.answer.revealed', 'exam_attempt', $attemptId, 'success', [
                'question_id' => $questionId, 'position' => $position,
            ]);

            return [
                'allowed' => true, 'position' => $position, 'question_count' => $questionCount,
                'question_id' => $questionId, 'answer' => (int) $question['answer'],
                'explanation' => $question['explanation'] ?? null, 'revealed' => $revealed,
            ];
        });

        if ($outcome['allowed'] === false) {
            throw new PlatformException('question_read_rate_limited', 'Slow down before revealing the next answer.', 429);
        }
        unset($outcome['allowed']);

        return $outcome;
    }

    /**
     * Serves exactly one question of an in-progress attempt the caller owns,
     * paced by the shared per-user token bucket. Never the answer or
     * explanation (the definition()-validated question set never leaves this
     * class with those fields intact before submission -- AGENTS.md §8).
     * Question and choice order are permuted deterministically
     * per attempt (ExamAttemptShuffle) so a resumed attempt sees the exact
     * same order every time, and a leaked set of reads carries the specific
     * permutation of the attempt it came from.
     *
     * @return array{position:int,question_count:int,question:array<string,mixed>}
     */
    public function readQuestion(string $userId, string $workspaceId, string $attemptId, int $position, ?int $now = null): array
    {
        $outcome = Transaction::run($this->database, function () use ($userId, $workspaceId, $attemptId, $position, $now): array {
            $attempt = $this->attempt($userId, $workspaceId, $attemptId, false);
            if ($attempt['status'] !== 'in_progress') {
                throw new PlatformException('attempt_not_in_progress', 'Only an in-progress attempt exposes questions.', 409);
            }
            $assessment = $this->publishedAssessment($workspaceId, (string) $attempt['assessment_id']);
            $decision = $this->authorizer->decide($userId, 'exam.take', 'assessment', (string) $assessment['scope_id'], $workspaceId);
            if (!$decision->allowed) {
                throw new PlatformException('assessment_access_denied', 'Assessment access was denied.', 403);
            }
            if ((bool) $assessment['requires_entitlement'] && !$this->entitlements->has($userId, $workspaceId, (string) $assessment['target_scope_id'])) {
                throw new PlatformException('entitlement_required', 'An active entitlement is required for this assessment.', 403);
            }

            $definition = json_decode((string) $attempt['definition_json'], true, 64, JSON_THROW_ON_ERROR);
            $questionIds = array_map(static fn (array $question): string => (string) $question['id'], $definition['questions']);
            $questionCount = count($questionIds);
            if ($position < 1 || $position > $questionCount) {
                throw new PlatformException('question_position_invalid', 'Question position is out of range.', 422);
            }

            // Everything above is a pure read; nothing has been written yet,
            // so throwing from any of those checks is safe. From here on a
            // refusal must not throw until after this transaction commits --
            // see ExamQuestionRateGuard's docblock.
            if (!$this->questionRateGuard->consume($userId, $now)) {
                return ['allowed' => false];
            }

            $order = ExamAttemptShuffle::questionOrder($attemptId, $questionIds);
            $questionId = $order[$position - 1];
            $question = $this->questionById($definition, $questionId);

            $this->audit->record($workspaceId, $userId, 'exam.question.read', 'exam_attempt', $attemptId, 'success', [
                'question_id' => $questionId, 'position' => $position,
            ]);

            return ['allowed' => true, 'position' => $position, 'question_count' => $questionCount, 'question' => $this->safeQuestion($question)];
        });

        if ($outcome['allowed'] === false) {
            throw new PlatformException('question_read_rate_limited', 'Slow down before reading the next question.', 429);
        }
        unset($outcome['allowed']);

        return $outcome;
    }

    /**
     * @param array<string, mixed> $answers
     * @return array{attempt_id:string,revision:int,status:string}
     */
    public function saveProgress(string $userId, string $workspaceId, string $attemptId, int $expectedRevision, array $answers, ?int $now = null): array
    {
        return Transaction::run($this->database, function () use ($userId, $workspaceId, $attemptId, $expectedRevision, $answers, $now): array {
            $attempt = $this->attempt($userId, $workspaceId, $attemptId, true);
            if ($attempt['status'] !== 'in_progress') {
                throw new PlatformException('attempt_state_conflict', 'Only an in-progress attempt can be saved.', 409);
            }
            if ($this->isExpired($attempt, $now)) {
                // Pencils down: a browser timer is a display, not a rule, so
                // the refusal to accept further edits is enforced here, from
                // the deadline this same attempt was given when it started
                // -- not from whatever the client's own clock claims.
                throw new PlatformException('attempt_deadline_passed', 'The time limit for this attempt has passed.', 409);
            }
            if ((int) $attempt['revision'] !== $expectedRevision) {
                throw new PlatformException('attempt_revision_conflict', 'Attempt revision is stale.', 409);
            }
            $definition = json_decode((string) $attempt['definition_json'], true, 64, JSON_THROW_ON_ERROR);
            $normalized = $this->answers($definition, $answers);
            $revision = $expectedRevision + 1;
            $this->execute(<<<'SQL'
UPDATE exam_attempts SET answers_json = :answers, revision = :revision
WHERE id = :attempt AND workspace_id = :workspace AND user_id = :user AND revision = :expected
SQL, [
                'answers' => json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'revision' => $revision, 'attempt' => $attemptId, 'workspace' => $workspaceId,
                'user' => $userId, 'expected' => $expectedRevision,
            ]);

            return ['attempt_id' => $attemptId, 'revision' => $revision, 'status' => 'in_progress'];
        });
    }

    /**
     * @param array<string, mixed> $answers
     * @return array<string, mixed>
     */
    public function submitAttempt(string $userId, string $workspaceId, string $attemptId, int $expectedRevision, array $answers, ?int $now = null): array
    {
        $outcome = Transaction::run($this->database, function () use ($userId, $workspaceId, $attemptId, $expectedRevision, $answers, $now): array {
            $attempt = $this->attempt($userId, $workspaceId, $attemptId, true);
            if ($attempt['status'] !== 'in_progress') {
                throw new PlatformException('attempt_state_conflict', 'Attempt is not open for submission.', 409);
            }
            if ($this->isExpired($attempt, $now)) {
                // The deadline already passed before this request arrived.
                // The attempt is closed honestly, using the answers already
                // saved while it was still open -- never the ones in this
                // late request, which the student had no right to still be
                // changing (saveProgress already refused them). The refusal
                // is thrown only after this transaction returns/commits; see
                // ExamQuestionRateGuard's docblock for why throwing from
                // inside would be wrong here.
                $lastAnswers = json_decode((string) ($attempt['answers_json'] ?? '{}'), true, 64, JSON_THROW_ON_ERROR);
                $this->scoreAndClose($workspaceId, $userId, $attemptId, $attempt, is_array($lastAnswers) ? $lastAnswers : [], true);

                return ['late' => true, 'result' => null];
            }
            if ((int) $attempt['revision'] !== $expectedRevision) {
                throw new PlatformException('attempt_revision_conflict', 'Attempt revision is stale.', 409);
            }

            return ['late' => false, 'result' => $this->scoreAndClose($workspaceId, $userId, $attemptId, $attempt, $answers, false)];
        });

        if ($outcome['late']) {
            throw new PlatformException('attempt_deadline_passed', 'The time limit for this attempt passed before this submission arrived; it was closed using your last saved answers.', 409);
        }

        return $outcome['result'];
    }

    /**
     * Scores an attempt and closes it, shared by an on-time submission and a
     * late one that submitAttempt() closes using the last saved answers
     * instead of the (refused) request's own. The attempt's own current
     * revision is always used for the write, never a caller-supplied one --
     * for the late path there is no caller-supplied revision to trust.
     *
     * @param array<string, mixed> $attempt
     * @param array<string, mixed> $answers
     * @return array<string, mixed>
     */
    private function scoreAndClose(string $workspaceId, string $userId, string $attemptId, array $attempt, array $answers, bool $late): array
    {
        $definition = json_decode((string) $attempt['definition_json'], true, 64, JSON_THROW_ON_ERROR);
        $normalized = $this->answers($definition, $answers);
        $review = [];
        $correct = 0;
        foreach ($definition['questions'] as $question) {
            $id = (string) $question['id'];
            $selected = $normalized[$id] ?? null;
            $isCorrect = $selected !== null && $selected === $question['answer'];
            $correct += $isCorrect ? 1 : 0;
            $review[] = [
                'id' => $id, 'selected' => $selected, 'correct' => $question['answer'],
                'is_correct' => $isCorrect, 'explanation' => $question['explanation'] ?? null,
            ];
        }
        $questionCount = count($definition['questions']);
        $score = $questionCount === 0 ? 0 : (int) round(($correct / $questionCount) * 10000);
        $currentRevision = (int) $attempt['revision'];
        $revision = $currentRevision + 1;
        $this->execute(<<<'SQL'
UPDATE exam_attempts
SET answers_json = :answers, revision = :revision, status = 'scored', submitted_at = UTC_TIMESTAMP(6)
WHERE id = :attempt AND workspace_id = :workspace AND user_id = :user AND revision = :expected
SQL, [
            'answers' => json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'revision' => $revision, 'attempt' => $attemptId, 'workspace' => $workspaceId,
            'user' => $userId, 'expected' => $currentRevision,
        ]);
        $this->execute(<<<'SQL'
INSERT INTO exam_attempt_results (
    workspace_id, attempt_id, correct_count, question_count, score_basis_points, review_json, computed_at
) VALUES (:workspace, :attempt, :correct, :questions, :score, :review, UTC_TIMESTAMP(6))
SQL, [
            'workspace' => $workspaceId, 'attempt' => $attemptId, 'correct' => $correct,
            'questions' => $questionCount, 'score' => $score,
            'review' => json_encode($review, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
        $this->audit->record($workspaceId, $userId, 'exam.attempt.scored', 'exam_attempt', $attemptId, 'success', [
            'assessment_id' => $attempt['assessment_id'], 'score_basis_points' => $score, 'late' => $late,
        ]);

        // answered_count is what lets a report tell "answered and wrong" apart
        // from "never answered". Both were previously folded into the same
        // "not correct" remainder, so a student who ran out of time and one who
        // guessed everything wrong saw the identical card. It is derived here
        // rather than in the client because the client has only its own local
        // answers, which an expiry-triggered scoring path never sees.
        $answered = count($normalized);

        return ['attempt_id' => $attemptId, 'revision' => $revision, 'status' => 'scored', 'correct_count' => $correct, 'answered_count' => $answered, 'question_count' => $questionCount, 'score_basis_points' => $score];
    }

    /** @param array<string, mixed> $attempt */
    private function isExpired(array $attempt, ?int $now): bool
    {
        if ($attempt['deadline_at'] === null) {
            return false;
        }
        $deadline = strtotime((string) $attempt['deadline_at'] . ' UTC');
        if ($deadline === false) {
            return false;
        }

        return ($now ?? time()) >= $deadline;
    }

    private function isoTimestamp(mixed $mysqlDatetime): ?string
    {
        if ($mysqlDatetime === null) {
            return null;
        }
        $timestamp = strtotime((string) $mysqlDatetime . ' UTC');

        return $timestamp === false ? null : gmdate(DATE_ATOM, $timestamp);
    }

    private function timeLimitMinutes(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $minutes = filter_var($value, FILTER_VALIDATE_INT);
        if ($minutes === false || $minutes < 1 || $minutes > 600) {
            throw new PlatformException('time_limit_invalid', 'Time limit must be between 1 and 600 minutes.', 422);
        }

        return $minutes;
    }

    /**
     * Scored-attempt summary only -- never the per-question review list.
     * Explanations and per-choice correctness are the most valuable part of
     * the product and are served one at a time by attemptReviewQuestion(),
     * through the same pacing and audit as readQuestion().
     *
     * @return array<string, mixed>
     */
    public function attemptReview(string $userId, string $workspaceId, string $attemptId): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT attempt.id, attempt.assessment_id, attempt.revision, attempt.status, attempt.mode,
       JSON_LENGTH(COALESCE(attempt.revealed_json, JSON_ARRAY())) AS revealed_count,
       result.correct_count, result.question_count, result.score_basis_points
FROM exam_attempts attempt
JOIN exam_attempt_results result ON result.attempt_id = attempt.id AND result.workspace_id = attempt.workspace_id
WHERE attempt.id = :attempt AND attempt.workspace_id = :workspace AND attempt.user_id = :user
SQL);
        $query->execute(['attempt' => $attemptId, 'workspace' => $workspaceId, 'user' => $userId]);
        $row = $query->fetch();
        if ($row === false) {
            throw new PlatformException('attempt_not_found', 'Scored attempt was not found.', 404);
        }

        return $row;
    }

    /**
     * One reviewed question -- selection, correct choice and explanation --
     * of a scored attempt the caller owns, paced by the same token bucket
     * readQuestion() uses. Position follows the identical per-attempt order
     * ExamAttemptShuffle produced while the attempt was in progress, so
     * "question 3" means the same thing before and after submission -- and so
     * do its choice positions: `selected` and `correct` are indices into the
     * returned `choices`, which are in the same displayed order the student
     * answered against, never the canonical authored order.
     *
     * @return array{position:int,question_count:int,question_id:string,prompt:string,choices:list<string>,selected:?int,correct:int,is_correct:bool,explanation:?string}
     */
    public function attemptReviewQuestion(string $userId, string $workspaceId, string $attemptId, int $position, ?int $now = null): array
    {
        $outcome = Transaction::run($this->database, function () use ($userId, $workspaceId, $attemptId, $position, $now): array {
            $scored = $this->scoredAttemptWithDefinition($userId, $workspaceId, $attemptId);
            $review = json_decode((string) $scored['review_json'], true, 64, JSON_THROW_ON_ERROR);
            $questionCount = count($review);
            if ($position < 1 || $position > $questionCount) {
                throw new PlatformException('question_position_invalid', 'Question position is out of range.', 422);
            }

            if (!$this->questionRateGuard->consume($userId, $now)) {
                return ['allowed' => false];
            }

            $definition = json_decode((string) $scored['definition_json'], true, 64, JSON_THROW_ON_ERROR);
            $questionIds = array_map(static fn (array $question): string => (string) $question['id'], $definition['questions']);
            $order = ExamAttemptShuffle::questionOrder($attemptId, $questionIds);
            $questionId = $order[$position - 1];
            $byId = array_column($review, null, 'id');
            $entry = $byId[$questionId] ?? null;
            if ($entry === null) {
                throw new PlatformException('question_not_found', 'Question was not found.', 404);
            }

            $this->audit->record($workspaceId, $userId, 'exam.explanation.read', 'exam_attempt', $attemptId, 'success', [
                'question_id' => $questionId, 'position' => $position,
            ]);

            // The review returns the question itself, not just indices: the
            // client has no other way to obtain the choices, since
            // readQuestion refuses once the attempt is no longer in progress.
            $question = $this->questionById($definition, $questionId);

            // A question whose answer was revealed during a learning
            // attempt is still scored on what the student chose, but the
            // review has to say so -- a report that presents a seen answer
            // as an earned one is telling the student something untrue about
            // what they know.
            $revealed = json_decode((string) ($scored['revealed_json'] ?? '[]'), true, 16, JSON_THROW_ON_ERROR);
            $revealed = is_array($revealed) ? array_map('intval', $revealed) : [];

            return [
                'allowed' => true, 'position' => $position, 'question_count' => $questionCount,
                'question_id' => $questionId, 'prompt' => $question['prompt'],
                'choices' => $question['choices'],
                'selected' => $entry['selected'], 'correct' => $entry['correct'],
                'is_correct' => $entry['is_correct'], 'explanation' => $entry['explanation'],
                'was_revealed' => in_array($position, $revealed, true),
            ];
        });

        if ($outcome['allowed'] === false) {
            throw new PlatformException('question_read_rate_limited', 'Slow down before reading the next explanation.', 429);
        }
        unset($outcome['allowed']);

        return $outcome;
    }

    /**
     * تاریخچه‌ی تلاش‌ها: this student's own past scored attempts at one
     * assessment, newest first -- reachable from the assessment's intro
     * card. Only aggregate outcome per attempt (score, date, mode); the
     * per-question review of any one of them still goes through
     * attemptReview()/attemptReviewQuestion() the same as always.
     *
     * @return list<array{attempt_id:string,mode:string,submitted_at:?string,correct_count:int,question_count:int,score_basis_points:int}>
     */
    public function attemptHistory(string $userId, string $workspaceId, string $assessmentId): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        $query = $this->database->prepare(<<<'SQL'
SELECT attempt.id AS attempt_id, attempt.mode, attempt.submitted_at,
       result.correct_count, result.question_count, result.score_basis_points
FROM exam_attempts attempt
JOIN exam_attempt_results result ON result.attempt_id = attempt.id AND result.workspace_id = attempt.workspace_id
WHERE attempt.workspace_id = :workspace AND attempt.assessment_id = :assessment
  AND attempt.user_id = :user AND attempt.status = 'scored'
ORDER BY attempt.submitted_at DESC
SQL);
        $query->execute(['workspace' => $workspaceId, 'assessment' => $assessmentId, 'user' => $userId]);

        $attempts = [];
        while (($row = $query->fetch()) !== false) {
            $attempts[] = [
                'attempt_id' => (string) $row['attempt_id'],
                'mode' => (string) $row['mode'],
                'submitted_at' => $this->isoTimestamp($row['submitted_at']),
                'correct_count' => (int) $row['correct_count'],
                'question_count' => (int) $row['question_count'],
                'score_basis_points' => (int) $row['score_basis_points'],
            ];
        }

        return $attempts;
    }

    /** @return array{assessment_id:string,attempt_count:int,average_score_basis_points:int,min_score_basis_points:int,max_score_basis_points:int} */
    public function analytics(string $actorUserId, string $workspaceId, string $assessmentId): array
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'exam.manage');
        $exists = $this->database->prepare('SELECT 1 FROM exam_assessments WHERE id = :assessment AND workspace_id = :workspace AND archived_at IS NULL');
        $exists->execute(['assessment' => $assessmentId, 'workspace' => $workspaceId]);
        if ($exists->fetchColumn() === false) {
            throw new PlatformException('assessment_not_found', 'Assessment was not found.', 404);
        }
        $query = $this->database->prepare(<<<'SQL'
SELECT COUNT(*) AS attempt_count,
       COALESCE(ROUND(AVG(result.score_basis_points)), 0) AS average_score,
       COALESCE(MIN(result.score_basis_points), 0) AS minimum_score,
       COALESCE(MAX(result.score_basis_points), 0) AS maximum_score
FROM exam_attempts attempt
JOIN exam_attempt_results result ON result.attempt_id = attempt.id AND result.workspace_id = attempt.workspace_id
WHERE attempt.workspace_id = :workspace AND attempt.assessment_id = :assessment AND attempt.status = 'scored'
SQL);
        $query->execute(['workspace' => $workspaceId, 'assessment' => $assessmentId]);
        $row = $query->fetch();

        return [
            'assessment_id' => $assessmentId,
            'attempt_count' => (int) ($row['attempt_count'] ?? 0),
            'average_score_basis_points' => (int) ($row['average_score'] ?? 0),
            'min_score_basis_points' => (int) ($row['minimum_score'] ?? 0),
            'max_score_basis_points' => (int) ($row['maximum_score'] ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private function publishedAssessment(string $workspaceId, string $assessmentId): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT assessment.id, assessment.title, version.id AS version_id, version.definition_json,
       scope.id AS scope_id, policy.target_scope_id, policy.requires_entitlement, policy.max_attempts,
       policy.time_limit_minutes
FROM exam_assessments assessment
JOIN exam_assessment_versions version ON version.assessment_id = assessment.id
 AND version.workspace_id = assessment.workspace_id AND version.version_no = assessment.current_version_no
JOIN exam_version_states state ON state.assessment_version_id = version.id
 AND state.workspace_id = version.workspace_id AND state.status = 'approved'
JOIN rbac_scopes scope ON scope.scope_type = 'assessment' AND scope.entity_id = assessment.id
 AND scope.workspace_id = assessment.workspace_id AND scope.archived_at IS NULL
JOIN exam_access_policies policy ON policy.assessment_id = assessment.id AND policy.workspace_id = assessment.workspace_id
LEFT JOIN academic_course_offerings offering ON offering.id = assessment.offering_id AND offering.workspace_id = assessment.workspace_id
LEFT JOIN academic_terms term ON term.id = offering.term_id AND term.workspace_id = offering.workspace_id
LEFT JOIN exam_assessment_metadata metadata ON metadata.assessment_id = assessment.id AND metadata.workspace_id = assessment.workspace_id
LEFT JOIN academic_courses course ON course.id = metadata.course_id AND course.workspace_id = assessment.workspace_id
WHERE assessment.id = :assessment AND assessment.workspace_id = :workspace
 AND assessment.status = 'published' AND assessment.archived_at IS NULL
 AND (assessment.offering_id IS NULL OR (offering.status <> 'archived' AND offering.archived_at IS NULL))
 AND (assessment.offering_id IS NULL OR (term.status <> 'archived' AND term.archived_at IS NULL))
 AND (metadata.course_id IS NULL OR (course.status = 'active' AND course.archived_at IS NULL))
LIMIT 1
SQL);
        $query->execute(['assessment' => $assessmentId, 'workspace' => $workspaceId]);
        $row = $query->fetch();
        if ($row === false) {
            throw new PlatformException('assessment_not_found', 'Published assessment was not found.', 404);
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function attempt(string $userId, string $workspaceId, string $attemptId, bool $lock): array
    {
        $lockClause = $lock ? 'FOR UPDATE' : '';
        $query = $this->database->prepare(<<<SQL
SELECT attempt.id, attempt.assessment_id, attempt.status, attempt.mode, attempt.revision,
       attempt.answers_json, attempt.revealed_json, attempt.deadline_at, version.definition_json
FROM exam_attempts attempt
JOIN exam_assessment_versions version ON version.id = attempt.assessment_version_id
 AND version.assessment_id = attempt.assessment_id AND version.workspace_id = attempt.workspace_id
WHERE attempt.id = :attempt AND attempt.workspace_id = :workspace AND attempt.user_id = :user
{$lockClause}
SQL);
        $query->execute(['attempt' => $attemptId, 'workspace' => $workspaceId, 'user' => $userId]);
        $row = $query->fetch();
        if ($row === false) {
            throw new PlatformException('attempt_not_found', 'Assessment attempt was not found.', 404);
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function versionState(string $workspaceId, string $assessmentId, string $versionId, bool $lock): array
    {
        $lockClause = $lock ? 'FOR UPDATE' : '';
        $query = $this->database->prepare(<<<SQL
SELECT state.status, version.version_no, version.created_by_user_id
FROM exam_version_states state
JOIN exam_assessment_versions version ON version.id = state.assessment_version_id
 AND version.assessment_id = state.assessment_id AND version.workspace_id = state.workspace_id
WHERE state.workspace_id = :workspace AND state.assessment_id = :assessment
 AND state.assessment_version_id = :version
{$lockClause}
SQL);
        $query->execute(['workspace' => $workspaceId, 'assessment' => $assessmentId, 'version' => $versionId]);
        $row = $query->fetch();
        if ($row === false) {
            throw new PlatformException('assessment_version_not_found', 'Assessment version was not found.', 404);
        }

        return $row;
    }

    private function insertVersion(string $workspaceId, string $assessmentId, string $versionId, int $versionNo, string $actorUserId, string $definitionJson): void
    {
        $checksum = hash('sha256', $definitionJson, true);
        $this->execute(<<<'SQL'
INSERT INTO exam_assessment_versions (
    id, workspace_id, assessment_id, version_no, definition_json,
    checksum_sha256, created_by_user_id, created_at
) VALUES (
    :id, :workspace, :assessment, :version_no, :definition,
    :checksum, :creator, UTC_TIMESTAMP(6)
)
SQL, [
            'id' => $versionId, 'workspace' => $workspaceId, 'assessment' => $assessmentId,
            'version_no' => $versionNo, 'definition' => $definitionJson,
            'checksum' => $checksum, 'creator' => $actorUserId,
        ]);
        $this->execute(<<<'SQL'
INSERT INTO exam_version_states (workspace_id, assessment_id, assessment_version_id, status, updated_at)
VALUES (:workspace, :assessment, :version, 'draft', UTC_TIMESTAMP(6))
SQL, ['workspace' => $workspaceId, 'assessment' => $assessmentId, 'version' => $versionId]);
    }

    /** @param array<string, mixed> $definition */
    private function definition(array $definition): string
    {
        $questions = $definition['questions'] ?? null;
        if (!is_array($questions) || !array_is_list($questions) || count($questions) < 1 || count($questions) > 500) {
            throw new PlatformException('question_set_invalid', 'Assessment must contain between 1 and 500 questions.', 422);
        }
        $ids = [];
        foreach ($questions as $index => $question) {
            if (!is_array($question)) {
                throw new PlatformException('question_invalid', "Question {$index} is invalid.", 422);
            }
            $id = (string) ($question['id'] ?? '');
            if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,63}$/', $id) || isset($ids[$id])) {
                throw new PlatformException('question_id_invalid', 'Question identifiers must be unique stable keys.', 422);
            }
            $ids[$id] = true;
            $normalized = [
                'id' => $id,
                'prompt' => $this->text((string) ($question['prompt'] ?? ''), 4000, 'question_prompt_invalid'),
            ];
            $choices = $question['choices'] ?? null;
            if (!is_array($choices) || !array_is_list($choices) || count($choices) < 2 || count($choices) > 10) {
                throw new PlatformException('question_choices_invalid', 'Each question must contain between 2 and 10 choices.', 422);
            }
            foreach ($choices as $choiceIndex => $choice) {
                if (!is_string($choice)) {
                    throw new PlatformException('question_choice_invalid', 'Question choices must be text.', 422);
                }
                $normalized['choices'][$choiceIndex] = $this->text($choice, 1000, 'question_choice_invalid');
            }
            $answer = filter_var($question['answer'] ?? null, FILTER_VALIDATE_INT);
            if ($answer === false || $answer < 0 || $answer >= count($choices)) {
                throw new PlatformException('question_answer_invalid', 'Correct choice index is invalid.', 422);
            }
            $normalized['answer'] = $answer;
            if (isset($question['explanation']) && $question['explanation'] !== null && $question['explanation'] !== '') {
                $normalized['explanation'] = $this->text((string) $question['explanation'], 4000, 'question_explanation_invalid');
            } else {
                $normalized['explanation'] = null;
            }
            if (array_key_exists('topic', $question) && $question['topic'] !== null && $question['topic'] !== '') {
                $normalized['topic'] = $this->text((string) $question['topic'], 200, 'question_topic_invalid');
            }
            if (array_key_exists('difficulty', $question) && $question['difficulty'] !== null && $question['difficulty'] !== '') {
                $difficulty = strtolower($this->text((string) $question['difficulty'], 32, 'question_difficulty_invalid'));
                if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
                    throw new PlatformException('question_difficulty_invalid', 'Question difficulty is invalid.', 422);
                }
                $normalized['difficulty'] = $difficulty;
            }
            if (array_key_exists('tags', $question) && $question['tags'] !== null) {
                if (!is_array($question['tags']) || !array_is_list($question['tags']) || count($question['tags']) > 12) {
                    throw new PlatformException('question_tags_invalid', 'Question tags are invalid.', 422);
                }
                $tags = [];
                foreach ($question['tags'] as $tag) {
                    if (!is_string($tag) || trim($tag) === '') {
                        throw new PlatformException('question_tags_invalid', 'Question tags are invalid.', 422);
                    }
                    $cleanTag = $this->text($tag, 64, 'question_tags_invalid');
                    if (!in_array($cleanTag, $tags, true)) $tags[] = $cleanTag;
                }
                $normalized['tags'] = $tags;
            }
            if (array_key_exists('provenance', $question) && $question['provenance'] !== null) {
                if (!is_array($question['provenance'])) {
                    throw new PlatformException('question_provenance_invalid', 'Question provenance is invalid.', 422);
                }
                $provenance = [];
                foreach (['source_question_id' => 128, 'source_locator' => 240, 'note' => 500] as $key => $limit) {
                    if (array_key_exists($key, $question['provenance']) && $question['provenance'][$key] !== null && $question['provenance'][$key] !== '') {
                        $provenance[$key] = $this->text((string) $question['provenance'][$key], $limit, 'question_provenance_invalid');
                    }
                }
                if ($provenance !== []) $normalized['provenance'] = $provenance;
            }
            $questions[$index] = $normalized;
        }
        $definition['questions'] = $questions;

        return ContentPayload::encode($definition);
    }

    /**
     * The never-answer-before-submission shape (AGENTS.md §8): id, prompt,
     * choices, and whichever optional descriptive fields the question
     * carries -- never `answer` or `explanation`. This is the one place that
     * builds it; readQuestion() and startAttempt()'s bonus first-question
     * both call it rather than each shaping their own copy, so the guarantee
     * cannot drift between the two call sites.
     *
     * @param array<string, mixed> $question
     * @return array<string, mixed>
     */
    private function safeQuestion(array $question): array
    {
        $safe = ['id' => $question['id'], 'prompt' => $question['prompt'], 'choices' => $question['choices']];
        foreach (['topic', 'tags', 'difficulty', 'provenance'] as $key) {
            if (array_key_exists($key, $question)) {
                $safe[$key] = $question[$key];
            }
        }

        return $safe;
    }

    /** @param array<string, mixed> $definition @return array<string, mixed> */
    private function questionById(array $definition, string $questionId): array
    {
        foreach ($definition['questions'] as $question) {
            if ((string) $question['id'] === $questionId) {
                return $question;
            }
        }
        throw new PlatformException('question_not_found', 'Question was not found.', 404);
    }

    /** @return array<string, mixed> */
    private function scoredAttemptWithDefinition(string $userId, string $workspaceId, string $attemptId): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT attempt.id, attempt.assessment_id, attempt.mode, attempt.revealed_json, result.review_json, version.definition_json
FROM exam_attempts attempt
JOIN exam_attempt_results result ON result.attempt_id = attempt.id AND result.workspace_id = attempt.workspace_id
JOIN exam_assessment_versions version ON version.id = attempt.assessment_version_id
 AND version.assessment_id = attempt.assessment_id AND version.workspace_id = attempt.workspace_id
WHERE attempt.id = :attempt AND attempt.workspace_id = :workspace AND attempt.user_id = :user
SQL);
        $query->execute(['attempt' => $attemptId, 'workspace' => $workspaceId, 'user' => $userId]);
        $row = $query->fetch();
        if ($row === false) {
            throw new PlatformException('attempt_not_found', 'Scored attempt was not found.', 404);
        }

        return $row;
    }

    /** @param array<string, mixed> $definition @param array<string, mixed> $answers @return array<string, int> */
    private function answers(array $definition, array $answers): array
    {
        $allowed = [];
        foreach ($definition['questions'] as $question) {
            $allowed[(string) $question['id']] = count($question['choices']);
        }
        $normalized = [];
        foreach ($answers as $id => $answer) {
            if (!is_string($id) || !isset($allowed[$id])) {
                throw new PlatformException('answer_question_invalid', 'Answer references an unknown question.', 422);
            }
            $choice = filter_var($answer, FILTER_VALIDATE_INT);
            if ($choice === false || $choice < 0 || $choice >= $allowed[$id]) {
                throw new PlatformException('answer_choice_invalid', 'Answer choice index is invalid.', 422);
            }
            $normalized[$id] = $choice;
        }
        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    /** @param array<string, mixed> $metadata @return array{course_id:?string,offering_id:?string,source_resource_id:?string} */
    private function academic(string $workspaceId, array $metadata): array
    {
        $course = $this->uuid($metadata['course_id'] ?? null, 'course_id_invalid');
        $offering = $this->uuid($metadata['offering_id'] ?? null, 'offering_id_invalid');
        $source = $this->uuid($metadata['source_resource_id'] ?? null, 'source_resource_id_invalid');
        if ($offering !== null) {
            $query = $this->database->prepare("SELECT course_id FROM academic_course_offerings WHERE id = :offering AND workspace_id = :workspace AND status <> 'archived' AND archived_at IS NULL");
            $query->execute(['offering' => $offering, 'workspace' => $workspaceId]);
            $foundCourse = $query->fetchColumn();
            if ($foundCourse === false || ($course !== null && !hash_equals($course, (string) $foundCourse))) {
                throw new PlatformException('offering_metadata_mismatch', 'Offering does not belong to the selected course/workspace.', 422);
            }
            $course ??= (string) $foundCourse;
        } elseif ($course !== null) {
            $this->workspaceEntity('academic_courses', $course, $workspaceId, 'course_not_found');
        }
        if ($source !== null) {
            $this->workspaceEntity('content_resources', $source, $workspaceId, 'source_resource_not_found');
        }

        return ['course_id' => $course, 'offering_id' => $offering, 'source_resource_id' => $source];
    }

    private function targetScope(string $workspaceId, mixed $value): string
    {
        $scope = $this->uuid($value, 'target_scope_invalid') ?? $this->workspaceScope($workspaceId);
        $query = $this->database->prepare('SELECT 1 FROM rbac_scopes WHERE id = :scope AND workspace_id = :workspace AND archived_at IS NULL');
        $query->execute(['scope' => $scope, 'workspace' => $workspaceId]);
        if ($query->fetchColumn() === false) {
            throw new PlatformException('target_scope_not_found', 'Assessment target scope was not found in this workspace.', 422);
        }

        return $scope;
    }

    private function workspaceScope(string $workspaceId): string
    {
        $query = $this->database->prepare("SELECT id FROM rbac_scopes WHERE scope_type = 'workspace' AND entity_id = :entity_workspace AND workspace_id = :workspace AND archived_at IS NULL");
        $query->execute(['entity_workspace' => $workspaceId, 'workspace' => $workspaceId]);
        $scope = $query->fetchColumn();
        if ($scope === false) {
            throw new PlatformException('workspace_scope_missing', 'Workspace scope is unavailable.', 409);
        }

        return (string) $scope;
    }

    /** @param array<string, scalar|null> $payload */
    private function outbox(string $workspaceId, string $aggregateType, string $aggregateId, string $eventType, array $payload): void
    {
        $this->execute(<<<'SQL'
INSERT INTO outbox_events (
    id, scope_type, workspace_id, aggregate_type, aggregate_id, event_type,
    payload_json, occurred_at, available_at
) VALUES (
    :id, 'workspace', :workspace, :aggregate_type, :aggregate_id, :event_type,
    :payload, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
)
SQL, [
            'id' => Uuid::v7(), 'workspace' => $workspaceId, 'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId, 'event_type' => $eventType,
            'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
    }

    private function workspaceEntity(string $table, string $id, string $workspaceId, string $errorCode): void
    {
        if (!in_array($table, ['academic_courses', 'content_resources'], true)) {
            throw new \LogicException('Unsupported exam metadata entity.');
        }
        $lifecycle = $table === 'academic_courses' ? " AND status = 'active' AND archived_at IS NULL" : '';
        $query = $this->database->prepare("SELECT 1 FROM {$table} WHERE id = :id AND workspace_id = :workspace{$lifecycle} LIMIT 1");
        $query->execute(['id' => $id, 'workspace' => $workspaceId]);
        if ($query->fetchColumn() === false) {
            throw new PlatformException($errorCode, 'Assessment metadata does not belong to this workspace.', 422);
        }
    }

    private function text(string $value, int $maximum, string $errorCode): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $maximum || preg_match('/<\?(?:php|=)|<script\b|javascript\s*:/i', $value)) {
            throw new PlatformException($errorCode, 'Text value is invalid.', 422);
        }

        return $value;
    }

    private function uuid(mixed $value, string $errorCode): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value)) {
            throw new PlatformException($errorCode, 'Identifier must be a UUID.', 422);
        }

        return strtolower($value);
    }

    /** @param array<string, scalar|null> $parameters */
    private function execute(string $sql, array $parameters): void
    {
        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);
    }
}
