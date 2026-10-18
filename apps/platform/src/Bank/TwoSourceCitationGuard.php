<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

/** A source-free residency question whose correct answer needs two exact pages. */
final class TwoSourceCitationGuard
{
    public static function validate(array $plan, array $audit, array $map): bool
    {
        if (($plan['format'] ?? null) !== 'fanoos.two-source-citation-plan/1'
            || ($plan['exam_type'] ?? null) !== 'residency'
            || !is_int($plan['year'] ?? null) || $plan['year'] < 1396 || $plan['year'] > 1500
            || !is_string($plan['subject'] ?? null)
            || preg_match('/^[a-z][a-z0-9-]{1,59}$/D', $plan['subject']) !== 1
            || !is_int($plan['number'] ?? null) || $plan['number'] < 1 || $plan['number'] > 300
            || !self::sha($plan['stem_sha256'] ?? null)
            || !self::sha($plan['choices_sha256'] ?? null)
            || !self::sha($plan['answer_sha256'] ?? null)
            || !is_array($plan['sources'] ?? null) || !array_is_list($plan['sources'])
            || count($plan['sources']) !== 2
            || ($audit['format'] ?? null) !== 'fanoos.research.multi-chapter-original-pdf-evidence/1'
            || ($audit['research_only'] ?? null) !== true
            || ($audit['exam_type'] ?? null) !== 'residency'
            || ($audit['year'] ?? null) !== $plan['year']
            || ($audit['round'] ?? null) !== 1
            || ($audit['question'] ?? null) !== $plan['number']
            || ($audit['source_free_verified'] ?? null) !== true
            || !is_array($audit['supporting_pages'] ?? null)
            || count($audit['supporting_pages']) !== 2) {
            return false;
        }
        $seen = [];
        foreach ($plan['sources'] as $i => $source) {
            if (!is_array($source) || !is_string($source['edition'] ?? null)
                || !preg_match('/^[a-z0-9-]+@[a-z0-9]+$/D', $source['edition'])
                || !is_string($source['chapter'] ?? null)
                || !preg_match('/^[1-9][0-9]{0,2}$/D', $source['chapter'])
                || !is_int($source['pdf_page'] ?? null)
                || $source['pdf_page'] < 1 || $source['pdf_page'] > 6000
                || !self::sha($source['pdf_sha256'] ?? null)
                || !is_string($source['evidence'] ?? null)
                || strlen($source['evidence']) < 16 || strlen($source['evidence']) > 600
                || ($source['edition'] !== ($audit['official_reference'] ?? null))
                || ($source['pdf_sha256'] !== ($audit['approved_pdf_sha256'] ?? null))
                || ($source['chapter'] !== ($audit['supporting_pages'][$i]['chapter'] ?? null))
                || ($source['pdf_page'] !== ($audit['supporting_pages'][$i]['pdf_page'] ?? null))
                || ($source['evidence'] !== ($audit['supporting_pages'][$i]['exact_quote'] ?? null))
                || ($map['editions'][$source['edition']]['source_pdf_sha256'] ?? null) !== $source['pdf_sha256']) {
                return false;
            }
            // Two answer-bearing pages may be in different chapters or in
            // separate parts of one chapter. Reject only a duplicate citation
            // to the same edition, chapter and physical PDF page.
            $pageKey = $source['edition'] . '#ch' . $source['chapter'] . '#pdf' . $source['pdf_page'];
            if (isset($seen[$pageKey])) {
                return false;
            }
            $seen[$pageKey] = true;
            $inside = false;
            foreach ($map['editions'][$source['edition']]['runs'] ?? [] as $run) {
                if (is_array($run) && count($run) === 3
                    && (string) $run[0] === $source['chapter']
                    && $source['pdf_page'] >= $run[1] && $source['pdf_page'] <= $run[2]) {
                    $inside = true;
                }
            }
            if (!$inside) {
                return false;
            }
        }
        return true;
    }

    public static function scopeAllows(?string $json, string $chapter): bool
    {
        if ($json === null) {
            return false;
        }
        try {
            $scope = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }
        foreach (is_array($scope) ? $scope : [] as $entry) {
            if (is_array($entry) && ($entry['number'] ?? null) === $chapter
                && empty($entry['partial'])) {
                return true;
            }
        }
        return false;
    }

    private static function sha(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/D', $value) === 1;
    }
}
