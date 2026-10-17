<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

use JsonException;

/** Resolves structured chapter scope to a printed-page disposition. */
final class OfficialScopePageGuard
{
    /**
     * Return true for an allowed chapter/page, false for an excluded page, or
     * null when the partial-scope note cannot be parsed safely.
     *
     * @param list<array{number?:string|int,partial?:string}>|string|null $scopeChapters
     */
    public static function pageDisposition(
        mixed $scopeChapters,
        string $chapter,
        ?string $printedPage,
    ): ?bool {
        if (is_string($scopeChapters)) {
            try {
                $scopeChapters = json_decode($scopeChapters, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return null;
            }
        }
        if (!is_array($scopeChapters) || $scopeChapters === []) {
            return null;
        }

        $matches = array_values(array_filter(
            $scopeChapters,
            static fn (mixed $entry): bool => is_array($entry)
                && (string) ($entry['number'] ?? '') === $chapter,
        ));
        if ($matches === []) {
            return false;
        }
        if (count($matches) !== 1) {
            return null;
        }

        $partial = $matches[0]['partial'] ?? null;
        if ($partial === null || trim((string) $partial) === '') {
            return true;
        }
        if (!is_string($partial) || !is_string($printedPage)) {
            return null;
        }

        $page = self::printedPageBase($printedPage);
        if ($page === null) {
            return null;
        }
        $note = self::normalizeDigits($partial);
        $excluded = str_contains($note, 'به جز') || str_contains($note, 'بجز')
            || str_contains($note, 'غیر از') || stripos($note, 'except') !== false
            || stripos($note, 'excluding') !== false;

        if (!$excluded
            && preg_match('/(?:تا|to)\s*(?:ص(?:فحه)?|page)\s*([0-9]{1,4})/iu', $note, $limit) === 1) {
            $end = (int) $limit[1];
            if (str_contains(strtolower($printedPage), '.e') && $page === $end) {
                return null;
            }
            return $page <= $end;
        }

        preg_match_all('/([0-9]{1,4})\s*(?:-|–|—|تا|to)\s*([0-9]{1,4})/iu', $note, $rangeMatches, PREG_SET_ORDER);
        $ranges = [];
        foreach ($rangeMatches as $range) {
            $start = (int) $range[1];
            $end = (int) $range[2];
            if ($start < 1 || $end < $start) {
                return null;
            }
            $ranges[] = [$start, $end];
        }

        if ($excluded) {
            if ($ranges !== []) {
                foreach ($ranges as [$start, $end]) {
                    if ($page >= $start && $page <= $end) {
                        return false;
                    }
                }
                return true;
            }
            $marker = preg_match('/(?:به جز|بجز|غیر از|except|excluding)/iu', $note, $match, PREG_OFFSET_CAPTURE);
            if ($marker !== 1) {
                return null;
            }
            $tail = substr($note, $match[0][1] + strlen($match[0][0]));
            preg_match_all('/(?:ص+|صفحه(?:ها)?|pages?)\s*([0-9]{1,4})/iu', $tail, $singleMatches);
            $excludedPages = array_map('intval', $singleMatches[1] ?? []);
            return $excludedPages === [] ? null : !in_array($page, $excludedPages, true);
        }

        if ($ranges !== []) {
            foreach ($ranges as [$start, $end]) {
                if ($page >= $start && $page <= $end) {
                    if (str_contains(strtolower($printedPage), '.e') && ($page === $start || $page === $end)) {
                        return null;
                    }
                    return true;
                }
            }
            return false;
        }

        return null;
    }

    private static function printedPageBase(string $printedPage): ?int
    {
        $normalized = self::normalizeDigits($printedPage);
        if (preg_match('/^([1-9][0-9]{0,3})(?:\.e[1-9][0-9]?)?$/D', strtolower($normalized), $match) !== 1) {
            return null;
        }
        return (int) $match[1];
    }

    private static function normalizeDigits(string $value): string
    {
        return strtr($value, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
            '–'=>'-','—'=>'-','−'=>'-',
        ]);
    }
}
