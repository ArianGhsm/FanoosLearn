-- fanoos:rollback-compatible=expand
SET NAMES utf8mb4;

-- A durable, idempotent request from a limited (self-joined) workspace member
-- asking their class representative to upgrade them to the full student role.
-- Representatives do not exist yet (appointing one is a later capability), so
-- this table only ever records the request; nothing here approves it. One row
-- per (workspace, user): repeated taps on "request approval" must not create
-- a second request, and a resolved request is reopened rather than duplicated
-- if the member is somehow demoted and asks again.

CREATE TABLE IF NOT EXISTS tenant_workspace_role_upgrade_requests (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'pending',
    requested_at DATETIME(6) NOT NULL,
    resolved_at DATETIME(6) NULL,
    resolved_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_workspace_role_upgrade_requests_member (workspace_id, user_id),
    KEY idx_workspace_role_upgrade_requests_status (workspace_id, status),
    CONSTRAINT fk_workspace_role_upgrade_requests_workspace FOREIGN KEY (workspace_id) REFERENCES tenant_workspaces (id),
    CONSTRAINT fk_workspace_role_upgrade_requests_user FOREIGN KEY (user_id) REFERENCES iam_users (id),
    CONSTRAINT fk_workspace_role_upgrade_requests_resolver FOREIGN KEY (resolved_by_user_id) REFERENCES iam_users (id),
    CONSTRAINT chk_workspace_role_upgrade_requests_status CHECK (status IN ('pending', 'approved', 'rejected'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
