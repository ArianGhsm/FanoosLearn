<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Bank\VerifiedSourcePrintedLabelGuard;
use RuntimeException;

final class VerifiedSourcePrintedLabelGuardTest
{
    public function run(): int
    {
        $sha = str_repeat('a', 64);
        $item = [
            'number'=>3,
            'source_id'=>'01a11be7-9707-7d09-b28b-69dfa00ed68d',
            'edition'=>'proffit-orthodontics@6e',
            'chapter'=>'8',
            'pdf_page'=>281,
            'printed_page'=>'271',
            'pdf_sha256'=>$sha,
            'expected_page'=>'pdf 281',
            'expected_pdf_page'=>281,
            'expected_pdf_sha256'=>$sha,
            'expected_confidence_page'=>'0.900',
            'expected_anchor_sha256'=>str_repeat('b', 64),
            'expected_stem_sha256'=>str_repeat('c', 64),
            'expected_choices_sha256'=>str_repeat('d', 64),
            'expected_answer_sha256'=>str_repeat('e', 64),
            'expected_question_status'=>'published',
            'expected_answer_status'=>'final',
            'expected_origin'=>'ai',
            'expected_reviewed_at'=>null,
            'expected_reviewed_by_user_id'=>null,
        ];
        $plan = ['year'=>1401, 'subject'=>'orthodontics', 'entries'=>[$item]];
        $audit = [
            'format'=>'fanoos.ai-source-printed-label-audit/1',
            'research_only'=>true,
            'year'=>1401,
            'subject'=>'orthodontics',
            'records'=>[array_merge($item, [
                'accepted'=>true,
                'chapter_map_page_valid'=>true,
                'pdf_sha_valid'=>true,
                'printed_label_neighbor_check'=>true,
                'scope_page_disposition'=>'in_scope',
            ])],
        ];

        $this->check(VerifiedSourcePrintedLabelGuard::auditMatches($audit, $plan, $item), 'Valid printed-label audit rejected.');
        $checks = 1;
        foreach ([
            ['printed_page'=>'272'],
            ['pdf_page'=>282],
            ['pdf_sha256'=>str_repeat('f', 64)],
            ['expected_confidence_page'=>'0.700'],
            ['expected_answer_sha256'=>str_repeat('f', 64)],
        ] as $change) {
            $this->check(
                !VerifiedSourcePrintedLabelGuard::auditMatches($audit, $plan, array_replace($item, $change)),
                'Modified source printed-label audit was accepted.',
            );
            ++$checks;
        }
        $outside = $audit;
        $outside['records'][0]['scope_page_disposition'] = 'outside_scope';
        $this->check(!VerifiedSourcePrintedLabelGuard::auditMatches($outside, $plan, $item), 'Out-of-scope page passed the in-scope label writer.');
        ++$checks;

        $humanItem = array_replace($item, [
            'expected_origin'=>'human',
            'expected_confidence_page'=>null,
            'expected_reviewed_at'=>'2026-10-10 12:00:00.000000',
            'expected_reviewed_by_user_id'=>'01a11be7-0000-7000-8000-000000000001',
        ]);
        $humanAudit = [
            'format'=>'fanoos.human-source-printed-label-audit/1',
            'research_only'=>true,
            'year'=>1401,
            'subject'=>'orthodontics',
            'records'=>[array_merge($humanItem, [
                'accepted'=>true,
                'chapter_map_page_valid'=>true,
                'pdf_sha_valid'=>true,
                'printed_label_neighbor_check'=>true,
                'scope_page_disposition'=>'in_scope',
            ])],
        ];
        $this->check(VerifiedSourcePrintedLabelGuard::auditMatches($humanAudit, $plan, $humanItem),
            'Valid human-source printed-label audit rejected.');
        ++$checks;
        $badHuman = $humanAudit;
        $badHuman['records'][0]['expected_reviewed_by_user_id'] = '01a11be7-0000-7000-8000-000000000002';
        $this->check(!VerifiedSourcePrintedLabelGuard::auditMatches($badHuman, $plan, $humanItem),
            'Human reviewer attribution changed in the audit.');
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
