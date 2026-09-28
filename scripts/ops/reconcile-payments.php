<?php

declare(strict_types=1);

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Commerce\CommerceService;
use Fanoos\Platform\Commerce\PaymentGatewayFactory;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\JsonLogger;
use Fanoos\Platform\Support\RuntimeConfig;

/**
 * Settles payments nobody came back to settle (CommerceService::
 * reconcileStale). Run every few minutes by fanoos-reconcile-payments.timer.
 * With payments switched off it does nothing and says so.
 */

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

try {
    $config = RuntimeConfig::load();
    $payments = PaymentGatewayFactory::fromConfig($config);
    if (!$payments['enabled']) {
        echo json_encode(['state' => 'payments_off']) . PHP_EOL;
        exit(0);
    }
    $database = DatabaseConnection::fromEnvironment();
    $audit = new AuditLogger($database);
    $access = new AccessGate($database, new ScopeAuthorizer($database));
    $commerce = new CommerceService(
        $database,
        $access,
        new EntitlementService($database, $access, $audit),
        $audit,
        $payments['gateway'],
        $config->requireString('FANOOS_PAYMENT_CALLBACK_KEY'),
    );
    $summary = $commerce->reconcileStale();
    if ($summary['checked'] > 0) {
        JsonLogger::write('info', 'payment.auto_reconcile', $summary);
    }
    echo json_encode($summary) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Payment reconciliation failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
