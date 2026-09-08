# Worker 03 — Bot Semantic Presentation Handoff

Base SHA: `fef86a4adcad99cb75327582750dd4cd2df88dea`  
Branch: `ux/03-bot-semantic-presentation`

## Scope

This workstream changes presentation only. Telegram and Bale still use the same canonical FANOOS backend, and no API/schema, authorization, payment, entitlement, notification-delivery, protected-delivery, callback, deployment-control, or runtime contract is changed.

Owned files only are modified.

## Semantic model

`Screen` remains backward compatible in its first four fields:

1. `text`
2. `rows`
3. `edit`
4. `protect_content`

An additive fifth field is introduced:

- `presentation: ScreenPresentation | None = None`

`ScreenPresentation` is framework-neutral, display-only metadata:

- `title`
- `semantic_kind`
- `severity`
- `intro`
- `facts`
- `list_items`
- `sections`
- `footer`
- `rtl`

`SemanticSection` contains only display strings (`title`, `body`, `items`). Both models expose `to_dict()` and contain no authorization or business object. Backend projections remain the source of truth; the semantic model is not a second state store.

## Fallback compatibility

`presentation.semantic_screen()` builds `Screen.text` from the same semantic metadata with `render_fallback()` unless a protected-content flow must preserve backend-provided display text. Old transports can ignore `presentation` and continue using `Screen.text` + `Screen.rows`.

No callback action or callback payload format was changed.

## Persian message language

Primary vocabulary:

| Meaning | Copy |
| --- | --- |
| Home | 🏠 خانه / 🏠 فانوس |
| Schedule | 📅 برنامه |
| Grades | 🎓 نمرات |
| Announcements | 📢 اطلاعیه‌ها |
| Resources | 📚 منابع |
| Purchase/access | 💳 خرید و دسترسی |
| Workspace | 🏫 فضای آموزشی |
| Account | 👤 حساب |
| Settings/admin | ⚙️ |
| Refresh | 🔄 |
| Protected content | 🔒 |
| Success | ✅ |
| Warning | ⚠️ |
| Error | ❌ |
| Information/help | ℹ️ |
| Notifications | 🔔 |

Emoji use is intentionally moderate and semantic. Buttons use the same vocabulary as screen titles where useful.

## Localization and formatting

Central maps in `localization.py` cover:

- platform names (`telegram` → `تلگرام`, `bale` → `بله`)
- payment/order status
- entitlement display
- deployment state
- deployment health
- resource types
- user-safe error taxonomy

Error presentation never emits `FanoosApiError.message`, provider detail, raw exception text, or raw internal error code.

`formatting.py` covers:

- Persian digits for human counts, scores, dates and times
- localized money formatting from `amount_minor` + currency
- ISO datetime parsing and timezone-aware time rendering
- UUID/SHA preservation
- owner-only short SHA rendering with LTR isolation
- Unicode-aware bounded truncation
- mixed LTR isolation
- humanized slug fallback when no human workspace label exists

## Screens covered

### Home

- short `🏠 فانوس` title
- active workspace uses human label, never workspace UUID
- requested navigation labels:
  - 📅 امروز
  - 📅 فردا
  - 🎓 نمرات من
  - 📢 اطلاعیه‌ها
  - 📚 منابع
  - 💳 خرید و دسترسی
  - 🏫 فضای آموزشی
  - 👤 حساب
  - 🌐 باز کردن فانوس only when URL exists

### Account / link / unlink

- Persian platform label
- workspace count in Persian digits
- active workspace by human label
- link success is explicit and does not present platform subject as identity proof
- expired/invalid link challenge points the user to a fresh FANOOS link code
- unlink explicitly says only the messenger connection is removed and the main FANOOS account is not deleted

### Workspace

- `🏫 فضای آموزشی`
- consistent `✓` selected marker
- no UUID in text
- slug is used only as a last humanized fallback
- empty/error states are user-facing
- switching returns Home with a single success footer rather than a second standalone message

### Schedule

- `📅 برنامه امروز` / `📅 برنامه فردا`
- Persian time
- title and optional location only
- no invented event detail
- ISO values are parsed where possible

### Grades

- `🎓 نمرات من`
- title + score + optional maximum
- null-safe rendering
- no raw backend publication/status value

### Announcements

- `📢 اطلاعیه‌ها`
- bounded title/body
- Persian datetime when backend supplies a timestamp
- structured sections; no raw IDs

### Resources / protected delivery

- `📚 منابع`
- localized resource type where available
- concise delivery buttons
- protected states for preparing, ready, expired/wrong-account, wrong workspace, size failure, revoked authorization through the common error taxonomy
- Bale forward-protection limitation is explicit: official Bale UI support is unavailable and the direct send is refused
- no alternate route is promised because no such route exists in the current contract
- no absolute DRM claim
- no fake delay, percentage, or ETA

### Payments

- `/buy <product_id>` developer notation removed from user copy
- current command contract remains `/buy`, but the instruction is Persian and concise
- amount/currency/status are localized
- `pending` / `paid` are never user-visible
- access is shown only from backend `entitlement.granted`
- a paid success label is shown only when backend status is canonically paid/succeeded

### Notifications

`presentation.py` now provides framework-neutral:

- `notification_center_screen()`
- `notification_detail_screen()`

They support Persian unread/read wording, Persian datetime, bounded text and semantic metadata.

**Integration gap:** current notification delivery is owned by forbidden `packages/python/fanoos_bot/runtime.py::NotificationPump`, which directly creates `Screen(part)`. Worker 03 did not modify that runtime. There is also no user-facing notification inbox/list API exposed through `BotApplication` at this base SHA. No new business/API contract was invented.

### Update Server

- `⚙️ به‌روزرسانی سرور`
- current health localized
- “نسخه جدید موجود است” / no-update / unknown states localized
- two-step confirmation preserved
- all observed durable deployment states localized
- no fake percent/ETA
- request ID hidden from user copy while still used in unchanged callback semantics
- only bounded short SHA is shown as explicit `(SHA)` owner diagnostic
- raw `failure_code`, state enum and provider/error detail are not shown
- rollback is explicitly rendered as return to previous application version; no database rollback claim is made

## Worker 4 integration expectation

Worker 4 should:

1. Read `Screen.presentation` before transport rendering.
2. For Telegram Rich UI, map:
   - `title` → heading
   - `intro` / `footer` → paragraphs or appropriate semantic blocks
   - `facts` → compact fact rows/list (table only where it is genuinely tabular)
   - `list_items` → native list
   - `sections` → section headings + body/list
   - `rtl=True` → Telegram RTL rich-message flag.
3. Preserve `Screen.rows` exactly as the action mechanism. Do not rewrite callbacks or URLs.
4. If Rich UI is unavailable, rejected, disabled, or the screen has no semantic metadata, send `Screen.text` unchanged.
5. Do not re-run application/business actions when Rich rendering fails; fallback is presentation-only.
6. When runtime reconstructs/chunks `Screen`, do not accidentally turn semantic metadata into business state. Prefer rendering a semantic screen before fallback chunking; fallback chunking may remain text-only.
7. For `NotificationPump`, use `notification_detail_screen(payload)` for shared Persian notification semantics while preserving current lease/receipt/idempotency behavior. Notification center/list must only be wired if a canonical backend projection actually exists.
8. Keep Bale fail-closed for `forward_protection_required=true`; presentation must not imply parity or an unsupported alternate route.

## Tests

Updated `tests/bots/test_application.py` checks:

- Persian core screens
- human workspace/platform labels
- callback payload stability
- localized payment/deployment states
- Persian numbers/time
- protected-delivery receipt and reauthorization behavior
- Bale fail-closed behavior
- no provider detail leak
- semantic metadata on core screens
- owner update permission/confirmation behavior remains intact

Worker-specific tests:

- `tests/ux/worker03_test_formatting.py`
- `tests/ux/worker03_test_semantic_screen.py`

They cover Persian numbers/money/date/time, UUID/SHA preservation, mixed-direction isolation, Unicode truncation, localization maps, positional `Screen` backward compatibility, serialization, fallback derivation, notification semantic helpers, severity/title/RTL metadata.

## Report-only backend/business gaps

- No bot-facing product catalog projection exists in the current application contract; therefore purchase discovery was not invented. Existing `/buy` command semantics remain.
- Current `BotApplication` does not expose notification inbox/list/read contracts. Shared presentation helpers are ready, but wiring those interactions requires canonical backend operations or existing projections.
- Bale has no documented forward/save restriction equivalent in the current project capability contract; protected direct delivery remains refused when the backend requires forward protection.
