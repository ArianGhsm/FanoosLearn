<?php

declare(strict_types=1);

namespace Fanoos\Platform\Core;

use DateTimeImmutable;
use DateTimeZone;
use Fanoos\Platform\Support\PlatformException;
use PDO;

final class ScheduleWindowResolver
{
    public function __construct(private readonly PDO $database)
    {
    }

    /** @return array{from_utc:string,to_utc_exclusive:string,timezone:string} */
    public function resolve(string $workspaceId, string $fromDate, string $toDate): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
            throw new PlatformException('invalid_date', 'Schedule dates must use YYYY-MM-DD.', 422);
        }
        $query = $this->database->prepare(
            "SELECT timezone_name FROM tenant_workspaces WHERE id = :workspace AND status = 'active' AND archived_at IS NULL LIMIT 1"
        );
        $query->execute(['workspace' => $workspaceId]);
        $name = $query->fetchColumn();
        if ($name === false) {
            throw new PlatformException('workspace_not_found', 'Workspace was not found.', 404);
        }
        try {
            $timezone = new DateTimeZone((string) $name);
        } catch (\Exception) {
            throw new PlatformException('workspace_timezone_invalid', 'Workspace timezone configuration is invalid.', 500);
        }
        $from = DateTimeImmutable::createFromFormat('!Y-m-d', $fromDate, $timezone);
        $to = DateTimeImmutable::createFromFormat('!Y-m-d', $toDate, $timezone);
        if ($from === false || $from->format('Y-m-d') !== $fromDate || $to === false || $to->format('Y-m-d') !== $toDate) {
            throw new PlatformException('invalid_date', 'Schedule date is invalid.', 422);
        }
        if ($to < $from || $to->getTimestamp() - $from->getTimestamp() > 400 * 86400) {
            throw new PlatformException('invalid_date_range', 'Schedule range must be ordered and no longer than 400 days.', 422);
        }
        $utc = new DateTimeZone('UTC');
        return [
            'from_utc' => $from->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
            'to_utc_exclusive' => $to->modify('+1 day')->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
            'timezone' => $timezone->getName(),
        ];
    }
}
