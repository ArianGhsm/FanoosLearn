# Classification of exams before 1396 against 1397+ reference books

**Policy adopted by the owner on 2026-10-11.** This is a new *retrospective*
classification allowance, NOT a statement that a 1397 edition or later was
an official reference when a 1391–1395 question originally appeared.

## Scope and selection

- Eligible exam sitting: year **<= 1395** (strictly *before* 1396).
  The implementation must retain exam-type and subject identity.
- Start with the applicable subject's verified **1397** announced references,
  then examine **1398, 1399, ...** in order. For a particular reference-book
  family use the earliest eligible year whose **exact** edition has an
  accessible approved private PDF, a hash-bound chapter-to-PDF-page map and
  usable evidence. If a book family is unavailable, investigate the later
  listed references for the subject; do not stop all subjects.
- Each accepted evidence record notes separately the exam year, **reference
  year**, book/edition, chapter, positive original **PDF page**,
  **SHA-256 of that PDF**, verbatim source-page evidence, confidence and
  `classification_basis=retrospective_proxy_1397_plus`.
- Source year = year of the official *proxy* list, not the exam year.
  The original official exam-year source list is explicitly **unknown** unless
  separately proved. No invented historical `official=true` rows in
  `catalog.validity`, and no future publication or UI may present these
  links as verified original-year official references.
- Ineligible: year **>= 1396**, unspecified edition, absent or unverified
  original server book PDF, invalid chapter map, unverified page, unsupported
  official proxy-year chapter/scope, unsupported answer inference, or
  human-reviewed conflicts. Absent exact evidence stays pending.
- Keep the official-key-only English exception where applicable; voided or
  disputed answers stay outside confirmed scored classifications.

## Operational method

1. Inspect fresh GitHub `main`, server release, live bank, exact existing
   source records, prior decisions and concurrent tasks before changes.
2. Use the exact protected site-matching sitting; never rewrite stems,
   choices, answer keys, statuses, human decisions or frozen attempts.
3. Select 1397+ proxy-year validity rows while *preserving original validity
   years*. Check the exact available edition using the private reference
   inventory and original server PDF; no copied books or persistent text
   exports. Verify the chapter-to-page map's hash, coverage and boundaries.
4. For each supported fact locate the *answer-bearing* original PDF page,
   record a short exact quote, chapter, `pdf_page`, hash, confidence and
   explicit `reference_year`; run `apply_classification.py` and the
   independent private study audit against that same PDF page.
5. The guarded source-only publisher must explicitly check the selected
   reference-year's authentic official DB validity and scope, the current
   original question/choices/official-answer snapshot, existing source
   absence, non-voided published status, matching provenance receipt and
   proof of an independently verified **fresh full DB backup**. Rollback
   previews and publication must never broaden scope by mislabeling a
   1397+ reference as officially valid in 1391.
6. Confirm the same-source chapter+`pdf_page`+`pdf_sha256` readback,
   published assessment, CI and official updater deployment where
   applicable. Report counts separately for original-year official
   citations versus **retrospective proxy** citations.

**No shortcuts:** a bare chapter counts zero, even for retroactive exam years;
a PDF page without proven chapter and edition counts zero. Source-only
publication is allowed only once compatible, tested, audited code is
deployed; no manual SQL bypass or ordinary full-sitting importer.

## Residency 1391 baseline and recovery

- `220` bank questions; `217` final keys, `3` voided
  (Q86, Q111, Q160); `0` previously linked sources.
- Exact exported sitting:
  `/srv/fanoos/shared/research/bank-sittings/1391/residency-1391-1.json`
  SHA-256 `7ef8f7c4aabc620fe7fc132c76615a9f5517baa98f9d4c7a951f97f9efc2730a`.
  Dry-run: `questions_changed=0`, `answers_recorded=0`.
- Original preflight:
  `/srv/fanoos/shared/research/ops/residency-1391-classification-preflight-20261011.json`.
  Independently validated four-file protected research archive:
  `/var/backups/fanoos/research/20261011-residency1391-preflight.tar.gz`.
- The first official 1397 subject catalog exists, but only eligible **exact
  PDFs/maps** can be used. For example, an absent 1397 PDF is not an excuse
  to guess its chapter or page; progress to the next documented year.
- Previous 1391 preflight held on absence of a 1391 official list; this
  policy **supersedes only that hold**, not the scientific page requirement.

Future receipts must record the selected reference year and all
recoverability / publication evidence. Update this document with actual
processed questions and remaining reason codes, not predictions.
