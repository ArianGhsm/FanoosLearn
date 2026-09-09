# FANOOS Bot V3 — Learning & Commerce Screen Specs

Design Lock: `FANOOS-UX-2026.09-R1`  
Workstream: `03_LEARNING_COMMERCE`  
Base: `9e72ef32b331ad41626229c28ed24dcc43a49292`

These are provider-neutral product screens. Telegram/Bale rendering is owned by bot-04; authorization, payment, entitlement, protected delivery and scoring remain backend-owned.

## Core contract

The implementation was aligned read-only against completed bot-01 commit `faead96a4bedca34151562c81c712ae42b2a7693` without merging/rebasing it. It uses the exact bot-01 primitives:

`Screen`, `Section`, `Fact`, `ListItem`, `Action`, `ActionRow`, `Pagination`, `Severity`, `Context`, `Breadcrumb`, `ProtectContent`, `EditPolicy`, `CallbackIntent`.

Navigation exits use bot-01 callback names `back` and `home`. Learning identifiers/UUIDs may exist only inside `CallbackIntent.params`; none are user-visible and none are authority.

## Screen inventory

| Family | Screen | Purpose |
| --- | --- | --- |
| Resources | `learning.resource_hub` | bounded authorized library + filters/pagination |
| Resources | `learning.resource_detail` | title/course/type/version/access/protection + delivery intent |
| Protected | `learning.protected.checking` | current authorization check |
| Protected | `learning.protected.preparing` | truthful unmeasured preparation |
| Protected | `learning.protected.ready` | protected delivery ready state |
| Protected | `learning.protected.expired` | expired request; restart from resource |
| Protected | `learning.protected.denied` | access not currently authorized |
| Protected | `learning.protected.unsupported_channel` | provider cannot meet required protection |
| Protected | `learning.protected.temporary_failure` | retryable temporary failure |
| Assessments | `learning.assessment_hub` | active/upcoming/completed/practice/past if canonical |
| Assessments | `learning.assessment_detail` | title/course/deadline/state + safe website continuation |
| Commerce | `learning.commerce_hub` | order/access summary; catalog only when bot-safe |
| Commerce | `learning.order_access_detail` | separate order/payment/entitlement + amount/currency |
| Forms | `learning.forms_hub` | list if bot-safe; otherwise explicit web handoff |
| Forms | `learning.form_detail` | metadata + canonical web submission |
| Shared | `learning.<domain>.<state>` | empty/error/denied/unavailable recovery |

## Resources

Current `/api/internal/v1/content/resources/list` is sufficient for a native resource experience. `BotReadProjectionService::resourceCatalog()` reauthorizes each candidate and exposes safe metadata plus `resource_id`, authorized `resource_version_id` and `delivery_supported`; it never exposes object/storage paths.

The hub renders at most 8 items. Up to 6 item CTAs are grouped two per row. Filter controls appear only when canonical choices are supplied. `تازه‌ها` is valid because the current canonical catalog is ordered by resource update time. Pagination uses canonical opaque cursor data inside callback correlation only.

Example:

```text
📚 منابع و یادگیری
منابع مجاز فضای آموزشی شما

📚 کتابخانه
🔒 اندو ۱ — جلسه ۳
اندودانتیکس ۱ · جزوه
نسخهٔ جاری مجاز

📄 رفرنس پالپ
اندودانتیکس ۱ · رفرنس
نسخهٔ جاری مجاز

[ درس ] [ نوع ]
[ تازه‌ها ]
[ اندو ۱ — جلسه ۳ ] [ رفرنس پالپ ]
[ ‹ قبلی ] [ بعدی › ]
[ ‹ بازگشت ] [ 🏠 خانه ]
```

No UUID, cursor, object ID or storage key appears in text.

### Resource detail

Facts are title, course when available, type, version, access and protection. The current projection exposes a version UUID but no human version number, so copy is `نسخهٔ جاری مجاز`; the UUID is never converted to a fake version label.

If the backend does not expose a human protected-state value, copy says:

`حفاظت: هنگام دریافت بر اساس سیاست منبع بررسی می‌شود`

`🔒 دریافت امن` appears only when `delivery_supported` is true. Clicking it is only an intent to begin the canonical delivery flow; it does not carry authorization.

## Protected delivery state family

Presentation does not issue/redeem tokens or send files. Current application/backend owns delivery issue/consume, protected-media preparation, derivative issue/redeem and receipts.

### Checking

```text
🔒 بررسی دسترسی
دسترسی شما به «جزوه اندو ۱» در حال بررسی است.

وضعیت
این مرحله فقط وضعیت فعلی مجوز و دسترسی را از backend می‌گیرد.

[ 🔄 بررسی دوباره ]
[ ‹ بازگشت ] [ 🏠 خانه ]
```

### Preparing

```text
🔒 آماده‌سازی نسخه محافظت‌شده
نسخهٔ قابل ارسال «جزوه اندو ۱» هنوز آماده نشده است.

وضعیت
آماده‌سازی ادامه دارد؛ درصد یا زمان پایان تا وقتی اندازه‌گیری واقعی وجود نداشته باشد نمایش داده نمی‌شود.

[ 🔄 بررسی آماده‌شدن ]
[ ‹ بازگشت ] [ 🏠 خانه ]
```

### Ready

`learning.protected.ready` sets `ProtectContent.REQUIRED` and `EditPolicy.SEND_NEW`. bot-04 must use the verified protected provider path for the accompanying delivery payload; rich-render failure must not replay business actions.

### Denied — required example

```text
🔒 دسترسی به این منبع فعال نیست
backend دریافت «جزوه اندو ۱» را برای وضعیت فعلی حساب تأیید نکرد.

وضعیت
اگر اخیراً خرید یا دسترسی شما تغییر کرده است، وضعیت را از بخش خرید و دسترسی بررسی کنید.

[ 💳 خرید و دسترسی ]
[ 🌐 بررسی در فانوس ]   ← only when integration supplies a safe HTTPS route
[ ‹ بازگشت ] [ 🏠 خانه ]
```

Denied copy does not expose authorization internals and never promises that payment itself grants access.

### Unsupported channel — required example

When forward/save protection is required and the provider cannot satisfy it (current Bale invariant):

```text
⚠️ ارسال محافظت‌شده در این پیام‌رسان ممکن نیست
«جزوه اندو ۱» به حفاظتی نیاز دارد که در بله تأیید نشده است.

وضعیت
نسخهٔ بدون حفاظت ارسال نمی‌شود. این محدودیت امنیتی عمداً fail-closed است.

[ 🌐 دریافت امن در فانوس ]  ← only with a safe canonical web destination
[ ‹ بازگشت ] [ 🏠 خانه ]
```

Never fall back to original bytes, cached provider media, an unrestricted URL or weaker protection.

### Expired / temporary failure

Expired requests restart from resource context so authorization is fresh. Temporary failures are not rendered as denial or success; retry invokes the canonical flow once through integration. No fake ETA/percentage is shown.

## Assessments

The source supports active/upcoming/completed/practice/past-exam grouping only when a bot-safe canonical projection is supplied.

At this base SHA, `core-v1` exposes browser assessment catalog/attempt/submit/review routes, but `internal-v1` exposes no assessment list/detail/attempt/result contract. Therefore current bot integration must use this structured handoff rather than synthesizing exam truth:

```text
📝 آزمون‌ها
وضعیت آزمون‌ها و مسیر امن ادامه

📝 وضعیت ربات
فهرست و تلاش آزمون هنوز projection امن و اختصاصی ربات ندارد؛ پاسخ و امتیاز محلی ساخته نمی‌شود.

🌐 ادامه در فانوس
فهرست آزمون‌ها، شروع یا ادامهٔ تلاش و نتیجه از رابط وب canonical انجام می‌شود.

[ 🌐 باز کردن آزمون‌ها ]
[ ‹ بازگشت ] [ 🏠 خانه ]
```

Future native detail is limited to title, course, deadline and canonical state plus safe website continuation. No answer/scoring logic exists in this workstream.

## Purchase & access

Normal UX never asks the user for `/buy <product_id>` and never displays raw product/order IDs.

At this base SHA internal commerce exposes create-order (only when a product is already known) and order-status-by-ID. `BotCommerceService` projects title snapshot, amount, currency, order status and `entitlement.granted`. It does not expose product catalog, order history, full payment-attempt status, or a complete self-service entitlement list.

Accordingly `learning.commerce_hub` can consume those richer projections later, but today shows explicit gaps plus the website center when configured.

### Order/payment/access separation

Three lines never collapse into one:

```text
💳 بانک سؤال ترمیمی
خرید و دسترسی › سفارش

وضعیت
وضعیت سفارش: در انتظار
وضعیت پرداخت: در انتظار پرداخت
دسترسی: دسترسی فعال
مبلغ: ۱٬۵۰۰٬۰۰۰ ریال

[ 🔄 تازه‌سازی وضعیت ]
[ 🌐 جزئیات در فانوس ]
[ ‹ بازگشت ] [ 🏠 خانه ]
```

### Payment pending vs access active — required example

This combination is valid. An entitlement may already be active while a newer order is pending. The bot must display both facts independently.

The inverse must also remain explicit:

```text
وضعیت سفارش: پرداخت تأیید شده
وضعیت پرداخت: پرداخت تأیید شده
دسترسی: دسترسی فعال نیست
```

Payment success never causes presentation code to invent entitlement success.

Because the current bot order projection has no distinct payment-status field, `order_access_detail_screen(..., payment_status=None)` renders:

`وضعیت پرداخت: در projection فعلی جداگانه گزارش نشده`

It deliberately does not duplicate `order.status` under a second label.

Money uses backend snapshot amount and canonical currency. `IRR` is shown as `ریال`; no implicit rial→toman conversion exists.

## Forms / services

`core-v1` has canonical form listing/submission. `internal-v1` has no forms projection at this base SHA. Current native state is therefore:

```text
📝 فرم‌ها و خدمات
ثبت پاسخ فقط از مسیر canonical انجام می‌شود.

فرم‌ها و خدمات
فرم‌های فعال در API وب canonical هستند، اما projection امن اختصاصی ربات در قرارداد فعلی وجود ندارد.

[ 🌐 باز کردن فرم‌ها در فانوس ]
[ ‹ بازگشت ] [ 🏠 خانه ]
```

The builders can render list/detail once a bot-safe projection exists. Submission remains a website handoff until an explicit bot-safe canonical submission contract is added.

## Error / empty states

Each owned domain supports `empty`, `denied`, `unavailable`, and `error` screens. Copy states what happened, whether retry is meaningful and the next safe step. Stale content is never labeled current. Raw HTTP codes, enums, HMAC details, provider tokens and IDs are never surfaced.

## Protected action intents for bot-04

| Intent | Meaning | Requirement |
| --- | --- | --- |
| `learning.resource.deliver` | start canonical secure-delivery journey | no provider send before backend result |
| `learning.protected.check` | re-check current access | no fake progress |
| `learning.protected.refresh` | check derivative readiness | render failure must not replay mutation |
| `learning.protected.retry` | retry temporary failure via application | invoke canonical flow once |
| `learning.protected.resource` | restart/return from resource | never reuse expired capability |

Provider capability requirements:

- Telegram may satisfy `ProtectContent.REQUIRED` only with verified native protection behavior.
- Bale must fail closed when equivalent required protection is unavailable.
- Provider layer may adapt formatting/edit behavior, never authorization or entitlement truth.
- Protected send failure must report through the existing receipt semantics; it must not silently downgrade.

## Website handoff requirements

Integration supplies only canonical HTTPS destinations from application configuration/router:

- resource/detail or safe web delivery where appropriate;
- assessment catalog/detail/attempt;
- purchase/access center, browser checkout and retry;
- forms list/detail/submission.

Never place download capabilities, storage keys, provider IDs, checkout secrets, CSRF material or HMAC data in button URLs.

## Merge assumptions

- Merge bot-01/core before wiring these imports; this workstream was aligned to bot-01 commit `faead96a4bedca34151562c81c712ae42b2a7693` without importing its files into this branch.
- Application integration must translate learning `CallbackIntent` names into current routing and re-read canonical state before actions.
- bot-04 owns provider rendering and protected-send capability checks.
- No new backend projection is assumed by the native paths marked as gaps above.
