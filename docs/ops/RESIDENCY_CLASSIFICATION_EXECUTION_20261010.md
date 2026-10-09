# Residency exact-edition chapter mapping — 2026-10-10 checkpoint

**Owner instruction:** continue chapter-classifying eligible dentistry residency
questions against approved exact official reference editions already available
on the server; do not wait for the remaining unready editions or use a laptop.
**Status:** verified private **research decisions**, not production imports.

Read first:
- `docs/PROJECT_PRINCIPLES.md` — GitHub/server authority, exact-edition rule
- `docs/product/09_CHAPTER_CLASSIFICATION.md` — book evidence and validator
- `docs/ops/RESIDENCY_CLASSIFICATION_EXECUTION_20261009.md` — original 22
  decisions, PDF extraction, and initial research workspace
- `docs/ops/SERVER.md` — protected storage, backup and release protocols

## Live environment and baseline

Checked through authorized SentinelX server access on 2026-10-10:

- GitHub `main`, updater checkout and live release **matched** SHA
  `cd2e864dbed9fa85786ced4d14e8a8dbd824e14a`;
  `scripts/dev/check-sync.sh` returned `in sync`.
- Updater timer `active`; `https://fanooslearn.ir/health` returned HTTP 200.
  The old 2026-10-09 `operator_cancelled` deploy incident is **historical**,
  not a claim the site remains stuck on that earlier release.
- 1,995 residency questions, of which **1,504 chaptered and 491 missing a
  chapter** (unchanged by this private research pass).
- Live reference inventory previously confirmed **27 verified private PDFs
  out of 44 catalog editions**. A PDF is not automatically text-searchable;
  only verified page-marked texts may be used for a decision.
- Source PDF evidence used here:
  `national-oral-health@1394` (330 PDF pages; 24 page-mapped chapters);
  `white-pharoah-radiology@8e` (1,958 pages; 33 mapped chapters).
  PDFs were not copied into a laptop or public repo.

## Work completed, from 22 to 74 verified decisions

Each batch uses an exact dentistry residency exam year, sitting and subject.
Each decision has `edition`, `chapter`, original PDF `page`, confidence
and page-verbatim `evidence`. The regular
`scripts/references/apply_classification.py` returned **0 final rejects**.
References are resolved into the validated source-node form
`<official-edition>#chNN`, with page labels and `origin: ai`.

| Year | Subject | Questions examined | Decisions accepted | Pending |
|---|---|---:|---:|---:|
| 1398 | community-dentistry | 10 | 10 | 0 |
| 1399 | community-dentistry | 10 | 9 | 1 |
| 1400 | community-dentistry | 10 | 5 | 5 |
| 1401 | community-dentistry | 10 | 10 | 0 |
| 1402 | community-dentistry | 10 | 7 | 3 |
| 1403 | community-dentistry | 10 | 9 | 1 |
| 1405 | community-dentistry | 10 | 9 | 1 |
| 1405 | oral-radiology | 20 | 15 | 5 |
| **Total** | | **90** | **74** | **16** |

The earlier 2026-10-09 report held 22 accepted decisions (1403 community 5,
1405 community 9, 1405 radiology 8). This pass produced **52 additional
accepted** decisions: new 1398–1402 community-dentistry batches (41), plus
4 more 1403 community and 7 more 1405 radiology. All passed the original
book-text/page-to-chapter validator.

**No answer key, question, source row, publication status or other production
data was written.** The number of missing production chapters is still 491.

### Integrity and judgment caveats

- **1398 community-dentistry:** the catalog names the exact 1394 national
  reference, whose PDF is present, but its historical announced syllabus
  scope is incomplete (the source announcement's detailed PDF is missing).
  Ten study mappings are *book-evidence validated*, but the exact syllabus
  boundary needs independent confirmation **before production publication**.
  Do not infer that the year had a documented chapters 1–16 scope.
- The national book's extracted two-column Persian text sometimes interleaves
  columns. Search hits alone are insufficient: each selection required the
  actual supporting concept, quoted page text, correct chapter run, and
  existing validator acceptance. Human content review remains appropriate
  before approving a mass import.
- Questions depending on absent figures, ambiguous answer mappings,
  absent exact editions or insufficient page evidence **remain undecided**.
  Do not invent a source, use `none` to hide a search failure, or substitute
  a newer/older edition by default.
- All new sources are `origin=ai`; none replace reviewed human sources.
  Research files came from a **read-only, workspace-scoped production DB
  export** with an intentionally non-importable
  `fanoos.classification.study-only/1` format.
- The new read-only auditor compares every validated question (including
  choices and answer) against its study input after excluding only the
  newly added `sources`; checks accepted question numbers, `ref#ch` nodes,
  exact PDF or mapped printed page, origin/confidence and input/decision/
  output SHA-256. Its report contains **no stems or copyrighted page text**.

## Files and reproducible audit

Protected server root: `/srv/fanoos/shared/research`.

- Inputs: `bank-sittings/<year>/<stem>-study.json`
- Edition texts and provenance: `references/<edition>.txt` and
  `references/<edition>.provenance.json`
- Search terms: `classification/reports/<year>-<stem>-queries.json`
- Decisions: `classification/decisions/<year>-<subject>.json`
- Validator outputs: `classification/sittings/<year>-<stem>-validated.json`
- **Full 8-batch private audit**:
  `classification/reports/residency-audit-20261010.json`
  SHA-256:
  `43ec6f2d23ed1b2c2a4717be47bbf20d1609b603887607f42157df564f64c4b8`.

For any future evaluation, run all decision files through the **original**
`apply_classification.py` first, with `--local` pointed to this protected
root. Then audit the full bank-study integrity and source maps (read-only):

```sh
python3 scripts/references/audit_private_study_batches.py \
  --local=/srv/fanoos/shared/research \
  --batch=1398:community-dentistry:community \
  --batch=1399:community-dentistry:community \
  --batch=1400:community-dentistry:community \
  --batch=1401:community-dentistry:community \
  --batch=1402:community-dentistry:community \
  --batch=1403:community-dentistry:community \
  --batch=1405:community-dentistry:community \
  --batch=1405:oral-radiology:radiology \
  --out=/srv/fanoos/shared/research/classification/reports/residency-audit-20261010.json
```

Expected audit: `batch_count=8`, `questions=90`, `accepted=74`,
`pending=16`, `research_only=true`, with 0 question-content changes.
The auditor refuses to overwrite a differing report under an existing name:
use a new date/suffix when decisions legitimately change. Synthetic regression
tests live in `tests/references/test_audit_private_study_batches.py`;
they include a deliberate answer-change rejection, node-map mismatch and
prohibition of writing a report outside protected storage.

### Verified on-host recovery checkpoints

All the following are protected `fanoosupd` archives in
`/var/backups/fanoos/research/`, each with its adjacent `.sha256`
receipt; archive checksum verification and contents listing succeeded:

- `20261010-1398-community-first-batch.tar.gz`
- `20261010-1399-community-first-batch.tar.gz`
- `20261010-1400-community-first-batch.tar.gz`
- `20261010-1401-community-first-batch.tar.gz`
- `20261010-1402-community-first-batch.tar.gz`
- `20261010-1403-community-batch-v2.tar.gz`
- `20261010-1405-radiology-batch-v2.tar.gz`
- `20261009-1405-community-batch-v2.tar.gz` (prior still-valid batch)
- `20261010-residency-audit-8-batches.tar.gz` (audit JSON only)

These are verified **on-host** snapshots, not a claimed independent offsite
backup. The original 2026-10-09 archives remain historical checkpoints.
No PDF was duplicated, no unrelated workload was touched or restarted.

## Next safe actions

1. For an eligible subject/year beyond the listed batches, verify exact
   official edition in `data/bank/catalog.json`, approved PDF inventory,
   book-text provenance hash and page-to-chapter map. Extract one book only
   when necessary and disk space allows; make a private study-only sitting.
2. Continue the 16 unresolved questions, especially only where exact text
   and original required figures permit a conclusive chapter. Leave
   incomplete/absent-edition references pending; do not block other subjects.
3. Build/review a **source-only audited importer**, independently tested so
   it cannot change question text/options/official answers, protects human
   rows, requires production preflight and **verified full backup**, and
   explicitly verifies `questions_changed=0`. The study-only JSON must
   never be passed to a normal bank sitting importer.
4. Confirm 1398 announced scope before publishing its ten research mappings.
5. Import/publication has **not** occurred; only after approved importer,
   backup and post-import checks should the live chapter backlog be
   decremented. Re-audit the live DB rather than projecting 491−74 as fact.
6. Put new generic rules in `docs/PROJECT_PRINCIPLES.md` or the canonical
   classification runbook, and update this checkpoint with new batches,
   hashes, CI/deploy/import IDs and remaining dependencies.
