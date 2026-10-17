-- fanoos:rollback-compatible=expand
-- Preserve an exact source location when an official chapter is only
-- partially in scope but the approved PDF page has no corroborated printed
-- label. This status is never publication-complete.
SET NAMES utf8mb4;

ALTER TABLE bank_question_sources
    ADD COLUMN scope_unverified BOOLEAN NOT NULL DEFAULT FALSE AFTER official_scope_note,
    ADD KEY idx_bank_question_sources_scope_unverified (workspace_id, scope_unverified);
