# FANOOS Website V3 — Integration Report

**Design Lock:** `FANOOS-UX-2026.09-R1`  
**Original parallel base:** `9e72ef32b331ad41626229c28ed24dcc43a49292`  
**Integration branch:** `rebuild-v3/integration-web`  
**Boundary:** GitHub source integration only; no server access and no deployment.

## Architecture

`apps/platform/public/index.php` is now the canonical Website entrypoint and boots V3 directly. The page has `lang="fa"`, `dir="rtl"`, one `.f3-root`, deterministic local CSS links, and one ES-module entrypoint at `assets/ui-v3/app/bootstrap.js`. V2 remains in the repository as rollback/reference source but is not loaded by the default page.

Foundation is the canonical token/primitive layer. Shell remains the persistent product shell and the only browser route listener. Domain workers are mounted through one integration route manager. The manager creates a fresh `AbortController` for each route mount, aborts the previous mount before unmount, and aborts workspace-scoped work as soon as Shell emits the workspace-switch start event.

The canonical domain context is:

`ctx.root`, `ctx.api`, `ctx.state`, `ctx.navigate`, `ctx.format`, `ctx.ui`, `ctx.capabilities`, `ctx.signal`.

`ctx.state` is presentation-only. Server projections remain authoritative for workspace selection, permissions, assessment scoring, payment status, entitlement/access and protected-resource delivery.

## Router and navigation reconciliation

Shell's route registry is the only route resolution authority. Integration does not create a second `hashchange` listener.

Canonical routes:

- `/home`
- `/courses`
- `/courses/{human-course-code}`
- `/schedule` with deterministic Today default
- `/schedule/today`
- `/schedule/week`
- `/schedule/upcoming`
- `/resources`
- `/assessments`
- `/grades`
- `/announcements`
- `/notifications`
- `/forms`
- `/orders`
- `/account`
- `/management` when Shell's canonical management projection permits entry
- `/more` for compact/mobile secondary navigation

`/learning` is accepted only as a compatibility input and normalizes to `/resources`; it does not create a second learning screen.

## Auth, session and workspace

web-02 Shell remains responsible for auth/session/workspace UI. Signed-out, login pending/error, session-check error, selected workspace, explicit workspace selection, zero-membership, account, logout and workspace switch states remain centralized.

The API bridge preserves bearer-session and CSRF semantics and stores only the existing short session bridge values in `sessionStorage`. It does not persist workspace identity. Account data is cached only as an in-memory presentation snapshot from the canonical `/api/v1/account` response so domain mounts can resolve workspace name/timezone.

A workspace switch immediately removes/aborts old route content before the canonical mutation. New route data is mounted only against the new workspace context. Domain 401 responses from workspace APIs request Shell session-expiry handling rather than creating a second auth UI.

## Home

web-03 remains the renderer. `app/home-module.js` supplies the module adapter and independently reads schedule, assessments, announcements, resources and grades. Each read is wrapped so one failed section degrades locally instead of collapsing Home. Schedule query dates are derived using the canonical workspace timezone.

No recommendation ranking, deadline, personalized continuation item, GPA or grade average is fabricated.

## Courses and Course Detail

web-04 remains canonical for Courses. Human `course_code` is the route identity; opaque course UUIDs are not route labels.

Course Detail integrations:

- Schedule → web-05, filtered by human course code.
- Resources → web-06 `createCourseLearningEmbed`, filtered by canonical course ID.
- Assessments → web-07 module mounted invisibly, then filtered using web-07's canonical academics-derived presentation key before reveal; if an exact course filter cannot be established, the embed fails closed instead of showing another course's assessments.
- Grades → web-07 `renderCourseGradesSlot`.
- Announcements → intentionally absent because no canonical course↔announcement binding exists.

Embedded domain H1 elements are demoted through a scoped integration observer so Course Detail keeps one page H1 while worker screens can continue to own H1 at top level.

## Schedule

web-05 is canonical. `/schedule` resolves to Today semantics; Today, Week and Upcoming remain distinct subviews. Saturday–Friday week logic comes from the worker model. All schedule date/time presentation requires canonical workspace timezone; browser/device timezone is not used as authority.

The schedule worker's own dynamic stylesheet fallback is suppressed by preloading the exact CSS URL with its `data-f3-schedule-style` marker, preventing duplicate asset injection.

## Learning / Resources

web-06 is canonical under `/resources`. Search, course/type filtering, recent ordering, resource detail and protected delivery states remain worker-owned.

Integration owns the secure delivery bridge:

1. issue a short-lived delivery capability;
2. consume it;
3. render only whitelisted structured text when structured content is returned; or
4. redeem the short-lived download token through same-origin binary API;
5. save bytes through a temporary object URL that is revoked.

Delivery/download tokens stay in local function scope and are never copied to DOM, route, local/session storage or persistent application state. Storage/object IDs are ignored for presentation.

## Assessments and Grades

web-07 remains canonical. Attempt creation, revision saves, submit and review use canonical server APIs. No browser scoring and no answer-key field are introduced. Grades display published rows and score/max only when projected; integration does not calculate GPA or aggregate average.

## Operations / Communication / Commerce

web-08 remains canonical for announcements, personal-notification gap state, forms, orders/access and management.

Announcement and personal notification remain separate semantics. No durable notification history is fabricated.

Payment and access are separate. Paid status does not imply active entitlement. Product/provider technical identifiers are not requested from users or rendered as business truth.

Management entry is gated by the canonical dashboard projection. Granular mutation capabilities remain fail-closed because the current public projection does not expose a complete trustworthy permission set. Read-only member presentation may appear only when the canonical dashboard exposes that section. Website V3 contains no Update Server surface.

## Design reconciliation

All worker styles load after Foundation tokens/components and remain under V3 namespaces. Integration adds no alternate palette, external font/CDN dependency or emoji-based primary Website navigation. Foundation remains the common emerald/token authority; integration CSS is layout/reconciliation-only.

The integrated entrypoint removes the V2 double-shell possibility and avoids repeated CSS injection. Desktop and mobile remain Shell-owned, including the mobile bottom navigation and sheets.

## Accessibility reconciliation

Source-level invariants include:

- Persian RTL document semantics;
- one Shell skip link per rendered shell/auth surface;
- focus-visible foundation rules;
- route focus handoff to the new route H1/outlet;
- native modal dialog containment for structured resources plus explicit focus return;
- Shell sheet containment/return;
- `aria-current` in navigation;
- worker tab/list semantics;
- live status regions;
- label associations;
- 44px minimum control token;
- reduced-motion rules;
- embedded H1 demotion for Course Detail composition.

Live browser/assistive-technology verification remains a post-deployment requirement.

## Resolved integration conflicts

1. Different worker route descriptor shapes → only Shell top-level routes are authoritative; worker descriptors are not blindly registered.
2. `/learning` vs `/resources` → `/resources` canonical, `/learning` compatibility-normalized.
3. Global Home renderer vs module contract → narrow Home adapter.
4. Course cross-domain slots → integration adapters using the owning workers.
5. Schedule duplicate CSS fallback → deterministic preload marker.
6. V2 tests tied to the previous primary `index.php` → exact previous entrypoint retained as a test fixture; V2 regression assertions remain active without forcing V2 back into production entrypoint.
7. Personal notification route absent from Shell seed → registered through Shell's integration route seam and exposed as a secondary More destination.
8. Granular management permission gap → mutation UI stays fail-closed.

## Deferred non-blocking backend/product gaps

- no durable browser personal-notification history projection;
- no canonical course↔announcement relation;
- no complete browser granular-permission projection for management mutations;
- no trustworthy library total/cursor projection beyond current limit;
- no personalized continue-learning projection;
- no assessment deadline projection in the current public catalog;
- no authoritative exact per-resource entitlement badge in list projection;
- no management queue projection containing target resource-version IDs for review/publish.

These gaps are represented honestly in UI and do not justify browser-side invented authority.
