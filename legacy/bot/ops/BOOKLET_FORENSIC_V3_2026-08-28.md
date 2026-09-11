# گزارش فنی Recipient Fingerprinting و Forensic Detector v3

> این گزارش مبنای امنیتی الگوریتم spread نسخهٔ v3 است. مسیر فعال اکنون
> این گزارش تاریخی v3/v4 است. وضعیت فعال `recipient-pdf-v5` و نتایج سخت‌سازی
> جدید در `ops/BOOKLET_FORENSIC_V5_2026-08-29.md` ثبت شده است. `recipient-pdf-v4`
> با همان forensic layout قدیمی و فونت B Nazanin Bold بود؛ جزئیات
> تغییر typography در `ops/BOOKLET_FONT_V4_2026-08-28.md` ثبت شده است.

تاریخ: ۱۴۰۵/۶/۶ (۲۰۲۶-۰۸-۲۸)

## نتیجهٔ نهایی

مسیر فعال صدور جزوه `recipient-pdf-v3` است. Encoder و Detector با هم نسخه‌بندی
شده‌اند و Detector صدورهای v1 و v2 را نیز می‌خواند. این سامانه بازدارنده و ابزار
نسبت‌دادن نشت بر اساس شواهد است؛ «غیرقابل‌حذف» یا «صددرصد قابل‌ردیابی» نیست.

## معماری

- برای هر `user × document × source_hash × watermark_version` یک issuance پایدار
  با شناسهٔ تصادفی، Trace Code و fingerprint مشتق‌شده از HMAC سرور ثبت می‌شود.
  hidden payload شامل کد ملی، موبایل، Telegram ID یا user ID خام نیست.
- نام کامل، کد ملی، موبایل و Trace Code به‌صورت تکرارشونده و متغیر روی تمام صفحات
  در content stream قرار می‌گیرند. یک direct trace مستقل نیز بالا/پایین صفحات
  درج می‌شود.
- دو micro-channel مستقل Hamming(12,8)+CRC با seed محرمانه در تقریباً کل صفحه
  پخش می‌شوند. دو copy در streamهای مستقل و در دو سوی کانال‌های آشکار قرار دارند؛
  حذف یک XObject یا tail واحد همهٔ شواهد را پاک نمی‌کند.
- delivery فقط PyMuPDF و qpdf، بدون rasterization کامل، AI، GPU یا پردازش دائمی
  تصویر است. OpenCV و Ghostscript فقط در محیط جداگانهٔ admin-forensic نصب‌اند.
- صف bounded، deduplication و lock بر مبنای `user × document × version` فعال است.
  production یک worker و queue 48 دارد. اولین upload موفق، `telegram_file_id` را
  اتمیک cache می‌کند؛ cache hit هیچ download، generation یا upload ندارد.
- هر job دایرکتوری موقت خصوصی دارد؛ cleanup مسیرهای success/failure/timeout و
  job دوره‌ای orphanها را پوشش می‌دهد. لاگ عملیاتی فقط زمان، اندازه، queue، CPU،
  RSS، خطا و cache state را ثبت می‌کند و PII/secret را نمی‌نویسد.

Detector برای PDF و تصویر نتیجهٔ هر channel، symbolهای بازیابی‌شده، ECC/CRC،
candidate issuance، confidence و evidence را جدا گزارش می‌کند. مسیر تصویر شامل
resize normalization، deskew، perspective correction، ORB page alignment و
crop-scale search است. فقط Trace دقیق یا ECC معتبر می‌تواند verdict قطعی
`attributed` بسازد؛ evidence ناقص `candidate` یا `inconclusive` می‌ماند.

## اندازه‌گیری واقعی روی VPS با سقف ۱GB

| سناریو | زمان کل | peak RSS | temp peak | نتیجه |
|---|---:|---:|---:|---|
| صدور v3، ۸ صفحه | ۰٫۹۹ ثانیه | ۶۸٫۹۴ MiB | ۲٫۳۸ MB | ۱٫۵۹× حجم مبدا |
| صدور v3، ۴۰ صفحه | ۴٫۶۳ ثانیه | ۶۹٫۱۹ MiB | ۲٫۵۴ MB | ۱٫۷۵× حجم مبدا |
| صدور v3، ۸۰ صفحه | ۱۴٫۸۹ ثانیه | ۶۹٫۵۶ MiB | ۲٫۷۴ MB | ۱٫۹۵× حجم مبدا |
| ۲۰ cache hit، یک worker | ۰٫۰۵۹ ثانیه | ۲۷٫۴۶ MiB | ۱٫۴۹ MB | صفر download/upload |
| ۲۰ درخواست یک سند | ۱٫۰۱ ثانیه | ۷۱٫۰۳ MiB | ۲٫۷۵ MB | ۱۹ duplicate، یک generation |
| ۲۰ سند ۸صفحه‌ای، یک worker | ۱۷٫۵۶ ثانیه | ۷۱٫۲۸ MiB | ۴٫۵۰ MB | صفر failure/orphan |
| ۲۰ سند ۴۰صفحه‌ای، یک worker | ۹۶٫۲۶ ثانیه | ۷۱٫۴۳ MiB | ۴٫۶۷ MB | صفر failure/orphan |

پروفایل دو worker نیز برای مقایسهٔ ۲۰ سند ۸صفحه‌ای اجرا شد: ۱۳٫۱۰ ثانیه و
۷۵٫۷۳ MiB. با وجود عبور از تست ۱GB، production روی یک worker باقی ماند تا برای
دو ربات و سرویس‌های سیستم headroom داشته باشد. ۲۰ درخواست هم‌زمان به معنی ۲۰
پردازش هم‌زمان نیست و back-pressure آن‌ها را صف‌بندی می‌کند.

در fixture مشترک ۸صفحه‌ای، v2 در ۰٫۸۲ ثانیه با RSS برابر ۷۱٫۶۵ MiB و نسبت حجم
۱٫۵۸ تولید شد؛ v3 در ۰٫۸۴ ثانیه با RSS برابر ۷۲٫۵۳ MiB و نسبت حجم ۱٫۶۰. هزینهٔ
نسخهٔ جدید حدود ۲۵ میلی‌ثانیه و ۱۳٫۶ KiB خروجی بود، در مقابل پراکندگی hidden
channel از ۴٪ tileهای صفحه به ۱۰۰٪ رسید.

## نتایج regression حملات

| دستکاری | verdict | confidence | شواهد اصلی |
|---|---:|---:|---|
| حذف annotation | attributed | ۰٫۹۹۵ | ۲ ECC معتبر + trace |
| حذف اولین XObject | attributed | ۰٫۹۹۵ | ۲ ECC معتبر + trace |
| حذف content-stream tail | attributed | ۰٫۹۹۵ | ۱ ECC معتبر + trace |
| حذف همهٔ micro-streamها | attributed | ۰٫۹۶۵ | visible/direct trace؛ بدون ECC |
| qpdf rewrite | attributed | ۰٫۹۹۵ | ۲ ECC معتبر + trace |
| Ghostscript rewrite | attributed | ۰٫۹۹۵ | ۲ ECC معتبر + trace |
| screenshot و JPEG Q60/Q75/Q90 | attributed | ۰٫۹۸۵ | ۲ ECC معتبر |
| crop ۵٪ | attributed | ۰٫۹۷۵ | ۱ ECC معتبر |
| crop ۱۰٪ و ۲۰٪ | candidate | ۰٫۸۸۷ | evidence دو کانال، CRC نامعتبر |
| rotate / resize | candidate | ۰٫۸۶۹ / ۰٫۸۲۷ | evidence ناقص |
| print→scan / mobile photo | candidate | ۰٫۸۸۷ / ۰٫۸۵۳ | evidence ناقص |
| حذف micro با diff کوچک | inconclusive | ۰٫۰۵۵ | attribution قطعی ممنوع |
| ماسک destructive با original | inconclusive | ۰٫۰۲۲ | attribution قطعی ممنوع |

در مقایسهٔ دو نسخهٔ شخصی‌شده، تفاوت‌ها در ۱۰۰٪ tileهای صفحه پراکنده بودند و در
یک object یا ناحیهٔ واحد متمرکز نشدند. حذف با diff در اختیار داشتن original را
ممکن است، اما footprint و آسیب ویرایشی آن نیز روی ۹۹٪ tileها پخش می‌شود.

## تست، استقرار و محدودیت‌ها

- ۱۶۵ تست unit/integration و compile check پاس شد؛ PDF نهایی روی صفحات متن،
  جدول، تصویر و سفید با Poppler رندر و بررسی بصری شد.
- release فعال Telegram: `telegram-20260828-192150`؛ worker=1 و queue=48.
- بکاپ نهایی لپ‌تاپ restore-verified است:
  `backups/vps-state-iran/vps-state-20260828-193903.tar.gz.dpapi`.
- دادهٔ خام: `ops/benchmarks/booklet-v3-production-20260828.jsonl` و
  `ops/benchmarks/booklet-forensic-v3-production-attack-suite.json`.
- screenshot با دستگاه دوم، بازسازی دستی، rasterization شدید و مهاجمی که original
  دقیق را دارد قابل جلوگیری قطعی نیستند. print/scan و عکس موبایل probabilistic
  هستند و بدون ECC/Trace معتبر فقط candidate می‌مانند.
- corpus میدانی دوربین، چاپگر، کاغذ و نورهای متنوع هنوز باید بزرگ‌تر شود تا
  thresholdها دوباره کالیبره شوند. دفاع اختصاصی collusion چند گیرنده نیز کار
  آینده است؛ معماری versioned افزودن channel جدید را بدون شکستن v1/v2/v3 ممکن
  می‌کند.
