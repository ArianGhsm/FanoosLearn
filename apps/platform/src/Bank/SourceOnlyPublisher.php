<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Only INSERTs new official source/chapter links for matching, published,
 * currently source-free questions. No UPDATE, DELETE or publication writes.
 * An entire batch commits together or rolls back together.
 */
final class SourceOnlyPublisher
{
    public static function run(
        PDO $db, string $workspace, int $year, string $subject,
        array $study, array $validated, array $decisions, bool $apply,
    ): int {
        $retrospective = $year >= 1300 && $year < 1396;
        if (($year < 1399 && !$retrospective && !($year === 1398 && in_array($subject, ['endodontics', 'periodontics', 'community-dentistry', 'prosthodontics', 'operative-dentistry', 'pediatric-dentistry', 'oral-radiology'], true))) || $year > 1500
            || ($study['format'] ?? '') !== 'fanoos.classification.study-only/1'
            || ($validated['format'] ?? '') !== ($study['format'] ?? '')
            || ($study['exam_type'] ?? '') !== 'residency'
            || ($validated['exam_type'] ?? '') !== 'residency'
            || ($study['year'] ?? 0) !== $year || ($validated['year'] ?? 0) !== $year
            || ($study['round'] ?? 0) !== 1 || ($validated['round'] ?? 0) !== 1) {
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
            } elseif (isset($decision['reference_year']) || isset($decision['classification_basis'])) {
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
            if ($year === 1398 && $subject === 'endodontics'
                && $decision['edition'] !== 'torabinejad-endodontics@5e') {
                throw new RuntimeException('1398 endodontics requires exact announced Torabinejad 5e.');
            }
            if ($year === 1398 && $subject === 'periodontics'
                && $decision['edition'] !== 'carranza-periodontology@12e') {
                throw new RuntimeException('1398 periodontics requires exact announced Carranza 12e.');
            }
            if ($year === 1398 && $subject === 'community-dentistry'
                && $decision['edition'] !== 'national-oral-health@1394') {
                throw new RuntimeException('1398 community dentistry requires the exact national oral-health 1394 edition.');
            }
            if ($year === 1398 && $subject === 'prosthodontics'
                && !in_array((string) $decision['edition'], [
                    'shillingburg-fixed@4e',
                    'mccracken-rpd@12e',
                    'zarb-edentulous@13e',
                ], true)) {
                throw new RuntimeException('1398 prosthodontics requires an exact officially announced edition.');
            }
            if ($year === 1398 && $subject === 'operative-dentistry'
                && ($n !== 175
                    || $decision['edition'] !== 'summit-operative@4e'
                    || (string) $decision['chapter'] !== '6'
                    || $decision['page'] !== 294)) {
                throw new RuntimeException('1398 operative source gate permits only audited Summit 4e Q175 chapter 6 exact PDF page 294.');
            }
            if ($year === 1398 && in_array($subject, ['pediatric-dentistry', 'oral-radiology'], true)) {
                $specific = $subject === 'pediatric-dentistry'
                    ? [
                        164 => ['mcdonald-avery-pediatric@10e', '11', 215],
                        168 => ['mcdonald-avery-pediatric@10e', '8', 159],
                    ]
                    : [
                        211 => ['white-pharoah-radiology@7e', '1', 27],
                        212 => ['white-pharoah-radiology@7e', '3', 50],
                        214 => ['white-pharoah-radiology@7e', '6', 103],
                        217 => ['white-pharoah-radiology@7e', '10', 189],
                    ];
                $expectedSource = $specific[$n] ?? null;
                if (!is_array($expectedSource)
                    || $decision['edition'] !== $expectedSource[0]
                    || (string) $decision['chapter'] !== $expectedSource[1]
                    || $decision['page'] !== $expectedSource[2]) {
                    throw new RuntimeException('1398 pediatric/radiology exact approved original PDF-page source gate refused.');
                }
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
            if (!is_array($sources) || count($sources) !== 1 || !is_array($sources[0])) {
                throw new RuntimeException('One source per accepted question is required.');
            }
            $s = $sources[0];
            $edition = (string) $d['edition'];
            $chapter = 'ch' . str_pad((string) $d['chapter'], 2, '0', STR_PAD_LEFT);
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
            $conf = (float) $d['confidence'];
            if ($conf < .85 || $conf > 1.0) {
                throw new RuntimeException('Unreviewed low-confidence decision.');
            }
            foreach (['source', 'node', 'page'] as $kind) {
                if (abs((float) ($s['confidence'][$kind] ?? 0) - $conf) > .0005) {
                    throw new RuntimeException('Source confidence mismatch.');
                }
            }
            $mapped[$n] = ['ref' => $m[1], 'edition' => $m[2],
                'node' => $chapter, 'chapter' => (string) $d['chapter'],
                'page' => (string) $s['page'],
                'pdf_page' => $sourcePdfPage,
                'pdf_sha256' => strtolower($sourcePdfHash),
                'printed_page' => isset($s['printed_page']) ? (string) $s['printed_page'] : null,
                'anchor' => $s['anchor'], 'conf' => $conf,
                'reference_year' => $retrospective ? (int) $d['reference_year'] : $year];
        }
        if (count($seen) !== count($original) || count($mapped) !== count($wanted)
            || $mapped === []) {
            throw new RuntimeException('Study completeness or accepted count mismatch.');
        }
        $get = $db->prepare(<<<'SQL'
SELECT q.id,q.stem,q.status FROM bank_questions q
JOIN bank_exam_sittings si ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=q.workspace_id
JOIN bank_subjects sb ON sb.id=q.subject_id AND sb.workspace_id=q.workspace_id
WHERE q.workspace_id=:ws AND et.type_key='residency' AND si.exam_year=:yr
AND si.exam_round=1 AND sb.subject_key=:sub AND q.number_in_sitting=:num FOR UPDATE
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
AND et.type_key='residency' AND v.is_official=1
SQL);
        $insert = $db->prepare(<<<'SQL'
INSERT INTO bank_question_sources
(id,workspace_id,question_id,edition_id,node_id,page,pdf_page,printed_page,pdf_sha256,anchor_text,
is_primary,confidence_source,confidence_node,confidence_page,origin,created_at)
VALUES (:id,:ws,:qid,:edition,:node,:page,:pdf_page,:printed_page,:pdf_sha256,:anchor,1,:cs,:cn,:cp,'ai',UTC_TIMESTAMP(6))
SQL);
        $db->beginTransaction();
        $count = 0;
        try {
            foreach ($mapped as $n => $src) {
                $get->execute(['ws' => $workspace, 'yr' => $year, 'sub' => $subject, 'num' => $n]);
                $q = $get->fetch(PDO::FETCH_ASSOC);
                if (!is_array($q) || $get->fetch(PDO::FETCH_ASSOC) !== false
                    || $q['status'] !== 'published' || $q['stem'] !== $original[$n]['stem']) {
                    throw new RuntimeException('Site question differs from reviewed study or is not published.');
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
                if ($retrospective && ($actual['choice'] === null
                    || !in_array($actual['status'], ['final', 'amended'], true))) {
                    throw new RuntimeException('Voided, disputed or keyless old exam question cannot receive retrospective source.');
                }
                $existing->execute($params);
                if ($existing->fetch(PDO::FETCH_ASSOC) !== false) {
                    throw new RuntimeException('Source already exists; reviewed sources are immutable.');
                }
                $nodes->execute([
                    'ws' => $workspace, 'ref' => $src['ref'], 'edition' => $src['edition'],
                    'node' => $src['node'], 'yr' => $src['reference_year'], 'sub' => $subject,
                ]);
                $mappedNode = $nodes->fetch(PDO::FETCH_ASSOC);
                if (!is_array($mappedNode) || $nodes->fetch(PDO::FETCH_ASSOC) !== false
                    || $mappedNode['kind'] !== 'chapter') {
                    throw new RuntimeException('Exact-edition chapter lacks an official validity row for its selected reference year.');
                }
                $scope = json_decode((string) $mappedNode['scope_chapters'], true);
                $numbers = is_array($scope)
                    ? array_map(static fn(array $a): string => (string) ($a['number'] ?? ''), $scope) : [];
                if (!in_array($src['chapter'], $numbers, true)) {
                    throw new RuntimeException('Year-specific official syllabus scope is missing this chapter.');
                }
                $insert->execute([
                    'id' => Uuid::v7(), 'ws' => $workspace, 'qid' => $q['id'],
                    'edition' => $mappedNode['edition_id'], 'node' => $mappedNode['node_id'],
                    'page' => $src['page'], 'pdf_page' => $src['pdf_page'],
                    'printed_page' => $src['printed_page'], 'pdf_sha256' => $src['pdf_sha256'],
                    'anchor' => $src['anchor'],
                    'cs' => $src['conf'], 'cn' => $src['conf'], 'cp' => $src['conf'],
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
}
