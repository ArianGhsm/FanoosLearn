# Source-only chapter and physical-PDF citation for voided residency questions

**Owner clarification: 2026-10-16.** A question that is officially
`voided` is still a dental question designed from its reference and
MUST have the official-year reference, chapter and exact physical
original-PDF page assigned. It MUST NOT receive a fabricated final
answer or become scored, shown as a new gradeable question, or have its
`draft` status changed.

## Two independent dimensions

1. **Academic citation:** every non-English book-based question,
   including voided, must have an approved reference edition, official
   year-allowed chapter node, correct printed book page, exact positive
   original physical PDF page and approved source SHA-256, supported by
   an original-book page-local scientific excerpt.
2. **Scoring:** an officially voided answer keeps `status=voided`,
   `choice_position=NULL` and the original `bank_questions.status`.
   Such questions stay excluded from the existing scoring/publication
   projection; their source classification does not authorize them to
   count as scored questions.

**A completed year requires source classification of voided items too.**
Research proceeds in private groups of 30–40 questions. A frozen exam
version of a year may be first-published or republished only when the
entire year's mandatory source locations are verified and all applicable
review/backup/deployment checks pass. An older published version
remains immutable while classifications are underway.

## New guarded source-only operator

The explicit operator
`scripts/references/repair_verified_voided_source_pages.php`
works **only for an existing, unreviewed AI source** of an officially
voided, non-scored `draft` residency question. It only modifies:

- `bank_question_sources.printed_page` — when an old placeholder
  differs from the actual original-book printed label;
- `bank_question_sources.pdf_page` — one-based *physical PDF page*;
- `bank_question_sources.pdf_sha256` — the exact approved server PDF.

No modification to `bank_questions`, `bank_question_choices`,
`bank_official_answers`, reviewed sources, assessment versions,
attempts, grading, chapter node or source edition is permitted. The
source's existing chapter and edition must already be official for
the exact year; chapter corrections and no-source inserts require
**different separately approved operators**.

Protected plan format: `fanoos.verified-voided-source-page-repair/1`.
The plan has `exam_type: residency`, `year`, `subject`, 1–12
`entries`, `audit_path` and `audit_sha256`. The independent
SHA-pinned audit format is
`fanoos.voided-original-pdf-citation-audit/1`: year, subject,
`research_only: true`, and `records` with each independently
scientifically accepted original chapter/PDF evidence. Each record
must have `question_status: draft`,
`official_answer_status: voided`, and
`scientifically_accepted: true`.

Each plan entry binds original source ID, old legacy and printed
labels, anchor SHA, immutable original stem SHA, ordered original
four-choice SHA, latest official answer SHA, original source edition,
official chapter, corrected printed label, physical PDF page,
original PDF SHA, and one exact original-page quote. All identifying
values must agree with the independent research audit. The writer
rechecks official chapter scope and approved PDF chapter boundaries,
corroborates corrected book printed pagination against original
neighboring pages, and looks up the quoted passage on that exact page
of the SHA-verified server PDF.

**Preview:** the writer performs the same protected source-only SQL
UPDATE in a transaction and rolls it back. **Apply:** only the
`fanoosupd` service account, with the required independently verified
fresh full backup (no older than four hours) and an unused private
receipt path, may commit. Each successful result records original
official `voided` and `draft` states and emits a unique protected
receipt. Verify all source fields and content hashes with independent
SQL after any apply. Stop after a rejection; never change a voided key
to get past an existing final-key-only writer.

No unaudited private source plan should be generated automatically
from a mere keyword search. First independently review answer-bearing
original-book text and original question meaning.

## Residency 1405 scientific candidates

- **Q133**, oral surgery: Hupp 7e **ch9**, physical PDF **168**,
  original printed page **154**, book PDF SHA
  `35e3c37fec67a831a4464205c1817cc1e51c0a9f8a0bd8660a1881ecd2f10a83`.
  Passage on the original page describes mesio-buccal, distal-buccal
  and finally palatal maxillary molar root removal. Q133 remains
  **voided/draft**.
- **Q174**, pediatric dentistry: McDonald/Avery 11e **ch4**,
  physical PDF **104**, original printed page **91**, exact approved
  book PDF SHA
  `a344465f89ff6711099a8bee1878e83236c9778340447ac7491bbc27a89fb629`.
  The original page discusses odontoma development, peg teeth and
  bell-stage differentiation. Q174 remains **voided/draft**.

The original printed pages were independently corroborated on the
approved server book pages and nearby labels; this does NOT constitute
a production apply or a full independent accepted audit for either
candidate. Produce signed/SHA-pinned independent review artifacts
and a protected plan only when the scientific answer-bearing link is
accepted. A current status without the PDF fields is **not closed**.

## Deployment is separate

This feature requires PR review, all CI, authorized upstream merge,
and release through the platform's **official audited updater**.
There is no generic email-address restriction on deployment. The
authorization checks (including `deployment.manage`) and the
platform-level tool-safety decision are not bypassable through this
operator. A GitHub code merge is not a production update.

Verification after authorized deployment includes the live code SHA,
guarded rollback-only source preview, backup and independent readback,
invariant original question/choice/voided-key records, prior frozen
exam history, other question sources, login health and site `/health`.
