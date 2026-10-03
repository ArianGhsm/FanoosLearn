# FANOOS UI/UX DESIGN LOCK — REBUILD V3

**Design Lock ID:** `FANOOS-UX-2026.09-R1`  
**Canonical repository:** `ArianGhsm/FanoosLearn`  
**Applies to:** Website, Telegram, Bale  
**Status:** Authoritative for the entire Rebuild V3 parallel wave

---

## 1. Purpose

FANOOS is not allowed to drift into twelve unrelated visual styles. Every parallel implementation must read this file before writing code and treat it as the shared design language, interaction grammar, information hierarchy, accessibility baseline, and quality bar.

The rebuild is not a cosmetic reskin. The product must feel like a coherent modern student platform rather than an API viewer, generic admin dashboard, command bot, text dump, or collection of disconnected cards.

---

## 2. Product identity

FANOOS is a **student academic infrastructure + learning/content platform**.

It should feel:
- modern;
- premium without being luxurious;
- calm;
- academic;
- trustworthy;
- fast;
- student-centered;
- useful within seconds.

It should not feel:
- childish;
- government-portal-like;
- corporate-HR;
- crypto/neon;
- over-gamified;
- excessively glassy;
- gradient-heavy;
- or like a generic Bootstrap admin template.

Quality reference: modern American education/SaaS products such as Canvas/Instructure, Coursera, Khan Academy and strong contemporary SaaS products. These are **quality references, never pixel-copy targets**.

Transferable principles:
- predictable navigation;
- course-first information architecture;
- strong typography and spacing;
- accessible components;
- calm surfaces;
- fast path to the next student task;
- progressive disclosure;
- robust empty/loading/error states;
- clear distinction between content, action, status and navigation.

---

## 3. Canonical product spine

1. خانه / امروز
2. درس‌ها
3. برنامه
4. یادگیری / منابع
5. آزمون‌ها و بانک سؤال
6. نمرات
7. اطلاعیه‌ها
8. اعلان‌های شخصی
9. فرم‌ها
10. خرید و دسترسی
11. فضای آموزشی
12. حساب
13. مدیریت — only when canonically authorized

Website, Telegram and Bale may present this differently, but semantic meaning must stay aligned.

A **Course** is a first-class product object, not a hidden filter. A course may include overview, sessions, schedule, resources, notes/summaries, question banks, assessments, grades, announcements and instructor metadata when available. Never fabricate sections from unavailable backend data.

---

## 4. Visual signature

### 4.1 Direction

**Calm academic clarity with a subtle lantern glow.**

The lantern metaphor appears only through warm attention accents, restrained depth and subtle illumination around high-value actions. Do not turn the product into a literal lantern-themed illustration system.

### 4.2 Core palette

```css
--f3-bg: #F6F8F7;
--f3-bg-elevated: #FBFCFB;
--f3-surface: #FFFFFF;
--f3-surface-subtle: #F0F4F2;
--f3-surface-strong: #E7EEEA;

--f3-text: #13201C;
--f3-text-secondary: #53635D;
--f3-text-tertiary: #788680;
--f3-text-inverse: #FFFFFF;

--f3-border: #DCE5E1;
--f3-border-strong: #C8D6D0;

--f3-primary: #126B57;
--f3-primary-hover: #0E5A49;
--f3-primary-pressed: #0A493C;
--f3-primary-soft: #E4F2EC;

--f3-accent: #5B5BD6;
--f3-accent-soft: #EEEEFF;

--f3-lantern: #D99A22;
--f3-lantern-soft: #FFF4D9;

--f3-success: #067647;
--f3-success-soft: #E7F6EC;
--f3-warning: #A15C07;
--f3-warning-soft: #FFF1D6;
--f3-danger: #B42318;
--f3-danger-soft: #FDECEC;
--f3-info: #175CD3;
--f3-info-soft: #EAF2FF;
```

Rules:
- emerald is primary interaction color;
- indigo is secondary accent, not a second primary;
- lantern amber is reserved for attention/highlight;
- status is never color-only;
- avoid full-page saturated backgrounds;
- no random module-specific palettes.

Dark mode is outside this wave unless later explicitly requested.

---

## 5. Typography

Persian-first, RTL-native. No worker may bundle or commit a proprietary font file or introduce an external font CDN.

Preferred stack:

```css
font-family: "Vazirmatn", "IRANSansX", system-ui, -apple-system, BlinkMacSystemFont,
  "Segoe UI", Tahoma, Arial, sans-serif;
```

Use the first locally available option.

Type scale:

```text
Display  32–36 / 1.25 / 700
H1       28–32 / 1.35 / 700
H2       22–24 / 1.45 / 700
H3       18–20 / 1.55 / 650–700
Body L   16–17 / 1.90 / 400–500
Body     14–16 / 1.85 / 400–500
Meta     12–13 / 1.70 / 500–600
```

Rules:
- do not bold everything;
- long academic titles wrap naturally;
- course codes, hashes and technical tokens use LTR isolation/`<bdi>`;
- Persian punctuation and نیم‌فاصله are consistent;
- number-script choice follows semantics, not decoration.

---

## 6. Spacing, radii and elevation

Spacing scale: `4 / 8 / 12 / 16 / 20 / 24 / 32 / 40 / 48 / 64`.

Default rhythm:
- section gap: 32–40;
- component-group gap: 16–24;
- list row vertical padding: 12–16;
- mobile edge: 16;
- tablet edge: 24;
- desktop content inset: 32–40.

Radius:
- small control: 8;
- input/button: 10–12;
- surface/card: 14–16;
- large sheet/dialog: 18–20;
- pill: 999 only for true pills/chips.

Elevation:
- default surface uses border first;
- floating menu/dialog gets subtle shadow;
- hero/high-value surface gets restrained shadow;
- never stack heavy shadows.

Do not make every object a floating rounded card.

---

## 7. Website shell

Desktop:
- persistent sidebar or rail;
- compact top header;
- clear active workspace context;
- page content with readable max width;
- secondary contextual navigation inside domains.

Mobile:
- bottom navigation for highest-frequency destinations;
- secondary destinations in sheet/drawer;
- persistent workspace/context access;
- safe-area aware;
- no horizontal table overflow.

Preferred mobile primary nav:
- خانه
- درس‌ها
- برنامه
- منابع
- بیشتر

The shell must make every domain feel like one product, not microsites.

---

## 8. Website layout grammar

### Page header
- eyebrow/context when useful;
- one H1;
- one short supporting line;
- one primary action at most;
- secondary actions grouped separately.

### Section
Use whitespace + heading + content. Do not wrap every section in a card.

### Card
Use only for self-contained semantic objects such as course, resource, assessment, order or meaningful home summary.

### Data list
Use clean rows + separators for dense information such as grades, announcements, sessions and notifications.

### Table
Use on desktop only when column comparison helps. Adapt to stacked rows/cards on narrow screens.

### Dashboard
Dashboard is not a card grid. It is a prioritized composition of next action, today, attention, recent activity and shortcuts.

---

## 9. Website interaction grammar

Every destination must encode:
- initial state;
- loading;
- content;
- empty;
- partial failure;
- full error;
- permission denied;
- session expired where relevant;
- safe retry;
- mutation pending;
- mutation success/failure.

No `alert()`.
No fake progress.
No fake ETA.
No unexplained disabled controls.

Use imperative Persian verbs for actions: مشاهده، ادامه، باز کردن، ارسال، ثبت، پرداخت، تلاش دوباره، انتخاب.

Never surface raw endpoint names, UUIDs or developer terms in normal UI.

---

## 10. Motion

Motion is functional, quiet and fast.

```text
micro interaction 120–160ms
surface transition 160–220ms
drawer/dialog 200–260ms
```

Allowed:
- fade + 4–8px translate;
- subtle 0.98→1 popover scale;
- restrained skeleton shimmer;
- limited first-load section reveal.

Forbidden:
- parallax;
- bounce;
- constant floating;
- decorative spinners;
- motion that delays information.

Honor `prefers-reduced-motion`.

---

## 11. Responsive quality bar

Minimum widths: `320 / 360 / 390–430 / 768 / 1024 / 1280 / wide desktop`.

Rules:
- no horizontal body overflow;
- no clipped Persian text;
- touch targets approximately 44px where practical;
- sticky elements never hide content;
- dialogs/drawers fit small screens;
- mobile bottom nav respects safe-area;
- long labels may wrap without breaking controls;
- no desktop-only critical action.

---

## 12. Accessibility

Target **WCAG 2.2 AA** as the design baseline.

Required:
- semantic landmarks;
- logical heading hierarchy;
- visible focus;
- focus not obscured;
- keyboard operation;
- skip navigation where relevant;
- accessible dialogs/drawers;
- adequate target sizes;
- no color-only status;
- labels and error associations;
- `aria-current` for navigation;
- `aria-live` for async feedback;
- reduced motion;
- accessible authentication patterns;
- consistent primary navigation/help placement.

RTL must work structurally, not only visually.

---

## 13. Icons and illustration

Website:
- one local outline SVG family;
- consistent stroke weight/viewBox;
- `currentColor`;
- decorative icons `aria-hidden`;
- no emoji as primary website navigation iconography.

Empty-state illustration:
- optional;
- simple vector;
- sparse;
- no stock-photo feel;
- never dominates the task.

Telegram/Bale use semantic emoji rather than fake icon systems.

---

## 14. Microcopy

Tone:
- concise;
- competent;
- student-friendly;
- non-bureaucratic;
- non-patronizing;
- non-developer-facing.

Good: `برای این درس هنوز منبعی منتشر نشده است.`

Bad: `Resource list is empty.`

Empty states explain:
1. what is empty;
2. whether this is normal;
3. what the user can do next.

---

## 15. Course-first UX

Course list:
- title dominant;
- code secondary;
- term/status subtle;
- counts only when canonical and useful.

Course detail:
- course context remains visible;
- tabs/secondary nav remain bounded;
- overview summarizes next useful steps;
- schedule/resources/grades/assessments/announcements keep clear course scope.

Never show raw `course_id`.

---

## 16. Home / Today UX

Home answers within seconds:
1. الان کجا هستم؟
2. امروز/بعدی چه چیزی مهم است؟
3. از اینجا سریع کجا بروم؟

Priority:
- active workspace;
- current date/context;
- next schedule item;
- today schedule;
- upcoming assessment/deadline;
- recent/important announcement;
- recent learning item;
- grade update if available;
- quick actions.

Do not fabricate recommendation/ranking.

---

## 17. Resources / learning

Library presentation supports:
- course;
- type;
- recent;
- search;
- access state;
- protected state;
- version;
- detail.

Protected states:
- available;
- preparing;
- ready;
- expired;
- denied;
- unavailable.

Never expose storage keys, filesystem paths, credentials or raw provider IDs.

---

## 18. Assessments and grades

Assessments:
- active;
- upcoming;
- completed;
- practice/past exam if canonical backend supports them.

Presentation never becomes scoring authority.

Grades:
- term/course grouping;
- score/max;
- published state;
- explicit missing state.

Never invent GPA/average policy.

---

## 19. Commerce and access

Payment and entitlement/access are separate.

Human states include:
- در انتظار پرداخت
- پرداخت تأیید شد
- پرداخت ناموفق
- دسترسی فعال
- دسترسی منقضی
- دسترسی لغو شده
- وضعیت نامشخص

Raw product IDs are forbidden in normal UX. Browser/provider success never grants access by itself.

---

## 20. Management

Management remains visually part of FANOOS.

Rules:
- permission-gated;
- context-aware;
- destructive actions require explicit confirmation;
- desktop dense data may use tables;
- no fake control for nonexistent backend action.

Deployment/Update Server remains outside normal website management under the current product contract.

---

# PART II — TELEGRAM & BALE

## 21. Bot philosophy

Telegram and Bale are compact product interfaces, not website pages converted to text.

Every screen has:
- clear context;
- one dominant purpose;
- bounded information;
- predictable action rows;
- a way back/home;
- useful behavior even when data is absent.

A bot screen is a product surface, not a log message.

---

## 22. Critical zero-state rule

This failure pattern is forbidden as the default zero state:

```text
Title
+ warning/quote
+ one “open website” button
```

Linked account with no workspace must still expose product structure, for example:

```text
🏠 فانوس

حساب شما متصل است ✅
هنوز فضای آموزشی فعالی برای این حساب ندارید.

[ 🏫 فضای آموزشی ]
[ 👤 حساب من ]
[ ℹ️ راهنمای شروع ]
[ 🌐 باز کردن فانوس ]

[ 🏠 خانه ]
```

No data state ≠ no UI.

---

## 23. Bot Home with active workspace

Reference grammar:

```text
🏠 خانه
دندان‌پزشکی تهران · ورودی ۱۴۰۲

📅 بعدی
ترمیمی ۱ — ۰۸:۳۰
دانشکده دندان‌پزشکی

📢 تازه
عنوان کوتاه اطلاعیه

[ 📚 درس‌ها ] [ 📅 برنامه ]
[ 🎓 نمرات ] [ 🔔 اعلان‌ها ]
[ 📚 منابع ] [ 📝 آزمون‌ها ]
[ 👤 حساب ]   [ ➕ بیشتر ]
```

Keep it bounded. Do not repeat identical facts in multiple blocks.

---

## 24. Bot navigation grammar

Every deep screen supports predictable exits.

Standard concepts:
- 🏠 خانه
- بازگشت
- درس‌ها
- صفحه قبل / بعد
- تلاش دوباره
- لغو

Typed commands are bootstrap/escape hatches, not normal navigation.

---

## 25. Bot button hierarchy

Prefer:
- 2 short buttons per row;
- 1 full-width row for a strong primary action;
- pagination on one row;
- Home/Back at the bottom.

Avoid 8 single-button rows unless provider constraints require it.
Do not put destructive actions beside routine navigation.

---

## 26. Telegram presentation

Telegram should feel like a native mini product:
- concise formatted text;
- semantic headings;
- compact sections;
- inline keyboard;
- edits for navigation when safe;
- new messages for durable/important events;
- protected send behavior when required.

Use only verified provider capabilities. Rich-render failure may fall back without replaying business actions.

---

## 27. Bale presentation

Bale gets the same semantic product, honestly adapted.

Use:
- readable Persian structure;
- concise sections;
- supported keyboards;
- supported editing when verified.

No Telegram-only payloads. If required protection is unavailable, fail closed clearly.

---

## 28. Bot emoji system

```text
🏠 Home
📚 Courses / Learning
📅 Schedule
🎓 Grades
📢 Announcements
🔔 Notifications
📝 Assessments
💳 Purchase & access
🏫 Workspace
👤 Account
⚙️ Management
🔄 Update
🔒 Protected
✅ Success
⚠️ Warning
❌ Error
ℹ️ Information
```

One semantic icon per label is enough.

---

## 29. Bot content density

Typical screen:
- title: 1 line;
- context: 0–1 line;
- intro: 0–2 lines;
- sections: 1–3;
- actions: 3–8 buttons.

Long datasets are paginated/summarized. Do not dump dozens of rows into one message.

---

## 30. Bot error grammar

Error UI answers:
1. what happened;
2. whether recovery is possible;
3. what to do next.

Never show stack traces, raw HTTP payloads, raw enums, HMAC details, provider tokens or internal IDs.

---

## 31. Bot progress grammar

Short operation: provider activity/chat action.

Long unmeasured operation: factual waiting state.

Measured operation: percent only if the true total exists.

No fake ETA. No fake percent. One changing status indicator.

---

## 32. Cross-channel semantic lock

Website, Telegram and Bale must agree on account identity, workspace membership, course identity, schedule timezone, grades, announcements, resources, assessment state, order/payment, entitlement/access and permission result. Only presentation differs.

---

# PART III — PARALLEL WORKSTREAM CONTRACT

## 33. Mandatory no-conflict strategy

No independent worker may modify shared current entrypoints.

Website workers must NOT modify:
- `apps/platform/public/index.php`
- `apps/platform/public/assets/app.js`
- `apps/platform/public/assets/app.css`
- `apps/platform/public/assets/domain-ux.js`
- `apps/platform/public/assets/domain-ux.css`
- existing `apps/platform/public/assets/ui-v2/**`

Bot workers must NOT modify:
- `packages/python/fanoos_bot/application.py`
- `packages/python/fanoos_bot/integrated_application.py`
- `apps/telegram-bot/runtime.py`
- `apps/bale-bot/runtime.py`
- existing provider production wiring

All may READ those files.

---

## 34. Website V3 workstream paths

All independent website code lives under:

`apps/platform/public/assets/ui-v3/`

Ownership:

```text
web-01 → foundation/**
web-02 → shell/**
web-03 → home/**
web-04 → courses/**
web-05 → schedule/**
web-06 → learning/**
web-07 → progress/**
web-08 → operations/**
```

Workstream metadata:

`docs/ui-v3/workstreams/web-XX-<slug>/`

No worker writes another worker's directory.

---

## 35. Website module contract

Every website module except foundation exports an integration-friendly definition:

```js
export const moduleDefinition = {
  id: "unique-id",
  routes: [],
  navItems: [],
  mount(ctx) {},
  unmount(ctx) {}
};
```

`ctx` is the integration contract:

```text
ctx.root
ctx.api
ctx.state
ctx.navigate
ctx.format
ctx.ui
ctx.capabilities
ctx.signal
```

A worker may inspect current APIs and existing V2 code, but cannot create new canonical business authority.

Parallel modules may import the documented future foundation path even if that path becomes physically available only after branch integration.

---

## 36. CSS isolation

Prefix domain classes:

```text
.f3-shell-*
.f3-home-*
.f3-course-*
.f3-schedule-*
.f3-learning-*
.f3-progress-*
.f3-ops-*
```

Foundation owns shared `.f3-*` primitives. Domain modules do not globally style raw tags. `!important` is not normal conflict resolution.

---

## 37. Bot V3 workstream paths

New bot product code:

`packages/python/fanoos_bot/ui_v3/`

Ownership:

```text
bot-01 → core/**
bot-02 → academic/**
bot-03 → learning/**
bot-04 → providers/**
```

Handoff metadata:

`docs/ui-v3/workstreams/bot-XX-<slug>/`

---

## 38. Bot V3 semantic contract

Core should support provider-neutral concepts:

```text
Screen
Section
Fact
ListItem
Action
ActionRow
Pagination
Severity
Context/Breadcrumb
ProtectContent
EditPolicy
```

Exact Python data structures are owned by `bot-01/core`. Other workers code against this contract. Provider renderers are owned by `bot-04/providers`. Business authorization stays backend-owned.

---

## 39. No-execution rule

All 12 implementation prompts are SOURCE-ONLY.

Allowed:
- read repository;
- inspect current UI/UX and backend contracts;
- write source inside owned paths;
- write minimal workstream handoff metadata;
- commit to the dedicated GitHub branch.

Forbidden:
- deployment;
- server access or mutation;
- production DB access;
- live Telegram/Bale sends;
- browser/live smoke;
- tests;
- test creation;
- running existing tests;
- CI/workflow changes;
- opening/merging PRs;
- merging to main;
- database migration;
- backend business/schema redesign.

---

## 40. Parallel base rule

Before starting workers:
1. add this file at `docs/ui-v3/FANOOS_UI_UX_DESIGN_LOCK.md`;
2. commit it to `main`;
3. do not merge any V3 worker into main until all 12 finish;
4. every worker starts from that same stable main.

At worker start:
- verify this file and Design Lock ID;
- capture current `main` as `PARALLEL_REBUILD_BASE_SHA`;
- if another `rebuild-v3/*` branch already records a different base, stop with `PARALLEL_BASE_DRIFT`;
- never silently rebase to newer main.

---

## 41. Workstream handoff

Every worker creates `WORKSTREAM_HANDOFF.md` in its owned docs directory containing:
- design lock ID;
- base SHA;
- branch;
- owned paths;
- files created;
- current-code elements reused;
- integration imports/dependencies;
- canonical API projections consumed;
- `INTEGRATION_GAPS`;
- user-visible screens/components produced.

No test report is created because tests are intentionally excluded from this wave.

---

## 42. Definition of quality

A workstream is complete only when its source:
- obeys this lock;
- is coherent as a real product surface;
- reuses current backend semantics;
- encodes loading/empty/error UX;
- encodes responsive behavior where relevant;
- avoids developer data leakage;
- avoids visual drift;
- and is ready for a later dedicated merge/integration prompt.

---

## 43. Authority

If a worker preference conflicts with this file, **this file wins**.

If this file conflicts with a canonical FANOOS security/business invariant, **the security/business invariant wins**. Document the conflict; do not invent a third rule.
