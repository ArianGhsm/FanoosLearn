# Dent1402Bot UX and Interaction System

This system applies equally to the Telegram and Bale adapters. Both platforms
use the shared `DentBotApp` screens and callback namespace. An adapter may fall
back from unsupported button presentation metadata, but navigation, Persian
copy, owner checks, and website authorization boundaries remain identical.

Feature parity is a release gate: adding, changing, renaming or deleting any
command, menu action, callback, permission or workflow must change both bot
surfaces in the same patch. Runtime downtime waives only the live smoke test,
never implementation or offline contract tests. A platform adapter may differ
only in transport syntax/capabilities and must retain an equivalent readable
outcome. Scheduled external side effects may have one elected coordinator to
avoid duplicate work, while the corresponding interactive option stays
available on both bots.

Both long-polling services now run on the Iran VPS. Bale uses direct networking;
Telegram alone uses the loopback Xray HTTP CONNECT egress documented in
`TELEGRAM_IRAN_EGRESS.md`. Separate service users, environments and runtime
SQLite files preserve failure isolation. Payment-offer definitions are the
deliberate exception: both adapters use one group-restricted shared SQLite
store so an owner action on either platform has the same immediate result.

## Emoji and rich-text language

Emoji are semantic interface markers, not decoration. Use at most one leading
emoji for a title or button and keep the mapping stable: `📝` exams, `📚` notes,
`📅` class operations, `📊` grades, `👤` account, `🔔` notifications, `🛟` help,
and `⚙️` owner tools. Operational reports reserve `🔵`, `🟢`, `🟠`, and `🔴`
for started, succeeded, rolled back, and failed. Do not scatter unrelated emoji
through body copy. `📚` therefore remains notes-only and must not label class
operations.

Use the current Telegram Bot API native Rich Message layer for structured data:
`sendRichMessage` on send and the `rich_message` field of `editMessageText` on
edit. Set `is_rtl=true`; use real `<h1>`-`<h6>`, `<table bordered striped
compact>`, lists, `<details>`, footer and inline formatting blocks. Grades and
Navid are native tables inside Telegram itself. A `<pre>` imitation, screenshot
or external page is not accepted as the primary Telegram renderer.

Class Operations keeps its hub compact and consistent with the rest of the bot.
Structured item lists/details, Tomorrow Summary, Weekly Digest, owner preview
and AI draft preview use the shared native-rich layer on Telegram. Long
descriptions belong in `<details>` or an expandable bounded fallback rather
than a wall of text. The shared semantic data is rendered as a readable Bale
fallback; no ClassOps renderer may fork audience, ordering, permission, ACK,
task or digest semantics per platform. Machine UTC/ISO time remains internal:
every visible Class Operations date/time uses the shared Tehran Solar Hijri
formatter and every visible number uses Persian digits.

Class Operations callback navigation remains in one app-like message. A
callback that opens or refreshes a native-rich report edits the existing
`message_id` through `editMessageText.rich_message`, including regular-to-rich
and rich-to-regular transitions. A new message is permitted only for a command
or user-text entry point, or when Telegram explicitly reports that the original
message is genuinely uneditable. Malformed rich content or another transport
error must fail visibly rather than silently creating a second navigation
message.

Regular screens still use bounded Telegram HTML where a document structure
would add no value: bold for a title/field, blockquote for a short status, code
for a safe identifier and links only for explicit destinations. Formatting
must clarify hierarchy rather than decorate every line. Native rich sends have
a safe `sendMessage` fallback only when the Telegram endpoint explicitly lacks
the method; malformed rich HTML is a release failure and must not silently
degrade.
The active Bale API/client path does not render Telegram HTML. Its transport
must therefore translate the bounded HTML subset into Bale-native Markdown and
omit `parse_mode` for send, edit and captions. Bold and italic markers retain
Bale's required surrounding spaces; code/pre and HTTPS links retain their
semantics, while blockquotes use a stable `▎` visual fallback.
Raw `<b>`, `<i>`, `<code>`, `<pre>` or `<blockquote>` tags in a Bale message are
a release failure, not an acceptable visual difference.

Every visible number uses Persian digits, including screen/caption text,
callback answers, reply and inline button labels, page indicators and input
placeholders. This is a transport invariant in addition to renderer-level
formatting. URLs, href attributes, callback data, opaque refs and tokens remain
unchanged so localization can never corrupt navigation or authorization.

Render every user-facing time literally in Tehran Solar Hijri format, for
example `ساعت ۱۵:۳۴ شنبه ۱۴۰۵/۶/۷`. This covers deadlines, exam times,
publication/read times, deploy events and session expiry on Telegram and Bale.
Do not use `<tg-time>` for display because client localization may show
Gregorian output. Keep ISO-8601 only in machine APIs and persistent state.

## Product role

Dent1402Bot is the Telegram/Bale interaction layer for the existing educational website. It
does not own a second user account, role system, exam database, grade store, or
parallel booklet permission model. Personalized operations use the implemented
single-use account-linking contract with the website.

That account-linking contract is now implemented for grades: the bot opens a
ten-minute website confirmation link, and the authenticated website session
binds the platform identity. Grade screens always read the canonical website
gradebook. Owner/manager grade changes are written through the same website
grade service and never retained as bot state.

The grade screen is a compact Persian report card: student name and grade count
first, then a native Telegram RTL table with course, score/maximum, class
average and rank when the canonical website response contains them. Bale
receives the same rows as readable Markdown cards through the shared fallback
renderer. Both always include refresh and website-report actions.

The payment screen lists only independent bot offers from the shared private
Telegram/Bale commerce SQLite state. It must never display the website catalog
or website payment collections. `/product` opens a persisted four-step inline wizard for title,
amount, optional description, preview, and publication. Owners can inspect,
share, activate, deactivate, and confirm deletion from buttons; no formatted
command is required. Listing, confirmation, and management are local and
immediate, and changes made through one bot must appear through the other;
only final checkout calls the signed website API.

Checkout sends the stored offer snapshot, not callback-provided values. The
website creates a generic order and uses its existing gateway, callback and
verification flow. A successful Telegram API call or opened gateway URL is
never treated as proof of payment.

Payment completion preserves the originating surface. After provider
verification, Telegram checkout returns to Telegram and Bale checkout returns
to Bale with an opaque `receipt_` start parameter. The bot then reads canonical
status from the signed website API; it does not treat the deep link as success.
The website also queues one idempotent verified-success push for that origin
platform so closing the gateway browser cannot lose the confirmation. Success
is not copied to the other linked bot, and the public website receipt is not the
primary or final bot-purchase action. The site contract is
`SITE_BOT_PAYMENT_RETURN_HANDOFF_V1.md`.

Exam participation follows `EXAM_BOT_SYSTEM_V1.md`. Catalog, enrollment,
course purchase, active attempts, per-question answers, timers, study state and
reports are canonical website data. Telegram and Bale render the same shared
structured view and return only opaque action references. The feature stays
behind disabled parity flags until the website contract and central-attempt
migration pass authenticated acceptance; browser-local drafts are not a valid
cross-platform synchronization layer.

## Reference review

The authorized Telegram CLI account reviewed `@sadshekanvpnbot` and
`@NexNodeBot` on 2026-08-13 using `/start` and read-only menu inspection.
Useful patterns were:

- a short branded introduction before the actions;
- two-column task-oriented menus;
- one clear primary action per screen;
- concise labels combining a familiar icon and text;
- a stable path back to the main menu;
- inline callback buttons for app-like navigation and URL buttons only when
  leaving Telegram is intentional.

The implementation does not copy either bot's wording, sales flows, membership
gates, or business logic.

## Visual language

- Persian and RTL-friendly copy is the default.
- Use Telegram's semantic button styles: `primary` for the main next action,
  `success` for constructive submission/confirmation, and `danger` only for a
  destructive action that also receives a confirmation screen.
- Do not use color as the only meaning; every button has an explicit text label.
- Use one consistent emoji per domain: exams, notes, class operations, grades,
  account, notifications, help, and administration.
- Keep a screen to a short title, one or two sentences, an optional status
  block, and at most four rows of actions where practical.
- Edit the current inline-menu message for callback navigation, including
  regular-to-native-rich and native-rich-to-regular transitions. Send a fresh
  message only for a command/user-text entry point or when Telegram reports the
  existing message is genuinely uneditable.
- Site-backed callbacks may run concurrently in a bounded update worker pool
  (`DENT_BOT_UPDATE_WORKERS`, default 8). Local screens such as bot offers must
  not wait for the site relay.
  Rapid callbacks from one user are coalesced before an expensive site call.
  Telegram callback acknowledgement has a short best-effort timeout; a reset
  must not prevent the requested screen from rendering. Repeated site-network
  failure opens a short circuit breaker. Periodic notification and Navid calls
  use a separate API client and thread, so they cannot open the interactive
  circuit or delay polling.
- Never use disabled right-click, hidden links, or cosmetic UI as access
  control.

Telegram clients choose the exact theme-aware colors. The Bot API currently
supports `primary` (blue), `success` (green), and `danger` (red); arbitrary
brand hex colors are not used. Some Bot API deployments still reject the new
field; the transport retries once without color styling while preserving text,
icons, layout, callback behavior, and accessibility.

## Navigation

Main sections:

- Exams
- Notes
- Class operations
- Grades
- Account and devices
- Notifications
- Help
- Owner-only administration

`جزوات` uses normal reply buttons rather than inline callbacks: first the
allowlisted course list, then syllabus sessions, then `🎙 ویس`, `📊 پاور`,
`📓 جزوه`, and `📘 رفرنس`. Every level has `مرحله قبل` and cancel/home exits,
and every exit explicitly removes the persistent reply keyboard. The old
website-viewer and note-submission behaviors of this button are retired. The
secure file and source-channel contract is `PROTECTED_BOOKLET_DELIVERY.md`.

Operational deployment reports use the same typography and semantic status
language. Blue means started, green means succeeded, red means failed, and
orange means rolled back; the text always states the status as well, so color
and emoji are not the only signal.

Each nested screen has a visible main-menu action. Callback payloads are
versioned (`v1:<action>`) and contain no user data, token, permission, or URL.

## Security and privacy

- Telegram private use first requires live membership in
  `@Dent1402Booklets`. Every message and callback is checked with
  `getChatMember` before `/start`, onboarding or any website call. The blocked
  screen exposes only an HTTPS join action and a membership recheck action.
  There is no positive cache; leaving the channel blocks the next interaction,
  and API failure or an unknown status fails closed. Production health also
  requires Dent1402Bot to remain a channel administrator.
- Membership recheck is never a silent callback: Telegram shows an alert when
  the account is still absent/unavailable, acknowledges success before routing
  through the normal auth gate, and falls back to a normal message if callback
  acknowledgement itself cannot be delivered.
- Active onboarding/class-auth progress is user-serialized and retained for 24
  hours. Repeated `/start` resumes the exact saved step; a retired/corrupt step
  recovers to the nearest valid step from the existing payload. Only explicit
  cancel/restart or successful completion clears progress.
- A reply keyboard is explicitly removed before the first inline account/home
  screen after successful authentication. Telegram and Bale do not implicitly
  replace a persistent reply keyboard when a later message contains inline
  buttons, so every successful exit path carries this transport cleanup.
- This is enforced centrally by the shared API coordinator. It durably marks a
  reply keyboard active after a successful send, removes it before every later
  inline/plain send or edit, and records the cleanup version. Missing legacy
  state is treated as stale once after deployment. Explicit cleanup calls first
  consult the same durable marker, so repeated `/start` or `/menu` after a clean
  transition is a no-op and cannot create another authentication-success
  message. If the Bot API cannot remove the keyboard, the destination screen is
  not sent and the next interaction retries; individual workflow handlers cannot
  opt out of this invariant.
- Bale cannot associate a Bale numeric identity with a Telegram identity, so
  it must not claim or simulate this Telegram-only membership check.

- Telegram ID alone never grants website access.
- Historical class-group inventory and name matching are not authorization
  factors and cannot create or approve a mapping. A missing canonical website
  account is never replaced by a bot-local user record.
- Owner-only buttons are hidden and independently checked on callback.
- The owner Telegram ID and bot token live only in the server environment.
- The bot never asks for a website password, website-login OTP, booklet-access
  token, or device credential in chat. The one narrow exception is the
  `bot-onboarding-v1` phone OTP after an own-Contact share; it is short-lived,
  one-time, rate-limited, never stored in bot SQLite, and grants no private
  website access.
- Bot messages never expose grades, private documents, devices, or sessions
  until the website confirms a valid account link and authorization.
- URLs point only to the canonical HTTPS website origin.
- Callback data and logs exclude secrets and personal content.
- Deployment readiness is checked with `python3 -m dent_bot.health`; its output
  contains booleans/counts only and never prints the token or owner identifier.

## Notification interaction

- Feed items show unread/read state from the website and use short opaque local
  callback references; notification IDs, account IDs, tokens, and personal data
  are not embedded in callback payloads.
- The chat view is bounded to the six newest items. It uses one compact unread
  summary, one row per item, a combined refresh/settings row and one home row;
  the canonical website remains the full archive.
- A pushed notification may contain bounded opaque website actions plus an
  explicit `✓ مشاهده شد` button. The Term 7 food notice also has the one
  website-configured allowlisted TUMS food URL; clicking it is never treated as
  reservation confirmation. Opening an item also counts as an explicit
  read action, so an item opened from the feed does not repeat that button.
  Mere transport delivery never changes read state.
- Owners may open a bounded audience screen showing recipient, viewed, and
  pending counts in one compact block plus at most eight recent viewer names and
  localized read times. The website rechecks owner authorization for every request.
- Telegram and Bale publication/read times use the same escaped Tehran Solar
  Hijri formatter; raw ISO values remain machine-only.

## Identity integration contract

Identity v2 supersedes the legacy name-claim UI: manual approval is disabled.
Class users authenticate by canonical-site OTP or the website-login challenge,
and every private section is gated on the resulting permanent link. Account
shows the full profile read-only; proposed field changes are owner-approved
requests and cannot mutate the profile while pending or rejected.

The website-login link remains a ten-minute, single-use challenge confirmed in
an authenticated website session. The class OTP path accepts only the phone
already OTP-ready on the matching canonical class account. Missing OTP setup,
student-number mismatch and every one-to-one conflict fail closed. Names,
roster membership and Telegram account metadata are not authentication factors.
The owner may inspect or delete an existing platform mapping, and deletion
requires a recorded reason and audit event. No owner wizard or service action
may manually create or replace a mapping; the student must relink through OTP
or the single-use website-login challenge.

Authorization is centralized and fail-closed. Canceling either onboarding path
only clears non-secret dialog progress and returns to the public gateway. It
never grants a menu entitlement. `/menu`, unknown text, malformed callbacks and
every persisted dialog first require either a verified generic Contact/OTP
profile or a live canonical mapping. Generic verification opens only the general
menu; deep-linked payments/receipts, personal data and every administrative
action re-check the canonical website link. A class-marked profile cannot fall
back to generic access after disconnect, and a missing/unreachable account API
blocks access rather than rendering the home screen.

Bale onboarding follows `BALE_IDENTITY_ONBOARDING.md`. Telegram and Bale
numeric identities are independent mappings to the same canonical website
account. `/verify`, account status, class OTP, secure website link, profile-edit
review and mapping management are shared application behavior with platform-aware
copy; a Bale screen must never label its identifier or account as Telegram.

Generic intake follows `BOT_ONBOARDING_V1.md`. Every step uses a normal reply
keyboard, mobile is the final field, and Telegram/Bale consume the same website
catalog and encrypted profile record.

Website Account exposes the same two independent platform mappings in an
`اتصال به ربات‌ها` surface. A connected card shows the canonical website name,
numeric platform ID, link time and only platform profile fields that were
actually captured. Website-initiated unlink is CSRF-protected and removes only
the selected platform after atomically queuing a security notice for that old
chat. The selected bot sends the notice with Tehran Solar Hijri time and a
secure relink action; the other adapter never receives or duplicates it.

The retired bulk-review utility may open only the explicitly selected
`source-account` Telethon session with the API ID/hash protected by the archive
worker, and that session remains strictly read-only. It cannot create new v2
links. Any operational report or confirmation uses Bot API, never a personal
Telegram account.

## Student connector CAPTCHA interaction

`/assistant` is a shared Telegram/Bale workflow behind the
`student-assistant-v1` capability gate. The website issues opaque actions and
remains authoritative for jobs and connector sessions. If an action requires
an image CAPTCHA, the bot sends the bounded image only to the requesting
student's private chat and requires Reply to that exact platform message.

Bot SQLite retains only the opaque challenge/job refs, user/message binding and
expiry. Image bytes and answers are never stored. The local binding is consumed
before the signed one-time answer request, so replay or a second message cannot
resume the job. Expired, cross-user, cross-platform and wrong-job answers fail
closed. Solving the CAPTCHA only resumes the same preview transaction; final
reservation/upload still needs explicit confirmation and an upstream receipt.
