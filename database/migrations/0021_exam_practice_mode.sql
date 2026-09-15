-- fanoos:rollback-compatible=expand
--
-- Expand-only: the release running before this migration only ever writes
-- 'assessment' or 'learning' into exam_attempts.mode, both of which remain
-- valid under the widened CHECK, so it keeps working unchanged against the
-- new schema -- which is what lets the updater apply it unattended and roll
-- back to the previous release without a reverse migration.
--
-- Practice mode.
--
-- A third attempt mode, between the two that already exist: like learning,
-- the explanation is reachable on demand rather than withheld until
-- submission; like assessment, the correctness of a choice is told
-- immediately rather than only after the fact. Widening the CHECK (rather
-- than adding a fourth acceptable value the app never uses) is the only
-- MySQL-native way to admit a new mode value, and requires dropping and
-- re-adding the named constraint -- there is no ALTER CHECK for a condition
-- change. Nothing else about the column or its rows changes.

ALTER TABLE exam_attempts
    DROP CHECK chk_exam_attempts_mode,
    ADD CONSTRAINT chk_exam_attempts_mode CHECK (mode IN ('assessment', 'learning', 'practice'));
