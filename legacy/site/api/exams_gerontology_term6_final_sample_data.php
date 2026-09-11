<?php
declare(strict_types=1);

require_once __DIR__ . '/text_source_parser.php';

function dent_exams_gerontology_term6_final_sample_parse_file(string $path): array
{
    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return ['topic' => '', 'questions' => []];
    }

    $text = dent_exams_text_source_normalize_text($raw);
    $parts = preg_split('/^\s*س(?:ؤ|و)ال\s*(\d+)\s*$/mu', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
    $questions = [];
    for ($index = 1; $index + 1 < count($parts); $index += 2) {
        $question = dent_exams_gerontology_term6_final_sample_parse_chunk(
            max(0, (int) ($parts[$index] ?? 0)),
            trim((string) ($parts[$index + 1] ?? ''))
        );
        if (is_array($question)) {
            $questions[] = $question;
        }
    }
    return ['topic' => 'نمونه سوالات پایان‌ترم سالمندشناسی', 'questions' => $questions];
}

function dent_exams_gerontology_term6_final_sample_parse_chunk(int $number, string $chunk): ?array
{
    if ($number <= 0 || $chunk === '') {
        return null;
    }
    $lines = preg_split('/\R/u', $chunk) ?: [];
    $questionLines = [];
    $options = ['', '', '', ''];
    $sections = [];
    $markedRaw = '';
    $documentedRaw = '';
    $comparisonRaw = '';
    $activeSection = '';
    $readingOptions = true;

    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '' || preg_match('/^[\x{2500}-\x{257f}\-=*_ ]{5,}$/u', $line) === 1) {
            continue;
        }
        if (preg_match('/^(?:جمع‌بندی کنترل نهایی|پایان فایل)\b/u', $line) === 1) {
            break;
        }
        if (preg_match('/^(گزینه\s+علامت[‌-]?خورده\s+در\s+فایل(?:\s+آزمون)?)\s*[:：]\s*(.*)$/u', $line, $matches) === 1) {
            $markedRaw = dent_exams_text_source_restore_digits(trim((string) ($matches[2] ?? '')));
            $readingOptions = false;
            $activeSection = '';
            continue;
        }
        if (preg_match('/^(پاسخ\s+مستند)\s*[:：]\s*(.*)$/u', $line, $matches) === 1) {
            $documentedRaw = dent_exams_text_source_restore_digits(trim((string) ($matches[2] ?? '')));
            $readingOptions = false;
            $activeSection = '';
            continue;
        }
        if (preg_match('/^(وضعیت\s+تطابق)\s*[:：]\s*(.*)$/u', $line, $matches) === 1) {
            $comparisonRaw = dent_exams_text_source_restore_digits(trim((string) ($matches[2] ?? '')));
            $readingOptions = false;
            $activeSection = '';
            continue;
        }
        if (preg_match('/^(منبع|وضعیت\s+دسترسی\s+پاسخ\s+در\s+منابع|پاسخ\s+تشریحی|بررسی\s+تک[‌-]?تک\s+گزینه‌ها|نکات\s+و\s+اصطلاحات\s+مهم)\s*[:：]\s*(.*)$/u', $line, $matches) === 1) {
            $activeSection = dent_exams_text_source_restore_digits(trim((string) ($matches[1] ?? '')));
            $sections[$activeSection] = $sections[$activeSection] ?? [];
            $tail = trim((string) ($matches[2] ?? ''));
            if ($tail !== '') {
                $sections[$activeSection][] = $tail;
            }
            $readingOptions = false;
            continue;
        }
        if ($readingOptions && preg_match('/^([1-4])\)\s*(.+)$/u', $line, $matches) === 1) {
            $options[(int) $matches[1] - 1] = dent_exams_text_source_restore_digits(trim((string) $matches[2]));
            continue;
        }
        if ($activeSection !== '') {
            $sections[$activeSection][] = $line;
        } elseif ($readingOptions) {
            $questionLines[] = $line;
        }
    }

    $correctIndex = dent_exams_gerontology_term6_final_sample_option_index($documentedRaw);
    if (count(array_filter($options, static fn(string $option): bool => $option !== '')) !== 4 || $correctIndex === null) {
        return null;
    }
    $source = dent_exams_gerontology_term6_final_sample_join($sections['منبع'] ?? []);
    $availability = dent_exams_gerontology_term6_final_sample_join($sections['وضعیت دسترسی پاسخ در منابع'] ?? []);
    $reason = dent_exams_gerontology_term6_final_sample_join($sections['پاسخ تشریحی'] ?? []);
    $analysisLines = is_array($sections['بررسی تک‌تک گزینه‌ها'] ?? null) ? $sections['بررسی تک‌تک گزینه‌ها'] : [];
    $analysis = dent_exams_gerontology_term6_final_sample_join($analysisLines);
    $notes = dent_exams_gerontology_term6_final_sample_join($sections['نکات و اصطلاحات مهم'] ?? []);
    $answerSections = [];
    foreach ([
        ['پاسخ تشریحی', $reason, 'success'],
        ['بررسی تک‌تک گزینه‌ها', $analysis, ''],
        ['نکات و اصطلاحات مهم', $notes, 'info'],
    ] as [$label, $value, $tone]) {
        if ($value !== '') {
            $answerSections[] = ['label' => $label, 'value' => $value, 'tone' => $tone];
        }
    }
    $answerMeta = [
        ['label' => 'پاسخ مستند', 'value' => $documentedRaw, 'tone' => 'success'],
    ];
    if ($markedRaw !== '') {
        $answerMeta[] = ['label' => 'گزینه علامت‌خورده فایل', 'value' => $markedRaw, 'tone' => 'info'];
    }
    if ($comparisonRaw !== '') {
        $answerMeta[] = ['label' => 'وضعیت تطابق', 'value' => $comparisonRaw, 'wide' => true];
    }
    if ($source !== '') {
        $answerMeta[] = ['label' => 'منبع', 'value' => $source, 'tone' => 'info', 'wide' => true];
    }
    if ($availability !== '') {
        $answerMeta[] = ['label' => 'وضعیت دسترسی پاسخ', 'value' => $availability, 'wide' => true];
    }
    $explanation = ['**پاسخ مستند:** ' . $documentedRaw];
    if ($reason !== '') { $explanation[] = "**پاسخ تشریحی:**\n" . $reason; }
    if ($analysis !== '') { $explanation[] = "**بررسی تک‌تک گزینه‌ها:**\n" . $analysis; }
    if ($source !== '') { $explanation[] = "**منبع:**\n" . $source; }

    return [
        'number' => $number,
        'question' => dent_exams_text_source_inline_text(implode(' ', $questionLines)),
        'options' => $options,
        'correctIndex' => $correctIndex,
        'explanation' => implode("\n\n", $explanation),
        'answerCardLabel' => 'پاسخ تشریحی، منبع و تحلیل گزینه‌ها',
        'answerSections' => $answerSections,
        'answerMeta' => $answerMeta,
        'optionRationales' => dent_exams_gerontology_term6_final_sample_option_rationales($analysisLines),
    ];
}

function dent_exams_gerontology_term6_final_sample_option_index(string $value): ?int
{
    $value = dent_exams_text_source_normalize_digits($value);
    return preg_match('/گزینه\s*([1-4])\)/u', $value, $matches) === 1
        ? ((int) $matches[1] - 1)
        : null;
}

function dent_exams_gerontology_term6_final_sample_join(array $lines): string
{
    return dent_exams_text_source_restore_digits(trim(implode("\n", array_filter(array_map('trim', $lines)))));
}

function dent_exams_gerontology_term6_final_sample_option_rationales(array $lines): array
{
    $items = ['', '', '', ''];
    $active = null;
    foreach ($lines as $line) {
        if (preg_match('/^([1-4])\)\s*(.*)$/u', trim((string) $line), $matches) === 1) {
            $active = (int) $matches[1] - 1;
            $items[$active] = trim((string) $matches[2]);
        } elseif ($active !== null) {
            $items[$active] = trim($items[$active] . ' ' . trim((string) $line));
        }
    }
    return array_map('dent_exams_text_source_restore_digits', $items);
}

function dent_exams_gerontology_term6_final_sample_course(): array
{
    $course = dent_exams_text_source_build_course([
        'courseSlug' => 'gerontology-term-6-final-sample',
        'title' => 'آزمون نمونه سوالات پایانترم سالمندشناسی',
        'shortTitle' => 'نمونه سوالات پایانترم سالمندشناسی',
        'termLabel' => 'ترم ۶',
        'dataDir' => __DIR__ . '/data/gerontology_term6_final_sample',
        'paymentAmount' => 300000,
        'examDefinitions' => [[
            'slug' => 'final-sample',
            'label' => 'نمونه سوالات پایانترم',
            'topic' => 'نمونه سوالات پایان‌ترم سالمندشناسی',
            'patterns' => ['final_sample.txt'],
            'parser' => 'dent_exams_gerontology_term6_final_sample_parse_file',
        ]],
    ]);
    if ($course === []) {
        return [];
    }
    $course['addedAt'] = '2026-08-07T02:16:00+03:30';
    $course['cardDescription'] = 'نمونه سوالات پایانترم سالمندشناسی با پاسخ تشریحی، منبع و بررسی تک‌تک گزینه‌ها.';
    $course['heroTitle'] = 'آزمون نمونه سوالات پایانترم سالمندشناسی';
    $course['paymentTitle'] = 'دسترسی به آزمون نمونه سوالات پایانترم سالمندشناسی';
    $course['paymentDescription'] = 'با یک بار پرداخت ۳۰ هزار تومان، نمونه سوالات پایانترم سالمندشناسی برای همین حساب فعال می‌شود.';
    foreach ($course['exams'] as &$exam) { $exam['addedAt'] = $course['addedAt']; }
    unset($exam);
    return $course;
}
