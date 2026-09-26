<?php

declare(strict_types=1);

namespace Fanoos\Platform\Identity;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Onboarding\OnboardingPhoneVerificationService;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Transaction;
use Fanoos\Platform\Support\Uuid;
use PDO;
use PDOException;

/**
 * A phone number added to a website account after sign-up.
 *
 * Sign-up asks for none (owner's decision, 2026-09-26). Adding one later
 * goes through the same OTP engine the bots use -- same throttle, same code
 * rules, same SMS gateway -- under platform 'web' with the account id as the
 * subject, and ends as a verified 'phone' identifier on the account, the
 * same shape the bot's accounts have.
 *
 * A number that already belongs to another account is refused rather than
 * moved: that account may be the same student's bot account, and quietly
 * taking the number from it would cut it off from its own phone. Joining the
 * two is a separate, deliberate step.
 */
final class AccountPhoneService
{
    public function __construct(
        private readonly PDO $database,
        private readonly OnboardingPhoneVerificationService $verification,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return array{phone_masked:?string} */
    public function current(string $userId): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT normalized_value FROM iam_user_identifiers
WHERE user_id = :user AND identifier_type = 'phone' AND is_verified = TRUE AND revoked_at IS NULL
ORDER BY verified_at DESC
LIMIT 1
SQL);
        $query->execute(['user' => $userId]);
        $phone = $query->fetchColumn();

        return ['phone_masked' => $phone === false ? null : self::mask((string) $phone)];
    }

    /** @return array{challenge_token:string,phone_masked:string,expires_in_seconds:int,cooldown_seconds:int} */
    public function requestCode(string $userId, string $phoneNumber, ?int $now = null): array
    {
        $this->refuseIfTakenByAnother($userId, $phoneNumber);

        return $this->verification->requestOtp('web', $userId, $phoneNumber, $now);
    }

    /** @return array{phone_masked:string} */
    public function confirm(string $userId, string $challengeToken, string $code, ?int $now = null): array
    {
        $this->verification->verifyOtp('web', $userId, $challengeToken, $code, $now);
        $phone = $this->verification->verifiedPhone('web', $userId);
        if ($phone === null) {
            throw new PlatformException('account_phone_not_verified', 'The phone number could not be verified.', 409);
        }

        Transaction::run($this->database, function () use ($userId, $phone): void {
            // One phone per account: an earlier number is retired, not kept
            // alongside, so "the account's phone" always means one number.
            $this->database->prepare(<<<'SQL'
UPDATE iam_user_identifiers SET revoked_at = UTC_TIMESTAMP(6)
WHERE user_id = :user AND identifier_type = 'phone' AND revoked_at IS NULL AND normalized_value <> :phone
SQL)->execute(['user' => $userId, 'phone' => $phone]);

            $existing = $this->database->prepare("SELECT id, user_id, revoked_at FROM iam_user_identifiers WHERE identifier_type = 'phone' AND normalized_value = :phone FOR UPDATE");
            $existing->execute(['phone' => $phone]);
            $row = $existing->fetch();
            if ($row !== false && (string) $row['user_id'] !== $userId && $row['revoked_at'] === null) {
                throw new PlatformException('account_phone_in_use', 'This phone number belongs to another account.', 409);
            }
            if ($row !== false) {
                // Ours already, or a number another account gave up: take it over.
                $this->database->prepare(<<<'SQL'
UPDATE iam_user_identifiers
SET user_id = :user, is_verified = TRUE, verified_at = UTC_TIMESTAMP(6), revoked_at = NULL
WHERE id = :id
SQL)->execute(['user' => $userId, 'id' => $row['id']]);
            } else {
                try {
                    $this->database->prepare(<<<'SQL'
INSERT INTO iam_user_identifiers (id, user_id, identifier_type, normalized_value, is_verified, verified_at, created_at)
VALUES (:id, :user, 'phone', :phone, TRUE, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL)->execute(['id' => Uuid::v7(), 'user' => $userId, 'phone' => $phone]);
                } catch (PDOException $error) {
                    if ((string) $error->getCode() === '23000') {
                        throw new PlatformException('account_phone_in_use', 'This phone number belongs to another account.', 409);
                    }
                    throw $error;
                }
            }
            $this->audit->record(null, $userId, 'account.phone.set', 'iam_user', $userId, 'success', ['phone_masked' => self::mask($phone)]);
        });

        return ['phone_masked' => self::mask($phone)];
    }

    /** Checked before an SMS is spent on a number that could never be attached. */
    private function refuseIfTakenByAnother(string $userId, string $phoneNumber): void
    {
        $normalized = self::normalize($phoneNumber);
        if ($normalized === '') {
            throw new PlatformException('onboarding_phone_invalid', 'Phone number is invalid.', 422);
        }
        $query = $this->database->prepare("SELECT user_id FROM iam_user_identifiers WHERE identifier_type = 'phone' AND normalized_value = :phone AND revoked_at IS NULL");
        $query->execute(['phone' => $normalized]);
        $owner = $query->fetchColumn();
        if ($owner !== false && (string) $owner !== $userId) {
            throw new PlatformException('account_phone_in_use', 'This phone number belongs to another account.', 409);
        }
    }

    /** The same +98 form OnboardingPhoneVerificationService stores. */
    private static function normalize(string $value): string
    {
        $digits = preg_replace('/\D+/', '', strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ])) ?? '';
        if (str_starts_with($digits, '0098')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '98')) {
            $digits = substr($digits, 2);
        }
        $digits = ltrim($digits, '0');

        return strlen($digits) === 10 && str_starts_with($digits, '9') ? '+98' . $digits : '';
    }

    private static function mask(string $phone): string
    {
        return strlen($phone) < 8 ? '***' : substr($phone, 0, 6) . str_repeat('*', max(0, strlen($phone) - 8)) . substr($phone, -2);
    }
}
