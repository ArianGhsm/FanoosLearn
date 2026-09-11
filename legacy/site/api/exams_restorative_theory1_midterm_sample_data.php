<?php
declare(strict_types=1);

require_once __DIR__ . '/answer_sheet_parser.php';

function dent_exams_restorative_theory1_midterm_sample_course(): array
{
    static $course = null;
    if (is_array($course)) {
        return $course;
    }

    $courseSlug = 'restorative-theory-1-midterm-sample';
    $courseTitle = 'نمونه سوالات میان‌ترم ترمیمی نظری ۱';
    $coursePath = '/exams/' . $courseSlug . '/';
    $dataDir = __DIR__ . '/data/restorative_theory1_midterm_sample';
    $sources = [
        [
            'slug' => 'sample-set',
            'file' => $dataDir . '/midterm_sample_set_62.txt',
        ],
        [
            'slug' => 'ordibehesht-1400',
            'file' => $dataDir . '/midterm_ordibehesht_1400.md',
            'questionFile' => $dataDir . '/midterm_ordibehesht_1400_questions.txt',
        ],
        [
            'slug' => 'tir-1400',
            'file' => $dataDir . '/midterm_tir_1400.txt',
        ],
    ];

    $exams = [];
    $totalQuestions = 0;

    foreach ($sources as $source) {
        $parsed = dent_exams_answer_sheet_parse_file((string) ($source['file'] ?? ''));
        $questionFile = trim((string) ($source['questionFile'] ?? ''));
        if ($questionFile !== '') {
            $parsed = dent_exams_answer_sheet_merge_question_source(
                $parsed,
                dent_exams_answer_sheet_parse_question_source_file($questionFile)
            );
        }

        $questions = is_array($parsed['questions'] ?? null) ? $parsed['questions'] : [];
        $questionCount = count($questions);
        if ($questionCount <= 0) {
            continue;
        }

        $label = trim((string) ($parsed['title'] ?? ''));
        if ($label === '') {
            $label = 'نمونه سوالات میان‌ترم';
        }

        $questionCountFa = dent_exams_text_source_to_persian_digits((string) $questionCount);
        $totalQuestions += $questionCount;

        $exams[] = [
            'slug' => (string) ($source['slug'] ?? ''),
            'path' => $coursePath . (string) ($source['slug'] ?? '') . '/',
            'label' => $label,
            'title' => $label,
            'subtitle' => $questionCountFa . ' سوال چهارگزینه‌ای با پاسخ تشریحی، کلید پیشنهادی و منبع/جزوه.',
            'description' => 'مرور ' . $questionCountFa . ' سوال از «' . $label . '».',
            'eyebrow' => $courseTitle . ' | ' . $label,
            'ctaLabel' => 'انتخاب حالت و شروع',
            'backHref' => $coursePath,
            'backLabel' => 'بازگشت به فهرست آزمون‌های ' . $courseTitle,
            'autoAdvance' => true,
            'siteTitle' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
            'siteSubtitle' => 'آزمون‌ها',
            'siteBadge' => 'نمونه سوال میان‌ترم',
            'footerText' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
            'attemptable' => true,
            'comingSoon' => false,
            'countsTowardStats' => true,
            'questionCount' => $questionCount,
            'questions' => $questions,
        ];
    }

    $examCount = count($exams);
    if ($examCount <= 0) {
        return [];
    }

    $examCountFa = dent_exams_text_source_to_persian_digits((string) $examCount);
    $questionCountFa = dent_exams_text_source_to_persian_digits((string) $totalQuestions);

    $course = [
        'slug' => $courseSlug,
        'addedAt' => '2026-07-08T20:57:15+03:30',
        'title' => $courseTitle,
        'shortTitle' => 'نمونه سوالات میان‌ترم',
        'badge' => $examCountFa . ' آزمون',
        'cardDescription' => 'سه مجموعه میان‌ترم ترمیمی نظری ۱ با مجموع ' . $questionCountFa . ' سوال و پاسخ تشریحی در این بخش جمع شده‌اند.',
        'heroTitle' => $courseTitle,
        'heroDescription' => 'این مجموعه برای ترمیمی نظری ۱ ترم ۶ آماده شده و ' . $examCountFa
            . ' آزمون با مجموع ' . $questionCountFa
            . ' سوال دارد. در پاسخ هر سؤال، گزینه علامت‌خورده در فایل، پاسخ پیشنهادی و منبع/جزوه هم نمایش داده می‌شود. با یک بار پرداخت ۳۰ هزار تومان، کل این مجموعه برای همین حساب فعال می‌شود.',
        'path' => $coursePath,
        'paymentTitle' => 'دسترسی به ' . $courseTitle,
        'paymentDescription' => 'با یک بار پرداخت ۳۰ هزار تومان، هر سه آزمون میان‌ترم ترمیمی نظری ۱ برای همین حساب فعال می‌شود.',
        'paymentSuccessMessage' => 'پرداخت شما تایید شد و همه آزمون‌های ' . $courseTitle . ' برای این حساب باز شد.',
        'paymentFailureMessage' => 'فعال‌سازی ' . $courseTitle . ' انجام نشد. نتیجه را دوباره بررسی کنید.',
        'defaultPaymentMode' => 'paid',
        'defaultAmount' => 300000,
        'exams' => $exams,
    ];

    foreach (($course['exams'] ?? []) as $index => $exam) {
        if (is_array($exam) && empty($exam['addedAt'])) {
            $course['exams'][$index]['addedAt'] = $course['addedAt'];
        }
    }

    return $course;
}
