<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

/** Pure guards for protected one-question plans and their independent audits. */
final class SourceChapterCorrectionGuard
{
    /** @return list<string>|null */
    public static function evidenceFragments(mixed $evidence): ?array
    {
        if (!is_string($evidence) || strlen($evidence) < 16 || strlen($evidence) > 190) {
            return null;
        }

        $fragments = preg_split('/\s*(?:\.{3}|…)\s*/u', trim($evidence));
        if (!is_array($fragments) || $fragments === []) {
            return null;
        }

        foreach ($fragments as $fragment) {
            if (!is_string($fragment) || trim($fragment) === '') {
                return null;
            }
            $words = preg_split('/\s+/u', trim($fragment), -1, PREG_SPLIT_NO_EMPTY);
            if (!is_array($words) || count($words) < 4) {
                return null;
            }
        }

        return array_map('trim', $fragments);
    }

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
