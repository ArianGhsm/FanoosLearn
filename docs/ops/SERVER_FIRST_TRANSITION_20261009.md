# FANOOS server-first transition — 2026-10-09

## Goal and owner decision

Remove all mandatory dependence on the owner's laptop. GitHub is authoritative
for versioned source, scripts and docs; the production server is authoritative
for database, approved private reference objects, question sources, and
processing outputs. Continue classifying questions supported by complete,
verified editions on the server. Absent/incomplete editions are pending.

This document records the transition and its remaining technical prerequisites.
It does not authorize a shortcut around the existing classification validator.

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
  references/<edition>.txt            full page-marked text after verification
  bank-sittings/<year>/<sitting>.json  exact site-matching private source file
  classification/decisions/           one decision batch per year and subject
  classification/sittings/            validated classified sittings
  classification/reports/             inventory/validation counts (private)
```

This structure can be provisioned with mode 0700 under a dedicated authorized
FANOOS operator account. **It is not automatically covered by the normal DB
and content-object backup**. Set up a tested protected backup/restore for this
working area before placing irreplaceable files there, or mirror its durable
artifacts through an existing audited private backup mechanism. Empty folders
do not prove content was migrated.

Scripts currently accept the `--local` argument pointing to that root.
The future secure source bridge must read the approved object file associated
with `content_resource_metadata.topic=<edition>` through an authorized,
audited accessor and never expose those objects to public URLs.
Use a space-aware, single-edition workflow and verify PDF page markers.

## Reproducible continuation

1. Run `scripts/ops/reference-library-inventory.php` with the authorized
   server runtime config; record current approved edition keys.
2. Verify a secure reader and backed-up protected workspace; only then
   materialize or convert one eligible edition.
3. Independently verify full text, chapter starts/ends, official year scope,
   and the exact site-matching sitting and current source assignments.
4. Select one subject/year with an eligible *exact* official reference.
   Use `classification_batch.py` (nearest fallback disabled by default),
   `find_in_books.py`, and `apply_classification.py` with `--local`.
5. Accept only decisions with exact page text evidence, chapter and
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
- Do not run large batch text extraction while disk remains close to full.
  Retention changes must use the existing supervised script's dry-run and
  never delete backups or other workloads by ad-hoc commands.
