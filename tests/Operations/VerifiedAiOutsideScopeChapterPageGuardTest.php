<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Bank\VerifiedAiOutsideScopeChapterPageGuard;
use RuntimeException;

final class VerifiedAiOutsideScopeChapterPageGuardTest
{
    public function run(): int
    {
        $sha = str_repeat('a', 64);
        $plan = ['year'=>1401, 'subject'=>'dental-materials'];
        $item = [
            'number'=>212,
            'source_id'=>'01a11be7-9bf6-70ea-9e46-1ab4c388346b',
            'edition'=>'van-noort-materials@4e',
            'expected_chapter'=>'1.6',
            'chapter'=>'2.7',
            'printed_page'=>'144',
            'pdf_page'=>159,
            'pdf_sha256'=>$sha,
            'evidence'=>['permanent deformation'],
            'note'=>'Van Noort 4e chapter 2.7 is خارج از محدوده آزمون.',
            'expected_legacy_page'=>'35',
            'expected_stored_printed_page'=>'35',
            'expected_pdf_page'=>null,
            'expected_pdf_sha256'=>null,
            'expected_scope_status'=>'in_scope',
            'expected_scope_note'=>null,
            'expected_old_confidence_page'=>'0.550',
            'new_confidence_page'=>'0.900',
            'expected_stem_sha256'=>str_repeat('b', 64),
            'expected_choices_sha256'=>str_repeat('c', 64),
            'expected_answer_sha256'=>str_repeat('d', 64),
            'expected_anchor_sha256'=>str_repeat('e', 64),
            'expected_question_status'=>'published',
            'expected_answer_status'=>'final',
        ];
        $plan['entries'] = [$item];
        $record = array_merge($item, [
            'scientifically_accepted'=>true,
            'confidence_after_reaudit'=>'0.900',
            'pdf_chapter_map_valid'=>true,
            'printed_page_sequence_valid'=>true,
            'exact_page_evidence_valid'=>true,
            'answer_alignment'=>'The exact page supports the hardness definition.',
        ]);
        $audit = [
            'format'=>'fanoos.ai-outside-scope-chapter-page-audit/1',
            'research_only'=>true,
            'year'=>1401,
            'subject'=>'dental-materials',
            'records'=>[$record],
        ];

        $this->check(
            VerifiedAiOutsideScopeChapterPageGuard::auditMatches($audit, $plan, $item),
            'Valid AI outside-scope audit rejected.',
        );
        $checks = 1;
        foreach ([
            ['chapter'=>'2.8'],
            ['pdf_page'=>160],
            ['pdf_sha256'=>str_repeat('f', 64)],
            ['new_confidence_page'=>'0.800'],
            ['evidence'=>['different phrase']],
            ['expected_answer_sha256'=>str_repeat('f', 64)],
        ] as $change) {
            $this->check(
                !VerifiedAiOutsideScopeChapterPageGuard::auditMatches(
                    $audit,
                    $plan,
                    array_replace($item, $change),
                ),
                'Modified source audit was accepted.',
            );
            ++$checks;
        }

        $badAudit = $audit;
        $badAudit['records'][0]['scientifically_accepted'] = false;
        $this->check(
            !VerifiedAiOutsideScopeChapterPageGuard::auditMatches($badAudit, $plan, $item),
            'Unaccepted source audit passed.',
        );
        ++$checks;

        $scope = '[{"number":"1.6"},{"number":"3.4","partial":"selected pages"}]';
        $this->check(
            VerifiedAiOutsideScopeChapterPageGuard::chapterIsOutOfScope($scope, '2.7'),
            'Absent chapter was not recognized as outside scope.',
        );
        ++$checks;
        $this->check(
            !VerifiedAiOutsideScopeChapterPageGuard::chapterIsOutOfScope($scope, '1.6'),
            'In-scope chapter was accepted as outside scope.',
        );
        ++$checks;
        $this->check(
            !VerifiedAiOutsideScopeChapterPageGuard::chapterIsOutOfScope($scope, '3.4'),
            'Partially scoped chapter was accepted as outside scope.',
        );
        ++$checks;
        $this->check(
            !VerifiedAiOutsideScopeChapterPageGuard::chapterIsOutOfScope('{broken', '2.7'),
            'Malformed scope JSON was accepted.',
        );
        ++$checks;

        return $checks;
    }

    private function check(bool $okay, string $message): void
    {
        if (!$okay) {
            throw new RuntimeException($message);
        }
    }
}
