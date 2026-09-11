<?php
declare(strict_types=1);

require_once __DIR__ . '/text_source_parser.php';

function dent_exams_medical_emergencies_final_sample_parse_file(string $path): array
{
    $topic = basename($path) === 'khordad_1400.txt'
        ? 'فوریت پزشکی ـ خرداد ۱۴۰۰'
        : 'نمونه سوالات پایان‌ترم فوریت پزشکی';
    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return ['topic' => $topic, 'questions' => []];
    }

    $text = dent_exams_text_source_normalize_text($raw);
    $parts = preg_split('/^\s*س(?:ؤ|و)ال\s*(\d+)\s*$/mu', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
    $questions = [];
    for ($index = 1; $index + 1 < count($parts); $index += 2) {
        $number = max(0, (int) ($parts[$index] ?? 0));
        $question = dent_exams_medical_emergencies_final_sample_parse_block(
            $number,
            trim((string) ($parts[$index + 1] ?? '')),
            basename($path)
        );
        if (is_array($question)) {
            $questions[] = $question;
        }
    }

    return ['topic' => $topic, 'questions' => $questions];
}

function dent_exams_medical_emergencies_comprehensive_parse_file(string $path): array
{
    $topic = 'نمونه سوالات جامع فوریت پزشکی';
    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return ['topic' => $topic, 'questions' => []];
    }

    $text = dent_exams_text_source_normalize_text($raw);
    $parts = preg_split(
        '/^\s*س(?:ؤ|و)ال\/رکورد\s*(\d+)\s+از\s+\d+\s*$/mu',
        $text,
        -1,
        PREG_SPLIT_DELIM_CAPTURE
    ) ?: [];
    $questions = [];
    for ($index = 1; $index + 1 < count($parts); $index += 2) {
        $number = max(0, (int) ($parts[$index] ?? 0));
        $question = dent_exams_medical_emergencies_final_sample_parse_block(
            $number,
            trim((string) ($parts[$index + 1] ?? '')),
            basename($path)
        );
        if (is_array($question)) {
            $questions[] = $question;
        }
    }

    return ['topic' => $topic, 'questions' => $questions];
}

function dent_exams_medical_emergencies_final_sample_parse_block(int $number, string $chunk, string $sourceName): ?array
{
    if ($number <= 0 || $chunk === '') {
        return null;
    }

    $lines = preg_split('/\R/u', $chunk) ?: [];
    $questionLines = [];
    $options = ['', '', '', ''];
    $sections = [
        'answer' => [],
        'marked' => [],
        'status' => [],
        'prediction' => [],
        'source' => [],
        'reason' => [],
        'analysis' => [],
        'review' => [],
        'note' => [],
    ];
    $activeSection = '';
    $readingQuestion = true;

    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '' || preg_match('/^[\x{2500}-\x{257f}\-=*_ ]{5,}$/u', $line) === 1) {
            continue;
        }
        if (preg_match('/^(?:جمع‌بندی|اختلاف پاسخ|منابع تکمیلی|پایان فایل|پایان پاسخنامه)/u', $line) === 1) {
            break;
        }
        if (preg_match('/^\[تصویر\s*:/u', $line) === 1) {
            continue;
        }
        if ($readingQuestion && preg_match('/^س(?:ؤ|و)ال(?:\s*\d+)?\s*(?:\)|[:：])\s*(.*)$/u', $line, $matches) === 1) {
            $tail = trim((string) ($matches[1] ?? ''));
            if ($tail !== '') {
                $questionLines[] = $tail;
            }
            continue;
        }

        if ($readingQuestion && preg_match('/^([1-4])\s*[\)\.\-:]\s*(.+)$/u', $line, $matches) === 1) {
            $options[(int) $matches[1] - 1] = dent_exams_text_source_restore_digits(trim((string) $matches[2]));
            continue;
        }

        $headingPatterns = [
            '/^پاسخ\s+مستقل[^:：]*[:：]\s*(.*)$/u' => 'answer',
            '/^پاسخ\s+صحیح\s+مستقل[^:：]*[:：]\s*(.*)$/u' => 'answer',
            '/^گزینه\s+علامت[^:：]*[:：]\s*(.*)$/u' => 'marked',
            '/^(?:وضعیت|نتیجه)\s+تطابق[^:：]*[:：]\s*(.*)$/u' => 'status',
            '/^پاسخنامه\s+پیش‌بینی\s+از\s+روی\s+کلیدهای\s+سال‌های\s+اخیر\s*[:：]\s*(.*)$/u' => 'prediction',
            '/^منابع?\s+(?:پاسخ|طراحی\s+س(?:ؤ|و)ال|مرتبط|آموزشی\s+اصلی)\s*[:：]\s*(.*)$/u' => 'source',
            '/^(?:تذکر\s+منبع|رفرنس(?:[‌-]?های)?\s+تکمیلی|محل\s+مرتبط\s+در\s+منبع)\s*[:：]\s*(.*)$/u' => 'source',
            '/^پاسخ\s+تشریحی\s*[:：]\s*(.*)$/u' => 'reason',
            '/^بررسی\s+(?:تک[‌-]?تک\s+)?گزینه[‌-]?ها\s*[:：]\s*(.*)$/u' => 'analysis',
            '/^مرور\s+آموزشی\s+سریع(?:\s+این\s+مبحث)?\s*[:：]\s*(.*)$/u' => 'review',
            '/^بخش\s+آموزشی\s*[ـ—-]\s*مرور\s+سریع\s+نکات\s+س(?:ؤ|و)ال\s*[:：]\s*(.*)$/u' => 'review',
            '/^(?:نکته\s+(?:آزمونی|بالینی\s+مهم|مهم(?:\s+علمی|\s+درباره\s+اصطلاحات)?)|توضیح\s+تکمیلی)\s*[:：]\s*(.*)$/u' => 'note',
        ];
        $matchedHeading = false;
        foreach ($headingPatterns as $pattern => $target) {
            if (preg_match($pattern, $line, $matches) === 1) {
                $readingQuestion = false;
                $activeSection = $target;
                $tailIndex = $target === 'note' ? 1 : 1;
                $tail = trim((string) ($matches[$tailIndex] ?? ''));
                if ($tail !== '') {
                    $sections[$target][] = $tail;
                }
                $matchedHeading = true;
                break;
            }
        }
        if ($matchedHeading) {
            continue;
        }
        if (preg_match('/^پاسخ\s+و\s+تحلیل\s+مستقل\s*$/u', $line) === 1) {
            $readingQuestion = false;
            $activeSection = '';
            continue;
        }

        if ($readingQuestion) {
            $questionLines[] = $line;
        } elseif ($activeSection !== '') {
            $sections[$activeSection][] = $line;
        }
    }

    if (count(array_filter($options, static fn(string $item): bool => $item !== '')) !== 4) {
        return null;
    }
    $questionText = dent_exams_text_source_inline_text(implode(' ', $questionLines));
    if ($questionText === '') {
        return null;
    }

    $answerRaw = dent_exams_medical_emergencies_final_sample_join($sections['answer']);
    $correctIndex = dent_exams_medical_emergencies_final_sample_correct_index($answerRaw, $number, $sourceName);
    if ($correctIndex === null) {
        return null;
    }

    $marked = dent_exams_medical_emergencies_final_sample_join($sections['marked']);
    $status = dent_exams_medical_emergencies_final_sample_join($sections['status']);
    $prediction = dent_exams_medical_emergencies_final_sample_join($sections['prediction']);
    $source = dent_exams_medical_emergencies_final_sample_join($sections['source']);
    $reason = dent_exams_medical_emergencies_final_sample_join($sections['reason']);
    $analysis = dent_exams_medical_emergencies_final_sample_join($sections['analysis']);
    $review = dent_exams_medical_emergencies_final_sample_join($sections['review']);
    $note = dent_exams_medical_emergencies_final_sample_join($sections['note']);
    $hasAmbiguity = preg_match('/(?:تک[‌-]?پاسخی.{0,100}نیست|مبهم|ابهام|گزینه[‌-]?گذاری\s+کاملاً\s+تمیز\s+نیست|درمان[^.]*ناقص)/u', $answerRaw . ' ' . $note) === 1;

    $answerSections = [];
    foreach ([
        ['پاسخ تشریحی', $reason, 'success'],
        ['پاسخنامه پیش‌بینی از کلیدهای سال‌های اخیر', $prediction, 'info'],
        ['بررسی گزینه‌ها', $analysis, ''],
        ['مرور آموزشی سریع', $review, 'info'],
        ['نکته مهم', $note, 'warning'],
    ] as [$label, $value, $tone]) {
        if ($value !== '') {
            $answerSections[] = ['label' => $label, 'value' => $value, 'tone' => $tone];
        }
    }

    $answerMeta = [[
        'label' => $hasAmbiguity ? 'کلید آموزشی با توضیح ابهام' : 'پاسخ مستقل',
        'value' => $answerRaw,
        'tone' => $hasAmbiguity ? 'warning' : 'success',
        'wide' => true,
    ]];
    foreach ([
        ['گزینه علامت‌خورده فایل', $marked, ''],
        ['وضعیت تطابق', $status, ''],
        ['منبع', $source, 'info'],
    ] as [$label, $value, $tone]) {
        if ($value !== '') {
            $answerMeta[] = ['label' => $label, 'value' => $value, 'tone' => $tone, 'wide' => true];
        }
    }

    $explanation = ['**پاسخ مستقل:** ' . $answerRaw];
    if ($reason !== '') { $explanation[] = "**پاسخ تشریحی:**\n" . $reason; }
    if ($prediction !== '') { $explanation[] = "**پاسخنامه پیش‌بینی از کلیدهای سال‌های اخیر:**\n" . $prediction; }
    if ($analysis !== '') { $explanation[] = "**بررسی گزینه‌ها:**\n" . $analysis; }
    if ($review !== '') { $explanation[] = "**مرور آموزشی سریع:**\n" . $review; }
    if ($note !== '') { $explanation[] = "**نکته مهم:**\n" . $note; }
    if ($source !== '') { $explanation[] = "**منبع:**\n" . $source; }

    return [
        'number' => $number,
        'question' => $questionText,
        'options' => $options,
        'correctIndex' => $correctIndex,
        'topic' => 'فوریت‌های پزشکی در دندانپزشکی',
        'explanation' => implode("\n\n", $explanation),
        'answerCardLabel' => 'پاسخ تشریحی، کلید سال‌های اخیر، منبع و تحلیل گزینه‌ها',
        'answerSections' => $answerSections,
        'answerMeta' => $answerMeta,
        'optionRationales' => dent_exams_medical_emergencies_final_sample_option_rationales($sections['analysis']),
    ];
}

function dent_exams_medical_emergencies_final_sample_correct_index(string $answer, int $number, string $sourceName): ?int
{
    // Two questions in the June 1400 scan are explicitly ambiguous. The source
    // itself states the intended educational key, which is preserved here while
    // the full caveat remains visible in the answer card.
    if ($sourceName === 'khordad_1400.txt' && $number === 2) {
        return 2;
    }
    if ($sourceName === 'khordad_1400.txt' && $number === 5) {
        return 3;
    }
    if (preg_match('/گزینه\s*([1-4])/u', dent_exams_text_source_normalize_digits($answer), $matches) === 1) {
        return (int) $matches[1] - 1;
    }
    return null;
}

function dent_exams_medical_emergencies_final_sample_join(array $lines): string
{
    $clean = [];
    $seen = [];
    foreach (array_filter(array_map('trim', $lines)) as $line) {
        if (isset($seen[$line])) {
            continue;
        }
        $seen[$line] = true;
        $clean[] = $line;
    }
    return dent_exams_text_source_restore_digits(trim(implode("\n", $clean)));
}

function dent_exams_medical_emergencies_final_sample_option_rationales(array $lines): array
{
    $items = ['', '', '', ''];
    $active = null;
    foreach ($lines as $line) {
        $line = trim((string) $line);
        if (preg_match('/^(?:[•-]\s*)?(?:گزینه\s*)?([1-4])\s*(?:[ـ—:\)\.\-]|$)\s*(.*)$/u', $line, $matches) === 1) {
            $active = (int) $matches[1] - 1;
            $items[$active] = trim((string) ($matches[2] ?? ''));
        } elseif ($active !== null) {
            $items[$active] = trim($items[$active] . ' ' . $line);
        }
    }
    return array_map('dent_exams_text_source_restore_digits', $items);
}

function dent_exams_medical_emergencies_term6_final_sample_course(): array
{
    $course = dent_exams_text_source_build_course([
        'courseSlug' => 'medical-emergencies-term6-final-sample',
        'title' => 'آزمون نمونه سوالات پایان‌ترم فوریت پزشکی',
        'shortTitle' => 'نمونه سوالات فوریت پزشکی',
        'termLabel' => 'ترم ۶',
        'dataDir' => __DIR__ . '/data/medical_emergencies_term6_final_sample',
        'paymentAmount' => 450000,
        'examDefinitions' => [
            [
                'slug' => 'final-sample-36',
                'label' => 'نمونه سوالات پایان‌ترم فوریت پزشکی',
                'topic' => 'فوریت‌های پزشکی در دندانپزشکی',
                'patterns' => ['final_sample_36.txt'],
                'parser' => 'dent_exams_medical_emergencies_final_sample_parse_file',
            ],
            [
                'slug' => 'khordad-1400',
                'label' => 'پایان‌ترم خرداد ۱۴۰۰',
                'topic' => 'فوریت‌های پزشکی در دندانپزشکی ـ خرداد ۱۴۰۰',
                'patterns' => ['khordad_1400.txt'],
                'parser' => 'dent_exams_medical_emergencies_final_sample_parse_file',
            ],
            [
                'slug' => 'comprehensive-33',
                'label' => 'نمونه سوالات جامع فوریت پزشکی',
                'topic' => 'احیا، راه هوایی، آنافیلاکسی، ACS و تروما',
                'patterns' => ['comprehensive_33.txt'],
                'parser' => 'dent_exams_medical_emergencies_comprehensive_parse_file',
            ],
        ],
    ]);
    if ($course === []) {
        return [];
    }

    $course['addedAt'] = '2026-08-23T22:01:11+03:30';
    $course['cardDescription'] = 'سه آزمون نمونه سوال فوریت پزشکی شامل ۸۵ سؤال چهارگزینه‌ای با پاسخ تشریحی، کلید سال‌های اخیر، منبع و تحلیل گزینه‌ها.';
    $course['heroTitle'] = 'آزمون نمونه سوالات پایان‌ترم فوریت پزشکی';
    $course['heroDescription'] = 'این مجموعه شامل سه آزمون فوریت پزشکی با مجموع ۸۵ سؤال چهارگزینه‌ای است. پاسخنامه‌ها با بخش پیش‌بینی از کلیدهای سال‌های اخیر، منبع و تحلیل گزینه‌ها تکمیل شده‌اند. با یک بار پرداخت ۴۵ هزار تومان، هر سه آزمون برای همین حساب فعال می‌شوند.';
    $course['paymentTitle'] = 'دسترسی به نمونه سوالات پایان‌ترم فوریت پزشکی';
    $course['paymentDescription'] = 'با یک بار پرداخت ۴۵ هزار تومان، هر سه آزمون نمونه سوال فوریت پزشکی برای همین حساب فعال می‌شوند.';
    foreach ($course['exams'] as &$exam) {
        $exam['addedAt'] = (string) ($exam['slug'] ?? '') === 'comprehensive-33'
            ? $course['addedAt']
            : '2026-08-23T00:55:59+03:30';
    }
    unset($exam);

    return $course;
}
