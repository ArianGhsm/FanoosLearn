<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Only INSERTs new source/chapter links for matching, published,
 * currently source-free questions, plus the exact voided/draft 1398 Q97
 * exception. No UPDATE, DELETE or publication writes. Updated-reference
 * proxies are limited to separately authorized 1397/1406 and
 * 1398/Hupp7/1406 cases, visibly labelled outside exam-year scope. The
 * same-lineage proxies are limited to residency 1396–1398 and to
 * national/board/promotion subjects with no exact-year official list; each
 * stores its actual reference year and explicit proxy note on the source row.
 * An entire batch commits together or rolls back together.
 */
final class SourceOnlyPublisher
{
    public static function run(
        PDO $db, string $workspace, int $year, string $subject,
        array $study, array $validated, array $decisions, bool $apply,
        ?array &$readbackEvidence = null,
    ): int {
        $readbackEvidence = [];
        $examType = $study['exam_type'] ?? null;
        $round = $study['round'] ?? null;
        $supportedExamTypes = ['residency', 'national', 'board', 'promotion'];
        $isResidency = $examType === 'residency';
        $policyYear = $isResidency ? $year : 0;
        $maxRound = in_array($examType, ['board', 'promotion'], true) ? 99 : 9;
        $retrospective = $isResidency && $year >= 1300 && $year < 1396;
        $nearestSameLineageProxyYear = $isResidency && $year >= 1396 && $year <= 1398;
        $nonResidencyProxyType = !$isResidency
            && in_array($examType, ['national', 'board', 'promotion'], true);
        $updated1397Subjects = ['community-dentistry', 'dental-materials', 'endodontics',
            'operative-dentistry', 'oral-medicine', 'oral-pathology', 'oral-radiology',
            'oral-surgery', 'orthodontics', 'pediatric-dentistry', 'periodontics',
            'prosthodontics'];
        if (!in_array($examType, $supportedExamTypes, true)
            || !is_int($round) || $round < 1 || $round > $maxRound
            || ($isResidency && $round !== 1)
            || $year < ($isResidency ? 1300 : 1350)
            || (($isResidency && $year < 1399) && !$retrospective && !$nearestSameLineageProxyYear
                && !(($policyYear === 1397 && in_array($subject, $updated1397Subjects, true))
                    || ($policyYear === 1398 && in_array($subject, ['endodontics', 'periodontics', 'community-dentistry', 'prosthodontics', 'operative-dentistry', 'pediatric-dentistry', 'oral-radiology', 'oral-surgery'], true)))) || $year > 1500
            || ($study['format'] ?? '') !== 'fanoos.classification.study-only/1'
            || ($validated['format'] ?? '') !== ($study['format'] ?? '')
            || ($validated['exam_type'] ?? null) !== $examType
            || ($study['year'] ?? 0) !== $year || ($validated['year'] ?? 0) !== $year
            || ($validated['round'] ?? null) !== $round) {
            throw new RuntimeException('Scope, year or study-only format is not eligible.');
        }
        $original = [];
        foreach ($study['questions'] ?? [] as $question) {
            $n = $question['number'] ?? null;
            if (!is_int($n) || isset($original[$n]) || ($question['subject'] ?? '') !== $subject
                || isset($question['sources'])) {
                throw new RuntimeException('Invalid or duplicate unsourced study question.');
            }
            $original[$n] = $question;
        }
        $wanted = [];
        $batchClassificationBasis = null;
        foreach ($decisions as $decision) {
            $n = $decision['number'] ?? null;
            if (!is_int($n) || isset($wanted[$n]) || !isset($original[$n])
                || !isset($decision['edition'], $decision['chapter'], $decision['page'],
                           $decision['evidence'], $decision['confidence'])
                || isset($decision['override_human'], $decision['none'])) {
                throw new RuntimeException('Unsupported source decision or human override.');
            }
            if ($retrospective) {
                $proxyYear = $decision['reference_year'] ?? null;
                if (!is_int($proxyYear) || $proxyYear < 1397 || $proxyYear > 1500
                    || ($decision['classification_basis'] ?? '') !== 'retrospective_proxy_1397_plus') {
                    throw new RuntimeException('Pre-1396 source must declare an actual 1397+ official proxy reference year and retrospective basis.');
                }
            }
            $sameLineageProxy = ($nearestSameLineageProxyYear || $nonResidencyProxyType)
                && ($decision['classification_basis'] ?? null) === 'same_lineage_proxy';
            if ($sameLineageProxy) {
                $proxyYear = $decision['reference_year'] ?? null;
                $proxyNote = $decision['proxy_note'] ?? null;
                $asciiProxyNote = is_string($proxyNote)
                    ? self::asciiDigits($proxyNote) : '';
                if (!is_int($proxyYear) || $proxyYear < ($isResidency ? 1390 : 1350) || $proxyYear > 1500
                    || $proxyYear === $year
                    || !is_string($proxyNote) || mb_strlen(trim($proxyNote)) < 45
                    || mb_strlen(trim($proxyNote)) > 300
                    || !str_contains($proxyNote, 'پروکسی')
                    || !str_contains($asciiProxyNote, (string) $year)
                    || !str_contains($asciiProxyNote, (string) $proxyYear)
                    || !str_contains($proxyNote, 'رسمی')
                    || !(str_contains($proxyNote, 'تأیید نشده')
                        || str_contains($proxyNote, 'رسمی نیست')
                        || str_contains($proxyNote, 'رسمی نبوده')
                        || str_contains($proxyNote, 'رسمی اعلام نشده'))) {
                    throw new RuntimeException('Same-lineage proxy must name the actual reference year, exam year and unverified official status.');
                }
            }
            $referenceSubject = (string) ($decision['reference_subject'] ?? $subject);
            $updated1397Proxy = $policyYear === 1397
                && in_array($subject, $updated1397Subjects, true)
                && ($decision['reference_year'] ?? null) === 1406
                && ($decision['classification_basis'] ?? null) === 'updated_reference_proxy';
            $crossSubject1397HuppQ95 = $updated1397Proxy
                && $subject === 'endodontics' && $n === 95
                && $referenceSubject === 'oral-surgery'
                && ($decision['edition'] ?? null) === 'hupp-oral-surgery@7e';
            $exact1397EndodonticsQ88 = $policyYear === 1397 && $subject === 'endodontics'
                && $n === 88 && $referenceSubject === $subject
                && ($decision['edition'] ?? null) === 'torabinejad-endodontics@5e'
                && (string) ($decision['chapter'] ?? '') === '13'
                && ($decision['page'] ?? null) === 235
                && !isset($decision['reference_year'])
                && !isset($decision['classification_basis'])
                && !isset($decision['updated_reference_note']);
            if ($referenceSubject !== $subject && !$crossSubject1397HuppQ95) {
                throw new RuntimeException('Cross-subject reference is permitted only for the exact 1397 endodontics Q95 oral-surgery proxy.');
            }
            $updated1398HuppProxy = $policyYear === 1398 && $subject === 'oral-surgery'
                && ($decision['edition'] ?? null) === 'hupp-oral-surgery@7e'
                && ($decision['reference_year'] ?? null) === 1406
                && ($decision['classification_basis'] ?? null) === 'updated_reference_proxy';
            $updatedReferenceProxy = $updated1397Proxy || $updated1398HuppProxy;
            $decisionBasis = $updatedReferenceProxy ? 'updated_reference_proxy'
                : ($sameLineageProxy ? 'same_lineage_proxy'
                    : ($retrospective ? 'retrospective_proxy_1397_plus' : 'official_exam_year'));
            if ($batchClassificationBasis !== null && $batchClassificationBasis !== $decisionBasis) {
                throw new RuntimeException('A source-only batch cannot mix exact-year and proxy classification bases; split it into separately audited batches.');
            }
            $batchClassificationBasis = $decisionBasis;
            if ($updatedReferenceProxy) {
                $proxyNote = $decision['updated_reference_note'] ?? null;
                if (!is_string($proxyNote) || mb_strlen(trim($proxyNote)) < 60
                    || mb_strlen(trim($proxyNote)) > 300
                    || !str_contains($proxyNote, 'خارج از محدوده')
                    || !str_contains($proxyNote, 'فهرست رسمی')
                    || !str_contains($proxyNote, $policyYear === 1397 ? '۱۳۹۷' : '۱۳۹۸')
                    || !str_contains($proxyNote, '۱۴۰۶')) {
                    throw new RuntimeException('Updated-reference proxy must name both years and disclose that it is outside the exam-year official reference list.');
                }
                if ($policyYear === 1397 && !$updated1397Proxy) {
                    throw new RuntimeException('1397 sources require the owner-approved 1406 updated-reference basis.');
                }
                if ($crossSubject1397HuppQ95 && !str_contains($proxyNote, 'جراحی دهان')) {
                    throw new RuntimeException('1397 Q95 must disclose its 1406 oral-surgery cross-subject reference in the source note.');
                }
            } elseif ($policyYear === 1397 && !$exact1397EndodonticsQ88 && !$sameLineageProxy) {
                throw new RuntimeException('1397 source batches require the explicit 1406 updated-reference proxy metadata.');
            } elseif (!$retrospective && !$sameLineageProxy && (isset($decision['reference_year'])
                    || isset($decision['classification_basis'])
                    || isset($decision['updated_reference_note'])
                    || isset($decision['proxy_note']))) {
                throw new RuntimeException('A normal exact-year source cannot claim retrospective basis.');
            }
            // Official 1398 source announcement independently identifies exact Torabinejad 5e
            // for endodontics, original Carranza 12e for periodontics, and the national
            // oral-health 1394 text (chapters 1–16) for community dentistry.
            // The complete 1398 notice additionally specifies Shillingburg Fixed 4e,
            // McCracken RPD 12e and Zarb Edentulous 13e for prosthodontics.
            // Their specific chapter exclusions are enforced below by the
            // year-specific official reference-validity scope lookup.
            // Every other 1398 subject remains blocked by the year gate.
            if ($policyYear === 1398 && !$sameLineageProxy && $subject === 'endodontics'
                && $decision['edition'] !== 'torabinejad-endodontics@5e') {
                throw new RuntimeException('1398 endodontics requires exact announced Torabinejad 5e.');
            }
            if ($policyYear === 1398 && !$sameLineageProxy && $subject === 'periodontics'
                && $decision['edition'] !== 'carranza-periodontology@12e') {
                throw new RuntimeException('1398 periodontics requires exact announced Carranza 12e.');
            }
            if ($policyYear === 1398 && !$sameLineageProxy && $subject === 'community-dentistry'
                && $decision['edition'] !== 'national-oral-health@1394') {
                throw new RuntimeException('1398 community dentistry requires the exact national oral-health 1394 edition.');
            }
            if ($policyYear === 1398 && !$sameLineageProxy && $subject === 'prosthodontics'
                && !in_array((string) $decision['edition'], [
                    'shillingburg-fixed@4e',
                    'mccracken-rpd@12e',
                    'zarb-edentulous@13e',
                ], true)) {
                throw new RuntimeException('1398 prosthodontics requires an exact officially announced edition.');
            }
            if ($policyYear === 1398 && $subject === 'prosthodontics' && $n === 97
                && ($decision['edition'] !== 'shillingburg-fixed@4e'
                    || (string) $decision['chapter'] !== '22'
                    || $decision['page'] !== 430)) {
                throw new RuntimeException('1398 voided prosthodontic Q97 permits only the audited Shillingburg 4e chapter22 PDF430 source.');
            }
            if ($policyYear === 1398 && !$sameLineageProxy && $subject === 'operative-dentistry' && $n !== 188
                && ($n !== 175
                    || $decision['edition'] !== 'summit-operative@4e'
                    || (string) $decision['chapter'] !== '6'
                    || $decision['page'] !== 294)) {
                throw new RuntimeException('1398 operative source gate permits only audited Summit 4e Q175 chapter 6 exact PDF page 294.');
            }
            if ($policyYear === 1398 && !$sameLineageProxy && $subject === 'operative-dentistry' && $n === 188
                && ($decision['edition'] !== 'summit-operative@4e'
                    || (string) $decision['chapter'] !== '11'
                    || $decision['page'] !== 610)) {
                throw new RuntimeException('1398 operative Q188 permits only the audited Summit 4e chapter11 PDF610 source.');
            }
            if ($policyYear === 1398 && !$sameLineageProxy && $subject === 'oral-surgery') {
                $expectedSurgerySource = $updatedReferenceProxy
                    ? match ($n) {
                        110 => ['hupp-oral-surgery@7e', '11', 203],
                        111 => ['hupp-oral-surgery@7e', '4', 60],
                        112 => ['hupp-oral-surgery@7e', '3', 56],
                        113 => ['hupp-oral-surgery@7e', '15', 298],
                        114 => ['hupp-oral-surgery@7e', '19', 417],
                        115 => ['hupp-oral-surgery@7e', '17', 373],
                        116 => ['hupp-oral-surgery@7e', '23', 502],
                        117 => ['hupp-oral-surgery@7e', '25', 547],
                        118 => ['hupp-oral-surgery@7e', '24', 526],
                        119 => ['hupp-oral-surgery@7e', '21', 452],
                        120 => ['hupp-oral-surgery@7e', '26', 574],
                        121 => ['hupp-oral-surgery@7e', '31', 672],
                        122 => ['hupp-oral-surgery@7e', '2', 43],
                        129 => ['hupp-oral-surgery@7e', '13', 237],
                        default => null,
                    }
                    : match ($n) {
                        123 => ['malamed-medical-emergencies@7e', '3', 110],
                        124 => ['malamed-medical-emergencies@7e', '11', 211],
                        default => null,
                    };
                if (!is_array($expectedSurgerySource)
                    || $decision['edition'] !== $expectedSurgerySource[0]
                    || (string) $decision['chapter'] !== $expectedSurgerySource[1]
                    || $decision['page'] !== $expectedSurgerySource[2]) {
                    throw new RuntimeException('1398 oral-surgery source gate rejected the exact official Malamed or owner-authorized 1406 Hupp proxy page.');
                }
            }
            if ($policyYear === 1398 && !$sameLineageProxy && in_array($subject, ['pediatric-dentistry', 'oral-radiology'], true)) {
                $specific = $subject === 'pediatric-dentistry'
                    ? [
                        152 => ['mcdonald-avery-pediatric@10e', '13', 246],
                        159 => ['mcdonald-avery-pediatric@10e', '19', 360],
                        164 => ['mcdonald-avery-pediatric@10e', '11', 215],
                        168 => ['mcdonald-avery-pediatric@10e', '8', 159],
                        150 => ['mcdonald-avery-pediatric@10e', '16', 303],
                        151 => ['mcdonald-avery-pediatric@10e', '19', 366],
                        153 => ['mcdonald-avery-pediatric@10e', '7', 137],
                        154 => ['mcdonald-avery-pediatric@10e', '2', 28],
                        155 => ['mcdonald-avery-pediatric@10e', '27', 605],
                        156 => ['mcdonald-avery-pediatric@10e', '26', 566],
                        157 => ['mcdonald-avery-pediatric@10e', '25', 539],
                        158 => ['mcdonald-avery-pediatric@10e', '25', 531],
                        160 => ['mcdonald-avery-pediatric@10e', '16', 308],
                        161 => ['mcdonald-avery-pediatric@10e', '15', 291],
                        162 => ['mcdonald-avery-pediatric@10e', '13', 245],
                        163 => ['mcdonald-avery-pediatric@10e', '13', 231],
                        165 => ['mcdonald-avery-pediatric@10e', '10', 189],
                        166 => ['mcdonald-avery-pediatric@10e', '3', 54],
                        167 => ['mcdonald-avery-pediatric@10e', '9', 179],
                        169 => ['mcdonald-avery-pediatric@10e', '4', 93],
                    ]
                    : [
                        211 => ['white-pharoah-radiology@7e', '1', 27],
                        212 => ['white-pharoah-radiology@7e', '3', 50],
                        213 => ['white-pharoah-radiology@7e', '2', 38],
                        214 => ['white-pharoah-radiology@7e', '6', 103],
                        215 => ['white-pharoah-radiology@7e', '4', 61],
                        216 => ['white-pharoah-radiology@7e', '7', 109],
                        217 => ['white-pharoah-radiology@7e', '10', 189],
                        218 => ['white-pharoah-radiology@7e', '10', 199],
                        219 => ['white-pharoah-radiology@7e', '14', 260],
                        220 => ['white-pharoah-radiology@7e', '20', 331],
                        222 => ['white-pharoah-radiology@7e', '23', 425],
                        223 => ['white-pharoah-radiology@7e', '25', 486],
                        224 => ['white-pharoah-radiology@7e', '22', 386],
                        225 => ['white-pharoah-radiology@7e', '24', 457],
                        226 => ['white-pharoah-radiology@7e', '21', 355],
                        227 => ['white-pharoah-radiology@7e', '20', 333],
                        228 => ['white-pharoah-radiology@7e', '25', 473],
                        229 => ['white-pharoah-radiology@7e', '23', 437],
                        230 => ['white-pharoah-radiology@7e', '24', 463],
                    ];
                $expectedSource = $specific[$n] ?? null;
                if (!is_array($expectedSource)
                    || $decision['edition'] !== $expectedSource[0]
                    || (string) $decision['chapter'] !== $expectedSource[1]
                    || $decision['page'] !== $expectedSource[2]) {
                    throw new RuntimeException('1398 pediatric/radiology exact approved original PDF-page source gate refused.');
                }
            }
            if ($policyYear === 1398 && !$sameLineageProxy && $subject === 'periodontics' && $n === 134
                && ($decision['edition'] !== 'carranza-periodontology@12e'
                    || (string) $decision['chapter'] !== '20'
                    || $decision['page'] !== 664)) {
                throw new RuntimeException('1398 Carranza Q134 requires exact chapter20 original PDF664.');
            }
            $wanted[$n] = $decision;
        }
        $mapped = [];
        $seen = [];
        foreach ($validated['questions'] ?? [] as $question) {
            $n = $question['number'] ?? null;
            if (!is_int($n) || isset($seen[$n]) || !isset($original[$n])) {
                throw new RuntimeException('Question set mismatch.');
            }
            $seen[$n] = true;
            $sources = $question['sources'] ?? [];
            unset($question['sources']);
            if ($question !== $original[$n]) {
                throw new RuntimeException('A stem, choice or answer changed in the study.');
            }
            if (!isset($wanted[$n])) {
                if ($sources !== []) {
                    throw new RuntimeException('An undecided question has acquired a source.');
                }
                continue;
            }
            $d = $wanted[$n];
            $referenceSubject = (string) ($d['reference_subject'] ?? $subject);
            $updatedReferenceProxy = $policyYear === 1397
                && in_array($subject, $updated1397Subjects, true)
                && ($d['reference_year'] ?? null) === 1406
                && ($d['classification_basis'] ?? null) === 'updated_reference_proxy';
            $updatedReferenceProxy = $updatedReferenceProxy || ($policyYear === 1398
                && $subject === 'oral-surgery'
                && ($d['edition'] ?? null) === 'hupp-oral-surgery@7e'
                && ($d['reference_year'] ?? null) === 1406
                && ($d['classification_basis'] ?? null) === 'updated_reference_proxy');
            if (!is_array($sources) || count($sources) !== 1 || !is_array($sources[0])) {
                throw new RuntimeException('One source per accepted question is required.');
            }
            $s = $sources[0];
            $edition = (string) $d['edition'];
            $chapterNumber = (string) $d['chapter'];
            if (!preg_match('/^([1-9][0-9]?)(?:\.([1-9]))?$/D', $chapterNumber, $chapterMatch)) {
                throw new RuntimeException('Unsupported official chapter or section node number.');
            }
            $chapter = 'ch' . str_pad($chapterMatch[1], 2, '0', STR_PAD_LEFT)
                . (isset($chapterMatch[2]) ? '.' . $chapterMatch[2] : '');
            $pageLabel = (string) ($s['page'] ?? '');
            $pdfPage = $d['page'];
            $sourcePdfPage = $s['pdf_page'] ?? null;
            $sourcePdfHash = $s['pdf_sha256'] ?? null;
            if (!is_int($pdfPage) || $pdfPage < 1
                || !is_int($sourcePdfPage) || $sourcePdfPage !== $pdfPage
                || !is_string($sourcePdfHash)
                || preg_match('/^[a-fA-F0-9]{64}$/D', $sourcePdfHash) !== 1) {
                throw new RuntimeException('A chapter source requires its exact pdf_page and verified PDF SHA-256.');
            }
            $validPage = in_array($pageLabel, [(string) $pdfPage, 'pdf ' . $pdfPage], true);
            if (!$validPage) {
                $validPage = PrintedBookPageEvidence::corroborates(
                    $edition, $pdfPage, $pageLabel
                );
            }
            if (!preg_match('/^([a-z0-9-]+)@([a-z0-9-]+)$/D', $edition, $m)
                || ($s['ref'] ?? '') !== $edition . '#' . $chapter
                || !$validPage
                || ($s['anchor'] ?? '') !== $d['evidence']
                || mb_strlen((string) ($s['anchor'] ?? '')) < 15
                || mb_strlen((string) ($s['anchor'] ?? '')) > 600
                || ($s['origin'] ?? '') !== 'ai' || ($s['primary'] ?? false) !== true) {
                throw new RuntimeException('Mapped source does not equal exact evidence decision.');
            }
            if ($updatedReferenceProxy
                && (($s['official_scope_status'] ?? null) !== 'outside_scope_exam_question'
                    || ($s['official_scope_note'] ?? null) !== ($d['updated_reference_note'] ?? null)
                    || ($s['classification_basis'] ?? null) !== 'updated_reference_proxy'
                    || ($s['reference_year'] ?? null) !== 1406
                    || ($s['classification_note'] ?? null) !== ($d['updated_reference_note'] ?? null)
                    || ($s['scope_unverified'] ?? null) !== false)) {
                throw new RuntimeException('Updated-reference proxy citation must preserve the explicit outside-exam-year label and exact 1406 provenance.');
            }
            $outsideScopeDecision = $nonResidencyProxyType
                && isset($d['outside_scope_note']);
            if ($sameLineageProxy
                && (($s['classification_basis'] ?? null) !== 'same_lineage_proxy'
                    || ($s['reference_year'] ?? null) !== ($d['reference_year'] ?? null)
                    || ($s['classification_note'] ?? null) !== ($d['proxy_note'] ?? null)
                    || ($s['official_scope_status'] ?? null) !== ($outsideScopeDecision
                        ? 'outside_scope_exam_question' : 'in_scope')
                    || ($s['official_scope_note'] ?? null) !== ($outsideScopeDecision
                        ? ($d['outside_scope_note'] ?? null) : null)
                    || ($outsideScopeDecision && !self::validNonResidencyOutsideScopeNote(
                        $d['outside_scope_note'], (string) $examType, $year))
                    || ($s['scope_unverified'] ?? null) !== false)) {
                throw new RuntimeException('Same-lineage proxy must retain its actual reference year and visible proxy note.');
            }
            if ($retrospective
                && (($s['classification_basis'] ?? null) !== 'retrospective_proxy_1397_plus'
                    || ($s['reference_year'] ?? null) !== ($d['reference_year'] ?? null)
                    || !is_int($d['reference_year'] ?? null)
                    || ($s['classification_note'] ?? null)
                        !== self::retrospectiveNote($d['reference_year'], (int) $year))) {
                throw new RuntimeException('Pre-1396 retrospective source must retain its actual proxy year and visible label.');
            }
            if (!$updatedReferenceProxy && !$sameLineageProxy && !$retrospective
                && (($s['classification_basis'] ?? null) !== 'official_exam_year'
                    || ($s['reference_year'] ?? null) !== $year
                    || ($s['classification_note'] ?? null) !== null)) {
                throw new RuntimeException('Exact-year source must state its actual exam-year reference basis.');
            }
            if ($nonResidencyProxyType && !$sameLineageProxy) {
                $outsideScopeDecision = isset($d['outside_scope_note']);
                if (($s['official_scope_status'] ?? 'in_scope') !== ($outsideScopeDecision
                        ? 'outside_scope_exam_question' : 'in_scope')
                    || ($s['official_scope_note'] ?? null) !== ($outsideScopeDecision
                        ? ($d['outside_scope_note'] ?? null) : null)
                    || ($outsideScopeDecision && !self::validNonResidencyOutsideScopeNote(
                        $d['outside_scope_note'], (string) $examType, $year))) {
                    throw new RuntimeException('Non-residency outside-scope status requires its exact Persian year-specific note.');
                }
            }
            $conf = (float) $d['confidence'];
            if ($conf < .85 || $conf > 1.0) {
                throw new RuntimeException('Unreviewed low-confidence decision.');
            }
            foreach (['source', 'node', 'page'] as $kind) {
                if (abs((float) ($s['confidence'][$kind] ?? 0) - $conf) > .0005) {
                    throw new RuntimeException('Source confidence mismatch.');
                }
            }
            $referenceYear = ($retrospective || $updatedReferenceProxy || $sameLineageProxy)
                ? (int) $d['reference_year'] : $year;
            $classificationBasis = $updatedReferenceProxy ? 'updated_reference_proxy'
                : ($sameLineageProxy ? 'same_lineage_proxy'
                    : ($retrospective ? 'retrospective_proxy_1397_plus' : 'official_exam_year'));
            $classificationNote = $updatedReferenceProxy
                ? (string) $d['updated_reference_note']
                : ($sameLineageProxy ? (string) $d['proxy_note']
                    : ($retrospective
                        ? self::retrospectiveNote($referenceYear, (int) $year)
                        : null));
            $mapped[$n] = ['ref' => $m[1], 'edition' => $m[2],
                'node' => $chapter, 'chapter' => (string) $d['chapter'],
                'page' => (string) $s['page'],
                'pdf_page' => $sourcePdfPage,
                'pdf_sha256' => strtolower($sourcePdfHash),
                'printed_page' => isset($s['printed_page']) ? (string) $s['printed_page'] : null,
                'anchor' => $s['anchor'], 'conf' => $conf,
                'reference_year' => $referenceYear,
                'classification_basis' => $classificationBasis,
                'classification_note' => $classificationNote,
                'reference_subject' => $referenceSubject,
                'official_scope_status' => $updatedReferenceProxy
                    ? 'outside_scope_exam_question'
                    : ($nonResidencyProxyType
                        ? (string) ($s['official_scope_status'] ?? 'in_scope') : 'in_scope'),
                'official_scope_note' => $updatedReferenceProxy
                    ? (string) $d['updated_reference_note']
                    : ($nonResidencyProxyType ? ($s['official_scope_note'] ?? null) : null),
                'scope_unverified' => false];
        }
        if (count($seen) !== count($original) || count($mapped) !== count($wanted)
            || $mapped === []) {
            throw new RuntimeException('Study completeness or accepted count mismatch.');
        }
        $get = $db->prepare(<<<'SQL'
SELECT q.id,q.question_key,q.stem,q.stem_image,q.status FROM bank_questions q
JOIN bank_exam_sittings si ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=q.workspace_id
JOIN bank_subjects sb ON sb.id=q.subject_id AND sb.workspace_id=q.workspace_id
WHERE q.workspace_id=:ws AND et.type_key=:type AND si.exam_year=:yr
AND si.exam_round=:round AND sb.subject_key=:sub AND q.number_in_sitting=:num FOR UPDATE
SQL);
        $choices = $db->prepare('SELECT text,image FROM bank_question_choices WHERE workspace_id=:ws AND question_id=:qid ORDER BY position');
        $answer = $db->prepare('SELECT choice_position,also_correct_positions,status FROM bank_official_answers WHERE workspace_id=:ws AND question_id=:qid ORDER BY recorded_at DESC,id DESC LIMIT 1');
        $existing = $db->prepare('SELECT id FROM bank_question_sources WHERE workspace_id=:ws AND question_id=:qid');
        $nodes = $db->prepare(<<<'SQL'
SELECT e.id AS edition_id,n.id AS node_id,n.kind,v.scope_chapters
FROM bank_references r
JOIN bank_reference_editions e ON e.reference_id=r.id AND e.workspace_id=r.workspace_id
JOIN bank_reference_nodes n ON n.edition_id=e.id AND n.workspace_id=e.workspace_id
JOIN bank_reference_validity v ON v.edition_id=e.id AND v.workspace_id=e.workspace_id
JOIN bank_subjects sb ON sb.id=v.subject_id AND sb.workspace_id=v.workspace_id
JOIN bank_exam_types et ON et.id=v.exam_type_id AND et.workspace_id=v.workspace_id
WHERE r.workspace_id=:ws AND r.reference_key=:ref AND e.edition_key=:edition
AND n.node_key=:node AND v.exam_year=:yr AND sb.subject_key=:sub
AND et.type_key=:type AND v.is_official=1
SQL);
        $examYearOfficialValidity = $nonResidencyProxyType ? $db->prepare(<<<'SQL'
SELECT v.id,v.scope_chapters,r.reference_key,e.edition_key
FROM bank_reference_validity v
JOIN bank_subjects sb ON sb.id=v.subject_id AND sb.workspace_id=v.workspace_id
JOIN bank_exam_types et ON et.id=v.exam_type_id AND et.workspace_id=v.workspace_id
JOIN bank_reference_editions e ON e.id=v.edition_id AND e.workspace_id=v.workspace_id
JOIN bank_references r ON r.id=e.reference_id AND r.workspace_id=e.workspace_id
WHERE v.workspace_id=:ws AND v.exam_year=:yr AND sb.subject_key=:sub
AND et.type_key=:type AND v.is_official=1
FOR UPDATE
SQL) : null;
        $insert = $db->prepare(<<<'SQL'
INSERT INTO bank_question_sources
(id,workspace_id,question_id,edition_id,reference_year,classification_basis,classification_note,
node_id,page,pdf_page,printed_page,pdf_sha256,anchor_text,
is_primary,confidence_source,confidence_node,confidence_page,origin,official_scope_status,
official_scope_note,scope_unverified,created_at)
VALUES (:id,:ws,:qid,:edition,:reference_year,:classification_basis,:classification_note,
:node,:page,:pdf_page,:printed_page,:pdf_sha256,:anchor,
1,:cs,:cn,:cp,'ai',:scope_status,:scope_note,:scope_unverified,UTC_TIMESTAMP(6))
SQL);
        $db->beginTransaction();
        $count = 0;
        try {
            if ($nonResidencyProxyType && $batchClassificationBasis === 'same_lineage_proxy') {
                $examYearOfficialValidity->execute([
                    'ws' => $workspace, 'yr' => $year, 'sub' => $subject, 'type' => $examType,
                ]);
                $examYearRows = $examYearOfficialValidity->fetchAll(PDO::FETCH_ASSOC);
                if ($examYearRows !== []
                    && !self::ownerApprovedScopeGap($examYearRows, (string) $examType, $year, $subject, $mapped)) {
                    throw new RuntimeException('A non-residency same-lineage proxy is forbidden when the exact exam-year official list exists.');
                }
            }
            foreach ($mapped as $n => $src) {
                $get->execute(['ws' => $workspace, 'type' => $examType, 'yr' => $year, 'round' => $round, 'sub' => $subject, 'num' => $n]);
                $q = $get->fetch(PDO::FETCH_ASSOC);
                if (!is_array($q) || $get->fetch(PDO::FETCH_ASSOC) !== false
                    || $q['stem'] !== $original[$n]['stem']) {
                    throw new RuntimeException('Site question differs from reviewed study.');
                }
                $voidedDraftCandidate = $policyYear === 1398 && $subject === 'prosthodontics'
                    && $n === 97 && $q['status'] === 'draft';
                if ($q['status'] !== 'published' && !$voidedDraftCandidate) {
                    throw new RuntimeException('Only published questions and the exact voided 1398 prosthodontics Q97 draft may receive a source.');
                }
                $params = ['ws' => $workspace, 'qid' => $q['id']];
                $choices->execute($params);
                $realChoices = array_map(
                    static fn(array $c): mixed => $c['image'] === null
                        ? (string) $c['text'] : ['text' => (string) $c['text'], 'image' => (string) $c['image']],
                    $choices->fetchAll(PDO::FETCH_ASSOC),
                );
                if ($realChoices !== $original[$n]['choices']) {
                    throw new RuntimeException('Published choices no longer equal reviewed choices.');
                }
                $answer->execute($params);
                $ans = $answer->fetch(PDO::FETCH_ASSOC);
                if (!is_array($ans)) {
                    throw new RuntimeException('Published official answer missing.');
                }
                $actual = [
                    'choice' => $ans['choice_position'] === null ? null : (int) $ans['choice_position'],
                    'also_correct' => $ans['also_correct_positions']
                        ? json_decode((string) $ans['also_correct_positions'], true, 32, JSON_THROW_ON_ERROR) : [],
                    'status' => (string) $ans['status'],
                ];
                if ($actual !== $original[$n]['answer']) {
                    throw new RuntimeException('Published answer differs from reviewed study.');
                }
                $readbackEvidence[$n] = self::digest([
                    'number' => $n,
                    'question_key' => (string) $q['question_key'],
                    'status' => (string) $q['status'],
                    'stem' => (string) $q['stem'],
                    'stem_image_sha256' => $q['stem_image'] === null
                        ? null : hash('sha256', (string) $q['stem_image']),
                    'choices' => $realChoices,
                    'answer' => $actual,
                ]);
                if ($voidedDraftCandidate
                    && ($actual['choice'] !== null || $actual['status'] !== 'voided')) {
                    throw new RuntimeException('Voided Q97 must retain its null-choice voided official answer.');
                }
                // A scope-gap exam is current (only its chapter scope is borrowed), so its
                // published preliminary key is accepted as for an exact batch.
                // Owner-approved exams whose only published key is preliminary likewise.
                $keyStatuses = !$retrospective
                    && (isset(self::SCOPE_GAP_REFERENCE_YEARS["{$examType}:{$year}:{$subject}"])
                        || isset(self::OWNER_PRELIMINARY_KEY_EXAMS["{$examType}:{$year}"]))
                    ? ['final', 'amended', 'preliminary'] : ['final', 'amended'];
                if (($retrospective || $sameLineageProxy) && ($actual['choice'] === null
                    || !in_array($actual['status'], $keyStatuses, true))) {
                    throw new RuntimeException('Voided, disputed or keyless older-exam question cannot receive a proxy source.');
                }
                $existing->execute($params);
                if ($existing->fetch(PDO::FETCH_ASSOC) !== false) {
                    throw new RuntimeException('Source already exists; reviewed sources are immutable.');
                }
            $nodes->execute([
                'ws' => $workspace, 'ref' => $src['ref'], 'edition' => $src['edition'],
                'node' => $src['node'], 'yr' => $src['reference_year'], 'sub' => $src['reference_subject'],
                'type' => $examType,
            ]);
                $mappedNode = $nodes->fetch(PDO::FETCH_ASSOC);
                if (!is_array($mappedNode) || $nodes->fetch(PDO::FETCH_ASSOC) !== false
                    || $mappedNode['kind'] !== 'chapter') {
                    throw new RuntimeException('Exact-edition chapter lacks an official validity row for its selected reference year.');
                }
                $scope = json_decode((string) $mappedNode['scope_chapters'], true);
                $numbers = is_array($scope)
                    ? array_map(static fn(array $a): string => (string) ($a['number'] ?? ''), $scope) : [];
                $structuredScope = is_array($scope) && $scope !== [];
                if ($structuredScope) {
                    foreach ($scope as $scopeItem) {
                        if (!is_array($scopeItem)
                            || !is_string($scopeItem['number'] ?? null)
                                && !is_int($scopeItem['number'] ?? null)
                            || trim((string) ($scopeItem['number'] ?? '')) === '') {
                            $structuredScope = false;
                            break;
                        }
                    }
                }
                $explicitlyOutside1406Syllabus = $policyYear === 1397
                    && $updatedReferenceProxy
                    && ($src['official_scope_status'] ?? null) === 'outside_scope_exam_question'
                    && is_string($src['official_scope_note'] ?? null)
                    && str_contains($src['official_scope_note'], 'سرفصل رسمی ۱۴۰۶')
                    && str_contains($src['official_scope_note'], 'خارج از محدوده')
                    && str_contains($src['official_scope_note'], '۱۳۹۷')
                    && str_contains($src['official_scope_note'], '۱۴۰۶');
                $nonResidencyOutsideScope = $nonResidencyProxyType
                    && ($src['official_scope_status'] ?? null) === 'outside_scope_exam_question'
                    && is_string($src['official_scope_note'] ?? null)
                    && self::validNonResidencyOutsideScopeNote(
                        $src['official_scope_note'], (string) $examType, $year)
                    && $structuredScope
                    && count($numbers) === count($scope);
                if (!in_array($src['chapter'], $numbers, true)) {
                    if (!$explicitlyOutside1406Syllabus && !$nonResidencyOutsideScope) {
                        throw new RuntimeException('Year-specific official syllabus scope is missing this chapter.');
                    }
                } elseif ($nonResidencyOutsideScope) {
                    throw new RuntimeException('An in-scope chapter cannot carry an outside-scope disposition.');
                }
                $insert->execute([
                    'id' => Uuid::v7(), 'ws' => $workspace, 'qid' => $q['id'],
                    'edition' => $mappedNode['edition_id'], 'node' => $mappedNode['node_id'],
                    'reference_year' => $src['reference_year'],
                    'classification_basis' => $src['classification_basis'],
                    'classification_note' => $src['classification_note'],
                    'page' => $src['page'], 'pdf_page' => $src['pdf_page'],
                    'printed_page' => $src['printed_page'], 'pdf_sha256' => $src['pdf_sha256'],
                    'anchor' => $src['anchor'],
                    'cs' => $src['conf'], 'cn' => $src['conf'], 'cp' => $src['conf'],
                    'scope_status' => $src['official_scope_status'],
                    'scope_note' => $src['official_scope_note'],
                    'scope_unverified' => $src['scope_unverified'] ? 1 : 0,
                ]);
                ++$count;
            }
            if ($count !== count($wanted)) {
                throw new RuntimeException('Insert count mismatch.');
            }
            if ($apply) {
                $db->commit();
            } else {
                $db->rollBack();
            }
            return $count;
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    /**
     * Independently read back a committed source-only batch on a fresh PDO
     * connection. The returned evidence contains counts, row IDs and hashes
     * only; question, answer, anchor and proxy-note text never leaves this
     * method. Call only after run(..., true) has committed.
     *
     * @return array<string, mixed> Safe receipt fields; `verified` is false on
     *                              any missing, extra or altered row/content.
     */
    public static function readback(
        PDO $db, string $workspace, int $year, string $subject,
        array $study, array $validated, array $readbackEvidence,
    ): array {
        if ($db->inTransaction()) {
            throw new RuntimeException('Post-commit readback requires a fresh connection outside a transaction.');
        }

        $examType = $study['exam_type'] ?? null;
        $round = $study['round'] ?? null;
        $maxRound = in_array($examType, ['board', 'promotion'], true) ? 99 : 9;
        if (!in_array($examType, ['residency', 'national', 'board', 'promotion'], true)
            || !is_int($round) || $round < 1 || $round > $maxRound
            || ($examType === 'residency' && $round !== 1)
            || ($validated['exam_type'] ?? null) !== $examType
            || ($validated['round'] ?? null) !== $round
            || ($study['year'] ?? null) !== $year || ($validated['year'] ?? null) !== $year) {
            throw new RuntimeException('Readback study type, year or round does not match the validated sitting.');
        }
        $policyYear = $examType === 'residency' ? $year : 0;

        $original = [];
        foreach ($study['questions'] ?? [] as $question) {
            $number = $question['number'] ?? null;
            if (!is_int($number) || isset($original[$number])) {
                throw new RuntimeException('Readback input has an invalid or duplicate question number.');
            }
            $original[$number] = $question;
        }
        ksort($original, SORT_NUMERIC);

        $validatedByNumber = [];
        foreach ($validated['questions'] ?? [] as $question) {
            $number = $question['number'] ?? null;
            if (!is_int($number) || isset($validatedByNumber[$number]) || !isset($original[$number])) {
                throw new RuntimeException('Readback validated question set differs from the study.');
            }
            $sourceRows = $question['sources'] ?? [];
            unset($question['sources']);
            if ($question !== $original[$number]) {
                throw new RuntimeException('Readback validated question content differs from the study.');
            }
            if (!is_array($sourceRows) || count($sourceRows) > 1) {
                throw new RuntimeException('Readback expects at most one reviewed source per question.');
            }
            $validatedByNumber[$number] = $sourceRows;
        }
        if (count($validatedByNumber) !== count($original)) {
            throw new RuntimeException('Readback validated question count differs from the study.');
        }

        $questionQuery = $db->prepare(<<<'SQL'
SELECT q.id,q.question_key,q.number_in_sitting,q.stem,q.stem_image,q.status
FROM bank_questions q
JOIN bank_exam_sittings si ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=q.workspace_id
JOIN bank_subjects sb ON sb.id=q.subject_id AND sb.workspace_id=q.workspace_id
WHERE q.workspace_id=:ws AND et.type_key=:type AND si.exam_year=:yr
AND si.exam_round=:round AND sb.subject_key=:sub AND q.number_in_sitting=:num
SQL);
        $choicesQuery = $db->prepare('SELECT text,image FROM bank_question_choices WHERE workspace_id=:ws AND question_id=:qid ORDER BY position');
        $answerQuery = $db->prepare('SELECT choice_position,also_correct_positions,status FROM bank_official_answers WHERE workspace_id=:ws AND question_id=:qid ORDER BY recorded_at DESC,id DESC LIMIT 1');
        $sourcesQuery = $db->prepare(<<<'SQL'
SELECT s.id,s.question_id,r.reference_key,e.edition_key,n.node_key,
       s.page,s.printed_page,s.pdf_page,s.pdf_sha256,s.anchor_text,
       s.reference_year,s.classification_basis,s.classification_note,
       s.origin,s.is_primary,s.confidence_source,s.confidence_node,s.confidence_page,
       s.official_scope_status,s.official_scope_note,s.scope_unverified
FROM bank_question_sources s
LEFT JOIN bank_reference_editions e
  ON e.id=s.edition_id AND e.workspace_id=s.workspace_id
LEFT JOIN bank_references r
  ON r.id=e.reference_id AND r.workspace_id=e.workspace_id
LEFT JOIN bank_reference_nodes n
  ON n.id=s.node_id AND n.edition_id=s.edition_id AND n.workspace_id=s.workspace_id
WHERE s.workspace_id=:ws AND s.question_id=:qid
ORDER BY s.id
SQL);

        $expectedQuestions = [];
        $actualQuestions = [];
        $expectedSources = [];
        $actualSources = [];
        $sourceIds = [];
        foreach ($original as $number => $question) {
            $questionKey = BankImporter::questionKey($examType, $year, $round, $number);
            $hasExpectedSource = ($validatedByNumber[$number] ?? []) !== [];
            $expectedQuestions[] = [
                'number' => $number,
                'question_key' => $questionKey,
                'status' => $hasExpectedSource
                    ? (($policyYear === 1398 && $subject === 'prosthodontics' && $number === 97)
                        ? 'draft' : 'published') : null,
                'stem' => $question['stem'] ?? null,
                'choices' => $question['choices'] ?? null,
                'answer' => $question['answer'] ?? null,
                'live_fingerprint_sha256' => $hasExpectedSource
                    ? ($readbackEvidence[$number] ?? null) : null,
            ];

            $questionQuery->execute(['ws' => $workspace, 'type' => $examType, 'yr' => $year, 'round' => $round, 'sub' => $subject, 'num' => $number]);
            $siteQuestion = $questionQuery->fetch(PDO::FETCH_ASSOC);
            if (!is_array($siteQuestion) || $questionQuery->fetch(PDO::FETCH_ASSOC) !== false) {
                $actualQuestions[] = ['number' => $number, 'missing_or_duplicate' => true];
                continue;
            }

            $params = ['ws' => $workspace, 'qid' => $siteQuestion['id']];
            $choicesQuery->execute($params);
            $siteChoices = array_map(
                static fn(array $choice): mixed => $choice['image'] === null
                    ? (string) $choice['text']
                    : ['text' => (string) $choice['text'], 'image' => (string) $choice['image']],
                $choicesQuery->fetchAll(PDO::FETCH_ASSOC),
            );
            $answerQuery->execute($params);
            $siteAnswer = $answerQuery->fetch(PDO::FETCH_ASSOC);
            $actualAnswer = is_array($siteAnswer) ? [
                'choice' => $siteAnswer['choice_position'] === null ? null : (int) $siteAnswer['choice_position'],
                'also_correct' => $siteAnswer['also_correct_positions']
                    ? json_decode((string) $siteAnswer['also_correct_positions'], true, 32, JSON_THROW_ON_ERROR) : [],
                'status' => (string) $siteAnswer['status'],
            ] : null;
            $actualLiveFingerprint = self::digest([
                'number' => $number,
                'question_key' => (string) $siteQuestion['question_key'],
                'status' => (string) $siteQuestion['status'],
                'stem' => (string) $siteQuestion['stem'],
                'stem_image_sha256' => $siteQuestion['stem_image'] === null
                    ? null : hash('sha256', (string) $siteQuestion['stem_image']),
                'choices' => $siteChoices,
                'answer' => $actualAnswer,
            ]);
            $actualQuestions[] = [
                'number' => $number,
                'question_key' => (string) $siteQuestion['question_key'],
                'status' => $hasExpectedSource ? (string) $siteQuestion['status'] : null,
                'stem' => (string) $siteQuestion['stem'],
                'choices' => $siteChoices,
                'answer' => $actualAnswer,
                'live_fingerprint_sha256' => $hasExpectedSource ? $actualLiveFingerprint : null,
            ];

            $expectedForQuestion = $validatedByNumber[$number] ?? [];
            foreach ($expectedForQuestion as $source) {
                $expectedSources[] = self::readbackSource($number, $source, null);
            }
            $sourcesQuery->execute($params);
            foreach ($sourcesQuery->fetchAll(PDO::FETCH_ASSOC) as $source) {
                $sourceIds[] = (string) $source['id'];
                $actualSources[] = self::readbackSource($number, [
                    'ref' => ($source['reference_key'] === null || $source['edition_key'] === null || $source['node_key'] === null)
                        ? null : $source['reference_key'] . '@' . $source['edition_key'] . '#' . $source['node_key'],
                    'page' => $source['page'],
                    'printed_page' => $source['printed_page'],
                    'pdf_page' => $source['pdf_page'] === null ? null : (int) $source['pdf_page'],
                    'pdf_sha256' => $source['pdf_sha256'],
                    'anchor' => $source['anchor_text'],
                    'reference_year' => $source['reference_year'] === null ? null : (int) $source['reference_year'],
                    'classification_basis' => $source['classification_basis'],
                    'classification_note' => $source['classification_note'],
                    'origin' => $source['origin'],
                    'primary' => (bool) $source['is_primary'],
                    'confidence' => [
                        'source' => $source['confidence_source'],
                        'node' => $source['confidence_node'],
                        'page' => $source['confidence_page'],
                    ],
                    'official_scope_status' => $source['official_scope_status'],
                    'official_scope_note' => $source['official_scope_note'],
                    'scope_unverified' => (bool) $source['scope_unverified'],
                ], (string) $source['id']);
            }
        }

        $comparison = self::compareReadback($expectedQuestions, $actualQuestions, $expectedSources, $actualSources);
        sort($sourceIds, SORT_STRING);
        return $comparison + [
            'source_row_ids' => $sourceIds,
            'source_rows_sha256' => self::digest($actualSources),
        ];
    }

    /**
     * Pure comparison function kept public for focused tests. Arrays passed
     * here may contain private values; the result never returns them.
     *
     * @return array<string, mixed>
     */
    public static function compareReadback(
        array $expectedQuestions, array $actualQuestions,
        array $expectedSources, array $actualSources,
    ): array {
        $expectedQuestionHash = self::digest($expectedQuestions);
        $actualQuestionHash = self::digest($actualQuestions);
        $withoutIds = static fn(array $rows): array => array_map(
            static function (array $row): array {
                unset($row['source_id']);
                return $row;
            },
            $rows,
        );
        $expectedSourceHash = self::digest($withoutIds($expectedSources));
        $actualSourceHash = self::digest($withoutIds($actualSources));
        $sourceFieldMismatches = self::sourceFieldMismatches($expectedSources, $actualSources);
        $checks = [
            'question_row_count' => count($expectedQuestions) === count($actualQuestions),
            'question_key_content_fingerprint' => hash_equals($expectedQuestionHash, $actualQuestionHash),
            'source_row_count' => count($expectedSources) === count($actualSources),
            'source_provenance_rows' => hash_equals($expectedSourceHash, $actualSourceHash),
        ];
        $mismatches = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));

        return [
            'verified' => $mismatches === [],
            'mismatches' => $mismatches,
            'expected_question_rows' => count($expectedQuestions),
            'question_rows' => count($actualQuestions),
            'question_key_fingerprint_sha256' => $actualQuestionHash,
            'expected_question_key_fingerprint_sha256' => $expectedQuestionHash,
            'expected_source_rows' => count($expectedSources),
            'source_rows' => count($actualSources),
            'expected_source_fields_sha256' => $expectedSourceHash,
            'source_fields_sha256' => $actualSourceHash,
            'source_field_mismatches' => $sourceFieldMismatches,
            'checks' => $checks,
        ];
    }

    /** @return array<string, mixed> */
    /**
     * Visible pre-1396 proxy label. Persian digits are multibyte, so the
     * array form of strtr is required (the three-string form is byte-wise).
     * Must equal retrospective_reference_policy.retrospective_note().
     */
    public static function retrospectiveNote(int $referenceYear, int $examYear): string
    {
        $digits = ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹'];
        return 'این ارجاع پروکسی از فهرست رسمی سال ' . strtr((string) $referenceYear, $digits)
            . ' برای آزمون سال ' . strtr((string) $examYear, $digits)
            . ' است؛ رسمی بودن آن برای سال آزمون ادعا نمی‌شود.';
    }

    /**
     * Persian/Arabic-Indic digits to ASCII. They are multibyte, so the array
     * form of strtr is required (the three-string form is byte-wise).
     */
    public static function asciiDigits(string $text): string
    {
        return strtr($text, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    /**
     * Owner decision 2026-10-24: these exact exam-year lists name the book but
     * their recovered syllabus row names no chapters («فصول منتخب»). Their
     * sources may borrow the chapter scope of the named later official list of
     * the same edition as a visible same-lineage proxy. Mirrors
     * same_lineage_proxy_policy.SCOPE_GAP_REFERENCE_YEARS.
     */
    /**
     * Owner decision 2026-10-26: exams whose only published key is preliminary;
     * it is accepted as the official key. Mirrors
     * same_lineage_proxy_policy.OWNER_PRELIMINARY_KEY_EXAMS.
     */
    private const OWNER_PRELIMINARY_KEY_EXAMS = [
        'board:1400' => true,
        'national:1399' => true,
    ];

    private const SCOPE_GAP_REFERENCE_YEARS = [
        'national:1402:pediatric-dentistry' => 1404,
        'national:1403:pediatric-dentistry' => 1404,
    ];

    /**
     * @param list<array<string, mixed>> $examYearRows
     * @param array<int, array<string, mixed>> $mapped
     */
    private static function ownerApprovedScopeGap(
        array $examYearRows, string $examType, int $year, string $subject, array $mapped,
    ): bool {
        $referenceYear = self::SCOPE_GAP_REFERENCE_YEARS["{$examType}:{$year}:{$subject}"] ?? null;
        if ($referenceYear === null || $mapped === []) {
            return false;
        }
        $editions = [];
        foreach ($examYearRows as $row) {
            $scope = json_decode((string) ($row['scope_chapters'] ?? ''), true);
            if (is_array($scope) && $scope !== []) {
                return false;
            }
            $editions[$row['reference_key'] . '@' . $row['edition_key']] = true;
        }
        foreach ($mapped as $src) {
            if (($src['reference_year'] ?? null) !== $referenceYear
                || !isset($editions[$src['ref'] . '@' . $src['edition']])) {
                return false;
            }
        }
        return true;
    }

    private static function validNonResidencyOutsideScopeNote(
        mixed $note, string $examType, int $year,
    ): bool {
        if (!is_string($note) || mb_strlen(trim($note)) < 24
            || !str_contains($note, 'خارج از محدوده')
            || !preg_match('/[\x{0600}-\x{06FF}]/u', $note)) {
            return false;
        }
        $persianDigits = ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹'];
        if ($examType === 'national') {
            return trim($note) === 'محل طراحی این سؤال در فصلی خارج از محدوده سرفصل رسمی آزمون ملی '
                . strtr((string) $year, $persianDigits) . ' است.';
        }
        return in_array($examType, ['board', 'promotion'], true)
            && str_contains($note, 'سرفصل رسمی')
            && str_contains(self::asciiDigits($note), (string) $year);
    }

    private static function readbackSource(int $number, array $source, ?string $id): array
    {
        $confidence = $source['confidence'] ?? [];
        return [
            'number' => $number,
            'source_id' => $id,
            'ref' => $source['ref'] ?? null,
            'page' => isset($source['page']) ? (string) $source['page'] : null,
            'printed_page' => isset($source['printed_page']) ? (string) $source['printed_page'] : null,
            'pdf_page' => isset($source['pdf_page']) ? (int) $source['pdf_page'] : null,
            'pdf_sha256' => isset($source['pdf_sha256']) ? strtolower((string) $source['pdf_sha256']) : null,
            'anchor_sha256' => isset($source['anchor']) ? hash('sha256', (string) $source['anchor']) : null,
            'reference_year' => isset($source['reference_year']) ? (int) $source['reference_year'] : null,
            'classification_basis' => $source['classification_basis'] ?? null,
            'classification_note_sha256' => isset($source['classification_note'])
                ? hash('sha256', (string) $source['classification_note']) : null,
            'origin' => $source['origin'] ?? null,
            'is_primary' => (bool) ($source['primary'] ?? false),
            'confidence_source' => isset($confidence['source']) ? number_format((float) $confidence['source'], 4, '.', '') : null,
            'confidence_node' => isset($confidence['node']) ? number_format((float) $confidence['node'], 4, '.', '') : null,
            'confidence_page' => isset($confidence['page']) ? number_format((float) $confidence['page'], 4, '.', '') : null,
            'official_scope_status' => $source['official_scope_status']
                ?? self::effectiveScopeStatus(($source['classification_basis'] ?? null) === 'updated_reference_proxy'),
            'official_scope_note_sha256' => isset($source['official_scope_note'])
                ? hash('sha256', (string) $source['official_scope_note']) : null,
            'scope_unverified' => false,
        ];
    }

    /** @return list<array{number: int|null, fields: list<string>}> */
    private static function sourceFieldMismatches(array $expected, array $actual): array
    {
        $out = [];
        $length = max(count($expected), count($actual));
        for ($index = 0; $index < $length; ++$index) {
            if (!isset($expected[$index]) || !isset($actual[$index])) {
                $row = $expected[$index] ?? $actual[$index] ?? [];
                $out[] = ['number' => isset($row['number']) ? (int) $row['number'] : null, 'fields' => ['row_count']];
                continue;
            }
            $expectedRow = $expected[$index];
            $actualRow = $actual[$index];
            unset($expectedRow['source_id'], $actualRow['source_id']);
            $fields = [];
            foreach (array_unique(array_merge(array_keys($expectedRow), array_keys($actualRow))) as $field) {
                if (($expectedRow[$field] ?? null) !== ($actualRow[$field] ?? null)
                    || array_key_exists($field, $expectedRow) !== array_key_exists($field, $actualRow)) {
                    $fields[] = (string) $field;
                }
            }
            if ($fields !== []) {
                sort($fields, SORT_STRING);
                $out[] = ['number' => (int) ($expectedRow['number'] ?? $actualRow['number'] ?? 0), 'fields' => $fields];
            }
        }
        return $out;
    }

    private static function digest(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private static function effectiveScopeStatus(bool $updatedReferenceProxy): string
    {
        return $updatedReferenceProxy ? 'outside_scope_exam_question' : 'in_scope';
    }
}
