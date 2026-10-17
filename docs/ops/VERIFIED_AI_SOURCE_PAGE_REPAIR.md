# Verified original-PDF page and confidence repair for AI sources

Use `scripts/references/repair_verified_ai_source_pages.php` only for one
existing, published, unreviewed AI source whose official-year edition and
full in-scope chapter are already correct, but whose physical PDF page and
page confidence need an original-book re-audit.

The writer changes only `bank_question_sources.pdf_page`, `pdf_sha256`, and
`confidence_page`. It refuses a confidence below 0.85 or below the stored
score, a partial-scope chapter, an invalid key, changed question/choice/key
fingerprints, a human-reviewed row, an unverified edition PDF/map, or a page
without the quoted evidence and corroborated printed-page sequence. The
source edition, chapter, legacy and printed pages, anchor, question, choices,
answer, review state, frozen assessments, and attempts remain unchanged.

## Protected plan

Store a single-entry plan and its separate independent provenance audit under
`/srv/fanoos/shared/research/classification/reports/`, both mode `0600`. The
audit must be the accepted one-question output of
`audit_private_study_batches.py`, with unchanged live question content and
matching year, subject, chapter, PDF page, and PDF SHA. Bind the plan to the
audit's SHA-256. Include the current source ID, exact edition and chapter,
original PDF page and SHA, book-printed page, a short exact page quotation,
current legacy/printed-page values, anchor hash, question/choice/latest-key
hashes, prior `confidence_page`, and independently accepted confidence.

## Preview and apply

Run the preview from the deployed release as the updater account:

```sh
sudo -u fanoosupd env FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php \
  php scripts/references/repair_verified_ai_source_pages.php \
  --workspace=<workspace-uuid> \
  --plan=/srv/fanoos/shared/research/classification/reports/<plan>.json
```

Apply only after preview succeeds and a complete fresh production backup has
been independently verified:

```sh
sudo -u fanoosupd env FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php \
  php scripts/references/repair_verified_ai_source_pages.php \
  --workspace=<workspace-uuid> \
  --plan=/srv/fanoos/shared/research/classification/reports/<plan>.json \
  --apply --backup=/var/backups/fanoos/<verified-backup> \
  --receipt=/srv/fanoos/shared/research/classification/reports/<unique-receipt>.json
```

After apply, independently read back the source, confidence, and immutable
question/choice/key hashes. Then rerun the official whole-year publication
gate. A page citation with a recorded answer discrepancy remains a scientific
hold even if its chapter and page are structurally present.
