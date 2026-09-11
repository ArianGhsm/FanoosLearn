<?php
declare(strict_types=1);

require_once __DIR__ . '/text_source_parser.php';

function dent_exams_restorative_theory1_final_practice_parse_file(string $path): array
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
    $answerMap = dent_exams_restorative_theory1_final_practice_collect_answers($answerText);
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

function dent_exams_restorative_theory1_final_practice_collect_answers(string $text): array
{
    $lines = preg_split('/\R/u', $text) ?: [];
    $answers = [];
    $currentNumber = 0;
    $currentCorrectIndex = null;
    $currentExplanation = [];
    $awaitingCorrectOption = false;

    $flush = static function () use (&$answers, &$currentNumber, &$currentCorrectIndex, &$currentExplanation, &$awaitingCorrectOption): void {
        if ($currentNumber > 0 && $currentCorrectIndex !== null) {
            $answers[$currentNumber] = [
                'correctIndex' => $currentCorrectIndex,
                'explanation' => dent_exams_text_source_explanation_lines($currentExplanation),
            ];
        }

        $currentNumber = 0;
        $currentCorrectIndex = null;
        $currentExplanation = [];
        $awaitingCorrectOption = false;
    };

    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '' || preg_match('/^[\x{2500}-\x{257f}\-=_* ]+$/u', $trimmed) === 1) {
            continue;
        }

        $questionStart = dent_exams_text_source_match_question_start($trimmed);
        if ($questionStart !== null) {
            $flush();
            $currentNumber = max(0, (int) ($questionStart['number'] ?? 0));
            continue;
        }

        if ($currentNumber <= 0) {
            continue;
        }

        if (preg_match('/^پاسخ\s*(?:درست|صحیح)\s*:?\s*(?:گزینه\s*)?(الف|ب|ج|د|a|b|c|d)?\s*$/iu', $trimmed, $matches) === 1) {
            $letter = trim((string) ($matches[1] ?? ''));
            if ($letter !== '') {
                $currentCorrectIndex = dent_exams_text_source_option_letter_to_index($letter);
                $awaitingCorrectOption = false;
            } else {
                $awaitingCorrectOption = true;
            }
            continue;
        }

        if ($awaitingCorrectOption && preg_match('/^(?:گزینه\s*)?(الف|ب|ج|د|a|b|c|d)\s*$/iu', $trimmed, $matches) === 1) {
            $currentCorrectIndex = dent_exams_text_source_option_letter_to_index((string) ($matches[1] ?? ''));
            $awaitingCorrectOption = false;
            continue;
        }

        if ($currentCorrectIndex !== null) {
            $currentExplanation[] = $trimmed;
        }
    }

    $flush();

    return $answers;
}

function dent_exams_restorative_theory1_final_practice_course(): array
{
    static $course = null;
    if (is_array($course)) {
        return $course;
    }

    $course = dent_exams_text_source_build_course([
        'courseSlug' => 'restorative-theory-1-final-practice',
        'title' => 'آزمون تمرینی پایان‌ترم ترمیمی نظری ۱',
        'shortTitle' => 'پایان‌ترم ترمیمی نظری ۱',
        'termLabel' => 'ترم ۶',
        'dataDir' => __DIR__ . '/data/restorative_theory1_final_practice',
        'paymentAmount' => 300000,
        'examDefinitions' => [
            [
                'slug' => '9',
                'label' => 'جلسه ۹',
                'topic' => 'ترمیم‌های همرنگ خلفی',
                'patterns' => ['session9*.txt'],
                'parser' => 'dent_exams_restorative_theory1_final_practice_parse_file',
            ],
            [
                'slug' => '10',
                'label' => 'جلسه ۱۰',
                'topic' => 'آشنایی با سمان‌های رزینی',
                'patterns' => ['session10*.txt'],
                'parser' => 'dent_exams_restorative_theory1_final_practice_parse_file',
            ],
            [
                'slug' => '11',
                'label' => 'جلسه ۱۱',
                'topic' => 'دندانپزشکی سالمندان',
                'patterns' => ['session11*.txt'],
                'parser' => 'dent_exams_restorative_theory1_final_practice_parse_file',
            ],
            [
                'slug' => '12',
                'label' => 'جلسه ۱۲',
                'topic' => 'ضایعات سرویکالی',
                'patterns' => ['session12*.txt'],
                'parser' => 'dent_exams_restorative_theory1_final_practice_parse_file',
            ],
            [
                'slug' => '13',
                'label' => 'جلسه ۱۳',
                'topic' => 'کیورینگ مواد دندانی و انواع دستگاه‌های لایت کیور',
                'patterns' => ['session13*.txt'],
                'parser' => 'dent_exams_restorative_theory1_final_practice_parse_file',
            ],
            [
                'slug' => '14',
                'label' => 'جلسه ۱۴',
                'topic' => 'تماس‌های پروگزیمالی',
                'patterns' => ['session14*.txt'],
                'parser' => 'dent_exams_restorative_theory1_final_practice_parse_file',
            ],
            [
                'slug' => '15',
                'label' => 'جلسه ۱۵',
                'topic' => 'اکلوژن',
                'patterns' => ['session15_occlusion*.txt'],
                'parser' => 'dent_exams_restorative_theory1_final_practice_parse_file',
            ],
            [
                'slug' => '15-article',
                'label' => 'مقاله جلسه ۱۵',
                'topic' => 'Canine Rise',
                'patterns' => ['session15_article*.txt'],
                'parser' => 'dent_exams_restorative_theory1_final_practice_parse_file',
            ],
        ],
    ]);

    $course['addedAt'] = '2026-07-24T03:50:01+03:30';
    foreach (($course['exams'] ?? []) as $index => $exam) {
        if (is_array($exam) && empty($exam['addedAt'])) {
            $course['exams'][$index]['addedAt'] = $course['addedAt'];
        }
    }

    return $course;
}
