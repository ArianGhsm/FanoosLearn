# Bot runtime project logic

> Canonical source now lives in `bot_runtime/` of
> `ArianGhsm/Dentistry1402TUMS`. The former unversioned
> `IntegratedDent1402Tums` sibling is operational/compatibility evidence only,
> not a code source. Runtime data and secrets remain outside Git.

## 1. Purpose

This directory is the operational runtime source for the
Dentistry1402TUMS ecosystem. It coordinates:

- the existing educational website;
- private note-writing channels and their access workflows;
- educational references and public resource distribution;
- authorized Telegram accounts used for project operations;
- a Telegram bot;
- a Bale bot;
- protected Telegram booklet issuance and forensic attribution;
- deployment, monitoring, backups, and incident recovery.

The goal is coordination through shared rules and shared business logic. It is
not a rewrite and must not create parallel users, permissions, content state,
or authentication.

### Active ownership boundary

As of 2026-08-13, another Codex task owns development of the general website.
This workspace must not edit or deploy unrelated website code. Its active scope
is limited to:

- provisioning, securing, monitoring, and backing up the new VPS;
- Telegram bot integration;
- Bale bot integration;
- explicitly authorized Telegram-account/channel operations for note writing
  and references;
- protected booklet ingestion, recipient fingerprinting, delivery and forensic analysis;
- versioned API/authentication contracts required to connect these services to
  the website.

Website-side work must be described in a handoff contract for the site-owning
task. The existing `../dentistry1402tums` repository is read-only context here
unless the user explicitly transfers ownership of a specific file/change.

## 2. Current repository state

The current website remains at:

`../dentistry1402tums`

Inspection on 2026-08-13 found:

- branch `main` at local commit `9901d5354bccb2d72933e612d9c4f7a1b6c8cfe3`;
- remote `origin` points to `ArianGhsm/Dentistry1402TUMS`;
- the clone is shallow;
- local and remote histories report substantial divergence;
- 616 changed paths: 442 modified and 174 untracked;
- no staged changes were observed;
- most changes are under `public_html`;
- the project is custom PHP, HTML, CSS, and vanilla JavaScript with script-based
  PHP/Python/Node tests and no Composer or npm application manifest.

Consequences:

- do not copy the old working tree wholesale into this repository;
- do not merge or rebase until a recoverable snapshot and full-history audit
  exist;
- classify tracked changes, generated files, runtime data, diagnostics, and
  intended untracked source before establishing the integrated baseline;
- ignored `_remote_*`, storage, session, secret, backup, and local deployment
  files must not enter this repository or the VPS deployment package.

## 3. Current infrastructure target

The deployment uses one replaceable ParsPack VPS in Iran. It is the only
configured runtime and backup role; no retired-host connection metadata,
runtime path, relay, or backup source remains in the active workspace.

The Iran server runs Ubuntu 24.04 with 2 vCPU, 2 GB RAM, 50 GB nominal disk and
daily billing. It hosts Telegram, Bale and the protected-booklet worker. Telegram alone
uses an Xray HTTP CONNECT listener bound to `127.0.0.1`; the selected outbound
comes from the owner-provided v2ray subscriptions. The selector refetches both
sources every ten minutes, deduplicates their nodes and atomically retains the
fastest Telegram-reachable candidate. Bale, website traffic, SSH
and backups retain the direct Iran route. The proxy must never bind publicly or
replace the server default route. Exact live state is recorded in
`ops/SERVER_STATE.md`.

Non-secret host and access status are tracked in `ops/SERVER_STATE.md`; ignored
connection metadata and key paths are in `.codex-local/iran-server.json`. Passwords,
private keys, bot tokens, owner identifiers, and secret environment values must
never be recorded in tracked files.

This server is sufficient for the initial 50-60 user pilot when:

- only one heavy PDF process runs at a time;
- secure-raster PDF personalization is queued, batched and bounded;
- no source or personalized PDF persists after delivery;
- bot webhook handlers return quickly;
- background work is queued;
- disk, memory, load, backup freshness, and wallet/billing risk are monitored.

The design target may consider roughly 200 users, but no capacity claim may be
made until production-like load tests succeed.

## 4. Single-node runtime architecture

The initial release uses one server:

```text
systemd on the Iran VPS
   |-- Xray Telegram egress proxy (loopback only)
   |-- Telegram bot polling worker
   |     +-- one-at-a-time PDF recipient fingerprinting queue
   |-- Bale bot polling worker
   +-- deployment notifier and retry timer

Private local filesystem
   |-- bot SQLite and issuance/trace records
   |-- encrypted runtime configuration source for laptop backup
   +-- per-job temporary PDF files removed in every exit path
```

Use systemd isolation and a host firewall. The bot VPS does not host the website
or a public Reader endpoint. Do not install a public administration panel merely
for convenience. SSH administration is key-only.

Failures must be isolated:

- a PDF worker failure must not take down the site or bots;
- Telegram failure must not break Bale or the website;
- Bale failure must not break Telegram or the website;
- bot delivery failures must be retried with a bound, backoff, and durable safe
  state;
- protected-booklet worker failures must not log users out of the site or stop either bot.

## 5. Shared integration logic

Telegram and Bale should have thin platform adapters over a shared application
service layer.

Feature parity is mandatory even when one runtime is powered off. Every feature
addition, change or removal must update Telegram and Bale together: commands,
menus, callbacks, permissions, workflows, copy semantics and persisted state
transitions come from the shared application layer. Offline unit/contract tests
remain mandatory for an unavailable platform; only its live network smoke may
be recorded as pending. Adapter-only divergence is allowed solely for a real,
documented platform API limitation and must provide a tested equivalent
fallback. Background jobs that would create duplicate external side effects
use one elected coordinator without removing the interactive feature from the
other platform.

As verified on 2026-08-25, the Iran VPS reaches Bale and the signed website API
directly and reaches Telegram only through its isolated loopback Xray egress.
Both bot services run there from the shared application code with separate
users, environments and platform-runtime SQLite state. Their bot-commerce
offers use one shared SQLite store; poll offsets, dialogs, scheduling and
delivery receipts stay platform-local. The laptop is never a production relay.

The root-owned `/etc/integrated-dent` directory is a shared parent for several
least-privilege services and therefore uses mode `0711`: service users may
traverse to explicitly authorized files but cannot list the directory. Secret
files retain restrictive per-service ownership and `0600`/`0640` modes. Every
installer, deployer and restore path touching this parent must preserve that
invariant; changing the parent to `0700` or to a service-specific group can
silently take unrelated runtimes down.

Shared logic owns:

- commands and action names;
- permissions and owner/manager checks;
- course, semester, cohort, and content identifiers;
- notification templates and delivery intent;
- idempotency and deduplication;
- audit records;
- job scheduling;
- safe error handling.

### Account linking and grades

- The website's private grade storage and `grades_store.php` are the sole
  authoritative grade source. Telegram and Bale fetch current values and send
  authorized mutations through the website API; bot SQLite never stores grade
  rows or becomes a synchronization peer.
- A bot identity is linked through a ten-minute, single-use website challenge
  confirmed by the already authenticated website session. Website passwords
  and website-login OTP codes are never entered into bot chat. The only OTP
  accepted in chat is the separate `bot-onboarding-v1` phone-verification code
  documented in `docs/BOT_ONBOARDING_V1.md`; it cannot link a website account
  or authorize private data.
- Service calls are signed with a dedicated 32-byte HMAC secret, a short clock
  window, and a one-time nonce. The shared secret is server environment only.
- Platform identifiers are encrypted at rest in website storage. Tokens are
  stored only as keyed hashes. Revoked or missing website accounts fail closed.
- The historical class-group inventory/import workflow is retired and must not
  participate in authorization. Any retained ignored inventory is audit input
  only; it cannot create, replace or approve a mapping.
- Manual/name-based claims are disabled. Existing pending claims are rejected
  by the audited identity-v2 migration and cannot unlock any personal data.
  The bot never asks for a password, national code, or payment secret. Generic
  intake may ask only for its short-lived phone OTP after the user shares their
  own Contact as the final field; that OTP does not create an account link.
- Canonical bot authorization is versioned separately from link existence.
  Every private website action and both bot gates require
  `authComplete=true` backed by a durable `bot-canonical-auth-v1` record. Old,
  migrated and manually approved mappings remain recoverable one-to-one
  history, but cannot grant menu or private access until the same identity
  completes class OTP or the authenticated single-use website-login flow.
  This rule has no owner exception and is enforced before commands, callbacks,
  dialogs and the signed website service dispatch.
- Grade reads and writes are always evaluated against current website roles and
  cohort scope. The owner may manage all cohorts; other managers remain bounded
  by the existing website permission model.
- The account-link and grade service contract is deployed on the website, and
  both owner platform identities have been linked and verified. The current
  Iran runtimes call the signed website API directly; the historical Vercel
  relay is not required for their normal path.
- BitNinja blocks Cloudflare Worker POST traffic and the normal domain path.
  The active origin hostname exposes only the existing API document root. Do
  not add other content there or use it as a general proxy. A hosting support
  request to disable the unwanted BitNinja protection remains open.
- Never work around the network block by keeping an unsynchronized grade copy
  in bot SQLite, routing through the laptop, disabling TLS, or using an
  untrusted public proxy. The Vercel relay and signed website endpoint must
  continue to pass the real VPS health check after every deployment.
- If an independent relay is unavailable, moving the authoritative grade store
  to the VPS is a separately approved migration. It requires an encrypted
  database, a website adapter, laptop backups with restore tests, and explicit
  acceptance that grades become unavailable when that single VPS is down.

### Bot purchases and payment gateways

The production contract is `docs/BOT_COMMERCE_V2.md`. Product definitions now
include lifecycle, time windows, audience, capacity, per-user purchase limit,
optional fulfillment, opaque revocable share token and audit version. The
Telegram/Bale shared SQLite is the product source of truth; the existing website
payment store remains the order, transaction, callback and reconciliation
source of truth. This is intentionally not a website-catalog mirror.

Ordinary users never see an owner payment menu. Their `🛍 محصولات` entry is
conditional on current server-side eligibility. Telegram supports owner-only
inline sharing of opaque deep links; Bale keeps the same product outcome through
normal buttons/deep links and does not fake unsupported inline mode.

- The bot must never mirror or list the website product catalog or website
  payment collections. Bot payment offers are an independent domain stored in
  one private shared Telegram/Bale SQLite database at
  `/var/lib/integrated-dent/shared/payment-offers.sqlite3` and are visible only
  through bot menus. Creating, changing or deleting an offer through either
  adapter is immediately reflected in the other adapter.
- `/product` (`/payform` is an alias) opens a persisted interactive wizard for
  title, preset/custom amount, audience, preview, optional description and publication.
  Owners inspect, share, activate, deactivate, and confirm deletion entirely
  inside the bot. The public user menu evaluates lifecycle/audience from the
  local shared table and obtains a short-lived signed website state summary for
  successful/pending counts and remaining capacity; API failure hides the
  conditional entry rather than exposing stale or unauthorized products.
- On explicit checkout only, the bot sends an HMAC-signed snapshot containing
  its opaque offer reference, title, description, amount and idempotency key to
  `createBotPayment`. Either a canonically linked website account or a verified
  non-class Contact/OTP onboarding profile may pay. Generic payers use an opaque
  verified-phone HMAC key; their optional self-declared student number is never
  an authorization key. A user callback cannot supply or alter these fields.
- The website does not add the offer to its catalog. It creates a generic
  `bot-offer` order with `item_id=0`, uses the existing configured gateway,
  callback and verification flow, and remains authoritative for payment status
  and payer identity. The shared bot-commerce store remains authoritative for
  offer definition.
- The bot never collects a card number, website password or website-login OTP
  in chat. The bounded onboarding phone OTP exception is not a payment or login
  credential and remains governed by `docs/BOT_ONBOARDING_V1.md`.
- The website creates the order and returns the configured gateway URL. Payment
  success exists only after the existing callback verifies it with the
  provider. Opening a link or returning to the bot is not proof of payment.
- Mock gateways are allowed only when `DENT_APP_ENV` is explicitly
  `development` or `test`. Production uses only enabled, configured real
  gateways.
- Checkout and status use the same active, end-to-end-tested HTTPS relay as
  grades. The signed bot service request supplies the offer amount; the website
  validates its range and remains authoritative for gateway and order state.
- Checkout continuity follows the originating surface. A Telegram checkout
  returns to Telegram and a Bale checkout returns to Bale; the public website
  receipt is not the final bot UX. The return carries only an opaque order token
  and the bot fetches canonical status through the signed website service.
- The first provider-verified success creates one idempotent delivery intent for
  the originating platform only. It must never fan out to the user's other bot
  mapping or through a personal account. Bot runtime state writes a delivery
  receipt before site ACK so callback/lease retries cannot duplicate the
  financial confirmation. The versioned website handoff is
  `docs/SITE_BOT_PAYMENT_RETURN_HANDOFF_V1.md`.
- The owner transaction center supports canonical detail, product/status/date/
  platform/gateway filters, paginated reports and full matching CSV/TXT export.
  Manual corrections can only move an unverified order among pending, failed,
  canceled and expired; they require a note and durable actor/before/after audit.
  They can never manufacture or rewrite a verified success.

### Website exams in Telegram and Bale

- The canonical design and activation gate are in
  `docs/EXAM_BOT_SYSTEM_V1.md`; required site-owner work is in
  `docs/SITE_EXAM_BOT_HANDOFF_V1.md`.
- The website is the sole authority for exam catalog, access/enrollment,
  prices/discounts, verified orders, active attempts, answers, timers, study
  state and reports. Bot runtime SQLite stores none of these.
- Telegram and Bale use one shared structured-view renderer and opaque,
  user-bound, short-lived action references. Active assessment payloads never
  expose correctness or explanations.
- Current browser-local exam drafts must migrate to central revisioned,
  idempotent active attempts before either bot capability flag is enabled.
- Website and bot purchases converge only through the existing canonical
  gateway callback verification; opening a gateway link never grants access.

### Bot responsiveness and background work

- Activation is verified against the process actually serving updates, not a
  fresh health interpreter launched from the `current` symlink. Telegram and
  Bale runtime checks compare `/proc/<MainPID>/cwd` with the resolved active
  release. Code deploys restart the service and roll back to the previous
  release on failed health; they preserve live SQLite/environment state rather
  than rewinding it from the pre-deploy backup.
- This activation rule applies to routine code deployment, full provisioning
  and disaster-state restore for both Telegram and Bale. No script may report
  success after changing only a symlink or using `enable --now` against an
  already-running service; restart plus active-release CWD proof is mandatory.

- Before handling any private Telegram update, the runtime checks the sender's
  current membership in `@Dent1402Booklets` through Bot API `getChatMember`.
  There is no positive cache: leaving the channel blocks the next interaction.
  Network/API failure and an unknown membership status deny access and show
  only join/recheck actions. The channel bot-administration prerequisite is a
  production health invariant. Bale has no trustworthy mapping from a Bale
  identity to a Telegram account, so it does not pretend to enforce this
  Telegram-only condition.
- The membership recheck callback must always provide visible feedback: a
  Telegram alert while membership is still absent/unavailable, and a success
  acknowledgement plus navigation through the normal auth gate after membership
  is confirmed. Callback-answer transport failure falls back to a normal bot
  message instead of looking like a dead button.
- One user's messages and callbacks are ordered through bounded lock stripes
  before any dialog mutation, while callback versioning still coalesces rapid
  duplicate inline taps. Repeated `/start` resumes active onboarding/class auth;
  onboarding state is retained for 24 hours and an obsolete step recovers from
  its existing payload rather than clearing progress. Explicit cancel/restart
  and successful completion remain the only reset paths.
- Telegram and Bale retain normal reply keyboards until Bot API receives an
  explicit `remove_keyboard`. Leaving class authentication for a site-link
  screen, completing either OTP flow, or entering the post-onboarding inline
  home therefore performs that cleanup as part of the transition. The durable
  cleanup marker makes explicit cleanup idempotent: after the keyboard has been
  removed, repeated `/start` and `/menu` go straight to their destination and do
  not repeat the authentication-completed transport message.

- Telegram long polling, interactive update handling, and periodic site work
  must not share one blocking loop. Polling continuously fetches updates,
  bounded worker threads handle user interactions, and a separate background
  thread performs notification claim/ack and Navid scheduling.
- The update queue is bounded, offsets advance in Telegram update order only
  after handlers complete, and SQLite access is serialized with an in-process
  lock. A slow relay or site timeout must never pause receipt of new Telegram
  updates.
- Callback acknowledgement is best-effort with a short timeout. Rapid repeated
  callbacks from one user are coalesced before expensive work. Interactive and
  periodic jobs have separate signed-site clients/circuit breakers, so a Navid
  or notification failure cannot poison the user's grade/account path.
- This is still one systemd process on the single-node pilot VPS, not a claim of
  distributed queueing or horizontal scale.

### Website notifications in Telegram and Bale

- `notifications_store.php` remains the sole source of notification content,
  recipient snapshots, safe internal CTA paths, and per-user read timestamps.
  Bot SQLite stores only callback references and delivery receipts; it does not
  mirror the feed or invent a second seen state.
- The signed bot API exposes the current linked user's feed, explicit read
  mutation, owner-only audience reporting, and owner-only lease-based delivery
  claim/ack operations. Every operation resolves the current website account
  and permissions again.
- Delivery is eligible only for a recipient who has started the relevant bot
  and linked that platform identity to the website. "All users" means all
  notification recipients reachable under those prerequisites; Telegram and
  Bale cannot accept unsolicited bot messages to users who never started the
  bot.
- Platform send success is not treated as proof of reading because neither bot
  transport supplies a reliable read receipt. Opening an item or pressing
  `مشاهده شد` explicitly writes the canonical website read timestamp. The owner
  sees viewed/pending counts and bounded recipient details from that state.
- Notification actions are optional. The website validates local CTA paths and
  opaque authenticated actions. The sole current external exception is the
  canonical Term 7 food URL `http://foodstu.tums.ac.ir`, emitted only by the
  website-owned schedule config; raw external CTA URLs from bots are not accepted.
- Student reminders and upstream integrations follow
  `docs/STUDENT_ASSISTANT_V1.md`. Telegram, Bale, and the website share one
  canonical reminder/preference/acknowledgement state. Per-assignment “submitted”
  and per-cycle “reserved” actions are durable website state, not bot SQLite.
  Self-attestation is visibly distinct from an upstream-verified receipt.
- Navid, Food, and Saba credentials are collected only on an authenticated
  website screen and encrypted at rest. CAPTCHA remains user-in-the-loop and
  bound to the same linked user and short-lived job; automated CAPTCHA solving
  or anti-bot bypass is not an accepted dependency.
- Reservation and assignment upload require an explicit final student
  confirmation and an upstream receipt before success is claimed. Both bot
  adapters expose the same workflow even if only one platform is live-tested.
- Student connector CAPTCHA is a user-in-the-loop continuation, never an
  automated solver. The website binds one short-lived challenge to the linked
  account, origin platform and connector job; shared bot code sends the image
  only in that private chat and accepts only a one-use Reply to the exact
  message. Bot SQLite stores opaque refs/message binding/expiry but never image
  bytes or answers. CAPTCHA completion resumes the preview job and cannot skip
  final confirmation or upstream receipt verification.
- The website implements the signed `student-assistant-v1` summary, opaque
  action and CAPTCHA-answer state machine in canonical website storage. Its
  deterministic connector exists only under an explicit test/development
  fixture gate; production menus remain off until encrypted per-student
  credential setup, real Navid/Food/Saba workers and verified upstream receipts
  replace that fixture. An endpoint contract alone is not evidence that an
  upstream reservation or upload exists.
- Site delivery records use a durable per-platform/account/notification key,
  bounded attempts and an expiring lease. Runtime SQLite writes a receipt
  immediately after platform send and before site ACK so an ACK network failure
  does not resend the message. Terminal delivery metadata has bounded retention.
- Every user-visible bot timestamp, including notifications, Navid deadlines,
  challenge expiry and deployment reports, is rendered literally in Tehran
  Solar Hijri format: `ساعت ۱۵:۳۴ شنبه ۱۴۰۵/۶/۷`. Raw ISO/Gregorian dates and
  `<tg-time>` are not user-facing because client localization is not guaranteed
  to remain Jalali. Machine storage and signed APIs remain ISO-8601.
- Required site settings are `DENT_BOT_SERVICE_SECRET`,
  `DENT_SITE_PUBLIC_URL`, and a deliberate `DENT_BOT_NOTIFICATIONS_SINCE`
  rollout cutoff. Runtime tuning uses notification poll and batch settings from
  the platform environment examples.
- Each platform polls notifications from the Iran runtime. Bale reaches the
  signed website API directly; platform delivery state remains independent.
- Behavioral Exam Reminder notifications are retired across the website,
  Web Push, Telegram, and Bale. Incomplete/resumable attempts, inactivity, and
  flagged-question review remain normal exam state but never create a
  notification candidate or delivery. Legacy `examReminders` preferences are
  ignored safely; legacy exam-reminder feed records are removed and pending or
  leased platform deliveries are terminally canceled without changing any
  other notification category.

### Canonical ClassOps foundation (2026-09-07)

- The website owns the versioned `classops-v1` source of truth in its private
  `storage/classops/store.json`; Telegram and Bale are not ClassOps databases.
- Phase 1 contains only validated generic items, lifecycle, immutable revision
  history, optimistic concurrency, idempotent owner mutations and bounded audit.
  It intentionally has no audience resolution, destination registry, scheduler,
  delivery, AI, specialized task/exam/requirement engine, digest or Saba logic.
- ClassOps is independent of bot identity/link state, payment and notification
  deliveries, Term 7 assignments, food confirmations and platform-local SQLite.
  No Phase 1 state is permission to send a Telegram/Bale/group/channel message.
- The current hosting uses a dedicated crash-safe/fail-closed atomic JSON store
  because PDO SQLite support is not verified. Once present, the store is a
  critical double-read member of the website's verified laptop snapshot.
- Future adapters must obtain a structured owner-confirmed ClassOps item through
  the website contract and integrate with the existing canonical notification
  boundary; they must not mutate storage or deliver directly.

### Term 7 academic and food reminders (2026-09-02)

- `dentistry1402tums/public_html/api/academic_term7.php` is the versioned
  canonical resolver for Term 7 theory, Rotation A/B practical patterns, red
  practical closures, makeup days and the explicit Thursday Endo correction
  `08:30-10:30`. Telegram and Bale never duplicate the timetable in handlers.
- Group membership is keyed only by canonical student number in deploy-safe
  website storage. The initial assignment store is empty: Term 6 groups and PDF
  layout are never used to infer Term 7 membership. Missing assignments retain
  theory and fail closed for practical events.
- One central website scheduler is opportunistically ticked by the existing
  notification workers. Deterministic source keys and website locks make a
  Telegram/Bale double tick idempotent. Only auth-complete links in the exact
  `dentistry-1402` cohort are candidates.
- Tomorrow summaries run in `Asia/Tehran` at the 21:00 slot. Food reminders use
  Tuesday 15/17/19/21/23 and Wednesday 01/03/05 slots; 06:00 creates nothing.
  Both cycles are disabled outside the configured Term 7 active date window.
  The URL button never confirms. Only the authenticated `رزرو کردم` callback
  writes canonical user/week state, suppressing later delivery on both platforms.
- Account DIS comes from the existing signed website `disNumber`; bots keep no
  DIS table. The default-password note is shown only when a real DIS exists.

### Daily Navid assignment check

- The canonical contract is `docs/NAVID_DAILY_AUTOMATION.md`.
- Once per Tehran day, one elected bot runtime sends a fresh Navid captcha only
  to the owner; only a reply to that exact challenge message is accepted.
  Interactive `/navid` refresh is available on both Telegram and Bale.
- The website performs the actual Navid sync and remains the assignment
  snapshot and notification source of truth. Bot SQLite stores only schedule,
  challenge-message and delivery state.
- The first successful assignment snapshot is a silent baseline. Only later
  new assignment keys create deduplicated canonical site notifications.
- Telegram and Bale fan-out uses the existing notification claim/ack pipeline,
  so notification content, audience and read timestamps are never forked.
- The owner Navid screen renders the bounded current assignment list as a
  Telegram Bot API native RTL Rich Message with a bordered/striped/compact
  table and expandable nearest-assignment card. Bale receives the same fields
  and actions as bounded Markdown cards until its API supports equivalent
  native rich blocks; `<pre>` is fallback formatting, not a Telegram rich table.
  Course, title, description and Jalali deadline come from the canonical
  website snapshot and share one Telegram/Bale implementation.
- The daily scheduler has exactly one elected runtime to prevent duplicate
  captcha challenges. Telegram and Bale keep identical interactive Navid code;
  the laptop is not a relay or scheduler.
- Class-group assignment screenshots follow the disabled-by-default claim/ack
  design in `docs/NAVID_DAILY_AUTOMATION.md` and
  `docs/SITE_NAVID_GROUP_HANDOFF_V1.md`: new, seven-day and one-day delivery
  intents are canonical website state; bots deliver one cropped assignment
  image and store only a transport receipt. Group IDs remain protected runtime
  settings.

Adapters own only platform differences:

- webhook validation and update parsing;
- chat/user/message identifier mapping;
- keyboard/button formatting;
- file upload/download API differences;
- platform-specific response limits;
- retryable API errors.

Presentation follows `docs/BOT_UX_SYSTEM.md`: stable semantic emoji, restrained
rich text, and literal Tehran Solar Hijri timestamps for every user-visible date.
Platform-specific rich features always require a readable fallback, especially
for Bale.

### Permanent website-to-platform identity mapping

- The website store enforces one platform account per canonical student and one
  canonical student per platform account. Canonical-site OTP and secure website
  confirmation fail on conflict instead of silently replacing either side.
- Manual/name claims and roster-based authorization are retired. The audited v2
  migration terminates legacy pending claims and preserves only already-valid
  canonical links; neither display-name similarity nor group membership grants
  access.
- The owner mapping surface is inspect/delete only. Deletion requires a reason,
  records a private audit event and uses opaque callback references. Only the
  student-facing canonical OTP or single-use website-login flow may create a
  mapping; nobody, including the owner, can manually add or replace one.
- Telegram and Bale mappings are separate one-to-one namespaces. The same
  canonical student may own one mapping on each platform, but confirming Bale
  never imports, replaces or infers Telegram identity. Shared `/verify` and the
  platform-aware secure-link flow are documented in
  `docs/BALE_IDENTITY_ONBOARDING.md`.
- Generic onboarding is a separate encrypted intake profile keyed by a
  verified-phone HMAC. Each platform verifies the phone independently before
  joining the profile; this synchronization never implies a canonical website
  account mapping. The fixed field order, 106-entry catalog (75 public medical
  centers plus 31 explicitly marked Islamic Azad medical units),
  reply-keyboard UX and backup contract live in `docs/BOT_ONBOARDING_V1.md`.
- In that fixed order, required `entryYear` follows institution and precedes
  entry semester. The shared catalog and website validation allow exactly the
  Persian values `۱۳۹۹` through `۱۴۰۵`; primary-class profiles always store
  `۱۴۰۲`, and recovery from an older in-progress dialog resumes at this missing
  step without discarding already-entered fields.

Webhook endpoints must use unguessable endpoint paths or supported signature
validation, HTTPS, strict body-size limits, idempotent update IDs, and fast
responses. Bot tokens must live only in the server environment with restrictive
permissions.

## 6. Telegram accounts versus Telegram bot

These are separate integrations:

- Telegram bot: Bot API token, limited to bot capabilities. The initial
  single-node pilot uses bounded long polling under systemd; a future HTTPS
  deployment may switch the adapter to a validated webhook without changing
  shared business logic.
- Authorized Telegram accounts: user-session credentials for explicitly
  approved operational workflows such as managing owned note-writing channels.

User-account automation must be separately designed and approved before
implementation. Session material is highly sensitive, must be encrypted at
rest, must not be copied into Git or chat, and must have revocation and audit
procedures. It must not be used for unsolicited messaging, scraping unrelated
users, bypassing access restrictions, or violating platform rules.

Authorized Telegram user sessions are read-only by default. A user-account
mutation requires explicit, action-specific owner authorization in the current
task; a general request to notify bot users is never permission to send from a
personal account. Class identity review is permanently read-only on Telethon.
Approvals, confirmation messages and owner review reports use the Bot API
adapter for the relevant platform.

### Dent1402Bot checkpoint

- `@Dent1402Bot` is active on the ParsPack Iran VPS as
  `integrated-dent-bot.service`.
- It provides live account linking, canonical grade cards, independent bot
  payment offers, notifications, owner controls and the navigation paths for
  exams, notes, account/devices and help.
- It also carries the deployment notifier transport, but notifier events and
  interactive educational updates remain separate modules and state paths.
- The authorized `@arianbc` CLI session was used only to inspect the `/start`
  UX of two user-specified reference bots and to verify Dent1402Bot's real
  owner menu. Their wording and business workflows were not copied.
- The owner Telegram ID is discovered from the authorized session and stored
  only in the VPS environment, not in source or documentation.
- Grades are fetched from the website and never copied into bot state. Payment
  offers are intentionally bot-owned state shared by Telegram and Bale; only
  their resulting orders and gateway status live on the website. Exams remain
  website-owned; protected booklet source files remain in the private Telegram
  channel and issuance/trace state remains in Telegram bot SQLite.
- Account linking is short-lived and single-use, initiated through an
  authenticated website session. Telegram identity alone never authorizes
  website data.
- The interaction rules are defined in `docs/BOT_UX_SYSTEM.md`.

## 7. Content and channel management

The integrated system may coordinate:

- note-writing channels;
- reference channels and reference catalogs;
- course/semester/cohort mappings;
- document intake and publication state;
- website resource cards and links;
- authorized managers and writers;
- Telegram/Bale notifications.

Each content item needs one stable internal identity. Platform message IDs and
website URLs are references to that identity, not separate records of truth.
Every publish/edit/delete operation must be idempotent and auditable.

Public educational resources and private protected notes are different data
classes:

- public resources may be published to the existing download host;
- private originals and unwatermarked assets may not be placed in any public
  download-host directory;
- moving a file to another host does not make it private unless that host has
  verified private storage and authenticated delivery.

## 8. Protected booklet delivery and attribution

The former website Reader is retired. The only protected booklet product path is
the existing Telegram `جزوات` workflow defined by
`docs/PROTECTED_BOOKLET_DELIVERY.md`. No website viewer, Mini App, parallel login
or persistent private-PDF service may be recreated.

The private Telegram management channel is the file source. Caption parsing maps
course hashtags, term, session one through forty and content kind. Every request
passes the normal canonical bot-auth gate, required-channel membership and the
current course/term permission policy before enqueue and again before delivery.
Voice and other non-PDF media are copied unchanged with
`protect_content=true`.

Term-access policy is canonical in the shared Telegram/Bale commerce SQLite
and follows `docs/TERM_SUBSCRIPTION_ACCESS_V1.md`. Term 7 stays authenticated-open
until Solar Hijri `1405-07-01`; from that Tehran boundary its fresh access
decision is current-month provider-verified paid entitlement OR active
owner-granted complimentary entitlement. The default price is 1,500,000 rials
(150,000 tomans), each entitlement ends at the next Solar Hijri month boundary,
and no rolling-30-day, prorating or automatic charge path exists. Other terms
stay unchanged unless an owner policy explicitly protects them. The check is
repeated before enqueue, dequeue, cached resend, non-PDF send and final PDF
upload. Legacy orders never become entitlements and migration grants nobody.

PDF delivery uses `recipient-pdf-v9` while the detector preserves
`recipient-pdf-v1` through `recipient-pdf-v8` compatibility. The signed website service returns the
fully authenticated user's canonical full name, national code and verified
mobile. A random issuance ID and server-only HMAC key derive a non-guessable
fingerprint, trace code, layout seed and opaque issuance-only token. Raw identity
is absent from every hidden payload. Full canonical name, national code and
mobile remain visibly burned into page pixels as a deliberate deterrent. A
page-aware HMAC layout places 3–5 full B Nazanin Bold identity/trace blocks and
1–2 compact trace blocks on cover, text, image and sparse pages. Candidate
scoring avoids headings and paragraph starts, prefers true whitespace, and
penalizes the central 50% ROI of each source image. An image-heavy page puts at
most one smaller, faint compact trace at a figure edge/corner rather than its
center. Two independent
secret constellations carry a page-specific opaque 40-bit token with
repetition-3 error correction. The final edition has exactly one raster image
and one content stream per page, with no live text, annotations, attachments,
original stream, Form/XObject watermark or detachable security tail.

The normal path renders one page at a time with PyMuPDF, writes four-page
batches and uses qpdf to merge and validate them. pikepdf is a development merge
fallback only when qpdf is unavailable. Delivery does not use OCR, AI, GPU,
Ghostscript or ImageMagick. Adaptive DPI/quality and a 49 MiB output ceiling keep
long documents within Telegram's limit. A bounded queue accepts bursts while the
current production profile runs one watermark job on its 2-vCPU host. A
constrained 1 GB benchmark proved that 20 simultaneous requests are accepted
without processing 20 PDFs in parallel; 10/50/150-page mixed fixtures measured
about 107/107/102 MiB peak RSS after batching. Concurrency is configuration-driven and
may be raised only after another constrained benchmark. Every job uses an isolated private temporary directory deleted in
all exit paths, plus safe age-based orphan cleanup. The first successful upload
atomically stores Telegram's per-user `file_id`; later sends reuse it without
another download, watermark or upload, but only after the same fresh term
authorization succeeds.

SQLite keeps issuance ID, user ID, document ID, trace code, fingerprint hash,
watermark version, source hash, issued time and Telegram IDs. These attribution
records survive source-catalog cleanup. The server-only fingerprint key and the
SQLite state are covered by the normal DPAPI-encrypted laptop backup. Source and
personalized PDF bytes are transient on the VPS; the management channel remains
the source-file authority.

The owner-only `dent_bot.forensic_detector` is separate from delivery. It checks
visible OCR trace, opaque metadata and both raster constellations independently
and returns recovered symbols, ECC state,
confidence, successful/failed channels and evidence. A weak
match is never reported as a definitive attribution. Screenshot, scan and photographed-page analysis may run in an
optional OpenCV environment off production and align against the original page
with deskew, perspective and crop normalization. Embedding and detection are
selected by `watermark_version`, allowing future print/scan or collusion channels
without breaking old issuances.

## 9. Storage rules

For the initial single-node release:

- the VPS private local filesystem is the online source of truth;
- private roots must be outside `public_html` and denied by Nginx;
- filenames never control storage paths;
- path-boundary validation is mandatory;
- temporary files must be cleaned on success and failure;
- cache loss must be recoverable from originals and metadata;
- 40 GB disk usage must be monitored with warning and critical thresholds.

The current public download host remains suitable only for public resources and
owner upload-center content under its existing contract. It is not a protected
booklet source or personalized-PDF store.

S3, shared storage, and multi-node operation are not implemented requirements
for this release and must not be claimed.

## 10. Backup and recovery contract

The laptop must always hold an independent recoverable backup of critical server
state.

Required backup content:

- authoritative application storage;
- original private PDFs;
- database/SQLite/JSON metadata in a consistent snapshot;
- auth data and active operational state where required for continuity;
- Nginx, PHP, systemd, firewall, cron/timer, and deployment configuration;
- encrypted production environment/secrets backup;
- bot integration mappings and durable queues;
- the shared Telegram/Bale bot-commerce SQLite store as a consistent snapshot;
- a manifest containing versions, checksums, timestamps, and schema versions.

Excluded or optional content:

- rendered page cache;
- personalized watermark cache;
- temporary files;
- rebuildable dependencies and logs outside the incident-retention policy.

Policy:

- create a consistent runtime snapshot at least daily and pull it immediately
  to the ignored `backups/vps-state-iran/` directory inside this project;
- encrypt the laptop copy with Windows DPAPI and remove all plaintext staging;
- do not keep a second full copy of source code or reinstallable packages in
  each snapshot; this source tree is the code recovery source;
- Windows Task Scheduler runs daily and on login, processes VPS roles
  independently, and records per-role success/degraded state;
- retain at least 14 daily and 8 weekly laptop copies;
- verify checksums after transfer;
- alert when the newest laptop backup is older than 24 hours;
- perform and document a test restore at least monthly;
- take a fresh laptop backup before every production migration or risky deploy;
- provider snapshots are useful but do not replace the laptop backup.

The implemented commands are:

- `scripts/backup-vps-state.ps1` for consistent, encrypted, retention-bounded
  snapshots;
- `scripts/restore-vps-state.ps1` for local extraction and mandatory integrity
  verification;
- `scripts/restore-vps-state-to-server.ps1` for controlled recovery onto a
  newly provisioned server;
- `scripts/install-vps-backup-schedule.ps1` for daily and login-triggered pulls;
- `docs/VPS_DISASTER_RECOVERY.md` as the zero-to-running runbook.

Synchronization is not backup if deletions automatically propagate. Backups
must be versioned and recoverable.

## 11. Security baseline for the VPS

Before application deployment:

- record the server's SSH host fingerprint through a trusted first connection;
- create a named sudo deployment user;
- install the laptop SSH public key;
- disable password authentication after key access is verified;
- restrict or disable direct root SSH login;
- enable UFW for only required ports, normally 22, 80, and 443;
- install Fail2ban and unattended security updates;
- configure time synchronization and the correct application timezone handling;
- configure bounded PHP upload/body/memory/execution limits;
- install Poppler, PHP-FPM, GD, fileinfo, PDO SQLite, and a Persian-capable font;
- add swap suitable for failure tolerance, not as a substitute for RAM;
- run workers with memory/CPU limits and lower scheduling priority;
- configure TLS renewal and health checks;
- prevent logs from containing credentials, cookies, tokens, private paths, or
  personal watermark text.

## 12. Deployment and migration rules

The existing live site remains authoritative until migration completes.

Required sequence:

1. Establish a recoverable repository baseline.
2. Mirror and back up live production storage to the laptop.
3. Provision and harden the VPS.
4. Configure automated encrypted backup and test a restore.
5. Deploy code to a staging hostname/IP without changing public DNS.
6. Restore a sanitized test data set and run migrations.
7. Verify public pages, owner access, normal-user access, sessions, storage,
   uploads, bots, recipient PDF issuance, attribution detection, and backups.
8. Freeze or safely synchronize mutable production state for final cutover.
9. Take a final backup.
10. Change DNS with a rollback window.
11. Keep the old host untouched until continuity is confirmed.

Do not deploy local storage, sessions, backups, secrets, `.env`, diagnostics, or
ignored remote mirrors as application code.

## 13. Monitoring and operations

At minimum monitor:

- website HTTPS health;
- Telegram and Bale webhook health and backlog;
- PDF worker status and queue age;
- CPU load, memory pressure, swap, disk usage, and inode usage;
- TLS certificate expiry;
- latest successful server backup;
- latest successful laptop pull;
- failed SSH/auth attempts;
- application error rate;
- ParsPack wallet/billing risk and Telegram subscription/egress health.

Alerts must avoid personal data and secrets.

All operational and product-facing text is UTF-8. Persian summaries crossing
Windows PowerShell and SSH are encoded to strict UTF-8 bytes and Base64-wrapped;
passing raw Unicode through the native pipeline is forbidden because a console
code page can irreversibly replace characters with `?`. Persian, ZWNJ, RTL
punctuation, and emoji require a byte-level round-trip regression test before
notifier or bot changes are deployed.

### Deployment status bot

The central implementation is `deploy_notifier/` and its contract is
`docs/DEPLOY_STATUS_NOTIFIER.md`.

- It reports deployments for the website, archive worker,
  Telegram bot, Bale bot, and integrated operations.
- Every event is durably queued before transport.
- Telegram, Bale and the owner-only website notification center are independent
  delivery channels and retry with bounded backoff from one durable spool.
- Telegram uses only the loopback Xray proxy; Bale and the signed website call
  use the Iran host's normal route. Bale receives native Markdown converted at
  the transport boundary, never Telegram HTML.
- Website delivery is signed, linked-owner-only, idempotent by event ID and
  marked `disablePush` so bot notification workers cannot duplicate the direct
  Telegram/Bale lifecycle messages.
- The website and archive worker emit lifecycle events but do not store bot
  tokens or implement their own transports.
- A deployment emits `started` plus exactly one of `succeeded`, `failed`, or
  `rolled_back`.
- A stable event ID makes retries idempotent.
- Bot/API failure must not trigger a second application deployment.
- Because the notifier runs on the same VPS, total VPS outage requires an
  external monitor or laptop fallback to report out of band.
- Tokens pasted into chat are test-only compromised credentials and must be
  rotated before production.

### Unified bot identity v2 (2026-08-27)

- Telegram and Bale share one authentication state machine backed only by the
  website identity store. Manual/name claims are disabled at both UI and API.
- The owner can inspect and delete an existing mapping with an audited reason,
  but cannot manually create, approve, or replace a mapping. Relinking is always
  completed by that student through canonical OTP or the single-use site login.
- Primary-class users link by either a short-lived OTP sent to the verified,
  OTP-enabled phone of their canonical `dentistry-1402` account, or the
  ten-minute website-login challenge. Both paths enforce the permanent
  one-to-one mapping conflict guard. Passwords remain website-only.
- Pending legacy claims are rejected by one audited migration. Old approvals
  are retained as migrated history only when the permanent mapping already
  exists; the migration never manufactures a link from a name claim.
- Every bot option fails closed until one approved entry route is complete.
  A verified generic Contact/OTP profile may enter the general menu and purchase
  an eligible bot-owned product, but cannot open personal website data, owner
  financial reports or administrative data. Those private surfaces still require
  `account.linked=true`; a class profile never falls back to generic access after
  its canonical mapping is disconnected.
- This gate includes the configured owner; numeric bot ownership alone never
  substitutes for a live canonical website link.
- Missing account-verification capability, a failed account check, canceling
  class authentication, an unknown callback, or returning to `/menu` must all
  remain fail-closed. Cancel only returns to the public gateway and never
  changes authorization state.
- Telegram structured reports use Bot API native Rich Messages with `is_rtl`,
  real heading/table/details blocks and a normal-message fallback. All visible
  digits are Persianized at the shared transport boundary without modifying
  URLs, callback data, tokens, or other machine identifiers. Bale preserves
  the same semantics with its Markdown fallback.
- Public medical-center admission is stored as one of six combined
  semester/type modes. Islamic Azad units store only first or second semester
  and skip the public course-type step. Class profiles
  are marked 1402 Dentistry, Tehran University of Medical Sciences, first
  semester, regular/commitment. Account renders the complete profile read-only.
  Proposed edits are encrypted requests and apply only after owner approval.
- Telegram and Bale persist per-user reply-keyboard state. Any transition from
  a normal-keyboard workflow to an inline/plain screen removes the client-side
  keyboard before rendering the destination. Unknown legacy state is cleaned
  once, successful cleanup survives service restarts, and transport failure
  leaves the transition blocked and retryable.

### Website bot-connection lifecycle v1 (2026-08-27)

- Account has one authenticated `اتصال به ربات‌ها` surface backed by the
  website bot identity store; Telegram and Bale status are independent and the
  browser never infers a connection from local state.
- The same website user may read only their own platform mapping. The response
  includes the canonical website name, encrypted-at-rest numeric platform ID,
  link time and the real platform display name/username when it was captured;
  legacy links with no captured profile are labelled as such rather than guessed.
- A website disconnect is a CSRF-protected, platform-scoped mutation. Under one
  store lock it creates a durable same-platform delivery, removes the permanent
  identity link and appends an audit event. It never removes the other platform.
- Telegram and Bale workers claim and acknowledge these deliveries through the
  signed service contract. They persist a local delivery receipt before ACK so
  an ACK timeout cannot send the security notice twice. The notice uses the
  shared Tehran Solar Hijri formatter and offers the normal secure relink path.
- Service-HMAC claim/ack for this queue intentionally does not depend on the old
  user mapping: the final disconnect notice must remain deliverable after that
  mapping has already been removed. No personal Telegram session is involved.

## 14. Decisions still required

- VPS IP, SSH username, and verified host fingerprint.
- A server-side host reachable by Bale for the already shared bot adapter.
- The exact authorized Telegram account workflows and channel ownership map.
- The canonical mapping between channels, courses, semesters, cohorts, and
  website resources.
- Staging hostname and DNS cutover plan.
- Which current uncommitted changes from `../dentistry1402tums` are intended
  source and which are generated or obsolete.

## 15. Immediate implementation order

1. Create a read-only inventory and recoverable snapshot of the old repository.
2. Recover full Git history and establish a clean integration baseline without
   losing the dirty worktree.
3. Record non-secret VPS access metadata locally and establish SSH-key access.
4. Harden the VPS and verify Telegram/Bale API connectivity.
5. Keep the implemented laptop backup/restore pipeline scheduled and verify a
   real restore at least monthly.
6. Deploy the existing website to staging without DNS cutover.
7. Add shared bot integration boundaries and minimal webhook health tests.
8. Protected booklet discovery, secure-raster personalization, forensic attribution and cached Telegram delivery are active in Dent1402Bot and covered by restore-verified laptop snapshots.
9. Continue real owner/mobile delivery checks and measured queue acceptance before expanding permissions.
10. Perform every production cutover with a fresh backup and rollback evidence.
