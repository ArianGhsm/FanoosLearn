<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Bank\ResidencyAiSourceCitationReplacementGuard as Guard;

final class ResidencyAiSourceCitationReplacementGuardTest
{
    public function run(): int
    {
        $checks=0;
        $this->check(Guard::supportedQuestions(1398)===[48,176,180,182],'Pinned 1398 low-confidence rows are incorrect.');++$checks;
        $this->check(Guard::supportedQuestions(1402)===[139,175,190,244],'Pinned 1402 rows are incorrect.');++$checks;
        $this->check(Guard::supportedQuestions(1401)===[123,128,165,168],'Pinned 1401 rows are incorrect.');++$checks;
        $this->check(Guard::spec(174)===null,'An unlisted question entered the citation replacement route.');++$checks;
        $immutable=[139=>['stem'=>'0','choices'=>'1','answer'=>'2'],175=>['stem'=>'a','choices'=>'b','answer'=>'c'],190=>['stem'=>'d','choices'=>'e','answer'=>'f'],244=>['stem'=>'g','choices'=>'h','answer'=>'i']];
        $this->check(Guard::immutableSnapshotsMatch($immutable,$immutable),'Unchanged item fingerprints were rejected.');++$checks;
        $changed=$immutable;$changed[190]['answer']='changed';
        $this->check(!Guard::immutableSnapshotsMatch($immutable,$changed),'Changed answer passed immutable comparison.');++$checks;
        $missing=$immutable;unset($missing[244]);
        $this->check(!Guard::immutableSnapshotsMatch($immutable,$missing),'Missing item passed immutable comparison.');++$checks;
        foreach([
            [false,false,false,false,true],
            [false,false,false,true,true],
            [false,true,false,true,false],
            [false,false,true,true,false],
            [true,true,true,true,true],
            [true,true,true,false,false],
            [true,false,true,true,false],
        ] as [$apply,$backup,$receipt,$preview,$expected]){
            $this->check(Guard::modeArgumentsValid($apply,$backup,$receipt,$preview)===$expected,'Preview/apply arguments bypassed.');++$checks;
        }

        $entries=[];
        foreach(Guard::supportedQuestions(1402) as $number){
            $spec=Guard::spec($number);
            $entries[]=$spec+[
                'number'=>$number,
                'audit_batch'=>match($number){139=>'1402:periodontics:q139',244=>'1402:dental-materials:q244',default=>'1402:operative-dentistry:q175-q190'},
                'old_scope_unverified'=>false,
            ];
        }
        $plan=[
            'format'=>'fanoos.residency-ai-source-citation-replacement-plan/1',
            'year'=>1402,'round'=>1,
            'audit_path'=>'/srv/fanoos/shared/research/classification/recovery-1402-source-replacements-fixture/classification/reports/audit.json',
            'audit_sha256'=>str_repeat('a',64),
            'entries'=>$entries,
        ];
        $this->check(Guard::planMatches($plan),'Exact four-question replacement plan was rejected.');++$checks;
        foreach([
            array_replace($entries[0],['pdf_page'=>786]),
            array_replace($entries[1],['reference'=>'summit-operative@4e']),
            array_replace($entries[3],['pdf_sha256'=>str_repeat('b',64)]),
            array_replace($entries[3],['source_id'=>'00000000-0000-7000-8000-000000000000']),
            array_replace($entries[0],['answer_choice'=>1]),
        ] as $badEntry){
            $bad=$plan;$bad['entries'][array_search($badEntry['number']??-1,[139,175,190,244],true)??0]=$badEntry;
            $this->check(!Guard::planMatches($bad),'Mutated exact citation plan passed its pin.');++$checks;
        }
        $bad=$plan;$bad['entries'][1]['number']=172;
        $this->check(!Guard::planMatches($bad),'An unpinned source-correction target passed.');++$checks;

        $audit=[
            'format'=>'fanoos.classification.provenance-audit/1','research_only'=>true,
            'batch_count'=>3,'questions'=>4,'accepted'=>4,'pending'=>0,
            'batches'=>[
                ['batch'=>'1402:periodontics:q139','exam_year'=>1402,'subject'=>'periodontics',
                    'questions'=>1,'accepted'=>1,'pending'=>0,'question_content_identical'=>true,
                    'mapped'=>['139'=>['ref'=>'carranza-periodontology@13e#ch28','pdf_page'=>785,
                        'confidence_source'=>0.9,'confidence_node'=>0.9,'confidence_page'=>0.9]],
                    'source_pdf_provenance'=>['carranza-periodontology@13e'=>['sha256'=>Guard::spec(139)['pdf_sha256']]]],
                ['batch'=>'1402:operative-dentistry:q175-q190','exam_year'=>1402,'subject'=>'operative-dentistry',
                    'questions'=>2,'accepted'=>2,'pending'=>0,'question_content_identical'=>true,
                    'mapped'=>[
                        '175'=>['ref'=>'craig-restorative-materials@14e#ch13','pdf_page'=>288,
                            'confidence_source'=>0.95,'confidence_node'=>0.95,'confidence_page'=>0.95],
                        '190'=>['ref'=>'craig-restorative-materials@14e#ch13','pdf_page'=>288,
                            'confidence_source'=>0.95,'confidence_node'=>0.95,'confidence_page'=>0.95],
                    ],
                    'source_pdf_provenance'=>['craig-restorative-materials@14e'=>['sha256'=>Guard::spec(175)['pdf_sha256']]]],
                ['batch'=>'1402:dental-materials:q244','exam_year'=>1402,'subject'=>'dental-materials',
                    'questions'=>1,'accepted'=>1,'pending'=>0,'question_content_identical'=>true,
                    'mapped'=>['244'=>['ref'=>'powers-wataha-materials@11e#ch04','pdf_page'=>67,
                        'confidence_source'=>0.95,'confidence_node'=>0.95,'confidence_page'=>0.95]],
                    'source_pdf_provenance'=>['powers-wataha-materials@11e'=>['sha256'=>Guard::spec(244)['pdf_sha256']]]],
            ],
        ];
        $this->check(Guard::researchMatches($audit,$plan),'Matching independent original-PDF audit was rejected.');++$checks;
        $badAudit=$audit;$badAudit['batches'][1]['mapped']['190']['pdf_page']=450;
        $this->check(!Guard::researchMatches($badAudit,$plan),'Changed page in independent audit passed.');++$checks;
        $badAudit=$audit;$badAudit['batches'][2]['question_content_identical']=false;
        $this->check(!Guard::researchMatches($badAudit,$plan),'Changed question content passed research audit.');++$checks;

        $singlePlan=$plan;$singlePlan['entries']=[$entries[0]];
        $singleAudit=['format'=>'fanoos.classification.provenance-audit/1','research_only'=>true,
            'batch_count'=>1,'questions'=>1,'accepted'=>1,'pending'=>0,
            'batches'=>[['batch'=>'1402:periodontics:q139','exam_year'=>1402,'subject'=>'periodontics',
                'questions'=>1,'accepted'=>1,'pending'=>0,'question_content_identical'=>true,
                'mapped'=>['139'=>['ref'=>'carranza-periodontology@13e#ch28','pdf_page'=>785,
                    'confidence_source'=>0.9,'confidence_node'=>0.9,'confidence_page'=>0.9]],
                'source_pdf_provenance'=>['carranza-periodontology@13e'=>['sha256'=>Guard::spec(139)['pdf_sha256']]]]]];
        $this->check(Guard::planMatches($singlePlan)&&Guard::researchMatches($singleAudit,$singlePlan),
            'Exact pinned Q139 source correction cannot be run as an independent single-question operation.');++$checks;

        $entries1401=[];
        foreach([165,168] as $number){
            $spec=Guard::spec($number);
            $entries1401[]=$spec+['number'=>$number,'audit_batch'=>'1401:operative-dentistry:q165-q168','old_scope_unverified'=>false];
        }
        $plan1401=['format'=>'fanoos.residency-ai-source-citation-replacement-plan/1','year'=>1401,'round'=>1,
            'audit_path'=>'/srv/fanoos/shared/research/classification/recovery-1401-q165-q168-fixture/classification/reports/audit.json',
            'audit_sha256'=>str_repeat('b',64),'entries'=>$entries1401];
        $audit1401=['format'=>'fanoos.classification.provenance-audit/1','research_only'=>true,
            'batch_count'=>1,'questions'=>2,'accepted'=>2,'pending'=>0,
            'batches'=>[['batch'=>'1401:operative-dentistry:q165-q168','exam_year'=>1401,'subject'=>'operative-dentistry',
                'questions'=>2,'accepted'=>2,'pending'=>0,'question_content_identical'=>true,
                'mapped'=>[
                    '165'=>['ref'=>'craig-restorative-materials@14e#ch13','pdf_page'=>290,
                        'confidence_source'=>0.9,'confidence_node'=>0.9,'confidence_page'=>0.9],
                    '168'=>['ref'=>'craig-restorative-materials@14e#ch13','pdf_page'=>290,
                        'confidence_source'=>0.95,'confidence_node'=>0.95,'confidence_page'=>0.95],
                ],'source_pdf_provenance'=>['craig-restorative-materials@14e'=>['sha256'=>Guard::spec(165)['pdf_sha256']]]]]];
        $this->check(Guard::planMatches($plan1401)&&Guard::researchMatches($audit1401,$plan1401),
            'Exact pinned 1401 Q165/Q168 source replacement plan was rejected.');++$checks;

        $entriesPerio1401=[];
        foreach([123,128] as $number){
            $spec=Guard::spec($number);
            $entriesPerio1401[]=$spec+['number'=>$number,'audit_batch'=>'1401:periodontics:q123-q128','old_scope_unverified'=>false];
        }
        $planPerio1401=['format'=>'fanoos.residency-ai-source-citation-replacement-plan/1','year'=>1401,'round'=>1,
            'audit_path'=>'/srv/fanoos/shared/research/classification/recovery-1401-q123-q128-fixture/classification/reports/audit.json',
            'audit_sha256'=>str_repeat('c',64),'entries'=>$entriesPerio1401];
        $auditPerio1401=['format'=>'fanoos.classification.provenance-audit/1','research_only'=>true,
            'batch_count'=>1,'questions'=>2,'accepted'=>2,'pending'=>0,
            'batches'=>[['batch'=>'1401:periodontics:q123-q128','exam_year'=>1401,'subject'=>'periodontics',
                'questions'=>2,'accepted'=>2,'pending'=>0,'question_content_identical'=>true,
                'mapped'=>[
                    '123'=>['ref'=>'carranza-periodontology@13e#ch49','pdf_page'=>1112,
                        'confidence_source'=>0.9,'confidence_node'=>0.9,'confidence_page'=>0.9],
                    '128'=>['ref'=>'carranza-periodontology@13e#ch64','pdf_page'=>1454,
                        'confidence_source'=>0.95,'confidence_node'=>0.95,'confidence_page'=>0.95],
                ],'source_pdf_provenance'=>['carranza-periodontology@13e'=>['sha256'=>Guard::spec(123)['pdf_sha256']]]]]];
        $this->check(Guard::planMatches($planPerio1401)&&Guard::researchMatches($auditPerio1401,$planPerio1401),
            'Exact pinned 1401 Q123/Q128 source replacement plan was rejected.');++$checks;

        $entries1398=[];
        foreach(Guard::supportedQuestions(1398) as $number){
            $spec=Guard::spec($number);
            $entries1398[]=$spec+[
                'number'=>$number,
                'audit_batch'=>$number===48
                    ?'1398:oral-medicine:oral-medicine-low-confidence'
                    :'1398:operative-dentistry:operative-low-confidence',
                'old_scope_unverified'=>false,
            ];
        }
        $plan1398=['format'=>'fanoos.residency-ai-source-citation-replacement-plan/1','year'=>1398,'round'=>1,
            'audit_path'=>'/srv/fanoos/shared/research/classification/recovery-1398-confidence-citations-fixture/classification/reports/audit.json',
            'audit_sha256'=>str_repeat('d',64),'entries'=>$entries1398];
        $mappedOperative=[];
        foreach([176,180,182] as $number){
            $spec=Guard::spec($number);
            $mappedOperative[(string)$number]=[
                'ref'=>$spec['reference'].'#ch'.str_pad($spec['chapter'],2,'0',STR_PAD_LEFT),
                'pdf_page'=>$spec['pdf_page'],
                'confidence_source'=>$spec['confidence'],
                'confidence_node'=>$spec['confidence'],
                'confidence_page'=>$spec['confidence'],
            ];
        }
        $spec48=Guard::spec(48);
        $audit1398=['format'=>'fanoos.classification.provenance-audit/1','research_only'=>true,
            'batch_count'=>2,'questions'=>4,'accepted'=>4,'pending'=>0,
            'batches'=>[
                ['batch'=>'1398:operative-dentistry:operative-low-confidence','exam_year'=>1398,'subject'=>'operative-dentistry',
                    'questions'=>3,'accepted'=>3,'pending'=>0,'question_content_identical'=>true,'mapped'=>$mappedOperative,
                    'source_pdf_provenance'=>['sturdevant-operative@7e'=>['sha256'=>Guard::spec(176)['pdf_sha256']]]],
                ['batch'=>'1398:oral-medicine:oral-medicine-low-confidence','exam_year'=>1398,'subject'=>'oral-medicine',
                    'questions'=>1,'accepted'=>1,'pending'=>0,'question_content_identical'=>true,
                    'mapped'=>['48'=>[
                        'ref'=>$spec48['reference'].'#ch'.str_pad($spec48['chapter'],2,'0',STR_PAD_LEFT),
                        'pdf_page'=>$spec48['pdf_page'],'confidence_source'=>$spec48['confidence'],
                        'confidence_node'=>$spec48['confidence'],'confidence_page'=>$spec48['confidence'],
                    ]],
                    'source_pdf_provenance'=>['burket-oral-medicine@12e'=>['sha256'=>$spec48['pdf_sha256']]]],
            ]];
        $this->check(Guard::planMatches($plan1398)&&Guard::researchMatches($audit1398,$plan1398),
            'Exact four-row 1398 source correction plan was rejected.');++$checks;
        $badAudit1398=$audit1398;$badAudit1398['batches'][0]['mapped']['182']['confidence_page']=0.80;
        $this->check(!Guard::researchMatches($badAudit1398,$plan1398),
            'Below-threshold confidence in a 1398 provenance audit passed.');++$checks;
        $badPlan1398=$plan1398;$badPlan1398['entries'][2]['pdf_page']=162;
        $this->check(!Guard::planMatches($badPlan1398),'Unpinned 1398 correction page passed the exact plan guard.');++$checks;

        $root=dirname(__DIR__,2);
        $operator=file_get_contents($root.'/scripts/references/repair_residency_ai_source_citation_replacements.php');
        $this->check(is_string($operator),'Versioned citation-replacement operator is missing.');++$checks;
        $this->check(str_contains($operator,"\$afterImmutableByNumber[(string)\$entry['number']]=\$immutableAfter['immutable'];")
            &&str_contains($operator,'Guard::immutableSnapshotsMatch($beforeImmutable,$afterImmutableByNumber)')
            &&!str_contains($operator,"\$afterImmutable[(string)\$entry['number']]=\$afterImmutable['immutable'];"),
            'Operator must keep distinct before/after immutable maps for every planned question.');++$checks;
        $updates=preg_match_all('/UPDATE\s+bank_question_sources\s+SET\s+(.+?)\s+WHERE/is',$operator,$matches);
        $fields=$updates===1&&preg_match_all('/(?:^|,)\s*([a-z0-9_]+)\s*=/i',$matches[1][0],$fieldMatches)
            ? array_map('strtolower',$fieldMatches[1]):[];
        $this->check($fields===['edition_id','node_id','page','printed_page','pdf_page','pdf_sha256','anchor_text',
            'confidence_source','confidence_node','confidence_page','official_scope_status','official_scope_note','scope_unverified'],
            'Operator writes outside citation, scope and confidence metadata.');++$checks;
        foreach(['FOR UPDATE','GET_LOCK','BackupManifest::verify','PrintedBookPageEvidence::corroborates',
            'PrintedBookPageEvidence::pageContainsEvidence','OfficialScopePageGuard::pageDisposition',
            'Guard::researchMatches','Guard::immutableSnapshotsMatch','residencySourceReplacementAssessment',
            'page <=> :old_page','$db->rollBack();','$db->commit();'] as $marker){
            $this->check(str_contains($operator,$marker),"Operator is missing guard {$marker}.");++$checks;
        }
        foreach(['confidence_source=:confidence_source','confidence_node=:confidence_node','confidence_page=:confidence_page',
            'confidence_source=:old_confidence_source','confidence_node=:old_confidence_node','confidence_page=:old_confidence_page'] as $binding){
            $this->check(str_contains($operator,$binding),"Operator is missing the unique confidence bind {$binding}.");++$checks;
        }
        foreach(['UPDATE bank_questions','UPDATE bank_question_choices','UPDATE bank_official_answers',
            'UPDATE exam_assessments','UPDATE exam_attempts','UPDATE exam_version_reviews','BankPublisher::publish'] as $forbidden){
            $this->check(!str_contains($operator,$forbidden),"Operator contains forbidden mutation {$forbidden}.");++$checks;
        }
        return $checks;
    }

    private function check(bool $condition,string $message):void
    {
        if(!$condition)throw new \RuntimeException($message);
    }
}
