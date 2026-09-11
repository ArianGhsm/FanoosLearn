<?php
declare(strict_types=1);

require_once __DIR__ . '/text_source_parser.php';

function dent_exams_restorative_theory1_term6_course(): array
{
    static $course = null;
    if (is_array($course)) {
        return $course;
    }

    $course = dent_exams_text_source_build_course([
        'courseSlug' => 'restorative-theory-1-term6',
        'title' => 'ترمیمی نظری ۱',
        'shortTitle' => 'ترمیمی نظری ۱',
        'termLabel' => 'ترم ۶',
        'dataDir' => __DIR__ . '/data/restorative_theory1_term6',
        'paymentAmount' => 300000,
        'examDefinitions' => [
            [
                'slug' => '1',
                'label' => 'جلسه ۱',
                'topic' => 'آشنایی با قوانین Adhesion و باندینگ‌ها',
                'patterns' => ['session1*.txt'],
                'parser' => 'dent_exams_text_source_parse_file_multiline',
            ],
            [
                'slug' => '2',
                'label' => 'جلسه ۲',
                'topic' => 'روش‌های معاینه، تشخیص و طرح درمان در دندانپزشکی ترمیمی ۱',
                'patterns' => ['session2*.txt'],
                'parser' => 'dent_exams_text_source_parse_file_multiline',
            ],
            [
                'slug' => '3',
                'label' => 'جلسه ۳',
                'topic' => 'روش‌های معاینه، تشخیص و طرح درمان در دندانپزشکی ترمیمی ۲',
                'patterns' => ['session3*.txt'],
                'parser' => 'dent_exams_text_source_parse_file_multiline',
            ],
            [
                'slug' => '4',
                'label' => 'جلسه ۴',
                'topic' => 'بیولوژی و اصول حفاظت پالپ',
                'patterns' => ['session4*.txt'],
                'parser' => 'dent_exams_text_source_parse_file_multiline',
            ],
            [
                'slug' => '5',
                'label' => 'جلسه ۵',
                'topic' => 'شناخت مواد پوشش پالپی و تکنیک‌های پوشش پالپی',
                'patterns' => ['session5*.txt'],
                'parser' => 'dent_exams_text_source_parse_file_multiline',
            ],
            [
                'slug' => '6',
                'label' => 'جلسه ۶',
                'topic' => 'آشنایی و کاربرد درمان‌های غیر تهاجمی پوسیدگی‌های دندانی',
                'patterns' => ['session6*.txt'],
                'parser' => 'dent_exams_text_source_parse_file_multiline',
            ],
            [
                'slug' => '7',
                'label' => 'جلسه ۷',
                'topic' => 'سیستم‌های باندینگ دندانی',
                'patterns' => ['session7*.txt'],
                'parser' => 'dent_exams_text_source_parse_file_multiline',
            ],
            [
                'slug' => '8',
                'label' => 'جلسه ۸',
                'topic' => 'اصول تراش و ترمیم در دندان‌های قدامی',
                'patterns' => ['session8*.txt'],
                'parser' => 'dent_exams_text_source_parse_file_multiline',
            ],
        ],
    ]);

    $course['addedAt'] = '2026-07-06T21:03:27+03:30';
    foreach (($course['exams'] ?? []) as $index => $exam) {
        if (is_array($exam) && empty($exam['addedAt'])) {
            $course['exams'][$index]['addedAt'] = $course['addedAt'];
        }
    }

    return $course;
}
