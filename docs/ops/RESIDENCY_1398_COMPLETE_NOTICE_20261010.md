# 1398 dentistry residency: complete source notice and controlled rollout

**Date:** 2026-10-10. **Input:** owner-supplied four-page `Reference-Assistant-Dentistry98-[konkur.in].pdf`, title «لیست منابع آزمون ورودی دستیاری دندانپزشکی سال ۱۳۹۸», SHA-256 `e6409765d78b6fb8ace629f466235c33ac23026a6af3b9e88d5c9c89743dd7d8`. PDF created 2018-10-13 and modified 2018-10-29. It is a `konkur.in` mirror; no Ministry-hosted original was independently authenticated in this run.

The earlier three-page **partial** notice, SHA-256 `f3f304ed79d64eae50d9833eaf5df1eb2d8681acd12937c0665011bcf3b033c5`, supplied **13** official validity rows across **8** clinical subjects. The supplied later four-page notice adds **6** reference rows covering **4** more subjects. Together, **19** official reference-validity rows cover **12** clinical subjects. English remains a general **Upper Intermediate** level and has **no** book/chapter/page assignment. The first 13 source-document fingerprints are preserved.

## Added reference rows and exact announced scopes

| Subject | Canonical edition | Announced coverage |
| --- | --- | --- |
| oral-pathology | `neville-oral-pathology@4e` (2016) | All except chapters 4, 18, 19 |
| prosthodontics | `shillingburg-fixed@4e` (2012) | All except chapters 5, 11, 12 |
| prosthodontics | `mccracken-rpd@12e` (2011) | All chapters |
| prosthodontics | `zarb-edentulous@13e` (2013) | All except chapters 18–22 |
| community-dentistry | `national-oral-health@1394` | Chapters 1–16 |
| dental-materials | `van-noort-materials@4e` | All chapters; full notice prints 2014, canonically identified as 4th edition (4e). |

**Normalization:** printed “Schillingberg” is keyed to `shillingburg-fixed@4e`; the document does not print the National Oral Health edition year, so the existing library's 1394 edition is the canonical mapping and the publication date is not represented as a PDF claim. The original partial notice's Little/Falace 2018 “12th Ed.” is a known printed edition-label error; current catalogue maps it to 9e and retains that correction. Do not invent question page numbers from this notice.

## Build and verification

Source of truth: `docs/research/dental-residency-references-1398.json` (the six new rows carry per-row evidence/source document). The reproducible generator `python3 scripts/import/reference_map_to_catalog.py` creates `data/bank/catalog.json`. The checked result is **192 total validity rows, 19 official 1398 rows, zero provisional 1398 rows**; `python3 -m unittest discover -s tests/import -p test_reference_map_1398.py -v` verifies counts, source provenance and the exact chapter exclusions. The catalog must be committed together with the source/generator; do not regenerate directly in a live immutable release.

## Live application: safety gates

1. Confirm deployed SHA, fresh main, exclusive publication ownership, original source PDF identity, live DB validity counts, existing question/source human review, and current backups. Never overwrite another worker's 1398 source decisions.
2. Apply **only the six 1398 `bank_reference_validity` records** from the new four-page PDF, using the existing `BankImporter` with a *minimal catalog* containing `format: "fanoos.bank.catalog/1"` and precisely those six `validity` items. Omit `subjects`, `exam_types`, `references`, `concepts`, `edition_mappings`; this prevents touching nodes, concept associations, questions, options, answer keys, reviews or attempts. Never run `prune-nodes` or a full catalog import for this task.
3. Verify all six canonical editions already exist in live DB; run `check` and an importer `--dry-run`; independently finish and verify a fresh official full backup, then run the one-time import with the exact pinned minimal payload. The three previously provisional rows must become official and three prosthodontic rows must be newly inserted. Verify resulting 19 official 1398 rows, 0 provisional, six SHA-identified validity rows and no change to `bank_question_sources`, question texts, options, keys or attempts.
4. No residency sitting import, frozen assessment publication or synthetic English reference is needed for this syllabus-only update. Workstream-held classification proposals must **not** auto-publish merely because a missing syllabus is now available: all original-book and page/answer checks remain mandatory.
5. After green applicable CI and PR merge, use the official site updater; inspect live `/health` and updater sync. Record evidence, hashes, backup, DB counts, merge/deploy details and any blockers in protected `/srv/fanoos/shared/research/ops` without leaking private questions/books.

Historical runbooks saying “1398 community/pathology/materials/prosthodontics syllabus missing” are superseded **for reference-list validity only** by this dated, SHA-identified notice. They remain valid for unresolved question-by-question book evidence and old publication receipts.
