# Bank chapter-classification snapshot — 2026-10-11

> **Production read-back, not a target or a claim of reference validity.**
> Source: the live `fanoos_prod` MySQL 8 database on the authenticated
> FANOOS production server, 2026-10-11 (UTC), while GitHub `main` and
> `/srv/fanoos/current` both pointed to `524ec2b25a8ce6325ab95b26b66e25bcb50f7140`.
> Read-only operations only; **no questions, answers, sources, assessments,
> reviews or attempts were changed**.

## Owner's newer atomic rule — 2026-10-11

This snapshot originally measured **any chapter node**, yielding
2,169/10,295. Under the later owner decision, a new book-based chapter
classification **must** include its exact, hash-bound PDF page on the same
source row; otherwise it is incomplete. A new read-only audit of this
same production state found **1,047/10,295** questions with `node_id`,
`pdf_page` and `pdf_sha256` together, leaving **9,248** incomplete by
the new recorded-fields criterion (including 1,122 legacy chaptered rows
without a bound PDF page). 239 independently verified residency-English
questions are explicitly exempt, leaving **9,009** after subtracting only
that exemption. This is a *field-presence* count, not a replacement for
original-page and official-scope validation. The complete 70-sitting
read-back and reproducible SQL are in
`docs/ops/BANK_CLASSIFICATION_PAGE_BACKLOG_20261011.md`
(which is introduced by PR #291). Old raw node/source counts below are
retained and labeled as historical/diagnostic only. No production
records were modified to recalculate these counts.

## Site-wide live totals

A question is **chaptered** if it has at least one
`bank_question_sources` row with non-null `node_id`. Count unique
`bank_questions.id`, **not** total source rows. Includes all question
statuses, including drafts and withdrawn.

| Exam type | All questions | Chaptered | Without chapter | Chapter rate |
|---|---:|---:|---:|---:|
| residency | 3,202 | 1,760 | 1,442 | 54.97% |
| promotion | 2,000 | 185 | 1,815 | 9.25% |
| board | 2,600 | 0 | 2,600 | 0.00% |
| national | 2,493 | 224 | 2,269 | 8.99% |
| **All** | **10,295** | **2,169** | **8,126** | **21.07%** |

Every one of the 2,169 sourced questions has a chapter node. There
are exactly 2,169 source rows and 2,169 distinct sourced question IDs.
Of these, **1,832** have at least one chapter-bearing source row with
`pdf_page`, `printed_page` or legacy `page`, leaving **337** with
a chapter but no page field. **1,047** have both `pdf_page` and
`pdf_sha256` populated. There are **58** questions with human-origin
source rows. Field presence is **not** a fresh independent audit of
PDF evidence, current official scope, citation accuracy or source
review status.

**Published questions only:** 10,228 questions, of which 2,151
have a chapter node; 8,077 do not. The all-status figure includes
17 chaptered drafts and one chaptered withdrawn question.

## Policy exception: residency English

There are **239** residency `english` questions, all without book
source rows. Their latest official key records were independently
checked: **238 final + 1 amended**, all with a non-null
`choice_position`, and all 239 questions are published. Under
`docs/PROJECT_PRINCIPLES.md` §3A they are **complete without a book
citation**. Do not insert synthetic sources or count them in
book-reference classification queues.

Thus, from the raw **8,126** without chapters, **239** are this
verified explicit exemption; **7,887** remain after subtracting this
exemption only. This is *not* a claim that every remaining question
requires a textbook: English and basic sciences in national papers
have their own source-scope statements, and all exam-type/year
validities must be checked before forming actionable queues. For
reporting, keep the raw numerator and denominator unchanged.

## Where new recorded chapters came from

- Residency 1398–1405: 1,697; residency 1397: 63; together 1,760.
- National 1405: 80; national 1404 August: 61; national 1404
  winter: 83; together 224.
- Promotion 1405: 185 in rounds 4 (16), 6 (78), 7 (56), 10 (35).
- Board: no recorded chaptered questions; promotion 1404: none.

Runbooks: `docs/ops/RESIDENCY_CLASSIFICATION_1398_1405.md`,
`docs/ops/RESIDENCY_PROMOTION_CLASSIFICATION_20261011.md`, and
`docs/ops/NATIONAL_CLASSIFICATION_1404_1405.md`.

The contemporaneous open PR **#291**, "Enforce official chapter scope
and repair four exact-PDF maps", reports **126** live source rows
outside explicit year-specific chapter lists (including **25** in
latest published sittings). That pending PR is **not in deployed main**
at this snapshot. The recorded chaptered total must **not** be
presented as a fully independently verified, in-scope total. Source
review/corrections require separate audits, backup and authorized
source-only procedures; no existing human decisions should be
overwritten silently.

## Reproducibility

The read-back ran as the authorized `fanoosupd` operator against the
live production DB, using the server-held MySQL defaults configuration.
Credentials and raw questions were neither read into this report nor
committed. To reproduce, use the authorized read-only DB connection and
the following MySQL query (or equivalent); **do not** run on a test,
restore or staging DB when claiming live statistics.

```sql
SELECT et.type_key,
       COUNT(*) AS total_questions,
       SUM(EXISTS (
           SELECT 1 FROM bank_question_sources src
           WHERE src.workspace_id = q.workspace_id
             AND src.question_id = q.id
             AND src.node_id IS NOT NULL
       )) AS chaptered_questions
FROM bank_questions q
JOIN bank_exam_sittings si ON si.id = q.sitting_id
                           AND si.workspace_id = q.workspace_id
JOIN bank_exam_types et ON et.id = si.exam_type_id
                        AND et.workspace_id = si.workspace_id
GROUP BY et.type_key ORDER BY et.type_key;
```

For page coverage, count a question if a **single** source row has
non-null `node_id` and any of `pdf_page`, `printed_page` or
`page`. Count a verified-PDF-field question if a single source row
has both `pdf_page` and `pdf_sha256`. For the English exemption,
join residency + subject `english`, select the latest
`bank_official_answers` record per question by `recorded_at DESC,
id DESC`, and check `status IN ('final','amended')` and
`choice_position IS NOT NULL`.

Additional read-only checks: live `/health` returned **HTTP 200**;
`scripts/dev/check-sync.sh` reported updater checkout and deployed
release both in sync with GitHub `main` at `524ec2b`.

**Follow-up:** Re-run the database audit after a new import; audit
out-of-scope references before declaring source completeness; keep
raw, exemption-aware, page-bearing, and verified-citation counts
separate.
