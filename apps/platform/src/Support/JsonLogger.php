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
        // Callers now pass through text that originated outside PHP -- a failed
        // subprocess's stderr, for one -- and json_encode returns false on a
        // malformed UTF-8 sequence, which would write a bare newline and lose
        // the line silently. Substituting the bad bytes keeps the log entry.
        file_put_contents('php://stderr', json_encode($record, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL);
    }
}
