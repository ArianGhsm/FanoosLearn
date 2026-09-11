<?php

declare(strict_types=1);

namespace Fanoos\Platform\Core;

use DateTimeImmutable;
use DateTimeZone;
use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Content\ProtectedResourceAuthorizer;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\TextNormalizer;
use Fanoos\Platform\Support\Transaction;
use Fanoos\Platform\Support\Uuid;
use PDO;

final class WorkspacePlatformService
{
    public function __construct(
        private readonly PDO $database,
        private readonly AccessGate $access,
        private readonly AuditLogger $audit,
        private readonly ?ProtectedResourceAuthorizer $resources = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function academicNavigation(string $actorUserId, string $workspaceId): array
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'academic.view');
        $hierarchy = $this->database->prepare(<<<'SQL'
SELECT workspace.id AS workspace_id, workspace.name AS workspace_name,
       cohort.id AS cohort_id, cohort.label AS cohort_name,
       program.id AS program_id, program.name AS program_name,
       faculty.id AS faculty_id, faculty.name AS faculty_name,
       institution.id AS institution_id, institution.name AS institution_name
FROM tenant_workspaces workspace
JOIN directory_cohorts cohort ON cohort.id = workspace.cohort_id
JOIN directory_programs program ON program.id = cohort.program_id
JOIN directory_faculties faculty ON faculty.id = program.faculty_id
JOIN directory_institutions institution ON institution.id = faculty.institution_id
WHERE workspace.id = :workspace AND workspace.status = 'active' AND workspace.archived_at IS NULL
SQL);
        $hierarchy->execute(['workspace' => $workspaceId]);
        $directory = $hierarchy->fetch();
        if ($directory === false) {
            throw new PlatformException('workspace_not_found', 'Workspace was not found.', 404);
        }

        $terms = $this->database->prepare("SELECT id, term_key, name, starts_on, ends_on, status FROM academic_terms WHERE workspace_id = :workspace AND status <> 'archived' AND archived_at IS NULL ORDER BY starts_on DESC");
        $terms->execute(['workspace' => $workspaceId]);
        $courses = $this->database->prepare(<<<'SQL'
SELECT course.id, course.course_code, course.title, course.credit_value,
       offering.id AS offering_id, offering.section_key, offering.status AS offering_status,
       term.id AS term_id, term.name AS term_name,
       session.id AS session_id, session.sequence_no, session.title AS session_title,
       session.starts_at, session.ends_at, session.status AS session_status
FROM academic_courses course
LEFT JOIN academic_course_offerings offering ON offering.course_id = course.id
 AND offering.workspace_id = course.workspace_id AND offering.status <> 'archived' AND offering.archived_at IS NULL
LEFT JOIN academic_terms term ON term.id = offering.term_id AND term.workspace_id = offering.workspace_id
 AND term.status <> 'archived' AND term.archived_at IS NULL
LEFT JOIN academic_course_sessions session ON session.offering_id = offering.id
 AND session.workspace_id = offering.workspace_id AND session.status <> 'archived' AND session.archived_at IS NULL
WHERE course.workspace_id = :workspace AND course.status = 'active' AND course.archived_at IS NULL
ORDER BY course.title, offering.section_key, session.sequence_no
SQL);
        $courses->execute(['workspace' => $workspaceId]);

        return ['directory' => $directory, 'terms' => $terms->fetchAll(), 'courses' => $courses->fetchAll()];
    }

    /** @return list<array<string, mixed>> */
    public function schedule(string $actorUserId, string $workspaceId, string $from, string $to): array
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'academic.view');
        $timezone = $this->workspaceTimezone($workspaceId);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            throw new PlatformException('invalid_date', 'Schedule dates must use YYYY-MM-DD.', 422);
        }
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $from, $timezone);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $to, $timezone);
        if ($start === false || $start->format('Y-m-d') !== $from || $end === false || $end->format('Y-m-d') !== $to) {
            throw new PlatformException('invalid_date', 'Schedule date is invalid.', 422);
        }
        if ($end < $start || $end->getTimestamp() - $start->getTimestamp() > 400 * 86400) {
            throw new PlatformException('invalid_date_range', 'Schedule range must be ordered and no longer than 400 days.', 422);
        }
        $utc = new DateTimeZone('UTC');
        $query = $this->database->prepare(<<<'SQL'
SELECT event.id, event.event_type, event.title, event.starts_at, event.ends_at,
       event.location_text, event.status, offering.id AS offering_id,
       course.course_code, course.title AS course_title
FROM schedule_events event
LEFT JOIN academic_course_offerings offering ON offering.id = event.offering_id AND offering.workspace_id = event.workspace_id
 AND offering.status <> 'archived' AND offering.archived_at IS NULL
LEFT JOIN academic_courses course ON course.id = offering.course_id AND course.workspace_id = offering.workspace_id
 AND course.status = 'active' AND course.archived_at IS NULL
LEFT JOIN academic_terms term ON term.id = offering.term_id AND term.workspace_id = offering.workspace_id
 AND term.status <> 'archived' AND term.archived_at IS NULL
WHERE event.workspace_id = :workspace
  AND event.starts_at >= :starts_at AND event.starts_at < :ends_at
  AND (event.offering_id IS NULL OR (offering.id IS NOT NULL AND term.id IS NOT NULL))
  AND event.status <> 'cancelled'
ORDER BY event.starts_at, event.id
SQL);
        $query->execute([
            'workspace' => $workspaceId,
            'starts_at' => $start->setTimezone($utc)->format('Y-m-d H:i:s'),
            'ends_at' => $end->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s'),
        ]);
        return $query->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function myGrades(string $actorUserId, string $workspaceId): array
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'grade.view_self');
        $query = $this->database->prepare(<<<'SQL'
SELECT course.course_code, course.title AS course_title,
       term.id AS term_id, term.term_key, term.name AS term_name,
       offering.id AS offering_id, gradebook.title AS gradebook_title,
       item.item_key, item.title AS item_title, item.max_score, result.score, result.status AS result_status, result.updated_at
FROM tenant_workspace_memberships membership
JOIN academic_enrollments enrollment ON enrollment.membership_id = membership.id
 AND enrollment.workspace_id = membership.workspace_id AND enrollment.status IN ('active', 'completed')
JOIN academic_course_offerings offering ON offering.id = enrollment.offering_id AND offering.workspace_id = enrollment.workspace_id
 AND offering.status <> 'archived' AND offering.archived_at IS NULL
JOIN academic_terms term ON term.id = offering.term_id AND term.workspace_id = offering.workspace_id
 AND term.status <> 'archived' AND term.archived_at IS NULL
JOIN academic_courses course ON course.id = offering.course_id AND course.workspace_id = offering.workspace_id
 AND course.status = 'active' AND course.archived_at IS NULL
JOIN grade_gradebooks gradebook ON gradebook.offering_id = offering.id
 AND gradebook.workspace_id = offering.workspace_id AND gradebook.status = 'published'
JOIN grade_items item ON item.gradebook_id = gradebook.id AND item.workspace_id = gradebook.workspace_id
JOIN grade_results result ON result.grade_item_id = item.id AND result.enrollment_id = enrollment.id
 AND result.workspace_id = enrollment.workspace_id AND result.status = 'published'
WHERE membership.workspace_id = :workspace AND membership.user_id = :user AND membership.status = 'active'
ORDER BY course.title, item.title
SQL);
        $query->execute(['workspace' => $workspaceId, 'user' => $actorUserId]);
        return $query->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function announcements(string $actorUserId, string $workspaceId, ?string $courseId = null): array
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'notification.receive');
        $where = [
            'recipient.workspace_id = :workspace',
            'recipient.user_id = :user',
            "recipient.channel = 'web'",
            "message.status = 'published'",
            'message.archived_at IS NULL',
        ];
        $parameters = ['workspace' => $workspaceId, 'user' => $actorUserId];
        if ($courseId !== null && trim($courseId) !== '') {
            $where[] = 'scope_course.id = :course';
            $parameters['course'] = trim($courseId);
        }
        $query = $this->database->prepare(sprintf(<<<'SQL'
SELECT message.id, message.title, message.body, message.published_at,
       scope_course.course_code AS scope_course_code,
       scope_course.title AS scope_course_title,
       JSON_UNQUOTE(JSON_EXTRACT(message.data_json, '$.scope_label')) AS scope_label,
       recipient.status, recipient.read_at
FROM notification_recipients recipient
JOIN notification_messages message ON message.id = recipient.notification_id AND message.workspace_id = recipient.workspace_id
LEFT JOIN academic_courses scope_course ON scope_course.id = JSON_UNQUOTE(JSON_EXTRACT(message.data_json, '$.course_id'))
 AND scope_course.workspace_id = message.workspace_id AND scope_course.status = 'active' AND scope_course.archived_at IS NULL
WHERE %s
ORDER BY message.published_at DESC, message.id DESC
SQL, implode(' AND ', $where)));
        $query->execute($parameters);
        return $query->fetchAll();
    }

    public function markAnnouncementRead(string $actorUserId, string $workspaceId, string $announcementId): void
    {
        $this->markNotificationRead($actorUserId, $workspaceId, $announcementId);
    }

    /** @return array{items:list<array<string,mixed>>,next_cursor:?string} */
    public function notifications(string $actorUserId, string $workspaceId, int $limit = 20, ?string $cursor = null): array
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'notification.receive');
        $limit = max(1, min($limit, 50));
        $where = [
            'recipient.workspace_id = :workspace',
            'recipient.user_id = :user',
            "recipient.channel = 'web'",
            "message.status = 'published'",
            'message.archived_at IS NULL',
        ];
        $parameters = ['workspace' => $workspaceId, 'user' => $actorUserId];
        $decoded = $this->decodeNotificationCursor($cursor);
        if ($decoded !== null) {
            $where[] = '(message.published_at < :cursor_published OR (message.published_at = :cursor_published AND message.id < :cursor_id))';
            $parameters['cursor_published'] = $decoded['published_at'];
            $parameters['cursor_id'] = $decoded['id'];
        }
        $query = $this->database->prepare(sprintf(<<<'SQL'
SELECT message.id, message.title, message.body, message.published_at,
       scope_course.course_code AS scope_course_code,
       scope_course.title AS scope_course_title,
       JSON_UNQUOTE(JSON_EXTRACT(message.data_json, '$.scope_label')) AS scope_label,
       recipient.status, recipient.read_at
FROM notification_recipients recipient
JOIN notification_messages message ON message.id = recipient.notification_id AND message.workspace_id = recipient.workspace_id
LEFT JOIN academic_courses scope_course ON scope_course.id = JSON_UNQUOTE(JSON_EXTRACT(message.data_json, '$.course_id'))
 AND scope_course.workspace_id = message.workspace_id AND scope_course.status = 'active' AND scope_course.archived_at IS NULL
WHERE %s
ORDER BY message.published_at DESC, message.id DESC
LIMIT %d
SQL, implode(' AND ', $where), $limit + 1));
        $query->execute($parameters);
        $rows = $query->fetchAll();
        $hasMore = count($rows) > $limit;
        if ($hasMore) array_pop($rows);
        $nextCursor = null;
        if ($hasMore && $rows !== []) {
            $last = $rows[array_key_last($rows)];
            $nextCursor = $this->encodeNotificationCursor((string) $last['published_at'], (string) $last['id']);
        }
        return ['items' => $rows, 'next_cursor' => $nextCursor];
    }

    public function markNotificationRead(string $actorUserId, string $workspaceId, string $notificationId): void
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'notification.receive');
        $existing = $this->database->prepare(<<<'SQL'
SELECT recipient.status
FROM notification_recipients recipient
JOIN notification_messages message ON message.id = recipient.notification_id AND message.workspace_id = recipient.workspace_id
WHERE recipient.workspace_id = :workspace AND recipient.user_id = :user
  AND recipient.notification_id = :notification AND recipient.channel = 'web'
  AND message.status = 'published' AND message.archived_at IS NULL
LIMIT 1
SQL);
        $existing->execute(['workspace' => $workspaceId, 'user' => $actorUserId, 'notification' => $notificationId]);
        $previous = $existing->fetchColumn();
        if ($previous === false) {
            throw new PlatformException('notification_not_found', 'این اعلان در صندوق شخصی شما پیدا نشد.', 404);
        }
        $update = $this->database->prepare(<<<'SQL'
UPDATE notification_recipients
SET status = 'read', read_at = COALESCE(read_at, UTC_TIMESTAMP(6))
WHERE workspace_id = :workspace AND user_id = :user
  AND notification_id = :notification AND channel = 'web'
SQL);
        $update->execute(['workspace' => $workspaceId, 'user' => $actorUserId, 'notification' => $notificationId]);
        if ($previous !== 'read') {
            $this->audit->record($workspaceId, $actorUserId, 'notification.read', 'notification', $notificationId, 'success');
        }
    }

    /** @return array<string, bool|string|null> */
    public function notificationPreferences(string $actorUserId, string $workspaceId): array
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'notification.receive');
        $query = $this->database->prepare(<<<'SQL'
SELECT in_app_enabled, email_enabled, push_enabled, snoozed_until, updated_at
FROM notification_preferences
WHERE workspace_id = :workspace AND user_id = :user
LIMIT 1
SQL);
        $query->execute(['workspace' => $workspaceId, 'user' => $actorUserId]);
        $row = $query->fetch();
        if ($row === false) {
            return [
                'in_app_enabled' => true, 'email_enabled' => false, 'push_enabled' => false,
                'snoozed_until' => null, 'updated_at' => null,
            ];
        }
        return [
            'in_app_enabled' => (int) $row['in_app_enabled'] === 1,
            'email_enabled' => (int) $row['email_enabled'] === 1,
            'push_enabled' => (int) $row['push_enabled'] === 1,
            'snoozed_until' => $row['snoozed_until'],
            'updated_at' => $row['updated_at'],
        ];
    }

    /** @param array<string,mixed> $preferences */
    public function updateNotificationPreferences(string $actorUserId, string $workspaceId, array $preferences): array
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'notification.receive');
        $current = $this->notificationPreferences($actorUserId, $workspaceId);
        $values = [];
        foreach (['in_app_enabled', 'email_enabled', 'push_enabled'] as $key) {
            if (array_key_exists($key, $preferences) && !is_bool($preferences[$key]) && !in_array($preferences[$key], [0, 1, '0', '1'], true)) {
                throw new PlatformException('invalid_notification_preferences', 'مقدار تنظیم اعلان معتبر نیست.', 422);
            }
            $values[$key] = array_key_exists($key, $preferences) ? (bool) $preferences[$key] : (bool) $current[$key];
        }
        $snoozed = $preferences['snoozed_until'] ?? $current['snoozed_until'];
        if ($snoozed !== null && $snoozed !== '') {
            try {
                $snoozed = (new DateTimeImmutable((string) $snoozed))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
            } catch (\Exception) {
                throw new PlatformException('invalid_notification_preferences', 'زمان توقف اعلان معتبر نیست.', 422);
            }
        } else {
            $snoozed = null;
        }
        $statement = $this->database->prepare(<<<'SQL'
INSERT INTO notification_preferences (
    workspace_id, user_id, in_app_enabled, email_enabled, push_enabled, snoozed_until, updated_at
) VALUES (:workspace, :user, :in_app, :email, :push, :snoozed, UTC_TIMESTAMP(6))
ON DUPLICATE KEY UPDATE
    in_app_enabled = VALUES(in_app_enabled), email_enabled = VALUES(email_enabled),
    push_enabled = VALUES(push_enabled), snoozed_until = VALUES(snoozed_until), updated_at = UTC_TIMESTAMP(6)
SQL);
        $statement->execute([
            'workspace' => $workspaceId, 'user' => $actorUserId,
            'in_app' => $values['in_app_enabled'] ? 1 : 0,
            'email' => $values['email_enabled'] ? 1 : 0,
            'push' => $values['push_enabled'] ? 1 : 0,
            'snoozed' => $snoozed,
        ]);
        $this->audit->record($workspaceId, $actorUserId, 'notification.preferences.update', 'notification_preferences', $actorUserId, 'success', $values);
        return $this->notificationPreferences($actorUserId, $workspaceId);
    }

    public function publishAnnouncement(string $actorUserId, string $workspaceId, string $title, string $body, ?string $courseId = null): string
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'notification.broadcast');
        $title = trim($title);
        $body = trim($body);
        if ($title === '' || mb_strlen($title) > 200 || $body === '') {
            throw new PlatformException('invalid_announcement', 'Announcement title and body are required.', 422);
        }
        $courseId = $courseId === null || trim($courseId) === '' ? null : trim($courseId);
        if ($courseId !== null) {
            $course = $this->database->prepare("SELECT 1 FROM academic_courses WHERE id = :course AND workspace_id = :workspace AND status = 'active' AND archived_at IS NULL LIMIT 1");
            $course->execute(['course' => $courseId, 'workspace' => $workspaceId]);
            if ($course->fetchColumn() === false) {
                throw new PlatformException('course_not_found', 'Announcement course scope was not found in this workspace.', 404);
            }
        }

        return Transaction::run($this->database, function () use ($actorUserId, $workspaceId, $title, $body, $courseId): string {
            $id = Uuid::v7();
            $data = json_encode($courseId === null ? [] : ['course_id' => $courseId], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $message = $this->database->prepare(<<<'SQL'
INSERT INTO notification_messages (
    id, workspace_id, message_type, title, body, data_json, status,
    created_by_user_id, created_at, published_at
) VALUES (:id, :workspace, 'announcement', :title, :body, :data, 'published', :actor, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL);
            $message->execute(['id' => $id, 'workspace' => $workspaceId, 'title' => $title, 'body' => $body, 'data' => $data, 'actor' => $actorUserId]);
            $recipients = $this->database->prepare(<<<'SQL'
INSERT INTO notification_recipients (
    id, workspace_id, notification_id, user_id, channel, status, delivered_at, created_at
)
SELECT UUID(), membership.workspace_id, :notification, membership.user_id, 'web', 'delivered', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
FROM tenant_workspace_memberships membership
WHERE membership.workspace_id = :workspace AND membership.status = 'active'
  AND (membership.ended_at IS NULL OR membership.ended_at > UTC_TIMESTAMP(6))
SQL);
            $recipients->execute(['notification' => $id, 'workspace' => $workspaceId]);
            $this->outbox($workspaceId, 'notification', $id, 'announcement.published', ['recipient_count' => $recipients->rowCount()]);
            $this->audit->record($workspaceId, $actorUserId, 'announcement.publish', 'notification', $id, 'success', ['recipient_count' => $recipients->rowCount()]);
            return $id;
        });
    }

    /** @param array<string, mixed> $schema */
    public function createForm(string $actorUserId, string $workspaceId, string $title, array $schema, bool $open = false): string
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'form.manage');
        $this->validateFormSchema($schema);
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 200) {
            throw new PlatformException('invalid_form_title', 'Form title is required.', 422);
        }

        return Transaction::run($this->database, function () use ($actorUserId, $workspaceId, $title, $schema, $open): string {
            $formId = Uuid::v7();
            $versionId = Uuid::v7();
            $encoded = json_encode($schema, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $definition = $this->database->prepare(<<<'SQL'
INSERT INTO form_definitions (
    id, workspace_id, owner_user_id, title, status, audience_type, allow_multiple,
    current_version_no, created_at, updated_at
) VALUES (:id, :workspace, :owner, :title, :status, 'members', FALSE, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL);
            $definition->execute(['id' => $formId, 'workspace' => $workspaceId, 'owner' => $actorUserId, 'title' => $title, 'status' => $open ? 'open' : 'draft']);
            $version = $this->database->prepare(<<<'SQL'
INSERT INTO form_versions (
    id, workspace_id, form_id, version_no, schema_json, schema_checksum, created_by_user_id, created_at
) VALUES (:id, :workspace, :form, 1, :schema, :checksum, :creator, UTC_TIMESTAMP(6))
SQL);
            $version->bindValue(':id', $versionId);
            $version->bindValue(':workspace', $workspaceId);
            $version->bindValue(':form', $formId);
            $version->bindValue(':schema', $encoded);
            $version->bindValue(':checksum', hash('sha256', $encoded, true), PDO::PARAM_LOB);
            $version->bindValue(':creator', $actorUserId);
            $version->execute();
            $this->audit->record($workspaceId, $actorUserId, 'form.create', 'form', $formId);
            return $formId;
        });
    }

    /** @return list<array<string, mixed>> */
    public function forms(string $actorUserId, string $workspaceId): array
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'form.submit');
        $query = $this->database->prepare(<<<'SQL'
SELECT form.id, form.title, form.description, form.allow_multiple, form.opens_at, form.closes_at,
       version.id AS version_id, version.schema_json,
       submission.status AS submission_status, submission.submitted_at
FROM form_definitions form
JOIN form_versions version ON version.form_id = form.id AND version.workspace_id = form.workspace_id
 AND version.version_no = form.current_version_no
LEFT JOIN (
    SELECT form_id, MAX(status) AS status, MAX(submitted_at) AS submitted_at
    FROM form_submissions
    WHERE workspace_id = :submission_workspace AND submitter_user_id = :submission_user AND status = 'submitted'
    GROUP BY form_id
) submission ON submission.form_id = form.id
WHERE form.workspace_id = :workspace AND form.status = 'open' AND form.archived_at IS NULL
  AND (form.opens_at IS NULL OR form.opens_at <= UTC_TIMESTAMP(6))
  AND (form.closes_at IS NULL OR form.closes_at > UTC_TIMESTAMP(6))
ORDER BY form.created_at DESC
SQL);
        $query->execute(['workspace' => $workspaceId, 'submission_workspace' => $workspaceId, 'submission_user' => $actorUserId]);
        return $query->fetchAll();
    }

    /** @param array<string, mixed> $answers */
    public function submitForm(string $actorUserId, string $workspaceId, string $formId, array $answers, string $idempotencyKey): string
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'form.submit');
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 128) {
            throw new PlatformException('invalid_idempotency_key', 'A valid idempotency key is required.', 422);
        }

        return Transaction::run($this->database, function () use ($actorUserId, $workspaceId, $formId, $answers, $idempotencyKey): string {
            $form = $this->database->prepare(<<<'SQL'
SELECT definition.allow_multiple, version.id AS version_id, version.schema_json
FROM form_definitions definition
JOIN form_versions version ON version.form_id = definition.id AND version.workspace_id = definition.workspace_id
 AND version.version_no = definition.current_version_no
WHERE definition.id = :form AND definition.workspace_id = :workspace
  AND definition.status = 'open' AND definition.archived_at IS NULL
  AND (definition.opens_at IS NULL OR definition.opens_at <= UTC_TIMESTAMP(6))
  AND (definition.closes_at IS NULL OR definition.closes_at > UTC_TIMESTAMP(6))
FOR UPDATE
SQL);
            $form->execute(['form' => $formId, 'workspace' => $workspaceId]);
            $row = $form->fetch();
            if ($row === false) {
                throw new PlatformException('form_unavailable', 'Form is not open in this workspace.', 404);
            }
            $schema = json_decode((string) $row['schema_json'], true, 64, JSON_THROW_ON_ERROR);
            $this->validateAnswers($schema, $answers);

            $existing = $this->database->prepare('SELECT id FROM form_submissions WHERE workspace_id = :workspace AND form_id = :form AND submitter_user_id = :user AND idempotency_key = :key');
            $existing->execute(['workspace' => $workspaceId, 'form' => $formId, 'user' => $actorUserId, 'key' => $idempotencyKey]);
            $existingId = $existing->fetchColumn();
            if ($existingId !== false) {
                return (string) $existingId;
            }
            if (!(bool) $row['allow_multiple']) {
                $previous = $this->database->prepare("SELECT id FROM form_submissions WHERE workspace_id = :workspace AND form_id = :form AND submitter_user_id = :user AND status = 'submitted' LIMIT 1");
                $previous->execute(['workspace' => $workspaceId, 'form' => $formId, 'user' => $actorUserId]);
                if ($previous->fetchColumn() !== false) {
                    throw new PlatformException('duplicate_submission', 'This form accepts one submission per member.', 409);
                }
            }

            $id = Uuid::v7();
            $insert = $this->database->prepare(<<<'SQL'
INSERT INTO form_submissions (
    id, workspace_id, form_id, form_version_id, submitter_user_id,
    idempotency_key, status, answers_json, submitted_at, updated_at
) VALUES (:id, :workspace, :form, :version, :user, :key, 'submitted', :answers, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL);
            $insert->execute([
                'id' => $id, 'workspace' => $workspaceId, 'form' => $formId,
                'version' => $row['version_id'], 'user' => $actorUserId, 'key' => $idempotencyKey,
                'answers' => json_encode($answers, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ]);
            $this->audit->record($workspaceId, $actorUserId, 'form.submit', 'form_submission', $id);
            return $id;
        });
    }

    public function upsertSearchDocument(
        string $actorUserId,
        string $workspaceId,
        string $sourceType,
        string $sourceId,
        string $title,
        string $body,
        string $route,
        int $sourceVersion = 1,
    ): void {
        $permission = $sourceType === 'announcement' ? 'notification.broadcast' : ($sourceType === 'form' ? 'form.manage' : 'resource.create');
        $this->access->requireWorkspace($actorUserId, $workspaceId, $permission);
        if (!in_array($sourceType, ['course', 'session', 'schedule', 'announcement', 'form', 'resource'], true)) {
            throw new PlatformException('invalid_search_source', 'Search source type is not supported.', 422);
        }
        $id = Uuid::v7();
        $statement = $this->database->prepare(<<<'SQL'
INSERT INTO search_documents (
    id, workspace_id, source_type, source_id, source_version, title, normalized_text, route, status, updated_at
) VALUES (:id, :workspace, :source_type, :source_id, :source_version, :title, :text, :route, 'active', UTC_TIMESTAMP(6))
ON DUPLICATE KEY UPDATE
    source_version = VALUES(source_version), title = VALUES(title), normalized_text = VALUES(normalized_text),
    route = VALUES(route), status = 'active', updated_at = UTC_TIMESTAMP(6)
SQL);
        $statement->execute([
            'id' => $id, 'workspace' => $workspaceId, 'source_type' => $sourceType,
            'source_id' => $sourceId, 'source_version' => $sourceVersion, 'title' => trim($title),
            'text' => TextNormalizer::normalize($title . ' ' . $body), 'route' => $route,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function search(string $actorUserId, string $workspaceId, string $queryText): array
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'workspace.view');
        $queryText = TextNormalizer::normalize($queryText);
        if (mb_strlen($queryText) < 2 || mb_strlen($queryText) > 120) {
            throw new PlatformException('invalid_search_query', 'Search query must contain 2 to 120 characters.', 422);
        }
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $queryText) . '%';
        $query = $this->database->prepare(<<<'SQL'
SELECT id, source_type, source_id, title, route, updated_at
FROM search_documents
WHERE workspace_id = :workspace AND status = 'active' AND normalized_text LIKE :query ESCAPE '\\'
ORDER BY updated_at DESC, id DESC
LIMIT 50
SQL);
        $query->execute(['workspace' => $workspaceId, 'query' => $like]);
        $results = [];
        foreach ($query->fetchAll() as $row) {
            if (!$this->searchDocumentVisible($actorUserId, $workspaceId, (string) $row['source_type'], (string) $row['source_id'])) continue;
            $row['source_label'] = [
                'course' => 'درس', 'session' => 'جلسه', 'schedule' => 'برنامه',
                'announcement' => 'اطلاعیه', 'form' => 'فرم', 'resource' => 'منبع آموزشی',
            ][$row['source_type']] ?? 'محتوا';
            $results[] = $row;
        }
        return $results;
    }

    private function searchDocumentVisible(string $actorUserId, string $workspaceId, string $sourceType, string $sourceId): bool
    {
        try {
            return match ($sourceType) {
                'announcement' => $this->searchAnnouncementVisible($actorUserId, $workspaceId, $sourceId),
                'form' => $this->searchFormVisible($actorUserId, $workspaceId, $sourceId),
                'course' => $this->searchAcademicVisible($actorUserId, $workspaceId, 'course', $sourceId),
                'session' => $this->searchAcademicVisible($actorUserId, $workspaceId, 'session', $sourceId),
                'schedule' => $this->searchAcademicVisible($actorUserId, $workspaceId, 'schedule', $sourceId),
                'resource' => $this->searchResourceVisible($actorUserId, $workspaceId, $sourceId),
                default => false,
            };
        } catch (PlatformException) {
            return false;
        }
    }

    private function searchAnnouncementVisible(string $actorUserId, string $workspaceId, string $sourceId): bool
    {
        if (!$this->access->workspace($actorUserId, $workspaceId, 'notification.receive')->allowed) return false;
        $query = $this->database->prepare(<<<'SQL'
SELECT 1
FROM notification_recipients recipient
JOIN notification_messages message ON message.id = recipient.notification_id AND message.workspace_id = recipient.workspace_id
WHERE recipient.workspace_id = :workspace AND recipient.user_id = :user AND recipient.notification_id = :notification
  AND recipient.channel = 'web' AND message.status = 'published' AND message.archived_at IS NULL
LIMIT 1
SQL);
        $query->execute(['workspace' => $workspaceId, 'user' => $actorUserId, 'notification' => $sourceId]);
        return $query->fetchColumn() !== false;
    }

    private function searchFormVisible(string $actorUserId, string $workspaceId, string $sourceId): bool
    {
        if (!$this->access->workspace($actorUserId, $workspaceId, 'form.submit')->allowed) return false;
        $query = $this->database->prepare(<<<'SQL'
SELECT 1 FROM form_definitions
WHERE id = :form AND workspace_id = :workspace AND status = 'open' AND archived_at IS NULL
  AND (opens_at IS NULL OR opens_at <= UTC_TIMESTAMP(6))
  AND (closes_at IS NULL OR closes_at > UTC_TIMESTAMP(6))
LIMIT 1
SQL);
        $query->execute(['workspace' => $workspaceId, 'form' => $sourceId]);
        return $query->fetchColumn() !== false;
    }

    private function searchAcademicVisible(string $actorUserId, string $workspaceId, string $sourceType, string $sourceId): bool
    {
        if (!$this->access->workspace($actorUserId, $workspaceId, 'academic.view')->allowed) return false;
        $sql = match ($sourceType) {
            'course' => "SELECT 1 FROM academic_courses WHERE id = :id AND workspace_id = :workspace AND status = 'active' AND archived_at IS NULL LIMIT 1",
            'session' => "SELECT 1 FROM academic_course_sessions session JOIN academic_course_offerings offering ON offering.id = session.offering_id AND offering.workspace_id = session.workspace_id JOIN academic_terms term ON term.id = offering.term_id AND term.workspace_id = offering.workspace_id WHERE session.id = :id AND session.workspace_id = :workspace AND session.status <> 'archived' AND session.archived_at IS NULL AND offering.status <> 'archived' AND offering.archived_at IS NULL AND term.status <> 'archived' AND term.archived_at IS NULL LIMIT 1",
            default => "SELECT 1 FROM schedule_events event LEFT JOIN academic_course_offerings offering ON offering.id = event.offering_id AND offering.workspace_id = event.workspace_id WHERE event.id = :id AND event.workspace_id = :workspace AND event.status <> 'cancelled' AND (event.offering_id IS NULL OR (offering.status <> 'archived' AND offering.archived_at IS NULL)) LIMIT 1",
        };
        $query = $this->database->prepare($sql);
        $query->execute(['workspace' => $workspaceId, 'id' => $sourceId]);
        return $query->fetchColumn() !== false;
    }

    private function searchResourceVisible(string $actorUserId, string $workspaceId, string $sourceId): bool
    {
        if ($this->resources !== null) return $this->resources->decide($actorUserId, $workspaceId, $sourceId)['allowed'] === true;
        return $this->access->workspace($actorUserId, $workspaceId, 'resource.view')->allowed;
    }

    /** @return list<array<string, mixed>> */
    public function members(string $actorUserId, string $workspaceId): array
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'membership.view');
        $query = $this->database->prepare(<<<'SQL'
SELECT membership.id, membership.user_id, user.display_name, user.locale, membership.status, membership.joined_at,
       GROUP_CONCAT(DISTINCT role.role_key ORDER BY role.role_key SEPARATOR ',') AS role_keys
FROM tenant_workspace_memberships membership
JOIN iam_users user ON user.id = membership.user_id
LEFT JOIN rbac_scopes scope ON scope.workspace_id = membership.workspace_id
LEFT JOIN rbac_role_assignments assignment ON assignment.user_id = membership.user_id AND assignment.scope_id = scope.id
 AND assignment.revoked_at IS NULL AND assignment.valid_from <= UTC_TIMESTAMP(6)
 AND (assignment.valid_until IS NULL OR assignment.valid_until > UTC_TIMESTAMP(6))
LEFT JOIN rbac_role_templates role ON role.id = assignment.role_template_id
WHERE membership.workspace_id = :workspace
GROUP BY membership.id, membership.user_id, user.display_name, user.locale, membership.status, membership.joined_at
ORDER BY user.display_name
SQL);
        $query->execute(['workspace' => $workspaceId]);
        return $query->fetchAll();
    }

    public function assignRepresentative(string $actorUserId, string $workspaceId, string $targetUserId): string
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'membership.manage');
        $member = $this->database->prepare("SELECT 1 FROM tenant_workspace_memberships WHERE workspace_id = :workspace AND user_id = :user AND status = 'active' LIMIT 1");
        $member->execute(['workspace' => $workspaceId, 'user' => $targetUserId]);
        if ($member->fetchColumn() === false) {
            throw new PlatformException('member_not_found', 'Representative must be an active member of this workspace.', 404);
        }
        $scope = $this->workspaceScopeId($workspaceId);
        $existing = $this->database->prepare(<<<'SQL'
SELECT assignment.id
FROM rbac_role_assignments assignment
JOIN rbac_role_templates role ON role.id = assignment.role_template_id
WHERE assignment.user_id = :user AND assignment.scope_id = :scope AND role.role_key = 'cohort-representative'
LIMIT 1
SQL);
        $existing->execute(['user' => $targetUserId, 'scope' => $scope]);
        $id = $existing->fetchColumn();
        if ($id !== false) {
            $restore = $this->database->prepare('UPDATE rbac_role_assignments SET revoked_at = NULL, revoke_reason = NULL, valid_from = UTC_TIMESTAMP(6), valid_until = NULL, granted_by_user_id = :actor WHERE id = :id');
            $restore->execute(['actor' => $actorUserId, 'id' => $id]);
            $assignmentId = (string) $id;
        } else {
            $assignmentId = Uuid::v7();
            $insert = $this->database->prepare(<<<'SQL'
INSERT INTO rbac_role_assignments (
    id, user_id, role_template_id, scope_id, granted_by_user_id, valid_from, created_at
)
SELECT :id, :user, role.id, :scope, :actor, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
FROM rbac_role_templates role WHERE role.role_key = 'cohort-representative' AND role.status = 'active'
SQL);
            $insert->execute(['id' => $assignmentId, 'user' => $targetUserId, 'scope' => $scope, 'actor' => $actorUserId]);
            if ($insert->rowCount() !== 1) {
                throw new PlatformException('role_unavailable', 'Representative role is unavailable.', 500);
            }
        }
        $this->audit->record($workspaceId, $actorUserId, 'membership.assign_representative', 'user', $targetUserId, 'success', ['assignment_id' => $assignmentId]);
        return $assignmentId;
    }

    /** @return array<string, mixed> */
    public function adminDashboard(string $actorUserId, string $workspaceId): array
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'workspace.view');
        $definitions = [
            'members' => ['membership.view', 'tenant_workspace_memberships'],
            'courses' => ['academic.view', 'academic_courses'],
            'gradebooks' => ['grade.manage', 'grade_gradebooks'],
            'orders' => ['payment.reconcile', 'commerce_orders'],
            'forms' => ['form.manage', 'form_definitions'],
            'audit_events' => ['audit.view', 'audit_events'],
        ];
        $managementPermissions = [
            'membership.manage', 'academic.manage', 'resource.create', 'resource.review',
            'resource.publish', 'exam.manage', 'grade.manage', 'form.manage',
            'commerce.manage_catalog', 'payment.reconcile', 'entitlement.grant',
            'notification.broadcast', 'audit.view',
        ];
        $managementAvailable = false;
        $capabilities = [];
        foreach ($managementPermissions as $permission) {
            $allowed = $this->access->workspace($actorUserId, $workspaceId, $permission)->allowed;
            $capabilities[$permission] = $allowed;
            if ($allowed) {
                $managementAvailable = true;
            }
        }

        $sections = [];
        foreach ($definitions as $key => [$permission, $table]) {
            if (!$this->access->workspace($actorUserId, $workspaceId, $permission)->allowed) {
                continue;
            }
            $query = $this->database->prepare("SELECT COUNT(*) FROM {$table} WHERE workspace_id = :workspace");
            $query->execute(['workspace' => $workspaceId]);
            $sections[$key] = (int) $query->fetchColumn();
        }
        return [
            'workspace_id' => $workspaceId,
            'management_available' => $managementAvailable,
            'capabilities' => $capabilities,
            'sections' => $sections,
            'generated_at' => gmdate(DATE_ATOM),
        ];
    }

    /** @return array<string, mixed> */
    public function globalAdminDashboard(string $actorUserId): array
    {
        $this->access->requirePlatform($actorUserId, 'audit.view');
        $counts = [];
        foreach ([
            'active_workspaces' => "SELECT COUNT(*) FROM tenant_workspaces WHERE status = 'active' AND archived_at IS NULL",
            'active_users' => "SELECT COUNT(*) FROM iam_users WHERE status = 'active' AND deleted_at IS NULL",
            'paid_orders' => "SELECT COUNT(*) FROM commerce_orders WHERE status = 'paid'",
            'audit_events' => 'SELECT COUNT(*) FROM audit_events',
        ] as $key => $sql) {
            $counts[$key] = (int) $this->database->query($sql)->fetchColumn();
        }
        return ['scope' => 'platform', 'counts' => $counts, 'generated_at' => gmdate(DATE_ATOM)];
    }

    /** @param array<string, mixed> $schema */
    private function validateFormSchema(array $schema): void
    {
        $fields = $schema['fields'] ?? null;
        if (!is_array($fields) || count($fields) === 0 || count($fields) > 100) {
            throw new PlatformException('invalid_form_schema', 'Form schema must contain 1 to 100 fields.', 422);
        }
        $ids = [];
        foreach ($fields as $field) {
            if (!is_array($field) || !isset($field['id'], $field['type'], $field['label'])) {
                throw new PlatformException('invalid_form_schema', 'Each field requires id, type and label.', 422);
            }
            $id = (string) $field['id'];
            if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $id) || isset($ids[$id])) {
                throw new PlatformException('invalid_form_schema', 'Field ids must be unique stable keys.', 422);
            }
            if (!in_array($field['type'], ['text', 'textarea', 'number', 'choice', 'multi_choice', 'date', 'boolean'], true)) {
                throw new PlatformException('invalid_form_schema', 'Field type is not supported.', 422);
            }
            $ids[$id] = true;
        }
    }

    /** @param array<string, mixed> $schema @param array<string, mixed> $answers */
    private function validateAnswers(array $schema, array $answers): void
    {
        $this->validateFormSchema($schema);
        $allowed = [];
        foreach ($schema['fields'] as $field) {
            $id = (string) $field['id'];
            $allowed[$id] = true;
            if (($field['required'] ?? false) && (!array_key_exists($id, $answers) || $answers[$id] === '' || $answers[$id] === null)) {
                throw new PlatformException('required_answer_missing', "Required answer is missing: {$id}", 422);
            }
        }
        if (array_diff_key($answers, $allowed) !== []) {
            throw new PlatformException('unknown_answer', 'Answers contain an unknown field.', 422);
        }
    }

    /** @param array<string, mixed> $payload */
    private function outbox(string $workspaceId, string $aggregateType, string $aggregateId, string $eventType, array $payload): void
    {
        $statement = $this->database->prepare(<<<'SQL'
INSERT INTO outbox_events (
    id, scope_type, workspace_id, aggregate_type, aggregate_id, event_type,
    payload_json, occurred_at, available_at
) VALUES (:id, 'workspace', :workspace, :aggregate_type, :aggregate_id, :event_type, :payload, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL);
        $statement->execute([
            'id' => Uuid::v7(), 'workspace' => $workspaceId, 'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId, 'event_type' => $eventType,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function workspaceScopeId(string $workspaceId): string
    {
        $query = $this->database->prepare("SELECT id FROM rbac_scopes WHERE scope_type = 'workspace' AND workspace_id = :workspace AND entity_id = :entity_workspace AND archived_at IS NULL");
        $query->execute(['workspace' => $workspaceId, 'entity_workspace' => $workspaceId]);
        $id = $query->fetchColumn();
        if ($id === false) {
            throw new PlatformException('workspace_scope_not_found', 'Workspace authorization scope was not found.', 500);
        }
        return (string) $id;
    }

    /** @return array{published_at:string,id:string}|null */
    private function decodeNotificationCursor(?string $cursor): ?array
    {
        $raw = trim((string) $cursor);
        if ($raw === '') return null;
        if (!preg_match('/^[A-Za-z0-9_-]{1,256}$/', $raw)) {
            throw new PlatformException('cursor_invalid', 'Pagination cursor is invalid.', 422);
        }
        $padding = (4 - strlen($raw) % 4) % 4;
        $decoded = base64_decode(strtr($raw . str_repeat('=', $padding), '-_', '+/'), true);
        if ($decoded === false) throw new PlatformException('cursor_invalid', 'Pagination cursor is invalid.', 422);
        try {
            $payload = json_decode($decoded, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new PlatformException('cursor_invalid', 'Pagination cursor is invalid.', 422);
        }
        if (!is_array($payload) || !isset($payload['published_at'], $payload['id'])) {
            throw new PlatformException('cursor_invalid', 'Pagination cursor is invalid.', 422);
        }
        $publishedAt = (string) $payload['published_at'];
        $id = (string) $payload['id'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d+)?$/', $publishedAt) || !preg_match('/^[0-9a-f-]{36}$/i', $id)) {
            throw new PlatformException('cursor_invalid', 'Pagination cursor is invalid.', 422);
        }
        return ['published_at' => $publishedAt, 'id' => $id];
    }

    private function encodeNotificationCursor(string $publishedAt, string $id): string
    {
        return rtrim(strtr(base64_encode(json_encode(['published_at' => $publishedAt, 'id' => $id], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    private function workspaceTimezone(string $workspaceId): DateTimeZone
    {
        $query = $this->database->prepare("SELECT timezone_name FROM tenant_workspaces WHERE id = :workspace AND status = 'active' AND archived_at IS NULL LIMIT 1");
        $query->execute(['workspace' => $workspaceId]);
        $name = $query->fetchColumn();
        if ($name === false) {
            throw new PlatformException('workspace_not_found', 'Workspace was not found.', 404);
        }
        try {
            return new DateTimeZone((string) $name);
        } catch (\Exception) {
            throw new PlatformException('workspace_timezone_invalid', 'Workspace timezone configuration is invalid.', 500);
        }
    }
}
