<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * تابلو اعلانات: the workspace's announcements, newest first, marked read as
 * they are seen; and, for whoever may broadcast (notification.broadcast), a
 * composer. Publishing goes through WorkspacePlatformService, so an
 * announcement also reaches the bots like every other one.
 */
final class AnnouncementsPage
{
    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    public function render(ViewerContext $viewer): string
    {
        $composer = !$viewer->can('notification.broadcast') ? '' : <<<'HTML'
<form class="f-card b-panel n-compose" id="compose" novalidate>
    <h2>اطلاعیه‌ی تازه</h2>
    <input class="f-input" id="compose-title" maxlength="200" placeholder="عنوان" aria-label="عنوان" required>
    <textarea class="f-input" id="compose-body" rows="4" placeholder="متن اطلاعیه" aria-label="متن" required></textarea>
    <div class="b-actions"><button class="f-btn f-btn--primary" type="submit" id="compose-send">انتشار برای همه</button></div>
    <p class="f-tiny" id="compose-status" aria-live="polite"></p>
</form>
HTML;

        $main = <<<HTML
<header class="b-head">
    <h1>اطلاعیه‌ها</h1>
    <p class="f-muted">خبرهای فانوس: سؤال‌های تازه، منابع سال جدید و تغییرات سایت.</p>
</header>
<div class="f-notice f-notice--error" id="news-error" hidden>
    <div class="f-notice__body"><p id="news-error-text"></p></div>
</div>
{$composer}
<div id="news" aria-busy="true" aria-live="polite"><div class="x-skeleton" aria-hidden="true"></div></div>
HTML;

        return $this->renderer->render([
            'title' => 'اطلاعیه‌ها | فانوس',
            'description' => 'اطلاعیه‌های فانوس.',
            'stylesheets' => ['/assets/web/pages/exams.css', '/assets/web/pages/bank.css', '/assets/web/pages/lessons.css'],
            'modules' => ['/assets/web/pages/announcements.js'],
            'viewer' => $viewer,
            'activeNav' => 'home',
        ], $main);
    }
}
