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
     *     activeNav?: string
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
            '<meta name="theme-color" content="#f4f7fb">',
            '<title>' . $title . '</title>',
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

        $scripts = [];
        foreach ($page['modules'] ?? [] as $module) {
            $scripts[] = '<script type="module" src="' . $this->escape($this->assets->url($module)) . '"></script>';
        }

        $chrome = $viewer === null ? '' : $this->chrome($viewer, $page['activeNav'] ?? '');

        return '<!doctype html>' . "\n"
            . '<html lang="fa" dir="rtl">' . "\n"
            . '<head>' . "\n" . implode("\n", $head) . "\n" . '</head>' . "\n"
            . '<body' . ($bodyClasses === '' ? '' : ' class="' . $this->escape($bodyClasses) . '"') . '>' . "\n"
            . '<a class="f-skip" href="#main">رفتن به محتوای اصلی</a>' . "\n"
            . $chrome
            . '<main id="main" class="f-page">' . "\n" . $mainHtml . "\n" . '</main>' . "\n"
            . '<footer class="f-footer">فانوس — فضای آموزشی دانشجو</footer>' . "\n"
            . implode("\n", $scripts) . "\n"
            . '<noscript><div class="f-notice f-notice--warning"><div class="f-notice__body">'
            . 'بعضی بخش‌های فانوس بدون JavaScript کار نمی‌کنند. لطفاً آن را روشن کنید.'
            . '</div></div></noscript>' . "\n"
            . '</body>' . "\n" . '</html>' . "\n";
    }

    private function chrome(ViewerContext $viewer, string $activeNav): string
    {
        $items = '';
        foreach ($viewer->navigation() as $item) {
            $current = $item['key'] === $activeNav ? ' aria-current="page"' : '';
            $items .= '<a class="f-nav__link" href="' . $this->escape($item['href']) . '"' . $current . '>'
                . '<span class="f-nav__icon" aria-hidden="true">' . $this->escape($item['icon']) . '</span>'
                . '<span>' . $this->escape($item['label']) . '</span>'
                . '</a>';
        }

        $workspaceLine = $viewer->workspaceName === null
            ? ''
            : '<span class="f-brand__workspace">' . $this->escape($viewer->workspaceName) . '</span>';

        return '<header class="f-header">'
            . '<a class="f-brand" href="/app">'
            . '<span class="f-brand__mark" aria-hidden="true"></span>'
            . '<span>فانوس</span>' . $workspaceLine
            . '</a>'
            . '<span class="f-header__spacer"></span>'
            . '<nav class="f-nav" aria-label="بخش‌های اصلی">' . $items . '</nav>'
            . '<div class="f-account">'
            . '<span class="f-account__name">' . $this->escape($viewer->displayName) . '</span>'
            . '<a class="f-btn f-btn--ghost" href="/account">حساب</a>'
            . '</div>'
            . '</header>';
    }

    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
