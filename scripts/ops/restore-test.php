<?php

declare(strict_types=1);

use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Operations\FileTreeSnapshot;
use Fanoos\Platform\Operations\MySqlDsn;
use Fanoos\Platform\Operations\ProcessRunner;
use Fanoos\Platform\Support\RuntimeConfig;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

$backup = $argv[1] ?? '';
$targetStorage = $argv[2] ?? '';
try {
    if (getenv('FANOOS_ALLOW_TEST_RESTORE') !== '1' || getenv('FANOOS_RESTORE_TEST_EMPTY_DB_CONFIRMED') !== '1') {
        throw new RuntimeException('Test restore requires both restore safety confirmations.');
    }
    if ($backup === '' || $targetStorage === '' || file_exists($targetStorage)) {
        throw new RuntimeException('Usage requires a ready backup and a nonexistent target storage directory.');
    }
    $manifest = BackupManifest::verify($backup);
    if (!isset($manifest['files']['database.sql'])) {
        throw new RuntimeException('Backup has no database dump.');
    }

    $config = RuntimeConfig::load();
    $database = MySqlDsn::parse($config->requireString('FANOOS_RESTORE_TEST_DSN'));
    if (!str_ends_with(strtolower($database['database']), '_restore_test')) {
        throw new RuntimeException('Restore target database name must end in _restore_test.');
    }
    $defaultsFile = $config->requireString('FANOOS_MYSQL_DEFAULTS_FILE');
    if (!is_file($defaultsFile) || !is_readable($defaultsFile)) {
        throw new RuntimeException('MySQL defaults file is unavailable.');
    }

    ProcessRunner::run([
        $config->optionalString('FANOOS_MYSQL_BIN', 'mysql') ?? 'mysql',
        '--defaults-extra-file=' . $defaultsFile,
        '--host=' . $database['host'],
        '--port=' . $database['port'],
        $database['database'],
    ], null, rtrim($backup, '/\\') . DIRECTORY_SEPARATOR . 'database.sql');
    FileTreeSnapshot::copy(rtrim($backup, '/\\') . DIRECTORY_SEPARATOR . 'objects', $targetStorage);
    echo "Restore rehearsal completed; application-level smoke tests are still required.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Restore rehearsal failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
