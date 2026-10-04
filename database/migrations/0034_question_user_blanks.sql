-- fanoos:rollback-compatible=expand
--
-- Expand-only: one new table. The release before this one never reads or
-- writes it.
--
-- پاسخ سفید: how often a student left a question blank in a scored attempt.
-- Kept apart from exam_question_user_stats on purpose: those counters are
-- about answers (a blank is not one), and every reader of them -- the
-- review box, mastery, weak topics -- stays exactly as it was. Derived like
-- the other counters (QuestionStatsRecorder::record and ::rebuild).
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS exam_question_user_blanks (
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    question_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    blank_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_blank_at DATETIME(6) NOT NULL,
    PRIMARY KEY (workspace_id, user_id, question_key),
    CONSTRAINT fk_exam_question_user_blanks_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_exam_question_user_blanks_user FOREIGN KEY (user_id) REFERENCES iam_users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
