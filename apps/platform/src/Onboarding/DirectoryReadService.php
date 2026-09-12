<?php

declare(strict_types=1);

namespace Fanoos\Platform\Onboarding;

use Fanoos\Platform\Support\PlatformException;
use PDO;

/**
 * Paginated, read-only projection of the directory chain (country -> province ->
 * city -> institution -> faculty -> program) for the onboarding catalog and any
 * other channel that needs to let someone browse the directory before they are a
 * member of anything. Mirrors the legacy dent_bot_onboarding_catalog province ->
 * institution narrowing (legacy/bot/dent_bot/onboarding.py paginated 10 per page).
 *
 * This is deliberately a bare read: no workspace_id anywhere in these queries or
 * their results, because directory_* rows sit above tenant_workspaces in the
 * chain and are shared platform reference data, not tenant-owned. Cohorts and
 * workspaces are intentionally not exposed here -- surfacing them would leak
 * which classes exist under a program to a caller with no membership in any of
 * them, which is exactly the isolation boundary this service must not cross.
 */
final class DirectoryReadService
{
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 50;

    public function __construct(private readonly PDO $database)
    {
    }

    /** @return array{items:list<array<string,mixed>>,next_cursor:?string} */
    public function provinces(int $limit = self::DEFAULT_LIMIT, ?string $cursor = null): array
    {
        $limit = $this->boundedLimit($limit);
        $offset = $this->decodeCursor($cursor);
        $query = $this->database->prepare(<<<'SQL'
SELECT province.id, province.code, province.name, country.code AS country_code
FROM directory_provinces province
JOIN directory_countries country ON country.id = province.country_id AND country.status = 'active' AND country.archived_at IS NULL
WHERE province.status = 'active' AND province.archived_at IS NULL
ORDER BY province.name, province.id
LIMIT :limit OFFSET :offset
SQL);
        $query->bindValue(':limit', $limit + 1, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();
        return $this->paged($query->fetchAll(), $limit, $offset);
    }

    /** @return array{items:list<array<string,mixed>>,next_cursor:?string} */
    public function institutionsByProvince(string $provinceId, int $limit = self::DEFAULT_LIMIT, ?string $cursor = null): array
    {
        $this->requireExists('directory_provinces', $provinceId, 'province_not_found', 'Province was not found.');
        $limit = $this->boundedLimit($limit);
        $offset = $this->decodeCursor($cursor);
        $query = $this->database->prepare(<<<'SQL'
SELECT institution.id, institution.slug, institution.name, institution.institution_type,
       city.id AS city_id, city.code AS city_code, city.name AS city_name
FROM directory_institutions institution
JOIN directory_cities city ON city.id = institution.city_id AND city.province_id = :province
 AND city.status = 'active' AND city.archived_at IS NULL
WHERE institution.status = 'active' AND institution.archived_at IS NULL
ORDER BY institution.name, institution.id
LIMIT :limit OFFSET :offset
SQL);
        $query->bindValue(':province', $provinceId);
        $query->bindValue(':limit', $limit + 1, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();
        return $this->paged($query->fetchAll(), $limit, $offset);
    }

    /** @return array{items:list<array<string,mixed>>,next_cursor:?string} */
    public function facultiesByInstitution(string $institutionId, int $limit = self::DEFAULT_LIMIT, ?string $cursor = null): array
    {
        $this->requireExists('directory_institutions', $institutionId, 'institution_not_found', 'Institution was not found.');
        $limit = $this->boundedLimit($limit);
        $offset = $this->decodeCursor($cursor);
        $query = $this->database->prepare(<<<'SQL'
SELECT faculty.id, faculty.code, faculty.name
FROM directory_faculties faculty
WHERE faculty.institution_id = :institution AND faculty.status = 'active' AND faculty.archived_at IS NULL
ORDER BY faculty.name, faculty.id
LIMIT :limit OFFSET :offset
SQL);
        $query->bindValue(':institution', $institutionId);
        $query->bindValue(':limit', $limit + 1, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();
        return $this->paged($query->fetchAll(), $limit, $offset);
    }

    /** @return array{items:list<array<string,mixed>>,next_cursor:?string} */
    public function programsByFaculty(string $facultyId, int $limit = self::DEFAULT_LIMIT, ?string $cursor = null): array
    {
        $this->requireExists('directory_faculties', $facultyId, 'faculty_not_found', 'Faculty was not found.');
        $limit = $this->boundedLimit($limit);
        $offset = $this->decodeCursor($cursor);
        $query = $this->database->prepare(<<<'SQL'
SELECT program.id, program.code, program.name, program.degree_level
FROM directory_programs program
WHERE program.faculty_id = :faculty AND program.status = 'active' AND program.archived_at IS NULL
ORDER BY program.name, program.id
LIMIT :limit OFFSET :offset
SQL);
        $query->bindValue(':faculty', $facultyId);
        $query->bindValue(':limit', $limit + 1, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();
        return $this->paged($query->fetchAll(), $limit, $offset);
    }

    /**
     * Cohorts under a program that already have a live class (tenant_workspaces
     * row) to join. This is a narrower, deliberately different read than the
     * province/institution/faculty/program methods above: those never expose
     * cohort or workspace identity, because a caller with no membership browsing
     * the catalog has no legitimate reason to enumerate which classes exist. A
     * join wizard is the opposite case -- the caller has already narrowed down
     * to one specific program and is asking "does my entry year actually have a
     * class", which is the wizard's whole purpose. A cohort with no matching
     * workspace is not a class anyone can join yet, so it is omitted entirely
     * rather than offered and then failing at submission.
     *
     * @return array{items:list<array<string,mixed>>,next_cursor:?string}
     */
    public function joinableCohortsByProgram(string $programId, int $limit = self::DEFAULT_LIMIT, ?string $cursor = null): array
    {
        $this->requireExists('directory_programs', $programId, 'program_not_found', 'Program was not found.');
        $limit = $this->boundedLimit($limit);
        $offset = $this->decodeCursor($cursor);
        $query = $this->database->prepare(<<<'SQL'
SELECT cohort.id, cohort.entry_year, cohort.label, workspace.id AS workspace_id, workspace.name AS workspace_name
FROM directory_cohorts cohort
JOIN tenant_workspaces workspace ON workspace.cohort_id = cohort.id
 AND workspace.status = 'active' AND workspace.archived_at IS NULL
WHERE cohort.program_id = :program AND cohort.status = 'active' AND cohort.archived_at IS NULL
ORDER BY cohort.entry_year DESC, cohort.id
LIMIT :limit OFFSET :offset
SQL);
        $query->bindValue(':program', $programId);
        $query->bindValue(':limit', $limit + 1, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();
        return $this->paged($query->fetchAll(), $limit, $offset);
    }

    private function requireExists(string $table, string $id, string $errorCode, string $message): void
    {
        if (!preg_match('/^[a-z_]+$/', $table)) {
            throw new PlatformException('invalid_directory_lookup', 'Directory lookup table is invalid.', 500);
        }
        $query = $this->database->prepare("SELECT 1 FROM {$table} WHERE id = :id AND status = 'active' AND archived_at IS NULL");
        $query->bindValue(':id', $id);
        $query->execute();
        if ($query->fetchColumn() === false) {
            throw new PlatformException($errorCode, $message, 404);
        }
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
