# Stage 7 — Codex One-Time Runtime Bootstrap

## Operating model

Codex is not the routine release author or a bot-controlled shell. Its preferred Stage 7 role is one supervised bootstrap of a FANOOS runtime plus exceptional environment troubleshooting afterward. Normal updates are requested through the FANOOS deployment control plane and executed by the fixed privileged updater against canonical GitHub `main`.

## Preconditions

Codex must be given the target environment by the user at runtime. Secrets, credentials, hostnames that are not already public configuration, database passwords and private deploy keys stay outside Git and outside chat artifacts intended for source control.

Repository identity is fixed to:

`ArianGhsm/FanoosLearn`

Do not reuse legacy Dentistry/Voice runtime databases, bot tokens, service directories, object roots, queues, env files or backup state.

## One-time bootstrap inventory

A supervised bootstrap should perform and record the following.

### 1. Host and dependency inspection
- verify OS, disk/memory headroom and filesystem capabilities;
- verify supported PHP, required extensions, MySQL/MariaDB client/server compatibility and Python/runtime requirements for installed workers;
- verify Git, archive, backup and health tooling;
- install only approved missing runtime packages.

### 2. Least-privilege runtime identities
- create an application identity for web/runtime access;
- create a distinct updater service identity with only the privileges required for FANOOS releases and configured restart hooks;
- create worker identities where isolation is practical;
- ensure web/PHP cannot write privileged service configuration or execute the updater as arbitrary shell.

### 3. Filesystem layout
Create durable roots outside immutable releases for at least:
- shared runtime configuration/secrets;
- production object storage;
- logs/state required by operations;
- backups;
- repository/updater checkout;
- immutable releases;
- atomic `current` pointer.

Production DB/object state must never be populated from a Git checkout during update.

### 4. Database
- provision least-privilege FANOOS application and migration access consistent with the deployment environment;
- apply the canonical migration set only after backup/preflight gates;
- retain the migration ledger;
- verify interrupted-migration recovery behavior and expand-only unattended migration policy.

### 5. Stage 7 service credentials
Using `scripts/ops/register-service.php`, register service identity metadata/actions for the required adapters/workers. Generate independent high-entropy secrets outside Git and bind each key ID to its configured environment secret name.

Expected service classes may include:
- Telegram adapter;
- Bale adapter;
- protected-media worker;
- notification/worker role when deployed separately.

Do not share one all-powerful secret across unrelated services.

### 6. Messaging privacy keys
Generate and install independent high-entropy values for:
- channel subject lookup HMAC;
- channel subject encryption;
- existing protected-delivery/download/payment keys as required by the environment.

Only environment/config variable names belong in Git; values do not.

### 7. Deployment target
Register the canonical deployment target with `scripts/ops/register-deployment-target.php`. Configure the updater with fixed server-side values for repository checkout, release root, current pointer, backup root, restart hooks, health hooks and required CI policy.

The target configuration must not expose a user-selectable shell command or Git ref.

### 8. Private repository credential
Install a deploy credential scoped as narrowly as possible to read `ArianGhsm/FanoosLearn`. Keep it accessible to the updater service only. Never return it through a bot/backend API or commit it to repository config.

### 9. Updater service/timer
Adapt the tracked templates:
- `ops/updater/fanoos-updater.service.example`
- `ops/updater/fanoos-updater.timer.example`

The service executes `scripts/ops/update-runner.php` with no positional request arguments. Verify single-run serialization, restart behavior and safe logging/redaction.

### 10. Backup and restore proof
- configure production DB/object backup destinations outside releases;
- run `backup.php`;
- run `verify-backup.php` independently;
- perform an isolated restore rehearsal using the current restore contract;
- record only safe evidence/identifiers, never database contents or secrets.

### 11. Health/smoke hooks
Configure fixed hooks for the processes actually present on the host. At minimum validate platform readiness/DB. As Chat 2 deployables become available, add fixed Telegram/Bale/worker process and no-cost protected-delivery smoke hooks. Hooks are server configuration, not update request inputs.

### 12. First supervised update rehearsal
Perform one supervised end-to-end control-plane rehearsal:
1. create an authorized deployment request;
2. verify candidate resolves from canonical `origin/main` to an exact SHA;
3. verify exact-SHA CI;
4. verify backup + backup verification;
5. verify candidate tests/migration preflight;
6. stage immutable release;
7. activate atomically;
8. restart fixed services;
9. run health/smoke;
10. verify durable `SUCCEEDED` status;
11. separately rehearse application-pointer rollback with a safe test scenario.

Never simulate success by manually editing the request state.

## After bootstrap

Routine release path:

```text
GitHub reviewed/accepted main
→ owner Update Server action
→ signed internal request
→ deployment.manage
→ durable request
→ fixed updater
→ canonical main exact SHA
→ gates/activation/health
→ durable terminal result
```

Codex should not be required for every normal release.

## Exceptional Codex troubleshooting

Codex may later inspect/fix small runtime-specific defects such as a package, permission, path or service-unit issue. Source/contract/schema redesign must return to normal ChatGPT GitHub development and CI instead of being patched ad hoc on production.

Any reproducible environment change should be represented in source/template/docs where appropriate and reviewed before it becomes the new baseline.

## Bootstrap completion evidence

A bootstrap report should include, without secrets:
- host/runtime versions;
- service identities created (names/key IDs only);
- filesystem/service layout;
- database/migration result;
- backup verification and isolated restore result;
- updater service/timer status;
- canonical repository verification;
- exact SHA of first supervised release;
- tests/health/smoke results;
- rollback rehearsal result;
- remaining runtime risks;
- final `COMPLETE`, `PARTIAL` or `BLOCKED`.
