<?php

declare(strict_types=1);

namespace Fanoos\Tests\Integration;

use Fanoos\Platform\Migration\AdditiveAlterGuard;
use Fanoos\Platform\Migration\MigrationRunner;
use PDO;
use RuntimeException;

final class MigrationSafetyTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $suffix = bin2hex(random_bytes(5));
        $completeTable = 'migration_complete_' . $suffix;
        $partialTable = 'migration_partial_' . $suffix;
        $directory = sys_get_temp_dir() . '/fanoos-migration-' . $suffix;
        mkdir($directory, 0700, true);
        try {
            $this->database->exec("CREATE TABLE `{$completeTable}` (id INT NOT NULL PRIMARY KEY) ENGINE=InnoDB");
            $statement = "ALTER TABLE `{$completeTable}` ADD COLUMN recovered_col INT NULL, ADD UNIQUE KEY uq_{$suffix}_recovered (recovered_col)";
            $this->database->exec($statement);
            file_put_contents($directory . '/9000_fault_recovery.sql', $statement . ';');
            $result = (new MigrationRunner($this->database, $directory))->run();
            self::assert($result['applied'] === ['9000_fault_recovery.sql'], 'Runner did not ledger a fully committed additive statement after interruption.');
            $rerun = (new MigrationRunner($this->database, $directory))->run();
            self::assert($rerun['skipped'] === ['9000_fault_recovery.sql'], 'Recovered migration was not a deterministic no-op on rerun.');

            $this->database->exec("CREATE TABLE `{$partialTable}` (id INT NOT NULL PRIMARY KEY, recovered_col INT NULL) ENGINE=InnoDB");
            $partial = "ALTER TABLE `{$partialTable}` ADD COLUMN recovered_col INT NULL, ADD UNIQUE KEY uq_{$suffix}_partial (recovered_col)";
            $blocked = false;
            try {
                (new AdditiveAlterGuard($this->database))->decision($partial);
            } catch (RuntimeException) {
                $blocked = true;
            }
            self::assert($blocked, 'Partially applied additive DDL was not failed closed.');
        } finally {
            $this->database->exec("DROP TABLE IF EXISTS `{$completeTable}`");
            $this->database->exec("DROP TABLE IF EXISTS `{$partialTable}`");
            @unlink($directory . '/9000_fault_recovery.sql');
            @rmdir($directory);
            $this->database->prepare("DELETE FROM schema_migrations WHERE migration_name = '9000_fault_recovery.sql'")->execute();
        }
        return $this->assertions;
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
