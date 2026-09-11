<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';

const DENT_ZIBAL_HANDOFF_ORIGIN = 'https://dentistry1402tums.ir';
const DENT_ZIBAL_HANDOFF_PATH = '/payment/start/zibal/';
const DENT_ZIBAL_HANDOFF_TTL = 900;

function dent_zibal_track_id_valid(string $trackId): bool
{
    return preg_match('/^[1-9][0-9]{0,19}$/D', $trackId) === 1;
}

/** The provider URL stays internal; never accept a caller-supplied destination. */
function dent_zibal_provider_track_id(string $url): string
{
    if (preg_match('#^https://gateway\.zibal\.ir/start/([1-9][0-9]{0,19})$#D', $url, $match) !== 1) {
        throw new InvalidArgumentException('INVALID_ZIBAL_PROVIDER_URL');
    }
    return $match[1];
}

function dent_zibal_handoff_signature(string $trackId, string $expires): string
{
    return hash_hmac('sha256', 'zibal-payment-handoff-v1:' . $trackId . ':' . $expires, dent_auth_secret_key());
}

function dent_zibal_handoff_url(string $providerUrl, string $expectedTrackId, ?int $now = null): string
{
    $trackId = dent_zibal_provider_track_id($providerUrl);
    if (!hash_equals($expectedTrackId, $trackId)) {
        throw new InvalidArgumentException('ZIBAL_TRACK_MISMATCH');
    }
    $expires = (string) (($now ?? time()) + DENT_ZIBAL_HANDOFF_TTL);
    return DENT_ZIBAL_HANDOFF_ORIGIN . DENT_ZIBAL_HANDOFF_PATH
        . '?trackId=' . $trackId . '&exp=' . $expires
        . '&sig=' . dent_zibal_handoff_signature($trackId, $expires);
}

/** Parse the raw query to reject duplicate keys, PHP array syntax and alternate encodings. */
function dent_zibal_handoff_validate(string $query, ?int $now = null): array
{
    if (strlen($query) > 180 || preg_match('/^trackId=([1-9][0-9]{0,19})&exp=([1-9][0-9]{9})&sig=([a-f0-9]{64})$/D', $query, $match) !== 1) {
        return ['valid' => false, 'reason' => 'malformed'];
    }
    [, $trackId, $expires, $signature] = $match;
    if (!hash_equals(dent_zibal_handoff_signature($trackId, $expires), $signature)) {
        return ['valid' => false, 'reason' => 'signature'];
    }
    $now = $now ?? time();
    if ((int) $expires <= $now) {
        return ['valid' => false, 'reason' => 'expired'];
    }
    if ((int) $expires > $now + DENT_ZIBAL_HANDOFF_TTL + 60) {
        return ['valid' => false, 'reason' => 'expiry_range'];
    }
    return ['valid' => true, 'reason' => 'ok', 'trackId' => $trackId];
}

/** No state, transaction, verification or auth mutation belongs in this document. */
function dent_zibal_handoff_document(array $validated, string $nonce): string
{
    $valid = ($validated['valid'] ?? false) === true && dent_zibal_track_id_valid((string) ($validated['trackId'] ?? ''));
    $safeNonce = htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8');
    $title = $valid ? 'انتقال به درگاه پرداخت' : 'لینک پرداخت معتبر نیست';
    $message = $valid ? 'در حال انتقال به درگاه امن پرداخت…' : 'اعتبار این لینک پایان یافته یا لینک ناقص است. به ربات برگرد و پرداخت را دوباره باز کن.';
    $action = '';
    if ($valid) {
        $target = 'https://gateway.zibal.ir/start/' . $validated['trackId'];
        $action = '<p><a referrerpolicy="origin" href="' . $target . '">ادامه به درگاه پرداخت</a></p>'
            . '<script nonce="' . $safeNonce . '">window.location.replace('
            . json_encode($target, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');</script>';
    }
    return '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="referrer" content="origin">'
        . '<title>' . $title . '</title><style nonce="' . $safeNonce . '">'
        . 'body{font-family:Tahoma,Arial,sans-serif;background:#f4f7fb;color:#172033;margin:0;display:grid;min-height:100vh;place-items:center}'
        . 'main{background:white;max-width:32rem;margin:1.5rem;padding:2rem;border-radius:1.25rem;text-align:center;line-height:2}'
        . 'a{display:inline-block;background:#1778f2;color:white;padding:.6rem 1.4rem;border-radius:.75rem;text-decoration:none}'
        . '</style></head><body><main><h1>' . $title . '</h1><p>' . $message . '</p>' . $action . '</main></body></html>';
}
