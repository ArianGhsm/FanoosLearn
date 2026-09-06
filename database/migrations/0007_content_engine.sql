SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS content_resource_metadata (
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    resource_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    term_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    course_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    session_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    topic VARCHAR(200) NULL,
    professor_name VARCHAR(200) NULL,
    format_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'standard',
    access_level VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'workspace',
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (workspace_id, resource_id),
    KEY idx_content_metadata_library (workspace_id, course_id, term_id, format_key),
    CONSTRAINT fk_content_metadata_resource_workspace FOREIGN KEY (resource_id, workspace_id) REFERENCES content_resources (id, workspace_id),
    CONSTRAINT fk_content_metadata_term_workspace FOREIGN KEY (term_id, workspace_id) REFERENCES academic_terms (id, workspace_id),
    CONSTRAINT fk_content_metadata_course_workspace FOREIGN KEY (course_id, workspace_id) REFERENCES academic_courses (id, workspace_id),
    CONSTRAINT fk_content_metadata_session_workspace FOREIGN KEY (session_id, workspace_id) REFERENCES academic_course_sessions (id, workspace_id),
    CONSTRAINT chk_content_metadata_access CHECK (access_level IN ('private', 'workspace', 'entitled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_version_reviews (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    resource_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    resource_version_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    reviewer_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    decision VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    note VARCHAR(1000) NULL,
    decided_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_content_review_decision (resource_version_id, reviewer_user_id, decision),
    KEY idx_content_reviews_workspace_time (workspace_id, decided_at),
    CONSTRAINT fk_content_reviews_version_resource_workspace FOREIGN KEY (resource_version_id, resource_id, workspace_id) REFERENCES content_resource_versions (id, resource_id, workspace_id),
    CONSTRAINT fk_content_reviews_reviewer FOREIGN KEY (reviewer_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_content_reviews_decision CHECK (decision IN ('approved', 'rejected'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_derivations (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_resource_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_version_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    output_resource_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    output_version_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    transformation_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    producer VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_content_derivation_output (output_version_id),
    KEY idx_content_derivation_source (workspace_id, source_resource_id, source_version_id),
    CONSTRAINT fk_content_derivation_source_version FOREIGN KEY (source_version_id, source_resource_id, workspace_id) REFERENCES content_resource_versions (id, resource_id, workspace_id),
    CONSTRAINT fk_content_derivation_output_version FOREIGN KEY (output_version_id, output_resource_id, workspace_id) REFERENCES content_resource_versions (id, resource_id, workspace_id),
    CONSTRAINT fk_content_derivation_creator FOREIGN KEY (created_by_user_id) REFERENCES iam_users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_import_batches (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_system_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    import_key VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    manifest_digest BINARY(32) NOT NULL,
    status VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    item_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_count INT UNSIGNED NOT NULL DEFAULT 0,
    duplicate_count INT UNSIGNED NOT NULL DEFAULT 0,
    imported_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    started_at DATETIME(6) NOT NULL,
    completed_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_content_import_batch_identity (id, workspace_id, source_system_id),
    UNIQUE KEY uq_content_import_batch_key (workspace_id, source_system_id, import_key),
    KEY idx_content_import_batch_status (workspace_id, status, started_at),
    CONSTRAINT fk_content_import_batch_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_content_import_batch_source FOREIGN KEY (source_system_id) REFERENCES migration_source_systems (id),
    CONSTRAINT fk_content_import_batch_actor FOREIGN KEY (imported_by_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_content_import_batch_status CHECK (status IN ('running', 'completed', 'failed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_import_items (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_system_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    batch_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_item_digest BINARY(32) NOT NULL,
    content_digest BINARY(32) NOT NULL,
    resource_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    resource_version_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    outcome VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    imported_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_content_import_source_item (workspace_id, source_system_id, source_item_digest),
    KEY idx_content_import_items_batch (batch_id, outcome),
    CONSTRAINT fk_content_import_item_batch_scope FOREIGN KEY (batch_id, workspace_id, source_system_id) REFERENCES content_import_batches (id, workspace_id, source_system_id),
    CONSTRAINT fk_content_import_item_version FOREIGN KEY (resource_version_id, resource_id, workspace_id) REFERENCES content_resource_versions (id, resource_id, workspace_id),
    CONSTRAINT chk_content_import_item_outcome CHECK (outcome IN ('created', 'updated', 'duplicate'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_import_results (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_system_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    batch_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_item_digest BINARY(32) NOT NULL,
    resource_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    resource_version_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    outcome VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_content_import_result_item (batch_id, source_item_digest),
    KEY idx_content_import_results_outcome (batch_id, outcome),
    CONSTRAINT fk_content_import_result_batch_scope FOREIGN KEY (batch_id, workspace_id, source_system_id) REFERENCES content_import_batches (id, workspace_id, source_system_id),
    CONSTRAINT fk_content_import_result_version FOREIGN KEY (resource_version_id, resource_id, workspace_id) REFERENCES content_resource_versions (id, resource_id, workspace_id),
    CONSTRAINT chk_content_import_result_outcome CHECK (outcome IN ('created', 'updated', 'duplicate'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_delivery_issuances (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    resource_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    resource_version_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    object_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    channel VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    token_digest BINARY(32) NOT NULL,
    watermark_fingerprint BINARY(32) NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    issued_at DATETIME(6) NOT NULL,
    last_accessed_at DATETIME(6) NULL,
    revoked_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_content_delivery_issuance_workspace (id, workspace_id),
    UNIQUE KEY uq_content_delivery_token (token_digest),
    KEY idx_content_delivery_user_time (workspace_id, user_id, issued_at),
    CONSTRAINT fk_content_delivery_version FOREIGN KEY (resource_version_id, resource_id, workspace_id) REFERENCES content_resource_versions (id, resource_id, workspace_id),
    CONSTRAINT fk_content_delivery_object_workspace FOREIGN KEY (object_id, workspace_id) REFERENCES content_objects (id, workspace_id),
    CONSTRAINT fk_content_delivery_user FOREIGN KEY (user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_content_delivery_channel CHECK (channel IN ('web', 'api', 'telegram', 'bale'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_delivery_events (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    issuance_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    event_type VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    occurred_at DATETIME(6) NOT NULL,
    metadata_json JSON NOT NULL,
    PRIMARY KEY (id),
    KEY idx_content_delivery_events_issuance (issuance_id, occurred_at),
    CONSTRAINT fk_content_delivery_event_issuance_workspace FOREIGN KEY (issuance_id, workspace_id) REFERENCES content_delivery_issuances (id, workspace_id),
    CONSTRAINT chk_content_delivery_event_type CHECK (event_type IN ('issued', 'served', 'denied', 'revoked'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_assessment_metadata (
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    assessment_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    assessment_kind VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    course_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    source_resource_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (workspace_id, assessment_id),
    KEY idx_exam_metadata_catalog (workspace_id, course_id, assessment_kind),
    CONSTRAINT fk_exam_metadata_assessment_workspace FOREIGN KEY (assessment_id, workspace_id) REFERENCES exam_assessments (id, workspace_id),
    CONSTRAINT fk_exam_metadata_course_workspace FOREIGN KEY (course_id, workspace_id) REFERENCES academic_courses (id, workspace_id),
    CONSTRAINT fk_exam_metadata_resource_workspace FOREIGN KEY (source_resource_id, workspace_id) REFERENCES content_resources (id, workspace_id),
    CONSTRAINT chk_exam_metadata_kind CHECK (assessment_kind IN ('practice', 'mock_exam', 'past_exam'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_access_policies (
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    assessment_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    target_scope_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    requires_entitlement BOOLEAN NOT NULL DEFAULT FALSE,
    max_attempts INT UNSIGNED NOT NULL DEFAULT 3,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (workspace_id, assessment_id),
    CONSTRAINT fk_exam_access_assessment_workspace FOREIGN KEY (assessment_id, workspace_id) REFERENCES exam_assessments (id, workspace_id),
    CONSTRAINT fk_exam_access_scope_workspace FOREIGN KEY (target_scope_id, workspace_id) REFERENCES rbac_scopes (id, workspace_id),
    CONSTRAINT chk_exam_access_attempts CHECK (max_attempts BETWEEN 1 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_version_reviews (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    assessment_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    assessment_version_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    reviewer_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    decision VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    note VARCHAR(1000) NULL,
    decided_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_exam_review_decision (assessment_version_id, reviewer_user_id, decision),
    CONSTRAINT fk_exam_review_version_assessment FOREIGN KEY (assessment_version_id, assessment_id, workspace_id) REFERENCES exam_assessment_versions (id, assessment_id, workspace_id),
    CONSTRAINT fk_exam_review_reviewer FOREIGN KEY (reviewer_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_exam_review_decision CHECK (decision IN ('approved', 'rejected'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_version_states (
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    assessment_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    assessment_version_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft',
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (workspace_id, assessment_version_id),
    CONSTRAINT fk_exam_state_version_assessment FOREIGN KEY (assessment_version_id, assessment_id, workspace_id) REFERENCES exam_assessment_versions (id, assessment_id, workspace_id),
    CONSTRAINT chk_exam_version_state CHECK (status IN ('draft', 'review', 'approved', 'rejected'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_attempt_results (
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    attempt_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    correct_count INT UNSIGNED NOT NULL,
    question_count INT UNSIGNED NOT NULL,
    score_basis_points INT UNSIGNED NOT NULL,
    review_json JSON NOT NULL,
    computed_at DATETIME(6) NOT NULL,
    PRIMARY KEY (workspace_id, attempt_id),
    CONSTRAINT fk_exam_result_attempt_workspace FOREIGN KEY (attempt_id, workspace_id) REFERENCES exam_attempts (id, workspace_id),
    CONSTRAINT chk_exam_result_score CHECK (correct_count <= question_count AND score_basis_points <= 10000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
