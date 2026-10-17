# Verified original-PDF page and confidence-vector repair for AI sources

Use `scripts/references/repair_verified_ai_source_pages.php` only for one
existing, published, unreviewed AI source whose official-year edition and
chapter are already correct, but whose physical PDF page or AI confidence
vector needs an original-book re-audit. It can fill a missing page or re-score
a page already bound by a previous guarded repair. Full chapters and
structured partial chapters are accepted only when the verified printed page
falls inside the official boundary.

The writer changes only `bank_question_sources.printed_page`, `pdf_page`,
`pdf_sha256`, `confidence_source`, `confidence_node`, and `confidence_page`. All three
accepted confidence values must be at least 0.85, match the independent audit,
and be no lower than any stored value. A partial chapter is accepted only when
its verified printed page falls inside the explicit official boundary; the
operator stores that label so the whole-year gate can verify the same bound.
It refuses an excluded or unparsed partial page, invalid key, changed
question/choice/key fingerprints, human-reviewed row, unverified edition
PDF/map, or a page without quoted evidence and a corroborated printed-page
sequence. The source edition, chapter, legacy and
printed pages, anchor, question, choices, answer, review state, frozen
assessments, and attempts remain unchanged.

## Protected plan

The single-entry plan format is
`fanoos.verified-ai-source-page-repair/3`.

Store a single-entry plan and its separate independent provenance audit under
`/srv/fanoos/shared/research/classification/reports/`, both mode `0600`. The
audit must be the accepted one-question output of
`audit_private_study_batches.py`, with unchanged live question content and
matching year, subject, chapter, PDF page, PDF SHA, and all three audited
confidence values. Bind the plan's accepted confidence exactly to the audit
and bind the plan to the audit's SHA-256. Include the current source ID, exact
edition and chapter, current PDF page/SHA (or both null), original PDF page
and SHA, book-printed page, one or more short exact page
quotation fragments (use separate fragments only when original PDF column
order splits the text layer),
current legacy/printed-page values, anchor hash, question/choice/latest-key
hashes, prior `confidence_source`, `confidence_node`, and `confidence_page`,
and independently accepted confidence. The plan must also pin
`expected_pdf_page` and `expected_pdf_sha256` together: both null for a missing
page, or both equal to the already verified page/PDF pair when only confidence
needs repair.

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
