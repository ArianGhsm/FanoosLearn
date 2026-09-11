<?php
declare(strict_types=1);

require_once __DIR__ . '/exams_diagnostics1_term6_data.php';

function dent_exams_apply_diagnostics1_term6_catalog_overrides(array $bank): array
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

    $course = dent_exams_diagnostics1_term6_course();
    $courseSlug = trim((string) ($course['slug'] ?? ''));
    if ($courseSlug === '' || !is_array($course['exams'] ?? null)) {
        return $bank;
    }

    $rebuilt = [];
    $inserted = false;

    foreach ($courses as $slug => $existingCourse) {
        if ($slug === $courseSlug) {
            continue;
        }

        if (!$inserted && $slug === 'zarb-complete-prosthodontics') {
            $rebuilt[$courseSlug] = $course;
            $inserted = true;
        }

        $rebuilt[$slug] = $existingCourse;
    }

    if (!$inserted) {
        $rebuilt[$courseSlug] = $course;
    }

    $bank['catalogs']['shared']['courses'] = $rebuilt;
    return $bank;
}
