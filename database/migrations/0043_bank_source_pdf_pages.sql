-- fanoos:rollback-compatible=expand
--
-- Expand-only: nullable columns and one index. The release before this one
-- never reads them.
--
-- Where a question comes from, to the page of the exact book file, so a
-- descriptive answer can later be written from that page (owner, 2026-10-11):
--
-- bank_question_sources.pdf_page -- the page number in the PDF file (1-based),
--   as read from the verified server PDF.
-- bank_question_sources.printed_page -- the page label printed on that page
--   ("523", "xii"); the legacy `page` column mixed both forms ("523", "pdf 257").
-- bank_question_sources.pdf_sha256 -- the SHA-256 of the PDF file the page
--   numbers refer to (content_objects.checksum_sha256 of the reference_pdf), so
--   a later replacement of the file cannot silently shift the page.
--
-- bank_explanations.source_edition_id / source_pdf_page / source_pdf_sha256 --
--   the book page an explanation was written from, recorded by value (source
--   rows can be replaced by a re-import; the page they named cannot change).
SET NAMES utf8mb4;

ALTER TABLE bank_question_sources
    ADD COLUMN pdf_page INT UNSIGNED NULL AFTER page,
    ADD COLUMN printed_page VARCHAR(20) NULL AFTER pdf_page,
    ADD COLUMN pdf_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER printed_page,
    ADD KEY idx_bank_question_sources_pdf_page (workspace_id, edition_id, pdf_page);

ALTER TABLE bank_explanations
    ADD COLUMN source_edition_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER source_location,
    ADD COLUMN source_pdf_page INT UNSIGNED NULL AFTER source_edition_id,
    ADD COLUMN source_pdf_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER source_pdf_page,
    ADD CONSTRAINT fk_bank_explanations_source_edition FOREIGN KEY (source_edition_id) REFERENCES bank_reference_editions (id);
