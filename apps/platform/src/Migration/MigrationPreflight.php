<?php

declare(strict_types=1);

namespace Fanoos\Platform\Migration;

use PDO;
use RuntimeException;

final class MigrationPreflight
{
    public function __construct(
        private readonly PDO $database,
        private readonly string $migrationDirectory,
    ) {
    }

    /** @return array{pending:list<string>,compatible:bool,bootstrap:bool} */
    public function inspect(bool $allowBootstrap = false): array
    {
        $ledgerExists = $this->ledgerExists();
        $applied = $ledgerExists ? $this->appliedNames() : [];
        $bootstrap = !$ledgerExists || $applied === [];
        $pending = [];

        $files = glob(rtrim($this->migrationDirectory, '/\\') . '/*.sql') ?: [];
        sort($files, SORT_STRING);
        foreach ($files as $path) {
            $name = basename($path);
            if (isset($applied[$name])) {
                continue;
            }
            $sql = file_get_contents($path);
            if ($sql === false) {
                throw new RuntimeException("Cannot read migration {$name}.");
            }
            $pending[] = $name;

            if ($bootstrap && $allowBootstrap) {
                continue;
            }
            if ($bootstrap) {
                throw new RuntimeException('Automatic update control may not bootstrap a fresh database. Use the supervised bootstrap runbook.');
            }
            if (!preg_match('/^\s*--\s*fanoos:rollback-compatible=expand\s*$/mi', $sql)) {
                throw new RuntimeException("Pending migration {$name} is not marked expand-compatible for unattended update.");
            }
            if (preg_match('/\b(?:DROP|TRUNCATE)\b|\bRENAME\s+TABLE\b|\bALTER\s+TABLE\b[\s\S]*?\b(?:DROP|MODIFY|CHANGE|RENAME)\b/i', $sql)) {
                throw new RuntimeException("Pending migration {$name} contains a destructive or contract-changing operation.");
            }
        }

        return ['pending' => $pending, 'compatible' => true, 'bootstrap' => $bootstrap];
    }

    private function ledgerExists(): bool
    {
        $query = $this->database->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schema_migrations' LIMIT 1");
        return $query->fetchColumn() !== false;
    }

    /** @return array<string, true> */
    private function appliedNames(): array
    {
        $rows = $this->database->query('SELECT migration_name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $result = [];
        foreach ($rows as $name) {
            $result[(string) $name] = true;
        }
        return $result;
    }
}
