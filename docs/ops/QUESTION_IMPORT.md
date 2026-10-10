# Importing exam papers from Google Drive

How the owner's question papers (Word files on Google Drive) reach the bank.
Binding rules: `docs/product/06_QUESTION_FORMAT.md` (sitting format, §5
publication), `docs/PROJECT_PRINCIPLES.md` §4–5 (verified backup, no
production write from a clone). Tool: `scripts/import/drive_batch.py`.

## What is on Drive (2026-10-11)

Folder `سوالات ورد بورد／رزیدنتی／ارتقا` of the `gdrive` rclone remote (root's
config on the FANOOS host), all `.docx`:

| Exam | Years | Papers | In the bank |
|---|---|---|---|
| بورد (board) | 1400, 1401 | 8 specialties each | no |
| بورد | 1403 | 8 specialties (+ an archive of older copies, skipped) — **no valid key** | no |
| بورد | 1404 | 10 | yes |
| ارتقا (promotion) | 1404, 1405 | 10 each | yes |
| ملی (national) | 1396–1405 | 15 (مرداد and دی sittings; 1396 has forms A and B) | no |
| دستیاری (residency) | 1391–1393, 1395–1397 | 6 | no (1398–1405 are) |
| `۹۰ ـ منابع و نسخه‌های ناقص` | — | partial copies, a promotion bundle | skipped |

About 7,500 new questions. The papers share one layout: «سؤال N ـ …» /
«سؤال 001 | …» / «N. …», options الف–د, a «پاسخ کلیدی: …» line, often a
«منبع درج‌شده در دفترچه: …» line, subject headings in multi-subject papers,
and a key table at the end.

## Identity of a paper

- `exam_type`: residency, national, board, promotion.
- `round`: residency 1 (one form imported, A); national 1 = مرداد, 2 = دی;
  board and promotion: the specialty's stable slot — 1 endodontics,
  2 periodontics, 3 prosthodontics, 4 operative-dentistry, 5 oral-surgery,
  6 oral-medicine, 7 oral-pathology, 8 oral-radiology, 9 orthodontics,
  10 pediatric-dentistry (the same in every year; never reuse a slot).
- A specialty paper's questions are all filed under its specialty.

## Keys

- «کلید اولیه» → `preliminary`; a final key → `final`; «حذف» → `voided`;
  several accepted options → `also_correct`.
- «نامشخص» (no valid key, e.g. board 1403) → `disputed` with no choice: the
  question is in the bank, browsable, **never in a scored exam** until a key
  is found and the paper re-imported with it (migration 0044 allows this one
  shape).
- The booklet's «منبع درج‌شده در دفترچه» is stored as `booklet_source`: a hint
  for chapter classification, never a source (sources need the book page,
  09).

## The three steps (on the FANOOS host, as root)

```sh
cd /srv/fanoos/current
# 1. what is there -> a manifest to read and correct
python3 scripts/import/drive_batch.py discover --workspace=<workspace uuid> \
    --folder="سوالات ورد بورد／رزیدنتی／ارتقا" --out=/root/drive-manifest.json
# 2. download, convert, check, dry-run (writes nothing to the bank)
python3 scripts/import/drive_batch.py prepare --manifest=/root/drive-manifest.json --batch=<name>
# 3. verified backup, then import (and publish)
sudo -u fanoosupd env FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php php scripts/ops/backup.php
python3 scripts/import/drive_batch.py import --batch=<name> --backup=/var/backups/fanoos/<id> \
    --publish --actor=<owner uuid> --reviewer=<owner uuid>
```

- **Manifest review:** every entry marked `review` needs its type, year,
  round or subject set by hand; `skip` entries (form B, archives, partial
  copies) stay skipped. Remove papers already in the bank (board 1404,
  promotion 1404/1405) or let `prepare` mark them `already-in-bank`.
- **Report:** `/srv/fanoos/staging/question-import/<batch>/report.json`,
  per paper: questions, voided, no valid key, preliminary, booklet sources,
  images, every converter warning (absent numbers, missing options, answer
  lines not understood, key-table disagreements) and the dry-run counts.
  Read every warning before importing; fix the Word file on Drive (and
  prepare a new batch) rather than editing the converted JSON.
- **Status:** `ready` imports; `already-in-bank` and `exists-and-would-change`
  are never imported by this tool (a wording correction of a live paper is a
  separate approved operation, 06 §5); `error` and `needs review` stop that
  paper only.
- `import` stops at the first failure and writes `receipt.json` (backup,
  papers imported and published).
- After a batch: read back the counts per sitting, check a paper on the
  site, then delete the batch folder (`rm -rf
  /srv/fanoos/staging/question-import/<batch>`): it holds private question
  text and is not part of any backup.

## Safety that holds for every import

- Re-importing a sitting never erases its classification: sources and
  concepts are replaced only when the file lists them (06 §2).
- A reviewed answer, source, concept or explanation is never overwritten.
- Questions keep their key `type-year-round-number`; changing a paper's round
  later creates new questions, so settle the manifest before importing.
- Chapter classification of the new papers is a separate step (09): each
  source names the reference, edition, chapter, PDF page, printed page and
  the PDF's SHA-256.
