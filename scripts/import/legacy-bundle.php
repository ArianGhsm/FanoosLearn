<?php

declare(strict_types=1);

use Fanoos\Platform\Migration\LegacyBundleValidator;
use Fanoos\Platform\Migration\LegacyImportEngine;
use Fanoos\Platform\Support\DatabaseConnection;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

$options = getopt('', ['bundle:', 'validate', 'dry-run', 'apply']);
$bundlePath = $options['bundle'] ?? null;
$modes = array_values(array_filter([
    'validate' => array_key_exists('validate', $options),
    'dry-run' => array_key_exists('dry-run', $options),
    'apply' => array_key_exists('apply', $options),
]));

if (!is_string($bundlePath) || $bundlePath === '' || count($modes) !== 1) {
    fwrite(STDERR, "Usage: php scripts/import/legacy-bundle.php --bundle=/private/path/bundle.json (--validate|--dry-run|--apply)\n");
    exit(2);
}
if (!is_file($bundlePath) || is_link($bundlePath)) {
    fwrite(STDERR, "Bundle path must be a regular non-symlink file.\n");
    exit(2);
}
$key = getenv('FANOOS_LEGACY_ID_HMAC_KEY');
if ($key === false || strlen($key) < 16) {
    fwrite(STDERR, "FANOOS_LEGACY_ID_HMAC_KEY must be supplied from the runtime secret boundary.\n");
    exit(2);
}

try {
    $decoded = json_decode((string) file_get_contents($bundlePath), true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($decoded) || array_is_list($decoded)) {
        throw new RuntimeException('Bundle root must be a JSON object.');
    }
    $validator = new LegacyBundleValidator($key);
    if (array_key_exists('validate', $options)) {
        $result = $validator->inspect($decoded);
    } else {
        $database = DatabaseConnection::fromEnvironment();
        $engine = new LegacyImportEngine($database, $key, $validator);
        if (array_key_exists('dry-run', $options)) {
            $result = $engine->dryRun($decoded);
        } else {
            if (getenv('FANOOS_MIGRATION_APPLY_CONFIRMED') !== '1') {
                throw new RuntimeException('Apply requires FANOOS_MIGRATION_APPLY_CONFIRMED=1.');
            }
            $result = $engine->apply($decoded);
        }
    }
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    if (($result['valid'] ?? true) !== true || ($result['status'] ?? 'PASS') === 'FAIL') {
        exit(1);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
