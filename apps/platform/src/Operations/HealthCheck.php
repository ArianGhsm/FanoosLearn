<?php

declare(strict_types=1);

namespace Fanoos\Platform\Operations;

use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;

final class HealthCheck
{
    /** @return array{status: string, checks: array<string, bool>, release: string} */
    public static function run(bool $readiness): array
    {
        $config = RuntimeConfig::load();
        $checks = [
            'runtime' => PHP_VERSION_ID >= 80200
                && extension_loaded('fileinfo')
                && extension_loaded('json'),
        ];

        if ($readiness) {
            $checks['database'] = self::databaseIsReady();
            $storageRoot = $config->optionalString('FANOOS_STORAGE_ROOT');
            $checks['storage'] = is_string($storageRoot)
                && is_dir($storageRoot)
                && is_readable($storageRoot)
                && is_writable($storageRoot);
        }

        // The release's own READY marker, written by the updater when it
        // activated this code, is the truth; a SHA in a config file is only
        // what someone last remembered to type there.
        $ready = dirname(__DIR__, 4) . '/READY';
        $marker = is_file($ready) ? trim((string) file_get_contents($ready)) : '';
        $release = $marker !== '' ? $marker : ($config->optionalString('FANOOS_RELEASE_SHA', 'unknown') ?? 'unknown');
        if (!preg_match('/^(?:[a-f0-9]{7,40}|local|unknown)$/', $release)) {
            $release = 'unknown';
        }

        return [
            'status' => !in_array(false, $checks, true) ? 'ok' : 'unavailable',
            'checks' => $checks,
            'release' => $release,
        ];
    }

    private static function databaseIsReady(): bool
    {
        try {
            return (int) DatabaseConnection::fromEnvironment()->query('SELECT 1')->fetchColumn() === 1;
        } catch (\Throwable) {
            return false;
        }
    }
}
