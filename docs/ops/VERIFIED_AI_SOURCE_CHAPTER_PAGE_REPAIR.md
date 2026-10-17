# Verified AI source chapter and page correction

Use `scripts/references/repair_verified_source_chapter_pages.php` for one
existing, published, unreviewed AI source when the current chapter or printed
page is wrong and direct original-PDF evidence supports a corrected location
in the exact official edition and year scope. The operator supports residency
years 1396–1500 and fails closed on every other exam type.

## Fields and safeguards

The guarded transaction changes only `bank_question_sources.node_id`,
`printed_page`, `pdf_page`, `pdf_sha256`, `confidence_source`,
`confidence_node`, and `confidence_page`. It preserves the source edition,
legacy `page`, original anchor, question, four choices, latest official key,
review provenance, frozen assessments, and attempts. The source must remain
AI-origin and unreviewed.

The single-entry format is
`fanoos.verified-source-chapter-page-correction/2`. Its protected plan must
pin the previous and corrected chapter, previous and accepted printed page,
legacy page, source ID, approved PDF SHA, physical PDF page, exact short page
evidence, anchor hash, question/choice/key hashes, and previous confidence
for all three source confidence fields. It applies only when the prior
`pdf_page` and `pdf_sha256` are both null; already-bound rows use the guarded
page/confidence repair instead. The separate accepted one-question
`audit_private_study_batches.py` report must match the year, subject, chapter,
PDF page, PDF SHA, unchanged question content, and all three accepted
confidence scores. The plan pins the audit SHA-256.

The operator also verifies exact official-year reference validity, full
chapter or page-bounded scope, mapped chapter containment, actual book-page
label with neighboring-page corroboration, and the exact page quote. The
accepted confidence must be at least 0.85 and no lower than any stored
confidence. It updates the three confidence values only to the score in the
accepted audit.

## Preview and apply

Store the plan and audit under protected server research directories with
mode `0600`. The audit can be in the flat reports directory or the matching
`classification/recovery-<year>-<case>/classification/reports/` directory.

Run the rollback-only preview from the deployed release as `fanoosupd`:

```sh
sudo -u fanoosupd env FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php \
  php scripts/references/repair_verified_source_chapter_pages.php \
  --workspace=<workspace-uuid> \
  --plan=/srv/fanoos/shared/research/classification/reports/<plan>.json
```

Apply the exact plan only after preview passes and a complete fresh backup is
independently verified:

```sh
sudo -u fanoosupd env FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php \
  php scripts/references/repair_verified_source_chapter_pages.php \
  --workspace=<workspace-uuid> \
  --plan=/srv/fanoos/shared/research/classification/reports/<plan>.json \
  --apply --backup=/var/backups/fanoos/<verified-backup> \
  --receipt=/srv/fanoos/shared/research/classification/reports/<unique-receipt>.json
```

After apply, independently re-read the source row and compare its source,
chapter, page, confidence, question, choices, and official-key fingerprints.
Rerun the whole-year publication gate. A page citation with an unresolved
question/key discrepancy remains a scientific hold.
