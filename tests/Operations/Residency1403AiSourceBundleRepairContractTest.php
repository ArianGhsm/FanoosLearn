<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use RuntimeException;

final class Residency1403AiSourceBundleRepairContractTest
{
    public function run(): int
    {
        $root = dirname(__DIR__, 2);
        $operatorPath = $root . '/scripts/references/repair_residency_1403_ai_source_bundles.php';
        $builderPath = $root . '/scripts/references/prepare_residency_1403_ai_source_bundle_plan.php';
        $operator = file_get_contents($operatorPath);
        $builder = file_get_contents($builderPath);
        if (!is_string($operator) || !is_string($builder)) {
            throw new RuntimeException('1403 AI bundle scripts are missing.');
        }

        $checks = 0;
        $updates = preg_match_all('/UPDATE\s+bank_question_sources\s+SET\s+(.+?)\s+WHERE/is', $operator, $updateMatches);
        $this->check($updates === 1, 'Bundle operator must have one guarded source-row update.');
        ++$checks;
        $fields = preg_match_all('/(?:^|,)\s*([a-z0-9_]+)\s*=/i', $updateMatches[1][0], $fieldMatches)
            ? array_map('strtolower', $fieldMatches[1]) : [];
        $this->check($fields === ['edition_id','node_id','page','printed_page','pdf_page','pdf_sha256','anchor_text'],
            'Existing primary source update changed fields outside the source citation.');
        ++$checks;

        $inserts = preg_match_all('/INSERT\s+INTO\s+bank_question_sources\s*\((.+?)\)\s*VALUES/is', $operator);
        $this->check($inserts === 1, 'Bundle operator must insert exactly one audited secondary source row.');
        ++$checks;
        foreach (['origin=\'ai\'', 'is_primary', 'reviewed_by_user_id', 'reviewed_at', 'FOR UPDATE', 'GET_LOCK',
            'BackupManifest::verify', '$db->rollBack();', '$db->commit();',
            'questions_choices_official_answers_human_reviews_attempts_assessments_changed'] as $marker) {
            $this->check(str_contains($operator, $marker), "Bundle operator is missing guard {$marker}.");
            ++$checks;
        }
        foreach (['UPDATE bank_questions', 'UPDATE bank_question_choices', 'UPDATE bank_official_answers',
            'UPDATE exam_assessments', 'BankPublisher::publish'] as $forbidden) {
            $this->check(!str_contains($operator, $forbidden), "Bundle operator contains forbidden mutation {$forbidden}.");
            ++$checks;
        }
        foreach ([
            'Residency1403AiSourceBundleCorrectionGuard::evidenceAuditMatches',
            'Residency1403AiSourceBundleCorrectionGuard::entryMatchesEvidence',
            'Residency1403AiSourceCitationCorrectionGuard::officialAnswerMatches',
            'VerifiedHumanChapterPageRepairGuard::scopeAllows',
            'VerifiedHumanChapterPageRepairGuard::pageInMap',
            'PrintedBookPageEvidence::pageContainsEvidence',
            'researchManifestMatches',
        ] as $marker) {
            $this->check(str_contains($operator, $marker), "Bundle operator is missing validation {$marker}.");
            ++$checks;
        }
        foreach ([
            'Residency1403AiSourceBundleCorrectionGuard::evidenceAuditMatches',
            'Residency1403AiSourceCitationCorrectionGuard::independentAuditMatches',
            'PrintedBookPageEvidence::pageContainsEvidence',
            'SET TRANSACTION READ ONLY',
            'database_writes' => 'database_writes',
        ] as $marker) {
            $this->check(str_contains($builder, $marker), "Bundle plan builder is missing read-only validation {$marker}.");
            ++$checks;
        }

        return $checks;
    }

    private function check(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
