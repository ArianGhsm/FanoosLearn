<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

/** Fail-closed checks shared by the 1403 AI-source repair CLI and its tests. */
final class Residency1403AiSourceCitationCorrectionGuard
{
    public const CANONICAL_SHA256 = '5a903b491496ed3b7f912dde0fdc37607ff102f33b36a9c5e723baab19a1fcc7';
    public const RESEARCH_MANIFEST_SHA256 = '1285dd66b7de3c967534ec15b483e5e8f8a0c92b6ddc56022a4d85e755f6c3ab';
    public const RESEARCH_MANIFEST_PATH = '/srv/fanoos/shared/research/tmp/1403-source-page-expansion-20261011/residency1403-research-153-versus-live71-reconciliation-20261012.json';
    public const PENDING_QUESTION_NUMBERS = [61, 62, 63, 68, 69, 127];

    public static function isPrivateFile(
        string $path,
        string $trustedRoot,
        bool $strictPathPermissions = true,
    ): bool
    {
        $root = realpath($trustedRoot);
        $file = realpath($path);
        if ($root === false || $root !== $trustedRoot || !is_dir($root) || is_link($trustedRoot)
            || ($rootMode = fileperms($root)) === false || ($rootMode & 0077) !== 0
            || $file === false || $file !== $path || is_link($path) || !is_file($file)
            || !str_starts_with($file, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            return false;
        }
        if ($strictPathPermissions
            && (($fileMode = fileperms($file)) === false || ($fileMode & 0077) !== 0)) {
            return false;
        }

        $directory = dirname($file);
        while ($directory !== $root) {
            if (!str_starts_with($directory, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
                || is_link($directory) || !is_dir($directory) || realpath($directory) !== $directory) {
                return false;
            }
            if ($strictPathPermissions
                && (($mode = fileperms($directory)) === false || ($mode & 0077) !== 0)) {
                return false;
            }
            $parent = dirname($directory);
            if ($parent === $directory) {
                return false;
            }
            $directory = $parent;
        }

        return true;
    }

    /**
     * This is the immutable 2026-10-12 reconciliation snapshot. Its original
     * live count is historical; per-question DB compare-and-swap checks still
     * decide whether a specific candidate remains unpublished.
     *
     * @param array<string,mixed> $manifest
     */
    public static function researchManifestMatches(array $manifest): bool
    {
        $pending = $manifest['clinical_pending_numbers'] ?? null;
        if (!is_array($pending) || !array_is_list($pending)
            || array_filter($pending, static fn (mixed $number): bool => !is_int($number)) !== []) {
            return false;
        }
        sort($pending);

        return ($manifest['format'] ?? null) === 'fanoos.residency1403.research-only-source-completeness/1'
            && ($manifest['research_only'] ?? null) === true
            && ($manifest['question_count'] ?? null) === 250
            && ($manifest['canonical_source_sha256'] ?? null) === self::CANONICAL_SHA256
            && ($manifest['live_paired_unchanged'] ?? null) === 71
            && ($manifest['english_final_exempt'] ?? null) === 20
            && ($manifest['research_audited_not_published'] ?? null) === 153
            && ($manifest['clinical_pending'] ?? null) === 6
            && $pending === self::PENDING_QUESTION_NUMBERS
            && ($manifest['db_updates_performed'] ?? null) === 0
            && ($manifest['assessment_updates_performed'] ?? null) === 0;
    }

    /**
     * @param array<string,mixed> $item
     * @param array<string,mixed> $audit
     * @param array<mixed> $decisions
     */
    public static function independentAuditMatches(array $item, array $audit, array $decisions): bool
    {
        $number = $item['number'] ?? null;
        $subject = $item['subject'] ?? null;
        $reference = $item['new_ref'] ?? null;
        $chapter = $item['new_ch'] ?? null;
        $page = $item['pdf_page'] ?? null;
        $pdfSha = $item['pdf_sha'] ?? null;
        $evidence = $item['evidence'] ?? null;
        $decisionsSha = $item['decisions_sha256'] ?? null;
        if (!is_int($number) || $number < 1 || $number > 250
            || !is_string($subject) || !preg_match('/^[a-z][a-z0-9-]{1,59}$/D', $subject)
            || !is_string($reference) || !preg_match('/^[a-z0-9-]+@[a-z0-9]+$/D', $reference)
            || !is_string($chapter) || !preg_match('/^[0-9]{1,3}$/D', $chapter)
            || !is_int($page) || $page < 1 || $page > 6000
            || !is_string($pdfSha) || !preg_match('/^[a-f0-9]{64}$/D', $pdfSha)
            || !is_string($evidence) || mb_strlen(trim($evidence)) < 24 || mb_strlen($evidence) > 190
            || !is_string($decisionsSha) || !preg_match('/^[a-f0-9]{64}$/D', $decisionsSha)) {
            return false;
        }

        if (($audit['format'] ?? null) !== 'fanoos.classification.provenance-audit/1'
            || ($audit['research_only'] ?? null) !== true
            || ($audit['batch_count'] ?? null) !== 1
            || !is_int($audit['questions'] ?? null) || $audit['questions'] < 1
            || !is_int($audit['accepted'] ?? null) || $audit['accepted'] < 1
            || !is_int($audit['pending'] ?? null) || $audit['pending'] < 0
            || $audit['accepted'] + $audit['pending'] !== $audit['questions']
            || !is_array($audit['batches'] ?? null) || count($audit['batches']) !== 1) {
            return false;
        }

        $batch = $audit['batches'][0];
        if (!is_array($batch)) {
            return false;
        }
        $mapped = $batch['mapped'][(string) $number] ?? null;
        $pdf = $batch['source_pdf_provenance'][$reference] ?? null;
        $batchName = $batch['batch'] ?? null;
        if (!is_array($mapped) || !is_array($pdf)
            || !is_string($batchName) || !str_starts_with($batchName, "1403:{$subject}:")
            || ($batch['subject'] ?? null) !== $subject
            || ($batch['exam_year'] ?? null) !== 1403
            || ($batch['classification_basis'] ?? null) !== 'official_exam_year'
            || ($batch['question_content_identical'] ?? null) !== true
            || !is_int($batch['questions'] ?? null) || $batch['questions'] < 1
            || !is_int($batch['accepted'] ?? null) || $batch['accepted'] < 1
            || !is_int($batch['pending'] ?? null) || $batch['pending'] < 0
            || $batch['accepted'] + $batch['pending'] !== $batch['questions']
            || ($batch['reference_years'] ?? null) !== [1403]
            || ($batch['decisions_sha256'] ?? null) !== $decisionsSha
            || ($mapped['ref'] ?? null) !== $reference . '#ch' . str_pad($chapter, 2, '0', STR_PAD_LEFT)
            || ($mapped['pdf_page'] ?? null) !== $page
            || ($pdf['source'] ?? null) !== 'verified-server-pdf'
            || ($pdf['sha256'] ?? null) !== $pdfSha) {
            return false;
        }

        $matches = array_values(array_filter($decisions, static fn (mixed $decision): bool =>
            is_array($decision) && ($decision['number'] ?? null) === $number));
        if (count($matches) !== 1) {
            return false;
        }
        $decision = $matches[0];

        return ($decision['edition'] ?? null) === $reference
            && (string) ($decision['chapter'] ?? '') === $chapter
            && ($decision['page'] ?? null) === $page
            && ($decision['evidence'] ?? null) === $evidence;
    }
}
