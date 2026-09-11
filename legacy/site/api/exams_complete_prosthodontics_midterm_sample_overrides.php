<?php
declare(strict_types=1);

require_once __DIR__ . '/exams_complete_prosthodontics_midterm_sample_data.php';

function dent_exams_apply_complete_prosthodontics_midterm_sample_catalog_overrides(array $bank): array
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

    $course = dent_exams_complete_prosthodontics_midterm_sample_course(false);
    $courseSlug = trim((string) ($course['slug'] ?? ''));
    if ($courseSlug === '') {
        return $bank;
    }

    $rebuilt = [];
    $inserted = false;

    foreach ($courses as $slug => $existingCourse) {
        if ($slug === $courseSlug) {
            continue;
        }

        $rebuilt[$slug] = $existingCourse;

        if (!$inserted && $slug === 'complete-foundations-theory-midterm-practice') {
            $rebuilt[$courseSlug] = $course;
            $inserted = true;
        }
    }

    if (!$inserted) {
        $rebuilt[$courseSlug] = $course;
    }

    $bank['catalogs']['shared']['courses'] = $rebuilt;
    return $bank;
}
