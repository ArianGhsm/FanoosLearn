<?php
declare(strict_types=1);

require_once __DIR__ . '/../../api/content_tools_store.php';

$token = content_clean_slug_token((string) ($_GET['token'] ?? ''));
$paste = null;
if ($token !== '') {
    $paste = content_find_paste_by_token(content_read_store(), $token);
}
if (!is_array($paste)) {
    http_response_code(404);
}
$title = is_array($paste) ? ((string) ($paste['title'] ?? 'Paste')) : 'Paste در دسترس نیست';
$description = is_array($paste)
    ? ('نمایش paste عمومی ' . $title . ' در سایت ورودی ۱۴۰۲ دندانپزشکی تهران.')
    : 'این لینک paste ممکن است حذف، منقضی یا غیرفعال شده باشد.';

function ct_paste_h(string $value): string
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
    <title><?php echo ct_paste_h($title); ?> | Paste</title>
    <meta name="description" content="<?php echo ct_paste_h($description); ?>">
    <meta name="robots" content="noindex,follow">
    <link rel="manifest" href="/manifest.webmanifest?v=20260722-200509">
    <link rel="icon" type="image/png" href="/assets/images/favicon.png?v=20260722-200509">
    <script src="/assets/site/scripts/theme.js?v=20260722-200509"></script>
    <link rel="stylesheet" href="/assets/site/styles/core.css?v=20260722-200509">
    <link rel="stylesheet" href="/assets/site/styles/content-tools.css?v=20260722-200509">
    <link rel="stylesheet" href="/assets/site/styles/theme.css?v=20260812-phase4b">
</head>
<body class="content-tools-page content-tools-page--public content-tools-page--public-paste" data-content-tool="public-paste" data-public-token="<?php echo ct_paste_h($token); ?>" data-shell-header="off" data-shell-reserve="self">
    <div class="background-overlay" aria-hidden="true"></div>
    <main class="ct-public-shell ct-public-shell--paste">
        <header class="ct-public-header">
            <a class="ct-brand-link" href="/app/">
                <span class="ct-brand-mark ct-brand-mark--paste" aria-hidden="true">P</span>
                <span>Paste ورودی ۱۴۰۲</span>
            </a>
            <span class="ct-theme-slot" data-theme-toggle-slot></span>
        </header>
        <section id="ct-public-root" class="ct-public-card ct-public-card--paste" aria-live="polite">
            <div class="ct-public-loading">
                <span class="ct-loader" aria-hidden="true"></span>
                <h1>در حال آماده‌سازی paste</h1>
                <p>چند لحظه صبر کنید.</p>
            </div>
        </section>
    </main>
    <script src="/assets/site/scripts/content-tools.js?v=20260722-200509"></script>
    <script src="/assets/site/scripts/shell.js?v=20260722-200509"></script>
</body>
</html>
