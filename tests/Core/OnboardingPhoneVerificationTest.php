<?php

declare(strict_types=1);

namespace Fanoos\Tests\Core;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Messaging\ChannelSubjectProtector;
use Fanoos\Platform\Onboarding\OnboardingPhoneVerificationService;
use Fanoos\Platform\Onboarding\SmsGateway;
use Fanoos\Platform\Support\PlatformException;
use PDO;
use RuntimeException;

final class OnboardingPhoneVerificationTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $this->assertRequestVerifyRoundTrip();
        $this->assertWrongCodeThenCorrectCode();
        $this->assertUsedCodeCannotBeReused();
        $this->assertCodeExpiry();
        $this->assertSessionExpiry();
        $this->assertResendCooldownAndThrottle();
        $this->assertAttemptsAreBounded();
        $this->assertSendFailureInvalidatesChallenge();
        $this->assertPhoneNeverStoredOrReturnedInPlaintext();

        return $this->assertions;
    }

    private function assertRequestVerifyRoundTrip(): void
    {
        [$service, $sms] = $this->service();
        $subject = 'tg-otp-' . bin2hex(random_bytes(6));
        $now = 1_800_000_000;

        $issued = $service->requestOtp('telegram', $subject, '09121234567', $now);
        $this->assert($issued['phone_masked'] === '+98912***67', 'Phone mask did not match the expected pattern.');
        $this->assert($issued['expires_in_seconds'] === 180, 'Requested code did not report the expected TTL.');
        $this->assert($sms->lastCode !== null, 'SMS gateway did not receive a code to send.');

        $verified = $service->verifyOtp('telegram', $subject, $issued['challenge_token'], (string) $sms->lastCode, $now + 5);
        $this->assert($verified['verified'] === true, 'Correct code did not verify.');

        $status = $service->status('telegram', $subject);
        $this->assert($status['verified'] === true, 'Status did not reflect a verified phone.');
        $this->assert($status['phone_masked'] === '+98912***67', 'Status returned an unexpected masked phone.');
    }

    private function assertWrongCodeThenCorrectCode(): void
    {
        [$service, $sms] = $this->service();
        $subject = 'tg-otp-' . bin2hex(random_bytes(6));
        $now = 1_800_100_000;

        $issued = $service->requestOtp('telegram', $subject, '۰۹۱۲۱۲۳۴۵۶۷', $now);
        $this->expectCode('onboarding_otp_code_invalid', fn () => $service->verifyOtp('telegram', $subject, $issued['challenge_token'], '000000', $now + 1));
        $verified = $service->verifyOtp('telegram', $subject, $issued['challenge_token'], (string) $sms->lastCode, $now + 2);
        $this->assert($verified['verified'] === true, 'Correct code after a wrong attempt did not verify.');
    }

    private function assertUsedCodeCannotBeReused(): void
    {
        [$service, $sms] = $this->service();
        $subject = 'tg-otp-' . bin2hex(random_bytes(6));
        $now = 1_800_200_000;

        $issued = $service->requestOtp('telegram', $subject, '09121234567', $now);
        $code = (string) $sms->lastCode;
        $service->verifyOtp('telegram', $subject, $issued['challenge_token'], $code, $now + 1);
        $this->expectCode('onboarding_challenge_used', fn () => $service->verifyOtp('telegram', $subject, $issued['challenge_token'], $code, $now + 2));
    }

    private function assertCodeExpiry(): void
    {
        [$service, $sms] = $this->service();
        $subject = 'tg-otp-' . bin2hex(random_bytes(6));
        $now = 1_800_300_000;

        $issued = $service->requestOtp('telegram', $subject, '09121234567', $now);
        $this->expectCode('onboarding_otp_code_expired', fn () => $service->verifyOtp('telegram', $subject, $issued['challenge_token'], (string) $sms->lastCode, $now + 181));
    }

    private function assertSessionExpiry(): void
    {
        [$service, $sms] = $this->service();
        $subject = 'tg-otp-' . bin2hex(random_bytes(6));
        $now = 1_800_400_000;

        $issued = $service->requestOtp('telegram', $subject, '09121234567', $now);
        $this->expectCode('onboarding_challenge_expired', fn () => $service->verifyOtp('telegram', $subject, $issued['challenge_token'], (string) $sms->lastCode, $now + 601));
        $this->expectCode('onboarding_challenge_expired', fn () => $service->resendOtp('telegram', $subject, $issued['challenge_token'], $now + 601));
    }

    private function assertResendCooldownAndThrottle(): void
    {
        [$service, $sms] = $this->service();
        $subject = 'tg-otp-' . bin2hex(random_bytes(6));
        $now = 1_800_500_000;

        $issued = $service->requestOtp('telegram', $subject, '09121234567', $now);
        $firstCode = (string) $sms->lastCode;

        $this->expectCode('onboarding_otp_cooldown', fn () => $service->resendOtp('telegram', $subject, $issued['challenge_token'], $now + 5));
        $this->expectCode('onboarding_otp_cooldown', fn () => $service->requestOtp('telegram', $subject, '09121234567', $now + 5));

        $resendTime = $now + 61;
        $resend = $service->resendOtp('telegram', $subject, $issued['challenge_token'], $resendTime);
        $this->assert($resend['challenge_token'] === $issued['challenge_token'], 'Resend did not keep the same challenge token.');
        $this->assert($sms->lastCode !== null && (string) $sms->lastCode !== $firstCode, 'Resend did not issue a new code.');
        $this->expectCode('onboarding_otp_code_invalid', fn () => $service->verifyOtp('telegram', $subject, $issued['challenge_token'], $firstCode, $resendTime + 1));
        $verified = $service->verifyOtp('telegram', $subject, $issued['challenge_token'], (string) $sms->lastCode, $resendTime + 2);
        $this->assert($verified['verified'] === true, 'Resent code did not verify.');

        // Six sends per rolling hour: one request + five resends is the last allowed
        // send in the window (60s cooldown between each, so each step is spaced 61s
        // apart); the seventh send attempt must be rate limited.
        $subject2 = 'tg-otp-' . bin2hex(random_bytes(6));
        $windowStart = $now + 10_000;
        $second = $service->requestOtp('telegram', $subject2, '09121234567', $windowStart);
        $token = $second['challenge_token'];
        for ($i = 1; $i <= 5; $i++) {
            $service->resendOtp('telegram', $subject2, $token, $windowStart + $i * 61);
        }
        $this->expectCode('onboarding_otp_rate_limited', fn () => $service->resendOtp('telegram', $subject2, $token, $windowStart + 6 * 61));
    }

    private function assertAttemptsAreBounded(): void
    {
        [$service, $sms] = $this->service();
        $subject = 'tg-otp-' . bin2hex(random_bytes(6));
        $now = 1_800_600_000;

        $issued = $service->requestOtp('telegram', $subject, '09121234567', $now);
        for ($i = 0; $i < 4; $i++) {
            $this->expectCode('onboarding_otp_code_invalid', fn () => $service->verifyOtp('telegram', $subject, $issued['challenge_token'], '000000', $now + 1));
        }
        $this->expectCode('onboarding_otp_attempts_exceeded', fn () => $service->verifyOtp('telegram', $subject, $issued['challenge_token'], '000000', $now + 1));
        // Even the correct code is refused once attempts are exhausted.
        $this->expectCode('onboarding_otp_attempts_exceeded', fn () => $service->verifyOtp('telegram', $subject, $issued['challenge_token'], (string) $sms->lastCode, $now + 2));
    }

    private function assertSendFailureInvalidatesChallenge(): void
    {
        $sms = new FakeSmsGateway();
        $sms->nextSendFails = true;
        $service = new OnboardingPhoneVerificationService($this->database, new AuditLogger($this->database), new ChannelSubjectProtector(str_repeat('o', 32)), $sms);
        $subject = 'tg-otp-' . bin2hex(random_bytes(6));
        $now = 1_800_700_000;

        $this->expectCode('onboarding_otp_send_failed', fn () => $service->requestOtp('telegram', $subject, '09121234567', $now));
        $status = $service->status('telegram', $subject);
        $this->assert($status['verified'] === false, 'A failed send left the identity marked as verified.');
    }

    private function assertPhoneNeverStoredOrReturnedInPlaintext(): void
    {
        [$service, $sms] = $this->service();
        $subject = 'tg-otp-' . bin2hex(random_bytes(6));
        $now = 1_800_800_000;
        $phone = '09121234567';
        $normalized = '+98' . substr($phone, 1);

        $issued = $service->requestOtp('telegram', $subject, $phone, $now);
        foreach ([$issued] as $response) {
            foreach ($response as $value) {
                if (is_string($value)) {
                    $this->assert(!str_contains($value, $normalized) && !str_contains($value, $phone), 'A response value contained the raw phone number.');
                }
            }
        }

        $ciphertext = (string) $this->database->query('SELECT phone_ciphertext FROM onboarding_phone_challenges ORDER BY created_at DESC LIMIT 1')->fetchColumn();
        $this->assert(!str_contains($ciphertext, $normalized) && !str_contains($ciphertext, $phone), 'Phone ciphertext column contains the raw phone number.');

        $verified = $service->verifyOtp('telegram', $subject, $issued['challenge_token'], (string) $sms->lastCode, $now + 1);
        foreach ($verified as $value) {
            if (is_string($value)) {
                $this->assert(!str_contains($value, $normalized) && !str_contains($value, $phone), 'Verify response contained the raw phone number.');
            }
        }

        $verifiedRow = $this->database->query('SELECT phone_ciphertext FROM onboarding_verified_phones ORDER BY updated_at DESC LIMIT 1')->fetchColumn();
        $this->assert(is_string($verifiedRow) && !str_contains($verifiedRow, $normalized) && !str_contains($verifiedRow, $phone), 'Verified-phone ciphertext column contains the raw phone number.');
    }

    /** @return array{0:OnboardingPhoneVerificationService,1:FakeSmsGateway} */
    private function service(): array
    {
        $sms = new FakeSmsGateway();
        $service = new OnboardingPhoneVerificationService(
            $this->database,
            new AuditLogger($this->database),
            new ChannelSubjectProtector(str_repeat('o', 32)),
            $sms,
        );
        return [$service, $sms];
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

final class FakeSmsGateway implements SmsGateway
{
    public ?string $lastCode = null;
    public bool $nextSendFails = false;

    public function send(string $phoneNumber, string $code): array
    {
        if ($this->nextSendFails) {
            $this->nextSendFails = false;
            return ['success' => false, 'message' => 'Simulated SMS delivery failure.'];
        }
        $this->lastCode = $code;
        return ['success' => true];
    }
}
