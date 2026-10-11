# Residency 1404 exact chapter + PDF-page audit — 2026-10-11

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
