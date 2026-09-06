<?php

declare(strict_types=1);

namespace Fanoos\Platform\Support;

use PDO;
use Throwable;

final class Transaction
{
    public static function run(PDO $database, callable $operation): mixed
    {
        $owner = !$database->inTransaction();
        if ($owner) {
            $database->beginTransaction();
        }

        try {
            $result = $operation();
            if ($owner) {
                $database->commit();
            }
            return $result;
        } catch (Throwable $error) {
            if ($owner && $database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }
}
