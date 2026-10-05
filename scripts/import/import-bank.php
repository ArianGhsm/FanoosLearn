<?php

declare(strict_types=1);

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Bank\BankImporter;
use Fanoos\Platform\Bank\BankImportException;
use Fanoos\Platform\Bank\BankPublisher;
use Fanoos\Platform\Content\ExamImageStore;
use Fanoos\Platform\Content\ExamQuestionRateGuard;
use Fanoos\Platform\Content\ExamService;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;

/**
 * The dental bank's import and publish tool (docs/product/06_QUESTION_FORMAT.md).
 *
 * Check a file without touching anything:
 *   php scripts/import/import-bank.php check --workspace=<uuid> --file=<file.json> [--assets=<folder>]
 *
 * Import it (a catalog file, or one exam sitting); --dry-run writes and rolls
 * back, so the counts are real:
 *   php scripts/import/import-bank.php import --workspace=<uuid> --file=<file.json> [--assets=<folder>] [--dry-run]
 *
 * Remove the nodes, editions and references that a catalog no longer lists
 * (never one a question source or edition mapping points at); --dry-run rolls back:
 *   php scripts/import/import-bank.php prune-nodes --workspace=<uuid> --file=<catalog.json> [--dry-run]
 *
 * Publish an imported sitting as an exam on the site (actor and reviewer
 * must be different accounts):
 *   php scripts/import/import-bank.php publish --workspace=<uuid> --type=residency --year=1404 [--round=1]
 *       --actor=<uuid> --reviewer=<uuid> [--time-limit=<minutes>] [--max-attempts=<n>]
 *
 * Images named in a sitting file are read from --assets and stored in
 * FANOOS_STORAGE_ROOT; run imports with images as the web user.
 */

ini_set('memory_limit', '512M');
$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

$command = $argv[1] ?? '';
$options = [];
foreach (array_slice($argv, 2) as $argument) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $argument, $m) !== 1) {
        fwrite(STDERR, "Unknown argument: {$argument}\n");
        exit(2);
    }
    $options[$m[1]] = $m[2] ?? '1';
}

try {
    $workspace = (string) ($options['workspace'] ?? '');
    if (preg_match('/^[0-9a-f-]{36}$/', $workspace) !== 1) {
        throw new RuntimeException('--workspace=<uuid> is required');
    }
    $config = RuntimeConfig::load();
    $database = DatabaseConnection::fromEnvironment();
    $storage = $config->optionalString('FANOOS_STORAGE_ROOT');
    $images = is_string($storage) && trim($storage) !== '' ? new ExamImageStore($storage) : null;

    if ($command === 'check' || $command === 'import') {
        $path = (string) ($options['file'] ?? '');
        $file = json_decode((string) @file_get_contents($path), true, 64);
        if (!is_array($file)) {
            throw new RuntimeException("{$path} is not a readable JSON file");
        }
        $assets = isset($options['assets']) ? (string) $options['assets'] : null;
        $importer = new BankImporter($database, $images);
        if ($command === 'check') {
            $problems = $importer->validate($workspace, $file, $assets);
            echo $problems === [] ? "OK: the file can be imported.\n" : implode("\n", $problems) . "\n" . count($problems) . " problem(s).\n";
            exit($problems === [] ? 0 : 1);
        }
        $dryRun = isset($options['dry-run']);
        $counts = $importer->import($workspace, $file, $assets, $dryRun);
        echo json_encode(['format' => $file['format'], 'dry_run' => $dryRun, 'written' => $counts], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
        exit(0);
    }

    if ($command === 'prune-nodes') {
        $path = (string) ($options['file'] ?? '');
        $file = json_decode((string) @file_get_contents($path), true, 64);
        if (!is_array($file)) {
            throw new RuntimeException("{$path} is not a readable JSON file");
        }
        $dryRun = isset($options['dry-run']);
        $result = (new BankImporter($database, $images))->pruneNodes($workspace, $file, $dryRun);
        echo json_encode(['dry_run' => $dryRun] + $result, JSON_PRETTY_PRINT) . PHP_EOL;
        exit(0);
    }

    if ($command === 'publish') {
        $audit = new AuditLogger($database);
        $authorizer = new ScopeAuthorizer($database);
        $access = new AccessGate($database, $authorizer);
        $exams = new ExamService($database, $access, $authorizer, new EntitlementService($database, $access, $audit), $audit, new ExamQuestionRateGuard($database));
        $result = (new BankPublisher($database, $exams))->publish(
            $workspace,
            (string) ($options['type'] ?? 'residency'),
            (int) ($options['year'] ?? 0),
            (int) ($options['round'] ?? 1),
            (string) ($options['actor'] ?? ''),
            (string) ($options['reviewer'] ?? ''),
            array_filter([
                'time_limit_minutes' => isset($options['time-limit']) ? (int) $options['time-limit'] : null,
                'max_attempts' => isset($options['max-attempts']) ? (int) $options['max-attempts'] : null,
            ], static fn ($value): bool => $value !== null),
        );
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
        exit(0);
    }

    throw new RuntimeException('Usage: import-bank.php check|import|publish --workspace=<uuid> ... (see the header of this file)');
} catch (BankImportException $error) {
    fwrite(STDERR, implode("\n", $error->problems) . "\n" . count($error->problems) . " problem(s); nothing was written.\n");
    exit(1);
} catch (Throwable $error) {
    fwrite(STDERR, 'Failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
