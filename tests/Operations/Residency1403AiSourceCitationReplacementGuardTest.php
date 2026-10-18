<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Bank\Residency1403AiSourceCitationReplacementGuard as Guard;

final class Residency1403AiSourceCitationReplacementGuardTest
{
    public function run(): int
    {
        $checks = 0;
        $this->check(Guard::supportedQuestions() === [152, 162], 'Only Q152 and Q162 should be eligible.');
        ++$checks;
        $this->check(Guard::spec(151) === null, 'An unpinned question entered the correction route.');
        ++$checks;
        foreach ([
            [false, false, false, false, true],
            [false, false, false, true, true],
            [false, true, false, true, false],
            [false, false, true, true, false],
            [true, true, true, true, true],
            [true, true, true, false, false],
            [true, false, true, true, false],
        ] as [$apply, $backup, $receipt, $preview, $expected]) {
            $this->check(Guard::modeArgumentsValid($apply, $backup, $receipt, $preview) === $expected,
                'Apply and rollback-preview command argument rules are incorrect.');
            ++$checks;
        }

        foreach ([152, 162] as $number) {
            $spec = Guard::spec($number);
            $this->check(is_array($spec), "Q{$number} specification is missing.");
            ++$checks;
            $plan = [
                'format' => 'fanoos.residency1403.ai-source-citation-replacement-plan/1',
                'year' => 1403,
                'round' => 1,
                'number' => $number,
                'subject' => $spec['subject'],
                'reference' => $spec['reference'],
                'chapter' => $spec['chapter'],
                'pdf_page' => $spec['pdf_page'],
                'printed_page' => $spec['printed_page'],
                'pdf_sha256' => $spec['pdf_sha256'],
                'confidence' => $spec['confidence'],
                'audit_path' => $spec['audit_path'],
                'audit_sha256' => $spec['audit_sha256'],
                'evidence_input_path' => $spec['evidence_input_path'],
                'evidence_input_sha256' => $spec['evidence_input_sha256'],
                'expected_source' => [
                    'source_id' => '01a11b09-0000-7000-8000-000000000000',
                    'edition' => $spec['old_reference'],
                    'chapter' => $spec['old_chapter'],
                    'legacy_page' => $spec['old_legacy_page'],
                    'printed_page' => null,
                    'pdf_page' => $spec['old_pdf_page'],
                    'pdf_sha256' => $spec['old_pdf_sha256'],
                    'confidence' => $spec['old_confidence'],
                    'anchor_sha256' => str_repeat('a', 64),
                ],
                'evidence_fragments' => ['Exact short evidence from the approved original PDF page.'],
                'anchor_text' => 'Exact short evidence from the approved original PDF page.',
            ];
            $this->check(Guard::planMatches($number, $plan, $plan['evidence_fragments']),
                "Q{$number} exact correction plan was rejected.");
            ++$checks;
            foreach ([
                ['pdf_page' => $spec['pdf_page'] + 1],
                ['reference' => 'summit-operative@4e'],
                ['confidence' => 0.849],
                ['audit_sha256' => str_repeat('b', 64)],
                ['evidence_input_sha256' => str_repeat('c', 64)],
                ['expected_source' => array_replace($plan['expected_source'], ['printed_page' => '11'])],
            ] as $change) {
                $this->check(!Guard::planMatches($number, array_replace($plan, $change), $plan['evidence_fragments']),
                    "Q{$number} mutated plan passed the exact target guard.");
                ++$checks;
            }
        }

        $spec = Guard::spec(152);
        $question = [
            'number' => 152,
            'subject' => 'operative-dentistry',
            'stem' => 'A sufficiently long unchanged research stem for a guard fixture.',
            'choices' => ['a', 'b', 'c', 'd'],
            'answer' => ['status' => 'final', 'choice' => 2, 'also_correct' => []],
        ];
        $study = ['format' => 'fanoos.classification.study-only/1', 'exam_type' => 'residency',
            'year' => 1403, 'round' => 1, 'questions' => [$question]];
        $validatedQuestion = $question;
        $validatedQuestion['sources'] = [[
            'ref' => 'sturdevant-operative@7e#ch01', 'pdf_page' => 26,
            'pdf_sha256' => $spec['pdf_sha256'], 'origin' => 'ai', 'page' => 'pdf 26',
        ]];
        $validated = ['format' => 'fanoos.classification.study-only/1', 'exam_type' => 'residency',
            'year' => 1403, 'round' => 1, 'questions' => [$validatedQuestion]];
        $decision = ['number' => 152, 'edition' => 'sturdevant-operative@7e', 'chapter' => '1',
            'page' => 26, 'confidence' => 0.95,
            'evidence' => 'This is a sufficiently long exact original-page evidence quote for testing.'];
        $decisions = [$decision];
        $audit = [
            'format' => 'fanoos.classification.provenance-audit/1',
            'research_only' => true,
            'batch_count' => 1,
            'questions' => 1,
            'accepted' => 1,
            'pending' => 0,
            'batches' => [[
                'batch' => '1403:operative-dentistry:q152-fixture',
                'subject' => 'operative-dentistry',
                'exam_year' => 1403,
                'questions' => 1,
                'accepted' => 1,
                'pending' => 0,
                'classification_basis' => 'official_exam_year',
                'question_content_identical' => true,
                'reference_years' => [1403],
                'decisions_sha256' => $spec['decisions_sha256'],
                'mapped' => ['152' => [
                    'ref' => 'sturdevant-operative@7e#ch01', 'pdf_page' => 26,
                ]],
                'source_pdf_provenance' => ['sturdevant-operative@7e' => [
                    'source' => 'verified-server-pdf', 'sha256' => $spec['pdf_sha256'],
                ]],
            ]],
        ];
        $study['questions'][0]['unused_fixture_key'] = null;
        $validated['questions'][0]['unused_fixture_key'] = null;
        $this->check(Guard::researchMatches(152, $study, $decisions, $validated, $audit,
            $spec['study_sha256'], $spec['decisions_sha256'], $spec['validated_sha256']),
            'Matching Q152 private research evidence was rejected.');
        ++$checks;

        $badAudit = $audit;
        $badAudit['batches'][0]['mapped']['152']['pdf_page'] = 27;
        $this->check(!Guard::researchMatches(152, $study, $decisions, $validated, $badAudit,
            $spec['study_sha256'], $spec['decisions_sha256'], $spec['validated_sha256']),
            'Changed audit page passed the research guard.');
        ++$checks;
        $badDecision = $decisions;
        $badDecision[0]['confidence'] = 0.84;
        $this->check(!Guard::researchMatches(152, $study, $badDecision, $validated, $audit,
            $spec['study_sha256'], $spec['decisions_sha256'], $spec['validated_sha256']),
            'Below-threshold confidence passed the research guard.');
        ++$checks;
        $badValidated = $validated;
        $badValidated['questions'][0]['sources'][0]['pdf_sha256'] = str_repeat('c', 64);
        $this->check(!Guard::researchMatches(152, $study, $decisions, $badValidated, $audit,
            $spec['study_sha256'], $spec['decisions_sha256'], $spec['validated_sha256']),
            'Changed validated source page passed the research guard.');
        ++$checks;

        $root = dirname(__DIR__, 2);
        $operator = file_get_contents($root . '/scripts/references/repair_residency_1403_ai_source_citation_replacements.php');
        $this->check(is_string($operator), 'Citation replacement operator is missing.');
        ++$checks;
        $this->check(str_contains($operator, 'SELECT s.id source_id')
            && str_contains($operator, "\$old['source_id'] !== \$expected['source_id']")
            && !str_contains($operator, "\$old['id']"),
            'Locked source snapshot comparison must use the selected source_id alias.');
        ++$checks;
        $updates = preg_match_all('/UPDATE\s+bank_question_sources\s+SET\s+(.+?)\s+WHERE/is', $operator, $matches);
        $fields = $updates === 1 && preg_match_all('/(?:^|,)\s*([a-z0-9_]+)\s*=/i', $matches[1][0], $fieldMatches)
            ? array_map('strtolower', $fieldMatches[1]) : [];
        $this->check($fields === ['edition_id','node_id','page','printed_page','pdf_page','pdf_sha256',
            'anchor_text','confidence_source','confidence_node','confidence_page'],
            'Operator writes fields outside the existing citation and confidence metadata.');
        ++$checks;
        foreach (['FOR UPDATE', 'GET_LOCK', 'BackupManifest::verify', 'PrintedBookPageEvidence::corroborates',
            'PrintedBookPageEvidence::pageContainsEvidence', 'OfficialScopePageGuard::pageDisposition',
            'Guard::modeArgumentsValid',
            'replacementAssessmentSnapshot', 'Rollback preview does not match this exact plan.',
            'private_plan_sha256', '$db->rollBack();', '$db->commit();'] as $marker) {
            $this->check(str_contains($operator, $marker), "Operator is missing guard {$marker}.");
            ++$checks;
        }
        foreach (['UPDATE bank_questions', 'UPDATE bank_question_choices', 'UPDATE bank_official_answers',
            'UPDATE exam_assessments', 'UPDATE exam_attempts', 'UPDATE exam_version_reviews',
            'BankPublisher::publish'] as $forbidden) {
            $this->check(!str_contains($operator, $forbidden), "Operator contains forbidden mutation {$forbidden}.");
            ++$checks;
        }
        $builder = file_get_contents($root . '/scripts/references/prepare_residency_1403_ai_source_citation_replacement_plan.php');
        $this->check(is_string($builder) && str_contains($builder, 'SET TRANSACTION READ ONLY')
            && str_contains($builder, 'database_writes\' => 0'),
            'Plan builder must run read-only and declare zero database writes.');
        ++$checks;
        foreach (['UPDATE bank_question_sources', 'INSERT INTO bank_question_sources',
            'DELETE FROM bank_question_sources', 'BankPublisher::publish'] as $forbidden) {
            $this->check(!str_contains($builder, $forbidden), "Plan builder contains forbidden mutation {$forbidden}.");
            ++$checks;
        }

        return $checks;
    }

    private function check(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }
}
