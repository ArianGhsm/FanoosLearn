# Stage 7 — Telegram / Bale Runtime

Telegram and Bale are separate deployable processes with distinct bot tokens, service identities, update offsets, local SQLite files and service units. Both import the same presentation-neutral FANOOS package.

## Telegram

Uses the ordinary Cloud Bot API baseline audited for Stage 7: 4096-character text, 64-byte callback data, 20 MB `getFile`, 50 MB document upload, inline callbacks/edit/chat action and `protect_content`. `/start` payloads are treated only as opaque short-lived account-link tokens and are charset/length bounded.

## Bale

Only the documented common subset is serialized. No Telegram-only `protect_content` field is sent. When the backend requires forward protection, direct Bale delivery fails closed and records the failure. Telegram-style start payloads are not assumed.

## Restart safety

Offsets are committed only after an update is handled. Notification deliveries use canonical lease/receipt and a bounded local send record to recover the send-success/receipt-failure window. Deployment state is read back from the canonical control plane after bot restart.

## Runtime validation

`health.py` verifies non-secret configuration/state and platform identity. `smoke.py` performs `getMe` and optional safe non-production message smoke. Live smoke is mandatory before parity/production claims.
