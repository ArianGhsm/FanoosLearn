<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

/** Exact, audited source-citation replacements for the supported 1401/1402 rows. */
final class ResidencyAiSourceCitationReplacementGuard
{
    /** @var array<int,array<string,mixed>> */
    private const SPECS = [
        139 => [
            'year' => 1402,
            'subject' => 'periodontics',
            'stem_sha256' => '135de36f7a19697892436ec03757e00ea25a701bdbcb77af353452f204429b78',
            'choices_sha256' => '130db2fbd2cf0eef8dca766eddd20f1cd4ed24374cf82cd25083e588a1393c16',
            'answer_sha256' => 'd99140b505ae44ee35fc6950e8a72a99040471d1e38b8572f876faf5b170edb9',
            'answer_choice' => 2,
            'source_id' => '01a11b0f-a157-7fab-846d-72bde05e5622',
            'old_reference' => 'carranza-periodontology@13e',
            'old_chapter' => '34',
            'old_legacy_page' => '412.e2',
            'old_printed_page' => '412.e2',
            'old_pdf_page' => 892,
            'old_pdf_sha256' => 'f0e411898ae010688ca5c0d21afe312cef6f5dae86d0e2e45648bc51ca8e2adf',
            'old_anchor_sha256' => '56c35cf8277b15fabd277c166ec5b2357581ba3cbe8d4d2c5fdac867b4fd82a8',
            'old_confidence' => '0.900',
            'old_official_scope_status' => 'outside_scope_exam_question',
            'old_official_scope_note_sha256' => 'af20fd1be878f445b582946e3a4db84be3208de64e083d5c419cf5ae3db7cfe5',
            'reference' => 'carranza-periodontology@13e',
            'chapter' => '28',
            'pdf_page' => 785,
            'printed_page' => '353',
            'pdf_sha256' => 'f0e411898ae010688ca5c0d21afe312cef6f5dae86d0e2e45648bc51ca8e2adf',
            'evidence' => 'Familiar aggregation of aggressive peri- ... advisable to verify similar cases in the family',
            'note' => 'Carranza 13e chapter 28, printed page 353 / PDF 785, describes familial aggregation and recommends verifying similar family cases. The page does not specify first-degree relatives; preserve official final choice 2 and record that qualifier as an inference.',
            'confidence' => 0.900,
        ],
        165 => [
            'year' => 1401,
            'subject' => 'operative-dentistry',
            'stem_sha256' => '21365d473541477aac5b6b871fd2d75bdac4e3b970812cd5b5f7561c75752a64',
            'choices_sha256' => '1d56e451c8d02c428619ef24b95e93e20e5f9956bb28929d695f437b57c0e343',
            'answer_sha256' => 'f3f070afa8ad5df580cdec18672f8a8a762ce4a246ea20368622cf59b1a15794',
            'answer_choice' => 3,
            'source_id' => '01a11be7-9b0a-7a10-ae22-ebb6236b36cc',
            'old_reference' => 'summit-operative@4e',
            'old_chapter' => '9',
            'old_legacy_page' => 'pdf 476',
            'old_printed_page' => null,
            'old_pdf_page' => 476,
            'old_pdf_sha256' => 'a634d3519e67001151d0d406c3fb91dbeea30ff546c92ed3dec8dfade5a3d6f6',
            'old_anchor_sha256' => 'f48bf520cf951de997509393be0b22f9e6f063952f1ba395a69a591196777292',
            'old_confidence' => '0.800',
            'old_official_scope_status' => 'in_scope',
            'old_official_scope_note_sha256' => null,
            'reference' => 'craig-restorative-materials@14e',
            'chapter' => '13',
            'pdf_page' => 290,
            'printed_page' => '279',
            'pdf_sha256' => '333e81ae8a1818cdff57f00e3f870698776e3b8e02fcc49169f397f2fbaf90b9',
            'evidence' => 'With these systems, the smear layer is dis- ... solved and incorporated into the hybrid layer',
            'note' => 'Craig 14e chapter 13, printed page 279 / PDF 290, says self-etch systems dissolve and incorporate the smear layer. This supports the self-etch mechanism but does not distinguish two-step from one-step self-etch; preserve official choice 3 and record the specificity conflict.',
            'confidence' => 0.900,
        ],
        168 => [
            'year' => 1401,
            'subject' => 'operative-dentistry',
            'stem_sha256' => '9894149f16b8c4f5160b22b131066fe0b1488f1bc5f52116e6159832c9b9926b',
            'choices_sha256' => '447a03a20ec9ba71dcd5f7d09b4b537256cb97cda1beecbddbc7864a8d74041a',
            'answer_sha256' => '23b0f883c3f687ed853c0b4f115570b444e98a796af732fd08e8095a8ad28215',
            'answer_choice' => 3,
            'source_id' => '01a11be7-9b21-7284-8862-7a777020edd6',
            'old_reference' => 'summit-operative@4e',
            'old_chapter' => '9',
            'old_legacy_page' => 'pdf 438',
            'old_printed_page' => null,
            'old_pdf_page' => 438,
            'old_pdf_sha256' => 'a634d3519e67001151d0d406c3fb91dbeea30ff546c92ed3dec8dfade5a3d6f6',
            'old_anchor_sha256' => 'd6cf18397643df3dcedd11a7b0a45be993777ce73566556aeb0fb1b40058ce19',
            'old_confidence' => '0.900',
            'old_official_scope_status' => 'in_scope',
            'old_official_scope_note_sha256' => null,
            'reference' => 'craig-restorative-materials@14e',
            'chapter' => '13',
            'pdf_page' => 290,
            'printed_page' => '279',
            'pdf_sha256' => '333e81ae8a1818cdff57f00e3f870698776e3b8e02fcc49169f397f2fbaf90b9',
            'evidence' => 'Self-etch systems have the great advantages of ... initial acidity and high water content',
            'note' => 'Craig 14e chapter 13, printed page 279 / PDF 290, identifies high water content as an advantage of self-etch systems. Preserve official final choice 3.',
            'confidence' => 0.950,
        ],
        175 => [
            'year' => 1402,
            'subject' => 'operative-dentistry',
            'stem_sha256' => '384991e96437bcb328e44fff014a07a5d58ba0f14dc4ae1e780893e525f00311',
            'choices_sha256' => '59d80234cac471fe5445295af65a11a5c3b44a0b4e33b4ded1ca7a4bfc3bf71f',
            'answer_sha256' => '955c6627f3adfbcf8f1e9b39108431a0d82c90e44157f5eb0289b74b29bde510',
            'answer_choice' => 3,
            'source_id' => '01a11b0f-a231-7abd-bac7-84996734e589',
            'old_reference' => 'summit-operative@4e',
            'old_chapter' => '9',
            'old_legacy_page' => '2',
            'old_printed_page' => '2',
            'old_pdf_page' => null,
            'old_pdf_sha256' => null,
            'old_anchor_sha256' => 'c7b72d1152c945076e492fd723a816bf2069cb3af670ab418712b5fb30d323ef',
            'old_confidence' => '0.850',
            'old_official_scope_status' => 'in_scope',
            'old_official_scope_note_sha256' => null,
            'reference' => 'craig-restorative-materials@14e',
            'chapter' => '13',
            'pdf_page' => 288,
            'printed_page' => '277',
            'pdf_sha256' => '333e81ae8a1818cdff57f00e3f870698776e3b8e02fcc49169f397f2fbaf90b9',
            'evidence' => 'three-step etch-and-rinse systems as a group remain the “gold standard” for adhesive systems, in both laboratory and clinical evaluations',
            'note' => 'Craig 14e chapter 13, printed page 277 / PDF 288, directly identifies three-step etch-and-rinse systems as the gold standard. Preserve official final choice 3.',
            'confidence' => 0.95,
        ],
        190 => [
            'year' => 1402,
            'subject' => 'operative-dentistry',
            'stem_sha256' => '879d051bc4e280d5311b0f014f83adbac121d3b7669e06929b3e38dea51f2fa7',
            'choices_sha256' => 'e00b9b3f3e6c3b896bede6aa41d79c405d4fc82cb65f0693099653456649f417',
            'answer_sha256' => 'bad5084d87dd35adc3c2fb945dee8e5c72be5703361b221daa63b7cfdb27b4fb',
            'answer_choice' => 3,
            'source_id' => '01a11b0f-a28c-7da3-8039-d054645e11c3',
            'old_reference' => 'summit-operative@4e',
            'old_chapter' => '9',
            'old_legacy_page' => 'pdf 450',
            'old_printed_page' => null,
            'old_pdf_page' => 450,
            'old_pdf_sha256' => 'a634d3519e67001151d0d406c3fb91dbeea30ff546c92ed3dec8dfade5a3d6f6',
            'old_anchor_sha256' => 'c5919929151a7f59626d5af7c16f3b3972ed8b960f9cfb376f17347a878fa9f6',
            'old_confidence' => '0.600',
            'old_official_scope_status' => 'in_scope',
            'old_official_scope_note_sha256' => null,
            'reference' => 'craig-restorative-materials@14e',
            'chapter' => '13',
            'pdf_page' => 288,
            'printed_page' => '277',
            'pdf_sha256' => '333e81ae8a1818cdff57f00e3f870698776e3b8e02fcc49169f397f2fbaf90b9',
            'evidence' => 'contains some prismless enamel that has not yet worn away and requires longer etching times (20 to 30 seconds)',
            'note' => 'Craig 14e chapter 13, printed page 277 / PDF 288, directly gives 20 to 30 seconds for deciduous unground enamel. Preserve official final choice 3.',
            'confidence' => 0.95,
        ],
        244 => [
            'year' => 1402,
            'subject' => 'dental-materials',
            'stem_sha256' => 'c696829eb9e906464a9dd460514ac6885c44be0b87d89751b8b360598c62d20b',
            'choices_sha256' => '4d88ce4708f3a9b0596728b389e84e5b0b19fc2f31295193a5b8c0932814ab61',
            'answer_sha256' => '18eb8874bb78dba77c2dd6b91ce112e085c364f8c52e942cc6fdb4257e30050f',
            'answer_choice' => 3,
            'source_id' => '01a11b0f-a346-780b-a249-8db4bb80bd14',
            'old_reference' => 'van-noort-materials@4e',
            'old_chapter' => '2.2',
            'old_legacy_page' => '74',
            'old_printed_page' => '74',
            'old_pdf_page' => null,
            'old_pdf_sha256' => null,
            'old_anchor_sha256' => '15239a7d2b0e69b3b1168c1c95335ac268a1ba7b0bbf825bca88bc95f667cdad',
            'old_confidence' => '0.400',
            'old_official_scope_status' => 'in_scope',
            'old_official_scope_note_sha256' => null,
            'reference' => 'powers-wataha-materials@11e',
            'chapter' => '4',
            'pdf_page' => 67,
            'printed_page' => '49',
            'pdf_sha256' => 'df1b471c7c2949422cbe9f883a792800cb811e39c7217dbae781084df8478399',
            'evidence' => 'particles with a filler loading of 66% to 70% by volume',
            'note' => 'Powers and Wataha 11e chapter 4, printed page 49 / PDF 67, says 66–70% by volume; the official option says by weight. That unit difference supports the keyed “not correct” option 3. Preserve the official final key.',
            'confidence' => 0.95,
        ],
    ];

    /** @return array<string,mixed>|null */
    public static function spec(int $number): ?array
    {
        return self::SPECS[$number] ?? null;
    }

    /** @return list<int> */
    public static function supportedQuestions(?int $year = null): array
    {
        if ($year === null) {
            return array_keys(self::SPECS);
        }
        return array_keys(array_filter(self::SPECS,static fn(array $spec):bool=>$spec['year']===$year));
    }

    public static function modeArgumentsValid(bool $apply, bool $hasBackup, bool $hasReceipt, bool $hasPreview): bool
    {
        return $apply
            ? $hasBackup && $hasReceipt && $hasPreview
            : !$hasBackup && !$hasReceipt;
    }

    /** @param array<int|string,array<string,mixed>> $before @param array<int|string,array<string,mixed>> $after */
    public static function immutableSnapshotsMatch(array $before, array $after): bool
    {
        return $before !== [] && $before === $after;
    }

    /** @param array<string,mixed> $plan */
    public static function planMatches(array $plan): bool
    {
        if (($plan['format'] ?? null) !== 'fanoos.residency-ai-source-citation-replacement-plan/1'
            || !in_array($plan['year'] ?? null,[1401,1402],true) || ($plan['round'] ?? null) !== 1
            || !is_string($plan['audit_path'] ?? null)
            || !preg_match('~^/srv/fanoos/shared/research/classification/recovery-(?:1401|1402)-[a-z0-9-]+/classification/reports/[^/]+\.json$~D', $plan['audit_path'])
            || !self::sha($plan['audit_sha256'] ?? null)
            || !is_array($plan['entries'] ?? null) || !array_is_list($plan['entries'])
            || $plan['entries'] === [] || count($plan['entries']) > count(self::SPECS)) {
            return false;
        }
        $numbers=array_column($plan['entries'],'number');
        $supportedInPlan=array_values(array_filter(self::supportedQuestions((int)$plan['year']),static fn(int $number):bool=>in_array($number,$numbers,true)));
        if($numbers!==$supportedInPlan||count(array_unique($numbers))!==count($numbers))return false;

        foreach ($plan['entries'] as $entry) {
            if (!is_array($entry) || !is_int($entry['number'] ?? null)) {
                return false;
            }
            $spec = self::spec($entry['number']);
            if ($spec === null || $spec['year'] !== $plan['year']) {
                return false;
            }
            foreach (['year','subject','stem_sha256','choices_sha256','answer_sha256','source_id','old_reference',
                'old_chapter','old_legacy_page','old_printed_page','old_pdf_page','old_pdf_sha256',
                'old_anchor_sha256','old_confidence','old_official_scope_status','old_official_scope_note_sha256','answer_choice',
                'reference','chapter','pdf_page','printed_page','pdf_sha256','evidence','note','confidence'] as $key) {
                if (!array_key_exists($key, $entry) || $entry[$key] !== $spec[$key]) {
                    return false;
                }
            }
            if (!in_array($entry['confidence'] ?? null,[0.90,0.95],true)
                || !is_string($entry['audit_batch'] ?? null)
                || !in_array($entry['audit_batch'], [
                    '1401:operative-dentistry:q165-q168',
                    '1402:periodontics:q139',
                    '1402:operative-dentistry:q175-q190',
                    '1402:dental-materials:q244',
                ], true)
                || ($entry['old_scope_unverified'] ?? null) !== false) {
                return false;
            }
        }
        return true;
    }

    /** @param array<string,mixed> $audit @param array<string,mixed> $plan */
    public static function researchMatches(array $audit, array $plan): bool
    {
        if (($audit['format'] ?? null) !== 'fanoos.classification.provenance-audit/1'
            || ($audit['research_only'] ?? null) !== true
            || !is_array($audit['batches'] ?? null)
            || ($audit['questions'] ?? null) !== count($plan['entries'])
            || ($audit['accepted'] ?? null) !== count($plan['entries']) || ($audit['pending'] ?? null) !== 0) {
            return false;
        }
        $batches = [];
        foreach ($audit['batches'] as $batch) {
            if (is_array($batch) && is_string($batch['batch'] ?? null)) {
                $batches[$batch['batch']] = $batch;
            }
        }
        $expectedBatchNumbers=[];
        foreach($plan['entries'] as $entry){$expectedBatchNumbers[$entry['audit_batch']][]=(string)$entry['number'];}
        if(($audit['batch_count']??null)!==count($expectedBatchNumbers)
            ||count($audit['batches'])!==count($expectedBatchNumbers))return false;
        foreach ($expectedBatchNumbers as $name => $numbers) {
            $batch = $batches[$name] ?? null;
            if (!is_array($batch) || ($batch['questions'] ?? null) !== count($numbers)
                || ($batch['accepted'] ?? null) !== count($numbers) || ($batch['pending'] ?? null) !== 0
                || !is_array($batch['mapped'] ?? null)
                || array_map('strval',array_keys($batch['mapped'])) !== $numbers) {
                return false;
            }
        }
        foreach ($plan['entries'] as $entry) {
            $batch = $batches[$entry['audit_batch']] ?? null;
            $question = (string) $entry['number'];
            $mapped = is_array($batch) ? ($batch['mapped'][$question] ?? null) : null;
            $pdf = is_array($batch)
                ? ($batch['source_pdf_provenance'][$entry['reference']] ?? null) : null;
            if (!is_array($batch) || ($batch['exam_year'] ?? null) !== ($plan['year'] ?? null)
                || ($batch['subject'] ?? null) !== $entry['subject']
                || ($batch['question_content_identical'] ?? null) !== true
                || !is_array($mapped)
                || ($mapped['ref'] ?? null) !== $entry['reference'] . '#ch' . str_pad($entry['chapter'], 2, '0', STR_PAD_LEFT)
                || ($mapped['pdf_page'] ?? null) !== $entry['pdf_page']
                || !is_array($pdf) || ($pdf['sha256'] ?? null) !== $entry['pdf_sha256']) {
                return false;
            }
        }
        return true;
    }

    private static function sha(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/D', $value) === 1;
    }
}
