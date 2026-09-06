# FANOOS legacy data mapping contract

Status: import foundation only; no import authorized or executed

## Separation rule

The earlier systems are read-only evidence. FANOOS does not share their code, database, storage, credentials, deployment, or runtime. This document describes a future controlled exporter/importer boundary; it does not authorize reading, copying, or changing any specific legacy file or database. Literal source-code or data access requires the user's explicit path and scope permission first.

## Canonical destination map

| Source concept, if later approved | FANOOS owner/table family | Transformation rule |
| --- | --- | --- |
| Geographic/institution labels | `directory_countries` through `directory_institutions` | Normalize labels; create governed directory aliases; never infer authorization |
| Faculty/major/entry labels | `directory_faculties`, `directory_programs`, `directory_cohorts` | Resolve explicit hierarchy; reject ambiguous or missing parents |
| Year/site partition | `tenant_workspaces` | Map from an approved manifest; a route/year string never becomes tenant authority |
| Person/account | `iam_users`, `iam_user_identifiers` | Deduplicate only by verified normalized identifiers; never import plaintext passwords/session tokens |
| Role/admin/representative flags | memberships plus scoped RBAC assignments | Translate through an approved role map; no boolean admin bypass |
| Term/course/session | `academic_*` | Bind every row to the manifest workspace and validate composite FKs |
| Note/file/resource | `content_resources`, versions, objects, bindings | One owner, immutable version, verified checksum/MIME, explicit binding/publication |
| Exam/question/attempt | `exam_*` | Freeze a version; preserve provenance; import attempts only after parity rules exist |
| Grade | `grade_*` | Resolve user through verified mappings and offering/enrollment within the same workspace |
| Product/order/payment | `commerce_*` | Preserve immutable amount/currency/provider reference; never infer success from a return URL |
| Access/subscription | `entitlement_grants` | Record explicit source and validity; do not translate stale cache flags into access |
| Announcement/notification | `notification_*` | Create canonical message and explicit recipients; channel history is not domain authority |

This is a concept map, not a field-by-field copy specification. Field mappings are versioned per approved source snapshot after inspection.

## Source identity map

`migration_legacy_id_mappings` provides stable, repeatable identity resolution:

```text
(source_system, source_entity_type, HMAC(source_key))
    -> (target_entity_type, target_uuid)
```

- Raw source identifiers are never stored in the mapping table or logs.
- The HMAC key comes from `FANOOS_LEGACY_ID_HMAC_KEY` in the secret boundary and is never committed.
- Repeating the same source-to-target mapping returns the existing mapping and updates only last-seen metadata.
- Mapping the same source identity to a different target is a hard conflict; the importer must stop or reject that row.
- `first_batch_id` preserves provenance, while `last_seen_batch_id` supports reconciliation.
- Rotating the HMAC key requires a planned re-key process with old/new key access; unplanned rotation makes source identities unresolvable.

## Batch and row workflow

1. Obtain explicit permission for the exact legacy source and fields.
2. Create a read-only, immutable export. Record its digest and a redacted manifest.
3. Register a `migration_source_systems` namespace and a unique `migration_batches.batch_key`.
4. Validate the entire hierarchy/workspace manifest before row processing.
5. For each row, digest its source key, resolve/create the target through the owning FANOOS module, then record `inserted`, `updated`, `unchanged`, `rejected`, or `conflict`.
6. Keep rejected payloads minimal and redacted. Never store secrets, password hashes, payment credentials, private message bodies, or raw source identifiers in rejection details.
7. Reconcile source counts, accepted/rejected counts, relationship counts, checksums, and product-specific parity assertions.
8. Mark the batch completed only when every row has an outcome and invariants pass.

Import code must call module validation/application services when those exist; it must not become a permanent cross-module raw-table writer.

## Idempotency and rerun behavior

- Source systems and batches have stable unique keys.
- Source identity mapping is a unique digest tuple and is protected by `SELECT ... FOR UPDATE` in the current PHP adapter.
- Workspace-local natural keys are unique within the target tenant.
- Identical reruns return `unchanged` and do not generate new users, resources, payments, entitlements, or mappings.
- A changed source row requires an explicit update policy for that entity; immutable versions create a new target version rather than overwrite history.
- A conflicting identity, workspace, financial state, or ownership relationship is rejected instead of guessed.

The Prompt 3 integration test proves same-input rerun stability and conflicting-remap rejection without connecting to any real legacy source.

## Reconciliation gates

Before any future production import is accepted:

- every source row is counted exactly once as accepted/rejected/conflict;
- all target foreign keys and same-workspace assertions pass;
- sample users and multi-workspace memberships are reviewed;
- representatives/admins are checked against the RBAC matrix;
- resource object count, size, checksum, ownership, binding, and publication are reconciled;
- payment totals/statuses are reconciled by immutable provider evidence;
- entitlements reconcile to approved payment/admin policy, never to a cache flag;
- exam attempts and grades pass product-specific parity checks;
- a restore/retry rehearsal succeeds from the pre-import backup.

No cutover or deletion of legacy data is part of Prompt 3.
