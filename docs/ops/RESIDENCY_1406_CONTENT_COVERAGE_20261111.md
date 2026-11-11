# Residency 1406 content coverage — Phase 0 read-back, 2026-11-11

Phase 0 of `docs/product/11_STUDY_CONTENT_1406_PLAN.md`: how many residency
questions can get a 1406 explanation today, and what blocks the rest.

**Read-only** production read-back from `fanoos_prod` on the authorized
FANOOS server, at deployed `main` `127fe9e`. Counts only: no question text,
book text or user data was read out or written. No production row changed.
Temporary files lived in one `mktemp -d` directory under `/tmp`, removed by a
`trap` on exit (verified: none left).

## Method

- Universe: `bank_questions` in residency sittings (`bank_exam_sittings` →
  `bank_exam_types` key `residency`), any status.
- 1406 editions: `bank_reference_validity` rows for residency, `exam_year=1406`,
  `is_official=1` (19 rows), with their `scope_chapters`.
- Each question is counted **once per subject**, in its best state over all
  its `bank_question_sources` rows:
  - **A_in** — a source on a 1406 edition with `node_id`, `pdf_page` and
    `pdf_sha256`, whose chapter (the node's `chapter` ancestor) is in that
    edition's 1406 scope. Ready for a 1406 explanation.
  - **A_out** — the same, but the chapter is outside the 1406 scope.
  - **B** — node on a 1406 edition, no PDF page.
  - **D** — sources only on an **older edition** of a book that is official
    in 1406 (for example Neville 4e). Needs a 1406 page.
  - **F** — sources only on references that are not official in 1406.
  - **G** — no source row.
- Also read: `bank_edition_mappings` rows, `bank_explanations` rows,
  `bank_visuals` by status.

## Results

Bank: 10,533 questions; residency 3,202 (3,172 published, 30 draft), sittings
1391, 1392, 1395–1405. Residency English (239) has no book reference and is
outside this count.

| Subject | Residency Q | A_in | A_out | B | D | F | G | A_in by year 1401 · 1402 · 1403 · 1404 · 1405 (of 20; 10 community/materials, 30 prostho) |
|---|---:|---:|---:|---:|---:|---:|---:|---|
| community-dentistry | 130 | 88 | 3 | 0 | 0 | 31 | 8 | 10 · 10 · 10 · 10 · 10 |
| dental-materials | 129 | 31 | 13 | 0 | 53 | 30 | 2 | 0 · 0 · 4 · 8 · 8 |
| endodontics | 259 | 163 | 0 | 0 | 80 | 1 | 15 | 20 · 20 · 20 · 20 · 20 |
| operative-dentistry | 259 | 180 | 25 | 1 | 5 | 29 | 19 | 16 · 14 · 15 · 17 · 20 |
| oral-medicine | 254 | 130 | 8 | 0 | 105 | 0 | 11 | 0 · 11 · 11 · 19 · 20 |
| oral-pathology | 253 | 68 | 3 | 0 | 177 | 0 | 5 | 0 · 0 · 0 · 20 · 20 |
| oral-radiology | 246 | 206 | 5 | 0 | 27 | 0 | 8 | 20 · 20 · 20 · 20 · 20 |
| oral-surgery | 265 | 224 | 0 | 0 | 9 | 13 | 19 | 20 · 20 · 20 · 20 · 20 |
| orthodontics | 259 | 197 | 1 | 0 | 46 | 0 | 15 | 20 · 20 · 20 · 19 · 20 |
| pediatric-dentistry | 259 | 132 | 1 | 0 | 24 | 71 | 31 | 0 · 20 · 20 · 20 · 20 |
| periodontics | 260 | 90 | 4 | 0 | 150 | 0 | 16 | 0 · 0 · 0 · 19 · 19 |
| prosthodontics | 390 | 317 | 15 | 0 | 31 | 0 | 27 | 28 · 26 · 29 · 30 · 30 |
| **Total** | **2,963** | **1,826** | **78** | **1** | **707** | **175** | **176** | |

Other read-backs:

- `bank_edition_mappings`: **0 rows**. No old-edition chapter is mapped to its
  1406 chapter, so the 707 **D** questions cannot inherit a 1406 chapter; each
  needs its own 1406 page (one-pass source location, principles §3).
- `bank_explanations`: **0 rows**. `bank_visuals`: **0 rows**.

Most-asked 1406 chapters with A_in questions (questions per chapter):
Sturdevant 7e ch10 (28) and ch2 (25); Burket 13e ch3 (25); Hupp 7e ch25 (15);
Proffit 6e ch11 (22) and ch6 (21); White & Pharoah 8e ch1 (20); Torabinejad 6e
ch11 (18); Zarb 13e ch7 (15); Summit 4e ch7 (15). The per-chapter list for all
19 editions is reproducible with the method above.

## What this means for the plan

1. **1,826 questions (62 % of 2,963) can get a 1406 explanation now**: their
   page on the 1406 PDF is recorded. Oral surgery, radiology, orthodontics,
   prosthodontics and endodontics are nearly complete for 1401–1405.
2. **707 questions need a 1406 page first** (D), concentrated in oral
   pathology (177, all of 1401–1403), periodontics (150), oral medicine (105),
   endodontics (80) and materials (53). These are older sittings classified
   against the edition of their own year.
3. **78 A_out questions** sit in chapters the 1406 list no longer covers
   (mostly operative and materials). They get an explanation marked
   «خارج از منابع ۱۴۰۶», and no lesson.
4. **175 F + 176 G** need source location from scratch; F includes
   questions whose book is no longer official (for example pediatric 71,
   community 31).

## Next safe step

Phase 1 (lesson reader) and Phase 3 (explanation pipeline) can start; the
pilot (Phase 4) should take a chapter whose questions are all A_in. Before
each content batch, repeat this read-back and the PDF/map check of plan §2.
