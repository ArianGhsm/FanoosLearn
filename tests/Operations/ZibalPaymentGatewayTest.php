<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Commerce\PaymentGatewayFactory;
use Fanoos\Platform\Commerce\ZibalPaymentGateway;
use Fanoos\Platform\Support\RuntimeConfig;
use RuntimeException;

/**
 * The Zibal gateway against a recorded HTTP exchange. The rule that matters
 * most: a payment is believed only when Zibal verifies it *and* Zibal's own
 * figure for the amount is this order's amount.
 */
final class ZibalPaymentGatewayTest
{
    private int $assertions = 0;

    public function run(): int
    {
        $this->assertStartSendsTheOrderAndReturnsTheGatewayAddress();
        $this->assertVerificationNeedsTheRightAmount();
        $this->assertAnAlreadyVerifiedAnswerIsCheckedByInquiry();
        $this->assertUnpaidAndUnreconcilableAreFailures();
        $this->assertTheFactoryOnlyEnablesACompleteConfiguration();

        return $this->assertions;
    }

    /** @param array<string, array<string, mixed>> $answers keyed by endpoint suffix */
    private function gateway(array $answers, array &$calls): ZibalPaymentGateway
    {
        return new ZibalPaymentGateway('merchant-x', 'https://fanoos.test/pay/return', static function (string $url, array $body) use ($answers, &$calls): array {
            $calls[] = [$url, $body];
            foreach ($answers as $suffix => $answer) {
                if (str_ends_with($url, $suffix)) {
                    return $answer;
                }
            }
            throw new RuntimeException('unexpected call ' . $url);
        });
    }

    private function assertStartSendsTheOrderAndReturnsTheGatewayAddress(): void
    {
        $calls = [];
        $started = $this->gateway(['/request' => ['result' => 100, 'trackId' => 3714061657]], $calls)
            ->start('01a0e486-2933-7720-b6f5-e57c1673a98e', 1500000, 'IRR', 'tok/en');
        $this->assert($started['authority'] === '3714061657', 'The trackId did not become the authority.');
        $this->assert($started['redirect_url'] === 'https://gateway.zibal.ir/start/3714061657', 'The payer was not sent to Zibal.');
        $this->assert($calls[0][1]['amount'] === 1500000 && $calls[0][1]['merchant'] === 'merchant-x', 'Amount or merchant was not sent.');
        $this->assert($calls[0][1]['callbackUrl'] === 'https://fanoos.test/pay/return?token=tok%2Fen', 'The return address did not carry the callback token.');

        $calls = [];
        $this->expectFailure(fn () => $this->gateway(['/request' => ['result' => 103, 'message' => 'merchant inactive']], $calls)->start('o', 1500000, 'IRR', 't'), 'A refused request was treated as started.');
        $this->expectFailure(fn () => $this->gateway([], $calls)->start('o', 500, 'IRR', 't'), 'An amount under Zibal\'s floor was sent.');
        $this->expectFailure(fn () => $this->gateway([], $calls)->start('o', 150000, 'USD', 't'), 'A non-Rial amount was sent.');
    }

    private function assertVerificationNeedsTheRightAmount(): void
    {
        $calls = [];
        $ok = $this->gateway(['/verify' => ['result' => 100, 'amount' => 1500000, 'refNumber' => 'R-77']], $calls)->verify('3714061657', 1500000, 'IRR', []);
        $this->assert($ok['verified'] === true && $ok['reference'] === 'R-77', 'A verified payment of the right amount was refused.');

        $calls = [];
        $cheap = $this->gateway(['/verify' => ['result' => 100, 'amount' => 10000, 'refNumber' => 'R-78']], $calls)->verify('3714061657', 1500000, 'IRR', []);
        $this->assert($cheap['verified'] === false && $cheap['failure_code'] === 'zibal_amount_mismatch', 'A cheaper payment verified this order.');
    }

    private function assertAnAlreadyVerifiedAnswerIsCheckedByInquiry(): void
    {
        $calls = [];
        $again = $this->gateway([
            '/verify' => ['result' => 201],
            '/inquiry' => ['result' => 100, 'amount' => 1500000],
        ], $calls)->verify('3714061657', 1500000, 'IRR', []);
        $this->assert($again['verified'] === true && count($calls) === 2, 'An already-verified payment was not confirmed by inquiry.');
    }

    private function assertUnpaidAndUnreconcilableAreFailures(): void
    {
        $calls = [];
        $unpaid = $this->gateway(['/verify' => ['result' => 202]], $calls)->verify('3714061657', 1500000, 'IRR', []);
        $this->assert($unpaid['verified'] === false && $unpaid['failure_code'] === 'zibal_not_paid', 'An unpaid order verified.');
        $calls = [];
        $this->assert($this->gateway([], $calls)->verify('../x', 1500000, 'IRR', [])['verified'] === false && $calls === [], 'A malformed trackId reached Zibal.');
        $this->assert($this->gateway([], $calls)->reconcile('o', null, 1500000, 'IRR')['verified'] === false, 'An attempt without a trackId reconciled.');
    }

    private function assertTheFactoryOnlyEnablesACompleteConfiguration(): void
    {
        $config = static function (array $values): RuntimeConfig {
            $path = tempnam(sys_get_temp_dir(), 'fanoos-config-');
            file_put_contents($path, '<?php return ' . var_export($values, true) . ';');
            putenv('FANOOS_CONFIG_FILE=' . $path);
            $loaded = RuntimeConfig::load();
            putenv('FANOOS_CONFIG_FILE');
            unlink($path);
            return $loaded;
        };
        $zibal = PaymentGatewayFactory::fromConfig($config(['FANOOS_PAYMENT_GATEWAY' => 'zibal', 'FANOOS_PAYMENT_ZIBAL_MERCHANT' => 'm', 'FANOOS_PUBLIC_ORIGIN' => 'https://fanooslearn.ir']));
        $this->assert($zibal['enabled'] && $zibal['gateway']->key() === 'zibal', 'A complete Zibal configuration did not enable payments.');
        $noOrigin = PaymentGatewayFactory::fromConfig($config(['FANOOS_PAYMENT_GATEWAY' => 'zibal', 'FANOOS_PAYMENT_ZIBAL_MERCHANT' => 'm']));
        $this->assert(!$noOrigin['enabled'], 'Zibal without a return address enabled payments.');
        $fakeInProduction = PaymentGatewayFactory::fromConfig($config(['FANOOS_PAYMENT_GATEWAY' => 'fake', 'FANOOS_ENV' => 'production']));
        $this->assert(!$fakeInProduction['enabled'], 'The fake gateway was enabled in production.');
    }

    private function expectFailure(callable $operation, string $message): void
    {
        ++$this->assertions;
        try {
            $operation();
        } catch (RuntimeException) {
            return;
        }
        throw new RuntimeException($message);
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
