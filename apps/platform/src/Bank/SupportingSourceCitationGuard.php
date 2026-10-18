<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

/** Validate an audited supplement to exactly one already-bound source row. */
final class SupportingSourceCitationGuard
{
    public static function validate(array $plan, array $audit, array $map): bool
    {
        if (($plan['format'] ?? null) !== 'fanoos.supporting-source-citation-plan/1'
            || ($plan['exam_type'] ?? null) !== 'residency'
            || !is_int($plan['year'] ?? null) || $plan['year'] < 1396 || $plan['year'] > 1500
            || !is_string($plan['subject'] ?? null)
            || preg_match('/^[a-z][a-z0-9-]{1,59}$/D', $plan['subject']) !== 1
            || !is_int($plan['number'] ?? null) || $plan['number'] < 1 || $plan['number'] > 300
            || !self::sha($plan['stem_sha256'] ?? null)
            || !self::sha($plan['choices_sha256'] ?? null)
            || !self::sha($plan['answer_sha256'] ?? null)
            || !is_array($plan['existing_source'] ?? null)
            || !is_array($plan['supporting_sources'] ?? null)
            || !array_is_list($plan['supporting_sources'])
            || count($plan['supporting_sources']) < 1 || count($plan['supporting_sources']) > 2
            || ($audit['format'] ?? null) !== 'fanoos.research.supporting-source-original-pdf-evidence/1'
            || ($audit['research_only'] ?? null) !== true
            || ($audit['exam_type'] ?? null) !== 'residency'
            || ($audit['year'] ?? null) !== $plan['year']
            || ($audit['round'] ?? null) !== 1
            || ($audit['subject'] ?? null) !== $plan['subject']
            || ($audit['question'] ?? null) !== $plan['number']
            || ($audit['existing_source_verified'] ?? null) !== true
            || !self::sha($audit['study_sha256'] ?? null)
            || !is_array($audit['question_identity_sha256'] ?? null)
            || ($audit['question_identity_sha256']['stem'] ?? null) !== $plan['stem_sha256']
            || ($audit['question_identity_sha256']['choices'] ?? null) !== $plan['choices_sha256']
            || ($audit['question_identity_sha256']['answer'] ?? null) !== $plan['answer_sha256']
            || !is_array($audit['existing_source'] ?? null)
            || !is_array($audit['supporting_pages'] ?? null)
            || count($audit['supporting_pages']) !== count($plan['supporting_sources'])) {
            return false;
        }

        $existing = $plan['existing_source'];
        $auditedExisting = $audit['existing_source'];
        foreach (['source_id', 'edition', 'chapter', 'pdf_page', 'pdf_sha256', 'printed_page', 'anchor_sha256', 'evidence', 'is_primary'] as $field) {
            if (!array_key_exists($field, $existing) || ($existing[$field] ?? null) !== ($auditedExisting[$field] ?? null)) {
                return false;
            }
        }
        if (!is_string($existing['source_id'] ?? null)
            || !preg_match('/^[a-f0-9-]{36}$/D', $existing['source_id'])
            || !is_string($existing['edition'] ?? null)
            || !preg_match('/^[a-z0-9-]+@[a-z0-9]+$/D', $existing['edition'])
            || !self::chapter($existing['chapter'] ?? null)
            || !is_int($existing['pdf_page'] ?? null) || $existing['pdf_page'] < 1 || $existing['pdf_page'] > 6000
            || !self::sha($existing['pdf_sha256'] ?? null)
            || !self::sha($existing['anchor_sha256'] ?? null)
            || !is_string($existing['evidence'] ?? null)
            || strlen($existing['evidence']) < 16 || strlen($existing['evidence']) > 600
            || !self::fourWords($existing['evidence'])
            || ($existing['is_primary'] ?? null) !== true
            || !is_string($existing['printed_page'] ?? null)
            || !preg_match('/^[1-9][0-9]{0,3}(?:\.e[1-9][0-9]?)?$/D', $existing['printed_page'])) {
            return false;
        }

        $locations = [];
        $all = [$existing];
        foreach ($plan['supporting_sources'] as $index => $source) {
            $page = $audit['supporting_pages'][$index] ?? null;
            if (!is_array($source) || !is_array($page)
                || !is_string($source['edition'] ?? null)
                || !preg_match('/^[a-z0-9-]+@[a-z0-9]+$/D', $source['edition'])
                || !self::chapter($source['chapter'] ?? null)
                || !is_int($source['pdf_page'] ?? null) || $source['pdf_page'] < 1 || $source['pdf_page'] > 6000
                || !self::sha($source['pdf_sha256'] ?? null)
                || !is_string($source['evidence'] ?? null)
                || strlen($source['evidence']) < 16 || strlen($source['evidence']) > 600
                || !self::fourWords($source['evidence'])
                || ($page['edition'] ?? null) !== $source['edition']
                || ($page['chapter'] ?? null) !== $source['chapter']
                || ($page['pdf_page'] ?? null) !== $source['pdf_page']
                || ($page['pdf_sha256'] ?? null) !== $source['pdf_sha256']
                || ($page['exact_quote'] ?? null) !== $source['evidence']) {
                return false;
            }
            $all[] = $source;
        }

        foreach ($all as $source) {
            $edition = $source['edition'];
            $chapter = $source['chapter'];
            $page = $source['pdf_page'];
            $sha = $source['pdf_sha256'];
            $location = $edition . '#' . $chapter . ':' . $page;
            if (isset($locations[$location])
                || ($map['editions'][$edition]['source_pdf_sha256'] ?? null) !== $sha) {
                return false;
            }
            $locations[$location] = true;
            $inside = false;
            foreach ($map['editions'][$edition]['runs'] ?? [] as $run) {
                if (is_array($run) && count($run) === 3
                    && (string) $run[0] === $chapter && $page >= $run[1] && $page <= $run[2]) {
                    $inside = true;
                    break;
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

    private static function chapter(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[1-9][0-9]{0,2}$/D', $value) === 1;
    }

    private static function fourWords(string $value): bool
    {
        return preg_match_all('/[\p{L}\p{N}]+/u', $value) >= 4;
    }

    private static function sha(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/D', $value) === 1;
    }
}
