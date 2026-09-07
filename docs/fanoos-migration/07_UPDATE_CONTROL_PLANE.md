# Stage 7 — Safe Update Control Plane

## Goal

The owner-facing `Update Server` action must create a durable deployment intent. It must never execute raw shell in Telegram/Bale, accept an arbitrary ref/SHA/remote, or expose deploy credentials to the bot or PHP request process.

Canonical flow:

```text
owner private bot action
→ signed FANOOS internal API
→ canonical messaging link
→ platform-scoped deployment.manage
→ release_update_requests
→ dedicated updater runner
→ canonical origin/main exact SHA
→ preflight + exact-SHA CI
→ verified backup
→ tests + migration preflight
→ immutable release activation
→ fixed service restart hooks
→ health/smoke
→ durable terminal state
```

## Authorization

Permission: `deployment.manage`.

It is platform-scoped and is seeded only into `platform-super-admin` and `platform-deployment-operator`. Workspace representative/admin roles do not receive it by inheritance. The bot must resolve its platform subject through the canonical FANOOS messaging link before an update request can be created. Telegram private-chat gating remains an adapter concern in Chat 2 and is an additional gate, not a replacement for backend authorization.

## Request contract

Internal endpoint:

`POST /api/internal/v1/deployments/requests`

Allowed body fields are limited to:
- `platform`: `telegram|bale`
- `subject`: platform subject observed by the signed adapter
- `target_key`: configured deployment target
- `idempotency_key`: caller-stable key for callback retry

Unknown fields are rejected. In particular the contract has no `command`, `shell`, `path`, `remote`, `branch`, `ref`, or user-supplied SHA field.

Status endpoint:

`POST /api/internal/v1/deployments/status`

returns safe observable state only. Secrets, environment contents, deploy credentials, lease tokens and raw command output are not API fields.

## Durable state model

`release_update_requests` is the current request snapshot; `release_update_events` is an append-only safe lifecycle record. A target may have at most one non-terminal request claimed by the runner at a time.

Observable states:

```text
REQUESTED
PREFLIGHT
BACKUP
TESTING
MIGRATING
ACTIVATING
RESTARTING
HEALTHCHECK
SUCCEEDED
FAILED
ROLLED_BACK
```

There is no fake percentage. `safe_failure_code`, `current_sha`, `candidate_sha` and `rollback_sha` are bounded operational metadata, not logs.

## Candidate policy

The privileged executor resolves the candidate itself:
1. repository identity must be exactly `ArianGhsm/FanoosLearn`;
2. configured origin must resolve to that repository;
3. fetch canonical `refs/heads/main`;
4. resolve one exact lowercase 40-character commit SHA;
5. verify current release → candidate ancestry according to policy;
6. verify required GitHub CI for that exact candidate SHA;
7. treat candidate == current as a safe no-op terminal success.

No user or bot input participates in ref selection.

## Privileged runner boundary

`scripts/ops/update-runner.php` is invoked by a dedicated timer/service and accepts no positional arguments. The web request only writes an authorized durable request. Repository/deploy credentials live in the updater service environment created during one-time bootstrap and are not available to Telegram/Bale adapters.

The runner claims work with a lease and persists state after each phase, providing restart-safe observability and idempotent callback handling.

## Preflight and backup gates

Before production mutation the executor checks at least:
- current deployed SHA and candidate SHA;
- canonical repository/ancestry policy;
- exact candidate CI;
- release workspace/disk/tool prerequisites;
- current health;
- migration compatibility preflight;
- deployment lock/lease;
- configured critical-operation hook when a runtime defines one.

Backup is not Git. The production backup script must run and `verify-backup.php` must independently validate the result. Any backup or verification failure stops before migration/activation.

The legacy `cpanel-deploy.sh` path is hardened to verify the backup before migration as well; normal post-bootstrap updates should use the durable updater rather than a bot-triggered shell call.

## Deployment and activation

The executor uses an exact-SHA immutable release directory. Runtime config, database state, object storage, logs and backups remain outside the release tree and are never overwritten from Git.

Candidate tests run before activation. Forward-compatible migrations run only after migration preflight. Activation uses the existing atomic current-release pointer pattern. Restart actions are fixed operator-configured hooks; request payloads cannot add commands.

## Health and smoke

The platform hook must cover liveness/readiness, database connectivity and repository/runtime sanity. Additional fixed hooks may be configured during bootstrap for:
- tenant-safe authenticated API smoke;
- object-storage safe read;
- Telegram process/API smoke;
- Bale process/API smoke;
- protected-delivery no-cost smoke;
- worker process health.

Hooks are configured on the server, not supplied in an update request.

## Rollback semantics

If post-activation restart/health fails, the runner may atomically restore the previous application pointer when the migration set was declared expand/backward-compatible, restart the previous release and persist `ROLLED_BACK`.

Database migrations are never automatically reversed. If schema compatibility cannot be proven, the operation fails closed and requires supervised recovery; application rollback must not pretend to be database rollback.

## Security properties

- no arbitrary shell from bot/web request;
- no arbitrary branch/ref/SHA/remote;
- explicit platform permission;
- durable idempotency and per-target serialization;
- exact-SHA CI gate;
- verified backup gate;
- safe finite states/failure codes;
- credentials outside Git and outside bot/backend responses;
- immutable code release; production data remains authoritative outside Git.

## Runtime-only validation remaining

Repository tests can exercise the state machine with fake executors, but production completion still requires one supervised bootstrap rehearsal: real filesystem permissions, GitHub deploy credential, backup destination, restore verification, system service/timer behavior, configured restart/health hooks, first exact-SHA activation and pointer rollback on the target host. No such server validation is claimed by Stage 7 source work.
