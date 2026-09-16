<?php

declare(strict_types=1);

/**
 * Applies exactly one declared-contract (destructive) migration, after a
 * fresh, verified, restore-rehearsed backup -- the supervised operator path
 * MigrationPreflight and MigrationRunner both point at when they refuse a
 * contract-mode migration for the unattended updater.
 *
 * This script is never invoked by the deploy pipeline. It is operator-run
 * only, by design: a contract migration is destructive on purpose (a
 * rollback to the release before it needs the dropped/changed shape back),
 * so applying one is a decision a human makes deliberately, with a backup
 * in hand that has actually been proven restorable -- not something a
 * release does on its own.
 *
 * Usage: php scripts/ops/apply-contract-migration.php <NNNN_name.sql>
 */

use Fanoos\Platform\Migration\MigrationRunner;
use Fanoos\Platform\Migration\MigrationSafety;
use Fanoos\Platform\Operations\ProcessRunner;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\JsonLogger;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

try {
    // 1. Validate the argument and resolve the file.
    $name = $argv[1] ?? '';
    if (!preg_match('/^\d{4}_[A-Za-z0-9_]+\.sql$/', $name)) {
        throw new RuntimeException('Usage: php scripts/ops/apply-contract-migration.php <NNNN_name.sql>');
    }
    $migrationDirectory = $root . '/database/migrations';
    $path = $migrationDirectory . '/' . $name;
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException("Migration {$name} does not exist under database/migrations.");
    }
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException("Cannot read migration {$name}.");
    }

    // 2. This script only ever applies a migration that is genuinely both
    // declared contract-mode and actually destructive. Either missing is a
    // sign the migration does not belong on this path.
    if (!MigrationSafety::isDeclaredContract($sql)) {
        throw new RuntimeException("Migration {$name} is not declared contract-mode. This script only applies migrations declared \"-- fanoos:rollback-compatible=contract\"; use the normal deploy path (scripts/db/migrate.php via the unattended updater) for anything else.");
    }
    if (MigrationSafety::unsafeReason($sql) === null) {
        throw new RuntimeException("Migration {$name} is declared contract-mode but is not actually destructive. That is a sign the header is wrong, not a reason to apply it here -- fix the declaration and let the unattended updater handle it.");
    }

    $database = DatabaseConnection::fromEnvironment();

    // 3. Refuse to run twice, before touching the backup system at all.
    $ledgerExists = (bool) $database->query(
        "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schema_migrations' LIMIT 1",
    )->fetchColumn();
    if ($ledgerExists) {
        $appliedAt = $database->prepare('SELECT applied_at FROM schema_migrations WHERE migration_name = :name');
        $appliedAt->execute(['name' => $name]);
        $existing = $appliedAt->fetchColumn();
        if ($existing !== false) {
            throw new RuntimeException("Migration {$name} is already applied at {$existing}; the supervised path will not run a contract migration twice.");
        }
    }

    // 4. Restore rehearsal is a *cheap* gate compared to actually taking a
    // backup -- confirm it is authorized before spending a backup cycle on
    // a run that could not complete anyway. A checksum-verified backup is
    // not yet a *restorable* backup -- only an actual rehearsed restore
    // proves that -- so this requires the operator to have consciously set
    // both confirmation variables; this script never sets them itself.
    // (Deliberately checked before the backup step, ahead of the brief's
    // literal step order, for the same reason step 3 runs before either:
    // do not spend an expensive resource on a run a cheap check would have
    // refused anyway.)
    if (getenv('FANOOS_ALLOW_TEST_RESTORE') !== '1' || getenv('FANOOS_RESTORE_TEST_EMPTY_DB_CONFIRMED') !== '1') {
        throw new RuntimeException(
            'Restore rehearsal was not authorized. A backup that is only checksum-verified but never actually '
            . 'restored is not "verified as restorable", only "verified as internally consistent". Set both '
            . 'FANOOS_ALLOW_TEST_RESTORE=1 and FANOOS_RESTORE_TEST_EMPTY_DB_CONFIRMED=1 (after confirming '
            . 'FANOOS_RESTORE_TEST_DSN points at a disposable *_restore_test database) and re-run.',
        );
    }

    // 5. A fresh, verified backup. Reused verbatim -- this script does not
    // reimplement backup or verification logic.
    $backupPath = trim(ProcessRunner::run(['php', $root . '/scripts/ops/backup.php']));
    if ($backupPath === '' || !is_dir($backupPath)) {
        throw new RuntimeException('Backup command did not produce a finalized directory.');
    }
    ProcessRunner::run(['php', $root . '/scripts/ops/verify-backup.php', $backupPath]);
    $backupId = basename($backupPath);

    // Now actually rehearse the restore, using the backup just taken.
    $restoreTarget = rtrim(sys_get_temp_dir(), '/\\') . '/fanoos-restore-test-' . bin2hex(random_bytes(8));
    if (file_exists($restoreTarget)) {
        throw new RuntimeException('Generated restore-rehearsal target directory unexpectedly already exists.');
    }
    try {
        ProcessRunner::run(['php', $root . '/scripts/ops/restore-test.php', $backupPath, $restoreTarget]);
    } finally {
        // Best-effort: this is only a rehearsal artifact. The _restore_test
        // database itself is left alone -- that is the operator's to
        // inspect or drop, not this script's.
        if (is_dir($restoreTarget)) {
            removeDirectoryRecursively($restoreTarget);
        }
    }

    // 6. Only now, apply the migration -- through the same runner, statement
    // splitter and additive-alter guard every other migration goes through,
    // told that this one specific, already-verified-contract file is the
    // one it is authorized to touch.
    $result = (new MigrationRunner($database, $migrationDirectory))->run($name);
    $applied = in_array($name, $result['applied'], true);
    $skipped = in_array($name, $result['skipped'], true);
    if (!$applied && !$skipped) {
        throw new RuntimeException("Migration runner did not report {$name} as applied or skipped; refusing to assume success.");
    }

    // 7. Record what happened.
    $event = $applied ? 'contract_migration.applied' : 'contract_migration.skipped';
    JsonLogger::write('info', $event, ['migration' => $name, 'backup_id' => $backupId]);

    $timestamp = gmdate('Y-m-d\TH:i:s\Z');
    if ($applied) {
        echo "Applied {$name} using backup {$backupId} at {$timestamp}.\n";
    } else {
        echo "{$name} was already applied by another process between the ledger check and the run (backup {$backupId} taken at {$timestamp} regardless); no changes were made by this invocation.\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Supervised contract migration failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}

function removeDirectoryRecursively(string $directory): void
{
    $entries = @scandir($directory);
    if ($entries === false) {
        return;
    }
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $directory . DIRECTORY_SEPARATOR . $entry;
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            continue;
        }
        if (is_dir($path)) {
            removeDirectoryRecursively($path);
        }
    }
    @rmdir($directory);
}
