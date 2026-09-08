# Worker 02 — Web Domain Presentation / Localization / Interaction States

## Baseline and ownership

- Repository: `ArianGhsm/FanoosLearn`
- Parallel base SHA: `fef86a4adcad99cb75327582750dd4cd2df88dea`
- Branch: `ux/02-web-domain-presentation`
- Only Worker 02 owned paths were changed. `index.php`, `app.css`, backend/API contracts, database, workflows and ops paths were read-only.
- Read-only UX references: `VoiceMatnAIBot/docs/PROCESSING_UX.md`, `VoiceMatnAIBot/docs/TELEGRAM_RICH_UI.md`, current FANOOS platform projections, and legacy Dentistry presentation patterns where discoverable. No runtime/state/branding was copied.

## Renderer architecture

`domain-ux.js` is a dependency-free presentation module loaded by `app.js`. It exposes dedicated renderers for:

- `schedule`
- `grades`
- `announcements`
- `academics`
- `resources`
- `assessments`
- `forms`
- `orders`
- `search`

The generic `renderRows()` remains only as a development fallback. It uses a strict display allowlist (`title`, `course_title`, `item_title`, `product_name_snapshot`, `description`, `body`, `message`, `status`) and never iterates arbitrary object keys.

## Safe presentation utilities

- DOM nodes are created with `document.createElement()` and user/API strings are assigned with `textContent`; there is no renderer `innerHTML` path.
- `normalizeText()` applies NFKC, Persian Yeh/Kaf normalization, removes bidi-control injection characters, collapses whitespace, and handles null/undefined as empty.
- `safeTruncate()` bounds long presentation text without injecting markup.
- `Intl.NumberFormat('fa-IR')` renders Persian digits.
- `Intl.DateTimeFormat('fa-IR')` renders Persian date/time. Explicit offsets/timezones are honored by the browser. Naive backend datetimes are treated as wall-clock values because the current web projections do not expose a workspace timezone field; no timezone is invented client-side.
- `formatMoney()` renders `amount_minor` using the returned currency. Current contract/test currency `IRR` is rendered as ریال; `IRT` is supported as تومان; unknown currencies receive a non-technical safe fallback.
- Technical human-useful tokens such as course codes use `<bdi dir="ltr">` plus `unicode-bidi:isolate`.

## Localization matrix

Statuses include:

`pending`, `payment_pending`, `paid`, `failed`, `canceled`, `cancelled`, `active`, `expired`, `published`, `hidden`, `draft`, `archived`, `open`, `closed`, `read`, `delivered`, `completed`, `in_progress`, `review`, `approved`, `rejected`, `private`, `workspace`, `entitled`, `restricted`, `redirected`, `created`, `verifying`, `refunded`, `telegram`, `bale`.

Unknown status codes render only `وضعیت نامشخص`; the raw code is not shown.

Resource type labels cover booklet/lecture note/note, summary, DentNote/discipline note, question bank, past exam/questions, flashcard, audio, video and generic educational files. Assessment labels cover practice, mock exam, past exam, quiz and exam.

## Domain-specific field use

### Schedule
Uses current `title/course_title`, `starts_at`, `ends_at`, `location_text`, `event_type`, `course_code`, `status`. Rows are grouped by date. Raw labels such as `starts_at` are never shown.

### Grades
Uses `course_title`, `course_code`, `gradebook_title`, `item_title`, `score`, `max_score`, `updated_at`. Current endpoint already returns published results only, so rows without an explicit status are labeled `منتشرشده`. Gradebook/item IDs are never shown.

### Announcements
Uses `title`, bounded `body`, `published_at`, recipient `status/read_at`. A CTA is created only when a presentation-ready `safe_url/action_url/url` exists and passes URL validation. Current raw `data_json` is never parsed or dumped.

### Academics
Uses current course/session projection fields: `title`, `course_code`, `term_name`, `section_key`, `session_title`, `sequence_no`, `starts_at`, session/offering status. Internal IDs are not rendered.

### Resources
Uses `title`, `description`, `type_key`, `topic`, `professor_name`, `current_version_no`, `access_level/visibility`, lifecycle status. Current `course_id/term_id/session_id` are not displayed because they are identifiers without human labels.

### Assessments
Uses `title`, `assessment_kind`, `current_version_no`, `requires_entitlement`, `max_attempts`; future presentation-ready attempt/progress fields are supported if returned. No scoring or answer logic is client-owned.

### Forms
Uses `title`, `description`, `opens_at`, `closes_at`, `allow_multiple`; current `schema_json`/version IDs are never rendered.

### Orders
Uses `product_name_snapshot`, `total_minor/amount_minor`, `currency`, order `status`, `created_at`, `paid_at`, and entitlement status only if explicitly returned. `provider_key`, provider references and order/payment IDs are hidden.

### Search
Uses `source_type`, `title`, `updated_at` and optional presentation-ready context. `source_id`, raw route internals and object dumps are hidden.

## Interaction states

- Loading, empty and error states are inline and Persian.
- GET view/search errors expose a safe retry button only for the idempotent GET.
- Technical backend messages are not rendered. The API wrapper retains only bounded `code/status` for safe client decisions.
- No normal-flow `alert()` remains.
- Login, logout and workspace-selection mutating actions disable their control while pending, preventing duplicate mutating requests.
- Workspace selection is persisted only after the canonical POST succeeds.
- Active view and search query are session-persisted as presentation state only; cache/session state is never entitlement/payment/membership truth.
- A monotonically increasing request serial prevents stale GET responses from overwriting a newer view/search.

## Domain CSS

`domain-ux.css` owns only domain presentation classes:

- `.domain-card*`
- `.domain-chip*`
- `.domain-token`
- `.domain-meta*`
- `.domain-state*`
- `.domain-retry`, `.domain-link`
- `.schedule-group*`, `.schedule-card`
- `.grade-card`, `.grade-score`
- `.announcement-card`, `.resource-card`, `.assessment-card`, `.order-card`, `.order-amount`

It consumes Worker 1/global variables such as `--line`, `--muted`, `--ink`, `--card`, `--green` and does not redefine palette, typography or shell layout. `app.js` deterministically injects `/assets/domain-ux.css` and `/assets/domain-ux.js` because `index.php` is outside Worker 02 ownership.

## Worker 1 shell assumptions

Integration should preserve these existing IDs/data hooks or coordinate any Worker 1 rename before merge: `#result-list`, `#view-title`, `#workspace-select`, `#workspace-path`, `#search-form`, `#login-form`, `#login-message`, `#today`, `[data-view]`, `#dashboard`, `#login-panel`, `#logout`, `#workspace-picker`, `#greeting`.

`domain-ux.css` assumes the shell continues defining the existing CSS variables. Integration may replace dynamic stylesheet injection with a static `<link>` after Worker 1 is merged, but should keep only one effective `domain-ux.css` load.

## Backend gaps / deliberate omissions

No backend changes were made. Current web projections do not provide all potentially useful product labels:

1. Schedule does not expose instructor/professor; UI shows it only if a future existing contract field provides it.
2. Resource catalog returns `course_id/term_id/session_id` but not the corresponding human titles; Worker 02 intentionally hides those IDs rather than leaking them.
3. Assessment catalog returns `course_id` without course title and does not return per-user attempt/progress; UI does not infer them.
4. Order history does not return canonical entitlement state; UI shows entitlement only if explicitly returned, and never derives it from `paid`.
5. Web schedule projection has no explicit workspace timezone metadata. Naive datetimes are not client-shifted to an invented timezone.
6. Announcement `data_json` is raw structured data, not a presentation-safe CTA contract; it is not parsed for links.

These are presentation gaps only; integration should not patch them client-side with business truth.

## Tests

Self-contained Node check: `node tests/ux/worker02_domain_ux_test.js`.

Coverage includes status/resource/assessment localization, Persian numbers/date, IRR money, null/undefined handling, safe text behavior, no `innerHTML` assignment, no normal `alert()`, all nine dedicated renderers, raw-field dump guard, loading/empty/error Persian states, and mixed LTR token isolation.

## Integration notes

- Merge from the exact parallel base; do not rebase this worker onto later `main` before integration.
- `app.js` is the principal integration-conflict surface. Preserve Worker 02 renderer selection, safe API-error handling, duplicate-submit guards, request-serial stale-response guard and session-only view state when reconciling other workers.
- If Worker 1 statically links `domain-ux.css`/`domain-ux.js`, remove only the redundant dynamic loader lines, not the module itself.
- No deploy or merge is part of this worker.
