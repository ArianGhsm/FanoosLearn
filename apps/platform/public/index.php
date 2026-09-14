<?php

declare(strict_types=1);

use Fanoos\Platform\Core\PlatformFactory;
use Fanoos\Platform\Web\WebRouter;

require dirname(__DIR__) . '/bootstrap.php';

/*
 * The website's front controller. nginx serves real files directly and sends
 * everything else here, so this is the only entry point for a page.
 *
 * Pages are rendered by the server. The session is an HttpOnly cookie, so
 * this process knows who is asking before a single byte is sent -- which is
 * why a signed-in visitor never sees a signed-out frame flash first.
 */

$response = PlatformFactory::web()->handle(
    (string) ($_SERVER['REQUEST_URI'] ?? '/'),
    $_COOKIE,
    $_GET,
);

http_response_code($response['status']);
foreach ($response['headers'] as $header) {
    header($header, true);
}
// Pages are personal and permission-shaped; a shared cache must never hand
// one visitor's rendered page to another.
header('Cache-Control: private, no-store', true);
header('X-Content-Type-Options: nosniff', true);
header('Referrer-Policy: same-origin', true);

echo $response['body'];
