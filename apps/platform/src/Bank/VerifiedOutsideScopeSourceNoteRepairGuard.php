<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

/** Pins a correction to an already recorded outside-scope citation note. */
final class VerifiedOutsideScopeSourceNoteRepairGuard
{
    public static function auditMatches(array $audit, array $plan, array $item): bool
    {
        if (($plan['format'] ?? null) !== 'fanoos.outside-scope-source-note-repair/1'
            || ($audit['format'] ?? null) !== 'fanoos.outside-scope-source-note-audit/1'
            || ($audit['research_only'] ?? null) !== true
            || ($audit['year'] ?? null) !== ($plan['year'] ?? null)
            || ($audit['subject'] ?? null) !== ($plan['subject'] ?? null)
            || !is_array($plan['entries'] ?? null)
            || !is_array($audit['records'] ?? null)
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
        if (count($matches) !== 1) {
            return false;
        }
        $record = $matches[0];

        foreach ([
            'source_id', 'question_key', 'edition', 'chapter', 'printed_page', 'pdf_page', 'pdf_sha256',
            'anchor_sha256', 'current_note_sha256', 'stem_sha256', 'choices_sha256', 'answer_sha256',
            'question_status', 'answer_status', 'answer_choice_position', 'answer_alignment_status',
            'page_evidence', 'corrected_note', 'question_identity_verified',
        ] as $field) {
            if (!array_key_exists($field, $item) || ($record[$field] ?? null) !== $item[$field]) {
                return false;
            }
        }

        $note = $item['corrected_note'] ?? null;
        return is_string($note)
            && mb_strlen($note) <= 300
            && str_contains($note, 'خارج از محدوده')
            && str_contains($note, 'تعارض')
            && is_string($item['current_note_sha256'] ?? null)
            && preg_match('/^[a-f0-9]{64}$/D', $item['current_note_sha256']) === 1
            && is_string($item['pdf_sha256'] ?? null)
            && preg_match('/^[a-f0-9]{64}$/D', $item['pdf_sha256']) === 1
            && is_string($item['anchor_sha256'] ?? null)
            && preg_match('/^[a-f0-9]{64}$/D', $item['anchor_sha256']) === 1
            && is_string($item['stem_sha256'] ?? null)
            && preg_match('/^[a-f0-9]{64}$/D', $item['stem_sha256']) === 1
            && is_string($item['choices_sha256'] ?? null)
            && preg_match('/^[a-f0-9]{64}$/D', $item['choices_sha256']) === 1
            && is_string($item['answer_sha256'] ?? null)
            && preg_match('/^[a-f0-9]{64}$/D', $item['answer_sha256']) === 1
            && is_string($item['page_evidence'] ?? null)
            && mb_strlen(trim($item['page_evidence'])) >= 16
            && mb_strlen($item['page_evidence']) <= 190
            && ($item['question_status'] ?? null) === 'published'
            && in_array($item['answer_status'] ?? null, ['final', 'amended'], true)
            && is_int($item['answer_choice_position'] ?? null)
            && ($item['answer_alignment_status'] ?? null) === 'unresolved'
            && str_contains($note, 'کلید رسمی گزینه 2 حفظ شد')
            && ($item['question_identity_verified'] ?? null) === true
            && ($record['question_identity_verified'] ?? null) === true
            && ($record['page_evidence_verified'] ?? null) === true
            && ($record['answer_alignment_reviewed'] ?? null) === true
            && ($record['official_answer_unchanged'] ?? null) === true;
    }
}
