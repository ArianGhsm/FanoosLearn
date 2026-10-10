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

## Protected 1398 exact-5e question-by-question candidate index

On 2026-10-10, the original `find_in_books.py` searched the complete verified 5e text for the **19 live 1398 orthodontics questions**. The 19-page first-pass candidate index is saved privately at `/srv/fanoos/shared/research/classification/reports/1398-orthodontics-original5e-candidate-audit-20261010.json`, SHA256 `aa62f1550e3b27fdc36315cb89ff47598463c71d561798963c765d2bc5685a6c`. Its query inputs live at `classification/reports/1398-orthodontics-exact5e-queries-20261010.json`. It explicitly labels every candidate `approved=false`; no evidence decisions or production updates resulted. It identifies **potential answer/reference contradictions** at questions 20, 24, 33 and a prior out-of-scope chapter at 27, plus inadequate exact timing evidence at 37. Do not rewrite official answers, rationalize wrong answer options, or falsely claim a verified mapping based on search score alone. PDF5e versus printed page offset is approximately +21 and must be verified per page. Use the separate protected original-book validator after obtaining a full site-identical read-only review snapshot of the already-sourced questions; the standard unsourced-only exporter will return zero orthodontics rows.

## Reproducible all-year live source metadata inventory

Protected original current-DB inventory `/srv/fanoos/shared/research/classification/reports/orthodontics-live-159-metadata-inventory-20261010.json`, SHA256 `75e8432c6d210f08e35079945252c25c747a29e50828839f4ba3d37a23bac996`, contains **all 159** current year/question/reference/chapter/page/origin/confidence records and source-anchor *status only* (no question stems, answers, or book passages), marked `original_book_verified=false` on every row. It captures 21 missing-page rows (1398:19; 1404 human:2) and 19 legacy-nearest anchors (1398). This is an inventory for eventual precise audit, not an approved classification artifact or production import. The live DB may subsequently change; repeat the read-only query and compare before corrections.

## Read-only official-scope and chapter-boundary quality scan

Protected report `/srv/fanoos/shared/research/classification/reports/orthodontics-159-structural-scope-qc-20261010.json`, SHA256 `62af37d5365a5b5e6abbe34a7540d36f44086e75a5ec6919ebf0532807830ffe`. The scan cross-checks all **159** existing source rows with each year's **official Proffit validity scope**, the exact edition's page/chapter map, and PDF-page versus printed-page numbering (5e PDF ≈ printed+21; 6e PDF ≈ printed+10). **32 distinct questions are flagged**, including overlapping categories: **21 missing page**; **4 unqualified non-PDF page labels** (1399 Q3, 1401 Q12, 1402 Q21 and Q23); **5 chapters absent from the literal official chapter list** (1398 Q27, 1399 Q7, and 1399 Q18/1400 Q20/1401 Q11); **3 PDF pages outside the announced partial printed-page scope** (1401 Q8, 1403 Q13, 1404 Q11). The last three chapter-list differences concern the *Retention* chapter numbered 18 in the original 6e PDF although the 1399–1401 official scope writes chapter 17 with printed pages 579–589, which fall in actual 6e chapter 18: mark **announcement-number ambiguity, not proven invalid original content**. Other chapter-list mismatches require separate official scope and original evidence review. No validated existing 'pdf N' page was outside its own mechanical chapter range; that alone does not clinically validate it. This flag-only report makes no changes in production.
