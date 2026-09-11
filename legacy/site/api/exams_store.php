<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';
require_once __DIR__ . '/exams_bank.php';
require_once __DIR__ . '/exams_catalog_timeline.php';

if (!defined('DENT_EXAMS_SCHEMA_VERSION')) {
    define('DENT_EXAMS_SCHEMA_VERSION', 5);
}

function dent_exams_store_path(): string
{
    return dent_storage_path('exams/store.json');
}

function dent_exams_lock_path(): string
{
    return dent_storage_path('exams/store.lock');
}

function dent_exams_default_store(): array
{
    return [
        'schemaVersion' => DENT_EXAMS_SCHEMA_VERSION,
        'courseSettings' => [],
        'examRecords' => [],
    ];
}

function dent_exams_normalize_datetime_string(string $value, ?string $fallback = null): string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return $fallback ?? dent_iso_now();
    }

    $parsed = strtotime($trimmed);
    if ($parsed === false) {
        return $fallback ?? dent_iso_now();
    }

    return date('c', $parsed);
}

function dent_exams_ensure_storage(): void
{
    dent_ensure_directory(dirname(dent_exams_store_path()));

    if (!is_file(dent_exams_store_path())) {
        dent_write_json_file(dent_exams_store_path(), dent_exams_default_store());
    }
}

/**
 * Holds the in-memory copy of the exams store for the lifetime of the
 * current request, so repeated reads (including the "read the fresh state
 * back after a write" pattern used across exams_api.php) don't re-read and
 * re-normalize the store file each time.
 *
 * @return array|null
 */
function &dent_exams_store_cache_slot()
{
    static $cache = null;
    return $cache;
}

function dent_exams_read_store(): array
{
    $cache =& dent_exams_store_cache_slot();
    if (is_array($cache)) {
        return $cache;
    }

    dent_exams_ensure_storage();

    $lock = fopen(dent_exams_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل فضای ذخیره‌سازی آزمون‌ها.', 500);
    }

    try {
        if (!flock($lock, LOCK_SH)) {
            throw new RuntimeException('Unable to acquire shared lock.');
        }

        $store = dent_exams_load_store_unlocked();
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }

    $cache = $store;

    return $store;
}

/**
 * @template T
 * @param callable(array):T $callback
 * @return T
 */
function dent_exams_with_store_lock(callable $callback)
{
    dent_exams_ensure_storage();

    $lock = fopen(dent_exams_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل فضای ذخیره‌سازی آزمون‌ها.', 500);
    }

    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('Unable to acquire exclusive lock.');
        }

        $store = dent_exams_load_store_unlocked();
        $result = $callback($store);
        $normalized = dent_exams_save_store_unlocked($store);

        $cache =& dent_exams_store_cache_slot();
        $cache = $normalized;

        return $result;
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function dent_exams_load_store_unlocked(): array
{
    $raw = dent_read_json_file(dent_exams_store_path(), dent_exams_default_store());
    if (!isset($raw['courseSettings'], $raw['examRecords']) || !is_array($raw['courseSettings']) || !is_array($raw['examRecords'])) {
        throw new DentJsonPersistenceException(
            'EXAMS_STORE_SCHEMA_INVALID',
            'Existing exam store has an invalid schema'
        );
    }

    return dent_exams_normalize_store($raw);
}

function dent_exams_save_store_unlocked(array $store): array
{
    $normalized = dent_exams_normalize_store($store);
    dent_write_json_file(dent_exams_store_path(), $normalized);

    return $normalized;
}

function dent_exams_normalize_store(array $store): array
{
    $settingsRaw = $store['courseSettings'] ?? [];
    if (!is_array($settingsRaw)) {
        $settingsRaw = [];
    }

    $normalizedSettings = [];
    foreach ($settingsRaw as $key => $value) {
        $courseKey = dent_exams_clean_course_key((string) $key);
        if ($courseKey === '' || !is_array($value)) {
            continue;
        }

        $normalizedSettings[$courseKey] = dent_exams_normalize_course_setting($value);
    }
    ksort($normalizedSettings);

    $recordsRaw = $store['examRecords'] ?? [];
    if (!is_array($recordsRaw)) {
        $recordsRaw = [];
    }

    $normalizedRecords = [];
    foreach ($recordsRaw as $key => $value) {
        $examKey = dent_exams_clean_exam_key((string) $key);
        if ($examKey === '' || !is_array($value)) {
            continue;
        }

        $normalizedRecords[$examKey] = dent_exams_normalize_exam_record($value);
    }
    ksort($normalizedRecords);

    return [
        'schemaVersion' => DENT_EXAMS_SCHEMA_VERSION,
        'courseSettings' => $normalizedSettings,
        'examRecords' => $normalizedRecords,
    ];
}

function dent_exams_clean_course_slug(string $value): string
{
    $value = trim(strtolower($value));
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^a-z0-9_-]+/', '', $value) ?? '';
    return substr($value, 0, 80);
}

function dent_exams_clean_catalog_key(string $value): string
{
    $value = trim(strtolower($value));
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^a-z0-9_-]+/', '', $value) ?? '';
    return substr($value, 0, 80);
}

function dent_exams_clean_exam_slug(string $value): string
{
    $value = trim(strtolower($value));
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^a-z0-9_-]+/', '', $value) ?? '';
    return substr($value, 0, 120);
}

function dent_exams_clean_course_key(string $value): string
{
    $value = trim(strtolower($value));
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^a-z0-9:_-]+/', '', $value) ?? '';
    return substr($value, 0, 160);
}

function dent_exams_clean_exam_key(string $value): string
{
    $value = trim(strtolower($value));
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^a-z0-9:_-]+/', '', $value) ?? '';
    return substr($value, 0, 220);
}

function dent_exams_clean_participant_key(string $value): string
{
    return dent_normalize_student_number($value);
}

function dent_exams_course_key(string $catalogKey, string $courseSlug): string
{
    $cleanCatalog = dent_exams_clean_catalog_key($catalogKey);
    $cleanCourse = dent_exams_clean_course_slug($courseSlug);
    if ($cleanCatalog === '' || $cleanCourse === '') {
        return '';
    }

    return $cleanCatalog . ':' . $cleanCourse;
}

function dent_exams_exam_key(string $catalogKey, string $courseSlug, string $examSlug): string
{
    $cleanCatalog = dent_exams_clean_catalog_key($catalogKey);
    $cleanCourse = dent_exams_clean_course_slug($courseSlug);
    $cleanExam = dent_exams_clean_exam_slug($examSlug);
    if ($cleanCatalog === '' || $cleanCourse === '' || $cleanExam === '') {
        return '';
    }

    return $cleanCatalog . ':' . $cleanCourse . ':' . $cleanExam;
}

function dent_exams_clean_discount_code(string $value): string
{
    $value = dent_clean_text($value, 40);
    if ($value === '') {
        return '';
    }

    return strtoupper(preg_replace('/\s+/u', '', $value) ?? '');
}

function dent_exams_normalize_discount_codes($value): array
{
    $codes = [];
    if (is_array($value)) {
        $codes = $value;
    } elseif (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed !== '') {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $codes = $decoded;
            }
        }
    }

    $normalized = [];
    $seen = [];
    foreach ($codes as $code) {
        if (!is_array($code)) {
            continue;
        }

        $rawCode = dent_exams_clean_discount_code((string) ($code['code'] ?? ''));
        if ($rawCode === '' || isset($seen[$rawCode])) {
            continue;
        }

        $type = trim(strtolower((string) ($code['type'] ?? 'fixed')));
        if (!in_array($type, ['fixed', 'percent'], true)) {
            $type = 'fixed';
        }

        $amount = max(0, (int) dent_normalize_digits((string) ($code['amount'] ?? 0)));
        if ($amount <= 0) {
            continue;
        }
        if ($type === 'percent') {
            $amount = min(95, $amount);
        }

        $maxUsesRaw = $code['maxUses'] ?? ($code['max_uses'] ?? null);
        $maxUses = null;
        if ($maxUsesRaw !== null && $maxUsesRaw !== '') {
            $maxUses = max(1, min(1000000, (int) dent_normalize_digits((string) $maxUsesRaw)));
        }

        $enabledRaw = $code['isEnabled'] ?? ($code['is_enabled'] ?? true);
        $enabled = filter_var($enabledRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        $normalized[] = [
            'code' => $rawCode,
            'label' => dent_clean_text((string) ($code['label'] ?? ''), 120),
            'type' => $type,
            'amount' => $amount,
            'maxUses' => $maxUses,
            'studentNumber' => dent_normalize_student_number((string) ($code['studentNumber'] ?? ($code['student_number'] ?? ''))),
            'expiresAt' => dent_exams_normalize_datetime_string((string) ($code['expiresAt'] ?? ($code['expires_at'] ?? '')), ''),
            'isEnabled' => $enabled !== false,
        ];
        $seen[$rawCode] = true;

        if (count($normalized) >= 30) {
            break;
        }
    }

    return $normalized;
}

function dent_exams_normalize_course_setting(array $value): array
{
    $paymentMode = trim(strtolower((string) ($value['paymentMode'] ?? ($value['payment_mode'] ?? 'free'))));
    if (!in_array($paymentMode, ['free', 'paid'], true)) {
        $paymentMode = 'free';
    }

    return [
        'paymentMode' => $paymentMode,
        'amount' => max(0, (int) dent_normalize_digits((string) ($value['amount'] ?? 0))),
        'collectionId' => max(0, (int) ($value['collectionId'] ?? ($value['collection_id'] ?? 0))),
        'discountCodes' => dent_exams_normalize_discount_codes($value['discountCodes'] ?? ($value['discount_codes'] ?? [])),
        'paymentGroupVersion' => max(0, (int) ($value['paymentGroupVersion'] ?? ($value['payment_group_version'] ?? 0))),
        'updatedAt' => dent_exams_normalize_datetime_string((string) ($value['updatedAt'] ?? ($value['updated_at'] ?? dent_iso_now())), dent_iso_now()),
    ];
}

function dent_exams_legacy_purchase_effective_timestamp(array $order): int
{
    foreach (['paid_at', 'verified_at', 'created_at'] as $field) {
        $value = trim((string) ($order[$field] ?? ''));
        if ($value === '') {
            continue;
        }

        $timestamp = strtotime($value);
        if ($timestamp !== false && $timestamp > 0) {
            return $timestamp;
        }
    }

    return 0;
}

function dent_exams_find_eligible_legacy_purchase(
    array $orders,
    string $studentNumber,
    string $purchasedBefore,
    string $successStatus = 'success'
): ?array {
    $normalizedStudentNumber = dent_normalize_student_number($studentNumber);
    $cutoffTimestamp = strtotime(trim($purchasedBefore));
    if ($normalizedStudentNumber === '' || $cutoffTimestamp === false || $cutoffTimestamp <= 0) {
        return null;
    }

    $eligibleOrder = null;
    $eligibleTimestamp = 0;
    foreach ($orders as $order) {
        if (!is_array($order) || (string) ($order['status'] ?? '') !== $successStatus) {
            continue;
        }

        $matchesUser = $normalizedStudentNumber === dent_normalize_student_number((string) ($order['user_id'] ?? ''))
            || $normalizedStudentNumber === dent_normalize_student_number((string) ($order['payer_student_number'] ?? ''));
        if (!$matchesUser) {
            continue;
        }

        $orderTimestamp = dent_exams_legacy_purchase_effective_timestamp($order);
        if ($orderTimestamp <= 0 || $orderTimestamp > $cutoffTimestamp || $orderTimestamp < $eligibleTimestamp) {
            continue;
        }

        $eligibleOrder = $order;
        $eligibleTimestamp = $orderTimestamp;
    }

    return $eligibleOrder;
}

function dent_exams_default_course_setting(?array $course = null): array
{
    $paymentMode = trim(strtolower((string) ($course['defaultPaymentMode'] ?? 'free')));
    if (!in_array($paymentMode, ['free', 'paid'], true)) {
        $paymentMode = 'free';
    }

    $amount = max(0, (int) dent_normalize_digits((string) ($course['defaultAmount'] ?? 0)));

    return [
        'paymentMode' => $paymentMode,
        'amount' => $amount,
        'collectionId' => 0,
        'discountCodes' => dent_exams_normalize_discount_codes($course['defaultDiscountCodes'] ?? []),
        'paymentGroupVersion' => 0,
        'updatedAt' => dent_iso_now(),
    ];
}

function dent_exams_normalize_question_index_list($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $indexes = [];
    foreach ($value as $item) {
        $normalized = dent_normalize_digits((string) $item);
        if ($normalized === '' || !preg_match('/^-?\d+$/', $normalized)) {
            continue;
        }

        $index = (int) $normalized;
        if ($index < 0 || $index > 5000) {
            continue;
        }

        $indexes[] = $index;
    }

    $indexes = array_values(array_unique($indexes));
    sort($indexes, SORT_NUMERIC);
    return $indexes;
}

function dent_exams_normalize_answer_list($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $answers = [];
    foreach ($value as $item) {
        if ($item === null || $item === '') {
            $answers[] = null;
            continue;
        }

        $normalized = dent_normalize_digits((string) $item);
        if ($normalized === '' || !preg_match('/^-?\d+$/', $normalized)) {
            $answers[] = null;
            continue;
        }

        $answer = (int) $normalized;
        $answers[] = $answer >= 0 && $answer <= 32 ? $answer : null;
    }

    return array_slice($answers, 0, 5000);
}

function dent_exams_normalize_percent($value): float
{
    $percent = (float) $value;
    if ($percent < 0) {
        $percent = 0;
    }
    if ($percent > 100) {
        $percent = 100;
    }

    return round($percent, 1);
}

function dent_exams_normalize_assessment_report(array $value): array
{
    $answers = dent_exams_normalize_answer_list($value['answers'] ?? []);
    $reportedTotal = max(0, (int) ($value['totalQuestions'] ?? ($value['total_questions'] ?? 0)));
    $correct = max(0, (int) ($value['correct'] ?? 0));
    $wrong = max(0, (int) ($value['wrong'] ?? 0));
    $unanswered = max(0, (int) ($value['unanswered'] ?? 0));
    $scoreTotal = $correct + $wrong + $unanswered;
    $totalQuestions = max($reportedTotal, $scoreTotal, count($answers));

    if ($totalQuestions > 0 && $scoreTotal < $totalQuestions) {
        $unanswered = max(0, $totalQuestions - $correct - $wrong);
    } elseif ($scoreTotal > $totalQuestions) {
        $totalQuestions = $scoreTotal;
    }

    $percent = array_key_exists('percent', $value)
        ? dent_exams_normalize_percent($value['percent'])
        : ($totalQuestions > 0 ? round(($correct / $totalQuestions) * 100, 1) : 0.0);

    $submittedFallback = dent_exams_normalize_datetime_string((string) ($value['submittedAt'] ?? ($value['submitted_at'] ?? dent_iso_now())), dent_iso_now());
    $startedFallback = dent_exams_normalize_datetime_string((string) ($value['startedAt'] ?? ($value['started_at'] ?? $submittedFallback)), $submittedFallback);

    $attemptId = trim(strtolower((string) ($value['attemptId'] ?? ($value['attempt_id'] ?? ''))));
    $attemptId = preg_replace('/[^a-z0-9_-]+/', '', $attemptId) ?? '';
    if ($attemptId === '') {
        $attemptId = 'attempt_' . substr(hash('sha256', $submittedFallback . '|' . json_encode($answers)), 0, 20);
    }

    return [
        'attemptId' => substr($attemptId, 0, 80),
        'answers' => $answers,
        'totalQuestions' => $totalQuestions,
        'correct' => $correct,
        'wrong' => $wrong,
        'unanswered' => $unanswered,
        'percent' => $percent,
        'startedAt' => dent_exams_normalize_datetime_string((string) ($value['startedAt'] ?? ($value['started_at'] ?? $startedFallback)), $startedFallback),
        'submittedAt' => $submittedFallback,
        'updatedAt' => dent_exams_normalize_datetime_string((string) ($value['updatedAt'] ?? ($value['updated_at'] ?? $submittedFallback)), $submittedFallback),
        'topicBreakdown' => dent_exams_normalize_topic_breakdown($value['topicBreakdown'] ?? ($value['topic_breakdown'] ?? [])),
        'mistakeQuestionIndexes' => dent_exams_normalize_question_index_list($value['mistakeQuestionIndexes'] ?? ($value['mistake_question_indexes'] ?? [])),
    ];
}

function dent_exams_normalize_topic_breakdown($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $normalized = [];
    foreach ($value as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $label = dent_clean_text((string) ($entry['label'] ?? ($entry['topic'] ?? '')), 160);
        if ($label === '') {
            continue;
        }
        $correct = max(0, (int) ($entry['correct'] ?? 0));
        $wrong = max(0, (int) ($entry['wrong'] ?? 0));
        $unanswered = max(0, (int) ($entry['unanswered'] ?? 0));
        $total = max($correct + $wrong + $unanswered, max(0, (int) ($entry['total'] ?? 0)));
        $normalized[] = [
            'label' => $label,
            'total' => $total,
            'correct' => $correct,
            'wrong' => $wrong,
            'unanswered' => $unanswered,
            'percent' => $total > 0
                ? dent_exams_normalize_percent($entry['percent'] ?? (($correct / $total) * 100))
                : 0.0,
        ];
        if (count($normalized) >= 80) {
            break;
        }
    }

    return $normalized;
}

function dent_exams_normalize_attempt_history($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $attempts = [];
    $seen = [];
    foreach ($value as $attempt) {
        if (!is_array($attempt)) {
            continue;
        }
        $normalized = dent_exams_normalize_assessment_report($attempt);
        $attemptId = (string) ($normalized['attemptId'] ?? '');
        if ($attemptId === '' || isset($seen[$attemptId])) {
            continue;
        }
        $seen[$attemptId] = true;
        $attempts[] = $normalized;
    }

    usort($attempts, static function (array $left, array $right): int {
        return strcmp((string) ($left['submittedAt'] ?? ''), (string) ($right['submittedAt'] ?? ''));
    });

    return array_slice($attempts, -50);
}

function dent_exams_normalize_study_state($value): array
{
    $value = is_array($value) ? $value : [];
    $notesRaw = is_array($value['notesByQuestion'] ?? null) ? $value['notesByQuestion'] : [];
    $notes = [];
    foreach ($notesRaw as $questionIndex => $note) {
        $index = (int) dent_normalize_digits((string) $questionIndex);
        $cleanNote = dent_clean_text((string) $note, 4000);
        if ($index < 0 || $index > 5000 || $cleanNote === '') {
            continue;
        }
        $notes[(string) $index] = $cleanNote;
    }

    $struckRaw = is_array($value['struckOptionsByQuestion'] ?? null) ? $value['struckOptionsByQuestion'] : [];
    $struck = [];
    foreach ($struckRaw as $questionIndex => $optionIndexes) {
        $index = (int) dent_normalize_digits((string) $questionIndex);
        if ($index < 0 || $index > 5000 || !is_array($optionIndexes)) {
            continue;
        }
        $cleanOptions = [];
        foreach ($optionIndexes as $optionIndex) {
            $option = (int) dent_normalize_digits((string) $optionIndex);
            if ($option >= 0 && $option <= 32) {
                $cleanOptions[] = $option;
            }
        }
        $cleanOptions = array_values(array_unique($cleanOptions));
        sort($cleanOptions, SORT_NUMERIC);
        if ($cleanOptions) {
            $struck[(string) $index] = $cleanOptions;
        }
    }

    $highlightsRaw = is_array($value['highlightsByQuestion'] ?? null) ? $value['highlightsByQuestion'] : [];
    $highlights = [];
    foreach ($highlightsRaw as $questionIndex => $items) {
        $index = (int) dent_normalize_digits((string) $questionIndex);
        if ($index < 0 || $index > 5000 || !is_array($items)) {
            continue;
        }
        $cleanItems = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $start = max(0, (int) ($item['start'] ?? 0));
            $end = max($start, (int) ($item['end'] ?? $start));
            if ($end <= $start || $end - $start > 2000) {
                continue;
            }
            $cleanItems[] = ['start' => $start, 'end' => $end];
            if (count($cleanItems) >= 20) {
                break;
            }
        }
        if ($cleanItems) {
            $highlights[(string) $index] = $cleanItems;
        }
    }

    return [
        'notesByQuestion' => $notes,
        'struckOptionsByQuestion' => $struck,
        'highlightsByQuestion' => $highlights,
        'mistakeQuestionIndexes' => dent_exams_normalize_question_index_list($value['mistakeQuestionIndexes'] ?? []),
        'updatedAt' => dent_exams_normalize_datetime_string(
            (string) ($value['updatedAt'] ?? '1970-01-01T00:00:00+00:00'),
            '1970-01-01T00:00:00+00:00'
        ),
    ];
}

function dent_exams_default_exam_record(): array
{
    return [
        'flagsByUser' => [],
        'reportsByUser' => [],
        'attemptsByUser' => [],
        'studyStateByUser' => [],
        'activityByUser' => [],
    ];
}

function dent_exams_normalize_exam_activity(array $value): array
{
    $lastMode = trim(strtolower((string) ($value['lastMode'] ?? ($value['last_mode'] ?? 'view'))));
    if (!in_array($lastMode, ['view', 'assessment', 'learning'], true)) {
        $lastMode = 'view';
    }

    return [
        'lastMode' => $lastMode,
        'updatedAt' => dent_exams_normalize_datetime_string(
            (string) ($value['updatedAt'] ?? ($value['updated_at'] ?? dent_iso_now())),
            dent_iso_now()
        ),
    ];
}

function dent_exams_normalize_exam_record(array $value): array
{
    $flagsRaw = $value['flagsByUser'] ?? ($value['flags_by_user'] ?? []);
    if (!is_array($flagsRaw)) {
        $flagsRaw = [];
    }

    $normalizedFlags = [];
    foreach ($flagsRaw as $participantKey => $indexes) {
        $cleanParticipant = dent_exams_clean_participant_key((string) $participantKey);
        if ($cleanParticipant === '') {
            continue;
        }

        $normalizedFlags[$cleanParticipant] = dent_exams_normalize_question_index_list($indexes);
    }
    ksort($normalizedFlags);

    $reportsRaw = $value['reportsByUser'] ?? ($value['reports_by_user'] ?? []);
    if (!is_array($reportsRaw)) {
        $reportsRaw = [];
    }

    $normalizedReports = [];
    foreach ($reportsRaw as $participantKey => $report) {
        $cleanParticipant = dent_exams_clean_participant_key((string) $participantKey);
        if ($cleanParticipant === '' || !is_array($report)) {
            continue;
        }

        $normalizedReports[$cleanParticipant] = dent_exams_normalize_assessment_report($report);
    }
    ksort($normalizedReports);

    $attemptsRaw = $value['attemptsByUser'] ?? ($value['attempts_by_user'] ?? []);
    if (!is_array($attemptsRaw)) {
        $attemptsRaw = [];
    }
    $normalizedAttempts = [];
    foreach ($attemptsRaw as $participantKey => $attempts) {
        $cleanParticipant = dent_exams_clean_participant_key((string) $participantKey);
        if ($cleanParticipant === '') {
            continue;
        }
        $history = dent_exams_normalize_attempt_history($attempts);
        if ($history) {
            $normalizedAttempts[$cleanParticipant] = $history;
        }
    }
    foreach ($normalizedReports as $participantKey => $report) {
        if (!isset($normalizedAttempts[$participantKey]) || !$normalizedAttempts[$participantKey]) {
            $normalizedAttempts[$participantKey] = [$report];
        }
    }
    ksort($normalizedAttempts);

    $studyRaw = $value['studyStateByUser'] ?? ($value['study_state_by_user'] ?? []);
    if (!is_array($studyRaw)) {
        $studyRaw = [];
    }
    $normalizedStudy = [];
    foreach ($studyRaw as $participantKey => $studyState) {
        $cleanParticipant = dent_exams_clean_participant_key((string) $participantKey);
        if ($cleanParticipant === '' || !is_array($studyState)) {
            continue;
        }
        $normalizedStudy[$cleanParticipant] = dent_exams_normalize_study_state($studyState);
    }
    ksort($normalizedStudy);

    $activityRaw = $value['activityByUser'] ?? ($value['activity_by_user'] ?? []);
    if (!is_array($activityRaw)) {
        $activityRaw = [];
    }

    $normalizedActivity = [];
    foreach ($activityRaw as $participantKey => $activity) {
        $cleanParticipant = dent_exams_clean_participant_key((string) $participantKey);
        if ($cleanParticipant === '' || !is_array($activity)) {
            continue;
        }

        $normalizedActivity[$cleanParticipant] = dent_exams_normalize_exam_activity($activity);
    }
    ksort($normalizedActivity);

    return [
        'flagsByUser' => $normalizedFlags,
        'reportsByUser' => $normalizedReports,
        'attemptsByUser' => $normalizedAttempts,
        'studyStateByUser' => $normalizedStudy,
        'activityByUser' => $normalizedActivity,
    ];
}

function dent_exams_course_default_setting(string $catalogKey, string $courseSlug): array
{
    $course = dent_exams_course($catalogKey, $courseSlug);
    if (!is_array($course)) {
        return dent_exams_default_course_setting();
    }

    return dent_exams_default_course_setting($course);
}

function dent_exams_catalogs(): array
{
    $bank = dent_exams_bank();
    $catalogs = $bank['catalogs'] ?? [];
    return is_array($catalogs) ? $catalogs : [];
}

function dent_exams_resolve_catalog_key(?string $cohortKey = null): string
{
    $requested = dent_clean_cohort_key($cohortKey ?? dent_requested_cohort_key());
    $catalogs = dent_exams_catalogs();
    if ($requested !== '' && isset($catalogs[$requested]) && is_array($catalogs[$requested])) {
        return $requested;
    }
    if (isset($catalogs['shared']) && is_array($catalogs['shared'])) {
        return 'shared';
    }

    foreach ($catalogs as $key => $catalog) {
        if (is_array($catalog)) {
            return (string) $key;
        }
    }

    return '';
}

function dent_exams_catalog(string $catalogKey): ?array
{
    $catalogs = dent_exams_catalogs();
    $cleanKey = dent_exams_clean_catalog_key($catalogKey);
    $catalog = $cleanKey !== '' ? ($catalogs[$cleanKey] ?? null) : null;
    return is_array($catalog) ? $catalog : null;
}

function dent_exams_course(string $catalogKey, string $courseSlug): ?array
{
    $catalog = dent_exams_catalog($catalogKey);
    if ($catalog === null) {
        return null;
    }

    $courses = $catalog['courses'] ?? [];
    if (!is_array($courses)) {
        return null;
    }

    $course = $courses[dent_exams_clean_course_slug($courseSlug)] ?? null;
    return is_array($course) ? $course : null;
}

function dent_exams_exam(string $catalogKey, string $courseSlug, string $examSlug): ?array
{
    $course = dent_exams_course($catalogKey, $courseSlug);
    if ($course === null) {
        return null;
    }

    $exams = $course['exams'] ?? [];
    if (!is_array($exams)) {
        return null;
    }

    $cleanExamSlug = dent_exams_clean_exam_slug($examSlug);
    foreach ($exams as $exam) {
        if (!is_array($exam)) {
            continue;
        }
        if (dent_exams_clean_exam_slug((string) ($exam['slug'] ?? '')) === $cleanExamSlug) {
            return $exam;
        }
    }

    return null;
}

function dent_exams_course_setting(array $store, string $catalogKey, string $courseSlug): array
{
    $courseKey = dent_exams_course_key($catalogKey, $courseSlug);
    if ($courseKey === '') {
        return dent_exams_course_default_setting($catalogKey, $courseSlug);
    }

    $settings = $store['courseSettings'] ?? [];
    $current = $settings[$courseKey] ?? null;
    if (!is_array($current)) {
        return dent_exams_course_default_setting($catalogKey, $courseSlug);
    }

    return dent_exams_normalize_course_setting($current);
}

function dent_exams_record(array $store, string $catalogKey, string $courseSlug, string $examSlug): array
{
    $examKey = dent_exams_exam_key($catalogKey, $courseSlug, $examSlug);
    if ($examKey === '') {
        return dent_exams_default_exam_record();
    }

    $records = $store['examRecords'] ?? [];
    $record = $records[$examKey] ?? null;
    if (!is_array($record)) {
        return dent_exams_default_exam_record();
    }

    return dent_exams_normalize_exam_record($record);
}

function dent_exams_report_for_user(array $store, string $catalogKey, string $courseSlug, string $examSlug, string $participantKey): ?array
{
    $cleanParticipant = dent_exams_clean_participant_key($participantKey);
    if ($cleanParticipant === '') {
        return null;
    }

    $record = dent_exams_record($store, $catalogKey, $courseSlug, $examSlug);
    $report = $record['reportsByUser'][$cleanParticipant] ?? null;
    return is_array($report) ? dent_exams_normalize_assessment_report($report) : null;
}

function dent_exams_flags_for_user(array $store, string $catalogKey, string $courseSlug, string $examSlug, string $participantKey): array
{
    $cleanParticipant = dent_exams_clean_participant_key($participantKey);
    if ($cleanParticipant === '') {
        return [];
    }

    $record = dent_exams_record($store, $catalogKey, $courseSlug, $examSlug);
    return dent_exams_normalize_question_index_list($record['flagsByUser'][$cleanParticipant] ?? []);
}

function dent_exams_reports_by_user(array $store, string $catalogKey, string $courseSlug, string $examSlug): array
{
    $record = dent_exams_record($store, $catalogKey, $courseSlug, $examSlug);
    $reports = $record['reportsByUser'] ?? [];
    return is_array($reports) ? $reports : [];
}

function dent_exams_attempts_for_user(array $store, string $catalogKey, string $courseSlug, string $examSlug, string $participantKey): array
{
    $cleanParticipant = dent_exams_clean_participant_key($participantKey);
    if ($cleanParticipant === '') {
        return [];
    }
    $record = dent_exams_record($store, $catalogKey, $courseSlug, $examSlug);
    return dent_exams_normalize_attempt_history($record['attemptsByUser'][$cleanParticipant] ?? []);
}

function dent_exams_study_state_for_user(array $store, string $catalogKey, string $courseSlug, string $examSlug, string $participantKey): array
{
    $cleanParticipant = dent_exams_clean_participant_key($participantKey);
    if ($cleanParticipant === '') {
        return dent_exams_normalize_study_state([]);
    }
    $record = dent_exams_record($store, $catalogKey, $courseSlug, $examSlug);
    return dent_exams_normalize_study_state($record['studyStateByUser'][$cleanParticipant] ?? []);
}

function dent_exams_learning_summary_index(array $store): array
{
    $index = [];
    $records = is_array($store['examRecords'] ?? null) ? $store['examRecords'] : [];
    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }
        $normalized = dent_exams_normalize_exam_record($record);
        $participantKeys = array_values(array_unique(array_merge(
            array_keys(is_array($normalized['attemptsByUser'] ?? null) ? $normalized['attemptsByUser'] : []),
            array_keys(is_array($normalized['studyStateByUser'] ?? null) ? $normalized['studyStateByUser'] : [])
        )));
        foreach ($participantKeys as $participantKey) {
            $cleanParticipant = dent_exams_clean_participant_key((string) $participantKey);
            if ($cleanParticipant === '') {
                continue;
            }
            if (!isset($index[$cleanParticipant])) {
                $index[$cleanParticipant] = [
                    'examCount' => 0,
                    'attemptCount' => 0,
                    'noteCount' => 0,
                    'highlightCount' => 0,
                    'struckOptionCount' => 0,
                    'mistakeQuestionCount' => 0,
                ];
            }
            $attempts = dent_exams_normalize_attempt_history($normalized['attemptsByUser'][$cleanParticipant] ?? []);
            $study = isset($normalized['studyStateByUser'][$cleanParticipant])
                ? dent_exams_normalize_study_state($normalized['studyStateByUser'][$cleanParticipant])
                : null;
            $index[$cleanParticipant]['examCount']++;
            $index[$cleanParticipant]['attemptCount'] += count($attempts);
            if ($study === null) {
                continue;
            }
            $index[$cleanParticipant]['noteCount'] += count($study['notesByQuestion'] ?? []);
            foreach (($study['highlightsByQuestion'] ?? []) as $items) {
                $index[$cleanParticipant]['highlightCount'] += is_array($items) ? count($items) : 0;
            }
            foreach (($study['struckOptionsByQuestion'] ?? []) as $items) {
                $index[$cleanParticipant]['struckOptionCount'] += is_array($items) ? count($items) : 0;
            }
            $index[$cleanParticipant]['mistakeQuestionCount'] += count($study['mistakeQuestionIndexes'] ?? []);
        }
    }
    ksort($index);
    return $index;
}

function dent_exams_user_learning_summary(array $store, string $participantKey): array
{
    $cleanParticipant = dent_exams_clean_participant_key($participantKey);
    $empty = [
        'examCount' => 0,
        'attemptCount' => 0,
        'noteCount' => 0,
        'highlightCount' => 0,
        'struckOptionCount' => 0,
        'mistakeQuestionCount' => 0,
    ];
    if ($cleanParticipant === '') {
        return $empty;
    }
    $index = dent_exams_learning_summary_index($store);
    return is_array($index[$cleanParticipant] ?? null) ? $index[$cleanParticipant] : $empty;
}

function dent_exams_activity_for_user(array $store, string $catalogKey, string $courseSlug, string $examSlug, string $participantKey): ?array
{
    $cleanParticipant = dent_exams_clean_participant_key($participantKey);
    if ($cleanParticipant === '') {
        return null;
    }

    $record = dent_exams_record($store, $catalogKey, $courseSlug, $examSlug);
    $activity = $record['activityByUser'][$cleanParticipant] ?? null;
    return is_array($activity) ? dent_exams_normalize_exam_activity($activity) : null;
}
