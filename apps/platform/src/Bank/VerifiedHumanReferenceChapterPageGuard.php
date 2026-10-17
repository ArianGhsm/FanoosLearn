<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

/** Binds an audited, exact-official-edition correction to a human source row. */
final class VerifiedHumanReferenceChapterPageGuard
{
    public static function auditMatches(array $audit, array $plan, array $item): bool
    {
        if (($audit['format'] ?? null) !== 'fanoos.human-reference-chapter-page-audit/1'
            || ($audit['research_only'] ?? null) !== true
            || ($audit['year'] ?? null) !== ($plan['year'] ?? null)
            || ($audit['subject'] ?? null) !== ($plan['subject'] ?? null)
            || !is_array($audit['records'] ?? null)) {
            return false;
        }
        $number=$item['number']??null;
        if(!is_int($number))return false;
        $matches=array_values(array_filter($audit['records'],static fn(mixed $record):bool=>is_array($record)&&($record['number']??null)===$number));
        if(count($matches)!==1||($matches[0]['scientifically_accepted']??null)!==true)return false;
        foreach ([
            'source_id','expected_edition','edition','expected_chapter','chapter','pdf_page','pdf_sha256','evidence',
            'expected_legacy_page','expected_stored_printed_page','expected_old_pdf_page','expected_old_pdf_sha256',
            'expected_official_scope_status','expected_official_scope_note','expected_stem_sha256',
            'expected_choices_sha256','expected_answer_sha256','expected_anchor_sha256',
            'expected_reviewed_at','expected_reviewed_by_user_id','expected_question_status','expected_answer_status',
        ] as $field) {
            if (!array_key_exists($field, $item) || ($matches[0][$field] ?? null) !== $item[$field]) {
                return false;
            }
        }
        return true;
    }
}
