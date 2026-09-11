-- fanoos:rollback-compatible=expand
SET NAMES utf8mb4;

ALTER TABLE tenant_workspace_memberships
    ADD KEY idx_tenant_workspace_memberships_user_workspace_active (user_id, workspace_id, status, ended_at);

ALTER TABLE rbac_role_assignments
    ADD KEY idx_rbac_role_assignments_user_scope_active (user_id, scope_id, revoked_at, valid_from, valid_until);
