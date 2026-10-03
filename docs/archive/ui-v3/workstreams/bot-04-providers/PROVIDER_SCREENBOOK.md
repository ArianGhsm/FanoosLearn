# FANOOS Rebuild V3 — Telegram + Bale Provider Screenbook

Design Lock: `FANOOS-UX-2026.09-R1`  
Workstream: `bot-04-providers`  
Purpose: representative provider grammar for later integration; this document is source documentation, not runtime validation.

## 1. Provider grammar

### Telegram

- semantic title → Rich Message `h3`;
- context → compact paragraph;
- warning/error/success intro → block quotation;
- ordinary intro/body → paragraph;
- section title → `h4`;
- facts/lists → native unordered list;
- Persian semantic output → `is_rtl=true`;
- action layer → inline keyboard;
- normal Rich failure → at most one plain rendering of the same already-computed screen;
- edit failure → at most one new rendering of the same already-computed screen;
- neither fallback may invoke the application/business action again;
- protected output → one new provider operation with `protect_content=true`; no Rich→plain second-send fallback after an ambiguous provider result.

### Bale

- title and safe section headings use Bale's documented Markdown emphasis;
- body remains compact Persian text with bullets and whitespace hierarchy;
- action layer uses only documented inline keyboard fields;
- navigation/status screens may edit when the integration has a current message ID;
- Telegram Rich fields, Telegram RTL fields and Telegram deployment controls are absent;
- required protected delivery maps to a safe explanatory screen and the protected original is not sent.

## 2. Action-row grammar

Packing is deterministic:

1. strong primary, destructive and long actions → one full-width row;
2. routine short actions → two columns when both labels fit the safe display budget;
3. pagination → its own bottom row, up to two buttons;
4. Back/Home → final row when both fit, otherwise separate rows;
5. destructive actions never share a row with routine navigation;
6. callback payloads are validated by UTF-8 byte count against the provider's 64-byte limit.

Bracket notation below represents one inline-keyboard row.

---

## 3. Unlinked

### Text hierarchy

```text
🏠 فانوس

برای دیدن اطلاعات شخصی و آموزشی، ابتدا حساب فانوس را به این پیام‌رسان متصل کنید.
```

### Rows

```text
[ 🔗 اتصال حساب ]
[ ℹ️ راهنمای شروع ]
[ 🏠 خانه ]
```

Telegram uses Rich heading + paragraph. Bale uses its native text structure. No account identity is inferred from messenger display data.

---

## 4. Linked account — no workspace

This is the critical Design Lock zero-state. It must never collapse to a quote plus a single website button.

### Text hierarchy

```text
🏠 فانوس

حساب شما متصل است ✅
هنوز فضای آموزشی فعالی برای این حساب ندارید.
```

### Rows

```text
[ 🏫 فضای آموزشی ]
[ 👤 حساب من ] [ ℹ️ راهنمای شروع ]
[ 🌐 باز کردن فانوس ]
[ 🏠 خانه ]
```

The workspace/account/help/web/home structure remains usable even with no academic data.

---

## 5. Home — active workspace

### Text hierarchy

```text
🏠 خانه
دندان‌پزشکی تهران · ورودی ۱۴۰۲

📅 بعدی
ترمیمی ۱ — ۰۸:۳۰
دانشکده دندان‌پزشکی

📢 تازه
زمان آزمون میان‌ترم ترمیمی ۱ اعلام شد.
```

### Rows

```text
[ 📚 درس‌ها ] [ 📅 برنامه ]
[ 🎓 نمرات ] [ 🔔 اعلان‌ها ]
[ 📚 منابع ] [ 📝 آزمون‌ها ]
[ 👤 حساب ] [ ➕ بیشتر ]
```

No ranking or recommendation is implied; order follows the canonical home priority.

---

## 6. Courses

### Text hierarchy

```text
📚 درس‌ها
نیمسال جاری

• ترمیمی ۱ · REST-301
• پریودانتیکس ۱ · PERIO-301
• رادیولوژی ۱ · RAD-301

صفحه ۱ از ۲
```

### Rows

```text
[ ترمیمی ۱ ] [ پریودانتیکس ۱ ]
[ رادیولوژی ۱ ]
[ صفحه بعد ]
[ 🏠 خانه ]
```

Canonical IDs may live inside opaque callback semantics, but are never shown in text.

---

## 7. Course detail

### Text hierarchy

```text
📚 ترمیمی ۱
درس‌ها › ترمیمی ۱

کد درس: REST-301
نیمسال: جاری

قدم بعدی
کلاس بعدی: شنبه، ۰۸:۳۰
```

### Rows

```text
[ 📅 برنامه ] [ 📚 منابع ]
[ 🎓 نمرات ] [ 📝 آزمون‌ها ]
[ 📢 اطلاعیه‌ها ]
[ بازگشت به درس‌ها ] [ 🏠 خانه ]
```

A native assessment or course-announcement action must still respect the canonical projection available at integration time; the provider renderer does not fabricate missing data.

---

## 8. Schedule

### Text hierarchy

```text
📅 برنامه امروز
دندان‌پزشکی تهران · پنجشنبه ۱۹ شهریور

۰۸:۳۰
ترمیمی ۱
دانشکده دندان‌پزشکی

۱۰:۳۰
پریودانتیکس ۱
کلینیک پریو
```

### Rows

```text
[ امروز ] [ فردا ]
[ ۷ روز آینده ]
[ بازگشت ] [ 🏠 خانه ]
```

All displayed dates/times must already be resolved from canonical workspace timezone semantics upstream.

---

## 9. Grades

### Text hierarchy

```text
🎓 نمرات
نمرات منتشرشده

• ترمیمی ۱ — ۱۷٫۵ از ۲۰
• رادیولوژی ۱ — ۱۸ از ۲۰
• پریودانتیکس ۱ — هنوز نمره‌ای منتشر نشده
```

### Rows

```text
[ بازگشت ] [ 🏠 خانه ]
```

No GPA/average is calculated in presentation code.

---

## 10. Announcements

### Text hierarchy

```text
📢 اطلاعیه‌ها

• زمان آزمون میان‌ترم ترمیمی ۱ اعلام شد.
• فایل جلسه جدید رادیولوژی ۱ منتشر شد.

صفحه ۱ از ۳
```

### Rows

```text
[ صفحه بعد ]
[ بازگشت ] [ 🏠 خانه ]
```

`اطلاعیه‌ها` remains distinct from a personal notification inbox.

---

## 11. Resources

### Text hierarchy

```text
📚 منابع
ترمیمی ۱

• جزوه جلسه ۵ · PDF · دسترسی فعال
• خلاصه جلسه ۴ · PDF · دسترسی فعال
• بانک سؤال فصل ۲ · محافظت‌شده
```

### Rows

```text
[ جزوه جلسه ۵ ] [ خلاصه جلسه ۴ ]
[ بانک سؤال فصل ۲ ]
[ بازگشت ] [ 🏠 خانه ]
```

The renderer displays authorization results only; it does not grant access or receive storage keys.

---

## 12. Protected — denied

### Text hierarchy

```text
🔒 محتوای محافظت‌شده

امکان ارسال این فایل برای حساب شما تأیید نشد. دسترسی یا وضعیت منبع را دوباره بررسی کنید.
```

### Rows

```text
[ بازگشت به منابع ] [ 🏠 خانه ]
```

No provider error, token, capability or storage identifier is shown.

---

## 13. Protected — ready

### Telegram

```text
🔒 دریافت امن

ترمیمی ۱ · جزوه جلسه ۵

نسخه محافظت‌شده آماده ارسال است.
```

Rows:

```text
[ بازگشت به منابع ] [ 🏠 خانه ]
```

Intent:

```text
new message
protect_content = true
atomic = true
no second provider operation after ambiguous send failure
```

### Bale fail-closed mapping

```text
🔒 ارسال محافظت‌شده

🔒 این محتوا باید به‌صورت محافظت‌شده ارسال شود، اما این قابلیت در این پیام‌رسان در دسترس نیست. فایل ارسال نشد.
```

Rows:

```text
[ بازگشت به منابع ] [ 🏠 خانه ]
```

The protected original is not silently downgraded to an ordinary Bale message.

---

## 14. Payment / access

### Text hierarchy

```text
💳 خرید و دسترسی

پرداخت و دسترسی دو وضعیت مستقل هستند.

وضعیت پرداخت: پرداخت تأیید شد
دسترسی: در انتظار فعال‌سازی
```

### Rows

```text
[ 🌐 مشاهده خریدها ]
[ بازگشت ] [ 🏠 خانه ]
```

A successful-looking provider/browser state never becomes an entitlement grant in the renderer.

---

## 15. Account

### Text hierarchy

```text
👤 حساب

اتصال پیام‌رسان: فعال
فضای آموزشی: دندان‌پزشکی تهران · ورودی ۱۴۰۲
```

### Rows

```text
[ 🏫 تغییر فضای آموزشی ]
[ 🌐 باز کردن حساب فانوس ]
[ قطع اتصال پیام‌رسان ]
[ بازگشت ] [ 🏠 خانه ]
```

The destructive unlink action is isolated from routine navigation.

---

## 16. Error

### Text hierarchy

```text
❌ خطا

اطلاعات این صفحه دریافت نشد.

می‌توانید دوباره تلاش کنید.
```

### Rows

```text
[ 🔄 تلاش دوباره ]
[ 🏠 خانه ]
```

Retry must be attached only where repeating the presentation/read is safe and cannot replay a completed business mutation.

---

## 17. Owner management — Telegram only

This screen is renderable only when integration supplies both:

- private Telegram context;
- canonical `deployment.manage` permission from the backend projection.

### Telegram text hierarchy

```text
⚙️ مدیریت
تلگرام خصوصی

وضعیت
سرویس سالم است.
نسخه جدید برای بررسی موجود است.
```

### Rows

```text
[ 🔄 به‌روزرسانی سرور ]
[ آخرین وضعیت ]
[ بازگشت ] [ 🏠 خانه ]
```

There is no text field or action for arbitrary repository/ref/SHA/path/command/shell input.

### Bale

No deployment action is rendered. A direct attempt to render the owner surface maps to:

```text
⚙️ مدیریت

مدیریت سرور از بله انجام نمی‌شود. این بخش فقط در تلگرام خصوصی و پس از تأیید دسترسی مدیریتی در دسترس است.
```

Only safe navigation may remain.

---

## 18. Edit/new-message policy

| Situation | Telegram | Bale |
| --- | --- | --- |
| ordinary navigation + current message ID + `edit_if_safe` | edit preferred | edit preferred |
| edit unavailable/fails | one new rendering of same screen | one new rendering of same screen |
| durable/important/new-message policy | new message | new message |
| protected required | new protected operation, no edit | protected original refused; safe explanatory mapping only |
| Rich render/send failure | one plain render of same computed screen | not applicable |
| business action after any presentation failure | never replayed by renderer | never replayed by renderer |

Callback interactions carry metadata requiring provider callback acknowledgement **before business work**. Integration retains existing backend idempotency and processed-update protections.

## 19. Capability basis

Capability review date: **2026-09-10**.

- Telegram: <https://core.telegram.org/bots/api> — Bot API 10.3; Rich Messages, RTL, rich editing, inline keyboards, callback data limit and `protect_content` are documented.
- Bale: <https://docs.bale.ai/> — `sendMessage`, Markdown text formatting, inline keyboards, 1–64 byte callback data, `answerCallbackQuery`, `editMessageText` and `reply_to_message_id` are documented. No `protect_content` field is relied on by this workstream.
