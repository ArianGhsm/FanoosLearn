<?php

declare(strict_types=1);

namespace Fanoos\Tests\Integration;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Bank\BankBrowseService;
use Fanoos\Platform\Bank\BankImporter;
use Fanoos\Platform\Bank\BankImportException;
use Fanoos\Platform\Bank\BankPublisher;
use Fanoos\Platform\Content\CustomPracticeService;
use Fanoos\Platform\Content\ExamQuestionRateGuard;
use Fanoos\Platform\Content\ExamScheduleService;
use Fanoos\Platform\Content\ExamService;
use Fanoos\Platform\Content\QuestionToolsService;
use Fanoos\Platform\Content\StudyPlanService;
use Fanoos\Platform\Core\ClassProvisioningService;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;

/**
 * The dental bank, end to end with the real example files in
 * contracts/bank/examples: check, import, re-import, an amended answer, a
 * reviewed row an import must not touch, publishing as an exam, taking it,
 * and a voided question left out of the next version.
 */
final class BankImportTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database, private readonly string $root)
    {
    }

    public function run(): int
    {
        $f = $this->fixture();
        $ws = $f['workspace'];
        $importer = new BankImporter($this->database);
        $catalog = $this->example('catalog');
        $sitting = $this->example('sitting');

        // A sitting cannot come before the catalog it refers to.
        $early = $importer->validate($ws, $sitting);
        $this->assert($this->mentions($early, 'exam_type') && $this->mentions($early, 'questions[0].subject'), 'A sitting was accepted before its catalog: ' . json_encode($early));

        $counts = $importer->import($ws, $catalog);
        $this->assert($counts['exam_types'] === 3 && $counts['concepts'] === 4 && $counts['editions'] === 2 && $counts['nodes'] === 3, 'Catalog counts: ' . json_encode($counts));
        $before = $this->rows($ws);
        $importer->import($ws, $catalog);
        $this->assert($this->rows($ws) === $before, 'Importing the same catalog twice created rows.');
        $board = $this->database->prepare("SELECT is_active FROM bank_exam_types WHERE workspace_id = :ws AND type_key = 'board'");
        $board->execute(['ws' => $ws]);
        $this->assert((int) $board->fetchColumn() === 0, 'Board should exist but be inactive.');

        // Every problem is reported with its path, and nothing is written.
        $broken = $sitting;
        $broken['questions'][0]['answer']['choice'] = 9;
        $broken['questions'][1]['number'] = 1;
        $broken['questions'][1]['concepts'][0]['key'] = 'endodontics/no-such-concept';
        $broken['questions'][1]['sources'][0]['confidence']['node'] = 1.4;
        $broken['questions'][0]['sources'][0]['pdf_page'] = 0;
        $broken['questions'][1]['sources'][0]['pdf_sha256'] = str_repeat('a', 64); // a file named without its page
        $broken['questions'][0]['explanation']['from']['ref'] = 'no-such@1e';
        $problems = $importer->validate($ws, $broken);
        foreach (['questions[0].answer.choice', 'questions[1].number', 'questions[1].concepts[0].key', 'questions[1].sources[0].confidence.node',
            'questions[0].sources[0].pdf_page', 'questions[1].sources[0].pdf_sha256', 'questions[0].explanation.from.ref'] as $path) {
            $this->assert($this->mentions($problems, $path), "No problem reported at {$path}: " . json_encode($problems));
        }
        try {
            $importer->import($ws, $broken);
            throw new RuntimeException('A broken file was imported.');
        } catch (BankImportException) {
            ++$this->assertions;
        }
        $this->assert($this->count('bank_questions', $ws) === 0, 'A refused import wrote questions.');

        // The real thing.
        $counts = $importer->import($ws, $sitting);
        $this->assert($counts['questions'] === 2 && $counts['answers_recorded'] === 2 && $counts['explanations'] === 2, 'Sitting counts: ' . json_encode($counts));
        $key = BankImporter::questionKey('residency', 1404, 1, 1);
        $this->assert($key === 'residency-1404-1-001', 'Question keys are not type-year-round-number.');
        $this->assert($this->scalar('SELECT COUNT(*) FROM bank_question_choices c JOIN bank_questions q ON q.id = c.question_id WHERE q.question_key = :key', ['key' => $key]) === 4, 'Choices were not stored.');
        $this->assert($this->scalar('SELECT COUNT(*) FROM bank_explanation_choices e JOIN bank_explanations x ON x.id = e.explanation_id JOIN bank_questions q ON q.id = x.question_id WHERE q.question_key = :key', ['key' => $key]) === 3, 'Why-wrong reasons were not stored.');

        // The exact book page: PDF page and printed label apart, and the page an explanation was written from.
        $pages = $this->database->prepare('SELECT s.pdf_page, s.printed_page, s.page FROM bank_question_sources s JOIN bank_questions q ON q.id = s.question_id WHERE q.question_key = :key');
        $pages->execute(['key' => $key]);
        $this->assert($pages->fetch(PDO::FETCH_ASSOC) == ['pdf_page' => 271, 'printed_page' => '256', 'page' => '256'], 'The source page fields were not stored apart.');
        $this->assert($this->scalar('SELECT x.source_pdf_page FROM bank_explanations x JOIN bank_questions q ON q.id = x.question_id WHERE q.question_key = :key AND x.is_current = TRUE', ['key' => $key]) === 271, 'The explanation does not record the page it was written from.');

        // A file without the source list (a wording fix, a new key) leaves the classification alone.
        $bare = $sitting;
        foreach ($bare['questions'] as &$bareQuestion) {
            unset($bareQuestion['sources'], $bareQuestion['concepts']);
        }
        unset($bareQuestion);
        $sourcesBefore = $this->count('bank_question_sources', $ws);
        $importer->import($ws, $bare);
        $this->assert($this->count('bank_question_sources', $ws) === $sourcesBefore && $sourcesBefore > 0, 'Re-importing a sitting without sources erased its sources.');

        $again = $importer->import($ws, $sitting);
        $this->assert($again['answers_recorded'] === 0 && $again['questions_changed'] === 0 && $again['explanations'] === 0, 'Re-importing an unchanged sitting changed something: ' . json_encode($again));

        // A heading the catalog no longer lists is pruned; one a question cites is kept.
        $extra = $catalog;
        $extra['references'][0]['editions'][0]['nodes'][] = ['key' => 'ch15', 'kind' => 'chapter', 'number' => '15', 'title' => 'Obturation'];
        $importer->import($ws, $extra);
        $withExtra = $this->count('bank_reference_nodes', $ws);
        $dry = $importer->pruneNodes($ws, $catalog, true);
        $this->assert($dry === ['stale' => 1, 'deleted' => 1, 'kept_in_use' => 0, 'editions_deleted' => 0, 'references_deleted' => 0], 'Prune dry run: ' . json_encode($dry));
        $this->assert($this->count('bank_reference_nodes', $ws) === $withExtra, 'A prune dry run deleted rows.');
        $pruned = $importer->pruneNodes($ws, $catalog);
        $this->assert($pruned['deleted'] === 1 && $this->count('bank_reference_nodes', $ws) === $withExtra - 1, 'The unlisted chapter was not pruned: ' . json_encode($pruned));
        $bare = $catalog;
        unset($bare['references'][0]['editions'][0]['nodes'][0]['children']);
        $kept = $importer->pruneNodes($ws, $bare);
        $this->assert($kept === ['stale' => 1, 'deleted' => 0, 'kept_in_use' => 1, 'editions_deleted' => 0, 'references_deleted' => 0], 'A section a question cites was pruned: ' . json_encode($kept));
        // An edition the catalog no longer names goes with its chapters and year rows.
        $oldEdition = $catalog;
        $oldEdition['references'][0]['editions'][] = ['key' => '4e', 'label' => '4th edition', 'year' => 2009, 'nodes' => [['key' => 'ch12', 'kind' => 'chapter', 'number' => '12', 'title' => 'Cleaning and Shaping']]];
        $oldEdition['validity'][] = ['exam_type' => 'residency', 'year' => 1390, 'subject' => 'endodontics', 'edition' => 'torabinejad@4e', 'official' => true];
        $importer->import($ws, $oldEdition);
        $validityBefore = $this->count('bank_reference_validity', $ws);
        $gone = $importer->pruneNodes($ws, $catalog);
        $this->assert($gone['editions_deleted'] === 1 && $gone['deleted'] === 1 && $this->count('bank_reference_validity', $ws) === $validityBefore - 1,
            'An edition the catalog dropped was not removed with its chapter and year row: ' . json_encode($gone));
        // So does a whole reference the catalog no longer has.
        $oldReference = $catalog;
        $oldReference['references'][] = ['key' => 'old-english-reader', 'title' => 'An English Reader', 'subject' => 'endodontics',
            'editions' => [['key' => '2014', 'label' => '2014', 'year' => 2014]]];
        $oldReference['validity'][] = ['exam_type' => 'residency', 'year' => 1391, 'subject' => 'endodontics', 'edition' => 'old-english-reader@2014', 'official' => true];
        $importer->import($ws, $oldReference);
        $referencesBefore = $this->count('bank_references', $ws);
        $dropped = $importer->pruneNodes($ws, $catalog);
        $this->assert($dropped['references_deleted'] === 1 && $dropped['editions_deleted'] === 1 && $this->count('bank_references', $ws) === $referencesBefore - 1,
            'A reference the catalog no longer has was not removed: ' . json_encode($dropped));

        // The real catalog built from the reference workbook imports cleanly.
        $real = json_decode((string) file_get_contents($this->root . '/data/bank/catalog.json'), true, 64, JSON_THROW_ON_ERROR);
        $problems = $importer->validate($ws, $real);
        $this->assert($problems === [], 'data/bank/catalog.json does not import: ' . json_encode(array_slice($problems, 0, 5), JSON_UNESCAPED_UNICODE));
        $importer->import($ws, $real);
        $endo = $this->database->prepare(<<<'SQL'
SELECT edition.edition_key, validity.scope FROM bank_reference_validity validity
JOIN bank_subjects subject ON subject.id = validity.subject_id
JOIN bank_reference_editions edition ON edition.id = validity.edition_id
JOIN bank_references reference ON reference.id = edition.reference_id
WHERE validity.workspace_id = :ws AND validity.exam_year = 1405 AND subject.subject_key = 'endodontics' AND reference.reference_key = 'torabinejad-endodontics'
SQL);
        $endo->execute(['ws' => $ws]);
        $row = $endo->fetch();
        $this->assert($row !== false && $row['edition_key'] === '6e' && $row['scope'] !== null, 'The 1405 endodontics reference is not Torabinejad 6e with its scope.');
        $this->assert($this->count('bank_reference_validity', $ws) >= 150, 'The real catalog lost its year-by-year references.');
        $this->assert($this->count('bank_reference_nodes', $ws) >= 1000, 'The real catalog lost its chapter lists.');

        // Promotion specialty papers in one year must not collide with each other.
        $promotion = $sitting;
        $promotion['exam_type'] = 'promotion';
        $promotion['year'] = 1405;
        $promotion['round'] = 10;
        foreach ($promotion['questions'] as &$promotionQuestion) {
            $promotionQuestion['subject'] = 'endodontics';
            unset($promotionQuestion['sources'], $promotionQuestion['concepts'], $promotionQuestion['explanation'], $promotionQuestion['currency']);
        }
        unset($promotionQuestion);
        $this->assert($importer->validate($ws, $promotion) === [], 'Promotion round 10 must validate when every question has the same specialty.');
        $mixedPromotion = $promotion;
        $mixedPromotion['questions'][1]['subject'] = 'orthodontics';
        $this->assert($this->mentions($importer->validate($ws, $mixedPromotion), 'questions[1].subject'), 'Promotion sitting mixed specialties.');
        $importer->import($ws, $promotion);
        $this->assert($this->scalar('SELECT COUNT(*) FROM bank_questions WHERE workspace_id = :ws AND question_key LIKE :prefix', ['ws' => $ws, 'prefix' => 'promotion-1405-10-%']) === 2, 'Promotion specialty slot keys missing.');
        $this->assert($this->scalar("SELECT is_active FROM bank_exam_types WHERE workspace_id = :ws AND type_key = 'promotion'", ['ws' => $ws]) == 1, 'Promotion not active from real catalog.');

        // Board papers in the same year also require independent specialty slots.
        $board = $promotion;
        $board['exam_type'] = 'board';
        $board['year'] = 1404;
        $this->assert($importer->validate($ws, $board) === [], 'Board round 10 must validate when all questions have the same specialty.');
        $mixedBoard = $board;
        $mixedBoard['questions'][1]['subject'] = 'orthodontics';
        $this->assert($this->mentions($importer->validate($ws, $mixedBoard), 'questions[1].subject'), 'Board sitting mixed specialties.');
        $importer->import($ws, $board);
        $this->assert($this->scalar('SELECT COUNT(*) FROM bank_questions WHERE workspace_id = :ws AND question_key LIKE :prefix', ['ws' => $ws, 'prefix' => 'board-1404-10-%']) === 2, 'Board specialty slot keys missing.');
        $this->assert($this->scalar("SELECT is_active FROM bank_exam_types WHERE workspace_id = :ws AND type_key = 'board'", ['ws' => $ws]) == 1, 'Board not active in catalog.');

        // A reviewed source survives a later AI pass.
        $this->database->prepare("UPDATE bank_question_sources s JOIN bank_questions q ON q.id = s.question_id SET s.reviewed_by_user_id = :user, s.reviewed_at = UTC_TIMESTAMP(6), s.page = '257' WHERE q.question_key = :key")
            ->execute(['user' => $f['reviewer'], 'key' => $key]);

        // The official key is amended: history, not overwrite.
        $amended = $sitting;
        $amended['questions'][0]['answer'] = ['choice' => 3, 'status' => 'amended', 'source' => 'اصلاحیه'];
        $amended['questions'][0]['sources'][0]['page'] = '999';
        $result = $importer->import($ws, $amended);
        $this->assert($result['answers_recorded'] === 1, 'An amended answer was not recorded.');
        $this->assert($this->scalar('SELECT COUNT(*) FROM bank_official_answers a JOIN bank_questions q ON q.id = a.question_id WHERE q.question_key = :key', ['key' => $key]) === 2, 'The answer history was overwritten.');
        $page = $this->database->prepare('SELECT s.page FROM bank_question_sources s JOIN bank_questions q ON q.id = s.question_id WHERE q.question_key = :key');
        $page->execute(['key' => $key]);
        $this->assert($page->fetchAll(PDO::FETCH_COLUMN) === ['257'], 'An import overwrote a reviewed source.');

        // Publish, take, review.
        $publisher = new BankPublisher($this->database, $this->exams());
        $published = $publisher->publish($ws, 'residency', 1404, 1, $f['manager'], $f['reviewer'], ['time_limit_minutes' => 30]);
        $this->assert($published['new_exam'] === true && $published['questions'] === 2 && $published['left_out'] === [], 'Publish: ' . json_encode($published));
        $exams = $this->exams();
        $entry = $exams->catalogEntry($f['student'], $ws, $published['assessment_id']);
        $this->assert($entry !== null && str_contains((string) $entry['title'], '۱۴۰۴') && $entry['assessment_kind'] === 'past_exam', 'The published exam is not in the catalogue as a past exam.');
        $attempt = $exams->startAttempt($f['student'], $ws, $published['assessment_id']);
        $answers = [];
        foreach ([1, 2] as $position) {
            $id = (string) $exams->readQuestion($f['student'], $ws, $attempt['attempt_id'], $position)['question']['id'];
            $answers[$id] = $id === $key ? 2 : 1; // the amended answer (choice 3) and choice 2
        }
        $scored = $exams->submitAttempt($f['student'], $ws, $attempt['attempt_id'], 1, $answers);
        $this->assert($scored['correct_count'] === 2, 'The published answers do not match the bank: ' . json_encode($scored));
        $review = null;
        foreach ([1, 2] as $position) {
            $entry = $exams->attemptReviewQuestion($f['student'], $ws, $attempt['attempt_id'], $position);
            if ($entry['question_id'] === $key) {
                $review = $entry;
            }
        }
        $explanation = (string) ($review['explanation'] ?? '');
        $this->assert(str_contains($explanation, 'پاسخ: گزینه‌ی ج') && str_contains($explanation, 'چرا بقیه نه') && str_contains($explanation, 'منبع'), 'The review does not carry the structured explanation: ' . $explanation);

        // The bank browser: by subject, topics most-asked first, and a study set in year order.
        $access = new AccessGate($this->database, new ScopeAuthorizer($this->database));
        $audit = new AuditLogger($this->database);
        $browse = new BankBrowseService($this->database, $access, new CustomPracticeService($this->database, $access, new EntitlementService($this->database, $access, $audit), $audit));
        $overview = $browse->overview($f['student'], $ws);
        $endodontics = array_values(array_filter($overview['subjects'], static fn (array $s): bool => $s['key'] === 'endodontics'))[0] ?? null;
        $this->assert($overview['total'] === 2 && $endodontics !== null && $endodontics['total'] === 2 && $endodontics['per_exam'] === 2 && $endodontics['first_year'] === 1404, 'Overview: ' . json_encode($overview, JSON_UNESCAPED_UNICODE));
        $this->assert(count($overview['sittings']) === 1 && $overview['sittings'][0]['assessment_id'] === $published['assessment_id'], 'The published sitting is not listed by year.');
        // «آزمون من»: one exam type narrows the bank; another type's view is empty, and an unknown type is refused.
        $sittingType = $overview['sittings'][0]['type_key'];
        $narrowed = $browse->overview($f['student'], $ws, $sittingType);
        $this->assert($narrowed['type'] === $sittingType && $narrowed['total'] === 2 && in_array($sittingType, array_column($overview['types'], 'key'), true), 'The bank does not narrow to an exam type.');
        $other = array_values(array_diff(array_column($overview['types'], 'key'), [$sittingType]))[0] ?? null;
        if ($other !== null) {
            $empty = $browse->overview($f['student'], $ws, $other);
            $this->assert($empty['total'] === 0 && $empty['sittings'] === [] && count($empty['types']) === count($overview['types']), 'Another exam type still shows this paper.');
            $this->assert($browse->subject($f['student'], $ws, 'endodontics', $other)['total'] === 0, 'A subject narrowed to another exam type still counts this paper.');
        }
        try {
            $browse->overview($f['student'], $ws, 'no-such-exam');
            $this->assert(false, 'An unknown exam type was accepted.');
        } catch (PlatformException $e) {
            $this->assert($e->errorCode === 'bank_type_not_found', 'An unknown exam type gave ' . $e->errorCode);
        }
        $subject = $browse->subject($f['student'], $ws, 'endodontics');
        $this->assert($subject['topics'][0]['key'] === 'endodontics/cleaning-and-shaping' && $subject['topics'][0]['total'] === 2 && count($subject['high_yield']) === 2, 'Subject topics: ' . json_encode($subject['topics'], JSON_UNESCAPED_UNICODE));
        $this->assert($subject['references'] !== [], 'The subject page lists no references.');
        // A topic filed under a chapter's English title shows the chapter's Persian title, with the English beside it.
        $node = $this->database->prepare('SELECT id, title FROM bank_reference_nodes WHERE workspace_id = :workspace ORDER BY title LIMIT 1');
        $node->execute(['workspace' => $ws]);
        $node = $node->fetch();
        $this->database->prepare("UPDATE bank_reference_nodes SET title_fa = 'پاک‌سازی و شکل‌دهی', title_fa_origin = 'ai' WHERE id = :id")->execute(['id' => $node['id']]);
        $this->database->prepare("UPDATE bank_concepts SET name = :title WHERE workspace_id = :workspace AND concept_key = 'endodontics/cleaning-and-shaping'")->execute(['title' => $node['title'], 'workspace' => $ws]);
        $named = $browse->subject($f['student'], $ws, 'endodontics')['topics'][0];
        $this->assert($named['name'] === 'پاک‌سازی و شکل‌دهی' && $named['name_en'] !== null, 'The topic is not named in Persian with its English title: ' . json_encode($named, JSON_UNESCAPED_UNICODE));
        $years = $browse->references($f['student'], $ws);
        $this->assert($years[0]['year'] >= 1405, 'References are not newest year first.');
        $this->assert($years[0]['type_key'] === 'residency', 'Residency is not first within the newest year.');
        $types = array_unique(array_column($years, 'type_key'));
        $this->assert(in_array('national', $types, true) && in_array('board', $types, true) && in_array('promotion', $types, true), 'The national, board and promotion lists are missing: ' . implode(',', $types));
        $torabinejad = null;
        foreach ($years[0]['subjects'] as $row) {
            foreach ($row['references'] as $ref) {
                if ($row['key'] === 'endodontics' && str_starts_with($ref['title'], 'Endodontics')) {
                    $torabinejad = $ref;
                }
            }
        }
        $this->assert($torabinejad !== null && count($torabinejad['chapters']) === 22 && $torabinejad['chapters'][0]['number'] === '1' && $torabinejad['chapters'][21]['number'] === '22',
            'The 1405 endodontics reference does not list its 22 chapters in order: ' . json_encode($torabinejad['chapters'] ?? null, JSON_UNESCAPED_UNICODE));
        $this->assert($torabinejad['chapters'][0]['in_scope'] === true && is_string($torabinejad['chapters'][0]['title_fa']) && $torabinejad['chapters'][0]['title_fa_reviewed'] === false,
            'A chapter of a whole-book reference is not in scope with its unreviewed Persian title: ' . json_encode($torabinejad['chapters'][0], JSON_UNESCAPED_UNICODE));
        $cleaning = array_values(array_filter($torabinejad['chapters'], static fn (array $c): bool => $c['number'] === '14'))[0];
        $this->assert($cleaning['sections'] > 0 && $cleaning['key'] === 'ch14' && $torabinejad['edition_ref'] === 'torabinejad-endodontics@6e',
            'The cleaning-and-shaping chapter does not say it has headings: ' . json_encode($cleaning, JSON_UNESCAPED_UNICODE));
        $outline = $browse->chapterOutline($f['student'], $ws, 'torabinejad-endodontics@6e', 'ch14');
        $this->assert(count($outline['sections']) === $cleaning['sections'] && $outline['sections'][0]['title'] === 'Principles of Cleaning and Shaping'
            && array_sum(array_map(static fn (array $s): int => count($s['subsections']), $outline['sections'])) > 0,
            'The chapter outline does not list the book\'s headings in order: ' . json_encode(array_slice($outline['sections'], 0, 3), JSON_UNESCAPED_UNICODE));
        $proffit = null;
        foreach ($years[0]['subjects'] as $row) {
            foreach ($row['references'] as $ref) {
                if ($row['key'] === 'orthodontics' && $ref['title'] === 'Contemporary Orthodontics') {
                    $proffit = $ref;
                }
            }
        }
        $byNumber = array_column($proffit['chapters'] ?? [], null, 'number');
        $this->assert(($byNumber['1']['in_scope'] ?? null) === true && ($byNumber['20']['in_scope'] ?? null) === false && is_string($byNumber['2']['partial'] ?? null),
            'The 1405 orthodontics scope is not marked chapter by chapter: ' . json_encode($proffit['chapters'] ?? null, JSON_UNESCAPED_UNICODE));
        $set = $browse->study($f['student'], $ws, ['subject' => 'endodontics', 'topic' => 'endodontics/cleaning-and-shaping']);
        // بانک به تفکیک کتاب و فصل: the sourced book, its chapter's count, and that chapter as a study set.
        $books = $browse->books($f['student'], $ws);
        $endoBooks = array_values(array_filter($books['subjects'], static fn (array $s): bool => $s['key'] === 'endodontics'))[0]['books'] ?? [];
        $torabinejadBook = array_values(array_filter($endoBooks, static fn (array $b): bool => $b['edition_ref'] === 'torabinejad@6e'))[0] ?? null;
        $ch14 = $torabinejadBook === null ? null : (array_values(array_filter($torabinejadBook['chapters'], static fn (array $c): bool => $c['key'] === 'ch14'))[0] ?? null);
        $this->assert($ch14 !== null && $ch14['questions'] >= 1 && $torabinejadBook['questions'] >= $ch14['questions'], 'The book view does not count chapter 14: ' . json_encode($torabinejadBook, JSON_UNESCAPED_UNICODE));
        $chapterSet = $browse->study($f['student'], $ws, ['edition' => 'torabinejad@6e', 'chapter' => 'ch14']);
        $this->assert($chapterSet['question_count'] === $ch14['questions'], 'A chapter did not open as a study set of its questions.');
        try {
            $browse->study($f['student'], $ws, ['edition' => 'torabinejad@6e', 'chapter' => 'ch99']);
            $this->assert(false, 'An unknown chapter opened.');
        } catch (PlatformException $e) {
            $this->assert($e->errorCode === 'bank_chapter_not_found', 'An unknown chapter gave ' . $e->errorCode);
        }
        $this->assert($set['question_count'] === 2, 'The study set does not hold the topic: ' . json_encode($set, JSON_UNESCAPED_UNICODE));
        $studyAttempt = $exams->startAttempt($f['student'], $ws, $set['assessment_id']);
        $first = (string) $exams->readQuestion($f['student'], $ws, $studyAttempt['attempt_id'], 1)['question']['id'];
        $this->assert($first === $key, 'A study set is not shown in paper order.');

        // A student's tools on a question: bookmark, note, report; the reviewers' queue.
        $practice = new CustomPracticeService($this->database, $access, new EntitlementService($this->database, $access, $audit), $audit);
        $tools = new QuestionToolsService($this->database, $access, $audit, $practice);
        $examId = $published['assessment_id'];
        $tools->setBookmark($f['student'], $ws, $examId, $key, true);
        $tools->setBookmark($f['student'], $ws, $examId, $key, true); // idempotent
        $tools->saveNote($f['student'], $ws, $examId, $key, '  طول کارکرد با آپکس‌یاب  ');
        $state = $tools->forAssessment($f['student'], $ws, $examId);
        $this->assert($state['bookmarks'] === [$key] && ($state['notes'][$key] ?? null) === 'طول کارکرد با آپکس‌یاب', 'Tools state: ' . json_encode($state, JSON_UNESCAPED_UNICODE));
        $saved = $tools->saved($f['student'], $ws);
        $this->assert(count($saved['bookmarks']) === 1 && $saved['bookmarks'][0]['topic'] !== null && !array_key_exists('answer', $saved['bookmarks'][0]), 'Saved list: ' . json_encode($saved, JSON_UNESCAPED_UNICODE));
        $this->assert($tools->studyBookmarks($f['student'], $ws)['question_count'] === 1, 'Bookmarks did not open as a study set.');
        try {
            $tools->setBookmark($f['student'], $ws, $examId, 'residency-1300-1-999', true);
            throw new RuntimeException('A question outside the exam was bookmarked.');
        } catch (PlatformException $error) {
            $this->assert($error->errorCode === 'question_not_found', 'Unexpected error: ' . $error->errorCode);
        }
        $reportId = $tools->report($f['student'], $ws, $examId, $key, 'answer', 'کلید اشتباه است.')['report_id'];
        try {
            $tools->reports($f['student'], $ws);
            throw new RuntimeException('A student read the reports queue.');
        } catch (PlatformException) {
            ++$this->assertions;
        }
        $queue = $tools->reports($f['reviewer'], $ws);
        $this->assert(count($queue) === 1 && $queue[0]['id'] === $reportId && $queue[0]['answer'] !== null, 'Reports queue: ' . json_encode($queue, JSON_UNESCAPED_UNICODE));
        $tools->resolveReport($f['reviewer'], $ws, $reportId, 'resolved', 'اصلاح شد');
        $this->assert($tools->reports($f['reviewer'], $ws) === [] && count($tools->reports($f['reviewer'], $ws, 'resolved')) === 1, 'A resolved report stayed open.');
        $tools->saveNote($f['student'], $ws, $examId, $key, '');

        // هایلایت‌ها: offsets on the account, fragments read back from the exam.
        $saved = $tools->saveHighlights($f['student'], $ws, $examId, $key, [['start' => 4, 'end' => 9], ['start' => 0, 'end' => 5], ['start' => 'x']]);
        $this->assert($saved['ranges'] === [['start' => 0, 'end' => 9]], 'Highlights were not merged: ' . json_encode($saved));
        $this->assert($tools->forAssessment($f['student'], $ws, $examId)['highlights'][$key] === [['start' => 0, 'end' => 9]], 'Highlights were not kept on the account.');
        $marked = $tools->saved($f['student'], $ws)['highlights'];
        $this->assert(count($marked) === 1 && mb_strlen($marked[0]['fragments'][0]) === 9, 'The highlights page did not read the fragment back: ' . json_encode($marked, JSON_UNESCAPED_UNICODE));
        $tools->saveHighlights($f['student'], $ws, $examId, $key, []);
        $this->assert($tools->saved($f['student'], $ws)['highlights'] === [], 'Clearing highlights left them on the page.');
        $this->assert(QuestionToolsService::mergeRanges([['start' => 5, 'end' => 50]], 20) === [['start' => 5, 'end' => 20]], 'A range past the stem was not clamped.');
        $this->assert($tools->forAssessment($f['student'], $ws, $examId)['notes'] === [], 'An emptied note was kept.');

        // تقویم آزمون‌ها: a window that has not opened refuses every start; a
        // closed one refuses exam mode but still allows practice.
        $calendar = new ExamScheduleService($this->database, $access, $audit);
        try {
            $calendar->schedule($f['student'], $ws, $examId, '+1 day', '+2 days');
            throw new RuntimeException('A student scheduled an exam.');
        } catch (PlatformException) {
            ++$this->assertions;
        }
        $calendar->schedule($f['manager'], $ws, $examId, gmdate(DATE_ATOM, time() + 86400), gmdate(DATE_ATOM, time() + 2 * 86400), 'آزمون جامع');
        try {
            $exams->startAttempt($f['student'], $ws, $examId, 'practice');
            throw new RuntimeException('An exam started before its window opened.');
        } catch (PlatformException $error) {
            $this->assert($error->errorCode === 'exam_not_open', 'Unexpected refusal: ' . $error->errorCode);
        }
        $calendar->schedule($f['manager'], $ws, $examId, gmdate(DATE_ATOM, time() - 2 * 86400), gmdate(DATE_ATOM, time() - 86400));
        try {
            $exams->startAttempt($f['student'], $ws, $examId, 'assessment');
            throw new RuntimeException('An exam was sat in exam mode after its window closed.');
        } catch (PlatformException $error) {
            $this->assert($error->errorCode === 'exam_window_closed', 'Unexpected refusal: ' . $error->errorCode);
        }
        $this->assert(isset($exams->startAttempt($f['student'], $ws, $examId, 'practice')['attempt_id']), 'A closed exam could not be practised.');
        $listed = $calendar->calendar($f['student'], $ws);
        $this->assert(count($listed) === 1 && $listed[0]['state'] === 'closed' && $listed[0]['participants'] === 1 && $listed[0]['my_score_percent'] === 100, 'Calendar row: ' . json_encode($listed, JSON_UNESCAPED_UNICODE));
        $calendar->unschedule($f['manager'], $ws, $examId);
        $this->assert($calendar->calendar($f['student'], $ws) === [], 'An unscheduled exam stayed in the calendar.');

        // برنامه‌ی مطالعه: made from the bank's topics, ticked, replaced.
        $plans = new StudyPlanService($this->database, $access, $browse);
        $plan = $plans->create($f['student'], $ws, gmdate('Y-m-d', time() + 30 * 86400), 6);
        $items = array_merge(...array_column($plan['days'], 'items'));
        $this->assert($plan['total'] >= 20 && in_array('topic', array_column($items, 'kind'), true) && in_array('mock', array_column($items, 'kind'), true), 'Plan: ' . json_encode(array_slice($plan['days'], 0, 3), JSON_UNESCAPED_UNICODE));
        $plans->setDone($f['student'], $ws, 1, true);
        $this->assert($plans->plan($f['student'], $ws)['done'] === 1, 'A ticked day was not kept.');
        $plans->create($f['student'], $ws, gmdate('Y-m-d', time() + 60 * 86400), 5);
        $this->assert($plans->plan($f['student'], $ws)['done'] === 0, 'A new plan did not replace the old one.');
        $plans->archive($f['student'], $ws);
        $this->assert($plans->plan($f['student'], $ws) === null, 'An archived plan is still shown.');

        // A voided question leaves the next version; the exam stays the same exam.
        $voided = $amended;
        $voided['questions'][1]['answer'] = ['choice' => null, 'status' => 'voided'];
        $importer->import($ws, $voided);
        $again = $publisher->publish($ws, 'residency', 1404, 1, $f['manager'], $f['reviewer']);
        $this->assert($again['assessment_id'] === $published['assessment_id'] && $again['new_exam'] === false, 'Republishing created a second exam.');
        $this->assert($again['questions'] === 1 && $again['left_out'] === [BankImporter::questionKey('residency', 1404, 1, 2) . ': official answer voided'], 'A voided question was published: ' . json_encode($again));

        // A final key that accepts two options: the question is published and either option scores.
        $two = $amended;
        $two['questions'][1]['answer'] = ['choice' => 2, 'also_correct' => [1], 'status' => 'amended', 'source' => 'کلید نهایی: ۱ و ۲'];
        $bad = $two;
        $bad['questions'][1]['answer']['also_correct'] = [2];
        $this->assert($this->mentions($importer->validate($ws, $bad), 'questions[1].answer.also_correct'), 'An also-correct option equal to the answer was accepted.');
        $this->assert($importer->import($ws, $two)['answers_recorded'] === 1, 'The second accepted option was not recorded.');
        $both = $publisher->publish($ws, 'residency', 1404, 1, $f['manager'], $f['reviewer']);
        $this->assert($both['questions'] === 2 && $both['left_out'] === [], 'A question with two accepted options was left out: ' . json_encode($both));
        $second = BankImporter::questionKey('residency', 1404, 1, 2);
        // A new student each time: a fresh attempt on the version just published.
        foreach ([0 => 1, 1 => 1, 2 => 0] as $choice => $expected) {
            $student = $this->member($ws, 'student');
            $try = $exams->startAttempt($student, $ws, $both['assessment_id']);
            $result = $exams->submitAttempt($student, $ws, $try['attempt_id'], (int) $try['revision'], [$second => $choice]);
            $this->assert($result['correct_count'] === $expected, "Option {$choice} scored wrongly: " . json_encode($result));
        }

        // Two people review an exam; only an installation owner may review their own.
        // The manager is given the reviewer role too, so only the self-review rule refuses.
        $this->grant($ws, $f['manager'], 'content-reviewer');
        $own = $exams->createAssessment($f['manager'], $ws, 'Self review', ['questions' => [['id' => 'q1', 'prompt' => 'p', 'choices' => ['a', 'b'], 'answer' => 0]]]);
        $exams->submitForReview($f['manager'], $ws, $own['assessment_id'], $own['version_id']);
        try {
            $exams->reviewVersion($f['manager'], $ws, $own['assessment_id'], $own['version_id'], 'approved');
            $this->assert(false, 'A content manager approved their own exam.');
        } catch (PlatformException $e) {
            $this->assert($e->errorCode === 'self_review_forbidden', 'Self review failed for the wrong reason: ' . $e->errorCode);
        }
        $byOwner = $publisher->publish($ws, 'residency', 1404, 1, $f['owner'], $f['owner']);
        $this->assert($byOwner['assessment_id'] === $published['assessment_id'] && $byOwner['new_exam'] === false, 'The owner could not publish their own version.');
        $audited = $this->scalar("SELECT COUNT(*) FROM audit_events WHERE action = 'exam.version.approved' AND subject_id = :version AND actor_id = :owner AND JSON_EXTRACT(metadata_json, '$.self_review_by_owner') = true", ['version' => $byOwner['version_id'], 'owner' => $f['owner']]);
        $this->assert($audited === 1, 'The self review by the owner was not audited as such.');

        return $this->assertions;
    }

    /** @return array<string, mixed> */
    private function example(string $name): array
    {
        $file = json_decode((string) file_get_contents($this->root . "/contracts/bank/examples/{$name}.example.json"), true, 64, JSON_THROW_ON_ERROR);

        return $file;
    }

    /** @param list<string> $problems */
    private function mentions(array $problems, string $path): bool
    {
        foreach ($problems as $problem) {
            if (str_starts_with($problem, $path . ':')) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, int> */
    private function rows(string $ws): array
    {
        $out = [];
        foreach (['bank_exam_types', 'bank_subjects', 'bank_concepts', 'bank_references', 'bank_reference_editions', 'bank_reference_nodes', 'bank_node_concepts', 'bank_reference_validity', 'bank_edition_mappings'] as $table) {
            $out[$table] = $this->count($table, $ws);
        }

        return $out;
    }

    private function count(string $table, string $ws): int
    {
        return $this->scalar("SELECT COUNT(*) FROM {$table} WHERE workspace_id = :ws", ['ws' => $ws]);
    }

    /** @param array<string, string> $params */
    private function scalar(string $sql, array $params): int
    {
        $query = $this->database->prepare($sql);
        $query->execute($params);

        return (int) $query->fetchColumn();
    }

    private function exams(): ExamService
    {
        $audit = new AuditLogger($this->database);
        $access = new AccessGate($this->database, new ScopeAuthorizer($this->database));

        return new ExamService($this->database, $access, new ScopeAuthorizer($this->database), new EntitlementService($this->database, $access, $audit), $audit, new ExamQuestionRateGuard($this->database, 1000.0, 1.0));
    }

    /** @return array<string, string> */
    private function fixture(): array
    {
        $suffix = substr(str_replace('-', '', Uuid::v7()), -10);
        $owner = $this->user('Bank Owner');
        $this->database->prepare("INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at) SELECT :id, :user, id, '00000000-0000-7000-8000-000000000001', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6) FROM rbac_role_templates WHERE role_key = 'platform-super-admin'")
            ->execute(['id' => Uuid::v7(), 'user' => $owner]);
        $access = new AccessGate($this->database, new ScopeAuthorizer($this->database));
        $workspace = (new ClassProvisioningService($this->database, $access, new AuditLogger($this->database)))->createClass($owner, [
            'country' => ['code' => 'ZZ', 'name' => 'Fixture Country'],
            'province' => ['name' => 'Bank Province ' . $suffix],
            'city' => ['name' => 'Bank City ' . $suffix],
            'institution' => ['name' => 'Bank University ' . $suffix],
            'faculty' => ['name' => 'Bank Faculty ' . $suffix],
            'program' => ['name' => 'Bank Program ' . $suffix, 'degree_level' => 'professional-doctorate'],
            'cohort' => ['entry_year' => 5907, 'label' => 'Bank Cohort ' . $suffix],
            'workspace' => ['name' => 'Dental Library ' . $suffix],
        ])['workspace_id'];

        return [
            'workspace' => $workspace,
            'owner' => $owner,
            'manager' => $this->member($workspace, 'content-manager'),
            'reviewer' => $this->member($workspace, 'content-reviewer'),
            'student' => $this->member($workspace, 'student'),
        ];
    }

    private function member(string $workspace, string $roleKey): string
    {
        $user = $this->user('Bank ' . $roleKey);
        $this->database->prepare("INSERT INTO tenant_workspace_memberships (id, workspace_id, user_id, status, joined_at, created_at, updated_at) VALUES (:id, :workspace, :user, 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => Uuid::v7(), 'workspace' => $workspace, 'user' => $user]);
        $this->grant($workspace, $user, $roleKey);

        return $user;
    }

    private function grant(string $workspace, string $user, string $roleKey): void
    {
        $this->database->prepare(<<<'SQL'
INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at)
SELECT :id, :user, role.id, scope.id, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
FROM rbac_role_templates role
JOIN rbac_scopes scope ON scope.scope_type = 'workspace' AND scope.entity_id = :workspace AND scope.workspace_id = :workspace_check
WHERE role.role_key = :role
SQL)->execute(['id' => Uuid::v7(), 'user' => $user, 'workspace' => $workspace, 'workspace_check' => $workspace, 'role' => $roleKey]);
    }

    private function user(string $name): string
    {
        $id = Uuid::v7();
        $this->database->prepare("INSERT INTO iam_users (id, display_name, status, locale, created_at, updated_at) VALUES (:id, :name, 'active', 'fa-IR', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => $id, 'name' => $name]);

        return $id;
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
