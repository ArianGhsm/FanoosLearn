# Website identity-auth v2 cleanup handoff

This handoff is for the task that owns `../dentistry1402tums`. The integration
workspace must not edit that dirty website worktree.

## Required behavior (no product change)

- Keep the service dispatchers for `submitIdentityClaim`,
  `importIdentityCandidates`, `identityClaims`, `resolveIdentityClaim`, and
  `setIdentityMapping`, but make every one return HTTP `410` with
  `MANUAL_IDENTITY_DISABLED`. Old clients/buttons must fail safely.
- Remove the now-unreachable implementations in `public_html/api/bot_store.php`:
  `dent_bot_import_identity_candidates`, `dent_bot_submit_identity_claim`,
  `dent_bot_identity_claims`, `dent_bot_resolve_identity_claim`, and
  `dent_bot_set_identity_mapping`.
- Do not delete historical `identityClaims` / `identityCandidates` records from
  production storage. They remain encrypted migration/audit evidence and must
  never authorize a request or supply a current public identity/profile value.
- Remove legacy claim/candidate fallback from current account/public-identity
  responses. Current authorization is derived only from a live permanent
  one-to-one mapping created by canonical-site OTP or a single-use authenticated
  website link.
- Update `scripts/setup_bot_api_http_fixture.php` to seed the minimum legacy
  encrypted records directly inside the isolated fixture instead of calling the
  deleted production functions. This fixture exists only to prove the v2
  normalizer rejects/retires historical records idempotently.

## Acceptance tests

1. Every retired action returns `410 MANUAL_IDENTITY_DISABLED` and performs no
   mutation.
2. A pending/approved legacy claim without an existing permanent mapping cannot
   unlock `account`, grades, notifications, payments, exams, or admin actions.
3. Running identity-auth v2 normalization twice produces zero second-run
   mutations and preserves all valid existing Telegram/Bale mappings.
4. Account linking through canonical OTP and single-use website login still
   passes conflict, replay, expiry, and one-to-one tests.
5. Production storage is mirrored to the laptop before deployment and the
   post-deploy mirror includes the unchanged historical audit records.
