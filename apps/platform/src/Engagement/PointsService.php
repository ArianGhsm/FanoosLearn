<?php

declare(strict_types=1);

namespace Fanoos\Platform\Engagement;

use DateTimeImmutable;
use DateTimeZone;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Support\JalaliCalendar;
use Fanoos\Platform\Support\Uuid;
use PDO;

/**
 * امتیاز روزانه و رتبه‌بندی (docs/product/08_ENGAGEMENT.md).
 *
 * A right answer earns points by how hard the question is (5, 10 or 20),
 * once per question per day, so retaking a question the same day earns
 * nothing more. Only answers QuestionStatsRecorder counts are scored: a
 * blank or an answer seen before choosing earns nothing. Days are the
 * workspace's local days; a week starts on Saturday; a month is a Jalali
 * month. Reaching DAILY_GOAL in a day earns one coin.
 *
 * Rankings are among the students of the same workspace and show only the
 * student's own place and how many were active -- never other people's
 * names or scores.
 */
final class PointsService
{
    public const DAILY_GOAL = 500;
    public const POINTS = ['easy' => 5, 'medium' => 10, 'hard' => 20];
    /** A question whose difficulty is not known yet earns the middle amount. */
    public const UNKNOWN_POINTS = 10;

    public function __construct(
        private readonly PDO $database,
        private readonly ?AccessGate $access = null,
    ) {
    }

    /**
     * Awards points for questions answered right in one scored attempt. Runs
     * inside the caller's transaction (ExamService::scoreAndClose).
     *
     * @param list<string> $correctKeys question ids answered right and counted
     * @param array<string, ?string> $levels question id => difficulty level
     * @return array{points:int,goal_reached:bool}
     */
    public function award(string $workspaceId, string $userId, array $correctKeys, array $levels, string $answeredAtUtc): array
    {
        if ($correctKeys === []) {
            return ['points' => 0, 'goal_reached' => false];
        }
        $day = (new DateTimeImmutable($answeredAtUtc . ' UTC'))->setTimezone($this->timezone($workspaceId))->format('Y-m-d');
        $insert = $this->database->prepare(<<<'SQL'
INSERT IGNORE INTO engagement_point_awards (workspace_id, user_id, day, question_key, points, difficulty, awarded_at)
VALUES (:workspace, :user, :day, :question, :points, :difficulty, UTC_TIMESTAMP(6))
SQL);
        $points = 0;
        $awarded = 0;
        sort($correctKeys, SORT_STRING);
        foreach (array_unique($correctKeys) as $key) {
            $level = $levels[$key] ?? null;
            $worth = $level === null ? self::UNKNOWN_POINTS : self::POINTS[$level];
            $insert->execute(['workspace' => $workspaceId, 'user' => $userId, 'day' => $day, 'question' => $key, 'points' => $worth, 'difficulty' => $level]);
            if ($insert->rowCount() === 1) {
                $points += $worth;
                ++$awarded;
            }
        }
        if ($points === 0) {
            return ['points' => 0, 'goal_reached' => false];
        }

        $this->database->prepare(<<<'SQL'
INSERT INTO engagement_daily_points (workspace_id, user_id, day, points, correct_answers, updated_at)
VALUES (:workspace, :user, :day, :points, :answers, UTC_TIMESTAMP(6))
ON DUPLICATE KEY UPDATE points = points + VALUES(points), correct_answers = correct_answers + VALUES(correct_answers), updated_at = UTC_TIMESTAMP(6)
SQL)->execute(['workspace' => $workspaceId, 'user' => $userId, 'day' => $day, 'points' => $points, 'answers' => $awarded]);

        // The goal is crossed once a day: the row is marked and one coin
        // granted, keyed by the day so a retry cannot pay twice.
        $reached = $this->database->prepare(<<<'SQL'
UPDATE engagement_daily_points SET goal_reached_at = UTC_TIMESTAMP(6)
WHERE workspace_id = :workspace AND user_id = :user AND day = :day AND goal_reached_at IS NULL AND points >= :goal
SQL);
        $reached->execute(['workspace' => $workspaceId, 'user' => $userId, 'day' => $day, 'goal' => self::DAILY_GOAL]);
        $goalReached = $reached->rowCount() === 1;
        if ($goalReached) {
            $this->database->prepare(<<<'SQL'
INSERT IGNORE INTO engagement_coin_ledger (id, workspace_id, user_id, delta, reason, reference, created_at)
VALUES (:id, :workspace, :user, 1, 'daily_goal', :day, UTC_TIMESTAMP(6))
SQL)->execute(['id' => Uuid::v7(), 'workspace' => $workspaceId, 'user' => $userId, 'day' => $day]);
        }

        return ['points' => $points, 'goal_reached' => $goalReached];
    }

    /**
     * The student's points page: today against the goal, their place today,
     * this week and this month, the recent days, weeks and months, and coins.
     *
     * @return array<string, mixed>
     */
    public function summary(string $userId, string $workspaceId, ?int $now = null): array
    {
        $this->access?->requireWorkspace($userId, $workspaceId, 'exam.take');
        $today = (new DateTimeImmutable('@' . ($now ?? time())))->setTimezone($this->timezone($workspaceId))->setTime(0, 0);
        $weekStart = self::weekStart($today);
        $monthStart = JalaliCalendar::monthStart($today);

        $todayPoints = $this->total($workspaceId, $userId, $today, $today);
        $days = [];
        for ($offset = 6; $offset >= 0; $offset--) {
            $date = $today->modify("-{$offset} days");
            $days[] = ['date' => $date->format('Y-m-d'), 'points' => $this->total($workspaceId, $userId, $date, $date)];
        }
        $weeks = [];
        for ($offset = 6; $offset >= 0; $offset--) {
            $start = $weekStart->modify('-' . (7 * $offset) . ' days');
            $weeks[] = ['start' => $start->format('Y-m-d'), 'points' => $this->total($workspaceId, $userId, $start, $start->modify('+6 days'))];
        }
        $months = [];
        $start = $monthStart;
        $end = $today;
        for ($i = 0; $i < 7; $i++) {
            $months[] = [
                'start' => $start->format('Y-m-d'), 'label' => JalaliCalendar::monthName($start), 'year' => JalaliCalendar::of($start)[0],
                'points' => $this->total($workspaceId, $userId, $start, $end),
            ];
            $end = $start->modify('-1 day');
            $start = JalaliCalendar::monthStart($end);
        }
        $months = array_reverse($months);

        return [
            'goal' => self::DAILY_GOAL,
            'points_per_answer' => self::POINTS,
            'today' => ['date' => $today->format('Y-m-d'), 'points' => $todayPoints] + $this->standing($workspaceId, $userId, $today, $today),
            'week' => ['start' => $weekStart->format('Y-m-d'), 'points' => $this->total($workspaceId, $userId, $weekStart, $today)] + $this->standing($workspaceId, $userId, $weekStart, $today),
            'month' => ['start' => $monthStart->format('Y-m-d'), 'label' => JalaliCalendar::monthName($today), 'points' => $this->total($workspaceId, $userId, $monthStart, $today)] + $this->standing($workspaceId, $userId, $monthStart, $today),
            'days' => $days,
            'weeks' => $weeks,
            'months' => $months,
            'coins' => $this->coins($workspaceId, $userId),
            'goal_days' => $this->goalDays($workspaceId, $userId),
        ];
    }

    /** A student's points today, in the workspace's local day. */
    public function today(string $workspaceId, string $userId, ?int $now = null): int
    {
        $today = (new DateTimeImmutable('@' . ($now ?? time())))->setTimezone($this->timezone($workspaceId))->setTime(0, 0);

        return $this->total($workspaceId, $userId, $today, $today);
    }

    public function coins(string $workspaceId, string $userId): int
    {
        $query = $this->database->prepare('SELECT COALESCE(SUM(delta), 0) FROM engagement_coin_ledger WHERE workspace_id = :workspace AND user_id = :user');
        $query->execute(['workspace' => $workspaceId, 'user' => $userId]);

        return (int) $query->fetchColumn();
    }

    /** The Saturday that starts the Iranian week $date is in. */
    public static function weekStart(DateTimeImmutable $date): DateTimeImmutable
    {
        $sinceSaturday = ((int) $date->format('w') + 1) % 7;

        return $sinceSaturday === 0 ? $date : $date->modify("-{$sinceSaturday} days");
    }

    private function total(string $workspaceId, string $userId, DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        $query = $this->database->prepare('SELECT COALESCE(SUM(points), 0) FROM engagement_daily_points WHERE workspace_id = :workspace AND user_id = :user AND day BETWEEN :from AND :to');
        $query->execute(['workspace' => $workspaceId, 'user' => $userId, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]);

        return (int) $query->fetchColumn();
    }

    /**
     * Place among the workspace's students with points in the period, and how
     * many those are. No place without points of one's own.
     *
     * @return array{rank:?int,active:int}
     */
    private function standing(string $workspaceId, string $userId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT COUNT(*) AS active,
       SUM(totals.points > COALESCE((SELECT SUM(points) FROM engagement_daily_points WHERE workspace_id = :workspace_me AND user_id = :user AND day BETWEEN :from_me AND :to_me), 0)) AS ahead,
       MAX(totals.user_id = :user_check) AS present
FROM (
    SELECT user_id, SUM(points) AS points FROM engagement_daily_points
    WHERE workspace_id = :workspace AND day BETWEEN :from AND :to
    GROUP BY user_id HAVING SUM(points) > 0
) totals
SQL);
        $query->execute([
            'workspace_me' => $workspaceId, 'user' => $userId, 'from_me' => $from->format('Y-m-d'), 'to_me' => $to->format('Y-m-d'),
            'user_check' => $userId, 'workspace' => $workspaceId, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'),
        ]);
        $row = $query->fetch() ?: [];

        return [
            'rank' => (int) ($row['present'] ?? 0) === 1 ? (int) $row['ahead'] + 1 : null,
            'active' => (int) ($row['active'] ?? 0),
        ];
    }

    private function goalDays(string $workspaceId, string $userId): int
    {
        $query = $this->database->prepare('SELECT COUNT(*) FROM engagement_daily_points WHERE workspace_id = :workspace AND user_id = :user AND goal_reached_at IS NOT NULL');
        $query->execute(['workspace' => $workspaceId, 'user' => $userId]);

        return (int) $query->fetchColumn();
    }

    private function timezone(string $workspaceId): DateTimeZone
    {
        $query = $this->database->prepare('SELECT timezone_name FROM tenant_workspaces WHERE id = :workspace');
        $query->execute(['workspace' => $workspaceId]);
        $name = $query->fetchColumn();
        try {
            return new DateTimeZone(is_string($name) && $name !== '' ? $name : 'Asia/Tehran');
        } catch (\Exception) {
            return new DateTimeZone('Asia/Tehran');
        }
    }
}
