<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

/** Immutable target and audit checks for the two open 1403 operative citations. */
final class Residency1403AiSourceCitationReplacementGuard
{
    /** @var array<int,array<string,mixed>> */
    private const SPECS = [
        152 => [
            'subject' => 'operative-dentistry',
            'audit_path' => '/srv/fanoos/shared/research/classification/recovery-1403-q152-sturdevant-source-research-v2-20261018/classification/reports/q152-sturdevant-v2-original-pdf-audit.json',
            'audit_sha256' => 'c2fdadbef2782869aae32dcebb9608f9bd79930516d0d0fedc8baa97319bb1d3',
            'study_path' => '/srv/fanoos/shared/research/classification/recovery-1403-q152-sturdevant-source-research-v2-20261018/bank-sittings/1403/q152-sturdevant-v2-study.json',
            'study_sha256' => '4d34137895cc7ba5673a200e6f69c092fb67e12759b075723543527e27e27b42',
            'decisions_path' => '/srv/fanoos/shared/research/classification/recovery-1403-q152-sturdevant-source-research-v2-20261018/classification/decisions/1403-operative-dentistry.json',
            'decisions_sha256' => '96b3832e1d3092edcd21828cfef17a758ef6cea598f4db21c07d9f9e148b57da',
            'validated_path' => '/srv/fanoos/shared/research/classification/recovery-1403-q152-sturdevant-source-research-v2-20261018/classification/sittings/1403-q152-sturdevant-v2-validated.json',
            'validated_sha256' => '92a4f658ead4dfe262a0e4588a8ba6b4500480f2ceb3a6083acda088b3e86565',
            'evidence_input_path' => '/srv/fanoos/shared/research/classification/reports/1403-q152-sturdevant-source-citation-page-evidence-20261018.json',
            'evidence_input_sha256' => 'c6ee28b203095c02eec6436be58519e4f095c2f9046248b8d6e3d2a9d3ae53b2',
            'reference' => 'sturdevant-operative@7e',
            'chapter' => '1',
            'pdf_page' => 26,
            'printed_page' => '13',
            'pdf_sha256' => '4ab53ca38252912464f30f165d1ab39e4f0ce907159897f9e15633254acd18ec',
            'confidence' => 0.950,
            'old_reference' => 'sturdevant-operative@7e',
            'old_chapter' => '1',
            'old_pdf_page' => 24,
            'old_pdf_sha256' => '4ab53ca38252912464f30f165d1ab39e4f0ce907159897f9e15633254acd18ec',
            'old_confidence' => '0.800',
            'old_legacy_page' => 'pdf 24',
        ],
        162 => [
            'subject' => 'operative-dentistry',
            'audit_path' => '/srv/fanoos/shared/research/classification/recovery-1403-q162-craig-source-audit-v3-20261018/classification/reports/q162-craig-v3-original-pdf-audit.json',
            'audit_sha256' => 'dc6eb573db2ce3e81087ca8357f68625cc1a837232628fd46f3b31c64a4c7472',
            'study_path' => '/srv/fanoos/shared/research/classification/recovery-1403-q162-craig-source-audit-v3-20261018/bank-sittings/1403/q162-craig-v3-study.json',
            'study_sha256' => 'd75879df2c87b64c3690c064ac89854b95541c8506e64d1d46e1b02432cb0320',
            'decisions_path' => '/srv/fanoos/shared/research/classification/recovery-1403-q162-craig-source-audit-v3-20261018/classification/decisions/1403-operative-dentistry.json',
            'decisions_sha256' => '113305fc2dd82605481b30dc6417c17fa99c50b23eee5e86be3051a8a8004b1d',
            'validated_path' => '/srv/fanoos/shared/research/classification/recovery-1403-q162-craig-source-audit-v3-20261018/classification/sittings/1403-q162-craig-v3-validated.json',
            'validated_sha256' => '2c35879d5afaeb22531b4bb950d3d838752fc47b12d211dfeb7454bd85d0ace5',
            'evidence_input_path' => '/srv/fanoos/shared/research/classification/reports/1403-q162-craig-source-citation-page-evidence-20261018.json',
            'evidence_input_sha256' => '9218f4abc8949174c47ed21440cdb914b87e9b53b5d50653927d9b7d185e36bb',
            'reference' => 'craig-restorative-materials@14e',
            'chapter' => '13',
            'pdf_page' => 288,
            'printed_page' => '277',
            'pdf_sha256' => '333e81ae8a1818cdff57f00e3f870698776e3b8e02fcc49169f397f2fbaf90b9',
            'confidence' => 0.950,
            'old_reference' => 'summit-operative@4e',
            'old_chapter' => '9',
            'old_pdf_page' => 427,
            'old_pdf_sha256' => 'a634d3519e67001151d0d406c3fb91dbeea30ff546c92ed3dec8dfade5a3d6f6',
            'old_confidence' => '0.750',
            'old_legacy_page' => 'pdf 427',
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

    /** Validate that the private research package and its exact-page decision agree. */
    public static function researchMatches(
        int $number,
        array $study,
        array $decisions,
        array $validated,
        array $audit,
        string $studySha256,
        string $decisionsSha256,
        string $validatedSha256,
    ): bool {
        $spec = self::spec($number);
        if ($spec === null
            || $studySha256 !== $spec['study_sha256']
            || $decisionsSha256 !== $spec['decisions_sha256']
            || $validatedSha256 !== $spec['validated_sha256']
            || ($study['format'] ?? null) !== 'fanoos.classification.study-only/1'
            || ($study['exam_type'] ?? null) !== 'residency'
            || ($study['year'] ?? null) !== 1403
            || ($study['round'] ?? null) !== 1
            || !is_array($study['questions'] ?? null) || count($study['questions']) !== 1
            || !is_array($decisions) || count($decisions) !== 1
            || ($validated['format'] ?? null) !== 'fanoos.classification.study-only/1'
            || ($validated['exam_type'] ?? null) !== 'residency'
            || ($validated['year'] ?? null) !== 1403
            || ($validated['round'] ?? null) !== 1
            || !is_array($validated['questions'] ?? null) || count($validated['questions']) !== 1) {
            return false;
        }

        $original = $study['questions'][0];
        $checked = $validated['questions'][0];
        if (!is_array($original) || !is_array($checked)
            || ($original['number'] ?? null) !== $number
            || ($original['subject'] ?? null) !== $spec['subject']
            || array_key_exists('sources', $original)
            || ($checked['number'] ?? null) !== $number
            || ($checked['subject'] ?? null) !== $spec['subject']) {
            return false;
        }
        $validatedSources = $checked['sources'] ?? null;
        unset($checked['sources']);
        if ($checked !== $original || !is_array($validatedSources) || count($validatedSources) !== 1) {
            return false;
        }

        $decision = $decisions[0];
        if (!is_array($decision)
            || ($decision['number'] ?? null) !== $number
            || ($decision['edition'] ?? null) !== $spec['reference']
            || (string) ($decision['chapter'] ?? '') !== $spec['chapter']
            || ($decision['page'] ?? null) !== $spec['pdf_page']
            || !is_numeric($decision['confidence'] ?? null)
            || (float) $decision['confidence'] < 0.85
            || (float) $decision['confidence'] !== $spec['confidence']
            || !is_string($decision['evidence'] ?? null)
            || mb_strlen(trim($decision['evidence'])) < 24
            || !is_array($validatedSources[0] ?? null)
            || !self::validatedSourceMatches($validatedSources[0], $decision, $spec)
            || !Residency1403AiSourceCitationCorrectionGuard::independentAuditMatches(
                [
                    'number' => $number,
                    'subject' => $spec['subject'],
                    'new_ref' => $spec['reference'],
                    'new_ch' => $spec['chapter'],
                    'pdf_page' => $spec['pdf_page'],
                    'pdf_sha' => $spec['pdf_sha256'],
                    'evidence' => $decision['evidence'],
                    'decisions_sha256' => $spec['decisions_sha256'],
                ],
                $audit,
                $decisions,
            )) {
            return false;
        }

        return true;
    }

    /** @param array<string,mixed> $source @param array<string,mixed> $decision @param array<string,mixed> $spec */
    private static function validatedSourceMatches(array $source, array $decision, array $spec): bool
    {
        $reference = $spec['reference'] . '#ch' . str_pad($spec['chapter'], 2, '0', STR_PAD_LEFT);
        return ($source['ref'] ?? null) === $reference
            && ($source['pdf_page'] ?? null) === $spec['pdf_page']
            && strtolower((string) ($source['pdf_sha256'] ?? '')) === $spec['pdf_sha256']
            && ($source['origin'] ?? null) === 'ai'
            && ($source['page'] ?? null) === 'pdf ' . $spec['pdf_page'];
    }

    /** @param list<mixed> $fragments */
    public static function planMatches(int $number, array $plan, array $fragments): bool
    {
        $spec = self::spec($number);
        if ($spec === null || ($plan['format'] ?? null) !== 'fanoos.residency1403.ai-source-citation-replacement-plan/1'
            || ($plan['year'] ?? null) !== 1403
            || ($plan['round'] ?? null) !== 1
            || ($plan['number'] ?? null) !== $number
            || ($plan['subject'] ?? null) !== $spec['subject']
            || ($plan['reference'] ?? null) !== $spec['reference']
            || ($plan['chapter'] ?? null) !== $spec['chapter']
            || ($plan['pdf_page'] ?? null) !== $spec['pdf_page']
            || ($plan['printed_page'] ?? null) !== $spec['printed_page']
            || ($plan['pdf_sha256'] ?? null) !== $spec['pdf_sha256']
            || ($plan['confidence'] ?? null) !== $spec['confidence']
            || ($plan['audit_path'] ?? null) !== $spec['audit_path']
            || ($plan['audit_sha256'] ?? null) !== $spec['audit_sha256']
            || ($plan['evidence_input_path'] ?? null) !== $spec['evidence_input_path']
            || ($plan['evidence_input_sha256'] ?? null) !== $spec['evidence_input_sha256']
            || !is_array($plan['expected_source'] ?? null)
            || !is_string($plan['anchor_text'] ?? null)
            || trim($plan['anchor_text']) === '' || mb_strlen($plan['anchor_text']) > 600
            || !is_array($plan['evidence_fragments'] ?? null)
            || !array_is_list($plan['evidence_fragments'])
            || $plan['evidence_fragments'] !== $fragments
            || $fragments === [] || count($fragments) > 4
            || !in_array($plan['anchor_text'], $fragments, true)) {
            return false;
        }

        foreach ($fragments as $fragment) {
            if (!is_string($fragment) || mb_strlen(trim($fragment)) < 6 || mb_strlen($fragment) > 190) {
                return false;
            }
        }

        $old = $plan['expected_source'];
        return is_string($old['source_id'] ?? null)
            && preg_match('/^[a-f0-9-]{36}$/D', $old['source_id']) === 1
            && is_string($old['anchor_sha256'] ?? null)
            && preg_match('/^[a-f0-9]{64}$/D', $old['anchor_sha256']) === 1
            && ($old['edition'] ?? null) === $spec['old_reference']
            && ($old['chapter'] ?? null) === $spec['old_chapter']
            && ($old['legacy_page'] ?? null) === $spec['old_legacy_page']
            && array_key_exists('printed_page', $old) && $old['printed_page'] === null
            && ($old['pdf_page'] ?? null) === $spec['old_pdf_page']
            && ($old['pdf_sha256'] ?? null) === $spec['old_pdf_sha256']
            && ($old['confidence'] ?? null) === $spec['old_confidence'];
    }
}
