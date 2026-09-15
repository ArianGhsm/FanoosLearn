<?php

declare(strict_types=1);

namespace Fanoos\Platform\Identity;

/**
 * Derives a session's CSRF token from its own session token instead of
 * storing one.
 *
 * The bug this replaces: iam_sessions stored only a digest of the CSRF
 * token (the same pattern used for the session token itself), which meant
 * the real value existed exactly once, in the login response body. A
 * server-rendered page has no way to recover a value it was never given, so
 * AuthService::authenticate() returned an empty csrfToken on every request
 * after login, every embedded <meta name="fanoos-csrf"> was blank, and
 * every write from a rendered page (logout, workspace selection, exam
 * answers) was refused.
 *
 * The fix needs no new secret and no schema change: the session token
 * itself is already a high-entropy value known only to the server and the
 * browser holding the session cookie, which is exactly the audience CSRF
 * needs and exactly the audience a stored HMAC key would not improve on.
 * Keying an HMAC on the session token domain-separates the derived value
 * from the token itself (so leaking one four-figure derivation formula does
 * not hand over the other), while staying a pure function callable from
 * anywhere that already holds the session token -- login(),
 * establishSession(), authenticate() and requireCsrf() all compute the
 * same value from the same input and can never drift apart.
 */
final class SessionCsrf
{
    private const DOMAIN = 'fanoos-csrf-v1';

    public static function derive(string $sessionToken): string
    {
        return hash_hmac('sha256', self::DOMAIN, $sessionToken);
    }
}
