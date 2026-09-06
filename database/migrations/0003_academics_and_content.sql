SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS academic_terms (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    term_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    name VARCHAR(160) NOT NULL,
    starts_on DATE NOT NULL,
    ends_on DATE NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'planned',
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    archived_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_academic_terms_workspace_key (workspace_id, term_key),
    UNIQUE KEY uq_academic_terms_id_workspace (id, workspace_id),
    KEY idx_academic_terms_workspace_dates (workspace_id, starts_on, ends_on),
    CONSTRAINT fk_academic_terms_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT chk_academic_terms_dates CHECK (ends_on >= starts_on),
    CONSTRAINT chk_academic_terms_status CHECK (status IN ('planned', 'active', 'closed', 'archived'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academic_courses (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    course_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    title VARCHAR(200) NOT NULL,
    credit_value DECIMAL(5,2) NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    archived_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_academic_courses_workspace_code (workspace_id, course_code),
    UNIQUE KEY uq_academic_courses_id_workspace (id, workspace_id),
    CONSTRAINT fk_academic_courses_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT chk_academic_courses_status CHECK (status IN ('active', 'archived')),
    CONSTRAINT chk_academic_courses_version CHECK (version > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academic_course_offerings (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    course_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    term_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    section_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'planned',
    capacity INT UNSIGNED NULL,
    version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    archived_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_academic_offerings_workspace_term_course_section (workspace_id, term_id, course_id, section_key),
    UNIQUE KEY uq_academic_offerings_id_workspace (id, workspace_id),
    KEY idx_academic_offerings_workspace_status (workspace_id, status),
    CONSTRAINT fk_academic_offerings_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_academic_offerings_course_workspace FOREIGN KEY (course_id, workspace_id) REFERENCES academic_courses (id, workspace_id),
    CONSTRAINT fk_academic_offerings_term_workspace FOREIGN KEY (term_id, workspace_id) REFERENCES academic_terms (id, workspace_id),
    CONSTRAINT chk_academic_offerings_status CHECK (status IN ('planned', 'open', 'active', 'closed', 'archived')),
    CONSTRAINT chk_academic_offerings_version CHECK (version > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academic_course_sessions (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    offering_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    sequence_no INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    starts_at DATETIME(6) NULL,
    ends_at DATETIME(6) NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'scheduled',
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    archived_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_academic_sessions_offering_sequence (workspace_id, offering_id, sequence_no),
    UNIQUE KEY uq_academic_sessions_id_workspace (id, workspace_id),
    CONSTRAINT fk_academic_sessions_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_academic_sessions_offering_workspace FOREIGN KEY (offering_id, workspace_id) REFERENCES academic_course_offerings (id, workspace_id),
    CONSTRAINT chk_academic_sessions_times CHECK (ends_at IS NULL OR starts_at IS NULL OR ends_at >= starts_at),
    CONSTRAINT chk_academic_sessions_status CHECK (status IN ('scheduled', 'completed', 'cancelled', 'archived'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academic_enrollments (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    offering_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    membership_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    enrolled_at DATETIME(6) NOT NULL,
    ended_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_academic_enrollments_offering_membership (offering_id, membership_id),
    UNIQUE KEY uq_academic_enrollments_id_workspace (id, workspace_id),
    KEY idx_academic_enrollments_workspace_status (workspace_id, status),
    CONSTRAINT fk_academic_enrollments_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_academic_enrollments_offering_workspace FOREIGN KEY (offering_id, workspace_id) REFERENCES academic_course_offerings (id, workspace_id),
    CONSTRAINT fk_academic_enrollments_membership_workspace FOREIGN KEY (membership_id, workspace_id) REFERENCES tenant_workspace_memberships (id, workspace_id),
    CONSTRAINT chk_academic_enrollments_status CHECK (status IN ('active', 'completed', 'withdrawn')),
    CONSTRAINT chk_academic_enrollments_dates CHECK (ended_at IS NULL OR ended_at >= enrolled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_resource_types (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    type_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    name VARCHAR(160) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_content_resource_types_key (type_key),
    CONSTRAINT chk_content_resource_types_status CHECK (status IN ('active', 'archived'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_objects (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    storage_adapter VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    storage_key VARCHAR(512) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    detected_mime VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NULL,
    byte_size BIGINT UNSIGNED NULL,
    checksum_sha256 BINARY(32) NULL,
    classification VARCHAR(24) NOT NULL DEFAULT 'private',
    status VARCHAR(24) NOT NULL DEFAULT 'pending',
    created_at DATETIME(6) NOT NULL,
    verified_at DATETIME(6) NULL,
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_content_objects_storage_key (storage_adapter, storage_key),
    UNIQUE KEY uq_content_objects_id_workspace (id, workspace_id),
    KEY idx_content_objects_workspace_status (workspace_id, status),
    CONSTRAINT fk_content_objects_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT chk_content_objects_classification CHECK (classification IN ('private', 'protected', 'public')),
    CONSTRAINT chk_content_objects_status CHECK (status IN ('pending', 'verified', 'failed', 'deleted')),
    CONSTRAINT chk_content_objects_verified CHECK (status <> 'verified' OR (verified_at IS NOT NULL AND byte_size IS NOT NULL AND checksum_sha256 IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_resources (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    resource_type_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    owner_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    visibility VARCHAR(24) NOT NULL DEFAULT 'private',
    lifecycle_status VARCHAR(24) NOT NULL DEFAULT 'draft',
    current_version_no INT UNSIGNED NOT NULL DEFAULT 0,
    version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    archived_at DATETIME(6) NULL,
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_content_resources_id_workspace (id, workspace_id),
    KEY idx_content_resources_workspace_type_status (workspace_id, resource_type_id, lifecycle_status),
    KEY idx_content_resources_owner (workspace_id, owner_user_id),
    CONSTRAINT fk_content_resources_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_content_resources_type FOREIGN KEY (resource_type_id) REFERENCES content_resource_types (id),
    CONSTRAINT fk_content_resources_owner FOREIGN KEY (owner_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_content_resources_visibility CHECK (visibility IN ('private', 'workspace', 'restricted')),
    CONSTRAINT chk_content_resources_lifecycle CHECK (lifecycle_status IN ('draft', 'review', 'published', 'archived', 'deleted')),
    CONSTRAINT chk_content_resources_versions CHECK (current_version_no >= 0 AND version > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_resource_versions (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    resource_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    version_no INT UNSIGNED NOT NULL,
    object_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    created_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_kind VARCHAR(32) NOT NULL DEFAULT 'authored',
    source_reference VARCHAR(255) NULL,
    content_json JSON NULL,
    checksum_sha256 BINARY(32) NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'draft',
    created_at DATETIME(6) NOT NULL,
    reviewed_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_content_resource_versions_resource_version (resource_id, version_no),
    UNIQUE KEY uq_content_resource_versions_id_workspace (id, workspace_id),
    UNIQUE KEY uq_content_resource_versions_id_resource_workspace (id, resource_id, workspace_id),
    KEY idx_content_resource_versions_workspace_status (workspace_id, status),
    CONSTRAINT fk_content_versions_resource_workspace FOREIGN KEY (resource_id, workspace_id) REFERENCES content_resources (id, workspace_id),
    CONSTRAINT fk_content_versions_object_workspace FOREIGN KEY (object_id, workspace_id) REFERENCES content_objects (id, workspace_id),
    CONSTRAINT fk_content_versions_creator FOREIGN KEY (created_by_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_content_versions_number CHECK (version_no > 0),
    CONSTRAINT chk_content_versions_source CHECK (source_kind IN ('authored', 'uploaded', 'imported', 'forked', 'generated')),
    CONSTRAINT chk_content_versions_status CHECK (status IN ('draft', 'review', 'approved', 'rejected', 'withdrawn')),
    CONSTRAINT chk_content_versions_payload CHECK (object_id IS NOT NULL OR content_json IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_resource_bindings (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    resource_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    binding_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    target_entity_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    course_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    offering_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    session_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    position_no INT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL,
    archived_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_content_bindings_target (workspace_id, resource_id, binding_type, target_entity_id),
    KEY idx_content_bindings_course (workspace_id, course_id),
    KEY idx_content_bindings_offering (workspace_id, offering_id),
    KEY idx_content_bindings_session (workspace_id, session_id),
    CONSTRAINT fk_content_bindings_resource_workspace FOREIGN KEY (resource_id, workspace_id) REFERENCES content_resources (id, workspace_id),
    CONSTRAINT fk_content_bindings_course_workspace FOREIGN KEY (course_id, workspace_id) REFERENCES academic_courses (id, workspace_id),
    CONSTRAINT fk_content_bindings_offering_workspace FOREIGN KEY (offering_id, workspace_id) REFERENCES academic_course_offerings (id, workspace_id),
    CONSTRAINT fk_content_bindings_session_workspace FOREIGN KEY (session_id, workspace_id) REFERENCES academic_course_sessions (id, workspace_id),
    CONSTRAINT chk_content_bindings_target CHECK (
        (binding_type = 'workspace' AND target_entity_id = workspace_id AND course_id IS NULL AND offering_id IS NULL AND session_id IS NULL)
        OR (binding_type = 'course' AND target_entity_id = course_id AND course_id IS NOT NULL AND offering_id IS NULL AND session_id IS NULL)
        OR (binding_type = 'course_offering' AND target_entity_id = offering_id AND course_id IS NULL AND offering_id IS NOT NULL AND session_id IS NULL)
        OR (binding_type = 'course_session' AND target_entity_id = session_id AND course_id IS NULL AND offering_id IS NULL AND session_id IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_resource_publications (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    resource_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    resource_version_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    target_workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    visibility VARCHAR(24) NOT NULL DEFAULT 'workspace',
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    published_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    published_at DATETIME(6) NOT NULL,
    expires_at DATETIME(6) NULL,
    revoked_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_content_publications_resource_target (resource_version_id, target_workspace_id),
    KEY idx_content_publications_target_active (target_workspace_id, status, expires_at),
    CONSTRAINT fk_content_publications_source_workspace FOREIGN KEY (source_workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_content_publications_target_workspace FOREIGN KEY (target_workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_content_publications_resource_workspace FOREIGN KEY (resource_id, source_workspace_id) REFERENCES content_resources (id, workspace_id),
    CONSTRAINT fk_content_publications_version_resource_workspace FOREIGN KEY (resource_version_id, resource_id, source_workspace_id) REFERENCES content_resource_versions (id, resource_id, workspace_id),
    CONSTRAINT fk_content_publications_publisher FOREIGN KEY (published_by_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_content_publications_visibility CHECK (visibility IN ('workspace', 'restricted')),
    CONSTRAINT chk_content_publications_status CHECK (status IN ('active', 'revoked', 'expired')),
    CONSTRAINT chk_content_publications_expiry CHECK (expires_at IS NULL OR expires_at > published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
