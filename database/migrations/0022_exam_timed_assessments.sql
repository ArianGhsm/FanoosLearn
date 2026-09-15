-- fanoos:rollback-compatible=expand
--
-- Expand-only: both columns are nullable with no default write from the
-- release running before this migration, so it keeps working unchanged
-- against the new schema -- an old INSERT into exam_attempts that does not
-- list deadline_at gets NULL there automatically, and old code never reads
-- either column.
--
-- آزمون زمان‌دار (timed assessments).
--
-- A time limit is a property of the assessment (time_limit_minutes, how the
-- owner defines the paper) and a deadline is a property of the attempt
-- (deadline_at, computed once when that specific attempt starts and then
-- fixed -- resuming never recomputes it, same rule as the attempt's mode).
-- Enforcement lives in ExamService, not here; this only gives it somewhere
-- to read and write.

ALTER TABLE exam_access_policies
    ADD COLUMN time_limit_minutes SMALLINT UNSIGNED NULL AFTER max_attempts;

ALTER TABLE exam_attempts
    ADD COLUMN deadline_at DATETIME(6) NULL AFTER started_at;
