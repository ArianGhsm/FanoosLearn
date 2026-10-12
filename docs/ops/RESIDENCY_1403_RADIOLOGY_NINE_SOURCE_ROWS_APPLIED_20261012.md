# Residency 1403: nine radiology sources inserted in the live bank — 2026-10-12

This is a **verified production source-only insertion**, NOT a claim that all residency 1403 chapter/pages or its published student assessment have been updated. Questions, answers, decisions and assessment history were not modified.

## Before/after DB readback

- Exact sitting: `residency / 1403 / round 1`, 250 original questions. Canonical original export SHA-256: `5a903b491496ed3b7f912dde0fdc37607ff102f33b36a9c5e723baab19a1fcc7`.
- Initial live readback: **71/250** source rows independently containing `node_id`, `pdf_page` and `pdf_sha256` on the same question source.
- Final live readback: **80/250**, precisely **nine** added `bank_question_sources` rows. All nine are `origin=ai`, primary, unreviewed, attached to still-published questions, official White–Pharoah radiology 8e.
- Original PDF server SHA-256: `228ceb46982c3057d8a7d78b1d10759e72ed1a3a385a76a9affc38e45874c25a`.

| Question | 1403 official chapter | PDF page |
|---:|---:|---:|
| 192 | 1 | 70 |
| 193 | 3 | 136 |
| 196 | 6 | 302 |
| 197 | 15 | 754 |
| 203 | 12 | 606 |
| 205 | 22 | 1084 |
| 207 | 25 | 1344 |
| 208 | 26 | 1446 |
| 210 | 28 | 1568 |

Each exact original PDF page/edition/chapter and untouched canonical question/answer was validated by `scripts/references/apply_classification.py` and independently by `audit_private_study_batches.py`. Private independent audit `/srv/fanoos/shared/research/tmp/1403-radiology-page-audit-20261011/audit-root/classification/reports/1403-radiology-nine-independent-page-audit.json`, SHA-256 `fded7361c03d9d8735911d1e0f2956070e91722e9d9b7b2fe4253cee21d4c337`.

## Protected signed input and official transaction

The original four input files were copied byte-for-byte to protected staging under `/srv/fanoos/shared/research/classification/coordinator/W01-audit-stage/` **without overwriting that existing coordinator's other-year files**: `bank-sittings/1403/radiology-study-20261011-study.json`; `classification/decisions/1403-oral-radiology.json`; `classification/sittings/1403-radiology-study-20261011-validated.json`; `classification/reports/1403-radiology-nine-independent-page-audit.json`.

Staged hashes: study `84499b3fb8a73317a4bee125d49f68267af2c713e74341c53201f5f6e0d99951`, validated `87e7c228190ea87076e6a879fd033a4dc22ce71bd0d31d1642d51b4573a2bcc8`, decisions `da5bbbc3ac377c7346208ab01483521a006cf865e74d5d0c1fe64cf7ad971e6a`, audit as above.

The **unmodified, already deployed** official production source-only importer `scripts/references/import_verified_sources.php`, operating as `fanoosupd` with the configured FANOOS environment, first passed the real DB **rollback-only preview**: `applied=false; source_rows=9; questions_changed=choices_changed=answers_changed=0`.

Before apply, `scripts/ops/backup.php` completed **fresh full production database + objects backup** `/var/backups/fanoos/20261012T154715Z-06739959`, excluding only original reference PDF objects per explicit storage policy. Independent official `scripts/ops/verify-backup.php` passed: **114 verified files**, manifest+READY present, `database.sql` nonempty. This is a protected server backup, **not** a claimed offsite copy.

Atomic importer applied at `2026-10-12T15:52:29Z` with all 4 exact stage files, year=1403, round=1, subject=`oral-radiology`, expected=9 and full backup verification. Official receipt: `/srv/fanoos/shared/research/classification/reports/residency1403-oral-radiology-nine-original-pdf-applied-20261012.json`, SHA-256 `985af186f30c5ca79bae30b5281a82d363553d9794bc6b121191108087b5bf60`. Receipt records `applied=true; source_rows=9; questions_changed=choices_changed=answers_changed=0`.

Independently read all nine live rows after commit and verified each original reference chapter/page/PDF SHA, unreviewed AI provenance, primary flag and published question status. Entire 250-question bank's immutable stems, all options and latest official answer keys were independently rechecked: `/srv/fanoos/shared/research/tmp/1403-source-page-expansion-20261011/residency1403-live-immutable-postradiology-20261012.json`, SHA-256 `19cc3eabb5f8e748a0bd0a69d77bebd00866a20dac8bf468c9b24c562ffc68cd` (all passed). Public site `/health`: OK, release `cd3b7913640dcb6ccdcb471e637e9e3236b89c4e`.

## Assessment publication and remaining tasks

Read-only frozen assessment preflight ran against current **published version 5** and passed: **all nine reviewed study questions identical, all 250 frozen questions still present**. **No assessment new version was published** in this operation, no attempted exam or historical snapshot was changed, and no new code deployment was performed (the source-only importer was already installed). Re-publish only via explicitly authorized actor/reviewer official workflow and full frozen assessment version-diff validation, ideally after remaining clinical source mappings.

The **unpublished, independently source-verified research set now consists of 144 questions** with existing AI/legacy source rows needing a guarded metadata repair. These cannot use the insert-only importer; 48 old legacy anchors reference a nearest edition; **2 source editions and 6 chapters need corrections** across the 144. The current source-only metadata repair operator only handles **existing unchanged edition/chapter**, so never attach pages to an outdated or out-of-scope chapter/quote.

**Six remaining original-reference research holds**, without science-grade official chapter+page pairs: Q61, Q62, Q63, Q68, Q69, Q127. Those remain pending; never invent an in-scope page or change the official answer.

The full 153-paper research checkpoint in `docs/ops/RESIDENCY_1403_153_VERIFIED_ORIGINAL_PDF_PAGES_20261012.md` predates these nine production insertions. Read it as chapter/page *research*, not current production count. Other parallel workers may be active: re-check fresh LIVE DB, source audit receipts, GitHub head, official year chapter scope, server job ownership, exclusive transaction coordination and backup freshness before any future apply. This ledger prevents duplicate insertion of the same nine question IDs.

**Result of this operation:** 9 source-only INSERTs, 0 source/answer/choice/stem/human/attempt/frozen-assessment updates; 80 live fully paired questions, 144 verified-but-not-yet-applied sources, 20 English textbook-exempt, 6 unresolved.
