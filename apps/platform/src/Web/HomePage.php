<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * The signed-in home.
 *
 * With a workspace selected it is a starting point: who you are and in which
 * field, the two things you came to do, and the courses to pick from --
 * filled in by the page script from the same catalogue API the exams page
 * reads, so the numbers are never a second, drifting copy. Without one it is
 * the workspace chooser.
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
     * @param array<string, mixed>|null $profile the website sign-up profile, if any
     * @param bool $welcome the visitor has just created their account
     */
    public function render(ViewerContext $viewer, bool $forceChooser = false, ?array $profile = null, bool $welcome = false): string
    {
        $firstName = trim((string) ($profile['first_name'] ?? ''));
        $name = $this->renderer->escape($firstName !== '' ? $firstName : $viewer->displayName);

        $facts = [];
        if (($profile['discipline_name'] ?? '') !== '') {
            $facts[] = '<span class="f-hello__fact"><i>رشته</i>' . $this->renderer->escape((string) $profile['discipline_name']) . '</span>';
        }
        if (($profile['institution_name'] ?? '') !== '') {
            $facts[] = '<span class="f-hello__fact"><i>دانشگاه</i>' . $this->renderer->escape((string) $profile['institution_name']) . '</span>';
        }
        if ($viewer->workspaceName !== null && $viewer->workspaceName !== '') {
            $facts[] = '<span class="f-hello__fact"><i>فضا</i>' . $this->renderer->escape($viewer->workspaceName) . '</span>';
        }
        $factsHtml = $facts === [] ? '' : '<div class="f-hello__facts">' . implode('', $facts) . '</div>';

        $lede = $welcome
            ? 'حسابت ساخته شد. آزمون‌های رشته‌ات آماده‌اند؛ از هر درسی که دوست داری شروع کن.'
            : 'امروز سراغ کدام درس برویم؟';

        $chooser = $viewer->workspaceId === null || $forceChooser;
        $lantern = PublicChrome::LANTERN;

        $hello = <<<HTML
<section class="f-hello">
    <div class="f-hello__text">
        <h1 class="f-hello__title">سلام، {$name}</h1>
        <p class="f-hello__lede">{$lede}</p>
        {$factsHtml}
    </div>
    <div class="f-hello__mark" aria-hidden="true">{$lantern}</div>
</section>
HTML;

        $body = $chooser
            ? <<<'HTML'
<section class="f-card f-home__panel" aria-labelledby="pick-title">
    <h2 id="pick-title">فضای آموزشی‌ات را انتخاب کن</h2>
    <p class="f-muted">برای دیدن آزمون‌ها و بقیه بخش‌ها، اول مشخص کن کدام فضا.</p>
    <div id="workspace-list" aria-busy="true">
        <p class="f-muted">در حال خواندن فهرست…</p>
    </div>
</section>
HTML
            : <<<'HTML'
<section class="f-actions" aria-label="شروع">
    <a class="f-action f-action--primary" href="/app/exams">
        <span class="f-action__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9"/></svg></span>
        <span class="f-action__body"><strong>آزمون‌ها</strong><span>درس را انتخاب کن و تمرین را شروع کن</span></span>
        <span class="f-action__go" aria-hidden="true">←</span>
    </a>
    <a class="f-action" href="/app/exams/mistakes">
        <span class="f-action__icon f-action__icon--review" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 3-6.7M3 4v5h5"/><path d="M12 8v4l3 2"/></svg></span>
        <span class="f-action__body"><strong>مرور اشتباه‌ها</strong><span>فقط سؤال‌هایی که غلط زده‌ای</span></span>
        <span class="f-action__go" aria-hidden="true">←</span>
    </a>
</section>

<section class="f-home__courses" aria-labelledby="courses-title">
    <div class="f-home__head">
        <h2 id="courses-title">درس‌ها</h2>
        <a href="/app/exams" class="f-home__all">همه‌ی درس‌ها</a>
    </div>
    <div class="f-courses-grid" id="home-courses" aria-busy="true">
        <span class="f-course-skel"></span><span class="f-course-skel"></span><span class="f-course-skel"></span>
        <span class="f-course-skel"></span><span class="f-course-skel"></span><span class="f-course-skel"></span>
    </div>
</section>

<p class="f-tiny f-home__switch">
    فضای دیگری هم داری؟ <a href="/app?switch=1">فضای آموزشی را عوض کن</a>
</p>
HTML;

        return $this->renderer->render([
            'title' => 'خانه | فانوس',
            'description' => 'فضای آموزشی شما در فانوس.',
            'stylesheets' => ['/assets/web/pages/public.css', '/assets/web/pages/home.css'],
            'modules' => ['/assets/web/pages/home.js'],
            'viewer' => $viewer,
            'activeNav' => 'home',
        ], $hello . "\n" . $body);
    }
}
