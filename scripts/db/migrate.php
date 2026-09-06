<?php

declare(strict_types=1);

use Fanoos\Platform\Migration\MigrationRunner;
use Fanoos\Platform\Support\DatabaseConnection;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

try {
    $result = (new MigrationRunner(
        DatabaseConnection::fromEnvironment(),
        $root . '/database/migrations',
    ))->run();

    printf(
        "Migrations complete: %d applied, %d already current.\n",
        count($result['applied']),
        count($result['skipped']),
    );
    foreach ($result['applied'] as $name) {
        echo "  applied {$name}\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Migration failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
