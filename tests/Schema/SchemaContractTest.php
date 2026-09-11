<?php

declare(strict_types=1);

namespace Fanoos\Tests\Schema;

use Fanoos\Platform\Migration\SqlStatementSplitter;
use Fanoos\Platform\Support\Uuid;
use RuntimeException;

final class SchemaContractTest
{
    private int $assertions = 0;

    public function __construct(private readonly string $root)
    {
    }

    public function run(): int
    {
        $migrationPaths = glob($this->root . '/database/migrations/*.sql') ?: [];
        sort($migrationPaths, SORT_STRING);
        $this->assert(count($migrationPaths) === 13, 'Expected exactly thirteen versioned platform migrations through the onboarding phone verification contract.');

        $sql = '';
        foreach ($migrationPaths as $path) {
            $contents = file_get_contents($path);
            $this->assert($contents !== false, 'Migration could not be read: ' . basename($path));
            $sql .= "\n" . $contents;
            $this->assert(!preg_match('/\b(?:DROP|TRUNCATE)\b/i', $contents), 'Destructive DDL found in ' . basename($path));
            $this->assert(!preg_match('/Dentistry|IntegratedDent|TUMS|1402/i', $contents), 'Legacy product identifier found in a migration.');
        }

        foreach (['0008_stage7_platform_contracts.sql', '0009_stage7_notification_receipts.sql', '0010_bot_handoff_contracts.sql', '0011_stage2_rbac_projection_indexes.sql', '0012_stage8_assessment_variants.sql'] as $migration) {
            $contents = file_get_contents($this->root . '/database/migrations/' . $migration);
            $this->assert(is_string($contents) && preg_match('/^\s*--\s*fanoos:rollback-compatible=expand\s*$/mi', $contents) === 1, "Migration is missing the unattended expand-compatibility marker: {$migration}");
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
            'content_resource_metadata', 'content_version_reviews', 'content_derivations',
            'content_import_batches', 'content_import_items', 'content_import_results',
            'content_delivery_issuances', 'content_delivery_events', 'exam_assessment_metadata',
            'exam_access_policies', 'exam_version_reviews', 'exam_version_states', 'exam_attempt_results',
            'integration_service_identities', 'integration_service_keys', 'integration_service_nonces',
            'messaging_links', 'messaging_link_challenges', 'messaging_link_rate_guards', 'messaging_channel_contexts',
            'notification_channel_preferences', 'notification_channel_deliveries', 'notification_delivery_receipts',
            'content_delivery_receipts', 'protected_media_jobs', 'protected_media_artifacts',
            'release_update_targets', 'release_update_requests', 'release_update_events', 'release_update_snapshots',
        ];
        foreach ($requiredTables as $table) {
            $this->assert(in_array($table, $tables, true), "Required table is missing: {$table}");
        }

        preg_match_all('/CREATE TABLE IF NOT EXISTS\s+[a-z0-9_]+\s*\(.*?\) ENGINE=InnoDB/is', $sql, $innodbMatches);
        $this->assert(count($innodbMatches[0]) === count($tables), 'Every migration table must explicitly use InnoDB.');

        $tenantTables = [
            'academic_terms', 'academic_courses', 'academic_course_offerings',
            'academic_course_sessions', 'academic_enrollments', 'content_objects',
            'content_resources', 'content_resource_versions', 'content_resource_bindings',
            'commerce_products', 'commerce_orders', 'exam_assessments', 'grade_gradebooks',
            'grade_results', 'schedule_events',
            'notification_preferences', 'form_definitions', 'form_versions',
            'form_submissions', 'search_documents', 'commerce_reconciliation_runs',
            'content_access_policies', 'grade_import_batches',
            'content_resource_metadata', 'content_version_reviews', 'content_derivations',
            'content_import_batches', 'content_import_items', 'content_import_results',
            'content_delivery_issuances', 'content_delivery_events', 'exam_assessment_metadata',
            'exam_access_policies', 'exam_version_reviews', 'exam_version_states', 'exam_attempt_results',
            'notification_channel_preferences', 'content_delivery_receipts', 'protected_media_jobs', 'protected_media_artifacts',
        ];
        foreach ($tenantTables as $table) {
            $pattern = '/CREATE TABLE IF NOT EXISTS\s+' . preg_quote($table, '/') . '\s*\((.*?)\) ENGINE=InnoDB/is';
            $this->assert((bool) preg_match($pattern, $sql, $tableMatch), "Cannot inspect tenant table {$table}.");
            $this->assert(str_contains(strtolower($tableMatch[1]), 'workspace_id'), "Tenant table lacks workspace_id: {$table}");
        }
        $this->assert(str_contains($sql, 'timezone_name'), 'Workspace timezone column required by bot schedule projection is missing.');

        foreach (['03_DATA_MODEL.md', '03_LEGACY_DATA_MAPPING.md', '03_RBAC_SCOPE_MATRIX.md', '03_MIGRATION_RUNBOOK.md'] as $document) {
            $this->assert(is_file($this->root . '/docs/fanoos-migration/' . $document), "Required document is missing: {$document}");
        }
        foreach (['index.php', 'api.php', 'assets/ui-v3/foundation/tokens.css', 'assets/ui-v3/app/bootstrap.js'] as $asset) {
            $this->assert(is_file($this->root . '/apps/platform/public/' . $asset), "Platform UI/API asset is missing: {$asset}");
        }
        foreach (['core-v1.yaml', 'internal-v1.yaml'] as $contract) {
            $this->assert(is_file($this->root . '/contracts/openapi/' . $contract), "Shared API contract is missing: {$contract}");
        }
        foreach (['06_CONTENT_ENGINE.md', '06_CONTENT_REUSE_AND_ADAPTATION_REPORT.md', '06_SECURE_DELIVERY_CONTRACT.md', '06_CONTENT_PARITY_MATRIX.md'] as $document) {
            $this->assert(is_file($this->root . '/docs/fanoos-migration/' . $document), "Stage 6 document is missing: {$document}");
        }
        foreach (['07_PLATFORM_BACKEND_CONTRACTS.md', '07_UPDATE_CONTROL_PLANE.md', '07_CODEX_ONE_TIME_BOOTSTRAP.md', '07_PLATFORM_HANDOFF_TO_BOTS.md', '07_BOT_INTEGRATION_HANDOFF.md'] as $document) {
            $this->assert(is_file($this->root . '/docs/fanoos-migration/' . $document), "Stage 7 document is missing: {$document}");
        }
        foreach (['scripts/ops/update-runner.php', 'scripts/ops/refresh-update-status.php', 'ops/updater/fanoos-updater.service.example', 'ops/updater/fanoos-updater.timer.example', 'ops/updater/fanoos-update-status.service.example', 'ops/updater/fanoos-update-status.timer.example'] as $path) {
            $this->assert(is_file($this->root . '/' . $path), "Stage 7 updater artifact is missing: {$path}");
        }
        $this->assert(is_file($this->root . '/scripts/import/content-manifest.php'), 'Content import tool is missing.');

        $internal = file_get_contents($this->root . '/contracts/openapi/internal-v1.yaml');
        $this->assert(is_string($internal) && str_contains($internal, 'X-Fanoos-Key-Id') && str_contains($internal, 'X-Fanoos-Nonce') && str_contains($internal, 'X-Fanoos-Signature'), 'Internal service signing headers are not machine-documented.');
        $this->assert(is_string($internal) && str_contains($internal, '/messaging/links/revoke:') && str_contains($internal, '/academics/schedule:') && str_contains($internal, '/academics/grades:') && str_contains($internal, '/announcements/list:') && str_contains($internal, '/content/resources/list:'), 'Bot-safe unlink or academic/content read contracts are missing.');
        $this->assert(is_string($internal) && str_contains($internal, '/protected-media/source/redeem:') && str_contains($internal, '/protected-media/artifacts/authorize-publish:') && str_contains($internal, '/protected-media/artifacts/publish:') && str_contains($internal, '/protected-media/derivatives/issue:') && str_contains($internal, '/protected-media/derivatives/redeem:'), 'Protected-media source/publication/retrieval contracts are incomplete.');
        $this->assert(is_string($internal) && str_contains($internal, 'application/pdf') && str_contains($internal, 'format: binary') && !str_contains($internal, 'storage_key:'), 'Protected-media contract does not expose bounded PDF bytes safely.');
        $this->assert(is_string($internal) && str_contains($internal, '/deployments/overview:') && str_contains($internal, 'can_manage_deployments'), 'Read-only owner deployment overview is not documented.');
        $this->assert(is_string($internal) && str_contains($internal, 'additionalProperties: false') && str_contains($internal, '/deployments/request:'), 'Deployment request contract does not reject arbitrary fields.');
        $this->assert(is_string($internal) && !preg_match('/\b(?:branch|remote|command|shell|target_sha|candidate_sha)\s*:/i', $this->deploymentSchema($internal)), 'Deployment request schema exposes an arbitrary ref, SHA, remote or shell field.');

        $kernel = file_get_contents($this->root . '/apps/platform/src/Http/InternalApiKernel.php');
        $this->assert(is_string($kernel) && !preg_match('/\b(?:proc_open|shell_exec|exec|system|passthru)\s*\(/', $kernel), 'Internal HTTP kernel contains process execution.');
        $this->assert(is_string($kernel) && str_contains($kernel, "['platform', 'subject', 'target_key', 'idempotency_key']"), 'Deployment endpoint allowlist is not fixed.');
        $this->assert(is_string($kernel) && str_contains($kernel, 'protected_media.artifact.authorize') && str_contains($kernel, 'x-fanoos-upload-capability'), 'Raw derivative upload is not separated from signed metadata authorization.');

        $runner = file_get_contents($this->root . '/scripts/ops/update-runner.php');
        $this->assert(is_string($runner) && str_contains($runner, '$argc !== 1'), 'Privileged updater runner accepts user-controlled positional arguments.');
        $refresher = file_get_contents($this->root . '/scripts/ops/refresh-update-status.php');
        $this->assert(is_string($refresher) && str_contains($refresher, '$argc !== 1') && str_contains($refresher, 'CanonicalMainUpdateExecutor'), 'Updater-side owner status refresher can accept arbitrary candidate input or bypass canonical preflight.');
        $executor = file_get_contents($this->root . '/apps/platform/src/Operations/CanonicalMainUpdateExecutor.php');
        $this->assert(is_string($executor) && str_contains($executor, 'refs/heads/main:refs/remotes/origin/main') && str_contains($executor, 'ArianGhsm/FanoosLearn'), 'Updater does not resolve the fixed canonical FANOOS main branch.');
        $owner = file_get_contents($this->root . '/apps/platform/src/Operations/OwnerControlPlaneService.php');
        $this->assert(is_string($owner) && !preg_match('/\b(?:ProcessRunner|proc_open|shell_exec|exec|system|passthru)\b/', $owner), 'Owner overview can execute privileged/runtime commands.');

        $deploy = file_get_contents($this->root . '/scripts/ops/cpanel-deploy.sh');
        $verifyPosition = is_string($deploy) ? strpos($deploy, 'verify-backup.php') : false;
        $migratePosition = is_string($deploy) ? strpos($deploy, 'scripts/db/migrate.php') : false;
        $this->assert($verifyPosition !== false && $migratePosition !== false && $verifyPosition < $migratePosition, 'Canonical deploy does not verify backup before migration.');

        $ci = file_get_contents($this->root . '/.github/workflows/ci.yml');
        $this->assert(is_string($ci) && str_contains($ci, 'python-bot-worker:') && str_contains($ci, 'ops/stage7-bots/run-deterministic-tests.sh'), 'Central CI does not run deterministic Python bot/worker tests.');
        $this->assert(is_string($ci) && str_contains($ci, 'static-and-unit:') && str_contains($ci, 'mysql-integration:'), 'Python CI integration replaced existing PHP/MySQL gates.');

        $split = SqlStatementSplitter::split("SELECT ';' AS value; -- comment\nSELECT 2;");
        $this->assert(count($split) === 2, 'SQL statement splitter does not preserve quoted semicolons.');
        $this->assert((bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', Uuid::v7()), 'UUIDv7 format is invalid.');

        return $this->assertions;
    }

    private function deploymentSchema(string $yaml): string
    {
        $start = strpos($yaml, '    DeploymentRequest:');
        $end = strpos($yaml, '    DeploymentStatus:', $start === false ? 0 : $start);
        return $start === false ? '' : substr($yaml, $start, $end === false ? null : $end - $start);
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
