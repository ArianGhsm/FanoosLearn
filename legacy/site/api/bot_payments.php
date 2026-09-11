<?php
declare(strict_types=1);

require_once __DIR__ . '/payments_gateway.php';
require_once __DIR__ . '/payment_handoff.php';

/** Keep provider URLs internal and expose only the constrained browser launch URL. */
function dent_bot_payment_public_redirect(array $startResult, string $gateway): string
{
    $redirectUrl = dent_clean_text((string) ($startResult['redirectUrl'] ?? ''), 900);
    $resolved = payments_gateway_resolve_record($gateway);
    $provider = payments_gateway_provider_clean((string) ($startResult['gateway'] ?? ($resolved['provider'] ?? '')));
    $isExactZibalUrl = preg_match('#^https://gateway\.zibal\.ir/start/[1-9][0-9]{0,19}$#D', $redirectUrl) === 1;

    if ($provider === PAYMENTS_GATEWAY_ZIBAL || $isExactZibalUrl) {
        $trackId = trim((string) (($startResult['trackId'] ?? '') ?: ($startResult['authority'] ?? '')));
        try {
            return dent_zibal_handoff_url($redirectUrl, $trackId);
        } catch (InvalidArgumentException $error) {
            dent_error('پاسخ درگاه معتبر نبود.', 503, ['code' => 'PAYMENT_GATEWAY_RESPONSE_INVALID']);
        }
    }

    if (str_starts_with($redirectUrl, '/')) {
        $redirectUrl = dent_bot_site_origin() . $redirectUrl;
    }
    $redirectScheme = strtolower((string) parse_url($redirectUrl, PHP_URL_SCHEME));
    if ($redirectUrl === '' || filter_var($redirectUrl, FILTER_VALIDATE_URL) === false || $redirectScheme !== 'https') {
        dent_error('پاسخ درگاه معتبر نبود.', 503, ['code' => 'PAYMENT_GATEWAY_RESPONSE_INVALID']);
    }
    return $redirectUrl;
}

function dent_bot_payment_request_ref(string $platform, string $platformUserId, string $requestId): string
{
    $requestId = trim($requestId);
    if (preg_match('/^[A-Za-z0-9_-]{12,100}$/', $requestId) !== 1) {
        dent_error('شناسه درخواست پرداخت نامعتبر است.', 422, ['code' => 'INVALID_PAYMENT_REQUEST']);
    }
    return hash_hmac('sha256', $platform . ':' . $platformUserId . ':' . $requestId, dent_auth_secret_key());
}

function dent_bot_payment_profile_payer_key(string $phoneNumber): string
{
    $phone = dent_normalize_phone_number($phoneNumber);
    if ($phone === '') {
        return '';
    }
    return 'profile:' . substr(hash_hmac('sha256', 'bot-payment-profile:' . $phone, dent_auth_secret_key()), 0, 40);
}

/** @return list<string> */
function dent_bot_payment_payer_keys(array $user): array
{
    $explicit = trim((string) ($user['botPaymentPayerKey'] ?? ''));
    if (preg_match('/^profile:[a-f0-9]{40}$/', $explicit) === 1) {
        // Generic onboarding student numbers are self-declared and optional;
        // they must never grant access to another website student's orders.
        return [$explicit];
    }
    $keys = [];
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($studentNumber !== '') {
        $keys[] = $studentNumber;
    }
    $profileKey = dent_bot_payment_profile_payer_key((string) ($user['phoneNumber'] ?? ''));
    if ($profileKey !== '') {
        $keys[] = $profileKey;
    }
    return array_values(array_unique($keys));
}

function dent_bot_payment_result_url(string $orderToken): string
{
    return dent_bot_site_origin() . '/buy/result/?orderToken=' . rawurlencode($orderToken);
}

function dent_bot_payment_require_v2(array $payload): void
{
    if ((string) ($payload['contractVersion'] ?? '') !== 'bot-commerce-v2') {
        dent_error('نسخه قرارداد محصول معتبر نیست.', 409, ['code' => 'BOT_COMMERCE_CONTRACT_REQUIRED']);
    }
}

function dent_bot_payment_iso(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        dent_error('زمان‌بندی محصول معتبر نیست.', 422, ['code' => 'PRODUCT_WINDOW_INVALID']);
    }
    return gmdate('Y-m-d\TH:i:s\Z', $timestamp);
}

function dent_bot_payment_extra(array $order): array
{
    return is_array($order['extra_form_data'] ?? null) ? $order['extra_form_data'] : [];
}

function dent_bot_payment_is_offer_order(array $order, string $offerRef = ''): bool
{
    $extra = dent_bot_payment_extra($order);
    if ((string) ($extra['source'] ?? '') !== 'bot-offer') {
        return false;
    }
    return $offerRef === '' || hash_equals((string) ($extra['bot_offer_ref'] ?? ''), $offerRef);
}

function dent_bot_payment_status_label(string $status): string
{
    return [
        PAYMENTS_ORDER_STATUS_SUCCESS => 'موفق',
        PAYMENTS_ORDER_STATUS_PENDING => 'در انتظار',
        PAYMENTS_ORDER_STATUS_FAILED => 'ناموفق',
        PAYMENTS_ORDER_STATUS_CANCELED => 'لغوشده',
        PAYMENTS_ORDER_STATUS_EXPIRED => 'منقضی',
    ][$status] ?? 'نامشخص';
}

function dent_bot_payment_safe_fulfillment($value): array
{
    if (!is_array($value)) {
        return [];
    }
    $result = [];
    $text = dent_clean_text((string) ($value['text'] ?? ''), 600);
    if ($text !== '') {
        $result['text'] = $text;
    }
    $url = dent_clean_text((string) ($value['url'] ?? ''), 900);
    if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) !== false && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https') {
        $result['url'] = $url;
    }
    return $result;
}

function dent_bot_payment_order_payload(array $order, bool $owner = false): array
{
    $extra = dent_bot_payment_extra($order);
    $payload = [
        'orderId' => (int) ($order['id'] ?? 0),
        'orderToken' => (string) ($order['public_token'] ?? ''),
        'offerRef' => (string) ($extra['bot_offer_ref'] ?? ''),
        'title' => (string) ($extra['bot_offer_title'] ?? 'محصول'),
        'amountRials' => max(0, (int) ($order['amount'] ?? 0)),
        'status' => (string) ($order['status'] ?? PAYMENTS_ORDER_STATUS_PENDING),
        'statusLabel' => dent_bot_payment_status_label((string) ($order['status'] ?? '')),
        'gateway' => (string) ($order['gateway'] ?? ''),
        'trackingRef' => (string) (($order['ref_id'] ?? '') ?: ($order['authority'] ?? '')),
        'createdAt' => (string) ($order['created_at'] ?? ''),
        'paymentStartedAt' => (string) ($order['payment_started_at'] ?? ''),
        'paidAt' => (string) ($order['paid_at'] ?? ''),
        'verifiedAt' => (string) ($order['verified_at'] ?? ''),
        'updatedAt' => (string) ($order['updated_at'] ?? ''),
        'expiredAt' => (string) ($order['expires_at'] ?? ''),
        'originPlatform' => (string) ($extra['bot_origin_platform'] ?? ''),
        'fulfillment' => dent_bot_payment_safe_fulfillment(
            is_string($extra['bot_fulfillment_json'] ?? null)
                ? (json_decode((string) $extra['bot_fulfillment_json'], true) ?: [])
                : []
        ),
    ];
    if ($owner) {
        $payload['payerName'] = (string) ($order['payer_name'] ?? '');
        $studentNumber = dent_normalize_student_number((string) ($order['payer_student_number'] ?? ''));
        if ($studentNumber === '') {
            $legacyUserId = dent_normalize_digits(trim((string) ($order['user_id'] ?? '')));
            if (preg_match('/^[0-9]{5,20}$/', $legacyUserId) === 1) {
                $studentNumber = $legacyUserId;
            }
        }
        $payload['studentNumber'] = $studentNumber;
        $payload['payerPhone'] = (string) ($order['payer_phone'] ?? '');
    }
    return $payload;
}

function dent_bot_payment_existing_response(array $order): ?array
{
    $snapshot = is_array($order['gateway_response_snapshot'] ?? null) ? $order['gateway_response_snapshot'] : [];
    $start = is_array($snapshot['start'] ?? null) ? $snapshot['start'] : [];
    $orderToken = (string) ($order['public_token'] ?? '');
    if (trim((string) ($start['redirectUrl'] ?? '')) === '' || $orderToken === '') {
        return null;
    }
    $start['gateway'] = (string) (($start['gateway'] ?? '') ?: ($order['gateway'] ?? ''));
    $start['authority'] = (string) (($start['authority'] ?? '') ?: ($order['authority'] ?? ''));
    $start['trackId'] = (string) (($start['trackId'] ?? '') ?: $start['authority']);
    $redirectUrl = dent_bot_payment_public_redirect($start, (string) ($order['gateway'] ?? ''));
    return [
        'success' => true,
        'alreadyCreated' => true,
        'orderToken' => $orderToken,
        'amountRials' => max(0, (int) ($order['amount'] ?? 0)),
        'redirectUrl' => $redirectUrl,
        'resultUrl' => dent_bot_payment_result_url($orderToken),
        'status' => (string) ($order['status'] ?? PAYMENTS_ORDER_STATUS_PENDING),
    ];
}

/**
 * Computes the checkout reservation state in one deterministic pass.
 *
 * The caller still holds the payments-store exclusive lock, so the returned
 * counters and any order created from them belong to the same atomic decision.
 * Expired pending attempts do not consume capacity; successful attempts do.
 *
 * @return array{existing:?array,reserved:int,userReserved:int}
 */
function dent_bot_payment_reservation_state(
    array $orders,
    string $offerRef,
    string $payerKey,
    string $requestRef,
    ?int $nowEpoch = null,
    array $payerKeys = []
): array {
    $nowEpoch = $nowEpoch ?? time();
    $identityKeys = array_values(array_unique(array_filter(array_map('strval', $payerKeys))));
    if ($identityKeys === []) {
        $identityKeys = [$payerKey];
    }
    $state = ['existing' => null, 'reserved' => 0, 'userReserved' => 0];
    foreach ($orders as $existing) {
        if (!is_array($existing)) {
            continue;
        }
        $extra = is_array($existing['extra_form_data'] ?? null) ? $existing['extra_form_data'] : [];
        if (in_array((string) ($existing['user_id'] ?? ''), $identityKeys, true)
            && (string) ($extra['bot_request_ref'] ?? '') !== ''
            && hash_equals((string) ($extra['bot_request_ref'] ?? ''), $requestRef)) {
            $state['existing'] = $existing;
        }
        if (!dent_bot_payment_is_offer_order($existing, $offerRef)) {
            continue;
        }
        $status = (string) ($existing['status'] ?? PAYMENTS_ORDER_STATUS_PENDING);
        if (!in_array($status, [PAYMENTS_ORDER_STATUS_PENDING, PAYMENTS_ORDER_STATUS_SUCCESS], true)) {
            continue;
        }
        if ($status === PAYMENTS_ORDER_STATUS_PENDING) {
            $expiresAt = trim((string) ($existing['expires_at'] ?? ''));
            if ($expiresAt !== '' && (int) strtotime($expiresAt) <= $nowEpoch) {
                continue;
            }
        }
        $state['reserved']++;
        if (in_array((string) ($existing['user_id'] ?? ''), $identityKeys, true)) {
            $state['userReserved']++;
        }
    }
    return $state;
}

function dent_bot_payment_reservation_error(array $state, int $capacity, int $maxPerUser): string
{
    if ($capacity > 0 && (int) ($state['reserved'] ?? 0) >= $capacity) {
        return 'PRODUCT_CAPACITY_REACHED';
    }
    if ($maxPerUser > 0 && (int) ($state['userReserved'] ?? 0) >= $maxPerUser) {
        return 'PRODUCT_PURCHASE_LIMIT_REACHED';
    }
    return '';
}

function dent_bot_create_offer_payment(array $user, string $platform, string $platformUserId, array $payload): array
{
    dent_bot_payment_require_v2($payload);
    $offerRef = trim((string) ($payload['offerRef'] ?? ''));
    $title = dent_clean_text((string) ($payload['title'] ?? ''), 160);
    $description = dent_clean_text((string) ($payload['description'] ?? ''), 360);
    $amount = max(0, (int) dent_normalize_digits((string) ($payload['amountRials'] ?? '0')));
    $requestRef = dent_bot_payment_request_ref($platform, $platformUserId, (string) ($payload['requestId'] ?? ''));
    $productVersion = max(1, min(1000000, (int) ($payload['productVersion'] ?? 1)));
    $availableFrom = dent_bot_payment_iso((string) ($payload['availableFrom'] ?? ''));
    $expiresAt = dent_bot_payment_iso((string) ($payload['expiresAt'] ?? ''));
    $capacity = max(0, min(1000000, (int) ($payload['capacity'] ?? 0)));
    $maxPerUser = max(0, min(10000, (int) ($payload['maxPurchasesPerUser'] ?? 1)));
    $fulfillment = dent_bot_payment_safe_fulfillment($payload['fulfillment'] ?? []);
    $nowEpoch = time();
    if (($availableFrom !== '' && (int) strtotime($availableFrom) > $nowEpoch)
        || ($expiresAt !== '' && (int) strtotime($expiresAt) <= $nowEpoch)) {
        dent_error('این محصول اکنون قابل خرید نیست.', 409, ['code' => 'PRODUCT_NOT_AVAILABLE']);
    }
    if (preg_match('/^[A-Za-z0-9_-]{16,80}$/', $offerRef) !== 1 || $title === '') {
        dent_error('محصول پرداختی ربات نامعتبر است.', 422, ['code' => 'BOT_PAYMENT_OFFER_INVALID']);
    }
    if ($amount < 10000 || $amount > 100000000000) {
        dent_error('مبلغ پرداخت نامعتبر است.', 422, ['code' => 'PAYMENT_AMOUNT_INVALID']);
    }

    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    $payerKeys = dent_bot_payment_payer_keys($user);
    $payerKey = (string) ($payerKeys[0] ?? '');
    $payerName = dent_clean_text((string) ($user['name'] ?? ''), 120);
    $payerPhone = payments_normalize_phone((string) ($user['phoneNumber'] ?? ''));
    if ($payerKey === '' || $payerName === '') {
        dent_error('اطلاعات پروفایل تأییدشده برای پرداخت کامل نیست.', 422, ['code' => 'PAYMENT_PROFILE_INCOMPLETE']);
    }
    if ($payerPhone === '' || strlen($payerPhone) < 10 || strlen($payerPhone) > 14) {
        dent_error('شماره موبایل تأییدشده برای پرداخت لازم است.', 422, ['code' => 'PAYMENT_PHONE_REQUIRED']);
    }

    $appEnvironment = strtolower(trim((string) dent_env_value('DENT_APP_ENV')));
    $allowMock = in_array($appEnvironment, ['development', 'test'], true);
    $enabledGateways = payments_gateway_enabled_checkout_keys($allowMock);
    $gateway = payments_gateway_default_enabled_checkout($allowMock);
    if ($gateway === '' || !in_array($gateway, $enabledGateways, true)) {
        dent_error('درگاه پرداخت فعالی وجود ندارد.', 503, ['code' => 'PAYMENT_GATEWAY_UNAVAILABLE']);
    }

    $created = payments_with_store_lock(static function (array &$store) use (
        $offerRef,
        $title,
        $description,
        $amount,
        $requestRef,
        $studentNumber,
        $payerKey,
        $payerKeys,
        $payerName,
        $payerPhone,
        $gateway,
        $platform,
        $platformUserId,
        $productVersion,
        $availableFrom,
        $expiresAt,
        $capacity,
        $maxPerUser,
        $fulfillment
    ): array {
        $reservation = dent_bot_payment_reservation_state(
            is_array($store['orders'] ?? null) ? $store['orders'] : [],
            $offerRef,
            $payerKey,
            $requestRef,
            null,
            $payerKeys
        );
        if (is_array($reservation['existing'] ?? null)) {
            return ['existing' => $reservation['existing']];
        }
        $reservationError = dent_bot_payment_reservation_error($reservation, $capacity, $maxPerUser);
        if ($reservationError === 'PRODUCT_CAPACITY_REACHED') {
            dent_error('ظرفیت این محصول تکمیل شده است.', 409, ['code' => 'PRODUCT_CAPACITY_REACHED']);
        }
        if ($reservationError === 'PRODUCT_PURCHASE_LIMIT_REACHED') {
            dent_error('سقف خرید این محصول برای حساب شما تکمیل شده است.', 409, ['code' => 'PRODUCT_PURCHASE_LIMIT_REACHED']);
        }

        $now = dent_iso_now();
        $order = [
            'id' => payments_next_order_id($store),
            'item_id' => 0,
            'user_id' => $payerKey,
            'payer_name' => $payerName,
            'payer_phone' => $payerPhone,
            'payer_student_number' => $studentNumber,
            'extra_form_data' => [
                'source' => 'bot-offer',
                'bot_offer_ref' => $offerRef,
                'bot_offer_title' => $title,
                'bot_offer_description' => $description,
                'bot_request_ref' => $requestRef,
                'bot_offer_version' => $productVersion,
                'bot_origin_platform' => $platform,
                'bot_origin_identity_hash' => dent_bot_identity_hash($platform, $platformUserId),
                'bot_origin_route_encrypted_json' => json_encode(
                    dent_encrypt_secret_text($platformUserId),
                    JSON_UNESCAPED_SLASHES
                ) ?: '',
                'bot_available_from' => $availableFrom,
                'bot_expires_at' => $expiresAt,
                'bot_capacity' => $capacity,
                'bot_max_purchases_per_user' => $maxPerUser,
                'bot_fulfillment_json' => json_encode($fulfillment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            ],
            'quantity' => 1,
            'unit_price' => $amount,
            'subtotal' => $amount,
            'discount_code' => '',
            'discount_amount' => 0,
            'amount' => $amount,
            'gateway' => $gateway,
            'authority' => '',
            'ref_id' => '',
            'status' => PAYMENTS_ORDER_STATUS_PENDING,
            'gateway_response_snapshot' => ['created' => ['at' => $now, 'source' => 'bot-offer']],
            'created_at' => $now,
            'payment_started_at' => '',
            'paid_at' => '',
            'verified_at' => '',
            'updated_at' => $now,
            'expires_at' => $expiresAt,
            'public_token' => payments_random_token(),
        ];
        $store['orders'][] = $order;
        return ['order' => $order];
    });

    if (is_array($created['existing'] ?? null)) {
        $response = dent_bot_payment_existing_response($created['existing']);
        if (is_array($response)) {
            return $response;
        }
        dent_error('درخواست پرداخت قبلی هنوز در حال ایجاد است.', 409, ['code' => 'PAYMENT_REQUEST_IN_PROGRESS']);
    }

    $order = is_array($created['order'] ?? null) ? $created['order'] : [];
    $syntheticItem = [
        'id' => 0,
        'title' => $title,
        'description' => $description,
        'price' => $amount,
        'amount' => $amount,
    ];
    $orderToken = (string) ($order['public_token'] ?? '');
    $callbackUrl = dent_bot_site_origin() . '/api/payments_api.php?action=callback&orderToken=' . rawurlencode($orderToken);
    $startResult = payments_gateway_start_payment((string) ($order['gateway'] ?? ''), $syntheticItem, $order, [
        'callbackUrl' => $callbackUrl,
        'description' => 'پرداخت ' . $title,
        'mobile' => $payerPhone,
        'orderId' => $orderToken,
    ]);

    if (!(bool) ($startResult['success'] ?? false)) {
        payments_with_store_lock(static function (array &$store) use ($order, $startResult): void {
            $index = payments_find_order_index_by_id($store, (int) ($order['id'] ?? 0));
            if ($index < 0) {
                return;
            }
            $store['orders'][$index]['status'] = PAYMENTS_ORDER_STATUS_FAILED;
            $store['orders'][$index]['gateway_response_snapshot']['start'] = $startResult;
            $store['orders'][$index]['updated_at'] = dent_iso_now();
        });
        dent_error('ساخت درخواست درگاه انجام نشد.', 503, ['code' => 'PAYMENT_START_FAILED']);
    }

    $redirectUrl = dent_bot_payment_public_redirect($startResult, (string) ($order['gateway'] ?? ''));
    payments_with_store_lock(static function (array &$store) use ($order, $startResult): void {
        $index = payments_find_order_index_by_id($store, (int) ($order['id'] ?? 0));
        if ($index < 0) {
            return;
        }
        $store['orders'][$index]['authority'] = dent_clean_text((string) ($startResult['authority'] ?? ''), 120);
        $store['orders'][$index]['gateway_response_snapshot']['start'] = $startResult;
        $store['orders'][$index]['payment_started_at'] = dent_iso_now();
        $store['orders'][$index]['updated_at'] = dent_iso_now();
    });

    return [
        'success' => true,
        'alreadyCreated' => false,
        'orderToken' => $orderToken,
        'amountRials' => $amount,
        'redirectUrl' => $redirectUrl,
        'resultUrl' => dent_bot_payment_result_url($orderToken),
        'status' => PAYMENTS_ORDER_STATUS_PENDING,
    ];
}

function dent_bot_payment_status(array $user, string $platform, string $platformUserId, array $payload): array
{
    $orderToken = trim((string) ($payload['orderToken'] ?? ''));
    if (preg_match('/^[A-Za-z0-9_-]{20,120}$/', $orderToken) !== 1) {
        dent_error('سفارش پیدا نشد.', 404, ['code' => 'PAYMENT_ORDER_NOT_FOUND']);
    }
    $store = payments_read_store();
    $index = payments_find_order_index_by_token($store, $orderToken);
    $order = $index >= 0 && is_array($store['orders'][$index] ?? null) ? $store['orders'][$index] : null;
    $payerKeys = dent_bot_payment_payer_keys($user);
    $extra = is_array($order) ? dent_bot_payment_extra($order) : [];
    if (!is_array($order)
        || !in_array((string) ($order['user_id'] ?? ''), $payerKeys, true)
        || !hash_equals((string) ($extra['bot_origin_platform'] ?? ''), $platform)
        || !hash_equals((string) ($extra['bot_origin_identity_hash'] ?? ''), dent_bot_identity_hash($platform, $platformUserId))) {
        dent_error('سفارش پیدا نشد.', 404, ['code' => 'PAYMENT_ORDER_NOT_FOUND']);
    }
    return array_merge([
        'success' => true,
        'resultUrl' => dent_bot_payment_result_url($orderToken),
    ], dent_bot_payment_order_payload($order));
}

function dent_bot_payment_require_owner(array $user): void
{
    if ((string) ($user['role'] ?? '') !== 'owner') {
        dent_error('این بخش فقط برای مالک است.', 403, ['code' => 'OWNER_REQUIRED']);
    }
}

function dent_bot_payment_orders(string $offerRef = ''): array
{
    $orders = [];
    foreach (payments_read_store()['orders'] ?? [] as $order) {
        if (is_array($order) && dent_bot_payment_is_offer_order($order, $offerRef)) {
            $orders[] = $order;
        }
    }
    usort($orders, static fn(array $left, array $right): int => strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? '')));
    return $orders;
}

function dent_bot_payment_product_states(array $user, array $payload): array
{
    dent_bot_payment_require_v2($payload);
    $refs = is_array($payload['offerRefs'] ?? null) ? $payload['offerRefs'] : [];
    $allowed = [];
    foreach (array_slice($refs, 0, 200) as $ref) {
        $ref = trim((string) $ref);
        if (preg_match('/^[A-Za-z0-9_-]{16,80}$/', $ref) === 1) {
            $allowed[$ref] = true;
        }
    }
    $payerKeys = dent_bot_payment_payer_keys($user);
    $states = [];
    foreach (array_keys($allowed) as $ref) {
        $states[$ref] = ['successCount' => 0, 'pendingCount' => 0, 'reservedCount' => 0, 'latestSuccessOrderToken' => ''];
    }
    foreach (dent_bot_payment_orders() as $order) {
        $extra = dent_bot_payment_extra($order);
        $ref = (string) ($extra['bot_offer_ref'] ?? '');
        if (!isset($states[$ref])) {
            continue;
        }
        $status = (string) ($order['status'] ?? PAYMENTS_ORDER_STATUS_PENDING);
        if ($status === PAYMENTS_ORDER_STATUS_PENDING && trim((string) ($order['expires_at'] ?? '')) !== ''
            && (int) strtotime((string) $order['expires_at']) <= time()) {
            continue;
        }
        if (in_array($status, [PAYMENTS_ORDER_STATUS_SUCCESS, PAYMENTS_ORDER_STATUS_PENDING], true)) {
            $states[$ref]['reservedCount']++;
        }
        if (!in_array((string) ($order['user_id'] ?? ''), $payerKeys, true)) {
            continue;
        }
        if ($status === PAYMENTS_ORDER_STATUS_SUCCESS) {
            $states[$ref]['successCount']++;
            if ($states[$ref]['latestSuccessOrderToken'] === '') {
                $states[$ref]['latestSuccessOrderToken'] = (string) ($order['public_token'] ?? '');
            }
        } elseif ($status === PAYMENTS_ORDER_STATUS_PENDING) {
            $states[$ref]['pendingCount']++;
        }
    }
    return ['success' => true, 'contractVersion' => 'bot-commerce-v2', 'states' => $states];
}

function dent_bot_payment_summary_bucket(array $orders, int $since = 0): array
{
    $result = ['successCount' => 0, 'pendingCount' => 0, 'failedCount' => 0, 'receivedRials' => 0, 'pendingRials' => 0];
    foreach ($orders as $order) {
        $status = (string) ($order['status'] ?? PAYMENTS_ORDER_STATUS_PENDING);
        if ($status === PAYMENTS_ORDER_STATUS_PENDING) {
            $expiresAt = trim((string) ($order['expires_at'] ?? ''));
            if ($expiresAt !== '' && (int) strtotime($expiresAt) <= time()) {
                continue;
            }
        }
        $eventAt = $status === PAYMENTS_ORDER_STATUS_SUCCESS
            ? (string) (($order['verified_at'] ?? '') ?: (($order['paid_at'] ?? '') ?: ($order['created_at'] ?? '')))
            : (string) ($order['created_at'] ?? '');
        $eventTimestamp = strtotime($eventAt) ?: 0;
        if ($since > 0 && $eventTimestamp < $since) {
            continue;
        }
        $amount = max(0, (int) ($order['amount'] ?? 0));
        if ($status === PAYMENTS_ORDER_STATUS_SUCCESS) {
            $result['successCount']++;
            $result['receivedRials'] += $amount;
        } elseif ($status === PAYMENTS_ORDER_STATUS_PENDING) {
            $result['pendingCount']++;
            $result['pendingRials'] += $amount;
        } else {
            $result['failedCount']++;
        }
    }
    return $result;
}

function dent_bot_payment_owner_dashboard(array $owner, array $payload): array
{
    dent_bot_payment_require_owner($owner);
    dent_bot_payment_require_v2($payload);
    $orders = dent_bot_payment_orders();
    $startToday = strtotime('today');
    return [
        'success' => true,
        'contractVersion' => 'bot-commerce-v2',
        'today' => dent_bot_payment_summary_bucket($orders, $startToday === false ? time() : $startToday),
        'week' => dent_bot_payment_summary_bucket($orders, time() - 7 * 86400),
        'month' => dent_bot_payment_summary_bucket($orders, time() - 30 * 86400),
        'total' => dent_bot_payment_summary_bucket($orders),
    ];
}

function dent_bot_payment_product_report(array $owner, array $payload): array
{
    dent_bot_payment_require_owner($owner);
    dent_bot_payment_require_v2($payload);
    $offerRef = trim((string) ($payload['offerRef'] ?? ''));
    if (preg_match('/^[A-Za-z0-9_-]{16,80}$/', $offerRef) !== 1) {
        dent_error('محصول پیدا نشد.', 404, ['code' => 'PRODUCT_NOT_FOUND']);
    }
    $dateFrom = strtotime((string) ($payload['dateFrom'] ?? '')) ?: 0;
    $dateTo = strtotime((string) ($payload['dateTo'] ?? '')) ?: 0;
    $orders = dent_bot_payment_orders($offerRef);
    $counts = ['success' => 0, 'pending' => 0, 'failed' => 0, 'uniquePayers' => 0];
    $payers = [];
    $unique = [];
    $received = 0;
    $pendingRials = 0;
    foreach ($orders as $order) {
        $created = strtotime((string) ($order['created_at'] ?? '')) ?: 0;
        if (($dateFrom > 0 && $created < $dateFrom) || ($dateTo > 0 && $created > $dateTo)) {
            continue;
        }
        $status = (string) ($order['status'] ?? PAYMENTS_ORDER_STATUS_PENDING);
        if ($status === PAYMENTS_ORDER_STATUS_PENDING) {
            $orderExpiry = trim((string) ($order['expires_at'] ?? ''));
            if ($orderExpiry !== '' && (int) strtotime($orderExpiry) <= time()) {
                continue;
            }
        }
        $amount = max(0, (int) ($order['amount'] ?? 0));
        $studentNumber = dent_normalize_student_number((string) ($order['payer_student_number'] ?? ''));
        if ($studentNumber === '') {
            $legacyUserId = dent_normalize_digits(trim((string) ($order['user_id'] ?? '')));
            if (preg_match('/^[0-9]{5,20}$/', $legacyUserId) === 1) {
                $studentNumber = $legacyUserId;
            }
        }
        $payerIdentity = trim((string) ($order['user_id'] ?? ''));
        if ($status === PAYMENTS_ORDER_STATUS_SUCCESS) {
            $counts['success']++;
            $received += $amount;
            if ($payerIdentity !== '' && !isset($unique[$payerIdentity])) {
                $unique[$payerIdentity] = true;
                $payers[] = [
                    'name' => (string) ($order['payer_name'] ?? ''),
                    'studentNumber' => $studentNumber,
                    'paidAt' => (string) (($order['verified_at'] ?? '') ?: ($order['paid_at'] ?? '')),
                    'amountRials' => $amount,
                    'orderToken' => (string) ($order['public_token'] ?? ''),
                ];
            }
        } elseif ($status === PAYMENTS_ORDER_STATUS_PENDING) {
            $counts['pending']++;
            $pendingRials += $amount;
        } else {
            $counts['failed']++;
        }
    }
    $counts['uniquePayers'] = count($unique);
    return [
        'success' => true, 'contractVersion' => 'bot-commerce-v2', 'offerRef' => $offerRef,
        'counts' => $counts, 'receivedRials' => $received, 'pendingRials' => $pendingRials,
        'payers' => $payers,
        'dateFrom' => $dateFrom > 0 ? gmdate('Y-m-d\TH:i:s\Z', $dateFrom) : '',
        'dateTo' => $dateTo > 0 ? gmdate('Y-m-d\TH:i:s\Z', $dateTo) : '',
    ];
}

function dent_bot_payment_transactions(array $owner, array $payload): array
{
    dent_bot_payment_require_owner($owner);
    dent_bot_payment_require_v2($payload);
    $query = mb_strtolower(dent_clean_text((string) ($payload['query'] ?? ''), 120), 'UTF-8');
    $offerRef = trim((string) ($payload['offerRef'] ?? ''));
    $status = trim((string) ($payload['status'] ?? ''));
    $originPlatform = trim((string) ($payload['originPlatform'] ?? ''));
    $gateway = trim((string) ($payload['gateway'] ?? ''));
    $dateFrom = strtotime((string) ($payload['dateFrom'] ?? '')) ?: 0;
    $dateTo = strtotime((string) ($payload['dateTo'] ?? '')) ?: 0;
    $page = max(0, (int) ($payload['page'] ?? 0));
    $limit = max(1, min(100, (int) ($payload['limit'] ?? 20)));
    $filtered = [];
    foreach (dent_bot_payment_orders() as $order) {
        $extra = dent_bot_payment_extra($order);
        $created = strtotime((string) ($order['created_at'] ?? '')) ?: 0;
        if (($offerRef !== '' && (string) ($extra['bot_offer_ref'] ?? '') !== $offerRef)
            || ($status !== '' && (string) ($order['status'] ?? '') !== $status)
            || ($originPlatform !== '' && (string) ($extra['bot_origin_platform'] ?? '') !== $originPlatform)
            || ($gateway !== '' && (string) ($order['gateway'] ?? '') !== $gateway)
            || ($dateFrom > 0 && $created < $dateFrom)
            || ($dateTo > 0 && $created > $dateTo)) {
            continue;
        }
        if ($query !== '') {
            $haystack = mb_strtolower(implode('|', [
                (string) ($order['payer_name'] ?? ''), (string) ($order['user_id'] ?? ''),
                (string) ($order['ref_id'] ?? ''), (string) ($order['authority'] ?? ''),
                (string) ($order['id'] ?? ''), (string) ($extra['bot_offer_title'] ?? ''),
            ]), 'UTF-8');
            if (!str_contains($haystack, $query)) {
                continue;
            }
        }
        $filtered[] = dent_bot_payment_order_payload($order, true);
    }
    $total = count($filtered);
    return [
        'success' => true, 'contractVersion' => 'bot-commerce-v2',
        'items' => array_slice($filtered, $page * $limit, $limit),
        'page' => $page, 'limit' => $limit, 'total' => $total,
    ];
}

function dent_bot_payment_transaction(array $owner, array $payload): array
{
    dent_bot_payment_require_owner($owner);
    dent_bot_payment_require_v2($payload);
    $orderId = max(0, (int) ($payload['orderId'] ?? 0));
    if ($orderId <= 0) {
        dent_error('سفارش پیدا نشد.', 404, ['code' => 'PAYMENT_ORDER_NOT_FOUND']);
    }
    $store = payments_read_store();
    $index = payments_find_order_index_by_id($store, $orderId);
    $order = $index >= 0 && is_array($store['orders'][$index] ?? null) ? $store['orders'][$index] : null;
    if (!is_array($order) || !dent_bot_payment_is_offer_order($order)) {
        dent_error('سفارش پیدا نشد.', 404, ['code' => 'PAYMENT_ORDER_NOT_FOUND']);
    }
    return [
        'success' => true,
        'contractVersion' => 'bot-commerce-v2',
        'order' => dent_bot_payment_order_payload($order, true),
    ];
}

function dent_bot_payment_update_transaction_status(array $owner, array $payload): array
{
    dent_bot_payment_require_owner($owner);
    dent_bot_payment_require_v2($payload);
    $orderId = max(0, (int) ($payload['orderId'] ?? 0));
    $status = trim((string) ($payload['status'] ?? ''));
    $note = dent_clean_text((string) ($payload['note'] ?? ''), 240);
    if ($orderId <= 0 || !in_array($status, [
        PAYMENTS_ORDER_STATUS_PENDING,
        PAYMENTS_ORDER_STATUS_FAILED,
        PAYMENTS_ORDER_STATUS_CANCELED,
        PAYMENTS_ORDER_STATUS_EXPIRED,
    ], true)) {
        dent_error('تغییر وضعیت معتبر نیست.', 422, ['code' => 'PAYMENT_STATUS_INVALID']);
    }
    if (mb_strlen($note, 'UTF-8') < 3) {
        dent_error('دلیل تغییر وضعیت را کوتاه و روشن بنویسید.', 422, ['code' => 'PAYMENT_STATUS_NOTE_REQUIRED']);
    }
    $actor = dent_normalize_student_number((string) ($owner['studentNumber'] ?? ''));
    $result = payments_with_store_lock(static function (array &$store) use ($orderId, $status, $note, $actor): array {
        $index = payments_find_order_index_by_id($store, $orderId);
        if ($index < 0 || !is_array($store['orders'][$index] ?? null)
            || !dent_bot_payment_is_offer_order($store['orders'][$index])) {
            return ['found' => false];
        }
        $order = $store['orders'][$index];
        $previous = (string) ($order['status'] ?? PAYMENTS_ORDER_STATUS_PENDING);
        if ($previous === PAYMENTS_ORDER_STATUS_SUCCESS) {
            dent_error('پرداخت تأییدشده با تغییر دستی وضعیت دست‌کاری نمی‌شود.', 409, ['code' => 'VERIFIED_PAYMENT_IMMUTABLE']);
        }
        $changedAt = dent_iso_now();
        $snapshot = is_array($order['gateway_response_snapshot'] ?? null) ? $order['gateway_response_snapshot'] : [];
        $history = is_array($snapshot['bot_owner_status_history'] ?? null) ? $snapshot['bot_owner_status_history'] : [];
        $history[] = [
            'actor' => $actor,
            'at' => $changedAt,
            'previousStatus' => $previous,
            'newStatus' => $status,
            'note' => $note,
            'source' => 'bot-owner-manual',
        ];
        $snapshot['bot_owner_status_history'] = array_slice($history, -40);
        $order['status'] = $status;
        $order['updated_at'] = $changedAt;
        $order['gateway_response_snapshot'] = $snapshot;
        $store['orders'][$index] = $order;
        return ['found' => true, 'order' => $order, 'previousStatus' => $previous];
    });
    if (empty($result['found']) || !is_array($result['order'] ?? null)) {
        dent_error('سفارش پیدا نشد.', 404, ['code' => 'PAYMENT_ORDER_NOT_FOUND']);
    }
    return [
        'success' => true,
        'contractVersion' => 'bot-commerce-v2',
        'manual' => true,
        'previousStatus' => (string) ($result['previousStatus'] ?? ''),
        'order' => dent_bot_payment_order_payload($result['order'], true),
    ];
}

function dent_bot_payment_directory(array $owner, string $platform, array $payload): array
{
    dent_bot_payment_require_owner($owner);
    dent_bot_payment_require_v2($payload);
    $query = mb_strtolower(dent_clean_text((string) ($payload['query'] ?? ''), 120), 'UTF-8');
    $limit = max(1, min(500, (int) ($payload['limit'] ?? 100)));
    $links = dent_bot_store_read(static fn(array $store): array => ['links' => $store['links'] ?? []]);
    $byStudent = [];
    foreach ($links['links'] ?? [] as $identityHash => $link) {
        if (!is_array($link) || (string) ($link['platform'] ?? '') !== $platform || !dent_bot_link_auth_complete($link)) {
            continue;
        }
        $student = dent_normalize_student_number((string) ($link['studentNumber'] ?? ''));
        if ($student === '') {
            continue;
        }
        $platformId = dent_decrypt_secret_text($link['platformUserIdEncrypted'] ?? null);
        if (preg_match('/^[0-9]{1,24}$/', $platformId) === 1) {
            $byStudent[$student] = $platformId;
        }
    }
    $items = [];
    foreach (dent_list_public_users() as $user) {
        $student = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
        $name = (string) ($user['name'] ?? '');
        $cohort = (string) ($user['cohortKey'] ?? '');
        if ($query !== '' && !str_contains(mb_strtolower($name . '|' . $student, 'UTF-8'), $query)) {
            continue;
        }
        $items[] = [
            'name' => $name, 'studentNumber' => $student, 'cohortKey' => $cohort,
            'role' => (string) ($user['role'] ?? 'student'),
            'platformUserId' => (string) ($byStudent[$student] ?? ''),
        ];
        if (count($items) >= $limit) {
            break;
        }
    }
    return ['success' => true, 'contractVersion' => 'bot-commerce-v2', 'items' => $items];
}
