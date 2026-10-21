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

## Discovery checkpoint: research in progress

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
These are discovery evidence, not an import receipt.

## Publication requirements and remaining work

Finish exact-page transcription and edition resolution; retain explicit
uncertainty for missing years, missing specialties and illegible scopes.
Never fill an older year's list from a newer list. Separate national written
reference lists from English-language eligibility and clinical-exam protocols.
Check whether same-year national periods have different scope before merging
them into the year-only validity model.

Build an additive catalog subset, validate and preview its import, merge only
with green CI, deploy through the updater, take/verify a fresh full backup,
import catalog metadata only, and independently read back the reference API
and per-year totals. Question text, answers, citations, attempts and frozen
assessment versions are outside this task's mutation scope.
