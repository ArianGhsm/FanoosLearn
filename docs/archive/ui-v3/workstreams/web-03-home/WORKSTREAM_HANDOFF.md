# FANOOS Rebuild V3 — Website Workstream 03 Home / Today Handoff

## Workstream identity

- **Design Lock ID:** `FANOOS-UX-2026.09-R1`
- **PARALLEL_REBUILD_BASE_SHA:** `9e72ef32b331ad41626229c28ed24dcc43a49292`
- **Branch:** `rebuild-v3/web-03-home`
- **Owned source path:** `apps/platform/public/assets/ui-v3/home/**`
- **Owned docs path:** `docs/ui-v3/workstreams/web-03-home/**`
- **Source-only:** yes
- **Deployment / server / production state:** untouched

## Files created

1. `apps/platform/public/assets/ui-v3/home/home.js`
2. `apps/platform/public/assets/ui-v3/home/home.css`
3. `docs/ui-v3/workstreams/web-03-home/WORKSTREAM_HANDOFF.md`

No existing entrypoint, UI V2 file, backend service, contract, schema, test, CI/workflow or deployment file is modified by this workstream.

## Product outcome

Home/Today is rebuilt as a prioritized student surface rather than an equal-card dashboard. The visual and information order is:

1. active workspace + workspace-local date context;
2. nearest canonical schedule item / current event when safely derivable;
3. compact today timeline with current/next emphasis;
4. assessment/deadline attention slot;
5. one recent workspace announcement;
6. fresh/available learning resources;
7. latest published grade only when a valid canonical update timestamp identifies it;
8. six lower-priority quick actions.

The desktop composition is asymmetric (today timeline + secondary rail) and the mobile composition stacks by priority. Sections use whitespace, separators and bounded semantic surfaces; the screen is deliberately not a grid of equal cards.

## Public integration surface

After the integration worker loads `home.css` and `home.js`, the renderer is exposed as:

```text
window.FanoosV3.home.render(container, model)
```

Additional presentation-only exports:

```text
window.FanoosV3.home.resolveState(model)
window.FanoosV3.home.stateMatrix
window.FanoosV3.home.dataDependencies
window.FanoosV3.home.routes
window.FanoosV3.home.designLockId
```

The renderer performs no HTTP calls and owns no browser persistence, authentication, authorization, workspace selection, scoring, entitlement or payment truth. Integration must supply canonical read results and route/action handlers.

### Expected model shape

```text
{
  phase: "ready" | "loading" | "session-expired" | "permission-denied" | "error",
  context: {
    workspaceId,
    workspaceName,
    workspace: { id, name, timezone_name },
    timezoneName,
    todayKey,        // preferred canonical workspace-local YYYY-MM-DD
    dateLabel,       // preferred human workspace-local date string
    nowEpochMs,      // optional stable current instant supplied by shell
    greeting         // optional; omitted when not useful
  },
  parts: {
    schedule:      { ok, data },
    assessments:   { ok, data },
    announcements: { ok, data },
    resources:     { ok, data },
    grades:        { ok, data }
  },
  handlers: {
    navigate(routeName, query),
    retryHome(),
    openWorkspacePicker(),
    reauthenticate()
  },
  formatters: {
    time(value, context),
    date(value, context),
    dateTime(value, context),
    number(value, context)
  }
}
```

All handlers/formatters are optional at renderer level so missing integration pieces degrade rather than crash. For correct production schedule/date display, however, integration must pass the canonical workspace timezone and should reuse the shared V3/domain date formatters.

## Data dependencies by section

| Section | Canonical source | Fields used | Behavior when empty | Behavior when failed |
| --- | --- | --- | --- | --- |
| Header / context | `GET /api/v1/account` + workspace projection | workspace human name, `timezone_name`, selected workspace; shell may provide date label | no active workspace becomes dedicated no-workspace state | session/account failure should enter global session/error phase |
| Hero / next-up | `GET /api/v1/workspaces/{workspaceId}/schedule?from=&to=` | `starts_at`, `ends_at`, `title`, `course_title`, `course_code`, `location_text` | honest no-upcoming-event state + Schedule action | local hero failure; other Home sections continue |
| Today timeline | same schedule projection | same fields; rows are ordered by canonical event time | `برای امروز برنامه‌ای ثبت نشده است.` | local timeline failure + safe retry |
| Assessment attention | `GET /api/v1/workspaces/{workspaceId}/assessments` | current: `title`, `assessment_kind`, `requires_entitlement`, `max_attempts`; future-compatible date fields only when actually returned | neutral no-assessment state | local failure + safe retry |
| Announcement | `GET /api/v1/workspaces/{workspaceId}/announcements` | `title`, bounded `body`, `published_at`, recipient `status/read_at` | concise no-new-announcement state | local failure + safe retry |
| Learning | `GET /api/v1/workspaces/{workspaceId}/resources` | `title`, `type_key`, `topic`, `updated_at` | no-published-resource state | local failure + safe retry |
| Grade update | `GET /api/v1/workspaces/{workspaceId}/grades/me` | `item_title`, `gradebook_title`, `course_title`, `score`, `max_score`, `updated_at` | no-published-grade state | local failure + safe retry |
| Quick actions | canonical product routes | no domain data | remains navigation-only | unaffected by a single domain read failure |

## Canonical endpoints / projections consumed

Browser/public contracts only:

- `GET /api/v1/account`
- `GET /api/v1/workspaces`
- `GET /api/v1/workspaces/{workspaceId}/schedule`
- `GET /api/v1/workspaces/{workspaceId}/assessments`
- `GET /api/v1/workspaces/{workspaceId}/announcements`
- `GET /api/v1/workspaces/{workspaceId}/resources`
- `GET /api/v1/workspaces/{workspaceId}/grades/me`

`contracts/openapi/internal-v1.yaml` was inspected but is deliberately **not** consumed: it is HMAC service-only for bots/workers and is not a browser Home API.

## Route actions

Renderer route names deliberately match the canonical V2/V3 product concepts so the merge worker can bridge them to the final shell/router without changing domain semantics:

| Home action | Route name | Intended destination |
| --- | --- | --- |
| مشاهده برنامه / همه برنامه | `schedule` | برنامه |
| باز کردن درس | `courses` + human course-code query | درس‌ها / جزئیات درس |
| همه منابع | `resources` | یادگیری / منابع |
| مشاهده آزمون‌ها | `assessments` | آزمون‌ها |
| مشاهده نمرات | `grades` | نمرات |
| همه اطلاعیه‌ها | `announcements` | اطلاعیه‌ها |
| مشاهده حساب / workspace fallback | `account` | حساب / فضای آموزشی |

Opaque workspace/course/resource/assessment IDs are never rendered as human labels. Course navigation uses `course_code` only when present as the existing human route key.

## State matrix delivered

The matrix is source-exported as `window.FanoosV3.home.stateMatrix` and is also implemented in render behavior:

- `normal_populated`
- `no_schedule`
- `no_announcement`
- `no_workspace`
- `partial_api_failure`
- `session_permission_issue` through explicit `session-expired` / `permission-denied` phases
- `first_use_empty`

Additional explicit states: initial/loading and full Home read error.

### Partial-failure behavior

Home keeps the V2 proven independent-read model. One failed projection does not collapse the page. A top-level compact status announces that some information could not be retrieved, each failed section provides human copy, and `retryHome()` is offered only as a safe read retry. Healthy sections render normally.

A session-expired or global permission issue is different: integration must pass the corresponding global phase so Home stops presenting potentially stale academic content and shows a blocking state.

## Current / next derivation

- `ScheduleProjectionService` reauthorizes `academic.view`, resolves local date bounds through the workspace timezone, excludes cancelled events, and orders by `starts_at`.
- Current/next highlighting compares canonical event instants with `context.nowEpochMs` (or the current instant when none is supplied).
- Database-style naive schedule timestamps are interpreted as UTC instants, matching the current stabilized Web projection behavior; explicit-offset ISO strings are also accepted.
- A timezone name from canonical account/workspace context is required for human local date/time formatting. The renderer does **not** fall back to browser/device timezone authority.
- If a timestamp cannot be compared safely, Home does not label it current/next based on guesswork.

## Freshness / recency policy

### Resources

The current public Content library returns `resource.updated_at` and defaults to `ORDER BY resource.updated_at DESC`. Therefore Home may truthfully call the area `منابع تازه` when comparable update timestamps are present. Without comparable timestamps, wording becomes `منابع در دسترس`.

There is no user-specific “last opened / continue learning” projection, so this workstream does not claim personalized continuation history.

### Grades

`grades/me` returns canonical published grade results and `result.updated_at`, but the current backend orders the list by course/item rather than update time. Home therefore sorts only rows with valid `updated_at` for presentation and uses `نمره تازه منتشرشده` only when a comparable timestamp exists. Otherwise it uses the neutral `نمرات منتشرشده` label.

No average, GPA, weighting or completion policy is calculated client-side.

### Announcements

The public Web projection is already ordered by `published_at DESC`. Home shows one item only and keeps the full announcement list as the destination; it does not duplicate an inbox dump on Home.

## Existing/current files inspected and conceptual reuse

### Required governance / contracts

- `AGENTS.md`
- `docs/ui-v3/FANOOS_UI_UX_DESIGN_LOCK.md`
- supplied Design Lock reference file `FANOOS_UI_UX_DESIGN_LOCK(1).md`
- `docs/ui-v2/FANOOS_PRODUCT_IA.md`
- `docs/ui-v2/FANOOS_PRESENTATION_CONTRACT.md`
- `docs/ui-v2/FANOOS_CROSS_CHANNEL_MATRIX.md`
- `contracts/REGISTRY.md`
- `contracts/openapi/core-v1.yaml`
- `contracts/openapi/internal-v1.yaml`

### Website / prior UX references

- `docs/ui-v2/web/WEB_BACKEND_UI_GAPS.md`
- `docs/ui-v2/web/WEB_DESIGN_SYSTEM.md`
- `docs/ui-v2/web/WEB_HANDOFF.md`
- `docs/ui-v2/web/WEB_INFORMATION_ARCHITECTURE.md`
- `docs/ui-v2/web/WEB_PRODUCT_AUDIT.md`
- `docs/ui-v2/web/WEB_SCREEN_MAP.md`
- `docs/ux-parallel/worker01_web_visual_shell.md`
- `docs/ux-parallel/worker02_web_domain_presentation.md`
- `docs/ux-parallel/UX_INTEGRATION_REPORT.md`
- `docs/ux-parallel/UX_GLOBAL_STABILIZATION_REPORT.md`

### Current implementation / backend projections

- `apps/platform/public/assets/ui-v2/product-academic.js`
- `apps/platform/public/assets/ui-v2/product-core.js` (relevant helpers located through repository search)
- `apps/platform/public/assets/app.js`
- `apps/platform/src/Core/ScheduleProjectionService.php`
- `apps/platform/src/Core/WorkspacePlatformService.php`
- `apps/platform/src/Content/ContentService.php`
- `apps/platform/src/Content/ExamService.php`

### Reused concepts, not copied presentation

- independent Home reads and local partial-failure behavior;
- request-serial/stale-workspace protection remains an integration responsibility and must not be weakened;
- canonical workspace timezone semantics;
- safe DOM text construction rather than API-derived HTML;
- V2 human route names and course-code route convention;
- server authority for schedule, grades, assessments, entitlements and permissions.

The V2 equal-card/overview composition itself is intentionally not retained.

## Integration imports / dependencies

The later merge worker must:

1. load `apps/platform/public/assets/ui-v3/home/home.css` through the shared V3 asset strategy;
2. load `apps/platform/public/assets/ui-v3/home/home.js` after the V3 shell foundation but before first Home render;
3. bridge existing authenticated orchestration into the renderer rather than adding duplicate fetch/session logic inside this module;
4. preserve stale-workspace/request-serial invalidation around the five independent reads;
5. pass canonical `timezone_name` from account/workspace context and preferably canonical `todayKey/dateLabel` through shared formatters;
6. bridge `handlers.navigate` to the final V3 shell/router;
7. keep backend authorization as the only authority even when a route/action is visually present.

No dependency on another V3 workstream's physical source path is hard-coded. The module consumes the shared Design Lock contract and browser-provided model only, so it can be integrated after foundation/shell branches without cross-worker source ownership.

## INTEGRATION_GAPS

1. **Assessment deadline / upcoming projection missing.** Current public assessment catalog exposes `title`, `assessment_kind`, `course_id`, `requires_entitlement` and `max_attempts` but no `deadline_at`, start/end availability window, or other due timestamp. Home therefore renders `آزمون‌های در دسترس` from current data and exposes a future-compatible attention slot. If a canonical due field is added during integration, the same renderer will use it only when actually returned; this workstream does not invent a deadline.
2. **No personalized learning-continuation projection.** Resources expose publication/update metadata, not per-user last-opened/progress/continue state. Home labels the section `منابع تازه` or `منابع در دسترس`, never a fabricated personalized “continue” item.
3. **Grade recency is not server-ordered.** `grades/me` includes `updated_at` but orders by course/item. Home can choose the newest valid timestamp for display, but there is no dedicated server `recent grade update` projection. Merge should verify this remains acceptable if grade contracts change.
4. **Schedule payload does not carry timezone metadata.** Timezone authority lives in canonical workspace/account context. Integration must pass it to Home; browser/device timezone is intentionally not used as authority.
5. **No course-scoped announcement relation.** Home announcement stays workspace-level. It must not synthesize a course association from title/body text.
6. **No dedicated Home aggregate contract.** This is not treated as a blocker: the proven independent safe-read composition is retained. If a future aggregate is introduced, it must remain a presentation projection over canonical domains rather than a new domain authority.

## Screens / components delivered

- Home/Today page header with workspace identity and local date context
- Next-up hero with current/next/fallback/no-event behavior
- Today chronological timeline with bounded six-row density and current/next labels
- Assessment/deadline attention slot with truthful current fallback
- One-item recent announcement section
- Fresh/available resources section
- Published grade update section with no GPA/average fabrication
- Six-action quick navigation section below today content
- Loading skeleton
- First-use empty state
- No-workspace state
- No-schedule state
- No-announcement state
- Partial API failure banner + local failures
- Session-expired / permission-denied / full-error blocking states
- Responsive desktop/tablet/mobile styles down through narrow 320–390px class behavior
- `prefers-reduced-motion` handling and visible keyboard focus

## Merge-worker assumptions to verify

- `main` integration still treats `9e72ef32b331ad41626229c28ed24dcc43a49292` as the common parallel rebuild base for all worker branches.
- V3 foundation/shell exposes or bridges the Design Lock tokens used here; local fallbacks intentionally match the lock values.
- Account/workspace projection continues exposing canonical workspace timezone metadata.
- Home orchestration still supplies independent `schedule`, `assessments`, `announcements`, `resources`, and `grades` results and invalidates stale workspace responses.
- Route names remain compatible with canonical product destinations or are explicitly adapted once in the integration layer.
- No integration worker replaces honest empty/degraded states with guessed client data.

## Finish boundary

This worker intentionally does not run tests, create tests, edit CI, open a PR, merge, deploy, access a server, inspect production state or perform live browser validation. Those actions are outside this source-only workstream.
