<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

use DateTimeImmutable;
use DateTimeZone;
use Fanoos\Platform\Authorization\AccessGate;
use PDO;

/**
 * داشبورد پیشرفت: one student's own progress in one workspace.
 *
 * Built from two sources that already exist: their scored attempts (what
 * they did, when, and how it went) and exam_question_user_stats (their
 * record per question, filed under the course and topic it was asked in --
 * see QuestionStatsRecorder). Nothing here is about anyone else, and nothing
 * is stored: it is read fresh on every visit.
 *
 * Days are the workspace's days (its timezone), because "you studied three
 * days in a row" has to mean the student's evenings, not UTC's.
 */
final class ProgressService
{
    public const ACTIVITY_DAYS = 84;
    private const TREND_ATTEMPTS = 30;
    private const RECENT_ATTEMPTS = 8;
    private const WEAK_TOPICS = 8;
    /** A topic needs this many answers before it can be called weak -- two wrong answers are not a pattern. */
    private const WEAK_MINIMUM = 5;

    public function __construct(
        private readonly PDO $database,
        private readonly AccessGate $access,
    ) {
    }

    /** @return array<string, mixed> */
    public function progress(string $userId, string $workspaceId, ?int $now = null): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        $timezone = $this->timezone($workspaceId);
        $today = (new DateTimeImmutable('@' . ($now ?? time())))->setTimezone($timezone)->setTime(0, 0);

        $attempts = $this->attempts($userId, $workspaceId);
        $days = [];
        $answers = 0;
        $correct = 0;
        foreach ($attempts as $attempt) {
            $day = (new DateTimeImmutable($attempt['submitted_at'] . ' UTC'))->setTimezone($timezone)->format('Y-m-d');
            $days[$day] ??= ['answered' => 0, 'correct' => 0, 'attempts' => 0, 'minutes' => 0];
            $days[$day]['answered'] += $attempt['answered'];
            $days[$day]['correct'] += $attempt['correct'];
            $days[$day]['minutes'] += $attempt['minutes'];
            ++$days[$day]['attempts'];
            $answers += $attempt['answered'];
            $correct += $attempt['correct'];
        }

        // Timed focus blocks (تایمر مطالعه) count as study time, but not as
        // a study day of their own for the streak: the streak is about exams.
        $timed = [];
        foreach ($this->studySessions($userId, $workspaceId) as $session) {
            $day = (new DateTimeImmutable($session['ended_at'] . ' UTC'))->setTimezone($timezone)->format('Y-m-d');
            $timed[$day] = ($timed[$day] ?? 0) + $session['minutes'];
        }
        $minutes = 0;
        foreach (array_unique([...array_keys($days), ...array_keys($timed)]) as $day) {
            $minutes += ($days[$day]['minutes'] ?? 0) + ($timed[$day] ?? 0);
        }

        $activity = [];
        for ($offset = self::ACTIVITY_DAYS - 1; $offset >= 0; $offset--) {
            $date = $today->modify("-{$offset} days")->format('Y-m-d');
            $entry = ['date' => $date] + ($days[$date] ?? ['answered' => 0, 'correct' => 0, 'attempts' => 0, 'minutes' => 0]);
            $entry['minutes'] += $timed[$date] ?? 0;
            $activity[] = $entry;
        }

        $questions = $this->questionTotals($userId, $workspaceId);

        return [
            'totals' => [
                'attempts' => count($attempts),
                'answers' => $answers,
                'correct_percent' => $answers === 0 ? null : (int) round($correct * 100 / $answers),
                'questions_seen' => $questions['seen'],
                'questions_mastered' => $questions['mastered'],
                'study_days' => count($days),
                'study_minutes' => $minutes,
                'today_minutes' => ($days[$today->format('Y-m-d')]['minutes'] ?? 0) + ($timed[$today->format('Y-m-d')] ?? 0),
            ],
            'streak' => $this->streak(array_keys($days), $today),
            'activity' => $activity,
            'trend' => array_map(fn (array $attempt): array => [
                'date' => $this->iso($attempt['submitted_at']),
                'score_percent' => intdiv($attempt['score_basis_points'], 100),
                'title' => $attempt['title'],
            ], array_slice($attempts, -self::TREND_ATTEMPTS)),
            'recent' => array_map(fn (array $attempt): array => [
                'assessment_id' => $attempt['assessment_id'],
                'title' => $attempt['title'],
                'kind' => $attempt['kind'],
                'mode' => $attempt['mode'],
                'date' => $this->iso($attempt['submitted_at']),
                'correct_count' => $attempt['correct'],
                'question_count' => $attempt['question_count'],
                'score_percent' => intdiv($attempt['score_basis_points'], 100),
            ], array_reverse(array_slice($attempts, -self::RECENT_ATTEMPTS))),
            'courses' => $this->courses($userId, $workspaceId),
            'weak_topics' => $this->weakTopics($userId, $workspaceId),
        ];
    }

    /**
     * Oldest first. `answered` and `correct` leave out blanks, like the
     * per-question counters do.
     *
     * @return list<array{assessment_id:string,title:string,kind:string,mode:string,submitted_at:string,answered:int,correct:int,question_count:int,score_basis_points:int}>
     */
    private function attempts(string $userId, string $workspaceId): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT attempt.assessment_id, attempt.mode, attempt.started_at, attempt.submitted_at, assessment.title,
       COALESCE(metadata.assessment_variant, metadata.assessment_kind, 'practice') AS kind,
       result.correct_count, result.question_count, result.score_basis_points, attempt.answers_json
FROM exam_attempts attempt
JOIN exam_attempt_results result ON result.attempt_id = attempt.id AND result.workspace_id = attempt.workspace_id
JOIN exam_assessments assessment ON assessment.id = attempt.assessment_id AND assessment.workspace_id = attempt.workspace_id
LEFT JOIN exam_assessment_metadata metadata ON metadata.assessment_id = attempt.assessment_id AND metadata.workspace_id = attempt.workspace_id
WHERE attempt.workspace_id = :workspace AND attempt.user_id = :user AND attempt.status = 'scored' AND attempt.submitted_at IS NOT NULL
ORDER BY attempt.submitted_at, attempt.id
SQL);
        $query->execute(['workspace' => $workspaceId, 'user' => $userId]);
        $attempts = [];
        while (($row = $query->fetch()) !== false) {
            // answers_json holds exactly the answered questions (scoring
            // stores the normalised answers), so its size is the answered count.
            $answers = json_decode((string) ($row['answers_json'] ?? '{}'), true);
            $attempts[] = [
                'assessment_id' => (string) $row['assessment_id'],
                'title' => (string) $row['title'],
                'kind' => (string) $row['kind'],
                'mode' => (string) $row['mode'],
                'submitted_at' => (string) $row['submitted_at'],
                'answered' => is_array($answers) ? count($answers) : 0,
                'correct' => (int) $row['correct_count'],
                'question_count' => (int) $row['question_count'],
                'score_basis_points' => (int) $row['score_basis_points'],
                'minutes' => self::attemptMinutes((string) $row['started_at'], (string) $row['submitted_at'], (int) $row['question_count']),
            ];
        }

        return $attempts;
    }

    /**
     * Time spent in an attempt, as study time. An attempt left open
     * overnight is not a night of study: it is capped at three minutes a
     * question and three hours in all.
     */
    public static function attemptMinutes(string $startedAt, string $submittedAt, int $questionCount): int
    {
        $seconds = max(0, (int) strtotime($submittedAt . ' UTC') - (int) strtotime($startedAt . ' UTC'));

        return (int) min(ceil($seconds / 60), max(1, $questionCount) * 3, 180);
    }

    /** @return list<array{minutes:int,ended_at:string}> */
    private function studySessions(string $userId, string $workspaceId): array
    {
        $query = $this->database->prepare('SELECT minutes, ended_at FROM study_sessions WHERE workspace_id = :workspace AND user_id = :user ORDER BY ended_at');
        $query->execute(['workspace' => $workspaceId, 'user' => $userId]);

        return array_map(static fn (array $row): array => ['minutes' => (int) $row['minutes'], 'ended_at' => (string) $row['ended_at']], $query->fetchAll());
    }

    /** @return array{seen:int,mastered:int} */
    private function questionTotals(string $userId, string $workspaceId): array
    {
        $query = $this->database->prepare('SELECT COUNT(*) AS seen, COALESCE(SUM(last_correct), 0) AS mastered FROM exam_question_user_stats WHERE workspace_id = :workspace AND user_id = :user');
        $query->execute(['workspace' => $workspaceId, 'user' => $userId]);
        $row = $query->fetch() ?: [];

        return ['seen' => (int) ($row['seen'] ?? 0), 'mastered' => (int) ($row['mastered'] ?? 0)];
    }

    /**
     * Per course the student has answered in: how many of its questions they
     * have seen (of how many the course's exams hold), how often they were
     * right, and how many they last got right.
     *
     * @return list<array<string, mixed>>
     */
    private function courses(string $userId, string $workspaceId): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT stats.course_id, course.title,
       COUNT(*) AS seen, SUM(stats.last_correct) AS mastered,
       SUM(stats.answered_count) AS answers, SUM(stats.correct_count) AS correct
FROM exam_question_user_stats stats
JOIN academic_courses course ON course.id = stats.course_id AND course.workspace_id = stats.workspace_id
WHERE stats.workspace_id = :workspace AND stats.user_id = :user
GROUP BY stats.course_id, course.title
ORDER BY seen DESC
LIMIT 60
SQL);
        $query->execute(['workspace' => $workspaceId, 'user' => $userId]);
        $rows = $query->fetchAll();
        $totals = $this->courseQuestionTotals($workspaceId, array_map(static fn (array $row): string => (string) $row['course_id'], $rows));

        return array_map(static function (array $row) use ($totals): array {
            $answers = (int) $row['answers'];

            return [
                'course_id' => (string) $row['course_id'],
                'title' => (string) $row['title'],
                'questions_seen' => (int) $row['seen'],
                'questions_total' => $totals[(string) $row['course_id']] ?? null,
                'questions_mastered' => (int) $row['mastered'],
                'answers' => $answers,
                'correct_percent' => $answers === 0 ? null : (int) round(((int) $row['correct']) * 100 / $answers),
            ];
        }, $rows);
    }

    /**
     * How many questions each course's published exams hold. A question that
     * appears in two exams of a course is counted twice here, so this is an
     * upper bound; the page words it as "of about".
     *
     * @param list<string> $courseIds
     * @return array<string, int>
     */
    private function courseQuestionTotals(string $workspaceId, array $courseIds): array
    {
        if ($courseIds === []) {
            return [];
        }
        $placeholders = implode(',', array_map(static fn (int $i): string => ':course' . $i, array_keys($courseIds)));
        $query = $this->database->prepare(<<<SQL
SELECT metadata.course_id, SUM(JSON_LENGTH(version.definition_json, '$.questions')) AS total
FROM exam_assessments assessment
JOIN exam_assessment_metadata metadata ON metadata.assessment_id = assessment.id AND metadata.workspace_id = assessment.workspace_id
JOIN exam_assessment_versions version ON version.assessment_id = assessment.id
 AND version.workspace_id = assessment.workspace_id AND version.version_no = assessment.current_version_no
WHERE assessment.workspace_id = :workspace AND assessment.status = 'published' AND assessment.archived_at IS NULL
  AND assessment.created_for_user_id IS NULL AND metadata.course_id IN ({$placeholders})
GROUP BY metadata.course_id
SQL);
        $parameters = ['workspace' => $workspaceId];
        foreach ($courseIds as $i => $courseId) {
            $parameters['course' . $i] = $courseId;
        }
        $query->execute($parameters);
        $totals = [];
        foreach ($query->fetchAll() as $row) {
            $totals[(string) $row['course_id']] = (int) $row['total'];
        }

        return $totals;
    }

    /**
     * The topics this student gets wrong most often, among those with enough
     * answers to say so.
     *
     * @return list<array{course_id:string,course_title:string,topic:string,answers:int,correct_percent:int}>
     */
    private function weakTopics(string $userId, string $workspaceId): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT stats.course_id, course.title AS course_title, stats.topic,
       SUM(stats.answered_count) AS answers, SUM(stats.correct_count) AS correct
FROM exam_question_user_stats stats
JOIN academic_courses course ON course.id = stats.course_id AND course.workspace_id = stats.workspace_id
WHERE stats.workspace_id = :workspace AND stats.user_id = :user AND stats.topic IS NOT NULL
GROUP BY stats.course_id, course.title, stats.topic
HAVING SUM(stats.answered_count) >= :minimum AND SUM(stats.correct_count) < SUM(stats.answered_count)
ORDER BY SUM(stats.correct_count) / SUM(stats.answered_count), SUM(stats.answered_count) DESC
LIMIT :limit
SQL);
        $query->bindValue('workspace', $workspaceId);
        $query->bindValue('user', $userId);
        $query->bindValue('minimum', self::WEAK_MINIMUM, PDO::PARAM_INT);
        $query->bindValue('limit', self::WEAK_TOPICS, PDO::PARAM_INT);
        $query->execute();

        return array_map(static fn (array $row): array => [
            'course_id' => (string) $row['course_id'],
            'course_title' => (string) $row['course_title'],
            'topic' => (string) $row['topic'],
            'answers' => (int) $row['answers'],
            'correct_percent' => (int) round(((int) $row['correct']) * 100 / max(1, (int) $row['answers'])),
        ], $query->fetchAll());
    }

    /**
     * Consecutive days with at least one finished exam, ending today -- or
     * yesterday, so a streak is not shown as broken in the morning before
     * the day's first exam.
     *
     * @param list<string> $days Y-m-d, any order
     * @return array{current:int,best:int,today:bool}
     */
    public static function streak(array $days, DateTimeImmutable $today): array
    {
        $set = array_fill_keys($days, true);
        sort($days);
        $best = 0;
        $run = 0;
        $previous = null;
        foreach ($days as $day) {
            $date = new DateTimeImmutable($day, $today->getTimezone());
            $run = $previous !== null && $previous->modify('+1 day')->format('Y-m-d') === $day ? $run + 1 : 1;
            $best = max($best, $run);
            $previous = $date;
        }

        $studiedToday = isset($set[$today->format('Y-m-d')]);
        $cursor = $studiedToday ? $today : $today->modify('-1 day');
        $current = 0;
        while (isset($set[$cursor->format('Y-m-d')])) {
            ++$current;
            $cursor = $cursor->modify('-1 day');
        }

        return ['current' => $current, 'best' => $best, 'today' => $studiedToday];
    }

    private function timezone(string $workspaceId): DateTimeZone
    {
        $query = $this->database->prepare('SELECT timezone_name FROM tenant_workspaces WHERE id = :workspace');
        $query->execute(['workspace' => $workspaceId]);
        try {
            return new DateTimeZone((string) ($query->fetchColumn() ?: 'Asia/Tehran'));
        } catch (\Exception) {
            return new DateTimeZone('Asia/Tehran');
        }
    }

    private function iso(string $mysqlDatetime): string
    {
        return gmdate(DATE_ATOM, (int) strtotime($mysqlDatetime . ' UTC'));
    }
}
