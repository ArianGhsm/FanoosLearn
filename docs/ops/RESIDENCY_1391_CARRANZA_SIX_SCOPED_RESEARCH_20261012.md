# Residency 1391 — six Carranza 12e chapter+PDF-page research candidates

**Date:** 2026-10-12. **Status: RESEARCH-ONLY, NOT IMPORTED.**

Owner-authorized pre-1396 retrospective policy requires a genuine official
reference list from 1397 onwards, an authentic original server PDF, exact
source chapter and *original PDF page*, and a separate later reference year.

Carranza's 1397 validity row does **not** contain structured allowed chapters,
so the production validator correctly rejected its otherwise plausible
chapter-1 pages. The authentic 1398 Carranza Periodontology 12e residency
validity list DOES contain allowed chapter scope. No 1397 official chapter
eligibility may be inferred by guessing or filling `official` rows for 1391.

## Candidate evidence, verified in the original server book

| Exam question | Exact edition | Reference year | Chapter | Original PDF page |
|---:|---|---:|---:|---:|
| 65 | carranza-periodontology@12e | 1398 | 1 | 37 |
| 66 | carranza-periodontology@12e | 1398 | 1 | 36 |
| 67 | carranza-periodontology@12e | 1398 | 1 | 42 |
| 72 | carranza-periodontology@12e | 1398 | 16 | 602 |
| 75 | carranza-periodontology@12e | 1398 | 25 | 713 |
| 83 | carranza-periodontology@12e | 1398 | 45 | 981 |

For all six, research ran exact-original-page quote-fragment matching,
same-book chapter-map boundary checks, verified PDF SHA matching the source map,
and authentic 1398 subject chapter scope; every check passed.

- **Original PDF SHA-256:** `1332e1f92ec1dea1ee99c7382993ce3f191407099d7650ebb97c79b989553263`.
- **Protected study-only sitting:** `/srv/fanoos/shared/research/bank-sittings/1391/periodontics-study.json`.
- **Protected decisions:** `/srv/fanoos/shared/research/classification/decisions/1391-periodontics.json`.
- **Checkpoint:** `/srv/fanoos/shared/research/classification/reports/1391-periodontics-six-scoped-1398-research-20261012.json`; SHA-256 `95789b0cdeccabe80e7cc4579a9e05d6d3c75a45812bb842514f6a4d40e4c0c9`.
- **Versioned policy repair:** [PR #334](https://github.com/ArianGhsm/FanoosLearn/pull/334), passed CI and merged as `01d03db35e4ec684ed4426bfd542543b6b79ce67`; selector now skips unstructured proxy-year validity, so Carranza 1398 is the first eligible scoped validity.

**IMPORTANT:** Production server last checked still ran release
`cd3b7913640dcb6ccdcb471e637e9e3236b89c4e`, which **does not yet
contain** the code fix. Until the versioned selector is deployed, the normal
`apply_classification.py` rejects these citations. Do not bypass the normal
validator, use raw SQL, or claim any of these six as published. After a
verified eligible deployment, re-run ordinary exact-PDF validation, independent
private evidence audit, source-only rollback preview, an independently
verified fresh full production DB backup, guarded source-only apply, and
production readback. Never change the question, choices, answer keys, voided
status, human review or attempt history.

**Baseline before these candidates:** 32 of 220 questions confirmed live
with same-source edition+chapter+positive PDF page+PDF SHA-256; 188 raw
questions still lacking such citations, including three voided questions.
Original 1391 official reference list and independently verified 1391 answer
booklet remain unavailable. These six candidates do not change counts.
