# FANOOS Prompt 5 feature parity matrix

`Core` means the canonical path needed by later prompts is operational and tested. `Foundation` means safe schema/interface boundaries exist but the complete operator workflow is intentionally deferred. `Deferred` means no runtime implementation is claimed.

| Feature | Legacy outcome preserved | FANOOS status | Validation / remaining work |
| --- | --- | --- | --- |
| Canonical account | One user projection after login | Core | PBKDF2 fixture logs in and rehashes; account lists memberships |
| Authentication/session | Revocation, CSRF, safe failure | Core | Login failure/success, CSRF failure, token authentication |
| OTP/recovery | Expiry/cooldown behavior identified | Deferred | Add provider interface and replay/expiry suite when provider is selected |
| Workspace membership | User can belong to one or many cohorts | Core | Two-workspace account fixture |
| Representative/admin | Scoped manager outcomes | Core | representative allow/deny plus protected role-assignment test |
| Onboarding/tenant selection | Resolve active cohort context | Core | Session-bound workspace selection; UX picker |
| Academic navigation | Curriculum hierarchy and sessions | Core | Data-driven directory/term/course/offering/session projection |
| Schedule/exam dates | Feed/calendar view | Core | Two-tenant bounded date queries |
| External academic connector | Normalized source schedule | Foundation | Adapter/runtime/captcha workflow remains Prompt 7/institution integration work |
| Grades center | Own-grade view | Core | Only published gradebook/results through enrollment |
| Grade import/delete/reset | Managed batch semantics | Foundation | checksum batch table exists; parser/operator flows deferred |
| Announcements | inbox, read state, broadcast | Core | active-member fan-out, isolation, read receipt, outbox/audit |
| Preferences/snooze | Per-user delivery preference | Foundation | canonical table exists; channel worker/UI deferred |
| Forms | lifecycle, version, submit, duplicate protection | Core | create/open/list/validated idempotent submit |
| Form exports/receipts/public guest | response and paid-form utilities | Foundation | export permission/schema present; storage-backed receipt and signed guest policy deferred |
| Shared search | query results filtered by access | Core replacement | tenant index plus result-level reauthorization and leakage test |
| Products/prices/orders | server-owned quote/snapshot | Core | active price and immutable line snapshot |
| Payment callback | verified success/failure, result token | Core with fake provider | success/failure/duplicate callback tests; live adapter deferred |
| Payment history/reconciliation | owner history and manual verify | Core | history and same-finalizer reconciliation test |
| Entitlements | fail-closed grant/revoke | Core | verified-payment grant, admin grant and revoke tests |
| Protected resource authorization | reauthorize before delivery | Core foundation | published + RBAC + entitlement + verified-object checks; worker in Prompt 6 |
| Audit | sensitive operation trail | Core | auth, form, announcement, role, payment and entitlement writes |
| Scoped admin dashboard | owner/manager overview | Core | independent permission gating per section |
| Global dashboard | global owner overview | Core | platform-scoped permission required; representative denied |
| Shared web/bot/app API | signed/canonical backend authority | Core | OpenAPI v1 plus deterministic envelopes |
| New FANOOS UI shell | responsive app navigation | Core shell | new RTL responsive design, server API only; browser E2E after runtime is available |
| Legacy redirects | old links survive cutover | Planned | mapping and coexistence stages documented; activating redirects awaits route inventory/cutover approval |

## No-hardcode proof

The implementation contains no institution, faculty, program, cohort, workspace, academic-year, course, representative identity, gateway merchant key or legacy product route constant. Tests create two independent workspaces from data and use opaque IDs throughout.

## Rewrite justifications

| Rewritten area | Why extraction was unsafe | Compatibility oracle retained |
| --- | --- | --- |
| Tenant/cohort resolver | Source constants and path branches encode one institution/year | membership, representative and visibility outcomes |
| Search storage | File aggregation and fixed URLs cannot enforce tenant authority or stale-index reauthorization | normalized query, compact results, access filtering |
| UI shell | Legacy branding/routes and page forks would recreate product coupling | interaction lessons, responsive navigation and feature visibility |

All other adapted areas follow the behavior identified as `EXTRACT_AND_ADAPT` or `REFACTOR_IN_PLACE` in `01_REUSE_MAP.md`, but use new FANOOS code because literal source copying was not authorized.
