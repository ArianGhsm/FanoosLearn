# Workflow Governance

Use these files together:

- `AGENTS.md` — repository-wide agent rules.
- `docs/DEVELOPMENT_WORKFLOW.md` — GitHub-first ChatGPT development/integration model.
- `docs/CODEX_RUNTIME_HANDOFF.md` — Codex runtime/deployment role and escalation boundary.
- `contracts/REGISTRY.md` — shared contract ownership/freeze.
- `docs/workflow/INTEGRATION_ONLY_PATHS.md` — central shared hotspots.
- `docs/workflow/WORKER_MANIFEST_TEMPLATE.md` — template for each parallel worker.
- `docs/workflow/WORKFLOW_MIGRATION_0.md` — migration checkpoint and baseline-freeze rule.

The final `PARALLEL_BASE_SHA` is the reviewed, CI-green merged `main` commit after Workflow Migration 0, not an arbitrary worker or migration-branch commit.