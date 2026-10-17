<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Bank\VerifiedHumanSourcePageBundleGuard;
use RuntimeException;

final class VerifiedHumanSourcePageBundleGuardTest
{
    public function run(): int
    {
        $sha=str_repeat('a',64);$plan=['year'=>1404,'subject'=>'oral-radiology'];
        $pages=[['pdf_page'=>430,'evidence'=>['left ramus casts a real image']],
            ['pdf_page'=>432,'evidence'=>['ramus lies between the x-ray source and the center of rotation']]];
        $item=['number'=>198,'source_id'=>'01a11bb7-2211-709c-ae94-67e0e667fdfc',
            'edition'=>'white-pharoah-radiology@8e','expected_chapter'=>'9','chapter'=>'9',
            'pdf_sha256'=>$sha,'pages'=>$pages,'expected_stem_sha256'=>str_repeat('b',64),
            'expected_choices_sha256'=>str_repeat('c',64),'expected_answer_sha256'=>str_repeat('d',64),
            'expected_anchor_sha256'=>str_repeat('e',64),'expected_question_status'=>'published',
            'expected_answer_status'=>'final'];
        $audit=['format'=>'fanoos.human-source-page-bundle-audit/1','research_only'=>true,
            'year'=>1404,'subject'=>'oral-radiology','records'=>[array_merge($item,['scientifically_accepted'=>true])]];
        $this->check(VerifiedHumanSourcePageBundleGuard::auditMatches($audit,$plan,$item),'Valid bundle audit rejected.');$checks=1;
        foreach([['chapter'=>'10'],['pages'=>array_reverse($pages)],['expected_chapter'=>'8'],
            ['source_id'=>'01a11bb7-2211-709c-ae94-67e0e667fdfd'],['pdf_sha256'=>str_repeat('f',64)],
            ['expected_choices_sha256'=>str_repeat('f',64)]] as $change){
            $this->check(!VerifiedHumanSourcePageBundleGuard::auditMatches($audit,$plan,array_replace($item,$change)),'Changed bundle decision accepted.');++$checks;
        }
        $bad=$audit;$bad['records'][0]['scientifically_accepted']=false;
        $this->check(!VerifiedHumanSourcePageBundleGuard::auditMatches($bad,$plan,$item),'Unaccepted bundle audit passed.');++$checks;
        return $checks;
    }

    private function check(bool $okay,string $message):void
    {
        if(!$okay)throw new RuntimeException($message);
    }
}
