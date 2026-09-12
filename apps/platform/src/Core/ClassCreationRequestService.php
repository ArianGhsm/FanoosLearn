<?php

declare(strict_types=1);

namespace Fanoos\Platform\Core;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Transaction;
use PDO;

/**
 * The owner-facing consumption side of capability 5
 * (docs/product/01_FRONT_DOOR.md #9): `class_creation_requests` rows that
 * ClassMembershipService::requestClassCreation() writes have had no reader
 * until this. Several students landing on the same (program_id, entry_year)
 * each get their own row (the table's unique key is per user), so every read
 * and write here operates on that identity as a group, never a single row --
 * that group is exactly the owner's unit of decision ("do I create this class
 * or not"), and the demand count is the number of pending rows in it.
 *
 * Authorization reuses workspace.provision, the same platform permission
 * ClassProvisioningService::createClass() already requires: reviewing and
 * acting on a request to create a class is the same authority as creating one
 * directly, not a new capability.
 *
 * Approval hands the request's already-known directory identity (resolved by
 * walking up from program_id) to ClassProvisioningService::createClass()
 * rather than re-deriving or re-typing it -- the owner supplies only what a
 * pending request cannot already answer: the new cohort's label and the
 * class's display name. createClass() is idempotent by identity, so an
 * approval that lands after the same class was somehow already created
 * (another approval, or the owner's own class wizard) resolves onto the
 * existing workspace instead of duplicating it.
 */
final class ClassCreationRequestService
{
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 50;
    private const REQUIRED_PERMISSION = 'workspace.provision';

    public function __construct(
        private readonly PDO $database,
        private readonly AccessGate $access,
        private readonly AuditLogger $audit,
        private readonly ClassProvisioningService $provisioning,
    ) {
    }

    /**
     * Pending requests grouped by (program_id, entry_year), each with its
     * demand count, resolved directory labels and the earliest request time --
     * ordered oldest-demand-first, since that is the signal an owner acts on.
     *
     * @return array{items:list<array<string,mixed>>,next_cursor:?string}
     */
    public function listPendingGroups(string $actorUserId, int $limit = self::DEFAULT_LIMIT, ?string $cursor = null): array
    {
        $this->access->requirePlatform($actorUserId, self::REQUIRED_PERMISSION);
        $limit = $this->boundedLimit($limit);
        $offset = $this->decodeCursor($cursor);

        $query = $this->database->prepare(<<<'SQL'
SELECT
    request.program_id,
    request.entry_year,
    COUNT(*) AS demand_count,
    MIN(request.requested_at) AS earliest_requested_at,
    program.name AS program_name,
    program.degree_level AS degree_level,
    faculty.name AS faculty_name,
    institution.name AS institution_name,
    city.name AS city_name,
    province.name AS province_name
FROM class_creation_requests request
JOIN directory_programs program ON program.id = request.program_id
JOIN directory_faculties faculty ON faculty.id = program.faculty_id
JOIN directory_institutions institution ON institution.id = faculty.institution_id
JOIN directory_cities city ON city.id = institution.city_id
JOIN directory_provinces province ON province.id = city.province_id
WHERE request.status = 'pending'
GROUP BY request.program_id, request.entry_year, program.id, faculty.id, institution.id, city.id, province.id
ORDER BY earliest_requested_at ASC, request.program_id ASC, request.entry_year ASC
LIMIT :limit OFFSET :offset
SQL);
        $query->bindValue(':limit', $limit + 1, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();
        return $this->paged($query->fetchAll(), $limit, $offset);
    }

    /**
     * Creates the class from the request group's already-known directory
     * identity and closes every pending request in the group, atomically: a
     * class created with requests left pending, or requests closed with no
     * class, are both failures this transaction must never produce.
     *
     * @return array{workspace_id:string,workspace_created:bool,resolved_count:int}
     */
    public function approveGroup(
        string $actorUserId,
        string $programId,
        int $entryYear,
        string $cohortLabel,
        string $workspaceName,
    ): array {
        $this->access->requirePlatform($actorUserId, self::REQUIRED_PERMISSION);
        $cohortLabel = $this->requireText($cohortLabel, 1, 160, 'cohort_label');
        $workspaceName = $this->requireText($workspaceName, 1, 200, 'workspace_name');

        return Transaction::run($this->database, function () use ($actorUserId, $programId, $entryYear, $cohortLabel, $workspaceName): array {
            $ids = $this->lockPendingGroup($programId, $entryYear);
            $identity = $this->resolveIdentity($programId, $entryYear, $cohortLabel, $workspaceName);

            $result = $this->provisioning->createClass($actorUserId, $identity);
            $workspaceId = (string) $result['workspace_id'];

            $this->resolveGroup($ids, $actorUserId, 'created');

            $this->audit->record($workspaceId, $actorUserId, 'class_creation_request.approve', 'class_creation_request_group', $programId, 'success', [
                'entry_year' => $entryYear,
                'request_count' => count($ids),
                'workspace_id' => $workspaceId,
                'workspace_created' => $result['workspace_created'],
            ]);

            return [
                'workspace_id' => $workspaceId,
                'workspace_created' => (bool) $result['workspace_created'],
                'resolved_count' => count($ids),
            ];
        });
    }

    /** @return array{declined_count:int} */
    public function declineGroup(string $actorUserId, string $programId, int $entryYear): array
    {
        $this->access->requirePlatform($actorUserId, self::REQUIRED_PERMISSION);

        return Transaction::run($this->database, function () use ($actorUserId, $programId, $entryYear): array {
            $ids = $this->lockPendingGroup($programId, $entryYear);
            $this->resolveGroup($ids, $actorUserId, 'declined');

            $this->audit->record(null, $actorUserId, 'class_creation_request.decline', 'class_creation_request_group', $programId, 'success', [
                'entry_year' => $entryYear,
                'request_count' => count($ids),
            ]);

            return ['declined_count' => count($ids)];
        });
    }

    /** @return list<string> */
    private function lockPendingGroup(string $programId, int $entryYear): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT id FROM class_creation_requests
WHERE program_id = :program AND entry_year = :year AND status = 'pending'
FOR UPDATE
SQL);
        $query->execute(['program' => $programId, 'year' => $entryYear]);
        $ids = $query->fetchAll(PDO::FETCH_COLUMN);
        if ($ids === false || count($ids) === 0) {
            throw new PlatformException('class_creation_request_not_found', 'No pending class-creation requests were found for this identity.', 404);
        }
        return array_map('strval', $ids);
    }

    /** @param list<string> $ids */
    private function resolveGroup(array $ids, string $actorUserId, string $status): void
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $update = $this->database->prepare(<<<SQL
UPDATE class_creation_requests
SET status = ?, resolved_at = UTC_TIMESTAMP(6), resolved_by_user_id = ?, updated_at = UTC_TIMESTAMP(6)
WHERE id IN ({$placeholders})
SQL);
        $update->execute(array_merge([$status, $actorUserId], $ids));
    }

    /** @return array<string,mixed> */
    private function resolveIdentity(string $programId, int $entryYear, string $cohortLabel, string $workspaceName): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT
    program.code AS program_code, program.name AS program_name, program.degree_level,
    faculty.code AS faculty_code, faculty.name AS faculty_name,
    institution.id AS institution_id, institution.slug AS institution_slug, institution.name AS institution_name, institution.institution_type,
    city.code AS city_code, city.name AS city_name,
    province.code AS province_code, province.name AS province_name,
    country.code AS country_code, country.name AS country_name
FROM directory_programs program
JOIN directory_faculties faculty ON faculty.id = program.faculty_id
JOIN directory_institutions institution ON institution.id = faculty.institution_id
JOIN directory_cities city ON city.id = institution.city_id
JOIN directory_provinces province ON province.id = city.province_id
JOIN directory_countries country ON country.id = province.country_id
WHERE program.id = :program
SQL);
        $query->execute(['program' => $programId]);
        $row = $query->fetch();
        if ($row === false) {
            throw new PlatformException('program_not_found', 'Program was not found.', 404);
        }

        return [
            'country' => ['code' => $row['country_code'], 'name' => $row['country_name']],
            'province' => ['code' => $row['province_code'], 'name' => $row['province_name']],
            'city' => ['code' => $row['city_code'], 'name' => $row['city_name']],
            'institution' => ['slug' => $row['institution_slug'], 'name' => $row['institution_name'], 'institution_type' => $row['institution_type']],
            'faculty' => ['code' => $row['faculty_code'], 'name' => $row['faculty_name']],
            'department' => null,
            'program' => ['code' => $row['program_code'], 'name' => $row['program_name'], 'degree_level' => $row['degree_level']],
            'cohort' => ['entry_year' => $entryYear, 'label' => $cohortLabel],
            'workspace' => ['name' => $workspaceName],
        ];
    }

    private function requireText(string $value, int $min, int $max, string $field): string
    {
        $trimmed = trim($value);
        $length = mb_strlen($trimmed);
        if ($length < $min || $length > $max) {
            throw new PlatformException('invalid_class_creation_decision', "Field is invalid: {$field}.", 422);
        }
        return $trimmed;
    }

    /** @param list<array<string,mixed>> $rows @return array{items:list<array<string,mixed>>,next_cursor:?string} */
    private function paged(array $rows, int $limit, int $offset): array
    {
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }
        return ['items' => $rows, 'next_cursor' => $hasMore ? $this->encodeCursor($offset + count($rows)) : null];
    }

    private function boundedLimit(int $limit): int
    {
        return max(1, min(self::MAX_LIMIT, $limit));
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
