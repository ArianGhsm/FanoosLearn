# Codex Runtime / Deployment Handoff

## Scope

Codex is the runtime, environment and deployment operator for FANOOS after ChatGPT integration is complete.

Codex must not redesign architecture, silently change shared contracts, perform broad refactors or implement unrelated product features during deployment verification.

## Required handoff input

The integration stage must provide:

```text
Repository: ArianGhsm/FanoosLearn
Release candidate SHA: <exact 40-character SHA>
Environment: <staging|production>
Expected migration set: <if any>
Expected deploy/runbook: docs/fanoos-migration/04_DEPLOY_RUNBOOK.md
```

Any required NEW FANOOS server, domain, database/storage or service credentials are provided by the user only at runtime/deployment time and remain outside Git.

## MODE A — Verification

Codex should:
1. verify repository full name and exact SHA;
2. verify the deploy checkout is clean and corresponds to that SHA;
3. inspect real OS/PHP/Python/MySQL/service/storage capabilities rather than assume them;
4. install/verify dependencies required by the approved source;
5. run repository/static/integration checks applicable to the environment;
6. perform migration dry-run/ledger verification before any approved migration;
7. run runtime, integration, smoke and health checks;
8. inspect relevant logs and resource state;
9. verify backup prerequisites before any risky production mutation;
10. deploy only through the canonical runbook and verify live state afterward.

## MODE B — Small runtime/environment fix

Codex may directly diagnose and fix a small, obvious environment-specific defect such as:
- missing OS package or extension;
- service/systemd/cPanel path mismatch;
- filesystem permission/ownership;
- environment wiring;
- executable path;
- runtime-specific migration command;
- safe service configuration issue.

If the fix is represented by source/declarative config/docs, it must be committed to a dedicated branch and returned to ChatGPT for review/CI before the corrected SHA is considered deployable.

Do not leave a reproducible production configuration fix only as an undocumented server edit.

## MODE C — Source defect escalation

If the failure requires any of the following, stop source modification and return evidence to ChatGPT:
- architecture redesign;
- shared API/DTO/contract change;
- schema/domain redesign;
- broad refactor;
- multi-module business logic change;
- authorization/payment/entitlement redesign;
- feature redesign.

Report:
- exact command;
- full relevant error/log without secret values;
- OS/runtime/service/version context;
- failing test/health check;
- expected vs observed behavior;
- likely module/file when reasonably identifiable;
- whether production state was mutated;
- rollback/restore state.

## Safety rules

- Never print or commit secret values.
- Never reuse legacy project tokens, env files, databases, queues, storage or service directories for FANOOS.
- Never overwrite production data from the Git checkout or a developer backup.
- Backups and restore verification remain separate from Git.
- Do not deploy a dirty checkout or a SHA different from the approved release candidate.
- Do not bypass a failed backup, migration, readiness, tenant-isolation or security gate.
- Application rollback does not silently reverse forward database migrations; verify schema compatibility first.

## Expected final report

Return:
- repository and exact deployed SHA;
- environment/runtime versions inspected;
- dependency result;
- migration result;
- tests/smoke/health result;
- backup verification result;
- deployment result;
- live log/health result;
- any runtime fixes made and their branch/commit;
- remaining risks;
- final status: `COMPLETE`, `PARTIAL` or `BLOCKED`.

Do not declare deployment complete without observed live verification.