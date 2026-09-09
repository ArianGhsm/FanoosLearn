# FANOOS UI V2 — Web Information Architecture

## Product spine

Desktop primary navigation:

1. خانه
2. درس‌ها
3. برنامه
4. یادگیری و منابع
5. آزمون‌ها
6. نمرات
7. اطلاعیه‌ها
8. بیشتر: فرم‌ها، خرید و دسترسی، حساب، و مدیریت when authorized

Mobile bottom navigation:

- خانه
- درس‌ها
- برنامه
- منابع
- بیشتر

`بیشتر` opens an accessible drawer containing assessments, grades, announcements, forms, purchase/access, account, and Management only when the backend authorizes the scoped dashboard.

## Global context

### Workspace

The active workspace is globally visible and switchable. The selection remains canonical server state through `POST /api/v1/workspaces/select`. V2 never stores a separate workspace truth in browser storage. Before mutation, the request serial is invalidated; stale reads from the previous workspace are ignored.

### Account

Account is independent of workspace navigation. It shows user-facing profile data, memberships and active workspace. It does not expose internal user/workspace IDs.

### Search

Global search is scoped to the active workspace and only executes for queries of at least two characters. It uses the canonical `/search` endpoint and does not build a second local index.

## Route/state model

The Website uses reload-safe hash routes to remain compatible with the existing static/PHP entrypoint and avoid a framework/router migration.

- `#/home`
- `#/courses`
- `#/courses?course=<human-course-code>`
- `#/courses?course=<human-course-code>&tab=sessions|schedule|resources|assessments|grades`
- `#/schedule/today`
- `#/schedule/week`
- `#/schedule/upcoming`
- `#/resources?q=&type=&course=<human-course-code>`
- `#/assessments?kind=practice|mock_exam|past_exam`
- `#/grades`
- `#/announcements`
- `#/forms`
- `#/orders`
- `#/account`
- `#/search?q=`
- `#/management` only after backend authorization succeeds

Opaque resource/assessment/form/announcement identifiers remain transient action state and are not displayed or made the primary human route.

## Home composition

Home is a composition of independent safe reads rather than a new client authority:

- schedule → next event/today
- assessments → available/upcoming assessment summary
- announcements → recent communication
- resources → recent resources
- grades → recent published grade items

Each panel can fail independently. A failed resource call does not collapse schedule or announcements. No “Home aggregate” is treated as domain truth.

## Course architecture

Course list is built from the canonical academic projection and groups repeated session rows by canonical course identity. The visible/bookmarkable key is human course code when available; internal IDs are used only to call canonical filters.

Course Detail tabs:

- نمای کلی
- جلسات
- برنامه
- منابع
- آزمون‌ها
- نمرات

A tab whose projection failed is not presented as healthy content. A course-announcements tab is intentionally absent until a public course-scoped announcement relation exists.

## Learning/resources

Library filters are translated to canonical API query parameters:

- `q`
- `type`
- `course_id` resolved from current academic projection
- `sort=newest`

Resource detail never exposes storage/object identifiers. Protected delivery uses issue/consume authorization with `channel=web`; final binary serving remains constrained by the public contract documented in `WEB_BACKEND_UI_GAPS.md`.

## Assessments

The browse IA distinguishes all/practice/mock/past-exam categories using canonical assessment `kind`. Detail explains that answers and scoring are server-owned. V2 does not reconstruct the question engine or reveal answers in JavaScript.

## Grades

Published grade items are grouped by course. Score/max are rendered as provided. No GPA, weighted average, or “term average” is calculated because policy/completeness cannot be guaranteed from the current self-grade projection.

## Announcements vs notifications

Announcements are a strong Website destination because a public workspace/user projection exists. A separate personal Notification Center is not invented because current notification projector/receipt flows are internal channel infrastructure, not a public Web inbox contract.

## Purchase/access

The backend supports order creation by product ID, but no public catalog projection is available in the audited Web contract. Therefore V2 presents existing order/payment/access state and explicitly avoids a developer-style product-ID input. Catalog purchase belongs after Prompt 3 integration closes the projection gap.

## Management

`GET /api/v1/workspaces/{workspace}/admin/dashboard` is used as the permission gate. A 403 hides Management. A success renders only the sections the backend returned. No Website deployment/update control is added.
