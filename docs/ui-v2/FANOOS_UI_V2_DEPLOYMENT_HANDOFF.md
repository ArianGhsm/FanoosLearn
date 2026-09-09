# FANOOS UI V2 — Deployment Handoff

This handoff describes the production-impacting delta for the integrated UI V2 candidate. It contains no secrets. The release SHA is assigned only after PR #21 is squash-merged into `main` and the exact merged-main CI succeeds.

## Release identity

- Canonical repository: `ArianGhsm/FanoosLearn`
- Integration PR: #21
- Integration branch: `ui-v2/product-integration`
- Historical integration head before final handoff docs: `3f1d4033965128a84b44b2b42f20ea11927eb6b6`
- Final deployable release: `UI_V2_RELEASE_SHA=<assign exact merged main SHA after merge>`
- Deployment target: FanoosLearn Server only

Production must never deploy a branch head. `origin/main` on the server must equal the accepted release SHA before activation.

## Runtime/code delta

UI V2 changes affect these runtime areas:

- Website shell and UI assets under `apps/platform/public/**`.
- Platform HTTP/core/content wiring required by the integrated UI, including workspace-timezone schedule resolution, canonical bot course projection and secure same-origin object download redemption.
- Telegram and Bale runtime entrypoints selecting the integrated shared bot application.
- Shared Python bot presentation/application/state/localization layers.
- Deterministic Web/bot/parity test suites and CI coverage.

No server-side source edit is part of deployment. GitHub remains canonical code truth.

## Database and migration delta

- UI V2 integration requires **no new database migration**.
- Historical migration checksums remain immutable; any checksum drift is a blocker.
- Production SQL remains canonical relational data truth and must not be reset or replaced from repository/dev data.
- The schedule behavior change resolves date-only query bounds using the canonical workspace timezone; it does not alter stored schedule data.

## Configuration and secrets

No new secret is introduced by UI V2.

The secure browser download path reuses existing runtime contracts when configured:

- object storage root
- existing download signing key
- existing delivery signing key where protected delivery is enabled

Deployment must inspect existing `/etc/fanoos` runtime configuration first and must not print secret values. A missing required runtime value is a preflight blocker; do not create or move credentials casually.

## Assets and cache implications

Website asset paths change/add JavaScript, CSS and SVG files under the immutable release. After activation:

- verify all referenced JS/CSS/SVG assets return successfully;
- verify no stale HTML references old/missing asset paths;
- clear/reload only the canonical application/web cache mechanism if the runbook requires it;
- do not mutate historical release contents to repair cache symptoms;
- verify PHP/web integration after release switch.

## Services affected

The synchronized release must place the same exact SHA behind all source-based FANOOS services:

- Website/platform runtime
- Telegram bot
- Bale bot
- notification worker(s)
- protected-media worker(s)
- updater/deployment status metadata that records the active release

Restart only the fixed canonical FANOOS services defined by the repository/runtime runbooks. Do not introduce mixed-version production.

## Pre-deploy gate

Before mutation on FanoosLearn Server:

1. Read the mandatory repository/runbook documents from the accepted release.
2. Inventory the current active SHA, services, storage/data roots, migration status and bounded recent errors without secrets/personal payloads.
3. Using only `fanoos-updater` and `/etc/fanoos/deploy/ssh_config`, verify read-only GitHub access and `origin/main == UI_V2_RELEASE_SHA`.
4. Fetch read-only, verify the exact commit object and repository identity.
5. Compare current active SHA to candidate for migrations/config/runtime/service delta.
6. Pass SQL/object backup, manifest/checksum verification and any restore rehearsal required by the canonical runbook.
7. Block on migration checksum drift, failed backup/restore verification, missing required runtime config, wrong remote SHA or any Critical/High blocker.

The server must not receive GitHub source-write credentials and must not push source.

## Preferred activation mechanism

Use the existing canonical FANOOS updater/deploy control plane described by repository runbooks. For this supervised release, the accepted candidate is fixed by canonical `origin/main`; arbitrary branch/ref/SHA input from a bot or operator surface must not be used.

Expected flow:

- read/fetch accepted main SHA;
- preflight and backup;
- run required deterministic tests/migration checks;
- create immutable release;
- apply forward migration only if a verified pending migration exists;
- atomically switch `/srv/fanoos/current`;
- restart the fixed synchronized services;
- health-check and persist deployment status.

## Website live smoke matrix

Verify at minimum:

| Area | Required result |
| --- | --- |
| HTTPS / health / readiness | healthy, no 5xx |
| PHP-FPM / web integration | active and serving accepted release |
| Static assets | JS/CSS/SVG load without 404/module failure |
| Authentication | login/logout work without leaking backend errors |
| Workspace | canonical selection and stale-read protection |
| Home | partial composition works |
| Courses / Course Detail | canonical authorized course truth |
| Schedule | correct workspace-timezone behavior |
| Resources | metadata/detail and secure browser delivery where entitled |
| Assessments | backend-owned start/save/submit/review behavior |
| Grades | published self grades only; no fabricated aggregate |
| Announcements / Forms | authorized canonical data/actions |
| Purchase & Access | backend order/payment/entitlement truth |
| Search | authorized results only |
| Account / Management | own account; management permission-aware |

If browser tooling is unavailable, set `LIVE_BROWSER_VISUAL_VALIDATION_REQUIRED=true`; do not report responsive visual success without observing it.

## Telegram live smoke matrix

Safely verify:

- service active, no crash loop;
- one polling consumer and no webhook conflict;
- provider identity/getMe;
- offset progression;
- `/start`, Home, Courses, Course detail, Schedule, Grades, Announcements, Resources, Purchase & Access, Workspace, Account;
- rich UI and complete plain fallback;
- callback ACK and restart recovery;
- notification delivery using a safe fixture;
- protected entitlement recheck, personalized derivative/protection where required, no duplicate provider send, receipt behavior;
- owner Update Server only in private Telegram for the linked canonical owner/operator with `deployment.manage`;
- no arbitrary branch/ref/SHA/shell deployment input.

Do not send test messages to unrelated users.

## Bale live smoke matrix

Safely verify:

- service active, provider identity, no crash loop;
- `/start`, Home, Courses, Course detail, Schedule, Grades, Announcements, Resources, Purchase & Access, Workspace, Account;
- supported callbacks/keyboards/edit behavior;
- notification and restart recovery;
- no Telegram-only payload assumptions;
- no Update Server surface;
- forward/protected content remains fail-closed when required provider capability is unavailable.

## Cross-channel parity smoke

Using one safe canonical user/workspace, compare the business truth seen by Website, Telegram and Bale for:

- user identity
- memberships / active workspace
- courses
- schedule
- grades
- announcements
- resources
- assessment metadata
- orders
- payment state
- entitlement/access state
- notifications where a canonical projection exists

Presentation differences are allowed. Canonical facts and authorization outcomes are not.

## Security regression smoke

After activation and after every redeploy in a live-fix cycle, verify:

- tenant/workspace isolation;
- inactive/revoked membership rejection;
- RBAC and foreign-workspace denial;
- identity/linking authority;
- service HMAC/replay controls where applicable;
- payment server authority;
- independent entitlement checks;
- protected delivery/media behavior and receipts;
- Telegram private owner Update Server gate;
- Website/Bale deployment absence;
- updater fixed to canonical repository/main and read-only server GitHub path;
- secrets remain outside Git and out of logs/reports.

A Critical/High security regression requires rollback/block.

## Rollback notes

- Preserve the previous immutable release and current canonical production data.
- If the candidate fails before irreversible data change, atomically reactivate the last known-good immutable release and restart the same fixed service set according to the runbook.
- Do not edit historical release source during rollback.
- Do not perform blind SQL rollback. UI V2 has no new migration; if a later live-fix release introduces a forward migration, follow its explicit migration/rollback classification and backup/restore procedure.
- Do not delete production objects, SQL data, bot runtime state or backups to force a rollback.
- Capture sanitized evidence for any source bug, fix it through GitHub branch/PR/CI/merge, then read/fetch and redeploy the exact new main SHA.

## Source-bug loop

A production source bug must follow:

server evidence → GitHub branch `fix/ui-v2-live-<slug>` → minimal fix + regression test → PR → deterministic CI → merge → exact new `main` SHA → server read-only fetch → synchronized redeploy.

Never patch `/srv/fanoos/current` or `/srv/fanoos/releases/<sha>` as source authority. Maximum supervised source-fix redeploy cycles for this release task: 3.

## Final release acceptance record

After merge and live stabilization, record:

- `FINAL_MAIN_SHA`
- `FINAL_ACTIVE_SHA`
- `FINAL_WEB_SHA`
- `FINAL_TELEGRAM_SHA`
- `FINAL_BALE_SHA`
- `FINAL_WORKERS_SHA`

All must be identical for COMPLETE status.

Also record:

`SERVER_GITHUB_WRITE_ACCESS_REQUIRED=false`

Production acceptance is COMPLETE only after source closure, final main CI, read-only server fetch, backup/preflight, synchronized exact-SHA activation, Website/Telegram/Bale/workers smoke/parity/security gates, absence of Critical/High bugs and confirmation that no production-only source hot-patch exists.