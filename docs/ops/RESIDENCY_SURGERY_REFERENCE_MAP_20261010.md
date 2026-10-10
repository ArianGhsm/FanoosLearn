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
| 123 | `malamed-medical-emergencies@7e` | 3, Preparation | 110 | Atropine-refractory bradycardia discussion; alternative drug is isoproterenol (PDF p. 109) | Research-validated; original validator and independent audit passed |
| 124 | `malamed-medical-emergencies@7e` | 11, Foreign Body Airway Obstruction | 211 | "underlying esophagus are lesser risks" in the cricothyrotomy explanation | Research-validated; original validator and independent audit passed |

**Verified 2026-10-10:** original unmodified `extract_server_reference.py`
read the authenticated 561-page approved PDF and created protected page-marked
text with 31 mapped chapters; source SHA-256
`cc2ca3fb90039656483f6f2ddc22b482331613a9ab8889c79407f1e28baa184c`,
text SHA-256 `526864224d2436661bfd2950593cb44927cb4cee780c22b55b9fd3c76949e146`.
The original `apply_classification.py` validated **2 accepted, 0 rejected,
14 undecided** against private exact-edition text. The independent unmodified
`audit_private_study_batches.py` then passed **16 inputs, 2 accepted,
14 pending** with all stems, options and official answers unchanged. Private
new, isolated research package:
`/srv/fanoos/shared/research/classification/parallel/W08/followup-malamed7/`;
audit receipt `classification/reports/1398-surgery-malamed7-audit-20261010.json`.
Original W08 decisions/manifests remain untouched. Do not publish these
research results without the separate source-only production safety gates.

The quoted phrases are finding aids. The 2 validated decisions contain
page-verbatim evidence, official reference/chapter, confidence >=0.95 and
were verified by the independent content/page auditor. Both chapters (3 and 11) are included
in the catalog's official 1398 syllabus. They do not settle Q122, whose
obstructed-airway clinical scenario and official key merit separate review.

### Safe next operations

1. Review this map against the original PDF chapter openings; verify that no
   chapter boundary was inferred from a table of contents or index.
2. Completed in isolated W08 checkout: original text extraction and original
   validator plus independent SHA-pinned study content audit; keep others pending.
3. A separate authorized coordinator alone may review fresh live
   no-source/answer match, original page evidence, verified full backup and
   source-only preview before any atomic production insert. Publishing frozen
   assessment versions requires a full unchanged-content diff.
4. Record verified accepted/applied/assessment/CI/merge/deploy facts
   separately. Never claim this provisional page-map PR published a source.

## Legacy cited sources: live read-only quality inventory

A fresh dentistry-residency `oral-surgery` production read found **144 existing
source rows**, 4 in 1398 and 20 each in 1399–1405. Their edition IDs
all have a matching official year/subject validity row. This is an
**assignment inventory**, not a claim that every historical original-book
quote was independently revalidated.

Quality flags across the 144 rows (categories overlap):

- **9 lack a source page** (1398:4, 1399:4, 1404:1).
- **38 have at least one source/chapter/page confidence below 0.85**.
- **1 lacks its stored anchor quote**.
- **1 has origin `human`** (1404); preserve it, including its missing page,
  unless a separately authorized human review explicitly changes it.
- **8 no-page LA6 rows** (1398 Q125–128; 1399 Q138,145–147) have old
  annotations stating evidence came from **nearest LA7**, though official
  edition is LA6. They need independent exact-LA6 page/quote re-research;
  the existing official-edition identifier alone is **not** proof that
  the precise 6e page was ever checked. Never import nearest 7e evidence
  as exact 6e source.

Protected per-question evidence-preserving database inventory:
`/srv/fanoos/shared/research/classification/parallel/W08/followup-malamed7/classification/reports/1398-1405-existing-surgery-sources-20261010.tsv`
(144 lines, SHA-256
`da42f1490c815a406a9bcff678a55470da2962d5eaca3d17497450811908b8d7`).
It includes no question stems/choices/answers and makes no DB changes.

**Outstanding surgery tasks:** 14 completely unsourced 1398 cases (Hupp6
approved exact PDF still missing, plus independent Malamed evidence/answer
problems); 8 historical LA7-derived no-page annotations requiring exact LA6
reassessment; the 1404 human no-page row for separate owner review; and
the remaining lower-confidence/page-evidence audit queue. Publication of
the two newly validated research sources requires the original
source-only transaction and frozen-assessment gates.

## Concurrent-work caution

At inspection the updater checkout was at `2411fc1`, while live release was
`5416312` (main ahead of live). This branch does not deploy or modify the
other workstreams. A later operator must independently check current main
CI, live sync, backup and health before any unrelated release action.
