-- fanoos:rollback-compatible=expand
--
-- Expand-only: three nullable columns. The release before this one never
-- reads them.
--
-- bank_reference_validity.scope_chapters -- the announced scope read into
--   chapter numbers ([{number, partial?}]), so the site can mark which
--   chapters of a book an exam year covers. NULL when the announcement does
--   not name a chapter list; `scope` keeps the text either way.
-- bank_reference_nodes.title_fa / title_fa_origin -- a Persian title beside
--   the publisher's English one, and whether it is a machine translation
--   ('ai') or reviewed ('human').
SET NAMES utf8mb4;

ALTER TABLE bank_reference_validity
    ADD COLUMN scope_chapters JSON NULL AFTER scope;

ALTER TABLE bank_reference_nodes
    ADD COLUMN title_fa VARCHAR(300) NULL AFTER title,
    ADD COLUMN title_fa_origin VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER title_fa;
