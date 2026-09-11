<?php
declare(strict_types=1);

require_once __DIR__ . '/exams_morphology_data.php';

function dent_exams_apply_morphology_catalog_overrides(array $bank): array
{
    $courses = $bank['catalogs']['shared']['courses'] ?? null;
    if (!is_array($courses)) {
        return $bank;
    }

    $morphologyCourse = dent_exams_morphology_course();
    $rebuilt = [];
    $inserted = false;

    foreach ($courses as $slug => $course) {
        if (!$inserted && $slug === 'radiology2') {
            $rebuilt['morphology'] = $morphologyCourse;
            $inserted = true;
        }

        $rebuilt[$slug] = $course;
    }

    if (!$inserted) {
        $rebuilt['morphology'] = $morphologyCourse;
    }

    $bank['catalogs']['shared']['courses'] = $rebuilt;
    return $bank;
}
