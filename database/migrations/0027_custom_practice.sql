-- fanoos:rollback-compatible=expand
--
-- Expand-only: one nullable column and an index. The release before this one
-- never reads or writes the column, and every existing assessment keeps NULL.
--
-- آزمون دلخواه: a student builds a practice exam from the bank -- chosen
-- courses and topics, how many questions, all / not yet seen / got wrong --
-- and it is stored as an ordinary published assessment so the whole runner
-- (pacing, modes, timer, review, mistakes, images) works on it unchanged.
-- What makes it personal is this column: set, the assessment belongs to that
-- student alone and never appears in anyone's catalogue, their own included.
-- It is listed separately, as theirs.
SET NAMES utf8mb4;

ALTER TABLE exam_assessments
    ADD COLUMN created_for_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER workspace_id,
    ADD KEY idx_exam_assessments_created_for (workspace_id, created_for_user_id, created_at),
    ADD CONSTRAINT fk_exam_assessments_created_for FOREIGN KEY (created_for_user_id) REFERENCES iam_users (id);
