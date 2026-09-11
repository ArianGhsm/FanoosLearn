<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function dent_owner_student_number(): string
{
    return '40211272003';
}

function dent_primary_cohort_key(): string
{
    return 'dentistry-1402';
}

function dent_prosthesis_legacy_cohort_key(): string
{
    return 'prosthesis-1402';
}

function dent_external_site_users_cohort_key(): string
{
    return 'site-users';
}

function dent_clean_cohort_key(?string $value): string
{
    $value = dent_force_utf8((string) $value);
    $value = trim(strtolower($value));
    if ($value === '' || $value === 'main' || $value === '1402') {
        return dent_primary_cohort_key();
    }
    if ($value === 'prosthesis' || $value === 'prosthesis1402') {
        return dent_prosthesis_legacy_cohort_key();
    }
    if ($value === 'siteusers' || $value === 'site-users' || $value === 'external-users' || $value === 'external') {
        return dent_external_site_users_cohort_key();
    }

    $value = preg_replace('/[^a-z0-9\-_]+/u', '-', $value) ?? '';
    $value = trim((string) preg_replace('/-{2,}/', '-', str_replace('_', '-', $value)), '-');
    return $value;
}

function dent_default_cohort_catalog(): array
{
    return [
        dent_primary_cohort_key() => [
            'title' => 'دندانپزشکی ۱۴۰۲',
            'shortTitle' => 'دندان ۱۴۰۲',
            'description' => 'ورودی اصلی دندانپزشکی با مسیرهای سراسری سایت.',
            'productType' => 'dentistry',
            'year' => '1402',
            'siteVariant' => 'main',
            'notesMode' => 'terms',
            'allowRepresentativeManagement' => false,
            'supportsRotationGroups' => true,
            'sortOrder' => 100,
            'isSeeded' => true,
            'isIsolated' => true,
        ],
        'dentistry-1403' => [
            'title' => 'دندانپزشکی ۱۴۰۳',
            'shortTitle' => 'دندان ۱۴۰۳',
            'description' => 'ورودی ایزوله برای دانشجویان دندانپزشکی ۱۴۰۳.',
            'productType' => 'dentistry',
            'year' => '1403',
            'siteVariant' => 'main',
            'notesMode' => 'terms',
            'allowRepresentativeManagement' => true,
            'supportsRotationGroups' => false,
            'sortOrder' => 110,
            'isSeeded' => true,
            'isIsolated' => true,
        ],
        'dentistry-1404' => [
            'title' => 'دندانپزشکی ۱۴۰۴',
            'shortTitle' => 'دندان ۱۴۰۴',
            'description' => 'ورودی ایزوله برای دانشجویان دندانپزشکی ۱۴۰۴.',
            'productType' => 'dentistry',
            'year' => '1404',
            'siteVariant' => 'main',
            'notesMode' => 'terms',
            'allowRepresentativeManagement' => true,
            'supportsRotationGroups' => false,
            'sortOrder' => 120,
            'isSeeded' => true,
            'isIsolated' => true,
        ],
        dent_prosthesis_legacy_cohort_key() => [
            'title' => 'پروتز ۱۴۰۲',
            'shortTitle' => 'پروتز ۱۴۰۲',
            'description' => 'زیرمحصول ایزوله پروتز ۱۴۰۲ با مسیرهای اختصاصی خودش.',
            'productType' => 'prosthesis',
            'year' => '1402',
            'siteVariant' => 'prosthesis-legacy',
            'notesMode' => 'terms',
            'allowRepresentativeManagement' => true,
            'supportsRotationGroups' => false,
            'sortOrder' => 130,
            'isSeeded' => true,
            'isIsolated' => true,
        ],
        dent_external_site_users_cohort_key() => [
            'title' => 'کاربران عادی سایت',
            'shortTitle' => 'کاربران عادی',
            'description' => 'کاربران عمومی سایت که با شماره موبایل ثبت‌نام می‌کنند و به آزمون‌ها و پرداخت دسترسی دارند.',
            'productType' => 'site-users',
            'year' => '',
            'siteVariant' => 'main',
            'notesMode' => 'none',
            'allowRepresentativeManagement' => false,
            'supportsRotationGroups' => false,
            'sortOrder' => 140,
            'isSeeded' => true,
            'isIsolated' => true,
        ],
    ];
}

function dent_cohort_storage_slug(string $cohortKey): string
{
    $clean = dent_clean_cohort_key($cohortKey);
    if ($clean === '') {
        return 'cohort';
    }

    return str_replace('-', '_', $clean);
}

function dent_default_cohort_title(string $key): string
{
    $key = dent_clean_cohort_key($key);
    $defaults = dent_default_cohort_catalog();
    if (isset($defaults[$key]['title'])) {
        return (string) $defaults[$key]['title'];
    }

    $parts = array_values(array_filter(explode('-', $key), static function ($part): bool {
        return trim((string) $part) !== '';
    }));
    if (!$parts) {
        return 'ورودی جدید';
    }

    $year = '';
    $labelParts = [];
    foreach ($parts as $part) {
        if (preg_match('/^\d{4}$/', $part) === 1) {
            $year = $part;
            continue;
        }
        $labelParts[] = $part;
    }

    $product = implode(' ', $labelParts);
    if ($product === 'dentistry') {
        $product = 'دندانپزشکی';
    } elseif ($product === 'prosthesis') {
        $product = 'پروتز';
    } elseif ($product === '') {
        $product = 'ورودی';
    }

    return trim($product . ' ' . dent_to_fa_digits($year));
}

function dent_default_cohort_short_title(string $key): string
{
    $key = dent_clean_cohort_key($key);
    $defaults = dent_default_cohort_catalog();
    if (isset($defaults[$key]['shortTitle'])) {
        return (string) $defaults[$key]['shortTitle'];
    }

    return dent_default_cohort_title($key);
}

function dent_default_cohort_services(string $key, string $productType, string $notesMode): array
{
    $isSiteUsers = $productType === 'site-users';
    return [
        'notes' => !$isSiteUsers && $notesMode !== 'none',
        'forms' => !$isSiteUsers,
        'grades' => !$isSiteUsers,
        'navid' => !$isSiteUsers,
        'buy' => $isSiteUsers,
        'activeExamHighlights' => $key === dent_primary_cohort_key(),
    ];
}

function dent_normalize_cohort_services(string $key, string $productType, string $notesMode, $input): array
{
    $defaults = dent_default_cohort_services($key, $productType, $notesMode);
    $raw = is_array($input) ? $input : [];
    $services = [];
    foreach ($defaults as $name => $enabled) {
        $services[$name] = dent_parse_bool($raw[$name] ?? $enabled, $enabled);
    }
    return $services;
}

function dent_default_cohort_routes(string $key, array $services): array
{
    $buildScoped = static function (string $path) use ($key): string {
        return $key === dent_primary_cohort_key()
            ? $path
            : $path . '?cohort=' . rawurlencode($key);
    };

    if (!empty($services['notes'])) {
        if ($key === dent_primary_cohort_key()) {
            $notesRoute = '/notes/';
        } elseif ($key === 'dentistry-1403') {
            $notesRoute = '/notes/1403/';
        } elseif ($key === 'dentistry-1404') {
            $notesRoute = '/notes/1404/';
        } else {
            $notesRoute = '/notes/?cohort=' . rawurlencode($key);
        }
    } else {
        $notesRoute = '';
    }

    return [
        'notes' => $notesRoute,
        'forms' => !empty($services['forms']) ? $buildScoped('/forms/') : '',
        'grades' => !empty($services['grades']) ? $buildScoped('/grades/') : '',
        'navid' => !empty($services['navid']) ? '/navid/' : '',
        'buy' => !empty($services['buy']) ? '/buy/' : '',
    ];
}

function dent_normalize_cohort_route(string $value, string $fallback): string
{
    $clean = trim($value);
    if ($clean === '') {
        return $fallback;
    }
    if ($clean[0] !== '/') {
        return $fallback;
    }
    return $clean;
}

function dent_normalize_cohort_routes(string $key, array $services, $input): array
{
    $defaults = dent_default_cohort_routes($key, $services);
    $raw = is_array($input) ? $input : [];
    $routes = [];
    foreach ($defaults as $name => $fallback) {
        if (empty($services[$name])) {
            $routes[$name] = '';
            continue;
        }
        $routes[$name] = dent_normalize_cohort_route((string) ($raw[$name] ?? ''), $fallback);
    }
    return $routes;
}

function dent_public_cohort_record(array $cohort): array
{
    return [
        'key' => (string) ($cohort['key'] ?? ''),
        'title' => (string) ($cohort['title'] ?? ''),
        'shortTitle' => (string) ($cohort['shortTitle'] ?? ''),
        'description' => (string) ($cohort['description'] ?? ''),
        'productType' => (string) ($cohort['productType'] ?? ''),
        'year' => (string) ($cohort['year'] ?? ''),
        'siteVariant' => (string) ($cohort['siteVariant'] ?? ''),
        'notesMode' => (string) ($cohort['notesMode'] ?? ''),
        'allowRepresentativeManagement' => !empty($cohort['allowRepresentativeManagement']),
        'supportsRotationGroups' => !empty($cohort['supportsRotationGroups']),
        'services' => is_array($cohort['services'] ?? null) ? $cohort['services'] : [],
        'routes' => is_array($cohort['routes'] ?? null) ? $cohort['routes'] : [],
    ];
}

function dent_normalize_cohort_record(string $key, array $seed): ?array
{
    $key = dent_clean_cohort_key($key !== '' ? $key : (string) ($seed['key'] ?? ''));
    if ($key === '') {
        return null;
    }

    $defaultSeed = dent_default_cohort_catalog()[$key] ?? [];
    $merged = array_merge($defaultSeed, $seed);

    $productType = strtolower(trim((string) ($merged['productType'] ?? 'dentistry')));
    if (!in_array($productType, ['dentistry', 'prosthesis', 'site-users'], true)) {
        $productType = 'dentistry';
    }

    $siteVariant = trim((string) ($merged['siteVariant'] ?? 'main'));
    if ($siteVariant !== 'prosthesis-legacy') {
        $siteVariant = 'main';
    }

    $notesMode = trim((string) ($merged['notesMode'] ?? 'archive'));
    if (!in_array($notesMode, ['terms', 'archive', 'none'], true)) {
        $notesMode = 'archive';
    }

    $title = dent_clean_text((string) ($merged['title'] ?? dent_default_cohort_title($key)), 140);
    if ($title === '') {
        $title = dent_default_cohort_title($key);
    }

    $shortTitle = dent_clean_text((string) ($merged['shortTitle'] ?? dent_default_cohort_short_title($key)), 80);
    if ($shortTitle === '') {
        $shortTitle = $title;
    }

    $description = dent_clean_text((string) ($merged['description'] ?? ''), 240);
    $year = dent_normalize_digits((string) ($merged['year'] ?? ''));
    if ($year === '' && preg_match('/(\d{4})$/', $key, $matches) === 1) {
        $year = (string) $matches[1];
    }

    $services = dent_normalize_cohort_services($key, $productType, $notesMode, $merged['services'] ?? null);
    $routes = dent_normalize_cohort_routes($key, $services, $merged['routes'] ?? null);

    return [
        'key' => $key,
        'title' => $title,
        'shortTitle' => $shortTitle,
        'description' => $description,
        'productType' => $productType,
        'year' => $year,
        'siteVariant' => $siteVariant,
        'notesMode' => $notesMode,
        'allowRepresentativeManagement' => dent_parse_bool($merged['allowRepresentativeManagement'] ?? false, false),
        'supportsRotationGroups' => dent_parse_bool($merged['supportsRotationGroups'] ?? ($key === dent_primary_cohort_key()), $key === dent_primary_cohort_key()),
        'services' => $services,
        'routes' => $routes,
        'sortOrder' => max(0, (int) ($merged['sortOrder'] ?? 9999)),
        'isSeeded' => dent_parse_bool($merged['isSeeded'] ?? false, false),
        'isIsolated' => dent_parse_bool($merged['isIsolated'] ?? true, true),
        'createdAt' => trim((string) ($merged['createdAt'] ?? dent_iso_now())),
        'updatedAt' => trim((string) ($merged['updatedAt'] ?? dent_iso_now())),
    ];
}

function dent_normalize_cohort_catalog(array $seed): array
{
    $catalog = [];
    foreach (dent_default_cohort_catalog() as $key => $record) {
        $normalized = dent_normalize_cohort_record((string) $key, is_array($record) ? $record : []);
        if ($normalized !== null) {
            $catalog[$normalized['key']] = $normalized;
        }
    }

    foreach ($seed as $key => $record) {
        if (!is_array($record)) {
            continue;
        }

        $normalized = dent_normalize_cohort_record((string) $key, $record);
        if ($normalized === null) {
            continue;
        }

        $catalog[$normalized['key']] = array_merge($catalog[$normalized['key']] ?? [], $normalized);
    }

    uasort($catalog, static function (array $left, array $right): int {
        $orderCompare = (int) ($left['sortOrder'] ?? 9999) <=> (int) ($right['sortOrder'] ?? 9999);
        if ($orderCompare !== 0) {
            return $orderCompare;
        }
        return strcmp((string) ($left['key'] ?? ''), (string) ($right['key'] ?? ''));
    });

    return $catalog;
}

function dent_dis_number_index(): array
{
    return [
        '40211272014' => '2325',
        '40211272055' => '2326',
        '40211272063' => '2327',
        '40211272074' => '2328',
        '40211272012' => '2329',
        '40211272018' => '2330',
        '40211272086' => '2331',
        '40211272005' => '2332',
        '40211272031' => '2333',
        '40211272049' => '2334',
        '40211272040' => '2335',
        '40211272007' => '2336',
        '40211272013' => '2337',
        '40211272083' => '2338',
        '40211272030' => '2339',
        '40211272036' => '2340',
        '40211272039' => '2341',
        '40121272022' => '2342',
        '40211272087' => '2343',
        '40211272045' => '2344',
        '40211272054' => '2345',
        '40211272078' => '2346',
        '40211272059' => '2347',
        '40211272082' => '2348',
        '40221272093' => '2349',
        '40211272075' => '2350',
        '40211272032' => '2351',
        '40211272069' => '2352',
        '40211272010' => '2353',
        '40111272001' => '2354',
        '40211272004' => '2355',
        '40211272057' => '2356',
        '40211272092' => '2357',
        '40211272067' => '2358',
        '40211272077' => '2359',
        '40211272073' => '2360',
        '40211272052' => '2361',
        '40211272084' => '2362',
        '40211272047' => '2363',
        '40211272085' => '2364',
        '40211272017' => '2365',
        '40211272081' => '2366',
        '40211272062' => '2367',
        '40211272060' => '2368',
        '40211272058' => '2369',
        '40211272071' => '2370',
        '40211272064' => '2371',
        '40211272026' => '2372',
        '40211272072' => '2373',
        '40211272002' => '2374',
        '40211272046' => '2375',
        '40211272029' => '2376',
        '40411272002' => '2377',
        '40211272021' => '2378',
        '40211272048' => '2379',
        '40211272044' => '2380',
        '40211272042' => '2381',
        '40211272024' => '2382',
        '40211272056' => '2383',
        '40121272046' => '2384',
        '40211272070' => '2385',
        '40211272076' => '2386',
        '40211272001' => '2387',
        '40211272033' => '2388',
        '40211272080' => '2389',
        '40211272011' => '2390',
        '40211272034' => '2391',
        '40211272051' => '2392',
        '40211272050' => '2393',
        '40211272068' => '2394',
        '40211272041' => '2395',
        '40211272089' => '2396',
        '40211272027' => '2397',
        '40211272035' => '2398',
        '40211272028' => '2399',
        '40211272025' => '2400',
        '40211272008' => '2401',
        '40211272023' => '2402',
        '40211272022' => '2403',
        '40211272037' => '2405',
        '40211272006' => '2406',
        '40211272009' => '2407',
        '40211272043' => '2408',
        '40411272001' => '2409',
        '40211272088' => '2410',
        '40211272015' => '2411',
        '40121272047' => '2412',
        '40211272079' => '2413',
        '40211272091' => '2414',
        '40211272053' => '2415',
        '40211272019' => '2416',
        '40211272016' => '2417',
        '40211272020' => '2418',
        '40111272027' => '2419',
        '40211272038' => '2420',
        '40211272061' => '2421',
        '40211272003' => '2422',
    ];
}

function dent_dis_number_for_student($studentNumber): string
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        return '';
    }

    return dent_dis_number_index()[$studentNumber] ?? '';
}

function dent_auth_store_path(): string
{
    return dent_storage_path('auth/users.json');
}

function dent_auth_store_backup_path(): string
{
    return dent_storage_path('auth/users.backup.json');
}

function dent_auth_store_seed_payload(): array
{
    return [
        'schemaVersion' => 2,
        'ownerStudentNumber' => dent_owner_student_number(),
        'cohorts' => dent_default_cohort_catalog(),
        'users' => [],
    ];
}

function dent_auth_store_runtime_cache(?array $nextStore = null, bool $replace = false, bool $clear = false): ?array
{
    static $cachedStore = null;
    static $cachedSignature = '';

    $signature = static function (): string {
        $path = dent_auth_store_path();
        clearstatcache(false, $path);
        if (!is_file($path)) {
            return 'missing';
        }

        $mtime = @filemtime($path);
        $size = @filesize($path);
        return ($mtime === false ? 'unknown' : (string) $mtime) . '|' . ($size === false ? 'unknown' : (string) $size);
    };

    if ($clear) {
        $cachedStore = null;
        $cachedSignature = '';
        return null;
    }

    if ($replace) {
        $cachedStore = $nextStore;
        $cachedSignature = $signature();
    }

    if ($cachedStore !== null && $cachedSignature !== $signature()) {
        $cachedStore = null;
        $cachedSignature = '';
    }

    return $cachedStore;
}

function dent_decode_auth_store_snapshot(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }

    $raw = @file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }
    if (!isset($decoded['users']) || !is_array($decoded['users'])) {
        return null;
    }
    if (isset($decoded['schemaVersion']) && (!is_int($decoded['schemaVersion']) || $decoded['schemaVersion'] < 1)) {
        return null;
    }

    return $decoded;
}

function dent_write_auth_store_payload(array $payload, bool $refreshBackup = true): void
{
    $path = dent_auth_store_path();
    $backupPath = dent_auth_store_backup_path();
    $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    $json = json_encode($payload, $flags);
    if ($json === false) {
        dent_error('خطا در تولید داده JSON.', 500);
    }

    $writeSnapshot = static function (string $targetPath, string $contents): void {
        dent_ensure_directory(dirname($targetPath));
        $tmpPath = $targetPath . '.tmp.' . bin2hex(random_bytes(6));
        $handle = @fopen($tmpPath, 'xb');
        if ($handle === false) {
            dent_error('خطا در ذخیره‌سازی داده‌ها.', 500);
        }
        $total = 0;
        $length = strlen($contents);
        try {
            while ($total < $length) {
                $written = @fwrite($handle, substr($contents, $total));
                if ($written === false || $written === 0) {
                    throw new RuntimeException('AUTH_STORE_SHORT_WRITE');
                }
                $total += $written;
            }
            if (!@fflush($handle)) {
                throw new RuntimeException('AUTH_STORE_FLUSH_FAILED');
            }
            if (function_exists('fsync') && !@fsync($handle)) {
                throw new RuntimeException('AUTH_STORE_FSYNC_FAILED');
            }
        } catch (Throwable $exception) {
            @fclose($handle);
            @unlink($tmpPath);
            dent_error('خطا در ذخیره‌سازی داده‌ها.', 500);
        } finally {
            if (is_resource($handle)) {
                @fclose($handle);
            }
        }
        if ($total !== $length) {
            @unlink($tmpPath);
            dent_error('خطا در ذخیره‌سازی داده‌ها.', 500);
        }
        $verified = dent_decode_auth_store_snapshot($tmpPath);
        if (!is_array($verified) || @filesize($tmpPath) !== $length) {
            @unlink($tmpPath);
            dent_error('خطا در ذخیره‌سازی داده‌ها.', 500);
        }
        if (!@rename($tmpPath, $targetPath)) {
            @unlink($tmpPath);
            dent_error('خطا در ذخیره‌سازی داده‌ها.', 500);
        }
    };

    if ($refreshBackup && is_file($path) && filesize($path) > 0) {
        $current = dent_decode_auth_store_snapshot($path);
        if (is_array($current)) {
            $currentJson = json_encode($current, $flags);
            if ($currentJson !== false) {
                $writeSnapshot($backupPath, $currentJson . PHP_EOL);
            }
        }
    }

    $writeSnapshot($path, $json . PHP_EOL);
    dent_auth_store_runtime_cache(null, false, true);
}

function dent_public_html_path(string $relativePath): string
{
    $trimmed = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);
    return DENT_PROJECT_ROOT . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR . $trimmed;
}

function dent_default_profile(): array
{
    return [
        'about' => '',
        'bio' => '',
        'contactHandle' => '',
        'focusArea' => '',
        'avatarUrl' => '',
    ];
}

function dent_normalize_national_code(?string $value): string
{
    $digits = preg_replace('/\D+/u', '', dent_normalize_digits($value)) ?? '';
    if ($digits === '') {
        return '';
    }

    if (strlen($digits) !== 10) {
        return '';
    }

    return $digits;
}

function dent_to_fa_digits(string $value): string
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

function dent_rotation_name_canonical(string $value): string
{
    $normalized = dent_force_utf8($value);
    $normalized = str_replace(
        ['ي', 'ك', 'ة', 'ۀ', 'أ', 'إ', 'ٱ', 'آ', 'ؤ', 'ئ'],
        ['ی', 'ک', 'ه', 'ه', 'ا', 'ا', 'ا', 'ا', 'و', 'ی'],
        $normalized
    );
    $normalized = preg_replace('/[\x{200c}\x{200d}\x{200e}\x{200f}\s\-_]+/u', '', $normalized) ?? '';
    $normalized = preg_replace('/[^\p{L}\p{N}]+/u', '', $normalized) ?? '';
    $normalized = trim(dent_utf8_strtolower($normalized));
    return $normalized;
}

function dent_rotation_name_keys(string $value): array
{
    $base = dent_rotation_name_canonical($value);
    if ($base === '') {
        return [];
    }

    $keys = [$base => true];
    $prefixes = ['سیده', 'سید', 'سادات'];
    foreach ($prefixes as $prefix) {
        if (!str_starts_with($base, $prefix)) {
            continue;
        }
        $next = trim(dent_utf8_substr($base, dent_utf8_strlen($prefix), dent_utf8_strlen($base)));
        if ($next !== '') {
            $keys[$next] = true;
        }
    }

    return array_keys($keys);
}

function dent_rotation_group_catalog(): array
{
    return [
        [
            'rotationId' => 1,
            'groupNumber' => 1,
            'groupTitle' => 'گروه آقای آقایی',
            'members' => [
                'سید مهدی آقایی',
                'سجاد اشرف',
                'سجاد اشرف گنجوئی',
                'سجاد اشرف گنجویی',
                'حسام قربانی',
                'حسین شاهسواری',
                'مهدی گنجی وطن',
                'نازنین عبادی',
                'مبینا روحانی',
                'مینا مطبعه چی',
                'زهرا رفیعی راد',
                'فرزاد مصطفوی',
            ],
        ],
        [
            'rotationId' => 1,
            'groupNumber' => 2,
            'groupTitle' => 'گروه آقای پورهاشمی',
            'members' => [
                'حسام محمدی',
                'امیرمهدی طاهری',
                'رضا رحیمی',
                'حسین عبدی',
                'محمدرضا حسینی جی',
                'شقایق باقری',
                'سارینا غلامی',
                'مهدی پورهاشمی',
                'عرفان محبوبی‌نیا',
                'عرفان محبوبی نیا',
            ],
        ],
        [
            'rotationId' => 1,
            'groupNumber' => 3,
            'groupTitle' => 'گروه آقای آهنگ',
            'members' => [
                'حسین آهنگ',
                'احسان فضلی',
                'علیرضا نصرتی',
                'ابولفضل موسی زاده',
                'ابوالفضل موسی زاده',
                'امیرحسین درواری',
                'سیدامیرحسین درواری',
                'آیناز شاهنده',
                'مبینا لطفی',
                'اسما زارعی پور',
                'سیده اسما زارعی پور',
                'فاطمه موسی زاده',
                'شیدا سادات کریمی',
                'بهاره مهدیقلی',
            ],
        ],
        [
            'rotationId' => 1,
            'groupNumber' => 4,
            'groupTitle' => 'گروه آقای موسوی',
            'members' => [
                'مانی موسوی',
                'آرمان اعوانی',
                'آتنا یوسف زاده',
                'مهدی اسماعیلی',
                'رژین جباری',
                'مائده بابایی آذر',
                'محدثه رستملو',
                'رضوانه کاظمی مقدم',
                'محمدحسین رضازاده',
                'علیرضا تیموری',
            ],
        ],
        [
            'rotationId' => 1,
            'groupNumber' => 5,
            'groupTitle' => 'گروه خانم کریمی',
            'members' => [
                'زهرا کریمی',
                'بهاره ابراهیمی',
                'وستا ایزدی',
                'نسترن طبسی',
                'فاطمه فتحی',
                'ثنا مهدوی',
                'مجتبی نیک مراد',
                'امیرحسین شهیدی',
                'آرمین عظیمی',
                'مهدی رستمی',
            ],
        ],
        [
            'rotationId' => 2,
            'groupNumber' => 6,
            'groupTitle' => 'گروه خانم مالکی',
            'members' => [
                'صبا مالکی',
                'الناز امیردادی',
                'کوثر قربانی',
                'آرین قاسم پور',
                'فاطمه افلاطونی',
                'مریم قنبری',
                'سحر سلیمانی',
                'محدثه هدایتی',
                'فاطمه سالمی',
            ],
        ],
        [
            'rotationId' => 2,
            'groupNumber' => 7,
            'groupTitle' => 'گروه خانم جهان‌زاد',
            'members' => [
                'درسا ابراهیم‌زاده',
                'درسا ابراهیم زاده',
                'امیرمحمد امجدی',
                'بردیا باطبی',
                'آرین تقوی',
                'سحر جهان‌زاد',
                'سحر جهان زاد',
                'امیرحسین حاتمی',
                'نازنین ختایی',
                'عسل عزیزمحمدی',
                'آیلین هاشمی',
                'شقایق صمدیان',
            ],
        ],
        [
            'rotationId' => 2,
            'groupNumber' => 8,
            'groupTitle' => 'گروه آقای زارع',
            'members' => [
                'علی‌سینا امیری',
                'علی سینا امیری',
                'محمد بهمن‌آبادی',
                'محمد بهمن ابادی',
                'هومن چاووشی‌فر',
                'هومن چاووشی فر',
                'ایلیا خان‌محمدی',
                'ایلیا خان محمدی',
                'محمد مهدی زارع',
                'سید امیر‌حسین علوی',
                'سید امیرحسین علوی',
                'امیر‌محمد قلندری',
                'امیرمحمد قلندری',
                'حسین محمدی',
                'محمد‌جواد نصر',
                'محمدجواد نصر',
                'محمد جواد نصر',
                'محمد‌رضا یگانه',
                'محمدرضا یگانه',
                'محمد رضا یگانه',
            ],
        ],
        [
            'rotationId' => 2,
            'groupNumber' => 9,
            'groupTitle' => 'گروه خانم عابدی',
            'members' => [
                'فاطمه عابدی',
                'فاطمه سادات مرجانی',
                'هستی غلامی',
                'فاطمه صابری',
                'فاطمه زین العابدینی',
                'مهدیه دهقان',
                'کوثر حاجیان نژاد',
                'آنیتا شیرخانی',
                'مبینا زمانی',
            ],
        ],
        [
            'rotationId' => 2,
            'groupNumber' => 10,
            'groupTitle' => 'گروه خانم محمودی',
            'members' => [
                'امیررضا شادمهر',
                'امیرمحمد قیصری',
                'امیر محمد قیصری',
                'محمد نوشادیان',
                'بشری محمودی',
                'ریحانه حقیقی',
                'سارا زارع کاشانی',
                'فاطمه باباپور',
                'عماد ریاست',
                'محمدرضا باجلان',
                'سارینا صادق پور',
            ],
        ],
    ];
}

function dent_rotation_assignment_index(): array
{
    static $index = null;
    if (is_array($index)) {
        return $index;
    }

    $index = [];
    foreach (dent_rotation_group_catalog() as $group) {
        $rotationId = (int) ($group['rotationId'] ?? 0);
        $groupNumber = (int) ($group['groupNumber'] ?? 0);
        if (!in_array($rotationId, [1, 2], true) || $groupNumber <= 0) {
            continue;
        }

        $assignment = [
            'rotationId' => $rotationId,
            'rotationLabel' => 'روتیشن ' . ($rotationId === 1 ? '۱' : '۲'),
            'groupNumber' => $groupNumber,
            'groupLabel' => 'گروه ' . strtr((string) $groupNumber, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']),
            'groupTitle' => (string) ($group['groupTitle'] ?? ''),
        ];
        $assignment['summary'] = $assignment['rotationLabel'] . ' • ' . $assignment['groupLabel'] . ' (' . $assignment['groupTitle'] . ')';

        $members = is_array($group['members'] ?? null) ? $group['members'] : [];
        foreach ($members as $memberName) {
            foreach (dent_rotation_name_keys((string) $memberName) as $key) {
                if (!isset($index[$key])) {
                    $index[$key] = $assignment;
                }
            }
        }
    }

    return $index;
}

function dent_rotation_group_options(): array
{
    static $options = null;
    if (is_array($options)) {
        return $options;
    }

    $options = [];
    foreach (dent_rotation_group_catalog() as $group) {
        $rotationId = (int) ($group['rotationId'] ?? 0);
        $groupNumber = (int) ($group['groupNumber'] ?? 0);
        if (!in_array($rotationId, [1, 2], true) || $groupNumber <= 0) {
            continue;
        }

        $groupTitle = trim((string) ($group['groupTitle'] ?? ''));
        $rotationLabel = 'روتیشن ' . ($rotationId === 1 ? '۱' : '۲');
        $groupLabel = 'گروه ' . dent_to_fa_digits((string) $groupNumber);
        $summary = $rotationLabel . ' • ' . $groupLabel . ($groupTitle !== '' ? (' (' . $groupTitle . ')') : '');

        $options[] = [
            'rotationId' => $rotationId,
            'rotationLabel' => $rotationLabel,
            'groupNumber' => $groupNumber,
            'groupLabel' => $groupLabel,
            'groupTitle' => $groupTitle,
            'summary' => $summary,
            'source' => 'catalog',
            'isCampus' => false,
        ];
    }

    usort($options, static function (array $left, array $right): int {
        $leftOrder = ((int) ($left['rotationId'] ?? 0)) * 100 + (int) ($left['groupNumber'] ?? 0);
        $rightOrder = ((int) ($right['rotationId'] ?? 0)) * 100 + (int) ($right['groupNumber'] ?? 0);
        return $leftOrder <=> $rightOrder;
    });

    return $options;
}

function dent_rotation_group_option(int $rotationId, int $groupNumber): ?array
{
    static $index = null;
    if (!is_array($index)) {
        $index = [];
        foreach (dent_rotation_group_options() as $option) {
            $key = (string) ($option['rotationId'] ?? 0) . ':' . (string) ($option['groupNumber'] ?? 0);
            $index[$key] = $option;
        }
    }

    return $index[$rotationId . ':' . $groupNumber] ?? null;
}

function dent_campus_rotation_assignment(): array
{
    return [
        'rotationId' => null,
        'rotationLabel' => 'پردیس',
        'groupNumber' => null,
        'groupLabel' => 'دانشجوی پردیس',
        'groupTitle' => 'دانشجوی پردیس',
        'summary' => 'دانشجوی پردیس',
        'source' => 'campus',
        'isCampus' => true,
    ];
}

function dent_normalize_rotation_override($raw): array
{
    if (!is_array($raw)) {
        return ['mode' => 'none'];
    }

    $mode = trim(strtolower((string) ($raw['mode'] ?? 'none')));
    if ($mode === '' || $mode === 'auto' || $mode === 'catalog') {
        $mode = 'none';
    }

    if ($mode === 'campus') {
        return ['mode' => 'campus'];
    }

    if ($mode === 'manual') {
        $rotationId = (int) ($raw['rotationId'] ?? 0);
        $groupNumber = (int) ($raw['groupNumber'] ?? 0);
        $groupOption = dent_rotation_group_option($rotationId, $groupNumber);
        if (!is_array($groupOption)) {
            return ['mode' => 'none'];
        }

        return [
            'mode' => 'manual',
            'rotationId' => $rotationId,
            'groupNumber' => $groupNumber,
            'groupTitle' => (string) ($groupOption['groupTitle'] ?? ''),
        ];
    }

    return ['mode' => 'none'];
}

function dent_rotation_assignment_for_name(string $name): ?array
{
    $keys = dent_rotation_name_keys($name);
    if ($keys === []) {
        return null;
    }

    $index = dent_rotation_assignment_index();
    foreach ($keys as $key) {
        if (isset($index[$key])) {
            $matched = $index[$key];
            $matched['source'] = 'catalog';
            $matched['isCampus'] = false;
            return $matched;
        }
    }

    foreach ($keys as $key) {
        if (dent_utf8_strlen($key) < 6) {
            continue;
        }
        foreach ($index as $indexedName => $assignment) {
            if (dent_utf8_strlen($indexedName) < 6) {
                continue;
            }
            if (str_contains($indexedName, $key) || str_contains($key, $indexedName)) {
                $assignment['source'] = 'catalog';
                $assignment['isCampus'] = false;
                return $assignment;
            }
        }
    }

    return null;
}

function dent_user_rotation_assignment(array $user): ?array
{
    if (!dent_cohort_supports_rotation_groups(dent_user_cohort_key($user))) {
        return null;
    }

    $override = dent_normalize_rotation_override($user['rotationOverride'] ?? null);
    $mode = (string) ($override['mode'] ?? 'none');

    if ($mode === 'campus') {
        return dent_campus_rotation_assignment();
    }

    if ($mode === 'manual') {
        $rotationId = (int) ($override['rotationId'] ?? 0);
        $groupNumber = (int) ($override['groupNumber'] ?? 0);
        $groupOption = dent_rotation_group_option($rotationId, $groupNumber);
        if (is_array($groupOption)) {
            $groupOption['source'] = 'manual';
            $groupOption['isCampus'] = false;
            return $groupOption;
        }
    }

    $name = trim((string) ($user['name'] ?? ''));
    if ($name === '') {
        return null;
    }

    return dent_rotation_assignment_for_name($name);
}

function dent_clean_avatar_url(?string $value, bool $strict = false): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (strlen($value) > 400000) {
        if ($strict) {
            dent_error('اندازه عکس پروفایل بیش از حد مجاز است.', 422);
        }
        return '';
    }

    if (preg_match('/^data:image\/(png|jpe?g|webp);base64,/i', $value) === 1) {
        $parts = explode(',', $value, 2);
        if (count($parts) !== 2) {
            if ($strict) {
                dent_error('فرمت عکس پروفایل نامعتبر است.', 422);
            }
            return '';
        }

        $payload = preg_replace('/\s+/u', '', $parts[1]) ?? '';
        if ($payload === '') {
            if ($strict) {
                dent_error('داده عکس پروفایل خالی است.', 422);
            }
            return '';
        }

        $binary = base64_decode($payload, true);
        if ($binary === false) {
            if ($strict) {
                dent_error('داده عکس پروفایل معتبر نیست.', 422);
            }
            return '';
        }

        if (strlen($binary) > (220 * 1024)) {
            if ($strict) {
                dent_error('حجم عکس پروفایل بیش از حد مجاز است.', 422);
            }
            return '';
        }

        return $parts[0] . ',' . $payload;
    }

    if (str_starts_with($value, '/')) {
        return dent_clean_text($value, 400);
    }

    $parts = parse_url($value);
    if ($parts === false) {
        if ($strict) {
            dent_error('آدرس عکس پروفایل معتبر نیست.', 422);
        }
        return '';
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['https', 'http'], true)) {
        if ($strict) {
            dent_error('فقط آدرس http/https برای عکس پروفایل مجاز است.', 422);
        }
        return '';
    }

    return dent_clean_text($value, 400);
}

function dent_hash_password(string $password): string
{
    $password = dent_normalize_digits($password);
    if ($password === '') {
        dent_error('رمز عبور خالی معتبر نیست.', 422);
    }

    $iterations = 210000;
    $salt = random_bytes(16);
    $derived = hash_pbkdf2('sha256', $password, $salt, $iterations, 32, true);

    return 'pbkdf2_sha256$' . $iterations . '$' . dent_base64url_encode($salt) . '$' . dent_base64url_encode($derived);
}

function dent_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function dent_base64url_decode(string $value): string
{
    $padding = strlen($value) % 4;
    if ($padding > 0) {
        $value .= str_repeat('=', 4 - $padding);
    }

    return base64_decode(strtr($value, '-_', '+/'), true) ?: '';
}

function dent_verify_password(string $password, string $storedHash): bool
{
    $password = dent_normalize_digits($password);
    $storedHash = trim($storedHash);

    if ($password === '' || $storedHash === '') {
        return false;
    }

    if (str_starts_with($storedHash, 'pbkdf2_sha256$')) {
        $parts = explode('$', $storedHash, 4);
        if (count($parts) !== 4) {
            return false;
        }

        $iterations = (int) $parts[1];
        $salt = dent_base64url_decode($parts[2]);
        $expected = dent_base64url_decode($parts[3]);
        if ($iterations < 10000 || $salt === '' || $expected === '') {
            return false;
        }

        $derived = hash_pbkdf2('sha256', $password, $salt, $iterations, strlen($expected), true);
        return hash_equals($expected, $derived);
    }

    $normalizedStored = dent_normalize_digits($storedHash);
    if ($normalizedStored !== '' && hash_equals($normalizedStored, $password)) {
        return true;
    }

    if (preg_match('/^[a-f0-9]{32}$/i', $storedHash) === 1) {
        return hash_equals(strtolower($storedHash), md5($password));
    }

    if (preg_match('/^[a-f0-9]{40}$/i', $storedHash) === 1) {
        return hash_equals(strtolower($storedHash), sha1($password));
    }

    return password_verify($password, $storedHash);
}

function dent_password_needs_rehash(string $storedHash): bool
{
    if (!str_starts_with($storedHash, 'pbkdf2_sha256$')) {
        return true;
    }

    $parts = explode('$', $storedHash, 4);
    if (count($parts) !== 4) {
        return true;
    }

    return (int) $parts[1] !== 210000;
}

function dent_normalize_role(?string $role, string $studentNumber): string
{
    if ($studentNumber === dent_owner_student_number()) {
        return 'owner';
    }

    $role = trim((string) $role);
    if (in_array($role, ['representative', 'prosthesis_student', 'prosthesis_representative', 'external_exam_user'], true)) {
        return $role;
    }

    return 'student';
}

function dent_default_user_cohort_key(string $studentNumber, string $role): string
{
    if ($studentNumber === dent_owner_student_number()) {
        return '';
    }

    if (in_array($role, ['prosthesis_student', 'prosthesis_representative'], true)) {
        return dent_prosthesis_legacy_cohort_key();
    }

    if (dent_user_is_external_exam_role($role)) {
        return dent_external_site_users_cohort_key();
    }

    return dent_primary_cohort_key();
}

function dent_cohort_catalog(): array
{
    $store = dent_load_user_store();
    return is_array($store['cohorts'] ?? null) ? $store['cohorts'] : dent_normalize_cohort_catalog([]);
}

function dent_cohort_record(string $cohortKey): ?array
{
    $clean = dent_clean_cohort_key($cohortKey);
    if ($clean === '') {
        return null;
    }

    $catalog = dent_cohort_catalog();
    return is_array($catalog[$clean] ?? null) ? $catalog[$clean] : null;
}

function dent_cohort_exists(string $cohortKey): bool
{
    return dent_cohort_record($cohortKey) !== null;
}

function dent_user_cohort_key(array $user): string
{
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    $role = dent_normalize_role((string) ($user['role'] ?? 'student'), $studentNumber);
    $cohortKey = dent_clean_cohort_key((string) ($user['cohortKey'] ?? ''));
    if ($cohortKey === '') {
        $cohortKey = dent_default_user_cohort_key($studentNumber, $role);
    }
    if (dent_user_is_external_exam_role($role)) {
        $cohortKey = dent_external_site_users_cohort_key();
    }

    return $cohortKey;
}

function dent_is_prosthesis_cohort_key(string $cohortKey): bool
{
    $record = dent_cohort_record($cohortKey);
    return is_array($record) && (string) ($record['productType'] ?? '') === 'prosthesis';
}

function dent_cohort_allows_representative_management(string $cohortKey): bool
{
    $record = dent_cohort_record($cohortKey);
    return is_array($record) && !empty($record['allowRepresentativeManagement']);
}

function dent_cohort_supports_rotation_groups(string $cohortKey): bool
{
    $record = dent_cohort_record($cohortKey);
    return is_array($record) && !empty($record['supportsRotationGroups']);
}

function dent_role_label(string $role): string
{
    if ($role === 'owner') {
        return 'مالک سامانه';
    }

    if ($role === 'representative') {
        return 'نماینده';
    }

    if ($role === 'prosthesis_representative') {
        return 'نماینده پروتز';
    }

    if ($role === 'prosthesis_student') {
        return 'دانشجوی پروتز';
    }

    return 'دانشجو';
}

function dent_user_is_external_exam_role(string $role): bool
{
    return $role === 'external_exam_user';
}

function dent_permissions_for_role(string $role, ?string $cohortKey = null): array
{
    $cohortKey = dent_clean_cohort_key($cohortKey ?? '');
    $cohortManagedByRepresentative = $cohortKey !== ''
        && $cohortKey !== dent_primary_cohort_key()
        && dent_cohort_allows_representative_management($cohortKey);

    if ($role === 'owner') {
        return [
            'moderateChat' => true,
            'manageUsers' => true,
            'manageRepresentatives' => true,
            'manageProsthesisNotes' => true,
            'manageCohort' => true,
            'manageForms' => true,
            'manageNotes' => true,
            'manageGrades' => true,
        ];
    }

    if ($role === 'representative') {
        return [
            'moderateChat' => true,
            'manageUsers' => $cohortManagedByRepresentative,
            'manageRepresentatives' => $cohortManagedByRepresentative,
            'manageProsthesisNotes' => false,
            'manageCohort' => $cohortManagedByRepresentative,
            'manageForms' => $cohortManagedByRepresentative,
            'manageNotes' => $cohortManagedByRepresentative,
            'manageGrades' => $cohortManagedByRepresentative,
        ];
    }

    if ($role === 'prosthesis_representative') {
        return [
            'moderateChat' => true,
            'manageUsers' => true,
            'manageRepresentatives' => true,
            'manageProsthesisNotes' => true,
            'manageCohort' => true,
            'manageForms' => true,
            'manageNotes' => true,
            'manageGrades' => true,
        ];
    }

    return [
        'moderateChat' => false,
        'manageUsers' => false,
        'manageRepresentatives' => false,
        'manageProsthesisNotes' => false,
        'manageCohort' => false,
        'manageForms' => false,
        'manageNotes' => false,
        'manageGrades' => false,
    ];
}

function dent_user_is_prosthesis(array $user): bool
{
    $cohortKey = dent_user_cohort_key($user);
    if ($cohortKey !== '') {
        return dent_is_prosthesis_cohort_key($cohortKey);
    }

    $role = dent_normalize_role($user['role'] ?? 'student', (string) ($user['studentNumber'] ?? ''));
    return $role === 'prosthesis_student' || $role === 'prosthesis_representative';
}

function dent_require_main_site_user(): array
{
    $user = dent_require_user();
    if (dent_user_is_prosthesis($user)) {
        dent_error('این حساب به بخش‌های دانشجویی دندانپزشکی دسترسی ندارد.', 403);
    }

    return $user;
}

function dent_normalize_user_record($studentNumber, array $user): array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    $role = dent_normalize_role($user['role'] ?? 'student', $studentNumber);
    $cohortKey = dent_clean_cohort_key((string) ($user['cohortKey'] ?? ''));
    if ($cohortKey === '') {
        $cohortKey = dent_default_user_cohort_key($studentNumber, $role);
    }
    $profile = $user['profile'] ?? [];
    $rotationOverride = dent_normalize_rotation_override($user['rotationOverride'] ?? null);
    $defaults = dent_default_profile();
    $name = dent_clean_text((string) ($user['name'] ?? $studentNumber), 120);
    if ($name === '') {
        $name = $studentNumber;
    }

    $about = dent_clean_text($profile['about'] ?? ($profile['bio'] ?? $defaults['about']), 280);
    if ($about === '') {
        $about = dent_clean_text($profile['bio'] ?? '', 280);
    }

    $bio = dent_clean_text($profile['bio'] ?? $about, 280);
    if ($bio === '') {
        $bio = $about;
    }

    $phoneNumber = dent_normalize_phone_number((string) (
        $user['phoneNumber']
        ?? ($user['phone']['number'] ?? '')
    ));
    $phoneVerifiedAt = trim((string) (
        $user['phoneVerifiedAt']
        ?? ($user['phone']['verifiedAt'] ?? '')
    ));
    $phoneLoginEnabledRaw = $user['phoneLoginEnabled']
        ?? ($user['phone']['otpLoginEnabled'] ?? null);
    $phoneLoginEnabled = $phoneLoginEnabledRaw === null
        ? ($phoneVerifiedAt !== '')
        : filter_var($phoneLoginEnabledRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if (!is_bool($phoneLoginEnabled)) {
        $phoneLoginEnabled = $phoneVerifiedAt !== '';
    }
    $phoneNudgeDismissedAt = trim((string) (
        $user['phoneNudgeDismissedAt']
        ?? ($user['phone']['nudgeDismissedAt'] ?? '')
    ));
    $nationalCode = dent_normalize_national_code((string) (
        $user['nationalCode']
        ?? ($user['private']['nationalCode'] ?? '')
    ));
    $directoryPhoneNumber = dent_normalize_phone_number((string) (
        $user['directoryPhoneNumber']
        ?? ($user['private']['directoryPhoneNumber'] ?? '')
    ));
    if ($phoneNumber === '') {
        $phoneVerifiedAt = '';
        $phoneLoginEnabled = false;
    } elseif ($phoneVerifiedAt === '') {
        $phoneLoginEnabled = false;
    }

    $normalized = [
        'studentNumber' => $studentNumber,
        'name' => $name,
        'passwordHash' => (string) ($user['passwordHash'] ?? ''),
        'role' => $role,
        'cohortKey' => $cohortKey,
        'profile' => [
            'about' => $about,
            'bio' => $bio,
            'contactHandle' => dent_clean_text($profile['contactHandle'] ?? $defaults['contactHandle'], 80),
                'focusArea' => dent_clean_text($profile['focusArea'] ?? $defaults['focusArea'], 80),
                'avatarUrl' => dent_clean_avatar_url($profile['avatarUrl'] ?? $defaults['avatarUrl'], false),
        ],
        'phoneNumber' => $phoneNumber,
        'phoneVerifiedAt' => $phoneVerifiedAt,
        'phoneLoginEnabled' => $phoneLoginEnabled,
        'phoneNudgeDismissedAt' => $phoneNudgeDismissedAt,
        'nationalCode' => $nationalCode,
        'directoryPhoneNumber' => $directoryPhoneNumber,
        'rotationOverride' => $rotationOverride,
        'createdAt' => trim((string) ($user['createdAt'] ?? dent_iso_now())),
        'updatedAt' => trim((string) ($user['updatedAt'] ?? dent_iso_now())),
    ];

    if ($normalized['passwordHash'] === '') {
        dent_error('یک حساب کاربری بدون رمز معتبر در storage پیدا شد.', 500);
    }

    return $normalized;
}

function dent_read_csv_table(string $path): array
{
    if (!file_exists($path)) {
        return [[], []];
    }

    $handle = @fopen($path, 'r');
    if ($handle === false) {
        return [[], []];
    }

    $header = fgetcsv($handle);
    if ($header === false || !is_array($header)) {
        fclose($handle);
        return [[], []];
    }
    $header = array_map(
        static fn($value): string => dent_force_utf8((string) $value),
        $header
    );

    $rows = [];
    while (($row = fgetcsv($handle)) !== false) {
        $rows[] = array_map(
            static fn($value): string => dent_force_utf8((string) $value),
            is_array($row) ? $row : []
        );
    }

    fclose($handle);
    return [$header, $rows];
}

function dent_legacy_role(string $studentNumber): string
{
    if ($studentNumber === dent_owner_student_number()) {
        return 'owner';
    }

    if ($studentNumber === '40211272021') {
        return 'representative';
    }

    return 'student';
}

function dent_legacy_user_record(string $studentNumber, string $name, string $password): ?array
{
    $normalizedStudentNumber = dent_normalize_student_number($studentNumber);
    $normalizedPassword = dent_normalize_digits($password);

    if ($normalizedStudentNumber === '' || $normalizedPassword === '') {
        return null;
    }

    $timestamp = dent_iso_now();

    return [
        'studentNumber' => $normalizedStudentNumber,
        'name' => trim($name) !== '' ? trim($name) : $normalizedStudentNumber,
        'passwordHash' => $normalizedPassword,
        'role' => dent_legacy_role($normalizedStudentNumber),
        'profile' => dent_default_profile(),
        'createdAt' => $timestamp,
        'updatedAt' => $timestamp,
    ];
}

function dent_load_legacy_users(): array
{
    $users = [];

    [$chatHeader, $chatRows] = dent_read_csv_table(dent_public_html_path('chat/users.csv'));
    $chatUserIdx = array_search('username', $chatHeader, true);
    $chatPassIdx = array_search('password', $chatHeader, true);
    $chatNameIdx = array_search('name', $chatHeader, true);

    if ($chatUserIdx !== false && $chatPassIdx !== false) {
        foreach ($chatRows as $row) {
            $record = dent_legacy_user_record(
                (string) ($row[$chatUserIdx] ?? ''),
                (string) ($row[$chatNameIdx !== false ? $chatNameIdx : $chatUserIdx] ?? ''),
                (string) ($row[$chatPassIdx] ?? '')
            );

            if ($record === null) {
                continue;
            }

            $users[$record['studentNumber']] = $record;
        }
    }

    [$gradesHeader, $gradesRows] = dent_read_csv_table(dent_public_html_path('grades/grades.csv'));
    $gradesUserIdx = array_search('StudentID', $gradesHeader, true);
    $gradesPassIdx = array_search('Password', $gradesHeader, true);
    $gradesNameIdx = array_search('Name', $gradesHeader, true);

    if ($gradesUserIdx !== false && $gradesPassIdx !== false) {
        foreach ($gradesRows as $row) {
            $record = dent_legacy_user_record(
                (string) ($row[$gradesUserIdx] ?? ''),
                (string) ($row[$gradesNameIdx !== false ? $gradesNameIdx : $gradesUserIdx] ?? ''),
                (string) ($row[$gradesPassIdx] ?? '')
            );

            if ($record === null) {
                continue;
            }

            $studentNumber = $record['studentNumber'];
            if (!isset($users[$studentNumber])) {
                $users[$studentNumber] = $record;
                continue;
            }

            if (($users[$studentNumber]['name'] ?? $studentNumber) === $studentNumber && $record['name'] !== '') {
                $users[$studentNumber]['name'] = $record['name'];
            }

            if (trim((string) ($users[$studentNumber]['passwordHash'] ?? '')) === '' && $record['passwordHash'] !== '') {
                $users[$studentNumber]['passwordHash'] = $record['passwordHash'];
            }
        }
    }

    $normalizedUsers = [];
    foreach ($users as $studentNumber => $user) {
        $normalized = dent_normalize_user_record((string) $studentNumber, $user);
        $normalizedUsers[$normalized['studentNumber']] = $normalized;
    }

    ksort($normalizedUsers, SORT_STRING);
    return $normalizedUsers;
}

function dent_prosthesis_1402_roster(): array
{
    return [
        '40211448012' => ['firstName' => 'امیررضا', 'lastName' => 'آئین باروق', 'representative' => false],
        '40211448014' => ['firstName' => 'اسماعیل', 'lastName' => 'ابراهیمی', 'representative' => false],
        '40211448019' => ['firstName' => 'مهتاب', 'lastName' => 'اسماعیلی', 'representative' => true],
        '40211448010' => ['firstName' => 'سیده ستایش', 'lastName' => 'اسماعیلی شیاده', 'representative' => false],
        '40211448006' => ['firstName' => 'مجتبی', 'lastName' => 'اشرف', 'representative' => true],
        '40211448004' => ['firstName' => 'زهرا', 'lastName' => 'بابائی', 'representative' => false],
        '40211448001' => ['firstName' => 'مهدیه', 'lastName' => 'بیات شهبازی', 'representative' => false],
        '40211448013' => ['firstName' => 'نرگس', 'lastName' => 'خلیلی', 'representative' => false],
        '40211448018' => ['firstName' => 'علیرضا', 'lastName' => 'زارع کاریزی', 'representative' => false],
        '40211448005' => ['firstName' => 'مهدی', 'lastName' => 'عمرانی', 'representative' => false],
        '40211448007' => ['firstName' => 'ریحانه', 'lastName' => 'قاسمی', 'representative' => false],
        '40211448015' => ['firstName' => 'سارا', 'lastName' => 'قریب', 'representative' => false],
        '40211448002' => ['firstName' => 'امیر', 'lastName' => 'کردلو', 'representative' => false],
        '40211448003' => ['firstName' => 'دلنیا', 'lastName' => 'کریمی سرابشهرک', 'representative' => false],
        '40211448017' => ['firstName' => 'تینا', 'lastName' => 'کمالی شکیب', 'representative' => false],
        '40211448008' => ['firstName' => 'امیرحسین', 'lastName' => 'مقدسیان', 'representative' => false, 'preserveExistingRole' => true],
        '40211448011' => ['firstName' => 'فاطمه', 'lastName' => 'مهدی زاده اردکانی', 'representative' => false],
        '40211448024' => ['firstName' => 'حمیدرضا', 'lastName' => 'منگلی', 'representative' => false],
    ];
}

function dent_apply_prosthesis_1402_roster(array $users): array
{
    $changed = false;
    $now = dent_iso_now();
    $prosthesisCohortKey = dent_prosthesis_legacy_cohort_key();

    foreach (dent_prosthesis_1402_roster() as $studentNumber => $entry) {
        $studentNumber = dent_normalize_student_number((string) $studentNumber);
        if ($studentNumber === '') {
            continue;
        }

        $firstName = dent_clean_text((string) ($entry['firstName'] ?? ''), 60);
        $lastName = dent_clean_text((string) ($entry['lastName'] ?? ''), 60);
        $fullName = trim($firstName . ' ' . $lastName);
        $targetRole = !empty($entry['representative']) ? 'prosthesis_representative' : 'prosthesis_student';

        if (!isset($users[$studentNumber]) || !is_array($users[$studentNumber])) {
            $users[$studentNumber] = dent_normalize_user_record($studentNumber, [
                'studentNumber' => $studentNumber,
                'name' => $fullName !== '' ? $fullName : $studentNumber,
                'passwordHash' => dent_hash_password('12345678'),
                'role' => $targetRole,
                'cohortKey' => $prosthesisCohortKey,
                'profile' => dent_default_profile(),
                'rotationOverride' => ['mode' => 'none'],
                'createdAt' => $now,
                'updatedAt' => $now,
            ]);
            $changed = true;
            continue;
        }

        $user = $users[$studentNumber];
        $userChanged = false;
        if (dent_user_cohort_key($user) !== $prosthesisCohortKey) {
            $user['cohortKey'] = $prosthesisCohortKey;
            $userChanged = true;
        }

        $currentRole = dent_normalize_role((string) ($user['role'] ?? 'student'), $studentNumber);
        if (empty($entry['preserveExistingRole']) && $currentRole !== $targetRole) {
            $user['role'] = $targetRole;
            $userChanged = true;
        }

        if ($userChanged) {
            $user['updatedAt'] = $now;
            $users[$studentNumber] = dent_normalize_user_record($studentNumber, $user);
            $changed = true;
        }
    }

    ksort($users, SORT_STRING);

    return [
        'users' => $users,
        'changed' => $changed,
    ];
}

function dent_load_user_store(): array
{
    $cachedStore = dent_auth_store_runtime_cache();
    if (is_array($cachedStore)) {
        return $cachedStore;
    }

    $path = dent_auth_store_path();
    $backupPath = dent_auth_store_backup_path();
    $seedPayload = dent_auth_store_seed_payload();
    $storeFileExists = is_file($path);
    $backupFileExists = is_file($backupPath);
    $backupMissing = !$backupFileExists;
    $store = dent_decode_auth_store_snapshot($path);
    $backupStore = dent_decode_auth_store_snapshot($backupPath);
    $restoredFromBackup = false;
    $seededFromLegacy = false;

    if (!is_array($store)) {
        if (is_array($backupStore)) {
            $store = $backupStore;
            $restoredFromBackup = true;
        } elseif ($storeFileExists || $backupFileExists) {
            throw new DentJsonPersistenceException(
                'AUTH_STORE_NO_VALID_GENERATION',
                'No valid auth store generation is available'
            );
        } else {
            $store = $seedPayload;
        }
    }

    $rawCohorts = is_array($store['cohorts'] ?? null) ? $store['cohorts'] : [];
    $cohorts = dent_normalize_cohort_catalog($rawCohorts);
    $cohortChanged = json_encode($rawCohorts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !== json_encode($cohorts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $users = $store['users'] ?? [];
    if (!is_array($users)) {
        $users = [];
    }

    $normalizedUsers = [];
    foreach ($users as $key => $user) {
        if (!is_array($user)) {
            continue;
        }

        $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? $key));
        if ($studentNumber === '') {
            continue;
        }

        $normalizedUsers[$studentNumber] = dent_normalize_user_record($studentNumber, $user);
    }

    ksort($normalizedUsers, SORT_STRING);

    if ($storeFileExists && !$normalizedUsers && is_array($backupStore)) {
        $backupUsers = $backupStore['users'] ?? [];
        if (is_array($backupUsers)) {
            $normalizedBackupUsers = [];
            foreach ($backupUsers as $key => $user) {
                if (!is_array($user)) {
                    continue;
                }

                $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? $key));
                if ($studentNumber === '') {
                    continue;
                }

                $normalizedBackupUsers[$studentNumber] = dent_normalize_user_record($studentNumber, $user);
            }

            if ($normalizedBackupUsers) {
                ksort($normalizedBackupUsers, SORT_STRING);
                $normalizedUsers = $normalizedBackupUsers;
                $rawCohorts = is_array($backupStore['cohorts'] ?? null) ? $backupStore['cohorts'] : [];
                $cohorts = dent_normalize_cohort_catalog($rawCohorts);
                $cohortChanged = true;
                $restoredFromBackup = true;
            }
        }
    }

    if (!$storeFileExists && !$normalizedUsers) {
        $normalizedUsers = dent_load_legacy_users();
        $seededFromLegacy = true;
    }

    $prosthesisRosterResult = dent_apply_prosthesis_1402_roster($normalizedUsers);
    $normalizedUsers = is_array($prosthesisRosterResult['users'] ?? null)
        ? $prosthesisRosterResult['users']
        : $normalizedUsers;

    if ($restoredFromBackup || $seededFromLegacy || $backupMissing || $cohortChanged || !empty($prosthesisRosterResult['changed'])) {
        dent_write_auth_store_payload([
            'schemaVersion' => 2,
            'ownerStudentNumber' => dent_owner_student_number(),
            'cohorts' => $cohorts,
            'users' => $normalizedUsers,
        ], !$restoredFromBackup);
    }

    $result = [
        'schemaVersion' => 2,
        'ownerStudentNumber' => dent_owner_student_number(),
        'cohorts' => $cohorts,
        'users' => $normalizedUsers,
    ];

    dent_auth_store_runtime_cache($result, true);
    return $result;
}

function dent_save_user_store(array $store): void
{
    $users = $store['users'] ?? [];
    if (!is_array($users)) {
        $users = [];
    }

    $normalizedUsers = [];
    foreach ($users as $studentNumber => $user) {
        if (!is_array($user)) {
            continue;
        }

        $normalizedStudentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? $studentNumber));
        if ($normalizedStudentNumber === '') {
            continue;
        }

        $normalizedUsers[$normalizedStudentNumber] = dent_normalize_user_record($normalizedStudentNumber, $user);
    }

    ksort($normalizedUsers, SORT_STRING);

    $nextStore = [
        'schemaVersion' => 2,
        'ownerStudentNumber' => dent_owner_student_number(),
        'cohorts' => dent_normalize_cohort_catalog(is_array($store['cohorts'] ?? null) ? $store['cohorts'] : []),
        'users' => $normalizedUsers,
    ];

    dent_write_auth_store_payload($nextStore);
    dent_auth_store_runtime_cache($nextStore, true);

    $publicUsersCache = &dent_public_users_cache_ref();
    $publicUsersCache = [];
}

function dent_get_user_record($studentNumber): ?array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        return null;
    }

    $store = dent_load_user_store();
    return $store['users'][$studentNumber] ?? null;
}

function dent_public_user(array $user): array
{
    $role = dent_normalize_role($user['role'] ?? 'student', (string) ($user['studentNumber'] ?? ''));
    $cohortKey = dent_user_cohort_key($user);
    $cohort = $cohortKey !== '' ? dent_cohort_record($cohortKey) : null;
    $permissions = dent_permissions_for_role($role, $cohortKey);
    $rotation = dent_user_rotation_assignment($user);
    $rotationSource = $rotation !== null ? (string) ($rotation['source'] ?? 'catalog') : 'none';
    $rotationPayload = [
        'assigned' => $rotation !== null,
        'rotationId' => $rotation['rotationId'] ?? null,
        'rotationLabel' => $rotation['rotationLabel'] ?? '',
        'groupNumber' => $rotation['groupNumber'] ?? null,
        'groupLabel' => $rotation['groupLabel'] ?? '',
        'groupTitle' => $rotation['groupTitle'] ?? '',
        'summary' => $rotation['summary'] ?? '',
        'source' => $rotationSource,
        'isCampus' => (bool) ($rotation['isCampus'] ?? false),
    ];

    return [
        'studentNumber' => (string) ($user['studentNumber'] ?? ''),
        'disNumber' => dent_dis_number_for_student((string) ($user['studentNumber'] ?? '')),
        'name' => (string) ($user['name'] ?? ''),
        'role' => $role,
        'roleLabel' => dent_user_is_external_exam_role($role) ? 'کاربر عادی سایت' : dent_role_label($role),
        'cohortKey' => $cohortKey,
        'cohort' => $cohort === null ? null : dent_public_cohort_record($cohort),
        'isOwner' => $role === 'owner',
        'isRepresentative' => $role === 'representative',
        'isExternalExamUser' => dent_user_is_external_exam_role($role),
        'canUseChat' => !dent_user_is_external_exam_role($role),
        'isProsthesisStudent' => dent_user_is_prosthesis($user),
        'isProsthesisRepresentative' => $role === 'prosthesis_representative',
        'canModerateChat' => $permissions['moderateChat'],
        'permissions' => $permissions,
        'profile' => [
            'about' => (string) (($user['profile']['about'] ?? ($user['profile']['bio'] ?? '')) ?: ''),
            'bio' => (string) (($user['profile']['bio'] ?? ($user['profile']['about'] ?? '')) ?: ''),
            'contactHandle' => (string) (($user['profile']['contactHandle'] ?? '') ?: ''),
            'focusArea' => (string) (($user['profile']['focusArea'] ?? '') ?: ''),
            'avatarUrl' => (string) (($user['profile']['avatarUrl'] ?? '') ?: ''),
        ],
        'phone' => [
            'hasNumber' => (string) ($user['phoneNumber'] ?? '') !== '',
            'numberMasked' => dent_mask_phone_number((string) ($user['phoneNumber'] ?? '')),
            'verified' => (string) ($user['phoneVerifiedAt'] ?? '') !== '',
            'verifiedAt' => (string) ($user['phoneVerifiedAt'] ?? ''),
            'otpLoginEnabled' => (bool) ($user['phoneLoginEnabled'] ?? false),
            'canLoginWithOtp' => dent_user_phone_ready_for_otp($user),
            'nudgeDismissedAt' => (string) ($user['phoneNudgeDismissedAt'] ?? ''),
        ],
        'siteSettings' => [
            'appearance' => dent_site_appearance_public_settings(),
        ],
        'rotation' => $rotationPayload,
        'createdAt' => (string) ($user['createdAt'] ?? ''),
        'updatedAt' => (string) ($user['updatedAt'] ?? ''),
    ];
}

function dent_owner_private_user_fields(array $user): array
{
    $nationalCode = dent_normalize_national_code((string) ($user['nationalCode'] ?? ''));
    $directoryPhone = dent_normalize_phone_number((string) ($user['directoryPhoneNumber'] ?? ''));

    return [
        'nationalCode' => $nationalCode,
        'directoryPhoneNumber' => $directoryPhone,
        'nationalCodeMasked' => $nationalCode === '' ? '' : ('******' . substr($nationalCode, -4)),
        'directoryPhoneMasked' => $directoryPhone === '' ? '' : dent_mask_phone_number($directoryPhone),
        'hasNationalCode' => $nationalCode !== '',
        'hasDirectoryPhone' => $directoryPhone !== '',
    ];
}

function dent_auth_status(array $user): string
{
    return 'logged-in';
}

function dent_auth_sessions_path(): string
{
    return dent_storage_path('auth/active_sessions.json');
}

function dent_auth_sessions_default_store(): array
{
    return [
        'schemaVersion' => 1,
        'updatedAt' => '',
        'sessions' => [],
    ];
}

function dent_auth_sessions_load_store(): array
{
    $path = dent_auth_sessions_path();
    $store = dent_read_json_file($path, dent_auth_sessions_default_store());
    if (!isset($store['sessions']) || !is_array($store['sessions'])) {
        throw new DentJsonPersistenceException(
            'AUTH_SESSIONS_SCHEMA_INVALID',
            'Existing auth session store has an invalid schema'
        );
    }
    return $store;
}

function dent_auth_sessions_with_lock(callable $callback, bool $required = false): array
{
    $path = dent_auth_sessions_path();
    dent_ensure_directory(dirname($path));
    $lock = @fopen($path . '.lock', 'c+');
    if ($lock === false || !@flock($lock, LOCK_EX)) {
        if (is_resource($lock)) {
            @fclose($lock);
        }
        if ($required) {
            dent_error('امکان مدیریت نشست‌های حساب وجود ندارد.', 503);
        }
        return [];
    }

    try {
        $store = dent_auth_sessions_load_store();

        $now = time();
        foreach ($store['sessions'] as $id => $row) {
            if (!is_array($row)) {
                unset($store['sessions'][$id]);
                continue;
            }
            $lastSeen = strtotime((string) ($row['lastSeenAt'] ?? ($row['createdAt'] ?? '')));
            $status = (string) ($row['status'] ?? 'active');
            $retention = $status === 'active' ? 60 * 60 * 24 * 45 : 60 * 60 * 24 * 7;
            if ($lastSeen !== false && ($now - $lastSeen) > $retention) {
                unset($store['sessions'][$id]);
            }
        }

        $result = $callback($store);
        $store['schemaVersion'] = 1;
        $store['updatedAt'] = dent_iso_now();
        try {
            dent_write_json_file($path, $store, true);
        } catch (DentJsonPersistenceException $exception) {
            if ($required) {
                throw $exception;
            }
            return [];
        }
        return is_array($result) ? $result : [];
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function dent_auth_session_client_summary(): array
{
    $ua = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $lower = strtolower($ua);
    $browser = 'مرورگر';
    if (str_contains($lower, 'edg/')) {
        $browser = 'Edge';
    } elseif (str_contains($lower, 'firefox/')) {
        $browser = 'Firefox';
    } elseif (str_contains($lower, 'crios/') || str_contains($lower, 'chrome/')) {
        $browser = 'Chrome';
    } elseif (str_contains($lower, 'safari/')) {
        $browser = 'Safari';
    }

    $os = 'دستگاه ناشناس';
    $type = 'computer';
    if (str_contains($lower, 'iphone')) {
        $os = 'iPhone';
        $type = 'phone';
    } elseif (str_contains($lower, 'ipad')) {
        $os = 'iPad';
        $type = 'tablet';
    } elseif (str_contains($lower, 'android')) {
        $os = 'Android';
        $type = str_contains($lower, 'mobile') ? 'phone' : 'tablet';
    } elseif (str_contains($lower, 'windows')) {
        $os = 'Windows';
    } elseif (str_contains($lower, 'mac os') || str_contains($lower, 'macintosh')) {
        $os = 'macOS';
    } elseif (str_contains($lower, 'linux')) {
        $os = 'Linux';
    }

    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $network = '';
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        $network = implode('.', array_slice($parts, 0, 3)) . '.x';
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $parts = explode(':', $ip);
        $network = implode(':', array_slice($parts, 0, 3)) . ':…';
    }

    return [
        'browser' => $browser,
        'operatingSystem' => $os,
        'deviceType' => $type,
        'label' => $browser . ' روی ' . $os,
        'network' => $network,
        'userAgentSummary' => dent_clean_text($ua, 220),
    ];
}

function dent_auth_session_current_public_id(): string
{
    $id = trim((string) ($_SESSION['auth_session_public_id'] ?? ''));
    if (preg_match('/^das-[a-f0-9]{24}$/', $id) !== 1) {
        $id = 'das-' . bin2hex(random_bytes(12));
        $_SESSION['auth_session_public_id'] = $id;
    }
    return $id;
}

function dent_auth_session_register(string $studentNumber, bool $force = false): array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '' || session_status() !== PHP_SESSION_ACTIVE) {
        return [];
    }
    $id = dent_auth_session_current_public_id();
    $now = dent_iso_now();
    $summary = dent_auth_session_client_summary();
    $sessionHash = hash('sha256', session_id());

    $result = dent_auth_sessions_with_lock(static function (array &$store) use ($id, $studentNumber, $now, $summary, $sessionHash, $force): array {
        $existing = is_array($store['sessions'][$id] ?? null) ? $store['sessions'][$id] : [];
        if (!$force && $existing !== [] && (string) ($existing['status'] ?? '') !== 'active') {
            return ['ok' => false, 'revoked' => true];
        }
        $store['sessions'][$id] = array_merge($existing, $summary, [
            'id' => $id,
            'userKey' => $studentNumber,
            'sessionHash' => $sessionHash,
            'status' => 'active',
            'createdAt' => (string) (($existing['createdAt'] ?? '') ?: $now),
            'lastSeenAt' => $now,
            'revokedAt' => '',
            'revokedReason' => '',
        ]);
        return ['ok' => true, 'session' => $store['sessions'][$id]];
    });

    $_SESSION['auth_session_touch_at'] = time();
    return $result;
}

function dent_auth_session_validate_and_touch(string $studentNumber): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return true;
    }
    $id = trim((string) ($_SESSION['auth_session_public_id'] ?? ''));
    if ($id === '') {
        dent_auth_session_register($studentNumber, true);
        return true;
    }

    $store = dent_auth_sessions_load_store();
    $record = is_array($store['sessions'][$id] ?? null) ? $store['sessions'][$id] : null;
    if (!is_array($record)) {
        dent_auth_session_register($studentNumber, true);
        return true;
    }
    if ((string) ($record['userKey'] ?? '') !== $studentNumber || (string) ($record['status'] ?? '') !== 'active') {
        unset($_SESSION['student_number'], $_SESSION['auth_at'], $_SESSION['auth_session_public_id'], $_SESSION['auth_session_touch_at']);
        return false;
    }

    $lastTouch = (int) ($_SESSION['auth_session_touch_at'] ?? 0);
    if ($lastTouch <= 0 || (time() - $lastTouch) >= 300) {
        dent_auth_session_register($studentNumber, false);
    }
    return true;
}

function dent_auth_session_public_payload(array $record, string $currentId): array
{
    return [
        'id' => (string) ($record['id'] ?? ''),
        'label' => (string) ($record['label'] ?? 'نشست ورود'),
        'browser' => (string) ($record['browser'] ?? ''),
        'operatingSystem' => (string) ($record['operatingSystem'] ?? ''),
        'deviceType' => (string) ($record['deviceType'] ?? 'computer'),
        'network' => (string) ($record['network'] ?? ''),
        'createdAt' => (string) ($record['createdAt'] ?? ''),
        'lastSeenAt' => (string) ($record['lastSeenAt'] ?? ''),
        'isCurrent' => $currentId !== '' && (string) ($record['id'] ?? '') === $currentId,
        'status' => (string) ($record['status'] ?? 'active'),
    ];
}

function dent_auth_sessions_for_user(array $user): array
{
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    dent_auth_session_validate_and_touch($studentNumber);
    $currentId = trim((string) ($_SESSION['auth_session_public_id'] ?? ''));
    $store = dent_auth_sessions_load_store();
    $sessions = [];
    foreach (($store['sessions'] ?? []) as $record) {
        if (!is_array($record) || (string) ($record['userKey'] ?? '') !== $studentNumber || (string) ($record['status'] ?? '') !== 'active') {
            continue;
        }
        $sessions[] = dent_auth_session_public_payload($record, $currentId);
    }
    usort($sessions, static function (array $left, array $right): int {
        if (!empty($left['isCurrent']) !== !empty($right['isCurrent'])) {
            return !empty($left['isCurrent']) ? -1 : 1;
        }
        return strcmp((string) ($right['lastSeenAt'] ?? ''), (string) ($left['lastSeenAt'] ?? ''));
    });
    return ['sessions' => $sessions, 'currentSessionId' => $currentId];
}

function dent_auth_session_revoke(array $user, string $sessionId, string $reason = 'revoked-by-user'): array
{
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    $sessionId = trim($sessionId);
    $currentId = trim((string) ($_SESSION['auth_session_public_id'] ?? ''));
    if ($sessionId === '' || $sessionId === $currentId) {
        dent_error('برای خروج از همین دستگاه از گزینه خروج حساب استفاده کن.', 422);
    }
    return dent_auth_sessions_with_lock(static function (array &$store) use ($studentNumber, $sessionId, $reason): array {
        $record = is_array($store['sessions'][$sessionId] ?? null) ? $store['sessions'][$sessionId] : null;
        if (!is_array($record) || (string) ($record['userKey'] ?? '') !== $studentNumber) {
            dent_error('نشست موردنظر پیدا نشد.', 404);
        }
        $record['status'] = 'revoked';
        $record['revokedAt'] = dent_iso_now();
        $record['revokedReason'] = $reason;
        $store['sessions'][$sessionId] = $record;
        return ['revokedSessionId' => $sessionId];
    }, true);
}

function dent_auth_session_revoke_others(array $user): array
{
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    $currentId = trim((string) ($_SESSION['auth_session_public_id'] ?? ''));
    return dent_auth_sessions_with_lock(static function (array &$store) use ($studentNumber, $currentId): array {
        $revoked = [];
        foreach ($store['sessions'] as $id => $record) {
            if (!is_array($record) || (string) ($record['userKey'] ?? '') !== $studentNumber || (string) ($record['status'] ?? '') !== 'active' || (string) $id === $currentId) {
                continue;
            }
            $record['status'] = 'revoked';
            $record['revokedAt'] = dent_iso_now();
            $record['revokedReason'] = 'revoked-other-sessions-by-user';
            $store['sessions'][$id] = $record;
            $revoked[] = (string) $id;
        }
        return ['revokedSessionIds' => $revoked];
    }, true);
}

function dent_auth_session_mark_current_revoked(string $reason): void
{
    $id = trim((string) ($_SESSION['auth_session_public_id'] ?? ''));
    if ($id === '') {
        return;
    }
    dent_auth_sessions_with_lock(static function (array &$store) use ($id, $reason): array {
        if (is_array($store['sessions'][$id] ?? null)) {
            $store['sessions'][$id]['status'] = 'revoked';
            $store['sessions'][$id]['revokedAt'] = dent_iso_now();
            $store['sessions'][$id]['revokedReason'] = $reason;
        }
        return [];
    });
}

function dent_auth_session_csrf_token(): string
{
    $token = trim((string) ($_SESSION['auth_session_csrf_token'] ?? ''));
    if (strlen($token) < 32) {
        $token = dent_base64url_encode(random_bytes(32));
        $_SESSION['auth_session_csrf_token'] = $token;
    }
    return $token;
}

function dent_auth_session_require_csrf(): void
{
    $expected = dent_auth_session_csrf_token();
    $provided = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrfToken'] ?? '')));
    if ($provided === '' || !hash_equals($expected, $provided)) {
        dent_error('نشست صفحه منقضی شده؛ صفحه را دوباره باز کن.', 403);
    }
}

function dent_current_user(): ?array
{
    $studentNumber = dent_normalize_student_number((string) ($_SESSION['student_number'] ?? ''));
    if ($studentNumber === '') {
        return null;
    }

    $user = dent_get_user_record($studentNumber);
    if ($user === null) {
        unset($_SESSION['student_number'], $_SESSION['auth_at']);
        return null;
    }

    if (!dent_auth_session_validate_and_touch($studentNumber)) {
        return null;
    }

    return $user;
}

function dent_require_user(): array
{
    $user = dent_current_user();
    if ($user === null) {
        dent_error('نیاز به ورود به حساب کاربری است.', 401, ['loggedOut' => true]);
    }

    return $user;
}

function dent_require_owner(): array
{
    $user = dent_require_user();
    if (($user['role'] ?? 'student') !== 'owner') {
        dent_error('دسترسی این بخش فقط برای مالک سامانه مجاز است.', 403);
    }

    return $user;
}

function dent_requested_cohort_key(?string $fallback = null): string
{
    $raw = $_POST['cohort'] ?? ($_GET['cohort'] ?? ($fallback ?? dent_primary_cohort_key()));
    $key = dent_clean_cohort_key((string) $raw);
    return $key !== '' ? $key : dent_primary_cohort_key();
}

function dent_resolve_accessible_cohort(array $user, ?string $requested = null): string
{
    $requestedKey = dent_clean_cohort_key($requested ?? dent_requested_cohort_key());
    $userRole = dent_normalize_role((string) ($user['role'] ?? 'student'), (string) ($user['studentNumber'] ?? ''));
    $userCohortKey = dent_user_cohort_key($user);

    if ($userRole === 'owner') {
        if ($requestedKey !== '' && dent_cohort_exists($requestedKey)) {
            return $requestedKey;
        }
        return dent_primary_cohort_key();
    }

    if ($requestedKey !== '' && $requestedKey !== $userCohortKey) {
        dent_error('این ورودی برای حساب شما فعال نیست.', 403);
    }

    return $userCohortKey;
}

function dent_user_has_cohort_management_access(array $user, ?string $cohortKey = null): bool
{
    $role = dent_normalize_role((string) ($user['role'] ?? 'student'), (string) ($user['studentNumber'] ?? ''));
    if ($role === 'owner') {
        return true;
    }

    $targetCohortKey = dent_clean_cohort_key($cohortKey ?? dent_user_cohort_key($user));
    if ($targetCohortKey === '' || $targetCohortKey !== dent_user_cohort_key($user)) {
        return false;
    }

    return (bool) dent_permissions_for_role($role, $targetCohortKey)['manageCohort'];
}

function dent_require_cohort_manager(?string $cohortKey = null): array
{
    $user = dent_require_user();
    $resolvedCohortKey = dent_resolve_accessible_cohort($user, $cohortKey);
    if (!dent_user_has_cohort_management_access($user, $resolvedCohortKey)) {
        dent_error('مدیریت این ورودی فقط برای مالک یا نماینده مجاز همان ورودی فعال است.', 403);
    }

    return $user;
}

function dent_target_user_belongs_to_cohort(array $user, string $cohortKey): bool
{
    return dent_user_cohort_key($user) === dent_clean_cohort_key($cohortKey);
}

function dent_can_moderate_chat(array $user): bool
{
    return (bool) dent_permissions_for_role((string) ($user['role'] ?? 'student'), dent_user_cohort_key($user))['moderateChat'];
}

function dent_verify_credentials(string $studentNumber, string $password): ?array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    $password = dent_normalize_digits($password);

    $user = dent_get_user_record($studentNumber);
    if ($user === null) {
        return null;
    }

    if (!dent_verify_password($password, (string) $user['passwordHash'])) {
        return null;
    }

    if (str_starts_with((string) ($user['passwordHash'] ?? ''), 'pbkdf2_sha256$') &&
        dent_password_needs_rehash((string) $user['passwordHash'])) {
        $user['passwordHash'] = dent_hash_password($password);
        $user['updatedAt'] = dent_iso_now();
        dent_persist_user($user);
    }

    return $user;
}

function dent_persist_user(array $user): array
{
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($studentNumber === '') {
        dent_error('شناسه کاربر نامعتبر است.', 422);
    }

    $store = dent_load_user_store();
    $existing = $store['users'][$studentNumber] ?? [];

    $merged = array_merge($existing, $user);
    $merged['studentNumber'] = $studentNumber;
    $merged['updatedAt'] = dent_iso_now();
    if (empty($merged['createdAt'])) {
        $merged['createdAt'] = dent_iso_now();
    }

    $store['users'][$studentNumber] = dent_normalize_user_record($studentNumber, $merged);
    dent_save_user_store($store);

    return $store['users'][$studentNumber];
}

function dent_login_user(array $user): array
{
    session_regenerate_id(true);
    $_SESSION['student_number'] = (string) ($user['studentNumber'] ?? '');
    $_SESSION['auth_at'] = time();
    unset($_SESSION['auth_session_public_id'], $_SESSION['auth_session_touch_at'], $_SESSION['auth_session_csrf_token']);
    dent_auth_session_register((string) ($user['studentNumber'] ?? ''), true);

    return dent_public_user($user);
}

function dent_logout_user(): void
{
    dent_auth_session_mark_current_revoked('logout');
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'] ?: '/',
            $params['domain'] ?? '',
            (bool) ($params['secure'] ?? false),
            (bool) ($params['httponly'] ?? true)
        );
    }

    session_destroy();
}

function dent_update_profile(array $user, array $input): array
{
    $existingProfile = is_array($user['profile'] ?? null) ? $user['profile'] : [];
    $aboutInput = $input['about'] ?? ($input['bio'] ?? ($existingProfile['about'] ?? ($existingProfile['bio'] ?? '')));
    $about = dent_clean_text((string) $aboutInput, 280);

    $avatarSource = array_key_exists('avatarUrl', $input)
        ? (string) $input['avatarUrl']
        : (string) ($existingProfile['avatarUrl'] ?? '');

    $user['profile'] = [
        'about' => $about,
        'bio' => $about,
        'contactHandle' => dent_clean_text($input['contactHandle'] ?? ($existingProfile['contactHandle'] ?? ''), 80),
        'focusArea' => dent_clean_text($input['focusArea'] ?? ($existingProfile['focusArea'] ?? ''), 80),
        'avatarUrl' => dent_clean_avatar_url($avatarSource, array_key_exists('avatarUrl', $input)),
    ];

    return dent_persist_user($user);
}

function dent_change_user_password(array $user, string $currentPassword, string $newPassword): array
{
    $currentPassword = dent_normalize_digits($currentPassword);
    $newPassword = dent_normalize_digits($newPassword);

    if ($currentPassword === '' || $newPassword === '') {
        dent_error('رمز فعلی و رمز جدید را کامل وارد کن.', 422);
    }

    if (!dent_verify_password($currentPassword, (string) $user['passwordHash'])) {
        dent_error('رمز فعلی درست نیست.', 422);
    }

    if (dent_utf8_strlen($newPassword) < 6) {
        dent_error('رمز جدید باید حداقل ۶ کاراکتر باشد.', 422);
    }

    $user['passwordHash'] = dent_hash_password($newPassword);

    return dent_persist_user($user);
}

function dent_sorted_cohort_records(array $cohorts): array
{
    $items = array_values($cohorts);
    usort($items, static function (array $left, array $right): int {
        $orderCompare = (int) ($left['sortOrder'] ?? 9999) <=> (int) ($right['sortOrder'] ?? 9999);
        if ($orderCompare !== 0) {
            return $orderCompare;
        }

        return strcmp((string) ($left['key'] ?? ''), (string) ($right['key'] ?? ''));
    });

    return $items;
}

function dent_visible_cohorts_for_user(array $viewer): array
{
    $catalog = dent_cohort_catalog();
    $role = dent_normalize_role((string) ($viewer['role'] ?? 'student'), (string) ($viewer['studentNumber'] ?? ''));
    if ($role === 'owner') {
        return array_map('dent_public_cohort_record', dent_sorted_cohort_records($catalog));
    }

    $cohortKey = dent_user_cohort_key($viewer);
    $record = $cohortKey !== '' ? dent_cohort_record($cohortKey) : null;
    return $record === null ? [] : [dent_public_cohort_record($record)];
}

function dent_user_can_manage_target_user(array $viewer, array $targetUser): bool
{
    $viewerRole = dent_normalize_role((string) ($viewer['role'] ?? 'student'), (string) ($viewer['studentNumber'] ?? ''));
    if ($viewerRole === 'owner') {
        return true;
    }

    if (!dent_user_has_cohort_management_access($viewer, dent_user_cohort_key($viewer))) {
        return false;
    }

    $targetRole = dent_normalize_role((string) ($targetUser['role'] ?? 'student'), (string) ($targetUser['studentNumber'] ?? ''));
    if ($targetRole === 'owner') {
        return false;
    }

    return dent_target_user_belongs_to_cohort($targetUser, dent_user_cohort_key($viewer));
}

function dent_require_manage_target_user(array $viewer, string $studentNumber): array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        dent_error('شماره دانشجویی نامعتبر است.', 422);
    }

    $targetUser = dent_get_user_record($studentNumber);
    if ($targetUser === null) {
        dent_error('کاربر موردنظر پیدا نشد.', 404);
    }

    if (!dent_user_can_manage_target_user($viewer, $targetUser)) {
        dent_error('این حساب در محدوده مدیریتی شما نیست.', 403);
    }

    return $targetUser;
}

function dent_cohort_user_counts(array $users): array
{
    $counts = [];
    foreach ($users as $user) {
        if (!is_array($user)) {
            continue;
        }

        $cohortKey = dent_user_cohort_key($user);
        if ($cohortKey === '') {
            continue;
        }

        if (!isset($counts[$cohortKey])) {
            $counts[$cohortKey] = [
                'totalUsers' => 0,
                'representatives' => 0,
            ];
        }

        $counts[$cohortKey]['totalUsers']++;
        $role = dent_normalize_role((string) ($user['role'] ?? 'student'), (string) ($user['studentNumber'] ?? ''));
        if (in_array($role, ['representative', 'prosthesis_representative'], true)) {
            $counts[$cohortKey]['representatives']++;
        }
    }

    return $counts;
}

function dent_cohort_management_payload(array $viewer, array $users): array
{
    $visibleCohorts = dent_visible_cohorts_for_user($viewer);
    $userCounts = dent_cohort_user_counts(array_map(static function ($user) {
        return is_array($user['raw'] ?? null) ? $user['raw'] : [];
    }, $users));
    $payload = [];

    foreach ($visibleCohorts as $cohort) {
        $key = (string) ($cohort['key'] ?? '');
        $permissions = dent_permissions_for_role(
            dent_normalize_role((string) ($viewer['role'] ?? 'student'), (string) ($viewer['studentNumber'] ?? '')),
            $key
        );
        $counts = $userCounts[$key] ?? ['totalUsers' => 0, 'representatives' => 0];
        $payload[] = [
            'key' => $key,
            'title' => (string) ($cohort['title'] ?? ''),
            'shortTitle' => (string) ($cohort['shortTitle'] ?? ''),
            'description' => (string) ($cohort['description'] ?? ''),
            'productType' => (string) ($cohort['productType'] ?? ''),
            'year' => (string) ($cohort['year'] ?? ''),
            'siteVariant' => (string) ($cohort['siteVariant'] ?? ''),
            'notesMode' => (string) ($cohort['notesMode'] ?? ''),
            'allowRepresentativeManagement' => !empty($cohort['allowRepresentativeManagement']),
            'supportsRotationGroups' => !empty($cohort['supportsRotationGroups']),
            'services' => is_array($cohort['services'] ?? null) ? $cohort['services'] : [],
            'routes' => is_array($cohort['routes'] ?? null) ? $cohort['routes'] : [],
            'permissions' => $permissions,
            'counts' => [
                'totalUsers' => (int) ($counts['totalUsers'] ?? 0),
                'representatives' => (int) ($counts['representatives'] ?? 0),
            ],
        ];
    }

    return $payload;
}

function &dent_public_users_cache_ref(): array
{
    static $cache = [];
    return $cache;
}

function dent_list_public_users(bool $includeOwnerPrivate = false): array
{
    $cache = &dent_public_users_cache_ref();
    $cacheKey = $includeOwnerPrivate ? '1' : '0';
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $store = dent_load_user_store();
    $users = [];

    foreach ($store['users'] as $studentNumber => $user) {
        $public = dent_public_user($user);
        if ($includeOwnerPrivate) {
            $public['ownerPrivate'] = dent_owner_private_user_fields($user);
        }
        $public['sortableName'] = trim((string) ($user['name'] ?? $studentNumber));
        $users[] = $public;
    }

    usort($users, static function (array $left, array $right): int {
        if (($left['role'] ?? '') === 'owner' && ($right['role'] ?? '') !== 'owner') {
            return -1;
        }

        if (($right['role'] ?? '') === 'owner' && ($left['role'] ?? '') !== 'owner') {
            return 1;
        }

        if (($left['role'] ?? '') === 'representative' && ($right['role'] ?? '') === 'student') {
            return -1;
        }

        if (($right['role'] ?? '') === 'representative' && ($left['role'] ?? '') === 'student') {
            return 1;
        }

        if (($left['role'] ?? '') === 'prosthesis_representative' && ($right['role'] ?? '') === 'prosthesis_student') {
            return -1;
        }

        if (($right['role'] ?? '') === 'prosthesis_representative' && ($left['role'] ?? '') === 'prosthesis_student') {
            return 1;
        }

        return strcasecmp((string) ($left['sortableName'] ?? ''), (string) ($right['sortableName'] ?? ''));
    });

    foreach ($users as &$user) {
        unset($user['sortableName']);
    }
    unset($user);

    $cache[$cacheKey] = $users;

    return $users;
}

function dent_set_representative_status(string $studentNumber, bool $isRepresentative): array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        dent_error('شماره دانشجویی نامعتبر است.', 422);
    }

    $user = dent_get_user_record($studentNumber);
    if ($user === null) {
        dent_error('کاربر موردنظر پیدا نشد.', 404);
    }

    if ($studentNumber === dent_owner_student_number()) {
        dent_error('حساب مالک اصلی نمی‌تواند از نقش مالک خارج شود.', 422);
    }

    $currentRole = dent_normalize_role((string) ($user['role'] ?? 'student'), $studentNumber);
    $cohortKey = dent_user_cohort_key($user);
    if (dent_is_prosthesis_cohort_key($cohortKey) || $currentRole === 'prosthesis_student' || $currentRole === 'prosthesis_representative') {
        $user['role'] = $isRepresentative ? 'prosthesis_representative' : 'prosthesis_student';
    } else {
        $user['role'] = $isRepresentative ? 'representative' : 'student';
    }

    return dent_persist_user($user);
}

function dent_owner_set_user_password(string $studentNumber, string $newPassword): array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        dent_error('شماره دانشجویی نامعتبر است.', 422);
    }

    $user = dent_get_user_record($studentNumber);
    if ($user === null) {
        dent_error('کاربر موردنظر پیدا نشد.', 404);
    }

    $newPassword = dent_normalize_digits($newPassword);
    if (dent_utf8_strlen($newPassword) < 6) {
        dent_error('رمز عبور باید حداقل ۶ کاراکتر باشد.', 422);
    }

    $user['passwordHash'] = dent_hash_password($newPassword);
    return dent_persist_user($user);
}

function dent_owner_set_user_rotation(
    string $studentNumber,
    string $rotationMode,
    ?int $rotationId = null,
    ?int $groupNumber = null
): array {
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        dent_error('شماره دانشجویی نامعتبر است.', 422);
    }

    $user = dent_get_user_record($studentNumber);
    if ($user === null) {
        dent_error('کاربر موردنظر پیدا نشد.', 404);
    }

    if (!dent_cohort_supports_rotation_groups(dent_user_cohort_key($user))) {
        $user['rotationOverride'] = ['mode' => 'none'];
        return dent_persist_user($user);
    }

    $rotationMode = trim(strtolower($rotationMode));
    if ($rotationMode === '' || $rotationMode === 'auto' || $rotationMode === 'catalog') {
        $rotationMode = 'none';
    }

    $overrideInput = ['mode' => $rotationMode];
    if ($rotationMode === 'manual') {
        $overrideInput['rotationId'] = (int) ($rotationId ?? 0);
        $overrideInput['groupNumber'] = (int) ($groupNumber ?? 0);
    }
    $override = dent_normalize_rotation_override($overrideInput);

    if ($rotationMode === 'manual' && ($override['mode'] ?? '') !== 'manual') {
        dent_error('برای ثبت دستی، روتیشن و گروه معتبر انتخاب کنید.', 422);
    }
    if ($rotationMode === 'campus' && ($override['mode'] ?? '') !== 'campus') {
        dent_error('حالت پردیس نامعتبر است.', 422);
    }

    $user['rotationOverride'] = $override;
    return dent_persist_user($user);
}

function dent_mark_unassigned_students_as_campus(): array
{
    $store = dent_load_user_store();
    $updatedUsers = [];
    $updatedStudentNumbers = [];

    foreach (($store['users'] ?? []) as $studentNumber => $user) {
        if (!is_array($user)) {
            continue;
        }

        $role = dent_normalize_role((string) ($user['role'] ?? 'student'), (string) $studentNumber);
        if ($role !== 'student') {
            continue;
        }
        if (!dent_cohort_supports_rotation_groups(dent_user_cohort_key($user))) {
            continue;
        }

        $override = dent_normalize_rotation_override($user['rotationOverride'] ?? null);
        if (($override['mode'] ?? 'none') !== 'none') {
            continue;
        }

        $name = trim((string) ($user['name'] ?? ''));
        if ($name !== '' && dent_rotation_assignment_for_name($name) !== null) {
            continue;
        }

        $user['rotationOverride'] = ['mode' => 'campus'];
        $normalizedStudentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? $studentNumber));
        if ($normalizedStudentNumber === '') {
            continue;
        }

        $store['users'][$normalizedStudentNumber] = dent_normalize_user_record($normalizedStudentNumber, $user);
        $updatedStudentNumbers[] = $normalizedStudentNumber;
        $updatedUsers[] = dent_public_user($store['users'][$normalizedStudentNumber]);
    }

    if ($updatedStudentNumbers !== []) {
        dent_save_user_store($store);
    }

    usort($updatedUsers, static function (array $left, array $right): int {
        return strcmp((string) ($left['studentNumber'] ?? ''), (string) ($right['studentNumber'] ?? ''));
    });

    sort($updatedStudentNumbers, SORT_STRING);

    return [
        'count' => count($updatedStudentNumbers),
        'studentNumbers' => $updatedStudentNumbers,
        'users' => $updatedUsers,
    ];
}

function dent_create_cohort(array $input): array
{
    $store = dent_load_user_store();
    $productType = strtolower(trim((string) ($input['productType'] ?? 'dentistry')));
    if (!in_array($productType, ['dentistry', 'prosthesis', 'site-users'], true)) {
        $productType = 'dentistry';
    }

    $year = dent_normalize_digits((string) ($input['year'] ?? ''));
    $requestedKey = dent_clean_cohort_key((string) ($input['key'] ?? ''));
    if ($requestedKey === '') {
        if ($year === '') {
            dent_error('برای ساخت ورودی جدید، سال یا کد ورودی لازم است.', 422);
        }
        $requestedKey = $productType . '-' . $year;
    }

    if (isset($store['cohorts'][$requestedKey])) {
        dent_error('برای این کد ورودی قبلا رکوردی ثبت شده است.', 409);
    }

    $title = dent_clean_text((string) ($input['title'] ?? ''), 140);
    if ($title === '') {
        $title = dent_default_cohort_title($requestedKey);
    }

    $shortTitle = dent_clean_text((string) ($input['shortTitle'] ?? ''), 80);
    if ($shortTitle === '') {
        $shortTitle = dent_default_cohort_short_title($requestedKey);
    }

    $siteVariant = $requestedKey === dent_prosthesis_legacy_cohort_key() ? 'prosthesis-legacy' : 'main';
    $notesMode = trim((string) ($input['notesMode'] ?? ($productType === 'prosthesis' ? 'terms' : ($productType === 'site-users' ? 'none' : 'archive'))));
    if (!in_array($notesMode, ['terms', 'archive', 'none'], true)) {
        $notesMode = 'archive';
    }

    $record = dent_normalize_cohort_record($requestedKey, [
        'title' => $title,
        'shortTitle' => $shortTitle,
        'description' => (string) ($input['description'] ?? ''),
        'productType' => $productType,
        'year' => $year,
        'siteVariant' => $siteVariant,
        'notesMode' => $notesMode,
        'allowRepresentativeManagement' => dent_parse_bool($input['allowRepresentativeManagement'] ?? ($requestedKey !== dent_primary_cohort_key()), $requestedKey !== dent_primary_cohort_key()),
        'supportsRotationGroups' => dent_parse_bool($input['supportsRotationGroups'] ?? ($requestedKey === dent_primary_cohort_key()), $requestedKey === dent_primary_cohort_key()),
        'services' => is_array($input['services'] ?? null) ? $input['services'] : [],
        'routes' => is_array($input['routes'] ?? null) ? $input['routes'] : [],
        'sortOrder' => (int) ($input['sortOrder'] ?? (140 + count($store['cohorts']) * 10)),
        'isSeeded' => false,
        'isIsolated' => true,
        'createdAt' => dent_iso_now(),
        'updatedAt' => dent_iso_now(),
    ]);

    if ($record === null) {
        dent_error('ساخت ورودی جدید انجام نشد.', 422);
    }

    $store['cohorts'][$record['key']] = $record;
    dent_save_user_store($store);
    return $record;
}

function dent_create_student_account(
    string $firstName,
    string $lastName,
    string $studentNumber,
    string $password,
    string $cohortKey = '',
    string $role = 'student',
    string $rotationMode = 'none',
    ?int $rotationId = null,
    ?int $groupNumber = null,
    string $nationalCode = '',
    string $directoryPhoneNumber = ''
): array {
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        dent_error('شماره دانشجویی نامعتبر است.', 422);
    }

    if (strlen($studentNumber) < 5 || strlen($studentNumber) > 20) {
        dent_error('شماره دانشجویی باید بین ۵ تا ۲۰ رقم باشد.', 422);
    }

    $firstName = dent_clean_text($firstName, 60);
    $lastName = dent_clean_text($lastName, 60);
    if ($firstName === '' || $lastName === '') {
        dent_error('نام و نام خانوادگی الزامی است.', 422);
    }

    $password = dent_normalize_digits($password);
    if (dent_utf8_strlen($password) < 6) {
        dent_error('رمز عبور باید حداقل ۶ کاراکتر باشد.', 422);
    }

    $store = dent_load_user_store();
    if (isset($store['users'][$studentNumber])) {
        dent_error('برای این شماره دانشجویی حسابی وجود دارد.', 409);
    }

    $cohortKey = dent_clean_cohort_key($cohortKey);
    if ($cohortKey === '') {
        $cohortKey = dent_primary_cohort_key();
    }
    if (!isset($store['cohorts'][$cohortKey])) {
        dent_error('ورودی انتخاب‌شده پیدا نشد.', 404);
    }

    $role = dent_normalize_role($role, $studentNumber);
    if ($role === 'owner') {
        dent_error('حساب مالک اصلی از این بخش قابل ساخت نیست.', 422);
    }

    $rotationMode = trim(strtolower($rotationMode));
    if (!dent_cohort_supports_rotation_groups($cohortKey)) {
        $rotationMode = 'none';
    }
    if ($rotationMode === '' || $rotationMode === 'auto' || $rotationMode === 'catalog') {
        $rotationMode = 'none';
    }
    $rotationOverrideInput = ['mode' => $rotationMode];
    if ($rotationMode === 'manual') {
        $rotationOverrideInput['rotationId'] = (int) ($rotationId ?? 0);
        $rotationOverrideInput['groupNumber'] = (int) ($groupNumber ?? 0);
    }
    $rotationOverride = dent_normalize_rotation_override($rotationOverrideInput);
    if ($rotationMode === 'manual' && ($rotationOverride['mode'] ?? '') !== 'manual') {
        dent_error('برای ثبت دستی، روتیشن و گروه معتبر انتخاب کنید.', 422);
    }

    $name = trim($firstName . ' ' . $lastName);
    $now = dent_iso_now();
    $normalizedNationalCode = dent_normalize_national_code($nationalCode);
    $normalizedDirectoryPhone = dent_normalize_phone_number($directoryPhoneNumber);
    $store['users'][$studentNumber] = dent_normalize_user_record($studentNumber, [
        'studentNumber' => $studentNumber,
        'name' => $name,
        'passwordHash' => dent_hash_password($password),
        'role' => $role,
        'cohortKey' => $cohortKey,
        'profile' => dent_default_profile(),
        'nationalCode' => $normalizedNationalCode,
        'directoryPhoneNumber' => $normalizedDirectoryPhone,
        'rotationOverride' => $rotationOverride,
        'createdAt' => $now,
        'updatedAt' => $now,
    ]);

    dent_save_user_store($store);
    return $store['users'][$studentNumber];
}

function dent_owner_sync_cohort_users(string $cohortKey, array $entries, string $defaultPassword, bool $replaceExisting = false): array
{
    $cohortKey = dent_clean_cohort_key($cohortKey);
    if ($cohortKey === '') {
        dent_error('ورودی انتخاب‌شده نامعتبر است.', 422);
    }

    $defaultPassword = dent_normalize_digits($defaultPassword);
    if (dent_utf8_strlen($defaultPassword) < 6) {
        dent_error('رمز پیش‌فرض باید حداقل ۶ کاراکتر باشد.', 422);
    }

    $store = dent_load_user_store();
    if (!isset($store['cohorts'][$cohortKey])) {
        dent_error('ورودی انتخاب‌شده پیدا نشد.', 404);
    }

    $users = is_array($store['users'] ?? null) ? $store['users'] : [];
    $incomingStudentNumbers = [];
    $createdUsers = [];
    $updatedUsers = [];
    $removedUsers = [];
    $removedUsersForCleanup = [];
    $removedStudentNumbers = [];
    $now = dent_iso_now();

    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $studentNumber = dent_normalize_student_number((string) ($entry['studentNumber'] ?? ''));
        if ($studentNumber === '') {
            dent_error('شماره دانشجویی نامعتبر است.', 422);
        }
        if (isset($incomingStudentNumbers[$studentNumber])) {
            dent_error('شماره دانشجویی ' . $studentNumber . ' در فایل/متن import تکراری است.', 422);
        }

        $firstName = dent_clean_text((string) ($entry['firstName'] ?? ''), 60);
        $lastName = dent_clean_text((string) ($entry['lastName'] ?? ''), 60);
        if ($firstName === '' || $lastName === '') {
            dent_error('نام و نام خانوادگی برای شماره دانشجویی ' . $studentNumber . ' کامل نیست.', 422);
        }

        $role = dent_normalize_role((string) ($entry['role'] ?? 'student'), $studentNumber);
        if ($role === 'owner') {
            dent_error('حساب مالک از این مسیر قابل import نیست.', 422);
        }

        $fullName = trim($firstName . ' ' . $lastName);
        $incomingStudentNumbers[$studentNumber] = true;

        if (isset($users[$studentNumber]) && is_array($users[$studentNumber])) {
            $existing = $users[$studentNumber];
            $existingCohortKey = dent_user_cohort_key($existing);
            if ($existingCohortKey !== $cohortKey) {
                if (!$replaceExisting) {
                    dent_error('شماره دانشجویی ' . $studentNumber . ' در ورودی دیگری ثبت شده است.', 409);
                }
                if (dent_is_prosthesis_cohort_key($existingCohortKey) !== dent_is_prosthesis_cohort_key($cohortKey)) {
                    dent_error('شماره دانشجویی ' . $studentNumber . ' در زیرمحصول دیگری ثبت شده است.', 409);
                }
            } elseif (!$replaceExisting) {
                dent_error('برای این شماره دانشجویی حسابی وجود دارد.', 409);
            }

            $existing['name'] = $fullName;
            $existing['role'] = $role;
            $existing['cohortKey'] = $cohortKey;
            $existing['passwordHash'] = dent_hash_password($defaultPassword);
            $existing['updatedAt'] = $now;
            $users[$studentNumber] = dent_normalize_user_record($studentNumber, $existing);
            $updatedUsers[] = dent_public_user($users[$studentNumber]);
            continue;
        }

        $users[$studentNumber] = dent_normalize_user_record($studentNumber, [
            'studentNumber' => $studentNumber,
            'name' => $fullName,
            'passwordHash' => dent_hash_password($defaultPassword),
            'role' => $role,
            'cohortKey' => $cohortKey,
            'profile' => dent_default_profile(),
            'rotationOverride' => ['mode' => 'none'],
            'createdAt' => $now,
            'updatedAt' => $now,
        ]);
        $createdUsers[] = dent_public_user($users[$studentNumber]);
    }

    if ($replaceExisting) {
        foreach ($users as $studentNumber => $user) {
            if (!is_array($user) || dent_user_cohort_key($user) !== $cohortKey) {
                continue;
            }
            if (isset($incomingStudentNumbers[$studentNumber])) {
                continue;
            }
            if ($studentNumber === dent_owner_student_number()) {
                continue;
            }

            $removedUsers[] = dent_public_user($user);
            $removedUsersForCleanup[(string) $studentNumber] = $user;
            $removedStudentNumbers[] = (string) $studentNumber;
            unset($users[$studentNumber]);
        }
    }

    ksort($users, SORT_STRING);
    $store['users'] = $users;
    dent_save_user_store($store);

    foreach ($removedStudentNumbers as $studentNumber) {
        $normalizedStudentNumber = dent_normalize_student_number((string) $studentNumber);
        if ($normalizedStudentNumber === '') {
            continue;
        }
        $removedUser = is_array($removedUsersForCleanup[$normalizedStudentNumber] ?? null) ? $removedUsersForCleanup[$normalizedStudentNumber] : [];
        $removedPhone = dent_normalize_phone_number((string) ($removedUser['phoneNumber'] ?? ''));
        dent_clear_phone_related_otp_records($normalizedStudentNumber, $removedPhone);
    }

    return [
        'createdUsers' => $createdUsers,
        'updatedUsers' => $updatedUsers,
        'removedUsers' => $removedUsers,
        'removedStudentNumbers' => $removedStudentNumbers,
        'count' => count($createdUsers) + count($updatedUsers),
        'createdCount' => count($createdUsers),
        'updatedCount' => count($updatedUsers),
        'removedCount' => count($removedUsers),
    ];
}

function dent_owner_remove_user_phone(string $studentNumber): array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        dent_error('شماره دانشجویی نامعتبر است.', 422);
    }

    if ($studentNumber === dent_owner_student_number()) {
        dent_error('حذف شماره موبایل حساب مالک اصلی از این بخش مجاز نیست.', 422);
    }

    $user = dent_get_user_record($studentNumber);
    if ($user === null) {
        dent_error('کاربر موردنظر پیدا نشد.', 404);
    }

    return dent_remove_phone_number($user);
}

function dent_owner_delete_student_account(string $studentNumber): array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        dent_error('شماره دانشجویی نامعتبر است.', 422);
    }

    if ($studentNumber === dent_owner_student_number()) {
        dent_error('حساب مالک اصلی قابل حذف نیست.', 422);
    }

    $store = dent_load_user_store();
    $user = $store['users'][$studentNumber] ?? null;
    if (!is_array($user)) {
        dent_error('کاربر موردنظر پیدا نشد.', 404);
    }

    $phoneNumber = dent_normalize_phone_number((string) ($user['phoneNumber'] ?? ''));
    unset($store['users'][$studentNumber]);
    dent_save_user_store($store);

    dent_clear_phone_related_otp_records($studentNumber, $phoneNumber);

    return dent_normalize_user_record($studentNumber, $user);
}

function dent_auth_meta_path(): string
{
    return dent_storage_path('auth/meta.json');
}

function dent_auth_secret_key_path(): string
{
    return dent_storage_path('auth/.secret.key');
}

function dent_default_auth_meta_store(): array
{
    return [
        'schemaVersion' => 1,
        'sms' => [
            'enabled' => false,
            'apiKeyEncrypted' => null,
            'patternCode' => '',
            'senderLine' => '',
            'domain' => '',
            'codeParam' => 'code',
            'updatedAt' => '',
            'lastHealthAt' => '',
            'lastHealthStatus' => 'unknown',
            'lastHealthMessage' => '',
        ],
        'otp' => [
            'records' => [],
        ],
        'siteAppearance' => [
            'bottomNavSwipeEnabled' => false,
            'bottomNavLabelsEnabled' => true,
            'bottomNavGlassEnabled' => true,
            'visualEffectsLiteEnabled' => false,
            'updatedAt' => '',
        ],
    ];
}

function dent_load_auth_meta_store(): array
{
    $defaults = dent_default_auth_meta_store();
    $raw = dent_read_json_file(dent_auth_meta_path(), $defaults);
    if (!isset($raw['sms'], $raw['otp']) || !is_array($raw['sms']) || !is_array($raw['otp'])) {
        throw new DentJsonPersistenceException(
            'AUTH_META_SCHEMA_INVALID',
            'Existing authentication metadata store has an invalid schema'
        );
    }

    $store = $defaults;
    foreach (['schemaVersion', 'sms', 'otp', 'siteAppearance'] as $key) {
        if (array_key_exists($key, $raw)) {
            $store[$key] = $raw[$key];
        }
    }

    if (!is_array($store['sms'])) {
        $store['sms'] = $defaults['sms'];
    } else {
        $store['sms'] = array_merge($defaults['sms'], $store['sms']);
    }
    if (!is_array($store['otp'])) {
        $store['otp'] = $defaults['otp'];
    } else {
        $store['otp'] = array_merge($defaults['otp'], $store['otp']);
    }
    if (!is_array($store['otp']['records'] ?? null)) {
        $store['otp']['records'] = [];
    }
    if (!is_array($store['siteAppearance'])) {
        $store['siteAppearance'] = $defaults['siteAppearance'];
    } else {
        $store['siteAppearance'] = array_merge($defaults['siteAppearance'], $store['siteAppearance']);
        $store['siteAppearance']['bottomNavSwipeEnabled'] = dent_parse_bool(
            $store['siteAppearance']['bottomNavSwipeEnabled'] ?? false,
            false
        );
        $store['siteAppearance']['bottomNavLabelsEnabled'] = dent_parse_bool(
            $store['siteAppearance']['bottomNavLabelsEnabled'] ?? true,
            true
        );
        $store['siteAppearance']['bottomNavGlassEnabled'] = dent_parse_bool(
            $store['siteAppearance']['bottomNavGlassEnabled'] ?? true,
            true
        );
        $store['siteAppearance']['visualEffectsLiteEnabled'] = dent_parse_bool(
            $store['siteAppearance']['visualEffectsLiteEnabled'] ?? false,
            false
        );
        $store['siteAppearance']['updatedAt'] = dent_clean_text((string) ($store['siteAppearance']['updatedAt'] ?? ''), 80);
    }

    return $store;
}

function dent_save_auth_meta_store(array $store): void
{
    $defaults = dent_default_auth_meta_store();
    if (!is_array($store['sms'] ?? null)) {
        $store['sms'] = $defaults['sms'];
    } else {
        $store['sms'] = array_merge($defaults['sms'], $store['sms']);
    }
    if (!is_array($store['otp'] ?? null)) {
        $store['otp'] = $defaults['otp'];
    } else {
        $store['otp'] = array_merge($defaults['otp'], $store['otp']);
    }
    if (!is_array($store['otp']['records'] ?? null)) {
        $store['otp']['records'] = [];
    }
    if (!is_array($store['siteAppearance'] ?? null)) {
        $store['siteAppearance'] = $defaults['siteAppearance'];
    } else {
        $store['siteAppearance'] = array_merge($defaults['siteAppearance'], $store['siteAppearance']);
        $store['siteAppearance']['bottomNavSwipeEnabled'] = dent_parse_bool(
            $store['siteAppearance']['bottomNavSwipeEnabled'] ?? false,
            false
        );
        $store['siteAppearance']['bottomNavLabelsEnabled'] = dent_parse_bool(
            $store['siteAppearance']['bottomNavLabelsEnabled'] ?? true,
            true
        );
        $store['siteAppearance']['bottomNavGlassEnabled'] = dent_parse_bool(
            $store['siteAppearance']['bottomNavGlassEnabled'] ?? true,
            true
        );
        $store['siteAppearance']['visualEffectsLiteEnabled'] = dent_parse_bool(
            $store['siteAppearance']['visualEffectsLiteEnabled'] ?? false,
            false
        );
        $store['siteAppearance']['updatedAt'] = dent_clean_text((string) ($store['siteAppearance']['updatedAt'] ?? ''), 80);
    }

    dent_write_json_file(dent_auth_meta_path(), [
        'schemaVersion' => 1,
        'sms' => $store['sms'],
        'otp' => $store['otp'],
        'siteAppearance' => $store['siteAppearance'],
    ]);
}

function dent_site_appearance_public_settings(): array
{
    $meta = dent_load_auth_meta_store();
    $appearance = is_array($meta['siteAppearance'] ?? null) ? $meta['siteAppearance'] : dent_default_auth_meta_store()['siteAppearance'];
    return [
        'bottomNavSwipeEnabled' => dent_parse_bool($appearance['bottomNavSwipeEnabled'] ?? false, false),
        'bottomNavLabelsEnabled' => dent_parse_bool($appearance['bottomNavLabelsEnabled'] ?? true, true),
        'bottomNavGlassEnabled' => dent_parse_bool($appearance['bottomNavGlassEnabled'] ?? true, true),
        'visualEffectsLiteEnabled' => dent_parse_bool($appearance['visualEffectsLiteEnabled'] ?? false, false),
        'updatedAt' => dent_clean_text((string) ($appearance['updatedAt'] ?? ''), 80),
    ];
}

function dent_save_site_appearance_owner_config(array $input): array
{
    $meta = dent_load_auth_meta_store();
    if (!is_array($meta['siteAppearance'] ?? null)) {
        $meta['siteAppearance'] = dent_default_auth_meta_store()['siteAppearance'];
    }

    $meta['siteAppearance']['bottomNavSwipeEnabled'] = dent_parse_bool($input['bottomNavSwipeEnabled'] ?? false, false);
    $meta['siteAppearance']['bottomNavLabelsEnabled'] = dent_parse_bool($input['bottomNavLabelsEnabled'] ?? true, true);
    $meta['siteAppearance']['bottomNavGlassEnabled'] = dent_parse_bool($input['bottomNavGlassEnabled'] ?? true, true);
    $meta['siteAppearance']['visualEffectsLiteEnabled'] = dent_parse_bool($input['visualEffectsLiteEnabled'] ?? false, false);
    $meta['siteAppearance']['updatedAt'] = dent_iso_now();
    dent_save_auth_meta_store($meta);

    return dent_site_appearance_public_settings();
}

function dent_auth_secret_key(): string
{
    static $cached = null;
    if (is_string($cached) && strlen($cached) === 32) {
        return $cached;
    }

    $env = trim((string) (getenv('DENT_AUTH_SECRET_KEY') ?: ''));
    if ($env !== '') {
        $decoded = base64_decode($env, true);
        if (is_string($decoded) && strlen($decoded) >= 32) {
            $cached = substr($decoded, 0, 32);
            return $cached;
        }
        $cached = hash('sha256', $env, true);
        return $cached;
    }

    $cached = dent_load_or_create_base64_secret_file(dent_auth_secret_key_path());
    return $cached;
}

function dent_encrypt_secret_text(string $plainText): array
{
    if (!function_exists('openssl_encrypt')) {
        dent_error('رمزنگاری سمت سرور در دسترس نیست.', 500);
    }

    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plainText, 'aes-256-gcm', dent_auth_secret_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if (!is_string($cipher) || $cipher === '' || $tag === '') {
        dent_error('رمزنگاری داده محرمانه انجام نشد.', 500);
    }

    return [
        'v' => 1,
        'alg' => 'aes-256-gcm',
        'iv' => base64_encode($iv),
        'tag' => base64_encode($tag),
        'ct' => base64_encode($cipher),
    ];
}

function dent_decrypt_secret_text($payload): string
{
    if (!is_array($payload) || !function_exists('openssl_decrypt')) {
        return '';
    }

    $iv = base64_decode((string) ($payload['iv'] ?? ''), true);
    $tag = base64_decode((string) ($payload['tag'] ?? ''), true);
    $ct = base64_decode((string) ($payload['ct'] ?? ''), true);
    if (!is_string($iv) || !is_string($tag) || !is_string($ct)) {
        return '';
    }

    $plain = openssl_decrypt($ct, 'aes-256-gcm', dent_auth_secret_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return is_string($plain) ? $plain : '';
}

function dent_normalize_phone_number(?string $value): string
{
    $digits = preg_replace('/\D+/u', '', dent_normalize_digits($value)) ?? '';
    if ($digits === '') {
        return '';
    }

    if (str_starts_with($digits, '0098')) {
        $digits = substr($digits, 4);
    } elseif (str_starts_with($digits, '98')) {
        $digits = substr($digits, 2);
    }

    if (str_starts_with($digits, '0')) {
        $digits = ltrim($digits, '0');
    }

    if (strlen($digits) !== 10 || !str_starts_with($digits, '9')) {
        return '';
    }

    return '+98' . $digits;
}

function dent_mask_phone_number(string $phoneNumber): string
{
    $normalized = dent_normalize_phone_number($phoneNumber);
    if ($normalized === '') {
        return '';
    }

    $head = substr($normalized, 0, 6);
    $tail = substr($normalized, -2);
    return $head . '***' . $tail;
}

function dent_phone_username_from_normalized(string $phoneNumber): string
{
    $normalized = dent_normalize_phone_number($phoneNumber);
    if ($normalized === '') {
        return '';
    }

    return '0' . substr($normalized, 3);
}

function dent_validate_external_signup_input(string $firstName, string $lastName, string $phoneNumber, string $password, string $passwordConfirm = ''): array
{
    $firstName = dent_clean_text($firstName, 60);
    $lastName = dent_clean_text($lastName, 80);
    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    $password = dent_normalize_digits($password);

    if ($firstName === '' || $lastName === '') {
        dent_error('نام و نام خانوادگی را کامل وارد کن.', 422);
    }
    if ($normalizedPhone === '') {
        dent_error('شماره موبایل معتبر وارد کن.', 422);
    }
    if (dent_utf8_strlen($password) < 6) {
        dent_error('رمز عبور باید حداقل ۶ کاراکتر باشد.', 422);
    }
    if ($passwordConfirm === '') {
        dent_error('تکرار رمز عبور را وارد کن.', 422);
    }
    if ($passwordConfirm !== '' && !hash_equals($password, dent_normalize_digits($passwordConfirm))) {
        dent_error('تکرار رمز عبور با رمز عبور یکسان نیست.', 422);
    }

    $username = dent_phone_username_from_normalized($normalizedPhone);
    if ($username === '' || !str_starts_with($username, '09') || strlen($username) !== 11) {
        dent_error('شماره موبایل برای ساخت نام کاربری معتبر نیست.', 422);
    }
    if (dent_get_user_record($username) !== null || dent_phone_number_in_use($normalizedPhone, $username)) {
        dent_error('با این شماره موبایل قبلاً حساب ساخته شده است. از ورود با موبایل استفاده کن.', 409);
    }

    return [
        'firstName' => $firstName,
        'lastName' => $lastName,
        'name' => trim($firstName . ' ' . $lastName),
        'phoneNumber' => $normalizedPhone,
        'username' => $username,
        'password' => $password,
    ];
}

function dent_user_phone_ready_for_otp(array $user): bool
{
    $phoneNumber = dent_normalize_phone_number((string) ($user['phoneNumber'] ?? ''));
    $verifiedAt = trim((string) ($user['phoneVerifiedAt'] ?? ''));
    $otpEnabled = (bool) ($user['phoneLoginEnabled'] ?? false);
    return $phoneNumber !== '' && $verifiedAt !== '' && $otpEnabled;
}

function dent_auth_load_dis_request_store(): array
{
    $store = dent_read_json_file(dent_storage_path('dis_request/store.json'), [
        'responses' => [],
    ]);
    if (!isset($store['responses']) || !is_array($store['responses'])) {
        throw new DentJsonPersistenceException(
            'DIS_REQUEST_STORE_SCHEMA_INVALID',
            'Existing DIS request store has an invalid schema'
        );
    }
    return $store;
}

function dent_auth_dis_request_phone_index(): array
{
    $store = dent_auth_load_dis_request_store();
    $responses = $store['responses'];
    $index = [];

    foreach ($responses as $studentNumber => $record) {
        if (!is_array($record)) {
            continue;
        }

        $normalizedStudentNumber = dent_normalize_student_number((string) ($record['studentNumber'] ?? $studentNumber));
        if ($normalizedStudentNumber === '') {
            continue;
        }

        $fields = is_array($record['fields'] ?? null) ? $record['fields'] : [];
        $phoneNumber = dent_normalize_phone_number((string) ($fields['phoneNumber'] ?? ''));
        if ($phoneNumber === '') {
            continue;
        }

        $index[$normalizedStudentNumber] = $phoneNumber;
    }

    return $index;
}

function dent_dis_request_private_identity_for_student(string $studentNumber): array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        return ['nationalCode' => '', 'phoneNumber' => ''];
    }
    $store = dent_auth_load_dis_request_store();
    $responses = $store['responses'];
    $record = is_array($responses[$studentNumber] ?? null) ? $responses[$studentNumber] : null;
    if (!is_array($record)) {
        foreach ($responses as $candidate) {
            if (!is_array($candidate)) continue;
            if (dent_normalize_student_number((string) ($candidate['studentNumber'] ?? '')) === $studentNumber) {
                $record = $candidate;
                break;
            }
        }
    }
    $fields = is_array($record['fields'] ?? null) ? $record['fields'] : [];
    return [
        'nationalCode' => dent_normalize_national_code((string) ($fields['nationalCode'] ?? '')),
        'phoneNumber' => dent_normalize_phone_number((string) ($fields['phoneNumber'] ?? '')),
    ];
}

function dent_phone_number_in_use(string $phoneNumber, string $excludeStudentNumber = ''): bool
{
    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    if ($normalizedPhone === '') {
        return false;
    }

    $excludeStudentNumber = dent_normalize_student_number($excludeStudentNumber);
    $store = dent_load_user_store();
    $disPhoneIndex = dent_auth_dis_request_phone_index();
    foreach ((array) ($store['users'] ?? []) as $studentNumber => $user) {
        if (!is_array($user)) {
            continue;
        }
        $normalizedStudentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? $studentNumber));
        if ($excludeStudentNumber !== '' && $normalizedStudentNumber === $excludeStudentNumber) {
            continue;
        }
        if (dent_normalize_phone_number((string) ($user['phoneNumber'] ?? '')) === $normalizedPhone) {
            return true;
        }
        if (dent_normalize_phone_number((string) ($user['directoryPhoneNumber'] ?? '')) === $normalizedPhone) {
            return true;
        }
        if (dent_normalize_phone_number((string) ($disPhoneIndex[$normalizedStudentNumber] ?? '')) === $normalizedPhone) {
            return true;
        }
    }

    return false;
}

function dent_find_user_by_phone(string $phoneNumber): ?array
{
    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    if ($normalizedPhone === '') {
        return null;
    }

    $store = dent_load_user_store();
    foreach ((array) ($store['users'] ?? []) as $user) {
        if (!is_array($user)) {
            continue;
        }
        if (dent_normalize_phone_number((string) ($user['phoneNumber'] ?? '')) === $normalizedPhone) {
            return $user;
        }
    }

    return null;
}

function dent_find_login_otp_user_by_phone(string $phoneNumber): ?array
{
    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    if ($normalizedPhone === '') {
        return null;
    }

    $store = dent_load_user_store();
    $users = is_array($store['users'] ?? null) ? $store['users'] : [];
    $candidates = [];
    foreach ($users as $studentNumber => $user) {
        if (!is_array($user)) {
            continue;
        }
        $normalizedStudentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? $studentNumber));
        if ($normalizedStudentNumber === '') {
            continue;
        }
        if (dent_normalize_phone_number((string) ($user['phoneNumber'] ?? '')) === $normalizedPhone
            && dent_user_phone_ready_for_otp($user)) {
            $user['_otpPhoneFallback'] = false;
            $candidates[$normalizedStudentNumber] = $user;
        }
    }

    $disPhoneIndex = dent_auth_dis_request_phone_index();
    foreach ($users as $studentNumber => $user) {
        if (!is_array($user)) {
            continue;
        }

        $normalizedStudentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? $studentNumber));
        if ($normalizedStudentNumber === '') {
            continue;
        }

        $storedPhone = dent_normalize_phone_number((string) ($user['phoneNumber'] ?? ''));
        if ($storedPhone !== '' && $storedPhone !== $normalizedPhone) {
            continue;
        }

        $directoryPhone = dent_normalize_phone_number((string) ($user['directoryPhoneNumber'] ?? ''));
        $disPhone = dent_normalize_phone_number((string) ($disPhoneIndex[$normalizedStudentNumber] ?? ''));
        if ($directoryPhone !== $normalizedPhone && $disPhone !== $normalizedPhone) {
            continue;
        }

        if (isset($candidates[$normalizedStudentNumber])) {
            continue;
        }

        $user['_otpPhoneFallback'] = true;
        $user['_otpPhoneFallbackSource'] = $directoryPhone === $normalizedPhone ? 'directory' : 'dis-request';
        $candidates[$normalizedStudentNumber] = $user;
    }

    if (count($candidates) > 1) {
        dent_error('این شماره موبایل به بیش از یک حساب وصل است. برای فعال‌سازی ورود پیامکی با مالک سایت تماس بگیر.', 409);
    }

    if (count($candidates) === 1) {
        return reset($candidates) ?: null;
    }

    return null;
}

function dent_sms_env_bool(string $name): ?bool
{
    $raw = getenv($name);
    if ($raw === false) {
        return null;
    }
    $parsed = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return is_bool($parsed) ? $parsed : null;
}

function dent_normalize_sms_sender_number(string $value): string
{
    $clean = trim(dent_normalize_digits($value));
    if ($clean === '') {
        return '';
    }
    $digits = preg_replace('/\D+/u', '', $clean) ?? '';
    if ($digits === '') {
        return '';
    }
    if (str_starts_with($digits, '0098')) {
        $digits = substr($digits, 2);
    }

    return $digits;
}

function dent_sms_provider_recipient_number(string $value): string
{
    $clean = trim(dent_normalize_digits($value));
    if ($clean === '') {
        return '';
    }

    if (str_starts_with($clean, '+')) {
        $clean = substr($clean, 1);
    }

    $digits = preg_replace('/\D+/u', '', $clean) ?? '';
    if ($digits === '') {
        return '';
    }

    if (str_starts_with($digits, '0098')) {
        $digits = '0' . substr($digits, 4);
    } elseif (str_starts_with($digits, '98')) {
        $digits = '0' . substr($digits, 2);
    } elseif (str_starts_with($digits, '9')) {
        $digits = '0' . $digits;
    }

    if (strlen($digits) !== 11 || !str_starts_with($digits, '09')) {
        return '';
    }

    return $digits;
}

function dent_sms_log(string $event, array $context = []): void
{
    $safe = [
        'event' => $event,
        'at' => dent_iso_now(),
    ];
    foreach ($context as $key => $value) {
        if (in_array((string) $key, ['apiKey', 'authorization', 'otpCode', 'code'], true)) {
            continue;
        }
        $safe[(string) $key] = is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
    }
    error_log('[dent_sms] ' . json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function dent_sms_resolved_config(): array
{
    $meta = dent_load_auth_meta_store();
    $sms = is_array($meta['sms'] ?? null) ? $meta['sms'] : [];

    $storedApiKey = dent_decrypt_secret_text($sms['apiKeyEncrypted'] ?? null);
    $envApiKey = trim((string) (getenv('DENT_SMS_FARAZ_API_KEY') ?: ''));
    $apiKey = $storedApiKey !== '' ? $storedApiKey : $envApiKey;

    $patternCode = dent_clean_text((string) ($sms['patternCode'] ?? ''), 80);
    if ($patternCode === '') {
        $patternCode = dent_clean_text((string) (getenv('DENT_SMS_FARAZ_PATTERN_CODE') ?: ''), 80);
    }

    $senderLine = dent_clean_text((string) ($sms['senderLine'] ?? ''), 40);
    if ($senderLine === '') {
        $senderLine = dent_clean_text((string) (getenv('DENT_SMS_FARAZ_SENDER_LINE') ?: ''), 40);
    }
    $senderLine = dent_normalize_sms_sender_number($senderLine);

    $domain = dent_clean_text((string) ($sms['domain'] ?? ''), 120);
    if ($domain === '') {
        $domain = dent_clean_text((string) (getenv('DENT_SMS_FARAZ_DOMAIN') ?: ''), 120);
    }
    if ($domain === '') {
        $domain = dent_clean_text((string) ($_SERVER['HTTP_HOST'] ?? ''), 120);
        $domain = preg_replace('/:\d+$/', '', $domain) ?? '';
    }
    $domain = trim($domain, " \t\n\r\0\x0B/");

    $codeParam = dent_clean_text((string) ($sms['codeParam'] ?? ''), 40);
    if ($codeParam === '') {
        $codeParam = dent_clean_text((string) (getenv('DENT_SMS_FARAZ_CODE_PARAM') ?: 'code'), 40);
    }
    if ($codeParam === '') {
        $codeParam = 'code';
    }

    $enabled = (bool) ($sms['enabled'] ?? false);
    $envEnabled = dent_sms_env_bool('DENT_SMS_FARAZ_ENABLED');
    if ($envEnabled !== null) {
        $enabled = $envEnabled;
    } elseif ($storedApiKey === '' && $envApiKey !== '' && $patternCode !== '' && $senderLine !== '') {
        $enabled = true;
    }

    return [
        'enabled' => $enabled,
        'apiKey' => $apiKey,
        'patternCode' => $patternCode,
        'senderLine' => $senderLine,
        'domain' => $domain,
        'codeParam' => $codeParam,
        'lastHealthAt' => (string) ($sms['lastHealthAt'] ?? ''),
        'lastHealthStatus' => (string) ($sms['lastHealthStatus'] ?? 'unknown'),
        'lastHealthMessage' => (string) ($sms['lastHealthMessage'] ?? ''),
    ];
}

function dent_sms_health_store_update(bool $ok, string $message): void
{
    $meta = dent_load_auth_meta_store();
    if (!is_array($meta['sms'] ?? null)) {
        $meta['sms'] = dent_default_auth_meta_store()['sms'];
    }
    $meta['sms']['lastHealthAt'] = dent_iso_now();
    $meta['sms']['lastHealthStatus'] = $ok ? 'ok' : 'error';
    $meta['sms']['lastHealthMessage'] = dent_clean_text($message, 220);
    dent_save_auth_meta_store($meta);
}

function dent_sms_status_payload(): array
{
    $config = dent_sms_resolved_config();
    return [
        'enabled' => (bool) $config['enabled'],
        'provider' => 'farazsms',
        'apiKeyConfigured' => trim((string) $config['apiKey']) !== '',
        'patternConfigured' => trim((string) $config['patternCode']) !== '',
        'senderLineConfigured' => trim((string) $config['senderLine']) !== '',
        'senderLine' => (string) $config['senderLine'],
        'domainConfigured' => trim((string) $config['domain']) !== '',
        'domain' => (string) $config['domain'],
        'codeParam' => (string) $config['codeParam'],
        'lastHealthAt' => (string) $config['lastHealthAt'],
        'lastHealthStatus' => (string) $config['lastHealthStatus'],
        'lastHealthMessage' => (string) $config['lastHealthMessage'],
    ];
}

function dent_save_sms_owner_config(array $input): array
{
    $meta = dent_load_auth_meta_store();
    if (!is_array($meta['sms'] ?? null)) {
        $meta['sms'] = dent_default_auth_meta_store()['sms'];
    }

    $enabled = filter_var($input['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $meta['sms']['enabled'] = is_bool($enabled) ? $enabled : false;
    $meta['sms']['patternCode'] = dent_clean_text((string) ($input['patternCode'] ?? ''), 80);
    $meta['sms']['senderLine'] = dent_clean_text((string) ($input['senderLine'] ?? ''), 40);
    $meta['sms']['domain'] = dent_clean_text((string) ($input['domain'] ?? ''), 120);
    $meta['sms']['domain'] = trim((string) $meta['sms']['domain'], " \t\n\r\0\x0B/");
    $meta['sms']['codeParam'] = dent_clean_text((string) ($input['codeParam'] ?? 'code'), 40);
    if ($meta['sms']['codeParam'] === '') {
        $meta['sms']['codeParam'] = 'code';
    }

    $apiKey = trim((string) ($input['apiKey'] ?? ''));
    $clearApiKey = filter_var($input['clearApiKey'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
    if ($clearApiKey) {
        $meta['sms']['apiKeyEncrypted'] = null;
    } elseif ($apiKey !== '') {
        $meta['sms']['apiKeyEncrypted'] = dent_encrypt_secret_text($apiKey);
    }

    $meta['sms']['updatedAt'] = dent_iso_now();
    dent_save_auth_meta_store($meta);
    return dent_sms_status_payload();
}

function dent_sms_send_pattern(string $phoneNumber, string $otpCode): array
{
    $config = dent_sms_resolved_config();
    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    $providerPhone = dent_sms_provider_recipient_number($normalizedPhone);

    if (!(bool) $config['enabled']) {
        dent_sms_log('send_blocked', ['reason' => 'disabled', 'phone' => dent_mask_phone_number($normalizedPhone)]);
        return ['success' => false, 'message' => 'سرویس پیامکی غیرفعال است.'];
    }
    if (trim((string) $config['apiKey']) === '') {
        dent_sms_log('send_blocked', ['reason' => 'missing_api_key', 'phone' => dent_mask_phone_number($normalizedPhone)]);
        return ['success' => false, 'message' => 'کلید API سرویس پیامکی تنظیم نشده است.'];
    }
    if (trim((string) $config['patternCode']) === '') {
        dent_sms_log('send_blocked', ['reason' => 'missing_pattern', 'phone' => dent_mask_phone_number($normalizedPhone)]);
        return ['success' => false, 'message' => 'کد پترن پیامکی تنظیم نشده است.'];
    }
    if (trim((string) $config['senderLine']) === '') {
        dent_sms_log('send_blocked', ['reason' => 'missing_sender', 'phone' => dent_mask_phone_number($normalizedPhone)]);
        return ['success' => false, 'message' => 'لاین/شماره ارسال پیامک تنظیم نشده است.'];
    }

    if ($normalizedPhone === '' || $providerPhone === '') {
        return ['success' => false, 'message' => 'شماره موبایل مقصد نامعتبر است.'];
    }

    if (!function_exists('curl_init')) {
        return ['success' => false, 'message' => 'cURL روی سرور فعال نیست.'];
    }

    $params = [
        (string) $config['codeParam'] => $otpCode,
    ];
    if (trim((string) $config['domain']) !== '') {
        $webOtpLine = '@' . (string) $config['domain'] . ' #' . $otpCode;
        $params['domain'] = (string) $config['domain'];
        $params['webotp'] = $webOtpLine;
        $params['webOtpLine'] = $webOtpLine;
    }

    $payload = [
        'code' => (string) $config['patternCode'],
        'recipient' => $providerPhone,
        'line_number' => (string) $config['senderLine'],
        'number_format' => 'english',
        'attributes' => $params,
    ];

    $attempts = 3;
    $raw = '';
    $httpCode = 0;
    $curlError = '';
    $decoded = null;

    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        dent_sms_log('send_attempt', [
            'attempt' => $attempt,
            'phone' => dent_mask_phone_number($normalizedPhone),
            'patternConfigured' => true,
            'sender' => (string) $config['senderLine'],
            'params' => implode(',', array_keys($params)),
        ]);

        $ch = curl_init('https://api.iranpayamak.com/ws/v1/sms/pattern');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Api-Key: ' . (string) $config['apiKey'],
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $raw = is_string($response) ? $response : '';
        $decoded = $raw !== '' ? json_decode($raw, true) : null;

        $temporaryFailure = $curlError !== '' || $httpCode >= 500;
        if (!$temporaryFailure) {
            break;
        }
        if ($attempt < $attempts) {
            usleep(250000 * $attempt);
            continue;
        }
    }

    if ($raw === '') {
        dent_sms_log('send_failed', [
            'reason' => 'empty_response',
            'phone' => dent_mask_phone_number($normalizedPhone),
            'httpStatus' => $httpCode,
            'curlError' => dent_clean_text($curlError, 160),
        ]);
        return [
            'success' => false,
            'message' => $curlError !== '' ? ('خطای ارتباط با سرویس پیامک: ' . $curlError) : 'پاسخی از سرویس پیامکی دریافت نشد.',
            'httpStatus' => $httpCode,
        ];
    }

    if (!is_array($decoded)) {
        dent_sms_log('send_failed', [
            'reason' => 'invalid_json',
            'phone' => dent_mask_phone_number($normalizedPhone),
            'httpStatus' => $httpCode,
        ]);
        if ($httpCode >= 500) {
            return ['success' => false, 'message' => 'سرویس پیامکی موقتاً در دسترس نیست.', 'httpStatus' => $httpCode];
        }
        $brief = dent_clean_text(trim(strip_tags($raw)), 120);
        $detail = $brief !== '' ? (' جزئیات: ' . $brief) : '';
        return ['success' => false, 'message' => 'پاسخ سرویس پیامکی نامعتبر است.' . $detail, 'httpStatus' => $httpCode];
    }

    $providerResult = dent_sms_parse_pattern_response($decoded, $httpCode);
    $ok = (bool) $providerResult['success'];
    $messageCode = (string) $providerResult['messageCode'];
    $providerRequestId = (string) $providerResult['providerRequestId'];
    $message = (string) $providerResult['message'];

    dent_sms_log($ok ? 'send_ok' : 'send_failed', [
        'phone' => dent_mask_phone_number($normalizedPhone),
        'httpStatus' => $httpCode,
        'messageCode' => $messageCode,
        'providerRequestId' => $providerRequestId,
        'providerMessage' => $message,
    ]);

    return [
        'success' => $ok,
        'message' => $message,
        'httpStatus' => $httpCode,
        'messageCode' => $messageCode,
        'providerRequestId' => $providerRequestId,
    ];
}

/**
 * Normalize the current IranPayamak/FarazSMS pattern endpoint response.
 *
 * A successful response means the provider accepted the request. It does not
 * prove delivery to the handset, so callers must not present it as delivered.
 */
function dent_sms_parse_pattern_response(array $decoded, int $httpCode): array
{
    $statusRaw = strtolower(trim((string) ($decoded['status'] ?? '')));
    $ok = $httpCode >= 200 && $httpCode < 300 && $statusRaw === 'success';
    $messageCode = dent_clean_text((string) ($decoded['code'] ?? ''), 40);
    $providerRequestId = '';
    if (is_string($decoded['data'] ?? null) || is_int($decoded['data'] ?? null)) {
        $providerRequestId = dent_clean_text((string) $decoded['data'], 80);
    }

    $message = '';
    $rawMessage = $decoded['messages'] ?? ($decoded['message'] ?? '');
    if (is_string($rawMessage)) {
        $message = dent_clean_text($rawMessage, 220);
    } elseif (is_array($rawMessage)) {
        foreach ($rawMessage as $messageItem) {
            if (is_string($messageItem)) {
                $message = dent_clean_text($messageItem, 220);
                if ($message !== '') {
                    break;
                }
                continue;
            }
            if (!is_array($messageItem)) {
                continue;
            }
            foreach ($messageItem as $nestedMessage) {
                if (!is_string($nestedMessage)) {
                    continue;
                }
                $message = dent_clean_text($nestedMessage, 220);
                if ($message !== '') {
                    break 2;
                }
            }
        }
    }
    if ($message === '') {
        $message = $ok ? 'درخواست ارسال در سرویس پیامکی ثبت شد.' : 'ارسال پیامک انجام نشد.';
    }
    if (!$ok && $httpCode >= 500) {
        $message = 'سرویس پیامکی موقتاً در دسترس نیست.';
    }

    return [
        'success' => $ok,
        'message' => $message,
        'httpStatus' => $httpCode,
        'messageCode' => $messageCode,
        'providerRequestId' => $providerRequestId,
        'acceptanceOnly' => $ok,
    ];
}

function dent_sms_send_simple(array $phoneNumbers, string $messageText): array
{
    $config = dent_sms_resolved_config();
    $cleanText = dent_clean_text($messageText, 480);

    if (!(bool) $config['enabled']) {
        dent_sms_log('send_simple_blocked', ['reason' => 'disabled']);
        return ['success' => false, 'message' => 'سرویس پیامکی غیرفعال است.'];
    }
    if (trim((string) $config['apiKey']) === '') {
        dent_sms_log('send_simple_blocked', ['reason' => 'missing_api_key']);
        return ['success' => false, 'message' => 'کلید API سرویس پیامکی تنظیم نشده است.'];
    }
    if (trim((string) $config['senderLine']) === '') {
        dent_sms_log('send_simple_blocked', ['reason' => 'missing_sender']);
        return ['success' => false, 'message' => 'لاین/شماره ارسال پیامک تنظیم نشده است.'];
    }
    if ($cleanText === '') {
        return ['success' => false, 'message' => 'متن پیامک خالی است.'];
    }
    if (!function_exists('curl_init')) {
        return ['success' => false, 'message' => 'cURL روی سرور فعال نیست.'];
    }

    $recipients = [];
    foreach ($phoneNumbers as $phoneNumber) {
        $providerPhone = dent_sms_provider_recipient_number(dent_normalize_phone_number((string) $phoneNumber));
        if ($providerPhone === '') {
            continue;
        }
        $recipients[$providerPhone] = true;
    }

    if (!$recipients) {
        return ['success' => false, 'message' => 'هیچ شماره موبایل معتبری برای ارسال پیامک پیدا نشد.'];
    }

    $payload = [
        'text' => $cleanText,
        'line_number' => (string) $config['senderLine'],
        'recipients' => array_keys($recipients),
        'number_format' => 'english',
    ];

    $attempts = 3;
    $raw = '';
    $httpCode = 0;
    $curlError = '';
    $decoded = null;

    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        dent_sms_log('send_simple_attempt', [
            'attempt' => $attempt,
            'recipientCount' => count($payload['recipients']),
            'sender' => (string) $config['senderLine'],
        ]);

        $ch = curl_init('https://api.iranpayamak.com/ws/v1/sms/simple');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Api-Key: ' . (string) $config['apiKey'],
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $raw = is_string($response) ? $response : '';
        $decoded = $raw !== '' ? json_decode($raw, true) : null;

        $temporaryFailure = $curlError !== '' || $httpCode >= 500;
        if (!$temporaryFailure) {
            break;
        }
        if ($attempt < $attempts) {
            usleep(250000 * $attempt);
            continue;
        }
    }

    if ($raw === '') {
        dent_sms_log('send_simple_failed', [
            'reason' => 'empty_response',
            'httpStatus' => $httpCode,
            'curlError' => dent_clean_text($curlError, 160),
        ]);
        return [
            'success' => false,
            'message' => $curlError !== '' ? ('خطای ارتباط با سرویس پیامک: ' . $curlError) : 'پاسخی از سرویس پیامکی دریافت نشد.',
            'httpStatus' => $httpCode,
        ];
    }

    if (!is_array($decoded)) {
        dent_sms_log('send_simple_failed', [
            'reason' => 'invalid_json',
            'httpStatus' => $httpCode,
        ]);
        if ($httpCode >= 500) {
            return ['success' => false, 'message' => 'سرویس پیامکی موقتاً در دسترس نیست.', 'httpStatus' => $httpCode];
        }
        $brief = dent_clean_text(trim(strip_tags($raw)), 120);
        $detail = $brief !== '' ? (' جزئیات: ' . $brief) : '';
        return ['success' => false, 'message' => 'پاسخ سرویس پیامکی نامعتبر است.' . $detail, 'httpStatus' => $httpCode];
    }

    $statusRaw = strtolower(trim((string) ($decoded['status'] ?? '')));
    $ok = $statusRaw === 'success';
    $messageCode = dent_clean_text((string) ($decoded['code'] ?? ''), 40);
    $message = '';
    $rawMessage = $decoded['messages'] ?? ($decoded['message'] ?? '');
    if (is_string($rawMessage)) {
        $message = dent_clean_text($rawMessage, 220);
    } elseif (is_array($rawMessage)) {
        foreach ($rawMessage as $messageItem) {
            if (is_string($messageItem)) {
                $message = dent_clean_text($messageItem, 220);
                if ($message !== '') {
                    break;
                }
                continue;
            }
            if (!is_array($messageItem)) {
                continue;
            }
            foreach ($messageItem as $nestedMessage) {
                if (!is_string($nestedMessage)) {
                    continue;
                }
                $message = dent_clean_text($nestedMessage, 220);
                if ($message !== '') {
                    break 2;
                }
            }
        }
    }
    if ($message === '') {
        $message = $ok ? 'ارسال انجام شد.' : 'ارسال پیامک انجام نشد.';
    }
    if (!$ok && $httpCode >= 500) {
        $message = 'سرویس پیامکی موقتاً در دسترس نیست.';
    }

    dent_sms_log($ok ? 'send_simple_ok' : 'send_simple_failed', [
        'httpStatus' => $httpCode,
        'messageCode' => $messageCode,
        'providerMessage' => $message,
        'recipientCount' => count($payload['recipients']),
    ]);

    return [
        'success' => $ok,
        'message' => $message,
        'httpStatus' => $httpCode,
        'messageCode' => $messageCode,
    ];
}

function dent_otp_ttl_seconds(): int
{
    return 180;
}

function dent_otp_cooldown_seconds(): int
{
    return 60;
}

function dent_otp_max_attempts(): int
{
    return 5;
}

function dent_otp_window_seconds(): int
{
    return 3600;
}

function dent_otp_max_send_per_window(): int
{
    return 6;
}

function dent_otp_record_key(string $purpose, string $phoneNumber): string
{
    return hash('sha256', $purpose . '|' . dent_normalize_phone_number($phoneNumber));
}

function dent_otp_cleanup_records(array &$metaStore): bool
{
    $changed = false;
    $now = time();
    $records = is_array($metaStore['otp']['records'] ?? null) ? $metaStore['otp']['records'] : [];
    foreach ($records as $key => $record) {
        if (!is_array($record)) {
            unset($records[$key]);
            $changed = true;
            continue;
        }
        $expiresAt = (int) ($record['expiresAt'] ?? 0);
        $consumedAt = (int) ($record['consumedAt'] ?? 0);
        $issuedAt = (int) ($record['issuedAt'] ?? 0);
        if ($expiresAt > 0 && $expiresAt + 86400 < $now) {
            unset($records[$key]);
            $changed = true;
            continue;
        }
        if ($consumedAt > 0 && $consumedAt + 86400 < $now) {
            unset($records[$key]);
            $changed = true;
            continue;
        }
        if ($issuedAt > 0 && $issuedAt + (2 * 86400) < $now) {
            unset($records[$key]);
            $changed = true;
            continue;
        }
    }

    if ($changed) {
        $metaStore['otp']['records'] = $records;
    }
    return $changed;
}

function dent_issue_otp_for_phone(string $purpose, string $phoneNumber, string $studentNumber = ''): array
{
    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    if ($normalizedPhone === '') {
        return ['success' => false, 'error' => 'شماره موبایل نامعتبر است.', 'statusCode' => 422];
    }

    $meta = dent_load_auth_meta_store();
    $metaChanged = dent_otp_cleanup_records($meta);
    $records = is_array($meta['otp']['records'] ?? null) ? $meta['otp']['records'] : [];
    $now = time();
    $key = dent_otp_record_key($purpose, $normalizedPhone);
    $record = is_array($records[$key] ?? null) ? $records[$key] : [];

    $cooldownUntil = (int) ($record['cooldownUntil'] ?? 0);
    if ($cooldownUntil > $now) {
        if ($metaChanged) {
            dent_save_auth_meta_store($meta);
        }
        return [
            'success' => false,
            'error' => 'برای دریافت مجدد کد کمی صبر کن.',
            'statusCode' => 429,
            'cooldownSeconds' => $cooldownUntil - $now,
        ];
    }

    $windowStart = (int) ($record['sendWindowStart'] ?? 0);
    $sendCount = (int) ($record['sendCount'] ?? 0);
    if ($windowStart <= 0 || ($now - $windowStart) > dent_otp_window_seconds()) {
        $windowStart = $now;
        $sendCount = 0;
    }
    if ($sendCount >= dent_otp_max_send_per_window()) {
        if ($metaChanged) {
            dent_save_auth_meta_store($meta);
        }
        return [
            'success' => false,
            'error' => 'تعداد درخواست‌های کد تایید بیش از حد مجاز است. بعداً دوباره تلاش کن.',
            'statusCode' => 429,
        ];
    }

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $salt = dent_base64url_encode(random_bytes(12));
    $codeHash = hash_hmac('sha256', $code, dent_auth_secret_key() . '|' . $salt);

    $records[$key] = array_merge($record, [
        'purpose' => $purpose,
        'phoneNumber' => $normalizedPhone,
        'studentNumber' => dent_normalize_student_number($studentNumber),
        'issuedAt' => $now,
        'cooldownUntil' => $now + dent_otp_cooldown_seconds(),
        'sendWindowStart' => $windowStart,
        'sendCount' => $sendCount + 1,
        'lastSendFailedAt' => 0,
    ]);
    $meta['otp']['records'] = $records;
    dent_save_auth_meta_store($meta);

    $sendResult = dent_sms_send_pattern($normalizedPhone, $code);
    if (!(bool) ($sendResult['success'] ?? false)) {
        $records[$key]['lastSendFailedAt'] = time();
        $records[$key]['lastSendFailureMessage'] = dent_clean_text((string) ($sendResult['message'] ?? ''), 220);
        $records[$key]['codeHash'] = '';
        $records[$key]['salt'] = '';
        $records[$key]['expiresAt'] = 0;
        $records[$key]['attempts'] = 0;
        $records[$key]['maxAttempts'] = dent_otp_max_attempts();
        $records[$key]['consumedAt'] = 0;
        $meta['otp']['records'] = $records;
        dent_save_auth_meta_store($meta);
        dent_sms_health_store_update(false, (string) ($sendResult['message'] ?? ''));
        return [
            'success' => false,
            'error' => (string) ($sendResult['message'] ?? 'ارسال کد تایید انجام نشد.'),
            'statusCode' => 502,
            'cooldownSeconds' => dent_otp_cooldown_seconds(),
        ];
    }

    $records[$key] = [
        'purpose' => $purpose,
        'phoneNumber' => $normalizedPhone,
        'studentNumber' => dent_normalize_student_number($studentNumber),
        'codeHash' => $codeHash,
        'salt' => $salt,
        'issuedAt' => $now,
        'expiresAt' => $now + dent_otp_ttl_seconds(),
        'cooldownUntil' => $now + dent_otp_cooldown_seconds(),
        'attempts' => 0,
        'maxAttempts' => dent_otp_max_attempts(),
        'consumedAt' => 0,
        'sendWindowStart' => $windowStart,
        'sendCount' => $sendCount + 1,
    ];
    $meta['otp']['records'] = $records;
    dent_save_auth_meta_store($meta);
    dent_sms_health_store_update(true, (string) ($sendResult['message'] ?? ''));

    return [
        'success' => true,
        'cooldownSeconds' => dent_otp_cooldown_seconds(),
        'expiresInSeconds' => dent_otp_ttl_seconds(),
        'phoneMasked' => dent_mask_phone_number($normalizedPhone),
    ];
}

function dent_verify_otp_for_phone(string $purpose, string $phoneNumber, string $code, string $studentNumber = ''): array
{
    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    if ($normalizedPhone === '') {
        return ['success' => false, 'error' => 'شماره موبایل نامعتبر است.', 'statusCode' => 422];
    }

    $normalizedCode = preg_replace('/\D+/u', '', dent_normalize_digits($code)) ?? '';
    if ($normalizedCode === '') {
        return ['success' => false, 'error' => 'کد تایید نامعتبر است.', 'statusCode' => 422];
    }

    $meta = dent_load_auth_meta_store();
    dent_otp_cleanup_records($meta);
    $records = is_array($meta['otp']['records'] ?? null) ? $meta['otp']['records'] : [];
    $key = dent_otp_record_key($purpose, $normalizedPhone);
    $record = is_array($records[$key] ?? null) ? $records[$key] : null;
    if ($record === null) {
        dent_save_auth_meta_store($meta);
        return ['success' => false, 'error' => 'درخواست کد تایید پیدا نشد یا منقضی شده است.', 'statusCode' => 404];
    }

    $now = time();
    $expiresAt = (int) ($record['expiresAt'] ?? 0);
    if ($expiresAt <= $now) {
        unset($records[$key]);
        $meta['otp']['records'] = $records;
        dent_save_auth_meta_store($meta);
        return ['success' => false, 'error' => 'کد تایید منقضی شده است.', 'statusCode' => 422];
    }

    $consumedAt = (int) ($record['consumedAt'] ?? 0);
    if ($consumedAt > 0) {
        return ['success' => false, 'error' => 'این کد قبلاً استفاده شده است.', 'statusCode' => 409];
    }

    $expectedStudent = dent_normalize_student_number((string) ($record['studentNumber'] ?? ''));
    $normalizedStudent = dent_normalize_student_number($studentNumber);
    if ($expectedStudent !== '' && $normalizedStudent !== '' && $expectedStudent !== $normalizedStudent) {
        return ['success' => false, 'error' => 'این کد برای کاربر دیگری صادر شده است.', 'statusCode' => 403];
    }

    $attempts = max(0, (int) ($record['attempts'] ?? 0));
    $maxAttempts = max(1, (int) ($record['maxAttempts'] ?? dent_otp_max_attempts()));
    $salt = (string) ($record['salt'] ?? '');
    $expectedHash = (string) ($record['codeHash'] ?? '');
    if ($expectedHash === '' || $salt === '') {
        return ['success' => false, 'error' => 'کد فعالی برای این شماره ثبت نشده است. دوباره درخواست ارسال بده.', 'statusCode' => 404];
    }
    $candidateHash = hash_hmac('sha256', $normalizedCode, dent_auth_secret_key() . '|' . $salt);
    if (!hash_equals($expectedHash, $candidateHash)) {
        $attempts++;
        if ($attempts >= $maxAttempts) {
            unset($records[$key]);
        } else {
            $record['attempts'] = $attempts;
            $records[$key] = $record;
        }
        $meta['otp']['records'] = $records;
        dent_save_auth_meta_store($meta);
        return [
            'success' => false,
            'error' => 'کد تایید صحیح نیست.',
            'statusCode' => 422,
            'remainingAttempts' => max(0, $maxAttempts - $attempts),
        ];
    }

    $record['consumedAt'] = $now;
    $records[$key] = $record;
    $meta['otp']['records'] = $records;
    dent_save_auth_meta_store($meta);

    return ['success' => true];
}

function dent_request_phone_enrollment_otp(array $user, string $phoneNumber): array
{
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($studentNumber === '') {
        dent_error('شناسه کاربر نامعتبر است.', 422);
    }

    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    if ($normalizedPhone === '') {
        dent_error('شماره موبایل نامعتبر است.', 422);
    }

    if (dent_phone_number_in_use($normalizedPhone, $studentNumber)) {
        dent_error('این شماره موبایل قبلاً روی حساب دیگری ثبت شده است.', 409);
    }

    $result = dent_issue_otp_for_phone('enroll:' . $studentNumber, $normalizedPhone, $studentNumber);
    if (!(bool) ($result['success'] ?? false)) {
        dent_error((string) ($result['error'] ?? 'ارسال کد تایید انجام نشد.'), (int) ($result['statusCode'] ?? 422), $result);
    }

    return $result;
}

function dent_request_external_signup_otp(string $firstName, string $lastName, string $phoneNumber, string $password, string $passwordConfirm = ''): array
{
    $input = dent_validate_external_signup_input($firstName, $lastName, $phoneNumber, $password, $passwordConfirm);
    $result = dent_issue_otp_for_phone('external-signup', (string) $input['phoneNumber'], (string) $input['username']);
    if (!(bool) ($result['success'] ?? false)) {
        dent_error((string) ($result['error'] ?? 'ارسال کد تایید انجام نشد.'), (int) ($result['statusCode'] ?? 422), $result);
    }

    return array_merge($result, [
        'phoneMasked' => dent_mask_phone_number((string) $input['phoneNumber']),
        'username' => (string) $input['username'],
    ]);
}

function dent_verify_external_signup_otp(string $firstName, string $lastName, string $phoneNumber, string $password, string $passwordConfirm, string $otpCode): array
{
    $input = dent_validate_external_signup_input($firstName, $lastName, $phoneNumber, $password, $passwordConfirm);
    $verify = dent_verify_otp_for_phone('external-signup', (string) $input['phoneNumber'], $otpCode, (string) $input['username']);
    if (!(bool) ($verify['success'] ?? false)) {
        dent_error((string) ($verify['error'] ?? 'تایید شماره موبایل انجام نشد.'), (int) ($verify['statusCode'] ?? 422), $verify);
    }

    $now = dent_iso_now();
    $user = dent_persist_user([
        'studentNumber' => (string) $input['username'],
        'name' => (string) $input['name'],
        'passwordHash' => dent_hash_password((string) $input['password']),
        'role' => 'external_exam_user',
        'cohortKey' => dent_external_site_users_cohort_key(),
        'profile' => dent_default_profile(),
        'phoneNumber' => (string) $input['phoneNumber'],
        'phoneVerifiedAt' => $now,
        'phoneLoginEnabled' => true,
        'phoneNudgeDismissedAt' => '',
        'rotationOverride' => ['mode' => 'none'],
        'createdAt' => $now,
        'updatedAt' => $now,
    ]);

    return dent_login_user($user);
}

function dent_verify_phone_enrollment_otp(array $user, string $phoneNumber, string $otpCode): array
{
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    if ($studentNumber === '' || $normalizedPhone === '') {
        dent_error('اطلاعات تایید شماره موبایل نامعتبر است.', 422);
    }
    if (dent_phone_number_in_use($normalizedPhone, $studentNumber)) {
        dent_error('این شماره موبایل قبلاً روی حساب دیگری ثبت شده است.', 409);
    }

    $verify = dent_verify_otp_for_phone('enroll:' . $studentNumber, $normalizedPhone, $otpCode, $studentNumber);
    if (!(bool) ($verify['success'] ?? false)) {
        dent_error((string) ($verify['error'] ?? 'تایید شماره موبایل انجام نشد.'), (int) ($verify['statusCode'] ?? 422), $verify);
    }

    $user['phoneNumber'] = $normalizedPhone;
    $user['phoneVerifiedAt'] = dent_iso_now();
    $user['phoneLoginEnabled'] = true;
    $user['phoneNudgeDismissedAt'] = '';
    return dent_persist_user($user);
}

function dent_dismiss_phone_nudge(array $user): array
{
    if (dent_normalize_phone_number((string) ($user['phoneNumber'] ?? '')) !== '') {
        return $user;
    }
    $user['phoneNudgeDismissedAt'] = dent_iso_now();
    return dent_persist_user($user);
}

function dent_set_phone_login_enabled(array $user, bool $enabled): array
{
    if (dent_normalize_phone_number((string) ($user['phoneNumber'] ?? '')) === '') {
        dent_error('برای فعال‌سازی ورود پیامکی، ابتدا شماره موبایل را ثبت و تایید کنید.', 422);
    }
    if (trim((string) ($user['phoneVerifiedAt'] ?? '')) === '') {
        dent_error('شماره موبایل هنوز تایید نشده است.', 422);
    }
    $user['phoneLoginEnabled'] = $enabled;
    return dent_persist_user($user);
}

function dent_remove_phone_number(array $user): array
{
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($studentNumber === '') {
        dent_error('شناسه کاربر نامعتبر است.', 422);
    }

    $currentPhone = dent_normalize_phone_number((string) ($user['phoneNumber'] ?? ''));
    if ($currentPhone === '') {
        $user['phoneNumber'] = '';
        $user['phoneVerifiedAt'] = '';
        $user['phoneLoginEnabled'] = false;
        return dent_persist_user($user);
    }

    $user['phoneNumber'] = '';
    $user['phoneVerifiedAt'] = '';
    $user['phoneLoginEnabled'] = false;
    $user['phoneNudgeDismissedAt'] = dent_iso_now();
    $updated = dent_persist_user($user);

    dent_clear_phone_related_otp_records($studentNumber, $currentPhone);
    return $updated;
}

function dent_request_login_otp(string $phoneNumber): array
{
    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    if ($normalizedPhone === '') {
        dent_error('شماره موبایل نامعتبر است.', 422);
    }

    $user = dent_find_login_otp_user_by_phone($normalizedPhone);
    if ($user === null) {
        dent_error('برای این شماره، ورود با کد تایید فعال نیست.', 403);
    }

    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    $result = dent_issue_otp_for_phone('login', $normalizedPhone, $studentNumber);
    if (!(bool) ($result['success'] ?? false)) {
        dent_error((string) ($result['error'] ?? 'ارسال کد تایید انجام نشد.'), (int) ($result['statusCode'] ?? 422), $result);
    }

    return array_merge($result, [
        'phoneMasked' => dent_mask_phone_number($normalizedPhone),
    ]);
}

function dent_verify_login_otp(string $phoneNumber, string $otpCode): array
{
    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    if ($normalizedPhone === '') {
        dent_error('شماره موبایل نامعتبر است.', 422);
    }

    $user = dent_find_login_otp_user_by_phone($normalizedPhone);
    if ($user === null) {
        dent_error('برای این شماره، ورود با کد تایید فعال نیست.', 403);
    }

    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    $verify = dent_verify_otp_for_phone('login', $normalizedPhone, $otpCode, $studentNumber);
    if (!(bool) ($verify['success'] ?? false)) {
        dent_error((string) ($verify['error'] ?? 'تایید کد انجام نشد.'), (int) ($verify['statusCode'] ?? 422), $verify);
    }

    if (!dent_user_phone_ready_for_otp($user) || dent_normalize_phone_number((string) ($user['phoneNumber'] ?? '')) !== $normalizedPhone) {
        $user['phoneNumber'] = $normalizedPhone;
        $user['phoneVerifiedAt'] = dent_iso_now();
        $user['phoneLoginEnabled'] = true;
        $user['phoneNudgeDismissedAt'] = '';
        unset($user['_otpPhoneFallback'], $user['_otpPhoneFallbackSource']);
        $user = dent_persist_user($user);
    }

    return dent_login_user($user);
}

function dent_request_password_reset_otp(string $phoneNumber): array
{
    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    if ($normalizedPhone === '') {
        dent_error('شماره موبایل نامعتبر است.', 422);
    }

    $user = dent_find_login_otp_user_by_phone($normalizedPhone);
    if ($user === null) {
        dent_error('برای این شماره، بازیابی رمز با کد تایید فعال نیست.', 403);
    }

    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    $result = dent_issue_otp_for_phone('password-reset', $normalizedPhone, $studentNumber);
    if (!(bool) ($result['success'] ?? false)) {
        dent_error((string) ($result['error'] ?? 'ارسال کد بازیابی انجام نشد.'), (int) ($result['statusCode'] ?? 422), $result);
    }

    return array_merge($result, [
        'phoneMasked' => dent_mask_phone_number($normalizedPhone),
    ]);
}

function dent_verify_password_reset_otp(string $phoneNumber, string $otpCode, string $newPassword, string $confirmPassword = ''): array
{
    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    if ($normalizedPhone === '') {
        dent_error('شماره موبایل نامعتبر است.', 422);
    }

    $user = dent_find_login_otp_user_by_phone($normalizedPhone);
    if ($user === null) {
        dent_error('برای این شماره، بازیابی رمز با کد تایید فعال نیست.', 403);
    }

    $newPassword = dent_normalize_digits($newPassword);
    $confirmPassword = dent_normalize_digits($confirmPassword);
    if (dent_utf8_strlen($newPassword) < 6) {
        dent_error('رمز جدید باید حداقل ۶ کاراکتر باشد.', 422);
    }
    if ($confirmPassword === '') {
        dent_error('تکرار رمز جدید را وارد کن.', 422);
    }
    if (!hash_equals($newPassword, $confirmPassword)) {
        dent_error('تکرار رمز جدید با رمز جدید یکسان نیست.', 422);
    }

    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    $verify = dent_verify_otp_for_phone('password-reset', $normalizedPhone, $otpCode, $studentNumber);
    if (!(bool) ($verify['success'] ?? false)) {
        dent_error((string) ($verify['error'] ?? 'تایید کد بازیابی انجام نشد.'), (int) ($verify['statusCode'] ?? 422), $verify);
    }

    if (!dent_user_phone_ready_for_otp($user) || dent_normalize_phone_number((string) ($user['phoneNumber'] ?? '')) !== $normalizedPhone) {
        $user['phoneNumber'] = $normalizedPhone;
        $user['phoneVerifiedAt'] = dent_iso_now();
        $user['phoneLoginEnabled'] = true;
        $user['phoneNudgeDismissedAt'] = '';
    }
    unset($user['_otpPhoneFallback'], $user['_otpPhoneFallbackSource']);
    $user['passwordHash'] = dent_hash_password($newPassword);
    $user['updatedAt'] = dent_iso_now();
    $user = dent_persist_user($user);

    return dent_login_user($user);
}

function dent_clear_phone_related_otp_records(string $studentNumber, string $phoneNumber): void
{
    $normalizedStudentNumber = dent_normalize_student_number($studentNumber);
    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    if ($normalizedStudentNumber === '' || $normalizedPhone === '') {
        return;
    }

    $meta = dent_load_auth_meta_store();
    if (!is_array($meta['otp'] ?? null)) {
        return;
    }
    $records = is_array($meta['otp']['records'] ?? null) ? $meta['otp']['records'] : [];
    if (!$records) {
        return;
    }

    $keys = [
        dent_otp_record_key('login', $normalizedPhone),
        dent_otp_record_key('enroll:' . $normalizedStudentNumber, $normalizedPhone),
        dent_otp_record_key('password-reset', $normalizedPhone),
    ];
    $changed = false;
    foreach ($keys as $key) {
        if (!isset($records[$key])) {
            continue;
        }
        unset($records[$key]);
        $changed = true;
    }

    if (!$changed) {
        return;
    }

    $meta['otp']['records'] = $records;
    dent_save_auth_meta_store($meta);
}

function dent_sms_health_check(?string $phoneNumber = null): array
{
    $status = dent_sms_status_payload();
    if ($phoneNumber === null || trim($phoneNumber) === '') {
        return [
            'success' => $status['enabled'] && $status['apiKeyConfigured'] && $status['patternConfigured'],
            'message' => ($status['enabled'] && $status['apiKeyConfigured'] && $status['patternConfigured'])
                ? 'تنظیمات پیامکی کامل است.'
                : 'تنظیمات پیامکی کامل نیست.',
            'status' => $status,
        ];
    }

    $normalizedPhone = dent_normalize_phone_number($phoneNumber);
    if ($normalizedPhone === '') {
        dent_error('شماره موبایل تست نامعتبر است.', 422);
    }

    $testCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $sendResult = dent_sms_send_pattern($normalizedPhone, $testCode);
    dent_sms_health_store_update((bool) ($sendResult['success'] ?? false), (string) ($sendResult['message'] ?? ''));
    return [
        'success' => (bool) ($sendResult['success'] ?? false),
        'message' => (string) ($sendResult['message'] ?? ''),
        'status' => dent_sms_status_payload(),
    ];
}
