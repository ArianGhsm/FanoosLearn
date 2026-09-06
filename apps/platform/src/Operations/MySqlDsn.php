<?php

declare(strict_types=1);

namespace Fanoos\Platform\Operations;

use RuntimeException;

final class MySqlDsn
{
    /** @return array{host: string, port: string, database: string} */
    public static function parse(string $dsn): array
    {
        if (!str_starts_with($dsn, 'mysql:')) {
            throw new RuntimeException('Only MySQL DSNs are supported by operational scripts.');
        }

        $values = [];
        foreach (explode(';', substr($dsn, 6)) as $part) {
            if (!str_contains($part, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $part, 2);
            $values[strtolower(trim($key))] = trim($value);
        }

        $host = $values['host'] ?? '127.0.0.1';
        $port = $values['port'] ?? '3306';
        $database = $values['dbname'] ?? '';
        if (!preg_match('/^[A-Za-z0-9_.-]+$/', $host)
            || !preg_match('/^[1-9][0-9]{0,4}$/', $port)
            || (int) $port > 65535
            || !preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            throw new RuntimeException('MySQL DSN contains an unsafe host, port, or database name.');
        }

        return ['host' => $host, 'port' => $port, 'database' => $database];
    }
}
