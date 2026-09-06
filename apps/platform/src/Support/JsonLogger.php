<?php

declare(strict_types=1);

namespace Fanoos\Platform\Support;

final class JsonLogger
{
    /** @param array<string, scalar|null> $context */
    public static function write(string $level, string $event, array $context = []): void
    {
        $safe = [];
        foreach ($context as $key => $value) {
            if (preg_match('/password|secret|token|authorization|cookie|key/i', $key)) {
                $safe[$key] = '[redacted]';
                continue;
            }
            $safe[$key] = is_string($value) && strlen($value) > 500
                ? substr($value, 0, 500) . '…'
                : $value;
        }

        $record = [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => $level,
            'event' => $event,
            'context' => $safe,
        ];
        file_put_contents('php://stderr', json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }
}
