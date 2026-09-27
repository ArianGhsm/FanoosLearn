<?php

declare(strict_types=1);

namespace Fanoos\Platform\Commerce;

use Closure;
use Fanoos\Platform\Support\JsonLogger;
use RuntimeException;

/**
 * Zibal, the gateway the Dent1402 site already takes real payments through
 * (legacy payments_gateway.php: the same request/start/verify endpoints and
 * result codes -- 100 verified, 201 already verified).
 *
 * One deliberate difference from that source: a verification is only
 * believed if Zibal's own figure for the amount matches the order's. The
 * legacy verify trusted result 100 alone, which would accept a payment for a
 * cheaper order redirected to this one's callback.
 *
 * Amounts are Rials, the only currency Zibal takes (IRR).
 */
final class ZibalPaymentGateway implements PaymentGateway
{
    private const API = 'https://gateway.zibal.ir/v1';
    private const START = 'https://gateway.zibal.ir/start/';

    /** @var Closure(string, array<string, mixed>): array<string, mixed> */
    private readonly Closure $post;

    /**
     * @param string $returnUrl where Zibal sends the payer back; the callback token is appended
     * @param (Closure(string, array<string, mixed>): array<string, mixed>)|null $post replaces the HTTP call in tests
     */
    public function __construct(
        private readonly string $merchant,
        private readonly string $returnUrl,
        ?Closure $post = null,
    ) {
        if (trim($merchant) === '' || !str_starts_with($returnUrl, 'https://')) {
            throw new RuntimeException('Zibal needs a merchant and an https return URL.');
        }
        $this->post = $post ?? self::postJson(...);
    }

    public function key(): string
    {
        return 'zibal';
    }

    public function start(string $orderId, int $amountMinor, string $currency, string $callbackToken): array
    {
        $this->requireRial($currency, $amountMinor);
        $response = ($this->post)(self::API . '/request', [
            'merchant' => $this->merchant,
            'amount' => $amountMinor,
            'callbackUrl' => $this->callbackUrl($callbackToken),
            'description' => 'فانوس — سفارش ' . substr(str_replace('-', '', $orderId), -8),
            'orderId' => $orderId,
        ]);
        $trackId = (string) ($response['trackId'] ?? '');
        if ((int) ($response['result'] ?? 0) !== 100 || preg_match('/^[1-9][0-9]{0,19}$/', $trackId) !== 1) {
            JsonLogger::write('warning', 'payment.zibal.request_refused', [
                'result' => (int) ($response['result'] ?? 0),
                'provider_message' => substr((string) ($response['message'] ?? ''), 0, 200),
            ]);
            throw new RuntimeException('Zibal refused the payment request.');
        }

        return ['authority' => $trackId, 'redirect_url' => $this->redirectUrl($trackId, $callbackToken)];
    }

    public function redirectUrl(string $authority, string $callbackToken): string
    {
        return self::START . rawurlencode($authority);
    }

    public function verify(string $authority, int $amountMinor, string $currency, array $payload): array
    {
        if (preg_match('/^[1-9][0-9]{0,19}$/', $authority) !== 1 || strtoupper($currency) !== 'IRR') {
            return self::outcome(false, null, 'zibal_invalid_request');
        }
        $response = ($this->post)(self::API . '/verify', ['merchant' => $this->merchant, 'trackId' => $authority]);
        $result = (int) ($response['result'] ?? 0);
        if (!in_array($result, [100, 201], true)) {
            // 202 is "not paid or failed": the payer cancelled or the bank refused.
            return self::outcome(false, null, $result === 202 ? 'zibal_not_paid' : 'zibal_verify_' . $result);
        }
        // An already-verified answer (201) may omit the amount; ask again.
        $paid = $response['amount'] ?? null;
        if ($paid === null) {
            $paid = ($this->post)(self::API . '/inquiry', ['merchant' => $this->merchant, 'trackId' => $authority])['amount'] ?? null;
        }
        if (!is_numeric($paid) || (int) $paid !== $amountMinor) {
            JsonLogger::write('error', 'payment.zibal.amount_mismatch', ['expected' => $amountMinor, 'paid' => is_numeric($paid) ? (int) $paid : null]);
            return self::outcome(false, null, 'zibal_amount_mismatch');
        }
        $reference = (string) ($response['refNumber'] ?? '');

        return self::outcome(true, $reference !== '' ? substr($reference, 0, 120) : $authority, null);
    }

    public function reconcile(string $orderId, ?string $authority, int $amountMinor, string $currency): array
    {
        if ($authority === null || $authority === '') {
            return self::outcome(false, null, 'zibal_no_track_id');
        }

        return $this->verify($authority, $amountMinor, $currency, []);
    }

    private function callbackUrl(string $callbackToken): string
    {
        return $this->returnUrl . (str_contains($this->returnUrl, '?') ? '&' : '?') . 'token=' . rawurlencode($callbackToken);
    }

    private function requireRial(string $currency, int $amountMinor): void
    {
        // Zibal's own floor is 1,000 Rials.
        if (strtoupper($currency) !== 'IRR' || $amountMinor < 1000) {
            throw new RuntimeException('Zibal takes Rial amounts of at least 1,000.');
        }
    }

    /** @return array{verified:bool,reference:?string,failure_code:?string,payload:array<string,mixed>} */
    private static function outcome(bool $verified, ?string $reference, ?string $failure): array
    {
        return [
            'verified' => $verified,
            'reference' => $reference,
            'failure_code' => $failure,
            'payload' => ['adapter' => 'zibal', 'status' => $verified ? 'verified' : 'failed'],
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private static function postJson(string $url, array $body): array
    {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
        $raw = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if ($status < 200 || $status >= 500 || !is_array($decoded)) {
            throw new RuntimeException('Zibal did not answer.');
        }

        return $decoded;
    }
}
