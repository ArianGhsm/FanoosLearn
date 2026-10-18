<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

/** Pure guards for source-free AI citations whose whole chapter is out of scope. */
final class VerifiedAiOutsideScopeSourceInsertGuard
{
    public static function hasExplicitOutsideScopeNote(mixed $note): bool
    {
        return is_string($note)
            && mb_strlen(trim($note)) >= 24
            && str_contains($note, 'خارج از محدوده');
    }

    /**
     * Require a structured official chapter list and a chapter absent from
     * that list. Partial-chapter page exceptions use their own page guard.
     */
    public static function chapterIsEntirelyOutsideScope(mixed $scope, string $chapter): bool
    {
        if (is_string($scope)) {
            try {
                $scope = json_decode($scope, true, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return false;
            }
        }
        if (!is_array($scope) || $scope === []) {
            return false;
        }
        foreach ($scope as $item) {
            if (!is_array($item) || !is_string($item['number'] ?? null)
                && !is_int($item['number'] ?? null)) {
                return false;
            }
            if ((string) $item['number'] === $chapter) {
                return false;
            }
        }
        return true;
    }

    public static function auditMatches(array $audit, string $batch, array $item): bool
    {
        if (($audit['format'] ?? null) !== 'fanoos.classification.provenance-audit/1'
            || ($audit['research_only'] ?? null) !== true
            || !is_array($audit['batches'] ?? null)) {
            return false;
        }
        $batches = array_values(array_filter(
            $audit['batches'],
            static fn (mixed $record): bool => is_array($record)
                && ($record['batch'] ?? null) === $batch,
        ));
        if (count($batches) !== 1 || ($batches[0]['question_content_identical'] ?? null) !== true) {
            return false;
        }
        $number = $item['number'] ?? null;
        $edition = $item['edition'] ?? null;
        $chapter = $item['chapter'] ?? null;
        $page = $item['pdf_page'] ?? null;
        $sha = $item['pdf_sha256'] ?? null;
        $confidence = $item['confidence'] ?? null;
        if (!is_int($number) || !is_string($edition) || !is_string($chapter)
            || !ctype_digit($chapter) || !is_int($page) || !is_string($sha)
            || !is_numeric($confidence) || (float) $confidence < 0.85) {
            return false;
        }
        $chapterKey = 'ch' . str_pad($chapter, 2, '0', STR_PAD_LEFT);
        $mapped = $batches[0]['mapped'][(string) $number] ?? null;
        $provenance = $batches[0]['source_pdf_provenance'][$edition] ?? null;
        return is_array($mapped)
            && ($mapped['ref'] ?? null) === $edition . '#' . $chapterKey
            && ($mapped['pdf_page'] ?? null) === $page
            && abs((float) ($mapped['confidence_source'] ?? 0) - (float) $confidence) <= 0.0005
            && abs((float) ($mapped['confidence_node'] ?? 0) - (float) $confidence) <= 0.0005
            && abs((float) ($mapped['confidence_page'] ?? 0) - (float) $confidence) <= 0.0005
            && is_array($provenance)
            && ($provenance['source'] ?? null) === 'verified-server-pdf'
            && ($provenance['sha256'] ?? null) === $sha;
    }
}
