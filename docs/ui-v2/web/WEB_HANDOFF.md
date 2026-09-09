# FANOOS UI V2 — Web Handoff

## Branch contract

- Base: `74e0d4083a5311cd5d2e527af8b3baf4a46c96f6`
- Branch: `ui-v2/web-product-rebuild`
- Scope: Website presentation + Web V2 tests/docs only.
- Backend/schema/bots/updater/deployment: unchanged.

## Implementation structure

### Existing assets retained as compatibility/security layer

- `assets/domain-ux.js`
- `assets/domain-ux.css`

Their safe formatter/renderer contract remains available and is deliberately not duplicated into backend authority.

### V2 product layer

- `index.php`: authenticated product shell, persistent navigation, mobile navigation/drawer, workspace/account/search context.
- `assets/app.css`: existing integrated design-token/accessibility primitives retained unchanged.
- `assets/ui-v2/product-shell.css` + `product-shell-responsive.css`: V2 app shell and responsive layout overrides.
- `assets/ui-v2/product-ui.css`: product destination components.
- `assets/ui-v2/product-core.js`: safe DOM helpers, route parsing, formatting adapters and course/date helpers.
- `assets/ui-v2/product-learning.js`: resources, assessments and grades renderers.
- `assets/ui-v2/product-academic.js`: Home/Today, courses/course detail and schedule renderers.
- `assets/ui-v2/product-communication.js`: announcements, forms, orders/access and search renderers.
- `assets/ui-v2/product-account.js`: account/workspace and permission-gated management renderers.
- `assets/ui-v2/product-ui.js`: small compatibility/export bridge after the modules above load.
- `assets/app.js`: canonical API orchestration, stale-request prevention, workspace mutation, partial Home composition, routing, safe mutations.
- `assets/web-shell/icons.svg`: local icon family extension; existing symbol IDs preserved.

## Compatibility details Prompt 3 must preserve

Existing UX regression tests expect the original eight `data-view` keys. V2 preserves exactly that set and maps them to new destinations:

- `academics` → `courses`
- `schedule` → `schedule`
- `resources` → `resources`
- `assessments` → `assessments`
- `grades` → `grades`
- `announcements` → `announcements`
- `forms` → `forms`
- `orders` → `orders`

New destinations (`home`, `account`, `management`, `search`) use `data-route`, so the old regression contract is not weakened.

## Security/authority notes

1. `state.workspace` comes from `/account` or a successful `/workspaces/select`; no persistent browser workspace authority is introduced.
2. Workspace switching increments request serial before the mutation and disables the selector; stale old-workspace reads cannot overwrite UI.
3. Management is visible only after the scoped `/admin/dashboard` succeeds. Hidden controls are never treated as authorization.
4. Home uses independent safe reads; one failed section remains a local presentation failure.
5. API error messages/codes are not dumped to users.
6. All normal API content is rendered through safe DOM/text construction.
7. Grades, payments, entitlements and assessment scoring remain server authority.
8. Update Server remains absent from Website.

## Prompt 3 exact integration work

Prompt 3 should merge this branch together with the independently produced Bot UI branch, then resolve only actual shared-surface conflicts. Do not replace this Web IA with bot menu semantics and do not make bots own Web route state.

Priority reconciliation:

1. Keep canonical backend/API contracts as shared truth.
2. Preserve V2 Web route labels and course-centric IA unless a merged backend projection changes a documented `BACKEND_UI_GAP`.
3. Preserve bot semantic screens/channel adapters independently from Web DOM components.
4. If Prompt 2 adds backend projections, close gaps by updating API orchestration/renderers rather than adding hard-coded JS domain data.
5. For notifications, catalog, secure browser binary delivery and assessment attempt UX, use the exact gaps in `WEB_BACKEND_UI_GAPS.md` as integration checklist items.
6. Re-run both current `tests/ux/**` and `tests/ux-v2/web/**`; do not relax existing static source invariants just to merge branches.
7. After merge, do a live browser validation at 320, 360, 390/430, 768, 1024, 1280+ and wide desktop against a non-production/test-safe environment before deployment acceptance.

## Local acceptance added by this branch

- `tests/ux-v2/web/static_contract_test.py`
- `tests/ux-v2/web/product_ui_test.js`
- `tests/ux-v2/web/product_dom_safety_test.js`

They cover route/IA hooks, legacy navigation compatibility, stale-workspace guards, safe DOM behavior, malicious/null data, Persian/course grouping helpers, local assets, responsive/a11y guards, and absence of developer product-ID UX.

## Known integration blockers

No blocker prevents merging this Website presentation branch itself. Product completeness is limited by the explicit backend projection gaps; those are not silently patched in JavaScript.
