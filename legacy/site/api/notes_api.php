<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';
require_once __DIR__ . '/dentistry_curriculum.php';
require_once __DIR__ . '/notes_download_host.php';

const NOTES_1402_SCHEMA_VERSION = 1;
const NOTES_1402_MIN_TERM = 4;
const NOTES_1402_MAX_TERM = 12;
const NOTES_1402_SEED_BACKFILL_VERSION = 0;
const NOTES_1403_SCHEMA_VERSION = 1;
const NOTES_1403_MIN_TERM = 3;
const NOTES_1403_MAX_TERM = 12;
const NOTES_1404_SCHEMA_VERSION = 1;
const NOTES_1404_MIN_TERM = 1;
const NOTES_1404_MAX_TERM = 12;
const NOTES_PROSTHESIS_1402_SCHEMA_VERSION = 1;
const NOTES_DIRECT_UPLOAD_SCHEMA_VERSION = 1;
const NOTES_DIRECT_UPLOAD_SESSION_TTL_SECONDS = 14400;
const NOTES_DIRECT_UPLOAD_COMPLETED_TTL_SECONDS = 172800;
const NOTES_RESOURCE_RECENT_LIMIT = 40;
const NOTES_RESOURCE_HISTORY_LIMIT = 12;
const NOTES_RESOURCE_INSIGHT_LIMIT = 8;

function notes_1402_store_path(): string
{
    return dent_storage_path('notes/1402_terms.json');
}

function notes_1402_lock_path(): string
{
    return dent_storage_path('notes/1402_terms.lock');
}

function notes_1403_store_path(): string
{
    return dent_storage_path('notes/1403_terms.json');
}

function notes_1403_lock_path(): string
{
    return dent_storage_path('notes/1403_terms.lock');
}

function notes_1403_legacy_store_path(): string
{
    return dent_storage_path('notes/1403_archive.json');
}

function notes_1403_legacy_lock_path(): string
{
    return dent_storage_path('notes/1403_archive.lock');
}

function notes_1404_store_path(): string
{
    return dent_storage_path('notes/1404_terms.json');
}

function notes_1404_lock_path(): string
{
    return dent_storage_path('notes/1404_terms.lock');
}

function notes_prosthesis_1402_store_path(): string
{
    return dent_storage_path('notes/prosthesis_1402_terms.json');
}

function notes_prosthesis_1402_lock_path(): string
{
    return dent_storage_path('notes/prosthesis_1402_terms.lock');
}

function notes_direct_upload_store_path(): string
{
    return dent_storage_path('notes/direct_upload_sessions.json');
}

function notes_direct_upload_lock_path(): string
{
    return dent_storage_path('notes/direct_upload_sessions.lock');
}

function notes_direct_upload_default_store(): array
{
    return [
        'schemaVersion' => NOTES_DIRECT_UPLOAD_SCHEMA_VERSION,
        'sessions' => [],
    ];
}

function notes_direct_upload_clean_token($value): string
{
    $token = trim(strtolower((string) $value));
    return preg_match('/^[a-z0-9_-]{16,200}$/', $token) === 1 ? $token : '';
}

function notes_direct_upload_next_token(int $bytes = 18): string
{
    try {
        return strtolower(dent_base64url_encode(random_bytes($bytes)));
    } catch (Throwable $error) {
        return strtolower(hash('sha256', microtime(true) . '|' . mt_rand() . '|' . uniqid('', true)));
    }
}

function notes_direct_upload_clean_status($value): string
{
    $status = trim(strtolower((string) $value));
    return in_array($status, ['prepared', 'resolved', 'completed'], true) ? $status : 'prepared';
}

function notes_direct_upload_ensure_storage(): void
{
    dent_ensure_directory(dirname(notes_direct_upload_store_path()));
    if (!is_file(notes_direct_upload_store_path())) {
        dent_write_json_file(notes_direct_upload_store_path(), notes_direct_upload_default_store());
    }
}

function notes_direct_upload_normalize_store(array $store): array
{
    $now = time();
    $sessions = [];
    foreach (($store['sessions'] ?? []) as $key => $value) {
        if (!is_array($value)) {
            continue;
        }

        $token = notes_direct_upload_clean_token($value['token'] ?? $key);
        if ($token === '') {
            continue;
        }

        $status = notes_direct_upload_clean_status($value['status'] ?? 'prepared');
        $createdAt = trim((string) ($value['createdAt'] ?? ''));
        $updatedAt = trim((string) ($value['updatedAt'] ?? $createdAt));
        $expiresAt = trim((string) ($value['expiresAt'] ?? ''));
        $expiresUnix = $expiresAt !== '' ? (int) strtotime($expiresAt) : 0;
        $updatedUnix = $updatedAt !== '' ? (int) strtotime($updatedAt) : 0;

        if ($expiresUnix > 0 && $expiresUnix < ($now - 300)) {
            continue;
        }
        if ($status === 'completed' && $updatedUnix > 0 && ($now - $updatedUnix) > NOTES_DIRECT_UPLOAD_COMPLETED_TTL_SECONDS) {
            continue;
        }

        $sessions[$token] = [
            'token' => $token,
            'status' => $status,
            'cohort' => trim((string) ($value['cohort'] ?? '1402')),
            'scopeRoot' => trim((string) ($value['scopeRoot'] ?? '')),
            'relativeDir' => trim((string) ($value['relativeDir'] ?? '')),
            'relativePath' => trim((string) ($value['relativePath'] ?? '')),
            'finalName' => trim((string) ($value['finalName'] ?? '')),
            'mimeType' => trim((string) ($value['mimeType'] ?? '')),
            'expectedSize' => max(0, (int) ($value['expectedSize'] ?? 0)),
            'createdAt' => $createdAt !== '' ? $createdAt : dent_iso_now(),
            'updatedAt' => $updatedAt !== '' ? $updatedAt : dent_iso_now(),
            'expiresAt' => $expiresAt !== '' ? $expiresAt : date('c', $now + NOTES_DIRECT_UPLOAD_SESSION_TTL_SECONDS),
            'sessionKey' => notes_direct_upload_clean_token($value['sessionKey'] ?? ''),
            'origin' => trim((string) ($value['origin'] ?? '')),
            'gatewayVersion' => trim((string) ($value['gatewayVersion'] ?? '')),
            'contentType' => trim((string) ($value['contentType'] ?? '')),
            'contentLength' => max(0, (int) ($value['contentLength'] ?? 0)),
            'completedAt' => trim((string) ($value['completedAt'] ?? '')),
            'file' => is_array($value['file'] ?? null) ? $value['file'] : null,
        ];
    }

    return [
        'schemaVersion' => NOTES_DIRECT_UPLOAD_SCHEMA_VERSION,
        'sessions' => $sessions,
    ];
}

function notes_direct_upload_load_store_unlocked(): array
{
    notes_direct_upload_ensure_storage();
    $raw = dent_read_json_file(notes_direct_upload_store_path(), notes_direct_upload_default_store());
    if (!isset($raw['sessions']) || !is_array($raw['sessions'])) {
        throw new DentJsonPersistenceException(
            'NOTES_DIRECT_UPLOAD_STORE_SCHEMA_INVALID',
            'Existing direct-upload session store has an invalid schema'
        );
    }

    return notes_direct_upload_normalize_store($raw);
}

/**
 * @template T
 * @param callable(array):T $callback
 * @return T
 */
function notes_direct_upload_with_store_lock(callable $callback)
{
    notes_direct_upload_ensure_storage();
    $lock = fopen(notes_direct_upload_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('قفل آپلود مستقیم منابع در دسترس نیست.', 500);
    }

    try {
        if (!flock($lock, LOCK_EX)) {
            dent_error('قفل آپلود مستقیم منابع آماده نشد.', 500);
        }
        $store = notes_direct_upload_load_store_unlocked();
        $result = $callback($store);
        dent_write_json_file(notes_direct_upload_store_path(), notes_direct_upload_normalize_store($store));
        return $result;
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function notes_direct_upload_reserved_names_for_dir(array $store, string $relativeDir): array
{
    $reserved = [];
    foreach (($store['sessions'] ?? []) as $session) {
        if (!is_array($session)) {
            continue;
        }
        if ((string) ($session['status'] ?? '') === 'completed') {
            continue;
        }
        if (trim((string) ($session['relativeDir'] ?? '')) !== $relativeDir) {
            continue;
        }
        $name = trim((string) ($session['finalName'] ?? ''));
        if ($name === '') {
            continue;
        }
        $reserved[] = $name;
    }

    return $reserved;
}

function notes_direct_upload_limit_bytes(array $gateway): ?int
{
    $limits = [];
    foreach (['postMaxBytes', 'uploadMaxBytes'] as $key) {
        $value = $gateway[$key] ?? null;
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            $bytes = max(0, (int) round((float) $value));
            if ($bytes > 0) {
                $limits[] = $bytes;
            }
        }
    }

    if ($limits === []) {
        return null;
    }

    return min($limits);
}

function notes_direct_upload_build_file_payload(array $session, ?int $sizeBytes = null, ?string $mimeType = null): array
{
    $bytes = $sizeBytes !== null ? max(0, $sizeBytes) : max(0, (int) ($session['expectedSize'] ?? 0));
    $finalMimeType = trim((string) ($mimeType !== null ? $mimeType : ($session['mimeType'] ?? '')));
    $relativeDir = trim((string) ($session['relativeDir'] ?? ''));
    $relativePath = trim((string) ($session['relativePath'] ?? ''));
    $finalName = trim((string) ($session['finalName'] ?? basename($relativePath)));

    return [
        'name' => $finalName,
        'relativeDir' => $relativeDir,
        'relativePath' => $relativePath,
        'sizeBytes' => $bytes,
        'sizeLabel' => notes_download_host_human_size($bytes),
        'mimeType' => $finalMimeType,
        'publicUrl' => notes_download_host_public_url($relativePath),
        'message' => 'فایل روی هاست دانلود ذخیره شد.',
    ];
}

function notes_build_host_upload_url(string $relativeDir, string $cohort): string
{
    $query = [
        'action' => 'hostUploadFile',
        'path' => $relativeDir,
    ];
    if ($cohort !== '') {
        $query['cohort'] = $cohort;
    }

    return '/api/notes_api.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

function notes_direct_upload_main_site_origin(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return '';
    }

    $parsedHost = parse_url('http://' . $host, PHP_URL_HOST);
    $hostname = strtolower(trim((string) $parsedHost));
    if ($hostname === '' || in_array($hostname, ['localhost', '127.0.0.1', '::1'], true)) {
        return '';
    }
    if (str_ends_with($hostname, '.local') || str_ends_with($hostname, '.test') || str_ends_with($hostname, '.invalid') || str_ends_with($hostname, '.localhost')) {
        return '';
    }
    if (filter_var($hostname, FILTER_VALIDATE_IP) && filter_var($hostname, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return '';
    }
    if (!filter_var($hostname, FILTER_VALIDATE_IP) && !str_contains($hostname, '.')) {
        return '';
    }

    $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    $forwardedSsl = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')));
    $secure = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || $forwardedProto === 'https'
        || $forwardedSsl === 'on';

    return ($secure ? 'https://' : 'http://') . $host;
}

function notes_download_host_target_context(string $cohort, array $viewer, array $params): array
{
    $scopeRoot = notes_download_host_scope_for_viewer($cohort, $viewer);
    $relativeDir = trim((string) ($params['path'] ?? $params['relativeDir'] ?? ''));
    if ($relativeDir === '') {
        $term = $cohort === 'prosthesis-1402'
            ? notes_prosthesis_1402_parse_term_id($params['term'] ?? '1')
            : notes_require_term_for_cohort($cohort, $params['term'] ?? '');
        $store = notes_curriculum_store_for_cohort($cohort);
        $relativeDir = notes_download_host_default_relative_dir_from_request($cohort, $term, $params, $store);
    }

    return [
        'scopeRoot' => $scopeRoot,
        'relativeDir' => notes_download_host_assert_allowed_relative_path($relativeDir, $scopeRoot, false),
    ];
}

function notes_prepare_host_upload_plan(string $cohort, array $viewer, array $params): array
{
    $target = notes_download_host_target_context($cohort, $viewer, $params);
    $expectedSize = max(0, (int) ($params['fileSize'] ?? 0));
    if ($expectedSize <= 0) {
        dent_error('حجم فایل برای آپلود معتبر نیست.', 422);
    }

    $desiredName = trim((string) ($params['fileName'] ?? ''));
    if ($desiredName === '') {
        dent_error('نام فایل برای آپلود معتبر نیست.', 422);
    }

    $mimeType = trim((string) ($params['mimeType'] ?? ''));

    // Create only the destination directory. File bytes are sent later as
    // bounded raw-body chunks and streamed over FTP into a token-scoped partial
    // file on the download host. No complete-file staging is created here or on
    // the main host.
    notes_download_host_ensure_dir($target['relativeDir'], $target['scopeRoot']);
    $mainSiteOrigin = notes_direct_upload_main_site_origin();
    if ($mainSiteOrigin !== '') {
        notes_download_host_ensure_direct_upload_gateway($mainSiteOrigin);
    }

    return notes_direct_upload_with_store_lock(static function (array &$store) use ($cohort, $target, $desiredName, $mimeType, $expectedSize): array {
        $finalName = notes_download_host_unique_file_name_with_reserved(
            $target['relativeDir'],
            $desiredName,
            notes_direct_upload_reserved_names_for_dir($store, $target['relativeDir'])
        );
        $relativePath = trim($target['relativeDir'] . '/' . $finalName, '/');
        $token = notes_direct_upload_next_token();
        $sessionKey = notes_direct_upload_next_token();
        $now = dent_iso_now();

        $session = [
            'token' => $token,
            'status' => 'prepared',
            'cohort' => $cohort,
            'scopeRoot' => (string) ($target['scopeRoot'] ?? ''),
            'relativeDir' => $target['relativeDir'],
            'relativePath' => $relativePath,
            'finalName' => $finalName,
            'mimeType' => $mimeType,
            'expectedSize' => $expectedSize,
            'createdAt' => $now,
            'updatedAt' => $now,
            'expiresAt' => date('c', time() + NOTES_DIRECT_UPLOAD_SESSION_TTL_SECONDS),
            'sessionKey' => $sessionKey,
            'origin' => notes_direct_upload_main_site_origin(),
            'gatewayVersion' => 'main-ftp-stream-v1',
            'contentType' => '',
            'contentLength' => 0,
            'completedAt' => '',
            'file' => null,
        ];
        $store['sessions'][$token] = $session;

        return [
            'mode' => 'stream',
            'url' => '/api/notes_api.php?action=streamHostUploadChunk&cohort=' . rawurlencode($cohort) . '&token=' . rawurlencode($token),
            'relativeDir' => $target['relativeDir'],
            'relativePath' => $relativePath,
            'fileName' => $finalName,
            'scopeRoot' => $target['scopeRoot'],
            'transport' => 'raw-chunk-to-ftp',
            'chunkBytes' => 4 * 1024 * 1024,
        ];
    });
}

function notes_1402_term_template(int $term): array
{
    if ($term === 5) {
        return [
            'term' => 5,
            'kicker' => 'ترم ۵',
            'title' => 'فایل‌های فعلی آرشیو',
            'description' => 'همه منابعی که قبلاً در صفحه جزوات ۱۴۰۲ بودند، فعلاً در این ترم قرار گرفته‌اند.',
            'emptyMessage' => 'برای ترم ۵ هنوز منبعی ثبت نشده است.',
        ];
    }

    return [
        'term' => $term,
        'kicker' => 'ترم ' . dent_to_fa_digits((string) $term),
        'title' => 'آرشیو منابع ترم ' . dent_to_fa_digits((string) $term),
        'description' => 'منابع این ترم به‌مرور اضافه می‌شوند.',
        'emptyMessage' => 'منابع ترم ' . dent_to_fa_digits((string) $term) . ' هنوز ثبت نشده است.',
    ];
}

function notes_1402_seed_term_5_items(): array
{
    return [
        [
            'id' => 1,
            'badge' => 'برنامه',
            'title' => 'برنامه امتحانات پایان‌ترم',
            'description' => 'برنامه پایان‌ترم.',
            'buttonLabel' => 'دیدن',
            'buttonUrl' => 'https://dentistry.tums.ac.ir/uploads/351/2026/Jan/20/%D8%A8%D8%B1%D9%86%D8%A7%D9%85%D9%87%20%D8%A7%D9%85%D8%AA%D8%AD%D8%A7%D9%86%D8%A7%D8%AA%20%D9%BE%D8%A7%DB%8C%D8%A7%D9%86%20%D8%AA%D8%B1%D9%85%20%D8%AF%DA%A9%D8%AA%D8%B1%D8%A7%20%D9%86%DB%8C%D9%85%D8%B3%D8%A7%D9%84%20%D8%A7%D9%88%D9%84%201404-1405_1.jpg',
        ],
        [
            'id' => 2,
            'badge' => 'سیستمیک',
            'title' => 'جزوات بیماری‌های سیستمیک',
            'description' => 'جلسه‌های نهم تا هفدهم.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s5.uupload.ir/files/arianghsm/Systemicdiseases.zip',
        ],
        [
            'id' => 3,
            'badge' => 'ترمیمی',
            'title' => 'بارم‌بندی ترمیمی',
            'description' => 'بارم‌بندی و تعداد سؤال‌ها.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s31.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201402/_lrm_⁨بارم%20بندی%20پایان%20ترم%20مبانی%20ترمیمی⁩.pdf',
        ],
        [
            'id' => 4,
            'badge' => 'ترمیمی',
            'title' => 'جزوات ترمیمی',
            'description' => 'جلسه‌های ۱، ۲، ۴، ۵، ۷، ۸، ۹، ۱۰، ۱۱، ۱۲ و ۱۴ به‌همراه مواد دندانی.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s15.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201402/Restorative%20Dentistry.zip',
        ],
        [
            'id' => 5,
            'badge' => 'جراحی',
            'title' => 'جزوات جراحی نظری ۱',
            'description' => 'همه جلسه‌ها به‌جز جلسه پنجم.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s15.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201402/Surgery%20(-%205).zip',
        ],
        [
            'id' => 6,
            'badge' => 'رادیولوژی',
            'title' => 'جزوات رادیولوژی نظری ۲',
            'description' => 'جلسه‌های اول تا هفتم.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s15.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201402/Radiology%20(1-7).zip',
        ],
        [
            'id' => 7,
            'badge' => 'فارماکولوژی',
            'title' => 'جزوات فارماکولوژی',
            'description' => 'جلسه‌های ۷، ۹، ۱۰، ۱۱ و ۱۶.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s5.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201402/Pharmacology.zip',
        ],
        [
            'id' => 8,
            'badge' => 'اخلاق',
            'title' => 'جزوات اخلاق پزشکی',
            'description' => 'جلسه‌های ۱، ۴، ۵، ۷، ۹، ۱۱، ۱۵ و ۱۶.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s15.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201402/Medical%20Ethics%20(1-4-5-7-9-11-15-16).zip',
        ],
        [
            'id' => 9,
            'badge' => 'جراحی',
            'title' => 'کتاب CDR جراحی نظری ۱',
            'description' => 'فایل کامل کتاب.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s15.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201402/پیترسون%20CDR%202019.pdf',
        ],
        [
            'id' => 10,
            'badge' => 'جراحی',
            'title' => 'رفرنس فارسی جراحی نظری ۱',
            'description' => 'فصل‌های ۴، ۷، ۸، ۹، ۱۱، ۱۶ و ۱۷.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s31.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201402/رفرنس%20جراحی%20پیترسون.zip',
        ],
        [
            'id' => 11,
            'badge' => 'پارسیل',
            'title' => 'کتاب گام‌به‌گام با پروتز پارسیل',
            'description' => 'فایل کامل کتاب.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s5.uupload.ir/files/arianghsm/_lrm_⁨گام%20به%20گام%20با%20پروتز%20پارسیل⁩.pdf',
        ],
    ];
}

function notes_1403_archive_template(): array
{
    return [
        'kicker' => 'ورودی ۱۴۰۳',
        'title' => 'فایل‌های موجود',
        'description' => 'کارت‌های منابع این آرشیو از پنل مالک مدیریت می‌شوند.',
        'emptyMessage' => 'برای آرشیو ۱۴۰۳ هنوز منبعی ثبت نشده است.',
    ];
}

function notes_1403_seed_items(): array
{
    return [
        [
            'id' => 1,
            'badge' => 'برنامه',
            'title' => 'برنامه امتحانات پایان‌ترم',
            'description' => 'برنامه پایان‌ترم.',
            'buttonLabel' => 'دیدن',
            'buttonUrl' => 'https://my.uupload.ir/dl/EOwg2LrM',
        ],
        [
            'id' => 2,
            'badge' => 'نورواناتومی',
            'title' => 'جزوه جامع نورواناتومی',
            'description' => 'فایل کامل.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s21.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201403/جزوه%20جامع%20نوروآناتومی.pdf',
        ],
        [
            'id' => 3,
            'badge' => 'ویروس',
            'title' => 'جزوه جامع ویروس‌شناسی پایان‌ترم',
            'description' => 'جزوه پایان‌ترم.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s21.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201403/جزوه%20جامع%20ویروس_شناسی%20پایانترم.pdf',
        ],
        [
            'id' => 4,
            'badge' => 'ژنتیک',
            'title' => 'جزوه جامع ژنتیک ۱ تا ۸',
            'description' => 'جلسه‌های ۱ تا ۸.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s21.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201403/جزوه%20جامع%20ژنتیک%20۱%20تا%20۸.pdf',
        ],
        [
            'id' => 5,
            'badge' => 'فیزیک پزشکی',
            'title' => 'جزوه جامع فیزیک پزشکی',
            'description' => 'به‌جز جلسه ۴.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s21.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201403/جزوه%20جامع%20فیزیک%20پزشکی%20بجز%20۴.pdf',
        ],
        [
            'id' => 6,
            'badge' => 'متون',
            'title' => 'تفسیر موضوعی قرآن کریم',
            'description' => 'فایل کامل کتاب.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s21.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201403/تفسیر_موضوعی_قرآن_کریم_محمدعلی_رضایی_اصفهانی.pdf',
        ],
        [
            'id' => 7,
            'badge' => 'انقلاب',
            'title' => 'کتاب صعود چهل‌ساله',
            'description' => 'فایل کامل کتاب.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s21.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201403/صعود%20چهل%20ساله%20۲.pdf',
        ],
        [
            'id' => 8,
            'badge' => 'زبان',
            'title' => 'مجموعه فایل‌های زبان عمومی',
            'description' => 'فایل‌های درس.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s31.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201403/زبان%20عمومی/زبان%20عمومی.zip',
        ],
        [
            'id' => 9,
            'badge' => 'متون',
            'title' => 'نمونه سؤال متون',
            'description' => 'فایل نمونه سؤال.',
            'buttonLabel' => 'دریافت',
            'buttonUrl' => 'https://s31.uupload.ir/files/arianghsm/جزوات%20دندانپزشکی%201403/خلاصه%20و%20نمونه%20سوال%20متون/نمونه%20سوال%20متون.pdf',
        ],
    ];
}

function notes_fixed_terms_term_template(int $term, string $cohortLabel, int $startingTerm): array
{
    $termFa = dent_to_fa_digits((string) $term);
    if ($term === $startingTerm) {
        return [
            'term' => $term,
            'kicker' => 'ترم ' . $termFa,
            'title' => 'فایل‌های فعلی آرشیو',
            'description' => 'همه منابع فعلی ' . $cohortLabel . ' فعلاً در این ترم قرار گرفته‌اند.',
            'emptyMessage' => 'برای ترم ' . $termFa . ' هنوز منبعی ثبت نشده است.',
        ];
    }

    return [
        'term' => $term,
        'kicker' => 'ترم ' . $termFa,
        'title' => 'آرشیو منابع ترم ' . $termFa,
        'description' => 'منابع این ترم به‌مرور اضافه می‌شوند.',
        'emptyMessage' => 'منابع ترم ' . $termFa . ' هنوز ثبت نشده است.',
    ];
}

function notes_1403_term_template(int $term): array
{
    return notes_fixed_terms_term_template($term, 'ورودی ۱۴۰۳', NOTES_1403_MIN_TERM);
}

function notes_1404_term_template(int $term): array
{
    return notes_fixed_terms_term_template($term, 'ورودی ۱۴۰۴', NOTES_1404_MIN_TERM);
}

/**
 * @param array<int, array<int, mixed>> $seedItemsByTerm
 */
function notes_fixed_terms_default_store(
    int $schemaVersion,
    int $minTerm,
    int $maxTerm,
    callable $templateFn,
    array $seedItemsByTerm = []
): array {
    $terms = [];
    $maxItemId = 0;
    for ($term = $minTerm; $term <= $maxTerm; $term++) {
        $template = $templateFn($term);
        $items = is_array($seedItemsByTerm[$term] ?? null) ? $seedItemsByTerm[$term] : [];
        $template['items'] = $items;
        foreach ($items as $itemSeed) {
            if (!is_array($itemSeed)) {
                continue;
            }
            $maxItemId = max($maxItemId, (int) ($itemSeed['id'] ?? 0));
        }
        $terms[(string) $term] = $template;
    }

    return array_merge([
        'schemaVersion' => $schemaVersion,
        'nextItemId' => max(1, $maxItemId + 1),
        'terms' => $terms,
    ], notes_resource_meta_defaults());
}

function notes_1402_item_signature(array $item): string
{
    $title = dent_utf8_strtolower(trim((string) ($item['title'] ?? '')));
    $buttonUrl = trim((string) ($item['buttonUrl'] ?? ''));
    return $title . '|' . $buttonUrl;
}

function notes_1402_item_signature_from_seed(array $item): string
{
    $normalized = notes_1402_normalize_item_record($item);
    if (is_array($normalized)) {
        return notes_1402_item_signature($normalized);
    }

    $title = dent_utf8_strtolower(trim((string) ($item['title'] ?? '')));
    $buttonUrl = trim((string) ($item['buttonUrl'] ?? ''));
    return $title . '|' . $buttonUrl;
}

function notes_repair_fa_digit_mojibake(string $value): string
{
    static $map = null;
    if (!is_array($map)) {
        $map = [
            hex2bin('c39bc2b0') => '۰',
            hex2bin('c39bc2b1') => '۱',
            hex2bin('c39bc2b2') => '۲',
            hex2bin('c39bc2b3') => '۳',
            hex2bin('c39bc2b4') => '۴',
            hex2bin('c39bc2b5') => '۵',
            hex2bin('c39bc2b6') => '۶',
            hex2bin('c39bc2b7') => '۷',
            hex2bin('c39bc2b8') => '۸',
            hex2bin('c39bc2b9') => '۹',
        ];
    }

    return strtr($value, $map);
}

function notes_1402_needs_term_5_seed_backfill(array $seed): bool
{
    $backfillVersion = (int) ($seed['seedBackfillVersion'] ?? 0);
    if ($backfillVersion >= NOTES_1402_SEED_BACKFILL_VERSION) {
        return false;
    }

    $term5 = is_array($seed['terms']['5'] ?? null) ? $seed['terms']['5'] : [];
    $items = is_array($term5['items'] ?? null) ? $term5['items'] : [];
    if (count($items) === 0 || count($items) >= count(notes_1402_seed_term_5_items())) {
        return false;
    }

    // Legacy bad migration left term 5 with only a couple of seed items.
    // Keep backfill conservative so owner-managed states are not overridden.
    if (count($items) > 3) {
        return false;
    }

    $seedSignatures = [];
    foreach (notes_1402_seed_term_5_items() as $seedItem) {
        $normalized = notes_1402_normalize_item_record($seedItem);
        if ($normalized === null) {
            continue;
        }
        $seedSignatures[notes_1402_item_signature($normalized)] = true;
    }

    foreach ($items as $item) {
        if (!is_array($item)) {
            return false;
        }

        $normalized = notes_1402_normalize_item_record($item);
        if ($normalized === null) {
            return false;
        }

        if (!isset($seedSignatures[notes_1402_item_signature($normalized)])) {
            return false;
        }
    }

    return true;
}

function notes_1402_apply_term_5_seed_backfill(array $seed): array
{
    if (!notes_1402_needs_term_5_seed_backfill($seed)) {
        return $seed;
    }

    if (!isset($seed['terms']) || !is_array($seed['terms'])) {
        $seed['terms'] = [];
    }
    if (!isset($seed['terms']['5']) || !is_array($seed['terms']['5'])) {
        $seed['terms']['5'] = notes_1402_term_template(5);
    }

    $term5 = $seed['terms']['5'];
    $currentItems = is_array($term5['items'] ?? null) ? $term5['items'] : [];
    $mergedItems = [];
    $knownSignatures = [];
    $maxId = 0;

    foreach ($currentItems as $itemSeed) {
        if (!is_array($itemSeed)) {
            continue;
        }
        $normalized = notes_1402_normalize_item_record($itemSeed);
        if ($normalized === null) {
            continue;
        }

        $signature = notes_1402_item_signature($normalized);
        if (isset($knownSignatures[$signature])) {
            continue;
        }
        $knownSignatures[$signature] = true;
        $maxId = max($maxId, (int) ($normalized['id'] ?? 0));
        $mergedItems[] = $normalized;
    }

    foreach (notes_1402_seed_term_5_items() as $seedItem) {
        $normalized = notes_1402_normalize_item_record($seedItem);
        if ($normalized === null) {
            continue;
        }

        $signature = notes_1402_item_signature($normalized);
        if (isset($knownSignatures[$signature])) {
            continue;
        }

        $knownSignatures[$signature] = true;
        $maxId = max($maxId, (int) ($normalized['id'] ?? 0));
        $mergedItems[] = $normalized;
    }

    usort($mergedItems, static function (array $left, array $right): int {
        return (int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0);
    });

    $term5['items'] = $mergedItems;
    $seed['terms']['5'] = $term5;
    $seed['nextItemId'] = max((int) ($seed['nextItemId'] ?? 1), $maxId + 1);
    $seed['seedBackfillVersion'] = NOTES_1402_SEED_BACKFILL_VERSION;

    return $seed;
}

function notes_1402_pulp_periapical_fix_aliases(): array
{
    static $aliases = null;
    if (is_array($aliases)) {
        return $aliases;
    }

    $unit = dent_dentistry_curriculum_find_unit('pulp-periapical-complex');
    if (!is_array($unit)) {
        $aliases = [];
        return $aliases;
    }

    $aliases = notes_curriculum_unit_search_aliases($unit);
    return $aliases;
}

function notes_1402_item_matches_pulp_periapical_fix(array $item): bool
{
    $storedUnitKey = trim(strtolower((string) ($item['unitKey'] ?? '')));
    if ($storedUnitKey === 'pulp-periapical-complex' || $storedUnitKey === 'endo-theory-1') {
        return true;
    }

    $haystack = notes_curriculum_normalize_text(implode(' ', [
        (string) ($item['title'] ?? ''),
        (string) ($item['badge'] ?? ''),
        (string) ($item['description'] ?? ''),
        (string) ($item['buttonUrl'] ?? ''),
    ]));
    if ($haystack === '') {
        return false;
    }

    foreach (notes_1402_pulp_periapical_fix_aliases() as $alias) {
        if ($alias !== '' && strpos($haystack, $alias) !== false) {
            return true;
        }
    }

    return false;
}

function notes_1402_apply_pulp_periapical_curriculum_fix(array $seed): array
{
    if (!isset($seed['terms']) || !is_array($seed['terms'])) {
        return $seed;
    }

    $terms = $seed['terms'];
    if (!isset($terms['5']) || !is_array($terms['5'])) {
        $terms['5'] = notes_1402_term_template(5);
    }

    $term5 = $terms['5'];
    $term5Items = is_array($term5['items'] ?? null) ? $term5['items'] : [];
    $term5Signatures = [];
    $normalizedTerm5Items = [];

    foreach ($term5Items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if (notes_1402_item_matches_pulp_periapical_fix($item)) {
            $item['unitKey'] = 'pulp-periapical-complex';
        }
        $term5Signatures[notes_1402_item_signature_from_seed($item)] = true;
        $normalizedTerm5Items[] = $item;
    }

    foreach ($terms as $termKey => $termRecord) {
        if ((string) $termKey === '5' || !is_array($termRecord)) {
            continue;
        }

        $items = is_array($termRecord['items'] ?? null) ? $termRecord['items'] : [];
        $keptItems = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (!notes_1402_item_matches_pulp_periapical_fix($item)) {
                $keptItems[] = $item;
                continue;
            }

            $item['unitKey'] = 'pulp-periapical-complex';
            $signature = notes_1402_item_signature_from_seed($item);
            if (!isset($term5Signatures[$signature])) {
                $term5Signatures[$signature] = true;
                $normalizedTerm5Items[] = $item;
            }
        }

        $termRecord['items'] = $keptItems;
        $terms[$termKey] = $termRecord;
    }

    $term5['items'] = $normalizedTerm5Items;
    $terms['5'] = $term5;
    $seed['terms'] = $terms;

    return $seed;
}

function notes_1402_default_store(): array
{
    $terms = [];
    for ($term = NOTES_1402_MIN_TERM; $term <= NOTES_1402_MAX_TERM; $term++) {
        $template = notes_1402_term_template($term);
        $template['items'] = $term === 5 ? notes_1402_seed_term_5_items() : [];
        $terms[(string) $term] = $template;
    }

    return array_merge([
        'schemaVersion' => NOTES_1402_SCHEMA_VERSION,
        'seedBackfillVersion' => NOTES_1402_SEED_BACKFILL_VERSION,
        'nextItemId' => 12,
        'terms' => $terms,
    ], notes_resource_meta_defaults());
}

function notes_1402_ensure_storage(): void
{
    dent_ensure_directory(dirname(notes_1402_store_path()));
    if (!is_file(notes_1402_store_path())) {
        dent_write_json_file(notes_1402_store_path(), notes_1402_default_store());
    }
}

function notes_1402_normalize_url(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $clean = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $value);
    if (!is_string($clean)) {
        $clean = $value;
    }
    $clean = trim($clean);
    if ($clean === '') {
        return '';
    }

    if (str_starts_with($clean, '/') && !str_starts_with($clean, '//')) {
        return $clean;
    }

    if (!preg_match('/^https?:\/\//i', $clean)) {
        return '';
    }

    $validated = filter_var($clean, FILTER_VALIDATE_URL);
    if (!is_string($validated) || trim($validated) === '') {
        $parsed = @parse_url($clean);
        if (
            !is_array($parsed) ||
            !in_array(dent_utf8_strtolower((string) ($parsed['scheme'] ?? '')), ['http', 'https'], true) ||
            trim((string) ($parsed['host'] ?? '')) === ''
        ) {
            return '';
        }

        return $clean;
    }

    return $validated;
}

function notes_normalize_iso_string($value, string $fallback = ''): string
{
    $clean = trim((string) $value);
    if ($clean === '') {
        return $fallback;
    }

    return $clean;
}

function notes_normalize_item_version_history($value): array
{
    $historySeed = is_array($value) ? $value : [];
    $history = [];
    foreach ($historySeed as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $version = max(1, (int) ($entry['version'] ?? 1));
        $snapshotAt = notes_normalize_iso_string($entry['snapshotAt'] ?? '', (string) ($entry['updatedAt'] ?? ''));
        if ($snapshotAt === '') {
            $snapshotAt = dent_iso_now();
        }

        $history[] = [
            'version' => $version,
            'snapshotAt' => $snapshotAt,
            'badge' => dent_clean_text((string) ($entry['badge'] ?? ''), 70),
            'title' => dent_clean_text((string) ($entry['title'] ?? ''), 180),
            'description' => dent_clean_text((string) ($entry['description'] ?? ''), 600),
            'buttonLabel' => dent_clean_text((string) ($entry['buttonLabel'] ?? ''), 70),
            'buttonUrl' => notes_1402_normalize_url((string) ($entry['buttonUrl'] ?? '')),
            'unitKey' => trim(strtolower((string) ($entry['unitKey'] ?? ''))),
            'updatedAt' => notes_normalize_iso_string($entry['updatedAt'] ?? '', $snapshotAt),
        ];
    }

    usort($history, static function (array $left, array $right): int {
        return strcmp((string) ($right['snapshotAt'] ?? ''), (string) ($left['snapshotAt'] ?? ''));
    });

    return array_slice($history, 0, NOTES_RESOURCE_HISTORY_LIMIT);
}

function notes_item_version_snapshot(array $item, string $snapshotAt): array
{
    return [
        'version' => max(1, (int) ($item['version'] ?? 1)),
        'snapshotAt' => $snapshotAt,
        'badge' => (string) ($item['badge'] ?? ''),
        'title' => (string) ($item['title'] ?? ''),
        'description' => (string) ($item['description'] ?? ''),
        'buttonLabel' => (string) ($item['buttonLabel'] ?? ''),
        'buttonUrl' => (string) ($item['buttonUrl'] ?? ''),
        'unitKey' => (string) ($item['unitKey'] ?? ''),
        'updatedAt' => (string) ($item['updatedAt'] ?? ''),
    ];
}

function notes_item_edit_signature(array $item): string
{
    return hash('sha256', json_encode([
        (string) ($item['badge'] ?? ''),
        (string) ($item['title'] ?? ''),
        (string) ($item['description'] ?? ''),
        (string) ($item['buttonLabel'] ?? ''),
        (string) ($item['buttonUrl'] ?? ''),
        (string) ($item['unitKey'] ?? ''),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function notes_normalize_resource_user_key($value): string
{
    $studentNumber = dent_normalize_student_number((string) $value);
    if ($studentNumber !== '') {
        return $studentNumber;
    }

    $clean = trim((string) $value);
    return preg_match('/^[a-zA-Z0-9_.:-]{2,80}$/', $clean) === 1 ? $clean : '';
}

function notes_resource_user_key(array $viewer): string
{
    return notes_normalize_resource_user_key($viewer['studentNumber'] ?? '');
}

function notes_resource_user_name(array $viewer): string
{
    $name = dent_clean_text((string) ($viewer['name'] ?? ''), 120);
    if ($name !== '') {
        return $name;
    }

    return (string) ($viewer['studentNumber'] ?? '');
}

function notes_normalize_resource_state_entry($value): ?array
{
    if (!is_array($value)) {
        return null;
    }

    $itemId = max(0, (int) ($value['itemId'] ?? $value['id'] ?? 0));
    if ($itemId <= 0) {
        return null;
    }

    return [
        'itemId' => $itemId,
        'term' => max(0, (int) ($value['term'] ?? 0)),
        'unitKey' => trim(strtolower((string) ($value['unitKey'] ?? ''))),
        'updatedAt' => notes_normalize_iso_string($value['updatedAt'] ?? $value['viewedAt'] ?? '', dent_iso_now()),
        'viewedAt' => notes_normalize_iso_string($value['viewedAt'] ?? $value['updatedAt'] ?? '', dent_iso_now()),
    ];
}

function notes_normalize_resource_user_state($value): array
{
    $seed = is_array($value) ? $value : [];
    $favorites = [];
    $favoriteSeed = is_array($seed['favorites'] ?? null) ? $seed['favorites'] : [];
    foreach ($favoriteSeed as $key => $entry) {
        if (!is_array($entry)) {
            $entry = ['itemId' => $key, 'updatedAt' => (string) $entry];
        }
        $normalized = notes_normalize_resource_state_entry($entry);
        if ($normalized !== null) {
            $favorites[(string) $normalized['itemId']] = $normalized;
        }
    }

    $recent = [];
    $recentSeed = is_array($seed['recent'] ?? null) ? $seed['recent'] : [];
    foreach ($recentSeed as $entry) {
        $normalized = notes_normalize_resource_state_entry($entry);
        if ($normalized !== null) {
            $recent[] = $normalized;
        }
    }
    usort($recent, static function (array $left, array $right): int {
        return strcmp((string) ($right['viewedAt'] ?? ''), (string) ($left['viewedAt'] ?? ''));
    });

    return [
        'favorites' => $favorites,
        'recent' => array_slice($recent, 0, NOTES_RESOURCE_RECENT_LIMIT),
    ];
}

function notes_normalize_resource_user_states($value): array
{
    $seed = is_array($value) ? $value : [];
    $states = [];
    foreach ($seed as $key => $entry) {
        $userKey = notes_normalize_resource_user_key($key);
        if ($userKey === '') {
            continue;
        }
        $states[$userKey] = notes_normalize_resource_user_state($entry);
    }

    return $states;
}

function notes_normalize_resource_issue_status($value): string
{
    $status = trim(strtolower((string) $value));
    return in_array($status, ['open', 'resolved', 'dismissed'], true) ? $status : 'open';
}

function notes_normalize_resource_requests($value): array
{
    $seed = is_array($value) ? $value : [];
    $requests = [];
    foreach ($seed as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $id = max(0, (int) ($entry['id'] ?? 0));
        $title = dent_clean_text((string) ($entry['title'] ?? ''), 180);
        $note = dent_clean_text((string) ($entry['note'] ?? ''), 800);
        if ($id <= 0 || ($title === '' && $note === '')) {
            continue;
        }

        $requests[(string) $id] = [
            'id' => $id,
            'userKey' => notes_normalize_resource_user_key($entry['userKey'] ?? ''),
            'userName' => dent_clean_text((string) ($entry['userName'] ?? ''), 120),
            'term' => max(0, (int) ($entry['term'] ?? 0)),
            'unitKey' => trim(strtolower((string) ($entry['unitKey'] ?? ''))),
            'title' => $title,
            'note' => $note,
            'status' => notes_normalize_resource_issue_status($entry['status'] ?? 'open'),
            'createdAt' => notes_normalize_iso_string($entry['createdAt'] ?? '', dent_iso_now()),
            'updatedAt' => notes_normalize_iso_string($entry['updatedAt'] ?? $entry['createdAt'] ?? '', dent_iso_now()),
        ];
    }

    uasort($requests, static function (array $left, array $right): int {
        return strcmp((string) ($right['createdAt'] ?? ''), (string) ($left['createdAt'] ?? ''));
    });

    return $requests;
}

function notes_normalize_resource_link_reports($value): array
{
    $seed = is_array($value) ? $value : [];
    $reports = [];
    foreach ($seed as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $id = max(0, (int) ($entry['id'] ?? 0));
        $itemId = max(0, (int) ($entry['itemId'] ?? 0));
        if ($id <= 0 || $itemId <= 0) {
            continue;
        }

        $reports[(string) $id] = [
            'id' => $id,
            'itemId' => $itemId,
            'userKey' => notes_normalize_resource_user_key($entry['userKey'] ?? ''),
            'userName' => dent_clean_text((string) ($entry['userName'] ?? ''), 120),
            'term' => max(0, (int) ($entry['term'] ?? 0)),
            'unitKey' => trim(strtolower((string) ($entry['unitKey'] ?? ''))),
            'itemTitle' => dent_clean_text((string) ($entry['itemTitle'] ?? ''), 180),
            'itemUrl' => notes_1402_normalize_url((string) ($entry['itemUrl'] ?? '')),
            'reason' => dent_clean_text((string) ($entry['reason'] ?? ''), 180),
            'note' => dent_clean_text((string) ($entry['note'] ?? ''), 800),
            'status' => notes_normalize_resource_issue_status($entry['status'] ?? 'open'),
            'createdAt' => notes_normalize_iso_string($entry['createdAt'] ?? '', dent_iso_now()),
            'updatedAt' => notes_normalize_iso_string($entry['updatedAt'] ?? $entry['createdAt'] ?? '', dent_iso_now()),
        ];
    }

    uasort($reports, static function (array $left, array $right): int {
        return strcmp((string) ($right['createdAt'] ?? ''), (string) ($left['createdAt'] ?? ''));
    });

    return $reports;
}

function notes_resource_meta_defaults(): array
{
    return [
        'resourceUserState' => [],
        'resourceRequests' => [],
        'resourceLinkReports' => [],
        'nextResourceRequestId' => 1,
        'nextResourceLinkReportId' => 1,
    ];
}

function notes_normalize_resource_meta_store(array $seed): array
{
    $requests = notes_normalize_resource_requests($seed['resourceRequests'] ?? []);
    $reports = notes_normalize_resource_link_reports($seed['resourceLinkReports'] ?? []);
    $maxRequestId = 0;
    foreach ($requests as $request) {
        $maxRequestId = max($maxRequestId, (int) ($request['id'] ?? 0));
    }
    $maxReportId = 0;
    foreach ($reports as $report) {
        $maxReportId = max($maxReportId, (int) ($report['id'] ?? 0));
    }

    return [
        'resourceUserState' => notes_normalize_resource_user_states($seed['resourceUserState'] ?? []),
        'resourceRequests' => $requests,
        'resourceLinkReports' => $reports,
        'nextResourceRequestId' => max(1, (int) ($seed['nextResourceRequestId'] ?? 1), $maxRequestId + 1),
        'nextResourceLinkReportId' => max(1, (int) ($seed['nextResourceLinkReportId'] ?? 1), $maxReportId + 1),
    ];
}

function notes_1402_normalize_item_record(array $seed): ?array
{
    $id = max(0, (int) ($seed['id'] ?? 0));
    if ($id <= 0) {
        return null;
    }

    $badge = dent_clean_text((string) ($seed['badge'] ?? ''), 70);
    $title = dent_clean_text((string) ($seed['title'] ?? ''), 180);
    $description = dent_clean_text((string) ($seed['description'] ?? ''), 600);
    $buttonLabel = dent_clean_text((string) ($seed['buttonLabel'] ?? ''), 70);
    $buttonUrl = notes_1402_normalize_url((string) ($seed['buttonUrl'] ?? ''));
    $unitKey = trim(strtolower((string) ($seed['unitKey'] ?? '')));
    if ($unitKey !== '') {
        $unit = dent_dentistry_curriculum_find_unit($unitKey);
        $unitKey = is_array($unit) ? (string) ($unit['key'] ?? '') : '';
    }

    if ($badge === '' || $title === '' || $description === '' || $buttonLabel === '' || $buttonUrl === '') {
        return null;
    }

    return [
        'id' => $id,
        'badge' => $badge,
        'title' => $title,
        'description' => $description,
        'buttonLabel' => $buttonLabel,
        'buttonUrl' => $buttonUrl,
        'unitKey' => $unitKey,
        'createdAt' => (string) ($seed['createdAt'] ?? dent_iso_now()),
        'updatedAt' => (string) ($seed['updatedAt'] ?? dent_iso_now()),
        'version' => max(1, (int) ($seed['version'] ?? 1)),
        'versionHistory' => notes_normalize_item_version_history($seed['versionHistory'] ?? []),
    ];
}

function notes_1402_normalize_store(array $seed): array
{
    $defaults = notes_1402_default_store();
    $termsSeed = is_array($seed['terms'] ?? null) ? $seed['terms'] : [];

    $normalizedTerms = [];
    $maxItemId = 0;
    for ($term = NOTES_1402_MIN_TERM; $term <= NOTES_1402_MAX_TERM; $term++) {
        $termKey = (string) $term;
        $defaultTerm = $defaults['terms'][$termKey];
        $termSeed = is_array($termsSeed[$termKey] ?? null) ? $termsSeed[$termKey] : [];
        $itemsSeed = is_array($termSeed['items'] ?? null) ? $termSeed['items'] : [];

        $items = [];
        foreach ($itemsSeed as $itemSeed) {
            if (!is_array($itemSeed)) {
                continue;
            }
            $item = notes_1402_normalize_item_record($itemSeed);
            if ($item === null) {
                continue;
            }
            $maxItemId = max($maxItemId, (int) $item['id']);
            $items[] = $item;
        }

        usort($items, static function (array $left, array $right): int {
            return (int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0);
        });

        $kicker = notes_repair_fa_digit_mojibake((string) ($termSeed['kicker'] ?? $defaultTerm['kicker']));
        $title = notes_repair_fa_digit_mojibake((string) ($termSeed['title'] ?? $defaultTerm['title']));
        $description = notes_repair_fa_digit_mojibake((string) ($termSeed['description'] ?? $defaultTerm['description']));
        $emptyMessage = notes_repair_fa_digit_mojibake((string) ($termSeed['emptyMessage'] ?? $defaultTerm['emptyMessage']));

        $normalizedTerms[$termKey] = [
            'term' => $term,
            'kicker' => dent_clean_text($kicker, 80),
            'title' => dent_clean_text($title, 160),
            'description' => dent_clean_text($description, 800),
            'emptyMessage' => dent_clean_text($emptyMessage, 400),
            'items' => $items,
        ];
    }

    return array_merge([
        'schemaVersion' => NOTES_1402_SCHEMA_VERSION,
        'seedBackfillVersion' => max(0, (int) ($seed['seedBackfillVersion'] ?? 0)),
        'nextItemId' => max(1, (int) ($seed['nextItemId'] ?? 1), $maxItemId + 1),
        'terms' => $normalizedTerms,
    ], notes_normalize_resource_meta_store($seed));
}

function notes_1402_load_store_unlocked(): array
{
    $raw = dent_read_json_file(notes_1402_store_path(), notes_1402_default_store());
    if (!isset($raw['terms']) || !is_array($raw['terms'])) {
        throw new DentJsonPersistenceException(
            'NOTES_1402_STORE_SCHEMA_INVALID',
            'Existing notes 1402 store has an invalid schema'
        );
    }
    $raw = notes_1402_apply_term_5_seed_backfill($raw);
    $raw = notes_1402_apply_pulp_periapical_curriculum_fix($raw);

    return notes_1402_normalize_store($raw);
}

function notes_1402_save_store_unlocked(array $store): void
{
    dent_write_json_file(notes_1402_store_path(), notes_1402_normalize_store($store));
}

function notes_1403_default_store(): array
{
    return notes_fixed_terms_default_store(
        NOTES_1403_SCHEMA_VERSION,
        NOTES_1403_MIN_TERM,
        NOTES_1403_MAX_TERM,
        'notes_1403_term_template',
        [NOTES_1403_MIN_TERM => notes_1403_seed_items()]
    );
}

function notes_1404_default_store(): array
{
    return notes_fixed_terms_default_store(
        NOTES_1404_SCHEMA_VERSION,
        NOTES_1404_MIN_TERM,
        NOTES_1404_MAX_TERM,
        'notes_1404_term_template'
    );
}

function notes_fixed_terms_normalize_store(
    array $seed,
    int $schemaVersion,
    int $minTerm,
    int $maxTerm,
    callable $templateFn
): array {
    $defaults = notes_fixed_terms_default_store($schemaVersion, $minTerm, $maxTerm, $templateFn);
    $termsSeed = is_array($seed['terms'] ?? null) ? $seed['terms'] : [];
    $normalizedTerms = [];
    $maxItemId = 0;

    for ($term = $minTerm; $term <= $maxTerm; $term++) {
        $termKey = (string) $term;
        $defaultTerm = $defaults['terms'][$termKey];
        $termSeed = is_array($termsSeed[$termKey] ?? null) ? $termsSeed[$termKey] : [];
        $itemsSeed = is_array($termSeed['items'] ?? null) ? $termSeed['items'] : [];
        $items = [];

        foreach ($itemsSeed as $itemSeed) {
            if (!is_array($itemSeed)) {
                continue;
            }
            $item = notes_1402_normalize_item_record($itemSeed);
            if ($item === null) {
                continue;
            }
            $maxItemId = max($maxItemId, (int) $item['id']);
            $items[] = $item;
        }

        usort($items, static function (array $left, array $right): int {
            return (int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0);
        });

        $kicker = notes_repair_fa_digit_mojibake((string) ($termSeed['kicker'] ?? $defaultTerm['kicker']));
        $title = notes_repair_fa_digit_mojibake((string) ($termSeed['title'] ?? $defaultTerm['title']));
        $description = notes_repair_fa_digit_mojibake((string) ($termSeed['description'] ?? $defaultTerm['description']));
        $emptyMessage = notes_repair_fa_digit_mojibake((string) ($termSeed['emptyMessage'] ?? $defaultTerm['emptyMessage']));

        $normalizedTerms[$termKey] = [
            'term' => $term,
            'kicker' => dent_clean_text($kicker, 80),
            'title' => dent_clean_text($title, 160),
            'description' => dent_clean_text($description, 800),
            'emptyMessage' => dent_clean_text($emptyMessage, 400),
            'items' => $items,
        ];
    }

    return array_merge([
        'schemaVersion' => $schemaVersion,
        'nextItemId' => max(1, (int) ($seed['nextItemId'] ?? 1), $maxItemId + 1),
        'terms' => $normalizedTerms,
    ], notes_normalize_resource_meta_store($seed));
}

function notes_1403_normalize_store(array $seed): array
{
    return notes_fixed_terms_normalize_store(
        $seed,
        NOTES_1403_SCHEMA_VERSION,
        NOTES_1403_MIN_TERM,
        NOTES_1403_MAX_TERM,
        'notes_1403_term_template'
    );
}

function notes_1404_normalize_store(array $seed): array
{
    return notes_fixed_terms_normalize_store(
        $seed,
        NOTES_1404_SCHEMA_VERSION,
        NOTES_1404_MIN_TERM,
        NOTES_1404_MAX_TERM,
        'notes_1404_term_template'
    );
}

function notes_1403_migrate_legacy_store(array $legacy): array
{
    $store = notes_fixed_terms_default_store(
        NOTES_1403_SCHEMA_VERSION,
        NOTES_1403_MIN_TERM,
        NOTES_1403_MAX_TERM,
        'notes_1403_term_template'
    );

    $archive = is_array($legacy['archive'] ?? null) ? $legacy['archive'] : [];
    $termKey = (string) NOTES_1403_MIN_TERM;
    $termSeed = $store['terms'][$termKey] ?? notes_1403_term_template(NOTES_1403_MIN_TERM);
    $termSeed['kicker'] = dent_clean_text((string) ($archive['kicker'] ?? $termSeed['kicker']), 80);
    $termSeed['title'] = dent_clean_text((string) ($archive['title'] ?? $termSeed['title']), 160);
    $termSeed['description'] = dent_clean_text((string) ($archive['description'] ?? $termSeed['description']), 800);
    $termSeed['emptyMessage'] = dent_clean_text((string) ($archive['emptyMessage'] ?? $termSeed['emptyMessage']), 400);
    $termSeed['items'] = is_array($archive['items'] ?? null) ? $archive['items'] : [];
    $store['terms'][$termKey] = $termSeed;

    return notes_1403_normalize_store($store);
}

function notes_1403_ensure_storage(): void
{
    dent_ensure_directory(dirname(notes_1403_store_path()));
    if (is_file(notes_1403_store_path())) {
        return;
    }

    $seed = notes_1403_default_store();
    if (is_file(notes_1403_legacy_store_path())) {
        $legacy = dent_read_json_file(notes_1403_legacy_store_path(), []);
        if (is_array($legacy)) {
            $seed = notes_1403_migrate_legacy_store($legacy);
        }
    }

    dent_write_json_file(notes_1403_store_path(), $seed);
}

function notes_1404_ensure_storage(): void
{
    dent_ensure_directory(dirname(notes_1404_store_path()));
    if (!is_file(notes_1404_store_path())) {
        dent_write_json_file(notes_1404_store_path(), notes_1404_default_store());
    }
}

function notes_1403_load_store_unlocked(): array
{
    $raw = dent_read_json_file(notes_1403_store_path(), notes_1403_default_store());
    if (!isset($raw['terms']) || !is_array($raw['terms'])) {
        throw new DentJsonPersistenceException(
            'NOTES_1403_STORE_SCHEMA_INVALID',
            'Existing notes 1403 store has an invalid schema'
        );
    }

    return notes_1403_normalize_store($raw);
}

function notes_1403_save_store_unlocked(array $store): void
{
    dent_write_json_file(notes_1403_store_path(), notes_1403_normalize_store($store));
}

function notes_1404_load_store_unlocked(): array
{
    $raw = dent_read_json_file(notes_1404_store_path(), notes_1404_default_store());
    if (!isset($raw['terms']) || !is_array($raw['terms'])) {
        throw new DentJsonPersistenceException(
            'NOTES_1404_STORE_SCHEMA_INVALID',
            'Existing notes 1404 store has an invalid schema'
        );
    }

    return notes_1404_normalize_store($raw);
}

function notes_1404_save_store_unlocked(array $store): void
{
    dent_write_json_file(notes_1404_store_path(), notes_1404_normalize_store($store));
}

function notes_prosthesis_1402_default_store(): array
{
    return array_merge([
        'schemaVersion' => NOTES_PROSTHESIS_1402_SCHEMA_VERSION,
        'nextTermId' => 1,
        'nextItemId' => 1,
        'terms' => [],
    ], notes_resource_meta_defaults());
}

function notes_prosthesis_1402_ensure_storage(): void
{
    dent_ensure_directory(dirname(notes_prosthesis_1402_store_path()));
    if (!is_file(notes_prosthesis_1402_store_path())) {
        dent_write_json_file(notes_prosthesis_1402_store_path(), notes_prosthesis_1402_default_store());
    }
}

function notes_prosthesis_1402_parse_term_id($raw): int
{
    $termId = (int) dent_normalize_digits((string) $raw);
    if ($termId <= 0) {
        dent_error('شناسه ترم پروتز معتبر نیست.', 422);
    }

    return $termId;
}

function notes_prosthesis_1402_normalize_term_record(array $seed): ?array
{
    $id = max(0, (int) ($seed['id'] ?? 0));
    if ($id <= 0) {
        return null;
    }

    $title = dent_clean_text((string) ($seed['title'] ?? ''), 160);
    if ($title === '') {
        return null;
    }

    $kicker = dent_clean_text((string) ($seed['kicker'] ?? ''), 80);
    if ($kicker === '') {
        $kicker = 'پروتز ۱۴۰۲';
    }

    $description = dent_clean_text((string) ($seed['description'] ?? ''), 800);
    if ($description === '') {
        $description = 'منابع این ترم به‌مرور اضافه می‌شوند.';
    }

    $emptyMessage = dent_clean_text((string) ($seed['emptyMessage'] ?? ''), 400);
    if ($emptyMessage === '') {
        $emptyMessage = 'برای این ترم هنوز منبعی ثبت نشده است.';
    }

    $itemsSeed = is_array($seed['items'] ?? null) ? $seed['items'] : [];
    $items = [];
    foreach ($itemsSeed as $itemSeed) {
        if (!is_array($itemSeed)) {
            continue;
        }
        $item = notes_1402_normalize_item_record($itemSeed);
        if ($item !== null) {
            $items[] = $item;
        }
    }
    usort($items, static function (array $left, array $right): int {
        return (int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0);
    });

    return [
        'id' => $id,
        'kicker' => $kicker,
        'title' => $title,
        'description' => $description,
        'emptyMessage' => $emptyMessage,
        'items' => $items,
        'createdAt' => (string) ($seed['createdAt'] ?? dent_iso_now()),
        'updatedAt' => (string) ($seed['updatedAt'] ?? dent_iso_now()),
    ];
}

function notes_prosthesis_1402_normalize_store(array $seed): array
{
    $termsSeed = is_array($seed['terms'] ?? null) ? $seed['terms'] : [];
    $terms = [];
    $maxTermId = 0;
    $maxItemId = 0;

    foreach ($termsSeed as $termSeed) {
        if (!is_array($termSeed)) {
            continue;
        }
        $term = notes_prosthesis_1402_normalize_term_record($termSeed);
        if ($term === null) {
            continue;
        }
        $termId = (int) $term['id'];
        $maxTermId = max($maxTermId, $termId);
        foreach ($term['items'] as $item) {
            $maxItemId = max($maxItemId, (int) ($item['id'] ?? 0));
        }
        $terms[(string) $termId] = $term;
    }

    uasort($terms, static function (array $left, array $right): int {
        return (int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0);
    });

    return array_merge([
        'schemaVersion' => NOTES_PROSTHESIS_1402_SCHEMA_VERSION,
        'nextTermId' => max(1, (int) ($seed['nextTermId'] ?? 1), $maxTermId + 1),
        'nextItemId' => max(1, (int) ($seed['nextItemId'] ?? 1), $maxItemId + 1),
        'terms' => $terms,
    ], notes_normalize_resource_meta_store($seed));
}

function notes_prosthesis_1402_load_store_unlocked(): array
{
    $raw = dent_read_json_file(notes_prosthesis_1402_store_path(), notes_prosthesis_1402_default_store());
    if (!isset($raw['terms']) || !is_array($raw['terms'])) {
        throw new DentJsonPersistenceException(
            'NOTES_PROSTHESIS_STORE_SCHEMA_INVALID',
            'Existing prosthesis notes store has an invalid schema'
        );
    }

    return notes_prosthesis_1402_normalize_store($raw);
}

function notes_prosthesis_1402_save_store_unlocked(array $store): void
{
    dent_write_json_file(notes_prosthesis_1402_store_path(), notes_prosthesis_1402_normalize_store($store));
}

function notes_prosthesis_1402_read_store(): array
{
    notes_prosthesis_1402_ensure_storage();

    $lock = fopen(notes_prosthesis_1402_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل آرشیو پروتز.', 500);
    }

    try {
        if (!flock($lock, LOCK_SH)) {
            throw new RuntimeException('Unable to acquire prosthesis notes shared lock.');
        }

        return notes_prosthesis_1402_load_store_unlocked();
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

/**
 * @template T
 * @param callable(array):T $callback
 * @return T
 */
function notes_prosthesis_1402_with_store_lock(callable $callback)
{
    notes_prosthesis_1402_ensure_storage();

    $lock = fopen(notes_prosthesis_1402_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل آرشیو پروتز.', 500);
    }

    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('Unable to acquire prosthesis notes exclusive lock.');
        }

        $store = notes_prosthesis_1402_load_store_unlocked();
        $result = $callback($store);
        notes_prosthesis_1402_save_store_unlocked($store);
        return $result;
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function notes_1403_read_store(): array
{
    notes_1403_ensure_storage();

    $lock = fopen(notes_1403_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل آرشیو منابع.', 500);
    }

    $store = notes_1403_default_store();
    try {
        if (!flock($lock, LOCK_SH)) {
            throw new RuntimeException('Unable to acquire notes 1403 shared lock.');
        }

        $store = notes_1403_load_store_unlocked();
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }

    return $store;
}

/**
 * @template T
 * @param callable(array):T $callback
 * @return T
 */
function notes_1403_with_store_lock(callable $callback)
{
    notes_1403_ensure_storage();

    $lock = fopen(notes_1403_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل آرشیو منابع.', 500);
    }

    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('Unable to acquire notes 1403 exclusive lock.');
        }

        $store = notes_1403_load_store_unlocked();
        $result = $callback($store);
        notes_1403_save_store_unlocked($store);
        return $result;
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function notes_1404_read_store(): array
{
    notes_1404_ensure_storage();

    $lock = fopen(notes_1404_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل آرشیو منابع.', 500);
    }

    $store = notes_1404_default_store();
    try {
        if (!flock($lock, LOCK_SH)) {
            throw new RuntimeException('Unable to acquire notes 1404 shared lock.');
        }

        $store = notes_1404_load_store_unlocked();
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }

    return $store;
}

/**
 * @template T
 * @param callable(array):T $callback
 * @return T
 */
function notes_1404_with_store_lock(callable $callback)
{
    notes_1404_ensure_storage();

    $lock = fopen(notes_1404_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل آرشیو منابع.', 500);
    }

    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('Unable to acquire notes 1404 exclusive lock.');
        }

        $store = notes_1404_load_store_unlocked();
        $result = $callback($store);
        notes_1404_save_store_unlocked($store);
        return $result;
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function notes_1402_read_store(): array
{
    notes_1402_ensure_storage();

    $lock = fopen(notes_1402_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل آرشیو منابع.', 500);
    }

    $store = notes_1402_default_store();
    try {
        if (!flock($lock, LOCK_SH)) {
            throw new RuntimeException('Unable to acquire notes shared lock.');
        }

        $store = notes_1402_load_store_unlocked();
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }

    return $store;
}

/**
 * @template T
 * @param callable(array):T $callback
 * @return T
 */
function notes_1402_with_store_lock(callable $callback)
{
    notes_1402_ensure_storage();

    $lock = fopen(notes_1402_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل آرشیو منابع.', 500);
    }

    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('Unable to acquire notes exclusive lock.');
        }

        $store = notes_1402_load_store_unlocked();
        $result = $callback($store);
        notes_1402_save_store_unlocked($store);
        return $result;
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function notes_1402_parse_term($raw): int
{
    $term = (int) dent_normalize_digits((string) $raw);
    if ($term < NOTES_1402_MIN_TERM || $term > NOTES_1402_MAX_TERM) {
        dent_error('شماره ترم معتبر نیست.', 422);
    }

    return $term;
}

function notes_parse_cohort($raw): string
{
    $cohort = dent_normalize_digits(trim((string) $raw));
    if ($cohort === '') {
        return '1402';
    }

    if (!in_array($cohort, ['1402', '1403', '1404', 'prosthesis-1402'], true)) {
        dent_error('آرشیو منابع معتبر نیست.', 422);
    }

    return $cohort;
}

function notes_require_term_for_cohort(string $cohort, $raw): int
{
    if ($cohort === '1402') {
        return notes_1402_parse_term($raw);
    }

    if ($cohort === '1403') {
        $term = (int) dent_normalize_digits((string) $raw);
        if ($term < NOTES_1403_MIN_TERM || $term > NOTES_1403_MAX_TERM) {
            dent_error('شماره ترم معتبر نیست.', 422);
        }
        return $term;
    }

    if ($cohort === '1404') {
        $term = (int) dent_normalize_digits((string) $raw);
        if ($term < NOTES_1404_MIN_TERM || $term > NOTES_1404_MAX_TERM) {
            dent_error('شماره ترم معتبر نیست.', 422);
        }
        return $term;
    }

    return 0;
}

function notes_1402_item_payload(array $item): array
{
    $url = (string) ($item['buttonUrl'] ?? '');
    $isExternal = !str_starts_with($url, '/');
    $unitKey = trim(strtolower((string) ($item['unitKey'] ?? '')));

    return [
        'id' => (int) ($item['id'] ?? 0),
        'badge' => (string) ($item['badge'] ?? ''),
        'title' => (string) ($item['title'] ?? ''),
        'description' => (string) ($item['description'] ?? ''),
        'buttonLabel' => (string) ($item['buttonLabel'] ?? ''),
        'buttonUrl' => $url,
        'isExternal' => $isExternal,
        'unitKey' => $unitKey,
        'createdAt' => (string) ($item['createdAt'] ?? ''),
        'updatedAt' => (string) ($item['updatedAt'] ?? ''),
        'version' => max(1, (int) ($item['version'] ?? 1)),
        'versionCount' => max(1, count(is_array($item['versionHistory'] ?? null) ? $item['versionHistory'] : []) + 1),
    ];
}

function notes_curriculum_primary_term_numbers(): array
{
    static $numbers = null;
    if (is_array($numbers)) {
        return $numbers;
    }

    $numbers = array_values(array_map(
        static function (array $term): int {
            return max(0, (int) ($term['number'] ?? 0));
        },
        array_filter(dent_dentistry_curriculum_terms(), 'is_array')
    ));

    return $numbers;
}

function notes_is_curriculum_cohort(string $cohort): bool
{
    return $cohort === '1402' || $cohort === '1403' || $cohort === '1404';
}

function notes_curriculum_normalize_text(string $value): string
{
    $normalized = dent_normalize_digits(trim($value));
    $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
    return dent_utf8_strtolower($normalized);
}

function notes_curriculum_unit_search_aliases(array $unit): array
{
    $aliases = [];
    $candidates = array_merge(
        [(string) ($unit['title'] ?? '')],
        is_array($unit['aliases'] ?? null) ? $unit['aliases'] : [],
        is_array($unit['resourceAliases'] ?? null) ? $unit['resourceAliases'] : []
    );

    foreach ($candidates as $candidate) {
        $normalized = notes_curriculum_normalize_text((string) $candidate);
        if ($normalized === '') {
            continue;
        }
        $aliases[$normalized] = true;
    }

    return array_keys($aliases);
}

function notes_curriculum_meta_from_unit(array $unit): array
{
    return [
        'termNumber' => max(0, (int) ($unit['termNumber'] ?? 0)),
        'termLabel' => (string) ($unit['termLabel'] ?? ''),
        'categoryKey' => (string) ($unit['categoryKey'] ?? ''),
        'categoryTitle' => (string) ($unit['categoryTitle'] ?? ''),
        'unitKey' => (string) ($unit['key'] ?? ''),
        'unitTitle' => (string) ($unit['title'] ?? ''),
    ];
}

function notes_curriculum_item_match_meta(array $item): ?array
{
    $storedUnitKey = trim(strtolower((string) ($item['unitKey'] ?? '')));
    if ($storedUnitKey !== '') {
        $storedUnit = dent_dentistry_curriculum_find_unit($storedUnitKey);
        if (is_array($storedUnit)) {
            return notes_curriculum_meta_from_unit($storedUnit);
        }
    }

    $titleText = notes_curriculum_normalize_text((string) ($item['title'] ?? ''));
    $badgeText = notes_curriculum_normalize_text((string) ($item['badge'] ?? ''));
    $descriptionText = notes_curriculum_normalize_text((string) ($item['description'] ?? ''));
    $haystack = trim($titleText . ' ' . $badgeText . ' ' . $descriptionText);

    $bestUnit = null;
    $bestScore = 0;
    foreach (dent_dentistry_curriculum_unit_index() as $unit) {
        if (!is_array($unit)) {
            continue;
        }

        foreach (notes_curriculum_unit_search_aliases($unit) as $alias) {
            $score = 0;
            $aliasLength = dent_utf8_strlen($alias);
            if ($titleText !== '' && strpos($titleText, $alias) !== false) {
                $score = max($score, 420 + $aliasLength);
            }
            if ($badgeText !== '' && strpos($badgeText, $alias) !== false) {
                $score = max($score, 320 + $aliasLength);
            }
            if ($descriptionText !== '' && strpos($descriptionText, $alias) !== false) {
                $score = max($score, 180 + $aliasLength);
            }
            if ($haystack !== '' && strpos($haystack, $alias) !== false) {
                $score = max($score, 90 + $aliasLength);
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestUnit = $unit;
            }
        }
    }

    return is_array($bestUnit) ? notes_curriculum_meta_from_unit($bestUnit) : null;
}

function notes_curriculum_item_payload(array $item, int $storageTerm): array
{
    $payload = notes_1402_item_payload($item);
    $payload['storageTerm'] = $storageTerm;
    $payload['curriculum'] = notes_curriculum_item_match_meta($item);
    return $payload;
}

function notes_resource_offline_proxy_url(string $cohort, int $term, array $item): string
{
    $itemId = (int) ($item['id'] ?? 0);
    $buttonUrl = trim((string) ($item['buttonUrl'] ?? ''));
    if ($itemId <= 0 || $term <= 0 || $buttonUrl === '') {
        return '';
    }

    return '/api/notes_api.php?' . http_build_query([
        'action' => 'offlineResourceProxy',
        'cohort' => $cohort,
        'term' => (string) $term,
        'itemId' => (string) $itemId,
    ], '', '&', PHP_QUERY_RFC3986);
}

function notes_item_with_offline_pack_url(string $cohort, array $item, int $term): array
{
    $item['offlinePackUrl'] = notes_resource_offline_proxy_url($cohort, $term, $item);
    return $item;
}

function notes_attach_offline_pack_urls(string $cohort, array $termPayload): array
{
    $fallbackTerm = max(0, (int) ($termPayload['term'] ?? $termPayload['id'] ?? 0));
    $items = is_array($termPayload['items'] ?? null) ? $termPayload['items'] : [];
    foreach ($items as $index => $item) {
        if (!is_array($item)) {
            continue;
        }
        $itemTerm = max(0, (int) ($item['storageTerm'] ?? $item['term'] ?? $fallbackTerm));
        $items[$index] = notes_item_with_offline_pack_url($cohort, $item, $itemTerm);
    }
    $termPayload['items'] = $items;
    return $termPayload;
}

function notes_term_item_payloads(array $items, int $storageTerm): array
{
    $payloads = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $payloads[] = notes_curriculum_item_payload($item, $storageTerm);
    }

    return $payloads;
}

function notes_1402_term_payload(array $store, int $term): array
{
    $termRecord = $store['terms'][(string) $term] ?? notes_1402_term_template($term);
    $items = is_array($termRecord['items'] ?? null) ? $termRecord['items'] : [];
    $itemPayloads = notes_term_item_payloads($items, $term);

    return [
        'cohort' => '1402',
        'term' => $term,
        'id' => $term,
        'kicker' => (string) ($termRecord['kicker'] ?? ''),
        'title' => (string) ($termRecord['title'] ?? ''),
        'description' => (string) ($termRecord['description'] ?? ''),
        'emptyMessage' => (string) ($termRecord['emptyMessage'] ?? ''),
        'items' => $itemPayloads,
    ];
}

function notes_1402_terms_payload(array $store): array
{
    $terms = [];
    for ($term = NOTES_1402_MIN_TERM; $term <= NOTES_1402_MAX_TERM; $term++) {
        $terms[] = notes_1402_term_payload($store, $term);
    }

    return $terms;
}

function notes_fixed_terms_term_payload(string $cohort, array $store, int $term, callable $templateFn): array
{
    $termRecord = $store['terms'][(string) $term] ?? $templateFn($term);
    $items = is_array($termRecord['items'] ?? null) ? $termRecord['items'] : [];
    $itemPayloads = notes_term_item_payloads($items, $term);

    return [
        'cohort' => $cohort,
        'term' => $term,
        'id' => $term,
        'kicker' => (string) ($termRecord['kicker'] ?? ''),
        'title' => (string) ($termRecord['title'] ?? ''),
        'description' => (string) ($termRecord['description'] ?? ''),
        'emptyMessage' => (string) ($termRecord['emptyMessage'] ?? ''),
        'items' => $itemPayloads,
    ];
}

function notes_1403_term_payload(array $store, int $term): array
{
    return notes_fixed_terms_term_payload('1403', $store, $term, 'notes_1403_term_template');
}

function notes_1404_term_payload(array $store, int $term): array
{
    return notes_fixed_terms_term_payload('1404', $store, $term, 'notes_1404_term_template');
}

function notes_1403_terms_payload(array $store): array
{
    $terms = [];
    for ($term = NOTES_1403_MIN_TERM; $term <= NOTES_1403_MAX_TERM; $term++) {
        $terms[] = notes_1403_term_payload($store, $term);
    }

    return $terms;
}

function notes_1404_terms_payload(array $store): array
{
    $terms = [];
    for ($term = NOTES_1404_MIN_TERM; $term <= NOTES_1404_MAX_TERM; $term++) {
        $terms[] = notes_1404_term_payload($store, $term);
    }

    return $terms;
}

function notes_curriculum_build_uncategorized_unit_key(int $termNumber): string
{
    return 'uncategorized-term-' . max(0, $termNumber);
}

function notes_curriculum_is_uncategorized_unit_key(string $unitKey): bool
{
    return preg_match('/^uncategorized-term-\d+$/', $unitKey) === 1;
}

function notes_curriculum_uncategorized_term_from_key(string $unitKey): int
{
    if (preg_match('/^uncategorized-term-(\d+)$/', $unitKey, $matches) !== 1) {
        return 0;
    }

    return max(0, (int) ($matches[1] ?? 0));
}

function notes_curriculum_sort_items(array $items): array
{
    usort($items, static function (array $left, array $right): int {
        $updatedComparison = strcmp((string) ($right['updatedAt'] ?? ''), (string) ($left['updatedAt'] ?? ''));
        if ($updatedComparison !== 0) {
            return $updatedComparison;
        }

        return (int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0);
    });

    return $items;
}

function notes_curriculum_unit_summary_payload(array $unit, array $items, string $cohort): array
{
    $sortedItems = notes_curriculum_sort_items($items);
    $itemCount = count($sortedItems);
    $previewTitles = array_values(array_slice(array_map(
        static function (array $item): string {
            return (string) ($item['title'] ?? '');
        },
        $sortedItems
    ), 0, 3));
    $storageTerms = array_values(array_unique(array_map(
        static function (array $item): int {
            return max(0, (int) ($item['storageTerm'] ?? 0));
        },
        $sortedItems
    )));

    return [
        'key' => (string) ($unit['key'] ?? ''),
        'title' => (string) ($unit['title'] ?? ''),
        'aliases' => is_array($unit['aliases'] ?? null) ? array_values($unit['aliases']) : [],
        'termNumber' => max(0, (int) ($unit['termNumber'] ?? 0)),
        'termLabel' => (string) ($unit['termLabel'] ?? ''),
        'categoryKey' => (string) ($unit['categoryKey'] ?? ''),
        'categoryTitle' => (string) ($unit['categoryTitle'] ?? ''),
        'statusKey' => $itemCount > 0 ? 'available' : 'empty',
        'statusLabel' => $itemCount > 0 ? 'دارای منبع' : 'بدون منبع',
        'itemCount' => $itemCount,
        'storageTerms' => $storageTerms,
        'previewTitles' => $previewTitles,
        'description' => $itemCount > 0
            ? ('در حال حاضر ' . dent_to_fa_digits((string) $itemCount) . ' منبع برای این واحد ثبت شده است.')
            : 'هنوز منبعی برای این واحد ثبت نشده است.',
        'finalExams' => dent_dentistry_curriculum_final_exams($unit, $cohort),
    ];
}

function notes_curriculum_uncategorized_unit_summary_payload(int $termNumber, array $items): array
{
    $sortedItems = notes_curriculum_sort_items($items);
    $itemCount = count($sortedItems);
    $termLabel = 'ترم ' . dent_to_fa_digits((string) $termNumber);

    return [
        'key' => notes_curriculum_build_uncategorized_unit_key($termNumber),
        'title' => 'آرشیو دسته‌بندی‌نشده',
        'aliases' => [],
        'termNumber' => $termNumber,
        'termLabel' => $termLabel,
        'categoryKey' => 'archive',
        'categoryTitle' => 'آرشیو دسته‌بندی‌نشده',
        'statusKey' => $itemCount > 0 ? 'available' : 'empty',
        'statusLabel' => $itemCount > 0 ? 'دارای منبع' : 'بدون منبع',
        'itemCount' => $itemCount,
        'storageTerms' => [$termNumber],
        'previewTitles' => array_values(array_slice(array_map(
            static function (array $item): string {
                return (string) ($item['title'] ?? '');
            },
            $sortedItems
        ), 0, 3)),
        'description' => 'این منابع هنوز به واحد مشخصی متصل نشده‌اند.',
    ];
}

function notes_term_payload_for_cohort(string $cohort, array $store, int $term): array
{
    if ($cohort === '1403') {
        return notes_1403_term_payload($store, $term);
    }
    if ($cohort === '1404') {
        return notes_1404_term_payload($store, $term);
    }

    return notes_1402_term_payload($store, $term);
}

function notes_curriculum_store_for_cohort(string $cohort): array
{
    if ($cohort === '1403') {
        return notes_1403_read_store();
    }
    if ($cohort === '1404') {
        return notes_1404_read_store();
    }
    if ($cohort === 'prosthesis-1402') {
        return notes_prosthesis_1402_read_store();
    }

    return notes_1402_read_store();
}

function notes_curriculum_extra_terms_payload(string $cohort, array $store): array
{
    $primaryLookup = array_fill_keys(notes_curriculum_primary_term_numbers(), true);
    $termsSeed = is_array($store['terms'] ?? null) ? $store['terms'] : [];
    $extra = [];

    foreach ($termsSeed as $termKey => $termRecord) {
        if (!is_array($termRecord)) {
            continue;
        }

        $termNumber = max(0, (int) ($termRecord['term'] ?? $termKey));
        if ($termNumber <= 0 || isset($primaryLookup[$termNumber])) {
            continue;
        }

        $payload = notes_term_payload_for_cohort($cohort, $store, $termNumber);
        $itemCount = is_array($payload['items'] ?? null) ? count($payload['items']) : 0;
        if ($itemCount <= 0) {
            continue;
        }

        $payload['kind'] = 'legacy-term';
        $payload['itemCount'] = $itemCount;
        $extra[] = $payload;
    }

    usort($extra, static function (array $left, array $right): int {
        return max(0, (int) ($left['term'] ?? 0)) <=> max(0, (int) ($right['term'] ?? 0));
    });

    return $extra;
}

function notes_curriculum_payload(string $cohort, array $store): array
{
    $itemsByUnit = [];
    $uncategorizedByTerm = [];
    $primaryLookup = array_fill_keys(notes_curriculum_primary_term_numbers(), true);
    $termsSeed = is_array($store['terms'] ?? null) ? $store['terms'] : [];

    foreach ($termsSeed as $termKey => $termRecord) {
        if (!is_array($termRecord)) {
            continue;
        }

        $storageTerm = max(0, (int) ($termRecord['term'] ?? $termKey));
        $items = is_array($termRecord['items'] ?? null) ? $termRecord['items'] : [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $payload = notes_curriculum_item_payload($item, $storageTerm);
            $curriculum = is_array($payload['curriculum'] ?? null) ? $payload['curriculum'] : null;
            if (is_array($curriculum) && isset($primaryLookup[(int) ($curriculum['termNumber'] ?? 0)])) {
                $unitKey = (string) ($curriculum['unitKey'] ?? '');
                if ($unitKey !== '') {
                    $itemsByUnit[$unitKey][] = $payload;
                    continue;
                }
            }

            if (isset($primaryLookup[$storageTerm])) {
                $uncategorizedByTerm[$storageTerm][] = $payload;
            }
        }
    }

    $terms = [];
    $catalogStats = [
        'termCount' => 0,
        'availableTermCount' => 0,
        'unitCount' => 0,
        'availableUnitCount' => 0,
        'itemCount' => 0,
    ];

    foreach (dent_dentistry_curriculum_terms() as $term) {
        if (!is_array($term)) {
            continue;
        }

        $termNumber = max(0, (int) ($term['number'] ?? 0));
        $termCategories = [];
        $termStats = [
            'unitCount' => 0,
            'availableUnitCount' => 0,
            'itemCount' => 0,
        ];
        $previewUnits = [];

        foreach ((is_array($term['categories'] ?? null) ? $term['categories'] : []) as $category) {
            if (!is_array($category)) {
                continue;
            }

            $units = [];
            $categoryStats = [
                'unitCount' => 0,
                'availableUnitCount' => 0,
                'itemCount' => 0,
            ];

            foreach ((is_array($category['units'] ?? null) ? $category['units'] : []) as $unit) {
                if (!is_array($unit)) {
                    continue;
                }

                $unitKey = trim(strtolower((string) ($unit['key'] ?? '')));
                if ($unitKey === '') {
                    continue;
                }

                $normalizedUnit = dent_dentistry_curriculum_find_unit($unitKey);
                if (!is_array($normalizedUnit)) {
                    continue;
                }

                $summary = notes_curriculum_unit_summary_payload(
                    $normalizedUnit,
                    is_array($itemsByUnit[$unitKey] ?? null) ? $itemsByUnit[$unitKey] : [],
                    $cohort
                );
                $units[] = $summary;

                $categoryStats['unitCount']++;
                $termStats['unitCount']++;
                $catalogStats['unitCount']++;
                if ($summary['itemCount'] > 0) {
                    $categoryStats['availableUnitCount']++;
                    $termStats['availableUnitCount']++;
                    $catalogStats['availableUnitCount']++;
                    $previewUnits[] = $summary['title'];
                }
                $categoryStats['itemCount'] += max(0, (int) ($summary['itemCount'] ?? 0));
                $termStats['itemCount'] += max(0, (int) ($summary['itemCount'] ?? 0));
                $catalogStats['itemCount'] += max(0, (int) ($summary['itemCount'] ?? 0));
            }

            $termCategories[] = [
                'key' => (string) ($category['key'] ?? ''),
                'title' => (string) ($category['title'] ?? ''),
                'stats' => $categoryStats,
                'units' => $units,
            ];
        }

        if (is_array($uncategorizedByTerm[$termNumber] ?? null) && $uncategorizedByTerm[$termNumber] !== []) {
            $archiveSummary = notes_curriculum_uncategorized_unit_summary_payload(
                $termNumber,
                $uncategorizedByTerm[$termNumber]
            );
            $termCategories[] = [
                'key' => 'archive',
                'title' => 'آرشیو دسته‌بندی‌نشده',
                'stats' => [
                    'unitCount' => 1,
                    'availableUnitCount' => $archiveSummary['itemCount'] > 0 ? 1 : 0,
                    'itemCount' => max(0, (int) $archiveSummary['itemCount']),
                ],
                'units' => [$archiveSummary],
            ];

            $termStats['unitCount']++;
            $catalogStats['unitCount']++;
            if ($archiveSummary['itemCount'] > 0) {
                $termStats['availableUnitCount']++;
                $catalogStats['availableUnitCount']++;
                $previewUnits[] = $archiveSummary['title'];
            }
            $termStats['itemCount'] += max(0, (int) $archiveSummary['itemCount']);
            $catalogStats['itemCount'] += max(0, (int) $archiveSummary['itemCount']);
        }

        $catalogStats['termCount']++;
        if ($termStats['availableUnitCount'] > 0) {
            $catalogStats['availableTermCount']++;
        }

        $terms[] = [
            'number' => $termNumber,
            'label' => (string) ($term['label'] ?? ''),
            'stats' => $termStats,
            'previewUnits' => array_values(array_slice($previewUnits, 0, 4)),
            'categories' => $termCategories,
        ];
    }

    return [
        'title' => 'منابع بر اساس ترم و واحد',
        'description' => 'ابتدا ترم را انتخاب کن، بعد از داخل دسته‌ها وارد واحد هر درس شو.',
        'stats' => $catalogStats,
        'terms' => $terms,
        'extraTerms' => notes_curriculum_extra_terms_payload($cohort, $store),
    ];
}

function notes_curriculum_unit_term_payload(string $cohort, int $termNumber, string $unitKey, array $store): array
{
    $cleanUnitKey = trim(strtolower($unitKey));
    if ($cleanUnitKey === '') {
        dent_error('واحد انتخاب‌شده معتبر نیست.', 422);
    }

    $items = [];
    $termLabel = 'ترم ' . dent_to_fa_digits((string) $termNumber);
    $categoryKey = 'archive';
    $categoryTitle = 'آرشیو دسته‌بندی‌نشده';
    $unitTitle = 'آرشیو دسته‌بندی‌نشده';
    $finalExams = [];

    if (notes_curriculum_is_uncategorized_unit_key($cleanUnitKey)) {
        $uncategorizedTerm = notes_curriculum_uncategorized_term_from_key($cleanUnitKey);
        if ($uncategorizedTerm !== $termNumber) {
            dent_error('واحد انتخاب‌شده برای این ترم معتبر نیست.', 404);
        }

        $termRecord = is_array($store['terms'][(string) $termNumber] ?? null) ? $store['terms'][(string) $termNumber] : [];
        foreach ((is_array($termRecord['items'] ?? null) ? $termRecord['items'] : []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $payload = notes_curriculum_item_payload($item, $termNumber);
            if (is_array($payload['curriculum'] ?? null)) {
                continue;
            }
            $items[] = $payload;
        }
    } else {
        $unit = dent_dentistry_curriculum_find_unit($cleanUnitKey);
        if (!is_array($unit) || max(0, (int) ($unit['termNumber'] ?? 0)) !== $termNumber) {
            dent_error('واحد انتخاب‌شده برای این ترم پیدا نشد.', 404);
        }

        $termLabel = (string) ($unit['termLabel'] ?? $termLabel);
        $categoryKey = (string) ($unit['categoryKey'] ?? '');
        $categoryTitle = (string) ($unit['categoryTitle'] ?? '');
        $unitTitle = (string) ($unit['title'] ?? '');
        $finalExams = dent_dentistry_curriculum_final_exams($unit, $cohort);

        $termsSeed = is_array($store['terms'] ?? null) ? $store['terms'] : [];
        foreach ($termsSeed as $storageTermKey => $termRecord) {
            if (!is_array($termRecord)) {
                continue;
            }

            $storageTerm = max(0, (int) ($termRecord['term'] ?? $storageTermKey));
            foreach ((is_array($termRecord['items'] ?? null) ? $termRecord['items'] : []) as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $payload = notes_curriculum_item_payload($item, $storageTerm);
                $curriculum = is_array($payload['curriculum'] ?? null) ? $payload['curriculum'] : null;
                if (!is_array($curriculum) || (string) ($curriculum['unitKey'] ?? '') !== $cleanUnitKey) {
                    continue;
                }
                $items[] = $payload;
            }
        }
    }

    $sortedItems = notes_curriculum_sort_items($items);
    $sourceTerms = array_values(array_unique(array_map(
        static function (array $item): int {
            return max(0, (int) ($item['storageTerm'] ?? 0));
        },
        $sortedItems
    )));

    return [
        'cohort' => $cohort,
        'term' => $termNumber,
        'id' => $termNumber,
        'kicker' => $termLabel,
        'title' => $unitTitle,
        'description' => $sortedItems === []
            ? 'هنوز منبعی برای این واحد ثبت نشده است.'
            : ('در حال حاضر ' . dent_to_fa_digits((string) count($sortedItems)) . ' منبع برای این واحد در دسترس است.'),
        'emptyMessage' => 'برای این واحد هنوز منبعی ثبت نشده است.',
        'items' => $sortedItems,
        'mode' => 'curriculum-unit',
        'unitKey' => $cleanUnitKey,
        'unitTitle' => $unitTitle,
        'termNumber' => $termNumber,
        'termLabel' => $termLabel,
        'categoryKey' => $categoryKey,
        'categoryTitle' => $categoryTitle,
        'finalExams' => $finalExams,
        'stats' => [
            'itemCount' => count($sortedItems),
            'sourceTerms' => $sourceTerms,
        ],
    ];
}

function notes_prosthesis_1402_term_payload(array $termRecord): array
{
    $items = is_array($termRecord['items'] ?? null) ? $termRecord['items'] : [];
    $termId = (int) ($termRecord['id'] ?? 0);
    $itemPayloads = notes_term_item_payloads($items, $termId);

    return [
        'cohort' => 'prosthesis-1402',
        'term' => $termId,
        'id' => $termId,
        'kicker' => (string) ($termRecord['kicker'] ?? ''),
        'title' => (string) ($termRecord['title'] ?? ''),
        'description' => (string) ($termRecord['description'] ?? ''),
        'emptyMessage' => (string) ($termRecord['emptyMessage'] ?? ''),
        'items' => $itemPayloads,
        'createdAt' => (string) ($termRecord['createdAt'] ?? ''),
        'updatedAt' => (string) ($termRecord['updatedAt'] ?? ''),
    ];
}

function notes_prosthesis_1402_terms_payload(array $store): array
{
    $terms = [];
    foreach (($store['terms'] ?? []) as $termRecord) {
        if (is_array($termRecord)) {
            $terms[] = notes_prosthesis_1402_term_payload($termRecord);
        }
    }

    usort($terms, static function (array $left, array $right): int {
        return (int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0);
    });

    return $terms;
}

function notes_can_manage_cohort(string $cohort, ?array $viewer): bool
{
    if (!is_array($viewer)) {
        return false;
    }

    $role = (string) ($viewer['role'] ?? 'student');
    if ($role === 'owner') {
        return true;
    }

    $targetCohortKey = $cohort === '1402'
        ? dent_primary_cohort_key()
        : ($cohort === 'prosthesis-1402' ? dent_prosthesis_legacy_cohort_key() : ('dentistry-' . $cohort));

    if (dent_user_cohort_key($viewer) !== dent_clean_cohort_key($targetCohortKey)) {
        return false;
    }

    return !empty(dent_permissions_for_role($role, $targetCohortKey)['manageNotes']);
}

function notes_require_manage_cohort(string $cohort): array
{
    $viewer = dent_require_user();
    if (!notes_can_manage_cohort($cohort, $viewer)) {
        dent_error('اجازه مدیریت این آرشیو را ندارید.', 403);
    }

    return $viewer;
}

function notes_1402_next_item_id(array &$store): int
{
    $next = max(1, (int) ($store['nextItemId'] ?? 1));
    $store['nextItemId'] = $next + 1;
    return $next;
}

function notes_prosthesis_1402_next_term_id(array &$store): int
{
    $next = max(1, (int) ($store['nextTermId'] ?? 1));
    $store['nextTermId'] = $next + 1;
    return $next;
}

function notes_1402_require_method(array $allowed): void
{
    $method = dent_request_method();
    if (!in_array($method, $allowed, true)) {
        dent_error('متد درخواست معتبر نیست.', 405);
    }
}

function notes_1402_parse_item_id($raw): int
{
    $itemId = (int) dent_normalize_digits((string) $raw);
    if ($itemId <= 0) {
        dent_error('شناسه کارت معتبر نیست.', 422);
    }

    return $itemId;
}

function notes_parse_item_fields_from_post(): array
{
    $badge = dent_clean_text((string) ($_POST['badge'] ?? ''), 70);
    $title = dent_clean_text((string) ($_POST['title'] ?? ''), 180);
    $description = dent_clean_text((string) ($_POST['description'] ?? ''), 600);
    $buttonLabel = dent_clean_text((string) ($_POST['buttonLabel'] ?? ''), 70);
    $buttonUrl = notes_1402_normalize_url((string) ($_POST['buttonUrl'] ?? ''));
    $unitKey = trim(strtolower((string) ($_POST['unitKey'] ?? '')));
    if ($unitKey !== '') {
        $unit = dent_dentistry_curriculum_find_unit($unitKey);
        if (!is_array($unit)) {
            dent_error('واحد انتخاب‌شده معتبر نیست.', 422);
        }
        $unitKey = (string) ($unit['key'] ?? '');
    }

    if ($badge === '' || $title === '' || $description === '' || $buttonLabel === '' || $buttonUrl === '') {
        dent_error('همه فیلدهای کارت باید کامل و معتبر باشند.', 422);
    }

    return [
        'badge' => $badge,
        'title' => $title,
        'description' => $description,
        'buttonLabel' => $buttonLabel,
        'buttonUrl' => $buttonUrl,
        'unitKey' => $unitKey,
    ];
}

function notes_download_host_scope_root_for_cohort(string $cohort): string
{
    if ($cohort === '1403' || $cohort === '1404') {
        return $cohort;
    }
    if ($cohort === 'prosthesis-1402') {
        return 'prosthesis-1402';
    }

    return '1402';
}

function notes_download_host_scope_for_viewer(string $cohort, array $viewer): ?string
{
    $role = (string) ($viewer['role'] ?? 'student');
    if ($role === 'owner') {
        return null;
    }

    return notes_download_host_scope_root_for_cohort($cohort);
}

function notes_download_host_manager_url(string $defaultPath, string $cohort): string
{
    $query = [];
    if ($defaultPath !== '') {
        $query['path'] = $defaultPath;
    }
    if ($cohort !== '') {
        $query['cohort'] = $cohort;
    }

    return '/notes/files/' . ($query === [] ? '' : ('?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986)));
}

function notes_download_host_term_payload(string $cohort, int $term, array $termPayload, ?array $viewer): array
{
    $isEnabled = notes_download_host_is_enabled();
    $canManage = notes_can_manage_cohort($cohort, $viewer);
    $role = is_array($viewer) ? (string) ($viewer['role'] ?? 'student') : 'guest';
    $defaultRelativeDir = notes_download_host_default_relative_dir(
        $cohort,
        $term,
        (string) ($termPayload['title'] ?? ''),
        $termPayload
    );

    return [
        'enabled' => $isEnabled,
        'publicBaseUrl' => notes_download_host_public_base_url(),
        'defaultRelativeDir' => $defaultRelativeDir,
        'scopeRoot' => notes_download_host_scope_root_for_cohort($cohort),
        'canUpload' => $canManage && $isEnabled,
        'canManageAllRoots' => $role === 'owner' && $isEnabled,
        'managerUrl' => notes_download_host_manager_url($defaultRelativeDir, $cohort),
    ];
}

function notes_download_host_default_relative_dir_from_request(string $cohort, int $term, array $params, array $store): string
{
    $requestedUnitKey = trim(strtolower((string) ($params['unitKey'] ?? $params['unit'] ?? '')));
    if ($requestedUnitKey !== '' && notes_is_curriculum_cohort($cohort)) {
        $termPayload = notes_curriculum_unit_term_payload($cohort, $term, $requestedUnitKey, $store);
        return notes_download_host_default_relative_dir(
            $cohort,
            $term,
            (string) ($termPayload['title'] ?? ''),
            $termPayload
        );
    }

    $termTitle = trim((string) ($params['termTitle'] ?? ''));
    if ($termTitle === '') {
        $termPayload = notes_term_payload_for_cohort($cohort, $store, $term);
        $termTitle = (string) ($termPayload['title'] ?? '');
    }
    return notes_download_host_default_relative_dir($cohort, $term, $termTitle);
}

function notes_parse_prosthesis_term_fields_from_post(): array
{
    $title = dent_clean_text((string) ($_POST['title'] ?? ''), 160);
    $kicker = dent_clean_text((string) ($_POST['kicker'] ?? ''), 80);
    $description = dent_clean_text((string) ($_POST['description'] ?? ''), 800);
    $emptyMessage = dent_clean_text((string) ($_POST['emptyMessage'] ?? ''), 400);

    if ($title === '') {
        dent_error('عنوان ترم پروتز الزامی است.', 422);
    }

    return [
        'title' => $title,
        'kicker' => $kicker !== '' ? $kicker : 'پروتز ۱۴۰۲',
        'description' => $description !== '' ? $description : 'منابع این ترم به‌مرور اضافه می‌شوند.',
        'emptyMessage' => $emptyMessage !== '' ? $emptyMessage : 'برای این ترم هنوز منبعی ثبت نشده است.',
    ];
}

function notes_new_item(array &$store, array $fields): array
{
    return array_merge($fields, [
        'id' => notes_1402_next_item_id($store),
        'createdAt' => dent_iso_now(),
        'updatedAt' => dent_iso_now(),
        'version' => 1,
        'versionHistory' => [],
    ]);
}

function notes_update_item_record(array $current, array $fields): array
{
    $next = array_merge($current, $fields);
    $now = dent_iso_now();
    $changed = notes_item_edit_signature($current) !== notes_item_edit_signature($next);
    $history = notes_normalize_item_version_history($current['versionHistory'] ?? []);
    $version = max(1, (int) ($current['version'] ?? 1));

    if ($changed) {
        array_unshift($history, notes_item_version_snapshot($current, $now));
        $history = array_slice(notes_normalize_item_version_history($history), 0, NOTES_RESOURCE_HISTORY_LIMIT);
        $version++;
    }

    return array_merge($next, [
        'updatedAt' => $now,
        'version' => $version,
        'versionHistory' => $history,
    ]);
}

function notes_1402_add_item(int $term, array $fields): array
{
    return notes_1402_with_store_lock(static function (array &$store) use ($term, $fields): array {
        $termKey = (string) $term;
        if (!isset($store['terms'][$termKey]) || !is_array($store['terms'][$termKey])) {
            $store['terms'][$termKey] = notes_1402_term_template($term);
            $store['terms'][$termKey]['items'] = [];
        }

        if (!is_array($store['terms'][$termKey]['items'] ?? null)) {
            $store['terms'][$termKey]['items'] = [];
        }

        $item = notes_new_item($store, $fields);
        array_unshift($store['terms'][$termKey]['items'], $item);
        return $item;
    });
}

function notes_fixed_terms_add_item(string $cohort, int $term, array $fields): array
{
    $withLock = $cohort === '1403' ? 'notes_1403_with_store_lock' : 'notes_1404_with_store_lock';
    $template = $cohort === '1403' ? 'notes_1403_term_template' : 'notes_1404_term_template';

    return $withLock(static function (array &$store) use ($term, $fields, $template): array {
        $termKey = (string) $term;
        if (!isset($store['terms'][$termKey]) || !is_array($store['terms'][$termKey])) {
            $store['terms'][$termKey] = $template($term);
            $store['terms'][$termKey]['items'] = [];
        }
        if (!is_array($store['terms'][$termKey]['items'] ?? null)) {
            $store['terms'][$termKey]['items'] = [];
        }

        $item = notes_new_item($store, $fields);
        array_unshift($store['terms'][$termKey]['items'], $item);
        return $item;
    });
}

function notes_1403_add_item(int $term, array $fields): array
{
    return notes_fixed_terms_add_item('1403', $term, $fields);
}

function notes_1404_add_item(int $term, array $fields): array
{
    return notes_fixed_terms_add_item('1404', $term, $fields);
}

function notes_prosthesis_1402_add_term(array $fields): array
{
    return notes_prosthesis_1402_with_store_lock(static function (array &$store) use ($fields): array {
        $termId = notes_prosthesis_1402_next_term_id($store);
        $term = array_merge($fields, [
            'id' => $termId,
            'items' => [],
            'createdAt' => dent_iso_now(),
            'updatedAt' => dent_iso_now(),
        ]);
        $store['terms'][(string) $termId] = $term;
        return $term;
    });
}

function notes_prosthesis_1402_edit_term(int $termId, array $fields): array
{
    return notes_prosthesis_1402_with_store_lock(static function (array &$store) use ($termId, $fields): array {
        $termKey = (string) $termId;
        if (!is_array($store['terms'][$termKey] ?? null)) {
            throw new RuntimeException('term-not-found');
        }

        $store['terms'][$termKey] = array_merge($store['terms'][$termKey], $fields, [
            'id' => $termId,
            'updatedAt' => dent_iso_now(),
        ]);
        return $store['terms'][$termKey];
    });
}

function notes_prosthesis_1402_delete_term(int $termId): array
{
    return notes_prosthesis_1402_with_store_lock(static function (array &$store) use ($termId): array {
        $termKey = (string) $termId;
        if (!is_array($store['terms'][$termKey] ?? null)) {
            throw new RuntimeException('term-not-found');
        }

        $deleted = $store['terms'][$termKey];
        unset($store['terms'][$termKey]);
        return $deleted;
    });
}

function notes_prosthesis_1402_add_item(int $termId, array $fields): array
{
    return notes_prosthesis_1402_with_store_lock(static function (array &$store) use ($termId, $fields): array {
        $termKey = (string) $termId;
        if (!is_array($store['terms'][$termKey] ?? null)) {
            throw new RuntimeException('term-not-found');
        }
        if (!is_array($store['terms'][$termKey]['items'] ?? null)) {
            $store['terms'][$termKey]['items'] = [];
        }

        $item = notes_new_item($store, $fields);
        array_unshift($store['terms'][$termKey]['items'], $item);
        $store['terms'][$termKey]['updatedAt'] = dent_iso_now();
        return $item;
    });
}

function notes_1402_edit_item(int $term, int $itemId, array $fields): array
{
    return notes_1402_with_store_lock(static function (array &$store) use ($term, $itemId, $fields): array {
        $termKey = (string) $term;
        if (!is_array($store['terms'][$termKey]['items'] ?? null)) {
            throw new RuntimeException('item-not-found');
        }

        foreach ($store['terms'][$termKey]['items'] as $index => $item) {
            if ((int) ($item['id'] ?? 0) !== $itemId) {
                continue;
            }

            $updated = notes_update_item_record(is_array($item) ? $item : [], $fields);
            $store['terms'][$termKey]['items'][$index] = $updated;
            return $updated;
        }

        throw new RuntimeException('item-not-found');
    });
}

function notes_fixed_terms_edit_item(string $cohort, int $term, int $itemId, array $fields): array
{
    $withLock = $cohort === '1403' ? 'notes_1403_with_store_lock' : 'notes_1404_with_store_lock';

    return $withLock(static function (array &$store) use ($term, $itemId, $fields): array {
        $termKey = (string) $term;
        if (!is_array($store['terms'][$termKey]['items'] ?? null)) {
            throw new RuntimeException('item-not-found');
        }

        foreach ($store['terms'][$termKey]['items'] as $index => $item) {
            if ((int) ($item['id'] ?? 0) !== $itemId) {
                continue;
            }

            $updated = notes_update_item_record(is_array($item) ? $item : [], $fields);
            $store['terms'][$termKey]['items'][$index] = $updated;
            return $updated;
        }

        throw new RuntimeException('item-not-found');
    });
}

function notes_1403_edit_item(int $term, int $itemId, array $fields): array
{
    return notes_fixed_terms_edit_item('1403', $term, $itemId, $fields);
}

function notes_1404_edit_item(int $term, int $itemId, array $fields): array
{
    return notes_fixed_terms_edit_item('1404', $term, $itemId, $fields);
}

function notes_prosthesis_1402_edit_item(int $termId, int $itemId, array $fields): array
{
    return notes_prosthesis_1402_with_store_lock(static function (array &$store) use ($termId, $itemId, $fields): array {
        $termKey = (string) $termId;
        if (!is_array($store['terms'][$termKey]['items'] ?? null)) {
            throw new RuntimeException('item-not-found');
        }

        foreach ($store['terms'][$termKey]['items'] as $index => $item) {
            if ((int) ($item['id'] ?? 0) !== $itemId) {
                continue;
            }

            $updated = notes_update_item_record(is_array($item) ? $item : [], $fields);
            $store['terms'][$termKey]['items'][$index] = $updated;
            $store['terms'][$termKey]['updatedAt'] = dent_iso_now();
            return $updated;
        }

        throw new RuntimeException('item-not-found');
    });
}

function notes_1402_delete_item(int $term, int $itemId): array
{
    return notes_1402_with_store_lock(static function (array &$store) use ($term, $itemId): array {
        $termKey = (string) $term;
        if (!is_array($store['terms'][$termKey]['items'] ?? null)) {
            throw new RuntimeException('item-not-found');
        }

        $items = &$store['terms'][$termKey]['items'];
        foreach ($items as $index => $item) {
            if ((int) ($item['id'] ?? 0) !== $itemId) {
                continue;
            }

            $deleted = is_array($item) ? $item : [];
            array_splice($items, $index, 1);
            return $deleted;
        }

        throw new RuntimeException('item-not-found');
    });
}

function notes_fixed_terms_delete_item(string $cohort, int $term, int $itemId): array
{
    $withLock = $cohort === '1403' ? 'notes_1403_with_store_lock' : 'notes_1404_with_store_lock';

    return $withLock(static function (array &$store) use ($term, $itemId): array {
        $termKey = (string) $term;
        if (!is_array($store['terms'][$termKey]['items'] ?? null)) {
            throw new RuntimeException('item-not-found');
        }

        $items = &$store['terms'][$termKey]['items'];
        foreach ($items as $index => $item) {
            if ((int) ($item['id'] ?? 0) !== $itemId) {
                continue;
            }

            $deleted = is_array($item) ? $item : [];
            array_splice($items, $index, 1);
            return $deleted;
        }

        throw new RuntimeException('item-not-found');
    });
}

function notes_1403_delete_item(int $term, int $itemId): array
{
    return notes_fixed_terms_delete_item('1403', $term, $itemId);
}

function notes_1404_delete_item(int $term, int $itemId): array
{
    return notes_fixed_terms_delete_item('1404', $term, $itemId);
}

function notes_prosthesis_1402_delete_item(int $termId, int $itemId): array
{
    return notes_prosthesis_1402_with_store_lock(static function (array &$store) use ($termId, $itemId): array {
        $termKey = (string) $termId;
        if (!is_array($store['terms'][$termKey]['items'] ?? null)) {
            throw new RuntimeException('item-not-found');
        }

        $items = &$store['terms'][$termKey]['items'];
        foreach ($items as $index => $item) {
            if ((int) ($item['id'] ?? 0) !== $itemId) {
                continue;
            }

            $deleted = is_array($item) ? $item : [];
            array_splice($items, $index, 1);
            $store['terms'][$termKey]['updatedAt'] = dent_iso_now();
            return $deleted;
        }

        throw new RuntimeException('item-not-found');
    });
}

function notes_item_response_payload(string $cohort, array $item, int $storageTerm): array
{
    $payload = null;
    if ($cohort === 'prosthesis-1402') {
        $payload = notes_1402_item_payload($item);
    } else {
        $payload = notes_curriculum_item_payload($item, $storageTerm);
    }

    return notes_item_with_offline_pack_url($cohort, $payload, $storageTerm);
}

function notes_with_store_lock_for_cohort(string $cohort, callable $callback)
{
    if ($cohort === '1403') {
        return notes_1403_with_store_lock($callback);
    }
    if ($cohort === '1404') {
        return notes_1404_with_store_lock($callback);
    }
    if ($cohort === 'prosthesis-1402') {
        return notes_prosthesis_1402_with_store_lock($callback);
    }

    return notes_1402_with_store_lock($callback);
}

function notes_resource_next_request_id(array &$store): int
{
    $next = max(1, (int) ($store['nextResourceRequestId'] ?? 1));
    $store['nextResourceRequestId'] = $next + 1;
    return $next;
}

function notes_resource_next_report_id(array &$store): int
{
    $next = max(1, (int) ($store['nextResourceLinkReportId'] ?? 1));
    $store['nextResourceLinkReportId'] = $next + 1;
    return $next;
}

function notes_resource_find_item_context(string $cohort, array $store, int $itemId, int $preferredTerm = 0): ?array
{
    $terms = is_array($store['terms'] ?? null) ? $store['terms'] : [];
    foreach ($terms as $termKey => $termRecord) {
        if (!is_array($termRecord)) {
            continue;
        }

        $termNumber = max(0, (int) ($termRecord['term'] ?? $termRecord['id'] ?? $termKey));
        if ($preferredTerm > 0 && $termNumber !== $preferredTerm) {
            continue;
        }

        $items = is_array($termRecord['items'] ?? null) ? $termRecord['items'] : [];
        foreach ($items as $item) {
            if (!is_array($item) || (int) ($item['id'] ?? 0) !== $itemId) {
                continue;
            }

            return [
                'term' => $termNumber,
                'termTitle' => (string) ($termRecord['title'] ?? ''),
                'item' => $item,
                'unitKey' => (string) ($item['unitKey'] ?? ''),
                'cohort' => $cohort,
            ];
        }
    }

    return null;
}

function notes_resource_view_url(string $cohort, int $term, array $item): string
{
    $params = ['term' => (string) $term];
    $unitKey = trim(strtolower((string) ($item['unitKey'] ?? '')));
    if ($unitKey !== '' && notes_is_curriculum_cohort($cohort)) {
        $params['unit'] = $unitKey;
    }
    if ($cohort !== '1402') {
        $params['cohort'] = $cohort;
    }

    $base = $unitKey !== '' && notes_is_curriculum_cohort($cohort) ? '/notes/' : '/notes/term/';
    if ($unitKey !== '' && $cohort === '1403') {
        $base = '/notes/1403/';
    } elseif ($unitKey !== '' && $cohort === '1404') {
        $base = '/notes/1404/';
    }

    return $base . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function notes_resource_item_context_payload(string $cohort, array $context): array
{
    $term = max(0, (int) ($context['term'] ?? 0));
    $item = is_array($context['item'] ?? null) ? $context['item'] : [];
    $payload = notes_item_response_payload($cohort, $item, $term);
    $payload['term'] = $term;
    $payload['termTitle'] = (string) ($context['termTitle'] ?? '');
    $payload['viewUrl'] = notes_resource_view_url($cohort, $term, $item);
    return $payload;
}

function notes_resource_proxy_public_origin(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'dentistry1402tums.ir'));
    if ($host === '' || preg_match('/[^a-zA-Z0-9.:-]/', $host) === 1) {
        $host = 'dentistry1402tums.ir';
    }
    $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    return $scheme . '://' . $host;
}

function notes_resource_proxy_source_url(string $rawUrl): string
{
    $url = notes_1402_normalize_url($rawUrl);
    if ($url === '') {
        dent_error('لینک منبع برای ذخیره آفلاین معتبر نیست.', 422);
    }
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
        return notes_resource_proxy_public_origin() . $url;
    }

    $parts = @parse_url($url);
    if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
        dent_error('لینک منبع برای ذخیره آفلاین معتبر نیست.', 422);
    }

    $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
    if ($host === '' || $host === 'localhost' || $host === 'localdomain' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
        dent_error('این لینک برای ذخیره آفلاین مجاز نیست.', 422);
    }
    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        $publicIp = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        if ($publicIp === false) {
            dent_error('این لینک برای ذخیره آفلاین مجاز نیست.', 422);
        }
    }

    return $url;
}

function notes_resource_proxy_filename(array $item, string $sourceUrl): string
{
    $title = dent_clean_text((string) ($item['title'] ?? ''), 160);
    if ($title === '') {
        $title = 'resource';
    }
    $path = (string) (parse_url($sourceUrl, PHP_URL_PATH) ?: '');
    $extension = strtolower((string) pathinfo(rawurldecode($path), PATHINFO_EXTENSION));
    if ($extension !== '' && preg_match('/^[a-z0-9]{1,12}$/i', $extension) === 1 && !preg_match('/\.' . preg_quote($extension, '/') . '$/i', $title)) {
        $title .= '.' . $extension;
    }

    $clean = preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', '-', $title);
    if (!is_string($clean)) {
        $clean = $title;
    }
    $clean = trim($clean, " .-\t\n\r\0\x0B");
    return $clean !== '' ? $clean : 'resource';
}

function notes_stream_offline_resource(string $sourceUrl, array $item): void
{
    @set_time_limit(0);
    $http_response_header = [];
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'ignore_errors' => true,
            'follow_location' => 1,
            'max_redirects' => 5,
            'timeout' => 60,
            'header' => "Accept: */*\r\nUser-Agent: Dentistry1402TUMS-PWA/1.0\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $stream = @fopen($sourceUrl, 'rb', false, $context);
    if (!is_resource($stream)) {
        dent_error('دریافت منبع برای ذخیره آفلاین انجام نشد.', 502);
    }

    $status = 200;
    $contentType = 'application/octet-stream';
    $contentLength = '';
    foreach (($http_response_header ?? []) as $line) {
        $headerLine = trim((string) $line);
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/i', $headerLine, $matches) === 1) {
            $status = (int) $matches[1];
            $contentType = 'application/octet-stream';
            $contentLength = '';
            continue;
        }
        if (stripos($headerLine, 'Content-Type:') === 0) {
            $contentType = trim(substr($headerLine, strlen('Content-Type:'))) ?: $contentType;
        } elseif (stripos($headerLine, 'Content-Length:') === 0) {
            $length = trim(substr($headerLine, strlen('Content-Length:')));
            $contentLength = ctype_digit($length) ? $length : '';
        }
    }
    if ($status >= 400) {
        fclose($stream);
        dent_error('منبع اصلی برای ذخیره آفلاین در دسترس نیست.', $status === 404 ? 404 : 502);
    }

    if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
        @session_write_close();
    }
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    if (!headers_sent()) {
        header('Content-Type: ' . $contentType);
        header('Cache-Control: private, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        if ($contentLength !== '') {
            header('Content-Length: ' . $contentLength);
        }
        header("Content-Disposition: inline; filename*=UTF-8''" . rawurlencode(notes_resource_proxy_filename($item, $sourceUrl)));
    }

    fpassthru($stream);
    fclose($stream);
    exit;
}

function notes_resource_hydrate_user_entries(string $cohort, array $store, array $entries): array
{
    $payloads = [];
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $itemId = max(0, (int) ($entry['itemId'] ?? 0));
        if ($itemId <= 0) {
            continue;
        }

        $preferredTerm = max(0, (int) ($entry['term'] ?? 0));
        $context = notes_resource_find_item_context($cohort, $store, $itemId, $preferredTerm);
        if ($context === null && $preferredTerm > 0) {
            $context = notes_resource_find_item_context($cohort, $store, $itemId, 0);
        }
        if ($context === null) {
            continue;
        }

        $payload = notes_resource_item_context_payload($cohort, $context);
        $payload['viewedAt'] = (string) ($entry['viewedAt'] ?? '');
        $payload['savedAt'] = (string) ($entry['updatedAt'] ?? '');
        $payloads[] = $payload;
    }

    return $payloads;
}

function notes_latest_updates_payload(string $cohort, array $store, int $limit = NOTES_RESOURCE_INSIGHT_LIMIT): array
{
    $updates = [];
    $terms = is_array($store['terms'] ?? null) ? $store['terms'] : [];
    foreach ($terms as $termKey => $termRecord) {
        if (!is_array($termRecord)) {
            continue;
        }
        $term = max(0, (int) ($termRecord['term'] ?? $termRecord['id'] ?? $termKey));
        $items = is_array($termRecord['items'] ?? null) ? $termRecord['items'] : [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $updates[] = notes_resource_item_context_payload($cohort, [
                'term' => $term,
                'termTitle' => (string) ($termRecord['title'] ?? ''),
                'item' => $item,
            ]);
        }
    }

    usort($updates, static function (array $left, array $right): int {
        return strcmp((string) ($right['updatedAt'] ?? ''), (string) ($left['updatedAt'] ?? ''));
    });

    return array_slice($updates, 0, max(1, $limit));
}

function notes_resource_open_issues(array $store, string $type, int $limit = NOTES_RESOURCE_INSIGHT_LIMIT): array
{
    $source = $type === 'request' ? ($store['resourceRequests'] ?? []) : ($store['resourceLinkReports'] ?? []);
    $items = [];
    foreach (is_array($source) ? $source : [] as $entry) {
        if (!is_array($entry) || (string) ($entry['status'] ?? 'open') !== 'open') {
            continue;
        }
        $entry['type'] = $type;
        $items[] = $entry;
    }
    usort($items, static function (array $left, array $right): int {
        return strcmp((string) ($right['createdAt'] ?? ''), (string) ($left['createdAt'] ?? ''));
    });

    return array_slice($items, 0, max(1, $limit));
}

function notes_resource_insights_payload(string $cohort, array $store, ?array $viewer): array
{
    $canManage = notes_can_manage_cohort($cohort, $viewer);
    $payload = [
        'authenticated' => $viewer !== null,
        'favoriteIds' => [],
        'favorites' => [],
        'recent' => [],
        'latestUpdates' => notes_latest_updates_payload($cohort, $store),
        'pending' => [
            'requests' => count(notes_resource_open_issues($store, 'request', 200)),
            'reports' => count(notes_resource_open_issues($store, 'report', 200)),
        ],
        'inbox' => null,
    ];

    if ($viewer !== null) {
        $userKey = notes_resource_user_key($viewer);
        $userState = is_array($store['resourceUserState'][$userKey] ?? null)
            ? $store['resourceUserState'][$userKey]
            : notes_normalize_resource_user_state([]);
        $favorites = is_array($userState['favorites'] ?? null) ? $userState['favorites'] : [];
        $payload['favoriteIds'] = array_values(array_map('intval', array_keys($favorites)));
        $payload['favorites'] = notes_resource_hydrate_user_entries($cohort, $store, array_values($favorites));
        $payload['recent'] = notes_resource_hydrate_user_entries($cohort, $store, is_array($userState['recent'] ?? null) ? $userState['recent'] : []);
    }

    if ($canManage) {
        $payload['inbox'] = [
            'requests' => notes_resource_open_issues($store, 'request'),
            'reports' => notes_resource_open_issues($store, 'report'),
        ];
    }

    return $payload;
}

function notes_resource_context_entry_from_item_context(array $context, string $now): array
{
    $item = is_array($context['item'] ?? null) ? $context['item'] : [];
    return [
        'itemId' => max(0, (int) ($item['id'] ?? 0)),
        'term' => max(0, (int) ($context['term'] ?? 0)),
        'unitKey' => trim(strtolower((string) ($item['unitKey'] ?? ''))),
        'updatedAt' => $now,
        'viewedAt' => $now,
    ];
}

function notes_parse_resource_issue_status_from_post(): string
{
    $status = notes_normalize_resource_issue_status($_POST['status'] ?? 'resolved');
    if ($status === 'open') {
        dent_error('وضعیت انتخابی معتبر نیست.', 422);
    }

    return $status;
}

$action = dent_request_action();

if ($action === 'terms') {
    notes_1402_require_method(['GET']);

    $cohort = notes_parse_cohort($_GET['cohort'] ?? '1402');
    $viewer = dent_current_user();
    $store = notes_curriculum_store_for_cohort($cohort);
    if ($cohort === 'prosthesis-1402') {
        $terms = notes_prosthesis_1402_terms_payload($store);
    } elseif ($cohort === '1403') {
        $terms = notes_1403_terms_payload($store);
    } elseif ($cohort === '1404') {
        $terms = notes_1404_terms_payload($store);
    } else {
        $terms = notes_1402_terms_payload($store);
    }
    dent_json_response([
        'success' => true,
        'terms' => $terms,
        'curriculum' => notes_is_curriculum_cohort($cohort) ? notes_curriculum_payload($cohort, $store) : null,
        'canManage' => notes_can_manage_cohort($cohort, $viewer),
        'resourceInsights' => notes_resource_insights_payload($cohort, $store, $viewer),
    ]);
}

if ($action === 'addTerm') {
    notes_1402_require_method(['POST']);
    $cohort = notes_parse_cohort($_POST['cohort'] ?? 'prosthesis-1402');
    if ($cohort !== 'prosthesis-1402') {
        dent_error('افزودن ترم فقط برای آرشیو پروتز فعال است.', 422);
    }
    notes_require_manage_cohort($cohort);

    $created = notes_prosthesis_1402_add_term(notes_parse_prosthesis_term_fields_from_post());
    dent_json_response([
        'success' => true,
        'term' => notes_prosthesis_1402_term_payload($created),
        'message' => 'ترم پروتز ثبت شد.',
    ]);
}

if ($action === 'editTerm') {
    notes_1402_require_method(['POST']);
    $cohort = notes_parse_cohort($_POST['cohort'] ?? 'prosthesis-1402');
    if ($cohort !== 'prosthesis-1402') {
        dent_error('ویرایش ترم فقط برای آرشیو پروتز فعال است.', 422);
    }
    notes_require_manage_cohort($cohort);
    $termId = notes_prosthesis_1402_parse_term_id($_POST['term'] ?? '');

    try {
        $updated = notes_prosthesis_1402_edit_term($termId, notes_parse_prosthesis_term_fields_from_post());
    } catch (RuntimeException $error) {
        if ($error->getMessage() === 'term-not-found') {
            dent_error('ترم موردنظر پیدا نشد.', 404);
        }
        throw $error;
    }

    dent_json_response([
        'success' => true,
        'term' => notes_prosthesis_1402_term_payload($updated),
        'message' => 'ترم پروتز ذخیره شد.',
    ]);
}

if ($action === 'deleteTerm') {
    notes_1402_require_method(['POST']);
    $cohort = notes_parse_cohort($_POST['cohort'] ?? 'prosthesis-1402');
    if ($cohort !== 'prosthesis-1402') {
        dent_error('حذف ترم فقط برای آرشیو پروتز فعال است.', 422);
    }
    notes_require_manage_cohort($cohort);
    $termId = notes_prosthesis_1402_parse_term_id($_POST['term'] ?? '');

    try {
        $deleted = notes_prosthesis_1402_delete_term($termId);
    } catch (RuntimeException $error) {
        if ($error->getMessage() === 'term-not-found') {
            dent_error('ترم موردنظر پیدا نشد.', 404);
        }
        throw $error;
    }

    dent_json_response([
        'success' => true,
        'term' => notes_prosthesis_1402_term_payload($deleted),
        'message' => 'ترم پروتز حذف شد.',
    ]);
}

if ($action === 'term') {
    notes_1402_require_method(['GET']);

    $cohort = notes_parse_cohort($_GET['cohort'] ?? '1402');
    $term = $cohort === 'prosthesis-1402'
        ? notes_prosthesis_1402_parse_term_id($_GET['term'] ?? '')
        : notes_require_term_for_cohort($cohort, $_GET['term'] ?? '');
    $viewer = dent_current_user();
    $store = notes_curriculum_store_for_cohort($cohort);
    $termPayload = null;
    $requestedUnitKey = trim(strtolower((string) ($_GET['unit'] ?? '')));

    if ($cohort === 'prosthesis-1402') {
        $termRecord = $store['terms'][(string) $term] ?? null;
        if (!is_array($termRecord)) {
            dent_error('ترم موردنظر پیدا نشد.', 404);
        }
        $termPayload = notes_prosthesis_1402_term_payload($termRecord);
    } elseif (notes_is_curriculum_cohort($cohort) && $requestedUnitKey !== '') {
        $termPayload = notes_curriculum_unit_term_payload($cohort, $term, $requestedUnitKey, $store);
    } else {
        $termPayload = notes_term_payload_for_cohort($cohort, $store, $term);
    }
    $termPayload = notes_attach_offline_pack_urls($cohort, $termPayload);

    dent_json_response([
        'success' => true,
        'term' => $termPayload,
        'canManage' => notes_can_manage_cohort($cohort, $viewer),
        'downloadHost' => notes_download_host_term_payload($cohort, $term, $termPayload, $viewer),
        'resourceInsights' => notes_resource_insights_payload($cohort, $store, $viewer),
    ]);
}

if ($action === 'offlineResourceProxy') {
    notes_1402_require_method(['GET']);

    $cohort = notes_parse_cohort($_GET['cohort'] ?? '1402');
    $term = $cohort === 'prosthesis-1402'
        ? notes_prosthesis_1402_parse_term_id($_GET['term'] ?? '')
        : notes_require_term_for_cohort($cohort, $_GET['term'] ?? '');
    $itemId = notes_1402_parse_item_id($_GET['itemId'] ?? '');
    $store = notes_curriculum_store_for_cohort($cohort);
    $context = notes_resource_find_item_context($cohort, $store, $itemId, $term);
    if ($context === null) {
        dent_error('منبع موردنظر پیدا نشد.', 404);
    }

    $item = is_array($context['item'] ?? null) ? $context['item'] : [];
    $sourceUrl = notes_resource_proxy_source_url((string) ($item['buttonUrl'] ?? ''));
    notes_stream_offline_resource($sourceUrl, $item);
}

if ($action === 'resourceState') {
    notes_1402_require_method(['GET']);
    $cohort = notes_parse_cohort($_GET['cohort'] ?? '1402');
    $viewer = dent_current_user();
    $store = notes_curriculum_store_for_cohort($cohort);
    dent_json_response([
        'success' => true,
        'canManage' => notes_can_manage_cohort($cohort, $viewer),
        'resourceInsights' => notes_resource_insights_payload($cohort, $store, $viewer),
    ]);
}

if ($action === 'trackResourceOpen') {
    notes_1402_require_method(['POST']);
    $cohort = notes_parse_cohort($_POST['cohort'] ?? '1402');
    $viewer = dent_current_user();
    if ($viewer === null) {
        dent_json_response([
            'success' => true,
            'tracked' => false,
        ]);
    }

    $itemId = notes_1402_parse_item_id($_POST['itemId'] ?? '');
    $preferredTerm = max(0, (int) dent_normalize_digits((string) ($_POST['term'] ?? '0')));
    $userKey = notes_resource_user_key($viewer);

    $insights = notes_with_store_lock_for_cohort($cohort, static function (array &$store) use ($cohort, $itemId, $preferredTerm, $viewer, $userKey): array {
        $context = notes_resource_find_item_context($cohort, $store, $itemId, $preferredTerm);
        if ($context === null) {
            dent_error('منبع موردنظر پیدا نشد.', 404);
        }

        if (!is_array($store['resourceUserState'][$userKey] ?? null)) {
            $store['resourceUserState'][$userKey] = notes_normalize_resource_user_state([]);
        }
        $now = dent_iso_now();
        $entry = notes_resource_context_entry_from_item_context($context, $now);
        $recent = is_array($store['resourceUserState'][$userKey]['recent'] ?? null) ? $store['resourceUserState'][$userKey]['recent'] : [];
        $recent = array_values(array_filter($recent, static function ($current) use ($itemId): bool {
            return !is_array($current) || (int) ($current['itemId'] ?? 0) !== $itemId;
        }));
        array_unshift($recent, $entry);
        $store['resourceUserState'][$userKey]['recent'] = array_slice($recent, 0, NOTES_RESOURCE_RECENT_LIMIT);
        return notes_resource_insights_payload($cohort, $store, $viewer);
    });

    dent_json_response([
        'success' => true,
        'tracked' => true,
        'resourceInsights' => $insights,
    ]);
}

if ($action === 'toggleResourceFavorite') {
    notes_1402_require_method(['POST']);
    $viewer = dent_require_user();
    $cohort = notes_parse_cohort($_POST['cohort'] ?? '1402');
    $itemId = notes_1402_parse_item_id($_POST['itemId'] ?? '');
    $preferredTerm = max(0, (int) dent_normalize_digits((string) ($_POST['term'] ?? '0')));
    $favoriteRaw = strtolower(trim((string) ($_POST['favorite'] ?? '1')));
    $favorite = !in_array($favoriteRaw, ['0', 'false', 'off', 'no'], true);
    $userKey = notes_resource_user_key($viewer);

    $insights = notes_with_store_lock_for_cohort($cohort, static function (array &$store) use ($cohort, $itemId, $preferredTerm, $favorite, $viewer, $userKey): array {
        $context = notes_resource_find_item_context($cohort, $store, $itemId, $preferredTerm);
        if ($context === null) {
            dent_error('منبع موردنظر پیدا نشد.', 404);
        }

        if (!is_array($store['resourceUserState'][$userKey] ?? null)) {
            $store['resourceUserState'][$userKey] = notes_normalize_resource_user_state([]);
        }
        if (!is_array($store['resourceUserState'][$userKey]['favorites'] ?? null)) {
            $store['resourceUserState'][$userKey]['favorites'] = [];
        }

        if ($favorite) {
            $entry = notes_resource_context_entry_from_item_context($context, dent_iso_now());
            $store['resourceUserState'][$userKey]['favorites'][(string) $itemId] = $entry;
        } else {
            unset($store['resourceUserState'][$userKey]['favorites'][(string) $itemId]);
        }

        return notes_resource_insights_payload($cohort, $store, $viewer);
    });

    dent_json_response([
        'success' => true,
        'favorite' => $favorite,
        'resourceInsights' => $insights,
        'message' => $favorite ? 'منبع به علاقه‌مندی‌ها اضافه شد.' : 'منبع از علاقه‌مندی‌ها حذف شد.',
    ]);
}

if ($action === 'requestResource') {
    notes_1402_require_method(['POST']);
    $viewer = dent_require_user();
    $cohort = notes_parse_cohort($_POST['cohort'] ?? '1402');
    $term = max(0, (int) dent_normalize_digits((string) ($_POST['term'] ?? '0')));
    $unitKey = trim(strtolower((string) ($_POST['unitKey'] ?? $_POST['unit'] ?? '')));
    if ($unitKey !== '' && notes_is_curriculum_cohort($cohort)) {
        $unit = dent_dentistry_curriculum_find_unit($unitKey);
        if (!is_array($unit)) {
            dent_error('واحد انتخاب‌شده معتبر نیست.', 422);
        }
        $unitKey = (string) ($unit['key'] ?? '');
    }

    $title = dent_clean_text((string) ($_POST['title'] ?? ''), 180);
    $note = dent_clean_text((string) ($_POST['note'] ?? ''), 800);
    if ($title === '' && $note === '') {
        dent_error('عنوان یا توضیح درخواست منبع را وارد کن.', 422);
    }

    $insights = notes_with_store_lock_for_cohort($cohort, static function (array &$store) use ($cohort, $viewer, $term, $unitKey, $title, $note): array {
        $now = dent_iso_now();
        $id = notes_resource_next_request_id($store);
        $store['resourceRequests'][(string) $id] = [
            'id' => $id,
            'userKey' => notes_resource_user_key($viewer),
            'userName' => notes_resource_user_name($viewer),
            'term' => $term,
            'unitKey' => $unitKey,
            'title' => $title,
            'note' => $note,
            'status' => 'open',
            'createdAt' => $now,
            'updatedAt' => $now,
        ];

        return notes_resource_insights_payload($cohort, $store, $viewer);
    });

    dent_json_response([
        'success' => true,
        'resourceInsights' => $insights,
        'message' => 'درخواست منبع ثبت شد.',
    ]);
}

if ($action === 'reportResourceLink') {
    notes_1402_require_method(['POST']);
    $viewer = dent_require_user();
    $cohort = notes_parse_cohort($_POST['cohort'] ?? '1402');
    $itemId = notes_1402_parse_item_id($_POST['itemId'] ?? '');
    $preferredTerm = max(0, (int) dent_normalize_digits((string) ($_POST['term'] ?? '0')));
    $reason = dent_clean_text((string) ($_POST['reason'] ?? 'لینک خراب است'), 180);
    $note = dent_clean_text((string) ($_POST['note'] ?? ''), 800);

    $insights = notes_with_store_lock_for_cohort($cohort, static function (array &$store) use ($cohort, $viewer, $itemId, $preferredTerm, $reason, $note): array {
        $context = notes_resource_find_item_context($cohort, $store, $itemId, $preferredTerm);
        if ($context === null) {
            dent_error('منبع موردنظر پیدا نشد.', 404);
        }
        $item = is_array($context['item'] ?? null) ? $context['item'] : [];
        $now = dent_iso_now();
        $id = notes_resource_next_report_id($store);
        $store['resourceLinkReports'][(string) $id] = [
            'id' => $id,
            'itemId' => $itemId,
            'userKey' => notes_resource_user_key($viewer),
            'userName' => notes_resource_user_name($viewer),
            'term' => max(0, (int) ($context['term'] ?? 0)),
            'unitKey' => trim(strtolower((string) ($item['unitKey'] ?? ''))),
            'itemTitle' => (string) ($item['title'] ?? ''),
            'itemUrl' => (string) ($item['buttonUrl'] ?? ''),
            'reason' => $reason !== '' ? $reason : 'لینک خراب است',
            'note' => $note,
            'status' => 'open',
            'createdAt' => $now,
            'updatedAt' => $now,
        ];

        return notes_resource_insights_payload($cohort, $store, $viewer);
    });

    dent_json_response([
        'success' => true,
        'resourceInsights' => $insights,
        'message' => 'گزارش لینک ثبت شد.',
    ]);
}

if ($action === 'updateResourceIssueStatus') {
    notes_1402_require_method(['POST']);
    $cohort = notes_parse_cohort($_POST['cohort'] ?? '1402');
    $viewer = notes_require_manage_cohort($cohort);
    $type = trim(strtolower((string) ($_POST['type'] ?? '')));
    if (!in_array($type, ['request', 'report'], true)) {
        dent_error('نوع مورد معتبر نیست.', 422);
    }
    $issueId = max(0, (int) dent_normalize_digits((string) ($_POST['id'] ?? '0')));
    if ($issueId <= 0) {
        dent_error('شناسه مورد معتبر نیست.', 422);
    }
    $status = notes_parse_resource_issue_status_from_post();

    $insights = notes_with_store_lock_for_cohort($cohort, static function (array &$store) use ($cohort, $viewer, $type, $issueId, $status): array {
        $key = $type === 'request' ? 'resourceRequests' : 'resourceLinkReports';
        if (!is_array($store[$key][(string) $issueId] ?? null)) {
            dent_error('مورد انتخابی پیدا نشد.', 404);
        }

        $store[$key][(string) $issueId]['status'] = $status;
        $store[$key][(string) $issueId]['updatedAt'] = dent_iso_now();
        return notes_resource_insights_payload($cohort, $store, $viewer);
    });

    dent_json_response([
        'success' => true,
        'resourceInsights' => $insights,
        'message' => 'وضعیت مورد منابع به‌روزرسانی شد.',
    ]);
}

if ($action === 'downloadHostBrowse') {
    notes_1402_require_method(['GET']);
    $viewer = dent_require_user();
    $cohort = notes_parse_cohort($_GET['cohort'] ?? '');
    if ($cohort === '') {
        $cohort = '1402';
    }
    if (!notes_can_manage_cohort($cohort, $viewer)) {
        dent_error('اجازه مدیریت فایل‌های منابع را ندارید.', 403);
    }

    $scopeRoot = notes_download_host_scope_for_viewer($cohort, $viewer);
    $path = trim((string) ($_GET['path'] ?? ''));
    if ($path === '' && $scopeRoot !== null) {
        $path = $scopeRoot;
    }

    $payload = notes_download_host_browse($path, $scopeRoot);
    dent_json_response([
        'success' => true,
        'browse' => $payload,
        'downloadHost' => [
            'enabled' => notes_download_host_is_enabled(),
            'publicBaseUrl' => notes_download_host_public_base_url(),
            'scopeRoot' => $scopeRoot,
            'canManageAllRoots' => (string) ($viewer['role'] ?? '') === 'owner',
        ],
    ]);
}

if ($action === 'prepareHostUpload') {
    notes_1402_require_method(['POST']);
    $uploadParams = array_merge($_GET, $_POST);
    $cohort = notes_parse_cohort($uploadParams['cohort'] ?? '1402');
    $viewer = notes_require_manage_cohort($cohort);
    $upload = notes_prepare_host_upload_plan($cohort, $viewer, $uploadParams);

    dent_json_response([
        'success' => true,
        'upload' => $upload,
    ]);
}

if ($action === 'ensureDirectUploadGateway') {
    notes_1402_require_method(['POST']);
    $uploadParams = array_merge($_GET, $_POST);
    $cohort = notes_parse_cohort($uploadParams['cohort'] ?? '1402');
    notes_require_manage_cohort($cohort);

    $mainSiteOrigin = notes_direct_upload_main_site_origin();
    if ($mainSiteOrigin === '') {
        dent_error('Main-site origin for direct upload could not be determined (local/dev host?).', 422);
    }

    $downloadPublicHost = strtolower(trim((string) parse_url(notes_download_host_public_base_url(), PHP_URL_HOST)));
    if ($downloadPublicHost === '') {
        dent_error('هاست دانلود برای آپلود مستقیم پیکربندی نشده است.', 422);
    }

    $gateway = notes_download_host_ensure_direct_upload_gateway($mainSiteOrigin);
    if (!is_array($gateway)) {
        dent_error('گیت‌وی آپلود مستقیم روی هاست دانلود راه‌اندازی نشد.', 502);
    }

    dent_json_response([
        'success' => true,
        'gateway' => $gateway,
        'message' => 'گیت‌وی آپلود مستقیم روی هاست دانلود فعال و آماده است.',
    ]);
}

if ($action === 'streamHostUploadChunk') {
    notes_1402_require_method(['POST']);
    $cohort = notes_parse_cohort($_GET['cohort'] ?? $_POST['cohort'] ?? '1402');
    $viewer = notes_require_manage_cohort($cohort);
    dent_release_session_lock();

    $token = notes_direct_upload_clean_token($_GET['token'] ?? $_POST['token'] ?? '');
    $chunkIndex = max(0, (int) ($_GET['chunkIndex'] ?? -1));
    $chunkCount = max(1, (int) ($_GET['chunkCount'] ?? 1));
    $chunkStart = max(0, (int) ($_GET['chunkStart'] ?? -1));
    $chunkEnd = max(0, (int) ($_GET['chunkEnd'] ?? -1));
    $contentLength = max(0, (int) notes_download_host_request_header('Content-Length'));
    $chunkEncoding = strtolower(trim(notes_download_host_request_header('X-Dent-Chunk-Encoding')));
    if ($chunkEncoding === '') {
        // Some managed WAF/proxy layers strip unknown request headers. The
        // authenticated, token-scoped endpoint therefore accepts the same
        // non-secret transport hint in the query string as a reliable fallback.
        $chunkEncoding = strtolower(trim((string) ($_GET['chunkEncoding'] ?? '')));
    }
    $decodedChunkBytes = max(0, $chunkEnd - $chunkStart);
    if ($token === '' || $chunkIndex >= $chunkCount || $chunkEnd <= $chunkStart || $contentLength <= 0) {
        dent_error('اطلاعات chunk آپلود معتبر نیست.', 422);
    }
    if ($chunkEncoding === '' && $decodedChunkBytes !== $contentLength) {
        dent_error('حجم بدنه chunk با بازه اعلام‌شده برابر نیست.', 422);
    }
    if ($chunkEncoding !== '' && $chunkEncoding !== 'base64url') {
        dent_error('شیوه کدگذاری chunk پشتیبانی نمی‌شود.', 422);
    }

    $sessionSnapshot = notes_direct_upload_with_store_lock(static function (array &$store) use ($token, $cohort): array {
        $session = $store['sessions'][$token] ?? null;
        if (!is_array($session)) {
            dent_error('نشست آپلود پیدا نشد یا منقضی شده است.', 404);
        }
        if ((string) ($session['cohort'] ?? '') !== $cohort) {
            dent_error('نشست آپلود متعلق به این ورودی نیست.', 403);
        }
        if ((string) ($session['status'] ?? '') === 'completed' && is_array($session['file'] ?? null)) {
            return $session;
        }
        $session['status'] = 'resolved';
        $session['updatedAt'] = dent_iso_now();
        $store['sessions'][$token] = $session;
        return $session;
    });

    if ((string) ($sessionSnapshot['status'] ?? '') === 'completed' && is_array($sessionSnapshot['file'] ?? null)) {
        dent_json_response([
            'success' => true,
            'file' => $sessionSnapshot['file'],
            'message' => 'فایل پیش‌تر روی هاست دانلود کامل شده است.',
        ]);
    }

    $expectedSize = max(0, (int) ($sessionSnapshot['expectedSize'] ?? 0));
    $relativePath = trim((string) ($sessionSnapshot['relativePath'] ?? ''));
    $scopeRoot = notes_download_host_scope_for_viewer($cohort, $viewer);
    $relativeDir = trim((string) ($sessionSnapshot['relativeDir'] ?? dirname($relativePath)));
    notes_download_host_assert_allowed_relative_path($relativeDir, $scopeRoot, false);
    if ($expectedSize <= 0 || $relativePath === '' || $chunkEnd > $expectedSize) {
        dent_error('نشست یا بازه فایل آپلود معتبر نیست.', 422);
    }
    $isLastChunk = $chunkIndex === ($chunkCount - 1);
    if ($isLastChunk !== ($chunkEnd === $expectedSize)) {
        dent_error('chunk پایانی با حجم کل فایل هم‌خوانی ندارد.', 422);
    }

    $stream = null;
    if ($chunkEncoding === 'base64url') {
        $encoded = file_get_contents('php://input');
        if (!is_string($encoded) || $encoded === '') {
            dent_error('بدنه متنی chunk دریافت نشد.', 422);
        }
        $decoded = dent_base64url_decode(trim($encoded));
        unset($encoded);
        if ($decoded === '' || strlen($decoded) !== $decodedChunkBytes) {
            dent_error('کدگشایی یا حجم chunk متنی معتبر نیست.', 422);
        }
        $stream = fopen('php://temp/maxmemory:8388608', 'w+b');
        if ($stream === false || fwrite($stream, $decoded) !== strlen($decoded)) {
            if (is_resource($stream)) fclose($stream);
            dent_error('بافر حافظه chunk آماده نشد.', 500);
        }
        unset($decoded);
        rewind($stream);
    } else {
        $stream = fopen('php://input', 'rb');
    }
    if (!is_resource($stream)) {
        dent_error('جریان raw-body آپلود باز نشد.', 422);
    }
    try {
        $stored = notes_download_host_stream_chunk_to_ftp(
            $relativePath,
            $stream,
            $chunkIndex,
            $chunkCount,
            $chunkStart,
            $decodedChunkBytes,
            $expectedSize,
            $token,
            $isLastChunk,
            notes_direct_upload_main_site_origin()
        );
    } finally {
        fclose($stream);
    }

    if (!$isLastChunk) {
        dent_json_response([
            'success' => true,
            'partial' => true,
            'chunkIndex' => $chunkIndex,
            'chunkCount' => $chunkCount,
            'storedBytes' => max(0, (int) ($stored['storedBytes'] ?? $chunkEnd)),
        ]);
    }

    $mimeType = trim((string) ($sessionSnapshot['mimeType'] ?? strtok(notes_download_host_request_header('Content-Type'), ';')));
    $filePayload = notes_direct_upload_with_store_lock(static function (array &$store) use ($token, $expectedSize, $mimeType): array {
        $session = $store['sessions'][$token] ?? null;
        if (!is_array($session)) {
            dent_error('نشست آپلود هنگام نهایی‌سازی پیدا نشد.', 404);
        }
        if ((string) ($session['status'] ?? '') === 'completed' && is_array($session['file'] ?? null)) {
            return $session['file'];
        }
        $file = notes_direct_upload_build_file_payload($session, $expectedSize, $mimeType);
        $session['status'] = 'completed';
        $session['updatedAt'] = dent_iso_now();
        $session['completedAt'] = $session['updatedAt'];
        $session['mimeType'] = (string) ($file['mimeType'] ?? '');
        $session['file'] = $file;
        $store['sessions'][$token] = $session;
        return $file;
    });

    dent_json_response([
        'success' => true,
        'file' => $filePayload,
        'transport' => 'raw-chunk-to-ftp',
        'message' => 'فایل بدون staging روی هاست اصلی، مستقیماً روی هاست دانلود کامل شد.',
    ]);
}

if ($action === 'resolveDirectHostUpload') {
    notes_1402_require_method(['POST']);
    dent_release_session_lock();

    $token = notes_direct_upload_clean_token($_POST['token'] ?? '');
    if ($token === '') {
        dent_error('توکن آپلود مستقیم معتبر نیست.', 422);
    }

    $sessionPayload = notes_direct_upload_with_store_lock(static function (array &$store) use ($token): array {
        $session = $store['sessions'][$token] ?? null;
        if (!is_array($session)) {
            dent_error('نشست آپلود مستقیم پیدا نشد یا منقضی شده است.', 404);
        }

        $session['status'] = 'resolved';
        $session['updatedAt'] = dent_iso_now();
        $session['contentType'] = trim((string) ($_POST['contentType'] ?? $session['contentType'] ?? ''));
        $session['contentLength'] = max(0, (int) ($_POST['contentLength'] ?? $session['contentLength'] ?? 0));
        $session['origin'] = trim((string) ($_POST['origin'] ?? $session['origin'] ?? ''));
        $session['gatewayVersion'] = trim((string) ($_POST['gatewayVersion'] ?? $session['gatewayVersion'] ?? ''));
        $store['sessions'][$token] = $session;

        return [
            'token' => $session['token'],
            'sessionKey' => (string) ($session['sessionKey'] ?? ''),
            'relativeDir' => (string) ($session['relativeDir'] ?? ''),
            'relativePath' => (string) ($session['relativePath'] ?? ''),
            'fileName' => (string) ($session['finalName'] ?? ''),
            'expectedSize' => max(0, (int) ($session['expectedSize'] ?? 0)),
            'mimeType' => (string) ($session['mimeType'] ?? ''),
            'publicUrl' => notes_download_host_public_url((string) ($session['relativePath'] ?? '')),
        ];
    });

    dent_json_response([
        'success' => true,
        'session' => $sessionPayload,
    ]);
}

if ($action === 'completeDirectHostUpload') {
    notes_1402_require_method(['POST']);
    dent_release_session_lock();

    $token = notes_direct_upload_clean_token($_POST['token'] ?? '');
    $sessionKey = notes_direct_upload_clean_token($_POST['sessionKey'] ?? '');
    $sizeBytes = max(0, (int) ($_POST['bytes'] ?? 0));
    $mimeType = trim((string) ($_POST['mimeType'] ?? ''));

    if ($token === '' || $sessionKey === '') {
        dent_error('اطلاعات نهایی‌سازی آپلود مستقیم معتبر نیست.', 422);
    }

    $filePayload = notes_direct_upload_with_store_lock(static function (array &$store) use ($token, $sessionKey, $sizeBytes, $mimeType): array {
        $session = $store['sessions'][$token] ?? null;
        if (!is_array($session)) {
            dent_error('نشست آپلود مستقیم پیدا نشد یا منقضی شده است.', 404);
        }
        if (!hash_equals((string) ($session['sessionKey'] ?? ''), $sessionKey)) {
            dent_error('کلید نهایی‌سازی آپلود مستقیم معتبر نیست.', 403);
        }

        $storedFile = is_array($session['file'] ?? null) ? $session['file'] : null;
        if ((string) ($session['status'] ?? '') === 'completed' && $storedFile !== null) {
            return $storedFile;
        }

        $expectedSize = max(0, (int) ($session['expectedSize'] ?? 0));
        if ($expectedSize > 0 && $sizeBytes > 0 && $sizeBytes !== $expectedSize) {
            dent_error('حجم نهایی فایل با نشست آپلود مستقیم هم‌خوانی ندارد.', 422);
        }

        $finalFile = notes_direct_upload_build_file_payload(
            $session,
            $sizeBytes > 0 ? $sizeBytes : $expectedSize,
            $mimeType !== '' ? $mimeType : (string) ($session['mimeType'] ?? '')
        );

        $session['status'] = 'completed';
        $session['updatedAt'] = dent_iso_now();
        $session['completedAt'] = $session['updatedAt'];
        $session['mimeType'] = (string) ($finalFile['mimeType'] ?? '');
        $session['file'] = $finalFile;
        $store['sessions'][$token] = $session;

        return $finalFile;
    });

    dent_json_response([
        'success' => true,
        'file' => $filePayload,
        'message' => (string) ($filePayload['message'] ?? 'فایل روی هاست دانلود ذخیره شد.'),
    ]);
}

if ($action === 'hostUploadFile') {
    notes_1402_require_method(['POST']);
    $uploadParams = array_merge($_GET, $_POST);
    $cohort = notes_parse_cohort($uploadParams['cohort'] ?? '1402');
    $viewer = notes_require_manage_cohort($cohort);
    $target = notes_download_host_target_context($cohort, $viewer, $uploadParams);
    $scopeRoot = $target['scopeRoot'];
    $relativeDir = $target['relativeDir'];

    if (!isset($_FILES['file']) && max(0, (int) notes_download_host_request_header('Content-Length')) > 0) {
        $contentLength = max(0, (int) notes_download_host_request_header('Content-Length'));
        $desiredName = trim((string) ($uploadParams['fileName'] ?? notes_download_host_decode_header_value(notes_download_host_request_header('X-Dent-Upload-Name'))));
        $mimeType = trim((string) strtok(notes_download_host_request_header('Content-Type'), ';'));
        $stream = fopen('php://input', 'rb');
        if ($stream === false) {
            dent_error('Upload stream could not be opened.', 422);
        }

        notes_download_host_stream_upload_relay($relativeDir, $stream, $contentLength, $desiredName, $mimeType, $scopeRoot);
    }

    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
        dent_error('فایل برای آپلود ارسال نشد.', 422);
    }

    notes_download_host_prepare_long_transfer();
    $desiredName = trim((string) ($uploadParams['fileName'] ?? ''));
    $uploaded = notes_download_host_upload_file($relativeDir, $_FILES['file'], $desiredName, $scopeRoot);
    dent_json_response([
        'success' => true,
        'file' => $uploaded,
        'message' => $uploaded['message'] ?? 'فایل روی هاست دانلود ذخیره شد.',
    ]);
}

if ($action === 'downloadHostEnsureDir') {
    notes_1402_require_method(['POST']);
    $cohort = notes_parse_cohort($_POST['cohort'] ?? '1402');
    $viewer = notes_require_manage_cohort($cohort);
    $scopeRoot = notes_download_host_scope_for_viewer($cohort, $viewer);
    $targetPath = trim((string) ($_POST['path'] ?? ''));
    if ($targetPath === '') {
        $term = $cohort === 'prosthesis-1402'
            ? notes_prosthesis_1402_parse_term_id($_POST['term'] ?? '1')
            : notes_require_term_for_cohort($cohort, $_POST['term'] ?? '');
        $store = notes_curriculum_store_for_cohort($cohort);
        $targetPath = notes_download_host_default_relative_dir_from_request($cohort, $term, $_POST, $store);
    }
    $normalized = notes_download_host_normalize_relative_path($targetPath);
    notes_download_host_ensure_dir($normalized, $scopeRoot);
    dent_json_response([
        'success' => true,
        'path' => $normalized,
        'publicUrl' => notes_download_host_public_url($normalized),
        'message' => 'پوشه مقصد روی هاست دانلود آماده شد.',
    ]);
}

if ($action === 'downloadHostCreateDir') {
    notes_1402_require_method(['POST']);
    $cohort = notes_parse_cohort($_POST['cohort'] ?? '1402');
    $viewer = notes_require_manage_cohort($cohort);
    $scopeRoot = notes_download_host_scope_for_viewer($cohort, $viewer);
    $parentPath = trim((string) ($_POST['path'] ?? ''));
    if ($parentPath === '' && $scopeRoot !== null) {
        $parentPath = $scopeRoot;
    }
    $created = notes_download_host_create_dir($parentPath, (string) ($_POST['name'] ?? ''), $scopeRoot);
    dent_json_response([
        'success' => true,
        'entry' => $created,
        'message' => 'پوشه جدید روی هاست دانلود ساخته شد.',
    ]);
}

if ($action === 'downloadHostRenameEntry') {
    notes_1402_require_method(['POST']);
    $cohort = notes_parse_cohort($_POST['cohort'] ?? '1402');
    $viewer = notes_require_manage_cohort($cohort);
    $scopeRoot = notes_download_host_scope_for_viewer($cohort, $viewer);
    $renamed = notes_download_host_rename_entry(
        (string) ($_POST['path'] ?? ''),
        (string) ($_POST['name'] ?? ''),
        $scopeRoot
    );
    dent_json_response([
        'success' => true,
        'entry' => $renamed,
        'message' => 'نام فایل یا پوشه تغییر کرد.',
    ]);
}

if ($action === 'downloadHostDeleteEntry') {
    notes_1402_require_method(['POST']);
    $cohort = notes_parse_cohort($_POST['cohort'] ?? '1402');
    $viewer = notes_require_manage_cohort($cohort);
    $scopeRoot = notes_download_host_scope_for_viewer($cohort, $viewer);
    $deleted = notes_download_host_delete_entry(
        (string) ($_POST['path'] ?? ''),
        (string) ($_POST['entryType'] ?? 'file'),
        $scopeRoot
    );
    dent_json_response([
        'success' => true,
        'entry' => $deleted,
        'message' => 'آیتم انتخابی از هاست دانلود حذف شد.',
    ]);
}

if ($action === 'addItem') {
    notes_1402_require_method(['POST']);

    $cohort = notes_parse_cohort($_POST['cohort'] ?? '1402');
    notes_require_manage_cohort($cohort);
    $term = $cohort === 'prosthesis-1402'
        ? notes_prosthesis_1402_parse_term_id($_POST['term'] ?? '')
        : notes_require_term_for_cohort($cohort, $_POST['term'] ?? '');
    $fields = notes_parse_item_fields_from_post();
    try {
        if ($cohort === '1403') {
            $created = notes_1403_add_item($term, $fields);
        } elseif ($cohort === '1404') {
            $created = notes_1404_add_item($term, $fields);
        } elseif ($cohort === 'prosthesis-1402') {
            $created = notes_prosthesis_1402_add_item($term, $fields);
        } else {
            $created = notes_1402_add_item($term, $fields);
        }
    } catch (RuntimeException $error) {
        if ($error->getMessage() === 'term-not-found') {
            dent_error('ترم موردنظر پیدا نشد.', 404);
        }
        throw $error;
    }

    dent_json_response([
        'success' => true,
        'item' => notes_item_response_payload($cohort, $created, $term),
        'message' => 'کارت منبع جدید با موفقیت ثبت شد.',
    ]);
}

if ($action === 'editItem') {
    notes_1402_require_method(['POST']);

    $cohort = notes_parse_cohort($_POST['cohort'] ?? '1402');
    notes_require_manage_cohort($cohort);
    $term = $cohort === 'prosthesis-1402'
        ? notes_prosthesis_1402_parse_term_id($_POST['term'] ?? '')
        : notes_require_term_for_cohort($cohort, $_POST['term'] ?? '');
    $itemId = notes_1402_parse_item_id($_POST['itemId'] ?? '');
    $fields = notes_parse_item_fields_from_post();

    try {
        if ($cohort === '1403') {
            $updated = notes_1403_edit_item($term, $itemId, $fields);
        } elseif ($cohort === '1404') {
            $updated = notes_1404_edit_item($term, $itemId, $fields);
        } elseif ($cohort === 'prosthesis-1402') {
            $updated = notes_prosthesis_1402_edit_item($term, $itemId, $fields);
        } else {
            $updated = notes_1402_edit_item($term, $itemId, $fields);
        }
    } catch (RuntimeException $error) {
        if ($error->getMessage() === 'term-not-found') {
            dent_error('ترم موردنظر پیدا نشد.', 404);
        }
        if ($error->getMessage() === 'item-not-found') {
            dent_error('کارت موردنظر پیدا نشد.', 404);
        }

        throw $error;
    }

    dent_json_response([
        'success' => true,
        'item' => notes_item_response_payload($cohort, $updated, $term),
        'message' => 'کارت منبع ذخیره شد.',
    ]);
}

if ($action === 'deleteItem') {
    notes_1402_require_method(['POST']);

    $cohort = notes_parse_cohort($_POST['cohort'] ?? '1402');
    notes_require_manage_cohort($cohort);
    $term = $cohort === 'prosthesis-1402'
        ? notes_prosthesis_1402_parse_term_id($_POST['term'] ?? '')
        : notes_require_term_for_cohort($cohort, $_POST['term'] ?? '');
    $itemId = notes_1402_parse_item_id($_POST['itemId'] ?? '');

    try {
        if ($cohort === '1403') {
            $deleted = notes_1403_delete_item($term, $itemId);
        } elseif ($cohort === '1404') {
            $deleted = notes_1404_delete_item($term, $itemId);
        } elseif ($cohort === 'prosthesis-1402') {
            $deleted = notes_prosthesis_1402_delete_item($term, $itemId);
        } else {
            $deleted = notes_1402_delete_item($term, $itemId);
        }
    } catch (RuntimeException $error) {
        if ($error->getMessage() === 'term-not-found') {
            dent_error('ترم موردنظر پیدا نشد.', 404);
        }
        if ($error->getMessage() === 'item-not-found') {
            dent_error('کارت موردنظر پیدا نشد.', 404);
        }

        throw $error;
    }

    dent_json_response([
        'success' => true,
        'item' => notes_item_response_payload($cohort, $deleted, $term),
        'message' => 'کارت منبع حذف شد.',
    ]);
}

dent_error('درخواست نامعتبر است.', 404);
