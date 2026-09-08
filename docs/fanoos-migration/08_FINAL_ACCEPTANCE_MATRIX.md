# Stage 8 — Final Acceptance Matrix

Status: **REPOSITORY-SIDE RELEASE GATE**  
Starting accepted UX state: `UX_STABILIZED_MAIN_SHA=aab498bee84f08979ec6bc6c5f4f0ffc03707b21`

The global UX stabilization report was merged by PR #15 and the exact resulting `main` SHA above passed main CI run #69. The stabilization report contains no unresolved Critical/High repository-side defect. Runtime/provider/browser evidence remains explicitly separate and is never inferred from CI.

Status vocabulary: `PASS`, `PASS_WITH_RUNTIME_VALIDATION`, `PARTIAL`, `BLOCKED`, `DEFERRED_BY_PRODUCT_DECISION`.

| Capability | Source Stage | Code evidence | Contract | Deterministic test evidence | UX/channel coverage | Runtime validation remaining | Status | Risk |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Multi-tenant directory/workspaces | 2–5 | `database/migrations/0001_*`, Platform Core | `core-v1`, tenant ownership docs | `TenantIsolationTest`, `CorePlatformTest` | Web + bot workspace projection | Real production hierarchy/mapping reconciliation | PASS_WITH_RUNTIME_VALIDATION | Low |
| Auth/account | 3–5 | Identity/Auth services, `0002_*` | public core auth/session contract | Core + service/auth tests | Web login/account + bot linking | Live cookie/TLS/session policy on target | PASS_WITH_RUNTIME_VALIDATION | Medium |
| Scoped RBAC | 3–7 | Authorization services, RBAC schema/seeds | scope/RBAC ownership contract | tenant, Stage7, deployment tests | Web and bot use backend decisions | Production role assignment review | PASS_WITH_RUNTIME_VALIDATION | Medium |
| Academics | 3–5 | academic tables + WorkspacePlatformService | core-v1 | CorePlatformTest | Web domain renderer + bot read | Legacy term/course mapping | PASS_WITH_RUNTIME_VALIDATION | Low |
| Schedule | 4–5 | `schedule_events`, Core service | core-v1 | CorePlatformTest | Web/Telegram/Bale | Timezone/live data smoke | PASS_WITH_RUNTIME_VALIDATION | Low |
| Grades | 4–5 | grade schema/services | core-v1 | CorePlatformTest + tenant constraints | Web/Telegram/Bale | Legacy grade parity | PASS_WITH_RUNTIME_VALIDATION | Medium |
| Announcements | 4–5 | notification domain + Core service | core-v1/internal-v1 | Core + Stage7 tests | Web + both bots | Live notification provider smoke | PASS_WITH_RUNTIME_VALIDATION | Low |
| Forms | 4–5 | form tables/services | core-v1 | CorePlatformTest | Web | Legacy form/version policy | PASS_WITH_RUNTIME_VALIDATION | Low |
| Search | 5 | tenant-scoped search service | core-v1 | Core + UX regression tests | Web domain search | Production dataset leakage smoke | PASS_WITH_RUNTIME_VALIDATION | Medium |
| Commerce/orders | 4–7 | Commerce services/schema | core/internal commerce contracts | Stage7PlatformTest | Web + bots truthful order projection | Provider/config smoke | PASS_WITH_RUNTIME_VALIDATION | Medium |
| Payment verification | 4–7 | Payment service/provider abstraction | server-authoritative payment contract | Stage7 tests + Stage8 bundle proof guard | Web/bots never grant success locally | A real provider adapter/credentials must be selected and verified before paid production launch | DEFERRED_BY_PRODUCT_DECISION | Medium if paid launch enabled |
| Entitlements | 4–7 | EntitlementService/schema | entitlement contract | Stage7 + protected-delivery tests | Web/bot wording separated from payment | Production migrated grants reconciliation | PASS_WITH_RUNTIME_VALIDATION | High impact, gated |
| Content/resources | 3–6 | Content services/schema/import service | content + storage contracts | ContentEngineTest | Web + bot resource UX | Real object/content migration | PASS_WITH_RUNTIME_VALIDATION | Medium |
| Assessments | 4–6 | exam schema/content engine | core/content contracts | ContentEngineTest | Web + bots | Legacy question/exam parity | PASS_WITH_RUNTIME_VALIDATION | Medium |
| Secure delivery | 6–7 | Delivery services + internal API | secure delivery contract | Stage7 + bot transport tests | Telegram Rich; Bale capability-aware | Live recipient-specific send | PASS_WITH_RUNTIME_VALIDATION | High impact, gated |
| Protected-media | 6–7 | `apps/workers/protected-media`, Platform capabilities | protected media internal-v1 | Python worker tests | Telegram protected document; Bale fail-closed | qpdf/poppler/Pillow/font + live derivative smoke | PASS_WITH_RUNTIME_VALIDATION | High impact, gated |
| Notifications | 4–7 | notification projector + receipt outbox | jobs/notification contract | Stage7 + bot restart tests | Telegram/Bale | Crash/restart/provider observation | PASS_WITH_RUNTIME_VALIDATION | Medium |
| Telegram | 7 + UX | `apps/telegram-bot`, shared package | internal-v1 | Python deterministic suites | Rich-first Persian UX | `getMe`, send/edit/document/update live smoke | PASS_WITH_RUNTIME_VALIDATION | Medium |
| Bale | 7 + UX | `apps/bale-bot`, shared package | internal-v1 + capability matrix | Python deterministic suites | readable native/plain fallback | live provider smoke | PASS_WITH_RUNTIME_VALIDATION | Medium |
| Update control | 7 | DeploymentControlService/Runner, updater scripts | deployment control-plane contract | DeploymentControlTest | Telegram private owner UX only | real deploy credential/timer/hooks/rollback rehearsal | PASS_WITH_RUNTIME_VALIDATION | High impact, gated |
| Backup/restore | 4 + 7 | Operations backup/manifest/restore scripts | deploy/backup runbooks | BackupContractTest | operator-only | real SQL/object restore rehearsal | PASS_WITH_RUNTIME_VALIDATION | High impact, gated |
| Deploy/rollback | 4 + 7 | exact-SHA build/deploy/update runner | deploy runbook | release artifact + DeploymentControlTest | owner Update Server status | real immutable activation/health/rollback | PASS_WITH_RUNTIME_VALIDATION | High impact, gated |
| Web UX | UX wave | PHP shell + `app.js` + `domain-ux.js` | UX reports | web UX/fuzz CI | Persian RTL/accessibility/domain renderers | iOS/Android/desktop/browser + screen-reader smoke | PASS_WITH_RUNTIME_VALIDATION | Low |
| Bot semantic UX | UX wave | shared semantic presentation | Worker 3/4 UX contract | bot UX stabilization tests | Telegram/Bale shared meaning | provider rendering observation | PASS_WITH_RUNTIME_VALIDATION | Low |
| Telegram Rich | UX wave | `telegram_presentation.py` | channel capability contract | Python UX tests | Rich-first | live layout/protected-send smoke | PASS_WITH_RUNTIME_VALIDATION | Low |
| Bale fallback | UX wave | `bale_presentation.py` | capability matrix | Python UX tests | plain/native readable; protected delivery fails closed | live provider behavior | PASS_WITH_RUNTIME_VALIDATION | Low |
| Legacy normalized import | 3 + 8 | `LegacyTargetId`, `LegacyBundleValidator`, `LegacyImportEngine` | `08_LEGACY_DATA_MIGRATION_PLAN.md` | `Stage8FinalClosureTest` | operator-only | real read-only production snapshot/extraction | PASS_WITH_RUNTIME_VALIDATION | High impact, gated |
| Migration reconciliation | 3 + 8 | `LegacyReconciler`, reconciliation CLI | `08_DATA_RECONCILIATION.md` | `Stage8FinalClosureTest` | operator-only | real source/destination counts and object evidence | PASS_WITH_RUNTIME_VALIDATION | High impact, gated |
| Real production cutover | 8 | runbooks only by design | Stage8 Codex handoff | cannot be proven by repo tests | all channels | supervised staging + production evidence | PASS_WITH_RUNTIME_VALIDATION | Critical operation |

## Acceptance decision

Repository-side Stage 1–7 implementation, the UX integration/stabilization wave, deterministic migration tooling, red-team coverage, and release/cutover contracts are acceptable for an exact-SHA runtime rehearsal. There is **no Critical/High repository-side `BLOCKED` item** at this gate.

A production-complete claim is prohibited until the one-time runtime handoff verifies the actual host, SQL/object backup and restore, real legacy snapshot reconciliation, browser/provider behavior, service supervision, protected-media toolchain, Update Server runner, and exact-SHA activation/rollback.

If paid commerce is required at initial launch, selection and verification of a real payment provider becomes a cutover prerequisite; otherwise payment initiation must remain disabled rather than falling back to a fake/client-authoritative success path.
