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
