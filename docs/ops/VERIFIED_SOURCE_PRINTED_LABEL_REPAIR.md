# Guarded printed-page label repair

Use `scripts/references/repair_verified_source_printed_labels.php` when an
AI-origin source already has an independently verified `pdf_page` and
`pdf_sha256`, but its printed-page label is missing. The operator fills only
`bank_question_sources.printed_page`; it preserves the source's chapter,
legacy page string, PDF binding, confidence, anchor, question, choices, key,
review state, frozen assessments and attempts.

The protected audit and plan bind the source ID, chapter, existing PDF page
and SHA, new printed label, confidence, question/choice/key fingerprints and
official page-scope disposition. The operator rechecks the current approved
PDF, exact chapter map, neighboring printed-page sequence, structured
partial-page scope, source state and immutable question/key fingerprints.
Unknown page boundaries stay pending.

Store plans, audits, receipts and readbacks only in
`/srv/fanoos/shared/research/classification/reports/`, mode `0600`. Plans
are limited to 12 rows. Preview is a rollback-only transaction. Apply
requires the authorized `fanoosupd` service account, a verified full backup
no older than four hours and a unique receipt path. After apply, independently
read back the printed labels and verify the questions, choices, answers,
assessment version and year-level gate.
