<?php
declare(strict_types=1);

const DENT_STUDENT_ASSISTANT_CONTRACT = 'student-assistant-v1';
const DENT_STUDENT_ASSISTANT_ACTION_TTL_SECONDS = 600;
const DENT_STUDENT_ASSISTANT_CHALLENGE_TTL_SECONDS = 300;
const DENT_STUDENT_ASSISTANT_MAX_CAPTCHA_ATTEMPTS = 3;

function dent_student_assistant_store_path(): string
{
    return dent_storage_path('integrations/student_assistant.json');
}

function dent_student_assistant_store_default(): array
{
    return [
        'schemaVersion' => 1,
        'accounts' => [],
        'jobs' => [],
        'challenges' => [],
        'actions' => [],
        'idempotency' => [],
        'audit' => [],
    ];
}

function dent_student_assistant_normalize_store($value): array
{
    $store = is_array($value) ? array_merge(dent_student_assistant_store_default(), $value) : dent_student_assistant_store_default();
    foreach (['accounts', 'jobs', 'challenges', 'actions', 'idempotency', 'audit'] as $key) {
        if (!is_array($store[$key] ?? null)) {
            $store[$key] = [];
        }
    }
    return $store;
}

function dent_student_assistant_store_with_lock(callable $callback): array
{
    $path = dent_student_assistant_store_path();
    dent_ensure_directory(dirname($path));
    $handle = fopen($path . '.lock', 'c');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        dent_error('دستیار دانشجو موقتاً در دسترس نیست.', 503, ['code' => 'STUDENT_ASSISTANT_UNAVAILABLE']);
    }

    try {
        $decoded = is_file($path) ? dent_read_json_file($path, dent_student_assistant_store_default()) : dent_student_assistant_store_default();
        if (!is_array($decoded)) {
            throw new DentJsonPersistenceException('STUDENT_ASSISTANT_STORE_SCHEMA_INVALID', 'Student assistant state must be an object');
        }
        $store = dent_student_assistant_normalize_store($decoded);
        dent_student_assistant_cleanup($store, time());
        $result = $callback($store);
        dent_write_json_file($path, $store, true);
        return is_array($result) ? $result : [];
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function dent_student_assistant_cleanup(array &$store, int $now): void
{
    foreach ($store['actions'] as $key => $record) {
        if (!is_array($record) || (int) ($record['expiresAt'] ?? 0) < $now - 3600) {
            unset($store['actions'][$key]);
        }
    }
    foreach ($store['challenges'] as $key => $record) {
        if (!is_array($record) || (int) ($record['expiresAt'] ?? 0) < $now - 3600) {
            unset($store['challenges'][$key]);
        }
    }
    foreach ($store['jobs'] as $key => $record) {
        if (!is_array($record)) {
            unset($store['jobs'][$key]);
            continue;
        }
        $updatedAt = strtotime((string) ($record['updatedAt'] ?? ''));
        if ($updatedAt !== false && $updatedAt < $now - 604800) {
            unset($store['jobs'][$key]);
        }
    }
    foreach ($store['idempotency'] as $key => $record) {
        if (!is_array($record) || (int) ($record['expiresAt'] ?? 0) < $now) {
            unset($store['idempotency'][$key]);
        }
    }
    if (count($store['audit']) > 500) {
        $store['audit'] = array_slice($store['audit'], -500);
    }
}

function dent_student_assistant_require_contract(array $payload): void
{
    if (!hash_equals(DENT_STUDENT_ASSISTANT_CONTRACT, trim((string) ($payload['contractVersion'] ?? '')))) {
        dent_error('نسخه دستیار دانشجو پشتیبانی نمی‌شود.', 409, ['code' => 'STUDENT_ASSISTANT_CONTRACT_MISMATCH']);
    }
}

function dent_student_assistant_user_ref(array $user): string
{
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($studentNumber === '') {
        dent_error('حساب سایت نامعتبر است.', 422, ['code' => 'STUDENT_ASSISTANT_ACCOUNT_INVALID']);
    }
    return $studentNumber;
}

function dent_student_assistant_ref(int $bytes = 24): string
{
    return dent_bot_base64url_encode(random_bytes($bytes));
}

function dent_student_assistant_ref_hash(string $ref): string
{
    return hash_hmac('sha256', $ref, dent_auth_secret_key());
}

function dent_student_assistant_identity_ref(string $platform, string $platformUserId): string
{
    return dent_bot_identity_hash($platform, $platformUserId);
}

function dent_student_assistant_test_connector_enabled(): bool
{
    $environment = strtolower(trim((string) dent_env_value('DENT_APP_ENV')));
    return in_array($environment, ['development', 'test'], true)
        && filter_var(dent_env_value('DENT_STUDENT_ASSISTANT_TEST_CONNECTOR'), FILTER_VALIDATE_BOOLEAN);
}

function dent_student_assistant_connector_labels(): array
{
    return ['navid' => 'نوید', 'food' => 'تغذیه', 'saba' => 'سرویس'];
}

function dent_student_assistant_account_key(string $userRef, string $connector): string
{
    return hash_hmac('sha256', $userRef . ':' . $connector, dent_auth_secret_key());
}

function dent_student_assistant_issue_action(
    array &$store,
    string $identityRef,
    string $userRef,
    string $platform,
    string $connector,
    string $kind,
    string $jobRef = ''
): string {
    $ref = dent_student_assistant_ref(15);
    $store['actions'][dent_student_assistant_ref_hash($ref)] = [
        'identityRef' => $identityRef,
        'userRef' => $userRef,
        'platform' => $platform,
        'connector' => $connector,
        'kind' => $kind,
        'jobRef' => $jobRef,
        'createdAt' => dent_iso_now(),
        'expiresAt' => time() + DENT_STUDENT_ASSISTANT_ACTION_TTL_SECONDS,
        'usedAt' => 0,
    ];
    return $ref;
}

function dent_student_assistant_audit(array &$store, string $event, string $identityRef, string $connector, string $jobRef = ''): void
{
    $store['audit'][] = [
        'event' => dent_clean_text($event, 80),
        'identityRef' => substr($identityRef, 0, 16),
        'connector' => dent_clean_text($connector, 20),
        'jobRef' => $jobRef !== '' ? substr(hash('sha256', $jobRef), 0, 16) : '',
        'createdAt' => dent_iso_now(),
    ];
}

function dent_student_assistant_idempotency_key(string $identityRef, string $requestId): string
{
    if (preg_match('/^[a-f0-9]{64}$/', $requestId) !== 1) {
        dent_error('شناسه درخواست نامعتبر است.', 422, ['code' => 'STUDENT_ASSISTANT_REQUEST_INVALID']);
    }
    return hash_hmac('sha256', $identityRef . ':' . $requestId, dent_auth_secret_key());
}

function dent_student_assistant_idempotent_existing(array $store, string $key, string $fingerprint): ?array
{
    $record = $store['idempotency'][$key] ?? null;
    if (!is_array($record)) {
        return null;
    }
    if (!hash_equals((string) ($record['fingerprint'] ?? ''), $fingerprint)) {
        dent_error('شناسه درخواست قبلاً برای عملیات دیگری مصرف شده است.', 409, ['code' => 'STUDENT_ASSISTANT_IDEMPOTENCY_CONFLICT']);
    }
    return is_array($record['response'] ?? null) ? $record['response'] : null;
}

function dent_student_assistant_remember_response(array &$store, string $key, string $fingerprint, array $response): void
{
    $store['idempotency'][$key] = [
        'fingerprint' => $fingerprint,
        'response' => $response,
        'createdAt' => dent_iso_now(),
        'expiresAt' => time() + 86400,
    ];
}

function dent_student_assistant_summary_view(array &$store, string $identityRef, string $userRef, string $platform): array
{
    $connectors = [];
    $actions = [];
    foreach (dent_student_assistant_connector_labels() as $connector => $label) {
        $account = $store['accounts'][dent_student_assistant_account_key($userRef, $connector)] ?? null;
        $configured = is_array($account) && (string) ($account['status'] ?? '') === 'configured';
        $available = $configured && dent_student_assistant_test_connector_enabled();
        $connectors[] = [
            'connector' => $connector,
            'label' => $label,
            'status' => $available ? 'ready' : ($configured ? 'unavailable' : 'not-configured'),
            'statusLabel' => $available ? 'آماده' : ($configured ? 'موقتاً در دسترس نیست' : 'متصل نشده'),
            'maskedAccountLabel' => $configured ? dent_clean_text((string) ($account['maskedAccountLabel'] ?? ''), 80) : '',
        ];
        if ($available) {
            $actions[] = [
                'ref' => dent_student_assistant_issue_action($store, $identityRef, $userRef, $platform, $connector, 'start'),
                'label' => 'شروع ' . $label,
                'style' => 'primary',
            ];
        }
    }
    return [
        'title' => 'دستیار دانشجو',
        'description' => 'عملیات سامانه‌های دانشگاه با حساب متصل خودت انجام می‌شود. رمز یا کد ورود را در چت نفرست.',
        'statusText' => $actions ? 'سامانه‌های آماده را می‌توانی از همین‌جا ادامه بدهی.' : 'برای استفاده، اتصال امن سامانه باید از حساب سایت تکمیل شود.',
        'connectors' => $connectors,
        'items' => [],
        'actions' => $actions,
    ];
}

function dent_bot_student_assistant_summary_v1(array $user, string $platform, string $platformUserId, array $payload): array
{
    dent_student_assistant_require_contract($payload);
    [$platform, $platformUserId] = dent_bot_identity($platform, $platformUserId);
    $identityRef = dent_student_assistant_identity_ref($platform, $platformUserId);
    $userRef = dent_student_assistant_user_ref($user);
    return dent_student_assistant_store_with_lock(static function (array &$store) use ($identityRef, $userRef, $platform): array {
        return [
            'success' => true,
            'contractVersion' => DENT_STUDENT_ASSISTANT_CONTRACT,
            'view' => dent_student_assistant_summary_view($store, $identityRef, $userRef, $platform),
        ];
    });
}

function dent_student_assistant_fixture_answer(): string
{
    if (!dent_student_assistant_test_connector_enabled()) {
        return '';
    }
    $answer = strtoupper(trim((string) dent_env_value('DENT_STUDENT_ASSISTANT_TEST_CAPTCHA_ANSWER')));
    return preg_match('/^[A-Z0-9]{4,12}$/', $answer) === 1 ? $answer : '';
}

function dent_student_assistant_issue_challenge(array &$store, string $identityRef, string $userRef, string $platform, string $connector, string $jobRef): array
{
    $answer = dent_student_assistant_fixture_answer();
    if ($answer === '') {
        dent_error('اتصال سامانه بالادستی آماده نیست.', 503, ['code' => 'INTEGRATION_CONNECTOR_UNAVAILABLE']);
    }
    $ref = dent_student_assistant_ref();
    $expiresAt = time() + DENT_STUDENT_ASSISTANT_CHALLENGE_TTL_SECONDS;
    $store['challenges'][dent_student_assistant_ref_hash($ref)] = [
        'identityRef' => $identityRef,
        'userRef' => $userRef,
        'platform' => $platform,
        'connector' => $connector,
        'jobRef' => $jobRef,
        'expectedAnswerHash' => hash_hmac('sha256', $answer, dent_auth_secret_key()),
        'createdAt' => dent_iso_now(),
        'expiresAt' => $expiresAt,
        'usedAt' => 0,
    ];
    dent_student_assistant_audit($store, 'captcha-issued', $identityRef, $connector, $jobRef);
    return [
        'ref' => $ref,
        'connector' => $connector,
        'expiresAt' => gmdate('c', $expiresAt),
    ];
}

function dent_student_assistant_fixture_image_data_uri(): string
{
    return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
}

function dent_student_assistant_hydrate_response(array $response): array
{
    if ((string) ($response['status'] ?? '') === 'challenge' && is_array($response['challenge'] ?? null)) {
        if (!dent_student_assistant_test_connector_enabled()) {
            dent_error('تصویر کپچا در دسترس نیست.', 503, ['code' => 'INTEGRATION_CHALLENGE_UNAVAILABLE']);
        }
        $response['challenge']['imageDataUri'] = dent_student_assistant_fixture_image_data_uri();
    }
    return $response;
}

function dent_student_assistant_challenge_response(string $jobRef, string $connector, array $challenge): array
{
    $label = dent_student_assistant_connector_labels()[$connector] ?? 'سامانه دانشگاه';
    return [
        'success' => true,
        'contractVersion' => DENT_STUDENT_ASSISTANT_CONTRACT,
        'status' => 'challenge',
        'jobRef' => $jobRef,
        'connector' => $connector,
        'challenge' => $challenge,
        'view' => [
            'title' => 'ادامه عملیات ' . $label,
            'statusText' => 'در انتظار پاسخ تصویر',
        ],
    ];
}

function dent_bot_perform_integration_action_v1(array $user, string $platform, string $platformUserId, array $payload): array
{
    dent_student_assistant_require_contract($payload);
    [$platform, $platformUserId] = dent_bot_identity($platform, $platformUserId);
    $identityRef = dent_student_assistant_identity_ref($platform, $platformUserId);
    $userRef = dent_student_assistant_user_ref($user);
    $actionRef = trim((string) ($payload['actionRef'] ?? ''));
    if (preg_match('/^[A-Za-z0-9_-]{12,20}$/', $actionRef) !== 1) {
        dent_error('عملیات منقضی یا نامعتبر است.', 404, ['code' => 'INTEGRATION_ACTION_NOT_FOUND']);
    }
    $requestId = strtolower(trim((string) ($payload['requestId'] ?? '')));
    $idempotencyKey = dent_student_assistant_idempotency_key($identityRef, $requestId);
    $fingerprint = hash_hmac('sha256', 'action:' . $actionRef, dent_auth_secret_key());

    $response = dent_student_assistant_store_with_lock(static function (array &$store) use ($identityRef, $userRef, $platform, $actionRef, $idempotencyKey, $fingerprint): array {
        $existing = dent_student_assistant_idempotent_existing($store, $idempotencyKey, $fingerprint);
        if (is_array($existing)) {
            return $existing;
        }
        $actionKey = dent_student_assistant_ref_hash($actionRef);
        $action = $store['actions'][$actionKey] ?? null;
        $now = time();
        if (!is_array($action) || (int) ($action['usedAt'] ?? 0) > 0 || (int) ($action['expiresAt'] ?? 0) < $now
            || !hash_equals((string) ($action['identityRef'] ?? ''), $identityRef)
            || !hash_equals((string) ($action['userRef'] ?? ''), $userRef)
            || !hash_equals((string) ($action['platform'] ?? ''), $platform)) {
            dent_error('عملیات منقضی یا نامعتبر است.', 404, ['code' => 'INTEGRATION_ACTION_NOT_FOUND']);
        }
        $store['actions'][$actionKey]['usedAt'] = $now;
        $connector = (string) ($action['connector'] ?? '');
        $kind = (string) ($action['kind'] ?? '');
        if (!array_key_exists($connector, dent_student_assistant_connector_labels())) {
            dent_error('اتصال سامانه نامعتبر است.', 422, ['code' => 'INTEGRATION_CONNECTOR_INVALID']);
        }

        if ($kind === 'start') {
            $account = $store['accounts'][dent_student_assistant_account_key($userRef, $connector)] ?? null;
            if (!is_array($account) || (string) ($account['status'] ?? '') !== 'configured') {
                dent_error('ابتدا اتصال امن این سامانه را در سایت تکمیل کن.', 409, ['code' => 'INTEGRATION_ACCOUNT_REQUIRED']);
            }
            $jobRef = dent_student_assistant_ref();
            $store['jobs'][dent_student_assistant_ref_hash($jobRef)] = [
                'identityRef' => $identityRef,
                'userRef' => $userRef,
                'platform' => $platform,
                'connector' => $connector,
                'state' => 'challenge',
                'captchaAttempts' => 0,
                'createdAt' => dent_iso_now(),
                'updatedAt' => dent_iso_now(),
            ];
            $challenge = dent_student_assistant_issue_challenge($store, $identityRef, $userRef, $platform, $connector, $jobRef);
            $response = dent_student_assistant_challenge_response($jobRef, $connector, $challenge);
            dent_student_assistant_remember_response($store, $idempotencyKey, $fingerprint, $response);
            return $response;
        }

        if ($kind === 'confirm') {
            $jobRef = (string) ($action['jobRef'] ?? '');
            $jobKey = dent_student_assistant_ref_hash($jobRef);
            $job = $store['jobs'][$jobKey] ?? null;
            if (!is_array($job) || (string) ($job['state'] ?? '') !== 'preview'
                || !hash_equals((string) ($job['identityRef'] ?? ''), $identityRef)
                || !hash_equals((string) ($job['platform'] ?? ''), $platform)) {
                dent_error('عملیات آماده تأیید نیست.', 409, ['code' => 'INTEGRATION_JOB_NOT_CONFIRMABLE']);
            }
            if (!dent_student_assistant_test_connector_enabled()) {
                dent_error('اتصال سامانه بالادستی آماده نیست.', 503, ['code' => 'INTEGRATION_CONNECTOR_UNAVAILABLE']);
            }
            $receiptRef = dent_student_assistant_ref();
            $store['jobs'][$jobKey]['state'] = 'verified';
            $store['jobs'][$jobKey]['receiptRef'] = $receiptRef;
            $store['jobs'][$jobKey]['updatedAt'] = dent_iso_now();
            dent_student_assistant_audit($store, 'fixture-receipt-verified', $identityRef, $connector, $jobRef);
            $response = [
                'success' => true,
                'contractVersion' => DENT_STUDENT_ASSISTANT_CONTRACT,
                'status' => 'verified',
                'receiptRef' => $receiptRef,
                'view' => [
                    'title' => 'عملیات آزمایشی تکمیل شد',
                    'statusText' => 'رسید محیط تست ثبت و تأیید شد.',
                    'connectors' => [],
                    'items' => [],
                    'actions' => [],
                ],
            ];
            dent_student_assistant_remember_response($store, $idempotencyKey, $fingerprint, $response);
            return $response;
        }

        dent_error('عملیات پشتیبانی نمی‌شود.', 422, ['code' => 'INTEGRATION_ACTION_UNSUPPORTED']);
    });
    return dent_student_assistant_hydrate_response($response);
}

function dent_bot_integration_challenge_answer_v1(array $user, string $platform, string $platformUserId, array $payload): array
{
    dent_student_assistant_require_contract($payload);
    [$platform, $platformUserId] = dent_bot_identity($platform, $platformUserId);
    $identityRef = dent_student_assistant_identity_ref($platform, $platformUserId);
    $userRef = dent_student_assistant_user_ref($user);
    $challengeRef = trim((string) ($payload['challengeRef'] ?? ''));
    $jobRef = trim((string) ($payload['jobRef'] ?? ''));
    $answer = strtoupper(trim((string) ($payload['answer'] ?? '')));
    if (preg_match('/^[A-Za-z0-9_-]{12,80}$/', $challengeRef) !== 1 || preg_match('/^[A-Za-z0-9_-]{12,80}$/', $jobRef) !== 1
        || preg_match('/^[A-Z0-9]{4,12}$/', $answer) !== 1) {
        dent_error('پاسخ کپچا نامعتبر است.', 422, ['code' => 'INTEGRATION_CHALLENGE_ANSWER_INVALID']);
    }
    $requestId = strtolower(trim((string) ($payload['requestId'] ?? '')));
    $idempotencyKey = dent_student_assistant_idempotency_key($identityRef, $requestId);
    $answerProof = hash_hmac('sha256', $answer, dent_auth_secret_key());
    $fingerprint = hash_hmac('sha256', 'answer:' . $challengeRef . ':' . $jobRef . ':' . $answerProof, dent_auth_secret_key());
    unset($answer);

    $response = dent_student_assistant_store_with_lock(static function (array &$store) use (
        $identityRef,
        $userRef,
        $platform,
        $challengeRef,
        $jobRef,
        $answerProof,
        $idempotencyKey,
        $fingerprint
    ): array {
        $existing = dent_student_assistant_idempotent_existing($store, $idempotencyKey, $fingerprint);
        if (is_array($existing)) {
            return $existing;
        }
        $challengeKey = dent_student_assistant_ref_hash($challengeRef);
        $challenge = $store['challenges'][$challengeKey] ?? null;
        $now = time();
        if (!is_array($challenge) || (int) ($challenge['usedAt'] ?? 0) > 0 || (int) ($challenge['expiresAt'] ?? 0) < $now
            || !hash_equals((string) ($challenge['identityRef'] ?? ''), $identityRef)
            || !hash_equals((string) ($challenge['userRef'] ?? ''), $userRef)
            || !hash_equals((string) ($challenge['platform'] ?? ''), $platform)
            || !hash_equals((string) ($challenge['jobRef'] ?? ''), $jobRef)) {
            dent_error('کپچا منقضی یا قبلاً مصرف شده است.', 409, ['code' => 'INTEGRATION_CHALLENGE_NOT_ACTIVE']);
        }
        $jobKey = dent_student_assistant_ref_hash($jobRef);
        $job = $store['jobs'][$jobKey] ?? null;
        if (!is_array($job) || (string) ($job['state'] ?? '') !== 'challenge'
            || !hash_equals((string) ($job['identityRef'] ?? ''), $identityRef)
            || !hash_equals((string) ($job['platform'] ?? ''), $platform)) {
            dent_error('عملیات مرتبط با کپچا پیدا نشد.', 409, ['code' => 'INTEGRATION_JOB_NOT_ACTIVE']);
        }

        // Consume first. The same request can only obtain its recorded idempotent result.
        $store['challenges'][$challengeKey]['usedAt'] = $now;
        $connector = (string) ($challenge['connector'] ?? '');
        $attempts = max(0, (int) ($job['captchaAttempts'] ?? 0)) + 1;
        $store['jobs'][$jobKey]['captchaAttempts'] = $attempts;
        $store['jobs'][$jobKey]['updatedAt'] = dent_iso_now();
        $correct = hash_equals((string) ($challenge['expectedAnswerHash'] ?? ''), $answerProof);
        if (!$correct) {
            dent_student_assistant_audit($store, 'captcha-rejected', $identityRef, $connector, $jobRef);
            if ($attempts >= DENT_STUDENT_ASSISTANT_MAX_CAPTCHA_ATTEMPTS) {
                $store['jobs'][$jobKey]['state'] = 'failed';
                $response = [
                    'success' => false,
                    'contractVersion' => DENT_STUDENT_ASSISTANT_CONTRACT,
                    'status' => 'failed',
                    'code' => 'INTEGRATION_CAPTCHA_ATTEMPTS_EXHAUSTED',
                    'error' => 'تعداد تلاش‌های کپچا به پایان رسید؛ عملیات را از ابتدا شروع کن.',
                ];
                dent_student_assistant_remember_response($store, $idempotencyKey, $fingerprint, $response);
                return $response;
            }
            $freshChallenge = dent_student_assistant_issue_challenge($store, $identityRef, $userRef, $platform, $connector, $jobRef);
            $response = dent_student_assistant_challenge_response($jobRef, $connector, $freshChallenge);
            $response['view']['statusText'] = 'کد درست نبود؛ تصویر تازه ارسال شد.';
            dent_student_assistant_remember_response($store, $idempotencyKey, $fingerprint, $response);
            return $response;
        }

        $store['jobs'][$jobKey]['state'] = 'preview';
        $store['jobs'][$jobKey]['updatedAt'] = dent_iso_now();
        $confirmRef = dent_student_assistant_issue_action($store, $identityRef, $userRef, $platform, $connector, 'confirm', $jobRef);
        dent_student_assistant_audit($store, 'captcha-accepted-preview-ready', $identityRef, $connector, $jobRef);
        $label = dent_student_assistant_connector_labels()[$connector] ?? 'سامانه دانشگاه';
        $response = [
            'success' => true,
            'contractVersion' => DENT_STUDENT_ASSISTANT_CONTRACT,
            'status' => 'preview',
            'jobRef' => $jobRef,
            'connector' => $connector,
            'view' => [
                'title' => 'پیش‌نمایش ' . $label,
                'description' => 'کپچا تأیید شد، اما هنوز هیچ رزرو، ارسال یا پرداختی انجام نشده است.',
                'statusText' => 'برای اجرای نهایی باید جداگانه تأیید کنی.',
                'connectors' => [],
                'items' => [],
                'actions' => [[
                    'ref' => $confirmRef,
                    'label' => 'تأیید نهایی',
                    'style' => 'success',
                ]],
            ],
        ];
        dent_student_assistant_remember_response($store, $idempotencyKey, $fingerprint, $response);
        return $response;
    });
    return dent_student_assistant_hydrate_response($response);
}

/** Test-only fixture helper. Production calls fail closed. */
function dent_student_assistant_test_seed_connector(string $userRef, string $connector): void
{
    if (!dent_student_assistant_test_connector_enabled() || !array_key_exists($connector, dent_student_assistant_connector_labels())) {
        throw new RuntimeException('Student assistant fixture connector is unavailable.');
    }
    dent_student_assistant_store_with_lock(static function (array &$store) use ($userRef, $connector): array {
        $store['accounts'][dent_student_assistant_account_key($userRef, $connector)] = [
            'connector' => $connector,
            'status' => 'configured',
            'maskedAccountLabel' => 'fixture-account',
            'credentialVersion' => 1,
            'verifiedAt' => dent_iso_now(),
            'updatedAt' => dent_iso_now(),
        ];
        return [];
    });
}
