-- fanoos:rollback-compatible=expand
-- Disciplines (رشته) as a platform-wide list, and the profile a student fills
-- in when they sign up on the website.
--
-- Until now the only way to see an exam was to be a member of the one class
-- -- university + program + entry year -- that owned it, so a bank of past
-- exams meant for every medical student was visible to one cohort at one
-- university. A discipline owns a "library" workspace: every student who
-- signs up under that discipline becomes a member of it, from any university
-- and any entry year. Everything downstream (catalogue, attempts, pacing,
-- review, RBAC) is the ordinary workspace machinery, unchanged; class
-- workspaces stay for the exams that really are specific to one class.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS academic_disciplines (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    name VARCHAR(160) NOT NULL,
    library_workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_academic_disciplines_code (code),
    UNIQUE KEY uq_academic_disciplines_library (library_workspace_id),
    CONSTRAINT fk_academic_disciplines_library FOREIGN KEY (library_workspace_id) REFERENCES tenant_workspaces (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per person who signed up on the website. The bot's onboarding keeps
-- its own challenge/verified-phone rows; this is the durable profile. Phone
-- is deliberately absent: signing up does not require one, and a phone added
-- later is an iam_user_identifiers row like any other identifier.
CREATE TABLE IF NOT EXISTS iam_student_profiles (
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    discipline_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    institution_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    entry_year SMALLINT UNSIGNED NULL,
    entry_term VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL,
    course_type VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NULL,
    student_number VARCHAR(32) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (user_id),
    KEY idx_iam_student_profiles_discipline (discipline_id),
    CONSTRAINT fk_iam_student_profiles_user FOREIGN KEY (user_id) REFERENCES iam_users (id),
    CONSTRAINT fk_iam_student_profiles_discipline FOREIGN KEY (discipline_id) REFERENCES academic_disciplines (id),
    CONSTRAINT fk_iam_student_profiles_institution FOREIGN KEY (institution_id) REFERENCES directory_institutions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sign-up is unauthenticated, so it is throttled per source the same way
-- owner recovery is: one row per source, locked and written on every request.
CREATE TABLE IF NOT EXISTS iam_registration_rate_guards (
    source_digest BINARY(32) NOT NULL,
    window_started_at DATETIME(6) NOT NULL,
    registration_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (source_digest)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
