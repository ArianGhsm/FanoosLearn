# Persistent signed-in devices — 2026-10-16

## Problem

Previously the browser cookie `fanoos_session` was a session-only cookie
(no `Max-Age` or `Expires`), and the server's default
`AuthService` lifetime was **43,200 seconds (12 hours)**. Closing a
mobile browser, restarting a laptop or returning the next day could
therefore require another login even on a trusted personal device.

## Security-preserving implementation

- Login, account registration and authenticated owner-recovery issue a
  `fanoos_session` cookie via **one shared `SessionCookie::issue()`**
  function. It is **Secure, HttpOnly, SameSite=Lax, host-only, Path=/**,
  with a positive `Max-Age` aligned to the actual database session expiry.
  Cookies are never persisted in JavaScript or localStorage.
- Newly created sessions have a **30-day** server-side absolute expiry
  by default. There is **no sliding refresh** or perpetual renewal merely
  because a browser is open. Sessions still fail immediately after
  revocation, password/account blocking, server expiry or manual logout.
- Each login creates an independent per-device database session. Signing
  out on one device revokes **that** session and expires its cookie with
  `Max-Age=0`; it does not unexpectedly sign out another device.
- Older existing 12-hour sessions are **not silently extended**. Users
  benefit after a fresh login following the authorized deployment.
- Shared or public devices should explicitly use **Log out** when done.
  Browser/private-mode cookie clearing or user device/browser settings
  still take precedence. Remembered login means a securely stored
  session cookie, **not** storing the user's plaintext password.

## Verification

Integration tests verify 30-day lifetime and persistence attributes,
independent sessions for phone and laptop, correct logout revocation
without cross-device logout, plus existing CSRF derivation and
authentication protection. CI must be green. Authorized updater
deployment and actual browser/device tests are separate requirements;
a merged PR does not mean the live cookie changed.

No database migration, user reauthentication bypass or raw auth
credential logging is introduced.
