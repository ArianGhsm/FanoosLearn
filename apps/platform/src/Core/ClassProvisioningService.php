<?php

declare(strict_types=1);

namespace Fanoos\Platform\Core;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\TextNormalizer;
use Fanoos\Platform\Support\Transaction;
use Fanoos\Platform\Support\Uuid;
use PDO;

/**
 * Owner-only class (workspace) provisioning: resolves or creates the directory
 * chain (country -> province -> city -> institution -> faculty -> [department]
 * -> program -> cohort) an owner names and creates the tenant_workspaces row
 * at its leaf, idempotently, in one transaction.
 */
final class ClassProvisioningService
{
    private const PLATFORM_SCOPE_ID = '00000000-0000-7000-8000-000000000001';

    public function __construct(
        private readonly PDO $database,
        private readonly AccessGate $access,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @param array<string,mixed> $identity @return array<string,mixed> */
    public function createClass(string $actorUserId, array $identity): array
    {
        $this->access->requirePlatform($actorUserId, 'workspace.provision');
        $spec = $this->validateIdentity($identity);

        return Transaction::run($this->database, function () use ($actorUserId, $spec): array {
            $countryId = $this->findOrCreateCountry($spec['country']);
            $provinceId = $this->findOrCreateProvince($countryId, $spec['province']);
            $cityId = $this->findOrCreateCity($provinceId, $spec['city']);
            $institutionId = $this->findOrCreateInstitution($cityId, $spec['institution']);
            $institutionScope = $this->ensureScope('institution', $institutionId, null, self::PLATFORM_SCOPE_ID);

            $facultyId = $this->findOrCreateFaculty($institutionId, $spec['faculty']);
            $facultyScope = $this->ensureScope('faculty', $facultyId, null, $institutionScope);

            $departmentId = $spec['department'] === null ? null : $this->findOrCreateDepartment($facultyId, $spec['department']);

            $programId = $this->findOrCreateProgram($facultyId, $departmentId, $spec['program']);
            $programScope = $this->ensureScope('program', $programId, null, $facultyScope);

            $cohortId = $this->findOrCreateCohort($programId, $spec['cohort']);
            $cohortScope = $this->ensureScope('cohort', $cohortId, null, $programScope);

            [$workspaceId, $workspaceSlug, $workspaceCreated] = $this->findOrCreateWorkspace($cohortId, $spec['workspace']);
            $this->ensureScope('workspace', $workspaceId, $workspaceId, $cohortScope);

            $this->audit->record(
                $workspaceId,
                $actorUserId,
                'workspace.provision',
                'tenant_workspace',
                $workspaceId,
                'success',
                ['created' => $workspaceCreated, 'cohort_id' => $cohortId, 'program_id' => $programId],
            );

            return [
                'workspace_id' => $workspaceId,
                'workspace_slug' => $workspaceSlug,
                'workspace_created' => $workspaceCreated,
                'cohort_id' => $cohortId,
                'program_id' => $programId,
                'faculty_id' => $facultyId,
                'institution_id' => $institutionId,
            ];
        });
    }

    private function findOrCreateCountry(array $spec): string
    {
        $existing = $this->database->prepare('SELECT id FROM directory_countries WHERE code = :code FOR UPDATE');
        $existing->execute(['code' => $spec['code']]);
        $id = $existing->fetchColumn();
        if ($id !== false) {
            return (string) $id;
        }
        $id = Uuid::v7();
        $this->database->prepare(<<<'SQL'
INSERT INTO directory_countries (id, code, name, status, created_at, updated_at)
VALUES (:id, :code, :name, 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL)->execute(['id' => $id, 'code' => $spec['code'], 'name' => $spec['name']]);
        return $id;
    }

    private function findOrCreateProvince(string $countryId, array $spec): string
    {
        $code = $this->deriveCode($spec['name'], $spec['code'], 32);
        $existing = $this->database->prepare('SELECT id FROM directory_provinces WHERE country_id = :country AND code = :code FOR UPDATE');
        $existing->execute(['country' => $countryId, 'code' => $code]);
        $id = $existing->fetchColumn();
        if ($id !== false) {
            return (string) $id;
        }
        $id = Uuid::v7();
        $this->database->prepare(<<<'SQL'
INSERT INTO directory_provinces (id, country_id, code, name, status, created_at, updated_at)
VALUES (:id, :country, :code, :name, 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL)->execute(['id' => $id, 'country' => $countryId, 'code' => $code, 'name' => $spec['name']]);
        return $id;
    }

    private function findOrCreateCity(string $provinceId, array $spec): string
    {
        $code = $this->deriveCode($spec['name'], $spec['code'], 48);
        $existing = $this->database->prepare('SELECT id FROM directory_cities WHERE province_id = :province AND code = :code FOR UPDATE');
        $existing->execute(['province' => $provinceId, 'code' => $code]);
        $id = $existing->fetchColumn();
        if ($id !== false) {
            return (string) $id;
        }
        $id = Uuid::v7();
        $this->database->prepare(<<<'SQL'
INSERT INTO directory_cities (id, province_id, code, name, status, created_at, updated_at)
VALUES (:id, :province, :code, :name, 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL)->execute(['id' => $id, 'province' => $provinceId, 'code' => $code, 'name' => $spec['name']]);
        return $id;
    }

    private function findOrCreateInstitution(string $cityId, array $spec): string
    {
        $slug = $this->deriveCode($spec['name'], $spec['slug'], 120, $cityId);
        $existing = $this->database->prepare('SELECT id, city_id FROM directory_institutions WHERE slug = :slug FOR UPDATE');
        $existing->execute(['slug' => $slug]);
        $row = $existing->fetch();
        if ($row !== false) {
            if ((string) $row['city_id'] !== $cityId) {
                throw new PlatformException('institution_identity_conflict', 'This institution identity already resolves to a different city.', 409);
            }
            return (string) $row['id'];
        }
        $id = Uuid::v7();
        $this->database->prepare(<<<'SQL'
INSERT INTO directory_institutions (id, city_id, slug, name, institution_type, status, created_at, updated_at)
VALUES (:id, :city, :slug, :name, :type, 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL)->execute(['id' => $id, 'city' => $cityId, 'slug' => $slug, 'name' => $spec['name'], 'type' => $spec['type']]);
        return $id;
    }

    private function findOrCreateFaculty(string $institutionId, array $spec): string
    {
        $code = $this->deriveCode($spec['name'], $spec['code'], 64);
        $existing = $this->database->prepare('SELECT id FROM directory_faculties WHERE institution_id = :institution AND code = :code FOR UPDATE');
        $existing->execute(['institution' => $institutionId, 'code' => $code]);
        $id = $existing->fetchColumn();
        if ($id !== false) {
            return (string) $id;
        }
        $id = Uuid::v7();
        $this->database->prepare(<<<'SQL'
INSERT INTO directory_faculties (id, institution_id, campus_id, code, name, status, created_at, updated_at)
VALUES (:id, :institution, NULL, :code, :name, 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL)->execute(['id' => $id, 'institution' => $institutionId, 'code' => $code, 'name' => $spec['name']]);
        return $id;
    }

    private function findOrCreateDepartment(string $facultyId, array $spec): string
    {
        $code = $this->deriveCode($spec['name'], $spec['code'], 64);
        $existing = $this->database->prepare('SELECT id FROM directory_departments WHERE faculty_id = :faculty AND code = :code FOR UPDATE');
        $existing->execute(['faculty' => $facultyId, 'code' => $code]);
        $id = $existing->fetchColumn();
        if ($id !== false) {
            return (string) $id;
        }
        $id = Uuid::v7();
        $this->database->prepare(<<<'SQL'
INSERT INTO directory_departments (id, faculty_id, code, name, status, created_at, updated_at)
VALUES (:id, :faculty, :code, :name, 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL)->execute(['id' => $id, 'faculty' => $facultyId, 'code' => $code, 'name' => $spec['name']]);
        return $id;
    }

    private function findOrCreateProgram(string $facultyId, ?string $departmentId, array $spec): string
    {
        $code = $this->deriveCode($spec['name'], $spec['code'], 64);
        $existing = $this->database->prepare('SELECT id FROM directory_programs WHERE faculty_id = :faculty AND code = :code FOR UPDATE');
        $existing->execute(['faculty' => $facultyId, 'code' => $code]);
        $id = $existing->fetchColumn();
        if ($id !== false) {
            return (string) $id;
        }
        $id = Uuid::v7();
        $this->database->prepare(<<<'SQL'
INSERT INTO directory_programs (id, faculty_id, department_id, code, name, degree_level, status, created_at, updated_at)
VALUES (:id, :faculty, :department, :code, :name, :degree, 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL)->execute([
            'id' => $id, 'faculty' => $facultyId, 'department' => $departmentId,
            'code' => $code, 'name' => $spec['name'], 'degree' => $spec['degree_level'],
        ]);
        return $id;
    }

    private function findOrCreateCohort(string $programId, array $spec): string
    {
        $existing = $this->database->prepare('SELECT id FROM directory_cohorts WHERE program_id = :program AND entry_year = :year FOR UPDATE');
        $existing->execute(['program' => $programId, 'year' => $spec['entry_year']]);
        $id = $existing->fetchColumn();
        if ($id !== false) {
            return (string) $id;
        }
        $id = Uuid::v7();
        $this->database->prepare(<<<'SQL'
INSERT INTO directory_cohorts (id, program_id, entry_year, label, status, created_at, updated_at)
VALUES (:id, :program, :year, :label, 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL)->execute(['id' => $id, 'program' => $programId, 'year' => $spec['entry_year'], 'label' => $spec['label']]);
        return $id;
    }

    /** @return array{0:string,1:string,2:bool} */
    private function findOrCreateWorkspace(string $cohortId, array $spec): array
    {
        $existing = $this->database->prepare(<<<'SQL'
SELECT id, slug FROM tenant_workspaces WHERE cohort_id = :cohort AND archived_at IS NULL ORDER BY created_at ASC LIMIT 1 FOR UPDATE
SQL);
        $existing->execute(['cohort' => $cohortId]);
        $row = $existing->fetch();
        if ($row !== false) {
            return [(string) $row['id'], (string) $row['slug'], false];
        }
        $id = Uuid::v7();
        $base = $this->asciiToken($spec['name']);
        $hash = substr(hash('sha256', $cohortId), 0, 10);
        $slug = substr($base === '' ? $hash : substr($base, 0, 100) . '-' . $hash, 0, 120);
        $this->database->prepare(<<<'SQL'
INSERT INTO tenant_workspaces (id, cohort_id, slug, name, status, settings_json, version, created_at, updated_at)
VALUES (:id, :cohort, :slug, :name, 'active', :settings, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL)->execute(['id' => $id, 'cohort' => $cohortId, 'slug' => $slug, 'name' => $spec['name'], 'settings' => '{}']);
        return [$id, $slug, true];
    }

    private function ensureScope(string $scopeType, string $entityId, ?string $workspaceId, string $parentScopeId): string
    {
        $existing = $this->database->prepare('SELECT id FROM rbac_scopes WHERE scope_type = :type AND entity_id = :entity FOR UPDATE');
        $existing->execute(['type' => $scopeType, 'entity' => $entityId]);
        $id = $existing->fetchColumn();
        if ($id !== false) {
            return (string) $id;
        }
        $id = Uuid::v7();
        $this->database->prepare(<<<'SQL'
INSERT INTO rbac_scopes (id, scope_type, entity_id, workspace_id, parent_scope_id, created_at)
VALUES (:id, :type, :entity, :workspace, :parent, UTC_TIMESTAMP(6))
SQL)->execute(['id' => $id, 'type' => $scopeType, 'entity' => $entityId, 'workspace' => $workspaceId, 'parent' => $parentScopeId]);
        return $id;
    }

    /** @param array<string,mixed> $identity @return array<string,mixed> */
    private function validateIdentity(array $identity): array
    {
        foreach (['country', 'province', 'city', 'institution', 'faculty', 'program', 'cohort', 'workspace'] as $key) {
            if (!isset($identity[$key]) || !is_array($identity[$key])) {
                throw new PlatformException('invalid_class_identity', "Class identity is missing required section: {$key}.", 422);
            }
        }
        if (array_key_exists('department', $identity) && $identity['department'] !== null && !is_array($identity['department'])) {
            throw new PlatformException('invalid_class_identity', 'Class identity department section is invalid.', 422);
        }

        $countryCode = strtoupper(trim((string) ($identity['country']['code'] ?? '')));
        if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
            throw new PlatformException('invalid_country_code', 'Country code must be a 2-letter ASCII code (e.g. IR).', 422);
        }
        $countryName = $this->requireText($identity['country']['name'] ?? null, 1, 160, 'country.name');

        $provinceName = $this->requireText($identity['province']['name'] ?? null, 1, 160, 'province.name');
        $provinceCode = $this->optionalCode($identity['province']['code'] ?? null, 32);

        $cityName = $this->requireText($identity['city']['name'] ?? null, 1, 160, 'city.name');
        $cityCode = $this->optionalCode($identity['city']['code'] ?? null, 48);

        $institutionName = $this->requireText($identity['institution']['name'] ?? null, 1, 200, 'institution.name');
        $institutionSlug = $this->optionalCode($identity['institution']['slug'] ?? null, 120);
        $institutionType = isset($identity['institution']['institution_type'])
            ? $this->requireText($identity['institution']['institution_type'], 1, 32, 'institution.institution_type')
            : 'university';

        $facultyName = $this->requireText($identity['faculty']['name'] ?? null, 1, 200, 'faculty.name');
        $facultyCode = $this->optionalCode($identity['faculty']['code'] ?? null, 64);

        $department = null;
        $departmentInput = $identity['department'] ?? null;
        if ($departmentInput !== null) {
            $department = [
                'name' => $this->requireText($departmentInput['name'] ?? null, 1, 200, 'department.name'),
                'code' => $this->optionalCode($departmentInput['code'] ?? null, 64),
            ];
        }

        $programName = $this->requireText($identity['program']['name'] ?? null, 1, 200, 'program.name');
        $programCode = $this->optionalCode($identity['program']['code'] ?? null, 64);
        $degreeLevel = $this->requireText($identity['program']['degree_level'] ?? null, 1, 48, 'program.degree_level');

        $entryYearRaw = $identity['cohort']['entry_year'] ?? null;
        if (!is_int($entryYearRaw) && !(is_string($entryYearRaw) && ctype_digit($entryYearRaw))) {
            throw new PlatformException('invalid_entry_year', 'Cohort entry year must be an integer.', 422);
        }
        $entryYear = (int) $entryYearRaw;
        if ($entryYear < 1000 || $entryYear > 9999) {
            throw new PlatformException('invalid_entry_year', 'Cohort entry year is out of range.', 422);
        }
        $cohortLabel = $this->requireText($identity['cohort']['label'] ?? null, 1, 160, 'cohort.label');

        $workspaceName = $this->requireText($identity['workspace']['name'] ?? null, 1, 200, 'workspace.name');

        return [
            'country' => ['code' => $countryCode, 'name' => $countryName],
            'province' => ['code' => $provinceCode, 'name' => $provinceName],
            'city' => ['code' => $cityCode, 'name' => $cityName],
            'institution' => ['slug' => $institutionSlug, 'name' => $institutionName, 'type' => $institutionType],
            'faculty' => ['code' => $facultyCode, 'name' => $facultyName],
            'department' => $department,
            'program' => ['code' => $programCode, 'name' => $programName, 'degree_level' => $degreeLevel],
            'cohort' => ['entry_year' => $entryYear, 'label' => $cohortLabel],
            'workspace' => ['name' => $workspaceName],
        ];
    }

    private function requireText(mixed $value, int $min, int $max, string $field): string
    {
        if (!is_string($value)) {
            throw new PlatformException('invalid_class_identity', "Class identity field is invalid: {$field}.", 422);
        }
        $trimmed = trim($value);
        $length = mb_strlen($trimmed);
        if ($length < $min || $length > $max) {
            throw new PlatformException('invalid_class_identity', "Class identity field is invalid: {$field}.", 422);
        }
        return $trimmed;
    }

    private function optionalCode(mixed $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new PlatformException('invalid_identity_code', 'A provided directory code must be a string.', 422);
        }
        $normalized = $this->asciiToken($value);
        if ($normalized === '' || strlen($normalized) > $maxLength) {
            throw new PlatformException('invalid_identity_code', 'A provided directory code must be a short ASCII identifier.', 422);
        }
        return $normalized;
    }

    private function deriveCode(string $name, ?string $explicit, int $maxLength, string $salt = ''): string
    {
        if ($explicit !== null) {
            return $explicit;
        }
        $slug = $this->asciiToken($name);
        $hash = substr(hash('sha256', TextNormalizer::normalize($name) . '|' . $salt), 0, 10);
        $derived = $slug === '' ? $hash : substr($slug, 0, max(0, $maxLength - 11)) . '-' . $hash;
        return substr($derived, 0, $maxLength);
    }

    private function asciiToken(string $value): string
    {
        $ascii = strtolower(trim($value));
        $ascii = preg_replace('/[^a-z0-9]+/', '-', $ascii) ?? '';
        return trim($ascii, '-');
    }
}
