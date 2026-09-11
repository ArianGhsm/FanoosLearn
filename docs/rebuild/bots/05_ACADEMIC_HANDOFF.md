# FANOOS Bot Stage 5 — Academic Journeys Handoff

## Scope

Stage 5 exposes the canonical academic read model through compact, Persian-first
Telegram and Bale journeys. The work is local source plus deterministic tests
only: no migration, deployment, restart or live bot message was performed.

The integrated application now covers:

- courses with bounded pagination, workspace/term context and course detail;
- schedule hub, today, tomorrow, upcoming/week, course-filtered schedule and
  event detail;
- published grades grouped by course, showing score/max only and never
  inventing an average or GPA;
- announcements list/detail, including a canonical course-scoped list and
  contextual Back navigation;
- forms list/detail when a backend supplies a bot-safe projection, otherwise a
  safe handoff to the website for completion.

Every journey has Back/Home navigation. Object identifiers and backend enum
values remain in subject-bound, expiring route references or internal calls;
they are not copied into user-facing text or provider callback payloads.

## Runtime and authority

```text
Telegram/Bale update
  → BotRuntime
  → integrated BotApplication
  → canonical workspace-scoped read projection
  → shared academic Screen + provider-neutral intent
  → TelegramV3Renderer or BaleV3Renderer
```

The application re-checks the linked subject and selected workspace before each
academic read and before resolving a route. The backend remains authoritative
for workspace membership, course visibility, schedule, grades, announcement
recipients and form state. The bot keeps only bounded presentation route
correlation in `LocalState`; it does not create an academic shadow database.

Courses continue to use the already-authorized `courses` projection nested in
the schedule read because internal-v1 has no separate course-list action. The
internal announcements read gained one additive optional `course_id` filter;
the SQL join and workspace authorization remain canonical. The Python API
client sends that field only when a course-scoped journey requests it.

## Journey notes

### Courses

`academic.course.list` displays canonical title, code, term and workspace
context. Page navigation uses opaque refs. `academic.course.detail` advertises
schedule, resources, grades and announcements. A foreign, stale or malformed
course ref resolves to the safe unavailable screen.

### Schedule

Today/tomorrow use the workspace timezone returned by the schedule projection.
Upcoming/week and course-filtered pages re-fetch canonical data for each page;
event buttons carry short subject-bound refs and event detail re-fetches the
event before display. The UI says that time is based on the educational
workspace timezone without exposing a raw timezone slug.

### Grades

The list is grouped by course and renders `score از max_score`. Rows with an
explicit non-published state are filtered defensively; the production
projection already returns only published grade results. No local average,
GPA or inferred score is calculated.

### Announcements

Global and course-scoped lists show bounded title/body previews and publish time.
Detail is re-read from the canonical projection and supports an HTTPS website
link only when one is supplied. Course scope is fail-closed for a legacy fake
backend that cannot filter or establish the course relation.

### Forms

The presentation layer maps known form states to human Persian labels and
normalizes boolean/deadline facts. Form submission stays on the website. The
current internal-v1 bot contract does not define a bot-safe forms projection, so
the live client renders an explanatory screen plus the configured website
handoff until a reviewed additive contract is introduced.

## Contracts and documentation

- `contracts/openapi/internal-v1.yaml` and `contracts/REGISTRY.md` document the
  optional course filter on `announcement.read`.
- `docs/fanoos-migration/07_PLATFORM_HANDOFF_TO_BOTS.md` and
  `docs/fanoos-migration/07_BOT_INTEGRATION_HANDOFF.md` record the same
  canonical boundary.
- V3 action routing is registered in
  `packages/python/fanoos_bot/ui_v3/wiring.py`; no provider-specific academic
  business logic was added.

## Validation

The new `tests/ux-v3/test_bot_academic_journeys.py` exercises six deterministic
journeys: opaque course/event/announcement routes, workspace denial, timezone
copy, published-only grades and pagination, course-scoped announcements,
human form status plus web fallback, long Persian/Unicode text, and Telegram/
Bale semantic parity. Existing suites remain regression gates:

- `tests/bots`: 78 tests;
- `tests/workers`: 13 tests;
- `tests/ux-v2/bots`: 25 tests;
- `tests/ux-v3`: 40 tests (including Stage 5).

Live provider limits, real backend data and form submission remain deployment-
stage checks. This handoff intentionally stops before deployment.

## Next handoff

Bot Stage 6 can build on these academic routes for content/library journeys.
Keep both channels over the same canonical backend and semantic screen contract,
preserve opaque subject-bound routes and Back/Home, and introduce any new read
surface through an additive contract with authorization and cross-workspace
tests.
