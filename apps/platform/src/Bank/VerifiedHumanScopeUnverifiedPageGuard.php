<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

/** Binds a physical page location to an explicit unresolved partial-scope audit. */
final class VerifiedHumanScopeUnverifiedPageGuard
{
    public static function auditMatches(array $audit, array $plan, array $item): bool
    {
        if (($audit['format'] ?? null) !== 'fanoos.human-source-scope-unverified-page-audit/1'
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
        if (count($matches) !== 1
            || ($matches[0]['location_accepted'] ?? null) !== true
            || ($matches[0]['scope_unverified'] ?? null) !== true) {
            return false;
        }

        foreach ([
            'source_id','edition','chapter','pdf_page','pdf_sha256','evidence','note',
            'expected_legacy_page','expected_stored_printed_page','expected_anchor_sha256',
            'expected_stem_sha256','expected_choices_sha256','expected_answer_sha256',
            'expected_reviewed_at','expected_reviewed_by_user_id','expected_question_status',
            'expected_answer_status',
        ] as $field) {
            if (!array_key_exists($field, $item) || ($matches[0][$field] ?? null) !== $item[$field]) {
                return false;
            }
        }

        return true;
    }

    /** Partial means the official list explicitly limits the chapter by page/topic. */
    public static function chapterIsPartial(mixed $scope, string $chapter): bool
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
        $matches = array_values(array_filter(
            $scope,
            static fn (mixed $entry): bool => is_array($entry)
                && (string) ($entry['number'] ?? '') === $chapter,
        ));
        return count($matches) === 1
            && is_string($matches[0]['partial'] ?? null)
            && trim($matches[0]['partial']) !== '';
    }
}
