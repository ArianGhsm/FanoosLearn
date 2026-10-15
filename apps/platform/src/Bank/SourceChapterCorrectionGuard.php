<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

/** Pure identity check between a protected one-question plan and its independent audit. */
final class SourceChapterCorrectionGuard
{
    public static function auditMatches(array $audit, array $plan, array $item): bool
    {
        if (($audit['format'] ?? null) !== 'fanoos.classification.provenance-audit/1'
            || ($audit['accepted'] ?? null) !== 1 || ($audit['pending'] ?? null) !== 0
            || ($audit['questions'] ?? null) !== 1 || ($audit['batch_count'] ?? null) !== 1
            || !is_array($audit['batches'] ?? null) || count($audit['batches']) !== 1) {
            return false;
        }
        $batch = $audit['batches'][0];
        $number = $item['number'] ?? null;
        $edition = $item['edition'] ?? null;
        $chapter = $item['chapter'] ?? null;
        if (!is_int($number) || !is_string($edition) || !is_string($chapter)) {
            return false;
        }
        $mapped = $batch['mapped'][(string) $number] ?? null;
        return ($batch['exam_year'] ?? null) === ($plan['year'] ?? null)
            && ($batch['subject'] ?? null) === ($plan['subject'] ?? null)
            && ($batch['question_content_identical'] ?? null) === true
            && ($mapped['ref'] ?? null) === $edition . '#ch' . str_pad($chapter, 2, '0', STR_PAD_LEFT)
            && ($mapped['pdf_page'] ?? null) === ($item['pdf_page'] ?? null)
            && ($batch['source_pdf_provenance'][$edition]['sha256'] ?? null) === ($item['pdf_sha256'] ?? null);
    }
}
