# Newest-first same-lineage proxy classification

**Owner authorization: 2026-10-22, revised 2026-10-23 and extended
2026-10-24.** The original residency rule remains: unfinished residency
sittings **1391–1396** use newest-first editions, 1397/1398 retain their
separate named exceptions, and pre-1396 sittings retain
`retrospective_proxy_1397_plus`.

The 2026-10-24 owner rule also applies this same-lineage proxy to **national,
board and promotion** sittings whenever the catalog has no official list for
that exact exam type, year and subject. It does not depend on a lower year
bound. National 1397–1400 subjects with no list may use the nearest official
national list of that subject (for example, national 1399 may select the
1401 list). Board and promotion subjects follow their own nearest same-type
list. An exact-year/type/subject list suppresses proxy selection completely,
with one owner-approved exception below. Never cross exam types or add a
synthetic validity row.

**Scope-gap exception (owner decision 2026-10-24).** National 1402 and 1403
pediatric dentistry list McDonald & Avery 11e, but the recovered syllabus row
names no chapters («فصول منتخب»), so no page can be checked against scope.
Only these two pairs (`SCOPE_GAP_REFERENCE_YEARS` in
`same_lineage_proxy_policy.py`, mirrored in `SourceOnlyPublisher`) may cite the
same edition under its national **1404** list and chapter scope, as a visible
`same_lineage_proxy` with `reference_year=1404` and the usual Persian proxy
note. The exception lapses automatically if a structured exam-year scope is
ever recorded, and it never selects another edition, family or year.

## Selection

1. Identify reference families: the exam-year/type/subject list for exact
   citations; for a non-residency proxy, use the families in the nearest
   official list year of the same type and subject. Prefer the later year
   when two list years are equally near. Pre-1396 residency sittings continue
   to use the 1397+ retrospective selector.
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
Residency callers and CLI behavior are unchanged. For national, board and
promotion, the selector returns only exact official rows when the requested
year has a list; otherwise it selects same-type/same-subject proxy families
from the nearest list year, with later-year tie-breaking. It only selects
candidates; `verified_reference_pdf.py`,
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
start with `same-lineage-proxy-`. This applies to non-residency batches too;
the importer rechecks in the same transaction that their exact-year official
list is absent and resolves the cited official row for the same type,
subject and `reference_year`. Keep exact-year and proxy decisions in
separate audited batches. Preview the source-only transaction first.
Apply requires a fresh verified full backup, a unique private receipt and
independent source-row readback. The operator inserts only new source rows;
it does not edit questions, choices, answers, statuses, attempts or scores.
Keep the applicable whole-year publication gate. Exact non-residency
citations retain `official_exam_year`; same-lineage proxy citations retain
their actual `reference_year`, Persian proxy note and same-type validity row.

`--allow-nearest` and `--include-nearest` remain historical-audit options and
are not a route to authorize a new proxy. Unsupported answer, unreadable page, ambiguous
lineage or confidence below 0.85 also leaves the question pending while
eligible questions continue.
