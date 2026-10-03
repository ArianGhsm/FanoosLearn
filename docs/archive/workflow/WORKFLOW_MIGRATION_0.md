# Workflow Migration 0 — Checkpoint

> Historical reference. The local-first workflow in `docs/REBUILD_LOCAL_WORKFLOW.md` is now canonical for ordinary work. This checkpoint is retained only to explain the earlier GitHub-first governance and must not be treated as a current prerequisite.

## Goal

Prepare FANOOS for GitHub-first development where ChatGPT performs primary source development/review/integration and Codex remains the runtime/deployment operator. No product feature is implemented by this migration.

## Baseline

```text
Repository: ArianGhsm/FanoosLearn
Original main HEAD: 51e2a8865f4f446b4305de3aa4571e9f863b1501
Migration branch: workflow/github-first-chatgpt
```

The original baseline was the completed Stage 6 checkpoint (`fix: use inspected upload contract`) with successful GitHub Actions CI.

## Source-of-truth result

- Code/contracts/migrations/tests/docs/declarative operations: GitHub.
- Production SQL/object storage: production data authority.
- Backups: independent verified copies outside Git.
- Legacy repositories: read-only implementation references, not FANOOS runtime dependencies.

## Governance added

- root `AGENTS.md` with repository/product boundary, reuse-first, branch, safety, test and role rules;
- `docs/DEVELOPMENT_WORKFLOW.md` for worker/integration semantics;
- `docs/CODEX_RUNTIME_HANDOFF.md` for verification/runtime-fix/escalation modes;
- `contracts/REGISTRY.md` for shared contract ownership/freeze;
- `docs/workflow/INTEGRATION_ONLY_PATHS.md` for central hotspots;
- `docs/workflow/WORKER_MANIFEST_TEMPLATE.md` for future parallel workstreams;
- stronger local runtime exclusions and repository secret/runtime-state guards;
- deploy runbook aligned with exact-SHA ChatGPT -> CI -> Codex handoff.

## What intentionally did not change

- no FANOOS product feature;
- no business logic rewrite;
- no auth/payment/entitlement redesign;
- no database migration/schema change;
- no server/runtime/deployment execution;
- no production data or secret movement;
- no legacy repository modification;
- no change to the existing core CI workflow unless validation proves it necessary.

## Parallel baseline freeze

Do **not** use the migration branch head as the long-term parallel baseline before review/CI/merge.

After this branch is reviewed, CI is green and the migration is merged to `main`, record the resulting exact 40-character `main` commit as:

```text
PARALLEL_BASE_SHA=<merged main SHA>
```

Stage 7 contract preparation should then start from that SHA. Before Stage 7 workers fork, the Integration Chat must verify the account-link, workspace, protected-delivery, payment projection, notification and protected-media worker contracts listed in `contracts/REGISTRY.md`.

## Status rule

This workflow migration is `COMPLETE` only after its PR is CI-green, reviewed and merged to `main`. Until then it is a migration candidate and production remains untouched.
