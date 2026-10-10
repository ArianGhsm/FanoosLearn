# Dental Materials Residency — Operational Case Closure (2026-10-10)

**Subject:** `residency:dental-materials`, examinations 1398–1405 inclusive, ten questions per year.
**Owner scope:** dental materials only. Do not modify operative/restorative dentistry or any other residency workstream.
**Requested disposition:** The user explicitly declined further human review and requested that this subject's case be closed.

## Closure decision

**Status: `CLOSED_OPERATIONAL_WITH_DOCUMENTED_SCIENTIFIC_EXCEPTIONS`.**

This means the *source-row completion and student assessment publication workstream* is closed. It does **not** mean all 80 historical source attributions have been scientifically authenticated. No automatic relabelling, rewriting or clearing of historical exceptions is permitted solely to satisfy an administrative completion count.

Final fresh production read-only verification:

| Measure | Verified |
|---|---:|
| Questions in materials residency, years 1398–1405 | 80 |
| Questions with at least one recorded source row | 80 |
| Questions lacking a source row | 0 |
| Sources whose edition/chapter falls within catalogued official same-year syllabus | 49 |
| Distinct previously linked questions with an unresolved official-year chapter-scope contradiction | **31** |
| Newly modified source rows in this closure | 0 |

The 31 unresolved questions consist of **30 old machine-attributed source rows** outside the exact officially permitted year/edition chapters and **one pre-existing human-attributed 1404 question 219**, carrying an out-of-scope Craig 14e chapter 13 and a missing page. That one item triggers two machine-audit flags, so the 32 reported flags represent 31 distinct questions. The user's request *waives a new human-review workflow*, not the requirement for truthful provenance or preservation of an existing human decision. The old human decision was neither overridden nor falsely certified.

Annual verified inventory:

| Year | Source-linked | Annual edition/chapter in recorded allowed scope | Historical exceptions |
|---|---:|---:|---:|
| 1398 | 10/10 | 10 | 0 |
| 1399 | 10/10 | 6 | 4 |
| 1400 | 10/10 | 4 | 6 |
| 1401 | 10/10 | 3 | 7 |
| 1402 | 10/10 | 2 | 8 |
| 1403 | 10/10 | 8 | 2 |
| 1404 | 10/10 | 8 | 2 |
| 1405 | 10/10 | 8 | 2 |
| **Total** | **80/80** | **49** | **31** |

**1398 update:** the newly added complete four-page examination source list announces Van Noort fourth edition, all chapters, for dental materials. The corresponding annual validity was imported independently and its published catalog updated. An independent recheck verified original-book chapter/page marks and adjacent printed page labels for **all ten** 1398 original source rows. The 1398 frozen assessment v6 includes ten material source explanations. The 1398 PDF is a user-supplied third-party mirror, not independently authenticated from a Ministry-hosted original; avoid misrepresenting its provenance.

**1399–1401 completion:** eight earlier missing links were individually audited, atomically inserted and republished in three authenticated transactions with unique receipts and independent full production backups: 1399 +4 and assessment v7→v8, 1400 +2 and assessment v5→v6, and 1401 +2 and assessment v5→v6. Entire published assessment snapshots were compared with zero non-explanation changes. A concurrent independent worker later published 1400 v7 with a further source-only explanation update, also audited. Earlier saved exam/attempt history and official answers were preserved. **Never reimport the eight already inserted citations.**

### Why unsupported auto-corrections were not made

The 30 earlier machine citations were matched against exact same-year official alternative book chapters using a protected research-only candidate search. **Zero of the 30 old source anchor quotations matched verbatim within the proposed different authorized textbook/chapter**. Updating just `reference`, `chapter` or `page` while leaving the attribution text would produce a misleading citation. That unsafe operation was not performed. The candidate report is a research aid, not verified scientific proof; mere vocabulary matching does not confirm the published answer. The existing human attribution at 1404 Q219 remains unchanged.

This closes the **operational case and its review-request queue** as requested, with the 31 legacy quality exceptions **preserved as immutable documented findings**. No further human review is scheduled from this case. Future scientific certification can only be recorded separately if verifiable evidence from the exact officially valid reference and chapter becomes available.

## Durable evidence and reproducibility

Protected server work directory: `/srv/fanoos/shared/research/classification/materials-20261010-dedicated/`.

- Closing canonical immutable receipt: `MATERIALS_CASE_CLOSED_WITH_EXCEPTIONS_20261010.json`, SHA-256 `9bddec1a7e7e3c21f9ac1e8cf374379a39c5fd016104aaa82bafdd90406da2b5`.
- Closing fresh production read-only source inventory: `materials-live-final-closure-recheck-20261010.json`, SHA-256 `dac82287a521d714def45aae133588f1ce2a24c3dd40713f400c3ee38837cac5`.
- Latest original 1398 book/page proof: `materials-1398-ten-source-proof-after-new-notice-20261010.json`.
- Research-only 30-candidate shortlist: `materials-30-machine-sources-reference-search-candidates-20261010.json`.
- Anchored-provenance mismatch guard: `materials-30-legacy-anchor-compatibility-safeguard-20261010.json`.
- Read-only reproducible auditor: `audit_live_materials_source_scope.py`.
- Original publication ledger, transaction backups, receipts, freeze audits and merge-deployment record: `docs/ops/RESIDENCY_MATERIALS_CLASSIFICATION_20261010.md`, PR #212 and #222.

**Protection attestation for closure:** no question stem, answer key, option, human-origin source, student attempt, frozen historical exam version, existing source anchor or legacy citation was changed by the closure process. No scientific-validity flag was fabricated. No new human review task was opened.
