SET NAMES utf8mb4;

INSERT INTO rbac_permissions (id, permission_key, description, risk_level, created_at) VALUES
    (UUID(), 'form.submit', 'Submit an available workspace form', 'normal', NOW(6)),
    (UUID(), 'form.manage', 'Create and manage workspace forms', 'sensitive', NOW(6)),
    (UUID(), 'form.export', 'Read and export form responses', 'sensitive', NOW(6))
ON DUPLICATE KEY UPDATE
    description = VALUES(description),
    risk_level = VALUES(risk_level);

INSERT IGNORE INTO rbac_role_permissions (role_template_id, permission_id, created_at)
SELECT roles.id, permissions.id, NOW(6)
FROM rbac_role_templates roles
JOIN rbac_permissions permissions ON permissions.permission_key IN ('form.submit')
WHERE roles.role_key = 'student';

INSERT IGNORE INTO rbac_role_permissions (role_template_id, permission_id, created_at)
SELECT roles.id, permissions.id, NOW(6)
FROM rbac_role_templates roles
JOIN rbac_permissions permissions ON permissions.permission_key IN ('form.submit', 'form.manage', 'form.export')
WHERE roles.role_key IN ('cohort-representative', 'workspace-admin', 'platform-super-admin');
