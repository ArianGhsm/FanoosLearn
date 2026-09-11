SET NAMES utf8mb4;

INSERT INTO rbac_permissions (id, permission_key, description, risk_level, created_at) VALUES
    (UUID(), 'exam.review', 'Review assessment versions before publication', 'sensitive', NOW(6))
ON DUPLICATE KEY UPDATE
    description = VALUES(description),
    risk_level = VALUES(risk_level);

INSERT IGNORE INTO rbac_role_permissions (role_template_id, permission_id, created_at)
SELECT roles.id, permissions.id, NOW(6)
FROM rbac_role_templates roles
JOIN rbac_permissions permissions ON permissions.permission_key = 'exam.review'
WHERE roles.role_key IN (
    'platform-super-admin', 'institution-admin', 'faculty-admin', 'program-admin',
    'workspace-admin', 'content-reviewer'
);

INSERT IGNORE INTO rbac_role_permissions (role_template_id, permission_id, created_at)
SELECT roles.id, permissions.id, NOW(6)
FROM rbac_role_templates roles
JOIN rbac_permissions permissions ON permissions.permission_key = 'exam.manage'
WHERE roles.role_key = 'content-manager';

INSERT INTO content_resource_types (id, type_key, name, status, created_at, updated_at) VALUES
    (UUID(), 'discipline_note', 'Discipline note', 'active', NOW(6), NOW(6)),
    (UUID(), 'cheat_sheet', 'Cheat sheet', 'active', NOW(6), NOW(6)),
    (UUID(), 'flashcards', 'Flashcards', 'active', NOW(6), NOW(6)),
    (UUID(), 'audio', 'Audio', 'active', NOW(6), NOW(6)),
    (UUID(), 'transcript', 'Transcript', 'active', NOW(6), NOW(6)),
    (UUID(), 'slide_reference', 'Slides and reference', 'active', NOW(6), NOW(6))
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    status = VALUES(status),
    updated_at = VALUES(updated_at);
