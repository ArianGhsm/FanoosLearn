# WORKSTREAM HANDOFF — BOT 01 CORE SHELL

## Identity

- Design Lock ID: `FANOOS-UX-2026.09-R1`
- `PARALLEL_REBUILD_BASE_SHA`: `9e72ef32b331ad41626229c28ed24dcc43a49292`
- Branch: `rebuild-v3/bot-01-core-shell`
- Owned source: `packages/python/fanoos_bot/ui_v3/core/**`
- Owned docs: `docs/ui-v3/workstreams/bot-01-core-shell/**`

## Files created

Source:

- `packages/python/fanoos_bot/ui_v3/core/__init__.py`
- `packages/python/fanoos_bot/ui_v3/core/contracts.py`
- `packages/python/fanoos_bot/ui_v3/core/navigation.py`
- `packages/python/fanoos_bot/ui_v3/core/actions.py`
- `packages/python/fanoos_bot/ui_v3/core/states.py`
- `packages/python/fanoos_bot/ui_v3/core/onboarding.py`
- `packages/python/fanoos_bot/ui_v3/core/home.py`
- `packages/python/fanoos_bot/ui_v3/core/workspace.py`
- `packages/python/fanoos_bot/ui_v3/core/account.py`

Docs:

- `docs/ui-v3/workstreams/bot-01-core-shell/screen_specs.md`
- `docs/ui-v3/workstreams/bot-01-core-shell/WORKSTREAM_HANDOFF.md`

## Exact public dataclass API for bot-02 / bot-03 / bot-04

```python
class Severity(str, Enum):
    NEUTRAL = "neutral"
    INFO = "info"
    SUCCESS = "success"
    WARNING = "warning"
    ERROR = "error"

class ProtectContent(str, Enum):
    INHERIT = "inherit"
    REQUIRED = "required"

class EditPolicy(str, Enum):
    AUTO = "auto"
    EDIT_IF_SAFE = "edit_if_safe"
    SEND_NEW = "send_new"

@dataclass(frozen=True)
class CallbackIntent:
    name: str
    params: tuple[tuple[str, str], ...] = ()
    route_ref: str | None = None
    def compact(self, *, max_bytes: int = 64) -> str | None: ...
    def to_dict(self) -> dict: ...

@dataclass(frozen=True)
class Fact:
    label: str
    value: str

@dataclass(frozen=True)
class ListItem:
    title: str
    description: str = ""
    meta: str = ""
    marker: str = ""

@dataclass(frozen=True)
class Section:
    title: str = ""
    body: str = ""
    facts: tuple[Fact, ...] = ()
    items: tuple[ListItem, ...] = ()

@dataclass(frozen=True)
class Context:
    label: str
    value: str
    detail: str = ""

@dataclass(frozen=True)
class Breadcrumb:
    label: str
    intent: CallbackIntent | None = None

@dataclass(frozen=True)
class Action:
    identifier: str
    label: str
    intent: CallbackIntent | None = None
    url: str | None = None
    destructive: bool = False

@dataclass(frozen=True)
class ActionRow:
    actions: tuple[Action, ...]  # exactly 1–2

@dataclass(frozen=True)
class Pagination:
    page: int
    total_pages: int | None = None
    previous: Action | None = None
    next: Action | None = None
    label: str = ""

@dataclass(frozen=True)
class Screen:
    identifier: str
    title: str
    intro: str = ""
    severity: Severity = Severity.NEUTRAL
    context: Context | None = None
    breadcrumb: tuple[Breadcrumb, ...] = ()
    sections: tuple[Section, ...] = ()
    action_rows: tuple[ActionRow, ...] = ()
    pagination: Pagination | None = None
    footer: str = ""
    protect_content: ProtectContent = ProtectContent.INHERIT
    edit_policy: EditPolicy = EditPolicy.EDIT_IF_SAFE
    rtl: bool = True
    def to_dict(self) -> dict: ...
    def plain_text(self) -> str: ...

@dataclass(frozen=True)
class Route:
    name: str
    label: str
    params: tuple[tuple[str, str], ...] = ()

@dataclass(frozen=True)
class NavigationState:
    stack: tuple[Route, ...] = (HOME_ROUTE,)
    page: int = 1
    cancel_target: Route | None = None
    max_depth: int = 8
```

`Screen.plain_text()` is a complete semantic plain fallback for Bale or Telegram fallback paths. bot-04 may render `Screen` richly without changing action semantics.

## Navigation contract

Navigation state is disposable presentation state only:

- stack starts at `home`;
- `push()` keeps a bounded max depth;
- `back()` returns the previous presentation route;
- `home()` resets the stack;
- `with_page()` changes presentation pagination only;
- `cancel()` returns to the explicit cancel target or previous route;
- `CallbackIntent.compact()` produces a <=64-byte callback payload when safe;
- when it returns `None`, bot-04 must allocate an opaque short, subject-bound, expiring route ref and persist only bounded presentation correlation;
- every domain route/callback causes a fresh canonical backend read/reauthorization before data is shown or an action is performed.

Forbidden in route/navigation state: role, RBAC decision, membership truth, grade truth, score, payment result, entitlement, protected-resource authorization, deployment permission, secrets.

## Stable action identifiers

- `core.home`
- `core.back`
- `core.cancel`
- `core.retry`
- `core.website.open`
- `core.help`
- `core.more`
- `core.courses`
- `core.schedule`
- `core.grades`
- `core.notifications`
- `core.resources`
- `core.assessments`
- `core.workspace`
- `core.workspace.select`
- `core.account`
- `core.account.unlink.request`
- `core.account.unlink.confirm`

bot-02/03 should import these shared action builders rather than inventing alternate labels for the same top-level destination. Domain-specific actions remain owned by their own workstreams.

## Screens/components delivered

- provider-neutral semantic contracts;
- compact callback intent descriptor;
- bounded route-stack navigation model;
- home/back/cancel/pagination primitives;
- unlinked onboarding;
- linked zero-workspace onboarding;
- linked one-workspace onboarding;
- linked multi-workspace onboarding entry;
- expired challenge state;
- service-unavailable onboarding;
- getting-started/help;
- active-workspace Home with bounded schedule/announcement slots;
- main product menu grammar;
- More menu;
- workspace list/selected marker/switch/empty/success;
- linked Account;
- unlink confirmation/success;
- standard empty/error/unavailable/success builders.

## Current/old files inspected and conceptually reused

Canonical/current FANOOS:

- `AGENTS.md`
- `docs/DEVELOPMENT_WORKFLOW.md`
- `docs/ui-v3/FANOOS_UI_UX_DESIGN_LOCK.md`
- `docs/ui-v2/FANOOS_PRODUCT_IA.md`
- `docs/ui-v2/FANOOS_PRESENTATION_CONTRACT.md`
- `docs/ui-v2/FANOOS_CROSS_CHANNEL_MATRIX.md`
- all `docs/ui-v2/bots/**`
- `docs/ux-parallel/worker03_bot_semantic_presentation.md`
- `docs/ux-parallel/worker04_channel_native_presentation.md`
- `contracts/REGISTRY.md`
- `contracts/openapi/core-v1.yaml`
- `contracts/openapi/internal-v1.yaml`
- `docs/fanoos-migration/02_MODULE_AND_DATA_OWNERSHIP.md`
- `docs/fanoos-migration/02_TARGET_ARCHITECTURE.md`
- `packages/python/fanoos_bot/models.py`
- `packages/python/fanoos_bot/presentation.py`
- `packages/python/fanoos_bot/product_ui.py`
- `packages/python/fanoos_bot/api.py`
- relevant `packages/python/fanoos_bot/application.py` Home/workspace/account/link paths
- relevant `apps/platform/src/Http/InternalApiKernel.php` workspace/link projection paths

Concepts reused:

- backend authority and subject-bound messaging-link model;
- canonical workspace list/select semantics;
- semantic screen metadata as provider-neutral input;
- complete plain fallback;
- selected workspace marker;
- explicit unlink confirmation;
- human Persian labels/no raw identifiers;
- callback <=64-byte constraint;
- bounded presentation-route correlation;
- Rich-to-plain presentation fallback without business replay;
- Bale capability asymmetry/protected fail-closed invariant.

Rebuilt rather than copied:

- V3 semantic dataclass API;
- information hierarchy and action rows;
- no-workspace shell;
- onboarding state system;
- Home composition;
- navigation model;
- workspace/account composition;
- standard state builders.

## Canonical endpoints/projections consumed by future integration

This package performs no HTTP I/O. Integration should map canonical results into these screen builders.

Internal bot API:

- `POST /api/internal/v1/messaging/link-challenges/consume`
- `POST /api/internal/v1/messaging/links/revoke`
- `POST /api/internal/v1/messaging/workspaces/list`
- `POST /api/internal/v1/messaging/workspaces/select`
- `POST /api/internal/v1/academics/schedule` for Home schedule slots
- `POST /api/internal/v1/announcements/list` for Home latest-announcement slot

Public web API is not called by this bot package. The configured Website action leads the human to the FANOOS web product, whose canonical account/workspace endpoints include `/api/v1/account`, `/api/v1/workspaces`, `/api/v1/workspaces/select`, and messaging link-challenge/revoke operations.

## Integration imports/dependencies

Expected downstream imports:

```python
from fanoos_bot.ui_v3.core import Screen, Section, Fact, ListItem
from fanoos_bot.ui_v3.core import Action, ActionRow, Pagination
from fanoos_bot.ui_v3.core import Severity, Context, Breadcrumb
from fanoos_bot.ui_v3.core import ProtectContent, EditPolicy, CallbackIntent
```

bot-04 provider integration must:

1. Render `Screen` semantics without creating business state.
2. Treat `plain_text()` as the complete fallback.
3. Keep `ProtectContent.REQUIRED` fail-closed; never downgrade it for Bale.
4. Encode callback intents in <=64 bytes; allocate short subject-bound route refs when `compact()` returns `None`.
5. Never replay a business action because Rich/edit/plain fallback failed.
6. Preserve Telegram-only deployment-control rules outside this core package.

## INTEGRATION_GAPS

1. **No bot-safe workspace-creation endpoint exists.** Zero-workspace screens therefore expose workspace/account/help/website/home but do not invent a create action.
2. **No bot account-profile projection exists beyond linked-subject/workspace context.** Account V3 can safely show link status, platform, workspace count/active workspace supplied by current projections; it must not invent a display identity or expose subject/user IDs.
3. **Complete course/enrollment projection remains missing from internal v1.** Owned by bot-02 handoff/integration; core only exposes the `درس‌ها` destination.
4. **Personal notification-history projection remains missing.** `🔔 اعلان‌ها` is a destination contract, not permission to derive inbox history from delivery receipts.
5. **Bot-safe assessment projection remains missing.** Core exposes `📝 آزمون‌ها` as a product destination; bot-03/integration must use a truthful gap/Website handoff until a canonical projection exists.
6. **Provider route-ref persistence/wire encoding is integration-owned.** Core defines safe intent semantics and a compact hint but intentionally does not modify current `state.py`, runtime, or provider wiring.
7. **Website base URL/config injection is integration-owned.** Builders require a configured HTTP(S) URL and never fabricate a production domain.

## Merge-worker assumptions to verify

- `PARALLEL_REBUILD_BASE_SHA` is still the common base for every worker branch.
- shared V3 package integration preserves these dataclass field names and action identifiers;
- provider workers do not treat callback params as authorization;
- backend selection/revoke calls are made only after fresh linked-subject authorization;
- zero-workspace Home uses `linked_no_workspace_screen()` rather than collapsing to a warning + Website CTA;
- Home distinguishes canonical empty vs unavailable before constructing `HomeSlot`;
- no current V2 or central runtime file should be modified merely to make this source branch standalone.

## Ownership check

All source changes in this worker are under `packages/python/fanoos_bot/ui_v3/core/**`. All documentation changes are under `docs/ui-v3/workstreams/bot-01-core-shell/**`. No V2 file, runtime, application entrypoint, provider wiring, contract, CI file, test file, migration, server, or deployment artifact is owned or changed by this workstream.
