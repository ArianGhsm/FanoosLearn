# FANOOS local-first rebuild workflow

This is the normal workflow for FANOOS authoring while the next server/release is being prepared. It keeps development local and makes the accepted Git commit the hand-off point for a later deployment.

## Source of truth

- Canonical repository: `ArianGhsm/FanoosLearn` (the local remote is named `production`).
- `main` is the release baseline. Work in a short-lived `codex/...` or task branch; do not force-push shared branches.
- Production database, object storage, secrets, logs and backups are outside Git. A local checkout never overwrites them.

## Daily loop

1. Start from a clean checkout. Confirm `git remote -v`, the target branch and `git status --short`.
2. Synchronize before editing: `git fetch production` and fast-forward the base branch when it is clean. Preserve or stash unrelated local work; never reset it away.
3. Make the smallest coherent change locally. Read the relevant ownership/contract document before changing a cross-module boundary.
4. Run the checks appropriate to the change. For documentation-only work, at minimum run `git diff --check` and the repository secret/text guards; source changes also use the applicable unit, static and integration checks.
5. Review the diff and confirm that no `.env`, runtime state, cache, log, upload, backup or generated secret entered Git.
6. Commit one clear, reviewable change and push the branch to the canonical remote: `git push -u production <branch>`.
7. Review/merge through the repository's normal protection when the change is ready. The accepted commit SHA is the only release hand-off.

## Later deployment hand-off

Deployment is a separate operation. The runtime operator checks out the exact accepted SHA, verifies dependencies and backups, runs migrations/health checks, and deploys through the approved runbook. This document does not authorize deployment, server edits or production-data access.

## What is not required for ordinary work

The old parallel-worker manifests, `PARALLEL_BASE_SHA` ceremony, integration chat and GitHub-UI authoring are historical governance material, not prerequisites for a normal local task. Use them only if a future task explicitly needs coordinated parallel branches. Contract ownership, tenant isolation, secret handling, testing and release gates remain mandatory.

## Boundaries

Web, Telegram and Bale are clients of the shared platform contracts. They do not become alternate domain stores. Legacy projects are read-only evidence: reuse behavior through a documented adapter or extraction, never their credentials, runtime state, database, storage tree or hard-coded Dentistry assumptions.
