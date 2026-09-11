# Shared Telegram/Bale onboarding v1

> Identity v2 (2026-08-27): the class route offers only canonical-site OTP or
> the single-use website-login link. Manual/name approval is disabled. OTP is
> sent only when the selected `dentistry-1402` account already has a verified,
> OTP-enabled phone; otherwise the student is directed to website login. All
> legacy import, claim-review, resolution and manual mapping actions return
> `MANUAL_IDENTITY_DISABLED`; the owner can only inspect or delete an existing
> mapping. A fresh link is always created by the student through OTP or the
> single-use website-login challenge.
> The private gate has no owner bypass: the configured owner must also have a
> live canonical website link before any private or administrative surface opens.
> Private sections are locked until the canonical account is linked and its
> durable `bot-canonical-auth-v1` completion proof exists. `linked=true` by
> itself—including a migrated or formerly approved link—never unlocks the bot.

`DentBotApp` owns one onboarding state machine for Telegram and Bale. Platform
adapters only transport the same screens and normal reply keyboards.

## Entry routes

For a new, unlinked and unprofiled user, `/start` shows exactly two normal
keyboard choices in this order:

1. `وارد کردن نام و نام خانوادگی`
2. `دانشجوی ورودی ۱۴۰۲ دندانپزشکی تهران هستم`

The second route leaves generic onboarding immediately and opens the canonical
OTP/website-link class flow. It never asks class members for the generic profile.

The generic route order is fixed: first name, last name, major, province,
medical university/faculty, entry year, entry semester, course type, optional
student number, final review, own contact, and phone OTP. Entry year is a
required reply-keyboard selection from exactly `۱۳۹۹` through `۱۴۰۵`, appears
immediately before entry semester, and is validated again by the website API.
Mobile is always last and is accepted only as a platform Contact. Telegram
requires the Contact user ID to match the sender; Bale also checks it whenever
Bale supplies that field.

Every screen has a normal-keyboard `مرحله قبل` beside `انصراف`. Going back
keeps already entered non-secret values and returns exactly one logical step;
for an Islamic Azad profile it skips the public-only course-type step in both
directions. The Contact screen states that the number remains confidential and
is used only for real-person verification and blocking automated/AI/bot entry.

Semester is selected before course type. The website stores only one canonical
`admissionType`, limited to six combinations: first/second semester crossed
with regular-or-commitment, tuition-paying, or international.
For an Islamic Azad medical unit, `admissionType` is only `نیمسال اول` or
`نیمسال دوم`; the separate public course-type screen is not shown.

All progress controls are normal reply-keyboard buttons. Inline callbacks are
allowed only after the user leaves onboarding for an existing bot screen.

## Authority, synchronization and access

The signed website API is the only durable source for onboarding profiles. The
catalog contains 75 public medical universities/faculties plus all 31 Islamic
Azad units listed in the medical/dentistry/pharmacy/veterinary section of the
1402 national admissions annex. Each institution carries an explicit `public`
or `azad` system marker. Both bot adapters fetch the same versioned catalog.

The transport retries once on a fresh TLS connection only when a stale pooled
socket fails while the HTTP request is still incomplete. A failure after the
request has been sent is not retried, preventing duplicate messages while also
ensuring the first `/start` after an idle period is not silently lost.

The profile and phone are encrypted at rest in
`storage/integrations/bot_links.json`. A verified-phone HMAC joins Telegram and
Bale identities to the same profile only after each platform completes its own
OTP. Raw platform IDs and raw phone numbers are not public or logged.

Generic onboarding is not website authentication. It creates no PHP session,
canonical student account, role, grade access or private-content access. After
its Contact/OTP is verified it may use the general bot menu and purchase an
eligible bot-owned product; checkout is bound to an opaque HMAC payer key made
from the verified phone and does not trust the optional student number. Private
account data, owner financial reports and administrative actions still require
the canonical OTP or authenticated website link. A class-marked profile never
uses this generic fallback after its website mapping is disconnected.

Linked primary-class accounts receive a fixed class marker and the canonical
Dentistry/Tehran/entry-year-`۱۴۰۲`/first-semester/regular-or-commitment profile. Account
shows all fields read-only. An edit creates one encrypted pending request; only
owner approval applies it and rejection keeps the previous value.

The website account response exposes a non-sensitive `authComplete` decision.
Both adapters require that exact decision for entry and private actions; the
signed website dispatcher independently rejects legacy-link requests with
`ACCOUNT_AUTH_REQUIRED`. A legacy identity may reauthenticate without deleting
or replacing its one-to-one mapping: OTP must match the same student number, and
website login confirmation refuses a different canonical account.

The bot SQLite dialog may persist non-secret form progress and an opaque
challenge reference. It must never persist a raw phone number or OTP. The OTP
uses the website's existing short-lived, one-time, rate-limited SMS subsystem.

## Recovery

The canonical website storage is mirrored from production to the laptop before
every website deployment. Telegram and Bale SQLite files are included in the
encrypted, restore-verified VPS backup. Therefore a lost VPS can be replaced
without losing verified onboarding profiles, and unfinished non-secret dialog
progress is recoverable from the latest VPS snapshot.
