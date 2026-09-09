# FANOOS UI V2 — Final Backend/UI Gap Classification

Classification follows Prompt 3: **A** presentation-only, **B** existing canonical contract/consumer missing, **C** missing noncritical projection/defer, **D** missing core-blocking projection/implement minimally, **E** unsupported/fake feature/remove.

## Resolved in integration

| Source gap | Class | Resolution |
| --- | --- | --- |
| Bot complete course/enrollment list | D | Added an authorized canonical course projection from `academic_courses`/offerings to the existing bot academic read projection. Final Telegram/Bale runtime consumes that projection; it no longer derives course truth from schedule/grade/resource activity. No DB migration. |
| Web browser binary handoff | D | Added `SecureObjectDownloadService` and versioned core-v1 `POST /workspaces/{workspaceId}/downloads/consume`. POST+CSRF capability redemption reauthorizes current resource entitlement and exact resource-version/object before same-origin binary streaming. No storage key/capability URL. |
| Web assessment catalog/detail attempt wiring | B | Existing ExamService start/save/submit/review contracts are now consumed by UI V2. Start sends only safe questions; revision is server-owned; submit/scoring and review are backend canonical. No local answer key/scoring authority. |
| Schedule day-boundary mismatch | A/B | Website request dates now originate in workspace timezone and public backend date-only bounds are resolved with `tenant_workspaces.timezone_name` before UTC query. Bot already used canonical timezone. |
| Cross-channel `pending` wording mismatch | A | Normalized `pending` → `در انتظار`; `payment_pending` → `در انتظار پرداخت`. |

## Deferred canonical projections

| Gap | Class | Current safe behavior | Required future contract if product needs it |
| --- | --- | --- | --- |
| Durable personal notification inbox | C | Website does not invent an inbox; bots distinguish push delivery from durable history | user/workspace-authorized notification-history projection with read state/cursor |
| Purchasable product catalog | C | Web Purchase/Access shows canonical orders; bots hand off to Web; no product UUID field | eligible product/price/currency/access catalog, backend eligibility and immutable displayed price snapshot |
| Bot-native assessment attempt/result | C | Bots show assessment destination and safe Web CTA; never score locally | bot-safe catalog/detail/start/resume/save/submit/result projection with answer-key protections |
| Course-scoped server filters for bot schedule/grades/resources | C | bounded authorized projections can be presentation-filtered by canonical `course_id` | native `course_id` server filters for scale/pagination completeness |
| Course-scoped announcements | C | announcements stay workspace-level; no inferred course binding | canonical course/offering binding in announcement projection |
| Self entitlement/access list | C | access decision appears only in specific resource/order contexts | subject-scoped entitlement list/status projection |
| Form submission history/results | C | active forms and canonical submission only | self submission/history/result projection |
| GPA/weighted grade summary | C | score/max only; no fabricated GPA/average | authoritative completeness/weighting/summary projection |
| Instructor/offering presentation completeness | C | show only provided metadata | optional canonical instructor/offering fields |
| Full Website linked-channel management | C | account does not fabricate linked state | public linked Telegram/Bale state + revoke/challenge management projection |
| Resume an in-progress assessment after full browser reload | C | current in-page attempt maintains server revision; refresh does not fabricate resume state | own in-progress attempt projection keyed to assessment/user |

## Explicitly unsupported / removed from primary UX

| Feature | Class | Decision |
| --- | --- | --- |
| Bot `/buy <product_uuid>` as normal product UX | E | retained only as backward-compatible technical path; not advertised. Normal flow uses Purchase/Access and safe Web handoff. |
| Local bot course database/truth | E | prohibited; integration runtime consumes canonical projection. |
| Local/browser assessment scoring | E | prohibited; canonical server scores. |
| Durable inbox reconstructed from bot delivery receipts | E | prohibited. |
| Bale unprotected fallback for forward-protected resource | E | prohibited; fail closed. |
| Website/Bale deployment control | E | not part of UI V2. |

## Contract delta

- `contracts/openapi/core-v1.yaml` is version **1.2.0** for additive secure download redemption and explicit canonical schedule date semantics.
- Existing internal-v1 academic schedule endpoint remains the authorized bot transport and now carries the canonical bounded course projection consumed by integrated Bot UI. The route/service authorization model is unchanged; no new service secret/action is introduced.
- No database migration was required.
- No new runtime secret was introduced. Secure download uses the existing object-storage root and download signing key contracts.

## Rule for future work

A missing UI destination is not justification for a new datastore or duplicate backend. Add a projection only when a concrete user journey is blocked, scope it to the canonical domain service, authorize it server-side, version/document it and test tenant isolation.
