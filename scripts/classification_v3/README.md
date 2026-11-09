# Classifier v3 — read-only research SKELETON (not production-ready)

Status: prototype core + tests. It does NOT import, back up, publish or write
the DB. Legacy `fanoos-classifier.timer` stays DISABLED; legacy trees are kept
until v3 is proven on a live pilot.

## What it does
- `plan`: groups live-export rows by edition. Every (workspace_id, id) row is
  kept; identical content in other rows/exam types is never dropped. Editions
  come only from a policy table whose entries carry `provenance` (official list
  id or named owner rule). No table/provenance => `pending` with exact reason.
  Board/promotion/national/residency policy tables are NOT yet generated from
  the live official-list inventory — that is a blocker, not a default.
- `scan`: RAM-only single-page multi-query search. One pass per approved
  edition; each page is read alone via `verified_reference_pdf.py`, tokenised,
  reduced to numeric counts for the batch's query terms, then discarded. No
  text, OCR, index or page dump is written.
- `verify`: PRECHECK only (self-check: SHA, physical page, same-edition chapter
  map, verbatim 4–40-word quote, conf>=0.85, scope/proxy Persian notes). Output
  is `audit_pending`, never approval.
- `package`: refuses any row lacking an independent audit (different auditor,
  `audit_private_study_batches.py` or separate page reopen, private receipt file
  whose SHA-256 matches, identical id/SHA/page/chapter/quote hash).

## Not implemented (blockers)
- Printed-page-label corroboration via the edition bridge.
- Mapping the package onto `import_verified_sources.php` input schema.
- Apply: `import_verified_sources.php --apply` requires euid `fanoosupd`; this
  worker has no sudo. Proposed least-privilege host bridge (needs owner/root,
  reviewed PR, NOT installed): a root-owned spool dir writable by fanoosworker
  (packages only) and a fanoosupd oneshot that, per package: re-exports the
  study from live DB and compares hash; requires a verified full backup <=4h
  (or takes one via the updater backup path); runs the importer dry run then
  apply; does independent readback; writes an atomic receipt; on
  `manual_reconciliation_required` stops the queue (no retry).
- Batch source-only apply never authorizes publication; the whole-year gate
  (AGENTS.md §3A) is separate.

Tests: `python3 -B -m unittest tests/references/test_classifier_v3.py`.
