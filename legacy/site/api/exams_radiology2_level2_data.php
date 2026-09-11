<?php
declare(strict_types=1);

function dent_exams_radiology2_apply_level2_exams(array $course): array
{
    if (!is_array($course['exams'] ?? null)) {
        return $course;
    }

    foreach (dent_exams_radiology2_level2_course_overrides() as $key => $value) {
        $course[$key] = $value;
    }

    $course['exams'] = dent_exams_radiology2_merge_level2_exams($course['exams']);
    return $course;
}

function dent_exams_radiology2_level2_course_overrides(): array
{
    return [
        'badge' => '۳۰ آزمون',
        'cardDescription' => 'برای هر جلسه، آزمون اصلی و آزمون‌های سطح دوم را از همین صفحه جداگانه شروع کن.',
        'heroDescription' => 'برای هر جلسه علاوه بر آزمون اصلی، آزمون سطح دوم همان مبحث هم در دسترس است و با همان خرید رادیولوژی باز می‌شود.',
        'paymentDescription' => 'با یک بار پرداخت، همه آزمون‌های سطح اول و سطح دوم درس رادیولوژی نظری ۲ برای شما فعال می‌شود.',
        'paymentSuccessMessage' => 'پرداخت شما تایید شد و همه آزمون‌های سطح اول و سطح دوم رادیولوژی نظری ۲ برای این حساب باز شد.',
    ];
}

function dent_exams_radiology2_merge_level2_exams(array $exams): array
{
    foreach ($exams as $exam) {
        if (!is_array($exam)) {
            continue;
        }

        $slug = trim((string) ($exam['slug'] ?? ''));
        if ($slug !== '' && preg_match('/-2$/', $slug) === 1) {
            return $exams;
        }
    }

    $sessionQuestions = dent_exams_radiology2_level2_session_questions();
    $merged = [];

    foreach ($exams as $exam) {
        $merged[] = $exam;
        if (!is_array($exam)) {
            continue;
        }

        $sessionNumber = dent_exams_radiology2_level2_slug_to_session((string) ($exam['slug'] ?? ''));
        if ($sessionNumber < 1 || $sessionNumber > 15) {
            continue;
        }

        $merged[] = dent_exams_radiology2_build_level2_exam(
            $exam,
            $sessionNumber,
            $sessionQuestions[$sessionNumber] ?? []
        );
    }

    return $merged;
}

function dent_exams_radiology2_build_level2_exam(array $baseExam, int $sessionNumber, array $questions): array
{
    $sessionFa = dent_exams_radiology2_level2_to_persian_digits((string) $sessionNumber);
    $topic = dent_exams_radiology2_level2_topic_from_exam($baseExam, $sessionNumber);
    $hasQuestions = $questions !== [];

    $exam = $baseExam;
    $exam['slug'] = $sessionNumber . '-2';
    $exam['path'] = '/exams/radiology2/' . $sessionNumber . '-2/';
    $exam['label'] = 'جلسه ' . $sessionNumber . ' · آزمون‌های سطح دوم';
    $exam['title'] = dent_exams_radiology2_level2_title_from_exam($baseExam, $sessionFa);
    $exam['subtitle'] = $hasQuestions
        ? 'این مجموعه دوم از همان مبحث، با همان دسترسی رادیولوژی و در دو حالت سنجشی و آموزشی در اختیار شماست.'
        : 'صفحه این جلسه آماده است. سوالات سطح دوم این جلسه به‌زودی اضافه می‌شود و از همین مسیر در دسترس خواهد بود.';
    $exam['description'] = $hasQuestions
        ? 'مجموعه دوم سوالات این جلسه برای مرور عمیق‌تر همین مبحث آماده شده است.'
        : 'صفحه این جلسه آماده است و سوالات سطح دوم آن به‌زودی اضافه می‌شود.';
    $exam['ctaLabel'] = $hasQuestions ? 'انتخاب حالت و شروع' : 'مشاهده صفحه آزمون';
    $exam['eyebrow'] = 'آزمون‌های سطح دوم رادیولوژی نظری ۲ - جلسه ' . $sessionNumber
        . ($topic !== '' ? ' (' . $topic . ')' : '');
    $exam['siteBadge'] = 'آزمون‌های سطح دوم';
    $exam['questions'] = $questions;
    $exam['questionCount'] = count($questions);

    return $exam;
}

function dent_exams_radiology2_level2_title_from_exam(array $baseExam, string $sessionFa): string
{
    $baseTitle = trim((string) ($baseExam['title'] ?? ''));
    if ($baseTitle !== '') {
        $replaced = preg_replace(
            '/^سؤالات\s+جلسه\s+[0-9۰-۹]+/u',
            'سؤالات سطح دوم جلسه ' . $sessionFa,
            $baseTitle,
            1
        );
        if (is_string($replaced) && $replaced !== '') {
            return $replaced;
        }
    }

    $topic = dent_exams_radiology2_level2_topic_from_exam($baseExam, (int) dent_exams_radiology2_level2_ascii_digits($sessionFa));
    return 'سؤالات سطح دوم جلسه ' . $sessionFa . ($topic !== '' ? ' – ' . $topic : '');
}

function dent_exams_radiology2_level2_topic_from_exam(array $baseExam, int $sessionNumber): string
{
    $baseTitle = trim((string) ($baseExam['title'] ?? ''));
    if ($baseTitle !== '' && preg_match('/[–-]\s*(.+)$/u', $baseTitle, $matches) === 1) {
        return trim((string) ($matches[1] ?? ''));
    }

    $eyebrow = trim((string) ($baseExam['eyebrow'] ?? ''));
    if ($eyebrow !== '' && preg_match('/\((.+)\)\s*$/u', $eyebrow, $matches) === 1) {
        return trim((string) ($matches[1] ?? ''));
    }

    return 'مبحث جلسه ' . dent_exams_radiology2_level2_to_persian_digits((string) $sessionNumber);
}

function dent_exams_radiology2_level2_session_questions(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $cache = [];
    for ($session = 1; $session <= 15; $session++) {
        $cache[$session] = [];
    }

    foreach (dent_exams_radiology2_level2_source_paths() as $path) {
        if (!is_file($path)) {
            continue;
        }

        $parsed = dent_exams_radiology2_level2_parse_source((string) file_get_contents($path));
        foreach ($parsed as $session => $questions) {
            $sessionNumber = (int) $session;
            if ($sessionNumber < 1 || $sessionNumber > 15 || !is_array($questions)) {
                continue;
            }

            $cache[$sessionNumber] = $questions;
        }
    }

    return $cache;
}

function dent_exams_radiology2_level2_source_paths(): array
{
    return [
        __DIR__ . '/data/radiology2_level2_sessions_1_to_5.txt',
        __DIR__ . '/data/radiology2_level2_sessions_6_to_10.txt',
        __DIR__ . '/data/radiology2_level2_sessions_11_to_15.txt',
    ];
}

function dent_exams_radiology2_level2_parse_source(string $text): array
{
    $normalized = dent_exams_radiology2_level2_normalize_text($text);
    $answerPos = strpos($normalized, 'بخش دوم');
    $questionText = $answerPos === false ? $normalized : substr($normalized, 0, $answerPos);
    $answerText = $answerPos === false ? '' : substr($normalized, $answerPos);

    $questionBlocks = dent_exams_radiology2_level2_collect_session_blocks($questionText);
    $answerBlocks = dent_exams_radiology2_level2_collect_session_blocks($answerText);
    $sessions = [];

    foreach ($questionBlocks as $sessionNumber => $questionLines) {
        $questions = dent_exams_radiology2_level2_parse_question_block($questionLines);
        $answersByNumber = dent_exams_radiology2_level2_parse_answer_block($answerBlocks[$sessionNumber] ?? []);
        $sessionQuestions = [];

        foreach ($questions as $question) {
            $questionNumber = (int) ($question['number'] ?? 0);
            if ($questionNumber < 1 || !isset($answersByNumber[$questionNumber])) {
                continue;
            }

            $answer = $answersByNumber[$questionNumber];
            $options = is_array($question['options'] ?? null) ? $question['options'] : [];
            if (count($options) < 4) {
                continue;
            }

            $sessionQuestions[] = [
                'question' => (string) ($question['question'] ?? ''),
                'options' => array_values(array_slice($options, 0, 4)),
                'correctIndex' => max(0, min(3, (int) ($answer['correctIndex'] ?? 0))),
                'explanation' => dent_exams_radiology2_level2_format_explanation(
                    is_array($answer['lines'] ?? null) ? $answer['lines'] : []
                ),
            ];
        }

        $sessions[$sessionNumber] = $sessionQuestions;
    }

    return $sessions;
}

function dent_exams_radiology2_level2_collect_session_blocks(string $text): array
{
    $lines = preg_split('/\R/u', dent_exams_radiology2_level2_normalize_text($text)) ?: [];
    $blocks = [];
    $currentSession = null;
    $currentLines = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed !== '' && preg_match('/^-*\s*(?:پاسخنامه\s+)?جلسه\s+([0-9۰-۹]+)\b.*$/u', $trimmed, $matches) === 1) {
            if ($currentSession !== null) {
                $blocks[$currentSession] = $currentLines;
            }

            $currentSession = dent_exams_radiology2_level2_int((string) ($matches[1] ?? ''));
            $currentLines = [];
            continue;
        }

        if ($currentSession === null) {
            continue;
        }

        if ($trimmed === '' || preg_match('/^[-=]{3,}$/u', $trimmed) === 1) {
            continue;
        }

        $currentLines[] = rtrim($line);
    }

    if ($currentSession !== null) {
        $blocks[$currentSession] = $currentLines;
    }

    return $blocks;
}

function dent_exams_radiology2_level2_parse_question_block(array $lines): array
{
    $questions = [];
    $current = null;
    $currentOption = null;

    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '') {
            continue;
        }

        if (preg_match('/^\s*([0-9۰-۹]+)\.\s*(.+)$/u', $trimmed, $matches) === 1) {
            if (is_array($current)) {
                $questions[] = $current;
            }

            $current = [
                'number' => dent_exams_radiology2_level2_int((string) ($matches[1] ?? '')),
                'question' => trim((string) ($matches[2] ?? '')),
                'options' => [],
            ];
            $currentOption = null;
            continue;
        }

        if ($current === null) {
            continue;
        }

        if (preg_match('/^\s*(الف|ب|ج|د)\)\s*(.+)$/u', $trimmed, $matches) === 1) {
            $current['options'][] = trim((string) ($matches[2] ?? ''));
            $currentOption = count($current['options']) - 1;
            continue;
        }

        if ($currentOption === null) {
            $current['question'] .= ' ' . $trimmed;
            continue;
        }

        $current['options'][$currentOption] .= ' ' . $trimmed;
    }

    if (is_array($current)) {
        $questions[] = $current;
    }

    return $questions;
}

function dent_exams_radiology2_level2_parse_answer_block(array $lines): array
{
    $answers = [];
    $currentNumber = null;
    $currentLines = [];
    $currentCorrectIndex = 0;

    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '') {
            continue;
        }

        if (preg_match('/^\s*([0-9۰-۹]+)\.\s*پاسخ(?:\s+درست)?\s*:\s*(.+)$/u', $trimmed, $matches) === 1) {
            if ($currentNumber !== null) {
                $answers[$currentNumber] = [
                    'correctIndex' => $currentCorrectIndex,
                    'lines' => $currentLines,
                ];
            }

            $currentNumber = dent_exams_radiology2_level2_int((string) ($matches[1] ?? ''));
            $currentCorrectIndex = dent_exams_radiology2_level2_correct_index((string) ($matches[2] ?? ''));
            $currentLines = ['پاسخ درست: ' . trim((string) ($matches[2] ?? ''))];
            continue;
        }

        if ($currentNumber === null) {
            continue;
        }

        $currentLines[] = $trimmed;
    }

    if ($currentNumber !== null) {
        $answers[$currentNumber] = [
            'correctIndex' => $currentCorrectIndex,
            'lines' => $currentLines,
        ];
    }

    return $answers;
}

function dent_exams_radiology2_level2_format_explanation(array $lines): string
{
    $labels = [
        'پاسخ درست',
        'پاسخ',
        'منبع',
        'علت درستی',
        'علت رد گزینه‌های دیگر',
        'علت رد گزینه های دیگر',
        'علت رد گزینه‌های غلط',
        'علت رد گزینه های غلط',
    ];
    $formatted = [];

    foreach ($lines as $line) {
        $text = trim((string) $line);
        if ($text === '') {
            continue;
        }

        $isLabeled = false;
        foreach ($labels as $label) {
            $prefix = $label . ':';
            if (strpos($text, $prefix) !== 0) {
                continue;
            }

            $formatted[] = '**' . $label . ':** ' . trim(substr($text, strlen($prefix)));
            $isLabeled = true;
            break;
        }

        if (!$isLabeled) {
            $formatted[] = $text;
        }
    }

    return implode("\n", $formatted);
}

function dent_exams_radiology2_level2_correct_index(string $value): int
{
    $normalized = preg_replace('/^گزینه\s+/u', '', trim($value));
    if (!is_string($normalized)) {
        return 0;
    }

    $normalized = trim($normalized);
    $map = [
        'الف' => 0,
        'ب' => 1,
        'ج' => 2,
        'د' => 3,
    ];

    foreach ($map as $letter => $index) {
        if (strpos($normalized, $letter) === 0) {
            return $index;
        }
    }

    return 0;
}

function dent_exams_radiology2_level2_slug_to_session(string $slug): int
{
    $normalized = trim($slug);
    if ($normalized === '' || preg_match('/^[0-9]+$/', $normalized) !== 1) {
        return 0;
    }

    return (int) $normalized;
}

function dent_exams_radiology2_level2_int(string $value): int
{
    $digits = preg_replace('/[^0-9]/', '', dent_exams_radiology2_level2_ascii_digits($value));
    return $digits === '' ? 0 : (int) $digits;
}

function dent_exams_radiology2_level2_ascii_digits(string $value): string
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
    ]);
}

function dent_exams_radiology2_level2_to_persian_digits(string $value): string
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

function dent_exams_radiology2_level2_normalize_text(string $text): string
{
    $withoutBom = preg_replace('/^\xEF\xBB\xBF/', '', $text);
    if (!is_string($withoutBom)) {
        $withoutBom = $text;
    }

    return str_replace(["\r\n", "\r"], "\n", $withoutBom);
}
