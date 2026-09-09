# FANOOS UI V2 — Web Screen Map

| Screen | Route/state | Purpose | Canonical source/API | Roles | Empty state | Primary action | Mobile behavior |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Login | unauthenticated | Establish canonical session | `POST /api/v1/auth/login` | all users | N/A | ورود | Two-column collapses to single card |
| Home/Today | `#/home` | What matters now | schedule, assessments, announcements, resources, grades | student+ | Per-section empty/partial failure | Open destination | Stacked overview, bottom nav |
| Courses | `#/courses` | Browse academic courses | `/academics` | `academic.view` users | “هنوز درسی ثبت نشده” | Open course | Single-column cards |
| Course overview | `#/courses?course=CODE` | Course context | academics + filtered schedule/resources/assessments + grades | permitted student+ | Per-panel states | Switch tab | Horizontal/scrollable tabs |
| Course sessions | `...&tab=sessions` | Ordered sessions | `/academics` rows | permitted student+ | No sessions | Back/course tabs | Compact list |
| Course schedule | `...&tab=schedule` | Events for course | `/schedule` filtered by canonical course relation | permitted student+ | No events | Course tabs | Timeline |
| Course resources | `...&tab=resources` | Learning content for course | `/resources?course_id=` | `resource.view` | No resources | Resource detail | Card list |
| Course assessments | `...&tab=assessments` | Assessments for course | `/assessments?course_id=` | `exam.take` | No assessments | Assessment detail | Card list |
| Course grades | `...&tab=grades` | Published course grades | `/grades/me` + canonical course match | `grade.view_self` | No published grades | Course tabs | Table adapts to cards |
| Schedule Today | `#/schedule/today` | Today timeline | `/schedule?from=&to=` | `academic.view` | No schedule | Change mode | Timeline |
| Schedule Week | `#/schedule/week` | Current academic week | same | same | No schedule | Change mode | Timeline; no ornamental grid |
| Schedule Upcoming | `#/schedule/upcoming` | Future events | same | same | No upcoming events | Change mode | Timeline |
| Resource library | `#/resources` + filters | Search/filter learning content | `/resources?q=&type=&course_id=&sort=` + `/academics` | `resource.view` | No matching resource | Apply filters | Filters stack |
| Resource detail | transient selected resource | Metadata/access | `/resources/{id}` | `resource.view` | Unavailable detail | Secure delivery when contract supports | Full-width detail card |
| Assessments | `#/assessments?kind=` | Browse practice/assessment/past exams | `/assessments?kind=` + `/academics` | `exam.take` | No assessments | Open detail | Segmented filter wraps/scrolls |
| Assessment detail | transient selected assessment | Safe assessment metadata | catalog item | `exam.take` | N/A | Action intentionally limited by current Web projection | Full-width detail |
| Grades | `#/grades` | Published results | `/grades/me` | `grade.view_self` | No published grades | None fabricated | Tables become mobile cards |
| Announcements | `#/announcements` | Official workspace messages | `/announcements` | authorized workspace members | No announcements | Open detail | Inbox-like list |
| Announcement detail | transient selected announcement | Read message | same + `POST /announcements/{id}/read` | same | N/A | Mark read | Detail panel |
| Forms | `#/forms` | Active forms | `/forms` | workspace access | No active forms | Open form | Stacked list |
| Form detail | transient selected form | Render recognized schema fields | form projection + `POST /forms/{id}/submissions` | permitted member | Unsupported schema is explained; JSON hidden | Submit | Single-column fields |
| Purchase & Access | `#/orders` | Existing orders/payment/access | `/orders` | purchaser | No orders | None until catalog projection exists | Stacked order rows |
| Search | `#/search?q=` | Workspace search | `/search?q=` | current workspace member | No result | Search | Full-width results |
| Account/Workspaces | `#/account` | Profile, memberships, active workspace | `/account`, `/workspaces/select` | authenticated user | No membership | Select workspace | Stacked account cards |
| Management | `#/management` | Supported scoped management overview | `/admin/dashboard` scoped | backend-authorized managers only | Authorized but no sections | Contextual follow-up in future projections | Hidden unless authorized; stacked cards |

## Deliberately absent screens

- Website Update Server / deployment control.
- Fake Notification Center backed by internal bot notification projection.
- Product catalog/checkout that asks for an opaque product ID.
- Client-side assessment scoring/answer reveal.
- Client-generated GPA/average.
- Browser page exposing storage object keys or direct private paths.
