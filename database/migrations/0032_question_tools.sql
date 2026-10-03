-- fanoos:rollback-compatible=expand
--
-- Expand-only: three new tables. The release before this one never reads or
-- writes them.
--
-- A student's own tools on a question, kept across exams and attempts and
-- keyed by the stable question id (the bank's question_key), like the stats
-- and the mistakes review:
--   exam_question_bookmarks -- سؤال‌های ذخیره‌شده, to come back to.
--   exam_question_notes     -- the student's note; only they see it.
--   exam_question_reports   -- "this question or its answer is wrong",
--                              for the workspace's reviewers to resolve.
-- assessment_id is the exam it was saved from: where its text is read back.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS exam_question_bookmarks (
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    question_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    assessment_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (workspace_id, user_id, question_key),
    KEY idx_exam_question_bookmarks_recent (workspace_id, user_id, created_at),
    CONSTRAINT fk_exam_question_bookmarks_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_exam_question_bookmarks_user FOREIGN KEY (user_id) REFERENCES iam_users (id),
    CONSTRAINT fk_exam_question_bookmarks_assessment FOREIGN KEY (assessment_id) REFERENCES exam_assessments (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_question_notes (
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    question_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    assessment_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    body TEXT NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (workspace_id, user_id, question_key),
    KEY idx_exam_question_notes_recent (workspace_id, user_id, updated_at),
    CONSTRAINT fk_exam_question_notes_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_exam_question_notes_user FOREIGN KEY (user_id) REFERENCES iam_users (id),
    CONSTRAINT fk_exam_question_notes_assessment FOREIGN KEY (assessment_id) REFERENCES exam_assessments (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_question_reports (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    question_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    assessment_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    kind VARCHAR(16) NOT NULL,
    body VARCHAR(1000) NOT NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'open',
    resolution VARCHAR(1000) NULL,
    resolved_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    resolved_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_exam_question_reports_queue (workspace_id, status, created_at),
    KEY idx_exam_question_reports_user (workspace_id, user_id, created_at),
    CONSTRAINT fk_exam_question_reports_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_exam_question_reports_user FOREIGN KEY (user_id) REFERENCES iam_users (id),
    CONSTRAINT fk_exam_question_reports_resolver FOREIGN KEY (resolved_by_user_id) REFERENCES iam_users (id),
    CONSTRAINT fk_exam_question_reports_assessment FOREIGN KEY (assessment_id) REFERENCES exam_assessments (id),
    CONSTRAINT chk_exam_question_reports_kind CHECK (kind IN ('question', 'answer', 'explanation', 'other')),
    CONSTRAINT chk_exam_question_reports_status CHECK (status IN ('open', 'resolved', 'rejected'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
