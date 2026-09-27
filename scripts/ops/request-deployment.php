<?php

declare(strict_types=1);

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Operations\DeploymentControlService;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;

/**
 * Queues a deployment of canonical main for the updater to claim -- the
 * operator's equivalent of the owner's deploy button in the bot.
 *
 * It only asks. Everything that decides whether a deploy happens stays with
 * the updater (fanoos-updater.timer):
 * - main must be a fast-forward from the live release;
 * - every required CI job must be green for that exact SHA;
 * - the backup must verify;
 * - migrations must pass preflight;
 * - health must pass, or the updater rolls back.
 *
 * The request is made as an account holding platform deployment.manage,
 * and is audited as that account.
 *
 * Usage:
 *   php scripts/ops/request-deployment.php --actor=<uuid> [--key=<idempotency key>]
 */

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

try {
    $actor = '';
    $key = 'operator-' . gmdate('YmdHis');
    foreach (array_slice($argv, 1) as $argument) {
        if (str_starts_with($argument, '--actor=')) {
            $actor = substr($argument, 8);
        } elseif (str_starts_with($argument, '--key=')) {
            $key = substr($argument, 6);
        } else {
            throw new RuntimeException("Unknown argument: {$argument}");
        }
    }
    if (preg_match('/^[0-9a-f-]{36}$/', $actor) !== 1) {
        throw new RuntimeException('Usage: --actor=<uuid of an account with platform deployment.manage> [--key=<idempotency key>]');
    }

    $database = DatabaseConnection::fromEnvironment();
    $target = RuntimeConfig::load()->requireString('FANOOS_UPDATER_TARGET_KEY');
    $service = new DeploymentControlService($database, new AccessGate($database, new ScopeAuthorizer($database)), new AuditLogger($database));

    echo json_encode($service->request($actor, 'api', $target, $key), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Deployment request failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
