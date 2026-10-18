<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Bank\VerifiedAiOutsideScopeSourceInsertGuard;
use RuntimeException;

final class VerifiedAiOutsideScopeSourceInsertGuardTest
{
    public function run(): int
    {
        $this->check(
            VerifiedAiOutsideScopeSourceInsertGuard::hasExplicitOutsideScopeNote(
                'فصل ۲۶ خارج از محدوده رسمی ۱ تا ۲۵ است.',
            ),
            'Explicit Persian outside-scope note was rejected.',
        );
        $checks = 1;
        foreach ([null, '', 'outside syllabus', 'خارج از محدوده'] as $note) {
            $this->check(
                !VerifiedAiOutsideScopeSourceInsertGuard::hasExplicitOutsideScopeNote($note),
                'Missing or underspecified outside-scope note was accepted.',
            );
            ++$checks;
        }

        $scope = array_map(static fn (int $number): array => ['number'=>(string) $number], range(1, 25));
        $this->check(
            VerifiedAiOutsideScopeSourceInsertGuard::chapterIsEntirelyOutsideScope($scope, '26'),
            'Unlisted whole chapter was not recognized as outside scope.',
        );
        ++$checks;
        $this->check(
            !VerifiedAiOutsideScopeSourceInsertGuard::chapterIsEntirelyOutsideScope($scope, '25'),
            'Listed official chapter was accepted as outside scope.',
        );
        ++$checks;
        $this->check(
            !VerifiedAiOutsideScopeSourceInsertGuard::chapterIsEntirelyOutsideScope(
                [['number'=>'26','partial'=>'selected pages']], '26',
            ),
            'Partially listed chapter was accepted by the whole-chapter outside-scope path.',
        );
        ++$checks;
        foreach ([[], '{broken', null] as $invalidScope) {
            $this->check(
                !VerifiedAiOutsideScopeSourceInsertGuard::chapterIsEntirelyOutsideScope($invalidScope, '26'),
                'Missing or malformed official scope was accepted.',
            );
            ++$checks;
        }

        $sha = str_repeat('a', 64);
        $item = [
            'number'=>192,
            'edition'=>'white-pharoah-radiology@8e',
            'chapter'=>'26',
            'pdf_page'=>1422,
            'pdf_sha256'=>$sha,
            'confidence'=>0.90,
        ];
        $batch = [
            'batch'=>'1401:oral-radiology:open35-oral-radiology',
            'question_content_identical'=>true,
            'source_pdf_provenance'=>[
                $item['edition']=>['source'=>'verified-server-pdf','sha256'=>$sha],
            ],
            'mapped'=>[
                '192'=>[
                    'ref'=>'white-pharoah-radiology@8e#ch26',
                    'pdf_page'=>1422,
                    'confidence_source'=>0.90,
                    'confidence_node'=>0.90,
                    'confidence_page'=>0.90,
                ],
            ],
        ];
        $audit = [
            'format'=>'fanoos.classification.provenance-audit/1',
            'research_only'=>true,
            'batches'=>[$batch],
        ];
        $this->check(
            VerifiedAiOutsideScopeSourceInsertGuard::auditMatches(
                $audit, $batch['batch'], $item,
            ),
            'Matching independent exact-page audit was rejected.',
        );
        ++$checks;
        foreach ([
            ['pdf_page'=>1423],
            ['pdf_sha256'=>str_repeat('b', 64)],
            ['confidence'=>0.80],
            ['chapter'=>'27'],
        ] as $change) {
            $this->check(
                !VerifiedAiOutsideScopeSourceInsertGuard::auditMatches(
                    $audit, $batch['batch'], array_replace($item, $change),
                ),
                'Changed question/page/PDF/confidence audit binding was accepted.',
            );
            ++$checks;
        }
        $this->check(
            !VerifiedAiOutsideScopeSourceInsertGuard::auditMatches($audit, '1401:oral-radiology:other', $item),
            'Audit for another batch was accepted.',
        );
        ++$checks;
        $badAudit = $audit;
        $badAudit['research_only'] = false;
        $this->check(
            !VerifiedAiOutsideScopeSourceInsertGuard::auditMatches($badAudit, $batch['batch'], $item),
            'Non-research or untrusted audit was accepted.',
        );
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
