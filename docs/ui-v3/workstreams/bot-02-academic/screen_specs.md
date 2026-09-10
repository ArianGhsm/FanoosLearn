# FANOOS V3 Bot Academic Journeys — Screen Specs

Design Lock: `FANOOS-UX-2026.09-R1`  
Workstream: `02_ACADEMIC_JOURNEYS`  
Presentation: provider-neutral; Telegram and Bale consume the same semantic screens.

## Shared behavior

- Persian-first, RTL and student-facing.
- Domain truth always comes from fresh authorized backend projections.
- Canonical IDs/cursors exist only in callback intents or short subject-bound route references; they are never visible text.
- Short peer actions may be packed two per row. Long labels are not squeezed.
- `Screen.action_rows` stays within bot-01/core's bounded 10-row contract even in the one-button-per-row worst case.
- Every secondary journey has contextual Back plus `🏠 خانه`.
- Empty/error/loading states never invent data, progress percent or ETA.
- No GPA/average, course binding, personal notification history or safe link is inferred from incomplete data.

## Courses

### Course list — `academic.course.list`

Rendered page bound: **8 courses**.

```text
📚 درس‌ها
فضای آموزشی: دانشکده نمونه · ترم: نیمسال اول

• ترمیمی ۱
  REST-301 · نیمسال اول
• پریودنتولوژی ۱
  PERIO-301 · نیمسال اول

[ ترمیمی ۱ ] [ پریودنتولوژی ۱ ]
[ ‹ قبلی ] [ بعدی › ]   # only when route refs exist
[ 🏠 خانه ]
```

Rules:
- consume the canonical `courses` projection currently attached to the bot schedule response;
- collapse duplicate offering rows by hidden canonical `course_id`;
- show `course_code` as secondary metadata;
- show course term only when canonical offering rows do not conflict;
- optional workspace/selected-term context is shown only when integration has canonical labels;
- pagination uses opaque route references, never raw cursors.

### Course empty — `academic.course.empty`

```text
📚 درس‌ها

برای این فضای آموزشی هنوز درسی در فهرست قابل‌نمایش منتشر نشده است.
بعداً دوباره این بخش را بررسی کنید یا فضای آموزشی فعال را تغییر دهید.

[ 🏠 خانه ]
```

### Course unavailable — `academic.course.unavailable`

```text
📚 درس در دسترس نیست

این درس در فهرست مجاز فعلی پیدا نشد. فهرست درس‌ها را دوباره باز کنید.

[ ‹ درس‌ها ] [ 🏠 خانه ]
```

Permission-denied copy is separate and does not reveal foreign-course existence beyond the already-authorized context.

### Course detail — `academic.course.detail`

```text
📚 ترمیمی ۱
درس‌ها › ترمیمی ۱

کد درس: REST-301
ترم: نیمسال اول

[ 📅 برنامه ] [ 📚 منابع ]
[ 🎓 نمرات ]
[ ‹ درس‌ها ] [ 🏠 خانه ]
```

Destinations are capability/projection driven through `supported_actions`. At the frozen base the safe default is schedule + resources + grades. Assessments, course announcements and sessions stay hidden until canonical support exists.

Course child action IDs:

| Meaning | Action ID |
| --- | --- |
| Open course | `academic.course.open` |
| Course schedule | `academic.course.schedule` |
| Course resources | `academic.course.resources` |
| Course assessments | `academic.course.assessments` |
| Course grades | `academic.course.grades` |
| Course announcements | `academic.course.announcements` |
| Course sessions | `academic.course.sessions` |

Top-level Course/Schedule/Grades/Home/Notifications action identifiers reuse bot-01/core rather than creating duplicates.

## Schedule

### Schedule hub — `academic.schedule.hub`

```text
📅 برنامه

بازه موردنظر را انتخاب کنید. تاریخ و ساعت از منطقه زمانی فضای آموزشی می‌آید.

[ 📅 امروز ] [ 📅 فردا ]
[ 🗓 پیشِ رو ]
[ 🏠 خانه ]
```

A course-scoped hub keeps course context and returns to that course. `پیشِ رو` is valid because the frozen schedule projection supports bounded local-date ranges up to 31 days.

### Schedule list/day — `academic.schedule.list`

Rendered page bound: **8 events**.

```text
📅 امروز
برنامه › امروز

• جلسه ترمیمی
  ترمیمی ۱
  ۰۸:۳۰ · کلینیک ترمیمی

• جراحی ۱
  ۱۰:۳۰ · بخش جراحی

صفحه ۱
زمان‌ها بر اساس منطقه زمانی فضای آموزشی نمایش داده می‌شوند.

[ جزئیات · جلسه ترمیمی ]
[ جزئیات · جراحی ۱ ]
[ ‹ برنامه ] [ 🏠 خانه ]
```

Rules:
- request date bounds are workspace-local calendar dates;
- `tenant_workspaces.timezone_name` is authoritative;
- `starts_at`/`ends_at` are formatted using the canonical timezone input;
- the technical timezone slug is not leaked to student copy;
- course/title/location are omitted when absent, not guessed;
- if integration has a short subject-bound event route ref, detail actions use it; otherwise hidden canonical event ID may be used only to drive a fresh authorized re-read.

### Schedule empty

```text
📅 فردا

برای فردا برنامه‌ای ثبت نشده است.
زمان‌ها بر اساس منطقه زمانی فضای آموزشی نمایش داده می‌شوند.

[ ‹ برنامه ] [ 🏠 خانه ]
```

### Event detail — `academic.schedule.event.detail`

```text
📅 جلسه ترمیمی
برنامه › جزئیات رویداد

زمان: ۰۸:۳۰ تا ۱۰:۳۰
درس: ترمیمی ۱
مکان: کلینیک ترمیمی

زمان‌ها بر اساس منطقه زمانی فضای آموزشی نمایش داده می‌شوند.

[ ‹ برنامه ] [ 🏠 خانه ]
```

Raw internal status/event-type enums and identifiers are not shown.

## Grades

### Grade list — `academic.grades.list`

Rendered page bound: **16 grade rows**, grouped by canonical course title inside the fetched page.

```text
🎓 نمرات

ترمیمی ۱
• میان‌ترم
  ۱۷٫۵ از ۲۰
  منتشرشده
• پایان‌ترم
  ۱۸ از ۲۰
  منتشرشده

صفحه ۱
فقط نمره‌های منتشرشده نمایش داده می‌شوند. معدل یا میانگین در این بخش محاسبه نمی‌شود.

[ 🏠 خانه ]
```

The frozen backend projection only returns published gradebook/result rows for the linked student's authorized enrollment. `منتشرشده` therefore describes the projection contract; the presentation does not infer unpublished state.

Never calculate GPA, weighted course total, class average or rank.

### Grade empty

```text
🎓 نمرات

نمره منتشرشده‌ای برای شما پیدا نشد.

[ 🏠 خانه ]
```

### Course grade detail — `academic.grades.course`

```text
🎓 نمرات · ترمیمی ۱
درس‌ها › ترمیمی ۱ › نمرات

• میان‌ترم
  ۱۷٫۵ از ۲۰
  منتشرشده

هیچ معدل یا میانگینی از روی داده ناقص محاسبه نمی‌شود.

[ ‹ بازگشت به درس ] [ 🏠 خانه ]
```

At the frozen base integration must bounded-fetch authorized grade pages and filter using canonical `course_id`; native server-side course filtering is still a gap.

## Announcements

### Announcement list — `academic.announcements.list`

Rendered page bound: **8 announcements**.

```text
📢 اطلاعیه‌ها

• تغییر زمان جلسه جراحی
  ۲۰۲۶/۰۹/۱۰، ۱۲:۳۰
• انتشار منبع جدید
  ۲۰۲۶/۰۹/۰۹، ۱۸:۱۰ · خوانده‌شده

صفحه ۱

[ مشاهده · تغییر زمان جلسه جراحی ]
[ ‹ قبلی ] [ بعدی › ]
[ 🏠 خانه ]
```

List is intentionally title-first; full body belongs to detail. If integration has a short subject-bound detail route ref, it is preferred so the handler can retain only correlation and re-read canonical data.

### Announcement detail — `academic.announcement.detail`

```text
📢 تغییر زمان جلسه جراحی
اطلاعیه‌ها › تغییر زمان جلسه جراحی

متن کامل اطلاعیه در اینجا نمایش داده می‌شود.

زمان انتشار: ۲۰۲۶/۰۹/۱۰، ۱۲:۳۰
وضعیت: خوانده‌شده

[ ‹ اطلاعیه‌ها ] [ 🏠 خانه ]
```

### Safe link semantics

The frozen announcement projection contains no trusted link field. Academic presentation:
- never extracts a URL from body text;
- never treats a provider token or arbitrary raw URL as domain truth;
- shows `academic.announcement.link.open` only when integration supplies a validated opaque `safe_link_ref` compatible with bot-01/core route-reference rules.

## Personal notifications

### Entry only — `academic.notifications.entry`

```text
🔔 اعلان‌های شخصی

اعلان‌های شخصی ممکن است به‌صورت خودکار در پیام‌رسان به شما تحویل شوند، اما این تحویل به معنی وجود صندوق ورودی دائمی در ربات نیست.

📢 اطلاعیه‌ها
اطلاعیه‌های منتشرشده فضای آموزشی فهرست مستقل و قابل‌مشاهده دارند.

🔔 تاریخچه شخصی
در حال حاضر تاریخچه قابل‌اعتمادی برای نمایش اعلان‌های شخصی در ربات وجود ندارد؛ بنابراین ربات از روی پیام‌های تحویل‌شده تاریخچه نمی‌سازد.

[ 📢 اطلاعیه‌ها ]
[ 🏠 خانه ]
```

`/notifications/project`, `/claim` and `/receipt` are transport worker contracts, not a user inbox. They must never be reconstructed into durable history.

## Loading, error and pagination

### Loading — `academic.loading`

```text
📚 درس‌ها
در حال دریافت اطلاعات از فانوس…

[ 🏠 خانه ]
```

Prefer provider-native activity feedback for short reads. No percentage or ETA is invented.

### Error — `academic.error`

```text
❌ نمرات

این بخش فعلاً بارگذاری نشد. دوباره تلاش کنید؛ اگر مشکل ادامه داشت از خانه مسیر را از نو باز کنید.

[ 🔄 تلاش دوباره ]
[ 🏠 خانه ]
```

Raw backend/provider errors are normalized before presentation.

### Pagination contract

- page label uses Persian human digits;
- Previous/Next appear only when an opaque route ref exists;
- route refs are short, expiring, provider+subject-bound presentation correlation;
- a route ref is never authorization;
- page/detail handlers must re-read and reauthorize canonical backend state;
- bot-02 stores no course truth, membership truth, cursor authority, grade truth or notification history.

## Provider rendering

Telegram may render semantic headings/lists/facts with its verified rich-message capability. Bale renders the same hierarchy using its own native text/actions. Academic source contains no provider branch and no Telegram-only or Bale-only domain behavior.
