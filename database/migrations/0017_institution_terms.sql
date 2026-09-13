-- fanoos:rollback-compatible=expand
SET NAMES utf8mb4;

-- Term dates (docs/product/01_FRONT_DOOR.md #4) are configured per
-- institution, not re-typed per class: institution_terms is the canonical
-- source an owner edits once, and its dates are materialised into the
-- existing per-workspace academic_terms row(s) so every current read/join
-- on academic_terms (BotReadProjectionService, ExamService, ContentService)
-- keeps working unchanged. academic_terms stays keyed (workspace_id,
-- term_key) -- tenant isolation depends on workspace_id, and that is not
-- negotiable.
CREATE TABLE IF NOT EXISTS institution_terms (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    institution_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    term_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    name VARCHAR(160) NOT NULL,
    starts_on DATE NOT NULL,
    ends_on DATE NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'planned',
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    archived_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_institution_terms_institution_key (institution_id, term_key),
    KEY idx_institution_terms_institution_dates (institution_id, starts_on, ends_on),
    CONSTRAINT fk_institution_terms_institution FOREIGN KEY (institution_id) REFERENCES directory_institutions (id),
    CONSTRAINT chk_institution_terms_dates CHECK (ends_on >= starts_on),
    CONSTRAINT chk_institution_terms_status CHECK (status IN ('planned', 'active', 'closed', 'archived'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- origin distinguishes a row materialised from the institution's canonical
-- term (default going forward: 'inherited') from one a representative
-- deliberately overrode for their own class ('override') -- materialisation
-- must never clobber an override, so this is the flag it checks.
-- institution_term_id records which canonical row an inherited row came
-- from; it is informational once a row is overridden and is never itself
-- used to decide whether to skip a row (origin is).
--
-- Existing academic_terms rows (there are none created through any service
-- yet -- this table has had no writer until this change) default to
-- 'override' precisely so that if one somehow existed already, a later
-- institution-wide apply leaves it alone rather than silently rewriting a
-- row nobody asked it to touch.
ALTER TABLE academic_terms
    ADD COLUMN institution_term_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER term_key,
    ADD COLUMN origin VARCHAR(16) NOT NULL DEFAULT 'override' AFTER status,
    ADD CONSTRAINT fk_academic_terms_institution_term FOREIGN KEY (institution_term_id) REFERENCES institution_terms (id),
    ADD CONSTRAINT chk_academic_terms_origin CHECK (origin IN ('inherited', 'override'));
