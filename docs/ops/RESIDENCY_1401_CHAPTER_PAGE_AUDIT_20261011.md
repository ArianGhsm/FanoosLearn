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
