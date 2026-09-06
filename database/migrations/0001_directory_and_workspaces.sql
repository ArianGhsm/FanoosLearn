SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS directory_countries (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    code CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    name VARCHAR(160) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    archived_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_directory_countries_code (code),
    CONSTRAINT chk_directory_countries_status CHECK (status IN ('active', 'archived'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS directory_provinces (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    country_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    name VARCHAR(160) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    archived_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_directory_provinces_country_code (country_id, code),
    CONSTRAINT fk_directory_provinces_country FOREIGN KEY (country_id) REFERENCES directory_countries (id),
    CONSTRAINT chk_directory_provinces_status CHECK (status IN ('active', 'archived'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS directory_cities (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    province_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    code VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    name VARCHAR(160) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    archived_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_directory_cities_province_code (province_id, code),
    CONSTRAINT fk_directory_cities_province FOREIGN KEY (province_id) REFERENCES directory_provinces (id),
    CONSTRAINT chk_directory_cities_status CHECK (status IN ('active', 'archived'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS directory_institutions (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    city_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    slug VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    name VARCHAR(200) NOT NULL,
    institution_type VARCHAR(32) NOT NULL DEFAULT 'university',
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    archived_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_directory_institutions_slug (slug),
    KEY idx_directory_institutions_city (city_id, status),
    CONSTRAINT fk_directory_institutions_city FOREIGN KEY (city_id) REFERENCES directory_cities (id),
    CONSTRAINT chk_directory_institutions_status CHECK (status IN ('active', 'archived'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS directory_campuses (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    institution_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    name VARCHAR(200) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    archived_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_directory_campuses_institution_code (institution_id, code),
    UNIQUE KEY uq_directory_campuses_id_institution (id, institution_id),
    CONSTRAINT fk_directory_campuses_institution FOREIGN KEY (institution_id) REFERENCES directory_institutions (id),
    CONSTRAINT chk_directory_campuses_status CHECK (status IN ('active', 'archived'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS directory_faculties (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    institution_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    campus_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    name VARCHAR(200) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    archived_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_directory_faculties_institution_code (institution_id, code),
    UNIQUE KEY uq_directory_faculties_id_institution (id, institution_id),
    CONSTRAINT fk_directory_faculties_institution FOREIGN KEY (institution_id) REFERENCES directory_institutions (id),
    CONSTRAINT fk_directory_faculties_campus FOREIGN KEY (campus_id, institution_id) REFERENCES directory_campuses (id, institution_id),
    CONSTRAINT chk_directory_faculties_status CHECK (status IN ('active', 'archived'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS directory_departments (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    faculty_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    name VARCHAR(200) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    archived_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_directory_departments_faculty_code (faculty_id, code),
    UNIQUE KEY uq_directory_departments_id_faculty (id, faculty_id),
    CONSTRAINT fk_directory_departments_faculty FOREIGN KEY (faculty_id) REFERENCES directory_faculties (id),
    CONSTRAINT chk_directory_departments_status CHECK (status IN ('active', 'archived'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS directory_programs (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    faculty_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    department_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    name VARCHAR(200) NOT NULL,
    degree_level VARCHAR(48) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    archived_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_directory_programs_faculty_code (faculty_id, code),
    UNIQUE KEY uq_directory_programs_id_faculty (id, faculty_id),
    CONSTRAINT fk_directory_programs_faculty FOREIGN KEY (faculty_id) REFERENCES directory_faculties (id),
    CONSTRAINT fk_directory_programs_department FOREIGN KEY (department_id, faculty_id) REFERENCES directory_departments (id, faculty_id),
    CONSTRAINT chk_directory_programs_status CHECK (status IN ('active', 'archived'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS directory_cohorts (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    program_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    entry_year SMALLINT UNSIGNED NOT NULL,
    label VARCHAR(160) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    starts_on DATE NULL,
    ends_on DATE NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    archived_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_directory_cohorts_program_year (program_id, entry_year),
    CONSTRAINT fk_directory_cohorts_program FOREIGN KEY (program_id) REFERENCES directory_programs (id),
    CONSTRAINT chk_directory_cohorts_status CHECK (status IN ('active', 'archived')),
    CONSTRAINT chk_directory_cohorts_dates CHECK (ends_on IS NULL OR starts_on IS NULL OR ends_on >= starts_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_workspaces (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    cohort_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    slug VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    name VARCHAR(200) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    settings_json JSON NOT NULL,
    version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    archived_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tenant_workspaces_slug (slug),
    UNIQUE KEY uq_tenant_workspaces_id_cohort (id, cohort_id),
    KEY idx_tenant_workspaces_cohort_status (cohort_id, status),
    CONSTRAINT fk_tenant_workspaces_cohort FOREIGN KEY (cohort_id) REFERENCES directory_cohorts (id),
    CONSTRAINT chk_tenant_workspaces_status CHECK (status IN ('active', 'suspended', 'archived')),
    CONSTRAINT chk_tenant_workspaces_version CHECK (version > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
