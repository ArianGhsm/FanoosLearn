<?php

declare(strict_types=1);

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Content\ContentImportService;
use Fanoos\Platform\Content\ContentService;
use Fanoos\Platform\Content\ProtectedResourceAuthorizer;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Migration\LegacyIdMap;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

[$script, $sourceKey, $importKey, $actorUserId, $workspaceId, $manifestPath] = array_pad($argv, 6, '');
if ($sourceKey === '' || $importKey === '' || $actorUserId === '' || $workspaceId === '' || !is_file($manifestPath) || is_link($manifestPath)) {
    fwrite(STDERR, "Usage: php scripts/import/content-manifest.php <source-key> <import-key> <actor-user-uuid> <workspace-uuid> <manifest.json>\n");
    exit(2);
}

try {
    $raw = file_get_contents($manifestPath);
    $manifest = json_decode(is_string($raw) ? $raw : '', true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($manifest)) {
        throw new RuntimeException('Manifest root must be an object.');
    }
    $config = RuntimeConfig::load();
    $hmacKey = $config->requireString('FANOOS_LEGACY_ID_HMAC_KEY');
    $database = DatabaseConnection::fromEnvironment();
    $audit = new AuditLogger($database);
    $authorizer = new ScopeAuthorizer($database);
    $access = new AccessGate($database, $authorizer);
    $entitlements = new EntitlementService($database, $access, $audit);
    $resourceAuthorizer = new ProtectedResourceAuthorizer($database, $authorizer, $entitlements);
    $content = new ContentService($database, $access, $resourceAuthorizer, $audit);
    $service = new ContentImportService(
        $database,
        $access,
        $content,
        new LegacyIdMap($database, $hmacKey),
        $audit,
        $hmacKey,
    );
    echo json_encode(
        $service->import($actorUserId, $workspaceId, $sourceKey, $importKey, $manifest),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
    ) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'CONTENT IMPORT FAILED: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
