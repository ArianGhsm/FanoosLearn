<?php

declare(strict_types=1);

namespace Fanoos\Tests\Integration;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Content\ExamImageStore;
use Fanoos\Platform\Content\ExamQuestionRateGuard;
use Fanoos\Platform\Content\ExamService;
use Fanoos\Platform\Core\ClassProvisioningService;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;

/**
 * Questions with pictures: a stem that is only a photo, an option that is
 * only a picture. What a student is sent says only that an image exists;
 * the image itself is handed out only to someone with an attempt of that
 * assessment, and never by its storage key.
 */
final class ExamQuestionImageTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $this->assertImagesAreValidatedWhenAuthored();
        $this->assertStudentsSeeFlagsNotKeysAndOnlyTheirOwnImages();
        $this->assertTheStoreKeepsOnlyRealImagesByContent();

        return $this->assertions;
    }

    private function assertImagesAreValidatedWhenAuthored(): void
    {
        $workspace = $this->workspace($this->suffix());
        $exams = $this->exams();
        $key = str_repeat('a', 64) . '.jpg';

        // A photo-only stem and a picture-only option are accepted.
        $exams->createAssessment($workspace['manager'], $workspace['workspace'], 'تصویری ۱', ['questions' => [
            ['id' => 'q1', 'prompt' => '', 'choices' => ['', 'دو'], 'answer' => 0, 'images' => ['stem' => $key, 'choices' => [$key, null]]],
        ]]);
        ++$this->assertions;

        foreach ([
            'an empty prompt with no stem image' => ['prompt' => '', 'choices' => ['یک', 'دو'], 'images' => ['stem' => null, 'choices' => [$key, null]]],
            'an empty option with no image of its own' => ['prompt' => 'متن', 'choices' => ['', 'دو'], 'images' => ['stem' => $key, 'choices' => [null, null]]],
            'a key that is a path' => ['prompt' => 'متن', 'choices' => ['یک', 'دو'], 'images' => ['stem' => '../../etc/passwd']],
            'choice images that do not line up' => ['prompt' => 'متن', 'choices' => ['یک', 'دو'], 'images' => ['choices' => [$key]]],
        ] as $label => $question) {
            $this->expectRefused($label, fn () => $exams->createAssessment(
                $workspace['manager'], $workspace['workspace'], 'تصویری ' . $label,
                ['questions' => [array_replace(['id' => 'q1', 'answer' => 1], $question)]],
            ));
        }
    }

    private function assertStudentsSeeFlagsNotKeysAndOnlyTheirOwnImages(): void
    {
        $workspace = $this->workspace($this->suffix());
        $exams = $this->exams();
        $stem = str_repeat('b', 64) . '.png';
        $option = str_repeat('c', 64) . '.webp';
        $assessment = $this->assessment($workspace, [
            ['id' => 'q1', 'prompt' => 'این ضایعه چیست؟', 'choices' => ['الف', ''], 'answer' => 1, 'images' => ['stem' => $stem, 'choices' => [null, $option]]],
        ]);

        // Before any attempt, nothing: the images are for people sitting it.
        $this->expectCode('exam_image_not_found', fn () => $exams->questionImage($workspace['student'], $workspace['workspace'], $assessment, 'q1', 'stem'));

        $attempt = $exams->startAttempt($workspace['student'], $workspace['workspace'], $assessment);
        $read = $exams->readQuestion($workspace['student'], $workspace['workspace'], $attempt['attempt_id'], 1);
        $this->assert(($read['question']['images'] ?? null) === ['stem' => true, 'choices' => [false, true]], 'The question did not say which parts have images.');
        $this->assert(!str_contains(json_encode($read, JSON_THROW_ON_ERROR), $stem), 'A storage key reached the student.');

        $this->assert($exams->questionImage($workspace['student'], $workspace['workspace'], $assessment, 'q1', 'stem') === $stem, 'The stem image was not resolved for the student sitting it.');
        $this->assert($exams->questionImage($workspace['student'], $workspace['workspace'], $assessment, 'q1', 'choice-1') === $option, 'The option image was not resolved.');
        $this->expectCode('exam_image_not_found', fn () => $exams->questionImage($workspace['student'], $workspace['workspace'], $assessment, 'q1', 'choice-0'));
        $this->expectCode('exam_image_not_found', fn () => $exams->questionImage($workspace['student'], $workspace['workspace'], $assessment, 'q1', 'choice-../x'));

        // A classmate who has not opened the assessment gets nothing.
        $classmate = $this->assignRole($workspace['workspace'], 'Image Classmate', 'student');
        $this->expectCode('exam_image_not_found', fn () => $exams->questionImage($classmate, $workspace['workspace'], $assessment, 'q1', 'stem'));

        // After submitting, the review and the mistakes review still show it.
        $exams->submitAttempt($workspace['student'], $workspace['workspace'], $attempt['attempt_id'], 1, ['q1' => 0]);
        $review = $exams->attemptReviewQuestion($workspace['student'], $workspace['workspace'], $attempt['attempt_id'], 1);
        $this->assert(($review['images']['stem'] ?? null) === true, 'The review lost the image flags.');
        $mistakes = $exams->mistakesReview($workspace['student'], $workspace['workspace']);
        $this->assert(($mistakes['questions'][0]['images']['choices'] ?? null) === [false, true], 'The mistakes review lost the image flags.');
        $this->assert($exams->questionImage($workspace['student'], $workspace['workspace'], $assessment, 'q1', 'stem') === $stem, 'A submitted attempt no longer reaches its images.');
    }

    private function assertTheStoreKeepsOnlyRealImagesByContent(): void
    {
        $root = sys_get_temp_dir() . '/fanoos-exam-images-' . bin2hex(random_bytes(6));
        $store = new ExamImageStore($root);
        $png = "\x89PNG\r\n\x1A\n" . random_bytes(32);
        $key = $store->put($png);
        $this->assert($key === hash('sha256', $png) . '.png', 'The key is not the content hash.');
        $this->assert($store->put($png) === $key, 'Storing the same bytes twice gave two keys.');
        $staging = glob($root . '/exam-images/*/*/*.tmp');
        $this->assert($staging === [], 'Successful image writes must remove their temporary staging file.');
        $opened = $store->open($key);
        $this->assert($opened['mime'] === 'image/png' && $opened['length'] === strlen($png), 'The stored image did not come back as it went in.');
        fclose($opened['stream']);
        $this->expectCode('exam_image_type_invalid', fn () => $store->put('<?php echo 1;'));
        $this->expectCode('exam_image_not_found', fn () => $store->open('../' . $key));
    }

    private function expectRefused(string $label, callable $operation): void
    {
        ++$this->assertions;
        try {
            $operation();
        } catch (PlatformException $error) {
            if ($error->httpStatus === 422) {
                return;
            }
            throw new RuntimeException("Expected {$label} to be refused with 422, got {$error->errorCode}.");
        }
        throw new RuntimeException("Expected {$label} to be refused.");
    }

    private function expectCode(string $code, callable $operation): void
    {
        ++$this->assertions;
        try {
            $operation();
        } catch (PlatformException $error) {
            if ($error->errorCode === $code) {
                return;
            }
            throw new RuntimeException("Expected {$code}, got {$error->errorCode}: {$error->getMessage()}");
        }
        throw new RuntimeException("Expected PlatformException {$code}.");
    }

    private function suffix(): string
    {
        return substr(str_replace('-', '', Uuid::v7()), -10);
    }

    private function accessGate(): AccessGate
    {
        return new AccessGate($this->database, new ScopeAuthorizer($this->database));
    }

    private function exams(): ExamService
    {
        $audit = new AuditLogger($this->database);
        $authorizer = new ScopeAuthorizer($this->database);
        $access = new AccessGate($this->database, $authorizer);

        return new ExamService($this->database, $access, $authorizer, new EntitlementService($this->database, $access, $audit), $audit, new ExamQuestionRateGuard($this->database, 1000.0, 1.0));
    }

    /** @return array{workspace:string,manager:string,reviewer:string,student:string} */
    private function workspace(string $suffix): array
    {
        $owner = $this->assignRole(null, 'Image Owner ' . $suffix, 'platform-super-admin');
        $class = (new ClassProvisioningService($this->database, $this->accessGate(), new AuditLogger($this->database)))->createClass($owner, [
            'country' => ['code' => 'ZZ', 'name' => 'Fixture Country'],
            'province' => ['name' => 'Image Province ' . $suffix],
            'city' => ['name' => 'Image City ' . $suffix],
            'institution' => ['name' => 'Image University ' . $suffix],
            'faculty' => ['name' => 'Image Faculty ' . $suffix],
            'program' => ['name' => 'Image Program ' . $suffix, 'degree_level' => 'professional-doctorate'],
            'cohort' => ['entry_year' => 5901, 'label' => 'Image Cohort ' . $suffix],
            'workspace' => ['name' => 'Image Class ' . $suffix],
        ]);
        $workspaceId = $class['workspace_id'];

        return [
            'workspace' => $workspaceId,
            'manager' => $this->assignRole($workspaceId, 'Image Manager ' . $suffix, 'content-manager'),
            'reviewer' => $this->assignRole($workspaceId, 'Image Reviewer ' . $suffix, 'content-reviewer'),
            'student' => $this->assignRole($workspaceId, 'Image Student ' . $suffix, 'student'),
        ];
    }

    /** @param list<array<string, mixed>> $questions */
    private function assessment(array $workspace, array $questions): string
    {
        $exams = $this->exams();
        $assessment = $exams->createAssessment($workspace['manager'], $workspace['workspace'], 'آزمون تصویری ' . $this->suffix(), ['questions' => $questions], ['max_attempts' => 5]);
        $exams->submitForReview($workspace['manager'], $workspace['workspace'], $assessment['assessment_id'], $assessment['version_id']);
        $exams->reviewVersion($workspace['reviewer'], $workspace['workspace'], $assessment['assessment_id'], $assessment['version_id'], 'approved');
        $exams->publishVersion($workspace['manager'], $workspace['workspace'], $assessment['assessment_id'], $assessment['version_id']);

        return $assessment['assessment_id'];
    }

    /** A user with a role: at a workspace, or (null) at the platform scope. */
    private function assignRole(?string $workspaceId, string $displayName, string $roleKey): string
    {
        $userId = Uuid::v7();
        $this->database->prepare("INSERT INTO iam_users (id, display_name, status, locale, created_at, updated_at) VALUES (:id, :name, 'active', 'fa-IR', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => $userId, 'name' => $displayName]);
        $scope = '00000000-0000-7000-8000-000000000001';
        if ($workspaceId !== null) {
            $this->database->prepare("INSERT INTO tenant_workspace_memberships (id, workspace_id, user_id, status, joined_at, created_at, updated_at) VALUES (:id, :workspace, :user, 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
                ->execute(['id' => Uuid::v7(), 'workspace' => $workspaceId, 'user' => $userId]);
            $lookup = $this->database->prepare("SELECT id FROM rbac_scopes WHERE scope_type = 'workspace' AND entity_id = :entity AND workspace_id = :workspace");
            $lookup->execute(['entity' => $workspaceId, 'workspace' => $workspaceId]);
            $scope = (string) $lookup->fetchColumn();
        }
        $this->database->prepare(<<<'SQL'
INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at)
SELECT :id, :user, role.id, :scope, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
FROM rbac_role_templates role WHERE role.role_key = :role
SQL)->execute(['id' => Uuid::v7(), 'user' => $userId, 'scope' => $scope, 'role' => $roleKey]);

        return $userId;
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
