<?php

declare(strict_types=1);

namespace Fanoos\Platform\Onboarding;

interface SmsGateway
{
    /** @return array{success:bool,message?:string} */
    public function send(string $phoneNumber, string $code): array;
}
