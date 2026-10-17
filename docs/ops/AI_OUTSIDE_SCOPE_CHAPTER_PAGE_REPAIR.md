# Guarded AI outside-scope chapter and page correction

Use `scripts/references/repair_verified_ai_outside_scope_chapter_pages.php`
only when a pre-existing, unreviewed AI source has an independently
re-audited exact source chapter that is absent from the official year and
subject scope. It corrects the source chapter, binds the original PDF page
and SHA, records the printed page and an explicit Persian outside-scope note,
and replaces the old page-confidence score with the accepted re-audit score.

The writer changes only these fields on `bank_question_sources`:

- `node_id`, `page`, `printed_page`, `pdf_page`, and `pdf_sha256`;
- `official_scope_status` and `official_scope_note`;
- `confidence_page`, only when the audit accepts the page at **0.85 or
  higher** and the new score does not lower the stored score.

It preserves the question, choices, official answer, source edition and
anchor, human review fields, frozen assessment versions, and attempts. It
refuses any target page present in the official full or partial scope. For a
partial chapter, the guard evaluates the structured boundary and printed
label; it fails closed when the boundary cannot be parsed or the printed
label is unavailable. It also requires the exact edition PDF SHA, chapter
map, printed-page neighbor sequence, original-page quote, immutable
question/choice/key fingerprints, and an accepted private audit bound to the
same plan.

## Private audit and plan

Store the audit, plan, rollback receipt, apply receipt and independent
readback only in
`/srv/fanoos/shared/research/classification/reports/`, with mode `0600`.
The plan and audit must agree on the year, subject, source ID, old and new
chapter, printed page, physical PDF page, SHA, evidence quote, confidence
and immutable fingerprints. The note must explicitly say
`خارج از محدوده` and keep the official key unchanged. Each plan is limited
to 12 source rows.

The original-page audit should record any key/source discrepancy without
changing or suppressing the official key. An unresolved answer alignment
remains an explicit scientific hold, even when the chapter and page are
recorded for provenance.

## Preview and apply

Run as `fanoosupd` with the official updater runtime configuration. The
preview runs the exact source update in a transaction and rolls it back:

```sh
sudo -u fanoosupd env FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php \
  php scripts/references/repair_verified_ai_outside_scope_chapter_pages.php \
  --workspace=<workspace-uuid> \
  --plan=/srv/fanoos/shared/research/classification/reports/<plan>.json
```

Apply only after a rollback preview succeeds and an independently verified
full backup is no more than four hours old:

```sh
sudo -u fanoosupd env FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php \
  php scripts/references/repair_verified_ai_outside_scope_chapter_pages.php \
  --workspace=<workspace-uuid> \
  --plan=/srv/fanoos/shared/research/classification/reports/<plan>.json \
  --apply --backup=/var/backups/fanoos/<verified-backup> \
  --receipt=/srv/fanoos/shared/research/classification/reports/<unique-receipt>.json
```

After apply, independently re-read each updated source and compare the saved
stem, four-choice and latest-answer fingerprints. Re-run the official
whole-year publication gate. An outside-scope page disposition can satisfy
the structural source inventory only after the exact page and explicit scope
note are present; it never waives unresolved scientific conflicts or
authorizes partial-year exam publication.
