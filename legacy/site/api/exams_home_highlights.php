<?php
declare(strict_types=1);

require_once __DIR__ . '/exams_home_highlights_index.php';

function dent_exams_home_highlights_catalog_key_for_cohort(string $cohortKey): string
{
    $index = dent_exams_home_highlights_index();
    $cleanCohort = dent_clean_cohort_key($cohortKey);
    $byCohort = is_array($index['catalogByCohort'] ?? null) ? $index['catalogByCohort'] : [];
    $catalogKey = trim((string) ($byCohort[$cleanCohort] ?? ($index['defaultCatalogKey'] ?? '')));

    return $catalogKey;
}

function dent_exams_home_highlights_course_has_expired(array $course, ?int $now = null): bool
{
    $curriculum = is_array($course['curriculum'] ?? null) ? $course['curriculum'] : [];
    $finalExam = is_array($curriculum['finalExam'] ?? null) ? $curriculum['finalExam'] : [];
    $expiresAt = trim((string) ($finalExam['expiresAt'] ?? ''));
    if ($expiresAt === '') {
        return false;
    }

    $timestamp = strtotime($expiresAt);
    if ($timestamp === false) {
        return false;
    }

    return ($now ?? time()) >= $timestamp;
}

function dent_exams_home_highlights_courses_for_cohort(string $cohortKey, ?int $now = null): array
{
    $index = dent_exams_home_highlights_index();
    $catalogKey = dent_exams_home_highlights_catalog_key_for_cohort($cohortKey);
    $catalogs = is_array($index['catalogs'] ?? null) ? $index['catalogs'] : [];
    $courses = is_array($catalogs[$catalogKey] ?? null) ? $catalogs[$catalogKey] : [];

    // The generated list intentionally contains only the two newest courses.
    // When either expires, do not backfill it with an older catalog entry.
    return array_values(array_filter($courses, static function ($course) use ($now): bool {
        return is_array($course) && !dent_exams_home_highlights_course_has_expired($course, $now);
    }));
}
