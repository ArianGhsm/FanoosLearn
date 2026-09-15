-- Learning mode, and the retirement of the stored CSRF digest.
--
-- Learning mode lets a student reveal the answer and explanation for the
-- question they are on, before submitting, the way the legacy exam did. It
-- is a property of the attempt rather than of the assessment, because the
-- same paper is worth both practising against and sitting properly, and the
-- student decides which they are doing when they start.
--
-- Recording which positions were revealed is what keeps the resulting score
-- honest: a revealed question is still answered and still scored, but the
-- report can say plainly that the answer was seen first. Without this the
-- two kinds of attempt would be indistinguishable afterwards.

ALTER TABLE exam_attempts
    ADD COLUMN mode VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'assessment' AFTER status,
    ADD COLUMN revealed_json JSON NULL AFTER answers_json,
    ADD CONSTRAINT chk_exam_attempts_mode CHECK (mode IN ('assessment', 'learning'));
