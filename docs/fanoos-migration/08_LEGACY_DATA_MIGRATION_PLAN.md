# Stage 8 — Legacy Data Migration Plan

Status: **repository tooling complete; real legacy snapshot not available to this Chat**. No production data is invented or committed.

Primary read-only oracle: `ArianGhsm/Dentistry1402TUMS`. Its documented production truth is private `storage/` plus service-owned state, not the Git checkout. Observed examples include `storage/classops/store.json`, `storage/classops/domain-state.json`, `storage/integrations/bot_links.json`, and `server-only/storage/chat/store.json`; exact production bytes must be extracted read-only during the supervised runtime handoff.

## Classification inventory

| Source class | Decision | Destination / rule | Release note |
| --- | --- | --- | --- |
| Users/student identities | MIGRATE_TRANSFORMED | `iam_users` + verified `iam_user_identifiers` | Deduplicate only on verified normalized identifiers. No password/session migration. |
| Country/city/university/faculty/program/cohort | REBUILD_FROM_CANONICAL_SOURCE | Fanoos directory hierarchy | Explicit mapping manifest; no route/year inference. |
| Workspace mappings | MIGRATE_TRANSFORMED | `tenant_workspaces` | Every source workspace alias must map explicitly to one destination UUID. |
| Memberships | MIGRATE_TRANSFORMED | `tenant_workspace_memberships` | Active/suspended/ended translated explicitly. |
| Representatives/admin roles | MIGRATE_TRANSFORMED | scoped RBAC assignments | Never copy boolean admin flags as a bypass. Human review required. |
| Terms/courses/offerings/sessions | MIGRATE_TRANSFORMED | `academic_*` | Same-workspace references and canonical codes required. |
| Schedules/exams | MIGRATE_TRANSFORMED | `schedule_events`, assessment domain | Normalize timezone and canonical offering/assessment references. |
| Grades | MIGRATE_TRANSFORMED | gradebook/item/result model | Student + enrollment + offering must resolve in same workspace. Reconcile counts/samples. |
| Announcements | MIGRATE_TRANSFORMED | notification message records | Historical import must not accidentally broadcast to current recipients. |
| Forms | MIGRATE_TRANSFORMED | form definition/version model | Preserve version/provenance; old submissions only if product relevance is approved. |
| Notes/resources/DentNotes | MIGRATE_TRANSFORMED | generic Content Resource model | DentNote remains a format, not a tenant/product primitive. Use Content import service where structured. |
| Content files/object references | MIGRATE_TRANSFORMED | private object store + `content_objects` | Copy bytes through private staging; verify size/MIME/SHA-256 before metadata apply. Never trust a legacy public path as a new object key. |
| Question banks/past exams | MIGRATE_TRANSFORMED | content/assessment versions | Preserve provenance and immutable source/version checksum. |
| Assessment attempts | ARCHIVE_ONLY by default | optional assessment attempts after explicit parity decision | Historical attempts can create misleading analytics if scoring/version parity is not provable. |
| Products/prices | MIGRATE_TRANSFORMED | `commerce_products` / immutable price versions | Only still-valid commercial catalog rows. Historical prices remain immutable. |
| Orders | MIGRATE_TRANSFORMED | `commerce_orders`/lines | Preserve amount/currency/buyer/workspace and source evidence. |
| Payments | MIGRATE_TRANSFORMED | `commerce_payment_attempts` | `verified` only with canonical provider proof. Return URL/UI success is insufficient. |
| Entitlements | MIGRATE_TRANSFORMED | `entitlement_grants` | Separate access grant from payment presentation. Order-derived grants require verified payment evidence. |
| Meaningful notification history | ARCHIVE_ONLY by default | external archive or selected canonical records | Delivery/retry telemetry is not domain truth. |
| Telegram/Bale account links | REBUILD_FROM_CANONICAL_SOURCE by default | canonical Stage 7 account-link flow | Direct import is prohibited by normalized bundle tooling. A special supervised migration is allowed only if identity can be cryptographically mapped without exposing old encryption keys/subjects. |
| ClassOps canonical academic items | MIGRATE_TRANSFORMED | relevant Fanoos academic/content modules | Read from the documented legacy website-owned store, not bot-local caches. |
| DM/chat history | ARCHIVE_ONLY | legacy retained archive | `server-only/storage/chat/store.json` is not required for Fanoos academic truth unless product scope explicitly changes. |
| Analytics counters/history | ARCHIVE_ONLY | retained legacy analytics export | Never use analytics/logs as canonical user/payment/access state. |
| Runtime update offsets/retry/cache/PID/locks/temp | RUNTIME_ONLY / DO_NOT_MIGRATE | none | New Fanoos channel/runtime state starts independently. |
| `.env`, tokens, HMAC/API/payment keys, cookies, SSH/FTP credentials | SECRET_NEVER_MIGRATE | new secret manager/runtime env | Generate/configure new Fanoos secrets. Never place values in bundle, report, Git, or migration ledger. |
| Legacy logs | ARCHIVE_ONLY | operational archive outside Git | Logs may support reconciliation evidence; they never create canonical success/access facts. |

## Normalized bundle contract

Stage 8 adds `scripts/import/legacy-bundle.php` and a version-1 normalized JSON contract. It is deliberately **not** a raw SQL/table importer. Each row names one approved `entity_type`; that type maps inside Fanoos code to an explicit destination table. The bundle cannot select arbitrary tables, cannot supply `id`/`workspace_id`, and cannot contain secret/runtime field names.

Required top-level fields are `schema_version=1`, a stable read-only `source.key`, exact `source.snapshot_sha256`, unique `batch_key`, explicit `workspace_map`, and `rows`. Each row carries `entity_type`, opaque `source_key`, deterministic `target_id`, canonical `values`, optional `workspace_key`, `transformed`, and bounded evidence metadata. `LegacyTargetId` derives the target UUID with the runtime-only legacy HMAC key from `(source system, entity type, source key)`; the importer rejects forged target IDs.

The source key itself is used only while processing the private bundle. Persistent mapping and row ledgers store its HMAC digest, not the raw legacy identifier. `migration_batches.manifest_json` stores only counts/digests/workspace mappings/expectations, not the raw rows.

## Modes and safety

```bash
# Offline shape/secret/evidence validation; no DB mutation.
php scripts/import/legacy-bundle.php --bundle=/private/staging/legacy-v1.json --validate

# Destination-aware preflight; reads DB, verifies workspaces/schema/existing mappings/target conflicts; no mutation.
php scripts/import/legacy-bundle.php --bundle=/private/staging/legacy-v1.json --dry-run

# Explicit apply. Bundle must already be clean and conflict-free.
FANOOS_MIGRATION_APPLY_CONFIRMED=1 \
php scripts/import/legacy-bundle.php --bundle=/private/staging/legacy-v1.json --apply
```

`--apply` creates/uses a read-only source namespace and migration batch, then applies domain rows in dependency order inside one transaction. Any destination constraint, mapping conflict, or changed target after preflight rolls back **all domain rows for that batch**. The batch is marked failed separately with only a redacted source digest/index. A completed identical `batch_key` + snapshot + normalized bundle is an idempotent replay. A failed/non-terminal batch is retained for audit; supervised retry uses a new batch key rather than rewriting history.

The importer introspects the destination schema and rejects unknown/missing required columns. Nested values are allowed only for JSON columns. Binary canonical values use `hex:<hex>` encoding. Content object rows additionally require verified object evidence, a safe private storage key, and a SHA-256 match; production sequence must copy/verify bytes **before** inserting verified metadata.

## Financial evidence policy

A payment row may preserve failed/cancelled/non-verified history without implying access. A row with `status=verified` is accepted only when its bundle evidence contains `provider_verified=true`, a SHA-256 digest of canonical provider verification evidence, non-empty provider reference, and `verified_at`. Raw provider secrets/authorities are not evidence.

An order-derived entitlement must point in bundle evidence to a verified payment source row in the same workspace. A migration-policy entitlement that is not tied to a migrated order requires an explicit approval evidence digest. No client page, bot button, legacy cache flag, or notification text can create entitlement.

## Content/object migration

Structured notes/question resources should preferentially use the existing `ContentImportService` so current content validation/version semantics remain authoritative. Binary objects require a private runtime extraction manifest containing source logical identity, source byte size and SHA-256. Codex must copy to a non-public staging object prefix, re-hash destination bytes, run MIME/PDF checks as appropriate, then emit normalized `content_object` rows whose evidence digest equals the verified object checksum. Partial/mismatched objects stay rejected and are never published.

## Legacy bot-link policy

The old Dentistry bot link store contains encrypted profile/phone material and verified-phone HMAC relationships. Stage 8 intentionally does not expose an importer for `messaging_links`: old encryption/runtime identity material belongs to the legacy boundary. Default cutover behavior is to have users link Telegram/Bale through the new short-lived Fanoos challenge. If a supervised direct mapping is later judged necessary, it requires a separate one-time runtime adapter that proves canonical user identity and writes through Stage 7 link services; no raw subject/token/key enters Git or the generic bundle.

## Exact runtime extraction requirement

Because this Chat has no access to production `storage/`, the one-time Codex handoff must first create a **read-only immutable export** from the actual legacy host, record a SHA-256 fingerprint before transformation, and generate a redacted inventory/count report. It must compare source implementation/docs against the live storage paths before declaring a file canonical. It then transforms only approved classes into the versioned private bundle, using the same HMAC key solely inside the runtime secret boundary.

The repository ships `scripts/import/legacy-bundle.sanitized.json` with synthetic data and the deterministic integration test as proof of contract behavior. No real person, payment, token, storage path, bot subject, or production identifier belongs in that fixture.

## Stop conditions

Cutover stops on unknown source ownership, snapshot digest change after export, ambiguous workspace/person mapping, representative/admin ambiguity, duplicate source identity, target conflict, cross-tenant reference, unverified financial success, entitlement without approved source, object checksum mismatch, secret/runtime field detection, partial apply, or failed reconciliation/restore rehearsal. Legacy stays available as rollback/reference until the production observation window is accepted.
