<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Bank\VerifiedOutsideScopeSourceNoteRepairGuard;
use RuntimeException;

final class VerifiedOutsideScopeSourceNoteRepairGuardTest
{
    public function run(): int
    {
        $item = [
            'number'=>217,
            'source_id'=>'01a11be7-92af-7f0b-9a34-07081ed39101',
            'question_key'=>'residency-1400-1-217',
            'edition'=>'van-noort-materials@4e',
            'chapter'=>'3.8','printed_page'=>'222','pdf_page'=>237,
            'pdf_sha256'=>str_repeat('a',64),'anchor_sha256'=>str_repeat('b',64),
            'current_note_sha256'=>str_repeat('c',64),'stem_sha256'=>str_repeat('d',64),
            'choices_sha256'=>str_repeat('e',64),'answer_sha256'=>str_repeat('f',64),
            'question_status'=>'published','answer_status'=>'final','answer_choice_position'=>2,
            'answer_alignment_status'=>'unresolved',
            'question_identity_verified'=>true,
            'page_evidence'=>'composite luting resin is preferred for veneers',
            'corrected_note'=>'ارجاع Van Noort فصل 3.8 صفحه 222/PDF237 خارج از محدوده رسمی است؛ هم‌ترازی حل‌نشده است؛ کلید رسمی گزینه 2 حفظ شد و تعارض ثبت شد.',
        ];
        $plan=['format'=>'fanoos.outside-scope-source-note-repair/1','year'=>1400,
            'subject'=>'dental-materials','entries'=>[$item]];
        $audit=['format'=>'fanoos.outside-scope-source-note-audit/1','research_only'=>true,
            'year'=>1400,'subject'=>'dental-materials','records'=>[$item+[
                'question_identity_verified'=>true,'page_evidence_verified'=>true,
                'answer_alignment_reviewed'=>true,'official_answer_unchanged'=>true,
            ]]];
        $this->check(VerifiedOutsideScopeSourceNoteRepairGuard::auditMatches($audit,$plan,$item),
            'A valid pinned outside-scope note correction was rejected.');
        $checks=1;
        foreach ([
            ['current_note_sha256'=>str_repeat('1',64)],
            ['answer_choice_position'=>4],
            ['pdf_page'=>238],
            ['corrected_note'=>'A note without a scope/conflict finding.'],
            ['answer_alignment_status'=>'confirmed'],
        ] as $change) {
            $this->check(!VerifiedOutsideScopeSourceNoteRepairGuard::auditMatches(
                $audit,$plan,array_replace($item,$change)),
                'A changed source, answer, or note identity passed the correction guard.');
            ++$checks;
        }
        $badAudit=$audit;
        $badAudit['records'][0]['answer_alignment_reviewed']=false;
        $this->check(!VerifiedOutsideScopeSourceNoteRepairGuard::auditMatches($badAudit,$plan,$item),
            'An audit without an accepted answer-conflict finding was accepted.');
        ++$checks;

        $root=dirname(__DIR__,2);
        $operator=file_get_contents($root.'/scripts/references/repair_verified_outside_scope_source_note.php');
        $this->check(is_string($operator),'Outside-scope note correction operator is missing.');
        ++$checks;
        $updates=preg_match_all('/UPDATE\s+bank_question_sources\s+SET\s+(.+?)\s+WHERE/is',$operator,$matches);
        $fields=$updates===1&&preg_match_all('/(?:^|,)\s*([a-z0-9_]+)\s*=/i',$matches[1][0],$fieldMatches)
            ?array_map('strtolower',$fieldMatches[1]):[];
        $this->check($fields===['official_scope_note'],'Note-correction operator must update only official_scope_note.');
        ++$checks;
        foreach (['BackupManifest::verify','SHA2(official_scope_note,256)','PrintedBookPageEvidence::corroborates',
            'PrintedBookPageEvidence::pageContainsEvidence','OfficialScopePageGuard::pageDisposition'] as $marker) {
            $this->check(str_contains($operator,$marker),"Operator is missing {$marker}.");
            ++$checks;
        }
        foreach (['UPDATE bank_questions','UPDATE bank_question_choices','UPDATE bank_official_answers',
            'UPDATE exam_assessments','UPDATE exam_attempts','BankPublisher::publish'] as $forbidden) {
            $this->check(!str_contains($operator,$forbidden),"Operator contains forbidden mutation {$forbidden}.");
            ++$checks;
        }
        return $checks;
    }

    private function check(bool $okay,string $message):void
    {
        if(!$okay) throw new RuntimeException($message);
    }
}
