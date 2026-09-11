-- fanoos:rollback-compatible=expand
-- Stage 8: expose quiz as a presentation variant without changing the
-- established assessment-kind constraint used by older runtimes.
SET NAMES utf8mb4;

ALTER TABLE exam_assessment_metadata
    ADD COLUMN assessment_variant VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER assessment_kind,
    ADD KEY idx_exam_metadata_variant (workspace_id, assessment_variant);
