<?php
declare(strict_types=1);

require_once __DIR__ . '/answer_sheet_parser.php';

function dent_exams_complete_prosthodontics_midterm_sample_sources(): array
{
    $dataDir = __DIR__ . '/data/complete_prosthodontics_midterm_sample';

    return [
        [
            'slug' => 'ordibehesht-1400',
            'title' => 'میان‌ترم اردیبهشت ۱۴۰۰ پروتز کامل نظری ۱',
            'file' => $dataDir . '/ordibehesht_1400.txt',
            'questionCount' => 28,
        ],
        [
            'slug' => 'tir-1400',
            'title' => 'نمونه‌سؤالات پروتز کامل ۱ - تیر ۱۴۰۰',
            'file' => $dataDir . '/tir_1400.txt',
            'questionCount' => 32,
        ],
    ];
}

function dent_exams_complete_prosthodontics_midterm_sample_course(bool $hydrateQuestions = false): array
{
    static $catalogCourse = null;
    static $fullCourse = null;

    if ($hydrateQuestions && is_array($fullCourse)) {
        return $fullCourse;
    }
    if (!$hydrateQuestions && is_array($catalogCourse)) {
        return $catalogCourse;
    }

    $courseSlug = 'complete-prosthodontics-midterm-sample';
    $courseTitle = 'آزمون نمونه سوالات میانترم کامل نظری';
    $coursePath = '/exams/' . $courseSlug . '/';
    $addedAt = '2026-07-14T19:34:23+03:30';
    $exams = [];
    $totalQuestions = 0;

    foreach (dent_exams_complete_prosthodontics_midterm_sample_sources() as $source) {
        $slug = trim((string) ($source['slug'] ?? ''));
        $title = trim((string) ($source['title'] ?? ''));
        $expectedQuestionCount = max(0, (int) ($source['questionCount'] ?? 0));
        if ($slug === '' || $title === '' || $expectedQuestionCount <= 0) {
            continue;
        }

        $questions = [];
        $questionCount = $expectedQuestionCount;
        if ($hydrateQuestions) {
            $parsed = dent_exams_complete_prosthodontics_midterm_sample_parse_file((string) ($source['file'] ?? ''));
            $questions = is_array($parsed['questions'] ?? null) ? $parsed['questions'] : [];
            $questionCount = count($questions);
            if ($questionCount <= 0) {
                continue;
            }
            if (trim((string) ($parsed['title'] ?? '')) !== '') {
                $title = trim((string) ($parsed['title'] ?? ''));
            }
        }

        $questionCountFa = dent_exams_text_source_to_persian_digits((string) $questionCount);
        $totalQuestions += $questionCount;

        $exam = [
            'slug' => $slug,
            'path' => $coursePath . $slug . '/',
            'label' => $title,
            'title' => $title,
            'subtitle' => $questionCountFa . ' سؤال چهارگزینه‌ای با پاسخ تشریحی، کلید پیشنهادی، وضعیت تطابق و منبع/جزوه.',
            'description' => 'مرور ' . $questionCountFa . ' سؤال از «' . $title . '».',
            'eyebrow' => $courseTitle . ' | ' . $title,
            'ctaLabel' => 'انتخاب حالت و شروع',
            'backHref' => $coursePath,
            'backLabel' => 'بازگشت به فهرست آزمون‌های ' . $courseTitle,
            'autoAdvance' => true,
            'siteTitle' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
            'siteSubtitle' => 'آزمون‌ها',
            'siteBadge' => 'نمونه سوال میانترم',
            'footerText' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
            'attemptable' => true,
            'comingSoon' => false,
            'countsTowardStats' => true,
            'questionCount' => $questionCount,
            'addedAt' => $addedAt,
        ];

        if ($hydrateQuestions) {
            $exam['questions'] = $questions;
        }

        $exams[] = $exam;
    }

    $examCount = count($exams);
    if ($examCount <= 0) {
        return [];
    }

    $examCountFa = dent_exams_text_source_to_persian_digits((string) $examCount);
    $questionCountFa = dent_exams_text_source_to_persian_digits((string) $totalQuestions);
    $course = [
        'slug' => $courseSlug,
        'addedAt' => $addedAt,
        'title' => $courseTitle,
        'shortTitle' => 'نمونه سوالات میانترم',
        'badge' => $examCountFa . ' آزمون',
        'cardDescription' => $examCountFa . ' مجموعه نمونه‌سؤال میانترم کامل نظری با مجموع ' . $questionCountFa . ' سؤال و پاسخ تشریحی.',
        'heroTitle' => $courseTitle,
        'heroDescription' => 'این مجموعه برای پروتز کامل نظری ترم ۶ آماده شده و شامل ' . $examCountFa
            . ' آزمون با مجموع ' . $questionCountFa
            . ' سؤال است. در پاسخ هر سؤال، گزینه علامت‌خورده در فایل، پاسخ پیشنهادی، وضعیت تطابق، وضعیت منبع، رفرنس/جزوه و بخش‌های آموزشی جداگانه نمایش داده می‌شود. با یک بار پرداخت ۳۰ هزار تومان، کل این مجموعه برای همین حساب فعال می‌شود.',
        'path' => $coursePath,
        'paymentTitle' => 'دسترسی به ' . $courseTitle,
        'paymentDescription' => 'با یک بار پرداخت ۳۰ هزار تومان، هر دو آزمون نمونه‌سؤالات میانترم کامل نظری برای همین حساب فعال می‌شود.',
        'paymentSuccessMessage' => 'پرداخت شما تایید شد و همه آزمون‌های ' . $courseTitle . ' برای این حساب باز شد.',
        'paymentFailureMessage' => 'فعال‌سازی ' . $courseTitle . ' انجام نشد. نتیجه را دوباره بررسی کنید.',
        'defaultPaymentMode' => 'paid',
        'defaultAmount' => 300000,
        'exams' => $exams,
    ];

    if ($hydrateQuestions) {
        $fullCourse = $course;
        return $fullCourse;
    }

    $catalogCourse = $course;
    return $catalogCourse;
}

function dent_exams_complete_prosthodontics_midterm_sample_runtime_exam_payload(
    string $catalogKey,
    string $courseSlug,
    string $examSlug
): ?array {
    if ($catalogKey !== 'shared' || $courseSlug !== 'complete-prosthodontics-midterm-sample') {
        return null;
    }

    $examSlug = trim($examSlug);
    if ($examSlug === '') {
        return null;
    }

    $course = dent_exams_complete_prosthodontics_midterm_sample_course(true);
    foreach ((is_array($course['exams'] ?? null) ? $course['exams'] : []) as $exam) {
        if (is_array($exam) && (string) ($exam['slug'] ?? '') === $examSlug) {
            return $exam;
        }
    }

    return null;
}

function dent_exams_complete_prosthodontics_midterm_sample_parse_file(string $path): array
{
    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return [
            'title' => '',
            'questions' => [],
        ];
    }

    $text = dent_exams_text_source_normalize_text($raw);
    $parts = preg_split('/^\s*(?:سوال|سؤال)\s*(\d+)\)?\s*$/mu', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
    $questions = [];

    for ($index = 1; $index + 1 < count($parts); $index += 2) {
        $number = max(0, (int) ($parts[$index] ?? 0));
        $chunk = trim((string) ($parts[$index + 1] ?? ''));
        if ($number <= 0 || $chunk === '') {
            continue;
        }

        $question = dent_exams_complete_prosthodontics_midterm_sample_parse_chunk($number, $chunk);
        if ($question !== null) {
            $questions[] = $question;
        }
    }

    return [
        'title' => dent_exams_complete_prosthodontics_midterm_sample_extract_title($text),
        'questions' => $questions,
    ];
}

function dent_exams_complete_prosthodontics_midterm_sample_extract_title(string $text): string
{
    $lines = preg_split('/\R/u', $text) ?: [];
    $titleLines = [];

    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '') {
            if ($titleLines !== []) {
                break;
            }
            continue;
        }
        if (preg_match('/^[=\-]{5,}$/u', $trimmed) === 1) {
            break;
        }
        if (preg_match('/^(?:روش|فایل|خلاصه|منابع|نکته)\b/u', $trimmed) === 1) {
            break;
        }

        $titleLines[] = dent_exams_text_source_restore_digits($trimmed);
        if (count($titleLines) >= 2) {
            break;
        }
    }

    return trim(implode(' - ', $titleLines));
}

function dent_exams_complete_prosthodontics_midterm_sample_parse_chunk(int $number, string $chunk): ?array
{
    $lines = preg_split('/\R/u', $chunk) ?: [];
    $questionLines = [];
    $optionMap = [];
    $currentOption = 0;
    $markedRaw = '';
    $suggestedRaw = '';
    $matchStatus = '';
    $sourceStatus = '';
    $referenceRaw = '';
    $answerSections = [];
    $currentSection = '';
    $hasStartedAnswer = false;

    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '' || preg_match('/^-{5,}$/u', $trimmed) === 1) {
            if ($currentSection !== '' && isset($answerSections[$currentSection])) {
                $answerSections[$currentSection][] = '';
            }
            continue;
        }

        if (preg_match('/^گزینه\s+علامت.خورده\s+در\s+فایل(?:\s+اصلی)?\s*:\s*(.*)$/u', $trimmed, $matches) === 1) {
            $markedRaw = trim((string) ($matches[1] ?? ''));
            $hasStartedAnswer = true;
            $currentSection = '';
            continue;
        }

        if (preg_match('/^پاسخ(?:\s+درست|\s+نهایی)?\s+پیشنهادی\s*:\s*(.*)$/u', $trimmed, $matches) === 1) {
            $suggestedRaw = trim((string) ($matches[1] ?? ''));
            $hasStartedAnswer = true;
            $currentSection = '';
            continue;
        }

        if (preg_match('/^وضعیت\s+تطابق\s*:\s*(.*)$/u', $trimmed, $matches) === 1) {
            $matchStatus = trim((string) ($matches[1] ?? ''));
            $hasStartedAnswer = true;
            $currentSection = '';
            continue;
        }

        if (preg_match('/^تفاوت\s+با\s+پاسخ\s+پیشنهادی\s*:\s*(.*)$/u', $trimmed, $matches) === 1) {
            $matchStatus = trim((string) ($matches[1] ?? ''));
            $hasStartedAnswer = true;
            $currentSection = '';
            continue;
        }

        if (preg_match('/^وضعیت\s+منبع\s*:\s*(.*)$/u', $trimmed, $matches) === 1) {
            $sourceStatus = trim((string) ($matches[1] ?? ''));
            $hasStartedAnswer = true;
            $currentSection = '';
            continue;
        }

        if (preg_match('/^رفرنس\s+جزوه\/منبع\s*:\s*(.*)$/u', $trimmed, $matches) === 1) {
            $referenceRaw = trim((string) ($matches[1] ?? ''));
            $hasStartedAnswer = true;
            $currentSection = '';
            continue;
        }

        $sectionLabel = dent_exams_complete_prosthodontics_midterm_sample_section_label($trimmed);
        if ($sectionLabel !== '') {
            $currentSection = $sectionLabel;
            $answerSections[$currentSection] = $answerSections[$currentSection] ?? [];
            $hasStartedAnswer = true;
            continue;
        }

        if (!$hasStartedAnswer && preg_match('/^([1-4])\)\s*(.*)$/u', $trimmed, $matches) === 1) {
            $currentOption = max(0, (int) ($matches[1] ?? 0));
            $optionMap[$currentOption] = trim((string) ($matches[2] ?? ''));
            continue;
        }

        if (!$hasStartedAnswer && $currentOption >= 1 && $currentOption <= 4 && isset($optionMap[$currentOption])) {
            $optionMap[$currentOption] = trim($optionMap[$currentOption] . ' ' . $trimmed);
            continue;
        }

        if ($currentSection !== '' && isset($answerSections[$currentSection])) {
            $answerSections[$currentSection][] = $trimmed;
            if ($currentSection === 'منبع / جزوه') {
                $referenceRaw = trim($referenceRaw . "\n" . $trimmed);
            }
            continue;
        }

        if (!$hasStartedAnswer) {
            $questionLines[] = $trimmed;
        }
    }

    $options = dent_exams_answer_sheet_normalize_option_map($optionMap);
    $questionText = dent_exams_answer_sheet_compose_question_text($questionLines);
    if ($questionText === '' || count($options) !== 4) {
        return null;
    }

    $markedIndex = dent_exams_answer_sheet_extract_option_index($markedRaw);
    $suggestedIndex = dent_exams_answer_sheet_extract_option_index($suggestedRaw);
    $analysisLines = array_merge(
        $answerSections['بررسی گزینه‌ها'] ?? [],
        $answerSections['بررسی تک‌تک گزینه‌ها'] ?? [],
        $answerSections['بررسی عبارات'] ?? []
    );
    $answerMeta = dent_exams_answer_sheet_build_meta(
        array_values($options),
        $markedRaw,
        $markedIndex,
        $suggestedRaw,
        $suggestedIndex,
        $referenceRaw
    );

    if ($matchStatus !== '') {
        $answerMeta[] = [
            'label' => 'وضعیت تطابق',
            'value' => dent_exams_text_source_restore_digits($matchStatus),
            'tone' => dent_exams_complete_prosthodontics_midterm_sample_tone($matchStatus),
            'wide' => true,
        ];
    }

    if ($sourceStatus !== '') {
        $answerMeta[] = [
            'label' => 'وضعیت منبع',
            'value' => dent_exams_text_source_restore_digits($sourceStatus),
            'tone' => dent_exams_complete_prosthodontics_midterm_sample_tone($sourceStatus, 'info'),
            'wide' => true,
        ];
    }

    return [
        'number' => $number,
        'question' => $questionText,
        'options' => array_values($options),
        'correctIndex' => $suggestedIndex,
        'explanation' => dent_exams_complete_prosthodontics_midterm_sample_compose_explanation($answerSections),
        'answerSections' => dent_exams_complete_prosthodontics_midterm_sample_build_answer_sections($answerSections),
        'optionRationales' => dent_exams_complete_prosthodontics_midterm_sample_option_rationales($analysisLines),
        'answerMeta' => $answerMeta,
        'answerMetaSource' => [
            'markedRaw' => dent_exams_text_source_restore_digits(trim($markedRaw)),
            'markedIndex' => $markedIndex,
            'suggestedRaw' => dent_exams_text_source_restore_digits(trim($suggestedRaw)),
            'suggestedIndex' => $suggestedIndex,
            'referenceRaw' => dent_exams_text_source_restore_digits(trim($referenceRaw)),
            'matchStatus' => dent_exams_text_source_restore_digits(trim($matchStatus)),
            'sourceStatus' => dent_exams_text_source_restore_digits(trim($sourceStatus)),
        ],
    ];
}

function dent_exams_complete_prosthodontics_midterm_sample_section_label(string $line): string
{
    $labels = [
        'منبع در فایل‌های پروژه' => 'منبع / جزوه',
        'منبع در فایل های پروژه' => 'منبع / جزوه',
        'پاسخ تشریحی' => 'پاسخ تشریحی',
        'بررسی گزینه‌ها' => 'بررسی گزینه‌ها',
        'بررسی گزینه ها' => 'بررسی گزینه‌ها',
        'بررسی تک‌تک گزینه‌ها' => 'بررسی تک‌تک گزینه‌ها',
        'بررسی تک تک گزینه ها' => 'بررسی تک‌تک گزینه‌ها',
        'بررسی عبارات' => 'بررسی عبارات',
        'نکته آموزشی' => 'نکته آموزشی',
        'نکته عملی' => 'نکته عملی',
        'نکته افتراقی' => 'نکته افتراقی',
        'هشدار بالینی' => 'هشدار بالینی',
        'مشکل صورت سؤال' => 'مشکل صورت سؤال',
        'مشکل صورت سوال' => 'مشکل صورت سؤال',
        'نتیجه اختلاف' => 'نتیجه اختلاف',
        'نتیجه امتحانی' => 'نتیجه امتحانی',
        'اصطلاح مهم' => 'اصطلاح مهم',
        'اصطلاحات مهم' => 'اصطلاحات مهم',
    ];

    foreach ($labels as $raw => $normalized) {
        if (preg_match('/^' . preg_quote($raw, '/') . '\s*:\s*$/u', $line) === 1) {
            return $normalized;
        }
    }

    return '';
}

function dent_exams_complete_prosthodontics_midterm_sample_build_answer_sections(array $sections): array
{
    $payload = [];
    foreach ($sections as $label => $lines) {
        $label = trim((string) $label);
        if ($label === 'منبع / جزوه') {
            continue;
        }

        $value = dent_exams_complete_prosthodontics_midterm_sample_join_lines(is_array($lines) ? $lines : []);
        if ($label === '' || $value === '') {
            continue;
        }

        $payload[] = [
            'label' => $label,
            'value' => $value,
            'tone' => dent_exams_complete_prosthodontics_midterm_sample_section_tone($label),
        ];
    }

    return $payload;
}

function dent_exams_complete_prosthodontics_midterm_sample_compose_explanation(array $sections): string
{
    $parts = [];
    foreach (dent_exams_complete_prosthodontics_midterm_sample_build_answer_sections($sections) as $section) {
        $parts[] = '**' . (string) ($section['label'] ?? 'توضیح') . ":**\n" . (string) ($section['value'] ?? '');
    }

    return trim(implode("\n\n", $parts));
}

function dent_exams_complete_prosthodontics_midterm_sample_option_rationales(array $analysisLines): array
{
    $rationales = ['', '', '', ''];
    $currentIndex = null;

    foreach ($analysisLines as $line) {
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }

        if (preg_match('/^([1-4])\)\s*(.+)$/u', $line, $matches) === 1) {
            $currentIndex = max(0, (int) ($matches[1] ?? 1)) - 1;
            $rationales[$currentIndex] = dent_exams_text_source_restore_digits(trim((string) ($matches[2] ?? '')));
            continue;
        }

        if ($currentIndex !== null && $currentIndex >= 0 && $currentIndex <= 3) {
            $rationales[$currentIndex] = trim($rationales[$currentIndex] . ' ' . dent_exams_text_source_restore_digits($line));
        }
    }

    return $rationales;
}

function dent_exams_complete_prosthodontics_midterm_sample_join_lines(array $lines): string
{
    $clean = [];
    foreach ($lines as $line) {
        $line = trim(preg_replace('/[ \t]+/u', ' ', (string) $line) ?? (string) $line);
        if ($line === '') {
            if ($clean !== [] && end($clean) !== '') {
                $clean[] = '';
            }
            continue;
        }
        $clean[] = dent_exams_text_source_restore_digits($line);
    }

    return trim(implode("\n", $clean));
}

function dent_exams_complete_prosthodontics_midterm_sample_tone(string $value, string $default = 'neutral'): string
{
    if (preg_match('/متفاوت|اختلاف|ابهام|ناسازگاری|بله|نیست|نبود|نداشت|فاقد/u', $value) === 1) {
        return 'warning';
    }
    if (preg_match('/منطبق|ندارد|موجود|مستقیم/u', $value) === 1) {
        return 'success';
    }

    return $default;
}

function dent_exams_complete_prosthodontics_midterm_sample_section_tone(string $label): string
{
    if (preg_match('/اختلاف|مشکل|هشدار/u', $label) === 1) {
        return 'warning';
    }
    if (preg_match('/نکته|اصطلاح|نتیجه/u', $label) === 1) {
        return 'info';
    }

    return '';
}
