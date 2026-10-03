# FANOOS Bot V3 — Worker Provenance

Design Lock: `FANOOS-UX-2026.09-R1`

Original parallel base: `9e72ef32b331ad41626229c28ed24dcc43a49292`

Website-integrated base used for Bot integration: `60cde274b2a769e36bc2db10ab73e439a7580124`

## Accepted worker heads

| Workstream | Branch | Accepted head | Owned source |
| --- | --- | --- | --- |
| Bot 01 — Core Shell / Onboarding | `rebuild-v3/bot-01-core-shell` | `d7bb9a86b5438b8e287dc910cd02f41b12db2a38` | `packages/python/fanoos_bot/ui_v3/core/**` |
| Bot 02 — Academic | `rebuild-v3/bot-02-academic` | `6072af48e505d2e60d1f870362e9f26dd34e99fa` | `packages/python/fanoos_bot/ui_v3/academic/**` |
| Bot 03 — Learning / Commerce | `rebuild-v3/bot-03-learning` | `bd02f2c15ec73d923e1d21a87e806ca229f94970` | `packages/python/fanoos_bot/ui_v3/learning/**` |
| Bot 04 — Telegram / Bale Provider Native | `rebuild-v3/bot-04-providers` | `7b2fed22aeaab712a21e64b7ce82c7994700ddd5` | `packages/python/fanoos_bot/ui_v3/providers/**` |

All four heads were re-read immediately before integration. Their source changes stayed inside their declared workstream paths and handoff documentation. Shared runtime, backend contracts, database schema and CI were not silently redefined by a worker.

## Integration-owned reconciliation

The integration branch does not use worker branches as independent runtime authorities. It imports their owned semantic modules, then adds a single integration-owned bridge for:

- canonical V3 intent registration and callback routing;
- subject-bound expiring route references when provider callback limits would be exceeded;
- conversion of existing application results into the bot-01 semantic `Screen` model;
- conversion of bot-01 semantic screens into bot-04 provider plans;
- compatibility with existing receipt, idempotency and protected-media transport semantics;
- explicit workspace selection instead of implicit single-membership auto-selection.

No worker callback value, workspace/resource identifier, UI label or provider payload is treated as authorization. Canonical backend checks remain authoritative.

## Contract boundary

No new internal HTTP endpoint, public API field, database migration or service-auth rule was introduced by this integration. Existing frozen Stage 7 contracts remain the source of truth for messaging links, workspace membership, academic reads, commerce, notifications, protected delivery/media and deployment management.
