<?php

declare(strict_types=1);

namespace Fanoos\Platform\Core;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Transaction;
use Fanoos\Platform\Support\Uuid;
use PDO;

/**
 * Term dates (docs/product/01_FRONT_DOOR.md #4) configured once per
 * institution and materialised into every active class's academic_terms
 * row, rather than re-typed per class. academic_terms stays keyed
 * (workspace_id, term_key) -- every existing read/join
 * (BotReadProjectionService, ExamService, ContentService) and the tenant
 * isolation filter on workspace_id keep working untouched; this service
 * only ever writes rows there, it never changes what reads them.
 *
 * Two triggers write into academic_terms, both idempotent:
 * setInstitutionTerm() applies to every currently active class under the
 * institution, and materializeCurrentTermsIntoWorkspace() applies every
 * current institution term to one workspace, for the moment a class is
 * created (docs/product/01_FRONT_DOOR.md #4: "a class created later picks
 * up its institution's current terms at creation").
 *
 * A representative may override their own class's dates
 * (overrideClassTerm()) when they genuinely differ. origin distinguishes
 * the two: an 'inherited' row is exactly what the institution's canonical
 * term currently says and is safe to overwrite on the next institution-wide
 * edit; an 'override' row is a representative's deliberate departure and
 * both materialisation methods skip it rather than clobber it.
 */
final class InstitutionTermService
{
    private const OWNER_PERMISSION = 'workspace.provision';
    private const WORKSPACE_VIEW_PERMISSION = 'academic.view';
    private const WORKSPACE_MANAGE_PERMISSION = 'academic.manage';
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 50;

    public function __construct(
        private readonly PDO $database,
        private readonly AccessGate $access,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Owner-only: every active institution, for the term-setting picker's
     * first step (which institution). Authorized the same way class
     * provisioning is -- workspace.provision at platform scope.
     *
     * @return array{items:list<array<string,mixed>>,next_cursor:?string}
     */
    public function listInstitutions(string $actorUserId, int $limit = self::DEFAULT_LIMIT, ?string $cursor = null): array
    {
        $this->access->requirePlatform($actorUserId, self::OWNER_PERMISSION);
        $limit = $this->boundedLimit($limit);
        $offset = $this->decodeCursor($cursor);

        $query = $this->database->prepare(<<<'SQL'
SELECT institution.id, institution.name, city.name AS city_name, province.name AS province_name
FROM directory_institutions institution
JOIN directory_cities city ON city.id = institution.city_id
JOIN directory_provinces province ON province.id = city.province_id
WHERE institution.status = 'active' AND institution.archived_at IS NULL
ORDER BY institution.name, institution.id
LIMIT :limit OFFSET :offset
SQL);
        $query->bindValue(':limit', $limit + 1, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();
        return $this->paged($query->fetchAll(), $limit, $offset);
    }

    /**
     * Owner-only: sets or edits the institution's canonical term, then
     * materialises it into every currently active class under that
     * institution in the same transaction -- an institution row updated
     * with some classes left stale, or classes updated with the
     * institution row unchanged, are both wrong.
     *
     * @return array{institution_term_id:string,applied_count:int,skipped_count:int}
     */
    public function setInstitutionTerm(
        string $actorUserId,
        string $institutionId,
        string $termKey,
        string $name,
        string $startsOn,
        string $endsOn,
        string $status = 'planned',
    ): array {
        $this->access->requirePlatform($actorUserId, self::OWNER_PERMISSION);
        $termKey = $this->requireCode($termKey, 64, 'term_key');
        $name = $this->requireText($name, 1, 160, 'name');
        $startsOn = $this->requireDate($startsOn, 'starts_on');
        $endsOn = $this->requireDate($endsOn, 'ends_on');
        $status = $this->requireStatus($status);
        if ($endsOn < $startsOn) {
            throw new PlatformException('invalid_term_range', 'Term end date cannot be before its start date.', 422);
        }

        return Transaction::run($this->database, function () use (
            $actorUserId, $institutionId, $termKey, $name, $startsOn, $endsOn, $status,
        ): array {
            $institution = $this->database->prepare("SELECT id FROM directory_institutions WHERE id = :id AND status = 'active' AND archived_at IS NULL FOR UPDATE");
            $institution->execute(['id' => $institutionId]);
            if ($institution->fetchColumn() === false) {
                throw new PlatformException('institution_not_found', 'Institution was not found.', 404);
            }

            $existing = $this->database->prepare('SELECT id FROM institution_terms WHERE institution_id = :institution AND term_key = :key FOR UPDATE');
            $existing->execute(['institution' => $institutionId, 'key' => $termKey]);
            $institutionTermId = $existing->fetchColumn();
            if ($institutionTermId !== false) {
                $institutionTermId = (string) $institutionTermId;
                $this->database->prepare(<<<'SQL'
UPDATE institution_terms SET name = :name, starts_on = :starts, ends_on = :ends, status = :status, updated_at = UTC_TIMESTAMP(6)
WHERE id = :id
SQL)->execute(['name' => $name, 'starts' => $startsOn, 'ends' => $endsOn, 'status' => $status, 'id' => $institutionTermId]);
            } else {
                $institutionTermId = Uuid::v7();
                $this->database->prepare(<<<'SQL'
INSERT INTO institution_terms (id, institution_id, term_key, name, starts_on, ends_on, status, created_at, updated_at)
VALUES (:id, :institution, :key, :name, :starts, :ends, :status, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL)->execute([
                    'id' => $institutionTermId, 'institution' => $institutionId, 'key' => $termKey,
                    'name' => $name, 'starts' => $startsOn, 'ends' => $endsOn, 'status' => $status,
                ]);
            }

            [$applied, $skipped] = $this->applyToActiveWorkspaces($institutionId, $institutionTermId, $termKey, $name, $startsOn, $endsOn, $status);

            $this->audit->record(null, $actorUserId, 'institution_term.set', 'institution_term', $institutionTermId, 'success', [
                'institution_id' => $institutionId,
                'term_key' => $termKey,
                'applied_count' => $applied,
                'skipped_count' => $skipped,
            ]);

            return ['institution_term_id' => $institutionTermId, 'applied_count' => $applied, 'skipped_count' => $skipped];
        });
    }

    /**
     * Applies every current institution term to one workspace -- the "a
     * class created later picks up its institution's current terms"
     * moment. Called right after a new workspace is created; intentionally
     * has no actor/permission check of its own because it is not a public
     * capability, it is the same materialisation setInstitutionTerm() does,
     * invoked for one specific (already-authorized) workspace instead of
     * every workspace under the institution.
     */
    public function materializeCurrentTermsIntoWorkspace(string $workspaceId, string $institutionId): void
    {
        Transaction::run($this->database, function () use ($workspaceId, $institutionId): void {
            $terms = $this->database->prepare('SELECT id, term_key, name, starts_on, ends_on, status FROM institution_terms WHERE institution_id = :institution AND archived_at IS NULL');
            $terms->execute(['institution' => $institutionId]);
            foreach ($terms->fetchAll() as $term) {
                $this->upsertWorkspaceTerm(
                    $workspaceId,
                    (string) $term['term_key'],
                    (string) $term['id'],
                    (string) $term['name'],
                    (string) $term['starts_on'],
                    (string) $term['ends_on'],
                    (string) $term['status'],
                );
            }
        });
    }

    /**
     * A workspace's own term rows, for the representative's override
     * screen and anyone else who can already view academic structures.
     *
     * @return list<array<string,mixed>>
     */
    /** @return array{items:list<array<string,mixed>>,can_override:bool} */
    public function listWorkspaceTerms(string $actorUserId, string $workspaceId): array
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, self::WORKSPACE_VIEW_PERMISSION);
        $query = $this->database->prepare(<<<'SQL'
SELECT id, term_key, name, starts_on, ends_on, status, origin
FROM academic_terms
WHERE workspace_id = :workspace AND archived_at IS NULL
ORDER BY starts_on DESC, term_key
SQL);
        $query->execute(['workspace' => $workspaceId]);

        // can_override is a plain read of the caller's own authorization, not
        // a grant: it lets the bot decide whether to offer the override
        // action at all, the same probe-and-hide pattern
        // docs/product/01_FRONT_DOOR.md #12 already used for membership.approve
        // -- overrideClassTerm() below re-checks academic.manage itself
        // regardless of what this flag says.
        $canOverride = $this->access->workspace($actorUserId, $workspaceId, self::WORKSPACE_MANAGE_PERMISSION)->allowed;

        return ['items' => $query->fetchAll(), 'can_override' => $canOverride];
    }

    /**
     * A representative (or admin) overriding their own class's term dates.
     * Scoped to academic.manage on this workspace -- managing this
     * workspace's own academic structures, not any wider power. Marks the
     * row 'override' so a later institution-wide edit never clobbers it.
     *
     * @return array{term_id:string,created:bool}
     */
    public function overrideClassTerm(
        string $actorUserId,
        string $workspaceId,
        string $termKey,
        string $name,
        string $startsOn,
        string $endsOn,
        string $status = 'planned',
    ): array {
        $this->access->requireWorkspace($actorUserId, $workspaceId, self::WORKSPACE_MANAGE_PERMISSION);
        $termKey = $this->requireCode($termKey, 64, 'term_key');
        $name = $this->requireText($name, 1, 160, 'name');
        $startsOn = $this->requireDate($startsOn, 'starts_on');
        $endsOn = $this->requireDate($endsOn, 'ends_on');
        $status = $this->requireStatus($status);
        if ($endsOn < $startsOn) {
            throw new PlatformException('invalid_term_range', 'Term end date cannot be before its start date.', 422);
        }

        return Transaction::run($this->database, function () use (
            $actorUserId, $workspaceId, $termKey, $name, $startsOn, $endsOn, $status,
        ): array {
            $existing = $this->database->prepare('SELECT id FROM academic_terms WHERE workspace_id = :workspace AND term_key = :key FOR UPDATE');
            $existing->execute(['workspace' => $workspaceId, 'key' => $termKey]);
            $termId = $existing->fetchColumn();
            $created = $termId === false;
            if ($created) {
                $termId = Uuid::v7();
                $this->database->prepare(<<<'SQL'
INSERT INTO academic_terms (id, workspace_id, term_key, institution_term_id, name, starts_on, ends_on, status, origin, created_at, updated_at)
VALUES (:id, :workspace, :key, NULL, :name, :starts, :ends, :status, 'override', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL)->execute([
                    'id' => $termId, 'workspace' => $workspaceId, 'key' => $termKey,
                    'name' => $name, 'starts' => $startsOn, 'ends' => $endsOn, 'status' => $status,
                ]);
            } else {
                $termId = (string) $termId;
                $this->database->prepare(<<<'SQL'
UPDATE academic_terms SET name = :name, starts_on = :starts, ends_on = :ends, status = :status, origin = 'override', updated_at = UTC_TIMESTAMP(6)
WHERE id = :id
SQL)->execute(['name' => $name, 'starts' => $startsOn, 'ends' => $endsOn, 'status' => $status, 'id' => $termId]);
            }

            $this->audit->record($workspaceId, $actorUserId, 'academic_term.override', 'academic_term', $termId, 'success', [
                'term_key' => $termKey,
                'created' => $created,
            ]);

            return ['term_id' => $termId, 'created' => $created];
        });
    }

    /** @return array{0:int,1:int} [applied, skipped] */
    private function applyToActiveWorkspaces(
        string $institutionId,
        string $institutionTermId,
        string $termKey,
        string $name,
        string $startsOn,
        string $endsOn,
        string $status,
    ): array {
        $workspaces = $this->database->prepare(<<<'SQL'
SELECT workspace.id
FROM tenant_workspaces workspace
JOIN directory_cohorts cohort ON cohort.id = workspace.cohort_id
JOIN directory_programs program ON program.id = cohort.program_id
JOIN directory_faculties faculty ON faculty.id = program.faculty_id
WHERE faculty.institution_id = :institution
  AND workspace.status = 'active' AND workspace.archived_at IS NULL
SQL);
        $workspaces->execute(['institution' => $institutionId]);
        $applied = 0;
        $skipped = 0;
        foreach ($workspaces->fetchAll(PDO::FETCH_COLUMN) as $workspaceId) {
            if ($this->upsertWorkspaceTerm((string) $workspaceId, $termKey, $institutionTermId, $name, $startsOn, $endsOn, $status)) {
                $applied++;
            } else {
                $skipped++;
            }
        }
        return [$applied, $skipped];
    }

    /** @return bool true if the row was inherited-and-applied, false if an existing override was left alone */
    private function upsertWorkspaceTerm(
        string $workspaceId,
        string $termKey,
        string $institutionTermId,
        string $name,
        string $startsOn,
        string $endsOn,
        string $status,
    ): bool {
        $existing = $this->database->prepare('SELECT id, origin FROM academic_terms WHERE workspace_id = :workspace AND term_key = :key FOR UPDATE');
        $existing->execute(['workspace' => $workspaceId, 'key' => $termKey]);
        $row = $existing->fetch();

        if ($row !== false && $row['origin'] === 'override') {
            return false;
        }

        if ($row !== false) {
            $this->database->prepare(<<<'SQL'
UPDATE academic_terms
SET institution_term_id = :institution_term, name = :name, starts_on = :starts, ends_on = :ends, status = :status, origin = 'inherited', updated_at = UTC_TIMESTAMP(6)
WHERE id = :id
SQL)->execute([
                'institution_term' => $institutionTermId, 'name' => $name, 'starts' => $startsOn,
                'ends' => $endsOn, 'status' => $status, 'id' => $row['id'],
            ]);
            return true;
        }

        $this->database->prepare(<<<'SQL'
INSERT INTO academic_terms (id, workspace_id, term_key, institution_term_id, name, starts_on, ends_on, status, origin, created_at, updated_at)
VALUES (:id, :workspace, :key, :institution_term, :name, :starts, :ends, :status, 'inherited', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL)->execute([
            'id' => Uuid::v7(), 'workspace' => $workspaceId, 'key' => $termKey, 'institution_term' => $institutionTermId,
            'name' => $name, 'starts' => $startsOn, 'ends' => $endsOn, 'status' => $status,
        ]);
        return true;
    }

    private function requireText(string $value, int $min, int $max, string $field): string
    {
        $trimmed = trim($value);
        $length = mb_strlen($trimmed);
        if ($length < $min || $length > $max) {
            throw new PlatformException('invalid_term_field', "Field is invalid: {$field}.", 422);
        }
        return $trimmed;
    }

    private function requireCode(string $value, int $maxLength, string $field): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '';
        $normalized = trim($normalized, '-');
        if ($normalized === '' || strlen($normalized) > $maxLength) {
            throw new PlatformException('invalid_term_field', "Field is invalid: {$field}.", 422);
        }
        return $normalized;
    }

    private function requireDate(string $value, string $field): string
    {
        $value = trim($value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new PlatformException('invalid_term_field', "Field must be an ISO date (YYYY-MM-DD): {$field}.", 422);
        }
        [$year, $month, $day] = array_map('intval', explode('-', $value));
        if (!checkdate($month, $day, $year)) {
            throw new PlatformException('invalid_term_field', "Field is not a valid calendar date: {$field}.", 422);
        }
        return $value;
    }

    private function requireStatus(string $status): string
    {
        $status = trim($status);
        if (!in_array($status, ['planned', 'active', 'closed', 'archived'], true)) {
            throw new PlatformException('invalid_term_status', 'Term status is invalid.', 422);
        }
        return $status;
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
