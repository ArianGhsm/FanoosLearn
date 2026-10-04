<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

use Fanoos\Platform\Content\ExamImageStore;
use Fanoos\Platform\Support\Transaction;
use Fanoos\Platform\Support\Uuid;
use PDO;

/**
 * Brings the two bank exchange files into the database
 * (docs/product/06_QUESTION_FORMAT.md):
 *
 * - a **catalog** file — exam types, subjects, concepts, references with
 *   their editions and chapter trees, which edition is official in which
 *   year, and how chapters of different editions correspond;
 * - a **sitting** file — one real exam (type, year, round) and its
 *   questions, each with its choices, official answer, sources, concepts,
 *   structured explanation and currency.
 *
 * Every row is matched by its stable key, so applying the same file again
 * changes nothing and applying a corrected file corrects only what changed.
 * A row a person has reviewed (`reviewed_by_user_id` set) is never
 * overwritten by an import: an AI pass proposes, a review decides.
 *
 * validate() reads only. import() validates first and writes nothing at all
 * when anything is wrong; with $dryRun it writes inside a transaction and
 * rolls back, so the counts are real.
 */
final class BankImporter
{
    public const CATALOG_FORMAT = 'fanoos.bank.catalog/1';
    public const SITTING_FORMAT = 'fanoos.bank.sitting/1';

    public const QUESTION_TYPES = ['recall', 'conceptual', 'clinical_scenario', 'diagnosis', 'treatment_planning', 'image_based', 'calculation'];
    public const COGNITIVE_LEVELS = ['recall', 'understanding', 'application', 'analysis'];
    public const QUESTION_STATUSES = ['draft', 'reviewed', 'published', 'withdrawn'];
    public const ANSWER_STATUSES = ['preliminary', 'final', 'amended', 'disputed', 'voided'];
    public const KEY_STATUSES = ['preliminary', 'final', 'amended'];
    public const CURRENCY = ['current', 'valid_old_edition', 'changed_in_newer', 'outdated', 'contradicted', 'removed_from_syllabus'];
    public const SIMILARITY = ['exact_repeat', 'near_duplicate', 'same_concept'];
    public const MAPPING = ['equivalent', 'partial', 'removed'];
    public const NODE_KINDS = ['part', 'chapter', 'section', 'subsection'];
    public const CONCEPT_LEVELS = ['topic', 'subtopic', 'concept'];
    public const ORIGINS = ['ai', 'human'];

    private const KEY = '/^[a-z0-9][a-z0-9_.-]{0,59}$/';
    private const CONCEPT_KEY = '/^[a-z0-9][a-z0-9_.\/-]{0,159}$/';
    private const NODE_KEY = '/^[a-z0-9][a-z0-9_.-]{0,79}$/';

    /** @var list<string> */
    private array $errors = [];

    public function __construct(
        private readonly PDO $database,
        private readonly ?ExamImageStore $images = null,
    ) {
    }

    /** "residency-1404-1-007" — the question's id everywhere, from the stats to the runner. */
    public static function questionKey(string $examType, int $year, int $round, int $number): string
    {
        return sprintf('%s-%d-%d-%03d', $examType, $year, $round, $number);
    }

    /**
     * @param array<string, mixed> $file
     * @return list<string> problems, each starting with its path in the file
     */
    public function validate(string $workspaceId, array $file, ?string $assetsDir = null): array
    {
        $this->errors = [];
        match ($file['format'] ?? null) {
            self::CATALOG_FORMAT => $this->checkCatalog($workspaceId, $file),
            self::SITTING_FORMAT => $this->checkSitting($workspaceId, $file, $assetsDir),
            default => $this->fail('format', 'must be "' . self::CATALOG_FORMAT . '" or "' . self::SITTING_FORMAT . '"'),
        };

        return $this->errors;
    }

    /**
     * @param array<string, mixed> $file
     * @return array<string, int> what was written, by kind
     */
    public function import(string $workspaceId, array $file, ?string $assetsDir = null, bool $dryRun = false): array
    {
        $errors = $this->validate($workspaceId, $file, $assetsDir);
        if ($errors !== []) {
            throw new BankImportException($errors);
        }
        $counts = [];
        $write = function () use ($workspaceId, $file, $assetsDir, &$counts): void {
            $counts = $file['format'] === self::CATALOG_FORMAT
                ? $this->writeCatalog($workspaceId, $file)
                : $this->writeSitting($workspaceId, $file, $assetsDir);
        };
        if ($dryRun) {
            $this->database->beginTransaction();
            try {
                $write();
            } finally {
                $this->database->rollBack();
            }
        } else {
            Transaction::run($this->database, $write);
        }

        return $counts;
    }

    // ================================================================ checks

    /** @param array<string, mixed> $file */
    private function checkCatalog(string $workspaceId, array $file): void
    {
        $subjects = $this->existingKeys($workspaceId, 'bank_subjects', 'subject_key');
        foreach ($this->list($file, 'subjects') as $i => $subject) {
            $this->requireKey($subject, 'key', self::KEY, "subjects[{$i}]");
            $this->requireText($subject, 'name', 200, "subjects[{$i}]");
            $subjects[(string) ($subject['key'] ?? '')] = true;
        }
        foreach ($this->list($file, 'subjects') as $i => $subject) {
            if (isset($subject['parent']) && !isset($subjects[(string) $subject['parent']])) {
                $this->fail("subjects[{$i}].parent", 'unknown subject "' . $subject['parent'] . '"');
            }
        }
        foreach ($this->list($file, 'exam_types') as $i => $type) {
            $this->requireKey($type, 'key', self::KEY, "exam_types[{$i}]");
            $this->requireText($type, 'name', 120, "exam_types[{$i}]");
        }
        $types = $this->existingKeys($workspaceId, 'bank_exam_types', 'type_key')
            + array_fill_keys(array_map(static fn (array $t): string => (string) ($t['key'] ?? ''), $this->list($file, 'exam_types')), true);

        $concepts = $this->existingKeys($workspaceId, 'bank_concepts', 'concept_key');
        $walk = function (array $nodes, string $path, ?string $parentSubject) use (&$walk, &$concepts, $subjects): void {
            foreach ($nodes as $i => $concept) {
                $here = "{$path}[{$i}]";
                if (!is_array($concept)) {
                    $this->fail($here, 'must be an object');
                    continue;
                }
                $this->requireKey($concept, 'key', self::CONCEPT_KEY, $here);
                $this->requireText($concept, 'name', 200, $here);
                $this->requireEnum($concept, 'level', self::CONCEPT_LEVELS, $here);
                $subject = (string) ($concept['subject'] ?? $parentSubject ?? '');
                if (!isset($subjects[$subject])) {
                    $this->fail("{$here}.subject", 'unknown subject "' . $subject . '"');
                }
                $concepts[(string) ($concept['key'] ?? '')] = true;
                $walk($this->arrayOf($concept, 'children', $here), "{$here}.children", $subject);
            }
        };
        $walk($this->list($file, 'concepts'), 'concepts', null);

        $editions = [];
        $nodes = [];
        foreach ($this->list($file, 'references') as $i => $reference) {
            $here = "references[{$i}]";
            $this->requireKey($reference, 'key', self::KEY, $here);
            $this->requireText($reference, 'title', 300, $here);
            if (isset($reference['subject']) && !isset($subjects[(string) $reference['subject']])) {
                $this->fail("{$here}.subject", 'unknown subject "' . $reference['subject'] . '"');
            }
            foreach ($this->arrayOf($reference, 'editions', $here) as $j => $edition) {
                $at = "{$here}.editions[{$j}]";
                $this->requireKey($edition, 'key', self::KEY, $at);
                $this->requireText($edition, 'label', 80, $at);
                $editionRef = ($reference['key'] ?? '') . '@' . ($edition['key'] ?? '');
                $editions[$editionRef] = true;
                $walkNodes = function (array $list, string $path) use (&$walkNodes, &$nodes, $editionRef, $concepts): void {
                    foreach ($list as $k => $node) {
                        $where = "{$path}[{$k}]";
                        $this->requireKey($node, 'key', self::NODE_KEY, $where);
                        $this->requireEnum($node, 'kind', self::NODE_KINDS, $where);
                        $this->requireText($node, 'title', 300, $where);
                        if (isset($node['title_fa'])) {
                            $this->requireText($node, 'title_fa', 300, $where);
                            $this->requireEnum($node, 'title_fa_origin', ['ai', 'human'], $where);
                        }
                        $pages = $node['pages'] ?? null;
                        if ($pages !== null && (!is_array($pages) || count($pages) !== 2 || !is_int($pages[0]) || !is_int($pages[1]) || $pages[0] > $pages[1])) {
                            $this->fail("{$where}.pages", 'must be [first, last] page numbers');
                        }
                        foreach ($this->arrayOf($node, 'concepts', $where) as $c => $concept) {
                            if (!isset($concepts[(string) $concept])) {
                                $this->fail("{$where}.concepts[{$c}]", 'unknown concept "' . $concept . '"');
                            }
                        }
                        $nodes[$editionRef . '#' . ($node['key'] ?? '')] = true;
                        $walkNodes($this->arrayOf($node, 'children', $where), "{$where}.children");
                    }
                };
                $walkNodes($this->arrayOf($edition, 'nodes', $at), "{$at}.nodes");
            }
        }

        foreach ($this->list($file, 'validity') as $i => $row) {
            $here = "validity[{$i}]";
            if (!isset($types[(string) ($row['exam_type'] ?? '')])) {
                $this->fail("{$here}.exam_type", 'unknown exam type');
            }
            if (!is_int($row['year'] ?? null) || $row['year'] < 1350 || $row['year'] > 1500) {
                $this->fail("{$here}.year", 'must be a Jalali year');
            }
            if (!isset($subjects[(string) ($row['subject'] ?? '')])) {
                $this->fail("{$here}.subject", 'unknown subject');
            }
            if (!isset($editions[(string) ($row['edition'] ?? '')]) && $this->resolveEdition($workspaceId, (string) ($row['edition'] ?? '')) === null) {
                $this->fail("{$here}.edition", 'unknown edition "' . ($row['edition'] ?? '') . '" (reference@edition)');
            }
            foreach ($this->arrayOf($row, 'scope_chapters', $here) as $c => $chapter) {
                $this->requireText($chapter, 'number', 20, "{$here}.scope_chapters[{$c}]");
                if (isset($chapter['partial'])) {
                    $this->requireText($chapter, 'partial', 400, "{$here}.scope_chapters[{$c}]");
                }
            }
        }

        foreach ($this->list($file, 'edition_mappings') as $i => $row) {
            $here = "edition_mappings[{$i}]";
            foreach (['from', 'to'] as $end) {
                $ref = $row[$end] ?? null;
                if ($ref === null && $end === 'to') {
                    continue;
                }
                if (!isset($nodes[(string) $ref]) && $this->resolveNode($workspaceId, (string) $ref) === null) {
                    $this->fail("{$here}.{$end}", 'unknown chapter "' . $ref . '" (reference@edition#node)');
                }
            }
            $this->requireEnum($row, 'relation', self::MAPPING, $here);
            if (($row['relation'] ?? null) === 'removed' && ($row['to'] ?? null) !== null) {
                $this->fail("{$here}.to", 'must be null when the relation is "removed"');
            }
            $this->checkMachineFields($row, $here);
        }
    }

    /** @param array<string, mixed> $file */
    private function checkSitting(string $workspaceId, array $file, ?string $assetsDir): void
    {
        if ($this->lookup($workspaceId, 'bank_exam_types', 'type_key', (string) ($file['exam_type'] ?? '')) === null) {
            $this->fail('exam_type', 'unknown exam type "' . ($file['exam_type'] ?? '') . '" — add it in the catalog first');
        }
        if (!is_int($file['year'] ?? null) || $file['year'] < 1350 || $file['year'] > 1500) {
            $this->fail('year', 'must be a Jalali year, e.g. 1404');
        }
        if (isset($file['round']) && (!is_int($file['round']) || $file['round'] < 1 || $file['round'] > 9)) {
            $this->fail('round', 'must be 1–9');
        }
        if (isset($file['held_on']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $file['held_on']) !== 1) {
            $this->fail('held_on', 'must be a Gregorian date, YYYY-MM-DD');
        }
        if (isset($file['answer_key_status'])) {
            $this->requireEnum($file, 'answer_key_status', self::KEY_STATUSES, '');
        }

        $questions = $this->list($file, 'questions');
        if ($questions === []) {
            $this->fail('questions', 'must hold at least one question');
        }
        $numbers = [];
        foreach ($questions as $i => $question) {
            $here = "questions[{$i}]";
            if (!is_array($question)) {
                $this->fail($here, 'must be an object');
                continue;
            }
            $number = $question['number'] ?? null;
            if (!is_int($number) || $number < 1 || $number > 999) {
                $this->fail("{$here}.number", 'must be the question\'s number in the exam (1–999)');
            } elseif (isset($numbers[$number])) {
                $this->fail("{$here}.number", "{$number} appears twice");
            }
            $numbers[(int) $number] = true;
            if ($this->lookup($workspaceId, 'bank_subjects', 'subject_key', (string) ($question['subject'] ?? '')) === null) {
                $this->fail("{$here}.subject", 'unknown subject "' . ($question['subject'] ?? '') . '"');
            }
            $this->requireText($question, 'stem', 4000, $here);
            $this->checkImage($question['stem_image'] ?? null, "{$here}.stem_image", $assetsDir);

            $choices = $this->choices($question);
            if (count($choices) < 2 || count($choices) > 10) {
                $this->fail("{$here}.choices", 'must hold 2–10 choices');
            }
            foreach ($choices as $c => $choice) {
                if (trim($choice['text']) === '' && $choice['image'] === null) {
                    $this->fail("{$here}.choices[{$c}]", 'needs text or an image');
                }
                if (mb_strlen($choice['text']) > 1000) {
                    $this->fail("{$here}.choices[{$c}]", 'is longer than 1000 characters');
                }
                $this->checkImage($choice['image'], "{$here}.choices[{$c}].image", $assetsDir);
            }

            $answer = $question['answer'] ?? null;
            if (!is_array($answer)) {
                $this->fail("{$here}.answer", 'is required: {"choice": n, "status": "final"}');
            } else {
                $this->requireEnum($answer, 'status', self::ANSWER_STATUSES, "{$here}.answer");
                $choice = $answer['choice'] ?? null;
                if (($answer['status'] ?? null) === 'voided') {
                    if ($choice !== null) {
                        $this->fail("{$here}.answer.choice", 'must be null for a voided question');
                    }
                } elseif (!is_int($choice) || $choice < 1 || $choice > count($choices)) {
                    $this->fail("{$here}.answer.choice", 'must be the 1-based number of a choice');
                }
            }

            if (isset($question['type'])) {
                $this->requireEnum($question, 'type', self::QUESTION_TYPES, $here);
            }
            if (isset($question['cognitive_level'])) {
                $this->requireEnum($question, 'cognitive_level', self::COGNITIVE_LEVELS, $here);
            }
            if (isset($question['status'])) {
                $this->requireEnum($question, 'status', self::QUESTION_STATUSES, $here);
            }
            if (isset($question['difficulty']) && (!is_int($question['difficulty']) || $question['difficulty'] < 1 || $question['difficulty'] > 5)) {
                $this->fail("{$here}.difficulty", 'must be 1–5');
            }

            foreach ($this->arrayOf($question, 'sources', $here) as $s => $source) {
                $at = "{$here}.sources[{$s}]";
                $ref = (string) ($source['ref'] ?? '');
                $resolved = str_contains($ref, '#') ? $this->resolveNode($workspaceId, $ref) : $this->resolveEdition($workspaceId, $ref);
                if ($resolved === null) {
                    $this->fail("{$at}.ref", 'unknown "' . $ref . '" (reference@edition or reference@edition#node)');
                }
                foreach (['source', 'node', 'page'] as $field) {
                    $this->checkConfidence($source['confidence'][$field] ?? null, "{$at}.confidence.{$field}");
                }
                $this->checkOrigin($source, $at);
            }
            foreach ($this->arrayOf($question, 'concepts', $here) as $c => $concept) {
                $at = "{$here}.concepts[{$c}]";
                if ($this->lookup($workspaceId, 'bank_concepts', 'concept_key', (string) ($concept['key'] ?? '')) === null) {
                    $this->fail("{$at}.key", 'unknown concept "' . ($concept['key'] ?? '') . '"');
                }
                $this->checkMachineFields($concept, $at);
            }
            $explanation = $question['explanation'] ?? null;
            if ($explanation !== null) {
                if (!is_array($explanation)) {
                    $this->fail("{$here}.explanation", 'must be an object');
                } else {
                    foreach ((array) ($explanation['why_wrong'] ?? []) as $position => $text) {
                        if (!ctype_digit((string) $position) || (int) $position < 1 || (int) $position > count($choices) || !is_string($text)) {
                            $this->fail("{$here}.explanation.why_wrong", 'keys must be choice numbers, values text');
                            break;
                        }
                    }
                    $this->checkMachineFields($explanation, "{$here}.explanation");
                }
            }
            foreach ($this->arrayOf($question, 'currency', $here) as $c => $row) {
                $at = "{$here}.currency[{$c}]";
                if ($this->resolveEdition($workspaceId, (string) ($row['against'] ?? '')) === null) {
                    $this->fail("{$at}.against", 'unknown edition "' . ($row['against'] ?? '') . '"');
                }
                $this->requireEnum($row, 'status', self::CURRENCY, $at);
                $this->checkMachineFields($row, $at);
            }
        }

        foreach ($this->list($file, 'similar') as $i => $pair) {
            $here = "similar[{$i}]";
            $this->requireEnum($pair, 'relation', self::SIMILARITY, $here);
            $this->checkMachineFields($pair, $here);
            if (($pair['a'] ?? null) === ($pair['b'] ?? null)) {
                $this->fail($here, 'a question cannot be similar to itself');
            }
        }
    }

    // ================================================================ writes

    /**
     * @param array<string, mixed> $file
     * @return array<string, int>
     */
    private function writeCatalog(string $workspaceId, array $file): array
    {
        $counts = ['exam_types' => 0, 'subjects' => 0, 'concepts' => 0, 'references' => 0, 'editions' => 0, 'nodes' => 0, 'validity' => 0, 'edition_mappings' => 0];
        foreach ($this->list($file, 'exam_types') as $i => $type) {
            $this->upsert('bank_exam_types', $workspaceId, ['type_key' => $type['key']], [
                'name' => $type['name'], 'is_active' => ($type['active'] ?? true) ? 1 : 0, 'sort_order' => $type['order'] ?? $i,
            ]);
            ++$counts['exam_types'];
        }
        foreach ($this->list($file, 'subjects') as $i => $subject) {
            $this->upsert('bank_subjects', $workspaceId, ['subject_key' => $subject['key']], [
                'name' => $subject['name'], 'name_en' => $subject['name_en'] ?? null, 'sort_order' => $subject['order'] ?? $i,
            ]);
            ++$counts['subjects'];
        }
        foreach ($this->list($file, 'subjects') as $subject) {
            $parent = isset($subject['parent']) ? $this->lookup($workspaceId, 'bank_subjects', 'subject_key', (string) $subject['parent']) : null;
            $this->database->prepare('UPDATE bank_subjects SET parent_id = :parent WHERE workspace_id = :workspace AND subject_key = :subject')
                ->execute(['parent' => $parent, 'workspace' => $workspaceId, 'subject' => $subject['key']]);
        }

        $writeConcepts = function (array $list, ?string $parentId, ?string $parentSubject) use (&$writeConcepts, $workspaceId, &$counts): void {
            foreach ($list as $concept) {
                $subject = (string) ($concept['subject'] ?? $parentSubject);
                $id = $this->upsert('bank_concepts', $workspaceId, ['concept_key' => $concept['key']], [
                    'subject_id' => $this->lookup($workspaceId, 'bank_subjects', 'subject_key', $subject),
                    'parent_id' => $parentId, 'level' => $concept['level'],
                    'name' => $concept['name'], 'name_en' => $concept['name_en'] ?? null,
                ]);
                ++$counts['concepts'];
                $writeConcepts($concept['children'] ?? [], $id, $subject);
            }
        };
        $writeConcepts($this->list($file, 'concepts'), null, null);

        foreach ($this->list($file, 'references') as $reference) {
            $referenceId = $this->upsert('bank_references', $workspaceId, ['reference_key' => $reference['key']], [
                'title' => $reference['title'], 'authors' => $reference['authors'] ?? null,
                'subject_id' => isset($reference['subject']) ? $this->lookup($workspaceId, 'bank_subjects', 'subject_key', (string) $reference['subject']) : null,
            ]);
            ++$counts['references'];
            foreach ($reference['editions'] ?? [] as $edition) {
                $editionId = $this->upsert('bank_reference_editions', $workspaceId, ['reference_id' => $referenceId, 'edition_key' => $edition['key']], [
                    'edition_label' => $edition['label'], 'published_year' => $edition['year'] ?? null, 'isbn' => $edition['isbn'] ?? null,
                ]);
                ++$counts['editions'];
                $writeNodes = function (array $list, ?string $parentId) use (&$writeNodes, $workspaceId, $editionId, &$counts): void {
                    foreach ($list as $order => $node) {
                        $values = [
                            'parent_id' => $parentId, 'kind' => $node['kind'], 'number' => isset($node['number']) ? (string) $node['number'] : null,
                            'title' => $node['title'], 'page_start' => $node['pages'][0] ?? null, 'page_end' => $node['pages'][1] ?? null,
                            'sort_order' => $order,
                        ];
                        // A file without Persian titles (a question import's chapters) keeps the ones already stored.
                        if (isset($node['title_fa'])) {
                            $values += ['title_fa' => $node['title_fa'], 'title_fa_origin' => $node['title_fa_origin']];
                        }
                        $id = $this->upsert('bank_reference_nodes', $workspaceId, ['edition_id' => $editionId, 'node_key' => $node['key']], $values);
                        ++$counts['nodes'];
                        $this->database->prepare('DELETE FROM bank_node_concepts WHERE node_id = :node')->execute(['node' => $id]);
                        foreach ($node['concepts'] ?? [] as $conceptKey) {
                            $this->database->prepare('INSERT IGNORE INTO bank_node_concepts (workspace_id, node_id, concept_id) VALUES (:workspace, :node, :concept)')
                                ->execute(['workspace' => $workspaceId, 'node' => $id, 'concept' => $this->lookup($workspaceId, 'bank_concepts', 'concept_key', (string) $conceptKey)]);
                        }
                        $writeNodes($node['children'] ?? [], $id);
                    }
                };
                $writeNodes($edition['nodes'] ?? [], null);
            }
        }

        foreach ($this->list($file, 'validity') as $row) {
            $this->upsert('bank_reference_validity', $workspaceId, [
                'exam_type_id' => $this->lookup($workspaceId, 'bank_exam_types', 'type_key', (string) $row['exam_type']),
                'exam_year' => $row['year'],
                'subject_id' => $this->lookup($workspaceId, 'bank_subjects', 'subject_key', (string) $row['subject']),
                'edition_id' => $this->resolveEdition($workspaceId, (string) $row['edition']),
            ], [
                'is_official' => ($row['official'] ?? true) ? 1 : 0,
                'scope' => isset($row['scope']) ? mb_substr((string) $row['scope'], 0, 1000) : null,
                'scope_chapters' => isset($row['scope_chapters']) ? json_encode($row['scope_chapters'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
                'evidence' => isset($row['evidence']) ? mb_substr((string) $row['evidence'], 0, 400) : null,
                'source_document' => isset($row['source_document']) ? mb_substr((string) $row['source_document'], 0, 400) : null,
                'recorded_at' => $this->now(),
            ], false);
            ++$counts['validity'];
        }

        foreach ($this->list($file, 'edition_mappings') as $row) {
            $from = $this->resolveNode($workspaceId, (string) $row['from']);
            $to = isset($row['to']) ? $this->resolveNode($workspaceId, (string) $row['to']) : null;
            if ($this->reviewedExists('bank_edition_mappings', ['workspace_id' => $workspaceId, 'from_node_id' => $from, 'to_node_id' => $to])) {
                continue;
            }
            $this->upsert('bank_edition_mappings', $workspaceId, ['from_node_id' => $from, 'to_node_id' => $to], [
                'relation' => $row['relation'], 'note' => $row['note'] ?? null,
                'confidence' => $row['confidence'] ?? null, 'origin' => $row['origin'] ?? 'human',
            ], false);
            ++$counts['edition_mappings'];
        }

        return $counts;
    }

    /**
     * @param array<string, mixed> $file
     * @return array<string, int>
     */
    private function writeSitting(string $workspaceId, array $file, ?string $assetsDir): array
    {
        $counts = ['questions' => 0, 'questions_changed' => 0, 'answers_recorded' => 0, 'sources' => 0, 'concepts' => 0, 'explanations' => 0, 'currency' => 0, 'similar' => 0, 'images' => 0];
        $type = (string) $file['exam_type'];
        $year = (int) $file['year'];
        $round = (int) ($file['round'] ?? 1);
        $typeId = $this->lookup($workspaceId, 'bank_exam_types', 'type_key', $type);
        $sittingId = $this->upsert('bank_exam_sittings', $workspaceId, ['exam_type_id' => $typeId, 'exam_year' => $year, 'exam_round' => $round], [
            'held_on' => $file['held_on'] ?? null,
            'question_count' => count($file['questions']),
            'answer_key_status' => $file['answer_key_status'] ?? 'preliminary',
        ]);

        foreach ($file['questions'] as $question) {
            $key = self::questionKey($type, $year, $round, (int) $question['number']);
            $choices = $this->choices($question);
            $stemImage = $this->storeImage($question['stem_image'] ?? null, $assetsDir, $counts);
            foreach ($choices as $c => $choice) {
                $choices[$c]['image'] = $this->storeImage($choice['image'], $assetsDir, $counts);
            }

            $existing = $this->database->prepare('SELECT id, stem, stem_image, version FROM bank_questions WHERE workspace_id = :workspace AND question_key = :key');
            $existing->execute(['workspace' => $workspaceId, 'key' => $key]);
            $row = $existing->fetch() ?: null;
            $fingerprint = hash('sha256', json_encode([$question['stem'], $stemImage, $choices], JSON_UNESCAPED_UNICODE));
            $changed = $row === null || hash('sha256', json_encode([$row['stem'], $row['stem_image'], $this->storedChoices((string) $row['id'])], JSON_UNESCAPED_UNICODE)) !== $fingerprint;

            $questionId = $this->upsert('bank_questions', $workspaceId, ['question_key' => $key], [
                'sitting_id' => $sittingId, 'number_in_sitting' => $question['number'],
                'subject_id' => $this->lookup($workspaceId, 'bank_subjects', 'subject_key', (string) $question['subject']),
                'stem' => $question['stem'], 'stem_image' => $stemImage,
                'question_type' => $question['type'] ?? 'recall',
                'is_negative_stem' => ($question['negative_stem'] ?? false) ? 1 : 0,
                'is_multiple_statement' => ($question['multiple_statement'] ?? false) ? 1 : 0,
                'cognitive_level' => $question['cognitive_level'] ?? null,
                'expert_difficulty' => $question['difficulty'] ?? null,
                'version' => $row === null ? 1 : ((int) $row['version'] + ($changed ? 1 : 0)),
            ] + (isset($question['status']) ? ['status' => $question['status']] : []));
            ++$counts['questions'];
            if ($row !== null && $changed) {
                ++$counts['questions_changed'];
            }
            if ($changed) {
                $this->database->prepare('DELETE FROM bank_question_choices WHERE question_id = :question')->execute(['question' => $questionId]);
                foreach ($choices as $c => $choice) {
                    $this->database->prepare('INSERT INTO bank_question_choices (workspace_id, question_id, position, text, image) VALUES (:workspace, :question, :position, :text, :image)')
                        ->execute(['workspace' => $workspaceId, 'question' => $questionId, 'position' => $c + 1, 'text' => $choice['text'], 'image' => $choice['image']]);
                }
            }

            // The official key keeps its history: a new row only when it changed.
            $answer = $question['answer'];
            $latest = $this->database->prepare('SELECT choice_position, status FROM bank_official_answers WHERE question_id = :question ORDER BY recorded_at DESC, id DESC LIMIT 1');
            $latest->execute(['question' => $questionId]);
            $current = $latest->fetch() ?: null;
            $choice = $answer['choice'] ?? null;
            if ($current === null || (int) $current['choice_position'] !== (int) $choice || $current['status'] !== $answer['status'] || ($current['choice_position'] === null) !== ($choice === null)) {
                $this->database->prepare('INSERT INTO bank_official_answers (id, workspace_id, question_id, choice_position, status, source, recorded_at) VALUES (:id, :workspace, :question, :choice, :status, :source, :at)')
                    ->execute(['id' => Uuid::v7(), 'workspace' => $workspaceId, 'question' => $questionId, 'choice' => $choice, 'status' => $answer['status'], 'source' => $answer['source'] ?? null, 'at' => $this->now()]);
                ++$counts['answers_recorded'];
            }

            // Sources and concepts: unreviewed rows are replaced, reviewed ones stay.
            $this->database->prepare('DELETE FROM bank_question_sources WHERE question_id = :question AND reviewed_by_user_id IS NULL')->execute(['question' => $questionId]);
            foreach ($question['sources'] ?? [] as $source) {
                $ref = (string) $source['ref'];
                $nodeId = str_contains($ref, '#') ? $this->resolveNode($workspaceId, $ref) : null;
                $editionId = $this->resolveEdition($workspaceId, explode('#', $ref)[0]);
                if ($this->reviewedExists('bank_question_sources', ['question_id' => $questionId, 'edition_id' => $editionId, 'node_id' => $nodeId])) {
                    continue;
                }
                $this->database->prepare(<<<'SQL'
INSERT INTO bank_question_sources (id, workspace_id, question_id, edition_id, node_id, page, table_ref, figure_ref, box_ref, anchor_text, is_primary,
    confidence_source, confidence_node, confidence_page, origin, created_at)
VALUES (:id, :workspace, :question, :edition, :node, :page, :table_ref, :figure, :box, :anchor, :primary, :c_source, :c_node, :c_page, :origin, :at)
SQL)->execute([
                    'id' => Uuid::v7(), 'workspace' => $workspaceId, 'question' => $questionId, 'edition' => $editionId, 'node' => $nodeId,
                    'page' => isset($source['page']) ? (string) $source['page'] : null, 'table_ref' => $source['table'] ?? null,
                    'figure' => $source['figure'] ?? null, 'box' => $source['box'] ?? null, 'anchor' => $source['anchor'] ?? null,
                    'primary' => ($source['primary'] ?? true) ? 1 : 0,
                    'c_source' => $source['confidence']['source'] ?? null, 'c_node' => $source['confidence']['node'] ?? null,
                    'c_page' => $source['confidence']['page'] ?? null, 'origin' => $source['origin'] ?? 'ai', 'at' => $this->now(),
                ]);
                ++$counts['sources'];
            }
            $this->database->prepare('DELETE FROM bank_question_concepts WHERE question_id = :question AND reviewed_by_user_id IS NULL')->execute(['question' => $questionId]);
            foreach ($question['concepts'] ?? [] as $concept) {
                $conceptId = $this->lookup($workspaceId, 'bank_concepts', 'concept_key', (string) $concept['key']);
                $this->database->prepare('INSERT IGNORE INTO bank_question_concepts (workspace_id, question_id, concept_id, is_primary, confidence, origin) VALUES (:workspace, :question, :concept, :primary, :confidence, :origin)')
                    ->execute(['workspace' => $workspaceId, 'question' => $questionId, 'concept' => $conceptId, 'primary' => ($concept['primary'] ?? true) ? 1 : 0, 'confidence' => $concept['confidence'] ?? null, 'origin' => $concept['origin'] ?? 'ai']);
                ++$counts['concepts'];
            }

            if (isset($question['explanation']) && $this->writeExplanation($workspaceId, $questionId, $question['explanation'])) {
                ++$counts['explanations'];
            }

            foreach ($question['currency'] ?? [] as $row) {
                $editionId = $this->resolveEdition($workspaceId, (string) $row['against']);
                if ($this->reviewedExists('bank_question_currency', ['question_id' => $questionId, 'against_edition_id' => $editionId])) {
                    continue;
                }
                $this->upsert('bank_question_currency', $workspaceId, ['question_id' => $questionId, 'against_edition_id' => $editionId], [
                    'status' => $row['status'], 'note' => $row['note'] ?? null, 'confidence' => $row['confidence'] ?? null, 'origin' => $row['origin'] ?? 'ai',
                ], false);
                ++$counts['currency'];
            }
        }

        foreach ($this->list($file, 'similar') as $pair) {
            $a = $this->lookup($workspaceId, 'bank_questions', 'question_key', (string) $pair['a']);
            $b = $this->lookup($workspaceId, 'bank_questions', 'question_key', (string) $pair['b']);
            if ($a === null || $b === null) {
                throw new BankImportException(['similar: "' . $pair['a'] . '" or "' . $pair['b'] . '" is not in the bank']);
            }
            [$a, $b] = strcmp($a, $b) < 0 ? [$a, $b] : [$b, $a];
            if ($this->reviewedExists('bank_question_similarity', ['question_a_id' => $a, 'question_b_id' => $b])) {
                continue;
            }
            $this->upsert('bank_question_similarity', $workspaceId, ['question_a_id' => $a, 'question_b_id' => $b], [
                'relation' => $pair['relation'], 'confidence' => $pair['confidence'] ?? null, 'origin' => $pair['origin'] ?? 'ai',
            ], false);
            ++$counts['similar'];
        }

        return $counts;
    }

    /**
     * A new version when the text changed and the current one is not
     * reviewed; a reviewed explanation is left alone.
     *
     * @param array<string, mixed> $explanation
     */
    private function writeExplanation(string $workspaceId, string $questionId, array $explanation): bool
    {
        $current = $this->database->prepare('SELECT id, version, short_answer, reference_explanation, source_location, exam_tip, common_trap, reviewed_by_user_id FROM bank_explanations WHERE question_id = :question AND is_current = TRUE');
        $current->execute(['question' => $questionId]);
        $row = $current->fetch() ?: null;
        $fields = [
            'short_answer' => $explanation['short'] ?? null,
            'reference_explanation' => $explanation['reference'] ?? null,
            'source_location' => $explanation['location'] ?? null,
            'exam_tip' => $explanation['tip'] ?? null,
            'common_trap' => $explanation['trap'] ?? null,
        ];
        $whyWrong = [];
        foreach ((array) ($explanation['why_wrong'] ?? []) as $position => $text) {
            $whyWrong[(int) $position] = (string) $text;
        }
        ksort($whyWrong);
        if ($row !== null) {
            if ($row['reviewed_by_user_id'] !== null) {
                return false;
            }
            $stored = $this->database->prepare('SELECT position, why_wrong FROM bank_explanation_choices WHERE explanation_id = :explanation ORDER BY position');
            $stored->execute(['explanation' => $row['id']]);
            $storedWhy = array_map('strval', array_column($stored->fetchAll(), 'why_wrong', 'position'));
            $same = $storedWhy == $whyWrong;
            foreach ($fields as $name => $value) {
                $same = $same && $row[$name] === $value;
            }
            if ($same) {
                return false;
            }
            $this->database->prepare('UPDATE bank_explanations SET is_current = FALSE WHERE id = :id')->execute(['id' => $row['id']]);
        }
        $id = Uuid::v7();
        $this->database->prepare(<<<'SQL'
INSERT INTO bank_explanations (id, workspace_id, question_id, version, is_current, short_answer, reference_explanation, source_location, exam_tip, common_trap, confidence, origin, created_at)
VALUES (:id, :workspace, :question, :version, TRUE, :short_answer, :reference_explanation, :source_location, :exam_tip, :common_trap, :confidence, :origin, :at)
SQL)->execute($fields + [
            'id' => $id, 'workspace' => $workspaceId, 'question' => $questionId, 'version' => $row === null ? 1 : (int) $row['version'] + 1,
            'confidence' => $explanation['confidence'] ?? null, 'origin' => $explanation['origin'] ?? 'ai', 'at' => $this->now(),
        ]);
        foreach ($whyWrong as $position => $text) {
            $this->database->prepare('INSERT INTO bank_explanation_choices (workspace_id, explanation_id, position, why_wrong) VALUES (:workspace, :explanation, :position, :text)')
                ->execute(['workspace' => $workspaceId, 'explanation' => $id, 'position' => $position, 'text' => $text]);
        }

        return true;
    }

    // ================================================================ helpers

    /**
     * Insert or update the row matched by $match; returns its id. Tables
     * without updated_at (validity, mappings, currency, similarity) pass
     * $hasUpdated = false.
     *
     * @param array<string, mixed> $match
     * @param array<string, mixed> $values
     */
    private function upsert(string $table, string $workspaceId, array $match, array $values, bool $hasUpdated = true): string
    {
        $where = ['workspace_id = :m_workspace'];
        $params = ['m_workspace' => $workspaceId];
        foreach ($match as $column => $value) {
            if ($value === null) {
                $where[] = "{$column} IS NULL";
            } else {
                $where[] = "{$column} = :m_{$column}";
                $params["m_{$column}"] = $value;
            }
        }
        $find = $this->database->prepare("SELECT id FROM {$table} WHERE " . implode(' AND ', $where));
        $find->execute($params);
        $id = $find->fetchColumn();
        if ($id !== false) {
            $set = [];
            $update = ['id' => $id];
            foreach ($values as $column => $value) {
                $set[] = "{$column} = :v_{$column}";
                $update["v_{$column}"] = $value;
            }
            if ($hasUpdated) {
                $set[] = 'updated_at = :v_updated_at';
                $update['v_updated_at'] = $this->now();
            }
            if ($set !== []) {
                $this->database->prepare("UPDATE {$table} SET " . implode(', ', $set) . ' WHERE id = :id')->execute($update);
            }

            return (string) $id;
        }
        $id = Uuid::v7();
        $row = ['id' => $id, 'workspace_id' => $workspaceId] + $match + $values + ['created_at' => $this->now()];
        if ($hasUpdated) {
            $row['updated_at'] = $this->now();
        }
        if ($table === 'bank_reference_validity') {
            unset($row['created_at']);
        }
        $columns = array_keys($row);
        $this->database->prepare("INSERT INTO {$table} (" . implode(', ', $columns) . ') VALUES (:' . implode(', :', $columns) . ')')->execute($row);

        return $id;
    }

    /** @param array<string, mixed> $match */
    private function reviewedExists(string $table, array $match): bool
    {
        $where = ['reviewed_by_user_id IS NOT NULL'];
        $params = [];
        foreach ($match as $column => $value) {
            if ($value === null) {
                $where[] = "{$column} IS NULL";
            } else {
                $where[] = "{$column} = :{$column}";
                $params[$column] = $value;
            }
        }
        $query = $this->database->prepare("SELECT 1 FROM {$table} WHERE " . implode(' AND ', $where) . ' LIMIT 1');
        $query->execute($params);

        return $query->fetchColumn() !== false;
    }

    private function lookup(string $workspaceId, string $table, string $column, string $key): ?string
    {
        if ($key === '') {
            return null;
        }
        $query = $this->database->prepare("SELECT id FROM {$table} WHERE workspace_id = :workspace AND {$column} = :key");
        $query->execute(['workspace' => $workspaceId, 'key' => $key]);
        $id = $query->fetchColumn();

        return $id === false ? null : (string) $id;
    }

    /** "torabinejad@6e" -> edition id */
    private function resolveEdition(string $workspaceId, string $ref): ?string
    {
        if (preg_match('/^([^@#]+)@([^@#]+)$/', $ref, $m) !== 1) {
            return null;
        }
        $query = $this->database->prepare(<<<'SQL'
SELECT edition.id FROM bank_reference_editions edition
JOIN bank_references reference ON reference.id = edition.reference_id
WHERE edition.workspace_id = :workspace AND reference.reference_key = :reference AND edition.edition_key = :edition
SQL);
        $query->execute(['workspace' => $workspaceId, 'reference' => $m[1], 'edition' => $m[2]]);
        $id = $query->fetchColumn();

        return $id === false ? null : (string) $id;
    }

    /** "torabinejad@6e#ch6.s2" -> node id */
    private function resolveNode(string $workspaceId, string $ref): ?string
    {
        if (preg_match('/^([^@#]+@[^@#]+)#(.+)$/', $ref, $m) !== 1) {
            return null;
        }
        $edition = $this->resolveEdition($workspaceId, $m[1]);
        if ($edition === null) {
            return null;
        }
        $query = $this->database->prepare('SELECT id FROM bank_reference_nodes WHERE edition_id = :edition AND node_key = :node');
        $query->execute(['edition' => $edition, 'node' => $m[2]]);
        $id = $query->fetchColumn();

        return $id === false ? null : (string) $id;
    }

    /** @return array<string, true> */
    private function existingKeys(string $workspaceId, string $table, string $column): array
    {
        $query = $this->database->prepare("SELECT {$column} FROM {$table} WHERE workspace_id = :workspace");
        $query->execute(['workspace' => $workspaceId]);

        return array_fill_keys(array_map('strval', $query->fetchAll(PDO::FETCH_COLUMN)), true);
    }

    /**
     * Choices as written: a list of strings, or of {"text", "image"}.
     *
     * @param array<string, mixed> $question
     * @return list<array{text:string,image:?string}>
     */
    private function choices(array $question): array
    {
        $out = [];
        foreach ((array) ($question['choices'] ?? []) as $choice) {
            $out[] = is_array($choice)
                ? ['text' => (string) ($choice['text'] ?? ''), 'image' => isset($choice['image']) ? (string) $choice['image'] : null]
                : ['text' => (string) $choice, 'image' => null];
        }

        return $out;
    }

    /** @return list<array{text:string,image:?string}> */
    private function storedChoices(string $questionId): array
    {
        $query = $this->database->prepare('SELECT text, image FROM bank_question_choices WHERE question_id = :question ORDER BY position');
        $query->execute(['question' => $questionId]);

        return array_map(static fn (array $row): array => ['text' => (string) $row['text'], 'image' => $row['image'] === null ? null : (string) $row['image']], $query->fetchAll());
    }

    /**
     * An image named in the file (a path relative to the assets folder),
     * stored content-addressed; returns its key.
     *
     * @param array<string, int> $counts
     */
    private function storeImage(?string $path, ?string $assetsDir, array &$counts): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        if (preg_match(ExamImageStore::KEY_PATTERN, $path) === 1) {
            return $path;
        }
        if ($this->images === null || $assetsDir === null) {
            throw new BankImportException(["image \"{$path}\": no image storage or assets folder configured"]);
        }
        ++$counts['images'];

        return $this->images->put((string) file_get_contents(rtrim($assetsDir, '/\\') . '/' . $path));
    }

    private function checkImage(mixed $path, string $where, ?string $assetsDir): void
    {
        if ($path === null || $path === '') {
            return;
        }
        if (!is_string($path)) {
            $this->fail($where, 'must be a file path');
            return;
        }
        if (preg_match(ExamImageStore::KEY_PATTERN, $path) === 1) {
            return;
        }
        if (str_contains($path, '..') || $assetsDir === null) {
            $this->fail($where, $assetsDir === null ? 'images need --assets=<folder>' : 'must stay inside the assets folder');
            return;
        }
        $full = rtrim($assetsDir, '/\\') . '/' . $path;
        if (!is_file($full)) {
            $this->fail($where, "file \"{$path}\" not found in the assets folder");
        } elseif (ExamImageStore::sniff((string) file_get_contents($full, false, null, 0, 16)) === null) {
            $this->fail($where, "\"{$path}\" is not a JPEG, PNG or WebP image");
        }
    }

    /** @param array<string, mixed> $row */
    private function checkMachineFields(array $row, string $where): void
    {
        $this->checkConfidence($row['confidence'] ?? null, "{$where}.confidence");
        $this->checkOrigin($row, $where);
    }

    private function checkConfidence(mixed $value, string $where): void
    {
        if ($value !== null && (!is_int($value) && !is_float($value) || $value < 0 || $value > 1)) {
            $this->fail($where, 'must be a number from 0 to 1');
        }
    }

    /** @param array<string, mixed> $row */
    private function checkOrigin(array $row, string $where): void
    {
        if (isset($row['origin'])) {
            $this->requireEnum($row, 'origin', self::ORIGINS, $where);
        }
    }

    /** @param array<string, mixed> $row */
    private function requireKey(mixed $row, string $field, string $pattern, string $where): void
    {
        $value = is_array($row) ? ($row[$field] ?? null) : null;
        if (!is_string($value) || preg_match($pattern, $value) !== 1) {
            $this->fail(ltrim("{$where}.{$field}", '.'), 'must be a lowercase key (letters, digits, - _ .)');
        }
    }

    private function requireText(mixed $row, string $field, int $max, string $where): void
    {
        $value = is_array($row) ? ($row[$field] ?? null) : null;
        if (!is_string($value) || trim($value) === '' || mb_strlen($value) > $max) {
            $this->fail(ltrim("{$where}.{$field}", '.'), "is required text (at most {$max} characters)");
        }
    }

    /** @param list<string> $allowed */
    private function requireEnum(mixed $row, string $field, array $allowed, string $where): void
    {
        $value = is_array($row) ? ($row[$field] ?? null) : null;
        if (!in_array($value, $allowed, true)) {
            $this->fail(ltrim("{$where}.{$field}", '.'), 'must be one of: ' . implode(', ', $allowed));
        }
    }

    /**
     * @param array<string, mixed> $file
     * @return list<array<string, mixed>>
     */
    private function list(array $file, string $field): array
    {
        $value = $file[$field] ?? [];
        if (!is_array($value) || !array_is_list($value)) {
            $this->fail($field, 'must be a list');
            return [];
        }

        return $value;
    }

    /** @return list<mixed> */
    private function arrayOf(mixed $row, string $field, string $where): array
    {
        $value = is_array($row) ? ($row[$field] ?? []) : [];
        if (!is_array($value) || !array_is_list($value)) {
            $this->fail("{$where}.{$field}", 'must be a list');
            return [];
        }

        return $value;
    }

    private function fail(string $where, string $problem): void
    {
        $this->errors[] = "{$where}: {$problem}";
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }
}
