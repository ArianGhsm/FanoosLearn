# Update Control Plane Readiness

Baseline: `5695d3ebcff52aa9bebe2782617e749bb093bdfb`

Readiness for an owner/admin bot “Update Server” control: **BLOCKED**

This is an architecture/readiness document only. It does not implement an updater, expose a shell, alter the server, or authorize deployment.

## Required trust model

```text
Owner private Telegram interaction
        |
        | normal bot authentication + canonical FANOOS account link
        v
FANOOS backend/control API
        |
        | narrow authenticated deployment-control request
        v
Privileged updater runner
        |
        | GitHub canonical main only
        | exact SHA + CI + ancestry + preflight
        v
Immutable release -> atomic activation -> health -> durable result
```

The bot is presentation/control input only. It is never a shell and never receives filesystem, SSH, process-manager or arbitrary Git-ref authority.

## A. Authority

Required policy:

- update permission is a dedicated platform-scoped operator capability, not a workspace admin/representative capability;
- request is accepted only from a linked canonical FANOOS user with that explicit platform permission;
- Telegram invocation is restricted to the owner's approved private-chat context;
- a numeric Telegram ID alone is not authorization;
- channel link revocation or platform-role revocation must immediately prevent new update requests;
- high-risk update actions are audit logged with request ID and candidate SHA, never secrets.

Current readiness: **MISSING / HIGH**.

The current RBAC model can represent an explicit platform permission, but the messaging identity link and deployment-control permission/endpoint do not exist yet.

## B. Control plane

Required boundary:

`Bot -> backend/control service -> privileged updater`

Forbidden:

- bot `exec`/shell calls;
- callback payload containing a command, path, branch, tag or arbitrary SHA to execute;
- deployment credentials inside bot state;
- direct database manipulation from the updater to simulate deployment success;
- updater access to unrelated legacy projects/services.

Required control request shape should be minimal: effectively “apply the currently approved canonical main release” plus an idempotency/request token. The updater resolves the actual candidate server-side.

Current readiness: **MISSING / HIGH**.

## C. Candidate release

Required algorithm:

1. fetch the canonical configured GitHub repository identity and reject any mismatch;
2. resolve `refs/heads/main` server-side;
3. obtain its exact 40-character commit SHA;
4. verify the candidate is not an arbitrary caller-provided ref;
5. verify ancestry/fast-forward policy from the active release unless an explicitly separate recovery procedure is invoked;
6. verify the required CI workflow/check set for that exact SHA is successful;
7. record candidate SHA before any mutation;
8. no-op safely if candidate equals active SHA.

Current deploy tooling validates a supplied exact SHA but does not provide the complete canonical-main/CI/ancestry policy.

Current readiness: **PARTIAL / HIGH**.

## D. Preflight

A future updater must fail closed before mutating schema or active release unless all required checks pass.

### Required deployment lock

- one global FANOOS deployment lock;
- durable request identity/idempotency;
- duplicate callback/request returns the same operation status;
- lock state is recoverable after runner restart;
- stale locks require explicit, auditable recovery policy.

Current: absent.

### Required state checks

- repository identity exactly `ArianGhsm/FanoosLearn`;
- current active release SHA and candidate SHA;
- candidate ancestry policy;
- release artifact integrity;
- runtime config exists and is not inside release tree;
- required PHP/DB/object-store/process dependencies healthy;
- sufficient disk/inode headroom;
- no forbidden dirty mutation of runtime code/release paths;
- migration ledger checksum clean;
- migration precheck proves each unapplied migration is safe/resumable for this release;
- previous application release remains compatible with the post-migration schema during rollback window;
- required deterministic tests/contract checks pass or verified CI evidence is cryptographically/operationally tied to the candidate;
- verified pre-update backup completes before migration;
- active critical jobs/operations that cannot tolerate restart are absent or safely quiesced.

Current readiness: **PARTIAL / HIGH**.

The present deploy script covers several filesystem/repository/health checks but not the full set above. In particular, it does not execute backup verification and does not protect against interrupted multi-statement DDL.

## E. Activation

Required lifecycle states should be durable, for example:

`requested -> preflight -> backing_up -> testing -> migrating -> staging_release -> activating -> post_check -> succeeded`

Terminal alternatives:

`rejected`, `failed_pre_activation`, `rolled_back`, `failed_rollback`, `cancelled_before_mutation`.

Every state transition records timestamps and sanitized evidence. UI messages are a projection of this durable state; deleting/restarting the bot must not lose deployment truth.

Activation requirements:

- immutable release directory named by/linked to exact SHA;
- no application runtime data inside release tree;
- atomic current pointer/symlink activation;
- process reload/restart only after candidate is ready;
- restart-safe updater state;
- no concurrent activation;
- old release retained through observation/rollback window.

Current immutable-release/atomic-pointer foundation: **PASS_WITH_GAPS**.

Current durable lifecycle: **MISSING / HIGH**.

## F. Post-check

Required zero/low-risk post-activation checks:

- platform health/readiness reports exact active SHA;
- DB connection and migration ledger readiness;
- authenticated API smoke using a dedicated harmless test path/fixture, not production user mutation;
- object-storage private read/write readiness through a bounded diagnostic object if approved;
- outbox/job runner readiness;
- Telegram runtime identity/polling/webhook mode as applicable;
- Bale runtime identity/polling mode as applicable;
- service-auth signed request smoke without revealing keys;
- protected-delivery authorization/issuance/consume no-cost smoke using synthetic/approved fixture;
- protected-media worker capability/dependency check once implemented;
- no accidental restart/mutation of unrelated legacy services;
- error/log scan for the deployment correlation ID.

Paid providers must not be invoked merely to validate deployment presentation/runtime unless separately approved with a cost ceiling.

Current readiness: **PARTIAL / MEDIUM-HIGH**. Platform health exists; bot/worker hooks do not yet exist.

## G. Rollback

Rollback must be defined as **application rollback under a schema-compatibility contract**, not an assumption that the database is reversed.

Required properties:

- retain previous immutable release;
- migration changes are expand-first/backward compatible throughout the rollback window;
- preflight rejects a candidate whose migration contract makes previous app unsafe;
- post-activation health failure atomically returns application pointer to the previous known-good release;
- updater records rollback attempt and terminal result durably;
- if application rollback is unsafe because of schema/data mutation, stop and escalate to the explicit forward-fix/DR procedure instead of silently switching;
- backup restore is a separate disaster-recovery action because it can discard post-backup writes;
- a failed rollback health check must not be represented as successful rollback.

Current readiness: **PARTIAL / HIGH**.

`cpanel-rollback.sh` truthfully states DB changes are not reversed, but compatibility is not machine-enforced. The deploy path's automatic pointer rollback also cannot repair a partially applied migration.

## H. One-time Codex/bootstrap boundary

Under the new operating direction, Codex should be needed for initial server provisioning and exceptional troubleshooting, not routine releases.

### One-time bootstrap tasks

The bootstrap should install/configure exactly the host-owned prerequisites that GitHub release updates should not recreate ad hoc:

1. verify supported OS/hosting environment and storage headroom;
2. install/verify required system packages and PHP CLI/extensions;
3. create dedicated FANOOS process/deploy identities with least privilege;
4. create canonical immutable release root and `current` pointer location;
5. create durable runtime/state/config/log/backup/object roots outside releases with strict ownership/modes;
6. create the canonical MySQL database plus separate migration/application credentials where supported;
7. configure secret injection outside Git (DB, signing keys, payment/provider/channel/service keys);
8. configure private object storage root/bucket and permissions;
9. configure encrypted/off-host backup destination and recovery-key handling;
10. install the privileged updater runner/service with a fixed executable/config path and no arbitrary command interface;
11. configure the updater's GitHub read/fetch method for **only** `ArianGhsm/FanoosLearn`;
12. configure deployment lock/durable status storage;
13. install web/process-manager/service definitions for platform, Telegram, Bale and workers only when those deployables actually exist;
14. configure loopback/internal control endpoint or bounded IPC between backend and updater;
15. configure firewall/network rules so the updater is not a public shell/API;
16. configure log rotation/metrics/health endpoints;
17. perform first verified backup + isolated restore rehearsal;
18. perform first exact-SHA release activation/rollback rehearsal;
19. record the accepted runtime inventory without secrets;
20. disable/remove any temporary bootstrap access that is not required for normal operation.

### What future Update Server runs may do automatically

After bootstrap and after the HIGH blockers are fixed, an update run may:

1. resolve canonical `main` exact SHA;
2. verify required CI/ancestry/policy;
3. acquire deployment lock;
4. create **and verify** backup;
5. build/fetch/verify candidate release artifact;
6. run deterministic preflight/tests;
7. apply only approved ordered migrations under the migration-safety contract;
8. stage immutable release;
9. atomically activate;
10. restart/reload only FANOOS-owned processes that require it;
11. run post-checks;
12. automatically roll application pointer back when safe and necessary;
13. persist terminal result;
14. return a sanitized status to the owner bot.

It may **not** install arbitrary OS packages, edit arbitrary server files, accept arbitrary branches/SHAs, expose secrets, touch legacy runtimes, or bypass a failed gate.

## Durable deployment record — minimum fields

A future schema/secure control store should preserve at least:

- operation/request ID;
- requester canonical user ID;
- source channel/link ID reference;
- requested action;
- active SHA before request;
- candidate SHA;
- lifecycle status and phase;
- created/started/completed timestamps;
- deployment lock/lease owner and expiry if leases are used;
- CI verification result/reference;
- migration preflight/applied set/checksums;
- backup ID + verified status;
- release artifact digest;
- pre/post health results;
- rollback target/result;
- sanitized terminal error code;
- correlation/audit ID.

Do not store raw bot tokens, HMAC keys, DB credentials, SSH keys, complete environment files or arbitrary command strings in this record.

## Attack-surface rules

- no shell syntax from Telegram callbacks;
- no caller-provided path/ref/SHA as deployment authority;
- no implicit admin because a Telegram ID matches a configured number;
- no GitHub token sent to bot/backend responses;
- no updater endpoint exposed publicly without an internal authenticated boundary;
- replayed update callback/request resolves to the existing operation, not a second deployment;
- failure/progress-message retries cannot repeat backup/migration/activation;
- update status endpoints return sanitized phases/results only;
- all update permissions are platform scoped and independently revocable.

## Current decision

The existing release tooling is a useful foundation but **not ready** to be remotely driven by the Telegram owner/admin bot.

Readiness becomes `READY_WITH_GAPS` only after all HIGH prerequisites are closed and tested. Implementation of the actual Update Server button/control flow belongs to a later prompt after that re-gate.
