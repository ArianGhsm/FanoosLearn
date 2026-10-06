<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

use Fanoos\Platform\Content\ExamService;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;

/**
 * Publishes one bank sitting as an exam students can take.
 *
 * The exam is an ordinary assessment whose version holds a frozen copy of
 * the sitting's questions, so the runner, pacing, attempts, review,
 * statistics and images all work unchanged, and a later correction to the
 * bank never changes a past attempt (data model §1 rule 8). Publishing again
 * after a correction adds a new version of the same exam.
 *
 * It goes through ExamService's own draft → review → publish path: the
 * actor and the reviewer are different people, as for any exam, unless the
 * actor is an installation owner reviewing their own (audited as such).
 *
 * Left out of the exam, and reported: withdrawn questions, and questions
 * whose official answer is voided or disputed — a student cannot be scored
 * against an answer that does not stand.
 */
final class BankPublisher
{
    private const LETTERS = ['الف', 'ب', 'ج', 'د', 'ه', 'و', 'ز', 'ح', 'ط', 'ی'];

    public function __construct(
        private readonly PDO $database,
        private readonly ExamService $exams,
    ) {
    }

    /**
     * @param array{max_attempts?:int,time_limit_minutes?:?int} $options
     * @return array{assessment_id:string,version_id:string,questions:int,left_out:list<string>,new_exam:bool}
     */
    public function publish(string $workspaceId, string $examType, int $year, int $round, string $actorUserId, string $reviewerUserId, array $options = []): array
    {
        $sitting = $this->database->prepare(<<<'SQL'
SELECT sitting.id, sitting.assessment_id, type.type_key, type.name AS type_name
FROM bank_exam_sittings sitting
JOIN bank_exam_types type ON type.id = sitting.exam_type_id
WHERE sitting.workspace_id = :workspace AND type.type_key = :type AND sitting.exam_year = :year AND sitting.exam_round = :round
SQL);
        $sitting->execute(['workspace' => $workspaceId, 'type' => $examType, 'year' => $year, 'round' => $round]);
        $row = $sitting->fetch();
        if ($row === false) {
            throw new PlatformException('bank_sitting_not_found', 'That exam is not in the bank.', 404);
        }

        [$questions, $leftOut, $included] = $this->questions($workspaceId, (string) $row['id']);
        if ($questions === []) {
            throw new PlatformException('bank_sitting_empty', 'No question in this exam can be scored.', 422);
        }
        $definition = ['questions' => $questions];
        $title = $row['type_name'] . ' ' . self::faDigits((string) $year) . ($round > 1 ? ' · نوبت ' . self::faDigits((string) $round) : '');

        $newExam = $row['assessment_id'] === null;
        if ($newExam) {
            $created = $this->exams->createAssessment($actorUserId, $workspaceId, $title, $definition, [
                'course_id' => $this->course($workspaceId, (string) $row['type_key'], (string) $row['type_name']),
                'assessment_kind' => 'past_exam',
                'max_attempts' => $options['max_attempts'] ?? 10,
                'time_limit_minutes' => $options['time_limit_minutes'] ?? null,
            ]);
            $assessmentId = $created['assessment_id'];
            $versionId = $created['version_id'];
            $this->database->prepare('UPDATE bank_exam_sittings SET assessment_id = :assessment, updated_at = UTC_TIMESTAMP(6) WHERE id = :id')
                ->execute(['assessment' => $assessmentId, 'id' => $row['id']]);
        } else {
            $assessmentId = (string) $row['assessment_id'];
            $versionId = $this->exams->addVersion($actorUserId, $workspaceId, $assessmentId, $definition)['version_id'];
        }
        $this->exams->submitForReview($actorUserId, $workspaceId, $assessmentId, $versionId);
        $this->exams->reviewVersion($reviewerUserId, $workspaceId, $assessmentId, $versionId, 'approved');
        $this->exams->publishVersion($actorUserId, $workspaceId, $assessmentId, $versionId);

        if ($included !== []) {
            $placeholders = implode(',', array_fill(0, count($included), '?'));
            $this->database->prepare("UPDATE bank_questions SET status = 'published', updated_at = UTC_TIMESTAMP(6) WHERE id IN ({$placeholders})")
                ->execute($included);
        }

        return ['assessment_id' => $assessmentId, 'version_id' => $versionId, 'questions' => count($questions), 'left_out' => $leftOut, 'new_exam' => $newExam];
    }

    /**
     * The sitting's questions in exam order, shaped as the runner's
     * definition expects.
     *
     * @return array{0:list<array<string,mixed>>,1:list<string>,2:list<string>}
     */
    private function questions(string $workspaceId, string $sittingId): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT question.id, question.question_key, question.stem, question.stem_image, question.question_type,
       question.expert_difficulty, question.status, subject.name AS subject_name,
       (SELECT answer.choice_position FROM bank_official_answers answer WHERE answer.question_id = question.id
        ORDER BY answer.recorded_at DESC, answer.id DESC LIMIT 1) AS answer_choice,
       (SELECT answer.status FROM bank_official_answers answer WHERE answer.question_id = question.id
        ORDER BY answer.recorded_at DESC, answer.id DESC LIMIT 1) AS answer_status,
       (SELECT concept.name FROM bank_question_concepts link JOIN bank_concepts concept ON concept.id = link.concept_id
        WHERE link.question_id = question.id ORDER BY link.is_primary DESC, concept.name LIMIT 1) AS concept_name
FROM bank_questions question
JOIN bank_subjects subject ON subject.id = question.subject_id
WHERE question.workspace_id = :workspace AND question.sitting_id = :sitting
ORDER BY question.number_in_sitting
SQL);
        $query->execute(['workspace' => $workspaceId, 'sitting' => $sittingId]);

        $questions = [];
        $leftOut = [];
        $included = [];
        foreach ($query->fetchAll() as $row) {
            $key = (string) $row['question_key'];
            if ($row['status'] === 'withdrawn') {
                $leftOut[] = "{$key}: withdrawn";
                continue;
            }
            if (!in_array($row['answer_status'], ['preliminary', 'final', 'amended'], true) || $row['answer_choice'] === null) {
                $leftOut[] = "{$key}: official answer " . ($row['answer_status'] ?? 'missing');
                continue;
            }
            $choices = $this->database->prepare('SELECT text, image FROM bank_question_choices WHERE question_id = :question ORDER BY position');
            $choices->execute(['question' => $row['id']]);
            $choices = $choices->fetchAll();

            $question = [
                'id' => $key,
                'prompt' => (string) $row['stem'],
                'choices' => array_map(static fn (array $choice): string => (string) $choice['text'], $choices),
                'answer' => (int) $row['answer_choice'] - 1,
                'explanation' => $this->explanation((string) $row['id'], (int) $row['answer_choice'], count($choices)),
                'topic' => (string) ($row['concept_name'] ?? $row['subject_name']),
                'tags' => [(string) $row['subject_name']],
            ];
            if ($row['expert_difficulty'] !== null) {
                $question['difficulty'] = match (true) {
                    (int) $row['expert_difficulty'] <= 2 => 'easy',
                    (int) $row['expert_difficulty'] === 3 => 'medium',
                    default => 'hard',
                };
            }
            $choiceImages = array_map(static fn (array $choice): ?string => $choice['image'] === null ? null : (string) $choice['image'], $choices);
            if ($row['stem_image'] !== null || array_filter($choiceImages) !== []) {
                $question['images'] = ['stem' => $row['stem_image'], 'choices' => $choiceImages];
            }
            $questions[] = $question;
            $included[] = (string) $row['id'];
        }

        return [$questions, $leftOut, $included];
    }

    /**
     * The structured explanation as the Markdown the review renders: the
     * answer, why, why not the others, the tip, the trap, and where in the
     * reference it comes from.
     */
    private function explanation(string $questionId, int $answer, int $choiceCount): ?string
    {
        $query = $this->database->prepare('SELECT id, short_answer, reference_explanation, source_location, exam_tip, common_trap FROM bank_explanations WHERE question_id = :question AND is_current = TRUE');
        $query->execute(['question' => $questionId]);
        $row = $query->fetch();

        $source = $this->database->prepare(<<<'SQL'
SELECT reference.title, edition.edition_label, node.number, node.title AS node_title, link.page
FROM bank_question_sources link
JOIN bank_reference_editions edition ON edition.id = link.edition_id
JOIN bank_references reference ON reference.id = edition.reference_id
LEFT JOIN bank_reference_nodes node ON node.id = link.node_id
WHERE link.question_id = :question
ORDER BY link.is_primary DESC, link.created_at
LIMIT 1
SQL);
        $source->execute(['question' => $questionId]);
        $where = $source->fetch();

        if ($row === false && $where === false) {
            return null;
        }
        $parts = ['**پاسخ: گزینه‌ی ' . (self::LETTERS[$answer - 1] ?? (string) $answer) . '**'];
        if ($row !== false) {
            if ($row['short_answer']) {
                $parts[] = (string) $row['short_answer'];
            }
            if ($row['reference_explanation']) {
                $parts[] = "## از رفرنس\n" . $row['reference_explanation'];
            }
            $why = $this->database->prepare('SELECT position, why_wrong FROM bank_explanation_choices WHERE explanation_id = :explanation ORDER BY position');
            $why->execute(['explanation' => $row['id']]);
            $lines = [];
            foreach ($why->fetchAll() as $choice) {
                if ((int) $choice['position'] !== $answer && (int) $choice['position'] <= $choiceCount) {
                    $lines[] = '- **' . (self::LETTERS[(int) $choice['position'] - 1] ?? $choice['position']) . ':** ' . $choice['why_wrong'];
                }
            }
            if ($lines !== []) {
                $parts[] = "## چرا بقیه نه\n" . implode("\n", $lines);
            }
            if ($row['exam_tip']) {
                $parts[] = "## نکته‌ی آزمون\n" . $row['exam_tip'];
            }
            if ($row['common_trap']) {
                $parts[] = "## دام رایج\n" . $row['common_trap'];
            }
        }
        $location = $row !== false && $row['source_location'] ? (string) $row['source_location'] : null;
        if ($location === null && $where !== false) {
            $location = trim($where['title'] . ' · ' . $where['edition_label']
                . ($where['number'] !== null ? ' · فصل ' . $where['number'] : '')
                . ($where['page'] !== null ? ' · ص ' . $where['page'] : ''));
        }
        if ($location !== null) {
            $parts[] = '**منبع:** ' . $location;
        }

        return mb_substr(implode("\n\n", $parts), 0, 4000);
    }

    /** One catalogue course per exam type ("دستیاری"), so the exams page can list the papers. */
    private function course(string $workspaceId, string $typeKey, string $typeName): string
    {
        $code = 'bank-' . $typeKey;
        $find = $this->database->prepare('SELECT id FROM academic_courses WHERE workspace_id = :workspace AND course_code = :code');
        $find->execute(['workspace' => $workspaceId, 'code' => $code]);
        $id = $find->fetchColumn();
        if ($id !== false) {
            return (string) $id;
        }
        $id = Uuid::v7();
        $this->database->prepare("INSERT INTO academic_courses (id, workspace_id, course_code, title, status, version, created_at, updated_at) VALUES (:id, :workspace, :code, :title, 'active', 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => $id, 'workspace' => $workspaceId, 'code' => $code, 'title' => 'آزمون‌های ' . $typeName]);

        return $id;
    }

    private static function faDigits(string $value): string
    {
        return strtr($value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }
}
