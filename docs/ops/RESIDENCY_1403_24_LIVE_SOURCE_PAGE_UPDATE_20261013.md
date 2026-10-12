# Residency 1403 exact-year chapter+PDF-page production checkpoint — 2026-10-13

**Measured on LIVE production DB (not inferred from research): 95 out of 250 residency/1403 round1 questions have node_id, physical pdf_page, and the matching exact original reference PDF sha256 on the same source record.** The original 250-question/answer canonical export SHA-256 remains `5a903b491496ed3b7f912dde0fdc37607ff102f33b36a9c5e723baab19a1fcc7`.

Previously 71 fully paired. In this campaign **24 more** were committed: nine **new** White–Pharoah radiology8e original-reference sources Q192,193,196,197,203,205,207,208,210; seven metadata-only original-PDF page repairs Q30,31 (Torabinejad endodontics6e), Q66,71 (Burket oral medicine13e), Q133,146,149 (Hupp oral surgery7e / Malamed LA7e); and **eight further repairs Q178,179 (McDonald/Avery pediatric11e) and Q93,94,95,96,97,107 (McCracken13e/Zarb13e)**. The latter eight were blocked on an old deployed PDF map the prior day but the actually checked deployed release was updated to the SHA-bound original McDonald/McCracken maps. They then passed the **unchanged official** `repair_verified_source_pages.php` rollback previews, new official backup verification and two atomic source-only commit transactions:

| Question | Reference | Official chapter | Exact PDF page |
|---:|---|---:|---:|
| 93 | McCracken RPD 13e | 9 | 120 |
| 94 | McCracken RPD 13e | 11 | 157 |
| 95 | McCracken RPD 13e | 3 | 29 |
| 96 | McCracken RPD 13e | 7 | 101 |
| 97 | McCracken RPD 13e | 5 | 49 |
| 107 | Zarb Edentulous 13e | 10 | 230 |
| 178 | McDonald/Avery Pediatric 11e | 12 | 247 |
| 179 | McDonald/Avery Pediatric 11e | 14 | 287 |

The exact old AI source edition/chapter/anchor and printed page labels for these eight were unchanged. All eight old source rows were `origin=ai`, unreviewed, single primary, previously missing pdf_page/pdf_sha256. On the new release, official chapter map SHA binding and original exact-PDF/printed-page-neighbour/evidence checks passed. **Only** `bank_question_sources.pdf_page` and `bank_question_sources.pdf_sha256` changed. No question, option, answer, human decision, assessment, or attempt was updated. Original server PDF SHA:
- McCracken13e `4e05bee57d1066601b8a2b30c2ae72d4a5bf65298747b600ae3fdd8248a192d9`
- McDonald11e `a344465f89ff6711099a8bee1878e83236c9778340447ac7491bbc27a89fb629`
- Zarb13e `bcf92916cd0a01915695d1cb5aad8c51e7f2dac5909477c4df4454d229156a3d`

The 114-file full official **production database-and-objects** backup `/var/backups/fanoos/20261012T171139Z-e47ca46e` passed `scripts/ops/verify-backup.php` immediately before apply. Two original protected SHA-sealed plans and production applied receipts:

| Subject | Original plan SHA256 | Applied official receipt SHA256 |
|---|---|---|
| pediatric-dentistry | `1ddee78bd8f1f4092e227f07733199223e4f910f01769d2706f9304afb7e6e6f` | `c482db649c080263f93c58e7513160f747540e99ea5fd5735a3cd933ca7af8e0` |
| prosthodontics | `e17bc54e52b5f79e2af8b8dbdb91fe15522ce10b69c985efd270f412b6df2cba` | `d8fb64b153f566ca3d20299349dc2d9ddfad531569b825bb9246f65c83b4b410` |

Plan path pattern: `/srv/fanoos/shared/research/classification/reports/residency1403-verified-page-metadata-preview-<subject>-20261012.json`. Applied receipts: `/srv/fanoos/shared/research/classification/reports/residency1403-page-repair-applied-<subject>-20261012.json`. The exact eight source IDs were independently queried afterward from LIVE production and matched approved original edition/chapter/pdf_page/pdf_sha256 and nonhuman AI origin. **95/250** exact-paired source rows as read from live SQL following those transactions. Site health OK at deployed SHA `3ec7953b4c10b1cefe78f7242d4385876d15818b`.

The independent original-250-question post-commit immutable audit `/srv/fanoos/shared/research/tmp/1403-source-page-expansion-20261011/residency1403-live-immutable-postfifteen-20261013.json`, SHA-256 **`c3ca2215b74bd39f9063a55db9598fc5493cf01ff298f1fa799d96c1ddd510e5`**, independently verified all stems, option texts/images, latest official keys and answer statuses, nine inserted original radiology sources and all fifteen old-AI metadata-only page repairs against pinned plan SHA, source row ID, original unchanged page, anchor SHA and official edition/chapter/PDF SHA. Result: zero immutable content drift.

**Remainder is disjoint: 129 earlier independently audited year-official original PDF chapter+page pairs not yet safely applied to legacy AI source rows; 20 English official-final-key questions exempt from book references; and six clinically held Q61,62,63,68,69,127 without acceptable same-year in-scope original-page proof. 95+129+20+6=250.** Do not say those 129 have reached production. Old legacy "nearest edition" anchors and some incorrect original chapters/editions require a separately reviewed, tested, source-only citation repair, not a blind PDF page stamped onto stale anchors. Frozen student exam remains published at assessment version **5** as last verified; **no 1403 assessment re-publication or software deploy** was performed by this source-only operation.

Related earlier permanent docs: `RESIDENCY_1403_153_VERIFIED_ORIGINAL_PDF_PAGES_20261012.md` (research), `RESIDENCY_1403_RADIOLOGY_NINE_SOURCE_ROWS_APPLIED_20261012.md` (nine live INSERTs; PR #363 merged), and `RESIDENCY_1403_SEVEN_EXISTING_SOURCE_PAGES_APPLIED_20261012.md` (seven live repairs; PR #368 pending in earlier checkpoint). New checkpoint supersedes numerical counts there; never overwrite old receipt files.

**Operational protocol:** Re-query live DB, report inventory, official reference map, current GitHub/main, deployed release and site health before another mutation. Apply only via reviewed protected original-PDF proof bound to a fresh verified full backup and official guarded source-only tooling, hold contradictions, and verify immutable bank plus assessment states after commit. Code/docs Git changes require their own green CI and merge; this checkpoint itself never deploys an unreviewed release.
