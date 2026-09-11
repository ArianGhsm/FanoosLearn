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
        $kind = (string) ($metadata['assessment_kind'] ?? 'practice');
        if (!in_array($kind, ['practice', 'mock_exam', 'past_exam'], true)) {
            throw new PlatformException('assessment_kind_invalid', 'Assessment kind is invalid.', 422);
        }
        $definitionJson = $this->definition($definition);
        $academic = $this->academic($workspaceId, $metadata);
        $scopeId = $this->targetScope($workspaceId, $metadata['target_scope_id'] ?? null);
        $maxAttempts = filter_var($metadata['max_attempts'] ?? 3, FILTER_VALIDATE_INT);
        if ($maxAttempts === false || $maxAttempts < 1 || $maxAttempts > 100) {
            throw new PlatformException('max_attempts_invalid', 'Maximum attempts must be between 1 and 100.', 422);
        }
        $assessmentId = Uuid::v7();
        $versionId = Uuid::v7();
        $assessmentScopeId = Uuid::v7();

        Transaction::run($this->database, function () use (
            $actorUserId, $workspaceId, $title, $kind, $definitionJson, $academic,
            $scopeId, $maxAttempts, $assessmentId, $versionId, $assessmentScopeId, $metadata,
        ): void {
            $this->execute(<<<'SQL'
INSERT INTO exam_assessments (
    id, workspace_id, offering_id, title, status, current_version_no, created_at, updated_at
) VALUES (:id, :workspace, :offering, :title, 'draft', 0, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL, ['id' => $assessmentId, 'workspace' => $workspaceId, 'offering' => $academic['offering_id'], 'title' => $title]);
            $this->insertVersion($workspaceId, $assessmentId, $versionId, 1, $actorUserId, $definitionJson);
            $this->execute(<<<'SQL'
INSERT INTO exam_assessment_metadata (
    workspace_id, assessment_id, assessment_kind, course_id, source_resource_id, created_at, updated_at
) VALUES (:workspace, :assessment, :kind, :course, :source, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL, [
                'workspace' => $workspaceId, 'assessment' => $assessmentId, 'kind' => $kind,
                'course' => $academic['course_id'], 'source' => $academic['source_resource_id'],
            ]);
            $this->execute(<<<'SQL'
INSERT INTO exam_access_policies (
    workspace_id, assessment_id, target_scope_id, requires_entitlement, max_attempts, updated_at
) VALUES (:workspace, :assessment, :scope, :entitled, :max_attempts, UTC_TIMESTAMP(6))
SQL, [
                'workspace' => $workspaceId, 'assessment' => $assessmentId, 'scope' => $scopeId,
                'entitled' => (bool) ($metadata['requires_entitlement'] ?? false) ? 1 : 0,
                'max_attempts' => $maxAttempts,
            ]);
            $workspaceScope = $this->workspaceScope($workspaceId);
            $this->execute(<<<'SQL'
INSERT INTO rbac_scopes (id, scope_type, entity_id, workspace_id, parent_scope_id, created_at)
VALUES (:id, 'assessment', :assessment, :workspace, :parent, UTC_TIMESTAMP(6))
SQL, ['id' => $assessmentScopeId, 'assessment' => $assessmentId, 'workspace' => $workspaceId, 'parent' => $workspaceScope]);
            $this->audit->record($workspaceId, $actorUserId, 'exam.assessment.created', 'exam_assessment', $assessmentId, 'success', ['kind' => $kind, 'version_no' => 1]);
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
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'exam.take');
        $where = ["assessment.workspace_id = :workspace", "assessment.status = 'published'", 'assessment.archived_at IS NULL'];
        $parameters = ['workspace' => $workspaceId];
        if ($courseId !== null && $courseId !== '') {
            $where[] = 'metadata.course_id = :course';
            $parameters['course'] = $courseId;
        }
        if ($kind !== null && $kind !== '') {
            $where[] = 'metadata.assessment_kind = :kind';
            $parameters['kind'] = $kind;
        }
        $query = $this->database->prepare(sprintf(<<<'SQL'
SELECT assessment.id, assessment.title, assessment.current_version_no,
       metadata.assessment_kind, metadata.course_id, course.course_code, course.title AS course_title,
       term.id AS term_id, term.term_key, term.name AS term_name,
       policy.requires_entitlement, policy.max_attempts
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
LIMIT 100
SQL, implode(' AND ', $where)));
        $query->execute($parameters);

        return $query->fetchAll();
    }

    /** @return array<string, mixed> */
    public function startAttempt(string $userId, string $workspaceId, string $assessmentId): array
    {
        $assessment = $this->publishedAssessment($workspaceId, $assessmentId);
        $decision = $this->authorizer->decide($userId, 'exam.take', 'assessment', (string) $assessment['scope_id'], $workspaceId);
        if (!$decision->allowed) {
            throw new PlatformException('assessment_access_denied', 'Assessment access was denied.', 403);
        }
        if ((bool) $assessment['requires_entitlement'] && !$this->entitlements->has($userId, $workspaceId, (string) $assessment['target_scope_id'])) {
            throw new PlatformException('entitlement_required', 'An active entitlement is required for this assessment.', 403);
        }
        $attempts = $this->database->prepare("SELECT COUNT(*) FROM exam_attempts WHERE workspace_id = :workspace AND assessment_id = :assessment AND user_id = :user AND status IN ('submitted', 'scored')");
        $attempts->execute(['workspace' => $workspaceId, 'assessment' => $assessmentId, 'user' => $userId]);
        if ((int) $attempts->fetchColumn() >= (int) $assessment['max_attempts']) {
            throw new PlatformException('attempt_limit_reached', 'Assessment attempt limit has been reached.', 409);
        }
        $attemptId = Uuid::v7();
        $this->execute(<<<'SQL'
INSERT INTO exam_attempts (
    id, workspace_id, assessment_id, assessment_version_id, user_id,
    status, revision, answers_json, started_at
) VALUES (
    :id, :workspace, :assessment, :version, :user,
    'in_progress', 1, JSON_OBJECT(), UTC_TIMESTAMP(6)
)
SQL, [
            'id' => $attemptId, 'workspace' => $workspaceId, 'assessment' => $assessmentId,
            'version' => $assessment['version_id'], 'user' => $userId,
        ]);
        $definition = json_decode((string) $assessment['definition_json'], true, 64, JSON_THROW_ON_ERROR);
        $this->audit->record($workspaceId, $userId, 'exam.attempt.started', 'exam_attempt', $attemptId, 'success', ['assessment_id' => $assessmentId, 'version_id' => $assessment['version_id']]);

        return [
            'attempt_id' => $attemptId, 'revision' => 1, 'status' => 'in_progress',
            'assessment_id' => $assessmentId, 'title' => (string) $assessment['title'],
            'questions' => $this->safeQuestions($definition),
        ];
    }

    /** @param array<string, mixed> $answers @return array{attempt_id:string,revision:int,status:string} */
    public function saveProgress(string $userId, string $workspaceId, string $attemptId, int $expectedRevision, array $answers): array
    {
        return Transaction::run($this->database, function () use ($userId, $workspaceId, $attemptId, $expectedRevision, $answers): array {
            $attempt = $this->attempt($userId, $workspaceId, $attemptId, true);
            if ($attempt['status'] !== 'in_progress') {
                throw new PlatformException('attempt_state_conflict', 'Only an in-progress attempt can be saved.', 409);
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

    /** @param array<string, mixed> $answers @return array<string, mixed> */
    public function submitAttempt(string $userId, string $workspaceId, string $attemptId, int $expectedRevision, array $answers): array
    {
        return Transaction::run($this->database, function () use ($userId, $workspaceId, $attemptId, $expectedRevision, $answers): array {
            $attempt = $this->attempt($userId, $workspaceId, $attemptId, true);
            if ($attempt['status'] !== 'in_progress') {
                throw new PlatformException('attempt_state_conflict', 'Attempt is not open for submission.', 409);
            }
            if ((int) $attempt['revision'] !== $expectedRevision) {
                throw new PlatformException('attempt_revision_conflict', 'Attempt revision is stale.', 409);
            }
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
            $revision = $expectedRevision + 1;
            $this->execute(<<<'SQL'
UPDATE exam_attempts
SET answers_json = :answers, revision = :revision, status = 'scored', submitted_at = UTC_TIMESTAMP(6)
WHERE id = :attempt AND workspace_id = :workspace AND user_id = :user AND revision = :expected
SQL, [
                'answers' => json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'revision' => $revision, 'attempt' => $attemptId, 'workspace' => $workspaceId,
                'user' => $userId, 'expected' => $expectedRevision,
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
            $this->audit->record($workspaceId, $userId, 'exam.attempt.scored', 'exam_attempt', $attemptId, 'success', ['assessment_id' => $attempt['assessment_id'], 'score_basis_points' => $score]);

            return ['attempt_id' => $attemptId, 'revision' => $revision, 'status' => 'scored', 'correct_count' => $correct, 'question_count' => $questionCount, 'score_basis_points' => $score];
        });
    }

    /** @return array<string, mixed> */
    public function attemptReview(string $userId, string $workspaceId, string $attemptId): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT attempt.id, attempt.assessment_id, attempt.revision, attempt.status,
       result.correct_count, result.question_count, result.score_basis_points, result.review_json
FROM exam_attempts attempt
JOIN exam_attempt_results result ON result.attempt_id = attempt.id AND result.workspace_id = attempt.workspace_id
WHERE attempt.id = :attempt AND attempt.workspace_id = :workspace AND attempt.user_id = :user
SQL);
        $query->execute(['attempt' => $attemptId, 'workspace' => $workspaceId, 'user' => $userId]);
        $row = $query->fetch();
        if ($row === false) {
            throw new PlatformException('attempt_not_found', 'Scored attempt was not found.', 404);
        }
        $row['review'] = json_decode((string) $row['review_json'], true, 64, JSON_THROW_ON_ERROR);
        unset($row['review_json']);

        return $row;
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
       scope.id AS scope_id, policy.target_scope_id, policy.requires_entitlement, policy.max_attempts
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
SELECT attempt.id, attempt.assessment_id, attempt.status, attempt.revision, version.definition_json
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
            $questions[$index]['prompt'] = $this->text((string) ($question['prompt'] ?? ''), 4000, 'question_prompt_invalid');
            $choices = $question['choices'] ?? null;
            if (!is_array($choices) || !array_is_list($choices) || count($choices) < 2 || count($choices) > 10) {
                throw new PlatformException('question_choices_invalid', 'Each question must contain between 2 and 10 choices.', 422);
            }
            foreach ($choices as $choiceIndex => $choice) {
                if (!is_string($choice)) {
                    throw new PlatformException('question_choice_invalid', 'Question choices must be text.', 422);
                }
                $questions[$index]['choices'][$choiceIndex] = $this->text($choice, 1000, 'question_choice_invalid');
            }
            $answer = filter_var($question['answer'] ?? null, FILTER_VALIDATE_INT);
            if ($answer === false || $answer < 0 || $answer >= count($choices)) {
                throw new PlatformException('question_answer_invalid', 'Correct choice index is invalid.', 422);
            }
            $questions[$index]['answer'] = $answer;
            if (isset($question['explanation']) && $question['explanation'] !== null && $question['explanation'] !== '') {
                $questions[$index]['explanation'] = $this->text((string) $question['explanation'], 4000, 'question_explanation_invalid');
            } else {
                $questions[$index]['explanation'] = null;
            }
        }
        $definition['questions'] = $questions;

        return ContentPayload::encode($definition);
    }

    /** @param array<string, mixed> $definition @return list<array<string, mixed>> */
    private function safeQuestions(array $definition): array
    {
        $safe = [];
        foreach ($definition['questions'] as $question) {
            $safe[] = ['id' => $question['id'], 'prompt' => $question['prompt'], 'choices' => $question['choices']];
        }

        return $safe;
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
