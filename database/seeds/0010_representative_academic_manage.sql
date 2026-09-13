SET NAMES utf8mb4;

-- Term dates (docs/product/01_FRONT_DOOR.md #4): a representative overriding
-- their own class's term dates is managing an academic structure --
-- academic_terms is exactly that -- so this reuses the existing
-- academic.manage permission (0001_generic_rbac.sql) rather than inventing a
-- new one. academic.manage is already granted to institution-admin,
-- faculty-admin, program-admin and workspace-admin; cohort-representative
-- currently holds only academic.view. This is deliberately wider than
-- membership.approve's narrow carve-out (docs/product/01_FRONT_DOOR.md #10
-- kept membership.approve separate from membership.manage specifically
-- because approving a join is not roster administration) -- academic.manage
-- already covers exactly one thing, academic structures, and a
-- representative's own class's terms are exactly that, not a wider power
-- borrowed from a bigger permission.
--
-- academic.manage already exists (seeded in 0001_generic_rbac.sql before
-- this file runs in the same pass), so there is no first-pass/second-pass
-- rbac_role_permissions drift to guard against here the way
-- 0009_membership_approve_permission.sql's comment describes for a
-- newly-created permission -- this file only adds one additional role to an
-- already-seeded permission's grant list.
INSERT IGNORE INTO rbac_role_permissions (role_template_id, permission_id, created_at)
SELECT roles.id, permissions.id, NOW(6)
FROM rbac_role_templates roles
JOIN rbac_permissions permissions ON permissions.permission_key = 'academic.manage'
WHERE roles.role_key = 'cohort-representative';
