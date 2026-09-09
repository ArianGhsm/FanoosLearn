# FANOOS UI V2 — Final Acceptance

This document is the source-side acceptance record for the integrated FANOOS UI V2 release candidate. Production acceptance still requires the supervised deployment/live-validation procedure in the deployment handoff and production cutover runbooks.

## Final information architecture

FANOOS is one canonical academic/content product with three presentation channels: Website, Telegram and Bale. Canonical identity, workspace membership, permissions, course identity, schedule facts, grades, announcements, resources, commerce/payment state, entitlements and operational authority remain backend-owned. Channel presentation may differ, but business truth may not.

Primary product destinations are:

- Home / Today
- Courses and Course Detail
- Schedule
- Resources / Learning
- Assessments
- Grades
- Announcements
- Notifications where a canonical projection exists
- Forms
- Purchase & Access
- Account / Workspaces
- Permission-aware Management
- Update Server only in the private Telegram owner/operator surface

## Website acceptance

Accepted source behavior:

- Persian-first RTL product shell with persistent desktop navigation and mobile bottom navigation/drawer.
- Canonical active-workspace selection; stale reads from a previous workspace are suppressed after workspace mutation.
- Course-centric navigation and course detail journeys.
- Workspace-timezone schedule rendering and backend date-boundary resolution.
- Resources, secure browser delivery redemption, assessments, grades, announcements, forms, orders/access, search and account surfaces use canonical APIs rather than local business truth.
- Partial Home failures remain section-local rather than replacing the entire page.
- API-derived content is rendered with safe DOM/text construction; raw API dumps, private storage paths and provider identifiers are not normal UI copy.
- Management visibility is only a presentation consequence of an authorized backend projection; hidden UI is never treated as authorization.
- Website exposes no deployment/update control.

Source test evidence includes the existing Web UX suite plus `tests/ux-v2/web/static_contract_test.py`, `tests/ux-v2/web/product_ui_test.js`, and `tests/ux-v2/web/product_dom_safety_test.js` in CI.

## Telegram acceptance

Accepted source behavior:

- Structured Home / Courses / Schedule / Notifications / More product spine.
- Canonical course list and course detail navigation, including opaque subject-bound pagination over fresh backend course projection.
- Course-sensitive schedule/resources/grades journeys are derived from canonical authorized projections.
- Resource detail precedes protected delivery; delivery authorization, consume/send and receipt semantics remain backend/runtime controlled.
- Purchase & Access no longer exposes raw product UUID entry as normal UX.
- Account unlink requires explicit confirmation.
- Rich presentation is additive to a complete text fallback; presentation failure does not replay business operations.
- Callback acknowledgement/order and restart-safe route correlation remain covered by deterministic bot tests.
- Update Server is visible only in private Telegram context to the same linked canonical user who is allowed `deployment.manage`; arbitrary branch/ref/SHA/shell input is not part of the user surface.

## Bale acceptance

Accepted source behavior:

- Same canonical product semantics as Telegram with Bale-compatible text/keyboards and no Telegram-only payload assumptions.
- Same canonical workspace/course/schedule/grade/announcement/resource/access truth.
- No Update Server or deployment-management surface.
- Protected content remains fail-closed when the provider cannot satisfy required protection capability; the implementation does not downgrade to an unprotected original.
- Channel-native presentation differences are allowed, but business outcomes and authorization decisions must match the canonical backend.

## Course-centric journeys

A canonical course is identified by backend `course_id`; titles/codes are presentation metadata. Website, Telegram and Bale may remember a selected course only as navigation state. Each course-sensitive operation reauthorizes against the selected workspace and canonical backend.

Accepted journeys include:

- Courses → Course Detail → Schedule/Sessions
- Courses → Course Detail → Resources
- Courses → Course Detail → Grades
- Courses → Course Detail → Assessments where the channel has a supported projection
- Course-scoped presentation filtering only over canonical projected data; no channel-local course datastore

## Cross-channel parity

Parity requires the same canonical concept, source, permission and business outcome across channels. Verified source contracts cover workspace, Home/Today, course, schedule, grades, announcements, resources, protected delivery policy, assessments, orders/payment/entitlement semantics, account identity and role management.

Intentional asymmetry is accepted only when documented:

- Website provides richer assessment interaction; bots may hand off safely rather than score locally.
- Website search is a presentation surface not promoted as first-class bot navigation.
- Telegram alone may expose the private, permission-gated Update Server operational surface.
- Bale may fail closed for provider protection limitations.

## Roles and authority

Accepted invariants:

- Active membership and tenant/workspace authorization remain server-side.
- RBAC remains server-side; ordinary users cannot gain management capability through route/UI manipulation.
- Canonical human identity is IAM/messaging-link based; display name or username is not identity proof.
- Payment status and entitlement status are distinct backend facts; provider/browser success alone never grants access.
- Owner/operator deployment capability is not replicated into Website or Bale.

## Accessibility and responsive behavior

Source acceptance includes:

- RTL-first layout and local assets.
- Keyboard-focus treatment and skip-navigation support.
- Semantic dialogs/drawers/live regions where used.
- Reduced-motion handling.
- Responsive contracts covering narrow mobile through desktop in source tests.

Live visual acceptance at approximately 320, 390, 768 and desktop widths remains a deployment-time/browser validation requirement. It must be reported honestly if browser tooling is unavailable.

## Security acceptance

No Critical/High source-side security blocker was identified by the integration self-review and green CI candidate at the time this document was added. Acceptance relies on preservation of these invariants:

- tenant/workspace isolation and inactive/revoked membership rejection;
- server-side RBAC and identity linking;
- service authentication/HMAC/replay controls remain canonical;
- payment server authority and independent entitlement checks;
- protected-resource authorization is rechecked before delivery/download;
- secure browser download redemption does not expose object-storage keys as normal client authority;
- protected originals are not used as unsafe fallback;
- Telegram Update Server remains private + `deployment.manage` gated;
- Bale/Website have no deployment surface;
- secrets and production data remain outside Git.

## Test evidence

The integration candidate must have exact-head deterministic CI SUCCESS before merge. Required green lanes are:

- PHP 8.2 static/unit/repository safety
- PHP 8.4 static/unit/repository safety
- MySQL migration/tenant-isolation integration
- Web UX and UI V2 product contracts
- Python Telegram/Bale/worker deterministic tests

PR #21 head `3f1d4033965128a84b44b2b42f20ea11927eb6b6` previously passed CI run #94 before this final documentation commit. Because adding this file changes the PR head, CI on the new exact head is required again before merge; this prior run is historical evidence only.

## Deferred gaps

The following are accepted deferred product/backend gaps and are not faked by UI V2:

- durable personal notification inbox/history projection;
- browsable canonical product catalog/access center;
- bot-native assessment attempt/result projection;
- native server-side course filters where presentation filtering is currently bounded and authorized;
- course-scoped announcement binding;
- self entitlement/access list projection;
- form submission history/results;
- authoritative GPA/weighted grade summary;
- complete instructor/offering presentation metadata;
- full Website linked-channel management projection;
- resume of an in-progress assessment after full browser reload.

These are non-blocking only while the current safe fallbacks remain intact.

## Runtime validations required before production acceptance

Source acceptance does not imply live acceptance. The deployment task must verify, on FanoosLearn Server only:

- exact merged `main` SHA fetched read-only and activated immutably;
- verified backup and required restore/migration preflight;
- Website HTTPS/health/static assets/login/workspace/core routes and permission-aware management;
- Telegram service/polling/provider identity/core journeys/callback ACK/restart/protected delivery/private owner Update Server;
- Bale service/provider/core journeys/restart/protection fail-closed behavior;
- same canonical user/workspace truth across Website, Telegram and Bale;
- bounded logs with no Critical/High runtime regression;
- final SHA consistency across Website, Telegram, Bale and workers;
- no production source hot-patch and no GitHub write credential on the server.

Production status is COMPLETE only after these runtime gates pass on the exact merged main SHA.