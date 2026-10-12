# Residency 1404 exact chapter + PDF-page audit — 2026-10-11

## 2026-10-12 production checkpoint: eight more bound original PDF pages, now 110/250

**Verified live production readback (1404 residency, first sitting, 2026-10-12):** 250 total, 230 clinical, 20 English reference-exempt. A source row with official chapter AND physical 1-indexed original PDF page AND exact SHA grew from **102 to 110**; **120 clinical** still missing verified page. Eight new bound source rows below are backed by five separate successful protected official `repair_verified_source_pages.php` application receipts and two independently verified full production backups (each 114 manifest files). Only `bank_question_sources.pdf_page` and `bank_question_sources.pdf_sha256` changed, no question/answer, chapter node, printed label, human source or student history mutation.

| Q | Subject | Original approved edition | Official chapter | Printed page | Original PDF page |
| ---: | --- | --- | ---: | ---: | ---: |
| 22 | Endodontics | Torabinejad 6e | 17 | 370 | **377** |
| 23 | Endodontics | Torabinejad 6e | 14 | 302 | **309** |
| 39 | Endodontics | Torabinejad 6e | 18 | 392 | **399** |
| 76 | Oral medicine | Little & Falace 10e | 16 | 251 | **287** |
| 78 | Oral medicine | Little & Falace 10e | 17 | 275 | **313** |
| 134 | Oral surgery | Hupp 7e | 11 | 192 | **206** |
| 145 | Oral surgery | Hupp 7e | 30 | 645 | **659** |
| 213 | Dental materials | Craig 14e | 11 | 211 | **222** |

**Provenance (private owner `fanoosupd`, mode 0600):** `/srv/fanoos/shared/research/classification/reports/residency-1404-eight-pages-applied-checkpoint-20261012.json`, SHA-256 `2f9a6ce0f4077d5c9faa6c51f4372fa2aab5fa1f4e1e3c17d59c101412c56596`, verified as 8 rows, 5 successful applied receipts, from 102 to 110. All five independently verified receipt SHA-256 values, original PDF object SHA-256 values, and printed physical-page associations are recorded in that private checkpoint. Their public nontext identifiers: `residency-1404-hupp-2-applied-20261012.json` (2 rows); `residency-1404-oralmedicine-little-2-applied-20261012.json` (2); `residency-1404-endo-q23-applied-20261012.json` (1); `residency-1404-endo-q22-q39-applied-20261012.json` (2); `residency-1404-craig-q213-applied-20261012.json` (1). Verified full backups: `/var/backups/fanoos/20261012T153557Z-38668c66` and `/var/backups/fanoos/20261012T153424Z-011f6665`, both `verify-backup.php` passed **114 files**.

**Exact independent unchanged digests:** 250 question rows `5550ae476311940bce104182eebdde3e95b72594ef324628dc1fe6f77e767098`, 1000 choices `8103a7487af85336871bd90eeb00db5dc1240d3cb811e3eb5f07b74d53a8c215`, 260 official-answer history `7ccaafbe4e04c9dcf5a80b83c594013abd4e06c6a690b10bcb10ea08c86b8621`. Frozen student assessment remains version 5, 247 questions, SHA `91d37dc63430d140597c515f4838059c8dba7ffe415522556d19291c707fdc11`. **No assessment republish or code deployment** in this source-repair checkpoint.

**Concurrency:** Other valid first-party guarded work on the same exam overlapped, producing MySQL lock-wait rejection on the first attempted Hupp apply and CAS rejection when the already-valid Hupp rows were committed concurrently. An attempted duplicate Q23 receipt was also correctly rejected after another official operator applied the same plan, and an independent DB readback confirmed it. This checkpoint counts distinct successful source rows only, not attempted duplicate writes. The separately planned **Q213** was successfully committed by this continuation with verified `153424` backup. **Holds:** Q216 Craig last printed page 299 is followed by a physical blank leaf and fails the mandatory independent neighboring printed-page validator; Q35 Torabinejad fails physical source-page/evidence corroboration. Neither may be forced into a completed count. Other previously documented human-origin and incomplete-reference-map holds remain in force.


## LATEST PRODUCTION CHECKPOINT — 36 more original-PDF pages applied, total **102/250**

**Execution 2026-10-11 (latest in this file).** The original, officially approved book/PDF SHA of each of these **36** existing AI-origin source rows was verified, each 1-indexed PDF page was visually/textually corroborated with the precise printed page and adjacent printed labels, and each cited question/official keyed choice was screened against its original passage. The official operator `scripts/references/repair_verified_source_pages.php` passed a full rollback-only preview before executing the isolated production writes with a fresh full backup. Only **`bank_question_sources.pdf_page` and `bank_question_sources.pdf_sha256`** were updated; book, chapter node, printed labels, original question text, options, official keys (including amended/voided history), human reviews, attempts and immutable student assessment versions were never rewritten.

**Live authenticated MySQL after applies:** 1404 residency, round 1, total 250 questions = 230 clinical plus 20 English reference-exempt; same-row chapter + `pdf_page` + exact-PDF `pdf_sha256` rose **66 → 102** during this continuation (the original day's first baseline was 48). **128 non-English clinical still need completion.** New rows: Neville5e oral pathology **16**, Hupp7e oral surgery **12**, Malamed7e oral surgery/local anesthesia **3**, Zarb13e prosthodontics **5**. The original approved PDF SHA-256 values are included in the protected private checkpoint and enforced by the publisher.

| Question | Original reference (edition) | Existing official chapter | Book printed page | Verified PDF page |
| ---: | --- | ---: | ---: | ---: |
| 41 | `neville-oral-pathology@5e` | 3 | 143 | **153** |
| 43 | `neville-oral-pathology@5e` | 6 | 222 | **232** |
| 45 | `neville-oral-pathology@5e` | 9 | 329 | **339** |
| 46 | `neville-oral-pathology@5e` | 10 | 367 | **377** |
| 47 | `neville-oral-pathology@5e` | 10 | 379 | **389** |
| 49 | `neville-oral-pathology@5e` | 11 | 489 | **499** |
| 50 | `neville-oral-pathology@5e` | 11 | 488 | **498** |
| 51 | `neville-oral-pathology@5e` | 12 | 569 | **579** |
| 53 | `neville-oral-pathology@5e` | 13 | 607 | **617** |
| 54 | `neville-oral-pathology@5e` | 14 | 635 | **645** |
| 52 | `neville-oral-pathology@5e` | 12 | 534 | **544** |
| 55 | `neville-oral-pathology@5e` | 14 | 664 | **674** |
| 57 | `neville-oral-pathology@5e` | 15 | 702 | **712** |
| 58 | `neville-oral-pathology@5e` | 15 | 733 | **743** |
| 59 | `neville-oral-pathology@5e` | 15 | 694 | **704** |
| 60 | `neville-oral-pathology@5e` | 16 | 790 | **800** |
| 131 | `hupp-oral-surgery@7e` | 1 | 10 | **24** |
| 133 | `hupp-oral-surgery@7e` | 4 | 48 | **62** |
| 135 | `hupp-oral-surgery@7e` | 14 | 261 | **275** |
| 136 | `hupp-oral-surgery@7e` | 15 | 281 | **295** |
| 137 | `hupp-oral-surgery@7e` | 16 | 320 | **334** |
| 138 | `hupp-oral-surgery@7e` | 21 | 446 | **460** |
| 139 | `hupp-oral-surgery@7e` | 23 | 484 | **498** |
| 140 | `hupp-oral-surgery@7e` | 24 | 512 | **526** |
| 141 | `hupp-oral-surgery@7e` | 25 | 531 | **545** |
| 142 | `hupp-oral-surgery@7e` | 28 | 617 | **631** |
| 143 | `hupp-oral-surgery@7e` | 29 | 632 | **646** |
| 144 | `hupp-oral-surgery@7e` | 26 | 584 | **598** |
| 146 | `malamed-local-anesthesia@7e` | 4 | 62 | **79** |
| 148 | `malamed-local-anesthesia@7e` | 18 | 349 | **366** |
| 149 | `malamed-local-anesthesia@7e` | 4 | 61 | **78** |
| 81 | `zarb-edentulous@13e` | 1 | 18 | **32** |
| 83 | `zarb-edentulous@13e` | 12 | 272 | **286** |
| 84 | `zarb-edentulous@13e` | 17 | 336 | **350** |
| 85 | `zarb-edentulous@13e` | 7 | 151 | **165** |
| 89 | `zarb-edentulous@13e` | 15 | 317 | **331** |

**Pre-apply independently verified full backup:** `/var/backups/fanoos/20261011T190447Z-cbce7c1e`, official `verify-backup.php` returned **114 verified files**. The completed atomic apply receipt per batch was read back and SHA-256 verified:

- `/srv/fanoos/shared/research/classification/reports/residency-1404-neville-10-applied-20261011.json` — SHA-256 `d8a765d418056e7f3d3cc09a5feb226f00b1b9cc570c5353e4dafb4c5c89e64e`.
- `/srv/fanoos/shared/research/classification/reports/residency-1404-neville-6-applied-20261011.json` — SHA-256 `244f302a28a9ed5fefcf9feb3dbb7195051cdc2a7926542ec6676557b5940b26`.
- `/srv/fanoos/shared/research/classification/reports/residency-1404-hupp-12-applied-20261011.json` — SHA-256 `6b1192eb4fa83ef41b8ac2dfb164ceaeec30cc7c57a102989f87936c4a483f46`.
- `/srv/fanoos/shared/research/classification/reports/residency-1404-malamed-3-applied-20261011.json` — SHA-256 `edd4c2371ff7081ead0a3166f1f097ca8149df89bf4c35ce82886c7e6ea0cbbc`.
- `/srv/fanoos/shared/research/classification/reports/residency-1404-zarb-5-applied-20261011.json` — SHA-256 `4b13841288c23fa827523653990459dd3a0098c00e9a3ba08b5b24adfb81203e`.

Protected server research checkpoint: `/srv/fanoos/shared/research/classification/reports/residency-1404-36-exact-pdf-page-applied-checkpoint-20261011.json` (owner `fanoosupd`, `0600`), SHA-256 `1c1c360a310022edb3378eb4a18694a42da7c642caa312110f3e3b14572f80d3`; readback confirms exactly 36 source rows, 5 committed receipts, new total 102. The previous 18-applied checkpoint was `residency-1404-eighteen-bound-pages-applied-checkpoint-20261011.json`; these 36 are **additional**, not duplicates.

**Strict non-source invariants after updates:** 250 question rows digest `5550ae476311940bce104182eebdde3e95b72594ef324628dc1fe6f77e767098`; 1,000 options digest `8103a7487af85336871bd90eeb00db5dc1240d3cb811e3eb5f07b74d53a8c215`; 260 official answer-history records digest `7ccaafbe4e04c9dcf5a80b83c594013abd4e06c6a690b10bcb10ea08c86b8621` — all identical to the preceding checkpoint. Student-facing immutable published exam **v5 / 247 questions** remains unchanged, frozen digest `91d37dc63430d140597c515f4838059c8dba7ffe415522556d19291c707fdc11`. **No frozen-exam re-publication** in this execution; bank citation metadata is already committed, but the site assessment's legacy explanation snapshot remains until the full official reviewer-authorized publisher/revalidation.

**Explicit holds:** oral pathology Q42 (amended negative question), Q44 wrong year-scope chapter, Q48 historic chapter/page conflict, Q56 protected human source; oral surgery Hupp Q132/Q134 and Q145 (amended) require further semantic review, human Q147 excluded, Malamed Q150 official-key premise clashes with discussion of safety of amide agents; Zarb Q82 impossible original printed label for ch11, Q86/Q88 fail independent operator original-page/evidence gate even though preliminary locations were read, Q87 source does not directly establish keyed lip sign, Q90 human protected. The McCracken13e and McDonald11e chapter maps are not SHA-bound to their currently approved original PDF objects, and Carranza14e approved object is only a 105-page fragment. Existing 1404 oral radiology/community sources are human-origin. **Do not infer pages, change human decisions or force rejected rows.** Earlier Little & Falace Q76/Q78 passed rollback preview but their apply remained blocked, so they are not counted.

The site `/health` was OK at live release `cd3b7913640dcb6ccdcb471e637e9e3236b89c4e`; repository documentation merge is tracked independently from live database source commits and site deployment.



**Scope:** FANOOS dental **residency**, 1404, round 1. This is a read-only production and original-reference audit, not a publication or authorization to rewrite older source metadata. Use [the indivisible chapter/page policy](../product/09_CHAPTER_CLASSIFICATION.md) and [project principles](../PROJECT_PRINCIPLES.md) for all subsequent steps.

## Live baseline and exemption

The authenticated production `fanoos_prod` query counted 250 questions, of which 230 non-English questions had an existing source/chapter and **48** had `node_id`, `pdf_page`, and `pdf_sha256` **on the same row**. Thus **202** lack this recorded pair; **20** are English and independently satisfy the official-key-only exemption (19 `final` and one `amended`, all published, all with a non-null keyed choice). Exactly **182 non-English questions** still need chapter+PDF-page completion or review. Field presence is not an independent proof of correct quotations or year scope. No new source row was inserted or updated during this audit.

| Subject | All | Existing chapter | Bound chapter + PDF page + SHA | Non-English still incomplete | Existing human-origin sources |
| --- | ---: | ---: | ---: | ---: | ---: |
| Orthodontics | 20 | 20 | 20 | 0 | 2 |
| Endodontics | 20 | 20 | 2 | 18 | 2 |
| Oral pathology | 20 | 20 | 0 | 20 | 1 |
| Oral medicine | 20 | 20 | 3 | 17 | 4 |
| Prosthodontics | 30 | 30 | 7 | 23 | 6 |
| Periodontics | 20 | 20 | 0 | 20 | 0 |
| Oral surgery | 20 | 20 | 0 | 20 | 1 |
| Operative dentistry | 20 | 20 | 12 | 8 | 6 |
| Pediatric dentistry | 20 | 20 | 0 | 20 | 5 |
| Oral radiology | 20 | 20 | 0 | 20 | 20 |
| Dental materials | 10 | 10 | 4 | 6 | 1 |
| Community dentistry | 10 | 10 | 0 | 10 | 10 |
| English (reference-exempt) | 20 | 0 | 0 | excluded | 0 |
| **Total** | **250** | **230** | **48** | **182** | **58** |

The earlier production backlog `BANK_CLASSIFICATION_PAGE_BACKLOG_20261011.md` independently reports the same 250/48/202 recorded-field figures. At the start of this audit the deployed health endpoint showed release `524ec2b`; it subsequently advanced normally to `2de5b8d` while concurrent work updated GitHub main. Read the current live release and Git HEAD again before any next step. Site `/health` returned `status=ok`. This audit did not deploy software or publish assessments.

## Exact official 1404 reference eligibility

The live `bank_reference_validity` catalog names **19 reference editions** (including multiple titles for some subjects). All 19 were read through the approved `verify_reference_pdfs.py --only <edition>` on the authorized server; all 19 original protected objects passed their SHA metadata checks, and **18** had a structurally valid PDF chapter-run map bound to their exact file. **Carranza Periodontology 14e** is an exception: the object contains only **105 PDF pages** despite an expected full-book map; `chapter_map_valid=false`. It cannot be used to claim exact question pages or silently replaced with the 13e. Continue other subjects without waiting for its complete original edition.

**Torabinejad Endodontics 6e:** 501 pages, 22 chapter runs, SHA-256 `09333f079cb3300550cc0702985aae216d952eacabdaac005e1c66fafebe13e0`; readability check: 499/501 pages with text. The 1404 official validity includes all its chapters.

All PDF inspections used the approved original object and single-page in-memory reader. No copied reference PDF, whole-book text, OCR output, or page-text index was created.

## Direct original-PDF source-location candidates — not applied

Two existing AI-origin **1404 endodontics** chapter-anchored but incomplete citations were checked individually against the unmodified official Torabinejad 6e PDF:

| Question | Existing chapter | Legacy printed page | Directly checked original PDF page | Finding | Status |
| --- | --- | --- | --- | --- | --- |
| Q21 | 11 | 215 | **222** | Original page displays printed 215, chapter 11; the passage explicitly discusses permanent-successor discoloration, malformation and enamel hypoplasia after primary-tooth injury, corresponding to the recorded question and excluded distractor | Original-page location identified; pending dedicated immutable metadata validator, backup, and publication |
| Q40 | 7 | 133 | **140** | Original page displays printed 133, chapter 7; figure 7.31 names the periodontal curette for the root-surface procedure after apical surgery / granulation tissue removal | Original-page location identified; require figure-level review for precise question wording; not published |

The listed PDF numbers are 1-indexed PDF viewer pages, **not** the printed labels. Both candidates use the hash above. Do not infer a universal +7 offset for every page: each cited question requires its own original-page verification, a correct chapter boundary, and authentic official scope. Exact limited evidence quotations and metadata must be kept in protected private audit/decision artifacts before any source mutation. Q24 endodontics has a `voided` official answer and must not be silently treated as final; Q29 has an `amended` answer and must preserve it.

## First direct-PDF screening of all 18 legacy endodontics pages

A follow-up read-only pass requested the **original PDF page of every one of the 18** legacy chaptered endodontics questions lacking `pdf_page` and `pdf_sha256`; existing human rows Q27 and Q34 already have the bound PDF fields and were excluded. All 18 expected printed labels were found on the individually requested original pages in their recorded chapters; this is **location screening only**, not scientific approval of all historic chapter/key claims. An independent question-level content screen classified **eight** page locations as strong or potentially strong citation candidates (Q21, Q23, Q25, Q28, Q31, Q36, Q37, Q40), and **ten** as needing further evidence/figure/key review (Q22, Q24, Q26, Q29, Q30, Q32, Q33, Q35, Q38, Q39). Q24 has a voided answer, Q29 an amended answer, and Q32/Q38 depend on figures. Preserving the official answer states is mandatory.

The 18-row private no-apply source-location report is at `/srv/fanoos/shared/research/classification/reports/residency-1404-endo-original-page-screen-20261011.json`, SHA-256 `4c7177790d93f2e5637f4ba182a2f8b8c5884ced4e2f1815adc385dc138122dc`. It contains only question numbers, chapter, printed and verified original PDF page, short screening dispositions and the pinned original PDF SHA (no extracted original-book pages or question stems). The report is not an import file, and its eight candidates have `approved:false`; direct evidence, visual checks where needed, source-only publishing guards and full verified backup remain necessary before DB publication. The 18 read-only page inspections changed **zero** source rows.

## Follow-up original-PDF screening: six endodontics + three dental materials (same day)

A second evidence-level examination of the stored endodontics screen narrowed the **eight preliminary endodontics candidates** to **six comparatively direct, keyed-option-supporting locations**: Q21 ch11/PDF222 (printed215), Q25 ch10/PDF197 (printed190), Q28 ch16/PDF359 (printed352), Q31 ch9/PDF175 (printed168), Q36 ch15/PDF340 (printed333), and Q37 ch13/PDF278 (printed271). All use Torabinejad 6e SHA `09333f079cb3300550cc0702985aae216d952eacabdaac005e1c66fafebe13e0`. **Q23** remains on hold because the negative-apical-patency answer needs further independent semantic justification. **Q40** remains on hold because the existing historical anchor discusses a *bone curette* although the official answer specifies a *periodontal curette*; the separate figure-caption location is not enough to silently override that conflict. Q24 (voided), Q29 (amended), and the figure-dependent items retain their existing protected states.

Direct original-page reads also found three promising existing **Craig Restorative Dental Materials 14e** citations (approved server object SHA `333e81ae8a1818cdff57f00e3f870698776e3b8e02fcc49169f397f2fbaf90b9`):

| 1404 residency question | Existing official Craig 14e chapter | Original printed page | Direct PDF page | Fact corroborated |
| --- | ---: | ---: | ---: | --- |
| Q211 | 4 | 42 | **53** | An immediate reduction in creep-recovery strain represents elastic recovery |
| Q212 | 10 | 200 | **211** | Heating can recrystallize wrought alloys into a grained structure |
| Q217 | 12 | 233 | **244** | Phosphate binds calcium and regulates alginate working/setting characteristics |

Materials Q213 (negative fracture-toughness rationale not established on found page), Q216 (legacy printed299 points to an uninformative/blank page around PDF310), Q219 (human source outside listed official scope), and Q220 (historical Van Noort section 2.3 outside listed scope) are held. No nearest-book substitution, page-offset extrapolation or source record mutation was performed.

The 9 candidate metadata/proof snippets are preserved in the **protected, research-only** report `/srv/fanoos/shared/research/classification/reports/residency-1404-nine-original-pdf-page-candidates-20261011.json`, SHA-256 `85b658ac84c508bc2ddb34764fe1e3b204b450041dccc5b1d977e44167f7ea9f`. Read-back confirmed exactly six endodontics and three dental-materials entries, six stated holds and `live_source_records_updated:0`. The receipt records original-PDF page locations and short candidate proof snippets, not entire original pages or bank stem/choice text. It **does not** substitute for two-neighbor printed-label verification, complete independent scientific review or the approved source-metadata publication.

**Implementation gate:** A provisional one-time script was created on an isolated GitHub branch, but a safety validation interrupted its correction. The incomplete script was explicitly **deleted from that branch before any pull request/merge/deploy**. No page operator from this 1404 work is present in GitHub main or active production. Any future production operator must be separately reviewed, pass CI, protect full bank-row fingerprints, update *only* `pdf_page` and `pdf_sha256`, take a fresh complete verified backup, record a unique receipt, and perform full source/assessment before–after checks. **New live bound-page count remains 48/250, with 182 non-English requiring completion and 20 exempt English questions.**

## Production execution checkpoint: ten exact original-PDF pages applied (2026-10-11)

**This newer section supersedes the earlier read-only-only status of the *nine* candidate rows above.** Ten existing `ai` sources were actually updated in production with the deployed, pre-existing, Git-reviewed `scripts/references/repair_verified_source_pages.php` operator. Each batch passed rollback-preview against the **official 1404 scope**, exact original PDF SHA and bound chapter runs, historic original anchor fingerprint, authentic **PDF + printed page-label corroboration using two neighboring pages**, published non-human source identity, official key, and existing question choices. The **only changed columns** were `bank_question_sources.pdf_page` and `bank_question_sources.pdf_sha256`: neither the legacy printed number, chapter, edition, human source, question, options, keys nor any historical student assessment/attempt was changed.

| Question(s) | Subject / exact edition | Existing chapters | Book printed → original PDF pages | Result |
| --- | --- | --- | --- | --- |
| 21, 25, 28, 31, 36, 37 | Endodontics / Torabinejad 6e | 11, 10, 16, 9, 15, 13 | 215→222; 190→197; 352→359; 168→175; 333→340; 271→278 | 6 atomically applied |
| 30 | Endodontics / Torabinejad 6e | 20 | 434→**441** | 1 separately reviewed and atomically applied; original text explicitly lists *longer surgical duration* as a disadvantage of piezosurgery, supporting the existing negative-choice final key |
| 211, 212, 217 | Dental materials / Craig 14e | 4, 10, 12 | 42→53; 200→211; 233→244 | 3 atomically applied |

**Before/after production database readback:** full 1404 residency sitting **250** questions / 230 clinical, **48 → 58** source rows with chapter + `pdf_page` + `pdf_sha256` on the **same row**, endodontics **2 → 9**, dental materials **4 → 7**, remaining **172** non-English questions. English exemption remains 20. The original PDF SHA is `09333f079cb3300550cc0702985aae216d952eacabdaac005e1c66fafebe13e0` for Torabinejad6e and `333e81ae8a1818cdff57f00e3f870698776e3b8e02fcc49169f397f2fbaf90b9` for Craig14e. The 10 source rows, printed labels, chapter and PDF hashes were separately read back from `fanoos_prod`.

**Verified full pre-update backup:** `/var/backups/fanoos/20261011T150038Z-b3aff002`, official `verify-backup.php` passed **114 files** before first apply and on subsequent check. Each of the three batch mutations has an independently read-back protected receipt:

- `residency-1404-materials-3-applied-20261011-1.json`: SHA-256 `755d42fb226f912cc2fb1f4dd639cbbeab7758272cad32f88dcefce5e95ed8b0` (3 rows).
- `residency-1404-endo-6-applied-20261011-1.json`: SHA-256 `fca3aa0e3cc83729eab3bcb803e24e2bb74543050934621a025b08cafd6d8082` (6 rows).
- `residency-1404-endo-q30-piezosurgery-applied-20261011-1.json`: SHA-256 `8ba577cdde7bf3cfbc1cbbc25c49938739bce35e95704b709859c912eebd2b37` (1 row).

All receipt files reside under `/srv/fanoos/shared/research/classification/reports/` with private permissions. The aggregate private immutable audit is `residency-1404-ten-bound-pages-applied-checkpoint-20261011.json`, SHA-256 `88cbdd6d69f446b7a7df17926cb652dcbe361549c90e0bfc61c73c8154d166c0`. It records the backup, receipts, 48→58 counts and stable bank/frozen exam fingerprints. Across before/after verified SQL, **all 250 stored questions** retain digest `5550ae476311940bce104182eebdde3e95b72594ef324628dc1fe6f77e767098`; **1000 choices** retain `8103a7487af85336871bd90eeb00db5dc1240d3cb811e3eb5f07b74d53a8c215`; and **260 official-answer history rows** retain `7ccaafbe4e04c9dcf5a80b83c594013abd4e06c6a690b10bcb10ea08c86b8621`.

**Frozen assessment not yet re-published.** Published version remains **5**, with **247 questions**, unchanged digest `91d37dc63430d140597c515f4838059c8dba7ffe415522556d19291c707fdc11`; the other **three** bank questions are legitimately draft (not deleted). The nine/ten new citation locations are immediately present in *bank source rows*, but an immutable student-facing published assessment retains its previous explanations until a **separate**, preflighted, reviewer-authorized BankPublisher append and full before/after frozen exam audit. Do not confuse database metadata completion with student-view publication. The deployed site `/health` remained `status=ok` at release `cd3b7913640dcb6ccdcb471e637e9e3236b89c4e`. No code deployment or frozen-exam publication was claimed for this source-only repair.

The previously held endodontics items (e.g. Q23, Q40, Q24 voided) and nine year-scope conflicts remain unmodified. **Next:** continue direct, independent original-page evidence per subject and approved same-row source metadata repair, while obtaining a safe published-assessment preflight and reviewer-authorized append if student-visible updating is requested.

## Second production source-only publication: eight Burket oral-medicine citations (same day)

After the earlier 10 audited original-page repairs, eight additional 1404 **oral-medicine** questions were verified directly in the **official Burket's Oral Medicine 13e** original PDF, exact SHA-256 `0ca841beb1820e8e0a33c96b5ce37cc36c94d6adce53fbf172b27312e0fd5252`. The deployed, reviewed `repair_verified_source_pages.php` existing-source operator passed **the entire eight-row rollback preview** and then the real atomic apply. The original PDF page contents, the corresponding printed-book label and at least two neighboring label pages, the 1404 official chapter scope, source anchor SHA, original final-key/choice state, absence of a human reviewer and exact pinned edition/hash passed for each source.

| 1404 question | Existing Burket 13e chapter | Original printed page | One-indexed verified PDF page |
| --- | ---: | ---: | ---: |
| 61 | 3 | 41 | **61** |
| 62 | 3 | 59 | **79** |
| 63 | 3 | 65 | **85** |
| 64 | 4 | 90 | **110** |
| 65 | 5 | 148 | **168** |
| 66 | 6 | 177 | **197** |
| 67 | 6 | 192 | **212** |
| 80 | 9 | 318 | **338** |

The original page passages matched the existing keyed medical concepts: herpes-labialis topical antivirals (61), pemphigus biopsy at the advancing lesion margin (62), desquamative gingivitis differential (63), pseudomembranous Candida (64), mucosal melanoma sites/paresthesia (65), gingival pyogenic granuloma (66), ALP evaluation for a central giant-cell lesion/hyperparathyroidism (67), and asymptomatic bilateral sialadenosis with chronic alcoholism (80). These are original-book locations, not printed-page offsets inferred from a conversion constant.

**Production verified 48 → 66 bound clinical source rows cumulatively** (10 earlier + 8 Burket): 1404 residency **250 total**, 230 clinical, 20 reference-exempt English, **164 clinical still incomplete**. Endodontics now 9/20, dental materials 7/10, and oral medicine **3 → 11 of 20**; unchanged subjects retain the earlier inventory. Independent direct MySQL row readback returned all eight book chapters/printed pages/PDF locations, `origin=ai` and no human review. The precise sole SQL mutations were `bank_question_sources.pdf_page` and `bank_question_sources.pdf_sha256` (not chapters, legacy labels or any question, option, answer, human review or attempt). The original 250-question digest and 1000-choice digest remain identical to the earlier checkpoint. Frozen student assessment remained v5 / 247 published questions and identical SHA `91d37dc63430d140597c515f4838059c8dba7ffe415522556d19291c707fdc11`, **not re-published**.

**Independent verified full backup** for both batches remained `/var/backups/fanoos/20261011T150038Z-b3aff002` (114 manifest files verified). The new protected apply receipt is `/srv/fanoos/shared/research/classification/reports/residency-1404-oralmedicine-burket-8-applied-20261011-1.json`, SHA-256 `00daa92d1b7eee3eb4bd98b6e5f6899d9e92a2167a0d018af1bd79915abaa822`; the cumulative 18-applied private immutable checkpoint is `residency-1404-eighteen-bound-pages-applied-checkpoint-20261011.json`, SHA `31cfc7994e6eaba4b5200ef18887ae5ad9de94ebfbafde26ab42bfcbec32be78`. Site `/health` remained OK (active release `cd3b7913640dcb6ccdcb471e637e9e3236b89c4e` at readback). The 1404 student-facing frozen assessment still needs separately reviewed, non-explanation-identity preflight before any new version is published.

**Additional independent research, NOT applied:** Two Little & Falace 10e oral-medicine cases passed the same official rollback preview, Q76 ch16 printed251/PDF287 and Q78 ch17 printed275/PDF313, tied to original SHA `6568ce7b80ae216f80285d08c231502931535bd322e835f62bd76f255341834e`. Their prepared protected plan is `residency-1404-oralmedicine-little-2-verified-20261011.json`, SHA `e81f85b8c195f6feba101531f9b16ff0bb3fcb21761a036c5f64a681c0f304a0`. The actual apply command was **blocked by an external tool safety check** before execution; live DB readback confirms Q76/Q78 still `pdf_page=NULL`. Do not count these two as completed or bypass the execution safeguard. Human source records Q68/71/72/74, including Q71 voided, were deliberately excluded. Q69 may have key/differential issues; Q70 and Q77 require further original-page semantic review. Continue other independently valid sources rather than assuming all subjects are ready.

## Existing citations that conflict with enumerated 1404 official scope

A read-only equality audit joined each existing `bank_question_sources` chapter number and edition to the **same** 1404 residency subject/edition `bank_reference_validity.scope_chapters` list. **Nine** existing rows have a chapter outside their explicitly enumerated chapter list (seven AI-origin, **two protected human-origin**). This first pass does **not** validate nuanced partial-page/section restrictions, and must not trigger deletion, key changes or automatic replacement.

| Subject | Question | Existing book and chapter | Origin | Gate |
| --- | --- | --- | --- | --- |
| Dental materials | 219 | Craig 14e, chapter 13 | human | Hold for owner/reference-scope review |
| Dental materials | 220 | Van Noort 5e, section 2.3 | AI | Verify section limits and original page |
| Operative | 154 | Summit 4e, chapter 5 | AI | Verify exact official scope |
| Operative | 162 | Sturdevant 7e, chapter 14 | AI | Verify exact official scope |
| Operative | 163 | Sturdevant 7e, chapter 14 | human | Preserve reviewer; hold |
| Operative | 166 | Summit 4e, chapter 3 | AI | Verify exact official scope |
| Oral pathology | 44 | Neville 5e, chapter 7 | AI | Chapter 7 excluded from that year's list |
| Periodontics | 129 | Carranza 14e, chapter 73 | AI | Exact 14e PDF incomplete and chapter out of scope |
| Periodontics | 130 | Carranza 14e, chapter 87 | AI | Exact 14e PDF incomplete and chapter out of scope |

Nine is a chapter-list discrepancy count, not nine approved replacement decisions. Subject names correspond to bank keys and year-specific source validity; any recorded human decision is immutable without an explicit separately reviewed resolution.

## Reproduce the counts and protect historical state

The authorized production read used the project's configured read-only SQL route through the updater account. Per-question completeness is computed with **one-row** `EXISTS`:

```sql
SELECT bs.subject_key, COUNT(*) AS total,
       SUM(EXISTS (
          SELECT 1 FROM bank_question_sources s
          WHERE s.workspace_id=q.workspace_id AND s.question_id=q.id
            AND s.node_id IS NOT NULL
       )) AS chapter_rows,
       SUM(EXISTS (
          SELECT 1 FROM bank_question_sources s
          WHERE s.workspace_id=q.workspace_id AND s.question_id=q.id
            AND s.node_id IS NOT NULL AND s.pdf_page IS NOT NULL
            AND s.pdf_sha256 IS NOT NULL
       )) AS chapter_pdf_bound
FROM bank_questions q
JOIN bank_exam_sittings si
 ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
JOIN bank_exam_types et
 ON et.id=si.exam_type_id AND et.workspace_id=si.workspace_id
JOIN bank_subjects bs
 ON bs.id=q.subject_id AND bs.workspace_id=q.workspace_id
WHERE et.type_key='residency' AND si.exam_year=1404 AND si.exam_round=1
GROUP BY bs.subject_key;
```

Before changing **any** source, retrieve the current live question/choice/key/assessment baseline and compare it with the exact 1404 site-matching sitting specified by the classification manual; the 1404 generic DOCX conversion is historically **not** identical to all site stems. Never import a study-only sitting into the question bank. For existing sources use a distinct, scope-locked, compare-and-swap metadata-only publisher that first rejects any changed human-owned row, any missing genuine PDF page evidence, any out-of-scope candidate, or any edition/hash/row-identity drift. Perform dry-run, independent evidence audit, fresh complete verified backup, source-only apply with a unique immutable receipt, read-back of all source rows and protected question/choices/keys, then exact frozen-assessment comparison and approved publication. CI green, merge and official updater deployment are separate requirements **only if code changes are involved**.

**Checkpoint outcome:** all 250 questions counted; 19 official reference objects inspected; 18 maps structurally eligible; one book unavailable as a complete map; nine scope discrepancies isolated; 18 original printed/PDF page pairs screened (8 preliminary candidates, 10 holds) and two initially examined in greater detail. **Zero newly completed bank source rows, zero question/choice/key modifications, zero assessment republishes, and zero new classification deployments.** Preserve these research candidates for the next guarded publication transaction; do not represent them as live classifications.
