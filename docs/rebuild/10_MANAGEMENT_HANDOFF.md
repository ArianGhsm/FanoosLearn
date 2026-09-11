# FANOOS Stage 10 — Management, Search & Notifications Handoff

## Scope completed

Stage 10 turns the existing scoped management and notification contracts into an operational website surface. No production migration, deployment, restart or live message was performed.

- The existing workspace management surface is reused: authorized members can see only the member, representative, announcement, form and content-production controls projected by their workspace capabilities. The representative role remains workspace-scoped; in FANOOS a workspace is the canonical cohort boundary, so representative operations cannot cross a cohort/workspace route.
- Producer/reviewer controls continue to use the canonical resource lifecycle and version endpoints. Draft, review and publish actions remain server-authorized; platform/deployment controls are not exposed in ordinary management.
- The persisted `notification_recipients` web projection now powers `/notifications`. The inbox is recipient-, workspace- and published-message-scoped, supports an opaque cursor, preserves read state, and records a `notification.read` audit event on the first read.
- `/notification-preferences` reads and updates the existing per-workspace `notification_preferences` row (in-app, email, push and snooze-until). Updates are validated, workspace-bound and audited as `notification.preferences.update`; no fake Telegram/Bale inbox is presented.
- `/search` now re-checks the source record and the current actor before returning a row. Courses, sessions and schedule entries must still be active in the selected workspace; forms must be open; announcements must have a published web recipient for the actor; protected resources use `ProtectedResourceAuthorizer` where available. Results include a human `source_label` while retaining the opaque route contract.
- The V3 operations module now provides the personal inbox, preference form and a scoped search route. Search navigation is registered in the single shell router, uses safe links, and does not render raw source enums or untrusted HTML.

## Changed areas

- `apps/platform/src/Core/WorkspacePlatformService.php`: persisted notification inbox/read/preferences projections, audit hooks and source-aware search visibility checks.
- `apps/platform/src/Core/PlatformFactory.php`: injects the existing protected-resource authorizer into workspace search.
- `apps/platform/src/Http/ApiKernel.php`: notification, read and preference routes.
- `apps/platform/public/assets/ui-v3/operations/operations.js` and `operations.css`: inbox, preference controls, scoped search and human feedback states.
- `apps/platform/public/assets/ui-v3/app/bootstrap.js`, `shell/router.js`, `shell/module.js`, `shell/icons.svg`: one-router search registration and navigation.
- `contracts/openapi/core-v1.yaml`: notification/read/preferences contracts and search pagination description.
- `tests/Integration/CorePlatformTest.php`: workspace/read-state, preference isolation, scoped-search and audit assertions.
- `tests/ux-v3/web_integration_contract_test.js` and `web_student_operations_contract_test.js`: browser contract coverage for inbox, preferences and search.

## Validation and limits

The Node V3 integration, student operations and content-pipeline contracts pass; Python bot integration contracts pass; PHP lint, text encoding, secret/runtime-state and Stage 8 guards pass. The full `php tests/run.php` remains blocked on this workstation before integration execution because the PHP `fileinfo` extension (`finfo`) is unavailable. No production schema or runtime state was changed in this stage.

Higher-scope institution/faculty/platform mutation endpoints were deliberately not invented: only the existing safe workspace-scoped capabilities are surfaced. If those backend contracts are added later, they should receive a separate scoped API and audit test before UI exposure.

## Next-stage handoff

Run the complete PHP integration suite in an environment with `pdo_mysql`, `mbstring` and `fileinfo`, then exercise representative cross-workspace denial, source-leakage cases (including private/entitlement resources), notification read idempotency and preference updates against a migrated test database. Preserve the exact-release, backup and deployment gates before any live rollout.
