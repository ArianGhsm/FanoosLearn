<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * امتیاز روزانه: today's points against the goal, the student's place today,
 * this week and this month, the recent days, weeks and months, and coins.
 *
 * The frame is server-rendered; the numbers come from one call to
 * GET /workspaces/{id}/points (PointsService) and are drawn by points.js.
 */
final class PointsPage
{
    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    public function render(ViewerContext $viewer): string
    {
        $main = <<<'HTML'
<header class="p-head">
    <h1>امتیاز روزانه</h1>
    <p class="f-muted">هر پاسخ درست امتیاز دارد؛ سؤال سخت‌تر، امتیاز بیشتر. هر روز که به هدف برسی، یک سکه می‌گیری.</p>
</header>

<div class="f-notice f-notice--error" id="points-error" hidden>
    <div class="f-notice__body"><p id="points-error-text"></p></div>
</div>

<div class="pt-board" id="points-board" aria-busy="true">
    <section class="f-card pt-goal" aria-labelledby="goal-title">
        <div class="pt-goal__ring" id="goal-ring" role="img" aria-label="پیشرفت هدف امروز">
            <svg viewBox="0 0 120 120" aria-hidden="true">
                <circle class="pt-goal__track" cx="60" cy="60" r="52"/>
                <circle class="pt-goal__fill" id="goal-fill" cx="60" cy="60" r="52"/>
            </svg>
            <span class="pt-goal__value" id="goal-value">—</span>
        </div>
        <div class="pt-goal__text">
            <h2 id="goal-title">امروز</h2>
            <p class="pt-goal__line" id="goal-line"></p>
            <p class="f-tiny" id="goal-rule"></p>
        </div>
        <div class="pt-coins" title="سکه‌های تو">
            <span class="pt-coins__icon" aria-hidden="true"></span>
            <strong id="coins">—</strong>
            <span class="f-tiny">سکه</span>
        </div>
    </section>

    <section class="pt-ranks" id="ranks" aria-label="رتبه‌ها">
        <span class="p-skel"></span><span class="p-skel"></span><span class="p-skel"></span>
    </section>

    <section class="f-card p-panel" aria-labelledby="days-title">
        <div class="p-panel__head"><h2 id="days-title">هفت روز اخیر</h2></div>
        <div class="pt-bars" id="days"></div>
    </section>
    <section class="f-card p-panel" aria-labelledby="weeks-title">
        <div class="p-panel__head"><h2 id="weeks-title">هفت هفته‌ی اخیر</h2></div>
        <div class="pt-bars" id="weeks"></div>
    </section>
    <section class="f-card p-panel" aria-labelledby="months-title">
        <div class="p-panel__head"><h2 id="months-title">هفت ماه اخیر</h2></div>
        <div class="pt-bars" id="months"></div>
    </section>

    <section class="f-card p-panel pt-wide" aria-labelledby="shop-title" id="coin-shop">
        <div class="p-panel__head">
            <h2 id="shop-title">سکه‌هایت را خرج کن</h2>
            <span class="p-panel__hint" id="shop-balance"></span>
        </div>
        <p class="f-tiny">هر جعبه یک کد تخفیف شخصی می‌سازد که فقط با همین حساب و فقط یک بار، تا چند روز، کار می‌کند.</p>
        <ul class="pt-offers" id="offers"></ul>
        <div class="pt-mycodes" id="my-codes-box" hidden>
            <h3>کدهای تو</h3>
            <ul class="pt-mycodes__list" id="my-codes"></ul>
        </div>
    </section>

    <section class="f-card p-panel" aria-labelledby="rules-title">
        <div class="p-panel__head"><h2 id="rules-title">امتیاز چطور حساب می‌شود</h2></div>
        <ul class="pt-rules" id="rules"></ul>
    </section>
</div>
HTML;

        return $this->renderer->render([
            'title' => 'امتیاز روزانه | فانوس',
            'description' => 'امتیاز روزانه، رتبه و سکه‌های تو.',
            'stylesheets' => ['/assets/web/pages/progress.css', '/assets/web/pages/points.css'],
            'modules' => ['/assets/web/pages/points.js'],
            'viewer' => $viewer,
            'activeNav' => 'progress',
        ], $main);
    }
}
