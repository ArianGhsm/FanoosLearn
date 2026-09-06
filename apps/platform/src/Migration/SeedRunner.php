<?php

declare(strict_types=1);

namespace Fanoos\Platform\Migration;

use PDO;
use RuntimeException;
use Throwable;

final class SeedRunner
{
    public function __construct(
        private readonly PDO $database,
        private readonly string $seedDirectory,
    ) {
    }

    /** @return list<string> */
    public function run(): array
    {
        $files = glob(rtrim($this->seedDirectory, '/\\') . '/*.sql') ?: [];
        sort($files, SORT_STRING);
        $executed = [];

        foreach ($files as $path) {
            $name = basename($path);
            $sql = file_get_contents($path);
            if ($sql === false) {
                throw new RuntimeException("Cannot read seed {$name}.");
            }

            $this->database->beginTransaction();
            try {
                foreach (SqlStatementSplitter::split($sql) as $statement) {
                    $this->database->exec($statement);
                }
                $this->database->commit();
                $executed[] = $name;
            } catch (Throwable $error) {
                if ($this->database->inTransaction()) {
                    $this->database->rollBack();
                }
                throw $error;
            }
        }

        return $executed;
    }
}
