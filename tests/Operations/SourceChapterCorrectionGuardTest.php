<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Bank\SourceChapterCorrectionGuard;
use RuntimeException;

final class SourceChapterCorrectionGuardTest
{
    public function run(): int
    {
        $sha = str_repeat('a', 64);
        $plan = ['year'=>1405, 'subject'=>'periodontics'];
        $item = ['number'=>129, 'edition'=>'carranza-periodontology@14e',
            'chapter'=>'78', 'pdf_page'=>1618, 'pdf_sha256'=>$sha];
        $audit = ['format'=>'fanoos.classification.provenance-audit/1',
            'accepted'=>1, 'pending'=>0, 'questions'=>1, 'batch_count'=>1,
            'batches'=>[['exam_year'=>1405, 'subject'=>'periodontics',
                'question_content_identical'=>true,
                'mapped'=>['129'=>['ref'=>'carranza-periodontology@14e#ch78', 'pdf_page'=>1618]],
                'source_pdf_provenance'=>['carranza-periodontology@14e'=>['sha256'=>$sha]]]]];
        $this->check(SourceChapterCorrectionGuard::auditMatches($audit, $plan, $item), 'Valid audit rejected.');
        foreach ([
            ['chapter'=>'79'], ['pdf_page'=>1619], ['number'=>130],
            ['pdf_sha256'=>str_repeat('b', 64)], ['edition'=>'carranza-periodontology@13e'],
        ] as $change) {
            $this->check(!SourceChapterCorrectionGuard::auditMatches($audit, $plan, array_replace($item, $change)),
                'Audit accepted a different source decision.');
        }
        $wrongYear = array_replace($plan, ['year'=>1404]);
        $this->check(!SourceChapterCorrectionGuard::auditMatches($audit, $wrongYear, $item), 'Wrong year accepted.');
        $audit['batches'][0]['question_content_identical'] = false;
        $this->check(!SourceChapterCorrectionGuard::auditMatches($audit, $plan, $item), 'Modified question accepted.');
        return 8;
    }

    private function check(bool $okay, string $message): void
    {
        if (!$okay) {
            throw new RuntimeException($message);
        }
    }
}
