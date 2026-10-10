# Drive question import — 2026-10-11

Owner request (2026-10-11): check that every paper in the Google Drive
folder «سوالات ورد بورد／رزیدنتی／ارتقا» is on the site, check each for
defects, and put the sound ones on. Tooling: `scripts/import/drive_batch.py`
(runbook `docs/ops/QUESTION_IMPORT.md`).

## Inputs

- Manifest `/root/drive-manifest-20261011.json` (86 .docx; 12 skipped as
  partial copies or link sheets).
- First batch `drive-20261011` (converter at 345005e) exposed converter
  defects: options left inside the stem («1) …», «الف ـ …», «الف( …»),
  questions 1–3 lost, «سؤال N» alone on a line, key lists read as questions,
  short stems taken for subject headings, the national basic-science block
  filed as oral pathology. Fixed in PR #283 and PR #285 (tests in
  `tests/import/test_docx_to_sitting.py`).
- Second batch `drive-20261011b` (converter at c087724, `--reuse` of the
  first batch's downloads). Report:
  `/srv/fanoos/staging/question-import/drive-20261011b/report.json`.

## Catalog change

New subject `basic-sciences` (علوم پایه) for the national exam's
basic-science block (1404 on). Subset built with `catalog_subset.py`
(old = 8b97515, new = c087724; 14 subjects, nothing else), SHA-256
`7f7b02c879985f27eff7dc389ef4e9668ffd3ba670b98fce3e40e70a1f73eb1e`.
Dry run, backup `/var/backups/fanoos/20261010T232203Z-73d71423`
(verified, 114 files), import, read-back: 14 subjects.

## Imported and published (32 papers)

Backup `/var/backups/fanoos/20261010T232759Z-051ae47e` (verified, 114
files), then `drive_batch.py import --publish --only=…`; receipt
`/srv/fanoos/staging/question-import/drive-20261011b/receipt-4602bd3f.json`.

| Exam | Papers | Questions | Key |
|---|---|---|---|
| board 1400 | 8 specialties | 800 | preliminary |
| board 1401 | 8 specialties | 800 | preliminary |
| residency 1391, 1392, 1395, 1396, 1397 | 5 | 1,207 | final (voided kept unscored) |
| national 1397-2, 1398-1, 1399-2, 1400-1/2, 1402-2, 1403-1/2, 1404-1/2, 1405-1 | 11 | 2,513 | final (1399-2 and 1403-1 partly/all preliminary) |

Read-back after import: 10,295 questions in the bank; 150 under
basic-sciences. Audit of every imported paper: no question without its four
options, no missing number except where the source itself lacks it
(residency 1397: 40, 210, 220; residency 1391 and national 1400-2 are
shorter documents — 220 and 183 questions).

## Not imported

- **No valid key** (status `disputed` throughout): board 1403 (8 papers),
  residency 1393, national 1396-1, 1399-1, 1401-1. Prepared and ready in the
  batch; import with `--only` once a key is found.
- **Already in the bank and the Drive copy differs** (`exists-and-would-change`):
  board 1404, promotion 1404, promotion 1405. A change to a live paper is a
  separate approved operation (06 §5); not touched.
- Promotion 1405-6 (oral medicine): about 19 questions are mangled PDF text
  with unreadable options; the converter leaves them out and lists them.

## Remaining

- Classification of the new national, board and residency papers per 09
  (exact edition with verified PDF and hash-bound map).
- Keys for the held papers; a reviewed comparison for the
  exists-and-would-change papers.
- Staging folders `drive-20261011` and `drive-20261011b` stay until the held
  papers are resolved (private, not in Git).
