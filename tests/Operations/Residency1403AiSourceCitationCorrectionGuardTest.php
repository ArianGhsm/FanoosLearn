<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Bank\Residency1403AiSourceCitationCorrectionGuard;
use RuntimeException;

final class Residency1403AiSourceCitationCorrectionGuardTest
{
    public function run(): int
    {
        $checks = 0;
        $manifest = [
            'format' => 'fanoos.residency1403.research-only-source-completeness/1',
            'research_only' => true,
            'question_count' => 250,
            'canonical_source_sha256' => Residency1403AiSourceCitationCorrectionGuard::CANONICAL_SHA256,
            'live_paired_unchanged' => 71,
            'english_final_exempt' => 20,
            'research_audited_not_published' => 153,
            'clinical_pending' => 6,
            'clinical_pending_numbers' => [127, 69, 68, 63, 62, 61],
            'db_updates_performed' => 0,
            'assessment_updates_performed' => 0,
        ];
        $this->check(Residency1403AiSourceCitationCorrectionGuard::researchManifestMatches($manifest),
            'Pinned 1403 research manifest was rejected.');
        ++$checks;
        foreach ([
            ['research_only' => false],
            ['question_count' => 249],
            ['canonical_source_sha256' => str_repeat('a', 64)],
            ['research_audited_not_published' => 152],
            ['db_updates_performed' => 1],
            ['clinical_pending_numbers' => [61, 62, 63, 68, 69, '127']],
        ] as $change) {
            $this->check(!Residency1403AiSourceCitationCorrectionGuard::researchManifestMatches(
                array_replace($manifest, $change)
            ), 'Changed or malformed research-manifest data passed the guard.');
            ++$checks;
        }

        $entry = [
            'number' => 216,
            'subject' => 'dental-materials',
            'new_ref' => 'craig-restorative-materials@14e',
            'new_ch' => '11',
            'pdf_page' => 228,
            'pdf_sha' => str_repeat('a', 64),
            'evidence' => 'A short evidence fragment from the approved original page.',
            'decisions_sha256' => str_repeat('b', 64),
        ];
        $audit = [
            'format' => 'fanoos.classification.provenance-audit/1',
            'research_only' => true,
            'batch_count' => 1,
            'questions' => 10,
            'accepted' => 8,
            'pending' => 2,
            'batches' => [[
                'batch' => '1403:dental-materials:materials-study-20261011',
                'subject' => 'dental-materials',
                'exam_year' => 1403,
                'questions' => 10,
                'accepted' => 8,
                'pending' => 2,
                'classification_basis' => 'official_exam_year',
                'question_content_identical' => true,
                'reference_years' => [1403],
                'decisions_sha256' => str_repeat('b', 64),
                'mapped' => ['216' => [
                    'ref' => 'craig-restorative-materials@14e#ch11',
                    'pdf_page' => 228,
                ]],
                'source_pdf_provenance' => ['craig-restorative-materials@14e' => [
                    'source' => 'verified-server-pdf',
                    'sha256' => str_repeat('a', 64),
                ]],
            ]],
        ];
        $decisions = [[
            'number' => 216,
            'edition' => 'craig-restorative-materials@14e',
            'chapter' => 11,
            'page' => 228,
            'evidence' => $entry['evidence'],
        ]];
        $this->check(Residency1403AiSourceCitationCorrectionGuard::independentAuditMatches(
            $entry,
            $audit,
            $decisions,
        ), 'Matching official-year audit and decision were rejected.');
        ++$checks;

        $invalidAudits = [];
        $changed = $audit;
        $changed['research_only'] = false;
        $invalidAudits[] = $changed;
        $changed = $audit;
        $changed['batches'][0]['classification_basis'] = 'nearest_edition';
        $invalidAudits[] = $changed;
        $changed = $audit;
        $changed['batches'][0]['exam_year'] = 1404;
        $invalidAudits[] = $changed;
        $changed = $audit;
        $changed['batches'][0]['question_content_identical'] = false;
        $invalidAudits[] = $changed;
        $changed = $audit;
        $changed['batches'][0]['accepted'] = 7;
        $invalidAudits[] = $changed;
        $changed = $audit;
        $changed['batches'][0]['mapped']['216']['pdf_page'] = 229;
        $invalidAudits[] = $changed;
        $changed = $audit;
        $changed['batches'][0]['source_pdf_provenance']['craig-restorative-materials@14e']['sha256'] = str_repeat('c', 64);
        $invalidAudits[] = $changed;
        $changed = $audit;
        $changed['pending'] = 3;
        $invalidAudits[] = $changed;
        foreach ($invalidAudits as $invalidAudit) {
            $this->check(!Residency1403AiSourceCitationCorrectionGuard::independentAuditMatches(
                $entry,
                $invalidAudit,
                $decisions,
            ), 'Altered audit scope or page evidence passed the guard.');
            ++$checks;
        }
        foreach ([
            array_replace($entry, ['new_ref' => 'craig-restorative-materials@13e']),
            array_replace($entry, ['pdf_page' => 229]),
            array_replace($entry, ['evidence' => 'different evidence on the page']),
        ] as $invalidEntry) {
            $this->check(!Residency1403AiSourceCitationCorrectionGuard::independentAuditMatches(
                $invalidEntry,
                $audit,
                $decisions,
            ), 'Changed plan identity or evidence passed the guard.');
            ++$checks;
        }
        $this->check(!Residency1403AiSourceCitationCorrectionGuard::independentAuditMatches(
            $entry,
            $audit,
            array_merge($decisions, $decisions),
        ), 'Duplicate audited decisions for the same question were accepted.');
        ++$checks;

        $checks += $this->privateFileContracts();
        $checks += $this->operatorContracts();
        return $checks;
    }

    private function privateFileContracts(): int
    {
        $root = sys_get_temp_dir() . '/fanoos-1403-guard-' . bin2hex(random_bytes(5));
        if (!mkdir($root, 0700)) {
            throw new RuntimeException('Cannot create private-file guard fixture.');
        }
        $nested = $root . '/batch/reports';
        $otherRoot = $root . '/other';
        mkdir($root . '/batch', 0700);
        mkdir($nested, 0700);
        mkdir($otherRoot, 0700);
        $file = $nested . '/plan.json';
        file_put_contents($file, '{}');
        chmod($file, 0600);
        $symlink = $nested . '/linked.json';
        symlink($file, $symlink);
        try {
            $this->check(Residency1403AiSourceCitationCorrectionGuard::isPrivateFile($file, $root),
                'Private file in a private research tree was rejected.');
            $checks = 1;
            chmod($file, 0640);
            $this->check(!Residency1403AiSourceCitationCorrectionGuard::isPrivateFile($file, $root),
                'Group-readable source evidence passed the private-file guard.');
            ++$checks;
            chmod($file, 0600);
            $this->check(!Residency1403AiSourceCitationCorrectionGuard::isPrivateFile($symlink, $root),
                'A symlink to private evidence passed the private-file guard.');
            ++$checks;
            $this->check(!Residency1403AiSourceCitationCorrectionGuard::isPrivateFile($file, $otherRoot),
                'A different trust root was accepted.');
            ++$checks;
            chmod($nested, 0750);
            $this->check(!Residency1403AiSourceCitationCorrectionGuard::isPrivateFile($file, $root),
                'A group-readable evidence directory passed the private-file guard.');
            ++$checks;
            return $checks;
        } finally {
            unlink($symlink);
            unlink($file);
            chmod($nested, 0700);
            rmdir($nested);
            rmdir($otherRoot);
            rmdir($root . '/batch');
            rmdir($root);
        }
    }

    private function operatorContracts(): int
    {
        $root = dirname(__DIR__, 2);
        $operator = (string) file_get_contents($root . '/scripts/references/repair_residency_1403_ai_sources.php');
        $this->check(str_contains($operator, 'RuntimeConfig::load();'),
            'The production CLI must load the updater runtime configuration before opening the database.');
        $checks = 1;
        $this->check(str_contains($operator, 'Residency1403AiSourceCitationCorrectionGuard::researchManifestMatches')
            && str_contains($operator, 'Residency1403AiSourceCitationCorrectionGuard::independentAuditMatches')
            && str_contains($operator, 'Residency1403AiSourceCitationCorrectionGuard::isPrivateFile'),
            'The CLI must use the tested research, audit and private-file guards.');
        ++$checks;
        preg_match_all('/^\s*(?:UPDATE|INSERT\s+INTO|DELETE\s+FROM)\s+([a-z_][a-z0-9_]*)/im', $operator, $mutations);
        $this->check($mutations[1] === [] && str_contains($operator, 'Residency1403AiSourceCitationCorrectionWriter::write'),
            'The CLI must delegate its only source-row mutation to the tested writer.');
        ++$checks;
        $writer = (string) file_get_contents($root . '/apps/platform/src/Bank/Residency1403AiSourceCitationCorrectionWriter.php');
        preg_match('/UPDATE\s+bank_question_sources\s+SET\s+(.+?)\s+WHERE/si', $writer, $update);
        $this->check(isset($update[1]), 'The guarded source-only compare-and-swap is required.');
        $fields = preg_match_all('/([a-z_][a-z0-9_]*)\s*=/i', $update[1], $matches) ? $matches[1] : [];
        $this->check($fields === ['edition_id', 'node_id', 'page', 'printed_page', 'pdf_page', 'pdf_sha256', 'anchor_text'],
            'Only the reviewed source-citation fields may be changed.');
        $checks += 2;
        foreach ([
            'BackupManifest::verify', 'FOR UPDATE', 'GET_LOCK(', 'RELEASE_LOCK(',
            "\$old['origin'] !== 'ai'", "\$old['reviewed_at'] !== null", "\$old['reviewed_by_user_id'] !== null",
            "\$old['pdf_page'] !== null", "\$old['pdf_sha256'] !== null", '$db->rollBack();', '$db->commit();',
            'validateOriginalQuestion1403($db, $old, $original[$n])',
        ] as $marker) {
            $this->check(str_contains($operator, $marker), "Missing guarded source-only invariant: {$marker}");
            ++$checks;
        }
        $builder = (string) file_get_contents($root . '/scripts/references/prepare_residency_1403_ai_source_correction_plan.php');
        $this->check(str_contains($builder, 'SET TRANSACTION READ ONLY')
            && str_contains($builder, 'Residency1403AiSourceCitationCorrectionGuard::independentAuditMatches')
            && str_contains($builder, 'VerifiedHumanChapterPageRepairGuard::pageInMap')
            && str_contains($builder, 'PrintedBookPageEvidence::pageContainsEvidence')
            && str_contains($builder, "'database_writes'=>0"),
            'The planner must read only and bind the plan to accepted evidence and the exact PDF page.');
        ++$checks;
        preg_match_all('/^\s*(?:UPDATE|INSERT\s+INTO|DELETE\s+FROM)\s+([a-z_][a-z0-9_]*)/im', $builder, $builderWrites);
        $this->check($builderWrites[1] === [], 'The private plan builder must not write to any database table.');
        ++$checks;
        preg_match_all('/^\s*(?:UPDATE|INSERT\s+INTO|DELETE\s+FROM)\s+([a-z_][a-z0-9_]*)/im', $writer, $writerWrites);
        $this->check($writerWrites[1] === ['bank_question_sources']
            && str_contains($writer, 'page <=> :old_page')
            && str_contains($writer, 'anchor_text <=> :old_anchor')
            && str_contains($writer, "origin='ai'")
            && str_contains($writer, 'is_primary=1')
            && str_contains($writer, 'reviewed_by_user_id IS NULL')
            && str_contains($writer, '$database->inTransaction()'),
            'The MySQL writer must use a transaction, exact source-row CAS and one source table.');
        ++$checks;
        return $checks;
    }

    private function check(bool $okay, string $message): void
    {
        if (!$okay) {
            throw new RuntimeException($message);
        }
    }
}
