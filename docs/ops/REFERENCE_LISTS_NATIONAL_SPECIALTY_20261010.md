# National, board and promotion reference lists — 2026-10-10

**Status:** APPLIED to production and live on `/app/references`.
**Owner request (2026-10-10):** find the official reference lists of the
national exam (آزمون ملی) and the specialty board exams for every year and show
them like the residency lists.

## Sources

Read from the FANOOS host on 2026-10-10 (sanjeshp.ir is reachable from there,
not from outside Iran): `https://www.sanjeshp.ir/Content.aspx?click=26`
(منابع آزمون‌ها). The page links each list by ASP.NET postback; the archive
page (`click=27`) and the per-exam pages (`click=4`, `click=7`, `click=30`)
carry only announcements and a few old correction notices, **not older
lists**. What was published, and transcribed:

| Exam | Years | Document |
|---|---|---|
| آزمون ملی دانش‌آموختگان دندانپزشکی | دی ۱۴۰۴ (دوره ۲۴); ۱۴۰۵ (دوره‌های ۲۵ و ۲۶) | «منابع آزمون ملی دی ماه 1404- دوره 24» (one-page table); the center's own row says 1405 uses the same list |
| دانشنامه/گواهینامه (بورد) و ارتقا | ۱۴۰۵ | zip «منابع آزمون های دانشنامه/گواهینامه و ارتقای رشته های تخصصی دندانپزشکی سال 1405» (ten specialty PDFs) |
| same, operative | ۱۴۰۵ | «اصلاحیه منابع آزمون دانشنامه/گواهینامه تخصصی دندانپزشکی ترمیمی سال 1405» (آذر ۱۴۰۴) — supersedes the zip's operative list |
| same, prosthodontics | ۱۴۰۵ and ۱۴۰۶ | «منابع آزمون ارتقاء و دانشنامه/ گواهینامه تخصصی پروتزهای دندانی ۱۴۰۵ و ۱۴۰۶» — supersedes the zip's prosthodontics list |
| promotion, orthodontics | ۱۴۰۵ | «اطلاعیه اصلاح منابع آزمون ارتقای دستیاری رشته ارتودانتیکس سال 1405»: Proffit 2019 → 2026 (promotion only) |

Transcriptions with their per-row sources: `docs/research/dental-national-references-1404.json`,
`dental-specialty-references-1405.json`, `dental-specialty-references-1406.json`.
Scanned or garbled PDF text was read from page images, not from the text layer.

**Not available:** board 1404 and promotion 1404 lists (the bank holds those
sittings: board 1404 and promotion 1404/1405, 1,000 questions each), and any
national list before دی ۱۴۰۴. They are not on sanjeshp.ir any more; a copy from
the owner or a verified mirror is needed. Do not infer them from the 1405 lists.

## Catalog change (PR #267, merge `c137c368`)

`scripts/import/reference_map_to_catalog.py` reads every
`docs/research/dental-*-references-*.json` (a file names its `exam_types` and
`also_years`; a row may name its own exam types). Result: new exam type
`national` (آزمون ملی); +188 validity rows (board 76, promotion 76, national
36); 35 new books plus one «مقاله‌ها و ژورنال‌های اعلام‌شده» entry per
specialty and year; new editions Proffit 7e, White & Pharoah 9e, Sturdevant 8e,
Zarb 14e. Residency validity rows are byte-identical. New editions are listed as
`missing` in `data/bank/reference-texts.json`. 28 review notes record
edition-from-year judgements for the owner to confirm.

## Production import

Only the added part was imported, so no existing node or validity row was
rewritten (`scripts/import/catalog_subset.py`, old = `c137c368^1`, new =
`c137c368`):

1. `import-bank.php check` and `import --dry-run` on the subset: 4 exam types,
   13 subjects, 49 references, 50 editions, 0 nodes, 188 validity rows.
2. Full backup `/var/backups/fanoos/20261010T181449Z-c436d8f9`, verified by
   `verify-backup.php`: 54 files.
3. `import-bank.php import` of the subset (SHA-256
   `557fe047a38dbff316684b028b888661446f0d37b59b0b07d9e15e11c4b2ede8`),
   workspace `01a1037b-0322-7c8f-8904-7c9334fb9cbe`: same counts.
4. Read-back of `bank_reference_validity`: board 1405 67, board 1406 9,
   promotion 1405 67, promotion 1406 9, national 1404 18, national 1405 18;
   residency rows unchanged (19–20 per year). No question, answer, source or
   assessment was touched.

## To reproduce or extend

Add or correct a `docs/research/dental-*-references-<year>.json`, run
`python scripts/import/reference_map_to_catalog.py`, merge and deploy, then on
the server: build the subset from the previous and new catalog with
`catalog_subset.py`, `check`, `import --dry-run`, verified backup, `import`,
read back. If the subset script refuses (an old row changed), import the whole
catalog after the same checks.
