<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Support\PlatformException;
use PDO;

/**
 * تقویم آزمون‌ها: published exams given a window -- when they open, until
 * when they can be sat, and how many have sat them.
 *
 * The window is enforced where an attempt starts (ExamService::startAttempt
 * calls guard()): before it opens nobody can start it; after it closes it
 * can still be practised, but not sat in exam mode, so its ranking
 * (ExamRankingService) stays the ranking of those who sat it in time.
 */
final class ExamScheduleService
{
    public function __construct(
        private readonly PDO $database,
        private readonly AccessGate $access,
        private readonly ?AuditLogger $audit = null,
    ) {
    }

    /**
     * Refuses a start the window does not allow. A no-op for an exam with no window.
     */
    public static function guard(PDO $database, string $workspaceId, string $assessmentId, string $mode, ?int $now = null): void
    {
        $query = $database->prepare('SELECT opens_at, closes_at FROM exam_schedules WHERE assessment_id = :assessment AND workspace_id = :workspace');
        $query->execute(['assessment' => $assessmentId, 'workspace' => $workspaceId]);
        $row = $query->fetch();
        if ($row === false) {
            return;
        }
        $now ??= time();
        if ($now < (int) strtotime($row['opens_at'] . ' UTC')) {
            throw new PlatformException('exam_not_open', 'This exam has not opened yet.', 409);
        }
        if ($mode === 'assessment' && $now > (int) strtotime($row['closes_at'] . ' UTC')) {
            throw new PlatformException('exam_window_closed', 'The window to sit this exam has closed; it can still be practised.', 409);
        }
    }

    /**
     * Scheduled exams the student can see, soonest first within each state.
     *
     * @return list<array<string, mixed>>
     */
    public function calendar(string $userId, string $workspaceId, ?int $now = null): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        $now ??= time();
        $query = $this->database->prepare(<<<'SQL'
SELECT schedule.assessment_id, schedule.opens_at, schedule.closes_at, schedule.note, assessment.title,
       JSON_LENGTH(version.definition_json, '$.questions') AS question_count, policy.time_limit_minutes,
       (SELECT COUNT(DISTINCT attempt.user_id) FROM exam_attempts attempt
        WHERE attempt.assessment_id = schedule.assessment_id AND attempt.workspace_id = schedule.workspace_id
          AND attempt.status = 'scored' AND attempt.mode = 'assessment') AS participants,
       (SELECT result.score_basis_points FROM exam_attempts attempt
        JOIN exam_attempt_results result ON result.attempt_id = attempt.id AND result.workspace_id = attempt.workspace_id
        WHERE attempt.assessment_id = schedule.assessment_id AND attempt.workspace_id = schedule.workspace_id
          AND attempt.user_id = :user AND attempt.status = 'scored' AND attempt.mode = 'assessment'
        ORDER BY attempt.submitted_at LIMIT 1) AS my_score
FROM exam_schedules schedule
JOIN exam_assessments assessment ON assessment.id = schedule.assessment_id AND assessment.workspace_id = schedule.workspace_id
JOIN exam_assessment_versions version ON version.assessment_id = assessment.id
 AND version.workspace_id = assessment.workspace_id AND version.version_no = assessment.current_version_no
JOIN exam_access_policies policy ON policy.assessment_id = assessment.id AND policy.workspace_id = assessment.workspace_id
WHERE schedule.workspace_id = :workspace AND assessment.status = 'published' AND assessment.archived_at IS NULL
  AND assessment.created_for_user_id IS NULL
ORDER BY schedule.opens_at
SQL);
        $query->execute(['workspace' => $workspaceId, 'user' => $userId]);

        return array_map(static function (array $row) use ($now): array {
            $opens = (int) strtotime($row['opens_at'] . ' UTC');
            $closes = (int) strtotime($row['closes_at'] . ' UTC');
            return [
                'assessment_id' => (string) $row['assessment_id'],
                'title' => (string) $row['title'],
                'note' => $row['note'],
                'opens_at' => gmdate(DATE_ATOM, $opens),
                'closes_at' => gmdate(DATE_ATOM, $closes),
                'state' => $now < $opens ? 'upcoming' : ($now > $closes ? 'closed' : 'open'),
                'question_count' => (int) $row['question_count'],
                'time_limit_minutes' => $row['time_limit_minutes'] === null ? null : (int) $row['time_limit_minutes'],
                'participants' => (int) $row['participants'],
                'my_score_percent' => $row['my_score'] === null ? null : intdiv((int) $row['my_score'], 100),
            ];
        }, $query->fetchAll());
    }

    /**
     * Gives a published exam a window (or moves it). Needs exam.manage.
     *
     * @return array{assessment_id:string,opens_at:string,closes_at:string}
     */
    public function schedule(string $actorUserId, string $workspaceId, string $assessmentId, string $opensAt, string $closesAt, ?string $note = null): array
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'exam.manage');
        $opens = strtotime($opensAt);
        $closes = strtotime($closesAt);
        if ($opens === false || $closes === false || $closes <= $opens) {
            throw new PlatformException('exam_schedule_invalid', 'The window must close after it opens.', 422);
        }
        if ($closes - $opens > 366 * 86400) {
            throw new PlatformException('exam_schedule_invalid', 'A window is at most a year.', 422);
        }
        $exam = $this->database->prepare("SELECT 1 FROM exam_assessments WHERE id = :assessment AND workspace_id = :workspace AND status = 'published' AND archived_at IS NULL AND created_for_user_id IS NULL");
        $exam->execute(['assessment' => $assessmentId, 'workspace' => $workspaceId]);
        if ($exam->fetchColumn() === false) {
            throw new PlatformException('assessment_not_found', 'Published assessment was not found.', 404);
        }
        $note = $note === null || trim($note) === '' ? null : mb_substr(trim($note), 0, 300);
        $this->database->prepare(<<<'SQL'
INSERT INTO exam_schedules (assessment_id, workspace_id, opens_at, closes_at, note, created_by_user_id, created_at, updated_at)
VALUES (:assessment, :workspace, :opens, :closes, :note, :actor, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
ON DUPLICATE KEY UPDATE opens_at = VALUES(opens_at), closes_at = VALUES(closes_at), note = VALUES(note), updated_at = VALUES(updated_at)
SQL)->execute(['assessment' => $assessmentId, 'workspace' => $workspaceId, 'opens' => gmdate('Y-m-d H:i:s', $opens), 'closes' => gmdate('Y-m-d H:i:s', $closes), 'note' => $note, 'actor' => $actorUserId]);
        $this->audit?->record($workspaceId, $actorUserId, 'exam.schedule.set', 'exam_assessment', $assessmentId, 'success', ['opens_at' => gmdate(DATE_ATOM, $opens), 'closes_at' => gmdate(DATE_ATOM, $closes)]);

        return ['assessment_id' => $assessmentId, 'opens_at' => gmdate(DATE_ATOM, $opens), 'closes_at' => gmdate(DATE_ATOM, $closes)];
    }

    /** @return array{removed:bool} */
    public function unschedule(string $actorUserId, string $workspaceId, string $assessmentId): array
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'exam.manage');
        $delete = $this->database->prepare('DELETE FROM exam_schedules WHERE assessment_id = :assessment AND workspace_id = :workspace');
        $delete->execute(['assessment' => $assessmentId, 'workspace' => $workspaceId]);
        $this->audit?->record($workspaceId, $actorUserId, 'exam.schedule.remove', 'exam_assessment', $assessmentId, 'success', []);

        return ['removed' => $delete->rowCount() > 0];
    }
}
