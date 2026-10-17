<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

/** Pure identity check for adding a printed label to a bound AI source page. */
final class VerifiedSourcePrintedLabelGuard
{
    public static function auditMatches(array $audit, array $plan, array $item): bool
    {
        if (($audit['format'] ?? null) !== 'fanoos.ai-source-printed-label-audit/1'
            || ($audit['research_only'] ?? null) !== true
            || ($audit['year'] ?? null) !== ($plan['year'] ?? null)
            || ($audit['subject'] ?? null) !== ($plan['subject'] ?? null)
            || !is_array($audit['records'] ?? null)
            || !is_array($plan['entries'] ?? null)
            || count($audit['records']) !== count($plan['entries'])) {
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
        if (count($matches) !== 1 || ($matches[0]['accepted'] ?? null) !== true) {
            return false;
        }

        foreach ([
            'source_id','edition','chapter','pdf_page','printed_page','pdf_sha256',
            'expected_page','expected_pdf_page','expected_pdf_sha256','expected_confidence_page',
            'expected_anchor_sha256','expected_stem_sha256','expected_choices_sha256',
            'expected_answer_sha256','expected_question_status','expected_answer_status',
        ] as $field) {
            if (!array_key_exists($field, $item) || ($matches[0][$field] ?? null) !== $item[$field]) {
                return false;
            }
        }

        return ($matches[0]['chapter_map_page_valid'] ?? null) === true
            && ($matches[0]['pdf_sha_valid'] ?? null) === true
            && ($matches[0]['printed_label_neighbor_check'] ?? null) === true
            && ($matches[0]['scope_page_disposition'] ?? null) === 'in_scope';
    }
}
