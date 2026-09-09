# FANOOS V3 Bot Academic Journeys — Screen Specs

Design Lock: `FANOOS-UX-2026.09-R1`  
Workstream: `02_ACADEMIC_JOURNEYS`  
Channel model: provider-neutral; Telegram and Bale render the same semantic screen with provider-native presentation.

## Shared rules

- Persian-first, RTL, concise and student-facing.
- Academic screens render canonical backend facts only. Presentation code never becomes enrollment, schedule, grade, announcement or notification authority.
- Human-facing text never exposes course/event/announcement UUIDs, opaque cursors, backend route names or service actions.
- Canonical IDs may exist only in hidden semantic action payloads and must be reauthorized when the integration handler performs the next backend read.
- Lists are bounded even if an integration caller accidentally passes a larger collection.
- Short peer actions may be packed two per row. Long labels occupy a row without forced squeezing.
- Pagination carries an opaque `page_ref`; the screen layer does not decode backend cursors or claim authorization from route state.
- Every secondary screen keeps a contextual back action and `🏠 خانه`.
- Empty state means the projection is empty, not that the underlying domain can never contain data.
- Errors expose recovery wording, not exception/provider details.
- No fake progress, ETA, GPA, weighted average, notification history or course binding is synthesized.

## 1. Course list

Semantic ID: `academic.course.list`

### Populated

```text
📚 درس‌ها
فضای آموزشی: دانشکده نمونه
ترم: نیمسال اول

• ترمیمی ۱ · REST-301 · نیمسال اول
• پریودنتولوژی ۱ · PERIO-301 · نیمسال اول
• رادیولوژی ۱ · RAD-301 · نیمسال اول

[ ترمیمی ۱ ] [ پریودنتولوژی ۱ ]
[ رادیولوژی ۱ ]
[ ‹ قبلی ] [ بعدی › ]        # only when a page ref exists
[ 🏠 خانه ]
```

Rules:
- Source is the canonical course projection attached to the bot academic schedule projection at the frozen base.
- Duplicate offering rows with the same canonical `course_id` collapse to one visible course entry.
- `course_code` is secondary metadata, never the primary label.
- `term_name`/`term_key` is shown only when the canonical rows agree; multiple offering terms are not guessed into a current term.
- Page bound: `10` visible courses.
- Course button payload: `{course_id}`; ID is never visible.

### Empty

```text
📚 درس‌ها

برای این فضای آموزشی هنوز درسی در فهرست قابل‌نمایش منتشر نشده است.
بعداً دوباره این بخش را بررسی کنید یا فضای آموزشی فعال را تغییر دهید.

[ 🏠 خانه ]
```

### Not found / unauthorized

```text
📚 درس در دسترس نیست

این درس در فهرست مجاز فعلی پیدا نشد. فهرست درس‌ها را دوباره باز کنید.

[ ‹ درس‌ها ] [ 🏠 خانه ]
```

Permission-denied copy is deliberately distinct and does not reveal whether a foreign course exists.

## 2. Course detail

Semantic ID: `academic.course.detail`

```text
📚 ترمیمی ۱
درس‌ها › ترمیمی ۱

کد درس: REST-301
ترم: نیمسال اول

[ 📅 برنامه ] [ 📚 منابع ]
[ 🎓 نمرات ]
[ ‹ درس‌ها ] [ 🏠 خانه ]
```

Actions are capability/projection driven. The builder accepts `supported_actions`; unsupported destinations are omitted rather than rendered as fake buttons.

Known action IDs:

| Meaning | Action ID | Current frozen backend status |
| --- | --- | --- |
| Course schedule | `academic.course.schedule` | canonical facts available; server-side course filter missing |
| Course resources | `academic.course.resources` | canonical resource rows available; destination composition integrates with bot-03 |
| Course assessments | `academic.course.assessments` | no bot-native assessment projection; omit unless integration adds one |
| Course grades | `academic.course.grades` | canonical published self grades available; server-side course filter missing |
| Course announcements | `academic.course.announcements` | no canonical course binding; omit |
| Course sessions | `academic.course.sessions` | no bot-safe session projection at frozen base; omit |

## 3. Schedule hub

Semantic ID: `academic.schedule.hub`

Workspace scope:

```text
📅 برنامه

بازه موردنظر را انتخاب کنید. تاریخ و ساعت از منطقه زمانی فضای آموزشی می‌آید.

[ 📅 امروز ] [ 📅 فردا ]
[ 🗓 پیشِ رو ]
[ 🏠 خانه ]
```

Course scope keeps course context and returns to that course.

`پیشِ رو` is shown only when the integration can back it with the existing bounded schedule range projection. At the frozen base the schedule endpoint supports up to 31 local calendar days, so a bounded 7-day/upcoming journey is supported.

## 4. Schedule list/day

Semantic ID: `academic.schedule.list`

Page bound: `12` items.

```text
📅 امروز
برنامه › امروز

• ترمیمی ۱
  ۰۸:۳۰ · ترمیمی ۱ · کلینیک ترمیمی

• جراحی ۱
  ۱۰:۳۰ · جراحی ۱ · بخش جراحی

صفحه ۱
زمان‌ها بر اساس منطقه زمانی فضای آموزشی (Asia/Tehran) نمایش داده می‌شوند.

[ ‹ برنامه ] [ 🏠 خانه ]
```

Rules:
- `starts_at`/`ends_at` must originate from the canonical schedule projection.
- `tenant_workspaces.timezone_name` is authoritative. Host/device timezone is never used as truth.
- The backend returns schedule instants localized to the workspace timezone and includes the IANA timezone name.
- Course/title/location are optional canonical facts. Missing values are omitted, not invented.
- A list row receives a detail action only when a canonical event ID exists.

### Empty

```text
📅 فردا

برای فردا برنامه‌ای ثبت نشده است.
زمان‌ها بر اساس منطقه زمانی فضای آموزشی نمایش داده می‌شوند.

[ ‹ برنامه ] [ 🏠 خانه ]
```

## 5. Event detail

Semantic ID: `academic.schedule.event.detail`

```text
📅 جلسه ترمیمی
برنامه › جزئیات رویداد

زمان: ۰۸:۳۰ تا ۱۰:۳۰
درس: ترمیمی ۱
مکان: کلینیک ترمیمی

زمان‌ها بر اساس منطقه زمانی فضای آموزشی (Asia/Tehran) نمایش داده می‌شوند.

[ ‹ برنامه ] [ 🏠 خانه ]
```

Only canonical event title/time/course/location are presented. Raw `event_type`, internal status enums and IDs are not surfaced unless a future presentation contract maps them explicitly.

## 6. Grades hub/list

Semantic ID: `academic.grades.list`

Page bound: `16` grade rows. Rows are grouped by canonical course title inside the currently fetched page.

```text
🎓 نمرات

ترمیمی ۱
• میان‌ترم — ۱۷٫۵ از ۲۰
  وضعیت: منتشرشده
• پایان‌ترم — ۱۸ از ۲۰
  وضعیت: منتشرشده

پریودنتولوژی ۱
• کوییز ۱ — ۹ از ۱۰
  وضعیت: منتشرشده

صفحه ۱
فقط نمره‌های منتشرشده نمایش داده می‌شوند. معدل یا میانگین در این بخش محاسبه نمی‌شود.

[ 🏠 خانه ]
```

The backend query already restricts this projection to published gradebooks/results for the linked student's authorized enrollment. The V3 label `منتشرشده` reflects that projection contract; it does not infer an unpublished state.

No GPA, weighted course total, class rank or average is calculated.

### Empty

```text
🎓 نمرات

نمره منتشرشده‌ای برای شما پیدا نشد.

[ 🏠 خانه ]
```

## 7. Course grade detail

Semantic ID: `academic.grades.course`

```text
🎓 نمرات · ترمیمی ۱
درس‌ها › ترمیمی ۱ › نمرات

• میان‌ترم — ۱۷٫۵ از ۲۰
  وضعیت: منتشرشده
• پایان‌ترم — ۱۸ از ۲۰
  وضعیت: منتشرشده

هیچ معدل یا میانگینی از روی داده ناقص محاسبه نمی‌شود.

[ ‹ بازگشت به درس ] [ 🏠 خانه ]
```

At the frozen base this requires bounded authorized grade pages to be fetched and filtered by canonical `course_id` in integration code. A native server-side course filter remains preferable for pagination completeness at scale.

## 8. Announcement list

Semantic ID: `academic.announcements.list`

Page bound: `8` items.

```text
📢 اطلاعیه‌ها

• تغییر زمان جلسه جراحی
  ۱۴۰۵/۰۶/۱۹، ۱۲:۳۰
• انتشار منبع جدید
  ۱۴۰۵/۰۶/۱۸، ۱۸:۱۰ · خوانده‌شده

صفحه ۱

[ ‹ قبلی ] [ بعدی › ]
[ 🏠 خانه ]
```

List view is intentionally title-first and concise. Full body belongs to detail.

## 9. Announcement detail

Semantic ID: `academic.announcement.detail`

```text
📢 تغییر زمان جلسه جراحی
اطلاعیه‌ها › تغییر زمان جلسه جراحی

متن کامل اطلاعیه در اینجا نمایش داده می‌شود.

زمان انتشار: ۱۴۰۵/۰۶/۱۹، ۱۲:۳۰
وضعیت: خوانده‌شده

[ ‹ اطلاعیه‌ها ] [ 🏠 خانه ]
```

### Safe link semantics

The frozen announcement projection contains no trusted link field. The builder therefore never extracts URLs from body text and never accepts a raw user-visible/provider URL as domain truth. If a future integration produces a separately validated, short-lived or canonical safe-link reference, it may pass an opaque `safe_link_ref`; the screen then exposes `academic.announcement.link.open` with that hidden reference.

## 10. Personal notification entry

Semantic ID: `academic.notifications.entry`

```text
🔔 اعلان‌های شخصی

اعلان‌های شخصی ممکن است از مسیر پیام‌رسان به شما تحویل شوند، اما تحویل push یک صندوق ورودی دائمی نیست.

📢 اطلاعیه‌ها
اطلاعیه‌های منتشرشده فضای آموزشی فهرست مستقل و قابل‌مشاهده دارند.

🔔 تاریخچه شخصی
در قرارداد فعلی، projection قابل‌اعتماد برای تاریخچه اعلان‌های شخصی وجود ندارد؛ بنابراین تاریخچه محلی ساخته نمی‌شود.

[ 📢 اطلاعیه‌ها ]
[ 🏠 خانه ]
```

`/notifications/project`, `/claim` and `/receipt` are worker delivery/idempotency contracts, not a user inbox. Delivery receipts must never be reconstructed into notification history.

## 11. Loading/error/pagination states

### Loading

```text
📚 درس‌ها
در حال دریافت اطلاعات از فانوس…

[ 🏠 خانه ]
```

Provider adapters should normally use truthful native activity feedback for short reads. This semantic state exists for renderers that need a persistent loading surface. It has no percent or ETA.

### Error

```text
❌ نمرات

این بخش فعلاً بارگذاری نشد. دوباره تلاش کنید؛ اگر مشکل ادامه داشت از خانه مسیر را از نو باز کنید.

[ 🔄 تلاش دوباره ]
[ 🏠 خانه ]
```

Security-sensitive errors should be normalized by bot-01/core before reaching this presentation. Raw backend/provider messages are never expected here.

### Pagination

- Page labels use Persian human digits.
- Previous/next appear only when an opaque page reference exists.
- Page references are subject/provider-bound presentation correlation owned by core/integration, not authorization.
- Opening a page must perform a fresh canonical backend read.
- Bot-02 never persists cursors, membership, grades, read state or course truth.

## Provider rendering expectation

Telegram may render headings, semantic lists/facts and RTL through its verified rich-message capability. Bale renders the same semantic hierarchy with readable native text and inline actions. Academic source does not branch on provider, emit Telegram-only rich payloads or infer Bale capabilities.
