# FANOOS agent rules

Rules for every person or coding agent working on this repository.

## 0. Read first

1. `docs/PROJECT_PRINCIPLES.md` — what FANOOS is (a dental residency
   exam-preparation platform built on a reference-aware question bank), the
   rule that the laptop, GitHub and the server stay identical, and the open
   decisions. Where this file and the principles disagree, the principles win.
2. `docs/product/05_DENTAL_RESIDENCY_DATA_MODEL.md` — the bank's design.
3. `docs/WORKFLOW.md` — the working loop, from branch to deploy.
4. `docs/ops/SERVER.md` — what runs on the server and how it is deployed.

## 1. Boundaries

- The only writable repository is `ArianGhsm/FanoosLearn`. It is **public**:
  nothing that grants access (credentials, tokens, keys, SSH users, key
  paths) is ever committed.
- FANOOS has its own runtime, database, storage, deployment, credentials and
  bot identities. Other projects (Dentistry1402TUMS, IntegratedDent1402Tums
  and others) are read-only references; FANOOS never depends on their
  runtime, database, files or secrets. The production host is shared with
  another workload, which FANOOS work never touches.
- Nothing about a specific university, cohort, course or reference is
  hard-coded in application logic; it belongs in data.

## 2. Sources of truth

- **GitHub `main`** — code, migrations, tests, contracts, docs, config
  templates, operator scripts.
- **The production database and storage** — all production data.
- **Backups** — verified copies of production; Git is not a backup.
- Secrets, runtime data, logs, caches, uploads and backups stay out of Git.
  Owner-only notes on the laptop go in the git-ignored `.local/`.

## 3. Working loop

Work happens on the laptop and reaches the server only through GitHub
(details and commands in `docs/WORKFLOW.md`):

1. Fetch and fast-forward `main`; branch from it.
2. Make one coherent change, with its tests and its documentation.
3. Run the checks (`php tests/run.php` static, `node --test tests/web/*.mjs`,
   plus the integration suite in CI).
4. Push, open a pull request, merge when CI is green, delete the branch.
5. Deploy the merged `main` through the updater in the same session, verify
   the live site, and run `scripts/dev/check-sync.sh`.

Every session ends with everything pushed. A merged change that is not on
the server is a gap to report.

## 4. Reuse first

Before building behaviour that may already exist (here, in git history, or
in a reference project), find the proven version and adapt it. Do not create
a second source of the same fact. When something new is unavoidable, say why
in the change.

## 5. Contracts and ownership

Before cross-module work read `contracts/REGISTRY.md`,
`docs/fanoos-migration/02_MODULE_AND_DATA_OWNERSHIP.md` and
`02_TARGET_ARCHITECTURE.md`. Every durable fact has one owning module; the
web, the bots and any future app are clients of the platform API, never
stores of their own. A contract change ships with its compatibility tests
and its `contracts/openapi` update.

## 6. Safety

- Never commit or print secrets: `.env` files, tokens, keys, passwords,
  session or payment state, production data, logs, backups.
- Schema changes are expand-only migrations applied by the updater after a
  verified backup; destructive ones go through the supervised contract path.
- Never overwrite production data from a development checkout. Bulk changes
  to production data are repository scripts with a dry run, run by the
  operator after a verified backup.
- The owner never operates the server by hand; anything the server must do
  is a script in this repository.

## 7. Tests

Never weaken a test or CI to make a change pass. A change is done when CI is
green, it is deployed, and the live site behaves.

## 8. Documentation

When behaviour, data, contracts, runtime or deployment change, the matching
document changes in the same pull request. Historical documents live in
`docs/archive/` and are not maintained.

## 9. Scope

Do what the task asks. Product features, redesigns, auth or payment changes
and broad cleanups happen when the owner asks for them, not as side effects.
