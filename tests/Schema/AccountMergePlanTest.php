<?php

declare(strict_types=1);

namespace Fanoos\Tests\Schema;

use Fanoos\Platform\Identity\AccountMergePlanner;
use RuntimeException;

/**
 * Every column the migrations create that points at an account must be in
 * the merge plan -- moved, kept, revoked, dropped or deliberately left as
 * history. Without this, a table added later that owns user rows would be
 * silently left behind by a merge, still pointing at an account that can no
 * longer sign in.
 */
final class AccountMergePlanTest
{
    public function __construct(private readonly string $root)
    {
    }

    public function run(): int
    {
        $planned = [];
        foreach (AccountMergePlanner::PLAN as $table => $columns) {
            foreach ($columns as [$column]) {
                $planned["{$table}.{$column}"] = true;
            }
        }

        $found = [];
        foreach (glob($this->root . '/database/migrations/*.sql') ?: [] as $path) {
            $sql = (string) file_get_contents($path);
            preg_match_all('/CREATE TABLE IF NOT EXISTS\s+`?([a-z_]+)`?\s*\((.*?)\)\s*ENGINE=/si', $sql, $tables, PREG_SET_ORDER);
            foreach ($tables as [, $table, $body]) {
                preg_match_all('/^\s*([a-z_]*user_id)\s+CHAR/mi', $body, $columns);
                foreach ($columns[1] as $column) {
                    $found["{$table}.{$column}"] = true;
                }
            }
            preg_match_all('/ALTER TABLE\s+`?([a-z_]+)`?(.*?);/si', $sql, $alters, PREG_SET_ORDER);
            foreach ($alters as [, $table, $body]) {
                preg_match_all('/ADD COLUMN\s+([a-z_]*user_id)\s+CHAR/i', $body, $columns);
                foreach ($columns[1] as $column) {
                    $found["{$table}.{$column}"] = true;
                }
            }
        }
        if (count($found) < 30) {
            throw new RuntimeException('The migration scan found too few user columns; the parser is broken, not the plan.');
        }

        $missing = array_keys(array_diff_key($found, $planned));
        if ($missing !== []) {
            throw new RuntimeException('The account merge plan does not cover: ' . implode(', ', $missing));
        }
        $stale = array_keys(array_diff_key($planned, $found));
        if ($stale !== []) {
            throw new RuntimeException('The account merge plan names columns no migration creates: ' . implode(', ', $stale));
        }

        return 3;
    }
}
