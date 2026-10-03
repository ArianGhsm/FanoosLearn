<?php

declare(strict_types=1);

use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\JsonLogger;
use Fanoos\Platform\Support\RuntimeConfig;

/**
 * Removes one workspace and everything in it -- its exams, versions,
 * courses, scopes and role assignments, memberships, attempts, counters and
 * audit trail -- and then the exam image files no remaining exam refers to.
 *
 * Written for the owner's decision of 2026-10-03 to delete the medical bank
 * when FANOOS became a dental residency product (docs/PROJECT_PRINCIPLES.md).
 * It is irreversible: run it only after a backup has been taken and
 * verified (scripts/ops/backup.php, scripts/ops/verify-backup.php).
 *
 * Without --execute it only reports what it would delete. With --execute it
 * deletes inside one transaction and, before committing, checks every
 * foreign key in the schema for rows left pointing at nothing; if any are
 * found it rolls everything back.
 *
 * Usage:
 *   php scripts/ops/purge-workspace.php --workspace=<uuid> --confirm=<same uuid> [--execute]
 */

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

try {
    $workspace = $confirm = '';
    $execute = false;
    foreach (array_slice($argv, 1) as $argument) {
        if (str_starts_with($argument, '--workspace=')) {
            $workspace = substr($argument, 12);
        } elseif (str_starts_with($argument, '--confirm=')) {
            $confirm = substr($argument, 10);
        } elseif ($argument === '--execute') {
            $execute = true;
        } else {
            throw new RuntimeException("Unknown argument: {$argument}");
        }
    }
    if (preg_match('/^[0-9a-f-]{36}$/', $workspace) !== 1 || $workspace !== $confirm) {
        throw new RuntimeException('Usage: --workspace=<uuid> --confirm=<the same uuid> [--execute]');
    }

    $config = RuntimeConfig::load();
    $database = DatabaseConnection::fromEnvironment();
    $schema = (string) $database->query('SELECT DATABASE()')->fetchColumn();

    $exists = $database->prepare('SELECT name FROM tenant_workspaces WHERE id = :workspace');
    $exists->execute(['workspace' => $workspace]);
    $name = $exists->fetchColumn();
    if ($name === false) {
        throw new RuntimeException('Workspace not found.');
    }

    // Every table that carries the workspace, except the workspace itself.
    $tables = $database->prepare(<<<'SQL'
SELECT table_name FROM information_schema.columns
WHERE table_schema = :schema AND column_name = 'workspace_id' AND table_name <> 'tenant_workspaces'
ORDER BY table_name
SQL);
    $tables->execute(['schema' => $schema]);
    $tables = $tables->fetchAll(PDO::FETCH_COLUMN);

    $plan = [];
    foreach ($tables as $table) {
        $count = $database->prepare("SELECT COUNT(*) FROM `{$table}` WHERE workspace_id = :workspace");
        $count->execute(['workspace' => $workspace]);
        $n = (int) $count->fetchColumn();
        if ($n > 0) {
            $plan[$table] = $n;
        }
    }
    // Role assignments hang off the workspace's scopes, not off the workspace.
    $assignments = $database->prepare('SELECT COUNT(*) FROM rbac_role_assignments WHERE scope_id IN (SELECT id FROM rbac_scopes WHERE workspace_id = :workspace)');
    $assignments->execute(['workspace' => $workspace]);
    $plan['rbac_role_assignments (via scopes)'] = (int) $assignments->fetchColumn();

    echo json_encode(['workspace' => $workspace, 'name' => $name, 'rows' => $plan, 'execute' => $execute], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    if (!$execute) {
        echo "Dry run. Nothing was deleted.\n";
        exit(0);
    }

    $database->beginTransaction();
    try {
        $database->exec('SET FOREIGN_KEY_CHECKS = 0');
        $database->prepare('DELETE FROM rbac_role_assignments WHERE scope_id IN (SELECT id FROM rbac_scopes WHERE workspace_id = :workspace)')
            ->execute(['workspace' => $workspace]);
        foreach (array_keys($plan) as $table) {
            if (str_contains($table, ' ')) {
                continue;
            }
            $database->prepare("DELETE FROM `{$table}` WHERE workspace_id = :workspace")->execute(['workspace' => $workspace]);
        }
        // Other columns that point at the workspace under another name (a
        // session's selected workspace, a discipline's library): cleared when
        // they may be empty, otherwise the row goes with the workspace.
        $references = $database->prepare(<<<'SQL'
SELECT k.table_name, k.column_name, c.is_nullable
FROM information_schema.key_column_usage k
JOIN information_schema.columns c ON c.table_schema = k.table_schema AND c.table_name = k.table_name AND c.column_name = k.column_name
WHERE k.table_schema = :schema AND k.referenced_table_name = 'tenant_workspaces' AND k.column_name <> 'workspace_id'
SQL);
        $references->execute(['schema' => $schema]);
        foreach ($references->fetchAll(PDO::FETCH_NUM) as [$table, $column, $nullable]) {
            $sql = $nullable === 'YES'
                ? "UPDATE `{$table}` SET `{$column}` = NULL WHERE `{$column}` = :workspace"
                : "DELETE FROM `{$table}` WHERE `{$column}` = :workspace";
            $database->prepare($sql)->execute(['workspace' => $workspace]);
        }
        $database->prepare('DELETE FROM tenant_workspaces WHERE id = :workspace')->execute(['workspace' => $workspace]);
        $database->exec('SET FOREIGN_KEY_CHECKS = 1');

        // Nothing may be left pointing at a row that is gone. Keys are
        // checked whole: many are composite (workspace_id plus the id), and
        // a row with any key column NULL is not bound by the key at all.
        $keys = $database->prepare(<<<'SQL'
SELECT table_name, constraint_name, column_name, referenced_table_name, referenced_column_name
FROM information_schema.key_column_usage
WHERE table_schema = :schema AND referenced_table_name IS NOT NULL
ORDER BY table_name, constraint_name, ordinal_position
SQL);
        $keys->execute(['schema' => $schema]);
        $constraints = [];
        foreach ($keys->fetchAll(PDO::FETCH_NUM) as [$child, $constraint, $column, $parent, $parentColumn]) {
            $constraints["{$child}.{$constraint}"]['child'] = $child;
            $constraints["{$child}.{$constraint}"]['parent'] = $parent;
            $constraints["{$child}.{$constraint}"]['pairs'][] = [$column, $parentColumn];
        }
        $orphans = [];
        foreach ($constraints as $name => $key) {
            $on = implode(' AND ', array_map(static fn (array $pair): string => "p.`{$pair[1]}` = c.`{$pair[0]}`", $key['pairs']));
            $bound = implode(' AND ', array_map(static fn (array $pair): string => "c.`{$pair[0]}` IS NOT NULL", $key['pairs']));
            $first = $key['pairs'][0][1];
            $n = (int) $database->query(
                "SELECT COUNT(*) FROM `{$key['child']}` c LEFT JOIN `{$key['parent']}` p ON {$on}"
                . " WHERE {$bound} AND p.`{$first}` IS NULL",
            )->fetchColumn();
            if ($n > 0) {
                $orphans[$name] = $n;
            }
        }
        if ($orphans !== []) {
            throw new RuntimeException('Rows would be left pointing at deleted rows: ' . json_encode($orphans));
        }
        $database->commit();
    } catch (Throwable $error) {
        $database->rollBack();
        $database->exec('SET FOREIGN_KEY_CHECKS = 1');
        throw $error;
    }

    // Image files: keep only those some remaining exam version still names.
    $removed = 0;
    $storage = $config->optionalString('FANOOS_STORAGE_ROOT');
    $images = is_string($storage) && trim($storage) !== '' ? rtrim($storage, '/') . '/exam-images' : null;
    if ($images !== null && is_dir($images)) {
        $referenced = [];
        $versions = $database->query('SELECT definition_json FROM exam_assessment_versions');
        while (($definition = $versions->fetchColumn()) !== false) {
            preg_match_all('/[a-f0-9]{64}\.(?:jpg|png|webp)/', (string) $definition, $match);
            foreach ($match[0] as $key) {
                $referenced[$key] = true;
            }
        }
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($images, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->isFile() && preg_match('/^[a-f0-9]{64}\.(jpg|png|webp)$/', $file->getFilename()) === 1 && !isset($referenced[$file->getFilename()])) {
                if (unlink($file->getPathname())) {
                    ++$removed;
                }
            }
        }
    }

    $summary = ['workspace' => $workspace, 'name' => $name, 'rows' => $plan, 'image_files_removed' => $removed];
    JsonLogger::write('warning', 'ops.workspace_purged', $summary);
    echo json_encode($summary, JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Purge failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
