<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Bank\VoidedSourceCitationGuard;
use RuntimeException;

final class VoidedSourceCitationGuardTest
{
    public function run(): int
    {
        $sha = str_repeat('a', 64);
        $item = [
            'number'=>133,'source_id'=>'01a00000-0000-0000-0000-000000000133',
            'edition'=>'hupp-oral-surgery@7e','chapter'=>'9','printed_page'=>'154',
            'pdf_page'=>168,'pdf_sha256'=>$sha,
            'expected_stem_sha256'=>str_repeat('b', 64),
            'expected_choices_sha256'=>str_repeat('c', 64),
            'expected_answer_sha256'=>str_repeat('d', 64),
            'evidence'=>'the mesiobuccal root is delivered with the cryer elevator',
        ];
        $plan = ['year'=>1405,'subject'=>'oral-surgery'];
        $audit = [
            'format'=>'fanoos.voided-original-pdf-citation-audit/1',
            'research_only'=>true,'year'=>1405,'subject'=>'oral-surgery',
            'records'=>[array_replace($item, [
                'question_status'=>'draft',
                'official_answer_status'=>'voided',
                'scientifically_accepted'=>true,
            ])],
        ];
        $checks = 0;
        $this->check(VoidedSourceCitationGuard::auditMatches($audit,$plan,$item), 'Accepted independent voided citation rejected.');
        ++$checks;
        foreach ([
            ['chapter'=>'10'],['pdf_page'=>169],['number'=>134],
            ['pdf_sha256'=>str_repeat('f',64)],
            ['expected_answer_sha256'=>str_repeat('f',64)],
            ['expected_stem_sha256'=>str_repeat('f',64)],
            ['expected_choices_sha256'=>str_repeat('f',64)],
            ['evidence'=>'a different unsupported quote'],
            ['source_id'=>'01a00000-0000-0000-0000-000000000200'],
        ] as $tamper) {
            $this->check(!VoidedSourceCitationGuard::auditMatches($audit,$plan,array_replace($item,$tamper)),
                'Changed original/citation evidence accepted.');
            ++$checks;
        }
        $this->check(!VoidedSourceCitationGuard::auditMatches($audit,array_replace($plan,['year'=>1404]),$item),
            'Wrong official year accepted.');
        ++$checks;
        $this->check(!VoidedSourceCitationGuard::auditMatches(
            array_replace($audit,['records'=>[array_replace($audit['records'][0],['official_answer_status'=>'final'])]]),
            $plan,$item
        ),'A scored key was accepted as voided.');
        ++$checks;
        $this->check(!VoidedSourceCitationGuard::auditMatches(
            array_replace($audit,['records'=>array_merge($audit['records'],$audit['records'])]),
            $plan,$item
        ),'Ambiguous double citation passed.');
        ++$checks;
        $this->check(VoidedSourceCitationGuard::allowedSourceFields() === ['printed_page','pdf_page','pdf_sha256'],
            'Question, option, official key or assessment metadata in permitted write set.');
        ++$checks;
        return $checks;
    }

    private function check(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
