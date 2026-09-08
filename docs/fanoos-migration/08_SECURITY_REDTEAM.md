# Stage 8 — System-Wide Security / Red-Team Closure

Status: **repository-side deterministic gates PASS; provider/server/browser attacks remain runtime validation where indicated**.

This review treats the system as one product: Platform, SQL tenant model, Web, Telegram, Bale, storage, protected-media, commerce, notification delivery, update control, backup and migration. Passing a unit test is not recorded as live evidence.

## Tenant / identity / authorization

| Attack / failure | Repository evidence | Result | Runtime remainder |
| --- | --- | --- | --- |
| Cross-workspace IDOR | composite workspace FKs, `AccessGate`, tenant integration tests | PASS | authenticated staging probes with two real workspaces |
| Forged workspace context | backend-resolved selected workspace + scope checks; UX stabilization removed sessionStorage truth | PASS | live cookie/bot context smoke |
| Same-name tenant collision | scoped unique keys + Stage8 explicit workspace mapping | PASS | source mapping review |
| Representative privilege escalation | scoped role templates; deployment permission absent; tests deny representative | PASS | production RBAC inventory |
| Workspace admin → platform escape | platform scope separate; `deployment.manage` platform-only | PASS | operator-role review |
| Inactive/revoked membership | access gates/membership status checks | PASS | real revoked-account smoke |
| Stale session | canonical session expiry/revocation model | PASS | runtime cookie/session TTL verification |
| Service-auth replay | nonce/timestamp HMAC service auth tests | PASS | clock skew/process restart observation |
| Messaging-link replay | one-use challenge/link tests + subject-bound unlink | PASS | provider identity smoke |
| Wrong channel identity | platform+subject binding; Telegram/Bale state separated | PASS | live channel relink smoke |
| Foreign resource/grade/search | workspace-qualified queries + tests | PASS | staging IDOR corpus |
| Announcement/form scope | same-workspace FKs/access permissions | PASS | staging role matrix |
| Admin/export scope | scoped authorization is canonical; no role boolean bypass | PASS_WITH_RUNTIME_VALIDATION | any production export endpoint must be probed with least privilege |
| Legacy import tenant forgery | explicit `workspace_map`, deterministic ID, target workspace preflight, reconciliation | PASS | actual source mapping evidence |

## Commerce / payment / entitlement

| Attack / failure | Gate | Result |
| --- | --- | --- |
| Client price tampering | server-side product/price version selection and order snapshots | PASS |
| Currency tampering | canonical order/price currency; bundle preserves explicit currency | PASS |
| Duplicate order/request | idempotency keys + unique constraints | PASS |
| Duplicate callback/replay | payment/provider idempotency + canonical verification state | PASS_WITH_RUNTIME_VALIDATION |
| Mismatched order/user/workspace | same-workspace references/access checks | PASS |
| Failed/abandoned payment | cannot become `verified`/entitled | PASS |
| Forged client success | client/bot UI cannot write verified payment | PASS |
| Migration of legacy “success” flag | Stage8 validator requires provider evidence for `verified` | PASS |
| Reconciliation retry | idempotent batch/order/payment semantics | PASS |
| Entitlement from UI | prohibited; entitlement is separate canonical record | PASS |
| Revoked/expired entitlement | authorization re-evaluates current grant | PASS |
| Protected delivery after entitlement change | issue/consume and derivative authorization immediately before delivery | PASS |
| Real payment provider behavior | no fake adapter may be promoted to production proof | RUNTIME_VALIDATION_REQUIRED / product decision |

A real payment provider adapter and credentials are a production prerequisite only if paid commerce is enabled at initial cutover. Without that evidence, payment initiation stays disabled; the rest of Fanoos may still be staged/released without fabricating a successful provider.

## Content / storage / protected media

| Attack / failure | Repository evidence | Result | Runtime remainder |
| --- | --- | --- | --- |
| MIME spoof / active upload | `UploadInspector` detects MIME and rejects active content | PASS | host fileinfo parity |
| Oversize | explicit byte limits; tests | PASS | web/proxy/PHP limits |
| Client path traversal | basename/display metadata only; generated private object key | PASS | target filesystem permissions |
| Object-key injection | generated/namespaced keys; Stage8 migration key guard | PASS | object-store policy |
| Checksum mismatch | object put idempotency + backup/object manifest + migration evidence | PASS | real object-copy verification |
| Partial finalize | canonical verified status requires checksum/size; worker completion contract | PASS | crash injection live rehearsal |
| Unsafe active HTML | upload rejection / escaped web rendering | PASS | browser CSP/header observation |
| Unauthorized signed download | tenant-bound signed token tests | PASS | HTTPS smoke |
| Expired capability | expiry checks | PASS | clock/runtime smoke |
| Foreign-tenant capability | workspace binding | PASS | live two-tenant probe |
| Malformed/truncated PDF | protected-media parser/raster bounds tests | PASS | real qpdf/poppler behavior |
| Page/resource bomb | bounded pages/bytes/time | PASS_WITH_RUNTIME_VALIDATION | host CPU/memory limit rehearsal |
| Embedded active content | rasterized protected derivative path; no original fallback | PASS_WITH_RUNTIME_VALIDATION | toolchain sample corpus |
| Temp cleanup | worker bounded private temp state | PASS_WITH_RUNTIME_VALIDATION | crash/restart filesystem inspection |
| User A derivative reused for B | personalized artifact/capability binding | PASS | live two-user fixture |
| Final derivative authorization | derivative capability issued/redeemed after canonical auth | PASS | live entitlement revoke test |
| Telegram `protect_content` | adapter capability set; one provider operation | PASS | live Telegram document smoke |
| Bale forward protection unavailable | explicit fail-closed capability downgrade | PASS | live Bale response smoke |

No document, watermark, fingerprint, rasterization, bot flag or signed URL is described as absolute DRM. These controls deter leakage and enforce authorized delivery; a recipient can still capture content outside the software boundary.

## Update control plane

| Attack / failure | Repository evidence | Result |
| --- | --- | --- |
| Normal user/representative update | denied in `DeploymentControlTest` | PASS |
| Owner/operator path | explicit platform role and signed Telegram subject | PASS |
| Non-private Telegram | adapter gate; backend permission remains independent | PASS_WITH_RUNTIME_VALIDATION |
| Expired/two-step confirmation | bot UX contract | PASS |
| Duplicate request | durable idempotency | PASS |
| Concurrent update | target advisory lock + active-request uniqueness | PASS |
| Arbitrary branch/ref/SHA/remote | request contract has no such fields; executor resolves canonical `origin/main` | PASS |
| Shell/path injection | no command/path payload; fixed hooks | PASS |
| Failed backup | activation blocked | PASS |
| Failed tests | activation blocked | PASS |
| Migration precheck failure | preflight gate | PASS |
| Failed health | durable rollback state, application pointer rollback only | PASS |
| Bot restart mid-update | durable request/event state; status refresh process | PASS_WITH_RUNTIME_VALIDATION |
| Secret/raw logs in bot UI | safe finite fields/failure codes only | PASS |
| Real deploy credential/runner | outside web/bot env by design | RUNTIME_VALIDATION_REQUIRED |

## Migration attack surface

Stage8 normalized migration rejects secret/runtime field names recursively, unsupported entity types/raw table choice, forged deterministic target IDs, duplicate source identities, missing workspace mappings, direct Telegram/Bale IAM identifier migration, verified payments without evidence, order entitlements without a verified same-workspace payment row, and unverified/unsafe content object metadata. Dry-run must not mutate the migration ledger. Apply is additionally gated by `FANOOS_MIGRATION_APPLY_CONFIRMED=1` and uses one domain transaction per bundle; an apply failure records only redacted digest/index evidence outside that rolled-back transaction.

Production bundles and source exports are private runtime artifacts, never Git inputs. Source row keys are HMACed before persistent migration mapping/result storage.

## Remaining live red-team corpus

The one-time staging rehearsal must execute the same two-tenant/role/payment/storage/provider/update scenarios against actual HTTPS endpoints and real Telegram/Bale test identities. It must include at least one expired/revoked membership, foreign UUIDs for grade/resource/search/form, malformed PDF samples, protected delivery revoked between issue and final send, bot process restart around a notification/update, and updater failure injection before and after activation. Evidence must contain timestamps, exact release SHA, safe response/status codes and redacted logs; screenshots/log lines alone never override failed machine checks.

## Release decision

Repository-wide red-team status: **PASS_WITH_RUNTIME_VALIDATION**. No Critical/High source defect remains known. Any live tenant leakage, arbitrary-update capability, unverified payment→entitlement path, protected-content authorization bypass, backup/restore failure, or migration reconciliation mismatch is an immediate production `BLOCKED` condition.
