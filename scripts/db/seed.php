<?php

declare(strict_types=1);

use Fanoos\Platform\Migration\SeedRunner;
use Fanoos\Platform\Support\DatabaseConnection;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

try {
    $executed = (new SeedRunner(
        DatabaseConnection::fromEnvironment(),
        $root . '/database/seeds',
    ))->run();

    printf("Seeds complete: %d file(s) executed idempotently.\n", count($executed));
    foreach ($executed as $name) {
        echo "  executed {$name}\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Seed failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
