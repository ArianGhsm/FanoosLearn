<?php

declare(strict_types=1);

namespace Fanoos\Platform\Support;

final class JsonLogger
{
    /**
     * An exception a request handler caught and turned into a bare 500. The
     * caller still answers "internal_error" -- the detail must never reach
     * the client -- but without this line the only trace of the failure was
     * the status code in the access log, which is how a missing service
     * secret went unexplained for a whole bot deployment.
     */
    public static function requestFailure(string $surface, string $requestId, string $method, string $path, \Throwable $error): void
    {
        $chain = [];
        for ($current = $error; $current !== null && count($chain) < 4; $current = $current->getPrevious()) {
            $chain[] = $current::class . ': ' . $current->getMessage()
                . ' @ ' . basename($current->getFile()) . ':' . $current->getLine();
        }
        self::write('error', 'http.request_failed', [
            'surface' => $surface,
            'request_id' => $requestId,
            'method' => $method,
            // Paths carry ids, never credentials; query strings are dropped.
            'path' => strtok($path, '?') ?: $path,
            'error' => implode(' <- ', $chain),
        ]);
    }

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
