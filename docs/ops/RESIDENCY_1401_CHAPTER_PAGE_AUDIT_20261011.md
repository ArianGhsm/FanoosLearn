# Residency 1401 — chapter plus exact PDF page research, 2026-10-11

**Status: read-only evidence; no bank or published assessment mutations.** Chapter, original-PDF page and bound PDF SHA-256 must be present in the same source row before a question is counted as recorded-complete. Merely having a printed or legacy page is insufficient.

## Production baseline (read-only audit)

- Residency 1401 round 1 has **249** bank questions in 13 subjects.
- Only **67** have a same-row chapter node, PDF page and PDF SHA-256. **182** lack this bound metadata.
- Of the 182, **19 English questions** are exempt from book references by PROJECT_PRINCIPLES section 3A. Their latest official answers are all final with a non-null choice. Do not create English book source rows.
- Therefore **163 non-English questions** need source-page evidence or repair: **151** already have a source but lack bound PDF-page metadata (none is human-origin); **12** have no source (11 oral radiology; one pediatric dentistry).
- The 67 recorded fields are not themselves proof of independent scientific page evidence.

| Subject | Total | Same-row chapter + PDF page + SHA |
|---|---:|---:|
| Community dentistry | 10 | 10 |
| Dental materials | 10 | 0 |
| Endodontics | 20 | 0 |
| English (key-only exemption) | 19 | 0 |
| Operative dentistry | 20 | 18 |
| Oral medicine | 20 | 0 |
| Oral pathology | 20 | 0 |
| Oral radiology | 20 | 9 |
| Oral surgery | 20 | 0 |
| Orthodontics | 20 | 20 |
| Pediatric dentistry | 20 | 0 |
| Periodontics | 20 | 0 |
| Prosthodontics | 30 | 10 |
| **Total** | **249** | **67** |

### Exact-edition original-book check: Torabinejad Endodontics 6e

The exact approved 1401 book PDF has **501 PDF pages** and SHA256 **09333f079cb3300550cc0702985aae216d952eacabdaac005e1c66fafebe13e0**. A one-page-at-a-time read-only search through the official SHA-bound chapter map found an existing exact anchor phrase on a PDF page of the recorded in-scope chapter for the following ten 1401 questions.

| Question | Original chapter | Exact matching PDF page |
|---:|---:|---:|
| 21 | 1 | 19 |
| 22 | 1 | 18 |
| 23 | 2 | 36 |
| 26 | 11 | 221 |
| 27 | 11 | 220 |
| 30 | 15 | 353 |
| 31 | 15 | 337 |
| 32 | 8 | 158 |
| 35 | 18 | 407 |
| 36 | 19 | 422 |

**These ten are unpublished research candidates.** Their live source records remain printed-page-only; the new strict recorded-complete count has NOT increased. The other ten endodontics anchor phrases were not exactly located in the initial full-PDF scan; do not impute a page offset or invent evidence.

A 13-edition metadata-only read-only audit was initiated under protected server job **job_5d25bbaaa6e7**. Proposed output path: /srv/fanoos/shared/research/classification/reports/1401-legacy-page-evidence-audit-20261011-v1.json. Do not claim the report or any additional findings exist before independent job completion, SHA read-back and verification. This operation reads individual PDF pages transiently without saving textbook text and checks exact anchored phrase, original chapter, official 1401 scope, SHA and confidence.

## Safe continuation protocol

1. Read back the complete private audit and its SHA; review all proposed page matches and scope exceptions against the same exact verified PDF and hash-bound chapter map. Reproduce using a pinned release, never infer PDF numbers from book printed-page offsets.
2. The existing backfill_source_pages.php converts explicit legacy 'pdf N' locations. It does **not** convert printed N to PDF N. SourceOnlyPublisher is insert-only and refuses already-sourced rows. Neither can correctly publish these 151 old rows without a separately reviewed, strictly controlled repair operator.
3. Implement and test a source-page-only repair on a feature branch, requiring original source ID, AI origin, same anchor fingerprint, unchanged existing chapter and edition, official scope, exact PDF SHA, unique valid page match, preview, backups and durable receipts. Do not alter question/choices/official key, human decisions, historical versions or student attempts.
4. With up-to-date server state and an independently verified fresh full backup, dry-run and atomically repair only eligible source page/hash fields, verify DB results and frozen exam content, then publish through the authorized review path if student-facing source explanations must change. Require green CI, merge and official updater for any deployed code changes; verify release SHA and health.
5. Independently evidence-map the 12 unsourced non-English questions using the exact year's references and the guarded source-only pipeline. Leave unsupported pages pending.
6. Update this document, private receipts and recovery archive with **actual** executed data changes; never treat discovery as publication. Multiple other year-specific workers may update the server concurrently, so re-check it before writing.

Current read-only inspection saw matching live and GitHub main at **2de5b8da121dfdd225923adeb386de839a086711**; this is not a guarantee of future consistency.


## Completed 13-edition research audit — private report independently read back

The private report was successfully generated, read back and its SHA-256 verified on the server. Its path is /srv/fanoos/shared/research/classification/reports/1401-legacy-page-evidence-audit-20261011-v1.json; **SHA256 d143e48cd9cbfe87dfc048cb9d31db0f68f6248e38b1fbca589ed0b199c87f68**. It covers exactly **151** previously sourced but PDF-page-incomplete records, and uses the exact approved original private server PDFs, bound chapter map and official 1401 validity.

| Exact reference edition | Rows inspected | Unique qualifying original-page matches | Remaining pending |
|---|---:|---:|---:|
| Burket 12e | 12 | 0 | 12 |
| Carranza 13e | 20 | 0 | 20 |
| Craig restorative materials 14e | 2 | 0 | 2 |
| Hupp 7e | 14 | 5 | 9 |
| Little-Falace 9e | 8 | 0 | 8 |
| Malamed 7e | 6 | 2 | 4 |
| McCracken RPD 13e | 10 | 0 | 10 |
| Neville 4e | 20 | 0 | 20 |
| Nowak 6e | 19 | 0 | 19 |
| Powers-Wataha 11e | 2 | 2 | 0 |
| Torabinejad 6e | 20 | 5 | 15 |
| Van Noort 4e | 8 | 0 | 8 |
| Zarb 13e | 10 | 3 | 7 |
| **Total** | **151** | **17** | **134** |

The 17 stringent match candidates have original-PDF page, year-valid edition, in-scope original chapter, literal complete quoted anchor fragments on a unique mapped page, origin AI and original page confidence >=0.85. By exam question number / chapter / PDF page:

- Oral surgery, Hupp 7e: **132/2/42, 134/11/215, 135/14/274, 138/23/499, 140/24/522**
- Oral surgery, Malamed 7e: **145/7/134, 148/16/311**
- Dental materials, Powers-Wataha 11e: **213/4/63, 214/9/140**
- Endodontics, Torabinejad 6e: **22/1/18, 23/2/36, 30/15/353, 31/15/337, 36/19/422**
- Prosthodontics, Zarb 13e: **83/15/332, 85/8/181, 87/12/286**

The earlier ten Torabinejad literal locations are correct *location discoveries*; only five passed the audit's **additional** pre-existing source confidence/eligibility and unique-match guards. The five others are not authorized for an automatic repair. The 134 pending comprise **63** without an exact existing anchor match, **61** failing strict anchor/scope/AI-confidence preconditions, and **10 McCracken 13e** blocked by an approved PDF versus chapter-map SHA mismatch. Rebuild and independently validate that exact edition map before touching those ten; do not bypass the hash guard or substitute another edition.

**Publication status remains ZERO newly completed rows and ZERO source/question/answer/assessment changes.** The accepted candidate page list is a research file, not a source repair receipt. Before a live update, inspect and test the safe existing-source page repair operator, get a fresh independently verified backup, verify unchanged source identity/edition/chapter/original quote and the frozen exam, and follow the source-only reviewed publication/deployment gates. Neither the 17 research matches nor the other 134 are counted as completed live classifications.


## Production publication of twelve verified PDF page locations — 2026-10-11

**Actual result independently re-read:** the residency 1401 book-chapter+bound-PDF-page count increased **67 → 79 / 249**. Exactly **12 existing AI source rows** had only their previously NULL `pdf_page` and `pdf_sha256` fields added. No question, choice, official answer, edition, chapter, historic printed/legacy page, anchor, review state, exam assessment, or attempt record was modified. **151 non-English questions remain incomplete** (139 historical sourced but still without bound PDF page, 12 genuinely unsourced); 19 final-key English questions remain citation-exempt.

### Verified individual mappings actually stored

| Subject / exact official edition | Question → existing chapter / PDF page |
|---|---|
| Oral surgery, Hupp 7e | 132 → 2 / 42; 134 → 11 / 215; 135 → 14 / 274; 138 → 23 / 499; 140 → 24 / 522 |
| Oral surgery, Malamed 7e | 145 → 7 / 134; 148 → 16 / 311 |
| Endodontics, Torabinejad 6e | 22 → 1 / 18; 31 → 15 / 337; 36 → 19 / 422 |
| Prosthodontics, Zarb 13e | 83 → 15 / 332; 87 → 12 / 286 |

**Evidence provenance and controls:** previous independent source+chapter+PDF-page report SHA `d143e48cd9cbfe87dfc048cb9d31db0f68f6248e38b1fbca589ed0b199c87f68`; direct printed-label-neighbor and actual legacy-anchor original-PDF checks `1401-existing-page-neighbor-corroboration-20261011.json`, SHA `66d7b3223d2baf5c57dab7c7b7cdc11107d547fe436b8d39040942a76a88f3b9` (17 examined, 12 qualified, five held); 12 per-question protected study+decision+validated+independent audit packages (12/12 passing), stage index `1401-private-single-question-audit-stages-20261011.json` SHA `1e160ae8a5361911dc9df0b06bb2f877132f902d5bf0450ba800166dfbaeee47`; 12/12 individual source-page rollback previews SHA `cbf8f0219c3cd899da90ad3d0a4f59b726cb9d5dd50929b0d77924ce7d3dcf43`.

The three reviewed **atomic, source-metadata-only group plans** are private and byte-pinned:
- surgery plan `1401-surgery-page-repair-plan-20261011.json` SHA `4a5ade4c107df8cfe72d4095d9561ad12aa83eb915b22ab3bcd2dfdeb0347f22`, seven candidates;
- endodontics plan `1401-endo-page-repair-plan-20261011.json` SHA `ee743dabe5161c7b01fceea951b869eafaf29574ee2d4c8e919b686b87c505fa`, three candidates;
- prosthodontics plan `1401-prostho-page-repair-plan-20261011.json` SHA `f1c687f67a8790f8d69a783772a8cb23a3cbf945902eebd17ca0ba1b3f01a6e7`, two candidates.

The deployed operator is `scripts/references/repair_verified_source_pages.php`. Its real database rollback previews all passed (seven, three, two; no non-source mutations); report `1401-batch-verified-page-repair-previews-20261011.json` SHA `77370f5c4a8f0442df09b0dbe00a034070d726eb43a83f6024b2b570ee043467`. An independent frozen 249-question assessment preflight passed for all three affected subjects, prior version **6**, receipt `1401-frozen-assessment-preflight-20261011.json` SHA `ac4d30803570ef6d2926f41dc179f5e87fb75ab1cb8003d9acd155fb84edba35`.

### Deployment, backup, live applies and receipts

The approved GitHub `main` CI completed successfully for code SHA `cd3b7913640dcb6ccdcb471e637e9e3236b89c4e` and the official deployment request `01a12b64-6e4e-72bf-bdb5-f5aee1a21018` finished **SUCCEEDED** (UTC 2026-10-11 14:39:13); the live `/health` endpoint returned `ok` with exactly that release SHA. Deployment's fresh full backup `/var/backups/fanoos/20261011T143835Z-3e7ca9e5` was **independently verified, 114 files including database.sql**, and authorized all three source-only updates under the enforced four-hour freshness window.

Three actual atomic applies via that *deployed* operator, all protected with unique receipts:
- surgery seven applied UTC 14:41:15, `1401-surgery-verified-page-repair-20261011.json` SHA `eda426c37da7ce15b8a91d725cf3f97045f4e8e79d111f36a99ffe9a799ac8f8`;
- endodontics three applied UTC 14:41:57, `1401-endo-verified-page-repair-20261011.json` SHA `2da5cd0f977df93d0640be71283f9b9f4b7a53894e1bb7c78796bb84354d18ec`;
- prosthodontics two applied UTC 14:42:33, `1401-prostho-verified-page-repair-20261011.json` SHA `42d00fa5512681c37b2c17726973460c00c2d81ea372e5c67c60a2517e5c2d58`.

Independent **database read-back checked all 12 exact existing source IDs**, their current PDF pages and exact PDF hashes, previous chapter and edition, original printed/legacy label, original evidence-anchor SHA, zero human review, and a live comparison of every affected stem, all choices and the latest official answer against the immutable per-case input. No mismatch. Protected proof `1401-12-page-source-final-readback-20261011.json` SHA `a008f9fd4e29f52ef6c8685471a64148317bf0724ae046cb4e812e444afa293c`. Live strict count **79 of 249**, after both source update and independently verified read-back.

**Frozen student assessment was intentionally not republished.** The live assessment remains **version 6**. A read-only comparison of its full 249-question frozen definition with the current BankPublisher preview found **no changed explanations for any of the 12 repaired questions** and **zero non-source field-value changes**. The preview did find **141 unrelated historical source-explanation differences**, which would be swept into a new assessment version if a blind whole-year publish were executed. These were not authorized by this narrow PDF-page repair; they are held for a separate audit. Receipt `1401-assessment-publication-gate-20261011.json` SHA `2cb5c8cd89775e3d8e43aeedd4580f6033c112fa118a074735a4521cc543c847`. No assessment version or student attempts were changed.

### Protected evidence recovery and continuation

All 12 private per-question study/decision/audit packages and fourteen protected batch reports/receipts were archived at `/var/backups/fanoos/research/20261011-residency1401-12-source-page-repairs-evidence.tar.gz`, SHA-256 `585e76b0ec778b5b19b6ec5ab21069b2682183f69b762f111639d064867113bc`. The archive was re-opened and all **62 files compared byte-by-byte** to the original inputs; a checksum sidecar was created and read back. This is an **on-server** archive, not offsite backup. The official verified retention operator dry-run then applied the configured **five completed sets** limit, deleting only the oldest completed verified full set `20261011T072401Z-4c8d0351`; the newer pre-repair backup and research archive remain.

The remaining 151 non-English questions must be handled per same-year official edition, in-scope original chapter, exact server PDF page and hash. The five previously found but not strictly eligible PDF locations remain on hold (legacy anchor normalization or printed-page-neighbor issues); do **not** lower guards or copy a constant printed-page offset. The ten McCracken 13e cases remain blocked by the exact official PDF/chapter-map SHA mismatch, not by chapter naming alone. Separate fresh guarded source-only insertion is needed for 11 unsourced radiology and one unsourced pediatric question. All other old printed-only sources remain intact. Re-query production and current GitHub/deployment before attempting any additional repair.


## Further original-book recovery: 3 additional pages actually verified and applied

**Subsequent production result:** three more historical AI sources received exact page and PDF hash using the same approved source-page-only operator, bringing strict 1401 completeness from **79 → 82 / 249**. The full 1401 improvement during this operation is **67 → 82**, i.e. **15 actual verified source-page repairs**. **148 non-English book-based cases remain incomplete** (136 already source-linked but not complete, 12 without any source); 19 English final-key questions remain explicitly exempt.

| Question | Exact official edition | Existing chapter | Verified PDF page | Preserved printed page |
|---:|---|---:|---:|---:|
| 30 | Torabinejad Endodontics 6e | 15 | 353 | 346 |
| 213 | Powers-Wataha Materials 11e | 4 | 63 | 45 |
| 214 | Powers-Wataha Materials 11e | 9 | 140 | 122 |

The three had passed the original independent 13-book chapter+page search but were withheld earlier because the historic full-length evidence strings were split across PDF columns or line layout. They were recovered using **new short, verbatim quotes from the same original authenticated PDF pages** and independently checked printed-page neighbor labels, while **leaving all historical anchor text completely unchanged**. Original additional read-only science proof: `/srv/fanoos/shared/research/classification/reports/1401-three-rescued-original-page-proof-20261011.json`, SHA `80430c379de422d20be650bf14a55a10fc2d4c0ed4db5ca2d095697da7ca115d`.

For each question, independently exported current question/choices/official key and created protected one-question original study, decision and validator package under `/srv/fanoos/shared/research/classification/recovery-1401-{endo30,materials213,materials214}-20261011/`; `apply_classification.py` and the independent original-PDF `audit_private_study_batches.py` succeeded **3/3** with no original-question change. Per-case independent audit SHA: endo30 `4c6242fa9fb3e3708c2433998871e2fab9cf9960384ea6265b591c6e0f4a5bd6`; materials213 `b02f586115240c79dbc99368aea975968f9b71d5bcb3339dd078ff925a958a4c`; materials214 `870679402bdbf139322af753be0774f924e0ce9f9fbdeb901d4a032217f92955`. Aggregated audit `1401-three-original-anchor-audits-20261011.json` in the protected reports directory.

Two uniquely identified private batch repair plans, **both with successful rollback-only transaction previews and zero question/choice/answer changes**, were then applied from the **already deployed** green release `cd3b7913640dcb6ccdcb471e637e9e3236b89c4e` after a fresh independent verified full backup `/var/backups/fanoos/20261011T145712Z-e0f10040` (114 files including `database.sql`):
- `1401-endo30-rescue-page-plan-20261011.json`, SHA `0980d3924d026182086d2e2715a2e5acbf7030513a2ab051c11a0405dcec81e9`. Applied one official Torabinejad PDF page with unique receipt `1401-endo30-rescue-page-applied-20261011.json`, SHA `22d04e233a8d49e2293059af83af63df044af1546b5ff67bcb25cc9bbb1ea211`, UTC 15:00:48.
- `1401-materials-213-214-rescue-page-plan-20261011.json`, SHA `71226355c735c96ff1ec88bb9698102f151bfa5b41ba6a9a90c9854fab5f3443`. Applied two Powers-Wataha PDF pages with unique receipt `1401-materials-213-214-rescue-page-applied-20261011.json`, SHA `bb9e811e1df567decfa508b0c49babd5a9c026af8825b235dd483af9a94ca5e5`, UTC 15:01:07.

Independent production re-query verified all **three exact original source IDs**, their unchanged edition/chapter, historic printed/legacy labels, original anchor SHA and AI/non-human review state, **all original question stems and option arrays and latest official answers** against each protected original study, and exact current `pdf_page` and `pdf_sha256`. Strict total independently read back **82/249**, 148 pending non-English. Private receipt `1401-three-additional-original-page-final-readback-20261011.json`, SHA `6c6478141a23d898a66b917e5fdaf448ec8205a5ae6b579a6bc6bd6c2c8f9b2b`. Only `pdf_page` and `pdf_sha256` were changed in the three sources. No student exam republish has been authorized as part of this narrower source metadata repair: the assessment remains version 6, and prior evidence of 141 unrelated historical explanation differences calls for separate examination rather than a whole-exam automatic publish.

The three extra studies, decisions, original-page scientific checks, plans and receipts are protected in a **second independently extracted-and-byte-verified 19-file on-server recovery archive**, `/var/backups/fanoos/research/20261011-residency1401-three-additional-pdf-pages-evidence.tar.gz`, SHA `40001597c76669f08cd1162a380b7a444a3a2d550dc00623fdf440d899db7be4` with verified checksum sidecar. The original 62-file first-batch archive and full backups remain separately recoverable; no offsite claim.

**Remaining scientific blockers:** Q23 Endodontics and Q85 Prosthodontics have located phrases but have not passed independent three-point printed-page neighbor corroboration; the exact McCracken RPD 13e chapter map conflicts with the approved current original PDF SHA, so ten older cases stay untouched; additional missing sources are radiology Q192,193,194,198,202,203,204,206,208,209,210 and pediatric Q177, and the other old source rows require separate page-specific original-book evidence. Never infer PDF pages from a fixed printed-page offset, never rewrite human reviews, and never regenerate or apply these fifteen already-completed source changes. The next worker starts by checking GitHub, health, full backup receipts, the exact saved 1401 package hashes, and live database strict count.


## 2026-10-12 — Four additional 1401 radiology sources published to the bank

**Verified production result: 83 → 87 / 249 strict chapter + original PDF page + bound PDF SHA-256.** Before this batch, 1401 pediatric Q177 had already been source-only published and independently read back: official Nowak Pediatric Dentistry 6e, chapter 22, PDF page 376, protected published-source receipt `1401-pediatric177-official-source-published-20261011.json` and readback `1401-pediatric177-final-source-readback-20261011.json` SHA `9ed876d65049f5b48308287bcf5f48c0df50aaec43572139eff22821290daa22`.

The four new source-free radiology questions were matched to exact year-1401 official White & Pharoah Radiology 8e, original server PDF SHA `228ceb46982c3057d8a7d78b1d10759e72ed1a3a385a76a9affc38e45874c25a`. All chapters 5, 6, 19 and 25 are within the official announced year-1401 chapters 1–25, with the pages within the same hash-bound chapter map. Each assignment has independently verified verbatim PDF-page fragments, not a printed-page offset or nearest-edition guess.

| Bank question | Official 8e chapter | Exact original PDF page |
|---:|---:|---:|
| 194 | 19 | 895 |
| 198 | 5 | 256 |
| 203 | 6 | 303 |
| 206 | 25 | 1386 |

Current unreferenced study input was freshly exported on the live host (11 originally source-free 1401 radiology questions), under the protected server research root `bank-sittings/1401/radiology-unsourced-20261012-study.json`, SHA `9552a180e0cf0d621db42bc1dd5eb7b83814e32e3c93f20df224f98a1db2439e`. The separately protected exact four-question source-only research package is `classification/coordinator/W05-audit-stage/` (stage was unclaimed and created for this isolated immutable package). Its four-question original study hash `2e1f1fa3d8abe3ce96e657e6344c312fa0faf6f8dd874f1464e901dfd57a05ed`, decision SHA `5aa62df5d4fbec16739acd356094a27486b272ba8d66d2f47157774316158dd0`, and independently verified validated-source SHA `a3a181d8e4164f1a592d50d432570d7099c9bc0ef790079ca260d5e351e81f31` are pinned in independent audit `classification/reports/1401-radiology-verified4-audit.json`, SHA `31ea4511ea4912beda2f1100c3dbfe00160cf1250094e4f9ed889d50fea3e39b`.

The official `apply_classification.py` accepted **4/4** original-page decisions, and independent `audit_private_study_batches.py` accepted **4/4** unchanged source-only cases. `import_verified_sources.php` with the exact supported `--package-root` completed a **rollback-only preview** (four exact new source rows, no question/choice/answer change). The frozen assessment preflight `audit_published_assessment.php` matched four original question stems/options/answers against the existing complete 249-question published assessment version **6**.

A fresh full production backup `/var/backups/fanoos/20261012T035946Z-27488df0` was independently verified with **114 files**, a complete `database.sql`, and official backup verification before apply. The **deployed**, health-checked source-only importer in server release `cd3b7913640dcb6ccdcb471e637e9e3236b89c4e` atomically inserted only the four new AI source rows at UTC **2026-10-12 04:03:19**. Protected receipt `classification/reports/1401-radiology4-official-original-pages-source-publish-20261012.json`, SHA `4d0cbae89bf780a04cf314414d7de259c29f53da32d2b9e52208fc5f337b232b`, confirms four source rows and **zero** changes to stems, choices or official answers.

Independent production database readback checked for each of the four new exact source IDs: original edition, chapter, PDF-page integer, original PDF hash, AI origin, no human review state, unchanged verbatim stem, unchanged choice arrays and unchanged latest official answer. **All 4/4 pass**; strict 1401 coverage is now **87/249**, with **143 incomplete non-English** questions: **136** old source-linked but without fully bound PDF page/hash, plus **7** still completely source-free radiology questions. Nineteen English questions are citation-exempt and must not receive book sources. The total bank count of source-free 1401 questions is therefore 26 (19 English + 7 radiology), *not* 7. Protected immutable readback `classification/reports/1401-radiology4-exact-source-final-readback-20261012.json`, SHA `dc8a71673d4ee4710a0884ec3ebeb4cfc19baf6625c7451518dfbdc4dc8d064e`.

**Student frozen assessment publication remains intentionally on hold.** The official read-only full 249-question comparison to frozen version **6** detects explanatory citation changes in precisely the four newly sourced questions, but also **139 unrelated historical source-explanation differences** across previously recorded questions. Every other student-visible field value (prompt, choices, answer, tags, topic) matches; the full-exam republish would also incorporate those 139 unrelated differences, an unapproved change. Neither the assessment version nor attempts were modified by this source-only apply. Do not claim the four updated source explanations are visible in the frozen published exam yet. Publication gate `classification/reports/1401-radiology4-assessment-publish-gate-20261012.json`, SHA `24f66165e131e22b70df6c33e4736573817cf0c4e8868e2579f525c0ee235e12`.

The protected W05 package, original research summaries, rollback/audit checkpoint, final source receipt and readback, and assessment publication gate are preserved in `/var/backups/fanoos/research/20261012-residency1401-radiology4-verified-source-publish-evidence.tar.gz`, SHA-256 `d9bb46c0500df6942fdad88d5b96b0a3a17d73bdfa223ee25cbaeb5caa56861c`, with an independent sidecar; all **11 files were extracted and verified byte-for-byte** against their originals. This is an on-server research recovery artifact, not an offsite copy. The public GitHub repository contains no question text or protected PDF content.

**Seven other 1401 radiology questions remain unclassified:** Q192 and Q209 have strongest original evidence in chapters **26** and **28**, outside the official 1401 chapter range; do not misattribute another in-scope chapter. Q193 official final-key answer describes a protective effect of **high** radiation doses, whereas the original 8e Hormesis description (chapter 3, PDF page 119) addresses possible **low-dose** radiation effects. Record the scientific conflict without altering the official answer. Q202 has a *research-only* candidate in official chapter 2, PDF page 98, where the reference discusses mucositis/inflammatory redness and subsequent healing; it has not yet passed a dedicated source-insertion audit. Q204, Q208 and Q210 remain held because searched in-scope passages did not yet prove the precise image/lesion/fracture distinction asked; Q210's in-scope suture passage describes radiographic fracture **simulation**, not definitively a true fracture. No held question was modified. All new classification needs an exact official in-scope edition, chapter, verified PDF page and immutable source-only QA.

**Resume checklist:** check current GitHub `main`, deployed release and health, concurrent workers, fresh full-backup availability, W05 package SHA, private receipt, frozen assessment version and live strict counts before working further. Do not reapply any of the 20 already completed 1401 original-PDF source additions/repairs across these operations (15 existing-source page repairs, pediatric Q177, and four radiology source additions). The remaining 143 non-English questions must be addressed separately; no chapter-only or page-less mapping is permitted.


## 2026-10-12 additional 11 verified original-book page assignments — **production 98 / 249**

**New independent live production count: 98 of 249 residency year 1401 questions have chapter, exact bound original PDF page and original PDF SHA-256.** This operation added **11** to the previously verified 87: **one new radiology source** plus **ten existing AI source PDF-page/hash repairs**. Cumulative improvement since original 67/249 audited baseline is **31 completed pairs**. The 19 English final-key questions are explicitly citation exempt, leaving **132 non-English incomplete**: **126 source-linked but still missing verified PDF page/hash, and six radiology questions still without any source**. Total source-free including 19 English is 25.

### Exact original-server PDF evidence actually applied

| Question | Subject | Exact official reference edition | Existing/assigned chapter | Original PDF page | Action |
|---:|---|---|---:|---:|---|
| 202 | Oral radiology | White & Pharoah 8e | 2 | 99 | Protected original source-only insertion |
| 28 | Endodontics | Torabinejad 6e | 12 | 242 | Existing AI source: page + PDF SHA only |
| 29 | Endodontics | Torabinejad 6e | 15 | 337 | Existing AI source: page + PDF SHA only |
| 33 | Endodontics | Torabinejad 6e | 17 | 382 | Existing AI source: page + PDF SHA only |
| 40 | Endodontics | Torabinejad 6e | 22 | 465 | Existing AI source: page + PDF SHA only |
| 131 | Oral surgery | Hupp 7e | 2 | 40 | Existing AI source: page + PDF SHA only |
| 139 | Oral surgery | Hupp 7e | 21 | 454 | Existing AI source: page + PDF SHA only |
| 143 | Oral surgery | Hupp 7e | 31 | 680 | Existing AI source: page + PDF SHA only |
| 149 | Oral surgery | Malamed 7e | 14 | 267 | Existing AI source: page + PDF SHA only |
| 86 | Prosthodontics | Zarb 13e | 9 | 210 | Existing AI source: page + PDF SHA only |
| 88 | Prosthodontics | Zarb 13e | 5 | 82 | Existing AI source: page + PDF SHA only |

**Official PDF identities:** White-Pharoah8e `228ceb46982c3057d8a7d78b1d10759e72ed1a3a385a76a9affc38e45874c25a`; Torabinejad6e `09333f079cb3300550cc0702985aae216d952eacabdaac005e1c66fafebe13e0`; Hupp7e `35e3c37fec67a831a4464205c1817cc1e51c0a9f8a0bd8660a1881ecd2f10a83`; Malamed7e `cc668f86a731bed4c016feb1a3e20be033637c7d0a30da86358dbc9b2a4c08e7`; Zarb13e `bcf92916cd0a01915695d1cb5aad8c51e7f2dac5909477c4df4454d229156a3d`. These are the exact verified original PDF sources with same-SHA official-year scope chapter maps; no fixed printed-page offset was used as a substitution for original page checks.

#### Source-only radiology question 202

Original currently unsourced case copied to protected `/srv/fanoos/shared/research/classification/coordinator/W06-audit-stage/` **without editing any pre-existing W06 file**; study input `bank-sittings/1401/radiology202-study.json` SHA `39e62a47b6b5e0bb163aa09a915fe226266f6d6ef14675470c83554696f401ba`; private decision `classification/decisions/1401-oral-radiology.json` SHA `429e7c26f2cc4bd243d6aa3ac930fc748f385426dd20a13f599b1755d720aca1`. Original 8e, chapter 2, PDF page **99**, explicitly differentiates rapid mucosal healing following radiotherapy from later underlying connective-tissue fibrosis. `apply_classification.py --pdf-pages` accepted 1/1; independent `audit_private_study_batches.py` accepted 1/1, audit SHA `3331c5c80e77d22fbf735394475cf0f34264b69f6341f7a9abf884ffaa85a835`. Both original clinical content and the current 249-question published v6 preflight matched. Rollback preview of `import_verified_sources.php` passed, zero question/choice/answer updates. After verifying fresh full backup `/var/backups/fanoos/20261012T041814Z-861b0f30` (114 files), official deployed source-only importer inserted **exactly one** source UTC 04:19:54Z; private receipt `classification/reports/1401-radiology202-official-source-apply-20261012.json`, SHA `b416b1484e6319f555d7e742bb0ab4eb81605d5a8371e7c18f3093cbf3e812dd`. Independent live DB comparison of original stem, options, latest official answer, source edition, chapter, exact PDF page/hash passed; `classification/reports/1401-radiology202-final-readback-20261012.json`, SHA `9fd5d43b0e8748b3b10f79fd8dba4dab8ac9a3a8de1500763f3253a8f5d45b88`. Strict coverage **87 → 88**.

#### Ten source metadata-only repairs

All ten source records were already published AI-origin sources with exact official-year edition and existing chapter. No human-reviewed source was touched. Independent reading of the original page confirmed short literal evidence for the exact clinical questions; the printed book page printed number was also present on the target original PDF page **and on at least two correct adjacent pages**. Existing anchor SHA, exact original source ID, unchanged historic printed/legacy page, no review state and source confidence at least 0.85 were verified at planning, in every database rollback-only preview and again during atomic apply. Each operation was strictly limited to `bank_question_sources.pdf_page` and `bank_question_sources.pdf_sha256`.

- **Endodontics 4**, approved plan `classification/reports/1401-endo4-repaired-original-pdf-page-plan-20261012.json` SHA `e68857a828821d1229ed850bc32689fcfc4d0c798f7264f9dc0709875edd7ff1`; pre-apply original question/source/options/latest-answer snapshot `1401-endo4-original-immutable-bank-snapshot-20261012.json` SHA `5b977e9a3830096cc672b0bebaf88b13b90fbecdb815aab4b8dd21a5186f7b8f`. Four-question rollback-preview passed, zero unrelated changes. Fresh pre-batch independently verified **114-file** backup `/var/backups/fanoos/20261012T042639Z-a393f330`. Deployed `repair_verified_source_pages.php` applied four original PDF page+hash pairs UTC 04:28:04Z; atomic receipt `1401-endo4-original-pages-repaired-20261012.json` SHA `594e432020f7c9a8a38cf6f481923f95cc4008ee1cbe95fcb5ecfef1c85bd159`. Independent readback of all historic and immutable fields, question choices, and latest official keys: **4/4 pass**; `1401-endo4-applied-final-independent-readback-20261012.json` SHA `85ab3a60b1e519bfbfd582c634014cccefe3fb167c9598de7675e867001a9842`. Coverage 88 → 92.
- **Oral surgery 4**, plan `1401-surgery4-verified-original-page-plan-20261012.json` SHA `189fb63fba0ec2ff4bacec0fd2913347ba7bcb59c5152b86ac2edbf58c1207d6`, successful four-row rollback-preview. Private frozen question/option/answer original snapshot for surgery and prosthodontics `1401-surgery4-prostho2-original-content-snapshot-20261012.json` SHA `e05eb236c9d7e4922194712eec47f6552e8cdede056b9abdebf65fa074dde069`. Independently verified full backup `/var/backups/fanoos/20261012T043032Z-9e2f4f45` (114 files), atomic apply UTC 04:31:33Z, private receipt `1401-surgery4-original-pages-repaired-20261012.json` SHA `01f02e146f43f1b101fefd856783ddad3e41bac58ad3fe00685c46692b42e0d7`. Independent immutable original database readback **4/4 pass**, protected `1401-surgery4-complete-source-final-readback-20261012.json` SHA `fb8224620e7060ba595081cefda5de1d9a642b39eaf1a6bf39b13933f458b021`. Coverage 92 → 96.
- **Prosthodontics 2**, plan `1401-prostho2-verified-original-page-plan-20261012.json` SHA `cf1434494496502e545ff693422c34865863d5d87e60637eca6ab909c6a5647f`, successful two-row rollback-preview. Independently verified new post-surgery backup `/var/backups/fanoos/20261012T043451Z-0b3031f5` (114 files), atomic apply UTC 04:35:21Z, receipt `1401-prostho2-original-pages-repaired-20261012.json` SHA `a2a5bf1d448a09005fc109a81a1bd6fb46d25766ca518f6cd1bc4535f6d6437e`. Independent immutable live DB readback **2/2 pass**, protected `1401-prostho2-source-only-final-immutable-readback-20261012.json` SHA `e0cc24a77455c39691be1628599a22f1a7e5d73ee25c3c0fd5fbf64c2ac4b25c`. Coverage 96 → **98**.

**No hidden assessment republish.** The site passed health checks running deployed release `cd3b7913640dcb6ccdcb471e637e9e3236b89c4e`. Full read-only 249-question BankPublisher/frozen exam comparison shows published version **6** remains unchanged, including answers, stems and options. The newly sourced Q202 would change its explanatory source text; the ten existing-source page fixes add **zero** further changed explanations; **133 unrelated historic source-explanation differences** would also be incorporated by a broad whole-exam publish. Therefore **do not republish the whole frozen exam** without a separately reviewed source-only publication plan; assessment version and attempts were not altered. Private gate `classification/reports/1401-additional11-frozen-exam-publish-gate-20261012.json` SHA `08db780a3959e99c8e17751a8d2cc2883760700a28dffdde66f0d8854837c9cc`.

**Scientific holds remain independent, not counted:** surgery Q133 has official final-key `4–6 mm` but Hupp 7e chapter 9, original PDF page 150 states `6–8 mm`; no source repair or answer change. Radiology Q193 official high-dose Hormesis answer disagrees with White-Pharoah8e chapter 3 original page 119 discussion of possible protective effects at low doses; preserve the final key. Radiology Q192 and Q209 strongest direct original-page evidence lies in chapters 26 and 28, outside the announced 1401 radiology scope chapters 1–25. Q204, Q208 and Q210 need specific in-scope direct evidence and are held. Private research conflict receipt `classification/reports/1401-scientific-official-answer-and-scope-holds-20261012.json` SHA `ff7e06bc87d1b9bda7587f399017e32a1b0ac4cdb5dd773cedbe46d419eace7a`. Additional printed-label or scientific mismatch holds include Q23/Q38 endodontics and Q85/Q90 prosthodontics; no fixed-page-offset shortcuts. Legacy Neville 4e source anchors mentioning a proxy 5e cannot be treated as evidence from the exact approved 4e. Correct source evidence under the exact same-year edition first; never silently carry forward an unsupported page or modify human review or official keys.

**Durable protected recovery:** all 11 additions/repairs' original studies, exact plans, independent audits, immutable source/assessment readbacks and atomic apply receipts plus scientific hold record were saved inside the private research directory and in separately re-opened byte-by-byte verified **19-file** on-server archive `/var/backups/fanoos/research/20261012-residency1401-additional-11-verified-source-page-repairs.tar.gz`, SHA `fa913e63f61611bc52fa357586bf9372d4c75ef406ec9f64fea2a4903dba0db5` with checksum sidecar. The later assessment publication gate is separately stored in private research. No private question text or full PDF material is published to GitHub. Keep the existing official full backups as described above, and follow managed backup retention only.

**Next work:** Re-query live 1401 strict count **98/249**, 19 exempt final-key English, **132 clinical non-English pending** (126 existing source links without exact bound PDF page/hash and six unsourced radiology); protect all existing scientific/human/source records and resume only from original year-appropriate verified PDFs. Do not reprocess any of these 11 already-applied source IDs, and do not treat a printed-page citation alone as a completed chapter/page assignment.
