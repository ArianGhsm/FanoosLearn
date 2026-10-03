<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Support\PlatformException;
use PDO;

/**
 * کارنامه: where a student stands among everyone who sat the same exam.
 *
 * Only exam-mode attempts count (learning and practice show answers as you
 * go), and only each person's first one, so a retake after reading the
 * explanations does not crowd the top. Personal sets have no ranking. Below
 * MIN_PARTICIPANTS the rank is withheld: in a group of three, "second of
 * three" names the others' scores.
 */
final class ExamRankingService
{
    public const MIN_PARTICIPANTS = 5;
    private const BUCKETS = 10;

    public function __construct(
        private readonly PDO $database,
        private readonly AccessGate $access,
    ) {
    }

    /** @return array<string, mixed> */
    public function ranking(string $userId, string $workspaceId, string $assessmentId): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        $exam = $this->database->prepare("SELECT created_for_user_id FROM exam_assessments WHERE id = :assessment AND workspace_id = :workspace AND status = 'published' AND archived_at IS NULL");
        $exam->execute(['assessment' => $assessmentId, 'workspace' => $workspaceId]);
        $row = $exam->fetch();
        if ($row === false) {
            throw new PlatformException('assessment_not_found', 'Assessment was not found.', 404);
        }
        if ($row['created_for_user_id'] !== null) {
            return ['ranked' => false, 'reason' => 'personal', 'participants' => 0];
        }

        $query = $this->database->prepare(<<<'SQL'
SELECT attempt.user_id, result.score_basis_points
FROM exam_attempts attempt
JOIN exam_attempt_results result ON result.attempt_id = attempt.id AND result.workspace_id = attempt.workspace_id
WHERE attempt.workspace_id = :workspace AND attempt.assessment_id = :assessment
  AND attempt.status = 'scored' AND attempt.mode = 'assessment'
ORDER BY attempt.submitted_at, attempt.id
SQL);
        $query->execute(['workspace' => $workspaceId, 'assessment' => $assessmentId]);
        $first = [];
        foreach ($query->fetchAll() as $attempt) {
            $first[(string) $attempt['user_id']] ??= (int) $attempt['score_basis_points'];
        }

        return self::standing($first, $userId);
    }

    /**
     * The arithmetic, on user id => first score (basis points).
     *
     * @param array<string, int> $scores
     * @return array<string, mixed>
     */
    public static function standing(array $scores, string $userId): array
    {
        $count = count($scores);
        $mine = $scores[$userId] ?? null;
        $base = ['ranked' => false, 'participants' => $count, 'mine_percent' => $mine === null ? null : intdiv($mine, 100)];
        if ($mine === null) {
            return $base + ['reason' => 'no_exam_attempt'];
        }
        if ($count < self::MIN_PARTICIPANTS) {
            return $base + ['reason' => 'too_few'];
        }
        $above = count(array_filter($scores, static fn (int $score): bool => $score > $mine));
        $below = count(array_filter($scores, static fn (int $score): bool => $score < $mine));
        $buckets = array_fill(0, self::BUCKETS, 0);
        foreach ($scores as $score) {
            ++$buckets[min(self::BUCKETS - 1, intdiv(max(0, $score), 10000 / self::BUCKETS))];
        }

        return [
            'ranked' => true,
            'participants' => $count,
            'rank' => $above + 1,
            'better_than_percent' => (int) round($below * 100 / ($count - 1)),
            'mine_percent' => intdiv($mine, 100),
            'average_percent' => (int) round(array_sum($scores) / $count / 100),
            'top_percent' => intdiv(max($scores), 100),
            'distribution' => $buckets,
            'my_bucket' => min(self::BUCKETS - 1, intdiv(max(0, $mine), 10000 / self::BUCKETS)),
        ];
    }
}
