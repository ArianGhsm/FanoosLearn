<?php
declare(strict_types=1);

require_once __DIR__ . '/payments_store.php';

if (!defined('PAYMENTS_GATEWAY_ZARINPAL')) {
    define('PAYMENTS_GATEWAY_ZARINPAL', 'zarinpal');
}

if (!defined('PAYMENTS_GATEWAY_ZIBAL')) {
    define('PAYMENTS_GATEWAY_ZIBAL', 'zibal');
}

if (!defined('PAYMENTS_GATEWAY_MOCK')) {
    define('PAYMENTS_GATEWAY_MOCK', 'mock');
}

function payments_gateway_default(): string
{
    $managedDefault = payments_gateway_managed_default_key();
    if ($managedDefault !== '') {
        return $managedDefault;
    }

    $configured = payments_gateway_clean((string) getenv('DENT_PAYMENT_GATEWAY'));
    if ($configured !== '') {
        return $configured;
    }

    return PAYMENTS_GATEWAY_MOCK;
}

function payments_gateway_clean(string $value): string
{
    return payments_gateway_key_clean($value);
}

function payments_gateway_label(string $gateway): string
{
    $record = payments_gateway_resolve_record($gateway);
    if ($record !== null) {
        return (string) ($record['provider_label'] ?? payments_gateway_provider_label_from_type((string) ($record['provider'] ?? '')));
    }

    return payments_gateway_provider_label_from_type($gateway);
}

function payments_gateway_is_configured(string $gateway): bool
{
    $record = payments_gateway_resolve_record($gateway);
    if ($record !== null) {
        return payments_gateway_is_record_configured($record);
    }

    $provider = payments_gateway_provider_clean($gateway);
    if ($provider === PAYMENTS_GATEWAY_ZIBAL) {
        return payments_zibal_merchant_id([]) !== '';
    }
    if ($provider === PAYMENTS_GATEWAY_ZARINPAL) {
        return payments_zarinpal_merchant_id([]) !== '';
    }
    if ($provider === PAYMENTS_GATEWAY_MOCK) {
        return true;
    }

    return false;
}

function payments_gateway_managed_store(): ?array
{
    if (!function_exists('payments_read_store')) {
        return null;
    }

    $store = payments_read_store();
    $settings = is_array($store['gatewaySettings'] ?? null) ? $store['gatewaySettings'] : [];
    if (!(bool) ($settings['managed'] ?? false)) {
        return null;
    }

    $gateways = is_array($store['gateways'] ?? null) ? $store['gateways'] : [];
    if ($gateways === []) {
        return null;
    }

    return $store;
}

function payments_gateway_managed_default_key(): string
{
    $store = payments_gateway_managed_store();
    if ($store === null) {
        return '';
    }

    foreach ($store['gateways'] as $gateway) {
        if (!is_array($gateway)) {
            continue;
        }
        if ((bool) ($gateway['is_default'] ?? false) && (bool) ($gateway['is_enabled'] ?? false)) {
            return payments_gateway_clean((string) ($gateway['key'] ?? ''));
        }
    }

    foreach ($store['gateways'] as $gateway) {
        if (!is_array($gateway)) {
            continue;
        }
        if ((bool) ($gateway['is_enabled'] ?? false)) {
            return payments_gateway_clean((string) ($gateway['key'] ?? ''));
        }
    }

    return '';
}

function payments_gateway_resolve_record(string $gateway): ?array
{
    $key = payments_gateway_clean($gateway);
    if ($key === '') {
        return null;
    }

    $store = payments_gateway_managed_store();
    if ($store !== null) {
        foreach ($store['gateways'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if ((string) ($entry['key'] ?? '') === $key) {
                return $entry;
            }
        }
        return null;
    }

    return payments_gateway_builtin_record($key);
}

function payments_gateway_builtin_record(string $key): ?array
{
    $provider = payments_gateway_provider_clean($key);
    if ($provider === '') {
        return null;
    }

    if ($provider === PAYMENTS_GATEWAY_ZIBAL) {
        return [
            'id' => 0,
            'key' => PAYMENTS_GATEWAY_ZIBAL,
            'provider' => PAYMENTS_GATEWAY_ZIBAL,
            'label' => 'پرداخت آنلاین',
            'provider_label' => 'درگاه زیبال',
            'icon' => 'Z',
            'merchant_id' => payments_zibal_merchant_id([]),
            'api_key' => '',
            'request_url' => trim((string) getenv('DENT_PAYMENT_ZIBAL_REQUEST_URL')),
            'verify_url' => trim((string) getenv('DENT_PAYMENT_ZIBAL_VERIFY_URL')),
            'start_url' => trim((string) getenv('DENT_PAYMENT_ZIBAL_START_URL')),
            'is_enabled' => payments_zibal_merchant_id([]) !== '',
            'is_default' => payments_gateway_clean((string) getenv('DENT_PAYMENT_GATEWAY')) === PAYMENTS_GATEWAY_ZIBAL,
        ];
    }

    if ($provider === PAYMENTS_GATEWAY_ZARINPAL) {
        return [
            'id' => 0,
            'key' => PAYMENTS_GATEWAY_ZARINPAL,
            'provider' => PAYMENTS_GATEWAY_ZARINPAL,
            'label' => 'پرداخت آنلاین',
            'provider_label' => 'درگاه زرین‌پال',
            'icon' => 'ZP',
            'merchant_id' => payments_zarinpal_merchant_id([]),
            'api_key' => '',
            'request_url' => trim((string) getenv('DENT_PAYMENT_ZARINPAL_REQUEST_URL')),
            'verify_url' => trim((string) getenv('DENT_PAYMENT_ZARINPAL_VERIFY_URL')),
            'start_url' => trim((string) getenv('DENT_PAYMENT_ZARINPAL_START_URL')),
            'is_enabled' => payments_zarinpal_merchant_id([]) !== '',
            'is_default' => payments_gateway_clean((string) getenv('DENT_PAYMENT_GATEWAY')) === PAYMENTS_GATEWAY_ZARINPAL,
        ];
    }

    return [
        'id' => 0,
        'key' => PAYMENTS_GATEWAY_MOCK,
        'provider' => PAYMENTS_GATEWAY_MOCK,
        'label' => 'پرداخت آزمایشی',
        'provider_label' => 'فقط برای تست',
        'icon' => 'T',
        'merchant_id' => '',
        'api_key' => '',
        'request_url' => '',
        'verify_url' => '',
        'start_url' => '',
        'is_enabled' => true,
        'is_default' => payments_gateway_clean((string) getenv('DENT_PAYMENT_GATEWAY')) === PAYMENTS_GATEWAY_MOCK,
    ];
}

function payments_gateway_checkout_catalog(bool $includeMock = false): array
{
    $managed = payments_gateway_managed_store();
    if ($managed !== null) {
        $result = [];
        foreach ($managed['gateways'] as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $provider = payments_gateway_provider_clean((string) ($entry['provider'] ?? ''));
            if ($provider === '' || (!$includeMock && $provider === PAYMENTS_GATEWAY_MOCK)) {
                continue;
            }
            $key = payments_gateway_clean((string) ($entry['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $result[] = [
                'key' => $key,
                'label' => (string) ($entry['label'] ?? payments_gateway_public_label($provider)),
                'provider' => (string) ($entry['provider_label'] ?? payments_gateway_provider_label_from_type($provider)),
                'providerType' => $provider,
                'icon' => dent_clean_text((string) ($entry['icon'] ?? ''), 8),
                'priority' => (int) $index,
                'isEnabled' => (bool) ($entry['is_enabled'] ?? false) && payments_gateway_is_record_configured($entry),
                'isDefault' => (bool) ($entry['is_default'] ?? false),
            ];
        }

        $fallbackDefault = '';
        $hasDefault = false;
        foreach ($result as $index => $entry) {
            if ((bool) ($entry['isEnabled'] ?? false) && $fallbackDefault === '') {
                $fallbackDefault = (string) ($entry['key'] ?? '');
            }
            if ((bool) ($entry['isEnabled'] ?? false) && (bool) ($entry['isDefault'] ?? false) && !$hasDefault) {
                $hasDefault = true;
                continue;
            }
            $result[$index]['isDefault'] = false;
        }
        if (!$hasDefault && $fallbackDefault !== '') {
            foreach ($result as $index => $entry) {
                if ((string) ($entry['key'] ?? '') === $fallbackDefault) {
                    $result[$index]['isDefault'] = true;
                    break;
                }
            }
        }

        return $result;
    }

    $catalog = [
        [
            'key' => PAYMENTS_GATEWAY_ZIBAL,
            'label' => 'پرداخت آنلاین',
            'provider' => 'درگاه زیبال',
            'icon' => 'Z',
            'priority' => 10,
        ],
        [
            'key' => PAYMENTS_GATEWAY_ZARINPAL,
            'label' => 'پرداخت آنلاین',
            'provider' => 'درگاه زرین‌پال',
            'icon' => 'ZP',
            'priority' => 20,
        ],
    ];

    if ($includeMock) {
        $catalog[] = [
            'key' => PAYMENTS_GATEWAY_MOCK,
            'label' => 'پرداخت آزمایشی',
            'provider' => 'فقط برای تست',
            'icon' => 'T',
            'priority' => 100,
        ];
    }

    $defaultGateway = payments_gateway_default();
    $fallbackDefault = '';
    $result = [];
    foreach ($catalog as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $key = payments_gateway_clean((string) ($entry['key'] ?? ''));
        if ($key === '') {
            continue;
        }
        $isEnabled = payments_gateway_is_configured($key);
        if ($fallbackDefault === '' && $isEnabled) {
            $fallbackDefault = $key;
        }

        $result[] = [
            'key' => $key,
            'label' => (string) ($entry['label'] ?? payments_gateway_label($key)),
            'provider' => (string) ($entry['provider'] ?? payments_gateway_label($key)),
            'icon' => dent_clean_text((string) ($entry['icon'] ?? ''), 8),
            'priority' => (int) ($entry['priority'] ?? 999),
            'isEnabled' => $isEnabled,
            'isDefault' => $isEnabled && $key === $defaultGateway,
        ];
    }

    if ($fallbackDefault !== '' && !in_array($defaultGateway, array_column($result, 'key'), true)) {
        $defaultGateway = $fallbackDefault;
    }

    foreach ($result as $index => $entry) {
        $key = (string) ($entry['key'] ?? '');
        $result[$index]['isDefault'] = (bool) ($entry['isEnabled'] ?? false) && $key === $defaultGateway;
    }

    usort($result, static function (array $left, array $right): int {
        return (int) ($left['priority'] ?? 999) <=> (int) ($right['priority'] ?? 999);
    });

    return $result;
}

function payments_gateway_enabled_checkout_keys(bool $includeMock = false): array
{
    $keys = [];
    foreach (payments_gateway_checkout_catalog($includeMock) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if (!(bool) ($entry['isEnabled'] ?? false)) {
            continue;
        }
        $key = payments_gateway_clean((string) ($entry['key'] ?? ''));
        if ($key !== '' && !in_array($key, $keys, true)) {
            $keys[] = $key;
        }
    }

    return $keys;
}

function payments_gateway_default_enabled_checkout(bool $includeMock = false): string
{
    $preferred = payments_gateway_clean(payments_gateway_default());
    $enabled = payments_gateway_enabled_checkout_keys($includeMock);
    if ($preferred !== '' && in_array($preferred, $enabled, true)) {
        return $preferred;
    }

    return $enabled[0] ?? '';
}

function payments_gateway_http_post_json(string $url, array $payload, int $timeoutSeconds = 20): array
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        return [
            'ok' => false,
            'statusCode' => 0,
            'raw' => null,
            'json' => null,
            'error' => 'json-encode-failed',
        ];
    }

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Content-Length: ' . strlen($body),
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return [
                'ok' => false,
                'statusCode' => 0,
                'raw' => null,
                'json' => null,
                'error' => 'curl-init-failed',
            ];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => max(3, $timeoutSeconds),
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            return [
                'ok' => false,
                'statusCode' => $statusCode,
                'raw' => null,
                'json' => null,
                'error' => $curlError !== '' ? $curlError : 'curl-request-failed',
            ];
        }

        $decoded = json_decode((string) $raw, true);
        return [
            'ok' => $statusCode >= 200 && $statusCode < 300,
            'statusCode' => $statusCode,
            'raw' => $raw,
            'json' => is_array($decoded) ? $decoded : null,
            'error' => is_array($decoded) ? '' : 'invalid-json-response',
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => max(3, $timeoutSeconds),
            'ignore_errors' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    $statusCode = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $headerLine) {
            if (preg_match('/\s(\d{3})\s/u', (string) $headerLine, $matches) === 1) {
                $statusCode = (int) $matches[1];
                break;
            }
        }
    }

    if ($raw === false) {
        return [
            'ok' => false,
            'statusCode' => $statusCode,
            'raw' => null,
            'json' => null,
            'error' => 'http-request-failed',
        ];
    }

    $decoded = json_decode((string) $raw, true);
    return [
        'ok' => $statusCode >= 200 && $statusCode < 300,
        'statusCode' => $statusCode,
        'raw' => $raw,
        'json' => is_array($decoded) ? $decoded : null,
        'error' => is_array($decoded) ? '' : 'invalid-json-response',
    ];
}

function payments_gateway_start_payment(string $gateway, array $item, array $order, array $context = []): array
{
    $gateway = payments_gateway_clean($gateway);
    $record = $gateway !== '' ? payments_gateway_resolve_record($gateway) : null;
    if ($gateway === '' || $record === null || !(bool) ($record['is_enabled'] ?? false) || !payments_gateway_is_record_configured($record)) {
        return [
            'success' => false,
            'gateway' => $gateway,
            'error' => 'درگاه پرداخت انتخاب‌شده معتبر یا فعال نیست.',
            'raw' => null,
        ];
    }

    $context['gatewayKey'] = $gateway;
    $context['gatewayConfig'] = $record;
    $provider = payments_gateway_provider_clean((string) ($record['provider'] ?? $gateway));

    if ($provider === PAYMENTS_GATEWAY_ZIBAL) {
        return payments_zibal_start_payment($item, $order, $context);
    }

    if ($provider === PAYMENTS_GATEWAY_ZARINPAL) {
        return payments_zarinpal_start_payment($item, $order, $context);
    }

    return payments_mock_start_payment($item, $order, $context);
}

function payments_gateway_verify_payment(string $gateway, array $order, array $context = []): array
{
    $gateway = payments_gateway_clean($gateway);
    $record = $gateway !== '' ? payments_gateway_resolve_record($gateway) : null;
    if ($gateway === '' || $record === null || !payments_gateway_is_record_configured($record)) {
        return [
            'success' => false,
            'gateway' => $gateway,
            'verified' => false,
            'status' => 'failed',
            'refId' => '',
            'error' => 'درگاه پرداخت سفارش معتبر نیست.',
            'raw' => null,
        ];
    }

    $context['gatewayKey'] = $gateway;
    $context['gatewayConfig'] = $record;
    $provider = payments_gateway_provider_clean((string) ($record['provider'] ?? $gateway));

    if ($provider === PAYMENTS_GATEWAY_ZIBAL) {
        return payments_zibal_verify_payment($order, $context);
    }

    if ($provider === PAYMENTS_GATEWAY_ZARINPAL) {
        return payments_zarinpal_verify_payment($order, $context);
    }

    return payments_mock_verify_payment($order, $context);
}

function payments_zibal_error_message(int $code): string
{
    $normalized = abs($code);
    if ($normalized === 100) {
        return 'با موفقیت تایید شد.';
    }
    if ($normalized === 102) {
        return 'merchant یافت نشد.';
    }
    if ($normalized === 103) {
        return 'merchant غیرفعال است.';
    }
    if ($normalized === 104) {
        return 'merchant نامعتبر است.';
    }
    if ($normalized === 105) {
        return 'amount باید بیشتر از 1000 ریال باشد.';
    }
    if ($normalized === 106) {
        return 'callbackUrl نامعتبر است (باید با http یا https شروع شود).';
    }
    if ($normalized === 113) {
        return 'amount بیشتر از سقف مجاز تراکنش است.';
    }
    if ($normalized === 201) {
        return 'تراکنش قبلا تایید شده است.';
    }
    if ($normalized === 202) {
        return 'سفارش پرداخت نشده یا ناموفق بوده است.';
    }
    if ($normalized === 203) {
        return 'trackId نامعتبر است.';
    }

    return 'خطا در ارتباط با زیبال.';
}

function payments_zibal_merchant_id(array $config = []): string
{
    $managed = payments_gateway_record_credential($config);
    if ($managed !== '') {
        return $managed;
    }

    $keys = [
        'DENT_PAYMENT_ZIBAL_MERCHANT',
        'DENT_PAYMENT_ZIBAL_TOKEN',
        'DENT_PAYMENT_ZIBAL_MERCHANT_ID',
    ];

    foreach ($keys as $key) {
        $value = trim((string) getenv($key));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function payments_zibal_start_payment(array $item, array $order, array $context = []): array
{
    $config = is_array($context['gatewayConfig'] ?? null) ? $context['gatewayConfig'] : [];
    $merchant = payments_zibal_merchant_id($config);
    if ($merchant === '') {
        return [
            'success' => false,
            'gateway' => PAYMENTS_GATEWAY_ZIBAL,
            'error' => 'تنظیمات زیبال کامل نیست (merchant/token).',
            'raw' => null,
        ];
    }

    $requestUrl = dent_clean_text((string) ($config['request_url'] ?? ''), 420);
    if ($requestUrl === '') {
        $requestUrl = trim((string) getenv('DENT_PAYMENT_ZIBAL_REQUEST_URL'));
    }
    if ($requestUrl === '') {
        $requestUrl = 'https://gateway.zibal.ir/v1/request';
    }

    $description = dent_clean_text(
        (string) ($context['description'] ?? ('پرداخت بابت ' . (string) ($item['title'] ?? 'آیتم پرداخت'))),
        200
    );
    if ($description === '') {
        $description = 'پرداخت آنلاین';
    }

    $amount = max(0, (int) ($order['amount'] ?? 0));
    if ($amount <= 0) {
        return [
            'success' => false,
            'gateway' => PAYMENTS_GATEWAY_ZIBAL,
            'error' => 'مبلغ سفارش معتبر نیست.',
            'raw' => null,
        ];
    }

    $payload = [
        'merchant' => $merchant,
        'amount' => $amount,
        'callbackUrl' => (string) ($context['callbackUrl'] ?? ''),
        'description' => $description,
    ];

    $mobile = trim((string) ($context['mobile'] ?? ''));
    if ($mobile !== '') {
        $payload['mobile'] = $mobile;
    }

    $orderId = trim((string) ($context['orderId'] ?? ($order['public_token'] ?? '')));
    if ($orderId !== '') {
        $payload['orderId'] = $orderId;
    }

    $httpResponse = payments_gateway_http_post_json($requestUrl, $payload, 15);
    $responsePayload = is_array($httpResponse['json'] ?? null) ? $httpResponse['json'] : [];
    $resultCode = (int) ($responsePayload['result'] ?? 0);
    $resultCode = abs($resultCode);
    $trackIdRaw = $responsePayload['trackId'] ?? '';
    $trackId = dent_clean_text(is_scalar($trackIdRaw) ? (string) $trackIdRaw : '', 120);

    if ($trackId === '' || $resultCode !== 100) {
        $errorMessage = trim((string) ($responsePayload['message'] ?? ''));
        if ($errorMessage === '') {
            $errorMessage = payments_zibal_error_message($resultCode);
        }

        return [
            'success' => false,
            'gateway' => PAYMENTS_GATEWAY_ZIBAL,
            'error' => $errorMessage,
            'raw' => [
                'request' => $payload,
                'response' => $httpResponse,
            ],
        ];
    }

    $startUrl = dent_clean_text((string) ($config['start_url'] ?? ''), 420);
    if ($startUrl === '') {
        $startUrl = trim((string) getenv('DENT_PAYMENT_ZIBAL_START_URL'));
    }
    if ($startUrl === '') {
        $startUrl = 'https://gateway.zibal.ir/start/';
    }
    $startUrl = rtrim($startUrl, '/') . '/';

    return [
        'success' => true,
        'gateway' => PAYMENTS_GATEWAY_ZIBAL,
        'authority' => $trackId,
        'trackId' => $trackId,
        'redirectUrl' => $startUrl . rawurlencode($trackId),
        'raw' => [
            'request' => $payload,
            'response' => $httpResponse,
        ],
    ];
}

function payments_zibal_verify_payment(array $order, array $context = []): array
{
    $config = is_array($context['gatewayConfig'] ?? null) ? $context['gatewayConfig'] : [];
    $merchant = payments_zibal_merchant_id($config);
    if ($merchant === '') {
        return [
            'success' => false,
            'gateway' => PAYMENTS_GATEWAY_ZIBAL,
            'verified' => false,
            'status' => 'failed',
            'refId' => '',
            'error' => 'تنظیمات زیبال کامل نیست (merchant/token).',
            'raw' => null,
        ];
    }

    $trackId = trim((string) ($context['trackId'] ?? ($context['authority'] ?? ($order['authority'] ?? ''))));
    if ($trackId === '') {
        return [
            'success' => false,
            'gateway' => PAYMENTS_GATEWAY_ZIBAL,
            'verified' => false,
            'status' => 'failed',
            'refId' => '',
            'error' => 'trackId سفارش برای verify معتبر نیست.',
            'raw' => null,
        ];
    }

    $verifyUrl = dent_clean_text((string) ($config['verify_url'] ?? ''), 420);
    if ($verifyUrl === '') {
        $verifyUrl = trim((string) getenv('DENT_PAYMENT_ZIBAL_VERIFY_URL'));
    }
    if ($verifyUrl === '') {
        $verifyUrl = 'https://gateway.zibal.ir/v1/verify';
    }

    $payload = [
        'merchant' => $merchant,
        'trackId' => $trackId,
    ];

    $httpResponse = payments_gateway_http_post_json($verifyUrl, $payload, 15);
    $responsePayload = is_array($httpResponse['json'] ?? null) ? $httpResponse['json'] : [];
    $resultCode = abs((int) ($responsePayload['result'] ?? 0));
    $refIdRaw = $responsePayload['refNumber'] ?? ($responsePayload['ref_number'] ?? '');
    $refId = dent_clean_text(is_scalar($refIdRaw) ? (string) $refIdRaw : '', 120);

    if (in_array($resultCode, [100, 201], true)) {
        if ($refId === '') {
            $refId = dent_clean_text((string) ($order['ref_id'] ?? ''), 120);
        }
        if ($refId === '') {
            $refId = $trackId;
        }

        return [
            'success' => true,
            'gateway' => PAYMENTS_GATEWAY_ZIBAL,
            'verified' => true,
            'status' => 'success',
            'refId' => $refId,
            'error' => '',
            'raw' => [
                'request' => $payload,
                'response' => $httpResponse,
            ],
        ];
    }

    $status = PAYMENTS_ORDER_STATUS_FAILED;
    $zibalStatusCode = (int) ($responsePayload['status'] ?? 0);
    if (in_array($zibalStatusCode, [3], true)) {
        $status = PAYMENTS_ORDER_STATUS_CANCELED;
    }

    $errorMessage = trim((string) ($responsePayload['message'] ?? ''));
    if ($errorMessage === '') {
        $errorMessage = payments_zibal_error_message($resultCode);
    }

    return [
        'success' => false,
        'gateway' => PAYMENTS_GATEWAY_ZIBAL,
        'verified' => false,
        'status' => $status,
        'refId' => $refId,
        'error' => $errorMessage,
        'raw' => [
            'request' => $payload,
            'response' => $httpResponse,
        ],
    ];
}

function payments_zarinpal_merchant_id(array $config = []): string
{
    $managed = payments_gateway_record_credential($config);
    if ($managed !== '') {
        return $managed;
    }

    return trim((string) getenv('DENT_PAYMENT_ZARINPAL_MERCHANT_ID'));
}

function payments_zarinpal_start_payment(array $item, array $order, array $context = []): array
{
    $config = is_array($context['gatewayConfig'] ?? null) ? $context['gatewayConfig'] : [];
    $merchantId = payments_zarinpal_merchant_id($config);
    if ($merchantId === '') {
        return [
            'success' => false,
            'gateway' => PAYMENTS_GATEWAY_ZARINPAL,
            'error' => 'تنظیمات زرین‌پال کامل نیست (merchant_id).',
            'raw' => null,
        ];
    }

    $requestUrl = dent_clean_text((string) ($config['request_url'] ?? ''), 420);
    if ($requestUrl === '') {
        $requestUrl = trim((string) getenv('DENT_PAYMENT_ZARINPAL_REQUEST_URL'));
    }
    if ($requestUrl === '') {
        $requestUrl = 'https://payment.zarinpal.com/pg/v4/payment/request.json';
    }

    $description = dent_clean_text(
        (string) ($context['description'] ?? ('پرداخت بابت ' . (string) ($item['title'] ?? 'آیتم پرداخت'))),
        200
    );
    if ($description === '') {
        $description = 'پرداخت آنلاین';
    }

    $amount = max(0, (int) ($order['amount'] ?? 0));
    if ($amount <= 0) {
        return [
            'success' => false,
            'gateway' => PAYMENTS_GATEWAY_ZARINPAL,
            'error' => 'مبلغ سفارش معتبر نیست.',
            'raw' => null,
        ];
    }

    $payload = [
        'merchant_id' => $merchantId,
        'amount' => $amount,
        'callback_url' => (string) ($context['callbackUrl'] ?? ''),
        'description' => $description,
    ];

    $metadata = [];
    $mobile = trim((string) ($context['mobile'] ?? ''));
    if ($mobile !== '') {
        $metadata['mobile'] = $mobile;
    }
    $email = trim((string) ($context['email'] ?? ''));
    if ($email !== '') {
        $metadata['email'] = $email;
    }
    if ($metadata !== []) {
        $payload['metadata'] = $metadata;
    }

    $httpResponse = payments_gateway_http_post_json($requestUrl, $payload, 15);
    $responsePayload = is_array($httpResponse['json'] ?? null) ? $httpResponse['json'] : [];
    $data = is_array($responsePayload['data'] ?? null) ? $responsePayload['data'] : [];
    $errors = is_array($responsePayload['errors'] ?? null) ? $responsePayload['errors'] : [];
    $authority = trim((string) ($data['authority'] ?? ''));
    $code = (int) ($data['code'] ?? ($errors['code'] ?? 0));

    if ($authority === '' || !in_array($code, [100], true)) {
        $errorMessage = trim((string) ($errors['message'] ?? ''));
        if ($errorMessage === '') {
            $errorMessage = 'درخواست پرداخت زرین‌پال پذیرفته نشد.';
        }

        return [
            'success' => false,
            'gateway' => PAYMENTS_GATEWAY_ZARINPAL,
            'error' => $errorMessage,
            'raw' => [
                'request' => $payload,
                'response' => $httpResponse,
            ],
        ];
    }

    $startUrl = dent_clean_text((string) ($config['start_url'] ?? ''), 420);
    if ($startUrl === '') {
        $startUrl = trim((string) getenv('DENT_PAYMENT_ZARINPAL_START_URL'));
    }
    if ($startUrl === '') {
        $startUrl = 'https://www.zarinpal.com/pg/StartPay/';
    }
    $startUrl = rtrim($startUrl, '/') . '/';

    return [
        'success' => true,
        'gateway' => PAYMENTS_GATEWAY_ZARINPAL,
        'authority' => $authority,
        'redirectUrl' => $startUrl . rawurlencode($authority),
        'raw' => [
            'request' => $payload,
            'response' => $httpResponse,
        ],
    ];
}

function payments_zarinpal_verify_payment(array $order, array $context = []): array
{
    $config = is_array($context['gatewayConfig'] ?? null) ? $context['gatewayConfig'] : [];
    $merchantId = payments_zarinpal_merchant_id($config);
    if ($merchantId === '') {
        return [
            'success' => false,
            'gateway' => PAYMENTS_GATEWAY_ZARINPAL,
            'verified' => false,
            'status' => 'failed',
            'refId' => '',
            'error' => 'تنظیمات زرین‌پال کامل نیست (merchant_id).',
            'raw' => null,
        ];
    }

    $authority = trim((string) ($context['authority'] ?? ($order['authority'] ?? '')));
    if ($authority === '') {
        return [
            'success' => false,
            'gateway' => PAYMENTS_GATEWAY_ZARINPAL,
            'verified' => false,
            'status' => 'failed',
            'refId' => '',
            'error' => 'authority سفارش برای verify معتبر نیست.',
            'raw' => null,
        ];
    }

    $verifyUrl = dent_clean_text((string) ($config['verify_url'] ?? ''), 420);
    if ($verifyUrl === '') {
        $verifyUrl = trim((string) getenv('DENT_PAYMENT_ZARINPAL_VERIFY_URL'));
    }
    if ($verifyUrl === '') {
        $verifyUrl = 'https://payment.zarinpal.com/pg/v4/payment/verify.json';
    }

    $amount = max(0, (int) ($order['amount'] ?? 0));
    $payload = [
        'merchant_id' => $merchantId,
        'amount' => $amount,
        'authority' => $authority,
    ];

    $httpResponse = payments_gateway_http_post_json($verifyUrl, $payload, 15);
    $responsePayload = is_array($httpResponse['json'] ?? null) ? $httpResponse['json'] : [];
    $data = is_array($responsePayload['data'] ?? null) ? $responsePayload['data'] : [];
    $errors = is_array($responsePayload['errors'] ?? null) ? $responsePayload['errors'] : [];
    $code = (int) ($data['code'] ?? ($errors['code'] ?? 0));
    $refId = trim((string) ($data['ref_id'] ?? ''));

    if (in_array($code, [100, 101], true) && $refId !== '') {
        return [
            'success' => true,
            'gateway' => PAYMENTS_GATEWAY_ZARINPAL,
            'verified' => true,
            'status' => 'success',
            'refId' => $refId,
            'error' => '',
            'raw' => [
                'request' => $payload,
                'response' => $httpResponse,
            ],
        ];
    }

    $errorMessage = trim((string) ($errors['message'] ?? ''));
    if ($errorMessage === '') {
        $errorMessage = 'verify زرین‌پال موفق نبود.';
    }

    return [
        'success' => false,
        'gateway' => PAYMENTS_GATEWAY_ZARINPAL,
        'verified' => false,
        'status' => 'failed',
        'refId' => $refId,
        'error' => $errorMessage,
        'raw' => [
            'request' => $payload,
            'response' => $httpResponse,
        ],
    ];
}

function payments_mock_start_payment(array $item, array $order, array $context = []): array
{
    $token = trim((string) ($order['public_token'] ?? ''));
    if ($token === '') {
        return [
            'success' => false,
            'gateway' => PAYMENTS_GATEWAY_MOCK,
            'error' => 'شناسه سفارش برای درگاه آزمایشی موجود نیست.',
            'raw' => null,
        ];
    }

    $authority = 'MOCK-' . strtoupper(substr(sha1($token . '|' . dent_iso_now()), 0, 16));
    $redirectUrl = '/api/payments_api.php?action=mockGateway'
        . '&orderToken=' . rawurlencode($token)
        . '&authority=' . rawurlencode($authority)
        . '&result=success';

    return [
        'success' => true,
        'gateway' => PAYMENTS_GATEWAY_MOCK,
        'authority' => $authority,
        'redirectUrl' => $redirectUrl,
        'raw' => [
            'request' => [
                'orderToken' => $token,
                'itemId' => (int) ($item['id'] ?? 0),
            ],
            'response' => [
                'result' => 'mock',
                'authority' => $authority,
            ],
        ],
    ];
}

function payments_mock_verify_payment(array $order, array $context = []): array
{
    $status = strtolower(trim((string) ($context['status'] ?? 'ok')));
    $authority = trim((string) ($context['authority'] ?? ($order['authority'] ?? '')));

    if (in_array($status, ['ok', 'success', 'approved'], true)) {
        $refId = 'MOCK-REF-' . strtoupper(substr(sha1((string) ($order['public_token'] ?? '') . '|' . dent_iso_now()), 0, 12));
        return [
            'success' => true,
            'gateway' => PAYMENTS_GATEWAY_MOCK,
            'verified' => true,
            'status' => 'success',
            'refId' => $refId,
            'error' => '',
            'raw' => [
                'authority' => $authority,
                'status' => $status,
            ],
        ];
    }

    if (in_array($status, ['cancel', 'canceled', 'cancelled', 'nok'], true)) {
        return [
            'success' => false,
            'gateway' => PAYMENTS_GATEWAY_MOCK,
            'verified' => false,
            'status' => 'canceled',
            'refId' => '',
            'error' => 'پرداخت توسط کاربر لغو شد.',
            'raw' => [
                'authority' => $authority,
                'status' => $status,
            ],
        ];
    }

    return [
        'success' => false,
        'gateway' => PAYMENTS_GATEWAY_MOCK,
        'verified' => false,
        'status' => 'failed',
        'refId' => '',
        'error' => 'پرداخت در درگاه آزمایشی ناموفق بود.',
        'raw' => [
            'authority' => $authority,
            'status' => $status,
        ],
    ];
}
