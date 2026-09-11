# FANOOS UI V2 — Role Surface Matrix

Authorization comes from canonical RBAC (`rbac_role_templates` + scoped assignments + permission checks). Hiding a control is presentation hygiene, never authorization.

Repository role templates currently include: `student`, `cohort-representative`, `workspace-admin`, `institution-admin`, `faculty-admin`, `program-admin`, `content-author`, `content-manager`, `content-reviewer`, `finance-manager`, `platform-super-admin`, and `platform-deployment-operator`.

| Role | Website surface | Telegram surface | Bale surface | Permission source / backend action | Explicitly prohibited / absent |
| --- | --- | --- | --- | --- | --- |
| Student | Home, Courses, Schedule, authorized Resources, Assessments, own Grades, Announcements, Forms, Purchase/Access, Account | corresponding concise student destinations; assessment/payment complexity may hand off to Web | same semantics within Bale capability limits | `academic.view`, `resource.view`, `exam.take`, `grade.view_self`, `commerce.purchase`, `notification.receive`, later seed additions such as form submission | no membership/grade/catalog/audit/deployment administration |
| Cohort representative | student surfaces plus only management data/actions whose endpoint permission is actually granted | shared student UX; no invented admin bot menu | same | repository role permissions include `membership.view`, resource creation and notification broadcast; mutations still enforce their own permission | cannot receive workspace-admin-only actions merely because the UI is hidden/shown |
| Workspace admin | scoped management surfaces supported by public canonical endpoints, plus normal workspace content | no generic privileged bot control unless a bot-safe canonical action exists | same | workspace-scoped permissions such as membership/academic/resource/grade/form/broadcast/audit according to seeded permission set | no platform deployment control unless separately assigned `deployment.manage` |
| Institution admin | hierarchy-scoped canonical administration where the current public API exposes it | no fabricated hierarchy-admin bot UX | same | institution scope assignment + canonical access gate | no workspace mutation without a backend action authorizing that scope |
| Faculty admin | hierarchy-scoped canonical administration where supported | none beyond supported bot contracts | same | faculty scope assignment | no deployment control by role name alone |
| Program admin | hierarchy-scoped canonical administration where supported | none beyond supported bot contracts | same | program scope assignment | no deployment control by role name alone |
| Content manager | Resources/content production and canonical review/publish operations exposed by Website | ordinary resource consumer presentation unless an internal management contract is added | same | `resource.create`, `resource.review`, `resource.publish` etc. | no local bot content authority; no raw storage operations |
| Content reviewer | Review surfaces only where canonical review endpoints support them | no fake bot reviewer queue | same | `resource.review` and exam-review additions where seeded | cannot publish/grant entitlement unless separately permitted |
| Finance manager | canonical order/payment reconciliation/entitlement/admin data where a user-facing management endpoint exists | normal Purchase/Access status; no provider-code/raw-ID primary UX | same | `commerce.manage_catalog`, `payment.reconcile`, `entitlement.grant`, `audit.view` | no client-side payment verification or entitlement grant |
| Platform super admin | platform dashboard only where public canonical endpoint supports it; workspace actions still scoped/reauthorized | no automatic Update Server visibility from role name alone; actual `deployment.manage` permission is checked | no Update Server | platform scope; all generic RBAC permissions plus seeded deployment permission | Website does not acquire deployment execution controls in UI V2 |
| Platform deployment operator | no deployment control in Website UI V2 | **private Telegram only**: Update Server overview/request/status after linked canonical identity and backend `deployment.manage` authorization | **none** | `deployment.manage`; deployment control plane reauthorizes actor and Telegram channel | group/public Telegram, Bale, Website and non-owner/operator users cannot request deployment |

## Management presentation rule

The Website management destination is a projection of canonical capabilities, not a role-name switch. The backend computes `management_available` from management-class permissions such as membership/academic/content/form/grade/commerce/broadcast/audit capabilities. A `200` response from the read-only dashboard is not enough: Website navigation requires `management_available=true`, while dashboard sections remain independently permission-filtered. Every mutation still performs its authoritative backend permission check.

## Update Server invariant

`deployment.manage` is a critical platform permission. Telegram adapter + private chat + canonical messaging link + backend permission are all required. Bale has no Update Server surface. Website UI V2 intentionally contains no updater trigger, systemd action or server shell control.
