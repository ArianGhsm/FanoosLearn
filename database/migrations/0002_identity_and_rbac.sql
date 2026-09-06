SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS iam_users (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    display_name VARCHAR(160) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    locale VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'fa-IR',
    version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    KEY idx_iam_users_status_deleted (status, deleted_at),
    CONSTRAINT chk_iam_users_status CHECK (status IN ('invited', 'active', 'suspended', 'deleted')),
    CONSTRAINT chk_iam_users_version CHECK (version > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS iam_user_identifiers (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    identifier_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    normalized_value VARCHAR(320) NOT NULL,
    is_verified BOOLEAN NOT NULL DEFAULT FALSE,
    verified_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    revoked_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_iam_user_identifiers_type_value (identifier_type, normalized_value),
    KEY idx_iam_user_identifiers_user (user_id, revoked_at),
    CONSTRAINT fk_iam_user_identifiers_user FOREIGN KEY (user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_iam_user_identifiers_type CHECK (identifier_type IN ('email', 'phone', 'telegram', 'bale', 'external'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS iam_authenticators (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    authenticator_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    secret_digest VARBINARY(255) NOT NULL,
    metadata_json JSON NOT NULL,
    created_at DATETIME(6) NOT NULL,
    last_used_at DATETIME(6) NULL,
    revoked_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    KEY idx_iam_authenticators_user_active (user_id, revoked_at),
    CONSTRAINT fk_iam_authenticators_user FOREIGN KEY (user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_iam_authenticators_type CHECK (authenticator_type IN ('password', 'passkey', 'totp', 'recovery'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS iam_sessions (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    token_digest BINARY(32) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    last_seen_at DATETIME(6) NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    revoked_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_iam_sessions_token_digest (token_digest),
    KEY idx_iam_sessions_user_expiry (user_id, expires_at, revoked_at),
    CONSTRAINT fk_iam_sessions_user FOREIGN KEY (user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_iam_sessions_expiry CHECK (expires_at > created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_workspace_memberships (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    joined_at DATETIME(6) NOT NULL,
    ended_at DATETIME(6) NULL,
    version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tenant_workspace_memberships_workspace_user (workspace_id, user_id),
    UNIQUE KEY uq_tenant_workspace_memberships_id_workspace (id, workspace_id),
    KEY idx_tenant_workspace_memberships_user (user_id, status),
    CONSTRAINT fk_tenant_workspace_memberships_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_tenant_workspace_memberships_user FOREIGN KEY (user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_tenant_workspace_memberships_status CHECK (status IN ('invited', 'active', 'suspended', 'ended')),
    CONSTRAINT chk_tenant_workspace_memberships_dates CHECK (ended_at IS NULL OR ended_at >= joined_at),
    CONSTRAINT chk_tenant_workspace_memberships_version CHECK (version > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rbac_permissions (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    permission_key VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    description VARCHAR(255) NOT NULL,
    risk_level VARCHAR(16) NOT NULL DEFAULT 'normal',
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rbac_permissions_key (permission_key),
    CONSTRAINT chk_rbac_permissions_risk CHECK (risk_level IN ('normal', 'sensitive', 'critical'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rbac_role_templates (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    role_key VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    name VARCHAR(160) NOT NULL,
    allowed_scope_types JSON NOT NULL,
    requires_workspace_membership BOOLEAN NOT NULL DEFAULT TRUE,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    archived_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rbac_role_templates_key (role_key),
    CONSTRAINT chk_rbac_role_templates_status CHECK (status IN ('active', 'archived'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rbac_role_permissions (
    role_template_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    permission_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (role_template_id, permission_id),
    CONSTRAINT fk_rbac_role_permissions_role FOREIGN KEY (role_template_id) REFERENCES rbac_role_templates (id),
    CONSTRAINT fk_rbac_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES rbac_permissions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rbac_scopes (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    scope_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    entity_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    parent_scope_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    created_at DATETIME(6) NOT NULL,
    archived_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rbac_scopes_type_entity (scope_type, entity_id),
    UNIQUE KEY uq_rbac_scopes_id_workspace (id, workspace_id),
    KEY idx_rbac_scopes_parent (parent_scope_id),
    KEY idx_rbac_scopes_workspace_type (workspace_id, scope_type),
    CONSTRAINT fk_rbac_scopes_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_rbac_scopes_parent FOREIGN KEY (parent_scope_id) REFERENCES rbac_scopes (id),
    CONSTRAINT chk_rbac_scopes_type CHECK (scope_type IN ('platform', 'institution', 'faculty', 'program', 'cohort', 'workspace', 'course_offering', 'resource', 'assessment')),
    CONSTRAINT chk_rbac_scopes_platform_identity CHECK (
        scope_type <> 'platform'
        OR (id = '00000000-0000-7000-8000-000000000001' AND entity_id = '00000000-0000-7000-8000-000000000001' AND parent_scope_id IS NULL)
    ),
    CONSTRAINT chk_rbac_scopes_workspace_presence CHECK (
        (scope_type IN ('platform', 'institution', 'faculty', 'program', 'cohort') AND workspace_id IS NULL)
        OR (scope_type IN ('workspace', 'course_offering', 'resource', 'assessment') AND workspace_id IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rbac_role_assignments (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    role_template_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    scope_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    granted_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    valid_from DATETIME(6) NOT NULL,
    valid_until DATETIME(6) NULL,
    revoked_at DATETIME(6) NULL,
    revoke_reason VARCHAR(255) NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rbac_role_assignments_user_role_scope (user_id, role_template_id, scope_id),
    KEY idx_rbac_role_assignments_user_active (user_id, revoked_at, valid_from, valid_until),
    KEY idx_rbac_role_assignments_scope_active (scope_id, revoked_at),
    CONSTRAINT fk_rbac_role_assignments_user FOREIGN KEY (user_id) REFERENCES iam_users (id),
    CONSTRAINT fk_rbac_role_assignments_role FOREIGN KEY (role_template_id) REFERENCES rbac_role_templates (id),
    CONSTRAINT fk_rbac_role_assignments_scope FOREIGN KEY (scope_id) REFERENCES rbac_scopes (id),
    CONSTRAINT fk_rbac_role_assignments_granter FOREIGN KEY (granted_by_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_rbac_role_assignments_window CHECK (valid_until IS NULL OR valid_until > valid_from),
    CONSTRAINT chk_rbac_role_assignments_revoke_reason CHECK (revoked_at IS NULL OR revoke_reason IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
