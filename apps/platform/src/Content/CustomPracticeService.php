<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Transaction;
use Fanoos\Platform\Support\Uuid;
use PDO;

/**
 * آزمون دلخواه -- a practice exam a student builds from the bank.
 *
 * They pick courses, optionally topics within them, how many questions, and
 * where from: every question, only ones they have never answered, or only
 * ones they last got wrong. The questions are drawn at random from the exams
 * they may take -- published, in this workspace, and not behind a purchase
 * they have not made -- so building one never reaches a question the student
 * could not already open in its own exam.
 *
 * The result is stored as an ordinary published assessment, marked as theirs
 * (exam_assessments.created_for_user_id), so everything the runner does --
 * pacing, the three modes, the timer, review, the mistakes review, images --
 * works on it unchanged, and it never appears in anyone's catalogue.
 */
final class CustomPracticeService
{
    public const SOURCES = ['all', 'unseen', 'wrong'];
    public const MIN_QUESTIONS = 5;
    public const MAX_QUESTIONS = 100;
    private const MAX_COURSES = 20;

    public function __construct(
        private readonly PDO $database,
        private readonly AccessGate $access,
        private readonly EntitlementService $entitlements,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * What the chosen courses hold for this student: per topic, how many
     * questions in total, never answered, and last answered wrong.
     *
     * @param list<string> $courseIds
     * @return array{total:int,unseen:int,wrong:int,topics:list<array{topic:string,total:int,unseen:int,wrong:int}>}
     */
    public function options(string $userId, string $workspaceId, array $courseIds): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        $pool = $this->pool($userId, $workspaceId, $this->courseIds($courseIds));
        $history = $this->history($userId, $workspaceId);

        $topics = [];
        $totals = ['total' => 0, 'unseen' => 0, 'wrong' => 0];
        foreach ($pool as $question) {
            $topic = (string) ($question['topic'] ?? '');
            $topics[$topic] ??= ['topic' => $topic, 'total' => 0, 'unseen' => 0, 'wrong' => 0];
            $state = $history[(string) $question['id']] ?? null;
            foreach (['total' => true, 'unseen' => $state === null, 'wrong' => $state === false] as $key => $counts) {
                if ($counts) {
                    ++$topics[$topic][$key];
                    ++$totals[$key];
                }
            }
        }
        $collator = class_exists(\Collator::class) ? new \Collator('fa_IR') : null;
        uasort($topics, static function (array $a, array $b) use ($collator): int {
            if (($a['topic'] === '') !== ($b['topic'] === '')) {
                return $a['topic'] === '' ? 1 : -1;
            }
            return $collator !== null ? $collator->compare($a['topic'], $b['topic']) : strcmp($a['topic'], $b['topic']);
        });

        return $totals + ['topics' => array_values($topics)];
    }

    /**
     * Builds the exam and returns it, ready to open.
     *
     * @param array<string, mixed> $input course_ids, topics (optional), count, source, time_limit_minutes (optional)
     * @return array{assessment_id:string,title:string,question_count:int}
     */
    public function create(string $userId, string $workspaceId, array $input): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        $courseIds = $this->courseIds((array) ($input['course_ids'] ?? []));
        $topics = array_values(array_unique(array_map('strval', (array) ($input['topics'] ?? []))));
        $count = filter_var($input['count'] ?? null, FILTER_VALIDATE_INT);
        if ($count === false || $count < self::MIN_QUESTIONS || $count > self::MAX_QUESTIONS) {
            throw new PlatformException('custom_practice_count_invalid', 'Choose between 5 and 100 questions.', 422);
        }
        $source = (string) ($input['source'] ?? 'all');
        if (!in_array($source, self::SOURCES, true)) {
            throw new PlatformException('custom_practice_source_invalid', 'Question source is invalid.', 422);
        }
        $timeLimit = null;
        if (($input['time_limit_minutes'] ?? null) !== null && $input['time_limit_minutes'] !== '') {
            $timeLimit = filter_var($input['time_limit_minutes'], FILTER_VALIDATE_INT);
            if ($timeLimit === false || $timeLimit < 1 || $timeLimit > 600) {
                throw new PlatformException('custom_practice_time_invalid', 'Time limit must be between 1 and 600 minutes.', 422);
            }
        }

        $history = $source === 'all' ? [] : $this->history($userId, $workspaceId);
        $candidates = [];
        foreach ($this->pool($userId, $workspaceId, $courseIds) as $question) {
            if ($topics !== [] && !in_array((string) ($question['topic'] ?? ''), $topics, true)) {
                continue;
            }
            $state = $history[(string) $question['id']] ?? null;
            if (($source === 'unseen' && $state !== null) || ($source === 'wrong' && $state !== false)) {
                continue;
            }
            $candidates[] = $question;
        }
        if (count($candidates) < self::MIN_QUESTIONS) {
            throw new PlatformException('custom_practice_too_few', 'Not enough questions match these choices.', 422);
        }
        shuffle($candidates);
        $questions = array_slice($candidates, 0, $count);

        $courseTitles = $this->courseTitles($workspaceId, $courseIds);
        $label = implode('، ', array_slice($courseTitles, 0, 3)) . (count($courseTitles) > 3 ? '، …' : '');
        $title = mb_substr('آزمون دلخواه · ' . $label . ' · ' . self::faDigits((string) count($questions)) . ' سؤال', 0, 200);
        $definitionJson = ContentPayload::encode(['questions' => $questions]);

        $assessmentId = Uuid::v7();
        Transaction::run($this->database, function () use ($userId, $workspaceId, $assessmentId, $title, $definitionJson, $timeLimit, $source, $count): void {
            $versionId = Uuid::v7();
            $workspaceScope = $this->database->prepare("SELECT id FROM rbac_scopes WHERE scope_type = 'workspace' AND entity_id = :workspace AND workspace_id = :workspace_check");
            $workspaceScope->execute(['workspace' => $workspaceId, 'workspace_check' => $workspaceId]);
            $workspaceScopeId = (string) $workspaceScope->fetchColumn();

            // Published from the start: every question in it was already
            // reviewed and published in the exam it came from.
            $this->database->prepare(<<<'SQL'
INSERT INTO exam_assessments (id, workspace_id, created_for_user_id, offering_id, title, status, current_version_no, created_at, updated_at)
VALUES (:id, :workspace, :user, NULL, :title, 'published', 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL)->execute(['id' => $assessmentId, 'workspace' => $workspaceId, 'user' => $userId, 'title' => $title]);
            $this->database->prepare(<<<'SQL'
INSERT INTO exam_assessment_versions (id, workspace_id, assessment_id, version_no, definition_json, checksum_sha256, created_by_user_id, created_at)
VALUES (:id, :workspace, :assessment, 1, :definition, :checksum, :user, UTC_TIMESTAMP(6))
SQL)->execute([
                'id' => $versionId, 'workspace' => $workspaceId, 'assessment' => $assessmentId,
                'definition' => $definitionJson, 'checksum' => hash('sha256', $definitionJson, true), 'user' => $userId,
            ]);
            $this->database->prepare(<<<'SQL'
INSERT INTO exam_version_states (workspace_id, assessment_id, assessment_version_id, status, updated_at)
VALUES (:workspace, :assessment, :version, 'approved', UTC_TIMESTAMP(6))
SQL)->execute(['workspace' => $workspaceId, 'assessment' => $assessmentId, 'version' => $versionId]);
            $this->database->prepare(<<<'SQL'
INSERT INTO exam_assessment_metadata (workspace_id, assessment_id, assessment_kind, assessment_variant, course_id, source_resource_id, created_at, updated_at)
VALUES (:workspace, :assessment, 'practice', 'custom', NULL, NULL, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL)->execute(['workspace' => $workspaceId, 'assessment' => $assessmentId]);
            $this->database->prepare(<<<'SQL'
INSERT INTO exam_access_policies (workspace_id, assessment_id, target_scope_id, requires_entitlement, max_attempts, time_limit_minutes, updated_at)
VALUES (:workspace, :assessment, :scope, FALSE, 20, :time_limit, UTC_TIMESTAMP(6))
SQL)->execute(['workspace' => $workspaceId, 'assessment' => $assessmentId, 'scope' => $workspaceScopeId, 'time_limit' => $timeLimit]);
            $this->database->prepare(<<<'SQL'
INSERT INTO rbac_scopes (id, scope_type, entity_id, workspace_id, parent_scope_id, created_at)
VALUES (:id, 'assessment', :assessment, :workspace, :parent, UTC_TIMESTAMP(6))
SQL)->execute(['id' => Uuid::v7(), 'assessment' => $assessmentId, 'workspace' => $workspaceId, 'parent' => $workspaceScopeId]);
            $this->audit->record($workspaceId, $userId, 'exam.custom_practice.created', 'exam_assessment', $assessmentId, 'success', [
                'source' => $source, 'requested' => $count,
            ]);
        });

        return ['assessment_id' => $assessmentId, 'title' => $title, 'question_count' => count($questions)];
    }

    /**
     * The student's own custom exams, newest first, with how the last attempt went.
     *
     * @return list<array<string, mixed>>
     */
    public function mine(string $userId, string $workspaceId): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        $query = $this->database->prepare(<<<'SQL'
SELECT assessment.id, assessment.title, assessment.created_at,
       JSON_LENGTH(version.definition_json, '$.questions') AS question_count,
       policy.time_limit_minutes,
       (SELECT result.score_basis_points FROM exam_attempts attempt
        JOIN exam_attempt_results result ON result.attempt_id = attempt.id AND result.workspace_id = attempt.workspace_id
        WHERE attempt.assessment_id = assessment.id AND attempt.workspace_id = assessment.workspace_id AND attempt.user_id = :user_score
        ORDER BY attempt.submitted_at DESC LIMIT 1) AS last_score,
       (SELECT attempt.id FROM exam_attempts attempt
        WHERE attempt.assessment_id = assessment.id AND attempt.workspace_id = assessment.workspace_id
          AND attempt.user_id = :user_open AND attempt.status = 'in_progress' LIMIT 1) AS open_attempt_id
FROM exam_assessments assessment
JOIN exam_assessment_versions version ON version.assessment_id = assessment.id
 AND version.workspace_id = assessment.workspace_id AND version.version_no = assessment.current_version_no
JOIN exam_access_policies policy ON policy.assessment_id = assessment.id AND policy.workspace_id = assessment.workspace_id
WHERE assessment.workspace_id = :workspace AND assessment.created_for_user_id = :user
  AND assessment.archived_at IS NULL
ORDER BY assessment.created_at DESC
LIMIT 30
SQL);
        $query->execute(['workspace' => $workspaceId, 'user' => $userId, 'user_score' => $userId, 'user_open' => $userId]);

        return array_map(static fn (array $row): array => [
            'id' => (string) $row['id'],
            'title' => (string) $row['title'],
            'created_at' => gmdate(DATE_ATOM, (int) strtotime($row['created_at'] . ' UTC')),
            'question_count' => (int) $row['question_count'],
            'time_limit_minutes' => $row['time_limit_minutes'] === null ? null : (int) $row['time_limit_minutes'],
            'last_score_percent' => $row['last_score'] === null ? null : intdiv((int) $row['last_score'], 100),
            'in_progress' => $row['open_attempt_id'] !== null,
        ], $query->fetchAll());
    }

    /**
     * Every question of every exam in these courses that this student may
     * take, once each (the same question id in two exams counts once).
     *
     * @param list<string> $courseIds
     * @return list<array<string, mixed>>
     */
    private function pool(string $userId, string $workspaceId, array $courseIds): array
    {
        $placeholders = implode(',', array_map(static fn (int $i): string => ':course' . $i, array_keys($courseIds)));
        $query = $this->database->prepare(<<<SQL
SELECT version.definition_json, policy.requires_entitlement, policy.target_scope_id, metadata.course_id
FROM exam_assessments assessment
JOIN exam_assessment_metadata metadata ON metadata.assessment_id = assessment.id AND metadata.workspace_id = assessment.workspace_id
JOIN exam_access_policies policy ON policy.assessment_id = assessment.id AND policy.workspace_id = assessment.workspace_id
JOIN exam_assessment_versions version ON version.assessment_id = assessment.id
 AND version.workspace_id = assessment.workspace_id AND version.version_no = assessment.current_version_no
WHERE assessment.workspace_id = :workspace AND assessment.status = 'published' AND assessment.archived_at IS NULL
  AND assessment.created_for_user_id IS NULL
  AND metadata.course_id IN ({$placeholders})
SQL);
        $parameters = ['workspace' => $workspaceId];
        foreach ($courseIds as $i => $courseId) {
            $parameters['course' . $i] = $courseId;
        }
        $query->execute($parameters);

        $questions = [];
        $entitled = [];
        foreach ($query->fetchAll() as $row) {
            if ((bool) $row['requires_entitlement']) {
                $scope = (string) $row['target_scope_id'];
                $entitled[$scope] ??= $this->entitlements->has($userId, $workspaceId, $scope);
                if (!$entitled[$scope]) {
                    continue;
                }
            }
            $definition = json_decode((string) $row['definition_json'], true, 64, JSON_THROW_ON_ERROR);
            foreach ($definition['questions'] ?? [] as $question) {
                // Remembered so the progress dashboard can file an answer in
                // a custom exam under the course the question came from.
                $question['course_id'] = (string) $row['course_id'];
                $questions[(string) $question['id']] ??= $question;
            }
        }

        return array_values($questions);
    }

    /**
     * question id => true if the student last answered it right, false if
     * wrong. Unanswered questions are absent; a blank on a later attempt does
     * not overwrite an earlier answer.
     *
     * @return array<string, bool>
     */
    private function history(string $userId, string $workspaceId): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT result.review_json
FROM exam_attempts attempt
JOIN exam_attempt_results result ON result.attempt_id = attempt.id AND result.workspace_id = attempt.workspace_id
WHERE attempt.workspace_id = :workspace AND attempt.user_id = :user
ORDER BY attempt.submitted_at
SQL);
        $query->execute(['workspace' => $workspaceId, 'user' => $userId]);
        $history = [];
        foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $reviewJson) {
            foreach (json_decode((string) $reviewJson, true, 64, JSON_THROW_ON_ERROR) as $entry) {
                if (($entry['selected'] ?? null) !== null) {
                    $history[(string) $entry['id']] = ($entry['is_correct'] ?? false) === true;
                }
            }
        }

        return $history;
    }

    /**
     * @param array<mixed> $courseIds
     * @return list<string>
     */
    private function courseIds(array $courseIds): array
    {
        $clean = array_values(array_unique(array_filter(array_map('strval', $courseIds), static fn (string $id): bool => preg_match('/^[0-9a-f-]{36}$/', $id) === 1)));
        if ($clean === [] || count($clean) > self::MAX_COURSES) {
            throw new PlatformException('custom_practice_courses_invalid', 'Choose between 1 and 20 courses.', 422);
        }

        return $clean;
    }

    /**
     * @param list<string> $courseIds
     * @return list<string>
     */
    private function courseTitles(string $workspaceId, array $courseIds): array
    {
        $placeholders = implode(',', array_map(static fn (int $i): string => ':course' . $i, array_keys($courseIds)));
        $query = $this->database->prepare("SELECT title FROM academic_courses WHERE workspace_id = :workspace AND id IN ({$placeholders}) ORDER BY title");
        $parameters = ['workspace' => $workspaceId];
        foreach ($courseIds as $i => $courseId) {
            $parameters['course' . $i] = $courseId;
        }
        $query->execute($parameters);

        // Bank course titles read "subject — stage"; the subject is enough in an exam title.
        return array_map(static fn (string $title): string => trim(explode(' — ', $title)[0]), $query->fetchAll(PDO::FETCH_COLUMN));
    }

    private static function faDigits(string $value): string
    {
        return strtr($value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }
}
