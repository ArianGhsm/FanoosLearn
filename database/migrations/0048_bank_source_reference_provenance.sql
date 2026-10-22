-- fanoos:rollback-compatible=expand
-- Record the actual official reference-list year and visible proxy basis for
-- source-only classifications. Historic rows remain NULL because their
-- reference year/basis must not be guessed retroactively.
SET NAMES utf8mb4;

ALTER TABLE bank_question_sources
    ADD COLUMN reference_year SMALLINT UNSIGNED NULL AFTER edition_id,
    ADD COLUMN classification_basis VARCHAR(40) NULL AFTER reference_year,
    ADD COLUMN classification_note VARCHAR(300) NULL AFTER classification_basis,
    ADD CONSTRAINT chk_bank_question_sources_classification_basis CHECK (
        classification_basis IS NULL OR classification_basis IN (
            'official_exam_year',
            'retrospective_proxy_1397_plus',
            'nearest_same_lineage_proxy',
            'updated_reference_proxy'
        )
    );
