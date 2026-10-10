# Residency dental materials: scoped chapter/source audit (2026-10-10)

**Subject owner:** W07, `residency:dental-materials` only. **Mode:** audited research checkpoint; **no production source writes, answer changes, publication, or deployment performed by this workstream.** Other residency subjects are out of scope.

## Production database inventory (read-only)

The live bank contains **80 dental-materials residency questions, ten per year from 1398 through 1405**. Of those, **72 have recorded source rows** and **8 have none**. A recorded source must not be conflated with an independently validated same-year official syllabus match.

| Year | Questions | Existing source rows | Missing source rows | Existing source/syllabus flags |
|---|---:|---:|---:|---:|
| 1398 | 10 | 10 | 0 | 10 no confirmed official same-year validity |
| 1399 | 10 | 6 | 4 | 4 outside recorded chapter scope |
| 1400 | 10 | 8 | 2 | 6 outside recorded chapter scope |
| 1401 | 10 | 8 | 2 | 7 outside recorded chapter scope |
| 1402 | 10 | 10 | 0 | 8 outside recorded chapter scope |
| 1403 | 10 | 10 | 0 | 2 outside recorded chapter scope |
| 1404 | 10 | 10 | 0 | 2 outside recorded chapter scope |
| 1405 | 10 | 10 | 0 | 2 outside recorded chapter scope |
| **Total** | **80** | **72** | **8** | **41 historical validation flags** |

The 31 chapter flags compare the recorded bank source node, after numeric-chapter normalization (e.g. `ch03.7` → `3.7`), against the *same edition, exam year and subject* in `bank_reference_validity.scope_chapters`. They do not demonstrate that the question's scientific answer is wrong or authorize overwriting the existing chapter, particularly if a human previously reviewed it. For 1398, the catalog includes a partial announcement naming Van Noort but has **no verified edition-and-chapter validity**; preserve all ten existing source rows and hold revalidation pending the complete official list.

The protected source metadata inventory and detailed 41-item flags are at:

- `/srv/fanoos/shared/research/classification/materials-20261010-dedicated/materials-full-source-inventory-v2-20261010.json` — SHA-256 `942f8b3ecdb570ad7677a539dee7fe1189e559156efe6d57c1dcbb02207f3950`. This **supersedes v1**, which incorrectly compared zero-padded chapter identifiers and overreported chapter mismatches.
- `/srv/fanoos/shared/research/classification/materials-20261010-dedicated/MATERIALS_AUDIT_STATUS.md` — SHA-256 `bd99e58f06c9835d848690fd39e641a4d41bddbda5c0e20d174f90ae339022b2`.

These private artifacts intentionally contain no question stems, option text, keys, reference-book prose, or credentials in this public GitHub document.

## Eight unsourced questions: independent source research accepted

The exact 1399–1401 official materials reference includes **Powers & Wataha 11th edition** (authorized chapters 2, 3, 4, 5, 7, 8, 9, 11, 13, 15). The immutable W07 research bundle was independently checked using the canonical `scripts/references/audit_private_study_batches.py` and a separate printed-page original-book neighbor review. The eight entries below were accepted for evidence **only**, not applied to production.

| Year | Question | Reference edition | Chapter | PDF page | Printed-book page |
|---|---:|---|---:|---:|---:|
| 1399 | 214 | `powers-wataha-materials@11e` | 8 | 120 | 102 |
| 1399 | 215 | `powers-wataha-materials@11e` | 5 | 84 | 66 |
| 1399 | 216 | `powers-wataha-materials@11e` | 2 | 39 | 21 |
| 1399 | 218 | `powers-wataha-materials@11e` | 7 | 105 | 87 |
| 1400 | 213 | `powers-wataha-materials@11e` | 8 | 128 | 110 |
| 1400 | 215 | `powers-wataha-materials@11e` | 7 | 110 | 92 |
| 1401 | 213 | `powers-wataha-materials@11e` | 4 | 63 | 45 |
| 1401 | 214 | `powers-wataha-materials@11e` | 9 | 140 | 122 |

All **three fresh live read-only study exports** are byte-identical to original W07 evidence, including stems, options and official answer objects; original SHA-256 values respectively: 1399 `bda408db03fd53a204c72920b42d3b89eb4e19d6632eb5e3761ba5c84332aee8`, 1400 `32e7ab79b3e57fc5e808b2f477f13e67eb8c4ea975416c69bf255e05c3288719`, 1401 `dde72aa3e6222b27a6f74c452aa795d2737029125b11faa0551a6d64941dc12d`.

Protected W07 reports:
- `classification/coordinator/W07-audit-stage/classification/reports/W07-canonical-verified-20261010.json`
- `classification/coordinator/printed-page-independent-review-20261010.json`
- `classification/materials-20261010-dedicated/materials-live-recheck-20261010.json`, SHA-256 `94f351268028ebd4971b39deb35e982e79e836f9f38be6e33194119064bfe665`.

### Technical hold and publication gates

The original `SourceOnlyPublisher` correctly rejects the eight cases today because it only accepts the source page equal to the decision's PDF page, while the evidence-preserving mapped source page is the genuine printed-book page (18-page offset in this edition). **Do not manipulate the decisions, page labels, or protected audit to make them match; do not bypass source-only protections or feed study-only JSON to a general question importer.** A separately reviewed, tested and CI-approved publisher extension for trusted original-book printed-page provenance is required.

After that change is merged and officially deployed, do **not** count a candidate as completed until all of the following succeed: fresh exact live question/export comparison; original protected audit digests; per-year rollback-only source insertion preview and year-specific syllabus guard; fresh independently verified full backup; unique receipt from atomic source-only insert; database row-by-row readback; entire frozen-assessment preflight and authorized new-version publication with unchanged stems/choices/answers, human decisions and historical attempts; full postpublication diff; green CI, updater deploy verification and live health. A production insertion/assessment revision must be documented separately with precise SHA and backup identity.

**Parallel-workstream boundary:** this checkpoint changes only the materials documentation. It does not claim or trigger writes in periodontics, endodontics, restorative dentistry, surgery or other subjects.
