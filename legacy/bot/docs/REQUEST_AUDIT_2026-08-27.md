# User-request audit — 2026-08-27

This is an evidence-oriented checkpoint. “Implemented” means code plus the
verification recorded in `ops/SERVER_STATE.md`; a design document or disabled
capability flag is not counted as production completion.

## Active and verified

- ParsPack Iran is the only active VPS. Telegram uses an isolated loopback Xray
  route; Bale, SSH, backups and website traffic use the normal Iran route. Node
  refresh probes Telegram every four hours and retains the last working node on
  failure.
- Telegram and Bale run the same application/permission/workflow code. Bale has
  its tested Markdown fallback; Telegram grade and owner Navid reports use native
  Bot API Rich Messages and real tables. Visible bot digits are Persianized at
  the transport boundary without changing URLs or callback data.
- New-user intake has the two requested entry choices, full generic profile,
  Contact-last phone verification, confidential-phone copy, site OTP, Azad and
  public university modes, back/cancel controls, and shared Telegram/Bale state.
- Class authentication has only canonical-site OTP and the single-use
  authenticated website-login link. Manual/name/group approval and manual
  mapping creation are retired. Existing mapping inspection and reasoned deletion
  remain owner-only.
- Account profile is read-only. Proposed edits become pending website records and
  apply only after owner approval with stale-write protection.
- Website account exposes Telegram/Bale connection status and platform-scoped
  disconnect. The same-platform bot sends the durable disconnect notice.
- Bot payment offers use one shared Telegram/Bale SQLite store. The website grade
  store remains the only grade source. Deployment lifecycle notices are delivered
  independently to Telegram, Bale and the owner website center.
- Critical VPS state is pulled to versioned DPAPI-encrypted laptop snapshots and
  restore-verified. This 2026-08-27 audit described the then-active Reader;
  that subsystem was retired on 2026-08-28. Current backups contain both bot
  states, booklet issuance/trace metadata, shared commerce, notifier state and
  essential runtime environments; source PDFs stay in Telegram and rebuildable
  code/packages/caches are excluded.

## Hardened in this cleanup

- No message, command, callback, CAPTCHA reply or persisted non-auth dialog can
  execute before an approved entry route is complete.
- Generic Contact/OTP completion opens only the general menu. Personal,
  financial and administrative actions still require a live canonical website
  mapping. A disconnected class profile cannot downgrade itself to generic access.
- `/verify`, `/menu`, direct commands, forged callbacks, old message buttons and
  the configured owner all obey the same fail-closed rules.
- Telegram additionally checks live membership in `@Dent1402Booklets` before
  every private message or callback. Nonmembers receive only join/recheck
  controls, leaving the channel blocks the next interaction, and lookup failure
  fails closed. Production verified that the bot is a channel administrator.
  Bale does not simulate this cross-platform proof because a Bale identity
  cannot be securely equated with a Telegram account.
- A production audit found that the Telegram deploy script had moved the active
  symlink without restarting the existing poller, so earlier health claims were
  checking new files while users still reached an old in-memory release. The
  corrected deployment restarts and verifies the real process release, rolls
  back safely on failure, and never overwrites live bot state during a routine
  code activation. Production now confirms 106 institutions including 31 Azad
  units, the Tehran Azad keyboard entry, Persian digits at the transport
  boundary, and the actual owner `/start` gate for the current canonical-auth
  state.
- Membership recheck now has explicit success/failure feedback instead of a
  silent callback. Repeated `/start`, a 30-minute dialog expiry, concurrent
  same-user updates and obsolete step names can no longer reset an intake:
  progress resumes for 24 hours, mutations are serialized per user, and invalid
  step metadata is recovered without dropping already submitted fields.
- `linked=true` is no longer an entitlement. A durable
  `bot-canonical-auth-v1` proof is required independently by both bot gates and
  the signed website dispatcher. Legacy/approved mappings stay recoverable but
  must complete OTP or authenticated site login before any class/private menu,
  command, callback, dialog or owner action can run.
- Historical identity buttons remain explicit no-ops for safe compatibility;
  obsolete identity-review/inventory scripts and generated artifacts are removed.

## Implemented in shared source but not active in production

- Same-platform bot payment return and verified-success delivery exist behind
  disabled capability flags. Website callback/provenance/claim/ACK acceptance is
  still required; the current provider callback returns to the website result.
- Student-assistant CAPTCHA continuation is implemented as private, one-use,
  exact-reply, user-in-the-loop handling. Production remains disabled because
  encrypted student credential setup, real Navid/Food/Saba workers and verified
  upstream receipts do not exist yet.
- Navid class-group screenshot claim/send/ACK code exists, but the canonical
  scheduler, screenshot generation and exact group destination are disabled.
- Exam bot contracts/renderers exist, but production exam capability is disabled
  pending canonical central attempts, payment enrollment and result APIs.

## Not completed and must not be presented as working

- Personal assignment reminders, food reservation, service reservation, direct
  Navid upload, upstream “reserved/submitted” verification and their schedules.
- Automatic CAPTCHA solving or bypass. The accepted replacement is the private
  one-time student reply flow above.
- Full question-by-question website exam participation and purchase inside both
  bots.
- Per-user PDF watermarking and protected-file delivery directly from both bots,
  and Telegram protected-content/no-forward delivery for those files.
- Automatic Navid assignment screenshots to the class group at new/one-week/
  one-day milestones.
- Archive/reference worker migration and long-running production service.
- External health monitoring capable of reporting a total Iran VPS outage.
- Real Telegram PDF delivery acceptance and measured multi-user queue capacity
  for recipient fingerprinting.

## Website-owner handoff still open

The integration workspace is not allowed to edit the separate dirty website
worktree. The remaining dead internal manual-identity functions and fixture
cleanup are specified in `SITE_IDENTITY_AUTH_V2_CLEANUP_HANDOFF.md`. Their public
dispatchers must remain as `410 MANUAL_IDENTITY_DISABLED` denial stubs so old
clients fail safely.
