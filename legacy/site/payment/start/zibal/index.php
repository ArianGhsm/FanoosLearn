<?php
declare(strict_types=1);

define('DENT_BOOTSTRAP_WITHOUT_SESSION', true);
require_once dirname(__DIR__, 3) . '/api/payment_handoff.php';
require_once dirname(__DIR__, 3) . '/api/payments_store.php';

$nonce = base64_encode(random_bytes(18));
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Referrer-Policy: origin');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; script-src 'nonce-{$nonce}'; style-src 'nonce-{$nonce}'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'");

$result = dent_zibal_handoff_validate((string) ($_SERVER['QUERY_STRING'] ?? ''));
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    $result = ['valid' => false, 'reason' => 'method'];
    header('Allow: GET');
    http_response_code(405);
} elseif (!$result['valid']) {
    http_response_code($result['reason'] === 'expired' ? 410 : 400);
}
payments_log_gateway_event($result['valid'] ? 'payment_handoff_rendered' : 'payment_handoff_rejected', [
    'gateway' => 'zibal',
    'reason' => $result['reason'],
    'trackingHash' => isset($result['trackId']) ? substr(hash('sha256', $result['trackId']), 0, 16) : '',
]);
echo dent_zibal_handoff_document($result, $nonce);
