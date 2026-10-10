# Oral pathology | Current verified bank and original-book status

**Last independently verified 2026-10-10. Exclusive subject: oral pathology residency 1398–1405.** This document supersedes old *progress/status* statements in earlier dated pathology research notes, but not their immutable original research, official book evidence, provenance or application receipts. This is **not** a declaration of 159/159 scientifically correct chapter+page classification.

## Current production state

- **159 questions / 159 existing attached source records.** Every question has a reference and assigned chapter, but five assigned chapters lie outside the official exam-year syllabus.
- **78 source rows still have no printed page**, rather than the previous 120.
- **Five original Neville fifth-edition incorrect nonempty page placeholders** were actually corrected in the previously documented production transaction.
- **Forty-two Neville fourth-edition NULL-page records** were actually corrected in a **single source-page-only atomic transaction**, on the officially deployed versioned CLI from [PR #230](https://github.com/ArianGhsm/FanoosLearn/pull/230). Prior to apply: read-only bank/answer/syllabus/physical book page checks passed 42/42, fresh independently verified FANOOS full backup `20261010T120645Z-2ac1eec3` held 54 files and was reverified. Private database apply receipt confirms `applied=true`, `count=42`; all other columns and human source rows are immutable.
- Independent readback compared **every one of 159 sources (all columns except the 42 authorized page fields)** and **all 159 bank question stems, choices, media and latest official answers** before/after. **Exactly 42 source.page values changed.** The **six entire previously published exam definition SHA256** values for 1398–1403 were identical (published versions 8, 8, 8, 6, 5, 5). Earlier five fifth-edition updates likewise independently audited the full 1404/1405 frozen assessments.
- As of this verification, the deployed server contained the official restricted source-page repair CLI, with site health HTTP 200.

The two production batch results should not be confused with all-purpose clinical answer certification. No recorded answer key, human-review decision or historical exam was changed.

## Immutable protected research and clean-up

Private root: `/srv/fanoos/shared/research/classification/parallel/oral-pathology-review-20261010`.

| Record | SHA-256 |
| --- | --- |
| Canonical current `README.md` | `a7b9f8fdac8e8c9ac07a341f771d173c117b03b5c07c97d24ced2cec41608548` |
| All-159 postbatch per-question disposition `CURRENT_159_CASE_STATES.json` | `1e07875e15eec1cc87eff091e6f829e8c155134f677ad2d3b65d091ed7e06f4b` |
| Protected 42-source atomic apply receipt `neville4-42-source-page-apply-receipt-20261010.json` | `3cb19e635592f510c9935b721f5dfed4d6e9d2452866c06bace981c4e65c4426` |
| Complete independent postapply 159-source and 6 frozen exam audit `neville4-after42-independent-diff.json` | `81068297c16f5a572433c5d08733e7d3480198e6a405f7acce536aeeddf0bed2` |
| Original book/answer verified 42-case approval evidence `neville4-reviewed-answer-page-candidates-20261010.json` | `06489c524c61e8fe221208117ce9cdc46e57b3d0a18e5f76215815ea1fc60f82` |
| Cleanup archive manifest `history/20261010/MANIFEST.json` | `87f25c23366976bbe8cb78e60b490e6cfd96c4cfcd0741942726b466e5368135` |

**Cleanup completed:** Eight outdated iterative checkpoint/readme documents were relocated (not destroyed) into a private dated, integrity-hashed archive. All original book extractions, clinically relevant research, source/answer snapshots, unique production transaction receipts, full backups, immutable exam hashes and replay scripts retain their previous accessible locations. Never delete the historical trails to manufacture course completion.

## Remaining original-edition clinical approval gates

| Current group | Questions |
| --- | ---: |
| Current 42 applied original-Neville4e source-page corrections | 42 |
| Older fourth-edition sources requiring independent answer-level page work | 72 |
| Fifth-edition existing pages requiring clinical/physical page review (includes earlier five actual page fixes) | 37 |
| Existing chapter outside official exam-year scope | 5 |
| Original-book vs recorded official-key concern, 1398 Q60 and Q64 | 2 |
| Protected human source with NULL page, 1404 Q56 | 1 |
| **Total** | **159** |

The 78 NULL fields are in the 72 fourth-edition fact-review group, two Q60/Q64 concerns, three older fourth-edition excluded-chapter cases, and one human source; all other issues include pages or placeholders.

**Next action cannot be bulk guessing:** independently locate each remaining original-book answer fact in the exact-year book and allowable chapter, print-page corroborate, adjudicate key discrepancies through authorized review, avoid human overrides, then follow guarded source-only publication with fresh full backup, exclusive ownership, CI and complete live + frozen exam integrity readback. The subject is **partially corrected**, not closed.
