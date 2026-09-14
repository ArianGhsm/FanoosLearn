<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

/**
 * Deterministic per-attempt question/choice ordering. Pure and stateless --
 * seeded only from the attempt id (already a unique UUIDv7,
 * docs/product/01_FRONT_DOOR.md) plus a fixed key, never persisted -- so the
 * same attempt always recomputes the same order on every read/resume without
 * a dedicated storage column, and two different attempts of the same
 * assessment almost never share an order. This is presentation ordering
 * only: answers stay keyed to stable question ids and are scored against the
 * canonical (authored) choice index, so a shuffled attempt scores identically
 * to an unshuffled one for the same choices (ExamService::submitAttempt
 * translates the displayed choice index back to canonical before comparing).
 */
final class ExamAttemptShuffle
{
    /**
     * @param list<string> $questionIds stable ids in authored order
     * @return list<string> the same ids, permuted for this attempt
     */
    public static function questionOrder(string $attemptId, array $questionIds): array
    {
        return self::permute($attemptId . ':questions', $questionIds);
    }

    /**
     * @return list<int> canonical (authored) choice indices, in the order
     *     they should be displayed for this attempt/question
     */
    public static function choiceOrder(string $attemptId, string $questionId, int $choiceCount): array
    {
        return self::permute($attemptId . ':choices:' . $questionId, range(0, $choiceCount - 1));
    }

    /**
     * @param list<mixed> $items
     * @return list<mixed>
     */
    private static function permute(string $seedKey, array $items): array
    {
        $state = self::seed($seedKey);
        for ($i = count($items) - 1; $i > 0; $i--) {
            $state = self::next($state);
            $j = $state % ($i + 1);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return array_values($items);
    }

    private static function seed(string $key): int
    {
        $seed = (int) hexdec(substr(hash('sha256', $key), 0, 8));

        return $seed === 0 ? 1 : $seed;
    }

    /** One xorshift32 step. Not cryptographic -- only needs to be a stable, well-mixed function of the seed. */
    private static function next(int $state): int
    {
        $state ^= ($state << 13) & 0xFFFFFFFF;
        $state ^= ($state >> 17);
        $state ^= ($state << 5) & 0xFFFFFFFF;

        return $state & 0xFFFFFFFF;
    }
}
