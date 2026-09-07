<?php

declare(strict_types=1);

namespace Fanoos\Platform\Migration;

use PDO;
use RuntimeException;

final class AdditiveAlterGuard
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function decision(string $statement): string
    {
        $parsed = $this->parse($statement);
        if ($parsed === null) {
            return 'execute';
        }

        $present = 0;
        foreach ($parsed['additions'] as $addition) {
            if ($this->exists($parsed['table'], $addition['type'], $addition['name'])) {
                ++$present;
            }
        }

        if ($present === 0) {
            return 'execute';
        }
        if ($present === count($parsed['additions'])) {
            return 'skip';
        }

        throw new RuntimeException(sprintf(
            'Interrupted additive migration detected for table %s: only %d of %d additions exist. Manual reconciliation is required.',
            $parsed['table'],
            $present,
            count($parsed['additions']),
        ));
    }

    /** @return array{table:string,additions:list<array{type:string,name:string}>}|null */
    private function parse(string $statement): ?array
    {
        if (!preg_match('/^ALTER\s+TABLE\s+`?([a-zA-Z0-9_]+)`?\s+(.+)$/is', trim($statement), $match)) {
            return null;
        }

        $additions = [];
        foreach ($this->splitTopLevel($match[2]) as $clause) {
            $clause = trim($clause);
            if (preg_match('/^ADD\s+CONSTRAINT\s+`?([a-zA-Z0-9_]+)`?/i', $clause, $item)) {
                $additions[] = ['type' => 'constraint', 'name' => $item[1]];
                continue;
            }
            if (preg_match('/^ADD\s+(?:UNIQUE\s+)?(?:KEY|INDEX)\s+`?([a-zA-Z0-9_]+)`?/i', $clause, $item)) {
                $additions[] = ['type' => 'index', 'name' => $item[1]];
                continue;
            }
            if (preg_match('/^ADD\s+(?:COLUMN\s+)?`?([a-zA-Z0-9_]+)`?/i', $clause, $item)) {
                $additions[] = ['type' => 'column', 'name' => $item[1]];
                continue;
            }

            return null;
        }

        return $additions === [] ? null : ['table' => $match[1], 'additions' => $additions];
    }

    /** @return list<string> */
    private function splitTopLevel(string $value): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;
        $quote = null;
        $length = strlen($value);

        for ($i = 0; $i < $length; ++$i) {
            $char = $value[$i];
            if ($quote !== null) {
                $buffer .= $char;
                if ($char === $quote) {
                    $quote = null;
                } elseif ($char === '\\' && $i + 1 < $length) {
                    $buffer .= $value[++$i];
                }
                continue;
            }
            if (in_array($char, ["'", '"', '`'], true)) {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === '(') {
                ++$depth;
                $buffer .= $char;
                continue;
            }
            if ($char === ')') {
                --$depth;
                $buffer .= $char;
                continue;
            }
            if ($char === ',' && $depth === 0) {
                $parts[] = trim($buffer);
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $parts[] = trim($buffer);
        }

        return $parts;
    }

    private function exists(string $table, string $type, string $name): bool
    {
        $queries = [
            'column' => 'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :name LIMIT 1',
            'constraint' => 'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :table AND CONSTRAINT_NAME = :name LIMIT 1',
            'index' => 'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :name LIMIT 1',
        ];
        $query = $this->database->prepare($queries[$type]);
        $query->execute(['table' => $table, 'name' => $name]);

        return $query->fetchColumn() !== false;
    }
}
