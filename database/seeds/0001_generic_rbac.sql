SET NAMES utf8mb4;

INSERT INTO rbac_permissions (id, permission_key, description, risk_level, created_at) VALUES
    (UUID(), 'workspace.view', 'View an authorized workspace', 'normal', NOW(6)),
    (UUID(), 'workspace.manage_settings', 'Manage workspace settings', 'sensitive', NOW(6)),
    (UUID(), 'membership.view', 'View workspace memberships', 'normal', NOW(6)),
    (UUID(), 'membership.manage', 'Invite, suspend, or end memberships', 'sensitive', NOW(6)),
    (UUID(), 'academic.view', 'View academic structures', 'normal', NOW(6)),
    (UUID(), 'academic.manage', 'Manage academic structures', 'sensitive', NOW(6)),
    (UUID(), 'resource.view', 'View authorized resources', 'normal', NOW(6)),
    (UUID(), 'resource.create', 'Create resources and versions', 'normal', NOW(6)),
    (UUID(), 'resource.review', 'Review resource versions', 'sensitive', NOW(6)),
    (UUID(), 'resource.publish', 'Publish resources to a workspace', 'sensitive', NOW(6)),
    (UUID(), 'resource.manage_protected', 'Manage protected source content', 'critical', NOW(6)),
    (UUID(), 'exam.take', 'Start and submit own assessment attempts', 'normal', NOW(6)),
    (UUID(), 'exam.manage', 'Manage assessments and scoring', 'sensitive', NOW(6)),
    (UUID(), 'grade.view_self', 'View own published grades', 'normal', NOW(6)),
    (UUID(), 'grade.manage', 'Manage gradebooks and grade results', 'critical', NOW(6)),
    (UUID(), 'commerce.purchase', 'Purchase an offered product', 'normal', NOW(6)),
    (UUID(), 'commerce.manage_catalog', 'Manage products and prices', 'sensitive', NOW(6)),
    (UUID(), 'payment.reconcile', 'Reconcile payment attempts', 'critical', NOW(6)),
    (UUID(), 'entitlement.grant', 'Grant or revoke entitlements', 'critical', NOW(6)),
    (UUID(), 'notification.receive', 'Receive workspace notifications', 'normal', NOW(6)),
    (UUID(), 'notification.broadcast', 'Broadcast workspace notifications', 'sensitive', NOW(6)),
    (UUID(), 'audit.view', 'View authorized audit records', 'critical', NOW(6)),
    (UUID(), 'integration.manage', 'Manage integration metadata', 'critical', NOW(6))
ON DUPLICATE KEY UPDATE
    description = VALUES(description),
    risk_level = VALUES(risk_level);

INSERT INTO rbac_role_templates (
    id, role_key, name, allowed_scope_types, requires_workspace_membership,
    status, created_at, updated_at, archived_at
) VALUES
    (UUID(), 'platform-super-admin', 'Platform super administrator', JSON_ARRAY('platform'), FALSE, 'active', NOW(6), NOW(6), NULL),
    (UUID(), 'institution-admin', 'Institution administrator', JSON_ARRAY('institution'), FALSE, 'active', NOW(6), NOW(6), NULL),
    (UUID(), 'faculty-admin', 'Faculty administrator', JSON_ARRAY('faculty'), FALSE, 'active', NOW(6), NOW(6), NULL),
    (UUID(), 'program-admin', 'Program administrator', JSON_ARRAY('program'), FALSE, 'active', NOW(6), NOW(6), NULL),
    (UUID(), 'workspace-admin', 'Workspace administrator', JSON_ARRAY('workspace'), TRUE, 'active', NOW(6), NOW(6), NULL),
    (UUID(), 'cohort-representative', 'Cohort representative', JSON_ARRAY('workspace'), TRUE, 'active', NOW(6), NOW(6), NULL),
    (UUID(), 'content-manager', 'Content manager', JSON_ARRAY('workspace', 'course_offering'), TRUE, 'active', NOW(6), NOW(6), NULL),
    (UUID(), 'content-reviewer', 'Content reviewer', JSON_ARRAY('workspace', 'course_offering', 'resource'), TRUE, 'active', NOW(6), NOW(6), NULL),
    (UUID(), 'student', 'Student', JSON_ARRAY('workspace', 'course_offering'), TRUE, 'active', NOW(6), NOW(6), NULL),
    (UUID(), 'finance-manager', 'Finance manager', JSON_ARRAY('workspace'), TRUE, 'active', NOW(6), NOW(6), NULL)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    allowed_scope_types = VALUES(allowed_scope_types),
    requires_workspace_membership = VALUES(requires_workspace_membership),
    status = VALUES(status),
    updated_at = VALUES(updated_at),
    archived_at = NULL;

INSERT INTO rbac_scopes (
    id, scope_type, entity_id, workspace_id, parent_scope_id, created_at, archived_at
) VALUES (
    '00000000-0000-7000-8000-000000000001',
    'platform',
    '00000000-0000-7000-8000-000000000001',
    NULL,
    NULL,
    NOW(6),
    NULL
) ON DUPLICATE KEY UPDATE archived_at = NULL;

INSERT IGNORE INTO rbac_role_permissions (role_template_id, permission_id, created_at)
SELECT roles.id, permissions.id, NOW(6)
FROM rbac_role_templates roles
CROSS JOIN rbac_permissions permissions
WHERE roles.role_key = 'platform-super-admin';

INSERT IGNORE INTO rbac_role_permissions (role_template_id, permission_id, created_at)
SELECT roles.id, permissions.id, NOW(6)
FROM rbac_role_templates roles
JOIN rbac_permissions permissions ON permissions.permission_key IN (
    'workspace.view', 'workspace.manage_settings', 'membership.view', 'membership.manage',
    'academic.view', 'academic.manage', 'resource.view', 'resource.create', 'resource.review',
    'resource.publish', 'exam.manage', 'grade.manage', 'notification.broadcast', 'audit.view'
)
WHERE roles.role_key IN ('institution-admin', 'faculty-admin', 'program-admin', 'workspace-admin');

INSERT IGNORE INTO rbac_role_permissions (role_template_id, permission_id, created_at)
SELECT roles.id, permissions.id, NOW(6)
FROM rbac_role_templates roles
JOIN rbac_permissions permissions ON permissions.permission_key IN (
    'workspace.view', 'membership.view', 'academic.view', 'resource.view',
    'resource.create', 'notification.receive', 'notification.broadcast'
)
WHERE roles.role_key = 'cohort-representative';

INSERT IGNORE INTO rbac_role_permissions (role_template_id, permission_id, created_at)
SELECT roles.id, permissions.id, NOW(6)
FROM rbac_role_templates roles
JOIN rbac_permissions permissions ON permissions.permission_key IN (
    'workspace.view', 'academic.view', 'resource.view', 'resource.create',
    'resource.review', 'resource.publish', 'notification.receive'
)
WHERE roles.role_key = 'content-manager';

INSERT IGNORE INTO rbac_role_permissions (role_template_id, permission_id, created_at)
SELECT roles.id, permissions.id, NOW(6)
FROM rbac_role_templates roles
JOIN rbac_permissions permissions ON permissions.permission_key IN (
    'workspace.view', 'academic.view', 'resource.view', 'resource.review', 'notification.receive'
)
WHERE roles.role_key = 'content-reviewer';

INSERT IGNORE INTO rbac_role_permissions (role_template_id, permission_id, created_at)
SELECT roles.id, permissions.id, NOW(6)
FROM rbac_role_templates roles
JOIN rbac_permissions permissions ON permissions.permission_key IN (
    'workspace.view', 'academic.view', 'resource.view', 'exam.take',
    'grade.view_self', 'commerce.purchase', 'notification.receive'
)
WHERE roles.role_key = 'student';

INSERT IGNORE INTO rbac_role_permissions (role_template_id, permission_id, created_at)
SELECT roles.id, permissions.id, NOW(6)
FROM rbac_role_templates roles
JOIN rbac_permissions permissions ON permissions.permission_key IN (
    'workspace.view', 'membership.view', 'commerce.manage_catalog',
    'payment.reconcile', 'entitlement.grant', 'audit.view'
)
WHERE roles.role_key = 'finance-manager';

INSERT INTO content_resource_types (id, type_key, name, status, created_at, updated_at) VALUES
    (UUID(), 'lecture_note', 'Lecture note', 'active', NOW(6), NOW(6)),
    (UUID(), 'summary', 'Summary', 'active', NOW(6), NOW(6)),
    (UUID(), 'question_bank', 'Question bank', 'active', NOW(6), NOW(6)),
    (UUID(), 'past_exam', 'Past exam', 'active', NOW(6), NOW(6)),
    (UUID(), 'other', 'Other', 'active', NOW(6), NOW(6))
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    status = VALUES(status),
    updated_at = VALUES(updated_at);
