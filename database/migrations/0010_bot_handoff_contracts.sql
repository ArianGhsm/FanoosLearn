-- fanoos:rollback-compatible=expand
SET NAMES utf8mb4;

ALTER TABLE tenant_workspaces
    ADD COLUMN timezone_name VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'UTC' AFTER status;

CREATE TABLE IF NOT EXISTS protected_media_artifacts (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    job_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    issuance_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    resource_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    resource_version_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    artifact_ref VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    checksum_sha256 BINARY(32) NOT NULL,
    byte_size BIGINT UNSIGNED NOT NULL,
    mime VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    storage_key VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    state VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
    expires_at DATETIME(6) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    last_accessed_at DATETIME(6) NULL,
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_protected_media_artifact_job (job_id),
    UNIQUE KEY uq_protected_media_artifact_ref (artifact_ref),
    KEY idx_protected_media_artifact_expiry (state, expires_at),
    KEY idx_protected_media_artifact_user (workspace_id, user_id, created_at),
    CONSTRAINT fk_protected_media_artifact_job FOREIGN KEY (job_id) REFERENCES protected_media_jobs (id),
    CONSTRAINT fk_protected_media_artifact_issuance FOREIGN KEY (issuance_id, workspace_id) REFERENCES content_delivery_issuances (id, workspace_id),
    CONSTRAINT fk_protected_media_artifact_user FOREIGN KEY (user_id) REFERENCES iam_users (id),
    CONSTRAINT fk_protected_media_artifact_version FOREIGN KEY (resource_version_id, resource_id, workspace_id) REFERENCES content_resource_versions (id, resource_id, workspace_id),
    CONSTRAINT chk_protected_media_artifact_state CHECK (state IN ('active', 'expired', 'deleted')),
    CONSTRAINT chk_protected_media_artifact_mime CHECK (mime = 'application/pdf')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS release_update_snapshots (
    target_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    current_sha CHAR(40) CHARACTER SET ascii COLLATE ascii_bin NULL,
    candidate_sha CHAR(40) CHARACTER SET ascii COLLATE ascii_bin NULL,
    update_available BOOLEAN NULL,
    health_status VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'unknown',
    safe_check_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NULL,
    checked_at DATETIME(6) NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (target_id),
    CONSTRAINT fk_release_update_snapshot_target FOREIGN KEY (target_id) REFERENCES release_update_targets (id),
    CONSTRAINT chk_release_update_snapshot_health CHECK (health_status IN ('healthy', 'degraded', 'unknown')),
    CONSTRAINT chk_release_update_snapshot_sha CHECK (
        (current_sha IS NULL OR current_sha REGEXP '^[0-9a-f]{40}$') AND
        (candidate_sha IS NULL OR candidate_sha REGEXP '^[0-9a-f]{40}$')
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
