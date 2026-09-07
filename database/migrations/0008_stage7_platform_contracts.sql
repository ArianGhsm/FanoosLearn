-- fanoos:rollback-compatible=expand
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS integration_service_identities (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    service_key VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    service_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    allowed_actions_json JSON NOT NULL,
    status VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_integration_service_key (service_key),
    CONSTRAINT chk_integration_service_type CHECK (service_type IN ('telegram_adapter', 'bale_adapter', 'protected_media_worker', 'notification_worker', 'deployment_updater')),
    CONSTRAINT chk_integration_service_status CHECK (status IN ('active', 'suspended', 'retired'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS integration_service_keys (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    service_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    key_id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    secret_env_name VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
    valid_from DATETIME(6) NOT NULL,
    valid_until DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    revoked_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_integration_service_key_id (key_id),
    KEY idx_integration_service_keys_service (service_id, status, valid_from, valid_until),
    CONSTRAINT fk_integration_service_keys_service FOREIGN KEY (service_id) REFERENCES integration_service_identities (id),
    CONSTRAINT chk_integration_service_key_status CHECK (status IN ('active', 'retiring', 'revoked')),
    CONSTRAINT chk_integration_service_key_window CHECK (valid_until IS NULL OR valid_until > valid_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS integration_service_nonces (
    service_key_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    nonce_digest BINARY(32) NOT NULL,
    seen_at DATETIME(6) NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    PRIMARY KEY (service_key_id, nonce_digest),
    KEY idx_integration_service_nonces_expiry (expires_at),
    CONSTRAINT fk_integration_service_nonce_key FOREIGN KEY (service_key_id) REFERENCES integration_service_keys (id),
    CONSTRAINT chk_integration_service_nonce_expiry CHECK (expires_at > seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messaging_links (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    platform VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    subject_digest BINARY(32) NOT NULL,
    subject_ciphertext VARBINARY(512) NOT NULL,
    status VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
    linked_at DATETIME(6) NOT NULL,
    revoked_at DATETIME(6) NULL,
    revoke_reason VARCHAR(255) NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_messaging_links_platform_subject (platform, subject_digest),
    UNIQUE KEY uq_messaging_links_user_platform (user_id, platform),
    KEY idx_messaging_links_user_status (user_id, status),
    CONSTRAINT fk_messaging_links_user FOREIGN KEY (user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_messaging_links_platform CHECK (platform IN ('telegram', 'bale')),
    CONSTRAINT chk_messaging_links_status CHECK (status IN ('active', 'revoked')),
    CONSTRAINT chk_messaging_links_revoke CHECK (revoked_at IS NULL OR revoke_reason IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messaging_link_challenges (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    platform VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    token_digest BINARY(32) NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    consumed_at DATETIME(6) NULL,
    consumed_link_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_messaging_link_challenge_token (token_digest),
    KEY idx_messaging_link_challenges_user (user_id, platform, created_at),
    KEY idx_messaging_link_challenges_expiry (expires_at, consumed_at),
    CONSTRAINT fk_messaging_link_challenge_user FOREIGN KEY (user_id) REFERENCES iam_users (id),
    CONSTRAINT fk_messaging_link_challenge_link FOREIGN KEY (consumed_link_id) REFERENCES messaging_links (id),
    CONSTRAINT chk_messaging_link_challenge_platform CHECK (platform IN ('telegram', 'bale')),
    CONSTRAINT chk_messaging_link_challenge_expiry CHECK (expires_at > created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messaging_link_rate_guards (
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    platform VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    window_started_at DATETIME(6) NOT NULL,
    challenge_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    last_challenge_at DATETIME(6) NULL,
    cooldown_until DATETIME(6) NULL,
    PRIMARY KEY (user_id, platform),
    CONSTRAINT fk_messaging_link_rate_user FOREIGN KEY (user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_messaging_link_rate_platform CHECK (platform IN ('telegram', 'bale'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messaging_channel_contexts (
    link_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (link_id),
    KEY idx_messaging_channel_context_workspace (workspace_id),
    CONSTRAINT fk_messaging_channel_context_link FOREIGN KEY (link_id) REFERENCES messaging_links (id),
    CONSTRAINT fk_messaging_channel_context_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_channel_preferences (
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    channel VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (workspace_id, user_id, channel),
    CONSTRAINT fk_notification_channel_pref_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_notification_channel_pref_user FOREIGN KEY (user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_notification_channel_pref_channel CHECK (channel IN ('telegram', 'bale'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_channel_deliveries (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    recipient_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    link_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    state VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending',
    lease_token_digest BINARY(32) NULL,
    leased_until DATETIME(6) NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    provider_message_ref VARCHAR(255) NULL,
    last_error_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NULL,
    delivered_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notification_channel_delivery_recipient (recipient_id),
    KEY idx_notification_channel_delivery_claim (state, leased_until, updated_at),
    CONSTRAINT fk_notification_channel_delivery_recipient FOREIGN KEY (recipient_id) REFERENCES notification_recipients (id),
    CONSTRAINT fk_notification_channel_delivery_link FOREIGN KEY (link_id) REFERENCES messaging_links (id),
    CONSTRAINT chk_notification_channel_delivery_state CHECK (state IN ('pending', 'leased', 'delivered', 'retry', 'failed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_delivery_receipts (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    issuance_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    channel VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    idempotency_key VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    outcome VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    provider_message_ref VARCHAR(255) NULL,
    error_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_content_delivery_receipt_idempotency (issuance_id, channel, idempotency_key),
    KEY idx_content_delivery_receipts_workspace (workspace_id, created_at),
    CONSTRAINT fk_content_delivery_receipt_issuance FOREIGN KEY (issuance_id, workspace_id) REFERENCES content_delivery_issuances (id, workspace_id),
    CONSTRAINT chk_content_delivery_receipt_channel CHECK (channel IN ('telegram', 'bale', 'web', 'api')),
    CONSTRAINT chk_content_delivery_receipt_outcome CHECK (outcome IN ('delivered', 'failed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS protected_media_jobs (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    resource_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    resource_version_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    object_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    issuance_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    completion_key VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    state VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'queued',
    renderer_algorithm_version VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    watermark_label VARCHAR(255) NOT NULL,
    forensic_id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    limits_json JSON NOT NULL,
    lease_token_digest BINARY(32) NULL,
    leased_until DATETIME(6) NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    output_checksum_sha256 BINARY(32) NULL,
    output_size BIGINT UNSIGNED NULL,
    output_mime VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NULL,
    artifact_ref VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
    failure_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    completed_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_protected_media_completion (completion_key),
    KEY idx_protected_media_claim (state, leased_until, created_at),
    CONSTRAINT fk_protected_media_version FOREIGN KEY (resource_version_id, resource_id, workspace_id) REFERENCES content_resource_versions (id, resource_id, workspace_id),
    CONSTRAINT fk_protected_media_object FOREIGN KEY (object_id, workspace_id) REFERENCES content_objects (id, workspace_id),
    CONSTRAINT fk_protected_media_issuance FOREIGN KEY (issuance_id, workspace_id) REFERENCES content_delivery_issuances (id, workspace_id),
    CONSTRAINT chk_protected_media_state CHECK (state IN ('queued', 'leased', 'completed', 'failed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS release_update_targets (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    target_key VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    node_key VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    service_key VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_release_update_target_key (target_key),
    CONSTRAINT chk_release_update_target_status CHECK (status IN ('active', 'disabled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS release_update_requests (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    target_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    requested_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    requested_via_channel VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    idempotency_key VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_digest BINARY(32) NOT NULL,
    state VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'REQUESTED',
    current_sha CHAR(40) CHARACTER SET ascii COLLATE ascii_bin NULL,
    candidate_sha CHAR(40) CHARACTER SET ascii COLLATE ascii_bin NULL,
    rollback_sha CHAR(40) CHARACTER SET ascii COLLATE ascii_bin NULL,
    safe_failure_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NULL,
    correlation_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    lease_token_digest BINARY(32) NULL,
    leased_until DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    finished_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_release_update_request_idempotency (target_id, idempotency_key),
    KEY idx_release_update_request_lock (target_id, state, leased_until),
    CONSTRAINT fk_release_update_request_target FOREIGN KEY (target_id) REFERENCES release_update_targets (id),
    CONSTRAINT fk_release_update_request_user FOREIGN KEY (requested_by_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_release_update_request_channel CHECK (requested_via_channel IN ('telegram', 'web', 'api')),
    CONSTRAINT chk_release_update_request_state CHECK (state IN ('REQUESTED', 'PREFLIGHT', 'BACKUP', 'TESTING', 'MIGRATING', 'ACTIVATING', 'RESTARTING', 'HEALTHCHECK', 'SUCCEEDED', 'FAILED', 'ROLLED_BACK')),
    CONSTRAINT chk_release_update_request_sha CHECK ((current_sha IS NULL OR current_sha REGEXP '^[0-9a-f]{40}$') AND (candidate_sha IS NULL OR candidate_sha REGEXP '^[0-9a-f]{40}$') AND (rollback_sha IS NULL OR rollback_sha REGEXP '^[0-9a-f]{40}$'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS release_update_events (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    state VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    event_code VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    detail_json JSON NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_release_update_events_request (request_id, created_at),
    CONSTRAINT fk_release_update_event_request FOREIGN KEY (request_id) REFERENCES release_update_requests (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
