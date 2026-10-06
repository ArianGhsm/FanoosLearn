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

        $hello = <<<HTML
<section class="f-hello">
    <div class="f-hello__text">
        <h1 class="f-hello__title">سلام، {$name}</h1>
        <p class="f-hello__lede">{$lede}</p>
    </div>
    {$factsHtml}
</section>
HTML;

        $today = <<<'HTML'
<section class="f-today" id="today" aria-label="امروز" hidden>
    <a class="f-today__item" href="/app/points">
        <span class="f-today__label">امتیاز امروز</span>
        <strong class="f-today__value" id="today-points">—</strong>
        <span class="f-today__bar" aria-hidden="true"><i id="today-bar"></i></span>
    </a>
    <a class="f-today__item" href="/app/progress#review">
        <span class="f-today__label">مرور امروز</span>
        <strong class="f-today__value" id="today-review">—</strong>
        <span class="f-tiny" id="today-review-note"></span>
    </a>
    <a class="f-today__item f-today__item--coins" href="/app/points#coin-shop">
        <span class="f-today__label">سکه</span>
        <strong class="f-today__value" id="today-coins">—</strong>
        <span class="f-tiny">خرج کردن</span>
    </a>
</section>
HTML;
        $menu = $this->menu();

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
            : <<<HTML
{$today}
{$menu}
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

    /**
     * منوی اصلی: every part of the site as a tile, in four groups, the way a
     * student looks for it -- the bank and exams, studying, their own
     * record, and the rest. One list, so a new section is added in one place.
     */
    private function menu(): string
    {
        $groups = [
            'بانک و آزمون' => [
                ['/app/bank', 'بانک سؤال', 'درس به درس، سال به سال', 'subject', '<path d="M4 5.5A1.5 1.5 0 0 1 5.5 4H10v16H5.5A1.5 1.5 0 0 1 4 18.5z"/><path d="M10 4h4v16h-4z"/><path d="m14.5 4.6 3.9-1 2.6 15.4-3.9 1z"/>'],
                ['/app/exams', 'آزمون‌ها', 'آزمون‌های سال‌های قبل', 'accent', '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h3"/>'],
                ['/app/exams/custom', 'آزمون‌ساز', 'درس، مبحث و تعداد دلخواه', 'tag', '<path d="M4 6h10M4 12h16M4 18h7"/><circle cx="17" cy="6" r="2"/><circle cx="14" cy="18" r="2"/>'],
                ['/app/calendar', 'تقویم آزمون‌ها', 'آزمون‌های زمان‌دار', 'source', '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/>'],
                ['/app/references', 'منابع آزمون', 'کتاب و فصل هر سال', 'difficulty', '<path d="M5 4h11a3 3 0 0 1 3 3v13H8a3 3 0 0 1-3-3z"/><path d="M5 17a3 3 0 0 1 3-3h11"/>'],
            ],
            'مطالعه' => [
                ['/app/exams/mistakes', 'مرور اشتباه‌ها', 'هر چه غلط زده‌ای', 'danger', '<path d="M3 12a9 9 0 1 0 3-6.7M3 4v5h5"/><path d="M12 8v4l3 2"/>'],
                ['/app/plan', 'برنامه‌ی مطالعه', 'روز به روز تا آزمون', 'success', '<path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9"/>'],
                ['/app/timer', 'تایمر مطالعه', 'بلوک‌های تمرکز', 'warning', '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2.5M9 2h6"/>'],
                ['/app/lessons', 'درسنامه‌ها', 'خلاصه و درسنامه', 'subject', '<path d="M12 6c-2-1.5-5-2-8-1.5v13c3-.5 6 0 8 1.5 2-1.5 5-2 8-1.5v-13c-3-.5-6 0-8 1.5z"/><path d="M12 6v13"/>'],
            ],
            'من' => [
                ['/app/progress', 'پیشرفت', 'نمودار و گزارش', 'accent', '<path d="M4 4v16h16"/><path d="m7.5 14.5 3.5-3.5 3 3 5-5.5"/>'],
                ['/app/points', 'امتیاز و رتبه', 'هدف روزانه و سکه', 'warning', '<path d="m12 3 2.6 5.6 6.1.7-4.5 4.2 1.2 6L12 16.6 6.6 19.5l1.2-6L3.3 9.3l6.1-.7z"/>'],
                ['/app/rooms', 'اتاق مطالعه', 'با دوستانت بخوان', 'tag', '<circle cx="9" cy="9" r="3"/><circle cx="17" cy="10" r="2.5"/><path d="M3.5 19a5.5 5.5 0 0 1 11 0M14 19a4 4 0 0 1 7 0"/>'],
                ['/app/saved', 'ذخیره‌ها', 'بوکمارک، یادداشت، هایلایت', 'source', '<path d="M6 4h12v17l-6-4-6 4z"/>'],
            ],
            'بیشتر' => [
                ['/app/store', 'فروشگاه', 'اشتراک و کد تخفیف', 'success', '<path d="M5 8h14l-1.1 11.1a1 1 0 0 1-1 .9H7.1a1 1 0 0 1-1-.9z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/>'],
                ['/app/announcements', 'اطلاعیه‌ها', 'خبرها و تغییرات', 'difficulty', '<path d="M4 10v4h3l6 4V6L7 10z"/><path d="M17 9a4 4 0 0 1 0 6"/>'],
                ['/app/affiliate', 'کسب درآمد', 'معرفی فانوس به دوستان', 'accent', '<path d="M12 3v18M8 7h6a3 3 0 0 1 0 6H9a3 3 0 0 0 0 6h7"/>'],
                ['/support', 'پشتیبانی', 'سؤال و راهنما', 'subject', '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6M12 17h.01"/>'],
            ],
        ];

        $html = '<nav class="f-menu" aria-labelledby="menu-title"><h2 class="f-menu__title" id="menu-title">منوی اصلی</h2>';
        foreach ($groups as $group => $tiles) {
            $html .= '<section class="f-menu__group"><h3 class="f-menu__group-title">' . $this->renderer->escape($group) . '</h3><div class="f-menu__grid">';
            foreach ($tiles as [$href, $label, $hint, $hue, $icon]) {
                $html .= '<a class="f-tile" href="' . $this->renderer->escape($href) . '" data-hue="' . $hue . '">'
                    . '<span class="f-tile__icon" aria-hidden="true"><svg viewBox="0 0 24 24">' . $icon . '</svg></span>'
                    . '<span class="f-tile__label">' . $this->renderer->escape($label) . '</span>'
                    . '<span class="f-tile__hint">' . $this->renderer->escape($hint) . '</span>'
                    . '</a>';
            }
            $html .= '</div></section>';
        }

        return $html . '</nav>';
    }
}
