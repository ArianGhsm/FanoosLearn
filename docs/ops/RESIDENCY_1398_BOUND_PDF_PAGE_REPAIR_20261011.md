# Dentistry residency 1398 — exact source-book chapter and PDF-page repair

**Status (2026-10-11, 14:42 UTC): VERIFIED AND APPLIED.** Thirteen existing 1398 source citations received their exact original-book PDF-page binding through one guarded transaction and an independently verified full backup. The production read-back confirmed 13 of 13 page/hash pairs and an increase from **89 to 102** recorded-complete out of 247. Frozen exams were not republished. Canonical owner rules are [PROJECT_PRINCIPLES](../PROJECT_PRINCIPLES.md) and the [atomic chapter classification policy](../product/09_CHAPTER_CLASSIFICATION.md).

## Baseline and evidence

At the original 2026-10-11 production read-back: **247** bank questions in the 1398 residency sitting, **89** with chapter node, `pdf_page` and `pdf_sha256` on the same source row, **158** without this strict triple. Of those 158, **20** English questions are book-citation-exempt (only if official final/amended answer is valid), and **138** clinical questions still lack a complete pair. There are **152** questions with source rows, including the 89 recorded-complete. After the audited batch: **102** recorded-complete, **145** without the strict triple, **20** separately book-exempt English, and **125** incomplete clinical questions. These are time-stamped counts, not a claim of final scientific audit for all prior 89 rows.

Private protected evidence input: `/srv/fanoos/shared/research/classification/reports/residency-1398-13-verified-bound-pdf-page-proposals-20261011.json` (SHA-256 `d9ca252d1d71fba7885f7b28237bf3555f4a799a1c2c40f85cce076dd9a515dd`). It combines an 11-source proof (SHA-256 `a4b7ce021b18f2bd642963be9bf0bc20f56e80263491b7110cd0ccd9eb751518`) and two exact Neville 4e page proofs (SHA-256 `a91be377f98088c80186d7f94925294e4ac1ffb8896a7bb8e3ae0a0e438d0026`). Source IDs, original metadata, SHA-256 references and short book phrases remain only in protected server storage.

| Exam question | Exact year-1398 official reference | Existing chapter | Verified PDF page |
|---|---|---|---:|
| 5 | Torabinejad Endodontics, 5e | 5 | 89 |
| 64 | Neville Oral and Maxillofacial Pathology, 4e | 12 | 499 |
| 78 | Neville Oral and Maxillofacial Pathology, 4e | 16 | 699 |
| 241 | van Noort Dental Materials, 4e | 1.6 | 48 |
| 242 | van Noort Dental Materials, 4e | 1.3 | 28 |
| 243 | van Noort Dental Materials, 4e | 2.1 | 77 |
| 244 | van Noort Dental Materials, 4e | 2.2 | 104 |
| 245 | van Noort Dental Materials, 4e | 2.5 | 137 |
| 246 | van Noort Dental Materials, 4e | 2.7 | 163 |
| 247 | van Noort Dental Materials, 4e | 3.8 | 243 |
| 248 | van Noort Dental Materials, 4e | 3.5 | 217 |
| 249 | van Noort Dental Materials, 4e | 3.7 | 226 |
| 250 | van Noort Dental Materials, 4e | 3.3 | 199 |

Evidence was checked against the **actual original private PDF pages**, never an inferred printed/PDF offset. The reference PDF hashes were bound to `data/bank/reference-chapter-pages.json`; every chosen page falls inside the existing chapter's page run and the official 1398 subject/edition scope. The verified Torabinejad hash starts `71f21c26a4f1`; van Noort starts `7e993e5c5097`; Neville 4e starts `6fbc9bcba900`. The original citation labels are printed/legacy page numbers and must remain unchanged. The complete reference list is described in [1398 complete notice](RESIDENCY_1398_COMPLETE_NOTICE_20261010.md).

## Safety and publication procedure

`scripts/references/repair_bound_pdf_pages.php` is a narrow operator for repairing **existing** AI-origin non-human-reviewed source rows only. Its only permitted SQL update sets `bank_question_sources.pdf_page` and `pdf_sha256`, with expected source ID/edition/node/page/hash verified under row locks. It reopens the exact approved server PDF by `verified_reference_pdf.py`, validates the same-page phrase and hash-bound chapter boundary, and checks current official-year validity. Ineligible, human-reviewed, already-repaired, modified, out-of-scope and missing-original-PDF rows fail closed. It never updates a question, option, answer, old printed label, approved human review, published exam or attempt.

### Completed controlled publication

1. [PR #309](https://github.com/ArianGhsm/FanoosLearn/pull/309) passed all five CI jobs. It merged at `cd3b7913640dcb6ccdcb471e637e9e3236b89c4e`. The exact main SHA then passed the five push CI jobs.
2. Authorized official updater deployed that SHA. Production `/health` was `ok`; `scripts/dev/check-sync.sh` verified updater checkout and active release both equal GitHub main.
3. Source-repair operator dry-run against the **live production DB**, after hash-bound direct PDF page checks: `count=13`, no writes.
4. Pre-import strict same-row count was **89** of **247**. Fresh official backup `/var/backups/fanoos/20261011T143835Z-3e7ca9e5` independently passed `verify-backup.php` (**114 files**).
5. The operator ran as `fanoosupd` in a single transaction with `--apply`, full backup and unique protected receipt. It returned `mode=applied`, `count=13`, `only_columns_changed=[bank_question_sources.pdf_page, bank_question_sources.pdf_sha256]`.
6. Independently queried all 13 actual source IDs: **13/13** stored `pdf_page` and full `pdf_sha256` matched the proposal; existing node, edition, legacy page, question number and non-reviewed provenance were unchanged. New strict count **102/247**. No question/option/key/attempt/frozen-assessment table was written by the operator.
7. Receipt: `/srv/fanoos/shared/research/classification/reports/residency-1398-13-exact-pdf-pages-applied-20261011.json`, SHA-256 `9e559f7d3261322d92d77179069e473de0c81abcdd5b193cabb8e3f37b395677`.

These commands illustrate the **executed** operation for recovery/audit. They are **not** to be replayed on the same records: already-bound sources now correctly fail the update guard.

```sh
export FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php
php scripts/references/repair_bound_pdf_pages.php \
  --file=/srv/fanoos/shared/research/classification/reports/residency-1398-13-verified-bound-pdf-page-proposals-20261011.json
php scripts/ops/verify-backup.php /var/backups/fanoos/20261011T143835Z-3e7ca9e5
php scripts/references/repair_bound_pdf_pages.php \
  --file=/srv/fanoos/shared/research/classification/reports/residency-1398-13-verified-bound-pdf-page-proposals-20261011.json \
  --apply --backup=/var/backups/fanoos/20261011T143835Z-3e7ca9e5 \
  --receipt=/srv/fanoos/shared/research/classification/reports/residency-1398-13-exact-pdf-pages-applied-20261011.json
```

Do not invent PDF-page offsets from printed-page labels or republish a frozen assessment without its distinct, authorized exact-sitting diff and review workflow. The English book-exemption remains source-free and is counted separately.


## Outstanding

- The 13 source-page repairs listed above **are complete in the live bank**. Frozen exam explanations and old assessment versions were **not** republished; review any assessment presentation separately.
- Other 1398 clinical questions remain under separate original-book evidence investigation; incomplete original sources and any human-reviewed records remain preserved.
- Additional read-only, verbatim chapter matches observed: periodontics Q144 (Carranza 12e chapter 45 PDF 981) and restorative materials Q181 (Craig 14e chapter 13 PDF 288). These two remain **preliminary discovery**, absent from the 13-row verified/applied receipt; check current validity and provenance before repair.
- Twenty historical oral-pathology source anchors carried a nearest-edition (Neville 5e) qualifier despite matching 1398 official edition Neville 4e in the source catalog. Two (Q64 and Q78) were independently located verbatim in Neville 4e and repaired; the others must be checked against 4e page evidence rather than fabricating a PDF offset.
- Never copy private PDF text, whole-book extracts, raw question/option text or identifiable production data to GitHub. Limit long-running PDF comparisons to one page in memory.
