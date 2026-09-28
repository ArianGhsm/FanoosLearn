<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

use PDO;

/**
 * What stands between the question bank and a bulk export, without slowing
 * a real student down. admit() is the entry point; it applies, in order:
 *
 * 1. A question this account already opened today is free to open again --
 *    going back, reloading, the map, the review. Re-reading is what real
 *    students do most, and it gives an exporter nothing new.
 * 2. A daily cap on *different* questions per account (exam_question_daily_reads,
 *    migration 0029): far above a heavy day of study, far below the bank, so
 *    the bank cannot be taken in one go.
 * 3. The token bucket below, now sized for speed rather than volume: a large
 *    burst and about one new question a second, which no person reading
 *    questions reaches and which stops a script from racing to the cap.
 *
 * Until 2026-09-28 the bucket alone was the protection -- six reads, then one
 * every twenty seconds, re-reads included -- and ordinary use kept running
 * into it (owner: "الکی سرعتو کم کرده").
 *
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
    public const DAILY_NEW_QUESTIONS = 1500;

    public function __construct(
        private readonly PDO $database,
        private readonly float $burstCapacity = 120.0,
        private readonly float $refillSecondsPerToken = 1.0,
        private readonly int $dailyNewQuestions = self::DAILY_NEW_QUESTIONS,
    ) {
    }

    /**
     * Null when the read may go ahead; otherwise the refusal's error code
     * (`question_daily_limit` or `question_read_rate_limited`). Same contract
     * as consume(): run it inside the caller's transaction and throw only
     * after that transaction returns.
     */
    public function admit(string $userId, string $questionKey, ?int $now = null): ?string
    {
        $now ??= time();
        $today = gmdate('Y-m-d', $now);
        $seen = $this->database->prepare('SELECT 1 FROM exam_question_daily_reads WHERE user_id = :user AND read_on = :day AND question_key = :question');
        $seen->execute(['user' => $userId, 'day' => $today, 'question' => $questionKey]);
        if ($seen->fetchColumn() !== false) {
            return null;
        }

        $count = $this->database->prepare('SELECT COUNT(*) FROM exam_question_daily_reads WHERE user_id = :user AND read_on = :day');
        $count->execute(['user' => $userId, 'day' => $today]);
        $opened = (int) $count->fetchColumn();
        if ($opened >= $this->dailyNewQuestions) {
            return 'question_daily_limit';
        }
        if (!$this->consume($userId, $now)) {
            return 'question_read_rate_limited';
        }

        if ($opened === 0) {
            // The account's first new question today: yesterday's list is
            // kept (a day boundary mid-session), anything older is not needed.
            $this->database->prepare('DELETE FROM exam_question_daily_reads WHERE user_id = :user AND read_on < :cutoff')
                ->execute(['user' => $userId, 'cutoff' => gmdate('Y-m-d', $now - 86400)]);
        }
        $this->database->prepare(<<<'SQL'
INSERT IGNORE INTO exam_question_daily_reads (user_id, read_on, question_key, first_read_at)
VALUES (:user, :day, :question, :at)
SQL)->execute(['user' => $userId, 'day' => $today, 'question' => $questionKey, 'at' => gmdate('Y-m-d H:i:s', $now)]);

        return null;
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
