# Stage 7 — Bot Migration / Implementation Report

## Baseline and ownership

Implementation baseline: `a2e99953b9c93d97a7302f24627563487c0e4ab1` after Stage 7 Platform contracts/control plane were merged. FANOOS is the only runtime authority. VoiceMatnAIBot and Dentistry1402TUMS were read-only behavior references only.

## Architecture delivered

- `packages/python/fanoos_bot`: presentation-neutral backend client, application flow, capability registry, callback codec, Unicode chunking, transport primitives and bounded local state.
- `apps/telegram-bot`: independent Telegram process/token/update offset and owner update UX.
- `apps/bale-bot`: independent Bale process/token/update offset with explicit capability downgrade.
- `apps/workers/notification-projector`: canonical outbox projection process.
- `apps/workers/protected-media`: bounded PDF processing engine, intentionally stopped before production claim while capability redemption is missing.

## Legacy protected-booklet reuse audit

Dentistry's current read-only implementation was re-inspected for the security properties worth retaining: private per-job temporary directories, per-recipient issuance, authorization before cached delivery, raster burn-in, opaque forensic material, bounded processing and cleanup. FANOOS does **not** copy its student identity fields, national code/phone watermark identity, local bot DB, cohort assumptions, Telegram-source storage, font asset, token/env or filesystem paths. FANOOS uses backend issuance-supplied visible/forensic metadata and adds a deterministic page-bound opaque trace before rebuilding an image-only PDF.

## Security outcomes

- exact-body HMAC service authentication and replay-safe fresh nonces;
- no browser-session or CSRF impersonation;
- no second domain database;
- callback data restricted to 64 UTF-8 bytes and opaque action references;
- Telegram callback acknowledgement before backend work;
- stable payment idempotency derived from transport update when available;
- canonical order/payment/entitlement remains server-owned;
- protected delivery issue + consume on every request;
- Telegram protection enabled only from canonical delivery requirement;
- Bale required forward protection fails closed and records a failed delivery receipt;
- Update Server never accepts shell/path/repository/ref/SHA/environment input and is not advertised in ordinary help;
- protected-media worker cannot be falsely enabled with an environment flag while redemption is absent.

## Validation

Run:

```bash
ops/stage7-bots/run-deterministic-tests.sh
```

Current deterministic workstream result: 19 bot tests + 9 worker tests = 28 tests passing. Repository-wide GitHub CI remains owned by the integration workflow; this worker does not edit `.github/workflows/**`.

## Remaining gaps / result

Status remains **PARTIAL** rather than production-complete because academic read projections and protected-media capability/artifact retrieval are not frozen in the backend contract, and real Telegram/Bale/server smoke/bootstrap has not yet run. No legacy or direct-storage shortcut was added to hide these gaps.
