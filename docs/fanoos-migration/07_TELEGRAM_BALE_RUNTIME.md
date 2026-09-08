# Stage 7 — Telegram / Bale Runtime

Status: **SOURCE READY / LIVE RUNTIME VALIDATION REQUIRED**

Telegram and Bale are separate deployable processes with distinct bot tokens, service identities, update offsets, local SQLite files and service units. Both import the same presentation-neutral FANOOS package. Canonical identity, workspace, grades, content, payment, entitlement and deployment state remain Platform-owned.

## Telegram

Uses the ordinary Cloud Bot API capability baseline configured by FANOOS: 4096-character text, 64-byte callback data, document upload bounds, inline callbacks/edit/chat action and `protect_content`. `/start` payloads are treated only as opaque short-lived account-link tokens and are charset/length bounded.

Native commands include workspace selection, today/tomorrow schedule, self grades, announcements, authorized resources, orders and protected delivery. `/update_server` remains outside ordinary help and is private-chat-only; it first performs the canonical read-only permission-aware deployment overview before creating any confirmation.

For a personalized protected PDF, Telegram receives only the derivative bytes returned by the Platform capability endpoint. The bot stages them in a mode-0600 temporary file solely because the provider transport requires a multipart file path, sends exactly one document operation with `protect_content`, then the temporary directory is destroyed.

## Bale

Only the supported common subset is serialized. No Telegram-only `protect_content` field is sent. Native schedule/grades/announcements/resource catalog reads use the same Platform contracts as Telegram. When canonical delivery requires forward protection, direct Bale delivery fails closed and records the failure; it does not fall back to an unprotected source or personalized PDF. Telegram-style start payloads and deployment controls are not assumed.

## Disposable local state

SQLite stores transport/recovery state only:
- polling offsets;
- short-lived owner confirmation references;
- notification/provider send dedupe;
- pending delivery-receipt retry rows;
- processed update IDs;
- bounded protected-media `job_id → subject/workspace/issuance` correlation.

None of these rows grants authorization. The Platform rechecks linked user, active workspace membership, entitlement/resource version and derivative capability on every sensitive operation.

## Restart / duplicate safety

Offsets are committed only after an update is handled. Notification and protected delivery outcomes use bounded local retry state so a backend receipt outage does not cause an already-sent protected payload to be resent. Receipt-bearing protected text must fit one provider message; protected PDF is one provider document operation. A repeated callback update whose protected send already succeeded returns the prior provider reference rather than sending the PDF again.

Deployment request identity/state is read back from the canonical control plane after bot restart.

## Protected-media worker runtime

The production worker template now uses only:
- `protected_media.claim`;
- `protected_media.source.redeem`;
- `protected_media.artifact.authorize`;
- `protected_media.artifact.publish`;
- `protected_media.complete`;
- `protected_media.fail`.

It receives no storage path or storage credential. Temporary input/output files are private and bounded. `PrivateSpoolArtifactSink` remains test/reference code only and is not constructed by production runtime.

Transient backend/API failures leave the lease to expire/retry naturally rather than writing a false terminal failure. Deterministic renderer/input failures use the Platform's bounded failure taxonomy.

## Runtime validation

`health.py` verifies non-secret configuration/state and platform identity. `smoke.py` performs `getMe` and optional safe non-production message smoke. Before enabling protected-media production service, bootstrap must also provision its signed service identity/action allowlist, qpdf/pdfinfo/pdftoppm/Pillow, private temp directory and optional runtime font.

Live smoke is mandatory before production-complete or Telegram/Bale full-parity claims.
