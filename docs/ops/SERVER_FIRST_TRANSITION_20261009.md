# FANOOS server-first transition — 2026-10-09

## Goal and owner decision

Remove all mandatory dependence on the owner's laptop. GitHub is authoritative
for versioned source, scripts and docs; the production server is authoritative
for database, approved private reference objects, question sources, and
processing outputs. Continue classifying questions supported by complete,
verified editions on the server. Absent/incomplete editions are pending.

This document records the transition and its remaining technical prerequisites.
It does not authorize a shortcut around the existing classification validator.

**Current clarification (2026-10-10):** the earlier bridge did create
protected temporary page-marked `.txt` indexes. They were removed during the
2026-10-10 cleanup. Its replacement, `scripts/references/verified_reference_pdf.py`,
verifies the approved PDF and reads only selected pages in memory; no text
copy, index or OCR export is created. This dated log remains historical for
its inventory and workspace findings; do not re-run its old extraction steps.

## Owner clarification — production host and access route

The owner confirms that the current FANOOS production server is hosted by
**IranServer (ایران‌سرور)**. SentinelX is optional tooling, not a separate
server and not a required access path. An authorized direct operator/SSH
route may be used when its host identity is verified and credentials are
available in the protected execution context. The provider name alone does
not verify an SSH host key; never bypass strict host-key checking.

## Evidence observed on 2026-10-09

- Production reference library inventory (run on the server via the
  repository's read-only inventory script): 44 catalog editions; 27 approved,
  published, verified private PDFs; 17 without a verified server PDF;
  approved private PDFs total 3,293,239,954 bytes.
- Server filesystem: 58-GB partition, 92% used, approximately 4.9 GB free.
- The private PDFs were verified as objects. No blanket claim has been made
  that all 27 already have complete searchable text or a verified chapter
  map; both properties must be checked before assigning chapters.
- The production database and PDF object store remain the live data sources.
  No question sources have been modified by this principles-transition task.
- Historical year coverage totals in `docs/product/06_QUESTION_FORMAT.md`
  are not a current database audit.
- The previously mandatory laptop SSH and sync flow was removed from
  `docs/PROJECT_PRINCIPLES.md`, `AGENTS.md`, `docs/WORKFLOW.md`,
  `docs/ops/SERVER.md`, `docs/product/09_CHAPTER_CLASSIFICATION.md`,
  `README.md` and `scripts/dev/check-sync.sh`.

## Canonical working layout

The production, protected content-addressed store is
`/srv/fanoos/shared/storage/` and must only be changed through approved
content import APIs/scripts.

The new **server-only, access-controlled working area** is
`/srv/fanoos/shared/research/`, with proposed structure:

```
/srv/fanoos/shared/research/
  tmp/<operation-id>/queries.json                       temporary query input
  tmp/<operation-id>/search-output.json                 temporary search output, if redirected
  bank-sittings/<year>/<sitting>.json  exact site-matching private source file
  classification/decisions/           one decision batch per year and subject
  classification/sittings/            validated classified sittings
  classification/reports/             inventory/validation counts (private)
```

**Provisioned on 2026-10-09:** the above directories were created on the
server with owner `fanoosupd:fanoosupd` and mode `0700`, verified by
read-back. They are presently an **empty workspace**: no verified book
texts, sittings or classification decisions were migrated in this task.
**It is not automatically covered by the normal DB
and content-object backup**. Set up a tested protected backup/restore for this
working area before placing irreplaceable files there, or mirror its durable
artifacts through an existing audited private backup mechanism. Empty folders
do not prove content was migrated.

The current bridge resolves `content_resource_metadata.topic=<edition>`
to the latest approved PDF, verifies its SHA-256 and reads requested pages
without exporting PDF text. Search and chapter mapping read one page at a time;
only short selected evidence quotes and PDF/page provenance enter decisions
and receipts. No page markers, `.txt` indexes or OCR files are created. Use a
space-aware, single-edition workflow and delete the operation's temporary
queries and search outputs after its validated audit receipt is recorded.

## Reproducible continuation

1. Run `scripts/ops/reference-library-inventory.php` with the authorized
   server runtime config; record current approved edition keys.
2. Verify the exact official PDF SHA-256 and page count through the read-only
   bridge; no extracted text file or index is created.
3. Independently verify complete page-index coverage, chapter starts/ends,
   official year scope,
   and the exact site-matching sitting and current source assignments.
4. Select one subject/year with an eligible *exact* official reference.
   Use `classification_batch.py` (nearest fallback disabled by default),
   `find_in_books.py`, `apply_classification.py` and the direct-PDF auditor.
5. Accept only decisions with evidence verified on the exact PDF page, chapter and
   confidence; keep absent/unfound questions pending; preserve human reviews.
6. Generate an inventory report, validate `questions_changed=0`,
   verified backup, audited import, publish and post-state counts.
7. Save decision JSON and run reports privately, and update public
   non-sensitive completion counts with Git SHA and import/backup IDs.

## Validation and rollout criteria

- No new mandatory laptop step anywhere in active top-level runbooks.
- Tests pass for exact-official-edition selection and normal project CI.
- `scripts/dev/check-sync.sh` works from the server without owner's SSH
  secrets or a laptop clone.
- Only green merge-commit `main` goes to production via the updater,
  with backup, smoke checks, SHA read-back and shared-host isolation.
- Distinguish **GitHub commit**, **server deployment**, and **bank import**
  separately in final status reports.
- No bulk text extraction is part of classification. Page reads are transient;
  preserve backup retention and never remove backups or other workloads ad hoc.
