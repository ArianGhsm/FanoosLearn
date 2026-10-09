# Workflow — GitHub → server (no laptop required)

Owner decision: `docs/PROJECT_PRINCIPLES.md` §2–5 (2026-10-09).
Development runs in an approved hosted checkout or an isolated worktree on
the **server**, never in the live release directory. A laptop is optional.
No essential state is left solely in a working directory or chat.

## 1. Begin with the current state

- Read `docs/PROJECT_PRINCIPLES.md`, `AGENTS.md`, and the task's runbook.
- Inspect GitHub `main` and the server's running release SHA; identify any
  pending deployment gap. Check current authorized production data through
  read-only scripts when needed. Do not copy it into public GitHub.
- Fetch `origin/main`; create a dedicated branch or separate server
  worktree **outside** `/srv/fanoos/releases` and `/srv/fanoos/current`.
  Never develop in the updater's active worktree while it is deploying.

```sh
git fetch --prune origin
git switch -c docs/example origin/main
```

Push the branch early, including unfinished WIP commits, so the next agent
can find all source changes.

## 2. Implement and validate

Make one auditable change with its tests, updated canonical documentation,
reproduction steps, expected outputs, and an explicit result checklist.
All private inputs and processing outputs stay in the secure server workspace,
with durable snapshots/checkpoints and a documented recovery path.
Do not publish secrets, copyrighted book files, question stems or user data.

```sh
php tests/run.php
node --test tests/web/*.mjs
php scripts/ci/check-text.php
```

Only run database integration tests against an isolated disposable
`*_test` database, with `FANOOS_ALLOW_TEST_DB=1`. CI runs its
separate test environment. Stop if any check fails; never lower tests to
make a change pass.

For question source classification, additionally follow
`docs/product/09_CHAPTER_CLASSIFICATION.md` and record all validator
results. The server's approved reference library—not the laptop—is the
input. Skip editions that are unready and continue eligible questions.

## 3. Review and merge

Push a named branch and open a PR with validation output and the
server/data impact. Wait for **green CI on the PR** and required review.
Merge to `main`, delete the merged branch, and verify **green CI on the
merge commit** before requesting a deploy.

```sh
git push -u origin docs/example
gh pr create --base main
gh pr checks --watch
# merge only after all required checks succeed
```

A WIP PR may remain open; it is not deployed and its state must be
documented. Do not force-merge a failed check.

## 4. Deploy the code on the server

Follow `docs/ops/SERVER.md` and `ops/updater/README.md`. The authorized
operator, through SentinelX or equivalent controlled access, performs:

1. Confirm the latest GitHub `main` SHA and its **green** CI result.
2. Fast-forward `/srv/fanoos/updater-checkout` to that SHA.
3. Queue deployment only through the repository's
   `scripts/ops/request-deployment.php` under the approved updater identity.
4. Let the updater back up and verify, build/check, migrate if needed, switch
   `current`, and perform its smoke/rollback checks.
5. Verify the live release SHA, site/API/bot health, and no interruption to
   the unrelated services sharing the host.

**No manual edits** to `current` or `releases/<sha>`. A merge that is
not deployed is a reported **deployment gap**, not "completed".

## 5. Check GitHub/server sync (from the server)

Run the read-only server-first checker, without SSH back to a laptop:

```sh
sh /srv/fanoos/updater-checkout/scripts/dev/check-sync.sh
```

It compares GitHub `origin/main` with the server's updater checkout and
running release. A difference exits nonzero and must be resolved via the
normal updater. Existing local development branches are **not** part of
the sync definition.

## 6. Apply data changes separately

A source classification, reference ingestion, or correction is **not
deployed by merging code**. For a private data change, inspect its exact
inputs, run importer `check` and `--dry-run`, verify
`questions_changed=0` unless a separately approved source transcription
fix, perform a **verified backup**, execute the audited import and
publication, and validate the resulting counts and existing human reviews.
Log all identifiers, decision files, versions and remaining pending work
in the protected server workspace and update the public, non-sensitive
runbook with verified totals.

## 7. Handoff after every session

Update the task-specific runbook and operational checkpoint with:

- purpose, working Git branch/PR/SHA and server release SHA;
- exact versioned commands and safe paths; data/audit/backup IDs;
- success/failure evidence, counts, exceptions, remaining dependencies;
- what the next agent should read and execute to resume safely.

A chat summary alone is not a handoff. A laptop artifact alone is not
a handoff. If a step cannot run, state precisely what is blocked and
persist that blocker in the appropriate runbook.
