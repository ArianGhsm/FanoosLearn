<?php
declare(strict_types=1);

require_once __DIR__ . '/exams_bank_helpers.php';

function dent_exams_require_optional_module(string $filename): void
{
    $path = __DIR__ . '/' . ltrim($filename, '\\/');
    if (is_file($path)) {
        require_once $path;
    }
}

function dent_exams_bootstrap_modules(): void
{
    static $bootstrapped = false;
    if ($bootstrapped) {
        return;
    }

    $bootstrapped = true;

    require_once __DIR__ . '/exams_radiology2_overrides.php';
    dent_exams_require_optional_module('exams_radiology1_overrides.php');
    dent_exams_require_optional_module('exams_morphology_overrides.php');
    dent_exams_require_optional_module('exams_endotorabinejad_overrides.php');
    dent_exams_require_optional_module('exams_radiology2_whitepharoah_overrides.php');
    dent_exams_require_optional_module('exams_dental_materials_foundations_overrides.php');
    dent_exams_require_optional_module('exams_dental_materials_midterm_sample_overrides.php');
    dent_exams_require_optional_module('exams_dental_materials_final_sample_overrides.php');
    dent_exams_require_optional_module('exams_diagnostics1_term6_overrides.php');
    dent_exams_require_optional_module('exams_diagnostics2_term6_overrides.php');
    dent_exams_require_optional_module('exams_diagnostics2_final_sample_overrides.php');
    dent_exams_require_optional_module('exams_gerontology_term6_overrides.php');
    dent_exams_require_optional_module('exams_gerontology_term6_final_sample_overrides.php');
    dent_exams_require_optional_module('exams_equipment_ergonomics_term6_final_sample_overrides.php');
    dent_exams_require_optional_module('exams_medical_emergencies_term6_final_sample_overrides.php');
    dent_exams_require_optional_module('exams_term6_reference_overrides.php');
    dent_exams_require_optional_module('exams_complete_foundations_theory_midterm_practice_overrides.php');
    dent_exams_require_optional_module('exams_complete_prosthodontics_midterm_sample_overrides.php');
    dent_exams_require_optional_module('exams_restorative_theory1_term6_overrides.php');
    dent_exams_require_optional_module('exams_restorative_theory1_midterm_sample_overrides.php');
    dent_exams_require_optional_module('exams_restorative_theory1_final_practice_overrides.php');
    dent_exams_require_optional_module('exams_restorative_theory1_final_sample_overrides.php');
    dent_exams_require_optional_module('exams_surgery_practical1_exit_overrides.php');
}

function dent_exams_bootstrap_runtime_modules(): void
{
    static $bootstrapped = false;
    if ($bootstrapped) {
        return;
    }

    $bootstrapped = true;

    // These modules hydrate question arrays that are intentionally omitted
    // from the compiled catalog cache. Load them only for an exam attempt.
    dent_exams_require_optional_module('exams_complete_foundations_theory_midterm_practice_overrides.php');
    dent_exams_require_optional_module('exams_complete_prosthodontics_midterm_sample_overrides.php');
    dent_exams_require_optional_module('exams_diagnostics2_term6_overrides.php');
    dent_exams_require_optional_module('exams_diagnostics2_final_sample_overrides.php');
    dent_exams_require_optional_module('exams_term6_reference_overrides.php');
}

function dent_exams_registered_catalog_override_callbacks(): array
{
    return [
        'dent_exams_apply_radiology1_catalog_overrides',
        'dent_exams_apply_morphology_catalog_overrides',
        'dent_exams_apply_endotorabinejad_catalog_overrides',
        'dent_exams_apply_radiology2_whitepharoah_catalog_overrides',
        'dent_exams_apply_dental_materials_foundations_catalog_overrides',
        'dent_exams_apply_dental_materials_midterm_sample_catalog_overrides',
        'dent_exams_apply_dental_materials_final_sample_catalog_overrides',
        'dent_exams_apply_diagnostics1_term6_catalog_overrides',
        'dent_exams_apply_diagnostics2_term6_catalog_overrides',
        'dent_exams_apply_diagnostics2_final_sample_catalog_overrides',
        'dent_exams_apply_gerontology_term6_catalog_overrides',
        'dent_exams_apply_gerontology_term6_final_sample_catalog_overrides',
        'dent_exams_apply_equipment_ergonomics_term6_final_sample_catalog_overrides',
        'dent_exams_apply_medical_emergencies_term6_final_sample_catalog_overrides',
        'dent_exams_apply_term6_reference_catalog_overrides',
        'dent_exams_apply_complete_foundations_theory_midterm_practice_catalog_overrides',
        'dent_exams_apply_complete_prosthodontics_midterm_sample_catalog_overrides',
        'dent_exams_apply_restorative_theory1_term6_catalog_overrides',
        'dent_exams_apply_restorative_theory1_midterm_sample_catalog_overrides',
        'dent_exams_apply_restorative_theory1_final_practice_catalog_overrides',
        'dent_exams_apply_restorative_theory1_final_sample_catalog_overrides',
        'dent_exams_apply_surgery_practical1_exit_catalog_overrides',
        'dent_exams_apply_radiology2_overrides',
    ];
}

/**
 * Applies the current site-wide standard exam price without touching courses
 * that have a different explicit price. Persisted owner settings are migrated
 * separately through the canonical ownerSaveCourseAccess API action.
 */
function dent_exams_apply_standard_price_policy(array $bank): array
{
    $catalogs = $bank['catalogs'] ?? null;
    if (!is_array($catalogs)) {
        return $bank;
    }

    foreach ($catalogs as $catalogKey => $catalog) {
        $courses = $catalog['courses'] ?? null;
        if (!is_array($courses)) {
            continue;
        }

        foreach ($courses as $courseSlug => $course) {
            if (!is_array($course) || (int) ($course['defaultAmount'] ?? 0) !== 300000) {
                continue;
            }

            $course['defaultAmount'] = 450000;
            foreach (['heroDescription', 'paymentDescription'] as $field) {
                if (!is_string($course[$field] ?? null)) {
                    continue;
                }
                $course[$field] = str_replace(
                    ['۳۰ هزار تومان', '30 هزار تومان'],
                    ['۴۵ هزار تومان', '45 هزار تومان'],
                    $course[$field]
                );
            }
            $courses[$courseSlug] = $course;
        }

        $catalog['courses'] = $courses;
        $catalogs[$catalogKey] = $catalog;
    }

    $bank['catalogs'] = $catalogs;
    return $bank;
}

function dent_exams_apply_registered_catalog_overrides(array $bank): array
{
    dent_exams_bootstrap_modules();

    foreach (dent_exams_registered_catalog_override_callbacks() as $callback) {
        if (function_exists($callback)) {
            $bank = $callback($bank);
        }
    }

    $bank = dent_exams_apply_standard_price_policy($bank);
    return dent_exams_sync_bank_question_counts($bank);
}

// Keep the small on-demand hydrators available to callers without loading the
// multi-megabyte catalog override set on every request.
dent_exams_bootstrap_runtime_modules();
