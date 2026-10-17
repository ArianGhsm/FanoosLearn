<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Bank\VerifiedHumanReferenceChapterPageGuard;
use RuntimeException;

final class VerifiedHumanReferenceChapterPageGuardTest
{
    public function run(): int
    {
        $sha = str_repeat('a', 64);
        $plan = ['year'=>1404, 'subject'=>'operative-dentistry'];
        $item = [
            'number'=>163,'source_id'=>'01a11bb7-20f1-737d-a225-785488e6b77d',
            'expected_edition'=>'sturdevant-operative@7e','edition'=>'summit-operative@4e',
            'expected_chapter'=>'14','chapter'=>'7','pdf_page'=>338,'pdf_sha256'=>$sha,
            'evidence'=>['carbon imparts hardness.'],
            'expected_legacy_page'=>null,'expected_stored_printed_page'=>null,
            'expected_old_pdf_page'=>null,'expected_old_pdf_sha256'=>null,
            'expected_official_scope_status'=>'in_scope','expected_official_scope_note'=>null,
            'expected_stem_sha256'=>str_repeat('b', 64),'expected_choices_sha256'=>str_repeat('c', 64),
            'expected_answer_sha256'=>str_repeat('d', 64),'expected_anchor_sha256'=>str_repeat('e', 64),
            'expected_reviewed_at'=>null,'expected_reviewed_by_user_id'=>null,
            'expected_question_status'=>'published','expected_answer_status'=>'final',
        ];
        $audit = [
            'format'=>'fanoos.human-reference-chapter-page-audit/1','research_only'=>true,
            'year'=>1404,'subject'=>'operative-dentistry','records'=>[
                array_merge($item, ['scientifically_accepted'=>true]),
            ],
        ];
        $this->check(VerifiedHumanReferenceChapterPageGuard::auditMatches($audit, $plan, $item),
            'Valid exact-edition human-reference correction was rejected.');
        $checks = 1;
        foreach ([
            ['expected_edition'=>'summit-operative@4e'],['edition'=>'sturdevant-operative@7e'],
            ['expected_chapter'=>'13'],['chapter'=>'6'],['pdf_page'=>339],['pdf_sha256'=>str_repeat('f',64)],
            ['expected_stem_sha256'=>str_repeat('f',64)],['expected_choices_sha256'=>str_repeat('f',64)],
            ['expected_answer_sha256'=>str_repeat('f',64)],['expected_anchor_sha256'=>str_repeat('f',64)],
            ['expected_official_scope_status'=>'outside_scope_exam_question'],
            ['expected_reviewed_by_user_id'=>'01a11bb7-20f1-737d-a225-785488e6b77d'],
        ] as $change) {
            $this->check(!VerifiedHumanReferenceChapterPageGuard::auditMatches(
                $audit, $plan, array_replace($item, $change)
            ), 'Audit accepted a changed source or immutable question identity.');
            ++$checks;
        }
        $unaccepted = $audit;
        $unaccepted['records'][0]['scientifically_accepted'] = false;
        $this->check(!VerifiedHumanReferenceChapterPageGuard::auditMatches($unaccepted, $plan, $item),
            'Scientifically unaccepted correction passed.');
        ++$checks;

        $operatorPath = dirname(__DIR__, 2) . '/scripts/references/repair_verified_human_reference_chapter_page.php';
        $operator = file_get_contents($operatorPath);
        if (!is_string($operator)) {
            throw new RuntimeException('Cross-edition source operator is missing.');
        }
        preg_match_all('/UPDATE\s+bank_question_sources\s+SET\s+(.+?)\s+WHERE/si', $operator, $updates);
        $this->check(count($updates[1]) === 1, 'Expected exactly one guarded source update.');
        $fields = preg_match_all('/([a-z0-9_]+)\s*=/i', $updates[1][0], $matches) ? $matches[1] : [];
        $this->check($fields === ['edition_id','node_id','pdf_page','pdf_sha256'],
            'Correction may update only the exact source edition/chapter/PDF binding.');
        foreach ([
            "origin='human'", 'reviewed_at <=> :old_reviewed', 'reviewed_by_user_id <=> :old_reviewer',
            'target_validity.is_official=1', 'scopeAllows(', 'pageInMap(',
            'PrintedBookPageEvidence::pageContainsEvidence', 'BackupManifest::verify',
            '$db->rollBack();', '$db->commit();', 'FOR UPDATE',
            "'human_source_provenance_changed'=>0", "'human_review_metadata_changed'=>0",
        ] as $marker) {
            $this->check(str_contains($operator, $marker), "Required protected correction check missing: {$marker}");
            ++$checks;
        }
        $this->check(!str_contains($operator, 'UPDATE bank_questions'), 'Question table must remain immutable.');
        $this->check(!str_contains($operator, 'UPDATE bank_official_answers'), 'Official key table must remain immutable.');
        $checks += 2;

        return $checks;
    }

    private function check(bool $okay, string $message): void
    {
        if (!$okay) {
            throw new RuntimeException($message);
        }
    }
}
