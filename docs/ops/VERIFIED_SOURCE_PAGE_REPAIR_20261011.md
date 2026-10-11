# Controlled repair of existing source PDF pages — 2026-10-11

The approved script scripts/references/repair_verified_source_pages.php repairs ONLY pdf_page and pdf_sha256 on existing, unreviewed AI source rows. It cannot change edition, chapter, printed/legacy page, source anchor, question, choices, key, review record, frozen assessment or attempt. The existing insert-only publisher must be used for genuinely source-free questions.

## Protected plan and evidence

Store each exact reviewed batch as a 0600 private JSON file inside /srv/fanoos/shared/research/classification/reports, with format fanoos.verified-source-page-repair/1, exam_type residency, year, subject, entries (maximum 12). Each entry includes number, source_id, edition, chapter, pdf_page, printed_page, pdf_sha256, expected_legacy_page, expected_anchor_sha256 and a short verbatim evidence quote. Source IDs and existing anchor digests must come from an authenticated current database read-back, never be guessed. No raw question text/book pages enter GitHub.

For 1402 oral-pathology Q65, original official Neville 4e, chapter 6, PDF page 202, printed page 195, PDF SHA-256 6fbc9bcba9003deda2f8fc006ccc4f23f9e15db0bcd0e237c56ddf6788578bb6 and exact evidence phrase "of candidiasis is the least common" were rechecked against the protected original book and its hash-bound map. The existing historical source anchor mentions a nearest edition; this operator does not rewrite it. This Q65 finding is a candidate until the live DB and publication checks succeed.

## Required execution gates

1. Check current GitHub main, official server/updater release, concurrent changes, row identity, official validity, and approved PDF hash. Reject partial-chapter scopes and any human-reviewed row.
2. Run the script with --workspace=<UUID> --plan=<protected-plan.json> as fanoosupd. Default is a live-transaction rollback preview. It must print rollback-preview and count, and an independent database check must confirm no changes.
3. Run all PHP/Python/MySQL integration CI, merge and deploy the approved tool through the official updater. Verify health and release SHA. Back up the entire production set through scripts/ops/backup.php and independently verify the manifest and database.sql; backups must be no more than 4 hours old.
4. Execute the identical plan with --apply --backup=<verified-full-backup> --receipt=<unique-protected-receipt> as fanoosupd. The tool locks and matches the existing source, verifies the source-row original hash and official chapter scope, reopens the original PDF page, checks the actual quote and consecutive printed page labels, then atomically updates only two page fields. Existing PDF page/hash data, reviewed rows and changed preconditions abort the batch.
5. Read back the exact live source IDs, old immutable fields and new page/hash values. Compare original questions/choices/answers and the frozen assessment with the previous version. Any authorized publication is a separate reviewed operation; old versions and attempts must persist.
6. Record plan hash, receipt hash, verified full backup, CI, PR, deployed SHA, site health and remaining counts. Never claim a successful publication on the basis of a dry run or a remote job submission.

The 1402 audit and unresolved 155 clinical cases are in docs/ops/RESIDENCY_1402_CHAPTER_PAGE_AUDIT_20261011.md. No new source completion is assumed merely from this code change.
