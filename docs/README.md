# Documentation

## Read first

- [`PROJECT_PRINCIPLES.md`](PROJECT_PRINCIPLES.md) — what FANOOS is, the
  GitHub/server source-of-truth rule, continuity and reference readiness.
- [`WORKFLOW.md`](WORKFLOW.md) — laptop-independent branch, check, merge, server deploy, confirm.
- [`ops/SERVER.md`](ops/SERVER.md) — the production server.

## Product

- [`product/05_DENTAL_RESIDENCY_DATA_MODEL.md`](product/05_DENTAL_RESIDENCY_DATA_MODEL.md)
  — the question bank's design and build order. **Current direction.**
- [`product/10_VISUALS.md`](product/10_VISUALS.md) — mind maps, flowcharts,
  tables and capsules: what they are, how one is made from the book pages.
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

- [`product/09_CHAPTER_CLASSIFICATION.md`](product/09_CHAPTER_CLASSIFICATION.md)
  — page-evidence rules; eligible server-held reference editions first.
- [`ops/SERVER.md`](ops/SERVER.md), [`../ops/updater/README.md`](../ops/updater/README.md),
  [`../ops/backup/README.md`](../ops/backup/README.md).
- [`ops/QUESTION_IMPORT.md`](ops/QUESTION_IMPORT.md) — bringing exam papers
  from Google Drive into the bank (discover, prepare, import).
- [`ops/RESIDENCY_CLASSIFICATION_1398_1405.md`](ops/RESIDENCY_CLASSIFICATION_1398_1405.md)
  — where residency classification stands, and how to read its removed per-batch records.
- [`ops/REFERENCE_LISTS_NATIONAL_SPECIALTY_20261010.md`](ops/REFERENCE_LISTS_NATIONAL_SPECIALTY_20261010.md)
  — the national, board and promotion reference lists.

## Archive

[`archive/`](archive/) keeps only the four earlier documents that tests and
code comments still cite. Everything else from earlier stages was removed on
2026-10-11 and is in Git history (`git show cd64979:docs/archive/<path>`).
