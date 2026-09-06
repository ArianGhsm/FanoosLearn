<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

use Fanoos\Platform\Support\PlatformException;

final class ContentPayload
{
    /** @param array<string, mixed> $payload */
    public static function encode(array $payload): string
    {
        if ($payload === []) {
            throw new PlatformException('content_payload_required', 'Structured content cannot be empty.', 422);
        }
        $normalized = self::sort($payload);
        $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($json) > 1_048_576) {
            throw new PlatformException('content_payload_too_large', 'Structured content exceeds the one MiB boundary.', 422);
        }
        if (preg_match('/<\?(?:php|=)|<script\b|javascript\s*:/i', $json)) {
            throw new PlatformException('active_content_not_allowed', 'Active content is not allowed in structured resources.', 422);
        }

        return $json;
    }

    private static function sort(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            $value[$key] = self::sort($item);
        }

        return $value;
    }
}
