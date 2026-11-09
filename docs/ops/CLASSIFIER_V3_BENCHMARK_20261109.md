# Classifier v3 blind retrieval benchmark — 2026-11-09

Model: Claude Opus 5.5. Read-only: SELECT-only DB access, one-page-at-a-time verified PDF reads, no OCR, no text cache, no live writes.

**Correction:** The first run omitted questions without any source rows because the sampling query wrongly started from the sources table. Use the corrected rerun below for missing-question counts.

## Command
`python3 -B scripts/classification_v3/benchmark.py neville-oral-pathology@5e --per-type 8 --missing 4 --out /srv/fanoos/shared/research/tmp/<op>/agg.json`
(The scratch directory is deleted by a trap. The aggregate is copied to `CLASSIFIER_V3_BENCHMARK_20261109.json`.)

## Blind design
Queries come only from the stem, the choices and the official key (`terms.deterministic_terms`). Gold `pdf_page` is fetched in a separate query after the scan and is used only for scoring. The sample is deterministic: `ORDER BY SHA2(question_id)`, 8 per exam type, taken from rows with same-row `node_id + pdf_page>0 + pdf_sha256`.

## Results (neville-oral-pathology@5e, 983 pages)
| type | gold | top1 | top3 | top5 | non-gold in top5 |
|---|---|---|---|---|---|
| board | 8 | 2 | 6 | 6 | 34 |
| promotion | 8 | 4 | 5 | 5 | 24 |
| national | 8 | 3 | 5 | 6 | 21 |
| residency | 8 | 5 | 5 | 5 | 16 |
| **total** | **32** | **14 (44%)** | **21 (66%)** | **22 (69%)** | 95 |

Scan: 983 pages in 106.7 s (9.2 pages/s). Wall time 108.4 s. Parent CPU 4.1 s (page extraction CPU runs outside the parent and is not counted). Max RSS 21 MiB. 130 pages were flagged as noise.

## Not measured / blockers
- **Missing questions: 0.** For this edition, no rows of these four types have an empty `pdf_page`, so missing-question recall was not tested.
- Only 1 book was tested. The `bank_reference_validity` eligibility cross-check, the negative controls (wrong SHA/page/quote) and the check of the importer boundary (`import_verified_sources.php`) were not run in this session.
- **Verified and committed throughput: 0, not measured.** Candidate pages are not classifications.
- Unit tests: 14/14 OK (`python3 -B -m unittest tests.references.test_classifier_v3`).

## GO/NO-GO
**NO-GO.** The target is 3,732 incomplete rows at ≥39/31 verified+committed per hour (margin ≥50/hr). The measured verified rate is 0. top1 = 44% means most questions still need manual page adjudication.

Bottlenecks, by impact:
1. No independent audit or importer-accepted package has been shown end to end.
2. Ranking precision is low (top1 44%; about 3 non-gold pages in each top5).
3. Scanning takes about 100 s per book. This is acceptable when amortized over a batch.

## Corrected real missing-source benchmark

The revised sample starts from live bank_questions, checks same-type/year/subject exact official reference validity, and excludes questions already having a complete atomic source. It does NOT infer proxy eligibility. No raw question or PDF text was persisted.

| Exam type | Gold | Top1 | Top5 | Missing eligible | Missing with candidate |
|---|---:|---:|---:|---:|---:|
| board | 8 | 2 | 6 | 10 | 7 |
| promotion | 8 | 4 | 5 | 10 | 7 |
| national | 8 | 3 | 6 | 1 | 1 |
| residency | 8 | 5 | 5 | 0 | 0 |
| **Total** | **32** | **14** | **22** | **21** | **15** |

Scanned 983 physical PDF pages in 106.09 s, 9.27 pages/s; wall 107.27 s; parent max RSS 21.5 MiB. 53 total question queries.

Candidate present means a matching-ranked page exists, not that it correctly answers the question. Six missing questions had no matching candidate. Gold Top5 remains 22/32. Independent audit acceptance and production source-only import/readback are still ZERO from this run. Therefore NO-GO; required fully committed throughput not established.

Corrected aggregate: docs/ops/CLASSIFIER_V3_BENCHMARK_20261109_CORRECTED.json. Added two regression tests for missing-source selection; now 16/16 pass.
