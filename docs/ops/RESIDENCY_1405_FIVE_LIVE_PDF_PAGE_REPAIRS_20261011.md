# Residency 1405 — exact book chapter/PDF-page live repair checkpoint, 2026-10-11

## Production and controlled source-only execution

Before this batch, the independently verified live `residency:1405:1` bank contained **250 questions**, **83 records** carrying all chapter node, real PDF-page index and source PDF SHA-256 together, and **147 incomplete textbook-based questions** (20 English questions exempt). The field-triple is a syntactic indicator only, not a scientifically verified validity count. Baseline is further documented in `RESIDENCY_1405_CHAPTER_PDF_PAGE_20261011.md`.

The official updater release `cd3b7913640dcb6ccdcb471e637e9e3236b89c4e` was confirmed live with `/health status=ok`; the deployed commit **descends from** guarded printed-page offset operator PR #306, merged as `f3e202d24743f504c727bbada7e26e91a90ed250`. PR #306 CI passed; the exact main-commit run 38146261648 was rerun after an external registry rate-limit and all **five** GitHub Actions jobs then passed. The official release-update request matching `cd3b791...` was `SUCCEEDED`.

A new **complete, official**, authorized `scripts/ops/backup.php` backup finished at 2026-10-11 18:57:09 UTC: `/var/backups/fanoos/20261011T185643Z-5d8b2831`. Independently ran `scripts/ops/verify-backup.php`, which printed **114 files verified**. The backup excludes approved private reference-PDF copies by official policy, but the references remain independently SHA-pinned and verified by the source operator.

Every applied item below had all of: exact official 1405 scope, current approved server PDF and SHA, chapter-bound PDF-page map, actual printed page label and at least two adjacent page labels, **original unchanged source anchor text found on the actual PDF page**, original live question/choices/latest official answer equality, existing unreviewed AI citation with unchanged reference/chapter/printed page, research-only single-question `apply_classification.py --pdf-pages` result **1 accepted/0 rejected**, independent `audit_private_study_batches.py` **1 accepted/0 pending**, digest-pinned research folder, and **rollback-only production preview** `applied:false` with zero protected-row changes. The guarded operator `repair_existing_source_pdf_page.php --printed-offset --apply` then returned **`applied:true`, precisely one existing source-row updated** and `question_rows_updated=0, choices_updated=0, answers_updated=0, source_chapter_updated=0, human_reviews_updated=0` for each, while preserving the legacy numeric `page`, `printed_page`, existing source anchor, chapter and reviewed status.

| Question | Subject | Exact official edition / chapter | Printed book page | Actual approved PDF page | PDF SHA-256 | Applied UTC |
|---:|---|---|---:|---:|---|---|
| 214 | dental-materials | `craig-restorative-materials@14e`, chapter 12 | 246 | **257** | `333e81ae8a1818cdff57f00e3f870698776e3b8e02fcc49169f397f2fbaf90b9` | 18:57:57 |
| 27 | endodontics | `torabinejad-endodontics@6e`, chapter 7 | 126 | **133** | `09333f079cb3300550cc0702985aae216d952eacabdaac005e1c66fafebe13e0` | 18:58:16 |
| 29 | endodontics | `torabinejad-endodontics@6e`, chapter 11 | 211 | **218** | same Torabinejad SHA | 18:58:30 |
| 35 | endodontics | `torabinejad-endodontics@6e`, chapter 17 | 377 | **384** | same Torabinejad SHA | 18:58:44 |
| 38 | endodontics | `torabinejad-endodontics@6e`, chapter 20 | 439 | **446** | same Torabinejad SHA | 18:58:57 |

All five actual operator responses independently asserted `source_rows_updated=1`, `backup_id=20261011T185643Z-5d8b2831`, all protected-domain update counts zero. Private reproducible case, original study, decision, validated sitting, SHA-pinned provenance audit, and actual live receipt for each are under:

- `/srv/fanoos/shared/research/classification/recovery-1405-materials214-20261011/classification/reports/1405-materials214-live-repair-20261011.json`, audit SHA `ea04f9d644ad8695504fac78feeb8e7a505d15c0270232ad364a96ac680b844f`
- `/srv/fanoos/shared/research/classification/recovery-1405-endo27-20261011/classification/reports/1405-endo27-live-repair-20261011.json`, audit SHA `cd25c11cd2d5817cd8e83e16d787d7ed27b39b5fe4a9aeffea2296b6e090d52d`
- `/srv/fanoos/shared/research/classification/recovery-1405-endo29-20261011/classification/reports/1405-endo29-live-repair-20261011.json`, audit SHA `dc3c3825fb64886bd91c591a3349620537ea821f9b19f2f30bf939f756a433ec`
- `/srv/fanoos/shared/research/classification/recovery-1405-endo35-20261011/classification/reports/1405-endo35-live-repair-20261011.json`, audit SHA `63c153a8adb14722fafa22f9897bc6408a6c96905afc8c27d26c192048571135`
- `/srv/fanoos/shared/research/classification/recovery-1405-endo38-20261011/classification/reports/1405-endo38-live-repair-20261011.json`, audit SHA `dd72622cb6a6c7d0498fc2c17e4b3fe25f1c207c5d77b63b5f3d68544e8ef3bf`

**Important validation status:** The five guarded transaction replies confirm live commits, but an independent combined posttransaction DB count, per-row select and frozen published assessment check were **not yet confirmed** when this checkpoint was authored because subsequent live shell calls were intermittently blocked. Expected strict-triple count is **88/250** and expected remaining textbook-based incomplete count is **142**, *conditional on confirming actual independent live queries and no concurrent updates*. Do **not** label these aggregate numbers as measured until readback returns.

## Held additional candidates and mandatory remaining work

- **Q34 endodontics:** Torabinejad 6e chapter 22, printed page 466, PDF page 473, independent research/sitting audit passed and PDF neighbor labels match. Crucially, the **existing live source anchor fails the guarded real-page exact-text test** (`pageContainsEvidence=false`) even though the selected research fragment was initially promising. The production rollback-only preview **rejected** this row: `Printed/PDF offset lacks independent neighbor-page and original source evidence.` This is a hard scientific hold, NOT applied. Find the actual book text and correct scientifically approved quotation through a separately reviewed safe change if warranted; never suppress the guard.
- **Q26 endodontics** likewise lacks corroboration of its neighboring printed page labels; no write.
- Earlier exact-year source chapter-scope exceptions **Q44, Q129, Q130, Q217 and Q218** remain open. Q217 already contains PDF metadata but is not valid merely because fields exist.
- Review each of the remaining textbook questions against the **same year's official scope** and **exact approved current reference PDF**, not inferred offsets. Missing source rows (five radiology cases initially identified) require the separate guarded source-only insertion workflow, not the existing-source metadata repair tool.
- Verify actual production counters/rows, run `scripts/references/audit_published_assessment.php` for changed subjects, archive protected research and receipts and validate site health. Before claiming final completion, verify bank, publication, deployment, CI, questions/choices/official keys, human reviews and exam history.

The sole authorized data operation performed in this batch was filling `bank_question_sources.pdf_page` and `bank_question_sources.pdf_sha256` for the five existing source records. No new assessment version or question/answer publication is asserted.
