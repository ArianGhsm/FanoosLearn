# 11 — Study content for residency 1406: lessons, explanations, review

**Status: proposed plan.** Owner decision 2026-11-11 (1405/08/20); this
document turns it into phases. Nothing here is built yet unless a row says so.

## 1. The decision

1. FANOOS builds its **review system, lessons (درسنامه) and per-question
   explanations (پاسخ تشریحی)** for the **upcoming dental residency exam:
   residency 1406 (دوره ۴۱)**.
2. Content is written **only from the official editions announced for 1406**,
   and only inside each edition's announced scope. Older editions and other
   references are out of scope for this work. "Latest edition" means the
   edition Sanjesh announced, not the newest one in print (for example
   Proffit 6e, not 7e).
3. The **model is MedoFast** (medofast.ir): its reading flow, page layout,
   ordering by exam frequency and writing style. FANOOS takes the **style
   only**. MedoFast is a medical/basic-sciences product; FANOOS is dental, and
   every word of FANOOS content is written from the dental reference PDFs. No
   MedoFast text, table, image or mind map is copied.
4. Today **no question has an explanation and no lesson exists**. The only
   content work done so far is chapter-and-page classification, and it is
   incomplete (§4).

## 2. Scope: the 19 official editions for 1406

Source of truth: `data/bank/catalog.json` → `validity[]` with
`exam_type=residency`, `year=1406` (from Sanjesh's «منابع آزمون دستیاری
دندانپزشکی دوره 41 - سال 1406»). Each entry carries the announced `scope` and
`scope_chapters`; content never goes outside them.

Server read-back 2026-11-11 (read-only; `verified_reference_pdf.py` per
edition: storage confinement, size, PDF signature, page count, SHA-256, and
`--require-map`; one page read in memory as a smoke test, text discarded):

| Subject | 1406 edition(s) | Verified PDF | Chapter map bound to that PDF |
|---|---|---|---|
| community-dentistry | `national-oral-health@1394` | ✅ | ✅ |
| dental-materials | `craig-restorative-materials@14e`, `van-noort-materials@5e` | ✅ | ✅ |
| endodontics | `torabinejad-endodontics@6e` | ✅ | ✅ |
| operative-dentistry | `sturdevant-operative@7e`, `summit-operative@4e` | ✅ | ✅ |
| oral-medicine | `burket-oral-medicine@13e`, `little-falace-dental-management@10e` | ✅ | ✅ |
| oral-pathology | `neville-oral-pathology@5e` | ✅ | ✅ |
| oral-radiology | `white-pharoah-radiology@8e` | ✅ | ✅ |
| oral-surgery | `hupp-oral-surgery@7e`, `malamed-local-anesthesia@7e` | ✅ | ✅ |
| orthodontics | `proffit-orthodontics@6e` | ✅ | ✅ |
| pediatric-dentistry | `mcdonald-avery-pediatric@11e` | ✅ | ✅ |
| periodontics | `carranza-periodontology@14e` | ✅ | ✅ |
| prosthodontics | `mccracken-rpd@13e`, `rosenstiel-fixed@6e`, `shillingburg-fixed@4e`, `zarb-edentulous@13e` | ✅ | ✅ |

All 19 are ready as sources. Scope changes against 1405 that matter for
content: Craig 14e narrowed to chapters 4, 10, 12, 14; Van Noort 5e gained
2.3–2.5; Neville 5e now includes chapter 7 and excludes 17.

Re-run this check before each production batch (§7); a missing or changed
PDF stops that edition's work and is recorded as a pending dependency.

## 3. What MedoFast does, and the FANOOS equivalent

Observed 2026-11-11 from a signed-in MedoFast account (structure and layout
only). ✅ exists on fanooslearn.ir · 🟡 partly · ❌ missing.

### 3.1 Lessons (میکرودرسنامه)

| MedoFast pattern | FANOOS today | Gap |
|---|---|---|
| Lesson index per subject: one row per topic, **ordered by how often the exam asks it**, with the reader's **progress %** per row | 🟡 `/app/lessons` lists resources by kind; no subject/topic ordering, no progress | order by question count of the chapter in 1406 scope; progress per lesson |
| A lesson is **paged** (≈10–15 short pages): progress bar, previous/next, «go to page N», resume where you stopped | ❌ one long Markdown page | paged reader: one `##` section = one page |
| Page 1 is a **clickable table of contents** (sub-topic → page) | ❌ | generated from the `##` headings |
| Each page: sub-topic heading, smaller sub-headings, **short bullets**, each bullet starting with a brand mark | 🟡 `##` and `-` render; no bullet mark, no sub-heading style | reader styling |
| **Colour-coded bold keywords**: one colour for the term being defined, one for the key fact, one or two for secondary facts | ❌ the renderer allows plain `**bold**` only | a small, safe markup for 3–4 semantic emphasis kinds (§5.1) |
| Last page: **summary table** (topic → key points) | 🟡 Markdown tables exist in visuals, not in lessons | lesson table support; required final «خلاصه و جمع‌بندی» page |
| Spoken, friendly, exam-focused tone | ❌ no content | house style guide (§5) |
| Report a mistake on the lesson, watermark, dark mode | 🟡 watermark and dark mode ✅; lesson report ❌ | reuse the question report flow for lessons |
| Three lesson formats: text micro-lesson, capsule, mind map with a quiz | 🟡 capsule and mind map exist as `bank_visuals` (10_VISUALS.md), no content; no quiz | link a lesson, its visuals and its chapter's questions |

### 3.2 Subject page and question page

| MedoFast pattern | FANOOS today |
|---|---|
| Subject page: questions per exam, questions in bank, years covered, a short friendly note on why the subject matters, «my performance in this subject» | 🟡 counts ✅ (07 #5); note and per-subject performance link ❌ |
| Three study modes: comprehensive by year, by topic (most-asked first, with progress), most-asked micro-topics | ✅ (07 #3, #4, #6); per-topic progress 🟡 |
| Question header: N of M, exam and date, subject, topic, difficulty, % correct, your history | ✅ (07 #11–#15) |
| Explanation shown after «submit and show answer» | ✅ structure exists (06 §4: answer, short, «از رفرنس», «چرا بقیه نه», «نکته‌ی آزمون», «دام رایج», source line) — **no content** |

### 3.3 Review

Spaced review of wrong answers (1/3/7/14/30 days), bookmarks, notes,
highlights → flashcards, timed exams and report cards already exist
(07 #18–#38). The gap is linking them to lessons: «review this lesson's
questions», and a lesson's flashcards/highlights feeding the same schedule.

## 4. Prerequisite: questions tied to a 1406 chapter and page

An explanation is written from the page that answers the question, and a
lesson's question list comes from the chapter. Both need the question's
source on a **1406 edition**: `node_id` + `pdf_page` + `pdf_sha256` in one
`bank_question_sources` row (09_CHAPTER_CLASSIFICATION.md).

Last full snapshot (BANK_CLASSIFICATION_PAGE_BACKLOG_20261011.md): 10,295
questions, 1,047 recorded complete. Older sittings are classified against the
edition valid in **their** year (for example Neville 4e for 1399). For 1406
content each such question also needs:

- the equivalent 1406 node through `bank_edition_mappings`
  (equivalent / partial / removed), and the 1406 page;
- a `bank_question_currency` row against the 1406 edition (current,
  changed_in_newer, outdated, contradicted, removed_from_syllabus). A
  question whose answer changed or whose topic left the 1406 scope gets a
  labelled note, not a silent explanation (principles §1.4).

**Phase 0 result** (2026-11-11, read-only;
`docs/ops/RESIDENCY_1406_CONTENT_COVERAGE_20261111.md`): of 2,963 residency
questions with a book reference, **1,826 have a verified page on a 1406
edition inside its 1406 scope** and can be explained now; 707 are classified
only on an older edition of a 1406 book (mostly oral pathology, periodontics,
oral medicine, endodontics) and need a 1406 page; 78 sit in chapters outside
the 1406 scope; 351 have no usable source. `bank_edition_mappings`,
`bank_explanations` and `bank_visuals` are empty.

## 5. House style (adapted from MedoFast, written for FANOOS)

### 5.1 Lessons

- One lesson per **chapter (or announced chapter part) in 1406 scope**.
  Long chapters split by their book sections; a page fits one phone screen
  and a half.
- Page 1: table of contents. Pages 2…n−1: one sub-topic per page. Last page:
  «خلاصه و جمع‌بندی» table (sub-topic → 2–4 key points).
- Bullets, not paragraphs. One fact per bullet. Numbers, classifications,
  "most common", "first choice", "contraindicated", "except" are always
  emphasised.
- Emphasis kinds (rendered as colour + bold, with text labels for screen
  readers and colour-blind mode): **term** (a defined term), **key** (the fact
  the exam asks), **note** (secondary fact), **warn** (exception / common
  trap). Proposed syntax, rendered from text only, never HTML:
  `{{term:…}}`, `{{key:…}}`, `{{note:…}}`, `{{warn:…}}`. Final syntax is
  decided with the renderer change (Phase 1) and added to the contract.
- Persian prose, English for terms that are English in the book (with the
  Persian on first use). Friendly and direct, as an experienced senior would
  explain it; no filler.
- Every lesson records `from`: edition, PDF pages, PDF SHA-256; links the
  chapter node, concepts and the questions it covers. Nothing outside the
  1406 scope of that edition.
- Original wording only. No sentence, table or figure is reproduced from the
  book beyond a short term or value needed to state a fact.

### 5.2 Explanations (one per question) — template, 2026-11-12

Settled from 36 MedoFast explanations across four subjects (structure only:
length, block order, option tags, emphasis, images; no text kept) and the
FANOOS rules above. What MedoFast almost never has and FANOOS always has:
the source line (1 of 36 cited anything).

**Length follows observed difficulty** (MedoFast: easy ≈ 86 words, medium ≈
101, hard ≈ 184): ≥ 75 % correct 50–90 words; 50–74 % 80–120; < 50 % or new
120–200; a clinical card up to 200. A voided question gets one line: that it
was voided, and why when known.

**Order**

1. «پاسخ: گزینه‌ی …» (every accepted option when `also_correct`).
2. If `currency` is not `current` for 1406: that notice first.
3. Core: 2–6 bullets, one fact each, paraphrased from the 1406 page. The
   bullet that proves the answer ends «(تأیید گزینه‌ی N)»; a fact that rules
   an option out ends «(رد گزینه‌ی N)». **Every option gets a tag**, inline
   when facts and options line up, otherwise in a closing
   «بررسی سایر گزینه‌ها:» block (one line per option).
4. Optional «نکته‌ی آزمون» / «دام رایج», only when real (about 1 in 8).
5. Source line: `Book edition · فصل N · ص printed` (PDF page and SHA-256
   stay in `explanation.from`), then links to the chapter's lesson / mind map.

**By question type**

| Type | Shape |
|---|---|
| recall / concept | core bullets; one «پس / چون» mechanism sentence when it helps memory |
| negative («به جز»، «نادرست») | first line «سؤال دنبال گزینه‌ی نادرست است»; true options «(درست — رد گزینه‌ی N)», the answer «(نادرست — تأیید گزینه‌ی N)» |
| multi-statement | each statement ✓ / ✗ with a reason, then the count |
| clinical scenario | card: «یافته‌ی کلیدی» → «تشخیص / اقدام» → «چرا بقیه نه» |
| comparison / classification | a small table (`| a | b |`) |
| image | first where to look, then the reading |

**Writing**: semi-colloquial Persian to «تو», short sentences; English terms
as in the book with Persian on first use; 2–6 emphases, at most one per
bullet (`{{key:…}}` the asked fact, `{{term:…}}` a term, `{{warn:…}}` an
exception; 12_LESSON_FORMAT.md). Every claim is on the cited page or on a
page added to `from`; reasons for wrong options that come from another page
cite it too, or the line is left out.

Fields: `short` = answer line + proving bullet; `reference` = core bullets;
`why_wrong` = per-option lines; `tip`, `trap`; `location`, `from`;
`confidence` ≥ 0.85 to publish.

## 6. Phases

Each phase is its own branch and PR, with tests and docs, merged on green CI
and deployed through the updater (principles §5).

| Phase | Deliverable | Done when |
|---|---|---|
| **0. Measure** ✅ 2026-11-11 | Read-only 1406 coverage report (§4) and the PDF/map re-check (§2), recorded as an ops doc with counts only | done: `docs/ops/RESIDENCY_1406_CONTENT_COVERAGE_20261111.md` |
| **1. Lesson reader** | Paged reader with TOC, progress bar, resume, go-to-page, final summary table; emphasis markup in `markdown.js` and the lesson contract; lesson ↔ edition/chapter/concepts/questions links and `from`; report on a lesson | tests for the renderer and the reader; a sample lesson renders on phone and desktop, light and dark |
| **2. Lesson index** | Per subject: chapters in 1406 scope ordered by question count, per-user progress, «test this chapter» (existing builder) | index matches Phase 0 counts |
| **3. Explanation pipeline** | Batch tool: select questions with a verified 1406 page → read the cited page(s) in memory → draft explanation → validators (official key, cited page supports the claim, scope, confidence ≥ 0.85) → `draft` → import; reviewed rows never overwritten | a pilot batch for one chapter passes the validators and renders in the review |
| **4. Content: pilot** | One high-yield chapter end to end: lesson + its questions' explanations + capsule + mind map | owner approves style and accuracy |
| **5. Content: rollout** | Chapter by chapter, highest question count first, across the 19 editions; classification gaps (§4) filled first for each chapter | each chapter: lesson published, all in-scope questions explained or recorded pending with a reason |
| **6. Review links** | «Review this lesson's questions», lesson flashcards into the spaced-review schedule, progress on the subject page | review schedule includes lesson items |

## 7. Rules that apply to every batch

- Verify the edition's PDF SHA-256 and page count before the batch; read one
  page at a time in memory; never write page text, OCR or an index to disk
  (PDF_REFERENCE_SOURCE_POLICY_20261010.md, principles §4).
- Below 0.85 confidence, or without a page that supports the claim, the item
  stays pending.
- Private material (question text, book content) never enters this public
  repository; content is imported into the server bank with an audited,
  idempotent importer and a recorded receipt.
- Each batch has a handoff record: inputs, PDF hashes, counts, failures,
  pending items, next step.

## 8. Open questions for the owner

1. May AI-validated explanations and lessons publish without a human
   reviewer, as chapter classification does (principles §1.5), or does each
   need a reviewer's approval?
2. Which subject goes first in the pilot (Phase 4)? Default: the subject
   with the most 1406-scope questions from Phase 0.
3. Free or paid: are explanations and lessons part of a plan, or free like
   the bank?
4. Tone: as informal as MedoFast, or a little more formal?
