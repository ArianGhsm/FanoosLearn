# FANOOS UI V2 — Canonical Product IA

FANOOS is one student academic/content platform with three presentation channels: Website, Telegram and Bale. Channel layout may differ; canonical identity, workspace, data, permission and outcome may not.

## Product spine

| Canonical label | Purpose | Website | Telegram | Bale | Canonical source | Permission / context | Unsupported-channel fallback |
| --- | --- | --- | --- | --- | --- | --- | --- |
| خانه / امروز | Active-workspace summary: next academic item and recent important broadcast, without invented ranking | `#/home` | `home`, `today` | `home`, `today` | account/workspace + schedule + announcements | active membership; downstream endpoints reauthorize | unavailable parts are omitted/empty, never synthesized |
| درس‌ها | First-class canonical course navigation | `#/courses` | `courses`, `course:*` | same semantic actions | `/workspaces/{id}/academics`; bot academic course projection from canonical DB | `academic.view`, selected workspace | safe Website link if a deeper channel action is absent |
| برنامه | Calendar/timeline of canonical schedule events | `#/schedule` | today/tomorrow/7-day | same | schedule projections | `academic.view`; `tenant_workspaces.timezone_name` authoritative | bounded list; no host-timezone inference |
| جلسات | Course session context | Course detail | course detail where projected | same | academics/course offering/session data | `academic.view` | Website for rich session detail |
| منابع / یادگیری | Authorized content library and course resources | `#/resources`, course resources | resources/course resources | same | content resource catalog + authorization + secure delivery | `resource.view` plus access policy/entitlement | Bale fails closed if required forward protection is unavailable |
| جزوه‌ها / خلاصه‌ها / بانک سؤال | Resource-type views, not separate authorities | resource filters | localized resource type | same | resource `type_key` | same as resources | channel-friendly list / Website detail |
| آزمون‌ها | Assessment catalog, attempt and server result | `#/assessments` with canonical start/save/submit/review | destination + safe Website CTA | destination + safe Website CTA | ExamService/core-v1 | `exam.take` + entitlement/policy | no local bot scoring or answer-key state |
| نمرات | Published self-grade items | `#/grades` | `grades`, course grades | same | published grade projection | `grade.view_self` | no fabricated GPA/average |
| اطلاعیه‌ها | Workspace/course broadcast content | `#/announcements` | announcements | same | published notification messages/recipients | `notification.receive` | course filter only when canonical binding exists |
| اعلان‌ها | Personal delivery/action concept | no fake durable inbox | notification hub + delivered pushes | same | notification projection/worker delivery contracts | recipient identity + workspace | until a durable inbox projection exists, do not present delivery receipts as inbox history |
| خرید و دسترسی | Products/orders/payment state/entitlement | `#/orders`; browser checkout when a canonical catalog/provider exists | safe Website handoff; technical legacy order path not primary UX | same | commerce + payment verification + entitlements | `commerce.purchase`; payment and entitlement remain distinct | no product UUID/provider-code entry in normal UX |
| فضای آموزشی | Membership and active workspace context | workspace selector/account | workspaces/select | same | canonical account/membership + messaging link | active membership; selection is UX state only | every operation reauthorizes workspace |
| حساب | Canonical human identity and linked-channel context | `#/account` | account | account | IAM session / messaging link identity | own account | username/display name never establishes identity |
| مدیریت | Role-aware workspace/platform actions | `#/management` only for canonical capabilities | limited channel-native role surfaces | limited channel-native role surfaces | RBAC-authorized endpoints | permission-specific; hidden UI is not authorization | absent if no supported canonical action |
| به‌روزرسانی سرور | Private deployment control plane, not ordinary product management | **not exposed** | private Telegram owner/operator only | **not exposed** | deployment control plane | `deployment.manage`, linked canonical user, Telegram private context | fail closed everywhere else |

## Canonical course object

A Course is identified by canonical `course_id`; title/code are presentation metadata. Website and bots may remember a selected course for navigation, but neither stores a shadow course truth. Course-sensitive operations are reauthorized against the selected workspace.

Course detail maps to the same concepts: overview, sessions, schedule, resources, assessments, grades and announcements. A channel that lacks a safe native projection uses an explicit fallback rather than inferred data.

## Home priority

Home does not introduce a ranking database or recommendation engine. Order is deterministic:

1. active workspace context;
2. nearest canonical schedule event in the workspace timezone;
3. most recent authorized announcement;
4. explicit navigation actions.

A richer Website can show more cards, but it may not change the underlying meaning.

## Time contract

`tenant_workspaces.timezone_name` is authoritative. Website date ranges are computed in that timezone; public schedule date-only bounds are resolved in that timezone before querying UTC storage. Telegram/Bale use the same workspace-local calendar semantics. Host PHP timezone and user-device timezone are never schedule authority.

## Announcement vs notification

An **اطلاعیه** is broadcast content. An **اعلان** is a personal event/delivery/action. Existing push delivery/receipt infrastructure is not a durable personal inbox. No channel may conflate the two merely to fill a navigation destination.

## Payment vs access

Order state, payment state and entitlement state are separate canonical facts. A redirect/browser success state cannot grant access. Entitlement changes only follow canonical backend policy/payment verification or authorized administrative action.
