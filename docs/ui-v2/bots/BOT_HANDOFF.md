# FANOOS UI V2 — Bot Handoff

Branch: `ui-v2/bot-product-rebuild`  
Base: `74e0d4083a5311cd5d2e527af8b3baf4a46c96f6`

## Owned implementation

- additive semantic screen metadata (`breadcrumb`, `pagination`);
- structured Home / More IA;
- course-centric product surface;
- schedule hub + weekly pagination;
- course-scoped schedule/resources/grades from reauthorized projections;
- explicit assessment/notification/catalog gap states;
- resource detail before protected delivery;
- human purchase/access path without UUID instructions;
- account unlink confirmation;
- role-aware Telegram management entry;
- standardized back/home paths;
- bounded restart-safe presentation route correlation;
- improved Bale semantic text layout;
- expanded deterministic UX V2 tests.

## Invariants intentionally unchanged

- canonical messaging identity/link authority;
- workspace membership/RBAC;
- payment and entitlement authority;
- delivery issue/consume/receipt;
- protected-media source/artifact security;
- notification claim/receipt idempotency;
- Telegram callback ACK ordering;
- Telegram private owner update control;
- Bale deployment prohibition;
- no business replay after presentation failure.

## Prompt 3 integration notes

1. Merge/reconcile the website UI V2 branch and this bot branch only after both are reviewed against the same base contract.
2. Preserve backend/UI ownership: do not resolve `BOT_BACKEND_UI_GAPS.md` by copying website state into bot local storage.
3. If the website branch adds canonical course/product/assessment/notification endpoints through an integration-approved backend change, adapt `BotApplication` to consume those projections and delete the corresponding gap copy. Do not retain two authorities.
4. Preserve `Screen.text` as complete fallback and keep `ScreenPresentation` additive; website components do not depend on bot semantic metadata.
5. Keep `.github/workflows/**`, contracts and central PHP wiring integration-owned. This branch only extends the existing Stage 7 deterministic test runner so the new `tests/ux-v2/bots/**` suite executes in the existing CI job.
6. Re-run the full Stage 7 bot/worker/UX tests after conflict resolution, then repository PHP/web/MySQL gates.
7. Re-check callback byte limits after any integration rename.
8. Keep Telegram `deployment.manage` entry private + overview-gated; never expose it on Bale.
9. Do not deploy from Prompt 3. Production runtime validation remains a separate supervised step.

## Expected conflict hotspots

- `packages/python/fanoos_bot/models.py`
- `packages/python/fanoos_bot/presentation.py`
- `packages/python/fanoos_bot/semantic_adapter.py`
- `packages/python/fanoos_bot/application.py`
- `packages/python/fanoos_bot/state.py`
- `packages/python/fanoos_bot/localization.py`
- `ops/stage7-bots/run-deterministic-tests.sh`

The website parallel branch should normally not own these paths. Any conflict here indicates cross-stream drift and should be resolved semantically, not by choosing one side blindly.
