<?php
declare(strict_types=1);

require_once __DIR__ . '/answer_sheet_parser.php';

function dent_exams_restorative_theory1_final_sample_parse_file(string $path): array
{
    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return [
            'topic' => '',
            'questions' => [],
        ];
    }

    $text = dent_exams_text_source_normalize_text($raw);
    $parts = preg_split(
        '/^\s*(?:سوال|سؤال)\s*([0-9]+)\s*$/mu',
        $text,
        -1,
        PREG_SPLIT_DELIM_CAPTURE
    ) ?: [];
    $questions = [];

    for ($index = 1; $index + 1 < count($parts); $index += 2) {
        $number = max(0, (int) ($parts[$index] ?? 0));
        $chunk = trim((string) ($parts[$index + 1] ?? ''));
        if ($number <= 0 || $chunk === '') {
            continue;
        }

        $question = dent_exams_restorative_theory1_final_sample_parse_chunk($number, $chunk);
        if ($question !== null) {
            $questions[] = $question;
        }
    }

    return [
        'topic' => '',
        'questions' => $questions,
    ];
}

function dent_exams_restorative_theory1_final_sample_parse_chunk(int $number, string $chunk): ?array
{
    $lines = preg_split('/\R/u', $chunk) ?: [];
    $preambleLines = [];
    $sections = [];
    $currentSection = '';
    $answerStarted = false;
    $markedRaw = '';
    $analyticalRaw = '';
    $comparisonRaw = '';

    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '' || preg_match('/^[\x{2500}-\x{257f}\-=*_ ]{5,}$/u', $trimmed) === 1) {
            continue;
        }

        if (preg_match('/^(?:جمع‌بندی کنترل نهایی|جمع‌بندی تطبیق پاسخ علامت|رفرنس‌های استاندارد تکمیلی استفاده‌شده|QA نهایی فایل|یادداشت نهایی QA|پایان فایل)\b/u', $trimmed) === 1) {
            break;
        }

        if (preg_match('/^پاسخ\s+علامت[‌-]?خورده\s+در\s+فایل(?:\s+اصلی)?\s*[:：]\s*(.*)$/u', $trimmed, $matches) === 1) {
            $markedRaw = dent_exams_text_source_restore_digits(trim((string) ($matches[1] ?? '')));
            $currentSection = '';
            $answerStarted = true;
            continue;
        }

        if (preg_match('/^پاسخ\s+تحلیلی(?:\s+نهایی)?\s*[:：]\s*(.*)$/u', $trimmed, $matches) === 1) {
            $analyticalRaw = dent_exams_text_source_restore_digits(trim((string) ($matches[1] ?? '')));
            $currentSection = '';
            $answerStarted = true;
            continue;
        }

        if (preg_match('/^وضعیت\s+(?:تطابق|مقایسه)\s*[:：]\s*(.*)$/u', $trimmed, $matches) === 1) {
            $comparisonRaw = dent_exams_text_source_restore_digits(trim((string) ($matches[1] ?? '')));
            $currentSection = '';
            $answerStarted = true;
            continue;
        }

        $section = dent_exams_restorative_theory1_final_sample_match_section($trimmed);
        if ($section !== null) {
            $answerStarted = true;
            $currentSection = (string) ($section['label'] ?? '');
            if ($currentSection !== '') {
                $sections[$currentSection] = $sections[$currentSection] ?? [];
                $tail = trim((string) ($section['tail'] ?? ''));
                if ($tail !== '') {
                    $sections[$currentSection][] = $tail;
                }
            }
            continue;
        }

        if ($currentSection !== '') {
            $sections[$currentSection][] = dent_exams_text_source_restore_digits($trimmed);
            continue;
        }

        if (!$answerStarted) {
            $preambleLines[] = $trimmed;
        }
    }

    [$questionLines, $optionMap] = dent_exams_restorative_theory1_final_sample_split_preamble($preambleLines);
    $options = dent_exams_answer_sheet_normalize_option_map($optionMap);
    $questionText = dent_exams_answer_sheet_compose_question_text($questionLines);
    if ($questionText === '' || count($options) !== 4) {
        return null;
    }

    $markedIndex = dent_exams_answer_sheet_extract_option_index($markedRaw);
    $analyticalIndex = dent_exams_answer_sheet_extract_option_index($analyticalRaw);
    $correctIndex = $analyticalIndex ?? $markedIndex;
    $answerSections = dent_exams_restorative_theory1_final_sample_build_answer_sections($sections);
    $optionRationales = dent_exams_restorative_theory1_final_sample_option_rationales(
        is_array($sections['بررسی تک‌تک گزینه‌ها'] ?? null) ? $sections['بررسی تک‌تک گزینه‌ها'] : []
    );
    $reference = dent_exams_restorative_theory1_final_sample_join_lines(
        is_array($sections['منبع / رفرنس'] ?? null) ? $sections['منبع / رفرنس'] : []
    );

    return [
        'number' => $number,
        'question' => $questionText,
        'options' => array_values($options),
        'correctIndex' => $correctIndex,
        'explanation' => dent_exams_restorative_theory1_final_sample_compose_explanation($sections),
        'reference' => $reference,
        'optionRationales' => $optionRationales,
        'answerCardLabel' => 'پاسخ تشریحی، منبع و تحلیل گزینه‌ها',
        'answerMeta' => dent_exams_restorative_theory1_final_sample_build_meta(
            $options,
            $markedRaw,
            $markedIndex,
            $analyticalRaw,
            $analyticalIndex,
            $comparisonRaw
        ),
        'answerSections' => $answerSections,
    ];
}

function dent_exams_restorative_theory1_final_sample_split_preamble(array $lines): array
{
    $optionStart = count($lines) - 4;
    if ($optionStart < 0) {
        return [$lines, []];
    }

    $optionMap = [];
    for ($offset = 0; $offset < 4; $offset++) {
        $line = trim((string) ($lines[$optionStart + $offset] ?? ''));
        if (preg_match('/^([1-4])[\)\.\-]\s*(.+)$/u', $line, $matches) !== 1
            || (int) ($matches[1] ?? 0) !== $offset + 1) {
            return [$lines, []];
        }

        $optionMap[$offset + 1] = dent_exams_text_source_restore_digits(trim((string) ($matches[2] ?? '')));
    }

    return [array_slice($lines, 0, $optionStart), $optionMap];
}

function dent_exams_restorative_theory1_final_sample_match_section(string $line): ?array
{
    if (preg_match('/^(منبع\s*\/\s*رفرنس|رفرنس\s+جزوه\s*\/\s*پاور)\s*[:：]\s*(.*)$/u', $line, $matches) === 1) {
        return [
            'label' => 'منبع / رفرنس',
            'tail' => dent_exams_text_source_restore_digits(trim((string) ($matches[2] ?? ''))),
        ];
    }

    if (preg_match('/^وضعیت\s+منبع\s*[:：]\s*(.*)$/u', $line, $matches) === 1) {
        return [
            'label' => 'وضعیت منبع',
            'tail' => dent_exams_text_source_restore_digits(trim((string) ($matches[1] ?? ''))),
        ];
    }

    if (preg_match('/^پاسخ\s+تشریحی(?:\s+و\s+نکات\s+آموزشی)?\s*[:：]\s*(.*)$/u', $line, $matches) === 1) {
        return [
            'label' => 'پاسخ تشریحی و نکات آموزشی',
            'tail' => dent_exams_text_source_restore_digits(trim((string) ($matches[1] ?? ''))),
        ];
    }

    if (preg_match('/^(?:تحلیل(?:\s+تک[‌-]?تک)?|بررسی\s+تک[‌-]?تک)\s+گزینه[‌-]?ها\s*[:：]\s*(.*)$/u', $line, $matches) === 1) {
        return [
            'label' => 'بررسی تک‌تک گزینه‌ها',
            'tail' => dent_exams_text_source_restore_digits(trim((string) ($matches[1] ?? ''))),
        ];
    }

    if (preg_match('/^اصطلاحات\s+و\s+نکات(?:\s+آموزشی)?\s+مهم\s*[:：]\s*(.*)$/u', $line, $matches) === 1) {
        return [
            'label' => 'اصطلاحات و نکات مهم',
            'tail' => dent_exams_text_source_restore_digits(trim((string) ($matches[1] ?? ''))),
        ];
    }

    return null;
}

function dent_exams_restorative_theory1_final_sample_build_meta(
    array $options,
    string $markedRaw,
    ?int $markedIndex,
    string $analyticalRaw,
    ?int $analyticalIndex,
    string $comparisonRaw
): array {
    $meta = [];

    if ($analyticalRaw !== '') {
        $meta[] = [
            'label' => 'پاسخ تحلیلی',
            'value' => dent_exams_answer_sheet_format_selection_display($analyticalRaw, $analyticalIndex, $options),
            'tone' => $analyticalIndex === null ? 'warning' : 'success',
        ];
    }

    if ($markedRaw !== '') {
        $meta[] = [
            'label' => 'پاسخ علامت‌خورده در فایل',
            'value' => dent_exams_answer_sheet_format_selection_display($markedRaw, $markedIndex, $options),
            'tone' => $markedIndex === null || ($analyticalIndex !== null && $markedIndex !== $analyticalIndex)
                ? 'warning'
                : 'neutral',
        ];
    }

    if ($comparisonRaw !== '') {
        $meta[] = [
            'label' => 'وضعیت تطابق',
            'value' => $comparisonRaw,
            'tone' => preg_match('/متفاوت|اختلاف|نامشخص|ابهام|بدون\s+علامت|فاقد/u', $comparisonRaw) === 1
                ? 'warning'
                : 'success',
            'wide' => true,
        ];
    }

    return $meta;
}

function dent_exams_restorative_theory1_final_sample_build_answer_sections(array $sections): array
{
    $payload = [];
    foreach ($sections as $label => $lines) {
        $label = trim((string) $label);
        $value = dent_exams_restorative_theory1_final_sample_join_lines(is_array($lines) ? $lines : []);
        if ($label === '' || $value === '') {
            continue;
        }

        $tone = '';
        if ($label === 'پاسخ تشریحی و نکات آموزشی') {
            $tone = 'success';
        } elseif ($label === 'منبع / رفرنس' || $label === 'وضعیت منبع') {
            $tone = 'info';
        } elseif ($label === 'اصطلاحات و نکات مهم') {
            $tone = 'accent';
        } elseif (preg_match('/ابهام|اختلاف|فاقد|پیدا نشد|موجود نبود/u', $value) === 1) {
            $tone = 'warning';
        }

        $payload[] = [
            'label' => $label,
            'value' => $value,
            'tone' => $tone,
        ];
    }

    return $payload;
}

function dent_exams_restorative_theory1_final_sample_option_rationales(array $lines): array
{
    $rationales = ['', '', '', ''];
    $currentIndex = null;

    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }

        $normalizedLine = dent_exams_text_source_normalize_digits($line);
        if (preg_match('/^(?:[-•]\s*)?(?:گزینه\s*)?([1-4])\s*[:\)\.\-]\s*(.*)$/u', $normalizedLine, $matches) === 1) {
            $currentIndex = max(0, min(3, ((int) ($matches[1] ?? 1)) - 1));
            $rationales[$currentIndex] = dent_exams_text_source_restore_digits(trim((string) ($matches[2] ?? '')));
            continue;
        }

        if ($currentIndex !== null) {
            $rationales[$currentIndex] = trim(
                $rationales[$currentIndex] . ' ' . dent_exams_text_source_restore_digits($line)
            );
        }
    }

    return $rationales;
}

function dent_exams_restorative_theory1_final_sample_compose_explanation(array $sections): string
{
    $payload = [];
    foreach ($sections as $label => $lines) {
        $value = dent_exams_restorative_theory1_final_sample_join_lines(is_array($lines) ? $lines : []);
        if ($value !== '') {
            $payload[] = '**' . trim((string) $label) . ":**\n" . $value;
        }
    }

    return implode("\n\n", $payload);
}

function dent_exams_restorative_theory1_final_sample_join_lines(array $lines): string
{
    $clean = [];
    foreach ($lines as $line) {
        $line = trim(preg_replace('/[ \t]+/u', ' ', (string) $line) ?? (string) $line);
        if ($line !== '') {
            $clean[] = dent_exams_text_source_restore_digits($line);
        }
    }

    return implode("\n", $clean);
}

function dent_exams_restorative_theory1_final_sample_course(): array
{
    static $course = null;
    if (is_array($course)) {
        return $course;
    }

    $courseSlug = 'restorative-theory-1-final-sample';
    $courseTitle = 'نمونه سوالات پایان‌ترم ترمیمی نظری ۱';
    $addedAt = '2026-07-24T21:53:02+03:30';
    $course = dent_exams_text_source_build_course([
        'courseSlug' => $courseSlug,
        'title' => $courseTitle,
        'shortTitle' => 'نمونه سوالات پایان‌ترم ترمیمی نظری ۱',
        'termLabel' => 'ترم ۶',
        'dataDir' => __DIR__ . '/data/restorative_theory1_final_sample',
        'paymentAmount' => 300000,
        'examDefinitions' => [
            [
                'slug' => 'final-exam-62',
                'label' => 'امتحان ترمیمی نظری ۱ - ۶۲ سوال',
                'topic' => 'امتحان جامع ترمیمی نظری ۱',
                'patterns' => ['final_exam_62.txt'],
                'parser' => 'dent_exams_restorative_theory1_final_sample_parse_file',
            ],
            [
                'slug' => 'ordibehesht-1400',
                'label' => 'نمونه سوال اردیبهشت ۱۴۰۰',
                'topic' => 'ترمیمی نظری ۱ - اردیبهشت ۱۴۰۰',
                'patterns' => ['ordibehesht_1400_32.txt'],
                'parser' => 'dent_exams_restorative_theory1_final_sample_parse_file',
            ],
            [
                'slug' => 'tir-1400',
                'label' => 'نمونه سوال تیر ۱۴۰۰',
                'topic' => 'ترمیمی نظری ۱ - تیر ۱۴۰۰',
                'patterns' => ['tir_1400_32.txt'],
                'parser' => 'dent_exams_restorative_theory1_final_sample_parse_file',
            ],
        ],
    ]);

    if ($course === []) {
        return [];
    }

    $totalQuestions = 0;
    foreach (($course['exams'] ?? []) as $index => $exam) {
        if (!is_array($exam)) {
            continue;
        }

        $questionCount = max(0, (int) ($exam['questionCount'] ?? 0));
        $questionCountFa = dent_exams_text_source_to_persian_digits((string) $questionCount);
        $totalQuestions += $questionCount;
        $course['exams'][$index]['addedAt'] = $addedAt;
        $course['exams'][$index]['subtitle'] = $questionCountFa . ' سوال چهارگزینه‌ای با پاسخ تشریحی، منبع و تحلیل جداگانه گزینه‌ها.';
        $course['exams'][$index]['siteBadge'] = 'نمونه سوال پایان‌ترم';
        $course['exams'][$index]['answerCardLabel'] = 'پاسخ تشریحی، منبع و تحلیل گزینه‌ها';
    }

    $questionCountFa = dent_exams_text_source_to_persian_digits((string) $totalQuestions);
    $course['addedAt'] = $addedAt;
    $course['badge'] = '۳ آزمون';
    $course['cardDescription'] = 'سه نمونه آزمون ترمیمی نظری ۱ با مجموع ' . $questionCountFa
        . ' سوال، پاسخ تشریحی، منبع و تحلیل تک‌تک گزینه‌ها.';
    $course['heroDescription'] = 'این مجموعه شامل سه نمونه آزمون پایان‌ترم ترمیمی نظری ۱ با مجموع '
        . $questionCountFa . ' سوال است. پاسخ تحلیلی، پاسخ علامت‌خورده فایل، وضعیت تطابق، منبع و بررسی گزینه‌ها در کادرهای جدا نمایش داده می‌شوند. خریداران قبلی مجموعه نمونه سوالات میان‌ترم، دسترسی این مجموعه را نیز دارند؛ خریدهای جدید این مجموعه مستقل و ۳۰ هزار تومان است.';
    $course['paymentTitle'] = 'دسترسی به ' . $courseTitle;
    $course['paymentDescription'] = 'با یک بار پرداخت ۳۰ هزار تومان، هر سه آزمون این مجموعه برای همین حساب فعال می‌شود. دسترسی هدیه فقط شامل خریدهای تاییدشده مجموعه میان‌ترم پیش از انتشار این مجموعه است.';
    $course['paymentSuccessMessage'] = 'پرداخت شما تایید شد و هر سه آزمون ' . $courseTitle . ' برای این حساب باز شد.';
    $course['paymentFailureMessage'] = 'فعال‌سازی ' . $courseTitle . ' انجام نشد. نتیجه را دوباره بررسی کنید.';
    $course['legacyPurchaseAccessGrants'] = [
        [
            'courseSlug' => 'restorative-theory-1-midterm-sample',
            'purchasedBefore' => $addedAt,
            'unlockLabel' => 'فعال از خرید قبلی نمونه سوالات میان‌ترم',
        ],
    ];

    return $course;
}
