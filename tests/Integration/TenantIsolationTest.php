<?php

declare(strict_types=1);

namespace Fanoos\Tests\Integration;

use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Identity\AuthenticatedSession;
use Fanoos\Platform\Identity\AuthService;
use Fanoos\Platform\Identity\PasswordHasher;
use Fanoos\Platform\Migration\LegacyIdMap;
use Fanoos\Platform\Migration\MigrationRunner;
use Fanoos\Platform\Migration\MigrationSafety;
use Fanoos\Platform\Migration\SeedRunner;
use Fanoos\Platform\Support\Uuid;
use PDO;
use PDOException;
use RuntimeException;

final class TenantIsolationTest
{
    public function __construct(
        private readonly PDO $database,
        private readonly string $root,
        private readonly string $legacyHmacKey,
    ) {
    }

    public function run(): int
    {
        $migrationRunner = new MigrationRunner($this->database, $this->root . '/database/migrations');
        $migrationFiles = glob($this->root . '/database/migrations/*.sql') ?: [];
        sort($migrationFiles, SORT_STRING);
        $migrationFileCount = count($migrationFiles);

        // A fresh database is exactly what the unattended path must not
        // finish bootstrapping on its own once a declared-contract migration
        // exists: it applies every expand-compatible file first (they sort
        // ahead of 0023 lexically) and then refuses the contract one,
        // pointing at the supervised script rather than silently skipping
        // or silently applying it.
        $unattendedRefusedContractMigration = false;
        try {
            $migrationRunner->run();
        } catch (RuntimeException $refusal) {
            $unattendedRefusedContractMigration = str_contains($refusal->getMessage(), 'supervised operator path');
        }
        self::assert($unattendedRefusedContractMigration, 'Unattended bootstrap must refuse the declared-contract migration, not silently apply or skip it.');

        // What scripts/ops/apply-contract-migration.php does for each
        // declared-contract migration the unattended path would not touch.
        foreach ($migrationFiles as $path) {
            $sql = (string) file_get_contents($path);
            if (MigrationSafety::isDeclaredContract($sql)) {
                $migrationRunner->run(basename($path));
            }
        }

        $firstMigrationRun = $migrationRunner->run();
        $secondMigrationRun = $migrationRunner->run();
        self::assert(count($firstMigrationRun['skipped']) === $migrationFileCount, 'First full unattended run after the supervised contract migration was applied was not a total no-op.');
        self::assert(count($secondMigrationRun['skipped']) === $migrationFileCount, 'Second migration run was not a no-op.');
        self::assert((int) $this->database->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn() === $migrationFileCount, 'Migration ledger count is incorrect.');

        $seedRunner = new SeedRunner($this->database, $this->root . '/database/seeds');
        $seedRunner->run();
        $seedCounts = $this->seedCounts();
        $seedRunner->run();
        $seedCountsAfterRerun = $this->seedCounts();
        self::assert($seedCounts === $seedCountsAfterRerun, 'Seed rerun changed generic row counts: ' . self::describeCountDiff($seedCounts, $seedCountsAfterRerun));

        $fixture = $this->createFixture();
        $authorizer = new ScopeAuthorizer($this->database);

        self::assert($authorizer->decide($fixture['representative'], 'membership.view', 'workspace', $fixture['scope_a'], $fixture['workspace_a'])->allowed, 'Representative should be allowed in the assigned workspace.');
        self::assert(!$authorizer->decide($fixture['representative'], 'membership.view', 'workspace', $fixture['scope_b'], $fixture['workspace_b'])->allowed, 'Representative leaked into another workspace.');
        self::assert(!$authorizer->decide($fixture['representative'], 'membership.view', 'workspace', $fixture['scope_a'], $fixture['workspace_b'])->allowed, 'Mismatched workspace context was accepted.');

        self::assert($authorizer->decide($fixture['multi_member'], 'workspace.view', 'workspace', $fixture['scope_a'], $fixture['workspace_a'])->allowed, 'Multi-workspace user was denied in workspace A.');
        self::assert($authorizer->decide($fixture['multi_member'], 'workspace.view', 'workspace', $fixture['scope_b'], $fixture['workspace_b'])->allowed, 'Multi-workspace user was denied in workspace B.');

        self::assert($authorizer->decide($fixture['global_admin'], 'payment.reconcile', 'workspace', $fixture['scope_a'], $fixture['workspace_a'])->allowed, 'Platform administrator was denied in workspace A.');
        self::assert($authorizer->decide($fixture['global_admin'], 'payment.reconcile', 'workspace', $fixture['scope_b'], $fixture['workspace_b'])->allowed, 'Platform administrator was denied in workspace B.');

        $this->assertAccountWorkspaceProjection($fixture);
        $this->assertDatabaseIsolation($fixture);
        $this->assertLegacyMapping($fixture);

        return 17;
    }

    /** @param array<string, string> $fixture */
    private function assertAccountWorkspaceProjection(array $fixture): void
    {
        $auth = new AuthService($this->database, new PasswordHasher(), new AuditLogger($this->database));
        $account = $auth->account(new AuthenticatedSession(
            Uuid::v7(),
            $fixture['multi_member'],
            'test-session-token',
            'test-csrf-token',
            $fixture['workspace_b'],
            gmdate('Y-m-d H:i:s.u', time() + 3600),
        ));
        self::assert(count($account['workspaces']) === 2, 'Account projection did not preserve multi-workspace membership.');
        foreach ($account['workspaces'] as $workspace) {
            self::assert(in_array('student', $workspace['role_keys'], true), 'Workspace projection omitted the effective student role.');
            self::assert(in_array('workspace.view', $workspace['permission_keys'], true), 'Workspace projection omitted the effective permission context.');
            self::assert((bool) $workspace['is_selected'] === ((string) $workspace['id'] === $fixture['workspace_b']), 'Selected workspace marker is inconsistent with the session.');
        }
    }

    /** @return array<string, int> */
    private function seedCounts(): array
    {
        return [
            'permissions' => (int) $this->database->query('SELECT COUNT(*) FROM rbac_permissions')->fetchColumn(),
            'roles' => (int) $this->database->query('SELECT COUNT(*) FROM rbac_role_templates')->fetchColumn(),
            'role_permissions' => (int) $this->database->query('SELECT COUNT(*) FROM rbac_role_permissions')->fetchColumn(),
            'resource_types' => (int) $this->database->query('SELECT COUNT(*) FROM content_resource_types')->fetchColumn(),
        ];
    }

    /**
     * Renders exactly which of seedCounts()'s tables changed between two
     * snapshots, and by how much -- a bare "counts differ" failure gives no
     * lead on which seed file (or cross-file interaction) is non-idempotent.
     *
     * @param array<string, int> $before
     * @param array<string, int> $after
     */
    private static function describeCountDiff(array $before, array $after): string
    {
        $parts = [];
        foreach ($after as $table => $count) {
            $previous = $before[$table] ?? null;
            if ($previous !== $count) {
                $parts[] = sprintf('%s: %s -> %d (%+d)', $table, $previous === null ? 'missing' : (string) $previous, $count, $count - (int) $previous);
            }
        }
        return $parts === [] ? 'no per-table difference detected (non-deterministic count?)' : implode(', ', $parts);
    }

    /** @return array<string, string> */
    private function createFixture(): array
    {
        $idNames = [
            'country', 'province', 'city_a', 'city_b', 'institution_a', 'institution_b',
            'faculty_a', 'faculty_b', 'program_a', 'program_b', 'cohort_a', 'cohort_b',
            'workspace_a', 'workspace_b', 'representative', 'multi_member', 'global_admin',
            'membership_rep_a', 'membership_multi_a', 'membership_multi_b',
            'scope_institution_a', 'scope_faculty_a', 'scope_program_a', 'scope_cohort_a', 'scope_a',
            'scope_institution_b', 'scope_faculty_b', 'scope_program_b', 'scope_cohort_b', 'scope_b',
            'course_a', 'course_b', 'term_a', 'term_b', 'source_system',
        ];
        $ids = [];
        foreach ($idNames as $name) {
            $ids[$name] = Uuid::v7();
        }
        $suffix = substr(str_replace('-', '', Uuid::v7()), -10);

        $this->insert('INSERT INTO directory_countries (id, code, name, status, created_at, updated_at) VALUES (:id, :code, :name, \'active\', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))', ['id' => $ids['country'], 'code' => strtoupper(substr($suffix, 0, 2)), 'name' => 'Fixture Country']);
        $this->insert('INSERT INTO directory_provinces (id, country_id, code, name, status, created_at, updated_at) VALUES (:id, :parent, :code, :name, \'active\', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))', ['id' => $ids['province'], 'parent' => $ids['country'], 'code' => 'p-' . $suffix, 'name' => 'Fixture Province']);
        foreach (['a', 'b'] as $side) {
            $this->insert('INSERT INTO directory_cities (id, province_id, code, name, status, created_at, updated_at) VALUES (:id, :parent, :code, :name, \'active\', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))', ['id' => $ids['city_' . $side], 'parent' => $ids['province'], 'code' => "city-{$side}-{$suffix}", 'name' => 'Fixture City ' . strtoupper($side)]);
            $this->insert('INSERT INTO directory_institutions (id, city_id, slug, name, institution_type, status, created_at, updated_at) VALUES (:id, :parent, :slug, :name, \'university\', \'active\', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))', ['id' => $ids['institution_' . $side], 'parent' => $ids['city_' . $side], 'slug' => "university-{$side}-{$suffix}", 'name' => 'Fixture University ' . strtoupper($side)]);
            $this->insert('INSERT INTO directory_faculties (id, institution_id, campus_id, code, name, status, created_at, updated_at) VALUES (:id, :parent, NULL, :code, :name, \'active\', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))', ['id' => $ids['faculty_' . $side], 'parent' => $ids['institution_' . $side], 'code' => "faculty-{$side}-{$suffix}", 'name' => 'Fixture Faculty ' . strtoupper($side)]);
            $this->insert('INSERT INTO directory_programs (id, faculty_id, department_id, code, name, degree_level, status, created_at, updated_at) VALUES (:id, :parent, NULL, :code, :name, \'professional\', \'active\', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))', ['id' => $ids['program_' . $side], 'parent' => $ids['faculty_' . $side], 'code' => "program-{$side}-{$suffix}", 'name' => 'Fixture Program ' . strtoupper($side)]);
            $this->insert('INSERT INTO directory_cohorts (id, program_id, entry_year, label, status, created_at, updated_at) VALUES (:id, :parent, :year, :label, \'active\', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))', ['id' => $ids['cohort_' . $side], 'parent' => $ids['program_' . $side], 'year' => $side === 'a' ? 2101 : 2102, 'label' => 'Fixture Cohort ' . strtoupper($side)]);
            $this->insert('INSERT INTO tenant_workspaces (id, cohort_id, slug, name, status, settings_json, created_at, updated_at) VALUES (:id, :parent, :slug, :name, \'active\', JSON_OBJECT(), UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))', ['id' => $ids['workspace_' . $side], 'parent' => $ids['cohort_' . $side], 'slug' => "workspace-{$side}-{$suffix}", 'name' => 'Fixture Workspace ' . strtoupper($side)]);
        }

        foreach (['representative', 'multi_member', 'global_admin'] as $user) {
            $this->insert('INSERT INTO iam_users (id, display_name, status, locale, created_at, updated_at) VALUES (:id, :name, \'active\', \'fa-IR\', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))', ['id' => $ids[$user], 'name' => str_replace('_', ' ', ucfirst($user))]);
        }

        $this->membership($ids['membership_rep_a'], $ids['workspace_a'], $ids['representative']);
        $this->membership($ids['membership_multi_a'], $ids['workspace_a'], $ids['multi_member']);
        $this->membership($ids['membership_multi_b'], $ids['workspace_b'], $ids['multi_member']);

        foreach (['a', 'b'] as $side) {
            $parent = '00000000-0000-7000-8000-000000000001';
            foreach (['institution', 'faculty', 'program', 'cohort'] as $type) {
                $scopeName = 'scope_' . $type . '_' . $side;
                $entityName = $type . '_' . $side;
                $this->scope($ids[$scopeName], $type, $ids[$entityName], null, $parent);
                $parent = $ids[$scopeName];
            }
            $this->scope($ids['scope_' . $side], 'workspace', $ids['workspace_' . $side], $ids['workspace_' . $side], $parent);
        }

        $this->assignment($ids['representative'], 'cohort-representative', $ids['scope_a']);
        $this->assignment($ids['multi_member'], 'student', $ids['scope_a']);
        $this->assignment($ids['multi_member'], 'student', $ids['scope_b']);
        $this->assignment($ids['global_admin'], 'platform-super-admin', '00000000-0000-7000-8000-000000000001');

        foreach (['a', 'b'] as $side) {
            $this->insert('INSERT INTO academic_terms (id, workspace_id, term_key, name, starts_on, ends_on, status, created_at, updated_at) VALUES (:id, :workspace, :term_key, :name, \'2026-01-01\', \'2026-06-30\', \'active\', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))', ['id' => $ids['term_' . $side], 'workspace' => $ids['workspace_' . $side], 'term_key' => "term-{$suffix}", 'name' => 'Fixture Term ' . strtoupper($side)]);
            $this->insert('INSERT INTO academic_courses (id, workspace_id, course_code, title, status, created_at, updated_at) VALUES (:id, :workspace, :code, :title, \'active\', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))', ['id' => $ids['course_' . $side], 'workspace' => $ids['workspace_' . $side], 'code' => 'SHARED-' . $suffix, 'title' => 'Fixture Course ' . strtoupper($side)]);
        }

        return $ids;
    }

    /** @param array<string, string> $fixture */
    private function assertDatabaseIsolation(array $fixture): void
    {
        $this->expectIntegrityViolation(function () use ($fixture): void {
            $this->insert('INSERT INTO academic_course_offerings (id, workspace_id, course_id, term_id, section_key, status, created_at, updated_at) VALUES (:id, :workspace, :course, :term, \'cross-tenant\', \'planned\', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))', ['id' => Uuid::v7(), 'workspace' => $fixture['workspace_a'], 'course' => $fixture['course_a'], 'term' => $fixture['term_b']]);
        }, 'Cross-workspace foreign key write was accepted.');

        $this->expectIntegrityViolation(function () use ($fixture): void {
            $code = $this->database->prepare('SELECT course_code FROM academic_courses WHERE id = :id');
            $code->execute(['id' => $fixture['course_a']]);
            $this->insert('INSERT INTO academic_courses (id, workspace_id, course_code, title, status, created_at, updated_at) VALUES (:id, :workspace, :code, \'Duplicate\', \'active\', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))', ['id' => Uuid::v7(), 'workspace' => $fixture['workspace_a'], 'code' => (string) $code->fetchColumn()]);
        }, 'Duplicate workspace-local course code was accepted.');

        $sameCodes = $this->database->prepare('SELECT COUNT(*) FROM academic_courses WHERE course_code = (SELECT course_code FROM academic_courses WHERE id = :course_id)');
        $sameCodes->execute(['course_id' => $fixture['course_a']]);
        self::assert((int) $sameCodes->fetchColumn() === 2, 'The same course code should be valid in two workspaces.');
    }

    /** @param array<string, string> $fixture */
    private function assertLegacyMapping(array $fixture): void
    {
        $this->insert('INSERT INTO migration_source_systems (id, source_key, description, mode, created_at) VALUES (:id, :key, \'Fixture read-only source\', \'read_only\', UTC_TIMESTAMP(6))', ['id' => $fixture['source_system'], 'key' => 'fixture-' . substr($fixture['source_system'], -8)]);
        $map = new LegacyIdMap($this->database, $this->legacyHmacKey);
        $first = $map->remember($fixture['source_system'], 'user', 'source-user-42', 'iam_user', $fixture['multi_member']);
        $second = $map->remember($fixture['source_system'], 'user', 'source-user-42', 'iam_user', $fixture['multi_member']);
        self::assert($first === $second, 'Legacy mapping rerun created a new mapping identity.');

        $count = $this->database->prepare('SELECT COUNT(*) FROM migration_legacy_id_mappings WHERE source_system_id = :source');
        $count->execute(['source' => $fixture['source_system']]);
        self::assert((int) $count->fetchColumn() === 1, 'Legacy mapping rerun duplicated a row.');

        $conflicted = false;
        try {
            $map->remember($fixture['source_system'], 'user', 'source-user-42', 'iam_user', $fixture['representative']);
        } catch (RuntimeException) {
            $conflicted = true;
        }
        self::assert($conflicted, 'Conflicting legacy remap was silently accepted.');
    }

    private function membership(string $id, string $workspaceId, string $userId): void
    {
        $this->insert('INSERT INTO tenant_workspace_memberships (id, workspace_id, user_id, status, joined_at, created_at, updated_at) VALUES (:id, :workspace, :user, \'active\', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))', ['id' => $id, 'workspace' => $workspaceId, 'user' => $userId]);
    }

    private function scope(string $id, string $type, string $entityId, ?string $workspaceId, string $parentId): void
    {
        $this->insert('INSERT INTO rbac_scopes (id, scope_type, entity_id, workspace_id, parent_scope_id, created_at) VALUES (:id, :type, :entity, :workspace, :parent, UTC_TIMESTAMP(6))', ['id' => $id, 'type' => $type, 'entity' => $entityId, 'workspace' => $workspaceId, 'parent' => $parentId]);
    }

    private function assignment(string $userId, string $roleKey, string $scopeId): void
    {
        $this->insert('INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at) SELECT :id, :user, id, :scope, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6) FROM rbac_role_templates WHERE role_key = :role', ['id' => Uuid::v7(), 'user' => $userId, 'scope' => $scopeId, 'role' => $roleKey]);
    }

    /** @param array<string, scalar|null> $parameters */
    private function insert(string $sql, array $parameters): void
    {
        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);
    }

    private function expectIntegrityViolation(callable $operation, string $message): void
    {
        try {
            $operation();
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                return;
            }
            throw $error;
        }
        throw new RuntimeException($message);
    }

    private static function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
