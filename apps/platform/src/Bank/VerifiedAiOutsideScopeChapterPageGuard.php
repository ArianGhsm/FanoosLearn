<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

/** Pure identity and confidence checks for AI-origin outside-scope repairs. */
final class VerifiedAiOutsideScopeChapterPageGuard
{
    public static function auditMatches(array $audit, array $plan, array $item): bool
    {
        if (($audit['format'] ?? null) !== 'fanoos.ai-outside-scope-chapter-page-audit/1'
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
        if (count($matches) !== 1 || ($matches[0]['scientifically_accepted'] ?? null) !== true) {
            return false;
        }

        $record = $matches[0];
        $newConfidence = $item['new_confidence_page'] ?? null;
        if (!is_string($newConfidence)
            || !is_numeric($newConfidence)
            || (float) $newConfidence < 0.85
            || (float) $newConfidence > 1.0
            || ($record['confidence_after_reaudit'] ?? null) !== $newConfidence) {
            return false;
        }

        foreach ([
            'source_id', 'edition', 'expected_chapter', 'chapter', 'printed_page', 'pdf_page',
            'pdf_sha256', 'evidence', 'note', 'expected_legacy_page', 'expected_stored_printed_page',
            'expected_pdf_page', 'expected_pdf_sha256', 'expected_scope_status', 'expected_scope_note',
            'new_confidence_page', 'expected_old_confidence_page',
            'expected_stem_sha256', 'expected_choices_sha256', 'expected_answer_sha256',
            'expected_anchor_sha256', 'expected_question_status', 'expected_answer_status',
        ] as $field) {
            if (!array_key_exists($field, $item) || ($record[$field] ?? null) !== $item[$field]) {
                return false;
            }
        }

        return ($record['pdf_chapter_map_valid'] ?? null) === true
            && ($record['printed_page_sequence_valid'] ?? null) === true
            && ($record['exact_page_evidence_valid'] ?? null) === true
            && is_string($record['answer_alignment'] ?? null)
            && trim($record['answer_alignment']) !== '';
    }

    /** Reject chapters listed in full or partial official year scope. */
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
