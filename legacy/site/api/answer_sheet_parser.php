<?php
declare(strict_types=1);

require_once __DIR__ . '/text_source_parser.php';

function dent_exams_answer_sheet_parse_file(string $path): array
{
    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return [
            'title' => '',
            'questions' => [],
        ];
    }

    $text = dent_exams_text_source_normalize_text($raw);
    $title = dent_exams_answer_sheet_extract_title($text);

    if (preg_match('/^##\s*(?:سوال|سؤال)\s*\d+/miu', $text) === 1) {
        $questions = dent_exams_answer_sheet_parse_markdown_blocks($text);
    } elseif (preg_match('/^\s*(?:سوال|سؤال)\s*\d+\s*$/miu', $text) === 1 && preg_match('/^\s*متن سؤال\s*:/miu', $text) === 1) {
        $questions = dent_exams_answer_sheet_parse_pipe_blocks($text);
    } else {
        $questions = dent_exams_answer_sheet_parse_plain_blocks($text);
    }

    return [
        'title' => $title,
        'questions' => $questions,
    ];
}

function dent_exams_answer_sheet_parse_question_source_file(string $path): array
{
    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return [
            'title' => '',
            'questions' => [],
        ];
    }

    $text = dent_exams_text_source_normalize_text($raw);

    return [
        'title' => dent_exams_answer_sheet_extract_title($text),
        'questions' => dent_exams_answer_sheet_parse_question_source_text($text),
    ];
}

function dent_exams_answer_sheet_merge_question_source(array $answerPayload, array $questionPayload): array
{
    $answerQuestions = is_array($answerPayload['questions'] ?? null) ? $answerPayload['questions'] : [];
    $questionSource = is_array($questionPayload['questions'] ?? null) ? $questionPayload['questions'] : [];
    $sourceMap = [];

    foreach ($questionSource as $index => $question) {
        if (!is_array($question)) {
            continue;
        }

        $number = max(0, (int) ($question['number'] ?? ($index + 1)));
        if ($number <= 0) {
            continue;
        }

        $sourceMap[$number] = $question;
    }

    $mergedQuestions = [];

    foreach ($answerQuestions as $index => $answerQuestion) {
        if (!is_array($answerQuestion)) {
            continue;
        }

        $number = max(0, (int) ($answerQuestion['number'] ?? ($index + 1)));
        $sourceQuestion = is_array($sourceMap[$number] ?? null)
            ? $sourceMap[$number]
            : (is_array($questionSource[$index] ?? null) ? $questionSource[$index] : null);

        if ($sourceQuestion === null) {
            $mergedQuestions[] = $answerQuestion;
            continue;
        }

        $questionText = trim((string) ($sourceQuestion['question'] ?? ''));
        if ($questionText !== '') {
            $answerQuestion['question'] = $questionText;
        }

        $sourceOptions = dent_exams_answer_sheet_normalize_option_map([
            1 => (string) ($sourceQuestion['options'][0] ?? ''),
            2 => (string) ($sourceQuestion['options'][1] ?? ''),
            3 => (string) ($sourceQuestion['options'][2] ?? ''),
            4 => (string) ($sourceQuestion['options'][3] ?? ''),
        ]);
        if (count($sourceOptions) === 4) {
            $answerQuestion['options'] = $sourceOptions;
        } else {
            $sourceOptions = is_array($answerQuestion['options'] ?? null) ? $answerQuestion['options'] : [];
        }

        $metaSource = is_array($answerQuestion['answerMetaSource'] ?? null) ? $answerQuestion['answerMetaSource'] : [];
        $markedRaw = trim((string) ($sourceQuestion['markedRaw'] ?? ''));
        if ($markedRaw === '') {
            $markedRaw = trim((string) ($metaSource['markedRaw'] ?? ''));
        }

        $markedIndex = dent_exams_answer_sheet_extract_option_index($markedRaw);
        $suggestedRaw = trim((string) ($metaSource['suggestedRaw'] ?? ''));
        $suggestedIndex = dent_exams_answer_sheet_extract_option_index($suggestedRaw);
        $referenceRaw = trim((string) ($metaSource['referenceRaw'] ?? ''));

        $answerQuestion['answerMetaSource'] = [
            'markedRaw' => $markedRaw,
            'markedIndex' => $markedIndex,
            'suggestedRaw' => $suggestedRaw,
            'suggestedIndex' => $suggestedIndex,
            'referenceRaw' => $referenceRaw,
        ];
        $answerQuestion['answerMeta'] = dent_exams_answer_sheet_build_meta(
            $sourceOptions,
            $markedRaw,
            $markedIndex,
            $suggestedRaw,
            $suggestedIndex,
            $referenceRaw
        );
        $mergedQuestions[] = $answerQuestion;
    }

    return [
        'title' => trim((string) ($questionPayload['title'] ?? '')) !== ''
            ? trim((string) ($questionPayload['title'] ?? ''))
            : trim((string) ($answerPayload['title'] ?? '')),
        'questions' => $mergedQuestions,
    ];
}

function dent_exams_answer_sheet_extract_title(string $text): string
{
    $lines = preg_split('/\R/u', $text) ?: [];
    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '') {
            continue;
        }

        $trimmed = preg_replace('/^#+\s*/u', '', $trimmed) ?? $trimmed;
        return dent_exams_text_source_restore_digits(trim($trimmed));
    }

    return '';
}

function dent_exams_answer_sheet_parse_markdown_blocks(string $text): array
{
    $parts = preg_split('/^##\s*(?:سوال|سؤال)\s*(\d+)\s*$/mu', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
    $questions = [];

    for ($index = 1; $index + 1 < count($parts); $index += 2) {
        $questionNumber = max(0, (int) ($parts[$index] ?? 0));
        $chunk = trim((string) ($parts[$index + 1] ?? ''));
        if ($questionNumber <= 0 || $chunk === '') {
            continue;
        }

        $question = dent_exams_answer_sheet_parse_markdown_chunk($questionNumber, $chunk);
        if ($question !== null) {
            $questions[] = $question;
        }
    }

    return $questions;
}

function dent_exams_answer_sheet_parse_markdown_chunk(int $questionNumber, string $chunk): ?array
{
    $lines = preg_split('/\R/u', $chunk) ?: [];
    $questionLines = [];
    $explanationLines = [];
    $analysisLines = [];
    $markedRaw = '';
    $suggestedRaw = '';
    $referenceRaw = '';
    $section = 'question';

    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '') {
            if ($section === 'explanation') {
                $explanationLines[] = '';
            }
            continue;
        }

        if (preg_match('/^-\s*گزینه\s+علامت.خورده\s+در\s+فایل\s*:\s*(.+)$/u', $trimmed, $matches) === 1) {
            $markedRaw = trim((string) ($matches[1] ?? ''));
            $section = 'meta';
            continue;
        }

        if (preg_match('/^-\s*پاسخ\s+پیشنهادی\s*:\s*(.+)$/u', $trimmed, $matches) === 1) {
            $suggestedRaw = trim((string) ($matches[1] ?? ''));
            $section = 'meta';
            continue;
        }

        if (preg_match('/^-\s*منبع\s*:\s*(.+)$/u', $trimmed, $matches) === 1) {
            $referenceRaw = trim((string) ($matches[1] ?? ''));
            $section = 'meta';
            continue;
        }

        if (preg_match('/^-\s*پاسخ\s+تشریحی\s*:\s*(.*)$/u', $trimmed, $matches) === 1) {
            $section = 'explanation';
            $tail = trim((string) ($matches[1] ?? ''));
            if ($tail !== '') {
                $explanationLines[] = $tail;
            }
            continue;
        }

        if (preg_match('/^-\s*علت\s+درست\/غلط\s+بودن\s+گزینه.ها\s*:\s*$/u', $trimmed) === 1) {
            $section = 'analysis';
            continue;
        }

        if ($section === 'question') {
            $questionLines[] = $trimmed;
        } elseif ($section === 'analysis') {
            $analysisLines[] = $trimmed;
        } elseif ($section === 'explanation') {
            $explanationLines[] = $trimmed;
        }
    }

    $options = dent_exams_answer_sheet_extract_options_from_analysis($analysisLines);

    return dent_exams_answer_sheet_build_question(
        $questionNumber,
        $questionLines,
        $options,
        $markedRaw,
        $suggestedRaw,
        $referenceRaw,
        $explanationLines,
        $analysisLines
    );
}

function dent_exams_answer_sheet_parse_pipe_blocks(string $text): array
{
    $parts = preg_split('/^\s*(?:سوال|سؤال)\s*(\d+)\s*$/mu', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
    $questions = [];

    for ($index = 1; $index + 1 < count($parts); $index += 2) {
        $questionNumber = max(0, (int) ($parts[$index] ?? 0));
        $chunk = trim((string) ($parts[$index + 1] ?? ''));
        if ($questionNumber <= 0 || $chunk === '') {
            continue;
        }

        $question = dent_exams_answer_sheet_parse_pipe_chunk($questionNumber, $chunk);
        if ($question !== null) {
            $questions[] = $question;
        }
    }

    return $questions;
}

function dent_exams_answer_sheet_parse_pipe_chunk(int $questionNumber, string $chunk): ?array
{
    $lines = preg_split('/\R/u', $chunk) ?: [];
    $questionLines = [];
    $explanationLines = [];
    $analysisLines = [];
    $optionsLine = '';
    $markedRaw = '';
    $suggestedRaw = '';
    $referenceRaw = '';
    $section = '';

    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '') {
            if ($section === 'explanation') {
                $explanationLines[] = '';
            }
            continue;
        }

        if (preg_match('/^متن\s+سؤال\s*:\s*(.*)$/u', $trimmed, $matches) === 1) {
            $section = 'question';
            $tail = trim((string) ($matches[1] ?? ''));
            if ($tail !== '') {
                $questionLines[] = $tail;
            }
            continue;
        }

        if (preg_match('/^گزینه.ها\s*:\s*(.*)$/u', $trimmed, $matches) === 1) {
            $optionsLine = trim((string) ($matches[1] ?? ''));
            $section = 'meta';
            continue;
        }

        if (preg_match('/^گزینه\s+علامت.خورده\s+در\s+فایل\s*\|\s*(.+)$/u', $trimmed, $matches) === 1) {
            $markedRaw = trim((string) ($matches[1] ?? ''));
            $section = 'meta';
            continue;
        }

        if (preg_match('/^پاسخ\s+پیشنهادی\s*\|\s*(.+)$/u', $trimmed, $matches) === 1) {
            $suggestedRaw = trim((string) ($matches[1] ?? ''));
            $section = 'meta';
            continue;
        }

        if (preg_match('/^رفرنس\/منبع\s*\|\s*(.+)$/u', $trimmed, $matches) === 1) {
            $referenceRaw = trim((string) ($matches[1] ?? ''));
            $section = 'meta';
            continue;
        }

        if (preg_match('/^پاسخ\s+تشریحی\s*:\s*(.*)$/u', $trimmed, $matches) === 1) {
            $section = 'explanation';
            $tail = trim((string) ($matches[1] ?? ''));
            if ($tail !== '') {
                $explanationLines[] = $tail;
            }
            continue;
        }

        if (preg_match('/^علت\s+درست\/غلط\s+بودن\s+گزینه.ها\s*:\s*$/u', $trimmed) === 1) {
            $section = 'analysis';
            continue;
        }

        if ($section === 'question') {
            $questionLines[] = $trimmed;
        } elseif ($section === 'analysis') {
            $analysisLines[] = $trimmed;
        } elseif ($section === 'explanation') {
            $explanationLines[] = $trimmed;
        }
    }

    $options = dent_exams_answer_sheet_parse_piped_options($optionsLine);
    if (count($options) !== 4) {
        $options = dent_exams_answer_sheet_extract_options_from_analysis($analysisLines);
    }

    return dent_exams_answer_sheet_build_question(
        $questionNumber,
        $questionLines,
        $options,
        $markedRaw,
        $suggestedRaw,
        $referenceRaw,
        $explanationLines,
        $analysisLines
    );
}

function dent_exams_answer_sheet_parse_plain_blocks(string $text): array
{
    $segments = preg_split('/^\s*={10,}\s*$/mu', $text) ?: [];
    $questions = [];

    foreach ($segments as $segment) {
        $chunk = trim((string) $segment);
        if ($chunk === '' || preg_match('/^\s*(?:سوال|سؤال)\s*\d+\s*:/mu', $chunk) !== 1) {
            continue;
        }

        $question = dent_exams_answer_sheet_parse_plain_chunk($chunk);
        if ($question !== null) {
            $questions[] = $question;
        }
    }

    return $questions;
}

function dent_exams_answer_sheet_parse_plain_chunk(string $chunk): ?array
{
    $lines = preg_split('/\R/u', $chunk) ?: [];
    $questionLines = [];
    $explanationLines = [];
    $analysisLines = [];
    $options = [];
    $markedRaw = '';
    $suggestedRaw = '';
    $referenceRaw = '';
    $section = 'question';

    foreach ($lines as $lineIndex => $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '') {
            if ($section === 'explanation') {
                $explanationLines[] = '';
            }
            continue;
        }

        if ($lineIndex === 0 && preg_match('/^(?:سوال|سؤال)\s*\d+\s*:\s*(.*)$/u', $trimmed, $matches) === 1) {
            $tail = trim((string) ($matches[1] ?? ''));
            if ($tail !== '') {
                $questionLines[] = $tail;
            }
            continue;
        }

        if (preg_match('/^گزینه\s+علامت.خورده\s+در\s+فایل(?:\s+ارسالی)?\s*:\s*(.+)$/u', $trimmed, $matches) === 1) {
            $markedRaw = trim((string) ($matches[1] ?? ''));
            $section = 'meta';
            continue;
        }

        if (preg_match('/^پاسخ\s+پیشنهادی\s*:\s*(.+)$/u', $trimmed, $matches) === 1) {
            $suggestedRaw = trim((string) ($matches[1] ?? ''));
            $section = 'meta';
            continue;
        }

        if (preg_match('/^رفرنس\s*:\s*(.+)$/u', $trimmed, $matches) === 1) {
            $referenceRaw = trim((string) ($matches[1] ?? ''));
            $section = 'meta';
            continue;
        }

        if (preg_match('/^توضیح\s+تشریحی\s*:\s*(.*)$/u', $trimmed, $matches) === 1) {
            $section = 'explanation';
            $tail = trim((string) ($matches[1] ?? ''));
            if ($tail !== '') {
                $explanationLines[] = $tail;
            }
            continue;
        }

        if (preg_match('/^بررسی\s+گزینه.ها\s*:\s*$/u', $trimmed) === 1) {
            $section = 'analysis';
            continue;
        }

        if ($section === 'question' && preg_match('/^([0-9]+)[\)\.]\s*(.+)$/u', $trimmed, $matches) === 1) {
            $optionNumber = max(0, (int) ($matches[1] ?? 0));
            if ($optionNumber >= 1 && $optionNumber <= 4) {
                $options[$optionNumber] = trim((string) ($matches[2] ?? ''));
                continue;
            }
        }

        if ($section === 'analysis') {
            $analysisLines[] = $trimmed;
        } elseif ($section === 'explanation') {
            $explanationLines[] = $trimmed;
        } else {
            $questionLines[] = $trimmed;
        }
    }

    return dent_exams_answer_sheet_build_question(
        0,
        $questionLines,
        dent_exams_answer_sheet_normalize_option_map($options),
        $markedRaw,
        $suggestedRaw,
        $referenceRaw,
        $explanationLines,
        $analysisLines
    );
}

function dent_exams_answer_sheet_build_question(
    int $questionNumber,
    array $questionLines,
    array $options,
    string $markedRaw,
    string $suggestedRaw,
    string $referenceRaw,
    array $explanationLines,
    array $analysisLines
): ?array {
    $question = dent_exams_answer_sheet_compose_question_text($questionLines);
    if ($question === '' || count($options) !== 4) {
        return null;
    }

    $markedIndex = dent_exams_answer_sheet_extract_option_index($markedRaw);
    $suggestedIndex = dent_exams_answer_sheet_extract_option_index($suggestedRaw);
    $explanation = dent_exams_answer_sheet_compose_explanation($explanationLines, $analysisLines);

    return [
        'question' => $question,
        'options' => array_values($options),
        'correctIndex' => $suggestedIndex,
        'explanation' => $explanation,
        'answerMeta' => dent_exams_answer_sheet_build_meta(
            array_values($options),
            $markedRaw,
            $markedIndex,
            $suggestedRaw,
            $suggestedIndex,
            $referenceRaw
        ),
        'answerMetaSource' => [
            'markedRaw' => trim($markedRaw),
            'markedIndex' => $markedIndex,
            'suggestedRaw' => trim($suggestedRaw),
            'suggestedIndex' => $suggestedIndex,
            'referenceRaw' => trim($referenceRaw),
        ],
    ];
}

function dent_exams_answer_sheet_compose_question_text(array $lines): string
{
    $clean = [];
    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }
        $clean[] = dent_exams_text_source_restore_digits($line);
    }

    return implode("\n", $clean);
}

function dent_exams_answer_sheet_compose_explanation(array $explanationLines, array $analysisLines): string
{
    $parts = [];
    $cleanExplanation = [];

    foreach ($explanationLines as $line) {
        $line = trim((string) $line);
        if ($line === '') {
            if ($cleanExplanation !== [] && end($cleanExplanation) !== '') {
                $cleanExplanation[] = '';
            }
            continue;
        }
        $cleanExplanation[] = dent_exams_text_source_restore_digits($line);
    }

    if ($cleanExplanation !== []) {
        $parts[] = trim(implode("\n", $cleanExplanation));
    }

    $formattedAnalysis = [];
    foreach ($analysisLines as $line) {
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }
        if (preg_match('/^-\s*(.+)$/u', $line, $matches) === 1) {
            $formattedAnalysis[] = '- ' . dent_exams_text_source_restore_digits(trim((string) ($matches[1] ?? '')));
            continue;
        }

        if (preg_match('/^([0-9]+)[\)\.]\s*(.+)$/u', $line, $matches) === 1) {
            $formattedAnalysis[] = '- گزینه ' . dent_exams_text_source_restore_digits((string) ($matches[1] ?? ''))
                . ': ' . dent_exams_text_source_restore_digits(trim((string) ($matches[2] ?? '')));
            continue;
        }

        $formattedAnalysis[] = '- ' . dent_exams_text_source_restore_digits($line);
    }

    if ($formattedAnalysis !== []) {
        $parts[] = "**بررسی گزینه‌ها:**\n" . implode("\n", $formattedAnalysis);
    }

    return trim(implode("\n\n", $parts));
}

function dent_exams_answer_sheet_parse_question_source_text(string $text): array
{
    $lines = preg_split('/\R/u', $text) ?: [];
    $questions = [];
    $currentNumber = 0;
    $questionLines = [];
    $optionMap = [];
    $currentOptionNumber = 0;
    $markedRaw = '';

    $flush = static function () use (&$questions, &$currentNumber, &$questionLines, &$optionMap, &$currentOptionNumber, &$markedRaw): void {
        if ($currentNumber <= 0) {
            $questionLines = [];
            $optionMap = [];
            $currentOptionNumber = 0;
            $markedRaw = '';
            return;
        }

        $question = dent_exams_text_source_restore_digits(trim(implode(' ', $questionLines)));
        $options = dent_exams_answer_sheet_normalize_option_map($optionMap);
        if ($question !== '' && count($options) === 4) {
            $questions[] = [
                'number' => $currentNumber,
                'question' => $question,
                'options' => $options,
                'markedRaw' => dent_exams_text_source_restore_digits(trim($markedRaw)),
            ];
        }

        $currentNumber = 0;
        $questionLines = [];
        $optionMap = [];
        $currentOptionNumber = 0;
        $markedRaw = '';
    };

    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '' || preg_match('/^-{5,}$/u', $trimmed) === 1) {
            continue;
        }

        if ($currentNumber <= 0 && preg_match('/^[^0-9].*:\s*.+$/u', $trimmed) === 1) {
            continue;
        }

        if (preg_match('/^([0-9]+)\.\s*(.+)$/u', $trimmed, $matches) === 1) {
            $flush();
            $currentNumber = max(0, (int) ($matches[1] ?? 0));
            $questionLines = [trim((string) ($matches[2] ?? ''))];
            continue;
        }

        if ($currentNumber <= 0) {
            continue;
        }

        if (preg_match('/^([1-4])\)\s*(.*)$/u', $trimmed, $matches) === 1) {
            $currentOptionNumber = max(0, (int) ($matches[1] ?? 0));
            $optionMap[$currentOptionNumber] = trim((string) ($matches[2] ?? ''));
            continue;
        }

        if (count($optionMap) === 4 && preg_match('/^[^0-9][^:]{2,}:\s*(.+)$/u', $trimmed, $matches) === 1) {
            $markedRaw = trim((string) ($matches[1] ?? ''));
            $currentOptionNumber = 0;
            continue;
        }

        if ($currentOptionNumber >= 1 && $currentOptionNumber <= 4 && isset($optionMap[$currentOptionNumber])) {
            $optionMap[$currentOptionNumber] .= ' ' . $trimmed;
            continue;
        }

        $questionLines[] = $trimmed;
    }

    $flush();

    return $questions;
}

function dent_exams_answer_sheet_extract_options_from_analysis(array $analysisLines): array
{
    $optionMap = [];
    foreach ($analysisLines as $line) {
        $line = trim((string) $line);
        if (preg_match('/^-+\s*گزینه\s*([0-9]+)\s*:\s*(.+)$/u', $line, $matches) !== 1) {
            continue;
        }

        $optionNumber = max(0, (int) ($matches[1] ?? 0));
        if ($optionNumber < 1 || $optionNumber > 4) {
            continue;
        }

        $optionMap[$optionNumber] = trim((string) ($matches[2] ?? ''));
    }

    return dent_exams_answer_sheet_normalize_option_map($optionMap);
}

function dent_exams_answer_sheet_parse_piped_options(string $line): array
{
    $optionMap = [];
    if ($line === '') {
        return [];
    }

    if (preg_match_all('/(?:^|\|\s*)([0-9]+)\)\s*(.*?)(?=(?:\s*\|\s*[0-9]+\)\s*)|$)/u', $line, $matches, PREG_SET_ORDER) !== false) {
        foreach ($matches as $match) {
            $optionNumber = max(0, (int) ($match[1] ?? 0));
            if ($optionNumber < 1 || $optionNumber > 4) {
                continue;
            }
            $optionMap[$optionNumber] = trim((string) ($match[2] ?? ''));
        }
    }

    return dent_exams_answer_sheet_normalize_option_map($optionMap);
}

function dent_exams_answer_sheet_normalize_option_map(array $optionMap): array
{
    $options = [];
    foreach ([1, 2, 3, 4] as $optionNumber) {
        $value = trim((string) ($optionMap[$optionNumber] ?? ''));
        if ($value === '') {
            return [];
        }
        $options[] = dent_exams_text_source_restore_digits($value);
    }

    return $options;
}

function dent_exams_answer_sheet_build_meta(
    array $options,
    string $markedRaw,
    ?int $markedIndex,
    string $suggestedRaw,
    ?int $suggestedIndex,
    string $referenceRaw
): array {
    $meta = [];

    if (trim($markedRaw) !== '') {
        $tone = 'neutral';
        if ($markedIndex === null) {
            $tone = 'warning';
        } elseif ($suggestedIndex !== null && $markedIndex !== $suggestedIndex) {
            $tone = 'warning';
        }

        $meta[] = [
            'label' => 'گزینه علامت‌خورده در فایل',
            'value' => dent_exams_answer_sheet_format_selection_display($markedRaw, $markedIndex, $options),
            'tone' => $tone,
        ];
    }

    if (trim($suggestedRaw) !== '') {
        $meta[] = [
            'label' => 'پاسخ پیشنهادی',
            'value' => dent_exams_answer_sheet_format_selection_display($suggestedRaw, $suggestedIndex, $options),
            'tone' => $suggestedIndex === null ? 'warning' : 'success',
        ];
    }

    if (trim($referenceRaw) !== '') {
        $meta[] = [
            'label' => 'منبع / جزوه',
            'value' => dent_exams_text_source_restore_digits(trim($referenceRaw)),
            'tone' => 'accent',
            'wide' => true,
        ];
    }

    return $meta;
}

function dent_exams_answer_sheet_extract_option_index(string $raw): ?int
{
    $trimmed = trim(dent_exams_text_source_normalize_digits($raw));
    if ($trimmed === '' || preg_match('/نامشخص|قابل\s+مشاهده\s+نیست|بدون\s+تصویر|نیازمند\s+تصویر/u', $trimmed) === 1) {
        return null;
    }

    if (preg_match('/(?:گزینه\s*)?([0-9]+)/u', $trimmed, $matches) !== 1) {
        return null;
    }

    $optionNumber = max(0, (int) ($matches[1] ?? 0));
    if ($optionNumber < 1 || $optionNumber > 4) {
        return null;
    }

    return $optionNumber - 1;
}

function dent_exams_answer_sheet_format_selection_display(string $raw, ?int $index, array $options): string
{
    $trimmed = dent_exams_text_source_restore_digits(trim($raw));
    if ($trimmed === '' || $index === null) {
        return $trimmed;
    }

    $remainder = trim((string) (preg_replace('/^(?:گزینه\s*)?[0-9۰-۹]+(?:\s*[\)\.\-:|]\s*)?/u', '', $trimmed) ?? ''));
    if ($remainder !== '' && preg_match('/[آ-یA-Za-z]/u', $remainder) === 1 && preg_match('/^(?:مطابق|اختلاف|قابل\s+مشاهده|نامشخص|در\s+فایل)/u', $remainder) !== 1) {
        return $trimmed;
    }

    $display = 'گزینه ' . dent_exams_text_source_restore_digits((string) ($index + 1));
    if (isset($options[$index]) && trim((string) $options[$index]) !== '') {
        $display .= ': ' . trim((string) $options[$index]);
    }

    if ($remainder !== '') {
        $display .= ' — ' . $remainder;
    }

    return $display;
}
