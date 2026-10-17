# PDF reference source policy — 2026-10-10

## Owner instruction

For chapter, page and evidence classification, the exact current approved
private server PDF is the sole reference-book source. Never create or store a
text copy, complete text stream, OCR export, page index or corpus derived from
a PDF. Do not copy the PDF out of protected storage. The official sitting
source verifies question wording; the exact reference PDF verifies its
chapter, page and evidence.

A selected short quote may be stored in a decision because the validator and
reviewer need evidence for that page. It is a citation, not a textual version
of the PDF. Full pages and books are never serialized.

## Direct-PDF workflow implemented on the working branch

1. `scripts/ops/reference-library-inventory.php` identifies current approved
   private PDF objects. The live inventory observed on 2026-10-10 contained
   42 ready PDFs for 44 catalog editions.
2. `scripts/references/verified_reference_pdf.py` resolves the exact edition
   through the production DB and verifies private-storage confinement, size,
   PDF signature, page count and current PDF SHA-256.
3. A tool requests a single numbered page from that PDF. Poppler writes only
   that page to stdout; the caller checks it in memory and discards it. No
   output file, whole-book text stream, `.txt` index, OCR file or search cache
   is created. Search keeps only selected page numbers and short snippets;
   chapter mapping keeps votes and page runs; audit receipts keep PDF hash,
   page count and decisions.
4. `scripts/references/apply_classification.py` and
   `scripts/references/audit_private_study_batches.py` re-open the current
   approved PDF and validate the evidence on the cited PDF page. The chapter
   map stores page ranges and source-PDF hashes, not extracted page text.
5. If the PDF is absent or a needed page cannot be read, leave the item
   pending and review the original PDF visually. Do not OCR or export text.

The PHP page-label checks use the same bridge with `--page` and
`--require-map`; they receive one PDF page over a process pipe, compare it in
memory and discard it. Operational correction scripts must pin the PDF edition
and page, and may keep only the PDF SHA-256 plus concise evidence in their
private receipt. They must not depend on a research `.txt` file or a whole-book
text hash.

Readability preflight:

```sh
sudo -u fanoosupd python3 -B scripts/references/verify_reference_pdfs.py \
  --only <edition> --check-readable
```

Build only the requested edition's page-to-chapter map after reviewing the
original PDF pages:

```sh
sudo -u fanoosupd python3 -B scripts/references/build_chapter_pages.py \
  --only <edition>
```

## Inserted printed-page labels

Some approved PDFs place separately labeled insert pages, such as 388.e1,
between ordinary printed pages. A numeric target is still accepted only when
its exact printed label appears on the target PDF page and the printed-page
sequence is confirmed on both sides within two physical pages. A predecessor
may use the immediately preceding numeric base with an .eN suffix; a successor
may be an .eN insert after the target or the next numeric page. This accounts
for supplemental pages without relying on a fixed printed-to-PDF offset. The
exact PDF SHA, mapped chapter, selected-page evidence and source-only guards
remain required. If either side of the sequence is missing or conflicts, leave
the classification pending.

## Historical cleanup and release state

The previous workflow did create temporary full-book page-text indexes. On
2026-10-10, cleanup removed 43 index paths (35 files and 8 links), 36
provenance sidecars, 16 search/validation text outputs and 25 Python bytecode
files. It reclaimed an estimated 149,016,576 allocated bytes. The seven
remaining `.txt` files in the protected research tree are checksum, license
or dependency manifests. Six checksum manifests were updated and verified.
The private path/hash receipt is
`/srv/fanoos/shared/research/classification/reports/artifact-cleanup-20261010.json`.
A later archive scan found one more copy inside the W08 Malamed 7 recovery
package. That member and two empty edit backups were removed; the package
checksum was rebuilt and verified. Receipt:
`/srv/fanoos/shared/research/classification/reports/pdf-text-archive-cleanup-20261010.json`.
Thus it would be inaccurate to say no text indexes were ever created; the
live working copies and the discovered archived copy have been removed.

A follow-up scan then found 10 regenerated page-text files, 8 cache receipts
and 3 search-output logs in the live research workspace. No classification
process was running; two of those indexes lacked valid PDF provenance. Cleanup
removed those 21 files (33,961,422 logical bytes) and recorded exact paths and
hashes in `/srv/fanoos/shared/research/classification/reports/artifact-cleanup-followup-20261010.json`.

A later scan at 13:47 UTC found one more 4,857,402-byte index and its cache
receipt, with a source hash matching Neville 4e's current approved PDF and no
active reader. Both were removed; receipt:
`/srv/fanoos/shared/research/classification/reports/artifact-cleanup-recurrence-20261010.json`.

At 19:45 UTC, the next exact-path scan found seven more complete page-text
copies (McCracken/Carranza/Neville/Shillingburg/Zarb) plus a 206-byte W06 Q86
proof note. No reader/classifier process was active. The current inventory
confirmed all seven corresponding private PDFs as approved and SHA-verified;
the Q86 quote already had a private JSON decision, which was checked against
PDF page 412 in memory. All eight files were removed, reclaiming 38,853,134
logical bytes. Receipt:
`/srv/fanoos/shared/research/classification/reports/artifact-cleanup-pdf-text-20261010-1945.json`.

At 19:50 UTC, an additional private JSON proof bundle containing full text for
PDF pages 246 and 281 of Torabinejad 6e was found. Both exact pages were read
again directly from the current approved 501-page PDF and matched the needed
short evidence quotes; the page-text bundle was removed (12,860 bytes). Receipt:
`/srv/fanoos/shared/research/classification/reports/artifact-cleanup-page-proof-20261010-1950.json`.

The post-cleanup readback found **zero** `@edition.txt` files, text provenance
sidecars, `.pyc`, `.tmp` or `.partial` files, and zero full-book text members in
the three research archives scanned. The seven remaining `.txt` files are
checksum, license and dependency manifests only.

The two W08 checkouts mentioned at the earlier checkpoint were later removed
on 2026-10-11. [PR #203](https://github.com/ArianGhsm/FanoosLearn/pull/203)
had merged and [PR #233](https://github.com/ArianGhsm/FanoosLearn/pull/233)
had closed; both checkouts were clean and their commits recoverable from Git.
The protected W08 handoff and checksums remain. Private receipt:
`classification/reports/artifact-cleanup-w08-checkouts-20261011.json`.

A fresh scan on 2026-10-11 found two complete Neville 4e/5e text copies in
an older oral-pathology research folder, created after the prior cleanup.
Both approved original PDFs and their current hashes/page counts were
verified through the direct reader; no classifier was active. The two exact
files were removed (10,444,094 logical bytes) with private receipt
`classification/reports/artifact-cleanup-neville-text-20261011.json`.
The completed `tmp/nat1405-probe/` operation also left 48 query, one-off
script and duplicate sitting files. Its `check.json` was byte-identical to
the protected validated national 1405 sitting; 80 sources were live. Those
48 exact files were removed with receipt
`classification/reports/artifact-cleanup-national1405-probe-20261011.json`.
Post-cleanup read-back of the protected research tree and seven inspected
archives found zero PDF text files, `page_text`/`full_text`/`book_text` JSON
fields, bytecode, `.tmp` or `.partial` files. The three remaining `.txt`
files are checksum manifests; `research/tmp/` is empty. Other protected
parallel research scripts and unique decisions were not swept without a
separate recovery review.
Private W04/W05/W06/W08 handoffs that still described the old extractor were
corrected with explicit PDF-only resume instructions; obsolete extractor
steps in the active W06/W08 procedures were superseded. The private W04
checksum manifest no longer lists ten removed caches, and the affected
W05/W06/W08 manifests were refreshed with old/new hashes preserved in audit
receipts. That intermediate read-back verified **15 checksum manifests and 198 member
hashes, with zero missing or mismatched members**. Private correction receipts:
`classification/reports/private-pdf-handoff-correction-20261011.json` and
`w08-handoff-pdf-only-correction-20261011.json`.
Four obsolete private one-off scripts that wrote PDF text or depended on the
removed extractor were then removed from W06/W07/W08, after correcting seven
protected handoffs and their checksum manifests. Current versioned inventory,
PDF verifier and page validator are their recovery route. Private receipt:
`classification/reports/obsolete-pdf-text-script-cleanup-20261011.json`.
A static post-cleanup scan found no remaining private script calling the old
text writer or a `pdftotext`-to-`.txt` path. The final checksum read-back now
verifies 15 manifests/195 members with zero errors.

The direct-PDF implementation in PR #255 was subsequently merged as
`1a31ac96b8cfcd2d5a2e5aa4147b77a81567b073` and is in the live release
`524ec2b25a8ce6325ab95b26b66e25bcb50f7140` as checked on 2026-10-11.
The previous `69ff7223a46298489a15cd35745dd4d77305089c` release and its
legacy extractor are historical; do not invoke that extractor. At the earlier
check, the catalog had 33 chapter maps: 24 carried a PDF hash,
and 28 have contiguous page coverage. Nine lack a source hash; five have page
gaps or overlaps, with four maps in both groups. Search, validation and audit
fail closed for the 10 maps that fail either check. The other 23 have both a
recorded source hash and contiguous coverage; runtime still compares each hash
to the exact current approved PDF. Recheck page count and hash through the
server bridge before using a map. Cleanup changed no live question/source rows,
answers, assessments or PDF objects; it removed only generated research text
artifacts. No release was deployed.

## Resume

Read `docs/PROJECT_PRINCIPLES.md`, `docs/product/09_CHAPTER_CLASSIFICATION.md`
and `docs/ops/SERVER.md`. Verify the live PDF inventory and exact SHA-256,
then inspect only the pages needed. Keep decisions and concise audit receipts;
there is no PDF-text cache to create or clean up.
