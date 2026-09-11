# WEB-08 Operations / Communication / Commerce — Workstream Handoff

## Identity

- Design Lock ID: `FANOOS-UX-2026.09-R1`
- PARALLEL_REBUILD_BASE_SHA: `9e72ef32b331ad41626229c28ed24dcc43a49292`
- Branch: `rebuild-v3/web-08-operations`
- Channel: Website
- Workstream: `08_OPERATIONS_COMMUNICATION_COMMERCE`

This branch is source-only. It contains no deployment, server mutation, production data access, test creation/execution, CI/workflow change, migration, PR, merge, or backend/schema redesign.

## Owned paths

Source ownership:

- `apps/platform/public/assets/ui-v3/operations/**`

Handoff ownership:

- `docs/ui-v3/workstreams/web-08-operations/**`

Files created:

- `apps/platform/public/assets/ui-v3/operations/operations.js`
- `apps/platform/public/assets/ui-v3/operations/operations.css`
- `docs/ui-v3/workstreams/web-08-operations/WORKSTREAM_HANDOFF.md`

No existing V2 source, shared entrypoint, backend file, contract, workflow, or another V3 workstream directory was modified.

## Discovery / conceptual reuse

The following current material was inspected read-only and reused conceptually rather than copied as presentation authority:

- `AGENTS.md`
- `docs/ui-v3/FANOOS_UI_UX_DESIGN_LOCK.md`
- `docs/ui-v2/FANOOS_PRODUCT_IA.md`
- `docs/ui-v2/FANOOS_PRESENTATION_CONTRACT.md`
- `docs/ui-v2/FANOOS_CROSS_CHANNEL_MATRIX.md`
- all current `docs/ui-v2/web/**` files
- relevant `docs/ux-parallel/**` web integration/stabilization reports
- `contracts/REGISTRY.md`
- `contracts/openapi/core-v1.yaml`
- `contracts/openapi/internal-v1.yaml`
- `apps/platform/public/assets/app.js`
- `apps/platform/public/assets/ui-v2/product-communication.js`
- `apps/platform/public/assets/ui-v2/product-account.js`
- `apps/platform/src/Core/WorkspacePlatformService.php`
- `apps/platform/src/Commerce/CommerceService.php`
- `apps/platform/src/Commerce/BotCommerceService.php`
- `apps/platform/src/Entitlements/EntitlementService.php`
- `apps/platform/src/Notifications/NotificationDeliveryService.php`
- `apps/platform/src/Content/ContentService.php`
- `apps/platform/src/Http/ApiKernel.php`

Concepts preserved:

- workspace-scoped authorization and server authority;
- per-user web announcement read state;
- server-side form validation and idempotent submission key;
- payment state separated from entitlement/access state;
- no raw object/schema dump;
- no product UUID input in normal UI;
- no provider reference exposure;
- safe GET retry / mutation pending semantics;
- Persian RTL presentation and current workspace timezone formatting;
- management visibility and actions fail closed when capabilities are unavailable.

Presentation rebuilt:

- information hierarchy;
- list/detail composition;
- responsive/mobile behavior;
- loading, empty, permission, error, mutation and success states;
- management composition;
- order/payment/access timeline language;
- forms renderer/builder;
- notification-gap presentation;
- V3 scoped styling and icon treatment.

## Module integration surface

`operations.js` exports:

- `moduleDefinition`
- `ROUTES`
- `operationsCapabilityMap`
- `renderRoute`
- `statusLabel`
- `explicitAccessState`
- `createPurchaseHandoff`
- `beginPurchase`

`moduleDefinition` follows the Design Lock shape:

- `id: "operations"`
- routes: `announcements`, `notifications`, `forms`, `orders`, `management`
- More/conditional navigation metadata
- `styles: ['/assets/ui-v3/operations/operations.css']`
- `mount(ctx)` / `unmount(ctx)` with abort-safe lifecycle

The module consumes the Design Lock context fields rather than importing the current V2 shell:

- `ctx.root`
- `ctx.api`
- `ctx.state`
- `ctx.navigate`
- `ctx.format`
- `ctx.ui`
- `ctx.capabilities`

### API adapter assumption

The merge worker must map the shared V3 API client to one of these presentation-only shapes:

- `ctx.api.request(path, {method, body, signal})`, or
- `ctx.api.get(path, {signal})` / `ctx.api.post(path, body, {signal})`.

Authentication, bearer/session persistence, CSRF, workspace authorization, retries and business truth remain the shared foundation/API client's responsibility. This module does not create a second auth/session layer.

### Workspace state assumption

The shared foundation should provide the canonically selected workspace through `ctx.state`, preferably as `ctx.state.workspace = {id, name, timezone_name}`. Compatibility reads for `workspaceId`, `activeWorkspaceId`, `workspaceName`, and `workspaceTimezone` are presentation-only integration aids; they are not persisted or treated as independent truth.

## Canonical public endpoints consumed

### Announcements

- `GET /api/v1/workspaces/{workspaceId}/announcements`
- `POST /api/v1/workspaces/{workspaceId}/announcements/{announcementId}/read`
- management: `POST /api/v1/workspaces/{workspaceId}/announcements`

Current projection fields used:

- `id` internally for detail/read mutation only;
- `title`;
- `body`;
- `published_at`;
- recipient `status` / `read_at`.

Internal IDs are not displayed. `data_json` is deliberately not parsed or dumped. A future presentation-ready `safe_url`, `action_url`, or `url` field is accepted only after HTTP(S) validation; the current raw structured payload is not treated as a link contract.

### Forms

- `GET /api/v1/workspaces/{workspaceId}/forms`
- `POST /api/v1/workspaces/{workspaceId}/forms/{formId}/submissions`
- management: `POST /api/v1/workspaces/{workspaceId}/forms`

Current student projection fields used:

- `id` internally for submission only;
- `title`;
- `description` if present;
- `allow_multiple`;
- `opens_at` / `closes_at`;
- `submission_status` / `submitted_at` for the current member when a response already exists;
- current schema via `schema_json` parsed into controls, never shown as raw JSON.

Supported canonical field types are rendered:

- `text`
- `textarea`
- `number`
- `choice`
- `multi_choice`
- `date`
- `boolean`

Management form creation converts a visual field builder into the existing canonical schema contract. Stable field keys are generated internally (`field_1`, `field_2`, …); managers are not asked to type technical keys or JSON.

### Purchase / orders

- `GET /api/v1/workspaces/{workspaceId}/orders`
- integration-ready purchase hook: `POST /api/v1/workspaces/{workspaceId}/orders`

Current history fields used:

- order ID only internally for client deduplication;
- `product_name_snapshot`;
- `total_minor` / `amount_minor`;
- `currency`;
- order/payment `status`;
- `created_at`;
- `paid_at`.

`provider_key`, `provider_reference`, callback tokens and technical payment references are never displayed.

`beginPurchase(ctx, product)` exists for later canonical catalog integration. It accepts the internal ID from a server-projected product object, creates the order through the public browser endpoint, and hands only the server-returned `redirect_url` to `createPurchaseHandoff`. There is no product UUID text box or user-entered technical identifier.

### Management

- `GET /api/v1/workspaces/{workspaceId}/admin/dashboard`
- `GET /api/v1/workspaces/{workspaceId}/admin/members`
- `POST /api/v1/workspaces/{workspaceId}/admin/representatives`
- announcement/form actions above.

Content review/publish endpoints were verified to exist:

- `POST /api/v1/workspaces/{workspaceId}/resources/{resourceId}/versions/{versionId}/review`
- `POST /api/v1/workspaces/{workspaceId}/resources/{resourceId}/versions/{versionId}/publish`

They are **not** wired to active buttons in this workstream because the current public resource list does not expose the target pending `version_id`. See `INTEGRATION_GAPS`.

No Website deployment/update-server control is present.

## Announcement vs personal notification distinction

These are intentionally separate destinations and semantics.

### Announcement

A durable published workspace message with a real browser projection and per-user web recipient/read state. The module provides list, detail, metadata, safe optional link handling, unread treatment and mark-read mutation.

### Personal notification

No durable public browser inbox/history projection exists at the locked base. `NotificationDeliveryService` and internal notification claim/receipt endpoints are transport infrastructure for Telegram/Bale delivery, not a user notification-history contract.

Therefore the Website `notifications` route is an integration-ready explanatory state only. It does **not** fabricate history from `notification_channel_deliveries`, delivery receipts, bot notifications, or announcement rows.

## Commerce state model

The V3 presentation keeps three concepts distinct:

1. **Order** — created order/history record.
2. **Payment** — pending / confirmed / failed/cancelled state from the commerce order projection.
3. **Access** — active / expired / revoked only when explicitly projected.

A paid order is never converted client-side into “access active”. Current public order history does not return an entitlement status, so the detail timeline explicitly renders access as unavailable/unknown rather than guessing.

The renderer already understands explicit future `active`, `expired`, and `revoked` access states. `entitlement.granted === true` may be treated as active only when that field is explicitly supplied; `false` is not reinterpreted as expired or revoked.

`BotCommerceService` was inspected because it demonstrates that the backend can calculate per-order entitlement from `target_scope_id`, but its route is internal service-only. Website V3 intentionally does not consume or imitate that internal API.

## Capability map

Action-level management gating is fail-closed through `ctx.capabilities`.

| Surface | Canonical capability |
| --- | --- |
| Publish announcement | `notification.broadcast` |
| Manage/create form | `form.manage` |
| Read members | `membership.view` |
| Change representative role | `membership.manage` |
| Create/submit content for review | `resource.create` |
| Review content | `resource.review` |
| Publish content | `resource.publish` |
| Manage catalog | `commerce.manage_catalog` |
| Reconcile payment | `payment.reconcile` |
| Grant/revoke access | `entitlement.grant` |

The existing `/admin/dashboard` provides `management_available` and capability-filtered section counts but does not expose the actor's complete individual permission set. Therefore the merge worker must connect the Design Lock `ctx.capabilities` slot to the canonical authorization/capability projection. When an individual capability is absent/unknown, the corresponding mutation UI stays hidden.

`/admin/dashboard.sections.members` can independently prove read visibility of the member section; mutation remains gated by `membership.manage`.

## Screens / components delivered

### Communication

- announcement list;
- unread/read presentation;
- concise preview + publish time + workspace/future scope label;
- long-form announcement detail;
- validated optional link action without parsing `data_json`;
- mark-as-read mutation state;
- no-announcement / permission / recoverable error states;
- personal-notification integration-ready explanatory state.

### Forms

- active forms list;
- deadline and multiplicity metadata;
- form detail;
- typed field renderer for all current schema field types;
- safe submission with idempotency key;
- duplicate-submission message based only on canonical server error;
- no-form / malformed-presentable-schema / permission / error / mutation success-failure states;
- capability-gated visual form creator for management.

### Purchase & access

- explicit missing-catalog state;
- existing orders list;
- amount/currency presentation;
- payment status and access status displayed separately;
- order detail with semantic status timeline;
- pending / failed / paid payment states;
- active / expired / revoked / unavailable access states;
- duplicate order rows collapsed internally when the existing history join returns more than one payment-attempt row;
- server-URL payment handoff hook for later canonical catalog integration;
- no product UUID input.

### Management

- capability-gated management home;
- announcement publisher;
- form builder/creator;
- member table with localized role labels;
- representative assignment when `membership.manage` is present;
- content review/publish integration slot with honest missing-projection state;
- commerce/access management integration slot with honest missing-projection state;
- permission denied / unavailable capability states;
- no deployment/update-server controls.

### Foundation-aligned presentation

- `.f3-ops-*` CSS isolation only;
- V3 semantic design tokens with locked palette fallbacks;
- restrained border-first surfaces;
- local inline outline SVG language using currentColor; no emoji navigation;
- responsive desktop/tablet/mobile layouts down through narrow phone widths;
- mobile member cards instead of compressed desktop table columns;
- 44px control targets;
- visible focus handling;
- semantic headings/labels/fieldsets/status regions;
- `textContent` only for API/user strings; no `innerHTML` rendering;
- bidi-control removal from presented text;
- reduced-motion handling;
- no raw object dump.

## INTEGRATION_GAPS

### GAP-OPS-01 — durable personal web notification history

**Missing:** a public browser projection for the signed-in user's durable personal notification history/read state.

**Do not use instead:** Telegram/Bale delivery claims, channel deliveries, delivery receipts, or announcement history.

**Current UI:** explanatory empty/integration-ready state.

### GAP-OPS-02 — browsable commerce catalog

**Missing:** public browser endpoint/projection listing purchasable products with presentation-safe title/description/current amount/currency/availability and an internal purchase handle.

**Current UI:** missing-catalog explanatory state + order history only. No UUID input.

**Integration slot:** once a canonical catalog exists, pass projected product objects to `beginPurchase`; the technical ID remains internal.

### GAP-OPS-03 — public order-detail access projection

**Missing:** public Website order/detail projection that returns entitlement/access state independently of payment state.

`CommerceService::history()` returns order/payment data but not target scope or entitlement state. `BotCommerceService` has richer internal-only data and must not be used by Website.

**Current UI:** payment remains authoritative; access renders unavailable unless an explicit future access field is returned.

### GAP-OPS-04 — resumable pending-payment handoff from order history

**Missing:** order history/detail does not expose a safe current payment handoff URL or a presentation-safe action handle. It also does not expose `attempt_id`, so the existing reconcile endpoint cannot be used from a normal student's order row.

**Current UI:** no fake “continue payment” or reconcile action on historical orders.

### GAP-OPS-05 — full form submission history/export

**Missing:** a full self-submission history and manager submission/export projection. The open-form projection now includes the current user's latest submitted state so one-response forms are visibly completed and cannot be re-entered from the Website.

**Current UI:** shows `پاسخ ثبت شده` and the canonical submitted timestamp; multi-response forms remain open. Export/analytics is not invented because no authorized public endpoint exists.

### GAP-OPS-06 — full form management lifecycle

**Missing:** manager projection for draft/open/closed/all forms, editing, closing/reopening, description/deadline mutation and submission results/analytics.

**Current management:** creates a form with the existing `{title, schema, open}` contract only. Deadline/description controls are not invented.

### GAP-OPS-07 — announcement lifecycle beyond validated course scope

**Missing:** draft lifecycle, edit or delete operation, and a manager course picker. The public projection now supports validated course-scoped announcements through `data_json.course_id` and `GET /announcements?course_id=…`.

**Current UI:** course detail renders the scoped projection when a canonical course binding exists; workspace publishing remains immediate and the current management composer does not guess a course scope.

### GAP-OPS-08 — content review queue target-version projection

**Existing actions:** review and publish endpoints are real and capability-protected.

**Missing:** the public manager list does not expose the pending target `version_id`. `ContentService::library()` exposes resource lifecycle/current published version number, while review/publish mutations require an exact `versionId`.

**Current UI:** capability-aware integration slot only; no manual UUID input and no guessed version target.

### GAP-OPS-09 — action-level capability projection integration

The Design Lock provides `ctx.capabilities`, but the locked public `/admin/dashboard` returns only `management_available` plus permission-filtered section counts rather than the full actor permission set.

**Merge requirement:** connect `ctx.capabilities` to canonical authorization data. Do not infer `notification.broadcast`, `resource.review`, `resource.publish`, payment, catalog or entitlement permissions from `management_available=true`.

### GAP-OPS-10 — commerce/access manager projections

Management permissions/endpoints exist for catalog/payment/entitlement operations, but the Website lacks safe list/detail projections needed to select a catalog item, payment attempt or entitlement grant without asking the operator for raw IDs.

**Current UI:** an explanatory integration slot only. No technical-ID forms are created.

## Merge-worker verification checklist

Before wiring this module into the shared V3 shell, verify:

- the shell route registry's exact route object/string shape and map these module route IDs without changing their domain semantics;
- `ctx.api` uses the shared authenticated browser client and preserves canonical CSRF/session rules;
- `ctx.state.workspace` is sourced from the successful canonical workspace selection, not browser-only persistence;
- `ctx.capabilities` is authoritative and workspace-scoped;
- the shell loads `operations.css` exactly once;
- the shell supplies the current workspace timezone to `ctx.format` or `ctx.state.workspace.timezone_name`;
- navigation places announcements/forms/orders/personal-notification under the intended More/secondary hierarchy and management only when canonically available;
- no other workstream has claimed a conflicting top-level `management` route name; reconcile only at integration time if needed;
- safe external payment/link navigation remains server-URL driven;
- no current internal service API is exposed to browser code;
- no deployment/update-server control is added to Website.

## Source assumptions

- Shared foundation CSS defines the Design Lock `--f3-*` semantic variables; this module includes locked-value fallbacks only to remain integration-safe before all branches are merged.
- The browser environment supports ES modules, `AbortController`, `URL`, `Intl`, logical CSS properties and modern layout primitives, consistent with the V3 target.
- The merge worker may replace the inline per-component SVG construction with the final shared V3 local icon sprite if Workstream 01 provides one, while preserving icon semantics and keeping this module free of external icon dependencies.
- Current form GET semantics already filter to open/in-window forms server-side; the UI labels returned rows as open instead of recalculating availability from the browser clock.
- Current order history may duplicate an order across joined payment attempts; UI deduplicates by internal order ID but never displays that ID.
- Unknown raw status/role codes are never leaked; they degrade to safe Persian generic labels.

## Status

Source delivery for this workstream is complete subject to later parallel merge/integration. All missing domain projections are documented above rather than invented in presentation code.
