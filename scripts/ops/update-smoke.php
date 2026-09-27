<?php

declare(strict_types=1);

use Fanoos\Platform\Support\RuntimeConfig;

/**
 * Post-activation smoke check: the site as a visitor reaches it.
 *
 * health.php runs as the updater, on the command line, and so cannot see
 * what nginx, php-fpm and the bots see. The first unattended deploy passed
 * it while the new release was unreadable to all three -- every page was a
 * 404 and both bots were in a restart loop. This asks the public origin for
 * pages that must answer, so the same fault fails the deploy and the updater
 * rolls back instead.
 *
 * Needs FANOOS_PUBLIC_ORIGIN. Without it there is nothing to ask, and the
 * check says so rather than passing.
 */

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

try {
    $origin = rtrim((string) RuntimeConfig::load()->optionalString('FANOOS_PUBLIC_ORIGIN', ''), '/');
    if (!str_starts_with($origin, 'https://')) {
        throw new RuntimeException('FANOOS_PUBLIC_ORIGIN is required for the smoke check.');
    }

    $checks = [
        '/' => static fn (int $status, string $body): bool => $status === 200 && str_contains($body, '<html'),
        '/login' => static fn (int $status, string $body): bool => $status === 200 && str_contains($body, 'login-form'),
        '/api/v1/signup/options' => static fn (int $status, string $body): bool => $status === 200 && str_contains($body, '"ok":true'),
    ];
    foreach ($checks as $path => $passes) {
        // The page settles a moment after php-fpm reloads; allow a few tries.
        $ok = false;
        $status = 0;
        for ($attempt = 1; $attempt <= 5 && !$ok; $attempt++) {
            $handle = curl_init($origin . $path);
            curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => false]);
            $body = (string) curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            curl_close($handle);
            $ok = $passes($status, $body);
            if (!$ok) {
                sleep(2);
            }
        }
        if (!$ok) {
            throw new RuntimeException("{$path} answered {$status}.");
        }
    }
    echo 'smoke ok' . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Smoke check failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
