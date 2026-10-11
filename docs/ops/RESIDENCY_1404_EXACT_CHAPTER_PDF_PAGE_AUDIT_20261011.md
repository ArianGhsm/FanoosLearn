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
