<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

/** Binds multiple page-local citations to one existing human source decision. */
final class VerifiedHumanSourcePageBundleGuard
{
    public static function auditMatches(array $audit, array $plan, array $item): bool
    {
        if (($audit['format'] ?? null) !== 'fanoos.human-source-page-bundle-audit/1'
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
            'source_id','edition','expected_chapter','chapter','pdf_sha256','pages',
            'expected_stem_sha256','expected_choices_sha256','expected_answer_sha256',
            'expected_anchor_sha256','expected_question_status','expected_answer_status',
        ] as $field) {
            if (!array_key_exists($field, $item) || ($matches[0][$field] ?? null) !== $item[$field]) {
                return false;
            }
        }
        return true;
    }
}
