<?php

declare(strict_types=1);

namespace Fanoos\Tests\Core;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Core\ClassProvisioningService;
use Fanoos\Platform\Migration\SeedRunner;
use Fanoos\Platform\Onboarding\DirectoryReadService;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;

final class OnboardingDirectoryTest
{
    private int $assertions = 0;

    /** The 31 provinces ported verbatim from legacy/site/api/bot_onboarding.php:12-127. */
    private const KNOWN_PROVINCES = [
        'هرمزگان', 'همدان', 'خراسان شمالی', 'خوزستان', 'چهارمحال و بختیاری',
        'خراسان رضوی', 'سیستان و بلوچستان', 'اردبیل', 'مرکزی', 'آذربایجان غربی',
        'آذربایجان شرقی', 'کرمان', 'خراسان جنوبی', 'فارس', 'تهران', 'اصفهان',
        'البرز', 'ایلام', 'مازندران', 'بوشهر', 'زنجان', 'سمنان', 'قزوین', 'قم',
        'کردستان', 'کرمانشاه', 'گلستان', 'گیلان', 'لرستان', 'کهگیلویه و بویراحمد', 'یزد',
    ];
    private const KNOWN_INSTITUTION_COUNT = 106;

    public function __construct(private readonly PDO $database, private readonly string $root)
    {
    }

    public function run(): int
    {
        $seedRunner = new SeedRunner($this->database, $this->root . '/database/seeds');
        $seedRunner->run();

        $this->assertSeedContents();

        $beforeProvinces = $this->countKnownProvinces();
        $beforeInstitutions = $this->countLegacyInstitutions();
        $seedRunner->run();
        $this->assert($this->countKnownProvinces() === $beforeProvinces, 'Re-running the onboarding seed changed the province count.');
        $this->assert($this->countLegacyInstitutions() === $beforeInstitutions, 'Re-running the onboarding seed changed the institution count.');

        $this->assertSeededInstitutionResolvesWithoutDuplicate();
        $this->assertDirectoryReadPagination();
        $this->assertTenantIsolation();

        return $this->assertions;
    }

    private function assertSeedContents(): void
    {
        $this->assert($this->countKnownProvinces() === 31, 'Seed did not insert exactly the 31 legacy provinces.');
        $this->assert($this->countLegacyInstitutions() === self::KNOWN_INSTITUTION_COUNT, 'Seed did not insert exactly 106 legacy institutions.');

        $tehran = $this->database->prepare("SELECT COUNT(*) FROM directory_institutions WHERE name = 'دانشگاه علوم پزشکی تهران'");
        $tehran->execute();
        $this->assert((int) $tehran->fetchColumn() === 1, 'Seeded Tehran university of medical sciences institution is missing or duplicated.');

        $city = $this->database->prepare(<<<'SQL'
SELECT COUNT(*) FROM directory_cities city
JOIN directory_provinces province ON province.id = city.province_id
WHERE city.code = 'unspecified' AND province.name IN ('هرمزگان', 'ایلام', 'یزد')
SQL);
        $city->execute();
        $this->assert((int) $city->fetchColumn() === 3, 'Placeholder "unspecified" city was not created for every seeded province.');
    }

    /** A seeded institution resolves through ClassProvisioningService by its own
     * slug/city/province/country identity without creating a duplicate row --
     * this is the intended reuse pattern: a real join wizard reads the catalog
     * (DirectoryReadService) and passes the codes it read back verbatim, rather
     * than re-deriving them from free text. */
    private function assertSeededInstitutionResolvesWithoutDuplicate(): void
    {
        $row = $this->database->query(<<<'SQL'
SELECT institution.id, institution.slug, institution.name, institution.institution_type,
       city.code AS city_code, city.name AS city_name,
       province.code AS province_code, province.name AS province_name
FROM directory_institutions institution
JOIN directory_cities city ON city.id = institution.city_id
JOIN directory_provinces province ON province.id = city.province_id
WHERE institution.slug = 'legacy-060'
SQL)->fetch();
        if ($row === false) {
            throw new RuntimeException('Expected seeded institution legacy-060 to exist.');
        }

        $access = new AccessGate($this->database, new ScopeAuthorizer($this->database));
        $service = new ClassProvisioningService($this->database, $access, new AuditLogger($this->database));
        $suffix = substr(str_replace('-', '', Uuid::v7()), -10);
        $owner = $this->platformSuperAdmin('Onboarding Directory Owner ' . $suffix);

        // The identity below deliberately threads through the province code and city
        // code the seed used (read from the catalog), not just their names: province
        // and city codes are hash-derived from the name when omitted, so a caller
        // that supplied only names would make ClassProvisioningService compute a
        // different code than the one already seeded and insert a duplicate
        // province/city row instead of resolving the existing one. This is the
        // reuse pattern a real join wizard must follow too.
        $identity = [
            'country' => ['code' => 'IR', 'name' => 'ایران'],
            'province' => ['code' => (string) $row['province_code'], 'name' => (string) $row['province_name']],
            'city' => ['code' => (string) $row['city_code'], 'name' => (string) $row['city_name']],
            'institution' => ['slug' => (string) $row['slug'], 'name' => (string) $row['name'], 'institution_type' => (string) $row['institution_type']],
            'faculty' => ['name' => 'Fixture Faculty ' . $suffix],
            'program' => ['name' => 'Fixture Program ' . $suffix, 'degree_level' => 'professional-doctorate'],
            'cohort' => ['entry_year' => 1450, 'label' => 'Fixture Cohort ' . $suffix],
            'workspace' => ['name' => 'Fixture Class ' . $suffix],
        ];
        $result = $service->createClass($owner, $identity);
        $this->assert($result['institution_id'] === (string) $row['id'], 'Provisioning through a seeded institution slug resolved to a different institution row.');

        $count = $this->database->prepare('SELECT COUNT(*) FROM directory_institutions WHERE name = :name');
        $count->execute(['name' => (string) $row['name']]);
        $this->assert((int) $count->fetchColumn() === 1, 'Resolving a seeded institution through ClassProvisioningService created a duplicate row.');
    }

    private function assertDirectoryReadPagination(): void
    {
        $directory = new DirectoryReadService($this->database);

        $firstPage = $directory->provinces(10, null);
        $this->assert(count($firstPage['items']) === 10, 'First province page did not return exactly the page size.');
        $this->assert($firstPage['next_cursor'] !== null, 'Province list with more than a page of rows did not return a cursor.');
        $seen = array_map(static fn (array $item): string => (string) $item['id'], $firstPage['items']);
        $cursor = $firstPage['next_cursor'];
        $pages = 0;
        while ($cursor !== null && $pages < 10) {
            $page = $directory->provinces(10, $cursor);
            foreach ($page['items'] as $item) {
                $id = (string) $item['id'];
                $this->assert(!in_array($id, $seen, true), 'Province pagination returned the same row on two pages.');
                $seen[] = $id;
            }
            $cursor = $page['next_cursor'];
            $pages++;
        }
        $this->assert(count($seen) >= 31, 'Paginating through provinces did not surface every seeded province.');

        $hormozgan = $this->database->prepare("SELECT id FROM directory_provinces WHERE name = 'هرمزگان'");
        $hormozgan->execute();
        $hormozganId = (string) $hormozgan->fetchColumn();
        $expectedInHormozgan = $this->database->prepare(<<<'SQL'
SELECT COUNT(*) FROM directory_institutions institution
JOIN directory_cities city ON city.id = institution.city_id
WHERE city.province_id = :province
SQL);
        $expectedInHormozgan->execute(['province' => $hormozganId]);
        $expectedCount = (int) $expectedInHormozgan->fetchColumn();
        $this->assert($expectedCount >= 3, 'Fixture province for the pagination test needs at least 3 institutions.');

        $collected = [];
        $cursor = null;
        do {
            $page = $directory->institutionsByProvince($hormozganId, 2, $cursor);
            $this->assert(count($page['items']) <= 2, 'Institution page exceeded the requested limit.');
            foreach ($page['items'] as $item) {
                $this->assert(!in_array((string) $item['id'], $collected, true), 'Institution pagination returned a duplicate row across pages.');
                $collected[] = (string) $item['id'];
            }
            $cursor = $page['next_cursor'];
        } while ($cursor !== null);
        $this->assert(count($collected) === $expectedCount, 'Paginated institution listing did not exactly cover every institution filtered by province.');

        $ilam = $this->database->prepare("SELECT id FROM directory_provinces WHERE name = 'ایلام'");
        $ilam->execute();
        $ilamPage = $directory->institutionsByProvince((string) $ilam->fetchColumn(), 10, null);
        foreach ($ilamPage['items'] as $item) {
            $this->assert(!in_array((string) $item['id'], $collected, true), 'Institution list for a different province leaked a Hormozgan institution.');
        }
    }

    private function assertTenantIsolation(): void
    {
        $access = new AccessGate($this->database, new ScopeAuthorizer($this->database));
        $service = new ClassProvisioningService($this->database, $access, new AuditLogger($this->database));
        $directory = new DirectoryReadService($this->database);
        $suffix = substr(str_replace('-', '', Uuid::v7()), -10);
        $owner = $this->platformSuperAdmin('Onboarding Isolation Owner ' . $suffix);

        $sideA = $this->provisionFixtureClass($service, $owner, $suffix . '-a', 1451);
        $sideB = $this->provisionFixtureClass($service, $owner, $suffix . '-b', 1452);
        $this->assert($sideA['workspace_id'] !== $sideB['workspace_id'], 'Two isolated fixture classes collapsed into one workspace.');

        $provinceA = $this->database->prepare("SELECT id FROM directory_provinces WHERE name = :name");
        $provinceA->execute(['name' => 'Fixture Province ' . $suffix . '-a']);
        $provinceAId = (string) $provinceA->fetchColumn();
        $listing = $directory->institutionsByProvince($provinceAId, 10, null);
        $this->assert(count($listing['items']) === 1, 'Institution listing for an isolated fixture province returned more than its own institution.');
        foreach ($listing['items'] as $item) {
            $this->assert(!array_key_exists('workspace_id', $item), 'Institution read leaked a workspace_id field.');
            $this->assert($item['name'] === 'Fixture University ' . $suffix . '-a', 'Institution listing for province A returned a different institution.');
        }

        $facultiesA = $directory->facultiesByInstitution((string) $listing['items'][0]['id'], 10, null);
        foreach ($facultiesA['items'] as $faculty) {
            $this->assert(!array_key_exists('workspace_id', $faculty) && !array_key_exists('cohort_id', $faculty), 'Faculty read leaked tenant/workspace identity.');
        }
        $programsA = $directory->programsByFaculty((string) $facultiesA['items'][0]['id'], 10, null);
        foreach ($programsA['items'] as $program) {
            $this->assert(!array_key_exists('workspace_id', $program) && !array_key_exists('cohort_id', $program), 'Program read leaked tenant/workspace identity.');
        }
    }

    /** @return array<string,mixed> */
    private function provisionFixtureClass(ClassProvisioningService $service, string $owner, string $suffix, int $entryYear): array
    {
        return $service->createClass($owner, [
            'country' => ['code' => 'ZZ', 'name' => 'Fixture Country'],
            'province' => ['name' => 'Fixture Province ' . $suffix],
            'city' => ['name' => 'Fixture City ' . $suffix],
            'institution' => ['name' => 'Fixture University ' . $suffix],
            'faculty' => ['name' => 'Fixture Faculty ' . $suffix],
            'program' => ['name' => 'Fixture Program ' . $suffix, 'degree_level' => 'professional-doctorate'],
            'cohort' => ['entry_year' => $entryYear, 'label' => 'Fixture Cohort ' . $suffix],
            'workspace' => ['name' => 'Fixture Class ' . $suffix],
        ]);
    }

    private function countKnownProvinces(): int
    {
        $placeholders = implode(',', array_fill(0, count(self::KNOWN_PROVINCES), '?'));
        $query = $this->database->prepare("SELECT COUNT(DISTINCT name) FROM directory_provinces WHERE name IN ({$placeholders})");
        $query->execute(self::KNOWN_PROVINCES);
        return (int) $query->fetchColumn();
    }

    private function countLegacyInstitutions(): int
    {
        return (int) $this->database->query("SELECT COUNT(*) FROM directory_institutions WHERE slug LIKE 'legacy-%'")->fetchColumn();
    }

    private function platformSuperAdmin(string $displayName): string
    {
        $id = Uuid::v7();
        $this->database->prepare("INSERT INTO iam_users (id, display_name, status, locale, created_at, updated_at) VALUES (:id, :name, 'active', 'fa-IR', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => $id, 'name' => $displayName]);
        $role = $this->database->prepare("SELECT id FROM rbac_role_templates WHERE role_key = 'platform-super-admin' LIMIT 1");
        $role->execute();
        $roleId = $role->fetchColumn();
        if ($roleId === false) {
            throw new RuntimeException('Role template is missing: platform-super-admin');
        }
        $this->database->prepare('INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at) VALUES (:id, :user, :role, :scope, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))')
            ->execute(['id' => Uuid::v7(), 'user' => $id, 'role' => $roleId, 'scope' => '00000000-0000-7000-8000-000000000001']);
        return $id;
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
