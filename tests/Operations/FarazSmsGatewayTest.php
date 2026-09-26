<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Onboarding\FarazSmsGateway;
use RuntimeException;

/**
 * The FarazSMS OTP sender, against a recorded transport instead of the
 * provider. What matters is what leaves the server: the recipient in the
 * format the provider takes, the code as a pattern variable, the WebOTP line
 * for the site's own domain -- and that a refused or failed send is never
 * reported as sent.
 */
final class FarazSmsGatewayTest
{
    private int $assertions = 0;

    public function run(): int
    {
        $this->assertAnAcceptedSendCarriesThePatternAndRecipient();
        $this->assertProviderRefusalIsAFailureAndNotRetried();
        $this->assertServerErrorsAreRetriedThenFail();
        $this->assertAnInvalidPhoneNeverReachesTheProvider();
        $this->assertRecipientNormalisation();

        return $this->assertions;
    }

    /** @param list<array{status:int, body:string, error:string}> $responses */
    private function gateway(array $responses, array &$calls, string $domain = 'fanooslearn.ir'): FarazSmsGateway
    {
        return new FarazSmsGateway('test-api-key', 'pat123', '3000123', 'code', $domain,
            static function (string $url, array $headers, string $body) use (&$responses, &$calls): array {
                $calls[] = ['url' => $url, 'headers' => $headers, 'body' => json_decode($body, true)];
                return array_shift($responses) ?? ['status' => 0, 'body' => '', 'error' => 'no more responses'];
            });
    }

    private function assertAnAcceptedSendCarriesThePatternAndRecipient(): void
    {
        $calls = [];
        $result = $this->gateway([['status' => 200, 'body' => '{"status":"success","code":"200","data":"991"}', 'error' => '']], $calls)
            ->send('+989121234567', '482913');
        $this->assert($result === ['success' => true], 'An accepted send was not reported as sent.');
        $this->assert(count($calls) === 1, 'An accepted send was retried.');
        $body = $calls[0]['body'];
        $this->assert($body['recipient'] === '09121234567', 'The recipient was not in the provider format.');
        $this->assert($body['code'] === 'pat123' && $body['line_number'] === '3000123', 'Pattern or sender line missing.');
        $this->assert($body['attributes']['code'] === '482913', 'The OTP was not sent as the pattern variable.');
        $this->assert($body['attributes']['webotp'] === '@fanooslearn.ir #482913', 'The WebOTP line does not name the site.');
        $this->assert(in_array('Api-Key: test-api-key', $calls[0]['headers'], true), 'The API key header is missing.');
    }

    private function assertProviderRefusalIsAFailureAndNotRetried(): void
    {
        $calls = [];
        $result = $this->gateway([['status' => 422, 'body' => '{"status":"error","messages":["خط نامعتبر"]}', 'error' => '']], $calls)
            ->send('09121234567', '111111');
        $this->assert(($result['success'] ?? null) === false, 'A refused send was reported as sent.');
        $this->assert(count($calls) === 1, 'A 4xx refusal was retried; retrying cannot change the answer.');

        // HTTP 200 alone is not acceptance.
        $calls = [];
        $result = $this->gateway([['status' => 200, 'body' => '{"status":"error"}', 'error' => '']], $calls)->send('09121234567', '1');
        $this->assert(($result['success'] ?? null) === false, 'A 200 with status error was reported as sent.');
    }

    private function assertServerErrorsAreRetriedThenFail(): void
    {
        $calls = [];
        $down = ['status' => 503, 'body' => '', 'error' => ''];
        $result = $this->gateway([$down, ['status' => 0, 'body' => '', 'error' => 'timeout'], $down], $calls)->send('09121234567', '2');
        $this->assert(count($calls) === 3, 'Transport and 5xx failures were not retried three times.');
        $this->assert(($result['success'] ?? null) === false, 'A provider outage was reported as sent.');

        $calls = [];
        $result = $this->gateway([$down, ['status' => 200, 'body' => '{"status":"success"}', 'error' => '']], $calls)->send('09121234567', '3');
        $this->assert(count($calls) === 2 && $result === ['success' => true], 'A send that succeeded on retry was lost.');
    }

    private function assertAnInvalidPhoneNeverReachesTheProvider(): void
    {
        $calls = [];
        $result = $this->gateway([], $calls)->send('+44 7700 900123', '4');
        $this->assert(($result['success'] ?? null) === false && $calls === [], 'A non-Iranian mobile number was sent to the provider.');
    }

    private function assertRecipientNormalisation(): void
    {
        foreach (['+989121234567', '989121234567', '00989121234567', '9121234567', '09121234567'] as $input) {
            $this->assert(FarazSmsGateway::recipient($input) === '09121234567', "Recipient {$input} was not normalised.");
        }
        $this->assert(FarazSmsGateway::recipient('02188776655') === '', 'A landline was accepted as a mobile.');
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
