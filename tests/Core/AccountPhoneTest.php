<?php

declare(strict_types=1);

namespace Fanoos\Tests\Core;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Identity\AccountPhoneService;
use Fanoos\Platform\Messaging\ChannelSubjectProtector;
use Fanoos\Platform\Onboarding\OnboardingPhoneVerificationService;
use Fanoos\Platform\Onboarding\SmsGateway;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;

/**
 * A phone added to a website account after sign-up: verified by SMS code,
 * one per account, and never taken from another account that holds it.
 */
final class AccountPhoneTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $this->assertAPhoneIsAddedOnlyWithTheRightCode();
        $this->assertANumberAnotherAccountHoldsIsRefusedBeforeAnySms();
        $this->assertChangingTheNumberRetiresTheOldOne();

        return $this->assertions;
    }

    private function assertAPhoneIsAddedOnlyWithTheRightCode(): void
    {
        [$service, $sms] = $this->service();
        $user = $this->user();
        $phone = $this->phone();

        $this->assert($service->current($user)['phone_masked'] === null, 'A new account already had a phone.');
        $challenge = $service->requestCode($user, '0' . substr($phone, 3));
        $this->assert($sms->lastCode !== null, 'No code was sent.');

        $wrong = $sms->lastCode === '000000' ? '111111' : '000000';
        $this->expectCode('onboarding_otp_code_invalid', fn () => $service->confirm($user, $challenge['challenge_token'], $wrong));
        $this->assert($service->current($user)['phone_masked'] === null, 'A wrong code attached the phone.');

        $service->confirm($user, $challenge['challenge_token'], (string) $sms->lastCode);
        $this->assert($service->current($user)['phone_masked'] !== null, 'The right code did not attach the phone.');
        $row = $this->database->prepare("SELECT is_verified FROM iam_user_identifiers WHERE user_id = :user AND identifier_type = 'phone' AND normalized_value = :phone AND revoked_at IS NULL");
        $row->execute(['user' => $user, 'phone' => $phone]);
        $this->assert((bool) $row->fetchColumn(), 'The phone was not stored as a verified identifier in the +98 form.');
    }

    private function assertANumberAnotherAccountHoldsIsRefusedBeforeAnySms(): void
    {
        [$service, $sms] = $this->service();
        $owner = $this->user();
        $phone = $this->phone();
        $this->database->prepare("INSERT INTO iam_user_identifiers (id, user_id, identifier_type, normalized_value, is_verified, verified_at, created_at) VALUES (:id, :user, 'phone', :phone, TRUE, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => Uuid::v7(), 'user' => $owner, 'phone' => $phone]);

        $this->expectCode('account_phone_in_use', fn () => $service->requestCode($this->user(), $phone));
        $this->assert($sms->lastCode === null, 'An SMS was spent on a number that could never be attached.');
    }

    private function assertChangingTheNumberRetiresTheOldOne(): void
    {
        [$service, $sms] = $this->service();
        $user = $this->user();
        // Two minutes apart: the second request must clear the OTP cooldown.
        foreach ([$this->phone(), $this->phone()] as $step => $phone) {
            $now = time() + $step * 120;
            $challenge = $service->requestCode($user, $phone, $now);
            $service->confirm($user, $challenge['challenge_token'], (string) $sms->lastCode, $now);
        }
        $count = $this->database->prepare("SELECT COUNT(*) FROM iam_user_identifiers WHERE user_id = :user AND identifier_type = 'phone' AND revoked_at IS NULL");
        $count->execute(['user' => $user]);
        $this->assert((int) $count->fetchColumn() === 1, 'An account ended up with two live phone numbers.');
    }

    /** @return array{0: AccountPhoneService, 1: RecordingSmsGateway} */
    private function service(): array
    {
        $sms = new RecordingSmsGateway();
        $audit = new AuditLogger($this->database);
        $verification = new OnboardingPhoneVerificationService($this->database, $audit, new ChannelSubjectProtector(str_repeat('p', 32)), $sms);

        return [new AccountPhoneService($this->database, $verification, $audit), $sms];
    }

    private function user(): string
    {
        $id = Uuid::v7();
        $this->database->prepare("INSERT INTO iam_users (id, display_name, status, locale, created_at, updated_at) VALUES (:id, 'Phone Student', 'active', 'fa-IR', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => $id]);

        return $id;
    }

    /** A fresh +98 mobile number that no earlier run used. */
    private function phone(): string
    {
        return '+989' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
    }

    private function expectCode(string $code, callable $operation): void
    {
        ++$this->assertions;
        try {
            $operation();
        } catch (PlatformException $error) {
            if ($error->errorCode === $code) {
                return;
            }
            throw new RuntimeException("Expected {$code}, got {$error->errorCode}: {$error->getMessage()}");
        }
        throw new RuntimeException("Expected PlatformException {$code}.");
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}

final class RecordingSmsGateway implements SmsGateway
{
    public ?string $lastCode = null;

    public function send(string $phoneNumber, string $code): array
    {
        $this->lastCode = $code;

        return ['success' => true];
    }
}
