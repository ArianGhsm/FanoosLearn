<?php

declare(strict_types=1);

use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Tests\Core\AnnouncementPublishTest;
use Fanoos\Tests\Core\ClassCreationRequestServiceTest;
use Fanoos\Tests\Core\ClassMembershipTest;
use Fanoos\Tests\Core\ClassProvisioningTest;
use Fanoos\Tests\Core\InstitutionTermServiceTest;
use Fanoos\Tests\Core\OnboardingDirectoryTest;
use Fanoos\Tests\Core\OnboardingPhoneVerificationTest;
use Fanoos\Tests\Core\RepresentativeApprovalTest;
use Fanoos\Tests\Integration\TenantIsolationTest;
use Fanoos\Tests\Integration\MigrationSafetyTest;
use Fanoos\Tests\Integration\CorePlatformTest;
use Fanoos\Tests\Integration\ContentEngineTest;
use Fanoos\Tests\Integration\Stage7PlatformTest;
use Fanoos\Tests\Integration\ServiceAuthLinkTest;
use Fanoos\Tests\Integration\DeploymentControlTest;
use Fanoos\Tests\Integration\BotPlatformHandoffTest;
use Fanoos\Tests\Integration\ProtectedMediaForensicTest;
use Fanoos\Tests\Integration\Stage8FinalClosureTest;
use Fanoos\Tests\Operations\BackupContractTest;
use Fanoos\Tests\Operations\GitHubCiVerifierContractTest;
use Fanoos\Tests\Operations\OwnerBootstrapTest;
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
    $assertions += (new GitHubCiVerifierContractTest())->run();
    echo "PASS GitHub Actions CI verifier contracts\n";

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

        $database = DatabaseConnection::fromEnvironment();
        $assertions += (new TenantIsolationTest($database, $root, $hmacKey))->run();
        echo "PASS database tenant isolation and rerun scenarios\n";
        $assertions += (new MigrationSafetyTest($database))->run();
        echo "PASS interrupted migration recovery scenarios\n";
        $assertions += (new CorePlatformTest($database))->run();
        echo "PASS core platform adaptation scenarios\n";
        $assertions += (new ContentEngineTest($database, $hmacKey))->run();
        echo "PASS content engine and secure learning scenarios\n";
        $assertions += (new Stage7PlatformTest($database))->run();
        echo "PASS Stage 7 commerce notification delivery and media scenarios\n";
        $assertions += (new ServiceAuthLinkTest($database))->run();
        echo "PASS Stage 7 service authentication and messaging link scenarios\n";
        $assertions += (new DeploymentControlTest($database))->run();
        echo "PASS Stage 7 deployment control-plane scenarios\n";
        $assertions += (new ClassProvisioningTest($database))->run();
        echo "PASS owner class provisioning scenarios\n";
        $assertions += (new OnboardingDirectoryTest($database, $root))->run();
        echo "PASS onboarding directory catalog and read scenarios\n";
        $assertions += (new OnboardingPhoneVerificationTest($database))->run();
        echo "PASS onboarding phone verification scenarios\n";
        $assertions += (new ClassMembershipTest($database))->run();
        echo "PASS join wizard membership and upgrade-request scenarios\n";
        $assertions += (new RepresentativeApprovalTest($database))->run();
        echo "PASS representative appointment and approval scenarios\n";
        $assertions += (new AnnouncementPublishTest($database))->run();
        echo "PASS representative announcement publish/read scenarios\n";
        $assertions += (new ClassCreationRequestServiceTest($database))->run();
        echo "PASS owner class-creation request review scenarios\n";
        $assertions += (new InstitutionTermServiceTest($database))->run();
        echo "PASS institution term-date scenarios\n";
        $assertions += (new OwnerBootstrapTest($database, $root))->run();
        echo "PASS first-owner bootstrap tool scenarios\n";
        $assertions += (new BotPlatformHandoffTest($database))->run();
        echo "PASS Stage 7 bot/platform handoff scenarios\n";
        $forensicStorageRoot = sys_get_temp_dir() . '/fanoos-forensic-test-' . bin2hex(random_bytes(8));
        $assertions += (new ProtectedMediaForensicTest($database, $forensicStorageRoot))->run();
        echo "PASS protected-media forensic candidate lookup scenarios\n";
        $assertions += (new Stage8FinalClosureTest($database, $hmacKey))->run();
        echo "PASS Stage 8 migration/reconciliation final-closure scenarios\n";
    }

    echo "PASS {$assertions} assertions\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
