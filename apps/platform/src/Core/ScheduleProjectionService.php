<?php

declare(strict_types=1);

namespace Fanoos\Platform\Core;

use Fanoos\Platform\Authorization\AccessGate;
use PDO;

final class ScheduleProjectionService
{
    public function __construct(
        private readonly PDO $database,
        private readonly AccessGate $access,
        private readonly ScheduleWindowResolver $windows,
    ) {
    }

    /** @return list<array<string,mixed>> */
    public function list(string $userId, string $workspaceId, string $fromDate, string $toDate): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'academic.view');
        $window = $this->windows->resolve($workspaceId, $fromDate, $toDate);
        $query = $this->database->prepare(<<<'SQL'
SELECT event.id, event.event_type, event.title, event.starts_at, event.ends_at,
       event.location_text, event.status, offering.id AS offering_id,
       course.id AS course_id, course.course_code, course.title AS course_title
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
            'starts_at' => $window['from_utc'],
            'ends_at' => $window['to_utc_exclusive'],
        ]);
        return $query->fetchAll();
    }
}
