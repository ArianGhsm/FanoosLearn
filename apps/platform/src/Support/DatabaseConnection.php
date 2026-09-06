<?php

declare(strict_types=1);

namespace Fanoos\Platform\Support;

use PDO;
use RuntimeException;

final class DatabaseConnection
{
    public static function fromEnvironment(): PDO
    {
        $dsn = getenv('FANOOS_DB_DSN');
        $user = getenv('FANOOS_DB_USER');
        $password = getenv('FANOOS_DB_PASSWORD');

        if ($dsn === false || $dsn === '') {
            throw new RuntimeException('FANOOS_DB_DSN is required.');
        }

        if ($user === false) {
            throw new RuntimeException('FANOOS_DB_USER is required.');
        }

        if ($password === false) {
            throw new RuntimeException('FANOOS_DB_PASSWORD is required.');
        }

        return new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
    }
}
