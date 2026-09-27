<?php

declare(strict_types=1);

namespace Fanoos\Platform\Commerce;

use Fanoos\Platform\Support\RuntimeConfig;

/**
 * Which payment gateway this environment takes money through, decided in one
 * place for the public API and the bots' internal API alike -- they used to
 * build the fake gateway separately, each with its own copy of the rule.
 *
 * FANOOS_PAYMENT_GATEWAY:
 *   zibal     real payments; needs FANOOS_PAYMENT_ZIBAL_MERCHANT and
 *             FANOOS_PUBLIC_ORIGIN (https) for the return address
 *   fake      development and test environments only
 *   anything else, or zibal without its settings: payments are off, and
 *   every payment route answers so rather than half-working
 */
final class PaymentGatewayFactory
{
    /** The page Zibal sends the payer back to (WebRouter). */
    public const RETURN_PATH = '/pay/return';

    /** @return array{gateway: PaymentGateway, enabled: bool} */
    public static function fromConfig(RuntimeConfig $config): array
    {
        $key = (string) $config->optionalString('FANOOS_PAYMENT_GATEWAY', 'disabled');
        $environment = (string) $config->optionalString('FANOOS_ENV', 'production');

        if ($key === 'zibal') {
            $merchant = trim((string) $config->optionalString('FANOOS_PAYMENT_ZIBAL_MERCHANT', ''));
            $origin = rtrim(trim((string) $config->optionalString('FANOOS_PUBLIC_ORIGIN', '')), '/');
            if ($merchant !== '' && str_starts_with($origin, 'https://')) {
                return ['gateway' => new ZibalPaymentGateway($merchant, $origin . self::RETURN_PATH), 'enabled' => true];
            }
        }

        return [
            'gateway' => new FakePaymentGateway(),
            'enabled' => $key === 'fake' && in_array($environment, ['development', 'test'], true),
        ];
    }
}
