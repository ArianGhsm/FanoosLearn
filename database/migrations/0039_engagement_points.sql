-- fanoos:rollback-compatible=expand
--
-- Expand-only: three new tables. The release before this one never reads
-- them.
--
-- امتیاز روزانه و رتبه‌بندی (docs/product/08_ENGAGEMENT.md):
-- engagement_point_awards -- one row per question a student earned points
--   for on a day (local to the workspace), so the same question cannot be
--   farmed for points by retaking it the same day.
-- engagement_daily_points -- each student's total for a day; rankings for
--   the day, week and month are read from it.
-- engagement_coin_ledger -- coins earned (a day's goal reached) and later
--   spent; the balance is the sum. One row per (reason, reference), so an
--   award can be retried safely.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS engagement_point_awards (
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    day DATE NOT NULL,
    question_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    points SMALLINT UNSIGNED NOT NULL,
    difficulty VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NULL,
    awarded_at DATETIME(6) NOT NULL,
    PRIMARY KEY (workspace_id, user_id, day, question_key),
    CONSTRAINT fk_engagement_awards_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_engagement_awards_user FOREIGN KEY (user_id) REFERENCES iam_users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS engagement_daily_points (
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    day DATE NOT NULL,
    points INT UNSIGNED NOT NULL DEFAULT 0,
    correct_answers INT UNSIGNED NOT NULL DEFAULT 0,
    goal_reached_at DATETIME(6) NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (workspace_id, user_id, day),
    KEY idx_engagement_daily_rank (workspace_id, day, points),
    CONSTRAINT fk_engagement_daily_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_engagement_daily_user FOREIGN KEY (user_id) REFERENCES iam_users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS engagement_coin_ledger (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    delta INT NOT NULL,
    reason VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    reference VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_engagement_coin_event (workspace_id, user_id, reason, reference),
    KEY idx_engagement_coin_user (workspace_id, user_id),
    CONSTRAINT fk_engagement_coin_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_engagement_coin_user FOREIGN KEY (user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_engagement_coin_delta CHECK (delta <> 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
