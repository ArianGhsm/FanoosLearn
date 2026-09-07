<?php

declare(strict_types=1);

namespace Fanoos\Platform\Migration;

use PDO;
use RuntimeException;
use Throwable;

final class MigrationRunner
{
    public function __construct(
        private readonly PDO $database,
        private readonly string $migrationDirectory,
    ) {
    }

    /** @return array{applied: list<string>, skipped: list<string>} */
    public function run(): array
    {
        $this->ensureLedger();
        $lock = $this->database->query("SELECT GET_LOCK('fanoos_schema_migrations', 30)")->fetchColumn();
        if ((string) $lock !== '1') {
            throw new RuntimeException('Could not acquire the schema migration lock.');
        }

        $result = ['applied' => [], 'skipped' => []];
        $alterGuard = new AdditiveAlterGuard($this->database);

        try {
            $files = glob(rtrim($this->migrationDirectory, '/\\') . '/*.sql') ?: [];
            sort($files, SORT_STRING);

            foreach ($files as $path) {
                $name = basename($path);
                $sql = file_get_contents($path);
                if ($sql === false) {
                    throw new RuntimeException("Cannot read migration {$name}.");
                }

                $checksum = hash('sha256', $sql);
                $existing = $this->findChecksum($name);
                if ($existing !== null) {
                    if (!hash_equals($existing, $checksum)) {
                        throw new RuntimeException("Applied migration checksum changed: {$name}.");
                    }
                    $result['skipped'][] = $name;
                    continue;
                }

                foreach (SqlStatementSplitter::split($sql) as $statement) {
                    if ($alterGuard->decision($statement) === 'skip') {
                        continue;
                    }
                    $this->database->exec($statement);
                }

                $insert = $this->database->prepare(
                    'INSERT INTO schema_migrations (migration_name, checksum_sha256, applied_at) VALUES (:name, :checksum, UTC_TIMESTAMP(6))',
                );
                $insert->execute(['name' => $name, 'checksum' => $checksum]);
                $result['applied'][] = $name;
            }

            return $result;
        } finally {
            try {
                $this->database->query("SELECT RELEASE_LOCK('fanoos_schema_migrations')");
            } catch (Throwable) {
                // The connection release also releases the advisory lock.
            }
        }
    }

    private function ensureLedger(): void
    {
        $this->database->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS schema_migrations (
    migration_name VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    checksum_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    applied_at DATETIME(6) NOT NULL,
    PRIMARY KEY (migration_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    private function findChecksum(string $name): ?string
    {
        $query = $this->database->prepare(
            'SELECT checksum_sha256 FROM schema_migrations WHERE migration_name = :name',
        );
        $query->execute(['name' => $name]);
        $checksum = $query->fetchColumn();

        return $checksum === false ? null : (string) $checksum;
    }
}
