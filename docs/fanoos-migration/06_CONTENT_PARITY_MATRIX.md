# Prompt 6 content parity matrix

Legend: **Implemented** is covered by FANOOS code/tests; **Contract** has a fail-closed interface but needs a worker/provider; **Deferred** is an explicit product or migration decision.

| Capability | Status | FANOOS evidence | Remaining condition |
| --- | --- | --- | --- |
| Common note/resource aggregate | Implemented | `ContentService`, resource/version/metadata tables | Production-shaped load testing |
| Lecture note | Implemented | `lecture_note` type and integration workflow | Real licensed content import |
| DentNote/discipline format | Implemented | data-driven `discipline_note` plus arbitrary `format_key` | Final rendering schema/template approval |
| Summary product | Implemented | derived `summary` resource with lineage/review | External generator adapter optional |
| Cheat sheet | Implemented foundation | common `cheat_sheet` type | Product template/content fixtures |
| Flashcards | Implemented foundation | common `flashcards` type | UX renderer and content fixtures |
| Question bank | Implemented | `question_bank` resource plus assessment source link | Real canonical source/licensing review |
| Past/sample exam | Implemented | `past_exam` resource and assessment kind | Real historical catalog import |
| Generated practice/mock exam | Implemented | reviewed version, catalog, attempts and scoring | Timer/advanced analytics if required |
| Audio/source resource | Implemented foundation | inspected object and `audio` type | Worker transcription/rendering contract |
| Source → note → summary/questions pipeline | Implemented | `content_derivations`, immutable generated versions | Automated provider adapter optional |
| Independent review/publish | Implemented | review evidence, self-review denial, publish gate | Operational reviewer assignment |
| Version update/supersession | Implemented | immutable versions and atomic current pointer | Retention policy |
| Library search/filter/sort | Implemented | tenant-scoped library endpoint and RTL UI | Search worker/ranking at scale |
| Upload/storage/checksum | Implemented | Prompt 4 inspector/store composed by `ContentUploadService` | Production storage root and quota monitoring |
| Entitlement-protected access | Implemented | Prompt 5 authorizer plus entitlement recheck | Live payment adapter remains Prompt 5 follow-up |
| Signed delivery and access logs | Implemented | issuance/consume token, object token and delivery events | Production keys outside Git |
| Watermark/tracing identity | Implemented | visible label and issuance-bound forensic HMAC | Byte-level renderer is Prompt 7 worker work |
| Telegram/Bale forward protection | Contract | `forward_protection_required` response | Prompt 7 bot must enforce platform flag |
| PDF raster fingerprint/attack suite | Contract | isolated rendering requirements documented | Worker/toolchain and approved legacy algorithm adaptation |
| Bulk content import | Implemented | manifest CLI, HMAC mapping, replay/duplicate/update ledger | Approved source snapshot and mapping rehearsal |
| Cross-tenant isolation | Implemented | same-name course/resources, foreign keys and denial tests | Prompt 8 red-team/rehearsal |
| Public anonymous resource policy | Deferred | not enabled by default | Explicit per-tenant product/security decision |
| Favorites/issue reports/open analytics | Deferred | not needed for secure core delivery | Product priority and retention/privacy policy |
| HTML sandbox hosting | Deferred | active content rejected | Separate origin/sandbox/abuse design if approved |

## Acceptance conclusion

Prompt 6 completion criteria are met locally: multiple resource types use one engine; content can be imported/mapped idempotently; secure access and traceable delivery work; exams/questions/summaries are generic; and DentNote remains a format rather than a discipline fork.

This matrix does not claim production deployment, real legacy data migration, live payment provider readiness or byte-level protected-PDF worker parity. Those require approved infrastructure, content/source authority and Prompt 7/8 operational validation.
