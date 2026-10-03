<?php

declare(strict_types=1);

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Core\ClassProvisioningService;
use Fanoos\Platform\Support\DatabaseConnection;

/**
 * Creates the workspace that becomes a discipline's library (the shared
 * space every student of that discipline joins -- see migration 0024), under
 * an institution that is already in the directory, then hands it to
 * provision-discipline-library.php to mark it as the library.
 *
 * Nothing about the institution is typed by hand: its city, province and
 * country are read from the directory, so the chain resolves to the rows
 * that already exist instead of creating look-alikes.
 *
 * Without --execute it only prints what it would create.
 *
 * Usage:
 *   php scripts/ops/create-library-workspace.php --discipline=dentistry --institution=<institution slug>
 *       --faculty="دانشکده دندانپزشکی" --program="دندانپزشکی عمومی" --name="کتابخانه‌ی دندانپزشکی"
 *       --actor=<platform owner uuid> [--degree=professional-doctorate] [--entry-year=1405] [--execute]
 */

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/u', $argument, $m) !== 1) {
        fwrite(STDERR, "Unknown argument: {$argument}\n");
        exit(2);
    }
    $options[$m[1]] = $m[2] ?? '1';
}

try {
    foreach (['discipline', 'institution', 'faculty', 'program', 'name', 'actor'] as $required) {
        if (trim((string) ($options[$required] ?? '')) === '') {
            throw new RuntimeException("--{$required} is required (see the header of this file)");
        }
    }
    $database = DatabaseConnection::fromEnvironment();

    $chain = $database->prepare(<<<'SQL'
SELECT institution.slug, institution.name AS institution_name, institution.institution_type,
       city.code AS city_code, city.name AS city_name,
       province.code AS province_code, province.name AS province_name,
       country.code AS country_code, country.name AS country_name
FROM directory_institutions institution
JOIN directory_cities city ON city.id = institution.city_id
JOIN directory_provinces province ON province.id = city.province_id
JOIN directory_countries country ON country.id = province.country_id
WHERE institution.slug = :slug AND institution.status = 'active' AND institution.archived_at IS NULL
SQL);
    $chain->execute(['slug' => $options['institution']]);
    $row = $chain->fetch();
    if ($row === false) {
        throw new RuntimeException("No active institution with slug {$options['institution']}.");
    }

    $discipline = $database->prepare('SELECT library_workspace_id FROM academic_disciplines WHERE code = :code');
    $discipline->execute(['code' => $options['discipline']]);
    $library = $discipline->fetch();
    if ($library === false) {
        throw new RuntimeException("No discipline with code {$options['discipline']}.");
    }
    if ($library['library_workspace_id'] !== null) {
        throw new RuntimeException("Discipline {$options['discipline']} already has a library: {$library['library_workspace_id']}.");
    }

    $identity = [
        'country' => ['code' => $row['country_code'], 'name' => $row['country_name']],
        'province' => ['code' => $row['province_code'], 'name' => $row['province_name']],
        'city' => ['code' => $row['city_code'], 'name' => $row['city_name']],
        'institution' => ['slug' => $row['slug'], 'name' => $row['institution_name'], 'institution_type' => $row['institution_type']],
        'faculty' => ['name' => $options['faculty']],
        'department' => null,
        'program' => ['name' => $options['program'], 'degree_level' => $options['degree'] ?? 'professional-doctorate'],
        'cohort' => ['entry_year' => (int) ($options['entry-year'] ?? 1405), 'label' => 'همه‌ی ورودی‌ها'],
        'workspace' => ['name' => $options['name']],
    ];

    if (!isset($options['execute'])) {
        echo json_encode(['dry_run' => true, 'would_create' => $identity], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
        exit(0);
    }

    $audit = new AuditLogger($database);
    $access = new AccessGate($database, new ScopeAuthorizer($database));
    $created = (new ClassProvisioningService($database, $access, $audit))->createClass((string) $options['actor'], $identity);
    echo json_encode($created, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

    $command = [PHP_BINARY, __DIR__ . '/provision-discipline-library.php', '--discipline=' . $options['discipline'], '--workspace=' . $created['workspace_id']];
    passthru(implode(' ', array_map('escapeshellarg', $command)), $status);
    exit($status);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
