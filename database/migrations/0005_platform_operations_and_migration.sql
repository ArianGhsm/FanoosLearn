SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS platform_idempotency_keys (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    scope_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    actor_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    operation_key VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    idempotency_key VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_digest BINARY(32) NOT NULL,
    response_status SMALLINT UNSIGNED NULL,
    response_json JSON NULL,
    created_at DATETIME(6) NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_platform_idempotency_identity (scope_type, actor_id, operation_key, idempotency_key),
    KEY idx_platform_idempotency_expiry (expires_at),
    CONSTRAINT fk_platform_idempotency_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT chk_platform_idempotency_scope CHECK ((scope_type = 'platform' AND workspace_id IS NULL) OR (scope_type = 'workspace' AND workspace_id IS NOT NULL)),
    CONSTRAINT chk_platform_idempotency_expiry CHECK (expires_at > created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS outbox_events (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    scope_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    aggregate_type VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    aggregate_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    event_type VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    payload_json JSON NOT NULL,
    occurred_at DATETIME(6) NOT NULL,
    available_at DATETIME(6) NOT NULL,
    published_at DATETIME(6) NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_error_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NULL,
    PRIMARY KEY (id),
    KEY idx_outbox_events_claim (published_at, available_at, attempt_count),
    KEY idx_outbox_events_aggregate (aggregate_type, aggregate_id, occurred_at),
    CONSTRAINT fk_outbox_events_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT chk_outbox_events_scope CHECK ((scope_type = 'platform' AND workspace_id IS NULL) OR (scope_type = 'workspace' AND workspace_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_jobs (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    job_type VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    idempotency_key VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    payload_json JSON NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'queued',
    priority SMALLINT NOT NULL DEFAULT 0,
    available_at DATETIME(6) NOT NULL,
    lease_owner VARCHAR(160) NULL,
    leased_until DATETIME(6) NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts INT UNSIGNED NOT NULL DEFAULT 5,
    result_json JSON NULL,
    error_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    completed_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_job_jobs_type_idempotency (job_type, idempotency_key),
    KEY idx_job_jobs_claim (status, available_at, priority, leased_until),
    CONSTRAINT fk_job_jobs_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT chk_job_jobs_status CHECK (status IN ('queued', 'leased', 'completed', 'failed', 'dead')),
    CONSTRAINT chk_job_jobs_attempts CHECK (max_attempts > 0 AND attempt_count <= max_attempts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_events (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    scope_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    actor_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    actor_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    action VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    subject_type VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    subject_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    outcome VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    correlation_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    metadata_json JSON NOT NULL,
    occurred_at DATETIME(6) NOT NULL,
    retention_class VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'standard',
    PRIMARY KEY (id),
    KEY idx_audit_events_scope_time (scope_type, workspace_id, occurred_at),
    KEY idx_audit_events_subject (subject_type, subject_id, occurred_at),
    KEY idx_audit_events_actor (actor_type, actor_id, occurred_at),
    CONSTRAINT fk_audit_events_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT chk_audit_events_scope CHECK ((scope_type = 'platform' AND workspace_id IS NULL) OR (scope_type = 'workspace' AND workspace_id IS NOT NULL)),
    CONSTRAINT chk_audit_events_actor CHECK (actor_type IN ('user', 'service', 'system')),
    CONSTRAINT chk_audit_events_outcome CHECK (outcome IN ('success', 'denied', 'failure'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS migration_source_systems (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_key VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    description VARCHAR(255) NOT NULL,
    mode VARCHAR(24) NOT NULL DEFAULT 'read_only',
    created_at DATETIME(6) NOT NULL,
    retired_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_migration_source_systems_key (source_key),
    CONSTRAINT chk_migration_source_systems_mode CHECK (mode = 'read_only')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS migration_batches (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_system_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    batch_key VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    requested_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'planned',
    source_snapshot_digest BINARY(32) NULL,
    manifest_json JSON NOT NULL,
    started_at DATETIME(6) NULL,
    completed_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_migration_batches_source_key (source_system_id, batch_key),
    KEY idx_migration_batches_status (status, created_at),
    CONSTRAINT fk_migration_batches_source FOREIGN KEY (source_system_id) REFERENCES migration_source_systems (id),
    CONSTRAINT fk_migration_batches_requester FOREIGN KEY (requested_by_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_migration_batches_status CHECK (status IN ('planned', 'validating', 'running', 'completed', 'completed_with_rejects', 'failed', 'reversed')),
    CONSTRAINT chk_migration_batches_times CHECK (completed_at IS NULL OR started_at IS NULL OR completed_at >= started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS migration_legacy_id_mappings (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_system_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_entity_type VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_key_digest BINARY(32) NOT NULL,
    target_entity_type VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    target_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    first_batch_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    last_seen_batch_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    created_at DATETIME(6) NOT NULL,
    last_seen_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_migration_legacy_source_identity (source_system_id, source_entity_type, source_key_digest),
    KEY idx_migration_legacy_target (target_entity_type, target_id),
    CONSTRAINT fk_migration_legacy_source FOREIGN KEY (source_system_id) REFERENCES migration_source_systems (id),
    CONSTRAINT fk_migration_legacy_first_batch FOREIGN KEY (first_batch_id) REFERENCES migration_batches (id),
    CONSTRAINT fk_migration_legacy_last_batch FOREIGN KEY (last_seen_batch_id) REFERENCES migration_batches (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS migration_row_results (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    batch_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_entity_type VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_key_digest BINARY(32) NOT NULL,
    outcome VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    target_entity_type VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NULL,
    target_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    error_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NULL,
    detail_json JSON NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_migration_row_results_batch_source (batch_id, source_entity_type, source_key_digest),
    KEY idx_migration_row_results_outcome (batch_id, outcome),
    CONSTRAINT fk_migration_row_results_batch FOREIGN KEY (batch_id) REFERENCES migration_batches (id),
    CONSTRAINT chk_migration_row_results_outcome CHECK (outcome IN ('inserted', 'updated', 'unchanged', 'rejected', 'conflict'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS migration_rejects (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    batch_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_entity_type VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_key_digest BINARY(32) NULL,
    error_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    redacted_payload_json JSON NOT NULL,
    occurred_at DATETIME(6) NOT NULL,
    resolved_at DATETIME(6) NULL,
    resolution_note VARCHAR(500) NULL,
    PRIMARY KEY (id),
    KEY idx_migration_rejects_batch_unresolved (batch_id, resolved_at, error_code),
    CONSTRAINT fk_migration_rejects_batch FOREIGN KEY (batch_id) REFERENCES migration_batches (id),
    CONSTRAINT chk_migration_rejects_resolution CHECK (resolved_at IS NULL OR resolution_note IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
