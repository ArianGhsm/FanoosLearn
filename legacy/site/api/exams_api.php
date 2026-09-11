<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';
require_once __DIR__ . '/dentistry_curriculum.php';
require_once __DIR__ . '/exams_store.php';
require_once __DIR__ . '/payments_store.php';
require_once __DIR__ . '/exams_modules.php';

final class DentExamsApiException extends RuntimeException
{
    private int $statusCode;
    private array $payload;

    public function __construct(string $message, int $statusCode = 422, array $payload = [])
    {
        parent::__construct($message);
        $this->statusCode = max(400, min(599, $statusCode));
        $this->payload = $payload;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function payload(): array
    {
        return $this->payload;
    }
}

function dent_exams_api_apply_runtime_exam_override(
    array $exam,
    string $catalogKey = '',
    string $courseSlug = '',
    bool $hydrateQuestions = false
): array
{
    if (
        $hydrateQuestions
        && !is_array($exam['questions'] ?? null)
        && $catalogKey !== ''
        && $courseSlug !== ''
    ) {
        dent_exams_bootstrap_runtime_modules();

        $runtimeExam = null;
        $examSlug = (string) ($exam['slug'] ?? '');

        if (function_exists('dent_exams_complete_foundations_theory_midterm_practice_runtime_exam_payload')) {
            $runtimeExam = dent_exams_complete_foundations_theory_midterm_practice_runtime_exam_payload(
                $catalogKey,
                $courseSlug,
                $examSlug
            );
        }

        if (!is_array($runtimeExam) && function_exists('dent_exams_complete_prosthodontics_midterm_sample_runtime_exam_payload')) {
            $runtimeExam = dent_exams_complete_prosthodontics_midterm_sample_runtime_exam_payload(
                $catalogKey,
                $courseSlug,
                $examSlug
            );
        }

        if (!is_array($runtimeExam) && function_exists('dent_exams_diagnostics2_term6_runtime_exam_payload')) {
            $runtimeExam = dent_exams_diagnostics2_term6_runtime_exam_payload(
                $catalogKey,
                $courseSlug,
                $examSlug
            );
        }

        if (!is_array($runtimeExam) && function_exists('dent_exams_diagnostics2_final_sample_runtime_exam_payload')) {
            $runtimeExam = dent_exams_diagnostics2_final_sample_runtime_exam_payload(
                $catalogKey,
                $courseSlug,
                $examSlug
            );
        }

        if (!is_array($runtimeExam) && function_exists('dent_exams_term6_reference_runtime_exam_payload')) {
            $runtimeExam = dent_exams_term6_reference_runtime_exam_payload(
                $catalogKey,
                $courseSlug,
                $examSlug
            );
        }

        if (is_array($runtimeExam)) {
            $exam = array_replace($exam, $runtimeExam);
        }
    }

    $exam['questionCount'] = dent_exams_api_resolve_exam_question_count($exam);
    return $exam;
}

function dent_exams_api_apply_runtime_course_override(array $course): array
{
    if (!is_array($course['exams'] ?? null)) {
        return $course;
    }

    $nextExams = [];
    foreach ($course['exams'] as $exam) {
        $nextExams[] = is_array($exam)
            ? dent_exams_api_apply_runtime_exam_override(
                $exam,
                '',
                (string) ($course['slug'] ?? ''),
                false
            )
            : $exam;
    }
    $course['exams'] = $nextExams;

    return $course;
}

function dent_exams_api_resolve_exam_question_count(array $exam): int
{
    $questions = $exam['questions'] ?? null;
    if (is_array($questions)) {
        return count($questions);
    }

    return max(0, (int) ($exam['questionCount'] ?? 0));
}

function dent_exams_api_exam_has_questions(array $exam): bool
{
    return dent_exams_api_resolve_exam_question_count($exam) > 0;
}

function dent_exams_api_exam_is_attemptable(array $exam): bool
{
    if (dent_exams_api_exam_has_questions($exam)) {
        return true;
    }

    if (array_key_exists('attemptable', $exam)) {
        return (bool) $exam['attemptable'];
    }

    $questions = $exam['questions'] ?? null;
    return is_array($questions) && count($questions) > 0;
}

function dent_exams_api_exam_counts_toward_stats(array $exam): bool
{
    if (dent_exams_api_exam_has_questions($exam)) {
        return true;
    }

    if (!array_key_exists('countsTowardStats', $exam)) {
        return true;
    }

    return (bool) $exam['countsTowardStats'];
}

function dent_exams_api_exam_is_coming_soon(array $exam): bool
{
    return (bool) ($exam['comingSoon'] ?? false) && !dent_exams_api_exam_has_questions($exam);
}

function dent_exams_api_course_is_catalog_visible(array $course): bool
{
    if (!array_key_exists('visibleOnCatalog', $course)) {
        return true;
    }

    return (bool) $course['visibleOnCatalog'];
}

function dent_exams_api_payment_binding_slug(string $catalogKey, string $courseSlug, ?array $course = null): string
{
    $cleanCourseSlug = dent_exams_clean_course_slug($courseSlug);
    $resolvedCourse = is_array($course) ? $course : dent_exams_course($catalogKey, $cleanCourseSlug);
    if (!is_array($resolvedCourse)) {
        return $cleanCourseSlug;
    }

    $bindingSlug = dent_exams_clean_course_slug((string) ($resolvedCourse['paymentGroupSlug'] ?? ''));
    if ($bindingSlug === '') {
        return $cleanCourseSlug;
    }

    return dent_exams_course($catalogKey, $bindingSlug) !== null
        ? $bindingSlug
        : $cleanCourseSlug;
}

function dent_exams_api_payment_binding_course(string $catalogKey, string $courseSlug, ?array $course = null): array
{
    $cleanCourseSlug = dent_exams_clean_course_slug($courseSlug);
    $resolvedCourse = is_array($course) ? $course : dent_exams_course($catalogKey, $cleanCourseSlug);
    if (!is_array($resolvedCourse)) {
        return [];
    }

    $bindingSlug = dent_exams_api_payment_binding_slug($catalogKey, $cleanCourseSlug, $resolvedCourse);
    if ($bindingSlug === '' || $bindingSlug === $cleanCourseSlug) {
        return $resolvedCourse;
    }

    $bindingCourse = dent_exams_course($catalogKey, $bindingSlug);
    return is_array($bindingCourse) ? $bindingCourse : $resolvedCourse;
}

function dent_exams_api_payment_group_version(array $course): int
{
    return max(0, (int) ($course['paymentGroupVersion'] ?? 0));
}

function dent_exams_api_payment_legacy_course_slugs(string $catalogKey, array $course): array
{
    $slugs = [];
    $seen = [];

    foreach ((is_array($course['paymentLegacyCourseSlugs'] ?? null) ? $course['paymentLegacyCourseSlugs'] : []) as $legacySlug) {
        $cleanSlug = dent_exams_clean_course_slug((string) $legacySlug);
        if ($cleanSlug === '' || isset($seen[$cleanSlug])) {
            continue;
        }
        if (dent_exams_course($catalogKey, $cleanSlug) === null) {
            continue;
        }

        $seen[$cleanSlug] = true;
        $slugs[] = $cleanSlug;
    }

    return $slugs;
}

function dent_exams_api_merge_discount_codes(array ...$lists): array
{
    $merged = [];
    foreach ($lists as $list) {
        foreach ($list as $code) {
            $merged[] = $code;
        }
    }

    return dent_exams_normalize_discount_codes($merged);
}

function dent_exams_api_course_curriculum_meta(string $courseSlug): ?array
{
    $unit = dent_dentistry_curriculum_find_unit_by_exam_course_slug($courseSlug);
    if ($unit === null) {
        return null;
    }

    $courseSlugs = array_values(array_filter(
        is_array($unit['examCourseSlugs'] ?? null) ? $unit['examCourseSlugs'] : [],
        static function ($value): bool {
            return is_string($value) && trim($value) !== '';
        }
    ));
    $finalExams = dent_dentistry_curriculum_final_exams($unit, dent_requested_cohort_key(), $courseSlug);

    return [
        'termNumber' => max(0, (int) ($unit['termNumber'] ?? 0)),
        'termLabel' => (string) ($unit['termLabel'] ?? ''),
        'categoryKey' => (string) ($unit['categoryKey'] ?? ''),
        'categoryTitle' => (string) ($unit['categoryTitle'] ?? ''),
        'unitKey' => (string) ($unit['key'] ?? ''),
        'unitTitle' => (string) ($unit['title'] ?? ''),
        'unitAliases' => array_values(array_filter(
            is_array($unit['aliases'] ?? null) ? $unit['aliases'] : [],
            static function ($value): bool {
                return is_string($value) && trim($value) !== '';
            }
        )),
        'unitCourseCount' => count($courseSlugs),
        'unitCourseSlugs' => $courseSlugs,
        'unitHasMultipleCollections' => count($courseSlugs) > 1,
        'finalExams' => $finalExams,
        'finalExam' => is_array($finalExams[0] ?? null) ? $finalExams[0] : null,
    ];
}

function dent_exams_api_collection_stats_aggregate(array $collections): array
{
    $courseCount = 0;
    $examCount = 0;
    $questionCount = 0;
    $completedAssessmentCount = 0;
    $flaggedQuestionsCount = 0;
    $weightedViewerPercent = 0.0;
    $viewerPercentWeight = 0;

    foreach ($collections as $collection) {
        if (!is_array($collection)) {
            continue;
        }

        $stats = is_array($collection['stats'] ?? null) ? $collection['stats'] : [];
        $completed = max(0, (int) ($stats['completedAssessmentCount'] ?? 0));

        $courseCount++;
        $examCount += max(0, (int) ($stats['examCount'] ?? 0));
        $questionCount += max(0, (int) ($stats['questionCount'] ?? 0));
        $completedAssessmentCount += $completed;
        $flaggedQuestionsCount += max(0, (int) ($stats['flaggedQuestionsCount'] ?? 0));

        if (array_key_exists('viewerAveragePercent', $stats) && $stats['viewerAveragePercent'] !== null) {
            $weight = $completed > 0 ? $completed : 1;
            $weightedViewerPercent += dent_exams_normalize_percent($stats['viewerAveragePercent']) * $weight;
            $viewerPercentWeight += $weight;
        }
    }

    return [
        'courseCount' => $courseCount,
        'examCount' => $examCount,
        'questionCount' => $questionCount,
        'completedAssessmentCount' => $completedAssessmentCount,
        'flaggedQuestionsCount' => $flaggedQuestionsCount,
        'viewerAveragePercent' => $viewerPercentWeight > 0
            ? round($weightedViewerPercent / $viewerPercentWeight, 1)
            : null,
    ];
}

function dent_exams_api_curriculum_unit_payload(array $unit, array $coursePayloadLookup): array
{
    $collections = [];
    $seenCourseSlugs = [];

    foreach ((is_array($unit['examCourseSlugs'] ?? null) ? $unit['examCourseSlugs'] : []) as $courseSlug) {
        $cleanSlug = dent_exams_clean_course_slug((string) $courseSlug);
        if ($cleanSlug === '' || isset($seenCourseSlugs[$cleanSlug])) {
            continue;
        }

        $seenCourseSlugs[$cleanSlug] = true;
        if (is_array($coursePayloadLookup[$cleanSlug] ?? null)) {
            $collections[] = $coursePayloadLookup[$cleanSlug];
        }
    }

    $stats = dent_exams_api_collection_stats_aggregate($collections);
    $courseTitles = array_values(array_filter(array_map(static function (array $collection): string {
        return trim((string) ($collection['title'] ?? ''));
    }, $collections)));
    $collectionCount = count($collections);
    $statusKey = $collectionCount <= 0
        ? 'empty'
        : ($collectionCount === 1 ? 'available' : 'multi');
    $entryMode = $collectionCount <= 0
        ? 'none'
        : ($collectionCount === 1 ? 'direct' : 'collections');
    $finalExams = dent_dentistry_curriculum_final_exams($unit, dent_requested_cohort_key());

    if ($collectionCount <= 0) {
        $description = 'هنوز آزمونی برای این واحد ثبت نشده است.';
    } elseif ($collectionCount === 1) {
        $description = trim((string) ($collections[0]['cardDescription'] ?? ''));
        if ($description === '') {
            $description = 'آزمون‌های این واحد از همین مسیر در دسترس هستند.';
        }
    } elseif ($courseTitles) {
        $previewTitles = array_slice($courseTitles, 0, 2);
        $description = 'در این واحد فعلاً ' . $collectionCount . ' مجموعه آزمونی ثبت شده: '
            . implode('، ', $previewTitles)
            . (count($courseTitles) > count($previewTitles) ? ' و ...' : '') . '.';
    } else {
        $description = 'چند مجموعه آزمونی برای این واحد ثبت شده است.';
    }

    return [
        'key' => (string) ($unit['key'] ?? ''),
        'title' => (string) ($unit['title'] ?? ''),
        'aliases' => array_values(array_filter(
            is_array($unit['aliases'] ?? null) ? $unit['aliases'] : [],
            static function ($value): bool {
                return is_string($value) && trim($value) !== '';
            }
        )),
        'termNumber' => max(0, (int) ($unit['termNumber'] ?? 0)),
        'termLabel' => (string) ($unit['termLabel'] ?? ''),
        'categoryKey' => (string) ($unit['categoryKey'] ?? ''),
        'categoryTitle' => (string) ($unit['categoryTitle'] ?? ''),
        'statusKey' => $statusKey,
        'statusLabel' => $collectionCount <= 0
            ? 'بدون آزمون'
            : ($collectionCount === 1 ? 'دارای آزمون' : 'چند مجموعه'),
        'entryMode' => $entryMode,
        'entryLabel' => $collectionCount <= 0
            ? 'هنوز فعال نشده'
            : ($collectionCount === 1 ? 'مشاهده آزمون‌ها' : 'مشاهده مجموعه‌ها'),
        'entryHref' => $collectionCount === 1
            ? (string) ($collections[0]['path'] ?? '')
            : '',
        'description' => $description,
        'finalExams' => $finalExams,
        'collectionTitles' => $courseTitles,
        'stats' => $stats,
        'collections' => $collections,
    ];
}

function dent_exams_api_curriculum_payload(array $coursePayloadLookup): array
{
    $terms = [];
    $catalogStats = [
        'termCount' => 0,
        'availableTermCount' => 0,
        'unitCount' => 0,
        'availableUnitCount' => 0,
        'courseCount' => 0,
        'examCount' => 0,
        'questionCount' => 0,
        'completedAssessmentCount' => 0,
    ];

    foreach (dent_dentistry_curriculum_terms() as $term) {
        if (!is_array($term)) {
            continue;
        }

        $termCategories = [];
        $termStats = [
            'unitCount' => 0,
            'availableUnitCount' => 0,
            'courseCount' => 0,
            'examCount' => 0,
            'questionCount' => 0,
            'completedAssessmentCount' => 0,
        ];

        foreach ((is_array($term['categories'] ?? null) ? $term['categories'] : []) as $category) {
            if (!is_array($category)) {
                continue;
            }

            $units = [];
            $categoryStats = [
                'unitCount' => 0,
                'availableUnitCount' => 0,
                'courseCount' => 0,
                'examCount' => 0,
                'questionCount' => 0,
                'completedAssessmentCount' => 0,
            ];

            foreach ((is_array($category['units'] ?? null) ? $category['units'] : []) as $unit) {
                if (!is_array($unit)) {
                    continue;
                }

                $unitPayload = dent_exams_api_curriculum_unit_payload(
                    array_merge($unit, [
                        'termNumber' => max(0, (int) ($term['number'] ?? 0)),
                        'termLabel' => (string) ($term['label'] ?? ''),
                        'categoryKey' => (string) ($category['key'] ?? ''),
                        'categoryTitle' => (string) ($category['title'] ?? ''),
                    ]),
                    $coursePayloadLookup
                );
                $stats = is_array($unitPayload['stats'] ?? null) ? $unitPayload['stats'] : [];

                $categoryStats['unitCount']++;
                $categoryStats['availableUnitCount'] += $unitPayload['statusKey'] === 'empty' ? 0 : 1;
                $categoryStats['courseCount'] += max(0, (int) ($stats['courseCount'] ?? 0));
                $categoryStats['examCount'] += max(0, (int) ($stats['examCount'] ?? 0));
                $categoryStats['questionCount'] += max(0, (int) ($stats['questionCount'] ?? 0));
                $categoryStats['completedAssessmentCount'] += max(0, (int) ($stats['completedAssessmentCount'] ?? 0));
                $units[] = $unitPayload;
            }

            $termStats['unitCount'] += $categoryStats['unitCount'];
            $termStats['availableUnitCount'] += $categoryStats['availableUnitCount'];
            $termStats['courseCount'] += $categoryStats['courseCount'];
            $termStats['examCount'] += $categoryStats['examCount'];
            $termStats['questionCount'] += $categoryStats['questionCount'];
            $termStats['completedAssessmentCount'] += $categoryStats['completedAssessmentCount'];

            $termCategories[] = [
                'key' => (string) ($category['key'] ?? ''),
                'title' => (string) ($category['title'] ?? ''),
                'stats' => $categoryStats,
                'units' => $units,
            ];
        }

        $catalogStats['termCount']++;
        $catalogStats['availableTermCount'] += $termStats['availableUnitCount'] > 0 ? 1 : 0;
        $catalogStats['unitCount'] += $termStats['unitCount'];
        $catalogStats['availableUnitCount'] += $termStats['availableUnitCount'];
        $catalogStats['courseCount'] += $termStats['courseCount'];
        $catalogStats['examCount'] += $termStats['examCount'];
        $catalogStats['questionCount'] += $termStats['questionCount'];
        $catalogStats['completedAssessmentCount'] += $termStats['completedAssessmentCount'];

        $terms[] = [
            'number' => max(0, (int) ($term['number'] ?? 0)),
            'label' => (string) ($term['label'] ?? ''),
            'stats' => $termStats,
            'categories' => $termCategories,
        ];
    }

    return [
        'title' => 'آزمون‌ها بر اساس ترم و واحد',
        'description' => 'ابتدا ترم را انتخاب کن، بعد از داخل دسته واحدها وارد مجموعه آزمون هر درس شو.',
        'stats' => $catalogStats,
        'terms' => $terms,
    ];
}

function dent_exams_api_reference_payload(array $reference, array $coursePayloadLookup): array
{
    $collections = [];
    $seenCourseSlugs = [];

    foreach ((is_array($reference['courseSlugs'] ?? null) ? $reference['courseSlugs'] : []) as $courseSlug) {
        $cleanSlug = dent_exams_clean_course_slug((string) $courseSlug);
        if ($cleanSlug === '' || isset($seenCourseSlugs[$cleanSlug])) {
            continue;
        }

        $seenCourseSlugs[$cleanSlug] = true;
        if (is_array($coursePayloadLookup[$cleanSlug] ?? null)) {
            $collections[] = $coursePayloadLookup[$cleanSlug];
        }
    }

    $collectionCount = count($collections);
    $statusKey = $collectionCount <= 0
        ? 'empty'
        : ($collectionCount === 1 ? 'available' : 'multi');
    $courseTitles = array_values(array_filter(array_map(static function (array $collection): string {
        return trim((string) ($collection['title'] ?? ''));
    }, $collections)));

    return [
        'key' => (string) ($reference['key'] ?? ''),
        'title' => (string) ($reference['title'] ?? ''),
        'sourceTitle' => (string) ($reference['sourceTitle'] ?? ''),
        'year' => max(0, (int) ($reference['year'] ?? 0)),
        'editionLabel' => (string) ($reference['editionLabel'] ?? ''),
        'statusKey' => $statusKey,
        'statusLabel' => $collectionCount <= 0
            ? 'بدون آزمون'
            : ($collectionCount === 1 ? 'دارای آزمون' : 'چند مجموعه'),
        'entryMode' => $collectionCount <= 0
            ? 'none'
            : ($collectionCount === 1 ? 'direct' : 'collections'),
        'entryLabel' => $collectionCount <= 0
            ? 'هنوز فعال نشده'
            : ($collectionCount === 1 ? 'مشاهده آزمون‌ها' : 'مشاهده مجموعه‌ها'),
        'entryHref' => $collectionCount === 1
            ? (string) ($collections[0]['path'] ?? '')
            : '',
        'collectionTitles' => $courseTitles,
        'stats' => dent_exams_api_collection_stats_aggregate($collections),
        'collections' => $collections,
    ];
}

function dent_exams_api_reference_catalog_payload(array $coursePayloadLookup): array
{
    $specialties = [];
    $catalogStats = [
        'specialtyCount' => 0,
        'referenceCount' => 0,
        'availableReferenceCount' => 0,
        'courseCount' => 0,
        'examCount' => 0,
        'questionCount' => 0,
        'completedAssessmentCount' => 0,
    ];

    foreach (dent_dentistry_exam_reference_specialties() as $specialty) {
        if (!is_array($specialty)) {
            continue;
        }

        $references = [];
        $specialtyStats = [
            'referenceCount' => 0,
            'availableReferenceCount' => 0,
            'courseCount' => 0,
            'examCount' => 0,
            'questionCount' => 0,
            'completedAssessmentCount' => 0,
        ];

        foreach ((is_array($specialty['references'] ?? null) ? $specialty['references'] : []) as $reference) {
            if (!is_array($reference)) {
                continue;
            }

            $referencePayload = dent_exams_api_reference_payload($reference, $coursePayloadLookup);
            $referenceStats = is_array($referencePayload['stats'] ?? null) ? $referencePayload['stats'] : [];

            $specialtyStats['referenceCount']++;
            $specialtyStats['availableReferenceCount'] += $referencePayload['statusKey'] === 'empty' ? 0 : 1;
            $specialtyStats['courseCount'] += max(0, (int) ($referenceStats['courseCount'] ?? 0));
            $specialtyStats['examCount'] += max(0, (int) ($referenceStats['examCount'] ?? 0));
            $specialtyStats['questionCount'] += max(0, (int) ($referenceStats['questionCount'] ?? 0));
            $specialtyStats['completedAssessmentCount'] += max(0, (int) ($referenceStats['completedAssessmentCount'] ?? 0));
            $references[] = $referencePayload;
        }

        $catalogStats['specialtyCount']++;
        $catalogStats['referenceCount'] += $specialtyStats['referenceCount'];
        $catalogStats['availableReferenceCount'] += $specialtyStats['availableReferenceCount'];
        $catalogStats['courseCount'] += $specialtyStats['courseCount'];
        $catalogStats['examCount'] += $specialtyStats['examCount'];
        $catalogStats['questionCount'] += $specialtyStats['questionCount'];
        $catalogStats['completedAssessmentCount'] += $specialtyStats['completedAssessmentCount'];

        $specialties[] = [
            'key' => (string) ($specialty['key'] ?? ''),
            'title' => (string) ($specialty['title'] ?? ''),
            'stats' => $specialtyStats,
            'references' => $references,
        ];
    }

    return [
        'title' => 'آزمون‌ها بر اساس رفرنس',
        'description' => 'ابتدا تخصص را انتخاب کن؛ بعد رفرنس‌های همان تخصص و آزمون‌های وصل‌شده به هر رفرنس را ببین.',
        'stats' => $catalogStats,
        'specialties' => $specialties,
    ];
}

function dent_exams_api_course_stats(array $course): array
{
    $examCount = 0;
    $questionCount = 0;
    $directAttemptableExamCount = 0;

    foreach (is_array($course['exams'] ?? null) ? $course['exams'] : [] as $exam) {
        if (!is_array($exam) || !dent_exams_api_exam_counts_toward_stats($exam)) {
            continue;
        }

        $examCount++;
        $questionCount += dent_exams_api_resolve_exam_question_count($exam);
        if (dent_exams_api_exam_is_attemptable($exam)) {
            $directAttemptableExamCount++;
        }
    }

    return [
        'examCount' => $examCount,
        'questionCount' => $questionCount,
        'directAttemptableExamCount' => $directAttemptableExamCount,
    ];
}

function dent_exams_api_viewer_key(?array $user): string
{
    if (!is_array($user)) {
        return '';
    }

    return dent_exams_clean_participant_key((string) ($user['studentNumber'] ?? ($user['student_number'] ?? '')));
}

function dent_exams_api_mode_definitions(): array
{
    return [
        [
            'key' => 'assessment',
            'title' => 'آزمون سنجشی',
            'tagline' => 'همه سوالات یکجا + کارنامه ذخیره‌شونده',
            'description' => 'همه سوال‌ها را یکجا می‌بینی، هر زمان خواستی ثبت می‌کنی و بعد کارنامه، پاسخ درست، پاسخ تو و پاسخ تشریحی را می‌گیری.',
        ],
        [
            'key' => 'learning',
            'title' => 'آزمون آموزشی',
            'tagline' => 'سوال‌به‌سوال + پاسخ فوری',
            'description' => 'سوال‌ها به‌ترتیب نمایش داده می‌شوند و بلافاصله بعد از پاسخ، گزینه صحیح و توضیح تشریحی همان سوال را می‌بینی.',
        ],
    ];
}

function dent_exams_api_clamp_answers(array $questions, array $answers): array
{
    $normalized = [];
    $totalQuestions = count($questions);
    for ($index = 0; $index < $totalQuestions; $index++) {
        $answer = $answers[$index] ?? null;
        if ($answer === null || $answer === '') {
            $normalized[] = null;
            continue;
        }

        $parsed = (int) $answer;
        $optionCount = is_array($questions[$index]['options'] ?? null)
            ? count($questions[$index]['options'])
            : 0;
        $normalized[] = ($parsed >= 0 && $parsed < $optionCount) ? $parsed : null;
    }

    return $normalized;
}

function dent_exams_api_score_answers(array $questions, array $answers): array
{
    $clampedAnswers = dent_exams_api_clamp_answers($questions, $answers);
    $correct = 0;
    $wrong = 0;
    $unanswered = 0;
    $gradableQuestions = 0;

    foreach ($questions as $index => $question) {
        $optionCount = is_array($question['options'] ?? null) ? count($question['options']) : 0;
        $correctIndex = $question['correctIndex'] ?? null;
        $hasResolvedAnswer = is_int($correctIndex) && $correctIndex >= 0 && $correctIndex < $optionCount;
        if (!$hasResolvedAnswer) {
            continue;
        }

        $gradableQuestions++;
        $selectedIndex = $clampedAnswers[$index] ?? null;
        if (!is_int($selectedIndex)) {
            $unanswered++;
            continue;
        }

        if ($selectedIndex === $correctIndex) {
            $correct++;
            continue;
        }

        $wrong++;
    }

    $totalQuestions = $gradableQuestions;
    $percent = $totalQuestions > 0 ? round(($correct / $totalQuestions) * 100, 1) : 0.0;

    return [
        'answers' => $clampedAnswers,
        'totalQuestions' => $totalQuestions,
        'correct' => $correct,
        'wrong' => $wrong,
        'unanswered' => $unanswered,
        'percent' => $percent,
    ];
}

function dent_exams_api_question_topic(array $question, array $exam): string
{
    $candidates = [
        $question['topic'] ?? '',
        $question['subject'] ?? '',
        $question['category'] ?? '',
        $exam['topic'] ?? '',
    ];
    $tags = $question['tags'] ?? [];
    if (is_array($tags) && isset($tags[0])) {
        $candidates[] = $tags[0];
    }
    foreach ($candidates as $candidate) {
        $label = dent_clean_text((string) $candidate, 160);
        if ($label !== '') {
            return $label;
        }
    }
    return 'کل آزمون';
}

function dent_exams_api_topic_breakdown(array $exam, array $questions, array $answers): array
{
    $rows = [];
    foreach ($questions as $index => $question) {
        if (!is_array($question)) {
            continue;
        }
        $options = is_array($question['options'] ?? null) ? $question['options'] : [];
        $correctIndex = $question['correctIndex'] ?? null;
        if (!is_int($correctIndex) || $correctIndex < 0 || $correctIndex >= count($options)) {
            continue;
        }
        $label = dent_exams_api_question_topic($question, $exam);
        if (!isset($rows[$label])) {
            $rows[$label] = [
                'label' => $label,
                'total' => 0,
                'correct' => 0,
                'wrong' => 0,
                'unanswered' => 0,
                'percent' => 0.0,
            ];
        }
        $rows[$label]['total']++;
        $selected = $answers[$index] ?? null;
        if (!is_int($selected)) {
            $rows[$label]['unanswered']++;
        } elseif ($selected === $correctIndex) {
            $rows[$label]['correct']++;
        } else {
            $rows[$label]['wrong']++;
        }
    }

    foreach ($rows as &$row) {
        $row['percent'] = $row['total'] > 0
            ? round(($row['correct'] / $row['total']) * 100, 1)
            : 0.0;
    }
    unset($row);
    return dent_exams_normalize_topic_breakdown(array_values($rows));
}

function dent_exams_api_mistake_indexes(array $questions, array $answers): array
{
    $indexes = [];
    foreach ($questions as $index => $question) {
        if (!is_array($question)) {
            continue;
        }
        $options = is_array($question['options'] ?? null) ? $question['options'] : [];
        $correctIndex = $question['correctIndex'] ?? null;
        $selected = $answers[$index] ?? null;
        if (!is_int($correctIndex) || $correctIndex < 0 || $correctIndex >= count($options) || !is_int($selected)) {
            continue;
        }
        if ($selected !== $correctIndex) {
            $indexes[] = (int) $index;
        }
    }
    return dent_exams_normalize_question_index_list($indexes);
}

function dent_exams_api_attempt_history_payload(array $attempts): array
{
    $rows = [];
    foreach (array_reverse(dent_exams_normalize_attempt_history($attempts)) as $attempt) {
        $rows[] = [
            'attemptId' => (string) ($attempt['attemptId'] ?? ''),
            'totalQuestions' => max(0, (int) ($attempt['totalQuestions'] ?? 0)),
            'correct' => max(0, (int) ($attempt['correct'] ?? 0)),
            'wrong' => max(0, (int) ($attempt['wrong'] ?? 0)),
            'unanswered' => max(0, (int) ($attempt['unanswered'] ?? 0)),
            'percent' => dent_exams_normalize_percent($attempt['percent'] ?? 0),
            'startedAt' => (string) ($attempt['startedAt'] ?? ''),
            'submittedAt' => (string) ($attempt['submittedAt'] ?? ''),
            'topicBreakdown' => dent_exams_normalize_topic_breakdown($attempt['topicBreakdown'] ?? []),
            'mistakeQuestionIndexes' => dent_exams_normalize_question_index_list($attempt['mistakeQuestionIndexes'] ?? []),
        ];
        if (count($rows) >= 20) {
            break;
        }
    }
    return $rows;
}

function dent_exams_api_parse_json_list($value): array
{
    if (is_array($value)) {
        return $value;
    }

    if (!is_string($value)) {
        return [];
    }

    $trimmed = trim($value);
    if ($trimmed === '') {
        return [];
    }

    $decoded = json_decode($trimmed, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    return array_map('trim', explode(',', $trimmed));
}

function dent_exams_api_parse_answers_param($value): array
{
    return dent_exams_normalize_answer_list(dent_exams_api_parse_json_list($value));
}

function dent_exams_api_parse_flag_indexes_param($value): array
{
    return dent_exams_normalize_question_index_list(dent_exams_api_parse_json_list($value));
}

function dent_exams_api_compare_reports(array $left, string $leftUserKey, array $right, string $rightUserKey): int
{
    $percentComparison = dent_exams_normalize_percent($right['percent'] ?? 0) <=> dent_exams_normalize_percent($left['percent'] ?? 0);
    if ($percentComparison !== 0) {
        return $percentComparison;
    }

    $correctComparison = max(0, (int) ($right['correct'] ?? 0)) <=> max(0, (int) ($left['correct'] ?? 0));
    if ($correctComparison !== 0) {
        return $correctComparison;
    }

    $unansweredComparison = max(0, (int) ($left['unanswered'] ?? 0)) <=> max(0, (int) ($right['unanswered'] ?? 0));
    if ($unansweredComparison !== 0) {
        return $unansweredComparison;
    }

    $submittedComparison = strcmp((string) ($left['submittedAt'] ?? ''), (string) ($right['submittedAt'] ?? ''));
    if ($submittedComparison !== 0) {
        return $submittedComparison;
    }

    return strcmp($leftUserKey, $rightUserKey);
}

function dent_exams_api_report_ranking(
    array $store,
    string $catalogKey,
    string $courseSlug,
    string $examSlug,
    string $participantKey
): array {
    $reportsByUser = dent_exams_reports_by_user($store, $catalogKey, $courseSlug, $examSlug);
    $rows = [];
    foreach ($reportsByUser as $userKey => $report) {
        if (!is_array($report)) {
            continue;
        }

        $cleanUserKey = dent_exams_clean_participant_key((string) $userKey);
        if ($cleanUserKey === '') {
            continue;
        }

        $rows[] = [
            'userKey' => $cleanUserKey,
            'report' => dent_exams_normalize_assessment_report($report),
        ];
    }

    usort($rows, static function (array $left, array $right): int {
        return dent_exams_api_compare_reports(
            is_array($left['report'] ?? null) ? $left['report'] : [],
            (string) ($left['userKey'] ?? ''),
            is_array($right['report'] ?? null) ? $right['report'] : [],
            (string) ($right['userKey'] ?? '')
        );
    });

    $participantCount = count($rows);
    $rank = null;
    foreach ($rows as $index => $row) {
        if ((string) ($row['userKey'] ?? '') !== $participantKey) {
            continue;
        }
        $rank = $index + 1;
        break;
    }

    return [
        'participantCount' => $participantCount,
        'rank' => $rank,
        'showRank' => $participantCount >= 10 && $rank !== null,
    ];
}

function dent_exams_api_user_average(array $store, string $catalogKey, string $participantKey): array
{
    $cleanParticipant = dent_exams_clean_participant_key($participantKey);
    if ($cleanParticipant === '') {
        return [
            'completedCount' => 0,
            'averagePercent' => null,
        ];
    }

    $records = $store['examRecords'] ?? [];
    if (!is_array($records)) {
        $records = [];
    }

    $percents = [];
    foreach ($records as $examKey => $record) {
        $cleanExamKey = dent_exams_clean_exam_key((string) $examKey);
        if ($cleanExamKey === '' || strpos($cleanExamKey, dent_exams_clean_catalog_key($catalogKey) . ':') !== 0) {
            continue;
        }

        if (!is_array($record)) {
            continue;
        }

        $normalizedRecord = dent_exams_normalize_exam_record($record);
        $report = $normalizedRecord['reportsByUser'][$cleanParticipant] ?? null;
        if (!is_array($report)) {
            continue;
        }

        $percents[] = dent_exams_normalize_percent($report['percent'] ?? 0);
    }

    if (!$percents) {
        return [
            'completedCount' => 0,
            'averagePercent' => null,
        ];
    }

    return [
        'completedCount' => count($percents),
        'averagePercent' => round(array_sum($percents) / count($percents), 1),
    ];
}

function dent_exams_api_report_summary_payload(
    array $store,
    string $catalogKey,
    string $courseSlug,
    string $examSlug,
    string $participantKey,
    array $report
): array {
    $normalizedReport = dent_exams_normalize_assessment_report($report);
    $ranking = dent_exams_api_report_ranking($store, $catalogKey, $courseSlug, $examSlug, $participantKey);
    $average = dent_exams_api_user_average($store, $catalogKey, $participantKey);
    $participantCount = max(0, (int) ($ranking['participantCount'] ?? 0));
    $showComparisons = $participantCount >= 10;

    return [
        'attemptId' => (string) ($normalizedReport['attemptId'] ?? ''),
        'totalQuestions' => max(0, (int) ($normalizedReport['totalQuestions'] ?? 0)),
        'correct' => max(0, (int) ($normalizedReport['correct'] ?? 0)),
        'wrong' => max(0, (int) ($normalizedReport['wrong'] ?? 0)),
        'unanswered' => max(0, (int) ($normalizedReport['unanswered'] ?? 0)),
        'percent' => dent_exams_normalize_percent($normalizedReport['percent'] ?? 0),
        'startedAt' => (string) ($normalizedReport['startedAt'] ?? ''),
        'submittedAt' => (string) ($normalizedReport['submittedAt'] ?? ''),
        'updatedAt' => (string) ($normalizedReport['updatedAt'] ?? ''),
        'participantCount' => $participantCount,
        'rank' => !empty($ranking['showRank']) ? (int) ($ranking['rank'] ?? 0) : null,
        'showRank' => !empty($ranking['showRank']),
        'overallCompletedExams' => max(0, (int) ($average['completedCount'] ?? 0)),
        'overallAveragePercent' => $showComparisons && array_key_exists('averagePercent', $average) ? $average['averagePercent'] : null,
        'topicBreakdown' => dent_exams_normalize_topic_breakdown($normalizedReport['topicBreakdown'] ?? []),
        'mistakeQuestionIndexes' => dent_exams_normalize_question_index_list($normalizedReport['mistakeQuestionIndexes'] ?? []),
    ];
}

function dent_exams_api_report_rows(array $store, string $catalogKey, string $courseSlug, string $examSlug): array
{
    $reportsByUser = dent_exams_reports_by_user($store, $catalogKey, $courseSlug, $examSlug);
    $rows = [];
    foreach ($reportsByUser as $userKey => $report) {
        if (!is_array($report)) {
            continue;
        }

        $cleanUserKey = dent_exams_clean_participant_key((string) $userKey);
        if ($cleanUserKey === '') {
            continue;
        }

        $rows[] = [
            'userKey' => $cleanUserKey,
            'report' => dent_exams_normalize_assessment_report($report),
        ];
    }

    usort($rows, static function (array $left, array $right): int {
        return dent_exams_api_compare_reports(
            is_array($left['report'] ?? null) ? $left['report'] : [],
            (string) ($left['userKey'] ?? ''),
            is_array($right['report'] ?? null) ? $right['report'] : [],
            (string) ($right['userKey'] ?? '')
        );
    });

    foreach ($rows as $index => $row) {
        $rows[$index]['rank'] = $index + 1;
    }

    return $rows;
}

function dent_exams_api_user_site_average(array $store, string $participantKey): array
{
    $cleanParticipant = dent_exams_clean_participant_key($participantKey);
    if ($cleanParticipant === '') {
        return [
            'completedCount' => 0,
            'averagePercent' => null,
        ];
    }

    $records = is_array($store['examRecords'] ?? null) ? $store['examRecords'] : [];
    $percents = [];
    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }

        $normalizedRecord = dent_exams_normalize_exam_record($record);
        $report = $normalizedRecord['reportsByUser'][$cleanParticipant] ?? null;
        if (!is_array($report)) {
            continue;
        }

        $percents[] = dent_exams_normalize_percent($report['percent'] ?? 0);
    }

    if ($percents === []) {
        return [
            'completedCount' => 0,
            'averagePercent' => null,
        ];
    }

    return [
        'completedCount' => count($percents),
        'averagePercent' => round(array_sum($percents) / count($percents), 1),
    ];
}

function dent_exams_api_owner_user_lookup(): array
{
    $lookup = [];
    foreach (dent_list_public_users(false) as $user) {
        if (!is_array($user)) {
            continue;
        }

        $participantKey = dent_exams_clean_participant_key((string) ($user['studentNumber'] ?? ($user['student_number'] ?? '')));
        if ($participantKey === '') {
            continue;
        }

        $lookup[$participantKey] = [
            'name' => trim((string) ($user['name'] ?? '')),
            'studentNumber' => $participantKey,
            'roleLabel' => dent_role_label((string) ($user['role'] ?? 'student')),
        ];
    }

    return $lookup;
}

function dent_exams_api_owner_order_participant_key(array $order): string
{
    $candidates = [
        (string) ($order['user_id'] ?? ''),
        (string) ($order['payer_student_number'] ?? ''),
    ];

    foreach ($candidates as $candidate) {
        $participantKey = dent_exams_clean_participant_key($candidate);
        if ($participantKey !== '') {
            return $participantKey;
        }
    }

    return '';
}

function dent_exams_api_owner_exam_collection_group_map(array $examsStore, array $paymentsStore): array
{
    $collectionToGroup = [];
    foreach (dent_exams_catalogs() as $catalogKey => $catalog) {
        if (!is_array($catalog)) {
            continue;
        }

        $courses = is_array($catalog['courses'] ?? null) ? $catalog['courses'] : [];
        foreach ($courses as $courseSlug => $course) {
            if (!is_array($course)) {
                continue;
            }

            $cleanCourseSlug = dent_exams_clean_course_slug((string) $courseSlug);
            $bindingSlug = dent_exams_api_payment_binding_slug((string) $catalogKey, $cleanCourseSlug, $course);
            if ($bindingSlug === '' || $bindingSlug !== $cleanCourseSlug) {
                continue;
            }

            $bindingCourse = dent_exams_api_payment_binding_course((string) $catalogKey, $bindingSlug, $course);
            $setting = dent_exams_course_setting($examsStore, (string) $catalogKey, $bindingSlug);
            $setting = dent_exams_api_setting_with_legacy_collection_ids(
                $examsStore,
                $paymentsStore,
                (string) $catalogKey,
                $bindingCourse,
                $setting
            );
            $groupKey = dent_exams_course_key((string) $catalogKey, $bindingSlug);
            if ($groupKey === '') {
                continue;
            }

            foreach (dent_exams_api_setting_collection_ids($setting) as $collectionId) {
                $cleanCollectionId = max(0, (int) $collectionId);
                if ($cleanCollectionId > 0) {
                    $collectionToGroup[$cleanCollectionId] = $groupKey;
                }
            }
        }
    }

    return $collectionToGroup;
}

function dent_exams_api_owner_exam_purchase_counts(array $examsStore, array $paymentsStore): array
{
    $collectionToGroup = dent_exams_api_owner_exam_collection_group_map($examsStore, $paymentsStore);
    if ($collectionToGroup === []) {
        return [];
    }

    $purchasesByUser = [];
    foreach (is_array($paymentsStore['orders'] ?? null) ? $paymentsStore['orders'] : [] as $order) {
        if (!is_array($order) || (string) ($order['status'] ?? '') !== PAYMENTS_ORDER_STATUS_SUCCESS) {
            continue;
        }

        $extra = is_array($order['extra_form_data'] ?? null) ? $order['extra_form_data'] : [];
        $collectionId = max(0, (int) ($extra['collection_id'] ?? 0));
        $groupKey = (string) ($collectionToGroup[$collectionId] ?? '');
        if ((string) ($extra['_source'] ?? '') !== 'collection' || $groupKey === '') {
            continue;
        }

        $participantKey = dent_exams_api_owner_order_participant_key($order);
        if ($participantKey === '') {
            continue;
        }

        if (!isset($purchasesByUser[$participantKey])) {
            $purchasesByUser[$participantKey] = [];
        }
        $purchasesByUser[$participantKey][$groupKey] = true;
    }

    $counts = [];
    foreach ($purchasesByUser as $participantKey => $groups) {
        $counts[$participantKey] = count($groups);
    }

    return $counts;
}

function dent_exams_api_owner_exam_type_label(bool $hasReport, string $lastMode): string
{
    if ($hasReport || $lastMode === 'assessment') {
        return 'سنجشی';
    }
    if ($lastMode === 'learning') {
        return 'آموزشی';
    }

    return 'شروع اولیه';
}

function dent_exams_api_owner_exam_insights_payload(
    array $store,
    array $paymentsStore,
    string $catalogKey,
    string $courseSlug,
    string $examSlug
): array {
    $record = dent_exams_record($store, $catalogKey, $courseSlug, $examSlug);
    $flagsByUser = is_array($record['flagsByUser'] ?? null) ? $record['flagsByUser'] : [];
    $activityByUser = is_array($record['activityByUser'] ?? null) ? $record['activityByUser'] : [];
    $reportRows = dent_exams_api_report_rows($store, $catalogKey, $courseSlug, $examSlug);
    $reportMap = [];
    $rankMap = [];
    foreach ($reportRows as $row) {
        $participantKey = (string) ($row['userKey'] ?? '');
        if ($participantKey === '') {
            continue;
        }

        $reportMap[$participantKey] = is_array($row['report'] ?? null) ? $row['report'] : null;
        $rankMap[$participantKey] = max(1, (int) ($row['rank'] ?? 0));
    }

    $userLookup = dent_exams_api_owner_user_lookup();
    $purchaseCounts = dent_exams_api_owner_exam_purchase_counts($store, $paymentsStore);

    $participantKeys = [];
    foreach (array_keys($reportMap) as $participantKey) {
        $participantKeys[$participantKey] = true;
    }
    foreach (array_keys($activityByUser) as $participantKey) {
        $cleanParticipant = dent_exams_clean_participant_key((string) $participantKey);
        if ($cleanParticipant !== '') {
            $participantKeys[$cleanParticipant] = true;
        }
    }
    foreach (array_keys($flagsByUser) as $participantKey) {
        $cleanParticipant = dent_exams_clean_participant_key((string) $participantKey);
        if ($cleanParticipant !== '') {
            $participantKeys[$cleanParticipant] = true;
        }
    }

    $extraParticipants = [];
    foreach (array_keys($participantKeys) as $participantKey) {
        if (isset($rankMap[$participantKey])) {
            continue;
        }

        $activity = is_array($activityByUser[$participantKey] ?? null)
            ? dent_exams_normalize_exam_activity($activityByUser[$participantKey])
            : null;
        $flagsCount = count(dent_exams_normalize_question_index_list($flagsByUser[$participantKey] ?? []));
        $extraParticipants[] = [
            'userKey' => $participantKey,
            'lastActivityAt' => is_array($activity) ? (string) ($activity['updatedAt'] ?? '') : '',
            'flagsCount' => $flagsCount,
        ];
    }

    usort($extraParticipants, static function (array $left, array $right): int {
        $activityComparison = strcmp((string) ($right['lastActivityAt'] ?? ''), (string) ($left['lastActivityAt'] ?? ''));
        if ($activityComparison !== 0) {
            return $activityComparison;
        }

        return max(0, (int) ($right['flagsCount'] ?? 0)) <=> max(0, (int) ($left['flagsCount'] ?? 0));
    });

    $orderedKeys = array_values(array_map(static function (array $row): string {
        return (string) ($row['userKey'] ?? '');
    }, $reportRows));
    foreach ($extraParticipants as $row) {
        $participantKey = (string) ($row['userKey'] ?? '');
        if ($participantKey !== '') {
            $orderedKeys[] = $participantKey;
        }
    }

    $participants = [];
    $percentTotal = 0.0;
    $percentCount = 0;
    $paidParticipantCount = 0;
    foreach ($orderedKeys as $participantKey) {
        if ($participantKey === '') {
            continue;
        }

        $report = is_array($reportMap[$participantKey] ?? null) ? $reportMap[$participantKey] : null;
        $activity = is_array($activityByUser[$participantKey] ?? null)
            ? dent_exams_normalize_exam_activity($activityByUser[$participantKey])
            : null;
        $flagsCount = count(dent_exams_normalize_question_index_list($flagsByUser[$participantKey] ?? []));
        $hasReport = is_array($report);
        $lastMode = is_array($activity)
            ? dent_exams_api_normalize_activity_mode((string) ($activity['lastMode'] ?? 'view'))
            : ($hasReport ? 'assessment' : 'view');
        $overallStats = dent_exams_api_user_site_average($store, $participantKey);
        $purchaseCount = max(0, (int) ($purchaseCounts[$participantKey] ?? 0));
        $userMeta = is_array($userLookup[$participantKey] ?? null) ? $userLookup[$participantKey] : [];

        if ($purchaseCount > 0) {
            $paidParticipantCount++;
        }
        if ($hasReport) {
            $percentTotal += dent_exams_normalize_percent($report['percent'] ?? 0);
            $percentCount++;
        }

        $participants[] = [
            'name' => trim((string) ($userMeta['name'] ?? '')) !== '' ? (string) $userMeta['name'] : ('کاربر ' . $participantKey),
            'studentNumber' => (string) ($userMeta['studentNumber'] ?? $participantKey),
            'roleLabel' => (string) ($userMeta['roleLabel'] ?? 'کاربر آزمون'),
            'typeLabel' => dent_exams_api_owner_exam_type_label($hasReport, $lastMode),
            'rank' => $hasReport ? max(1, (int) ($rankMap[$participantKey] ?? 0)) : null,
            'percent' => $hasReport ? dent_exams_normalize_percent($report['percent'] ?? 0) : null,
            'correct' => $hasReport ? max(0, (int) ($report['correct'] ?? 0)) : null,
            'wrong' => $hasReport ? max(0, (int) ($report['wrong'] ?? 0)) : null,
            'overallExamCount' => max(0, (int) ($overallStats['completedCount'] ?? 0)),
            'purchasedExamCount' => $purchaseCount,
            'flagsCount' => $flagsCount,
            'lastActivityAt' => is_array($activity) ? (string) ($activity['updatedAt'] ?? '') : ($hasReport ? (string) ($report['updatedAt'] ?? ($report['submittedAt'] ?? '')) : ''),
        ];
    }

    return [
        'canView' => true,
        'summary' => [
            'participantCount' => count($participants),
            'assessmentCount' => count($reportRows),
            'paidParticipantCount' => $paidParticipantCount,
            'averagePercent' => $percentCount > 0 ? round($percentTotal / $percentCount, 1) : null,
        ],
        'participants' => $participants,
    ];
}

function dent_exams_api_normalize_activity_mode(string $value): string
{
    $mode = trim(strtolower($value));
    if (!in_array($mode, ['view', 'assessment', 'learning'], true)) {
        return 'view';
    }

    return $mode;
}

function dent_exams_api_timestamp_value(string $value): int
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return 0;
    }

    $parsed = strtotime($trimmed);
    return $parsed === false ? 0 : $parsed;
}

function dent_exams_api_latest_datetime(array $values): string
{
    $latestValue = '';
    $latestTs = 0;

    foreach ($values as $value) {
        $raw = trim((string) $value);
        if ($raw === '') {
            continue;
        }

        $timestamp = dent_exams_api_timestamp_value($raw);
        if ($timestamp >= $latestTs) {
            $latestTs = $timestamp;
            $latestValue = $raw;
        }
    }

    return $latestValue;
}

function dent_exams_api_exam_progress_payload(
    array $store,
    string $catalogKey,
    string $courseSlug,
    string $examSlug,
    ?array $viewer
): ?array {
    $participantKey = dent_exams_api_viewer_key($viewer);
    if ($participantKey === '') {
        return null;
    }

    $flags = dent_exams_flags_for_user($store, $catalogKey, $courseSlug, $examSlug, $participantKey);
    $report = dent_exams_report_for_user($store, $catalogKey, $courseSlug, $examSlug, $participantKey);
    $activity = dent_exams_activity_for_user($store, $catalogKey, $courseSlug, $examSlug, $participantKey);
    $lastActivityAt = is_array($activity) ? (string) ($activity['updatedAt'] ?? '') : '';
    $lastMode = is_array($activity) ? dent_exams_api_normalize_activity_mode((string) ($activity['lastMode'] ?? 'view')) : '';
    $lastAttemptAt = dent_exams_api_latest_datetime([
        $lastActivityAt,
        is_array($report) ? (string) ($report['updatedAt'] ?? ($report['submittedAt'] ?? '')) : '',
    ]);
    $hasActivity = $lastActivityAt !== '';
    $statusKey = is_array($report)
        ? 'completed'
        : (($hasActivity || count($flags) > 0) ? 'in-progress' : 'not-started');

    return [
        'flagsCount' => count($flags),
        'hasFlags' => count($flags) > 0,
        'hasActivity' => $hasActivity,
        'hasAssessmentReport' => is_array($report),
        'lastMode' => $lastMode,
        'lastActivityAt' => $lastActivityAt,
        'lastAttemptAt' => $lastAttemptAt,
        'statusKey' => $statusKey,
        'assessmentReport' => is_array($report)
            ? dent_exams_api_report_summary_payload($store, $catalogKey, $courseSlug, $examSlug, $participantKey, $report)
            : null,
    ];
}

function dent_exams_api_exam_payload(
    array $store,
    array $paymentsStore,
    string $catalogKey,
    array $course,
    array $exam,
    ?array $viewer
): array {
    $courseSlug = dent_exams_clean_course_slug((string) ($course['slug'] ?? ''));
    $examSlug = dent_exams_clean_exam_slug((string) ($exam['slug'] ?? ''));
    $participantKey = dent_exams_api_viewer_key($viewer);
    $flags = $participantKey !== ''
        ? dent_exams_flags_for_user($store, $catalogKey, $courseSlug, $examSlug, $participantKey)
        : [];
    $report = $participantKey !== ''
        ? dent_exams_report_for_user($store, $catalogKey, $courseSlug, $examSlug, $participantKey)
        : null;
    $attempts = $participantKey !== ''
        ? dent_exams_attempts_for_user($store, $catalogKey, $courseSlug, $examSlug, $participantKey)
        : [];
    $studyState = $participantKey !== ''
        ? dent_exams_study_state_for_user($store, $catalogKey, $courseSlug, $examSlug, $participantKey)
        : dent_exams_normalize_study_state([]);

    $payload = $exam;
    $payload['questionCount'] = dent_exams_api_resolve_exam_question_count($exam);
    $payload['comingSoon'] = dent_exams_api_exam_is_coming_soon($exam);
    $payload['attemptable'] = dent_exams_api_exam_is_attemptable($exam);
    $courseAddedAt = dent_exams_catalog_course_added_at($courseSlug, $course);
    $payload['addedAt'] = dent_exams_catalog_exam_added_at($exam, $courseAddedAt);
    $payload['courseTitle'] = (string) ($course['title'] ?? '');
    $payload['coursePath'] = (string) ($course['path'] ?? '/exams/');
    $payload['modes'] = dent_exams_api_mode_definitions();
    $payload['viewerState'] = [
        'canPersist' => $participantKey !== '',
        'flaggedQuestionIndexes' => $flags,
        'assessmentReport' => ($participantKey !== '' && is_array($report))
            ? array_merge(
                dent_exams_api_report_summary_payload($store, $catalogKey, $courseSlug, $examSlug, $participantKey, $report),
                ['answers' => dent_exams_api_clamp_answers(
                    is_array($exam['questions'] ?? null) ? $exam['questions'] : [],
                    is_array($report['answers'] ?? null) ? $report['answers'] : []
                )]
            )
            : null,
        'attemptHistory' => dent_exams_api_attempt_history_payload($attempts),
        'studyState' => $studyState,
    ];
    if (dent_exams_api_is_owner($viewer)) {
        $payload['ownerInsights'] = dent_exams_api_owner_exam_insights_payload(
            $store,
            $paymentsStore,
            $catalogKey,
            $courseSlug,
            $examSlug
        );
    }

    return $payload;
}

function dent_exams_api_require_method(array $methods): void
{
    $method = dent_request_method();
    if (!in_array($method, $methods, true)) {
        dent_error('متد درخواست نامعتبر است.', 405);
    }
}

function dent_exams_api_payment_highlights(): array
{
    return [
        'طراحی شده صرفا بر اساس جزوات',
        'با بروزترین مدل های هوش مصنوعی و الگو گیری از سوالات رزیدنتی + آزمون های سال های قبل',
        'همراه با درصدگیری، پاسخ تشریحی و توضیح کامل',
        'هرگونه پیشنهاد برای بهتر شدن آزمون‌ها را به نماینده اطلاع دهید. پیشنهاد شما در صورت امکان، «حتما و فورا» برای آزمون های بعدی، در نظر گرفته می شود.',
    ];
}

function dent_exams_api_money(int $amount): string
{
    return number_format(max(0, $amount)) . ' ریال';
}

function dent_exams_api_absolute_url(string $path): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return $path;
    }

    $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    $isSecure = $forwardedProto === 'https'
        || (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');

    return ($isSecure ? 'https://' : 'http://') . $host . $path;
}

function dent_exams_api_parse_discount_codes_input($raw): array
{
    if (is_array($raw)) {
        return dent_exams_normalize_discount_codes($raw);
    }

    $text = trim((string) $raw);
    if ($text === '') {
        return [];
    }

    $decoded = json_decode($text, true);
    if (!is_array($decoded)) {
        dent_error('فرمت کدهای تخفیف معتبر نیست. JSON معتبر وارد کنید.', 422);
    }

    return dent_exams_normalize_discount_codes($decoded);
}

function dent_exams_api_collection_for_setting(array $paymentsStore, array $setting): ?array
{
    $collectionId = max(0, (int) ($setting['collectionId'] ?? 0));
    if ($collectionId <= 0) {
        return null;
    }

    $index = payments_find_collection_index_by_id($paymentsStore, $collectionId);
    if ($index < 0 || !is_array($paymentsStore['collections'][$index] ?? null)) {
        return null;
    }

    return $paymentsStore['collections'][$index];
}

function dent_exams_api_order_matches_collection(array $order, int $collectionId): bool
{
    if ($collectionId <= 0) {
        return false;
    }

    $extra = is_array($order['extra_form_data'] ?? null) ? $order['extra_form_data'] : [];
    return (string) ($extra['_source'] ?? '') === 'collection'
        && (int) ($extra['collection_id'] ?? 0) === $collectionId;
}

function dent_exams_api_collection_orders(array $paymentsStore, int $collectionId): array
{
    $orders = [];
    foreach (($paymentsStore['orders'] ?? []) as $order) {
        if (!is_array($order) || !dent_exams_api_order_matches_collection($order, $collectionId)) {
            continue;
        }
        $orders[] = $order;
    }

    usort($orders, static function (array $left, array $right): int {
        return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
    });

    return $orders;
}

function dent_exams_api_user_paid_order(array $paymentsStore, int $collectionId, ?array $user): ?array
{
    if ($collectionId <= 0 || !is_array($user)) {
        return null;
    }

    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ($user['student_number'] ?? '')));
    if ($studentNumber === '') {
        return null;
    }

    foreach (dent_exams_api_collection_orders($paymentsStore, $collectionId) as $order) {
        if ((string) ($order['status'] ?? '') !== PAYMENTS_ORDER_STATUS_SUCCESS) {
            continue;
        }

        if (
            $studentNumber === dent_normalize_student_number((string) ($order['user_id'] ?? ''))
            || $studentNumber === dent_normalize_student_number((string) ($order['payer_student_number'] ?? ''))
        ) {
            return $order;
        }
    }

    return null;
}

function dent_exams_api_collection_stats(array $paymentsStore, int $collectionId): array
{
    $totalOrders = 0;
    $successCount = 0;
    $receivedAmount = 0;
    foreach (dent_exams_api_collection_orders($paymentsStore, $collectionId) as $order) {
        $totalOrders++;
        if ((string) ($order['status'] ?? '') !== PAYMENTS_ORDER_STATUS_SUCCESS) {
            continue;
        }

        $successCount++;
        $receivedAmount += max(0, (int) ($order['amount'] ?? 0));
    }

    return [
        'totalOrders' => $totalOrders,
        'successCount' => $successCount,
        'receivedAmount' => $receivedAmount,
    ];
}

function dent_exams_api_collection_discount_usage_map(array $paymentsStore, int $collectionId): array
{
    $usage = [];
    foreach (dent_exams_api_collection_orders($paymentsStore, $collectionId) as $order) {
        if ((string) ($order['status'] ?? '') !== PAYMENTS_ORDER_STATUS_SUCCESS) {
            continue;
        }

        $code = payments_normalize_discount_code_text((string) ($order['discount_code'] ?? ''));
        if ($code === '') {
            continue;
        }

        $usage[$code] = max(0, (int) ($usage[$code] ?? 0)) + 1;
    }

    ksort($usage);
    return $usage;
}

function dent_exams_api_setting_collection_ids(array $setting): array
{
    $ids = [];
    $seen = [];
    $candidates = array_merge(
        [max(0, (int) ($setting['collectionId'] ?? 0))],
        is_array($setting['legacyCollectionIds'] ?? null) ? $setting['legacyCollectionIds'] : []
    );

    foreach ($candidates as $candidate) {
        $collectionId = max(0, (int) $candidate);
        if ($collectionId <= 0 || isset($seen[$collectionId])) {
            continue;
        }

        $seen[$collectionId] = true;
        $ids[] = $collectionId;
    }

    return $ids;
}

function dent_exams_api_user_paid_order_for_any(array $paymentsStore, array $collectionIds, ?array $user): ?array
{
    foreach ($collectionIds as $collectionId) {
        $paidOrder = dent_exams_api_user_paid_order($paymentsStore, max(0, (int) $collectionId), $user);
        if ($paidOrder !== null) {
            return $paidOrder;
        }
    }

    return null;
}

function dent_exams_api_collection_stats_for_ids(array $paymentsStore, array $collectionIds): array
{
    $stats = [
        'totalOrders' => 0,
        'successCount' => 0,
        'receivedAmount' => 0,
    ];

    foreach ($collectionIds as $collectionId) {
        $collectionStats = dent_exams_api_collection_stats($paymentsStore, max(0, (int) $collectionId));
        $stats['totalOrders'] += max(0, (int) ($collectionStats['totalOrders'] ?? 0));
        $stats['successCount'] += max(0, (int) ($collectionStats['successCount'] ?? 0));
        $stats['receivedAmount'] += max(0, (int) ($collectionStats['receivedAmount'] ?? 0));
    }

    return $stats;
}

function dent_exams_api_collection_discount_usage_map_for_ids(array $paymentsStore, array $collectionIds): array
{
    $usage = [];

    foreach ($collectionIds as $collectionId) {
        foreach (dent_exams_api_collection_discount_usage_map($paymentsStore, max(0, (int) $collectionId)) as $code => $count) {
            $normalizedCode = payments_normalize_discount_code_text((string) $code);
            if ($normalizedCode === '') {
                continue;
            }

            $usage[$normalizedCode] = max(0, (int) ($usage[$normalizedCode] ?? 0)) + max(0, (int) $count);
        }
    }

    ksort($usage);
    return $usage;
}

function dent_exams_api_collection_orders_for_ids(array $paymentsStore, array $collectionIds): array
{
    $orders = [];
    $seenIds = [];

    foreach ($collectionIds as $collectionId) {
        foreach (dent_exams_api_collection_orders($paymentsStore, max(0, (int) $collectionId)) as $order) {
            $orderId = (int) ($order['id'] ?? 0);
            if ($orderId > 0) {
                if (isset($seenIds[$orderId])) {
                    continue;
                }
                $seenIds[$orderId] = true;
            }
            $orders[] = $order;
        }
    }

    usort($orders, static function (array $left, array $right): int {
        return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
    });

    return $orders;
}

function dent_exams_api_owner_buyers_payload(array $paymentsStore, array $collectionIds): array
{
    $buyers = [];

    foreach (dent_exams_api_collection_orders_for_ids($paymentsStore, $collectionIds) as $order) {
        if ((string) ($order['status'] ?? '') !== PAYMENTS_ORDER_STATUS_SUCCESS) {
            continue;
        }

        $studentNumber = (string) ($order['payer_student_number'] ?? ($order['user_id'] ?? ''));
        $amount = max(0, (int) ($order['amount'] ?? 0));

        $buyers[] = [
            'orderId' => (int) ($order['id'] ?? 0),
            'payerName' => (string) ($order['payer_name'] ?? ''),
            'payerStudentNumber' => $studentNumber,
            'payerPhone' => (string) ($order['payer_phone'] ?? ''),
            'amount' => $amount,
            'amountLabel' => dent_exams_api_money($amount),
            'discountCode' => (string) ($order['discount_code'] ?? ''),
            'paidAt' => (string) ($order['paid_at'] ?? ($order['created_at'] ?? '')),
        ];
    }

    return $buyers;
}

function dent_exams_api_migrate_payment_group_setting(
    array $examsStore,
    string $catalogKey,
    string $courseSlug,
    array $course,
    array $setting
): array {
    $targetVersion = dent_exams_api_payment_group_version($course);
    $currentVersion = max(0, (int) ($setting['paymentGroupVersion'] ?? 0));
    if ($targetVersion <= 0 || $currentVersion >= $targetVersion) {
        return [
            'setting' => $setting,
            'changed' => false,
        ];
    }

    $nextSetting = $setting;
    $nextSetting['paymentGroupVersion'] = $targetVersion;

    $legacySlugs = dent_exams_api_payment_legacy_course_slugs($catalogKey, $course);
    $legacySettings = [];
    foreach ($legacySlugs as $legacySlug) {
        $legacySettings[] = dent_exams_course_setting($examsStore, $catalogKey, $legacySlug);
    }

    $desiredMode = trim(strtolower((string) ($course['defaultPaymentMode'] ?? 'free')));
    if (!in_array($desiredMode, ['free', 'paid'], true)) {
        $desiredMode = 'free';
    }
    $desiredAmount = max(0, (int) dent_normalize_digits((string) ($course['defaultAmount'] ?? 0)));

    $legacyHasPaymentState = false;
    $legacyCollectionId = 0;
    $legacyDiscountLists = [];
    foreach ($legacySettings as $legacySetting) {
        if ((string) ($legacySetting['paymentMode'] ?? 'free') === 'paid' || max(0, (int) ($legacySetting['collectionId'] ?? 0)) > 0) {
            $legacyHasPaymentState = true;
        }

        if ($legacyCollectionId <= 0) {
            $legacyCollectionId = max(0, (int) ($legacySetting['collectionId'] ?? 0));
        }
        $legacyDiscountLists[] = dent_exams_normalize_discount_codes($legacySetting['discountCodes'] ?? []);
    }

    if ($legacyHasPaymentState && $desiredMode === 'paid') {
        $nextSetting['paymentMode'] = 'paid';
        if (max(0, (int) ($nextSetting['amount'] ?? 0)) <= 0) {
            $nextSetting['amount'] = $desiredAmount;
        }
        if (max(0, (int) ($nextSetting['collectionId'] ?? 0)) <= 0 && $legacyCollectionId > 0) {
            $nextSetting['collectionId'] = $legacyCollectionId;
        }
        $nextSetting['discountCodes'] = dent_exams_api_merge_discount_codes(
            dent_exams_normalize_discount_codes($nextSetting['discountCodes'] ?? []),
            ...$legacyDiscountLists
        );
    }

    if ((string) ($nextSetting['paymentMode'] ?? 'free') === 'paid' && max(0, (int) ($nextSetting['amount'] ?? 0)) <= 0) {
        $nextSetting['amount'] = $desiredAmount;
    }

    $normalizedCurrent = dent_exams_normalize_course_setting($setting);
    $normalizedNext = dent_exams_normalize_course_setting($nextSetting);

    return [
        'setting' => $normalizedNext,
        'changed' => $normalizedNext !== $normalizedCurrent,
    ];
}

function dent_exams_api_collection_title_key(string $title): string
{
    $normalized = dent_utf8_strtolower(dent_normalize_digits(dent_force_utf8($title)));
    $normalized = strtr($normalized, [
        "\u{064A}" => "\u{06CC}",
        "\u{0643}" => "\u{06A9}",
    ]);

    return preg_replace('/[\s\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}\-_]+/u', '', $normalized) ?? '';
}

function dent_exams_api_setting_with_legacy_collection_ids(
    array $examsStore,
    array $paymentsStore,
    string $catalogKey,
    array $course,
    array $setting
): array {
    $nextSetting = $setting;
    $primaryCollectionId = max(0, (int) ($setting['collectionId'] ?? 0));
    $legacyCollectionIds = [];
    $seen = [];
    $titleKeys = [];
    $courseSlugs = [];

    $rememberTitle = static function ($title) use (&$titleKeys): void {
        $key = dent_exams_api_collection_title_key((string) $title);
        if ($key !== '') {
            $titleKeys[$key] = true;
        }
    };
    $rememberCollectionId = static function ($collectionId) use (
        &$legacyCollectionIds,
        &$seen,
        $primaryCollectionId
    ): void {
        $cleanCollectionId = max(0, (int) $collectionId);
        if (
            $cleanCollectionId <= 0
            || $cleanCollectionId === $primaryCollectionId
            || isset($seen[$cleanCollectionId])
        ) {
            return;
        }

        $seen[$cleanCollectionId] = true;
        $legacyCollectionIds[] = $cleanCollectionId;
    };
    $rememberCourseSlug = static function ($courseSlug) use (&$courseSlugs): void {
        $cleanCourseSlug = dent_exams_clean_course_slug((string) $courseSlug);
        if ($cleanCourseSlug !== '') {
            $courseSlugs[$cleanCourseSlug] = true;
        }
    };

    $rememberTitle($course['paymentTitle'] ?? '');
    foreach (
        is_array($course['paymentLegacyCollectionTitles'] ?? null)
            ? $course['paymentLegacyCollectionTitles']
            : [] as $legacyCollectionTitle
    ) {
        $rememberTitle($legacyCollectionTitle);
    }
    $rememberCourseSlug($course['slug'] ?? '');
    foreach (is_array($setting['legacyCollectionIds'] ?? null) ? $setting['legacyCollectionIds'] : [] as $collectionId) {
        $rememberCollectionId($collectionId);
    }

    $primaryCollection = dent_exams_api_collection_for_setting($paymentsStore, $setting);
    if (is_array($primaryCollection)) {
        $rememberTitle($primaryCollection['title'] ?? '');
    }

    foreach (dent_exams_api_payment_legacy_course_slugs($catalogKey, $course) as $legacySlug) {
        $rememberCourseSlug($legacySlug);
        $legacyCourse = dent_exams_course($catalogKey, $legacySlug);
        if (is_array($legacyCourse)) {
            $rememberTitle($legacyCourse['paymentTitle'] ?? '');
        }

        $legacySetting = dent_exams_course_setting($examsStore, $catalogKey, $legacySlug);
        $legacyCollectionId = max(0, (int) ($legacySetting['collectionId'] ?? 0));
        $rememberCollectionId($legacyCollectionId);

        if ($legacyCollectionId > 0) {
            $legacyCollectionIndex = payments_find_collection_index_by_id($paymentsStore, $legacyCollectionId);
            $legacyCollection = $legacyCollectionIndex >= 0
                ? ($paymentsStore['collections'][$legacyCollectionIndex] ?? null)
                : null;
            if (is_array($legacyCollection)) {
                $rememberTitle($legacyCollection['title'] ?? '');
            }
        }
    }

    foreach (is_array($paymentsStore['orders'] ?? null) ? $paymentsStore['orders'] : [] as $order) {
        if (!is_array($order)) {
            continue;
        }

        $extra = is_array($order['extra_form_data'] ?? null) ? $order['extra_form_data'] : [];
        if ((string) ($extra['_source'] ?? '') !== 'collection') {
            continue;
        }

        $returnPath = trim((string) ($extra['_return_path'] ?? ''));
        $query = parse_url($returnPath, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            continue;
        }

        $returnParams = [];
        parse_str($query, $returnParams);
        $returnCourseSlug = dent_exams_clean_course_slug((string) ($returnParams['course'] ?? ''));
        $returnCatalogKey = dent_exams_clean_catalog_key((string) ($returnParams['cohort'] ?? ''));
        $returnCourse = $returnCourseSlug !== ''
            ? dent_exams_course($catalogKey, $returnCourseSlug)
            : null;
        $returnBindingSlug = is_array($returnCourse)
            ? dent_exams_api_payment_binding_slug($catalogKey, $returnCourseSlug, $returnCourse)
            : '';
        $matchesCourse = isset($courseSlugs[$returnCourseSlug])
            || ($returnBindingSlug !== '' && isset($courseSlugs[$returnBindingSlug]));
        if (
            $returnCourseSlug === ''
            || !$matchesCourse
            || ($returnCatalogKey !== '' && $returnCatalogKey !== dent_exams_clean_catalog_key($catalogKey))
        ) {
            continue;
        }

        $rememberCollectionId($extra['collection_id'] ?? 0);
    }

    foreach (is_array($paymentsStore['collections'] ?? null) ? $paymentsStore['collections'] : [] as $collection) {
        if (!is_array($collection)) {
            continue;
        }

        $collectionTitleKey = dent_exams_api_collection_title_key((string) ($collection['title'] ?? ''));
        if ($collectionTitleKey !== '' && isset($titleKeys[$collectionTitleKey])) {
            $rememberCollectionId($collection['id'] ?? 0);
        }
    }

    sort($legacyCollectionIds, SORT_NUMERIC);
    $nextSetting['legacyCollectionIds'] = $legacyCollectionIds;
    return $nextSetting;
}

function dent_exams_api_owner_discount_codes_payload(array $setting, array $usageMap): array
{
    $payload = [];
    foreach (dent_exams_normalize_discount_codes($setting['discountCodes'] ?? []) as $code) {
        $normalizedCode = (string) ($code['code'] ?? '');
        $maxUses = array_key_exists('maxUses', $code) ? $code['maxUses'] : null;
        $usedCount = max(0, (int) ($usageMap[$normalizedCode] ?? 0));

        $payload[] = [
            'code' => $normalizedCode,
            'label' => (string) ($code['label'] ?? ''),
            'type' => (string) ($code['type'] ?? 'fixed'),
            'amount' => max(0, (int) ($code['amount'] ?? 0)),
            'maxUses' => is_int($maxUses) ? $maxUses : null,
            'studentNumber' => dent_normalize_student_number((string) ($code['studentNumber'] ?? '')),
            'expiresAt' => (string) ($code['expiresAt'] ?? ''),
            'isEnabled' => !array_key_exists('isEnabled', $code) || (bool) $code['isEnabled'],
            'usedCount' => $usedCount,
            'remainingUses' => is_int($maxUses) ? max(0, $maxUses - $usedCount) : null,
        ];
    }

    return $payload;
}

function dent_exams_api_collection_discount_codes_payload(array $setting): array
{
    $payload = [];
    foreach (dent_exams_normalize_discount_codes($setting['discountCodes'] ?? []) as $code) {
        $payload[] = [
            'code' => (string) ($code['code'] ?? ''),
            'label' => (string) ($code['label'] ?? ''),
            'type' => (string) ($code['type'] ?? 'fixed'),
            'amount' => max(0, (int) ($code['amount'] ?? 0)),
            'max_uses' => is_int($code['maxUses'] ?? null) ? (int) $code['maxUses'] : null,
            'student_number' => dent_normalize_student_number((string) ($code['studentNumber'] ?? '')),
            'expires_at' => (string) ($code['expiresAt'] ?? ''),
            'is_enabled' => !array_key_exists('isEnabled', $code) || (bool) $code['isEnabled'],
        ];
    }

    return $payload;
}

function dent_exams_api_paid_order_summary(?array $order): ?array
{
    if (!is_array($order)) {
        return null;
    }

    return [
        'status' => (string) ($order['status'] ?? ''),
        'amount' => max(0, (int) ($order['amount'] ?? 0)),
        'createdAt' => (string) ($order['created_at'] ?? ''),
        'paidAt' => (string) ($order['paid_at'] ?? ''),
        'verifiedAt' => (string) ($order['verified_at'] ?? ''),
        'refId' => (string) ($order['ref_id'] ?? ''),
    ];
}

function dent_exams_api_is_owner(?array $user): bool
{
    return is_array($user) && (string) ($user['role'] ?? '') === 'owner';
}

function dent_exams_api_legacy_purchase_access(
    ?array $user,
    array $course,
    string $catalogKey,
    array $examsStore,
    array $paymentsStore
): ?array {
    if (!is_array($user)) {
        return null;
    }

    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ($user['student_number'] ?? '')));
    $grants = is_array($course['legacyPurchaseAccessGrants'] ?? null)
        ? $course['legacyPurchaseAccessGrants']
        : [];
    if ($studentNumber === '' || $grants === []) {
        return null;
    }

    foreach ($grants as $grant) {
        if (!is_array($grant)) {
            continue;
        }

        $sourceCourseSlug = dent_exams_clean_course_slug((string) ($grant['courseSlug'] ?? ''));
        $purchasedBefore = trim((string) ($grant['purchasedBefore'] ?? ''));
        $sourceCourse = $sourceCourseSlug !== ''
            ? dent_exams_course($catalogKey, $sourceCourseSlug)
            : null;
        if ($sourceCourseSlug === '' || $purchasedBefore === '' || !is_array($sourceCourse)) {
            continue;
        }

        $paymentCourseSlug = dent_exams_api_payment_binding_slug($catalogKey, $sourceCourseSlug, $sourceCourse);
        $paymentCourse = dent_exams_api_payment_binding_course($catalogKey, $sourceCourseSlug, $sourceCourse);
        $sourceSetting = dent_exams_course_setting($examsStore, $catalogKey, $paymentCourseSlug);
        $sourceSetting = dent_exams_api_setting_with_legacy_collection_ids(
            $examsStore,
            $paymentsStore,
            $catalogKey,
            $paymentCourse,
            $sourceSetting
        );
        $sourceOrders = dent_exams_api_collection_orders_for_ids(
            $paymentsStore,
            dent_exams_api_setting_collection_ids($sourceSetting)
        );
        $eligibleOrder = dent_exams_find_eligible_legacy_purchase(
            $sourceOrders,
            $studentNumber,
            $purchasedBefore,
            PAYMENTS_ORDER_STATUS_SUCCESS
        );
        if ($eligibleOrder === null) {
            continue;
        }

        return [
            'order' => $eligibleOrder,
            'sourceCourseSlug' => $sourceCourseSlug,
            'unlockLabel' => trim((string) ($grant['unlockLabel'] ?? '')) ?: 'فعال از خرید قبلی',
        ];
    }

    return null;
}

function dent_exams_api_course_access(
    ?array $user,
    array $setting,
    ?array $collection,
    array $paymentsStore,
    array $course = [],
    string $catalogKey = '',
    array $examsStore = []
): array
{
    $isOwner = dent_exams_api_is_owner($user);
    $mode = (string) ($setting['paymentMode'] ?? 'free');
    $isPaidCourse = $mode === 'paid';
    $paidOrder = dent_exams_api_user_paid_order_for_any(
        $paymentsStore,
        dent_exams_api_setting_collection_ids($setting),
        $user
    );
    $isProsthesis = is_array($user) && dent_user_is_prosthesis($user);

    if ($isOwner) {
        return [
            'hasAccess' => true,
            'unlockKey' => 'owner',
            'unlockLabel' => 'دسترسی مالک',
            'requiresLogin' => false,
            'requiresPayment' => false,
            'canPurchase' => false,
            'isPaidCourse' => $isPaidCourse,
            'paidOrder' => dent_exams_api_paid_order_summary($paidOrder),
        ];
    }

    if (!$isPaidCourse) {
        return [
            'hasAccess' => true,
            'unlockKey' => 'free',
            'unlockLabel' => 'رایگان',
            'requiresLogin' => false,
            'requiresPayment' => false,
            'canPurchase' => false,
            'isPaidCourse' => false,
            'paidOrder' => dent_exams_api_paid_order_summary($paidOrder),
        ];
    }

    if ($paidOrder !== null) {
        return [
            'hasAccess' => true,
            'unlockKey' => 'paid',
            'unlockLabel' => 'پرداخت تایید شده',
            'requiresLogin' => false,
            'requiresPayment' => false,
            'canPurchase' => false,
            'isPaidCourse' => true,
            'paidOrder' => dent_exams_api_paid_order_summary($paidOrder),
        ];
    }

    $legacyAccess = dent_exams_api_legacy_purchase_access(
        $user,
        $course,
        $catalogKey,
        $examsStore,
        $paymentsStore
    );
    if ($legacyAccess !== null) {
        return [
            'hasAccess' => true,
            'unlockKey' => 'legacy-purchase',
            'unlockLabel' => (string) ($legacyAccess['unlockLabel'] ?? 'فعال از خرید قبلی'),
            'requiresLogin' => false,
            'requiresPayment' => false,
            'canPurchase' => false,
            'isPaidCourse' => true,
            'paidOrder' => dent_exams_api_paid_order_summary(
                is_array($legacyAccess['order'] ?? null) ? $legacyAccess['order'] : null
            ),
        ];
    }

    if (!is_array($user)) {
        return [
            'hasAccess' => false,
            'unlockKey' => 'login-required',
            'unlockLabel' => 'نیاز به ورود',
            'requiresLogin' => true,
            'requiresPayment' => true,
            'canPurchase' => true,
            'isPaidCourse' => true,
            'paidOrder' => null,
        ];
    }

    if ($isProsthesis) {
        return [
            'hasAccess' => false,
            'unlockKey' => 'unavailable',
            'unlockLabel' => 'غیرفعال برای این ورودی',
            'requiresLogin' => false,
            'requiresPayment' => true,
            'canPurchase' => false,
            'isPaidCourse' => true,
            'paidOrder' => null,
        ];
    }

    if ($collection === null || (string) ($collection['status'] ?? '') !== PAYMENTS_COLLECTION_STATUS_ACTIVE) {
        return [
            'hasAccess' => false,
            'unlockKey' => 'inactive',
            'unlockLabel' => 'پرداخت غیرفعال',
            'requiresLogin' => false,
            'requiresPayment' => true,
            'canPurchase' => false,
            'isPaidCourse' => true,
            'paidOrder' => null,
        ];
    }

    return [
        'hasAccess' => false,
        'unlockKey' => 'payment-required',
        'unlockLabel' => 'نیاز به پرداخت',
        'requiresLogin' => false,
        'requiresPayment' => true,
        'canPurchase' => true,
        'isPaidCourse' => true,
        'paidOrder' => null,
    ];
}

function dent_exams_api_course_summary_payload(
    string $catalogKey,
    array $course,
    array $setting,
    array $access,
    array $examsStore,
    ?array $collection,
    array $paymentsStore,
    bool $includeExams = false,
    ?array $viewer = null
): array {
    $courseSlug = (string) ($course['slug'] ?? '');
    $courseAddedAt = dent_exams_catalog_course_added_at($courseSlug, $course);
    $courseStats = dent_exams_api_course_stats($course);
    $paymentPath = '/exams/pay/?course=' . rawurlencode($courseSlug);
    $requestedCohort = dent_requested_cohort_key();
    $viewerIsOwner = dent_exams_api_is_owner($viewer);
    if ($requestedCohort !== '') {
        $paymentPath .= '&cohort=' . rawurlencode($requestedCohort);
    }

    $exams = [];
    $completedAssessmentCount = 0;
    $viewerPercents = [];
    $flaggedQuestionsCount = 0;
    if ($includeExams) {
        foreach (($course['exams'] ?? []) as $exam) {
            if (!is_array($exam)) {
                continue;
            }

            $examPath = (string) ($exam['path'] ?? '');
            $examSlug = (string) ($exam['slug'] ?? '');
            $questionCount = dent_exams_api_resolve_exam_question_count($exam);
            $isAttemptable = dent_exams_api_exam_is_attemptable($exam);
            $isComingSoon = dent_exams_api_exam_is_coming_soon($exam);
            $viewerProgress = $isAttemptable
                ? dent_exams_api_exam_progress_payload($examsStore, $catalogKey, $courseSlug, $examSlug, $viewer)
                : null;
            $assessmentReport = $isAttemptable && is_array($viewerProgress['assessmentReport'] ?? null)
                ? $viewerProgress['assessmentReport']
                : null;
            if ($isAttemptable && $assessmentReport !== null) {
                $completedAssessmentCount++;
                $viewerPercents[] = dent_exams_normalize_percent($assessmentReport['percent'] ?? 0);
            }
            if ($isAttemptable) {
                $flaggedQuestionsCount += max(0, (int) ($viewerProgress['flagsCount'] ?? 0));
            }
            $exams[] = [
                'slug' => $examSlug,
                'label' => (string) ($exam['label'] ?? ''),
                'title' => (string) ($exam['title'] ?? ''),
                'subtitle' => (string) ($exam['subtitle'] ?? ''),
                'description' => (string) ($exam['description'] ?? ''),
                'attemptable' => $isAttemptable,
                'comingSoon' => $isComingSoon,
                'emptyStateTitle' => (string) ($exam['emptyStateTitle'] ?? ''),
                'emptyStateMessage' => (string) ($exam['emptyStateMessage'] ?? ''),
                'ctaLabel' => (string) ($exam['ctaLabel'] ?? 'انتخاب حالت و شروع'),
                'questionCount' => $questionCount,
                'addedAt' => dent_exams_catalog_exam_added_at($exam, $courseAddedAt),
                'path' => $examPath,
                'href' => $access['hasAccess'] ? $examPath : $paymentPath,
                'isLocked' => !$access['hasAccess'] && (bool) ($access['isPaidCourse'] ?? false),
                'modes' => $isAttemptable ? dent_exams_api_mode_definitions() : [],
                'viewerProgress' => $viewerProgress,
            ];
        }
    }

    $collectionId = max(0, (int) ($setting['collectionId'] ?? 0));
    $collectionIds = dent_exams_api_setting_collection_ids($setting);
    $collectionStats = dent_exams_api_collection_stats_for_ids($paymentsStore, $collectionIds);
    $discountCodes = dent_exams_normalize_discount_codes($setting['discountCodes'] ?? []);
    $discountUsageMap = $viewerIsOwner
        ? dent_exams_api_collection_discount_usage_map_for_ids($paymentsStore, $collectionIds)
        : [];

    return [
        'catalogKey' => $catalogKey,
        'slug' => $courseSlug,
        'title' => (string) ($course['title'] ?? ''),
        'shortTitle' => (string) ($course['shortTitle'] ?? ''),
        'badge' => (string) ($course['badge'] ?? ''),
        'cardDescription' => (string) ($course['cardDescription'] ?? ''),
        'heroTitle' => (string) ($course['heroTitle'] ?? ''),
        'heroDescription' => (string) ($course['heroDescription'] ?? ''),
        'addedAt' => $courseAddedAt,
        'path' => (string) ($course['path'] ?? ''),
        'paymentPath' => $paymentPath,
        'paymentUrl' => dent_exams_api_absolute_url($paymentPath),
        'paymentTitle' => (string) ($course['paymentTitle'] ?? ''),
        'paymentDescription' => (string) ($course['paymentDescription'] ?? ''),
        'paymentHighlights' => dent_exams_api_payment_highlights(),
        'paymentSuccessMessage' => (string) ($course['paymentSuccessMessage'] ?? ''),
        'paymentFailureMessage' => (string) ($course['paymentFailureMessage'] ?? ''),
        'paymentMode' => (string) ($setting['paymentMode'] ?? 'free'),
        'amount' => max(0, (int) ($setting['amount'] ?? 0)),
        'amountLabel' => dent_exams_api_money((int) ($setting['amount'] ?? 0)),
        'collectionId' => $collectionId,
        'legacyCollectionIds' => array_values(array_map(static function ($value): int {
            return max(0, (int) $value);
        }, is_array($setting['legacyCollectionIds'] ?? null) ? $setting['legacyCollectionIds'] : [])),
        'collectionToken' => $collection ? (string) ($collection['token'] ?? '') : '',
        'collectionStatus' => $collection ? (string) ($collection['status'] ?? '') : '',
        'discounts' => [
            'hasCodes' => $discountCodes !== [],
        ],
        'curriculum' => dent_exams_api_course_curriculum_meta($courseSlug),
        'access' => $access,
        'supportsDirectAttemptableExams' => ($courseStats['directAttemptableExamCount'] ?? 0) > 0,
        'stats' => [
            'examCount' => $courseStats['examCount'],
            'questionCount' => $courseStats['questionCount'],
            'directAttemptableExamCount' => $courseStats['directAttemptableExamCount'] ?? 0,
            'totalOrders' => $collectionStats['totalOrders'],
            'successCount' => $viewerIsOwner ? $collectionStats['successCount'] : null,
            'receivedAmount' => $collectionStats['receivedAmount'],
            'showApprovedAccessCount' => $viewerIsOwner,
            'completedAssessmentCount' => $completedAssessmentCount,
            'viewerAveragePercent' => $viewerPercents ? round(array_sum($viewerPercents) / count($viewerPercents), 1) : null,
            'flaggedQuestionsCount' => $flaggedQuestionsCount,
        ],
        'ownerSettings' => [
            'canManage' => $viewerIsOwner,
            'updatedAt' => (string) ($setting['updatedAt'] ?? ''),
            'discountCodes' => $viewerIsOwner ? dent_exams_api_owner_discount_codes_payload($setting, $discountUsageMap) : [],
            'buyers' => $viewerIsOwner ? dent_exams_api_owner_buyers_payload($paymentsStore, $collectionIds) : [],
        ],
        'exams' => $exams,
    ];
}

function dent_exams_api_catalog_course_payload(array $course): array
{
    $curriculum = is_array($course['curriculum'] ?? null) ? $course['curriculum'] : [];
    $access = is_array($course['access'] ?? null) ? $course['access'] : [];

    return [
        'slug' => (string) ($course['slug'] ?? ''),
        'title' => (string) ($course['title'] ?? ''),
        'badge' => (string) ($course['badge'] ?? ''),
        'cardDescription' => (string) ($course['cardDescription'] ?? ''),
        'heroDescription' => (string) ($course['heroDescription'] ?? ''),
        'path' => (string) ($course['path'] ?? ''),
        'paymentPath' => (string) ($course['paymentPath'] ?? ''),
        'paymentMode' => (string) ($course['paymentMode'] ?? 'free'),
        'amountLabel' => (string) ($course['amountLabel'] ?? ''),
        'curriculum' => [
            'finalExam' => is_array($curriculum['finalExam'] ?? null) ? $curriculum['finalExam'] : null,
            'finalExams' => is_array($curriculum['finalExams'] ?? null) ? $curriculum['finalExams'] : [],
        ],
        'access' => [
            'hasAccess' => (bool) ($access['hasAccess'] ?? false),
            'requiresLogin' => (bool) ($access['requiresLogin'] ?? false),
            'canPurchase' => (bool) ($access['canPurchase'] ?? false),
            'unlockLabel' => (string) ($access['unlockLabel'] ?? ''),
        ],
        'supportsDirectAttemptableExams' => (bool) ($course['supportsDirectAttemptableExams'] ?? false),
        'stats' => is_array($course['stats'] ?? null) ? $course['stats'] : [],
    ];
}

function dent_exams_api_course_or_fail(string $catalogKey, string $courseSlug): array
{
    $course = dent_exams_course($catalogKey, $courseSlug);
    if ($course === null) {
        throw new DentExamsApiException('درس آزمون پیدا نشد.', 404);
    }

    return $course;
}

function dent_exams_api_exam_or_fail(string $catalogKey, string $courseSlug, string $examSlug): array
{
    $exam = dent_exams_exam($catalogKey, $courseSlug, $examSlug);
    if ($exam === null) {
        throw new DentExamsApiException('آزمون موردنظر پیدا نشد.', 404);
    }

    return $exam;
}

function dent_exams_api_collection_base_payload(array $course, array $setting, ?array $currentCollection = null): array
{
    $existingAmount = max(0, (int) ($currentCollection['amount'] ?? 0));
    $desiredAmount = max(0, (int) ($setting['amount'] ?? 0));
    $mode = (string) ($setting['paymentMode'] ?? 'free');
    $amount = $mode === 'paid'
        ? max(1, $desiredAmount)
        : max(1, $existingAmount > 0 ? $existingAmount : $desiredAmount);
    $now = dent_iso_now();

    return [
        'title' => (string) ($course['paymentTitle'] ?? $course['title'] ?? 'پرداخت آزمون'),
        'description' => (string) ($course['paymentDescription'] ?? ''),
        'image_url' => '',
        'amount' => $amount,
        'discount_codes' => dent_exams_api_collection_discount_codes_payload($setting),
        'status' => $mode === 'paid' ? PAYMENTS_COLLECTION_STATUS_ACTIVE : PAYMENTS_COLLECTION_STATUS_INACTIVE,
        'gateway' => '',
        'allow_guest_payments' => false,
        'collect_payer_name' => false,
        'collect_payer_phone' => false,
        'collect_payer_student_number' => false,
        'success_message' => (string) ($course['paymentSuccessMessage'] ?? ''),
        'failure_message' => (string) ($course['paymentFailureMessage'] ?? ''),
        'updated_at' => $now,
    ];
}

function dent_exams_api_sync_collection(string $catalogKey, string $courseSlug, array $course, array $setting): int
{
    $existingCollectionId = max(0, (int) ($setting['collectionId'] ?? 0));
    $mode = (string) ($setting['paymentMode'] ?? 'free');

    if ($existingCollectionId <= 0 && $mode !== 'paid') {
        return 0;
    }

    return payments_with_store_lock(static function (array &$paymentsStore) use (
        $existingCollectionId,
        $course,
        $setting
    ): int {
        $collectionIndex = $existingCollectionId > 0
            ? payments_find_collection_index_by_id($paymentsStore, $existingCollectionId)
            : -1;
        $current = $collectionIndex >= 0 && is_array($paymentsStore['collections'][$collectionIndex] ?? null)
            ? $paymentsStore['collections'][$collectionIndex]
            : null;
        $payload = dent_exams_api_collection_base_payload($course, $setting, is_array($current) ? $current : null);

        if ($collectionIndex >= 0 && is_array($current)) {
            $payload = array_merge($current, $payload, [
                'id' => (int) ($current['id'] ?? $existingCollectionId),
                'token' => (string) ($current['token'] ?? payments_random_token(12)),
                'created_at' => (string) ($current['created_at'] ?? dent_iso_now()),
            ]);
            $paymentsStore['collections'][$collectionIndex] = $payload;
            return (int) ($payload['id'] ?? $existingCollectionId);
        }

        $newId = payments_next_collection_id($paymentsStore);
        $payload = array_merge($payload, [
            'id' => $newId,
            'token' => payments_random_token(12),
            'created_at' => dent_iso_now(),
        ]);
        $paymentsStore['collections'][] = $payload;
        return $newId;
    });
}

function dent_exams_api_persist_course_setting(string $catalogKey, string $courseSlug, array $setting): void
{
    $normalizedSetting = dent_exams_normalize_course_setting($setting);
    dent_exams_with_store_lock(static function (array &$store) use ($catalogKey, $courseSlug, $normalizedSetting): void {
        $courseKey = dent_exams_course_key($catalogKey, $courseSlug);
        if ($courseKey === '') {
            throw new DentExamsApiException('شناسه داخلی درس معتبر نیست.', 422);
        }

        $store['courseSettings'][$courseKey] = $normalizedSetting;
    });
}

function dent_exams_api_resolve_course_setting(
    array $examsStore,
    array &$paymentsStore,
    string $catalogKey,
    string $courseSlug,
    array $course
): array {
    $paymentCourseSlug = dent_exams_api_payment_binding_slug($catalogKey, $courseSlug, $course);
    $paymentCourse = dent_exams_api_payment_binding_course($catalogKey, $courseSlug, $course);
    $setting = dent_exams_course_setting($examsStore, $catalogKey, $paymentCourseSlug);

    $migration = dent_exams_api_migrate_payment_group_setting(
        $examsStore,
        $catalogKey,
        $paymentCourseSlug,
        $paymentCourse,
        $setting
    );
    $setting = is_array($migration['setting'] ?? null) ? $migration['setting'] : $setting;
    $settingChanged = !empty($migration['changed']);
    if ($settingChanged) {
        $setting['updatedAt'] = dent_iso_now();
        dent_exams_api_persist_course_setting($catalogKey, $paymentCourseSlug, $setting);
    }

    $setting = dent_exams_api_setting_with_legacy_collection_ids(
        $examsStore,
        $paymentsStore,
        $catalogKey,
        $paymentCourse,
        $setting
    );
    if ((string) ($setting['paymentMode'] ?? 'free') !== 'paid') {
        return $setting;
    }

    $collection = dent_exams_api_collection_for_setting($paymentsStore, $setting);
    if ($collection !== null && !$settingChanged) {
        return $setting;
    }

    $nextSetting = $setting;
    $nextSetting['updatedAt'] = dent_iso_now();
    $collectionId = dent_exams_api_sync_collection($catalogKey, $paymentCourseSlug, $paymentCourse, $nextSetting);
    if ($collectionId <= 0) {
        return $setting;
    }

    $nextSetting['collectionId'] = $collectionId;
    dent_exams_api_persist_course_setting($catalogKey, $paymentCourseSlug, $nextSetting);
    $paymentsStore = payments_read_store();
    $nextSetting = dent_exams_api_setting_with_legacy_collection_ids(
        $examsStore,
        $paymentsStore,
        $catalogKey,
        $paymentCourse,
        $nextSetting
    );

    return $nextSetting;
}

function dent_exams_api_current_course_summary(
    string $catalogKey,
    string $courseSlug,
    ?array $viewer = null,
    bool $viewerProvided = false
): array
{
    $user = $viewerProvided ? $viewer : dent_current_user();
    $course = dent_exams_api_apply_runtime_course_override(dent_exams_api_course_or_fail($catalogKey, $courseSlug));
    $examsStore = dent_exams_read_store();
    $paymentsStore = payments_read_store();
    $setting = dent_exams_api_resolve_course_setting($examsStore, $paymentsStore, $catalogKey, $courseSlug, $course);
    $collection = dent_exams_api_collection_for_setting($paymentsStore, $setting);
    $access = dent_exams_api_course_access($user, $setting, $collection, $paymentsStore, $course, $catalogKey, $examsStore);
    return dent_exams_api_course_summary_payload($catalogKey, $course, $setting, $access, $examsStore, $collection, $paymentsStore, true, $user);
}

$action = dent_clean_text((string) ($_REQUEST['action'] ?? ''), 60);
$catalogKey = dent_exams_resolve_catalog_key(dent_requested_cohort_key());

if ($action === 'catalog') {
    dent_exams_api_require_method(['GET']);

    $user = dent_current_user();
    dent_release_session_lock();

    $catalog = dent_exams_catalog($catalogKey);
    if ($catalog === null) {
        dent_error('کاتالوگ آزمون‌ها پیدا نشد.', 404);
    }
    $examsStore = dent_exams_read_store();
    $paymentsStore = payments_read_store();
    $coursePayloadLookup = [];
    $courseRows = [];
    $courseIndex = 0;
    foreach (($catalog['courses'] ?? []) as $courseSlug => $course) {
        if (!is_array($course)) {
            continue;
        }
        $course = dent_exams_api_apply_runtime_course_override($course);
        $setting = dent_exams_api_resolve_course_setting($examsStore, $paymentsStore, $catalogKey, (string) $courseSlug, $course);
        $collection = dent_exams_api_collection_for_setting($paymentsStore, $setting);
        $access = dent_exams_api_course_access($user, $setting, $collection, $paymentsStore, $course, $catalogKey, $examsStore);
        $payload = dent_exams_api_course_summary_payload($catalogKey, $course, $setting, $access, $examsStore, $collection, $paymentsStore, false, $user);
        $catalogPayload = dent_exams_api_catalog_course_payload($payload);
        $coursePayloadLookup[dent_exams_clean_course_slug((string) $courseSlug)] = $catalogPayload;

        if (!dent_exams_api_course_is_catalog_visible($course)) {
            continue;
        }

        $courseRows[] = [
            'sortIndex' => $courseIndex++,
            'addedAtTimestamp' => strtotime((string) ($payload['addedAt'] ?? '')) ?: 0,
            'payload' => $catalogPayload,
        ];
    }
    usort($courseRows, static function (array $left, array $right): int {
        $dateOrder = (int) ($right['addedAtTimestamp'] ?? 0) <=> (int) ($left['addedAtTimestamp'] ?? 0);
        if ($dateOrder !== 0) {
            return $dateOrder;
        }
        return (int) ($right['sortIndex'] ?? 0) <=> (int) ($left['sortIndex'] ?? 0);
    });
    $courses = array_values(array_map(static function (array $row): array {
        return is_array($row['payload'] ?? null) ? $row['payload'] : [];
    }, $courseRows));
    $homeActiveCourses = dent_dentistry_select_home_active_exam_courses($courses, 2);

    dent_json_response([
        'success' => true,
        'catalog' => [
            'catalogKey' => $catalogKey,
            'requestedCohort' => dent_requested_cohort_key(),
            'title' => (string) ($catalog['title'] ?? 'آزمون‌ها'),
            'description' => (string) ($catalog['description'] ?? ''),
            'curriculum' => dent_exams_api_curriculum_payload($coursePayloadLookup),
            'referenceCatalog' => dent_exams_api_reference_catalog_payload($coursePayloadLookup),
            'homeActiveCourses' => $homeActiveCourses,
            'courses' => $courses,
        ],
        'viewer' => $user ? dent_public_user($user) : null,
    ]);
}

if ($action === 'course') {
    dent_exams_api_require_method(['GET']);

    $courseSlug = dent_exams_clean_course_slug((string) ($_GET['course'] ?? ''));
    if ($courseSlug === '') {
        dent_error('شناسه درس آزمون معتبر نیست.', 422);
    }

    $user = dent_current_user();
    dent_release_session_lock();

    try {
        $payload = dent_exams_api_current_course_summary($catalogKey, $courseSlug, $user, true);
    } catch (DentExamsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode(), $error->payload());
    }

    dent_json_response([
        'success' => true,
        'course' => $payload,
        'viewer' => $user ? dent_public_user($user) : null,
    ]);
}

if ($action === 'exam') {
    dent_exams_api_require_method(['GET']);

    $courseSlug = dent_exams_clean_course_slug((string) ($_GET['course'] ?? ''));
    $examSlug = trim((string) ($_GET['exam'] ?? ''));
    if ($courseSlug === '' || $examSlug === '') {
        dent_error('شناسه آزمون معتبر نیست.', 422);
    }

    $user = dent_current_user();
    dent_release_session_lock();

    try {
        $course = dent_exams_api_apply_runtime_course_override(dent_exams_api_course_or_fail($catalogKey, $courseSlug));
        $exam = dent_exams_api_apply_runtime_exam_override(
            dent_exams_api_exam_or_fail($catalogKey, $courseSlug, $examSlug),
            $catalogKey,
            $courseSlug,
            false
        );
    } catch (DentExamsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode(), $error->payload());
    }
    $examsStore = dent_exams_read_store();
    $paymentsStore = payments_read_store();
    $setting = dent_exams_api_resolve_course_setting($examsStore, $paymentsStore, $catalogKey, $courseSlug, $course);
    $collection = dent_exams_api_collection_for_setting($paymentsStore, $setting);
    $access = dent_exams_api_course_access($user, $setting, $collection, $paymentsStore, $course, $catalogKey, $examsStore);
    $coursePayload = dent_exams_api_course_summary_payload($catalogKey, $course, $setting, $access, $examsStore, $collection, $paymentsStore, false, $user);
    if ((bool) ($access['hasAccess'] ?? false)) {
        $exam = dent_exams_api_apply_runtime_exam_override($exam, $catalogKey, $courseSlug, true);
    }

    if ((bool) ($access['hasAccess'] ?? false) && !dent_exams_api_exam_is_attemptable($exam)) {
        dent_error('این بخش آزمون مستقیمی ندارد. از گزینه «مشاهده بخش» وارد زیرمجموعه‌های آن شوید.', 422, [
            'course' => $coursePayload,
            'entryPath' => (string) ($exam['path'] ?? ''),
        ]);
    }

    if (!(bool) ($access['hasAccess'] ?? false)) {
        if ((bool) ($access['requiresLogin'] ?? false)) {
            dent_error('برای مشاهده این آزمون باید وارد حساب کاربری شوید.', 401, [
                'loggedOut' => true,
                'course' => $coursePayload,
            ]);
        }

        dent_error('برای مشاهده سوال‌های این درس باید دسترسی آن را فعال کنید.', 403, [
            'requiresPayment' => true,
            'course' => $coursePayload,
        ]);
    }

    dent_json_response([
        'success' => true,
        'course' => $coursePayload,
        'exam' => dent_exams_api_exam_payload($examsStore, $paymentsStore, $catalogKey, $course, $exam, $user),
        'viewer' => $user ? dent_public_user($user) : null,
    ]);
}

if ($action === 'saveFlags') {
    dent_exams_api_require_method(['POST']);

    $user = dent_require_user();
    dent_release_session_lock();
    $courseSlug = dent_exams_clean_course_slug((string) ($_POST['course'] ?? ''));
    $examSlug = dent_exams_clean_exam_slug((string) ($_POST['exam'] ?? ''));
    if ($courseSlug === '' || $examSlug === '') {
        dent_error('شناسه آزمون معتبر نیست.', 422);
    }

    try {
        $course = dent_exams_api_apply_runtime_course_override(dent_exams_api_course_or_fail($catalogKey, $courseSlug));
        $exam = dent_exams_api_apply_runtime_exam_override(
            dent_exams_api_exam_or_fail($catalogKey, $courseSlug, $examSlug),
            $catalogKey,
            $courseSlug,
            false
        );
    } catch (DentExamsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode(), $error->payload());
    }

    $examsStore = dent_exams_read_store();
    $paymentsStore = payments_read_store();
    $setting = dent_exams_api_resolve_course_setting($examsStore, $paymentsStore, $catalogKey, $courseSlug, $course);
    $collection = dent_exams_api_collection_for_setting($paymentsStore, $setting);
    $access = dent_exams_api_course_access($user, $setting, $collection, $paymentsStore, $course, $catalogKey, $examsStore);
    if (!(bool) ($access['hasAccess'] ?? false)) {
        dent_error('برای ثبت نشان‌دارها ابتدا باید به این آزمون دسترسی داشته باشید.', 403);
    }

    $questionCount = dent_exams_api_resolve_exam_question_count($exam);
    $requestedIndexes = dent_exams_api_parse_flag_indexes_param($_POST['flaggedQuestionIndexes'] ?? ($_POST['flags'] ?? []));
    $flaggedIndexes = array_values(array_filter($requestedIndexes, static function (int $index) use ($questionCount): bool {
        return $index >= 0 && $index < $questionCount;
    }));
    $activityMode = dent_exams_api_normalize_activity_mode((string) ($_POST['mode'] ?? 'view'));
    $touchedAt = dent_iso_now();
    $participantKey = dent_exams_api_viewer_key($user);
    $examKey = dent_exams_exam_key($catalogKey, $courseSlug, $examSlug);
    if ($participantKey === '' || $examKey === '') {
        dent_error('امکان ثبت نشان‌دارهای این آزمون وجود ندارد.', 422);
    }

    dent_exams_with_store_lock(static function (array &$store) use ($examKey, $participantKey, $flaggedIndexes, $activityMode, $touchedAt): void {
        $records = is_array($store['examRecords'] ?? null) ? $store['examRecords'] : [];
        $record = dent_exams_normalize_exam_record(is_array($records[$examKey] ?? null) ? $records[$examKey] : []);
        if ($flaggedIndexes) {
            $record['flagsByUser'][$participantKey] = $flaggedIndexes;
        } else {
            unset($record['flagsByUser'][$participantKey]);
        }
        $record['activityByUser'][$participantKey] = [
            'lastMode' => $activityMode,
            'updatedAt' => $touchedAt,
        ];

        $store['examRecords'][$examKey] = $record;
    });

    dent_json_response([
        'success' => true,
        'flaggedQuestionIndexes' => $flaggedIndexes,
        'message' => 'نشان‌دارهای این آزمون ذخیره شد.',
    ]);
}

if ($action === 'saveStudyState') {
    dent_exams_api_require_method(['POST']);

    $user = dent_require_user();
    dent_release_session_lock();
    $courseSlug = dent_exams_clean_course_slug((string) ($_POST['course'] ?? ''));
    $examSlug = dent_exams_clean_exam_slug((string) ($_POST['exam'] ?? ''));
    if ($courseSlug === '' || $examSlug === '') {
        dent_error('شناسه آزمون معتبر نیست.', 422);
    }

    try {
        $course = dent_exams_api_apply_runtime_course_override(dent_exams_api_course_or_fail($catalogKey, $courseSlug));
        $exam = dent_exams_api_apply_runtime_exam_override(
            dent_exams_api_exam_or_fail($catalogKey, $courseSlug, $examSlug),
            $catalogKey,
            $courseSlug,
            false
        );
    } catch (DentExamsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode(), $error->payload());
    }

    $examsStore = dent_exams_read_store();
    $paymentsStore = payments_read_store();
    $setting = dent_exams_api_resolve_course_setting($examsStore, $paymentsStore, $catalogKey, $courseSlug, $course);
    $collection = dent_exams_api_collection_for_setting($paymentsStore, $setting);
    $access = dent_exams_api_course_access($user, $setting, $collection, $paymentsStore, $course, $catalogKey, $examsStore);
    if (!(bool) ($access['hasAccess'] ?? false)) {
        dent_error('برای ذخیره ابزارهای مطالعه ابتدا باید به این آزمون دسترسی داشته باشید.', 403);
    }

    $rawState = $_POST['studyState'] ?? [];
    if (is_string($rawState)) {
        $decoded = json_decode($rawState, true);
        $rawState = is_array($decoded) ? $decoded : [];
    }
    $studyState = dent_exams_normalize_study_state(is_array($rawState) ? $rawState : []);
    $questionCount = dent_exams_api_resolve_exam_question_count($exam);
    foreach (['notesByQuestion', 'struckOptionsByQuestion', 'highlightsByQuestion'] as $mapKey) {
        $map = is_array($studyState[$mapKey] ?? null) ? $studyState[$mapKey] : [];
        $studyState[$mapKey] = array_filter($map, static function ($value, $key) use ($questionCount): bool {
            $index = (int) $key;
            return $index >= 0 && $index < $questionCount;
        }, ARRAY_FILTER_USE_BOTH);
    }
    $studyState['mistakeQuestionIndexes'] = array_values(array_filter(
        dent_exams_normalize_question_index_list($studyState['mistakeQuestionIndexes'] ?? []),
        static function (int $index) use ($questionCount): bool {
            return $index >= 0 && $index < $questionCount;
        }
    ));
    $studyState['updatedAt'] = dent_iso_now();

    $participantKey = dent_exams_api_viewer_key($user);
    $examKey = dent_exams_exam_key($catalogKey, $courseSlug, $examSlug);
    if ($participantKey === '' || $examKey === '') {
        dent_error('امکان ذخیره ابزارهای مطالعه این آزمون وجود ندارد.', 422);
    }

    dent_exams_with_store_lock(static function (array &$store) use ($examKey, $participantKey, $studyState): void {
        $records = is_array($store['examRecords'] ?? null) ? $store['examRecords'] : [];
        $record = dent_exams_normalize_exam_record(is_array($records[$examKey] ?? null) ? $records[$examKey] : []);
        $record['studyStateByUser'][$participantKey] = $studyState;
        $store['examRecords'][$examKey] = $record;
    });

    dent_json_response([
        'success' => true,
        'studyState' => $studyState,
        'message' => 'یادداشت‌ها و ابزارهای مطالعه ذخیره شد.',
    ]);
}

if ($action === 'touchExamActivity') {
    dent_exams_api_require_method(['POST']);

    $user = dent_require_user();
    dent_release_session_lock();
    $courseSlug = dent_exams_clean_course_slug((string) ($_POST['course'] ?? ''));
    $examSlug = dent_exams_clean_exam_slug((string) ($_POST['exam'] ?? ''));
    if ($courseSlug === '' || $examSlug === '') {
        dent_error('شناسه آزمون معتبر نیست.', 422);
    }

    try {
        $course = dent_exams_api_apply_runtime_course_override(dent_exams_api_course_or_fail($catalogKey, $courseSlug));
        dent_exams_api_apply_runtime_exam_override(
            dent_exams_api_exam_or_fail($catalogKey, $courseSlug, $examSlug),
            $catalogKey,
            $courseSlug,
            false
        );
    } catch (DentExamsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode(), $error->payload());
    }

    $examsStore = dent_exams_read_store();
    $paymentsStore = payments_read_store();
    $setting = dent_exams_api_resolve_course_setting($examsStore, $paymentsStore, $catalogKey, $courseSlug, $course);
    $collection = dent_exams_api_collection_for_setting($paymentsStore, $setting);
    $access = dent_exams_api_course_access($user, $setting, $collection, $paymentsStore, $course, $catalogKey, $examsStore);
    if (!(bool) ($access['hasAccess'] ?? false)) {
        dent_error('برای ثبت فعالیت این آزمون باید ابتدا به آن دسترسی داشته باشید.', 403);
    }

    $participantKey = dent_exams_api_viewer_key($user);
    $examKey = dent_exams_exam_key($catalogKey, $courseSlug, $examSlug);
    if ($participantKey === '' || $examKey === '') {
        dent_error('امکان ثبت فعالیت این آزمون وجود ندارد.', 422);
    }

    $activityMode = dent_exams_api_normalize_activity_mode((string) ($_POST['mode'] ?? 'view'));
    $touchedAt = dent_iso_now();

    dent_exams_with_store_lock(static function (array &$store) use ($examKey, $participantKey, $activityMode, $touchedAt): void {
        $records = is_array($store['examRecords'] ?? null) ? $store['examRecords'] : [];
        $record = dent_exams_normalize_exam_record(is_array($records[$examKey] ?? null) ? $records[$examKey] : []);
        $record['activityByUser'][$participantKey] = [
            'lastMode' => $activityMode,
            'updatedAt' => $touchedAt,
        ];
        $store['examRecords'][$examKey] = $record;
    });

    $freshStore = dent_exams_read_store();
    dent_json_response([
        'success' => true,
        'activity' => dent_exams_activity_for_user($freshStore, $catalogKey, $courseSlug, $examSlug, $participantKey),
        'viewerProgress' => dent_exams_api_exam_progress_payload($freshStore, $catalogKey, $courseSlug, $examSlug, $user),
    ]);
}

if ($action === 'submitAssessment') {
    dent_exams_api_require_method(['POST']);

    $user = dent_require_user();
    dent_release_session_lock();
    $courseSlug = dent_exams_clean_course_slug((string) ($_POST['course'] ?? ''));
    $examSlug = dent_exams_clean_exam_slug((string) ($_POST['exam'] ?? ''));
    if ($courseSlug === '' || $examSlug === '') {
        dent_error('شناسه آزمون معتبر نیست.', 422);
    }

    try {
        $course = dent_exams_api_apply_runtime_course_override(dent_exams_api_course_or_fail($catalogKey, $courseSlug));
        $exam = dent_exams_api_apply_runtime_exam_override(
            dent_exams_api_exam_or_fail($catalogKey, $courseSlug, $examSlug),
            $catalogKey,
            $courseSlug,
            false
        );
    } catch (DentExamsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode(), $error->payload());
    }

    $examsStore = dent_exams_read_store();
    $paymentsStore = payments_read_store();
    $setting = dent_exams_api_resolve_course_setting($examsStore, $paymentsStore, $catalogKey, $courseSlug, $course);
    $collection = dent_exams_api_collection_for_setting($paymentsStore, $setting);
    $access = dent_exams_api_course_access($user, $setting, $collection, $paymentsStore, $course, $catalogKey, $examsStore);
    if (!(bool) ($access['hasAccess'] ?? false)) {
        dent_error('برای ثبت کارنامه باید ابتدا به این آزمون دسترسی داشته باشید.', 403);
    }

    $exam = dent_exams_api_apply_runtime_exam_override($exam, $catalogKey, $courseSlug, true);
    $questions = is_array($exam['questions'] ?? null) ? $exam['questions'] : [];
    if (!$questions) {
        dent_error('برای این آزمون هنوز سوالی ثبت نشده است.', 422);
    }

    $answers = dent_exams_api_clamp_answers($questions, dent_exams_api_parse_answers_param($_POST['answers'] ?? []));
    $startedAt = dent_exams_normalize_datetime_string((string) ($_POST['startedAt'] ?? dent_iso_now()), dent_iso_now());
    $submittedAt = dent_iso_now();
    $score = dent_exams_api_score_answers($questions, $answers);
    $report = [
        'answers' => $score['answers'],
        'totalQuestions' => $score['totalQuestions'],
        'correct' => $score['correct'],
        'wrong' => $score['wrong'],
        'unanswered' => $score['unanswered'],
        'percent' => $score['percent'],
        'startedAt' => $startedAt,
        'submittedAt' => $submittedAt,
        'updatedAt' => $submittedAt,
        'topicBreakdown' => dent_exams_api_topic_breakdown($exam, $questions, $score['answers']),
        'mistakeQuestionIndexes' => dent_exams_api_mistake_indexes($questions, $score['answers']),
    ];

    $participantKey = dent_exams_api_viewer_key($user);
    $examKey = dent_exams_exam_key($catalogKey, $courseSlug, $examSlug);
    if ($participantKey === '' || $examKey === '') {
        dent_error('امکان ثبت کارنامه این آزمون وجود ندارد.', 422);
    }

    dent_exams_with_store_lock(static function (array &$store) use ($examKey, $participantKey, $report, $submittedAt): void {
        $records = is_array($store['examRecords'] ?? null) ? $store['examRecords'] : [];
        $record = dent_exams_normalize_exam_record(is_array($records[$examKey] ?? null) ? $records[$examKey] : []);
        $normalizedReport = dent_exams_normalize_assessment_report($report);
        $record['reportsByUser'][$participantKey] = $normalizedReport;
        $history = dent_exams_normalize_attempt_history($record['attemptsByUser'][$participantKey] ?? []);
        $history[] = $normalizedReport;
        $record['attemptsByUser'][$participantKey] = dent_exams_normalize_attempt_history($history);
        $studyState = dent_exams_normalize_study_state($record['studyStateByUser'][$participantKey] ?? []);
        $studyState['mistakeQuestionIndexes'] = dent_exams_normalize_question_index_list(array_merge(
            $studyState['mistakeQuestionIndexes'] ?? [],
            $normalizedReport['mistakeQuestionIndexes'] ?? []
        ));
        $studyState['updatedAt'] = $submittedAt;
        $record['studyStateByUser'][$participantKey] = $studyState;
        $record['activityByUser'][$participantKey] = [
            'lastMode' => 'assessment',
            'updatedAt' => $submittedAt,
        ];
        $store['examRecords'][$examKey] = $record;
    });

    $freshStore = dent_exams_read_store();
    $savedReport = dent_exams_report_for_user($freshStore, $catalogKey, $courseSlug, $examSlug, $participantKey);
    if (!is_array($savedReport)) {
        dent_error('کارنامه آزمون ذخیره نشد.', 500);
    }

    dent_json_response([
        'success' => true,
        'report' => array_merge(
            dent_exams_api_report_summary_payload($freshStore, $catalogKey, $courseSlug, $examSlug, $participantKey, $savedReport),
            [
                'answers' => dent_exams_api_clamp_answers($questions, is_array($savedReport['answers'] ?? null) ? $savedReport['answers'] : []),
                'attemptHistory' => dent_exams_api_attempt_history_payload(dent_exams_attempts_for_user($freshStore, $catalogKey, $courseSlug, $examSlug, $participantKey)),
                'studyState' => dent_exams_study_state_for_user($freshStore, $catalogKey, $courseSlug, $examSlug, $participantKey),
            ]
        ),
        'message' => 'کارنامه این آزمون ثبت و ذخیره شد.',
    ]);
}

if ($action === 'resetAssessment') {
    dent_exams_api_require_method(['POST']);

    $user = dent_require_user();
    dent_release_session_lock();
    $courseSlug = dent_exams_clean_course_slug((string) ($_POST['course'] ?? ''));
    $examSlug = dent_exams_clean_exam_slug((string) ($_POST['exam'] ?? ''));
    if ($courseSlug === '' || $examSlug === '') {
        dent_error('شناسه آزمون معتبر نیست.', 422);
    }

    try {
        $course = dent_exams_api_apply_runtime_course_override(dent_exams_api_course_or_fail($catalogKey, $courseSlug));
    } catch (DentExamsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode(), $error->payload());
    }

    $examsStore = dent_exams_read_store();
    $paymentsStore = payments_read_store();
    $setting = dent_exams_api_resolve_course_setting($examsStore, $paymentsStore, $catalogKey, $courseSlug, $course);
    $collection = dent_exams_api_collection_for_setting($paymentsStore, $setting);
    $access = dent_exams_api_course_access($user, $setting, $collection, $paymentsStore, $course, $catalogKey, $examsStore);
    if (!(bool) ($access['hasAccess'] ?? false)) {
        dent_error('برای ریست کارنامه باید ابتدا به این آزمون دسترسی داشته باشید.', 403);
    }

    $participantKey = dent_exams_api_viewer_key($user);
    $examKey = dent_exams_exam_key($catalogKey, $courseSlug, $examSlug);
    if ($participantKey === '' || $examKey === '') {
        dent_error('امکان ریست کارنامه این آزمون وجود ندارد.', 422);
    }

    dent_exams_with_store_lock(static function (array &$store) use ($examKey, $participantKey): void {
        $records = is_array($store['examRecords'] ?? null) ? $store['examRecords'] : [];
        $record = dent_exams_normalize_exam_record(is_array($records[$examKey] ?? null) ? $records[$examKey] : []);
        unset($record['reportsByUser'][$participantKey]);
        $record['activityByUser'][$participantKey] = [
            'lastMode' => 'assessment',
            'updatedAt' => dent_iso_now(),
        ];
        $store['examRecords'][$examKey] = $record;
    });

    dent_json_response([
        'success' => true,
        'message' => 'کارنامه این آزمون ریست شد و می‌توانید دوباره آزمون بدهید.',
    ]);
}

if ($action === 'ownerSaveCourseAccess') {
    dent_exams_api_require_method(['POST']);
    dent_require_owner();
    dent_release_session_lock();

    $courseSlug = dent_exams_clean_course_slug((string) ($_POST['course'] ?? ''));
    if ($courseSlug === '') {
        dent_error('شناسه درس آزمون معتبر نیست.', 422);
    }

    try {
        $course = dent_exams_api_course_or_fail($catalogKey, $courseSlug);
    } catch (DentExamsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode(), $error->payload());
    }

    $rawMode = trim(strtolower((string) ($_POST['paymentMode'] ?? '')));
    if ($rawMode === '') {
        $isPaid = dent_parse_bool($_POST['isPaid'] ?? false, false);
        $rawMode = $isPaid ? 'paid' : 'free';
    }
    if (!in_array($rawMode, ['free', 'paid'], true)) {
        dent_error('حالت دسترسی آزمون معتبر نیست.', 422);
    }

    $amount = max(0, (int) dent_normalize_digits((string) ($_POST['amount'] ?? 0)));
    if ($rawMode === 'paid' && $amount <= 0) {
        dent_error('برای درس پولی باید مبلغ معتبر ثبت شود.', 422);
    }

    $paymentCourseSlug = dent_exams_api_payment_binding_slug($catalogKey, $courseSlug, $course);
    $paymentCourse = dent_exams_api_payment_binding_course($catalogKey, $courseSlug, $course);
    $paymentGroupVersion = dent_exams_api_payment_group_version($paymentCourse);
    $currentStore = dent_exams_read_store();
    $currentSetting = dent_exams_course_setting($currentStore, $catalogKey, $paymentCourseSlug);
    $discountCodes = array_key_exists('discountCodes', $_POST) || array_key_exists('discount_codes', $_POST)
        ? dent_exams_api_parse_discount_codes_input($_POST['discountCodes'] ?? ($_POST['discount_codes'] ?? []))
        : dent_exams_normalize_discount_codes($currentSetting['discountCodes'] ?? []);
    $nextSetting = [
        'paymentMode' => $rawMode,
        'amount' => $amount,
        'collectionId' => max(0, (int) ($currentSetting['collectionId'] ?? 0)),
        'discountCodes' => $discountCodes,
        'paymentGroupVersion' => $paymentGroupVersion,
        'updatedAt' => dent_iso_now(),
    ];
    $collectionId = dent_exams_api_sync_collection($catalogKey, $paymentCourseSlug, $paymentCourse, $nextSetting);
    if ($collectionId > 0) {
        $nextSetting['collectionId'] = $collectionId;
    }

    dent_exams_with_store_lock(static function (array &$store) use ($catalogKey, $paymentCourseSlug, $nextSetting): void {
        $courseKey = dent_exams_course_key($catalogKey, $paymentCourseSlug);
        if ($courseKey === '') {
            throw new DentExamsApiException('شناسه داخلی درس معتبر نیست.', 422);
        }

        $store['courseSettings'][$courseKey] = $nextSetting;
    });

    dent_json_response([
        'success' => true,
        'course' => dent_exams_api_current_course_summary($catalogKey, $courseSlug),
        'message' => $rawMode === 'paid'
            ? 'پرداخت این درس فعال شد و مبلغ جدید ذخیره شد.'
            : 'این درس به‌صورت رایگان تنظیم شد.',
    ]);
}

dent_error('درخواست نامعتبر است.', 404);
