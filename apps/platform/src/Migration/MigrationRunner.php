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

    /**
     * @param string|null $onlySupervised When given, the basename of the one
     *     migration a supervised operator run is authorized to apply -- every
     *     other pending file is skipped entirely, and the named file must be
     *     declared contract-mode (MigrationSafety::isDeclaredContract) or the
     *     run refuses it. When null (the normal, unattended path), every
     *     pending file is considered and any migration MigrationSafety::
     *     unsafeReason() flags as destructive is refused before it is ever
     *     executed -- this is the runner's own safety net, independent of
     *     MigrationPreflight, so invoking this class directly can no longer
     *     silently apply a destructive migration.
     * @return array{applied: list<string>, skipped: list<string>}
     */
    public function run(?string $onlySupervised = null): array
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
                if ($onlySupervised !== null && $name !== $onlySupervised) {
                    continue;
                }
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

                if ($onlySupervised === null) {
                    $unsafe = MigrationSafety::unsafeReason($sql);
                    if ($unsafe !== null) {
                        throw new RuntimeException("Pending migration {$name} {$unsafe}. The unattended updater refuses to apply it; use the supervised operator path (scripts/ops/apply-contract-migration.php) if it is genuinely a declared contract-mode change.");
                    }
                } elseif (!MigrationSafety::isDeclaredContract($sql)) {
                    throw new RuntimeException("Migration {$name} is not declared contract-mode; the supervised path refuses to apply it.");
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
