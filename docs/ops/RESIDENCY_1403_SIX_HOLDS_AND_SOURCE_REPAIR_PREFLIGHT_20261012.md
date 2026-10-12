# Residency 1403 — unresolved six, original-PDF source repair eligibility (2026-10-12)

## Mandatory invariant
A classification is complete **only** if **original 1403 official edition + official in-scope chapter + physical PDF page + SHA of that very PDF** are confirmed together. A chapter from a non-syllabus book section is not an acceptable placeholder. Never change questions, four choices, official answer keys, human decisions, frozen assessment or attempt records. Printed book pagination is not PDF pagination.

## Checkpoint and current bank
The existing reproducible and independently audited 153 research pairs are in `docs/ops/RESIDENCY_1403_153_VERIFIED_ORIGINAL_PDF_PAGES_20261012.md` (PR #348 merged after green CI). Fresh live `fanoos_prod` read-only query found **250 questions, 71 completed paired source rows**; **153 research-only independently audited** clinical pairs not yet applied; **20 English with official final answer, exempt**; **6 science holds**. The site health endpoint was OK, release `cd3b7913640dcb6ccdcb471e637e9e3236b89c4e` (behind the map-fixing merged GitHub source). **No bank source rows were mutated; no assessments republished; no deploy in this activity**.

### Six scientifically held questions

| Q | Topic | Why an actual official 1403 chapter + physical PDF page is not yet valid |
|---|---|---|
| 61 | Famciclovir and prevention of postherpetic neuralgia | Prior Burket13e ch3 source is excluded in 1403; available ch10 p408 mentions postherpetic neuralgia only incidentally, not the antiviral benefit |
| 62 | Behçet articular swelling versus RA | Prior Burket13e ch3 source excluded; searches across allowed Burket13e and Little/Falace9e yielded no answer-specific Behçet comparison |
| 63 | Thymoma and paraneoplastic pemphigus | Prior Burket13e ch3 source excluded; allowed ch7 p269 mentions the diagnosis in a different clinical setting; does not prove thymoma/vesiculobullous scenario |
| 68 | Saliva suction collection / sialometry | Prior Burket13e ch9 source excluded; allowed chapters had no specific whole-saliva suction method proof |
| 69 | Sialolith etiology and exclusion of viral infection | Prior Burket13e ch9 source excluded; allowed Little/Falace9e ch16 p323 mentions a medication causing sialoliths but does not prove the choices/viral exclusion |
| 127 | Irrigation safety with artificial/prosthetic heart valve | Original allowed Carranza13e ch51 p1185 covers irrigation, biofilm, and bacteremia, but did not establish the **prosthetic-valve-specific** contraindication. Old source used nearest nonofficial 14e, disallowed as physical PDF evidence |

Original year official syllabus scope: Burket13e ch4–7 plus ch10 with restrictions; Little/Falace9e ch2/3/4/12/14/16/17/24, dental management only; Carranza13e limited enumerated chapters. A read-only focused original-PDF scan `/srv/fanoos/shared/research/tmp/1403-source-page-expansion-20261011/deep_six_official_scope_search.py` scanned only chapters allowed by the official 1403 catalog for those books. Search hits are candidates, **not** verified answer-specific evidence and are **not** entered as chapter assignments.

## Exact original-PDF page AND historical source safety — read-only preflight

Receipt `/srv/fanoos/shared/research/tmp/1403-source-page-expansion-20261011/residency1403-source-only-metadata-repair-eligibility-20261012.json`; SHA-256 `fe98bd196dc50253a1ac2560f35b3c81915758dc593226977e1ee70c9d7c1f8a`. Generator `audit_existing_source_metadata_eligibility.py`. For **all 153** independently audited research questions, each original existing DB source was independently checked for original/reference matching and whether its **old** anchor was verbatim on the new physical PDF page. Counts (mutually exclusive, no production changes):

| Reason / outcome | Questions |
|---|---:|
| Old anchor fails verbatim exact original PDF page proof | 95 |
| Same approved edition/chapter, old anchor on page and printed labels usable — candidate **only** | 18 |
| Existing edition or chapter differs from audited original official citation | 7 |
| Existing book printed-page label missing | 24 |
| No single eligible AI/unreviewed current source (includes originally unsourced questions) | 9 |
| **Total independently audited research questions** | **153** |

The candidate 18 were subjected to a stricter *independent physical page-label plus at least two adjacent matched printed labels* audit using original server PDFs. Receipt `residency1403-18-original-PDF-printed-label-corroboration-20261012.json`; SHA-256 `d9256da9ab86b4816de7db04d93504266de2181cb0f6e10b46c685b2ed0de7f8`.

- **15 passed printed-neighbour test**: Q30, Q31, Q66, Q71, Q93, Q94, Q95, Q96, Q97, Q107, Q133, Q146, Q149, Q178, Q179. This means only that their existing AI citation/chapter, source quote and book-printed label can safely be considered for a *pdf_page/pdf_sha256-only* repair, subject to the actual guarded transaction/dry-run/backup/deploy gates.
- **3 failed**: Q186, Q188, Q216; do NOT infer their PDF labels or call them repairable from the old printed page.

Private protected 1403 proposal files for those 15 are under `/srv/fanoos/shared/research/classification/reports/residency1403-verified-page-metadata-preview-{endodontics,oral-medicine,oral-surgery,pediatric-dentistry,prosthodontics}-20261012.json`; prosthodontics divided into separately pinned Zarb one-question and McCracken five-question research proposals (files `residency1403-page-repair-{zarb,mccracken}-20261012.json`). **These are neither approved apply manifests nor proof of a DB change.** No source field was modified.

### Official application-specific rollback preview

The pre-existing strict, source-metadata-only `scripts/references/repair_verified_source_pages.php` was used with a scoped 1403 workspace and the original approved reader. First Q30 evidence needed an *unhyphenated fragment on one actual PDF line* rather than an extract joined across printed line wraps. The original book page was independently checked and only the plan's **short new proof string** was adjusted; the legacy bank anchor remained unchanged. The strict official CLI then returned **`mode=rollback-preview`, `count=2`, 0 question/answer/choice changes** for **Q30 Torabinejad6e ch12 PDF p248** and **Q31 Torabinejad6e ch16 PDF p367**. Protected plan SHA-256 `7ea72a5ec8e5003be28f812809a8af2d54774c0de05d3a01115a0ce3c8493155`. It internally attempted only `pdf_page,pdf_sha256` UPDATE and rolled the DB transaction back. An attempted further grouped preview was blocked by platform tool safety controls and **must not be represented as run or successful**. A live read-back still gave **71** completed paired sources out of 250 total. Site health remained OK.

### Verifiable protected recovery artifact (research only)

The original-search scripts, source-eligibility audit and 18-page printed-neighbour audit, alongside all seven draft source-only strict-preview JSON plans, are preserved in:

`/var/backups/fanoos/research/20261012-residency1403-source-repair-evidence-15-eligibility.tar.gz`

Archive SHA-256: `588185570d2a5eb8378579bc8bfe224f58b79caf271550c0247efc0f3e60acc4`. Archive listing validation passed (`tar -tzf`) and re-extracting the 18-item neighbour-evidence receipt reproduced SHA-256 `d9256da9ab86b4816de7db04d93504266de2181cb0f6e10b46c685b2ed0de7f8`. It contains no original protected PDF files and **is not a production database backup**.

## Mandatory remaining execution

The server runtime release lags Github merged official-original SHA-bound McDonald11e, McCracken13e and Phillips13e map changes (PR #339 and #346). Thus no strict live map check for these editions should be treated as complete until matching audited code is actually deployed.

Before actual publication: identify authorized coordination lock / concurrent worker state; create a **fresh, complete, independently verified and restorable** official database backup; ensure deployed runtime carries green CI matching reference maps; run exact source-only guarded plan on eligible rows (not all 153 blindly) with no human-reviewed source overwrite; separately design/test explicit research-proof-bound source/edition/chapter repair for mismatched legacy citations; verify all immutable question/choice/answer/human/assessment/attempt fields before and after; perform production row readback, and if appropriate safely republish frozen assessments with own audits and site-health verification. Failed or out-of-syllabus six remain held without fabricated mapping.

**This checkpoint is a scientific audit and rollback preview only. Do not mark 1403 production classification complete.**
