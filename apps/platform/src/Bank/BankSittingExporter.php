<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

use Fanoos\Platform\Content\ExamImageStore;
use PDO;
use RuntimeException;

/**
 * A sitting as the bank holds it, written back out as a fanoos.bank.sitting/1
 * file (and its images) so that a classification can be added to a sitting
 * that is already on the site through the ordinary importer.
 *
 * The file carries exactly what the importer compares and writes -- stems,
 * choices, images, the latest official answer, subject, question attributes
 * and booklet source -- so importing it unchanged reports
 * questions_changed 0 and answers_recorded 0 (09 §7). Sources, concepts and
 * explanations are left out: a file without them leaves the bank's as they
 * are, and the classification step adds the sources it has checked.
 */
final class BankSittingExporter
{
    public function __construct(private readonly PDO $database, private readonly ?ExamImageStore $images = null)
    {
    }

    /**
     * @return array{file:string, questions:int, images:int}
     */
    public function export(string $workspaceId, string $type, int $year, int $round, string $outDir): array
    {
        $sitting = $this->database->prepare(<<<'SQL'
SELECT sitting.id, sitting.held_on, sitting.answer_key_status
FROM bank_exam_sittings sitting
JOIN bank_exam_types type ON type.id = sitting.exam_type_id
WHERE sitting.workspace_id = :workspace AND type.type_key = :type AND sitting.exam_year = :year AND sitting.exam_round = :round
SQL);
        $sitting->execute(['workspace' => $workspaceId, 'type' => $type, 'year' => $year, 'round' => $round]);
        $row = $sitting->fetch();
        if ($row === false) {
            throw new RuntimeException("no {$type} {$year} round {$round} sitting in this workspace");
        }

        $questions = $this->database->prepare(<<<'SQL'
SELECT question.id, question.number_in_sitting, question.stem, question.stem_image, question.question_type,
       question.is_negative_stem, question.is_multiple_statement, question.cognitive_level, question.expert_difficulty,
       question.status, question.booklet_source, subject.subject_key
FROM bank_questions question
JOIN bank_subjects subject ON subject.id = question.subject_id
WHERE question.workspace_id = :workspace AND question.sitting_id = :sitting
ORDER BY question.number_in_sitting
SQL);
        $questions->execute(['workspace' => $workspaceId, 'sitting' => (string) $row['id']]);

        $assets = rtrim($outDir, '/\\') . '/assets';
        $imageCount = 0;
        $out = [];
        foreach ($questions->fetchAll() as $q) {
            $item = [
                'number' => (int) $q['number_in_sitting'],
                'subject' => (string) $q['subject_key'],
                'stem' => (string) $q['stem'],
                'choices' => [],
                'answer' => $this->answer((string) $q['id']),
                'type' => (string) $q['question_type'],
                'negative_stem' => (bool) $q['is_negative_stem'],
                'multiple_statement' => (bool) $q['is_multiple_statement'],
                'status' => (string) $q['status'],
            ];
            if ($q['cognitive_level'] !== null) {
                $item['cognitive_level'] = (string) $q['cognitive_level'];
            }
            if ($q['expert_difficulty'] !== null) {
                $item['difficulty'] = (int) $q['expert_difficulty'];
            }
            if ($q['booklet_source'] !== null) {
                $item['booklet_source'] = (string) $q['booklet_source'];
            }
            if ($q['stem_image'] !== null) {
                $item['stem_image'] = $this->writeImage((string) $q['stem_image'], $assets);
                ++$imageCount;
            }
            $choices = $this->database->prepare('SELECT text, image FROM bank_question_choices WHERE question_id = :question ORDER BY position');
            $choices->execute(['question' => (string) $q['id']]);
            foreach ($choices->fetchAll() as $choice) {
                $entry = ['text' => (string) $choice['text']];
                if ($choice['image'] !== null) {
                    $entry['image'] = $this->writeImage((string) $choice['image'], $assets);
                    ++$imageCount;
                }
                $item['choices'][] = $entry;
            }
            $out[] = $item;
        }

        $file = [
            'format' => BankImporter::SITTING_FORMAT,
            'notes' => 'Exported from the bank (BankSittingExporter) to add checked sources; re-importing it unchanged changes no question or answer.',
            'exam_type' => $type,
            'year' => $year,
            'round' => $round,
            'answer_key_status' => (string) $row['answer_key_status'],
            'questions' => $out,
        ];
        if ($row['held_on'] !== null) {
            $file['held_on'] = (string) $row['held_on'];
        }
        $path = rtrim($outDir, '/\\') . "/{$type}-{$year}-{$round}.json";
        if (file_put_contents($path, json_encode($file, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n") === false) {
            throw new RuntimeException("could not write {$path}");
        }

        return ['file' => $path, 'questions' => count($out), 'images' => $imageCount];
    }

    /** @return array{choice:?int, status:string, also_correct?:list<int>, source?:string} */
    private function answer(string $questionId): array
    {
        $latest = $this->database->prepare('SELECT choice_position, also_correct_positions, status, source FROM bank_official_answers WHERE question_id = :question ORDER BY recorded_at DESC, id DESC LIMIT 1');
        $latest->execute(['question' => $questionId]);
        $row = $latest->fetch();
        if ($row === false) {
            throw new RuntimeException("question {$questionId} has no official answer");
        }
        $answer = ['choice' => $row['choice_position'] === null ? null : (int) $row['choice_position'], 'status' => (string) $row['status']];
        $also = $row['also_correct_positions'] === null ? [] : array_map('intval', (array) json_decode((string) $row['also_correct_positions'], true));
        if ($also !== []) {
            $answer['also_correct'] = $also;
        }
        if ($row['source'] !== null) {
            $answer['source'] = (string) $row['source'];
        }

        return $answer;
    }

    /** The stored image under its own content-addressed key, so re-importing stores the same key. */
    private function writeImage(string $key, string $assets): string
    {
        if ($this->images === null) {
            throw new RuntimeException('this sitting has images; no image storage is configured');
        }
        $opened = $this->images->open($key);
        if (!is_dir($assets) && !mkdir($assets, 0750, true) && !is_dir($assets)) {
            throw new RuntimeException("could not create {$assets}");
        }
        $bytes = stream_get_contents($opened['stream']);
        fclose($opened['stream']);
        if (!is_string($bytes) || file_put_contents($assets . '/' . $key, $bytes) === false) {
            throw new RuntimeException("could not export image {$key}");
        }

        return $key;
    }
}
