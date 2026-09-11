SET NAMES utf8mb4;

INSERT INTO rbac_role_templates (
    id, role_key, name, allowed_scope_types, requires_workspace_membership,
    status, created_at, updated_at, archived_at
) VALUES (
    UUID(), 'content-author', 'Content author', JSON_ARRAY('workspace', 'course_offering', 'resource'),
    TRUE, 'active', NOW(6), NOW(6), NULL
) ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    allowed_scope_types = VALUES(allowed_scope_types),
    requires_workspace_membership = VALUES(requires_workspace_membership),
    status = VALUES(status),
    updated_at = VALUES(updated_at),
    archived_at = NULL;

INSERT IGNORE INTO rbac_role_permissions (role_template_id, permission_id, created_at)
SELECT role.id, permission.id, NOW(6)
FROM rbac_role_templates role
JOIN rbac_permissions permission ON permission.permission_key IN (
    'workspace.view', 'academic.view', 'resource.view', 'resource.create', 'notification.receive'
)
WHERE role.role_key = 'content-author';
