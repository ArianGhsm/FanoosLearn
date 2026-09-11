# گزارش ارتقای فونت واترمارک جزوات به v4

تاریخ: ۱۴۰۵/۶/۶ (۲۰۲۶-۰۸-۲۸)

## تغییر

- فونت مبدا `B Nazanin Bold-.ttf` از پوشهٔ فونت‌های فارسی مالک بررسی شد؛ نام
  داخلی خانواده `B Nazanin` و سبک فایل Bold است.
- asset با نام `dent_bot/assets/fonts/B_Nazanin_Bold.ttf` داخل release ربات
  بسته‌بندی می‌شود. SHA-256 آن
  `2293311D5738F48BF7DBF3BB38E1B7FA1475E955893E0B026258493AC1D4658C` است.
- `@font-face` وزن 700 را صریح تعریف می‌کند و کل متن visible watermark نیز با
  `font-weight:700` ساخته می‌شود. PDF خروجی فونت embedded را با نام
  `B Nazanin Bold` گزارش می‌کند.
- نسخهٔ صدور از `recipient-pdf-v3` به `recipient-pdf-v4` افزایش یافت. به این
  ترتیب cache قدیمی Telegram یک‌بار miss می‌شود و همان کاربر/جزوه با فونت جدید
  دوباره تولید می‌شود؛ دفعات بعد `telegram_file_id` نسخهٔ v4 استفاده می‌شود.
- micro-channel پخش‌شدهٔ نسخهٔ قبلی حفظ شده، اما HMAC domain صدور v4 مستقل است.
  Detector همچنان صدورهای v1، v2 و v3 را تشخیص می‌دهد.

## آزمون و پذیرش

- ۱۶۶ تست unit/integration، compile check و ۱۵۸ بررسی UTF-8 پاس شد.
- regression کامل حملات محلی v4 بدون failure پاس شد؛ دو ECC مخفی در فایل سالم
  بازیابی شدند و حذف XObject یا content tail همچنان attribution را از بین نبرد.
- PDF هشت‌صفحه‌ای متن/جدول/تصویر/صفحهٔ سفید با Poppler رندر و بصری بررسی شد.
  حجم نمونه ۱٬۳۹۴٬۲۲۱ بایت است و font inventory آن `B Nazanin Bold` را تأیید
  می‌کند.
- release فعال production برابر `telegram-20260828-195110` است؛ worker=1،
  queue=48 و RSS سرویس هنگام acceptance حدود ۳۳ MiB بود.
- صدور واقعی مالک در Telegram انجام شد: بار اول v4 ساخته و آپلود شد و بار دوم
  همان `telegram_file_id` را استفاده کرد. فایل cacheشده از خود Telegram موقتاً
  دانلود و font inventory آن نیز `B Nazanin Bold` را تأیید کرد؛ فایل موقت پاک شد.
- بکاپ نهایی پس از صدور و cache نسخهٔ v4 روی لپ‌تاپ restore-verified است:
  `backups/vps-state-iran/vps-state-20260828-195524.tar.gz.dpapi`.

تغییر فونت ادعای امنیتی جدیدی ایجاد نمی‌کند؛ مدل threat و محدودیت‌های detector
همان گزارش `ops/BOOKLET_FORENSIC_V3_2026-08-28.md` باقی مانده است.
