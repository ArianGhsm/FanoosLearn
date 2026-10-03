# Integration-Only Paths

These paths are shared hotspots. Parallel workers may read them but must not modify them unless their manifest explicitly transfers ownership for a specific task. Normal central wiring belongs to the Integration Chat.

## Default integration-only paths

```text
AGENTS.md
.env.example
.github/workflows/**
contracts/REGISTRY.md
contracts/openapi/**
database/migrations/**
database/seeds/**
apps/platform/src/Http/ApiKernel.php
apps/platform/src/Core/PlatformFactory.php
apps/platform/src/Core/WorkspacePlatformService.php
apps/platform/bootstrap.php
apps/platform/public/api.php
docs/DEVELOPMENT_WORKFLOW.md
docs/CODEX_RUNTIME_HANDOFF.md
docs/fanoos-migration/02_TARGET_ARCHITECTURE.md
docs/fanoos-migration/02_MODULE_AND_DATA_OWNERSHIP.md
docs/fanoos-migration/04_DEPLOY_RUNBOOK.md
```

If a listed path does not exist in a particular baseline, that does not authorize a worker to create an alternate central equivalent. Report the required wiring to integration.

## Why these paths are restricted

They contain one or more of:
- public/shared contracts;
- central routing/composition;
- cross-module orchestration;
- schema sequencing or generic seeds;
- environment/release/deploy semantics;
- repository-wide governance/CI.

Concurrent edits to these hotspots create semantic conflicts even when Git can merge the text automatically.

## Worker behavior

When a worker needs a central change:
1. implement the owned module/export/adapter/test fixture needed on its branch;
2. document the requested integration wiring in its manifest/handoff;
3. do not edit the central file;
4. Integration Chat applies the smallest compatible wiring after reviewing all workers.

## Exceptions

A task may explicitly assign one integration-only file to one worker when parallelism would otherwise be impossible. The manifest must state:
- exact path;
- reason;
- exclusive owner;
- consumers affected;
- contract/test impact.

No other worker may then modify that path for the same parallel batch.