# Verified supporting citations for an already-sourced residency question

This route appends one or two citations to a published residency question
that already has exactly one source row. It is for questions whose complete
answer requires more original-book evidence than the single existing row
contains, including multiple pages in one chapter. It does not edit or
replace the original row, question, choices, official key, frozen assessment,
review record, or attempt.

## Requirements

- The original source row must already be bound to its exact approved private
  PDF SHA and physical page. Its ID, chapter, printed/PDF pages, SHA and
  original anchor hash are pinned and left unchanged. A separate exact quote
  in the audit must validate on that page; this also handles original PDF
  text layers that split an unchanged historical anchor across columns.
- Every added citation must use the exact official-year edition, a fully
  in-syllabus chapter, the current SHA-bound chapter map, and a short exact
  quotation checked on the original PDF page.
- The private research audit pins the unchanged study and original source
  identity, and lists each added page, chapter, PDF SHA, and exact quote.
- The private plan pins the question, ordered four choices, latest final or
  amended official answer, and original source identity. Pages must be unique
  by edition, chapter, and physical PDF page.
- The guarded operator previews the full transaction and rolls it back.
  Apply requires a fresh independently verified full backup, the authorized
  `fanoosupd` account, and a unique protected receipt. New rows are secondary
  sources with confidence `0.950`; existing rows remain byte-for-byte intact.

## Operator

```sh
php scripts/references/insert_verified_supporting_sources.php \
  --workspace=<authorized-workspace-uuid> \
  --plan=/srv/fanoos/shared/research/classification/reports/<plan>.json

php scripts/references/insert_verified_supporting_sources.php \
  --workspace=<authorized-workspace-uuid> \
  --plan=/srv/fanoos/shared/research/classification/reports/<plan>.json \
  --apply \
  --backup=/var/backups/fanoos/<fresh-verified-backup> \
  --receipt=/srv/fanoos/shared/research/classification/reports/<unique-receipt>.json
```

The operator rejects source-free questions, questions with more than one
existing source, reviewed/non-AI original sources, partial or unspecified
chapter scope, stale source identity, stale original PDF hashes, changed
question/choice/key fingerprints, duplicate citation pages, and unsupported
quotes. Do not substitute the source-free two-page insertion operator for
this route; its stronger source-free precondition is intentional.

## Receipt and independent readback

The apply receipt records the exact source IDs/pages/hashes inserted and
states that the original source, question, choices, key, and assessment were
unchanged. A separate read-only production query must confirm both the
original and new rows, unchanged official content and assessment version,
and the whole-year publication gate must be rerun. This source-only action
does not authorize a partial-year exam publication.
