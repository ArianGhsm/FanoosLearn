<?php

declare(strict_types=1);

namespace Fanoos\Platform\Onboarding;

/**
 * Default SMS gateway when no real provider is wired for this environment.
 * Fails loud rather than silently pretending an OTP was delivered.
 */
final class UnconfiguredSmsGateway implements SmsGateway
{
    public function send(string $phoneNumber, string $code): array
    {
        return ['success' => false, 'message' => 'ارسال پیامک در این محیط پیکربندی نشده است.'];
    }
}
