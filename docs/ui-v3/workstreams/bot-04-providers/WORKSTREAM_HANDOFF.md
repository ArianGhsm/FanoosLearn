# WORKSTREAM HANDOFF — bot-04-providers

## Identity

- Design Lock ID: `FANOOS-UX-2026.09-R1`
- `PARALLEL_REBUILD_BASE_SHA`: `9e72ef32b331ad41626229c28ed24dcc43a49292`
- Branch: `rebuild-v3/bot-04-providers`
- Owned source path: `packages/python/fanoos_bot/ui_v3/providers/**`
- Owned handoff path: `docs/ui-v3/workstreams/bot-04-providers/**`

## Files created

- `packages/python/fanoos_bot/ui_v3/providers/__init__.py`
- `packages/python/fanoos_bot/ui_v3/providers/contract.py`
- `packages/python/fanoos_bot/ui_v3/providers/policy.py`
- `packages/python/fanoos_bot/ui_v3/providers/telegram.py`
- `packages/python/fanoos_bot/ui_v3/providers/bale.py`
- `packages/python/fanoos_bot/ui_v3/providers/examples.py`
- `docs/ui-v3/workstreams/bot-04-providers/PROVIDER_SCREENBOOK.md`
- `docs/ui-v3/workstreams/bot-04-providers/WORKSTREAM_HANDOFF.md`

## Delivered provider system

### Shared semantic input adapter

`contract.adapt_screen()` is a narrow presentation-only compatibility seam. It accepts:

- the current V2 `Screen`/`ScreenPresentation` shape;
- mappings/objects using the Design Lock concepts expected from bot-01/core;
- the V3-local normalized `ProviderScreen` directly.

It consumes display semantics only: title, context/breadcrumb, intro, facts, lists, sections, pagination/footer, actions, protection intent and edit policy. It never treats a callback, display label, workspace identifier, order/payment field or provider result as authorization.

### Message density policy

`MessageDensityPolicy` defines the normal bot budget:

- 1 title;
- up to 3 typical sections;
- up to 8 typical list items across the screen;
- 3–8 typical actions;
- hard action ceiling before a secondary screen is required;
- provider message hard length remains 4096 for the reliable plain/fallback path.

Long datasets are expected to be paginated by the semantic/core workstreams instead of being split into an unbounded text dump by provider code.

### Action-row sizing

`pack_actions()`:

- validates callback payloads by UTF-8 bytes, maximum 64;
- packs two short routine actions per row only when both fit the conservative visual-width budget;
- isolates primary/destructive/long actions at full width;
- places pagination in dedicated bottom rows;
- places Back/Home last;
- never pairs destructive actions with routine navigation;
- never rewrites callback data or URLs.

### Telegram renderer

`TelegramV3Renderer` produces a `TelegramRenderPlan` containing:

- complete plain fallback;
- Telegram Rich Message HTML using verified `h3`, `h4`, `p`, `br`, `ul/li`, `b` and `blockquote` semantics;
- `is_rtl=true` for Persian screens;
- inline keyboard layout;
- edit/new-message intent;
- callback-ACK-before-business intent metadata;
- `protect_content` intent;
- atomic/fallback policy;
- explicit `business_replay_allowed=False`.

Normal Rich failure is presentation-only and may fall back once to the same already-computed plain screen. Edit failure may become one new rendering of the same computed screen. Neither path grants permission or reruns a business mutation.

### Telegram protected-content policy

For `protect_content=True`:

- delivery intent is always a new message;
- render plan carries `protect_content=True`;
- the operation is atomic;
- Rich is not preferred for the protected operation;
- after an ambiguous provider attempt there is no second provider send fallback;
- the renderer never supplies authorization and never substitutes an unprotected original.

### Telegram owner-management seam

Deployment controls are defense-in-depth gated at presentation time:

- provider must be Telegram;
- context must be private;
- integration must supply canonical `deployment.manage` in `ProviderContext.canonical_permissions`;
- ordinary screens may simply lose the gated management action when these conditions are absent;
- a direct unauthorized owner-surface render maps to a safe denied screen;
- provider action model has no arbitrary text input for repository/ref/SHA/path/command/shell selection.

This seam does **not** replace backend authorization. The backend overview/request/status contract remains authoritative.

### Bale renderer

`BaleV3Renderer` produces a Bale-only `BaleRenderPlan`:

- readable Persian hierarchy;
- documented Bale Markdown emphasis only where safe;
- inline keyboard rows;
- edit/new-message intent;
- callback ACK metadata;
- no Telegram Rich payload, `is_rtl`, `protect_content`, Rich draft or Telegram owner-control field.

A Bale owner-management surface becomes a safe informational screen with no deployment controls.

### Bale protected-content policy

The current official Bale documentation does not expose a relied-on `protect_content` equivalent. When canonical presentation requires protection:

- `can_deliver_original=False`;
- the protected original is not sent;
- a safe explanatory fail-closed screen is produced;
- navigation may remain;
- there is no silent downgrade to ordinary delivery.

## Provider capabilities relied on

Capability review date: `2026-09-10`.

### Telegram

Official source: <https://core.telegram.org/bots/api>

Relied on:

- Bot API 10.3 Rich Messages;
- Rich HTML headings/paragraphs/lists/quotations/line breaks;
- `InputRichMessage.is_rtl`;
- `sendRichMessage`;
- `editMessageText.rich_message`;
- inline keyboards;
- callback payload maximum 64 bytes;
- callback acknowledgement;
- `protect_content`;
- normal text limit 4096 and Rich text limit 32768.

### Bale

Official source: <https://docs.bale.ai/>

Relied on:

- `sendMessage` with 1–4096 text;
- documented Markdown text formatting;
- inline keyboard;
- callback payload 1–64 bytes;
- `answerCallbackQuery` requirement after inline-button interaction;
- `editMessageText`;
- `reply_to_message_id` at transport integration time.

Not relied on because it is not documented in the reviewed surface:

- Telegram Rich Message payloads;
- Telegram Rich draft/RTL fields;
- Telegram `protect_content` equivalent.

## Fallback contract

1. Semantic/business result is computed once upstream.
2. Callback ACK intent is `before_business_action`; existing runtime ordering must remain ACK → dedupe → business → presentation.
3. Normal Telegram Rich failure may become one plain render of the same computed result.
4. Normal edit failure may become one new render of the same computed result.
5. Bale ordinary edit failure may become one new render of the same computed result.
6. No render/edit fallback may call the canonical business action again.
7. Protected Telegram output gets no ambiguous second provider operation.
8. Protected Bale original is refused; only a safe explanatory screen may be sent.

## Current code inspected and conceptually reused

Reused semantics/invariants rather than presentation structure from:

- `packages/python/fanoos_bot/models.py` — current `Screen`, `ScreenPresentation`, action/fallback semantics;
- `packages/python/fanoos_bot/presentation.py` — safe Persian semantic metadata and complete text fallback principle;
- `packages/python/fanoos_bot/semantic_adapter.py` — compatibility lesson for additive presentation metadata;
- `packages/python/fanoos_bot/telegram_presentation.py` — proven Rich/plain separation and protected precedence;
- `packages/python/fanoos_bot/bale_presentation.py` — provider-specific fallback boundary;
- `packages/python/fanoos_bot/capabilities.py` — provider limits/capability registry semantics;
- `packages/python/fanoos_bot/botapi.py` — inline keyboard shape, callback byte enforcement, edit/send and protected transport behavior;
- `packages/python/fanoos_bot/runtime.py` — callback ACK ordering, processed-update replay guard, protected receipt atomicity;
- `packages/python/fanoos_bot/api.py` — signed canonical bot projections only;
- `apps/platform/src/Operations/OwnerControlPlaneService.php` — canonical `deployment.manage` overview gate;
- `apps/platform/src/Http/InternalApiKernel.php` — signed linked-subject/workspace reauthorization boundary for bot-safe reads;
- `docs/ui-v2/bots/**` and `docs/ux-parallel/worker03_bot_semantic_presentation.md`, `worker04_channel_native_presentation.md`, `UX_INTEGRATION_REPORT.md` — prior IA, parity and provider reliability lessons.

V2's generic semantic-HTML transformation was **not** retained as the V3 product grammar. V3 adds deterministic hierarchy, action packing, density, navigation placement, owner gating, edit/new intent and explicit fail-closed provider plans.

## Canonical endpoints/projections consumed

Provider code itself performs no backend requests. It expects semantic screens already derived from canonical projections including, where applicable:

- `POST /api/internal/v1/messaging/workspaces/list`
- `POST /api/internal/v1/messaging/workspaces/select`
- `POST /api/internal/v1/academics/schedule`
- `POST /api/internal/v1/academics/grades`
- `POST /api/internal/v1/announcements/list`
- `POST /api/internal/v1/content/resources/list`
- `POST /api/internal/v1/commerce/orders`
- `POST /api/internal/v1/commerce/orders/status`
- `POST /api/internal/v1/notifications/project|claim|receipt`
- `POST /api/internal/v1/deliveries/issue|consume|receipt`
- protected-media derivative issue/redeem endpoints;
- `POST /api/internal/v1/deployments/overview|request|status` for private Telegram owner flow only.

Public/core-v1 remains the canonical browser/human surface for flows intentionally handed off to Website.

## Integration imports/dependencies

The package is deliberately self-contained inside `ui_v3/providers` and has no import from current production `application.py`, `runtime.py`, `botapi.py`, `telegram_presentation.py` or `bale_presentation.py`.

Later integration should:

1. reconcile `contract.adapt_screen()` against the final exact bot-01/core Python types;
2. map the final core Action role/capability/EditPolicy names to the compatibility adapter;
3. wire `TelegramRenderPlan`/`BaleRenderPlan` into transport without changing callback/business semantics;
4. keep current processed-update/idempotency/receipt logic around provider delivery;
5. preserve backend-supplied `deployment.manage` as the only permission source for owner controls;
6. keep provider URL generation outside presentation authority and pass already-safe Website links from core/integration.

## Screens/components delivered

Representative source examples and screenbook entries cover:

- unlinked;
- linked/no-workspace;
- Home active;
- courses;
- course detail;
- schedule;
- grades;
- announcements;
- resources;
- protected denied;
- protected ready;
- payment/access;
- account;
- error;
- owner management (Telegram only).

## INTEGRATION_GAPS

1. **bot-01/core physical types are parallel-owned.** The Design Lock contract is known, but the exact final Python field/enum names are not present on this base. `adapt_screen()` is intentionally duck-typed; merge integration must reconcile exact names without moving business truth into providers.
2. **V3 transport wiring is integration-owned.** Current production `botapi.py`, `runtime.py`, `application.py`, `telegram_presentation.py` and `bale_presentation.py` were not modified. Integration must consume V3 render plans while preserving ACK/idempotency/receipt behavior.
3. **Complete bot-safe course/enrollment projection remains missing on this base.** Existing course discovery can omit courses that have no projected schedule/grade/resource row. Provider code does not invent a course authority.
4. **Native bot-safe assessment projection remains missing.** Assessment screens must continue to use an honest Website handoff/gap until an integration-approved canonical contract exists.
5. **Personal notification-history projection remains missing.** Notification delivery receipts are not an inbox authority.
6. **Bot-safe commerce catalog/current-access list remains incomplete.** Provider UI must not ask normal users for raw product/order UUIDs or infer entitlement from payment appearance.
7. **Course-bound announcement filtering remains incomplete** where the projection does not carry canonical course/offering scope.
8. **Bale required forward protection remains unavailable in the reviewed official capability surface.** Protected originals therefore remain fail-closed.
9. **Long V3 datasets must be paginated upstream.** Provider renderers intentionally refuse hard message/action overflow rather than silently dropping rows or splitting a business-sensitive screen.
10. **Owner action metadata should be explicit in final bot-01/core.** The adapter contains compatibility inference that can only impose a stricter `deployment.manage` gate; it never grants permission. Integration should prefer explicit `requires_permission="deployment.manage"` and semantic action IDs.

## Merge-worker source assumptions to verify

- all V3 worker branches still share base `9e72ef32b331ad41626229c28ed24dcc43a49292`;
- bot-01 retains the Design Lock semantic concepts and supplies a complete plain fallback or equivalent semantic fields;
- callback values remain opaque and within 64 UTF-8 bytes after final integration naming;
- core explicitly marks strong/destructive/navigation/pagination action roles where inference is avoidable;
- protected delivery result carries protection intent separately from authorization result;
- owner `deployment.manage` comes only from the canonical backend overview/permission path and private Telegram context;
- Website URLs passed to provider actions are canonical safe destinations, never permanent signed capabilities.
