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
