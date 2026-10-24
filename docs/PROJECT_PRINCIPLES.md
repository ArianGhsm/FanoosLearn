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
   origin, confidence, and verifiable evidence. AI completes chapter
   classification through the exact-edition PDF and independent validators;
   no human review is required. A decision below **0.85** or without
   decisive page evidence remains pending. Earlier human-origin records
   retain their provenance; an AI correction needs an evidence-backed
   reason, guarded source-only transaction, and auditable before/after receipt.

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

Residency-only snapshot after the audited Telegram and Konkur.in imports on
2026-10-09 and web-source import on 2026-10-10: **44** catalog editions, **42**
private PDFs registered and verified, **2** without a verified library PDF.
The later 2026-10-10 national/board/promotion catalog import expanded the live
catalog to **94 official editions**. Its matching inventory snapshot still has
**42** approved private PDFs and **52** pending; `data/bank/reference-pdfs.json`
records a disposition for every official edition. The 42 PDFs total
**4,712,822,240 bytes**. These are time-stamped findings, not permanent
invariants.
Registration of a PDF is **not** proof that it has readable page content or
validated chapter-page boundaries.

### Atomic chapter-and-PDF-page completion — owner decision 2026-10-11

**A book-based question is chapter-classified only if its *same source
record* has an exact official edition and in-scope chapter node
(`bank_question_sources.node_id`), a positive 1-based `pdf_page`,
and `pdf_sha256` identifying the current verified server PDF.** Evidence
must be checked against that exact PDF page and its hash-bound chapter map,
with any official partial-page limits independently verified. A bare chapter,
a printed or legacy `page` label, or a PDF page without its file identity is
**not a completed classification**. No chapter without its verified PDF page
may be newly recorded or presented as completed. Do not infer pages from a
chapter title or unverified pagination.

When an exact page cannot be found and verified, leave the new decision
**pending with neither a new chapter assignment nor a fabricated page**;
continue other eligible questions. Historical incomplete source records
remain intact, including human-origin records, until individually audited
and safely repaired via the approved, backed-up source-only workflow. Do not
erase existing chapters, keys, assessments or source provenance merely to
satisfy the new metric.

### One-pass source location: page determines chapter immediately — owner decision 2026-10-18

For every new or corrected book-based question, locate the answer-bearing
passage in the exact official-edition PDF. **The physical page found is the
page decision; its chapter is read directly from that same edition's verified
page map in the same pass.** This is one source-location decision, not a
chapter-first phase followed later by a separate page-research phase. Never
write a chapter-only provisional source or leave a known page waiting for a
second chapter pass.

Within each 30–40-question private batch, check the official catalog, live
PDF availability, current PDF SHA-256 and chapter map once per exact edition.
For each question, search for the fact that makes the official keyed answer
correct; inspect the candidate page in the original PDF; confirm the evidence;
read that page's chapter from the verified map; and establish the printed
label only when partial-syllabus boundaries depend on it. Record edition,
chapter node, physical `pdf_page`, current `pdf_sha256`, page evidence,
confidence and any needed corroborated `printed_page` together as one
decision. A later independent batch audit checks those recorded decisions and
their provenance; it is not another chapter/page discovery phase.

If the evidence, page, chapter or scope is unresolved, state exactly what is
missing and keep that question pending while continuing eligible items. A
partial-chapter page with no corroborated printed label may use only the
audited `scope_unverified` recording path and remains incomplete for year
publication. For a located outside-scope source, preserve its true
chapter/page and record the explicit outside-scope disposition. A question
needing multiple pages or sources must retain every answer-bearing location
through the appropriate audited route. Do not report an available exact PDF
as a missing-book dependency when the remaining task is to locate or validate
the question's passage in it.

This one-pass research rule removes redundant classification stages; it does
not remove the validator, protected backup, guarded source-only write or
independent production readback required before changing the live bank.

**Continuous execution:** Do not pause for owner approval between eligible
questions or research batches. Continue across the whole year, carrying only
genuine evidence or access blockers forward in one concise report. Run the
research decision and chapter lookup together; batch validation checks that
work once. The verified backup, guarded write and independent readback are
the only required live-database checkpoints, and they do not repeat source
discovery.

**Partial scope with an unreadable printed label:** If the exact official
PDF page, chapter map and page-local evidence are verified, but an official
partial-chapter cutoff cannot be compared because that PDF page has no
corroborated printed label, an audited source-only operation may record the
physical `pdf_page` and `pdf_sha256` with
`scope_unverified=true` and a clear Persian note. This is a
recorded design location only; it is neither an in-scope completion nor an
outside-scope finding. The year-level publication gate must reject it until
the scope is authoritatively resolved. Never guess the printed page or mark
the item outside-scope to work around a missing label.

Every future backlog, import receipt, progress dashboard and handoff must
use `EXISTS` on a **single source row** containing `node_id`,
`pdf_page` and `pdf_sha256` for the strict **chapter+page recorded**
counter; the separately audited **verified/in-scope** counter may be lower.
Report incomplete rows with a chapter separately from questions without
any chapter, and retain historical raw source counts as labeled diagnostics.
Explicit no-book-source exemptions (residency English, §3A) are reported
separately and never given synthetic chapter/page rows.

### Voided residency questions must also be chapter-and-page classified — owner clarification 2026-10-16

**A voided question is NOT exempt from reference classification.** For
every dental-residency year, classify **all clinical questions**,
including those officially `voided`, `draft`, excluded from grading
or missing a scoreable key. Independently identify the officially
approved reference edition from that exam year, the original
**chapter AND exact physical PDF page** (bound to the actual approved
server PDF SHA-256), with original-page scientific evidence. Record
the source on the original question without fabricating a correct
answer; preserve official `voided` status, null choice position,
`draft` grading/exclusion status, all questions/options, existing
frozen versions and student attempts **unchanged**.

A separate guarded **source-only** metadata operator,
`scripts/references/repair_verified_voided_source_pages.php`,
provides a publication-independent way to correct original book
printed-page labels and physical PDF page/SHA for an unreviewed
existing citation of a voided, non-scored question. It never changes
the answer key, question status, question text or any grading state.
Require the independent private, SHA-pinned original-PDF scientific
audit, official-year syllabus, original question/choice/voided-key
identity, printed-page-neighbor corroboration, rollback-only preview,
fresh full verified backup, unique commit receipt and independent
live DB readback. It refuses all final/amended/published questions,
duplicate plans, changed original content and unsupported pages.

**Two independent requirements:** every voided question must have
chapter+page source fields; voided questions must simultaneously
remain excluded from scoring. Even if the whole-year gate is
scientifically complete, never convert a voided question into a
scored question just to permit publishing. Treat ambiguity about a
voided question's original *design source* as scientific hold to
resolve, not permission to mark the year complete.

### Residency WHOLE-YEAR classification gate — superseding owner decision 2026-10-16

**No dentistry-residency exam year may be newly published or republished
on the public site until the entire official question inventory for that
year and round is completely classified.** The unit of **site publication
is ONE COMPLETE YEAR**, not one question, one subject, a 12-item source
transaction or a group of 30–40 questions. This supersedes the earlier
one/two-subject *publication* scheduling instruction, and applies
equally to every human, AI agent, device, batch worker, CLI, owner
and assistant. Neither a temporary notebook nor a private tool result
authorizes a partial-year release.

**Research / staging is different from publishing.** Process exact-edition
chapter **AND original physical PDF page** decisions in convenient
**30–40-question research batches** (last batch may be smaller) until
**every subject and every question in the year** has been accounted for.
For each book-based item the *same* source row must have a verified
official-year/subject/edition in-scope chapter node, positive 1-based
`pdf_page`, and current approved original PDF SHA-256; page-local
original-book evidence must pass independent scientific validation.
The official English no-book-reference exemption remains valid only
with an eligible `final` or `amended` answer. Void, disputed, missing
figures, unsupported chapters, incomplete PDF maps or unsupported
multi-source citations remain **explicit unresolved gates**; they
must not be made up, silently omitted from the inventory or converted
into a scored answer just to trigger publication.

**Mandatory server-side enforcement.** Every normal residency
`BankPublisher::publish()` path, for both the initial publication and
every later frozen-version republish, must check the *actual production
DB* against the sitting's declared `question_count`. Reject whenever
any question is missing, any English item lacks a valid official key,
or any book-based item lacks a same-row chapter+PDF-page+SHA for an
official in-scope reference for that exact year and subject. The
versioned `ResidencyYearPublicationGate` is an immutable preflight:
failure must occur **before creating an assessment or version** and
return a conspicuous error, not a warning. A structurally complete DB
does not replace the original-page scientific audit, verified backup,
CI or operational authorization. Existing already-published frozen
versions and student attempts remain intact; do **not** remove the
historical exam from students because a later research cycle is pending.

**One final year-level release.** After all 30–40-question private
research batches are approved, run a full-year independent scientific
and source-only database audit, fresh verified backup, guarded writes
where needed, preview, unique receipts, and read-back. Then publish
the entire residency year's frozen assessment **once**, with the
existing operator authorization, two-person review/owner exception,
CI/merge, approved updater deployment and live health checks.
If a gate fails, publish **nothing new** for that year; preserve the
historical published version if present and keep work pending. Never
mislabel private research, a code merge, a source-only SQL write or a
successful `/health` check as full-year publication.

This policy is binding for all agents via repository-root `AGENTS.md`,
`docs/product/09_CHAPTER_CLASSIFICATION.md`, and
`docs/ops/RESIDENCY_WHOLE_YEAR_PUBLICATION_20261016.md`. The older
`docs/ops/SUBJECT_FIRST_SOURCE_PUBLICATION_20261016.md` is now a
**staged-research / guarded-writer chunking reference only**; it must
never be construed to authorize partial-year site publication.

### Pre-1396 retrospective reference policy — owner decision 2026-10-11

**For exam sittings dated 1395 or earlier (year < 1396), classify using the
published dentistry-residency references from 1397 onward.** Start at the
1397 reference-validity catalog for that subject; where its exact edition
PDF cannot be verified, its chapter map is invalid, or the evidence is
unavailable, continue through reference years 1398, 1399, … in ascending
order to find a demonstrably suitable approved edition. The selected book
must actually belong to an official source list for the **chosen reference
year**. Do not assume the book was officially assigned to the earlier
**exam year**.

This is an **owner-authorized retrospective reference alignment**, not a
historical claim about the exam-year official syllabus. Keep the
`exam_year`, `reference_year`, `edition`, and classification basis
(`retrospective_proxy_1397_plus`) separately documented and visible in
audits, handoffs and site labels when presented. The source's book and
edition are real; the historical official status remains unverified.
Do not manufacture `official=true` validity rows for pre-1396 years
merely to bypass guards. For residency exams dated 1396 or later, exact-year
editions remain the default except for the explicit unfinished-sitting rules
below. The separate non-residency rule applies to national, board and
promotion type/subject/year combinations that have no exact official list.

**The chapter+PDF-page atomic criterion is unchanged.** Each accepted
question must name a verified source edition and chapter plus a positive
PDF page and SHA-256 of that *same* original book's server PDF, with
original-page evidence and correct chapter boundaries; no title guesses,
generic chapter-only assignments, or 1397 page labels grafted onto another
edition. Keep unresolved/voided questions and absent evidence pending;
preserve texts, options, answer keys, human decisions, frozen exams and
attempts. The research and safe publication procedure is documented in
`docs/ops/PRE_1396_RETROSPECTIVE_CLASSIFICATION_20261011.md`.

### Classification eligibility

Work continuously through **eligible questions whose exact official edition
is available on the server** with verified PDF provenance and correct chapter
boundaries. A missing/unusable
edition is **pending**; document its exact key and reason and move on to
other eligible questions. Do not block all years/subjects on a handful of
missing references and do not fabricate chapter/page matches from titles,
booklets, secondary summaries, web searches, or an unavailable edition.

Older classifications made via an explicitly documented nearest-edition
substitution are preserved and identified as legacy/provisional where
applicable.

**Owner rule 2026-10-23 — newest edition first for unfinished older
residency sittings (1391–1396).** The current residency exam is set from the
new references, so an older sitting is classified against the **newest
available official edition** of each reference-book family, even when the
older exam-year edition is also available. Only when the answer-bearing page
is not found in that edition may an older edition of the same family be
cited, stepping back one edition at a time. A newer edition whose PDF is not
verified, or whose hash-bound chapter map or structured scope is unusable, is
pending and is skipped in that order. Each cited row keeps its own edition,
chapter, positive physical PDF page, PDF SHA, direct-page evidence, confidence
≥ 0.85 and actual official `reference_year`. A decision that cites an older
edition must list, newest first, every newer eligible edition of that family
in `newer_editions_searched`; the validator and the independent audit reject
it otherwise. Never carry a page or chapter from one edition to another.

For exam year 1396 the families come from the exam-year list (or the nearest
official list when it is absent) and the basis is `same_lineage_proxy`; a
citation that happens to use the exam-year edition stays
`official_exam_year`, and the two bases are published in separate batches.
Store and show a note that names both years and labels the edition as a proxy
whose official status for the exam year is unverified. Never create synthetic
historical `official=true` validity rows or present the proxy as the exam-year
edition. Owner rule 2026-10-24 extends this same newest-first selection to
national, board and promotion sittings whenever the exact type/year/subject
list is absent. Families come from the nearest official list year for the
same type and subject; prefer the later year on a tie. An exact exam-year
list suppresses the proxy path. Never cross exam types. Every such decision
keeps the selected catalog row's actual `reference_year` and a Persian note
that names both years and says official status for the exam year is
unverified; a rank above zero also requires the exact newer eligible editions
searched. Exam years 1397 and 1398 in residency remain under their named
updated-reference exceptions below; completed rows are not reopened.

For exam years **1395 and earlier** the basis remains
`retrospective_proxy_1397_plus` (candidates come from 1397+ official lists),
now with the same newest-first order. The two rules use separate labels and
guards. The unfinished residency same-lineage rule remains bounded to
1391–1396, with the separate residency exceptions for 1397/1398; the
2026-10-24 non-residency missing-list rule applies independently in any
exam year. Any exam type without a compatible guarded source-only publisher
must remain pending for publication until its normal versioned workflow is
extended; do not bypass it with a full-sitting import or raw SQL.

### Owner-approved updated-reference proxy for residency 1397 — 2026-10-21

The owner explicitly requested that residency 1397 be classified using the
newer 1406 official reference set. This is an `updated_reference_proxy`, not
a claim that a 1406 reference was official for the 1397 sitting. Every
accepted citation must use an exact approved 1406 PDF, its matching
SHA-bound chapter map and page evidence. A chapter within the 1406 scope may
be recorded normally; a chapter excluded from that structured scope may be
recorded only for its true chapter/page, with
`outside_scope_exam_question` and a Persian note that says it is outside both
the 1406 syllabus and the 1397 official reference scope. The single 1397
endodontics Q95 implant citation may use Hupp 7e from the 1406 oral-surgery
list; its source note must explicitly identify this cross-subject reference.
Store proxy rows as
`outside_scope_exam_question` with a
Persian note naming both 1397 and 1406 and explaining that it is outside the
1397 official reference list. Preserve the official catalog, question text,
choices, answers, frozen attempts and scores. This exception is limited to
residency 1397; it does not create a general nearest-edition rule.

### Owner-approved updated reference proxy for residency 1398 oral surgery — 2026-10-21

For the 1398 oral-surgery questions whose official Hupp 6e PDF is unavailable
in the verified server library, the owner explicitly requested classification
from the newer references. The approved exception uses **Hupp 7e as the 1406
official reference edition**, with its current verified PDF, chapter map and
original-page evidence. This is an `updated_reference_proxy` basis; it does
not claim that Hupp 7e was on the 1398 official reference list. Every such
source row must be visibly marked `outside_scope_exam_question` with a note
naming exam year 1398, reference year 1406 and the owner's updated-reference
instruction. Keep the 1398 official catalog unchanged, preserve official
answers and all frozen assessment/user history, and do not generalize this
exception to another year or subject. The whole-year publication gate remains
in force.

The exact verified server PDF is the sole book source for every
classification. Read it through the authenticated, read-only bridge in
`scripts/references/verified_reference_pdf.py`; do not copy the PDF out of
protected storage. The bridge verifies the current approved object, byte size,
PDF signature and SHA-256. Search, chapter mapping and evidence validation
request one PDF page at a time and discard its transient in-memory text after
checking it. They never write extracted page text, a whole-book text stream,
an OCR export or a search index. Store only the PDF SHA-256, PDF page number,
chapter map and the short quote needed to substantiate a decision. If a page
cannot be read or the exact PDF is missing, leave the item pending and review
the original PDF visually; do not create a text substitute. Verify available
disk space before any operation that intentionally writes a durable artifact.

The binding procedure is
`docs/product/09_CHAPTER_CLASSIFICATION.md`; source decisions must pass
`scripts/references/apply_classification.py` against the exact verified PDF
page and the page-to-chapter map. The authority order is: official year
validity and scope → exact verified edition PDF/page → short verbatim evidence
from that page → validator → audited import. When that exact design location
is outside the announced scope, store the true chapter, physical PDF page and
hash and set `official_scope_status=outside_scope_exam_question` with a clear
note (owner decision 2026-10-16). This labelled evidence exception does not
change the official syllabus and supersedes the earlier instruction to leave
such a located citation pending. Audit older outside-scope rows individually.
An unlocated fact stays **undecided**, not `none`.


## 3A. Dentistry-residency English: final answer key is sufficient (owner decision 2026-10-10)

**Permanent explicit exemption.** For the `english` subject of dentistry residency exams, the official sitting and its **recorded official final/amended answer key** constitute the complete deliverable. The official reference-validity catalog assigns **no book or chapter reference** to English. Do not invent a reference, edition, chapter, page, passage, AI confidence or placeholder `bank_question_sources` row. English is **complete without a source row**, and must not enter book-based chapter-classification work queues or generate outstanding classification alerts.

**Preserve everything:** question stems, passages, options, images, choices, `bank_official_answers` including `final` versus `amended` status, any `also_correct`, manual reviews, prior frozen assessment versions and student attempts remain untouched. This decision is about the *presence of source metadata*, not a mandate to change historical answer keys. A missing, withdrawn, disputed or voided official answer is a **separate answer-integrity issue** that must be reported and resolved against a legitimate official key; never mark it complete merely because it is English.

**Completion measurement:** maintain both unmodified raw counts and an explicit exemption-aware classification denominator. Never artificially insert source rows to make a database `NOT EXISTS(bank_question_sources)` query appear smaller. Report at least (i) all residency questions, (ii) raw questions without source records, (iii) the English questions with a verified eligible final/amended key exempted from book/chapter citations, and (iv) non-English unsourced candidates still requiring exact-edition evidence or official-scope investigation. For the verified 2026-10-10 snapshot: **1,995** total residency questions, **1,602** with source rows, **393** without source rows; of the latter **159** are English and **234** are non-English. All 159 English currently have answer records (158 `final`, one `amended`, none with null choice/voided/missing answer). Their English exemption is now **policy-final**; this alone makes **zero** changes to source tables, answer tables or old exams. These totals are time-stamped, not permanent future counts.

The rule applies to other exam types only after separate confirmation that their English section has the same official no-reference requirement; do not silently generalize dentistry-residency policy to unrelated subjects, years or exams. Update related operations dashboards/runbooks to show exempted English separately from the true evidence-dependent backlog.

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
durable decisions and final audit receipts** reside in the server's protected
workspace and verified backups. Generated text, search output and detailed
work queues exist only in the operation temp directory while a task is active,
then are removed after their non-copyrighted hashes and results are recorded.
Their recovery steps must be documented without leaking credentials or
copyrighted data. A convenient SentinelX context checkpoint is supplemental,
never the sole record.
Keep one canonical owner for each durable fact; update older documents or
mark them historical to prevent contradiction.

A new agent must be able to recover from GitHub documentation, authorized
server state and backups alone, **without chats or the owner's laptop**.
Do not claim a file, backup, upload, import, or deploy completed without a
read-back or other verifiable result.

### Generated-artifact lifecycle

- Across every FANOOS component, a temporary file must have one owner,
  operation and cleanup point. Use a unique scoped temporary path and
  `finally`/`trap` cleanup on both success and failure; for atomic writes,
  remove the staging file after rename or error. Never scatter scratch files
  through the repository, home directory, release tree or shared data roots.
  Keep only outputs explicitly designated as durable product data, reports,
  backups or audit receipts.
- For PDF classification, never create a `.txt` copy, full-book text stream,
  OCR export or search index. Read a selected page directly from the exact
  verified PDF, in memory, and discard it immediately after the check. A
  pending follow-up does not justify retaining page text. Keep PDF SHA-256,
  PDF page count, chapter map identity, decisions and concise audit receipts.
- Do not leave free-form `.txt` outputs in the research workspace. Its only
  persistent `.txt` exceptions are checksum manifests, licenses and dependency
  manifests; keep durable narrative in Markdown and machine-readable receipts
  in JSON/CSV as appropriate.
- One-off scripts are deleted after their validated output is recorded.
  Reusable scripts are intentional versioned source code: review and keep them
  in `scripts/`, then remove only their temporary outputs after each run.
- Clean Python bytecode and temporary checkouts after use. Before deleting any
  private artifact, check for running work, preview exact paths, and verify the
  required recovery point. Never use a blanket recursive delete. Preserve
  official source documents, original PDFs, validated decisions, human reviews,
  publication receipts and verified backups.
- Run research commands with `python3 -B`; temporary bytecode has no value and
  must not accumulate in the private workspace or nested checkouts.
- Remove legacy full-book text caches and scratch outputs after confirming the
  current approved PDF and reproducible extraction path. Keep pending decisions
  and handoffs; do not keep large generated inputs just because a later review
  is pending. Preserve unique research evidence that cannot be recreated from
  the source PDF or durable audit record.

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

**2026-10-08 — historical validation procedure:** the earlier workflow
created a temporary page-marked text index from the exact PDF, then removed
it after validation/audit. That procedure is retired by the 2026-10-10 owner
instruction below. Its remaining controls still apply: classify only against
the reference's own words; use strict chapter/page/evidence checks, the human
override guard, exact site-matching sittings, `questions_changed=0` dry runs
and verified backups. See `docs/product/09_CHAPTER_CLASSIFICATION.md`.

**2026-10-09 — new operating decisions (override previous laptop flow):**

1. GitHub and server are the two authorities; laptop operations are optional
   and **never required**.
2. Server-stored, verified reference PDFs are the starting collection;
   incomplete editions are set aside without blocking eligible batches.
3. New chapter decisions use an exact available official edition and
   page-level evidence; nearest substitutes are paused by default while
   existing reviewed decisions are preserved. This 2026-10-09 default is
   superseded by the same-lineage proxy decisions dated 2026-10-22 through
   2026-10-24 and the separate 2026-10-21 updated-reference exceptions.
4. Durable server-side private workspaces, verified backups and public
   runbooks—not chat history—carry ongoing processing across sessions.
5. Release deployments remain controlled GitHub `main` → updater; direct
   edits to deployed code and ad-hoc unbacked-up production writes are banned.
6. On the IranServer host, keep at most five newest completed, verified
   server-local backup sets per project, aggregated across that project's
   server-local backup destinations. Each project backup job or retention
   timer removes older completed sets after verification; incomplete or failed
   sets are not treated as valid recovery points. The separate Restic/Arvan
   object-storage repository is excluded and keeps its own retention policy.
   For FANOOS, full backups include the database and non-reference object
   storage; omit PDF objects used only by the private reference library. PDFs
   remain in live FANOOS storage. A disaster restore needs an audited step to
   repopulate their existing object IDs and storage keys from the owner's
   original PDFs; the ordinary library importer is not a substitute. The
   daily database transfer uses a private temporary directory and removes it
   on process exit.
7. The current FANOOS production host is provided by IranServer. SentinelX
   is optional tooling; authorized direct access to the IranServer host is
   valid when authenticated and host-key verified. The provider name itself
   is not proof of host identity.

**2026-10-10 — PDF source-of-truth decision:** for book-based question
classification, the exact current approved server PDF is authoritative.
Search, chapter mapping and evidence validation verify the current PDF's
SHA-256 and inspect only selected pages directly. No text file, text corpus,
OCR export or index may be generated from a PDF. If the exact PDF is absent or
a required page cannot be read, leave the item pending for direct visual review
of the original PDF. The exam sitting source and reference-book source remain
distinct: the sitting source verifies question wording, while the reference
PDF verifies chapter, page and evidence.

**2026-10-11 — Atomic completion rule:** assigning a chapter and assigning
its verified PDF page are one indivisible classification decision. Unverified
or page-less chapters are incomplete in every new progress report and cannot
be created by new imports. The 2026-10-11 production audit and sitting-level
backlog are documented in `docs/ops/BANK_CLASSIFICATION_PAGE_BACKLOG_20261011.md`.

**2026-10-13 — AI classification authority (supersedes the 2026-10-03 owner
review threshold and human-approval wording above):** AI agents make and
publish exact-edition chapter/page decisions autonomously after the original
PDF, official scope, independent validator, immutable question/key checks,
verified backup and source-only receipt all pass. Confidence below 0.85 is
pending further evidence, not a human-review queue. An earlier human-origin
source remains an attributed historical record; it can be superseded only by
a separately audited, evidence-backed source-only correction that records
the old and new citation and leaves question, answer and frozen attempts
unchanged. No human sign-off is a classification prerequisite.

**2026-10-23, extended 2026-10-24 — newest-first same-lineage proxy:** for
unfinished residency sittings 1391–1396 and for national, board and promotion
type/subject/year combinations with no exact official list, cite the newest
available official edition of each selected reference family first and step
back only when the answer-bearing page is not found, recording newer
editions searched. Non-residency families come from the nearest official
same-type list year, preferring the later year on a tie. Keep the actual
reference year, edition and visible proxy note; do not claim exam-year
official status, cross exam types or create validity rows. Residency
1397/1398 keep their named exceptions. Implementation and operation are specified in
`docs/ops/SAME_LINEAGE_PROXY_CLASSIFICATION_20261022.md`.

## 7. Open implementation items (do not assume completed)

- Rebuild the 10 ineligible chapter maps directly from their current approved
  PDFs. Nine lack `source_pdf_sha256`; five have gaps or overlaps in page runs,
  with four maps in both groups. Search, apply and audit fail closed for them.
  Twenty-three maps currently carry a PDF hash and contiguous coverage; each
  use still verifies that hash against the exact current approved PDF.
- Before each classification batch, verify the exact edition's current
  approved PDF SHA-256 and page count with the read-only PDF bridge. The bridge
  does not create text outputs. If a page is unreadable, inspect the original
  PDF visually or leave it pending; do not add an OCR/export path.
- Populate and back up the server's private classification workspace,
  including original site-matching sittings and review decisions; maintain
  the decision/provenance ledger, without exposing raw question data.
- Reconcile historical 1398–1405 coverage notes with a current **database
  audit**. Historical counts in `06_QUESTION_FORMAT.md` are not live counts.
- Regularly monitor disk capacity and backup retention without deleting
  anything outside the approved retention policy. After the audited web-source
  import on 2026-10-10, the live inventory reported **87% disk usage** (~7.7
  GiB free); see `docs/ops/SERVER.md` for the dated snapshot.
- Any access gap or unready edition is a documented **pending dependency**,
  not a reason to revert to a mandatory laptop workflow.
