# Verified multi-page citations for voided residency questions

Use `scripts/references/repair_verified_voided_source_page_bundle.php` when a
draft residency question has an official `voided` key and its exact design
location needs two to four pages from one official edition and one chapter.
The route keeps the existing primary source row, binds its selected primary
page, and appends every other audited page as a secondary source.

## Requirements

- The original question is `draft`, the latest official key is `voided`, and
  its `choice_position` is `NULL`.
- The source is a single unreviewed AI citation in an official year/subject
  edition. It must point to the same chapter and approved PDF as the bundle.
- Each page has a corroborated printed label, is inside the exact SHA-bound
  chapter map, and is within the official chapter/page scope.
- A private accepted original-PDF study audit pins the unchanged stem, four
  choices, voided key, source row and every page-local evidence fragment.
- The primary page must be one of the bundled pages. Other pages become
  non-primary citation rows with confidence at least 0.85.

The operation changes only citation metadata in `bank_question_sources`. It
does not create a key, publish or score the question, update an assessment, or
change attempts or reviews. It refuses source-free questions, human-reviewed
citations, duplicate pages, partial-scope pages without a clear printed label,
and any altered original question or key identity.

## Preview and apply

Store the plan, audit and receipts with mode `0600` under the protected
classification reports directory. Run the rollback preview as `fanoosupd`:

```sh
FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php \
  php scripts/references/repair_verified_voided_source_page_bundle.php \
  --workspace=<workspace-uuid> \
  --plan=/srv/fanoos/shared/research/classification/reports/<plan>.json
```

After a successful preview, take and independently verify a fresh full backup,
then apply with a new receipt path:

```sh
FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php \
  php scripts/references/repair_verified_voided_source_page_bundle.php \
  --workspace=<workspace-uuid> \
  --plan=/srv/fanoos/shared/research/classification/reports/<plan>.json \
  --apply --backup=/var/backups/fanoos/<verified-backup> \
  --receipt=/srv/fanoos/shared/research/classification/reports/<unique-receipt>.json
```

Independently read back the primary and secondary source rows, all four
unchanged choices, the still-voided null key, assessment version/reviews and
attempt counts. Then rerun the actual whole-year publication gate. A source
bundle is classification work only and never authorizes a partial-year
assessment publication.
