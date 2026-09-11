# FANOOS Bot Stage 8 — Final Cross-Channel Release Report

## Release boundary

This report records the final synchronized source handoff for the website,
Telegram, Bale and background workers. The deployed immutable release observed
by the updater is
`112a7af90e35c4d1ef3c143b7ad37adb4fe66c41` from canonical
`ArianGhsm/FanoosLearn` `main`. A later documentation-only commit, if any, is
not part of that active runtime release; this avoids a self-hashing report.
No secrets, provider payloads, personal data, runtime SQLite state or
production object contents are included here.

The release keeps one PHP platform authority, one tenant/RBAC model, one
payment/entitlement decision path and one content/protected-delivery path.
Telegram and Bale remain presentation/adaptation layers with independent
runtime identities and offsets.

## Cross-channel parity matrix

| Capability | Website | Telegram | Bale | Canonical authority | Release evidence |
| --- | --- | --- | --- | --- | --- |
| Account / identity | Authenticated session and account surface | Linked subject and account navigation | Linked subject and account navigation | Platform Identity + channel-link contract | Core/service-auth tests; bot UX suites |
| Workspace | Active/zero/multi-workspace shell | Workspace projection and switch | Workspace projection and switch | Tenant workspace membership | Tenant isolation + bot integration tests |
| Courses / sessions | Course workspace and session routes | Academic read journeys | Academic read journeys | Academic platform projections | CorePlatformTest + academic bot tests |
| Schedule / exams | Schedule and exam views | Schedule/exam summaries | Schedule/exam summaries | Academic services | Web academic contracts + bot tests |
| Grades | Grade/progress views | Grade summaries | Grade summaries | Grade projections | CorePlatformTest + cross-channel tests |
| Announcements | Published inbox/read state/preferences | Channel notification delivery | Channel notification delivery | Notification delivery/receipt contract | Notification worker + Stage 7 reliability tests |
| Forms | Scoped form routes | Website continuation where no bot-safe write exists | Website continuation where no bot-safe write exists | Forms service | Web integration contracts |
| Resources / learning | Library, detail and access states | Resource hub/detail/access handoff | Resource hub/detail/access handoff | Content + entitlement + delivery services | Content/learning bot tests |
| Assessments | Assessment metadata and attempts | Safe catalog/detail or website continuation | Safe catalog/detail or website continuation | Exam/attempt services | Content engine + bot journey tests |
| Orders / payments | Server-priced catalog/order/payment states | Order/access projection or website continuation | Order/access projection or website continuation | Commerce/payment verification | Stage 7 commerce tests; real provider remains policy-gated |
| Entitlements | Access library and reauthorization | Access marker and protected-delivery gate | Access marker and fail-closed protected gate | Entitlement service | Protected-delivery and tenant tests |
| Protected content | Signed/private object delivery | Protected Telegram send when capability permits | Fail-closed when protection cannot be enforced | Secure delivery + object capability | Protected-media worker tests |
| Notifications | Personal inbox/read/preferences | Lease/send/receipt/retry | Lease/send/receipt/retry | Notification delivery service | Durable receipt/retry tests |
| Search | Scoped, source-rechecked search | Website handoff | Website handoff | Workspace search service | Web integration contracts |
| Representative/admin | Existing scoped website controls | No shadow mutation surface without internal safe endpoint | No shadow mutation surface | Backend RBAC and audit | Management handoff + RBAC tests |
| Owner Update Server | Platform control-plane surface | Private Telegram only, capability-gated | Never exposed | Deployment control/updater | Deployment-control + Stage 7 tests |

Provider packing may differ, but meaning, authorization, payment/access facts,
notification receipts and protected-delivery policy remain shared.

## Validation performed

- Python deterministic suites: UX-v3 52, UX-v2 bots 25, bots 78, workers 13.
- Node web/UI contracts: all runnable contract and UX files passed.
- PHP static runner: 261 assertions passed with `fileinfo`, `mbstring` and
  `pdo_mysql` loaded explicitly.
- Stage 8 closure, text, secret/runtime-state guards and PHP lint (83 files)
  passed.
- The local MySQL integration suite was not runnable because Docker Desktop's
  Linux engine was stopped; the production updater performs its own guarded
  database preflight, backup, migration and health gates.

## Observed runtime/deployment evidence

- The updater request completed `SUCCEEDED`; current, candidate and active
  pointer all resolve to `112a7af90e35c4d1ef3c143b7ad37adb4fe66c41`, with the
  previous release retained as rollback target.
- The latest SQL/object backup
  `20260911T091851Z-0abe696a` passed independent manifest verification.
- All 12 canonical migrations are applied; no legacy import batch was run.
- Platform readiness and HTTPS `/health` both returned `ok` with the exact
  active release; the public RTL home returned HTTP 200. Browser DOM smoke
  found no console errors.
- PHP-FPM, Nginx, MySQL, Telegram egress, Telegram, Bale, notification
  projector and protected-media services are active with no Fanoos restart
  loop. Telegram `getMe` plus plain, rich/edit, fallback and protected fixture
  smoke all passed. Bale identity/configuration health passed, but no live Bale
  send was attempted because no active Bale link/test destination exists.
- Production notification messages and protected-media jobs were empty at the
  observation point, so no real delivery/derivative fixture was fabricated.
- Payment initiation remains disabled by policy; no financial transaction was
  attempted.

No live provider message or production data migration is inferred from a
repository test. The observed gaps above keep the final release status
`PARTIAL`; the previous immutable release remains the rollback target.

## Final status

`PARTIAL — synchronized release is deployed and healthy; Bale delivery,
notification/protected-media fixtures, payment provider validation and legacy
snapshot reconciliation remain intentionally unexecuted.`

Payment initiation remains disabled unless a real provider is configured and
verified. Legacy normalized import remains a supervised, snapshot-based step;
Git and bot caches are never treated as production data.
