<?php

declare(strict_types=1);

namespace Fanoos\Platform\Support;

final class Uuid
{
    public static function v7(): string
    {
        $milliseconds = (int) floor(microtime(true) * 1000);
        $time = str_pad(dechex($milliseconds), 12, '0', STR_PAD_LEFT);
        $random = bin2hex(random_bytes(10));
        $variant = dechex((hexdec($random[3]) & 0x3) | 0x8);

        return sprintf(
            '%s-%s-7%s-%s%s-%s',
            substr($time, 0, 8),
            substr($time, 8, 4),
            substr($random, 0, 3),
            $variant,
            substr($random, 4, 3),
            substr($random, 7, 12),
        );
    }
}
