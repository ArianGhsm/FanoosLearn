<?php
declare(strict_types=1);

/**
 * Stable creation-time index for exam collections that predate explicit
 * `addedAt` metadata. Values were captured from the original collection
 * entry files and must not be regenerated from deploy-time file mtimes.
 *
 * New collections should declare `addedAt` in their course definition. The
 * static checker rejects any collection that has neither explicit metadata
 * nor an entry in this legacy index.
 */
function dent_exams_catalog_legacy_added_at_index(): array
{
    return [
        'partialprosthesis' => '2026-06-22T11:03:26+03:30',
        'completeprosthesis' => '2026-06-22T11:03:26+03:30',
        'pharmacology' => '2026-06-22T11:03:26+03:30',
        'systemicdiseases' => '2026-06-22T11:03:26+03:30',
        'radiology1' => '2026-06-09T18:07:39+03:30',
        'morphology' => '2026-06-09T18:07:39+03:30',
        'endotorabinejad' => '2026-06-09T18:07:39+03:30',
        'radiology2' => '2026-06-22T11:03:26+03:30',
        'radiology2-whitepharoah' => '2026-06-09T18:07:39+03:30',
        'endotorabinejad-1-5' => '2026-06-09T18:07:39+03:30',
        'endotorabinejad-6-10' => '2026-06-09T18:07:39+03:30',
        'endotorabinejad-11-15' => '2026-06-09T18:07:39+03:30',
        'dental-materials-foundations' => '2026-06-11T17:13:33+03:30',
        'dental-materials-midterm-sample' => '2026-06-27T09:50:30+03:30',
        'diagnostics-1-term6' => '2026-06-06T10:12:41+03:30',
        'gerontology-term-6' => '2026-06-11T17:13:33+03:30',
        'burket-oral-medicine-1-5' => '2026-06-17T23:02:50+03:30',
        'burket-oral-medicine-11-15' => '2026-06-17T23:02:50+03:30',
        'burket-oral-medicine-16-20' => '2026-06-17T23:02:50+03:30',
        'burket-oral-medicine-21-25' => '2026-06-17T23:02:50+03:30',
        'burket-oral-medicine-25-29' => '2026-06-17T23:02:50+03:30',
        'burket-oral-medicine-6-10' => '2026-06-17T23:02:50+03:30',
        'burket-oral-medicine' => '2026-06-17T23:02:50+03:30',
        'craig-dental-materials-1-5' => '2026-06-17T23:02:50+03:30',
        'craig-dental-materials-11-16' => '2026-06-17T23:02:50+03:30',
        'craig-dental-materials-6-10' => '2026-06-17T23:02:50+03:30',
        'craig-dental-materials' => '2026-06-17T23:02:50+03:30',
        'falace-medically-compromised-1-5' => '2026-06-17T23:02:50+03:30',
        'falace-medically-compromised-11-15' => '2026-06-17T23:02:50+03:30',
        'falace-medically-compromised-16-20' => '2026-06-17T23:02:50+03:30',
        'falace-medically-compromised-21-25' => '2026-06-17T23:02:50+03:30',
        'falace-medically-compromised-26-29' => '2026-06-17T23:02:50+03:30',
        'falace-medically-compromised-6-10' => '2026-06-17T23:02:50+03:30',
        'falace-medically-compromised' => '2026-06-17T23:02:50+03:30',
        'malamed-local-anesthesia-1-5' => '2026-06-22T11:02:21+03:30',
        'malamed-local-anesthesia-11-15' => '2026-06-17T23:02:50+03:30',
        'malamed-local-anesthesia-16-21' => '2026-06-22T11:02:21+03:30',
        'malamed-local-anesthesia-6-10' => '2026-06-22T11:02:21+03:30',
        'malamed-local-anesthesia' => '2026-06-22T11:03:26+03:30',
        'malamed-medical-emergencies-1-5' => '2026-06-17T23:02:51+03:30',
        'malamed-medical-emergencies-11-15' => '2026-06-17T23:02:51+03:30',
        'malamed-medical-emergencies-21-25' => '2026-06-17T23:02:51+03:30',
        'malamed-medical-emergencies-26-31' => '2026-06-17T23:02:51+03:30',
        'malamed-medical-emergencies-6-10' => '2026-06-17T23:02:51+03:30',
        'malamed-medical-emergencies' => '2026-06-17T23:02:51+03:30',
        'peterson-oral-surgery-1-5' => '2026-06-22T11:02:21+03:30',
        'peterson-oral-surgery-11-15' => '2026-06-22T11:03:26+03:30',
        'peterson-oral-surgery-16-20' => '2026-06-22T11:03:26+03:30',
        'peterson-oral-surgery-21-25' => '2026-06-22T11:03:26+03:30',
        'peterson-oral-surgery-26-31' => '2026-06-17T23:02:51+03:30',
        'peterson-oral-surgery-6-10' => '2026-06-22T11:02:21+03:30',
        'peterson-oral-surgery' => '2026-06-22T11:03:26+03:30',
        'sturdevant-operative-dentistry-1-4' => '2026-06-17T23:02:51+03:30',
        'sturdevant-operative-dentistry-13-14' => '2026-06-17T23:02:51+03:30',
        'sturdevant-operative-dentistry-5-8' => '2026-06-17T23:02:51+03:30',
        'sturdevant-operative-dentistry-9-12' => '2026-06-17T23:02:51+03:30',
        'restorative-theory-1-term6' => '2026-07-06T21:03:27+03:30',
        'restorative-theory-1-midterm-sample' => '2026-07-08T20:57:15+03:30',
        'sturdevant-operative-dentistry' => '2026-06-17T23:02:51+03:30',
        'summit-operative-dentistry-1-5' => '2026-06-22T11:02:21+03:30',
        'summit-operative-dentistry-11-15' => '2026-06-22T11:02:22+03:30',
        'summit-operative-dentistry-16-21' => '2026-06-22T11:02:22+03:30',
        'summit-operative-dentistry-6-10' => '2026-06-22T11:02:22+03:30',
        'summit-operative-dentistry' => '2026-06-22T11:02:22+03:30',
        'vannoort-dental-materials-section-1' => '2026-06-17T23:02:51+03:30',
        'vannoort-dental-materials-section-2' => '2026-06-17T23:02:51+03:30',
        'vannoort-dental-materials-section-3' => '2026-06-17T23:02:51+03:30',
        'vannoort-dental-materials' => '2026-06-17T23:02:51+03:30',
        'whitepharoah-radiology-term6-1-5' => '2026-06-17T23:02:51+03:30',
        'whitepharoah-radiology-term6-11-15' => '2026-06-17T23:02:51+03:30',
        'whitepharoah-radiology-term6-16-20' => '2026-06-22T11:02:22+03:30',
        'whitepharoah-radiology-term6-21-25' => '2026-06-22T11:02:22+03:30',
        'whitepharoah-radiology-term6-26-30' => '2026-06-22T11:02:22+03:30',
        'whitepharoah-radiology-term6-31-33' => '2026-06-17T23:02:51+03:30',
        'whitepharoah-radiology-term6-6-10' => '2026-06-17T23:02:51+03:30',
        'whitepharoah-radiology-term6' => '2026-06-22T11:03:26+03:30',
        'zarb-complete-prosthodontics-1-5' => '2026-06-17T23:02:51+03:30',
        'zarb-complete-prosthodontics-11-15' => '2026-06-17T23:02:51+03:30',
        'zarb-complete-prosthodontics-16-20' => '2026-06-17T23:02:51+03:30',
        'zarb-complete-prosthodontics-21-23' => '2026-06-17T23:02:51+03:30',
        'zarb-complete-prosthodontics-6-10' => '2026-06-17T23:02:51+03:30',
        'zarb-complete-prosthodontics' => '2026-06-17T23:02:51+03:30',
    ];
}

function dent_exams_catalog_normalize_added_at($value): string
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($raw))->format(DATE_ATOM);
    } catch (Throwable $error) {
        return '';
    }
}

function dent_exams_catalog_course_added_at(string $courseSlug, array $course = []): string
{
    $explicit = dent_exams_catalog_normalize_added_at($course['addedAt'] ?? '');
    if ($explicit !== '') {
        return $explicit;
    }

    $index = dent_exams_catalog_legacy_added_at_index();
    return dent_exams_catalog_normalize_added_at($index[$courseSlug] ?? '');
}

function dent_exams_catalog_exam_added_at(array $exam, string $courseAddedAt): string
{
    $explicit = dent_exams_catalog_normalize_added_at($exam['addedAt'] ?? '');
    return $explicit !== '' ? $explicit : $courseAddedAt;
}
