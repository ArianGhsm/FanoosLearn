# FANOOS V3 — Source Acceptance

Design Lock: `FANOOS-UX-2026.09-R1`

This document defines the final source-level acceptance boundary for the Website V3 + Telegram/Bale Bot V3 repository state. It is intentionally independent of deployment and live-provider validation.

## Integration lineage

- Original parallel rebuild base: `9e72ef32b331ad41626229c28ed24dcc43a49292`
- Website-integrated `main` used as Bot integration base: `60cde274b2a769e36bc2db10ab73e439a7580124`
- Accepted Bot worker heads:
  - bot-01 core shell: `d7bb9a86b5438b8e287dc910cd02f41b12db2a38`
  - bot-02 academic: `6072af48e505d2e60d1f870362e9f26dd34e99fa`
  - bot-03 learning/commerce: `bd02f2c15ec73d923e1d21a87e806ca229f94970`
  - bot-04 provider-native Telegram/Bale: `7b2fed22aeaab712a21e64b7ce82c7994700ddd5`

Worker branches remain provenance inputs only. The final authority is the canonical merged `main` state after the Bot integration PR passes all gates and is merged.

## Accepted architecture

The canonical source architecture is:

`Website V3 + existing backend authority + integrated BotApplication + one semantic V3 Screen model + provider-specific Telegram/Bale renderers`

The source candidate is acceptable only when all of the following remain true:

- Website V3 integration handoff exists and declares `WEB_V3_INTEGRATED=true`.
- Bot-01 is the single semantic screen authority for V3 bot-owned screens.
- Bot-02 and bot-03 build on bot-01 semantics rather than defining competing Screen models.
- Bot-04 consumes the final semantic model through the integration adapter.
- Telegram and Bale runtimes instantiate the integrated application path.
- The backend/internal API remains authoritative for identity, workspace membership, grades, payments, entitlements, protected content and deployment permission.
- No presentation callback, local navigation state or provider payload is treated as authorization.
- Zero-workspace users retain useful Home/Workspace/Account/Help/Website navigation without invented membership.
- Course truth comes from the canonical attached course projection rather than activity-derived inference.
- Grades do not invent GPA/averages.
- Assessments do not create bot-local attempt/scoring authority when a bot-safe projection is unavailable.
- Order, payment and entitlement remain separate facts.
- Protected delivery is fail-closed and never falls back to an unprotected original.
- Owner Update Server remains private Telegram + canonical `deployment.manage` only; Bale exposes no equivalent.
- No secret, raw storage key/path, provider subject, arbitrary deployment ref/command or local authorization truth is introduced.

## Deterministic acceptance gates

The final source-ready state requires all of these gates:

1. Complete PR diff self-review with no unresolved Critical/High security issue.
2. Exact PR-head CI green for Python Bot/worker tests, Website UX tests, PHP/static/unit checks and MySQL integration tests.
3. PR merge to `main` without post-review source changes that bypass CI.
4. Exact post-merge `main` read proving the merged source contains Website V3 and Bot V3 integration.
5. If post-merge CI is automatically triggered, it must have no unresolved failure before deployment handoff is treated as complete.

The exact source-ready SHA is deliberately **not hard-coded into this committed document** because a document cannot safely contain the SHA of the commit that contains itself. The authoritative `FANOOS_V3_SOURCE_READY_SHA` is the exact post-merge `main` SHA reported by the integration task after merge.

## Source readiness vs deployment

When all deterministic acceptance gates above are satisfied, the external integration result may declare:

```text
WEB_V3_INTEGRATED=true
BOT_V3_INTEGRATED=true
FANOOS_V3_SOURCE_READY_FOR_DEPLOY=true
DEPLOYED=false
```

Until merge and exact post-merge verification, the integration branch is only a source candidate and must not claim final source-ready status.

## Mandatory deployment-stage validation

Source acceptance does not claim live behavior. The deployment task must still perform:

```text
LIVE_BROWSER_VALIDATION_REQUIRED=true
TELEGRAM_RUNTIME_VALIDATION_REQUIRED=true
BALE_RUNTIME_VALIDATION_REQUIRED=true
PROTECTED_MEDIA_RUNTIME_VALIDATION_REQUIRED=true
```

Any live failure discovered later must be fixed through normal source/CI/review controls rather than by weakening the source acceptance contract.
