<?php

declare(strict_types=1);

namespace Fanoos\Tests\Core;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Core\ClassProvisioningService;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;

final class ClassProvisioningTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $access = new AccessGate($this->database, new ScopeAuthorizer($this->database));
        $service = new ClassProvisioningService($this->database, $access, new AuditLogger($this->database));

        $suffix = substr(str_replace('-', '', Uuid::v7()), -10);
        $owner = $this->platformSuperAdmin('Class Provisioning Owner ' . $suffix);
        $plainUser = $this->plainUser('Class Provisioning Bystander ' . $suffix);

        // 1) A platform owner creates a class from a full identity.
        $identity = $this->identity($suffix, 1402, 'ورودی ۱۴۰۲');
        $result = $service->createClass($owner, $identity);
        self::assert($result['workspace_created'] === true, 'First provisioning call did not report the workspace as newly created.');
        self::assert($result['workspace_id'] !== '', 'Provisioning did not return a workspace id.');

        // The resulting workspace resolves through the normal tenancy path: its own
        // scope exists, and a role granted higher in the chain (faculty) cascades
        // down through cohort/program/faculty parent scopes to the new workspace.
        $decision = $access->workspace($owner, $result['workspace_id'], 'workspace.view');
        self::assert($decision->allowed === true, 'Platform owner could not resolve authorization on the newly provisioned workspace.');
        $facultyScopeId = $this->scopeIdFor('faculty', $result['faculty_id']);
        $facultyAdmin = $this->plainUser('Class Provisioning Faculty Admin ' . $suffix);
        $this->assignRole($facultyAdmin, 'faculty-admin', $facultyScopeId);
        $cascaded = $access->workspace($facultyAdmin, $result['workspace_id'], 'workspace.view');
        self::assert($cascaded->allowed === true, 'A role granted at the faculty scope did not cascade to the workspace created under it.');

        // Audit record was written.
        $audit = $this->database->prepare("SELECT COUNT(*) FROM audit_events WHERE action = 'workspace.provision' AND subject_id = :workspace AND outcome = 'success'");
        $audit->execute(['workspace' => $result['workspace_id']]);
        self::assert((int) $audit->fetchColumn() === 1, 'Class provisioning did not write an audit record.');

        // 2) The same identity submitted twice yields the same workspace and no
        // duplicated directory rows at any level of the chain.
        $repeat = $service->createClass($owner, $identity);
        self::assert($repeat['workspace_id'] === $result['workspace_id'], 'Re-running provisioning with the same identity created a different workspace.');
        self::assert($repeat['workspace_created'] === false, 'Re-running provisioning did not report the workspace as already existing.');
        $this->assertSingleRow('directory_institutions', 'name', $identity['institution']['name']);
        $this->assertSingleRow('directory_faculties', 'name', $identity['faculty']['name']);
        $this->assertSingleRow('directory_programs', 'name', $identity['program']['name']);
        $this->assertSingleRow('directory_cohorts', 'label', $identity['cohort']['label']);
        $this->assertSingleRow('tenant_workspaces', 'id', $result['workspace_id']);

        // 3) Two different cohorts under the same program produce two distinct
        // workspaces sharing one program row.
        $secondCohort = $this->identity($suffix, 1403, 'ورودی ۱۴۰۳');
        $secondResult = $service->createClass($owner, $secondCohort);
        self::assert($secondResult['program_id'] === $result['program_id'], 'Two cohorts of the same program resolved to different program rows.');
        self::assert($secondResult['workspace_id'] !== $result['workspace_id'], 'Two distinct cohorts collapsed into the same workspace.');
        self::assert($secondResult['cohort_id'] !== $result['cohort_id'], 'Two distinct entry years collapsed into the same cohort.');

        // 4) A user without the permission is refused, including a
        // platform-super-admin-adjacent but workspace-scoped role.
        $this->expectCode('forbidden', fn () => $service->createClass($plainUser, $this->identity($suffix . '-deny', 1404, 'Denied')));
        $workspaceAdmin = $this->plainUser('Class Provisioning Workspace Admin ' . $suffix);
        $workspaceScopeId = $this->scopeIdFor('workspace', $result['workspace_id']);
        $this->assignRole($workspaceAdmin, 'workspace-admin', $workspaceScopeId);
        $this->expectCode('forbidden', fn () => $service->createClass($workspaceAdmin, $this->identity($suffix . '-deny2', 1405, 'Denied')));

        // 5) Invalid input is rejected before anything is written, leaving no
        // partial chain behind.
        $missingYear = $this->identity($suffix . '-bad-year', 1406, 'Bad');
        unset($missingYear['cohort']['entry_year']);
        $this->expectCode('invalid_entry_year', fn () => $service->createClass($owner, $missingYear));

        $absurdYear = $this->identity($suffix . '-absurd', 1406, 'Bad');
        $absurdYear['cohort']['entry_year'] = 99999;
        $this->expectCode('invalid_entry_year', fn () => $service->createClass($owner, $absurdYear));

        $malformedName = $this->identity($suffix . '-malformed', 1407, 'Bad');
        $malformedName['institution']['name'] = '';
        $this->expectCode('invalid_class_identity', fn () => $service->createClass($owner, $malformedName));
        $this->assertNoRow('directory_provinces', 'name', 'Fixture Province ' . $suffix . '-malformed');

        return $this->assertions;
    }

    /** @return array<string,mixed> */
    private function identity(string $suffix, int $entryYear, string $label): array
    {
        return [
            'country' => ['code' => 'ZZ', 'name' => 'Fixture Country'],
            'province' => ['name' => 'Fixture Province ' . $suffix],
            'city' => ['name' => 'Fixture City ' . $suffix],
            'institution' => ['name' => 'Fixture University ' . $suffix],
            'faculty' => ['name' => 'Fixture Faculty ' . $suffix],
            'program' => ['name' => 'Fixture Program ' . $suffix, 'degree_level' => 'professional-doctorate'],
            'cohort' => ['entry_year' => $entryYear, 'label' => $label . ' ' . $suffix],
            'workspace' => ['name' => 'Fixture Class ' . $suffix . ' ' . $entryYear],
        ];
    }

    private function platformSuperAdmin(string $displayName): string
    {
        $id = Uuid::v7();
        $this->database->prepare("INSERT INTO iam_users (id, display_name, status, locale, created_at, updated_at) VALUES (:id, :name, 'active', 'fa-IR', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => $id, 'name' => $displayName]);
        $this->assignRole($id, 'platform-super-admin', '00000000-0000-7000-8000-000000000001');
        return $id;
    }

    private function plainUser(string $displayName): string
    {
        $id = Uuid::v7();
        $this->database->prepare("INSERT INTO iam_users (id, display_name, status, locale, created_at, updated_at) VALUES (:id, :name, 'active', 'fa-IR', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => $id, 'name' => $displayName]);
        return $id;
    }

    private function assignRole(string $userId, string $roleKey, string $scopeId): void
    {
        $role = $this->database->prepare('SELECT id FROM rbac_role_templates WHERE role_key = :role LIMIT 1');
        $role->execute(['role' => $roleKey]);
        $roleId = $role->fetchColumn();
        if ($roleId === false) {
            throw new RuntimeException("Role template is missing: {$roleKey}");
        }
        $this->database->prepare('INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at) VALUES (:id, :user, :role, :scope, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))')
            ->execute(['id' => Uuid::v7(), 'user' => $userId, 'role' => $roleId, 'scope' => $scopeId]);
    }

    private function scopeIdFor(string $scopeType, string $entityId): string
    {
        $query = $this->database->prepare('SELECT id FROM rbac_scopes WHERE scope_type = :type AND entity_id = :entity LIMIT 1');
        $query->execute(['type' => $scopeType, 'entity' => $entityId]);
        $id = $query->fetchColumn();
        if ($id === false) {
            throw new RuntimeException("Expected a {$scopeType} authorization scope to exist for {$entityId}.");
        }
        return (string) $id;
    }

    private function assertSingleRow(string $table, string $column, string $value): void
    {
        $query = $this->database->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = :value");
        $query->execute(['value' => $value]);
        self::assert((int) $query->fetchColumn() === 1, "Expected exactly one row in {$table} where {$column} = {$value}.");
    }

    private function assertNoRow(string $table, string $column, string $value): void
    {
        $query = $this->database->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = :value");
        $query->execute(['value' => $value]);
        self::assert((int) $query->fetchColumn() === 0, "Expected no row in {$table} where {$column} = {$value} after a rejected request.");
    }

    private function expectCode(string $code, callable $operation): void
    {
        ++$this->assertions;
        try {
            $operation();
        } catch (PlatformException $error) {
            if ($error->errorCode === $code) {
                return;
            }
            throw new RuntimeException("Expected {$code}, got {$error->errorCode}.");
        }
        throw new RuntimeException("Expected PlatformException {$code}.");
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
