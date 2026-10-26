-- fanoos:rollback-compatible=expand
-- Owner rule 2026-10-24/26: the official key always wins and is never changed.
-- When the cited page clearly gives another answer, the source keeps its
-- chapter/page and carries one visible note naming both the official key and
-- the book's answer. NULL for every other source.
SET NAMES utf8mb4;

ALTER TABLE bank_question_sources
    ADD COLUMN key_conflict_note VARCHAR(300) NULL AFTER official_scope_note;
