# WORKSTREAM HANDOFF — BOT 02 ACADEMIC JOURNEYS

## Identity

- Design Lock ID: `FANOOS-UX-2026.09-R1`
- `PARALLEL_REBUILD_BASE_SHA`: `9e72ef32b331ad41626229c28ed24dcc43a49292`
- Branch: `rebuild-v3/bot-02-academic`
- Workstream: `FANOOS REBUILD V3 — TELEGRAM + BALE BOT / 02_ACADEMIC_JOURNEYS`

## Owned paths

Source:
- `packages/python/fanoos_bot/ui_v3/academic/**`

Docs:
- `docs/ui-v3/workstreams/bot-02-academic/**`

## Files created

- `packages/python/fanoos_bot/ui_v3/academic/__init__.py`
- `packages/python/fanoos_bot/ui_v3/academic/actions.py`
- `packages/python/fanoos_bot/ui_v3/academic/screens.py`
- `docs/ui-v3/workstreams/bot-02-academic/screen_specs.md`
- `docs/ui-v3/workstreams/bot-02-academic/WORKSTREAM_HANDOFF.md`

## Screens/components delivered

### Courses
- course list, bounded to 8 visible courses per rendered page;
- canonical deduplication of duplicate offering rows by hidden `course_id`;
- optional canonical workspace/term context;
- ambiguity-safe term rendering when a course appears in multiple offering terms;
- two-column short-button packing;
- opaque route-reference pagination;
- course detail with capability-driven destinations;
- course empty, not-found and permission-denied states;
- no raw IDs in visible text.

### Schedule
- schedule hub: today, tomorrow, bounded upcoming;
- optional course-scoped schedule context;
- schedule list/day screen, bounded to 8 events;
- canonical time/course/title/location presentation;
- event-detail action and event-detail screen;
- workspace-timezone authority wording without exposing technical timezone slugs;
- empty/error/loading states.

### Grades
- grade list grouped by canonical course title within the fetched page;
- bound of 16 grade rows;
- score/max presentation;
- published-state presentation based on the published-only backend projection;
- course-grade detail;
- no GPA, weighted total, rank or average invention.

### Announcements
- concise title-first list, bounded to 8 announcements;
- announcement detail;
- pagination;
- optional subject-bound detail route refs;
- safe-link placeholder semantics through an opaque validated route ref only;
- no URL extraction from body text.

### Personal notifications
- honest entry screen only;
- explicit separation between durable workspace announcements and personal messenger delivery;
- no locally reconstructed personal inbox/history.

### Academic presentation utilities
- workstream-owned child action IDs;
- reuse of bot-01 top-level Home/Courses/Schedule/Grades/Notifications/Retry action identifiers;
- `CallbackIntent` construction with params or short route refs;
- bounded action packing respecting bot-01/core's maximum action-row count;
- contextual Back + Home composition;
- provider-neutral error/loading state builders.

## Discovery/read references

Mandatory/project sources read:
- `AGENTS.md`
- `docs/ui-v3/FANOOS_UI_UX_DESIGN_LOCK.md`
- `docs/ui-v2/FANOOS_PRODUCT_IA.md`
- `docs/ui-v2/FANOOS_PRESENTATION_CONTRACT.md`
- `docs/ui-v2/FANOOS_CROSS_CHANNEL_MATRIX.md`
- `contracts/REGISTRY.md`
- `contracts/openapi/core-v1.yaml`
- `contracts/openapi/internal-v1.yaml`

Bot/current references read:
- `docs/ui-v2/bots/BOT_INFORMATION_ARCHITECTURE.md`
- `docs/ui-v2/bots/BOT_SCREEN_MAP.md`
- `docs/ui-v2/bots/BOT_SEMANTIC_MODEL.md`
- `docs/ui-v2/bots/BOT_BACKEND_UI_GAPS.md`
- `docs/ui-v2/bots/BOT_PRODUCT_AUDIT.md`
- `docs/ui-v2/bots/BOT_HANDOFF.md`
- `docs/ui-v2/bots/TELEGRAM_PRESENTATION.md`
- `docs/ui-v2/bots/BALE_PRESENTATION.md`
- `docs/ux-parallel/worker03_bot_semantic_presentation.md`
- `docs/ux-parallel/worker04_channel_native_presentation.md`
- `docs/ui-v2/FANOOS_BACKEND_UI_GAPS_FINAL.md`
- `packages/python/fanoos_bot/integrated_application.py`
- `packages/python/fanoos_bot/application.py`
- `packages/python/fanoos_bot/product_ui.py`
- `packages/python/fanoos_bot/api.py`
- `packages/python/fanoos_bot/models.py`
- `packages/python/fanoos_bot/presentation.py`
- `packages/python/fanoos_bot/formatting.py`

Backend projection/service references read:
- `apps/platform/src/Http/InternalApiKernel.php`
- `apps/platform/src/Core/BotReadProjectionService.php`

Parallel core dependency read after it published source:
- `rebuild-v3/bot-01-core-shell` remained based on the same `PARALLEL_REBUILD_BASE_SHA` recorded above.
- Observed bot-01 branch commit during reconciliation: `faead96a4bedca34151562c81c712ae42b2a7693`.
- `docs/ui-v3/workstreams/bot-01-core-shell/WORKSTREAM_HANDOFF.md` records the same base SHA.
- `packages/python/fanoos_bot/ui_v3/core/__init__.py`
- `packages/python/fanoos_bot/ui_v3/core/contracts.py`
- `packages/python/fanoos_bot/ui_v3/core/actions.py`

No merge/rebase from bot-01 was performed. Its source was read only to align bot-02 against the concrete locked semantic contract while preserving this branch's frozen base.

## Reuse summary

Reused:
- linked subject + active workspace authorization boundaries;
- canonical backend projection semantics;
- current Persian human number/score/time formatting helpers;
- workspace timezone authority;
- bounded opaque pagination correlation;
- V2 course-first IA and contextual navigation ideas;
- final integrated V2's canonical course projection (not activity-derived course truth);
- published-grade semantics;
- announcement-vs-personal-notification distinction;
- bot-01 provider-neutral semantic contracts and top-level action IDs.

Rebuilt:
- academic hierarchy and screen composition;
- bounded density and action packing;
- course capability presentation;
- schedule hub/list/detail composition;
- grade grouping/course detail;
- title-first announcement list/detail flow;
- user-facing empty/error/loading wording;
- personal-notification entry behavior under the V3 Design Lock.

Explicitly not reused:
- the older V2 fallback that inferred the course catalog by deduplicating schedule/grade/resource activity.

## Concrete bot-01/core dependency

Bot-02 now targets the published bot-01 semantic API:
- `Action(identifier, label, intent | url, destructive)`
- `ActionRow(actions)`
- `CallbackIntent(name, params, route_ref)`
- `Breadcrumb(label, intent)`
- `Context(label, value, detail)`
- `Fact(label, value)`
- `ListItem(title, description, meta, marker)`
- `Pagination(page, total_pages, previous, next, label)`
- `Section(title, body, facts, items)`
- `Screen(identifier, title, intro, severity, context, breadcrumb, sections, action_rows, pagination, footer, ...)`
- `Severity`

Core action identifiers reused directly by `academic/actions.py`:
- `ACTION_HOME`
- `ACTION_COURSES`
- `ACTION_SCHEDULE`
- `ACTION_GRADES`
- `ACTION_NOTIFICATIONS`
- `ACTION_RETRY`

Core callback intent names for those destinations are preserved (`home`, `courses`, `schedule`, `grades`, `notifications`, `retry`).

Academic source also imports stable current formatting helpers from `packages/python/fanoos_bot/formatting.py`.

## Canonical projections consumed/expected

### Course catalog

At the frozen base there is no standalone internal bot course route. `BotReadProjectionService::schedule()` includes a `courses` array generated by the authorized canonical `courses()` projection.

Observed course fields:
- `course_id`
- `course_code`
- `course_title`
- `credit_value`
- course `status`
- `offering_id`
- `section_key`
- `offering_status`
- `term_id`
- `term_key`
- `term_name`

Authorization remains backend `academic.view` for the linked user/workspace.

### Schedule

`POST /api/internal/v1/academics/schedule`  
Service action: `academic.schedule.read`

Observed response facts:
- canonical IANA `timezone` for formatting authority;
- local `from_date`, `to_date`;
- event `id`, `event_type`, `title`, `starts_at`, `ends_at`, `location_text`, `status`;
- `offering_id`, `course_id`, `course_code`, `course_title`;
- `next_cursor`;
- attached bounded canonical `courses` projection.

The backend resolves date-only request bounds in `tenant_workspaces.timezone_name`, queries storage in UTC and returns localized event instants. The V3 student copy does not expose the technical timezone slug.

### Grades

`POST /api/internal/v1/academics/grades`  
Service action: `grade.self.read`

Observed fields:
- `result_id`
- `course_id`, `course_code`, `course_title`
- `gradebook_id`, `gradebook_title`
- `item_id`, `item_key`, `item_title`
- `max_score`, `score`, `updated_at`
- `next_cursor`

The backend query limits output to the linked student's authorized active/completed enrollment and published gradebook/result rows.

### Announcements

`POST /api/internal/v1/announcements/list`  
Service action: `announcement.read`

Observed fields:
- message `id`
- `title`
- `body`
- `published_at`
- recipient `status`
- `read_at`
- `next_cursor`

This is workspace-level. No canonical course/offering binding or trusted link field exists at the frozen base.

### Personal notification delivery

Existing worker routes:
- `/api/internal/v1/notifications/project`
- `/api/internal/v1/notifications/claim`
- `/api/internal/v1/notifications/receipt`

They are delivery/lease/receipt contracts, not a user-authorized durable inbox projection.

## Course-context action IDs

| Destination | Identifier |
| --- | --- |
| Top-level course list | `core.courses` |
| Open course | `academic.course.open` |
| Course schedule | `academic.course.schedule` |
| Course resources | `academic.course.resources` |
| Course assessments | `academic.course.assessments` |
| Course grades | `academic.course.grades` |
| Course announcements | `academic.course.announcements` |
| Course sessions | `academic.course.sessions` |
| Schedule event detail | `academic.schedule.event.open` |
| Course grade detail | `academic.grades.course.open` |

Canonical IDs remain hidden callback params. Route refs remain presentation correlation only. Every domain handler must freshly authorize/re-read backend state.

## Timezone assumptions

- `tenant_workspaces.timezone_name` is the calendar/time authority.
- Schedule date parameters represent workspace-local calendar dates.
- Schedule instants are formatted with the canonical timezone supplied by the projection.
- Server host, Telegram/Bale runtime and user device timezone are never academic truth.
- Student-facing copy states that times use the educational workspace timezone without exposing a technical IANA identifier.

## Announcement vs notification distinction

- `📢 اطلاعیه‌ها`: durable, user-authorized workspace announcement projection; list/detail is valid.
- `🔔 اعلان‌های شخصی`: message-delivery concept; current worker receipts are transport state, not history.
- Bot-02 therefore provides an entry/explanation plus a path to announcements, but no fabricated personal inbox.

## INTEGRATION_GAPS

### `ACADEMIC-GAP-01` — directly paginated course catalog

The canonical backend `courses()` projection exists, but internal-v1 exposes it only nested in schedule with a current limit of 100 and no `courses_next_cursor` in that response.

Effect:
- ordinary workspaces can render canonical courses correctly;
- course reachability above that attached bounded set is not guaranteed.

Do not restore activity-derived course truth. Preferred future contract: expose the existing authorized course projection directly or surface its opaque cursor additively.

### `ACADEMIC-GAP-02` — native course filters

Schedule and grades contain canonical `course_id`, but internal routes do not accept a course filter.

Effect:
- course schedule/grade detail requires bounded authorized fetch + presentation filtering;
- very high-volume pagination completeness is not guaranteed.

Preferred future contract: additive server-side `course_id` filter.

### `ACADEMIC-GAP-03` — bot-native assessments

No bot-safe assessment catalog/detail/attempt/result projection exists in internal-v1.

Effect:
- `academic.course.assessments` is defined for future integration but omitted from the default course detail;
- no answer/scoring state is invented locally.

### `ACADEMIC-GAP-04` — course-scoped announcements

Current announcements have no course/offering binding.

Effect:
- `academic.course.announcements` is defined but omitted at the frozen base;
- workspace announcements remain valid;
- title/body text must never be parsed to infer a course.

### `ACADEMIC-GAP-05` — durable personal notification history

No subject/workspace-authorized history/list projection with cursor/read state exists.

Effect:
- no personal history list is rendered;
- delivery receipts are never reconstructed into inbox truth.

### `ACADEMIC-GAP-06` — standalone event detail endpoint

Schedule rows contain event IDs and detail facts, but internal-v1 has no detail-by-ID route.

Integration behavior:
- prefer a short provider+subject-bound route ref that stores only range/page correlation;
- on open, re-fetch the authorized schedule range/page and locate the canonical event ID;
- never persist event facts as local domain truth.

### `ACADEMIC-GAP-07` — announcement detail/safe-link endpoint

Announcement list rows include body facts, but no dedicated detail route or trusted link field exists.

Integration behavior:
- prefer a short provider+subject-bound route ref with cursor/page correlation and re-read on open;
- do not persist body text as local truth;
- do not extract arbitrary URLs from body;
- only show the safe-link action if a future canonical integration provides a validated route reference.

### `ACADEMIC-GAP-08` — course sessions

No bot-safe course-session list/detail projection exists in internal-v1 at the frozen base.

Effect:
- `academic.course.sessions` is defined but omitted until supported.

### `ACADEMIC-GAP-09` — cross-workstream resource destination

Course resources are canonically available, but full resource journeys belong to bot-03.

Effect:
- bot-02 owns the course-context action ID and placement only;
- merge/integration must route `academic.course.resources` into bot-03's resource flow rather than duplicate resource UI here.

## Merge-worker verification points

1. Confirm final merged bot-01 still exports the concrete semantic API and core action identifiers observed on `faead96a4bedca34151562c81c712ae42b2a7693`; if bot-01 evolves after this read, adapt bot-02 imports mechanically without changing semantics.
2. Preserve bot-01 callback intent names for shared core destinations.
3. Keep local route references short, expiring and provider+subject-bound; they are never authorization.
4. Freshly reauthorize/re-read canonical backend state before course/event/announcement/page presentation.
5. Keep assessment/course-announcement/session actions hidden until canonical support exists.
6. Connect course resources to bot-03 rather than implementing a parallel resource journey.
7. Preserve workspace timezone semantics and Persian-facing copy.
8. Do not calculate GPA/average or infer grade completeness.
9. Do not build personal notification history from delivery receipts.
10. Provider adapters may enhance rendering, but must not fork academic domain semantics between Telegram and Bale.

## Ownership drift check

All changed files are inside:
- `packages/python/fanoos_bot/ui_v3/academic/**`
- `docs/ui-v3/workstreams/bot-02-academic/**`

No V2 source, shared current entrypoint, backend contract/service, workflow, test path or another V3 workstream directory was modified.
