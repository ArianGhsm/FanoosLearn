# WORKSTREAM HANDOFF — BOT 02 ACADEMIC JOURNEYS

## Identity

- Design Lock ID: `FANOOS-UX-2026.09-R1`
- `PARALLEL_REBUILD_BASE_SHA`: `9e72ef32b331ad41626229c28ed24dcc43a49292`
- Branch: `rebuild-v3/bot-02-academic`
- Workstream: `FANOOS REBUILD V3 — TELEGRAM + BALE BOT / 02_ACADEMIC_JOURNEYS`
- Scope: source-only provider-neutral bot academic presentation. No deploy, server access, production data access, tests, CI changes, PR or merge were part of this workstream.

## Owned paths

Source:
- `packages/python/fanoos_bot/ui_v3/academic/**`

Documentation:
- `docs/ui-v3/workstreams/bot-02-academic/**`

## Files created

- `packages/python/fanoos_bot/ui_v3/academic/__init__.py`
- `packages/python/fanoos_bot/ui_v3/academic/actions.py`
- `packages/python/fanoos_bot/ui_v3/academic/screens.py`
- `docs/ui-v3/workstreams/bot-02-academic/screen_specs.md`
- `docs/ui-v3/workstreams/bot-02-academic/WORKSTREAM_HANDOFF.md`

## Delivered screens/components

### Courses
- bounded course list (`10` per rendered page);
- canonical course deduplication by hidden `course_id` for duplicate offering rows;
- optional workspace and canonical term context;
- short-label two-column action packing;
- opaque presentation pagination actions;
- course detail with capability-driven destinations;
- explicit course empty state;
- explicit not-found and permission-denied state;
- no raw course IDs in visible copy.

### Schedule
- schedule hub: today, tomorrow, bounded upcoming;
- optional course-scoped hub/context;
- bounded day/upcoming list (`12` rows);
- event title/time/course/location presentation;
- event detail screen;
- explicit workspace-timezone footer;
- empty/error/loading states;
- no host/device-timezone authority.

### Grades
- bounded grade page (`16` rows);
- grouping by course within the fetched canonical page;
- score/max rendering;
- explicit `منتشرشده` state based on the backend projection contract, which only returns published gradebook/result rows;
- course-grade detail;
- no GPA, weighted total, class average or ranking invention.

### Announcements
- concise bounded list (`8` rows);
- detail screen with full bounded body and publication/read context;
- pagination;
- safe-link placeholder semantics through an opaque `safe_link_ref` only;
- no URL extraction from announcement body and no raw link/provider token assumption.

### Personal notifications
- entry screen only;
- explicit distinction between workspace announcements and personal push delivery;
- no durable history/inbox UI because no canonical list/history projection exists.

### Shared academic presentation utilities
- academic action identifier vocabulary;
- course-context action map;
- two-column button packing for short peer actions;
- contextual back + Home composition;
- bounded pagination metadata;
- provider-neutral loading/error presentation.

## Current files inspected and reused conceptually

Repository rules/contracts:
- `AGENTS.md`
- `docs/ui-v3/FANOOS_UI_UX_DESIGN_LOCK.md`
- `docs/ui-v2/FANOOS_PRODUCT_IA.md`
- `docs/ui-v2/FANOOS_PRESENTATION_CONTRACT.md`
- `docs/ui-v2/FANOOS_CROSS_CHANNEL_MATRIX.md`
- `contracts/REGISTRY.md`
- `contracts/openapi/core-v1.yaml`
- `contracts/openapi/internal-v1.yaml`

Bot V2/current presentation references:
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

Current implementation inspected:
- `packages/python/fanoos_bot/integrated_application.py`
- `packages/python/fanoos_bot/application.py`
- `packages/python/fanoos_bot/product_ui.py`
- `packages/python/fanoos_bot/api.py`
- `packages/python/fanoos_bot/models.py`
- `packages/python/fanoos_bot/presentation.py`
- `packages/python/fanoos_bot/formatting.py`

Current backend projection/service inspected:
- `apps/platform/src/Http/InternalApiKernel.php`
- `apps/platform/src/Core/BotReadProjectionService.php`

### Reuse decisions

Reused:
- canonical linked-subject/workspace reauthorization model;
- current Persian number/time formatting helpers;
- canonical workspace timezone semantics;
- bounded cursor-pagination behavior;
- course-first IA and contextual back/home behavior;
- V2 distinction between announcements and personal notifications;
- published-grade semantics;
- current final integration's decision to consume the canonical course projection instead of rebuilding course truth from schedule/grade/resource activity;
- provider-neutral semantic screen concept and provider-specific rendering separation.

Rebuilt:
- academic screen composition;
- list density and action packing;
- course detail capability presentation;
- schedule information hierarchy and event detail surface;
- grade grouping/course detail composition;
- title-first announcement list/detail split;
- zero/error/loading language;
- notification entry semantics under the V3 Design Lock.

Not reused:
- old V2 course-index fallback that deduplicated course facts from schedule/grades/resources. Final integrated V2 already replaced that with the canonical backend course projection, so V3 does not preserve the weaker fallback.

## Canonical backend projections expected

### Course catalog

There is no standalone internal bot course endpoint at the frozen base. `BotReadProjectionService::schedule()` returns a `courses` field built by the authorized canonical `courses()` projection.

Canonical course fields observed:
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

Authorization is server-side `academic.view` through the linked active workspace.

### Schedule

`POST /api/internal/v1/academics/schedule`

Service action:
- `academic.schedule.read`

Observed response facts:
- authoritative IANA `timezone`;
- local `from_date` / `to_date`;
- event `id`, `event_type`, `title`, `starts_at`, `ends_at`, `location_text`, `status`;
- `offering_id`, `course_id`, `course_code`, `course_title`;
- `next_cursor`;
- bounded canonical `courses` projection.

The backend resolves requested local calendar boundaries using `tenant_workspaces.timezone_name`, queries UTC storage, then returns localized ISO instants. Maximum range is 31 days.

### Grades

`POST /api/internal/v1/academics/grades`

Service action:
- `grade.self.read`

Observed fields:
- `result_id`
- `course_id`, `course_code`, `course_title`
- `gradebook_id`, `gradebook_title`
- `item_id`, `item_key`, `item_title`
- `max_score`, `score`, `updated_at`
- `next_cursor`

The backend query already restricts results to the linked user's authorized active/completed enrollment and published gradebook/result rows.

### Announcements

`POST /api/internal/v1/announcements/list`

Service action:
- `announcement.read`

Observed fields:
- message `id`
- `title`
- `body`
- `published_at`
- recipient `status`
- `read_at`
- `next_cursor`

The current projection is workspace-level and contains no canonical course/offering binding and no trusted link field.

### Personal notification delivery

Existing routes:
- `/api/internal/v1/notifications/project`
- `/api/internal/v1/notifications/claim`
- `/api/internal/v1/notifications/receipt`

These are worker projection/lease/receipt contracts. They are not a user-authorized durable inbox/list/history projection and are not consumed as one by this workstream.

## Course-context action IDs

| Destination | Semantic action ID |
| --- | --- |
| Course list | `academic.courses` |
| Course open | `academic.course.open` |
| Course schedule | `academic.course.schedule` |
| Course resources | `academic.course.resources` |
| Course assessments | `academic.course.assessments` |
| Course grades | `academic.course.grades` |
| Course announcements | `academic.course.announcements` |
| Course sessions | `academic.course.sessions` |
| Schedule event detail | `academic.schedule.event.open` |
| Course-grade detail | `academic.grades.course.open` |

Raw canonical IDs are action payload values only. Integration must reauthorize the linked subject/workspace before every domain read.

## Integration imports/dependencies

Academic source imports V3 semantic contracts from:
- `packages/python/fanoos_bot/ui_v3/core/**` via `from ..core import ...`

Expected semantic exports from bot-01/core, as named by the Design Lock:
- `Screen`
- `Section`
- `Fact`
- `ListItem`
- `Action`
- `ActionRow`
- `Pagination`
- `Severity`
- `Context`

No local duplicate core models or compatibility dataclasses were created.

Academic source also reuses stable existing formatting helpers from:
- `packages/python/fanoos_bot/formatting.py`

Specifically:
- `format_human_number`
- `format_score`
- `format_time`
- `format_datetime`
- `truncate_text`
- `is_uuid`

### Core constructor assumption for merge worker

At the moment this workstream branch was written, `rebuild-v3/bot-01-core-shell` existed at the same parallel base SHA but had not yet published its source commit. Therefore bot-02 necessarily targets the semantic contract names from the Design Lock and uses the following constructor shape assumptions:

- `Action(id, label, payload)`
- `ActionRow(actions)`
- `Context(breadcrumb)`
- `Fact(label, value)`
- `ListItem(title, subtitle, facts, action)`
- `Pagination(label, previous, next)`
- `Section(title, body, facts, items)`
- `Screen(id, title, context, intro, sections, action_rows, pagination, severity, footer)`
- `Severity.INFO`, `.WARNING`, `.ERROR`

If bot-01 chooses different field names while preserving the locked semantics, the merge worker must adapt these constructor calls only. Do not introduce a second semantic model and do not change academic action meanings to resolve a naming mismatch.

## Timezone assumptions

- `tenant_workspaces.timezone_name` is the only calendar/timezone authority.
- Schedule request dates represent workspace-local calendar dates.
- Schedule response instants are expected to be canonical localized ISO values from the backend plus the IANA timezone name.
- Academic screens may format those instants for humans but must not derive academic day boundaries from server host time or messenger/device time.
- If timezone metadata is absent, the screen uses generic wording (`منطقه زمانی فضای آموزشی`) rather than claiming UTC or another guessed zone.

## Announcement vs notification distinction

- `📢 اطلاعیه‌ها`: durable workspace broadcast projection currently available through `/announcements/list`; list/detail screens are valid.
- `🔔 اعلان‌های شخصی`: delivery/action concept. Existing notification worker projection/claim/receipt state is transport state, not durable inbox history.
- Bot-02 exposes an honest notification entry screen and a path to announcements, but no personal history list.

## INTEGRATION_GAPS

### `ACADEMIC-GAP-01` — independent paginated course catalog for bots

The canonical `courses()` backend projection exists, but the frozen internal API exposes it only nested inside a schedule response as `courses`, currently requested with limit `100` and without a `courses_next_cursor` in that response.

Effect:
- V3 can render canonical courses correctly for ordinary workspaces;
- a workspace with more than the attached bounded course set cannot guarantee reachability of every course through bot pagination.

Do not restore the old activity-derived course fallback. Preferred future integration: expose the existing authorized course projection directly, or carry its opaque cursor through a versioned internal contract.

### `ACADEMIC-GAP-02` — server-side course filters

Schedule and grade rows contain canonical `course_id`, but internal endpoints do not accept a course filter.

Effect:
- course-specific schedule/grade screens require bounded authorized fetch + presentation filtering;
- pagination completeness is not guaranteed for very high-volume workspaces.

Do not add a local course datastore. A future additive canonical `course_id` filter is the correct scale fix.

### `ACADEMIC-GAP-03` — bot-native assessment projection

No bot-safe assessment catalog/detail/attempt/result contract exists in internal-v1.

Effect:
- `academic.course.assessments` is defined for semantic integration but must be omitted from course detail until a canonical supported destination exists (or explicitly handed off to a safe Web action by an owning integration workstream).
- no scoring/answer state is invented in bot presentation.

### `ACADEMIC-GAP-04` — course-scoped announcement binding

Announcement rows contain no canonical `course_id` or offering binding.

Effect:
- course-specific announcement action is defined but must be omitted at the frozen base;
- workspace announcements remain valid.

Do not infer course scope from title/body text.

### `ACADEMIC-GAP-05` — durable personal notification inbox/history

No subject/workspace-authorized history/list projection with read state/cursor exists.

Effect:
- only the explanatory notification entry screen is delivered;
- worker delivery receipts are not used as inbox truth.

### `ACADEMIC-GAP-06` — standalone event detail re-read

The schedule projection returns event IDs and complete bounded row facts, but internal-v1 has no standalone event-detail-by-ID endpoint.

Integration requirement:
- a detail action must carry only bounded subject-bound presentation correlation;
- on open, re-fetch the canonical schedule range/page and locate the event by canonical ID;
- do not persist event facts locally as domain truth.

A future event-detail projection would simplify this journey but is not required to render a detail screen from a freshly reauthorized schedule read.

### `ACADEMIC-GAP-07` — standalone announcement detail / safe link projection

The announcement list row already contains body/detail facts, but there is no dedicated detail-by-ID endpoint and no trusted link field.

Integration requirement:
- retain only bounded route/cursor correlation and re-fetch the canonical announcement page before detail rendering;
- do not persist announcement body as local truth;
- do not regex/extract arbitrary URLs from body text;
- show `academic.announcement.link.open` only when a future canonical integration supplies a validated opaque `safe_link_ref`.

### `ACADEMIC-GAP-08` — course sessions projection

No bot-safe course-session list/detail projection is exposed by internal-v1 at the frozen base.

Effect:
- `academic.course.sessions` is defined but omitted from course detail unless integration later provides canonical support.

### `ACADEMIC-GAP-09` — bot-01 concrete Python constructor signatures

The Design Lock fixes semantic concepts but bot-01 had not committed concrete source signatures when bot-02 implementation was created.

Effect:
- merge may require a mechanical constructor/import adaptation inside bot-02 owned source;
- no semantic or business redesign is required.

## Source assumptions the merge worker must verify

1. Confirm bot-01/core exports the locked semantic concepts and map the constructor names documented above if its concrete field names differ.
2. Keep academic action IDs stable or provide a single integration mapping; provider callback encoding belongs to bot-01/bot-04, not bot-02.
3. Route/page references must be short, opaque, expiring and bound to provider + subject; opening them must trigger fresh backend authorization/read.
4. Use the canonical `courses` projection attached to schedule; never reintroduce V2's schedule/grade/resource-derived course truth.
5. Hide assessment/course-announcement/session actions until a canonical destination exists.
6. Integrate `academic.course.resources` with bot-03's resource journey rather than duplicating learning/resource UI inside bot-02.
7. Preserve workspace timezone semantics end-to-end.
8. Do not calculate GPA/average or infer grade completeness.
9. Keep personal notification history absent until a canonical history projection exists.
10. Provider adapters may enrich rendering, but must consume these same semantic screens; no Telegram/Bale domain forks.

## Ownership drift check

All source/document writes in this workstream are confined to:
- `packages/python/fanoos_bot/ui_v3/academic/**`
- `docs/ui-v3/workstreams/bot-02-academic/**`

No existing V2 files, current entrypoints, backend services/contracts, tests, workflows or other V3 workstream directories were edited.
