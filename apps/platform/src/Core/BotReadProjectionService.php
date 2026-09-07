<?php

declare(strict_types=1);

namespace Fanoos\Platform\Core;

use DateTimeImmutable;
use DateTimeZone;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Content\ProtectedResourceAuthorizer;
use Fanoos\Platform\Support\PlatformException;
use PDO;

final class BotReadProjectionService
{
    public function __construct(
        private readonly PDO $database,
        private readonly AccessGate $access,
        private readonly ProtectedResourceAuthorizer $resources,
    ) {
    }

    /** @return array<string,mixed> */
    public function schedule(string $userId, string $workspaceId, string $fromDate, string $toDate, int $limit = 100, ?string $cursor = null): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'academic.view');
        $timezone = $this->workspaceTimezone($workspaceId);
        $from = $this->date($fromDate, $timezone);
        $to = $this->date($toDate, $timezone);
        if ($to < $from || $to->getTimestamp() - $from->getTimestamp() > 31 * 86400) {
            throw new PlatformException('invalid_date_range', 'Schedule range must be ordered and no longer than 31 days.', 422);
        }
        $endExclusive = $to->modify('+1 day');
        $offset = $this->decodeCursor($cursor);
        $limit = max(1, min(200, $limit));
        $query = $this->database->prepare(<<<'SQL'
SELECT event.id, event.event_type, event.title, event.starts_at, event.ends_at,
       event.location_text, event.status, offering.id AS offering_id,
       course.id AS course_id, course.course_code, course.title AS course_title
FROM schedule_events event
LEFT JOIN academic_course_offerings offering ON offering.id = event.offering_id AND offering.workspace_id = event.workspace_id
LEFT JOIN academic_courses course ON course.id = offering.course_id AND course.workspace_id = offering.workspace_id
WHERE event.workspace_id = :workspace
  AND event.starts_at >= :starts_at AND event.starts_at < :ends_at
  AND event.status <> 'cancelled'
ORDER BY event.starts_at, event.id
LIMIT :limit OFFSET :offset
SQL);
        $query->bindValue(':workspace', $workspaceId);
        $query->bindValue(':starts_at', $from->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
        $query->bindValue(':ends_at', $endExclusive->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
        $query->bindValue(':limit', $limit + 1, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();
        $rows = $query->fetchAll();
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }
        foreach ($rows as &$row) {
            $row['starts_at'] = $this->localInstant((string) $row['starts_at'], $timezone);
            $row['ends_at'] = $row['ends_at'] === null ? null : $this->localInstant((string) $row['ends_at'], $timezone);
        }
        unset($row);
        return [
            'timezone' => $timezone->getName(),
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'items' => $rows,
            'next_cursor' => $hasMore ? $this->encodeCursor($offset + count($rows)) : null,
        ];
    }

    /** @return array<string,mixed> */
    public function grades(string $userId, string $workspaceId, int $limit = 50, ?string $cursor = null): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'grade.view_self');
        $offset = $this->decodeCursor($cursor);
        $limit = max(1, min(100, $limit));
        $query = $this->database->prepare(<<<'SQL'
SELECT result.id AS result_id, course.id AS course_id, course.course_code,
       course.title AS course_title, gradebook.id AS gradebook_id,
       gradebook.title AS gradebook_title, item.id AS item_id, item.item_key,
       item.title AS item_title, item.max_score, result.score, result.updated_at
FROM tenant_workspace_memberships membership
JOIN academic_enrollments enrollment ON enrollment.membership_id = membership.id
 AND enrollment.workspace_id = membership.workspace_id AND enrollment.status IN ('active', 'completed')
JOIN academic_course_offerings offering ON offering.id = enrollment.offering_id AND offering.workspace_id = enrollment.workspace_id
JOIN academic_courses course ON course.id = offering.course_id AND course.workspace_id = offering.workspace_id
JOIN grade_gradebooks gradebook ON gradebook.offering_id = offering.id
 AND gradebook.workspace_id = offering.workspace_id AND gradebook.status = 'published'
JOIN grade_items item ON item.gradebook_id = gradebook.id AND item.workspace_id = gradebook.workspace_id
JOIN grade_results result ON result.grade_item_id = item.id AND result.enrollment_id = enrollment.id
 AND result.workspace_id = enrollment.workspace_id AND result.status = 'published'
WHERE membership.workspace_id = :workspace AND membership.user_id = :user AND membership.status = 'active'
  AND (membership.ended_at IS NULL OR membership.ended_at > UTC_TIMESTAMP(6))
ORDER BY result.updated_at DESC, result.id DESC
LIMIT :limit OFFSET :offset
SQL);
        $query->bindValue(':workspace', $workspaceId);
        $query->bindValue(':user', $userId);
        $query->bindValue(':limit', $limit + 1, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();
        $rows = $query->fetchAll();
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }
        return ['items' => $rows, 'next_cursor' => $hasMore ? $this->encodeCursor($offset + count($rows)) : null];
    }

    /** @return array<string,mixed> */
    public function announcements(string $userId, string $workspaceId, int $limit = 20, ?string $cursor = null): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'notification.receive');
        $offset = $this->decodeCursor($cursor);
        $limit = max(1, min(50, $limit));
        $query = $this->database->prepare(<<<'SQL'
SELECT message.id, message.title, message.body, message.published_at,
       recipient.status, recipient.read_at
FROM notification_recipients recipient
JOIN notification_messages message ON message.id = recipient.notification_id AND message.workspace_id = recipient.workspace_id
WHERE recipient.workspace_id = :workspace AND recipient.user_id = :user
  AND recipient.channel = 'web' AND message.status = 'published' AND message.archived_at IS NULL
ORDER BY message.published_at DESC, message.id DESC
LIMIT :limit OFFSET :offset
SQL);
        $query->bindValue(':workspace', $workspaceId);
        $query->bindValue(':user', $userId);
        $query->bindValue(':limit', $limit + 1, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();
        $rows = $query->fetchAll();
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }
        return ['items' => $rows, 'next_cursor' => $hasMore ? $this->encodeCursor($offset + count($rows)) : null];
    }

    /** @return array<string,mixed> */
    public function resourceCatalog(string $userId, string $workspaceId, int $limit = 20, ?string $cursor = null): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'resource.view');
        $offset = $this->decodeCursor($cursor);
        $limit = max(1, min(50, $limit));
        $scanLimit = min(200, max($limit + 1, $limit * 4));
        $query = $this->database->prepare(<<<'SQL'
SELECT resource.id AS resource_id, resource.title, resource.description,
       resource.visibility, resource.updated_at, type.type_key,
       metadata.format_key, metadata.topic, metadata.professor_name,
       metadata.course_id, course.course_code, course.title AS course_title
FROM content_resources resource
JOIN content_resource_types type ON type.id = resource.resource_type_id
LEFT JOIN content_resource_metadata metadata ON metadata.resource_id = resource.id AND metadata.workspace_id = resource.workspace_id
LEFT JOIN academic_courses course ON course.id = metadata.course_id AND course.workspace_id = resource.workspace_id
WHERE resource.workspace_id = :workspace
  AND resource.lifecycle_status = 'published'
  AND resource.archived_at IS NULL AND resource.deleted_at IS NULL
ORDER BY resource.updated_at DESC, resource.id DESC
LIMIT :limit OFFSET :offset
SQL);
        $query->bindValue(':workspace', $workspaceId);
        $query->bindValue(':limit', $scanLimit, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();
        $candidates = $query->fetchAll();
        $items = [];
        foreach ($candidates as $candidate) {
            $decision = $this->resources->decide($userId, $workspaceId, (string) $candidate['resource_id']);
            if (!$decision['allowed']) {
                continue;
            }
            $candidate['resource_version_id'] = $decision['resource_version_id'];
            $candidate['delivery_supported'] = $decision['object_id'] !== null;
            $items[] = $candidate;
            if (count($items) >= $limit) {
                break;
            }
        }
        $nextOffset = $offset + count($candidates);
        $hasMore = count($candidates) === $scanLimit;
        return [
            'items' => $items,
            'next_cursor' => $hasMore ? $this->encodeCursor($nextOffset) : null,
        ];
    }

    private function workspaceTimezone(string $workspaceId): DateTimeZone
    {
        $query = $this->database->prepare("SELECT timezone_name FROM tenant_workspaces WHERE id = :workspace AND status = 'active' AND archived_at IS NULL");
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

    private function date(string $value, DateTimeZone $timezone): DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new PlatformException('invalid_date', 'Date must use YYYY-MM-DD.', 422);
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new PlatformException('invalid_date', 'Date is invalid.', 422);
        }
        return $date;
    }

    private function localInstant(string $utc, DateTimeZone $timezone): string
    {
        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone($timezone)->format(DATE_ATOM);
    }

    private function decodeCursor(?string $cursor): int
    {
        if ($cursor === null || $cursor === '') {
            return 0;
        }
        if (!preg_match('/^[A-Za-z0-9_-]{1,32}$/', $cursor)) {
            throw new PlatformException('cursor_invalid', 'Pagination cursor is invalid.', 422);
        }
        $padding = (4 - strlen($cursor) % 4) % 4;
        $decoded = base64_decode(strtr($cursor . str_repeat('=', $padding), '-_', '+/'), true);
        if (!is_string($decoded) || !preg_match('/^o:[0-9]{1,7}$/', $decoded)) {
            throw new PlatformException('cursor_invalid', 'Pagination cursor is invalid.', 422);
        }
        $offset = (int) substr($decoded, 2);
        if ($offset > 1_000_000) {
            throw new PlatformException('cursor_invalid', 'Pagination cursor is invalid.', 422);
        }
        return $offset;
    }

    private function encodeCursor(int $offset): string
    {
        return rtrim(strtr(base64_encode('o:' . $offset), '+/', '-_'), '=');
    }
}
