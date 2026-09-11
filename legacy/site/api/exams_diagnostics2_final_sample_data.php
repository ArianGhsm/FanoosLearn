<?php
declare(strict_types=1);

require_once __DIR__ . '/answer_sheet_parser.php';

function dent_exams_diagnostics2_final_sample_sources(): array
{
    $dataDir = __DIR__ . '/data/diagnostics2_final_sample';

    return [
        [
            'slug' => 'tir-1400',
            'title' => 'نمونه سوالات پایان ترم تشخیصی ۲ - تیر ۱۴۰۰',
            'label' => 'تیر ۱۴۰۰',
            'topic' => 'نمونه سوالات پایان ترم تشخیصی ۲',
            'file' => $dataDir . '/tir_1400.txt',
            'questionCount' => 48,
            'kind' => 'mcq',
            'parser' => 'dent_exams_diagnostics2_final_sample_parse_file',
        ],
        [
            'slug' => 'answer-sheet-prediction-48',
            'title' => 'پیش‌بینی سوالات از پاسخنامه Diagnostics 2',
            'label' => 'پیش‌بینی پاسخنامه ۴۸سؤالی',
            'topic' => 'بازسازی احتمالی سوالات از روی پاسخنامه',
            'file' => $dataDir . '/answer_sheet_prediction_48.txt',
            'questionCount' => 48,
            'kind' => 'answer-first',
            'parser' => 'dent_exams_diagnostics2_final_sample_parse_prediction_file',
            'answerRevealLabel' => 'نمایش سوالات احتمالی و پاسخ‌ها',
            'answerCardLabel' => 'سوالات احتمالی و پاسخ‌ها',
        ],
        [
            'slug' => 'answer-sheet-prediction-51',
            'title' => 'پیش‌بینی سوالات تشخیصی ۲ از روی پاسخنامه',
            'label' => 'پیش‌بینی پاسخنامه ۵۱سؤالی',
            'topic' => 'بازسازی احتمالی سوالات از روی پاسخنامه',
            'file' => $dataDir . '/answer_sheet_prediction_51.txt',
            'questionCount' => 51,
            'kind' => 'answer-first',
            'parser' => 'dent_exams_diagnostics2_final_sample_parse_prediction_file',
            'answerRevealLabel' => 'نمایش سوالات احتمالی و پاسخ‌ها',
            'answerCardLabel' => 'سوالات احتمالی و پاسخ‌ها',
        ],
        [
            'slug' => 'makeup-prediction-35',
            'title' => 'پیش‌بینی سوالات آزمون جبرانی تشخیصی ۲',
            'label' => 'پیش‌بینی آزمون جبرانی ۳۵سؤالی',
            'topic' => 'بازسازی احتمالی سوالات آزمون جبرانی از روی پاسخنامه',
            'file' => $dataDir . '/makeup_prediction_35.txt',
            'questionCount' => 35,
            'kind' => 'answer-first',
            'parser' => 'dent_exams_diagnostics2_final_sample_parse_prediction_file',
            'answerRevealLabel' => 'نمایش سوالات احتمالی و پاسخ‌ها',
            'answerCardLabel' => 'سوالات احتمالی و پاسخ‌ها',
        ],
        [
            'slug' => 'shakib-frequent-bank',
            'title' => 'بانک سوالات پرتکرار استاد شکیب',
            'label' => 'بانک پرتکرار استاد شکیب',
            'topic' => 'خانواده سوالات پرتکرار استاد شکیب',
            'file' => $dataDir . '/shakib_frequent_bank.txt',
            'questionCount' => 18,
            'kind' => 'mixed',
            'parser' => 'dent_exams_diagnostics2_final_sample_parse_shakib_file',
            'learningOnly' => true,
            'answerRevealLabel' => 'نمایش تحلیل و ردپای تکرار',
            'answerCardLabel' => 'تحلیل، منبع و ردپای تکرار',
        ],
    ];
}

function dent_exams_diagnostics2_final_sample_course(bool $hydrateQuestions = false): array
{
    static $catalogCourse = null;
    static $fullCourse = null;

    if ($hydrateQuestions && is_array($fullCourse)) {
        return $fullCourse;
    }
    if (!$hydrateQuestions && is_array($catalogCourse)) {
        return $catalogCourse;
    }

    $courseSlug = 'diagnostics-2-final-sample';
    $courseTitle = 'نمونه سوالات پایان ترم تشخیصی ۲';
    $coursePath = '/exams/' . $courseSlug . '/';
    $addedAt = '2026-07-19T14:24:47+03:30';
    $exams = [];
    $totalQuestions = 0;

    foreach (dent_exams_diagnostics2_final_sample_sources() as $source) {
        $slug = trim((string) ($source['slug'] ?? ''));
        $title = trim((string) ($source['title'] ?? ''));
        $label = trim((string) ($source['label'] ?? $title));
        $topic = trim((string) ($source['topic'] ?? $courseTitle));
        $kind = trim((string) ($source['kind'] ?? 'mcq'));
        $parser = is_callable($source['parser'] ?? null)
            ? (string) $source['parser']
            : 'dent_exams_diagnostics2_final_sample_parse_file';
        $expectedQuestionCount = max(0, (int) ($source['questionCount'] ?? 0));
        if ($slug === '' || $title === '' || $expectedQuestionCount <= 0) {
            continue;
        }

        $questions = [];
        $questionCount = $expectedQuestionCount;
        if ($hydrateQuestions) {
            $parsed = $parser((string) ($source['file'] ?? ''));
            $questions = is_array($parsed['questions'] ?? null) ? $parsed['questions'] : [];
            $questionCount = count($questions);
            if ($questionCount <= 0) {
                continue;
            }
        }

        $questionCountFa = dent_exams_text_source_to_persian_digits((string) $questionCount);
        $totalQuestions += $questionCount;
        $isAnswerFirst = $kind === 'answer-first';
        $isMixed = $kind === 'mixed';
        $subtitle = $questionCountFa . ' سوال چهارگزینه‌ای با پاسخ علامت‌خورده، پاسخ تحلیلی، وضعیت مقایسه و رفرنس جزوه.';
        $description = 'مرور ' . $questionCountFa . ' سوال از «' . $title . '» با پاسخ تشریحی و بررسی گزینه‌ها.';
        $ctaLabel = 'انتخاب حالت و شروع';
        if ($isAnswerFirst) {
            $subtitle = $questionCountFa . ' کارت پاسخ‌محور؛ در کادر سؤال، پاسخ ثبت‌شده آمده و در کادر پاسخ، صورت‌های احتمالی، منبع و میزان اطمینان نمایش داده می‌شود.';
            $description = 'مرور ' . $questionCountFa . ' پاسخ از «' . $title . '» همراه با بازسازی سوالات احتمالی و پاسخ همان سوالات.';
            $ctaLabel = 'شروع مرور پاسخ‌محور';
        } elseif ($isMixed) {
            $subtitle = $questionCountFa . ' خانواده سؤال پرتکرار؛ موارد دارای نسخه دقیق با گزینه نمایش داده می‌شوند و موارد بدون گزینه به‌صورت مرور تشریحی می‌آیند.';
            $description = 'مرور ' . $questionCountFa . ' خانواده پرتکرار استاد شکیب با پاسخ کلیدی، ردپای تکرار، منبع و تحلیل جداگانه.';
            $ctaLabel = 'شروع مرور بانک پرتکرار';
        }

        $exam = [
            'slug' => $slug,
            'path' => $coursePath . $slug . '/',
            'label' => $label,
            'title' => $title,
            'subtitle' => $subtitle,
            'description' => $description,
            'eyebrow' => $courseTitle . ' | ' . $label,
            'ctaLabel' => $ctaLabel,
            'backHref' => $coursePath,
            'backLabel' => 'بازگشت به فهرست آزمون‌های ' . $courseTitle,
            'autoAdvance' => true,
            'siteTitle' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
            'siteSubtitle' => 'آزمون‌ها',
            'siteBadge' => 'پایان ترم تشخیصی ۲',
            'footerText' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
            'attemptable' => true,
            'comingSoon' => false,
            'countsTowardStats' => true,
            'questionCount' => $questionCount,
            'addedAt' => $addedAt,
            'learningOnly' => (bool) ($source['learningOnly'] ?? false),
            'answerRevealLabel' => trim((string) ($source['answerRevealLabel'] ?? '')),
            'answerCardLabel' => trim((string) ($source['answerCardLabel'] ?? '')),
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
        'shortTitle' => 'نمونه سوالات تشخیصی ۲',
        'badge' => $examCountFa . ' آزمون',
        'cardDescription' => 'فعلاً ' . $examCountFa . ' آزمون پایان‌ترم تشخیصی ۲ با مجموع ' . $questionCountFa . ' سوال، پاسخ یا خانواده سؤال در این مجموعه قرار گرفته است.',
        'heroTitle' => $courseTitle,
        'heroDescription' => 'این مجموعه برای نمونه سوالات پایان‌ترم تشخیصی ۲ آماده شده و فعلاً شامل ' . $examCountFa
            . ' آزمون فعال با مجموع ' . $questionCountFa
            . ' سوال، پاسخ یا خانواده سؤال است. آزمون تیر ۱۴۰۰ مثل روال معمول چهارگزینه‌ای است؛ آزمون‌های پیش‌بینی‌شده پاسخ‌محورند و پاسخ موجود در کادر سؤال می‌آید و صورت‌های احتمالی در کارت پاسخ نمایش داده می‌شود؛ بانک شکیب نیز با کارت‌های جدا برای پاسخ کلیدی، ردپای تکرار، منبع و تحلیل آموزشی آماده شده است. با یک بار پرداخت ۳۰ هزار تومان، کل این مجموعه برای همین حساب فعال می‌شود و آزمون‌های بعدی همین مجموعه نیز از همین مسیر اضافه می‌شوند.',
        'path' => $coursePath,
        'paymentTitle' => 'دسترسی به ' . $courseTitle,
        'paymentDescription' => 'با یک بار پرداخت ۳۰ هزار تومان، همه آزمون‌های نمونه سوالات پایان ترم تشخیصی ۲ برای همین حساب فعال می‌شود.',
        'paymentSuccessMessage' => 'پرداخت شما تایید شد و همه آزمون‌های ' . $courseTitle . ' برای این حساب باز شد.',
        'paymentFailureMessage' => 'فعال‌سازی ' . $courseTitle . ' انجام نشد. نتیجه را دوباره بررسی کنید.',
        'defaultPaymentMode' => 'paid',
        'defaultAmount' => 300000,
        'qualityGuard' => true,
        'exams' => $exams,
    ];

    if ($hydrateQuestions) {
        $fullCourse = $course;
        return $fullCourse;
    }

    $catalogCourse = $course;
    return $catalogCourse;
}

function dent_exams_diagnostics2_final_sample_runtime_exam_payload(
    string $catalogKey,
    string $courseSlug,
    string $examSlug
): ?array {
    if ($catalogKey !== 'shared' || $courseSlug !== 'diagnostics-2-final-sample') {
        return null;
    }

    $examSlug = trim($examSlug);
    if ($examSlug === '') {
        return null;
    }

    $course = dent_exams_diagnostics2_final_sample_course(true);
    foreach ((is_array($course['exams'] ?? null) ? $course['exams'] : []) as $exam) {
        if (is_array($exam) && (string) ($exam['slug'] ?? '') === $examSlug) {
            return $exam;
        }
    }

    return null;
}

function dent_exams_diagnostics2_final_sample_parse_file(string $path): array
{
    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return [
            'title' => '',
            'questions' => [],
        ];
    }

    $text = dent_exams_text_source_normalize_text($raw);
    $parts = preg_split('/^\s*س(?:ؤ|و)ال\s*(\d+)\s*$/mu', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
    $questions = [];

    for ($index = 1; $index + 1 < count($parts); $index += 2) {
        $number = max(0, (int) ($parts[$index] ?? 0));
        $chunk = trim((string) ($parts[$index + 1] ?? ''));
        if ($number <= 0 || $chunk === '') {
            continue;
        }

        $question = dent_exams_diagnostics2_final_sample_parse_chunk($number, $chunk);
        if ($question !== null) {
            $questions[] = $question;
        }
    }

    return [
        'title' => dent_exams_diagnostics2_final_sample_extract_title($text),
        'questions' => $questions,
    ];
}

function dent_exams_diagnostics2_final_sample_extract_title(string $text): string
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
        if (preg_match('/^(?:راهنمای استفاده|فهرست فایل‌های منبع)\b/u', $trimmed) === 1) {
            break;
        }

        $titleLines[] = dent_exams_text_source_restore_digits($trimmed);
        if (count($titleLines) >= 2) {
            break;
        }
    }

    return trim(implode(' - ', $titleLines));
}

function dent_exams_diagnostics2_final_sample_parse_chunk(int $number, string $chunk): ?array
{
    $lines = preg_split('/\R/u', $chunk) ?: [];
    $questionLines = [];
    $optionMap = [];
    $currentOptionKey = '';
    $markedRaw = '';
    $suggestedRaw = '';
    $matchStatus = '';
    $referenceRaw = '';
    $sections = [];
    $currentSection = '';
    $hasStartedAnswer = false;

    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '' || preg_match('/^-{5,}$/u', $trimmed) === 1) {
            continue;
        }

        if (!$hasStartedAnswer && preg_match('/^(الف|ب|ج|د|a|b|c|d)[\)\.\-:]\s*(.*)$/iu', $trimmed, $matches) === 1) {
            $currentOptionKey = dent_exams_diagnostics2_final_sample_normalize_letter((string) ($matches[1] ?? ''));
            if ($currentOptionKey !== '') {
                $optionMap[$currentOptionKey] = trim((string) ($matches[2] ?? ''));
                continue;
            }
        }

        if (preg_match('/^پاسخ\s+علامت[‌\-\s]*خورده\s+در\s+فایل\s*:\s*(.*)$/u', $trimmed, $matches) === 1) {
            $markedRaw = trim((string) ($matches[1] ?? ''));
            $hasStartedAnswer = true;
            $currentOptionKey = '';
            $currentSection = '';
            continue;
        }

        if (preg_match('/^پاسخ\s+تحلیلی\s*:\s*(.*)$/u', $trimmed, $matches) === 1) {
            $suggestedRaw = trim((string) ($matches[1] ?? ''));
            $hasStartedAnswer = true;
            $currentOptionKey = '';
            $currentSection = '';
            continue;
        }

        if (preg_match('/^وضعیت\s+مقایسه\s*:\s*(.*)$/u', $trimmed, $matches) === 1) {
            $matchStatus = trim((string) ($matches[1] ?? ''));
            $hasStartedAnswer = true;
            $currentOptionKey = '';
            $currentSection = '';
            continue;
        }

        if (preg_match('/^رفرنس\s*:\s*(.*)$/u', $trimmed, $matches) === 1) {
            $referenceRaw = trim((string) ($matches[1] ?? ''));
            $hasStartedAnswer = true;
            $currentOptionKey = '';
            $currentSection = '';
            continue;
        }

        $sectionLabel = dent_exams_diagnostics2_final_sample_section_label($trimmed);
        if ($sectionLabel !== '') {
            $hasStartedAnswer = true;
            $currentOptionKey = '';
            $currentSection = $sectionLabel['label'];
            $sections[$currentSection] = $sections[$currentSection] ?? [];
            if (trim((string) ($sectionLabel['tail'] ?? '')) !== '') {
                $sections[$currentSection][] = trim((string) ($sectionLabel['tail'] ?? ''));
            }
            continue;
        }

        if (!$hasStartedAnswer && $currentOptionKey !== '' && isset($optionMap[$currentOptionKey])) {
            $optionMap[$currentOptionKey] = trim($optionMap[$currentOptionKey] . ' ' . $trimmed);
            continue;
        }

        if ($currentSection !== '' && isset($sections[$currentSection])) {
            $sections[$currentSection][] = $trimmed;
            continue;
        }

        if (!$hasStartedAnswer) {
            $questionLines[] = $trimmed;
        }
    }

    $options = dent_exams_diagnostics2_final_sample_normalize_option_map($optionMap);
    $questionText = dent_exams_answer_sheet_compose_question_text($questionLines);
    if ($questionText === '' || count($options) !== 4) {
        return null;
    }

    $markedIndex = dent_exams_diagnostics2_final_sample_extract_answer_index($markedRaw);
    $suggestedIndex = dent_exams_diagnostics2_final_sample_extract_answer_index($suggestedRaw);
    if ($suggestedIndex === null) {
        $suggestedIndex = $markedIndex;
    }

    $analysisLines = $sections['بررسی تک‌تک گزینه‌ها'] ?? [];

    return [
        'number' => $number,
        'question' => $questionText,
        'options' => array_values($options),
        'correctIndex' => $suggestedIndex ?? 0,
        'explanation' => dent_exams_diagnostics2_final_sample_compose_explanation($sections),
        'answerSections' => dent_exams_diagnostics2_final_sample_build_answer_sections($sections),
        'optionRationales' => dent_exams_diagnostics2_final_sample_option_rationales($analysisLines),
        'answerMeta' => dent_exams_diagnostics2_final_sample_build_meta(
            $markedRaw,
            $markedIndex,
            $suggestedRaw,
            $suggestedIndex,
            $matchStatus,
            $referenceRaw
        ),
        'answerMetaSource' => [
            'markedRaw' => dent_exams_text_source_restore_digits(trim($markedRaw)),
            'markedIndex' => $markedIndex,
            'suggestedRaw' => dent_exams_text_source_restore_digits(trim($suggestedRaw)),
            'suggestedIndex' => $suggestedIndex,
            'matchStatus' => dent_exams_text_source_restore_digits(trim($matchStatus)),
            'referenceRaw' => dent_exams_text_source_restore_digits(trim($referenceRaw)),
        ],
    ];
}

function dent_exams_diagnostics2_final_sample_section_label(string $line): array|string
{
    $labels = [
        'پاسخ تشریحی و نکته آموزشی' => 'پاسخ تشریحی و نکته آموزشی',
        'بررسی تک‌تک گزینه‌ها' => 'بررسی تک‌تک گزینه‌ها',
        'بررسی تک تک گزینه‌ها' => 'بررسی تک‌تک گزینه‌ها',
        'بررسی تک‌تک گزینه ها' => 'بررسی تک‌تک گزینه‌ها',
        'بررسی تک تک گزینه ها' => 'بررسی تک‌تک گزینه‌ها',
    ];

    foreach ($labels as $raw => $normalized) {
        if (preg_match('/^' . preg_quote($raw, '/') . '\s*:\s*$/u', $line) === 1) {
            return [
                'label' => $normalized,
                'tail' => '',
            ];
        }
    }

    if (preg_match('/^(هشدار[^:]*|نکته[^:]*|مشکل[^:]*)\s*:\s*(.*)$/u', $line, $matches) === 1) {
        return [
            'label' => trim((string) ($matches[1] ?? '')),
            'tail' => trim((string) ($matches[2] ?? '')),
        ];
    }

    return '';
}

function dent_exams_diagnostics2_final_sample_normalize_letter(string $letter): string
{
    $clean = strtolower(trim($letter));
    return match ($clean) {
        'الف', 'a' => 'الف',
        'ب', 'b' => 'ب',
        'ج', 'c' => 'ج',
        'د', 'd' => 'د',
        default => '',
    };
}

function dent_exams_diagnostics2_final_sample_letter_to_index(string $letter): ?int
{
    return match (dent_exams_diagnostics2_final_sample_normalize_letter($letter)) {
        'الف' => 0,
        'ب' => 1,
        'ج' => 2,
        'د' => 3,
        default => null,
    };
}

function dent_exams_diagnostics2_final_sample_extract_answer_index(string $raw): ?int
{
    $clean = trim(dent_exams_text_source_normalize_digits($raw));
    if ($clean === '') {
        return null;
    }

    if (preg_match('/(?:^|[\s:،؛\(\)\[\]\-—])(?:گزینه\s*)?(الف|ب|ج|د|a|b|c|d)(?:$|[\s\)\]\-—،؛:\.])/iu', $clean, $matches) === 1) {
        return dent_exams_diagnostics2_final_sample_letter_to_index((string) ($matches[1] ?? ''));
    }

    if (preg_match('/(?:گزینه\s*)?([1-4])/u', $clean, $matches) === 1) {
        return max(0, (int) ($matches[1] ?? 1)) - 1;
    }

    return null;
}

function dent_exams_diagnostics2_final_sample_normalize_option_map(array $optionMap): array
{
    $options = [];
    foreach (['الف', 'ب', 'ج', 'د'] as $letter) {
        $value = trim((string) ($optionMap[$letter] ?? ''));
        if ($value === '') {
            return [];
        }
        $options[] = dent_exams_text_source_restore_digits($value);
    }

    return $options;
}

function dent_exams_diagnostics2_final_sample_build_meta(
    string $markedRaw,
    ?int $markedIndex,
    string $suggestedRaw,
    ?int $suggestedIndex,
    string $matchStatus,
    string $referenceRaw
): array {
    $meta = [];

    if (trim($markedRaw) !== '') {
        $meta[] = [
            'label' => 'پاسخ علامت‌خورده در فایل',
            'value' => dent_exams_text_source_restore_digits(trim($markedRaw)),
            'tone' => ($markedIndex !== null && $suggestedIndex !== null && $markedIndex !== $suggestedIndex) ? 'warning' : 'neutral',
            'wide' => true,
        ];
    }

    if (trim($suggestedRaw) !== '') {
        $meta[] = [
            'label' => 'پاسخ تحلیلی',
            'value' => dent_exams_text_source_restore_digits(trim($suggestedRaw)),
            'tone' => $suggestedIndex === null ? 'warning' : 'success',
            'wide' => true,
        ];
    }

    if (trim($matchStatus) !== '') {
        $meta[] = [
            'label' => 'وضعیت مقایسه',
            'value' => dent_exams_text_source_restore_digits(trim($matchStatus)),
            'tone' => dent_exams_diagnostics2_final_sample_tone($matchStatus),
            'wide' => true,
        ];
    }

    if (trim($referenceRaw) !== '') {
        $meta[] = [
            'label' => 'رفرنس',
            'value' => dent_exams_text_source_restore_digits(trim($referenceRaw)),
            'tone' => 'accent',
            'wide' => true,
        ];
    }

    return $meta;
}

function dent_exams_diagnostics2_final_sample_build_answer_sections(array $sections): array
{
    $payload = [];
    foreach ($sections as $label => $lines) {
        $label = trim((string) $label);
        $value = dent_exams_diagnostics2_final_sample_join_lines(is_array($lines) ? $lines : []);
        if ($label === '' || $value === '') {
            continue;
        }

        $payload[] = [
            'label' => $label,
            'value' => $value,
            'tone' => dent_exams_diagnostics2_final_sample_section_tone($label),
        ];
    }

    return $payload;
}

function dent_exams_diagnostics2_final_sample_compose_explanation(array $sections): string
{
    $parts = [];
    foreach (dent_exams_diagnostics2_final_sample_build_answer_sections($sections) as $section) {
        $parts[] = '**' . (string) ($section['label'] ?? 'توضیح') . ":**\n" . (string) ($section['value'] ?? '');
    }

    return trim(implode("\n\n", $parts));
}

function dent_exams_diagnostics2_final_sample_option_rationales(array $analysisLines): array
{
    $rationales = ['', '', '', ''];
    $currentIndex = null;

    foreach ($analysisLines as $line) {
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }

        if (preg_match('/^-?\s*گزینه\s+(الف|ب|ج|د|a|b|c|d)\s*:\s*(.+)$/iu', $line, $matches) === 1) {
            $currentIndex = dent_exams_diagnostics2_final_sample_letter_to_index((string) ($matches[1] ?? ''));
            if ($currentIndex !== null) {
                $rationales[$currentIndex] = dent_exams_text_source_restore_digits(trim((string) ($matches[2] ?? '')));
            }
            continue;
        }

        if ($currentIndex !== null && $currentIndex >= 0 && $currentIndex <= 3) {
            $rationales[$currentIndex] = trim($rationales[$currentIndex] . ' ' . dent_exams_text_source_restore_digits($line));
        }
    }

    return $rationales;
}

function dent_exams_diagnostics2_final_sample_join_lines(array $lines): string
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

function dent_exams_diagnostics2_final_sample_tone(string $value, string $default = 'neutral'): string
{
    if (preg_match('/تعارض|ابهام|نیازمند|اختلاف|ناهماهنگ|نادرست/u', $value) === 1) {
        return 'warning';
    }
    if (preg_match('/مطابق|منطبق|تایید|صحیح/u', $value) === 1) {
        return 'success';
    }

    return $default;
}

function dent_exams_diagnostics2_final_sample_section_tone(string $label): string
{
    if (preg_match('/هشدار|مشکل|ابهام|تعارض/u', $label) === 1) {
        return 'warning';
    }
    if (preg_match('/نکته|نتیجه/u', $label) === 1) {
        return 'info';
    }

    return '';
}

function dent_exams_diagnostics2_final_sample_parse_prediction_file(string $path): array
{
    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return [
            'title' => '',
            'questions' => [],
        ];
    }

    $text = dent_exams_text_source_normalize_text($raw);
    $parts = preg_split('/^\s*س(?:ؤ|و)ال\s*(\d+)\s*$/mu', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
    $questions = [];

    for ($index = 1; $index + 1 < count($parts); $index += 2) {
        $number = max(0, (int) ($parts[$index] ?? 0));
        $chunk = trim((string) ($parts[$index + 1] ?? ''));
        if ($number <= 0 || $chunk === '') {
            continue;
        }

        $question = dent_exams_diagnostics2_final_sample_parse_prediction_chunk($number, $chunk);
        if ($question !== null) {
            $questions[] = $question;
        }
    }

    return [
        'title' => dent_exams_diagnostics2_final_sample_extract_title($text),
        'questions' => $questions,
    ];
}

function dent_exams_diagnostics2_final_sample_parse_prediction_chunk(int $number, string $chunk): ?array
{
    $lines = preg_split('/\R/u', $chunk) ?: [];
    $answerLines = [];
    $sections = [];
    $currentSection = '';

    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '' || preg_match('/^[=\-.]{5,}$/u', $trimmed) === 1) {
            continue;
        }
        if (preg_match('/^(?:جمع‌بندی|نکته مهم درباره|پایان فایل)\b/u', $trimmed) === 1) {
            break;
        }

        $answerTail = dent_exams_diagnostics2_final_sample_prediction_answer_tail($trimmed);
        if ($answerTail !== null) {
            if ($answerTail !== '') {
                $answerLines[] = $answerTail;
            }
            $currentSection = '__answer';
            continue;
        }

        $section = dent_exams_diagnostics2_final_sample_prediction_section_from_line($trimmed);
        if ($section !== null) {
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

        if ($currentSection === '__answer') {
            $answerLines[] = $trimmed;
            continue;
        }

        if ($currentSection !== '') {
            $sections[$currentSection] = $sections[$currentSection] ?? [];
            $sections[$currentSection][] = $trimmed;
        }
    }

    $answer = dent_exams_diagnostics2_final_sample_join_lines($answerLines);
    if ($answer === '') {
        return null;
    }

    $answerSections = dent_exams_diagnostics2_final_sample_build_prediction_answer_sections($sections);

    return [
        'number' => $number,
        'question' => $answer,
        'options' => [],
        'correctIndex' => null,
        'answerRevealLabel' => 'نمایش سوالات احتمالی و پاسخ‌ها',
        'answerCardLabel' => 'سوالات احتمالی و پاسخ‌ها',
        'answerMeta' => [
            [
                'label' => 'پاسخ موجود در پاسخنامه',
                'value' => $answer,
                'tone' => 'accent',
                'wide' => true,
            ],
        ],
        'answerSections' => $answerSections,
        'explanation' => dent_exams_diagnostics2_final_sample_compose_explanation($sections),
    ];
}

function dent_exams_diagnostics2_final_sample_prediction_answer_tail(string $line): ?string
{
    if (preg_match('/^پاسخ\s+(?:ثبت‌شده|موجود)\s+در\s+(?:تصویر|پاسخ‌?نامه)\s*:\s*(.*)$/u', $line, $matches) === 1) {
        return dent_exams_text_source_restore_digits(trim((string) ($matches[1] ?? '')));
    }

    return null;
}

function dent_exams_diagnostics2_final_sample_prediction_section_from_line(string $line): ?array
{
    if (preg_match('/^([^:：]{2,120})\s*[:：]\s*(.*)$/u', $line, $matches) !== 1) {
        return null;
    }

    $rawLabel = trim((string) ($matches[1] ?? ''));
    $tail = trim((string) ($matches[2] ?? ''));
    $label = $rawLabel;
    $recognized = true;

    if (preg_match('/^صورت\s+س(?:ؤ|و)ال/u', $rawLabel) === 1) {
        $label = 'صورت سؤال‌های احتمالی';
    } elseif (preg_match('/^وضعیت\s+(?:در\s+16\s+جزوه|پوشش|منبع)/u', $rawLabel) === 1) {
        $label = 'وضعیت منبع';
    } elseif (preg_match('/^وضعیت\s+تطبیق/u', $rawLabel) === 1) {
        $label = 'وضعیت تطبیق با نمونه';
    } elseif (preg_match('/^(?:علت\s+انتخاب|منطق\s+پیش‌بینی)/u', $rawLabel) === 1) {
        $label = 'منطق پیش‌بینی';
    } elseif (preg_match('/^(?:سطح|میزان)\s+اطمینان/u', $rawLabel) === 1) {
        $label = 'میزان اطمینان';
    } elseif (preg_match('/^صورت\s+کامل\s+احتمالی\s+پاسخ/u', $rawLabel) === 1) {
        $label = 'صورت کامل احتمالی پاسخ';
    } elseif (in_array($rawLabel, ['منبع', 'توضیح ابهام', 'احتمال جایگزین', 'احتمال تکرار از نمونه'], true)) {
        $label = $rawLabel;
    } else {
        $recognized = false;
    }

    if (!$recognized) {
        return null;
    }

    return [
        'label' => $label,
        'tail' => dent_exams_text_source_restore_digits($tail),
    ];
}

function dent_exams_diagnostics2_final_sample_build_prediction_answer_sections(array $sections): array
{
    $payload = [];
    foreach ($sections as $label => $lines) {
        $label = trim((string) $label);
        $value = dent_exams_diagnostics2_final_sample_join_lines(is_array($lines) ? $lines : []);
        if ($label === '' || $value === '') {
            continue;
        }

        $payload[] = [
            'label' => $label,
            'value' => $value,
            'tone' => dent_exams_diagnostics2_final_sample_prediction_section_tone($label, $value),
        ];
    }

    return $payload;
}

function dent_exams_diagnostics2_final_sample_prediction_section_tone(string $label, string $value): string
{
    if (preg_match('/اطمینان/u', $label) === 1) {
        return preg_match('/کم|متوسط|ابهام|ناقص/u', $value) === 1 ? 'warning' : 'success';
    }
    if (preg_match('/ابهام|جایگزین|هشدار|تعارض/u', $label . ' ' . $value) === 1) {
        return 'warning';
    }
    if (preg_match('/منبع|تطبیق|منطق|صورت/u', $label) === 1) {
        return 'info';
    }

    return dent_exams_diagnostics2_final_sample_section_tone($label);
}

function dent_exams_diagnostics2_final_sample_parse_shakib_file(string $path): array
{
    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return [
            'title' => '',
            'questions' => [],
        ];
    }

    $text = dent_exams_text_source_normalize_text($raw);
    $parts = preg_split('/^\s*خانواده\s+س(?:ؤ|و)ال\s*(\d+)\s*$/mu', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
    $questions = [];

    for ($index = 1; $index + 1 < count($parts); $index += 2) {
        $number = max(0, (int) ($parts[$index] ?? 0));
        $chunk = trim((string) ($parts[$index + 1] ?? ''));
        if ($number <= 0 || $chunk === '') {
            continue;
        }

        $question = dent_exams_diagnostics2_final_sample_parse_shakib_chunk($number, $chunk);
        if ($question !== null) {
            $questions[] = $question;
        }
    }

    return [
        'title' => dent_exams_diagnostics2_final_sample_extract_title($text),
        'questions' => $questions,
    ];
}

function dent_exams_diagnostics2_final_sample_parse_shakib_chunk(int $number, string $chunk): ?array
{
    $sections = dent_exams_diagnostics2_final_sample_collect_shakib_sections($chunk);
    $topic = dent_exams_diagnostics2_final_sample_first_section_value($sections, 'عنوان مبحث');
    $keyAnswer = dent_exams_diagnostics2_final_sample_first_section_value($sections, 'پاسخ کلیدی');
    $frequentQuestion = dent_exams_diagnostics2_final_sample_first_section_value($sections, 'صورت سؤال پرتکرار');
    $exact = dent_exams_diagnostics2_final_sample_extract_shakib_exact_question($chunk);

    $questionText = trim((string) ($exact['question'] ?? ''));
    $options = is_array($exact['options'] ?? null) ? $exact['options'] : [];
    $correctIndex = is_int($exact['correctIndex'] ?? null) ? $exact['correctIndex'] : null;

    if ($questionText === '') {
        $questionText = $frequentQuestion !== '' ? $frequentQuestion : $topic;
    }
    if ($questionText === '') {
        return null;
    }

    $answerSections = dent_exams_diagnostics2_final_sample_build_shakib_answer_sections($sections);
    $analysisLines = is_array($sections['بررسی تک‌تک گزینه‌ها'] ?? null) ? $sections['بررسی تک‌تک گزینه‌ها'] : [];

    $payload = [
        'number' => $number,
        'question' => $questionText,
        'options' => array_values($options),
        'correctIndex' => count($options) >= 2 ? ($correctIndex ?? 0) : null,
        'topic' => $topic,
        'answerRevealLabel' => 'نمایش تحلیل و ردپای تکرار',
        'answerCardLabel' => 'تحلیل، منبع و ردپای تکرار',
        'answerMeta' => dent_exams_diagnostics2_final_sample_build_shakib_meta($sections),
        'answerSections' => $answerSections,
        'explanation' => dent_exams_diagnostics2_final_sample_compose_explanation($sections),
        'optionRationales' => dent_exams_diagnostics2_final_sample_option_rationales($analysisLines),
    ];

    if ($keyAnswer !== '') {
        $payload['answerSummary'] = $keyAnswer;
    }

    return $payload;
}

function dent_exams_diagnostics2_final_sample_collect_shakib_sections(string $chunk): array
{
    $lines = preg_split('/\R/u', $chunk) ?: [];
    $sections = [];
    $currentSection = '';

    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '' || preg_match('/^[=\-.]{5,}$/u', $trimmed) === 1) {
            continue;
        }
        if (preg_match('/^(?:جمع‌بندی|پایان فایل)\b/u', $trimmed) === 1) {
            break;
        }

        if (preg_match('/^نمونه\s+تیر\s+1400/u', $trimmed) === 1) {
            $currentSection = 'نسخه دقیق تیر ۱۴۰۰';
            $sections[$currentSection] = $sections[$currentSection] ?? [];
            $sections[$currentSection][] = dent_exams_text_source_restore_digits($trimmed);
            continue;
        }

        $section = dent_exams_diagnostics2_final_sample_shakib_section_from_line($trimmed);
        if ($section !== null) {
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
            $sections[$currentSection] = $sections[$currentSection] ?? [];
            $sections[$currentSection][] = dent_exams_text_source_restore_digits($trimmed);
        }
    }

    return $sections;
}

function dent_exams_diagnostics2_final_sample_shakib_section_from_line(string $line): ?array
{
    if (preg_match('/^([^:：]{2,130})\s*[:：]\s*(.*)$/u', $line, $matches) !== 1) {
        return null;
    }

    $rawLabel = trim((string) ($matches[1] ?? ''));
    $tail = dent_exams_text_source_restore_digits(trim((string) ($matches[2] ?? '')));
    $label = $rawLabel;
    $knownLabels = [
        'عنوان مبحث' => true,
        'پاسخ کلیدی' => true,
        'میزان تکرار' => true,
        'نوع تکرار' => true,
        'صورت سؤال پرتکرار' => true,
        'صورت‌های دیگر و نکات دیده‌شده' => true,
        'ردپای تکرار در آزمون‌ها' => true,
        'منبع جزوه' => true,
        'اطمینان انتساب به استاد شکیب' => true,
        'پاسخ تشریحی و نکته آموزشی' => true,
        'پاسخ علامت‌خورده در فایل' => true,
        'پاسخ تحلیلی' => true,
        'وضعیت مقایسه' => true,
        'رفرنس' => true,
        'بررسی تک‌تک گزینه‌ها' => true,
        'وضعیت در نمونه تیر 1400' => true,
    ];

    if (preg_match('/^نسخه\s+یا\s+نسخه‌های\s+دقیق/u', $rawLabel) === 1) {
        $label = 'نسخه دقیق تیر ۱۴۰۰';
    } elseif (!isset($knownLabels[$rawLabel])) {
        return null;
    }

    return [
        'label' => $label,
        'tail' => $tail,
    ];
}

function dent_exams_diagnostics2_final_sample_first_section_value(array $sections, string $label): string
{
    $lines = is_array($sections[$label] ?? null) ? $sections[$label] : [];
    return dent_exams_diagnostics2_final_sample_join_lines($lines);
}

function dent_exams_diagnostics2_final_sample_extract_shakib_exact_question(string $chunk): array
{
    $lines = preg_split('/\R/u', $chunk) ?: [];
    $inside = false;
    $collectPrompt = true;
    $questionLines = [];
    $optionMap = [];
    $markedRaw = '';
    $suggestedRaw = '';

    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '' || preg_match('/^[.\-]{5,}$/u', $trimmed) === 1) {
            continue;
        }

        if (!$inside && preg_match('/^نمونه\s+تیر\s+1400/u', $trimmed) === 1) {
            $inside = true;
            continue;
        }
        if (!$inside) {
            continue;
        }

        if (preg_match('/^پاسخ\s+علامت‌خورده\s+در\s+فایل\s*:\s*(.*)$/u', $trimmed, $matches) === 1) {
            $markedRaw = trim((string) ($matches[1] ?? ''));
            $collectPrompt = false;
            continue;
        }
        if (preg_match('/^پاسخ\s+تحلیلی\s*:\s*(.*)$/u', $trimmed, $matches) === 1) {
            $suggestedRaw = trim((string) ($matches[1] ?? ''));
            $collectPrompt = false;
            continue;
        }
        if (preg_match('/^(?:وضعیت\s+مقایسه|رفرنس|پاسخ\s+تشریحی|بررسی\s+تک‌تک\s+گزینه‌ها)\s*:/u', $trimmed) === 1) {
            $collectPrompt = false;
            continue;
        }
        if (!$collectPrompt) {
            continue;
        }

        if (preg_match('/^(الف|ب|ج|د)[\)\.\-:]\s*(.*)$/u', $trimmed, $matches) === 1) {
            $letter = dent_exams_diagnostics2_final_sample_normalize_letter((string) ($matches[1] ?? ''));
            if ($letter !== '') {
                $optionMap[$letter] = trim((string) ($matches[2] ?? ''));
            }
            continue;
        }

        $questionLines[] = $trimmed;
    }

    $options = dent_exams_diagnostics2_final_sample_normalize_option_map($optionMap);
    if (count($options) !== 4) {
        return [
            'question' => '',
            'options' => [],
            'correctIndex' => null,
        ];
    }

    $suggestedIndex = dent_exams_diagnostics2_final_sample_extract_answer_index($suggestedRaw);
    if ($suggestedIndex === null) {
        $suggestedIndex = dent_exams_diagnostics2_final_sample_extract_answer_index($markedRaw);
    }

    return [
        'question' => dent_exams_answer_sheet_compose_question_text($questionLines),
        'options' => $options,
        'correctIndex' => $suggestedIndex,
    ];
}

function dent_exams_diagnostics2_final_sample_build_shakib_meta(array $sections): array
{
    $definitions = [
        ['عنوان مبحث', 'مبحث', 'accent'],
        ['پاسخ کلیدی', 'پاسخ کلیدی', 'success'],
        ['میزان تکرار', 'میزان تکرار', 'accent'],
        ['نوع تکرار', 'نوع تکرار', 'neutral'],
        ['اطمینان انتساب به استاد شکیب', 'اطمینان انتساب', 'success'],
    ];
    $meta = [];

    foreach ($definitions as $definition) {
        [$sourceLabel, $displayLabel, $tone] = $definition;
        $value = dent_exams_diagnostics2_final_sample_first_section_value($sections, $sourceLabel);
        if ($value === '') {
            continue;
        }

        $meta[] = [
            'label' => $displayLabel,
            'value' => $value,
            'tone' => preg_match('/کم|متوسط|ابهام/u', $value) === 1 ? 'warning' : $tone,
            'wide' => true,
        ];
    }

    return $meta;
}

function dent_exams_diagnostics2_final_sample_build_shakib_answer_sections(array $sections): array
{
    $skipLabels = [
        'عنوان مبحث' => true,
        'پاسخ کلیدی' => true,
        'میزان تکرار' => true,
        'نوع تکرار' => true,
        'اطمینان انتساب به استاد شکیب' => true,
    ];
    $payload = [];

    foreach ($sections as $label => $lines) {
        $label = trim((string) $label);
        if ($label === '' || isset($skipLabels[$label])) {
            continue;
        }

        $value = dent_exams_diagnostics2_final_sample_join_lines(is_array($lines) ? $lines : []);
        if ($value === '') {
            continue;
        }

        $payload[] = [
            'label' => $label,
            'value' => $value,
            'tone' => dent_exams_diagnostics2_final_sample_shakib_section_tone($label, $value),
        ];
    }

    return $payload;
}

function dent_exams_diagnostics2_final_sample_shakib_section_tone(string $label, string $value): string
{
    if (preg_match('/ردپای|منبع|نسخه دقیق|صورت/u', $label) === 1) {
        return 'info';
    }
    if (preg_match('/پاسخ تشریحی|بررسی/u', $label) === 1) {
        return 'success';
    }
    if (preg_match('/هشدار|مشکل|ابهام|محدود|تعارض|معیوب/u', $label . ' ' . $value) === 1) {
        return 'warning';
    }

    return dent_exams_diagnostics2_final_sample_section_tone($label);
}
