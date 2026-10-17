<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Bank\VerifiedHumanOutsideScopeChapterPageGuard;
use RuntimeException;

final class VerifiedHumanOutsideScopeChapterPageRepairGuardTest
{
    public function run(): int
    {
        $sha=str_repeat('a',64);$plan=['year'=>1404,'subject'=>'oral-medicine'];
        $item=['number'=>74,'source_id'=>'01a11bb7-1e73-7c9e-815e-fbdc73fef9db',
            'edition'=>'little-falace-dental-management@10e','expected_chapter'=>'14','chapter'=>'29',
            'printed_page'=>'533','pdf_page'=>596,'pdf_sha256'=>$sha,'allow_long_header'=>true,
            'note'=>'محل طراحی خارج از محدوده آزمون بوده است.','evidence'=>['glucagon 1 mg'],
            'expected_stem_sha256'=>str_repeat('b',64),'expected_choices_sha256'=>str_repeat('c',64),
            'expected_answer_sha256'=>str_repeat('d',64),'expected_anchor_sha256'=>str_repeat('e',64),
            'expected_question_status'=>'published','expected_answer_status'=>'final',
            'expected_official_scope_status'=>'in_scope','expected_official_scope_note'=>null];
        $audit=['format'=>'fanoos.human-outside-scope-chapter-page-audit/1','research_only'=>true,
            'year'=>1404,'subject'=>'oral-medicine','records'=>[array_merge($item,['scientifically_accepted'=>true])]];
        $this->check(VerifiedHumanOutsideScopeChapterPageGuard::auditMatches($audit,$plan,$item),'Valid audit rejected.');$checks=1;
        foreach([['chapter'=>'30'],['printed_page'=>'534'],['pdf_page'=>597],['allow_long_header'=>false],
            ['pdf_sha256'=>str_repeat('f',64)],['expected_answer_status'=>'voided']] as $change){
            $this->check(!VerifiedHumanOutsideScopeChapterPageGuard::auditMatches($audit,$plan,array_replace($item,$change)),'Modified outside-scope decision accepted.');++$checks;
        }
        $bad=$audit;$bad['records'][0]['scientifically_accepted']=false;
        $this->check(!VerifiedHumanOutsideScopeChapterPageGuard::auditMatches($bad,$plan,$item),'Unaccepted audit passed.');++$checks;
        $scope='[{"number":"14"},{"number":"24","partial":"selected pages"}]';
        $this->check(VerifiedHumanOutsideScopeChapterPageGuard::chapterIsOutOfScope($scope,'29'),'Absent chapter rejected.');++$checks;
        $this->check(!VerifiedHumanOutsideScopeChapterPageGuard::chapterIsOutOfScope($scope,'14'),'In-scope chapter accepted as outside.');++$checks;
        $this->check(!VerifiedHumanOutsideScopeChapterPageGuard::chapterIsOutOfScope($scope,'24'),'Partial chapter accepted as outside.');++$checks;
        return $checks;
    }

    private function check(bool $okay,string $message):void
    {
        if(!$okay)throw new RuntimeException($message);
    }
}
