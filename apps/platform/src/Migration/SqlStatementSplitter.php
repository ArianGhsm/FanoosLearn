<?php

declare(strict_types=1);

namespace Fanoos\Platform\Migration;

use RuntimeException;

final class SqlStatementSplitter
{
    /** @return list<string> */
    public static function split(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $quote = null;
        $lineComment = false;
        $blockComment = false;

        for ($index = 0; $index < $length; $index++) {
            $character = $sql[$index];
            $next = $index + 1 < $length ? $sql[$index + 1] : '';

            if ($lineComment) {
                if ($character === "\n") {
                    $lineComment = false;
                    $buffer .= $character;
                }
                continue;
            }

            if ($blockComment) {
                if ($character === '*' && $next === '/') {
                    $blockComment = false;
                    $index++;
                }
                continue;
            }

            if ($quote === null && $character === '-' && $next === '-' && ($index + 2 >= $length || ctype_space($sql[$index + 2]))) {
                $lineComment = true;
                $index++;
                continue;
            }

            if ($quote === null && $character === '#') {
                $lineComment = true;
                continue;
            }

            if ($quote === null && $character === '/' && $next === '*') {
                $blockComment = true;
                $index++;
                continue;
            }

            if ($quote !== null) {
                $buffer .= $character;

                if ($character === '\\' && $index + 1 < $length) {
                    $buffer .= $sql[++$index];
                    continue;
                }

                if ($character === $quote) {
                    if ($next === $quote) {
                        $buffer .= $sql[++$index];
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }

            if ($character === "'" || $character === '"' || $character === '`') {
                $quote = $character;
                $buffer .= $character;
                continue;
            }

            if ($character === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $character;
        }

        if ($quote !== null || $blockComment) {
            throw new RuntimeException('Unterminated SQL quote or comment.');
        }

        $last = trim($buffer);
        if ($last !== '') {
            $statements[] = $last;
        }

        return $statements;
    }
}
