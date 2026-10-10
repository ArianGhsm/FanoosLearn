# Oral-surgery exact reference follow-up — 2026-10-10 (research-only)

## Isolation and existing live cohort

Scope is `residency:oral-surgery` alone. The independently checked W08 package at
`/srv/fanoos/shared/research/classification/parallel/W08` has 160 oral-surgery questions,
20 in each of 1398–1405; 16 have no source, all in **1398**. No questions in
1399–1405 need new sources. Preserve W08 original study, manifests and all original
keys. This commit inserts **no bank source rows** and changes no official answers.

The official 1398 editions are Hupp 6e, Malamed Local Anesthesia 6e, and
Malamed Medical Emergencies 7e. Approved Hupp 6e has **not** been found.
The original W08 Malamed local-anesthesia 6e search for Q112 (3 minutes)
remains inconclusive. Do not substitute Hupp 7e.

## Complete 7e medical-emergencies page-map evidence

The independently approved, private full 2015 Malamed 7e PDF in Fanoos content
storage was examined **in place**, not copied into the public repository.
`pdfinfo` confirmed 561 PDF pages. `pdftotext -layout` inspected chapter
opening headings on the **original PDF pages**. All 31 sequential headings were
observed: 11,25,72,123,135,154,163,171,187,191,196,218,224,242,259,
261,265,291,303,321,324,346,357,394,426,431,450,466,485,489,515.
Front matter is PDF pp. 1–10; chapter 31 ends at PDF p. 531; Appendix starts on
p. 532 and Index appears on p. 544. Adjacent runs are contiguous and cover all
561 PDF pages, with no missing chapter number.

The run map is proposed in `data/bank/reference-chapter-pages.json` on a
**separate surgery-only review branch**. Original extraction and page-text
validation are mandatory before regarding any question decision as accepted.
`reference-texts.json` may retain an older missing-library note, but the
production storage object was independently verified and cannot be replaced
with an invented local path.

## Two textbook-backed source candidates (not applied)

| Exam question | Exact 1398 reference | Chapter | Original PDF page | Textbook corroboration | Status |
|---|---|---:|---:|---|---|
| 123 | `malamed-medical-emergencies@7e` | 3, Preparation | 109 | "Alternative drug. Isoproterenol" under symptomatic bradycardia | Candidate; original validator still required |
| 124 | `malamed-medical-emergencies@7e` | 11, Foreign Body Airway Obstruction | 211 | "underlying esophagus are lesser risks" in the cricothyrotomy explanation | Candidate; original validator still required |

The quoted phrases are short finding aids. Actual decisions must use complete
page-verbatim evidence, exact source-node/chapter, approved official year scope,
valid confidence and original `apply_classification.py` acceptance, with an
independent content/page auditor. These two chapters (3 and 11) are included
in the catalog's official 1398 syllabus. They do not settle Q122, whose
obstructed-airway clinical scenario and official key merit separate review.

### Safe next operations

1. Review this map against the original PDF chapter openings; verify that no
   chapter boundary was inferred from a table of contents or index.
2. In the isolated W08/private work area, use the **unmodified**
   `extract_server_reference.py` against the approved resource after the map
   is available in an isolated checkout. It must confirm the complete 561-page
   book and all chapter runs.
3. Create only source proposals for supported Q123/Q124, run the original
   `apply_classification.py` and an independent all-question immutability
   audit. Keep other questions pending.
4. A separate authorized coordinator alone may review fresh live
   no-source/answer match, original page evidence, verified full backup and
   source-only preview before any atomic production insert. Publishing frozen
   assessment versions requires a full unchanged-content diff.
5. Record verified accepted/applied/assessment/CI/merge/deploy facts
   separately. Never claim this provisional page-map PR published a source.

## Concurrent-work caution

At inspection the updater checkout was at `2411fc1`, while live release was
`5416312` (main ahead of live). This branch does not deploy or modify the
other workstreams. A later operator must independently check current main
CI, live sync, backup and health before any unrelated release action.
