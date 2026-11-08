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
    private const EDGE_MEASUREMENT_UNITS = [
        'mm', 'cm', 'm', 'km', 'um', 'µm', 'μm', 'nm', 'pm', 'in', 'inch', 'inches', 'ft',
        'mg', 'mcg', 'µg', 'μg', 'ng', 'g', 'kg', 'ml', 'milliliter', 'millilitre', 'l', 'dl',
        'meq', 'mmol', 'mol', 'mm', 'μm', 'µm', 'n', 'kn', 'pa', 'kpa', 'mpa', 'gpa', 'psi',
        'mmhg', 'cmh2o', 's', 'sec', 'min', 'h', 'hr', 'rpm', 'hz', 'khz', 'mhz', 'v', 'mv',
        'w', 'kw', 'j', 'kj', 'kcal', '°c', '°f', 'angstrom', 'å',
        'متر', 'میلی‌متر', 'میلیمتر', 'سانتی‌متر', 'سانتیمتر', 'میکرومتر', 'میکرون', 'نانومتر',
        'گرم', 'میلی‌گرم', 'کیلوگرم', 'میلی‌لیتر', 'میلیلتر', 'لیتر', 'درصد', 'درجه', 'ثانیه',
        'دقیقه', 'ساعت', 'روز', 'سال', 'مول', 'نیوتن', 'پاسکال', 'ميلي‌گرم', 'كيلوگرم',
        'cent', 'percent', 'day', 'days', 'week', 'weeks', 'month', 'months', 'hour', 'hours',
        'minute', 'minutes', 'second', 'seconds', 'year', 'years', 'cent', 'cigarette', 'cigarettes',
        'time', 'times', 'site', 'sites', 'tooth', 'teeth', 'implant', 'implants', 'unit', 'units',
        'drop', 'drops', 'capsule', 'capsules', 'tablet', 'tablets', 'dose', 'doses', 'pack', 'packs',
        'ppm', 'cc', 'iu', 'درصد', 'دندان', 'دندان‌ها', 'دندانه', 'بار', 'هفته', 'ماه', 'قطره', 'قرص', 'دوز', 'واحد',
    ];
    private const RIGHT_SCAN_ABBREVIATIONS = [
        'vs', 'approx', 'ca', 'cf', 'i', 'e', 'g', 'etc', 'al', 'dr', 'mr', 'mrs', 'ms',
        'fig', 'eq', 'no', 'ref', 'vol', 'ed', 'min', 'sec', 'pp', 'ch',
    ];
    private const EDGE_OPEN_ENDED = [
        'more', 'less', 'greater', 'higher', 'lower', 'older', 'younger', 'longer', 'shorter',
        'above', 'below', 'over', 'under', 'atleast', 'upto', 'بیشتر', 'کمتر', 'بالاتر', 'پایین‌تر',
        'حداقل', 'حداکثر', 'بیش‌از', 'کمتر‌از',
    ];
    private const EDGE_CONNECTORS = [
        'per', 'to', 'of', 'in', 'on', 'by', 'for', 'at', 'over', 'under', 'about', 'up', 'down',
        'the', 'a', 'an', 'from', 'between', 'every', 'than', 'and', 'or', 'nor', 'with', 'versus', 'vs',
        'در', 'تا', 'از', 'به', 'برای', 'حدود', 'کمتر', 'بیشتر', 'بین', 'و', 'هر', 'یا', 'با',
    ];

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
        if ($target === null) {
            return false;
        }

        $targetHasLabel = in_array($targetLabel, $target, true);

        if (is_int($targetLabel)) {
            $neighbors = 0;
            $neighborLabels = [];
            foreach ([-2, -1, 1, 2] as $step) {
                $neighborPage = $pdfPage + $step;
                $neighborPrinted = $targetLabel + $step;
                if ($neighborPage < 1 || $neighborPrinted < 1) {
                    continue;
                }
                $labels = self::readPageLabels($edition, $neighborPage, $expectedPdfSha256, $allowLongHeader);
                if ($labels !== null) {
                    $neighborLabels[$neighborPage] = $labels;
                    if (in_array($neighborPrinted, $labels, true)) {
                        ++$neighbors;
                    }
                }
            }
            if ($targetHasLabel && $neighbors >= 2) {
                return true;
            }
            if ($targetHasLabel
                && self::hasInsertedPageSequenceNeighbors($neighborLabels, $pdfPage, $targetLabel)) {
                return true;
            }

            // Full-page figures and tables sometimes omit the running page
            // number. Accept that omission only when both immediately
            // adjacent physical pages prove the exact printed sequence and
            // the target page has no nearby, conflicting printed label.
            if (!$targetHasLabel && $pdfPage >= 2 && $targetLabel >= 2) {
                $before = self::readPageLabels($edition, $pdfPage - 1, $expectedPdfSha256, $allowLongHeader);
                $after = self::readPageLabels($edition, $pdfPage + 1, $expectedPdfSha256, $allowLongHeader);
                $nearbyConflict = array_filter(
                    $target,
                    static fn(int|string $label): bool => is_int($label) && abs($label - $targetLabel) <= 4,
                );
                if ($nearbyConflict === [] && $before !== null && $after !== null
                    && in_array($targetLabel - 1, $before, true)
                    && in_array($targetLabel + 1, $after, true)) {
                    return true;
                }
            }
        }

        if (!$targetHasLabel) {
            return false;
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
        if (!preg_match('/^[a-z0-9][a-z0-9-]*@[a-z0-9]+$/D', $edition) || $pdfPage < 1) {
            return false;
        }
        // Evidence must always refer to the exact hash in the validated source row.
        if (!is_string($expectedPdfSha256) || !preg_match('/^[a-f0-9]{64}$/D', $expectedPdfSha256)) {
            return false;
        }
        $fragments = self::exactEvidenceFragments($evidence);
        if ($fragments === null) {
            return false;
        }
        $text = self::readPage($edition, $pdfPage, $expectedPdfSha256);
        if ($text === null) {
            return false;
        }
        // Printed page labels need neighbouring PDF proof; an arbitrary margin number
        // must never remove part of a measurement or study sample size.
        $verifiedLabels = [];
        foreach (self::pageLabelsFromText($text, false) as $candidate) {
            if (is_int($candidate)
                && self::corroborates($edition, $pdfPage, (string) $candidate, $expectedPdfSha256)) {
                $verifiedLabels[] = (string) $candidate;
            }
        }
        foreach ($fragments as $quote) {
            if (self::hasUnreadableEvidenceGlyph($quote)
                || !self::containsExactFragment($text, $quote, $verifiedLabels)) {
                unset($text);
                return false;
            }
        }
        unset($text);
        return true;
    }

    /** Return true only when this exact, hash-pinned page exposes no printed-page label. */
    public static function hasNoPrintedPageLabel(
        string $edition, int $pdfPage, string $expectedPdfSha256
    ): bool {
        if (!preg_match('/^[a-z0-9][a-z0-9-]*@[a-z0-9]+$/D', $edition)
            || $pdfPage < 1 || !preg_match('/^[a-f0-9]{64}$/D', $expectedPdfSha256)) {
            return false;
        }
        $labels = self::readPageLabels($edition, $pdfPage, $expectedPdfSha256, false);
        return $labels !== null && self::hasNoPrintedPageLabels($labels);
    }

    /** A numeric table entry such as zero is not a printed page label. */
    public static function hasNoPrintedPageLabels(array $labels): bool
    {
        foreach ($labels as $label) {
            if ((is_int($label) && $label > 0)
                || (is_string($label) && preg_match('/^[1-9][0-9]{0,3}(?:\.e[1-9][0-9]?)?$/D', $label) === 1)) {
                return false;
            }
        }
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
                || preg_match('/^([1-9][0-9]{0,3})\h{2,}CRAIG[’\x27]S\h+RESTORATIVE\h+DENTAL\h+MATERIALS$/iu', $lines[0], $header)
                || preg_match('/^[1-9][0-9]?\.\h+[^\r\n]{5,150}\h{2,}([1-9][0-9]{0,3})$/u', $lines[0], $header)) {
                $numbers[] = (int) $header[1];
            }
        }
        // Some verified scans prepend a short watermark line and put a
        // widely spaced CHAPTER running header on the next line. Examine
        // only the first three lines and only these two exact header shapes.
        if (!$allowLongHeader) {
            foreach (array_slice($lines, 0, 3) as $headerLine) {
                if (mb_strlen($headerLine) > 320) {
                    continue;
                }
                if (preg_match('/^([1-9][0-9]{0,3})\h+C\h*H\h*A\h*P\h*T\h*E\h*R\h+[0-9]{1,3}\b/iu', $headerLine, $header)
                    || preg_match('/^C\h*H\h*A\h*P\h*T\h*E\h*R\h+[0-9]{1,3}\b.{5,300}\h{2,}([1-9][0-9]{0,3})$/iu', $headerLine, $header)) {
                    $numbers[] = (int) $header[1];
                }
            }
        }
        foreach ($edge as $line) {
            if (!$allowLongHeader && mb_strlen($line) >= 90) {
                continue;
            }
            // Numeric table keys (for example, ICDAS `1 = ...`) can occur
            // at the edge of a scanned page. They are content labels, not
            // printed book-page numbers.
            if (!$allowLongHeader && preg_match('/^\h*[0-9]{1,4}\h*=\h*/u', $line) === 1) {
                continue;
            }
            if (preg_match('/^([0-9]{1,4}(?:\.e[1-9][0-9]?)?)(?:\s|$)/iu', $line, $match)
                || preg_match('/(?:^|\s)([0-9]{1,4}(?:\.e[1-9][0-9]?)?)$/iu', $line, $match)) {
                $numbers[] = str_contains($match[1], '.e') ? strtolower($match[1]) : (int) $match[1];
            }
        }
        return array_values(array_unique($numbers));
    }

    /** @param array<int, list<int|string>> $pages Page number => labels found on that page. */
    public static function corroboratesPageLabels(array $pages, int $pdfPage, string $printedPage): bool
    {
        if ($pdfPage < 1 || !preg_match('/^[1-9][0-9]{0,3}$/D', $printedPage)) {
            return false;
        }
        $target = (int) $printedPage;
        $targetLabels = $pages[$pdfPage] ?? [];
        $targetHasLabel = in_array($target, $targetLabels, true);
        $neighbors = 0;
        foreach ([-2, -1, 1, 2] as $step) {
            $page = $pdfPage + $step;
            $label = $target + $step;
            if ($page > 0 && $label > 0
                && in_array($label, $pages[$page] ?? [], true)) {
                ++$neighbors;
            }
        }
        if ($targetHasLabel && $neighbors >= 2) {
            return true;
        }
        if ($targetHasLabel) {
            $neighborLabels = [];
            foreach ([-2, -1, 1, 2] as $step) {
                $page = $pdfPage + $step;
                if ($page > 0 && isset($pages[$page])) {
                    $neighborLabels[$page] = $pages[$page];
                }
            }
            return self::hasInsertedPageSequenceNeighbors($neighborLabels, $pdfPage, $target);
        }
        $nearbyConflict = array_filter(
            $targetLabels,
            static function (int|string $label) use ($target): bool {
                $base = self::printedPageBase($label);
                return $base !== null && abs($base - $target) <= 4;
            },
        );
        return $pdfPage >= 2 && $target >= 2 && $nearbyConflict === []
            && in_array($target - 1, $pages[$pdfPage - 1] ?? [], true)
            && in_array($target + 1, $pages[$pdfPage + 1] ?? [], true);
    }

    /**
     * An inserted .e page can sit between the numeric predecessor, target,
     * and numeric successor. Accept a numeric target only when the exact
     * target label is present and both sides of its printed-page sequence
     * are independently visible within two physical PDF pages.
     *
     * @param array<int, list<int|string>> $neighborLabels
     */
    private static function hasInsertedPageSequenceNeighbors(
        array $neighborLabels, int $pdfPage, int $target
    ): bool {
        $before = false;
        foreach ([-2, -1] as $step) {
            foreach ($neighborLabels[$pdfPage + $step] ?? [] as $label) {
                if (self::printedPageBase($label) === $target - 1) {
                    $before = true;
                }
            }
        }

        $after = false;
        foreach ([1, 2] as $step) {
            foreach ($neighborLabels[$pdfPage + $step] ?? [] as $label) {
                $base = self::printedPageBase($label);
                $nextNumericPage = $base === $target + 1
                    && (!is_string($label) || $label === (string) ($target + 1));
                $insertedPageAfterTarget = $base === $target && is_string($label)
                    && preg_match('/^' . preg_quote((string) $target, '/') . '\\.e[1-9][0-9]?$/D', $label) === 1;
                if ($nextNumericPage || $insertedPageAfterTarget) {
                    $after = true;
                }
            }
        }

        return $before && $after;
    }

    private static function printedPageBase(int|string $label): ?int
    {
        if (is_int($label)) {
            return $label > 0 ? $label : null;
        }
        if (preg_match('/^([1-9][0-9]{0,3})(?:\\.e[1-9][0-9]?)?$/D', strtolower($label), $match) !== 1) {
            return null;
        }
        return (int) $match[1];
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

    /** @return list<string>|null */
    private static function exactEvidenceFragments(array $evidence): ?array
    {
        $fragments = [];
        foreach ($evidence as $quote) {
            if (!is_string($quote)) {
                return null;
            }
            $parts = preg_split('/\.{3}|…/u', $quote);
            if (!is_array($parts)) {
                return null;
            }
            foreach ($parts as $part) {
                $part = preg_replace('/^[\x{0009}-\x{000D}\p{Z}]+|[\x{0009}-\x{000D}\p{Z}]+$/u', '', $part);
                if ($part === null) {
                    return null;
                }
                if ($part === '') {
                    continue;
                }
                $wordCount = preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\p{M}\x{200C}\x{200D}]*/u', $part);
                if ($wordCount === false || $wordCount < 4) {
                    return null;
                }
                $fragments[] = $part;
            }
        }
        return $fragments === [] ? null : $fragments;
    }

    private static function hasUnreadableEvidenceGlyph(string $text): bool
    {
        $visibleControls = preg_replace(
            '/[\x{061C}\x{200C}\x{200D}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u',
            '',
            $text,
        );
        if ($visibleControls === null) {
            return true;
        }
        return preg_match('/[\x{0000}-\x{0008}\x{000E}-\x{001F}\x{007F}-\x{009F}\p{Cf}\p{Cs}\p{Cn}\p{Co}\x{FFFC}\x{FFFD}]/u', $visibleControls) === 1;
    }

    private static function isLayoutWhitespace(string $char): bool
    {
        return preg_match('/^[\x{0009}-\x{000D}\p{Z}]$/u', $char) === 1;
    }

    private static function isEvidenceWordChar(string $char): bool
    {
        return $char !== '' && ($char === '_'
            || preg_match('/^[\p{L}\p{N}\p{M}\p{C}_]$/u', $char) === 1
            || in_array(mb_ord($char, 'UTF-8'), [0xFFFC, 0xFFFD], true));
    }

    private static function isEvidenceNumberChar(string $char): bool
    {
        return $char !== '' && preg_match('/^\p{N}$/u', $char) === 1;
    }

    private static function isEvidenceDash(string $char): bool
    {
        return $char !== '' && (preg_match('/^\p{Pd}$/u', $char) === 1
            || in_array($char, ["−", "\u{00AD}"], true));
    }

    private static function isEvidenceMathOperator(string $char): bool
    {
        return $char !== '' && preg_match('/^\p{Sm}$/u', $char) === 1;
    }

    private static function hasNumericEdgeWord(string $text): bool
    {
        $count = preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\p{M}\x{200C}\x{200D}]*/u', $text, $words);
        if ($count === false || $count === 0) {
            return true;
        }
        $tokens = $words[0];
        foreach (array_merge(array_slice($tokens, 0, 2), array_slice($tokens, -2)) as $word) {
            if (preg_match('/\p{N}/u', $word) === 1) {
                return true;
            }
        }
        return false;
    }

    private static function quoteEndsWithAbbreviation(string $text): bool
    {
        if (!str_ends_with($text, '.')) {
            return false;
        }
        $prefix = mb_substr($text, 0, -1, 'UTF-8');
        preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\p{M}\x{200C}\x{200D}]*/u', $prefix, $words);
        return $words[0] !== []
            && in_array(self::unitKey($words[0][count($words[0]) - 1]), self::RIGHT_SCAN_ABBREVIATIONS, true);
    }

    private static function firstSentenceBoundary(string $text): ?int
    {
        $count = preg_match_all('/[.!?;؛؟](?=\s|$)/u', $text, $matches, PREG_OFFSET_CAPTURE);
        if ($count === false || $count === 0) {
            return null;
        }
        foreach ($matches[0] as [$punctuation, $offset]) {
            if ($punctuation === '.') {
                $before = substr($text, 0, $offset);
                preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\p{M}\x{200C}\x{200D}]*/u', $before, $words);
                if ($words[0] !== []
                    && in_array(self::unitKey($words[0][count($words[0]) - 1]), self::RIGHT_SCAN_ABBREVIATIONS, true)) {
                    continue;
                }
            }
            return $offset;
        }
        return null;
    }

    private static function rightUnitRunContinuesToNumber(string $page, int $end, string $fragment): bool
    {
        $count = preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\p{M}\x{200C}\x{200D}]*/u', $fragment, $words);
        if ($count === false || $count === 0) {
            return false;
        }
        $containsNumber = false;
        foreach ($words[0] as $word) {
            if (preg_match('/\p{N}/u', $word) === 1) {
                $containsNumber = true;
                break;
            }
        }
        if (!$containsNumber) {
            return false;
        }
        $quoteKey = self::normalize($fragment);
        $lastQuoteChar = $quoteKey === '' ? '' : mb_substr($quoteKey, -1, 1, 'UTF-8');
        if (in_array($lastQuoteChar, ['.', ';', '!', '?', '؛', '؟'], true)
            && ($lastQuoteChar !== '.' || !self::quoteEndsWithAbbreviation($quoteKey))) {
            return false;
        }
        $remaining = mb_substr($page, $end, null, 'UTF-8');
        $sentenceBoundary = self::firstSentenceBoundary($remaining);
        if ($sentenceBoundary !== null) {
            $remaining = substr($remaining, 0, $sentenceBoundary);
        }
        preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\p{M}\x{200C}\x{200D}]*/u', $remaining, $following);
        if ($following[0] === []) {
            return false;
        }
        $tail = self::unitKey($words[0][$count - 1]);
        $sawConnector = in_array($tail, self::EDGE_CONNECTORS, true);
        $perDenominator = false;
        foreach ($following[0] as $word) {
            $key = self::unitKey($word);
            if ($sawConnector && in_array($key, self::EDGE_OPEN_ENDED, true)) {
                return true;
            }
            if (in_array($key, self::EDGE_CONNECTORS, true)) {
                $sawConnector = true;
                $perDenominator = $key === 'per';
                continue;
            }
            if (in_array($key, self::EDGE_MEASUREMENT_UNITS, true)) {
                if ($perDenominator) {
                    return true;
                }
                $perDenominator = false;
                continue;
            }
            return $sawConnector && preg_match('/\p{N}/u', $word) === 1;
        }
        return false;
    }

    /** @param array<int, true> $lineBreaks */
    private static function leftBoundaryFollowsNumber(
        string $page, int $position, array $lineBreaks, array $verifiedPageLabels
    ): bool
    {
        $previous = $position - 1;
        while ($previous >= 0 && self::isLayoutWhitespace(mb_substr($page, $previous, 1, 'UTF-8'))) {
            --$previous;
        }
        if ($previous < 0 || !self::isEvidenceNumberChar(mb_substr($page, $previous, 1, 'UTF-8'))) {
            return false;
        }
        $boundary = null;
        foreach ($lineBreaks as $index => $_) {
            if ($previous < $index && $index < $position) {
                $boundary = $index;
                break;
            }
        }
        if ($boundary === null) {
            return true;
        }
        $lineStart = 0;
        $lineNumber = 0;
        foreach ($lineBreaks as $index => $_) {
            if ($index < $previous) {
                $lineStart = $index + 1;
                ++$lineNumber;
            }
        }
        $line = trim(mb_substr($page, $lineStart, $boundary - $lineStart, 'UTF-8'), ' ');
        $totalLines = count($lineBreaks) + 1;
        $pageLabel = in_array($line, $verifiedPageLabels, true);
        return !($pageLabel && ($lineNumber < 3 || $lineNumber >= $totalLines - 3));
    }

    private static function unitKey(string $word): string
    {
        return strtr(mb_strtolower($word, 'UTF-8'), ['ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک']);
    }

    private static function attachedSeparator(string $page, int $boundary, bool $leftSide): bool
    {
        $length = mb_strlen($page, 'UTF-8');
        if ($leftSide) {
            $separator = $boundary - 1;
            $hasLayoutWhitespace = false;
            while ($separator >= 0 && self::isLayoutWhitespace(mb_substr($page, $separator, 1, 'UTF-8'))) {
                $hasLayoutWhitespace = true;
                --$separator;
            }
            $current = $boundary < $length ? mb_substr($page, $boundary, 1, 'UTF-8') : '';
            $previous = $separator >= 0 ? mb_substr($page, $separator, 1, 'UTF-8') : '';
            if ($hasLayoutWhitespace && self::isEvidenceNumberChar($previous)
                && self::isEvidenceNumberChar($current)) {
                return true;
            }
            if ($separator < 0) {
                return false;
            }
            $symbol = mb_substr($page, $separator, 1, 'UTF-8');
            $beforeAdjacent = $separator > 0 ? mb_substr($page, $separator - 1, 1, 'UTF-8') : '';
            $beforePosition = $separator - 1;
            while ($beforePosition >= 0 && self::isLayoutWhitespace(mb_substr($page, $beforePosition, 1, 'UTF-8'))) {
                --$beforePosition;
            }
            $before = $beforePosition >= 0 ? mb_substr($page, $beforePosition, 1, 'UTF-8') : '';
            $after = $boundary < $length ? mb_substr($page, $boundary, 1, 'UTF-8') : '';
            if (self::isEvidenceDash($symbol)
                && self::isEvidenceWordChar($beforeAdjacent) && self::isEvidenceWordChar($after)) {
                return true;
            }
            if (self::isEvidenceDash($symbol)
                && self::isEvidenceNumberChar($before) && self::isEvidenceNumberChar($after)) {
                return true;
            }
            if ((in_array($symbol, ["×", "x", "X"], true) || self::isEvidenceMathOperator($symbol))
                && self::isEvidenceNumberChar($before) && self::isEvidenceNumberChar($after)) {
                return true;
            }
            if ((in_array($symbol, ["<", ">", "≤", "≥", "~", "≈", "≲", "≳", "≦", "≧", "∼", "～", "⩾", "⩽", "≃", "≅"], true)
                    || self::isEvidenceMathOperator($symbol))
                && self::isEvidenceNumberChar($after)) {
                return true;
            }
            if (str_contains(".,/:٫٬'’·،⁄∕±∓⁻₋", $symbol)
                && self::isEvidenceNumberChar($before) && self::isEvidenceNumberChar($after)) {
                return true;
            }
            if (str_contains(".,٫٬،", $symbol)
                && self::isEvidenceNumberChar($after)
                && ($before === '' || !self::isEvidenceWordChar($before))) {
                return true;
            }
            return str_contains("+-−±∓⁻₋", $symbol) && self::isEvidenceNumberChar($after)
                && (($before === '' || !self::isEvidenceWordChar($before))
                    || self::isEvidenceNumberChar($before));
        }

        if ($boundary >= $length) {
            return false;
        }
        $before = $boundary > 0 ? mb_substr($page, $boundary - 1, 1, 'UTF-8') : '';
        $firstAfter = mb_substr($page, $boundary, 1, 'UTF-8');
        if (self::isEvidenceDash($before) && self::isLayoutWhitespace($firstAfter) && $boundary > 1
            && self::isEvidenceWordChar(mb_substr($page, $boundary - 2, 1, 'UTF-8'))) {
            $continuation = $boundary;
            while ($continuation < $length && self::isLayoutWhitespace(mb_substr($page, $continuation, 1, 'UTF-8'))) {
                ++$continuation;
            }
            if ($continuation < $length && self::isEvidenceWordChar(mb_substr($page, $continuation, 1, 'UTF-8'))) {
                return true;
            }
        }
        if (self::isEvidenceNumberChar($before)) {
            $suffixPosition = $boundary;
            $hasLayoutWhitespace = false;
            while ($suffixPosition < $length
                && self::isLayoutWhitespace(mb_substr($page, $suffixPosition, 1, 'UTF-8'))) {
                $hasLayoutWhitespace = true;
                ++$suffixPosition;
            }
            $suffix = $suffixPosition < $length ? mb_substr($page, $suffixPosition, 1, 'UTF-8') : '';
            if ($hasLayoutWhitespace && self::isEvidenceNumberChar($suffix)) {
                return true;
            }
            if (str_contains("%‰‱٪％﹪", $suffix)) {
                return true;
            }
            if (in_array($suffix, ["×", "x", "X"], true)) {
                $operand = $suffixPosition + 1;
                while ($operand < $length
                    && self::isLayoutWhitespace(mb_substr($page, $operand, 1, 'UTF-8'))) {
                    ++$operand;
                }
                if ($operand < $length && self::isEvidenceNumberChar(mb_substr($page, $operand, 1, 'UTF-8'))) {
                    return true;
                }
            }
        }
        if (self::isEvidenceDash($firstAfter)) {
            $afterPosition = $boundary + 1;
            while ($afterPosition < $length && self::isLayoutWhitespace(mb_substr($page, $afterPosition, 1, 'UTF-8'))) {
                ++$afterPosition;
            }
            $after = $afterPosition < $length ? mb_substr($page, $afterPosition, 1, 'UTF-8') : '';
            return self::isEvidenceWordChar($before) && self::isEvidenceWordChar($after);
        }
        $separator = $boundary;
        while ($separator < $length && self::isLayoutWhitespace(mb_substr($page, $separator, 1, 'UTF-8'))) {
            ++$separator;
        }
        if ($separator >= $length) {
            return false;
        }
        $symbol = mb_substr($page, $separator, 1, 'UTF-8');
        $beforePosition = $boundary - 1;
        while ($beforePosition >= 0 && self::isLayoutWhitespace(mb_substr($page, $beforePosition, 1, 'UTF-8'))) {
            --$beforePosition;
        }
        $numberBefore = $beforePosition >= 0 ? mb_substr($page, $beforePosition, 1, 'UTF-8') : '';
        $afterPosition = $separator + 1;
        while ($afterPosition < $length && self::isLayoutWhitespace(mb_substr($page, $afterPosition, 1, 'UTF-8'))) {
            ++$afterPosition;
        }
        $numberAfter = $afterPosition < $length ? mb_substr($page, $afterPosition, 1, 'UTF-8') : '';
        if ((self::isEvidenceDash($symbol) || in_array($symbol, ["×", "x", "X"], true)
                || in_array($symbol, ["<", ">", "≤", "≥", "~", "≈", "≲", "≳", "≦", "≧", "∼", "～", "⩾", "⩽", "≃", "≅"], true)
                || self::isEvidenceMathOperator($symbol))
            && self::isEvidenceNumberChar($numberBefore) && self::isEvidenceNumberChar($numberAfter)) {
            return true;
        }
        if (str_contains(".,/:٫٬'’·،⁄∕±∓⁻₋", $symbol)
            && self::isEvidenceNumberChar($numberBefore) && self::isEvidenceNumberChar($numberAfter)) {
            return true;
        }
        if (str_contains(".,٫٬،", $symbol) && self::isEvidenceNumberChar($numberAfter)
            && ($numberBefore === '' || !self::isEvidenceWordChar($numberBefore))) {
            return true;
        }
        return str_contains("+-−±∓⁻₋", $symbol)
            && self::isEvidenceNumberChar($numberBefore) && self::isEvidenceNumberChar($numberAfter);
    }

    private static function containsExactFragment(
        string $page, string $fragment, array $verifiedPageLabels = []
    ): bool
    {
        [$pageKey, $lineBreaks] = self::normalizeWithLineBreaks($page);
        $quoteKey = self::normalize($fragment);
        if ($quoteKey === '' || self::hasNumericEdgeWord($quoteKey)) {
            return false;
        }
        $pageLength = mb_strlen($pageKey, 'UTF-8');
        $offset = 0;
        while (($position = mb_strpos($pageKey, $quoteKey, $offset, 'UTF-8')) !== false) {
            $end = $position + mb_strlen($quoteKey, 'UTF-8');
            $left = $position > 0 ? mb_substr($pageKey, $position - 1, 1, 'UTF-8') : '';
            $right = $end < $pageLength ? mb_substr($pageKey, $end, 1, 'UTF-8') : '';
            $leftIsWord = $left !== '' && self::isEvidenceWordChar($left);
            $rightIsWord = $right !== '' && self::isEvidenceWordChar($right);
            if (!$leftIsWord && !$rightIsWord
                && !self::leftBoundaryFollowsNumber($pageKey, $position, $lineBreaks, $verifiedPageLabels)
                && !self::attachedSeparator($pageKey, $position, true)
                && !self::attachedSeparator($pageKey, $end, false)
                && !self::rightUnitRunContinuesToNumber($pageKey, $end, $quoteKey)) {
                return true;
            }
            $offset = $position + 1;
        }
        return false;
    }

    private static function normalize(string $text): string
    {
        return self::normalizeWithLineBreaks($text)[0];
    }

    /** @return array{string, array<int, true>} */
    private static function normalizeWithLineBreaks(string $text): array
    {
        // Bidi display controls affect layout; line breaks are tracked for context checks.
        $directionless = preg_replace('/[\x{061C}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $text);
        if ($directionless === null) {
            return ['', []];
        }
        $parts = preg_split('/([\x{0009}-\x{000D}\p{Z}]+)/u', $directionless, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            return ['', []];
        }
        $normalized = '';
        $lineBreaks = [];
        $pendingSpace = false;
        $pendingLineBreak = false;
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (preg_match('/^[\x{0009}-\x{000D}\p{Z}]+$/u', $part) === 1) {
                if ($normalized !== '') {
                    $pendingSpace = true;
                    $pendingLineBreak = $pendingLineBreak
                        || preg_match('/[\r\n\x{2028}\x{2029}]/u', $part) === 1;
                }
                continue;
            }
            if ($pendingSpace) {
                $index = mb_strlen($normalized, 'UTF-8');
                $normalized .= ' ';
                if ($pendingLineBreak) {
                    $lineBreaks[$index] = true;
                }
                $pendingSpace = false;
                $pendingLineBreak = false;
            }
            $normalized .= $part;
        }
        return [$normalized, $lineBreaks];
    }
}
