# Periodontics 1399: second five independently reviewed original Carranza 13e printed pages

**2026-10-10.** Exact exam-year original Carranza13e PDF, 1,991 pages, SHA-256 `f0e411898ae010688ca5c0d21afe312cef6f5dae86d0e2e45648bc51ca8e2adf`. The earlier workflow temporarily generated a page-text derivative that cleanup removed. Current operation code reads each selected page directly from this approved PDF and records its PDF SHA/page in the receipt.

The historical page update used that temporary derivative; the PDF itself was the underlying exact edition. This record documents the completed operation, while the current code path rechecks each page directly and must not recreate the derivative.

| Question | Existing chapter | Exact original PDF page | Printed page | Verified official answer evidence |
|---|---:|---:|---:|---|
| 115 | 12 | 506 | 182 | Original table 12.1: smoking reduces probing bleeding, while attachment and bone loss increase |
| 118 | 33 | 882 | 408 | Original chapter explicitly compares CBCT with digital radiographs for fenestration and other defects |
| 122 | 72 | 1611 | 721 | Merin class C, surgery indicated but deferred for medical reasons: recall every 1–3 months |
| 123 | 52 | 1196 | 559 | Metronidazole inhibits warfarin metabolism, prolongs prothrombin time, avoid co-administration |
| 125 | 3 | 96 | 46 | Transseptal and alveolar-crest fibers develop on emergence of tooth into oral cavity |

Every listed printed page passed independent neighbor-page corroboration. All five original answer-defining passages were directly located on the stated approved-edition source PDF page. Current question/choice/official-key/origin/edition/official scope were compared to live production and recorded in SHA-pinned private snapshot `/srv/fanoos/shared/research/classification/reports/periodontics-1399-next5-exact13e-20261010/five-full-immutable-verified-snapshot.json`, SHA-256 `5a3eb3c985e6a0f5d4a4d27da45c5a0c3f6f04fe2b24df7628f2ae5ad3e3e781`. All current source rows are unreviewed AI with NULL page; existing original 13e source and chapter remain unchanged.

`scripts/references/correct_existing_periodontics_next5_pages.php` allows only the five exact IDs, independently verifies the approved book text and snapshot hashes, original-year official chapter/answer, unmodified stem/images/options/key, source origin and review protection, and uses an exclusive advisory lock and an atomic compare-and-swap to modify **only `bank_question_sources.page`**. It requires a fresh verified full backup and a unique protected receipt for apply; dry-run must first accept precisely five. Never alter anchor provenance, question, options, key, human decision, attempts, or historical assessment versions.

**Requires live release, dry-run, verified backup, transaction, source-row and exam immutable post-audit before calling published.** This five-case operation does not resolve all 1399–1405 periodontics items or missing full original 14e; the whole-subject record must remain open unless each original-year item has direct approved evidence.
