# Subject-first chapter/page classification and bulk site publication

> **SUPERSEDED for residency exam site publication — owner 2026-10-16.**
> Dentistry-residency classification research now proceeds in **30–40-question
> private batches**, but the site may publish/republish a frozen residency
> exam version **only after the ENTIRE year is classified, verified and
> independently audited**. The server-side `ResidencyYearPublicationGate`
> checks every question before version creation, including 1405 and later.
> Previously published historical versions remain unchanged. Internal
> source-only transaction caps/receipts and all backup safeguards continue
> to apply. The binding policy is `docs/PROJECT_PRINCIPLES.md` and
> `docs/ops/RESIDENCY_WHOLE_YEAR_PUBLICATION_20261016.md`.


**Binding owner decision: 2026-10-16.** Authority:
`docs/PROJECT_PRINCIPLES.md` §3, and
`docs/product/09_CHAPTER_CLASSIFICATION.md` §7.
This supersedes ad hoc one-question-at-a-time *publication scheduling*;
it does **not** weaken per-question scientific proof or source-only write guards.

## Objective and exact unit

For one residency exam **type/year/round**, finish chapter **and physical
original-PDF page** research for **one entire subject** (usually 10–30
questions); if two are ready, schedule both for the **same publication
cycle**. Do not open a new source-publication cycle after every individual
question. Continue finding original-book citations independently even
while a previously verified subject awaits authorized deployment.

Complete inventory of every question in a selected subject is mandatory,
including rows already bound and verified, rows audit-ready to publish, and
rows on a documented hold. One question is present in exactly one state.
For source rows with citations, verify that the chapter is **official for
that exam year and edition**, the original physical page lies within
the SHA-bound chapter range, and the cited book itself is the current
approved server object. The same source row must contain
`node_id`, `pdf_page`, `pdf_sha256`; this is stricter than a
chapter-only or historical printed-label metric.

A subject with holds is **not fully chapter-classified**. Nevertheless,
process **all verified eligible items** for the selected entire subject
in one grouped cycle; leave held items untouched, and report incomplete
counts explicitly. A single subject is the minimum organizational unit;
two are allowed when both are ready. Only explicit no-book-reference
subjects such as residency English are excluded.

## Separation of scientific work and site changes

**Stage A — scientific evidence** (no live writes):

1. Read the actual current GitHub `main`, live release SHA, server
   current approved PDF inventory, same-year official chapter scope,
   live bank and immutable exam version.
2. Enumerate every question of the entire subject, including official
   `voided` answers and already-bound-but-out-of-scope sources.
3. For each eligible question, independently audit its exact approved
   reference edition, *official* chapter, PDF physical page, original
   evidence passage, PDF SHA and correct official final answer; preserve
   stem/choices/key, old source provenance and prior attempts.
4. Store **private** study decisions, independent evidence audits and
   their SHA-256s under the authenticated server research workspace.
   Report `ready`, `already_verified_and_bound` and `hold` states.
   Do not leak copyrighted books or raw exam questions to GitHub.

**Stage B — publication** (only after approved preflight):

1. Make a publication-cycle manifest for exactly **one or two entire
   subjects** from a **fresh, read-only live inventory**. Include every
   subject question exactly once, with a SHA-256 of the read-only
   complete inventory and protected per-source plans. Do not infer the
   inventory from old notes or chat.
2. Prepare normal guarded writer plans *per subject*:
   - `repair_verified_source_pages.php`: at most **12** existing
     AI sources per plan, unchanged chapter/edition/legacy metadata;
   - `repair_verified_source_chapter_pages.php`: exactly **1** existing
     AI source per plan, independently accepted changed chapter/printed
     page, original question/answer hashes;
   - other actions (e.g. no-source inserts, voided special cases,
     figure or multi-citation cases) require their **separately approved
     guarded operator and manifest contract**; do not smuggle them into
     the above two formats.
3. Run the **read-only planner** before any apply. It verifies the
   complete-course partition, SHA-256s, year/subject and existing plan
   size caps. It **does not** interrogate live DB, validate science or
   apply anything, and hence cannot itself certify a source.
4. Independently recheck the **entire selected subject(s)** against
   actual live source IDs, release SHA, official scope, original book
   map, private scientific decisions, immutable original questions,
   choices, latest official key, published frozen-version and attempts.
   Confirm the *current deployed release* supports the exact approved
   PDF version; GitHub changes not deployed do not qualify.
5. Run every protected plan's **rollback-only preview** before the first
   production write. Use **one fresh independently verified full
   backup window** for the cycle, renewed when expired. A backup is
   neither optional nor proof that an apply succeeded.
6. Apply only with authorized `fanoosupd` operations and the guarded
   operator's exact CLI/schema. Each bounded transaction is atomic on
   its own. Each gets a **unique receipt**, immediately independent
   source-field readback, and updated per-subject totals. Do not
   represent all sequential transactions as one ACID transaction.
   On the **first failure**, stop the cycle, preserve already committed
   receipts, describe partial state and resume only from independently
   re-read source rows; never blindly retry a committed plan.
7. After **all eligible plans of both subjects** have succeeded, verify
   whole-subject source/page/SHA counts, unchanged protected exam
   content and attempt history. Execute an authorized frozen assessment
   publish **at most once per completed cycle**, if needed and eligible.
   Verify any new version against the previous immutable assessment,
   current release SHA, CI, deploy and `/health`. Record final or
   partial status, the exact holds and the next selected subject(s).
   Only a verified bank and frozen published assessment count toward
   *visible on-site* completion.

## Protected cycle manifest contract (read-only planner)

Write an example manifest to a **private** location on the server, not
to GitHub. All action files live immediately under protected
`/srv/fanoos/shared/research/classification/reports/`, with their
real SHA-256. The inventory hash must be calculated from a fresh
deterministically ordered query of *all* official question IDs/numbers
in the chosen subject(s). Never insert raw stem, option or answer text
into this coordination manifest.

The example below represents **one full four-question illustrative
subject**, not a real production 1405 census:

```json
{
  "format": "fanoos.subject-publication-cycle/1",
  "exam_type": "residency",
  "year": 1405,
  "round": 1,
  "live_question_inventory_sha256": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
  "subjects": [
    {
      "subject": "endodontics",
      "all_question_numbers": [21, 22, 23, 24],
      "already_verified_and_bound": [21],
      "holds": [{"number": 24, "reason": "Original figure-to-answer or page evidence unresolved"}],
      "plans": [
        {
          "operator": "repair_verified_source_pages.php",
          "path": "/srv/fanoos/shared/research/classification/reports/PROTECTED-PLAN.json",
          "sha256": "bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb"
        }
      ]
    }
  ]
}
```

In a real private cycle, the protected page plan must actually contain
questions 22 and 23, use its real SHA, match year/subject/scope, and pass
the official independent scientific and rollback-preview validators.
The dummy hash and filename above are *not* runnable evidence.

Read-only invocation:

```sh
sudo -n -u fanoosupd python3 -B \
  /srv/fanoos/current/scripts/references/plan_subject_publication_cycle.py \
  --manifest=/srv/fanoos/shared/research/classification/reports/CYCLE.json \
  --reports-dir=/srv/fanoos/shared/research/classification/reports
```

This returns a **coverage/plans preview**, not a write receipt. It
rejects any missing or repeated question, inconsistent subject/year,
more than two subjects, an unapproved operator, changed plan SHA,
or a protected plan exceeding its existing size cap. It does **not**
infer eligibility from plan contents: use the actual source-specific
official operators for independently protected rollback previews and
applies.

## Completion receipt and throughput

Maintain **one protected aggregate subject-cycle receipt** referencing
all individual per-transaction receipts by file path, SHA, verified
backup ID and actual commit mode. It also records:

- exam type, year, round, one or two subject names and the full inventory
  counts; existing verified / new source rows / unresolved by subject;
- old and new strict same-row chapter+physical PDF page+SHA counts,
  plus the separately audited year-in-scope count and English exemption;
- exact released source-map/code SHA and approved PDF object SHA for
  every touched edition;
- every scientific audit SHA, guarded preview proof, successful apply
  receipt, DB source-row read-back and original immutable-content hash;
- frozen assessment before/after publication version and proof of
  unchanged questions/options/answers/attempts, where applicable;
- CI, merge, permitted updater deploy and live site-health results
  *as actually observed*;
- explicit partial/hold reasons, fresh-backup expiry failures and
  restart-safe remaining plans.

**Performance metric:** count *full-subject cycles completed with source
and site readback*, not just number of small plan invocations. Reuse a
fresh backup and per-subject research inventory across guarded chunks
within its validity window; batch independent book-page verification,
but never lower the scientific evidence threshold, bypass tool safety
gates, widen protected write privileges, or manufacture source metadata.

## Implementation and migration status

The default planner and its tests are versioned at
`scripts/references/plan_subject_publication_cycle.py` and
`tests/references/test_subject_publication_cycle.py`.
It coordinates **read-only subject coverage**; it does not silently
rewrite existing legacy operators or claim to deploy itself.

**Observed 2026-10-16 pre-change snapshot:** residency 1405 has 230
book-based questions, with **223 same-row chapter/PDF-page/SHA
field-complete** and **7** still missing required source fields.
This is a dated strict-field metric, not a guarantee that all 223
sources meet every official year-scope constraint. Confirm again from
the live DB before selecting the next one or two subjects.

The operative full-backup, individually guarded writer contracts and
one authorized frozen assessment publish remain governed by
`docs/ops/SOURCE_ONLY_PUBLICATION_20261010.md`.
