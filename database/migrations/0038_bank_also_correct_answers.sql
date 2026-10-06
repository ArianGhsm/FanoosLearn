-- fanoos:rollback-compatible=expand
--
-- Expand-only: one nullable column. The release before this one never
-- reads it.
--
-- bank_official_answers.also_correct_positions -- when the official final
--   key accepts more than one option, the other accepted choices (1-based
--   positions, JSON list) beside choice_position. NULL for a single answer.
SET NAMES utf8mb4;

ALTER TABLE bank_official_answers
    ADD COLUMN also_correct_positions JSON NULL AFTER choice_position;
