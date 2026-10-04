<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;

/**
 * A student's own tools on a question: bookmarks (ذخیره)، notes (یادداشت)
 * and error reports (گزارش اشکال) -- plus the reviewers' queue of reports.
 *
 * Everything is keyed by the stable question id, so a bookmark or a note
 * follows the question into every exam and attempt it appears in. A tool
 * can only be used on a question of an exam the student can see (published
 * and not someone else's personal set), and the saved list shows a
 * question's opening words only -- never its answer -- read back from the
 * exam it was saved from.
 */
final class QuestionToolsService
{
    public const NOTE_MAX = 1000;
    public const REPORT_MAX = 1000;
    public const REPORT_KINDS = ['question', 'answer', 'explanation', 'other'];
    /** Reports one student may send in a day: enough for real mistakes, not a flood. */
    public const REPORTS_PER_DAY = 30;
    private const PREVIEW = 220;
    public const MAX_RANGES = 30;
    /** One highlight is a phrase or a sentence, not the stem. */
    public const MAX_FRAGMENT = 300;

    public function __construct(
        private readonly PDO $database,
        private readonly AccessGate $access,
        private readonly AuditLogger $audit,
        private readonly ?CustomPracticeService $practice = null,
    ) {
    }

    /**
     * This exam's questions the student has bookmarked or noted, for the runner.
     *
     * @return array{bookmarks:list<string>,notes:array<string,string>,highlights:array<string,list<array{start:int,end:int}>>}
     */
    public function forAssessment(string $userId, string $workspaceId, string $assessmentId): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        $keys = array_keys($this->questions($userId, $workspaceId, $assessmentId));
        if ($keys === []) {
            return ['bookmarks' => [], 'notes' => [], 'highlights' => []];
        }
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $bookmarks = $this->database->prepare("SELECT question_key FROM exam_question_bookmarks WHERE workspace_id = ? AND user_id = ? AND question_key IN ({$placeholders})");
        $bookmarks->execute([$workspaceId, $userId, ...$keys]);
        $notes = $this->database->prepare("SELECT question_key, body FROM exam_question_notes WHERE workspace_id = ? AND user_id = ? AND question_key IN ({$placeholders})");
        $notes->execute([$workspaceId, $userId, ...$keys]);
        $marks = $this->database->prepare("SELECT question_key, ranges_json FROM exam_question_highlights WHERE workspace_id = ? AND user_id = ? AND question_key IN ({$placeholders})");
        $marks->execute([$workspaceId, $userId, ...$keys]);
        $highlights = [];
        foreach ($marks->fetchAll() as $row) {
            // Through mergeRanges: MySQL stores JSON objects with their keys reordered.
            $highlights[(string) $row['question_key']] = self::mergeRanges(json_decode((string) $row['ranges_json'], true, 8, JSON_THROW_ON_ERROR) ?? [], PHP_INT_MAX);
        }

        return [
            'bookmarks' => array_map('strval', $bookmarks->fetchAll(PDO::FETCH_COLUMN)),
            'notes' => array_map('strval', array_column($notes->fetchAll(), 'body', 'question_key')),
            'highlights' => $highlights,
        ];
    }

    /** @return array{bookmarked:bool} */
    public function setBookmark(string $userId, string $workspaceId, string $assessmentId, string $questionKey, bool $on): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        $this->requireQuestion($userId, $workspaceId, $assessmentId, $questionKey);
        if ($on) {
            $this->database->prepare(<<<'SQL'
INSERT INTO exam_question_bookmarks (workspace_id, user_id, question_key, assessment_id, created_at)
VALUES (:workspace, :user, :question, :assessment, UTC_TIMESTAMP(6))
ON DUPLICATE KEY UPDATE assessment_id = VALUES(assessment_id)
SQL)->execute(['workspace' => $workspaceId, 'user' => $userId, 'question' => $questionKey, 'assessment' => $assessmentId]);
        } else {
            $this->database->prepare('DELETE FROM exam_question_bookmarks WHERE workspace_id = :workspace AND user_id = :user AND question_key = :question')
                ->execute(['workspace' => $workspaceId, 'user' => $userId, 'question' => $questionKey]);
        }

        return ['bookmarked' => $on];
    }

    /** An empty note deletes it. @return array{saved:bool} */
    public function saveNote(string $userId, string $workspaceId, string $assessmentId, string $questionKey, string $body): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        $this->requireQuestion($userId, $workspaceId, $assessmentId, $questionKey);
        $body = trim($body);
        if (mb_strlen($body) > self::NOTE_MAX) {
            throw new PlatformException('question_note_too_long', 'A note can be up to 1000 characters.', 422);
        }
        if ($body === '') {
            $this->database->prepare('DELETE FROM exam_question_notes WHERE workspace_id = :workspace AND user_id = :user AND question_key = :question')
                ->execute(['workspace' => $workspaceId, 'user' => $userId, 'question' => $questionKey]);
            return ['saved' => false];
        }
        $this->database->prepare(<<<'SQL'
INSERT INTO exam_question_notes (workspace_id, user_id, question_key, assessment_id, body, updated_at)
VALUES (:workspace, :user, :question, :assessment, :body, UTC_TIMESTAMP(6))
ON DUPLICATE KEY UPDATE body = VALUES(body), assessment_id = VALUES(assessment_id), updated_at = VALUES(updated_at)
SQL)->execute(['workspace' => $workspaceId, 'user' => $userId, 'question' => $questionKey, 'assessment' => $assessmentId, 'body' => $body]);

        return ['saved' => true];
    }

    /**
     * Replaces a question's highlights with these ranges (offsets into its
     * stem, as the runner shows it). Ranges are merged, clamped to the stem,
     * and refused when one is longer than a sentence. None deletes them.
     *
     * @param list<mixed> $ranges
     * @return array{ranges:list<array{start:int,end:int}>}
     */
    public function saveHighlights(string $userId, string $workspaceId, string $assessmentId, string $questionKey, array $ranges): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        $this->requireQuestion($userId, $workspaceId, $assessmentId, $questionKey);
        $question = $this->questions($userId, $workspaceId, $assessmentId)[$questionKey];
        $merged = self::mergeRanges($ranges, mb_strlen((string) ($question['prompt'] ?? '')));
        if (count($merged) > self::MAX_RANGES) {
            throw new PlatformException('question_highlights_too_many', 'Too many highlights on one question.', 422);
        }
        foreach ($merged as $range) {
            if ($range['end'] - $range['start'] > self::MAX_FRAGMENT) {
                throw new PlatformException('question_highlight_too_long', 'A highlight is a phrase, not the whole question.', 422);
            }
        }
        if ($merged === []) {
            $this->database->prepare('DELETE FROM exam_question_highlights WHERE workspace_id = :workspace AND user_id = :user AND question_key = :question')
                ->execute(['workspace' => $workspaceId, 'user' => $userId, 'question' => $questionKey]);
            return ['ranges' => []];
        }
        $this->database->prepare(<<<'SQL'
INSERT INTO exam_question_highlights (workspace_id, user_id, question_key, assessment_id, ranges_json, updated_at)
VALUES (:workspace, :user, :question, :assessment, :ranges, UTC_TIMESTAMP(6))
ON DUPLICATE KEY UPDATE ranges_json = VALUES(ranges_json), assessment_id = VALUES(assessment_id), updated_at = VALUES(updated_at)
SQL)->execute(['workspace' => $workspaceId, 'user' => $userId, 'question' => $questionKey, 'assessment' => $assessmentId, 'ranges' => json_encode($merged, JSON_THROW_ON_ERROR)]);

        return ['ranges' => $merged];
    }

    /**
     * The same normalisation as the runner's mergeRanges (runner-study.js):
     * clamp, drop empty, sort, merge overlapping or touching ranges.
     *
     * @param list<mixed> $ranges
     * @return list<array{start:int,end:int}>
     */
    public static function mergeRanges(array $ranges, int $length): array
    {
        $clean = [];
        foreach ($ranges as $range) {
            if (!is_array($range) || !is_numeric($range['start'] ?? null) || !is_numeric($range['end'] ?? null)) {
                continue;
            }
            $start = max(0, min($length, (int) $range['start']));
            $end = max(0, min($length, (int) $range['end']));
            if ($end > $start) {
                $clean[] = ['start' => $start, 'end' => $end];
            }
        }
        usort($clean, static fn (array $a, array $b): int => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);
        $merged = [];
        foreach ($clean as $range) {
            $last = count($merged) - 1;
            if ($last >= 0 && $range['start'] <= $merged[$last]['end']) {
                $merged[$last]['end'] = max($merged[$last]['end'], $range['end']);
                continue;
            }
            $merged[] = $range;
        }

        return $merged;
    }

    /** @return array{report_id:string} */
    public function report(string $userId, string $workspaceId, string $assessmentId, string $questionKey, string $kind, string $body): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        $this->requireQuestion($userId, $workspaceId, $assessmentId, $questionKey);
        if (!in_array($kind, self::REPORT_KINDS, true)) {
            throw new PlatformException('question_report_kind_invalid', 'Report kind is invalid.', 422);
        }
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > self::REPORT_MAX) {
            throw new PlatformException('question_report_body_invalid', 'Describe the mistake in up to 1000 characters.', 422);
        }
        $today = $this->database->prepare('SELECT COUNT(*) FROM exam_question_reports WHERE workspace_id = :workspace AND user_id = :user AND created_at >= UTC_TIMESTAMP(6) - INTERVAL 1 DAY');
        $today->execute(['workspace' => $workspaceId, 'user' => $userId]);
        if ((int) $today->fetchColumn() >= self::REPORTS_PER_DAY) {
            throw new PlatformException('question_report_limit', 'That is enough reports for today; thank you.', 429);
        }
        $id = Uuid::v7();
        $this->database->prepare(<<<'SQL'
INSERT INTO exam_question_reports (id, workspace_id, user_id, question_key, assessment_id, kind, body, status, created_at)
VALUES (:id, :workspace, :user, :question, :assessment, :kind, :body, 'open', UTC_TIMESTAMP(6))
SQL)->execute(['id' => $id, 'workspace' => $workspaceId, 'user' => $userId, 'question' => $questionKey, 'assessment' => $assessmentId, 'kind' => $kind, 'body' => $body]);
        $this->audit->record($workspaceId, $userId, 'exam.question.report', 'exam_question_report', $id, 'success', ['question' => $questionKey, 'kind' => $kind]);

        return ['report_id' => $id];
    }

    /**
     * The student's bookmarks and notes, newest first, each with where it
     * was saved, its topic and the opening words of the question.
     *
     * @return array{bookmarks:list<array<string,mixed>>,notes:list<array<string,mixed>>,highlights:list<array<string,mixed>>}
     */
    public function saved(string $userId, string $workspaceId): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        $bookmarks = $this->database->prepare('SELECT question_key, assessment_id, created_at AS at, NULL AS body FROM exam_question_bookmarks WHERE workspace_id = :workspace AND user_id = :user ORDER BY created_at DESC LIMIT 500');
        $bookmarks->execute(['workspace' => $workspaceId, 'user' => $userId]);
        $notes = $this->database->prepare('SELECT question_key, assessment_id, updated_at AS at, body FROM exam_question_notes WHERE workspace_id = :workspace AND user_id = :user ORDER BY updated_at DESC LIMIT 500');
        $notes->execute(['workspace' => $workspaceId, 'user' => $userId]);
        $marks = $this->database->prepare('SELECT question_key, assessment_id, updated_at AS at, NULL AS body, ranges_json FROM exam_question_highlights WHERE workspace_id = :workspace AND user_id = :user ORDER BY updated_at DESC LIMIT 300');
        $marks->execute(['workspace' => $workspaceId, 'user' => $userId]);

        $definitions = [];
        $shape = function (array $row) use ($userId, $workspaceId, &$definitions): ?array {
            $assessmentId = (string) $row['assessment_id'];
            $definitions[$assessmentId] ??= $this->visibleAssessment($userId, $workspaceId, $assessmentId);
            $exam = $definitions[$assessmentId];
            $question = $exam === null ? null : ($exam['questions'][(string) $row['question_key']] ?? null);
            if ($question === null) {
                return null; // the exam is gone or no longer visible to them
            }
            $prompt = (string) ($question['prompt'] ?? '');
            $fragments = [];
            if (isset($row['ranges_json'])) {
                foreach (self::mergeRanges(json_decode((string) $row['ranges_json'], true, 8, JSON_THROW_ON_ERROR) ?? [], mb_strlen($prompt)) as $range) {
                    $fragments[] = mb_substr($prompt, $range['start'], min(self::MAX_FRAGMENT, $range['end'] - $range['start']));
                }
            }
            return [
                'question_id' => (string) $row['question_key'],
                'assessment_id' => $assessmentId,
                'assessment_title' => $exam['title'],
                'topic' => isset($question['topic']) ? (string) $question['topic'] : null,
                'subject' => isset($question['tags'][0]) ? (string) $question['tags'][0] : null,
                'preview' => mb_strlen($prompt) > self::PREVIEW ? mb_substr($prompt, 0, self::PREVIEW) . '…' : $prompt,
                'note' => $row['body'] === null ? null : (string) $row['body'],
                'fragments' => $fragments,
                'at' => gmdate(DATE_ATOM, (int) strtotime($row['at'] . ' UTC')),
            ];
        };

        return [
            'bookmarks' => array_values(array_filter(array_map($shape, $bookmarks->fetchAll()))),
            'notes' => array_values(array_filter(array_map($shape, $notes->fetchAll()))),
            'highlights' => array_values(array_filter(array_map($shape, $marks->fetchAll()), static fn (?array $item): bool => $item !== null && $item['fragments'] !== [])),
        ];
    }

    /**
     * The bookmarked questions as one study set (optionally one topic's).
     *
     * @return array{assessment_id:string,title:string,question_count:int}
     */
    public function studyBookmarks(string $userId, string $workspaceId, ?string $topic = null): array
    {
        if ($this->practice === null) {
            throw new PlatformException('custom_practice_unavailable', 'Study sets are not available.', 503);
        }
        $saved = $this->saved($userId, $workspaceId)['bookmarks'];
        if ($topic !== null && $topic !== '') {
            $saved = array_values(array_filter($saved, static fn (array $item): bool => $item['topic'] === $topic));
        }
        if ($saved === []) {
            throw new PlatformException('custom_practice_too_few', 'No saved question matches.', 422);
        }

        return $this->practice->createFromQuestions($userId, $workspaceId, array_column($saved, 'question_id'), 'سؤال‌های ذخیره‌شده' . ($topic ? ' · ' . $topic : ''), 'bookmarks', false);
    }

    /**
     * The reviewers' queue: reports in this workspace, open first.
     *
     * @return list<array<string, mixed>>
     */
    public function reports(string $actorUserId, string $workspaceId, string $status = 'open'): array
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'exam.review');
        if (!in_array($status, ['open', 'resolved', 'rejected'], true)) {
            throw new PlatformException('question_report_status_invalid', 'Status is invalid.', 422);
        }
        $query = $this->database->prepare(<<<'SQL'
SELECT report.id, report.question_key, report.assessment_id, report.kind, report.body, report.status, report.resolution,
       report.created_at, report.resolved_at, assessment.title, user.display_name
FROM exam_question_reports report
JOIN exam_assessments assessment ON assessment.id = report.assessment_id
JOIN iam_users user ON user.id = report.user_id
WHERE report.workspace_id = :workspace AND report.status = :status
ORDER BY report.created_at DESC
LIMIT 200
SQL);
        $query->execute(['workspace' => $workspaceId, 'status' => $status]);
        $definitions = [];

        return array_map(function (array $row) use (&$definitions, $workspaceId): array {
            $assessmentId = (string) $row['assessment_id'];
            $definitions[$assessmentId] ??= $this->definition($workspaceId, $assessmentId);
            $question = $definitions[$assessmentId][(string) $row['question_key']] ?? null;
            return [
                'id' => (string) $row['id'],
                'question_id' => (string) $row['question_key'],
                'assessment_id' => $assessmentId,
                'assessment_title' => (string) $row['title'],
                'reporter' => (string) $row['display_name'],
                'kind' => (string) $row['kind'],
                'body' => (string) $row['body'],
                'status' => (string) $row['status'],
                'resolution' => $row['resolution'],
                'prompt' => $question === null ? null : (string) ($question['prompt'] ?? ''),
                'choices' => $question === null ? [] : array_map('strval', (array) ($question['choices'] ?? [])),
                'answer' => $question === null || !isset($question['answer']) ? null : (int) $question['answer'],
                'created_at' => gmdate(DATE_ATOM, (int) strtotime($row['created_at'] . ' UTC')),
            ];
        }, $query->fetchAll());
    }

    /** @return array{status:string} */
    public function resolveReport(string $actorUserId, string $workspaceId, string $reportId, string $status, string $resolution): array
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'exam.review');
        if (!in_array($status, ['resolved', 'rejected'], true)) {
            throw new PlatformException('question_report_status_invalid', 'Status is invalid.', 422);
        }
        $update = $this->database->prepare(<<<'SQL'
UPDATE exam_question_reports SET status = :status, resolution = :resolution, resolved_by_user_id = :actor, resolved_at = UTC_TIMESTAMP(6)
WHERE id = :id AND workspace_id = :workspace
SQL);
        $update->execute(['status' => $status, 'resolution' => mb_substr(trim($resolution), 0, 1000) ?: null, 'actor' => $actorUserId, 'id' => $reportId, 'workspace' => $workspaceId]);
        if ($update->rowCount() === 0) {
            throw new PlatformException('question_report_not_found', 'Report was not found.', 404);
        }
        $this->audit->record($workspaceId, $actorUserId, 'exam.question.report.resolve', 'exam_question_report', $reportId, 'success', ['status' => $status]);

        return ['status' => $status];
    }

    private function requireQuestion(string $userId, string $workspaceId, string $assessmentId, string $questionKey): void
    {
        if (preg_match('/^[0-9a-f-]{36}$/', $assessmentId) !== 1 || !isset($this->questions($userId, $workspaceId, $assessmentId)[$questionKey])) {
            throw new PlatformException('question_not_found', 'That question is not in this exam.', 404);
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function questions(string $userId, string $workspaceId, string $assessmentId): array
    {
        return $this->visibleAssessment($userId, $workspaceId, $assessmentId)['questions'] ?? [];
    }

    /**
     * The exam's title and questions, if the student can see it: published,
     * in this workspace, and either for everyone or their own set.
     *
     * @return ?array{title:string,questions:array<string,array<string,mixed>>}
     */
    private function visibleAssessment(string $userId, string $workspaceId, string $assessmentId): ?array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT assessment.title, version.definition_json
FROM exam_assessments assessment
JOIN exam_assessment_versions version ON version.assessment_id = assessment.id
 AND version.workspace_id = assessment.workspace_id AND version.version_no = assessment.current_version_no
WHERE assessment.id = :assessment AND assessment.workspace_id = :workspace AND assessment.status = 'published'
  AND assessment.archived_at IS NULL AND (assessment.created_for_user_id IS NULL OR assessment.created_for_user_id = :user)
SQL);
        $query->execute(['assessment' => $assessmentId, 'workspace' => $workspaceId, 'user' => $userId]);
        $row = $query->fetch();
        if ($row === false) {
            return null;
        }

        return ['title' => (string) $row['title'], 'questions' => self::byId((string) $row['definition_json'])];
    }

    /** @return array<string, array<string, mixed>> */
    private function definition(string $workspaceId, string $assessmentId): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT version.definition_json FROM exam_assessments assessment
JOIN exam_assessment_versions version ON version.assessment_id = assessment.id
 AND version.workspace_id = assessment.workspace_id AND version.version_no = assessment.current_version_no
WHERE assessment.id = :assessment AND assessment.workspace_id = :workspace
SQL);
        $query->execute(['assessment' => $assessmentId, 'workspace' => $workspaceId]);
        $json = $query->fetchColumn();

        return $json === false ? [] : self::byId((string) $json);
    }

    /** @return array<string, array<string, mixed>> */
    private static function byId(string $definitionJson): array
    {
        $out = [];
        foreach (json_decode($definitionJson, true, 64, JSON_THROW_ON_ERROR)['questions'] ?? [] as $question) {
            $out[(string) $question['id']] = $question;
        }

        return $out;
    }
}
