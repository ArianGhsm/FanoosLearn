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
 * only: answers stay keyed to stable question ids, so a shuffled attempt
 * scores identically to an unshuffled one.
 *
 * Question order only -- choice order is deliberately NOT shuffled. Real
 * explanations name the option they are about ("گزینه C صحیح است"); in the
 * first bank imported here that is 78% of them. Permuting the choices makes
 * the explanation contradict the answer the review marks as correct, which
 * is worse than the modest extraction cost it buys: a scraper merges copies
 * by text, not by position, and question-order shuffling still fingerprints
 * the attempt a leaked set came from.
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
