<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * اتاق مطالعه گروهی: the student's rooms, each with its members' study
 * today side by side, and the forms to make a room or join one. An invite
 * link is /app/rooms?join=<code>. Drawn by rooms.js from GET .../rooms.
 */
final class StudyRoomsPage
{
    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    public function render(ViewerContext $viewer): string
    {
        $main = <<<'HTML'
<header class="p-head">
    <h1>اتاق مطالعه گروهی</h1>
    <p class="f-muted">با چند دوست یک اتاق بساز و هر روز ببینید چقدر خوانده‌اید. اعضای یک اتاق نام و مطالعه‌ی امروز همدیگر را می‌بینند، نه بیشتر؛ کسی بیرون از اتاق چیزی نمی‌بیند.</p>
</header>

<div class="f-notice f-notice--error" id="rooms-error" hidden>
    <div class="f-notice__body"><p id="rooms-error-text"></p></div>
</div>
<div class="f-notice f-notice--success" id="rooms-done" hidden>
    <div class="f-notice__body"><p id="rooms-done-text"></p></div>
</div>

<div class="r-forms">
    <form class="f-card r-form" id="room-create" novalidate>
        <h2>اتاق تازه</h2>
        <label class="f-field"><span class="f-field__label">نام اتاق</span>
            <input class="f-input" name="name" maxlength="60" required placeholder="مثلاً کشیک‌های بی‌خواب"></label>
        <button class="f-btn f-btn--primary" type="submit">ساختن و گرفتن لینک دعوت</button>
    </form>
    <form class="f-card r-form" id="room-join" novalidate>
        <h2>پیوستن با لینک دعوت</h2>
        <label class="f-field"><span class="f-field__label">لینک یا کد دعوت</span>
            <input class="f-input" name="code" dir="ltr" autocomplete="off" required></label>
        <button class="f-btn f-btn--ghost" type="submit">پیوستن</button>
    </form>
</div>

<p class="f-tiny r-limits">هر اتاق تا ۱۰ نفر جا دارد و هر نفر می‌تواند تا ۱۰ اتاق داشته باشد.</p>

<div class="r-list" id="rooms" aria-busy="true"><p class="f-muted">در حال خواندن…</p></div>
HTML;

        return $this->renderer->render([
            'title' => 'اتاق مطالعه گروهی | فانوس',
            'description' => 'اتاق‌های مطالعه‌ی گروهی تو در فانوس.',
            'stylesheets' => ['/assets/web/pages/progress.css', '/assets/web/pages/rooms.css'],
            'modules' => ['/assets/web/pages/rooms.js'],
            'viewer' => $viewer,
            'activeNav' => 'progress',
        ], $main);
    }
}
