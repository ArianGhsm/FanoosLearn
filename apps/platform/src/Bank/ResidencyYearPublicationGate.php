<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

use Fanoos\Platform\Support\PlatformException;
use PDO;

/**
 * Mandatory residency-only, whole-year publication preflight.
 *
 * Staged citation research and protected source-only repairs may proceed,
 * but NO NEW frozen exam version may be created until every question in the
 * sitting's declared inventory has its official-year chapter AND same-row
 * original physical PDF page/hash. English is exempt only with a valid key.
 *
 * This is a structural production DB gate, not a substitute for the private
 * per-question original-book scientific evidence audits or backup checks.
 */
final class ResidencyYearPublicationGate
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function requireComplete(string $workspaceId, string $sittingId, string $typeId, int $year, int $expectedCount): void
    {
        if ($expectedCount < 1 || $expectedCount > 1000) {
            $this->reject('Declared yearly question count is absent or invalid.');
        }

        $query = $this->db->prepare(<<<'SQL'
SELECT q.id, q.number_in_sitting, q.status, sb.id AS subject_id, sb.subject_key,
(SELECT ans.status FROM bank_official_answers ans
 WHERE ans.question_id=q.id AND ans.workspace_id=q.workspace_id
 ORDER BY ans.recorded_at DESC,ans.id DESC LIMIT 1) AS answer_status,
(SELECT ans.choice_position FROM bank_official_answers ans
 WHERE ans.question_id=q.id AND ans.workspace_id=q.workspace_id
 ORDER BY ans.recorded_at DESC,ans.id DESC LIMIT 1) AS answer_choice
FROM bank_questions q
JOIN bank_subjects sb ON sb.id=q.subject_id AND sb.workspace_id=q.workspace_id
WHERE q.workspace_id=:ws AND q.sitting_id=:sitting
ORDER BY q.number_in_sitting
SQL);
        $query->execute(['ws' => $workspaceId, 'sitting' => $sittingId]);
        $questions = $query->fetchAll(PDO::FETCH_ASSOC);
        if (count($questions) !== $expectedCount) {
            $this->reject('The imported questions do not match the declared whole-year inventory.');
        }

        $sources = $this->db->prepare(<<<'SQL'
SELECT s.node_id,s.pdf_page,s.printed_page,s.pdf_sha256,s.official_scope_status,s.official_scope_note,
       s.scope_unverified,n.edition_id
FROM bank_question_sources s
LEFT JOIN bank_reference_nodes n ON n.id=s.node_id AND n.workspace_id=s.workspace_id
WHERE s.workspace_id=:ws AND s.question_id=:qid
SQL);
        $scope = $this->db->prepare(<<<'SQL'
WITH RECURSIVE ancestors AS (
 SELECT id,workspace_id,edition_id,parent_id,kind,number
 FROM bank_reference_nodes WHERE workspace_id=:ws AND id=:node
 UNION ALL
 SELECT parent.id,parent.workspace_id,parent.edition_id,parent.parent_id,parent.kind,parent.number
 FROM bank_reference_nodes parent
 JOIN ancestors child ON child.parent_id=parent.id AND child.workspace_id=parent.workspace_id
)
SELECT a.number,v.scope_chapters,v.scope
FROM ancestors a
JOIN bank_reference_validity v ON v.workspace_id=a.workspace_id
 AND v.edition_id=a.edition_id AND v.is_official=1
 AND v.exam_type_id=:exam_type AND v.exam_year=:exam_year AND v.subject_id=:subject
WHERE a.kind='chapter'
LIMIT 1
SQL);
        $unresolved = [];
        $numbers = [];
        foreach ($questions as $q) {
            $number = (int) $q['number_in_sitting'];
            if ($number < 1 || isset($numbers[$number])) {
                $this->reject('The yearly question inventory contains invalid or duplicate numbers.');
            }
            $numbers[$number] = true;
            if ($q['subject_key'] === 'english') {
                if (!in_array($q['answer_status'], ['final', 'amended'], true) || $q['answer_choice'] === null) {
                    $unresolved[] = $number;
                }
                continue;
            }

            $sources->execute(['ws' => $workspaceId, 'qid' => $q['id']]);
            $valid = false;
            foreach ($sources->fetchAll(PDO::FETCH_ASSOC) as $source) {
                if ($source['node_id'] === null || $source['edition_id'] === null
                    || (int) ($source['pdf_page'] ?? 0) < 1
                    || !is_string($source['pdf_sha256'])
                    || preg_match('/^[a-f0-9]{64}$/D', $source['pdf_sha256']) !== 1) {
                    continue;
                }
                // A physical PDF location is useful research progress, but a
                // partial chapter with no corroborated printed label has no
                // established year-scope disposition. Never publish it as
                // either in-scope or outside-scope by inference.
                if ((bool) ($source['scope_unverified'] ?? false)) {
                    continue;
                }
                $scope->execute([
                    'ws' => $workspaceId, 'node' => $source['node_id'], 'exam_type' => $typeId,
                    'exam_year' => $year, 'subject' => $q['subject_id'],
                ]);
                $official = $scope->fetch(PDO::FETCH_ASSOC);
                if (!is_array($official)) {
                    continue;
                }
                $listed = $official['scope_chapters'] === null ? [] : json_decode(
                    (string) $official['scope_chapters'], true, 32, JSON_THROW_ON_ERROR,
                );
                if (!is_array($listed)) {
                    continue;
                }
                $scopeText = (string) ($official['scope'] ?? '');
                $outsideTextScope = $listed === [] && (
                    str_contains($scopeText, 'منتخب') || str_contains($scopeText, 'به جز')
                    || str_contains($scopeText, 'بجز') || str_contains($scopeText, 'غیر از')
                    || stripos($scopeText, 'except') !== false
                    || stripos($scopeText, 'excluding') !== false
                );
                $pageScope = OfficialScopePageGuard::pageDisposition(
                    $listed,
                    (string) $official['number'],
                    is_string($source['printed_page'] ?? null) ? $source['printed_page'] : null,
                );
                if (($pageScope === null && !$outsideTextScope)
                    || ($listed === [] && !$outsideTextScope)) {
                    continue;
                }
                $outsideScope = $pageScope === false || $outsideTextScope;
                if ($outsideScope
                    && ($source['official_scope_status'] !== 'outside_scope_exam_question'
                        || !is_string($source['official_scope_note'])
                        || mb_strlen(trim($source['official_scope_note'])) < 12)) {
                    continue;
                }
                $valid = true;
                break;
            }
            if (!$valid) {
                $unresolved[] = $number;
            }
        }

        if ($unresolved !== []) {
            $preview = implode(',', array_slice($unresolved, 0, 12));
            $this->reject(
                'Whole residency year requires verified official chapters and original PDF pages'
                . ' for all book-based questions. Incomplete: ' . count($unresolved) . '; examples: ' . $preview,
            );
        }
    }

    private function reject(string $reason): never
    {
        throw new PlatformException('residency_year_classification_incomplete', $reason, 422);
    }
}
