<?php

declare(strict_types=1);

namespace Fanoos\Platform\Support;

use PDO;

final class DatabaseConnection
{
    public static function fromEnvironment(): PDO
    {
        $config = RuntimeConfig::load();
        $dsn = $config->requireString('FANOOS_DB_DSN');
        $user = $config->requireString('FANOOS_DB_USER');
        $password = $config->requireString('FANOOS_DB_PASSWORD');

        return new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
    }
}
