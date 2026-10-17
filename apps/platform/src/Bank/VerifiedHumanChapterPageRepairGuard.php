<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

/** Pure checks binding a protected human chapter/page plan to its independent audit. */
final class VerifiedHumanChapterPageRepairGuard
{
    public static function auditMatches(array $audit, array $plan, array $item): bool
    {
        if (($audit['format'] ?? null) !== 'fanoos.human-source-chapter-page-audit/1'
            || ($audit['research_only'] ?? null) !== true
            || ($audit['year'] ?? null) !== ($plan['year'] ?? null)
            || ($audit['subject'] ?? null) !== ($plan['subject'] ?? null)
            || !is_array($audit['records'] ?? null)) {
            return false;
        }

        $number = $item['number'] ?? null;
        if (!is_int($number)) {
            return false;
        }
        $matches = array_values(array_filter(
            $audit['records'],
            static fn (mixed $record): bool => is_array($record)
                && ($record['number'] ?? null) === $number,
        ));
        if (count($matches) !== 1 || ($matches[0]['scientifically_accepted'] ?? null) !== true) {
            return false;
        }

        foreach ([
            'source_id', 'edition', 'expected_chapter', 'chapter', 'pdf_page', 'pdf_sha256',
            'evidence', 'expected_stem_sha256', 'expected_choices_sha256',
            'expected_answer_sha256', 'expected_anchor_sha256',
            'expected_question_status', 'expected_answer_status',
        ] as $field) {
            if (!array_key_exists($field, $item)
                || ($matches[0][$field] ?? null) !== $item[$field]) {
                return false;
            }
        }

        return true;
    }

    /** Only complete official chapters are accepted by this repair path. */
    public static function scopeAllows(mixed $scope, string $chapter): bool
    {
        if (is_string($scope)) {
            try {
                $scope = json_decode($scope, true, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return false;
            }
        }
        if (!is_array($scope)) {
            return false;
        }
        foreach ($scope as $entry) {
            if (is_array($entry) && (string) ($entry['number'] ?? '') === $chapter
                && empty($entry['partial'])) {
                return true;
            }
        }
        return false;
    }

    /** Require a SHA-pinned, contiguous map placing this PDF page in this chapter. */
    public static function pageInMap(array $map, string $edition, string $chapter, int $page, string $sha): bool
    {
        $entry = $map['editions'][$edition] ?? null;
        if (!is_array($entry) || ($entry['source_pdf_sha256'] ?? null) !== $sha
            || !is_array($entry['runs'] ?? null) || $entry['runs'] === []) {
            return false;
        }
        foreach ($entry['runs'] as $run) {
            if (is_array($run) && count($run) === 3 && (string) $run[0] === $chapter
                && is_int($run[1]) && is_int($run[2]) && $page >= $run[1] && $page <= $run[2]) {
                return true;
            }
        }
        return false;
    }
}
