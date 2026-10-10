# Residency orthodontics — verified audit checkpoint (2026-10-10)

Scope: orthodontics ONLY, examination years 1398–1405. Do not alter other subjects. This is an audit/research checkpoint, not a claim that existing source mappings have been clinically revalidated or imported.

## Live source-row audit

Read-only query against the authorized dentistry workspace of `fanoos_prod` found 159/159 residency orthodontics questions with existing `bank_question_sources` rows (1398:19; 1399–1405:20 per year). Every row currently identifies the same exact Proffit edition listed for its year: `proffit-orthodontics@5e` for 1398 and `@6e` for 1399–1405. Existing origin: 157 `ai` and 2 `human` (1404). **No questions/options/answers/human rows were changed by this audit.**

Research gaps: all 19 in 1398 have NULL page and an anchor that literally says `nearest edition` based on *6e*, despite the current official edition field being *5e*. These are legacy approximations, **NOT approved page-verified exact-edition assignments**. Nine of these have node confidence below 0.80. In 1404, two human rows have NULL pages and absent anchors; do not replace/override them without independent owner-reviewed evidence. Across 1399–1403, source rows have pages and anchors but remain subject to independent original-page verification. 1405 also remains subject to the same verification.

## Verified original reference inventory

Production private inventory confirms a verified, published `proffit-orthodontics@5e` PDF of 54,185,933 bytes (SHA256 `696d0358a297ca26e1d183de5356475dcec10b87ec9c46afcd3449159665eb22`) and `proffit-orthodontics@6e` PDF of 176,110,547 bytes (SHA256 `5f18cc196553b691635b0c136f9761a4e7c478bf115424d1c7f26f04b5b50015`). No reference PDF was publicly exported.

The standard exact-edition server extractor successfully verified and privately extracted 5e (745 PDF pages, 19 catalog chapters, text SHA256 `fc683dfd8d7c4fdff605b3e91591cbe9afb1fb70a4f033511428ded6ee6bfb27`) to `/srv/fanoos/shared/research/references/proffit-orthodontics@5e.txt`, with provenance companion.

The 6e extraction initially **correctly failed** its contiguous page-map gate: old `reference-chapter-pages.json` started chapter 1 at PDF page 5 without mapping pages 1–4. Direct original-PDF inspection further showed pages 1–10 are title/contents/front matter, page 11 is the Section I introduction, **PDF page 12 begins Chapter 1** and page 28 begins Chapter 2. This PR corrects the map to an unassigned front-matter run [1,11] and Chapter 1 [12,27], retaining every other chapter unchanged, and adds a regression test for 739 contiguous pages and all 20 chapters. The old auto-voted `supported_pages` value for chapter 1 is removed because it described the invalid 5–27 range.

## Next research/release controls

1. Await reviewed CI-green merge + official updater for the corrected 6e map; rerun original verified PDF extraction, check SHA/provenance, and back up the protected research result.
2. Obtain site-identical question content under protected storage using an authorized read-only source. Validate 1398 against original 5e and 1399–1405 against original 6e, including the *announced per-year chapter/page scope*.
3. Record each finding as exact edition, chapter node, original PDF page, printed page when distinguishable, book quote, confidence and original question/answer identity. Check official choices and existing human decisions; preserve questionable rows as pending.
4. Do not use the unsourced-only importer on already sourced questions. A separate reviewed, transaction-safe source-correction writer is required; must preserve all question/choice/answer, published assessment and human-review invariants; require independent book evidence, tested preview, fresh verified backup and production readback.
5. Publish only verifiable effects and append unique immutable receipts; do not count any existing row as revalidated until its original book page and answer linkage actually pass.

Current branch is a reference map/test/documentation fix only: **no production question sources were edited, no assessment version advanced**.

### Verified 6e book-tail correction

Follow-up to the frontmatter correction: the exact private 6e PDF is **746** PDF pages. Re-running the strict original extractor on the repaired frontmatter map correctly detected an unmapped final seven pages. The original PDF was individually inspected: chapter 20 ends on PDF page **719**; the index runs from **720–739**; pages **740–746** contain the printed "This page intentionally left blank" marker. Map these pages as nonchapter back matter, and shorten chapter 20 to 667–719. The contiguous validator must cover 746 pages and 20 real chapters; neither the index nor deliberately blank pages are question evidence. Research must not proceed under an invalidly shortened 739-page map.
