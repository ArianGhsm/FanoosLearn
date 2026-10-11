# Chapter scope and direct-PDF map checkpoint — 2026-10-11

## Authority and starting point

Read `docs/PROJECT_PRINCIPLES.md`, `docs/product/09_CHAPTER_CLASSIFICATION.md`,
`docs/ops/RESIDENCY_PROMOTION_CLASSIFICATION_20261011.md`, and
`docs/ops/NATIONAL_CLASSIFICATION_1404_1405.md` before resuming. PR #290 is
the latest classification handoff: residency 1397 has 63/247 sources; promotion
1405 rounds 4, 6, 7 and 10 have 16, 78, 56 and 35/100 respectively. Those
are published counts, not evidence that every chapter fits the official
syllabus. The server's updater checkout and live release both matched GitHub
`main` `524ec2b25a8ce6325ab95b26b66e25bcb50f7140`; the read-only server
`scripts/dev/check-sync.sh` returned `in sync`. The approved private library
inventory remained 42 verified PDFs of 94 catalog editions.

## Live syllabus audit (read-only)

For each existing source, join its sitting exam type/year/subject and cited
edition to `bank_reference_validity`, then compare the cited chapter number
with that row's explicit `scope_chapters` array. This does **not** resolve
partial page/section limits and does not by itself justify changing a
historical human-reviewed source. On the 2026-10-11 read-back, **126 source
rows** were outside an explicitly listed chapter: residency **106** (two
human-origin, 33 with a PDF page), national **8**, promotion **12**. The
newest published sittings account for **25** of the 126:

| Sitting | Out-of-scope question numbers |
| --- | --- |
| national 1404-2 | operative 105; oral medicine 25, 27, 30, 32, 34; radiology 124 |
| national 1405-1 | endodontics 16 |
| promotion 1405-4 | operative 59 |
| promotion 1405-6 | oral medicine 11, 14, 16, 30, 32, 73, 83, 100 |
| promotion 1405-10 | pediatric 19, 31, 47 |
| residency 1397-1 | operative 61, 80; orthodontics 1, 2, 20 |

The other **101** are older residency rows and need individual catalog/PDF
and human-review checks. The audit compared exact exam type/year/subject,
edition and chapter, not a broad subject-only syllabus. It exposed a real
guard gap: `apply_classification.py` checked an official edition and a PDF
chapter map but did not reject a chapter outside `scope_chapters`; full
`BankImporter` could likewise accept one. This branch adds both guards.
The existing source-only publisher already has its own official-scope gate.
**No production source, question, answer, attempt or assessment was changed
by this audit or map research.** Do not bulk delete the 126 rows: verify the
official notice and original PDF page for each, preserve human decisions,
then plan a separate audited correction with a verified backup and read-back.
The complete protected 126-row queue, with source row IDs, exact edition,
chapter, recorded PDF page/hash and official chapter list but no question
stems or book text, is `classification/reports/out-of-scope-source-ledger-20261011.json`
(SHA-256 `4439620d87b6d20585f3e1fb14a462572c9042f483c81f61537b56a518186d42`).

The wider live bank read-back found **10,295** questions: board 2,600 with
zero source rows; national 2,493 with 224 sourced; promotion 2,000 with 185
sourced; residency 3,202 with 1,760 sourced. All 2,169 existing source rows
had an exact official year/subject/edition validity row. The national 224
and promotion 185 source rows have PDF page and PDF SHA-256 fields. In
residency, **1,122 of 1,760** source rows have neither field: they are legacy
citations and must not be presented as page-verified direct-PDF work merely
because a chapter is present. Residency English remains exempt only under
the separately recorded official-key rule; raw unsourced totals are not a
scientific completion measure by themselves. These are time-stamped counts,
not permanent invariants.

## Exact PDF boundary work on this branch

The current approved object SHA-256 was read through
`verified_reference_pdf.py`; only selected PDF pages were inspected in memory.
No PDF text file, OCR export, index, copied PDF or task-only script was made.
The map changes are proposed code/data until reviewed, merged and deployed.

| Edition | Current approved PDF SHA-256 | Boundary evidence and correction |
| --- | --- | --- |
| Torabinejad 5e | `71f21c26a4f1e5d038ff6aa408669a5cc040e90c94eea0bf2e00478997710146` | PDF 1–12 are cover/front matter; chapter 1 opens on PDF 13. All 25 openings were checked; several old boundaries preceded the actual heading by 1–3 pages. Chapter 25 ends on PDF 465; the appendix and index on 466–494 are unassigned. This repairs the map for residency 1397 endodontics. |
| Rosenstiel 6e | `197df557a38e8dd429c04f220a1b56102ed9eca52480283ed633a8042dd43b22` | PDF 1–11 are front matter. Every chapter opening (1–31) was checked directly; multiple old starts were one page early. PDF 787 still belongs to chapter 26, 788 opens 27, and 823 says it is intentionally blank. Chapter 31 ends on PDF 923; index pages 924–945 are unassigned. The corrected map is bound to this exact PDF. |
| Sturdevant 7e | `4ab53ca38252912464f30f165d1ab39e4f0ce907159897f9e15633254acd18ec` | PDF 1–13 are front matter; all 18 chapter opening pages were checked and preserved. Chapter 18 ends on PDF 677; index pages 678–697 and blank pages 698–699 are unassigned. The map is now bound to this PDF. |
| Van Noort 5e | `c70c5069699478dcfdeb78850345e7ad52770810e65a21faae9d6afab409a8da` | PDF 1–13 are front matter. All 23 actual chapter openings were checked; prior ranges repeatedly started before the chapter and omitted 3.1. Section divider pages 73 and 167 are unassigned. The corrected map is bound to this PDF. |

The four maps now cover every PDF page exactly once, leaving front matter,
section dividers and blank pages unassigned. Carranza 14e stays **pending**:
its registered PDF has only 105 supplementary pages, while the old map runs
to page 1875. It cannot be made eligible by padding a map.

## Follow-up private research, no production import

Using the PR #291 worktree and only the exact approved PDFs, two more
site-matching batches were validated. The private decision files contain
short page quotes; no question text or page text was added to Git. All cited
pages were also inspected as original PDF page images streamed in memory, with
no raster or text file left behind. Both sittings were compared against live
subject assignments in addition to the import dry run, because
`questions_changed` does not count a subject change.

| Sitting | Original PDF | New research decisions | Validator | Import dry run | Live source rows changed |
| --- | --- | --- | --- | --- | --- |
| residency 1397-1, endodontics | Torabinejad 5e, 494 pages | Q83, 84, 86, 89, 90, 92, 100 (7/20) | 7 accepted, 0 rejected | 247 questions; `questions_changed=0`, `answers_recorded=0`, 7 sources; 247/247 subjects match | 0 |
| promotion 1405-3, prosthodontics | Rosenstiel 6e, 945 pages | Q9, 10, 12, 13, 16, 21, 22 (7/100) | 7 accepted, 0 rejected | 100 questions; `questions_changed=0`, `answers_recorded=0`, 7 sources; 100/100 subjects match | 0 |

Protected decisions and read-back receipts:

- `classification/decisions/residency-1397-1/1397-endodontics.json`,
  SHA-256 `bfc231a3abc45a07d5983db3f8a95b8bef8c2d5eb365bbf3f42556beb7ae77d0`;
  `classification/reports/residency-1397-endo-direct-pdf-20261011.json`,
  SHA-256 `b85af89c21d763bb4418a59c0dc7d012a8a1182b180b8caf7329c0c1e3abe310`.
- `classification/decisions/promotion-1405-3/1405-prosthodontics.json`,
  SHA-256 `43b66f24545d6e2b996f048134be3c7ed0dbc23144838888c9454ab535860a8e`;
  `classification/reports/promotion-1405-r3-direct-pdf-20261011.json`,
  SHA-256 `3954912f9b6527874c2840e167aee64bc71dbdf08663f88da03d8599aa66682d`.

Both had **zero** live sources in their subject/round before this research.
The receipt names exact input/map/PDF hashes and visual pages. No verified
research recovery archive was made: the server already held its five most
recent full backup sets, so backup retention must be reconciled before any
production import. Do not claim these 14 decisions are on the site. Regenerate
validated sittings from the protected decisions after PR review, green merge,
updater deployment, and a fresh PDF/sitting read-back. Then repeat the import
dry run, make and verify a backup, import and publish with an audited receipt.
After receipt read-back and a process check, the two exact operation
directories (queries, draft decisions and generated validated sittings) and
the temporary PR worktree were removed. Only the protected decisions and
concise receipts remain from this research pass.

PR #291 (`fix/pdf-chapter-map-coverage-20261011`) had five green CI checks at
SHA `0560a40dcec43d027e623f7fe66f0fccd39ba28c`; it remained open and
unreviewed at this checkpoint. The server still ran `524ec2b` and was in
sync with its current GitHub `main`. Neither a branch push nor green CI is
a deployment or a bank data import.

## Research-workspace cleanup read-back

The follow-up exact-path cleanup removed two regenerated full-book Neville
text files (10,444,094 logical bytes), two clean W08 checkouts after their
PRs were resolved (43,999,232 allocated bytes), and the completed 48-file
national 1405 scratch operation. The protected decisions, handoffs, original
PDFs and live bank remained. Private receipts are
`classification/reports/artifact-cleanup-neville-text-20261011.json`,
`artifact-cleanup-w08-checkouts-20261011.json` and
`artifact-cleanup-national1405-probe-20261011.json`. A post-cleanup scan
found zero PDF text files, page/full/book-text JSON fields, bytecode or
temporary/partial files in the research tree and seven checked archives.
`research/tmp/` was empty; three remaining `.txt` files were checksum
manifests. The protected parallel folders still contain **127** historical
Python/PHP/shell/SQL research scripts. Some private handoffs explicitly use
them to reproduce unresolved audits. They need a separate exact-path review:
move reusable, non-private logic to versioned `scripts/`, preserve unique
pending evidence, then remove completed one-off scripts after confirming their
durable result and recovery path. Do not sweep them by extension.
The W04/W05/W06/W08 private handoffs were also marked historical where they
instructed whole-PDF text extraction. Current resume steps use the verified
one-page reader and private operation temp cleanup. A subsequent checksum
read-back verified 15 research manifests covering 198 files with no missing
or mismatched member. The original and replacement manifest hashes are kept
in `classification/reports/private-pdf-handoff-correction-20261011.json` and
`w08-handoff-pdf-only-correction-20261011.json`.

## Resume order and import gate

1. Review this branch's map boundaries and chapter-scope guard; merge only
   after green CI and review, then deploy through the updater and check live
   SHA/health/sync. Until then, the new maps and guards are not production.
2. Re-evaluate the 25 newest conflicts against each official scope notice and
   exact cited PDF page. Keep a corrected or pending decision privately; do
   not preserve an outside-scope citation merely by lowering confidence.
3. Continue eligible unsourced questions from the site-matching sitting for
   residency 1397 (Torabinejad 5e). The repaired Rosenstiel 6e map also makes
   a source available for promotion 1405 prosthodontics round 3 and more of
   operative round 4, subject to exact question/answer and scope checks.
   Board 1400/1401
   lack year-specific official validity rows; board 1404 lacks its official
   list; no board 1405/1406 sitting was present in the audited import record.
   Leave those pending rather than transferring another year's budget.
4. Each new decision requires the official year/subject/edition and partial
   syllabus limit, original PDF page and short verbatim evidence, current PDF
   SHA-256, validator acceptance, reviewed import preflight with
   `questions_changed=0` and subject comparison, verified backup, and live
   post-import read-back. Retain only the protected decision and audit receipt;
   remove the exact operation temp directory after its result is validated.
