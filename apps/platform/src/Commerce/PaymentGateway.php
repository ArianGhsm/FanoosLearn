<?php

declare(strict_types=1);

namespace Fanoos\Platform\Commerce;

interface PaymentGateway
{
    public function key(): string;

    /** @return array{authority:string,redirect_url:string} */
    public function start(string $orderId, int $amountMinor, string $currency, string $callbackToken): array;

    public function redirectUrl(string $authority, string $callbackToken): string;

    /** @param array<string, mixed> $payload @return array{verified:bool,reference:?string,failure_code:?string,payload:array<string,mixed>} */
    public function verify(string $authority, int $amountMinor, string $currency, array $payload): array;

    /** @return array{verified:bool,reference:?string,failure_code:?string,payload:array<string,mixed>} */
    public function reconcile(string $orderId, int $amountMinor, string $currency): array;
}
