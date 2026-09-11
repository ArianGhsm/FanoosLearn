<?php
declare(strict_types=1);

require_once __DIR__ . '/text_source_parser.php';

function dent_exams_essay_source_parse_file(string $path): array
{
    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        return ['topic' => '', 'questions' => []];
    }

    $text = dent_exams_text_source_normalize_text($raw);

    return [
        'topic' => dent_exams_text_source_extract_topic($text),
        'questions' => dent_exams_essay_source_collect_questions($text),
    ];
}

function dent_exams_essay_source_collect_questions(string $text): array
{
    $blocks = preg_split('/^-{10,}\s*$/mu', $text) ?: [];
    $questions = [];

    foreach ($blocks as $block) {
        $question = dent_exams_essay_source_parse_block($block);
        if ($question !== null) {
            $questions[] = $question;
        }
    }

    return $questions;
}

function dent_exams_essay_source_parse_block(string $block): ?array
{
    $lines = preg_split('/\R/u', $block) ?: [];
    $section = 'none';
    $questionLines = [];
    $answerLines = [];
    $detailLines = [];
    $referenceLines = [];

    foreach ($lines as $line) {
        $trimmed = trim((string) $line);

        if ($section === 'none') {
            if (preg_match('/^\s*(?:سؤال|سوال)\s*[0-9۰-۹]+\s*[\.\)]\s*(.*)$/u', $trimmed, $matches) === 1) {
                $section = 'question';
                if (trim((string) $matches[1]) !== '') {
                    $questionLines[] = trim((string) $matches[1]);
                }
            }
            continue;
        }

        if (preg_match('/^پاسخ\s*تشریحی\s*:\s*(.*)$/u', $trimmed, $matches) === 1) {
            $section = 'detail';
            if (trim((string) $matches[1]) !== '') {
                $detailLines[] = trim((string) $matches[1]);
            }
            continue;
        }

        if (preg_match('/^پاسخ\s*:\s*(.*)$/u', $trimmed, $matches) === 1) {
            $section = 'answer';
            if (trim((string) $matches[1]) !== '') {
                $answerLines[] = trim((string) $matches[1]);
            }
            continue;
        }

        if (preg_match('/^علت رد گزینه‌های غلط/u', $trimmed) === 1) {
            $section = 'skip';
            continue;
        }

        if (preg_match('/^رفرنس\s*:\s*(.*)$/u', $trimmed, $matches) === 1) {
            $section = 'reference';
            if (trim((string) $matches[1]) !== '') {
                $referenceLines[] = trim((string) $matches[1]);
            }
            continue;
        }

        switch ($section) {
            case 'question':
                if ($trimmed !== '') {
                    $questionLines[] = $trimmed;
                }
                break;
            case 'answer':
                $answerLines[] = $trimmed;
                break;
            case 'detail':
                $detailLines[] = $trimmed;
                break;
            case 'reference':
                if ($trimmed !== '') {
                    $referenceLines[] = $trimmed;
                }
                break;
            default:
                break;
        }
    }

    $question = trim(implode(' ', array_filter($questionLines, static fn (string $l): bool => $l !== '')));
    if ($question === '') {
        return null;
    }

    return [
        'question' => dent_exams_text_source_restore_digits($question),
        'options' => [],
        'correctIndex' => null,
        'answerSummary' => dent_exams_essay_source_join_paragraph_lines($answerLines),
        'answerDetail' => dent_exams_essay_source_join_paragraph_lines($detailLines),
        'reference' => dent_exams_text_source_restore_digits(trim(implode(' ', $referenceLines))),
    ];
}

function dent_exams_essay_source_join_paragraph_lines(array $lines): string
{
    $result = [];
    $previousBlank = true;

    foreach ($lines as $line) {
        $isBlank = trim($line) === '';
        if ($isBlank && $previousBlank) {
            continue;
        }
        $result[] = $line;
        $previousBlank = $isBlank;
    }

    while ($result !== [] && trim((string) end($result)) === '') {
        array_pop($result);
    }

    return dent_exams_text_source_restore_digits(trim(implode("\n", $result)));
}
