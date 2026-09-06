<?php

declare(strict_types=1);

use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Tests\Integration\TenantIsolationTest;
use Fanoos\Tests\Integration\CorePlatformTest;
use Fanoos\Tests\Integration\ContentEngineTest;
use Fanoos\Tests\Operations\BackupContractTest;
use Fanoos\Tests\Schema\SchemaContractTest;
use Fanoos\Tests\Storage\StorageSecurityTest;

$root = dirname(__DIR__);
require $root . '/apps/platform/bootstrap.php';

spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'Fanoos\\Tests\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = $root . '/tests/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

try {
    $assertions = (new SchemaContractTest($root))->run();
    echo "PASS schema contracts\n";
    $assertions += (new StorageSecurityTest())->run();
    echo "PASS storage security contracts\n";
    $assertions += (new BackupContractTest())->run();
    echo "PASS backup integrity contracts\n";

    $mode = getenv('FANOOS_TEST_MODE') ?: 'all';
    if ($mode !== 'static') {
        $dsn = getenv('FANOOS_DB_DSN') ?: '';
        $allow = getenv('FANOOS_ALLOW_TEST_DB');
        if ($allow !== '1' || !preg_match('/(?:^|;)dbname=([a-z0-9_]*_test)(?:;|$)/i', $dsn)) {
            throw new RuntimeException('Integration tests require FANOOS_ALLOW_TEST_DB=1 and a DSN whose database name ends in _test.');
        }

        $hmacKey = getenv('FANOOS_LEGACY_ID_HMAC_KEY');
        if ($hmacKey === false || strlen($hmacKey) < 16) {
            throw new RuntimeException('Integration tests require a test-only FANOOS_LEGACY_ID_HMAC_KEY of at least 16 characters.');
        }

        $assertions += (new TenantIsolationTest(
            DatabaseConnection::fromEnvironment(),
            $root,
            $hmacKey,
        ))->run();
        echo "PASS database tenant isolation and rerun scenarios\n";
        $assertions += (new CorePlatformTest(DatabaseConnection::fromEnvironment()))->run();
        echo "PASS core platform adaptation scenarios\n";
        $assertions += (new ContentEngineTest(DatabaseConnection::fromEnvironment(), $hmacKey))->run();
        echo "PASS content engine and secure learning scenarios\n";
    }

    echo "PASS {$assertions} assertions\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
