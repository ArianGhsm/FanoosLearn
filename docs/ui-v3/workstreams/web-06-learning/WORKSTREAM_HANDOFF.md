# FANOOS Rebuild V3 — Web 06 Learning Resources Handoff

## Workstream identity

- Design Lock ID: `FANOOS-UX-2026.09-R1`
- `PARALLEL_REBUILD_BASE_SHA`: `9e72ef32b331ad41626229c28ed24dcc43a49292`
- Branch: `rebuild-v3/web-06-learning`
- Source ownership: `apps/platform/public/assets/ui-v3/learning/**`
- Documentation ownership: `docs/ui-v3/workstreams/web-06-learning/**`
- Scope: presentation/source only; no backend, schema, central router, tests, workflow, runtime or deployment mutation.

## Files created

- `apps/platform/public/assets/ui-v3/learning/index.js`
- `apps/platform/public/assets/ui-v3/learning/learning-contract.js`
- `apps/platform/public/assets/ui-v3/learning/runtime.js`
- `apps/platform/public/assets/ui-v3/learning/library-view.js`
- `apps/platform/public/assets/ui-v3/learning/detail-view.js`
- `apps/platform/public/assets/ui-v3/learning/learning.css`
- `docs/ui-v3/workstreams/web-06-learning/WORKSTREAM_HANDOFF.md`

## Screens and components delivered

- Learning/library landing with Persian-first search, compact desktop filters and mobile bottom-sheet filters.
- Canonical course filter using the academic projection.
- Resource-type filter using the currently established Web resource vocabulary.
- Canonically ordered “تازه‌های کتابخانه” section shown only on the unfiltered library; it does not invent a recency threshold.
- All-resources list with title, course, resource type, version/update date when present, access/protection indicator and one detail action.
- Resource detail hierarchy with description, course, type, topic, presenter, version, date and format when canonically supplied.
- Protected-delivery panel covering `available`, `preparing`, `ready`, `expired`, `denied`, and `unavailable` presentation states.
- Safe empty/error/partial-course-projection/loading/retry states.
- Safe query highlighting built from text nodes and `<mark>`; no API-derived `innerHTML`.
- Course-learning embed export for Web 04: `createCourseLearningEmbed(ctx, { courseId, courseLabel })`.
- V3 module export: `moduleDefinition`.

## Current/legacy files inspected and conceptual reuse

Current FANOOS references:

- `AGENTS.md`
- `docs/DEVELOPMENT_WORKFLOW.md`
- `docs/workflow/INTEGRATION_ONLY_PATHS.md`
- `docs/ui-v3/FANOOS_UI_UX_DESIGN_LOCK.md`
- `docs/ui-v2/FANOOS_PRODUCT_IA.md`
- `docs/ui-v2/FANOOS_PRESENTATION_CONTRACT.md`
- `docs/ui-v2/FANOOS_CROSS_CHANNEL_MATRIX.md`
- all files under `docs/ui-v2/web/**`
- `docs/ui-v2/FANOOS_BACKEND_UI_GAPS_FINAL.md`
- `docs/ux-parallel/worker02_web_domain_presentation.md`
- `docs/ux-parallel/UX_GLOBAL_STABILIZATION_REPORT.md`
- `contracts/REGISTRY.md`
- `contracts/openapi/core-v1.yaml`
- `contracts/openapi/internal-v1.yaml`
- `docs/fanoos-migration/02_MODULE_AND_DATA_OWNERSHIP.md`
- `docs/fanoos-migration/02_TARGET_ARCHITECTURE.md`
- `apps/platform/public/assets/ui-v2/product-learning.js`
- `apps/platform/public/assets/ui-v2/product-core.js`
- `apps/platform/public/assets/domain-ux.js`
- `apps/platform/public/assets/app.js`
- `apps/platform/src/Content/ContentService.php`
- `apps/platform/src/Content/ProtectedResourceAuthorizer.php`
- `apps/platform/src/Content/SecureDeliveryService.php`
- `apps/platform/src/Content/SecureObjectDownloadService.php`

Reused conceptually rather than copied: canonical filter semantics, safe DOM construction, Persian resource vocabulary, workspace-scoped request behavior, existing secure-delivery chain and course/resource relation. The V2 card-heavy visual composition was not preserved.

## Canonical endpoints/projections consumed

- `GET /api/v1/workspaces/{workspaceId}/academics`
  - used only to resolve human course labels and canonical `course_id` filter values.
- `GET /api/v1/workspaces/{workspaceId}/resources?q=&type=&course_id=&sort=newest`
  - canonical library source.
  - query semantics: non-empty `q` must contain 2–120 characters; backend searches title, description and topic.
  - current backend limit is 100 rows. V3 displays a result count only when fewer than 100 rows are returned.
- `GET /api/v1/workspaces/{workspaceId}/resources/{resourceId}`
  - authorized resource detail and detail access gate.
- `POST /api/v1/workspaces/{workspaceId}/resources/{resourceId}/deliveries` with `{channel:"web"}`
  - issues the short-lived delivery capability after current authorization.
- `POST /api/v1/workspaces/{workspaceId}/deliveries/consume`
  - reauthorizes resource/version before yielding structured content or a short-lived download capability.
- `POST /api/v1/workspaces/{workspaceId}/downloads/consume`
  - same-origin binary redemption; server revalidates current session, workspace, authorization, entitlement, exact version and object identity.

No storage path, storage key, raw object identifier, provider secret or direct object URL is rendered or persisted by this module.

## Resource presentation fields

Consumed when present:

- `id` / `resource_id` — transient API action identity only; never displayed.
- `title`
- `description`
- `type_key`
- `course_id` — filter/join key only; never displayed raw.
- optional human `course_title`, `course_code` when supplied or resolved from academics.
- `topic`
- `professor_name`
- `current_version_no`
- `updated_at`
- `visibility`
- `access_level`
- `lifecycle_status`
- `format_key`

Unknown resource type safely renders as `منبع`.

Current established type filters from V2 canonical Web vocabulary are `lecture_note`, `discipline_note`, `summary`, `cheat_sheet`, `question_bank`, `past_exam`, `flashcards`, `audio`, and `other`. The label normalizer also safely understands `video` when a canonical projection returns it, but V3 does not advertise a video filter until the canonical type vocabulary exposes it as a selectable type.

## Access-state mapping

Catalog metadata is deliberately descriptive, not entitlement authority:

- `access_level=entitled` or `visibility=restricted` → `دسترسی محافظت‌شده`.
- `visibility=private` → `منبع خصوصی`.
- otherwise → workspace-visible presentation.

A successful detail request establishes only that the detail request was authorized at that moment. Secure delivery is reauthorized again by the backend.

Delivery UI state is local presentation state only:

- `available`: authorized detail ready for an attempted secure delivery.
- `preparing`: issue/consume/redemption is currently pending.
- `ready`: canonical delivery operation completed.
- `expired`: short-lived capability expired/invalid; user is asked to restart from the canonical detail action.
- `denied`: 403 or canonical access/entitlement/RBAC denial.
- `unavailable`: missing/unverified/version-unavailable/object-unavailable or safe unknown delivery failure.

No visual state grants access and no local entitlement boolean is created.

## Secure-delivery integration seam

Preferred integration seam is `ctx.capabilities.learning.deliverResource({workspaceId, resourceId, channel:'web'})`, allowing the foundation/integration worker to keep all short-lived capability handling centralized.

A contract-compatible fallback is implemented against `ctx.api`:

1. issue delivery;
2. keep `delivery_token` in a local function variable only;
3. consume delivery;
4. ignore all non-presentation fields such as backend `object_id`;
5. keep `download_token` in a local function variable only;
6. redeem through a binary `ctx.api.binary` / `ctx.api.requestBinary` method;
7. pass bytes to `ctx.capabilities.learning.saveBlob`.

Tokens are never copied to module state, DOM, routes, query parameters, local/session storage or URLs.

## Search semantics

- Search is server-backed; no shadow client search index is created.
- Empty query shows the canonical newest library.
- One-character queries are not sent; the field asks for at least two characters.
- Search highlighting is presentation-only and uses normalized text nodes, never HTML injection.
- Query is forwarded to canonical `q`; server currently searches title, description and topic.
- Result count is shown only for `<100` rows because the library endpoint currently caps the response at 100 and has no independent total projection.

## Integration imports/dependencies

The integration worker must:

- load `learning.css` once through the V3 asset/module registry;
- register `moduleDefinition` with the shared V3 router/shell;
- supply the Design Lock `ctx` object (`root`, `api`, `state`, `navigate`, `format`, `ui`, `capabilities`, `signal`);
- ensure `ctx.state` exposes the active canonical workspace identifier;
- provide a session/CSRF-aware JSON `ctx.api` adapter for POST mutations;
- provide either `ctx.capabilities.learning.deliverResource` OR the documented binary API + `saveBlob` seam;
- keep central routing and CSRF/session behavior outside this worker directory.

Web 04 can embed course resources without duplicating library truth by mounting `createCourseLearningEmbed` with the course's canonical ID and human label.

## INTEGRATION_GAPS

1. **Foundation API adapter shape not yet physically available at this parallel base.** The Design Lock defines `ctx.api` semantically but the web-01 implementation was not yet present for concrete import/type validation. Integration must bind the small adapter seam (`function`, `.request`, `.get/.post`, and for bytes `.binary/.requestBinary`) to the final foundation API client.
2. **Binary save/open hook must be wired by foundation/integration.** Source code deliberately does not build a raw storage/object URL. Integration should provide `ctx.capabilities.learning.saveBlob` or central `deliverResource` behavior for the authorized bytes.
3. **No authoritative total count/pagination projection.** Current library returns at most 100 rows. V3 avoids false totals and asks users to narrow the query when exactly 100 rows return. A future backend total/cursor projection is needed for complete large-library pagination.
4. **Catalog rows do not expose a trustworthy per-user entitlement decision.** V3 shows protected/restricted metadata but does not call it “available” until authorized detail/delivery succeeds. If product wants exact access badges in the list, backend must expose a safe per-resource access projection.
5. **Canonical selectable video type is not established in the current Web filter vocabulary.** Normalization supports a returned `video` type safely, but the filter remains absent until the shared type vocabulary exposes it.
6. **Structured content open behavior is foundation-owned.** Delivery consume can return structured content; V3 uses `ctx.capabilities.learning.openStructuredContent` when available. Integration must decide the shared renderer rather than this worker inventing one.

## Merge-worker assumptions to verify

- `PARALLEL_REBUILD_BASE_SHA` for every worker in this wave remains `9e72ef32b331ad41626229c28ed24dcc43a49292`.
- Shared V3 foundation tokens/classes remain compatible with the Design Lock; this module only defines `.f3-learning-*` selectors and consumes shared `--f3-*` variables with lock-compatible fallbacks.
- The final shell chooses one canonical public route for the library. This worker advertises `/learning` and compatibility `/resources`; integration should register aliases without duplicating screens.
- Current core-v1 secure-delivery/download semantics remain unchanged during integration.
