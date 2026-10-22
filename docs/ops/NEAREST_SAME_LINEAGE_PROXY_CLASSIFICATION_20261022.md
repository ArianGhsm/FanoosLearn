# Nearest same-lineage reference proxies for exam years 1396–1398

**Owner authorization: 2026-10-22.** This runbook covers older exam sittings
dated 1396, 1397 and 1398 when their exact official reference edition cannot
be used. It complements, and does not replace, the separate
`retrospective_proxy_1397_plus` rule for 1395 and earlier. It does not
authorize proxies after 1398.

## Selection

1. Read the exact exam-year/type/subject reference rows first. If the exact
   edition has a current approved private PDF and a contiguous chapter map
   bound to that PDF's SHA-256, use it.
2. If the exact edition/PDF is absent or unusable, keep the same reference
   family and exam-type/subject. Select the nearest different reference year
   with an official validity row, a verified private PDF and a matching
   complete chapter map. An equal-distance tie selects the earlier year.
3. If no exam-year validity list exists, take the nearest official reference
   list for that exam type and subject as the set of candidate book families;
   resolve each family to its nearest usable official edition under step 2.
4. The selected catalog row remains the source of its actual reference year
   and official scope. Never alter `catalog.validity` to make it look
   official for the exam year. If the exact edition exists and its page does
   not support the question, leave the question pending rather than trying a
   proxy.

The versioned selector is `scripts/references/nearest_same_lineage_proxy_policy.py`.
It only selects candidates; `verified_reference_pdf.py`,
`apply_classification.py` and `audit_private_study_batches.py` still check the
current approved object, exact PDF SHA, matching chapter map, original page,
chapter scope, direct evidence and confidence. Pages are read one at a time
and discarded; no book text, OCR or index is saved.

## Required decision fields and site label

Every proxy decision records:

- `classification_basis=nearest_same_lineage_proxy`;
- the actual `reference_year` and edition key, separately from `exam_year`;
- chapter, positive 1-based physical `pdf_page`, the same PDF's SHA-256,
  short evidence from that original page and confidence of at least 0.85;
- a Persian `proxy_note` naming both years and stating that the reference is
  a proxy whose official status for the exam year is unverified.

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
are not a route to authorize a new proxy. A missing PDF, invalid map,
unsupported answer, unreadable page, ambiguous lineage or confidence below
0.85 leaves the question pending while eligible questions continue.
