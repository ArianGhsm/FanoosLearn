SET NAMES utf8mb4;

ALTER TABLE iam_sessions
    ADD COLUMN csrf_token_digest BINARY(32) NULL AFTER token_digest,
    ADD COLUMN selected_workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER user_id,
    ADD COLUMN client_json JSON NULL AFTER revoked_at,
    ADD CONSTRAINT fk_iam_sessions_selected_workspace
        FOREIGN KEY (selected_workspace_id) REFERENCES tenant_workspaces (id);

CREATE TABLE IF NOT EXISTS iam_login_attempts (
    identifier_digest BINARY(32) NOT NULL,
    source_digest BINARY(32) NOT NULL,
    failure_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME(6) NOT NULL,
    locked_until DATETIME(6) NULL,
    last_attempt_at DATETIME(6) NOT NULL,
    PRIMARY KEY (identifier_digest, source_digest),
    KEY idx_iam_login_attempts_expiry (locked_until, last_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_preferences (
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    in_app_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    email_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    push_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    snoozed_until DATETIME(6) NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (workspace_id, user_id),
    CONSTRAINT fk_notification_preferences_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_notification_preferences_user FOREIGN KEY (user_id) REFERENCES iam_users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS form_definitions (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    owner_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'draft',
    audience_type VARCHAR(24) NOT NULL DEFAULT 'workspace',
    allow_multiple BOOLEAN NOT NULL DEFAULT FALSE,
    current_version_no INT UNSIGNED NOT NULL DEFAULT 1,
    opens_at DATETIME(6) NULL,
    closes_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    archived_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_form_definitions_id_workspace (id, workspace_id),
    KEY idx_form_definitions_workspace_status (workspace_id, status, opens_at, closes_at),
    CONSTRAINT fk_form_definitions_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_form_definitions_owner FOREIGN KEY (owner_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_form_definitions_status CHECK (status IN ('draft', 'open', 'closed', 'archived')),
    CONSTRAINT chk_form_definitions_audience CHECK (audience_type IN ('workspace', 'members', 'public')),
    CONSTRAINT chk_form_definitions_dates CHECK (closes_at IS NULL OR opens_at IS NULL OR closes_at >= opens_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS form_versions (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    form_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    version_no INT UNSIGNED NOT NULL,
    schema_json JSON NOT NULL,
    schema_checksum BINARY(32) NOT NULL,
    created_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_form_versions_form_version (workspace_id, form_id, version_no),
    UNIQUE KEY uq_form_versions_id_workspace (id, workspace_id),
    CONSTRAINT fk_form_versions_form_workspace FOREIGN KEY (form_id, workspace_id) REFERENCES form_definitions (id, workspace_id),
    CONSTRAINT fk_form_versions_creator FOREIGN KEY (created_by_user_id) REFERENCES iam_users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS form_submissions (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    form_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    form_version_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    submitter_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    idempotency_key VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'submitted',
    answers_json JSON NOT NULL,
    submitted_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_form_submissions_idempotency (workspace_id, form_id, submitter_user_id, idempotency_key),
    UNIQUE KEY uq_form_submissions_id_workspace (id, workspace_id),
    KEY idx_form_submissions_form_time (workspace_id, form_id, submitted_at),
    CONSTRAINT fk_form_submissions_form_workspace FOREIGN KEY (form_id, workspace_id) REFERENCES form_definitions (id, workspace_id),
    CONSTRAINT fk_form_submissions_version_workspace FOREIGN KEY (form_version_id, workspace_id) REFERENCES form_versions (id, workspace_id),
    CONSTRAINT fk_form_submissions_user FOREIGN KEY (submitter_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_form_submissions_status CHECK (status IN ('submitted', 'withdrawn'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS search_documents (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    title VARCHAR(255) NOT NULL,
    normalized_text TEXT NOT NULL,
    route VARCHAR(512) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_search_documents_source (workspace_id, source_type, source_id),
    KEY idx_search_documents_workspace_status (workspace_id, status),
    FULLTEXT KEY ft_search_documents_text (title, normalized_text),
    CONSTRAINT fk_search_documents_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT chk_search_documents_source CHECK (source_type IN ('course', 'session', 'schedule', 'announcement', 'form', 'resource')),
    CONSTRAINT chk_search_documents_status CHECK (status IN ('active', 'hidden', 'deleted'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE commerce_products
    ADD COLUMN target_scope_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER workspace_id,
    ADD CONSTRAINT fk_commerce_products_target_scope_workspace
        FOREIGN KEY (target_scope_id, workspace_id) REFERENCES rbac_scopes (id, workspace_id);

ALTER TABLE commerce_orders
    ADD COLUMN public_token_digest BINARY(32) NULL AFTER buyer_user_id,
    ADD COLUMN paid_at DATETIME(6) NULL AFTER updated_at,
    ADD COLUMN failed_at DATETIME(6) NULL AFTER paid_at,
    ADD UNIQUE KEY uq_commerce_orders_public_token (public_token_digest);

ALTER TABLE commerce_payment_attempts
    ADD COLUMN idempotency_key VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER order_id,
    ADD COLUMN failure_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER status,
    ADD COLUMN provider_payload_json JSON NULL AFTER failure_code,
    ADD UNIQUE KEY uq_commerce_payment_attempt_idempotency (workspace_id, order_id, provider_key, idempotency_key),
    ADD UNIQUE KEY uq_commerce_payment_attempt_id_workspace (id, workspace_id);

ALTER TABLE entitlement_grants
    ADD COLUMN grant_key BINARY(32) NULL AFTER id,
    ADD COLUMN version BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER source_id,
    ADD UNIQUE KEY uq_entitlement_grants_grant_key (grant_key),
    ADD CONSTRAINT chk_entitlement_grants_version CHECK (version > 0);

CREATE TABLE IF NOT EXISTS commerce_reconciliation_runs (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    payment_attempt_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    requested_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    result VARCHAR(24) NOT NULL,
    detail_json JSON NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_commerce_reconciliation_workspace_time (workspace_id, created_at),
    CONSTRAINT fk_commerce_reconciliation_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_commerce_reconciliation_attempt_workspace FOREIGN KEY (payment_attempt_id, workspace_id) REFERENCES commerce_payment_attempts (id, workspace_id),
    CONSTRAINT fk_commerce_reconciliation_requester FOREIGN KEY (requested_by_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_commerce_reconciliation_result CHECK (result IN ('verified', 'failed', 'unchanged'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_access_policies (
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    resource_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    target_scope_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    requires_entitlement BOOLEAN NOT NULL DEFAULT TRUE,
    download_ttl_seconds INT UNSIGNED NOT NULL DEFAULT 300,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (workspace_id, resource_id),
    CONSTRAINT fk_content_access_policies_resource_workspace FOREIGN KEY (resource_id, workspace_id) REFERENCES content_resources (id, workspace_id),
    CONSTRAINT fk_content_access_policies_scope_workspace FOREIGN KEY (target_scope_id, workspace_id) REFERENCES rbac_scopes (id, workspace_id),
    CONSTRAINT chk_content_access_policies_ttl CHECK (download_ttl_seconds BETWEEN 30 AND 900)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS grade_import_batches (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    gradebook_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_checksum BINARY(32) NOT NULL,
    imported_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    row_count INT UNSIGNED NOT NULL,
    status VARCHAR(24) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_grade_import_batches_source (workspace_id, gradebook_id, source_checksum),
    CONSTRAINT fk_grade_import_batches_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_grade_import_batches_gradebook_workspace FOREIGN KEY (gradebook_id, workspace_id) REFERENCES grade_gradebooks (id, workspace_id),
    CONSTRAINT fk_grade_import_batches_importer FOREIGN KEY (imported_by_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_grade_import_batches_status CHECK (status IN ('validated', 'applied', 'rejected'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
