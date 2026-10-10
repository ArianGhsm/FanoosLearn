-- fanoos:rollback-compatible=expand
--
-- Expand-only: one nullable column and one widened CHECK. The release
-- before this one never reads the column and never writes the newly allowed
-- answer shape.
--
-- bank_questions.booklet_source -- the reference a printed exam booklet names
--   beside a question («منبع درج‌شده در دفترچه: کارانزا ۲۰۱۹»), kept as written.
--   A hint for chapter classification only; it is never a source
--   (bank_question_sources holds those, with page evidence).
--
-- chk_bank_official_answers_choice is widened: a disputed answer may have no
--   choice (a paper whose key is not known yet -- board 1403). Such questions
--   are in the bank and left out of every scored exam (BankPublisher keeps
--   only preliminary, final and amended answers with a choice).
SET NAMES utf8mb4;

ALTER TABLE bank_questions
    ADD COLUMN booklet_source VARCHAR(300) NULL AFTER stem_image;

ALTER TABLE bank_official_answers
    DROP CHECK chk_bank_official_answers_choice,
    ADD CONSTRAINT chk_bank_official_answers_choice CHECK (
        (status = 'voided' AND choice_position IS NULL)
        OR (status <> 'voided' AND (choice_position IS NOT NULL OR status = 'disputed'))
    );
