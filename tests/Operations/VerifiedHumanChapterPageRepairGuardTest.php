<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Bank\VerifiedHumanChapterPageRepairGuard;
use RuntimeException;

final class VerifiedHumanChapterPageRepairGuardTest
{
    public function run(): int
    {
        $sha = str_repeat('a', 64);
        $plan = ['year'=>1404, 'subject'=>'pediatric-dentistry'];
        $item = [
            'number'=>178, 'source_id'=>'01a1037b-0322-7c8f-8904-7c9334fb9cbe',
            'edition'=>'mcdonald-avery-pediatric@11e', 'expected_chapter'=>'4',
            'chapter'=>'20', 'pdf_page'=>410, 'pdf_sha256'=>$sha,
            'evidence'=>['Root Completed'], 'expected_stem_sha256'=>str_repeat('b', 64),
            'expected_choices_sha256'=>str_repeat('c', 64), 'expected_answer_sha256'=>str_repeat('d', 64),
            'expected_anchor_sha256'=>str_repeat('e', 64), 'expected_question_status'=>'published',
            'expected_answer_status'=>'final',
        ];
        $audit = [
            'format'=>'fanoos.human-source-chapter-page-audit/1', 'research_only'=>true,
            'year'=>1404, 'subject'=>'pediatric-dentistry', 'records'=>[
                array_merge($item, ['scientifically_accepted'=>true]),
            ],
        ];
        $this->check(VerifiedHumanChapterPageRepairGuard::auditMatches($audit, $plan, $item), 'Valid human audit rejected.');
        $checks = 1;
        foreach ([
            ['chapter'=>'21'], ['expected_chapter'=>'3'], ['pdf_page'=>411],
            ['source_id'=>'01a1037b-0322-7c8f-8904-7c9334fb9cbd'],
            ['pdf_sha256'=>str_repeat('f', 64)], ['expected_answer_status'=>'voided'],
        ] as $change) {
            $this->check(!VerifiedHumanChapterPageRepairGuard::auditMatches(
                $audit, $plan, array_replace($item, $change)
            ), 'Audit accepted a changed protected fact.');
            ++$checks;
        }
        $unaccepted = $audit;
        $unaccepted['records'][0]['scientifically_accepted'] = false;
        $this->check(!VerifiedHumanChapterPageRepairGuard::auditMatches($unaccepted, $plan, $item), 'Unaccepted evidence passed.');
        ++$checks;
        $this->check(!VerifiedHumanChapterPageRepairGuard::scopeAllows('[{"number":"9","partial":"through 217"}]', '9'), 'Partial chapter passed.');
        ++$checks;
        $this->check(VerifiedHumanChapterPageRepairGuard::scopeAllows('[{"number":"20"}]', '20'), 'Complete chapter rejected.');
        ++$checks;

        $map = ['editions'=>['book@1e'=>['source_pdf_sha256'=>$sha,'runs'=>[
            ['4',100,149],['20',400,420],['27',600,620],
        ]]]];
        $this->check(VerifiedHumanChapterPageRepairGuard::pageInMap($map, 'book@1e', '20', 410, $sha), 'Mapped page rejected.');
        ++$checks;
        $this->check(!VerifiedHumanChapterPageRepairGuard::pageInMap($map, 'book@1e', '4', 410, $sha), 'Wrong chapter accepted.');
        ++$checks;
        $this->check(!VerifiedHumanChapterPageRepairGuard::pageInMap($map, 'book@1e', '20', 410, str_repeat('f', 64)), 'Wrong PDF hash accepted.');
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
