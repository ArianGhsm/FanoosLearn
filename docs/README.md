# Documentation

## Read first

- [`PROJECT_PRINCIPLES.md`](PROJECT_PRINCIPLES.md) — what FANOOS is, the
  three-copy sync rule, open decisions.
- [`WORKFLOW.md`](WORKFLOW.md) — branch, check, merge, deploy, confirm.
- [`ops/SERVER.md`](ops/SERVER.md) — the production server.

## Product

- [`product/05_DENTAL_RESIDENCY_DATA_MODEL.md`](product/05_DENTAL_RESIDENCY_DATA_MODEL.md)
  — the question bank's design and build order. **Current direction.**
- [`product/07_COMPETITOR_FEATURES.md`](product/07_COMPETITOR_FEATURES.md)
  — every option MedoFast and Parseh offer, and which ones FANOOS has.
- `product/01`–`04` — features built before the dental pivot that still run
  (exam runner, sign-up and disciplines, payments, account merge). They
  describe the code as it is; where they speak of medical banks or
  universities, the principles take precedence.

## Architecture and contracts

- [`../contracts/REGISTRY.md`](../contracts/REGISTRY.md) and
  `../contracts/openapi/` — the platform API.
- [`fanoos-migration/`](fanoos-migration/) — the platform's architecture
  record: target architecture, module and data ownership, data model, RBAC,
  backup and restore, content engine, bot contracts. The deploy and
  infrastructure runbooks in it (`04_*`) describe the earlier cPanel host;
  the live deploy path is the updater (`ops/SERVER.md`).

## Operations

- [`ops/SERVER.md`](ops/SERVER.md), [`../ops/updater/README.md`](../ops/updater/README.md),
  [`../ops/backup/README.md`](../ops/backup/README.md).

## Archive

[`archive/`](archive/) holds documents from earlier stages (UI rebuilds,
review rounds, the parallel-worker workflow, the cPanel-era handoffs). They
are kept for history and are not maintained.
