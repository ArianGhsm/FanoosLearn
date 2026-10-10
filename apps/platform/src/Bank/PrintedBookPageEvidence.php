<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

/**
 * Verify original book-printed page labels against the exact page-marked PDF
 * extraction. A printed page is never inferred from a constant PDF offset.
 * This is only a secondary verification of previously audited reference text;
 * the source-only importer still requires SHA-pinned independent provenance.
 */
final class PrintedBookPageEvidence
{
    public static function corroborates(
        string $referenceRoot,
        string $edition,
        int $pdfPage,
        string $printedPage
    ): bool {
        if ($pdfPage < 1 || !preg_match('/^[1-9][0-9]{0,3}$/D', $printedPage)
            || !preg_match('/^[a-z0-9-]+@[a-z0-9-]+$/D', $edition)) {
            return false;
        }
        $path = realpath(rtrim($referenceRoot, '/') . '/references/' . $edition . '.txt');
        $research = realpath('/srv/fanoos/shared/research');
        if ($path === false || $research === false
            || !str_starts_with($path, $research . '/')
            || !is_file($path) || filesize($path) > 15000000) {
            return false;
        }
        $raw = file_get_contents($path);
        if ($raw === false || preg_match_all(
            '/^=== PAGE ([1-9][0-9]*) ===\h*$/m',
            $raw, $matches, PREG_OFFSET_CAPTURE
        ) < 1) {
            return false;
        }
        $wanted = [$pdfPage - 2, $pdfPage - 1, $pdfPage, $pdfPage + 1, $pdfPage + 2];
        $pages = [];
        for ($i = 0, $n = count($matches[0]); $i < $n; ++$i) {
            $pageNo = (int) $matches[1][$i][0];
            if (!in_array($pageNo, $wanted, true)) {
                continue;
            }
            $start = $matches[0][$i][1] + strlen($matches[0][$i][0]);
            $end = $i + 1 < $n ? $matches[0][$i + 1][1] : strlen($raw);
            $pages[$pageNo] = substr($raw, $start, $end - $start);
        }
        $printed = (int) $printedPage;
        if (!in_array($printed, self::labels($pages[$pdfPage] ?? ''), true)) {
            return false;
        }
        $neighbors = 0;
        foreach ([-2, -1, 1, 2] as $step) {
            if ($pdfPage + $step > 0 && $printed + $step > 0
                && in_array($printed + $step, self::labels($pages[$pdfPage + $step] ?? ''), true)) {
                ++$neighbors;
            }
        }
        return $neighbors >= 2;
    }

    /** @return list<int> */
    private static function labels(string $text): array
    {
        $lines = array_values(array_filter(array_map(
            'trim', preg_split('/\R/u', $text) ?: []
        ), static fn(string $line): bool => $line !== ''));
        $edge = array_merge(array_slice($lines, 0, 3), array_slice($lines, -3));
        $numbers = [];
        foreach ($edge as $line) {
            if (mb_strlen($line) >= 90) {
                continue;
            }
            if (preg_match('/^([0-9]{1,4})(?:\s|$)/u', $line, $m)
                || preg_match('/(?:^|\s)([0-9]{1,4})$/u', $line, $m)) {
                $numbers[] = (int) $m[1];
            }
        }
        return array_values(array_unique($numbers));
    }
}
