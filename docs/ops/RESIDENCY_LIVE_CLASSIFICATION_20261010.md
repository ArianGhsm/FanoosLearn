# Live residency chapter-source publication checkpoint — 2026-10-10

> **1398 reference-list update (2026-10-10):** The later four-page, owner-supplied complete notice adds official reference-validity scopes for oral pathology, prosthodontics, community dentistry and dental materials (six additional references; 19 total across 12 clinical subjects). The earlier “1398 syllabus missing” remarks below are historical checkpoints and are superseded **only for source-list validity**, not for unverified question citations. See [1398 complete notice](RESIDENCY_1398_COMPLETE_NOTICE_20261010.md).

Historical cache note: earlier runs generated temporary page-marked text from
verified reference PDFs. Cleanup removed the live derivatives and the
discovered W08 archive copy. The current working branch reads one exact PDF
page at a time and creates no PDF text file or index; it is not yet merged or
deployed. Search-output filenames below are history, not files to reuse.

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

### Next subject opened (research only): 1401 oral radiology

An authenticated, read-only live export created
`/srv/fanoos/shared/research/bank-sittings/1401/radiology-study.json`
for **20** still-unsourced 1401 radiology questions, format
`fanoos.classification.study-only/1`, SHA-256
`c177f4f3d038167517d3201d7d5735c09ca87cbcad31cc617665e3718fb60b61`.
The announced exact official edition is White-Pharoah 8e; use only the
1401-specific scope from the catalog. **No 1401 search evidence, mapping,
source insert, bank publication or assessment version change is claimed.**
Repeat a fresh live study-only export, compare it with the protected original,
then follow the normal page-verbatim validator and publication sequence.

## Continuation: 1401 oral radiology source-only publication (2026-10-10)

**Completed and independently checked in production.** The year-specific
official reference was exactly `white-pharoah-radiology@8e`, with the
documented **chapters 1–25** official scope. Twenty still-unsourced
questions numbered **191–210** were exported from the authenticated
live database to protected read-only study format.

- Original protected study:
  `/srv/fanoos/shared/research/bank-sittings/1401/radiology-study.json`;
  SHA-256 `c177f4f3d038167517d3201d7d5735c09ca87cbcad31cc617665e3718fb60b61`.
  A second live export yielded **the same SHA-256**. Exact copied fields
  were rechecked by the atomic source-only writer.
- The protected research search terms, original decisions and validated
  output are at `classification/reports/1401-radiology-queries.json`,
  `classification/decisions/1401-oral-radiology.json`, and
  `classification/sittings/1401-radiology-validated.json`. The original
  `apply_classification.py` accepted **9**, rejected **0**, leaving
  **11 undecided**. One provisional Q208 passage initially passed
  source-page validation but the independent audit detected an invalid
  printed-page extraction (`page="10"` instead of the PDF page).
  It was removed from the accepted decision set and the validator/audit
  were rerun; **no Q208 source was inserted**.
- Private independent content and provenance audit:
  `classification/reports/residency-1401-radiology-audit-20261010.json`;
  SHA-256 `ca117329fcc164596ad0dd77c34dcf736855c0b1a857592ce15532762f5edddb`,
  outcome **20 study questions; 9 accepted; 11 pending**.
- Protected six-member research archive:
  `/var/backups/fanoos/research/20261010-1401-radiology-first-batch.tar.gz`,
  plus verified adjacent SHA256. Book or original question text remains
  on the protected server, never on GitHub.
- Live source-only DB preview: **9** new rows, **zero** changes to
  stems, choices, and official answers. Complete frozen exam preflight
  confirmed **249** questions' non-explanation content unchanged,
  version **4**.
- Immediately before apply, independently reverified full protected
  post-1402-deployment backup
  `/var/backups/fanoos/20261009T235234Z-9ad31034`:
  **54 verified files**, less than four hours old, created *after* the
  preceding 1402 source inserts. This was the actual pre-apply backup;
  it contains database and non-reference objects, not offsite PDFs.
- The production importer atomically inserted **9**
  `bank_question_sources` with
  `questions_changed=choices_changed=answers_changed=0`.
  Private receipt `classification/reports/1401-radiology-source-publish-20261010.json`,
  SHA-256 `c5fa47da77a23c880a71d521c04781b4ed576ccc0e11d98caba7b253a70fd7e0`.
  The matching two-member protected publication archive and SHA verification
  completed: `/var/backups/fanoos/research/20261010-1401-radiology-publication-receipt.tar.gz`.
- Independent live readback now shows **393** unchaptered residency
  questions, down from **402**. Campaign total new mappings:
  **98**; live residency chaptered **1602 of 1995**.
- The authorized canonical assessment review/publish flow appended
  **1401 version 4 → 5**; full old/new comparison confirmed all
  **249** question definitions identical except **9** source-derived
  explanations. No prior version or attempt history was deleted.
  `/health` returned HTTP **200**.

**Continuation:** Do not re-import or republish the completed 1401 batch.
The eleven unresolved 1401 questions stay queued, including any whose
book citation conflicts with printed-page extraction or official answer.
Proceed with another exact edition/year (for example 1400 radiology)
only after a fresh study export and an official syllabus-scope check.
The figures earlier in this document are historical checkpoints, not
the latest count. Reproduce the procedure using
`docs/ops/SOURCE_ONLY_PUBLICATION_20261010.md`.

## Independently completed W01 parallel handoff: 1399 radiology (2026-10-10)

**Actual production completion**, not research-only: W01 handed off exactly 12 White-Pharoah 8e official-scope mappings for 1399 oral-radiology. Coordinator independently reran the original exact-page validator and secondary audit on 20 source-free original study questions, then re-exported the live batch byte-identically (study SHA256 `420919d7621d1be41c277abcf6520f64a70258a434ca7c429661c93a52c1ce85`). Protected original provenance audit SHA256 `1a573f25e64a52cbc67d6f908055d6257f74b06957c0dd155fcb210e60d35c24`.

Rollback-only production preview returned exactly **12 source rows**, with `questions_changed=choices_changed=answers_changed=0`. Complete existing 1399 frozen assessment preflight checked **248** question definitions unchanged in version **4**. Independently verified complete full backup `/var/backups/fanoos/20261010T041149Z-352af4b5` was finalized before this source change and fresh under four hours; its manifest had **54** verified files (on-host, not offsite). The vetted `import_verified_sources.php` inserted **12** source rows atomically, completed `2026-10-10T07:53:51Z`; unique protected receipt:
`/srv/fanoos/shared/research/classification/reports/1399-radiology-W01-source-publish-20261010.json`, SHA256 `20166d5d634ea08f195b1580726b7a17d58d4cb7ac2684bb3cf6816b137b8d79`. Independent live DB readback: residency unsourced **393→381** (source-linked **1602→1614**).

Canonical authorized review/publish appended **1399 assessment version 4→5**. Full previous/current frozen assessment audit: **248 questions identical in all fields except 12 new source explanations**; previously voided official answer continues excluded. This 1399 batch is **DONE — never re-import or re-publish it blindly**.

### Parallel policy, English completion and still-pending 1400

The owner separately finalized residency `english` **by original official answer key only**, exempting 159 source-free English questions from citation. All 159 have a current answer: 158 `final`, one `amended`, none voided/missing/null. This changes **no** bank source rows, keys or exam content. GitHub authority: `docs/PROJECT_PRINCIPLES.md` section 3A, `docs/ops/RESIDENCY_ENGLISH_KEY_FINALITY_20261010.md`. After the above 12 actual 1399 source inserts, the **raw** no-source backlog is **381**, of which **159** are already final/exempt English and **222** are non-English evidence-dependent candidates.

W01 1400 oral-radiology still has **14** originally validated sources in 20 source-free questions. Independent live re-export SHA `1c0447f57a14bc49f24de97e98d3e919e89f7a6bdb6997b1a17de49fec64b85a` matched the protected research original. Post-release full 249-question frozen assessment preflight version4 and 14-source rollback preview both passed, with zero changes to stem/choice/answer. **NO 1400 ATOMIC APPLY OCCURRED**: the real execution was blocked by the connected tool's safety check, and no 1400 W01 receipt exists. Do not count its 14 research mappings as imported. Do not bypass the safety control or blindly repeat operations.

The page-auditor defect causing legitimate book-printed labels to be rejected when differing from PDF page indexes was corrected with stricter exact-book plus two-neighbor corroboration, three synthetic regression tests, and all GitHub PR/main CI green via merged PR #194. Owner's English policy was merged by PR #193. Official updater request `01a124d1-4542-7b3a-9e70-a9184fed5927` **SUCCEEDED** UTC `2026-10-10 08:00:21.892859`; live SHA `a803983c0732e5a6aecc18002152853bb433c8b1`, checkout/main `in sync`, public `/health` reported `ok`. Official post-1399 full backup `/var/backups/fanoos/20261010T080004Z-b1d7fb76` independently verified **54** files. In the protected coordinator, read-only original-book neighbor review `printed-page-independent-review-20261010.json` (SHA256 `abe48fab12f05192493af4e144ee582b5c4a9963645af39cc20462a3dcb4d198`) corroborated **13 of 14** previously flagged page label discrepancies, holding one. **Original auditor acceptance and question-content approval remain independent required gates.** W05's two Nowak decisions now independently pass the repaired original auditor but are not source-published. W04 clinical inference and W03 canonical map gaps remain blocked.

Current private operational recovery document:
`/srv/fanoos/shared/research/ops/residency-parallel-finalization-20261010.md`.
This release log supersedes older **393 raw / 234 non-English** as-of counts earlier in the report, without erasing them as historical checkpoints. To continue safely, prioritize authorized W01 1400 14-row atomic source apply and new 1400 assessment version after exact live recheck, verified backup and exclusive publisher guard; then W02 and remaining independently confirmed eligible batches. **Do not treat English exemption as a new source insert.**

## Parallel W02 community-dentistry fully published (2026-10-10)

All **eight** accepted W02 community source decisions were independently revalidated, reconciled to source-free live question IDs and imported **without overwriting earlier completed canonical decision JSON**. The audited optional coordinator-stage importer was introduced by PR #196 and officially deployed at release `541631221633a7889bd854224fc0496a8f998678` after green PR/main CI, synchronized host checkout, verified complete deploy backup and public health `ok`. Inputs stayed in `classification/coordinator/W02-audit-stage/`; original worker source files remained immutable. Canonical SHA-pinned W02 audit in the protected stage: `W02-canonical-verified-20261010.json`, SHA256 `cf67d9ff2e2e0995fe778f6fbede4080d2433b6a86b3ea7b872401e4343e3383`.

For **each** study year, a fresh live export confirmed the accepted question ID was still source-free and its original stem, choices and answer exactly matched the worker-reviewed study; other already-sourced questions in the historical study were neither reclassified nor modified. The individual rollback-only source-only previews, complete frozen assessment preflights and unique atomic receipts verified `questions_changed=choices_changed=answers_changed=0`:

| Year | New source rows | Protected pre-apply full backup (54 verified files each) | Receipt SHA256 | Frozen exam before → after |
| --- | ---: | --- | --- | --- |
| 1402 | 3 | `20261010T084322Z-4ae68199` | `46802e3dcbe675d574858a29e3d167c652842250c9f67fac8fdbbfea4543dbe2` | v4→v5, 243 questions, 3 new source explanations |
| 1400 | 3 | `20261010T084747Z-561c8c90` | `bf0f2f9705adce73f40a9918ffc5757e6bbd5297b1b04856b56b0673a11c7741` | v4→v5, 249 questions, 3 new source explanations |
| 1405 | 1 | `20261010T085148Z-11dea211` | `45ea3743113223825397d2107f122d3adc627765a41d42af3ecc80e5aff00c51` | v4→v5, 246 questions, 1 new source explanation |
| 1399 | 1 | `20261010T085523Z-6bdf3c44` | `5adbc1052d2aaa941d5c725450cb394bc41a17e048bd45bc26484378cfce59fe` | v5→v6, 248 questions, 1 new source explanation |

All preserved official voided answer exclusions from the historical frozen exams. The exact receipt filenames in protected `classification/reports/` are `YEAR-community-W02-source-publish-20261010.json`; original PDFs and questions remain private, temporary page text has been removed, and only short decision evidence is retained. A relay job for the 1400 pre-apply full backup timed out, but the finalized physical backup was subsequently independently verified as a valid 54-file set; a relay timeout is not itself evidence of backup corruption.

**Post-W02 checkpoint:** 1995 residency questions, 1622 with actual source rows, 373 raw without source rows. Of those 373, **159 published residency English questions are officially key-final/reference-exempt** under `docs/PROJECT_PRINCIPLES.md` §3A (158 `final`, 1 `amended`); the true remaining non-English source-review queue is **214**. These are time-stamped counts and should be refreshed after future imports. **Never rerun any of the four completed W02 source applies**. The private reproducibility ledger is `/srv/fanoos/shared/research/ops/residency-parallel-57-dispositions-20261010.md`.

## W03 endodontics 1399 one verified source added after W02 (2026-10-10)

The independently validated 1399 endodontic Q29 from W03 was checked against `torabinejad-endodontics@5e` chapter18 PDF page335; exact phrase and official answer agreed, and a **fresh live one-question unsourced study** matched its original study byte-for-byte (SHA256 `b307e78cd108cb572ec3c2ee80de615ff1e7440cce70fafb3afd736fdfef8c6a`). The repaired original protected canonical provenance auditor accepted this decision, after which the source-only rollback preview independently returned exactly **one** new source and zero changes to question/choices/answer.

The writer used an independently reverified **54-file** full post-W02 backup `/var/backups/fanoos/20261010T085856Z-7ebc1fa7`, finalized before source apply. Its cloud relay job became orphaned on a server reconnect, but the actual on-host backup process and completed manifest were separately inspected; no retry or speculative backup claim was made. The source-only production importer atomically inserted one source row at UTC `2026-10-10T08:59:40Z`, with protected receipt `classification/reports/1399-endodontics-W03-source-publish-20261010.json` SHA256 `0e00fc18d518773a8be3592cf21b286fab6d8a4e75527e915c4d0ba99f3bd3a2`. The canonical 1399 assessed exam moved **v6→v7**; its complete 248-question old/new frozen comparison confirmed no differences outside **one** source explanation. Voided-answer exclusions and past attempts remained unchanged.

**Latest live, after W02 and W03 publications:** `1623 / 1995` residency questions have a real recorded source; `372` have no source record. The 159 key-final, published English questions are citation-exempt by the permanent policy, leaving **213** genuinely outstanding non-English source-classification cases. This user turn published **nine** additional source mappings in total (W02 eight and W03 one); the prior 110-source campaign total therefore becomes 119 net new source mappings. Do **not** reapply any completed W02 or W03 1399 batch.

The independent printed-page scenario for W03 1400 (PDF170 vs printed163), all 14 research-valid W01 1400 radiology decisions, and all other unsupported/ambiguous batches remain **unpublished**. Printed-page corroboration in an independent auditor does not constitute permission to bypass the production source writer. The protected complete per-source ledger and narrow cleanup receipt are under `/srv/fanoos/shared/research/ops/`. Official public documentation about coordinator-staged imports and zero-byte-only cleanup is in `docs/ops/SOURCE_ONLY_PUBLICATION_20261010.md` (PR #197). Historical snapshots in this document must not override the newest live counts above.
