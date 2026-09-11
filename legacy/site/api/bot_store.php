<?php
declare(strict_types=1);

require_once __DIR__ . '/grades_store.php';
require_once __DIR__ . '/bot_payments.php';
require_once __DIR__ . '/bot_persistence.php';
require_once __DIR__ . '/bot_delivery_store.php';
require_once __DIR__ . '/bot_notifications.php';
require_once __DIR__ . '/bot_navid.php';
require_once __DIR__ . '/bot_student_assistant.php';
require_once __DIR__ . '/bot_onboarding.php';
require_once __DIR__ . '/bot_voice_payment_bridge.php';

function dent_bot_store_path(): string
{
    return dent_storage_path('integrations/bot_links.json');
}

function dent_bot_store_default(): array
{
    return [
        'schemaVersion' => 6,
        'links' => [],
        'challenges' => [],
        'identityCandidates' => [],
        'identityClaims' => [],
        'nonces' => [],
        'audit' => [],
        'notificationDeliveries' => [],
        'accountDisconnectDeliveries' => [],
        'paymentResultDeliveries' => [],
        'paymentResultPoll' => ['highWatermark' => 0, 'unresolvedOrderIds' => []],
        'notificationDispatchSince' => '',
        'deliveryStoreMigration' => [],
        'onboardingProfiles' => [],
        'onboardingIdentityProfiles' => [],
        'onboardingIdentityRoutes' => [],
        'onboardingChallenges' => [],
        'onboardingEditRequests' => [],
    ];
}

function dent_bot_store_normalize(array $store): array
{
    $defaults = dent_bot_store_default();
    foreach (['links', 'challenges', 'identityCandidates', 'identityClaims', 'nonces', 'audit', 'notificationDeliveries', 'accountDisconnectDeliveries', 'paymentResultDeliveries', 'paymentResultPoll', 'deliveryStoreMigration', 'onboardingProfiles', 'onboardingIdentityProfiles', 'onboardingIdentityRoutes', 'onboardingChallenges', 'onboardingEditRequests'] as $key) {
        if (array_key_exists($key, $store) && !is_array($store[$key])) {
            throw new DentBotPersistenceException('BOT_STORE_SCHEMA_INVALID', 'Bot store collection has an invalid type');
        }
    }
    $normalized = array_merge($defaults, $store);
    $normalized['schemaVersion'] = max(6, (int) ($normalized['schemaVersion'] ?? 0));
    if (isset($normalized['_storage']) && !is_array($normalized['_storage'])) {
        throw new DentBotPersistenceException('BOT_STORE_SCHEMA_INVALID', 'Bot store generation metadata is invalid');
    }
    return $normalized;
}

function dent_bot_store_fail(DentBotPersistenceException $exception): never
{
    $code = $exception->reasonCode;
    $message = $code === 'BOT_STORE_CORRUPT' || $code === 'BOT_STORE_SCHEMA_INVALID'
        ? 'داده اتصال ربات آسیب دیده و برای جلوگیری از حذف اطلاعات موقتاً قفل شده است.'
        : 'سرویس اتصال حساب موقتاً در دسترس نیست.';
    dent_error($message, 503, ['code' => $code]);
}

function dent_bot_store_with_lock(callable $callback, string $action = 'bot-store-write'): array
{
    try {
        return dent_bot_persistence_update(
            dent_bot_store_path(),
            dent_bot_store_default(),
            'dent_bot_store_normalize',
            $callback,
            false,
            $action
        );
    } catch (DentBotPersistenceException $exception) {
        dent_bot_store_fail($exception);
    }
}

function dent_bot_store_read(callable $callback, string $action = 'bot-store-read'): array
{
    try {
        return dent_bot_persistence_read(
            dent_bot_store_path(),
            dent_bot_store_default(),
            'dent_bot_store_normalize',
            $callback,
            $action
        );
    } catch (DentBotPersistenceException $exception) {
        dent_bot_store_fail($exception);
    }
}

function dent_bot_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function dent_bot_service_secret(): string
{
    $raw = trim((string) (getenv('DENT_BOT_SERVICE_SECRET') ?: ''));
    $decoded = null;
    if (preg_match('/^[a-f0-9]{64,}$/i', $raw) === 1) {
        $decoded = hex2bin(substr($raw, 0, 64));
    } elseif (preg_match('/^[A-Za-z0-9_-]{43,}$/', $raw) === 1) {
        $padding = str_repeat('=', (4 - strlen($raw) % 4) % 4);
        $decoded = base64_decode(strtr($raw . $padding, '-_', '+/'), true);
    }
    if (!is_string($decoded) || strlen($decoded) < 32) {
        dent_error('سرویس ربات پیکربندی نشده است.', 503);
    }
    return substr($decoded, 0, 32);
}

function dent_bot_identity(string $platform, string $platformUserId): array
{
    $platform = strtolower(trim($platform));
    $platformUserId = trim($platformUserId);
    if (!in_array($platform, ['telegram', 'bale'], true) || preg_match('/^[0-9]{1,24}$/', $platformUserId) !== 1) {
        dent_error('هویت ربات نامعتبر است.', 422);
    }
    return [$platform, $platformUserId];
}

function dent_bot_identity_hash(string $platform, string $platformUserId): string
{
    [$platform, $platformUserId] = dent_bot_identity($platform, $platformUserId);
    return hash_hmac('sha256', $platform . ':' . $platformUserId, dent_auth_secret_key());
}

function dent_bot_token_hash(string $token): string
{
    return hash_hmac('sha256', $token, dent_auth_secret_key());
}

function dent_bot_service_nonce_store_path(): string
{
    return dent_storage_path('integrations/bot_service_nonces.json');
}

function dent_bot_service_nonce_store_default(): array
{
    return ['schemaVersion' => 1, 'nonces' => []];
}

function dent_bot_service_nonce_store_normalize(array $store): array
{
    if (array_key_exists('nonces', $store) && !is_array($store['nonces'])) {
        throw new DentBotPersistenceException('BOT_NONCE_STORE_SCHEMA_INVALID', 'Bot nonce collection has an invalid type');
    }
    $store = array_merge(dent_bot_service_nonce_store_default(), $store);
    $store['schemaVersion'] = max(1, (int) ($store['schemaVersion'] ?? 0));
    return $store;
}

function dent_bot_service_nonce_remember(string $nonce): void
{
    try {
        dent_bot_persistence_update(
            dent_bot_service_nonce_store_path(),
            dent_bot_service_nonce_store_default(),
            'dent_bot_service_nonce_store_normalize',
            static function (array &$store) use ($nonce): array {
                $now = time();
                foreach ($store['nonces'] as $key => $expiresAt) {
                    if ((int) $expiresAt < $now) {
                        unset($store['nonces'][$key]);
                    }
                }
                $nonceKey = hash_hmac('sha256', $nonce, dent_auth_secret_key());
                if (isset($store['nonces'][$nonceKey])) {
                    dent_error('درخواست سرویس تکراری است.', 409);
                }
                $store['nonces'][$nonceKey] = $now + 180;
                return [];
            },
            true,
            'service-nonce-write'
        );
    } catch (DentBotPersistenceException $exception) {
        dent_bot_store_fail($exception);
    }
}

function dent_bot_cleanup_store(array &$store, int $now): void
{
    foreach ($store['challenges'] as $key => $challenge) {
        if (!is_array($challenge) || (int) ($challenge['expiresAt'] ?? 0) < $now - 3600) {
            unset($store['challenges'][$key]);
        }
    }
    foreach ($store['nonces'] as $key => $expiresAt) {
        if ((int) $expiresAt < $now) {
            unset($store['nonces'][$key]);
        }
    }
    foreach ($store['onboardingChallenges'] as $key => $challenge) {
        if (!is_array($challenge) || (int) ($challenge['expiresAt'] ?? 0) < $now - 3600) {
            unset($store['onboardingChallenges'][$key]);
        }
    }
    if (count($store['audit']) > 500) {
        $store['audit'] = array_slice($store['audit'], -500);
    }

    $retentionDays = max(7, min(365, (int) (getenv('DENT_BOT_NOTIFICATION_DELIVERY_RETENTION_DAYS') ?: 90)));
    $terminalBefore = $now - ($retentionDays * 86400);
    foreach ($store['notificationDeliveries'] as $key => $delivery) {
        if (!is_array($delivery)) {
            unset($store['notificationDeliveries'][$key]);
            continue;
        }
        $status = (string) ($delivery['status'] ?? '');
        $referenceAt = strtotime((string) (($delivery['deliveredAt'] ?? '') ?: ($delivery['lastAttemptAt'] ?? '')));
        if (in_array($status, ['delivered', 'failed'], true) && $referenceAt !== false && $referenceAt < $terminalBefore) {
            unset($store['notificationDeliveries'][$key]);
        }
    }
    foreach ($store['accountDisconnectDeliveries'] as $key => $delivery) {
        if (!is_array($delivery)) {
            unset($store['accountDisconnectDeliveries'][$key]);
            continue;
        }
        $status = (string) ($delivery['status'] ?? '');
        $referenceAt = strtotime((string) (($delivery['deliveredAt'] ?? '') ?: ($delivery['lastAttemptAt'] ?? '') ?: ($delivery['createdAt'] ?? '')));
        if (in_array($status, ['delivered', 'failed'], true) && $referenceAt !== false && $referenceAt < $terminalBefore) {
            unset($store['accountDisconnectDeliveries'][$key]);
        }
    }
    foreach ($store['paymentResultDeliveries'] as $key => $delivery) {
        if (!is_array($delivery)) {
            unset($store['paymentResultDeliveries'][$key]);
            continue;
        }
        $status = (string) ($delivery['status'] ?? '');
        $referenceAt = strtotime((string) (($delivery['deliveredAt'] ?? '') ?: ($delivery['lastAttemptAt'] ?? '') ?: ($delivery['createdAt'] ?? '')));
        if (in_array($status, ['delivered', 'failed'], true) && $referenceAt !== false && $referenceAt < $terminalBefore) {
            unset($store['paymentResultDeliveries'][$key]);
        }
    }
}

function dent_bot_audit(array &$store, string $event, string $identityHash, string $studentNumber = '', string $reason = ''): void
{
    $record = [
        'event' => dent_clean_text($event, 80),
        'identityRef' => substr($identityHash, 0, 16),
        'studentRef' => $studentNumber !== '' ? substr(hash('sha256', $studentNumber), 0, 16) : '',
        'createdAt' => dent_iso_now(),
    ];
    if ($reason !== '') {
        $record['reason'] = dent_clean_text($reason, 240);
    }
    $store['audit'][] = $record;
}

function dent_bot_verify_service_signature(string $body, string $timestamp, string $nonce, string $signature): void
{
    if (!ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 90) {
        dent_error('درخواست سرویس نامعتبر است.', 401);
    }
    if (preg_match('/^[A-Za-z0-9_-]{20,120}$/', $nonce) !== 1 || preg_match('/^[a-f0-9]{64}$/i', $signature) !== 1) {
        dent_error('درخواست سرویس نامعتبر است.', 401);
    }
    $expected = hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . hash('sha256', $body), dent_bot_service_secret());
    if (!hash_equals($expected, strtolower($signature))) {
        dent_error('درخواست سرویس نامعتبر است.', 401);
    }

    dent_bot_service_nonce_remember($nonce);
}

function dent_bot_service_request(): array
{
    if (!in_array(dent_request_method(), ['POST', 'PUT'], true)) {
        dent_error('متد سرویس نامعتبر است.', 405);
    }
    $body = file_get_contents('php://input');
    if (!is_string($body) || $body === '' || strlen($body) > 32768) {
        dent_error('بدنه درخواست سرویس نامعتبر است.', 400);
    }
    dent_bot_verify_service_signature(
        $body,
        trim((string) ($_SERVER['HTTP_X_DENT_TIMESTAMP'] ?? '')),
        trim((string) ($_SERVER['HTTP_X_DENT_NONCE'] ?? '')),
        trim((string) ($_SERVER['HTTP_X_DENT_SIGNATURE'] ?? ''))
    );
    $payload = json_decode($body, true);
    if (!is_array($payload)) {
        dent_error('بدنه درخواست سرویس نامعتبر است.', 400);
    }
    return $payload;
}

function dent_bot_public_user(array $user): array
{
    $public = dent_public_user($user);
    return [
        'name' => (string) ($public['name'] ?? ''),
        'studentNumber' => (string) ($public['studentNumber'] ?? ''),
        'disNumber' => (string) ($public['disNumber'] ?? ''),
        'role' => (string) ($public['role'] ?? 'student'),
        'roleLabel' => (string) ($public['roleLabel'] ?? ''),
        'cohortKey' => (string) ($public['cohortKey'] ?? ''),
        'isOwner' => !empty($public['isOwner']),
    ];
}

function dent_bot_booklet_watermark_identity(
    array $user,
    array $payload,
    string $platform,
    string $platformUserId
): array
{
    if ((string) ($payload['contractVersion'] ?? '') !== 'booklet-watermark-identity-v1') {
        dent_error('نسخه قرارداد هویت واترمارک پشتیبانی نمی‌شود.', 409, ['code' => 'CONTRACT_MISMATCH']);
    }
    $fullName = dent_clean_text((string) ($user['name'] ?? ''), 160);
    $directoryIdentity = dent_dis_request_private_identity_for_student((string) ($user['studentNumber'] ?? ''));
    $nationalCode = dent_normalize_national_code((string) (
        $user['nationalCode'] ?? ($user['private']['nationalCode'] ?? '')
    ));
    if ($nationalCode === '') {
        $nationalCode = dent_normalize_national_code((string) ($directoryIdentity['nationalCode'] ?? ''));
    }
    $profile = dent_bot_onboarding_private_profile($platform, $platformUserId);
    $phoneNumber = dent_normalize_phone_number((string) ($profile['phoneNumber'] ?? ''));
    if ($phoneNumber === '') {
        $phoneNumber = dent_normalize_phone_number((string) ($user['phoneNumber'] ?? ''));
    }
    if ($phoneNumber === '') {
        $phoneNumber = dent_normalize_phone_number((string) ($directoryIdentity['phoneNumber'] ?? ''));
    }
    $phoneVerifiedAt = trim((string) (
        $profile['verifiedAt'] ?? ($user['phoneVerifiedAt'] ?? '')
    ));
    if ($fullName === '' || $nationalCode === '' || $phoneNumber === '' || $phoneVerifiedAt === '') {
        dent_error(
            'برای دریافت جزوه شخصی، نام کامل، کد ملی و موبایل تأییدشده باید در حساب ثبت شده باشد.',
            409,
            ['code' => 'BOOKLET_IDENTITY_INCOMPLETE']
        );
    }
    return [
        'success' => true,
        'identity' => [
            'fullName' => $fullName,
            'nationalCode' => $nationalCode,
            'phoneNumber' => $phoneNumber,
        ],
    ];
}

function dent_bot_normalize_identity_name(string $value): string
{
    $value = strtr($value, ['ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک']);
    $value = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}\x{FEFF}]/u', ' ', $value) ?? $value;
    $value = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $value) ?? $value;
    return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
}

function dent_bot_telegram_profile(array $payload): array
{
    $profile = is_array($payload['telegramProfile'] ?? null) ? $payload['telegramProfile'] : [];
    $username = ltrim(dent_clean_text((string) ($profile['username'] ?? ''), 32), '@');
    if ($username !== '' && preg_match('/^[A-Za-z0-9_]{5,32}$/', $username) !== 1) {
        $username = '';
    }
    return [
        'displayName' => dent_clean_text((string) ($profile['displayName'] ?? ''), 128),
        'username' => $username,
        'languageCode' => preg_match('/^[A-Za-z-]{2,16}$/', (string) ($profile['languageCode'] ?? '')) === 1
            ? (string) $profile['languageCode']
            : '',
        'isPremium' => !empty($profile['isPremium']),
    ];
}

function dent_bot_conflicting_link(array $store, string $identityHash, string $platform, string $studentNumber): ?array
{
    foreach ($store['links'] as $key => $link) {
        if (!is_array($link)) {
            continue;
        }
        if ((string) $key === $identityHash && (string) ($link['studentNumber'] ?? '') !== $studentNumber) {
            return ['type' => 'identity', 'key' => (string) $key, 'link' => $link];
        }
        if ((string) $key !== $identityHash
            && (string) ($link['platform'] ?? '') === $platform
            && (string) ($link['studentNumber'] ?? '') === $studentNumber) {
            return ['type' => 'student', 'key' => (string) $key, 'link' => $link];
        }
    }
    return null;
}

function dent_bot_public_identity_state(string $platform, string $platformUserId): array
{
    $identityHash = dent_bot_identity_hash($platform, $platformUserId);
    return dent_bot_store_read(static function (array $store) use ($identityHash): array {
        $candidate = $store['identityCandidates'][$identityHash] ?? null;
        $claim = $store['identityClaims'][$identityHash] ?? null;
        return [
            'recognized' => is_array($candidate),
            'candidateStatus' => is_array($candidate) ? (string) ($candidate['status'] ?? 'recognized') : '',
            'claimStatus' => is_array($claim) ? (string) ($claim['status'] ?? '') : '',
        ];
    });
}

function dent_bot_require_owner(array $user): void
{
    if (empty(dent_public_user($user)['isOwner'])) {
        dent_error('این عملیات فقط برای مالک در دسترس است.', 403);
    }
}

function dent_bot_import_identity_candidates(array $owner, string $platform, array $payload): array
{
    dent_bot_require_owner($owner);
    $items = $payload['candidates'] ?? null;
    if (!is_array($items) || count($items) < 1 || count($items) > 200) {
        dent_error('فهرست تطبیق هویت نامعتبر است.', 422);
    }
    $accepted = 0;
    $linked = 0;
    $waitingAccount = 0;
    $skipped = 0;
    dent_bot_store_with_lock(static function (array &$store) use ($items, $platform, &$accepted, &$linked, &$waitingAccount, &$skipped): array {
        foreach ($items as $item) {
            if (!is_array($item)) {
                $skipped++;
                continue;
            }
            $cleanPlatform = strtolower(trim($platform));
            $platformUserId = trim((string) ($item['platformUserId'] ?? ''));
            if (!in_array($cleanPlatform, ['telegram', 'bale'], true)
                || preg_match('/^[0-9]{1,24}$/', $platformUserId) !== 1) {
                $skipped++;
                continue;
            }
            $studentNumber = dent_normalize_student_number((string) ($item['studentNumber'] ?? ''));
            $matchMethod = (string) ($item['matchMethod'] ?? '');
            if ($studentNumber === '' || !in_array($matchMethod, ['verified_phone', 'exact_unique_name'], true)) {
                $skipped++;
                continue;
            }
            $identityHash = dent_bot_identity_hash($cleanPlatform, $platformUserId);
            $user = dent_get_user_record($studentNumber);
            $approved = !empty($item['ownerApproved']);
            $status = is_array($user) ? ($approved ? 'approved' : 'recognized') : 'waiting_account';
            $store['identityCandidates'][$identityHash] = [
                'identityHash' => $identityHash,
                'platform' => $cleanPlatform,
                'platformUserIdEncrypted' => dent_encrypt_secret_text($platformUserId),
                'studentNumber' => $studentNumber,
                'matchMethod' => $matchMethod,
                'status' => $status,
                'source' => 'owner-class-group-import',
                'updatedAt' => dent_iso_now(),
            ];
            $accepted++;
            if (!is_array($user)) {
                $waitingAccount++;
                continue;
            }
            if (!$approved) {
                continue;
            }
            $conflict = dent_bot_conflicting_link($store, $identityHash, $cleanPlatform, $studentNumber);
            if (is_array($conflict)) {
                $skipped++;
                continue;
            }
            $store['links'][$identityHash] = [
                'identityHash' => $identityHash,
                'platform' => $cleanPlatform,
                'platformUserIdEncrypted' => dent_encrypt_secret_text($platformUserId),
                'studentNumber' => $studentNumber,
                'linkedAt' => dent_iso_now(),
                'source' => 'owner-class-group-import',
            ];
            dent_bot_audit($store, 'class-identity-linked', $identityHash, $studentNumber);
            $linked++;
        }
        return [];
    });
    return [
        'success' => true,
        'accepted' => $accepted,
        'linked' => $linked,
        'waitingAccount' => $waitingAccount,
        'skipped' => $skipped,
    ];
}

function dent_bot_submit_identity_claim(string $platform, string $platformUserId, array $payload): array
{
    [$platform, $platformUserId] = dent_bot_identity($platform, $platformUserId);
    $claimedName = dent_clean_text((string) ($payload['name'] ?? ''), 120);
    $normalizedName = dent_bot_normalize_identity_name($claimedName);
    $characterCount = preg_match_all('/./u', $normalizedName, $unusedMatches);
    if (!is_int($characterCount) || $characterCount < 5) {
        dent_error('نام و نام خانوادگی کامل را وارد کن.', 422);
    }
    $identityHash = dent_bot_identity_hash($platform, $platformUserId);
    if (is_array(dent_bot_link_for_identity($platform, $platformUserId))) {
        return ['success' => true, 'status' => 'already-linked'];
    }
    $telegramProfile = dent_bot_telegram_profile($payload);
    $candidate = dent_bot_store_read(static function (array $store) use ($identityHash): array {
        return ['candidate' => is_array($store['identityCandidates'][$identityHash] ?? null)
            ? $store['identityCandidates'][$identityHash]
            : null];
    })['candidate'] ?? null;
    $targetStudentNumber = is_array($candidate)
        ? dent_normalize_student_number((string) ($candidate['studentNumber'] ?? ''))
        : '';
    if ($targetStudentNumber === '') {
        $matches = [];
        foreach ((dent_load_user_store()['users'] ?? []) as $studentNumber => $user) {
            if (!is_array($user) || dent_user_cohort_key($user) !== dent_primary_cohort_key()) {
                continue;
            }
            if (dent_bot_normalize_identity_name((string) ($user['name'] ?? '')) === $normalizedName) {
                $matches[] = dent_normalize_student_number((string) $studentNumber);
            }
        }
        if (count($matches) === 1) {
            $targetStudentNumber = $matches[0];
        }
    }
    $claimRef = dent_bot_base64url_encode(random_bytes(9));
    dent_bot_store_with_lock(static function (array &$store) use ($identityHash, $platform, $platformUserId, $claimedName, $targetStudentNumber, $claimRef, $telegramProfile): array {
        $store['identityClaims'][$identityHash] = [
            'ref' => $claimRef,
            'identityHash' => $identityHash,
            'platform' => $platform,
            'platformUserIdEncrypted' => dent_encrypt_secret_text($platformUserId),
            'claimedNameEncrypted' => dent_encrypt_secret_text($claimedName),
            'platformProfileEncrypted' => dent_encrypt_secret_text((string) json_encode($telegramProfile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'studentNumber' => $targetStudentNumber,
            'status' => 'pending',
            'createdAt' => dent_iso_now(),
            'updatedAt' => dent_iso_now(),
        ];
        dent_bot_audit($store, 'identity-claim-created', $identityHash, $targetStudentNumber);
        return [];
    });
    return ['success' => true, 'status' => 'pending'];
}

function dent_bot_identity_claims(array $owner): array
{
    dent_bot_require_owner($owner);
    return dent_bot_store_read(static function (array $store): array {
        $items = [];
        foreach ($store['identityClaims'] as $claim) {
            if (!is_array($claim) || (string) ($claim['status'] ?? '') !== 'pending') {
                continue;
            }
            $studentNumber = dent_normalize_student_number((string) ($claim['studentNumber'] ?? ''));
            $user = $studentNumber !== '' ? dent_get_user_record($studentNumber) : null;
            $profileRaw = dent_decrypt_secret_text($claim['platformProfileEncrypted'] ?? null);
            $profile = json_decode($profileRaw, true);
            if (!is_array($profile)) {
                $profile = [];
            }
            $items[] = [
                'ref' => (string) ($claim['ref'] ?? ''),
                'candidateFound' => is_array($user),
                'name' => is_array($user) ? (string) ($user['name'] ?? '') : 'نیازمند تطبیق دستی',
                'claimedName' => dent_decrypt_secret_text($claim['claimedNameEncrypted'] ?? null),
                'studentNumber' => is_array($user) ? $studentNumber : '',
                'platform' => (string) ($claim['platform'] ?? ''),
                'platformUserId' => dent_decrypt_secret_text($claim['platformUserIdEncrypted'] ?? null),
                'telegramProfile' => $profile,
                'createdAt' => (string) ($claim['createdAt'] ?? ''),
            ];
        }
        return ['success' => true, 'claims' => array_slice($items, 0, 50)];
    });
}

function dent_bot_resolve_identity_claim(array $owner, array $payload): array
{
    dent_bot_require_owner($owner);
    $claimRef = trim((string) ($payload['claimRef'] ?? ''));
    $decision = (string) ($payload['decision'] ?? '');
    if (preg_match('/^[A-Za-z0-9_-]{12}$/', $claimRef) !== 1 || !in_array($decision, ['approve', 'reject'], true)) {
        dent_error('درخواست بررسی هویت نامعتبر است.', 422);
    }
    return dent_bot_store_with_lock(static function (array &$store) use ($claimRef, $decision): array {
        $identityHash = '';
        $claim = null;
        foreach ($store['identityClaims'] as $key => $candidate) {
            if (is_array($candidate) && (string) ($candidate['ref'] ?? '') === $claimRef) {
                $identityHash = (string) $key;
                $claim = $candidate;
                break;
            }
        }
        if (!is_array($claim) || (string) ($claim['status'] ?? '') !== 'pending') {
            dent_error('درخواست هویت پیدا نشد یا قبلاً بررسی شده است.', 404);
        }
        $studentNumber = dent_normalize_student_number((string) ($claim['studentNumber'] ?? ''));
        $user = $studentNumber !== '' ? dent_get_user_record($studentNumber) : null;
        if ($decision === 'approve' && !is_array($user)) {
            dent_error('برای این فرد هنوز حساب canonical سایت پیدا نشده است.', 409);
        }
        $store['identityClaims'][$identityHash]['status'] = $decision === 'approve' ? 'approved' : 'rejected';
        $store['identityClaims'][$identityHash]['updatedAt'] = dent_iso_now();
        if ($decision === 'approve') {
            $conflict = dent_bot_conflicting_link($store, $identityHash, (string) ($claim['platform'] ?? ''), $studentNumber);
            if (is_array($conflict)) {
                dent_error('این فرد یا حساب تلگرام قبلاً اتصال قطعی دیگری دارد؛ فقط مالک می‌تواند آن را از مدیریت اتصال‌ها تغییر دهد.', 409);
            }
            $store['links'][$identityHash] = [
                'identityHash' => $identityHash,
                'platform' => (string) ($claim['platform'] ?? ''),
                'platformUserIdEncrypted' => $claim['platformUserIdEncrypted'] ?? null,
                'studentNumber' => $studentNumber,
                'linkedAt' => dent_iso_now(),
                'source' => 'owner-approved-claim',
            ];
        }
        dent_bot_audit($store, $decision === 'approve' ? 'identity-claim-approved' : 'identity-claim-rejected', $identityHash, $studentNumber);
        return ['success' => true, 'status' => $store['identityClaims'][$identityHash]['status']];
    });
}

function dent_bot_identity_mappings(array $owner, string $platform): array
{
    dent_bot_require_owner($owner);
    $platform = strtolower(trim($platform));
    if (!in_array($platform, ['telegram', 'bale'], true)) {
        dent_error('پلتفرم اتصال نامعتبر است.', 422);
    }
    return dent_bot_store_read(static function (array $store) use ($platform): array {
        $items = [];
        foreach ($store['links'] as $identityHash => $link) {
            if (!is_array($link) || (string) ($link['platform'] ?? '') !== $platform) {
                continue;
            }
            $studentNumber = dent_normalize_student_number((string) ($link['studentNumber'] ?? ''));
            $user = $studentNumber !== '' ? dent_get_user_record($studentNumber) : null;
            $items[] = [
                'ref' => substr((string) $identityHash, 0, 16),
                'name' => is_array($user) ? (string) ($user['name'] ?? '') : 'حساب سایت حذف‌شده',
                'studentNumber' => $studentNumber,
                'platform' => $platform,
                'platformUserId' => dent_decrypt_secret_text($link['platformUserIdEncrypted'] ?? null),
                'linkedAt' => (string) ($link['linkedAt'] ?? ''),
                'source' => (string) ($link['source'] ?? 'secure-site-link'),
            ];
        }
        usort($items, static fn(array $left, array $right): int => strcmp((string) ($right['linkedAt'] ?? ''), (string) ($left['linkedAt'] ?? '')));
        return ['success' => true, 'mappings' => array_slice($items, 0, 200)];
    });
}

function dent_bot_set_identity_mapping(array $owner, string $platform, array $payload): array
{
    dent_bot_require_owner($owner);
    [$platform, $targetPlatformUserId] = dent_bot_identity($platform, (string) ($payload['targetPlatformUserId'] ?? ''));
    $studentNumber = dent_normalize_student_number((string) ($payload['studentNumber'] ?? ''));
    $reason = dent_clean_text((string) ($payload['reason'] ?? ''), 240);
    $user = $studentNumber !== '' ? dent_get_user_record($studentNumber) : null;
    $reasonLength = preg_match_all('/./u', $reason, $unusedReasonMatches);
    if (!is_array($user) || !is_int($reasonLength) || $reasonLength < 5) {
        dent_error('حساب سایت یا دلیل تغییر معتبر نیست.', 422);
    }
    $identityHash = dent_bot_identity_hash($platform, $targetPlatformUserId);
    return dent_bot_store_with_lock(static function (array &$store) use ($platform, $targetPlatformUserId, $studentNumber, $reason, $identityHash): array {
        foreach ($store['links'] as $key => $link) {
            if (!is_array($link)) {
                continue;
            }
            if ((string) $key === $identityHash
                || ((string) ($link['platform'] ?? '') === $platform && (string) ($link['studentNumber'] ?? '') === $studentNumber)) {
                unset($store['links'][$key]);
            }
        }
        $store['links'][$identityHash] = [
            'identityHash' => $identityHash,
            'platform' => $platform,
            'platformUserIdEncrypted' => dent_encrypt_secret_text($targetPlatformUserId),
            'studentNumber' => $studentNumber,
            'linkedAt' => dent_iso_now(),
            'source' => 'owner-manual',
        ];
        if (is_array($store['identityClaims'][$identityHash] ?? null)) {
            $store['identityClaims'][$identityHash]['status'] = 'owner-linked';
            $store['identityClaims'][$identityHash]['updatedAt'] = dent_iso_now();
        }
        dent_bot_audit($store, 'identity-mapping-owner-set', $identityHash, $studentNumber, $reason);
        return ['success' => true, 'status' => 'linked', 'mappingRef' => substr($identityHash, 0, 16)];
    });
}

function dent_bot_delete_identity_mapping(array $owner, string $platform, array $payload): array
{
    dent_bot_require_owner($owner);
    $mappingRef = trim((string) ($payload['mappingRef'] ?? ''));
    $reason = dent_clean_text((string) ($payload['reason'] ?? ''), 240);
    $reasonLength = preg_match_all('/./u', $reason, $unusedReasonMatches);
    if (preg_match('/^[a-f0-9]{16}$/', $mappingRef) !== 1 || !is_int($reasonLength) || $reasonLength < 5) {
        dent_error('درخواست حذف اتصال نامعتبر است.', 422);
    }
    return dent_bot_store_with_lock(static function (array &$store) use ($mappingRef, $reason, $platform): array {
        $matches = [];
        foreach ($store['links'] as $identityHash => $link) {
            if (is_array($link) && str_starts_with((string) $identityHash, $mappingRef)
                && (string) ($link['platform'] ?? '') === $platform) {
                $matches[(string) $identityHash] = $link;
            }
        }
        if (count($matches) !== 1) {
            dent_error('اتصال موردنظر پیدا نشد.', 404);
        }
        $identityHash = (string) array_key_first($matches);
        $link = $matches[$identityHash];
        $studentNumber = dent_normalize_student_number((string) ($link['studentNumber'] ?? ''));
        unset($store['links'][$identityHash]);
        dent_bot_audit($store, 'identity-mapping-owner-deleted', $identityHash, $studentNumber, $reason);
        return ['success' => true, 'status' => 'deleted'];
    });
}

function dent_bot_connection_profile(array $store, string $identityHash, array $link): array
{
    $encrypted = $link['platformProfileEncrypted'] ?? null;
    $decoded = $encrypted !== null && $encrypted !== ''
        ? json_decode(dent_decrypt_secret_text($encrypted), true)
        : null;
    $profile = dent_bot_telegram_profile([
        'telegramProfile' => is_array($decoded) ? $decoded : [],
    ]);
    if ((string) ($profile['displayName'] ?? '') !== '' || (string) ($profile['username'] ?? '') !== '') {
        return $profile;
    }
    $claim = $store['identityClaims'][$identityHash] ?? null;
    $claimEncrypted = is_array($claim) ? ($claim['platformProfileEncrypted'] ?? null) : null;
    $claimDecoded = $claimEncrypted !== null && $claimEncrypted !== ''
        ? json_decode(dent_decrypt_secret_text($claimEncrypted), true)
        : null;
    return dent_bot_telegram_profile([
        'telegramProfile' => is_array($claimDecoded) ? $claimDecoded : [],
    ]);
}

function dent_bot_account_connections(array $user): array
{
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($studentNumber === '') {
        dent_error('حساب سایت نامعتبر است.', 422);
    }
    $websiteName = dent_clean_text((string) ($user['name'] ?? ''), 120);
    return dent_bot_store_read(static function (array $store) use ($studentNumber, $websiteName): array {
        $connections = [];
        foreach (['telegram', 'bale'] as $platform) {
            $matches = [];
            foreach ($store['links'] as $identityHash => $link) {
                if (!is_array($link)
                    || (string) ($link['platform'] ?? '') !== $platform
                    || dent_normalize_student_number((string) ($link['studentNumber'] ?? '')) !== $studentNumber) {
                    continue;
                }
                $matches[(string) $identityHash] = $link;
            }
            if (count($matches) > 1) {
                dent_error('وضعیت اتصال این حساب نیازمند بررسی مالک است.', 409, ['code' => 'BOT_CONNECTION_CONFLICT']);
            }
            if (!$matches) {
                $connections[$platform] = [
                    'platform' => $platform,
                    'connected' => false,
                    'websiteName' => $websiteName,
                ];
                continue;
            }
            $identityHash = (string) array_key_first($matches);
            $link = $matches[$identityHash];
            $profile = dent_bot_connection_profile($store, $identityHash, $link);
            $connections[$platform] = [
                'platform' => $platform,
                'connected' => true,
                'websiteName' => $websiteName,
                'platformUserId' => dent_decrypt_secret_text($link['platformUserIdEncrypted'] ?? null),
                'platformDisplayName' => (string) ($profile['displayName'] ?? ''),
                'platformUsername' => (string) ($profile['username'] ?? ''),
                'linkedAt' => (string) ($link['linkedAt'] ?? ''),
                'source' => (string) ($link['source'] ?? 'secure-site-link'),
            ];
        }
        return [
            'success' => true,
            'contractVersion' => 'bot-account-connections-v1',
            'connections' => $connections,
        ];
    });
}

function dent_bot_disconnect_account(array $user, string $platform): array
{
    $platform = strtolower(trim($platform));
    if (!in_array($platform, ['telegram', 'bale'], true)) {
        dent_error('پیام‌رسان انتخاب‌شده معتبر نیست.', 422, ['code' => 'INVALID_BOT_PLATFORM']);
    }
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($studentNumber === '') {
        dent_error('حساب سایت نامعتبر است.', 422);
    }
    return dent_bot_store_with_lock(static function (array &$store) use ($studentNumber, $platform): array {
        dent_bot_cleanup_store($store, time());
        $matches = [];
        foreach ($store['links'] as $identityHash => $link) {
            if (is_array($link)
                && (string) ($link['platform'] ?? '') === $platform
                && dent_normalize_student_number((string) ($link['studentNumber'] ?? '')) === $studentNumber) {
                $matches[(string) $identityHash] = $link;
            }
        }
        if (count($matches) > 1) {
            dent_error('وضعیت اتصال این حساب نیازمند بررسی مالک است.', 409, ['code' => 'BOT_CONNECTION_CONFLICT']);
        }
        if (!$matches) {
            dent_error('این حساب به پیام‌رسان انتخاب‌شده متصل نیست.', 404, ['code' => 'BOT_CONNECTION_NOT_FOUND']);
        }

        $identityHash = (string) array_key_first($matches);
        $link = $matches[$identityHash];
        $platformUserId = dent_decrypt_secret_text($link['platformUserIdEncrypted'] ?? null);
        if (preg_match('/^[0-9]{1,24}$/', $platformUserId) !== 1) {
            dent_error('شناسه اتصال قابل بازیابی نیست؛ با مالک سامانه تماس بگیر.', 409, ['code' => 'BOT_CONNECTION_ID_UNAVAILABLE']);
        }

        $disconnectedAt = dent_iso_now();
        $deliveryId = 'bd-' . bin2hex(random_bytes(16));
        $store['accountDisconnectDeliveries'][$deliveryId] = [
            'deliveryId' => $deliveryId,
            'identityHash' => $identityHash,
            'platform' => $platform,
            'platformUserIdEncrypted' => $link['platformUserIdEncrypted'] ?? null,
            'status' => 'pending',
            'attempts' => 0,
            'leaseUntil' => 0,
            'createdAt' => $disconnectedAt,
            'lastAttemptAt' => '',
            'deliveredAt' => '',
            'reasonCode' => '',
        ];
        unset($store['links'][$identityHash]);
        if (is_array($store['identityClaims'][$identityHash] ?? null)) {
            $store['identityClaims'][$identityHash]['status'] = 'disconnected-from-site';
            $store['identityClaims'][$identityHash]['updatedAt'] = $disconnectedAt;
        }
        if (is_array($store['identityCandidates'][$identityHash] ?? null)) {
            $store['identityCandidates'][$identityHash]['status'] = 'disconnected-from-site';
            $store['identityCandidates'][$identityHash]['updatedAt'] = $disconnectedAt;
        }
        dent_bot_audit($store, 'account-disconnected-from-site', $identityHash, $studentNumber);
        return [
            'success' => true,
            'contractVersion' => 'bot-account-connections-v1',
            'platform' => $platform,
            'disconnectedAt' => $disconnectedAt,
            'deliveryQueued' => true,
        ];
    });
}

function dent_bot_claim_account_disconnect_deliveries(string $platform, array $payload): array
{
    [$platform] = dent_bot_identity($platform, (string) ($payload['platformUserId'] ?? ''));
    $limit = max(1, min(20, (int) ($payload['limit'] ?? 10)));
    $now = time();
    $leaseSeconds = 120;
    $maxAttempts = 8;
    return dent_bot_store_with_lock(static function (array &$store) use ($platform, $limit, $now, $leaseSeconds, $maxAttempts): array {
        dent_bot_cleanup_store($store, $now);
        $deliveries = [];
        foreach ($store['accountDisconnectDeliveries'] as $key => $delivery) {
            if (count($deliveries) >= $limit || !is_array($delivery) || (string) ($delivery['platform'] ?? '') !== $platform) {
                continue;
            }
            $status = (string) ($delivery['status'] ?? 'pending');
            $leaseUntil = (int) ($delivery['leaseUntil'] ?? 0);
            $attempts = max(0, (int) ($delivery['attempts'] ?? 0));
            if ($status === 'delivered' || $status === 'failed' || ($status === 'leased' && $leaseUntil > $now)) {
                continue;
            }
            if ($attempts >= $maxAttempts) {
                $delivery['status'] = 'failed';
                $delivery['leaseUntil'] = 0;
                $delivery['reasonCode'] = (string) (($delivery['reasonCode'] ?? '') ?: 'MAX_ATTEMPTS');
                $store['accountDisconnectDeliveries'][$key] = $delivery;
                continue;
            }
            $chatId = dent_decrypt_secret_text($delivery['platformUserIdEncrypted'] ?? null);
            if (preg_match('/^[0-9]{1,24}$/', $chatId) !== 1) {
                $delivery['status'] = 'failed';
                $delivery['reasonCode'] = 'INVALID_CHAT_ID';
                $store['accountDisconnectDeliveries'][$key] = $delivery;
                continue;
            }
            $delivery['status'] = 'leased';
            $delivery['attempts'] = $attempts + 1;
            $delivery['leaseUntil'] = $now + $leaseSeconds;
            $delivery['lastAttemptAt'] = dent_iso_now();
            $store['accountDisconnectDeliveries'][$key] = $delivery;
            $deliveries[] = [
                'deliveryId' => (string) ($delivery['deliveryId'] ?? $key),
                'platform' => $platform,
                'chatId' => $chatId,
                'disconnectedAt' => (string) ($delivery['createdAt'] ?? ''),
            ];
        }
        return [
            'success' => true,
            'contractVersion' => 'bot-account-connections-v1',
            'deliveries' => $deliveries,
        ];
    });
}

function dent_bot_ack_account_disconnect_delivery(string $platform, array $payload): array
{
    [$platform] = dent_bot_identity($platform, (string) ($payload['platformUserId'] ?? ''));
    $deliveryId = trim((string) ($payload['deliveryId'] ?? ''));
    $delivered = filter_var($payload['delivered'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $reasonCode = dent_clean_text((string) ($payload['reasonCode'] ?? ''), 60);
    if (preg_match('/^bd-[a-f0-9]{32}$/', $deliveryId) !== 1) {
        dent_error('شناسه تحویل نامعتبر است.', 422, ['code' => 'INVALID_DELIVERY_ID']);
    }
    $updated = dent_bot_store_with_lock(static function (array &$store) use ($platform, $deliveryId, $delivered, $reasonCode): array {
        $delivery = $store['accountDisconnectDeliveries'][$deliveryId] ?? null;
        if (!is_array($delivery) || (string) ($delivery['platform'] ?? '') !== $platform) {
            return ['found' => false];
        }
        $delivery['status'] = $delivered ? 'delivered' : 'pending';
        $delivery['leaseUntil'] = 0;
        $delivery['deliveredAt'] = $delivered ? dent_iso_now() : '';
        $delivery['reasonCode'] = $delivered ? '' : $reasonCode;
        $store['accountDisconnectDeliveries'][$deliveryId] = $delivery;
        return ['found' => true];
    });
    if (empty($updated['found'])) {
        dent_error('تحویل پیام قطع اتصال پیدا نشد.', 404, ['code' => 'DELIVERY_NOT_FOUND']);
    }
    return ['success' => true, 'deliveryId' => $deliveryId, 'delivered' => $delivered];
}

function dent_bot_queue_payment_success_deliveries(array $order): void
{
    if ((string) ($order['status'] ?? '') !== PAYMENTS_ORDER_STATUS_SUCCESS || !dent_bot_payment_is_offer_order($order)) {
        return;
    }
    $extra = dent_bot_payment_extra($order);
    $originPlatform = (string) ($extra['bot_origin_platform'] ?? '');
    $originIdentityHash = (string) ($extra['bot_origin_identity_hash'] ?? '');
    $orderId = max(0, (int) ($order['id'] ?? 0));
    if (!in_array($originPlatform, ['telegram', 'bale'], true) || preg_match('/^[a-f0-9]{64}$/', $originIdentityHash) !== 1 || $orderId <= 0) {
        return;
    }
    dent_bot_delivery_stores_ensure_migrated();
    $identityStore = dent_bot_identity_snapshot_optional() ?? dent_bot_store_default();
    dent_bot_payment_delivery_store_with_lock(static function (array &$store) use ($identityStore, $order, $originPlatform, $originIdentityHash, $orderId): array {
        return ['success' => true, 'created' => dent_bot_add_payment_success_deliveries(
            $store,
            $identityStore,
            $order,
            $originPlatform,
            $originIdentityHash,
            $orderId
        )];
    }, 'queue-payment-success');
}

function dent_bot_add_payment_success_deliveries(
    array &$store,
    array $identityStore,
    array $order,
    string $originPlatform,
    string $originIdentityHash,
    int $orderId
): int {
    $orderExtra = dent_bot_payment_extra($order);
    $originRoute = json_decode((string) ($orderExtra['bot_origin_route_encrypted_json'] ?? ''), true);
    if (!is_array($originRoute)) {
        $originRoute = dent_bot_route_encrypted_from_identity($identityStore, $originIdentityHash, $originPlatform);
    }
    $targets = [[
        'kind' => 'user',
        'platform' => $originPlatform,
        'identityHash' => $originIdentityHash,
        'platformUserIdEncrypted' => $originRoute,
    ]];
    foreach ($identityStore['links'] ?? [] as $identityHash => $link) {
        if (!is_array($link) || !dent_bot_link_auth_complete($link)) {
            continue;
        }
        $student = dent_normalize_student_number((string) ($link['studentNumber'] ?? ''));
        $linkedUser = $student !== '' ? dent_get_user_record($student) : null;
        if (!is_array($linkedUser) || (string) ($linkedUser['role'] ?? '') !== 'owner') {
            continue;
        }
        $targetPlatform = (string) ($link['platform'] ?? '');
        if (in_array($targetPlatform, ['telegram', 'bale'], true)) {
            $targets[] = [
                'kind' => 'owner',
                'platform' => $targetPlatform,
                'identityHash' => (string) $identityHash,
                'platformUserIdEncrypted' => $link['platformUserIdEncrypted'] ?? null,
            ];
        }
    }
    $created = 0;
    foreach ($targets as $target) {
        $dedupeKey = $orderId . '|' . $target['kind'] . '|' . $target['platform'] . '|' . $target['identityHash'];
        $deliveryId = 'prd-' . substr(hash_hmac('sha256', $dedupeKey, dent_auth_secret_key()), 0, 32);
        if (is_array($store['deliveries'][$deliveryId] ?? null)) {
            continue;
        }
        $store['deliveries'][$deliveryId] = [
            'deliveryId' => $deliveryId,
            'dedupeKey' => hash('sha256', $dedupeKey),
            'kind' => $target['kind'],
            'platform' => $target['platform'],
            'identityHash' => $target['identityHash'],
            'platformUserIdEncrypted' => $target['platformUserIdEncrypted'] ?? null,
            'orderId' => $orderId,
            'order' => dent_bot_payment_order_payload($order, $target['kind'] === 'owner'),
            'status' => 'pending', 'attempts' => 0, 'leaseUntil' => 0,
            'createdAt' => dent_iso_now(), 'lastAttemptAt' => '', 'deliveredAt' => '', 'reasonCode' => '',
        ];
        $created++;
    }
    return $created;
}

function dent_bot_backfill_payment_success_deliveries(array &$store, array $identityStore, array $orders): array
{
    $poll = is_array($store['poll'] ?? null) ? $store['poll'] : [];
    $highWatermark = max(0, (int) ($poll['highWatermark'] ?? 0));
    $unresolved = is_array($poll['unresolvedOrderIds'] ?? null) ? $poll['unresolvedOrderIds'] : [];
    usort($orders, static fn(array $left, array $right): int => ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0)));
    $scanned = 0;
    $created = 0;
    $nextHighWatermark = $highWatermark;
    foreach ($orders as $order) {
        if (!is_array($order) || !dent_bot_payment_is_offer_order($order)) {
            continue;
        }
        $orderId = max(0, (int) ($order['id'] ?? 0));
        if ($orderId <= 0 || ($orderId <= $highWatermark && !array_key_exists((string) $orderId, $unresolved))) {
            continue;
        }
        $scanned++;
        $nextHighWatermark = max($nextHighWatermark, $orderId);
        $status = (string) ($order['status'] ?? PAYMENTS_ORDER_STATUS_PENDING);
        if ($status === PAYMENTS_ORDER_STATUS_SUCCESS) {
            $extra = dent_bot_payment_extra($order);
            $originPlatform = (string) ($extra['bot_origin_platform'] ?? '');
            $originIdentityHash = (string) ($extra['bot_origin_identity_hash'] ?? '');
            if (in_array($originPlatform, ['telegram', 'bale'], true) && preg_match('/^[a-f0-9]{64}$/', $originIdentityHash) === 1) {
                $created += dent_bot_add_payment_success_deliveries($store, $identityStore, $order, $originPlatform, $originIdentityHash, $orderId);
            }
            unset($unresolved[(string) $orderId]);
        } elseif ($status === PAYMENTS_ORDER_STATUS_PENDING) {
            $unresolved[(string) $orderId] = (string) ($order['updated_at'] ?? '');
        } else {
            unset($unresolved[(string) $orderId]);
        }
    }
    $store['poll'] = [
        'highWatermark' => $nextHighWatermark,
        'unresolvedOrderIds' => $unresolved,
    ];
    return ['scanned' => $scanned, 'created' => $created, 'highWatermark' => $nextHighWatermark];
}

function dent_bot_claim_payment_result_deliveries(string $platform, array $payload): array
{
    [$platform] = dent_bot_identity($platform, (string) ($payload['platformUserId'] ?? ''));
    if ((string) ($payload['contractVersion'] ?? '') !== 'bot-payment-return-v1') {
        dent_error('نسخه قرارداد نتیجه پرداخت معتبر نیست.', 409, ['code' => 'PAYMENT_RETURN_CONTRACT_REQUIRED']);
    }
    $limit = max(1, min(20, (int) ($payload['limit'] ?? 10)));
    $now = time();
    $orders = dent_bot_payment_orders();
    dent_bot_delivery_stores_ensure_migrated();
    $identityStore = dent_bot_identity_snapshot_optional() ?? dent_bot_store_default();
    return dent_bot_payment_delivery_store_with_lock(static function (array &$store) use ($identityStore, $platform, $limit, $now, $orders): array {
        dent_bot_cleanup_payment_delivery_store($store, $now);
        $poll = dent_bot_backfill_payment_success_deliveries($store, $identityStore, $orders);
        $deliveries = [];
        foreach ($store['deliveries'] as $key => $delivery) {
            if (count($deliveries) >= $limit || !is_array($delivery) || (string) ($delivery['platform'] ?? '') !== $platform) {
                continue;
            }
            $status = (string) ($delivery['status'] ?? 'pending');
            $attempts = max(0, (int) ($delivery['attempts'] ?? 0));
            if ($status === 'delivered' || $status === 'failed' || ($status === 'leased' && (int) ($delivery['leaseUntil'] ?? 0) > $now)) {
                continue;
            }
            if ($attempts >= 8) {
                $delivery['status'] = 'failed';
                $delivery['reasonCode'] = 'MAX_ATTEMPTS';
                $store['deliveries'][$key] = $delivery;
                continue;
            }
            $identityHash = (string) ($delivery['identityHash'] ?? '');
            $chatId = dent_decrypt_secret_text($delivery['platformUserIdEncrypted'] ?? null);
            $link = $identityStore['links'][$identityHash] ?? null;
            if ($chatId === '' && is_array($link)
                && (string) ($link['platform'] ?? '') === $platform
                && dent_bot_link_auth_complete($link)) {
                $chatId = dent_decrypt_secret_text($link['platformUserIdEncrypted'] ?? null);
            } elseif ($chatId === '' && (string) ($delivery['kind'] ?? '') === 'user') {
                $route = is_array($identityStore['onboardingIdentityRoutes'][$identityHash] ?? null)
                    ? $identityStore['onboardingIdentityRoutes'][$identityHash]
                    : null;
                $profileRef = (string) ($identityStore['onboardingIdentityProfiles'][$identityHash] ?? '');
                $profileRecord = $profileRef !== '' && is_array($identityStore['onboardingProfiles'][$profileRef] ?? null)
                    ? $identityStore['onboardingProfiles'][$profileRef]
                    : null;
                $profilePlain = is_array($profileRecord)
                    ? dent_decrypt_secret_text($profileRecord['profileEncrypted'] ?? null)
                    : '';
                $profile = $profilePlain !== '' ? json_decode($profilePlain, true) : null;
                $profilePhone = is_array($profile)
                    ? dent_normalize_phone_number((string) ($profile['phoneNumber'] ?? ''))
                    : '';
                $profilePhoneHash = $profilePhone !== ''
                    ? hash_hmac('sha256', 'bot-onboarding-phone:' . $profilePhone, dent_auth_secret_key())
                    : '';
                if (is_array($route)
                    && (string) ($route['platform'] ?? '') === $platform
                    && $profileRef !== ''
                    && hash_equals($profileRef, (string) ($route['profileRef'] ?? ''))
                    && is_array($profile)
                    && empty($profile['isClassMember'])
                    && trim((string) ($profile['verifiedAt'] ?? '')) !== ''
                    && $profilePhoneHash !== ''
                    && hash_equals($profilePhoneHash, (string) ($profileRecord['phoneHash'] ?? ''))) {
                    $chatId = dent_decrypt_secret_text($route['platformUserIdEncrypted'] ?? null);
                }
            }
            if (preg_match('/^[0-9]{1,24}$/', $chatId) !== 1) {
                continue;
            }
            if (!isset($delivery['platformUserIdEncrypted'])) {
                $delivery['platformUserIdEncrypted'] = dent_encrypt_secret_text($chatId);
            }
            $delivery['status'] = 'leased';
            $delivery['attempts'] = $attempts + 1;
            $delivery['leaseUntil'] = $now + 120;
            $delivery['lastAttemptAt'] = dent_iso_now();
            $store['deliveries'][$key] = $delivery;
            $deliveries[] = [
                'deliveryId' => (string) ($delivery['deliveryId'] ?? $key),
                'deliveryKind' => (string) ($delivery['kind'] ?? 'user'),
                'platform' => $platform,
                'chatId' => $chatId,
                'order' => is_array($delivery['order'] ?? null) ? $delivery['order'] : [],
            ];
        }
        return ['success' => true, 'contractVersion' => 'bot-payment-return-v1', 'deliveries' => $deliveries, 'poll' => $poll];
    }, 'claim-payment-results');
}

function dent_bot_ack_payment_result_delivery(string $platform, array $payload): array
{
    [$platform] = dent_bot_identity($platform, (string) ($payload['platformUserId'] ?? ''));
    if ((string) ($payload['contractVersion'] ?? '') !== 'bot-payment-return-v1') {
        dent_error('نسخه قرارداد نتیجه پرداخت معتبر نیست.', 409, ['code' => 'PAYMENT_RETURN_CONTRACT_REQUIRED']);
    }
    $deliveryId = trim((string) ($payload['deliveryId'] ?? ''));
    $delivered = filter_var($payload['delivered'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $reason = dent_clean_text((string) ($payload['reasonCode'] ?? ''), 60);
    if (preg_match('/^prd-[a-f0-9]{32}$/', $deliveryId) !== 1) {
        dent_error('شناسه تحویل نامعتبر است.', 422, ['code' => 'INVALID_DELIVERY_ID']);
    }
    $batch = dent_bot_ack_payment_result_deliveries($platform, [
        'platformUserId' => (string) ($payload['platformUserId'] ?? ''),
        'contractVersion' => 'bot-payment-return-v1',
        'results' => [[
            'deliveryId' => $deliveryId,
            'delivered' => $delivered,
            'reasonCode' => $reason,
        ]],
    ]);
    if (empty($batch['results'][0]['found'])) {
        dent_error('تحویل نتیجه پرداخت پیدا نشد.', 404, ['code' => 'DELIVERY_NOT_FOUND']);
    }
    return ['success' => true, 'deliveryId' => $deliveryId, 'delivered' => $delivered];
}

function dent_bot_ack_payment_result_deliveries(string $platform, array $payload): array
{
    [$platform] = dent_bot_identity($platform, (string) ($payload['platformUserId'] ?? ''));
    if ((string) ($payload['contractVersion'] ?? '') !== 'bot-payment-return-v1') {
        dent_error('نسخه قرارداد نتیجه پرداخت معتبر نیست.', 409, ['code' => 'PAYMENT_RETURN_CONTRACT_REQUIRED']);
    }
    $items = is_array($payload['results'] ?? null) ? array_values($payload['results']) : [];
    if ($items === [] || count($items) > 20) {
        dent_error('فهرست تایید تحویل نامعتبر است.', 422, ['code' => 'INVALID_DELIVERY_ACK_BATCH']);
    }
    $normalized = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            dent_error('نتیجه تحویل نامعتبر است.', 422, ['code' => 'INVALID_DELIVERY_ACK']);
        }
        $deliveryId = trim((string) ($item['deliveryId'] ?? ''));
        if (preg_match('/^prd-[a-f0-9]{32}$/', $deliveryId) !== 1 || isset($normalized[$deliveryId])) {
            dent_error('شناسه تحویل نامعتبر است.', 422, ['code' => 'INVALID_DELIVERY_ID']);
        }
        $normalized[$deliveryId] = [
            'deliveryId' => $deliveryId,
            'delivered' => filter_var($item['delivered'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'reasonCode' => dent_clean_text((string) ($item['reasonCode'] ?? ''), 60),
        ];
    }
    dent_bot_delivery_stores_ensure_migrated();
    $result = dent_bot_payment_delivery_store_with_lock(static function (array &$store) use ($platform, $normalized): array {
        $results = [];
        foreach ($normalized as $deliveryId => $item) {
            $delivery = $store['deliveries'][$deliveryId] ?? null;
            $found = is_array($delivery)
                && (string) ($delivery['platform'] ?? '') === $platform
                && (string) ($delivery['status'] ?? '') === 'leased';
            if ($found) {
                $delivered = (bool) $item['delivered'];
                $delivery['status'] = $delivered ? 'delivered' : 'pending';
                $delivery['leaseUntil'] = 0;
                $delivery['deliveredAt'] = $delivered ? dent_iso_now() : '';
                $delivery['reasonCode'] = $delivered ? '' : (string) $item['reasonCode'];
                $store['deliveries'][$deliveryId] = $delivery;
            }
            $results[] = ['deliveryId' => $deliveryId, 'found' => $found, 'delivered' => (bool) $item['delivered']];
        }
        return ['results' => $results];
    }, 'ack-payment-results-batch');
    return [
        'success' => true,
        'contractVersion' => 'bot-payment-return-v1',
        'results' => is_array($result['results'] ?? null) ? $result['results'] : [],
    ];
}

function dent_bot_link_for_identity(string $platform, string $platformUserId): ?array
{
    $identityHash = dent_bot_identity_hash($platform, $platformUserId);
    $result = dent_bot_store_read(static function (array $store) use ($identityHash): array {
        $link = $store['links'][$identityHash] ?? null;
        return ['link' => is_array($link) ? $link : null];
    });
    return is_array($result['link'] ?? null) ? $result['link'] : null;
}

function dent_bot_linked_user(string $platform, string $platformUserId): ?array
{
    $link = dent_bot_link_for_identity($platform, $platformUserId);
    if (!is_array($link)) {
        return null;
    }
    $studentNumber = dent_normalize_student_number((string) ($link['studentNumber'] ?? ''));
    $user = $studentNumber !== '' ? dent_get_user_record($studentNumber) : null;
    return is_array($user) ? $user : null;
}

/**
 * Resolve the narrowly scoped payer identity created by generic Contact/OTP
 * onboarding. This is intentionally not a website account and must only be
 * used by the explicit bot-commerce actions in the service dispatcher.
 */
function dent_bot_verified_onboarding_payment_user(string $platform, string $platformUserId): ?array
{
    [$platform, $platformUserId] = dent_bot_identity($platform, $platformUserId);
    $identityHash = dent_bot_identity_hash($platform, $platformUserId);
    $result = dent_bot_store_with_lock(static function (array &$store) use ($platform, $platformUserId, $identityHash): array {
        $profileRef = (string) ($store['onboardingIdentityProfiles'][$identityHash] ?? '');
        $record = is_array($store['onboardingProfiles'][$profileRef] ?? null)
            ? $store['onboardingProfiles'][$profileRef]
            : null;
        $plain = is_array($record) ? dent_decrypt_secret_text($record['profileEncrypted'] ?? null) : '';
        $profile = $plain !== '' ? json_decode($plain, true) : null;
        if (!is_array($profile)
            || !empty($profile['isClassMember'])
            || trim((string) ($profile['verifiedAt'] ?? '')) === '') {
            return ['user' => null];
        }

        $phone = dent_normalize_phone_number((string) ($profile['phoneNumber'] ?? ''));
        $expectedPhoneHash = $phone !== ''
            ? hash_hmac('sha256', 'bot-onboarding-phone:' . $phone, dent_auth_secret_key())
            : '';
        $firstName = dent_clean_text((string) ($profile['firstName'] ?? ''), 80);
        $lastName = dent_clean_text((string) ($profile['lastName'] ?? ''), 100);
        $payerKey = dent_bot_payment_profile_payer_key($phone);
        if ($phone === '' || $payerKey === '' || $firstName === '' || $lastName === ''
            || $expectedPhoneHash === ''
            || !hash_equals($expectedPhoneHash, (string) ($record['phoneHash'] ?? ''))) {
            return ['user' => null];
        }

        // A verified generic user has no permanent website link. Keep only an
        // encrypted same-platform return route so a verified payment result can
        // reach the bot where checkout began.
        $existingRoute = is_array($store['onboardingIdentityRoutes'][$identityHash] ?? null)
            ? $store['onboardingIdentityRoutes'][$identityHash]
            : null;
        $routeMatches = is_array($existingRoute)
            && (string) ($existingRoute['platform'] ?? '') === $platform
            && (string) ($existingRoute['profileRef'] ?? '') === $profileRef
            && hash_equals($platformUserId, dent_decrypt_secret_text($existingRoute['platformUserIdEncrypted'] ?? null));
        if (!$routeMatches) {
            $store['onboardingIdentityRoutes'][$identityHash] = [
                'platform' => $platform,
                'profileRef' => $profileRef,
                'platformUserIdEncrypted' => dent_encrypt_secret_text($platformUserId),
                'updatedAt' => dent_iso_now(),
            ];
        }
        return ['user' => [
            'name' => trim($firstName . ' ' . $lastName),
            'studentNumber' => dent_normalize_student_number((string) ($profile['studentNumber'] ?? '')),
            'phoneNumber' => $phone,
            'phoneVerifiedAt' => (string) ($profile['verifiedAt'] ?? ''),
            'role' => 'student',
            'cohortKey' => '',
            'botPaymentPayerKey' => $payerKey,
        ]];
    }, 'ensure-onboarding-payment-route');
    return is_array($result['user'] ?? null) ? $result['user'] : null;
}

function dent_bot_canonical_auth_version(): string
{
    return 'bot-canonical-auth-v1';
}

function dent_bot_link_auth_complete(?array $link): bool
{
    return is_array($link)
        && hash_equals(dent_bot_canonical_auth_version(), (string) ($link['authVersion'] ?? ''))
        && trim((string) ($link['authCompletedAt'] ?? '')) !== ''
        && in_array((string) ($link['authMethod'] ?? ''), ['class-site-otp', 'secure-site-login'], true);
}

function dent_bot_site_origin(): string
{
    $origin = rtrim(trim((string) (getenv('DENT_SITE_PUBLIC_URL') ?: 'https://dentistry1402tums.ir')), '/');
    if (filter_var($origin, FILTER_VALIDATE_URL) === false || parse_url($origin, PHP_URL_SCHEME) !== 'https') {
        dent_error('نشانی عمومی سایت نامعتبر است.', 500);
    }
    return $origin;
}

function dent_bot_start_link(string $platform, string $platformUserId, array $payload = []): array
{
    [$platform, $platformUserId] = dent_bot_identity($platform, $platformUserId);
    $identityHash = dent_bot_identity_hash($platform, $platformUserId);
    $existingLink = dent_bot_link_for_identity($platform, $platformUserId);
    $existing = dent_bot_linked_user($platform, $platformUserId);
    if (is_array($existing) && dent_bot_link_auth_complete($existingLink)) {
        return [
            'success' => true,
            'alreadyLinked' => true,
            'authComplete' => true,
            'authVersion' => dent_bot_canonical_auth_version(),
            'user' => dent_bot_public_user($existing),
        ];
    }

    $requestedAuthVersion = trim((string) ($payload['authVersion'] ?? ''));
    if ($requestedAuthVersion !== dent_bot_canonical_auth_version()) {
        dent_error('نسخه احراز هویت امن ربات معتبر نیست.', 409, ['code' => 'BOT_AUTH_CONTRACT_MISMATCH']);
    }

    $token = dent_bot_base64url_encode(random_bytes(32));
    $tokenHash = dent_bot_token_hash($token);
    $expiresAt = time() + 600;
    $platformProfile = dent_bot_telegram_profile($payload);
    dent_bot_store_with_lock(static function (array &$store) use ($tokenHash, $identityHash, $platform, $platformUserId, $expiresAt, $platformProfile, $requestedAuthVersion): array {
        dent_bot_cleanup_store($store, time());
        $store['challenges'][$tokenHash] = [
            'identityHash' => $identityHash,
            'platform' => $platform,
            'platformUserIdEncrypted' => dent_encrypt_secret_text($platformUserId),
            'platformProfileEncrypted' => dent_encrypt_secret_text((string) json_encode($platformProfile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'createdAt' => time(),
            'expiresAt' => $expiresAt,
            'usedAt' => 0,
            'authVersion' => $requestedAuthVersion,
        ];
        dent_bot_audit($store, 'link-challenge-created', $identityHash);
        return [];
    });

    return [
        'success' => true,
        'alreadyLinked' => false,
        'linkUrl' => dent_bot_site_origin() . '/account/bot-link/?token=' . rawurlencode($token),
        'expiresAt' => gmdate('c', $expiresAt),
    ];
}

function dent_bot_link_challenge_info(string $token): array
{
    if (preg_match('/^[A-Za-z0-9_-]{43}$/', $token) !== 1) {
        dent_error('لینک اتصال نامعتبر یا منقضی است.', 404);
    }
    $tokenHash = dent_bot_token_hash($token);
    $result = dent_bot_store_with_lock(static function (array &$store) use ($tokenHash): array {
        dent_bot_cleanup_store($store, time());
        $challenge = $store['challenges'][$tokenHash] ?? null;
        if (!is_array($challenge) || (int) ($challenge['usedAt'] ?? 0) > 0 || (int) ($challenge['expiresAt'] ?? 0) < time()) {
            return ['challenge' => null];
        }
        return ['challenge' => $challenge];
    });
    $challenge = $result['challenge'] ?? null;
    if (!is_array($challenge)) {
        dent_error('لینک اتصال نامعتبر یا منقضی است.', 404);
    }
    return [
        'success' => true,
        'platform' => (string) ($challenge['platform'] ?? ''),
        'expiresAt' => gmdate('c', (int) ($challenge['expiresAt'] ?? 0)),
    ];
}

function dent_bot_confirm_link(string $token, array $user): array
{
    if (preg_match('/^[A-Za-z0-9_-]{43}$/', $token) !== 1) {
        dent_error('لینک اتصال نامعتبر یا منقضی است.', 404);
    }
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($studentNumber === '') {
        dent_error('حساب سایت نامعتبر است.', 422);
    }
    $tokenHash = dent_bot_token_hash($token);
    return dent_bot_store_with_lock(static function (array &$store) use ($tokenHash, $studentNumber, $user): array {
        $now = time();
        dent_bot_cleanup_store($store, $now);
        $challenge = $store['challenges'][$tokenHash] ?? null;
        if (!is_array($challenge) || (int) ($challenge['usedAt'] ?? 0) > 0 || (int) ($challenge['expiresAt'] ?? 0) < $now) {
            dent_error('لینک اتصال نامعتبر یا منقضی است.', 404);
        }
        if (!hash_equals(dent_bot_canonical_auth_version(), (string) ($challenge['authVersion'] ?? ''))) {
            dent_error('نسخه احراز هویت این لینک معتبر نیست؛ از ربات لینک تازه بگیر.', 409, ['code' => 'BOT_AUTH_CONTRACT_MISMATCH']);
        }
        $identityHash = (string) ($challenge['identityHash'] ?? '');
        $existing = $store['links'][$identityHash] ?? null;
        if (is_array($existing) && (string) ($existing['studentNumber'] ?? '') !== $studentNumber) {
            dent_error('این حساب ربات قبلاً به حساب دیگری متصل شده است.', 409);
        }

        $platform = (string) ($challenge['platform'] ?? '');
        $conflict = dent_bot_conflicting_link($store, $identityHash, $platform, $studentNumber);
        if (is_array($conflict)) {
            dent_error('این فرد یا حساب ربات قبلاً اتصال قطعی دیگری دارد؛ تغییر فقط توسط مالک ممکن است.', 409);
        }
        $store['links'][$identityHash] = [
            'identityHash' => $identityHash,
            'platform' => $platform,
            'platformUserIdEncrypted' => $challenge['platformUserIdEncrypted'] ?? null,
            'platformProfileEncrypted' => $challenge['platformProfileEncrypted'] ?? null,
            'studentNumber' => $studentNumber,
            'linkedAt' => dent_iso_now(),
            'source' => 'secure-site-link',
            'authVersion' => dent_bot_canonical_auth_version(),
            'authMethod' => 'secure-site-login',
            'authCompletedAt' => dent_iso_now(),
        ];
        $store['challenges'][$tokenHash]['usedAt'] = $now;
        if (is_array($store['identityClaims'][$identityHash] ?? null)) {
            $store['identityClaims'][$identityHash]['status'] = 'linked-secure-site';
            $store['identityClaims'][$identityHash]['updatedAt'] = dent_iso_now();
        }
        if (is_array($store['identityCandidates'][$identityHash] ?? null)) {
            $store['identityCandidates'][$identityHash]['status'] = 'linked-secure-site';
            $store['identityCandidates'][$identityHash]['updatedAt'] = dent_iso_now();
        }
        dent_bot_audit($store, 'account-linked', $identityHash, $studentNumber);
        return [
            'success' => true,
            'platform' => $platform,
            'authComplete' => true,
            'authVersion' => dent_bot_canonical_auth_version(),
            'user' => dent_bot_public_user($user),
        ];
    });
}

function dent_bot_service_dispatch(array $payload): array
{
    // Identity auth v2 routes are dispatched here so both bot adapters share one authorization boundary.
    $action = trim((string) ($payload['action'] ?? ''));
    $platform = (string) ($payload['platform'] ?? '');
    $platformUserId = (string) ($payload['platformUserId'] ?? '');

    // The voice bot owns its users, orders, wallet and ledger independently.
    // These two stateless gateway operations rely only on the verified service
    // HMAC and are deliberately dispatched before website account lookup.
    if ($action === 'voicePaymentStartV1') {
        return dent_voice_payment_start($payload);
    }
    if ($action === 'voicePaymentVerifyV1') {
        return dent_voice_payment_verify($payload);
    }

    if ($action === 'startLink') {
        return dent_bot_start_link($platform, $platformUserId, $payload);
    }
    if ($action === 'submitIdentityClaim') {
        dent_error('تأیید دستی هویت غیرفعال شده است؛ از OTP یا ورود امن سایت استفاده کن.', 410, ['code' => 'MANUAL_IDENTITY_DISABLED']);
    }
    if ($action === 'onboardingCatalogV1') {
        dent_bot_onboarding_require_contract($payload);
        return dent_bot_onboarding_catalog();
    }
    if ($action === 'onboardingStatusV1') {
        dent_bot_onboarding_require_contract($payload);
        return dent_bot_onboarding_status($platform, $platformUserId);
    }
    if ($action === 'requestOnboardingOtpV1') {
        dent_bot_onboarding_require_contract($payload);
        return dent_bot_onboarding_request_otp($platform, $platformUserId, $payload);
    }
    if ($action === 'resendOnboardingOtpV1') {
        dent_bot_onboarding_require_contract($payload);
        return dent_bot_onboarding_resend_otp($platform, $platformUserId, $payload);
    }
    if ($action === 'verifyOnboardingOtpV1') {
        dent_bot_onboarding_require_contract($payload);
        return dent_bot_onboarding_verify_otp($platform, $platformUserId, $payload);
    }
    if ($action === 'classAuthOtpStartV1') {
        dent_bot_onboarding_require_contract($payload);
        return dent_bot_class_auth_otp_start($platform, $platformUserId, $payload);
    }
    if ($action === 'classAuthOtpVerifyV1') {
        dent_bot_onboarding_require_contract($payload);
        return dent_bot_class_auth_otp_verify($platform, $platformUserId, $payload);
    }
    // These durable worker actions are authorized by the verified service HMAC.
    // They intentionally do not depend on the owner's own chat link, because a
    // website-initiated disconnect must still be able to notify that old chat.
    if ($action === 'claimAccountDisconnectDeliveriesV1') {
        return dent_bot_claim_account_disconnect_deliveries($platform, $payload);
    }
    if ($action === 'ackAccountDisconnectDeliveryV1') {
        return dent_bot_ack_account_disconnect_delivery($platform, $payload);
    }
    // Queue workers authenticate as services through the request HMAC. Their
    // ability to claim/ack must not disappear when an owner link is deleted or
    // when end-user identity data is temporarily unavailable.
    if ($action === 'claimPaymentResultDeliveriesV1') {
        return dent_bot_claim_payment_result_deliveries($platform, $payload);
    }
    if ($action === 'ackPaymentResultDeliveryV1') {
        return dent_bot_ack_payment_result_delivery($platform, $payload);
    }
    if ($action === 'ackPaymentResultDeliveriesV1') {
        return dent_bot_ack_payment_result_deliveries($platform, $payload);
    }
    if ($action === 'claimNotificationDeliveries') {
        return dent_bot_claim_notification_deliveries($platform, $payload);
    }
    if ($action === 'ackNotificationDelivery') {
        return dent_bot_ack_notification_delivery($platform, $payload);
    }
    $link = dent_bot_link_for_identity($platform, $platformUserId);
    $user = dent_bot_linked_user($platform, $platformUserId);
    $authComplete = dent_bot_link_auth_complete($link);
    if ($action === 'account') {
        if (is_array($user)) {
            dent_bot_ensure_linked_profile($platform, $platformUserId, $user);
        }
        $onboarding = dent_bot_onboarding_status($platform, $platformUserId);
        return [
            'success' => true,
            'linked' => is_array($user),
            'authComplete' => $authComplete,
            'authVersion' => $authComplete ? dent_bot_canonical_auth_version() : '',
            'authMethod' => $authComplete ? (string) ($link['authMethod'] ?? '') : '',
            'authCompletedAt' => $authComplete ? (string) ($link['authCompletedAt'] ?? '') : '',
            'user' => is_array($user) ? dent_bot_public_user($user) : null,
            'identity' => is_array($user) ? ['recognized' => true, 'claimStatus' => 'approved'] : dent_bot_public_identity_state($platform, $platformUserId),
            'onboardingProfile' => $onboarding['profile'] ?? null,
        ];
    }
    $genericPaymentActions = ['createBotPayment', 'paymentStatus', 'paymentProductStatesV2'];
    if (!is_array($user) && in_array($action, $genericPaymentActions, true)) {
        $user = dent_bot_verified_onboarding_payment_user($platform, $platformUserId);
        // This flag is local to the narrow commerce dispatch below. It does not
        // create a canonical site link or unlock any other service action.
        $authComplete = is_array($user);
    }
    if (!is_array($user)) {
        dent_error('اتصال حساب لازم است.', 403, ['code' => 'ACCOUNT_LINK_REQUIRED']);
    }
    // Deployment lifecycle delivery is a signed system operation. It remains
    // linked-owner-only inside dent_bot_create_deploy_notification(), but must
    // not disappear while that owner is completing the new interactive auth.
    if (!$authComplete && $action !== 'createDeployNotification') {
        dent_error('احراز هویت امن این اتصال هنوز کامل نشده است.', 403, ['code' => 'ACCOUNT_AUTH_REQUIRED']);
    }
    if ($action === 'requestProfileEditV1') {
        dent_bot_onboarding_require_contract($payload);
        return dent_bot_request_profile_edit($user, $platform, $platformUserId, $payload);
    }
    if ($action === 'bookletWatermarkIdentityV1') {
        return dent_bot_booklet_watermark_identity($user, $payload, $platform, $platformUserId);
    }
    if ($action === 'profileEditRequestsV1') {
        dent_bot_onboarding_require_contract($payload);
        return dent_bot_profile_edit_requests($user);
    }
    if ($action === 'resolveProfileEditV1') {
        dent_bot_onboarding_require_contract($payload);
        return dent_bot_resolve_profile_edit($user, $payload);
    }
    if ($action === 'normalizeIdentityAuthV2') {
        return dent_bot_normalize_identity_auth_v2($user);
    }
    if ($action === 'identityAuthV2Status') {
        return dent_bot_identity_auth_v2_status($user, $platform);
    }
    if ($action === 'grades') {
        dent_grades_set_active_cohort(dent_user_cohort_key($user));
        return dent_build_grades_payload($user);
    }
    if ($action === 'createBotPayment') {
        return dent_bot_create_offer_payment($user, $platform, $platformUserId, $payload);
    }
    if ($action === 'paymentStatus') {
        return dent_bot_payment_status($user, $platform, $platformUserId, $payload);
    }
    if ($action === 'paymentProductStatesV2') {
        return dent_bot_payment_product_states($user, $payload);
    }
    if ($action === 'paymentOwnerDashboardV2') {
        return dent_bot_payment_owner_dashboard($user, $payload);
    }
    if ($action === 'paymentProductReportV2') {
        return dent_bot_payment_product_report($user, $payload);
    }
    if ($action === 'paymentTransactionsV2') {
        return dent_bot_payment_transactions($user, $payload);
    }
    if ($action === 'paymentTransactionV2') {
        return dent_bot_payment_transaction($user, $payload);
    }
    if ($action === 'paymentUpdateTransactionStatusV2') {
        return dent_bot_payment_update_transaction_status($user, $payload);
    }
    if ($action === 'paymentDirectoryV2') {
        return dent_bot_payment_directory($user, $platform, $payload);
    }
    if ($action === 'studentAssistantSummaryV1') {
        return dent_bot_student_assistant_summary_v1($user, $platform, $platformUserId, $payload);
    }
    if ($action === 'performIntegrationActionV1') {
        return dent_bot_perform_integration_action_v1($user, $platform, $platformUserId, $payload);
    }
    if ($action === 'integrationChallengeAnswerV1') {
        return dent_bot_integration_challenge_answer_v1($user, $platform, $platformUserId, $payload);
    }
    if ($action === 'notifications') {
        return dent_bot_notification_feed($user, $payload);
    }
    if ($action === 'academicTerm7StatusV1') {
        return dent_bot_term7_status($user);
    }
    if ($action === 'createDeployNotification') {
        return dent_bot_create_deploy_notification($user, $payload);
    }
    if ($action === 'markNotificationRead') {
        return dent_bot_mark_notification_read($user, $payload);
    }
    if ($action === 'performNotificationAction') {
        return dent_bot_perform_notification_action($user, $platform, $payload);
    }
    if ($action === 'notificationAudience') {
        return dent_bot_notification_audience($user, $payload);
    }
    if ($action === 'navidDailyStart') {
        return dent_bot_navid_daily_start($user, $platform, $payload);
    }
    if ($action === 'navidStatus') {
        return dent_bot_navid_status($user);
    }
    if ($action === 'navidDailyComplete') {
        return dent_bot_navid_daily_complete($user, $platform, $payload);
    }
    if ($action === 'importIdentityCandidates') {
        dent_error('ورود نامزد و تأیید دستی هویت بازنشسته شده است؛ اتصال فقط با OTP یا ورود امن سایت انجام می‌شود.', 410, ['code' => 'MANUAL_IDENTITY_DISABLED']);
    }
    if ($action === 'identityClaims') {
        dent_error('صف تأیید دستی هویت بازنشسته شده است.', 410, ['code' => 'MANUAL_IDENTITY_DISABLED']);
    }
    if ($action === 'resolveIdentityClaim') {
        dent_error('تأیید یا رد دستی هویت بازنشسته شده است.', 410, ['code' => 'MANUAL_IDENTITY_DISABLED']);
    }
    if ($action === 'identityMappings') {
        return dent_bot_identity_mappings($user, $platform);
    }
    if ($action === 'setIdentityMapping') {
        dent_error('ساخت یا جایگزینی دستی اتصال غیرفعال است؛ خود دانشجو باید OTP یا ورود امن سایت را تکمیل کند.', 410, ['code' => 'MANUAL_IDENTITY_DISABLED']);
    }
    if ($action === 'deleteIdentityMapping') {
        return dent_bot_delete_identity_mapping($user, $platform, $payload);
    }
    if ($action === 'setGrade') {
        $targetStudentNumber = dent_normalize_student_number((string) ($payload['studentNumber'] ?? ''));
        $target = $targetStudentNumber !== '' ? dent_get_user_record($targetStudentNumber) : null;
        if (!is_array($target)) {
            dent_error('دانشجوی موردنظر پیدا نشد.', 404);
        }
        $targetCohort = dent_user_cohort_key($target);
        if (!dent_user_has_cohort_management_access($user, $targetCohort)) {
            dent_error('اجازه ثبت نمره را نداری.', 403);
        }
        $courseLabel = dent_clean_grade_course_label((string) ($payload['courseLabel'] ?? ''));
        $score = dent_parse_grade_score($payload['score'] ?? null);
        $maxScore = dent_parse_grade_score($payload['maxScore'] ?? 20);
        if ($courseLabel === '' || $score === null || $maxScore === null || $maxScore <= 0 || $maxScore > 100 || $score < 0 || $score > $maxScore) {
            dent_error('مشخصات نمره نامعتبر است.', 422);
        }
        dent_grades_set_active_cohort($targetCohort);
        $result = dent_owner_apply_grade_import($courseLabel, $maxScore, [[
            'studentNumber' => $targetStudentNumber,
            'score' => $score,
        ]]);
        return [
            'success' => true,
            'course' => $result['course'] ?? null,
            'grades' => dent_owner_grades_payload($targetStudentNumber, (string) ($target['name'] ?? '')),
        ];
    }
    dent_error('عملیات سرویس پشتیبانی نمی‌شود.', 404);
}
