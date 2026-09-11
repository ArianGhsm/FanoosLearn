SET NAMES utf8mb4;

INSERT INTO rbac_permissions (id, permission_key, description, risk_level, created_at) VALUES
    (UUID(), 'workspace.provision', 'Create a class: resolve/create its directory chain and workspace', 'critical', NOW(6))
ON DUPLICATE KEY UPDATE
    description = VALUES(description),
    risk_level = VALUES(risk_level);

INSERT IGNORE INTO rbac_role_permissions (role_template_id, permission_id, created_at)
SELECT role.id, permission.id, NOW(6)
FROM rbac_role_templates role
JOIN rbac_permissions permission ON permission.permission_key = 'workspace.provision'
WHERE role.role_key = 'platform-super-admin';
