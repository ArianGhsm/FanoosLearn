# FANOOS V3 — Web 02 Shell/Auth/Workspace/Account Handoff

## Workstream identity

- Design Lock ID: `FANOOS-UX-2026.09-R1`
- `PARALLEL_REBUILD_BASE_SHA`: `9e72ef32b331ad41626229c28ed24dcc43a49292`
- Branch: `rebuild-v3/web-02-shell`
- Owned source: `apps/platform/public/assets/ui-v3/shell/**`
- Owned handoff: `docs/ui-v3/workstreams/web-02-shell/**`
- Integration entry module: `apps/platform/public/assets/ui-v3/shell/index.js`
- Shell stylesheet to load during integration: `apps/platform/public/assets/ui-v3/shell/shell.css`

The branch is source-only. Shared entrypoints, backend/API contracts, V2 assets, migrations, workflows and runtime/deployment files are intentionally untouched.

## Files created

### Source

- `apps/platform/public/assets/ui-v3/shell/index.js`
  - official V3 module-contract entry;
  - hardened session-expiry transitions;
  - management-capability read behavior.
- `apps/platform/public/assets/ui-v3/shell/module.js`
  - desktop/mobile shell controller;
  - authentication composition;
  - workspace selection/switching states;
  - zero-workspace, Account and More destinations;
  - external route outlet/event integration.
- `apps/platform/public/assets/ui-v3/shell/router.js`
  - route registry consumer;
  - hash/history adapters;
  - stable route metadata and navigation slots.
- `apps/platform/public/assets/ui-v3/shell/api.js`
  - shell-facing canonical API adapter;
  - current browser session/CSRF fallback semantics;
  - safe error normalization.
- `apps/platform/public/assets/ui-v3/shell/ui.js`
  - safe DOM primitives using `textContent`;
  - local SVG icon helper;
  - focus-contained dialog/sheet controller;
  - Persian display helpers.
- `apps/platform/public/assets/ui-v3/shell/shell.css`
  - isolated `.f3-shell-*` presentation;
  - responsive desktop/mobile shell;
  - RTL, safe-area, focus and reduced-motion behavior.
- `apps/platform/public/assets/ui-v3/shell/icons.svg`
  - local outline icon sprite; no emoji-based Website navigation.

### Handoff

- `docs/ui-v3/workstreams/web-02-shell/WORKSTREAM_HANDOFF.md`

## User-visible surfaces delivered

1. Signed-out entry/login surface with identifier/password fields, password visibility control, pending state, safe errors and accessible live feedback.
2. Session-check loading and recoverable connection/error state without assuming a failed account probe means the user is signed out.
3. Desktop application shell with persistent RTL sidebar, FANOOS brand, primary/secondary navigation, selected route state, workspace context, compact topbar, account access and content viewport.
4. Mobile shell with exact bottom navigation: `خانه / درس‌ها / برنامه / منابع / بیشتر`, safe-area padding, compact top context and account/workspace access.
5. Mobile/compact secondary navigation sheet for assessments, grades, announcements, forms, purchase/access, account and capability-gated management.
6. Global workspace switcher with selected state, pending state, safe error state and explicit server-confirmed switching.
7. Explicit choose-workspace destination when memberships exist but canonical `selected_workspace_id` is absent/invalid. The client does **not** silently promote the first membership to active workspace.
8. Structured zero-workspace destination that preserves account, product map and start/help guidance instead of collapsing the product to one generic empty card.
9. Account destination with profile summary, membership/workspace summary, selected workspace state and logout. No raw IDs and no fabricated editable profile fields.
10. More destination with secondary product routes, workspace context, account and capability-gated management.
11. Breadcrumb/context strip and external route outlet integration for the domain workstreams.
12. Workspace-switch stale-data state: old outlet content is removed before the canonical switch mutation and downstream modules receive an invalidation event before they may load the new workspace.

## Expected route IDs and paths

The shell keeps existing product route vocabulary instead of introducing parallel aliases.

| Route ID | Path | Slot | Expected owner |
| --- | --- | --- | --- |
| `home` | `/home` | desktop primary + mobile | web-03 Home/Today |
| `courses` | `/courses` | desktop primary + mobile | web-04 Courses |
| `schedule` | `/schedule` | desktop primary + mobile | web-05 Schedule |
| `resources` | `/resources` | desktop primary + mobile | web-06 Learning/Resources |
| `assessments` | `/assessments` | desktop primary + More | web-07 Progress/Assessments/Grades |
| `grades` | `/grades` | desktop primary + More | web-07 Progress/Assessments/Grades |
| `announcements` | `/announcements` | desktop primary + More | web-08 Operations/Communication/Commerce |
| `forms` | `/forms` | desktop secondary + More | web-08 Operations/Communication/Commerce |
| `orders` | `/orders` | desktop secondary + More | web-08 Operations/Communication/Commerce |
| `account` | `/account` | account | web-02 Shell |
| `management` | `/management` | secondary, capability-gated | merge/integration must bind the canonical management destination |
| `more` | `/more` | shell/mobile utility | web-02 Shell |

Navigation presentation:
- Desktop primary: Home, Courses, Schedule, Resources, Assessments, Grades, Announcements.
- Desktop secondary: Forms, Purchase/Access; Management only when the canonical projection says it is available.
- Mobile primary: Home, Courses, Schedule, Resources, More.
- Account stays directly accessible from the shell rather than consuming a mobile-primary slot.

## Shell mount points and module contract

Integration should import the **official entry** from `shell/index.js` and consume its Design-Lock module shape:

```js
moduleDefinition = {
  id: 'web-02-shell',
  routes: [...],
  navItems: [...],
  mount(ctx) {},
  unmount(ctx) {}
}
```

Required/consumed context:
- `ctx.root`: shell host; required.
- `ctx.api`: preferred canonical client adapter; see API adapter expectations below.
- `ctx.navigate`: optional central-router navigation delegate. Without it, the self-contained source supports hash routing for later integration.
- `ctx.ui`: optional route-mount integration hook.
- `ctx.signal`: optional abort signal; unmount/abort is honored.
- `ctx.state`, `ctx.format`, `ctx.capabilities`: reserved by the Design Lock; this shell does not invent new authority from them.

DOM integration points:
- shell root: `[data-f3-shell-root]`
- main content landmark: `#f3-shell-content`
- domain route outlet: `#f3-shell-route-outlet` / `[data-f3-shell-outlet="route"]`
- workspace dialog: `#f3-shell-workspace-sheet`
- More dialog: `#f3-shell-more-sheet`
- accessible live region: `#f3-shell-live`

The merge worker must load `shell.css` and `index.js` from central integration wiring. This branch intentionally does not modify `index.php`, `app.js` or other shared entrypoints.

## External route mount contract

When a workspace-bound external route is active, the shell dispatches:

`fanoos:v3:route-change`

Event detail:

```text
route
outlet
workspaceId
workspaceEpoch
claim()
```

A synchronous integration listener should call `detail.claim()` before mounting the owning route module. `claim()` clears the shell fallback and marks the outlet busy. Alternatively, `ctx.ui.mountRoute(detail)` may claim the outlet itself or return `true` to acknowledge ownership. If neither integration path claims the route, the shell renders a safe unavailable-state rather than fabricated domain content.

Domain workers must keep their own `mount(ctx)/unmount(ctx)` lifecycle; the shell does not import another workstream directory.

## Workspace-switch event contract

One event name is used for the full transition:

`fanoos:v3:workspace-switch`

### Start

```text
phase: "start"
workspaceId: requested canonical workspace ID
previousWorkspaceId
workspaceEpoch: represented as `epoch`
```

Behavior before the POST:
- shell increments the epoch;
- visually removes the previous workspace's route content;
- marks the transition pending;
- emits `start` before the mutation.

**Integration requirement:** every mounted domain module must treat `start` as an immediate stale-read boundary: abort/invalidate pending previous-workspace requests and never paint a response associated with an older epoch.

### Commit

```text
phase: "commit"
workspaceId: canonical active workspace after revalidation
requestedWorkspaceId
previousWorkspaceId
epoch
```

The shell first receives successful `POST /workspaces/select`, then attempts a fresh `GET /account` so membership/workspace labels are replaced from canonical projection. If that post-mutation refresh is temporarily unavailable, the successful server selection remains the active context, a sync-warning is shown, and later domain requests still reauthorize server-side.

### Error

```text
phase: "error"
workspaceId: requested workspace
previousWorkspaceId
epoch
error: { status, code }
```

Only bounded machine status/code are exposed to integration code; raw backend messages are never rendered. The previous workspace context is restored and its route can remount.

## Session events

The shell emits `fanoos:v3:session` with presentation phases:
- `signed-in`
- `signed-out`
- `expired`

Logout follows current secure semantics: local session bridge state is cleared only after confirmed logout or confirmed `401`/already-expired state. Ambiguous network/server failure does not falsely present the user as logged out.

## Canonical endpoints/projections consumed

Public `/api/v1` only:

- `POST /auth/login`
  - existing identifier/password login;
  - consumes canonical `token`, `csrf_token` and session cookie behavior.
- `POST /auth/logout`
  - CSRF-bound canonical logout.
- `GET /account`
  - canonical user + active memberships/workspaces + `selected_workspace_id`.
- `GET /workspaces`
  - supported by the adapter because the current endpoint exposes the same membership/account projection; shell normal flow uses `GET /account` for revalidation.
- `POST /workspaces/select`
  - canonical active-workspace mutation; client never treats a local dropdown value as authority.
- `GET /workspaces/{workspaceId}/admin/dashboard`
  - read-only capability probe; Management navigation is shown only when `management_available === true`.

No `/api/internal/v1/*` endpoint is consumed by Website shell code.

### `ctx.api` integration expectation

Preferred foundation shape is either:
- callable `ctx.api(path, options)`, or
- `ctx.api.request(path, options)`.

The adapter accepts returned canonical data directly or a normalized `{ ok, data }` envelope and expects thrown errors to provide bounded `status`/`code`. Optional explicit methods (`login`, `logout`, `account`, `workspaces`, `selectWorkspace`, `management`, `clearSession`) are also supported.

If foundation does not yet supply `ctx.api`, the source contains a same-origin browser fallback matching current V2 mechanics: cookie credentials, optional bearer session bridge, `X-CSRF-Token` on mutations and session-scoped `fanoos_token`/`fanoos_csrf`. Integration should keep a **single** browser session/CSRF authority; if web-01 foundation owns that adapter, prefer the foundation `ctx.api` path and do not create a second store.

## Current code/documents inspected and conceptual reuse

Mandatory/current references read before writing include:
- `AGENTS.md`
- `docs/ui-v3/FANOOS_UI_UX_DESIGN_LOCK.md`
- `docs/ui-v2/FANOOS_PRODUCT_IA.md`
- `docs/ui-v2/FANOOS_PRESENTATION_CONTRACT.md`
- `docs/ui-v2/FANOOS_CROSS_CHANNEL_MATRIX.md`
- `docs/ui-v2/FANOOS_ROLE_SURFACE_MATRIX.md`
- `docs/ui-v2/FANOOS_BACKEND_UI_GAPS_FINAL.md`
- all current `docs/ui-v2/web/**`
- relevant `docs/ux-parallel/**` website/integration records
- `contracts/REGISTRY.md`
- `contracts/openapi/core-v1.yaml`
- `contracts/openapi/internal-v1.yaml`
- `docs/fanoos-migration/02_TARGET_ARCHITECTURE.md`
- `docs/fanoos-migration/02_MODULE_AND_DATA_OWNERSHIP.md`
- `docs/workflow/INTEGRATION_ONLY_PATHS.md`
- `apps/platform/public/index.php`
- `apps/platform/public/assets/app.js`
- `apps/platform/public/assets/ui-v2/product-core.js`
- `apps/platform/public/assets/ui-v2/product-account.js`
- current V2 shell assets/read-only presentation patterns
- `apps/platform/src/Http/ApiKernel.php`
- `apps/platform/src/Http/Request.php`
- `apps/platform/src/Identity/AuthService.php`

Concepts deliberately reused/adapted:
- exact canonical auth/session/CSRF semantics;
- server-authoritative workspace selection;
- capability-based management visibility instead of role-name checks;
- logout ambiguity handling;
- stale-response invalidation principle, rebuilt as explicit workspace epochs/events;
- Persian status/error hygiene and safe DOM text;
- current stable route vocabulary (`resources`, `orders`, etc.);
- local vector icons and accessible focus patterns.

Presentation was rebuilt: hierarchy, shell layout, auth composition, navigation, workspace chooser, zero-workspace state, account structure, responsive behavior and component composition are V3 source, not preservation of V2 markup.

## Integration dependencies

1. **web-01 foundation**
   - should supply/confirm shared `--f3-*` semantic tokens. `shell.css` consumes those tokens with local fallbacks and does not redefine global `:root` tokens.
   - should preferably provide the canonical `ctx.api` browser adapter so session/CSRF ownership remains centralized.
2. **web-03 through web-08 route modules**
   - merge worker must register their route definitions against the expected route IDs above and mount them into `#f3-shell-route-outlet` using the route claim contract.
3. **central integration router/entrypoint**
   - must import `shell/index.js`, load `shell.css`, provide `ctx.root`, and reconcile `ctx.navigate` with the final central route registry.
4. **management integration**
   - visibility is already canonical/capability-gated; the actual management destination/module must be bound by integration without adding Website deployment controls.

## INTEGRATION_GAPS

1. **Linked Telegram/Bale account state:** current public account projection does not expose canonical linked-channel status. Internal messaging endpoints are service-only. Therefore Account intentionally renders no linked-channel status/management area. If product scope later requires it, add a public subject-scoped projection before UI.
2. **Password/account profile editing and recovery:** no current public projection/command inspected in this workstream justifies editable profile fields, password recovery or account-edit controls. They are intentionally absent rather than faked.
3. **Durable personal notification inbox:** still a documented backend/UI gap. Shell does not create an Inbox destination from delivery receipts or announcements.
4. **Final external route registry ownership:** expected route IDs are frozen above for merge compatibility, but web-03…web-08 definitions are parallel branches. Merge worker must verify their exported IDs and resolve naming centrally without adding aliases that duplicate product truth.
5. **Final `ctx.ui` route-mount API:** Design Lock defines `ctx.ui` but not a physical `mountRoute` method. This workstream supplies a DOM CustomEvent + `claim()` contract and supports an optional `ctx.ui.mountRoute(detail)` hook. Merge worker should choose one canonical wiring path and avoid duplicate mounts.
6. **CSS/JS central loading:** no shared entrypoint may be changed by this worker. `shell.css` + `shell/index.js` must be wired during the dedicated merge/integration task.
7. **Management content ownership:** shell can safely decide whether the nav item is visible from the current canonical dashboard projection, but it does not own the management product screen. Merge worker must bind the real capability-scoped destination.
8. **Support/help backend:** no public support-ticket/help-content projection is required for this shell. The zero-workspace and More surfaces therefore provide static product guidance only; they do not claim a support workflow exists.

## Merge-time source assumptions to verify

- `GET /api/v1/account` retains `user.display_name`, active `workspaces[]`, hierarchy display labels and `selected_workspace_id` semantics used at this base.
- `POST /api/v1/workspaces/select` remains CSRF-bound and server-authoritative.
- `GET /api/v1/workspaces/{workspaceId}/admin/dashboard` keeps `management_available` as the Website nav projection; hidden navigation remains presentation hygiene, never authorization.
- web-01 foundation exposes compatible tokens and one canonical browser API/session bridge.
- web-03…web-08 do not independently render a second global sidebar/topbar/bottom-nav shell.
- external route modules honor workspace epoch invalidation and re-fetch canonical data after `workspace-switch:commit`.
- `shell/index.js` is the integration entry; `module.js` is its controller implementation dependency.
- Website must not gain deployment/update controls; Telegram-private owner Update Server and Bale no-deployment-control invariants remain unchanged.
