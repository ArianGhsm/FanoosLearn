# FANOOS Bot V3 — Screenbook Acceptance

Design Lock: `FANOOS-UX-2026.09-R1`

Scope: deterministic source acceptance for the canonical Telegram + Bale V3 product surface. Live provider behavior remains a deployment-stage validation item.

## Canonical screen families

| Surface | Canonical source | Telegram | Bale | Acceptance |
| --- | --- | --- | --- | --- |
| Unlinked / onboarding | bot-01 core | Rich RTL when supported + plain fallback | Provider-native text | Accepted by source contract |
| Linked, zero workspace | bot-01 core | Workspace + Account + Help + Website + Home | Same semantic actions within Bale capability | Deterministic coverage |
| Workspace picker | bot-01 core | Explicit selection; bounded callback | Explicit selection; bounded callback | Deterministic coverage |
| Active Home | bot-01 core | V3 semantic Home | V3 semantic Home | Empty vs unavailable tested |
| Courses | bot-02 academic | Canonical `schedule.courses` projection | Same projection | Pagination + raw-ID hiding tested |
| Course detail | bot-02 academic | Schedule / Resources / Grades only | Same semantic availability | Unsupported assessment/course-announcement actions hidden |
| Schedule | canonical Stage 7 business + V3 provider path | Workspace timezone | Workspace timezone | Existing + integration regression coverage |
| Grades | canonical Stage 7 business + V3 provider path | Published self-grade only | Published self-grade only | No GPA/average invention |
| Announcements | canonical Stage 7 business + V3 provider path | Published canonical items | Published canonical items | Existing regression coverage |
| Notifications | canonical Stage 7 business + V3 provider path | Personal delivery semantics only | Same semantics | No fake durable inbox |
| Resources | bot-03 + canonical delivery authority | Safe metadata + secure continuation | Safe metadata + safe continuation | Raw object/storage data hidden |
| Protected text | canonical delivery authority + bot-04 provider policy | Exactly one protected provider operation | Protected original refused when equivalent protection is unavailable | Deterministic coverage |
| Protected PDF | canonical Stage 7 derivative path | Protected document send | Fail closed | Existing protected-media regression coverage |
| Assessments | bot-03 safe capability surface | Web continuation without local scoring | Same | No bot-local attempt/scoring authority |
| Commerce / access | bot-03 + canonical commerce | Order/payment/entitlement kept distinct | Same | Deterministic fact separation |
| Forms | safe handoff | Website continuation | Website continuation | No bot-local submission authority |
| Account / unlink | bot-01 + canonical account authority | Safe account state and unlink | Same | No raw provider subject exposure |
| Owner management | canonical deployment authority | Private Telegram + `deployment.manage` only | Not exposed | Existing + V3 gating tests |
| Error / expired route | integration router | Safe recovery | Safe recovery | No backend internals in copy |

## Cross-channel invariants

- Telegram and Bale consume the same semantic screen meaning; provider renderers may differ only where platform capability requires it.
- Presentation state never creates membership, role, grade, entitlement, payment proof, content authorization or deployment permission.
- Callback payloads are navigation/correlation only. Oversized state is stored behind expiring platform+subject-bound route references.
- Provider rendering never replays the underlying business mutation/read after a render/edit failure.
- Raw UUIDs are not product copy. IDs used for correlation remain inside callbacks or server-side route state.
- Protection is fail-closed. No protected original is downgraded to an unprotected fallback.
- Telegram non-protected Rich failure may fall back once to the same already-computed semantic result.
- Bale contains no Telegram-only rich payload fields and no Update Server surface.

## Deterministic evidence

The central Stage 7 bot runner includes the Bot V3 regression suite. It covers at minimum:

- explicit workspace selection and zero-workspace shell completeness;
- subject-bound opaque route references and callback byte bounds;
- canonical course projection, course pagination and raw-ID hiding;
- active Home empty/unavailable differentiation;
- Telegram RTL rich rendering for V3 non-protected screens;
- protected Telegram atomicity and Bale fail-closed behavior;
- legacy protected transport migration compatibility without business replay;
- owner-management provider gating;
- order/payment/entitlement separation;
- secondary legacy screen compatibility during V3 migration;
- Web/Telegram/Bale semantic parity assertions.

## Deferred live acceptance

The following are explicitly **not** claimed by source acceptance and remain mandatory after deployment:

- live Telegram rendering/callback smoke;
- live Bale rendering/callback smoke;
- real Telegram forward-protection behavior;
- protected-media end-to-end delivery on production provider/runtime;
- live owner-management private-chat verification;
- live browser/provider cross-channel smoke.
