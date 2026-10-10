<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use RuntimeException;

/**
 * How scripts/references/backfill_source_pages.php reads a legacy source page:
 * "pdf N" is a PDF page, a number or roman numeral is a printed label, and
 * anything else (a range, a note) is left for a person.
 */
final class SourcePageBackfillTest
{
    private int $assertions = 0;

    public function run(): int
    {
        require_once dirname(__DIR__, 2) . '/scripts/references/backfill_source_pages.php';
        $cases = [
            'pdf 257' => ['pdf_page', 257],
            'PDF474' => ['pdf_page', 474],
            '523' => ['printed_page', '523'],
            ' 16 ' => ['printed_page', '16'],
            'XII' => ['printed_page', 'xii'],
            '462-465' => null,
            'pdf 0' => null,
            '0' => null,
            'see figure' => null,
        ];
        foreach ($cases as $page => $expected) {
            $page = (string) $page; // "523" became an integer array key
            $this->assert(\classify_page($page) === $expected, "Legacy page \"{$page}\" read as " . json_encode(\classify_page($page)));
        }

        return $this->assertions;
    }

    private function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
        ++$this->assertions;
    }
}
