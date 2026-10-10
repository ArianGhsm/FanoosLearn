# PDF reference source policy — 2026-10-10

## Owner instruction

For book-based chapter, page and evidence classification, the exact current
approved server PDF is the source of truth. A separate collected text file,
OCR export or manually edited transcription is not an alternate source. The
official exam sitting source and the reference-book source serve different
purposes: the sitting source verifies the question; the exact reference PDF
verifies its chapter, page and evidence.

## Implemented source path

1. `scripts/ops/reference-library-inventory.php` identifies current approved
   private PDF objects. The live inventory on 2026-10-10 contained 42 ready
   PDFs for 44 catalog editions.
2. `scripts/references/extract_server_reference.py` resolves the exact edition
   in the production DB, checks private storage confinement, size, PDF
   signature and SHA-256, then reads the PDF in place.
3. It creates one page-aligned private search index plus a
   `.provenance.json` receipt containing the source PDF SHA-256 and index
   SHA-256 inside `research/tmp/<operation-id>/`. It does not copy the PDF.
   Existing indexes that differ from the current verified PDF are refused for
   investigation instead of overwritten.
4. Search, chapter-map generation, classification validation and the private
   audit recheck the current PDF when run against
   an operation directory under `/srv/fanoos/shared/research`. Evidence must
   also be checked on the exact PDF page. After validator and audit receipts
   are recorded, delete the page index and sidecar when no concurrent batch
   uses that edition. The generated text index is a cache, not an independent
   source.
5. The reference manifest now names only verified-server-PDF sources for
   currently ready editions. On the 2026-10-10 inventory, 35 of its 36
   listed editions were ready; exact Hupp 6e remains pending. The live
   inventory, not this dated snapshot, controls future availability.

## Limits and safeguards

- The current extractor requires a complete enough PDF text layer. An
  unsearchable or incomplete edition stays pending until a reviewed,
  page-by-page PDF-derived OCR path is implemented and checked.
- Page indexes and provenance receipts, raw questions, decisions and book
  content remain in protected server storage, never Git. Page indexes and
  their `.provenance.json` cache sidecars live only under a per-operation
  `research/tmp/` directory and are deleted after the validated audit receipt
  is written and no concurrent batch uses them.
- On 2026-10-10, cleanup removed 43 PDF-index paths (35 files and 8 links),
  36 matching provenance sidecars, 16 transient search/validation text files
  and 25 Python bytecode files. All index hashes matched the current approved
  PDF inventory first. It reclaimed an estimated 149,016,576 allocated bytes;
  the remaining seven research `.txt` files are checksum, license or
  dependency manifests. Six affected checksum manifests were updated and
  verified. The private path/hash receipt is
  `classification/reports/artifact-cleanup-20261010.json`. No live
  question/source rows, answers, assessments or PDF objects changed, and no
  release was deployed.

## Resume steps

Read `docs/PROJECT_PRINCIPLES.md`, `docs/product/09_CHAPTER_CLASSIFICATION.md`
and `docs/ops/SERVER.md`. Before classifying a batch, create a unique protected
temp directory, refresh the exact edition index from the verified PDF, confirm
the source/index hashes and chapter map, then review the selected PDF page.
After the independent audit is recorded and no other active batch uses the
edition, remove the temporary index and directory. Keep the concise PDF hash
and validation receipt with the batch record.
