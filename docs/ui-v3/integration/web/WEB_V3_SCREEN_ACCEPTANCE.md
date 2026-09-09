# FANOOS Website V3 — Screen Acceptance Matrix

Design Lock: `FANOOS-UX-2026.09-R1`

This matrix records source-level acceptance. Live rendering, assistive-technology behavior and provider/runtime smoke remain required after deployment.

| Area / state | Route | Canonical source | V3 UI owner | Empty / error behavior | Mobile behavior | Security invariant |
| --- | --- | --- | --- | --- | --- | --- |
| Auth — signed out | entry | `/api/v1/auth/login` | web-02 Shell | V3 auth entry only; no legacy shell flash | single-column login | credentials sent only to canonical auth API |
| Auth — login pending | entry | login mutation | web-02 Shell | fields disabled, polite pending message | controls remain >=44px | no client auth truth |
| Auth — login error | entry | canonical error | web-02 Shell | bounded Persian error; password cleared | inline error | raw provider/error payload not rendered |
| Auth — session check error | entry | `GET /api/v1/account` | web-02 Shell | retry/login choice; transient failure is not called logout | full-width state | only 401 establishes expired-session UI |
| Workspace — active | all workspace routes | account projection | web-02 Shell | active workspace visible | persistent top/bottom context | server selected workspace is authority |
| Workspace — switch | all workspace routes | `POST /workspaces/select` then account refresh | Shell + integration manager | old route aborted/removed before mutation; failure restores prior canonical selection | sheet selection | stale old-workspace response cannot render after abort |
| Workspace — select | workspace gate | account memberships | web-02 Shell | explicit choice; no auto-first selection | stacked choices | membership list comes from server |
| Workspace — none selected | workspace-required route | account projection | web-02 Shell | selection destination when memberships exist | stacked | no silent selection |
| Workspace — zero memberships | workspace-required route | account projection | web-02 Shell | useful account/help state | stacked sections | no fabricated workspace |
| Home — full | `/home` | schedule + assessments + announcements + resources + grades | web-03 via adapter | prioritized next/today/attention/recent composition | priority stack | no fabricated ranking or aggregates |
| Home — no schedule | `/home` | schedule | web-03 | honest no-schedule hero/timeline; other parts remain | stacked | no inferred event |
| Home — partial error | `/home` | independent canonical reads | web-03 | failed section degrades locally, healthy sections remain | same priority | no stale workspace render |
| Home — no data | `/home` | all five projections | web-03 | calm first-use state | stacked | no invented content |
| Courses — list | `/courses` | academics | web-04 | empty/error state | responsive course list | opaque IDs not presented as route labels |
| Courses — filtered | `/courses?term=&q=` | academics | web-04 | no-match state | controls wrap/stack | presentation filter only |
| Course — detail | `/courses/{course_code}` | academics | web-04 | unknown code is explicit missing state | context/tabs scroll safely | human course code in URL, not UUID |
| Course — sessions | `/courses/{course_code}?tab=sessions` | academics sessions | web-04 | no sessions state | stacked rows | no guessed schedule data |
| Course — unknown | `/courses/{course_code}` | academics | web-04 | not-found/missing state | full-width state | no raw internal identifier disclosure |
| Schedule — today | `/schedule`, `/schedule/today` | schedule projection | web-05 | date-specific empty state | agenda | workspace timezone only |
| Schedule — week | `/schedule/week` | schedule projection | web-05 | seven-day view with empty days | day strip + agenda | Saturday–Friday worker rule preserved |
| Schedule — upcoming | `/schedule/upcoming` | schedule projection | web-05 | grouped upcoming or empty | stacked groups | no device-timezone authority |
| Schedule — event detail | schedule routes | event row | web-05 | modal detail; close/return focus | modal | only projected fields shown |
| Schedule — empty | schedule routes | schedule projection | web-05 | human empty/filter message | full-width state | no fabricated event |
| Learning — library | `/resources` | resources + academics | web-06 | newest library or empty | stacked library | server auth remains authority |
| Learning — filtered/search | `/resources?...` | server-backed filters | web-06 | no-match state, one-char search not sent | mobile filter sheet | no shadow search index |
| Learning — detail | `/resources` internal detail | authorized resource detail | web-06 | unavailable/denied handled explicitly | detail stack/sheet | raw storage/object path never displayed |
| Protected — preparing | resource detail | delivery issue/consume | web-06 + integration bridge | pending state | same | capability token local only |
| Protected — ready | resource detail | consume/download result | web-06 + integration bridge | structured text dialog or redeemed bytes | modal/download | no persistent capability URL |
| Protected — expired | resource detail | canonical token error | web-06 | restart action | same | expired token not reused |
| Protected — denied | resource detail | canonical 403 | web-06 | denied state | same | UI never grants entitlement |
| Assessments — list | `/assessments` | assessments + academics + question-bank resources | web-07 | empty/partial filter states | stacked cards | no answer key |
| Assessment — detail | `/assessments` internal state | assessment projection | web-07 | server-defined availability/access | stacked | browser does not infer permission |
| Assessment — attempt | `/assessments` internal state | server-created attempt | web-07 | question-by-question UI | single-question flow | scoring remains server-only |
| Assessment — revision conflict | attempt | canonical revision error | web-07 | conflict warning blocks unsafe overwrite | inline status | stale revision not silently submitted |
| Assessment — submit | attempt | submit mutation | web-07 | confirmation before final submit | modal/stack | server creates result |
| Assessment — review/result | attempt | review projection | web-07 | canonical result only | stacked | no browser scoring |
| Grades — list | `/grades` | `grades/me` | web-07 | grouped published rows | stacked/mobile adaptation | no fabricated GPA/average |
| Grades — empty | `/grades` | `grades/me` | web-07 | explicit empty | stacked | no zero-as-missing invention |
| Grades — missing score/max | `/grades` | grade row | web-07 | displays only available values | stacked | no denominator fabrication |
| Announcements — list | `/announcements` | announcements | web-08 | empty/permission/retry states | stacked list | announcement != personal notification |
| Announcement — detail | `/announcements` internal state | authorized row | web-08 | safe bounded content/link | stacked | URL normalized; raw data JSON not rendered |
| Announcements — empty | `/announcements` | announcements | web-08 | published-message empty state | full-width | no fake announcement |
| Personal notifications — gap | `/notifications` | no durable public inbox exists | web-08 | explanatory state only | full-width | no fabricated inbox/history |
| Forms — list | `/forms` | forms | web-08 | empty/permission/error | stacked | schema rendered through supported typed fields only |
| Forms — detail/submission | `/forms` internal state | form + submission API | web-08 | validation, pending, duplicate/success/error states | stacked form | idempotency/canonical response preserved |
| Purchase/access — list | `/orders` | orders | web-08 | existing orders or honest no-catalog state | stacked orders | no raw product ID input |
| Purchase/access — payment pending/paid/failed | `/orders` | order/payment projection | web-08 | payment status timeline | stacked | payment status is server truth |
| Purchase/access — active/expired/revoked/unknown | `/orders` | explicit entitlement/access projection when available | web-08 | unknown remains unknown | stacked | paid never implies entitlement |
| Account — profile | `/account` | account | web-02 Shell | bounded profile | stacked | technical IDs omitted |
| Account — memberships | `/account` | account memberships | web-02 Shell | zero-membership state | stacked | canonical membership only |
| Account — logout | `/account` | logout mutation | web-02 Shell | success/expired/error remain distinct | same | uncertain failure does not masquerade as success |
| Management — authorized | `/management` | admin dashboard + permitted reads | web-08 | available sections only | stacked cards/table adaptation | granular mutation capabilities fail closed |
| Management — unauthorized/absent | `/management` | admin dashboard | Shell + web-08 | Shell redirects unavailable entry or worker shows denied | full-width | client role inference forbidden |

## Cross-screen source requirements

- one canonical `.f3-root` and one V3 module bootstrap;
- one navigation authority (Shell);
- route mount abort before unmount/replacement;
- no worker-owned persistent workspace/domain truth;
- one shared Foundation token system;
- `textContent`/DOM node construction for API/user strings;
- local assets only;
- focus-visible, reduced motion, RTL and bidi isolation;
- embedded Course slots cannot create additional page-level H1s;
- 320px minimum viewport, mobile safe areas and long Persian labels must remain non-overflowing by source rules.
