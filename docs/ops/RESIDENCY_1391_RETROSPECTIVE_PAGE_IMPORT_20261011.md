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


## 2026-10-12: four pediatric chapter+verified PDF-page imports — live count 32

Direct original-book evidence in the hash-verified, server-private Nowak
Pediatric Dentistry 6e PDF from the **official 1399 reference list**; the
1391 exam itself is not claimed to have used that edition officially.

| Question | Exact edition | Official proxy year | Chapter | Original PDF page |
|---:|---|---:|---:|---:|
| 201 | nowak-pediatric@6e | 1399 | 3 | 82 |
| 204 | nowak-pediatric@6e | 1399 | 14 | 248 |
| 207 | nowak-pediatric@6e | 1399 | 16 | 278 |
| 218 | nowak-pediatric@6e | 1399 | 34 | 561 |

- Exact private 20-question study-only file:
  `/srv/fanoos/shared/research/bank-sittings/1391/pediatric-dentistry-study.json`.
- Four independently checked original-PDF evidence decisions:
  `/srv/fanoos/shared/research/classification/decisions/1391-pediatric-dentistry.json`.
- Validator: `4 accepted, 0 rejected, 16 not yet decided`.
  Independent provenance audit:
  `/srv/fanoos/shared/research/classification/reports/1391-pediatric-dentistry-retro-audit-20261012.json`
  SHA-256 `251e70f4ed55df49746203169ec12e94695a7f1124b785b05c4db62e23b7207a`.
- Guarded source-only preview: 4 inserts, zero item, choice or answer changes.
  Verified full production backup `/var/backups/fanoos/20261012T035946Z-27488df0`
  (114 files); source-only **applied** receipt:
  `/srv/fanoos/shared/research/classification/reports/1391-pediatric-dentistry-retro-applied-20261012.json`,
  completed 2026-10-12 04:03:20 UTC with
  `questions_changed=0, choices_changed=0, answers_changed=0`.
- Direct current production DB strict same-source chapter+positive PDF page+SHA
  readback: **32 of 220**. **188 raw missing**, comprising 185 non-voided
  imported keys and 3 historically voided questions (86, 111, 160).
- Additional scientific research: Carranza Periodontology 12e Q65 chapter 1
  PDF 37, Q67 chapter 1 PDF 42. Their 1397 reference-year list has
  **no independently machine-readable chapter scope**, so validation correctly
  rejected them and **neither has been published or counted**. The authentic
  1398 list does identify chapter 1, enabling a later 1398 proxy-year check.
  PR #334 added a tested rule to choose the earliest 1397+ official year
  with a verifiable structured scope; merge succeeded, but deployment to the
  live server and source publication must be independently confirmed before
  either Carranza candidate counts.

This supersedes the earlier 28-row checkpoint above; neither unverified
question key authenticity nor original 1391 official reference years are
misrepresented.


## 2026-10-12: five verified oral medicine exact chapter/PDF-page imports (37 live)

These original PDF-page sources were selected from the **authentic official
1398** Burket Oral Medicine **12e** reference list, *only* as owner-approved
`retrospective_proxy_1397_plus` for the 1391 exam. The source's original
1391 official status remains unverified.

| 1391 question | Exact book | Official proxy year | Chapter | Original PDF page |
|---:|---|---:|---:|---:|
| 48 | burket-oral-medicine@12e | 1398 | 4 | 95 |
| 49 | burket-oral-medicine@12e | 1398 | 4 | 80 |
| 51 | burket-oral-medicine@12e | 1398 | 5 | 123 |
| 56 | burket-oral-medicine@12e | 1398 | 7 | 184 |
| 58 | burket-oral-medicine@12e | 1398 | 10 | 268 |

- Original 17-item study-only input:
  `/srv/fanoos/shared/research/bank-sittings/1391/oral-medicine-study.json`
  (SHA-256 `9a00753b9534297eff5926af657849314a0e6cdcc03ba325c460df013f116f87`).
- Research decisions:
  `/srv/fanoos/shared/research/classification/decisions/1391-oral-medicine.json`;
  accepted 5, rejected 0, pending 12; exact original PDF/edition, mapped
  chapter, positive page and quote checked. Independent private audit:
  `/srv/fanoos/shared/research/classification/reports/1391-oral-medicine-retro-v3-audit-20261012.json`,
  audit SHA-256 `b02c9a728570a134a27893225a947e995d09f1cb6940ee2db5530e4beaf1ef73`.
- Verified full 114-file production backup:
  `/var/backups/fanoos/20261012T041814Z-19bd9989`.
- Guarded source-only **applied** receipt:
  `/srv/fanoos/shared/research/classification/reports/1391-oral-medicine-retro-applied-20261012.json`,
  5 source inserts, `questions_changed=0`, `choices_changed=0`,
  `answers_changed=0`, completed 2026-10-12 04:22:34 UTC.
- Independent strict same-row source+chapter+positive PDF page+PDF SHA
  production DB readback: **37 / 220**. **183 raw pending**,
  180 with nonvoided imported answer status plus 3 historically voided.

Six Carranza12e research candidates Q65/66/67/72/75/83 against the
**structured 1398** validity list are documented in
`docs/ops/RESIDENCY_1391_CARRANZA_SIX_SCOPED_RESEARCH_20261012.md`;
they are **not published or counted**. The PR #334 reference selector fix
is merged but must be present in a separately verified production release
before any normal validator/audit/source-only publication of those candidates.


### Same-day follow-up: sixth oral medicine source (38 total)

- Q52: **Burket Oral Medicine 12e**, genuine official reference year **1398**,
  chapter **5**, **original PDF page 111**. Direct-page evidence distinguishes
  erythroplakia from erythematous candidiasis by sharper lesion demarcation.
- Independent single-question study:
  `/srv/fanoos/shared/research/bank-sittings/1391/oral-medicine-q52-study.json`;
  source decision:
  `/srv/fanoos/shared/research/classification/decisions/1391-oral-medicine-q52.json`;
  validated study:
  `/srv/fanoos/shared/research/classification/sittings/1391-oral-medicine-q52-validated.json`.
- Accepted 1, rejected 0. Independent private audit:
  `/srv/fanoos/shared/research/classification/reports/1391-oral-medicine-q52-retro-audit-20261012.json`,
  audit SHA-256 `ba8fe57a26b91194784eec185c3d4208335e89729efe4b69dfb957f6f614ad84`.
- Independently verified recent complete 114-file backup:
  `/var/backups/fanoos/20261012T042639Z-a393f330`.
  Guarded source-only applied receipt:
  `/srv/fanoos/shared/research/classification/reports/1391-oral-medicine-q52-retro-applied-20261012.json`,
  reports source 1, **zero question/choice/answer changes**, UTC
  `2026-10-12T04:28:37Z`.
- The prior five-decision audit input was preserved at
  `/srv/fanoos/shared/research/ops/1391-oral-medicine-pre-q52-decisions-20261012.json`
  SHA-256 `dfd439ee7b8d472e3d502c86f67e8feb7e57f72d46d59a36d5ec5d893e026323`.
  After Q52's standalone study-only audit and insertion, the five-decision
  canonical file was restored *byte for byte* to that hash.
- **Independent strict live same-row edition+chapter+PDF page+SHA readback:
  38 of 220 questions**. Remaining 182 raw, including 3 voided.
  Earlier 32/37 counts above are historical checkpoints.


## 2026-10-12 follow-up: four additional Neville original-PDF chapter/page sources — live 42

Four residency-1391 oral-pathology question sources were inserted in two
independently audited, full-backup-protected **source-only** batches.
The book is **Neville Oral and Maxillofacial Pathology 4e**, in an *actual*
official **1397** reference list, cited only as an explicitly labelled
**retrospective proxy** for exam year 1391.

| Question | Neville4e chapter | Exact original PDF page | Direct passage context |
|---:|---:|---:|---|
| 33 | 2 | 100 | Amelogenesis imperfecta, generalized pitted type |
| 35 | 3 | 125 | Periapical granuloma, lymphocytic inflammatory infiltrate |
| 43 | 12 | 532 | Most common site of oral soft-tissue metastasis is gingiva |
| 47 | 16 | 729 | Bullous pemphigoid most common autoimmune blistering condition |

- Current server PDF of Neville4e SHA-256
  `6fbc9bcba9003deda2f8fc006ccc4f23f9e15db0bcd0e237c56ddf6788578bb6`,
  matched against the original hash-bound chapter/page map.
- **First publication Q33+Q35:** protected 2-question study
  `/srv/fanoos/shared/research/bank-sittings/1391/oral-pathology-q33q35-study.json`;
  candidate decisions
  `/srv/fanoos/shared/research/classification/decisions/1391-oral-pathology-q33q35.json`;
  strict validator accepted 2, rejected 0.
  Independent audit
  `/srv/fanoos/shared/research/classification/reports/1391-oralpathology-q33q35-independent-audit-20261012.json`
  SHA `33a658007d595035c781e1e218d6aefcc9dbf0ecb295d443e86cf386edc402a7`.
  Verified complete 114-file production backup
  `/var/backups/fanoos/20261012T153557Z-38668c66`.
  Guarded applied receipt
  `/srv/fanoos/shared/research/classification/reports/1391-oralpathology-q33q35-applied-20261012.json`,
  2 inserts, zero questions/choices/answers changed, completed 15:41:18 UTC.
- **Second publication Q43+Q47:** protected study
  `/srv/fanoos/shared/research/bank-sittings/1391/oral-pathology-q43q47-study.json`;
  decisions
  `/srv/fanoos/shared/research/classification/decisions/1391-oral-pathology-q43q47.json`;
  strict validator accepted 2, rejected 0.
  Independent audit
  `/srv/fanoos/shared/research/classification/reports/1391-oralpathology-q43q47-independent-audit-20261012.json`
  SHA `6e477b8fd1473a8a000ebc1e70fbce9249d24462b4ed216b57ea50951165a90f`.
  Verified full 114-file backup
  `/var/backups/fanoos/20261012T154715Z-06739959`.
  Guarded applied receipt
  `/srv/fanoos/shared/research/classification/reports/1391-oralpathology-q43q47-applied-20261012.json`,
  2 inserts, zero content/answer mutations, completed 15:48:34 UTC.
- Before both independent audits/publishers the previous six-decision
  canonical oral pathology file was saved **byte-for-byte** with SHA
  `aaf0bfb6159c835ac1a6118da7900c9c1c41a26301d12c0525de1372b7e54096`
  to distinct private protected `ops/1391-oralpathology-prior-six-decisions-before-*`
  files. Temporary staging was needed for the auditing/publishing contract,
  and a shell EXIT trap restored the previous canonical file on success
  or failure.
- **Direct live DB strict same-row source+chapter+PDF page+SHA readback: 42/220**.
  **178 raw remain** (175 with nonvoided imported answer metadata plus 3
  voided). Previous counts 12/24/28/32/37/38/40 are historical checkpoints.

### Separately researched prosthodontics — NOT YET LIVE at the 42-row checkpoint

Against **McCracken RPD 12e, official reference year 1397**, exact PDF
quotes and chapter-map membership were validated for Q105 (ch7 PDF91),
Q106 (ch4 PDF32), Q110 (ch7 PDF74), Q112 (ch21 PDF306).
Protected `bank-sittings/1391/prosthodontics-study.json`,
`classification/decisions/1391-prosthodontics.json`,
`classification/sittings/1391-prosthodontics-validated.json`.
Full strict validator and independent audit:
`/srv/fanoos/shared/research/classification/reports/1391-prosthodontics-v2-original-pdf-audit-20261012.json`;
4 accepted, 0 rejected, 26 pending in the 30-question study.
Guarded rollback preview accepted 4 and reported **zero question/choice/answer
mutations**, audit SHA `52cc584a5c0cc2d5fabade4eb611639cc13e1552f7bd4d6a09378f6611cbdc33`.
**Do not count these four until the verified full backup, applied receipt,
and strict DB readback are complete.**

Original 1391 official books and independently authenticated final official
answer booklet remain unverified. No historical reference status was invented.


## 2026-10-12 final follow-up: 46 verified LIVE same-source chapter+PDF page citations

The four McCracken RPD 12e candidate citations in the previous section
have now been **actually published**, not merely previewed.

| Question | Official retrospective reference year | Edition | Chapter | Exact original PDF page |
|---:|---:|---|---:|---:|
| 105 | 1397 | mccracken-rpd@12e | 7 | 91 |
| 106 | 1397 | mccracken-rpd@12e | 4 | 32 |
| 110 | 1397 | mccracken-rpd@12e | 7 | 74 |
| 112 | 1397 | mccracken-rpd@12e | 21 | 306 |

The protected 30-question study input is `/srv/fanoos/shared/research/bank-sittings/1391/prosthodontics-study.json`;
the research decisions are at `/srv/fanoos/shared/research/classification/decisions/1391-prosthodontics.json`;
the strict validator accepted 4 of 4 decided, rejected 0. Independent
audit `/srv/fanoos/shared/research/classification/reports/1391-prosthodontics-v2-original-pdf-audit-20261012.json`
SHA-256 `52cc584a5c0cc2d5fabade4eb611639cc13e1552f7bd4d6a09378f6611cbdc33`.
The complete original 114-file recovery backup
`/var/backups/fanoos/20261012T155537Z-0350e9a7` was verified. Guarded
source-only applied receipt
`/srv/fanoos/shared/research/classification/reports/1391-prosthodontics-retro-applied-20261012.json`
records 4 inserts completed 15:57:55 UTC and
`questions_changed=0, choices_changed=0, answers_changed=0`.

**Current independently queried production DB count: 46/220**, leaving
174 raw incomplete including 3 voided. This supersedes the 42-row
checkpoint and the previous candidates-not-live status.


## Additional verified Q46 oral pathology source (47/220 LIVE)

**2026-10-12:** residency1391 Q46 now source-only **published** using
Neville Oral Pathology 4e, *actual 1397 retrospective reference-year*,
**chapter 14, original PDF page 624**. Original passage identifies
complete primary surgical removal as the main prognostic determinant
of jaw osteosarcoma.

- Protected study: `/srv/fanoos/shared/research/bank-sittings/1391/oral-pathology-q46-study.json`.
- Decision: `/srv/fanoos/shared/research/classification/decisions/1391-oral-pathology-q46.json`.
- Validator accepted 1, rejected 0. Independent audit
  `/srv/fanoos/shared/research/classification/reports/1391-oralpathology-q46-original-page-audit-20261012.json`,
  SHA `39bfc0e411a83fe0ce86a9dd2d5a8be90bf5870100e010cbf25444f0549ae11b`.
- Six previously published oral-pathology research decisions were
  SHA-preserved privately at
  `/srv/fanoos/shared/research/ops/1391-oralpathology-prior-six-decisions-before-q46-20261012.json`.
  The audit/publisher staging was restored with a shell EXIT trap.
- Verified fresh 114-file full backup
  `/var/backups/fanoos/20261012T160058Z-a923565c`.
  Guarded applied receipt
  `/srv/fanoos/shared/research/classification/reports/1391-oralpathology-q46-applied-20261012.json`:
  1 source, zero question/choice/answer changes,
  completed 2026-10-12 16:02:41 UTC.

Strict production SQL readback **47 / 220 source+chapter+original-PDF-page+hash**.
**173 raw missing** including three historical voids. This supersedes
the previous checkpoints. Genuine 1397+ references remain labeled
retrospective proxies, not 1391-original official editions.
