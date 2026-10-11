# Dentistry residency 1402 — exact chapter + PDF-page audit (2026-10-11)

**Status:** research and read-only production preflight; no 1402 production source repair or new classification was published by this work. The numbers below are from the production database itself, not an estimate or a test database.

## Live snapshot and strict measure

- Site: `https://fanooslearn.ir/health` returned `status: ok`, running release `15e823ae7a04775e45646c1315ddcfa3cc11fd52`.
- At audit time GitHub `main` and the separately governed updater checkout were `2de5b8da121dfdd225923adeb386de839a086711`; this is newer than the running release. Do not describe them as synchronized or deploy only to close this documentary difference.
- Exam `residency / 1402 / round 1`: **250 total**, **243 published** and **7 draft**.
- **224** questions have a source and chapter node. Of these, **75** have on the same source row a `node_id`, positive `pdf_page`, and `pdf_sha256`. **149** have a chapter but lack a bound original PDF page. **26** have no source: **20** English with confirmed final official answer and **6** oral radiology requiring book research.
- Raw strict incomplete: **175/250** (includes the 20 English reference-exempt questions). **155** need chapter+PDF-page work after the official English exemption: 149 legacy source records needing individual page verification and six unsourced radiology records. The 75 field-complete rows are *not* certified as scientifically verified merely because the three fields exist.
- All **20 English** have a latest official answer with status `final` and a non-null selected choice. They are exempt from book/chapter/page citation under `docs/PROJECT_PRINCIPLES.md` §3A; insert no placeholder sources.
- The 1402 source set has **zero** `origin=human` rows at this snapshot. Nonetheless preserve existing sources, answer keys, frozen exams, human decisions elsewhere, and student attempt history.

| Subject | Total | Source rows / chapter | Bound chapter + PDF page | Raw incomplete |
|---|---:|---:|---:|---:|
| community-dentistry | 10 | 10 | 10 | 0 |
| dental-materials | 10 | 10 | 0 | 10 |
| endodontics | 20 | 20 | 0 | 20 |
| english (exempt) | 20 | 0 | 0 | 20 |
| operative-dentistry | 20 | 20 | 19 | 1 |
| oral-medicine | 20 | 20 | 0 | 20 |
| oral-pathology | 20 | 20 | 0 | 20 |
| oral-radiology | 20 | 14 | 14 | 6 |
| oral-surgery | 20 | 20 | 0 | 20 |
| orthodontics | 20 | 20 | 20 | 0 |
| pediatric-dentistry | 20 | 20 | 0 | 20 |
| periodontics | 20 | 20 | 0 | 20 |
| prosthodontics | 30 | 30 | 12 | 18 |
| **Total** | **250** | **224** | **75** | **175** |

The extra legacy `page` and `printed_page` values, including printed-only rows in endodontics, dental materials, pediatric dentistry, and oral surgery, do not satisfy the exact-PDF-page condition. Nor is the 75/250 value an independent syllabus-validity audit; the broader 126-source out-of-scope warning from PR #291 warrants a per-row validity recheck.

## Independent exact-book finding: oral-pathology 1402 Q65 (NOT APPLIED)

- Existing record: `neville-oral-pathology@4e`, chapter **6** (`ch06`, *Fungal and Protozoal Diseases*), old printed-page field **195**, but `pdf_page=NULL` and `pdf_sha256=NULL`. Existing AI anchor still mentions the *nearest fifth edition*; this legacy text is **not** proof for 4e.
- Current private approved **Neville 4e** PDF was authenticated against database object, size and SHA-256 using the versioned `verified_reference_pdf.py --edition=neville-oral-pathology@4e --require-map`. It is **878 PDF pages**, **692,822,013 bytes**, SHA-256:
  `6fbc9bcba9003deda2f8fc006ccc4f23f9e15db0bcd0e237c56ddf6788578bb6`.
- The SHA-bound verified chapter map places **chapter 6 on PDF pages 198–224**. Direct read of **PDF page 202** shows the original running head `CHAPTER 6 Fungal and Protozoal Diseases 195`, independently confirming **printed page 195**.
- Original page 202 explicitly states: “This form of candidiasis is the least common” while discussing chronic hyperplastic candidiasis. This matches the recorded official answer to Q65. The original 1402 official reference validity includes **Neville 4e chapter 6**. This is a **high-confidence page-repair candidate**: chapter 6, PDF **202**, printed **195**, exact above SHA-256.
- **No database update, change to the pre-existing legacy anchor, source-history rewrite or assessment republication occurred.** A production metadata repair needs an approved versioned operator that guards row/question identity, existing official edition and chapter, full frozen content, current PDF/hash, same-year official scope, fresh verified full backup, source-only row update, atomic receipt, DB read-back and published-assessment diff. Never claim this finding as one of the already-completed 75 until those checks actually succeed.

## Disposition of operative Q175 (NOT APPLIED)

- The sole operative-dentistry 1402 question missing a bound PDF page is **Q175**. Its source is `summit-operative@4e`, chapter **9**, with legacy/printed page `2`, `pdf_page=NULL`.
- Exact approved Summit 4e PDF verified at **1,145 PDF pages**, SHA-256 `a634d3519e67001151d0d406c3fb91dbeea30ff546c92ed3dec8dfade5a3d6f6`. Its hash-bound chapter map gives **chapter 9, PDF pages 423–509**; the official 1402 validity includes chapter 9 **only through the bonding section to printed p.217**. The legacy printed `2` cannot be converted automatically to a PDF page. The fact about three-step etch-and-rinse adhesives requires original-page evidence and confirmation that the exact supporting passage is within the partial announced scope. **Pending; do not invent a page.**

## Reproducible production read-back

Use the configured read-only authorized operator on production. Source-row-complete means an `EXISTS` correlated subquery with `s.node_id IS NOT NULL AND s.pdf_page IS NOT NULL AND s.pdf_sha256 IS NOT NULL`, **all on the same `bank_question_sources` row**, for `bank_exam_types.type_key='residency'`, `bank_exam_sittings.exam_year=1402`, round 1. Group by `bank_subjects.subject_key` to reproduce the table. The existing reproducible site-wide SQL is in `docs/ops/BANK_CLASSIFICATION_PAGE_BACKLOG_20261011.md`.

Protected research inputs and prior live source-only receipts, including `/srv/fanoos/shared/research/classification/decisions/1402-oral-radiology.json` and `/srv/fanoos/shared/research/classification/reports/1402-radiology-source-publish-20261010.json`, must not be re-imported blindly. The protected earlier 1402 radiology publication (14 rows, with six intentionally unresolved) is already reflected in these live counts.

## Next controlled work

1. Build an individual pending queue of the 149 already-chaptered/unbound 1402 questions and the six unsourced radiology questions, never including the 20 eligible English exemptions. Keep original question, key and source identifiers stable; perform research only on the current approved exact editions.
2. For each candidate verify *on its own page* the answer-bearing original-book quote, official year-specific subject/edition/chapter and partial-page scope, chapter-page map and PDF SHA-256. Do not extrapolate from printed-page offsets or nearest editions.
3. For existing sources, use a reviewed, versioned metadata-only correction operator, with CI integration tests, guarded preconditions, official full backup, rollback preview, exact row allowlist and immutable assessment audit. The insert-only `import_verified_sources.php` is not an existing-source repair tool.
4. For six unsourced radiology questions, research `white-pharoah-radiology@8e` only within the official 1402 included chapters (1–9, 12, 15–28); use the independently audited source-only insert workflow, with site-matching questions and exact original page proofs.
5. Following any actual source change, re-read production counts and affected assessment versions; require successful CI/merge/official updater deployment and site health where code/deploy is involved. Update this dated record with the actual per-question source/backup/receipt identifiers. **No such publication happened in this audit.**

All detailed question stems, options, reference-book pages and private object locations stay on the authorized server, not in GitHub.
