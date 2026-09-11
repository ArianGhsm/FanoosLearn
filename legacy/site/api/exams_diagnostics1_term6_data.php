<?php
declare(strict_types=1);
// data: session9 -> TMJ

function dent_exams_diagnostics1_term6_course(): array
{
    static $course = null;
    if (is_array($course)) {
        return $course;
    }

    $courseSlug = 'diagnostics-1-term6';
    $examDefinitions = dent_exams_diagnostics1_term6_exam_definitions();
    $examPayloads = dent_exams_diagnostics1_term6_exam_payloads($courseSlug, $examDefinitions);

    $plannedExamCount = count($examPayloads);
    $activeExamCount = 0;
    $activeQuestionCount = 0;

    foreach ($examPayloads as $exam) {
        if (!is_array($exam) || (bool) ($exam['comingSoon'] ?? false)) {
            continue;
        }

        $activeExamCount++;
        $activeQuestionCount += max(0, (int) ($exam['questionCount'] ?? 0));
    }

    $plannedExamCountFa = dent_exams_diagnostics1_term6_to_persian_digits((string) $plannedExamCount);
    $activeExamCountFa = dent_exams_diagnostics1_term6_to_persian_digits((string) $activeExamCount);
    $activeQuestionCountFa = dent_exams_diagnostics1_term6_to_persian_digits((string) $activeQuestionCount);

    $course = [
        'slug' => $courseSlug,
        'title' => 'تشخیصی ۱',
        'shortTitle' => 'تشخیصی ۱',
        'badge' => $plannedExamCountFa . ' جلسه',
        'cardDescription' => 'فعلاً ' . $activeExamCountFa . ' جلسه این درس فعال شده و بقیه جلسه‌ها به‌زودی از همین صفحه اضافه می‌شوند.',
        'heroTitle' => 'تشخیصی ۱',
        'heroDescription' => 'این مجموعه مربوط به درس تشخیصی ۱ ترم ۶ است. فعلاً '
            . $activeExamCountFa . ' جلسه از ' . $plannedExamCountFa . ' جلسه با مجموع '
            . $activeQuestionCountFa . ' سؤال فعال شده‌اند و بقیه جلسه‌ها به‌زودی از همین مسیر تکمیل می‌شوند. با یک بار پرداخت ۳۰ هزار تومان، کل درس برای همین حساب فعال می‌شود.',
        'path' => '/exams/' . $courseSlug . '/',
        'paymentTitle' => 'دسترسی به آزمون‌های تشخیصی ۱',
        'paymentDescription' => 'با یک بار پرداخت ۳۰ هزار تومان، همهٔ جلسه‌های درس تشخیصی ۱ برای همین حساب فعال می‌شود.',
        'paymentSuccessMessage' => 'پرداخت شما تایید شد و همهٔ جلسه‌های تشخیصی ۱ برای این حساب باز شد.',
        'paymentFailureMessage' => 'فعال‌سازی آزمون‌های تشخیصی ۱ انجام نشد. نتیجه را دوباره بررسی کنید.',
        'defaultPaymentMode' => 'paid',
        'defaultAmount' => 300000,
        'exams' => $examPayloads,
    ];

    return $course;
}

function dent_exams_diagnostics1_term6_exam_definitions(): array
{
    return [
        ['slug' => '1-2', 'label' => 'جلسه ۱ و ۲'],
        ['slug' => '3', 'label' => 'جلسه ۳'],
        ['slug' => '4', 'label' => 'جلسه ۴'],
        ['slug' => '5', 'label' => 'جلسه ۵'],
        ['slug' => '6', 'label' => 'جلسه ۶'],
        ['slug' => '7', 'label' => 'جلسه ۷'],
        ['slug' => '8', 'label' => 'جلسه ۸'],
        ['slug' => '9', 'label' => 'جلسه ۹'],
        ['slug' => '10', 'label' => 'جلسه ۱۰'],
        ['slug' => '11', 'label' => 'جلسه ۱۱'],
        ['slug' => '12', 'label' => 'جلسه ۱۲'],
        ['slug' => '13', 'label' => 'جلسه ۱۳'],
        ['slug' => '14', 'label' => 'جلسه ۱۴'],
    ];
}

function dent_exams_diagnostics1_term6_exam_payloads(string $courseSlug, array $examDefinitions): array
{
    $payloadsBySlug = dent_exams_diagnostics1_term6_source_payloads_by_slug();
    $payloads = [];

    foreach ($examDefinitions as $definition) {
        $slug = trim((string) ($definition['slug'] ?? ''));
        $label = trim((string) ($definition['label'] ?? ''));
        if ($slug === '' || $label === '') {
            continue;
        }

        $sourcePayload = is_array($payloadsBySlug[$slug] ?? null) ? $payloadsBySlug[$slug] : [];
        $questions = is_array($sourcePayload['questions'] ?? null) ? $sourcePayload['questions'] : [];
        $topic = trim((string) ($sourcePayload['topic'] ?? ''));
        $comingSoon = $questions === [];
        $questionCount = count($questions);
        $path = '/exams/' . $courseSlug . '/' . $slug . '/';

        $title = $topic !== ''
            ? 'آزمون ' . $label . ' - ' . $topic
            : ($comingSoon ? 'آزمون ' . $label . ' - به‌زودی' : 'آزمون ' . $label);
        $subtitle = $comingSoon
            ? 'صفحه این جلسه آماده است و سؤال‌های آن به‌زودی از همین مسیر فعال می‌شود.'
            : dent_exams_diagnostics1_term6_to_persian_digits((string) $questionCount)
                . ' سؤال چهارگزینه‌ای با پاسخ تشریحی'
                . ($topic !== '' ? ' از مبحث «' . $topic . '».' : '.');
        $description = $comingSoon
            ? 'سؤال‌های این جلسه هنوز اضافه نشده‌اند و به‌زودی از همین صفحه در دسترس قرار می‌گیرند.'
            : 'مرور ' . dent_exams_diagnostics1_term6_to_persian_digits((string) $questionCount)
                . ' سؤال' . ($topic !== '' ? ' از مبحث «' . $topic . '»' : '') . ' در تشخیصی ۱.';

        $payloads[] = [
            'slug' => $slug,
            'path' => $path,
            'label' => $label,
            'title' => $title,
            'subtitle' => $subtitle,
            'description' => $description,
            'eyebrow' => 'تشخیصی ۱ | ' . $label . ($topic !== '' ? ' | ' . $topic : ''),
            'ctaLabel' => $comingSoon ? 'مشاهده وضعیت جلسه' : 'انتخاب حالت و شروع',
            'backHref' => '/exams/' . $courseSlug . '/',
            'backLabel' => 'بازگشت به فهرست آزمون‌های تشخیصی ۱',
            'autoAdvance' => true,
            'siteTitle' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
            'siteSubtitle' => 'آزمون‌ها',
            'siteBadge' => 'آزمون تشخیصی',
            'footerText' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
            'attemptable' => true,
            'comingSoon' => $comingSoon,
            'emptyStateTitle' => 'سؤال‌های این جلسه به‌زودی اضافه می‌شود',
            'emptyStateMessage' => 'صفحه ' . $label . ' از همین حالا آماده است و سؤال‌های آن به‌زودی در همین مسیر قرار می‌گیرد.',
            'countsTowardStats' => !$comingSoon,
            'questionCount' => $questionCount,
            'questions' => $questions,
        ];
    }

    return $payloads;
}

function dent_exams_diagnostics1_term6_source_payloads_by_slug(): array
{
    static $payloads = null;
    if (is_array($payloads)) {
        return $payloads;
    }

    $payloads = [];
    foreach (dent_exams_diagnostics1_term6_source_patterns() as $slug => $patterns) {
        $path = dent_exams_diagnostics1_term6_find_source_file(is_array($patterns) ? $patterns : []);
        if ($path === '') {
            $payloads[$slug] = [
                'topic' => '',
                'questions' => [],
            ];
            continue;
        }

        $payloads[$slug] = dent_exams_diagnostics1_term6_parse_source_file($path);
    }

    return $payloads;
}

function dent_exams_diagnostics1_term6_source_patterns(): array
{
    return [
        '1-2' => ['session_1_2*.txt', 'session1_2*.txt'],
        '3' => ['session3*.txt'],
        '4' => ['session4*.txt'],
        '5' => ['session5*.txt'],
        '6' => ['session6*.txt'],
        '7' => ['session7*.txt'],
        '8' => ['session8*.txt'],
        '9' => ['session9*.txt'],
        '10' => ['session10*.txt'],
        '11' => ['session11*.txt'],
        '12' => ['session12*.txt'],
        '13' => ['session13*.txt'],
        '14' => ['session14*.txt'],
    ];
}

function dent_exams_diagnostics1_term6_find_source_file(array $patterns): string
{
    $baseDir = __DIR__ . '/data/diagnostics1_term6';
    foreach ($patterns as $pattern) {
        $globPattern = $baseDir . '/' . ltrim((string) $pattern, '\\/');
        $matches = glob($globPattern) ?: [];
        sort($matches, SORT_STRING);
        foreach ($matches as $match) {
            if (is_string($match) && is_file($match)) {
                return $match;
            }
        }
    }

    return '';
}

function dent_exams_diagnostics1_term6_parse_source_file(string $path): array
{
    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        return [
            'topic' => '',
            'questions' => [],
        ];
    }

    $text = dent_exams_diagnostics1_term6_normalize_text($raw);
    [$questionText, $answerText] = dent_exams_diagnostics1_term6_split_sections($text);
    $questionMap = dent_exams_diagnostics1_term6_collect_questions($questionText);
    $answerMap = dent_exams_diagnostics1_term6_collect_answers($answerText);
    $questions = [];

    foreach ($questionMap as $questionData) {
        if (!is_array($questionData)) {
            continue;
        }

        $number = max(0, (int) ($questionData['number'] ?? 0));
        $answerData = is_array($answerMap[$number] ?? null) ? $answerMap[$number] : null;
        $options = is_array($questionData['options'] ?? null) ? $questionData['options'] : [];

        if ($number <= 0 || $answerData === null || count($options) !== 4) {
            continue;
        }

        $questions[] = [
            'question' => (string) ($questionData['question'] ?? ''),
            'options' => array_values($options),
            'correctIndex' => max(0, min(3, (int) ($answerData['correctIndex'] ?? 0))),
            'explanation' => (string) ($answerData['explanation'] ?? ''),
        ];
    }

    return [
        'topic' => dent_exams_diagnostics1_term6_extract_topic($text),
        'questions' => $questions,
    ];
}

function dent_exams_diagnostics1_term6_split_sections(string $text): array
{
    $markerPattern = '/^\s*(?:بخش\s*دوم\s*:\s*)?پاسخنامه(?:\s*تشریحی(?:\s*کامل)?)?\s*$/miu';
    if (preg_match($markerPattern, $text, $matches, PREG_OFFSET_CAPTURE) !== 1) {
        return [$text, ''];
    }

    $offset = (int) ($matches[0][1] ?? 0);
    if ($offset < 0) {
        return [$text, ''];
    }

    $questionText = substr($text, 0, $offset);
    $answerText = substr($text, $offset);
    return [$questionText === false ? $text : $questionText, $answerText === false ? '' : $answerText];
}

function dent_exams_diagnostics1_term6_collect_questions(string $text): array
{
    $lines = preg_split('/\R/u', $text) ?: [];
    $questions = [];
    $currentNumber = 0;
    $questionLines = [];
    $options = [];
    $currentOptionKey = '';

    $flush = static function () use (&$questions, &$currentNumber, &$questionLines, &$options, &$currentOptionKey): void {
        if ($currentNumber <= 0) {
            $questionLines = [];
            $options = [];
            $currentOptionKey = '';
            return;
        }

        $normalizedOptions = [];
        foreach (['الف', 'ب', 'ج', 'د'] as $optionKey) {
            $optionValue = trim((string) ($options[$optionKey] ?? ''));
            if ($optionValue !== '') {
                $normalizedOptions[] = dent_exams_diagnostics1_term6_inline_text($optionValue);
            }
        }

        if (count($normalizedOptions) === 4) {
            $questions[] = [
                'number' => $currentNumber,
                'question' => dent_exams_diagnostics1_term6_inline_text(implode(' ', $questionLines)),
                'options' => $normalizedOptions,
            ];
        }

        $currentNumber = 0;
        $questionLines = [];
        $options = [];
        $currentOptionKey = '';
    };

    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '') {
            continue;
        }

        $questionStart = dent_exams_diagnostics1_term6_match_question_start($trimmed);
        if ($questionStart !== null) {
            $flush();
            $currentNumber = max(0, (int) ($questionStart['number'] ?? 0));
            $currentOptionKey = '';
            $questionText = trim((string) ($questionStart['text'] ?? ''));
            if ($questionText !== '') {
                $questionLines[] = $questionText;
            }
            continue;
        }

        $optionStart = dent_exams_diagnostics1_term6_match_option_start($trimmed);
        if ($optionStart !== null && $currentNumber > 0) {
            $currentOptionKey = (string) ($optionStart['key'] ?? '');
            if ($currentOptionKey !== '') {
                $options[$currentOptionKey] = trim((string) ($optionStart['text'] ?? ''));
            }
            continue;
        }

        if ($currentNumber <= 0) {
            continue;
        }

        if ($currentOptionKey !== '') {
            $options[$currentOptionKey] = trim((string) ($options[$currentOptionKey] ?? '') . ' ' . $trimmed);
        } else {
            $questionLines[] = $trimmed;
        }
    }

    $flush();
    return $questions;
}

function dent_exams_diagnostics1_term6_collect_answers(string $text): array
{
    $lines = preg_split('/\R/u', $text) ?: [];
    $answers = [];
    $currentNumber = 0;
    $currentCorrectIndex = 0;
    $explanationLines = [];

    $flush = static function () use (&$answers, &$currentNumber, &$currentCorrectIndex, &$explanationLines): void {
        if ($currentNumber <= 0) {
            $explanationLines = [];
            return;
        }

        $answers[$currentNumber] = [
            'correctIndex' => $currentCorrectIndex,
            'explanation' => dent_exams_diagnostics1_term6_explanation_text($explanationLines),
        ];

        $currentNumber = 0;
        $currentCorrectIndex = 0;
        $explanationLines = [];
    };

    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '') {
            if ($currentNumber > 0 && end($explanationLines) !== '') {
                $explanationLines[] = '';
            }
            continue;
        }

        $answerStart = dent_exams_diagnostics1_term6_match_answer_start($trimmed);
        if ($answerStart !== null) {
            $flush();
            $currentNumber = max(0, (int) ($answerStart['number'] ?? 0));
            $currentCorrectIndex = max(0, min(3, (int) ($answerStart['correctIndex'] ?? 0)));
            continue;
        }

        if ($currentNumber > 0) {
            $explanationLines[] = $trimmed;
        }
    }

    $flush();
    return $answers;
}

function dent_exams_diagnostics1_term6_match_question_start(string $line): ?array
{
    if (preg_match('/^\s*([0-9۰-۹]+)\s*[\)\.]\s*(.+)?$/u', $line, $matches) === 1) {
        return [
            'number' => dent_exams_diagnostics1_term6_int((string) ($matches[1] ?? '0')),
            'text' => trim((string) ($matches[2] ?? '')),
        ];
    }

    if (preg_match('/^\s*(?:سؤال|سوال)\s*([0-9۰-۹]+)\s*[\)\.:\-]?\s*(.+)?$/u', $line, $matches) === 1) {
        return [
            'number' => dent_exams_diagnostics1_term6_int((string) ($matches[1] ?? '0')),
            'text' => trim((string) ($matches[2] ?? '')),
        ];
    }

    return null;
}

function dent_exams_diagnostics1_term6_match_option_start(string $line): ?array
{
    if (preg_match('/^\s*(الف|ب|ج|د)\s*[\)\.]\s*(.+)?$/u', $line, $matches) !== 1) {
        return null;
    }

    return [
        'key' => trim((string) ($matches[1] ?? '')),
        'text' => trim((string) ($matches[2] ?? '')),
    ];
}

function dent_exams_diagnostics1_term6_match_answer_start(string $line): ?array
{
    if (
        preg_match(
            '/^\s*([0-9۰-۹]+)\s*[\)\.]\s*پاسخ(?:\s*(?:صحیح|درست))?\s*:\s*(?:گزینه\s*)?(الف|ب|ج|د)\s*$/u',
            $line,
            $matches
        ) === 1
    ) {
        return [
            'number' => dent_exams_diagnostics1_term6_int((string) ($matches[1] ?? '0')),
            'correctIndex' => dent_exams_diagnostics1_term6_option_index((string) ($matches[2] ?? '')),
        ];
    }

    if (
        preg_match(
            '/^\s*پاسخ\s*سؤال\s*([0-9۰-۹]+)\s*:\s*(?:گزینه\s*)?(الف|ب|ج|د)\s*$/u',
            $line,
            $matches
        ) === 1
    ) {
        return [
            'number' => dent_exams_diagnostics1_term6_int((string) ($matches[1] ?? '0')),
            'correctIndex' => dent_exams_diagnostics1_term6_option_index((string) ($matches[2] ?? '')),
        ];
    }

    return null;
}

function dent_exams_diagnostics1_term6_option_index(string $value): int
{
    switch (trim($value)) {
        case 'ب':
            return 1;
        case 'ج':
            return 2;
        case 'د':
            return 3;
        case 'الف':
        default:
            return 0;
    }
}

function dent_exams_diagnostics1_term6_extract_topic(string $text): string
{
    if (preg_match('/^\s*(?:مبحث|موضوع)\s*:\s*(.+)$/miu', $text, $matches) !== 1) {
        return '';
    }

    return dent_exams_diagnostics1_term6_inline_text((string) ($matches[1] ?? ''));
}

function dent_exams_diagnostics1_term6_explanation_text(array $lines): string
{
    $cleanLines = [];
    $lastWasBlank = false;

    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '') {
            if (!$lastWasBlank && $cleanLines !== []) {
                $cleanLines[] = '';
            }
            $lastWasBlank = true;
            continue;
        }

        $cleanLines[] = dent_exams_diagnostics1_term6_inline_text($trimmed);
        $lastWasBlank = false;
    }

    return trim(implode("\n", $cleanLines));
}

function dent_exams_diagnostics1_term6_inline_text(string $text): string
{
    return trim((string) preg_replace('/\s+/u', ' ', trim($text)));
}

function dent_exams_diagnostics1_term6_int(string $value): int
{
    $digits = preg_replace('/[^0-9]/', '', dent_exams_diagnostics1_term6_ascii_digits($value));
    return $digits === '' ? 0 : (int) $digits;
}

function dent_exams_diagnostics1_term6_ascii_digits(string $value): string
{
    return strtr($value, [
        '۰' => '0',
        '۱' => '1',
        '۲' => '2',
        '۳' => '3',
        '۴' => '4',
        '۵' => '5',
        '۶' => '6',
        '۷' => '7',
        '۸' => '8',
        '۹' => '9',
        '٠' => '0',
        '١' => '1',
        '٢' => '2',
        '٣' => '3',
        '٤' => '4',
        '٥' => '5',
        '٦' => '6',
        '٧' => '7',
        '٨' => '8',
        '٩' => '9',
    ]);
}

function dent_exams_diagnostics1_term6_to_persian_digits(string $value): string
{
    return strtr($value, [
        '0' => '۰',
        '1' => '۱',
        '2' => '۲',
        '3' => '۳',
        '4' => '۴',
        '5' => '۵',
        '6' => '۶',
        '7' => '۷',
        '8' => '۸',
        '9' => '۹',
    ]);
}

function dent_exams_diagnostics1_term6_normalize_text(string $text): string
{
    $text = preg_replace('/^\xEF\xBB\xBF/u', '', $text);
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = strtr($text, [
        'ي' => 'ی',
        'ك' => 'ک',
        "\xC2\xA0" => ' ',
        "\u{200C}" => '‌',
    ]);

    return is_string($text) ? trim($text) : '';
}
