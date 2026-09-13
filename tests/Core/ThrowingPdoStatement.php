<?php

declare(strict_types=1);

namespace Fanoos\Tests\Core;

use PDOException;
use PDOStatement;

/**
 * PDOStatement fault injection shared by atomicity tests
 * (ClassCreationRequestServiceTest, InstitutionTermServiceTest): installed
 * via PDO::ATTR_STATEMENT_CLASS, throws once for a statement whose SQL
 * contains a chosen substring, then behaves normally again.
 *
 * Deliberately never a schema mutation (e.g. toggling a CHECK constraint
 * with ALTER TABLE): DDL causes an implicit commit in MySQL, which would
 * commit the very transaction a mid-write atomicity test needs to prove
 * rolls back, and narrowing a constraint validates every existing row --
 * failing outright the moment an earlier, unrelated test has already left a
 * row the narrower constraint would reject.
 *
 * The explicit no-op constructor is required: PDO_MYSQL instantiates the
 * statement class itself, and without an accessible constructor here it
 * reports "User-supplied statement does not accept constructor arguments"
 * on the very first prepare() after ATTR_STATEMENT_CLASS is set. Restoring
 * the default afterward must pass [PDOStatement::class] with no ctor-args
 * element -- PDOStatement itself exposes no constructor, and supplying even
 * an empty args array trips the same error in reverse.
 */
final class ThrowingPdoStatement extends PDOStatement
{
    protected function __construct()
    {
    }

    private static bool $armed = false;
    private static string $match = '';

    public static function arm(string $sqlSubstring): void
    {
        self::$armed = true;
        self::$match = $sqlSubstring;
    }

    public static function disarm(): void
    {
        self::$armed = false;
        self::$match = '';
    }

    public function execute(?array $params = null): bool
    {
        if (self::$armed && str_contains($this->queryString, self::$match)) {
            self::$armed = false;
            throw new PDOException('Injected failure for atomicity test.');
        }
        return parent::execute($params);
    }
}
