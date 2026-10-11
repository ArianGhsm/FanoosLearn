# Dentistry residency 1391 — 1397+ retrospective chapter and PDF page application (2026-10-11)

**Operationally verified:** 12 source+chapter+PDF-page citations INSERTED in live FANOOS bank; 208 / 220 questions remain without a same-row source+chapter+PDF-page link. No question, answer, option or frozen-assessment mutation was authorized or performed.

These are **retrospective** classifications against references officially listed from **1397 onward**. They are NOT claims about which books were official at the time of the 1391 sitting. Owner policy and source-year provenance: `docs/PROJECT_PRINCIPLES.md` (2026-10-11 exception), `docs/ops/PRE_1396_RETROSPECTIVE_CLASSIFICATION_20261011.md`, PR #298. Original 1391 official references remain unverified. The historical 1391 exam file imported into the bank labels its key source as an exam-question document; the official independent original key was not obtained. There are **217** answers marked final in that file and **3** voided questions (86, 111, 160); do not overstate that an independent 1391 official-key booklet was verified.

## Read-back verified live source rows

| Subject | Question numbers | Original-book edition | 1397 proxy reference | PDF page and edition chapter |
|---|---|---|---|---|
| Oral pathology | 31 | Neville Oral and Maxillofacial Pathology 4e | 1397 | PDF 13, ch 1 |
| Oral pathology | 32 | Neville 4e | 1397 | PDF 18, ch 1 |
| Oral pathology | 34 | Neville 4e | 1397 | PDF 177, ch 5 |
| Oral pathology | 39 | Neville 4e | 1397 | PDF 410, ch 10 |
| Oral pathology | 40 | Neville 4e | 1397 | PDF 471, ch 11 |
| Oral pathology | 41 | Neville 4e | 1397 | PDF 432, ch 11 |
| Oral pathology | 42 | Neville 4e | 1397 | PDF 497, ch 12 |
| Oral pathology | 44 | Neville 4e | 1397 | PDF 687, ch 15 |
| Oral pathology | 45 | Neville 4e | 1397 | PDF 652, ch 15 |
| Endodontics | 169 | Torabinejad Endodontics 5e | 1397 | PDF 26, ch 1 |
| Endodontics | 171 | Torabinejad 5e | 1397 | PDF 203, ch 11 |
| Endodontics | 173 | Torabinejad 5e | 1397 | PDF 252, ch 14 |

**PDF provenance:**

- Neville 4e, current original verified server PDF SHA-256 `6fbc9bcba9003deda2f8fc006ccc4f23f9e15db0bcd0e237c56ddf6788578bb6`.
- Torabinejad 5e, current original verified server PDF SHA-256 `71f21c26a4f1e5d038ff6aa408669a5cc040e90c94eea0bf2e00478997710146`.
- Both checked against their complete hash-bound chapter maps and direct original PDF page evidence via `apply_classification.py` and `audit_private_study_batches.py`.

**Source-only publication receipts in protected server research workspace:**

1. `/srv/fanoos/shared/research/classification/reports/1391-oral-pathology-retrospective-source-only-applied-20261011.json` — 3 inserts, Q31/40/41, source audit `f8e3b9b0e0d69c029e7dec5f90aa499f92c7850f1531470e2d48a3fd95bae28b`, backup `20261011T145712Z-e0f10040`.
2. `/srv/fanoos/shared/research/classification/reports/1391-oral-pathology-retro-followup-v2-applied-20261011.json` — 6 inserts, Q32/34/39/42/44/45, source audit `f54271e3a1152ebac87013217625d58b4a8734fc61ecea57fb4b2708659e551f`, same recent verified backup.
3. `/srv/fanoos/shared/research/classification/reports/1391-endodontics-retro-source-applied-20261011.json` — 3 inserts, Q169/171/173, source audit `52443e59bf66a57f48b17686de58815abfcb768e16efc007424ecf30d71d97f6`, independently verified fresh full backup `20261011T151814Z-9b10a276` (114 files verified).

For all 3 imports the guarded source-only publisher reported `questions_changed=0`, `choices_changed=0`, `answers_changed=0`. Strict read-back SQL confirmed 12 linked question records bearing the same-row `node_id`, positive `pdf_page` and `pdf_sha256`. Site health returned `status=ok` at live release `cd3b7913640dcb6ccdcb471e637e9e3236b89c4e`. Existing public assessments were not rebuilt or modified by source-only inserts; verify public UI source visibility separately if needed.

## Recovery, safeguards, and remaining work

Canonical original 220-question export: `/srv/fanoos/shared/research/bank-sittings/1391/residency-1391-1.json` SHA-256 `7ef8f7c4aabc620fe7fc132c76615a9f5517baa98f9d4c7a951f97f9efc2730a`. Research-only study and decision files are at:
- `bank-sittings/1391/oral-pathology-study.json`, `classification/decisions/1391-oral-pathology.json`, `classification/sittings/1391-oral-pathology-validated.json`;
- `bank-sittings/1391/endodontics-study.json`, `classification/decisions/1391-endodontics.json`, `classification/sittings/1391-endodontics-validated.json`.

The first three pathology input files were immutably archived before follow-up working files were refreshed:
`/var/backups/fanoos/research/20261011-residency1391-pathology-published-v1-inputs.tar.gz`, SHA-256 `01ee3e0d9769867358d7d7ddc7a72ac2d473dbfe471ee424d3d4b85892c9ffc1`; archive read-back compared source hashes. Full server backup retention is limited to five completed sets and may later prune historical archives; verify current availability at recovery time, keep private original source decisions and per-batch audit receipts durable.

Current backlog: **8** pending oral-pathology questions, **17** pending endodontics, all other subject questions not yet classified, and **3 voided questions** retained in the raw count. Therefore **208 raw incompletes**, of which **205** have non-voided keys in the imported question file. For the pending questions, determine actual answer-bearing *original PDF pages* one by one; never impute pages, equate a generic topical mention with proof, or insert chapter-only rows. Some apparent exam-key anomalies remain candidates for independent answer-integrity review; do not change keys in a source-only task.

For the next batch, snapshot live current sources and operator state; maintain per-question `reference_year`, `classification_basis=retrospective_proxy_1397_plus` in the protected decision/audit/receipt. Use the exact existing study-only workflow with preflight dry-run, hash-bound validator, independent private evidence audit, newly verified full recovery backup and source-only insert. Never replace existing source records or overwrite user/human decisions. Verify production DB, health and any publication change before reporting completion.


## Follow-up — 24 source/page citations in live database, 2026-10-11

After the 12-row baseline above, 12 additional **retrospective** citations were independently page-verified, audited, source-only inserted and reread from the live DB. The 12-row table above is an earlier checkpoint, **not** the latest count. Exact-year official original sources still remain unverified.

| Subject | Question | 1397+ reference edition | Chapter | Original PDF page |
|---|---:|---|---:|---:|
| Oral pathology | 38 | Neville 4e, reference-year 1397 | 10 | 365 |
| Orthodontics | 183 | Proffit 5e, reference-year 1397 | 5 | 138 |
| Orthodontics | 187 | Proffit 5e, reference-year 1397 | 4 | 118 |
| Orthodontics | 190 | Proffit 5e, reference-year 1397 | 5 | 161 |
| Orthodontics | 192 | Proffit 5e, reference-year 1397 | 6 | 179 |
| Orthodontics | 193 | Proffit 5e, reference-year 1397 | 6 | 178 |
| Orthodontics | 196 | Proffit 5e, reference-year 1397 | 6 | 214 |
| Orthodontics | 198 | Proffit 5e, reference-year 1397 | 11 | 426 |
| Orthodontics | 199 | Proffit 5e, reference-year 1397 | 13 | 538 |
| Dental materials | 155 | Powers-Wataha 11e, reference-year 1399 | 5 | 81 |
| Dental materials | 156 | Powers-Wataha 11e, reference-year 1399 | 13 | 191 |
| Dental materials | 157 | Powers-Wataha 11e, reference-year 1399 | 8 | 134 |

- Q38 source-only receipt `/srv/fanoos/shared/research/classification/reports/1391-pathology-q38-retro-source-applied-20261011.json`; audit `b3f6840f9dbe59c74c03c0f209723c560d4096f8099f6a2da8285af5ce4ec5e0`.
- Eight orthodontics receipts/audit: `/srv/fanoos/shared/research/classification/reports/1391-orthodontics-retro-source-applied-20261011.json`; audit `47d252ff0d4605b15578ba0866a01076b7498cf6c235693cee37d7e4fc39f1f8`. A first dry-run failed only because the temporary study-only JSON answer fields had a different **key order** to the importer's strict PHP array comparison; the original fields and values were retained, reordered, independently revalidated and re-audited, and then passed.
- Three dental materials: `/srv/fanoos/shared/research/classification/reports/1391-dental-materials-retro-source-applied-20261011.json`; audit `d7db30f47cdd920b6341794123767b2ffdcbcbfbb0c8f99d891f0a182a3925e8`.
- Both batches of Q38+orthodontics and the materials batch used verified full recovery backup `/var/backups/fanoos/20261011T185456Z-2a972a2b`, 114 files successfully verified. All apply receipts report `questions_changed=0`, `choices_changed=0`, `answers_changed=0`.
- The prior 6-decision oral pathology canonical file was separately hash-backed up to `/srv/fanoos/shared/research/ops/1391-oral-pathology-v2-before-q38-20261011.json` (SHA-256 `aaf0bfb6159c835ac1a6118da7900c9c1c41a26301d12c0525de1372b7e54096`), temporarily staged with one Q38 decision for the guarded source-only importer and **restored** exactly after successful Q38 publication. The earlier 6-decision validated sitting was likewise preserved separately.
- Same-row `node_id`, positive `pdf_page`, and `pdf_sha256` readback: **24 / 220**. Pending **196 raw questions** = 193 with non-voided keys and 3 voided questions. Count no source unless chapter **and exact PDF page** are present in that same row.
- **Syllabus guard example:** Proffit 5e chapter 2 is **outside the 1397 scope**; Q181 and Q182 therefore remain pending rather than being falsely called 1397-exam scope citations, despite finding topical PDF pages. A later 1398+ proxy year requires genuine own-year scope verification.

The complete work remains in progress. No 1391 original official-key booklet was independently obtained; source-only records and evidence do not change official answers.


## Latest follow-up: 28 live same-source chapter + PDF page references

A subsequent oral-radiology batch used **White/Pharoah Radiology 8e**, the genuine 1399
reference-year list, as a specifically labeled `retrospective_proxy_1397_plus` for
1391. Direct original-PDF page evidence, SHA-bound chapter maps, independent
provenance audit, rollback preview, verified full backup and guarded source-only
production insert all passed.

| Question | Chapter | Exact PDF page |
|---:|---:|---:|
| 142 | 24 | 1257 |
| 147 | 3 | 120 |
| 149 | 1 | 60 |
| 150 | 2 | 90 |

Private receipt: `/srv/fanoos/shared/research/classification/reports/1391-oral-radiology-retro-source-applied-20261011.json`.
Audit SHA-256 `4a95ab95bb4d27d73c05de7b707f92a967b00a95c7d6f9a3134b0ece2585850d`.
Verified 114-file full backup `/var/backups/fanoos/20261011T190700Z-9101ae81`.
Receipt reports `source_rows=4` and zero question/choice/answer modifications.
**Live database strict read-back: 28 / 220 recorded-complete, 192 raw pending
(189 non-voided plus 3 voided).** Earlier 12/24 counts are historical
checkpoints. Neither later-year books nor the unverified original key are
mislabelled official for 1391.
