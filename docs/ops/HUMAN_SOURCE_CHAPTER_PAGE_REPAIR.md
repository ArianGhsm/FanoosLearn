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

## Explicitly out-of-scope human citations

For a source whose exact original-book chapter is **absent** from the official
year/subject scope, use
`scripts/references/repair_verified_human_outside_scope_chapter_pages.php`.
It changes only the existing source row's chapter, page, printed-page/PDF
binding and outside-scope status/note. The note must explicitly say in Persian
that the item is outside the exam scope. It rejects any chapter listed in the
official scope, including partial chapters; a page beyond a partial boundary
needs a separate boundary-aware review. The exact printed page must be
corroborated on the original PDF page and its neighbors. Long running headers
are accepted only when the plan records that case and the neighboring PDF
pages prove the sequential labels.

The status is a classification finding, not a scoring change or release
waiver. The question, choices, key, review data, frozen assessments and
attempts remain unchanged, and an outside-scope row does not count as an
in-scope chapter for the whole-year publication gate.

The private plan/audit use format
`fanoos.verified-human-outside-scope-chapter-page-repair/1`; apply uses the
same workspace, backup and unique-receipt arguments as the in-scope operator.
Require its rollback preview and independent receipt/DB readback before the
next batch.

## Validation

The pure guard is covered by
`tests/Operations/VerifiedHumanChapterPageRepairGuardTest.php`; the SQL
mutation contract is covered by
`tests/references/test_human_source_chapter_page_repair.py`. Integration and
source-page validation still run through the real protected operator in its
production context; the static unit suite never connects to production.
