# Dentistry residency 1398 — exact source-book chapter and PDF-page repair

**Status (2026-10-11):** source-book evidence verified for a first batch of 11 previously incomplete existing citations, **not yet applied to production**. This is a reproducible pre-publication record, not a completed import. Canonical owner rules are [PROJECT_PRINCIPLES](../PROJECT_PRINCIPLES.md) and the [atomic chapter classification policy](../product/09_CHAPTER_CLASSIFICATION.md).

## Baseline and evidence

At the original 2026-10-11 production read-back: **247** bank questions in the 1398 residency sitting, **89** with chapter node, `pdf_page` and `pdf_sha256` on the same source row, **158** without this strict triple. Of those 158, **20** English questions are book-citation-exempt (only if official final/amended answer is valid), and **138** clinical questions still lack a complete pair. There are **152** questions with source rows, including the 89 recorded-complete. Re-measure all these counts after any import: the live figures can change under other workers.

The protected evidence record is:

`/srv/fanoos/shared/research/classification/reports/residency-1398-11-exact-pdf-pages-proposal-20261011.json`

Last independently read SHA-256: `a4b7ce021b18f2bd642963be9bf0bc20f56e80263491b7110cd0ccd9eb751518` (must recheck before use). Its 11 source IDs, old field values, edition keys, SHA-256 hashes, chapters, and brief evidence phrases stay **only** on the protected server.

| Exam question | Exact year-1398 official reference | Existing chapter | Verified PDF page |
|---|---|---|---:|
| 5 | Torabinejad Endodontics, 5e | 5 | 89 |
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

Evidence was checked against the **actual original private PDF pages**, never an inferred printed/PDF offset. The reference PDF hashes were bound to `data/bank/reference-chapter-pages.json`; every chosen page falls inside the existing chapter's page run and the official 1398 subject/edition scope. The verified Torabinejad hash starts `71f21c26a4f1`; van Noort starts `7e993e5c5097`. The original citation labels are printed/legacy page numbers and must remain unchanged. The complete reference list is described in [1398 complete notice](RESIDENCY_1398_COMPLETE_NOTICE_20261010.md).

## Safety and publication procedure

`scripts/references/repair_bound_pdf_pages.php` is a narrow operator for repairing **existing** AI-origin non-human-reviewed source rows only. Its only permitted SQL update sets `bank_question_sources.pdf_page` and `pdf_sha256`, with expected source ID/edition/node/page/hash verified under row locks. It reopens the exact approved server PDF by `verified_reference_pdf.py`, validates the same-page phrase and hash-bound chapter boundary, and checks current official-year validity. Ineligible, human-reviewed, already-repaired, modified, out-of-scope and missing-original-PDF rows fail closed. It never updates a question, option, answer, old printed label, approved human review, published exam or attempt.

After a green reviewed PR and authorized updater deployment, and only with another production read-back and fresh verified **full backup**, operate as the `fanoosupd` service account with the approved runtime configuration:

```sh
export FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php
php scripts/references/repair_bound_pdf_pages.php \
  --file=/srv/fanoos/shared/research/classification/reports/residency-1398-11-exact-pdf-pages-proposal-20261011.json

# Only if dry-run accepted all 11, and after a fresh verify-backup.php:
php scripts/references/repair_bound_pdf_pages.php \
  --file=/srv/fanoos/shared/research/classification/reports/residency-1398-11-exact-pdf-pages-proposal-20261011.json \
  --apply --backup=/var/backups/fanoos/<fresh-verified-id> \
  --receipt=/srv/fanoos/shared/research/classification/reports/<unique-verified-1398-repair>.json
```

Never hand-author a source update or use a broad backfill from the legacy printed `page` values. Do not run an import against a changed source row or concurrently with a competing 1398 writer. After apply, independently check 11 same-row triples and initial/final strict completed totals (expected 89 → 100 **only if the baseline is still 89 and all 11 are still incomplete**). Re-read protected receipt, validate frozen assessment/source presentation, deploy SHA and `/health`, and record actual completion. The 20 English exceptions are never given synthetic chapter/source entries.

## Outstanding

- Until production SQL and audited receipt confirm, **no additional classification completion is claimed**.
- Other 1398 clinical questions remain under separate original-book evidence investigation; incomplete original sources and any human-reviewed records remain preserved.
- Additional read-only, verbatim chapter matches observed on 2026-10-11: periodontics question 144 (Carranza 12e chapter 45 PDF 981), restorative-materials question 181 (Craig 14e chapter 13 PDF 288). These two are **preliminary discovery**, not included in the first 11 proof package; each needs an independent current source/official-scope check before any repair.
- Never copy private PDF text, whole-book extracts, raw question/option text or identifiable production data to GitHub. Limit long-running PDF comparisons to one page in memory.
