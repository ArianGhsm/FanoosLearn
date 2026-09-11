<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_store.php';
require_once __DIR__ . '/exams_store.php';

const SEARCH_MIN_QUERY_LENGTH = 2;
const SEARCH_MAX_QUERY_LENGTH = 80;
const SEARCH_MAX_RESULTS_PER_SECTION = 18;
const SEARCH_MAX_RESULTS_TOTAL = 40;

function search_utf8_strlen(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    if (preg_match_all('/./us', $value, $matches) === false) {
        return strlen($value);
    }

    return count($matches[0]);
}

function search_utf8_substr(string $value, int $start, ?int $length = null): string
{
    if (function_exists('mb_substr')) {
        return $length === null
            ? mb_substr($value, $start, null, 'UTF-8')
            : mb_substr($value, $start, $length, 'UTF-8');
    }

    if (preg_match_all('/./us', $value, $matches) === false) {
        return $length === null ? substr($value, $start) : substr($value, $start, $length);
    }

    $slice = $length === null
        ? array_slice($matches[0], $start)
        : array_slice($matches[0], $start, $length);

    return implode('', $slice);
}

function search_utf8_strtolower(string $value): string
{
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }

    return strtolower($value);
}

function search_utf8_contains(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return false;
    }

    if (function_exists('mb_strpos')) {
        return mb_strpos($haystack, $needle, 0, 'UTF-8') !== false;
    }

    $pattern = '/' . preg_quote($needle, '/') . '/u';
    $matched = preg_match($pattern, $haystack);
    if ($matched === false) {
        return strpos($haystack, $needle) !== false;
    }

    return $matched === 1;
}

/**
 * Normalize Persian/Arabic text so search matching is tolerant of the usual
 * variations: Arabic vs Persian ye/kaf, diacritics, ZWNJ, tatweel and digits.
 */
function search_normalize_text(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    // Unify Arabic letters to their Persian forms.
    $value = strtr($value, [
        "\u{064A}" => "\u{06CC}", // ARABIC YEH -> FARSI YEH
        "\u{0649}" => "\u{06CC}", // ALEF MAKSURA -> FARSI YEH
        "\u{0643}" => "\u{06A9}", // ARABIC KAF -> KEHEH
        "\u{0629}" => "\u{0647}", // TEH MARBUTA -> HEH
        "\u{0623}" => "\u{0627}", // ALEF WITH HAMZA ABOVE -> ALEF
        "\u{0625}" => "\u{0627}", // ALEF WITH HAMZA BELOW -> ALEF
        "\u{0622}" => "\u{0627}", // ALEF WITH MADDA -> ALEF
    ]);

    // Drop ZWNJ, ZWJ, tatweel and combining diacritics.
    $value = preg_replace('/[\x{200C}\x{200D}\x{0640}\x{064B}-\x{065F}\x{0670}]/u', '', $value) ?? $value;

    // Fold Persian and Arabic digits to Latin.
    $value = dent_normalize_digits($value);

    $value = search_utf8_strtolower($value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    return trim($value);
}

function search_text_matches(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return false;
    }

    return search_utf8_contains(search_normalize_text($haystack), $needle);
}

/**
 * The notes module is keyed by its own short cohort tokens
 * (1402 / 1403 / 1404 / prosthesis-1402), not by the auth catalog key
 * (dentistry-1402 / ...). Mirror notes_curriculum_store_for_cohort() so search
 * reads the same archive the notes pages render.
 */
function search_notes_token_for_cohort(string $cohort): string
{
    $cohort = trim($cohort);
    if (function_exists('dent_is_prosthesis_cohort_key') && dent_is_prosthesis_cohort_key($cohort)) {
        return 'prosthesis-1402';
    }
    if (strpos($cohort, '1403') !== false) {
        return '1403';
    }
    if (strpos($cohort, '1404') !== false) {
        return '1404';
    }

    return '1402';
}

function search_notes_store_path_for_cohort(string $cohort): string
{
    $map = [
        '1402' => 'notes/1402_terms.json',
        '1403' => 'notes/1403_terms.json',
        '1404' => 'notes/1404_terms.json',
        'prosthesis-1402' => 'notes/prosthesis_1402_terms.json',
    ];

    $token = search_notes_token_for_cohort($cohort);

    return dent_storage_path($map[$token] ?? 'notes/1402_terms.json');
}

function search_notes_page_href(string $cohort): string
{
    switch (search_notes_token_for_cohort($cohort)) {
        case '1403':
            return '/notes/1403/';
        case '1404':
            return '/notes/1404/';
        case 'prosthesis-1402':
            return '/notes/?cohort=prosthesis-1402';
        default:
            return '/notes/';
    }
}

function search_clean_href(string $href): string
{
    $href = trim($href);
    if ($href === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $href) === 1) {
        return $href;
    }

    if ($href[0] === '/') {
        return $href;
    }

    return '';
}

function search_collect_notes(string $cohort, string $needle): array
{
    $store = dent_read_json_file(search_notes_store_path_for_cohort($cohort), []);
    if (!is_array($store)) {
        return [];
    }

    $terms = is_array($store['terms'] ?? null) ? $store['terms'] : [];
    $notesPage = search_notes_page_href($cohort);
    $results = [];

    foreach ($terms as $termRecord) {
        if (!is_array($termRecord)) {
            continue;
        }

        $termTitle = trim((string) ($termRecord['title'] ?? ''));
        $items = is_array($termRecord['items'] ?? null) ? $termRecord['items'] : [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $title = trim((string) ($item['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $description = trim((string) ($item['description'] ?? ''));
            $badge = trim((string) ($item['badge'] ?? ''));
            $haystack = $title . ' ' . $description . ' ' . $badge . ' ' . $termTitle;
            if (!search_text_matches($haystack, $needle)) {
                continue;
            }

            $direct = search_clean_href((string) ($item['buttonUrl'] ?? ''));
            $results[] = [
                'type' => 'note',
                'typeLabel' => 'منبع درسی',
                'title' => $title,
                'subtitle' => $termTitle !== '' ? $termTitle : 'منابع درسی',
                'href' => $direct !== '' ? $direct : $notesPage,
                'external' => $direct !== '' && preg_match('#^https?://#i', $direct) === 1,
            ];

            if (count($results) >= SEARCH_MAX_RESULTS_PER_SECTION) {
                return $results;
            }
        }
    }

    return $results;
}

function search_append_cohort_path(string $path, string $cohort): string
{
    $path = trim($path);
    if ($path === '' || $cohort === '' || $cohort === dent_primary_cohort_key()) {
        return $path;
    }

    if (strpos($path, 'cohort=') !== false) {
        return $path;
    }

    $separator = strpos($path, '?') !== false ? '&' : '?';

    return $path . $separator . 'cohort=' . rawurlencode($cohort);
}

function search_collect_exams(string $cohort, string $needle): array
{
    $catalogKey = dent_exams_resolve_catalog_key($cohort);
    if ($catalogKey === '') {
        return [];
    }

    $catalog = dent_exams_catalog($catalogKey);
    if ($catalog === null) {
        return [];
    }

    $courses = is_array($catalog['courses'] ?? null) ? $catalog['courses'] : [];
    $results = [];

    foreach ($courses as $course) {
        if (!is_array($course)) {
            continue;
        }

        if (array_key_exists('catalogVisible', $course) && $course['catalogVisible'] === false) {
            continue;
        }

        $courseTitle = trim((string) ($course['title'] ?? ''));
        if ($courseTitle === '') {
            continue;
        }

        $coursePath = search_clean_href((string) ($course['path'] ?? ''));
        $courseHref = $coursePath !== '' ? search_append_cohort_path($coursePath, $cohort) : '/exams/';

        if (search_text_matches($courseTitle, $needle)) {
            $results[] = [
                'type' => 'exam-course',
                'typeLabel' => 'درس آزمون',
                'title' => $courseTitle,
                'subtitle' => 'بانک آزمون‌ها',
                'href' => $courseHref,
                'external' => false,
            ];

            if (count($results) >= SEARCH_MAX_RESULTS_PER_SECTION) {
                return $results;
            }
        }

        $exams = is_array($course['exams'] ?? null) ? $course['exams'] : [];
        foreach ($exams as $exam) {
            if (!is_array($exam)) {
                continue;
            }

            $examTitle = trim((string) ($exam['title'] ?? ''));
            if ($examTitle === '' || !search_text_matches($examTitle, $needle)) {
                continue;
            }

            $examPath = search_clean_href((string) ($exam['path'] ?? ''));
            $href = $examPath !== '' ? search_append_cohort_path($examPath, $cohort) : $courseHref;

            $results[] = [
                'type' => 'exam-session',
                'typeLabel' => 'جلسه آزمون',
                'title' => $examTitle,
                'subtitle' => $courseTitle,
                'href' => $href,
                'external' => false,
            ];

            if (count($results) >= SEARCH_MAX_RESULTS_PER_SECTION) {
                return $results;
            }
        }
    }

    return $results;
}

/**
 * Run the global query for a resolved cohort and return the normalized payload.
 */
function search_run_query(string $rawQuery, string $cohort): array
{
    if (search_utf8_strlen($rawQuery) > SEARCH_MAX_QUERY_LENGTH) {
        $rawQuery = search_utf8_substr($rawQuery, 0, SEARCH_MAX_QUERY_LENGTH);
    }

    $needle = search_normalize_text($rawQuery);
    if (search_utf8_strlen($needle) < SEARCH_MIN_QUERY_LENGTH) {
        return [
            'success' => true,
            'query' => trim($rawQuery),
            'cohort' => $cohort,
            'results' => [],
            'counts' => ['total' => 0, 'note' => 0, 'exam' => 0],
            'tooShort' => true,
        ];
    }

    $notes = search_collect_notes($cohort, $needle);
    $exams = search_collect_exams($cohort, $needle);

    $results = array_merge($notes, $exams);
    if (count($results) > SEARCH_MAX_RESULTS_TOTAL) {
        $results = array_slice($results, 0, SEARCH_MAX_RESULTS_TOTAL);
    }

    return [
        'success' => true,
        'query' => trim($rawQuery),
        'cohort' => $cohort,
        'results' => $results,
        'counts' => [
            'total' => count($results),
            'note' => count($notes),
            'exam' => count($exams),
        ],
    ];
}
