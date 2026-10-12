# Residency 1403: seven original-book PDF page metadata repairs applied — 2026-10-12

This checkpoint records **seven existing-source repairs COMMITTED and independently read back in production**. No previously recorded source row was deleted and the repairs changed only `bank_question_sources.pdf_page` and `bank_question_sources.pdf_sha256`. No question stem, option, official answer, anchor text, edition, chapter, human decision, old printed page, student attempt or frozen assessment version was changed.

## Source location, before/after and original-reference evidence

The existing 250-question `residency / 1403 / round 1` question archive was held invariant against canonical immutable export SHA-256 `5a903b491496ed3b7f912dde0fdc37607ff102f33b36a9c5e723baab19a1fcc7`.

| Official subject | Question | Official source edition | Chapter | Physical original PDF page | Legacy printed page |
|---|---:|---|---:|---:|---:|
| endodontics | 30 | torabinejad-endodontics@6e | 12 | 248 | 241 |
| endodontics | 31 | torabinejad-endodontics@6e | 16 | 367 | 360 |
| oral-medicine | 66 | burket-oral-medicine@13e | 5 | 164 | 144 |
| oral-medicine | 71 | burket-oral-medicine@13e | 6 | 195 | 175 |
| oral-surgery | 133 | hupp-oral-surgery@7e | 23 | 503 | 489 |
| oral-surgery | 146 | malamed-local-anesthesia@7e | 4 | 93 | 76 |
| oral-surgery | 149 | hupp-oral-surgery@7e | 31 | 669 | 655 |

The original server PDF hashes remained bound to each source: Torabinejad6e `09333f079cb3300550cc0702985aae216d952eacabdaac005e1c66fafebe13e0`, Burket13e `0ca841beb1820e8e0a33c96b5ce37cc36c94d6adce53fbf172b27312e0fd5252`, Hupp7e `35e3c37fec67a831a4464205c1817cc1e51c0a9f8a0bd8660a1881ecd2f10a83`, Malamed7e `cc668f86a731bed4c016feb1a3e20be033637c7d0a30da86358dbc9b2a4c08e7`. Each corresponding old edition/chapter was confirmed already correct for the official 1403 subject syllabus; this existing-source repair operator **does not support changing an incorrect edition/chapter/anchor**.

Prior to these repairs, read-only, real DB transactional rollback previews by the **unchanged approved** `scripts/references/repair_verified_source_pages.php` passed for 3 exact private plans:

| Subject | Count | Protected original plan SHA-256 | Official applied receipt SHA-256 |
|---|---:|---|---|
| endodontics | 2 | `7ea72a5ec8e5003be28f812809a8af2d54774c0de05d3a01115a0ce3c8493155` | `b83e509c3de845273b1e37cc6a30117e9d36325bf44a771616d382db0a4a78a5` |
| oral-medicine | 2 | `02cfcc2eff0ff07f7c89e54c1f2d1e55eef9a406b77d904106eb589faaeaea2c` | `cb3152362c83014ee56036860dd60f7e9b45ad17241098167638ebde32583918` |
| oral-surgery | 3 | `e879439fa50b8e7fca7d3a14fee7d1d44fd95e108f4ba836a3c0dcf1b4da67c5` | `75a27811a655a195709a6ebe8c4d74680878e0339bdd164c1093feec0f421b83` |

Original plans are `/srv/fanoos/shared/research/classification/reports/residency1403-verified-page-metadata-preview-<subject>-20261012.json`; corresponding atomic committed receipts are `/srv/fanoos/shared/research/classification/reports/residency1403-page-repair-applied-<subject>-20261012.json`. No protected raw questions or PDF corpus are committed to Git.

The source repairer required the old source row ID, old printed page, old anchor SHA, `origin=ai`, unchanged existing official in-scope edition+chapter, original page contents + printed-page adjacent evidence, absent prior PDF page/hash, published question, final/amended key and at least two options. Each dry run performed a real guarded `UPDATE` **inside a transaction that was then rolled back**.

After [the nine 1403 radiology source-only INSERTs](RESIDENCY_1403_RADIOLOGY_NINE_SOURCE_ROWS_APPLIED_20261012.md) resulted in **80** exact completed source pairs, a **new full official backup was independently created and verified**: `/var/backups/fanoos/20261012T160207Z-e513e65a` (114 verified files, protected `database.sql`, manifest and READY; original reference PDFs explicitly excluded by policy). Do not mistake the earlier research-only tar archive for a full DB backup.

The three approved update operations applied separately and atomically under authorized `fanoosupd` with exact SHA-bound plans and the fresh complete backup at `2026-10-12T16:03:41Z` (endodontics), `16:04:11Z` (oral-medicine) and `16:04:17Z` UTC (oral-surgery). All three official receipts record `mode=applied`, exactly 7 cumulative repaired source rows, and `questions_changed=choices_changed=answers_changed=0`.

Independent **post-commit production DB readback** found all seven source IDs at the intended exact reference/chapter/PDF page/PDF SHA, AI origin and no human review. Clinical citation paired count increased **80 → 87 of 250**.

A new canonical **full 250-question read-only immutable audit** independently compared every live question's text, every option (including images), latest official answer/status and all 153 research target source states; it confirmed 9 prior newly inserted radiology sources + these 7 page-metadata repairs while leaving **137** previously researched existing-source targets still without validated original PDF page+SHA. Receipt `/srv/fanoos/shared/research/tmp/1403-source-page-expansion-20261011/residency1403-live-immutable-postseven-20261012.json`, SHA-256 **`1b9250f957a07f9600d563d245f39667d4d49bad4259423b371edbee4951cd5e`**. Health endpoint `/health` returned OK; deployed release remained `cd3b7913640dcb6ccdcb471e637e9e3236b89c4e`.

## What remains

Production source state: **87** full original reference chapter+physical-PDF-page pairs, **137** independently audited but unpublished existing AI-source citations, **20** English final-key textbook-exempt, **6** genuine year-syllabus evidence holds (Q61,62,63,68,69,127). Total = **250** disjoint.

Another eight attempted unchanged-original-source map repairs had **safe preflight rejection** on this deployed release: pediatric dentistry Q178/179 and prosthodontics Q93/Q94/Q95/Q96/Q97/Q107. The approved original McDonald11e/McCracken13e PDF SHA maps were merged in [PR #339](https://github.com/ArianGhsm/FanoosLearn/pull/339), but not yet present in **the actual deployed release**. Do not bypass these runtime guards or directly edit the symlink/current reference map; wait for an independently approved safe production release before re-previewing.

Of the remaining 137 existing-source targets, old unreviewed AI anchor strings include **48 nearest-edition legacy citations**. Two old source editions and six old source chapter assignments differ from the independently audited official original evidence. These cannot be repaired by simply stamping page+hash: a separately reviewed, tested, official 1403-only source metadata/source-evidence correction operator is required, protecting the full immutable question/official key/human/attempt/frozen-exam history. Do not attribute published completion to these 137 until actual DB readback validates same-row chapter, physical PDF page and original SHA.

No student frozen assessment was republished in either the 9-source insertion or these 7 metadata repairs. A read-only assessment preflight after the initial 9 inserts passed for published assessment **version 5**: all nine study questions matched unchanged frozen question content, total 250 questions. New-version publication needs a separately authorized and fully verified frozen-exam diff. No software deploy was performed by these source-only transactions; the approved tools already existed in release. The accompanying docs-only PR can have independent CI/merge and does not retroactively make the deployment version change.

**Always inspect current DB, full backups, source receipt ledger, GitHub/main, deployed release, and concurrent worker ownership before any subsequent mutation.**
