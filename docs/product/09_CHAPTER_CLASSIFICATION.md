# Chapter classification

How a question gets its source: which official reference, which chapter,
which page. The procedure is the same for every exam type the catalog
carries (دستیاری, بورد, ارتقا …), every year, and every agent or person who
does the work. It is written so another agent (Codex or any other) can be
handed a sitting and this document and produce the same kind of result.

The governing rule is PROJECT_PRINCIPLES decision 6: classification is done
against the book's own text, one full-book text per edition, and nothing
else.

## 1. What a result must be

A question's source is **found, not guessed**. It is accepted only with:

- an **official edition** for that exam type, year and subject (the
  catalog's `validity`), or the nearest edition named for a missing one;
- the **chapter** of that edition the content is in;
- the **page** it is on, inside that chapter;
- **evidence**: the book's own words from that page, quoted exactly;
- a **confidence** from 0 to 1.

`scripts/references/apply_classification.py` checks every one of these
against the books before anything is written. A chapter chosen from chapter
titles, a summary or a booklet, or from memory, fails that check because
there is no page and no quote to back it. The rejected classifications of
the past did exactly that: on the 20 endodontics questions of 1403, a third
of the chapters assigned from titles alone (some at "0.95 confidence") were
wrong once the book was read.

## 2. What the work needs

| What | Where |
|---|---|
| The official reference list per exam type, year and subject, with scope | `data/bank/catalog.json` → `validity` |
| Each edition's chapter list | `data/bank/catalog.json` / `data/bank/reference-tocs.json` |
| Which file each edition's text is built from, and which editions are missing | `data/bank/reference-texts.json` |
| The full texts | `.local/references/<edition>.txt` (git-ignored) |
| Which chapter every page is in | `data/bank/reference-chapter-pages.json` |
| The questions | a sitting file (`fanoos.bank.sitting/1`, docs/product/06_QUESTION_FORMAT.md) |
| Decisions | `.local/classification/decisions/<sitting>-<subject>.json` |

Setting up once (or after a new book arrives in the owner's library):

```sh
python scripts/references/build_reference_texts.py --library "<book library>"
python scripts/references/build_chapter_pages.py
```

A new exam type, year or edition is data: add the reference and its edition
to the catalog (with its chapter list), the year's official list to
`validity`, the edition's PDF to `reference-texts.json`, and rebuild. The
procedure does not change.

## 3. The procedure

One batch is one sitting and one subject (10–30 questions).

**Step 1 — open the batch.**

```sh
python scripts/references/classification_batch.py --sitting=<sitting.json> \
    --subject=<subject> --out=<queries.json>
```

It prints the official editions (and, for a missing one, the edition to
search instead) and every question with its official answer, and writes a
query file with the editions filled in.

**Step 2 — write the search terms.** For each question, write into `terms`
the English words the book would use for the fact the question tests:
technical terms from the stem and from the **correct** option, the specific
numbers, named techniques and instruments. Translate Persian into the book's
vocabulary. Terms of several words count most when the words occur together.
Think about what the right answer is, and search for that, not for the
question's general topic.

**Step 3 — search.**

```sh
python scripts/references/find_in_books.py <queries.json>
```

For each question: the best pages, each with its chapter and the matching
lines. Index, contents and bibliography pages are skipped.

**Step 4 — decide, reading the text.** Choose the page whose text actually
states the fact that makes the correct option correct (or the incorrect ones
incorrect). Not the page that merely mentions the topic. If the top results
do not state it, search again with different terms (step 2) until a page
does, or conclude it is not in the book.

- The fact is in two chapters: take the one that teaches it (its own
  section), not the one that refers to it in passing.
- The page is in a chapter outside the year's announced scope (`scope` in
  `validity`): look for the same fact inside the scope first; if it is only
  outside, keep it and lower the confidence.
- The question is not answerable from any official reference, or the
  subject has no official reference that year (English, often community
  dentistry): record `none` with the reason.

**Step 5 — record.** One decision per question in
`.local/classification/decisions/<year>-<subject>.json`:

```json
[
 {"number": 22, "edition": "torabinejad-endodontics@6e", "chapter": "20", "page": 446,
  "confidence": 0.97,
  "evidence": "the flap should be compressed with a saline soaked gauze and firm finger pressure for a minimum of 3 minutes ... formation of a hematoma under the flap"},
 {"number": 241, "none": "English reading passage; no official reference"}
]
```

- `page` is the PDF page as `find_in_books.py` printed it (`p446`).
- `evidence` is copied **word for word** from that page as the search
  printed it; omissions are marked with `...`; every fragment has at least
  four words. A paraphrase is rejected.
- `confidence`: 0.95+ when the quote states the answer outright; 0.85–0.94
  when it states it but the question's wording differs or two chapters
  teach it; below 0.85 when it is an inference — those go to the owner's
  review queue (PROJECT_PRINCIPLES decision 5).
- For a missing official edition, `edition` is the nearest one you searched;
  the check carries the chapter over to the official edition by its title
  and the source cites the official edition. When the editions were
  reorganised (a chapter renamed, split or merged — Carranza 13 → 14 is
  the common case), name the official edition's chapter yourself with
  `"official_chapter": "18"`, chosen from that edition's chapter list for
  the fact the quote states; the check confirms the chapter exists.

**Step 6 — check and write.**

```sh
python scripts/references/apply_classification.py --sitting=<sitting.json> \
    --decisions=.local/classification/decisions/ --out=<sitting-with-sources.json>
```

Every rejection is listed with its reason (wrong edition for the year,
chapter not in the catalog, page outside the chapter, quote not on the page,
…); fix and run again. With no rejections it writes the sitting with
`sources` — reference, chapter node, printed page, the quote as anchor,
confidence — ready to import with `scripts/import/import-bank.php`
(06_QUESTION_FORMAT.md §5–6). Imported sources are `origin: ai` until the
owner reviews them.

## 4. Handing a batch to another agent

Give the agent this document, the sitting file, the subject, and access to
the repository and `.local/references/`. Its deliverable is the decisions
file and a clean `apply_classification.py` run — nothing else. It must not
edit the catalog, the chapter map, the texts or the scripts to make a
decision pass; a decision that cannot pass is reported, with the reason.

## 5. When a book is missing

`reference-texts.json` lists editions with no complete copy (`missing`),
with the nearest available edition (`nearest`). Until the owner supplies the
book:

- search the nearest edition; the source is recorded against the official
  edition's same-titled chapter, its page left empty, and the anchor says
  which edition the quote came from;
- an edition with no nearest edition cannot be classified; its questions
  wait.

When the book arrives: add its PDF to `reference-texts.json`, rebuild the
text and the chapter map, and re-run those batches.
