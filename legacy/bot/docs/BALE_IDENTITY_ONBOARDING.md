# Bale identity onboarding

Identity v2 removes the name-claim/manual fallback. Bale class members choose
canonical-site OTP or the website-login link. OTP works only for a verified,
OTP-enabled phone already stored on the primary class account; otherwise
website login is required. Telegram uses the same application flow while the
two numeric platform mappings remain separate.

Telegram and Bale identities are separate. A canonical website account may
have at most one Telegram mapping and at most one Bale mapping. Linking Bale
never copies, replaces or infers the Telegram mapping; both may point to the
same student account because the website remains the identity authority.

## Student flow

1. Start the Bale bot and choose the explicit 1402 Tehran Dentistry class route.
   Sending `/verify` before an approved entry route only returns this gateway.
2. Choose canonical-site OTP or `ورود با نام کاربری و رمز سایت`. The latter
   opens the secure website-login link; credentials stay on the website.
3. For website login, the bot requests a ten-minute, single-use challenge with
   `platform=bale` and the current Bale numeric identity.
4. Sign in to the canonical website if needed. The confirmation page must say
   `اتصال حساب بله` before the student confirms.
5. Return to Bale and press `بررسی اتصال`. Personalized grades,
   notifications, payments and future site-backed features resolve through the
   Bale mapping only.

The bot never asks for a website password, website-login OTP, national ID or
banking data. The separate generic intake may receive only its own short-lived
phone-verification OTP after the user shares their Contact; that flow is
defined in `BOT_ONBOARDING_V1.md` and does not connect a website account.
The challenge token is not stored in bot SQLite and cannot link a different
platform or user after confirmation/expiry.

There is no name-claim fallback. If OTP is unavailable, the student must use
website login. The owner mapping screen is inspect/delete only and cannot create
or replace a connection, including for conflict recovery.

## Release gates

- Offline tests prove the shared `/verify` command, Bale-specific copy and
  absence of Telegram labels in Bale identity screens.
- The website contract accepts only `telegram` and `bale`, hashes the platform
  with the numeric identity and enforces one-to-one uniqueness within each
  platform.
- Live health verifies the configured Bale owner mapping and signed site API.
  A real unlinked student completes final acceptance; do not manufacture a
  second website account for testing.
