# Residency 1399 — strict chapter + original PDF page audit (2026-10-11)

**Status:** read-only baseline and evidence triage; **zero source rows updated by this audit**, zero question/answer/attempt changes, no assessment republished. This snapshot is not a publication receipt.

## Authority and current snapshot

- Production: `fanoos_prod`, exam type `residency`, year `1399`, round `1`; sitting ID `01a117cd-99f7-77d2-a05e-81a2ce47d681`.
- Read directly from the live MySQL bank through the authorized `fanoosupd` operator on IranServer; 2026-10-11, Tehran local afternoon.
- Exact remote GitHub `main` and updater checkout while checked: `2de5b8da121dfdd225923adeb386de839a086711`. Live `/health` was healthy on `15e823ae7a04775e45646c1315ddcfa3cc11fd52`; newer checkout revision was **not claimed deployed**.
- All 249 sitting questions: **87** with `node_id + pdf_page + pdf_sha256` on the **same** `bank_question_sources` row; **162** not recorded complete. **20** are English without book citation (source-free official-answer-key exemption); **142** non-English remain in scope for individual source or metadata audit.
- Field presence is not evidence of scientific verification: exact official syllabus, edition, chapter-map integrity, current PDF SHA-256 and original page quote must also be checked before declaring a specific record validated.

| Subject key | All | Same-row chapter+PDF page+hash | Incomplete (English exempt) |
|---|---:|---:|---:|
| community-dentistry | 10 | 10 | 0 |
| dental-materials | 10 | 0 | 10 |
| endodontics | 20 | 16 | 4 |
| english | 20 | 0 | 20 exempt |
| operative-dentistry | 20 | 16 | 4 |
| oral-medicine | 20 | 0 | 20 |
| oral-pathology | 19 | 0 | 19 |
| oral-radiology | 20 | 12 | 8 |
| oral-surgery | 20 | 0 | 20 |
| orthodontics | 20 | 20 | 0 |
| pediatric-dentistry | 20 | 0 | 20 |
| periodontics | 20 | 0 | 20 |
| prosthodontics | 30 | 13 | 17 |
| **Total** | **249** | **87** | **142 + 20 exempt** |

## Current cases and hazards

- Endodontics `torabinejad-endodontics@5e`: missing bound PDF page on Q25 (legacy printed 7, chapter 4), Q30 (printed 335, chapter 18), Q31 (printed 281, chapter 16), Q32 (printed 9, chapter 1). The verified bound map has chapter 1 on PDF pp. 13–32, chapter 4 on 60–79, chapter 16 on 285–312, and chapter 18 on 328–349. **None of the four printed labels alone proves the PDF page.** A direct check of PDF p335 did not locate the GuttaFlow answer fact for Q30; leave undecided.
- Periodontics: all 20 existing source records are incomplete. The stored exact year edition is `carranza-periodontology@13e`; legacy source anchors describe provisional 14e nearest-edition research. Do not treat those as validated current 13e evidence. Protected, preexisting candidate query terms are stored at `/srv/fanoos/shared/research/classification/reports/1399-periodontics-carranza13-queries-20261010.json`. A 13e page scan is research only; inspect original pages and announced scope before accepting anything.
- The current existing-row gaps have no case with `pdf_page` present but `pdf_sha256` missing. Most legacy rows use `printed_page` or no page. Generic `scripts/references/backfill_source_pages.php` only promotes an explicit `pdf N` legacy value to a bound page with its verified source; it is **not** proof that a printed number equals the physical PDF page, and does not independently repair this sitting.

## Independently reread original-PDF page leads (research, not DB commits)

The following original-book pages were read directly through `verified_reference_pdf.py --require-map --page N`. The bridge verified the current approved private PDF and SHA-bound chapter map. Official 1399 validity was independently confirmed in the live `bank_reference_validity` table; `torabinejad-endodontics@5e` has all chapters in scope and `carranza-periodontology@13e` explicitly includes chapters 3, 12, 33, 52 and 72. These findings are **not recorded-complete until a guarded source-row transaction commits `pdf_page + pdf_sha256` and passes post-audit.**

| Subject | Question | Original edition | Existing chapter | Physical PDF page | Printed label on page | Page evidence, summarized |
|---|---:|---|---:|---:|---:|---|
| Endodontics | 25 | Torabinejad 5e | 4 | **63** | **51** (legacy source claims **7**, conflicting) | LPS / lipid A and TLR4 receptor shown together in the innate-immunity table |
| Endodontics | 30 | Torabinejad 5e | 18 | **347** | 335 | GuttaFlow identified as cold, flowable injection filling system |
| Endodontics | 32 | Torabinejad 5e | 1 | **21** | 9 | Growth factors embedded in dentin matrix |
| Periodontics | 115 | Carranza 13e | 12 | **506** | 182 | Smoking changes probing bleeding, attachment and bone loss, table 12.1 |
| Periodontics | 118 | Carranza 13e | 33 | **882** | 408 | CBCT/periapical defect comparison; original historical audited fenestration quote needs exact retained validator check |
| Periodontics | 122 | Carranza 13e | 72 | **1611** | 721 | Merin class C, 1–3-month recall; unperformed indicated surgery for medical reasons |
| Periodontics | 123 | Carranza 13e | 52 | **1196** | 559 | Original 13e metronidazole and warfarin drug interaction; exact short quote needs revalidation by guarded publisher |
| Periodontics | 125 | Carranza 13e | 3 | **96** | 46 | Transseptal/alveolar-crest fibers develop at tooth emergence |

Pinned map/document SHA-256: Torabinejad 5e `71f21c26a4f1e5d038ff6aa408669a5cc040e90c94eea0bf2e00478997710146` (494 PDF pages); Carranza 13e `f0e411898ae010688ca5c0d21afe312cef6f5dae86d0e2e45648bc51ca8e2adf` (1,991 PDF pages). Perio Q115/Q118/Q122/Q123/Q125 already appear in retired historical Git PR #268 as previously page-evidence researched; **do not rerun obsolete page-label-only operator** because it does not fill the new strict `pdf_page` and `pdf_sha256` columns. This audit rechecked the indicated live original PDF pages.

Endodontics Q31 (QMix irrigation) remains pending: looking through original Torabinejad 5e chapter-16 pages did not identify direct QMix-specific supporting evidence. Q25 must not retain the contradictory legacy printed page 7 when a reviewed metadata correction is made (the actual original PDF p63 is printed p51). No live bank mutation has occurred here.

## Reproducibility: the read-only strict count

```sql
SELECT sub.subject_key,
       COUNT(*) AS total,
       SUM(EXISTS(
           SELECT 1 FROM bank_question_sources s
           WHERE s.workspace_id = q.workspace_id
             AND s.question_id = q.id
             AND s.node_id IS NOT NULL
             AND s.pdf_page IS NOT NULL
             AND s.pdf_sha256 IS NOT NULL
       )) AS recorded_complete
FROM bank_questions q
JOIN bank_exam_sittings si ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=si.workspace_id
JOIN bank_subjects sub ON sub.id=q.subject_id AND sub.workspace_id=q.workspace_id
WHERE et.type_key='residency' AND si.exam_year=1399 AND si.exam_round=1
GROUP BY sub.subject_key
ORDER BY sub.subject_key;
```

## Next safe work, acceptance and handoff

Check the new live head, unique official same-year `validity`, PDF inventory and pinned chapter map before processing each subject. Prioritize cases with a legitimate existing source and an exact original-book quote; search one PDF page at a time with `scripts/references/verified_reference_pdf.py` / `find_in_books.py`, without a saved text derivative. Independently confirm the answer fact **and** source chapter/scope. Where a legacy chapter is wrong, treat its correction as a separately audited change; protect every human decision. Do not import chapter-only proposals.

Use a tested GitHub-governed **existing-source metadata-only** operator where needed; do not use an unreviewed ad-hoc SQL bulk update. Preview the exact allowlisted row IDs, old and proposed values, edition/hash/page/quote, unchanged stems/choices/official keys and unaffected attempts. Require a fresh verified full backup, exclusive publisher lock, atomic source-only transaction, unique private receipt, live DB readback, frozen full sitting diff, green PR/main CI and authorized updater deployment with healthy site check. A green docs PR alone does **not** count as a completed classification or database publication.

Private continuity checkpoint: SentinelX context `sxc_6QE4SMKZ`. Public policy: `docs/PROJECT_PRINCIPLES.md` and `docs/product/09_CHAPTER_CLASSIFICATION.md`. No private question text, verbatim book passages, credentials or PDF copies are placed here.
