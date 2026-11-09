# Classifier v3 (read-only research core)

Replaces the legacy hourly `fanoos-classifier` orchestrator (timer stays DISABLED).
No model call per question; Opus only for consequential adjudication.

Pipeline: live study export -> `plan` (exemption/voided/duplicates, versioned
edition policy table, map availability) -> `scan` (one pass per approved
edition, one PDF page at a time via `verified_reference_pdf.py`, only numeric
term counts kept) -> adjudication writes Decision rows -> `verify` (re-reads only
the cited page; same-edition SHA, physical page, chapter from the same edition's
map, verbatim quote <=40 words, confidence >=0.85, scope/proxy Persian notes;
independent second check) -> `package` (<=40 rows, source-only, hashed).

Apply is NOT done here: `import_verified_sources.php --apply` requires
`fanoosupd`, a fresh (<=4h) verified backup, a re-exported study matching the
package, and independent readback. `manual_reconciliation_required` => inspect
receipt, never retry blindly. A batch apply never authorizes year publication
(whole-year gate, AGENTS.md §3A).

Never: full-book text, OCR, index, page dumps, PDF copies, DB writes.
Tests: `python3 -B -m unittest tests/references/test_classifier_v3.py`.
