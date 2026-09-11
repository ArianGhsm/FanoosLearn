<?php
declare(strict_types=1);

require_once __DIR__ . '/exams_medical_emergencies_term6_final_sample_data.php';

function dent_exams_apply_medical_emergencies_term6_final_sample_catalog_overrides(array $bank): array
{
    if (function_exists('dent_requested_cohort_key') && function_exists('dent_is_prosthesis_cohort_key')
        && dent_is_prosthesis_cohort_key(dent_requested_cohort_key())) {
        return $bank;
    }
    $courses = $bank['catalogs']['shared']['courses'] ?? null;
    $course = dent_exams_medical_emergencies_term6_final_sample_course();
    $slug = trim((string) ($course['slug'] ?? ''));
    if (!is_array($courses) || $slug === '' || !is_array($course['exams'] ?? null)) {
        return $bank;
    }
    $courses[$slug] = $course;
    $bank['catalogs']['shared']['courses'] = $courses;
    return $bank;
}
