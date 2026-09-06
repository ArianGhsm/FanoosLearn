<?php

declare(strict_types=1);

use Fanoos\Platform\Operations\BackupManifest;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

$backup = $argv[1] ?? '';
try {
    if ($backup === '' || !is_file(rtrim($backup, '/\\') . DIRECTORY_SEPARATOR . 'READY')) {
        throw new RuntimeException('Usage: php scripts/ops/verify-backup.php <ready-backup-directory>');
    }
    $manifest = BackupManifest::verify($backup);
    echo 'Backup verified: ' . count($manifest['files']) . " files.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Backup verification failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
