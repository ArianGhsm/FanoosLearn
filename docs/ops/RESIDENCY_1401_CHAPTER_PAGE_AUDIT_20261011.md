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
