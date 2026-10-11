# Residency 1399 — additional original PDF-page research (2026-10-11)

**Status: original private PDF evidence research only, NOT source publication.**
This report extends `docs/ops/RESIDENCY_1399_CHAPTER_PAGE_AUDIT_20261011.md`. An original-book physical PDF page is recorded as a research candidate only after the current approved private PDF, the hash-bound page-to-chapter map, a readable text passage on that page and the exam-year syllabus scope have been reviewed. A final bibliographic/source row requires the official QA/publication workflow separately.

## Production / release consistency

- Last reliably re-read production: `residency / 1399 / round 1`, 249 questions, **87** recorded chapter+PDF-page+SHA on the same source row, 162 not yet recorded complete (20 English citation-exempt; **142 clinical**). This snapshot can be invalidated by concurrent agents: re-query before use.
- The PHP metadata-only eight-row operator in PR #305 merged with CI green as `781b3111c18cf50cd1a13e665bfde10cd7f81501`, but at the start of this follow-up `/health` returned deployed release `04a1054a1399bfa0191ef92215876e0a933f3783`, and no newer successful `release_update_requests` record was observed. No attempt to apply source mutations was made from the nondeployed code. Direct attempts to create an on-server protected plan folder and to make an authorized deploy request were rejected by remote execution safety controls; do not evade them.
- Both `node_id`, positive physical `pdf_page` and exact `pdf_sha256` are required in the **same** source row; `page`/`printed_page` alone never counts. No original question, option, official key, human-review field or frozen exam is changed as part of page research.

## New exact original-PDF candidates

All physical page numbers below were read on server with `scripts/references/verified_reference_pdf.py --edition=... --require-map --page=...`. Expected printed page numbers were checked directly against the page headers/footers; automatic two-neighbor `PrintedBookPageEvidence::corroborates` and short-quote checks passed for most entries, as annotated. No page-text file, OCR output or text index was created.

| Q | Subject | Official book | Exact chapter | PDF page | Printed page | Direct original-PDF observation | Review state |
|---:|---|---|---|---:|---:|---|---|
| 170 | operative | Craig Restorative Dental Materials 14e | 10 | **183** | 172 | Amalgam high-copper admixed alloy contains spherical particles of silver-copper eutectic; primary table/text | quote + label validator passed; printed p172 **within** official partial-page scope 171–178 |
| 211 | materials | Van Noort 4e | 3.7 | **228** | 213 | Table 3.7.1 compares ceramic crown systems, aesthetics/cost/strength; correct answer requires visual comparison of table tick marks | quote + label validator passed; **visual table interpretation pending** |
| 212 | materials | Van Noort 4e | 3.5 | **217** | 202 | Excessive creep weakens sag resistance in metal-ceramic casting alloy | quote + label validator passed |
| 214 | materials | Powers/Wataha 11e | 8 | **120** | 102 | Alginate ingredient table: potassium zinc fluoride counteracts inhibition of gypsum set | quote + label validator passed |
| 215 | materials | Powers/Wataha 11e | 5 | **84** | 66 | Over-triturated amalgam can have poorer corrosion properties | original short quote and neighbor printed-page label validator both passed |
| 216 | materials | Powers/Wataha 11e | 2 | **39** | 21 | Unfilled acrylic exhibits higher toughness than composite, with approximately equal resilience | quote + neighbor label validator passed |
| 218 | materials | Powers/Wataha 11e | 7 | **105** | 87 | Bioceramic cement contains calcium aluminate, needs no separate priming/bonding | quote + neighbor label validator passed |
| 89 | prosthodontics | Zarb Edentulous 13e | 12 | **290** | 276 | Hamular-notch soreness and posterior palatal seal pressure | quote + label validator passed |
| 90 | prosthodontics | Zarb Edentulous 13e | 7 | **147** | 133 | Box 7-11 explicitly requires good thermal conductivity, matching official option 4 | original short quote and neighboring label validator both passed |
| 92 | prosthodontics | Zarb Edentulous 13e | 9 | **208** | 194 | Corners of the mouth are anterior landmarks for occlusion-rim transfer | quote + label validator passed |
| 93 | prosthodontics | Zarb Edentulous 13e | 12 | **291** | 277 | Gagging usually relates to maxillary denture posterior border | quote + label validator passed |
| 94 | prosthodontics | Zarb Edentulous 13e | 5 | **85** | 71 | Ask patient to place tongue tip in the opposite buccal sulcus (option 3) | exact original quote and neighboring label validator both passed |
| 95 | prosthodontics | Zarb Edentulous 13e | 9 | **210** | 196 | Initial laboratory step is securing articulator in centric relation | quote + label validator passed |
| 96 | prosthodontics | Zarb Edentulous 13e | 10 | **230** | 216 | Original Table 10-2 disadvantage of lingualized occlusion is that some grinding is needed, matching option 3 | original table text, short quote and neighbor label validator passed |
| 97 | prosthodontics | Zarb Edentulous 13e | 12 | **271** | 257 | Answer-bearing clause specifies premolars and canines on offending side; question starts PDF269/printed255, separated by figure-only PDF270 | exact answer quote and printed-page neighbor validator passed; update page/printed-page from historical 255 to 257 only through protected operator |
| 98 | prosthodontics | Zarb Edentulous 13e | 5 | **78** | 64 | Retruded tongue position removes lingual flange border seal | quote + label validator passed |

Exact current private-PDF SHA-256 for these three additional books:
- `craig-restorative-materials@14e`: `333e81ae8a1818cdff57f00e3f870698776e3b8e02fcc49169f397f2fbaf90b9`.
- `van-noort-materials@4e`: `7e993e5c50979c3287b755e4ade2a1f42c27e444acd1ca4ac9cc9e791d9f203a`.
- `powers-wataha-materials@11e`: `df1b471c7c2949422cbe9f883a792800cb811e39c7217dbae781084df8478399`.
- `zarb-edentulous@13e`: `bcf92916cd0a01915695d1cb5aad8c51e7f2dac5909477c4df4454d229156a3d`.

## **Official scope exclusions — do not blindly bind printed pages**

The original `bank_reference_validity` record of residency 1399 establishes:

- **Craig 14e:** Chapters 9–10 are subject to the announced printed-page portion **171–178**, with chapter 13 also in scope. Legacy **Q166 (chapter 9, printed 143, PDF154)** and **Q167 (chapter 9, printed 141, PDF152)** have directly located subject matter in the book, but these printed pages are outside the announced limit. Do **not** count or publish as in-scope source citations without a formal official syllabus resolution. Q170 (printed172, PDF183) is inside the included segment.
- **Van Noort 4e:** Only sections **3.4–3.7** of Part 3 are announced. Legacy Q211 `ch03.7` and Q212 `ch03.5` are within scope. Legacy Q213 `ch03.1`, Q217 `ch02.2`, Q219 `ch02.8`, and Q220 `ch02.2` are **outside the announced section scope**; preserve existing historical rows unchanged and flag official-syllabus investigation.
- **Powers/Wataha 11e:** All chapters except 1, 6, 10, 12 and 14. New candidates in 2, 5, 7, 8 are eligible at chapter level.
- **Zarb Edentulous 13e:** All chapters except 18–22. The nine candidate chapters 5, 7, 9, 10 and 12 are eligible at chapter level.

Read the **exact** original year validity, edition, chapter map and page evidence at import time. The existing rows for chapters outside scope are legacy diagnostics, not authority to override the official list or the new atomic chapter-and-page policy.

## Next reproducible safe action

Do not run generic `backfill_source_pages.php` on legacy printed labels: it cannot establish an original physical PDF page. Confirm that the 8-row initial repair operator of PR #305 has been properly deployed and completed with a fresh verified full backup and private receipts; if not, it remains pending. Any additional repair set in this report requires its own narrowly scoped versioned source-metadata operator, **explicit partial-page and section validity gates**, immutable before/after bank+frozen-assessment signatures, original-page short-quote checker, sealed private plan, exclusive publisher lock, fresh verified backup, live DB readback, approved publication and full assessment-version diff. For Q211 inspect the original ceramic-comparison table visually before promotion; Q90/Q94/Q96/Q97 were separately checked against their official choices and exact original answer-bearing page. Q97 uses PDF271/printed257 rather than the question-opening PDF269/printed255. No synthetic pages, unreviewed substitutions or source-only fragments may be presented as completed.

**End-of-pass confirmation:** no clinical bank source rows, human reviews, official keys or student attempt/frozen assessment tables were mutated by this research; no new source chapter/page completion was claimed.
