-- fanoos:rollback-compatible=expand
--
-- Expand-only: two new tables. The release before this one never reads or
-- writes them.
--
-- exam_schedules -- تقویم آزمون‌ها: a published exam may be given a window.
--   Before it opens nobody can start it; after it closes it can still be
--   practised, but not sat in exam mode, so its ranking stays the ranking
--   of those who sat it in time.
-- study_plans, study_plan_days -- برنامه‌ی مطالعه: a student's day-by-day
--   plan up to their exam date, generated from the bank, with a tick per day.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS exam_schedules (
    assessment_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    opens_at DATETIME(6) NOT NULL,
    closes_at DATETIME(6) NOT NULL,
    note VARCHAR(300) NULL,
    created_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (assessment_id),
    KEY idx_exam_schedules_window (workspace_id, opens_at),
    CONSTRAINT fk_exam_schedules_assessment FOREIGN KEY (assessment_id) REFERENCES exam_assessments (id),
    CONSTRAINT fk_exam_schedules_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_exam_schedules_creator FOREIGN KEY (created_by_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_exam_schedules_window CHECK (closes_at > opens_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS study_plans (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    exam_date DATE NOT NULL,
    start_date DATE NOT NULL,
    days_per_week TINYINT UNSIGNED NOT NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'active',
    created_at DATETIME(6) NOT NULL,
    archived_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    KEY idx_study_plans_user (workspace_id, user_id, status),
    CONSTRAINT fk_study_plans_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_study_plans_user FOREIGN KEY (user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_study_plans_status CHECK (status IN ('active', 'archived')),
    CONSTRAINT chk_study_plans_days CHECK (days_per_week BETWEEN 3 AND 7)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS study_plan_days (
    plan_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    day_no SMALLINT UNSIGNED NOT NULL,
    plan_date DATE NOT NULL,
    items_json JSON NOT NULL,
    done_at DATETIME(6) NULL,
    PRIMARY KEY (plan_id, day_no),
    KEY idx_study_plan_days_date (plan_id, plan_date),
    CONSTRAINT fk_study_plan_days_plan FOREIGN KEY (plan_id) REFERENCES study_plans (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
