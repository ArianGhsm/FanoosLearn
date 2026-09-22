<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Content\QuestionBankRow;
use RuntimeException;

/**
 * The rules for which question-bank rows become questions. Both importers read
 * through QuestionBankRow, so this is the one place those rules are checked.
 *
 * The case this suite was written for is the image rule. 1,350 of the 40,278
 * questions in the first medical banks show their stem or options as an image
 * -- "which lesion is this?" -- and the original importer did not look, so it
 * would have published them as questions with nothing to look at and a right
 * answer no student could reach.
 */
final class QuestionBankRowTest
{
    private int $assertions = 0;

    public function run(): int
    {
        $this->assertAValidRowBecomesAQuestion();
        $this->assertEveryUnscorableShapeIsSkippedWithItsReason();
        $this->assertImageDependentQuestionsAreSkipped();
        $this->assertBlankOptionsAreDroppedWithoutMovingTheAnswer();
        $this->assertExamTitlesCarrySubjectYearAndGroup();
        $this->assertCourseCodesAreStableAsciiAndStageSpecific();

        return $this->assertions;
    }

    /** @return array<string, mixed> */
    private function row(array $overrides = []): array
    {
        return array_replace([
            'id' => '0123abcd-4567-89ef-0123-456789abcdef',
            'question' => 'مرد ۴۵ ساله با درد اپی‌گاستر. محتمل‌ترین تشخیص؟',
            'question_type' => 'multiple_choice',
            'answer_status' => 'normal',
            'answer_key_status' => 'valid',
            'options' => [
                ['id' => 'A', 'text' => 'الف) زخم پپتیک'],
                ['id' => 'B', 'text' => 'ب) پانکراتیت مزمن'],
                ['id' => 'C', 'text' => 'ج) کارسینوم پانکراس'],
            ],
            'correct_option_ids' => ['C'],
            'explanation' => 'کاهش وزن به بدخیمی اشاره دارد.',
            'chapter_assignments' => [['chapterId' => 'x', 'title' => 'بیماری‌های پانکراس']],
            'question_image_path' => null,
            'question_image_local' => null,
            'option_image_paths' => [],
            'option_image_locals' => [],
        ], $overrides);
    }

    private function assertAValidRowBecomesAQuestion(): void
    {
        $mapped = QuestionBankRow::map($this->row());
        $this->assert(isset($mapped['question']), 'A valid row was skipped.');
        $question = $mapped['question'];
        $this->assert($question['id'] === 'q0123abcd456789ef0123456789abcdef', 'Question id was not derived from the row id.');
        $this->assert($question['answer'] === 2, 'The answer index does not point at the correct option.');
        $this->assert($question['choices'][1] === 'پانکراتیت مزمن', 'The option letter prefix was not stripped.');
        $this->assert($question['topic'] === 'بیماری‌های پانکراس', 'The chapter did not become the topic.');
        $this->assert(($question['explanation'] ?? null) === 'کاهش وزن به بدخیمی اشاره دارد.', 'The explanation was dropped.');
    }

    private function assertEveryUnscorableShapeIsSkippedWithItsReason(): void
    {
        $cases = [
            QuestionBankRow::SKIP_NOT_MULTIPLE_CHOICE => ['question_type' => 'descriptive'],
            QuestionBankRow::SKIP_DELETED => ['answer_status' => 'deleted'],
            QuestionBankRow::SKIP_NO_ANSWER_KEY => ['answer_key_status' => 'missing'],
            QuestionBankRow::SKIP_TOO_FEW_OPTIONS => ['options' => [['id' => 'A', 'text' => 'تنها گزینه']], 'correct_option_ids' => ['A']],
            // Two keys is as unscorable as none against a single-choice model.
            QuestionBankRow::SKIP_AMBIGUOUS_ANSWER => ['correct_option_ids' => ['A', 'B']],
        ];
        foreach ($cases as $reason => $overrides) {
            $this->assert(QuestionBankRow::map($this->row($overrides)) === ['skip' => $reason], "Expected skip reason {$reason}.");
        }
        // A key that names an option that does not exist.
        $this->assert(
            QuestionBankRow::map($this->row(['correct_option_ids' => ['Z']])) === ['skip' => QuestionBankRow::SKIP_AMBIGUOUS_ANSWER],
            'An answer key pointing at a missing option was accepted.',
        );
    }

    private function assertImageDependentQuestionsAreSkipped(): void
    {
        foreach ([
            'a stem image path' => ['question_image_path' => 'images/q/1.jpg'],
            'a stem image, local copy only' => ['question_image_local' => 'assets/1.jpg'],
            'one option image among text options' => ['option_image_paths' => [null, 'images/o/2.jpg', null]],
            'an option image, local copy only' => ['option_image_locals' => ['', '', 'assets/o/3.webp']],
        ] as $label => $overrides) {
            $this->assert(
                QuestionBankRow::map($this->row($overrides)) === ['skip' => QuestionBankRow::SKIP_NEEDS_IMAGE],
                "A question with {$label} was imported without it.",
            );
        }
        // Empty image fields are the normal case and must not trip the rule.
        $this->assert(
            isset(QuestionBankRow::map($this->row(['question_image_path' => '', 'option_image_paths' => [null, '', '  ']]))['question']),
            'Empty image fields were treated as an image.',
        );
    }

    /**
     * The export carries blank options: a trailing empty slot the extractor
     * left behind, or options that are nothing but their letter. ExamService
     * refuses an empty choice and, with it, the whole assessment -- which in
     * the first import cost four entire past exams for four bad rows.
     */
    private function assertBlankOptionsAreDroppedWithoutMovingTheAnswer(): void
    {
        $trailing = QuestionBankRow::map($this->row([
            'options' => [
                ['id' => 'A', 'text' => 'الف) یک'], ['id' => 'B', 'text' => 'ب) دو'],
                ['id' => 'C', 'text' => 'ج) سه'], ['id' => 'E', 'text' => ''],
            ],
            'correct_option_ids' => ['C'],
        ]));
        $this->assert(($trailing['question']['choices'] ?? null) === ['یک', 'دو', 'سه'], 'A trailing blank option was kept.');
        $this->assert(($trailing['question']['answer'] ?? null) === 2, 'Dropping a trailing blank moved the answer.');

        // A blank *before* the answer shifts its position: the index must be
        // recomputed, or the review marks the wrong option correct.
        $leading = QuestionBankRow::map($this->row([
            'options' => [
                ['id' => 'A', 'text' => 'الف)'], ['id' => 'B', 'text' => 'ب) دو'],
                ['id' => 'C', 'text' => 'ج) سه'], ['id' => 'D', 'text' => 'د) چهار'],
            ],
            'correct_option_ids' => ['C'],
        ]));
        $this->assert(($leading['question']['choices'] ?? null) === ['دو', 'سه', 'چهار'], 'A letter-only option was kept.');
        $this->assert(
            ($leading['question']['choices'][$leading['question']['answer'] ?? -1] ?? null) === 'سه',
            'After dropping a blank, the answer index no longer points at the correct text.',
        );

        // Every option is only its letter: the real choices were never extracted.
        $this->assert(
            QuestionBankRow::map($this->row([
                'options' => [['id' => 'A', 'text' => 'الف)'], ['id' => 'B', 'text' => 'ب)'], ['id' => 'C', 'text' => 'ج)']],
                'correct_option_ids' => ['A'],
            ])) === ['skip' => QuestionBankRow::SKIP_BLANK_ANSWER],
            'A question whose options are only letters was imported.',
        );
        // The correct option alone is blank.
        $this->assert(
            QuestionBankRow::map($this->row([
                'options' => [['id' => 'A', 'text' => 'الف) یک'], ['id' => 'B', 'text' => ''], ['id' => 'C', 'text' => 'ج) سه']],
                'correct_option_ids' => ['B'],
            ])) === ['skip' => QuestionBankRow::SKIP_BLANK_ANSWER],
            'A question whose correct option is blank was imported.',
        );
    }

    private function assertExamTitlesCarrySubjectYearAndGroup(): void
    {
        $this->assert(
            QuestionBankRow::examTitle(['subject' => 'ارتوپدی', 'academic_year' => '1395', 'entry_group' => 'A']) === 'ارتوپدی · ورودی ۱۳۹۵ · گروه A',
            'A rotation-group exam was titled wrongly.',
        );
        $this->assert(
            QuestionBankRow::examTitle(['subject' => 'ارتوپدی', 'academic_year' => '1391', 'entry_group' => 'آذر ۹۵']) === 'ارتوپدی · ورودی ۱۳۹۱ · آذر ۹۵',
            'A sitting-date exam was titled wrongly.',
        );
        $this->assert(
            QuestionBankRow::examTitle(['subject' => 'اخلاق', 'academic_year' => 'نامعلوم', 'entry_group' => 'unknown']) === 'اخلاق',
            'Unknown year and group should be left out, not printed.',
        );
        $this->assert(
            QuestionBankRow::examTitle(['subject' => 'زنان', 'academic_year' => 'متفرقه', 'entry_group' => '2']) === 'زنان · متفرقه · گروه ۲',
            'A non-numeric year label was lost.',
        );
    }

    private function assertCourseCodesAreStableAsciiAndStageSpecific(): void
    {
        $code = QuestionBankRow::courseCode('استاجری', 'ارتوپدی');
        $this->assert($code === QuestionBankRow::courseCode('استاجری', 'ارتوپدی'), 'Course code is not stable -- a re-import would duplicate courses.');
        $this->assert(preg_match('/^[a-z0-9-]{1,64}$/', $code) === 1, 'Course code is not ASCII; academic_courses.course_code is an ASCII column.');
        $this->assert($code !== QuestionBankRow::courseCode('فیزیوپات', 'ارتوپدی'), 'Two stages sharing a subject collapsed into one course.');
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
