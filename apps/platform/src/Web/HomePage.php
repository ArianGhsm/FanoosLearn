<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * The signed-in home.
 *
 * Its whole job is to get the visitor into a workspace and then out to the
 * area they came for. It renders the frame server-side and lets the page
 * script fill in the workspace list, so a slow API shows a real page with a
 * loading state rather than a blank screen.
 */
final class HomePage
{
    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    /**
     * @param bool $forceChooser the visitor explicitly asked to switch class
     *     (`/app?switch=1`), so show the chooser even though the session
     *     already has one selected.
     */
    public function render(ViewerContext $viewer, bool $forceChooser = false): string
    {
        $name = $this->renderer->escape($viewer->displayName);

        // Two different pages, really: "pick a workspace" and "here is your
        // workspace". Which one is live is decided by the server, from the
        // session, so neither flashes before the other.
        $body = ($viewer->workspaceId === null || $forceChooser)
            ? <<<'HTML'
<section class="f-card" aria-labelledby="pick-title">
    <h2 id="pick-title">فضای آموزشی‌ات را انتخاب کن</h2>
    <p class="f-muted">برای دیدن آزمون‌ها و بقیه بخش‌ها، اول مشخص کن کدام کلاس.</p>
    <div id="workspace-list" aria-busy="true">
        <p class="f-muted">در حال خواندن فهرست…</p>
    </div>
</section>
HTML
            : <<<'HTML'
<section class="f-card" aria-labelledby="area-title">
    <h2 id="area-title">از کجا ادامه بدهیم؟</h2>
    <div class="f-home__areas">
        <a class="f-home__area" href="/app/exams">
            <span class="f-home__area-icon" aria-hidden="true">📝</span>
            <span class="f-home__area-body">
                <strong>آزمون‌ها</strong>
                <span class="f-muted">تمرین کن و پاسخ‌های تشریحی را ببین.</span>
            </span>
        </a>
    </div>
    <p class="f-tiny f-home__switch">
        کلاس دیگری داری؟ <a href="/app?switch=1">فضای آموزشی را عوض کن</a>
    </p>
</section>
HTML;

        $main = '<h1 class="f-home__greeting">سلام، ' . $name . '</h1>' . "\n" . $body;

        return $this->renderer->render([
            'title' => 'خانه | فانوس',
            'description' => 'فضای آموزشی شما در فانوس.',
            'stylesheets' => ['/assets/web/pages/home.css'],
            'modules' => ['/assets/web/pages/home.js'],
            'viewer' => $viewer,
            'activeNav' => 'home',
        ], $main);
    }
}
