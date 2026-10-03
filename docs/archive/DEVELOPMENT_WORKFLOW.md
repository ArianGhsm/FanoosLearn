# FANOOS development workflow

FANOOS uses a local-first authoring loop. GitHub is the canonical source of accepted code and documentation; it is not a requirement to author ordinary work in the GitHub UI or to create a parallel-worker ceremony for every task.

## Normal local loop

1. Confirm the repository and target ref: `git remote -v`, `git branch --show-current`, and `git status --short`.
2. Fetch the canonical remote (`production` → `ArianGhsm/FanoosLearn`) and fast-forward a clean base branch. Do not discard unrelated local work.
3. Create a short-lived `codex/...` or task branch, edit locally, and keep the change scoped.
4. Read the ownership and contract docs before a cross-module change. Keep every tenant-owned operation server-resolved and keep legacy projects read-only.
5. Run applicable deterministic checks, review the diff, and scan for secrets/runtime artifacts.
6. Commit one clear change and push the branch: `git push -u production <branch>`.
7. Use normal review/branch protection before merging to `main`. Record the accepted commit SHA as the release hand-off.

## Runtime boundary

Codex/runtime operators use the accepted exact SHA to inspect dependencies, run migrations and smoke checks, verify backups, deploy through the approved runbook, and verify live health/logs. Development branches do not deploy or overwrite production data. Small environment-only fixes are returned as a reviewed commit; broad source redesign stays in the normal development flow.

## Safety that remains mandatory

- Production SQL/object storage, secrets, logs, caches, uploads and backups stay outside Git.
- No force-push on shared branches and no destructive reset of another user's work.
- Web, Telegram, Bale and workers call the shared platform contracts; they do not create alternate domain authorities.
- Contract changes, migrations, payment/entitlement changes and integration-only hotspots require explicit review and applicable regression tests.

## Historical parallel-work references

`docs/workflow/WORKER_MANIFEST_TEMPLATE.md`, `WORKFLOW_MIGRATION_0.md` and `INTEGRATION_ONLY_PATHS.md` document earlier coordinated work and central-file ownership. They remain useful when a task explicitly needs parallel branches, but `PARALLEL_BASE_SHA`, worker manifests and an integration chat are not prerequisites for ordinary local work. `docs/REBUILD_LOCAL_WORKFLOW.md` is the concise operational guide.

## Release definition

A release is complete only after the accepted SHA, deterministic CI, runtime verification, required backup, canonical deployment and live health/log/smoke checks all pass. This definition is unchanged by the local-first authoring model.
