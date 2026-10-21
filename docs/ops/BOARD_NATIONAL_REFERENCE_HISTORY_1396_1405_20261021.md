# Board and national reference history, 1396–1405

Owner request, 2026-10-21: search the internet and the authorized Telegram user
session for the last ten years of dentistry board and national official
reference lists; transcribe verified notices and update the live reference
catalog. The inclusive research window is 1396–1405. A source-list discovery
does not establish that the corresponding reference-book PDF is available.

## Baseline and protected recovery

The live catalog initially contains board 1405 (67 validity rows), board 1406
(9), and national 1404/1405 (18 each). The existing research inputs and
`scripts/import/reference_map_to_catalog.py` own the public catalog.

Original downloaded notices and their SHA-256 provenance receipts belong to
`/srv/fanoos/shared/research/reference-notices/board-national-1396-1405-20261021/`.
Disposable search metadata and extracted archive members belong only to
`/srv/fanoos/shared/research/tmp/reference-history-1396-1405-20261021-1225/`.
No reference-book text, Telegram session or credential belongs in Git.

## Verified transcription and preview

The nine new public transcription files contain 407 recovered rows. Six
unresolved edition identities are explicitly marked `pending_reason` and
excluded by the generator. The remaining **401** additive validity rows are
346 board rows and 55 national rows. All previous validity rows are unchanged. One existing first-edition metadata
year is corrected from 2022 to the publisher-confirmed 2015 without touching
its chapter tree. No promotion validity is added: the 1403
orthodontic board notice and the separate promotion notice have different
scopes.

| Year | Board catalog rows | National catalog rows | Coverage limitation |
| --- | ---: | ---: | --- |
| 1396 | 0 | 0 | Exact notices not recovered |
| 1397 | 0 | 0 | Library announcements exist, but not the actual list |
| 1398 | 49 | 0 | Board prosthodontics/pathology have correction entries only; one pediatric edition pending |
| 1399 | 43 | 0 | Board operative/pathology lists absent; three edition identities pending |
| 1400 | 0 | 0 | Exact notices not recovered |
| 1401 | 60 | 19 | Pediatric selected-paper addendum not recovered |
| 1402 | 64 | 18 | Two pathology entries say only “latest printing”; McDonald national chapter scope blank |
| 1403 | 65 | 18 | Peterson chapter-scope conflict pending; McDonald national chapter scope blank |
| 1404 | 65 | 18 | National count is the existing period-24 list, not a merged period-23/24 list |
| 1405 | 67 | 18 | Existing lists retained; national periods 25/26 explicitly reuse period 24 |

These are **annual subject/edition validity rows**, including announced
article collections, not numbers of distinct books or verified book PDFs.
There are 504 board/national validity rows within the ten-year window after
the proposed addition. Board 1406's existing nine rows are outside the window
and remain intact.

The source-list archive search included the current Sanjeshp reference page,
historical Konkur mirror searches, and the authorized Telegram user session
in dental_k, uni_k, Resi_denti, orthonews, Dent1402References and dentlib,
plus bounded global searches. The public university-library posts
https://t.me/dentlib/103 and https://t.me/dentlib/138 mention 1396/1397 board
resources but link only to library access/intranet facilities. They do not
prove the actual book/edition list. Older unlocated years remain pending.

The 1404 board PDF downloaded from Telegram post 141 is byte-identical to the
web mirror (SHA-256
`9e38102b0a136d63855d56447b603ceb412daf7d8485c1b68fada81532c72804`).
Pathology's later official correction image at
https://t.me/dental_k/333 changes Robbins/Woo to 2024 editions; the new 1404
transcription uses that correction. The separate operative correction at
https://t.me/dental_k/202 corrects Misch's stated year from 2018 to 2015.

The 42 approved private reference PDFs were independently re-inventoried.
The 77 newly introduced edition/article identities have no approved private
PDF or article bundle and are explicitly recorded as missing in
`reference-pdfs.json`. Official notice recovery is not book-PDF recovery.

The additive subset passed the actual production importer's `check` and
rollback `--dry-run`: 4 exam types, 14 subjects, 37 references, 78 edition upserts (77 new plus one metadata correction),
zero nodes, **400 validity rows**, zero mappings. No production catalog apply
is claimed by this preview. The subset is reproducible from the catalog at
baseline `472670d` and the final catalog using `catalog_subset.py`.

## Recovered source notices

The current official Sanjeshp reference page still exposes national 1404/1405
and newer board lists. Historical ministry notices were recovered from the
following mirror posts; the actual original notice pages, editions, scopes
and subsequent corrections must be checked before catalog inclusion:

| Exam | Year | Mirror post |
| --- | --- | --- |
| Board | 1398 | https://konkur.in/59602 |
| Board | 1399 | https://konkur.in/75663 |
| Board | 1401 | https://konkur.in/96520 |
| Board | 1402 | https://konkur.in/101590 |
| Board | 1403 | https://konkur.in/109393 |
| Board | 1404 | https://konkur.in/114202 |
| National | 1401 | https://konkur.in/96188 |
| National | 1402 | https://konkur.in/101281 |
| National | 1403 | https://konkur.in/109398 |
| National | 1404, periods 23/24 | https://konkur.in/117908 |

The authorized Telegram session was successfully used for channel and global
reference-notice searches. In particular, https://t.me/dental_k/141 carries the
1404 board PDF; posts 202 and 333 announce operative/pathology corrections.
The recovered source notices were read one exact PDF page at a time. Corrupt
Persian digits, the national 1401 notice, the 1403 national image and the 1404
pathology correction were inspected visually. No PDF text derivative or OCR
output was created. The public transcriptions contain source metadata and
chapter scopes, not reference-book content.

## Publication requirements and remaining work

Retain explicit uncertainty for missing years, missing specialties and
unresolved edition/scope identities.
Never fill an older year's list from a newer list. Separate national written
reference lists from English-language eligibility and clinical-exam protocols.
Check whether same-year national periods have different scope before merging
them into the year-only validity model.

Build an additive catalog subset, validate and preview its import, merge only
with green CI, deploy through the updater, take/verify a fresh full backup,
import catalog metadata only, and independently read back the reference API
and per-year totals. Question text, answers, citations, attempts and frozen
assessment versions are outside this task's mutation scope.

## Publisher identity correction before data apply

The original 400-row preview was not applied. A final publisher check resolved
Burstone 1403 as second edition 2022, ISBN 9780867159493; the publisher
copyright/cataloging page explicitly identifies the preceding first edition
as 2015, ISBN 9780867156515. The existing first-edition published-year metadata
was therefore corrected to 2015, and the separate second edition was added.
No legacy first-edition validity row was reassigned. Primary evidence:
https://www.quintessence-publishing.com/usa/en/product-info/814683/the-biomechanical-foundation-of-clinical-orthodontics
and
https://www.quintessence-publishing.com/downloads/extract_24071_choy_burstones_biomechanical_foundation_of_clinical_orthodontics.pdf.

`catalog_subset.py --include-edition-metadata` explicitly includes the reviewed
first-edition year correction without any chapter nodes. Its default mode now
refuses silently omitted metadata corrections, and both modes reject changes
to an existing chapter tree. Final planned apply: 401 new validity rows, 77
new edition identities and one existing edition metadata correction.
