<?php
declare(strict_types=1);

function dent_exams_text_source_build_course(array $config): array
{
    $courseSlug = trim((string) ($config['courseSlug'] ?? ''));
    $title = trim((string) ($config['title'] ?? ''));
    $shortTitle = trim((string) ($config['shortTitle'] ?? $title));
    $termLabel = trim((string) ($config['termLabel'] ?? ''));
    $dataDir = trim((string) ($config['dataDir'] ?? ''));
    $paymentAmount = max(0, (int) ($config['paymentAmount'] ?? 450000));
    $examDefinitions = is_array($config['examDefinitions'] ?? null) ? $config['examDefinitions'] : [];

    if ($courseSlug === '' || $title === '' || $dataDir === '' || $examDefinitions === []) {
        return [];
    }

    $coursePath = '/exams/' . $courseSlug . '/';
    $examPayloads = [];
    $activeExamCount = 0;
    $activeQuestionCount = 0;

    foreach ($examDefinitions as $definition) {
        if (!is_array($definition)) {
            continue;
        }

        $slug = trim((string) ($definition['slug'] ?? ''));
        $label = trim((string) ($definition['label'] ?? ''));
        $patterns = is_array($definition['patterns'] ?? null) ? $definition['patterns'] : [];
        if ($slug === '' || $label === '' || $patterns === []) {
            continue;
        }

        $isEssayDefinition = ($definition['kind'] ?? '') === 'essay';
        $parser = is_callable($definition['parser'] ?? null)
            ? $definition['parser']
            : 'dent_exams_text_source_parse_file';
        $definitionTopic = trim((string) ($definition['topic'] ?? ''));

        $sourcePath = dent_exams_text_source_find_source_file($dataDir, $patterns);
        $sourcePayload = $sourcePath !== ''
            ? $parser($sourcePath)
            : ['topic' => '', 'questions' => []];

        $sourceTopic = trim((string) ($sourcePayload['topic'] ?? ''));
        $topic = $sourceTopic !== '' ? $sourceTopic : $definitionTopic;
        $questions = is_array($sourcePayload['questions'] ?? null) ? $sourcePayload['questions'] : [];
        $comingSoon = $questions === [];
        $questionCount = count($questions);
        $questionCountFa = dent_exams_text_source_to_persian_digits((string) $questionCount);

        if (!$comingSoon) {
            $activeExamCount++;
            $activeQuestionCount += $questionCount;
        }

        $examPayloads[] = [
            'slug' => $slug,
            'path' => $coursePath . $slug . '/',
            'label' => $label,
            'title' => $topic !== ''
                ? 'آزمون ' . $label . ' - ' . $topic
                : 'آزمون ' . $label,
            'subtitle' => $comingSoon
                ? 'صفحه این جلسه آماده است و سوال‌های آن به‌زودی از همین مسیر فعال می‌شوند.'
                : $questionCountFa . ($isEssayDefinition ? ' سوال تشریحی با پاسخ تفصیلی' : ' سوال چهارگزینه‌ای با پاسخ تشریحی')
                    . ($topic !== '' ? ' از مبحث «' . $topic . '».' : '.'),
            'description' => $comingSoon
                ? 'سوال‌های این جلسه هنوز اضافه نشده‌اند و به‌زودی از همین صفحه در دسترس قرار می‌گیرند.'
                : 'مرور ' . $questionCountFa . ' سوال'
                    . ($topic !== '' ? ' از مبحث «' . $topic . '»' : '')
                    . ' در ' . $title . '.',
            'eyebrow' => $title . ' | ' . $label . ($topic !== '' ? ' | ' . $topic : ''),
            'ctaLabel' => $comingSoon ? 'مشاهده وضعیت جلسه' : 'انتخاب حالت و شروع',
            'backHref' => $coursePath,
            'backLabel' => 'بازگشت به فهرست آزمون‌های ' . $title,
            'autoAdvance' => true,
            'siteTitle' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
            'siteSubtitle' => 'آزمون‌ها',
            'siteBadge' => 'آزمون تمرینی',
            'footerText' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
            'attemptable' => true,
            'comingSoon' => $comingSoon,
            'emptyStateTitle' => 'سوال‌های این جلسه به‌زودی اضافه می‌شود',
            'emptyStateMessage' => 'صفحه ' . $label . ' از همین حالا آماده است و سوال‌های آن به‌زودی در همین مسیر قرار می‌گیرد.',
            'countsTowardStats' => !$comingSoon,
            'questionCount' => $questionCount,
            'questions' => $questions,
        ];
    }

    $plannedExamCount = count($examPayloads);
    if ($plannedExamCount <= 0) {
        return [];
    }

    $plannedExamCountFa = dent_exams_text_source_to_persian_digits((string) $plannedExamCount);
    $activeExamCountFa = dent_exams_text_source_to_persian_digits((string) $activeExamCount);
    $activeQuestionCountFa = dent_exams_text_source_to_persian_digits((string) $activeQuestionCount);
    $hasComingSoon = $activeExamCount < $plannedExamCount;
    $fullTitle = $termLabel !== '' ? $title . ' ' . $termLabel : $title;

    $cardDescription = $hasComingSoon
        ? 'فعلاً ' . $activeExamCountFa . ' آزمون از ' . $plannedExamCountFa
            . ' آزمون این درس فعال شده و بقیه آزمون‌ها از همین صفحه اضافه می‌شوند.'
        : 'همهٔ ' . $plannedExamCountFa . ' آزمون فعلی این درس با مجموع '
            . $activeQuestionCountFa . ' سوال از همین صفحه در دسترس است.';

    $heroDescription = $hasComingSoon
        ? 'این مجموعه مربوط به درس ' . $fullTitle . ' است. فعلاً ' . $activeExamCountFa
            . ' آزمون از ' . $plannedExamCountFa . ' آزمون با مجموع ' . $activeQuestionCountFa
            . ' سوال فعال شده‌اند و بقیه آزمون‌ها به‌زودی از همین مسیر تکمیل می‌شوند. با یک بار پرداخت ۴۵ هزار تومان، کل درس برای همین حساب فعال می‌شود.'
        : 'این مجموعه مربوط به درس ' . $fullTitle . ' است و فعلاً ' . $plannedExamCountFa
            . ' آزمون با مجموع ' . $activeQuestionCountFa
            . ' سوال را پوشش می‌دهد. با یک بار پرداخت ۴۵ هزار تومان، کل درس برای همین حساب فعال می‌شود.';

    return [
        'slug' => $courseSlug,
        'title' => $title,
        'shortTitle' => $shortTitle !== '' ? $shortTitle : $title,
        'badge' => $plannedExamCountFa . ' آزمون',
        'cardDescription' => $cardDescription,
        'heroTitle' => $title,
        'heroDescription' => $heroDescription,
        'path' => $coursePath,
        'paymentTitle' => 'دسترسی به آزمون‌های ' . $title,
        'paymentDescription' => 'با یک بار پرداخت ۴۵ هزار تومان، همهٔ جلسه‌های ' . $title . ' برای همین حساب فعال می‌شود.',
        'paymentSuccessMessage' => 'پرداخت شما تایید شد و همهٔ جلسه‌های ' . $title . ' برای این حساب باز شد.',
        'paymentFailureMessage' => 'فعال‌سازی آزمون‌های ' . $title
            . ' انجام نشد. نتیجه را دوباره بررسی کنید.',
        'defaultPaymentMode' => 'paid',
        'defaultAmount' => $paymentAmount,
        'exams' => $examPayloads,
    ];
}

function dent_exams_text_source_find_source_file(string $baseDir, array $patterns): string
{
    foreach ($patterns as $pattern) {
        $globPattern = rtrim($baseDir, '\\/') . '/' . ltrim((string) $pattern, '\\/');
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

function dent_exams_text_source_parse_file_multiline(string $path): array
{
    return dent_exams_text_source_parse_file($path, true);
}

/**
 * Parses the detailed four-option format used for course practice sessions:
 * question blocks followed by a «پاسخنامه تشریحی» with answer, reason,
 * option-by-option review and source location.
 */
function dent_exams_text_source_parse_detailed_file(string $path): array
{
    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return ['topic' => '', 'questions' => []];
    }

    $text = dent_exams_text_source_normalize_text($raw);
    $parts = preg_split('/^\s*پاسخنامه\s+تشریحی\s*$/miu', $text, 2);
    $questionMap = dent_exams_text_source_collect_questions(trim((string) ($parts[0] ?? $text)));
    $answerParts = preg_split(
        '/^\s*س(?:ؤ|و)ال\s*(\d+)\s*$/mu',
        trim((string) ($parts[1] ?? '')),
        -1,
        PREG_SPLIT_DELIM_CAPTURE
    ) ?: [];
    $answers = [];

    for ($index = 1; $index + 1 < count($answerParts); $index += 2) {
        $number = max(0, (int) ($answerParts[$index] ?? 0));
        $answer = dent_exams_text_source_parse_detailed_answer_chunk(trim((string) ($answerParts[$index + 1] ?? '')));
        if ($number > 0 && is_array($answer)) {
            $answers[$number] = $answer;
        }
    }

    $questions = [];
    foreach ($questionMap as $questionData) {
        $number = max(0, (int) ($questionData['number'] ?? 0));
        $options = is_array($questionData['options'] ?? null) ? array_values($questionData['options']) : [];
        $answer = is_array($answers[$number] ?? null) ? $answers[$number] : null;
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

function dent_exams_text_source_parse_detailed_answer_chunk(string $chunk): ?array
{
    $correctRaw = '';
    $section = '';
    $parts = ['reason' => [], 'analysis' => [], 'reference' => []];
    $lines = preg_split('/\R/u', $chunk) ?: [];

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
            $parts[$section][] = $line;
        }
    }

    if (preg_match('/گزینه\s*(الف|ب|ج|د|a|b|c|d)/iu', $correctRaw, $matches) !== 1) {
        return null;
    }
    $reason = dent_exams_text_source_detailed_join_lines($parts['reason']);
    $analysis = dent_exams_text_source_detailed_join_lines($parts['analysis']);
    $reference = dent_exams_text_source_detailed_join_lines($parts['reference']);
    $explanation = ['**پاسخ درست:** ' . dent_exams_text_source_restore_digits($correctRaw)];
    $answerSections = [];
    if ($reason !== '') {
        $explanation[] = "**دلیل درست‌بودن:**\n" . $reason;
        $answerSections[] = ['label' => 'دلیل درست‌بودن', 'value' => $reason, 'tone' => 'success'];
    }
    if ($analysis !== '') {
        $explanation[] = "**بررسی گزینه‌ها:**\n" . $analysis;
        $answerSections[] = ['label' => 'بررسی گزینه‌ها', 'value' => $analysis];
    }
    if ($reference !== '') {
        $explanation[] = "**محل پاسخ در منبع:**\n" . $reference;
    }

    $answerMeta = [['label' => 'پاسخ درست', 'value' => dent_exams_text_source_restore_digits($correctRaw), 'tone' => 'success']];
    if ($reference !== '') {
        $answerMeta[] = ['label' => 'محل پاسخ در منبع', 'value' => $reference, 'tone' => 'info', 'wide' => true];
    }

    return [
        'correctIndex' => dent_exams_text_source_option_letter_to_index((string) $matches[1]),
        'explanation' => implode("\n\n", $explanation),
        'answerSections' => $answerSections,
        'optionRationales' => dent_exams_text_source_detailed_option_rationales($parts['analysis']),
        'answerMeta' => $answerMeta,
    ];
}

function dent_exams_text_source_detailed_join_lines(array $lines): string
{
    return dent_exams_text_source_restore_digits(trim(implode("\n", array_filter(array_map('trim', $lines)))));
}

function dent_exams_text_source_detailed_option_rationales(array $lines): array
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

function dent_exams_text_source_parse_file(string $path, bool $multilineExplanation = false): array
{
    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        return [
            'topic' => '',
            'questions' => [],
        ];
    }

    $text = dent_exams_text_source_normalize_text($raw);
    [$questionText, $answerText] = dent_exams_text_source_split_sections($text);
    $questionMap = dent_exams_text_source_collect_questions($questionText);
    $answerMap = dent_exams_text_source_collect_answers($answerText, $multilineExplanation);
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
        'topic' => dent_exams_text_source_extract_topic($text),
        'questions' => $questions,
    ];
}

function dent_exams_text_source_normalize_text(string $raw): string
{
    $text = preg_replace('/^\x{FEFF}/u', '', $raw) ?? $raw;
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = str_replace(["\xC2\xA0", "\xE2\x80\x8B", "\xE2\x80\xAF"], ' ', $text);
    $text = dent_exams_text_source_normalize_digits($text);
    return trim($text);
}

function dent_exams_text_source_normalize_digits(string $text): string
{
    return strtr($text, [
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

function dent_exams_text_source_split_sections(string $text): array
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

    return [
        $questionText === false ? $text : $questionText,
        $answerText === false ? '' : $answerText,
    ];
}

function dent_exams_text_source_collect_questions(string $text): array
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
                $normalizedOptions[] = dent_exams_text_source_inline_text($optionValue);
            }
        }

        if (count($normalizedOptions) === 4) {
            $questions[] = [
                'number' => $currentNumber,
                'question' => dent_exams_text_source_inline_text(implode(' ', $questionLines)),
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

        $questionStart = dent_exams_text_source_match_question_start($trimmed);
        if ($questionStart !== null) {
            $flush();
            $currentNumber = $questionStart['number'];
            $questionLines = [trim((string) $questionStart['text'])];
            $currentOptionKey = '';
            continue;
        }

        if ($currentNumber <= 0) {
            continue;
        }

        $optionStart = dent_exams_text_source_match_option_start($trimmed);
        if ($optionStart !== null) {
            $currentOptionKey = $optionStart['key'];
            $options[$currentOptionKey] = trim((string) $optionStart['text']);
            continue;
        }

        if ($currentOptionKey !== '' && isset($options[$currentOptionKey])) {
            $options[$currentOptionKey] .= ' ' . $trimmed;
        } else {
            $questionLines[] = $trimmed;
        }
    }

    $flush();

    return $questions;
}

function dent_exams_text_source_collect_answers(string $text, bool $multilineExplanation = false): array
{
    $lines = preg_split('/\R/u', $text) ?: [];
    $answers = [];
    $currentNumber = 0;
    $currentCorrectIndex = null;
    $currentExplanation = [];

    $flush = static function () use (&$answers, &$currentNumber, &$currentCorrectIndex, &$currentExplanation, $multilineExplanation): void {
        if ($currentNumber <= 0 || $currentCorrectIndex === null) {
            $currentNumber = 0;
            $currentCorrectIndex = null;
            $currentExplanation = [];
            return;
        }

        $answers[$currentNumber] = [
            'correctIndex' => $currentCorrectIndex,
            'explanation' => $multilineExplanation
                ? dent_exams_text_source_explanation_lines($currentExplanation)
                : dent_exams_text_source_inline_text(implode(' ', $currentExplanation)),
        ];

        $currentNumber = 0;
        $currentCorrectIndex = null;
        $currentExplanation = [];
    };

    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '') {
            continue;
        }

        $answerStart = dent_exams_text_source_match_answer_start($trimmed);
        if ($answerStart !== null) {
            $flush();
            $currentNumber = max(0, (int) ($answerStart['number'] ?? 0));
            $currentCorrectIndex = dent_exams_text_source_option_letter_to_index((string) ($answerStart['letter'] ?? ''));
            $tail = trim((string) ($answerStart['tail'] ?? ''));
            $currentExplanation = $tail !== '' ? [$tail] : [];
            continue;
        }

        if ($currentNumber <= 0) {
            continue;
        }

        $currentExplanation[] = $trimmed;
    }

    $flush();

    return $answers;
}

function dent_exams_text_source_match_question_start(string $line): ?array
{
    if (preg_match('/^(\d+)[\.\)]\s*(.*)$/u', $line, $matches) === 1) {
        return [
            'number' => max(0, (int) $matches[1]),
            'text' => trim((string) ($matches[2] ?? '')),
        ];
    }

    if (preg_match('/^\s*(?:سؤال|سوال)\s*(\d+)\s*[\)\.:\-]?\s*(.*)$/u', $line, $matches) === 1) {
        return [
            'number' => max(0, (int) $matches[1]),
            'text' => trim((string) ($matches[2] ?? '')),
        ];
    }

    return null;
}

function dent_exams_text_source_match_option_start(string $line): ?array
{
    if (preg_match('/^(الف|ب|ج|د|a|b|c|d)[\)\.\-:\s]\s*(.*)$/iu', $line, $matches) === 1) {
        $key = dent_exams_text_source_normalize_option_key((string) $matches[1]);
        if ($key === '') {
            return null;
        }

        return [
            'key' => $key,
            'text' => trim((string) ($matches[2] ?? '')),
        ];
    }

    return null;
}

function dent_exams_text_source_match_answer_start(string $line): ?array
{
    $patterns = [
        '/^(\d+)[\.\)]\s*(?:گزینه\s*)?(الف|ب|ج|د|a|b|c|d)(?:\s*[،,:-]\s*(.*))?$/u',
        '/^(\d+)[\.\)]\s*پاسخ(?:\s*(?:صحیح|درست))?\s*:\s*(?:گزینه\s*)?(الف|ب|ج|د|a|b|c|d)(?:\s*[،,:-]\s*(.*))?$/u',
        '/^پاسخ\s*سؤال\s*(\d+)\s*:\s*(?:گزینه\s*)?(الف|ب|ج|د|a|b|c|d)(?:\s*[،,:-]\s*(.*))?$/u',
        '/^پاسخ\s*سوال\s*(\d+)\s*:\s*(?:گزینه\s*)?(الف|ب|ج|د|a|b|c|d)(?:\s*[،,:-]\s*(.*))?$/u',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $line, $matches) !== 1) {
            continue;
        }

        return [
            'number' => max(0, (int) ($matches[1] ?? 0)),
            'letter' => (string) ($matches[2] ?? ''),
            'tail' => trim((string) ($matches[3] ?? '')),
        ];
    }

    return null;
}

function dent_exams_text_source_normalize_option_key(string $key): string
{
    $key = trim($key);
    return match ($key) {
        'الف', 'a', 'A' => 'الف',
        'ب', 'b', 'B' => 'ب',
        'ج', 'c', 'C' => 'ج',
        'د', 'd', 'D' => 'د',
        default => '',
    };
}

function dent_exams_text_source_option_letter_to_index(string $letter): int
{
    return match (dent_exams_text_source_normalize_option_key($letter)) {
        'الف' => 0,
        'ب' => 1,
        'ج' => 2,
        'د' => 3,
        default => 0,
    };
}

function dent_exams_text_source_extract_topic(string $text): string
{
    if (preg_match('/^#\s*(.+)$/m', $text, $matches) === 1) {
        return dent_exams_text_source_restore_digits(trim((string) $matches[1]));
    }

    if (preg_match('/^\s*(?:مبحث|موضوع)\s*:\s*(.+)$/miu', $text, $matches) === 1) {
        return dent_exams_text_source_restore_digits(trim((string) ($matches[1] ?? '')));
    }

    return '';
}

function dent_exams_text_source_inline_text(string $text): string
{
    $text = trim(preg_replace('/\s+/u', ' ', trim($text)) ?? $text);
    return dent_exams_text_source_restore_digits($text);
}

function dent_exams_text_source_explanation_lines(array $lines): string
{
    $clean = [];
    foreach ($lines as $line) {
        $line = trim(preg_replace('/[ \t]+/u', ' ', (string) $line) ?? (string) $line);
        if ($line === '') {
            continue;
        }
        if (preg_match('/^(دلیل[^:]+|منبع):\s*(.+)$/u', $line, $matches) === 1) {
            $line = '**' . trim((string) $matches[1]) . ':** ' . trim((string) $matches[2]);
        }
        $clean[] = $line;
    }

    return dent_exams_text_source_restore_digits(implode("\n", $clean));
}

function dent_exams_text_source_restore_digits(string $text): string
{
    return strtr($text, [
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

function dent_exams_text_source_to_persian_digits(string $value): string
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
