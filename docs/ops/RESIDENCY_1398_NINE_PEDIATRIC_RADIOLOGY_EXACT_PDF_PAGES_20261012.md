# Residency 1398: nine exact original PDF chapter+page sources (12 October 2026)

## Verified live state

Production `fanoos_prod`, explicitly scoped to `exam_type='residency'`, `exam_year=1398` and same-row non-null `node_id`, `pdf_page`, `pdf_sha256`: **155 / 247**. Initial state for this campaign: **146 / 247**. Twenty English questions with verified official answer keys are **book-reference-exempt**, leaving **72 clinical questions without complete source chapter and original PDF page**. This figure includes special voided/draft cases; it is NOT a declaration that every existing legacy source is fully scientifically re-audited.

| Question | Subject | Approved 1398 original edition | Chapter | 1-based original PDF page |
|---:|---|---|---:|---:|
| 164 | Pediatric dentistry | McDonald & Avery 10e | 11 | 215 |
| 168 | Pediatric dentistry | McDonald & Avery 10e | 8 | 159 |
| 159 | Pediatric dentistry | McDonald & Avery 10e | 19 | 360 |
| 211 | Oral radiology | White & Pharoah 7e | 1 | 27 |
| 212 | Oral radiology | White & Pharoah 7e | 3 | 50 |
| 214 | Oral radiology | White & Pharoah 7e | 6 | 103 |
| 217 | Oral radiology | White & Pharoah 7e | 10 | 189 |
| 219 | Oral radiology | White & Pharoah 7e | 14 | 260 |
| 226 | Oral radiology | White & Pharoah 7e | 21 | 355 |

Original, protected, approved PDF hashes:
- McDonald & Avery 10e, 710 PDF pages, 31 verified chapter headings, begins physical chapter 1 on PDF 11, index begins PDF 693, SHA-256 `33e3bad50e6cb5a595bd05f1dc50ef602a565191964f2af2551e96efc761bf8b`.
- White & Pharoah 7e, 697 PDF pages, 34 verified chapter headings, begins chapter 1 on PDF 18, index begins PDF 670, SHA-256 `6fec290f733e5282284a722eeb44f3a99b35edd350711b683c87f103108a6169`.

These were missing hash-bound chapter-to-page maps. Chapter starts were checked against each original private PDF's explicit chapter/title heading; the index start was verified against adjacent pages. Maps cover all pages, with front/back matter not labeled a chapter. Fixed map rebuild and tests: [PR 352](https://github.com/ArianGhsm/FanoosLearn/pull/352), merged with green required CI.

## Four atomic source-only publication batches

Each used `export_server_candidates.php` as a read-only private study; `apply_classification.py --pdf-pages` to bind question/topic, official-year chapter and exact original-book PDF page/hash with a short quote; `audit_private_study_batches.py` to check complete preservation of stem/choices/official key; and `import_verified_sources.php` under `fanoosupd` for a **dry-run** followed by a transaction with a **fresh independently verified full backup**. Receipts report zero changed questions, choices and official answers. Independently queried production rows match the original PDF hashes and all nine exact chapter/page pairs.

| Private batch stem | Accepted and applied | Independently verified full backup (114 files) | Protected source-only receipt SHA-256 |
|---|---:|---|---|
| `pediatric-q164-q168-20261012` | 2 / 2 | `/var/backups/fanoos/20261012T154533Z-a21afa79` | `92614af3e0d4e478567a162b1b8863b36312cddc13e966343316ecdc9153982c` |
| `radiology-four-20261012` | 4 / 4 | `/var/backups/fanoos/20261012T154533Z-659c1f0b` | `eead09453117895c929f19c7227de2679117cad27c39e9b6d4efb2990b1497ea` |
| `pediatric-q159-20261012` | 1 / 1 | `/var/backups/fanoos/20261012T155537Z-0350e9a7` | `95156e755a184e5e365ab62648f1317c7244461004e26b9519aa6f40b13fd124` |
| `radiology-q219-q226-20261012` | 2 / 2 | `/var/backups/fanoos/20261012T155537Z-9a271573` | `e13da8a9f4161a99e5f76e9077231665e12a59a6e1c36833c9d987e53d196aa7` |

All four protected receipts reside at `/srv/fanoos/shared/research/classification/reports/residency-1398-<stem>-applied.json`, with separately named audits `residency-1398-<stem>-audit.json` and protected source-free study/decision/validated input snapshots under `/srv/fanoos/shared/research/`.

Safety-tested one-use import allowlist gates merged as [PR 353](https://github.com/ArianGhsm/FanoosLearn/pull/353) (six questions) and [PR 358](https://github.com/ArianGhsm/FanoosLearn/pull/358) (three more), each with CI green. These guard specific year, subject, exact edition, question number, chapter, PDF page, approved PDF hash, original syllabus scope, immutable answer/text/options and no existing source; no arbitrary extension or nearest-edition substitutions.

A concurrent backup attempt timed out, and its partial artifacts were **not used**. Only the four full, independently checked, 114-file backups above were used to authorize production changes.

## Live per-subject coverage after readback

| Subject | Bound / total |
|---|---:|
| Endodontics | 19 / 19 |
| Orthodontics | 19 / 19 |
| Oral medicine | 12 / 20 |
| Oral pathology | 19 / 20 |
| Prosthodontics | 29 / 30 (one voided/draft Q97) |
| Oral surgery | 4 / 20 |
| Periodontics | 6 / 20 |
| Pediatric dentistry | 3 / 20 |
| Operative dentistry | 18 / 19 |
| Oral radiology | 6 / 20 |
| Community dentistry | 10 / 10 |
| Dental materials | 10 / 10 |
| English | 0 / 20; 20 verified official-key book-exempt |

**Remaining clinical queue (72):** oral medicine 8, pathology 1, prosthodontics 1 (voided Q97), oral surgery 16, periodontics 14, pediatric dentistry 17, operative dentistry 1, oral radiology 14.

**Scientific holds:** Q77 pathology and Q188 operative have exact original reference passages but the stored final answer differs from textbook-supported choice. Older Q135/Q145 also require official-key adjudication; no answers were changed. The official Hupp oral-surgery 6e PDF is absent from the approved private original-reference library; do not fabricate pages or use edition 7e.

**Deployment caveat:** independent DB publication and private receipts are verified; a code publication/deployment claim requires the normal owner-authorized updater flow, merged-main CI and public /health verification. No frozen assessment was republished and no stem, options, official answer, human decision or user attempt was edited.

Next batch should reuse exact year-1398 verified PDF maps, independently research each remaining question and only publish when correct source chapter **and** original PDF page/hash are both proved. Unverified or invalid-question cases remain pending rather than guessed.
