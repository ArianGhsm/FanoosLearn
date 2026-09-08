# Stage 8 — Data Reconciliation Contract

Status: **machine-checkable repository implementation; real source reconciliation is runtime evidence**.

Canonical command after an applied normalized batch:

```bash
php scripts/import/legacy-reconcile.php --batch=<migration-batch-uuid>
```

The command returns JSON and exits non-zero unless the report status is `PASS`. It reads migration ledgers and canonical destination rows; it does not modify domain data.

## Required accounting

For every normalized relational bundle:

```text
source_count
  = accepted
  + rejected
  + conflict

accepted
  = inserted
  + updated
  + unchanged
```

The current Stage 8 importer is intentionally insert/unchanged-first for a one-time migration: an existing target with different canonical values is a conflict rather than a silent update. A changed source snapshot uses a new supervised batch key/policy instead of overwriting a completed migration history.

The report emits at least:

| Field | Meaning | Acceptance |
| --- | --- | --- |
| `source_count` | normalized source rows recorded in batch manifest | exact |
| `accepted` | inserted + updated + unchanged | accounted |
| `transformed` | rows explicitly marked transformed | informational + expectation capable |
| `duplicate` | unchanged/idempotent destination rows | accounted, never new duplicate target |
| `rejected` | row rejects ledger | explained/redacted |
| `conflict` | source/target mapping conflicts | zero for accepted cutover batch |
| `destination_count` | batch target rows that currently exist | equals accepted for clean cutover |
| `orphan` | mapping/result target missing | zero |
| `cross_tenant_mismatch` | target workspace differs from recorded bundle workspace | zero |
| `money.record_count` | mapped payment attempts | reconcile to approved source count |
| `money.total_minor` | total requested payment amount in minor units | reconcile by currency/source report; never silently mix currencies |
| `money.verified_*` | provider-verified payment subset | provider evidence only |
| `payment_entitlement_inconsistency` | order entitlement without verified same-workspace payment | zero |
| `object_checksum_coverage` | mapped verified object rows with size + SHA-256 | 100% when object rows exist |
| `expectation_deltas` | optional source-export counters/totals vs destination metrics | every declared expectation matches |

## Production source report

Before transforming the legacy snapshot, Codex must create a private, redacted source reconciliation report with counts per approved source class and, where meaningful, counts per legacy workspace. For money it must report per-currency record counts and minor-unit totals by status, including a separate provider-verifiable success set. For objects it must report object count, byte total, checksum count, missing/unreadable files, and duplicate checksum groups. Raw personal identifiers, phone numbers, access tokens, provider credentials and source file contents are prohibited from the report.

The private normalized bundle may include integer `expectations`. The importer persists only these counters/totals in `migration_batches.manifest_json`; the reconciler compares known expectation keys against actual destination metrics. Unknown expectation keys are retained as metadata and require manual reconciliation rather than being treated as a pass.

## Domain-specific reconciliation

| Domain | Required checks |
| --- | --- |
| Users | source persons accepted/rejected; verified identifier duplicates reviewed; no authenticator/session rows imported |
| Workspaces | every legacy workspace alias maps explicitly; hierarchy parents verified; no same-name implicit merge |
| Membership/RBAC | active/ended membership counts; every representative/admin assignment manually sampled; no role outside intended scope |
| Academics | term/course/offering/session counts and same-workspace FK integrity |
| Grades | gradebook/item/result counts; source student → membership → enrollment → result chain; sample score parity |
| Content/resources | structured resource/version counts; publication/binding scope; DentNote format retained as metadata, not product type |
| Objects | source/destination byte size + SHA-256; verified status only after byte transfer; no public-root protected file |
| Assessments | assessment/version/question counts; immutable definition checksum; attempts archived unless parity explicitly approved |
| Products/orders | item/price/order-line arithmetic; buyer/workspace; currency preserved |
| Payments | status count + amount total per currency; `verified` subset backed by canonical provider evidence |
| Entitlements | no UI/cache-derived grants; order grants have verified payment; validity/revocation preserved |
| Bot links | normally zero migrated direct links; relink count tracked separately after cutover |

## Orphan and tenant checks

`LegacyReconciler` resolves each batch target through the fixed Stage 8 entity specification. A missing target is an orphan. Tenant-scoped targets are compared against the exact workspace ID stored in the batch row-result detail. Any mismatch fails reconciliation even if foreign keys would otherwise allow the row.

Database-level composite foreign keys and RBAC tests remain a second independent guard. Reconciliation is not a substitute for tenant constraints; tenant constraints are not a substitute for source/destination accounting.

## Content objects outside the generic batch

If structured resources are imported through `ContentImportService` or objects are staged through a dedicated object-copy procedure, Codex must produce an additional machine-readable object/content reconciliation JSON and retain it with the cutover evidence. The final acceptance packet must combine normalized relational reconciliation with that content/object report; zero object rows in one relational batch never means legacy objects were reconciled.

## Incremental/final delta

The initial staging migration uses an immutable source snapshot. Production cutover may require a final incremental export if legacy writes continue after the staging snapshot. The delta must have a new snapshot SHA-256 and batch key. Reconciliation must pass independently for the delta and cumulatively for the destination. Do not reuse a previous digest or mutate a completed batch manifest to make counts balance.

## Acceptance gate

A production data migration is accepted only when:

- every approved source class is explicitly `MIGRATE`, `MIGRATE_TRANSFORMED`, `REBUILD_FROM_CANONICAL_SOURCE`, `ARCHIVE_ONLY`, or `DO_NOT_MIGRATE`;
- relational accounting balances;
- no unresolved conflict/orphan/cross-tenant mismatch exists;
- financial totals/statuses reconcile per currency and verified payments have provider evidence;
- entitlement consistency is zero-defect;
- object checksum coverage is complete for migrated objects;
- representative/admin and selected user/grade/content/payment samples are manually reviewed;
- pre-import backup and isolated restore rehearsal are verified.

`RUNTIME_VALIDATION_REQUIRED=true` until these checks run against the actual production snapshot and target environment.
