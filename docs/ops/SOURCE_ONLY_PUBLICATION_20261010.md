# Controlled source-only publication — 2026-10-10

**Objective:** after each evidence-validated residency question batch, safely
publish its exact official book, chapter node and page to the live bank,
without rewriting a question, option, image, answer key, status or user review.
This is an *additional*, tightly constrained operation, not a sitting import.

Authority: `docs/PROJECT_PRINCIPLES.md`,
`docs/product/09_CHAPTER_CLASSIFICATION.md` and
`docs/ops/RESIDENCY_CLASSIFICATION_EXECUTION_20261010.md`.

## Implementation

- `apps/platform/src/Bank/SourceOnlyPublisher.php` is a single-transaction
  source-only writer. The **only data mutation** in it is `INSERT INTO
  bank_question_sources`. It executes either in a rollback-only preview
  or an atomic commit of one year/subject batch.
- `scripts/references/import_verified_sources.php` enforces on-host private
  research files, an exact previously verified SHA-256 provenance audit,
  requested batch count, and (for apply) a **fresh full verified backup**.
  Apply is restricted to the `fanoosupd` OS account, requires a unique
  private receipt, and never accepts 1398 until its syllabus is verified.
- Live lock (`FOR UPDATE`) and exact-match checks guarantee published
  `bank_questions.stem`, all `bank_question_choices`, and the latest
  `bank_official_answers` remain identical to the study input.
- Every accepted mapping must be `ai`/primary, match the independently
  verified decision's exact reference edition/chapter/page and evidence
  text, have confidence at least 0.85, have **no existing source row**,
  resolve to an existing `chapter` node, and appear in the
  **year- and subject-specific official `bank_reference_validity`** with
  `scope_chapters` explicitly listing that chapter.
- If one source is rejected, **none** of that batch's source rows commit.
  Re-running an already imported batch fails instead of overwriting AI or
  human-reviewed sources. Never suppress these failures.
- Source-only inserts become available in the **bank** immediately; however,
  already-published assessments use immutable **version snapshots**. Students
  will not see new source explanations in the existing version until the
  authorized BankPublisher review/publish process produces a new assessment
  version. Publication is a separate audited action, **not** a side effect
  of the source-only row insertion. Preserve all previous versions/attempts.

## Allowed research batches

The 2026-10-10 SHA-verified audit
`/srv/fanoos/shared/research/classification/reports/residency-audit-20261010.json`
(SHA-256 `43ec6f2d23ed1b2c2a4717be47bbf20d1609b603887607f42157df564f64c4b8`)
contains 74 evidence-validated mappings across eight batches.

**Eligible for this release: 64** mappings:

| Year | Subject | stem | Expected |
|---|---|---|---:|
| 1399 | community-dentistry | community | 9 |
| 1400 | community-dentistry | community | 5 |
| 1401 | community-dentistry | community | 10 |
| 1402 | community-dentistry | community | 7 |
| 1403 | community-dentistry | community | 9 |
| 1405 | community-dentistry | community | 9 |
| 1405 | oral-radiology | radiology | 15 |

**Hold:** 1398 community-dentistry (10 verified research mappings)
because the actual announced syllabus scope is missing. The other
16 undecided questions are also on hold. Never default to nearest editions.

## Release, preflight, apply and verification

1. Complete all PHP lint, 18 Python reference tests and full GitHub PR
   CI. Merge into `main`, check the latest exact main SHA CI is green,
   then deploy through the documented official updater only; confirm
   `scripts/dev/check-sync.sh` and `/health`.
2. As authorized `fanoosupd`, **regenerate the study-only export from
   live DB** for the specific batch to a new private filename using
   `export_server_candidates.php --workspace=<verified-uuid>
   --year=<year> --subject=<subject>`; use `cmp -s` against its archived
   `<stem>-study.json`. Never use `import-bank.php` for these files.
3. Run the original `apply_classification.py` on the decisions against
   protected page-marked book text. It must say `0 rejected`. Re-run
   `audit_private_study_batches.py` for the original full eight batches,
   which must say `90 questions; 74 accepted; 16 pending`.
4. Preview a single batch with an explicit expected count (transaction
   inserts and rolls back, checking real FK and question content):

   ```sh
   FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php php \
     scripts/references/import_verified_sources.php \
     --workspace="<approved dentistry workspace UUID>" \
     --year=1403 --subject=community-dentistry --stem=community \
     --expected=9 \
     --audit=/srv/fanoos/shared/research/classification/reports/residency-audit-20261010.json
   ```

   Expect `applied=false`, `source_rows=9`,
   `questions_changed=choices_changed=answers_changed=0`.
   A real preview was performed for **all seven batches** on 2026-10-10,
   yielding accepted counts 9,5,10,7,9,9,15 and no preflight rejection.
5. Immediately before the first apply, take a **new** authorized full
   FANOOS backup via `scripts/ops/backup.php`, verify using
   `scripts/ops/verify-backup.php` and the backup manifest. Review
   retention and free disk first (do not delete other workloads or
   private question evidence). The importer re-verifies the full backup
   checksum and requires the backup to be no older than four hours.
   The research archive is a separate on-host recovery set; do not
   confuse it with a verified database-and-objects full backup.
6. Apply that **same, just-previewed batch** as `fanoosupd`:

   ```sh
   FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php php \
     scripts/references/import_verified_sources.php \
     --workspace="<approved dentistry workspace UUID>" \
     --year=1403 --subject=community-dentistry --stem=community \
     --expected=9 \
     --audit=/srv/fanoos/shared/research/classification/reports/residency-audit-20261010.json \
     --apply --backup="<full verified backup path>" \
     --receipt=/srv/fanoos/shared/research/classification/reports/1403-community-source-publish-20261010.json
   ```

   A successful transaction adds only the new source rows and writes a
   private unique JSON receipt. **Never claim success before checking both
   the receipt and production database.**
7. Re-query the exact batch joined to `bank_question_sources` and
   `bank_reference_nodes`, confirm count and page, `origin=ai`,
   full published question count, remaining unsourced backlog, and site
   `/health`. Confirm questions/options/answers were not changed via
   comparison to original study re-export. Record exact backup ID,
   release SHA, batch receipt SHA and verified before/after counts.
8. **Frozen-assessment preflight**: run
   `scripts/references/audit_published_assessment.php --workspace=UUID
   --year=YYYY --subject=SUBJECT --stem=STEM` (read-only). All 10/20
   reviewed study questions must match the currently published frozen
   version's stem, options and official answers. This preflight passed for
   all seven eligible batches on 2026-10-10 (7/7, including both 1405
   subjects). Stop if a question differs; never force publication.
9. **Update the frozen student assessment version** after validated bank-source
   inserts, using the *official* `import-bank.php publish --type=residency
   --year=YYYY --round=1 --workspace=UUID --actor=AUTHORIZED_UUID
   --reviewer=AUTHORIZED_UUID` path. This action deliberately creates a **new
   published version** (draft → review → publish) and preserves earlier
   attempts/versions. Before invoking, compare existing frozen questions,
   options and answers to current bank questions and confirm no content drift;
   after invoking, compare new and previous question definitions **excluding
   source-derived explanation/location**, assert they are identical, and
   confirm only new source citations appear. Do not republish blindly if
   existing assessment content already differs. For years with more than one
   accepted batch (e.g. 1405), apply both batches and publish one new version
   after their individual preflight+receipt checks to avoid redundant versions.
10. After publishing, re-run the frozen-assessment audit with
    `--previous=<old_version_no> --current=<new_version_no>`. It rejects
    changes to any field of the entire exam's frozen question list **except
    the source-derived explanation**. Record the old and new version
    numbers, and ensure existing attempt history is preserved.
11. Repeat steps 2–10 for other eligible years, using a distinct receipt per
   batch and logging each new assessment version ID. Do not send study-only
   files to the general question-bank importer.
12. Update the canonical operations documentation and checkpoint. Do not
   claim offsite backup merely from an on-host archive.

## Actual execution report — completed 2026-10-10

The entire source-only publication workflow **has now been exercised in
production**. See the authoritative, hash-audited execution log
[`RESIDENCY_LIVE_CLASSIFICATION_20261010.md`](RESIDENCY_LIVE_CLASSIFICATION_20261010.md)
for release SHA, updater request, verified full backup IDs, all private
receipt hashes, before/after counts, student assessment version numbers
and 11 additional verified 1403 oral-radiology chapter mappings.

- PR #186 was merged as `f7e5065`; all 5 main CI jobs passed,
  including MySQL integration; the official updater completed its
  request successfully, and GitHub main/live/updater sync and health
  checks passed.
- Seven original exact-reference batches received **64 source-only
  inserts**, with live preflight, protected SHA-256 receipts and
  zero question/choice/answer changes.
- The six affected assessments were safely re-published using the
  canonical review flow; full frozen exam comparisons confirmed
  unchanged non-explanation content and preserved old versions.
- A second separately verified full backup preceded the next
  1403 oral-radiology batch: **11 additional source-only inserts**,
  followed by the reviewed/published 1403 assessment version 4 and
  an unchanged 250-question postpublish diff.
- Total verified production additions: **75**, resulting in
  **416 currently unchaptered out of 1995 residency questions**.
  The 1398 community 10 research decisions remain excluded due to
  missing official syllabus scope, and unresolved questions stay
  queued; no edition substitutions.
- Private research/on-host backup archives are checksum-verified but
  are **not** claimed as independent offsite backups.

## Latest source-only production checkpoint: 1402 radiology

As of 2026-10-10, **89** source-only mappings have been safely published
across the ongoing campaign; the current residency backlog is **402 / 1995**
(1593 already chaptered). The older **75 / 416** figures elsewhere in this
document are historical checkpoint counts.

The newly completed `1402:oral-radiology:radiology` batch used the
exact announced White-Pharoah 8e edition and scope. Fourteen of the twenty
live study questions passed the original book-page validator, six remain
unresolved, and none was rejected. The independently regenerated live
export matched the private study file byte-for-byte. A private SHA-256
provenance audit, verified on-host archive, strict source-only preview,
independently verified eligible full backup and atomic apply all passed.
No question/choice/official-answer mutation was permitted or observed.

The canonical assessment publisher appended **1402 version 3 → 4**.
Read-only postpublish audit confirmed **243** frozen questions unchanged
except 14 source-derived explanations; `/health` returned 200.
Private receipt:
`/srv/fanoos/shared/research/classification/reports/1402-radiology-source-publish-20261010.json`,
SHA-256 `e0d7b9e45ecfb4e604b51f08a921c9e54e9fc3fbef709440a7565367b759d8e1`.

**Full-backup chronology:** the additional backup timed out over the
Sentinel relay but **later finalized on-host** as
`20261009T233920Z-f8f4ee51`, with READY and an independently verified
54-file manifest. Because it finalized **after** the 14-row source apply,
that backup was not relied upon to authorize the mutation. The apply instead
used the already completed, independently verified 54-file
post-prior-publication backup `20261009T220905Z-eee036a3`, still within
the enforced four-hour freshness window. All other checkpoints are described in
`RESIDENCY_LIVE_CLASSIFICATION_20261010.md`. This documentation change
contains no private questions or book text; future workers must not reapply
a completed batch.
