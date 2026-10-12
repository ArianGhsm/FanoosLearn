# Residency 1402 — nine original-book page repairs, reference-map deployment and frozen assessment v11

**Checkpoint:** 2026-10-13 (Asia/Baku); operations 2026-10-12 UTC. Syllabus: residency, 1402, round 1. This report records **actually applied and independently verified** production source changes, not hypothetical plans. Preserve all protected original questions, options, official answers, human reviews, historical attempts, and previous frozen assessment versions.

## Preflight and reference deployment

Starting strict same-source-row chapter + original PDF page + approved original PDF SHA-256: **130/250**; previously published sitting **v10** with 243 scored questions and seven officially voided questions Q60, Q96, Q99, Q102, Q103, Q144, Q181.

Research for `mcdonald-avery-pediatric@11e` (717 pages, SHA-256 `a344465f89ff6711099a8bee1878e83236c9778340447ac7491bbc27a89fb629`) and `mccracken-rpd@13e` (389 pages, SHA-256 `4e05bee57d1066601b8a2b30c2ae72d4a5bf65298747b600ae3fdd8248a192d9`) found that the OLD LIVE reference chapter maps did not bind the source SHA-256, so the exact-page verifier correctly rejected all candidate writes. Each approved PDF was reread using the verified private server reader, page-by-page, discarding raw text; reconstruction identified all **31** McDonald–Avery and all **25** McCracken chapters, full mapped page counts and high text readability (99.44% and 98.46% respectively). The newly merged GitHub main already had approved hash-bound chapter maps. An owner-authorized canonical deployment request `01a1310e-4671-71ee-b9b5-da99982679c8` was created through `DeploymentControlService`, run only through `scripts/ops/update-runner.php`, and finished `SUCCEEDED` on release SHA `3ec7953b4c10b1cefe78f7242d4385876d15818b`. Post-deployment `/health` responded `status:ok`; direct canonical `verified_reference_pdf.py --edition=... --require-map` confirmed both original exact PDF SHA identities and **zero map issues**, no text files exported.

The endodontic, oral surgery, prosthodontic, oral radiology, and other existing records were preserved.

## Nine exact chapter + PDF page + printed page repairs

Each below had an unchanged existing official-year edition and chapter, original source ID, printed-page value, exact anchor hash, unreviewed AI origin, published question, `final` or `amended` official key; the original approved PDF passage supporting the question's answer and the printed label on **at least two adjacent pages** were independently verified. All selected plans passed the protected `scripts/references/repair_verified_source_pages.php` rollback-only preview, with `questions_changed=0`, `choices_changed=0`, `answers_changed=0`. Atomic applies changed ONLY `bank_question_sources.pdf_page` and `bank_question_sources.pdf_sha256`.

| Q | Subject | Edition | Chapter | Original PDF page | Printed page | Evidence |
|---:|---|---|---:|---:|---:|---|
| 152 | Pediatric dentistry | McDonald–Avery 11e | 2 | 33 | 20 | Radiation protection optimization, limitation, ALARA |
| 154 | Pediatric dentistry | McDonald–Avery 11e | 4 | 105 | 92 | Central-incisor, first-molar, lateral-incisor, canine, second-molar primary calcification sequence |
| 155 | Pediatric dentistry | McDonald–Avery 11e | 8 | 183 | 172 | Silver and zinc listed among heavy-metal plaque-control agents |
| 161 | Pediatric dentistry | McDonald–Avery 11e | 15 | 311 | 300 | Prevotella intermedia associated with puberty gingivitis |
| 162 | Pediatric dentistry | McDonald–Avery 11e | 16 | 342 | 334 | Mental nerve block produces numbness similar to inferior alveolar block |
| 166 | Pediatric dentistry | McDonald–Avery 11e | 23 | 480 | 474 | Four to six months of premolar eruption movement per 1 mm bone |
| 110 | Prosthodontics RPD | McCracken 13e | 5 | 67 | 54 | Finish line positioning no farther than 2 mm palatal to a hypothetical tooth |
| 114 | Prosthodontics RPD | McCracken 13e | 23 | 319 | 306 | Causes of occlusal rest fracture |
| 115 | Prosthodontics RPD | McCracken 13e | 5 | 49 | 36 | Rare indication for labial bar major connector |

Protected private plans and applied receipts in `/srv/fanoos/shared/research/classification/reports/`:

- `residency-1402-pediatric11e-batch1-applied-20261012.json` (Q154/155/161), receipt SHA-256 `e0575e7239477f22273b375c589649f0b967bdb98dc32de9b012590863c5e44a`
- `residency-1402-pediatric11e-batch2-applied-20261012.json` (Q162/166), SHA `78a621752adede782052b84b9cd5ceec3f8924c3d6a8e07d942ba75fc546c154`
- `residency-1402-pediatric11e-q152-applied-20261012.json` (Q152), SHA `b0f1830c554f3010e72d264e5644206e785de4fb4aad064a31669330d4bdaca8`
- `residency-1402-mccracken13e-safe3-applied-20261012.json` (Q110/114/115), SHA `f1883f05c5cc8db4402477ab7491cb35718282397711e96c72b9fb2173d7a7a8`

All writes guarded by complete backup `/var/backups/fanoos/20261012T170233Z-650735dc`, independently verified **114 files**. The candidate McCracken Q111 was *excluded* because its original printed label page 7 could not be corroborated in the PDF's header (even though candidate book text mentioned stability). No unsupported update was attempted.

## New complete backup and frozen version 11 publication

After all nine source edits, a **NEW complete backup**
`/var/backups/fanoos/20261012T171139Z-e47ca46e` completed successfully and independent `verify-backup.php` validated **114 files**.

Official preflight `audit_published_assessment.php` against version 10 returned `question_content_identical=true`, `full_sitting_questions=243`, and `matched_study_questions=20` on protected oral-radiology study questions. Owner-authorized canonical `scripts/import/import-bank.php publish` created **frozen published version 11** (version ID `01a131df-4b55-7f0a-8f7a-245e97917386`) and excluded exactly the prior seven voided Q60,96,99,102,103,144,181. Postpublish audit `--previous=10 --current=11` returned `question_content_identical=true`, `questions=243`, `updated_explanations=9`. A separate full question-ID keyed comparison of all 243 frozen explanation fields found **exactly Q110,114,115,152,154,155,161,162,166** and `missing=[]`, `unexpected=[]`. The previous versions, frozen question contents, keys, scoring and historic attempts were unchanged; only explanatory source locations changed.

Live SQL at source readback: **139/250** strict chapter+page+SHA same row; subject pediatric 6/20 and prosthodontics 19/30. **111** raw incomplete rows, of which **20** final-key English questions are reference-exempt; **91** non-English candidates remain, including **6 voided**; **85 scored clinical citations** still need chapter/PDF-page pairing. This is a *structural coverage count*, not a retroactive semantic confirmation that all 139 legacy page citations are correct. Legacy year-specific structured-scope flags overlap the cohort and should be assessed case by case, never blindly forced.

## Next steps and scientific holds

Continue year-1402 official references for remaining pediatric, oral pathology, oral surgery, oral medicine, endodontics, RPD, dental materials and periodontics using original approved private PDFs and direct answer-bearing evidence. Cases with a wrong previous printed-page label or absent printed page require a separately designed, guarded reviewed process; do **not** map a guessed PDF offset or modify protected content. Candidate pediatric Q158 has an answer-bearing original book statement on PDF page 234 / printed page 223, whereas the current legacy source mistakenly claims printed page 220; current strict repair intentionally prevents applying without individually reviewed printed-page correction. Existing material science/periodontics unanswered cases cannot be completed by copying placeholders. Complete 1402 only after each valid reference+chapter+PDF page is really saved and published, with CI and live database/site audits as required.
