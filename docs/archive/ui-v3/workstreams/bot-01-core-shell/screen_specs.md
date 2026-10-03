# FANOOS V3 Bot Core — Screen Specs

Design Lock: `FANOOS-UX-2026.09-R1`  
Workstream: `01_CORE_SHELL_ONBOARDING`  
Channel semantics: provider-neutral Telegram + Bale

These examples describe semantic output. Telegram may render them as Rich Message blocks and Bale may render the same meaning as native text. Inline actions stay outside the text body.

## 1. Unlinked account

```text
👋 خوش آمدید به فانوس

این پیام‌رسان هنوز به حساب فانوس شما متصل نیست.

🔗 اتصال حساب
از وب‌سایت فانوس یک درخواست اتصال تازه بسازید و همان لینک یا کد را در این پیام‌رسان باز کنید.
• وارد حساب فانوس شوید.
• از بخش حساب، اتصال پیام‌رسان را شروع کنید.
• درخواست تازه را همین‌جا باز کنید.

[ 🌐 باز کردن فانوس ]
[ ℹ️ راهنمای شروع ] [ 🏠 خانه ]
```

No subject ID, user ID, challenge token, link ID, or backend error is rendered.

## 2. Linked account — zero workspace (critical)

```text
🏠 فانوس

حساب شما متصل است ✅
هنوز فضای آموزشی فعالی برای این حساب ندارید.

از اینجا چه کاری می‌توانید انجام دهید؟
عضویت فضای آموزشی از اطلاعات رسمی فانوس می‌آید. این ربات فضای آموزشی تازه‌ای ایجاد نمی‌کند.

[ 🏫 فضای آموزشی ]
[ 👤 حساب ] [ ℹ️ راهنمای شروع ]
[ 🌐 باز کردن فانوس ]
[ 🏠 خانه ]
```

This state deliberately remains a complete product surface. Empty domain data never collapses navigation. There is **no** create-workspace button because the frozen bot backend exposes workspace list/select, not workspace creation.

## 3. Linked account — one workspace

Selected:

```text
🏫 فضای آموزشی شما
فضای آموزشی: دندان‌پزشکی · ورودی ۱۴۰۲

حساب متصل است و یک فضای آموزشی برای شما پیدا شد.
وضعیت: فعال

[ 🏠 خانه ]
[ 👤 حساب ] [ ℹ️ راهنمای شروع ]
[ 🌐 باز کردن فانوس ]
```

Not yet selected:

```text
[ انتخاب دندان‌پزشکی · ورودی ۱۴۰۲ ]
```

The selection callback carries only presentation routing input. The integration worker must call canonical `/messaging/workspaces/select`; selection never grants membership by itself.

## 4. Linked account — multiple workspaces

```text
🏫 انتخاب فضای آموزشی

حساب شما به بیش از یک فضای آموزشی دسترسی دارد. فضای موردنظر را انتخاب کنید.

[ 🏫 فضای آموزشی ]
[ 👤 حساب ] [ ℹ️ راهنمای شروع ]
[ 🌐 باز کردن فانوس ]
[ 🏠 خانه ]
```

The workspace list screen then renders a bounded page with `✓` on the selected item and callbacks for the other items.

## 5. Expired link/challenge

```text
⌛ درخواست اتصال منقضی شده

این درخواست دیگر قابل استفاده نیست. از فانوس یک درخواست اتصال تازه بگیرید.

[ 🌐 باز کردن فانوس ]
[ ℹ️ راهنمای شروع ] [ 🏠 خانه ]
```

Invalid/used challenge codes should use the same safe family of copy; raw provider/backend detail is not presentation text.

## 6. Service unavailable

```text
⚠️ اتصال به فانوس ممکن نیست

در حال حاضر وضعیت حساب را نمی‌توان با اطمینان بررسی کرد.
هیچ وضعیت قبلی به‌عنوان اطلاعات تازه نمایش داده نمی‌شود.

[ 🔄 تلاش دوباره ]
[ 🌐 باز کردن فانوس ]
[ ℹ️ راهنمای شروع ] [ 🏠 خانه ]
```

## 7. Active Home

```text
🏠 خانه
فضای آموزشی: دندان‌پزشکی تهران · ورودی ۱۴۰۲ · امروز

📅 برنامه
• ترمیمی ۱ — ۰۸:۳۰
دانشکده دندان‌پزشکی
• ۳ جلسه برای امروز

📢 تازه
• تغییر محل کلاس پریو

[ 📚 درس‌ها ] [ 📅 برنامه ]
[ 🎓 نمرات ] [ 🔔 اعلان‌ها ]
[ 📚 منابع ] [ 📝 آزمون‌ها ]
[ 👤 حساب ] [ بیشتر ]
```

`HomeSlot` supports `CONTENT`, `EMPTY`, and `UNAVAILABLE`. The integration worker decides which state is true from canonical projections; the core does not infer empty from a failed request.

## 8. Workspace list

```text
🏫 فضای آموزشی

فضای فعال را ببینید یا یکی از فضاهای در دسترس را انتخاب کنید.

✓ دندان‌پزشکی تهران · ورودی ۱۴۰۲
فضای آموزشی فعال
• انجمن علمی دانشجویی
• دوره پژوهشی

[ انتخاب انجمن علمی دانشجویی ]
[ انتخاب دوره پژوهشی ]
[ بازگشت ] [ 🏠 خانه ]
```

A page contains at most five workspace options in this builder. Larger projections must use `Pagination`; provider callbacks may use a short subject-bound route ref.

## 9. Workspace empty

```text
🏫 فضای آموزشی

برای این حساب هنوز عضویت فعالی در یک فضای آموزشی ثبت نشده است.
فانوس فقط عضویت‌های ثبت‌شده در backend را نمایش می‌دهد و ربات فضای آموزشی جدیدی ایجاد نمی‌کند.

[ 👤 حساب ] [ ℹ️ راهنمای شروع ]
[ 🌐 باز کردن فانوس ]
[ 🏠 خانه ]
```

## 10. Workspace switch success

```text
✅ فضای آموزشی تغییر کرد
فضای آموزشی فعال: دندان‌پزشکی تهران · ورودی ۱۴۰۲

از این پس صفحه‌های بعدی با زمینه این فضای آموزشی باز می‌شوند.

[ 🏠 خانه ]
```

Render this only after backend selection succeeds.

## 11. Account

```text
👤 حساب
حساب: متصل

اتصال این پیام‌رسان به حساب فانوس فعال است.
وضعیت: متصل ✅
پیام‌رسان: تلگرام
فضاهای آموزشی: ۲
فضای فعال: دندان‌پزشکی تهران · ورودی ۱۴۰۲

[ 🏫 فضای آموزشی ] [ 🌐 باز کردن فانوس ]
[ قطع اتصال ]
[ بازگشت ] [ 🏠 خانه ]
```

The screen intentionally has no raw platform subject, UUID, link ID, canonical user ID, or username-as-identity claim.

## 12. Unlink confirmation

```text
⚠️ قطع اتصال پیام‌رسان

اتصال تلگرام به حساب فانوس قطع شود؟
این کار فقط اتصال همین پیام‌رسان را حذف می‌کند؛ حساب اصلی فانوس حذف نمی‌شود. برای استفاده دوباره باید اتصال تازه‌ای بسازید.

[ بله، قطع شود ]
[ لغو ]
[ 🏠 خانه ]
```

The destructive action is isolated from routine navigation. Confirmation itself carries no user/link identity; integration invokes the signed subject-bound revoke endpoint.

## 13. Unlink success

```text
✅ اتصال قطع شد

این پیام‌رسان دیگر به حساب فانوس متصل نیست. حساب اصلی فانوس شما تغییری نکرده است.

[ 🌐 باز کردن فانوس ]
[ 🏠 خانه ]
```

Only show after canonical revoke succeeds.

## 14. Getting started

```text
ℹ️ راهنمای شروع

کارهای معمول فانوس از دکمه‌ها انجام می‌شوند و نیازی به حفظ دستورهای متنی ندارید.

شروع سریع
🏫 فضای آموزشی را بررسی یا انتخاب کنید.
🏠 از خانه وارد درس‌ها، برنامه، منابع یا نمرات شوید.
👤 برای تنظیمات اتصال، بخش حساب را باز کنید.

[ 🏫 فضای آموزشی ] [ 👤 حساب ]
[ 🌐 باز کردن فانوس ]
[ 🏠 خانه ]
```

## 15. Standard state builders

### Empty

An empty screen states what is empty and offers the next safe action plus back/home. It must not imply that a failed/missing projection proves the business object does not exist.

### Error

```text
❌ مشکلی پیش آمد

<safe user-facing message>

[ 🔄 تلاش دوباره ]
[ بازگشت ] [ 🏠 خانه ]
```

### Unavailable

```text
⚠️ دسترسی موقتاً ممکن نیست

این بخش فعلاً در دسترس نیست. می‌توانید دوباره تلاش کنید یا به خانه برگردید.
اطلاعات قبلی را به‌عنوان وضعیت تازه در نظر نگیرید.

[ 🔄 تلاش دوباره ]
[ بازگشت ] [ 🏠 خانه ]
```

### Success

A success screen names only a backend-confirmed outcome and provides the next useful action/home.

## 16. Provider-neutral action grammar

Stable action identifiers owned here:

| Identifier | Persian label / role |
| --- | --- |
| `core.home` | 🏠 خانه |
| `core.back` | بازگشت |
| `core.cancel` | لغو |
| `core.retry` | 🔄 تلاش دوباره |
| `core.website.open` | 🌐 باز کردن فانوس |
| `core.help` | ℹ️ راهنمای شروع |
| `core.more` | بیشتر |
| `core.courses` | 📚 درس‌ها |
| `core.schedule` | 📅 برنامه |
| `core.grades` | 🎓 نمرات |
| `core.notifications` | 🔔 اعلان‌ها |
| `core.resources` | 📚 منابع |
| `core.assessments` | 📝 آزمون‌ها |
| `core.workspace` | 🏫 فضای آموزشی |
| `core.workspace.select` | switch candidate; backend reauthorizes |
| `core.account` | 👤 حساب |
| `core.account.unlink.request` | enter confirmation |
| `core.account.unlink.confirm` | destructive canonical revoke |

Action identifiers express presentation intent only. Provider callback encoding is separate from backend authorization.
