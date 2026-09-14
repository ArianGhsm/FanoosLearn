-- fanoos:rollback-compatible=expand
-- Server-paced single-question exam reads: closes the bulk-export hole in
-- ExamService::startAttempt (docs/product/01_FRONT_DOOR.md's question-bank
-- protection follow-up). One row per user holds a continuous token bucket
-- (small burst, then a slow refill) so a script reading every question in a
-- few seconds is throttled while a student reading at their own pace never
-- notices it. Modeled on iam_login_attempts (apps/platform/src/Identity/AuthService.php):
-- one row per identity, read with FOR UPDATE and upserted with
-- ON DUPLICATE KEY UPDATE, so the guard is durable across restarts and
-- lives in the same database the rest of the platform audits against,
-- rather than an in-memory or cache-based limiter.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS exam_question_read_rate_guards (
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    tokens_remaining DECIMAL(8,4) NOT NULL,
    last_refill_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_exam_question_rate_guard_user FOREIGN KEY (user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_exam_question_rate_guard_tokens CHECK (tokens_remaining >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
