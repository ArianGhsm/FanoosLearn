# VPS Operational State

Last verified: 2026-08-31

This file contains no credentials. Verify live state before changing the VPS;
do not infer that a planned subsystem is deployed merely because its design or
source code exists locally.

## Host

### Iran (sole Telegram, Bale and protected booklet runtime)

- Provider/server: ParsPack cloud `dfa2-9e96-a8b6-aaf1`
- Region: Tehran3 / THR-DC4
- OS: Ubuntu 24.04 LTS
- Capacity: 2 vCPU, 1.9 GiB RAM, 48 GiB usable root filesystem
- Swap: 2 GiB `/swapfile`
- Billing observed at purchase: `33,650` toman per day
- Key-only `dentops` administration is pinned through ignored
  `.codex-local/iran-server.json` and `.codex-local/iran_known_hosts`.
- UFW permits only OpenSSH and Nginx Full inbound; the Telegram proxy is
  loopback-only and has no public firewall opening. Fail2ban and unattended
  upgrades are active.
- Poppler 24.02, ImageMagick 6.9, SQLite 3.45, qpdf, Noto
  Arabic/Persian fonts, Python 3.12 and PHP 8.3 CLI with required extensions
  are installed. The Telegram release-local virtual environment contains
  PyMuPDF for the bounded PDF path; qpdf is the system validator. Measured
  pikepdf normalization is not installed in the production hot path because it
  added latency without reducing representative output size.

## Access and baseline

- Codex uses the dedicated local Ed25519 key recorded in ignored
  `.codex-local/iran-server.json`.
- The expected server Ed25519 host-key fingerprint is recorded in that ignored
  config and must remain pinned.
- SSH password and keyboard-interactive authentication are disabled.
- Root login is key-only (`PermitRootLogin prohibit-password`).
- UFW is active with inbound SSH only; fail2ban and unattended upgrades are
  active.
- Poppler 24.02, ImageMagick 6.9, SQLite, Python 3.12 and Noto Persian-capable
  fonts are installed.
- On 2026-08-29 all retired-host connection metadata, its dedicated SSH key and
  known-host entry, six encrypted snapshot pairs, and obsolete relay source and
  secret were removed from active paths. The remaining Iran snapshots were
  consolidated under `backups/vps-state-iran/`. Backup, repair and restore
  defaults now resolve only `.codex-local/iran-server.json`; the scheduled
  backup status contains only the required `iran` role.

## Deployment notifier

- Release `notifier-20260825012849` is installed under the isolated
  `/opt/integrated-dent/notifier/current` link on Iran. The one-minute flush
  timer is enabled and active, health reports `ready`, all three configured
  channels are ready, and the durable queue had zero pending events at the
  final checkpoint.
- Telegram delivery alone uses the loopback HTTP CONNECT proxy at
  `127.0.0.1:11080`. Bale and the signed owner-only website notification call
  use the normal Iran route. The root-owned notifier environment is mode
  `0600`; its secrets and identities are included only in encrypted VPS-state
  backups.
- Bootstrap lifecycle `notifier-bootstrap-20260825-013008` delivered both
  `started` and `succeeded` to Telegram, Bale and the website on the first
  attempt. Re-emitting the terminal event retained attempt count `1` on every
  channel, confirming spool idempotency.
- Canonical website lifecycle `website-20260825-013738` then passed the same
  real three-channel path around an actual one-file production API deploy:
  `started` was delivered before upload and `succeeded` after live `200` health
  checks. The legacy owner-login notice was skipped, so no third duplicate
  notification was created.
- The website host storage was mirrored back to the laptop after those final
  owner-only records at snapshot `20260825-013921`; the dry-run found zero code
  delta and performed no additional deploy or notification mutation.
- The signed website action accepts only allowlisted deployment services and
  states from the already-linked owner. It deduplicates by event ID, stores an
  owner-only canonical notification, and sets `disablePush` so Telegram/Bale
  notification workers cannot duplicate the notifier's direct sends.
- Canonical local deploy scripts for the website, Telegram bot and Bale bot now
  emit `started` and one terminal lifecycle event. The
  website requires the site channel to be delivered before its owner-notice
  completion guard can pass. Archive-worker runtime migration remains pending.
- Installing the notifier did not alter either bot unit. Telegram and Bale
  remained active with `NRestarts=0`; the proxy still listens only on
  `127.0.0.1:11080`.
- A notifier on the Iran VPS could not report total outage of the same VPS.
  External
  health monitoring remains pending.

## Dent1402Bot

### Recipient fingerprint v8 final secure raster (2026-08-31)

- Telegram release `telegram-20260831-081406` is active with `NRestarts=0` and
  the process CWD resolves to that exact release. Combined Telegram/Bale runtime,
  signed-site linkage, required-channel and private-source administration all
  passed. Its deployment lifecycle delivered one `started` and one `succeeded`
  event to Telegram, Bale and the website.
- `recipient-pdf-v8` keeps the v7 pixel burn-in and invalidates every earlier
  secure-raster cache key after the final visual-policy correction. Text, cover
  and sparse pages have 4 full B Nazanin Bold identity marks and 2 compact
  traces in the audited fixture. Image-heavy pages have 3 full identity marks
  in lower-salience regions and exactly one faint compact trace-only mark on the
  dominant image. No page has more than 5 full marks or 2 compact marks.
- Each final page is one burned-in image behind one content stream with no live
  text, annotation, attachment, Form/XObject watermark, retained original page
  or detachable security stream. Full canonical name, national code, mobile
  and Trace Code remain visible in pixels; the two HMAC/ECC constellations carry
  only an opaque page/issuance token. v1 through v7 detection remains available.
- The final repeatable attack suite passed with zero failures. Clean PDF,
  screenshot, JPEG Q60/Q75/Q90, 5% and 10% crop, slight rotation, resize,
  grayscale, raster rebuild and simulated print/scan retained a valid exact
  token. Stronger destructive cases may remain candidate/inconclusive, and a
  decoy was never definitively attributed. Poppler extracted zero characters;
  raw bytes/metadata contained no PII or Trace Code.
- Final v8 mixed-page benchmarks for 10/50/150 pages measured 4.88/22.84/61.77
  seconds, about 109/107/102 MiB peak RSS and 4.70/22.65/48.98 MB output. The
  one-worker 20-request tests had zero failures and orphan directories: cache
  hit 0.043 s/31.54 MiB with no processing, same-document dedup 0.72 s/79.16
  MiB with one job, and 20 distinct jobs 10.55 s/97.91 MiB.
- A real owner v8 file was generated/uploaded protected once and the immediate
  second send reused its Telegram `file_id`. The downloaded cached live file
  passed qpdf, empty-pdftotext, one-image/one-stream and no-extractable-PII
  checks. Production runs one worker, queue size 48 and measured 39,476 KiB RSS.
- Local compile, 25 focused tests and the complete 191-test suite passed. The
  restore-verified v8 pre-release snapshot is
  `vps-state-20260831-081356.tar.gz.dpapi`; the post-release snapshot containing
  the real v8 issuance/cache state is `vps-state-20260831-081532.tar.gz.dpapi`.
  Both are DPAPI-encrypted on the laptop and no plaintext archive remains.

### Recipient fingerprint v7 interim secure raster (2026-08-31)

- Telegram release `telegram-20260831-075249` was the first live secure-raster
  checkpoint and was superseded by v8 after the image-heavy visual policy was
  tightened. Its combined runtime and source-channel checks passed, and its
  canonical lifecycle delivered one `started` and one `succeeded` event to
  Telegram, Bale and the website.
- `recipient-pdf-v7` removes the remaining clean-layer recovery path. Each
  personalized page is rendered one at a time, burns the visible recipient
  identity and two secret HMAC/ECC constellations into pixels, and is stored as
  one page image behind one content stream. There is no retained source page,
  live text, annotation, attachment, recipient Form/XObject or separable
  security stream. Full name, national code, mobile and Trace Code remain
  visibly burned in; the hidden payload contains only an opaque issuance token.
- B Nazanin Bold remains the Persian font. Page-aware placement uses 3–5 full
  identity blocks and 1–2 compact trace marks rather than the old ten-block
  overlay. An outlined anchor without an opaque backing keeps one full identity
  readable over high-frequency medical images. The final audited sample had
  ten images and ten streams for ten pages, zero extractable characters, and
  neither raw bytes nor metadata contained PII or the Trace Code. Poppler
  `pdftotext` returned an empty document.
- The detector definitively recovered a valid ECC channel from the clean PDF,
  screenshot, JPEG Q60/Q75/Q90, 5% and 10% crops, 4-degree rotation, resize,
  grayscale, qpdf-compatible rewrite/raster rebuild and simulated print/scan.
  A 20% crop, strong mobile perspective and destructive original-diff attacks
  remained inconclusive rather than being attributed. The decoy issuance was
  never definitively attributed.
- Four-page batch assembly and production qpdf validation keep the hot path
  bounded. Benchmarks for 10/50/150 mixed pages measured about 107/107/102 MiB
  peak RSS, 11.95/55.55/146.86 seconds and 4.67/22.59/48.85 MB output. A
  one-worker 20-request test had zero failures or orphan jobs: cache hit used
  31.64 MiB with no download/process/upload, same-document deduplication
  generated once at 90.52 MiB, and 20 distinct one-page jobs peaked at
  98.58 MiB.
- At that checkpoint production had one watermark worker, queue size 48, a 300-second deadline,
  qpdf and Poppler gates, and measured idle process RSS 39,340 KiB. A real
  protected owner issuance was generated and uploaded once; the second send
  reused the stored Telegram `file_id`. The downloaded cached live PDF passed
  qpdf, empty-pdftotext, one-image/one-stream and no-extractable-PII checks.
- Local compile, 25 focused tests and the complete 191-test suite passed. The
  restore-verified pre-release snapshot is
  `vps-state-20260831-075235.tar.gz.dpapi`; the post-release snapshot including
  the real v7 issuance/cache state is `vps-state-20260831-075437.tar.gz.dpapi`.
  Both are DPAPI-encrypted on the laptop and no plaintext archive remains.

### Recipient fingerprint v6 interleaving (2026-08-30)

- Telegram release `telegram-20260830-232250` is active with `NRestarts=0` and
  its process CWD resolves to the exact release. Health, signed site linkage,
  required-channel administration and private source-channel administration
  passed. Deployment lifecycle `started` and `succeeded` reached Telegram,
  Bale and the website.
- `recipient-pdf-v6` replaces the separable original-stream plus security-stream
  page layout with one coalesced `/Contents` stream per final page. Secret-keyed
  Hamming+CRC perturbations are redundantly embedded in existing text and
  vector/image operators. Detector compatibility remains for v1 through v5;
  full name, national code and mobile remain only in the visible B Nazanin Bold
  deterrent layer and the hidden payload remains an opaque issuance token.
- The deliberate source-guided attack retained all 296 source-shaped operator
  chunks and discarded recipient-only chunks; both v6 text and visual channels
  still recovered 120/120 symbols with valid CRC/ECC and attribution confidence
  `0.995`. pikepdf/libqpdf rewrite also retained attribution. Mixed eight-page
  generation changed from 376 to 8 page streams, 2.6113 to 2.4094 seconds,
  1.1709x to 1.1024x output/source size, and measured 73.94 MiB peak RSS.
- A 20-request one-worker load test completed 20 distinct issuances with zero
  failures and 80.06 MiB peak RSS. Same-user deduplication generated/uploaded
  once for 20 requests; the 20-item Telegram file-id cache path performed zero
  downloads, watermark jobs or uploads and completed in 0.145 seconds. No
  orphan job directory remained.
- A real owner issuance was generated on production, sent protected, then sent
  again from the stored Telegram `file_id` without regeneration. The downloaded
  live copy verified one interleaved recipient stream per page, full visible
  identity, B Nazanin Bold, one configured worker, queue size 48 and process RSS
  39,404 KiB. Local compile, 10 focused tests and the complete 189-test suite
  passed; the earlier unrelated visual-detector failure did not recur.
- Pre-release snapshot `vps-state-20260830-232235.tar.gz.dpapi` and final
  snapshot `vps-state-20260830-232800.tar.gz.dpapi` are DPAPI-encrypted on the
  laptop, checksummed, SQLite-verified and immediately restore-verified. No
  plaintext archive remains.

### Generic bot-commerce gate fix (2026-08-29)

- Website PWA/API release `20260829-153231`, Telegram release
  `telegram-20260829-153439` and Bale release `bale-20260829-153530` are active.
  Both bot services are enabled and active with zero restarts, and each process
  CWD resolves to its exact current release.
- A verified non-class Contact/OTP profile can open an eligible bot product,
  create checkout and read its own payment status without linking a website
  account. Only `createBotPayment`, `paymentStatus` and
  `paymentProductStatesV2` accept this narrow generic payer; grades, Navid,
  account data, owner financial reports and administrative actions still fail
  closed without canonical authentication.
- Generic orders use an opaque verified-phone HMAC payer key. The optional
  self-declared student number is never an order-authorization key. If that
  phone is later linked canonically, both keys are considered for continuity of
  purchase limits/history. A same-platform payment-result route stores the
  numeric platform ID encrypted and is not a website account link.
- Reply-keyboard cleanup no longer displays the stale authentication-success
  sentence. The transport sends the required `remove_keyboard` carrier and
  immediately deletes that carrier message; durable keyboard state still
  prevents repeated cleanup on later `/start` calls.
- The local shared suite passed all 187 tests. The isolated signed HTTP service
  test passed generic commerce, private-action denial, encrypted routing,
  idempotent checkout and owner controls. A deployed-release no-network probe
  passed the generic product deep-link/create path, and the combined live check
  returned `generic_purchase_gate=true` with both runtimes healthy.
- No real payment, product, user, message or production profile was created for
  acceptance. The post-lifecycle website storage mirror is `20260829-153940`.
  Pre-release VPS snapshot `vps-state-20260829-153130.tar.gz.dpapi` and final
  snapshot `vps-state-20260829-154022.tar.gz.dpapi` are DPAPI-encrypted on the
  laptop and passed immediate isolated restore verification. Website host
  deployment and live checks succeeded; GitHub sync remains pending because
  the configured remote returned `Repository not found`.

### Shared new-user onboarding v1 (2026-08-27)

- Website API files `api/bot_onboarding.php` and `api/bot_store.php` are live.
  Production health returned `200`, and a real signed request from the Iran
  runtime verified contract `bot-onboarding-v1`, all 75 catalog institutions,
  all 31 represented provinces and the owner status endpoint without sending an
  SMS.
- Telegram release `telegram-20260827-140319` and Bale release
  `bale-20260827-140342` run the same reply-keyboard state machine. Both services
  and the isolated Telegram egress passed health with zero restarts.
- `/start` for a new unlinked/unprofiled user shows generic entry first and the
  dedicated 1402 Tehran dentistry class route directly below it. Generic order
  is name, surname, major, province/university, entry term, course type,
  optional student number, final review, own Contact, then phone OTP.
- The website is the durable source. Profile/phone values are encrypted in
  `storage/integrations/bot_links.json`; cross-platform synchronization uses a
  verified-phone HMAC. Bot SQLite never persists raw phone numbers or OTPs, and
  generic onboarding grants no canonical website account or private access.
- Offline discovery passed 112 bot tests. Final end-to-end acceptance that
  actually sends and enters an SMS remains user-driven to avoid sending an
  unsolicited OTP during deployment.
- Identity-auth v2 is live in website API release `20260827-165652`, Telegram
  release `telegram-20260827-164430` and Bale release
  `bale-20260827-164456`. Manual/name-based approval is disabled; unlinked
  class members may use only canonical-site OTP or the single-use authenticated
  website link, and private bot screens are gated until a canonical link exists.
- The controlled migration rejected 4 pending legacy claims, retained and
  normalized all 30 already-approved canonical links with zero missing-account
  or conflict cases, retired 1 legacy candidate, and created 42 missing fixed
  class profiles across existing Telegram/Bale links. A second idempotency run
  reported zero mutations in every category.
- Account now exposes the complete submitted profile read-only. Proposed field
  changes are encrypted pending requests and are applied only after owner
  approval. The six canonical admission modes distinguish semester 1/2 across
  regular-or-commitment, tuition-paying and international admission.
- Identity-auth v2 hardening is active in Telegram release
  `telegram-20260827-193734` and Bale release `bale-20260827-193808`. The owner
  bypass is removed, all legacy manual-claim/mapping service actions return
  `410 MANUAL_IDENTITY_DISABLED`, and the owner mapping surface is inspect/delete
  only. Profile-edit review shows previous and proposed values and rejects a
  stale approval instead of overwriting a newer profile. The live no-PII audit
  reports zero pending manual claims on both platforms while preserving 43
  Telegram mappings and 1 Bale mapping. Combined runtime, unlinked-private-gate
  and retired-endpoint smoke checks passed.

### Native Telegram rich reports and fail-closed access (2026-08-27)

- Telegram release `telegram-20260827-194716` fixes the real entry path into
  native reports: a callback from a regular menu now sends a fresh
  `sendRichMessage`, while refreshes from an existing rich message still edit
  in place. A production owner-grade probe returned `rich_message=true` and no
  regular `text`, proving the Bot API accepted the actual table payload. Bale
  release `bale-20260827-194742` keeps shared-code parity and its Markdown
  fallback.
- Telegram release `telegram-20260827-172840` uses Bot API native Rich Messages
  for grade and Navid reports. Grade rows are rendered by `sendRichMessage` as
  a real RTL `<table bordered striped compact>` rather than a monospaced text
  approximation or an external web view. Native send and edit both passed a
  live owner-only smoke, and the owner's real grade report was then delivered
  once through `Dent1402Bot` with the native table.
- Bale release `bale-20260827-172906` uses the same report data, actions and
  Persian wording but renders structured Markdown cards because Bale's current
  Bot API has no verified equivalent of Telegram Rich Messages. A live
  owner-only grade delivery completed without an API error and contained no
  unsupported literal table tag.
- Visible digits are Persianized centrally at both bot transport boundaries,
  including message text, captions, callback answers, button labels and input
  placeholders. URLs, callback data and opaque machine identifiers remain
  byte-for-byte unchanged.
- Private navigation now fails closed. The retired class-claim route, cancel,
  `/menu`, arbitrary text and a forged home callback cannot enter a private
  screen until the canonical website account check succeeds; a missing site API
  also denies access. The shared Telegram/Bale regression suite passed all 116
  tests before activation.
- Deploy lifecycle `started` and `succeeded` events reached Telegram, Bale and
  the website for both bot activations. Both services and the shared runtime
  check were healthy after activation.

### Strict entry/auth gate and legacy cleanup (2026-08-27)

- Telegram release `telegram-20260827-201555` and Bale release
  `bale-20260827-201619` run the shared two-level authorization gate. No command,
  callback, CAPTCHA reply or persisted private dialog can execute until one
  approved intake route is complete. Generic Contact/OTP verification opens only
  the general menu; personal, financial and owner actions still require a live
  canonical website mapping. A disconnected class profile cannot downgrade to
  generic access, and the configured owner has no bypass.
- The 123-test local suite covers unknown, partial, verified-generic,
  disconnected-class, linked and owner cases on both adapters. A no-network
  matrix smoke executed from each installed release and returned
  `DEPLOYED_AUTH_MATRIX_OK`; the combined runtime check also passed with both
  services active.
- Retired group/name identity inventory, personal-session review, automatic
  owner-link and stopped manual-review scripts were removed. Old callback names
  remain explicit mutation-free denial paths so buttons in historical messages
  fail safely. Generated preview/test/cache directories were removed locally.
- Pre-release snapshots `vps-state-20260827-200657.tar.gz.dpapi` and
  `vps-state-20260827-201328.tar.gz.dpapi`, plus final snapshot
  `vps-state-20260827-201654.tar.gz.dpapi`, are DPAPI-encrypted on the laptop and
  passed checksum, JSON, SQLite and isolated restore verification. No plaintext
  archive remains. One initial Telegram deploy command supplied `latest` as a
  literal path and failed before upload/activation; its `failed` lifecycle was
  delivered, then the corrected deployment succeeded normally.

### Onboarding reliability, back navigation and Azad catalog (2026-08-27)

- Website contract deployment `website-20260827-181556` now returns 106 institutions: 75
  public medical centers and 31 explicitly marked Islamic Azad units from the
  1402 national-admissions annex. Public admission retains the six combined
  semester/type modes; Azad admission accepts only first or second semester and
  the bots skip the public course-type screen in both directions.
- Telegram release `telegram-20260827-181757` and Bale release
  `bale-20260827-181821` expose `مرحله قبل` beside cancel on every onboarding
  screen, preserve non-secret progress, add the explicit Contact confidentiality
  notice and use less text-only copy with semantic emoji and step counters.
- The vague academic-profile failure was split into field-specific server
  validation. Legacy values such as `نیمسال اول (روزانه)` normalize to the
  canonical regular/commitment value, while an Azad/public-mode mismatch fails
  with `INVALID_ADMISSION_TYPE` before any OTP is issued.
- The first-interaction loss was traced in production logs to `SSLEOFError`
  inside `HTTPSConnection.request()` on an idle pooled socket. Transport now
  retries exactly once on a fresh connection only while the original HTTP
  request is incomplete; post-send response failures remain non-retryable to
  avoid duplicate messages.
- The shared suite passed 119 tests. A deployed-code smoke for both Telegram and
  Bale verified one immediate `/start` response, Azad term-only routing, back
  navigation and privacy copy without sending to a real user. Signed live site
  probes verified all 106/31 catalog counts, accepted the legacy alias up to
  phone validation, and rejected an invalid Azad mode. Runtime health then
  passed with zero service restarts.

### Separate Bale identity onboarding (2026-08-25)

- Shared application releases `telegram-20260825-025915` and
  `bale-20260825-025950` add `/verify` and platform-aware secure-link, claim,
  owner-review and mapping copy. Bale identity screens no longer describe Bale
  accounts or numeric identifiers as Telegram.
- The canonical website already namespaces identity hashes and one-to-one
  conflicts by `telegram` versus `bale`; one student may therefore link one
  account on each platform without either mapping replacing the other.
- Native command-profile refresh verified `/verify` on both live Bot APIs. Both
  services and the signed website API passed health; the configured owner Bale
  mapping remains linked.
- Final acceptance with a genuinely unlinked student is pending. Students use
  `/verify` -> `اتصال امن از سایت`, confirm a ten-minute one-time Bale challenge
  in their authenticated website session, then press `بررسی اتصال`. No website
  credential or OTP is entered in Bale.

### Navid Rich Text and class-group preview (2026-08-25)

- Shared Telegram/Bale release `telegram-20260825-025004` /
  `bale-20260825-025026` renders the owner Navid assignment list as a bounded
  Rich Text table with an expandable nearest-assignment card.
- Both live services passed health with `NRestarts=0`. Deployment lifecycle
  `started` and `succeeded` events were delivered independently to Telegram,
  Bale and the owner website center.
- A cropped screenshot of the latest prosthesis assignment card was sent once
  through Telegram Bot API to the configured owner private chat as a preview.
  No personal Telegram account and no class group were used.
- The shared class-group claim/send/ack adapter supports `new`, `week` and
  `day` event types but remains disabled on both runtimes. The required
  canonical website scheduler/screenshot actions and exact group destination
  have not been activated; historical assignments will not be backfilled.

### Current Iran Telegram runtime

- `integrated-dent-bot.service` release `telegram-20260827-211856` is enabled and active
  on Iran as unprivileged user `dentbot`, with zero restarts at verification.
- Every private Telegram message and callback is now checked live for membership
  in `@Dent1402Booklets` before `/start`, onboarding, authentication, menu,
  commands, callbacks or owner actions can run. The check is not positively
  cached, unknown/API-failure states fail closed, and the blocked screen contains
  only the channel join link plus an explicit recheck action. The production bot
  is a channel administrator; its own admin status and the owner's real channel
  membership passed the combined runtime check. The installed release also
  passed the nonmember/member gate probe before the normal canonical-auth matrix.
- Telegram configuration and SQLite state were migrated to Iran and then
  captured in Iran snapshot
  `vps-state-20260825-004535`, DPAPI-encrypted and restore-verified locally;
  it includes both proxy subscription environment and last working generated
  Xray configuration.
- Xray `26.3.27` was obtained from the official XTLS release and its SHA-256
  matched the published digest. `integrated-dent-telegram-egress.service` runs
  as `dentegress`, consumes about 6 MiB at the checkpoint, and exposes an HTTP
  CONNECT proxy only on `127.0.0.1:11080`.
- The owner subscription contained 28 supported nodes. A real Iran-host probe
  found 12 Telegram-reachable nodes during the final selection; the chosen
  VLESS node passed two additional consecutive API probes at about 487 ms.
  Node credentials and endpoints were not logged.
- `integrated-dent-telegram-egress-refresh.timer` is enabled every four hours at
  `00:15`, `04:15`, `08:15`, `12:15`, `16:15` and `20:15` Tehran, with up to
  ten minutes randomized delay and `Persistent=true`. Selection writes a new
  configuration only after success, so a failed refresh retains the last
  working node.
- On 2026-08-25 the Telegram egress was recovered from a restart loop caused by
  the shared `/etc/integrated-dent` parent having regressed to root-only mode
  `0700`; `dentegress` could not traverse it to read its valid group-readable
  configuration. The canonical parent mode is now root-owned `0711`, while
  each secret remains protected by its own `0600`/`0640` owner and service
  group. All installers and the restore path enforce this invariant. A forced
  subscription refresh selected a Telegram-reachable node successfully; Xray,
  the Telegram bot and the timer remained active with zero restarts, the
  loopback probe and bot health passed, SQLite integrity returned `ok`, and the
  Bale service remained active.
- Telegram `getMe`, the three Persian commands, signed website API, owner link,
  real send and edit, HTML formatting, webhook-empty polling mode and SQLite
  integrity all passed through the isolated proxy. The owner received the real
  `IRAN-EGRESS-SEND-EDIT-OK` smoke message.
- Bale, signed website calls, SSH and laptop backup keep the normal Iran default
  route. UFW has no proxy opening and the server default route remains on the
  ParsPack interface.
- On first activation, 62 unique durable notification receipts were processed
  from the downtime backlog. Receipt count increased with each batch, the last
  partial batch completed 1/1 with no failures, and then the queue stopped;
  this was not repeated delivery of the same IDs.

- `integrated-dent-bot.service` is enabled and runs as the unprivileged
  `dentbot` account using bounded Telegram long polling.
- Runtime SQLite state is private under `/var/lib/integrated-dent/dent-bot` and
  uses WAL mode.
- The bot token and owner Telegram ID are stored only in
  `/etc/integrated-dent/dent-bot.env`, mode `0600`.
- The Telegram owner mapping was corrected and re-verified on 2026-08-13 using
  the user-provided numeric Bot API identity. The numeric value remains only in
  the server environment; future work must read that configured value and must
  not infer ownership from a phone number, username, or Telegram CLI session.
- The corrected Telegram identity is the only Telegram link to the existing
  website owner account. Account, grades, bot checkout and notification-feed
  authorization checks passed, and a real owner-only confirmation message was
  delivered after the service restart.
- Bot commands, Persian descriptions, main navigation, owner-only management,
  and real Telegram delivery have been configured and verified.
- The website account-link and canonical grade APIs are deployed. The owner
  Telegram and Bale identities are linked to the existing website owner
  account, and signed `account` and `grades` requests were verified from a
  reachable client without exposing identity values.
- The Iran Telegram runtime reaches the signed website API directly; no relay
  runtime or relay secret is part of the active topology.
- BitNinja blocks POST from Cloudflare and Vercel but permits PUT from Vercel.
  The relay therefore accepts POST from the bot and forwards the unchanged body
  and HMAC headers as PUT to a TLS-protected API-only origin hostname. The site
  accepts POST and PUT only for the signed service action.
- The inactive Cloudflare Worker deployment was removed after the failed real
  test. Hosting support ticket `9858937` is still open with a clarified request
  to disable BitNinja for the account/domain or exempt the signed API path.
- Bot SQLite does not store or mirror grades. The website gradebook remains the
  sole authoritative source.
- Daily Navid code is deployed for both bots, but automatic scheduling is
  disabled on the migrated Telegram environment until exactly one coordinator
  is explicitly elected. Interactive `/navid` remains available on both.
- The previous purchase implementations mirrored website products and then
  website payment collections. Both were removed. Release
  `bots-20260825-015031` now keeps active offers in one group-restricted private
  SQLite store shared by Telegram and Bale; owner create/activate/deactivate
  actions from either adapter are immediately visible in the other. Platform
  polling/dialog/receipt databases remain separate. Final checkout sends a
  signed stored snapshot to the site,
  which creates a generic `bot-offer` order and reuses its gateway callback and
  verification. No live financial transaction was created during deployment.
- Telegram and Bale payment UI clients use the direct signed website API on the
  Iran VPS.
- The controlled migration found zero Telegram-local and one Bale-local offer,
  merged one unique record with no conflict, and created no order or financial
  transaction. Both live API health checks reported the same shared store ready,
  both services remained active with `NRestarts=0`, and SQLite integrity passed.
- The runtime now separates Telegram polling, bounded interactive update
  workers (default 8), and periodic site jobs. This removes the observed 12-second site
  timeout from the polling path. The release was deployed on 2026-08-14;
  systemd is active with the polling/background/update threads present. A real
  owner session displayed the new report-card screen. Payment listing and
  management no longer call the relay, while signed account/grade/checkout
  operations remain site-backed. Telegram API probes from the VPS measured
  roughly 0.04-0.24 seconds; no end-to-end user latency percentile is claimed.
- The shared Telegram/Bale notification feed, explicit-seen callbacks,
  owner-only audience view, and idempotent delivery worker exist locally and
  pass isolated unit/HTTP tests. The signed website endpoints are deployed and
  were verified for both linked owner identities. The current Telegram runtime
  reaches those endpoints directly from Iran. Its first activation drained the
  existing durable backlog as documented above; no synthetic student broadcast
  was created as part of the migration smoke.
- Shared-code release `bots-20260825-015031` on Telegram and Bale retains the
  compact notification center. It limits
  the keyboard to six recent items, combines refresh/settings controls, removes the redundant seen
  button after an item is opened, and bounds audience details to eight recent
  views. Pushed notifications retain their explicit seen action. It is deployed
  to both Telegram and Bale from the same shared application code.
- The 2026-08-14 interaction release replaced per-call Telegram TLS handshakes
  with a bounded persistent HTTPS connection pool. A real VPS probe after
  deployment completed 12/12 sequential Bot API requests with 0 failures,
  9.2 ms median, 13.0 ms p95 and 47.2 ms maximum. The signed website relay was
  separately measured around 0.4 seconds median. These are point-in-time probes,
  not an uptime or every-user latency guarantee.
- Callback acknowledgements are short and best-effort, repeated rapid callbacks
  from one user coalesce before site work, interactive and background site API
  circuit breakers are separate, and normal integration-store reads no longer
  rewrite the full JSON file.
- `/product` and `/payform` now launch the persisted interactive bot-only offer
  wizard. Offer list, detail, status, share, deletion confirmation and creation
  remain local; website checkout behavior is unchanged.
- The owner-only Navid center now shows current synchronization state, counts,
  assignments, literal Jalali dates and the daily captcha action. Telegram
  grade and Navid lists now use native RTL Rich Message tables; Bale uses the
  synchronized structured-card fallback documented above.
- The owner-authorized CLI account successfully read the exact 1402 class group
  (133 non-bot members) without sending messages. The current Telegram session
  exposes only one synchronized contact and only one deterministic unique-name
  candidate against the 121-row roster, so broad auto-linking was intentionally
  not fabricated. That candidate was imported through the signed owner API.
  This discovery/claim path is now retired and cannot create v2 links.
- Historical note: on 2026-08-25, an explicitly requested bulk review used the selected
  `source-account` Telethon session, the exact allowlisted class group and
  canonical website claims. Thirty deterministic claims were approved; one
  additional deterministic attempt was rejected by the site's conflict guard
  and three name-mismatched claims remained pending at that time. A first operational script
  mistakenly sent the thirty confirmations from the user session. That mutation
  path was removed immediately and a regression test now enforces read-only CLI
  review. Bot API then delivered 29 confirmation messages; one Bot API delivery
  failed. Identity-auth v2 subsequently terminated every remaining pending
  claim and normalized the approved canonical links. No conflict guard was bypassed.

## Bale adapter

- The Bale application uses the same commands, Persian screens, callback
  versioning, authorization boundary, and SQLite state model as Telegram.
- `integrated-dent-bale-bot.service` release `bale-20260827-211915` is active and
  enabled on the Iran VPS as the unprivileged `dentbale` user with two update
  workers. Telegram now runs beside it under a separate user and state path.
- Bale keeps the same application and canonical-auth gates, but does not claim
  to verify membership of a separate Telegram identity. Its health output marks
  the Telegram-channel prerequisite as `not-required` rather than reporting a
  fabricated pass.
- Real Iran checks passed for Bale TLS/API identity, SQLite state, the signed
  direct website API and the existing-site owner link. The service started with
  zero restarts and delivered/acknowledged two ten-item canonical notification
  batches without failure.
- Bale code is isolated under `/opt/integrated-dent/bale/current`; durable state
  is `/var/lib/integrated-dent/bale-bot` and the root-only environment is
  `/etc/integrated-dent/bale-bot.env`.
- The 2026-08-24 formatting fix converts the shared bounded Telegram HTML to
  Bale-native Markdown at the transport boundary and omits `parse_mode` for
  send, edit and photo captions. Bold, italic, inline code, preformatted text
  and HTTPS links retain their semantics; blockquotes use a stable `▎` visual
  fallback. A live owner-only multi-format smoke message passed; raw HTML tags
  must not appear in Bale output.
- Telegram/Bale feature parity is now an explicit release invariant in
  `AGENTS.md`, `PROJECT_LOGIC.md` and `docs/BOT_UX_SYSTEM.md`. Shared commands,
  grade/Navid keyboards and interactive Navid challenge availability have
  offline parity tests. Telegram live parity is now verified through the
  Iran-host isolated egress. The pre-release Iran snapshot
  `vps-state-20260824-175140` was encrypted and restore-verified on the laptop.
- The Navid scheduler implementation is also platform-neutral and can be
  elected through either Telegram or Bale environment settings. Production must
  enable it on exactly one runtime; the current Bale environment remains
  disabled until that coordinator choice is made explicitly.

## Protected booklet and retired Reader checkpoint

- The website Reader/viewer is retired. Website release `20260828-023336`
  removed its public account/admin UI, APIs and assets. `/account/devices/`
  remains as the canonical real-session/device page; the removed private API
  returns `404` in production. After its separate recovery backup was verified,
  the exact retired host path `storage/private_notes` was also removed; all
  other persistent website storage remained untouched. The post-removal laptop
  storage mirror is `20260828-072722` with 128 current files.
- Retirement event `integrated-ops-reader-retirement-20260828-072605` removed
  the exact Reader systemd units, Nginx/PHP configuration, environment, state,
  release directories and TLS certificate from Iran. Nginx validation passed,
  and Telegram and Bale stayed active afterwards. The pre-retirement VPS
  snapshot `vps-state-20260828-013520.tar.gz.dpapi` and the separate final
  website private-note backup
  `private-notes-production-20260828-013753.zip.dpapi` are both restore-verified
  on the laptop.
- Protected booklet delivery v4 is active in Telegram release
  `telegram-20260828-195110`; Bale remains release `bale-20260828-012931` and
  presents its documented transport-capability fallback. The Telegram process
  CWD resolves to the exact active release and services remained healthy after
  deployment. The deployment notifier delivered lifecycle events to Telegram,
  Bale and the website.
- The private management channel stays the source of truth. Historical source
  metadata was hydrated through a protected, silent, immediately deleted
  temporary copy; the server retains source routing IDs, issuance/trace records
  and reusable Telegram file IDs, not persistent source or personalized PDF
  bytes.
- The first real owner issuance passed the signed website identity contract,
  HMAC fingerprinting, visible and independent micro-mark embedding, qpdf
  validation, protected Telegram upload and isolated-temp cleanup. A second
  request reused the stored Telegram `file_id`; issuance count remained
  unchanged and no PDF processing reran.
- A live `recipient-pdf-v4` issuance exists in production and the hardening
  check confirms the current code path and embedded `B Nazanin Bold` font in the
  personalized file downloaded back from Telegram. Legacy v1/v2/v3 detector compatibility is
  covered offline. Existing `telegram_file_id` cache semantics remain atomic and
  unchanged: a cache hit performs no source download, watermark generation or
  upload.
- The v4 typography revision bundles `dent_bot/assets/fonts/B_Nazanin_Bold.ttf`
  in each Telegram release and points the root-only environment to that exact
  active-release path. The owner smoke generated and uploaded v4 once; the
  immediate second request reused its cached Telegram `file_id`.
- Production PDF work uses one worker and a 48-item bounded queue. The 1 GB
  benchmark on the actual 2-vCPU host also measured two workers, but one remains
  active to reserve service headroom. The vector/PDF-level delivery path uses
  PyMuPDF and qpdf. OpenCV and Ghostscript are installed only in the separate
  admin forensic environment and are not dependencies of normal delivery.
- Production v3 benchmark records are stored in `ops/benchmarks/`. The 8/40/80
  page runs completed in 0.99/4.63/14.89 seconds with 68.94/69.19/69.56 MiB
  peak RSS under a 1 GB limit. Twenty distinct 8-page requests completed in
  17.56 seconds with one worker; twenty 40-page requests completed in 96.26
  seconds. Both had zero failures and zero orphan job directories. Twenty cache
  hits completed in 59 ms total with no download or upload.
- The v3 regression corpus covers PDF object/stream attacks, qpdf and real
  Ghostscript rewrites, crop, rotate, resize, JPEG Q60/Q75/Q90, screenshot,
  simulated print-scan, mobile-photo perspective and original-diff attacks.
  Detailed results and honest residual limitations are in
  `ops/BOOKLET_FORENSIC_V3_2026-08-28.md`.
- Final acceptance passed 165 integration/unit tests, Python compile checks,
  visual PDF inspection and the live production invariant.
- The final post-v3 restore-verified laptop snapshot is
  `backups/vps-state-iran/vps-state-20260828-193903.tar.gz.dpapi`.
- The pre-v4 snapshot is
  `backups/vps-state-iran/vps-state-20260828-195055.tar.gz.dpapi`; the final
  post-v4 issuance/cache snapshot is
  `backups/vps-state-iran/vps-state-20260828-195524.tar.gz.dpapi`. Both are
  restore-verified on the laptop.
- Repeated-start cleanup hardening is active in Telegram release
  `telegram-20260828-200533` and Bale release `bale-20260828-200637`. The shared
  keyboard coordinator now checks its durable cleanup marker before an explicit
  `remove_keyboard`, so authenticated `/start`/`/menu` calls no longer repeat the
  «ورود کامل شد» transport message. All 168 local tests passed. An isolated
  live-release smoke sent three `/start` updates per platform after one simulated
  active auth keyboard and observed exactly one cleanup and no repeated success
  text on both platforms. Combined runtime, active-process CWD and booklet v4
  checks passed; lifecycle started/succeeded events reached Telegram, Bale and
  the website for both deployments. The restore-verified pre-release snapshot is
  `backups/vps-state-iran/vps-state-20260828-200510.tar.gz.dpapi`; the final
  post-deployment snapshot is
  `backups/vps-state-iran/vps-state-20260828-201023.tar.gz.dpapi` and is also
  restore-verified on the laptop.

## Recipient fingerprinting v5 checkpoint (2026-08-29)

- Telegram release `telegram-20260828-204453` is active with
  `recipient-pdf-v5`. Deployment lifecycle notifications were delivered to
  Telegram, Bale and the website. The bot process CWD resolves to that release,
  `NRestarts=0`, worker count is one, queue capacity is 48 and idle RSS is about
  34 MiB.
- Visible deterrence deliberately retains the full canonical name, full
  national code, full mobile and Trace Code. Only hidden channels exclude PII:
  they carry an opaque HMAC-derived issuance token.
- The live owner smoke generated one protected v5 PDF and the second request
  reused the same Telegram `file_id`. The downloaded live file passed B Nazanin
  Bold, direct v5 content-stream and full-visible-PII checks. No source or
  personalized PDF remained in server temporary storage.
- The v5 encoder has no watermark Form/XObject or annotation. It writes ten
  diversified direct recipient streams per page, distributes two micro
  channels across them and independently perturbs existing text/vector content
  with Hamming+CRC. Detector compatibility for v1-v4 remains available.
- The real 1 GiB server benchmark accepted all 20 concurrent requests with zero
  failure and zero orphan job directories. One worker peaked at 77.62 MiB for
  twenty distinct eight-page files and 78.93 MiB for twenty distinct 40-page
  files. Two workers peaked at 86.56 MiB but improved the small burst by only
  about nine percent, so production remains at one worker.
- Real qpdf and Ghostscript rewrites preserved a valid 120/120 text-content
  ECC/CRC channel. Removing all 80 suspected visible/micro streams also left
  that independent channel attributable. Screenshot/JPEG results were stronger;
  print-scan/mobile-photo remained probabilistic candidates and destructive
  original-diff remained inconclusive, never a definitive attribution.
- All 168 local tests passed. Poppler rendering of the final mixed fixture was
  inspected. Full evidence and benchmarks are recorded in
  `ops/BOOKLET_FORENSIC_V5_2026-08-29.md` and `ops/benchmarks/`.
- The restore-verified final laptop snapshot is
  `backups/vps-state-iran/vps-state-20260829-093812.tar.gz.dpapi`.

## Not deployed yet

- Telegram archive/reference worker migration or long-running archive worker
- Website application migration to this VPS
- `bot-payment-return-v1`: the shared Telegram/Bale deep-link handler,
  same-platform verified-success delivery worker and offline tests exist in the
  deployed shared source, but both production capability flags remain disabled.
  The website callback/provenance/claim/ACK work
  in `docs/SITE_BOT_PAYMENT_RETURN_HANDOFF_V1.md` must be deployed and accepted
  first; the current live callback still returns bot-offer purchases to the
  public website result page.
- `student-assistant-v1` private CAPTCHA continuation: shared Telegram/Bale
  source now binds a short-lived website challenge to the requesting user and
  exact reply message, stores no image/answer, and resumes the same job through
  a signed one-time action. It has offline parity/replay/expiry tests and the
  shared bot source is deployed, but the production capability remains disabled.
  The website-side summary, opaque
  action and one-use CAPTCHA-answer endpoints were deployed on 2026-08-25 and
  passed a real signed request from the Iran runtime; production returned the
  three connector states with zero executable actions and no test image. The
  deterministic connector remains test/development-only. Encrypted credential
  setup, real Navid/Food/Saba workers and verified upstream receipts remain the
  activation prerequisite documented in
  `docs/SITE_STUDENT_ASSISTANT_HANDOFF_V1.md`.

These items require implementation and real verification before their status
may be changed to active.

The only approved private-booklet architecture is documented in
`docs/PROTECTED_BOOKLET_DELIVERY.md`: discovery and protected personalized
delivery remain inside Dent1402Bot's existing `جزوات` flow. Do not recreate a
website viewer, Mini App, persistent source-PDF store or parallel identity
system.

## Laptop disaster-recovery backup

- `scripts/backup-vps-state.ps1` creates a consistent minimal snapshot using
  SQLite `.backup` for each platform runtime and the shared bot-commerce store,
  plus booklet issuance/trace state, notifier history and runtime environment
  files including the server-only fingerprint key.
- Current Iran snapshots are transferred to ignored
  `backups/vps-state-iran/`. Every new archive is DPAPI-encrypted,
  checksummed and immediately restore-tested. Python, packages, deployed code,
  caches, logs and temporary files are excluded.
- Source PDFs, personalized PDFs, watermark caches, release code, packages,
  logs and temporary files are excluded. The Telegram source channel remains
  the source of truth; the metadata and reusable file IDs needed to continue
  delivery are included.
- The payment-sync pre-migration snapshot `vps-state-20260825-015033` and
  post-migration snapshot `vps-state-20260825-015058` are DPAPI-encrypted on the
  laptop and passed checksum, JSON, every-SQLite integrity and isolated restore
  verification. The latter contains the shared payment-offer database. No
  plaintext archive remains.
- The previous post-release snapshot was `vps-state-20260825-013902`; it passed
  DPAPI encryption, checksum, JSON, SQLite and isolated restore verification on
  the laptop after the three-channel notifier activation and contains its
  root-only environment plus delivered spool history. The pre-change snapshot
  `vps-state-20260825-012315` also passed restore verification. The
  earlier post-activation snapshot `vps-state-20260824-195316` also passed
  checksum, JSON, SQLite and isolated restore verification with no plaintext
  archive retained.
- The first verified snapshot on 2026-08-13 contained two valid SQLite
  databases, 20 notifier records and three environment files; its compressed
  plaintext size before encryption was 6,307 bytes. Plaintext was removed.
- Local extraction and integrity verification passed with 33 restored files.
- Final post-retirement snapshot
  `vps-state-20260828-072656.tar.gz.dpapi` is DPAPI-encrypted, checksummed and
  restore-verified on the laptop. Its manifest includes bot SQLite with booklet
  issuances, shared commerce state when present, notifier history, runtime
  environments including the fingerprint key, Telegram egress configuration
  and service state. The plaintext archive size before encryption was 44,147
  bytes; no plaintext archive remains.
- The project content now lives under the correctly named
  `IntegratedDent1402Tums` directory. The old misspelled directory is empty and
  awaits removal after Codex Desktop releases its Windows directory handle.
- Windows task `IntegratedDent-VPS-State-Backup` is installed with daily 20:00
  and login triggers, StartWhenAvailable, battery operation and a 20-minute
  execution bound. Its runner backs up only the required Iran role.
- Encrypted snapshots are retained under the ignored laptop backup roots; no
  plaintext archive is kept. The post-release snapshot
  `vps-state-20260814-044633` passed checksum,
  JSON, SQLite, DPAPI decryption and local restore verification. A monthly real
  restore drill onto a disposable server remains required.
- A post-relay encrypted snapshot was created and restore-verified on
  2026-08-14 after the Vercel URL and Navid activation were installed.
- Iran Bale state and its single required runtime environment were captured in
  `backups/vps-state-iran/` on 2026-08-24. The DPAPI-encrypted snapshot passed
  archive checksum, SQLite integrity and an independent restore verification;
  no plaintext archive was retained.
- The post-egress-repair Iran snapshot
  `vps-state-20260825-170007.tar.gz.dpapi` is DPAPI-encrypted on the laptop and
  passed immediate isolated restore verification. The pre-repair snapshot
  `vps-state-20260825-165241.tar.gz.dpapi` is retained for rollback/audit; no
  plaintext archive remains.
- Onboarding deployment snapshots `vps-state-20260827-135905.tar.gz.dpapi`
  (pre-release) and `vps-state-20260827-140513.tar.gz.dpapi` (post-release) are
  DPAPI-encrypted on the laptop and both passed immediate isolated restore
  verification. The final website host-storage mirror is snapshot
  `20260827-140954` with 499 persistent files; it includes the canonical
  integration store after the live signed onboarding smoke and is schema v3
  with empty
  onboarding collections before first real student acceptance. No plaintext
  VPS archive remains.
- Identity-auth v2 used the restore-verified pre-release snapshots
  `vps-state-20260827-144730.tar.gz.dpapi` and
  `vps-state-20260827-164351.tar.gz.dpapi`. The final post-release snapshot
  `vps-state-20260827-170016.tar.gz.dpapi` is DPAPI-encrypted, checksummed and
  restore-verified on the laptop. The final website persistent-storage mirror
  is snapshot `20260827-165822` with 499 files and includes the completed
  identity/profile migration.
- Native-rich/fail-closed bot deployment used restore-verified Iran snapshot
  `vps-state-20260827-172826.tar.gz.dpapi` before activation. After both live
  owner-only report checks, snapshot
  `vps-state-20260827-173409.tar.gz.dpapi` was DPAPI-encrypted, checksummed and
  immediately restore-verified on the laptop; no plaintext archive remains.
- Onboarding reliability/Azad deployment used restore-verified Iran snapshot
  `vps-state-20260827-181343.tar.gz.dpapi` before activation. Final website
  storage mirror `20260827-182039` contains 499 persistent files after the
  lifecycle notices. Post-deploy VPS snapshot
  `vps-state-20260827-182154.tar.gz.dpapi` is DPAPI-encrypted, checksummed and
  immediately restore-verified on the laptop; no plaintext archive remains.
- Website-managed bot connections are live for Telegram and Bale. The account
  page reads canonical one-to-one mappings from website storage, exposes each
  platform independently, and uses an authenticated CSRF-protected disconnect
  action. A disconnect atomically removes the mapping and queues one durable
  same-platform notice that remains claimable after unlinking; bot-local
  receipts make delivery idempotent. The website release is
  `20260827-185803`, Telegram release `telegram-20260827-185047`, and Bale
  release `bale-20260827-185110`. Live authenticated status, mobile/desktop
  rendering, both bot health checks and the combined runtime check passed; no
  production connection was disconnected during verification. Pre-release
  snapshot `vps-state-20260827-184807.tar.gz.dpapi` and post-release snapshot
  `vps-state-20260827-190335.tar.gz.dpapi` are DPAPI-encrypted, checksummed and
  immediately restore-verified on the laptop. Final website storage mirror
  `20260827-190418` contains 499 persistent files and was captured after the
  final lifecycle records were written. GitHub sync is operationally pending
  because the configured private remote rejected the only locally
  authenticated GitHub account; host deployment and three-channel lifecycle
  delivery were not rolled back.
- The identity-auth v2 hardening used restore-verified VPS snapshots
  `vps-state-20260827-193718.tar.gz.dpapi` before bot activation and
  `vps-state-20260827-193940.tar.gz.dpapi` after final live verification. The
  final website storage mirror is `20260827-194005` with 499 persistent files.
  All copies are laptop-local; the VPS archives were checksum/SQLite verified
  and no plaintext archive remains. Website host deploy and live verification
  succeeded, but GitHub sync remains pending because the configured private
  remote still returns `Repository not found` for the locally authenticated
  account.
- Canonical-auth gate release `telegram-20260827-203226` and
  `bale-20260827-204041` is active. The live website service now distinguishes
  durable `bot-canonical-auth-v1` completion from mere link existence. Legacy,
  migrated and formerly approved mappings remain one-to-one records but return
  `authComplete=false`; every user/private service action returns
  `ACCOUNT_AUTH_REQUIRED` until class OTP or authenticated site-login completes
  the proof. Both installed releases passed the same four-state auth matrix
  (`unknown`, verified generic, class-unlinked, legacy-linked) and the combined
  runtime health check. Pre-release Iran snapshot
  `vps-state-20260827-202808.tar.gz.dpapi` was DPAPI-encrypted and
  restore-verified. Final post-release snapshot
  `vps-state-20260827-204413.tar.gz.dpapi` was also DPAPI-encrypted,
  checksummed and restore-verified on the laptop. Website storage mirrors `20260827-203317` and
  `20260827-203737` each contain 499 persistent files. The bootstrap lifecycle
  terminal event reached Telegram, Bale and the website after the system-only
  deploy-notice action was kept independent of interactive owner reauthentication.
  Website GitHub sync remains pending because the configured remote still
  returns `Repository not found`; the live host delta and health verification
  succeeded.
- Bale release `bale-20260827-205902` was activated correctly. Telegram release
  `telegram-20260827-205844` changed the `current` symlink but did not restart
  the already-active systemd process: the actual poller remained in release
  `telegram-20260827-140319`. Its standalone health therefore produced a false
  success while users still saw the old menus, Latin digits and incomplete
  onboarding. Corrective release `telegram-20260827-210735` restarts the real
  process, verifies that `/proc/<MainPID>/cwd` equals the active release, and
  rolls back/restarts the previous release on failure. Routine Telegram code
  deployment now preserves the live SQLite/environment instead of restoring
  mutable state over it. All 131 local tests, both installed auth matrices,
  the 106-institution/31-Azad production catalog check, Tehran Azad keyboard
  check, Persian-digit transport check, live bot/channel-admin check, current
  owner start-gate check, service health and signed website health passed.
  Deployment lifecycle `started`/`succeeded` events reached Telegram, Bale and
  the website. Restore-verified snapshots
  `vps-state-20260827-205530.tar.gz.dpapi` (pre-release) and
  `vps-state-20260827-205949.tar.gz.dpapi` (post-release) are encrypted on the
  laptop; the latter contains 1,532,609 plaintext archive bytes before DPAPI
  wrapping. Corrective pre/post snapshots
  `vps-state-20260827-210722.tar.gz.dpapi` and
  `vps-state-20260827-211217.tar.gz.dpapi` also passed DPAPI encryption,
  checksum, JSON, SQLite and isolated restore verification; no plaintext
  snapshot remains.
- Interaction-stability releases `telegram-20260827-211856` and
  `bale-20260827-211915` are active. Telegram membership recheck now produces a
  visible alert for absent/unavailable membership, a success acknowledgement
  before normal gated navigation, and a normal-message fallback if callback
  acknowledgement fails. Both adapters serialize each user's state mutations;
  repeated `/start` resumes the current step, onboarding/class-auth state lasts
  24 hours, and obsolete steps recover without discarding their payload. All
  135 tests plus both installed-release matrices passed, including nonmember,
  accepted-member and mid-intake `/start` cases. Lifecycle events reached all
  three channels. Pre-release snapshot
  `vps-state-20260827-211843.tar.gz.dpapi` and final snapshot
  `vps-state-20260827-212022.tar.gz.dpapi` are DPAPI-encrypted, checksummed and
  immediately restore-verified on the laptop; no plaintext archive remains.
- Reply-keyboard cleanup releases `telegram-20260827-212833` and
  `bale-20260827-212901` are active. Successful generic OTP, class OTP,
  already-linked class OTP and website-login transitions now explicitly send
  Bot API `remove_keyboard` before the inline account/home UI. A one-time Bale
  owner cleanup was delivered by Dent1402Bot itself and acknowledged by Bale;
  no personal account session was used. All 140 local tests, both installed
  auth matrices, live bot health, signed site health and resolved
  `/proc/<MainPID>/cwd` checks passed. Routine and full Bale deployment plus
  disaster restore now restart their service and reject a process whose CWD is
  not the resolved active release; full deployment also preserves existing
  environment and SQLite state. The restore-verified pre-release laptop
  snapshot is `vps-state-20260827-212815.tar.gz.dpapi`; final snapshot
  `vps-state-20260827-213144.tar.gz.dpapi` is also DPAPI-encrypted,
  checksummed and immediately restore-verified with no plaintext archive kept.
- Protected-booklet releases `telegram-20260828-010430` and
  `bale-20260828-010631` are active. The old `جزوات` viewer/submission behavior
  is retired; the shared flow now uses reply-keyboard course, syllabus-session
  and resource selection. Only Telegram can copy the exact private source
  channel; Bale exposes the documented transport-capability fallback.
  Telegram health proved the exact source title and bot administrator role.
  Existing source message `4` was registered from its caption as one ENT term-7,
  session-4 booklet route without downloading bytes, then the bounded worker
  copied it successfully to the configured owner with `protect_content=true`.
  Its durable receipt is `sent` with a valid message ID. The VPS retains only
  routing metadata and receipts; no source PDF or temporary output was written.
  All 147 local tests and compile checks passed, including ordinal 1-40 parsing,
  dual booklet/reference routing, source-channel isolation, transport
  protection and reply-keyboard cleanup on every exit. Both release-process
  CWD invariants and lifecycle delivery to Telegram, Bale and the website
  passed. An initially unquoted shell value for the Persian source title was
  corrected before acceptance; the root-only environment remains `0600`.
  Restore-verified laptop snapshots
  `vps-state-20260828-010046.tar.gz.dpapi` (pre-release) and
  `vps-state-20260828-010736.tar.gz.dpapi` (post-acceptance) are DPAPI-encrypted,
  checksummed and SQLite-verified; no plaintext archive remains.
- Durable keyboard-invariant releases `telegram-20260828-011407` and
  `bale-20260828-011445` are active. The shared transport wrapper persists each
  user's reply-keyboard state and requires a successful `remove_keyboard`
  before any later non-reply screen; unknown state left by an older release is
  cleaned once and a failed cleanup blocks/retries the transition. The owner's
  stale keyboard was then cleared once by each bot, and both Bot APIs returned
  valid success responses while storing the new cleanup marker. Telegram's
  active process CWD resolves exactly to `telegram-20260828-011407`; Bale's CWD
  invariant and health passed inside deployment. All 149 local tests, compile
  checks and both live health paths passed. Pre-release snapshot
  `vps-state-20260828-011243.tar.gz.dpapi` and post-acceptance snapshot
  `vps-state-20260828-011635.tar.gz.dpapi` are DPAPI-encrypted, checksummed,
  SQLite-verified and restore-verified on the laptop.
- Entry-year onboarding releases `telegram-20260828-012842` and
  `bale-20260828-012931` are active. The shared intake now requires a normal
  reply-keyboard `entryYear` step immediately after institution and before
  entry semester, with exactly Persian years `۱۳۹۹` through `۱۴۰۵`; the signed
  website API independently normalizes and validates the same field. Existing
  in-progress dialogs recover at the missing year step without losing earlier
  values. The idempotent identity-v2 normalization ensured four existing class
  profiles carry the canonical `entryYear=۱۴۰۲`; it rejected or remapped no
  identity. All 150 local tests, both deployed authentication matrices, signed
  site health, exact catalog/year rendering, owner class-year assertion and
  active-release CWD checks passed. The scoped website endpoint deployment
  completed at `2026-08-28T01:26:26+03:30` with active PWA version
  `20260828-012606`. Restore-verified VPS snapshots
  `vps-state-20260828-012807.tar.gz.dpapi` (pre-release) and
  `vps-state-20260828-013240.tar.gz.dpapi` (post-acceptance) are DPAPI-encrypted
  on the laptop; final website storage mirror `20260828-013058` contains 499
  persistent files and includes the post-normalization identity store.
- A real scheduled-task run on 2026-08-24 completed with Windows result `0` and
  produced restore-verified Iran snapshot `vps-state-20260824-173544`, with no
  plaintext archive retained.
- Bot-commerce v2 production rollout completed on 2026-08-29. The website PWA
  release is `20260829-132909`, the common store/code migration release is
  `bots-20260829-133854`, Telegram is `telegram-20260829-134332`, and Bale is
  `bale-20260829-134503`. Both systemd services are enabled/active with
  `NRestarts=0`; active process CWDs, bot identity, channel administration,
  shared-store access and signed website health passed. Both result-push flags
  are `1`. Signed owner dashboard and transaction reads passed from each
  platform without creating a product, order, reminder or broadcast. Telegram
  Bot API currently reports `supports_inline_queries=false`; the deployed
  ordinary opaque-link/copy fallback remains usable until inline mode is
  enabled externally in BotFather.
- During the first shared-store activation attempt, a precondition discovered
  an already-existing shared database before the installer had captured its
  rollback files. The old rollback handler removed that database and the
  running services recreated an empty schema. Snapshot
  `vps-state-20260829-133130.tar.gz.dpapi` proved the pre-attempt shared store
  contained one offer; it was restored atomically under an exact `0 -> 1`
  count guard, both services were health-checked, and the plaintext extraction
  was deleted. The installer is now idempotent for an existing shared store and
  cannot touch it before a consistent SQLite rollback snapshot exists. Telegram
  and Bale deploy rollback now restores environment and systemd unit files as
  well as the release symlink. The final shared store has one offer, integrity
  `ok`, mode/group `0660:dentcommerce`.
- Restore-verified encrypted VPS snapshots for this rollout include
  `vps-state-20260829-132808`, `vps-state-20260829-133130`,
  `vps-state-20260829-133709`, `vps-state-20260829-133857`,
  `vps-state-20260829-133940` and final
  `vps-state-20260829-134736`. Website storage snapshot `20260829-132826` was
  mirrored to the laptop before its delta deployment. The host deployment and
  live 200 checks succeeded; GitHub sync remains pending because the configured
  remote returns `Repository not found`.
- A final read-only website storage mirror was captured as `20260829-135005`
  after all Telegram/Bale lifecycle events. It contains 128 persistent files;
  the DryRun reported zero uploads and zero deletes, left PWA version
  `20260829-132909` unchanged, and performed no production mutation.
- Behavioral Exam Reminder retirement completed on 2026-08-30. Website PWA
  release `20260830-131842`, Telegram release `telegram-20260830-131651`, and
  Bale release `bale-20260830-131743` are active. The shared notification store
  no longer generates incomplete/resume/flagged-question/inactivity reminders,
  while exam progress, flags, resume/history and all non-exam notification
  producers remain unchanged. The post-deploy storage mirror `20260830-132131`
  confirms schema version `6`, zero active exam-reminder records, `101` retired
  legacy notification IDs, zero pending retired deliveries, and two formerly
  pending deliveries terminally invalidated; historically delivered records
  remain non-retryable. Authenticated production login, repeated summary and
  repeated list calls returned no exam reminder and no `examReminders`
  preference. VPS snapshot `vps-state-20260830-131638.tar.gz.dpapi` passed
  DPAPI encryption, checksum, JSON/SQLite and isolated restore verification.
  All 19 focused retirement checks, 64 bot tests, website static/auth/exam
  suites, signed bot HTTP contract tests, live host health, active bot runtime
  checks and three-channel lifecycle delivery passed. The broader 189-test bot
  suite retained one pre-existing unrelated forensic visual-channel CRC failure;
  its focused rerun failed identically and no forensic code was changed.
- Recipient-fingerprint v9 targeted placement release
  `telegram-20260831-195639` is active on the Iran host. It preserves the
  secure-raster renderer, one-image/one-stream page structure, complete visible
  identity deterrent, hidden opaque HMAC page tokens, Telegram
  `protect_content`, one worker, queue size 48 and the per-recipient reusable
  `telegram_file_id` cache. Content-aware candidate scoring now keeps full
  marks away from headings and paragraph starts, while image-heavy pages place
  their smaller compact trace at a figure edge/corner rather than the central
  50% ROI. On the deterministic CT/MRI fixture, compact ROI overlap changed
  from `1.0000/0.9917` under retired-v8 placement to `0.0000/0.0000` under v9.
  The numeric expected-issuance verifier recovered 38 of 40 representative
  transformed pages; only the 10%-per-side CT and MRI crops remained
  deliberately inconclusive, and all decoys were rejected. The complete PDF
  recovered valid ECC on 9 of 10 pages at confidence `0.995`. All 192 local
  tests passed. A real owner issuance was generated by Dent1402Bot from the
  private source channel, delivered protected, then delivered again from the
  same cached file ID without regeneration. Live hardening confirmed v9,
  empty extracted PDF text, burned-in visible PII, one worker, queue 48,
  clean temporary storage, active process CWD and service health. Restore-
  verified encrypted laptop snapshots are
  `vps-state-20260831-195616.tar.gz.dpapi` before deployment and
  `vps-state-20260831-212510.tar.gz.dpapi` after production acceptance; no
  plaintext snapshot remains.
