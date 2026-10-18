<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Bank\VoidedSourcePageBundleGuard;
use RuntimeException;

final class VoidedSourcePageBundleGuardTest
{
    public function run(): int
    {
        $sha = str_repeat('a', 64);
        $item = [
            'number'=>2,
            'source_id'=>'01a00000-0000-0000-0000-000000000002',
            'edition'=>'proffit-orthodontics@6e',
            'chapter'=>'2',
            'primary_pdf_page'=>52,
            'primary_printed_page'=>'42',
            'pdf_sha256'=>$sha,
            'pages'=>[
                ['pdf_page'=>50,'printed_page'=>'40','evidence'=>['transplantation evidence for nasal cartilage growth']],
                ['pdf_page'=>51,'printed_page'=>'41','evidence'=>['experimental findings about relative cartilage growth']],
                ['pdf_page'=>52,'printed_page'=>'42','evidence'=>['summary evidence of independent cartilage growth']],
            ],
            'expected_source_page'=>'pdf 52',
            'expected_source_printed_page'=>null,
            'expected_source_pdf_page'=>52,
            'expected_source_pdf_sha256'=>$sha,
            'expected_source_anchor_sha256'=>str_repeat('b', 64),
            'expected_stem_sha256'=>str_repeat('c', 64),
            'expected_choices_sha256'=>str_repeat('d', 64),
            'expected_answer_sha256'=>str_repeat('e', 64),
            'expected_question_status'=>'draft',
            'expected_answer_status'=>'voided',
            'expected_source_origin'=>'ai',
            'expected_source_reviewed_at'=>null,
            'expected_source_reviewed_by_user_id'=>null,
            'expected_source_scope_status'=>'in_scope',
            'expected_source_scope_note'=>null,
            'expected_scope_unverified'=>false,
            'expected_confidence_source'=>'0.600',
            'expected_confidence_node'=>'0.600',
            'expected_confidence_page'=>'0.600',
            'accepted_confidence'=>'0.950',
        ];
        $plan = [
            'format'=>'fanoos.verified-voided-source-page-bundle-repair/1',
            'exam_type'=>'residency','year'=>1405,'subject'=>'orthodontics','entries'=>[$item],
        ];
        $record = $item + [
            'scientifically_accepted'=>true,
            'question_status'=>'draft',
            'official_answer_status'=>'voided',
            'official_answer_choice_position'=>null,
            'page_map_valid'=>true,
            'pdf_sha_valid'=>true,
            'printed_page_sequences_valid'=>true,
            'all_page_evidence_valid'=>true,
            'all_pages_in_scope'=>true,
        ];
        $audit = [
            'format'=>'fanoos.voided-source-page-bundle-audit/1',
            'research_only'=>true,'year'=>1405,'subject'=>'orthodontics','records'=>[$record],
        ];

        $checks = 0;
        $this->check(VoidedSourcePageBundleGuard::planMatches($plan), 'Valid three-page voided source plan rejected.');
        ++$checks;
        $this->check(VoidedSourcePageBundleGuard::auditMatches($audit, $plan, $item), 'Accepted voided bundle audit rejected.');
        ++$checks;

        foreach ([
            ['expected_answer_status'=>'final'],
            ['expected_question_status'=>'published'],
            ['accepted_confidence'=>'0.600'],
            ['expected_source_origin'=>'human'],
            ['expected_source_scope_status'=>'outside_scope_exam_question'],
        ] as $tamper) {
            $badItem = array_replace($item, $tamper);
            $this->check(!VoidedSourcePageBundleGuard::planMatches(array_replace($plan, ['entries'=>[$badItem]])),
                'Invalid voided source identity or state accepted.');
            ++$checks;
        }

        $wrongNumber = array_replace($item, ['number'=>3]);
        $this->check(!VoidedSourcePageBundleGuard::auditMatches($audit,$plan,$wrongNumber),
            'Audit for a different question number accepted.');
        ++$checks;

        $duplicatePage = $item;
        $duplicatePage['pages'][1]['pdf_page'] = 50;
        $this->check(!VoidedSourcePageBundleGuard::planMatches(array_replace($plan, ['entries'=>[$duplicatePage]])),
            'Duplicate PDF page in a bundle accepted.');
        ++$checks;

        $missingPrimary = $item;
        $missingPrimary['pages'][2]['pdf_page'] = 53;
        $this->check(!VoidedSourcePageBundleGuard::planMatches(array_replace($plan, ['entries'=>[$missingPrimary]])),
            'Bundle that omits its existing primary page accepted.');
        ++$checks;

        foreach ([
            ['question_status'=>'published'],
            ['official_answer_status'=>'final'],
            ['official_answer_choice_position'=>1],
            ['all_page_evidence_valid'=>false],
            ['printed_page_sequences_valid'=>false],
            ['expected_answer_sha256'=>str_repeat('f', 64)],
            ['pages'=>array_reverse($item['pages'])],
        ] as $tamper) {
            $badRecord = array_replace($record, $tamper);
            $this->check(!VoidedSourcePageBundleGuard::auditMatches(
                array_replace($audit, ['records'=>[$badRecord]]), $plan, $item,
            ), 'Changed voided status, key, page evidence, or source identity accepted.');
            ++$checks;
        }

        $expectedFields = [
            'bank_question_sources.page','bank_question_sources.printed_page',
            'bank_question_sources.pdf_page','bank_question_sources.pdf_sha256',
            'bank_question_sources.anchor_text','bank_question_sources.confidence_source',
            'bank_question_sources.confidence_node','bank_question_sources.confidence_page',
        ];
        $this->check(VoidedSourcePageBundleGuard::allowedSourceFields() === $expectedFields,
            'The write set contains fields outside source-citation metadata.');
        ++$checks;

        $root = dirname(__DIR__, 2);
        $operator = file_get_contents($root . '/scripts/references/repair_verified_voided_source_page_bundle.php');
        $this->check(is_string($operator)
            && str_contains($operator, 'UPDATE bank_question_sources')
            && str_contains($operator, 'INSERT INTO bank_question_sources'),
            'The guarded operator does not contain both source-only write paths.');
        ++$checks;
        foreach ([
            'UPDATE bank_questions', 'UPDATE bank_question_choices', 'UPDATE bank_official_answers',
            'INSERT INTO bank_official_answers', 'UPDATE exam_assessment_versions', 'UPDATE exam_attempts',
        ] as $forbiddenWrite) {
            $this->check(!str_contains((string)$operator, $forbiddenWrite),
                'The operator can write outside the citation tables.');
            ++$checks;
        }

        return $checks;
    }

    private function check(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
