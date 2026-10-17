# Residency 1402 — 4 additional original-page source repairs and version 12 publication gate

**Audit date:** 2026-10-13 (Asia/Baku; production UTC records 2026-10-12). Exam type residency, year 1402, round 1. This ledger is reproducible and applies only to the exact official year-specific references. It does not expose licensed original PDF contents or protected examination questions.

## Source-only bank updates, already applied

Earlier frozen student assessment **version 11** held 243 scored questions; the live bank had **139 of 250** source rows with a bound chapter + physical original PDF page + original PDF SHA-256, while the 20 final-key English questions are reference-exempt. Four further sources were independently text-verified, checked against their original official year-scope and adjacent printed-page numbers, accepted by the rollback-only protected `scripts/references/repair_verified_source_pages.php`, then applied in guarded transactions changing **ONLY** `bank_question_sources.pdf_page` and `bank_question_sources.pdf_sha256`:

| Question | Subject | Exact official original edition | Chapter | PDF page | Printed page | Direct supporting evidence |
|---:|---|---|---:|---:|---:|---|
| 68 | Oral pathology | Neville Oral and Maxillofacial Pathology 4e | 10 | 389 | 382 | Gingival squamous cell carcinoma may resemble a reactive pyogenic granuloma |
| 82 | Oral surgery | Hupp Oral and Maxillofacial Surgery 7e | 3 | 57 | 43 | Diabetes type 1 uncontrolled, terminal kidney/liver disease and malignancy are explicitly listed as catabolic impediments to surgical healing; alcoholism is the negative choice |
| 241 | Dental materials | Van Noort 4e | 3.7 | 225 | 210 | Leucite-containing feldspathic ceramic used for veneers, with sintering, hot pressing and CAD/CAM processing on the page |
| 242 | Dental materials | Van Noort 4e | 3.5 | 214 | 199 | Metal–ceramic surface acid pickling using 30% hydrochloric acid |

Approved original private PDF SHA-256 values:
- `neville-oral-pathology@4e`: `6fbc9bcba9003deda2f8fc006ccc4f23f9e15db0bcd0e237c56ddf6788578bb6`.
- `hupp-oral-surgery@7e`: `35e3c37fec67a831a4464205c1817cc1e51c0a9f8a0bd8660a1881ecd2f10a83`.
- `van-noort-materials@4e`: `7e993e5c50979c3287b755e4ade2a1f42c27e444acd1ca4ac9cc9e791d9f203a`.

Private SHA-bound rollback previews each explicitly returned `questions_changed=0`, `choices_changed=0`, `answers_changed=0`, correct unchanged existing node/source/edition/anchor/year-specific official scope, and exactly the specified original-PDF page with printed-page header corroborated by neighboring pages.

Private protected applied receipts:
- `/srv/fanoos/shared/research/classification/reports/residency-1402-neville4e-q68-applied-20261012.json`, receipt SHA `93b45127cd364fd7f0868043b0310feabe1c2ed441f1263057b9c213736ea0b6`.
- `/srv/fanoos/shared/research/classification/reports/residency-1402-hupp7e-q82-applied-20261013.json`, receipt SHA `3886d5b8668c3d4c30474893106ea4f19831c4dbe94fcc0228550c4b27a0bba3`.
- `/srv/fanoos/shared/research/classification/reports/residency-1402-vannoort4e-q241-242-applied-20261013.json`, receipt SHA `297f69e41d94279824a0ed8b1ce9b672cd36e3ace561dbe5abdd0dcd579e7f26`.

Known full verified backup `/var/backups/fanoos/20261012T171139Z-e47ca46e` protected the Neville repair; later full verified `/var/backups/fanoos/20261012T205820Z-b31cde28` (114 verified files) protected Q82; and later independently verified `/var/backups/fanoos/20261012T210341Z-482ac22d` (114 verified files) protected Q241/Q242. At 2026-10-12 21:04 UTC **143 of 250** residency 1402 question rows were bound to a chapter + exact PDF page + PDF SHA-256 on the same source record. The live row count must be rechecked before final completion declaration.

## Publication gate and remaining work

The currently **confirmed published student assessment was v11** at the beginning of the four-source repair batch. The four source-only updates should NOT be represented as published student assessment explanation updates until an entirely **new independently verified backup after Q241/Q242**, current bank-versus-frozen preflight, owner-authorized canonical `import-bank.php publish` and complete frozen 243-question v11→v12 audit are successful. Demand **exactly 4** changes to frozen explanations (Q68, Q82, Q241, Q242), **zero** changes in stems/options/official answers/ordering/scoring, same 7 historically voided excluded and prior versions preserved, live status read-back, and healthy site.

At 143/250 strict structural completion, **107** raw missing, **20** final-key English exempt, **87** non-English historical classification cases unresolved (including six officially voided, so **81** scored non-English source gaps). The year-specific structured-scope review issues overlap these counts; do not add them. Many gaps have missing or incorrect legacy book-print page numbers, and must not be guessed or coerced through the guarded updater. For example, pediatric sealants Q158 is documented in McDonald–Avery 11e at PDF 234 / printed 223 but the old AI source incorrectly lists printed 220; protected page-only updater safely refuses such inconsistent evidence. Orthodontics and community dentistry source-row page binding is already complete; remaining high-volume gaps are periodontics, oral medicine, pediatric dentistry, surgery, endodontics and dental materials. **The 1402 case is not yet complete.**

## Final postmutation backup and published assessment v12 — completed, independently audited

A **new full production backup AFTER** the Q241/Q242 source-only mutation at 2026-10-12 21:04:14 UTC was completed at 21:07:07 UTC as `/var/backups/fanoos/20261012T210648Z-b075d8d7`. The canonical independent `scripts/ops/verify-backup.php` validated **114 files**. The before-publication full-sitting audit of existing **v11** returned `question_content_identical=true`, `full_sitting_questions=243` and 20/20 protected radiology-study questions matching.

The owner-authorized canonical `import-bank.php publish --type=residency --year=1402 --round=1` successfully published **frozen version 12**, version ID `01a131f1-a04c-7021-9fce-1d26ba3ecaf7`, with 243 scored questions and exactly the same officially voided Q60,Q96,Q99,Q102,Q103,Q144,Q181 excluded. Postpublication `audit_published_assessment.php --previous=11 --current=12` returned `question_content_identical=true`, `questions=243`, `updated_explanations=4`. An **independent** question-key-level exhaustive comparison of both frozen 243-question JSON definitions found precisely the four expected new source explanations Q68, Q82, Q241, Q242 and `unexpected=[]`, `missing=[]`. No stems, choices, keys, numbering, scoring or historical attempts changed; frozen version 11 and earlier versions were preserved.

Independent live SQL read-back confirmed `current_version_no=12`, `status=published`, and exact same-row chapter+original PDF page+PDF SHA-256 source completeness **143/250**. Site `/health` returned `status:ok`, deployed release `3ec7953b4c10b1cefe78f7242d4385876d15818b`. The actual PHP source binder and protected original PDF store never exported full licensed pages or changed non-source fields.

**Remaining unresolved:** 107 raw incomplete = 20 English book-exempt + 87 non-English; 6 of 87 were historically voided; **81 scored clinical question citations remain**. Exact official-year chapter scope and authoritative original page are still required together for every one before declaring the case complete. Year-scope flags overlap this total; do not double count. This document records completed actual v12 release, *not* completed scientific citation coverage.
