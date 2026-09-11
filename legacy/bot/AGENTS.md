# Bot runtime instructions

The repository-root `AGENTS.md` is authoritative. This directory is the
canonical Git source for Telegram/Bale runtime code previously kept in the
unversioned `IntegratedDent1402Tums` sibling.

- Production still runs release copies under `/opt/integrated-dent`; service paths do not follow the laptop checkout.
- Never add live `.env`, tokens, sessions, runtime databases/JSON state, logs, caches, PIDs, locks, generated output, backups, snapshots, or private keys.
- Current development is sequential: one task branch from an exact current `main` SHA, targeted/full relevant tests, one PR, CI, divergence/security review, then merge. Parallel workers/integration waves are historical only. Production deployment remains a separate exact-SHA release task.
- Before bot source writes, read `bot_runtime/docs/BOT_UX_SYSTEM.md` plus the existing implementation/tests that are the presentation or behavior benchmark for the affected surface.
- Runtime source changes use targeted tests here, then the repository/runtime gates from `docs/DEVELOPMENT_WORKFLOW.md`.
- Central router, scheduler, site API client, service units, deployment scripts, and dependency manifests remain high-risk shared files: edit them only when the current task explicitly requires it and add regression coverage.
