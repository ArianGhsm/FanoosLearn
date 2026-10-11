# Live chapter + verified PDF page backlog — 2026-10-11

**Production read-back** from `fanoos_prod` on the authorized FANOOS
server, at deployed GitHub main SHA `524ec2b25a8ce6325ab95b26b66e25bcb50f7140`.
Read-only audit; no production content or user data was altered.
This is a time-stamped snapshot, not a continuously refreshed dashboard.

**New owner rule (2026-10-11): chapter assignment and verified PDF
page assignment are indivisible.** A book-based question is recorded
complete only when **one and the same** `bank_question_sources` row
contains an official-edition `node_id`, `pdf_page` and `pdf_sha256`.
The page must be checked directly against the current approved exact
server PDF and official chapter scope before being called *verified*.
Presence of the three fields alone is `recorded_complete` and is
not proof of independent evidence audit. No chapter-only new
classification is allowed. Historical incomplete rows remain untouched
until individually reviewed. See `docs/PROJECT_PRINCIPLES.md` and
`docs/product/09_CHAPTER_CLASSIFICATION.md`.

## Site-wide totals

| Category | Questions |
|---|---:|
| All `bank_questions`, all statuses | 10,295 |
| Any chapter node (old broad definition) | 2,169 |
| Chapter + *any* legacy/printed/PDF page (diagnostic only) | 1,832 |
| Chapter + `pdf_page` + `pdf_sha256` (new recorded-complete definition) | **1,047** |
| Incomplete under new definition (before exemptions) | **9,248** |
| Residency English without book reference, official valid key | **239 exempt** |
| Incomplete after subtracting only this verified exception | **9,009** |

The 1,122 historically chaptered-but-incomplete questions comprise 337
without *any* page field and 785 with only legacy or printed-page fields
(or otherwise lacking the bound PDF page). They remain in the database.
The residency English exception was verified: 238 `final`, 1
`amended` with non-null choices. Other source-free exam sections
(e.g. national English/basic sciences) must be assessed by their
separate year-specific official scope before declaring them actionable
book-chapter backlog. Drafts and withdrawn questions are included in
these raw bank totals.

## Exam-by-exam backlog

`Missing` means `total - recorded_complete`, even for questions that
already have a bare chapter. The type/year/round values are bank sitting
identifiers, not all necessarily separate public exam dates. National
round 1 = Mordad, round 2 = Dey; for board/promotion the slot maps to
the specialty listed below.

### دستیاری (residency)

| Year | Sitting | Total | Chapter + PDF page | Missing |
|---|---:|---:|---:|---:|
| 1391 | 1 | 220 | 0 | 220 |
| 1392 | 1 | 240 | 0 | 240 |
| 1395 | 1 | 250 | 0 | 250 |
| 1396 | 1 | 250 | 0 | 250 |
| 1397 | 1 | 247 | 63 | 184 |
| 1398 | 1 | 247 | 89 | 158 |
| 1399 | 1 | 249 | 87 | 162 |
| 1400 | 1 | 250 | 56 | 194 |
| 1401 | 1 | 249 | 67 | 182 |
| 1402 | 1 | 250 | 75 | 175 |
| 1403 | 1 | 250 | 71 | 179 |
| 1404 | 1 | 250 | 48 | 202 |
| 1405 | 1 | 250 | 82 | 168 |
| **Total** | | **3202** | **638** | **2564** |

### ملی (national)

| Year | Round | Total | Chapter + PDF page | Missing |
|---|---:|---:|---:|---:|
| 1397 | 2 (Dey) | 240 | 0 | 240 |
| 1398 | 1 (Mordad) | 240 | 0 | 240 |
| 1399 | 2 (Dey) | 240 | 0 | 240 |
| 1400 | 1 (Mordad) | 240 | 0 | 240 |
| 1400 | 2 (Dey) | 183 | 0 | 183 |
| 1402 | 2 (Dey) | 240 | 0 | 240 |
| 1403 | 1 (Mordad) | 240 | 0 | 240 |
| 1403 | 2 (Dey) | 240 | 0 | 240 |
| 1404 | 1 (Mordad) | 210 | 61 | 149 |
| 1404 | 2 (Dey) | 210 | 83 | 127 |
| 1405 | 1 (Mordad) | 210 | 80 | 130 |
| **Total** | | **2493** | **224** | **2269** |

### ارتقا (promotion)

| Year | Specialty slot | Total | Chapter + PDF page | Missing |
|---|---:|---:|---:|---:|
| 1404 | 1 (اندودانتیکس) | 100 | 0 | 100 |
| 1404 | 2 (پریودانتیکس) | 100 | 0 | 100 |
| 1404 | 3 (پروتز) | 100 | 0 | 100 |
| 1404 | 4 (ترمیمی) | 100 | 0 | 100 |
| 1404 | 5 (جراحی دهان) | 100 | 0 | 100 |
| 1404 | 6 (بیماری‌های دهان) | 100 | 0 | 100 |
| 1404 | 7 (پاتولوژی دهان) | 100 | 0 | 100 |
| 1404 | 8 (رادیولوژی) | 100 | 0 | 100 |
| 1404 | 9 (ارتودنسی) | 100 | 0 | 100 |
| 1404 | 10 (دندان‌پزشکی کودکان) | 100 | 0 | 100 |
| 1405 | 1 (اندودانتیکس) | 100 | 0 | 100 |
| 1405 | 2 (پریودانتیکس) | 100 | 0 | 100 |
| 1405 | 3 (پروتز) | 100 | 0 | 100 |
| 1405 | 4 (ترمیمی) | 100 | 16 | 84 |
| 1405 | 5 (جراحی دهان) | 100 | 0 | 100 |
| 1405 | 6 (بیماری‌های دهان) | 100 | 78 | 22 |
| 1405 | 7 (پاتولوژی دهان) | 100 | 56 | 44 |
| 1405 | 8 (رادیولوژی) | 100 | 0 | 100 |
| 1405 | 9 (ارتودنسی) | 100 | 0 | 100 |
| 1405 | 10 (دندان‌پزشکی کودکان) | 100 | 35 | 65 |
| **Total** | | **2000** | **185** | **1815** |

### بورد (board)

| Year | Specialty slot | Total | Chapter + PDF page | Missing |
|---|---:|---:|---:|---:|
| 1400 | 2 (پریودانتیکس) | 100 | 0 | 100 |
| 1400 | 3 (پروتز) | 100 | 0 | 100 |
| 1400 | 4 (ترمیمی) | 100 | 0 | 100 |
| 1400 | 6 (بیماری‌های دهان) | 100 | 0 | 100 |
| 1400 | 7 (پاتولوژی دهان) | 100 | 0 | 100 |
| 1400 | 8 (رادیولوژی) | 100 | 0 | 100 |
| 1400 | 9 (ارتودنسی) | 100 | 0 | 100 |
| 1400 | 10 (دندان‌پزشکی کودکان) | 100 | 0 | 100 |
| 1401 | 2 (پریودانتیکس) | 100 | 0 | 100 |
| 1401 | 3 (پروتز) | 100 | 0 | 100 |
| 1401 | 4 (ترمیمی) | 100 | 0 | 100 |
| 1401 | 6 (بیماری‌های دهان) | 100 | 0 | 100 |
| 1401 | 7 (پاتولوژی دهان) | 100 | 0 | 100 |
| 1401 | 8 (رادیولوژی) | 100 | 0 | 100 |
| 1401 | 9 (ارتودنسی) | 100 | 0 | 100 |
| 1401 | 10 (دندان‌پزشکی کودکان) | 100 | 0 | 100 |
| 1404 | 1 (اندودانتیکس) | 100 | 0 | 100 |
| 1404 | 2 (پریودانتیکس) | 100 | 0 | 100 |
| 1404 | 3 (پروتز) | 100 | 0 | 100 |
| 1404 | 4 (ترمیمی) | 100 | 0 | 100 |
| 1404 | 5 (جراحی دهان) | 100 | 0 | 100 |
| 1404 | 6 (بیماری‌های دهان) | 100 | 0 | 100 |
| 1404 | 7 (پاتولوژی دهان) | 100 | 0 | 100 |
| 1404 | 8 (رادیولوژی) | 100 | 0 | 100 |
| 1404 | 9 (ارتودنسی) | 100 | 0 | 100 |
| 1404 | 10 (دندان‌پزشکی کودکان) | 100 | 0 | 100 |
| **Total** | | **2600** | **0** | **2600** |

## Reproduce, read-only

```sql
SELECT et.type_key, si.exam_year, si.exam_round,
       COUNT(*) AS total,
       SUM(EXISTS (
         SELECT 1 FROM bank_question_sources s
         WHERE s.workspace_id=q.workspace_id
           AND s.question_id=q.id
           AND s.node_id IS NOT NULL
           AND s.pdf_page IS NOT NULL
           AND s.pdf_sha256 IS NOT NULL
       )) AS chapter_and_bound_pdf_page
FROM bank_questions q
JOIN bank_exam_sittings si
  ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
JOIN bank_exam_types et
  ON et.id=si.exam_type_id AND et.workspace_id=si.workspace_id
GROUP BY et.type_key, si.exam_year, si.exam_round
ORDER BY et.type_key, si.exam_year, si.exam_round;
```

Authoritative source-level evidence and syllabus validity are checked in
`scripts/references/apply_classification.py` and the audited page workflow;
the SQL above measures persisted fields only. Report exemptions separately
without writing synthetic sources. Re-measure after every audited import.
Open change PR #291 contains PDF chapter-map and scope repairs and documents
126 potentially out-of-scope **existing** citations; do not treat
any field-presence count as a science-level verification until that separate
audit is complete.
