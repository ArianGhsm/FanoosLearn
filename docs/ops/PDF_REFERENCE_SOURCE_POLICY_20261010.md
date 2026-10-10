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
   SHA-256. It does not copy the PDF. Existing indexes that differ from the
   current verified PDF are refused for investigation instead of overwritten.
4. Search, chapter-map generation, classification validation and the private
   audit recheck the current PDF when run against
   `/srv/fanoos/shared/research`. Evidence must also be checked on the exact
   PDF page. The generated text index is a cache, not an independent source.
5. The reference manifest now names only verified-server-PDF sources for
   currently ready editions. On the 2026-10-10 inventory, 35 of its 36
   listed editions were ready; exact Hupp 6e remains pending. The live
   inventory, not this dated snapshot, controls future availability.

## Limits and safeguards

- The current extractor requires a complete enough PDF text layer. An
  unsearchable or incomplete edition stays pending until a reviewed,
  page-by-page PDF-derived OCR path is implemented and checked.
- Page indexes and provenance receipts, raw questions, decisions and book
  content remain in protected server storage, never Git.
- This change updated source policy and code only. It did not change live
  question/source rows, answers, assessments, PDF objects, or deploy a
  release.

## Resume steps

Read `docs/PROJECT_PRINCIPLES.md`, `docs/product/09_CHAPTER_CLASSIFICATION.md`
and `docs/ops/SERVER.md`. Before classifying a batch, refresh the exact
edition index from the verified PDF, confirm the source/index hashes, chapter
map, and protected workspace backup, then review the selected page in the PDF.
