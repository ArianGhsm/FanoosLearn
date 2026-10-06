<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * Renders one server-built page: the document, the shared chrome, and the
 * small amount of per-page JavaScript that page actually needs.
 *
 * The site is multi-page on purpose. Every route is its own document, so a
 * page ships only its own script (the exam runner never downloads the
 * announcement code), the first paint does not wait on a bundle, and each
 * page can be tested as a page. There is no build step: assets are served
 * exactly as they are committed, which is what keeps the release pipeline a
 * symlink swap.
 */
final class PageRenderer
{
    /** @param list<string> $stylesheets @param list<string> $modules */
    public function __construct(
        private readonly AssetVersioner $assets,
    ) {
    }

    /**
     * @param array{
     *     title: string,
     *     description?: string,
     *     bodyClass?: string,
     *     stylesheets?: list<string>,
     *     modules?: list<string>,
     *     viewer?: ViewerContext|null,
     *     activeNav?: string,
     *     publicHeader?: string
     * } $page
     */
    public function render(array $page, string $mainHtml): string
    {
        $viewer = $page['viewer'] ?? null;
        $title = $this->escape($page['title']);
        $description = $this->escape($page['description'] ?? 'فانوس؛ فضای آموزشی دانشجو.');
        $bodyClasses = trim(($page['bodyClass'] ?? '') . ($viewer !== null ? ' f-has-nav' : ''));

        $head = [
            '<meta charset="utf-8">',
            '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">',
            '<meta name="description" content="' . $description . '">',
            '<meta name="theme-color" content="#f5f3ee">',
            '<title>' . $title . '</title>',
            '<link rel="manifest" href="' . Pwa::MANIFEST_PATH . '">',
            '<link rel="icon" type="image/png" sizes="192x192" href="' . $this->escape($this->assets->url('/assets/web/icons/icon-192.png')) . '">',
            '<link rel="apple-touch-icon" href="' . $this->escape($this->assets->url('/assets/web/icons/apple-touch-icon.png')) . '">',
        ];

        // The CSRF token is embedded per page rather than fetched, so a
        // mutating request never needs a round trip first. It is bound to the
        // session cookie the browser already holds; a page rendered for a
        // signed-out visitor carries none.
        if ($viewer !== null) {
            $head[] = '<meta name="fanoos-csrf" content="' . $this->escape($viewer->csrfToken) . '">';
        }
        // Page scripts build workspace-scoped API paths from this rather than
        // parsing the URL, so a workspace can never be spoofed by editing the
        // address bar: it is whatever the session actually has selected.
        if ($viewer !== null && $viewer->workspaceId !== null) {
            $head[] = '<meta name="fanoos-workspace" content="' . $this->escape($viewer->workspaceId) . '">';
        }

        foreach (['/assets/web/foundation/tokens.css', '/assets/web/foundation/type.css', '/assets/web/foundation/base.css'] as $sheet) {
            $head[] = '<link rel="stylesheet" href="' . $this->escape($this->assets->url($sheet)) . '">';
        }
        if ($viewer !== null) {
            $head[] = '<link rel="stylesheet" href="' . $this->escape($this->assets->url('/assets/web/foundation/shell.css')) . '">';
        }
        foreach ($page['stylesheets'] ?? [] as $sheet) {
            $head[] = '<link rel="stylesheet" href="' . $this->escape($this->assets->url($sheet)) . '">';
        }

        $scripts = ['<script type="module" src="' . $this->escape($this->assets->url('/assets/web/foundation/pwa.js')) . '"></script>'];
        if ($viewer !== null && $viewer->workspaceId !== null) {
            $scripts[] = '<script type="module" src="' . $this->escape($this->assets->url('/assets/web/foundation/header-stats.js')) . '"></script>';
        }
        foreach ($page['modules'] ?? [] as $module) {
            $scripts[] = '<script type="module" src="' . $this->escape($this->assets->url($module)) . '"></script>';
        }

        // A signed-out page may bring its own header (PublicChrome); the
        // signed-in chrome is only ever built from a viewer.
        $chrome = $viewer === null ? ($page['publicHeader'] ?? '') : $this->chrome($viewer, $page['activeNav'] ?? '');

        return '<!doctype html>' . "\n"
            . '<html lang="fa" dir="rtl">' . "\n"
            . '<head>' . "\n" . implode("\n", $head) . "\n" . '</head>' . "\n"
            . '<body' . ($bodyClasses === '' ? '' : ' class="' . $this->escape($bodyClasses) . '"') . '>' . "\n"
            . '<a class="f-skip" href="#main">رفتن به محتوای اصلی</a>' . "\n"
            . $chrome
            . '<main id="main" class="f-page">' . "\n" . $mainHtml . "\n" . '</main>' . "\n"
            . $this->footer($viewer) . "\n"
            . implode("\n", $scripts) . "\n"
            . '<noscript><div class="f-notice f-notice--warning"><div class="f-notice__body">'
            . 'بعضی بخش‌های فانوس بدون JavaScript کار نمی‌کنند. لطفاً آن را روشن کنید.'
            . '</div></div></noscript>' . "\n"
            . '</body>' . "\n" . '</html>' . "\n";
    }

    /** Line icons for the navigation, drawn in the text colour. They replaced emoji, which rendered differently on every device. */
    private const NAV_ICONS = [
        'home' => '<svg viewBox="0 0 24 24"><path d="M3.5 10.5 12 3.5l8.5 7V20a1 1 0 0 1-1 1H15v-6H9v6H4.5a1 1 0 0 1-1-1z"/></svg>',
        'bank' => '<svg viewBox="0 0 24 24"><path d="M4 5.5A1.5 1.5 0 0 1 5.5 4H10v16H5.5A1.5 1.5 0 0 1 4 18.5z"/><path d="M10 4h4v16h-4z"/><path d="m14.5 4.6 3.9-1 2.6 15.4-3.9 1z"/></svg>',
        'flag' => '<svg viewBox="0 0 24 24"><path d="M5 21V4"/><path d="M5 4h11l-2 4 2 4H5"/></svg>',
        'exams' => '<svg viewBox="0 0 24 24"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h3"/></svg>',
        'progress' => '<svg viewBox="0 0 24 24"><path d="M4 4v16h16"/><path d="m7.5 14.5 3.5-3.5 3 3 5-5.5"/></svg>',
        'store' => '<svg viewBox="0 0 24 24"><path d="M5 8h14l-1.1 11.1a1 1 0 0 1-1 .9H7.1a1 1 0 0 1-1-.9z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/></svg>',
        'tag' => '<svg viewBox="0 0 24 24"><path d="M3.5 12V4.5a1 1 0 0 1 1-1H12l8.5 8.5-8.5 8.5z"/><circle cx="8" cy="8" r="1.4"/></svg>',
        'account' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4.5 20.5a7.5 7.5 0 0 1 15 0"/></svg>',
    ];

    private function chrome(ViewerContext $viewer, string $activeNav): string
    {
        $items = '';
        foreach ($viewer->navigation() as $item) {
            $current = $item['key'] === $activeNav ? ' aria-current="page"' : '';
            $items .= '<a class="f-nav__link" data-key="' . $this->escape($item['key']) . '" href="' . $this->escape($item['href']) . '"' . $current . '>'
                . '<span class="f-nav__icon" aria-hidden="true">' . (self::NAV_ICONS[$item['icon']] ?? '') . '</span>'
                . '<span>' . $this->escape($item['label']) . '</span>'
                . '</a>';
        }

        // The workspace is where the student is working, and choosing another
        // one is the only thing to do with it -- so it is the switch.
        $workspace = $viewer->workspaceName === null
            ? ''
            : '<a class="f-header__workspace" href="/app?switch=1" title="عوض کردن فضای آموزشی">'
                . '<span>' . $this->escape($viewer->workspaceName) . '</span>'
                . '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg>'
                . '</a>';

        $name = trim($viewer->displayName);
        $initial = preg_match('/^./us', $name, $first) === 1 ? $first[0] : '؟';
        $current = $activeNav === 'account' ? ' aria-current="page"' : '';

        return '<header class="f-header"><div class="f-header__inner">'
            . '<a class="f-brand" href="/app">' . PublicChrome::LANTERN . '<span class="f-brand__name">فانوس</span></a>'
            . $workspace
            . '<nav class="f-nav" aria-label="بخش‌های اصلی">' . $items . '</nav>'
            // Today's points and coins, filled in by header-stats.js; hidden until it has them.
            . ($viewer->workspaceId === null ? '' : '<a class="f-stats" id="f-stats" href="/app/points" hidden>'
                . '<span class="f-stats__item f-stats__item--points" title="امتیاز امروز"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.6 5.6 6.1.7-4.5 4.2 1.2 6L12 16.6 6.6 19.5l1.2-6L3.3 9.3l6.1-.7z"/></svg><b id="f-stats-points"></b></span>'
                . '<span class="f-stats__item f-stats__item--coins" title="سکه"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 8v8M9.5 10h4a1.5 1.5 0 0 1 0 3h-3a1.5 1.5 0 0 0 0 3h4"/></svg><b id="f-stats-coins"></b></span>'
                . '</a>')
            // On a wide screen the account is the person, at the end of the
            // bar; on a phone it is the last tab of the bottom bar instead.
            . '<a class="f-account" href="/account"' . $current . '>'
            . '<span class="f-account__avatar" aria-hidden="true">' . $this->escape($initial) . '</span>'
            . '<span class="f-account__name">' . $this->escape($name) . '</span>'
            . '</a>'
            . '</div></header>';
    }

    /** The foot of every page: the mark, what FANOOS is, and the ways around it. */
    private function footer(?ViewerContext $viewer): string
    {
        $links = $viewer === null
            ? [['/', 'فانوس'], ['/login', 'ورود'], ['/register', 'ساخت حساب'], ['/support', 'پشتیبانی']]
            : ($viewer->workspaceId === null
                ? [['/app', 'خانه'], ['/account', 'حساب'], ['/support', 'پشتیبانی']]
                : [['/app/bank', 'بانک سؤال'], ['/app/lessons', 'درسنامه‌ها'], ['/app/references', 'منابع آزمون'], ['/app/announcements', 'اطلاعیه‌ها'], ['/app/exams', 'آزمون‌ها'], ['/app/exams/custom', 'آزمون دلخواه'], ['/app/exams/mistakes', 'مرور اشتباه‌ها'], ['/app/saved', 'ذخیره‌ها و یادداشت‌ها'], ['/app/plan', 'برنامه‌ی مطالعه'], ['/app/calendar', 'تقویم آزمون‌ها'], ['/app/timer', 'تایمر مطالعه'], ['/app/progress', 'پیشرفت'], ['/account', 'حساب'], ['/support', 'پشتیبانی']]);
        $nav = '';
        foreach ($links as [$href, $label]) {
            $nav .= '<a href="' . $this->escape($href) . '">' . $this->escape($label) . '</a>';
        }

        return '<footer class="f-footer"><div class="f-footer__inner">'
            . '<div class="f-footer__brand">' . PublicChrome::LANTERN
            . '<span><strong>فانوس</strong><small>فضای آموزشی دانشجو</small></span></div>'
            . '<nav class="f-footer__links" aria-label="پیوندهای پایین صفحه">' . $nav . '</nav>'
            . '</div></footer>';
    }

    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Embeds server-known data as a JSON `<script>` block a page script can
     * read with `JSON.parse(document.getElementById(id).textContent)`
     * instead of fetching it. JSON_HEX_TAG (plus the other HEX flags) is
     * what makes this safe to place inside a `<script>` element: it escapes
     * `<`, `>`, `&`, `'` and `"` to `\uXXXX` sequences, so a string value
     * containing literal `</script>` cannot close the tag early and inject
     * markup.
     *
     * @param mixed $data must be JSON-encodable
     */
    public function embedJson(string $id, mixed $data): string
    {
        $json = json_encode(
            $data,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        return '<script id="' . $this->escape($id) . '" type="application/json">' . $json . '</script>';
    }
}
