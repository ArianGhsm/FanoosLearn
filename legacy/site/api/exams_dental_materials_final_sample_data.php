<?php
declare(strict_types=1);

require_once __DIR__ . '/text_source_parser.php';

function dent_exams_dental_materials_final_sample_blocks(string $path): array
{
    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $text = dent_exams_text_source_normalize_text($raw);
    $parts = preg_split('/^\s*س(?:ؤ|و)ال\s*(\d+)\s*$/mu', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
    $blocks = [];
    for ($index = 1; $index + 1 < count($parts); $index += 2) {
        $number = max(0, (int) ($parts[$index] ?? 0));
        $chunk = trim((string) ($parts[$index + 1] ?? ''));
        if ($number > 0 && $chunk !== '') {
            $blocks[] = [$number, $chunk];
        }
    }
    return $blocks;
}

function dent_exams_dental_materials_final_sample_join(array $lines): string
{
    return dent_exams_text_source_restore_digits(trim(implode("\n", array_filter(array_map('trim', $lines)))));
}

function dent_exams_dental_materials_final_sample_mcq_parse_file(string $path): array
{
    $questions = [];
    foreach (dent_exams_dental_materials_final_sample_blocks($path) as [$number, $chunk]) {
        $question = dent_exams_dental_materials_final_sample_mcq_parse_block($number, $chunk);
        if (is_array($question)) {
            $questions[] = $question;
        }
    }
    return ['topic' => 'نمونه سوالات پایان‌ترم مبانی مواد دندانی', 'questions' => $questions];
}

function dent_exams_dental_materials_final_sample_mcq_parse_block(int $number, string $chunk): ?array
{
    $lines = preg_split('/\R/u', $chunk) ?: [];
    $section = '';
    $questionLines = [];
    $options = ['الف' => '', 'ب' => '', 'ج' => '', 'د' => ''];
    $correctRaw = '';
    $correctIndex = null;
    $sections = [
        'marked' => [], 'comparison' => [], 'coverage' => [], 'reference' => [],
        'reason' => [], 'analysis' => [], 'review' => [], 'note' => [],
    ];

    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '' || preg_match('/^[\x{2500}-\x{257f}\-=*_ ]{5,}$/u', $line) === 1) {
            continue;
        }
        if (preg_match('/^(?:جمع‌بندی کنترل نهایی|پایان پاسخنامه)/u', $line) === 1) {
            break;
        }
        if (preg_match('/^متن\s+س(?:ؤ|و)ال\s*[:：]\s*(.*)$/u', $line, $matches) === 1) {
            $section = 'question';
            if (trim((string) ($matches[1] ?? '')) !== '') { $questionLines[] = trim((string) $matches[1]); }
            continue;
        }
        if (preg_match('/^گزینه[‌-]?ها\s*[:：]\s*$/u', $line) === 1) {
            $section = 'options';
            continue;
        }
        if (preg_match('/^پاسخ\s+مستقل\s*[:：]\s*(.*)$/u', $line, $matches) === 1) {
            $section = 'answer';
            $tail = trim((string) ($matches[1] ?? ''));
            if ($tail !== '') { $correctRaw = $tail; }
            continue;
        }
        $headingMap = [
            '/^گزینه\s+علامت[‌-]?خورده[^:：]*[:：]\s*(.*)$/u' => 'marked',
            '/^مقایسه\s+پاسخ\s*[:：]\s*(.*)$/u' => 'comparison',
            '/^وضعیت\s+پوشش[^:：]*[:：]\s*(.*)$/u' => 'coverage',
            '/^رفرنس\s+جزوه\/پاور\s*[:：]\s*(.*)$/u' => 'reference',
            '/^پاسخ\s+تشریحی\s*[:：]\s*(.*)$/u' => 'reason',
            '/^علت\s+درست\s+یا\s+غلط\s+بودن\s+گزینه[‌-]?ها\s*[:：]\s*(.*)$/u' => 'analysis',
            '/^مرور\s+آموزشی\s+سریع\s*[:：]\s*(.*)$/u' => 'review',
            '/^نکته\/ابهام\s+مهم\s*[:：]\s*(.*)$/u' => 'note',
        ];
        $matchedHeading = false;
        foreach ($headingMap as $pattern => $target) {
            if (preg_match($pattern, $line, $matches) === 1) {
                $section = $target;
                $tail = trim((string) ($matches[1] ?? ''));
                if ($tail !== '') { $sections[$target][] = $tail; }
                $matchedHeading = true;
                break;
            }
        }
        if ($matchedHeading) {
            continue;
        }

        if ($section === 'options' && preg_match('/^(الف|ب|ج|د)\)\s*(.+)$/u', $line, $matches) === 1) {
            $options[(string) $matches[1]] = trim((string) $matches[2]);
            continue;
        }
        if ($section === 'answer') {
            $correctRaw = trim($correctRaw . ' ' . $line);
            if (preg_match('/گزینه\s*(الف|ب|ج|د)\)/u', $correctRaw, $matches) === 1) {
                $correctIndex = dent_exams_text_source_option_letter_to_index((string) $matches[1]);
            }
            continue;
        }
        if ($section === 'question') {
            $questionLines[] = $line;
        } elseif (isset($sections[$section])) {
            $sections[$section][] = $line;
        }
    }

    if ($correctIndex === null && preg_match('/گزینه\s*(الف|ب|ج|د)\)/u', $correctRaw, $matches) === 1) {
        $correctIndex = dent_exams_text_source_option_letter_to_index((string) $matches[1]);
    }
    $questionText = dent_exams_text_source_inline_text(implode(' ', $questionLines));
    $optionValues = array_values(array_map('dent_exams_text_source_restore_digits', $options));
    if ($questionText === '' || $correctIndex === null || count(array_filter($optionValues)) !== 4) {
        return null;
    }

    $reason = dent_exams_dental_materials_final_sample_join($sections['reason']);
    $analysis = dent_exams_dental_materials_final_sample_join($sections['analysis']);
    $review = dent_exams_dental_materials_final_sample_join($sections['review']);
    $note = dent_exams_dental_materials_final_sample_join($sections['note']);
    $reference = dent_exams_dental_materials_final_sample_join($sections['reference']);
    $answerSections = [];
    foreach ([
        ['پاسخ تشریحی', $reason, 'success'],
        ['تحلیل گزینه‌ها', $analysis, ''],
        ['مرور آموزشی سریع', $review, 'info'],
        ['نکته یا ابهام مهم', $note, 'warning'],
    ] as [$label, $value, $tone]) {
        if ($value !== '') { $answerSections[] = ['label' => $label, 'value' => $value, 'tone' => $tone]; }
    }
    $answerMeta = [[
        'label' => 'پاسخ مستقل',
        'value' => dent_exams_text_source_restore_digits($correctRaw),
        'tone' => 'success',
    ]];
    foreach ([
        ['گزینه علامت‌خورده فایل', 'marked'],
        ['مقایسه پاسخ', 'comparison'],
        ['پوشش در جزوات', 'coverage'],
        ['رفرنس', 'reference'],
    ] as [$label, $key]) {
        $value = dent_exams_dental_materials_final_sample_join($sections[$key]);
        if ($value !== '') { $answerMeta[] = ['label' => $label, 'value' => $value, 'wide' => true]; }
    }

    $explanation = ['**پاسخ مستقل:** ' . dent_exams_text_source_restore_digits($correctRaw)];
    if ($reason !== '') { $explanation[] = "**پاسخ تشریحی:**\n" . $reason; }
    if ($analysis !== '') { $explanation[] = "**تحلیل گزینه‌ها:**\n" . $analysis; }
    if ($review !== '') { $explanation[] = "**مرور آموزشی سریع:**\n" . $review; }
    if ($reference !== '') { $explanation[] = "**رفرنس:**\n" . $reference; }

    return [
        'number' => $number,
        'question' => $questionText,
        'options' => $optionValues,
        'correctIndex' => $correctIndex,
        'topic' => 'مبانی مواد دندانی ـ جلسات ۸ تا ۱۶',
        'explanation' => implode("\n\n", $explanation),
        'answerCardLabel' => 'پاسخ تشریحی، منبع و تحلیل گزینه‌ها',
        'answerSections' => $answerSections,
        'answerMeta' => $answerMeta,
        'optionRationales' => dent_exams_dental_materials_final_sample_option_rationales($sections['analysis']),
    ];
}

function dent_exams_dental_materials_final_sample_option_rationales(array $lines): array
{
    $items = ['', '', '', ''];
    $active = null;
    foreach ($lines as $line) {
        $line = trim((string) $line);
        if (preg_match('/^-?\s*(الف|ب|ج|د)\)\s*(.*)$/u', $line, $matches) === 1) {
            $active = dent_exams_text_source_option_letter_to_index((string) $matches[1]);
            $items[$active] = trim((string) ($matches[2] ?? ''));
        } elseif ($active !== null) {
            $items[$active] = trim($items[$active] . ' ' . $line);
        }
    }
    return array_map('dent_exams_text_source_restore_digits', $items);
}

function dent_exams_dental_materials_final_sample_essay_parse_file(string $path): array
{
    $questions = [];
    foreach (dent_exams_dental_materials_final_sample_blocks($path) as [$number, $chunk]) {
        $question = dent_exams_dental_materials_final_sample_essay_parse_block($number, $chunk);
        if (is_array($question)) {
            $questions[] = $question;
        }
    }
    return ['topic' => 'نمونه سوالات تشریحی مبانی مواد دندانی', 'questions' => $questions];
}

function dent_exams_dental_materials_final_sample_essay_parse_block(int $number, string $chunk): ?array
{
    $lines = preg_split('/\R/u', $chunk) ?: [];
    $questionLines = [];
    $referenceLines = [];
    $answerLines = [];
    $section = '';

    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '' || preg_match('/^[\x{2500}-\x{257f}\-=*_ ]{5,}$/u', $line) === 1) {
            if ($section === 'answer' && $answerLines !== [] && end($answerLines) !== '') { $answerLines[] = ''; }
            continue;
        }
        if (preg_match('/^(?:جمع‌بندی نهایی منابع|پایان پاسخنامه)/u', $line) === 1) { break; }
        if (preg_match('/^متن\s+س(?:ؤ|و)ال\s*[:：]\s*(.*)$/u', $line, $matches) === 1) {
            $section = 'question';
            if (trim((string) ($matches[1] ?? '')) !== '') { $questionLines[] = trim((string) $matches[1]); }
            continue;
        }
        if (preg_match('/^رفرنس(?:\s+(?:پروژه|اصلی|برای\s+رفع\s+ابهام))?[‌-]?ها?\s*[:：]\s*(.*)$/u', $line, $matches) === 1) {
            $section = 'reference';
            $tail = trim((string) ($matches[1] ?? ''));
            if ($tail !== '') { $referenceLines[] = $tail; }
            continue;
        }
        if (preg_match('/^(پاسخ\s+تشریحی|پاسخ\s+صحیح|پاسخ\s+اصلی|پاسخ\s+تکمیلی\s+استاندارد|پاسخ|توضیح\s+تشریحی)\s*[:：]\s*(.*)$/u', $line, $matches) === 1) {
            $section = 'answer';
            $label = trim((string) $matches[1]);
            $answerLines[] = '**' . $label . ':**';
            $tail = trim((string) ($matches[2] ?? ''));
            if ($tail !== '') { $answerLines[] = $tail; }
            continue;
        }
        if (preg_match('/^(مرور\s+آموزشی\s+سریع|نکته(?:\s+بسیار)?\s+مهم|نکته\s+تکمیلی|نکته|تله\s+امتحانی|مفهوم|نتیجه\s+کلی)\s*[:：]\s*(.*)$/u', $line, $matches) === 1) {
            $section = 'answer';
            $answerLines[] = '**' . trim((string) $matches[1]) . ':**';
            $tail = trim((string) ($matches[2] ?? ''));
            if ($tail !== '') { $answerLines[] = $tail; }
            continue;
        }
        if (preg_match('/^(گزینه\s+علامت|وضعیت\s+در\s+فایل)/u', $line) === 1) {
            $section = 'metadata';
            continue;
        }

        if ($section === 'question') { $questionLines[] = $line; }
        elseif ($section === 'reference') { $referenceLines[] = $line; }
        elseif ($section === 'answer') { $answerLines[] = $line; }
    }

    $questionText = dent_exams_text_source_inline_text(implode(' ', $questionLines));
    $answerDetail = dent_exams_dental_materials_final_sample_join($answerLines);
    if ($questionText === '' || $answerDetail === '') {
        return null;
    }

    return [
        'number' => $number,
        'question' => $questionText,
        'options' => [],
        'correctIndex' => null,
        'topic' => 'مبانی مواد دندانی ـ جلسات ۸ تا ۱۶',
        'answerSummary' => 'پاسخ تشریحی و نکات آموزشی را پس از مرور سؤال مشاهده کنید.',
        'answerDetail' => $answerDetail,
        'reference' => dent_exams_dental_materials_final_sample_join($referenceLines),
    ];
}

function dent_exams_dental_materials_final_sample_course(): array
{
    $course = dent_exams_text_source_build_course([
        'courseSlug' => 'dental-materials-foundations-final-sample',
        'title' => 'آزمون نمونه سوالات پایان‌ترم مبانی مواد دندانی',
        'shortTitle' => 'نمونه سوالات پایان‌ترم مواد دندانی',
        'termLabel' => 'ترم ۶',
        'dataDir' => __DIR__ . '/data/dental_materials_final_sample',
        'paymentAmount' => 300000,
        'examDefinitions' => [
            [
                'slug' => 'final-mcq-72',
                'label' => 'نمونه سوالات تستی پایان‌ترم',
                'topic' => 'مبانی مواد دندانی ـ جلسات ۸ تا ۱۶',
                'patterns' => ['final_mcq_72.txt'],
                'parser' => 'dent_exams_dental_materials_final_sample_mcq_parse_file',
            ],
            [
                'slug' => 'essay-ordibehesht-1400',
                'label' => 'نمونه سوالات تشریحی اردیبهشت ۱۴۰۰',
                'topic' => 'مبانی مواد دندانی ـ جلسات ۸ تا ۱۶',
                'patterns' => ['essay_ordibehesht_1400.txt'],
                'kind' => 'essay',
                'parser' => 'dent_exams_dental_materials_final_sample_essay_parse_file',
            ],
        ],
    ]);
    if ($course === []) { return []; }

    $course['addedAt'] = '2026-08-20T21:30:00+03:30';
    $course['cardDescription'] = 'دو آزمون نمونه سوال پایان‌ترم مبانی مواد دندانی شامل ۷۲ سؤال تستی و ۱۶ سؤال تشریحی با پاسخ کامل.';
    $course['heroTitle'] = 'آزمون نمونه سوالات پایان‌ترم مبانی مواد دندانی';
    $course['paymentTitle'] = 'دسترسی به نمونه سوالات پایان‌ترم مبانی مواد دندانی';
    $course['paymentDescription'] = 'با یک بار پرداخت ۳۰ هزار تومان، هر دو آزمون تستی و تشریحی برای همین حساب فعال می‌شوند.';
    foreach ($course['exams'] as &$exam) { $exam['addedAt'] = $course['addedAt']; }
    unset($exam);
    return $course;
}
