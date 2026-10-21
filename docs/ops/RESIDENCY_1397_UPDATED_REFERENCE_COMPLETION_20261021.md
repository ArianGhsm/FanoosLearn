# Residency 1397 updated-reference classification — 2026-10-21

## Final status

The residency 1397 sitting now has an atomic chapter, PDF-page and source-hash row for every book-based question. The live bank has **247 questions**: **20 English questions with valid keys are source-exempt**, and all **227 book-based questions** have a same-row chapter, positive PDF page and PDF SHA-256. There are **zero source-free book questions**. The 1397 source table has 227 rows.

All **64** previously source-free questions were classified from the owner-authorized 1406 reference set. Their independent PDF audit accepted 64/64. The four source-only batches were applied through the guarded importer with rollback previews, a verified full backup, unique receipts and live readback. Six existing AI citations were re-evidenced and raised from 0.800 to 0.900 without changing their source page or identity. Q80 was separately marked outside the official 1397 scope through the guarded metadata-only operator.

Five existing source rows remain below the 0.85 confidence threshold. Their chapter, PDF page and PDF hash remain present; the confidence repair operator refused to re-score them because its printed-page or official-scope proof is unavailable. These rows and their precise limits are listed below. This runbook does not describe them as confidence-complete.

The 1398 sitting was independently rechecked at the same time: **247 total, 20 valid English exemptions, 227/227 book questions complete**, 228 source rows because Q221 has two cited pages, zero uncited book questions, zero rows below 0.85, and zero page-map/current-PDF-hash mismatches.

## Authority and safeguards

The owner authorized references officially listed for residency 1406 as updated-reference proxies for the 1397 sitting. This exception does not claim those books were on the 1397 list. Every proxy decision has a private audit and receipt, and the bank note identifies both years and says the reference was outside the official 1397 list. If its chapter is also outside the 1406 subject scope, the note says so explicitly.

The source-only importer inserted rows in `bank_question_sources`; it did not alter question text, choices, answer keys, statuses, assessments, reviews, attempts or scores. The 1397 whole-year publication gate remains in force. This source classification did not publish a new assessment version.

The 1406 reference PDF inventory and page maps were hash-verified against current approved private PDFs. The active release and updater are `bb940bff7ce956f09e8349932bc1cecc44723997` (PR #498); its five CI checks passed. The pre-write backup `20261021T133502Z-7a491238` was verified at 13:35:07 UTC and contains 114 files, including a 71,959,663-byte database dump.

## Applied source-free batches

The four batches add 64 source rows. The shorthand below is `question: edition chapter / PDF page`.

| Subject | Rows | Question → edition, chapter and PDF page |
|---|---:|---|
| Oral surgery | 19 | Q191 Hupp 7e ch25/PDF534; Q192 Hupp 7e ch27/PDF620; Q193 ch26/PDF570; Q194 ch30/PDF659; Q195 Malamed 7e ch10/PDF174; Q196 Malamed 7e ch18/PDF373; Q197 Hupp 7e ch1/PDF25; Q198 ch2/PDF38; Q199 ch2/PDF37; Q200 ch1/PDF23; Q201 ch2/PDF42; Q202 ch5/PDF73; Q203 ch10/PDF184; Q204 ch14/PDF269; Q205 ch13/PDF258; Q206 ch17/PDF357; Q207 ch21/PDF460; Q208 ch22/PDF485; Q209 ch23/PDF503. |
| Pediatric dentistry | 19 | McDonald/Avery 11e: Q21 ch10/PDF212; Q22 ch11/PDF234; Q23 ch12/PDF246; Q24 ch4/PDF108; Q25 ch27/PDF615; Q26 ch14/PDF285; Q27 ch16/PDF338; Q28 ch17/PDF349; Q29 ch3/PDF91; Q30 ch23/PDF488; Q31 ch23/PDF480; Q32 ch26/PDF579; Q33 ch26/PDF594; Q34 ch27/PDF607; Q35 ch28/PDF647; Q36 ch15/PDF330; Q37 ch2/PDF36; Q38 ch3/PDF92; Q39 ch4/PDF104. |
| Periodontics | 10 | Carranza 14e: Q152 ch4/PDF81; Q153 ch5/PDF113; Q154 ch38/PDF861; Q155 ch19/PDF484; Q156 ch27/PDF686; Q158 ch32/PDF774; Q159 ch5/PDF121; Q162 ch41/PDF903; Q167 ch50/PDF1034; Q168 ch31/PDF760. |
| Prosthodontics | 16 | Q121 Shillingburg 4e ch4/PDF46; Q122 ch27/PDF538; Q123 ch10/PDF173; Q124 ch11/PDF199; Q125 ch19/PDF360; Q126 ch23/PDF438; Q127 Rosenstiel 6e ch7/PDF228; Q128 Shillingburg 4e ch24/PDF459; Q129 ch27/PDF538; Q130 ch29/PDF564; Q131 McCracken 13e ch14/PDF212; Q132 Shillingburg 4e ch25/PDF486; Q135 Zarb 13e ch4/PDF61; Q140 ch4/PDF58; Q141 ch13/PDF309; Q143 McCracken 13e ch8/PDF111. |

The four citations outside the 1406 chapter scope are Q123 (Shillingburg 4e ch10), Q124 (Shillingburg 4e ch11), Q156 (Carranza 14e ch27) and Q168 (Carranza 14e ch31). Each retains its true PDF page and carries the explicit outside-syllabus note.

Independent audit SHA-256: `52d82a53f4f0646ba1ff1f97137b37a51b3076f1d3a0f6696fd1927958cb9d4e`.

| Batch receipt | Rows | Receipt SHA-256 |
|---|---:|---|
| Oral surgery | 19 | `855a4b424187b2c440fc8de0e4adf38a2163803cfd6684a2d1c33a5385c5823f` |
| Pediatric dentistry | 19 | `e066f321171b419da3f96d36b8f1ea173d421c3b4249111f422269a9766479c4` |
| Periodontics | 10 | `8d7de9a64fe4e78491f3e1fc27bbe2e9a7824a3dc75b0a17bcb2809636ad1c15` |
| Prosthodontics | 16 | `a453e8af025bf5fee2b85cc78506be5ddd7916245221231b1adbdc0b15bb2745` |

## Existing source repairs and scope note

Six existing AI citations passed the exact-page audit and guarded repair; each confidence vector is now 0.900. Only confidence fields changed: Q113 (Neville 4e ch15/PDF654), Q133 (Zarb 13e ch9/PDF196), Q136 (Zarb 13e ch7/PDF170), Q142 (Zarb 13e ch15/PDF335), Q146 (McCracken 12e ch7/PDF74) and Q149 (McCracken 12e ch11/PDF142). Every receipt reports zero question, choice and answer changes.

Q80 remains Summit 4e ch12/PDF685. Its chapter is outside the official 1397 syllabus. The guarded outside-scope operator recorded `outside_scope_exam_question` and a Persian note. The PDF page has no printed page label; the source retains `pdf 685` and `printed_page=null`.

## Remaining confidence limits

These five pre-existing AI source rows remain at 0.800. Their chapter, PDF page and current PDF SHA are present and all five are counted as structurally complete above.

| Question | Existing citation | Why confidence remains 0.800 |
|---|---|---|
| Q10 | Proffit 5e ch3/PDF103 | The rendered page shows printed page 82, but the label is raster-only. The current confidence-repair operator requires a text-corroborated label and refuses to change the row. |
| Q14 | Proffit 5e ch11/PDF440 | The rendered page shows printed page 419, but the label is raster-only; the same guarded check cannot corroborate it. |
| Q77 | Summit 4e ch10/PDF512 | The approved PDF page has no printed label. The existing PDF-index page and hash remain valid; the current confidence-repair operator requires a corroborated printed-page label. |
| Q80 | Summit 4e ch12/PDF685, now explicitly outside the 1397 syllabus | The PDF page has no printed label. Its outside-scope status and note are corrected, but confidence remains 0.800 under the same printed-label gate. |
| Q157 | Carranza 12e ch17, printed 250/PDF620 | The 1397 official scope row says it contains selected chapters and online sections, but its structured `scope_chapters` is null. The classifier refuses to re-score a chapter against an unstructured scope; no scope inference was made. |

Private page-label/scope audit SHA-256: `dfa7b14fda02a4bb8bff9a9da3ff8db617b164afe27a469be5661252fa5a9293`.

## Final live check: 1397 and 1398

| Sitting | Total | English exemptions with valid keys | Book questions with same-row chapter/PDF page/SHA | Source rows | Uncited book questions | Below 0.85 | Chapter-map or current-PDF hash mismatches |
|---|---:|---:|---:|---:|---:|---:|---:|
| 1397 | 247 | 20/20 | 227/227 | 227 | 0 | 5 | 0 |
| 1398 | 247 | 20/20 | 227/227 | 228 | 0 | 0 | 0 |

The extra 1398 source row is the two-page Q221 source bundle. The final source SHA groups match current approved PDF objects and every row is inside the page range for its mapped chapter. All outside-scope rows have notes; 1397 contains 163 updated-reference proxy rows plus the separately marked Q80, and 1398 retains its 17 documented outside-scope rows.

Before/after immutable checks have identical 247-question stem/choice/key fingerprints and identical frozen assessment version checksums, review counts and attempt counts for both years. 1397 remains assessment version 2 with 2 reviews and 0 attempts; 1398 remains version 10 with 10 reviews and 0 attempts. No keys or frozen assessments were republished.

Private final checkpoint:
`/srv/fanoos/shared/research/classification/reports/residency-1397-1398-final-checkpoint-20261021-v1.json`
(SHA-256 `1c7129dea9dd4bedb667dbf331cf2df8bef0a908db4dbfc88d679c9929291d8b`).
Final census SHA-256: `672c480b8a33047e87fd9e1ba62c2ed6d6a07087023dc23f79893bebe608a936`.
Immutable baseline SHA-256: `e886664fba9453e9019a23f318e614af6ab5a0cf387782b75821cd3e72eaf1ac`; final SHA-256: `f0a73f845e8a91b286497f65c33fa5b67ae1f3be557458ca5b3126be9cf01366`.

## Publication and issue state

No assessment version was published or republished as part of this source-only classification. The separate whole-year publication gate remains in force. Issue #422 and unrelated draft branches are not closed or cleaned up by this runbook.
