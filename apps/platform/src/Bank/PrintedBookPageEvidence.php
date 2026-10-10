<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

/**
 * Verifies one page and its printed page label against the exact approved
 * server PDF. The Python bridge emits only that selected page to a pipe; this
 * class keeps it in memory for the check and never creates a text file.
 */
final class PrintedBookPageEvidence
{
    private const MAX_PAGE_TEXT_BYTES = 2_000_000;

    public static function corroborates(
        string $edition, int $pdfPage, string $printedPage, ?string $expectedPdfSha256 = null
    ): bool
    {
        if (!self::validRequest($edition, $pdfPage, $printedPage)) {
            return false;
        }
        $target = self::readPageLabels($edition, $pdfPage, $expectedPdfSha256);
        if ($target === null || !in_array((int) $printedPage, $target, true)) {
            return false;
        }

        $neighbors = 0;
        foreach ([-2, -1, 1, 2] as $step) {
            $neighborPage = $pdfPage + $step;
            $neighborPrinted = (int) $printedPage + $step;
            if ($neighborPage < 1 || $neighborPrinted < 1) {
                continue;
            }
            $labels = self::readPageLabels($edition, $neighborPage, $expectedPdfSha256);
            if ($labels !== null && in_array($neighborPrinted, $labels, true)) {
                ++$neighbors;
            }
        }
        return $neighbors >= 2;
    }

    /** Check short, reviewer-selected evidence on one exact PDF page. */
    public static function pageContainsEvidence(
        string $edition, int $pdfPage, array $evidence, ?string $expectedPdfSha256 = null
    ): bool
    {
        if (!preg_match('/^[a-z0-9][a-z0-9-]*@[a-z0-9]+$/D', $edition)
            || $pdfPage < 1 || $evidence === []) {
            return false;
        }
        if ($expectedPdfSha256 !== null && !preg_match('/^[a-f0-9]{64}$/D', $expectedPdfSha256)) {
            return false;
        }
        $text = self::readPage($edition, $pdfPage, $expectedPdfSha256);
        if ($text === null) {
            return false;
        }
        $normalized = self::normalize($text);
        unset($text);
        foreach ($evidence as $quote) {
            if (!is_string($quote) || mb_strlen(trim($quote)) < 6
                || !str_contains($normalized, self::normalize($quote))) {
                unset($normalized);
                return false;
            }
        }
        unset($normalized);
        return true;
    }

    /** @return list<int>|null */
    public static function pageLabelsFromText(string $text): array
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
            if (preg_match('/^([0-9]{1,4})(?:\s|$)/u', $line, $match)
                || preg_match('/(?:^|\s)([0-9]{1,4})$/u', $line, $match)) {
                $numbers[] = (int) $match[1];
            }
        }
        return array_values(array_unique($numbers));
    }

    /** @param array<int, list<int>> $pages Page number => labels found on that page. */
    public static function corroboratesPageLabels(array $pages, int $pdfPage, string $printedPage): bool
    {
        if ($pdfPage < 1 || !preg_match('/^[1-9][0-9]{0,3}$/D', $printedPage)
            || !in_array((int) $printedPage, $pages[$pdfPage] ?? [], true)) {
            return false;
        }
        $neighbors = 0;
        foreach ([-2, -1, 1, 2] as $step) {
            $page = $pdfPage + $step;
            $label = (int) $printedPage + $step;
            if ($page > 0 && $label > 0
                && in_array($label, $pages[$page] ?? [], true)) {
                ++$neighbors;
            }
        }
        return $neighbors >= 2;
    }

    private static function validRequest(string $edition, int $pdfPage, string $printedPage): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9-]*@[a-z0-9]+$/D', $edition) === 1
            && $pdfPage >= 1
            && preg_match('/^[1-9][0-9]{0,3}$/D', $printedPage) === 1;
    }

    /** @return list<int>|null */
    private static function readPageLabels(string $edition, int $pdfPage, ?string $expectedPdfSha256): ?array
    {
        $text = self::readPage($edition, $pdfPage, $expectedPdfSha256);
        if ($text === null) {
            return null;
        }
        $labels = self::pageLabelsFromText($text);
        unset($text);
        return $labels;
    }

    private static function readPage(string $edition, int $pdfPage, ?string $expectedPdfSha256): ?string
    {
        $root = dirname(__DIR__, 4);
        $bridge = $root . '/scripts/references/verified_reference_pdf.py';
        if (!is_file($bridge)) {
            return null;
        }
        $command = [
            'python3', '-B', $bridge,
            '--edition', $edition,
            '--page', (string) $pdfPage,
            '--require-map',
        ];
        if ($expectedPdfSha256 !== null) {
            $command[] = '--expected-sha256';
            $command[] = $expectedPdfSha256;
        }
        $pipes = [];
        $process = @proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $root);
        if (!is_resource($process)) {
            return null;
        }
        fclose($pipes[0]);
        $text = '';
        $overflow = false;
        while (!feof($pipes[1])) {
            $chunk = fread($pipes[1], 8192);
            if ($chunk === false || $chunk === '') {
                break;
            }
            if (!$overflow && strlen($text) + strlen($chunk) <= self::MAX_PAGE_TEXT_BYTES) {
                $text .= $chunk;
            } else {
                $overflow = true;
                $text = '';
            }
        }
        fclose($pipes[1]);
        // Error output is intentionally discarded; it may contain host paths.
        stream_get_contents($pipes[2], 8192);
        fclose($pipes[2]);
        $status = proc_close($process);
        if ($status !== 0 || $overflow) {
            unset($text);
            return null;
        }
        return $text;
    }

    private static function normalize(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($text)));
    }
}
