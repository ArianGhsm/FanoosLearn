# Residency 1402 — verified exact-original PDF page repairs and scope holds

**Audit date:** 2026-10-11 (UTC). **Scope:** dental residency, 1402, round 1; production FANOOS. This is a durable, reproducible record of an actual guarded metadata-only database operation, distinct from an assessment republication. Full scientific credit still requires individual official-syllabus checks.

## Four source-page repairs applied and independently read back

The live bank initially had 250 total questions, 75 on-record complete under the owner's atomic SAME-SOURCE requirement: official chapter node, pdf_page, and pdf_sha256. Twenty English questions have final official answer choices and are book-reference exempt. This work added four previously NULL bound PDF page/hash pairs without changing any question, answer, choice, source edition/node, legacy page/printed page, evidence anchor, human decision or attempts. Production read-back after the second batch: **79/250 field-complete**, 171 raw field-incomplete, **151 non-English entries lacking complete page citations** after 20 exempt English (some additionally require official-scope review).

| Question | Exact edition | Chapter | PDF page | Printed page | Evidence on that same original page |
|---|---|---:|---:|---:|---|
| 64 | Neville Oral and Maxillofacial Pathology 4e (2016) | 5 | 191 | 184 | Histological actinomycosis colony with club-shaped filaments in a radiating rosette |
| 65 | Neville 4e | 6 | 202 | 195 | Chronic hyperplastic candidiasis described as the least common form |
| 66 | Neville 4e | 9 | 317 | 310 | Sarcoidosis, whose chronic violaceous lesions are termed lupus pernio |
| 73 | Neville 4e | 13 | 557 | 550 | Langerhans cell histiocytosis, including eosinophilic granuloma |

The approved on-host original **Neville 4e** PDF is 878 PDF pages, SHA-256:
6fbc9bcba9003deda2f8fc006ccc4f23f9e15db0bcd0e237c56ddf6788578bb6.
All four cited pages were read directly through verified_reference_pdf.py; chapter-page runs and current hash were checked, and PrintedBookPageEvidence::corroborates plus pageContainsEvidence independently passed for each. Their chapters are included in the official 1402 Neville 4e scope. Old source anchors may contain historical *nearest Neville 5e* wording: **this wording was preserved**, not silently rewritten, and is not substituted for the original 4e evidence.

**Batch A: Q65.** SHA-pinned private plan:
classification/reports/residency-1402-q65-exact4e-page-repair-plan-20261011.json
(plan SHA-256 4ab9d237d335cc00f59ce0e102755ba17fda82824c1690bbadf243e0ba14d5ae).
Rollback-only live preview accepted 1; independent post-preview source read still returned NULL page/hash.
Fresh verified backup /var/backups/fanoos/20261011T143835Z-3e7ca9e5 (114 verified files).
Atomic applied receipt:
classification/reports/residency-1402-q65-exact4e-page-repair-applied-20261011.json
SHA-256 abbbbee88bbb444f8c4ef9e47863fba31c548d7393a400289736c23de00fc01f.
Live after-apply exact source read: chapter 6, printed 195, PDF 202, PDF hash matches and unchanged old anchor digest.

**Batch B: Q64/Q66/Q73.** Private plan:
classification/reports/residency-1402-neville4e-q64-66-73-page-repair-plan-20261011.json
(plan SHA-256 784a500f2a1b8ca13951cd19517edf458f3552346596a08bb77799c7eb1a6745).
Rollback-only live preview accepted all 3 and independent post-preview rows still had NULL PDF pages.
Fresh full backup *after Q65 publication* /var/backups/fanoos/20261011T145027Z-7b8d3d3e independently verified (114 files).
Atomic applied receipt:
classification/reports/residency-1402-neville4e-q64-66-73-page-repair-applied-20261011.json
SHA-256 485322fb88129de740bd1585d20c22b207179b298388a92c6e27d05167d6c0fb.
Exact live read-back: Q64 ch5 PDF191, Q66 ch9 PDF317, Q73 ch13 PDF557, unchanged old printed pages, AI origin and unreviewed flags. The independent site health returned status ok with release cd3b7913640dcb6ccdcb471e637e9e3236b89c4e.

The shipped versioned code is scripts/references/repair_verified_source_pages.php, CI-tested in GitHub PR #308 and deployed via the official updater with an independently verified backup and site health. The operator changes ONLY bank_question_sources.pdf_page and bank_question_sources.pdf_sha256 and rejects a missing official chapter scope, a partial-scope citation, an already-valued page/hash, a human-reviewed source, an original PDF/printed-label/quote mismatch, or a changed source anchor. Both input plans and receipts are protected and mode 0600, not in public Git.

## Official-scope audit: 29 legacy rows requiring separate review

An independent read-only join of the same **1402 residency** 224 pre-existing source rows to the official 1402 reference-validity records and their structured scope_chapters lists found **29** rows whose recorded chapter number is absent from the corresponding official chapter list. This is a *structured-scope mismatch screening result*; original notices/partial limits may require correction before a final adverse scientific judgment. Do not silently relabel chapters, erase older citations or count the eight already PDF-bound rows as independently in-scope.

| Subject | Question numbers needing review | Total | Already PDF-bound |
|---|---|---:|---:|
| Dental materials | 243, 244, 245, 246, 247, 248, 249, 250 | 8 | 0 |
| Operative dentistry | 174, 176, 177, 179, 182, 185, 189 | 7 | 7 |
| Oral medicine | 49, 51, 52, 54, 60 | 5 | 0 |
| Oral pathology | 61, 62 | 2 | 0 |
| Pediatric dentistry | 167 | 1 | 0 |
| Periodontics | 138, 139, 148 | 3 | 0 |
| Prosthodontics | 102, 103, 121 | 3 | 1 |
| **Total** | | **29** | **8** |

The 8 PDF-bound out-of-list records demonstrate why same-row field presence (79/250 after this work) is not the same as independent exact-year scope validity. Oral pathology Q61 and Q62 refer to chapters 1 and 2, explicitly excluded in the 1402 official Neville 4e chapter list. Do not generate pages for them absent official resolution.

Reproduce with the bank_question_sources join to bank_questions / bank_exam_sittings / bank_exam_types / bank_subjects / bank_reference_editions / bank_reference_nodes and a LEFT JOIN bank_reference_validity ON the identical edition, subject, exam type and 1402 year with is_official=1. A legacy out-of-list chapter is one for which validity is NULL, structured scope_chapters is NULL, or JSON_CONTAINS(scope_chapters,JSON_OBJECT('number',reference_nodes.number))=0. This is screening; the exact official printed partial-page rules still need individual book-page verification.

## Assessment publication and remaining queue

The full 1402 frozen assessment version **5** had **243 questions**. Read-only prepublication audit via scripts/references/audit_published_assessment.php with the protected 1402 radiology study confirmed all 243 proposed question definitions are identical to the frozen version **outside the source-derived explanation** (20 independently matched study questions). Do not assert a new exam version unless the official publisher actually completes and a full v5-v6 diff validates, preserving previous attempts and answer keys.

Remaining book-citation backlog after the four source-row repairs: **145** existing chapter-only rows with no bound original PDF page and **6** source-free oral-radiology rows; 20 English final-key reference exemptions remain complete without an invented citation. Q175 operative remains pending exact original printed-page and partial official chapter-9 scope verification. Continue with other eligible original editions and skip false/out-of-scope mappings. Follow docs/ops/VERIFIED_SOURCE_PAGE_REPAIR_20261011.md and docs/product/09_CHAPTER_CLASSIFICATION.md.

**Final status:** Four guarded 1402 source-page repairs applied and audited in live bank; assessment version 6 subsequently published and the complete 243-question frozen-version diff passed. Other book-citation and official-scope holds remain pending.


## Final controlled student-assessment publication (verified)

On 2026-10-11 after all four source repairs, a third official full production backup
`/var/backups/fanoos/20261011T145712Z-e0f10040` was independently verified
(114 manifest files), and a new complete read-only publication preflight against
frozen **assessment version 5** returned **243/243** identical non-source
question definitions; all 20 protected radiology-study questions also matched.

The authorized existing owner publication path
`scripts/import/import-bank.php publish --type=residency --year=1402 --round=1`
then **successfully published version 6**, not a separate exam. Exactly **243**
scorable questions remain and the seven official-voided Q60, Q96, Q99, Q102,
Q103, Q144, Q181 remain excluded from scoring. The authoritative
postpublication audit `audit_published_assessment.php --previous=5 --current=6`
returned:

- `question_content_identical: true`, **243/243** questions, same order,
  prompt, choices, answer, and every other frozen non-explanation field.
- **145 source-derived explanation fields changed.** This number is not
  a claim of 145 new page assignments: the new version hides previously
  displayed legacy chapter citations that lack a same-row bound PDF page.
  Exactly **145** currently chaptered 1402 source records remain without
  `pdf_page/pdf_sha256`, matching the aggregate size of that affected
  cohort; the per-question explanation differences should still be reviewed
  before attributing every edit individually.
- Assessment version **5 still exists** with its historical SHA-256 and
  version **6** is the current `published` version. The database read-back
  confirmed version 6, alongside the unchanged total **79/250** strict
  recorded-complete chapter+PDF-page source rows.
- Public `/health` returned `status: ok` at deployed release
  `cd3b7913640dcb6ccdcb471e637e9e3236b89c4e` after publication.

All four source repairs and their original PDF page/hashes were already
applied before this independent full-backup-and-reviewed-publication step.
These steps did **not** edit the user-entered question text, options or
official keys, and the old frozen student-assessment version remains intact.
The backlog is **151 non-English page/scope cases** plus the separate
29-row official-structured-scope review queue (overlapping with the backlog,
not additive). No further 1402 source page edits are claimed in this record.
