<?php
declare(strict_types=1);

require_once __DIR__ . '/exams_surgery_practical1_exit_data.php';

function dent_exams_apply_surgery_practical1_exit_catalog_overrides(array $bank): array
{
    if (function_exists('dent_requested_cohort_key') && function_exists('dent_is_prosthesis_cohort_key')) {
        if (dent_is_prosthesis_cohort_key(dent_requested_cohort_key())) {
            return $bank;
        }
    }

    $courses = $bank['catalogs']['shared']['courses'] ?? null;
    $course = dent_exams_surgery_practical1_exit_course();
    $slug = trim((string) ($course['slug'] ?? ''));
    if (!is_array($courses) || $slug === '' || !is_array($course['exams'] ?? null)) {
        return $bank;
    }

    $rebuilt = [];
    $inserted = false;
    foreach ($courses as $existingSlug => $existingCourse) {
        if ($existingSlug === $slug) {
            continue;
        }
        if (!$inserted && $existingSlug === 'peterson-oral-surgery') {
            $rebuilt[$slug] = $course;
            $inserted = true;
        }
        $rebuilt[$existingSlug] = $existingCourse;
    }
    if (!$inserted) {
        $rebuilt[$slug] = $course;
    }

    $bank['catalogs']['shared']['courses'] = $rebuilt;
    return $bank;
}
