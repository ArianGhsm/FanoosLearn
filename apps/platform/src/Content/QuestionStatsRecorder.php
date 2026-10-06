<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

use Fanoos\Platform\Engagement\QuestionDifficulty;
use PDO;

/**
 * آمار هر سؤال: the counters behind "how has this question gone".
 *
 * Kept as each attempt is scored (ExamService::scoreAndClose calls record()
 * inside the same transaction), keyed by the stable question id, so a
 * question asked in its own exam and again in someone's آزمون دلخواه is one
 * question with one record. They are derived data -- rebuild() recomputes all
 * of them from exam_attempt_results, which stays the record of what happened.
 *
 * Counted: questions actually answered. Not counted: a blank, and a question
 * whose answer was revealed in a learning attempt, where it can be seen
 * before choosing -- a right answer after seeing the answer says nothing about
 * the question or the student.
 */
final class QuestionStatsRecorder
{
    /** Below this many answers from other people, the share is not shown: too few to mean anything, and too few to stay anonymous. */
    public const PEER_MINIMUM = 3;

    public function __construct(private readonly PDO $database)
    {
    }

    /**
     * @param array<string, mixed> $definition the attempt's version definition
     * @param list<array<string, mixed>> $review the scored review entries
     * @param list<int> $revealedPositions 1-based positions whose answer was seen before answering (seenFirst())
     * @return list<string> the questions counted as answered right (what points are earned for)
     */
    public function record(
        string $workspaceId,
        string $userId,
        string $attemptId,
        ?string $courseId,
        array $definition,
        array $review,
        array $revealedPositions,
        string $answeredAt,
    ): array {
        $questions = [];
        foreach ($definition['questions'] ?? [] as $question) {
            $questions[(string) $question['id']] = $question;
        }
        $revealed = [];
        if ($revealedPositions !== []) {
            $order = ExamAttemptShuffle::forDefinition($attemptId, $definition);
            foreach ($revealedPositions as $position) {
                if (isset($order[$position - 1])) {
                    $revealed[$order[$position - 1]] = true;
                }
            }
        }

        $entries = [];
        $blanks = [];
        foreach ($review as $entry) {
            $id = (string) ($entry['id'] ?? '');
            if (isset($revealed[$id]) || !isset($questions[$id])) {
                continue;
            }
            if (($entry['selected'] ?? null) === null) {
                $blanks[$id] = true;
                continue;
            }
            $entries[$id] = $entry;
        }
        // One lock order for every writer, so two submissions sharing
        // questions cannot deadlock on each other's rows.
        ksort($entries, SORT_STRING);
        ksort($blanks, SORT_STRING);

        $global = $this->database->prepare(<<<'SQL'
INSERT INTO exam_question_stats (workspace_id, question_key, answered_count, correct_count, updated_at)
VALUES (:workspace, :question, 1, :correct, UTC_TIMESTAMP(6))
ON DUPLICATE KEY UPDATE answered_count = answered_count + 1, correct_count = correct_count + VALUES(correct_count), updated_at = UTC_TIMESTAMP(6)
SQL);
        $choice = $this->database->prepare(<<<'SQL'
INSERT INTO exam_question_choice_stats (workspace_id, question_key, choice_index, picked_count)
VALUES (:workspace, :question, :choice, 1)
ON DUPLICATE KEY UPDATE picked_count = picked_count + 1
SQL);
        // last_correct is assigned before last_answered_at: MySQL applies the
        // assignments left to right, so the comparison still sees the old time.
        $mine = $this->database->prepare(<<<'SQL'
INSERT INTO exam_question_user_stats (
    workspace_id, user_id, question_key, course_id, topic, answered_count, correct_count, last_correct, last_answered_at
) VALUES (:workspace, :user, :question, :course, :topic, 1, :correct, :last_correct, :answered_at)
ON DUPLICATE KEY UPDATE
    answered_count = answered_count + 1,
    correct_count = correct_count + VALUES(correct_count),
    last_correct = IF(VALUES(last_answered_at) >= last_answered_at, VALUES(last_correct), last_correct),
    last_answered_at = GREATEST(last_answered_at, VALUES(last_answered_at)),
    course_id = COALESCE(VALUES(course_id), course_id),
    topic = COALESCE(VALUES(topic), topic)
SQL);

        // پاسخ سفید: a blank is not an answer, so it is counted on its own.
        $blank = $this->database->prepare(<<<'SQL'
INSERT INTO exam_question_user_blanks (workspace_id, user_id, question_key, blank_count, last_blank_at)
VALUES (:workspace, :user, :question, 1, :at)
ON DUPLICATE KEY UPDATE blank_count = blank_count + 1, last_blank_at = GREATEST(last_blank_at, VALUES(last_blank_at))
SQL);
        foreach (array_keys($blanks) as $id) {
            $blank->execute(['workspace' => $workspaceId, 'user' => $userId, 'question' => (string) $id, 'at' => $answeredAt]);
        }

        $counted = [];
        foreach ($entries as $id => $entry) {
            $correct = ($entry['is_correct'] ?? false) === true ? 1 : 0;
            if ($correct === 1) {
                $counted[] = (string) $id;
            }
            $question = $questions[$id];
            $topic = trim((string) ($question['topic'] ?? ''));
            $global->execute(['workspace' => $workspaceId, 'question' => $id, 'correct' => $correct]);
            $choice->execute(['workspace' => $workspaceId, 'question' => $id, 'choice' => (int) $entry['selected']]);
            $mine->execute([
                'workspace' => $workspaceId, 'user' => $userId, 'question' => $id,
                // A custom exam spans courses; each of its questions carries
                // the course it was drawn from.
                'course' => isset($question['course_id']) && is_string($question['course_id']) ? $question['course_id'] : $courseId,
                'topic' => $topic === '' ? null : mb_substr($topic, 0, 200),
                'correct' => $correct, 'last_correct' => $correct, 'answered_at' => $answeredAt,
            ]);
        }

        return $counted;
    }

    /**
     * The positions whose answer the student saw before choosing. Only a
     * learning attempt reveals on demand; practice reveals right after the
     * choice is made and locks it, so its reveals follow an honest answer.
     *
     * @return list<int>
     */
    public static function seenFirst(string $mode, mixed $revealed): array
    {
        return $mode === 'learning' && is_array($revealed) ? array_map('intval', $revealed) : [];
    }

    /**
     * Recomputes every counter from the scored attempts -- once after the
     * tables are introduced, and whenever the counters are in doubt.
     *
     * @return array{attempts:int}
     */
    public function rebuild(?string $workspaceId = null): array
    {
        $where = $workspaceId === null ? '' : 'WHERE workspace_id = :workspace';
        $parameters = $workspaceId === null ? [] : ['workspace' => $workspaceId];
        foreach (['exam_question_stats', 'exam_question_choice_stats', 'exam_question_user_stats', 'exam_question_user_blanks'] as $table) {
            $this->database->prepare("DELETE FROM {$table} {$where}")->execute($parameters);
        }

        $attemptWhere = $workspaceId === null ? '' : 'AND attempt.workspace_id = :workspace';
        $query = $this->database->prepare(<<<SQL
SELECT attempt.id, attempt.workspace_id, attempt.user_id, attempt.mode, attempt.revealed_json, attempt.submitted_at,
       result.review_json, version.definition_json, metadata.course_id
FROM exam_attempts attempt
JOIN exam_attempt_results result ON result.attempt_id = attempt.id AND result.workspace_id = attempt.workspace_id
JOIN exam_assessment_versions version ON version.id = attempt.assessment_version_id AND version.workspace_id = attempt.workspace_id
LEFT JOIN exam_assessment_metadata metadata ON metadata.assessment_id = attempt.assessment_id AND metadata.workspace_id = attempt.workspace_id
WHERE attempt.status = 'scored' {$attemptWhere}
ORDER BY attempt.submitted_at, attempt.id
SQL);
        $query->execute($parameters);
        $count = 0;
        while (($row = $query->fetch()) !== false) {
            $revealed = json_decode((string) ($row['revealed_json'] ?? '[]'), true, 16, JSON_THROW_ON_ERROR);
            $this->record(
                (string) $row['workspace_id'],
                (string) $row['user_id'],
                (string) $row['id'],
                $row['course_id'] === null ? null : (string) $row['course_id'],
                json_decode((string) $row['definition_json'], true, 64, JSON_THROW_ON_ERROR),
                json_decode((string) $row['review_json'], true, 64, JSON_THROW_ON_ERROR),
                self::seenFirst((string) $row['mode'], $revealed),
                (string) ($row['submitted_at'] ?? gmdate('Y-m-d H:i:s')),
            );
            ++$count;
        }

        return ['attempts' => $count];
    }

    /**
     * What the runner shows under a question: how everyone else has done on
     * it, and this student's own record. Other people's numbers are withheld
     * below PEER_MINIMUM answers.
     *
     * @return array{peer_answered:?int,peer_correct_percent:?int,answered:int,correct:int,blank:int,last_correct:?bool,last_answered_at:?string,difficulty:?string}
     */
    public function forQuestion(string $workspaceId, string $userId, string $questionKey): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT stats.answered_count AS all_answered, stats.correct_count AS all_correct,
       mine.answered_count, mine.correct_count, mine.last_correct, mine.last_answered_at, blanks.blank_count
FROM (SELECT 1) anchor
LEFT JOIN exam_question_stats stats ON stats.workspace_id = :workspace AND stats.question_key = :question
LEFT JOIN exam_question_user_stats mine ON mine.workspace_id = :workspace_mine AND mine.user_id = :user AND mine.question_key = :question_mine
LEFT JOIN exam_question_user_blanks blanks ON blanks.workspace_id = :workspace_blank AND blanks.user_id = :user_blank AND blanks.question_key = :question_blank
SQL);
        $query->execute([
            'workspace' => $workspaceId, 'question' => $questionKey,
            'workspace_mine' => $workspaceId, 'user' => $userId, 'question_mine' => $questionKey,
            'workspace_blank' => $workspaceId, 'user_blank' => $userId, 'question_blank' => $questionKey,
        ]);
        $row = $query->fetch() ?: [];
        $answered = (int) ($row['answered_count'] ?? 0);
        $correct = (int) ($row['correct_count'] ?? 0);
        $peerAnswered = max(0, (int) ($row['all_answered'] ?? 0) - $answered);
        $peerCorrect = max(0, (int) ($row['all_correct'] ?? 0) - $correct);
        $enough = $peerAnswered >= self::PEER_MINIMUM;

        return [
            'peer_answered' => $enough ? $peerAnswered : null,
            'peer_correct_percent' => $enough ? (int) round($peerCorrect * 100 / $peerAnswered) : null,
            'answered' => $answered,
            'correct' => $correct,
            'blank' => (int) ($row['blank_count'] ?? 0),
            'last_correct' => $answered > 0 ? (bool) $row['last_correct'] : null,
            'last_answered_at' => $answered > 0 ? gmdate(DATE_ATOM, (int) strtotime($row['last_answered_at'] . ' UTC')) : null,
            // Measured from everyone's answers (this student's included); null until enough are in.
            'difficulty' => QuestionDifficulty::measured((int) ($row['all_answered'] ?? 0), (int) ($row['all_correct'] ?? 0)),
        ];
    }

    /**
     * How often each choice was picked, by everyone (this student included),
     * for the review after submitting. Null below PEER_MINIMUM answers.
     *
     * @return array{answered:int,percent:list<int>}|null
     */
    public function choiceShares(string $workspaceId, string $questionKey, int $choiceCount): ?array
    {
        $query = $this->database->prepare('SELECT choice_index, picked_count FROM exam_question_choice_stats WHERE workspace_id = :workspace AND question_key = :question');
        $query->execute(['workspace' => $workspaceId, 'question' => $questionKey]);
        $counts = array_fill(0, $choiceCount, 0);
        foreach ($query->fetchAll() as $row) {
            $index = (int) $row['choice_index'];
            if ($index >= 0 && $index < $choiceCount) {
                $counts[$index] = (int) $row['picked_count'];
            }
        }
        $total = array_sum($counts);
        if ($total < self::PEER_MINIMUM) {
            return null;
        }

        return ['answered' => $total, 'percent' => array_map(static fn (int $n): int => (int) round($n * 100 / $total), $counts)];
    }
}
