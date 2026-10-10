# Oral-pathology Neville5e five-page correction: applied, independently checked (2026-10-10)

Production mutation scope: **only five existing AI-origin source page fields** for 1404 Q46 and 1405 Q47/Q51/Q54/Q60. Exact edition: approved original Neville oral and maxillofacial pathology 5e, 983-page private PDF. Correct physical PDF and book-printed pages were checked against original fact-level evidence and both neighboring printed pages. No source insertion, source chapter/reference change, anchor/provenance change, assessment republish, or human-source modification occurred.

| Question | Correct chapter | Previous page | Verified printed page |
|---|---:|---:|---:|
| 1404 Q46 | 10 | 10 | 367 |
| 1405 Q47 | 10 | 20 | 434 |
| 1405 Q51 | 12 | 12 | 533 |
| 1405 Q54 | 14 | 14 | 639 |
| 1405 Q60 | 16 | 3 | 770 |

**Code and release:** guarded script `scripts/references/correct_existing_pathology_pages.php` introduced in PR #221, merged commit `4d0090212b4a14b070134de69ac9d2f9612d1bef`; all five required PR CI jobs passed (run `38046126078`). Official updater independently deployed release `1178d9b80fb1cc1a95746f219c9c5a32fdd1bbe8` containing the script. Live public health returned HTTP 200.

**Backup and mutation:** freshly completed official full project backup `20261010T110300Z-a2f85dd8`, separately passed the canonical verify-backup script (**54 files**). Read-only live preview accepted precisely five expected rows (after release deployment). Authorized updater-account single-transaction execution returned `applied=true, count=5`. The protected immutable operator receipt is at `/srv/fanoos/shared/research/classification/parallel/oral-pathology-review-20261010/nev5-five-page-repair-receipt-20261010-1105.json` (SHA256 `763f5137c53a9b08f6f05826f7c53ba0fed1f6dced18e8b0cf485806802f2127`).

**Independent post-commit readback:** re-exported all **159** existing pathology source rows and all **159** question stems, choices, images and recorded official answers. Compared to the pre-apply private snapshots, no question/choice/key/answer, source ID, edition, chapter, anchor, origin, human review or other source page changed. **Exactly the above five page values changed.** Verified frozen full-assessment definition SHA256 unchanged for 1404 version **4** and 1405 version **5** (before/after). Protected independently generated diff `post-five-page-independent-diff.json` SHA256 `00531a818b8aa3b0db161ec5cd6cc88753729390d7d023ed6d7970af6753a6b2`.

**Limit:** This is five demonstrated production corrections, **not** a claim of full pathology completion. There were previously 120 missing pages (119 older original4e, one human-reviewed 1404 Q56); this five-record repair fixes *wrong nonnull placeholders*, so cannot count those 120 missing pages as resolved. The original4e nearest-edition annotations and two 1398 original-book/key conflicts require separate research and/or authorized human key adjudication. Protected source reports stay on the server and are never committed to Git.
