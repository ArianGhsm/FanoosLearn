# Chapter classification

How a question gets its source: which official reference, which chapter,
which page. The procedure is the same for every exam type the catalog
carries (دستیاری, بورد, ارتقا …), every year, and every agent or person who
does the work. It is written so that another agent (Codex or any other) can
be handed a year and this document and produce the same kind of result,
without breaking what is already on the site.

The governing rules are PROJECT_PRINCIPLES decisions 6–7 and the
**2026-10-09 server-first policy**: classification uses the reference book's
own exact page-marked text; its checks are never bypassed. **No laptop is
required.**

Everything here was learned on 1398–1405 (2026-10-08). §8 lists the mistakes
that were made and what now stops each of them.

## 1. What a result must be

A question's source is **found, not guessed**. It is accepted only with:

- an **exact, verified, available official edition** for that exam type,
  year and subject (catalog `validity`); old nearest-edition assignments
  are historical, not the default for new completion work;
- the **chapter** of that edition the content is in;
- the **page** it is on, inside that chapter;
- **evidence**: the book's own words from that page, quoted exactly;
- a **confidence** from 0 to 1.

`scripts/references/apply_classification.py` checks every one of these
against the books before anything is written. A chapter chosen from chapter
titles, a summary or a booklet, or from memory, fails that check because
there is no page and no quote to back it. Title-based classification was
measured: on 1403 endodontics a third of such chapters were wrong, and on
1404 a quarter (49 of 188) differed from the page-verified chapter.

## 2. What the work needs

| What | Where |
|---|---|
| The official reference list per exam type, year and subject, with scope | `data/bank/catalog.json` → `validity` |
| Each edition's chapter list | `data/bank/catalog.json` / `data/bank/reference-tocs.json` |
| Which file each edition's text is built from, and which editions are missing | `data/bank/reference-texts.json` |
| The complete page-marked full texts | server private `/srv/fanoos/shared/research/references/<edition>.txt` (`--local` points to `/srv/fanoos/shared/research`) |
| Which chapter every page is in | `data/bank/reference-chapter-pages.json` |
| The questions, as they are on the site | the year's sitting file (§4) |
| Decisions | protected server `/srv/fanoos/shared/research/classification/decisions/<year>-<subject>.json` (must be durably backed up) |
| Server access for the import | authorized access to the IranServer production host (direct authenticated operator/SSH access or SentinelX when available); see `docs/ops/SERVER.md` |

**Preflight on the server:** run the private reference-library inventory;
check that the exact official edition has an approved PDF, authenticated
read access, sufficient free disk space, a full page-marked text and complete
chapter-page runs. Do **not** assume that a registered PDF is already
searchable. The approved private-object-to-text bridge and backup of the
research workspace must be in place first (see `docs/ops/SERVER.md`).
Do not copy entire PDFs from the server to a laptop. Use the builder's
`--local` argument for the secure server workspace; build **only**
needed editions, not the entire library at once:

```sh
python scripts/references/build_reference_texts.py --library "<approved server book input>" \
    --local=/srv/fanoos/shared/research --only <edition>
python scripts/references/build_chapter_pages.py --library "<approved server book input>" --only <edition>
```

The documented commands illustrate the supported interfaces; the book input
must be securely prepared from the verified private object by an approved
operator. If that reader/input does not yet exist, mark the edition pending,
never invent a public/local path.

When a book is added, build its text as above, then update only its chapter
map (repeat `--only` for several new editions):

```sh
python scripts/references/build_chapter_pages.py --library "<book library>" --only <edition>
```

A new book means: its PDF named in `reference-texts.json` (the `missing`
mark removed), its text built, and its chapter pages built. A full rebuild
requires reviewing changed boundaries in existing editions before replacing
their maps. Check that every chapter of the edition has a run of pages.
Inspect the first chapter, the
last chapter and any weakly supported boundaries too: a complete count alone
does not prove the page ranges are right. A chapter opening printed inside a
page pins that page as its start; standalone Persian `n فصل` running heads
serve the same purpose. Contents pages and roman-numbered summary pages do
not establish a chapter start. If a range is wrong, fix the general mapping
method from the book's own pages and add a regression test before classifying;
never adjust a range merely to make a particular decision pass. A new exam
type, year or edition is data: the reference and its edition in the catalog (with its
chapter list), the year's official list in `validity`, the PDF in
`reference-texts.json`. The procedure does not change.

When the PDF has an ordered bookmark for every chapter, `--library` uses those
bookmarks as page boundaries. A partial or unordered bookmark list falls back
to chapter openings in the text; missing chapters remain visible in the
builder's report. The Persian national book uses its own Persian words for
`terms` and `evidence`; the search and quote check preserve Persian letters.
When the PDF has no chapter bookmarks and its opening labels omit the chapter
number, record the PDF page starts transcribed from that edition's printed
contents in `reference-texts.json` as `chapter_pdf_starts`, with the source
page documented. The builder requires one strictly ordered start for every
catalog chapter. Proffit 5e uses this rule (contents on PDF pages 18–20).

### Private post-validator audit (server-first)

For multiple read-only study-only batches, also run
`python3 scripts/references/audit_private_study_batches.py` with one
`--batch=YEAR:subject:stem` per batch, `--local` pointed at the protected
server research workspace and `--out` under its `classification/reports/`
folder. This independently checks *no changes* to the study question stems,
choices or answers, exact question identity, source-node reference and
chapter, mapped PDF/printed page, origin and confidence. It does **not**
import or publish production sources and never exports copyrighted text.
See `docs/ops/RESIDENCY_CLASSIFICATION_EXECUTION_20261010.md` for the
exact command and verifiable expected counts. Run the original
`apply_classification.py` evidence validator before auditing. A study-only
sitting is deliberately **not** an importable bank sitting.


### English subject: official-key-only exception (2026-10-10 owner decision)

For residency `english` there are **no official reference-validity rows**. A complete, valid official `final` or `amended` answer key is the final classification requirement: do **not** search an unrelated textbook, assign a chapter/page/evidence, generate an artificial `bank_question_sources` row, or classify those questions as "blocked for lack of a PDF." An English answer with missing, disputed, voided, or otherwise invalid key is a separate answer-review issue and must not be silently approved. Preserve stems, reading passages, all options, images, keys including corrections/multiple answers, human decisions, historical frozen assessments and attempts.

Report the counts separately, leaving the raw source-row count honest:
`raw_no_source`, `english_no_source_with_valid_key_exempt`, and
`non_english_no_source_requiring_source_review`. At the read-only snapshot
2026-10-10, these are **393**, **159**, **234**, respectively, of 1,995 residency questions. In the 159 English records, 158 current keys are `final` and one is `amended`; zero are missing/voided/null choice. No bank source insert or assessment republish is implied or permitted by this exemption. Authority: `docs/PROJECT_PRINCIPLES.md`, section 3A.

## 3. The procedure

One batch is one sitting and one subject (10–30 questions).

**Step 1 — open the batch.**

```sh
python scripts/references/classification_batch.py --sitting=<sitting.json> \
    --subject=<subject> --out=<queries.json>
```

It prints official editions, announced scope and questions with official
answers. **Default queries exclude missing exact editions.** The optional
`--include-nearest` switch exists for auditing historical mappings, not
for this new completion pass. No decision may be made against an absent
exact official edition by default. "No official reference for … these questions get no source" means
exactly that: do not classify that subject for that year (§5).

**Step 2 — write the search terms.** For each question, write into `terms`
the English words the book would use for the fact the question tests:
technical terms from the stem and from the **correct** option, the specific
numbers, named techniques, instruments, drugs, syndromes. Translate Persian
into the book's vocabulary. Five or six terms; terms of several words count
most when the words occur together. Search for what makes the right answer
right, not for the question's general topic.

**Step 3 — search.**

```sh
python scripts/references/find_in_books.py <queries.json> --top 3 --local=/srv/fanoos/shared/research
```

For each question: the best pages, each with its chapter, the matching
lines, and a `quote:` line — ten words from that page around the best
match, already checked the way step 6 checks evidence.

**Step 4 — decide, reading the text.** Choose the page whose text actually
states the fact that makes the correct option correct (or the incorrect ones
incorrect), not a page that merely mentions the topic. If the top results do
not state it, search again with other terms (`--top 5`, the wording of the
book rather than of the question, the wrong options' terms) until a page
does. Only after two or three honest attempts may a question be left
undecided (§5).

- The fact is in two chapters: take the one that teaches it, not the one that
  refers to it in passing.
- The page is outside the year's announced `scope`: look for the same fact
  inside the scope first; if it is only outside, keep it and lower the
  confidence.
- Review questions at the end of a chapter, the index, tables of contents
  and reference lists are never the evidence page.

**Step 5 — record.** One decision per question in
`/srv/fanoos/shared/research/classification/decisions/<year>-<subject>.json` (only this year's
files are read for a sitting, by the `<year>-` prefix):

```json
[
 {"number": 22, "edition": "torabinejad-endodontics@6e", "chapter": "20", "page": 446,
  "confidence": 0.97,
  "evidence": "it contains a small scissors that can also cut the suture"},
 {"number": 118, "edition": "carranza-periodontology@14e", "chapter": "32", "page": 777,
  "official_chapter": "25", "confidence": 0.95,
  "evidence": "trauma from occlusion occurs in the supporting tissues and does not"},
 {"number": 241, "none": "English language section; the official list names no reference for it"}
]
```

- `page` is the PDF page as the search printed it (`p446`), not the printed
  page number.
- `evidence` is copied **word for word** from the search output — best, the
  `quote:` line. Several fragments are joined with `...`; every fragment has
  at least four words. Never a paraphrase, a translation or a summary.
  Words broken by the PDF (`efective`, `he` for "The", `dierent`, `àap`) are
  copied as printed; avoid fragments with symbols (`≤`, `•`, `‐`, `#`,
  superscript reference numbers) when a plain run of words is available.
- `confidence`: 0.95+ when the quote states the answer outright; 0.85–0.94
  when it states it but the wording differs or two chapters teach it;
  0.6–0.84 when it is the right section but the exact fact is inferred;
  below 0.6 only after re-searching, and it goes to the owner's review
  queue (PROJECT_PRINCIPLES decision 5).
- **New batches use the exact official edition only.** For auditing earlier
  nearest-edition assignments, the `--include-nearest` batch flag and
  `--allow-nearest` apply flag must be explicitly supplied; the chapter
  carry-over logic and `official_chapter` are historical (§6). Do not
  silently use these exceptions for new decisions.

**Step 6 — check and write.**

```sh
python scripts/references/apply_classification.py --sitting=<sitting.json> \
    --decisions=/srv/fanoos/shared/research/classification/decisions/ \
    --out=/srv/fanoos/shared/research/classification/sittings/<sitting>.json \
    --local=/srv/fanoos/shared/research
```

Every rejection is listed with its reason; fix the decision and run again —
never the catalog, the chapter map, the texts or the scripts. With no
rejections it writes the sitting with `sources` (reference, chapter node,
printed page, the quote as anchor, `origin: ai`, confidence).

**Step 7 — put it on the site** (§7).

## 4. Which sitting file a year starts from

The sitting given to steps 1 and 6 must carry **the same text the site
has**, or the import rewrites the questions. The dry run (§7) proves it.

| Year | Sitting that matches the site |
|---|---|
| 1398–1403 | protected server `bank-sittings/<year>/residency-<year>-1.json` under `/srv/fanoos/shared/research`, originally made from the owner's `<year>.docx` with `scripts/import/docx_to_sitting.py` (06_QUESTION_FORMAT.md §6) |
| 1404 | built from the hand-checked transcription: `python scripts/import/corpus_to_bank.py build --workbook=<corpus.xlsx> --year=1404 --form=A --reviewed=.local/exam-questions/dental-residency/review-1404/form-a.import.json --catalog-out=<tmp> --sitting-out=<sitting>`, images from `review-1404/assets`. The docx sitting differs in 171 stems (spacing) and must not be imported |
| 1405 | protected server `bank-sittings/1405/residency-1405-1.json` under `/srv/fanoos/shared/research` (must be migrated/verified before use) — since 2026-10-08 it holds the workbook's visual transcription (the booklet's text layer is corrupt); the docx text is kept beside it as `.docx-text.json` for reference only |

A future year is added to this table when its sitting is first imported.

## 5. None, undecided, and what is already there

- **`none`** is only for a question that cannot have an official source:
  the subject has no official reference that year (English; a subject the
  year's list leaves out), or the question is about something no official
  reference covers. Its reason says which.
- **Not found yet is not `none`.** A question whose fact was not found after
  re-searching is left **out of the decisions file** (undecided). Undecided
  questions keep whatever source they already have and are retried when
  books are added.
- **A human-checked source is never replaced silently.** If the sitting
  already carries a source with `origin: human`, a decision for a different
  chapter — or `none` — is rejected unless it carries `"override_human":
  "<why this one is better>"`. The same chapter keeps the human source as
  it is. When the AI and the human disagree, compare both pages and keep the
  human chapter unless the quote clearly states the fact and the other does
  not.
- Questions whose **exact official edition** is unavailable, whose approved
  PDF cannot be securely read, or whose full text/page map is not verified
  stay undecided and are listed in the pending report. Continue eligible
  subjects without waiting for them.

## 6. Historical nearest editions and `official_chapter` (legacy)

**Do not use nearest-edition substitution for new classification by default
under the 2026-10-09 owner decision.** The following preserves the rules
needed to audit *existing* substitutions and does not authorize new ones.
Historically, when an official edition was missing, an explicitly named
nearest edition was searched and the official edition cited. Chapters with the same title are carried over automatically;
the rest need `official_chapter`, chosen from the official edition's chapter
list (`data/bank/reference-tocs.json`) for the fact the quote states. Pairs
met so far:

- **Carranza 14 → 13.** Reorganised throughout. Usual targets (14 → 13): 4 →
  3, 5 → 5, 8 → 7, 9 → 11, 10 → 8, 14 → 16, 15 → 17 or 18, 17 → 20, 19 →
  19, 22 → 23 or 24, 23 → 12, 25 → 14, 26 → 15, 31 → 49, 32 → 25, 38 → 32,
  39 → 33, 40 → 34, 41 → 35, 45 → 70, 49 → 46, 50 → 48, 51 → 50, 52 → 51,
  53 → 52, 61 → 60, 62 → 62 (resective) or 64 (furcation), 66 → 69, 69 →
  45, 70 → 72. Confirm against the titles each time.
- **Proffit 6 → 5.** 1–10 same; 11 → 11, 12 → 12, 13 and 14 → 13, 15 → 14,
  16 → 15, 17 → 16, 18 → 17, 19 → 18, 20 → 19.
- **McDonald & Avery 11 standing in for Nowak 6.** Different books: Nowak is
  arranged by age (birth–3: 12–16, 3–6: 18–27, 6–12: 30–36, adolescence:
  37–41). Choose the Nowak chapter for the topic **and** the patient's age in
  the question; lower the confidence by about 0.1 for the mapping.
- Burket 13 for 12, Little & Falace 10 for 9, Neville 5 for 4, McCracken 13
  for 12, Malamed 7 for 6: titles carry over; when the check says otherwise,
  name the chapter.

When the official edition itself arrives, re-run those batches against it:
its own page and quote replace the nearest-edition source.

## 7. Putting it on the site

**New source-only workflow for existing published, unsourced questions
(2026-10-10):** Follow
`docs/ops/SOURCE_ONLY_PUBLICATION_20261010.md`. The vetted
`scripts/references/import_verified_sources.php` inserts **only**
`bank_question_sources`, with exact-edition/scope, current live
question+choice+answer matching, all-or-nothing rollback-only preview,
verified full backup, and private post-commit receipt. Apply one validated
batch, verify live source count and published content, then proceed to the
next. A study-only JSON file must **never** be passed to the full
`import-bank.php` sitting importer. No `publish` action is needed when
an already published question acquires a source-only row. The historic
complete-sitting procedure below is only for true full-sitting updates,
not this source-completion operation.


The authorized agent/operator works on the FANOOS server using the
versioned procedures in `docs/ops/SERVER.md` and access-controlled runtime
configuration. No laptop or `.local/SERVER_ACCESS.md` is required.
Do not touch the unrelated workload. Per year:

1. Copy the classified sitting and its images to
   `/var/lib/fanoos/bank-import-<year>/` (owned by `fanoosweb`).
2. **Dry run** — `import-bank.php import --dry-run`. `questions_changed`
   must be **0** (only sources change). Anything else means the sitting does
   not match the site (§4): stop, find the right sitting, never import.
3. **Backup** — `scripts/ops/backup.php`, then `verify-backup.php` on it.
4. **Import**, then **publish** (`import-bank.php publish --type=residency
   --year=<year>`), exactly as 06_QUESTION_FORMAT.md §5 describes.
5. Record the year in 06_QUESTION_FORMAT.md's table (how many questions have
   chapters, what waits for which book) in a pull request.

A wording correction (a garbled stem or option replaced with a verified
transcription) is a separate, deliberate change: its dry run shows exactly
the corrected questions as changed, the answers and option order stay, and
06 records it.

## 8. Mistakes that were made, and what stops them now

| Mistake | Guard |
|---|---|
| Chapters chosen from titles, not text | evidence + page checked by `apply_classification.py` |
| Paraphrased or too-short quotes rejected after a long batch | `find_in_books.py` prints a ready, pre-checked `quote:` for every hit; fragments of ≥ 4 words |
| A quote containing ligature glyphs or a soft hyphen failed | normalised on both sides (`flat()`); prefer plain runs of words |
| Decisions of one year read into another | only `<year>-*.json` is read for a sitting |
| A reference that is not official that year (Craig for 1399–1401 materials) | the edition must be in that year's `validity` |
| A carried-over chapter landed on the wrong chapter after a reorganisation | carry-over only by identical title; otherwise `official_chapter` |
| The docx sitting of 1404 would have rewritten 171 stems | §4 table; dry run must show `questions_changed: 0` |
| An AI pass overwrote human-checked chapters (49 on 1404) | `override_human` required to replace a human source |
| `none` used for "not found", removing an existing source | §5: not found stays undecided |
| Deploy requested before CI on `main` finished | WORKFLOW: request deployment only after CI on the merge commit is green |
| Persian chapter numbers were present as `n فصل`, but the map skipped 19 of 24 chapters and put front matter in chapter 1 | recognise the standalone Persian heading and pin each chapter to its first occurrence; test the boundary and front matter |
| Burket 12e chapter 24 began after a long local contents, so its opening was missed and pages 629–641 landed in chapter 25 | scan the page for a standalone chapter label followed by its title, and use the opening page as a hard boundary; test a deep opening |
| A roman-numbered Little & Falace summary mentioned chapter 22 and falsely looked like its opening | ignore roman-numbered front matter as an opening; test that summary pages cannot move a chapter boundary |
| A page map contained every chapter but put some opening pages in the previous chapter | use the book's complete, ordered PDF chapter bookmarks where available; keep previous editions' maps unless their boundaries are separately reviewed |
| Persian evidence was reduced to punctuation by the English-only quote normaliser | preserve Persian letters in the same page-and-quote check; test both a real and an absent Persian phrase |

## 9. Handing a year to another agent

Give the agent this document, the year, an authorized server session and
access to the verified PDF/text sources and the protected server research
workspace (`/srv/fanoos/shared/research`), not to the owner's laptop.
Its deliverables:

1. the decisions files for the year, and a clean `apply_classification.py`
   run (no rejections);
2. the dry-run numbers, the verified backup, the import and publish output;
3. a short report: how many questions per subject have a chapter, which are
   undecided and why, which `override_human` were used and why;
4. the 06 table row, in a pull request.

It must not edit the catalog, the chapter map, the texts or the scripts to
make a decision pass, must not lower a confidence threshold, and must not
import a sitting whose dry run changes questions. A decision that cannot
pass is reported, with the reason.
