<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Content\CustomPracticeService;
use Fanoos\Platform\Support\PlatformException;
use PDO;

/**
 * بانک سؤال -- the bank as a student browses it: by subject (درس به درس)
 * and by exam year, each subject broken into its topics with how often each
 * was asked, and any slice of it opened as a study set in the ordinary
 * runner.
 *
 * Read straight from the bank tables, counting only questions that are
 * published (their sitting is on the site as an exam). Machine
 * classification is used only where it may be shown as fact: reviewed by a
 * person, or at the owner's confidence threshold (data model §4).
 *
 * A study set is built by CustomPracticeService::createFromQuestions, which
 * keeps only questions the student may already open in their own exam, so
 * browsing never reaches past a purchase the student has not made.
 */
final class BankBrowseService
{
    /** The owner's threshold for showing a machine classification as fact (data model §4). */
    public const CONFIDENCE_THRESHOLD = 0.85;
    /** "Recent" in the topic tables: the latest exam year and the four before it. */
    public const RECENT_YEARS = 5;
    private const HIGH_YIELD = 25;
    /** Currency statuses that mean the official answer belongs to an older reference. */
    public const OLD_REFERENCE = ['changed_in_newer', 'outdated', 'contradicted', 'removed_from_syllabus'];

    public function __construct(
        private readonly PDO $database,
        private readonly AccessGate $access,
        private readonly CustomPracticeService $practice,
    ) {
    }

    /**
     * Subjects with their numbers, and the exam papers by year.
     *
     * @return array<string, mixed>
     */
    public function overview(string $userId, string $workspaceId): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        $questions = $this->questions($workspaceId, null);
        $sittings = $this->sittings($workspaceId);
        $published = array_filter($sittings, static fn (array $sitting): bool => $sitting['assessment_id'] !== null);

        $subjects = [];
        foreach ($this->subjects($workspaceId) as $subject) {
            $subjects[$subject['key']] = $subject + ['total' => 0, 'old_reference' => 0, 'first_year' => null, 'last_year' => null, 'topics' => 0, '_sittings' => [], '_topics' => []];
        }
        foreach ($questions as $question) {
            if (!isset($subjects[$question['subject_key']])) {
                continue; // filed under a sub-subject; the overview lists top-level subjects
            }
            $row = &$subjects[$question['subject_key']];
            ++$row['total'];
            $row['old_reference'] += $question['old_reference'] ? 1 : 0;
            $row['first_year'] = $row['first_year'] === null ? $question['year'] : min($row['first_year'], $question['year']);
            $row['last_year'] = $row['last_year'] === null ? $question['year'] : max($row['last_year'], $question['year']);
            $row['_sittings'][$question['sitting_id']] = true;
            if ($question['topic_key'] !== null) {
                $row['_topics'][$question['topic_key']] = true;
            }
            unset($row);
        }

        return [
            'subjects' => array_values(array_map(static function (array $subject): array {
                $sittingCount = count($subject['_sittings']);
                $subject['per_exam'] = $sittingCount === 0 ? null : (int) round($subject['total'] / $sittingCount);
                $subject['topics'] = count($subject['_topics']);
                unset($subject['_sittings'], $subject['_topics'], $subject['id']);
                return $subject;
            }, $subjects)),
            'sittings' => array_values(array_map(static fn (array $sitting): array => [
                'type' => $sitting['type_name'],
                'year' => $sitting['year'],
                'round' => $sitting['round'],
                'assessment_id' => $sitting['assessment_id'],
                'questions' => $sitting['questions'],
            ], $published)),
            'total' => count($questions),
        ];
    }

    /**
     * One subject: its numbers, its topics most-asked first, its most-asked
     * concepts, and the references the latest exams named for it.
     *
     * @return array<string, mixed>
     */
    public function subject(string $userId, string $workspaceId, string $subjectKey): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        $subject = $this->subjectRow($workspaceId, $subjectKey);
        $questions = $this->questions($workspaceId, (string) $subject['id']);
        $latest = $questions === [] ? null : max(array_column($questions, 'year'));
        $recentFrom = $latest === null ? null : $latest - self::RECENT_YEARS + 1;

        $topics = [];
        $concepts = [];
        $sittings = [];
        $years = [];
        $old = 0;
        foreach ($questions as $question) {
            $recent = $recentFrom !== null && $question['year'] >= $recentFrom;
            $topicKey = $question['topic_key'] ?? '';
            $topics[$topicKey] ??= ['key' => $question['topic_key'], 'name' => $question['topic_name'], 'name_en' => $question['topic_name_en'], 'total' => 0, 'recent' => 0, 'old_reference' => 0];
            ++$topics[$topicKey]['total'];
            $topics[$topicKey]['recent'] += $recent ? 1 : 0;
            $topics[$topicKey]['old_reference'] += $question['old_reference'] ? 1 : 0;
            if ($question['concept_key'] !== null && $question['concept_key'] !== $question['topic_key']) {
                $concepts[$question['concept_key']] ??= ['key' => $question['concept_key'], 'name' => $question['concept_name'], 'name_en' => $question['concept_name_en'], 'topic' => $question['topic_name'], 'total' => 0, 'recent' => 0];
                ++$concepts[$question['concept_key']]['total'];
                $concepts[$question['concept_key']]['recent'] += $recent ? 1 : 0;
            }
            $sittings[$question['sitting_id']] = true;
            $years[$question['year']] = ($years[$question['year']] ?? 0) + 1;
            $old += $question['old_reference'] ? 1 : 0;
        }
        $byCount = static fn (array $a, array $b): int => [$b['total'], $b['recent']] <=> [$a['total'], $a['recent']];
        uasort($topics, static function (array $a, array $b) use ($byCount): int {
            // "Not classified yet" goes last, whatever its size.
            if (($a['key'] === null) !== ($b['key'] === null)) {
                return $a['key'] === null ? 1 : -1;
            }
            return $byCount($a, $b);
        });
        uasort($concepts, $byCount);
        krsort($years);

        return [
            'subject' => ['key' => $subject['subject_key'], 'name' => $subject['name'], 'name_en' => $subject['name_en']],
            'total' => count($questions),
            'per_exam' => $sittings === [] ? null : (int) round(count($questions) / count($sittings)),
            'first_year' => $questions === [] ? null : min(array_column($questions, 'year')),
            'last_year' => $latest,
            'recent_from' => $recentFrom,
            'old_reference' => $old,
            'years' => array_map(static fn (int $year, int $count): array => ['year' => $year, 'total' => $count], array_keys($years), array_values($years)),
            'topics' => array_values($topics),
            'high_yield' => array_slice(array_values($concepts), 0, self::HIGH_YIELD),
            'references' => $this->subjectReferences($workspaceId, (string) $subject['id']),
        ];
    }

    /**
     * Opens a slice of a subject as a study set, newest year first:
     * the whole subject, one topic or concept, one year, or only recent years.
     *
     * @param array<string, mixed> $input subject, topic (optional), year (optional), recent (optional bool)
     * @return array{assessment_id:string,title:string,question_count:int}
     */
    public function study(string $userId, string $workspaceId, array $input): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        $subject = $this->subjectRow($workspaceId, (string) ($input['subject'] ?? ''));
        $questions = $this->questions($workspaceId, (string) $subject['id']);
        $title = (string) $subject['name'];

        $topic = isset($input['topic']) && $input['topic'] !== '' ? (string) $input['topic'] : null;
        if ($topic !== null) {
            $questions = array_values(array_filter($questions, static fn (array $q): bool => $q['topic_key'] === $topic || $q['concept_key'] === $topic));
            if ($questions === []) {
                throw new PlatformException('bank_topic_not_found', 'That topic has no questions.', 404);
            }
            $title .= ' · ' . ($questions[0]['concept_key'] === $topic ? $questions[0]['concept_name'] : $questions[0]['topic_name']);
        }
        $year = filter_var($input['year'] ?? null, FILTER_VALIDATE_INT);
        if ($year !== false && $year !== null) {
            $questions = array_values(array_filter($questions, static fn (array $q): bool => $q['year'] === $year));
            $title .= ' · ' . self::faDigits((string) $year);
        }
        if (($input['recent'] ?? false) === true && $questions !== []) {
            $from = max(array_column($questions, 'year')) - self::RECENT_YEARS + 1;
            $questions = array_values(array_filter($questions, static fn (array $q): bool => $q['year'] >= $from));
            $title .= ' · از ' . self::faDigits((string) $from);
        }
        if ($questions === []) {
            throw new PlatformException('bank_study_empty', 'No question matches.', 422);
        }

        return $this->practice->createFromQuestions($userId, $workspaceId, array_column($questions, 'key'), $title, 'bank');
    }

    /**
     * منابع آزمون: the references named for each exam type and year, newest
     * year first (residency before the other types within a year), subject by subject.
     *
     * @return list<array<string, mixed>>
     */
    public function references(string $userId, string $workspaceId): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        $query = $this->database->prepare(<<<'SQL'
SELECT type.name AS type_name, type.type_key, validity.exam_year, subject.subject_key, subject.name AS subject_name, subject.sort_order,
       reference.reference_key, reference.title, reference.authors, edition.id AS edition_id, edition.edition_key, edition.edition_label, edition.published_year, validity.scope, validity.scope_chapters, validity.is_official
FROM bank_reference_validity validity
JOIN bank_exam_types type ON type.id = validity.exam_type_id
JOIN bank_subjects subject ON subject.id = validity.subject_id
JOIN bank_reference_editions edition ON edition.id = validity.edition_id
JOIN bank_references reference ON reference.id = edition.reference_id
WHERE validity.workspace_id = :workspace AND type.is_active = TRUE
ORDER BY validity.exam_year DESC, type.sort_order, subject.sort_order, subject.name, reference.title
SQL);
        $query->execute(['workspace' => $workspaceId]);
        $rows = $query->fetchAll();
        $chapters = $this->editionChapters($workspaceId);

        $years = [];
        foreach ($rows as $row) {
            $key = $row['type_key'] . ':' . $row['exam_year'];
            $years[$key] ??= ['type' => (string) $row['type_name'], 'type_key' => (string) $row['type_key'], 'year' => (int) $row['exam_year'], 'subjects' => []];
            $subjects = &$years[$key]['subjects'];
            $subjects[$row['subject_key']] ??= ['key' => (string) $row['subject_key'], 'name' => (string) $row['subject_name'], 'references' => []];
            $subjects[$row['subject_key']]['references'][] = [
                'title' => (string) $row['title'],
                'edition_ref' => $row['reference_key'] . '@' . $row['edition_key'],
                'authors' => $row['authors'],
                'edition' => (string) $row['edition_label'],
                'published_year' => $row['published_year'] === null ? null : (int) $row['published_year'],
                'scope' => $row['scope'],
                'official' => (bool) $row['is_official'],
                'chapters' => self::markScope($chapters[(string) $row['edition_id']] ?? [], $row['scope_chapters']),
            ];
            unset($subjects);
        }

        return array_values(array_map(static function (array $year): array {
            $year['subjects'] = array_values($year['subjects']);
            return $year;
        }, $years));
    }

    /**
     * Each edition's chapters (its top-level nodes), in chapter-number order:
     * question imports add nodes too, so sort_order is not the book's order.
     *
     * @return array<string, list<array{number:?string,title:string,title_fa:?string,title_fa_reviewed:bool}>>
     */
    private function editionChapters(string $workspaceId): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT node.edition_id, node.node_key, node.number, node.title, node.title_fa, node.title_fa_origin,
       (SELECT COUNT(*) FROM bank_reference_nodes child WHERE child.parent_id = node.id) AS sections
FROM bank_reference_nodes node
WHERE node.workspace_id = :workspace AND node.parent_id IS NULL AND node.kind = 'chapter'
SQL);
        $query->execute(['workspace' => $workspaceId]);
        $byEdition = [];
        foreach ($query->fetchAll() as $row) {
            $byEdition[(string) $row['edition_id']][] = [
                'key' => (string) $row['node_key'],
                'sections' => (int) $row['sections'],
                'number' => $row['number'] === null ? null : (string) $row['number'],
                'title' => (string) $row['title'],
                'title_fa' => $row['title_fa'] === null ? null : (string) $row['title_fa'],
                'title_fa_reviewed' => $row['title_fa_origin'] === 'human',
            ];
        }
        foreach ($byEdition as &$list) {
            usort($list, static fn (array $a, array $b): int => strnatcmp((string) $a['number'], (string) $b['number']));
        }
        unset($list);

        return $byEdition;
    }

    /**
     * One chapter's headings (sections and the subsections under them), in
     * book order, fetched when a reader opens the chapter on منابع آزمون.
     *
     * @return array{chapter:array<string,mixed>,sections:list<array<string,mixed>>}
     */
    public function chapterOutline(string $userId, string $workspaceId, string $editionRef, string $chapterKey): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        [$referenceKey, $editionKey] = array_pad(explode('@', $editionRef, 2), 2, '');
        $chapter = $this->database->prepare(<<<'SQL'
SELECT node.id, node.number, node.title, node.title_fa, node.title_fa_origin
FROM bank_reference_nodes node
JOIN bank_reference_editions edition ON edition.id = node.edition_id
JOIN bank_references reference ON reference.id = edition.reference_id
WHERE node.workspace_id = :workspace AND reference.reference_key = :reference AND edition.edition_key = :edition
  AND node.node_key = :chapter AND node.parent_id IS NULL
SQL);
        $chapter->execute(['workspace' => $workspaceId, 'reference' => $referenceKey, 'edition' => $editionKey, 'chapter' => $chapterKey]);
        $row = $chapter->fetch();
        if ($row === false) {
            throw new PlatformException('bank_chapter_not_found', 'That chapter is not in the catalog.', 404);
        }
        $children = $this->database->prepare(<<<'SQL'
SELECT id, parent_id, title, title_fa, title_fa_origin, sort_order FROM bank_reference_nodes
WHERE workspace_id = :workspace AND (parent_id = :chapter OR parent_id IN (SELECT id FROM bank_reference_nodes WHERE parent_id = :chapter_again))
ORDER BY sort_order
SQL);
        $children->execute(['workspace' => $workspaceId, 'chapter' => $row['id'], 'chapter_again' => $row['id']]);
        $node = static fn (array $r): array => [
            'title' => (string) $r['title'],
            'title_fa' => $r['title_fa'] === null ? null : (string) $r['title_fa'],
            'title_fa_reviewed' => $r['title_fa_origin'] === 'human',
        ];
        $sections = [];
        $rows = $children->fetchAll();
        foreach ($rows as $r) {
            if ($r['parent_id'] === $row['id']) {
                $sections[(string) $r['id']] = $node($r) + ['subsections' => []];
            }
        }
        foreach ($rows as $r) {
            if (isset($sections[(string) $r['parent_id']])) {
                $sections[(string) $r['parent_id']]['subsections'][] = $node($r);
            }
        }

        return ['chapter' => ['number' => $row['number'] === null ? null : (string) $row['number']] + $node($row), 'sections' => array_values($sections)];
    }

    /**
     * Marks each chapter in or out of the year's announced scope
     * (in_scope null when the announcement names no chapter list), with the
     * announcement's own words when it covers only part of the chapter.
     *
     * @param list<array<string, mixed>> $chapters
     * @return list<array<string, mixed>>
     */
    private static function markScope(array $chapters, mixed $scopeJson): array
    {
        $scope = is_string($scopeJson) ? json_decode($scopeJson, true) : null;
        $named = [];
        foreach (is_array($scope) ? $scope : [] as $entry) {
            if (is_array($entry) && isset($entry['number'])) {
                $named[(string) $entry['number']] = isset($entry['partial']) ? (string) $entry['partial'] : null;
            }
        }

        return array_map(static fn (array $chapter): array => $chapter + [
            'in_scope' => is_array($scope) ? array_key_exists((string) $chapter['number'], $named) : null,
            'partial' => $named[(string) $chapter['number']] ?? null,
        ], $chapters);
    }

    /**
     * Published questions, newest year first and in paper order, each with
     * the topic (its top-level concept) and concept it is filed under.
     *
     * @return list<array{key:string,subject_key:string,sitting_id:string,year:int,round:int,number:int,topic_key:?string,topic_name:?string,topic_name_en:?string,concept_key:?string,concept_name:?string,concept_name_en:?string,old_reference:bool}>
     */
    private function questions(string $workspaceId, ?string $subjectId): array
    {
        $concepts = $this->concepts($workspaceId);
        $filter = $subjectId === null ? '' : 'AND question.subject_id = :subject';
        $query = $this->database->prepare(<<<SQL
SELECT question.id, question.question_key, question.number_in_sitting, subject.subject_key,
       sitting.id AS sitting_id, sitting.exam_year, sitting.exam_round,
       (SELECT link.concept_id FROM bank_question_concepts link
        WHERE link.question_id = question.id AND (link.origin = 'human' OR link.confidence >= :threshold_concept)
        ORDER BY link.is_primary DESC, link.confidence DESC LIMIT 1) AS concept_id,
       (SELECT currency.status FROM bank_question_currency currency
        WHERE currency.question_id = question.id AND (currency.origin = 'human' OR currency.confidence >= :threshold_currency)
        ORDER BY currency.created_at DESC, currency.id DESC LIMIT 1) AS currency
FROM bank_questions question
JOIN bank_subjects subject ON subject.id = question.subject_id
JOIN bank_exam_sittings sitting ON sitting.id = question.sitting_id
WHERE question.workspace_id = :workspace AND question.status = 'published' AND sitting.assessment_id IS NOT NULL {$filter}
ORDER BY sitting.exam_year DESC, sitting.exam_round DESC, question.number_in_sitting
SQL);
        $parameters = ['workspace' => $workspaceId, 'threshold_concept' => self::CONFIDENCE_THRESHOLD, 'threshold_currency' => self::CONFIDENCE_THRESHOLD];
        if ($subjectId !== null) {
            $parameters['subject'] = $subjectId;
        }
        $query->execute($parameters);

        $rows = [];
        foreach ($query->fetchAll() as $row) {
            $concept = $row['concept_id'] === null ? null : ($concepts[(string) $row['concept_id']] ?? null);
            $topic = $concept;
            // Walk up to the top-level topic (the tree is at most three deep).
            for ($guard = 0; $topic !== null && $topic['parent_id'] !== null && $guard < 5; ++$guard) {
                $topic = $concepts[$topic['parent_id']] ?? null;
            }
            $rows[] = [
                'key' => (string) $row['question_key'],
                'subject_key' => (string) $row['subject_key'],
                'sitting_id' => (string) $row['sitting_id'],
                'year' => (int) $row['exam_year'],
                'round' => (int) $row['exam_round'],
                'number' => (int) $row['number_in_sitting'],
                'topic_key' => $topic['key'] ?? null,
                'topic_name' => $topic['name'] ?? null,
                'topic_name_en' => $topic['name_en'] ?? null,
                'concept_key' => $concept['key'] ?? null,
                'concept_name' => $concept['name'] ?? null,
                'concept_name_en' => $concept['name_en'] ?? null,
                'old_reference' => in_array($row['currency'], self::OLD_REFERENCE, true),
            ];
        }

        return $rows;
    }

    /**
     * Topics and concepts, each named in Persian with its English name beside
     * it. A topic that is a book chapter and was filed under its English title
     * takes the chapter's Persian title from the reference catalog.
     *
     * @return array<string, array{key:string,name:string,name_en:?string,parent_id:?string}>
     */
    private function concepts(string $workspaceId): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT concept.id, concept.concept_key, concept.name, concept.name_en, concept.parent_id,
       (SELECT node.title_fa FROM bank_reference_nodes node
        WHERE node.workspace_id = concept.workspace_id AND node.title = concept.name AND node.title_fa IS NOT NULL
        ORDER BY node.title_fa_origin = 'human' DESC LIMIT 1) AS title_fa
FROM bank_concepts concept
WHERE concept.workspace_id = :workspace
SQL);
        $query->execute(['workspace' => $workspaceId]);
        $concepts = [];
        foreach ($query->fetchAll() as $row) {
            $name = (string) $row['name'];
            $english = $row['name_en'] === null ? null : (string) $row['name_en'];
            if ($row['title_fa'] !== null && $row['title_fa'] !== '') {
                $english ??= $name;
                $name = (string) $row['title_fa'];
            }
            $concepts[(string) $row['id']] = [
                'key' => (string) $row['concept_key'],
                'name' => $name,
                'name_en' => $english !== null && $english !== $name ? $english : null,
                'parent_id' => $row['parent_id'] === null ? null : (string) $row['parent_id'],
            ];
        }

        return $concepts;
    }

    /** @return list<array{id:string,key:string,name:string,name_en:?string}> */
    private function subjects(string $workspaceId): array
    {
        $query = $this->database->prepare('SELECT id, subject_key, name, name_en FROM bank_subjects WHERE workspace_id = :workspace AND parent_id IS NULL ORDER BY sort_order, name');
        $query->execute(['workspace' => $workspaceId]);

        return array_map(static fn (array $row): array => [
            'id' => (string) $row['id'], 'key' => (string) $row['subject_key'], 'name' => (string) $row['name'], 'name_en' => $row['name_en'],
        ], $query->fetchAll());
    }

    /** @return array<string, mixed> */
    private function subjectRow(string $workspaceId, string $subjectKey): array
    {
        $query = $this->database->prepare('SELECT id, subject_key, name, name_en FROM bank_subjects WHERE workspace_id = :workspace AND subject_key = :key');
        $query->execute(['workspace' => $workspaceId, 'key' => $subjectKey]);
        $row = $query->fetch();
        if ($row === false) {
            throw new PlatformException('bank_subject_not_found', 'That subject is not in the bank.', 404);
        }

        return $row;
    }

    /** @return list<array<string, mixed>> */
    private function sittings(string $workspaceId): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT sitting.id, sitting.exam_year, sitting.exam_round, sitting.assessment_id, type.name AS type_name,
       (SELECT COUNT(*) FROM bank_questions question WHERE question.sitting_id = sitting.id AND question.status = 'published') AS questions
FROM bank_exam_sittings sitting
JOIN bank_exam_types type ON type.id = sitting.exam_type_id
WHERE sitting.workspace_id = :workspace
ORDER BY sitting.exam_year DESC, sitting.exam_round DESC, type.sort_order
SQL);
        $query->execute(['workspace' => $workspaceId]);

        return array_map(static fn (array $row): array => [
            'id' => (string) $row['id'],
            'year' => (int) $row['exam_year'],
            'round' => (int) $row['exam_round'],
            'assessment_id' => $row['assessment_id'] === null ? null : (string) $row['assessment_id'],
            'type_name' => (string) $row['type_name'],
            'questions' => (int) $row['questions'],
        ], $query->fetchAll());
    }

    /**
     * The residency references named for this subject in the two latest
     * years that name any, so the student sees what changed. The subject
     * page is the residency bank's; board, promotion and national lists are
     * on منابع آزمون.
     *
     * @return list<array<string, mixed>>
     */
    private function subjectReferences(string $workspaceId, string $subjectId): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT validity.exam_year, reference.title, edition.edition_label, validity.scope
FROM bank_reference_validity validity
JOIN bank_exam_types type ON type.id = validity.exam_type_id AND type.is_active = TRUE AND type.type_key = 'residency'
JOIN bank_reference_editions edition ON edition.id = validity.edition_id
JOIN bank_references reference ON reference.id = edition.reference_id
WHERE validity.workspace_id = :workspace AND validity.subject_id = :subject
ORDER BY validity.exam_year DESC, reference.title
SQL);
        $query->execute(['workspace' => $workspaceId, 'subject' => $subjectId]);
        $years = [];
        foreach ($query->fetchAll() as $row) {
            $year = (int) $row['exam_year'];
            if (!isset($years[$year]) && count($years) === 2) {
                break;
            }
            $years[$year][] = ['title' => (string) $row['title'], 'edition' => (string) $row['edition_label'], 'scope' => $row['scope']];
        }

        return array_map(static fn (int $year, array $references): array => ['year' => $year, 'references' => $references], array_keys($years), array_values($years));
    }

    private static function faDigits(string $value): string
    {
        return strtr($value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }
}
