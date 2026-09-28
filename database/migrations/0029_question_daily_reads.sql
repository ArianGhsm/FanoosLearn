-- fanoos:rollback-compatible=expand
--
-- Expand-only: one new table. The release before this one never reads or
-- writes it.
--
-- Question-bank export protection without slowing a real student down
-- (owner, 2026-09-28: the old pacing -- 6 reads, then one every 20 seconds --
-- stalled ordinary use). What stops a bulk export now is the number of
-- *different* questions one account may open in a day. This table is that
-- day's list per account: a question already on it is free to open again
-- (going back, reloading, the review), and a new one is admitted only while
-- the list is under the daily cap (ExamQuestionRateGuard::admit). Rows older
-- than yesterday are deleted as the account's next day starts.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS exam_question_daily_reads (
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    read_on DATE NOT NULL,
    question_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    first_read_at DATETIME(6) NOT NULL,
    PRIMARY KEY (user_id, read_on, question_key),
    CONSTRAINT fk_exam_question_daily_reads_user FOREIGN KEY (user_id) REFERENCES iam_users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
