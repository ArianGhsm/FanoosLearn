<?php
declare(strict_types=1);

require_once __DIR__ . '/text_source_parser.php';

function dent_exams_equipment_ergonomics_sample_parse_file(string $path): array
{
    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return ['topic' => '', 'questions' => []];
    }

    $text = dent_exams_text_source_normalize_text($raw);
    $parts = preg_split('/^\s*س(?:ؤ|و)ال\s*(\d+)\s*$/mu', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
    $questions = [];
    for ($index = 1; $index + 1 < count($parts); $index += 2) {
        $question = dent_exams_equipment_ergonomics_sample_parse_chunk(
            max(0, (int) ($parts[$index] ?? 0)),
            trim((string) ($parts[$index + 1] ?? ''))
        );
        if (is_array($question)) {
            $questions[] = $question;
        }
    }

    return ['topic' => '', 'questions' => $questions];
}

function dent_exams_equipment_ergonomics_sample_parse_chunk(int $number, string $chunk): ?array
{
    if ($number <= 0 || $chunk === '') {
        return null;
    }

    $lines = preg_split('/\R/u', $chunk) ?: [];
    $questionLines = [];
    $options = ['', '', '', ''];
    $sections = ['source' => [], 'reason' => [], 'analysis' => [], 'note' => []];
    $meta = [];
    $activeSection = '';
    $readingQuestion = true;
    $correctIndex = null;
    $correctRaw = '';

    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '' || preg_match('/^[\x{2500}-\x{257f}\-=*_ ]{5,}$/u', $line) === 1) {
            continue;
        }
        if (preg_match('/^(?:جمع‌بندی|خلاصه اختلاف|پایان پاسخنامه|فهرست منابع|یادداشت نهایی)/u', $line) === 1) {
            break;
        }

        if ($readingQuestion && preg_match('/^(?:گزینه\s*)?([1-4])\s*[\):：]\s*(.+)$/u', $line, $matches) === 1) {
            $options[(int) $matches[1] - 1] = dent_exams_text_source_restore_digits(trim((string) $matches[2]));
            continue;
        }

        if (preg_match('/^پاسخ[^:：]*[:：]\s*(.*)$/u', $line, $matches) === 1
            && preg_match('/گزینه\s*([1-4])/u', (string) ($matches[1] ?? ''), $answerMatch) === 1) {
            $correctIndex = (int) $answerMatch[1] - 1;
            $correctRaw = dent_exams_text_source_restore_digits(trim((string) ($matches[1] ?? '')));
            $readingQuestion = false;
            $activeSection = '';
            continue;
        }

        if (preg_match('/^(گزینه\s+علامت[‌-]?خورده[^:：]*|وضعیت\s+(?:تطابق|اتکا)|محل\s+در\s+منبع)\s*[:：]\s*(.*)$/u', $line, $matches) === 1) {
            $label = dent_exams_text_source_restore_digits(trim((string) $matches[1]));
            $value = dent_exams_text_source_restore_digits(trim((string) ($matches[2] ?? '')));
            if ($correctIndex === null && str_starts_with($label, 'گزینه علامت')
                && preg_match('/گزینه\s*([1-4])/u', dent_exams_text_source_normalize_digits($value), $markedMatch) === 1) {
                $correctIndex = (int) $markedMatch[1] - 1;
                $correctRaw = 'کلید علامت‌خورده فایل: ' . $value;
            }
            if ($value !== '') {
                $meta[] = ['label' => $label, 'value' => $value, 'wide' => true];
            }
            $readingQuestion = false;
            $activeSection = '';
            continue;
        }

        if (preg_match('/^(منبع(?:\s+جلسه)?)\s*[:：]\s*(.*)$/u', $line, $matches) === 1) {
            $activeSection = 'source';
            $readingQuestion = false;
            $tail = trim((string) ($matches[2] ?? ''));
            if ($tail !== '') { $sections[$activeSection][] = $tail; }
            continue;
        }
        if (preg_match('/^پاسخ\s+تشریحی\s*[:：]\s*(.*)$/u', $line, $matches) === 1) {
            $activeSection = 'reason';
            $readingQuestion = false;
            $tail = trim((string) ($matches[1] ?? ''));
            if ($tail !== '') { $sections[$activeSection][] = $tail; }
            continue;
        }
        if (preg_match('/^(?:تحلیل|بررسی)\s+گزینه[‌-]?ها[^:：]*[:：]\s*(.*)$/u', $line, $matches) === 1) {
            $activeSection = 'analysis';
            $readingQuestion = false;
            $tail = trim((string) ($matches[1] ?? ''));
            if ($tail !== '') { $sections[$activeSection][] = $tail; }
            continue;
        }
        if (preg_match('/^نکته\s+آموزشی\s*[:：]\s*(.*)$/u', $line, $matches) === 1) {
            $activeSection = 'note';
            $readingQuestion = false;
            $tail = trim((string) ($matches[1] ?? ''));
            if ($tail !== '') { $sections[$activeSection][] = $tail; }
            continue;
        }
        if (preg_match('/^نتیجه\s+(?:امتحانی|آموزشی)\s*[:：]\s*(.*)$/u', $line, $matches) === 1) {
            $activeSection = 'note';
            $readingQuestion = false;
            $tail = trim((string) ($matches[1] ?? ''));
            if ($tail !== '') { $sections[$activeSection][] = $tail; }
            continue;
        }

        if ($activeSection !== '') {
            $sections[$activeSection][] = $line;
        } elseif ($readingQuestion) {
            $questionLines[] = $line;
        }
    }

    if ($correctIndex === null || count(array_filter($options, static fn(string $item): bool => $item !== '')) !== 4) {
        return null;
    }

    $questionText = dent_exams_text_source_inline_text(implode(' ', $questionLines));
    if ($questionText === '') {
        return null;
    }

    $source = dent_exams_equipment_ergonomics_sample_join($sections['source']);
    $reason = dent_exams_equipment_ergonomics_sample_join($sections['reason']);
    $analysis = dent_exams_equipment_ergonomics_sample_join($sections['analysis']);
    $note = dent_exams_equipment_ergonomics_sample_join($sections['note']);
    $answerSections = [];
    foreach ([
        ['پاسخ تشریحی', $reason, 'success'],
        ['بررسی گزینه‌ها', $analysis, ''],
        ['نکته آموزشی', $note, 'info'],
    ] as [$label, $value, $tone]) {
        if ($value !== '') {
            $answerSections[] = ['label' => $label, 'value' => $value, 'tone' => $tone];
        }
    }

    $answerMeta = [['label' => 'پاسخ صحیح', 'value' => $correctRaw, 'tone' => 'success']];
    if ($source !== '') {
        $answerMeta[] = ['label' => 'منبع', 'value' => $source, 'tone' => 'info', 'wide' => true];
    }
    $answerMeta = array_merge($answerMeta, $meta);
    $explanation = ['**پاسخ صحیح:** ' . $correctRaw];
    if ($reason !== '') { $explanation[] = "**پاسخ تشریحی:**\n" . $reason; }
    if ($analysis !== '') { $explanation[] = "**بررسی گزینه‌ها:**\n" . $analysis; }
    if ($note !== '') { $explanation[] = "**نکته آموزشی:**\n" . $note; }
    if ($source !== '') { $explanation[] = "**منبع:**\n" . $source; }

    return [
        'number' => $number,
        'question' => $questionText,
        'options' => $options,
        'correctIndex' => $correctIndex,
        'explanation' => implode("\n\n", $explanation),
        'answerCardLabel' => 'پاسخ تشریحی، منبع و تحلیل گزینه‌ها',
        'answerSections' => $answerSections,
        'answerMeta' => $answerMeta,
        'optionRationales' => dent_exams_equipment_ergonomics_sample_option_rationales($sections['analysis']),
    ];
}

function dent_exams_equipment_ergonomics_sample_join(array $lines): string
{
    return dent_exams_text_source_restore_digits(trim(implode("\n", array_filter(array_map('trim', $lines)))));
}

function dent_exams_equipment_ergonomics_sample_option_rationales(array $lines): array
{
    $items = ['', '', '', ''];
    $active = null;
    foreach ($lines as $line) {
        $line = trim((string) $line);
        if (preg_match('/^(?:-\s*)?(?:گزینه\s*)?([1-4])\s*(?:[ـ—:\)\.\-]|$)\s*(.*)$/u', $line, $matches) === 1) {
            $active = (int) $matches[1] - 1;
            $items[$active] = trim((string) ($matches[2] ?? ''));
        } elseif ($active !== null) {
            $items[$active] = trim($items[$active] . ' ' . $line);
        }
    }
    return array_map('dent_exams_text_source_restore_digits', $items);
}

function dent_exams_equipment_ergonomics_term6_final_sample_course(): array
{
    $course = dent_exams_text_source_build_course([
        'courseSlug' => 'equipment-ergonomics-term6-final-sample',
        'title' => 'آزمون نمونه سوالات تجهیزات و ارگونومی',
        'shortTitle' => 'نمونه سوالات تجهیزات و ارگونومی',
        'termLabel' => 'ترم ۶',
        'dataDir' => __DIR__ . '/data/equipment_ergonomics_term6_final_sample',
        'paymentAmount' => 300000,
        'examDefinitions' => [
            [
                'slug' => 'khordad-1400',
                'label' => 'پایان‌ترم خرداد ۱۴۰۰',
                'topic' => 'تجهیزات دندانپزشکی و ارگونومی ـ خرداد ۱۴۰۰',
                'patterns' => ['khordad_1400.txt'],
                'parser' => 'dent_exams_equipment_ergonomics_sample_parse_file',
            ],
            [
                'slug' => 'final-sample-29',
                'label' => 'نمونه سوالات پایان‌ترم',
                'topic' => 'تجهیزات دندانپزشکی و ارگونومی',
                'patterns' => ['final_sample_29.txt'],
                'parser' => 'dent_exams_equipment_ergonomics_sample_parse_file',
            ],
        ],
    ]);
    if ($course === []) {
        return [];
    }

    $course['addedAt'] = '2026-08-10T23:58:00+03:30';
    $course['cardDescription'] = 'دو آزمون نمونه سوال پایان‌ترم تجهیزات و ارگونومی با پاسخ تشریحی، منبع و تحلیل تک‌تک گزینه‌ها.';
    $course['heroTitle'] = 'آزمون نمونه سوالات تجهیزات و ارگونومی';
    $course['paymentTitle'] = 'دسترسی به نمونه سوالات تجهیزات و ارگونومی';
    $course['paymentDescription'] = 'با یک بار پرداخت ۳۰ هزار تومان، هر دو آزمون نمونه سوال تجهیزات و ارگونومی برای همین حساب فعال می‌شوند.';
    foreach ($course['exams'] as &$exam) {
        $exam['addedAt'] = $course['addedAt'];
    }
    unset($exam);

    return $course;
}
