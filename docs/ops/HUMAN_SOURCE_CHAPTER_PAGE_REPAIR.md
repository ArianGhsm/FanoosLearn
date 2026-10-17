# Guarded human-source chapter and PDF-page correction

The versioned operator
`scripts/references/repair_verified_human_source_chapter_pages.php` corrects
the chapter node and binds an exact original PDF page and SHA-256 on an
existing human-origin residency citation. It is for audited source-location
corrections when the current chapter is wrong or lacks its physical PDF page.

The transaction changes only `bank_question_sources.node_id`, `pdf_page`, and
`pdf_sha256`. It preserves the existing edition, legacy page fields, anchor,
human origin, reviewer metadata, question and choices, official answer, frozen
assessment versions, and attempts. Both current and corrected chapters must
be complete chapters in the official exam-year scope. Partial chapter scopes,
new citations, multi-source insertions, and outside-scope records require a
different reviewed workflow.

## Required private evidence

The protected plan and its SHA-pinned private audit bind the exact source row,
original and corrected chapter, year, subject, edition, PDF page and current
approved PDF hash. Each accepted audit record includes the short original-page
evidence, question/choice/key/anchor fingerprints, and question and answer
statuses. Before preview, independently confirm the selected page against the
server's verified original PDF and the official syllabus. The operator then
rechecks the audit/plan identity, live source and question fingerprints,
official scope, hash-bound chapter map, and exact-page quotation.

Store these records only under
`/srv/fanoos/shared/research/classification/reports/`, mode `0600`. Never put
question text, book pages, reports, plans, receipts, or production data in Git.

## Preview and apply

Run as `fanoosupd` with the updater runtime configuration. Preview executes the
same source-only update inside a transaction and rolls it back:

```sh
sudo -u fanoosupd env FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php \
  php scripts/references/repair_verified_human_source_chapter_pages.php \
  --workspace=<workspace-uuid> \
  --plan=/srv/fanoos/shared/research/classification/reports/<plan>.json
```

After a successful preview, independently verify a fresh full production
backup with `sudo -u fanoosupd php scripts/ops/verify-backup.php
/var/backups/fanoos/<backup-id>`. Then apply using that verified backup and a
previously unused receipt path:

```sh
sudo -u fanoosupd env FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php \
  php scripts/references/repair_verified_human_source_chapter_pages.php \
  --workspace=<workspace-uuid> \
  --plan=/srv/fanoos/shared/research/classification/reports/<plan>.json \
  --apply --backup=/var/backups/fanoos/<verified-backup> \
  --receipt=/srv/fanoos/shared/research/classification/reports/<unique-receipt>.json
```

The operator requires the backup manifest and database dump to verify and the
backup to be no older than four hours. It refuses duplicate question numbers,
changed source/question/key state, a human review-state change, a partial or
out-of-scope chapter, an unmapped page, a changed PDF hash, missing direct-page
evidence, or a non-unique replacement chapter node. A preview report must say
`mode=rollback-preview` and report zero question, choice and answer changes.
After apply, hash the private receipt and independently read back each source
row and the year-level paired/incomplete counts before continuing.

## Validation

The pure guard is covered by
`tests/Operations/VerifiedHumanChapterPageRepairGuardTest.php`; the SQL
mutation contract is covered by
`tests/references/test_human_source_chapter_page_repair.py`. Integration and
source-page validation still run through the real protected operator in its
production context; the static unit suite never connects to production.
