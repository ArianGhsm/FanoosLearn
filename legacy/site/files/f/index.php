<?php
declare(strict_types=1);

require_once __DIR__ . '/../../api/content_tools_store.php';

$token = content_clean_slug_token((string) ($_GET['token'] ?? ''));
$file = null;
if ($token !== '') {
    $file = content_find_file_by_token(content_read_store(), $token);
}
if (!is_array($file)) {
    http_response_code(404);
}
$title = is_array($file) ? ((string) ($file['title'] ?? '') ?: (string) ($file['originalName'] ?? 'فایل')) : 'فایل در دسترس نیست';
$description = is_array($file)
    ? ('دانلود فایل ' . ((string) ($file['originalName'] ?? $title)) . ' از سایت ورودی ۱۴۰۲ دندانپزشکی تهران.')
    : 'این لینک فایل ممکن است حذف، منقضی یا غیرفعال شده باشد.';

function ct_file_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#eef2f7">
    <title><?php echo ct_file_h($title); ?> | لینک فایل</title>
    <meta name="description" content="<?php echo ct_file_h($description); ?>">
    <meta name="robots" content="noindex,follow">
    <link rel="manifest" href="/manifest.webmanifest?v=20260722-200509">
    <link rel="icon" type="image/png" href="/assets/images/favicon.png?v=20260722-200509">
    <script src="/assets/site/scripts/theme.js?v=20260722-200509"></script>
    <link rel="stylesheet" href="/assets/site/styles/core.css?v=20260722-200509">
    <link rel="stylesheet" href="/assets/site/styles/content-tools.css?v=20260722-200509">
    <link rel="stylesheet" href="/assets/site/styles/theme.css?v=20260812-phase4b">
</head>
<body class="content-tools-page content-tools-page--public" data-content-tool="public-file" data-public-token="<?php echo ct_file_h($token); ?>" data-shell-header="off" data-shell-reserve="self">
    <div class="background-overlay" aria-hidden="true"></div>
    <main class="ct-public-shell">
        <header class="ct-public-header">
            <a class="ct-brand-link" href="/app/">
                <span class="ct-brand-mark" aria-hidden="true">F</span>
                <span>فایل‌های ورودی ۱۴۰۲</span>
            </a>
            <span class="ct-theme-slot" data-theme-toggle-slot></span>
        </header>
        <section id="ct-public-root" class="ct-public-card" aria-live="polite">
            <div class="ct-public-loading">
                <span class="ct-loader" aria-hidden="true"></span>
                <h1>در حال آماده‌سازی لینک فایل</h1>
                <p>چند لحظه صبر کنید.</p>
            </div>
        </section>
    </main>
    <script src="/assets/site/scripts/content-tools.js?v=20260722-200509"></script>
    <script src="/assets/site/scripts/shell.js?v=20260722-200509"></script>
</body>
</html>
