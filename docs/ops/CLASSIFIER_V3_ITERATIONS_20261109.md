# Classifier v3 improve→test→diagnose cycles — 2026-11-09

Model: Claude Opus 5.5, single session (no subagents, no other model calls). Read-only:
SELECT-only DB, one verified PDF page in RAM at a time, no text/OCR/index/copies,
no production writes, timer untouched. Machine record: `CLASSIFIER_V3_ITERATIONS_20261109.json`
(metadata only; no question/page text or question IDs).

Protocol: Neville 5e (32 gold, 21 missing) = development set, numeric diagnosis only.
Carranza 13e (15 gold: board/national/residency 5 each; 10 missing) = frozen held-out,
scored only. Gold `pdf_page` is fetched after ranking; rank ≤ 5 fixed. All variants of a
run share one RAM-only page pass; ≤ 2 concurrent scanners.
Command: `python3 -B scripts/classification_v3/benchmark.py <edition> --per-type {8|5} --missing {10|5} --diagnose --variants v3b,c1_bridge[,c2_prox] --out <tmp>`.

| cycle | variant | decision | Neville top1/3/5 (32) | Carranza top1/3/5 (15) | missing w/ candidate N / C |
|---|---|---|---|---|---|
| 0 | v3b (baseline, reproduced) | baseline | 15 / 24 / 25 | 4 / 7 / 8 | 17/21 / 8/10 |
| 1 | c1_bridge | **accepted** | 18 / 26 / 28 | 7 / 9 / 10 | 21/21 / 9/10 |
| 2 | c2_prox | **reverted** | 19 / 26 / 28 | 7 / 9 / 10 | 21/21 / 9/10 |
| 3 | audit adapter (engineering) | accepted | — | — | — |

Per exam type (top1/top3/top5 of gold):

| book | type | v3b | c1_bridge | c2_prox |
|---|---|---|---|---|
| Neville | board (8) | 2/7/7 | 6/8/8 | 6/8/8 |
| Neville | promotion (8) | 4/5/6 | 4/6/7 | 4/6/7 |
| Neville | national (8) | 4/6/6 | 4/6/7 | 5/6/7 |
| Neville | residency (8) | 5/6/6 | 4/6/6 | 4/6/6 |
| Carranza | board (5) | 1/2/3 | 1/2/3 | 1/2/3 |
| Carranza | national (5) | 3/4/4 | 3/3/3 | 3/3/3 |
| Carranza | residency (5) | 0/1/1 | 3/4/4 | 3/4/4 |

Cost per shared pass (both variants together): Neville 983 pages, ~110–115 s wall, ~110 s CPU,
max RSS 23–31 MiB; Carranza 1,991 pages, ~217–220 s wall, ~209–213 s CPU, max RSS 26–30 MiB.

## Cycle 1 — Persian→English bridge (accepted)
Dev-set diagnosis of 17 non-top1 Neville gold: 3 queries had **zero** terms (Persian-only
stems), 1 had zero gold coverage, 3 had only 1–2 terms, 6 had top1 within ±7 pages of gold
(answer spans adjacent pages; symmetric neighbour smoothing cannot flip such pairs, so
it was not tried). Change: `terms.c1_terms` = v3b + ~300 general oral-pathology /
periodontology / histology terms, generic words ×0.5, whole-word matching for ≤4-letter
Persian keys (`سل` ≠ `سلول`, `کام` ≠ `کدام`), collapse of import letter doubling. Official
key keeps the higher weight; no key/voided → choices equal. CLI default now `c1_terms`.
Caveats: glossary written from general domain knowledge including periodontology while
knowing Carranza is the held-out family (its items/pages were not inspected); per-type
regressions remain (Carranza national top3/top5 4→3, Neville residency top1 5→4).
"Missing with candidate" only means some lexical page exists; it is not answer evidence.

## Cycle 2 — proximity re-weighting (reverted, negative trial)
Cause targeted: remaining Carranza misses are pages covering more query terms but
scattered, often hundreds of pages from gold; sparse 1–3-term queries rank gold 27–115.
Trial: multiply score by (0.5 + best 50-token window concept coverage). Result: Carranza
identical to cycle 1 in every type; Neville +1 top1 only. Held-out gain not demonstrated
→ code reverted. The same-pass c1 control reproduced cycle-1 numbers exactly.

## Cycle 3 — real audit adapter / anti-forgery (accepted, read-only)
Findings: a real `fanoos.classification.provenance-audit/1` (from
`audit_private_study_batches.py`) has no auditor or quote field. It has
`mapped[number] = {ref: "<edition>#<chapter>", pdf_page}`, a per-edition PDF `sha256`
and the pinned study/validated/decisions SHA-256s. The old v3 gate accepted a self-written
JSON that declared a different `auditor` string, so a forged audit could pass.
`scripts/classification_v3/audit_adapter.py` now rejects these cases: symlinks, files outside
`classification/reports`, files owned by the producing uid, a wrong format, digest or count,
and any row/page/SHA mismatch. When an applied receipt is supplied, it must have
`audit_sha256` equal to the audit digest, 0 changed questions/answers/choices, and must not be
`manual_reconciliation_required`. `handoff_package` uses this check. `import_authorised` is
always false. Positive fixture: an existing operator-owned independent audit and its applied
receipt verify. The same row with `pdf_page+1` is rejected.

Host operator preconditions (unchanged, NOT available to this worker): run as `fanoosupd`;
fresh verified full backup ≤ 4 h (`BackupManifest::verify`, `database.sql`); live re-export
of the study and hash match; `import_verified_sources.php` dry run; `--apply --backup --receipt`;
independent readback; on `manual_reconciliation_required` stop and reconcile, never retry.

Tests: new `test_classifier_v3_audit_adapter.py` (5), `test_classifier_v3_cycles.py` (6),
updated forged-receipt regression in `test_classifier_v3.py`; reference suite 285/285 OK.

## Outcome / GO-NO-GO
Independently audited new rows **0**, DB committed **0**, readback **0**. These are
retrieval candidates only. No quote or confidence adjudication and no audit run were done, and
this worker cannot act as `fanoosupd`. Top1 on held-out data is 7/15 (47%). Most questions still
need page adjudication, so 50 audited and committed rows per hour, or 3,732 rows in 4–5 days,
has **not** been shown. **NO-GO for scaling.** The next prerequisite is an end-to-end
pilot of one 30–40-question batch through adjudication, independent audit and the `fanoosupd`
importer, with measured wall time.
