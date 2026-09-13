-- fanoos:rollback-compatible=expand
SET NAMES utf8mb4;

-- Capability 5's owner-facing side (docs/product/01_FRONT_DOOR.md #9) needs to
-- record who resolved a class-creation request and when: approving or
-- declining is an owner decision that closes out other people's requests, and
-- AuditLogger alone is not queryable alongside this row. Mirrors
-- tenant_workspace_role_upgrade_requests (migrations/0014), which already
-- carries resolved_at/resolved_by_user_id for the same reason.

ALTER TABLE class_creation_requests
    ADD COLUMN resolved_at DATETIME(6) NULL AFTER requested_at,
    ADD COLUMN resolved_by_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER resolved_at,
    ADD CONSTRAINT fk_class_creation_requests_resolver FOREIGN KEY (resolved_by_user_id) REFERENCES iam_users (id);
