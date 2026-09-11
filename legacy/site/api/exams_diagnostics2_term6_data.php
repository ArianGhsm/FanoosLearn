<?php
declare(strict_types=1);

require_once __DIR__ . '/text_source_parser.php';

function dent_exams_diagnostics2_term6_sources(): array
{
    return [
        [
            'slug' => '1',
            'label' => 'جلسه ۱',
            'topic' => 'مبانی پاتولوژی دهان، رنگ‌آمیزی و طبقه‌بندی ضایعات',
            'patterns' => ['session1_*.txt'],
            'questionCount' => 40,
        ],
        [
            'slug' => '2',
            'label' => 'جلسه ۲',
            'topic' => 'کیست‌های ادنتوژنیک تکاملی و کیست دنتی‌ژروس',
            'patterns' => ['session2_*.txt'],
            'questionCount' => 40,
        ],
        [
            'slug' => '3',
            'label' => 'جلسه ۳',
            'topic' => 'کراتوسیست ادنتوژنیک و تشخیص افتراقی کیست‌ها',
            'patterns' => ['session3_*.txt'],
            'questionCount' => 40,
        ],
        [
            'slug' => '4',
            'label' => 'جلسه ۴',
            'topic' => 'کیست‌های پریودنتال، ژنژیوال، بوتریوئید و گلندولار',
            'patterns' => ['session4_*.txt'],
            'questionCount' => 40,
        ],
        [
            'slug' => '5',
            'label' => 'جلسه ۵',
            'topic' => 'کیست ادنتوژنیک کلسیفیه و ضایعات مرتبط',
            'patterns' => ['session5_*.txt'],
            'questionCount' => 40,
        ],
        [
            'slug' => '6',
            'label' => 'جلسه ۶',
            'topic' => 'آملوبلاستوما؛ انواع بالینی، رادیوگرافیک و میکروسکوپی',
            'patterns' => ['session6_*.txt'],
            'questionCount' => 50,
        ],
        [
            'slug' => '7',
            'label' => 'جلسه ۷',
            'topic' => 'آملوبلاستومای یونیکستیک، آملوبلاستیک کارسینوما و ضایعات clear cell',
            'patterns' => ['session7_*.txt'],
            'questionCount' => 50,
        ],
        [
            'slug' => '8',
            'label' => 'جلسه ۸',
            'topic' => 'AOT، CEOT، SOT و تومورهای ادنتوژنیک اپی‌تلیالی',
            'patterns' => ['session8_*.txt'],
            'questionCount' => 40,
        ],
        [
            'slug' => '9',
            'label' => 'جلسه ۹',
            'topic' => 'ادونتوما، آملوبلاستیک فیبروما و تومورهای ادنتوژنیک مختلط',
            'patterns' => ['session9_*.txt'],
            'questionCount' => 40,
        ],
        [
            'slug' => '10',
            'label' => 'جلسه ۱۰',
            'topic' => 'ادنتوژنیک میکسوما، فیبروم ادنتوژنیک و سمنتوبلاستوما',
            'patterns' => ['session10_*.txt'],
            'questionCount' => 40,
        ],
        [
            'slug' => '11',
            'label' => 'جلسه ۱۱',
            'topic' => 'آفت عودکننده، لیکن پلان و واکنش‌های دارویی/آلرژیک دهان',
            'patterns' => ['session11_*.txt'],
            'questionCount' => 40,
        ],
        [
            'slug' => '12',
            'label' => 'جلسه ۱۲',
            'topic' => 'سارکوئیدوز، حساسیت تماسی و بیماری‌های التهابی مخاط و لب',
            'patterns' => ['session12_*.txt'],
            'questionCount' => 40,
        ],
        [
            'slug' => '13',
            'label' => 'جلسه ۱۳',
            'topic' => 'مخاط دهان، ضایعات سفید ارثی و معیارهای لیکن پلان',
            'patterns' => ['session13_*.txt'],
            'questionCount' => 40,
        ],
        [
            'slug' => '14',
            'label' => 'جلسه ۱۴',
            'topic' => 'بیماری‌های تاولی؛ پمفیگوس، پمفیگوئید و نمونه‌برداری',
            'patterns' => ['session14_*.txt'],
            'questionCount' => 40,
        ],
        [
            'slug' => '15',
            'label' => 'جلسه ۱۵',
            'topic' => 'اریتم مولتی‌فرم، لوپوس، اپیدرمولیز بولوزا و ضایعات تاولی',
            'patterns' => ['session15_*.txt'],
            'questionCount' => 40,
        ],
        [
            'slug' => '16',
            'label' => 'جلسه ۱۶',
            'topic' => 'اسکلرودرمی، دیسپلازی اکتودرمال و جمع‌بندی ضایعات مخاطی',
            'patterns' => ['session16_*.txt'],
            'questionCount' => 40,
        ],
    ];
}

function dent_exams_diagnostics2_term6_course(bool $hydrateQuestions = false): array
{
    static $catalogCourse = null;
    static $fullCourse = null;

    if ($hydrateQuestions && is_array($fullCourse)) {
        return $fullCourse;
    }
    if (!$hydrateQuestions && is_array($catalogCourse)) {
        return $catalogCourse;
    }

    $courseSlug = 'diagnostics-2-term6';
    $courseTitle = 'آزمون های تمرینی پایان ترم تشخیصی ۲';
    $coursePath = '/exams/' . $courseSlug . '/';
    $dataDir = __DIR__ . '/data/diagnostics2_term6';
    $addedAt = '2026-07-18T20:49:39+03:30';
    $exams = [];
    $totalQuestions = 0;

    foreach (dent_exams_diagnostics2_term6_sources() as $source) {
        if (!is_array($source)) {
            continue;
        }

        $slug = trim((string) ($source['slug'] ?? ''));
        $label = trim((string) ($source['label'] ?? ''));
        $topic = trim((string) ($source['topic'] ?? ''));
        $expectedQuestionCount = max(0, (int) ($source['questionCount'] ?? 0));
        $patterns = is_array($source['patterns'] ?? null) ? $source['patterns'] : [];
        if ($slug === '' || $label === '' || $expectedQuestionCount <= 0 || $patterns === []) {
            continue;
        }

        $questions = [];
        $questionCount = $expectedQuestionCount;
        if ($hydrateQuestions) {
            $path = dent_exams_text_source_find_source_file($dataDir, $patterns);
            $parsed = $path !== ''
                ? dent_exams_diagnostics2_term6_parse_file($path)
                : ['questions' => []];
            $questions = is_array($parsed['questions'] ?? null) ? $parsed['questions'] : [];
            $questionCount = count($questions);
        }

        $questionCountFa = dent_exams_text_source_to_persian_digits((string) $questionCount);
        $totalQuestions += $questionCount;

        $exam = [
            'slug' => $slug,
            'path' => $coursePath . $slug . '/',
            'label' => $label,
            'title' => 'آزمون ' . $label . ' - ' . $topic,
            'subtitle' => $questionCountFa . ' سوال چهارگزینه‌ای با پاسخ تشریحی و محل پاسخ در منبع.',
            'description' => 'مرور ' . $questionCountFa . ' سوال از مبحث «' . $topic . '» در تشخیصی ۲.',
            'eyebrow' => $courseTitle . ' | ' . $label . ' | ' . $topic,
            'ctaLabel' => 'انتخاب حالت و شروع',
            'backHref' => $coursePath,
            'backLabel' => 'بازگشت به فهرست آزمون‌های ' . $courseTitle,
            'autoAdvance' => true,
            'siteTitle' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
            'siteSubtitle' => 'آزمون‌ها',
            'siteBadge' => 'پایان ترم تشخیصی ۲',
            'footerText' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
            'attemptable' => true,
            'comingSoon' => false,
            'emptyStateTitle' => 'سوال‌های این جلسه هنوز در دسترس نیست',
            'emptyStateMessage' => 'اگر این پیام را می‌بینید، لطفاً صفحه را چند دقیقه بعد دوباره باز کنید.',
            'countsTowardStats' => true,
            'questionCount' => $questionCount,
            'addedAt' => $addedAt,
        ];

        if ($hydrateQuestions) {
            foreach ($questions as $index => $question) {
                if (is_array($question) && empty($question['topic'])) {
                    $questions[$index]['topic'] = $topic;
                }
            }
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
        'shortTitle' => 'تمرینی پایان ترم تشخیصی ۲',
        'badge' => $examCountFa . ' آزمون',
        'cardDescription' => $examCountFa . ' آزمون پایان‌ترم تشخیصی ۲ با مجموع ' . $questionCountFa . ' سوال و پاسخ تشریحی در این بخش قرار گرفت.',
        'heroTitle' => $courseTitle,
        'heroDescription' => 'این مجموعه برای درس تشخیصی ۲ ترم ۶ آماده شده و شامل ' . $examCountFa
            . ' آزمون فعال با مجموع ' . $questionCountFa
            . ' سوال است. پاسخنامه هر سؤال به‌صورت جداگانه شامل پاسخ درست، دلیل درست‌بودن، بررسی گزینه‌ها و محل پاسخ در منبع نمایش داده می‌شود. با یک بار پرداخت ۳۰ هزار تومان، کل این مجموعه برای همین حساب فعال می‌شود.',
        'path' => $coursePath,
        'paymentTitle' => 'دسترسی به ' . $courseTitle,
        'paymentDescription' => 'با یک بار پرداخت ۳۰ هزار تومان، همه آزمون‌های پایان ترم تشخیصی ۲ برای همین حساب فعال می‌شود.',
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

function dent_exams_diagnostics2_term6_runtime_exam_payload(
    string $catalogKey,
    string $courseSlug,
    string $examSlug
): ?array {
    if ($catalogKey !== 'shared' || $courseSlug !== 'diagnostics-2-term6') {
        return null;
    }

    $examSlug = trim($examSlug);
    if ($examSlug === '') {
        return null;
    }

    $course = dent_exams_diagnostics2_term6_course(true);
    foreach ((is_array($course['exams'] ?? null) ? $course['exams'] : []) as $exam) {
        if (is_array($exam) && (string) ($exam['slug'] ?? '') === $examSlug) {
            return $exam;
        }
    }

    return null;
}

function dent_exams_diagnostics2_term6_parse_file(string $path): array
{
    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return [
            'topic' => '',
            'questions' => [],
        ];
    }

    $text = dent_exams_text_source_normalize_text($raw);
    $parts = preg_split('/^\s*پاسخنامه\s+تشریحی\s*$/miu', $text, 2);
    $questionText = trim((string) ($parts[0] ?? $text));
    $answerText = trim((string) ($parts[1] ?? ''));
    $questionMap = dent_exams_text_source_collect_questions($questionText);
    $answerMap = dent_exams_diagnostics2_term6_collect_answers($answerText);
    $questions = [];

    foreach ($questionMap as $questionData) {
        if (!is_array($questionData)) {
            continue;
        }

        $number = max(0, (int) ($questionData['number'] ?? 0));
        $answerData = is_array($answerMap[$number] ?? null) ? $answerMap[$number] : null;
        $options = is_array($questionData['options'] ?? null) ? array_values($questionData['options']) : [];
        if ($number <= 0 || $answerData === null || count($options) !== 4) {
            continue;
        }

        $questions[] = [
            'number' => $number,
            'question' => (string) ($questionData['question'] ?? ''),
            'options' => $options,
            'correctIndex' => max(0, min(3, (int) ($answerData['correctIndex'] ?? 0))),
            'explanation' => (string) ($answerData['explanation'] ?? ''),
            'answerSections' => is_array($answerData['answerSections'] ?? null) ? $answerData['answerSections'] : [],
            'optionRationales' => is_array($answerData['optionRationales'] ?? null) ? $answerData['optionRationales'] : [],
            'answerMeta' => is_array($answerData['answerMeta'] ?? null) ? $answerData['answerMeta'] : [],
            'answerMetaSource' => is_array($answerData['answerMetaSource'] ?? null) ? $answerData['answerMetaSource'] : [],
        ];
    }

    return [
        'topic' => '',
        'questions' => $questions,
    ];
}

function dent_exams_diagnostics2_term6_collect_answers(string $text): array
{
    if ($text === '') {
        return [];
    }

    $parts = preg_split('/^\s*س(?:ؤ|و)ال\s*(\d+)\s*$/mu', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
    $answers = [];

    for ($index = 1; $index + 1 < count($parts); $index += 2) {
        $number = max(0, (int) ($parts[$index] ?? 0));
        $chunk = trim((string) ($parts[$index + 1] ?? ''));
        if ($number <= 0 || $chunk === '') {
            continue;
        }

        $answer = dent_exams_diagnostics2_term6_parse_answer_chunk($chunk);
        if ($answer !== null) {
            $answers[$number] = $answer;
        }
    }

    return $answers;
}

function dent_exams_diagnostics2_term6_parse_answer_chunk(string $chunk): ?array
{
    $lines = preg_split('/\R/u', $chunk) ?: [];
    $correctRaw = '';
    $reasonLines = [];
    $analysisLines = [];
    $referenceLines = [];
    $section = '';

    for ($index = 0; $index < count($lines); $index++) {
        $line = trim((string) $lines[$index]);
        if ($line === '') {
            continue;
        }

        if (preg_match('/^پاسخ\s+درست\s*:\s*(.*)$/u', $line, $matches) === 1) {
            $tail = trim((string) ($matches[1] ?? ''));
            if ($tail === '') {
                for ($next = $index + 1; $next < count($lines); $next++) {
                    $candidate = trim((string) $lines[$next]);
                    if ($candidate !== '') {
                        $tail = $candidate;
                        $index = $next;
                        break;
                    }
                }
            }
            $correctRaw = $tail;
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

        if ($section === 'reason') {
            $reasonLines[] = $line;
        } elseif ($section === 'analysis') {
            $analysisLines[] = $line;
        } elseif ($section === 'reference') {
            $referenceLines[] = $line;
        }
    }

    $letter = dent_exams_diagnostics2_term6_extract_answer_letter($correctRaw);
    if ($letter === '') {
        return null;
    }

    $correctIndex = dent_exams_text_source_option_letter_to_index($letter);
    $reference = dent_exams_diagnostics2_term6_join_lines($referenceLines);
    $answerSections = dent_exams_diagnostics2_term6_build_answer_sections(
        $reasonLines,
        $analysisLines
    );
    $explanation = dent_exams_diagnostics2_term6_compose_explanation(
        $correctRaw,
        $reasonLines,
        $analysisLines,
        $referenceLines
    );
    $answerMeta = [
        [
            'label' => 'پاسخ درست',
            'value' => dent_exams_text_source_restore_digits(trim($correctRaw)),
            'tone' => 'success',
        ],
    ];

    if ($reference !== '') {
        $answerMeta[] = [
            'label' => 'محل پاسخ در منبع',
            'value' => $reference,
            'tone' => 'info',
            'wide' => true,
        ];
    }

    return [
        'correctIndex' => $correctIndex,
        'explanation' => $explanation,
        'answerSections' => $answerSections,
        'optionRationales' => dent_exams_diagnostics2_term6_option_rationales($analysisLines),
        'answerMeta' => $answerMeta,
        'answerMetaSource' => [
            'correctRaw' => dent_exams_text_source_restore_digits(trim($correctRaw)),
            'correctIndex' => $correctIndex,
            'referenceRaw' => $reference,
        ],
    ];
}

function dent_exams_diagnostics2_term6_extract_answer_letter(string $value): string
{
    $value = trim($value);
    if (preg_match('/گزینه\s*(الف|ب|ج|د|a|b|c|d)/iu', $value, $matches) === 1) {
        return (string) ($matches[1] ?? '');
    }
    if (preg_match('/^(الف|ب|ج|د|a|b|c|d)$/iu', $value, $matches) === 1) {
        return (string) ($matches[1] ?? '');
    }

    return '';
}

function dent_exams_diagnostics2_term6_build_answer_sections(array $reasonLines, array $analysisLines): array
{
    $sections = [];
    $reason = dent_exams_diagnostics2_term6_join_lines($reasonLines);
    if ($reason !== '') {
        $sections[] = [
            'label' => 'دلیل درست‌بودن',
            'value' => $reason,
            'tone' => 'success',
        ];
    }

    $analysis = dent_exams_diagnostics2_term6_join_lines($analysisLines);
    if ($analysis !== '') {
        $sections[] = [
            'label' => 'بررسی گزینه‌ها',
            'value' => $analysis,
        ];
    }

    return $sections;
}

function dent_exams_diagnostics2_term6_compose_explanation(
    string $correctRaw,
    array $reasonLines,
    array $analysisLines,
    array $referenceLines
): string {
    $sections = [];
    if (trim($correctRaw) !== '') {
        $sections[] = '**پاسخ درست:** ' . dent_exams_text_source_restore_digits(trim($correctRaw));
    }

    $reason = dent_exams_diagnostics2_term6_join_lines($reasonLines);
    if ($reason !== '') {
        $sections[] = "**دلیل درست‌بودن:**\n" . $reason;
    }

    $analysis = dent_exams_diagnostics2_term6_join_lines($analysisLines);
    if ($analysis !== '') {
        $sections[] = "**بررسی گزینه‌ها:**\n" . $analysis;
    }

    $reference = dent_exams_diagnostics2_term6_join_lines($referenceLines);
    if ($reference !== '') {
        $sections[] = "**محل پاسخ در منبع:**\n" . $reference;
    }

    return trim(implode("\n\n", $sections));
}

function dent_exams_diagnostics2_term6_option_rationales(array $analysisLines): array
{
    $rationales = ['', '', '', ''];
    $currentIndex = null;

    foreach ($analysisLines as $line) {
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }

        if (preg_match('/^(الف|ب|ج|د)\)\s*(.+)$/u', $line, $matches) === 1) {
            $currentIndex = dent_exams_text_source_option_letter_to_index((string) ($matches[1] ?? ''));
            $rationales[$currentIndex] = dent_exams_text_source_restore_digits(trim((string) ($matches[2] ?? '')));
            continue;
        }

        if ($currentIndex !== null && $currentIndex >= 0 && $currentIndex <= 3) {
            $rationales[$currentIndex] = trim($rationales[$currentIndex] . ' ' . dent_exams_text_source_restore_digits($line));
        }
    }

    return $rationales;
}

function dent_exams_diagnostics2_term6_join_lines(array $lines): string
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
