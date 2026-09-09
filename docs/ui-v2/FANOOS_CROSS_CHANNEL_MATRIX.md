# FANOOS UI V2 — Cross-Channel Matrix

Parity means the same canonical concept, source, permission and business outcome. Presentation density and provider capability may differ.

| Concept | Canonical source | Website | Telegram | Bale | Permission | Parity | Channel limitation | Test evidence |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Workspace | account/membership + messaging link | selector/account | linked workspaces/select | same | active membership | Full semantic parity | selected workspace is channel-local UX state only | tenant/isolation tests + cross-channel fixture |
| Home / Today | workspace + schedule + announcements | rich home cards | compact home/today | compact home/today | downstream permissions | Same facts/priorities | partial failures may reduce cards | Web V2 + bot UX + parity fixture |
| Course | `academic_courses` / authorized academic projection | canonical course list/detail | integrated canonical course projection | same | `academic.view` | Full first-class identity parity | bot deep detail may link Web | `test_cross_channel_parity.py`; existing Web course tests |
| Schedule | `schedule_events`, workspace timezone | calendar/timeline | today/tomorrow/7-day | same | `academic.view` | Full fact/timezone parity | list density differs | `ScheduleWindowResolver`, MySQL CI, parity fixture |
| Grades | published grade results | grouped course rows | compact rows/course grades | same | `grade.view_self` | Full published fact parity | no GPA without canonical summary | core/bot integration + parity fixture |
| Announcements | published notification messages/recipients | durable Web announcement view | announcement list | same | `notification.receive` | Full broadcast parity | no trustworthy course filter until binding exists | parity fixture + notification tests |
| Personal notifications | notification events/delivery infrastructure | no fake durable inbox | pushes + hub explanation | same | recipient authorization | Explicit partial parity | no canonical history projection | gap classification; bot UX tests |
| Resources | content catalog + access policy | library/detail | library/detail | same | `resource.view` + authorization | Metadata/access parity | delivery differs by channel | content engine + parity fixture |
| Protected delivery | SecureDelivery + entitlement/access | issue→consume→same-origin secure binary redemption | protected provider send/derivative path | fail closed when forward protection required | resource access + entitlement | Same authorization/outcome policy | Bale lacks verified required forward protection | Stage 7 content/media tests + parity fixture |
| Assessments | ExamService | catalog + start/save/submit/review; server scoring | IA destination + safe Website handoff | same | `exam.take` + policy/entitlement | Semantic destination parity; interaction capability partial | no bot-safe native attempt projection | Web V2 wiring + content engine + parity fixture |
| Question bank / past exam | resource and/or assessment kind | resource/assessment filters | localized destination when projected | same | relevant resource/exam permission | Source semantics shared | native bot assessment interaction deferred | Web/bot presentation tests |
| Orders | commerce order history/create contracts | order history; no raw product-ID UX | technical legacy create path retained only for compatibility; normal UI hands off Web | same | `commerce.purchase` | State semantics shared | canonical browsable catalog missing | commerce tests + parity fixture |
| Payment | provider callback/reconcile canonicality | backend status only | status only | same | purchase/reconcile as applicable | Full truth-source parity | bots do not prove provider success | Stage 7 commerce tests + parity fixture |
| Entitlement | entitlement grants/access policy | independent access state | independent access state | same | entitlement/access checks | Full truth-source parity | no complete self-service access-list projection | entitlement/content tests + parity fixture |
| Account / identity | IAM session / messaging links | canonical session account | linked platform subject | same | own identity | Same human identity | username/display name never identity proof | linking/service-auth tests |
| Role management | RBAC projections/actions | supported permission-aware management | only bot-safe supported actions | same | action-specific RBAC | Authorization parity | channel surface may be absent | CorePlatform/TenantIsolation tests |
| Update Server | release control plane | absent | private Telegram owner/operator only | absent | `deployment.manage` | Intentionally asymmetric | Telegram-only operational capability | deployment tests + cross-channel owner test |
| Forms | form definitions/submissions | native active form submission | no first-class V2 native form flow | same | form permissions | Canonical backend shared | bot fallback not promoted as fake native feature | core Web tests; deferred bot surface |
| Search | canonical workspace search | native search | not first-class V2 bot surface | same | source-specific authorization | Backend semantics shared where surfaced | Website-only presentation currently | Web tests; documented channel limitation |

## Security invariants compared by parity tests

- foreign workspace does not become accessible through another channel;
- inactive/unauthorized content is not reconstructed from local bot state;
- protected content follows the same entitlement decision; Bale fails closed rather than downgrading protection;
- payment pending never grants entitlement;
- raw UUID/storage/provider identifiers are not normal user copy;
- deployment controls remain absent from Website/Bale and public Telegram contexts.
