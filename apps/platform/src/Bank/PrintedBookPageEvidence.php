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
        string $edition, int $pdfPage, string $printedPage, ?string $expectedPdfSha256 = null,
        bool $allowLongHeader = false,
    ): bool
    {
        if (!self::validRequest($edition, $pdfPage, $printedPage)) {
            return false;
        }
        $target = self::readPageLabels($edition, $pdfPage, $expectedPdfSha256, $allowLongHeader);
        $targetLabel = str_contains($printedPage, '.e') ? $printedPage : (int) $printedPage;
        if ($target === null || !in_array($targetLabel, $target, true)) {
            return false;
        }

        if (is_int($targetLabel)) {
            $neighbors = 0;
            foreach ([-2, -1, 1, 2] as $step) {
                $neighborPage = $pdfPage + $step;
                $neighborPrinted = $targetLabel + $step;
                if ($neighborPage < 1 || $neighborPrinted < 1) {
                    continue;
                }
                $labels = self::readPageLabels($edition, $neighborPage, $expectedPdfSha256, $allowLongHeader);
                if ($labels !== null && in_array($neighborPrinted, $labels, true)) {
                    ++$neighbors;
                }
            }
            if ($neighbors >= 2) {
                return true;
            }
        }

        // Inserted book pages use labels such as 136.e1, 136.e2, then 137.
        // Require both immediately adjacent original PDF pages to show the
        // exact predecessor and successor; never truncate an insert label.
        [$previous, $next] = self::adjacentPrintedLabels($printedPage);
        if ($pdfPage < 2 || $previous === [] || $next === []) {
            return false;
        }
        $before = self::readPageLabels($edition, $pdfPage - 1, $expectedPdfSha256, $allowLongHeader);
        $after = self::readPageLabels($edition, $pdfPage + 1, $expectedPdfSha256, $allowLongHeader);
        return $before !== null && $after !== null
            && array_intersect($previous, array_map('strval', $before)) !== []
            && array_intersect($next, array_map('strval', $after)) !== [];
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

    /** @return list<int|string> */
    public static function pageLabelsFromText(string $text, bool $allowLongHeader = false): array
    {
        $lines = array_values(array_filter(array_map(
            'trim', preg_split('/\R/u', $text) ?: []
        ), static fn(string $line): bool => $line !== ''));
        $edge = array_merge(array_slice($lines, 0, 3), array_slice($lines, -3));
        $numbers = [];
        // Some approved PDFs put the printed label at the end of a long
        // first-line chapter running header. Accept only that exact shape;
        // ordinary long body lines remain excluded below.
        if (!$allowLongHeader && isset($lines[0]) && mb_strlen($lines[0]) < 180
            && preg_match('/^Chapter\s+[0-9]{1,3}\b.{0,170}\h{2,}([1-9][0-9]{0,3}(?:\.e[1-9][0-9]?)?)$/iu', $lines[0], $header)) {
            $numbers[] = str_contains($header[1], '.e') ? strtolower($header[1]) : (int) $header[1];
        }
        // Torabinejad 6e prints some page numbers before a spaced chapter
        // running header. Restrict this exception to the first line and the
        // exact header shape; corroborates() still requires neighboring pages.
        if (!$allowLongHeader && isset($lines[0]) && mb_strlen($lines[0]) < 180
            && preg_match('/^([1-9][0-9]{0,3})\h{2,}C\h*H\h*A\h*P\h*T\h*E\h*R\h+[0-9]{1,3}\b/iu', $lines[0], $header)) {
            $numbers[] = (int) $header[1];
        }
        // McDonald/Avery 11e alternates chapter-title and PART running
        // headers. Craig 14e puts the label before its full book title.
        // Accept these first-line book header shapes only; adjacent physical
        // pages must still corroborate the printed sequence.
        if (!$allowLongHeader && isset($lines[0]) && mb_strlen($lines[0]) < 180) {
            if (preg_match('/^[1-9][0-9]?\h*•\h*[^\r\n]{5,150}\h{2,}([1-9][0-9]{0,3})$/u', $lines[0], $header)
                || preg_match('/^([1-9][0-9]{0,3})\h{2,}PART\h+[1-9][0-9]?\h*•/iu', $lines[0], $header)
                || preg_match('/^([1-9][0-9]{0,3})\h{2,}CRAIG[’\x27]S\h+RESTORATIVE\h+DENTAL\h+MATERIALS$/iu', $lines[0], $header)) {
                $numbers[] = (int) $header[1];
            }
        }
        foreach ($edge as $line) {
            if (!$allowLongHeader && mb_strlen($line) >= 90) {
                continue;
            }
            if (preg_match('/^([0-9]{1,4}(?:\.e[1-9][0-9]?)?)(?:\s|$)/iu', $line, $match)
                || preg_match('/(?:^|\s)([0-9]{1,4}(?:\.e[1-9][0-9]?)?)$/iu', $line, $match)) {
                $numbers[] = str_contains($match[1], '.e') ? strtolower($match[1]) : (int) $match[1];
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
            && preg_match('/^[1-9][0-9]{0,3}(?:\.e[1-9][0-9]?)?$/D', $printedPage) === 1;
    }

    /** @return array{list<string>,list<string>} */
    private static function adjacentPrintedLabels(string $printedPage): array
    {
        if (preg_match('/^([1-9][0-9]{0,3})(?:\.e([1-9][0-9]?))?$/D', $printedPage, $parts) !== 1) {
            return [[], []];
        }
        $base = (int) $parts[1];
        $insert = isset($parts[2]) ? (int) $parts[2] : null;
        if ($insert === null) {
            return [$base > 1 ? [(string) ($base - 1)] : [],
                array_values(array_filter([$base < 9999 ? (string) ($base + 1) : null, $base . '.e1']))];
        }
        return [$insert === 1 ? [(string) $base] : [$base . '.e' . ($insert - 1)],
            array_values(array_filter([$insert < 99 ? $base . '.e' . ($insert + 1) : null,
                $base < 9999 ? (string) ($base + 1) : null]))];
    }

    /** @return list<int|string>|null */
    private static function readPageLabels(
        string $edition, int $pdfPage, ?string $expectedPdfSha256, bool $allowLongHeader
    ): ?array
    {
        $text = self::readPage($edition, $pdfPage, $expectedPdfSha256);
        if ($text === null) {
            return null;
        }
        $labels = self::pageLabelsFromText($text, $allowLongHeader);
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
        // Poppler may hard-wrap a word with a discretionary hyphen at the
        // physical end of a line. Rejoin only that pattern; keep in-line
        // hyphens and all other punctuation to preserve strict quote matching.
        $joined = preg_replace('/-\h*\R\h*/u', '', $text);
        if ($joined === null) {
            return '';
        }
        return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($joined)));
    }
}
