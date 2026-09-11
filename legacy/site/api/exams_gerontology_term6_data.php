<?php
declare(strict_types=1);

require_once __DIR__ . '/text_source_parser.php';

function dent_exams_gerontology_term6_course(): array
{
    static $course = null;
    if (is_array($course)) {
        return $course;
    }

    $course = dent_exams_text_source_build_course([
        'courseSlug' => 'gerontology-term-6',
        'title' => 'آزمون‌های تمرینی پایان‌ترم سالمندشناسی',
        'shortTitle' => 'تمرینی پایان‌ترم سالمندشناسی',
        'termLabel' => 'ترم ۶',
        'dataDir' => __DIR__ . '/data/gerontology_term6',
        'paymentAmount' => 300000,
        'examDefinitions' => [
            ['slug' => '1', 'label' => 'جلسه ۱', 'patterns' => ['session1.txt'], 'parser' => 'dent_exams_text_source_parse_detailed_file'],
            ['slug' => '2', 'label' => 'جلسه ۲', 'patterns' => ['session2.txt'], 'parser' => 'dent_exams_text_source_parse_detailed_file'],
            ['slug' => '3', 'label' => 'جلسه ۳', 'patterns' => ['session3.txt'], 'parser' => 'dent_exams_text_source_parse_detailed_file'],
            ['slug' => '4', 'label' => 'جلسه ۴', 'patterns' => ['session4.txt'], 'parser' => 'dent_exams_text_source_parse_detailed_file'],
            ['slug' => '5', 'label' => 'جلسه ۵', 'patterns' => ['session5.txt'], 'parser' => 'dent_exams_text_source_parse_detailed_file'],
            ['slug' => '6', 'label' => 'جلسه ۶', 'patterns' => ['session6.txt'], 'parser' => 'dent_exams_text_source_parse_detailed_file'],
            ['slug' => '7', 'label' => 'جلسه ۷', 'patterns' => ['session7.txt'], 'parser' => 'dent_exams_text_source_parse_detailed_file'],
            ['slug' => '8', 'label' => 'جلسه ۸', 'patterns' => ['session8.txt'], 'parser' => 'dent_exams_text_source_parse_detailed_file'],
            ['slug' => '9', 'label' => 'جلسه ۹', 'patterns' => ['session9.txt'], 'parser' => 'dent_exams_text_source_parse_detailed_file'],
            ['slug' => '10', 'label' => 'جلسه ۱۰', 'patterns' => ['session10.txt'], 'parser' => 'dent_exams_text_source_parse_detailed_file'],
        ],
    ]);

    $course['addedAt'] = '2026-08-07T02:15:00+03:30';
    $course['cardDescription'] = '۱۰ آزمون تمرینی پایان‌ترم سالمندشناسی، جلسه‌به‌جلسه و با پاسخ تشریحی.';
    $course['heroTitle'] = 'آزمون‌های تمرینی پایان‌ترم سالمندشناسی';
    $course['paymentTitle'] = 'دسترسی به آزمون‌های تمرینی پایان‌ترم سالمندشناسی';
    $course['paymentDescription'] = 'با یک بار پرداخت ۳۰ هزار تومان، هر ۱۰ آزمون تمرینی پایان‌ترم برای همین حساب فعال می‌شود.';
    foreach ($course['exams'] as &$exam) {
        $exam['addedAt'] = $course['addedAt'];
    }
    unset($exam);

    return $course;
}
