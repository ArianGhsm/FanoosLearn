# Newest-first same-lineage reference editions for older residency sittings

**Owner authorization: 2026-10-22, revised 2026-10-23.** The current
residency exam is set from the new references, so older sittings are
classified against the newest available edition of each reference family.
The newest-first order is enforced for the unfinished sittings **1391–1396**.
Exam years 1397 and 1398 are complete under their named updated-reference
exceptions and are not reopened. Sittings 1395 and earlier keep the separate
`retrospective_proxy_1397_plus` basis (candidates from 1397+ lists) with the
same order. No proxy is authorized after 1398.

## Selection

1. Identify the reference families: the exam-year/type/subject list, or the
   nearest official list for that exam type and subject when the exam-year
   list is absent (1395 and earlier: the 1397+ lists).
2. Within each family, rank every official edition newest first (by the
   newest year it was listed). One row per edition: the exam-year row when
   that edition was official in the exam year (then it is not a proxy),
   otherwise its newest official year, which is the recorded
   `reference_year`.
3. An edition is eligible only when its approved private PDF is verified and
   its hash-bound chapter map and structured scope are usable. Ineligible
   editions are reported as pending and skipped in the order.
4. Search the rank-0 edition first. Cite an older eligible edition only when
   the answer-bearing page is not found in every newer eligible edition of
   that family; the decision then lists those newer editions, newest first,
   in `newer_editions_searched`. If no eligible edition supports the
   question, it stays pending.
5. The selected catalog row remains the source of its actual reference year
   and official scope. Never alter `catalog.validity` to make it look
   official for the exam year, and never carry a page or chapter from one
   edition to another.

The versioned selector is `scripts/references/same_lineage_proxy_policy.py`.
It only selects candidates; `verified_reference_pdf.py`,
`apply_classification.py` and `audit_private_study_batches.py` still check the
current approved object, exact PDF SHA, matching chapter map, original page,
chapter scope, direct evidence and confidence. Pages are read one at a time
and discarded; no book text, OCR or index is saved.

## Required decision fields and site label

Every proxy decision records:

- `classification_basis=same_lineage_proxy`;
- the actual `reference_year` and edition key, separately from `exam_year`;
- chapter, positive 1-based physical `pdf_page`, the same PDF's SHA-256,
  short evidence from that original page and confidence of at least 0.85;
- a Persian `proxy_note` naming both years and stating that the reference is
  a proxy whose official status for the exam year is unverified;
- `newer_editions_searched` when the cited edition is not rank 0 in its
  family (exactly the newer eligible editions, newest first).

Migration `0048_bank_source_reference_provenance.sql` adds nullable source
columns for `reference_year`, `classification_basis` and
`classification_note`. Historical rows stay null until an individually
audited repair. `BankQuestionFacts` sends the values to the runner, which
shows the proxy note with the chapter and actual book edition.

## Safe source-only publication

Research proposals remain pending until they pass both the exact-page
validator and the independent provenance audit. Use the protected,
immutable same-year recovery case with
`scripts/references/import_verified_sources.php`; generic proxy case stems
start with `same-lineage-proxy-`. Keep exact-year and proxy decisions in
separate audited batches. Preview the source-only transaction first.
Apply requires a fresh verified full backup, a unique private receipt and
independent source-row readback. The operator inserts only new source rows;
it does not edit questions, choices, answers, statuses, attempts or scores.
Keep the whole-year publication gate. This operator currently accepts
residency batches; other exam types remain pending until their compatible
guarded writer is available.

`--allow-nearest` and `--include-nearest` remain historical-audit options and
are not a route to authorize a new proxy. Unsupported answer, unreadable page, ambiguous
lineage or confidence below 0.85 also leaves the question pending while
eligible questions continue.
