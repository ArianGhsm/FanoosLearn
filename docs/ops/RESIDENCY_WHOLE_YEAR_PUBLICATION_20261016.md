# Mandatory residency whole-year citation and frozen publication gate

**Owner decision:** 2026-10-16. **Binding for all humans, AI agents,
devices, servers and import scripts.** Read `AGENTS.md` and
`docs/PROJECT_PRINCIPLES.md` before any operation.

### Voided items: scientific citations required, official scores immutable

An officially voided residency question is **not** exempt from the
book reference mapping. Every non-English voided question still needs
an in-scope original reference edition, exact **chapter and physical
PDF page** and approved book SHA bound to the original source row.
Keep its official answer status `voided`, correct-choice NULL,
original question `draft` exclusion, four choices and all frozen
historical exams/attempts. The existence of a voided key must never be
used as an excuse to leave a year unclassified, nor may citations
convert a voided question into a graded one.

Use the independently audited guarded source-only voided metadata
workflow in `docs/ops/VOIDED_QUESTION_CHAPTER_PDF_SOURCE_20261016.md`
for already existing original AI sources. Its release, actual proof
and independent production readback are mandatory before reporting
scientific completion. The current scored sitting still correctly
omits voided items from the graded projection.

## Hard rule

A dentistry-residency exam (one official **year + round**) is the
**smallest permitted public site-publication unit**. Publish or republish
a *new* frozen year version ONLY AFTER all non-English questions for
that year have scientifically verified original reference edition,
official-year chapter AND original physical PDF page, bound in a single
source row with approved PDF SHA. The official English no-reference
exemption requires a latest valid final/amended answer key.

Before that point **no new public frozen year version**, even if 30–40
questions, one or two whole subjects, or 99% of the year are ready.
The previous subject-first **release** rule is superseded. Previously
published and frozen historical exam versions must remain accessible;
never unpublish them or change previous attempts as a side effect.

## Research: 30–40 questions per private batch, any approved device/AI

1. Read the newest GitHub `main`, current production release, official
   year/round subject and question inventory (`bank_exam_sittings.question_count`),
   approved exact-edition sources and verified private original PDF objects.
   Do not assume another chat's numbers are current.
2. Partition the **full year's** questions into successive **30–40-question
   private research batches** (the final batch can be fewer than 30).
   Subjects may cross batch boundaries. Each batch must independently
   check original current official answers, stem/options/frozen version,
   exact-year syllabus, original PDF evidence, physical PDF page and
   approved original PDF SHA. Mark scientifically uncertain questions as
   pending, never force a convenient chapter or page.
3. Store decisions and independent provenance audits only in protected
   server paths. Commit only scripts/tests/policy/compact nonprivate
   digests to GitHub. Scientific research completion is **not** public
   exam publication.
4. Fix any gaps, including unsupported chapters, missing or mixed PDF
   pages, voided/missing official keys, figure-dependent questions and
   genuinely multi-source items, through their authorized separate
   evidence-preserving routes. **Do not change official key status** to
   turn a voided question into scored content. Explicitly distinguish
   a source-classification requirement from whether a voided question
   may be scored in the final assessment.
5. Recount and independently validate all year subjects together; only
   when zero outstanding non-English citations, no invalid official
   chapter scope, and valid English exceptions remain may a release
   gate be attempted.

## Mandatory server-side frozen-exam release gate

Every `BankPublisher::publish()` for `examType=residency`, including
initial publication and later `addVersion` republish, invokes
`ResidencyYearPublicationGate::requireComplete()` **BEFORE** any
assessment, version, review or publication mutation.

The guard validates:
- the entire persisted sitting inventory size exactly equals its
  declared `bank_exam_sittings.question_count`, and question numbers
  are unique and positive;
- every non-English question has at least one same-row source with
  existing chapter-node ancestry to an **official** validity edition
  for the exact year/exam-type/subject, an in-syllabus chapter,
  positive physical `pdf_page` and 64-hex `pdf_sha256`;
- for a partial chapter, the bound `printed_page` must fall inside its
  explicit page boundary; a page outside that boundary needs an audited
  outside-scope disposition, and an unparseable boundary stays on hold;
- a selected/excluded syllabus without structured allowed chapter
  scope does **not** pass as unrestricted;
- English receives no fabricated book reference, but its latest
  official key must be final/amended with an actual choice.

On any failed check it throws
`residency_year_classification_incomplete` (HTTP 422) without creating
a new version. Older non-residency exam paths remain unchanged.

**The server gate only proves structural DB readiness.** It cannot
inspect copyrighted original PDFs by itself and must not be mistaken
for full scientific certification. The publisher operator must ALSO
have an independently accepted original-book year-wide scientific
manifest, a verified full backup, the exact deployed PDF map,
unchanged original question/option/official-key/attempt digests and
all scope evidence before attempting publication.

## Publishing a fully classified year

1. Prepare and audit a **whole-year** manifest of every question and
   original-page/source audit, protected on the server. All old source
   rows, previous frozen versions and attempts remain immutable.
2. Use approved narrowly scoped source-only writers only where needed,
   with the existing **12 page-only** and **1 chapter+page-correction**
   maximum per plan, rollback-only previews, fresh independently
   verified full backup, unique receipts, and independent live SQL
   readback. These bounded updates are internal to year preparation,
   **not** intermediate site-exam publications.
   For an AI-origin source whose re-audited chapter is outside official
   scope, use the separate guarded
   `scripts/references/repair_verified_ai_outside_scope_chapter_pages.php`
   workflow in `docs/ops/AI_OUTSIDE_SCOPE_CHAPTER_PAGE_REPAIR.md`;
   preserve the original key and record unresolved source/key conflicts.
   When an existing bound PDF page lacks only its printed label, use
   `scripts/references/repair_verified_source_printed_labels.php` to add the
   label needed for the page-level partial-scope check, following
   `docs/ops/VERIFIED_SOURCE_PRINTED_LABEL_REPAIR.md`.
3. Independently verify the **whole** year, not merely one subject,
   including English exemptions, newest scope, full official inventory,
   every chapter and physical page, approved SHA maps, prior assessment
   immutable hashes, and authorised reviewer/owner identity.
4. Invoke the normal `scripts/import/import-bank.php publish` or
   its normal `BankPublisher` equivalent **once for the complete
   year**, subject to its mandatory server-side gate and existing
   review process. Do not create a staged public version on failure.
5. Verify the new frozen version and scoreable/voided exclusions,
   old attempts, CI, GitHub PR merge, official updater deployment,
   live release SHA and `/health`. Only then mark the year closed.
   Partial source writes or a successful merge do not close it.

## 1405 historical snapshot and legacy compatibility

At the 2026-10-16 starting baseline, production 1405 had **223/230**
book-based questions structurally bound, **7** missing exact source
fields, and 20 English book-exempt. Another existing row can be
technically populated but scientifically out of official scope;
**223 is not a scientific completion claim**. Always requery current
DB in a new session. Previously published 1405 frozen versions and
old student attempts remain intact while the seven outstanding
question sources and remaining scope anomalies are resolved.

The previous `docs/ops/SUBJECT_FIRST_SOURCE_PUBLICATION_20261016.md`
is retained as an internal research/guarded-writer *chunking*
reference; its recommendation of site-publishing a one/two-subject
cycle has been superseded. The `AGENTS.md` root instruction makes
the full-year rule mandatory for every new human or AI workflow.
