<?php

declare(strict_types=1);

namespace Fanoos\Platform\Migration;

/**
 * The one definition of what a migration is allowed to contain.
 *
 * There were two: `MigrationPreflight` refuses to apply an unsafe migration
 * during a deployment, and `SchemaContractTest` refuses to let one into the
 * repository at all. They agreed until one of them was taught about paired
 * CHECK widening and the other was not -- so CI went green, a backup was
 * taken, the release was staged, and only then did the updater refuse it.
 *
 * Two guards enforcing the same rule must share the sentence that states it,
 * or the next change teaches one of them and the deployment finds out.
 */
final class MigrationSafety
{
    /**
     * Why this migration cannot be applied unattended, or null if it can.
     *
     * The rule is that a migration may only expand: the release running
     * *before* it must keep working against the new schema, so a rollback
     * needs no reverse migration. Dropping, truncating, renaming or
     * modifying a column all break that.
     *
     * One exception, and only in its paired form. MySQL has no way to alter
     * a CHECK constraint's condition, so widening one -- a mode enum gaining
     * a value, say -- can only be written as dropping it and adding it back.
     * That is an expansion: every row the old constraint allowed, the wider
     * one still allows, and the previous release keeps writing values that
     * remain valid. The pairing is what makes it an expansion rather than a
     * removal, so the pairing is what is checked.
     */
    public static function unsafeReason(string $sql): ?string
    {
        if (preg_match('/\bTRUNCATE\b|\bRENAME\s+TABLE\b/i', $sql) === 1) {
            return 'contains a destructive or contract-changing operation';
        }

        // Any DROP other than DROP CHECK, and any column-level rewrite.
        if (preg_match('/\bDROP\s+(?!CHECK\b)\w+/i', $sql) === 1) {
            return 'contains a destructive or contract-changing operation';
        }
        if (preg_match('/\bALTER\s+TABLE\b[\s\S]*?\b(?:MODIFY|CHANGE|RENAME)\b/i', $sql) === 1) {
            return 'contains a destructive or contract-changing operation';
        }

        if (preg_match_all('/\bDROP\s+CHECK\s+(\w+)/i', $sql, $dropped) === 0) {
            return null;
        }
        foreach ($dropped[1] as $name) {
            $readded = '/\bADD\s+CONSTRAINT\s+' . preg_quote($name, '/') . '\s+CHECK\s*\(/i';
            if (preg_match($readded, $sql) !== 1) {
                return "drops CHECK {$name} without re-adding a constraint of the same name -- a bare removal, not a widen";
            }
        }

        return null;
    }

    /** Whether the migration declares how it behaves on a rollback. */
    public static function declaresExpandCompatible(string $sql): bool
    {
        return preg_match('/^\s*--\s*fanoos:rollback-compatible=expand\s*$/mi', $sql) === 1;
    }
}
