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
-- -- can approve too.
--
-- platform-super-admin is listed explicitly rather than left to
-- 0001_generic_rbac.sql's CROSS JOIN rbac_permissions, because that cross
-- join already ran (and already finished granting platform-super-admin
-- every permission that existed at that point) before this file runs in
-- the same pass -- SeedRunner has no ledger and replays every file every
-- call, but within one call each file still runs exactly once, in name
-- order. Without this explicit grant, platform-super-admin would be short
-- exactly this one permission after the first pass; a *second* pass would
-- then have 0001's cross join pick it up (rbac_permissions now already
-- contains it), silently adding one role_permissions row that the first
-- pass didn't. That is precisely what CI's seed-rerun idempotency check
-- catches: it runs seeds once, snapshots row counts, runs again, and
-- diffs -- see tests/Integration/TenantIsolationTest.php's
-- describeCountDiff(), which will name role_permissions and the +1 if this
-- regresses.
INSERT IGNORE INTO rbac_role_permissions (role_template_id, permission_id, created_at)
SELECT roles.id, permissions.id, NOW(6)
FROM rbac_role_templates roles
JOIN rbac_permissions permissions ON permissions.permission_key = 'membership.approve'
WHERE roles.role_key IN (
    'platform-super-admin', 'cohort-representative', 'institution-admin', 'faculty-admin',
    'program-admin', 'workspace-admin'
);
