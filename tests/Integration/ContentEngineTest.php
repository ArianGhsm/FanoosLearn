<?php

declare(strict_types=1);

namespace Fanoos\Tests\Integration;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Content\ContentImportService;
use Fanoos\Platform\Content\ContentService;
use Fanoos\Platform\Content\ContentUploadService;
use Fanoos\Platform\Content\ExamService;
use Fanoos\Platform\Content\ProtectedResourceAuthorizer;
use Fanoos\Platform\Content\SecureDeliveryService;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Migration\LegacyIdMap;
use Fanoos\Platform\Storage\FilesystemObjectStore;
use Fanoos\Platform\Storage\SignedDownloadToken;
use Fanoos\Platform\Storage\UploadInspector;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;

final class ContentEngineTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database, private readonly string $hmacKey)
    {
    }

    public function run(): int
    {
        $fixture = $this->fixture();
        $audit = new AuditLogger($this->database);
        $scopeAuthorizer = new ScopeAuthorizer($this->database);
        $access = new AccessGate($this->database, $scopeAuthorizer);
        $entitlements = new EntitlementService($this->database, $access, $audit);
        $protected = new ProtectedResourceAuthorizer($this->database, $scopeAuthorizer, $entitlements);
        $content = new ContentService($this->database, $access, $protected, $audit);
        $exams = new ExamService($this->database, $access, $scopeAuthorizer, $entitlements, $audit);

        $this->createScopedOperators($fixture);
        $courses = $this->sameNamedCourses($fixture);

        $resourceA = $content->createResource(
            $fixture['manager'], $fixture['workspace_a'], 'lecture_note', 'منبع مشترک',
            ['blocks' => [['kind' => 'paragraph', 'text' => 'نسخه نخست جزوه']]],
            ['course_id' => $courses['a'], 'term_id' => $fixture['term_a'], 'topic' => 'جلسه اول', 'format_key' => 'standard', 'access_level' => 'workspace'],
        );
        $resourceB = $content->createResource(
            $fixture['manager'], $fixture['workspace_b'], 'lecture_note', 'منبع مشترک',
            ['blocks' => [['kind' => 'paragraph', 'text' => 'محتوای مستقل فضای دوم']]],
            ['course_id' => $courses['b'], 'term_id' => $fixture['term_b'], 'topic' => 'جلسه اول', 'format_key' => 'standard', 'access_level' => 'workspace'],
        );
        self::assert($resourceA['resource_id'] !== $resourceB['resource_id'], 'Same-name resources collided across tenants.');
        self::assert(count($content->library($fixture['manager'], $fixture['workspace_a'], ['q' => 'منبع مشترک'])) === 1, 'Workspace A library did not isolate the same-name resource.');
        self::assert(count($content->library($fixture['manager'], $fixture['workspace_b'], ['q' => 'منبع مشترک'])) === 1, 'Workspace B library did not isolate the same-name resource.');

        $content->submitForReview($fixture['manager'], $fixture['workspace_a'], $resourceA['resource_id'], $resourceA['version_id']);
        $this->expectPlatformException('self_review_forbidden', fn () => $content->reviewVersion($fixture['manager'], $fixture['workspace_a'], $resourceA['resource_id'], $resourceA['version_id'], 'approved'));
        $content->reviewVersion($fixture['reviewer'], $fixture['workspace_a'], $resourceA['resource_id'], $resourceA['version_id'], 'approved', 'بازبینی شد');
        $content->publishVersion($fixture['manager'], $fixture['workspace_a'], $resourceA['resource_id'], $resourceA['version_id']);
        $view = $content->view($fixture['student'], $fixture['workspace_a'], $resourceA['resource_id']);
        self::assert(($view['content']['blocks'][0]['text'] ?? null) === 'نسخه نخست جزوه', 'Authorized structured resource view failed.');
        $this->expectPlatformException('resource_access_denied', fn () => $content->view($fixture['outsider'], $fixture['workspace_a'], $resourceA['resource_id']));

        $versionTwo = $content->addStructuredVersion(
            $fixture['manager'], $fixture['workspace_a'], $resourceA['resource_id'],
            ['blocks' => [['kind' => 'paragraph', 'text' => 'نسخه دوم جزوه']]],
        );
        self::assert($versionTwo['version_no'] === 2, 'Content version number did not advance.');
        $content->submitForReview($fixture['manager'], $fixture['workspace_a'], $resourceA['resource_id'], $versionTwo['version_id']);
        $content->reviewVersion($fixture['reviewer'], $fixture['workspace_a'], $resourceA['resource_id'], $versionTwo['version_id'], 'approved');
        $content->publishVersion($fixture['manager'], $fixture['workspace_a'], $resourceA['resource_id'], $versionTwo['version_id']);
        self::assert(($content->view($fixture['student'], $fixture['workspace_a'], $resourceA['resource_id'])['content']['blocks'][0]['text'] ?? null) === 'نسخه دوم جزوه', 'Published content version did not switch atomically.');

        $disciplineNote = $content->deriveResource(
            $fixture['manager'], $fixture['workspace_a'], $resourceA['resource_id'], $versionTwo['version_id'],
            'discipline_note', 'قالب تخصصی نمونه', 'structured_note',
            ['sections' => [['heading' => 'نکته کلیدی', 'body' => 'محتوای ساختاریافته']]],
            ['course_id' => $courses['a'], 'format_key' => 'dentnote', 'access_level' => 'workspace'],
        );
        $summary = $content->deriveResource(
            $fixture['manager'], $fixture['workspace_a'], $resourceA['resource_id'], $versionTwo['version_id'],
            'summary', 'خلاصه منبع', 'summary', ['summary' => 'خلاصه بازبینی‌پذیر'],
            ['course_id' => $courses['a'], 'format_key' => 'standard', 'access_level' => 'workspace'],
        );
        $questionBank = $content->deriveResource(
            $fixture['manager'], $fixture['workspace_a'], $resourceA['resource_id'], $versionTwo['version_id'],
            'question_bank', 'بانک سؤال', 'questions', ['questions' => [['id' => 'qb-1', 'prompt' => 'نمونه سؤال']]],
            ['course_id' => $courses['a'], 'format_key' => 'standard', 'access_level' => 'workspace'],
        );
        $pastExam = $content->createResource(
            $fixture['manager'], $fixture['workspace_a'], 'past_exam', 'آزمون سال گذشته',
            ['questions' => [['id' => 'past-1', 'prompt' => 'نمونه گذشته']]],
            ['course_id' => $courses['a'], 'format_key' => 'standard', 'access_level' => 'workspace'],
        );
        foreach ([$disciplineNote, $summary, $questionBank, $pastExam] as $derived) {
            $content->submitForReview($fixture['manager'], $fixture['workspace_a'], $derived['resource_id'], $derived['version_id']);
            $content->reviewVersion($fixture['reviewer'], $fixture['workspace_a'], $derived['resource_id'], $derived['version_id'], 'approved');
            $content->publishVersion($fixture['manager'], $fixture['workspace_a'], $derived['resource_id'], $derived['version_id']);
        }
        self::assert(count($content->library($fixture['student'], $fixture['workspace_a'], ['type' => 'summary', 'course_id' => $courses['a'], 'sort' => 'title'])) === 1, 'Summary search/filter path failed.');
        self::assert(count($content->library($fixture['student'], $fixture['workspace_a'], ['type' => 'question_bank'])) === 1, 'Question-bank resource path failed.');
        self::assert(count($content->library($fixture['student'], $fixture['workspace_a'], ['type' => 'past_exam'])) === 1, 'Past-exam resource path failed.');

        $deliveryResource = $content->createResource(
            $fixture['global_admin'], $fixture['workspace_a'], 'lecture_note', 'فایل محافظت‌شده',
            ['placeholder' => 'uploaded version follows'],
            ['course_id' => $courses['a'], 'format_key' => 'standard', 'access_level' => 'entitled', 'target_scope_id' => $fixture['scope_a'], 'download_ttl_seconds' => 300],
        );
        $pdfPath = tempnam(sys_get_temp_dir(), 'fanoos-content-');
        if ($pdfPath === false) {
            throw new RuntimeException('Temporary upload fixture could not be created.');
        }
        file_put_contents($pdfPath, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\n");
        $objectRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fanoos-object-' . str_replace('-', '', Uuid::v7());
        $uploads = new ContentUploadService(
            $this->database, $access, new UploadInspector(1024 * 1024),
            new FilesystemObjectStore($objectRoot), $audit,
        );
        $uploaded = $uploads->addUploadedVersion(
            $fixture['global_admin'], $fixture['workspace_a'], $deliveryResource['resource_id'],
            $pdfPath, 'lesson.pdf', 'protected',
        );
        @unlink($pdfPath);
        $content->submitForReview($fixture['global_admin'], $fixture['workspace_a'], $deliveryResource['resource_id'], $uploaded['version_id']);
        $content->reviewVersion($fixture['reviewer'], $fixture['workspace_a'], $deliveryResource['resource_id'], $uploaded['version_id'], 'approved');
        $content->publishVersion($fixture['global_admin'], $fixture['workspace_a'], $deliveryResource['resource_id'], $uploaded['version_id']);
        $delivery = new SecureDeliveryService(
            $this->database, $protected, new SignedDownloadToken(str_repeat('d', 32)),
            $audit, str_repeat('i', 32),
        );
        $issued = $delivery->issue($fixture['student'], $fixture['workspace_a'], $deliveryResource['resource_id'], 'telegram');
        self::assert(($issued['watermark']['forensic_id'] ?? '') !== '' && ($issued['delivery_contract']['forward_protection_required'] ?? false) === true, 'Watermark/forward-protection contract was not issued.');
        $served = $delivery->consume($issued['delivery_token'], $fixture['student'], $fixture['workspace_a']);
        self::assert($served['download_token'] !== null && $served['object_id'] === $uploaded['object_id'], 'Authorized protected object delivery failed.');
        $this->expectPlatformException('delivery_subject_mismatch', fn () => $delivery->consume($issued['delivery_token'], $fixture['outsider'], $fixture['workspace_a']));
        $events = $this->database->prepare('SELECT COUNT(*) FROM content_delivery_events WHERE issuance_id = :issuance');
        $events->execute(['issuance' => $issued['issuance_id']]);
        self::assert((int) $events->fetchColumn() === 2, 'Delivery issuance/access events were not retained.');

        $definition = ['questions' => [
            ['id' => 'q1', 'prompt' => 'دو بعلاوه دو؟', 'choices' => ['سه', 'چهار'], 'answer' => 1, 'explanation' => 'پاسخ چهار است.'],
            ['id' => 'q2', 'prompt' => 'اولین گزینه را انتخاب کن.', 'choices' => ['اول', 'دوم'], 'answer' => 0],
        ]];
        $assessment = $exams->createAssessment(
            $fixture['manager'], $fixture['workspace_a'], 'آزمون آزمایشی', $definition,
            ['assessment_kind' => 'mock_exam', 'course_id' => $courses['a'], 'source_resource_id' => $questionBank['resource_id'], 'target_scope_id' => $fixture['scope_a'], 'requires_entitlement' => true, 'max_attempts' => 2],
        );
        $exams->submitForReview($fixture['manager'], $fixture['workspace_a'], $assessment['assessment_id'], $assessment['version_id']);
        $exams->reviewVersion($fixture['reviewer'], $fixture['workspace_a'], $assessment['assessment_id'], $assessment['version_id'], 'approved');
        $exams->publishVersion($fixture['manager'], $fixture['workspace_a'], $assessment['assessment_id'], $assessment['version_id']);
        self::assert(count($exams->catalog($fixture['student'], $fixture['workspace_a'], $courses['a'], 'mock_exam')) === 1, 'Assessment catalog/filter failed.');
        $attempt = $exams->startAttempt($fixture['student'], $fixture['workspace_a'], $assessment['assessment_id']);
        self::assert(!array_key_exists('answer', $attempt['questions'][0]), 'Correct answer leaked before submission.');
        $saved = $exams->saveProgress($fixture['student'], $fixture['workspace_a'], $attempt['attempt_id'], 1, ['q1' => 1]);
        self::assert($saved['revision'] === 2, 'Attempt progress revision did not advance.');
        $this->expectPlatformException('attempt_revision_conflict', fn () => $exams->saveProgress($fixture['student'], $fixture['workspace_a'], $attempt['attempt_id'], 1, ['q1' => 0]));
        $scored = $exams->submitAttempt($fixture['student'], $fixture['workspace_a'], $attempt['attempt_id'], 2, ['q1' => 1, 'q2' => 0]);
        self::assert($scored['status'] === 'scored' && $scored['score_basis_points'] === 10000, 'Server-side assessment scoring failed.');
        self::assert(count($exams->attemptReview($fixture['student'], $fixture['workspace_a'], $attempt['attempt_id'])['review']) === 2, 'Attempt review result is incomplete.');
        self::assert($exams->analytics($fixture['manager'], $fixture['workspace_a'], $assessment['assessment_id'])['average_score_basis_points'] === 10000, 'Assessment analytics did not use scored attempts.');
        $this->expectPlatformException('assessment_not_found', fn () => $exams->startAttempt($fixture['student'], $fixture['workspace_b'], $assessment['assessment_id']));

        $sourceId = Uuid::v7();
        $sourceKey = 'content-fixture-' . substr(str_replace('-', '', $sourceId), -12);
        $this->execute("INSERT INTO migration_source_systems (id, source_key, description, mode, created_at) VALUES (:id, :key, 'Read-only content fixture', 'read_only', UTC_TIMESTAMP(6))", ['id' => $sourceId, 'key' => $sourceKey]);
        $importer = new ContentImportService(
            $this->database, $access, $content, new LegacyIdMap($this->database, $this->hmacKey),
            $audit, $this->hmacKey,
        );
        $manifest = ['schema_version' => 1, 'items' => [[
            'source_key' => 'resource-001', 'type' => 'summary', 'title' => 'خلاصه واردشده',
            'metadata' => ['course_id' => $courses['a'], 'format_key' => 'standard', 'access_level' => 'workspace'],
            'content' => ['summary' => 'نسخه واردشده'],
        ]]];
        $firstImport = $importer->import($fixture['manager'], $fixture['workspace_a'], $sourceKey, 'batch-1', $manifest);
        self::assert($firstImport['created_count'] === 1 && $firstImport['replayed'] === false, 'Initial content import failed.');
        self::assert($importer->import($fixture['manager'], $fixture['workspace_a'], $sourceKey, 'batch-1', $manifest)['replayed'] === true, 'Same import key was not idempotent.');
        self::assert($importer->import($fixture['manager'], $fixture['workspace_a'], $sourceKey, 'batch-2', $manifest)['duplicate_count'] === 1, 'Duplicate source item was not detected across batches.');
        $manifest['items'][0]['content']['summary'] = 'نسخه اصلاح‌شده';
        self::assert($importer->import($fixture['manager'], $fixture['workspace_a'], $sourceKey, 'batch-3', $manifest)['updated_count'] === 1, 'Changed imported content did not create a new version.');
        $mappingCount = $this->database->prepare("SELECT COUNT(*) FROM migration_legacy_id_mappings WHERE source_system_id = :source AND source_entity_type = 'content_resource'");
        $mappingCount->execute(['source' => $sourceId]);
        self::assert((int) $mappingCount->fetchColumn() === 1, 'Content import did not preserve one stable legacy mapping.');
        $outbox = $this->database->prepare("SELECT COUNT(*) FROM outbox_events WHERE workspace_id = :workspace AND event_type IN ('content.resource.published', 'exam.assessment.published')");
        $outbox->execute(['workspace' => $fixture['workspace_a']]);
        self::assert((int) $outbox->fetchColumn() >= 7, 'Content/exam publication notification hooks were not emitted.');

        return $this->assertions;
    }

    /** @return array<string, string> */
    private function fixture(): array
    {
        $result = [];
        foreach (['A' => 'a', 'B' => 'b'] as $name => $side) {
            $workspace = $this->database->prepare('SELECT id FROM tenant_workspaces WHERE name = :name ORDER BY created_at DESC LIMIT 1');
            $workspace->execute(['name' => 'Fixture Workspace ' . $name]);
            $result['workspace_' . $side] = (string) $workspace->fetchColumn();
            $scope = $this->database->prepare("SELECT id FROM rbac_scopes WHERE scope_type = 'workspace' AND workspace_id = :workspace AND entity_id = :entity_workspace");
            $scope->execute(['workspace' => $result['workspace_' . $side], 'entity_workspace' => $result['workspace_' . $side]]);
            $result['scope_' . $side] = (string) $scope->fetchColumn();
            $term = $this->database->prepare('SELECT id FROM academic_terms WHERE workspace_id = :workspace ORDER BY created_at DESC LIMIT 1');
            $term->execute(['workspace' => $result['workspace_' . $side]]);
            $result['term_' . $side] = (string) $term->fetchColumn();
        }
        foreach (['Multi member' => 'student', 'Global admin' => 'global_admin'] as $name => $key) {
            $user = $this->database->prepare('SELECT id FROM iam_users WHERE display_name = :name ORDER BY created_at DESC LIMIT 1');
            $user->execute(['name' => $name]);
            $result[$key] = (string) $user->fetchColumn();
        }
        $result['outsider'] = Uuid::v7();
        $this->execute("INSERT INTO iam_users (id, display_name, status, locale, created_at, updated_at) VALUES (:id, 'Content outsider fixture', 'active', 'fa-IR', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))", ['id' => $result['outsider']]);
        foreach ($result as $value) {
            self::assert($value !== '', 'Content integration fixture is incomplete.');
        }

        return $result;
    }

    /** @param array<string, string> $fixture */
    private function createScopedOperators(array &$fixture): void
    {
        $fixture['manager'] = Uuid::v7();
        $fixture['reviewer'] = Uuid::v7();
        foreach (['manager' => 'Content manager fixture', 'reviewer' => 'Content reviewer fixture'] as $key => $name) {
            $this->execute("INSERT INTO iam_users (id, display_name, status, locale, created_at, updated_at) VALUES (:id, :name, 'active', 'fa-IR', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))", ['id' => $fixture[$key], 'name' => $name]);
            foreach (['a', 'b'] as $side) {
                $this->execute("INSERT INTO tenant_workspace_memberships (id, workspace_id, user_id, status, joined_at, created_at, updated_at) VALUES (:id, :workspace, :user, 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))", ['id' => Uuid::v7(), 'workspace' => $fixture['workspace_' . $side], 'user' => $fixture[$key]]);
                $role = $key === 'manager' ? 'content-manager' : 'content-reviewer';
                $this->execute(<<<'SQL'
INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at)
SELECT :id, :user, role.id, :scope, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
FROM rbac_role_templates role WHERE role.role_key = :role
SQL, ['id' => Uuid::v7(), 'user' => $fixture[$key], 'scope' => $fixture['scope_' . $side], 'role' => $role]);
            }
        }
    }

    /** @param array<string, string> $fixture @return array{a:string,b:string} */
    private function sameNamedCourses(array $fixture): array
    {
        $suffix = substr(str_replace('-', '', Uuid::v7()), -10);
        $courses = ['a' => Uuid::v7(), 'b' => Uuid::v7()];
        foreach (['a', 'b'] as $side) {
            $this->execute("INSERT INTO academic_courses (id, workspace_id, course_code, title, status, created_at, updated_at) VALUES (:id, :workspace, :code, 'درس هم‌نام', 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))", ['id' => $courses[$side], 'workspace' => $fixture['workspace_' . $side], 'code' => 'CONTENT-' . strtoupper($side) . '-' . $suffix]);
        }
        $count = $this->database->prepare("SELECT COUNT(*) FROM academic_courses WHERE title = 'درس هم‌نام' AND id IN (:course_a, :course_b)");
        $count->execute(['course_a' => $courses['a'], 'course_b' => $courses['b']]);
        self::assert((int) $count->fetchColumn() === 2, 'The same course name was not accepted in separate tenants.');

        return $courses;
    }

    /** @param array<string, scalar|null> $parameters */
    private function execute(string $sql, array $parameters): void
    {
        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);
    }

    private function expectPlatformException(string $code, callable $operation): void
    {
        try {
            $operation();
        } catch (PlatformException $error) {
            self::assert($error->errorCode === $code, "Expected {$code}, received {$error->errorCode}.");
            return;
        }
        throw new RuntimeException("Expected PlatformException {$code}.");
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
