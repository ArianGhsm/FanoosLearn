# Residency 1397 updated-reference classification — 2026-10-21

## Owner instruction and safeguards

The owner requested that the residency 1397 sitting be classified using the
newer reference set and explicitly approved the residency 1406 list as the
updated-reference basis. This is a task-specific exception to the default
exact-exam-year reference rule. It does not claim that a 1406 book appeared
on the 1397 official list. Every such citation is stored with
`classification_basis=updated_reference_proxy`, `reference_year=1406`, and
`official_scope_status=outside_scope_exam_question`, with a Persian note
naming both years. The exact 1406 source, PDF page, current PDF SHA, chapter
map, decisive page evidence and official 1406 subject scope must all pass
independent validation.

The exception covers only residency 1397 and the 12 cataloged clinical
subjects. It does not change `catalog.validity`, answer keys, question text,
choices, statuses, assessments, reviews, attempts or scores. The whole-year
assessment publication gate remains in force.

## Rechecked baseline

The server is on release and updater `472670dd67a49dafb5f689e761f957e3c19b3b44`,
with `/health` and the server-first sync check healthy. The protected prior
checkpoint
`/srv/fanoos/shared/research/classification/reports/residency-1398-1397-progress-checkpoint-20261019.json`
has SHA-256
`65c9ba49bae95512f9d2280348b980e6bd4fb31d60a35a99a6221523ff21c44c`.

The live source-free export on 2026-10-21 confirms 164 of 227 book-based
questions need new source rows. The 247-question sitting has 20 English
questions with valid keys exempt from book citations. Another 63 book-based
questions already have structurally complete source rows; 11 of those source
rows are below the 0.85 confidence threshold and require evidence-backed
resolution. Existing citations and the accepted nine-question endodontic
audit are preserved; no production source write has occurred under this
operation yet.

All 19 official 1406 residency reference-validity rows have an approved
private PDF and a chapter map whose recorded hash matches the live PDF hash.
The server inventory reports 42 ready reference PDFs. Current maps and scope
were checked for every 1406 subject before this exception was added.

## Guarded code change

The deployed 1398 Hupp proxy guard did not permit a 1397 source-only batch.
This branch extends the same protected route with a 1397/1406 proxy case,
whitelisted subject/count batches, explicit outside-scope notes, independent
page audit, current official 1406 scope lookup, fresh verified backup,
rollback preview, a unique private receipt and readback. The source-only
writer remains limited to `INSERT` on `bank_question_sources` and rejects
changes to any question, choice or answer. No raw SQL writer, alternate
importer or catalog validity workaround is permitted.

## Current continuation state

- Research inputs are private and isolated under
  `/srv/fanoos/shared/research/tmp/residency-1397-classification-20261021/`.
- Current work branch: `codex/residency-1397-classification`, based on
  `472670dd67a49dafb5f689e761f957e3c19b3b44`.
- Source changes and targeted tests are in progress; no PR, deployment,
  backup for new writes, or 1397 database apply has occurred yet.
- Before any import, require green CI on the merged change, official updater
  deployment, a fresh verified full backup, successful dry-run/rollback
  preview, independent audit SHA, and source-only readback.
- Complete the 11 low-confidence existing rows with the appropriate
  evidence-backed correction operator; never silently raise confidence or
  replace a human-origin source.
- Final verification must show 227/227 book-based questions with same-row
  chapter, positive PDF page and current PDF SHA, 20/20 valid-key English
  exemptions, no source below 0.85, zero question/choice/key changes, and
  unchanged frozen assessment history. Recheck 1398 from the live database
  before closing this task.
