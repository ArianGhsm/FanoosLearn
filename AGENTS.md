# FANOOS agent rules — server-first

These rules bind every human or AI agent. **Start with
`docs/PROJECT_PRINCIPLES.md`** (owner's 2026-10-09 operating decision);
where these or any old runbook differ, the principles prevail.

## 0. Read first

1. `docs/PROJECT_PRINCIPLES.md` — two authorities (GitHub and server),
   durable continuity and classification evidence.
2. `docs/product/05_DENTAL_RESIDENCY_DATA_MODEL.md` — model.
3. `docs/WORKFLOW.md` — GitHub PR, CI, updater deployment.
4. `docs/ops/SERVER.md` — production layout, protected data and operators.
5. `docs/product/09_CHAPTER_CLASSIFICATION.md` — mandatory before
   assigning a source, chapter or page.

## 1. Authorities and environment

- Only `ArianGhsm/FanoosLearn` is writable FANOOS source control. This
  repository is **public**; never commit private exam text/images, full
  copyrighted reference books, tokens, credentials, runtime state or
  identifiable production data.
- The **server** is the authority for production DB, storage, uploaded PDFs,
  protected source artifacts, processing workspaces and verified backups.
  FANOOS production is hosted by **IranServer (ایران‌سرور)**. Use the
  authorized access route available for that host: direct authenticated
  operator/SSH access is valid when its host key is pinned and credentials
  are protected; SentinelX is optional and is not the server itself.
- A provider name does not authenticate a host. Never bypass SSH host-key
  verification or put access credentials/host-key material in Git.
- **No laptop prerequisite:** a local computer is optional, not part of
  source-of-truth or sync checks. A separate authorized server worktree or
  hosted checkout may create and test branches; push to GitHub before ending.
- Immutable releases at `/srv/fanoos/releases/<sha>/` and
  `/srv/fanoos/current` are **not working directories**. All code ships
  from reviewed green `main` through the updater.
- The host runs unrelated workloads. Never depend on, inspect unnecessarily,
  restart, or modify those workloads. Keep FANOOS data and identities
  isolated.

## 2. Work protocol and documentation

Before each task, read the existing owner runbook and prior checkpoints.
Distinguish proposal, test, verified output, production change and pending
step. Document goal, exact inputs/versions, execution commands, decisions,
invariants, errors/fixes, output/backup IDs, validation, remaining work,
Git SHA/PR, and server state. Keep reusable logic in versioned scripts and
shared policy in `docs/PROJECT_PRINCIPLES.md`; task-specific details go
to the relevant `docs/` runbook and access-controlled server records.

**Everything must be recoverable in a new chat without history.**
A SentinelX context is only a convenience copy, never the sole handoff.
Never report a change as complete without verifying its persisted state.

## 3. Reference and question processing

- Fetch live private reference inventory with
  `scripts/ops/reference-library-inventory.php` and match each question
  to the official year/subject/edition in the catalog.
- Work with **eligible verified server editions** first. Set missing or
  incomplete editions aside as pending; no fabricated chapters and **no
  new nearest-edition fallback by default**. Preserve previously audited
  legacy assignments and human-reviewed sources.
- A verified PDF is **not automatically searchable text**: check complete
  page-marked book text and chapter-page mapping before deciding.
- Use `scripts/references/` search and evidence validator end-to-end.
  Every decision needs the book's own verbatim evidence, chapter, PDF page,
  edition and confidence; unanswered matches remain undecided, not `none`.
- Re-import only the site-matching sitting after
  `questions_changed=0` dry run and verified backup. Raw questions,
  decisions, reference text and reports stay in protected server storage,
  never Git. Do not write directly into content-addressed object storage.
- Avoid duplicating large PDFs, especially while disk usage is high.
  Missing secure reader or workspace is a dependency to document and
  implement under the normal PR process, not a reason to demand a laptop.

## 4. GitHub-to-server delivery

1. Fetch latest `main`; create a focused branch.
2. Commit reproducible changes with tests and docs; push WIP branches.
3. Run targeted checks, then a PR; merge only on green CI and review.
4. Have the authorized operator deploy `main` via the updater, with its
   backup and rollback logic; never patch a deployed release.
5. Check live SHA, service health, and `scripts/dev/check-sync.sh`
   **on the server**. Record an unresolved sync gap rather than hiding it.
6. Data imports are separate approved, auditable actions and require their
   own verified backup and post-import count check.

Integration tests use an isolated `*_test` DB; never point them at
production. Do not weaken tests, access checks, validators or thresholds to
get a green result. Migrations are expand-only; destructive production
changes require explicit authorization and a verified restoration path.

## 5. Ownership and boundaries

Before cross-module changes read `contracts/REGISTRY.md` and
`docs/fanoos-migration/02_MODULE_AND_DATA_OWNERSHIP.md`. Every durable
fact has one owner; applications/bots are API clients. A contract change
requires compatibility tests and a matching `contracts/openapi` update.
Product work not requested by the owner is out of scope. Old migration
history in `docs/archive/` is historical, not an instruction to restore
a laptop or obsolete runtime model.
