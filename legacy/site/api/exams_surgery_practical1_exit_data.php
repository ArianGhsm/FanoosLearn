<?php
declare(strict_types=1);

require_once __DIR__ . '/text_source_parser.php';

function dent_exams_surgery_practical1_exit_parse_file(string $path): array
{
    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return ['topic' => '', 'questions' => []];
    }

    $text = dent_exams_text_source_normalize_text($raw);
    $parts = preg_split('/^\s*پاسخنامه\s+تشریحی\s*$/miu', $text, 2);
    $questionMap = dent_exams_text_source_collect_questions(trim((string) ($parts[0] ?? $text)));
    $answerText = trim((string) ($parts[1] ?? ''));
    $answerParts = preg_split('/^\s*س(?:ؤ|و)ال\s*(\d+)\s*$/mu', $answerText, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
    $answerMap = [];

    for ($index = 1; $index + 1 < count($answerParts); $index += 2) {
        $number = max(0, (int) ($answerParts[$index] ?? 0));
        $answer = dent_exams_surgery_practical1_exit_parse_answer(trim((string) ($answerParts[$index + 1] ?? '')));
        if ($number > 0 && $answer !== null) {
            $answerMap[$number] = $answer;
        }
    }

    $questions = [];
    foreach ($questionMap as $questionData) {
        $number = max(0, (int) ($questionData['number'] ?? 0));
        $options = is_array($questionData['options'] ?? null) ? array_values($questionData['options']) : [];
        $answer = is_array($answerMap[$number] ?? null) ? $answerMap[$number] : null;
        if ($number <= 0 || count($options) !== 4 || $answer === null) {
            continue;
        }
        $questions[] = array_merge([
            'number' => $number,
            'question' => (string) ($questionData['question'] ?? ''),
            'options' => $options,
        ], $answer);
    }

    return ['topic' => '', 'questions' => $questions];
}

function dent_exams_surgery_practical1_exit_parse_answer(string $chunk): ?array
{
    $lines = preg_split('/\R/u', $chunk) ?: [];
    $correctRaw = '';
    $sections = ['reason' => [], 'analysis' => [], 'reference' => []];
    $section = '';

    for ($index = 0; $index < count($lines); $index++) {
        $line = trim((string) $lines[$index]);
        if ($line === '') {
            continue;
        }
        if (preg_match('/^پاسخ\s+درست\s*:\s*(.*)$/u', $line, $matches) === 1) {
            $correctRaw = trim((string) ($matches[1] ?? ''));
            if ($correctRaw === '') {
                while (++$index < count($lines) && $correctRaw === '') {
                    $correctRaw = trim((string) $lines[$index]);
                }
            }
            $section = '';
            continue;
        }
        if (preg_match('/^دلیل\s+درست(?:‌|-|\s)*بودن\s*:\s*$/u', $line) === 1) {
            $section = 'reason';
            continue;
        }
        if (preg_match('/^بررسی\s+گزینه(?:‌|-|\s)*ها\s*:\s*$/u', $line) === 1) {
            $section = 'analysis';
            continue;
        }
        if (preg_match('/^محل\s+پاسخ\s+در\s+منبع\s*:\s*$/u', $line) === 1) {
            $section = 'reference';
            continue;
        }
        if ($section !== '') {
            $sections[$section][] = $line;
        }
    }

    if (preg_match('/گزینه\s*(الف|ب|ج|د|a|b|c|d)/iu', $correctRaw, $matches) !== 1) {
        return null;
    }
    $correctIndex = dent_exams_text_source_option_letter_to_index((string) $matches[1]);
    $reason = dent_exams_surgery_practical1_exit_join_lines($sections['reason']);
    $analysis = dent_exams_surgery_practical1_exit_join_lines($sections['analysis']);
    $reference = dent_exams_surgery_practical1_exit_join_lines($sections['reference']);
    $explanationParts = ['**پاسخ درست:** ' . dent_exams_text_source_restore_digits($correctRaw)];
    $answerSections = [];
    if ($reason !== '') {
        $explanationParts[] = "**دلیل درست‌بودن:**\n" . $reason;
        $answerSections[] = ['label' => 'دلیل درست‌بودن', 'value' => $reason, 'tone' => 'success'];
    }
    if ($analysis !== '') {
        $explanationParts[] = "**بررسی گزینه‌ها:**\n" . $analysis;
        $answerSections[] = ['label' => 'بررسی گزینه‌ها', 'value' => $analysis];
    }
    $answerMeta = [['label' => 'پاسخ درست', 'value' => dent_exams_text_source_restore_digits($correctRaw), 'tone' => 'success']];
    if ($reference !== '') {
        $explanationParts[] = "**محل پاسخ در منبع:**\n" . $reference;
        $answerMeta[] = ['label' => 'محل پاسخ در منبع', 'value' => $reference, 'tone' => 'info', 'wide' => true];
    }

    return [
        'correctIndex' => $correctIndex,
        'explanation' => implode("\n\n", $explanationParts),
        'answerSections' => $answerSections,
        'optionRationales' => dent_exams_surgery_practical1_exit_option_rationales($sections['analysis']),
        'answerMeta' => $answerMeta,
    ];
}

function dent_exams_surgery_practical1_exit_join_lines(array $lines): string
{
    return dent_exams_text_source_restore_digits(trim(implode("\n", array_filter(array_map('trim', $lines)))));
}

function dent_exams_surgery_practical1_exit_option_rationales(array $lines): array
{
    $items = ['', '', '', ''];
    $active = null;
    foreach ($lines as $line) {
        if (preg_match('/^(الف|ب|ج|د)\)\s*(.*)$/u', trim((string) $line), $matches) === 1) {
            $active = dent_exams_text_source_option_letter_to_index((string) $matches[1]);
            $items[$active] = trim((string) $matches[2]);
        } elseif ($active !== null) {
            $items[$active] = trim($items[$active] . ' ' . trim((string) $line));
        }
    }
    return array_map('dent_exams_text_source_restore_digits', $items);
}

function dent_exams_surgery_practical1_exit_course(): array
{
    $course = dent_exams_text_source_build_course([
        'courseSlug' => 'surgery-practical-1-exit-practice',
        'title' => 'آزمون‌های تمرینی خروج از بخش جراحی عملی ۱',
        'shortTitle' => 'خروج از بخش جراحی عملی ۱',
        'termLabel' => 'ترم ۶',
        'dataDir' => __DIR__ . '/data/surgery_practical1_exit',
        'paymentAmount' => 300000,
        'examDefinitions' => [
            ['slug' => 'chapter-1', 'label' => 'فصل ۱', 'topic' => 'ارزیابی سلامت پیش از جراحی', 'patterns' => ['chapter1.txt'], 'parser' => 'dent_exams_surgery_practical1_exit_parse_file'],
            ['slug' => 'chapter-2', 'label' => 'فصل ۲', 'topic' => 'پیشگیری و مدیریت فوریت‌های پزشکی', 'patterns' => ['chapter2.txt'], 'parser' => 'dent_exams_surgery_practical1_exit_parse_file'],
            ['slug' => 'chapter-3', 'label' => 'فصل ۳', 'topic' => 'اصول جراحی', 'patterns' => ['chapter3.txt'], 'parser' => 'dent_exams_surgery_practical1_exit_parse_file'],
            ['slug' => 'chapter-5', 'label' => 'فصل ۵', 'topic' => 'کنترل عفونت در جراحی', 'patterns' => ['chapter5.txt'], 'parser' => 'dent_exams_surgery_practical1_exit_parse_file'],
            ['slug' => 'chapter-6', 'label' => 'فصل ۶', 'topic' => 'کنترل درد و اضطراب', 'patterns' => ['chapter6.txt'], 'parser' => 'dent_exams_surgery_practical1_exit_parse_file'],
            ['slug' => 'chapter-7', 'label' => 'فصل ۷', 'topic' => 'ابزارهای جراحی', 'patterns' => ['chapter7.txt'], 'parser' => 'dent_exams_surgery_practical1_exit_parse_file'],
            ['slug' => 'chapter-8', 'label' => 'فصل ۸', 'topic' => 'اصول کشیدن معمول دندان', 'patterns' => ['chapter8.txt'], 'parser' => 'dent_exams_surgery_practical1_exit_parse_file'],
            ['slug' => 'chapter-9', 'label' => 'فصل ۹', 'topic' => 'اصول کشیدن پیچیده دندان', 'patterns' => ['chapter9.txt'], 'parser' => 'dent_exams_surgery_practical1_exit_parse_file'],
        ],
    ]);

    if ($course === []) {
        return [];
    }

    $course['addedAt'] = '2026-08-01T01:00:00+03:30';
    $course['cardDescription'] = '۸ آزمون تمرینی فصل‌به‌فصل برای آمادگی خروج از بخش جراحی عملی ۱.';
    $course['heroTitle'] = 'آزمون‌های تمرینی خروج از بخش جراحی عملی ۱';
    $course['paymentTitle'] = 'دسترسی به ۸ آزمون خروج از بخش جراحی عملی ۱';
    $course['paymentDescription'] = 'با یک بار پرداخت ۳۰ هزار تومان، هر ۸ آزمون فصل‌به‌فصل برای همین حساب فعال می‌شود.';
    foreach ($course['exams'] as &$exam) {
        $exam['addedAt'] = $course['addedAt'];
    }
    unset($exam);

    return $course;
}
