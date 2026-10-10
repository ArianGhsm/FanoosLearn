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


## Final production ledger — 2026-10-10, materials 1398–1405

**This section supersedes the earlier research-only and 72/80 figures above.** Exact production DB and entire immutable assessment snapshots were independently re-read after three material-only source insertion batches. All **80/80** residency dental-materials questions now have source records (ten per year 1398–1405). **0** lack source rows. However **41 historical questions are still blocked from strict scientific/syllabus sign-off** as detailed below; "80/80 source-linked" MUST NOT be represented as "80/80 officially verified."

### Safely deployed original-book printed-page fix

- PR #204 merged as `b24dc31c74667c075e2c974242a573fef9281f5c`. Functional verification required the exact printed page and at least two corroborating adjacent original reference-text page labels; never an inferred fixed offset. Exact SHA-pinned provenance audit, official-year chapter scope, source-only INSERT, identical published stems/options/latest key, no prior row, rollback and unique verified backup guards were retained.
- Exact main deployment `c19c92645635655c5671c7732e4f0b2ca1efd7ee`, five green main CI jobs in run `38041636698`; official updater request `01a12529-58f9-793d-81f0-29d291364e63` returned `SUCCEEDED` UTC 09:37:46 with site `/health` status `ok` at the same SHA. Later merges from *other subjects* may advance GitHub main without changing this already-deployed material functionality; do not conflate deployments.
- The exact original Powers & Wataha 11e reference page test corroborated all 8 genuine printed page labels against corresponding PDF pages. Coordinator W07 original independent provenance audit `classification/coordinator/W07-audit-stage/classification/reports/W07-canonical-verified-20261010.json`, SHA256 `446d7b50f4dea3b3114c8a0fc65fc47871cf6fa6c4734e3ab3682219e0b65f8b`. Three independent live exports byte-identically matched original audited study, preserving every option/official answer.

### Three atomic production source-only batches and immutable exam snapshots

| Year | New Q numbers | Source only | Verified PRE-apply full backup | Unique receipt SHA256 | Published assessment | Whole-sitting postdiff |
|---|---|---:|---|---|---|---|
| 1399 | 214,215,216,218 | 4 | `20261010T093733Z-77ee041f`, 54 files | `5116cdf0f3de7fc717e1daa89764d3ea3f946d4249e5344ebc29d6316a3e93c2` | v7→v8 | 248 unchanged questions, 4 source explanations |
| 1400 | 213,215 | 2 | `20261010T094305Z-5c1826ac`, 54 files | `d7b80afe0745df199eb634f6cfe1bae099f9da6e86d8c537ddaf9500d6aa104d` | v5→v6 | 249 unchanged questions, 2 source explanations |
| 1401 | 213,214 | 2 | `20261010T094710Z-f9abcf17`, 54 files | `62a892c2c251ffd669a45ede67c37c6193cb55b207d16dee2649d689b8117c0d` | v5→v6 | 249 unchanged questions, 2 source explanations |

All receipts exist under protected `classification/reports/<year>-dental-materials-W07-source-publish-20261010.json`; each reported `questions_changed=choices_changed=answers_changed=0` and an atomic source insert. Source row printed labels were 1399 [102,66,21,87], 1400 [110,92], 1401 [45,122] with exact Powers11e chapters [8,5,2,7], [8,7], [4,9] respectively. All were **newly inserted** only in their particular year and the full frozen assessment was subsequently published and postdiff-checked; old answer exclusions and student attempt history were not rewritten. **Never re-apply any of these eight sources.**

Recovery chronological checks: after 1399 a separate full backup `20261010T094305Z-5c1826ac` (54 independently verified files) was completed before the 1400 write; after 1400 the 54-file backup `20261010T094710Z-f9abcf17` preceded 1401; **post-1401** independently verified 54-file full backup `20261010T095103Z-11091cf9` was completed UTC 09:51:08 after the 1401 source commit. Protected publication receipts and actual database/snapshot readback are authoritative, not research proposals.

Final read-only materials inventory (source metadata, no protected question text or book prose), SHA256 `6867597311b6258d7a6e900ee5250f4b1184944df44f58c011169f44a20d1dfe`:
`/srv/fanoos/shared/research/classification/materials-20261010-dedicated/materials-live-80-source-final-20261010.json`.
Reproducible private read-only script: `audit_live_materials_source_scope.py`, SHA256 `b70a6558330dce02ab094eef230095f4403f2866d647d0e53e27746cc6c51c0`.

### **Scientific sign-off NOT complete: exactly 41 pre-existing source records**

The newly inserted **8 are proven both book-evidence-valid and in exact official-year chapter scope**. Before those inserts, 72 records existed; the independent year-specific audit found **41 distinct pre-existing records** that are NOT yet supported by official year validity/scope:

- **10 questions from 1398**: existing Van Noort 4e chapter/page rows but *complete original official same-year edition/chapter validity document not found in the catalog*; partial source substitution notice does not suffice. Preserve entries, seek actual original official 1398 notice before claiming correctness. Some secondary contemporaneous sources describe all chapters; do not promote them to authoritative official scope in the database without the original issuance.
- **30 previously AI-attributed source rows** outside the chapter ranges authorized for that exact reference edition and exam year (including materials1399–1405); they need individually validated alternative exact-year official-book evidence, then separately reviewed protected correction pathway. **Do not falsely fix** them by changing chapter codes, rewriting mapped anchors, or overriding previous references.
- **1 human-supplied source record**, 1404 question219 (Craig 14e ch13), outside the recorded official scope and with a blank page label; **do not override** it. Requires explicit independent human/source adjudication.

The row-based follow-up audit shows 42 individual flags because the **same human-supplied row** has both an out-of-scope chapter and missing page. Count **41 distinct historical questions**, not 42. No pre-existing source was changed or erased. Preserved original detailed classification: `classification/materials-20261010-dedicated/materials-historical-scope-triage-20261010.json`, SHA256 `8459acabb5c50fe9e069b34123149c50f3bdddb5ff41cd5a77e9df4bb4267dfb`.

Thus **source-row insertion/publication backlog for 1398–1405 materials is CLOSED (0 remaining)**, whereas **strict reference-year compliance and scientific finalization remains OPEN for 41 historical items**. Do not mark the subject scientifically complete unless those separate holds actually resolve with original-book and original-official evidence. Materials-only ownership; never mix these rows into other subject workers.
