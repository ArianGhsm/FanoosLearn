<?php

declare(strict_types=1);

namespace Fanoos\Platform\Engagement;

use PDO;

/**
 * سطح دشواری: how hard a question is, measured from how everyone answered
 * it (exam_question_stats). Below MINIMUM_ANSWERS the measurement says too
 * little, so the question's own `difficulty` (set by an author or expert)
 * stands in, and with neither the level is unknown.
 */
final class QuestionDifficulty
{
    public const MINIMUM_ANSWERS = 10;
    public const LEVELS = ['easy', 'medium', 'hard'];

    /** easy from this share of right answers up, medium from MEDIUM_FROM up, hard below. */
    private const EASY_FROM = 70;
    private const MEDIUM_FROM = 40;

    public function __construct(private readonly PDO $database)
    {
    }

    /** The measured level, or null when fewer than MINIMUM_ANSWERS answers are in. */
    public static function measured(int $answered, int $correct): ?string
    {
        if ($answered < self::MINIMUM_ANSWERS) {
            return null;
        }
        $percent = $correct * 100 / $answered;

        return $percent >= self::EASY_FROM ? 'easy' : ($percent >= self::MEDIUM_FROM ? 'medium' : 'hard');
    }

    /** An authored difficulty in the runner's vocabulary, or null. */
    public static function authored(mixed $value): ?string
    {
        $value = is_string($value) ? strtolower(trim($value)) : null;

        return in_array($value, self::LEVELS, true) ? $value : null;
    }

    /**
     * The level of each question: measured where enough answers are in,
     * otherwise the definition's own difficulty, otherwise null.
     *
     * @param array<string, array<string, mixed>> $questions question id => definition question
     * @return array<string, ?string>
     */
    public function levels(string $workspaceId, array $questions): array
    {
        $levels = [];
        foreach ($questions as $id => $question) {
            $levels[(string) $id] = self::authored($question['difficulty'] ?? null);
        }
        if ($levels === []) {
            return [];
        }
        $keys = array_keys($levels);
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $query = $this->database->prepare("SELECT question_key, answered_count, correct_count FROM exam_question_stats WHERE workspace_id = ? AND question_key IN ({$placeholders})");
        $query->execute([$workspaceId, ...$keys]);
        foreach ($query->fetchAll() as $row) {
            $measured = self::measured((int) $row['answered_count'], (int) $row['correct_count']);
            if ($measured !== null) {
                $levels[(string) $row['question_key']] = $measured;
            }
        }

        return $levels;
    }
}
