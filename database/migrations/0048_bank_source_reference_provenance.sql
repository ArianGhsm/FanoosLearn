-- fanoos:rollback-compatible=expand
-- Record the actual official reference-list year and visible basis for
-- source-only classifications. Historical rows remain NULL until individually
-- audited; no reference year or basis is inferred retroactively.
SET NAMES utf8mb4;

ALTER TABLE bank_question_sources
    ADD COLUMN reference_year SMALLINT UNSIGNED NULL AFTER edition_id,
    ADD COLUMN classification_basis VARCHAR(48) NULL AFTER reference_year,
    ADD COLUMN classification_note VARCHAR(300) NULL AFTER classification_basis,
    ADD CONSTRAINT chk_bank_question_sources_classification_provenance CHECK (
        (
            reference_year IS NULL
            AND classification_basis IS NULL
            AND classification_note IS NULL
        )
        OR (
            reference_year IS NOT NULL
            AND classification_basis IS NOT NULL
            AND classification_basis IN (
            'official_exam_year',
            'retrospective_proxy_1397_plus',
            'nearest_same_lineage_proxy',
            'updated_reference_proxy'
            )
            AND (
                (classification_basis = 'official_exam_year' AND classification_note IS NULL)
                OR (
                    classification_basis <> 'official_exam_year'
                    AND classification_note IS NOT NULL
                    AND CHAR_LENGTH(TRIM(classification_note)) > 0
                )
            )
        )
    );
