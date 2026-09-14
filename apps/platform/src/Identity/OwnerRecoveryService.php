<?php

declare(strict_types=1);

namespace Fanoos\Platform\Identity;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Transaction;
use Fanoos\Platform\Support\Uuid;
use PDO;

/**
 * Lets an installation owner who is locked out of the web get back in
 * without anyone ever handling their password. The owner asks the bot for a
 * link (they are already authenticated there by their messaging link); this
 * issues a single-use, short-lived, workspace-less token bound to that user
 * and stored only as a digest -- the same shape iam_sessions.token_digest
 * already uses. Redeeming it establishes a normal session (AuthService::
 * establishSession(), the exact same session shape login() creates) and the
 * owner sets their own password from there (AuthService::setPassword()).
 *
 * Rate limiting reuses AuthService::recordFailure's exact idiom: one row per
 * identity in owner_recovery_rate_guards, read with FOR UPDATE and written
 * on every request -- allowed or refused -- before this method decides
 * whether to throw. A refusal is returned as a verdict from inside
 * Transaction::run and thrown only after it commits, so the write a
 * refusal itself makes can never be rolled back by the exception that
 * reports it -- the OTP counter bug this project already shipped and fixed
 * once in OnboardingPhoneVerificationService::verifyOtp.
 */
final class OwnerRecoveryService
{
    private const TOKEN_TTL_SECONDS = 600;
    private const WINDOW_SECONDS = 3600;
    private const MAX_REQUESTS_PER_WINDOW = 3;
    private const COOLDOWN_SECONDS = 60;

    public function __construct(
        private readonly PDO $database,
        private readonly AuthService $auth,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return array{token:string,expires_in_seconds:int} */
    public function requestLink(string $userId, ?int $now = null): array
    {
        // Same predicate AuthService::account()/selectWorkspace() already use
        // to decide who is an owner -- never a second definition of it.
        if (!$this->auth->isPlatformOperator($userId)) {
            throw new PlatformException('owner_recovery_forbidden', 'Only an installation owner can request a recovery link.', 403);
        }
        $now ??= time();

        $outcome = Transaction::run($this->database, function () use ($userId, $now): array {
            $lock = $this->database->prepare('SELECT request_count, window_started_at, cooldown_until FROM owner_recovery_rate_guards WHERE user_id = :user FOR UPDATE');
            $lock->execute(['user' => $userId]);
            $existing = $lock->fetch();

            $windowStart = $now;
            $count = 0;
            $cooldownUntil = null;
            if ($existing !== false) {
                $windowStart = $this->toTimestamp((string) $existing['window_started_at']);
                $count = (int) $existing['request_count'];
                $cooldownUntil = $existing['cooldown_until'] === null ? null : $this->toTimestamp((string) $existing['cooldown_until']);
                if ($now - $windowStart > self::WINDOW_SECONDS) {
                    $windowStart = $now;
                    $count = 0;
                }
            }

            if ($cooldownUntil !== null && $cooldownUntil > $now) {
                $this->persistRateGuard($userId, $count, $windowStart, $cooldownUntil, $now);
                return ['allowed' => false];
            }
            if ($count >= self::MAX_REQUESTS_PER_WINDOW) {
                $this->persistRateGuard($userId, $count, $windowStart, $cooldownUntil, $now);
                return ['allowed' => false];
            }

            $count++;
            $this->persistRateGuard($userId, $count, $windowStart, $now + self::COOLDOWN_SECONDS, $now);

            $token = self::randomToken();
            $id = Uuid::v7();
            $insert = $this->database->prepare(<<<'SQL'
INSERT INTO owner_recovery_tokens (id, user_id, token_digest, expires_at, consumed_at, created_at)
VALUES (:id, :user, :digest, FROM_UNIXTIME(:expires), NULL, FROM_UNIXTIME(:created))
SQL);
            $insert->bindValue(':id', $id);
            $insert->bindValue(':user', $userId);
            $insert->bindValue(':digest', hash('sha256', $token, true), PDO::PARAM_LOB);
            // Both timestamps must derive from the same $now: created_at as
            // real UTC_TIMESTAMP(6) here while expires_at was computed from
            // an injected (possibly fake, for deterministic tests) $now let
            // the two diverge and trip chk_owner_recovery_tokens_expiry the
            // moment a test injected a $now far from the real wall clock.
            $insert->bindValue(':expires', $now + self::TOKEN_TTL_SECONDS, PDO::PARAM_INT);
            $insert->bindValue(':created', $now, PDO::PARAM_INT);
            $insert->execute();

            $this->audit->record(null, $userId, 'auth.owner_recovery.requested', 'owner_recovery_token', $id, 'success');

            return ['allowed' => true, 'token' => $token, 'expires_in_seconds' => self::TOKEN_TTL_SECONDS];
        });

        if ($outcome['allowed'] === false) {
            throw new PlatformException('owner_recovery_rate_limited', 'Request another recovery link later.', 429);
        }

        return ['token' => $outcome['token'], 'expires_in_seconds' => $outcome['expires_in_seconds']];
    }

    public function redeem(string $token, ?int $now = null): AuthenticatedSession
    {
        $now ??= time();
        if ($token === '' || strlen($token) > 128) {
            throw new PlatformException('owner_recovery_token_invalid', 'Recovery link is invalid.', 400);
        }
        $digest = hash('sha256', $token, true);

        return Transaction::run($this->database, function () use ($digest, $now): AuthenticatedSession {
            $row = $this->database->prepare('SELECT id, user_id, expires_at, consumed_at FROM owner_recovery_tokens WHERE token_digest = :digest LIMIT 1 FOR UPDATE');
            $row->bindValue(':digest', $digest, PDO::PARAM_LOB);
            $row->execute();
            $record = $row->fetch();
            if ($record === false) {
                throw new PlatformException('owner_recovery_token_invalid', 'Recovery link is invalid.', 404);
            }
            if ($record['consumed_at'] !== null) {
                throw new PlatformException('owner_recovery_token_used', 'Recovery link was already used.', 409);
            }
            if ($this->toTimestamp((string) $record['expires_at']) < $now) {
                throw new PlatformException('owner_recovery_token_expired', 'Recovery link expired; request a new one.', 410);
            }

            $consume = $this->database->prepare('UPDATE owner_recovery_tokens SET consumed_at = UTC_TIMESTAMP(6) WHERE id = :id AND consumed_at IS NULL');
            $consume->execute(['id' => $record['id']]);
            if ($consume->rowCount() !== 1) {
                // Another concurrent redemption won the race.
                throw new PlatformException('owner_recovery_token_used', 'Recovery link was already used.', 409);
            }

            $userId = (string) $record['user_id'];
            $session = $this->auth->establishSession($userId);
            $this->audit->record(null, $userId, 'auth.owner_recovery.redeemed', 'session', $session->sessionId);

            return $session;
        });
    }

    private function persistRateGuard(string $userId, int $count, int $windowStart, ?int $cooldownUntil, int $now): void
    {
        $statement = $this->database->prepare(<<<'SQL'
INSERT INTO owner_recovery_rate_guards (user_id, request_count, window_started_at, cooldown_until, last_request_at)
VALUES (:user, :count, FROM_UNIXTIME(:window_start), FROM_UNIXTIME(:cooldown), FROM_UNIXTIME(:now))
ON DUPLICATE KEY UPDATE
    request_count = :count_update,
    window_started_at = FROM_UNIXTIME(:window_start_update),
    cooldown_until = FROM_UNIXTIME(:cooldown_update),
    last_request_at = FROM_UNIXTIME(:now_update)
SQL);
        $statement->bindValue(':user', $userId);
        $statement->bindValue(':count', $count, PDO::PARAM_INT);
        $statement->bindValue(':window_start', $windowStart, PDO::PARAM_INT);
        $statement->bindValue(':cooldown', $cooldownUntil, $cooldownUntil === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':now', $now, PDO::PARAM_INT);
        $statement->bindValue(':count_update', $count, PDO::PARAM_INT);
        $statement->bindValue(':window_start_update', $windowStart, PDO::PARAM_INT);
        $statement->bindValue(':cooldown_update', $cooldownUntil, $cooldownUntil === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':now_update', $now, PDO::PARAM_INT);
        $statement->execute();
    }

    private function toTimestamp(string $datetime): int
    {
        $timestamp = strtotime($datetime . ' UTC');

        return $timestamp === false ? 0 : $timestamp;
    }

    private static function randomToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
