# Residency 1405 oral-pathology — edition-verified chapter and printed/PDF-page evidence, 2026-10-11

## Exact source provenance

Current official 1405 syllabus includes `neville-oral-pathology@5e` relevant chapters (with **chapter 7 excluded**). The **current approved private server PDF** has 983 pages and SHA-256 `4d35199b9cda526997717802e174144071d38f0179e725e7ed6a90b2f98565ac`. The current hash-pinned `reference-chapter-pages.json` chapter runs match this PDF. On six sampled printed book pages (129, 328, 434, 533, 709, 750), the printed-to-PDF offset is consistently **+10**. This is NOT enough to autofill all source rows.

Read-only inspection of Q41–Q60 (twenty pathology questions) checked each existing AI source's printed page against actual hash-pinned PDF page, neighboring printed page labels (at least two), official year chapter eligibility and exact normalized original anchor on the same PDF page. **Only seven** passed this initial scientific exact-source-evidence screen:

| Q | Valid official chapter | Original printed page | PDF page | Primary page validation | Independent provenance audit | Guarded preview / production |
|---:|---:|---:|---:|---|---|---|
| 43 | 6 | 222 | 232 | 1 accepted / 0 rejected | 1 accepted / 0 pending | preview passed; **applied** 19:13:15 UTC |
| 50 | 11 | 496 | 506 | accepted | accepted | preview passed; **applied** 19:13:49 UTC |
| 51 | 12 | 533 | 543 | accepted | accepted | **hold:** original stored source text fails the stricter live PHP quote+neighbor page proof; no live write |
| 54 | 14 | 639 | 649 | accepted | accepted | preview passed; **applied** 19:14:18 UTC |
| 57 | 15 | 722 | 732 | accepted | accepted | **hold:** original live exact-text evidence check failed; no live write |
| 59 | 16 | 750 | 760 | accepted | accepted | preview passed; **applied** 19:14:47 UTC |
| 60 | 16 | 770 | 780 | accepted | accepted | preview passed; as of this note **apply not confirmed** (remote command was blocked; do not count) |

Scientific evidence covers original immutable live stem, four choices and latest final official key, original AI source identity, source PDF and hash, chapter map, printed book page labels with neighboring corroboration, exact source fragment and official syllabus. None of these initial cases is a question/option/answer import.

Reproducible protected case folders, **one original-question study, decision, independently PDF-validated sitting and provenance audit each**, are located at

`/srv/fanoos/shared/research/classification/recovery-1405-pathology<Q>-20261011/`

where Q is 43, 50, 51, 54, 57, 59 or 60. For each Q, files are `bank-sittings/1405/pathology<Q>-study.json`, `classification/decisions/1405-oral-pathology.json`, `classification/sittings/1405-pathology<Q>-validated.json`, and `classification/reports/1405-pathology<Q>-audit.json`.

Audit SHA-256, independently checked before guarded preview:

| Q | Exact audit SHA-256 |
|---:|---|
| 43 | `6351ce16b6aead3f7d135febe6fbcd3497703973554660cd9ca10edd49034d2f` |
| 50 | `d144a9a1d6b6ed468d38bfcaa02b519597cbfad9c5382dbca528303425728239` |
| 51 | `e8f710c2ba139473ea3d69daa98e96d8dcdece9a30cfe23619e9aad160a1a341` |
| 54 | `8d05141ef063794b2d886b634628299e90164e1cf16c14c2337ec1bd9fd5fc70` |
| 57 | `b7424f311049d3fe19dda731ceb71a9192660acfe7046a7fb849e7ae7ffbaaa3` |
| 59 | `0f5f3fd5a18b88dcd31e03d75c9693bdbcdb098c23eca72d708b41722bf0e39d` |
| 60 | `fd3a3ec4239c4504a3fa09d69f7c4f9c38218fb610fd4fca13a3530edbe048ef` |

## Four successful guarded live writes

Live, deployed, ancestry-checked guarded operator `scripts/references/repair_existing_source_pdf_page.php --printed-offset` was used with original approved private PDF SHA and four separate independent audit digests. The official backup `/var/backups/fanoos/20261011T185643Z-5d8b2831` had previously passed independent `verify-backup.php` (**114 files**); it was still recent for the four apply operations. Each produced `applied:true`, exactly one existing `bank_question_sources` row changed, and **ZERO** edits to `bank_questions`, choices, answers, existing source chapter, human reviews or attempt history. They filled only `pdf_page` and `pdf_sha256`, preserving old printed/legacy page fields. Unique production receipt:

`/srv/fanoos/shared/research/classification/recovery-1405-pathology<Q>-20261011/classification/reports/1405-pathology<Q>-live-repair-20261011.json`

exists for **Q43, Q50, Q54 and Q59**; do not assume it exists for the other three.

Prior same-date successfully applied 1405 Q214, Q27, Q29, Q35, Q38 are in `RESIDENCY_1405_FIVE_LIVE_PDF_PAGE_REPAIRS_20261011.md`. Cumulative this-day operations are **nine independent successful single-row source-only commits** after the preexisting 83 recorded-complete baseline. An aggregate DB read-back for exact `residency:1405:1` (expected **92/250** exact-field-complete and 138 book-based incomplete) remains a separate required check; do not assert the expected number as measured without confirming it. In addition, row Q217 remains outside official scope despite populated three fields.

A previously checked read-only official published-assessment preflight for the sitting confirmed current **v5 / 246 frozen questions** and `question_content_identical=true`; another subject-specific postreadback after this four-source batch remains an action item, as does verifying release health after the final operation.

## Holds and next actions

- Do not bypass strict source anchor matching on Q51, Q57, endodontics Q34. Scientific evidence and legacy AI source text may have different PDF text ordering or typographic errors. Require independent correction/evaluation workflow instead of manufacturing citation/page agreement.
- Q44 oral pathology is outside 1405 official chapter scope; five other previously identified chapter-scope holds Q129/Q130/Q217/Q218 persist.
- Q60 research and guarded preview are successful; **do not** claim an applied source until the protected unique receipt and live readback exist. A safe authorized invocation uses the existing Q60 audit SHA and full validated backup, with `--printed-offset --apply`, and a new unique receipt at the path described above. Do not insert a duplicate source.
- Continue the remaining 1405 textbook-based questions with exact approved PDF edition, official subject-specific chapter scope and genuinely confirmed PDF page. Never modify text, choices, answers, frozen exam assessment or human review.

No full-sitting completion is claimed.
