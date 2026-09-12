SET NAMES utf8mb4;

-- Tiered access for self-joined members (docs/product/01_FRONT_DOOR.md #9):
-- joining is open, privilege is what needs approval. A student who completes
-- the join wizard and verifies their phone becomes a member immediately, but
-- at this limited role rather than the full 'student' role from 0001. It
-- grants exactly enough to buy things and receive notifications -- the
-- owner's stated reason is selling notes to a cohort without anyone waiting
-- on a representative -- and nothing that reaches into class-internal
-- material (academic.view: schedule/programme, grade.view_self, exam.take;
-- resource.view is withheld too, since it gates the same lecture-note/past-
-- exam library the 'student' role uses for genuinely class-internal content,
-- not a separate storefront). Approval later upgrades the person to 'student'
-- by granting that role at the same workspace scope; this role is never
-- removed by that upgrade, it is simply superseded in practice.

INSERT INTO rbac_role_templates (
    id, role_key, name, allowed_scope_types, requires_workspace_membership,
    status, created_at, updated_at, archived_at
) VALUES
    (UUID(), 'workspace-limited-member', 'Limited workspace member', JSON_ARRAY('workspace'), TRUE, 'active', NOW(6), NOW(6), NULL)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    allowed_scope_types = VALUES(allowed_scope_types),
    requires_workspace_membership = VALUES(requires_workspace_membership),
    status = VALUES(status),
    updated_at = VALUES(updated_at),
    archived_at = NULL;

INSERT IGNORE INTO rbac_role_permissions (role_template_id, permission_id, created_at)
SELECT roles.id, permissions.id, NOW(6)
FROM rbac_role_templates roles
JOIN rbac_permissions permissions ON permissions.permission_key IN (
    'workspace.view', 'commerce.purchase', 'notification.receive'
)
WHERE roles.role_key = 'workspace-limited-member';
