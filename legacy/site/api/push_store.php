<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_store.php';

const PUSH_SCHEMA_VERSION = 1;
const PUSH_VAPID_SUBJECT = 'mailto:arian.ghpp@gmail.com';
const PUSH_TTL_SECONDS = 86400;
const PUSH_MAX_SUBSCRIPTIONS_PER_USER = 8;
const PUSH_MAX_RECIPIENTS_PER_DISPATCH = 600;

/**
 * Web Push needs P-256 ECDH key derivation (PHP 7.3+), HKDF, AES-128-GCM and
 * cURL. When the host lacks any of these the feature stays dormant and the rest
 * of the notification pipeline keeps working untouched.
 */
function push_supported(): bool
{
    return extension_loaded('openssl')
        && function_exists('openssl_pkey_new')
        && function_exists('openssl_pkey_derive')
        && function_exists('openssl_sign')
        && function_exists('hash_hkdf')
        && function_exists('curl_init');
}

function push_base64url_encode(string $binary): string
{
    return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
}

function push_base64url_decode(string $value): string
{
    $value = strtr(trim($value), '-_', '+/');
    $remainder = strlen($value) % 4;
    if ($remainder !== 0) {
        $value .= str_repeat('=', 4 - $remainder);
    }
    $decoded = base64_decode($value, true);

    return $decoded === false ? '' : $decoded;
}

function push_store_path(): string
{
    return dent_storage_path('push/subscriptions.json');
}

function push_store_lock_path(): string
{
    return dent_storage_path('push/subscriptions.lock');
}

function push_vapid_path(): string
{
    return dent_storage_path('push/vapid.json');
}

function push_vapid_lock_path(): string
{
    return dent_storage_path('push/vapid.lock');
}

function push_default_store(): array
{
    return [
        'schemaVersion' => PUSH_SCHEMA_VERSION,
        'subscriptions' => [],
    ];
}

function push_ensure_storage(): void
{
    dent_ensure_directory(dirname(push_store_path()));
    if (!is_file(push_store_path())) {
        dent_write_json_file(push_store_path(), push_default_store());
    }
}

function push_normalize_store($raw): array
{
    if (!is_array($raw)) {
        return push_default_store();
    }
    $subscriptions = is_array($raw['subscriptions'] ?? null) ? $raw['subscriptions'] : [];
    $clean = [];
    foreach ($subscriptions as $key => $subscription) {
        if (!is_array($subscription)) {
            continue;
        }
        $endpoint = trim((string) ($subscription['endpoint'] ?? ''));
        if ($endpoint === '') {
            continue;
        }
        $clean[(string) $key] = [
            'endpoint' => $endpoint,
            'p256dh' => trim((string) ($subscription['p256dh'] ?? '')),
            'auth' => trim((string) ($subscription['auth'] ?? '')),
            'studentNumber' => dent_normalize_student_number((string) ($subscription['studentNumber'] ?? '')),
            'cohortKey' => trim((string) ($subscription['cohortKey'] ?? '')),
            'createdAt' => (string) ($subscription['createdAt'] ?? ''),
            'lastSeenAt' => (string) ($subscription['lastSeenAt'] ?? ''),
        ];
    }

    return [
        'schemaVersion' => PUSH_SCHEMA_VERSION,
        'subscriptions' => $clean,
    ];
}

function push_read_store(): array
{
    push_ensure_storage();
    $lock = fopen(push_store_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('قفل خواندن اعلان‌های مرورگر در دسترس نیست.', 503);
    }
    try {
        if (!flock($lock, LOCK_SH)) {
            dent_error('قفل خواندن اعلان‌های مرورگر آماده نشد.', 503);
        }
        $raw = dent_read_json_file(push_store_path(), push_default_store());
        if (!isset($raw['subscriptions']) || !is_array($raw['subscriptions'])) {
            throw new DentJsonPersistenceException(
                'PUSH_STORE_SCHEMA_INVALID',
                'Existing push subscription store has an invalid schema'
            );
        }
        return push_normalize_store($raw);
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

/**
 * @template T
 * @param callable(array):T $callback
 * @return T
 */
function push_with_store_lock(callable $callback)
{
    push_ensure_storage();
    $lock = fopen(push_store_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل اعلان‌های مرورگر.', 500);
    }
    try {
        if (!flock($lock, LOCK_EX)) {
            dent_error('قفل ذخیره‌سازی اعلان‌های مرورگر آماده نشد.', 500);
        }
        $raw = dent_read_json_file(push_store_path(), push_default_store());
        if (!isset($raw['subscriptions']) || !is_array($raw['subscriptions'])) {
            throw new DentJsonPersistenceException(
                'PUSH_STORE_SCHEMA_INVALID',
                'Existing push subscription store has an invalid schema'
            );
        }
        $store = push_normalize_store($raw);
        $result = $callback($store);
        dent_write_json_file(push_store_path(), push_normalize_store($store));
        return $result;
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function push_subscription_key(string $endpoint): string
{
    return hash('sha256', trim($endpoint));
}

/**
 * Load (or lazily create) the site-wide VAPID identity keypair.
 *
 * @return array{publicKey:string,privateKeyPem:string,createdAt:string}|null
 */
function push_load_or_create_vapid(): ?array
{
    if (!push_supported()) {
        return null;
    }

    $vapidPath = push_vapid_path();
    $existing = dent_read_json_file($vapidPath, null);
    if (is_array($existing) && !empty($existing['publicKey']) && !empty($existing['privateKeyPem'])) {
        return $existing;
    }
    if (is_file($vapidPath)) {
        throw new DentJsonPersistenceException(
            'VAPID_STORE_SCHEMA_INVALID',
            'Existing VAPID identity store has an invalid schema'
        );
    }

    dent_ensure_directory(dirname($vapidPath));
    $lock = fopen(push_vapid_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('قفل کلید اعلان‌های مرورگر در دسترس نیست.', 503);
    }
    try {
        if (!flock($lock, LOCK_EX)) {
            dent_error('قفل کلید اعلان‌های مرورگر آماده نشد.', 503);
        }
        $existing = dent_read_json_file($vapidPath, null);
        if (is_array($existing) && !empty($existing['publicKey']) && !empty($existing['privateKeyPem'])) {
            return $existing;
        }
        if (is_file($vapidPath)) {
            throw new DentJsonPersistenceException(
                'VAPID_STORE_SCHEMA_INVALID',
                'Existing VAPID identity store has an invalid schema'
            );
        }

        $resource = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        if (!$resource) {
            return null;
        }
        $privatePem = '';
        if (!openssl_pkey_export($resource, $privatePem)) {
            return null;
        }
        $details = openssl_pkey_get_details($resource);
        $x = (string) ($details['ec']['x'] ?? '');
        $y = (string) ($details['ec']['y'] ?? '');
        if (strlen($x) !== 32 || strlen($y) !== 32) {
            return null;
        }
        $publicRaw = "\x04" . $x . $y;
        $vapid = [
            'publicKey' => push_base64url_encode($publicRaw),
            'privateKeyPem' => $privatePem,
            'createdAt' => dent_iso_now(),
        ];
        dent_write_json_file($vapidPath, $vapid);

        return $vapid;
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function push_public_key(): string
{
    $vapid = push_load_or_create_vapid();
    return is_array($vapid) ? (string) ($vapid['publicKey'] ?? '') : '';
}

/**
 * Build a PEM SubjectPublicKeyInfo wrapper around a raw 65-byte P-256 point so
 * openssl_pkey_derive can consume the browser-provided public key.
 */
function push_p256_public_pem_from_raw(string $rawPoint): string
{
    if (strlen($rawPoint) !== 65 || $rawPoint[0] !== "\x04") {
        return '';
    }
    $prefix = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
    if ($prefix === false) {
        return '';
    }
    $der = $prefix . $rawPoint;

    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/**
 * Convert an OpenSSL DER ECDSA signature into the raw 64-byte (r||s) form that
 * the VAPID JWS (ES256) requires.
 */
function push_der_to_raw_signature(string $der): string
{
    $length = strlen($der);
    $offset = 0;
    if ($length < 8 || ord($der[$offset++]) !== 0x30) {
        return '';
    }
    $seqLen = ord($der[$offset++]);
    if (($seqLen & 0x80) !== 0) {
        $offset += ($seqLen & 0x7f);
    }
    if ($offset >= $length || ord($der[$offset++]) !== 0x02) {
        return '';
    }
    $rLen = ord($der[$offset++]);
    $r = substr($der, $offset, $rLen);
    $offset += $rLen;
    if ($offset >= $length || ord($der[$offset++]) !== 0x02) {
        return '';
    }
    $sLen = ord($der[$offset++]);
    $s = substr($der, $offset, $sLen);

    $r = ltrim($r, "\x00");
    $s = ltrim($s, "\x00");
    if (strlen($r) > 32 || strlen($s) > 32) {
        return '';
    }
    $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
    $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);

    return $r . $s;
}

function push_vapid_jwt(string $audience, array $vapid): string
{
    $privatePem = (string) ($vapid['privateKeyPem'] ?? '');
    if ($privatePem === '' || $audience === '') {
        return '';
    }

    $header = push_base64url_encode((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_UNESCAPED_SLASHES));
    $payload = push_base64url_encode((string) json_encode([
        'aud' => $audience,
        'exp' => time() + (12 * 3600),
        'sub' => PUSH_VAPID_SUBJECT,
    ], JSON_UNESCAPED_SLASHES));
    $signingInput = $header . '.' . $payload;

    $privateKey = openssl_pkey_get_private($privatePem);
    if (!$privateKey) {
        return '';
    }
    $der = '';
    if (!openssl_sign($signingInput, $der, $privateKey, OPENSSL_ALGO_SHA256)) {
        return '';
    }
    $raw = push_der_to_raw_signature($der);
    if (strlen($raw) !== 64) {
        return '';
    }

    return $signingInput . '.' . push_base64url_encode($raw);
}

/**
 * Encrypt a payload with the aes128gcm content encoding (RFC 8188 / RFC 8291).
 *
 * @return array{body:string}|null
 */
function push_encrypt_payload(string $payload, string $uaPublicRaw, string $authSecret, ?string $fixedSalt = null, $fixedServerKey = null): ?array
{
    if (strlen($uaPublicRaw) !== 65 || $uaPublicRaw[0] !== "\x04" || strlen($authSecret) < 16) {
        return null;
    }

    $salt = $fixedSalt !== null ? $fixedSalt : random_bytes(16);
    if (strlen($salt) !== 16) {
        return null;
    }

    $serverKey = $fixedServerKey ?: openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1',
    ]);
    if (!$serverKey) {
        return null;
    }
    $serverDetails = openssl_pkey_get_details($serverKey);
    $serverX = (string) ($serverDetails['ec']['x'] ?? '');
    $serverY = (string) ($serverDetails['ec']['y'] ?? '');
    if (strlen($serverX) !== 32 || strlen($serverY) !== 32) {
        return null;
    }
    $serverPublicRaw = "\x04" . $serverX . $serverY;

    $uaPem = push_p256_public_pem_from_raw($uaPublicRaw);
    if ($uaPem === '') {
        return null;
    }
    $uaKey = openssl_pkey_get_public($uaPem);
    if (!$uaKey) {
        return null;
    }
    $sharedSecret = openssl_pkey_derive($uaKey, $serverKey, 32);
    if ($sharedSecret === false || $sharedSecret === null || strlen($sharedSecret) !== 32) {
        return null;
    }

    $keyInfo = "WebPush: info\x00" . $uaPublicRaw . $serverPublicRaw;
    $ikm = hash_hkdf('sha256', $sharedSecret, 32, $keyInfo, $authSecret);
    $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt);

    $recordSize = 4096;
    $plaintext = $payload . "\x02";
    if (strlen($plaintext) > $recordSize - 16) {
        return null;
    }

    $tag = '';
    $cipher = openssl_encrypt($plaintext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($cipher === false || $tag === '') {
        return null;
    }

    $header = $salt . pack('N', $recordSize) . chr(strlen($serverPublicRaw)) . $serverPublicRaw;
    $body = $header . $cipher . $tag;

    return ['body' => $body];
}

/**
 * @return array{ok:bool,status:int,gone:bool,error:string}
 */
function push_send_one(array $subscription, string $payloadJson, array $vapid): array
{
    $result = ['ok' => false, 'status' => 0, 'gone' => false, 'error' => ''];

    $endpoint = trim((string) ($subscription['endpoint'] ?? ''));
    if ($endpoint === '' || stripos($endpoint, 'https://') !== 0) {
        $result['error'] = 'invalid-endpoint';
        return $result;
    }

    $p256dh = push_base64url_decode((string) ($subscription['p256dh'] ?? ''));
    $auth = push_base64url_decode((string) ($subscription['auth'] ?? ''));
    if (strlen($p256dh) !== 65 || strlen($auth) < 16) {
        $result['error'] = 'invalid-keys';
        return $result;
    }

    $encrypted = push_encrypt_payload($payloadJson, $p256dh, $auth);
    if ($encrypted === null) {
        $result['error'] = 'encrypt-failed';
        return $result;
    }

    $parts = parse_url($endpoint);
    $audience = (string) ($parts['scheme'] ?? 'https') . '://' . (string) ($parts['host'] ?? '');
    $jwt = push_vapid_jwt($audience, $vapid);
    if ($jwt === '') {
        $result['error'] = 'jwt-failed';
        return $result;
    }

    $headers = [
        'Authorization: vapid t=' . $jwt . ', k=' . (string) ($vapid['publicKey'] ?? ''),
        'Content-Encoding: aes128gcm',
        'Content-Type: application/octet-stream',
        'TTL: ' . PUSH_TTL_SECONDS,
        'Urgency: normal',
    ];

    $ch = curl_init($endpoint);
    if ($ch === false) {
        $result['error'] = 'curl-init-failed';
        return $result;
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $encrypted['body'],
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 6,
    ]);
    curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = (string) curl_error($ch);
    curl_close($ch);

    $result['status'] = $status;
    $result['error'] = $error;
    $result['ok'] = $status >= 200 && $status < 300;
    $result['gone'] = in_array($status, [404, 410], true);

    return $result;
}

function push_save_subscription(array $user, string $cohortKey, array $subscription): array
{
    $endpoint = trim((string) ($subscription['endpoint'] ?? ''));
    if ($endpoint === '' || stripos($endpoint, 'https://') !== 0) {
        return ['success' => false, 'reason' => 'invalid-endpoint'];
    }
    $p256dh = trim((string) ($subscription['p256dh'] ?? ''));
    $auth = trim((string) ($subscription['auth'] ?? ''));
    if (strlen(push_base64url_decode($p256dh)) !== 65 || strlen(push_base64url_decode($auth)) < 16) {
        return ['success' => false, 'reason' => 'invalid-keys'];
    }

    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($studentNumber === '') {
        return ['success' => false, 'reason' => 'no-user'];
    }
    $key = push_subscription_key($endpoint);
    $now = dent_iso_now();

    push_with_store_lock(static function (array &$store) use ($key, $endpoint, $p256dh, $auth, $studentNumber, $cohortKey, $now): void {
        $existingCreatedAt = (string) ($store['subscriptions'][$key]['createdAt'] ?? '');
        $store['subscriptions'][$key] = [
            'endpoint' => $endpoint,
            'p256dh' => $p256dh,
            'auth' => $auth,
            'studentNumber' => $studentNumber,
            'cohortKey' => trim($cohortKey),
            'createdAt' => $existingCreatedAt !== '' ? $existingCreatedAt : $now,
            'lastSeenAt' => $now,
        ];

        $owned = [];
        foreach ($store['subscriptions'] as $subKey => $row) {
            if (dent_normalize_student_number((string) ($row['studentNumber'] ?? '')) === $studentNumber) {
                $owned[$subKey] = (string) ($row['lastSeenAt'] ?? ($row['createdAt'] ?? ''));
            }
        }
        if (count($owned) > PUSH_MAX_SUBSCRIPTIONS_PER_USER) {
            asort($owned);
            $removeCount = count($owned) - PUSH_MAX_SUBSCRIPTIONS_PER_USER;
            foreach (array_keys($owned) as $subKey) {
                if ($removeCount <= 0) {
                    break;
                }
                if ($subKey === $key) {
                    continue;
                }
                unset($store['subscriptions'][$subKey]);
                $removeCount--;
            }
        }
    });

    return ['success' => true];
}

function push_remove_subscription_by_endpoint(string $endpoint): array
{
    $endpoint = trim($endpoint);
    if ($endpoint === '') {
        return ['success' => false, 'reason' => 'invalid-endpoint'];
    }
    $key = push_subscription_key($endpoint);
    push_with_store_lock(static function (array &$store) use ($key): void {
        unset($store['subscriptions'][$key]);
    });

    return ['success' => true];
}

function push_user_has_subscription(string $studentNumber): bool
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        return false;
    }
    $store = push_read_store();
    foreach ($store['subscriptions'] as $row) {
        if (dent_normalize_student_number((string) ($row['studentNumber'] ?? '')) === $studentNumber) {
            return true;
        }
    }

    return false;
}

function push_subscriptions_for_student_numbers(array $studentNumbers): array
{
    $wanted = [];
    foreach ($studentNumbers as $number) {
        $clean = dent_normalize_student_number((string) $number);
        if ($clean !== '') {
            $wanted[$clean] = true;
        }
    }
    if ($wanted === []) {
        return [];
    }

    $store = push_read_store();
    $matched = [];
    foreach ($store['subscriptions'] as $key => $row) {
        $number = dent_normalize_student_number((string) ($row['studentNumber'] ?? ''));
        if ($number !== '' && isset($wanted[$number])) {
            $matched[(string) $key] = $row;
        }
    }

    return $matched;
}

function push_build_notification_payload(array $record): string
{
    $title = dent_clean_text((string) ($record['title'] ?? ''), 120);
    if ($title === '') {
        $title = 'اعلان جدید';
    }
    $body = dent_clean_text((string) ($record['body'] ?? ''), 240);
    $url = (string) ($record['ctaHref'] ?? '');
    if ($url === '' || strpos($url, '/') !== 0 || strpos($url, '//') === 0) {
        $url = (string) ($record['kind'] ?? '') === 'navid-assignment' ? '/navid/' : '/account/#notifications';
    }

    return (string) json_encode([
        'title' => $title,
        'body' => $body,
        'url' => $url,
        'tag' => (string) ($record['id'] ?? ''),
        'kind' => (string) ($record['kind'] ?? ''),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Deliver a freshly-activated notification to subscribed browsers. Idempotent
 * via the record `pushStatus` flag, fully isolated from the SMS/in-site paths
 * and guaranteed never to throw into the caller.
 */
function notifications_dispatch_push_if_needed(string $notificationId): array
{
    $notificationId = trim($notificationId);
    if ($notificationId === '' || !push_supported()) {
        return ['success' => false, 'reason' => 'unsupported'];
    }

    $dispatch = notifications_with_store_lock(static function (array &$store) use ($notificationId): array {
        $record = is_array($store['notifications'][$notificationId] ?? null)
            ? $store['notifications'][$notificationId]
            : null;
        if ($record === null) {
            return ['success' => false, 'reason' => 'missing'];
        }
        if (notifications_record_is_scheduled($record)) {
            return ['success' => false, 'reason' => 'scheduled'];
        }
        $status = (string) ($record['pushStatus'] ?? '');
        if ($status === 'sending' || $status === 'sent' || $status === 'empty') {
            return ['success' => false, 'reason' => 'already-processed'];
        }
        $record['pushStatus'] = 'sending';
        $store['notifications'][$notificationId] = $record;

        return ['success' => true, 'record' => $record];
    });

    if (empty($dispatch['success'])) {
        return $dispatch;
    }

    $record = is_array($dispatch['record'] ?? null) ? $dispatch['record'] : [];
    $recipients = notifications_record_recipients($record);
    $studentNumbers = [];
    foreach ($recipients as $recipient) {
        $studentNumbers[] = (string) ($recipient['studentNumber'] ?? '');
    }
    $subscriptions = push_subscriptions_for_student_numbers($studentNumbers);
    if (count($subscriptions) > PUSH_MAX_RECIPIENTS_PER_DISPATCH) {
        $subscriptions = array_slice($subscriptions, 0, PUSH_MAX_RECIPIENTS_PER_DISPATCH, true);
    }

    $sent = 0;
    $failed = 0;
    $gone = [];

    if ($subscriptions !== []) {
        $vapid = push_load_or_create_vapid();
        if (is_array($vapid)) {
            $payloadJson = push_build_notification_payload($record);
            foreach ($subscriptions as $key => $subscription) {
                $sendResult = push_send_one($subscription, $payloadJson, $vapid);
                if (!empty($sendResult['ok'])) {
                    $sent++;
                } else {
                    $failed++;
                    if (!empty($sendResult['gone'])) {
                        $gone[] = (string) $key;
                    }
                }
            }
        }
    }

    if ($gone !== []) {
        push_with_store_lock(static function (array &$store) use ($gone): void {
            foreach ($gone as $key) {
                unset($store['subscriptions'][$key]);
            }
        });
    }

    notifications_with_store_lock(static function (array &$store) use ($notificationId, $sent, $failed): void {
        if (!is_array($store['notifications'][$notificationId] ?? null)) {
            return;
        }
        $record = $store['notifications'][$notificationId];
        $record['pushStatus'] = ($sent + $failed) === 0 ? 'empty' : 'sent';
        $record['pushSentCount'] = $sent;
        $record['pushFailedCount'] = $failed;
        $record['pushDispatchedAt'] = dent_iso_now();
        $store['notifications'][$notificationId] = $record;
    });

    return ['success' => true, 'sent' => $sent, 'failed' => $failed];
}
