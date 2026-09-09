# FANOOS UI V2 — Web Product Audit

Baseline: `74e0d4083a5311cd5d2e527af8b3baf4a46c96f6`
Scope: Website presentation only. No backend, database, bot runtime, updater, deployment, or production-state mutation.

## Executive finding

The baseline Website is secure enough to preserve but is not yet a coherent student product. Its primary authenticated journey is a single dashboard containing eight module buttons (`schedule`, `grades`, `announcements`, `academics`, `resources`, `assessments`, `forms`, `orders`) and one shared result panel. Domain rendering is already substantially safer than the shell: it uses Persian labels, safe DOM text insertion, timezone-aware formatting, CSRF/session handling, workspace switching, and dedicated renderers. UI V2 keeps those invariants and replaces the information architecture.

## Current screen map

| Current surface | Behavior | Product problem | V2 disposition |
| --- | --- | --- | --- |
| Login | Identifier/password + canonical session | Functionally sound, visually isolated from product | Keep authority; redesign layout |
| Dashboard | Eight module cards | No student priority, no Today view, no persistent destination model | Replace with Home/Today |
| Shared result panel | Every module replaces one region | Weak orientation, no bookmarkable product context | Replace with route-aware destinations |
| Academics | Flattened academic navigation | Courses are data rows rather than first-class spaces | Promote to Courses + Course Detail |
| Schedule | Dedicated renderer | No destination modes/navigation | Add Today/Week/Upcoming |
| Resources | Dedicated renderer | Library/filter/course context incomplete | Add library, filters, detail, access state |
| Assessments | Catalog renderer | No clear practice/past-exam structure | Add typed browse + detail scaffold |
| Grades | Published rows | Weak hierarchy | Group by course; avoid invented averages |
| Announcements | Workspace list | Usable but not inbox-like | Add list/detail/read state |
| Forms | List/schema data | Risk of schema-like developer UX | Render recognized fields only; never dump JSON |
| Orders | Order history | Product-ID-oriented create contract exists backend-side | Show history/access only until catalog projection exists |

## Existing API → UI mapping kept

- `/api/v1/account` and `/api/v1/workspaces`: account, memberships, selected workspace.
- `/api/v1/workspaces/{workspace}/academics`: course/session source for Courses.
- `/schedule`: Home next/today + Schedule destination + Course schedule.
- `/grades/me`: Home grade update + Grades + Course grades.
- `/announcements`: Home important/recent communication + Announcements.
- `/resources`: Home recent resources + Library + Course resources.
- `/assessments`: Home upcoming/available assessments + Assessments + Course assessments.
- `/forms`: Forms destination.
- `/orders`: Purchase & Access history.
- `/search`: global workspace search.
- `/admin/dashboard`: permission probe for Management visibility; a successful backend response, never local role state, controls exposure.

## Missing journeys identified

1. A student cannot answer “what matters today?” without opening multiple modules.
2. A course is not a stable context joining sessions, schedule, resources, assessments and grades.
3. Mobile navigation has no product-level frequency model.
4. Workspace switching exists but is not treated as global context with stale-request protection across multi-read pages.
5. Search is a utility rather than a destination.
6. Forms and purchase/access lack product-safe explanations when backend projections are incomplete.
7. Role-specific management is not represented as a contextually gated destination.
8. Loading/error/empty state coverage is domain-level rather than destination-level and Home has no partial-failure strategy.

## Persona map

| Persona | Primary jobs | V2 surface | Authority rule |
| --- | --- | --- | --- |
| Student | Today, courses, schedule, resources, assessments, grades, announcements, forms, access | Full student IA | Reads remain workspace-scoped backend projections |
| Cohort representative | Student jobs + supported workspace operations | Management appears only if scoped dashboard succeeds | No local role flag |
| Workspace/admin-like manager | Supported scoped operations | Management summary of backend-permitted sections | Backend reauthorizes every action |
| Content producer/reviewer | Resource workflow where current endpoint/permission supports it | Management visibility only; no fake authoring controls added | `resource.*` backend rules remain authoritative |
| Platform owner | Platform authority remains backend-specific | No Website Update Server | Deployment control stays Telegram owner-only |

## Course-centric gaps in baseline

- Repeated academic rows contain enough course/session identity to construct a presentation grouping safely.
- Resource and assessment APIs accept `course_id`; course detail can therefore use canonical filtering without duplicating domain logic.
- Grade rows expose course title/code but no full term policy; V2 does not invent term GPA.
- Announcement projection does not expose a course relation suitable for a trustworthy Course Announcements tab.
- Instructor metadata is not consistently present in the academic projection; V2 renders it only when a resource/event projection provides it.

## Navigation and hierarchy problems

- Eight equal-weight cards flatten high-frequency and low-frequency jobs.
- Account/workspace context competes with domain navigation.
- No “More” layer exists for lower-frequency destinations.
- No persistent active state or `aria-current` destination semantics across a real route model.
- Deep course context is absent.

## Responsive/accessibility findings

Baseline already contains useful focus/loading conventions, but the product model needs: desktop persistent navigation, mobile bottom navigation, drawer semantics, safe-area spacing, table-to-card adaptation, route focus stability, skip link, visible focus, reduced motion, and long Persian/LTR token resilience. V2 implements these without introducing remote font/icon dependencies.

## Raw/developer UX remnants removed or contained

- No manual product ID input is exposed.
- No UUID is used as a visible course/resource route label.
- No schema JSON is dumped for forms.
- No provider/storage/path data is shown.
- No raw backend message becomes the default user error.
- No client-side grade average/GPA, entitlement, payment success, score, or permission truth is created.

## What should be kept

- Existing session + CSRF flow.
- Canonical account-selected workspace.
- Request serial invalidation before workspace mutation.
- Safe `textContent`/DOM construction.
- Persian normalization, number/money/date formatters and workspace timezone handling in `domain-ux.js`.
- Existing eight `data-view` compatibility hooks required by regression tests, mapped to V2 destinations rather than the old grid.
- Local SVG icon strategy and existing FANOOS visual identity.
