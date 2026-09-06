<?php

declare(strict_types=1);

namespace Fanoos\Tests\Schema;

use Fanoos\Platform\Migration\SqlStatementSplitter;
use Fanoos\Platform\Support\Uuid;
use RuntimeException;

final class SchemaContractTest
{
    public function __construct(private readonly string $root)
    {
    }

    public function run(): int
    {
        $migrationPaths = glob($this->root . '/database/migrations/*.sql') ?: [];
        sort($migrationPaths, SORT_STRING);
        self::assert(count($migrationPaths) === 6, 'Expected exactly six versioned platform migrations through Prompt 5.');

        $sql = '';
        foreach ($migrationPaths as $path) {
            $contents = file_get_contents($path);
            self::assert($contents !== false, 'Migration could not be read: ' . basename($path));
            $sql .= "\n" . $contents;
            self::assert(!preg_match('/\b(?:DROP|TRUNCATE)\b/i', $contents), 'Destructive DDL found in ' . basename($path));
            self::assert(!preg_match('/Dentistry|IntegratedDent|TUMS|1402/i', $contents), 'Legacy product identifier found in a migration.');
        }

        preg_match_all('/CREATE TABLE IF NOT EXISTS\s+([a-z0-9_]+)/i', $sql, $matches);
        $tables = array_map('strtolower', $matches[1]);
        $requiredTables = [
            'directory_countries', 'directory_provinces', 'directory_cities',
            'directory_institutions', 'directory_campuses', 'directory_faculties',
            'directory_departments', 'directory_programs', 'directory_cohorts',
            'tenant_workspaces', 'iam_users', 'tenant_workspace_memberships',
            'rbac_permissions', 'rbac_role_templates', 'rbac_scopes', 'rbac_role_assignments',
            'academic_terms', 'academic_courses', 'academic_course_offerings',
            'academic_course_sessions', 'academic_enrollments', 'content_resources',
            'content_resource_versions', 'content_resource_bindings', 'content_resource_publications',
            'commerce_orders', 'commerce_payment_attempts', 'entitlement_grants',
            'notification_messages', 'exam_assessments', 'grade_gradebooks',
            'schedule_events', 'audit_events', 'migration_legacy_id_mappings',
            'iam_login_attempts', 'notification_preferences', 'form_definitions',
            'form_versions', 'form_submissions', 'search_documents',
            'commerce_reconciliation_runs', 'content_access_policies', 'grade_import_batches',
        ];
        foreach ($requiredTables as $table) {
            self::assert(in_array($table, $tables, true), "Required table is missing: {$table}");
        }

        preg_match_all('/CREATE TABLE IF NOT EXISTS\s+[a-z0-9_]+\s*\(.*?\) ENGINE=InnoDB/is', $sql, $innodbMatches);
        self::assert(count($innodbMatches[0]) === count($tables), 'Every migration table must explicitly use InnoDB.');

        $tenantTables = [
            'academic_terms', 'academic_courses', 'academic_course_offerings',
            'academic_course_sessions', 'academic_enrollments', 'content_objects',
            'content_resources', 'content_resource_versions', 'content_resource_bindings',
            'commerce_products', 'commerce_orders', 'exam_assessments', 'grade_gradebooks',
            'grade_results', 'schedule_events',
            'notification_preferences', 'form_definitions', 'form_versions',
            'form_submissions', 'search_documents', 'commerce_reconciliation_runs',
            'content_access_policies', 'grade_import_batches',
        ];
        foreach ($tenantTables as $table) {
            $pattern = '/CREATE TABLE IF NOT EXISTS\s+' . preg_quote($table, '/') . '\s*\((.*?)\) ENGINE=InnoDB/is';
            self::assert((bool) preg_match($pattern, $sql, $tableMatch), "Cannot inspect tenant table {$table}.");
            self::assert(str_contains(strtolower($tableMatch[1]), 'workspace_id'), "Tenant table lacks workspace_id: {$table}");
        }

        foreach (['03_DATA_MODEL.md', '03_LEGACY_DATA_MAPPING.md', '03_RBAC_SCOPE_MATRIX.md', '03_MIGRATION_RUNBOOK.md'] as $document) {
            self::assert(is_file($this->root . '/docs/fanoos-migration/' . $document), "Required document is missing: {$document}");
        }

        foreach (['index.php', 'api.php', 'assets/app.css', 'assets/app.js'] as $asset) {
            self::assert(is_file($this->root . '/apps/platform/public/' . $asset), "Prompt 5 UI/API asset is missing: {$asset}");
        }
        self::assert(is_file($this->root . '/contracts/openapi/core-v1.yaml'), 'Shared API contract is missing.');

        $split = SqlStatementSplitter::split("SELECT ';' AS value; -- comment\nSELECT 2;");
        self::assert(count($split) === 2, 'SQL statement splitter does not preserve quoted semicolons.');
        self::assert((bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', Uuid::v7()), 'UUIDv7 format is invalid.');

        return 1 + count($requiredTables) + count($tenantTables) + 12;
    }

    private static function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
