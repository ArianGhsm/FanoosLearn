<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Bank\SourceOnlyPublisher;
use RuntimeException;

final class SourceOnlyReadbackTest
{
    public function run(): int
    {
        $questions = [[
            'number' => 7,
            'question_key' => 'residency-1398-1-007',
            'status' => 'published',
            'stem' => 'Private test stem; only its fingerprint may escape.',
            'choices' => ['one', 'two'],
            'answer' => ['choice' => 2, 'also_correct' => [], 'status' => 'final'],
            'live_fingerprint_sha256' => hash('sha256', 'stem-image-and-choice-image-fingerprint'),
        ]];
        $source = [
            'number' => 7,
            'source_id' => null,
            'ref' => 'test-reference@2e#ch04',
            'page' => 'pdf 120',
            'printed_page' => '102',
            'pdf_page' => 120,
            'pdf_sha256' => str_repeat('a', 64),
            'anchor_sha256' => hash('sha256', 'private page evidence'),
            'reference_year' => 1397,
            'classification_basis' => 'nearest_same_lineage_proxy',
            'classification_note_sha256' => hash('sha256', 'private proxy explanation'),
            'origin' => 'ai',
            'is_primary' => true,
            'confidence_source' => '0.9500',
            'confidence_node' => '0.9500',
            'confidence_page' => '0.9500',
            'official_scope_status' => 'in_scope',
            'official_scope_note_sha256' => null,
            'scope_unverified' => false,
        ];
        $persistedSource = $source;
        $persistedSource['source_id'] = '0199dd00-1234-7000-8000-000000000001';

        $valid = SourceOnlyPublisher::compareReadback($questions, $questions, [$source], [$persistedSource]);
        $this->check($valid['verified'] === true, 'Matching post-commit source and question fingerprints did not verify.');
        $this->check($valid['source_rows'] === 1 && $valid['expected_source_rows'] === 1,
            'Verified source-row counts were not reported.');
        $this->check(preg_match('/^[a-f0-9]{64}$/D', $valid['source_fields_sha256'] ?? '') === 1
            && preg_match('/^[a-f0-9]{64}$/D', $valid['question_key_fingerprint_sha256'] ?? '') === 1,
            'Readback hashes were not included in the verification summary.');
        $this->check(!str_contains(json_encode($valid, JSON_THROW_ON_ERROR), 'Private test stem')
            && !str_contains(json_encode($valid, JSON_THROW_ON_ERROR), 'private proxy explanation'),
            'Readback summary leaked question or proxy-note text.');
        $checks = 4;

        foreach ([
            ['reference_year' => 1398],
            ['classification_basis' => 'official_exam_year'],
            ['classification_note_sha256' => hash('sha256', 'different note')],
            ['pdf_page' => 121],
            ['pdf_sha256' => str_repeat('b', 64)],
            ['ref' => 'other-reference@2e#ch04'],
        ] as $change) {
            $badSource = array_replace($persistedSource, $change);
            $result = SourceOnlyPublisher::compareReadback($questions, $questions, [$source], [$badSource]);
            $this->check($result['verified'] === false
                && in_array('source_provenance_rows', $result['mismatches'], true),
                'Mismatched source provenance or same-row page metadata was accepted.');
            $changedField = array_key_first($change);
            $this->check(in_array($changedField, $result['source_field_mismatches'][0]['fields'] ?? [], true),
                'The sanitized source mismatch did not name the changed field.');
            $checks += 2;
        }

        $badQuestion = $questions;
        $badQuestion[0]['question_key'] = 'residency-1398-1-008';
        $result = SourceOnlyPublisher::compareReadback($questions, $badQuestion, [$source], [$persistedSource]);
        $this->check($result['verified'] === false
            && in_array('question_key_content_fingerprint', $result['mismatches'], true),
            'Changed question key/content fingerprint was accepted.');
        ++$checks;

        $badQuestionImage = $questions;
        $badQuestionImage[0]['live_fingerprint_sha256'] = hash('sha256', 'changed-image-reference');
        $result = SourceOnlyPublisher::compareReadback($questions, $badQuestionImage, [$source], [$persistedSource]);
        $this->check($result['verified'] === false
            && in_array('question_key_content_fingerprint', $result['mismatches'], true),
            'Changed stem/choice image reference fingerprint was accepted.');
        ++$checks;

        $result = SourceOnlyPublisher::compareReadback($questions, $questions, [$source], []);
        $this->check($result['verified'] === false
            && in_array('source_row_count', $result['mismatches'], true),
            'Missing committed source row was accepted.');
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
