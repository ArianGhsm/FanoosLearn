# Live residency chapter-source publication checkpoint — 2026-10-10

## Authorization and release

Owner requested merge/deploy first, then continue exact-official-edition
chapter assignment, importing and republishing completed batches after each
verified stage. **This is a production execution log, not a proposed plan.**

- Repository: `ArianGhsm/FanoosLearn`, public PR
  [#186](https://github.com/ArianGhsm/FanoosLearn/pull/186) successfully
  merged as `f7e5065ca7f350ae52d7aad7ecd2d85101abfb63`.
- Merge-commit GitHub CI run `37995540725`: **all 5 required jobs green**
  (static/unit PHP 8.2, PHP 8.4, Python/bot, web UX, MySQL integration).
- Operator fast-forwarded `/srv/fanoos/updater-checkout` to that exact SHA,
  then queued official deployment request
  `01a122a4-56bd-7420-970e-51ebe1078f81`.
- Updater request state `SUCCEEDED`, safe failure code NULL; immutable
  `/srv/fanoos/current` points to
  `/srv/fanoos/releases/f7e5065ca7f350ae52d7aad7ecd2d85101abfb63`.
  `scripts/dev/check-sync.sh` reported `in sync`; `/health` HTTP 200.
  No direct edits of release files or unrelated workloads.
- Protected full backup made by the official updater:
  `/var/backups/fanoos/20261009T215235Z-849264aa`, verified by
  `scripts/ops/verify-backup.php`: **54 files**. The protected backup ID
  is UTC-dated October 9 although the user's local date is October 10.
  Before creating this snapshot, `prune-retention.sh` was used as
  documented to retain four completed full backups and make room for
  the new fifth. Private research archives are separate on-host backups,
  **not** offsite.

## Actual live source-only import: 64 new chapters

The source-only importer
`scripts/references/import_verified_sources.php` loaded the exact
official edition, node `#chNN`, page, AI origin, confidence, and evidence
for the seven batches below. Before each apply:

1. The complete private original study-only export was regenerated from
   the **current live database** to a distinct protected file and compared
   byte-for-byte with `cmp -s`: 7/7 identical.
2. The source-only writer's transaction preview returned the expected count,
   **questions_changed=0; choices_changed=0; answers_changed=0** for
   all seven batches; the existing entire frozen assessment version was
   checked by `audit_published_assessment.php` without any non-explanation
   difference.
3. The verified full backup and private research SHA-256 audit were supplied
   to every `--apply`, with separate unique private receipts and an atomic
   SQL transaction. No existing source rows, including human-reviewed
   sources, were overridden.

| Year | Subject | New source rows | Unchaptered residency after apply |
|---|---|---:|---:|
| 1403 | community-dentistry | 9 | 482 |
| 1399 | community-dentistry | 9 | 473 |
| 1400 | community-dentistry | 5 | 468 |
| 1401 | community-dentistry | 10 | 458 |
| 1402 | community-dentistry | 7 | 451 |
| 1405 | community-dentistry | 9 | 442 |
| 1405 | oral-radiology | 15 | **427** |

Total: **64 source rows**. Live question mapping improved from
`1504 / 1995` chaptered (`491` missing) to
`1568 / 1995` chaptered (`427` missing).

Private receipts live in
`/srv/fanoos/shared/research/classification/reports/<year>-<stem>-source-publish-20261010.json`.
SHA-256 for each, in table order:

- 1403 community:
  `70ab81f373c194bbc6a2ae5cdcf9b3f11be750b1a6a0445f19d412f75dc25da7`
- 1399 community:
  `3dcd43604fc45a6160e8db83d6b3b58d454004749b6e07a2d32ef47bcf85c36f`
- 1400 community:
  `836663fba330ca327d2c5e97791c573d238bcb71eee9cbaca63114b6420acc14`
- 1401 community:
  `8d532fcb2457b2ae811cdb151edd8675445a5f3a594c78f32636a98d328e2814`
- 1402 community:
  `dd2b61f8b9b8397f682fb0cad013cd4cd6f4305ef0341ceb52c68a861e2fbee2`
- 1405 community:
  `19c5e5c2b1b2f0cabdd07811fc04b297b2d2307eedb475a71146f4ad560a15bd`
- 1405 radiology:
  `dc721469f375d469bdb8743783118639c1959b312380b31a6cb4cb6e21171e6e`

The original eight-batch SHA-256 research audit was
`43ec6f2d23ed1b2c2a4717be47bbf20d1609b603887607f42157df564f64c4b8`;
its decisions were already checked against the page-marked books and
existing chapter map. An archive of seven receipts and its verified
SHA-256 checksum are at
`/var/backups/fanoos/research/20261010-source-publication-seven-receipts.tar.gz{,.sha256}`.

**1398 community (10 decisions)** remains excluded from production because
the detailed official announced chapter scope is not present; never
supply a guessed `scope_chapters` or change the catalog to force an import.

## Published student-facing assessment snapshots

The six affected published residency assessments were re-published using
the canonical `scripts/import/import-bank.php publish` command, with an
authorized platform super-admin actor/reviewer. Each followed the service's
review/publish flow, **appending** one new frozen assessment version. All
old versions and attempts are retained. The dedicated post-publish audit
compared the *entire* old/new question set and confirmed every field
**except source-derived explanation** remained identical:

| Year | Old → new assessment version | Questions unchanged | Explanations changed |
|---|---|---:|---:|
| 1399 | 3 → 4 | 248 | 9 |
| 1400 | 3 → 4 | 249 | 5 |
| 1401 | 3 → 4 | 249 | 10 |
| 1402 | 2 → 3 | 243 | 7 |
| 1403 | 2 → 3 | 250 | 9 |
| 1405 | 3 → 4 | 246 | 24 |

Questions with **voided** official answers stayed excluded from their
assessment snapshot; no existing answer key was changed. All six
assessments report `status=published`, and the site health remained 200.

## Next exact-edition batch: 1403 oral radiology

Research resumed on another 20 unchaptered 1403 oral-radiology questions.
Official reference: `white-pharoah-radiology@8e`, only chapters
1–9, 12, 15–28. The same approved on-server 1,958-page text and
continuous chapter map were used; **11 decisions passed** the unmodified
page-verbatim evidence validator, **zero rejects**, **nine undecided**.
No out-of-scope chapter or nearest-edition substitution was accepted.
Research files:

- `bank-sittings/1403/radiology-study.json` (SHA-256
  `81a9a15bcb37bb1f57193102bd4d30492ebba7580c363d5cfa2419edb48b71c5`)
- `classification/reports/1403-radiology-queries.json`
- `classification/decisions/1403-oral-radiology.json`
- `classification/sittings/1403-radiology-validated.json`
- `classification/reports/residency-1403-radiology-audit-20261010.json`
  (SHA-256 `07a00756ab60a05d7b616ff260be65256a665032dc60ebd5b7f8cdc96ecefeb4`)

Protected seven-member archive with verified SHA-256:
`/var/backups/fanoos/research/20261010-1403-radiology-first-batch.tar.gz`.
The exact 1403 study re-export matches live byte-for-byte. The
source-only transaction dry run predicts **11** new rows and **zero
question/answer changes**; the complete currently published 1403 frozen
assessment preflight passed for all 250 questions.

**Actual 1403 radiology source-only publication: COMPLETED.**

- Separate fresh full DB + non-reference object backup made **after** the
  previous 64 links had been published:
  `/var/backups/fanoos/20261009T220140Z-db6146e1`. Its completed
  manifest was verified independently using
  `scripts/ops/verify-backup.php`: **54 files**. The SentinelX background
  job reported a relay timeout, but the finalized non-partial backup
  directory and independent verifier both confirmed success; no claim is
  based solely on the timed-out background task.
- The original 20-question study export was regenerated and compared
  byte-identically with the live production question/answer data. The
  `import_verified_sources.php` database preview returned exactly 11
  new source rows, `questions_changed=choices_changed=answers_changed=0`.
- With the independently verified new full backup and private audit as
  inputs, **11 `bank_question_sources` rows were committed atomically**.
  Receipt:
  `classification/reports/1403-radiology-source-publish-20261010.json`;
  SHA-256
  `943c8075bad8db101c6a5e8859c63e83c1aa114405d821d83666cdb754ed0609`.
  The source-only CLI reported `applied=true`, 11 rows, 0 question/
  choice/answer changes.
- Live readback after the transaction: **416 unchaptered** residency
  questions, down from 427.
- The current 1403 assessment version 3 was compared against the
  BankPublisher candidate for all 250 questions; no non-explanation
  difference. The canonical owner-authorized review/publish workflow
  created **version 4** of the same already-published assessment.
  Read-only *postpublish* diff verified all 250 frozen questions unchanged
  except **11 newly sourced explanations**. `/health` responded 200.
- Protected two-member publication-receipt archive with verified SHA:
  `/var/backups/fanoos/research/20261010-1403-radiology-publication-receipt.tar.gz`
  plus adjacent `.sha256`.

**Final measured cumulative effect:** 64 earlier plus 11 radiology 1403
= **75 newly chaptered/published** questions; chaptered residency now
**1579 / 1995**, **416 still unchaptered**. This is the live database
backlog, not a projection. The 10 research decisions from 1398 community
remain on hold because official syllabus scope was not verified, and 25
question-level research decisions remain unresolved across the nine studied
batches (16 old + 9 new). Continue only with exact ready editions.

## Recovery and continuation

Read `docs/ops/SOURCE_ONLY_PUBLICATION_20261010.md` for exact CLI syntax
and checks. Never use the full-sitting importer on research JSON; it was
designed to reject it. After each new batch: private study export,
approved exact book/page evidence, `apply_classification.py`,
`audit_private_study_batches.py`, live dry run, verified **fresh** full
backup, source-only atomic apply, verify production chapter counts, then
official assessment review/publish and all-question content diff.
Record new backup ID, private receipt SHA, version increment and updated
backlog here. Research question/book text remains **only** on protected
server storage; public GitHub records process and hashes only.

## Continuation: 1402 oral radiology (completed 2026-10-10)

**Production publication confirmed, not research-only.** The 1402 official
source is exactly `white-pharoah-radiology@8e`, announced scope chapters
1–9, 12 and 15–28. From the still-unsourced 1402 oral-radiology subject,
20 live questions (numbers 211–230) were exported as protected *study-only*
data. No missing figure, unsupported analogy or substitute edition was used.

- Original production study export:
  `/srv/fanoos/shared/research/bank-sittings/1402/radiology-study.json`;
  SHA-256 `0f1e529c1d42c4bcdd4eea08601b78121299776bc0393831527cf31f925c400e`.
  The separately regenerated live export was **byte-identical** before import.
- Private search queries, source evidence and exact chapter/page decisions:
  `classification/reports/1402-radiology-queries.json`,
  `classification/reports/1402-radiology-search-20261010.txt`,
  `classification/decisions/1402-oral-radiology.json`,
  `classification/sittings/1402-radiology-validated.json` under the protected
  research root. Unmodified `apply_classification.py` accepted **14**,
  rejected **0**, and left **6 undecided**; private exact-book audit confirmed
  all 20 study questions and 14 mappings without answer/content modification.
- Private provenance audit:
  `classification/reports/residency-1402-radiology-audit-20261010.json`,
  SHA-256 `156fa753eac5589343a3020443b39dd703f072f02b02acb86408ca1bff95f441`.
  Seven-file research archive
  `/var/backups/fanoos/research/20261010-1402-radiology-first-batch.tar.gz`
  has a separately verified adjacent `.sha256`. On-host only, not offsite.
- The source-only transaction preview reported **14** inserts and
  `questions_changed=choices_changed=answers_changed=0`; the *entire*
  published 1402 exam preflight matched **243** unchanged frozen questions.
- An additional full-backup request lost its Sentinel transport response,
  and an initial on-host inspection found no new finalized backup. **The same
  already-running backup later completed**: finalized protected
  `/var/backups/fanoos/20261009T233920Z-f8f4ee51`, independently
  verified **54 files** with READY at 23:39:23 UTC. This later backup was
  **not** used as the pre-apply recovery point: the source-only import
  completed at 23:39:18 UTC, and relied on the earlier independently
  verified full backup `20261009T220905Z-eee036a3` (54 files), which was
  created after the previous source publication and met the importer's
  strict four-hour freshness window. Do not treat a relay timeout as proof
  that a host-side background task failed; check for delayed finalization.
- The atomic production source-only importer committed **14**
  `bank_question_sources` rows with zero question, choice or official
  answer changes. Private receipt
  `classification/reports/1402-radiology-source-publish-20261010.json`,
  SHA-256 `e0d7b9e45ecfb4e604b51f08a921c9e54e9fc3fbef709440a7565367b759d8e1`.
  A two-member receipt archive
  `/var/backups/fanoos/research/20261010-1402-radiology-publication-receipt.tar.gz`
  and matching `.sha256` passed checksum verification.
- **Independent live database readback: 402 residency questions still lack
  source chapters**, versus 416 before the batch. Total cumulative
  newly chaptered questions across these publication stages: **89**;
  residency chaptered now **1593 of 1995**.
- The authorized, canonical assessment review/publish flow appended
  **1402 version 3 → 4**; all **243** frozen questions remained identical
  in every field except **14 source-derived explanations**. All earlier
  versions and attempts remain preserved. The local public-site health
  probe returned HTTP **200**.
- Six remaining 1402 radiology questions are held for further evidence.
  The 1398 community mappings remain held for missing official syllabus
  scope. Continue with an eligible exact edition (for example 1401 or 1400
  radiology) after independent live preflight. This section supersedes the
  earlier 416-pending checkpoint; do not repeat the 1402 batch.

**Reproduction:** use the source-only runbook, protected study/export and
audit above, plus `--year=1402 --subject=oral-radiology --stem=radiology
--expected=14`; the same `--audit` file must pass hash comparison. Since
this batch is already live, **do not run its apply again**: the source-only
writer correctly rejects duplicate sources. All public docs contain metadata
and audit hashes only, never copyrighted question/book content.
