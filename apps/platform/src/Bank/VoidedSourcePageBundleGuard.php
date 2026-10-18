<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

/** Pure, fail-closed plan/audit checks for draft questions with voided keys. */
final class VoidedSourcePageBundleGuard
{
    public static function planMatches(array $plan): bool
    {
        if (($plan['format'] ?? null) !== 'fanoos.verified-voided-source-page-bundle-repair/1'
            || ($plan['exam_type'] ?? null) !== 'residency'
            || !is_int($plan['year'] ?? null) || $plan['year'] < 1396 || $plan['year'] > 1500
            || !is_string($plan['subject'] ?? null)
            || preg_match('/^[a-z][a-z0-9-]{1,59}$/D', $plan['subject']) !== 1
            || !is_array($plan['entries'] ?? null) || !array_is_list($plan['entries'])
            || $plan['entries'] === [] || count($plan['entries']) > 12) {
            return false;
        }

        $numbers = [];
        foreach ($plan['entries'] as $item) {
            if (!is_array($item) || !self::entryIsValid($item)) {
                return false;
            }
            if (isset($numbers[$item['number']])) {
                return false;
            }
            $numbers[$item['number']] = true;
        }

        return true;
    }

    /** Each page and immutable voided-question fingerprint must match the accepted audit. */
    public static function auditMatches(array $audit, array $plan, array $item): bool
    {
        if (($audit['format'] ?? null) !== 'fanoos.voided-source-page-bundle-audit/1'
            || ($audit['research_only'] ?? null) !== true
            || ($audit['year'] ?? null) !== ($plan['year'] ?? null)
            || ($audit['subject'] ?? null) !== ($plan['subject'] ?? null)
            || !is_array($audit['records'] ?? null)
            || !is_array($plan['entries'] ?? null)
            || count($audit['records']) !== count($plan['entries'])) {
            return false;
        }

        $matches = array_values(array_filter(
            $audit['records'],
            static fn (mixed $record): bool => is_array($record)
                && ($record['number'] ?? null) === ($item['number'] ?? null),
        ));
        if (count($matches) !== 1) {
            return false;
        }

        $record = $matches[0];
        if (($record['scientifically_accepted'] ?? null) !== true
            || ($record['question_status'] ?? null) !== 'draft'
            || ($record['official_answer_status'] ?? null) !== 'voided'
            || !array_key_exists('official_answer_choice_position', $record)
            || $record['official_answer_choice_position'] !== null
            || ($record['page_map_valid'] ?? null) !== true
            || ($record['pdf_sha_valid'] ?? null) !== true
            || ($record['printed_page_sequences_valid'] ?? null) !== true
            || ($record['all_page_evidence_valid'] ?? null) !== true
            || ($record['all_pages_in_scope'] ?? null) !== true) {
            return false;
        }

        foreach ([
            'source_id', 'edition', 'chapter', 'primary_pdf_page', 'primary_printed_page',
            'pdf_sha256', 'pages', 'expected_source_page', 'expected_source_printed_page',
            'expected_source_pdf_page', 'expected_source_pdf_sha256', 'expected_source_anchor_sha256',
            'expected_stem_sha256', 'expected_choices_sha256', 'expected_answer_sha256',
            'expected_question_status', 'expected_answer_status', 'expected_source_origin',
            'expected_confidence_source', 'expected_confidence_node', 'expected_confidence_page',
            'accepted_confidence',
        ] as $field) {
            if (!array_key_exists($field, $item)
                || !array_key_exists($field, $record)
                || $record[$field] !== $item[$field]) {
                return false;
            }
        }

        return true;
    }

    /** Only existing citation metadata and additional secondary citations may be written. */
    public static function allowedSourceFields(): array
    {
        return [
            'bank_question_sources.page',
            'bank_question_sources.printed_page',
            'bank_question_sources.pdf_page',
            'bank_question_sources.pdf_sha256',
            'bank_question_sources.anchor_text',
            'bank_question_sources.confidence_source',
            'bank_question_sources.confidence_node',
            'bank_question_sources.confidence_page',
        ];
    }

    /** @param array<string,mixed> $item */
    private static function entryIsValid(array $item): bool
    {
        foreach ([
            'number', 'source_id', 'edition', 'chapter', 'primary_pdf_page', 'primary_printed_page',
            'pdf_sha256', 'pages', 'expected_source_page', 'expected_source_printed_page',
            'expected_source_pdf_page', 'expected_source_pdf_sha256', 'expected_source_anchor_sha256',
            'expected_stem_sha256', 'expected_choices_sha256', 'expected_answer_sha256',
            'expected_question_status', 'expected_answer_status', 'expected_source_origin',
            'expected_source_reviewed_at', 'expected_source_reviewed_by_user_id',
            'expected_source_scope_status', 'expected_source_scope_note', 'expected_scope_unverified',
            'expected_confidence_source', 'expected_confidence_node', 'expected_confidence_page',
            'accepted_confidence',
        ] as $field) {
            if (!array_key_exists($field, $item)) {
                return false;
            }
        }

        if (!is_int($item['number']) || $item['number'] < 1 || $item['number'] > 300
            || !is_string($item['source_id']) || preg_match('/^[a-f0-9-]{36}$/D', $item['source_id']) !== 1
            || !is_string($item['edition']) || preg_match('/^[a-z0-9-]+@[a-z0-9]+$/D', $item['edition']) !== 1
            || !is_string($item['chapter']) || preg_match('/^[0-9]+(?:\.[0-9]+)?$/D', $item['chapter']) !== 1
            || !is_int($item['primary_pdf_page']) || $item['primary_pdf_page'] < 1 || $item['primary_pdf_page'] > 6000
            || !self::printedPageIsValid($item['primary_printed_page'])
            || !self::sha($item['pdf_sha256'])
            || !is_array($item['pages']) || !array_is_list($item['pages'])
            || count($item['pages']) < 2 || count($item['pages']) > 4
            || !is_string($item['expected_source_page'])
            || ($item['expected_source_printed_page'] !== null
                && !self::printedPageIsValid($item['expected_source_printed_page']))
            || !is_string($item['expected_source_pdf_sha256'])
            || !self::sha($item['expected_source_pdf_sha256'])
            || !is_int($item['expected_source_pdf_page']) || $item['expected_source_pdf_page'] < 1
            || $item['expected_source_pdf_page'] !== $item['primary_pdf_page']
            || $item['expected_source_pdf_sha256'] !== $item['pdf_sha256']
            || !self::sha($item['expected_source_anchor_sha256'])
            || !self::sha($item['expected_stem_sha256'])
            || !self::sha($item['expected_choices_sha256'])
            || !self::sha($item['expected_answer_sha256'])
            || $item['expected_question_status'] !== 'draft'
            || $item['expected_answer_status'] !== 'voided'
            || $item['expected_source_origin'] !== 'ai'
            || $item['expected_source_reviewed_at'] !== null
            || $item['expected_source_reviewed_by_user_id'] !== null
            || $item['expected_source_scope_status'] !== 'in_scope'
            || $item['expected_source_scope_note'] !== null
            || $item['expected_scope_unverified'] !== false
            || !self::confidence($item['expected_confidence_source'])
            || !self::confidence($item['expected_confidence_node'])
            || !self::confidence($item['expected_confidence_page'])
            || !is_string($item['accepted_confidence'])
            || !is_numeric($item['accepted_confidence'])
            || (float) $item['accepted_confidence'] < 0.85
            || (float) $item['accepted_confidence'] > 1.0
            || (float) $item['accepted_confidence'] < (float) $item['expected_confidence_source']
            || (float) $item['accepted_confidence'] < (float) $item['expected_confidence_node']
            || (float) $item['accepted_confidence'] < (float) $item['expected_confidence_page']) {
            return false;
        }

        $pages = [];
        $labels = [];
        $primaryCount = 0;
        foreach ($item['pages'] as $page) {
            if (!is_array($page) || !is_int($page['pdf_page'] ?? null)
                || $page['pdf_page'] < 1 || $page['pdf_page'] > 6000
                || !self::printedPageIsValid($page['printed_page'] ?? null)
                || !is_array($page['evidence'] ?? null) || !array_is_list($page['evidence'])
                || $page['evidence'] === [] || count($page['evidence']) > 4
                || array_filter($page['evidence'], static fn (mixed $quote): bool => !is_string($quote)
                    || mb_strlen(trim($quote)) < 6 || mb_strlen($quote) > 190
                    || str_contains($quote, '...') || str_contains($quote, '…')
                    || count(preg_split('/\s+/u', trim($quote), -1, PREG_SPLIT_NO_EMPTY) ?: []) < 4) !== []) {
                return false;
            }
            if (isset($pages[$page['pdf_page']]) || isset($labels[$page['printed_page']])) {
                return false;
            }
            $pages[$page['pdf_page']] = true;
            $labels[$page['printed_page']] = true;
            if ($page['pdf_page'] === $item['primary_pdf_page']) {
                ++$primaryCount;
                if ($page['printed_page'] !== $item['primary_printed_page']) {
                    return false;
                }
            }
        }

        return $primaryCount === 1;
    }

    private static function printedPageIsValid(mixed $page): bool
    {
        return is_string($page) && preg_match('/^[1-9][0-9]{0,3}$/D', $page) === 1;
    }

    private static function sha(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/D', $value) === 1;
    }

    private static function confidence(mixed $value): bool
    {
        return is_string($value) && is_numeric($value)
            && (float) $value >= 0.0 && (float) $value <= 1.0;
    }
}
