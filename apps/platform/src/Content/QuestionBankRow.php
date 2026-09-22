<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

/**
 * Turns one row of the owner's question-bank export into a FANOOS question,
 * or says why it cannot become one.
 *
 * Both importers -- the single-assessment one and the bulk one that builds
 * an assessment per past exam -- read their rows through here, so the rules
 * for what counts as an honest, scorable question exist exactly once. They
 * used to live inline in the single importer, where the bulk importer could
 * only have copied them, and two copies of a validation rule drift.
 */
final class QuestionBankRow
{
    public const SKIP_NOT_MULTIPLE_CHOICE = 'not_multiple_choice';
    public const SKIP_DELETED = 'deleted';
    public const SKIP_NO_ANSWER_KEY = 'no_answer_key';
    public const SKIP_TOO_FEW_OPTIONS = 'too_few_options';
    public const SKIP_AMBIGUOUS_ANSWER = 'ambiguous_answer';
    public const SKIP_NEEDS_IMAGE = 'needs_image';
    public const SKIP_BLANK_ANSWER = 'blank_answer';

    private const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    /**
     * @param array<string, mixed> $row
     * @return array{question: array<string, mixed>}|array{skip: string}
     */
    public static function map(array $row): array
    {
        if ((string) ($row['question_type'] ?? '') !== 'multiple_choice') {
            return ['skip' => self::SKIP_NOT_MULTIPLE_CHOICE];
        }
        if ((string) ($row['answer_status'] ?? '') === 'deleted') {
            return ['skip' => self::SKIP_DELETED];
        }
        if ((string) ($row['answer_key_status'] ?? '') !== 'valid') {
            return ['skip' => self::SKIP_NO_ANSWER_KEY];
        }

        // A question whose stem or options are an image cannot be shown:
        // FANOOS has no image-bearing question model yet. Imported without it,
        // "which lesion is shown?" becomes a question with nothing to look at
        // and a right answer the student has no way to reach -- they would be
        // marked wrong for a question that was never really put to them. The
        // first importer did not check this, which is exactly how that happens.
        if (self::needsImage($row)) {
            return ['skip' => self::SKIP_NEEDS_IMAGE];
        }

        $correctIds = array_values(array_filter((array) ($row['correct_option_ids'] ?? [])));
        if (count($correctIds) !== 1) {
            // No answer, or several: neither can be scored against a
            // single-choice model without inventing a verdict.
            return ['skip' => self::SKIP_AMBIGUOUS_ANSWER];
        }
        $correctId = (string) $correctIds[0];

        $options = array_values(array_filter((array) ($row['options'] ?? []), 'is_array'));
        $optionIds = array_map(static fn (array $option): string => (string) ($option['id'] ?? ''), $options);
        if (!in_array($correctId, $optionIds, true)) {
            return ['skip' => self::SKIP_AMBIGUOUS_ANSWER];
        }

        // Options whose text is empty once the letter prefix is gone. The
        // export has two kinds: a trailing blank slot the extractor left
        // behind (an empty «ه» on a four-option question), which is safe to
        // drop; and a question whose options are nothing but their letters,
        // because the real choices were never extracted. ExamService refuses
        // an empty choice -- and refuses the whole assessment with it, so one
        // such row used to cost an entire past exam.
        $kept = [];
        foreach ($options as $option) {
            $text = self::stripLeadingLetter((string) ($option['text'] ?? ''));
            if ($text === '') {
                if ((string) ($option['id'] ?? '') === $correctId) {
                    // The right answer has no text: there is nothing for the
                    // student to choose.
                    return ['skip' => self::SKIP_BLANK_ANSWER];
                }
                continue;
            }
            $kept[] = ['id' => (string) ($option['id'] ?? ''), 'text' => $text];
        }
        if (count($kept) < 2) {
            return ['skip' => self::SKIP_TOO_FEW_OPTIONS];
        }
        // Recomputed after dropping blanks: a blank before the answer shifts
        // its position, and a stale index would mark the wrong option right.
        $answerIndex = array_search($correctId, array_column($kept, 'id'), true);

        $question = [
            'id' => 'q' . substr(str_replace('-', '', (string) ($row['id'] ?? '')), 0, 32),
            'prompt' => trim((string) ($row['question'] ?? '')),
            'choices' => array_column($kept, 'text'),
            'answer' => (int) $answerIndex,
        ];

        $explanation = trim((string) ($row['explanation'] ?? '')) ?: trim((string) ($row['ai_explanation_md'] ?? ''));
        if ($explanation !== '') {
            $question['explanation'] = mb_substr($explanation, 0, 4000);
        }

        // The chapter becomes the question's topic, which the runner shows as
        // its مبحث row -- a past exam mixes chapters, so this is the one place
        // a student sees which part of the course a question is about.
        $chapter = self::firstChapter($row);
        if ($chapter !== '') {
            $question['topic'] = $chapter;
        }

        return ['question' => $question];
    }

    /** @param array<string, mixed> $row */
    public static function needsImage(array $row): bool
    {
        foreach (['question_image_path', 'question_image_local'] as $key) {
            if (trim((string) ($row[$key] ?? '')) !== '') {
                return true;
            }
        }
        foreach (['option_image_paths', 'option_image_locals'] as $key) {
            foreach ((array) ($row[$key] ?? []) as $value) {
                if (is_string($value) && trim($value) !== '') {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param array<string, mixed> $row */
    public static function firstChapter(array $row): string
    {
        foreach ((array) ($row['chapter_assignments'] ?? []) as $entry) {
            if (is_array($entry) && trim((string) ($entry['title'] ?? '')) !== '') {
                return trim((string) $entry['title']);
            }
        }

        return '';
    }

    /**
     * The title a past exam is listed under: subject, entry year, group.
     *
     * An exam in the export is identified only by those three together --
     * ارتوپدی alone has dozens -- so all three are in the name. The year is
     * written in Persian digits here rather than left to the page, because a
     * title is also what an owner reads in audit logs and the bot.
     *
     * @param array<string, mixed> $context the row's exam_context
     */
    public static function examTitle(array $context): string
    {
        $subject = trim((string) ($context['subject'] ?? '')) ?: 'بدون درس';
        $parts = [$subject];

        $year = trim((string) ($context['academic_year'] ?? ''));
        if (preg_match('/^\d{4}$/', $year) === 1) {
            $parts[] = 'ورودی ' . self::persianDigits($year);
        } elseif ($year !== '' && $year !== 'نامعلوم') {
            $parts[] = $year;
        }

        $group = trim((string) ($context['entry_group'] ?? ''));
        if ($group !== '' && strtolower($group) !== 'unknown') {
            // A bare letter or digit is a rotation group; anything with a
            // space in it is already a sitting date such as «آذر ۹۵».
            $parts[] = preg_match('/^[A-Za-z0-9]{1,4}$/', $group) === 1
                ? 'گروه ' . self::persianDigits($group)
                : self::persianDigits($group);
        }

        return mb_substr(implode(' · ', $parts), 0, 200);
    }

    /**
     * An ASCII course code for a stage and subject. academic_courses.course_code
     * is an ASCII column and subjects are Persian, so the code is a digest of
     * the pair: stable across re-imports, which is what makes the course
     * lookup idempotent, and distinct between stages that share a subject
     * name.
     */
    public static function courseCode(string $stage, string $subject): string
    {
        return 'bank-' . substr(hash('sha256', trim($stage) . '|' . trim($subject)), 0, 16);
    }

    /**
     * Option text often already carries its own letter ("الف) ..."), and the
     * interface supplies the letter itself. Left in, every choice renders
     * with two letters.
     */
    public static function stripLeadingLetter(string $text): string
    {
        return trim(preg_replace('/^\s*(?:الف|ب|ج|د|ه|[A-Ea-e]|[۱-۵1-5])\s*[).\-]\s*/u', '', $text) ?? $text);
    }

    private static function persianDigits(string $value): string
    {
        return str_replace(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], self::PERSIAN_DIGITS, $value);
    }
}
