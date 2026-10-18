<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Bank\SupportingSourceCitationGuard;
use RuntimeException;

final class SupportingSourceCitationGuardTest
{
    public function run(): int
    {
        $a = str_repeat('a',64);
        $b = str_repeat('b',64);
        $existing = ['source_id'=>'01a00000-0000-7000-8000-000000000001',
            'edition'=>'malamed-local-anesthesia@7e','chapter'=>'14','pdf_page'=>272,
            'pdf_sha256'=>$a,'printed_page'=>'255','anchor_sha256'=>$b,
            'evidence'=>'Vazirani-Akinosi closed-mouth mandibular block','is_primary'=>true];
        $supporting = [
            ['edition'=>'malamed-local-anesthesia@7e','chapter'=>'14','pdf_page'=>273,
                'pdf_sha256'=>$a,'evidence'=>'No bony contact; depth of penetration somewhat arbitrary'],
            ['edition'=>'malamed-local-anesthesia@7e','chapter'=>'13','pdf_page'=>225,
                'pdf_sha256'=>$a,'evidence'=>'the fact that bone is not contacted by this nerve block'],
        ];
        $plan = ['format'=>'fanoos.supporting-source-citation-plan/1','exam_type'=>'residency',
            'year'=>1401,'subject'=>'oral-surgery','number'=>150,'stem_sha256'=>$a,
            'choices_sha256'=>$b,'answer_sha256'=>$a,'existing_source'=>$existing,
            'supporting_sources'=>$supporting];
        $audit = ['format'=>'fanoos.research.supporting-source-original-pdf-evidence/1',
            'research_only'=>true,'exam_type'=>'residency','year'=>1401,'round'=>1,
            'subject'=>'oral-surgery','question'=>150,'study_sha256'=>$b,
            'existing_source_verified'=>true,'question_identity_sha256'=>[
                'stem'=>$a,'choices'=>$b,'answer'=>$a],
            'existing_source'=>$existing,'supporting_pages'=>[
                ['edition'=>$supporting[0]['edition'],'chapter'=>'14','pdf_page'=>273,
                    'pdf_sha256'=>$a,'exact_quote'=>$supporting[0]['evidence']],
                ['edition'=>$supporting[1]['edition'],'chapter'=>'13','pdf_page'=>225,
                    'pdf_sha256'=>$a,'exact_quote'=>$supporting[1]['evidence']],
            ]];
        $map = ['editions'=>['malamed-local-anesthesia@7e'=>[
            'source_pdf_sha256'=>$a,'runs'=>[['13',221,255],['14',256,284]],
        ]]];

        $count = 0;
        $this->check(SupportingSourceCitationGuard::validate($plan,$audit,$map),
            'Valid existing source plus two mapped supporting pages was rejected.');
        ++$count;
        foreach ([
            ['supporting_sources'=>[]],
            ['supporting_sources'=>[$supporting[0],$supporting[0],$supporting[1]]],
            ['supporting_sources'=>[$supporting[0],array_replace($supporting[1],['chapter'=>'14','pdf_page'=>273])]],
            ['number'=>149], ['year'=>1400], ['answer_sha256'=>'bad'],
            ['existing_source'=>array_replace($existing,['source_id'=>'bad'])],
            ['supporting_sources'=>[$supporting[0],array_replace($supporting[1],['pdf_page'=>226])]],
            ['supporting_sources'=>[$supporting[0],array_replace($supporting[1],['evidence'=>'different exact quote'])]],
        ] as $change) {
            $this->check(!SupportingSourceCitationGuard::validate(array_replace($plan,$change),$audit,$map),
                'Tampered supporting-source plan was accepted.');
            ++$count;
        }
        $badAudit = array_replace($audit,['existing_source_verified'=>false]);
        $this->check(!SupportingSourceCitationGuard::validate($plan,$badAudit,$map),
            'Unverified original source was accepted.');
        ++$count;
        $badAudit = $audit;
        $badAudit['supporting_pages'][1]['pdf_sha256'] = str_repeat('c',64);
        $this->check(!SupportingSourceCitationGuard::validate($plan,$badAudit,$map),
            'Audit/source PDF hash mismatch was accepted.');
        ++$count;
        $badMap = ['editions'=>['malamed-local-anesthesia@7e'=>[
            'source_pdf_sha256'=>$a,'runs'=>[['13',221,255],['14',256,272]],
        ]]];
        $this->check(!SupportingSourceCitationGuard::validate($plan,$audit,$badMap),
            'Supporting page outside its mapped chapter was accepted.');
        ++$count;
        $this->check(!SupportingSourceCitationGuard::validate($plan,$audit,
            ['editions'=>['malamed-local-anesthesia@7e'=>[
                'source_pdf_sha256'=>str_repeat('c',64),'runs'=>[['13',221,255],['14',256,284]],
            ]]]), 'Stale PDF chapter map was accepted.');
        ++$count;
        $badPlan = $plan;
        $badPlan['existing_source']['is_primary'] = false;
        $badAudit = $audit;
        $badAudit['existing_source']['is_primary'] = false;
        $this->check(!SupportingSourceCitationGuard::validate($badPlan,$badAudit,$map),
            'Nonprimary original source was accepted.');
        ++$count;
        $root = dirname(__DIR__,2);
        $operator = file_get_contents($root.'/scripts/references/insert_verified_supporting_sources.php');
        $this->check(is_string($operator) && str_contains($operator,'INSERT INTO bank_question_sources'),
            'Supporting-source operator does not insert citation rows.');
        ++$count;
        $this->check(is_string($operator)
            && preg_match('/\bUPDATE\s+bank_(?:questions|question_choices|official_answers|question_sources)\b/i',$operator) !== 1,
            'Supporting-source operator can mutate original question/source rows.');
        ++$count;
        $this->check(SupportingSourceCitationGuard::scopeAllows('[{"number":"14"}]','14'),
            'Official full chapter was rejected.');
        ++$count;
        $this->check(!SupportingSourceCitationGuard::scopeAllows('[{"number":"14","partial":true}]','14'),
            'Partial chapter without page-scope evidence was accepted.');
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
