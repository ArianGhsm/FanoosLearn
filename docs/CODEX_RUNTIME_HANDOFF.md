# Codex Runtime / Deployment Handoff

## Scope

The preferred FANOOS operating model is GitHub-first development plus a **one-time supervised Codex runtime bootstrap**. After bootstrap, routine updates are control-plane driven from canonical GitHub `main`; Codex is not required as the normal release operator.

Codex may later be used for exceptional runtime/environment troubleshooting. It must not redesign architecture, silently change shared contracts, perform broad refactors or implement unrelated product features during bootstrap/troubleshooting.

Canonical source repository:

`ArianGhsm/FanoosLearn`

Full bootstrap contract:

`docs/fanoos-migration/07_CODEX_ONE_TIME_BOOTSTRAP.md`

Update-control contract:

`docs/fanoos-migration/07_UPDATE_CONTROL_PLANE.md`

## MODE A — One-time supervised bootstrap

Codex should, on the target host:
1. verify repository identity and accepted exact source SHA;
2. inspect real OS/PHP/Python/MySQL/disk/service/storage capabilities rather than assume them;
3. install/verify approved dependencies;
4. create least-privilege application/updater/worker service identities as required;
5. create immutable release/current-pointer layout and durable config/log/backup/object roots outside releases;
6. provision NEW FANOOS DB/storage access without reusing legacy runtime state;
7. inject FANOOS secrets outside Git;
8. register Stage 7 service identities/keys and deployment target using tracked tooling;
9. bootstrap the first owner (user, platform-scoped role, protected messaging link) with `scripts/ops/bootstrap-owner.php`, since nothing else can grant the initial `deployment.manage` permission;
10. install the fixed updater service/timer from tracked templates;
11. scope the private-repository deploy credential to the updater service and FANOOS repository;
12. configure fixed restart/health/smoke hooks for services actually present;
13. configure production backups and run independent backup verification;
14. perform an isolated restore rehearsal;
15. run the initial migration/preflight/test suite;
16. perform the first supervised control-plane exact-SHA activation and application-pointer rollback rehearsal;
17. return a redacted runtime inventory and evidence report.

Any required NEW FANOOS server/domain/database/storage/service credentials are provided by the user only at runtime and remain outside Git.

## Normal operation after bootstrap

Routine release path is:

```text
reviewed/accepted GitHub main
→ owner Update Server action
→ signed internal API
→ canonical linked user + deployment.manage
→ durable update request
→ fixed privileged updater
→ canonical origin/main exact SHA + exact-SHA CI
→ migration preflight + verified backup + tests
→ immutable activation + fixed restart/health hooks
→ durable SUCCEEDED / FAILED / ROLLED_BACK
```

Codex must not be inserted into this routine path merely to run a deployment shell command.

The bot/web request never supplies arbitrary command, path, remote, branch, ref or candidate SHA.

## MODE B — Exceptional runtime/environment fix

Codex may diagnose and fix a small environment-specific defect such as:
- missing OS package or PHP/Python extension;
- systemd/cPanel/runtime path mismatch;
- filesystem permission/ownership;
- environment wiring;
- executable path;
- fixed service configuration issue;
- backup/health hook wiring.

If the fix is representable by source/declarative config/docs, it must be committed to a dedicated branch and returned to normal ChatGPT review/CI before the corrected SHA becomes the baseline.

Do not leave a reproducible production configuration fix only as an undocumented server edit.

## MODE C — Source defect escalation

If the failure requires any of the following, stop ad-hoc source modification and return evidence to the GitHub development workflow:
- architecture redesign;
- shared API/DTO/contract change;
- schema/domain redesign;
- broad refactor;
- multi-module business logic change;
- authorization/payment/entitlement redesign;
- deployment-control policy change;
- feature redesign.

Report, with secret values removed:
- exact attempted operation/command;
- relevant error/log;
- OS/runtime/service/version context;
- failing test/health check;
- expected vs observed behavior;
- likely module/file when identifiable;
- whether production state was mutated;
- backup/rollback/restore state.

## Safety rules

- Never print or commit secret values.
- Never reuse legacy project tokens, env files, databases, queues, storage or service directories for FANOOS.
- Never overwrite production data from the Git checkout or a developer backup.
- Git is code source of truth; production DB/object storage are data source of truth.
- Backups and restore verification remain separate from Git.
- Do not bypass failed exact-SHA CI, backup verification, migration preflight, readiness, tenant-isolation or security gates.
- Application rollback does not reverse forward database migrations; schema compatibility must be known first.
- Do not add a raw-shell or arbitrary-ref interface to the owner bot/control plane.
- Do not give the web/Bot process updater deploy credentials.

## Expected one-time bootstrap report

Return:
- repository and exact accepted/deployed SHA;
- environment/runtime versions inspected;
- service/user and filesystem layout (without secrets);
- dependency result;
- DB/migration result;
- service identity/deployment-target registration result (IDs/names only);
- backup verification + isolated restore result;
- updater service/timer result;
- tests/smoke/health result;
- first control-plane deployment result;
- pointer rollback rehearsal result;
- any runtime fixes made and source branch/commit where applicable;
- remaining risks;
- final status: `COMPLETE`, `PARTIAL` or `BLOCKED`.

Do not declare bootstrap or deployment complete without observed live verification.
