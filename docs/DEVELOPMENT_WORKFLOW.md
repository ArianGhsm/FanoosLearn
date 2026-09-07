# FANOOS Development Workflow

## Purpose

FANOOS uses a GitHub-first development model in which ChatGPT performs most architecture, coding, review and integration work through repository branches, while Codex is retained for real-environment execution, runtime verification and canonical deployment.

This workflow changes how code is developed; it does not weaken data safety, backup, testing, rollback or deployment controls.

## Source-of-truth model

```text
GitHub exact SHA
      |
      v
feature/integration branches
      |
      v
CI + review
      |
      v
approved main SHA
      |
      v
Codex runtime verification
      |
      v
verified backup
      |
      v
canonical deployment
      |
      v
live health/log/smoke verification
```

- Code/configuration-as-code/docs/contracts: GitHub.
- Production data: production SQL/object storage.
- Backups: verified independent snapshots outside Git.

No development or deploy checkout may overwrite production data simply because its local state differs.

## Baseline and parallel development

Parallel work starts only after an immutable base commit is selected:

```text
PARALLEL_BASE_SHA=<40-character commit>
```

Every worker branch must start from exactly that SHA. Do not assume a fixed number of workers; choose 2-6 only when the dependency graph and file ownership make parallelism useful.

A worker must have a manifest defining:
- branch name;
- responsibility;
- owned paths;
- read-only paths;
- forbidden/integration-only paths;
- contracts consumed;
- dependencies;
- tests;
- required handoff output.

If two workers need to edit the same central file, reduce parallelism or defer that wiring to integration.

## Worker rules

Workers:
- write only the FANOOS repository and their assigned branch;
- never write directly to `main`;
- never merge or deploy;
- never force-push shared branches;
- do not rebase/pull arbitrary newer `main` during the task;
- do not edit another worker's implementation;
- do not commit secrets or runtime state;
- do not perform unrelated refactors;
- create/update their own tests and scoped documentation;
- expose cross-component needs through stable interfaces/DTOs/protocols rather than central wiring.

Definition of done for a worker branch:
- assigned scope complete;
- applicable deterministic checks pass;
- no secret/runtime-state leak;
- no unrelated changes;
- committed and pushed;
- PR/diff ready for integration;
- no deployment performed.

## Shared contract freeze

Before parallel development, identify shared contracts and freeze their version/ownership in `contracts/REGISTRY.md`.

Contract changes must be explicit. A worker that finds a contract insufficient should provide one or more of:
- a change request;
- a failing contract test;
- a documented requirement;
- an adapter that keeps the current contract stable.

Silent shared-contract changes are prohibited.

## Integration Chat

A separate Integration Chat owns cross-branch composition. It:
- reads every worker diff and manifest;
- verifies ownership boundaries;
- validates contract compatibility;
- resolves semantic conflicts;
- edits integration-only files;
- consolidates dependencies/configuration;
- adds cross-module and regression tests;
- aligns docs;
- produces the integrated release candidate.

The integration stage, not workers, owns central router/factory/bootstrap/schema-index/wiring changes unless a task explicitly says otherwise.

## CI

GitHub Actions is the primary deterministic validation layer for ChatGPT-authored branches and PRs. Existing CI must remain green and may be strengthened when a workflow requirement cannot otherwise be tested deterministically.

Do not disable or bypass a failing check merely to advance a branch.

## Codex handoff

After integration and CI, hand Codex one exact approved SHA. Codex does not receive a feature-development prompt; it receives a runtime/deployment handoff.

Codex responsibilities:
- checkout and verify exact SHA/clean state;
- install/verify dependencies;
- inspect the actual OS/runtime/services;
- execute migration dry-runs and approved migrations;
- run real integration/smoke/health tests;
- reproduce failures from logs;
- verify backup readiness;
- deploy through the canonical runbook;
- verify live health/logs after deployment;
- perform rollback when required.

Small environment-specific fixes may be committed on a dedicated runtime-fix branch and returned to ChatGPT for review/CI before deployment of the new SHA. Broad source-code redesign stays with ChatGPT.

## Collaboration between Arian and Hossein Ahang

GitHub is the shared coordination point between the two laptops. A local machine is not authoritative merely because it was used first.

For ordinary non-parallel work, sync the intended upstream state before starting and preserve any local uncommitted work. For coordinated parallel work, both users/agents use the agreed immutable `PARALLEL_BASE_SHA` instead of continuously pulling newer `main` into active worker branches.

## Legacy references

Legacy projects are evidence and source implementations, not runtime dependencies. Reuse follows:

```text
SEARCH -> VERIFY -> EXTRACT/ADAPT -> TEST -> DOCUMENT PROVENANCE
```

Do not rewrite a proven feature from scratch without a short technical justification, and do not copy legacy secrets, state, hard-coded tenant assumptions or deployment paths into FANOOS.

## Release definition of done

A release is complete only when:
- integration is complete;
- contract and regression CI is green;
- release SHA is exact and recorded;
- Codex runtime verification succeeds;
- required backup is verified;
- canonical deployment succeeds;
- live health/smoke/log verification succeeds.

Failure at any required gate means the release is partial/blocked, not complete.