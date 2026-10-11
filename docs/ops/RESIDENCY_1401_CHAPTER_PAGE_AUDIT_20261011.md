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
