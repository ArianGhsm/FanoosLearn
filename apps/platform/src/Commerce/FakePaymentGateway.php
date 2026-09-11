<?php

declare(strict_types=1);

namespace Fanoos\Platform\Commerce;

final class FakePaymentGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'fake';
    }

    public function start(string $orderId, int $amountMinor, string $currency, string $callbackToken): array
    {
        $authority = 'fake-' . str_replace('-', '', $orderId);
        return [
            'authority' => $authority,
            'redirect_url' => $this->redirectUrl($authority, $callbackToken),
        ];
    }

    public function redirectUrl(string $authority, string $callbackToken): string
    {
        return '/api/v1/payments/fake?token=' . rawurlencode($callbackToken) . '&authority=' . rawurlencode($authority);
    }

    public function verify(string $authority, int $amountMinor, string $currency, array $payload): array
    {
        $payloadAmount = $payload['amount_minor'] ?? null;
        $payloadCurrency = $payload['currency'] ?? null;
        $amountMatches = $payloadAmount === null || (is_int($payloadAmount) || is_numeric($payloadAmount)) && (int) $payloadAmount === $amountMinor;
        $currencyMatches = $payloadCurrency === null || is_string($payloadCurrency) && hash_equals(strtoupper($currency), strtoupper($payloadCurrency));
        $verified = ($payload['status'] ?? '') === 'success'
            && str_starts_with($authority, 'fake-')
            && $amountMinor > 0
            && $amountMatches
            && $currencyMatches;
        return [
            'verified' => $verified,
            'reference' => $verified ? 'fake-ref-' . substr(hash('sha256', $authority), 0, 24) : null,
            'failure_code' => $verified ? null : 'fake_payment_failed',
            'payload' => ['adapter' => 'fake', 'status' => $verified ? 'verified' : 'failed'],
        ];
    }

    public function reconcile(string $orderId, int $amountMinor, string $currency): array
    {
        return $this->verify('fake-' . str_replace('-', '', $orderId), $amountMinor, $currency, ['status' => 'success']);
    }
}
