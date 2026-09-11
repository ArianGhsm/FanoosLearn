# گزارش نهایی Recipient Fingerprinting v5 جزوات

تاریخ: ۱۴۰۵/۶/۷ (۲۰۲۶-۰۸-۲۹)

## نتیجه

نسخهٔ فعال صدور PDF در ربات Telegram برابر `recipient-pdf-v5` است. این نسخه
single point of failure واترمارک Form/XObject را حذف می‌کند، fingerprint پنهان
را در چند کانال مستقل پخش می‌کند و با یک worker واقعی روی VPS دو هسته‌ای/۱GB
پایدار می‌ماند. هدف سامانه افزایش هزینهٔ حذف و فراهم‌کردن attribution پس از
دستکاری‌های متداول است؛ ادعای غیرقابل‌حذف، AI-proof یا ردیابی صددرصدی ندارد.

## تفکیک PII آشکار و پنهان

- واترمارک آشکار عمداً شامل نام و نام خانوادگی کامل، کد ملی کامل، موبایل کامل و
  Trace Code است تا اثر بازدارنده حفظ شود. اعداد ماسک نمی‌شوند.
- hidden payload فقط از `HMAC(server_secret, issuance_id)` مشتق می‌شود. کد ملی،
  موبایل، نام، Telegram ID و `user_id` در hidden payload قرار نمی‌گیرند.
- Trace Code آشکار، fingerprint پنهان و رکورد issuance مستقل‌اند و فقط backend
  آن‌ها را correlate می‌کند.
- secret و HMAC key فقط در environment سرور و backup رمزگذاری‌شده باقی می‌مانند؛
  در PDF، filename، caption، log یا Git نوشته نمی‌شوند.

## معماری فعال

- هر صفحه ده instance مستقل B Nazanin Bold دارد. متن‌ها مستقیم داخل content
  streamهای صفحه قرار می‌گیرند؛ annotation، OCG، Form/XObject مشترک و background
  سفید وجود ندارد.
- position، angle، scale، spacing، offset و opacity با seed محرمانه برای هر
  issuance، صفحه و instance تغییر می‌کنند و پوشش دوبعدی کل صفحه می‌سازند.
- دو micro channel با مجموع ۲۴۰ symbol در هر صفحه بین همان ده stream پخش
  می‌شوند؛ یک block ثابت از rectangleهای هم‌شکل در tail فایل وجود ندارد.
- کانال مستقل `content-text-perturb-hamming12-v1` بیت‌ها را با perturbation
  بسیار کوچک و secret-keyed در operatorهای واقعی متن توزیع می‌کند. کانال
  `content-visual-perturb-hamming12-v1` برای محتوای vector/image-light مکمل است.
- payload پنهان با Hamming(12,8)، CRC و redundancy حمل می‌شود. Detector نتیجهٔ
  هر channel، تعداد symbol بازیابی‌شده، ECC status، evidence و confidence را
  جدا گزارش می‌کند و confidence پایین را قطعی اعلام نمی‌کند.
- delivery path کاملاً vector/PDF-level و بدون rasterization کامل، AI، GPU،
  OpenCV یا Ghostscript است. OpenCV و Ghostscript فقط در forensic venv ادمین
  نصب‌اند. production فقط PyMuPDF، B Nazanin، bidi/reshaper و qpdf validation
  را لازم دارد.
- صف روی `user × document × watermark_version` idempotent و deduplicated است.
  مقدار production یک worker و queue برابر ۴۸ است. ثبت `telegram_file_id` پس از
  upload اتمیک است و cache hit هیچ download، watermark یا upload مجدد ندارد.
- temp directory هر job در success/failure/timeout پاک می‌شود و cleanup orphan
  جداگانه وجود دارد.

## benchmark قبل/بعد روی fixture هشت‌صفحه‌ای

هر دو نسخه روی VPS واقعی و زیر `ulimit -v 1048576` اجرا شدند.

| معیار | v4 قبل | v5 بعد |
|---|---:|---:|
| زمان wall صدور | ۰٫۷۹۹ ثانیه | ۱٫۹۵۲ ثانیه |
| CPU time | ۰٫۴۸۳ ثانیه | ۱٫۶۴۶ ثانیه |
| peak RSS | ۷۱٫۰ MiB | ۸۰٫۳ MiB |
| فایل خروجی | ۱٬۳۹۵٬۲۷۰ بایت | ۱٬۰۷۰٬۷۰۴ بایت |
| نسبت خروجی/مبدا | ۱٫۵۲۴× | ۱٫۱۷۰× |
| peak temp disk | ۲٬۳۱۰٬۶۵۷ بایت | ۱٬۹۸۶٬۰۹۱ بایت |
| watermark Form/XObject | ۵۱ object در fixture v4 | صفر |
| instance مستقیم | یک trace در هر صفحه | ده مورد در هر صفحه؛ ۸۰ کل |

v5 برای حذف shared object و افزودن کانال content perturbation CPU بیشتری مصرف
می‌کند، اما خروجی ۲۳٪ کوچک‌تر از v4 است و مصرف حافظه همچنان بسیار پایین‌تر از
محدودیت ۱GB باقی ماند.

## load test سرور

| سناریو | زمان کل | p95 latency / queue | peak RSS | نتیجه |
|---|---:|---:|---:|---|
| ۲۰ cache hit، ۱ worker | ۰٫۰۶۴ ثانیه | ۰٫۰۱۸ / ۰٫۰۱۸ ثانیه | ۲۷٫۷۴ MiB | ۲۰ ارسال، صفر download/upload |
| ۲۰ درخواست یک سند | ۲٫۱۷۲ ثانیه | ۲٫۱۶۴ / ۰٫۰۰۲ ثانیه | ۷۶٫۴۶ MiB | یک generation، ۱۹ duplicate |
| ۲۰ سند ۸صفحه‌ای، ۱ worker | ۳۷٫۹۳۵ ثانیه | ۳۵٫۸۵۲ / ۳۴٫۰۳۹ ثانیه | ۷۷٫۶۲ MiB | ۲۰/۲۰، صفر failure/orphan |
| ۲۰ سند ۸صفحه‌ای، ۲ worker | ۳۴٫۳۸۷ ثانیه | ۳۳٫۵۲۵ / ۳۰٫۴۴۵ ثانیه | ۸۶٫۵۶ MiB | ۲۰/۲۰، صفر failure/orphan |
| ۲۰ سند ۴۰صفحه‌ای، ۱ worker | ۱۵۹٫۸۹۴ ثانیه | ۱۵۱٫۸۶۵ / ۱۴۳٫۹۹۹ ثانیه | ۷۸٫۹۳ MiB | ۲۰/۲۰، صفر failure/orphan |

دو worker فقط حدود ۹٪ زمان burst هشت‌صفحه‌ای را کاهش داد و headroom کمتری برای
ربات، SQLite و سیستم‌عامل گذاشت. بنابراین مقدار production همان یک worker است؛
پس از ارتقای سرور می‌توان benchmark را تکرار و concurrency را تغییر داد.

## regression حملات

- neutralize همهٔ Form/XObjectها: object متعلق به واترمارک وجود نداشت و attribution
  با confidence `0.995` باقی ماند.
- حذف هر ۸۰ stream مشکوک visible/micro: کانال text-content هر ۱۲۰ symbol را با
  ECC/CRC معتبر بازیابی کرد؛ attribution با confidence `0.975` باقی ماند.
- حذف content-stream tail: کانال text-content با erasure recovery معتبر ماند.
- qpdf rewrite واقعی: content-text `120/120`, CRC معتبر، verdict attributed با
  confidence `0.995`.
- Ghostscript rewrite واقعی: content-text `120/120`, CRC معتبر، verdict attributed
  با confidence `0.995`؛ کانال visual-content در این rewrite از بین رفت ولی کانال
  مستقل متن باقی ماند.
- screenshot و JPEG Q90 به attribution قطعی تستی رسیدند. JPEG Q60/Q75،
  print→scan و mobile-photo فقط candidate ماندند و قطعی گزارش نمی‌شوند.
- crop ۲۰٪ و ماسک destructive با دسترسی به original به‌ترتیب inconclusive شدند؛
  این رفتار عمدی detector است تا evidence ضعیف به کاربر قطعی نسبت داده نشود.
- دو نسخهٔ شخصی‌شده در ۹۵٫۲٪ content streamها و همهٔ هشت صفحه تفاوت داشتند؛
  تفاوت تصویری ۹۹٪ tileهای صفحه را پوشش داد و در یک object/ناحیه متمرکز نبود.

گزارش‌های خام:

- `ops/benchmarks/booklet-v5-server-benchmark.json`
- `ops/benchmarks/booklet-forensic-v5-server-attack-suite.json`
- `ops/benchmarks/booklet-forensic-v5-local-attack-suite.json`

## پذیرش production و بازیابی

- release فعال: `telegram-20260828-204453`
- health، CWD release، channel admin، source channel، signed website API، worker=1
  و queue=48 پاس شدند؛ RSS بیکار سرویس حدود ۳۴ MiB بود.
- یک صدور واقعی v5 برای مالک از طریق خود Bot API انجام شد. درخواست دوم همان
  `telegram_file_id` را reuse کرد و generation جدیدی نساخت.
- فایل واقعی دانلودشده از Telegram دارای B Nazanin Bold، streamهای مستقیم v5 و
  کد ملی/موبایل کامل در لایهٔ آشکار بود.
- ۱۶۸ تست unit/integration/regression پاس شدند و آخرین PDF با Poppler روی صفحات
  متن، تصویر و سفید رندر و بصری بررسی شد.
- snapshot نهایی لپ‌تاپ:
  `backups/vps-state-iran/vps-state-20260829-093812.tar.gz.dpapi`
  با `RESTORE_VERIFIED=true`.

## محدودیت‌های باقی‌مانده

- screenshot با دستگاه دوم، بازسازی دستی یا destructive diff با دسترسی به اصل
  فایل قابل جلوگیری قطعی نیست؛ هدف افزایش هزینه و حفظ evidence در دستکاری‌های
  متداول است.
- print→scan و عکس موبایل probabilistic هستند و برای threshold دقیق‌تر به corpus
  واقعی از چاپگر، اسکنر، نور و دوربین‌های مختلف نیاز دارند.
- content perturbation برای تحلیل PDF به original source و secret detector نیاز
  دارد. این وابستگی برای جلوگیری از inspection ساده پذیرفته شده است.
- صفحات کاملاً تصویری پس از بازنویسی شدید ممکن است فقط micro/visible evidence
  داشته باشند؛ کانال visual-content مکمل است و جایگزین تضمین‌شدهٔ text-content
  نیست.
- collusion چند گیرنده دفاع اختصاصی کامل ندارد. versioned/pluggable بودن encoder
  و detector اجازه می‌دهد channel ضد-collusion بعدی بدون شکستن v1 تا v5 اضافه شود.
