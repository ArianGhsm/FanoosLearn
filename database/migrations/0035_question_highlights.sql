-- fanoos:rollback-compatible=expand
--
-- Expand-only: one new table. The release before this one never reads or
-- writes it.
--
-- هایلایت‌ها: the parts of a question's stem a student highlighted, kept on
-- the account so they reach every device and the highlights page. Stored as
-- offsets into the stem, never as the text: the text stays in the exam it
-- came from, and the page reads the fragments back from there for the
-- student who made them.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS exam_question_highlights (
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    question_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    assessment_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ranges_json JSON NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (workspace_id, user_id, question_key),
    KEY idx_exam_question_highlights_recent (workspace_id, user_id, updated_at),
    CONSTRAINT fk_exam_question_highlights_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_exam_question_highlights_user FOREIGN KEY (user_id) REFERENCES iam_users (id),
    CONSTRAINT fk_exam_question_highlights_assessment FOREIGN KEY (assessment_id) REFERENCES exam_assessments (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
