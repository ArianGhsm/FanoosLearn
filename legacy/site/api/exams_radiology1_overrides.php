<?php
declare(strict_types=1);

require_once __DIR__ . '/exams_radiology1_data.php';

function dent_exams_apply_radiology1_catalog_overrides(array $bank): array
{
    $courses = $bank['catalogs']['shared']['courses'] ?? null;
    if (!is_array($courses)) {
        return $bank;
    }

    $course = dent_exams_radiology1_course();
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

        if (!$inserted && ($slug === 'morphology' || $slug === 'radiology2')) {
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
