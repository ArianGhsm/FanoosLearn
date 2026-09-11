<?php
declare(strict_types=1);

require_once __DIR__ . '/exams_radiology2_whitepharoah_data.php';

function dent_exams_apply_radiology2_whitepharoah_catalog_overrides(array $bank): array
{
    $courses = $bank['catalogs']['shared']['courses'] ?? null;
    if (!is_array($courses)) {
        return $bank;
    }

    $course = dent_exams_radiology2_whitepharoah_course();
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

        $rebuilt[$slug] = $existingCourse;
        if ($slug === 'radiology2') {
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
