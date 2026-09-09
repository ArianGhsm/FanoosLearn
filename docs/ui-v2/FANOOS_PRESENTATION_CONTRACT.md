# FANOOS UI V2 — Presentation Contract

This contract normalizes language and status semantics across Website, Telegram and Bale. Visual composition is channel-specific; meaning is shared.

## Tone and Persian

- concise, factual Persian; no developer/debug vocabulary in normal user copy;
- use نیم‌فاصله in established compounds such as `به‌روزرسانی`, `می‌شود`, `خوانده‌شده`;
- Persian punctuation and readable RTL sentence order;
- canonical product labels remain stable: خانه، امروز، درس‌ها، برنامه، جلسات، منابع، جزوه‌ها، خلاصه‌ها، بانک سؤال، آزمون‌ها، نمرات، اطلاعیه‌ها، اعلان‌ها، خرید و دسترسی، فضای آموزشی، حساب، مدیریت، به‌روزرسانی سرور;
- technical identifiers/tokens remain LTR and must normally be hidden from end users. UUIDs, storage keys, provider codes, service IDs and signed capabilities are not presentation labels.

## Status vocabulary

| Canonical state | Persian |
| --- | --- |
| `pending` | در انتظار |
| `payment_pending` | در انتظار پرداخت |
| `paid` / verified success | پرداخت تأیید شده |
| `failed` | ناموفق |
| `canceled` / `cancelled` | لغوشده |
| access active | دسترسی فعال / فعال |
| `expired` | منقضی‌شده |
| `revoked` | لغوشده / دسترسی لغوشده according to noun context |
| protected content | دسترسی محافظت‌شده / محتوای محافظت‌شده |
| unavailable | در دسترس نیست |
| permission denied | اجازه انجام این عملیات را ندارید |
| published | منتشرشده |
| in progress | در حال انجام |
| completed | تکمیل‌شده |

`pending` must not silently mean `payment_pending`. Payment and entitlement labels remain separate even when displayed together.

## Icons and emoji

Website uses the V2 vector/icon system; emoji are not primary Website navigation icons. Bots use bounded semantic emoji:

- 🏠 home;
- 📚 course/resource learning context;
- 📅 schedule/today;
- 📝 assessment;
- 🎓 grades;
- 📢 announcement;
- 🔔 personal notification concept;
- 💳 purchase/payment/access;
- 🏫 workspace;
- 👤 account;
- ⚙️ management/update control where authorized;
- 🔒 protected content.

Emoji never changes authorization or state meaning.

## Dates and time

- canonical calendar boundary: workspace IANA timezone (`tenant_workspaces.timezone_name`);
- Website may use Persian-calendar display where its UI formatter already does so, while API range keys remain Gregorian `YYYY-MM-DD`;
- Bots use compact Persian-digit time/date formatting;
- time must be displayed after conversion to canonical workspace timezone;
- host/server/device timezone is not authority.

## Digits

Human-facing numbers use Persian digits where the channel formatter supports them. Opaque IDs, hashes, URLs, API tokens, version identifiers that must remain machine-readable and other technical strings stay ASCII/LTR. Do not transform UUID/hash digits.

## Money

- always pair amount with canonical currency;
- IRR is rendered as ریال; no implicit rial↔toman conversion;
- unknown currency is never guessed;
- amount shown in purchase UI must be the backend price/order snapshot, not client recomputation.

## Empty states

Pattern: state fact + next safe action when one exists. Examples:

- `برای امروز برنامه‌ای ثبت نشده است.`
- `نمره منتشرشده‌ای برای شما پیدا نشد.`
- `منبع قابل‌دسترسی‌ای پیدا نشد.`

Do not imply that a missing projection means the underlying business object does not exist.

## Error grammar

User-facing errors describe the action/state, never raw exception/provider text. Security-sensitive errors intentionally collapse detail:

- foreign workspace → `این فضای آموزشی برای حساب شما در دسترس نیست.`
- forbidden action → `اجازه انجام این عملیات را ندارید.`
- expired route/capability → ask the user to restart from the canonical destination;
- service fault → safe retry wording without stack trace/provider payload.

## Success grammar

Success wording identifies the canonical outcome only after the backend confirms it: `پاسخ‌ها روی سرور ذخیره شدند`, `آزمون ثبت و توسط سرور امتیازدهی شد`, `فضای آموزشی فعال تغییر کرد`.

Never say payment/access succeeded based only on a client redirect or provider-page state.

## Action verbs

Use concrete verbs: `باز کردن`, `انتخاب`, `ثبت پاسخ`, `شروع تلاش`, `ذخیره موقت`, `ثبت نهایی پاسخ‌ها`, `دریافت امن`, `قطع اتصال`. Avoid developer verbs such as fetch/reconcile/consume in ordinary UX unless the action is specifically an operator function.

## Protected content wording

- describe access as protected, not as a guarantee that copying is impossible;
- Website: `دریافت امن` after backend authorization and same-origin capability redemption;
- Telegram: provider-native protected send when the canonical delivery contract requires it;
- Bale: if required forward protection is unavailable, explicitly state that sending was not performed; never fall back to the unprotected original.

## Payment and access wording

- `وضعیت پرداخت` reports canonical order/payment verification;
- `دسترسی` reports entitlement independently;
- `پرداخت تأیید شده` does not by itself mean `دسترسی فعال` unless backend entitlement projection confirms it.

## Channel limits

Website can host complex forms, assessment attempts, checkout and downloadable binary delivery. Telegram/Bale should link to Website when the canonical backend capability exists but a safe native presentation/interaction contract does not. A fallback must not contain permanent signed capabilities, raw storage keys or secrets.
