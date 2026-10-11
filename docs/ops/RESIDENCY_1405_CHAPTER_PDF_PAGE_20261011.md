# Residency 1405 — exact chapter and verified PDF-page completion (2026-10-11)

## Production baseline and policy

The live FANOOS production sitting `residency:1405:1` contains **250 questions**. Read-only, same-source-row audit: **82/250** possess chapter `node_id`, numeric `pdf_page`, and current-PDF `pdf_sha256` together; **168** lack the complete trio. Of those 168, **20 English** questions are exempt from textbook source citation subject to final official answer-key integrity, leaving **148 book-based incomplete**. The 143 incomplete existing-source records must be repaired in place when evidence warrants it; **5 oral-radiology questions** have no source row and need the approved source-only insertion workflow. Presence of fields is not independently proven scientific correctness.

| Subject | All | Strict recorded | Still book-based incomplete |
|---|---:|---:|---:|
| Orthodontics | 20 | 20 | 0 |
| Endodontics | 20 | 0 | 20 |
| Oral pathology | 20 | 0 | 20 |
| Oral medicine | 20 | 4 | 16 |
| Prosthodontics | 30 | 9 | 21 |
| Periodontics | 20 | 0 | 20 |
| Oral surgery | 20 | 0 | 20 |
| Operative dentistry | 20 | 20 | 0 |
| Pediatric dentistry | 20 | 1 | 19 |
| Oral radiology | 20 | 15 | 5 |
| Dental materials | 10 | 4 | 6 |
| Community dentistry | 10 | 9 | 1 |
| English (reference exemption) | 20 | 0 | 0 |
| **Total** | **250** | **82** | **148** |

This report distinguishes research validation, production update and published assessment validation. Never report a research-only candidate as live completion.

## Exact Q222 recovery, evidence-bound validation

- Existing source row for **1405 Q222**, community dentistry, was **chapter 4**, legacy printed page `57`, `printed_page=57`, `pdf_page=NULL`, `pdf_sha256=NULL`, `origin=ai`; its source is already present. Therefore **no source INSERT** may be attempted.
- Its official exact edition is `national-oral-health@1394`, inside the explicitly announced 1405 chapter 1–16 syllabus. The current approved private PDF has SHA-256 `d1cc93cdc6d567cad554cdb08329914ca39e043bc183c587d637f322da20ae66` (330 pages). The original **PDF page 57** carries the population-births-minus-deaths definition and printed label 57. The verified hash-bound map assigns PDF page 57 to **chapter 4**.
- A single-question **research-only** package lives under `/srv/fanoos/shared/research/classification/recovery-1405-community222-20261011/`. Original question, options and official answer remained equal. The exact-page `apply_classification.py --pdf-pages` validator returned **1 accepted, 0 rejected**. An independent `audit_private_study_batches.py` run returned **1 accepted, 0 pending**, with current approved PDF hash.
- Private hashes: original study `31d99e3f47e6be3f555ab32eb9803ab27cec05284517e41a3f025519a6cbdb9b`; decision `790c76e6eb445e8121a265aa33f229f2e46f6d1cf0e30c53620a85fceb892903`; validated sitting `ac694af120af755e20bef83d2a81dcd6fe4e9acf4069f8e060ce6e14b3fe10cc`; **independent audit SHA-256** `db8156cf77f5f91208bb9666f0ea432e9b4039042c12ab33194bc8503467175b`.
- Q229 was independently rechecked against same verified edition, chapter 14, PDF page 200, but already has all three required fields in production; do **not** count it as newly completed or reimport it. QA-only protected package at `/srv/fanoos/shared/research/classification/recovery-1405-community229-20261011/`.

## Source-only repair procedure for existing page-less records

The versioned `scripts/references/repair_existing_source_pdf_page.php` is the controlled addition-only metadata operator for this exact reviewed/printed-page-aligned case. It requires one original study question, one PDF-page-validated matching decision, one provenance audit, exact audit SHA, official same-year scope, unique approved current reference PDF, matching existing AI source edition/chapter/page, unchanged published question/options/latest official answer, no human source review, source `pdf_page`/hash both NULL and no duplicate source. Only two existing columns may change: `pdf_page` and `pdf_sha256`; chapter, printed page, legacy page, original source, answer, question, review, frozen exams and attempts are preserved.

**Prerequisites:** reviewed green PR and merge SHA, green merge CI, official updater deployment, fresh verified complete backup, private case/audit SHA, dry-run preview with `applied=false`, followed by authorized apply with unique private receipt, live read-back (including unchanged other fields and totals), and published-assessment check when necessary. Do not use this operator for missing source records or for printed labels that differ from PDF page numbers; those require their own validated evidence and safe workflow.

Illustrative commands, substituting only the private actual dentistry workspace UUID and a fresh validated backup path:

```sh
FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php php \
  scripts/references/repair_existing_source_pdf_page.php \
  --workspace=<verified-workspace-uuid> --year=1405 --subject=community-dentistry \
  --number=222 --stem=community222 \
  --case-root=/srv/fanoos/shared/research/classification/recovery-1405-community222-20261011 \
  --audit-sha=db8156cf77f5f91208bb9666f0ea432e9b4039042c12ab33194bc8503467175b

# After verifying a fresh complete backup:
# run the identical command as fanoosupd and add
# --apply --backup=/var/backups/fanoos/<verified-backup-id>
# --receipt=/srv/fanoos/shared/research/classification/recovery-1405-community222-20261011/classification/reports/1405-community222-live-repair.json
```

**Handoff status:** independent scientific Q222 validation complete and protected case available. Production has not been changed merely because its research case exists. Update this checkpoint with CI, merge, deploy, backup receipt, live source count and exam publication verification **only after each actually succeeds**.

## Remaining work

Proceed by official year validity and exact approved, hash-bound reference PDF, prioritizing old page-less AI rows and reporting blocked partial-page, unavailable edition and human-review cases separately. For subjects with many printed labels, never convert printed page into PDF page without direct book-page evidence and chapter-boundary/scope verification. Use official snapshot SQL in `BANK_CLASSIFICATION_PAGE_BACKLOG_20261011.md` after each applied repair; English remains exempt without synthetic source rows.
