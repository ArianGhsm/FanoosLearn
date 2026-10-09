# FANOOS project principles

**Current operating policy — owner decision 2026-10-09.** Read this first.
FANOOS is operated **server-first, GitHub-governed, and laptop-independent**.
This document supersedes previous three-copy/laptop requirements. When an
active document or script conflicts, update it in the same change; historical
records are not evidence of today's operating model.

## 1. Product and enduring safeguards

FANOOS (fanooslearn.ir) prepares students for Iranian dental residency
(دستیاری دندانپزشکی) and related specialty exams. Its durable asset is a
structured, source-aware question bank: official exam year, official edition,
chapter, page, evidence, and edition-independent concepts.

1. The bank is the single owner of question facts; websites and bots consume
   the platform API and do not keep competing question databases.
2. The official reference **and edition for each exam year** must be
   established from the catalog's year-specific validity and syllabus scope,
   never inferred from a question's wording.
3. Edition-specific chapters and edition-independent concepts stay distinct.
   Past attempts keep frozen question/answer versions.
4. Answers and explanations remain subject to entitlement, pacing, review,
   and privacy constraints; old answers contradicted by newer editions must
   be labeled rather than silently treated as current.
5. AI-produced source, chapter, page, similarity, and explanations carry
   origin and confidence. The owner reviews values below **0.85**. Human
   decisions cannot be replaced silently.

## 2. Two authorities: GitHub and the server

| Authority | Owns | Rules |
|---|---|---|
| **GitHub** `ArianGhsm/FanoosLearn` | application code, scripts, migrations, tests, data schemas/catalogs, non-secret templates, runbooks, shared principles | Reviewed version control; `main` is the authoritative code/documentation revision |
| **Server** `fanooslearn.ir` on **IranServer (ایران‌سرور)** | running release, production DB and private object store, uploaded references and exam sources, private processing inputs/outputs, secrets, verified backups | Audited runtime/data authority; private artifacts never enter the public repository |

IranServer is the current hosting provider for the FANOOS production host.
It identifies where the host runs; it is not an access credential or proof
of a particular SSH host key. Use the authorized, authenticated access route
configured for the host. SentinelX may provide that access when available,
but it is optional; authorized direct operator access is also valid.

**A laptop is neither necessary nor authoritative.** Agents and operators
may work from GitHub branches, approved hosted workspaces, or a controlled
server working checkout. No task may require the owner's laptop, an
unpublished local branch, or an untracked laptop file to resume.

**Source changes flow GitHub branch → tests and PR → green CI → `main`
→ updater deployment.** A server checkout can be used to *develop* a branch
if isolated from the running release; it must be pushed and reviewed.
Do not edit `/srv/fanoos/current`, immutable release directories,
production scripts, or live files in place as a substitute for a commit.

**Operational data stays on the server.** Changes to questions, decisions,
references, assets, or database records use approved versioned operators,
validation, a verified backup, audit logs, and idempotent imports. Never
overwrite production from a clone. A change to GitHub alone is not a
production data migration; a new release alone is not proof that a bank
import happened.

Server/code consistency means the running release and updater checkout are
checked against the **latest deployable green `main` commit**, and any
difference is reported until deployment. There is no laptop-sync criterion.
Use `scripts/dev/check-sync.sh` from the server/updater environment and
`docs/WORKFLOW.md` for the complete procedure.

## 3. Server references: work with what is ready

The official list is `data/bank/catalog.json` and its `validity` rows.
The production private reference library—not the laptop—is the primary
source of owned PDFs. `scripts/ops/reference-library-inventory.php`
is the authoritative **live availability check** for approved, verified,
private `reference_pdf` objects.

Snapshot of the inventory **2026-10-09**: **44** catalog editions,
**27** private PDFs registered and verified, **17** without a verified
library PDF. These are time-stamped findings, not permanent invariants.
Registration of a PDF is **not** proof that it has a complete, searchable
text and validated chapter-page boundaries.

### Classification eligibility

Work continuously through **eligible questions whose exact official edition
is available on the server** with verified PDF provenance, complete
page-marked book text, and correct chapter boundaries. A missing/unusable
edition is **pending**; document its exact key and reason and move on to
other eligible questions. Do not block all years/subjects on a handful of
missing references and do not fabricate chapter/page matches from titles,
booklets, secondary summaries, web searches, or an unavailable edition.

Older classifications made via an explicitly documented nearest-edition
substitution are preserved and identified as legacy/provisional where
applicable. **No new nearest-edition substitution by default** during this
completion pass; waiting for the exact official edition is preferred unless
the owner separately approves a specific exception and the evidence-mapping
workflow is documented.

The page text for an eligible edition must be read from the original book.
The protected PDF objects are not copied into Git or published URLs. Use an
approved, authenticated reader or secure, space-aware staging to produce
**one** full-book page-marked text per edition, with `=== PAGE n ===` markers.
The existing classification scripts take `--local`; the server-side
workspace is specified in `docs/ops/SERVER.md`. The book text is private
runtime data, not repository content. Verify available disk space **before**
building or duplicating a book.

The binding procedure is
`docs/product/09_CHAPTER_CLASSIFICATION.md`; source decisions must pass
`scripts/references/apply_classification.py` against the book text and
page-to-chapter map. The authority order is: official year validity and scope
→ original edition text/page → exact evidence → validator → reviewed import.
An unlocated fact stays **undecided**, not `none`; existing human-reviewed
sources remain protected.

## 4. Mandatory continuity and reproducibility

**No operation may depend solely on a chat or the assistant's memory.**
Every task—classification, reference ingestion, question import, automation,
site work, deployment, data repair—has a durable, discoverable handoff.

Document before, during, and at the end of each meaningful operation:

- purpose and scope, decision status (confirmed/proposed/inferred), input
  identities, versions, hashes where appropriate, output location and owner;
- relevant invariants, selection criteria, exceptions, algorithms, command
  lines and parameters, expected output, dependencies and versioned scripts;
- performed steps, validation evidence/results, counts, failures and fixes,
  pending items, next safe step, rollback/recovery plan;
- Git commit/PR and deployed SHA for code, and audited job/import/backup ID
  for server data; record whether the server actually changed;
- private artifacts' **safe identifiers/locations** in access-controlled
  operational records, never secrets, raw question text or book content in
  the public repository.

**Public documentation** resides in the relevant `docs/` runbook and
`docs/PROJECT_PRINCIPLES.md` for shared policy; **private source material,
decision JSON, generated texts and detailed work queues** reside in the
server's protected workspace and verified backups. Their recovery steps must
be documented without leaking credentials or copyrighted data. A convenient
SentinelX context checkpoint is supplemental, never the sole record.
Keep one canonical owner for each durable fact; update older documents or
mark them historical to prevent contradiction.

A new agent must be able to recover from GitHub documentation, authorized
server state and backups alone, **without chats or the owner's laptop**.
Do not claim a file, backup, upload, import, or deploy completed without a
read-back or other verifiable result.

## 5. Change and release discipline

1. Start from fresh `origin/main`; make a narrow named branch. A working
   checkout on the server is allowed only outside immutable releases and
   without touching running runtime data.
2. Make the code, tests, migration/contract updates, and documents together.
   New reusable decisions and error guards go into permanent files.
3. Run available targeted checks and CI; never weaken tests, validator
   thresholds, or reference mapping to force a desired result.
4. Open a PR and merge only when review requirements and CI are satisfied.
   Keep unfinished branches pushed so another chat can resume.
5. Deploy **only** green `main` through the FANOOS updater after the
   required preflight and verified backup. The owner does not operate the
   host manually; an authorized agent/operator runs reproducible steps.
6. Check release SHA, live health, and other workload isolation; record any
   sync gap rather than pretending deployment succeeded.
7. Before bulk bank imports: match exact site sitting, require
   `questions_changed=0` unless a separately approved wording correction,
   verify backup, apply with audited importer, publish and compare post-state.
8. Production data and snapshots never go to the public repo; root-managed
   secrets remain on the server. Schema changes are expand-only; destructive
   steps require explicit supervised approval and recovery verification.

The server is shared; do not restart or reconfigure another project's
services. Use `docs/ops/SERVER.md` and `docs/WORKFLOW.md`.

## 6. Decision register

**2026-10-03 — product decisions retained:** FANOOS brand/domain; dentistry
only (the previous medical bank was purged after backup); residency as the
first active exam type with board/promotion represented in schema; owner as
reviewer; confidence review threshold **0.85**.

**2026-10-08 — validation decisions retained:** one complete, page-marked
text per reference edition; classification only against the reference's own
words; strict chapter/page/evidence checks, human override guard, exact
site-matching sitting, `questions_changed=0` dry run, and verified backup.
See `docs/product/09_CHAPTER_CLASSIFICATION.md` for the mistakes and guards.

**2026-10-09 — new operating decisions (override previous laptop flow):**

1. GitHub and server are the two authorities; laptop operations are optional
   and **never required**.
2. Server-stored, verified reference PDFs are the starting collection;
   incomplete editions are set aside without blocking eligible batches.
3. New chapter decisions use an exact available official edition and
   page-level evidence; nearest substitutes are paused by default while
   existing reviewed decisions are preserved.
4. Durable server-side private workspaces, verified backups and public
   runbooks—not chat history—carry ongoing processing across sessions.
5. Release deployments remain controlled GitHub `main` → updater; direct
   edits to deployed code and ad-hoc unbacked-up production writes are banned.
6. Keep at most five completed full FANOOS backups on the server. Include the
   database and non-reference object storage; omit PDF objects used only by
   the private reference library. PDFs remain in live FANOOS storage. A
   disaster restore needs an audited step to repopulate their existing object
   IDs and storage keys from the owner's original PDFs; the ordinary library
   importer is not a substitute. The daily database transfer uses a private
   temporary directory and removes it on process exit. This policy applies
   only to FANOOS and does not change other projects' backup policy.
7. The current FANOOS production host is provided by IranServer. SentinelX
   is optional tooling; authorized direct access to the IranServer host is
   valid when authenticated and host-key verified. The provider name itself
   is not proof of host identity.

## 7. Open implementation items (do not assume completed)

- Establish and test the approved secure **read-only** bridge from registered
  private PDF objects to the classification builder. Verify full text and
  page-boundary integrity per edition. PDF inventory alone is insufficient.
- Populate and back up the server's private classification workspace,
  including original site-matching sittings and review decisions; maintain
  the decision/provenance ledger, without exposing raw question data.
- Reconcile historical 1398–1405 coverage notes with a current **database
  audit**. Historical counts in `06_QUESTION_FORMAT.md` are not live counts.
- Regularly monitor disk capacity and backup retention without deleting
  anything outside the approved retention policy. On 2026-10-09 the host
  reported **92% disk usage** (~4.9 GB free).
- Any access gap or unready edition is a documented **pending dependency**,
  not a reason to revert to a mandatory laptop workflow.
