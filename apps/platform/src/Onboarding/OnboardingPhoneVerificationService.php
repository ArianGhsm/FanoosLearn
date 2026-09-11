<?php

declare(strict_types=1);

namespace Fanoos\Platform\Onboarding;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Messaging\ChannelSubjectProtector;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Transaction;
use Fanoos\Platform\Support\Uuid;
use PDO;

/**
 * Pre-workspace phone verification for the onboarding identity flow, ported from
 * the legacy dent_issue_otp_for_phone / dent_verify_otp_for_phone engine
 * (legacy/site/api/auth_store.php) and the bot_onboarding.php request/resend/verify
 * contract it served. A student verifying their phone here is not yet a member of
 * any workspace -- nothing in this class or the tables it writes to carries a
 * workspace_id. The boundary into a workspace is a separate, later step (a
 * representative approving a join request), out of scope for this service.
 *
 * Numeric rules below (180s code TTL, 60s resend cooldown, 5 max verify attempts,
 * 6 sends per rolling hour) are carried over unchanged from the legacy engine,
 * which was tuned against real students. One deliberate deviation: the legacy
 * throttle key embeds a fresh random challengeRef on every "request" call, so its
 * cooldown/hourly cap only ever bites on "resend", not on repeated "request" calls
 * for the same identity -- a live SMS-cost/spam gap, not an intentional design.
 * Here the throttle is bound to (platform, subject) instead, so request and resend
 * share one throttle domain.
 */
final class OnboardingPhoneVerificationService
{
    private const PLATFORMS = ['telegram', 'bale'];
    private const TOKEN_TTL_SECONDS = 600;
    private const CODE_TTL_SECONDS = 180;
    private const COOLDOWN_SECONDS = 60;
    private const MAX_ATTEMPTS = 5;
    private const MAX_SEND_PER_WINDOW = 6;
    private const SEND_WINDOW_SECONDS = 3600;

    public function __construct(
        private readonly PDO $database,
        private readonly AuditLogger $audit,
        private readonly ChannelSubjectProtector $subjects,
        private readonly SmsGateway $sms,
    ) {
    }

    /** @return array{challenge_token:string,phone_masked:string,expires_in_seconds:int,cooldown_seconds:int} */
    public function requestOtp(string $platform, string $subject, string $phoneNumber, ?int $now = null): array
    {
        $platform = $this->platform($platform);
        $phone = $this->normalizePhone($phoneNumber);
        if ($phone === '') {
            throw new PlatformException('onboarding_phone_invalid', 'Phone number is invalid.', 422);
        }
        $now ??= time();
        $subjectDigest = $this->subjects->digest('onboarding:' . $platform, $subject);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // The row is committed before the SMS is sent (and stays committed even if
        // sending fails) so a failed send still burns a send slot against the
        // throttle -- see invalidateAfterSendFailure. Throwing after a commit, not
        // from inside Transaction::run, is what makes that possible: throwing while
        // still inside the transaction would roll the insert back along with it.
        $created = Transaction::run($this->database, function () use ($platform, $subjectDigest, $phone, $code, $now): array {
            $throttle = $this->currentThrottle($platform, $subjectDigest, $now);

            $id = Uuid::v7();
            $token = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
            $phoneDigest = $this->subjects->digest('phone', $phone);
            $phoneCiphertext = $this->subjects->encrypt('phone', $phone);
            $codeDigest = $this->subjects->digest('onboarding-otp:' . $id, $code);

            $insert = $this->database->prepare(<<<'SQL'
INSERT INTO onboarding_phone_challenges (
    id, platform, subject_digest, phone_digest, phone_ciphertext, token_digest, code_digest,
    attempts, max_attempts, send_count, send_window_started_at, cooldown_until,
    token_expires_at, code_expires_at, consumed_at, created_at, updated_at
) VALUES (
    :id, :platform, :subject_digest, :phone_digest, :phone_ciphertext, :token_digest, :code_digest,
    0, :max_attempts, :send_count, FROM_UNIXTIME(:window_start), FROM_UNIXTIME(:cooldown_until),
    FROM_UNIXTIME(:token_expires), FROM_UNIXTIME(:code_expires), NULL, FROM_UNIXTIME(:now), FROM_UNIXTIME(:now)
)
SQL);
            $insert->bindValue(':id', $id);
            $insert->bindValue(':platform', $platform);
            $insert->bindValue(':subject_digest', $subjectDigest, PDO::PARAM_LOB);
            $insert->bindValue(':phone_digest', $phoneDigest, PDO::PARAM_LOB);
            $insert->bindValue(':phone_ciphertext', $phoneCiphertext, PDO::PARAM_LOB);
            $insert->bindValue(':token_digest', hash('sha256', $token, true), PDO::PARAM_LOB);
            $insert->bindValue(':code_digest', $codeDigest, PDO::PARAM_LOB);
            $insert->bindValue(':max_attempts', self::MAX_ATTEMPTS, PDO::PARAM_INT);
            $insert->bindValue(':send_count', $throttle['send_count'] + 1, PDO::PARAM_INT);
            $insert->bindValue(':window_start', $throttle['window_start'], PDO::PARAM_INT);
            $insert->bindValue(':cooldown_until', $now + self::COOLDOWN_SECONDS, PDO::PARAM_INT);
            $insert->bindValue(':token_expires', $now + self::TOKEN_TTL_SECONDS, PDO::PARAM_INT);
            $insert->bindValue(':code_expires', $now + self::CODE_TTL_SECONDS, PDO::PARAM_INT);
            $insert->bindValue(':now', $now, PDO::PARAM_INT);
            $insert->execute();

            return ['id' => $id, 'token' => $token];
        });

        $issued = $this->sms->send($phone, $code);
        if (($issued['success'] ?? false) !== true) {
            $this->invalidateAfterSendFailure($created['id'], $now);
            throw new PlatformException('onboarding_otp_send_failed', (string) ($issued['message'] ?? 'SMS delivery failed.'), 502);
        }

        $this->audit->record(null, null, 'onboarding.otp.request', 'onboarding_phone_challenge', $created['id'], 'success', [
            'platform' => $platform,
            'phone_masked' => $this->mask($phone),
        ]);

        return [
            'challenge_token' => $created['token'],
            'phone_masked' => $this->mask($phone),
            'expires_in_seconds' => self::CODE_TTL_SECONDS,
            'cooldown_seconds' => self::COOLDOWN_SECONDS,
        ];
    }

    /** @return array{challenge_token:string,phone_masked:string,expires_in_seconds:int,cooldown_seconds:int} */
    public function resendOtp(string $platform, string $subject, string $challengeToken, ?int $now = null): array
    {
        $platform = $this->platform($platform);
        $now ??= time();
        $subjectDigest = $this->subjects->digest('onboarding:' . $platform, $subject);
        $tokenDigest = hash('sha256', $challengeToken, true);

        // As in requestOtp, the code update commits before the SMS is sent so a
        // failed send still burns a send slot and cannot leave a stale code usable.
        $updated = Transaction::run($this->database, function () use ($platform, $subjectDigest, $tokenDigest, $now): array {
            $row = $this->lockChallengeByToken($tokenDigest, $subjectDigest, $platform);
            if ($row['consumed_at'] !== null) {
                throw new PlatformException('onboarding_challenge_used', 'Onboarding challenge was already used.', 409);
            }
            if ($this->toTimestamp($row['token_expires_at']) < $now) {
                throw new PlatformException('onboarding_challenge_expired', 'Onboarding challenge session expired; start over.', 410);
            }
            $cooldownUntil = $this->toTimestamp($row['cooldown_until']);
            if ($cooldownUntil > $now) {
                throw new PlatformException('onboarding_otp_cooldown', 'Request another code later.', 429);
            }
            $windowStart = $this->toTimestamp($row['send_window_started_at']);
            $sendCount = (int) $row['send_count'];
            if ($now - $windowStart > self::SEND_WINDOW_SECONDS) {
                $windowStart = $now;
                $sendCount = 0;
            }
            if ($sendCount >= self::MAX_SEND_PER_WINDOW) {
                throw new PlatformException('onboarding_otp_rate_limited', 'Too many codes were requested for this phone.', 429);
            }

            $phone = $this->subjects->decrypt('phone', (string) $row['phone_ciphertext']);
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $codeDigest = $this->subjects->digest('onboarding-otp:' . $row['id'], $code);
            $tokenExpiresAt = $this->toTimestamp((string) $row['token_expires_at']);
            $codeExpiresAt = min($now + self::CODE_TTL_SECONDS, $tokenExpiresAt);

            $update = $this->database->prepare(<<<'SQL'
UPDATE onboarding_phone_challenges
SET code_digest = :code_digest, attempts = 0, send_count = :send_count,
    send_window_started_at = FROM_UNIXTIME(:window_start), cooldown_until = FROM_UNIXTIME(:cooldown_until),
    code_expires_at = FROM_UNIXTIME(:code_expires), updated_at = FROM_UNIXTIME(:now)
WHERE id = :id
SQL);
            $update->bindValue(':code_digest', $codeDigest, PDO::PARAM_LOB);
            $update->bindValue(':send_count', $sendCount + 1, PDO::PARAM_INT);
            $update->bindValue(':window_start', $windowStart, PDO::PARAM_INT);
            $update->bindValue(':cooldown_until', $now + self::COOLDOWN_SECONDS, PDO::PARAM_INT);
            $update->bindValue(':code_expires', $codeExpiresAt, PDO::PARAM_INT);
            $update->bindValue(':now', $now, PDO::PARAM_INT);
            $update->bindValue(':id', (string) $row['id']);
            $update->execute();

            return ['id' => (string) $row['id'], 'phone' => $phone, 'code' => $code, 'code_expires_at' => $codeExpiresAt];
        });

        $issued = $this->sms->send($updated['phone'], $updated['code']);
        if (($issued['success'] ?? false) !== true) {
            $this->invalidateAfterSendFailure($updated['id'], $now);
            throw new PlatformException('onboarding_otp_send_failed', (string) ($issued['message'] ?? 'SMS delivery failed.'), 502);
        }

        $this->audit->record(null, null, 'onboarding.otp.resend', 'onboarding_phone_challenge', $updated['id'], 'success', [
            'platform' => $platform,
            'phone_masked' => $this->mask($updated['phone']),
        ]);

        return [
            'challenge_token' => $challengeToken,
            'phone_masked' => $this->mask($updated['phone']),
            'expires_in_seconds' => max(0, $updated['code_expires_at'] - $now),
            'cooldown_seconds' => self::COOLDOWN_SECONDS,
        ];
    }

    /** @return array{verified:bool,phone_masked:string} */
    public function verifyOtp(string $platform, string $subject, string $challengeToken, string $code, ?int $now = null): array
    {
        $platform = $this->platform($platform);
        $now ??= time();
        $subjectDigest = $this->subjects->digest('onboarding:' . $platform, $subject);
        $tokenDigest = hash('sha256', $challengeToken, true);
        $normalizedCode = $this->normalizeDigits($code);

        return Transaction::run($this->database, function () use ($platform, $subjectDigest, $tokenDigest, $normalizedCode, $now): array {
            $row = $this->lockChallengeByToken($tokenDigest, $subjectDigest, $platform);
            if ($row['consumed_at'] !== null) {
                throw new PlatformException('onboarding_challenge_used', 'Onboarding challenge was already used.', 409);
            }
            if ($this->toTimestamp($row['token_expires_at']) < $now) {
                throw new PlatformException('onboarding_challenge_expired', 'Onboarding challenge session expired; start over.', 410);
            }
            if ($this->toTimestamp($row['code_expires_at']) < $now) {
                throw new PlatformException('onboarding_otp_code_expired', 'Verification code expired; request a new one.', 422);
            }
            $attempts = (int) $row['attempts'];
            $maxAttempts = (int) $row['max_attempts'];
            if ($attempts >= $maxAttempts) {
                throw new PlatformException('onboarding_otp_attempts_exceeded', 'Too many wrong attempts; request a new code.', 429);
            }

            $candidateDigest = $this->subjects->digest('onboarding-otp:' . $row['id'], $normalizedCode);
            if (!hash_equals((string) $row['code_digest'], $candidateDigest)) {
                $attempts++;
                $this->database->prepare('UPDATE onboarding_phone_challenges SET attempts = :attempts, updated_at = UTC_TIMESTAMP(6) WHERE id = :id')
                    ->execute(['attempts' => $attempts, 'id' => (string) $row['id']]);
                if ($attempts >= $maxAttempts) {
                    throw new PlatformException('onboarding_otp_attempts_exceeded', 'Too many wrong attempts; request a new code.', 429);
                }
                throw new PlatformException('onboarding_otp_code_invalid', 'Verification code is incorrect.', 422);
            }

            $consume = $this->database->prepare('UPDATE onboarding_phone_challenges SET consumed_at = UTC_TIMESTAMP(6), updated_at = UTC_TIMESTAMP(6) WHERE id = :id AND consumed_at IS NULL');
            $consume->execute(['id' => (string) $row['id']]);
            if ($consume->rowCount() !== 1) {
                throw new PlatformException('onboarding_challenge_used', 'Onboarding challenge was already used.', 409);
            }

            $phone = $this->subjects->decrypt('phone', (string) $row['phone_ciphertext']);
            $upsert = $this->database->prepare(<<<'SQL'
INSERT INTO onboarding_verified_phones (platform, subject_digest, phone_digest, phone_ciphertext, verified_at, updated_at)
VALUES (:platform, :subject_digest, :phone_digest, :phone_ciphertext, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
ON DUPLICATE KEY UPDATE phone_digest = VALUES(phone_digest), phone_ciphertext = VALUES(phone_ciphertext),
    verified_at = UTC_TIMESTAMP(6), updated_at = UTC_TIMESTAMP(6)
SQL);
            $upsert->bindValue(':platform', $platform);
            $upsert->bindValue(':subject_digest', $subjectDigest, PDO::PARAM_LOB);
            $upsert->bindValue(':phone_digest', (string) $row['phone_digest'], PDO::PARAM_LOB);
            $upsert->bindValue(':phone_ciphertext', (string) $row['phone_ciphertext'], PDO::PARAM_LOB);
            $upsert->execute();

            $this->audit->record(null, null, 'onboarding.otp.verify', 'onboarding_phone_challenge', (string) $row['id'], 'success', [
                'platform' => $platform,
                'phone_masked' => $this->mask($phone),
            ]);

            return ['verified' => true, 'phone_masked' => $this->mask($phone)];
        });
    }

    /** @return array{verified:bool,phone_masked:?string,verified_at:?string} */
    public function status(string $platform, string $subject): array
    {
        $platform = $this->platform($platform);
        $subjectDigest = $this->subjects->digest('onboarding:' . $platform, $subject);
        $query = $this->database->prepare('SELECT phone_ciphertext, verified_at FROM onboarding_verified_phones WHERE platform = :platform AND subject_digest = :digest');
        $query->bindValue(':platform', $platform);
        $query->bindValue(':digest', $subjectDigest, PDO::PARAM_LOB);
        $query->execute();
        $row = $query->fetch();
        if ($row === false) {
            return ['verified' => false, 'phone_masked' => null, 'verified_at' => null];
        }
        $phone = $this->subjects->decrypt('phone', (string) $row['phone_ciphertext']);
        return [
            'verified' => true,
            'phone_masked' => $this->mask($phone),
            'verified_at' => gmdate(DATE_ATOM, $this->toTimestamp((string) $row['verified_at'])),
        ];
    }

    /** @return array{window_start:int,send_count:int} */
    private function currentThrottle(string $platform, string $subjectDigest, int $now): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT send_window_started_at, send_count, cooldown_until
FROM onboarding_phone_challenges
WHERE platform = :platform AND subject_digest = :digest
ORDER BY created_at DESC
LIMIT 1
FOR UPDATE
SQL);
        $query->bindValue(':platform', $platform);
        $query->bindValue(':digest', $subjectDigest, PDO::PARAM_LOB);
        $query->execute();
        $row = $query->fetch();
        if ($row === false) {
            return ['window_start' => $now, 'send_count' => 0];
        }
        $cooldownUntil = $this->toTimestamp((string) $row['cooldown_until']);
        if ($cooldownUntil > $now) {
            throw new PlatformException('onboarding_otp_cooldown', 'Request another code later.', 429);
        }
        $windowStart = $this->toTimestamp((string) $row['send_window_started_at']);
        $sendCount = (int) $row['send_count'];
        if ($now - $windowStart > self::SEND_WINDOW_SECONDS) {
            return ['window_start' => $now, 'send_count' => 0];
        }
        if ($sendCount >= self::MAX_SEND_PER_WINDOW) {
            throw new PlatformException('onboarding_otp_rate_limited', 'Too many codes were requested for this phone.', 429);
        }
        return ['window_start' => $windowStart, 'send_count' => $sendCount];
    }

    /** @return array<string,mixed> */
    private function lockChallengeByToken(string $tokenDigest, string $subjectDigest, string $platform): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT id, platform, subject_digest, phone_digest, phone_ciphertext, code_digest,
       attempts, max_attempts, send_count, send_window_started_at, cooldown_until,
       token_expires_at, code_expires_at, consumed_at
FROM onboarding_phone_challenges
WHERE token_digest = :digest
LIMIT 1
FOR UPDATE
SQL);
        $query->bindValue(':digest', $tokenDigest, PDO::PARAM_LOB);
        $query->execute();
        $row = $query->fetch();
        if ($row === false || !hash_equals((string) $row['subject_digest'], $subjectDigest) || !hash_equals((string) $row['platform'], $platform)) {
            throw new PlatformException('onboarding_challenge_not_found', 'Onboarding challenge was not found.', 404);
        }
        return $row;
    }

    private function invalidateAfterSendFailure(string $id, int $now): void
    {
        $statement = $this->database->prepare('UPDATE onboarding_phone_challenges SET token_expires_at = FROM_UNIXTIME(:now), code_expires_at = FROM_UNIXTIME(:now), updated_at = FROM_UNIXTIME(:now) WHERE id = :id');
        $statement->bindValue(':now', $now, PDO::PARAM_INT);
        $statement->bindValue(':id', $id);
        $statement->execute();
    }

    private function toTimestamp(string $datetime): int
    {
        $timestamp = strtotime($datetime . ' UTC');
        return $timestamp === false ? 0 : $timestamp;
    }

    private function platform(string $platform): string
    {
        $platform = strtolower(trim($platform));
        if (!in_array($platform, self::PLATFORMS, true)) {
            throw new PlatformException('onboarding_platform_invalid', 'Onboarding platform is not supported.', 422);
        }
        return $platform;
    }

    private function normalizeDigits(string $value): string
    {
        $value = strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        return preg_replace('/\D+/u', '', $value) ?? '';
    }

    private function normalizePhone(string $value): string
    {
        $digits = $this->normalizeDigits($value);
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '0098')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '98')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0')) {
            $digits = ltrim($digits, '0');
        }
        if (strlen($digits) !== 10 || !str_starts_with($digits, '9')) {
            return '';
        }
        return '+98' . $digits;
    }

    private function mask(string $normalizedPhone): string
    {
        if ($normalizedPhone === '') {
            return '';
        }
        $head = substr($normalizedPhone, 0, 6);
        $tail = substr($normalizedPhone, -2);
        return $head . '***' . $tail;
    }
}
