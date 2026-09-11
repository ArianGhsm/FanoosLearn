# گزارش سخت‌سازی Recipient Fingerprinting جزوات

> این سند گزارش تاریخی نسخهٔ v2 است. وضعیت فعال و نتایج جدید در
> `ops/BOOKLET_FORENSIC_V3_2026-08-28.md` ثبت شده است.

تاریخ: ۱۴۰۵/۶/۶ (۲۰۲۶-۰۸-۲۸)

## معماری نهایی

- مسیر صدور فعال `recipient-pdf-v2` است و Detector همچنان
  `recipient-pdf-v1` را می‌خواند.
- فایل مبدا فقط از کانال خصوصی مدیریتی Telegram به دایرکتوری موقت اختصاصی
  job دانلود می‌شود. SHA-256 هم‌زمان با stream دانلود محاسبه می‌شود و دور دوم
  خواندن دیسک فقط fallback است.
- صدور PDF در سطح vector/content-stream با PyMuPDF انجام می‌شود. qpdf ورودی و
  خروجی را validate می‌کند؛ rasterize کامل، GPU، AI، Ghostscript و ImageMagick
  در delivery path وجود ندارند.
- واترمارک آشکار شامل هویت canonical کامل و Trace Code روی همهٔ صفحه‌هاست.
  angle، spacing، offset و موقعیت با seed اختصاصی issuance و صفحه تغییر می‌کند.
  یک Trace Code مستقل نیز مستقیماً و به‌صورت متناوب بالا/پایین صفحه نوشته
  می‌شود تا حذف Form XObject مورب، همهٔ کانال‌های آشکار را حذف نکند.
- payload مخفی v2 فقط `HMAC(secret, issuance_id)` است و هیچ user ID، Telegram
  ID، کد ملی یا موبایل خامی ندارد. دو micro-grid در هر صفحه، payload را با
  Hamming(12,8)، CRC و permutation محرمانهٔ مستقل در هر صفحه/کپی حمل می‌کنند.
- visible trace، hidden token و ردیف issuance مستقل‌اند و فقط backend آن‌ها را
  correlate می‌کند. secretها فقط در environment سرور و backup رمزگذاری‌شده‌اند.
- صف بر اساس `user × document × watermark_version` deduplicate می‌شود؛ دو route
  مختلف یک سند نیز generation موازی نمی‌سازند. production دو worker و queue
  برابر ۴۸ دارد. rate limit پایدار ۱۲ درخواست در ۶۰ ثانیه و cooldown سه‌ثانیه‌ای
  برای یک سند فعال است.
- پایان موفق upload، issuance و cache `telegram_file_id` را در یک transaction
  اتمیک ثبت می‌کند. cache hit هیچ download، watermark یا upload ندارد.
- هر job deadline سه‌دقیقه‌ای و دایرکتوری با PID دارد. cleanup در success/failure
  اجرا می‌شود و thread سبک دوره‌ای فقط orphanهای قدیمی را حذف می‌کند؛ job متعلق
  به PID زنده یا rollout هم‌پوشان حذف نمی‌شود.
- Detector یک بار evidence برداری/تصویری را preprocess می‌کند و سپس candidateها
  را مقایسه می‌کند. خروجی شامل verdict، confidence، channelهای موفق/ناموفق و
  evidence است. نتیجهٔ کم‌اعتماد هرگز attribution قطعی نام‌گذاری نمی‌شود.

## اندازه‌گیری قبل و بعد روی VPS

هر دو مجموعه روی VPS واقعی ۲ vCPU و زیر `ulimit -v 1048576` اجرا شدند. fixtureها
ثابت و شامل صفحات متن، جدول، تصویر و سفید بودند.

| سناریو | v1 قبل | v2 بعد | peak RSS بعد | نتیجه |
|---|---:|---:|---:|---|
| صدور ۸ صفحه | ۲٫۴۸ ثانیه / ۱٫۷۳× حجم | ۰٫۹۵ ثانیه / ۱٫۵۵× | ۷۰٫۳۹ MiB | ۶۱٫۶٪ سریع‌تر |
| صدور ۴۰ صفحه | ۱۲٫۶۴ ثانیه / ۲٫۵۴× | ۴٫۸۴ ثانیه / ۱٫۶۴× | ۷۱٫۷۵ MiB | ۶۱٫۷٪ سریع‌تر |
| صدور ۸۰ صفحه | ۳۰٫۰۳ ثانیه / ۳٫۵۲× | ۱۴٫۸۳ ثانیه / ۱٫۷۵× | ۷۳٫۲۶ MiB | ۵۰٫۶٪ سریع‌تر |
| ۲۰ درخواست یک سند | ۲٫۵۹ ثانیه | ۰٫۹۷ ثانیه | ۷۱٫۵۷ MiB | ۱۹ duplicate، یک generation |
| ۲۰ سند ۸صفحه‌ای، ۲ worker | ۴۵٫۶۸ ثانیه | ۱۱٫۰۵ ثانیه | ۷۷٫۱۹ MiB | صفر failure/orphan |
| ۲۰ سند ۴۰صفحه‌ای، ۲ worker | — | ۸۴٫۷۰ ثانیه | ۷۹٫۷۷ MiB | صفر failure/orphan |
| ۲۰ cache hit | صفر I/O سنگین | صفر download/upload | ۲۶٫۶۱ MiB | p95 برابر ۲۸ms |

دو worker در v2 نسبت به یک worker، burst بیست سند ۸صفحه‌ای را از ۱۵٫۸۳ به
۱۱٫۰۵ ثانیه رساند و فقط حدود ۵٫۴ MiB peak RSS افزود؛ به همین دلیل مقدار production
دو انتخاب شد. این مقدار hard-code معماری نیست و پس از ارتقای سرور قابل benchmark
و تغییر است.

مقایسهٔ نهایی local برای یک فایل ۸صفحه‌ای با کد یکسان:

- PyMuPDF + qpdf و بدون normalizer: ۰٫۳۵۳ ثانیه و ۱٬۴۴۱٬۲۳۳ بایت.
- همان مسیر + pikepdf: ۰٫۴۴۸ ثانیه و ۱٬۴۴۲٬۳۵۴ بایت.

pikepdf حدود ۲۷٪ latency افزود و فایل را کوچک نکرد؛ بنابراین از dependency
production حذف و فقط fallback اختیاری باقی ماند.

فایل‌های دادهٔ خام benchmark:

- `ops/benchmarks/booklet-baseline-production-20260828.jsonl`
- `ops/benchmarks/booklet-v2-production-20260828.jsonl`

## آزمون و پذیرش

- ۱۶۲ تست integration/unit پاس شد.
- regression نسخهٔ v1 و detection مستقل hidden-only نسخهٔ v2 پاس شد.
- corpus تصویری شامل screenshot، JPEG با کیفیت ۴۸، crop+resize، rotate و
  perspective warp همگی candidate درست و micro channel موفق دادند.
- PDF با Poppler روی صفحات متن، جدول، تصویر و سفید رندر و بصری بررسی شد.
- ۲۰ درخواست هم‌زمان کوچک و بزرگ، cache hit/miss، یک سند مشترک و سندهای متفاوت
  بدون failure، OOM و orphan اجرا شدند.
- release فعال `telegram-20260828-090355` است؛ CWD process با symlink فعال یکی،
  `NRestarts=0`، worker=2، queue=48 و RSS سرویس بیکار حدود ۳۴ MiB است.
- اولین صدور v2 واقعی برای مالک protected ارسال شد و درخواست دوم همان فایل از
  cache Telegram استفاده کرد.
- snapshot نهایی لپ‌تاپ restore-verified است:
  `backups/vps-state-iran/vps-state-20260828-090443.tar.gz.dpapi`.

## محدودیت‌ها و threatهای باقی‌مانده

- `protect_content`، واترمارک و fingerprint بازدارنده و ابزار attribution هستند؛
  screenshot با دستگاه دوم یا بازسازی دستی را مطلقاً متوقف نمی‌کنند.
- ویرایشگر ماهر می‌تواند Form XObject واترمارک مورب را حذف کند؛ v2 به همین دلیل
  direct trace و micro channel مستقل دارد، اما حذف هدفمند همهٔ content streamها
  یا rasterize/بازسازی کامل هنوز ممکن است.
- print→scan، عکس perspectiveدار و فشرده‌سازی شدید probabilistic هستند. نتیجهٔ
  Detector باید با confidence/evidence بررسی شود، نه به‌عنوان اثبات قطعی حقوقی.
- تست‌های تصویر synthetic و کنترل‌شده‌اند؛ thresholdها برای انواع اسکنر، نور،
  کاغذ و دوربین واقعی باید با corpus میدانی بزرگ‌تر دوباره calibrate شوند.
- collusion چند گیرنده و بازسازی صفحه از چند نسخه هنوز دفاع اختصاصی ندارد؛
  معماری versioned امکان افزودن channel ضد-collusion بعدی را بدون شکستن v1/v2
  فراهم می‌کند.
- Telegram Bot API idempotency key برای upload ندارد. crash در فاصلهٔ پذیرش
  upload و commit اتمیک SQLite ممکن است در retry یک پیام protected تکراری بسازد؛
  issuance و fingerprint ثابت می‌مانند و ردیف‌های بی‌نهایت تولید نمی‌شوند.
- تحلیل عکس/scan به original PDF و شمارهٔ صفحهٔ مرجع نیاز دارد و OpenCV/Tesseract
  محیط admin-only است، نه dependency سرویس delivery.
