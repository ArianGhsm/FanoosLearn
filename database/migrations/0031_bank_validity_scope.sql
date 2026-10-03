-- fanoos:rollback-compatible=expand
--
-- Expand-only: two nullable columns. The release before this one never reads
-- them.
--
-- The official reference list for an exam year rarely names a whole book:
-- it names chapters ("تمام فصول به جز ۴، ۸، ۱۸") and pages. `scope` keeps
-- that text exactly as announced, until chapter trees are filled in and it
-- can be resolved into nodes; `evidence` records where the announcement was
-- found and how trustworthy it is.
SET NAMES utf8mb4;

ALTER TABLE bank_reference_validity
    ADD COLUMN scope VARCHAR(1000) NULL AFTER is_official,
    ADD COLUMN evidence VARCHAR(400) NULL AFTER scope;
