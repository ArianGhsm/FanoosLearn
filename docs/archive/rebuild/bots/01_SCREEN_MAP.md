# FANOOS Bot Stage 1 — Product Screen Map

The map is provider-neutral. Telegram and Bale render the same semantic screen and action identifiers; only layout, formatting, callback acknowledgement and protected-delivery capability differ.

## Entry and account

| Screen identifier | Purpose | Primary actions | Zero/error rule |
|---|---|---|---|
| `onboarding.unlinked` | A channel identity is not linked to a FANOOS account | Open FANOOS, help, home | Explain the link flow; never ask for a raw technical token in ordinary navigation |
| `onboarding.linked_no_workspace` | Linked account with no active workspace | Workspaces, account, help, FANOOS, home | State that membership comes from FANOOS; do not invent a create-workspace action |
| `workspace.list` / `onboarding.multiple_workspaces` | Explicit workspace selection | Select a listed workspace, account, help, home | Never implicitly select the first membership |
| `onboarding.one_workspace` | One canonical workspace projection | Activate or open home, account, help | Selection remains an explicit backend command |
| `account.linked` | Channel/account status and active workspace | Switch workspace, open web account, unlink | Unlink confirmation is a separate screen |
| `account.unlink_confirm` / `account.unlink_success` | Safe channel unlink flow | Confirm, cancel/home | Explain that the FANOOS account and learning data are not deleted |
| `core.help` | Human getting-started guide | Workspace, account, web, home | Commands are compatibility help, not the primary UX |

## Home and academic spine

| Screen identifier | Purpose | Primary actions |
|---|---|---|
| `home.active` | Current workspace snapshot | Courses, schedule, grades, notifications, resources, assessments, account, more |
| `academic.course.list` | Workspace course projection | Open course, page, back/home |
| `academic.course.detail` | Course context and available child journeys | Schedule, resources, grades, supported assessments/announcements, back/home |
| `academic.schedule.hub` / `academic.schedule.list` | Today, tomorrow and upcoming schedule | Date window, event detail, pagination, back/home |
| `academic.grades.list` / `academic.grades.course` | Published grade projection | Course detail, pagination, back/home |
| `academic.announcements.list` / `academic.announcement.detail` | Published announcements | Open item, pagination, back/home |
| `academic.notifications.entry` | Notification item/detail | Read state or source route, back/home |

Every academic collection has explicit empty and unavailable states. Date labels use the workspace timezone returned by the backend.

## Learning, assessment and commerce

| Screen identifier | Purpose | Safety rule |
|---|---|---|
| `learning.resource_hub` / `learning.resource_detail` | Resource list and safe metadata | IDs/storage keys stay hidden; access is rechecked by backend |
| `learning.protected.*` | Protected-resource readiness, denial and delivery | No unprotected fallback; provider capability decides delivery |
| `learning.assessment_hub` / `learning.assessment_detail` | Assessments and question-bank entry points | Answers/scoring remain server-owned |
| `learning.commerce_hub` | Catalog/order/access entry point | User chooses a product action, not a UUID or command |
| `learning.order_access_detail` | Separate order, payment and entitlement facts | Paid UI never implies entitlement |
| `learning.forms_hub` / `learning.form_detail` | Forms and submission entry points | Form schema, deadline and idempotency are backend-owned |

## Operations and owner surfaces

| Screen identifier | Purpose | Visibility |
|---|---|---|
| `core.more` | Secondary navigation | All linked users |
| `management` | Workspace-scoped management projection | Only when canonical capability permits |
| `deployment_confirmation` / `deployment_status` | Server update controls | Telegram private chat plus explicit `deployment.manage`; never Bale |
| `state.empty` / `state.unavailable` / `state.error` / `state.success` | Shared factual state grammar | All providers, with Back/Home recovery |

## Provider rendering rules

1. The application returns one semantic screen; providers do not rebuild business state.
2. Routine short actions pack two per row; primary/destructive/long actions get a full row.
3. Back/Home and pagination stay at the bottom in predictable rows.
4. Callback acknowledgements happen before a business action, while business mutation happens once.
5. Telegram can render a safe rich variant; Bale uses escaped/markdown-safe text. Both retain the same labels, context and action semantics.
6. A provider that cannot meet a protection requirement displays a truthful refusal and does not send the original bytes.

## Future-stage extension rule

New screens must receive a stable semantic identifier, a builder under the owning domain, registered intents, empty/error states and cross-provider contract coverage. Domain IDs may travel only as bounded opaque correlation parameters and must be revalidated by the canonical backend before any read or mutation.
