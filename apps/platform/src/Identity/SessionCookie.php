<?php

declare(strict_types=1);

namespace Fanoos\Platform\Identity;

/**
 * Persistent, device-local, HttpOnly session cookie.
 *
 * The token is never exposed to JavaScript or localStorage. Each login
 * generates its own independent DB session; a separate browser/device
 * does not revoke an existing session. Server-side expiry/revocation
 * remains authoritative.
 */
final class SessionCookie
{
    public const MAX_LIFETIME_SECONDS = 30 * 86400;

    public static function issue(AuthenticatedSession $session): string
    {
        $expiry = strtotime($session->expiresAt . ' UTC');
        $remaining = $expiry === false ? 0 : max(0, min(self::MAX_LIFETIME_SECONDS, $expiry - time()));

        return 'Set-Cookie: fanoos_session=' . rawurlencode($session->token)
            . '; Path=/; Max-Age=' . $remaining . '; HttpOnly; Secure; SameSite=Lax';
    }

    public static function expire(): string
    {
        return 'Set-Cookie: fanoos_session=; Path=/; Max-Age=0; HttpOnly; Secure; SameSite=Lax';
    }
}
