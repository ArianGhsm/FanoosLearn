<?php
declare(strict_types=1);

require_once __DIR__ . '/payments_gateway.php';
require_once __DIR__ . '/payment_handoff.php';

const DENT_VOICE_PAYMENT_CONTRACT = 'voice-payment-bridge-v1';
const DENT_VOICE_PAYMENT_MIN_RIALS = 20000;
const DENT_VOICE_PAYMENT_MAX_RIALS = 500000000;

function dent_voice_payment_callback_url(string $callbackToken, string $platform): string
{
    $configured = trim((string) getenv('DENT_VOICE_PAYMENT_CALLBACK_URL'));
    if ($configured === '') {
        $configured = 'https://dentistry1402tums.ir/api/voice_payment_return.php';
    }
    if (preg_match('#^https://dentistry1402tums\.ir/api/voice_payment_return\.php$#D', $configured) !== 1) {
        dent_error('تنظیم مسیر بازگشت پرداخت معتبر نیست.', 500, ['code' => 'VOICE_PAYMENT_CALLBACK_CONFIG_INVALID']);
    }
    return $configured . '?token=' . rawurlencode($callbackToken)
        . '&platform=' . rawurlencode($platform);
}

/**
 * Validate and normalize the deliberately small, stateless bridge contract.
 * No website account, order or wallet state is created by this bridge.
 */
function dent_voice_payment_contract_payload(array $payload, string $expectedAction): array
{
    if ((string) ($payload['contractVersion'] ?? '') !== DENT_VOICE_PAYMENT_CONTRACT) {
        dent_error('نسخه قرارداد پرداخت پشتیبانی نمی‌شود.', 409, ['code' => 'CONTRACT_MISMATCH']);
    }
    if ((string) ($payload['action'] ?? '') !== $expectedAction) {
        dent_error('عملیات پرداخت نامعتبر است.', 422, ['code' => 'VOICE_PAYMENT_ACTION_INVALID']);
    }
    $platform = (string) ($payload['platform'] ?? '');
    if (!in_array($platform, ['telegram', 'bale'], true)) {
        dent_error('بستر پرداخت نامعتبر است.', 422, ['code' => 'VOICE_PAYMENT_PLATFORM_INVALID']);
    }
    $platformUserId = trim((string) ($payload['platformUserId'] ?? ''));
    if (preg_match('/^[1-9][0-9]{0,18}$/D', $platformUserId) !== 1) {
        dent_error('شناسه کاربر پرداخت نامعتبر است.', 422, ['code' => 'VOICE_PAYMENT_USER_INVALID']);
    }
    $orderId = trim((string) ($payload['orderId'] ?? ''));
    $expectedPrefix = $platform === 'bale' ? 'VB' : 'VT';
    if (preg_match('/^' . $expectedPrefix . '-[0-9]{8}-[A-Za-z0-9_-]{8,24}$/D', $orderId) !== 1) {
        dent_error('شناسه سفارش پرداخت نامعتبر است.', 422, ['code' => 'VOICE_PAYMENT_ORDER_INVALID']);
    }
    $amountRials = filter_var($payload['amountRials'] ?? null, FILTER_VALIDATE_INT);
    if (!is_int($amountRials) || $amountRials < DENT_VOICE_PAYMENT_MIN_RIALS || $amountRials > DENT_VOICE_PAYMENT_MAX_RIALS) {
        dent_error('مبلغ سفارش پرداخت نامعتبر است.', 422, ['code' => 'VOICE_PAYMENT_AMOUNT_INVALID']);
    }

    return [
        'platform' => $platform,
        'platformUserId' => $platformUserId,
        'orderId' => $orderId,
        'amountRials' => $amountRials,
    ];
}

function dent_voice_payment_provider_response(array $gatewayResult): array
{
    $raw = is_array($gatewayResult['raw'] ?? null) ? $gatewayResult['raw'] : [];
    $response = is_array($raw['response'] ?? null) ? $raw['response'] : [];
    return is_array($response['json'] ?? null) ? $response['json'] : [];
}

function dent_voice_payment_result_code(array $gatewayResult): int
{
    $provider = dent_voice_payment_provider_response($gatewayResult);
    return abs((int) ($provider['result'] ?? 0));
}

function dent_voice_payment_verified_identity(array $gatewayResult): array
{
    $provider = dent_voice_payment_provider_response($gatewayResult);
    $amount = filter_var($provider['amount'] ?? null, FILTER_VALIDATE_INT);
    $orderIdRaw = $provider['orderId'] ?? ($provider['order_id'] ?? '');
    $orderId = is_scalar($orderIdRaw) ? trim((string) $orderIdRaw) : '';
    return [
        'amountRials' => is_int($amount) ? $amount : 0,
        'orderId' => $orderId,
    ];
}

function dent_voice_payment_start(array $payload): array
{
    $request = dent_voice_payment_contract_payload($payload, 'voicePaymentStartV1');
    $callbackToken = trim((string) ($payload['callbackToken'] ?? ''));
    if (preg_match('/^[A-Za-z0-9_-]{32}$/D', $callbackToken) !== 1) {
        dent_error('شناسه بازگشت پرداخت نامعتبر است.', 422, ['code' => 'VOICE_PAYMENT_CALLBACK_INVALID']);
    }
    $gateway = PAYMENTS_GATEWAY_ZIBAL;
    $result = payments_gateway_start_payment(
        $gateway,
        ['title' => 'افزایش موجودی ربات تبدیل ویس به متن'],
        [
            'amount' => $request['amountRials'],
            'public_token' => $request['orderId'],
        ],
        [
            'callbackUrl' => dent_voice_payment_callback_url($callbackToken, $request['platform']),
            'description' => 'افزایش موجودی ربات تبدیل ویس به متن',
            'orderId' => $request['orderId'],
        ]
    );
    if (empty($result['success'])) {
        dent_error('ساخت درخواست پرداخت انجام نشد.', 502, [
            'code' => 'VOICE_PAYMENT_START_FAILED',
            'resultCode' => dent_voice_payment_result_code($result),
        ]);
    }
    $trackId = trim((string) ($result['trackId'] ?? ''));
    $redirectUrl = trim((string) ($result['redirectUrl'] ?? ''));
    try {
        $redirectUrl = dent_zibal_handoff_url($redirectUrl, $trackId);
    } catch (InvalidArgumentException $error) {
        dent_error('پاسخ درگاه پرداخت معتبر نبود.', 502, ['code' => 'VOICE_PAYMENT_START_RESPONSE_INVALID']);
    }
    return [
        'success' => true,
        'contractVersion' => DENT_VOICE_PAYMENT_CONTRACT,
        'gateway' => PAYMENTS_GATEWAY_ZIBAL,
        'orderId' => $request['orderId'],
        'amountRials' => $request['amountRials'],
        'trackId' => $trackId,
        'redirectUrl' => $redirectUrl,
        'resultCode' => dent_voice_payment_result_code($result),
    ];
}

function dent_voice_payment_verify(array $payload): array
{
    $request = dent_voice_payment_contract_payload($payload, 'voicePaymentVerifyV1');
    $trackId = trim((string) ($payload['trackId'] ?? ''));
    if (preg_match('/^[A-Za-z0-9_-]{1,120}$/D', $trackId) !== 1) {
        dent_error('شناسه پیگیری پرداخت نامعتبر است.', 422, ['code' => 'VOICE_PAYMENT_TRACK_INVALID']);
    }
    $result = payments_gateway_verify_payment(
        PAYMENTS_GATEWAY_ZIBAL,
        [
            'amount' => $request['amountRials'],
            'authority' => $trackId,
            'ref_id' => '',
        ],
        ['trackId' => $trackId]
    );
    $verified = !empty($result['success']) && !empty($result['verified']);
    if ($verified) {
        $identity = dent_voice_payment_verified_identity($result);
        if ($identity['amountRials'] !== $request['amountRials'] || !hash_equals($request['orderId'], $identity['orderId'])) {
            dent_error('مشخصات تراکنش با سفارش یکسان نیست.', 409, ['code' => 'VOICE_PAYMENT_IDENTITY_MISMATCH']);
        }
    }
    return [
        'success' => true,
        'contractVersion' => DENT_VOICE_PAYMENT_CONTRACT,
        'gateway' => PAYMENTS_GATEWAY_ZIBAL,
        'orderId' => $request['orderId'],
        'amountRials' => $request['amountRials'],
        'trackId' => $trackId,
        'verified' => $verified,
        'status' => dent_clean_text((string) ($result['status'] ?? 'failed'), 24),
        'refId' => dent_clean_text((string) ($result['refId'] ?? ''), 120),
        'resultCode' => dent_voice_payment_result_code($result),
    ];
}
