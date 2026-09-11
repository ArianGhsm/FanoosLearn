<?php
declare(strict_types=1);

require_once __DIR__ . '/text_source_parser.php';

function dent_exams_dental_materials_foundations_course(): array
{
    static $course = null;
    if (is_array($course)) {
        return $course;
    }

    $course = dent_exams_text_source_build_course([
        'courseSlug' => 'dental-materials-foundations',
        'title' => 'مبانی مواد دندانی',
        'shortTitle' => 'مواد دندانی',
        'termLabel' => 'ترم ۶',
        'dataDir' => __DIR__ . '/data/dental_materials_foundations',
        'paymentAmount' => 300000,
        'examDefinitions' => [
            ['slug' => '1-power', 'label' => 'جلسه ۱ - آزمون از پاورپوینت', 'patterns' => ['session1_power*.txt']],
            ['slug' => '1-jozve', 'label' => 'جلسه ۱ - آزمون از جزوه', 'patterns' => ['session1_jozve*.txt']],
            ['slug' => '2-power', 'label' => 'جلسه ۲ - آزمون از پاورپوینت', 'patterns' => ['session2_power*.txt']],
            ['slug' => '2-jozve', 'label' => 'جلسه ۲ - آزمون از جزوه', 'patterns' => ['session2_jozve*.txt']],
            ['slug' => '3-4-power', 'label' => 'جلسه ۳ و ۴ - آزمون از پاورپوینت', 'patterns' => ['session3_4_power*.txt']],
            ['slug' => '3-4-jozve', 'label' => 'جلسه ۳ و ۴ - آزمون از جزوه', 'patterns' => ['session3_4_jozve*.txt']],
            ['slug' => '5-power', 'label' => 'جلسه ۵ - آزمون از پاورپوینت', 'patterns' => ['session5_power*.txt']],
            ['slug' => '5-jozve', 'label' => 'جلسه ۵ - آزمون از جزوه', 'patterns' => ['session5_jozve*.txt']],
            ['slug' => '6-power', 'label' => 'جلسه ۶ - آزمون از پاورپوینت', 'patterns' => ['session6_power*.txt']],
            ['slug' => '6-jozve', 'label' => 'جلسه ۶ - آزمون از جزوه', 'patterns' => ['session6_jozve*.txt']],
            ['slug' => '7-power', 'label' => 'جلسه ۷ - آزمون از پاورپوینت', 'patterns' => ['session7_power*.txt']],
            ['slug' => '7-jozve', 'label' => 'جلسه ۷ - آزمون از جزوه', 'patterns' => ['session7_jozve*.txt']],
        ],
    ]);

    return $course;
}
