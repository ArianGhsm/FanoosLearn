<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

use DateTimeImmutable;
use DateTimeZone;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Bank\BankBrowseService;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Transaction;
use Fanoos\Platform\Support\Uuid;
use PDO;

/**
 * برنامه‌ی مطالعه: a day-by-day plan from today to the student's exam date,
 * built from the bank and ticked off day by day.
 *
 * The study days carry the bank's topics -- every subject's topics, most-asked
 * first, the subjects interleaved so no day is one long subject -- packed so
 * each day holds about the same number of questions. The last days (15 %,
 * between 3 and 14) are for consolidation: full past papers and the review
 * box. Rest days come off the end of the week (Friday first). While the bank
 * has no questions yet, the plan is by subject, to read from the references.
 *
 * The plan is written once and then only ticked; a new plan replaces it.
 */
final class StudyPlanService
{
    public const MIN_DAYS = 7;
    public const MAX_DAYS = 730;
    /** Rest days are taken in this order: Friday, Thursday, Wednesday, Tuesday (PHP 'w'). */
    private const REST_ORDER = [5, 4, 3, 2];

    public function __construct(
        private readonly PDO $database,
        private readonly AccessGate $access,
        private readonly BankBrowseService $bank,
    ) {
    }

    /** @return array<string, mixed>|null the active plan, or null */
    public function plan(string $userId, string $workspaceId, ?int $now = null): ?array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        $row = $this->active($userId, $workspaceId);
        if ($row === null) {
            return null;
        }
        $today = $this->today($workspaceId, $now)->format('Y-m-d');
        $query = $this->database->prepare('SELECT day_no, plan_date, items_json, done_at FROM study_plan_days WHERE plan_id = :plan ORDER BY day_no');
        $query->execute(['plan' => $row['id']]);
        $days = [];
        $done = 0;
        $missed = 0;
        $todayNo = null;
        foreach ($query->fetchAll() as $day) {
            $isDone = $day['done_at'] !== null;
            $done += $isDone ? 1 : 0;
            $missed += !$isDone && $day['plan_date'] < $today ? 1 : 0;
            if ($todayNo === null && $day['plan_date'] >= $today) {
                $todayNo = (int) $day['day_no'];
            }
            $days[] = [
                'day_no' => (int) $day['day_no'],
                'date' => (string) $day['plan_date'],
                'items' => json_decode((string) $day['items_json'], true, 8, JSON_THROW_ON_ERROR),
                'done' => $isDone,
            ];
        }

        return [
            'id' => (string) $row['id'],
            'exam_date' => (string) $row['exam_date'],
            'start_date' => (string) $row['start_date'],
            'days_per_week' => (int) $row['days_per_week'],
            'today' => $today,
            'today_day_no' => $todayNo,
            'total' => count($days),
            'done' => $done,
            'missed' => $missed,
            'days' => $days,
        ];
    }

    /** Writes a new plan (the old one is archived). @return array<string, mixed> */
    public function create(string $userId, string $workspaceId, string $examDate, int $daysPerWeek, ?int $now = null): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        if ($daysPerWeek < 3 || $daysPerWeek > 7) {
            throw new PlatformException('study_plan_days_invalid', 'Study between 3 and 7 days a week.', 422);
        }
        $today = $this->today($workspaceId, $now);
        $exam = DateTimeImmutable::createFromFormat('!Y-m-d', $examDate, $today->getTimezone());
        if ($exam === false || $exam->format('Y-m-d') !== $examDate) {
            throw new PlatformException('study_plan_date_invalid', 'The exam date is not a date.', 422);
        }
        $span = (int) $today->diff($exam)->format('%r%a');
        if ($span < self::MIN_DAYS || $span > self::MAX_DAYS) {
            throw new PlatformException('study_plan_date_invalid', 'The exam must be between a week and two years away.', 422);
        }

        $dates = self::studyDates($today, $exam, $daysPerWeek);
        $units = $this->units($userId, $workspaceId);
        $days = self::layout($dates, $units);

        $planId = Uuid::v7();
        Transaction::run($this->database, function () use ($planId, $userId, $workspaceId, $examDate, $today, $daysPerWeek, $days): void {
            $this->database->prepare("UPDATE study_plans SET status = 'archived', archived_at = UTC_TIMESTAMP(6) WHERE workspace_id = :workspace AND user_id = :user AND status = 'active'")
                ->execute(['workspace' => $workspaceId, 'user' => $userId]);
            $this->database->prepare(<<<'SQL'
INSERT INTO study_plans (id, workspace_id, user_id, exam_date, start_date, days_per_week, status, created_at)
VALUES (:id, :workspace, :user, :exam, :start, :days, 'active', UTC_TIMESTAMP(6))
SQL)->execute(['id' => $planId, 'workspace' => $workspaceId, 'user' => $userId, 'exam' => $examDate, 'start' => $today->format('Y-m-d'), 'days' => $daysPerWeek]);
            $insert = $this->database->prepare('INSERT INTO study_plan_days (plan_id, day_no, plan_date, items_json) VALUES (:plan, :no, :date, :items)');
            foreach ($days as $index => $day) {
                $insert->execute(['plan' => $planId, 'no' => $index + 1, 'date' => $day['date'], 'items' => json_encode($day['items'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
            }
        });

        return $this->plan($userId, $workspaceId, $now) ?? [];
    }

    /** @return array{done:bool} */
    public function setDone(string $userId, string $workspaceId, int $dayNo, bool $done): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        $row = $this->active($userId, $workspaceId);
        if ($row === null) {
            throw new PlatformException('study_plan_not_found', 'There is no study plan.', 404);
        }
        $update = $this->database->prepare('UPDATE study_plan_days SET done_at = ' . ($done ? 'COALESCE(done_at, UTC_TIMESTAMP(6))' : 'NULL') . ' WHERE plan_id = :plan AND day_no = :day');
        $update->execute(['plan' => $row['id'], 'day' => $dayNo]);
        $exists = $this->database->prepare('SELECT 1 FROM study_plan_days WHERE plan_id = :plan AND day_no = :day');
        $exists->execute(['plan' => $row['id'], 'day' => $dayNo]);
        if ($exists->fetchColumn() === false) {
            throw new PlatformException('study_plan_day_not_found', 'That day is not in the plan.', 404);
        }

        return ['done' => $done];
    }

    /** @return array{archived:bool} */
    public function archive(string $userId, string $workspaceId): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        $update = $this->database->prepare("UPDATE study_plans SET status = 'archived', archived_at = UTC_TIMESTAMP(6) WHERE workspace_id = :workspace AND user_id = :user AND status = 'active'");
        $update->execute(['workspace' => $workspaceId, 'user' => $userId]);

        return ['archived' => $update->rowCount() > 0];
    }

    /**
     * The study dates from today up to the day before the exam, without the
     * rest days.
     *
     * @return list<string>
     */
    public static function studyDates(DateTimeImmutable $today, DateTimeImmutable $exam, int $daysPerWeek): array
    {
        $rest = array_slice(self::REST_ORDER, 0, 7 - $daysPerWeek);
        $dates = [];
        for ($day = $today; $day < $exam; $day = $day->modify('+1 day')) {
            if (!in_array((int) $day->format('w'), $rest, true)) {
                $dates[] = $day->format('Y-m-d');
            }
        }

        return $dates;
    }

    /**
     * Lays the units out over the dates: study days packed to an even number
     * of questions, then the consolidation days.
     *
     * @param list<string> $dates
     * @param list<array<string, mixed>> $units each with a weight
     * @return list<array{date:string,items:list<array<string,mixed>>}>
     */
    public static function layout(array $dates, array $units): array
    {
        $count = count($dates);
        $final = $count === 0 ? 0 : max(min(3, $count), min(14, (int) round($count * 0.15)));
        $studyDates = array_slice($dates, 0, $count - $final);
        $finalDates = array_slice($dates, $count - $final);

        $days = [];
        $studyCount = count($studyDates);
        if ($studyCount > 0) {
            $total = array_sum(array_column($units, 'weight'));
            $target = $units === [] ? 0 : max(1, (int) ceil($total / $studyCount));
            $queue = $units;
            foreach ($studyDates as $index => $date) {
                $items = [];
                $load = 0;
                $daysLeft = $studyCount - $index;
                // Fill to the day's share, keep one unit for every day still to come,
                // and let the last study day take whatever is left.
                while ($queue !== [] && ($items === [] || $daysLeft === 1 || ($load < $target && count($queue) > $daysLeft - 1))) {
                    $unit = array_shift($queue);
                    $load += (int) $unit['weight'];
                    unset($unit['weight']);
                    $items[] = $unit;
                }
                $days[] = ['date' => $date, 'items' => $items === [] ? [['kind' => 'review']] : $items];
            }
        }
        foreach ($finalDates as $index => $date) {
            $last = $index === count($finalDates) - 1;
            $days[] = ['date' => $date, 'items' => $last ? [['kind' => 'rest']] : [['kind' => $index % 2 === 0 ? 'mock' : 'review']]];
        }

        return $days;
    }

    /**
     * What there is to study: every subject's topics (most-asked first), the
     * subjects interleaved round-robin, weighted by their questions. With no
     * questions in the bank yet, one reading unit per subject.
     *
     * @return list<array<string, mixed>>
     */
    private function units(string $userId, string $workspaceId): array
    {
        $overview = $this->bank->overview($userId, $workspaceId);
        $lanes = [];
        foreach ($overview['subjects'] as $subject) {
            if ($subject['total'] === 0) {
                continue;
            }
            $lane = [];
            $detail = $this->bank->subject($userId, $workspaceId, (string) $subject['key']);
            foreach ($detail['topics'] as $topic) {
                $lane[] = $topic['key'] === null
                    ? ['kind' => 'subject', 'subject_key' => $subject['key'], 'subject' => $subject['name'], 'questions' => $topic['total'], 'weight' => $topic['total']]
                    : ['kind' => 'topic', 'subject_key' => $subject['key'], 'subject' => $subject['name'], 'topic_key' => $topic['key'], 'topic' => $topic['name'], 'questions' => $topic['total'], 'weight' => $topic['total']];
            }
            $lanes[] = $lane;
        }
        if ($lanes === []) {
            return array_map(static fn (array $subject): array => [
                'kind' => 'reading', 'subject_key' => $subject['key'], 'subject' => $subject['name'], 'weight' => 1,
            ], $overview['subjects']);
        }
        $units = [];
        while ($lanes !== []) {
            foreach ($lanes as $index => &$lane) {
                $units[] = array_shift($lane);
                if ($lane === []) {
                    unset($lanes[$index]);
                }
            }
            unset($lane);
        }

        return $units;
    }

    /** @return array<string, mixed>|null */
    private function active(string $userId, string $workspaceId): ?array
    {
        $query = $this->database->prepare("SELECT id, exam_date, start_date, days_per_week FROM study_plans WHERE workspace_id = :workspace AND user_id = :user AND status = 'active' ORDER BY created_at DESC LIMIT 1");
        $query->execute(['workspace' => $workspaceId, 'user' => $userId]);
        $row = $query->fetch();

        return $row === false ? null : $row;
    }

    private function today(string $workspaceId, ?int $now): DateTimeImmutable
    {
        $query = $this->database->prepare('SELECT timezone_name FROM tenant_workspaces WHERE id = :workspace');
        $query->execute(['workspace' => $workspaceId]);
        $name = (string) ($query->fetchColumn() ?: 'Asia/Tehran');
        try {
            $zone = new DateTimeZone($name);
        } catch (\Exception) {
            $zone = new DateTimeZone('Asia/Tehran');
        }

        return (new DateTimeImmutable('@' . ($now ?? time())))->setTimezone($zone)->setTime(0, 0);
    }
}
