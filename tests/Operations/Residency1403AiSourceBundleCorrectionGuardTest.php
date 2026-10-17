<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Bank\Residency1403AiSourceBundleCorrectionGuard;
use RuntimeException;

final class Residency1403AiSourceBundleCorrectionGuardTest
{
    public function run(): int
    {
        $audit = $this->audit();
        $checks = 0;
        $this->check(Residency1403AiSourceBundleCorrectionGuard::evidenceAuditMatches($audit), 'Pinned 1403 bundle evidence rejected.');
        ++$checks;
        foreach ($audit['records'] as $record) {
            $entry = $record + ['number' => $record['number']];
            $this->check(Residency1403AiSourceBundleCorrectionGuard::entryMatchesEvidence($audit, $entry), 'Valid bundle entry rejected.');
            ++$checks;
        }

        foreach ([
            ['exam_year' => 1404],
            ['supplementary_research_sha256' => str_repeat('f', 64)],
            ['no_production_source_mutation' => false],
        ] as $change) {
            $mutated = array_replace($audit, $change);
            $this->check(!Residency1403AiSourceBundleCorrectionGuard::evidenceAuditMatches($mutated), 'Changed bundle evidence audit passed.');
            ++$checks;
        }

        $mutations = [
            static function (array $value): array { $value['pages'][0]['pdf_page']++; return $value; },
            static function (array $value): array { $value['pages'] = array_reverse($value['pages']); return $value; },
            static function (array $value): array { $value['pages'][1]['chapter'] = '9'; return $value; },
            static function (array $value): array { $value['pages'][1]['evidence'][0] = 'unverified invented citation'; return $value; },
            static function (array $value): array { $value['pdf_sha256'] = str_repeat('f', 64); return $value; },
            static function (array $value): array { $value['subject'] = 'oral-surgery'; return $value; },
        ];
        foreach ($mutations as $mutate) {
            $entry = $audit['records'][0] + ['number' => 34];
            $entry = $mutate($entry);
            $this->check(!Residency1403AiSourceBundleCorrectionGuard::entryMatchesEvidence($audit, $entry), 'Mutated source bundle entry passed.');
            ++$checks;
        }
        $unsupported = array_replace($audit['records'][0], ['number' => 35]);
        $this->check(!Residency1403AiSourceBundleCorrectionGuard::entryMatchesEvidence($audit, $unsupported), 'Unsupported question passed bundle guard.');
        ++$checks;

        return $checks;
    }

    /** @return array<string,mixed> */
    private function audit(): array
    {
        $records = [];
        foreach ([34, 119] as $number) {
            $spec = Residency1403AiSourceBundleCorrectionGuard::spec($number);
            if (!is_array($spec)) {
                throw new RuntimeException('Pinned bundle fixture missing.');
            }
            $pages = [];
            foreach ($spec['pages'] as $i => $page) {
                $pages[] = $page + ['evidence' => [$i === 0
                    ? 'a sufficiently long direct original PDF evidence quotation'
                    : 'a second sufficiently long direct original PDF quotation']];
            }
            $records[] = [
                'number' => $number,
                'subject' => $spec['subject'],
                'edition' => $spec['edition'],
                'pdf_sha256' => $spec['pdf_sha256'],
                'primary_research_path' => $spec['primary_research_path'],
                'primary_research_sha256' => $spec['primary_research_sha256'],
                'decisions_path' => $spec['decisions_path'],
                'decisions_sha256' => $spec['decisions_sha256'],
                'validated_study_path' => $spec['validated_study_path'],
                'validated_study_sha256' => $spec['validated_study_sha256'],
                'primary_research_evidence' => 'an independently audited original page quote with sufficient length',
                'scientific_disambiguation' => 'Two verified original pages support the same year-specific question location.',
                'pages' => $pages,
            ];
        }
        return [
            'format' => 'fanoos.residency1403.ai-source-bundle-original-pdf-evidence/1',
            'research_only' => true,
            'no_production_source_mutation' => true,
            'exam_year' => 1403,
            'supplementary_research_path' => Residency1403AiSourceBundleCorrectionGuard::SUPPLEMENTARY_RESEARCH_PATH,
            'supplementary_research_sha256' => Residency1403AiSourceBundleCorrectionGuard::SUPPLEMENTARY_RESEARCH_SHA256,
            'records' => $records,
        ];
    }

    private function check(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
