# FANOOS project principles

This is the first document to read. It states what FANOOS is now, how the three
copies of the project stay identical, and the rules every change follows.
Where an older document disagrees with this one, this one wins and the older
document is corrected or marked historical.

Decided by the owner on 2026-10-03.

## 1. What we are building

**FANOOS is a dental residency exam-preparation platform (دستیاری دندانپزشکی)** —
the kind of product MedoFast is for medicine, built only for dentistry's
residency and related national exams.

What makes it worth more than "a bigger question bank" is the bank itself:

> Every question knows exactly which edition of which official reference it
> came from — chapter, section and page — what that edition says today, and
> how its concept has recurred across the years.

The product principles that follow from that:

1. **The structured bank is the asset.** The UI can be copied; years of
   questions each tied to an edition, a location in it and a concept cannot.
   Every feature reads from the bank; nothing keeps its own copy of question
   facts.
2. **Reference-aware by default.** A student preparing for a given year sees
   the questions that fit that year's official references, and an old answer
   that a newer edition contradicts is labelled as such — never presented as
   current.
3. **Schema complete, UI small.** The data model is designed in full from the
   start (`docs/product/05_DENTAL_RESIDENCY_DATA_MODEL.md`); the interface
   shows only what the current stage needs. Adding a screen later must not
   require re-classifying the bank.
4. **Answers are earned, not leaked.** The pacing, daily cap on new questions
   and per-attempt reveal rules stay. Structured explanations are content the
   product sells; they are served one question at a time to someone entitled
   to them.
5. **AI classifies, people confirm.** Anything an AI assigned (source, chapter,
   page, concept, similarity, explanation) carries a confidence and its origin.
   Below the review threshold it goes to a human before it is shown as fact.

What carries over from the platform built so far: accounts and sign-up, the
exam runner (three modes, timer, pacing, images), per-question statistics,
custom practice, mistakes review, progress, the visual language, the
updater/backup/deploy machinery and the bots. What changes is the content
model underneath them (see the data-model document, §"Relationship to the
current platform").

## 2. Three copies, always the same

The project exists in three places:

| Copy | What it holds | What it is for |
|---|---|---|
| **GitHub** `ArianGhsm/FanoosLearn` | code, migrations, tests, contracts, docs, non-secret config templates | the single source of truth for everything that is not data |
| **Laptop** working copy | a clone of GitHub plus local branches in progress | where changes are made and tested |
| **Server** `fanooslearn.ir` | a release of GitHub `main` (exact SHA), the production database and object storage, secrets | where students use it; the source of truth for data |

"In sync" means all of the following are true:

- the laptop's `main` equals GitHub's `main`;
- the server runs exactly GitHub's `main` (`/srv/fanoos/current` points at that
  SHA's release, and the updater checkout is fast-forwarded to it);
- no work exists only on the laptop: anything in progress is on a pushed
  branch;
- nothing on the server differs from its release (no hand edits).

`scripts/dev/check-sync.sh` checks this and prints any gap.

### Rules

1. **Code moves one way: laptop → GitHub → server.** Change on a branch, push,
   merge to `main` on green CI, then deploy that `main` through the updater.
   Never edit files on the server, never copy files from the server back into
   the repository, never deploy anything that is not a commit on `main`.
2. **Start every piece of work from a fresh `main`.** `git fetch` and
   fast-forward first; branch from there.
3. **End every working session pushed.** A branch that is not finished is
   still pushed (marked WIP in its commit), so the laptop is never the only
   copy.
4. **Merged means deployed.** After a merge to `main`, deploy it in the same
   working session. A merged change that is not on the server is a sync gap
   and is reported as one until it is deployed.
5. **Data moves one way too: production stays on the server.** The production
   database and storage are never overwritten from the laptop. The laptop
   uses a test database built from migrations and seeds. Question content
   reaches production only through versioned import scripts or the admin
   interface, so every import can be repeated and audited.
6. **Secrets live only on the server**, in the root-owned config files; the
   repository holds templates. Nothing secret is printed, pasted or committed.
7. **Schema changes are expand-only migrations**, applied by the updater with a
   verified backup first. Destructive changes go through the supervised
   contract path.
8. **Merged branches are deleted** from GitHub after merge, so the branch list
   shows only work that is actually open.
9. **The owner never operates the server.** Anything the server must do is a
   script in the repository that the updater or an operator runs.

## 3. Engineering rules that stay

AGENTS.md remains in force for repository boundaries, secrets, tests and
release discipline. In particular: tests are never weakened to pass, every
durable fact has one owning module, documentation changes in the same change
as the behaviour it describes, and legacy projects are read-only.

## 4. Decisions

Made by the owner on 2026-10-03:

1. **Brand and domain** — FANOOS and fanooslearn.ir stay.
2. **The medical bank is gone.** The medical library workspace (868 exams,
   about 40,000 questions, 1,424 images) was deleted from production on
   2026-10-03 after a verified backup (`scripts/ops/purge-workspace.php`).
   FANOOS holds only dental content from here on.
3. **Exam types** — دستیاری (residency) first. بورد (board) and ارتقا
   (promotion) come later; the schema carries exam types from the start
   (`bank_exam_types`), so adding them is data, not a redesign.
4. **Reviewer** — the owner confirms machine classifications.
5. **Confidence threshold** — 0.85. Every value an AI assigns (which
   reference, which chapter, which page, which concept, how similar two
   questions are) comes with its own certainty between 0 and 1. At 0.85 or
   above it is used as is; below it, it waits in the owner's review queue
   and is not shown to students as fact. The threshold can be set per field
   (for example stricter for pages than for chapters) once real numbers show
   where the AI is reliable.

Made by the owner on 2026-10-08:

6. **One full-book text per reference edition, in one place.** Chapter
   classification (which reference, which chapter, which page a question
   comes from) is done against the book's own text and nothing else.
   - Every official edition (the catalog's `reference@edition` keys) has
     exactly one file: `.local/references/<edition>.txt` (git-ignored), the
     whole book from its first page to its last, with `=== PAGE n ===`
     before each PDF page.
   - The files are built by `scripts/references/build_reference_texts.py`
     from the list in `data/bank/reference-texts.json`, which names, for each
     edition, its PDF in the owner's book library (outside Git;
     `FANOOS_BOOKS_DIR`) or marks it missing.
   - Summaries, chapter-by-chapter extracts, CDR/DDQ/پارسه booklets and
     translations are not references and are never used for classification.
   - An edition with no complete copy is listed as missing; until the owner
     supplies it, its questions are matched in the nearest edition named in
     the list, mapped back to the official edition's chapter, and marked as
     such.
   - The procedure -- the same for every exam type and every agent -- is
     docs/product/09_CHAPTER_CLASSIFICATION.md: a source is accepted only
     with its page and a quote from that page, checked against the book by
     `scripts/references/apply_classification.py`.
7. **Chapter classification is one procedure for every agent, and its
   checks are never bypassed.** Whoever classifies -- Claude, Codex or a
   person -- follows docs/product/09_CHAPTER_CLASSIFICATION.md end to end:
   - a source is accepted only with an official edition, a chapter, a page
     inside that chapter and a quote from that page, all checked by
     `apply_classification.py`; to make a decision pass, the decision
     changes, never the catalog, the chapter map, the texts or the scripts;
   - a question not found after honest re-searching stays undecided; `none`
     is only for a question no official reference can cover;
   - a human-checked chapter is replaced only with a stated reason
     (`override_human`);
   - a year is imported only from the sitting that matches the site, proven
     by a dry run that changes no question, after a verified backup;
   - every mistake found in the work becomes a guard in the scripts or a
     line in 09 §8, so the next agent cannot repeat it.

Still open:

- **Where question source files live** (scans, Word/Excel files, answer keys).
  They must not go into this public repository. [default: a private storage
  location outside Git, with the import scripts in Git]
- **The university/cohort structure and the bots' class features** built for
  the earlier product — kept, simplified or removed as the dental product
  takes shape. [default: kept until the dental product replaces them]
