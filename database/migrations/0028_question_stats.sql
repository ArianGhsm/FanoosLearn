-- fanoos:rollback-compatible=expand
--
-- Expand-only: three new tables. The release before this one never reads or
-- writes them.
--
-- آمار هر سؤال و داشبورد پیشرفت. Every scored attempt already keeps its
-- per-question verdicts in exam_attempt_results.review_json, but asking "how
-- has this question gone for everyone" of that column means reading every
-- attempt ever made. These are counters kept as each attempt is scored
-- (QuestionStatsRecorder), keyed by the stable question id -- the same key
-- the mistakes review and آزمون دلخواه already treat as "the same question"
-- across exams. They are derived data: QuestionStatsRecorder::rebuild()
-- recomputes all of them from exam_attempt_results, which stays the record.
--
-- A question whose answer was revealed in a learning attempt (seen before
-- answering) is not counted: a right answer after seeing it says nothing about
-- the question or the student.
SET NAMES utf8mb4;

-- Everyone's answers to one question.
CREATE TABLE IF NOT EXISTS exam_question_stats (
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    question_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    answered_count INT UNSIGNED NOT NULL DEFAULT 0,
    correct_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (workspace_id, question_key),
    CONSTRAINT fk_exam_question_stats_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- How often each choice (authored index) was picked.
CREATE TABLE IF NOT EXISTS exam_question_choice_stats (
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    question_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    choice_index TINYINT UNSIGNED NOT NULL,
    picked_count INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (workspace_id, question_key, choice_index),
    CONSTRAINT fk_exam_question_choice_stats_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One student's record on one question, with the course and topic it was
-- asked under so the progress dashboard can group without reading exams.
CREATE TABLE IF NOT EXISTS exam_question_user_stats (
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    question_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    course_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    topic VARCHAR(200) NULL,
    answered_count INT UNSIGNED NOT NULL DEFAULT 0,
    correct_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_correct TINYINT(1) NOT NULL DEFAULT 0,
    last_answered_at DATETIME(6) NOT NULL,
    PRIMARY KEY (workspace_id, user_id, question_key),
    KEY idx_exam_question_user_stats_course (workspace_id, user_id, course_id),
    CONSTRAINT fk_exam_question_user_stats_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_exam_question_user_stats_user FOREIGN KEY (user_id) REFERENCES iam_users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
