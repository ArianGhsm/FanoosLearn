# Residency 1398: nine further bound original-PDF source rows (2026-10-12)

## Live production result, independently verified

Strict same-source-row `node_id + pdf_page + pdf_sha256` for **residency 1398 only**: **164 / 247**, up from 155 / 247 at this run's start. The 20 English questions with verified official keys remain exempt from a book source; **63 clinical questions remain unbound**. These counts do not automatically certify every preexisting legacy classification's scientific content.

| Question | Official original reference | Chapter | 1-based PDF page | Evidence topic |
|---:|---|---:|---:|---|
| 152 | McDonald & Avery Pediatric Dentistry 10e | 13 | 246 | Lin comparison favors MTA for primary molar pulpotomy |
| 215 | White & Pharoah Oral Radiology 7e | 4 | 61 | Linear CCD array with slit-shaped X-ray beam and multi-second scanning |
| 223 | White & Pharoah Oral Radiology 7e | 25 | 486 | Thalassemia: coarse trabeculae, thin cortex and short dental roots |
| 224 | White & Pharoah Oral Radiology 7e | 22 | 386 | Radiopaque foci within calcifying epithelial odontogenic tumor |
| 227 | White & Pharoah Oral Radiology 7e | 20 | 333 | Early inflammatory lesions can have no visible radiographic change |
| 228 | White & Pharoah Oral Radiology 7e | 25 | 473 | Tapered dental root appearance from loss of lamina dura in hyperparathyroidism |
| 229 | White & Pharoah Oral Radiology 7e | 23 | 437 | Cherubism, mandibular-ramus epicenter |
| 230 | White & Pharoah Oral Radiology 7e | 24 | 463 | Typical myeloma punched-out, noncorticated well-defined lesions |
| 218 | White & Pharoah Oral Radiology 7e | 10 | 199 | Panoramic nasopharyngeal airway and radiographically visible soft palate |

The exact protected original PDF fingerprints:
- McDonald & Avery 10e: `33e3bad50e6cb5a595bd05f1dc50ef602a565191964f2af2551e96efc761bf8b`.
- White & Pharoah 7e: `6fec290f733e5282284a722eeb44f3a99b35edd350711b683c87f103108a6169`.

Chapter ranges are sourced exclusively from the respective official 1398 editions' validated original PDFs and hash-bound chapter maps, not book-printed labels or nearest-edition substitutions.

## Protected audit evidence, backups, write receipts

All protected studies, decisions, validated sittings, provenance audits and receipts are on the server under `/srv/fanoos/shared/research/`. The immutable source-only batches used the original-PDF chapter+page validator, question/choice/key identity audit, an independently verified full backup, dry-run, guarded atomic source insertion and independent SQL readback.

| Private stem in `classification/reports/residency-1398-<stem>-applied.json` | Added | Backup verified, 114 files | Receipt SHA-256 |
|---|---:|---|---|
| `pediatric-q152-20261012` | 1/1 | `/var/backups/fanoos/20261012T161053Z-a03b8f8d` | `74cf01f9b10aff8ed4d930b8a8ba1b2ed866aaa2f100d762040bd1bed7c21a1a` |
| `radiology-seven-20261012` | 7/7 | `/var/backups/fanoos/20261012T163501Z-6215b20e` | `e22b492d67c89b70f54f2415af18936ef98b5d61e189e2f8d05d824caef8c4c6` |
| `radiology-q218-20261012` | 1/1 | `/var/backups/fanoos/20261012T164444Z-8022b068` | `11e5e5c9012d3323ff6412842e2d0b8a08169ffd20cbc405da243be32615b8ba` |

All receipts explicitly show `questions_changed=0`, `choices_changed=0`, `answers_changed=0`; no human reviews, attempts or frozen exam content was altered. The nine inserted source rows' approved edition, node chapter, PDF page, SHA-256 and published-question status were independently queried from production. PR [#371](https://github.com/ArianGhsm/FanoosLearn/pull/371) supplied eight audited, narrowly allowlisted source records and PR [#373](https://github.com/ArianGhsm/FanoosLearn/pull/373) enabled the additional radiology question. Both merged after green CI; the merge-main CI was also independently checked green.

**Duplicate prevention:** A candidate to create another source for question 134 (Carranza) was rightly rejected by the importer because its production source **already existed**. Independent verification found the existing source is Carranza 12e **chapter 15 / original PDF page 594**, with a chapter passage defining the actual gingival position. No duplicate source was created and Q134 is **not** counted among the nine new completions. A stale, older all-question research study must never be treated as proof that a current production source row is absent. Always check live same-year, same-exam source identity immediately before staging.

## Remaining 63 clinical questions

| Subject | Existing bound / total | Outstanding |
|---|---:|---:|
| Oral medicine | 12/20 | 8 |
| Oral pathology | 19/20 | 1 |
| Prosthodontics | 29/30 | 1 (voided/draft Q97) |
| Oral surgery | 4/20 | 16 |
| Periodontics | 6/20 | 14 |
| Pediatric dentistry | 4/20 | 16 |
| Operative dentistry | 18/19 | 1 |
| Oral radiology | 14/20 | 6 |

Endodontics 19/19, orthodontics 19/19, community dentistry 10/10 and dental materials 10/10 are strictly page-bound. English 20/20 official keys are book-exempt.

**Holds requiring nonfabricated evidence or official-key review:** Several source-free questions have textbook passages but apparent disagreements with stored official keys, including Q77 (epidermolysis bullosa), Q150 (Frankl behavior grade), Q155 (splint anchorage), Q160 (behavior guidance), Q166 (hypophosphatasia B6), Q167 (fluoride ingestion ratios), Q188 (Snowplow), and Q225 (sunray periosteal reaction). Carranza Q140 (depth-dependent calculus mineral) and Q148 (smoking as an environmental risk) also warrant source/key adjudication. None of these keys or questions may be edited by chapter classification. The exact official original Hupp 6e surgery PDF remains unavailable as a verified original on this server; chapter/page for its unbound questions must not be guessed from other editions. Existing invalid or human-reviewed entries must not be overwritten.

**Deployment distinction:** Source-only production database changes and `/health` verification are independent of release-code synchronization. The live web release has historically lagged merged GitHub main; do not mark code deployment complete without normal updater authorization, green merge-main CI and final live sync/health checks.

## Recovery and repeatability

Source candidates are stored in protected `bank-sittings/1398/<stem>-study.json`, `classification/decisions/1398-<stem>.json` and `classification/sittings/1398-<stem>-validated.json`. Read-only independent auditors produce protected `classification/reports/residency-1398-<stem>-audit.json`. Exact receipts and verified backups are listed above, so the 155→164 state is independently reproducible without relying on chat history. The next pass should prioritize only original-edition, official-year source evidence and exact PDF page together, while holding source/answer conflicts and drafts.
