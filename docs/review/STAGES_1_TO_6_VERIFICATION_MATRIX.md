# Stages 1 to 6 Verification Matrix

Baseline: `5695d3ebcff52aa9bebe2782617e749bb093bdfb`

Legend: `PASS`, `PASS_WITH_GAPS`, `PARTIAL`, `FAIL`, `DEFERRED_BY_DESIGN`, `NOT_VERIFIABLE_WITH_CURRENT_ACCESS`.

| Claim | Evidence | Test | Status | Risk | Next action |
| --- | --- | --- | --- | --- | --- |
| Stage 1 reuse decisions are evidence-driven | `docs/fanoos-migration/01_REUSE_MAP.md`, `01_FEATURE_INVENTORY.md`, `01_FORENSIC_AUDIT.md` | documentation/code reconciliation | PASS | LOW | Re-pin exact legacy commits before any future extraction |
| No FANOOS runtime dependency on legacy repos | target architecture + inspected `apps/platform/**` | repository inspection | PASS | LOW | Keep legacy repos read-only |
| Active tenancy is not hard-coded to TUMS/Dentistry/1402 | migrations, platform services, generic fixtures | tenant integration fixtures | PASS_WITH_GAPS | LOW | Retain active-source hard-code scan in CI |
| Standalone Dent1402Bot/Integrated repository provenance | GitHub account repository search | none available | NOT_VERIFIABLE_WITH_CURRENT_ACCESS | MEDIUM | Use Dentistry committed references unless a canonical repo is later supplied |
| Stage 2 canonical backend authority | ADR-005, `apps/platform/**`, one MySQL schema | Core/Content integration | PASS | LOW | Preserve for Stage 7 clients |
| Workspace is tenant boundary | ADR-004, migrations 0001–0007 | `TenantIsolationTest`, `ContentEngineTest` | PASS | LOW | Preserve exact workspace context in bot contracts |
| No parallel domain DB for bot/worker | repository tree: only platform app implemented | repository inspection | PASS | LOW | Stage 7 bot may retain transport-local state only |
| Module ownership is enforceable | `02_MODULE_AND_DATA_OWNERSHIP.md` vs `WorkspacePlatformService.php` direct cross-family SQL | no ownership-boundary static test | PARTIAL | MEDIUM | Split/repository-bound module access or add enforceable ownership guard |
| Shared web/bot/future-app API architecture | ADR-005 + OpenAPI | OpenAPI exists but implementation drift found | PARTIAL | HIGH | Reconcile/freeze contract before Stage 7 |
| Stage 3 hierarchy is data-driven | migration 0001 | schema/integration | PASS | LOW | none |
| Global user + scoped membership | migration 0002 | tenant integration | PASS | LOW | none |
| No hidden `is_admin` bypass | `ScopeAuthorizer.php`, RBAC matrix | representative/global-admin scenarios | PASS | LOW | Keep explicit platform role only |
| Workspace-aware FK constraints | migrations 0003–0007 | cross-workspace invalid offering write | PASS | LOW | Expand negative FK fixtures as modules grow |
| Same-name course/resource isolation | workspace-local uniques + content metadata | TenantIsolation + ContentEngine | PASS | LOW | none |
| RBAC assignment validity/revocation logic | `ScopeAuthorizer.php` | code predicates exist; expiry/revocation cases not explicit release tests | PASS_WITH_GAPS | MEDIUM | Add revoked/expired/ended-membership negative tests |
| Migration checksum ledger | `MigrationRunner.php` | successful run then no-op rerun | PASS | LOW | none |
| Migration concurrency lock | `GET_LOCK` / `RELEASE_LOCK` | implementation inspection | PASS | LOW | test concurrent runner behavior |
| Interrupted migration rerun safety | `MigrationRunner.php`; `0006_core_platform.sql` multi-`ALTER TABLE` without `IF NOT EXISTS` | no fault-injection test | FAIL | HIGH | Make migrations resumable/single-effect and add failure-recovery test |
| Legacy ID mapping rerun/conflict | `LegacyIdMap`, migration tables | `TenantIsolationTest` | PASS | LOW | none |
| Stage 4 release artifact exact SHA | `build-release.php` | CI builds `$GITHUB_SHA` | PASS | LOW | retain artifact hash manifest |
| Runtime data excluded from release | `.gitignore`, `check-secrets.php`, deploy docs | CI guard | PASS_WITH_GAPS | LOW | optionally add stronger secret scanning |
| Object storage private/tenant namespaced | `FilesystemObjectStore`, `ObjectAddress` | `StorageSecurityTest` | PASS | LOW | no public direct roots in prod |
| Path traversal rejection | `UploadInspector` + object addressing | `../lesson.txt` test | PASS | LOW | none |
| Backup is independent of Git | `backup.php`, backup runbook | `BackupContractTest` | PASS | LOW | retain off-host copy requirement |
| Backup manifest verification exists | `verify-backup.php` | backup contract | PASS | LOW | integrate into deploy gate |
| Canonical deploy verifies pre-deploy backup | `cpanel-deploy.sh` calls backup but not verifier | script inspection | FAIL | HIGH | invoke verifier fail-closed before migration |
| Isolated restore tooling exists | `restore-test.php` | guarded restore contract | PASS_WITH_GAPS | MEDIUM | perform dated staging restore rehearsal |
| Exact-SHA immutable activation | `cpanel-deploy.sh` | script/static checks | PASS | LOW | future updater derives candidate from main only |
| Post-activation health and pointer rollback | `cpanel-deploy.sh` | script logic | PASS_WITH_GAPS | MEDIUM | add release activation/rollback integration test |
| Schema rollback is truthful | `cpanel-rollback.sh` explicitly does not reverse DB | docs consistent | PASS_WITH_GAPS | HIGH | machine-enforce previous-app compatibility with forward migrations |
| Deploy lifecycle durable across restart | current scripts/log output/READY marker only | none | FAIL | HIGH | add durable deployment operation record + lock/idempotency |
| Candidate is canonical approved main | current deploy accepts operator SHA | none | FAIL for future updater | HIGH | updater resolves main server-side, checks ancestry + CI |
| Main branch write protection | GitHub branch state `protected:false` | preflight | PARTIAL | MEDIUM | use ruleset/protection when available; retain process guard meanwhile |
| Stage 5 canonical account | `AuthService`, API account route | `CorePlatformTest` | PASS | LOW | none |
| Auth/session/revocation | `AuthService`, `iam_sessions` | successful/failed login, logout/session tests | PASS | LOW | add browser E2E |
| Password compatibility + rehash | `AuthService` | PBKDF2 rehash fixture | PASS | LOW | none |
| Password login throttling | `iam_login_attempts`, AuthService | throttling test | PASS | LOW | production observability later |
| CSRF browser mutation defense | ApiKernel/AuthService | CSRF failure/success tests | PASS for browser | LOW | distinguish service auth from browser CSRF |
| Account/workspace selection | AuthService account/select | multi-workspace tests | PASS | LOW | define bot active-workspace projection separately |
| Scoped representative/admin | ScopeAuthorizer + platform service | restricted representative tests | PASS | LOW | none |
| Academics navigation | WorkspacePlatformService | CorePlatformTest | PASS | LOW | none |
| Schedule | WorkspacePlatformService | CorePlatformTest | PASS | LOW | recurrence/connector remain deferred |
| Own published grades | WorkspacePlatformService | CorePlatformTest | PASS | LOW | full import operator flow remains foundation |
| Announcements | WorkspacePlatformService + outbox | CorePlatformTest | PASS | LOW | notification delivery worker is Stage 7 |
| Forms | form version/submission tables + service | idempotent form submit test | PASS | LOW | export/receipt/public guest policy deferred |
| Search replacement | workspace-keyed read model + reauthorization | two-tenant search fixture | PASS_WITH_GAPS | MEDIUM | add ACL enumeration/relevance/load suite |
| Products/orders server pricing | CommerceService | payment integration fixtures | PASS | LOW | live gateway deferred |
| Payment callback proof | `gateway->verify` before finalize | success/failure/duplicate | PASS | LOW | real provider sandbox later |
| Payment callback idempotency | CommerceService state claim/finalizer | duplicate callback | PASS | LOW | add out-of-order/concurrency stress |
| Payment reconciliation | CommerceService | integration evidence | PASS | LOW | add OpenAPI path/schema |
| Entitlement grant/revoke/check | EntitlementService | CorePlatformTest | PASS | LOW | add OpenAPI grant/revoke definitions |
| Protected authorization foundation | ProtectedResourceAuthorizer | Core/Content tests | PASS | LOW | none |
| Audit events | AuditLogger + `audit_events` | integration assertions | PASS_WITH_GAPS | MEDIUM | central metadata redaction/allowlist |
| Scoped/global dashboards | platform service | authorization fixtures | PASS | LOW | none |
| Shared API v1 implementation parity | `ApiKernel.php` vs `core-v1.yaml` | manual route comparison | FAIL | HIGH | contract reconciliation + contract test |
| UI shell | Stage 5 report/platform assets | CI/static/integration only | PASS_WITH_GAPS | MEDIUM | browser E2E later |
| OTP/account recovery | Stage 5 parity matrix | intentionally absent | DEFERRED_BY_DESIGN | INFO | later product slice |
| Full grade import | batch foundation only | no full operator/parser test | DEFERRED_BY_DESIGN | INFO | later slice |
| Form export/receipt/public guest | foundation only | none | DEFERRED_BY_DESIGN | INFO | later product decision |
| Live payment adapter | FakePaymentGateway test/dev only | fake provider | DEFERRED_BY_DESIGN | INFO | choose provider then sandbox fixtures |
| Institution/Navid connector | interface/foundation only | none live | DEFERRED_BY_DESIGN | INFO | later scoped integration |
| Stage 6 common resource aggregate | content resources/versions/metadata | ContentEngineTest | PASS | LOW | none |
| Immutable versions | content version tables/ContentService | version 1->2 and publish switch | PASS | LOW | none |
| Independent review/publish | reviews + ContentService | self-review denial/reviewer approval | PASS | LOW | add API contract routes |
| Derivation lineage | `content_derivations` | discipline note/summary/question bank derivations | PASS | LOW | none |
| Lecture note | generic resource type | ContentEngineTest | PASS | LOW | none |
| DentNote/discipline note | `discipline_note` + `format_key=dentnote` | ContentEngineTest | PASS | LOW | keep format data-driven |
| Summary | derived resource | ContentEngineTest | PASS | LOW | generator worker deferred |
| Question bank | generic resource | ContentEngineTest | PASS | LOW | source licensing/provenance on real imports |
| Past exam | generic resource/assessment kind | ContentEngineTest | PASS | LOW | none |
| Mock/practice assessment | ExamService | create/publish/start/score | PASS | LOW | none |
| Upload/checksum/private object | ContentUploadService + storage | inspected PDF upload | PASS | LOW | production storage/GC later |
| Entitlement access | protected authorizer | entitled resource delivery | PASS | LOW | none |
| Signed delivery issuance | SecureDeliveryService | issue/consume | PASS | LOW | none |
| Reauthorization on serve | SecureDeliveryService.consume | subject/access negative tests | PASS | LOW | retain before cache hit/send |
| Watermark identity trace | issuance HMAC + ledger | forensic ID assertion | PASS | LOW | byte renderer still deferred |
| Bulk import | ContentImportService | replay/duplicate/changed source tests | PASS | LOW | real licensed source approval required |
| Exam attempt revision | ExamService | stale revision conflict | PASS | LOW | none |
| Server-side scoring | ExamService | 10000-bps fixture | PASS | LOW | none |
| Correct answers hidden before submit | ExamService projection | ContentEngineTest | PASS | LOW | none |
| Cross-tenant content isolation | content services/FKs | same-name resource + outsider/cross-workspace tests | PASS | LOW | expand negative fuzz cases |
| Telegram/Bale forward-protected send | contract only | no live adapter | DEFERRED_BY_DESIGN | INFO | Stage 7 adapter + official capability/live smoke |
| Byte-level PDF raster/fingerprint | contract only | no FANOOS worker | DEFERRED_BY_DESIGN | INFO | Stage 7 protected-media worker |
| Protected-media worker | absent | none | DEFERRED_BY_DESIGN | INFO | freeze job contract first |
| Transcription/rendering worker | absent | none | DEFERRED_BY_DESIGN | INFO | later worker slice |
| Delivery receipt from channel | expected in Stage 7 | absent | DEFERRED_BY_DESIGN | MEDIUM | freeze idempotent receipt contract |

## Stage-level result

| Stage | Result | Highest risk |
| --- | --- | --- |
| 1 | PASS_WITH_GAPS | MEDIUM provenance limitation for standalone integrated bot source |
| 2 | PASS_WITH_GAPS | MEDIUM module-boundary enforcement drift; HIGH shared-contract readiness gap is carried into Stage 5/7 |
| 3 | PARTIAL | **HIGH interrupted migration recovery** |
| 4 | PARTIAL | **HIGH unverified deploy backup + no durable updater lifecycle/compatibility gate** |
| 5 | PARTIAL | **HIGH OpenAPI/implementation drift + no service-auth contract** |
| 6 | PASS_WITH_GAPS | deferred channel/worker implementation is honest and bounded |
