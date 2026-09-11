<?php
declare(strict_types=1);

require_once __DIR__ . '/../../api/html_uploader_store.php';

$token = html_uploader_clean_token((string) ($_GET['token'] ?? ''));

function html_uploader_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function html_uploader_render_state_page(string $title, string $message, int $statusCode = 404): void
{
    http_response_code($statusCode);
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    ?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <title><?php echo html_uploader_h($title); ?></title>
    <link rel="manifest" href="/manifest.webmanifest?v=20260722-200509">
    <link rel="icon" type="image/png" href="/assets/images/favicon.png?v=20260722-200509">
    <script src="/assets/site/scripts/theme.js?v=20260722-200509"></script>
    <link rel="stylesheet" href="/assets/site/styles/core.css?v=20260722-200509">
    <link rel="stylesheet" href="/assets/site/styles/theme.css?v=20260812-phase4b">
    <style>
        body { min-height: 100vh; display: grid; place-items: center; padding: 1rem; background: var(--bg-body); }
        .html-public-state { width: min(34rem, 100%); padding: 1.2rem; border-radius: 24px; border: 1px solid var(--border-color); background: var(--surface); box-shadow: var(--surface-shadow); text-align: center; }
        .html-public-state h1, .html-public-state p { margin: 0; }
        .html-public-state h1 { font-family: var(--font-accent); font-size: 1.18rem; line-height: 1.5; }
        .html-public-state p { margin-top: 0.5rem; color: var(--text-secondary); font-size: 0.86rem; line-height: 1.9; }
    </style>
</head>
<body>
    <main class="html-public-state">
        <h1><?php echo html_uploader_h($title); ?></h1>
        <p><?php echo html_uploader_h($message); ?></p>
    </main>
</body>
</html>
<?php
    exit;
}

if ($token === '') {
    html_uploader_render_state_page('لینک صفحه HTML معتبر نیست', 'توکن این صفحه پیدا نشد یا فرمت آن معتبر نیست.', 404);
}

$page = html_uploader_with_store_lock(static function (array &$store) use ($token): ?array {
    foreach (($store['pages'] ?? []) as $id => $candidate) {
        if (!is_array($candidate) || (string) ($candidate['token'] ?? '') !== $token) {
            continue;
        }
        if (html_uploader_public_state($candidate) !== 'active') {
            return $candidate;
        }
        $candidate['viewCount'] = max(0, (int) ($candidate['viewCount'] ?? 0)) + 1;
        $candidate['lastViewedAt'] = dent_iso_now();
        $store['pages'][$id] = $candidate;
        return $candidate;
    }
    return null;
});

if (!is_array($page)) {
    html_uploader_render_state_page('صفحه HTML پیدا نشد', 'این لینک وجود ندارد یا قبلاً از دسترس خارج شده است.', 404);
}

$state = html_uploader_public_state($page);
if ($state !== 'active') {
    $title = $state === 'expired' ? 'مهلت این صفحه HTML تمام شده است' : 'این صفحه HTML فعال نیست';
    $message = $state === 'expired'
        ? 'این صفحه موقت منقضی شده و دیگر در دسترس نیست.'
        : 'این صفحه توسط مدیریت یا صاحب لینک از دسترس خارج شده است.';
    html_uploader_render_state_page($title, $message, 410);
}

html_uploader_emit_page_bytes($page);
