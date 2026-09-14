<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

use PDO;

/**
 * Per-user token bucket pacing exam question/explanation reads
 * (docs/product/01_FRONT_DOOR.md question-bank export protection). One row
 * per user, read with FOR UPDATE and upserted with ON DUPLICATE KEY UPDATE --
 * the same locking/upsert idiom AuthService::recordFailure uses for
 * iam_login_attempts -- rather than a session/cache-based limiter, so the
 * guard is durable across restarts and lives in the same database the rest
 * of the platform audits against. A continuous token bucket (rather than a
 * fixed-window counter like MessagingLinkService's challenge throttle) is
 * used deliberately: a hard per-minute wall would either stall a student
 * paging back through questions they already read, or hand a scraper a
 * fresh window every N seconds -- neither matches "read at a plausible human
 * pace". The small burst covers normal navigation; the slow refill after it
 * is what turns a bulk read into a very long one.
 *
 * The caller MUST run consume() inside its own Transaction::run and, on a
 * refusal, throw only *after* that transaction returns -- never from inside
 * it. Throwing inside would roll back the very token spend/refill this call
 * just persisted, the same class of bug already fixed in
 * OnboardingPhoneVerificationService::verifyOtp for its wrong-attempt
 * counter (see that class's docblock). consume() itself always executes its
 * write before returning, on both the allowed and refused paths, so there is
 * no code path where a refusal reaches the caller without the write having
 * already run.
 */
final class ExamQuestionRateGuard
{
    public function __construct(
        private readonly PDO $database,
        private readonly float $burstCapacity = 6.0,
        private readonly float $refillSecondsPerToken = 20.0,
    ) {
    }

    public function consume(string $userId, ?int $now = null): bool
    {
        $now ??= time();
        $row = $this->database->prepare(
            'SELECT tokens_remaining, last_refill_at FROM exam_question_read_rate_guards WHERE user_id = :user FOR UPDATE',
        );
        $row->execute(['user' => $userId]);
        $existing = $row->fetch();

        if ($existing === false) {
            $tokens = $this->burstCapacity;
        } else {
            $lastRefillAt = strtotime((string) $existing['last_refill_at'] . ' UTC') ?: $now;
            $elapsedSeconds = max(0, $now - $lastRefillAt);
            $tokens = min($this->burstCapacity, (float) $existing['tokens_remaining'] + ($elapsedSeconds / $this->refillSecondsPerToken));
        }

        $allowed = $tokens >= 1.0;
        if ($allowed) {
            $tokens -= 1.0;
        }

        $statement = $this->database->prepare(<<<'SQL'
INSERT INTO exam_question_read_rate_guards (user_id, tokens_remaining, last_refill_at, updated_at)
VALUES (:user, :tokens, FROM_UNIXTIME(:refilled_at), UTC_TIMESTAMP(6))
ON DUPLICATE KEY UPDATE
    tokens_remaining = :tokens_update,
    last_refill_at = FROM_UNIXTIME(:refilled_at_update),
    updated_at = UTC_TIMESTAMP(6)
SQL);
        $statement->bindValue(':user', $userId);
        $statement->bindValue(':tokens', $tokens);
        $statement->bindValue(':refilled_at', $now, PDO::PARAM_INT);
        $statement->bindValue(':tokens_update', $tokens);
        $statement->bindValue(':refilled_at_update', $now, PDO::PARAM_INT);
        $statement->execute();

        return $allowed;
    }
}
