<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Referrer-Policy: no-referrer');

if (!in_array(dent_request_method(), ['GET', 'POST'], true)) {
    dent_error('متد بازگشت پرداخت نامعتبر است.', 405);
}

$token = trim((string) ($_GET['token'] ?? ''));
if (preg_match('/^[A-Za-z0-9_-]{32}$/D', $token) !== 1) {
    dent_error('شناسه بازگشت پرداخت نامعتبر است.', 422);
}
$platform = trim((string) ($_GET['platform'] ?? 'telegram'));
if (!in_array($platform, ['telegram', 'bale'], true)) {
    dent_error('بستر بازگشت پرداخت نامعتبر است.', 422);
}

// The website remains stateless: the browser is returned to the independent
// voice-bot callback, where the canonical SQLite order is verified and credited.
$callbackPath = $platform === 'bale' ? 'voice-bale-pay' : 'voice-pay';
header('Location: http://185.239.0.235/' . $callbackPath . '/callback/' . rawurlencode($token), true, 303);
exit;
