<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';

function dent_grades_clean_cohort(?string $value): string
{
    $value = dent_clean_cohort_key((string) $value);
    return $value !== '' ? $value : dent_primary_cohort_key();
}

function dent_grades_requested_cohort(): string
{
    if (array_key_exists('cohort', $_POST)) {
        return dent_clean_cohort_key((string) $_POST['cohort']);
    }
    if (array_key_exists('cohort', $_GET)) {
        return dent_clean_cohort_key((string) $_GET['cohort']);
    }

    return '';
}

function dent_grades_set_active_cohort(string $cohort): void
{
    $GLOBALS['dent_grades_active_cohort'] = dent_grades_clean_cohort($cohort);
}

function dent_grades_active_cohort(): string
{
    return dent_grades_clean_cohort((string) ($GLOBALS['dent_grades_active_cohort'] ?? dent_primary_cohort_key()));
}

function dent_grades_store_path(): string
{
    $cohortKey = dent_grades_active_cohort();
    if ($cohortKey === dent_prosthesis_legacy_cohort_key()) {
        return dent_storage_path('grades/prosthesis_1402_grades.csv');
    }
    if ($cohortKey === dent_primary_cohort_key()) {
        return dent_storage_path('grades/grades.csv');
    }

    return dent_storage_path('grades/' . dent_cohort_storage_slug($cohortKey) . '_grades.csv');
}

function dent_grades_meta_path(): string
{
    $cohortKey = dent_grades_active_cohort();
    if ($cohortKey === dent_prosthesis_legacy_cohort_key()) {
        return dent_storage_path('grades/prosthesis_1402_meta.json');
    }
    if ($cohortKey === dent_primary_cohort_key()) {
        return dent_storage_path('grades/meta.json');
    }

    return dent_storage_path('grades/' . dent_cohort_storage_slug($cohortKey) . '_meta.json');
}

function dent_grades_require_user(): array
{
    $user = dent_require_user();
    $requestedCohort = dent_grades_requested_cohort();
    $activeCohort = $requestedCohort !== ''
        ? dent_resolve_accessible_cohort($user, $requestedCohort)
        : dent_user_cohort_key($user);

    dent_grades_set_active_cohort($activeCohort);
    return $user;
}
function dent_grades_prosthesis_roster_rows(): array
{
    $rows = [];
    if (!function_exists('dent_prosthesis_1402_roster')) {
        return $rows;
    }

    foreach (dent_prosthesis_1402_roster() as $studentNumber => $entry) {
        $studentNumber = dent_normalize_student_number((string) $studentNumber);
        if ($studentNumber === '') {
            continue;
        }
        $name = trim(
            dent_clean_text((string) ($entry['firstName'] ?? ''), 60)
            . ' '
            . dent_clean_text((string) ($entry['lastName'] ?? ''), 60)
        );
        $rows[] = [$studentNumber, $name !== '' ? $name : $studentNumber];
    }

    return $rows;
}

function dent_grades_missing_source(): array
{
    $cohortKey = dent_grades_active_cohort();
    if ($cohortKey === dent_prosthesis_legacy_cohort_key()) {
        return [
            'header' => ['StudentID', 'Name'],
            'rows' => dent_grades_prosthesis_roster_rows(),
            'idIndex' => 0,
            'nameIndex' => 1,
            'gradeColumns' => [],
            'statsAccumulator' => [],
        ];
    }

    if ($cohortKey !== dent_primary_cohort_key()) {
        $store = dent_load_user_store();
        $rows = [];
        foreach (($store['users'] ?? []) as $studentNumber => $user) {
            if (!is_array($user) || dent_user_cohort_key($user) !== $cohortKey) {
                continue;
            }
            $rows[] = [
                dent_normalize_student_number((string) ($user['studentNumber'] ?? $studentNumber)),
                trim((string) ($user['name'] ?? $studentNumber)),
            ];
        }

        return [
            'header' => ['StudentID', 'Name'],
            'rows' => $rows,
            'idIndex' => 0,
            'nameIndex' => 1,
            'gradeColumns' => [],
            'statsAccumulator' => [],
        ];
    }

    return dent_empty_grades_source();
}

function dent_empty_grades_source(): array
{
    return [
        'header' => [],
        'rows' => [],
        'idIndex' => 0,
        'nameIndex' => 0,
        'gradeColumns' => [],
        'statsAccumulator' => [],
    ];
}

function dent_normalize_header_label(?string $label): string
{
    $label = (string) $label;
    $label = ltrim($label, "\xEF\xBB\xBF");
    $label = trim($label);
    if ($label === '') {
        return '';
    }

    $label = preg_replace('/[\s_\-\.]+/u', '', $label) ?? $label;
    return strtolower($label);
}

function dent_find_header_index(array $header, array $aliases): ?int
{
    $lookup = [];
    foreach ($aliases as $alias) {
        $normalized = dent_normalize_header_label((string) $alias);
        if ($normalized !== '') {
            $lookup[$normalized] = true;
        }
    }

    foreach ($header as $index => $label) {
        $normalized = dent_normalize_header_label((string) $label);
        if ($normalized !== '' && isset($lookup[$normalized])) {
            return (int) $index;
        }
    }

    return null;
}

function dent_should_skip_grade_column(string $label): bool
{
    static $skip = null;

    if ($skip === null) {
        $skip = [];
        foreach (['password', 'pass', 'pwd'] as $alias) {
            $skip[dent_normalize_header_label($alias)] = true;
        }
    }

    $normalized = dent_normalize_header_label($label);
    return $normalized !== '' && isset($skip[$normalized]);
}

function dent_normalize_number_string(?string $value): string
{
    $value = dent_normalize_digits($value);
    $value = str_replace(['٫', '٬', '،'], ['.', '', ''], $value);
    $value = str_replace(',', '.', $value);

    return trim((string) $value);
}

function dent_parse_grade_score($value): ?float
{
    $normalized = dent_normalize_number_string((string) $value);
    if ($normalized === '' || !is_numeric($normalized)) {
        return null;
    }

    $score = (float) $normalized;
    return $score >= 0 ? $score : null;
}

function dent_grade_course_label_parts(string $label): array
{
    $label = trim(ltrim($label, "\xEF\xBB\xBF"));
    $maxScore = null;

    if (preg_match('/^(.*?)\s*[\((]\s*از\s*([0-9۰-۹٠-٩.,٫٬]+)\s*[\))]\s*$/u', $label, $matches) === 1) {
        $label = trim((string) $matches[1]);
        $maxScore = dent_parse_grade_score((string) $matches[2]);
    } elseif (preg_match('/^(.*?)\s*[,،]\s*([0-9۰-۹٠-٩.,٫٬]+)\s*$/u', $label, $matches) === 1) {
        $label = trim((string) $matches[1]);
        $maxScore = dent_parse_grade_score((string) $matches[2]);
    }

    return [
        'label' => $label,
        'maxScore' => $maxScore,
    ];
}

function dent_clean_grade_course_label(string $label): string
{
    $parts = dent_grade_course_label_parts($label);
    return dent_clean_text((string) $parts['label'], 140);
}

function dent_grade_course_key(string $label): string
{
    return dent_normalize_header_label(dent_clean_grade_course_label($label));
}

function dent_read_grades_meta(): array
{
    $raw = dent_read_json_file(dent_grades_meta_path(), [
        'schemaVersion' => 1,
        'courses' => [],
    ]);
    if (!isset($raw['courses']) || !is_array($raw['courses'])) {
        throw new DentJsonPersistenceException(
            'GRADES_META_SCHEMA_INVALID',
            'Existing grade metadata store has an invalid schema'
        );
    }

    $courses = [];
    foreach (($raw['courses'] ?? []) as $key => $course) {
        if (!is_array($course)) {
            continue;
        }
        $label = dent_clean_grade_course_label((string) ($course['label'] ?? ''));
        $courseKey = dent_grade_course_key($label !== '' ? $label : (string) $key);
        if ($courseKey === '') {
            continue;
        }
        $maxScore = dent_parse_grade_score($course['maxScore'] ?? null);
        $courses[$courseKey] = [
            'label' => $label,
            'maxScore' => $maxScore,
            'updatedAt' => (string) ($course['updatedAt'] ?? ''),
        ];
    }

    return [
        'schemaVersion' => 1,
        'courses' => $courses,
    ];
}

function dent_write_grades_meta(array $meta): void
{
    $courses = [];
    foreach (($meta['courses'] ?? []) as $key => $course) {
        if (!is_array($course)) {
            continue;
        }
        $label = dent_clean_grade_course_label((string) ($course['label'] ?? ''));
        $courseKey = dent_grade_course_key($label !== '' ? $label : (string) $key);
        if ($courseKey === '') {
            continue;
        }
        $courses[$courseKey] = [
            'label' => $label,
            'maxScore' => dent_parse_grade_score($course['maxScore'] ?? null),
            'updatedAt' => (string) ($course['updatedAt'] ?? dent_iso_now()),
        ];
    }

    dent_write_json_file(dent_grades_meta_path(), [
        'schemaVersion' => 1,
        'courses' => $courses,
    ]);
}

function dent_grade_course_meta(array $meta, string $label): array
{
    $key = dent_grade_course_key($label);
    $stored = $key !== '' && isset($meta['courses'][$key]) && is_array($meta['courses'][$key])
        ? $meta['courses'][$key]
        : [];
    $parts = dent_grade_course_label_parts($label);

    return [
        'key' => $key,
        'label' => dent_clean_grade_course_label((string) ($stored['label'] ?? ($parts['label'] ?? $label))),
        'maxScore' => dent_parse_grade_score($stored['maxScore'] ?? ($parts['maxScore'] ?? null)),
    ];
}

function dent_grades_row_has_values(array $row, array $gradeColumns): bool
{
    foreach ($gradeColumns as $index => $_label) {
        if (trim((string) ($row[$index] ?? '')) !== '') {
            return true;
        }
    }

    return false;
}

function dent_grade_payload_has_values(array $payload): bool
{
    foreach (($payload['grades'] ?? []) as $grade) {
        if (is_array($grade) && trim((string) ($grade['value'] ?? '')) !== '') {
            return true;
        }
    }

    return false;
}

function dent_read_grades_source(bool $strict = true): array
{
    $emptySource = dent_empty_grades_source();
    $csvFile = dent_grades_store_path();

    if (!file_exists($csvFile)) {
        if (!$strict) {
            return dent_grades_missing_source();
        }
        if (dent_grades_active_cohort() !== dent_primary_cohort_key()) {
            $source = dent_grades_missing_source();
            dent_write_grades_source($source['header'], $source['rows']);
            return $source;
        }
        dent_error('فایل نمرات در storage پیدا نشد.', 500);
    }

    $handle = fopen($csvFile, 'r');
    if ($handle === false) {
        if (!$strict) {
            return $emptySource;
        }
        dent_error('امکان خواندن فایل نمرات وجود ندارد.', 500);
    }

    $header = fgetcsv($handle, 0, ',', '"', '\\');
    if ($header === false) {
        fclose($handle);
        if (!$strict) {
            return $emptySource;
        }
        dent_error('هدر فایل نمرات نامعتبر است.', 500);
    }

    $idIndex = dent_find_header_index($header, ['StudentID', 'StudentId', 'StudentNumber', 'StudentNo', 'SID']);
    $nameIndex = dent_find_header_index($header, ['Name', 'FullName', 'StudentName']);

    if ($idIndex === null && array_key_exists(0, $header)) {
        $idIndex = 0;
    }

    if ($nameIndex === null) {
        if (array_key_exists(1, $header) && $idIndex !== 1) {
            $nameIndex = 1;
        } else {
            $nameIndex = $idIndex;
        }
    }

    if ($idIndex === null || $nameIndex === null) {
        fclose($handle);
        if (!$strict) {
            return $emptySource;
        }
        dent_error('ستون‌های StudentID و Name در فایل نمرات پیدا نشدند.', 500);
    }

    $gradeColumns = [];
    foreach ($header as $index => $label) {
        if ($index === $idIndex || $index === $nameIndex) {
            continue;
        }

        $cleanLabel = trim((string) $label);
        if ($cleanLabel === '' || dent_should_skip_grade_column($cleanLabel)) {
            continue;
        }

        $gradeColumns[$index] = $cleanLabel;
    }

    $rows = [];
    $statsAccumulator = [];
    foreach ($gradeColumns as $index => $label) {
        $statsAccumulator[$index] = [
            'sum' => 0.0,
            'count' => 0,
            'scores' => [],
            'students' => [],
        ];
    }

    while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        $studentNumber = dent_normalize_student_number($row[$idIndex] ?? '');
        if ($studentNumber === '') {
            continue;
        }

        $rows[] = $row;

        foreach ($gradeColumns as $index => $label) {
            $score = dent_parse_grade_score($row[$index] ?? null);
            if ($score === null) {
                continue;
            }
            if (isset($statsAccumulator[$index]['students'][$studentNumber])) {
                continue;
            }

            $statsAccumulator[$index]['sum'] += $score;
            $statsAccumulator[$index]['count']++;
            $statsAccumulator[$index]['scores'][] = $score;
            $statsAccumulator[$index]['students'][$studentNumber] = true;
        }
    }

    fclose($handle);

    return [
        'header' => $header,
        'rows' => $rows,
        'idIndex' => $idIndex,
        'nameIndex' => $nameIndex,
        'gradeColumns' => $gradeColumns,
        'statsAccumulator' => $statsAccumulator,
    ];
}

function dent_grade_roster_index(): array
{
    $source = dent_read_grades_source(false);
    $roster = [];

    foreach ($source['rows'] as $row) {
        $studentNumber = dent_normalize_student_number($row[$source['idIndex']] ?? '');
        if ($studentNumber !== '' && dent_grades_row_has_values($row, $source['gradeColumns'])) {
            $roster[$studentNumber] = true;
        }
    }

    return $roster;
}

function dent_build_grades_payload(array $user): array
{
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($studentNumber === '') {
        dent_error('شماره دانشجویی کاربر نامعتبر است.', 422);
    }

    $source = dent_read_grades_source();
    $row = null;

    foreach ($source['rows'] as $candidate) {
        $candidateStudentNumber = dent_normalize_student_number($candidate[$source['idIndex']] ?? '');
        if ($candidateStudentNumber === $studentNumber) {
            $row = $candidate;
            break;
        }
    }

    $grades = [];
    $stats = [];
    $meta = dent_read_grades_meta();

    foreach ($source['gradeColumns'] as $index => $label) {
        $value = $row[$index] ?? '';
        $courseMeta = dent_grade_course_meta($meta, $label);
        $grades[] = [
            'label' => $label,
            'value' => $value,
            'maxScore' => $courseMeta['maxScore'],
        ];

        $bucket = $source['statsAccumulator'][$index];
        $count = (int) $bucket['count'];
        $sum = (float) $bucket['sum'];
        $scores = $bucket['scores'];

        $classAverage = $count > 0 ? ($sum / $count) : null;
        $totalWithScore = count($scores);
        $myScore = dent_parse_grade_score($value);
        $rank = null;

        if ($myScore !== null && $totalWithScore > 0) {
            rsort($scores, SORT_NUMERIC);
            $rank = 1;
            foreach ($scores as $score) {
                if ($score > $myScore) {
                    $rank++;
                    continue;
                }
                break;
            }
        }

        $stats[] = [
            'label' => $label,
            'myScore' => $myScore,
            'classAverage' => $classAverage,
            'rank' => $rank,
            'totalWithScore' => $totalWithScore,
            'maxScore' => $courseMeta['maxScore'],
        ];
    }

    $default = $stats[0] ?? null;

    return [
        'success' => true,
        'name' => (string) ($user['name'] ?? ($row[$source['nameIndex']] ?? 'دانشجو')),
        'studentNumber' => $studentNumber,
        'grades' => $grades,
        'stats' => $stats,
        'classAverage' => $default['classAverage'] ?? null,
        'rank' => $default['rank'] ?? null,
        'totalWithScore' => $default['totalWithScore'] ?? 0,
    ];
}

function dent_format_grade_for_store(float $score): string
{
    $normalized = number_format($score, 2, '.', '');
    $normalized = rtrim(rtrim($normalized, '0'), '.');
    return $normalized === '' ? '0' : $normalized;
}

function dent_write_grades_source(array $header, array $rows): void
{
    $csvFile = dent_grades_store_path();
    dent_ensure_directory(dirname($csvFile));

    $lock = fopen($csvFile . '.lock', 'c');
    if ($lock === false) {
        dent_error('امکان نگارش فایل نمرات وجود ندارد.', 500);
    }

    if (!flock($lock, LOCK_EX)) {
        fclose($lock);
        dent_error('قفل فایل نمرات گرفته نشد.', 500);
    }
    $temp = $csvFile . '.tmp.' . bin2hex(random_bytes(6));
    try {
        $handle = fopen($temp, 'xb');
        if ($handle === false) {
            dent_error('امکان نگارش فایل نمرات وجود ندارد.', 500);
        }
        try {
            $columnCount = count($header);
            if (fputcsv($handle, $header, ',', '"', '\\') === false) {
                throw new RuntimeException('GRADES_STORE_WRITE_FAILED');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $normalizedRow = $row;
                if (count($normalizedRow) < $columnCount) {
                    $normalizedRow = array_pad($normalizedRow, $columnCount, '');
                } elseif (count($normalizedRow) > $columnCount) {
                    $normalizedRow = array_slice($normalizedRow, 0, $columnCount);
                }
                if (fputcsv($handle, $normalizedRow, ',', '"', '\\') === false) {
                    throw new RuntimeException('GRADES_STORE_WRITE_FAILED');
                }
            }
            if (!fflush($handle) || (function_exists('fsync') && !fsync($handle))) {
                throw new RuntimeException('GRADES_STORE_FLUSH_FAILED');
            }
        } finally {
            fclose($handle);
        }
        if (!@rename($temp, $csvFile)) {
            throw new RuntimeException('GRADES_STORE_COMMIT_FAILED');
        }
        $temp = '';
    } finally {
        if ($temp !== '' && is_file($temp)) {
            @unlink($temp);
        }
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function dent_owner_grades_payload(string $studentNumber, string $fallbackName = ''): array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        dent_error('شماره دانشجویی نامعتبر است.', 422);
    }

    $source = dent_read_grades_source(false);
    if (!is_array($source['header']) || count($source['header']) === 0) {
        return [
            'studentNumber' => $studentNumber,
            'name' => dent_clean_text($fallbackName, 120),
            'rowExists' => false,
            'grades' => [],
        ];
    }
    $row = null;
    $rowExists = false;

    foreach ($source['rows'] as $candidate) {
        $candidateStudentNumber = dent_normalize_student_number($candidate[$source['idIndex']] ?? '');
        if ($candidateStudentNumber === $studentNumber) {
            $row = $candidate;
            $rowExists = true;
            break;
        }
    }

    if (!is_array($row)) {
        $row = array_fill(0, count($source['header']), '');
        $row[$source['idIndex']] = $studentNumber;
        if ($fallbackName !== '') {
            $row[$source['nameIndex']] = dent_clean_text($fallbackName, 120);
        }
    }

    $name = trim((string) ($row[$source['nameIndex']] ?? ''));
    if ($name === '') {
        $name = dent_clean_text($fallbackName, 120);
    }

    $grades = [];
    $meta = dent_read_grades_meta();
    foreach ($source['gradeColumns'] as $index => $label) {
        $courseMeta = dent_grade_course_meta($meta, $label);
        $grades[] = [
            'index' => (int) $index,
            'label' => $label,
            'value' => (string) ($row[$index] ?? ''),
            'maxScore' => $courseMeta['maxScore'],
        ];
    }

    return [
        'studentNumber' => $studentNumber,
        'name' => $name,
        'rowExists' => $rowExists,
        'grades' => $grades,
    ];
}

function dent_owner_set_grade(
    string $studentNumber,
    int $columnIndex,
    ?string $gradeValue,
    string $fallbackName = ''
): array {
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        dent_error('شماره دانشجویی نامعتبر است.', 422);
    }

    $source = dent_read_grades_source();
    if (!array_key_exists($columnIndex, $source['gradeColumns'])) {
        dent_error('ستون نمره نامعتبر است.', 422);
    }
    $meta = dent_read_grades_meta();
    $courseMeta = dent_grade_course_meta($meta, (string) ($source['gradeColumns'][$columnIndex] ?? ''));

    $finalValue = '';
    $rawValue = trim((string) $gradeValue);
    if ($rawValue !== '') {
        $normalized = dent_normalize_number_string($rawValue);
        if ($normalized === '' || !is_numeric($normalized)) {
            dent_error('مقدار نمره باید عددی باشد.', 422);
        }

        $score = (float) $normalized;
        if ($score < 0) {
            dent_error('نمره منفی مجاز نیست.', 422);
        }
        if ($courseMeta['maxScore'] !== null && $score > (float) $courseMeta['maxScore']) {
            dent_error('نمره نمی‌تواند از سقف درس بیشتر باشد.', 422);
        }

        $finalValue = dent_format_grade_for_store($score);
    }

    $fallbackName = dent_clean_text($fallbackName, 120);
    $rowFound = false;
    foreach ($source['rows'] as $index => $candidate) {
        $candidateStudentNumber = dent_normalize_student_number($candidate[$source['idIndex']] ?? '');
        if ($candidateStudentNumber !== $studentNumber) {
            continue;
        }

        $rowFound = true;
        $row = $candidate;
        if (count($row) < count($source['header'])) {
            $row = array_pad($row, count($source['header']), '');
        }
        $row[$source['idIndex']] = $studentNumber;
        if ($fallbackName !== '' && trim((string) ($row[$source['nameIndex']] ?? '')) === '') {
            $row[$source['nameIndex']] = $fallbackName;
        }
        $row[$columnIndex] = $finalValue;
        $source['rows'][$index] = $row;
    }

    if (!$rowFound) {
        $row = array_fill(0, count($source['header']), '');
        $row[$source['idIndex']] = $studentNumber;
        if ($fallbackName !== '') {
            $row[$source['nameIndex']] = $fallbackName;
        }
        $row[$columnIndex] = $finalValue;
        $source['rows'][] = $row;
    }

    dent_write_grades_source($source['header'], $source['rows']);
    return dent_owner_grades_payload($studentNumber, $fallbackName);
}

function dent_owner_remove_grades_row(string $studentNumber): bool
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        return false;
    }

    $source = dent_read_grades_source(false);
    if (!is_array($source['header']) || count($source['header']) === 0) {
        return false;
    }

    $keptRows = [];
    $removed = false;
    foreach ($source['rows'] as $row) {
        $candidateStudentNumber = dent_normalize_student_number($row[$source['idIndex']] ?? '');
        if ($candidateStudentNumber === $studentNumber) {
            $removed = true;
            continue;
        }
        $keptRows[] = $row;
    }

    if ($removed) {
        dent_write_grades_source($source['header'], $keptRows);
    }

    return $removed;
}

function dent_owner_grades_course_catalog(): array
{
    $source = dent_read_grades_source(false);
    if (!is_array($source['header']) || count($source['header']) === 0) {
        return [];
    }

    $meta = dent_read_grades_meta();
    $courses = [];
    foreach ($source['gradeColumns'] as $index => $label) {
        $courseMeta = dent_grade_course_meta($meta, (string) $label);
        $studentsWithScore = [];
        foreach ($source['rows'] as $row) {
            $studentNumber = dent_normalize_student_number($row[$source['idIndex']] ?? '');
            if ($studentNumber !== '' && trim((string) ($row[$index] ?? '')) !== '') {
                $studentsWithScore[$studentNumber] = true;
            }
        }

        $courses[] = [
            'index' => (int) $index,
            'key' => $courseMeta['key'],
            'label' => (string) $label,
            'maxScore' => $courseMeta['maxScore'],
            'withScore' => count($studentsWithScore),
        ];
    }

    return $courses;
}

function dent_split_grade_import_course_header(string $line): array
{
    $line = trim(ltrim(dent_force_utf8($line), "\xEF\xBB\xBF"));
    $normalized = str_replace(["\t", '،', ';'], [',', ',', ','], $line);
    $parts = str_getcsv($normalized, ',', '"', '\\');
    if (count($parts) >= 2) {
        $label = dent_clean_grade_course_label((string) $parts[0]);
        $maxScore = dent_parse_grade_score((string) $parts[1]);
        if ($label !== '' && $maxScore !== null && $maxScore > 0) {
            return [$label, $maxScore];
        }
    }

    if (preg_match('/^(.*?)\s*[+]\s*([0-9۰-۹٠-٩.,٫٬]+)\s*$/u', $line, $matches) === 1) {
        $label = dent_clean_grade_course_label((string) $matches[1]);
        $maxScore = dent_parse_grade_score((string) $matches[2]);
        if ($label !== '' && $maxScore !== null && $maxScore > 0) {
            return [$label, $maxScore];
        }
    }

    if (preg_match('/^(.*?)\s*[,،;]\s*([0-9۰-۹٠-٩.,٫٬]+)\s*$/u', $line, $matches) === 1) {
        $label = dent_clean_grade_course_label((string) $matches[1]);
        $maxScore = dent_parse_grade_score((string) $matches[2]);
        if ($label !== '' && $maxScore !== null && $maxScore > 0) {
            return [$label, $maxScore];
        }
    }

    dent_error('خط اول import باید شامل نام درس و سقف نمره باشد؛ مثل: پروتز کامل نظری, 10', 422);
}

function dent_split_grade_import_row(string $line): ?array
{
    $line = trim(dent_force_utf8($line));
    if ($line === '') {
        return null;
    }

    $normalized = str_replace(["\t", '،', ';'], [',', ',', ','], $line);
    $parts = str_getcsv($normalized, ',', '"', '\\');
    if (count($parts) < 2) {
        return null;
    }

    $studentNumber = dent_normalize_student_number((string) $parts[0]);
    $score = dent_parse_grade_score((string) $parts[1]);
    if ($studentNumber === '') {
        return null;
    }
    if ($score === null) {
        dent_error('نمره واردشده برای شماره دانشجویی ' . $studentNumber . ' نامعتبر است.', 422);
    }

    return [
        'studentNumber' => $studentNumber,
        'score' => $score,
    ];
}

function dent_owner_import_grades_from_text(string $text): array
{
    $lines = preg_split('/\R/u', dent_force_utf8($text)) ?: [];
    $lines = array_values(array_filter($lines, static fn($line): bool => trim((string) $line) !== ''));
    if (count($lines) < 2) {
        dent_error('برای import، خط اول درس و سقف نمره و حداقل یک ردیف شماره دانشجویی/نمره لازم است.', 422);
    }

    [$courseLabel, $maxScore] = dent_split_grade_import_course_header((string) $lines[0]);
    $entries = [];
    $skippedRows = 0;

    for ($i = 1; $i < count($lines); $i++) {
        $entry = dent_split_grade_import_row((string) $lines[$i]);
        if ($entry === null) {
            $skippedRows++;
            continue;
        }
        if ((float) $entry['score'] > (float) $maxScore) {
            dent_error('نمره ' . $entry['studentNumber'] . ' از سقف درس بیشتر است.', 422);
        }
        $entries[(string) $entry['studentNumber']] = $entry;
    }

    return dent_owner_apply_grade_import($courseLabel, (float) $maxScore, array_values($entries), $skippedRows);
}

function dent_zip_uint16(string $bytes, int $offset): int
{
    $value = unpack('v', substr($bytes, $offset, 2));
    return (int) ($value[1] ?? 0);
}

function dent_zip_uint32(string $bytes, int $offset): int
{
    $value = unpack('V', substr($bytes, $offset, 4));
    return (int) ($value[1] ?? 0);
}

function dent_zip_entries(string $path): array
{
    $bytes = file_get_contents($path);
    if ($bytes === false || strlen($bytes) < 22) {
        dent_error('فایل Excel قابل خواندن نیست.', 422);
    }

    $tail = substr($bytes, -min(strlen($bytes), 66000));
    $eocdOffsetInTail = strrpos($tail, "PK\x05\x06");
    if ($eocdOffsetInTail === false) {
        dent_error('ساختار فایل Excel نامعتبر است.', 422);
    }

    $eocdOffset = strlen($bytes) - strlen($tail) + $eocdOffsetInTail;
    $entryCount = dent_zip_uint16($bytes, $eocdOffset + 10);
    $centralOffset = dent_zip_uint32($bytes, $eocdOffset + 16);
    $entries = [];
    $cursor = $centralOffset;

    for ($i = 0; $i < $entryCount; $i++) {
        if (substr($bytes, $cursor, 4) !== "PK\x01\x02") {
            break;
        }

        $method = dent_zip_uint16($bytes, $cursor + 10);
        $compressedSize = dent_zip_uint32($bytes, $cursor + 20);
        $fileNameLength = dent_zip_uint16($bytes, $cursor + 28);
        $extraLength = dent_zip_uint16($bytes, $cursor + 30);
        $commentLength = dent_zip_uint16($bytes, $cursor + 32);
        $localOffset = dent_zip_uint32($bytes, $cursor + 42);
        $name = substr($bytes, $cursor + 46, $fileNameLength);

        $entries[$name] = [
            'method' => $method,
            'compressedSize' => $compressedSize,
            'localOffset' => $localOffset,
        ];
        $cursor += 46 + $fileNameLength + $extraLength + $commentLength;
    }

    return [
        'bytes' => $bytes,
        'entries' => $entries,
    ];
}

function dent_zip_extract_entry(array $zip, string $name): ?string
{
    $entries = $zip['entries'] ?? [];
    if (!isset($entries[$name]) || !is_array($entries[$name])) {
        return null;
    }

    $bytes = (string) ($zip['bytes'] ?? '');
    $entry = $entries[$name];
    $localOffset = (int) ($entry['localOffset'] ?? 0);
    if (substr($bytes, $localOffset, 4) !== "PK\x03\x04") {
        return null;
    }

    $fileNameLength = dent_zip_uint16($bytes, $localOffset + 26);
    $extraLength = dent_zip_uint16($bytes, $localOffset + 28);
    $dataOffset = $localOffset + 30 + $fileNameLength + $extraLength;
    $compressed = substr($bytes, $dataOffset, (int) ($entry['compressedSize'] ?? 0));
    $method = (int) ($entry['method'] ?? -1);

    if ($method === 0) {
        return $compressed;
    }
    if ($method === 8) {
        $inflated = gzinflate($compressed);
        return $inflated === false ? null : $inflated;
    }

    return null;
}

function dent_xlsx_column_number(string $letters): int
{
    $letters = strtoupper($letters);
    $number = 0;
    for ($i = 0; $i < strlen($letters); $i++) {
        $number = ($number * 26) + (ord($letters[$i]) - 64);
    }
    return max(1, $number);
}

function dent_xlsx_shared_strings(?string $xml): array
{
    if ($xml === null || trim($xml) === '') {
        return [];
    }
    $doc = dent_xlsx_load_xml($xml);
    if (!$doc) {
        return [];
    }

    $strings = [];
    foreach ($doc->si as $si) {
        $text = '';
        if (isset($si->t)) {
            $text .= (string) $si->t;
        }
        foreach ($si->r as $run) {
            $text .= (string) $run->t;
        }
        $strings[] = $text;
    }

    return $strings;
}

function dent_xlsx_load_xml(?string $xml): ?SimpleXMLElement
{
    if ($xml === null || trim($xml) === '') {
        return null;
    }

    $clean = preg_replace('/^\xEF\xBB\xBF/u', '', $xml) ?? $xml;
    $clean = preg_replace('/(<\/?)[A-Za-z0-9_]+:/', '$1', $clean) ?? $clean;
    $clean = preg_replace('/\s+xmlns:[A-Za-z0-9_]+="[^"]*"/', '', $clean) ?? $clean;

    $previous = libxml_use_internal_errors(true);
    $doc = @simplexml_load_string($clean);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return $doc ?: null;
}

function dent_xlsx_first_sheet_path(array $zip): string
{
    $rels = dent_zip_extract_entry($zip, 'xl/_rels/workbook.xml.rels');
    if (is_string($rels) && preg_match('/<Relationship\b[^>]*Type="[^"]*\/worksheet"[^>]*Target="([^"]+)"/i', $rels, $matches) === 1) {
        $target = str_replace('\\', '/', (string) $matches[1]);
        if (str_starts_with($target, '/')) {
            return ltrim($target, '/');
        }
        return 'xl/' . ltrim($target, '/');
    }

    return 'xl/worksheets/sheet1.xml';
}

function dent_xlsx_cell_text(SimpleXMLElement $cell, array $sharedStrings): string
{
    $type = (string) ($cell['t'] ?? '');
    if ($type === 's') {
        $index = (int) ($cell->v ?? -1);
        return (string) ($sharedStrings[$index] ?? '');
    }
    if ($type === 'inlineStr') {
        $text = '';
        if (isset($cell->is->t)) {
            $text .= (string) $cell->is->t;
        }
        foreach ($cell->is->r as $run) {
            $text .= (string) $run->t;
        }
        return $text;
    }

    return (string) ($cell->v ?? '');
}

function dent_xlsx_read_rows(string $path): array
{
    $zip = dent_zip_entries($path);
    $sheetPath = dent_xlsx_first_sheet_path($zip);
    $sheetXml = dent_zip_extract_entry($zip, $sheetPath);
    if (!is_string($sheetXml) || trim($sheetXml) === '') {
        dent_error('برگه اول فایل Excel پیدا نشد.', 422);
    }

    $sharedStrings = dent_xlsx_shared_strings(dent_zip_extract_entry($zip, 'xl/sharedStrings.xml'));
    $doc = dent_xlsx_load_xml($sheetXml);
    if (!$doc) {
        dent_error('خواندن محتوای Excel انجام نشد.', 422);
    }

    $rows = [];
    foreach ($doc->sheetData->row as $rowNode) {
        $row = [];
        foreach ($rowNode->c as $cell) {
            $ref = (string) ($cell['r'] ?? '');
            $column = count($row) + 1;
            if (preg_match('/^([A-Z]+)\d+$/i', $ref, $matches) === 1) {
                $column = dent_xlsx_column_number((string) $matches[1]);
            }
            $row[$column - 1] = trim(dent_xlsx_cell_text($cell, $sharedStrings));
        }

        if ($row !== []) {
            ksort($row);
            $normalized = [];
            $maxIndex = max(array_keys($row));
            for ($i = 0; $i <= $maxIndex; $i++) {
                $normalized[$i] = (string) ($row[$i] ?? '');
            }
            while ($normalized !== [] && trim((string) end($normalized)) === '') {
                array_pop($normalized);
            }
            if ($normalized !== []) {
                $rows[] = $normalized;
            }
        }
    }

    return $rows;
}

function dent_owner_import_grades_from_xlsx(string $path): array
{
    $rows = dent_xlsx_read_rows($path);
    if (count($rows) < 2) {
        dent_error('فایل Excel باید حداقل ردیف عنوان درس و یک ردیف نمره داشته باشد.', 422);
    }

    $header = $rows[0];
    $courseLabel = dent_clean_grade_course_label((string) ($header[0] ?? ''));
    $maxScore = dent_parse_grade_score((string) ($header[2] ?? ''));
    if ($maxScore === null) {
        foreach ($header as $index => $value) {
            if ($index === 0) {
                continue;
            }
            $candidate = dent_parse_grade_score((string) $value);
            if ($candidate !== null) {
                $maxScore = $candidate;
                break;
            }
        }
    }

    if ($courseLabel === '' || $maxScore === null || $maxScore <= 0) {
        dent_error('ردیف اول Excel باید نام درس در ستون اول و سقف نمره در ستون سوم داشته باشد.', 422);
    }

    $entries = [];
    $skippedRows = 0;
    for ($i = 1; $i < count($rows); $i++) {
        $studentNumber = dent_normalize_student_number((string) ($rows[$i][0] ?? ''));
        $score = dent_parse_grade_score((string) ($rows[$i][1] ?? ''));
        if ($studentNumber === '') {
            $skippedRows++;
            continue;
        }
        if ($score === null) {
            dent_error('نمره واردشده برای شماره دانشجویی ' . $studentNumber . ' نامعتبر است.', 422);
        }
        if ($score > $maxScore) {
            dent_error('نمره ' . $studentNumber . ' از سقف درس بیشتر است.', 422);
        }
        $entries[$studentNumber] = [
            'studentNumber' => $studentNumber,
            'score' => $score,
        ];
    }

    return dent_owner_apply_grade_import($courseLabel, (float) $maxScore, array_values($entries), $skippedRows);
}

function dent_owner_apply_grade_import(string $courseLabel, float $maxScore, array $entries, int $skippedRows = 0): array
{
    $courseLabel = dent_clean_grade_course_label($courseLabel);
    if ($courseLabel === '') {
        dent_error('نام درس نامعتبر است.', 422);
    }
    if ($maxScore <= 0) {
        dent_error('سقف نمره باید عددی مثبت باشد.', 422);
    }
    if ($entries === []) {
        dent_error('هیچ ردیف نمره معتبری برای import پیدا نشد.', 422);
    }

    $source = dent_read_grades_source(false);
    if (!is_array($source['header']) || count($source['header']) === 0) {
        $source = [
            'header' => ['StudentID', 'Name'],
            'rows' => [],
            'idIndex' => 0,
            'nameIndex' => 1,
            'gradeColumns' => [],
            'statsAccumulator' => [],
        ];
    }

    if ($source['idIndex'] === $source['nameIndex']) {
        $source['nameIndex'] = count($source['header']);
        $source['header'][] = 'Name';
        foreach ($source['rows'] as &$row) {
            $row = array_pad($row, count($source['header']), '');
        }
        unset($row);
    }

    $targetKey = dent_grade_course_key($courseLabel);
    $columnIndex = null;
    foreach ($source['gradeColumns'] as $index => $label) {
        if (dent_grade_course_key((string) $label) === $targetKey) {
            $columnIndex = (int) $index;
            break;
        }
    }

    if ($columnIndex === null) {
        $columnIndex = count($source['header']);
        $source['header'][] = $courseLabel;
        foreach ($source['rows'] as &$row) {
            $row = array_pad($row, count($source['header']), '');
        }
        unset($row);
    } else {
        $source['header'][$columnIndex] = $courseLabel;
    }

    $rowIndexesByStudent = [];
    foreach ($source['rows'] as $index => $row) {
        $studentNumber = dent_normalize_student_number($row[$source['idIndex']] ?? '');
        if ($studentNumber !== '') {
            if (!isset($rowIndexesByStudent[$studentNumber])) {
                $rowIndexesByStudent[$studentNumber] = [];
            }
            $rowIndexesByStudent[$studentNumber][] = (int) $index;
        }
    }

    $createdRows = 0;
    $updatedRows = 0;
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $studentNumber = dent_normalize_student_number((string) ($entry['studentNumber'] ?? ''));
        $score = dent_parse_grade_score($entry['score'] ?? null);
        if ($studentNumber === '' || $score === null) {
            continue;
        }
        if ($score > $maxScore) {
            dent_error('نمره ' . $studentNumber . ' از سقف درس بیشتر است.', 422);
        }

        $rowIndexes = $rowIndexesByStudent[$studentNumber] ?? [];
        if ($rowIndexes === []) {
            $row = array_fill(0, count($source['header']), '');
            $row[$source['idIndex']] = $studentNumber;
            if (function_exists('dent_get_user_record')) {
                $user = dent_get_user_record($studentNumber);
                if (is_array($user)) {
                    $row[$source['nameIndex']] = dent_clean_text((string) ($user['name'] ?? ''), 120);
                }
            }
            $source['rows'][] = $row;
            $rowIndexes = [count($source['rows']) - 1];
            $rowIndexesByStudent[$studentNumber] = $rowIndexes;
            $createdRows++;
        } else {
            $updatedRows += count($rowIndexes);
        }

        foreach ($rowIndexes as $rowIndex) {
            $row = $source['rows'][$rowIndex];
            $row = array_pad($row, count($source['header']), '');
            $row[$source['idIndex']] = $studentNumber;
            $row[$columnIndex] = dent_format_grade_for_store((float) $score);
            $source['rows'][$rowIndex] = $row;
        }
    }

    $meta = dent_read_grades_meta();
    $meta['courses'][$targetKey] = [
        'label' => $courseLabel,
        'maxScore' => $maxScore,
        'updatedAt' => dent_iso_now(),
    ];

    dent_write_grades_source($source['header'], $source['rows']);
    dent_write_grades_meta($meta);

    return [
        'success' => true,
        'course' => [
            'key' => $targetKey,
            'label' => $courseLabel,
            'maxScore' => $maxScore,
            'index' => $columnIndex,
        ],
        'importedCount' => count($entries),
        'updatedRows' => $updatedRows,
        'createdRows' => $createdRows,
        'skippedRows' => $skippedRows,
        'courses' => dent_owner_grades_course_catalog(),
    ];
}

function dent_owner_delete_grade_course(string $courseKey): array
{
    $courseKey = dent_grade_course_key($courseKey);
    if ($courseKey === '') {
        dent_error('درس انتخاب‌شده نامعتبر است.', 422);
    }

    $source = dent_read_grades_source(false);
    if (!is_array($source['header']) || count($source['header']) === 0) {
        dent_error('کارنامه‌ای برای حذف درس پیدا نشد.', 404);
    }

    $columnIndex = null;
    $columnLabel = '';
    foreach ($source['gradeColumns'] as $index => $label) {
        if (dent_grade_course_key((string) $label) === $courseKey) {
            $columnIndex = (int) $index;
            $columnLabel = (string) $label;
            break;
        }
    }

    if ($columnIndex === null) {
        dent_error('درس انتخاب‌شده در کارنامه پیدا نشد.', 404);
    }

    array_splice($source['header'], $columnIndex, 1);
    foreach ($source['rows'] as &$row) {
        $row = array_values($row);
        array_splice($row, $columnIndex, 1);
    }
    unset($row);

    $meta = dent_read_grades_meta();
    unset($meta['courses'][$courseKey]);

    dent_write_grades_source($source['header'], $source['rows']);
    dent_write_grades_meta($meta);

    return [
        'success' => true,
        'removedCourse' => [
            'key' => $courseKey,
            'label' => $columnLabel,
        ],
        'courses' => dent_owner_grades_course_catalog(),
    ];
}

function dent_owner_reset_gradebook(): array
{
    $source = dent_read_grades_source(false);
    $rows = [];

    if (is_array($source['header']) && count($source['header']) > 0) {
        foreach ($source['rows'] as $row) {
            $studentNumber = dent_normalize_student_number($row[$source['idIndex']] ?? '');
            if ($studentNumber === '') {
                continue;
            }
            $rows[] = [
                $studentNumber,
                (string) ($row[$source['nameIndex']] ?? ''),
            ];
        }
    }

    dent_write_grades_source(['StudentID', 'Name'], $rows);
    dent_write_grades_meta([
        'schemaVersion' => 1,
        'courses' => [],
    ]);

    return [
        'success' => true,
        'courses' => [],
        'keptRows' => count($rows),
    ];
}
