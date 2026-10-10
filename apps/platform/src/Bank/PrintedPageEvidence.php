<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

/**
 * Checks book-printed page labels against the original, PDF-page-marked book
 * text. The caller MUST first pin this text's SHA256 to the approved private
 * provenance audit. Strict neighboring labels guard against accidental matches
 * to chapter numbers, figure numbers and unrelated in-page numerals.
 */
final class PrintedPageEvidence
{
    public static function matches(string $edition, int $pdfPage, string $label, string $bookPath): bool
    {
        if ($pdfPage < 1 || !preg_match('/^[1-9][0-9]{0,3}$/D', $label)
            || !preg_match('/^[a-z0-9-]+@[a-z0-9-]+$/D', $edition)
            || !is_file($bookPath) || is_link($bookPath)) {
            return false;
        }
        $printed = (int) $label;
        $raw = file_get_contents($bookPath);
        if (!is_string($raw) || !preg_match_all('/^=== PAGE (\\d+) ===\\s*$/m', $raw, $marks, PREG_OFFSET_CAPTURE)) {
            return false;
        }
        $pages = [];
        $count = count($marks[0]);
        for ($i = 0; $i < $count; ++$i) {
            $start = $marks[0][$i][1] + strlen($marks[0][$i][0]);
            $end = $i + 1 < $count ? $marks[0][$i + 1][1] : strlen($raw);
            $number = (int) $marks[1][$i][0];
            if (isset($pages[$number])) {
                return false;
            }
            if (abs($number - $pdfPage) <= 2) {
                $pages[$number] = substr($raw, $start, $end - $start);
            }
        }
        if (!in_array($printed, self::labels($pages[$pdfPage] ?? ''), true)) {
            return false;
        }
        $neighbors = 0;
        foreach ([-2, -1, 1, 2] as $step) {
            if ($printed + $step > 0 && in_array($printed + $step, self::labels($pages[$pdfPage + $step] ?? ''), true)) {
                ++$neighbors;
            }
        }
        return $neighbors >= 2;
    }

    /** @return list<int> */
    private static function labels(string $text): array
    {
        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\\R/u', $text) ?: []),
            static fn(string $v): bool => $v !== '',
        ));
        $nums = [];
        foreach (array_merge(array_slice($lines, 0, 3), array_slice($lines, -3)) as $line) {
            if (strlen($line) >= 90) {
                continue;
            }
            if (preg_match('/^(\\d{1,4})(?:\\s|$)/u', $line, $m)
                || preg_match('/(?:^|\\s)(\\d{1,4})$/u', $line, $m)) {
                $nums[(int) $m[1]] = (int) $m[1];
            }
        }
        return array_values($nums);
    }
}
