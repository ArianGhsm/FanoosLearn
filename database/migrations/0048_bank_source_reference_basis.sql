-- fanoos:rollback-compatible=expand
-- Keep the source edition's actual reference year and official/proxy basis
-- beside its exact PDF-page citation. Historical rows remain NULL until
-- individually audited; NULL never claims that an edition was exam-year official.
SET NAMES utf8mb4;

ALTER TABLE bank_question_sources
    ADD COLUMN reference_year SMALLINT UNSIGNED NULL AFTER official_scope_note,
    ADD COLUMN classification_basis VARCHAR(48) NULL AFTER reference_year,
    ADD CONSTRAINT chk_bank_question_sources_reference_basis CHECK (
        (reference_year IS NULL AND classification_basis IS NULL)
        OR (
            reference_year IS NOT NULL
            AND classification_basis IN (
                'official_exam_year',
                'retrospective_proxy_1397_plus',
                'updated_reference_proxy',
                'nearest_verified_edition_proxy'
            )
        )
    );
