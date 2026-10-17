<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Bank\TwoSourceCitationGuard;
use RuntimeException;

final class TwoSourceCitationGuardTest
{
    public function run(): int
    {
        $sha = str_repeat('a', 64);
        $sources = [
            ['edition'=>'white-pharoah-radiology@8e','chapter'=>'23','pdf_page'=>1161,
                'pdf_sha256'=>$sha,'evidence'=>'GOC recurrence and periodic examinations'],
            ['edition'=>'white-pharoah-radiology@8e','chapter'=>'24','pdf_page'=>1294,
                'pdf_sha256'=>$sha,'evidence'=>'ABC recurrence and careful follow-up'],
        ];
        $plan = ['format'=>'fanoos.two-source-citation-plan/1','exam_type'=>'residency',
            'year'=>1405,'subject'=>'oral-radiology','number'=>207,
            'stem_sha256'=>$sha,'choices_sha256'=>$sha,'answer_sha256'=>$sha,
            'sources'=>$sources];
        $audit = ['format'=>'fanoos.research.multi-chapter-original-pdf-evidence/1',
            'research_only'=>true,'exam_type'=>'residency','year'=>1405,'round'=>1,
            'question'=>207,'source_free_verified'=>true,
            'official_reference'=>'white-pharoah-radiology@8e',
            'approved_pdf_sha256'=>$sha,
            'supporting_pages'=>[
                ['chapter'=>'23','pdf_page'=>1161,'exact_quote'=>$sources[0]['evidence']],
                ['chapter'=>'24','pdf_page'=>1294,'exact_quote'=>$sources[1]['evidence']],
            ]];
        $map = ['editions'=>['white-pharoah-radiology@8e'=>[
            'source_pdf_sha256'=>$sha,'runs'=>[['23',1124,1190],['24',1191,1315]],
        ]]];
        $count = 0;
        $this->check(TwoSourceCitationGuard::validate($plan,$audit,$map), 'Valid two-page evidence rejected.');
        ++$count;
        foreach ([
            ['sources'=>[$sources[0]]],
            ['sources'=>[$sources[0],$sources[0]]],
            ['sources'=>[$sources[1],$sources[0]]],
            ['sources'=>[$sources[0],array_replace($sources[1],['pdf_page'=>1295])]],
            ['sources'=>[$sources[0],array_replace($sources[1],['pdf_sha256'=>str_repeat('b',64)])]],
            ['sources'=>[$sources[0],array_replace($sources[1],['evidence'=>'unsupported quote'])]],
            ['year'=>1404], ['answer_sha256'=>'bad'],
        ] as $change) {
            $this->check(!TwoSourceCitationGuard::validate(array_replace($plan,$change),$audit,$map),
                'Tampered two-source plan accepted.');
            ++$count;
        }
        $this->check(!TwoSourceCitationGuard::validate($plan,array_replace($audit,['source_free_verified'=>false]),$map),
            'Already-sourced question accepted.');
        ++$count;
        $this->check(!TwoSourceCitationGuard::validate($plan,$audit,
            ['editions'=>['white-pharoah-radiology@8e'=>['source_pdf_sha256'=>$sha,
                'runs'=>[['23',1124,1190],['24',1300,1315]]]]]),
            'Page outside mapped chapter accepted.');
        ++$count;
        $this->check(TwoSourceCitationGuard::scopeAllows('[{"number":"23"},{"number":"24"}]','24'),
            'Official chapter rejected.');
        ++$count;
        $this->check(!TwoSourceCitationGuard::scopeAllows('[{"number":"24","partial":true}]','24'),
            'Partial chapter was accepted without a bounded page.');
        ++$count;
        $this->check(!TwoSourceCitationGuard::scopeAllows(null,'24'), 'Unspecified scope accepted.');
        ++$count;
        return $count;
    }

    private function check(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
