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

- **93** printed/numeric legacy page labels: dental materials 10, endodontics 20, operative 5, oral pathology 20, oral surgery 20, prosthodontics 18.
- More source rows have blank legacy pages: oral medicine 20, pediatric 19, periodontics 20. The live SQL confirmed legacy pages are **not** `pdf N` labels. Printed page numbers cannot be mechanically assigned as PDF-page numbers.
- The previous `backfill_source_pages.php` can parse printed fields, but this does not establish actual PDF pages. Do **not** treat `--bind-pdf` as scientific page validation or run a bulk data operation under the new rule.

Together the **93** printed/numeric and **59** blank legacy-page records account for all **152** chaptered-but-incomplete rows.

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


## Additional original-PDF W01 audit, 2026-10-11

**Completed and read back, research only:** the previously prepared **14 radiology decisions** were rechecked against the *current approved original* White & Pharoah 8e PDF, SHA-256 `228ceb46982c3057d8a7d78b1d10759e72ed1a3a385a76a9affc38e45874c25a`, **1,958 PDF pages**. Its SHA-bound chapter map covers 33 chapters and the entire PDF without a gap. For every one of Q191, Q192, Q193, Q194, Q196, Q198, Q200–Q207, the original-page quotation is present, the requested PDF page is inside its mapped chapter and the selected chapter is within **1400's official radiology scope of chapters 1–25**. The earlier independent research audit provenance and the immutable decisions SHA were also checked against the prior 14-accepted batch. No new publication or DB write occurred.

**Protected durable on-server receipt (independently read back):** `/srv/fanoos/shared/research/classification/reports/residency-1400-W01-direct-pdf-revalidation-20261011.json`, SHA-256 `09461fb324d0531decddf90aeb3c6488c39bb7e98527af61a67e82241d48e001`, 14 entries, no copyrighted page text in the receipt. The accepted question/page list remains exactly the 14 entries in the table already documented by the earlier W01 handoff, with their original decisions file SHA `95721de828f628221ba7411d7b79ec0f01e915104a7928bbff02b24f8f8d8bd6`. These are **research-validated**, not recorded-complete under the live DB predicate.

**Focused original-PDF screening of the other six radiology questions:**

| Question | Direct original-book finding | Disposition |
|---|---|---|
| 195 | Digital imaging, chapter 4, pages 173/181 contain printing/DPI context but no supported resolution of the specific original-choice discrepancy | Pending |
| 197 | Intraoral imaging, chapter 7, candidate pages 321/377 contain bisecting-angle discussion, not sufficient original evidence for the mental-foramen projection answer | Pending |
| **199** | Panoramic imaging, **chapter 9, PDF page 457**, explicit original description of the condyle moving anteroinferior relative to the closed position, consistent with recorded key | **Primary validator accepted** in a 4-decision scratch batch; not yet secondary-audited or durably saved as a new decision |
| **208** | Sinus mucosal polyps, candidate **chapter 28, PDF 1556–1557**; direct original text supports the question, but chapter **28 is OUTSIDE** official 1400 scope (chapters 1–25) | **Hold, no classification** |
| **209** | Fracture nonunion, candidate **chapter 27, PDF 1529**; direct original text supports the question, but chapter **27 is OUTSIDE** 1400's official scope | **Hold, no classification** |
| **210** | Gingival SCC vs periodontal inflammation, candidate **chapter 26, PDF 1408**; direct original text supports the question, but chapter **26 is OUTSIDE** 1400's official scope | **Hold, no classification** |

The official 1400 radiology validity entry is `white-pharoah-radiology@8e` with explicit `scope_chapters` **1–25**, source-document `1400_منابع دستیاری 1400.pdf`. The three out-of-scope cases **must not** be imported, even though the original text is found; investigate the official exam reference list or question attribution separately without changing keys or old source rows. The first attempted new scratch validator correctly reported **1 accepted (Q199), 3 rejected (Q208–210)**; because it required all four to succeed, the batch had a nonzero exit code and its temporary output was automatically cleaned. The subsequent independent one-question validation and write attempt was **blocked by tool safety checks**; no private followup decision file, validation receipt, production DB import, frozen assessment version, or release change was claimed. Do not bypass that safety gate. A separately authorized resumption must perform the independent review and approved protected write before counting Q199 as completed.

**Production numbers remain 56/250 same-row chapter+PDF page+SHA, with 174 non-English book-based incomplete cases and 20 official-key-only English exceptions.** Scientific revalidation of earlier research is not publication. This doc update alone makes no production data, answer, or attempt changes.


## Exact-PDF research expansion, 2026-10-11 — not yet applied to production

**Live baseline rechecked in this continuation:** residency/1400/round1: **250 total, 56 with same-row chapter + PDF page + SHA**; **174 non-English clinical cases remain incomplete**, plus 20 English official-answer-only exceptions. Running release symlink was `cd3b7913640dcb6ccdcb471e637e9e3236b89c4e`, while the updater checkout showed `a4b447d14d7e490ca9a4309aeec7b84e8a659a86`; a full release/CI/synchronization assertion was *not* established. No live source rows, keys, answers, human decisions, frozen assessments, attempts or site release were changed.

### Authoritative W01 radiology source-only research package now rebuilt

The canonical current `apply_classification.py --pdf-pages` independently accepted **15/20**, **0 rejected**, retaining the original 14 untouched and adding **Q199 White & Pharoah 8e, chapter 9, PDF page 457**. The secondary `audit_private_study_batches.py` confirmed **15 accepted / 5 pending**, PDF source hash `228ceb46982c3057d8a7d78b1d10759e72ed1a3a385a76a9affc38e45874c25a`, and **all original stems/options/answers identical**. A new protected immutable coordinator research stage was created and read back:

- `/srv/fanoos/shared/research/classification/coordinator/W01-audit-stage/` (private, no PDF/page-text export)
- `classification/reports/W01-1400-15-original-pdf-audit-20261011.json`: SHA-256 `6c62e4baeae703632c1aa77159993dac7abc79fefdb061be25bd4bef169c39ef`
- Exact 15 decision-file SHA-256: `284d83c9fb352d73241ef0051efd5ca121adfdb614a4dbde8e7ad0f791d7dcc1`
- Rebuilt validated-study SHA-256: `985c79d1617022bba5b8cf1e5aa818d636c234ee1ed73fc05fee58866462e9c0`

An *older* source-only dry-run using the 14-question old-format validated file failed because that validated file omitted `pdf_page` and `pdf_sha256`. The new 15-question stage correctly includes these fields and passed both offline validators. **An attempt to run the source-only dry-run on this new stage was blocked by the tool's safety checks. Do not circumvent or mark the 15 as inserted.** Before authorized resumption, run the canonical source-only preflight against the protected 15-question stage with exact live question matching, then fresh full verified backup + publisher lock + approved apply + unique receipt + exam/answer/frozen-state audit. The remaining W01 Q195/Q197 and out-of-official-scope Q208/Q209/Q210 stay on hold.

### Legacy source rows: PDF-page evidence recovered, subject to safe metadata-only repair

The following **13 existing source rows** were independently checked against their exact original approved PDF (PDF SHA matches contiguous chapter map), actual same-page original evidence, official 1400 scope, *and printed-page matching on adjacent original PDF pages*. Legacy `printed_page` is **not** the `pdf_page`:

| Subject | Question | Edition | Existing chapter | Existing printed page | Verified original PDF page |
|---|---:|---|---|---:|---:|
| Dental materials | 213 | Powers & Wataha 11e | 8 | 110 | 128 |
| Dental materials | 215 | Powers & Wataha 11e | 7 | 92 | 110 |
| Dental materials | 219 | Van Noort 4e | 3.7 | 212 | 227 |
| Dental materials | 220 | Van Noort 4e | 3.6 | 206 | 221 |
| Endodontics | 24 | Torabinejad 6e | 4 | 66 | 73 |
| Endodontics | 26 | Torabinejad 6e | 6 | 102 | 109 |
| Endodontics | 29 | Torabinejad 6e | 9 | 163 | 170 |
| Endodontics | 30 | Torabinejad 6e | 10 | 190 | 197 |
| Endodontics | 33 | Torabinejad 6e | 12 | 236 | 243 |
| Endodontics | 34 | Torabinejad 6e | 13 | 294 | 301 |
| Endodontics | 35 | Torabinejad 6e | 14 | 313 | 320 |
| Endodontics | 39 | Torabinejad 6e | 20 | 439 | 446 |
| Endodontics | 40 | Torabinejad 6e | 15 | 331 | 338 |

And **six** additional existing oral-surgery source rows passed the same exact-PDF, chapter, scope, quotation and independent neighboring printed-page checks:

| Q | Edition | Chapter | Printed | PDF page |
|---:|---|---:|---:|---:|
| 134 | Malamed Local Anesthesia 7e | 18 | 345 | 362 |
| 136 | Hupp Oral Surgery 7e | 3 | 43 | 57 |
| 137 | Hupp 7e | 5 | 61 | 75 |
| 143 | Hupp 7e | 26 | 563 | 577 |
| 146 | Hupp 7e | 31 | 658 | 672 |
| 147 | Hupp 7e | 10 | 174 | 188 |

**Three** existing Zarb Edentulous 13e prosthodontics source rows independently passed the same checks: **Q83 ch12 printed272 PDF286; Q86 ch1 printed17 PDF31; Q90 ch7 printed152 PDF166**. Q88 ch8 PDF179 has original-PDF quote evidence but printed-page corroboration is incomplete; hold for independent source review. The McCracken RPD 13e chapter map is **not bound to the current approved original PDF SHA**; no McCracken PDF page can count as verified until that map is rebuilt and audited.

**Neville oral pathology:** all **20** historical citations were imported originally with `[neville-oral-pathology@5e ... nearest edition]` in their anchors, although **Neville 4e is the official year-1400 edition**. Re-reading the **actual Neville 4e** rather than mechanically carrying old 5e printed numbers found an exact phrase from the old anchored evidence on the corresponding official 4e chapter page for **Q62 ch2 PDF98, Q64 ch5 PDF192, Q71 ch12 PDF501, Q73 ch13 PDF561, Q74 ch14 PDF601, Q77 ch15 PDF657**. These **six are research candidates only**, pending full question/answer semantic review and source repair; the other 14 cannot be claimed from an unmatched 5e quote. The original verified Neville 4e PDF SHA was `6fbc9bcba9003deda2f8fc006ccc4f23f9e15db0bcd0e237c56ddf6788578bb6`.

**Scope conflicts:** six materials legacy Van Noort records **Q211/212/214/216/217/218** are presently mapped to chapters outside year-1400 official scope (only sections **3.4–3.7** eligible), even when old book text was found; they must not be completed without a new official in-scope exact-page citation. For oral medicine, pediatric dentistry and periodontics, historic blank-page anchors point to **Burket 13e / Little Falace 10e / McDonald-Avery 11e / Carranza 14e**, respectively, rather than the official **Burket 12e / Little Falace 9e / Nowak 6e / Carranza 13e**. These historical chapters must be independently revalidated, not merely relabeled with the official edition. The unsourced non-radiology question numbers are **Q170 operative** and **Q174 pediatric**; Q174 already has a private Nowak 6e ch15 PDF264 research decision pending approved source-only publication. A subsequent full Burket12e transient one-page scan timed out, so no results from that incomplete job are counted.

**Production completion is still 56/250.** The numbers above are overlapping categories of **research-validated candidate metadata**, not new live completion totals. The source-only publisher only inserts into genuinely source-free questions; it does **not** repair pre-existing chapter-only sources. No raw SQL shortcut, wholesale source replacement or fake printed-to-PDF-page conversion is authorized. Require a separately reviewed metadata-only repair implementation with snapshot/backups and immutable-published-exam checks. Any safety-gate block must remain a blocker for that operation, not an excuse for using a weaker write route.


## New independently re-read private source-row evidence, 2026-10-11 later checkpoint

**Read-only database baseline:** production residency 1400/round1 still **250 questions, 56 chapter + `pdf_page` + `pdf_sha256` on the same row**. Production release read back `cd3b7913640dcb6ccdcb471e637e9e3236b89c4e`; updater checkout at `77c77372253f734f1d820c7cdbbd5b0605b6eff4` at inspection, not proven synchronized. Preserve 20 exempt official-answer-only English questions separately.

**New immutable private audit receipt successfully created and read back:**

- Path: `/srv/fanoos/shared/research/classification/reports/residency-1400-22-legacy-exact-original-pdf-page-repair-evidence-20261011.json`
- Exact SHA-256: `1af62232e6f8a213aeecca22254d0e093653e6448e73a7b833a39ae18d27395d`
- **22 existing source rows** (4 materials, 9 endodontics, 6 oral surgery, 3 prosthodontics), with each actual **live source-row ID**, edition, existing chapter, original printed page, verified PDF page, *original approved PDF SHA-256*, original unchanged anchor hash, original source origin, official-1400 syllabus validity, chapter-map coverage and independent printed-page neighbor evidence. It ensures pre-existing `pdf_page` and `pdf_sha256` fields are NULL at research time, with no source updates. Individual numbers and pages are tabulated in the prior section. This is durable **repair input and scientific verification, not a successful source-row patch or bank publication**; a separate approved metadata-only repair, current live row recheck, exclusive publisher lock and fresh verified backup are mandatory.

**Eight additional endodontics PDF-page candidates** with *partial* original-anchor overlap and printed-page corroboration: Q21 ch11 PDF219 (printed212), Q23 ch3 PDF49 (printed42), Q27 ch7 PDF131 (printed124), Q28 ch8 PDF161 (printed154), Q32 ch12 PDF237 (printed230), Q36 ch15 PDF339 (printed332), Q37 ch18 PDF403 (printed396), Q38 ch19 PDF416 (printed409). Each had a meaningful overlap of contiguous five-word original evidence phrases, and matching printed labels on the candidate PDF page and neighboring pages, inside the official Torabinejad 6e chapter. **None matched the entire original legacy anchor verbatim. Therefore these eight remain pending independent question/answer semantic review; they MUST NOT be counted among the 22 fully verified records or published automatically.** Q22 and Q25 lacked independent printed-page corroboration despite partial phrase matches; Q31 had no meaningful phrase match. The eight partial findings were durably written and read back at `/srv/fanoos/shared/research/classification/reports/residency-1400-endo-8-partial-anchor-page-candidates-20261011.json`, SHA-256 `512e797e0a412539a618b3ba12062e8cb3331def532df82095a6f5400a065fec`. Full original-anchor match count: **0**.

**Pediatric Q174:** compared old one-question W05 research with actual production year-1400 stem, all four options and final correct choice 4 — **identical**, and live question was confirmed source-free. W05's older output only carries a display page and no `pdf_page` or `pdf_sha256`, so it is not yet canonical. A guarded attempt to rebuild its one-question private exact-PDF study/decision/audit stage was blocked by safety checks; **no new W05 stage was created**. Keep its researched Nowak 6e chapter15 PDF264 separate until an unobstructed canonical validator run and authorized publisher preflight. The earlier W01 15-question private validated stage remains research-only; its production source-only dry run was also blocked and must not be bypassed.

**Operations:** No DB source-row repair, new clinical source insertion, human-review override, answer/key modification, historical attempt modification, assessment republish or release deployment was performed as part of this checkpoint. No 1400 year-completion claim is justified.


## 2026-10-12 follow-up: direct-PDF semantic evidence and corrected page candidates

**Latest successful live read:** residency 1400/round1 stays at **250 total, 56 same-row chapter + PDF page + PDF SHA**, with release symlink `cd3b7913640dcb6ccdcb471e637e9e3236b89c4e`. Source publication and assessment updates were **not** performed. The following findings are **research only**; the references and exact numbered pages were read afresh from the approved private original PDFs on the live server.

### Hupp Oral Surgery 7e: three more old-source questions semantically reviewed

For all three, the original PDF SHA `35e3c37fec67a831a4464205c1817cc1e51c0a9f8a0bd8660a1881ecd2f10a83`, chapter-run map, official 1400 scope, and adjacent actual printed page labels were independently confirmed. The original question's recorded answer was reconciled to the direct-page clinical fact.

| Question | Chapter | Actual PDF page | Actual printed page | Short factual basis |
|---:|---:|---:|---:|---|
| 135 | 2 | 43 | 29 | Pulmonary aspiration: tachycardia, hypotension and stridor; the asked exception is bradycardia (answer 2) |
| 148 | 7 | 104 | 90 | Silk/filament sutures and capillary wicking (answer 3) |
| 149 | 28 | 633 | 619 | Wardill/W-Y push-back procedure and increased palatal length (answer 3) |

An independent private, read-back evidence receipt was written with source-row IDs, original-book SHA, exact excerpts, answer-key position and validations:

`/srv/fanoos/shared/research/classification/reports/residency-1400-hupp-q135-q148-q149-exact-page-semantic-review-20261012.json`

SHA-256 `9d0f9f3d93a94691579af88241e37523f1af9b85030e7bb09e227528f5b5f404`.

These **three are part of the 12 weak-anchor surgery candidates previously reported**, not 3 entirely new questions. The other separately semantically reviewed Hupp surgery Q139 ch16 PDF332, Q141 ch21 PDF446 and Q150 ch14 PDF272 were recorded in private receipt `residency-1400-oral-surgery-3-exact-page-semantic-review-20261012.json`, SHA `9436983806a318c1c3cd807fd61229439b44beaa3701152fc7ae04dc58d247dd`. Thus **six** of the 12 partial-anchor surgery questions now have specific same-page answer-relevant original PDF evidence, subject to protected metadata repair.

### Corrected exact answer-evidence pages for two earlier candidates

- **Q132, Malamed Local Anesthesia 7e, chapter 13, original PDF page 227, actual printed page 210.** Needle orientation is upward/inward/**backward**, making the question's forward-direction statement the keyed exception (original live key position 1 in previous read). Direct exact-page phrase fragments, chapter map, SHA and both neighboring printed labels were verified. This corrects the *previous research candidate* PDF page **226 / printed 209**. Do not apply both or use the older page as final evidence.
- **Q140, Hupp Oral Surgery 7e, chapter 20, original PDF page 433, actual printed page 419.** In the text's delayed oroantral fistula closure discussion, acute/chronic sinus infection must first be eliminated, supporting the previously verified official answer position 1. The specific page label and both neighbors, official scope and SHA-bound mapped chapter were revalidated. This corrects the *previous research candidate* PDF page **432 / printed 418**.

**Q131** Malamed 7e chapter 1 original PDF page **41 / printed 24** also has direct-page original evidence contrasting etidocaine/procaine lipid solubility and discussing protein binding, consistent with the previous live key position 2; hash-bound official scope and printed neighbors confirmed. Keep it a *research-only semantic candidate*: live source/official-key re-query and reviewed metadata publication did not run in this continuation.

A new Malamed research receipt creation and a later separate targeted SQL read were blocked by tool safety checks; do not claim those steps succeeded or circumvent the block. Q132 and Q140 corrections exist in these durable, reviewable operations notes but are **not recorded in production**. The original question, options, keys, human decisions, source provenance and historical exam attempts were not modified. A reviewed and fresh-backed-up source-metadata-only repair tool, protected publisher lock and post-write readbacks remain mandatory before any new completion count.
