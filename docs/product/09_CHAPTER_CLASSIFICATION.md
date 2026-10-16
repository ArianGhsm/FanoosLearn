# Chapter classification

## Outside-scope design location (owner decision 2026-10-16)

If the exact page in the verified official-edition PDF explains the official
answer but its chapter is outside the announced syllabus, preserve the true
chapter and page. Set `official_scope_status=outside_scope_exam_question` and
write a clear Persian note stating that the design location was outside the
official exam scope. This flag does not expand the syllabus. The whole-year
publication gate accepts it only with the same physical PDF page and SHA-256
requirements as an in-scope source.

**Owner decision 2026-10-13:** AI agents perform evidence-backed chapter
classification through the validators and audited publication workflow;
human review is not a prerequisite. Confidence below 0.85 remains pending.
Earlier human-origin citations are historical provenance, not automatic
authority over a better proven exact-edition PDF citation. Correction of an
existing citation still requires an explicit comparison and guarded receipt.

For an unchanged human-origin chapter citation whose exact physical PDF page
has not yet been recorded, use
`scripts/references/repair_verified_human_source_pages.php`. It accepts only
an exact official in-scope original PDF page with page-local evidence and
question, option, key, anchor, source, and review fingerprints. Its sole DB
updates are `pdf_page` and `pdf_sha256`; it preserves `origin`, chapter,
edition, printed-page fields, anchor text, and every human review field. A
missing printed label remains null. A draft/voided human source can use this
operator only with an audit that pins both the draft and voided states; it
never creates a key or changes scoreability. AI-origin voided sources retain
their separate guarded workflow. Persian page-local quote checks ignore only
Unicode bidi display controls; they retain visible letters and punctuation.

> **1398 reference-list update (2026-10-10):** The later four-page, owner-supplied complete notice adds official reference-validity scopes for oral pathology, prosthodontics, community dentistry and dental materials (six additional references; 19 total across 12 clinical subjects). The earlier “1398 syllabus missing” remarks below are historical checkpoints and are superseded **only for source-list validity**, not for unverified question citations. See [1398 complete notice](../ops/RESIDENCY_1398_COMPLETE_NOTICE_20261010.md).\n\n
How a question gets its source: which official reference, which chapter,
which page. The procedure is the same for every exam type the catalog
carries (دستیاری, بورد, ارتقا …), every year, and every agent or person who
does the work. It is written so that another agent (Codex or any other) can
be handed a year and this document and produce the same kind of result,
without breaking what is already on the site.

Keep the two PDFs distinct: the official exam sitting PDF (when available)
is evidence for the question's wording and figures; the exact official
reference-book PDF is evidence for its chapter, page and source. Page lookup may inspect one requested PDF page in memory; no text version,
OCR export or search index is created.

The governing rules are PROJECT_PRINCIPLES decisions 6–7 and the
**PDF-only reference policy**: the exact verified server PDF is the source.
Search, chapter mapping and validation inspect requested PDF pages in memory;
no text version, OCR export or index is created. **No laptop is required.**

Everything here was learned on 1398–1405 (2026-10-08). §8 lists the mistakes
that were made and what now stops each of them.

### Retrospective exception for exam years before 1396 (owner decision 2026-10-11)

For exam years **1395 and earlier**, use the references published for
**1397 onward**, in ascending year order, when their original verified server
PDFs and complete chapter maps are available. This is a designated
`retrospective_proxy_1397_plus` classification; its reference year must be
recorded separately from the exam year and never described as the official
reference list of the original sitting. It does not affect years **1396+**.

A retrospective decision requires exactly the same **book edition + chapter
+ verified original PDF page + PDF SHA-256 + short exact-page quotation**
as a normal decision. Do **not** fill `catalog.validity` for 1391–1395
with invented historical `official=true` records, disable exact-source
scope checks, or publish chapter-only sources. If a guarded importer does not
support the retrospective provenance, keep the proposal in research pending
an audited versioned publisher implementation; do not bypass it through
raw SQL or full-sitting imports. See
`docs/ops/PRE_1396_RETROSPECTIVE_CLASSIFICATION_20261011.md`.

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

### Mandatory chapter + page pair (owner, 2026-10-11)

New classification is **atomic**: no chapter assignment is accepted unless
the same record contains the exact edition's verified `pdf_page` and
`pdf_sha256`, and the short evidence actually supports the decision on
that PDF page, inside the official syllabus scope. The printed page is useful
for display but cannot replace `pdf_page` or its file SHA-256. For an
unlocated or unreadable page, keep **both** the proposed chapter and page
pending; do not emit a chapter-only source or treat it as complete.
Historical rows missing page/hash are backlog, not newly completed work;
preserve their original attribution pending an audited source-only repair.
The policy-final residency English exemption remains source-free (§3A).

For all new progress reports, use one same-row predicate:
`node_id IS NOT NULL AND pdf_page IS NOT NULL AND
pdf_sha256 IS NOT NULL`. Raw source counts and legacy printed/any-page
counts can be shown for diagnostics, clearly marked incomplete. An audit
must also independently confirm PDF hash, chapter boundaries, syllabus
scope and page evidence before calling the row scientifically verified.

## 2. What the work needs

| What | Where |
|---|---|
| The official reference list per exam type, year and subject, with scope | `data/bank/catalog.json` → `validity` |
| Each edition's chapter list | `data/bank/catalog.json` / `data/bank/reference-tocs.json` |
| Exact edition status and historical nearest-edition notes | `data/bank/reference-pdfs.json`; live PDF eligibility always comes from the server inventory |
| The canonical source and page reader | current approved, verified private PDF object; `scripts/references/verified_reference_pdf.py` reads one page at a time and writes no PDF text |
| Which chapter every page is in | `data/bank/reference-chapter-pages.json` |
| The questions, as they are on the site | the year's sitting file (§4) |
| Decisions | protected server `/srv/fanoos/shared/research/classification/decisions/<year>-<subject>.json` (must be durably backed up) |
| Server access for the import | authorized access to the IranServer production host (direct authenticated operator/SSH access or SentinelX when available); see `docs/ops/SERVER.md` |

**Preflight on the server:** run the private reference-library inventory;
check that the exact official edition has one approved PDF and authenticated
read access. The verifier checks the object and can inspect pages one at a time,
retaining only a readability count. It writes no page text, OCR output, search
index or provenance sidecar. Do not copy the PDF or create a text export.

```sh
sudo -u fanoosupd python3 -B scripts/references/verify_reference_pdfs.py \
    --only <edition> --check-readable
sudo -u fanoosupd python3 -B scripts/references/build_chapter_pages.py \
    --only <edition>
```

A low readable-page ratio, missing exact PDF or unverified page map leaves the
edition pending. Inspect the original PDF visually where needed; do not use a
separately collected text file or OCR output.

A new book means: the live inventory verifies the exact PDF, its
`reference-pdfs.json` entry requires `verified-server-pdf`, and its chapter map
is built directly from that PDF. The map records page ranges and PDF SHA-256,
not page text. A full rebuild requires reviewing changed boundaries in existing
editions before replacing their maps. Check that every chapter has a run of
pages.

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
`reference-pdfs.json`. The procedure does not change.

When the PDF has an ordered bookmark for every chapter, the builder reads
that PDF outline directly. Otherwise, it reads one page at a time and discards
the page text after detecting chapter openings; missing chapters remain visible
in the report. If opening labels omit chapter numbers, recorded starts in
`reference-pdfs.json` may be transcribed from the printed contents with source
PDF pages documented. The builder requires one strictly ordered start for every
catalog chapter. Proffit 5e uses this rule (contents on PDF pages 18–20).


### Private post-validator audit (server-first)

For multiple read-only study-only batches, run
`python3 -B scripts/references/audit_private_study_batches.py` with one
`--batch=YEAR:subject:stem` per batch, `--local` pointed at the protected
server research workspace, PDF storage/database access configured, and `--out`
under its `classification/reports/` folder. It independently re-reads the cited
PDF pages and checks question identity, answer integrity, chapter, page,
short evidence quote, origin and confidence. It does not import or publish.

See `docs/ops/RESIDENCY_CLASSIFICATION_1398_1405.md` (and the per-batch records it points to in Git history) for the
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

One scientific *research* batch is 30–40 questions from the same residency year (the final research batch may be smaller). Complete all subjects of that year before any public exam-version publication.
**This is the scientific research unit, not permission for one-question-at-a-time
site writes.** Site publication is scheduled by **one or two entire subjects**
as defined in §7 and `docs/PROJECT_PRINCIPLES.md`. Each question still needs
its own independently verified chapter **and original physical PDF page**.


**Step 1 — open the batch.**

Create one protected, unique task directory for temporary query JSON and
search output. Never write PDF page text or an index there:

```sh
sudo -u fanoosupd install -d -m 0700 /srv/fanoos/shared/research/tmp/<operation-id>
```

```sh
sudo -u fanoosupd python3 -B scripts/references/classification_batch.py --sitting=<sitting.json> \
    --subject=<subject> --out="/srv/fanoos/shared/research/tmp/<operation-id>/queries.json"
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
sudo -u fanoosupd python3 -B scripts/references/find_in_books.py "/srv/fanoos/shared/research/tmp/<operation-id>/queries.json" --top 3
```

For each question, search reads the current verified PDF page by page and
prints only selected page numbers and short candidate quotes. The query file
and any redirected search output are temporary and are removed after the
operation. No PDF text file or index is written.

**Step 4 — decide from the PDF page.** Open each candidate page in the
original PDF and verify that it states the fact making the correct option
correct (or the incorrect options incorrect), rather than merely mentioning
the topic. Check column order, Persian shaping and figures visually. A search
hit or short quote alone is not a decision. If page text is unreadable, review
the PDF visually; do not export OCR or build a text copy. If repeated searches
do not locate support, leave the question undecided (§5).

- The fact is in two chapters: take the one that teaches it, not the one that
  refers to it in passing.
- The page is outside the year's announced `scope`: look for the same fact
  inside the scope. If the only support is outside, leave the question
  undecided pending authoritative syllabus evidence. Lower confidence does not make an
  excluded chapter eligible. For a partially included chapter, verify the
  official printed-page or section limit on the original PDF page before
  publication; a chapter-list check alone cannot validate that limit.
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

- **Every accepted source carries the exact book page** (migration 0043):
  `pdf_page`, the verified file's `pdf_sha256`, and `printed_page` when the
  label was read on that page; `apply_classification.py` writes all three for
  the cited edition's own PDF. A descriptive answer is later written from
  that page, so a source without `pdf_page` is incomplete.
- `page` in a decision and its audit receipt is the PDF page number. A printed
  page label may be saved for display only after it is checked on that exact
  PDF page and neighboring pages; retain the PDF page number and source PDF
  SHA-256 in the audit.
- `evidence` is copied **word for word from the original PDF page**, using
  the search output's `quote:` line only as a candidate. Several fragments are
  joined with `...`; every fragment has at least four words. Never a
  paraphrase, a translation or a summary.
  Words broken by the PDF (`efective`, `he` for "The", `dierent`, `àap`) are
  copied as printed; avoid fragments with symbols (`≤`, `•`, `‐`, `#`,
  superscript reference numbers) when a plain run of words is available.
- `confidence`: 0.95+ when the quote states the answer outright; 0.85–0.94
  when it states it but the wording differs or two chapters teach it.
  Below 0.85, keep the decision pending and search again; there is no
  human-review queue or confidence-based exception to the PDF evidence gate.
- **New batches use the exact official edition only.** For auditing earlier
  nearest-edition assignments, the `--include-nearest` batch flag and
  `--allow-nearest` apply flag must be explicitly supplied; the chapter
  carry-over logic and `official_chapter` are historical (§6). Do not
  silently use these exceptions for new decisions.

**Step 6 — check and write.**

The validator rejects a chapter outside an explicit `scope_chapters` list.
If the official scope describes selected or excluded chapters but has no
structured chapter list, the decision stays pending until the catalog is
corrected from the official notice. The full sitting importer applies the
same chapter-level gate; partial page and section limits require an
independent check against the official notice and original PDF page.

```sh
sudo -u fanoosupd python3 -B scripts/references/apply_classification.py --sitting=<sitting.json> \
    --decisions=/srv/fanoos/shared/research/classification/decisions/ \
    --out=/srv/fanoos/shared/research/classification/sittings/<sitting>.json
```

Every rejection is listed with its reason; fix the decision and run again —
never the catalog, chapter map or scripts. With no
rejections it writes the sitting with `sources` (reference, chapter node,
verified page label, the quote as anchor, `origin: ai`, confidence). The audit
receipt records the exact PDF page separately from any printed label.
Run the private study audit against the original PDF:

```sh
sudo -u fanoosupd python3 -B scripts/references/audit_private_study_batches.py \
    --local=/srv/fanoos/shared/research \
    --batch=<year>:<subject>:<stem> \
    --out=/srv/fanoos/shared/research/classification/reports/<audit>.json
```

The audit re-reads cited pages and records the PDF SHA-256 and page count; it
creates no PDF text file. After the validated sitting and durable audit receipt
are recorded, remove the exact operation directory so its query files and
search output do not accumulate. Keep the sitting, decisions and concise audit
receipt. One-off scripts and temporary outputs are removed when their
operation ends; reusable scripts stay versioned in the repository.

After confirming this is the single operation directory, remove only that
exact directory:

```sh
sudo -u fanoosupd rm -rf -- /srv/fanoos/shared/research/tmp/<operation-id>
```

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
- **Earlier source attribution is preserved in the audit.** The validator's
  legacy `override_human` field is an evidence rationale, not a request for
  human approval. If the AI proposes a different chapter from a prior
  human-origin source, it must compare both original PDF pages and record
  why the new exact-edition passage better supports the official answer.
  An unchanged chapter keeps the existing citation. A correction may reach
  production only through a separately guarded source-only operator with a
  before/after receipt; never use a full-sitting import to overwrite the
  prior source or invent `none` for a fact that remains unlocated.
- For an unchanged human-origin chapter citation, add its exact PDF page/hash
  only through `repair_verified_human_source_pages.php`. Keep its original
  human provenance, reviewer, chapter, edition, printed-page label, and anchor
  untouched. For a draft/voided human source, the audit also pins the draft
  and voided states; no key or scoreability changes are allowed. AI-origin
  voided entries use their separate guarded workflow.
- Questions whose **exact official edition** is unavailable, whose approved
PDF cannot be securely read, or whose direct-PDF chapter map is not verified
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

### Binding clarification: voided questions still require chapter AND PDF page

**Do not exclude voided/draft questions from *scientific citation coverage*.**
All non-English dentistry-residency questions, including officially
`voided` and excluded-from-scoring items, must have the approved
year-specific book chapter and exact original physical PDF page/SHA.
Voided questions keep their official voided answers (null correct
choice), original draft/scoring state, four original options and
unchanged prior attempts and frozen exams. Citation completion is not
the same as turning a question into a scored item.

For existing AI-origin voided/draft sources use the separate guarded
`repair_verified_voided_source_pages.php` after accepted private
original-book scientific audit, exact page and printed neighbor
checks, rollback preview, new verified full backup and unique receipt.
Never attempt to pass voided items into a writer that explicitly
requires `final/amended`; **do not alter the key** to work around it.
See `docs/ops/VOIDED_QUESTION_CHAPTER_PDF_SOURCE_20261016.md`.

### Mandatory whole-YEAR residency publication gate (owner superseding decision 2026-10-16)

**Research batches: 30–40 questions; public exam publication: ONE ENTIRE
RESIDENCY YEAR.** This replaces the older one/two-subject publication
policy. Independently search and verify the exact-year approved
reference and **chapter PLUS original physical PDF page** for all
subjects, in private 30–40-question batches (last batch may be shorter).
A subject, a completed study file, a protected per-plan apply receipt
or a merged PR is **not** a license to publish a partial residency year.

The only allowed public exam publication trigger is the **full
sitting's declared `question_count`** matching the actual imported
question inventory and *every* book-based question possessing at
least one original-year official in-scope chapter/source with same-row
positive `pdf_page` and `pdf_sha256`, independently verified against
the original approved PDF. English is exempt from a book citation
only with valid official final/amended choice. No voided official
choice can be silently changed, and image-dependent, insufficiently
audited or out-of-syllabus references are blocking holds.

This is enforced inside **`BankPublisher::publish()`** by
`ResidencyYearPublicationGate`, BEFORE creating an assessment or
version, on both first publication and republishing. It returns
`residency_year_classification_incomplete` (HTTP 422) on a failed
year-level inventory/source check. This is a **structural DB gate**,
not a replacement for the independent science/official-book audit.
Previously published frozen exam versions remain live and immutable
during research; do not withdraw them merely because a future
revision is incomplete.

After all 30–40-question research batches are verified, complete
source-only production repairs only with the existing guarded operators
within their transaction limits and fresh full verified backup.
Independent whole-year SQL readback, official-scope and exact-PDF
science audit must pass. Then authorize, review and **publish one
whole-year frozen exam version**, verify the new assessment and
historical attempts, CI/merge/updater deploy and `/health`.
If even one question is pending, do not publish a new version.

Full operational contract:
`docs/ops/RESIDENCY_WHOLE_YEAR_PUBLICATION_20261016.md`.
The older `docs/ops/SUBJECT_FIRST_SOURCE_PUBLICATION_20261016.md`
describes **internal guarded chunking only**; its one/two-subject
release framing is superseded and MUST NOT be followed for residency
exam publication.

**New source-only workflow for existing published, unsourced questions
(2026-10-10):** Follow
`docs/ops/SOURCE_ONLY_PUBLICATION_20261010.md`. The vetted
`scripts/references/import_verified_sources.php` inserts **only**
`bank_question_sources`, with exact-edition/scope, current live
question+choice+answer matching, all-or-nothing rollback-only preview,
verified full backup, and private post-commit receipt. Stage verified 30–40-question research batches privately. After ALL year subjects are scientifically complete, use guarded source-only transactions with independent receipts and whole-year readback, then publish the frozen exam ONCE for the completed year. A study-only JSON file must **never** be passed to the full
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
| An AI pass overwrote 49 earlier attributed chapters on 1404 without evidence | `override_human` retains the explicit evidence rationale; source-only correction needs an auditable before/after receipt |
| `none` used for "not found", removing an existing source | §5: not found stays undecided |
| Deploy requested before CI on `main` finished | WORKFLOW: request deployment only after CI on the merge commit is green |
| Persian chapter numbers were present as `n فصل`, but the map skipped 19 of 24 chapters and put front matter in chapter 1 | recognise the standalone Persian heading and pin each chapter to its first occurrence; test the boundary and front matter |
| Burket 12e chapter 24 began after a long local contents, so its opening was missed and pages 629–641 landed in chapter 25 | scan the page for a standalone chapter label followed by its title, and use the opening page as a hard boundary; test a deep opening |
| A roman-numbered Little & Falace summary mentioned chapter 22 and falsely looked like its opening | ignore roman-numbered front matter as an opening; test that summary pages cannot move a chapter boundary |
| A page map contained every chapter but put some opening pages in the previous chapter | use the book's complete, ordered PDF chapter bookmarks where available; keep previous editions' maps unless their boundaries are separately reviewed |
| Persian evidence was reduced to punctuation by the English-only quote normaliser | preserve Persian letters in the same page-and-quote check; test both a real and an absent Persian phrase |

## 9. Handing a year to another agent

Give the agent this document, the year, an authorized server session and
read access to the exact verified PDF through the approved extraction tool,
plus the protected server research workspace
(`/srv/fanoos/shared/research`), not to the owner's laptop. The page reader opens the current approved PDF directly and writes no text
copy or index. Only the temporary query file and any redirected search output
need cleanup after the operation; preserve decisions, short page quotes, PDF
SHA-256 and durable audit receipts.
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
