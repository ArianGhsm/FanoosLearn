# FANOOS Rebuild V3 — Web 05 Schedule Handoff

Design Lock ID: `FANOOS-UX-2026.09-R1`

`PARALLEL_REBUILD_BASE_SHA`: `9e72ef32b331ad41626229c28ed24dcc43a49292`

Branch: `rebuild-v3/web-05-schedule`

## Ownership

Owned source path: `apps/platform/public/assets/ui-v3/schedule/**`

Owned handoff path: `docs/ui-v3/workstreams/web-05-schedule/**`

No shared entrypoint, backend, contract, migration, workflow, test, bot, worker, deployment or runtime file is modified by this workstream.

## Files created

- `apps/platform/public/assets/ui-v3/schedule/index.js`
- `apps/platform/public/assets/ui-v3/schedule/schedule-model.js`
- `apps/platform/public/assets/ui-v3/schedule/schedule.css`
- `docs/ui-v3/workstreams/web-05-schedule/WORKSTREAM_HANDOFF.md`

## Delivered surfaces and components

The workstream delivers one self-contained V3 Schedule module with three first-class modes: `امروز`, `هفته`, and `پیشِ رو`.

It includes an RTL chronological day timeline with a dedicated time rail; a seven-day Saturday–Friday desktop grid; a mobile week transformation using a seven-day strip plus one-day agenda; bounded upcoming groups by date with progressive reveal; previous/next date navigation and a Today shortcut; course and canonical event-type filters derived only from authorized schedule rows; partial-data handling; safe empty/loading/session/permission/error states; current/next presentation cues only when the fetched range contains the workspace-local current date and event instants are parseable; and a transient accessible event-detail dialog.

Event detail displays only projected fields: title, start/end, course, location, canonical event type and canonical status. Optional instructor/professor and notes fields are supported only when actually present in a future returned row. The current projection does not provide them. A course CTA is rendered only when a human `course_code` is present.

## Current/legacy files inspected and reused conceptually

Repository governance and architecture read before writing included `AGENTS.md`, `docs/workflow/INTEGRATION_ONLY_PATHS.md`, `docs/fanoos-migration/02_TARGET_ARCHITECTURE.md`, `docs/fanoos-migration/02_MODULE_AND_DATA_OWNERSHIP.md`, `contracts/REGISTRY.md`, the public/internal OpenAPI contracts, and the canonical Design Lock.

V2 website references included all files under `docs/ui-v2/web/**`, `docs/ui-v2/FANOOS_PRODUCT_IA.md`, `docs/ui-v2/FANOOS_PRESENTATION_CONTRACT.md`, `docs/ui-v2/FANOOS_CROSS_CHANNEL_MATRIX.md`, and the relevant parallel UX handoffs. Current implementation references included `apps/platform/public/assets/app.js`, `apps/platform/public/assets/domain-ux.js`, `apps/platform/public/assets/ui-v2/product-core.js`, and `apps/platform/public/assets/ui-v2/product-academic.js`.

Canonical backend references included `apps/platform/src/Core/ScheduleProjectionService.php`, `apps/platform/src/Core/ScheduleWindowResolver.php`, `apps/platform/src/Http/ApiKernel.php`, and the account projection in `apps/platform/src/Identity/AuthService.php`.

Concepts deliberately reused are: `academic.view` authorization remains server-owned; schedule datetimes returned from SQL are treated as UTC instants before workspace-local presentation; workspace `timezone_name` is authoritative; week starts on Saturday; course matching prefers human course code for routes and canonical IDs remain hidden; all normal API strings are inserted as text; cancelled events remain backend-filtered; and the established course deep-link route is retained.

The V2 flat schedule presentation is not preserved. V3 rebuilds hierarchy, density, mobile behavior, filters, detail affordance and temporal navigation from the Design Lock.

## Canonical endpoints and projections consumed

Primary schedule read:

`GET /api/v1/workspaces/{workspaceId}/schedule?from=YYYY-MM-DD&to=YYYY-MM-DD`

The public contract defines `from` and `to` as inclusive local calendar dates interpreted by the server in `tenant_workspaces.timezone_name`. This module always sends explicit bounds and therefore never relies on the `ApiKernel` fallback range.

Workspace identity and timezone are expected to have been loaded by the shared shell/account flow from `GET /api/v1/account`. Current account rows expose `id`, human workspace metadata and `timezone_name` for active memberships.

Current schedule row fields consumed are: `id` only as an internal DOM identity; `event_type`; `title`; `starts_at`; `ends_at`; `location_text`; `status`; `offering_id` only as hidden identity; `course_id` only as hidden identity; `course_code`; and `course_title`. No UUID is rendered or placed in a user-facing route.

## Date/range API assumptions

Today requests exactly one workspace-local date: `from=date`, `to=date`.

Week requests exactly seven inclusive local dates. The presentation week is Saturday through Friday, matching the established FANOOS V2 helper behavior. Date arithmetic operates on validated Gregorian `YYYY-MM-DD` keys and does not use browser-local midnight arithmetic.

Upcoming requests a bounded 90-day inclusive window beginning at the selected anchor date, clamped so the default/previous navigation cannot move before the workspace-local current date. The client initially renders 24 events and reveals further already-authorized results in batches of 24. This is presentation density only; it does not create pagination or domain state.

The backend resolver currently allows date windows up to 400 days, so all V3 ranges remain within the canonical bound.

## Timezone handling

`tenant_workspaces.timezone_name`, surfaced through the current account projection, is mandatory for this module. Missing or invalid timezone metadata fails closed into a user-facing state; the browser timezone and host timezone are never substituted.

The current SQL schedule projection returns database datetime strings without an offset. Existing FANOOS Web code treats these rows as UTC instants (`assumeUtc`) and formats them using the selected workspace IANA timezone. V3 preserves that proven behavior. Explicit-offset/`Z` datetimes are also accepted without reinterpretation.

The workspace-local current Gregorian date is derived with `Intl.DateTimeFormat` using the explicit workspace IANA timezone. Date-key navigation uses UTC only as a neutral arithmetic carrier for date-only keys, never as workspace business timezone.

Current/next badges are presentation cues, not domain state. They require parseable instants and are only computed when the fetched range contains the workspace-local current date.

## Route/query contract

Primary routes:

- `#/schedule/today`
- `#/schedule/week`
- `#/schedule/upcoming`

Supported presentation query state:

- `date=YYYY-MM-DD`: anchor date for the selected mode;
- `day=YYYY-MM-DD`: selected daily agenda inside mobile Week mode only;
- `course=<human-course-code>`: client-side filter over already-authorized returned rows;
- `type=class|exam|deadline|event|other`: client-side filter over canonical schedule event types actually present in returned rows.

No route uses event IDs, course UUIDs, offering UUIDs, workspace UUIDs or storage/provider identifiers as visible product labels.

The module uses the established navigation adapter shape `ctx.navigate(name, query, subview)`. The merge worker must preserve that adapter or wrap the shared V3 router accordingly.

## Course deep-link contract

From Event Detail, a course link is shown only when `course_code` is projected. It targets the established human route:

`#/courses?course=<human-course-code>&tab=schedule`

The source calls the navigation adapter as `ctx.navigate('courses', { course: code, tab: 'schedule' })`. Events lacking a human course code remain fully visible but do not expose an opaque fallback route.

## Integration imports and dependencies

The module follows the Design Lock module shape and exports `moduleDefinition` plus a default export. Its definition declares the three schedule routes, one Schedule nav item, and `styles: ['/assets/ui-v3/schedule/schedule.css']`.

Expected runtime context is the Design Lock `ctx` contract: `ctx.root`, `ctx.api`, `ctx.state`, `ctx.navigate`, and `ctx.signal`. To remain integration-friendly while central wiring is intentionally out of scope, schedule reads accept one of the shared adapter forms `ctx.api(url, options)`, `ctx.api.get(url, options)`, or `ctx.api.request(url, options)`. The module never calls `fetch()` directly and therefore never recreates browser session/CSRF authority.

The CSS owns only `.f3-schedule-*` selectors and consumes Design Lock `--f3-*` tokens with locked-value fallbacks. It does not redefine shared foundation classes or raw element styles.

The module self-links its owned stylesheet as a safe integration fallback while also declaring the stylesheet in `moduleDefinition.styles`. Once the V3 shell/foundation loader is integrated, the merge worker may rely on the declared style manifest and remove only redundant loading behavior if the shared loader guarantees one load.

## INTEGRATION_GAPS

1. The public OpenAPI schedule endpoint does not currently define a concrete response-row schema. Field semantics were verified from `ScheduleProjectionService.php`; integration should eventually publish that projection schema without changing the existing meanings.
2. The schedule response itself does not carry workspace timezone metadata. V3 therefore depends on the shared authenticated account/workspace state having the current `timezone_name` before Schedule mounts.
3. Current schedule projection does not expose instructor/professor or notes. Event Detail intentionally omits them until a canonical projection adds those optional fields.
4. There is no public event-detail endpoint. Detail is limited to the fields already returned in the authorized range row; no hidden event truth is fetched or invented.
5. Canonical database event types are `class`, `exam`, `deadline`, `event`, and `other`, but the public OpenAPI response currently does not publish that enum. V3 exposes filter choices only for recognized canonical values actually observed in authorized rows and hides unknown raw codes.
6. Course filtering/deep-linking requires a human `course_code`. A schedule event with only `course_id` cannot receive a bookmarkable course filter or deep link without leaking an opaque identifier; no title-derived pseudo-identity is invented.
7. Exact `ctx.api` and `ctx.navigate` implementation belongs to the later V3 shell/integration workstream. This module deliberately does not edit shared entrypoints to install itself.

## Merge-worker source assumptions to verify

The merge worker should verify that the integrated V3 shell provides the selected workspace object or account membership list with `timezone_name`; preserves stale-request cancellation through `ctx.signal`; maps `ctx.api` errors to a status-bearing error for 401/403 presentation; loads or honors `moduleDefinition.styles`; and adapts `ctx.navigate(name, query, subview)` without changing the documented human routes.

The merge worker should also preserve the current backend UTC storage/projection convention. If a later canonical API starts returning timezone-offset schedule strings or a typed schedule DTO, update only the temporal adapter and handoff assumptions rather than applying a second timezone conversion.
