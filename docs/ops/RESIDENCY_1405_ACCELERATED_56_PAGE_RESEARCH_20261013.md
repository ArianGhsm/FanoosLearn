# Residency 1405 — accelerated edition-accurate chapter/PDF page candidate screen (2026-10-13)

## Production state, safety and specific meaning of numbers

An exact-sitting, read-only live production query on the newly approved/deployed release `3ec7953b4c10b1cefe78f7242d4385876d15818b` showed **99/250 questions** with all three fields of the same source record populated: chapter node, actual `pdf_page`, approved PDF SHA-256. Twenty English questions are source-exempt, leaving **131 textbook-based questions without all three fields**. This field count is **not** itself a scientific certification of existing historical chapters. Public `/health` was OK.

On the new deployed release, which includes the strictly scoped genuine PDF terminal-line dehyphenation and SHA-pinned chapters, all **seven prior original-source chapter-and-page cases** Q34, Q51, Q57, Q88, Q143, Q185, Q186 passed independent **guarded rollback-only** source metadata repair, zero question/choice/answer/chapter/human review changes. Separately, source-free radiology Q191, Q192, Q193, Q194 passed a single **atomic guarded rollback-only preview** returning `source_rows=4`, zero question/choice/answer changes. All original single-question studies and provenance audits are retained in the protected case directories listed in `docs/ops/RESIDENCY_1405_ELEVEN_READY_PAGE_CASES_20261012.md`, with exact study/audit SHA pins.

A fresh official full backup at `/var/backups/fanoos/20261012T205139Z-9e750ac0` was successfully created and independently verified: **114 files passed**. However, actual production Q34 source metadata repair failed due to `SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded`; the independent scoped live count remained **99**. The host had serious I/O contention (`/proc/pressure/io` with elevated PSI, and overlapping authorized full backup operations). A later attempt to execute seven applies as a single script was rejected by tool safety gates and must not be reissued through a disguised tool or command form. **None of these 11 dry-run cases is counted as applied**. Treat transaction receipts and independent bank readback as the sole completion evidence.

## Accelerated 66-candidate / 56 corroborated-page screen

A read-only, whole-chapter scan of the current SHA-verified approved server PDFs was conducted for incompletely sourced questions using exact 1405 reference editions and official chapter scope plus rare source-anchor content terms. It identified **66 unique-PDF-page research candidates**, then separately inspected physical printed page labels from those pages and at least **two neighboring pages**, retaining **56 cases with independently corroborated same-number legacy printed labels**. The other **10** failed to corroborate a printed label and remain held.

These 56 are **corroborated printed-to-PDF-page research candidates**, not yet scientifically adjudicated complete source mappings. The source quote is not an exact stored original-anchor substring on some PDF pages; a word-set overlap is not sufficient for a final scientifically defended chapter/page assignment. Continue with a conservative **local passage** comparison and subject-matter decision for each candidate rather than filling 56 pages blindly.

| Approved 1405 reference edition | Candidates with original printed labels + PDF neighbor evidence |
|---|---:|
| Burket oral medicine 13e | 7 |
| Craig restorative materials 14e | 3 |
| Hupp oral surgery 7e | 8 |
| Little/Falace dental management 10e | 2 |
| Malamed local anesthesia 7e | 3 |
| McCracken removable partial dentures 13e | 6 |
| McDonald/Avery pediatric dentistry 11e | 7 |
| Neville oral pathology 5e | 8 |
| Torabinejad endodontics 6e | 7 |
| Zarb complete dentures 13e | 5 |
| **Total** | **56** |

Read-only original AI source anchors, official chapter, PDF index and old printed label were checked without mutation. Immutable protected research evidence is stored under:

- `/srv/fanoos/shared/research/classification/reports/1405-rare-token-chapter-page-fuzzy-screen-20261012.json` — all 66 unique page candidates, relative content-term scores.
- `/srv/fanoos/shared/research/classification/reports/1405-66-fuzzy-page-label-corroboration-20261013.json` — exact 56 corroborated and 10 held, as well as each edition, source chapter and candidate real PDF page.

**Do not use** the 56 as 56 production applied changes: additionally verify passage-local content and latest official year original answer, original choices, accepted independent scientific audit, current approved SHA chapter map, actual book-page labels, backup and guarded source-only readback. Existing-source repair intentionally fails closed if the original source anchor is paraphrased; it is unsafe to bypass the guard.

## Carranza 14e source problem remains

The approved present server PDF for Carranza periodontology 14e has **105 excerpted PDF pages** (approved SHA `eb2ebe1a682635f9be38b2da3016af6c521d6a5f70052a8acf90841bf3d4ae18`) whereas the legacy map goes to page 1875 and is not SHA-bound. All 20 of the remaining periodontics legacy source quotes were searched against all 105 available pages; zero exact matches. This edition needs its correct complete approved reference or a new evidence-grounded page mapping; do not copy the old map or fabricate an apparent number.

## Next actions

First resolve production I/O and MySQL transaction lock contention. Do not disturb other existing backups, bypass tool safety checks or impersonate deployment owners. Once stable, rerun independent guarded single-item previews using the actual deployed approved code, take and independently verify a fresh complete backup, apply source metadata or atomic source-only insert with unique private receipts, read back exact bank sources and all immutable question/answer/attempt invariants. Complete research-only individual local-passages, printed labels, official scope and SHA evidence for high-confidence additional candidates. The exam is **not closed** merely because an audit package exists.
