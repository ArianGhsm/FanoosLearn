<?php

declare(strict_types=1);

namespace Fanoos\Platform\Onboarding;

use Closure;
use Fanoos\Platform\Support\JsonLogger;

/**
 * Sends the onboarding OTP through FarazSMS (IranPayamak's pattern endpoint).
 *
 * Ported from the legacy dent_sms_send_pattern: the same endpoint, payload
 * shape, three attempts on transport or 5xx failure, and the same reading of
 * the response -- status "success" means the provider *accepted* the request,
 * not that the handset received it.
 *
 * The code goes out as a pattern variable, never as free text: a pattern is
 * the provider's pre-approved template, which is what lets an OTP through the
 * operator's content filtering quickly. When a domain is configured it is
 * also sent as the WebOTP line ("@domain #code") so a phone browser can offer
 * to fill the code in by itself.
 */
final class FarazSmsGateway implements SmsGateway
{
    private const ENDPOINT = 'https://api.iranpayamak.com/ws/v1/sms/pattern';
    private const ATTEMPTS = 3;

    /** @var Closure(string, array<int, string>, string): array{status:int, body:string, error:string} */
    private readonly Closure $transport;

    /**
     * @param (Closure(string, array<int, string>, string): array{status:int, body:string, error:string})|null $transport
     *        replaces the HTTP call in tests
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $patternCode,
        private readonly string $senderLine,
        private readonly string $codeParam = 'code',
        private readonly string $domain = '',
        ?Closure $transport = null,
    ) {
        $this->transport = $transport ?? self::curlTransport(...);
    }

    public function send(string $phoneNumber, string $code): array
    {
        $recipient = self::recipient($phoneNumber);
        if ($recipient === '') {
            return ['success' => false, 'message' => 'شماره موبایل مقصد نامعتبر است.'];
        }

        $attributes = [$this->codeParam => $code];
        if ($this->domain !== '') {
            $webOtp = '@' . $this->domain . ' #' . $code;
            $attributes['domain'] = $this->domain;
            $attributes['webotp'] = $webOtp;
            $attributes['webOtpLine'] = $webOtp;
        }
        $payload = json_encode([
            'code' => $this->patternCode,
            'recipient' => $recipient,
            'line_number' => $this->senderLine,
            'number_format' => 'english',
            'attributes' => $attributes,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Accept: application/json', 'Content-Type: application/json', 'Api-Key: ' . $this->apiKey];

        $response = ['status' => 0, 'body' => '', 'error' => ''];
        for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
            $response = ($this->transport)(self::ENDPOINT, $headers, (string) $payload);
            if ($response['error'] === '' && $response['status'] < 500) {
                break;
            }
            if ($attempt < self::ATTEMPTS) {
                usleep(250000 * $attempt);
            }
        }

        $decoded = $response['body'] !== '' ? json_decode($response['body'], true) : null;
        $accepted = $response['status'] >= 200 && $response['status'] < 300
            && is_array($decoded) && strtolower(trim((string) ($decoded['status'] ?? ''))) === 'success';

        JsonLogger::write($accepted ? 'info' : 'warning', $accepted ? 'sms.send_accepted' : 'sms.send_failed', [
            'provider' => 'farazsms',
            'phone' => substr($recipient, 0, 4) . '*****' . substr($recipient, -2),
            'http_status' => $response['status'],
            'transport_error' => $response['error'] !== '' ? substr($response['error'], 0, 160) : null,
            'provider_code' => is_array($decoded) ? substr((string) ($decoded['code'] ?? ''), 0, 40) : null,
            'provider_message' => is_array($decoded) ? substr(self::providerMessage($decoded), 0, 200) : null,
        ]);

        if ($accepted) {
            return ['success' => true];
        }
        if ($response['error'] !== '' || $response['status'] === 0 || $response['status'] >= 500) {
            return ['success' => false, 'message' => 'سرویس پیامک موقتاً در دسترس نیست. کمی بعد دوباره تلاش کنید.'];
        }

        return ['success' => false, 'message' => 'ارسال پیامک انجام نشد.'];
    }

    /** The provider takes 09xxxxxxxxx; the verification service hands over +989xxxxxxxxx. */
    public static function recipient(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '0098')) {
            $digits = '0' . substr($digits, 4);
        } elseif (str_starts_with($digits, '98')) {
            $digits = '0' . substr($digits, 2);
        } elseif (str_starts_with($digits, '9')) {
            $digits = '0' . $digits;
        }

        return strlen($digits) === 11 && str_starts_with($digits, '09') ? $digits : '';
    }

    /** @param array<string, mixed> $decoded */
    private static function providerMessage(array $decoded): string
    {
        $raw = $decoded['messages'] ?? ($decoded['message'] ?? '');
        if (is_string($raw)) {
            return $raw;
        }
        foreach ((array) $raw as $item) {
            foreach ((array) $item as $text) {
                if (is_string($text) && trim($text) !== '') {
                    return $text;
                }
            }
        }

        return '';
    }

    /**
     * @param array<int, string> $headers
     * @return array{status:int, body:string, error:string}
     */
    private static function curlTransport(string $url, array $headers, string $body): array
    {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
        ]);
        $response = curl_exec($handle);
        $result = [
            'status' => (int) curl_getinfo($handle, CURLINFO_HTTP_CODE),
            'body' => is_string($response) ? $response : '',
            'error' => curl_error($handle),
        ];
        curl_close($handle);

        return $result;
    }
}
