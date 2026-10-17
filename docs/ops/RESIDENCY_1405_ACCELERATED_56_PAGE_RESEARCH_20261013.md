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

## Sixteen individually audited, original-question-preserving exact page research cases

A third, stricter passage-local review of the above candidates isolated 17 cases whose original anchor terminology was concentrated on the **actual same approved PDF page** in an ~85-word span and whose legacy printed page label was corroborated by two neighboring labels. Q36 did **not** pass further protected case preconditions; it remains on hold. The other **16** were reconstructed as **unchanged original live published stem, exactly four choices and latest final/amended official answer** in isolated 0600 study-only folders.

For each of the sixteen, an exact **verbatim contiguous approved PDF passage** (not an invented or paraphrased quotation), official 1405 exact edition/chapter scope and SHA-bound chapter run, real PDF index and corroborated book printed page were saved as a decision. `apply_classification.py --pdf-pages` yielded **1 accepted / 0 rejected** per item, and the separate `audit_private_study_batches.py` provenance check yielded **1 accepted / 0 pending** per item with unchanged original question/choice/key. Individual folders exist at `/srv/fanoos/shared/research/classification/recovery-1405-anchored<Q>-20261013/`, each containing original study, decision, validated sitting and independently SHA-pinned audit.

| Q | 1405 official edition | Chapter | Book printed | Real PDF page | Independent provenance audit SHA-256 |
|---:|---|---:|---:|---:|---|
| 68 | Burket oral medicine 13e | 5 | 147 | **167** | `e640242154e093d3a72cc2935cb254f9d8b65c4e63adfad082e0caf0531c357a` |
| 70 | Burket oral medicine 13e | 9 | 306 | **326** | `12d3e9e86ad69e6ce1f8543b0ca5bb3eb1d7812415f7e3779dfefc940ea9dbca` |
| 211 | Craig restorative materials 14e | 4 | 37 | **48** | `00839fd7473ad01ccd5cbec8831810d00885ad5da1d92a85c564970948fcd4b2` |
| 212 | Craig restorative materials 14e | 4 | 40 | **51** | `0bd77c66c664088c6acabd626a3540bc7fef4db8e7e45d40d28deb6d468243db` |
| 219 | Craig restorative materials 14e | 11 | 211 | **222** | `13fa5416211baaea1b44c75b1ac7286a58779e206950597a0d9780912fbb413a` |
| 147 | Malamed local anesthesia 7e | 18 | 339 | **356** | `aaa78a3f02fe5c36078252243529aa2aa38d8193e14cace1885b8d0025dbe4a0` |
| 148 | Malamed local anesthesia 7e | 17 | 319 | **336** | `f982f97247ecda496875acbb6af41441a6c2bffb4af8d32681b1336035c91255` |
| 149 | Malamed local anesthesia 7e | 14 | 256 | **273** | `e451a87af9e6cd822d14e716f79c71a7e58e92d92e8834417460348a77418ec4` |
| 176 | McDonald/Avery pediatric dentistry 11e | 9 | 186 | **197** | `bb35be90952f697ac6b6981c53af9f39e2610127a5c66dcad4f9cfd7512b2245` |
| 180 | McDonald/Avery pediatric dentistry 11e | 14 | 268 | **279** | `1f4a505096558feef4ee0256f82d5cfa67686c8f7e5d72372128f151613e9941` |
| 182 | McDonald/Avery pediatric dentistry 11e | 16 | 336 | **344** | `82e7cd5c930daa3f8653068adbd4953ee12264611bec898970bad6a85f1c6995` |
| 183 | McDonald/Avery pediatric dentistry 11e | 17 | 348 | **356** | `9e8fb3cb6b6e9c0c9f7374685eaeedf303857eb15223e5e99c4cae7737fa3a18` |
| 56 | Neville oral pathology 5e | 15 | 709 | **719** | `0242e011aa779df152985fd1ce705525170c6c41df4be3780606207f17c03d54` |
| 31 | Torabinejad endodontics 6e | 13 | 269 | **276** | `f60df119e8da3f4366d7eb3c0e6f63c0d6d2c1467b55cbe6c6d36151b2b005d1` |
| 39 | Torabinejad endodontics 6e | 21 | 446 | **453** | `6315a97a34e98acfaf86b90869f365761d188dec5c7d8071760559817087e45f` |
| 97 | Zarb complete dentures 13e | 10 | 226 | **240** | `a1af82dc3eb416aa3eb4c751d03c0a2305cde757d54c632fd59e6e772aa0161a` |

The protected inventory is `/srv/fanoos/shared/research/classification/reports/1405-highlocal-17-study-case-inventory-20261013.json`. Q36 is the one research hold, not a validated case.

**Importantly, these are original-question-preserving scientifically grounded study/audit completions, not source metadata production writes.** The old existing AI source anchor may be paraphrased, so the existing-source repair guard will not automatically accept an alternative quote. Do not change stored original anchors, question text, choices, latest official keys, human reviews or exam histories. Any future source-page-only update requires an approved protected plan binding the exact existing source UUID and original anchor SHA, actual page, corroborated printed label, exact new verbatim page quote, official syllabus chapter, current source PDF hash and immutable recent verified backup. Independently inspect safe rollback-preview, apply only through the official approved source-only operator, require unique production receipt and independently read back live question/source fields.

The 16 are additional cases beyond the preceding 11 guarded previews: **27 individual research/audit cases** from these two categories, not 27 production completions. The field-triple production count last independently confirmed for this conversation remains **99**.
