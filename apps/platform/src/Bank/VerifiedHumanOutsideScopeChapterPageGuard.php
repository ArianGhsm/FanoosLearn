<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

/** Pure identity and syllabus checks for protected human outside-scope repairs. */
final class VerifiedHumanOutsideScopeChapterPageGuard
{
    public static function auditMatches(array $audit, array $plan, array $item): bool
    {
        if (($audit['format'] ?? null) !== 'fanoos.human-outside-scope-chapter-page-audit/1'
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
            'source_id','edition','expected_chapter','chapter','printed_page','pdf_page','pdf_sha256',
            'allow_long_header','note','evidence','expected_stem_sha256','expected_choices_sha256',
            'expected_answer_sha256','expected_anchor_sha256','expected_question_status',
            'expected_answer_status','expected_official_scope_status','expected_official_scope_note',
        ] as $field) {
            if (!array_key_exists($field, $item) || ($matches[0][$field] ?? null) !== $item[$field]) {
                return false;
            }
        }
        return true;
    }

    /** Reject any chapter listed in full or partial official scope. */
    public static function chapterIsOutOfScope(mixed $scope, string $chapter): bool
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
            if (is_array($entry) && (string) ($entry['number'] ?? '') === $chapter) {
                return false;
            }
        }
        return true;
    }
}
