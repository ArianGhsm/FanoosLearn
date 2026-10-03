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

## 4. Decisions still open

These are the owner's to make; until they are made, the default in brackets
applies.

1. **Brand and domain** — does the dental product keep the FANOOS name and
   fanooslearn.ir? [yes]
2. **The existing medical bank** (868 exams, ~40,000 questions) and the
   university/cohort structure — kept alongside, hidden, or removed?
   [kept in the database, hidden from the dental product's navigation]
3. **Exam types in scope at launch** — دستیاری only, or also بورد and ارتقا?
   [دستیاری first; the schema covers all]
4. **Where question source files live** (scans, Word/Excel files, answer
   keys) — they must not go into the code repository. [a private storage
   location outside Git, with the import scripts in Git]
5. **Who confirms AI classifications** — the owner, invited reviewers, or
   both, and at what confidence threshold. [owner; threshold 0.85]
