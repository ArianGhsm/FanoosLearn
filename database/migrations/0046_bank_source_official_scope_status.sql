-- fanoos:rollback-compatible=expand
-- A verified citation may identify the true design location even when that
-- chapter was outside the official syllabus. The exception stays explicit.
SET NAMES utf8mb4;

ALTER TABLE bank_question_sources
    ADD COLUMN official_scope_status ENUM('in_scope','outside_scope_exam_question') NOT NULL DEFAULT 'in_scope' AFTER pdf_sha256,
    ADD COLUMN official_scope_note VARCHAR(300) NULL AFTER official_scope_status,
    ADD KEY idx_bank_question_sources_scope_status (workspace_id, official_scope_status);
