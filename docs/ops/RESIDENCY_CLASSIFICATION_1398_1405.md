# Residency 1398–1405 chapter classification — consolidated record

**Status (owner, 2026-10-11): parked.** Residency 1398–1405 has its chapter
sources; the owner moved on to importing the remaining residency, board,
promotion and national questions. This page replaces the 40-odd per-batch
reports written between 2026-10-09 and 2026-10-10; they and the one-off page
operators are kept in Git history (see the end of this page).

## Live state (database read-back, 2026-10-11)

Residency, all published sittings 1398–1405. *Sourced*: the question has at
least one `bank_question_sources` row. *Paged*: one of them has a page.
*Human*: one of them is `origin=human`.

| Subject | Questions | Sourced | Paged | Human |
|---|---:|---:|---:|---:|
| community-dentistry | 80 | 80 | 70 | 10 |
| dental-materials | 80 | 80 | 79 | 1 |
| endodontics | 159 | 159 | 159 | 2 |
| english | 159 | 0 | 0 | 0 |
| operative-dentistry | 159 | 155 | 149 | 6 |
| oral-medicine | 160 | 159 | 59 | 4 |
| oral-pathology | 159 | 159 | 134 | 1 |
| oral-radiology | 160 | 81 | 61 | 20 |
| oral-surgery | 160 | 144 | 135 | 1 |
| orthodontics | 159 | 159 | 159 | 2 |
| pediatric-dentistry | 160 | 138 | 75 | 5 |
| periodontics | 160 | 144 | 55 | 0 |
| prosthodontics | 240 | 239 | 225 | 6 |
| **Total** | **1,995** | **1,697** | **1,360** | **58** |

English is citation-exempt by the owner's decision (PROJECT_PRINCIPLES §3A).
The `page` column holds printed-page labels and, where none was proven,
`pdf N` PDF-page values.

## Known open items (from the last per-subject reports)

- **Oral pathology:** 25 printed-page fields still empty; 15 suspected
  conflicts between the recorded final key and the original Neville text
  (1398 Q60, 62–64, 66–68, 70, 74, 76–79; 1399 Q63; 1404 Q48) — never change a
  recorded key through classification; they need the authentic year's answer
  correction and a person's decision.
- **Prosthodontics 1398:** held questions with key conflicts or no exact
  original-edition evidence (W06 holds).
- **Oral surgery 1398:** 16 unsourced; the official Hupp 6e is not available
  as a complete authenticated book, Hupp 7e findings are research only.
- **Oral radiology 1398:** White & Pharoah 7e absent.
- **Periodontics:** page fields missing for most 1398 rows (Carranza 12e/13e
  page evidence incomplete).
- Every count above is a snapshot; read the database before acting.

## How it was done (still binding for new work)

- Method: `docs/product/09_CHAPTER_CLASSIFICATION.md`; PDF pages are read
  directly from the verified PDF (`docs/ops/PDF_REFERENCE_SOURCE_POLICY_20261010.md`).
- Publication: source-only, `docs/ops/SOURCE_ONLY_PUBLICATION_20261010.md`
  (`scripts/references/import_verified_sources.php`, preview → verified backup
  → apply with a unique receipt → `audit_published_assessment.php` → publish).
- Parallel research by subject: `docs/ops/RESIDENCY_PARALLEL_WORKSTREAMS.md`.
- Private evidence (decisions, receipts, audits, worker handoffs) stays on the
  server in `/srv/fanoos/shared/research/` and its archives in
  `/var/backups/fanoos/research/`; never in Git.

## What was removed from the working tree, and how to read it

Removed on 2026-10-11 (all present at commit `cd64979df28f60c2f480559220073ec84871c550`;
read one with `git show cd64979:<path>`):

- per-batch reports `docs/ops/RESIDENCY_*_2026100*.md` (except the 1398
  notice, the English decision and the parallel-workstream protocol),
  `docs/ops/ORTHODONTICS_SOURCE_AUDIT_20261010.md`,
  `docs/ops/SERVER_FIRST_TRANSITION_20261009.md`, `docs/ops/data/`;
- one-off production page operators, each already applied:
  `scripts/references/complete_*.php`, `correct_*.php`,
  `build_periodontics_14e_topic_overlay.py`, and the tests that only
  exercised them.
