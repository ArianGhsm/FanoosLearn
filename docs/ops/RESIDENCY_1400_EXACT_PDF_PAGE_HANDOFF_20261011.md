# Residency 1400 exact chapter + verified PDF page — continuation checkpoint

**Date:** 2026-10-11 (UTC). **Status:** read-only production audit completed; no new classification imported or published. This handoff covers **residency / year 1400 / round 1 only**, and does not authorize bulk rewriting of historical records.

## Authoritative rules and runtime

- Policy: [PROJECT_PRINCIPLES](../PROJECT_PRINCIPLES.md) and [09_CHAPTER_CLASSIFICATION](../product/09_CHAPTER_CLASSIFICATION.md). Count a book-based classification only when the **same** `bank_question_sources` record holds `node_id`, a positive exact `pdf_page`, and `pdf_sha256`. Independently verify actual page evidence, hash-bound chapter map, edition and year-specific syllabus scope before describing a recorded row as scientifically verified. Bare chapters, numeric printed pages, and old `page` values do not qualify.
- Authorized server: IranServer FANOOS, reached using SentinelX; current release read back as `15e823ae7a04775e45646c1315ddcfa3cc11fd52` and public `https://fanooslearn.ir/health` reported `status=ok` and that exact release at 2026-10-11T13:20:05Z. Updater checkout was clean on `main` at this commit, **behind GitHub origin/main by one commit**; do not call the server fully synchronized. Available filesystem capacity 7.1 GiB (88% occupied).
- Read-only production SQL ran via the existing `DatabaseConnection::fromEnvironment()` with authorized updater configuration; no connection strings, credentials, question text, answer text, or reference-book text were logged in this public document.
- The 2026-10-11 site-wide backlog at `BANK_CLASSIFICATION_PAGE_BACKLOG_20261011.md` recorded 1400 as 56/250; fresh live query independently reconfirmed these numbers.

## Fresh production counts (1400-1)

| Subject | Total questions | Any source/chapter | Same-row chapter+PDF page+SHA | Still incomplete |
|---|---:|---:|---:|---:|
| community-dentistry | 10 | 10 | 10 | 0 |
| dental-materials | 10 | 10 | 0 | 10 |
| endodontics | 20 | 20 | 0 | 20 |
| english | 20 | 0 | 0 | 20 (official-key-only exemption; **no book citation**) |
| operative-dentistry | 20 | 19 | 14 | 6 |
| oral-medicine | 20 | 20 | 0 | 20 |
| oral-pathology | 20 | 20 | 0 | 20 |
| oral-radiology | 20 | 0 | 0 | 20 |
| oral-surgery | 20 | 20 | 0 | 20 |
| orthodontics | 20 | 20 | 20 | 0 |
| pediatric-dentistry | 20 | 19 | 0 | 20 |
| periodontics | 20 | 20 | 0 | 20 |
| prosthodontics | 30 | 30 | 12 | 18 |
| **Total** | **250** | **208** | **56** | **194** |

Breakdown of the **194** under the new definition:
- **152** have an existing chapter/source record, but no same-row verified bound PDF page;
- **22** are non-English and have no source record (20 radiology, one operative, one pediatric);
- **20** are English with the separately established final/amended official key exemption, never to be given fabricated book references.

The 56 is a **field-presence count**, not a new 56-page independent scientific re-audit; original evidence, chapter maps and partial-page official scope must still be revisited wherever challenged.

## Why historic citations need individual repair

All 152 existing but incomplete source records have `node_id` but no complete bound `pdf_page` + `pdf_sha256`. The read-only live metadata audit found:

- **85** printed/numeric legacy page labels: dental materials 10, endodontics 20, operative 5, oral pathology 20, oral surgery 20, prosthodontics 18 — **actually 93**; recalculate from the subject table rather than using a guessed aggregate.
- More source rows have blank legacy pages: oral medicine 20, pediatric 19, periodontics 20. The live SQL confirmed legacy pages are **not** `pdf N` labels. Printed page numbers cannot be mechanically assigned as PDF-page numbers.
- The previous `backfill_source_pages.php` can parse printed fields, but this does not establish actual PDF pages. Do **not** treat `--bind-pdf` as scientific page validation or run a bulk data operation under the new rule.

Correction to the numeric summary above: printed/numeric incomplete records total **93** (10+20+5+20+20+18); blank legacy-page records total **59** (20+19+20), together **152**. This explicit reconciliation supersedes the provisional number on the previous bullet.

All year-1400 official `catalog.json` validity entries were inspected by subject (White & Pharoah 8e for radiology; multiple exact texts for restorative/materials/prosthodontics); subject-specific scope and partial-page exclusions are mandatory. No other year's edition or approximate map may be substituted.

## Unpublished radiology candidates: W01

The existing private W01 research package contains **14 primary-validated / independently audited candidates out of 20** year-1400 radiology questions. The decision numbers are **191, 192, 193, 194, 196, 198, 200, 201, 202, 203, 204, 205, 206, 207**, citing **White & Pharoah 8e**, with both proposed chapters and page evidence. The other six remain pending.

- Private protected provenance: `/srv/fanoos/shared/research/classification/reports/W01-1399-1400-provenance-audit-v2.json`; prior archived audit hash `1a573f25e64a52cbc67d6f908055d6257f74b06957c0dd155fcb210e60d35c24`.
- Prepublication status: `/srv/fanoos/shared/research/classification/coordinator/W01-PREPUBLICATION-CHECKPOINT-20261010.md`; preview only, **14** proposed source rows, **0** changes to questions/options/answers. Older planned production apply was blocked before execution; no 1400 W01 source receipt was found in the read-only 2026-10-11 inspection.
- Current live radiology source count **zero**. Consequently these are **not deployed**, not part of the 56 recorded-complete questions, and must not be announced as completed.
- A subsequent remote script inspection request on 2026-10-11 was blocked by the tool's safety gate. **Do not circumvent that gate** using alternate execution methods or claim independent revalidation or production import. Resume only through an explicitly authorized, unobstructed workflow.

## Precise next execution gates

1. At resumption, re-read live GitHub `main`, release SHA, CI, updater and DB counts; reconcile concurrent year-specific work and receipts. Check approved publisher lock and access.
2. Re-open official **1400** validity and original exact-edition private verified PDFs. For W01 first, revalidate each of the 14 chapter/page/quote/sha combinations directly from the approved PDF through the verified one-page reader; verify chapter boundaries and partial-page scope, subject identity and official answer, then independently audit the frozen original study snapshots. Hold any disagreement; do not infer evidence.
3. Individually investigate the **152** legacy chapter records against corresponding exact original PDF and old evidence; recover *actual* PDF page for each printable/blank label and perform a human-review protection check. Existing sources and assessments stay unchanged until backed-up, authorized source-metadata repairs.
4. Individually research the **22** clinical questions with no source, beginning with six pending W01 radiology questions and the two other subject gaps; proceed with other eligible cases rather than blocking on missing PDFs.
5. For each fully vetted batch use the canonical source-only preflight and verify `questions_changed=0`, `choices_changed=0`, `answers_changed=0` **and** no unintended changes in human decisions, frozen content or historical attempts; secure a new verified full backup, unique receipt and exclusive publisher authorization. Only then apply approved source-only rows, independently read them back and perform full frozen-exam comparison and reviewed publication. Never repeat a previously published batch or treat a preview as an apply.
6. GitHub code/doc changes: PR → green relevant CI → review → merge → updater deployment → exact SHA/health/sync read-back. DB application, exam publication and deploy are **distinct** stages; none is completed merely because another succeeds.

**Checkpoint outcome:** 0 new sources applied; 0 historical sources repaired; 0 exams republished; no answer/question/choice modification. The durable value of this pass is a fresh, independently read-back year-1400 source/page backlog and non-overwriting next-step queue.
