<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

/**
 * Independent, fail-closed identity contract for citations to OFFICIALLY
 * VOIDED questions. A voided question can be scientifically classified
 * without becoming scoreable, published or assigned an answer.
 */
final class VoidedSourceCitationGuard
{
    public static function auditMatches(array $audit, array $plan, array $item): bool
    {
        if (($audit['format'] ?? null) !== 'fanoos.voided-original-pdf-citation-audit/1'
            || ($audit['research_only'] ?? null) !== true
            || ($audit['year'] ?? null) !== ($plan['year'] ?? null)
            || ($audit['subject'] ?? null) !== ($plan['subject'] ?? null)
            || !is_array($audit['records'] ?? null)) {
            return false;
        }

        $found = 0;
        foreach ($audit['records'] as $record) {
            if (!is_array($record) || ($record['number'] ?? null) !== ($item['number'] ?? null)) {
                continue;
            }
            ++$found;
            foreach (['source_id', 'edition', 'chapter', 'printed_page', 'pdf_page',
                'pdf_sha256', 'expected_stem_sha256', 'expected_choices_sha256',
                'expected_answer_sha256', 'evidence'] as $field) {
                if (!array_key_exists($field, $item)
                    || !array_key_exists($field, $record)
                    || $record[$field] !== $item[$field]) {
                    return false;
                }
            }
            if (($record['question_status'] ?? null) !== 'draft'
                || ($record['official_answer_status'] ?? null) !== 'voided'
                || ($record['scientifically_accepted'] ?? null) !== true) {
                return false;
            }
        }

        return $found === 1;
    }

    /** Only this exact source-field subset may be written; never edit grades. */
    public static function allowedSourceFields(): array
    {
        return ['printed_page', 'pdf_page', 'pdf_sha256'];
    }
}
