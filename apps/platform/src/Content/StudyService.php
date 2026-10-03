<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

use DateTimeImmutable;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;

/**
 * مرور هوشمند and تایمر مطالعه.
 *
 * The review box is read from exam_question_user_stats on every visit,
 * nothing is scheduled ahead: a question the student has ever got wrong
 * comes back on a growing interval (1, 3, 7, 14, 30 days) -- straight away
 * the next day while its last answer is wrong, and further out the more
 * often they have since got it right. Questions they have always answered
 * correctly are not reviewed; that is what the exams are for.
 *
 * The timer records each finished focus block, which the progress
 * dashboard adds to the time spent in exams.
 */
final class StudyService
{
    /** Days until a question is due again, by its box (1 = just got it wrong). */
    public const INTERVALS = [1, 1, 3, 7, 14, 30];
    public const REVIEW_SET = 50;
    public const MAX_SESSION_MINUTES = 180;
    /** More than this in one day is not a timer, it is a timer left running. */
    public const MAX_DAY_MINUTES = 16 * 60;

    public function __construct(
        private readonly PDO $database,
        private readonly AccessGate $access,
        private readonly CustomPracticeService $practice,
    ) {
    }

    /**
     * What is due today, by topic, and what comes due in the next week.
     *
     * @return array{due:int,topics:list<array{topic:?string,due:int}>,upcoming:list<array{days:int,count:int}>,in_review:int}
     */
    public function review(string $userId, string $workspaceId, ?int $now = null): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        $now ??= time();
        $due = [];
        $upcoming = array_fill(1, 7, 0);
        $inReview = 0;
        foreach ($this->candidates($userId, $workspaceId) as $row) {
            ++$inReview;
            $days = self::daysUntilDue($row, $now);
            if ($days <= 0) {
                $due[] = $row;
            } elseif ($days <= 7) {
                ++$upcoming[$days];
            }
        }
        $topics = [];
        foreach ($due as $row) {
            $topics[$row['topic'] ?? ''] = ($topics[$row['topic'] ?? ''] ?? 0) + 1;
        }
        arsort($topics);

        return [
            'due' => count($due),
            'topics' => array_map(static fn (string $topic, int $count): array => ['topic' => $topic === '' ? null : $topic, 'due' => $count], array_keys($topics), array_values($topics)),
            'upcoming' => array_map(static fn (int $days, int $count): array => ['days' => $days, 'count' => $count], array_keys($upcoming), array_values($upcoming)),
            'in_review' => $inReview,
        ];
    }

    /**
     * Today's due questions (most overdue first, optionally one topic's) as a study set.
     *
     * @return array{assessment_id:string,title:string,question_count:int}
     */
    public function startReview(string $userId, string $workspaceId, ?string $topic = null, ?int $now = null): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        $now ??= time();
        $due = [];
        foreach ($this->candidates($userId, $workspaceId) as $row) {
            $days = self::daysUntilDue($row, $now);
            if ($days <= 0 && ($topic === null || $topic === '' || $row['topic'] === $topic)) {
                $due[] = $row + ['overdue' => -$days];
            }
        }
        if ($due === []) {
            throw new PlatformException('review_nothing_due', 'Nothing is due for review today.', 422);
        }
        usort($due, static fn (array $a, array $b): int => $b['overdue'] <=> $a['overdue']);
        $keys = array_column(array_slice($due, 0, self::REVIEW_SET), 'question_key');

        return $this->practice->createFromQuestions($userId, $workspaceId, $keys, 'مرور هوشمند' . ($topic ? ' · ' . $topic : ''), 'review', false);
    }

    /** @return array{minutes_today:int} */
    public function logSession(string $userId, string $workspaceId, int $minutes, ?string $label = null): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        if ($minutes < 1 || $minutes > self::MAX_SESSION_MINUTES) {
            throw new PlatformException('study_session_invalid', 'A session is between 1 and 180 minutes.', 422);
        }
        $today = $this->database->prepare('SELECT COALESCE(SUM(minutes), 0) FROM study_sessions WHERE workspace_id = :workspace AND user_id = :user AND ended_at >= UTC_TIMESTAMP(6) - INTERVAL 1 DAY');
        $today->execute(['workspace' => $workspaceId, 'user' => $userId]);
        $sofar = (int) $today->fetchColumn();
        if ($sofar + $minutes > self::MAX_DAY_MINUTES) {
            throw new PlatformException('study_session_limit', 'That is more than a day holds.', 422);
        }
        $this->database->prepare('INSERT INTO study_sessions (id, workspace_id, user_id, minutes, label, ended_at) VALUES (:id, :workspace, :user, :minutes, :label, UTC_TIMESTAMP(6))')
            ->execute(['id' => Uuid::v7(), 'workspace' => $workspaceId, 'user' => $userId, 'minutes' => $minutes, 'label' => $label === null ? null : mb_substr(trim($label), 0, 120)]);

        return ['minutes_today' => $sofar + $minutes];
    }

    /**
     * The box a question is in: 1 while its last answer is wrong, then one
     * more for every right answer beyond its wrong ones.
     *
     * @param array{answered_count:int,correct_count:int,last_correct:bool} $row
     */
    public static function box(array $row): int
    {
        if (!$row['last_correct']) {
            return 1;
        }
        $wrong = $row['answered_count'] - $row['correct_count'];

        return max(2, min(count(self::INTERVALS) - 1, 1 + $row['correct_count'] - $wrong + 1));
    }

    /** Whole days until due; zero or less means due now. @param array{answered_count:int,correct_count:int,last_correct:bool,last_answered_at:string} $row */
    public static function daysUntilDue(array $row, int $now): int
    {
        $interval = self::INTERVALS[self::box($row)];
        $dueAt = (new DateTimeImmutable($row['last_answered_at'] . ' UTC'))->modify("+{$interval} days")->getTimestamp();

        return (int) ceil(($dueAt - $now) / 86400);
    }

    /** @return list<array{question_key:string,topic:?string,answered_count:int,correct_count:int,last_correct:bool,last_answered_at:string}> */
    private function candidates(string $userId, string $workspaceId): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT question_key, topic, answered_count, correct_count, last_correct, last_answered_at
FROM exam_question_user_stats
WHERE workspace_id = :workspace AND user_id = :user AND answered_count > correct_count
SQL);
        $query->execute(['workspace' => $workspaceId, 'user' => $userId]);

        return array_map(static fn (array $row): array => [
            'question_key' => (string) $row['question_key'],
            'topic' => $row['topic'] === null || $row['topic'] === '' ? null : (string) $row['topic'],
            'answered_count' => (int) $row['answered_count'],
            'correct_count' => (int) $row['correct_count'],
            'last_correct' => (bool) $row['last_correct'],
            'last_answered_at' => (string) $row['last_answered_at'],
        ], $query->fetchAll());
    }
}
