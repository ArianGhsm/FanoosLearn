# FANOOS Bot V3 — Learning & Commerce Screen Specs

Design Lock: `FANOOS-UX-2026.09-R1`  
Workstream: `03_LEARNING_COMMERCE`  
Channel model: provider-neutral semantics; Telegram/Bale rendering is owned by bot-04.

## Product grammar

These screens are compact product surfaces, not endpoint output. Each screen has one purpose, bounded content, predictable exits and no raw UUID/provider/storage/payment identifiers in visible copy.

Normal action hierarchy:

1. one full-width high-value action when one exists;
2. up to two short filter/navigation actions per row;
3. pagination in one row;
4. Back/Home at the bottom.

Backend truth is never reconstructed in presentation code. Resource authorization, protected delivery, payment verification, entitlement and assessment scoring remain canonical backend decisions.

## Screen inventory

| Family | Screen kind | Primary purpose | Canonical input | Normal next action |
| --- | --- | --- | --- | --- |
| Resources | `learning.resource_hub` | Browse bounded authorized resources | internal resource catalog | open detail / filter / page |
| Resources | `learning.resource_detail` | Understand resource before delivery | authorized resource row | secure delivery |
| Protected | `learning.protected.checking` | Explain access check | delivery state | check again |
| Protected | `learning.protected.preparing` | Explain derivative preparation | protected-media state | refresh |
| Protected | `learning.protected.ready` | Durable ready state | authorized result | provider delivery |
| Protected | `learning.protected.expired` | Expired correlation/capability | safe state only | restart from resource |
| Protected | `learning.protected.denied` | Current access denied | canonical denial | access center / safe web |
| Protected | `learning.protected.unsupported_channel` | Required channel protection unavailable | provider capability + canonical requirement | safe web if supplied |
| Protected | `learning.protected.temporary_failure` | Retryable service failure | safe failure taxonomy | retry |
| Assessments | `learning.assessment_hub` | Active/upcoming/practice/past destination | bot-safe projection if one exists | detail / website |
| Assessments | `learning.assessment_detail` | Title/course/deadline/state | canonical metadata if exposed | safe website handoff |
| Commerce | `learning.commerce_hub` | Human order/access summary | bot-safe summaries if exposed | order / website |
| Commerce | `learning.order_access_detail` | Separate order/payment/access truth | order + payment + entitlement projections | refresh / web |
| Forms | `learning.forms_hub` | List forms or explain safe handoff | bot-safe list if exposed | detail / website |
| Forms | `learning.form_detail` | Human form metadata | canonical metadata if exposed | website submission |
| Shared | `learning.<domain>.<state>` | empty/error/denied/unavailable | safe state | retry/back/web |

## Resources

### Hub

Current canonical bot resource projection is sufficient for a real native hub. It returns only already-authorized catalog rows, with stable resource/version identifiers for action correlation and no storage path/key. The UI displays at most 8 items per screen even if the backend page is larger.

Entry choices are exposed only when canonical context exists:

- `درس` when canonical course choices are supplied;
- `نوع` when canonical resource types are supplied;
- `تازه‌ها` because the current bot catalog is canonically ordered by resource update time;
- pagination from the canonical opaque cursor.

Example:

```text
📚 منابع و یادگیری
منابع مجاز فضای آموزشی شما

📚 کتابخانه
• اندو ۱ — جلسه ۳
  اندودانتیکس ۱ · جزوه
  نسخهٔ جاری مجاز · قابل دریافت
• بانک سؤال ترمیمی
  ترمیمی ۱ · بانک سؤال
  نسخهٔ جاری مجاز · قابل دریافت

[ درس ] [ نوع ]
[ تازه‌ها ]
[ ‹ قبلی ] [ بعدی › ]
[ ‹ بازگشت ] [ 🏠 خانه ]
```

No `resource_id`, `resource_version_id`, object ID or cursor is rendered.

### Empty hub

```text
📚 منابع و یادگیری
درس: پریو ۱ · نوع: خلاصه

📚 کتابخانه
منبعی با این فیلترها پیدا نشد.
فیلترها را تغییر دهید یا بعداً دوباره بررسی کنید.

[ درس ] [ نوع ]
[ تازه‌ها ]
[ ‹ بازگشت ] [ 🏠 خانه ]
```

This is intentionally richer than a warning plus website button.

### Resource detail

Required facts:

- title;
- course when supplied;
- localized type;
- version state;
- access state;
- protection state if canonical projection exposes one;
- optional topic/professor/format;
- `🔒 دریافت امن` only when canonical projection says direct delivery is supported.

The current internal projection exposes `resource_version_id` but no human version number/name, so V3 says `نسخهٔ جاری مجاز`; it never converts the UUID into a user-facing version label.

If no protected-state projection is exposed, copy is explicit: `هنگام دریافت بر اساس سیاست منبع بررسی می‌شود`.

## Protected delivery

Presentation does not issue or redeem delivery tokens. Integration/current application performs the canonical issue/consume/derivative flow; this workstream only defines screen states and action intents.

### Checking access

```text
🔒 بررسی دسترسی
دسترسی شما به «جزوه اندو ۱» در حال بررسی است.

وضعیت
این مرحله فقط وضعیت فعلی مجوز و دسترسی را از backend می‌گیرد.

[ 🔄 بررسی دوباره ]
[ ‹ بازگشت ] [ 🏠 خانه ]
```

No fake spinner percentage or ETA.

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

```text
✅ نسخه محافظت‌شده آماده است
«جزوه اندو ۱» برای تحویل امن آماده است.

وضعیت
ارسال فقط با سیاست حفاظتی تأییدشده و مجوز فعلی انجام می‌شود.
```

Semantic requirements:

- `ProtectContent = required`;
- durable/new-message edit policy;
- provider send is owned by bot-04;
- provider failure cannot replay delivery authorization/business logic.

### Denied — required example

```text
🔒 دسترسی به این منبع فعال نیست
backend دریافت «جزوه اندو ۱» را برای وضعیت فعلی حساب تأیید نکرد.

وضعیت
اگر اخیراً خرید یا دسترسی شما تغییر کرده است، وضعیت را از بخش خرید و دسترسی بررسی کنید.

[ 💳 خرید و دسترسی ]
[ 🌐 بررسی در فانوس ]     ← only when integration supplies a safe HTTPS destination
[ ‹ بازگشت ] [ 🏠 خانه ]
```

Security properties:

- do not distinguish secret authorization internals;
- do not expose entitlement IDs or failure payloads;
- do not promise that payment automatically grants access;
- a new delivery action must reauthorize.

### Unsupported channel — required example

Bale example when canonical delivery requires forward/save protection and current Bale capability cannot satisfy it:

```text
⚠️ ارسال محافظت‌شده در این پیام‌رسان ممکن نیست
«جزوه اندو ۱» به حفاظتی نیاز دارد که در بله تأیید نشده است.

وضعیت
نسخهٔ بدون حفاظت ارسال نمی‌شود. این محدودیت امنیتی عمداً fail-closed است.

[ 🌐 دریافت امن در فانوس ]  ← only when a safe canonical web route is supplied
[ ‹ بازگشت ] [ 🏠 خانه ]
```

There is no fallback to original source bytes, cached provider file IDs or a less-protected document.

### Expired

Expired correlation/capability does not get retried in place. User restarts from resource detail so authorization is fresh.

### Temporary failure

Temporary failure is not displayed as denied and is not displayed as success. `🔄 تلاش دوباره` starts the canonical flow again through integration; presentation itself does not replay a mutation.

## Assessment hub

The source builder supports active/upcoming/completed/practice/past-exam grouping **only when a bot-safe canonical projection is supplied**. No grouping data is synthesized from website HTML or local bot storage.

At this base SHA the internal bot API exposes no assessment catalog/detail/attempt/result contract, while the browser Core API does expose canonical assessments and attempt flows. Therefore the current integration state must use the explicit safe handoff variant:

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

### Assessment detail

When a future bot-safe projection exists, detail is limited to:

- title;
- course;
- deadline;
- canonical state;
- safe website handoff.

No question payload, answer key, scoring formula or client-side answer state belongs in this workstream.

## Purchase & access

The normal bot UX never asks the human for `/buy <product_id>` or renders a raw product/order identifier.

At this base SHA internal commerce supports:

- create order only when product ID is already known by a compatible technical path;
- read order status only when order ID is already known;
- order title snapshot;
- amount/currency;
- order status;
- entitlement `granted` boolean.

It does **not** expose bot-safe product catalog, order history or complete entitlement/access list. Therefore the hub truthfully shows these as projection gaps and provides the browser center when configured.

### Order/payment/access detail

Three status lines are always separate:

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

The state above is valid and must not be collapsed. A user can have an already-active entitlement while a newer order/payment is still pending. The UI therefore says both facts independently.

Likewise this is possible and must remain explicit:

```text
وضعیت سفارش: پرداخت تأیید شده
وضعیت پرداخت: پرداخت تأیید شده
دسترسی: دسترسی فعال نیست
```

The UI must not change the last line to `دسترسی فعال` merely because payment is paid. Only canonical entitlement truth can do that.

If the current bot projection does not contain a distinct payment-status field, V3 renders:

`وضعیت پرداخت: در projection فعلی جداگانه گزارش نشده`

rather than copying `order.status` into a second label and pretending they are independent facts.

Money rules:

- use backend snapshot amount;
- always show canonical currency;
- IRR is rendered as `ریال`;
- never silently convert rial to toman.

## Forms / secondary services

Core browser API has canonical open-form listing and submission, but internal bot API has no bot-safe forms projection at this base SHA. Current bot V3 therefore uses:

```text
📝 فرم‌ها و خدمات
ثبت پاسخ فقط از مسیر canonical انجام می‌شود.

فرم‌ها و خدمات
فرم‌های فعال در API وب canonical هستند، اما projection امن اختصاصی ربات در قرارداد فعلی وجود ندارد.

[ 🌐 باز کردن فرم‌ها در فانوس ]
[ ‹ بازگشت ] [ 🏠 خانه ]
```

The source can render a list/detail if a future bot-safe projection is supplied, but native submission remains off until an explicit canonical bot submission contract exists.

## Domain empty/error family

Every domain can render:

- `empty` — no current items; normal-state explanation + back/retry;
- `denied` — permission/access not confirmed; no sensitive details;
- `unavailable` — channel capability absent; safe alternative if supplied;
- `error` — temporary read failure; stale data not presented as current.

Error grammar always answers:

1. what happened;
2. whether recovery is possible;
3. what to do next.

## Action intent contract for bot-04

Protected-related intents that bot-04 must recognize after bot-01/application routing binds them:

| Intent | Meaning | Provider requirement |
| --- | --- | --- |
| `learning.resource.deliver` | begin canonical protected-delivery flow | no provider send before backend result |
| `learning.protected.check` | re-read/restart authorization check | activity feedback allowed; no fake progress |
| `learning.protected.refresh` | check prepared derivative state | no business replay on render failure |
| `learning.protected.retry` | retry after temporary failure through application | must invoke canonical flow once |
| `learning.protected.resource` | return/restart from resource context | no expired capability reuse |

`learning.protected.ready` semantic screen with `ProtectContent=required` means:

- Telegram: use the verified protected-send capability required by the final delivery result;
- Bale: if equivalent required protection is unavailable, refuse direct delivery and render `unsupported_channel`;
- neither provider may downgrade to an unprotected original.

## Website handoff requirements

Integration should supply HTTPS destinations from canonical application configuration/router, never from provider payloads or resource capability URLs.

Required destinations:

- resources/detail when web delivery is the safe alternative;
- assessments catalog/detail/attempt;
- purchase/access center and checkout/retry;
- forms list/detail/submission.

Do not put permanent signed download capabilities, storage keys, checkout secrets or CSRF material into buttons.

## Source assumptions for merge

bot-01/core is a parallel dependency and had not materialized on its branch when this workstream was authored. `screens.py` codes to the Design Lock concepts and assumes bot-01 exports:

`Screen`, `Section`, `Fact`, `ListItem`, `Action`, `ActionRow`, `Pagination`, `Severity`, `Context`, `ProtectContent`, `EditPolicy`.

The merge worker must reconcile only constructor/field naming if bot-01 chooses different exact Python signatures. Domain semantics, copy, actions and security boundaries should remain unchanged.
