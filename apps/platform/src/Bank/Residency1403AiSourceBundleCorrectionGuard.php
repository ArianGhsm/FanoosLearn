<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

/** Pins the only two audited 1403 AI-source bundles to their original PDFs. */
final class Residency1403AiSourceBundleCorrectionGuard
{
    public const CANONICAL_SHA256 = '5a903b491496ed3b7f912dde0fdc37607ff102f33b36a9c5e723baab19a1fcc7';
    public const RESEARCH_MANIFEST_SHA256 = '1285dd66b7de3c967534ec15b483e5e8f8a0c92b6ddc56022a4d85e755f6c3ab';
    public const RESEARCH_MANIFEST_PATH = '/srv/fanoos/shared/research/tmp/1403-source-page-expansion-20261011/residency1403-research-153-versus-live71-reconciliation-20261012.json';
    public const EVIDENCE_PATH = '/srv/fanoos/shared/research/classification/reports/residency1403-ai-source-bundle-original-pdf-evidence-20261017-v4.json';
    public const EVIDENCE_SHA256 = 'f664186e2ce13424d43f405ec58ba62ed50f92c7cd81e1e2003b99f8e0e64d1c';
    public const SUPPLEMENTARY_RESEARCH_PATH = '/srv/fanoos/shared/research/tmp/1403-source-page-expansion-20261011/audit-remaining-findings-20261012/classification/reports/1403-four-original-research-plus-secondary-page-evidence-20261012.json';
    public const SUPPLEMENTARY_RESEARCH_SHA256 = '493da3bf677c446197e1915121e90631cf482fc7b3af485be256073a70608b9e';
    public const LOCK_NAME = 'fanoos:1403:ai-source-bundle-correction';

    /** @var array<int,array<string,mixed>> */
    private const SPECS = [
        34 => [
            'subject' => 'endodontics',
            'edition' => 'torabinejad-endodontics@6e',
            'pdf_sha256' => '09333f079cb3300550cc0702985aae216d952eacabdaac005e1c66fafebe13e0',
            'primary_research_path' => '/srv/fanoos/shared/research/tmp/1403-source-page-expansion-20261011/audit-remaining-findings-20261012/classification/reports/1403-endodontics-exact-original-independent-20261012.json',
            'primary_research_sha256' => '4653dc402cb3f0085aad44adff809d5cee2faafc33b28295bd099604ec221124',
            'decisions_path' => '/srv/fanoos/shared/research/tmp/1403-source-page-expansion-20261011/audit-remaining-findings-20261012/classification/decisions/1403-endodontics.json',
            'decisions_sha256' => 'c7bcdc98939ca7b353a533f22b247bf276ebdbc079bd0a3f76b0a23f78746cd3',
            'validated_study_path' => '/srv/fanoos/shared/research/tmp/1403-source-page-expansion-20261011/audit-remaining-findings-20261012/classification/sittings/1403-endo-final-question-study-20261012-validated.json',
            'validated_study_sha256' => '989aecb72695985cd4ece20717ae7b5a285e37851e49d4068ae6a31d93896669',
            'pages' => [['chapter' => '8', 'pdf_page' => 148, 'primary' => true], ['chapter' => '8', 'pdf_page' => 153, 'primary' => false]],
        ],
        119 => [
            'subject' => 'periodontics',
            'edition' => 'carranza-periodontology@13e',
            'pdf_sha256' => 'f0e411898ae010688ca5c0d21afe312cef6f5dae86d0e2e45648bc51ca8e2adf',
            'primary_research_path' => '/srv/fanoos/shared/research/tmp/1403-source-page-expansion-20261011/audit-remaining-findings-20261012/classification/reports/1403-periodontics-exact-original-independent-20261012.json',
            'primary_research_sha256' => '5eb2e2e07e7db10250b9500ef8e0e43b1e1c6b7f6a1cf854d6bf01d69c9088ab',
            'decisions_path' => '/srv/fanoos/shared/research/tmp/1403-source-page-expansion-20261011/audit-remaining-findings-20261012/classification/decisions/1403-periodontics.json',
            'decisions_sha256' => 'ad167acd70016352b7dcd110dd4595ce62c474d7360cb4e2539483fc6f0858ab',
            'validated_study_path' => '/srv/fanoos/shared/research/tmp/1403-source-page-expansion-20261011/audit-remaining-findings-20261012/classification/sittings/1403-peri-final-question-study-20261012-validated.json',
            'validated_study_sha256' => '8afd66629cabd1e789c8b8fc5fcb695e15b361fa2ef83e76d52518342c8b7a44',
            'pages' => [['chapter' => '5', 'pdf_page' => 133, 'primary' => true], ['chapter' => '13', 'pdf_page' => 537, 'primary' => false]],
        ],
    ];

    /** @return array<string,mixed>|null */
    public static function spec(int $number): ?array
    {
        return self::SPECS[$number] ?? null;
    }

    /** @return list<int> */
    public static function supportedQuestions(): array
    {
        return array_keys(self::SPECS);
    }

    /** Validate the private research record's immutable 1403 bundle scope. */
    public static function evidenceAuditMatches(array $audit): bool
    {
        if (($audit['format'] ?? null) !== 'fanoos.residency1403.ai-source-bundle-original-pdf-evidence/1'
            || ($audit['research_only'] ?? null) !== true
            || ($audit['no_production_source_mutation'] ?? null) !== true
            || ($audit['exam_year'] ?? null) !== 1403
            || ($audit['supplementary_research_path'] ?? null) !== self::SUPPLEMENTARY_RESEARCH_PATH
            || ($audit['supplementary_research_sha256'] ?? null) !== self::SUPPLEMENTARY_RESEARCH_SHA256
            || !is_array($audit['records'] ?? null) || !array_is_list($audit['records'])
            || count($audit['records']) !== count(self::SPECS)) {
            return false;
        }

        $seen = [];
        foreach ($audit['records'] as $record) {
            if (!is_array($record) || !is_int($record['number'] ?? null)
                || isset($seen[$record['number']])) {
                return false;
            }
            $seen[$record['number']] = true;
            $spec = self::spec($record['number']);
            if ($spec === null || ($record['subject'] ?? null) !== $spec['subject']
                || ($record['edition'] ?? null) !== $spec['edition']
                || ($record['pdf_sha256'] ?? null) !== $spec['pdf_sha256']) {
                return false;
            }
            foreach ([
                'primary_research_path' => 'primary_research_path',
                'primary_research_sha256' => 'primary_research_sha256',
                'decisions_path' => 'decisions_path',
                'decisions_sha256' => 'decisions_sha256',
                'validated_study_path' => 'validated_study_path',
                'validated_study_sha256' => 'validated_study_sha256',
            ] as $field => $specField) {
                if (($record[$field] ?? null) !== $spec[$specField]) {
                    return false;
                }
            }
            if (!is_string($record['primary_research_evidence'] ?? null)
                || mb_strlen(trim($record['primary_research_evidence'])) < 24
                || mb_strlen($record['primary_research_evidence']) > 190
                || !is_string($record['scientific_disambiguation'] ?? null)
                || trim($record['scientific_disambiguation']) === ''
                || !is_array($record['pages'] ?? null) || !array_is_list($record['pages'])
                || count($record['pages']) !== count($spec['pages'])) {
                return false;
            }
            foreach ($spec['pages'] as $index => $expected) {
                $page = $record['pages'][$index] ?? null;
                if (!is_array($page) || ($page['chapter'] ?? null) !== $expected['chapter']
                    || ($page['pdf_page'] ?? null) !== $expected['pdf_page']
                    || ($page['primary'] ?? null) !== $expected['primary']
                    || !is_array($page['evidence'] ?? null) || !array_is_list($page['evidence'])
                    || count($page['evidence']) < 1 || count($page['evidence']) > 4) {
                    return false;
                }
                foreach ($page['evidence'] as $quote) {
                    if (!is_string($quote) || mb_strlen(trim($quote)) < 6 || strlen($quote) > 190) {
                        return false;
                    }
                }
            }
        }
        $seenNumbers = array_keys($seen);
        sort($seenNumbers);
        return $seenNumbers === self::supportedQuestions();
    }

    /** Bind each prepared source bundle to its one pinned evidence record. */
    public static function entryMatchesEvidence(array $audit, array $entry): bool
    {
        if (!self::evidenceAuditMatches($audit)) {
            return false;
        }
        $number = $entry['number'] ?? null;
        if (!is_int($number)) {
            return false;
        }
        $matches = array_values(array_filter(
            $audit['records'],
            static fn (mixed $record): bool => is_array($record) && ($record['number'] ?? null) === $number,
        ));
        if (count($matches) !== 1) {
            return false;
        }
        $record = $matches[0];
        foreach ([
            'subject','edition','pdf_sha256','primary_research_path','primary_research_sha256',
            'decisions_path','decisions_sha256','validated_study_path','validated_study_sha256',
            'primary_research_evidence','scientific_disambiguation','pages',
        ] as $field) {
            if (!array_key_exists($field, $entry) || ($record[$field] ?? null) !== $entry[$field]) {
                return false;
            }
        }
        return true;
    }
}
