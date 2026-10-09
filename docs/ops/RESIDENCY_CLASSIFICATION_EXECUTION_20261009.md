# Residency chapter classification — server-first execution log (2026-10-09)

Status: **started**. Verified study decisions exist on the server; **not imported
into the production bank**. All private question text, answer choices, and
copyrighted reference text remain outside the public repository.

## Baseline measured from the live DB

The query uses `bank_questions` joined through `bank_exam_sittings` to
`bank_exam_types.type_key='residency'`. A question is considered chaptered
only if an associated `bank_question_sources.node_id` is non-null:

| Year | Total | Chaptered | Unchaptered |
|---|---:|---:|---:|
| 1398 | 247 | 90 | 157 |
| 1399 | 249 | 192 | 57 |
| 1400 | 250 | 195 | 55 |
| 1401 | 249 | 197 | 52 |
| 1402 | 250 | 200 | 50 |
| 1403 | 250 | 200 | 50 |
| 1404 | 250 | 230 | 20 |
| 1405 | 250 | 200 | 50 |
| **Total** | **1995** | **1504** | **491** |

Note that the sum of listed chaptered values is **1504**, not 1505.
The historical static report in `docs/product/06_QUESTION_FORMAT.md` is
not a substitute for current DB measurement.

For a reproducible read-only check, via the authorized database identity:

```sql
SELECT s.exam_year, COUNT(*) AS questions,
       SUM(EXISTS(SELECT 1 FROM bank_question_sources x
                  WHERE x.question_id=q.id AND x.node_id IS NOT NULL)) AS chaptered,
       SUM(NOT EXISTS(SELECT 1 FROM bank_question_sources x
                      WHERE x.question_id=q.id AND x.node_id IS NOT NULL)) AS unchaptered
FROM bank_questions q
JOIN bank_exam_sittings s ON s.id=q.sitting_id
JOIN bank_exam_types t ON t.id=s.exam_type_id
WHERE t.type_key='residency'
GROUP BY s.exam_year
ORDER BY s.exam_year;
```

## Reference eligibility and extraction

The server's official `reference-library-inventory.php` reported 27
private, published, verified PDFs out of 44 catalog editions. We started with
`national-oral-health@1394`, the exact official edition for community
dentistry in 1405 (official scope chapters 1–16).

New script: `scripts/references/extract_server_reference.py`. It resolves
the **latest approved verified** PDF for an exact edition through the FANOOS
database, checks object path confinement, byte size, PDF magic and SHA-256,
runs `pdfinfo` and `pdftotext -layout` directly on that protected object,
checks complete contiguous chapter/page runs, and emits a private,
page-marked text plus provenance receipt. No second PDF or laptop is used.

```sh
sudo -u fanoosupd python3 scripts/references/extract_server_reference.py \
  --edition=national-oral-health@1394
sudo -u fanoosupd python3 scripts/references/extract_server_reference.py \
  --edition=national-oral-health@1394 --apply
```

Verified result: 330 PDF pages, 24 mapped chapters, 2,062,557 text bytes.
Original verified PDF SHA-256:
`d1cc93cdc6d567cad554cdb08329914ca39e043bc183c587d637f322da20ae66`.
Private extracted text SHA-256:
`38f85689432568fe37d128fc539f9205916b671af11671668502b6bf39dd7cd9`.
File and receipt:
`/srv/fanoos/shared/research/references/national-oral-health@1394.{txt,provenance.json}`.

**Limitation:** The book's PDF contains two-column Persian text. The
Poppler output occasionally interleaves columns and separates shaped
glyphs. Each chapter decision must therefore be anchored to concrete
page text and the correct concept, never inferred from raw similarity
scores alone. A successful page/quote check does not remove the need for
subject-matter review where a fact is ambiguous.

## First protected question batch (1405/community-dentistry)

New script: `scripts/references/export_server_candidates.php`.
It exports **only questions with no existing source** from the live bank,
including their current stem, choices and newest official answer, into
a private `fanoos.classification.study-only/1` JSON. This format is
deliberately **not importable**. It never overwrites published questions.

```sh
# As authorized fanoosupd, with FANOOS_CONFIG_FILE pointing to
# /etc/fanoos/updater-config.php:
php scripts/references/export_server_candidates.php \
  --year=1405 --subject=community-dentistry \
  --out=/srv/fanoos/shared/research/bank-sittings/1405/community-study.json
python3 scripts/references/classification_batch.py \
  --sitting=/srv/fanoos/shared/research/bank-sittings/1405/community-study.json \
  --subject=community-dentistry \
  --out=/srv/fanoos/shared/research/classification/reports/1405-community-queries.json
python3 scripts/references/find_in_books.py \
  /srv/fanoos/shared/research/classification/reports/1405-community-queries.json \
  --local=/srv/fanoos/shared/research --top 2
python3 scripts/references/apply_classification.py \
  --sitting=/srv/fanoos/shared/research/bank-sittings/1405/community-study.json \
  --decisions=/srv/fanoos/shared/research/classification/decisions/1405-community-dentistry.json \
  --out=/srv/fanoos/shared/research/classification/sittings/1405-community-validated.json \
  --local=/srv/fanoos/shared/research
```

Input study file SHA-256:
`8020c2e8a23508dd8b0937bd8e6eeb533dcc545ffd3d82f34a658211530302f8`.

Nine real candidate decisions passed the **original book-evidence validator**:
- Q221 chapter 2 PDF p33
- Q222 chapter 4 PDF p57
- Q223 chapter 5 PDF p67
- Q224 chapter 7 PDF p101
- Q225 chapter 9 PDF p114
- Q226 chapter 10 PDF p150
- Q227 chapter 12 PDF p169
- Q228 chapter 13 PDF p187
- Q230 chapter 16 PDF p222

**9 accepted, 0 rejected, 1 undecided** (Q229). The
undecided question remains untouched until specific evidence is found.
Decisions file SHA-256:
`007876757f56ae64244189136492e7e60cadd6836c9c7bd27f44a9fd652431dd`.
Validated study sitting SHA-256:
`d2e9974aebb64619bcdff770be1ee61d428487e5547a9e5175ae3b05aa6a97a7`.

## Private checkpoint backup

A local archive of the six private study/text/decision/report artifacts was
created and verified without duplicating the PDF. The initial eight-decision
archive is retained, and the updated nine-decision checkpoint is:

`/var/backups/fanoos/research/20261009-1405-community-batch-v2.tar.gz`

With its adjacent `.sha256` receipt. Archive size ~458 KB; verification
`sha256sum -c` succeeded and `tar -tzf` listed six members. Permissions
are `0600` under `fanoosupd`. This is an **on-host recovery copy only**;
verified independent/offsite backup remains a separate task.

## Deployment status and safety blocker

The previously merged operating-policy commit `09bd78e` had green CI and
was present in the updater checkout, while the running release remained
`57447fb`. A verified existing production backup was confirmed
(81 files). The free disk space had improved to ~6.6 GB at preflight.

Authorized deploy request
`01a121ec-b0c1-7fdd-9943-e733ccdac0b9` was queued using
`scripts/ops/request-deployment.php`. The updater's regular timer was
already **inactive** before the request, so the official service was
invoked **once**, not re-enabled. The request entered `BACKUP`, but the
service was **terminated by SIGTERM** at approximately
2026-10-09 18:34 UTC. The cause was not established by its journal; the
request still read `BACKUP` afterward and the live release was unchanged.
**No manual release edit, import, or publishing was attempted.** Do not
retrigger until the incomplete request and service termination have been
investigated; do not delete protected backups to force an update.

## Next reproducible steps

1. Review the new extraction/export scripts and tests in this PR; green CI,
   then merge to GitHub. Research scripts can run from an isolated checkout;
   production application deployment is a separate action.
2. Diagnose the stalled deploy request/lease and SIGTERM, confirm backup
   reserve and safe restore, and deploy green main only via the updater.
3. Build a reviewed, **source-only** audit/import process that updates
   `bank_question_sources` for exact question IDs without reconstructing or
   changing stems, options, keys or publication history. The study-only
   sitting must never be fed to `import-bank.php`.
4. Take and verify the required production backup, use a no-op/zero-text-change
   dry run, protect reviewed human rows, then import the accepted decisions;
   only afterward remeasure the DB and republish as appropriate.
5. Continue Q229 only with specific original-book support.
   For next years, skip absent official editions; never guess a nearest.
6. Set up tested independent/offsite retention for private research results,
   and update this log with actual audit IDs, import counts and post-state.
