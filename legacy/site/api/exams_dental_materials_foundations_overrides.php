<?php
declare(strict_types=1);

require_once __DIR__ . '/exams_dental_materials_foundations_data.php';

function dent_exams_apply_dental_materials_foundations_catalog_overrides(array $bank): array
{
    if (function_exists('dent_requested_cohort_key') && function_exists('dent_is_prosthesis_cohort_key')) {
        $requestedCohort = dent_requested_cohort_key();
        if (dent_is_prosthesis_cohort_key($requestedCohort)) {
            return $bank;
        }
    }

    $courses = $bank['catalogs']['shared']['courses'] ?? null;
    if (!is_array($courses)) {
        return $bank;
    }

    $course = dent_exams_dental_materials_foundations_course();
    $courseSlug = trim((string) ($course['slug'] ?? ''));
    if ($courseSlug === '') {
        return $bank;
    }

    $courses[$courseSlug] = $course;
    $bank['catalogs']['shared']['courses'] = $courses;

    return $bank;
}
