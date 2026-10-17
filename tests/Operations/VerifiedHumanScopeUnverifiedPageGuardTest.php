<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Bank\VerifiedHumanScopeUnverifiedPageGuard;
use RuntimeException;

final class VerifiedHumanScopeUnverifiedPageGuardTest
{
    public function run(): int
    {
        $sha = str_repeat('a', 64);
        $plan = ['year'=>1404,'subject'=>'operative-dentistry'];
        $item = [
            'number'=>164,'source_id'=>'01a11bb7-20f9-7059-96e0-7e554f755858',
            'edition'=>'summit-operative@4e','chapter'=>'9','pdf_page'=>438,'pdf_sha256'=>$sha,
            'evidence'=>['high vapor pressure','evaporate'],
            'expected_legacy_page'=>null,'expected_stored_printed_page'=>null,
            'expected_anchor_sha256'=>str_repeat('b',64),'expected_stem_sha256'=>str_repeat('c',64),
            'expected_choices_sha256'=>str_repeat('d',64),'expected_answer_sha256'=>str_repeat('e',64),
            'expected_reviewed_at'=>null,'expected_reviewed_by_user_id'=>null,
            'expected_question_status'=>'published','expected_answer_status'=>'final',
            'note'=>'محدودهٔ صفحه هنوز تأیید نشده است.',
        ];
        $audit=['format'=>'fanoos.human-source-scope-unverified-page-audit/1','research_only'=>true,
            'year'=>1404,'subject'=>'operative-dentistry','records'=>[
                array_merge($item,['location_accepted'=>true,'scope_unverified'=>true]),
            ]];
        $this->check(VerifiedHumanScopeUnverifiedPageGuard::auditMatches($audit,$plan,$item),
            'Valid page-location/scope-uncertainty audit was rejected.');
        $checks=1;
        foreach ([['pdf_page'=>439],['note'=>'changed scope note'],['source_id'=>'01a11bb7-20f9-7059-96e0-7e554f755859']] as $change) {
            $this->check(!VerifiedHumanScopeUnverifiedPageGuard::auditMatches(
                $audit,$plan,array_replace($item,$change)
            ),'A changed source location or scope decision passed audit binding.');
            ++$checks;
        }
        $this->check(VerifiedHumanScopeUnverifiedPageGuard::chapterIsPartial(
            '[{"number":"9","partial":"through printed page 217"}]','9'),
            'An officially partial chapter was not recognized.');
        ++$checks;
        $this->check(!VerifiedHumanScopeUnverifiedPageGuard::chapterIsPartial('[{"number":"9"}]','9'),
            'A complete chapter was mislabeled partial.');
        ++$checks;
        $this->check(!VerifiedHumanScopeUnverifiedPageGuard::chapterIsPartial('{broken','9'),
            'Malformed scope JSON was accepted.');
        ++$checks;

        $root=dirname(__DIR__,2);
        $operator=(string)file_get_contents($root.'/scripts/references/record_verified_human_scope_unverified_page.php');
        preg_match_all('/UPDATE\s+bank_question_sources\s+SET\s+(.+?)\s+WHERE/si',$operator,$updates);
        $this->check(count($updates[1])===1,'Expected one guarded source-only update.');++$checks;
        $fields=preg_match_all('/([a-z0-9_]+)\s*=/i',$updates[1][0],$matches)?$matches[1]:[];
        $this->check($fields===['pdf_page','pdf_sha256','scope_unverified','official_scope_note'],
            'Scope-unverified writer may update only page, hash and scope disposition.');++$checks;
        foreach ([
            'VerifiedHumanScopeUnverifiedPageGuard::auditMatches',
            'VerifiedHumanScopeUnverifiedPageGuard::chapterIsPartial',
            'VerifiedHumanChapterPageRepairGuard::pageInMap',
            'PrintedBookPageEvidence::hasNoPrintedPageLabel',
            'PrintedBookPageEvidence::pageContainsEvidence',
            'BackupManifest::verify','$db->rollBack();','$db->commit();',
            "origin='human'","reviewed_at <=> :old_reviewed","reviewed_by_user_id <=> :old_reviewer",
        ] as $marker) {
            $this->check(str_contains($operator,$marker),"Missing scope-unverified guard: {$marker}");++$checks;
        }
        $gate=(string)file_get_contents($root.'/apps/platform/src/Bank/ResidencyYearPublicationGate.php');
        $this->check(str_contains($gate,"scope_unverified'] ?? false")
            && str_contains($gate,"(bool) (\$source['scope_unverified'] ?? false)") && str_contains($gate,'continue;'),
            'Publication gate must reject scope-unverified citations.');++$checks;
        $migration=(string)file_get_contents($root.'/database/migrations/0047_bank_source_scope_unverified.sql');
        $this->check(str_contains($migration,'ADD COLUMN scope_unverified BOOLEAN')&&!str_contains(strtoupper($migration),'DROP COLUMN'),
            'Scope status migration must expand the enum only.');++$checks;
        $facts=(string)file_get_contents($root.'/apps/platform/public/assets/web/pages/question-facts.js');
        $this->check(str_contains($facts,'c.scope_unverified === true'),
            'Unverified scope note must be shown to the user.');++$checks;
        $this->check(!str_contains($operator,'UPDATE bank_questions')&&!str_contains($operator,'UPDATE bank_official_answers'),
            'Operator must not update questions or official answers.');$checks+=2;

        return $checks;
    }

    private function check(bool $okay,string $message):void
    {
        if(!$okay)throw new RuntimeException($message);
    }
}
