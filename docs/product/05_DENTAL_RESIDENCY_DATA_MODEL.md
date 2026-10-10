# Dental residency bank — data model

The design behind principle 1 of `docs/PROJECT_PRINCIPLES.md`: the question
bank is the product, so its structure is designed in full before any screen
needs it. Tables are named `bank_*`; everything is workspace-scoped like the
rest of the platform (the bank lives in one library workspace), and every
change follows the expand-only migration rule.

This is the agreed design, not yet a migration. Each stage below turns part
of it into migrations, with tests, in its own change.

## 1. The rules the model is built on

1. **A chapter belongs to an edition, never to a subject or a question
   directly.** Chapter 8 of Torabinejad 2021 is not chapter 8 of the next
   edition; it may move, merge or split.
2. **A concept is independent of any edition.** "Working length" is one
   concept whether it sits in chapter 5 of one edition or chapter 6 of the
   next. Chapters and sections point at concepts; questions point at
   concepts. That is what makes "every working-length question in every
   edition" one query.
3. **Which reference counts in which year is an official fact, recorded,
   never inferred** from where questions happened to come from.
4. **Editions are mapped to each other explicitly** (equivalent, partially
   equivalent, no longer present), so a new edition re-anchors the bank
   instead of re-classifying it.
5. **Repeating a question and repeating a concept are different things**,
   stored differently.
6. **Every machine-made classification carries a confidence and an origin**,
   and is reviewed by a person below the threshold.
7. **An answer is judged against a reference edition.** A question can be
   correct for the year it was set and wrong under today's edition; the
   model records both.
8. **Attempts keep a frozen copy of what was asked.** The bank can be
   corrected; a past attempt's question, choices and verdict never change
   under the student.

## 2. Entities

### Exams

| Table | Purpose | Key fields |
|---|---|---|
| `bank_exam_types` | دستیاری first; بورد and ارتقا added later as rows, not schema | `key`, `name`, `is_active` |
| `bank_exam_sittings` | one real exam: a type in a year (and round) | `exam_type_id`, `year` (Jalali), `round`, `held_on`, `question_count`, `answer_key_status` |
| `bank_subjects` | the dental subjects (اندو، پریو، …) | `key`, `name`, `parent_id` (for sub-specialties) |

### References

| Table | Purpose | Key fields |
|---|---|---|
| `bank_references` | a book or source (Torabinejad, Carranza, …) | `title`, `authors`, `subject_id` |
| `bank_reference_editions` | one edition of it | `reference_id`, `edition_label`, `published_year`, `isbn` |
| `bank_reference_nodes` | the edition's own tree: chapter → section → subsection | `edition_id`, `parent_id`, `kind` (chapter/section/subsection), `number`, `title`, `page_start`, `page_end`, `ordinal` |
| `bank_reference_validity` | the official reference list per exam year | `exam_type_id`, `exam_year`, `subject_id`, `edition_id`, `is_official`, `source_document`, `recorded_at` |
| `bank_edition_mappings` | old node ↔ new node | `from_node_id`, `to_node_id` (null when removed), `relation` (equivalent / partial / removed), `note`, `confidence`, `origin` |

### Concepts

| Table | Purpose | Key fields |
|---|---|---|
| `bank_concepts` | edition-independent knowledge tree: topic → subtopic → concept | `subject_id`, `parent_id`, `level`, `name`, `name_en` |
| `bank_node_concepts` | which reference sections teach which concept | `node_id`, `concept_id` |

### Questions

| Table | Purpose | Key fields |
|---|---|---|
| `bank_questions` | the question itself | `sitting_id` (null for non-exam questions), `number_in_sitting`, `subject_id`, `stem`, `stem_images`, `question_type`, `is_negative_stem`, `is_multiple_statement`, `cognitive_level`, `expert_difficulty`, `status` (draft / reviewed / published), `version` |
| `bank_question_choices` | ordered choices | `question_id`, `position`, `text`, `image` |
| `bank_official_answers` | the official key, with its history | `question_id`, `choice_position` (null when voided), `also_correct_positions` (other accepted choices, JSON), `status` (final / amended / disputed / voided), `source`, `recorded_at` |
| `bank_question_sources` | where in which edition the question comes from | `question_id`, `edition_id`, `node_id`, `page` (legacy text), `pdf_page`, `printed_page`, `pdf_sha256` (the exact PDF file), `table_ref`, `figure_ref`, `box_ref`, `anchor_text`, `is_primary`, confidences (`source`, `node`, `page`), `origin` (ai / human), `reviewed_by`, `reviewed_at` |
| `bank_question_concepts` | the concept(s) it tests | `question_id`, `concept_id`, `is_primary`, `confidence`, `origin` |
| `bank_question_similarity` | how two questions relate | `question_a`, `question_b`, `relation` (exact_repeat / near_duplicate / same_concept), `confidence`, `origin` |
| `bank_question_currency` | is the official answer still right under a given edition | `question_id`, `against_edition_id`, `status` (current / valid_old_edition / changed_in_newer / outdated / contradicted / removed_from_syllabus), `note`, `reviewed_by` |

`question_type` is one of recall, conceptual, clinical scenario, diagnosis,
treatment planning, image-based, calculation; negative stem and
multiple-statement are separate flags because they combine with any type.
`cognitive_level` is recall, understanding, application or analysis.

Similarity is stored as pairs, which are the facts; clusters ("this concept
came 13 times in 10 years, 4 of them near-identical") are derived from the
pairs and the concept links, not stored by hand.

### Explanations

| Table | Purpose | Key fields |
|---|---|---|
| `bank_explanations` | the structured answer, versioned | `question_id`, `version`, `short`, `reference_explanation`, `source_location` (text), `source_edition_id` / `source_pdf_page` / `source_pdf_sha256` (the book page it was written from), `exam_tip`, `common_trap`, `origin`, `reviewed_by`, `published` |
| `bank_explanation_choices` | why each other choice is wrong | `explanation_id`, `choice_position`, `text` |

"Why is choice 2 wrong?" is a read of a stored row, never a live AI call.

### Difficulty

- **Expert-rated** — `bank_questions.expert_difficulty`.
- **Observed** — from the platform's own counters
  (`exam_question_stats`: share of students who answered correctly). It
  becomes the more trusted of the two once enough answers exist.

### The student's state per question

Already built: `exam_question_user_stats` (answered, correct, last verdict,
last time), the in-attempt flag, the daily read list. Added for the dental
product:

| Field | Meaning |
|---|---|
| `bookmarked` | saved across exams (`exam_question_bookmarks`, in progress) |
| `note` | the student's own note |
| `confidence` per answer | sure / between two / guess — recorded with each answer in the attempt |
| `streak`, `best_streak` | consecutive correct answers |
| `mastered` | derived, never set by hand: correct *and* sure on recent answers; a correct guess does not count |
| `unsure`, `skipped` | from the attempt: answered with low confidence, or passed over |

## 3. Reference-aware mode

The student chooses a target:

- **"دستیاری ۱۴۰۶"** — only questions whose source (directly, or through an
  edition mapping, or through a concept the year's editions teach) is within
  that year's official references, and whose currency against those editions
  is not outdated, contradicted or removed.
- **"Current questions"** — the same against the latest official year.
- **"All historical questions"** — everything, with old-edition and outdated
  questions labelled.

A question whose official answer belongs to an older reference carries the
notice, e.g. «پاسخ رسمی مربوط به رفرنس آزمون ۱۳۹۶ است؛ در ویرایش فعلی این مطلب
تغییر کرده است.»

## 4. Review workflow for machine classification

1. An import or an AI pass writes sources, concepts, similarity, currency and
   explanations with `origin = ai` and a confidence per field.
2. Anything below the threshold (0.85, decided by the owner; per-field
   thresholds allowed) is in the review queue and is not shown to students
   as fact. The owner is the reviewer.
3. A reviewer confirms or corrects it; the row records who and when, and
   `origin` becomes `human`.
4. Nothing reviewed is overwritten by a later AI pass; a re-run proposes
   changes, it does not apply them.

## 5. Relationship to the current platform

Today a question exists only inside an assessment version's
`definition_json`. In the new model the bank is first-class, and an exam
becomes a selection from it:

- **A past exam paper** = a `bank_exam_sitting`, published as an assessment
  whose version holds a frozen copy of its questions at publish time (so
  attempts, pacing, review, stats and images keep working unchanged).
- **Practice, custom practice and reference-aware sets** = assessments built
  from bank queries, frozen the same way.
- The stable question id the stats, mistakes review, bookmarks and daily read
  list already key on becomes the `bank_questions` id.
- The runner, attempts, scoring, statistics, progress and custom practice are
  reused as they are; they gain filters (exam year, subject, edition, concept,
  currency) as the bank provides them.

## 6. Build order

1. **Foundation** — exam types, sittings, subjects, references, editions,
   nodes, validity, concepts; an import format and importer for them.
2. **Questions** — questions, choices, official answers; importer for past
   papers; publishing a sitting as an exam.
3. **Sources and concepts** — question sources and concepts with
   confidence; the review queue and its admin screen.
4. **Explanations** — structured explanations, per-choice reasons; shown in
   the runner's review.
5. **Reference-aware mode** — validity, currency, edition mappings; the
   target-year selector.
6. **Repetition analysis** — similarity pairs, concept recurrence across
   years.
7. **Student state** — confidence per answer, mastery, notes, spaced review.
