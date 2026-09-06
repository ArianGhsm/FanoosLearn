<?php

declare(strict_types=1);

namespace Fanoos\Platform\Support;

final class TextNormalizer
{
    public static function normalize(string $value): string
    {
        $value = strtr(trim($value), [
            'ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
        $value = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{200C}\x{200D}]/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }
}
