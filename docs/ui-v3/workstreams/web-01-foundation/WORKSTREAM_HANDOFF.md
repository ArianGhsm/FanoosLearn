# FANOOS REBUILD V3 — WEB 01 FOUNDATION HANDOFF

## Workstream identity

- Design Lock ID: `FANOOS-UX-2026.09-R1`
- `PARALLEL_REBUILD_BASE_SHA`: `9e72ef32b331ad41626229c28ed24dcc43a49292`
- Branch: `rebuild-v3/web-01-foundation`
- Owned source path: `apps/platform/public/assets/ui-v3/foundation/**`
- Owned docs path: `docs/ui-v3/workstreams/web-01-foundation/**`
- Scope: shared Website V3 presentation/runtime foundation only. No page/domain feature, backend, schema, deployment, CI or current entrypoint is owned here.

## Files created

Source:

- `apps/platform/public/assets/ui-v3/foundation/tokens.css`
- `apps/platform/public/assets/ui-v3/foundation/base.css`
- `apps/platform/public/assets/ui-v3/foundation/components.css`
- `apps/platform/public/assets/ui-v3/foundation/icons.svg`
- `apps/platform/public/assets/ui-v3/foundation/dom.js`
- `apps/platform/public/assets/ui-v3/foundation/ui.js`
- `apps/platform/public/assets/ui-v3/foundation/runtime-contract.js`
- `apps/platform/public/assets/ui-v3/foundation/module-contract.js`
- `apps/platform/public/assets/ui-v3/foundation/index.js`

Handoff:

- `docs/ui-v3/workstreams/web-01-foundation/WORKSTREAM_HANDOFF.md`

## Delivered foundation

### CSS

`tokens.css` implements the Design Lock V3 palette exactly under `--f3-*`, plus semantic status aliases, spacing, type, radius, elevation, motion, z-index, touch-target and responsive container primitives.

`base.css` is deliberately scoped under `.f3-root` except for the uniquely-prefixed token variables. It provides RTL-native typography, safe box sizing, accessible links/focus, `<bdi>` and technical-token helpers, selection, skip-link, bounded containers and reduced-motion behavior without resetting the current V2 application.

`components.css` supplies namespaced shared primitives:

- primary / secondary / quiet / danger buttons;
- icon buttons;
- badge/status and chips;
- fields, input/select styling and search;
- page header;
- grouped surfaces;
- data rows/lists;
- tabs and segmented controls;
- empty/info/error/success/warning states;
- skeletons;
- toast region/toasts;
- dialog and responsive drawer/sheet;
- pagination;
- divider;
- icon sizing/direction behavior.

The visually-hidden utility lives in `base.css` as `.f3-visually-hidden`.

### Local icon sprite

`icons.svg` is a same-origin 24×24 outline family using `currentColor`, rounded geometry and a consistent 1.8 stroke. Exact symbol IDs:

- `f3-icon-home`
- `f3-icon-courses`
- `f3-icon-calendar`
- `f3-icon-learning`
- `f3-icon-assessment`
- `f3-icon-grades`
- `f3-icon-announcement`
- `f3-icon-notification`
- `f3-icon-forms`
- `f3-icon-payment`
- `f3-icon-workspace`
- `f3-icon-account`
- `f3-icon-search`
- `f3-icon-chevron`
- `f3-icon-arrow`
- `f3-icon-close`
- `f3-icon-menu`
- `f3-icon-more`
- `f3-icon-filter`
- `f3-icon-download`
- `f3-icon-lock`
- `f3-icon-external-link`
- `f3-icon-success`
- `f3-icon-warning`
- `f3-icon-error`
- `f3-icon-info`

`chevron` and `arrow` are marked directional by the JS helper and mirror under an explicit RTL V3 root. No emoji or remote icon/font dependency is introduced.

## Exact JavaScript exports for web-02 through web-08

All source is native ES modules. `index.js` re-exports every public item below and also exports namespaces `dom` and `ui`.

### `dom.js`

- `asText`
- `textNode`
- `clear`
- `normalizeSafeUrl`
- `setAttributes`
- `setDataset`
- `append`
- `createElement`
- `createBdi`
- `technicalText`
- `focusSafely`

DOM composition uses `createElement`, `textContent`, `createTextNode`, validated attributes and node append operations. Event-handler attributes, raw style attributes, document-fragment HTML injection surfaces and unsafe URL protocols are rejected/omitted. There is no API-derived HTML composition helper.

### `ui.js`

Constants:

- `ICON_SPRITE_URL`
- `ICON_IDS`

Factories/controllers:

- `icon`
- `button`
- `iconButton`
- `badge`
- `chip`
- `field`
- `searchField`
- `pageHeader`
- `surface`
- `dataRow`
- `stateView`
- `skeleton`
- `skeletonStack`
- `createDialog`
- `createDrawer`
- `createToastRegion`
- `createTabs`
- `createSegmentedControl`
- `pagination`
- `divider`
- `setBusy`
- `replaceContent`

Dialog/drawer controllers provide `open`, `close`, `destroy` and `isOpen()` and implement Escape/backdrop close, Tab focus containment, initial focus and focus return. Consumers must append their returned overlay inside the V3 root and tie the controller to the current mount `signal`.

### `runtime-contract.js`

- `RUNTIME_CONTRACT_VERSION` = `FANOOS-V3-RUNTIME-1`
- `RUNTIME_CONTEXT_KEYS`
- `FORMATTER_KEYS`
- `isAbortSignal`
- `assertRuntimeContext`

The exact future merge-time context is:

`root, api, state, navigate, format, ui, capabilities, signal`

`api` is the one application/browser API adapter; it is not reimplemented here. `state` is application-owned presentation state, not canonical domain truth. `capabilities.has(name)` is visibility/presentation gating only and never authorization. `signal` is a fresh mount-scoped `AbortSignal`.

`ctx.format` is required to expose these adapters:

- `text`
- `number`
- `date`
- `dateTime`
- `time`
- `money`
- `status`
- `resourceType`
- `assessmentType`

They must preserve canonical workspace-timezone and server-owned domain semantics rather than being independently reimplemented by feature workstreams.

### `module-contract.js`

- `MODULE_CONTRACT_VERSION` = `FANOOS-V3-MODULE-1`
- `MODULE_SHAPE_KEYS`
- `MODULE_LIFECYCLE_EXPECTATIONS`
- `assertModuleDefinition`
- `defineModule`
- `mountModule`
- `unmountModule`

Exact module shape:

`id, routes, navItems, mount(ctx), unmount(ctx)`

IDs are lowercase kebab-case. `routes` and `navItems` remain presentation metadata; the shell owns actual routing/navigation. Every request/listener/timer/controller that can survive a route must bind to `ctx.signal`. `unmount(ctx)` must remain safe after that signal has already been aborted. A module may not create a second backend, durable app store, router authority, authorization decision, scoring decision, payment truth or entitlement truth.

## CSS load order after integration

Load in this exact order before shell/feature V3 CSS:

1. `/assets/ui-v3/foundation/tokens.css`
2. `/assets/ui-v3/foundation/base.css`
3. `/assets/ui-v3/foundation/components.css`
4. later V3 shell/workstream CSS

Do not load V3 foundation by dynamically injecting duplicate assets. The final integration entrypoint should parser-declare CSS and load `index.js`/feature code as ES modules in a deterministic order.

The V3 application root must carry both `class="f3-root"` and explicit `dir="rtl"`; `lang="fa"` should be on the document or V3 root. This is required for predictable RTL directional-icon behavior and Persian-first typography while V2 still coexists.

## Current/legacy source inspected and conceptually reused

Authoritative/current references read before implementation:

- `AGENTS.md`
- `docs/ui-v3/FANOOS_UI_UX_DESIGN_LOCK.md`
- `docs/ui-v2/FANOOS_PRODUCT_IA.md`
- `docs/ui-v2/FANOOS_PRESENTATION_CONTRACT.md`
- `docs/ui-v2/FANOOS_CROSS_CHANNEL_MATRIX.md`
- all files under `docs/ui-v2/web/**`
- `docs/ux-parallel/worker01_web_visual_shell.md`
- `docs/ux-parallel/UX_INTEGRATION_REPORT.md`
- `docs/ux-parallel/UX_GLOBAL_STABILIZATION_REPORT.md`
- `contracts/REGISTRY.md`
- `contracts/openapi/core-v1.yaml`
- `contracts/openapi/internal-v1.yaml`
- `apps/platform/public/assets/app.css`
- `apps/platform/public/assets/app.js`
- `apps/platform/public/assets/ui-v2/product-core.js`
- `apps/platform/public/assets/web-shell/icons.svg`
- `apps/platform/src/Identity/AuthService.php`

Conceptually reused/adapted:

- safe text/node construction rather than raw API HTML;
- server-selected workspace and canonical workspace timezone semantics;
- stale-request/AbortSignal cleanup requirement;
- local same-origin SVG sprite strategy;
- Persian-first RTL and mixed-direction isolation;
- 44px control target, visible focus and reduced motion;
- state text independent of color;
- server authority for permissions, grades/scoring, commerce/payment and entitlements;
- restrained green/neutral academic visual identity, rebuilt against the V3 Design Lock rather than preserving V2 composition.

No V2 source was copied as architecture authority and no legacy runtime/data/branding assumption was introduced.

## Canonical endpoints / projections consumed

**Directly consumed by this foundation: none.**

Foundation issues no HTTP request and owns no backend projection. It only defines the `ctx.api` dependency that the integration shell supplies.

The discovery pass verified the browser-side authority model against current `core-v1` and `AuthService`, including canonical account/session workspace selection and `timezone_name`. `internal-v1` was read to confirm that signed bot/worker service endpoints are not a Website foundation dependency.

Feature workers must consume the current public browser contract, not the internal service API. Current `core-v1` is authoritative when it differs from historical V2 gap documentation; for example, the current contract includes the bounded browser `/workspaces/{workspaceId}/downloads/consume` redemption route.

## Integration imports / dependencies

Web V3 workstreams may import foundation from:

`/assets/ui-v3/foundation/index.js`

or import a specific file directly when smaller dependency scope is useful.

Integration shell obligations:

1. parser-load CSS once in the order above;
2. mount all V3 UI inside an explicit `.f3-root[dir="rtl"]`;
3. create one fresh `AbortController` per module mount and abort it during route/workspace teardown before a stale module can commit presentation state;
4. supply the canonical `ctx.api`, `ctx.state`, `ctx.navigate` and formatter adapters instead of allowing feature-local substitutes;
5. supply `ctx.capabilities.has()` from canonical scoped projections/authorized responses only;
6. keep the icon sprite same-origin and do not replace it with a CDN/proprietary dependency;
7. append dialog/drawer/toast regions inside the V3 root so typography/direction and component semantics remain coherent.

## Screens / components delivered

No product screen is owned by this workstream.

Delivered shared substrate: design tokens, RTL base layer, safe DOM helpers, local icon family, buttons, icon buttons, status/badges/chips, forms/search, page headers, surfaces/data rows, tabs/segmented controls, state/skeleton primitives, toast/live-region controller, dialog/drawer focus controller, pagination/divider utilities, runtime context contract and module lifecycle contract.

## INTEGRATION_GAPS

### FOUNDATION-GAP-01 — shared entrypoint wiring

This branch is intentionally self-contained and does not touch `index.php`, current `app.js`, current `app.css` or UI V2. The later merge/integration worker must load the foundation assets and create the V3 root/module host in its owned integration surface.

### FOUNDATION-GAP-02 — canonical capability adapter

The foundation contract requires `ctx.capabilities.has(name)` for presentation gating, but this workstream does not own a new public authorization projection. The integration shell must derive this adapter only from canonical scoped backend responses/capability projections available after all workstreams merge. It must not infer authority from a role label or hidden control.

### FOUNDATION-GAP-03 — formatter adapter consolidation

Current V2 formatter behavior is split between `domain-ux.js` and presentation helpers. The merge worker must expose the exact `FORMATTER_KEYS` through one `ctx.format` adapter while preserving canonical workspace-timezone, Persian digit, money and status semantics. Feature modules must not fork those rules.

### FOUNDATION-GAP-04 — feature projection gaps remain feature-owned

Notification inbox/history, catalog, assessment-detail, grade-summary, course-announcement binding and other domain projection gaps documented by V2 are not hidden or synthesized by foundation code. Their current status must be rechecked against the latest public contract by the owning feature/integration workstream.

## Merge-worker source assumptions to verify

- All parallel branches being integrated still descend from `9e72ef32b331ad41626229c28ed24dcc43a49292`.
- The final shell provides an explicit V3 RTL root and deterministic local asset loading.
- Target browsers support native ES modules, external same-origin SVG `<use>`, CSS logical properties, `color-mix()`, `:has()` and `100dvh`; no compatibility polyfill/framework is introduced by this branch.
- No feature worker writes canonical domain facts into `ctx.state` merely because the runtime context exposes it.
- Abort-before-remount is preserved on route and workspace transitions so stale async work cannot render into a later context.
