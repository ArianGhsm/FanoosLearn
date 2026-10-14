# Residency 1405 — full Carranza 14e PDF: page research and guarded handoff (2026-10-14)

## Authority and limits

- Production read-only query from the IranServer FANOOS MySQL database, sitting `residency:1405:round1`: **250 questions**, **110** source rows with same-row chapter node, physical PDF page, PDF SHA-256; 20 valid-key English questions are textbook-exempt; **120/230 clinical questions remain incomplete**. The existence of these three fields in old records is not proof of in-scope scientific correctness.
- By subject, incomplete: periodontics 20; prosthodontics 18; oral surgery 17; pediatric dentistry 17; endodontics 15; oral medicine 14; oral pathology 13; dental materials 5; oral radiology 1; orthodontics/operative/community 0.
- Website `/health` reported `status=ok`, release `86d7c4fe37515381111994707e2914e308b7595f`; updater checkout `main...origin/main`. No source write, new public exam version or deployment was performed in this research session.

## Newly approved complete physical book

A read-only server library query found `carranza-periodontology@14e`, approved/published **version 3**, verified object size **951,240,625 bytes**, SHA-256
`32f50c3c01d6de459b0fdf84a79e969ed99844605d742d5571392555ce074327`,
original object name `carranza14e.pdf`. The earlier 105-page incomplete copy remains as superseded version 2, SHA-256 `eb2ebe1a682635f9be38b2da3016af6c521d6a5f70052a8acf90841bf3d4ae18`. The current approved version contains **1,876 physical PDF pages**, including a final intentionally blank page 1876. Text can be extracted page-by-page through the approved `verified_reference_pdf.py` read path, without a book-text derivative.

**Safety hold:** `verified_reference_pdf.py --edition=carranza-periodontology@14e --require-map` correctly rejected the book because the currently committed `data/bank/reference-chapter-pages.json` Carranza 14e entry is **not pinned to this PDF SHA** and its old ranges begin on page 2 and end on 1875. No auto-assignment to the book is authorized until a new SHA-bound, gap-free map is independently validated, tested, merged and deployed.

## Scientific chapter-start inspection: corrections to historic candidate map

Each old candidate chapter-start page and its neighbors was read directly from the approved physical PDF; a chapter-numbered heading within the beginning of the page was used as a **research signal**, not a sufficient stand-alone proof of the chapter/page map. Of the 88 expected chapters, numbered heading candidates were located for chapters 2–88. The original chapter 1 opening was separately located at physical page 27, not old page 2. For **40 chapters** the old start agreed with the heading candidate; the following **47 chapters** began one to three pages later than the old map implied (format: chapter -> new candidate physical start):

```text
3:55 4:67 7:153 9:248 11:333 12:356 13:371 15:393
18:457 19:480 23:562 24:576 25:600 26:639 28:713 29:729
31:745 32:772 34:800 35:816 37:832 40:893 42:917 43:923
49:1016 51:1053 53:1122 55:1174 57:1198 58:1209
60:1241 61:1259 63:1306 66:1402 70:1473 72:1501
73:1512 74:1533 75:1549 78:1611 79:1623 80:1655
81:1671 83:1696 84:1718 86:1764 87:1800
```

These are **candidate corrected starts**, not yet a released map: the end boundary of chapter 88 versus the trailing index and all boundary ambiguities still require final verification. The old map must not simply be relabeled with the new SHA. Any final map should mark front matter, index and deliberately unresolved boundary pages as `null` rather than assigning a false chapter; preserve contiguous 1-based physical PDF coverage and test every mapped chapter boundary against the approved object.

## Residency 1405 peri: entire-book original-source screening

A transient, in-memory scan of the new 1,876-page PDF compared all 20 live legacy AI source-anchor phrases with their original exact-edition book text, using 12-page temporary subprocess buffers. No book-text stream, OCR export or search index was stored. Exact contiguous normalized phrase matches were found for:
- **Q112**, existing chapter 5, physical PDF **115**
- **Q118**, existing chapter 25, physical PDF **606**
- **Q119**, existing chapter 25, physical PDF **607**

For the other 17, word-overlap ranked page candidates arose, often in the historical chapter, but none was an exact contiguous whole-anchor match. **Do not convert coverage scores into verified scientific decisions.** Q129 historic chapter 79 and Q130 historic chapter 87 remain **outside the official 1405 chapter scope**, despite book-text candidates, and need alternative official in-scope evidence or an explicit scientific hold. Legacy `page` / `printed_page` in the twenty peri records often equals the chapter number (e.g., 5, 25), not the actual book-printed page; never pass these as corroborated pagination or use the existing printed-offset repair operator to invent a page. No peri source row received a PDF page/hash in this session.

## Existing ready research and publisher limitations

Sixteen independently accepted, unchanged-original-study source-page cases already exist in the protected recovery paths `/srv/fanoos/shared/research/classification/recovery-1405-anchored<Q>-20261013/`, as documented in `RESIDENCY_1405_ACCELERATED_56_PAGE_RESEARCH_20261013.md`. Their question numbers:
- oral medicine 68, 70
- dental materials 211, 212, 219
- oral surgery 147, 148, 149
- pediatric dentistry 176, 180, 182, 183
- oral pathology 56
- endodontics 31, 39
- prosthodontics 97

`repair_verified_source_pages.php` is the existing deployed guarded source-only plan/preview/apply operator; it requires its exact plan schema, SHA-pinned current source, original anchor identity, actual physical page plus corroborated *book-printed* page and neighboring labels, a short verbatim original-PDF quotation, official year-scope, full SHA-bound chapter map, valid unchanged official key, fresh verified backup, and independent live readback. It must not be bypassed or weakened for a favorable count. A proposed temporary plan-construction run failed source/evidence preflight (long original quote exceeded the plan evidence-length limit) **before writing any plan or source records**; a revised request was blocked by the tool safety gate, and was not executed. Do not treat those cases as production applied. Another documented blocker is Q207 oral radiology: two independently verified official chapter citations but the present insert workflow is single-citation; no arbitrary choice between the two.

## Required continuation and invariants

1. Finish independent new-Carranza chapter boundary mapping and source-SHA pin in a narrow GitHub PR, green CI, merger and official updater release. Verify `--require-map` against the live object after deploy.
2. Individually validate each original residency question, four choices and final official key against page-local answer-bearing evidence **in the exact official edition and in-scope chapter**, with actual printed/PDF page distinction; move ambiguous cases to pending. Preserve current source provenance and any human-origin record.
3. For already audited non-perio cases use only the existing approved fail-closed source-only operator and independent authenticated review. Never reissue a tool safety-blocked operation via a disguised command or a weaker update path. Fresh independently verified full backup, rollback-only preview, guarded apply, unique receipt and independent source/question/choices/answer/attempt readback are mandatory.
4. Recheck total, strict same-source-row completeness and scientific in-scope completeness separately; inspect frozen published-assessment content, live health, CI/release before closing the year.

All private original questions, book passages, PDFs, study artifacts and any future source-update receipts stay exclusively on the protected server. This documentation contains **no new claims of live chapter/page completion**.

## 2026-10-15 continuation — proposed full-PDF map commit and review gate

A new dedicated branch `fix/carranza14e-approved-pdf-chapter-map-20261015` proposes to replace the old unbound Carranza 14e run mapping. It pins the already verified 1,876-page PDF SHA, leaves front-matter pages **1–26** unassigned, maps chapters **1–88** starting at the visually corroborated native headings (including chapter 1 at p27 and all 47 corrected boundary offsets above), and leaves glossary/index/blank pages **1821–1876** unassigned. Automated regression tests cover the file identity, contiguous physical-page coverage, all 88 chapters and selected critical boundaries. The old `supported_pages` votes are reset rather than misrepresenting confidence against the new PDF.

These changes are a **code/catalog proposal**, not a source-row publication. Require green PR CI, merge into canonical main, updater deployment with healthy live release and rerun of `verified_reference_pdf.py --edition=carranza-periodontology@14e --require-map`. Only then prepare individualized exact-year/official-scope evidence and source-only transactions; Q129/Q130 historical out-of-scope chapter labels remain held.
