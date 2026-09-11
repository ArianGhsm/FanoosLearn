# FANOOS Agent Rules

These rules apply to every coding/review agent working on this repository.

## 1. Repository and product boundary

- The only writable repository for FANOOS work is `ArianGhsm/FanoosLearn`.
- FANOOS is an independent product, repository, runtime, database/storage domain, deployment, credential set and bot identity.
- `Dentistry1402TUMS`, Dent1402Bot/IntegratedDent1402Tums, `VoiceMatnAIBot`, `DeepSeekLiveAIBot` and other legacy projects are read-only reference implementations unless the user explicitly authorizes a separate change there.
- Reuse proven behavior by extracting/adapting it into FANOOS. Do not make FANOOS depend on a legacy runtime, database, env file, queue, bot token, storage tree or service.
- Never hard-code a university, city, faculty, program, cohort, professor, course, Telegram group or Dentistry 1402 assumption into application logic when it belongs in data/configuration.

## 2. Canonical sources of truth

- GitHub is the canonical source of truth for FANOOS code, migrations, tests, contracts, non-secret config templates, operational scripts and documentation.
- Production database/object storage is the canonical source of truth for production data.
- Backups are independent verified copies of production state; Git is not a data backup.
- Runtime data, secrets, logs, caches, PID/lock files, uploaded production objects and backup archives must stay outside Git.

## 3. Development roles

Primary development is performed in a local working copy and synchronized to the canonical GitHub repository:
- architecture and implementation;
- feature/refactor work;
- contract work and deterministic tests/CI;
- review and integration through normal protected branches.

GitHub is the canonical coordination/release point, not a requirement to author ordinary work in the browser. Use the concise local loop in `docs/REBUILD_LOCAL_WORKFLOW.md`.

Codex is primarily the runtime/deployment operator after integration:
- exact-SHA checkout;
- environment/runtime inspection;
- dependency installation/verification;
- migration dry-runs/execution;
- runtime/integration/smoke tests;
- log-based diagnosis;
- service/system setup;
- backup verification;
- deployment, rollback and live health verification.

Codex may make only small, obvious, environment-specific fixes. Architectural, contract, broad-refactor or multi-module source defects must be reported with evidence and returned to ChatGPT for implementation.

## 4. Git and branch workflow

Before any write, verify repository full name and current target ref.

For normal single-task work:
1. verify the canonical repository, remote and clean target ref;
2. fetch and fast-forward the intended base when the checkout is clean;
3. create a short-lived `codex/...` or task branch and make scoped changes;
4. run applicable deterministic validation and review the diff for secrets/runtime state;
5. commit and push to the canonical remote;
6. use normal review/branch protection before merging.

For explicitly coordinated parallel work:
- all worker branches start from the same `PARALLEL_BASE_SHA`;
- workers never write directly to `main`;
- workers do not merge or deploy;
- workers do not pull/rebase arbitrary newer `main` mid-task;
- workers edit only their owned paths and must respect integration-only paths;
- no force-push on shared branches;
- unrelated refactors are forbidden.

A worker branch is done when its scope, tests and documentation are complete and the branch is ready for integration. Production deployment is not part of worker completion.

## 5. Reuse-first implementation rule

Before creating a new implementation for behavior known to exist in legacy projects or earlier FANOOS stages:
1. search FANOOS and the relevant read-only reference projects;
2. identify the proven implementation/behavior;
3. prefer extract/refactor/adapt over rewriting;
4. preserve validated behavior unless the FANOOS architecture requires a deliberate change;
5. when a new implementation is unavoidable, record a short technical reason why reuse was unsafe or unsuitable.

Do not create duplicate business logic or parallel sources of state merely for stylistic cleanup.

## 6. Shared contracts and ownership

Read these before cross-module work:
- `docs/fanoos-migration/02_MODULE_AND_DATA_OWNERSHIP.md`
- `docs/fanoos-migration/02_TARGET_ARCHITECTURE.md`
- `contracts/REGISTRY.md`
- `docs/workflow/INTEGRATION_ONLY_PATHS.md`

Rules:
- every durable fact has one owning module;
- Web, Telegram bot and future app use backend contracts and do not become canonical domain stores;
- workers do not silently change shared contracts;
- a required contract change must be made in the integration/baseline stage with compatibility tests and documentation;
- central wiring/hotspot files are integration-only unless a task explicitly reassigns them.

## 7. Safety and data integrity

Never commit or print real secrets. In particular, keep out of Git:
- real `.env` files;
- API/bot tokens, signing/HMAC keys, passwords, OAuth/session credentials;
- SSH/FTP credentials and private keys;
- production DB/SQLite/JSON state;
- payment/user/session runtime state;
- logs, caches, PID/lock files;
- production snapshots or backup archives.

Before risky schema, migration, payment, entitlement, identity, storage or deployment work, preserve the existing rollback/backup gates. Never overwrite production state from a development checkout.

## 8. Testing and release discipline

Do not weaken existing tests or CI to make a change pass.

At minimum, use the existing repository checks appropriate to the change. CI currently provides PHP lint/static checks, secret/text guards, exact-commit release artifact validation and MySQL integration/tenant-isolation tests.

An integrated release is complete only after:
- branch work is integrated;
- shared contracts are validated;
- deterministic regression/integration CI is green;
- Codex verifies the exact release SHA in the real runtime;
- required production backup is verified;
- canonical deployment succeeds;
- live health/smoke/log verification succeeds.

## 9. Documentation alignment

When architecture, contracts, persistence, runtime topology, backup/restore behavior or deployment semantics change, update the corresponding documentation in the same change. Do not let operational docs describe a different system from the code.

## 10. No silent scope expansion

Do not add product features, redesign UI, change auth/payment architecture or perform broad cleanup unless the task explicitly requires it. If another module must change, expose the dependency through an interface/contract or return it as an integration requirement rather than editing another worker's implementation.
