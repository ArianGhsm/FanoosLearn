> **Updated verified production checkpoint, 2026-10-10 UTC10:28.** The original 2026-10-10 research-only snapshot below is historical for 1398 source availability: exact Carranza12e became ready, and two validated question source rows were subsequently published. The following new section is the current result; do not reuse the earlier unsourced count or "12e pending" state as live.

## Verified periodontics production completion checkpoint (1398 partial; subject remains open)

Latest production query and original-book audits establish **160 periodontics residency questions (1398–1405), 142 existing source rows, 18 raw unsourced questions**, and no human-reviewed source overrides. This must not be called 160 exact page-verified records.

### 1398 official Carranza 12e — two sources published

- Approved original 12e private PDF: 1,766 pages, 89 original printed chapter headings verified against corrected page ranges. PDF object SHA-256 `1332e1f92ec1dea1ee99c7382993ce3f191407099d7650ebb97c79b989553263`; original-book private page-marked text SHA-256 `9b67b16cfaa080067bc9f03e550daa3fd71501855658922151fb2f90518f3e25`, canonical protected path `/srv/fanoos/shared/research/references/carranza-periodontology@12e.txt`. The private boundary audit SHA-256 is `2953344c8bc3dfdf42703ce798543c404e6ae52006991127b9dde1030f69160a`. Correction/manifest/tests: [PR #215](https://github.com/ArianGhsm/FanoosLearn/pull/215), merged SHA `652d496a21182568e549a157c5378a9f1d21db21`, green CI.
- Exact edition + year/subject-specific source-only publication gates and regression tests: [PR #217](https://github.com/ArianGhsm/FanoosLearn/pull/217), merged SHA `4253bf4c41a19896aa27b5cea7336ff0f8397b35`, CI workflow run `38044496861` green. Official updater request `01a12556-d879-757c-9582-1d2679445673` **SUCCEEDED**; both live `/health` and checkout matched `4253bf4c41a19896aa27b5cea7336ff0f8397b35`.
- Pre-release study, re-exported from live source-free DB, was byte/JSON identical to prior W03 study for all 20 periodontics questions. Primary original-book `apply_classification.py`: **2 accepted, 0 rejected, 18 undecided**. Independent audit `classification/reports/1398-periodontics-two-audit-20261010.json` confirms unchanged stems/choices/official answer and exact source page/quote/node.
- Official full backup `/var/backups/fanoos/20261010T102642Z-77b8d9ac` passed `scripts/ops/verify-backup.php` (54 files). Exact source-only PHP preview then atomic apply inserted **only 2** source rows: Q141 Carranza12e ch15, original PDF p595, printed page 230; Q144 Carranza12e ch45, PDF p981, printed page 487. Origin `ai`, official scope passed, both original printed page corroborations passed. Production receipt `/srv/fanoos/shared/research/classification/reports/1398-periodontics-two-source-publish-20261010.json`, SHA-256 `7823991b0e746b7db49ce911106fdd8d73826ce267ac09ede43f7ca0c0650867`, reports `applied=true`, `source_rows=2`, `questions_changed=choices_changed=answers_changed=0`. DB post-readback showed precisely Q141 and Q144 were added and **18** other perio 1398 questions remain source-free.
- Full 1398 frozen assessment preflight compared all **245** questions with version 4, zero stem/answer/choice changes; after 2 insertions, official `scripts/import/import-bank.php publish` added published version **5**, preserving previous version. Independent `audit_published_assessment.php --previous=4 --current=5` succeeded: `question_content_identical=true`, `questions=245`, `updated_explanations=2`. Publisher carried forward 2 unrelated voided answers as voided/left out; no key changes performed. Live site health remained OK.
- Recoverable research archive `/var/backups/fanoos/research/20261010-periodontics-exact-reference-checkpoint-v2.tar.gz`, SHA-256 `7b36bbb657c1003d3b194d85b08d547eac4cc69c202e65af15c9909d9478b3f3`; on-host `.sha256` verification succeeded. This is **not** offsite storage and predates two production source inserts. Durable private handoff `/srv/fanoos/shared/research/ops/residency-periodontics-20261010.md` has post-apply details and recovery paths.

### Outstanding blockers — do not claim this subject fully closed

| Year(s) | Count requiring further exact-page adjudication | Reason |
|---|---:|---|
| 1398 | 18 without source | Original 12e is now ready but questions require item-by-item exact-answer evidence; some recorded official answers are clinically inconsistent or malformed. Do not change human/official answers as part of classification. |
| 1399–1403 | 100 source rows, **all without page** | Historical nearest 14e evidence used despite exact official Carranza13e. Full original 13e private text now available; requires exact-page original quote and review, and a new guarded metadata-only *correction* operator for already sourced rows (existing source-only publisher only inserts). Current 1399 Q119 ch48 conflicts with original ch47 PDF p1073, printed p507. Five currently assigned chapters fall outside official year scope: 1400 Q130 ch34, 1402 Q138 ch14/Q139 ch11/Q148 ch65, 1403 Q115 ch87. |
| 1404–1405 | 40 source rows, all suspect page values | 22 pages equal chapter numbers, 18 pages are placeholders 1/2/3. Approved private Carranza14e PDF is **only 105 supplementary pages**, while full original 14e page map expects over 1,800 pages. The full exact 14e is not present in connected Drive or approved server library; neither chapter nor printed page can be final-verified. Never substitute 13e for 14e. |

Keep source rows and question keys untouched unless exact-year evidence, validation, fresh verified backup, atomic audited metadata-only change, frozen assessment diff, and post-readback all succeed. **A sourced-row count is not an exact-reference completion count.**

---

# Dentistry residency periodontics — exact-reference audit checkpoint (2026-10-10)

**Status: private research only. No live question, choice, answer, source, historical assessment, or attempt record was changed.** This checkpoint is limited to the `periodontics` subject; other parallel workstreams own all other subjects.

## Source/year authority and current audit

The authoritative source is `data/bank/catalog.json` `validity`, read for the exact dentistry residency year and subject.

| Residency years | Exact official edition | DB question count | Source-row state | Verification status |
|---|---|---:|---|---|
| 1398 | `carranza-periodontology@12e` | 20 | 20 without source rows | **Pending** full exact-edition text and verified chapter/page map |
| 1399–1403 | `carranza-periodontology@13e` | 100 | 100 source rows with chapter, but **none with page** | **Not final**; prior nearest-edition evidence needs direct 13e review |
| 1404–1405 | `carranza-periodontology@14e` | 40 | 40 source rows with chapter/page | **Not independently final**; complete exact book PDF not currently verified |

These are read-only production measurements on October 10, 2026. Source existence must not be counted as exact-book evidence validation. Do not overwrite reviewed human source decisions.

## Original reference availability

- **13th edition:** original approved private PDF (116,004,765 bytes), 1,991 pages, 88 mapped chapters. Source SHA-256 `f0e411898ae010688ca5c0d21afe312cef6f5dae86d0e2e45648bc51ca8e2adf`. Approved `extract_server_reference.py --edition=carranza-periodontology@13e --apply` completed and independently confirmed, producing 11,342,001 bytes of protected page-marked text at `/srv/fanoos/shared/research/references/carranza-periodontology@13e.txt`, text SHA-256 `baa5b5efd320bd286e101b7243dda7550392897bb6b2155261eeb83071d81814`; a provenance receipt sits beside it. No source PDF was copied to the public repo or a laptop.
- **14th edition:** approved private object SHA-256 `eb2ebe1a682635f9be38b2da3016af6c521d6a5f70052a8acf90841bf3d4ae18` is a **105-page supplemental e-page extract**, not a complete book; independent `pdfinfo` verified its length. The catalog chapter map spans 1,875 pages and has boundary gaps (before chapter 1 and before chapter 71). `extract_server_reference.py` correctly rejected the incomplete map. This **does not authorize reassigning 14e pages by guesswork** or modifying boundaries just to satisfy validation.
- **12th edition:** exact text/page map not available; the extractor reported no page/chapter mapping. No nearest-edition replacement is authorized.

## First independently original-page-checked records, 1399

- **Q111**: exact 13e chapter **62**, PDF page **1395** (printed **641.e3**), matches existing chapter 62; the original text relates removal of widow's peaks to gradualizing marginal bone. Existing production page is NULL.
- **Q119**: exact 13e chapter **47**, PDF page **1073** (printed **507**), **conflicts with current recorded chapter 48**. Original text explicitly equates targeted oral hygiene to the Bass technique, the official keyed answer. Existing production page is NULL. **Do not change source metadata until an audited, approved correction path exists.**

Five more 1399 questions independently checked against their **current official answer** and original 13e chapter/page/text:
- Q113 — ch45, PDF p1050, printed p496: acute periodontal abscess amoxicillin loading/dosing.
- Q114 — ch51, PDF p1182, printed p549: ultrasonic instrumentation and dysphagia.
- Q120 — ch17, PDF p616, printed p244: early lesion predominantly lymphocytes.
- Q126 — ch20, PDF p656, printed p270: biopsy in necrotizing gingivitis differential, including tuberculosis.
- Q129 — ch23, PDF p708, printed p306: most severe degenerative changes in lateral pocket epithelium.

Thus **seven exact-edition page-evidence research checks** are recorded (two above plus five here); they are neither imported nor independently approved for existing-row correction. Five-item private evidence report: `classification/reports/periodontics-1399-exact13-five-more-20261010.json`, SHA-256 `aa49845d6503ce2ab42748db1a920f14276c9e101b410920ba2ce47b6eb09e3f`. The isolated one-member on-host evidence archive `/var/backups/fanoos/research/20261010-periodontics-five-more-evidence.tar.gz`, SHA-256 `750c4f336f9f3c8d5d886c904d61840eb3add50fcf6c45544e8b70b8eb040923`, passed archive-member and external `sha256sum -c` verification. As with the main archive, this is not an offsite copy.

Both checked direct text presence, edition map and official answer. Protected JSON with page-verbatim evidence, `classification/reports/periodontics-1399-exact-book-evidence-20261010.json`, SHA-256 `01909be8c1b263c15c8a8edda499fbaf08ec58d2d895a662adc0a30921f444c2`. No original-book excerpts or private questions are committed to Git.

A 20-question original-book *search*, not 20 accepted mappings, has been staged:
- `classification/reports/1399-periodontics-carranza13-queries-20261010.json`, SHA-256 `527db92aef661242c37f22c856d40f13708f8ad8c925eab0fd20317a2f06a61b`
- `classification/reports/1399-periodontics-carranza13-search-20261010.txt`, SHA-256 `645be6d24927463d6ae8195565b1d5b8074d7deb98836d7a4b3c38f16439f555`.

These are under private root `/srv/fanoos/shared/research/`, alongside `ops/residency-periodontics-20261010.md` (SHA-256 `975025c151090d52f3fd864ae5aaa3a4baac70989b3415b06b3d6c191ed2d1b1`).

A six-member private on-host recovery archive includes the complete extracted reference text, provenance and these research artifacts: `/var/backups/fanoos/research/20261010-periodontics-exact13-audit.tar.gz`, SHA-256 `53763d82c232a0c99a3d67c53a93011f060c03cafa99a0fee8be2250e6955246`; the adjacent `.sha256` receipt passed `sha256sum -c` from within the archive directory. This is **on-host**, not independent offsite recovery.

## Reproducible next steps and guards

1. Recheck fresh `main`, production DB, reference object inventory, active research decisions and concurrent work before any new write.
2. Evaluate every 1399–1403 question from its current live official answer and exact **13e** text, inside that year's official scope. Require original text actually proving the answer, exact chapter, printed/PDF page alignment, confidence and human-review threshold. Keep unsupported candidates pending; do not auto-accept top search hits.
3. Reuse the 13e extracted text; do not re-extract and overwrite it. Preserve existing AI/human decisions and make immutable, independently reviewed before/after evidence packages.
4. **Existing rows already have source links:** the current `scripts/references/import_verified_sources.php` is an **INSERT-only source publisher** and is inappropriate for changing them. First implement/test/review a dedicated idempotent **source-metadata-only correction operator**, with immutable source snapshots, protected human decisions, no updates to question/choice/answer/assessment/attempt fields, a freshly verified full backup, preview, independent audit, exact-page validation and post-apply DB readback.
5. For 14e, source and verify the **full original exact PDF** and chapter boundaries; for 12e, obtain/verify exact reference and map. Missing editions remain pending without blocking 13e.
6. Develop any code change in an isolated branch → green CI → reviewed PR → merge → official updater deployment → health/sync validation. Count source changes only after a distinct audited import receipt and live post-readback; **none were made in this checkpoint**.

See `docs/PROJECT_PRINCIPLES.md`, `docs/product/09_CHAPTER_CLASSIFICATION.md`, `docs/ops/SERVER.md`. Retention dry run observed one oldest full backup eligible for policy-managed pruning after the on-host archive addition; do not delete backups ad hoc.
