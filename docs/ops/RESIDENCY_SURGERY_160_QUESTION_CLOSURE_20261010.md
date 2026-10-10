# Oral surgery 1398–1405 — completed research dossier; official publication pending

**Date:** 2026-10-10. **Subject:** `oral-surgery` only; residency exam, round 1. **Status:** `RESEARCH_CLOSED_PRODUCTION_OPEN`. This is not a published-bank completion receipt.

## Full independent live bank audit

| Year | Original surgery questions | With existing published source | Still source-free on live site |
| --- | ---: | ---: | ---: |
| 1398 | 20 | 4 | 16 |
| 1399 | 20 | 20 | 0 |
| 1400 | 20 | 20 | 0 |
| 1401 | 20 | 20 | 0 |
| 1402 | 20 | 20 | 0 |
| 1403 | 20 | 20 | 0 |
| 1404 | 20 | 20 | 0 |
| 1405 | 20 | 20 | 0 |
| **Total** | **160** | **144** | **16** |

A **private machine-readable 160-question dossier** was built *only from the current live official-source joins and separately validated research notes*, with unique (year, question number) identities:
- **144 original live source links** including original official book/edition, chapter, stored page, origin, minimum confidence, hashed evidence anchor; not modified;
- **14 original 1398 source-free questions**: `hupp-oral-surgery@7e` **cross-edition research-only** chapter plus PDF-page pointers, for questions 110–122, 129;
- **2 original 1398 source-free questions**: exact `malamed-medical-emergencies@7e` with independent private-study acceptance, Q123 ch3 PDF110 and Q124 ch11 PDF211; still **not** on the live bank.

Complete private research files and scripts: `/srv/fanoos/shared/research/classification/parallel/W08/hupp7-research-20261010/`. Dossier: `all-160-surgery-research-dossier.json` and `.csv`; independent validator `final_160_dossier_audit.py`, receipt `final-160-research-audit.json`. Original 1398 study SHA-256 `81133cf5ca2f7a532cbdc1f4ee8fbc6bf83715c2083eda4a5454b75bafc0814f`. The dossier SHA-256 at the time of audit: `83d527f3cc0620cca95cd21e61ec7b0ddbfce884371aca758307ff62a4cb8b63`.

The independent final audit passed **160 unique study topic records, 20/year; zero overlapping production/research question IDs**, 14 cross-edition chapter/page-map memberships, two exact-edition accepted cases, and eight additional precise Malamed6 research PDF-page candidates. No question content, options, answers, human overrides, attempts, historical source rows or assessment snapshots changed.

## Crucial scientific distinction: Hupp seventh edition is not the official 1398 sixth edition

User accepted Hupp7 for *research-based topic coverage*. The year-specific announced reference for 1398 remains Hupp **6e**. A 7e citation cannot be represented as a formally verified official 6e page/chapter without the original Hupp6 or an authenticated exam syllabus amendment. The 14 mapped topics are therefore **labelled provisional cross-edition research**, not source publication permissions. See [per-question cross-edition table](RESIDENCY_SURGERY_HUPP7_RESEARCH_20261010.md).

**1398 Hupp7 clinical and official-key discrepancies:** 110, 111, 112, 115, 119, 120, 121, 129 (8). Other 1398 Hupp7 cases: 113 and 116 lack adequate key support, 114 has a printed numeric-threshold ambiguity, 122 requires obstructed-airway scenario review. The keyed choice can be consistent for 117 and 118. All old official answer rows are **unchanged**. The original question statements themselves include some errors and cannot be repaired under the source-only classification mandate.

## Eight preexisting Malamed Local Anesthesia 6e links: exact-edition evidence found

Eight live source rows cite official Malamed LA6 but historically contain **LA7-nearest-edition evidence** and **NULL pages**. All have now been cross-checked against the **actual LA6** searchable reference; true PDF-page candidate and correct chapter are recorded *privately*, not written into the live source row:

| Exam year, question | LA6 chapter | Exact LA6 PDF candidate | Academic-review disposition |
| --- | ---: | ---: | --- |
| 1398 Q125 | 1 | 37 | Key differs: book identifies perineurium, not epineurium, as principal diffusion barrier |
| 1398 Q126 | 14 | 255 | Key differs: successful Vazirani–Akinosi block in bifid mandibular canals is an advantage |
| 1398 Q127 | 6 | 111 | Key differs: needle **gauge** affects aspiration, whereas key selects length |
| 1398 Q128 | 13 | 240 | Key differs: diplopia from abducens nerve block; optic nerve block instead causes transient loss of vision |
| 1399 Q138 | 13 | 237 | Book supports superior/medial target of maxillary nerve block relative to PSA |
| 1399 Q145 | 6 | 111 | Book supports needle gauge and aspiration reliability |
| 1399 Q146 | 1 | 41 | General protein-binding principle found; direct exact drug ranking still unverified |
| 1399 Q147 | 14 | 259 | Vazirani–Akinosi section located; no-bone-contact claim requires final quote review |

**Four 1398 LA6 key conflicts** are separate from the eight Hupp7 cross-edition discrepancies, so there are **12 flagged answer-key conflicts**. Record these as scientific-review issues; do **not** change the official keys, answer history, human decisions, choices or text.

## Remaining quality debt and production gate

- Existing source records: **9 missing stored pages**, including the eight LA6 records and one protected human-source record; **38 records have a confidence value below 0.85**.
- Hupp7 actual-text structural page audit for **110 existing live sources**: 77 printed-to-PDF +14 possible mappings, 32 ambiguous overlapping interpretations and one explicit valid PDF page; no chapter conflict was detected in this mechanical page-map test. Separate token overlap: 107 high, 3 medium (1405 Q139/Q140/Q146), **not equivalent to verifying the quote or original official answer**.
- The unchanged *old* W08 verifier checks historical hashes against a **moving** `/srv/fanoos/current` release and now reports three shared authority-file drifts. Its **original 27-file SHA manifest still passes 27/27**. The new research package `SHA256SUMS` passes **26/26** files, manifest SHA-256 `f2c2176171d77958fe13a8701199ecf04030c82a8e8c17b1d6e3b909ce15fb90`.
- A site `/health` check returned OK with deployed release `1178d9b80fb1cc1a95746f219c9c5a32fdd1bbe8` at the observed time; subsequent concurrent deploys may change that SHA.
- No production source insert, edit, assessment republish, Git merge or site deploy was performed in this research phase. [Draft surgery map PR #203](https://github.com/ArianGhsm/FanoosLearn/pull/203) remains open. Latest-head CI success is not confirmed, so **do not merge**.

### Exact gate before claiming official course closed

A responsible single coordinator must verify Hupp6 original or an authenticated official replacement, resolve questionable answer/text cases, verify the exact Malamed7 special 1398 publication contract (current production writer disallows 1398 oral-surgery, allowing only independently approved endodontics), freeze and re-audit original question identities, obtain exclusive writer permission, run preview with official original edition, take and independently verify a complete fresh backup, source-only apply under the authorized service account, check site DB and all historical data invariants, republish the frozen assessment with full old/new unchanged-content diff, pass current-head CI, merge, use official updater, confirm health and record final immutable receipt.

**Research coverage may now be treated as complete (160/160); official and student-facing published chapter-classification is 144/160 and cannot be closed yet.**


## Private final recovery archive and runnable tests

On the authorized server the complete **26-file** surgery-research package, its SHA manifest and reproduction scripts were archived as `/srv/fanoos/shared/research/classification/parallel/W08/reports/W08-surgery-160-research-final-20261010.tar.gz`, SHA-256 `c1a95802edbae510c22816d6c38e72cffd056e7d76a701512650fdde77bfb54f`. Independent in-memory archive verification checked every 26 payload SHA against the enclosed manifest. This private archive excludes the copyrighted original book PDFs and depends on the retained original W08 reference library for full reruns.

The original reference/unit suite was rerun from the pinned surgery branch checkout with `python3 -m unittest discover -s tests/references -v`: **22/22 passed**. The private final 160-question integrity audit likewise passed. These on-host checks **do not** replace latest GitHub-PR CI, production publisher approval or publication tests.
