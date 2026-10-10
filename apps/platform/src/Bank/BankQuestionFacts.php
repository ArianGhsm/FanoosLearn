<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

use PDO;

/**
 * Where a question in the runner comes from: its exam, year and number in
 * that sitting, and the reference chapter it is classified under -- the
 * boxes above every question (owner, 2026-10-11).
 *
 * Read live from the bank by the question's id (a published bank question's
 * id in its exam is its bank key), so a classification added after the exam
 * was published shows at once, with no republication. The chapter is shown
 * only where it may be shown as fact: set or reviewed by a person, or at the
 * owner's confidence threshold (data model §4). Anything not in the bank --
 * an authored question -- has no facts and the runner shows none.
 */
final class BankQuestionFacts
{
    public function __construct(private readonly PDO $database)
    {
    }

    /**
     * @return array{exam:string,exam_key:string,year:int,round:int,number:?int,subject:string,
     *     chapter:?array{book:string,edition:string,number:?string,title:string,title_fa:?string,pdf_page:?int}}|null
     */
    public function forQuestion(string $workspaceId, string $questionKey): ?array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT question.id, question.number_in_sitting, sitting.exam_year, sitting.exam_round,
       type.type_key, type.name AS type_name, subject.name AS subject_name
FROM bank_questions question
JOIN bank_exam_sittings sitting ON sitting.id = question.sitting_id
JOIN bank_exam_types type ON type.id = sitting.exam_type_id
JOIN bank_subjects subject ON subject.id = question.subject_id
WHERE question.workspace_id = :workspace AND question.question_key = :question_key AND question.status = 'published'
LIMIT 1
SQL);
        $query->execute(['workspace' => $workspaceId, 'question_key' => $questionKey]);
        $row = $query->fetch();
        if ($row === false) {
            return null;
        }

        return [
            'exam' => (string) $row['type_name'],
            'exam_key' => (string) $row['type_key'],
            'year' => (int) $row['exam_year'],
            'round' => (int) $row['exam_round'],
            'number' => $row['number_in_sitting'] === null ? null : (int) $row['number_in_sitting'],
            'subject' => (string) $row['subject_name'],
            'chapter' => $this->chapter($workspaceId, (string) $row['id']),
        ];
    }

    /** @return array{book:string,edition:string,number:?string,title:string,title_fa:?string,pdf_page:?int}|null */
    private function chapter(string $workspaceId, string $questionId): ?array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT link.node_id, link.pdf_page, reference.title AS book, edition.edition_label
FROM bank_question_sources link
JOIN bank_reference_editions edition ON edition.id = link.edition_id
JOIN bank_references reference ON reference.id = edition.reference_id
WHERE link.workspace_id = :workspace AND link.question_id = :question AND link.node_id IS NOT NULL
  AND (link.origin = 'human' OR link.reviewed_at IS NOT NULL OR COALESCE(link.confidence_node, link.confidence_source, 1) >= :threshold)
ORDER BY link.is_primary DESC, link.origin = 'human' DESC, link.reviewed_at IS NOT NULL DESC
LIMIT 1
SQL);
        $query->execute(['workspace' => $workspaceId, 'question' => $questionId, 'threshold' => BankBrowseService::CONFIDENCE_THRESHOLD]);
        $source = $query->fetch();
        if ($source === false) {
            return null;
        }

        // A source may cite a section; the box names its top-level chapter.
        $node = $this->node((string) $source['node_id']);
        for ($guard = 0; $node !== null && $node['parent_id'] !== null && $guard < 6; ++$guard) {
            $parent = $this->node($node['parent_id']);
            if ($parent === null) {
                break;
            }
            $node = $parent;
        }
        if ($node === null) {
            return null;
        }

        return [
            'book' => (string) $source['book'],
            'edition' => (string) $source['edition_label'],
            'number' => $node['number'] === null || $node['number'] === '' ? null : (string) $node['number'],
            'title' => (string) $node['title'],
            'title_fa' => $node['title_fa'] === null || $node['title_fa'] === '' ? null : (string) $node['title_fa'],
            'pdf_page' => $source['pdf_page'] === null ? null : (int) $source['pdf_page'],
        ];
    }

    /** @return array{parent_id:?string,number:?string,title:string,title_fa:?string}|null */
    private function node(string $nodeId): ?array
    {
        $query = $this->database->prepare('SELECT parent_id, number, title, title_fa FROM bank_reference_nodes WHERE id = :id');
        $query->execute(['id' => $nodeId]);
        $row = $query->fetch();

        return $row === false ? null : [
            'parent_id' => $row['parent_id'] === null ? null : (string) $row['parent_id'],
            'number' => $row['number'] === null ? null : (string) $row['number'],
            'title' => (string) $row['title'],
            'title_fa' => $row['title_fa'] === null ? null : (string) $row['title_fa'],
        ];
    }
}
