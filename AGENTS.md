# FANOOS agent rules — server-first

These rules bind every human or AI agent. **Start with
`docs/PROJECT_PRINCIPLES.md`** (owner's 2026-10-09 operating decision);
where these or any old runbook differ, the principles prevail.

## 0. Read first

1. `docs/PROJECT_PRINCIPLES.md` — two authorities (GitHub and server),
   durable continuity and classification evidence.
2. `docs/product/05_DENTAL_RESIDENCY_DATA_MODEL.md` — model.
3. `docs/WORKFLOW.md` — GitHub PR, CI, updater deployment.
4. `docs/ops/SERVER.md` — production layout, protected data and operators.
5. `docs/product/09_CHAPTER_CLASSIFICATION.md` — mandatory before
   assigning a source, chapter or page.
6. `docs/ops/PDF_REFERENCE_SOURCE_POLICY_20261010.md` — PDF source-of-truth
   rule and direct-page-read contract for reference classification.

## 1. Authorities and environment

- Only `ArianGhsm/FanoosLearn` is writable FANOOS source control. This
  repository is **public**; never commit private exam text/images, full
  copyrighted reference books, tokens, credentials, runtime state or
  identifiable production data.
- The **server** is the authority for production DB, storage, uploaded PDFs,
  protected source artifacts, processing workspaces and verified backups.
  FANOOS production is hosted by **IranServer (ایران‌سرور)**. Use the
  authorized access route available for that host: direct authenticated
  operator/SSH access is valid when its host key is pinned and credentials
  are protected; SentinelX is optional and is not the server itself.
- A provider name does not authenticate a host. Never bypass SSH host-key
  verification or put access credentials/host-key material in Git.
- **No laptop prerequisite:** a local computer is optional, not part of
  source-of-truth or sync checks. A separate authorized server worktree or
  hosted checkout may create and test branches; push to GitHub before ending.
- Immutable releases at `/srv/fanoos/releases/<sha>/` and
  `/srv/fanoos/current` are **not working directories**. All code ships
  from reviewed green `main` through the updater.
- The host runs unrelated workloads. Never depend on, inspect unnecessarily,
  restart, or modify those workloads. Keep FANOOS data and identities
  isolated.

## 2. Work protocol and documentation

Before each task, read the existing owner runbook and prior checkpoints.
Distinguish proposal, test, verified output, production change and pending
step. Document goal, exact inputs/versions, execution commands, decisions,
invariants, errors/fixes, output/backup IDs, validation, remaining work,
Git SHA/PR, and server state. Keep reusable logic in versioned scripts and
shared policy in `docs/PROJECT_PRINCIPLES.md`; task-specific details go
to the relevant `docs/` runbook and access-controlled server records.

**Everything must be recoverable in a new chat without history.**
A SentinelX context is only a convenience copy, never the sole handoff.
Never report a change as complete without verifying its persisted state.

Across all components, temporary files belong to one operation and must be
removed on success or failure. Use scoped temporary paths and `finally`/`trap`
cleanup; use atomic replacement for durable writes. Keep a generated file only
when it is an explicit product output, backup or audit receipt with a defined
owner. Do not leave scratch files in the repository or runtime roots.

Task-only scripts and generated files belong in a unique private
`/srv/fanoos/shared/research/tmp/<operation-id>/` directory. Use automatic
cleanup (`trap`/`finally`) or delete the exact directory after validation and
audit. Reusable scripts belong in versioned `scripts/` and stay there; never
leave one-off scripts, full-book text dumps, Python bytecode or duplicate
checkouts after their operation ends. Preserve durable decisions, concise
provenance/audit receipts, official PDFs, human reviews and verified backups.
Run research Python commands with `python3 -B` to avoid bytecode caches.
After an operation ends, remove its generated files once its validated result
and concise recovery receipt are recorded. Before cleanup, confirm no running
batch uses the files, list exact paths, and verify the replacement or recovery
path. A pending follow-up never requires retaining PDF text; inspect the current
approved PDF page directly when work resumes. Preserve pending decisions,
durable handoffs and unique source data. Do not clean a running batch or use
a blanket delete.

## 3. Reference and question processing

- Fetch live private reference inventory with
  `scripts/ops/reference-library-inventory.php` and match each question
  to the official year/subject/edition in the catalog.
- For book-based source/chapter/page work, the exact verified private server
  PDF is the only book source. Never make or retain an extracted text file,
  whole-book text stream, OCR export or search index from any PDF. Use
  `scripts/references/verified_reference_pdf.py` to verify its current
  approved object; tools may request one exact PDF page at a time and inspect
  only that page in memory, then discard it. Record the PDF SHA-256 and page
  number in the audit. Check selected evidence on the original PDF page,
  especially where columns, Persian shaping or figures affect reading order.
- **One-pass source location:** find the answer-bearing passage in the exact
  official-edition PDF, then derive its chapter immediately from that same
  edition's verified page map. Record page, chapter and evidence together;
  never create a chapter-only provisional source or defer chapter lookup to
  another phase. Follow `docs/PROJECT_PRINCIPLES.md`.
- Work with **eligible verified server editions** first. Set missing or
  incomplete editions aside as pending; no fabricated chapters and **no
  new nearest-edition fallback by default**. Preserve previously audited
  legacy assignments and source provenance until an audited correction.
- If a verified PDF page is not readable by the page-at-a-time reader, inspect
  the original page in a PDF viewer or leave that question pending. Do not
  create OCR output or a text version to fill the gap.
- **Atomic chapter+page policy (owner 2026-10-11):** No new
  chapter-based citation or classification completion without a positive
  `pdf_page` and `pdf_sha256` tied to the *same* source row for the
  exact official edition and supported by direct original-PDF evidence.
  A printed or legacy `page` field alone is insufficient. Leave
  unverified chapter+page proposals pending; preserve older incomplete
  records and provenance pending audited repairs. Report completion
  only using same-row `node_id + pdf_page + pdf_sha256`, with
  residency-English official-key exemptions separately counted. See
  `docs/ops/BANK_CLASSIFICATION_PAGE_BACKLOG_20261011.md`.
- **AI classification authority (owner 2026-10-13):** No human-review gate
  or owner confidence queue. The AI may publish a decision only after exact
  original-PDF evidence, official scope, independent validation, verified
  backup and source-only readback. Confidence below 0.85 stays pending.
  Human-origin legacy citations retain attribution but may be corrected by
  an evidence-backed, audited source-only transaction without human sign-off;
  question text, keys and frozen attempts stay untouched.
- Use `scripts/references/` search and evidence validator end-to-end.
  Every decision needs the book's own verbatim evidence, chapter, PDF page,
  edition and confidence; unanswered matches remain undecided, not `none`.
- Re-import only the site-matching sitting after
  `questions_changed=0` dry run and verified backup. Raw questions,
  decisions, reference text and reports stay in protected server storage,
  never Git. Do not write directly into content-addressed object storage.
- Avoid duplicating large PDFs, especially while disk usage is high.
  Missing secure reader or workspace is a dependency to document and
  implement under the normal PR process, not a reason to demand a laptop.

## 3AA. Voided questions REQUIRE original-book chapter + PDF page

For **every** residency year, including 1405, a question that is
officially **voided** must still receive an independently verified
original-year book **chapter AND exact physical PDF page/SHA** like
every other book-based question. **Never** equate `voided` with
`unclassifiable` or `citation-exempt`; do not omit these items
from the full-year science/citation census. They remain **ungraded**,
with official answer `voided`, null correct choice, and original
question status and attempts unchanged.

For existing AI-origin voided sources, use only the versioned,
reviewed, protected `repair_verified_voided_source_pages.php` operator
with original-PDF quote, independently accepted SHA-pinned audit,
original stem/choices/voided-key hashes, corroborated book printed
page, protected rollback preview, fresh verified backup and receipt
under the authorized updater. Do not convert voided into final,
publish a draft question, or bypass any separate deployment safety
gate. The entire year remains unclosed if any voided item's chapter
or page is missing. See `docs/PROJECT_PRINCIPLES.md`.

## 3A. Binding residency whole-year release gate — owner 2026-10-16

This rule binds **every AI model, device, human operator, hosted agent,
CLI, script and connected app** working on dentistry-residency
classification, regardless of their own conversation history.

**Never publish or republish a residency year's exam on the public site
while any official book-based question in that year/round lacks an
independently verified, official-year in-scope chapter **AND exact
physical original-PDF page/SHA**.** The separate no-book-reference
English exception requires a valid official final/amended key. Do not
relabel unsupported, voided, image-dependent or disputed items as
complete; preserve existing official answer states, students' frozen
versions and attempts.

**How to work quickly:** Classify in **30–40-question private research
batches** until **100% of the entire year** is complete. Multiple
subjects/batches may be processed in parallel. Protected, source-only
metadata operators may internally use bounded transactions and unique
receipts, but a research batch, a subject, an import, a code PR or a
healthy web service does NOT authorize a partial-year frozen exam
publication. Only after the full-year scientific manifest, full-year
live DB inventory, same-row chapter+PDF-page+SHA, complete official
scope, source evidence and fresh backup are all independently verified,
use the approved single year-level publication workflow and the
`BankPublisher` server-side gate; verify the new frozen version,
review/authorization, CI, deployment and health. If a gate fails,
publish nothing new; keep any previously published historical version.

This supersedes the earlier one/two-subject **site-publication** rule
from PR #419. Consult `docs/PROJECT_PRINCIPLES.md` and
`docs/ops/RESIDENCY_WHOLE_YEAR_PUBLICATION_20261016.md`.

## 4. GitHub-to-server delivery

1. Fetch latest `main`; create a focused branch.
2. Commit reproducible changes with tests and docs; push WIP branches.
3. Run targeted checks, then a PR; merge only on green CI and review.
4. Have the authorized operator deploy `main` via the updater, with its
   backup and rollback logic; never patch a deployed release.
5. Check live SHA, service health, and `scripts/dev/check-sync.sh`
   **on the server**. Record an unresolved sync gap rather than hiding it.
6. Data imports are separate approved, auditable actions and require their
   own verified backup and post-import count check.

Integration tests use an isolated `*_test` DB; never point them at
production. Do not weaken tests, access checks, validators or thresholds to
get a green result. Migrations are expand-only; destructive production
changes require explicit authorization and a verified restoration path.

## 5. Ownership and boundaries

Before cross-module changes read `contracts/REGISTRY.md` and
`docs/fanoos-migration/02_MODULE_AND_DATA_OWNERSHIP.md`. Every durable
fact has one owner; applications/bots are API clients. A contract change
requires compatibility tests and a matching `contracts/openapi` update.
Product work not requested by the owner is out of scope. Old migration
history in `docs/archive/` is historical, not an instruction to restore
a laptop or obsolete runtime model.
