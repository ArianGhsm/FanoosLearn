SET NAMES utf8mb4;

-- A narrow permission for approving/declining a pending workspace
-- role-upgrade request (tenant_workspace_role_upgrade_requests), distinct
-- from membership.manage. A cohort representative needs to be able to admit
-- their own classmates without also gaining membership.manage's much wider
-- power to invite, suspend or end memberships outright -- approving a join
-- is not the same capability as administering the roster
-- (docs/product/01_FRONT_DOOR.md #10).
INSERT INTO rbac_permissions (id, permission_key, description, risk_level, created_at) VALUES
    (UUID(), 'membership.approve', 'Approve or decline a pending workspace role-upgrade request', 'sensitive', NOW(6))
ON DUPLICATE KEY UPDATE
    description = VALUES(description),
    risk_level = VALUES(risk_level);

-- Granted to the cohort representative (the intended day-to-day approver)
-- and to every role that already holds membership.manage, so an
-- institution/faculty/program/workspace admin -- and platform-super-admin
-- -- can approve too. platform-super-admin is listed explicitly here rather
-- than left to 0001_generic_rbac.sql's cross-join: that cross-join runs
-- before this file in file order, so on a fresh database it would only pick
-- up this permission starting from a *second* full seed run, not the first
-- -- SeedRunner replays every seed file on every run with no ledger, and
-- CI's rerun check compares row counts after exactly one run against
-- exactly two, so that one-run lag is a genuine idempotency bug, not a
-- rounding error.
INSERT IGNORE INTO rbac_role_permissions (role_template_id, permission_id, created_at)
SELECT roles.id, permissions.id, NOW(6)
FROM rbac_role_templates roles
JOIN rbac_permissions permissions ON permissions.permission_key = 'membership.approve'
WHERE roles.role_key IN (
    'platform-super-admin', 'cohort-representative', 'institution-admin', 'faculty-admin',
    'program-admin', 'workspace-admin'
);
