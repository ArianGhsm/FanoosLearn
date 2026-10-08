# Question format — file, database, site

How a dental residency question exists at each stage, from the owner's
source files to the screen. The design behind it is
`05_DENTAL_RESIDENCY_DATA_MODEL.md`; this is its concrete form.

## 1. The path a question takes

```
owner's source (PDF / Word / scan + answer key)        private storage, never in Git
        │  converted (by AI, then checked)
        ▼
sitting file  residency-1404-1.json  + images/          the exchange format (§2)
        │  scripts/import/import-bank.php check   → every problem, with its path in the file
        │  scripts/import/import-bank.php import  → the bank tables (§3)
        ▼
bank tables  bank_questions, choices, answers, sources, concepts, explanations …
        │  review: anything below confidence 0.85 waits for the owner
        │  scripts/import/import-bank.php publish
        ▼
an exam on the site  "دستیاری ۱۴۰۴"                    a frozen copy (§4)
```

Two kinds of file go in, both JSON, both checked before anything is
written, both safe to apply again (rows are matched by stable keys):

| File | Holds | Schema | Example |
|---|---|---|---|
| **catalog** | exam types, subjects, concepts, references → editions → chapters, official reference per exam year, edition mappings | `contracts/bank/catalog.schema.json` | `contracts/bank/examples/catalog.example.json` |
| **sitting** | one real exam and its questions | `contracts/bank/sitting.schema.json` | `contracts/bank/examples/sitting.example.json` |

The catalog is imported first; a sitting may only name subjects, concepts
and editions the catalog already has.

**The real catalog** is `data/bank/catalog.json`. It is generated, not
edited by hand: `python scripts/import/reference_map_to_catalog.py` reads
the research workbook `docs/research/dental-residency-reference-map-1396-1405.xlsx`
(reference lists, 1396–1405) and every later year's official list in
`docs/research/dental-residency-references-<year>.json` (1406, transcribed
from the PDF on sanjeshp.ir), matches every book against an explicit
table of references and editions in the script, and writes:

- 13 subjects, the three exam types (دستیاری active; بورد and ارتقا present
  but inactive);
- only the books the medical education assessment center (سنجش پزشکی) named,
  1397–1406: the 1401 file's English books came from an appended page, not
  the official table (which only sets the English level), so the script's
  `NOT_IN_OFFICIAL_LIST` leaves them out with that reason;
- 27 references with 44 editions; 43 of the editions carry their chapter
  list (1,301 chapters) from `data/bank/reference-tocs.json`: the
  publishers' tables of contents, or a library catalogue record where the
  publisher lists none, with the page each was read from (`"language": "fa"`
  marks the Persian national book, whose titles are its own). Chapter n is
  node `ch0n` (`ch12`, and `ch01.3` for van Noort's numbering), the key
  question sources cite. Still without a list: Peterson (Persian
  translation, whose chapter numbering differs from the English 5th
  edition). Daly is the 2013 2nd edition, as the owner confirmed; its
  chapters come from the publisher's Crossref records;
- one validity row per exam year, subject and edition, carrying the
  announced chapter scope (`scope`, e.g. «تمام فصول به جز ۴، ۸، ۱۸») and where
  the announcement came from (`evidence`), and that scope read into the
  edition's chapter numbers (`scope_chapters`: `[{number, partial?}]`,
  `partial` holding the announcement's words when only some pages count).
  `scripts/import/scope_chapters.py` does the reading (tests in
  `tests/import/`); a notice that only adds or drops chapters, or names no
  chapter list, gets no `scope_chapters` and a line in `decisions`;
- each chapter's headings, from `data/bank/reference-sections.json`: the
  sections and subsections inside the chapter, read from the owner's copies
  of 13 books (the PDF's bookmarks, or its heading styles where it has
  none); children of the chapter node, keyed `ch14.s03` and `ch14.s03.02`.
  Only headings are kept, never the text. The page fetches them per
  chapter (`GET /bank/chapter-outline`);
- each chapter's Persian title (`title_fa`, with `title_fa_origin` `ai` until
  a dentist reviews it) from `data/bank/reference-tocs.fa.json`, keyed by the
  English title (chapters and the headings inside them alike, 6,400 titles,
  machine-translated and checked for consistency, origin `ai`);
- `decisions`: every judgement the script made that has been checked
  against the publishers' edition dates (an edition inferred from a year, a
  label that disagrees with its year, a row that is a scope statement rather
  than a book, a partial notice naming two books), each with its reason; the
  checked judgements live in the script's `CONFIRMED`, `SPLIT_NOTICES` and
  `MISSING_YEAR` tables;
- `review_notes`: judgements not yet checked, for the owner to confirm
  (currently none). Neither list is imported.

Concepts are not in the workbook; they are added as questions are
classified.

## 2. One question in the sitting file

```json
{
  "number": 1,
  "subject": "endodontics",
  "stem": "ایده‌آل‌ترین محل خاتمه‌ی طول کارکرد … کدام است؟",
  "stem_image": "images/q001.jpg",
  "choices": ["…", "…", "…", "…"],
  "answer": { "choice": 2, "status": "final", "source": "کلید نهایی" },
  "type": "recall",
  "negative_stem": false,
  "multiple_statement": false,
  "cognitive_level": "recall",
  "difficulty": 2,
  "sources": [{
    "ref": "torabinejad@6e#ch14.working-length",
    "page": "256", "table": "14-2", "figure": null, "box": null,
    "anchor": "Working Length Determination",
    "primary": true,
    "confidence": { "source": 0.97, "node": 0.92, "page": 0.71 },
    "origin": "ai"
  }],
  "concepts": [{ "key": "endodontics/…/apical-constriction", "primary": true, "confidence": 0.94, "origin": "ai" }],
  "explanation": {
    "short": "…", "reference": "…", "location": "Torabinejad 6e · فصل ۱۴",
    "why_wrong": { "1": "…", "3": "…", "4": "…" },
    "tip": "…", "trap": "…",
    "confidence": 0.86, "origin": "ai"
  },
  "currency": [{ "against": "torabinejad@6e", "status": "current", "confidence": 0.9, "origin": "ai" }]
}
```

Only `number`, `subject`, `stem`, `choices` and `answer` are required; a
question can enter the bank with just those and gain the rest later by
re-importing a fuller file.

- **Identity.** The question's key is built from the sitting and its number:
  `residency-1404-1-001` (`type-year-round-number`). It is the id used
  everywhere: statistics, mistakes review, bookmarks, the runner.
- **Choices** are numbered from 1 as printed on the paper. A choice is a
  string, or `{"text", "image"}` when it has a picture.
- **Answer status:** `preliminary`, `final`, `amended` (the official key
  changed), `disputed`, `voided` (question cancelled; `choice` is null).
  Every change is kept as history; disputed and voided questions are left
  out of the published exam.
- **Several accepted options:** when the final key accepts more than one
  option, `choice` is one of them and `also_correct` lists the others
  (`{"choice": 1, "also_correct": [2], "status": "amended"}`). The question
  is published; in the exam any of them scores, and the reveal, the review
  and the mistakes review mark all of them as right (`also_correct` beside
  `answer`/`correct`, 0-based there).
- **References** are written `reference@edition` and
  `reference@edition#node`, using the catalog's keys.
- **Type:** recall, conceptual, clinical_scenario, diagnosis,
  treatment_planning, image_based, calculation; `negative_stem` and
  `multiple_statement` are separate flags.
- **Cognitive level:** recall, understanding, application, analysis.
  **Difficulty:** 1–5, rated by an expert (the observed difficulty comes from
  students' answers on the site).
- **Confidence** (0–1) and **origin** (`ai` / `human`) go on every
  machine-made value. Below 0.85 a value waits for review.
- **Currency** says whether the official answer still holds under a given
  edition: current, valid_old_edition, changed_in_newer, outdated,
  contradicted, removed_from_syllabus.
- **Similarity** between questions goes in the file's top-level `similar`
  list as pairs of question keys: exact_repeat, near_duplicate,
  same_concept.

## 3. In the database

Migration `0030_dental_bank.sql`:

| Part of the question | Table |
|---|---|
| the question, its type, flags, level, difficulty, status, version | `bank_questions` (`question_key` is the identity) |
| choices | `bank_question_choices` (position 1–10, text, image key) |
| official answer, with history | `bank_official_answers` (newest row is current) |
| where in which edition | `bank_question_sources` |
| what concept | `bank_question_concepts` |
| explanation, versioned; why each other choice is wrong | `bank_explanations`, `bank_explanation_choices` |
| still correct under an edition? | `bank_question_currency` |
| related questions | `bank_question_similarity` |
| the exam it was set in | `bank_exam_sittings` → `bank_exam_types` |
| the catalog | `bank_subjects`, `bank_concepts`, `bank_references`, `bank_reference_editions`, `bank_reference_nodes`, `bank_node_concepts`, `bank_reference_validity` (with the year's announced `scope` and its `evidence`, migration 0031, and `scope_chapters`, migration 0037), `bank_reference_nodes.title_fa` (migration 0037), `bank_edition_mappings` |

Re-importing a file updates in place. A changed stem or choice raises the
question's `version`; a changed explanation becomes a new explanation
version. **Rows a person has reviewed are never overwritten by an import**
(`reviewed_by_user_id` set): an AI pass proposes, the review decides.

Images are stored once, by content hash, in the platform's image store; the
tables hold the key.

## 4. On the site

`publish` turns a sitting into an ordinary exam ("دستیاری ۱۴۰۴", kind
past exam, listed under «آزمون‌های دستیاری») whose version holds a **frozen
copy** of its questions. Everything the site already does works on it: the
three modes, the timer, pacing, the review, per-question statistics,
custom practice, the mistakes review and progress. Publishing again after a
correction adds a new version; past attempts keep the version they were
taken on. It goes through the exam's review like any exam: the reviewer is
another account, except that an installation owner (a platform-scope role)
may review their own; that approval is audited with `self_review_by_owner`.

What the student sees for one question:

- **while answering** — the stem and its image, the choices (lettered الف،
  ب، ج، د), the concept as the topic, the subject as a tag, the difficulty,
  and how others did on it;
- **in the review** — the verdict, then the structured explanation rendered
  as: **پاسخ: گزینه‌ی ب**, the short answer, «از رفرنس», «چرا بقیه نه»
  (one line per wrong choice), «نکته‌ی آزمون», «دام رایج», and
  **منبع:** reference · edition · chapter · page.

Still to come on the site (the data is already in place): the
reference-aware target-year selector, currency notices on old answers,
concept and edition filters, and the review queue screen.

## 5. Commands

```sh
# check a file (writes nothing)
php scripts/import/import-bank.php check  --workspace=<id> --file=residency-1404-1.json --assets=./images
# import (or --dry-run to see the counts without keeping them)
php scripts/import/import-bank.php import --workspace=<id> --file=catalog.json
php scripts/import/import-bank.php import --workspace=<id> --file=residency-1404-1.json --assets=./images
# importing only adds and updates; after headings are dropped or renumbered,
# remove the nodes, editions and references that the catalog no
# longer lists (anything a question source, edition mapping or currency check
# points at is always kept; --dry-run first)
php scripts/import/import-bank.php prune-nodes --workspace=<id> --file=catalog.json --dry-run
# publish the sitting as an exam
php scripts/import/import-bank.php publish --workspace=<id> --type=residency --year=1404 --actor=<id> --reviewer=<id> --time-limit=180
```

## 6. From a year's question document

The owner's consolidated per-year documents (`1398.docx` … `1405.docx`) give
each question in printed order: subject headings, the stem with any figure
right after it, the options (الف–د, or a–d in English), an answer line, the
English reading passages before their questions, and a closing key table.
`scripts/import/docx_to_sitting.py` turns one of them into a sitting file and
its images:

```sh
python scripts/import/docx_to_sitting.py --docx=1400.docx --year=1400 --form=A --out=out/1400 --workbook=question-corpus-1400-1405.xlsx
```

- **Text, options, figures** come from the document. A reading passage is
  put before each of its questions. An option the document lacks reads
  «(این گزینه در نسخه‌ی منبع چاپ نشده است)». Options drawn inside a figure
  read «نمودار الف» … «نمودار د», and the figure shows them.
- **Keys:** the documents' answer lines are **not** trusted where an
  official key exists. Checked against the official final key notices, the
  1404 document disagreed on 11 questions, including a deleted and a
  multiple-answer question swapped. With `--workbook`, every answer comes from
  the official final key for that year and form, and each disagreement is
  printed. For 1398 and 1399 no official key is on file, so the document's
  key is used, and the answer's source says so.
- **What is left out, and reported:**
  - questions the document says are missing (1398 Q190, 1399 Q64);
  - an unnumbered question (1398);
  - questions without a usable official key (1401 Q240);
  - questions named with `--leave-out` because a figure they need is not in
    the source (1398 Q22, Q187).
- **Numbering:** 1398 and 1402 are form B documents, so their sittings follow
  form B numbering. The others are form A (1405 has one form).
- **Chapters** are not assigned here; that is a later re-import of the same
  sitting with sources, found in the books' full texts
  (`.local/references/<edition>.txt`, built by
  `scripts/references/build_reference_texts.py`; PROJECT_PRINCIPLES
  decision 6).

Imported and published on 2026-10-07:

| Year | Questions | Published | Notes |
|---|---:|---:|---|
| 1398 (B) | 247 | 245 | chapters for 85 (2026-10-08; the recorded 1398 list names only the references that changed that year, so endodontics, prosthodontics, periodontics, pediatrics and most surgery have no official edition yet) |
| 1399 | 249 | 248 | chapters for 184 (2026-10-08) |
| 1400 | 250 | 249 | chapters for 188 (2026-10-08) |
| 1401 | 249 | 249 | chapters for 196 (2026-10-08; Carranza, Burket, Falace and Neville through the nearest edition, pediatrics through McDonald 11e mapped to Nowak 6e chapters; four questions left unclassified) |
| 1402 (B) | 250 | 243 | chapters for 200 (2026-10-08) |
| 1403 | 250 | 250 | chapters for 200 (2026-10-08; pathology, periodontics and Falace through the nearest edition) |
| 1404 | 250 | 247 | from the hand-checked transcription; chapters for 172 re-verified against the books on 2026-10-08 (33 corrected), the rest keep the transcription's checked chapters. Re-imports must start from `corpus_to_bank.py build` on the reviewed file, not from the docx, or 171 stems change |
| 1405 | 250 | 246 | chapters for 200 (2026-10-08, checked against the books; radiology and community dentistry wait for their books). The booklet's text layer is corrupt, so on 2026-10-08 the wording of 166 questions was replaced by the workbook's visual transcription (same question and option order; Q81, Q92 and Q191 keep their figure-based text) |

"Published" leaves out the questions the official key deleted.
