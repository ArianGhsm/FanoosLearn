<?php
declare(strict_types=1);

require_once __DIR__ . '/text_source_parser.php';
require_once __DIR__ . '/essay_text_source_parser.php';

function dent_exams_dental_materials_midterm_sample_course(): array
{
    static $course = null;
    if (is_array($course)) {
        return $course;
    }

    $course = dent_exams_text_source_build_course([
        'courseSlug' => 'dental-materials-midterm-sample',
        'title' => 'نمونه سوالات میانترم مبانی مواد دندانی',
        'shortTitle' => 'نمونه سوالات میانترم',
        'termLabel' => 'ترم ۶',
        'dataDir' => __DIR__ . '/data/dental_materials_midterm_sample',
        'paymentAmount' => 300000,
        'examDefinitions' => [
            [
                'slug' => 'mcq',
                'label' => 'نمونه سوالات تستی میانترم مبانی مواد دندانی',
                'patterns' => ['midterm_72_mcq*.txt'],
                'parser' => 'dent_exams_text_source_parse_file_multiline',
            ],
            [
                'slug' => 'essay',
                'label' => 'نمونه سوالات تشریحی',
                'patterns' => ['midterm_essay*.txt'],
                'kind' => 'essay',
                'parser' => 'dent_exams_essay_source_parse_file',
            ],
        ],
    ]);

    return $course;
}
