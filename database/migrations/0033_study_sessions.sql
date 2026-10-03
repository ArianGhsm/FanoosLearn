-- fanoos:rollback-compatible=expand
--
-- Expand-only: one new table. The release before this one never reads or
-- writes it.
--
-- تایمر مطالعه: one row per finished focus block a student timed on the
-- site. Together with the time spent in exams it is the study time the
-- progress dashboard shows per day.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS study_sessions (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    minutes SMALLINT UNSIGNED NOT NULL,
    label VARCHAR(120) NULL,
    ended_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_study_sessions_user_day (workspace_id, user_id, ended_at),
    CONSTRAINT fk_study_sessions_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_study_sessions_user FOREIGN KEY (user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_study_sessions_minutes CHECK (minutes BETWEEN 1 AND 180)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
