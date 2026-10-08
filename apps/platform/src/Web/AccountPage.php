<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * The account page -- the student's panel: who you are, the places a
 * student goes for their own record, the account's settings, and the way
 * out.
 *
 * Laid out like the panel of the exam apps students already use (owner,
 * 2026-10-08: "پنل کاربری به سبک مدوفست"): a profile card on top, then one
 * list of rows, each with an icon, a name and a chevron. The settings that
 * need a form (bots, phone, password) are rows that fold open in place, so
 * the page reads as a menu rather than a stack of forms.
 *
 * Signing out is not a nicety: on a shared or borrowed device it is the only
 * way to end a session, so it is always the last row and never folded away.
 */
final class AccountPage
{
    private const ICONS = [
        'progress' => '<path d="M4 4v16h16"/><path d="m7.5 14.5 3.5-3.5 3 3 5-5.5"/>',
        'points' => '<path d="m12 3 2.6 5.6 6.1.7-4.5 4.2 1.2 6L12 16.6 6.6 19.5l1.2-6L3.3 9.3l6.1-.7z"/>',
        'saved' => '<path d="M6 4h12v17l-6-4-6 4z"/>',
        'store' => '<path d="M5 8h14l-1.1 11.1a1 1 0 0 1-1 .9H7.1a1 1 0 0 1-1-.9z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/>',
        'earn' => '<path d="M12 3v18M8 7h6a3 3 0 0 1 0 6H9a3 3 0 0 0 0 6h7"/>',
        'support' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6M12 17h.01"/>',
        'bot' => '<rect x="5" y="8" width="14" height="11" rx="3"/><path d="M12 4v4M9 13h.01M15 13h.01"/>',
        'phone' => '<rect x="7" y="3" width="10" height="18" rx="2.5"/><path d="M11 17h2"/>',
        'lock' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
        'switch' => '<path d="M4 8h13l-3-3M20 16H7l3 3"/>',
        'exit' => '<path d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4"/><path d="M10 16l-4-4 4-4M6 12h10"/>',
    ];

    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    /**
     * @param array<string, string> $bots platform => bot username, for the
     *     connect buttons' deep links; a platform without one gets no button
     */
    public function render(ViewerContext $viewer, array $bots = []): string
    {
        $botAttributes = '';
        foreach (['bale', 'telegram'] as $platform) {
            $username = (string) ($bots[$platform] ?? '');
            if (preg_match('/^[A-Za-z][A-Za-z0-9_]{3,31}$/', $username) === 1) {
                $botAttributes .= ' data-' . $platform . '-bot="' . $this->renderer->escape($username) . '"';
            }
        }

        $name = $this->renderer->escape($viewer->displayName);
        $initial = $this->renderer->escape(mb_substr(trim($viewer->displayName), 0, 1) ?: 'ف');
        $workspace = $viewer->workspaceName === null
            ? 'هنوز فضای آموزشی‌ای انتخاب نکرده‌ای'
            : $this->renderer->escape($viewer->workspaceName);

        // Rows that lead to a student's own record; the workspace-scoped ones
        // only once there is a workspace for them to read.
        $links = [];
        if ($viewer->workspaceId !== null) {
            $links[] = $this->linkRow('/app/progress', 'progress', 'subject', 'پیشرفت من', 'نمودار و گزارش مطالعه');
            $links[] = $this->linkRow('/app/points', 'points', 'warning', 'امتیاز و رتبه', 'هدف روزانه، سکه و رتبه‌ات');
            $links[] = $this->linkRow('/app/saved', 'saved', 'source', 'ذخیره‌ها', 'بوکمارک، یادداشت و هایلایت');
            $links[] = $this->linkRow('/app/store', 'store', 'success', 'اشتراک و خریدها', 'فروشگاه و کد تخفیف');
            $links[] = $this->linkRow('/app/affiliate', 'earn', 'accent', 'کسب درآمد', 'لینک معرفی و پورسانت');
        }
        $links[] = $this->linkRow('/support', 'support', 'tag', 'پشتیبانی', 'سؤال و راهنما');
        $linkRows = implode("\n", $links);

        $botIcon = $this->icon('bot');
        $phoneIcon = $this->icon('phone');
        $lockIcon = $this->icon('lock');
        $switchIcon = $this->icon('switch');
        $exitIcon = $this->icon('exit');
        $chevron = $this->chevron();

        $main = <<<HTML
<h1 class="a-title">پنل کاربری</h1>

<section class="a-profile" aria-label="حساب">
    <span class="a-profile__avatar" aria-hidden="true">{$initial}</span>
    <div class="a-profile__who">
        <p class="a-profile__name">{$name}</p>
        <p class="a-profile__space">{$workspace}</p>
    </div>
    <a class="a-profile__switch" href="/app?switch=1">{$switchIcon}<span>تغییر فضا</span></a>
</section>

<nav class="a-list" aria-label="بخش‌های من">
{$linkRows}
</nav>

<h2 class="a-list__title">تنظیمات حساب</h2>
<div class="a-list">
    <details class="a-row a-row--fold" id="bots"{$botAttributes}>
        <summary class="a-row__head">
            <span class="a-row__icon" data-hue="tag">{$botIcon}</span>
            <span class="a-row__text"><span class="a-row__label" id="bots-title">اتصال به ربات</span><span class="a-row__hint">بله و تلگرام، با همین حساب</span></span>
            {$chevron}
        </summary>
        <div class="a-row__body">
            <p class="f-muted">با اتصال، همین حساب در ربات هم باز است: همان آزمون‌ها، همان پیشرفت، همان خریدها.</p>
            <div class="f-notice f-notice--error" id="bots-error" hidden>
                <div class="f-notice__body"><p id="bots-error-text"></p></div>
            </div>
            <div class="a-bots" id="bots-list" aria-busy="true"><p class="f-muted">در حال خواندن…</p></div>
        </div>
    </details>

    <details class="a-row a-row--fold">
        <summary class="a-row__head">
            <span class="a-row__icon" data-hue="subject">{$phoneIcon}</span>
            <span class="a-row__text"><span class="a-row__label" id="phone-title">شماره موبایل</span><span class="a-row__hint" id="phone-value" dir="auto">…</span></span>
            {$chevron}
        </summary>
        <div class="a-row__body">
            <p class="f-muted">اختیاری است. با آن می‌توانی بعداً همین حساب را به ربات فانوس هم وصل کنی.</p>

            <div class="f-notice f-notice--error" id="phone-error" hidden>
                <div class="f-notice__body"><p id="phone-error-text"></p></div>
            </div>
            <div class="f-notice f-notice--success" id="phone-done" hidden>
                <div class="f-notice__body"><p>شماره ثبت شد.</p></div>
            </div>

            <form id="phone-form" class="a-account__inline" novalidate>
                <label class="f-field" for="phone">
                    <span class="f-field__label">شماره‌ی تازه</span>
                    <input class="f-input" id="phone" name="phone" type="tel" inputmode="numeric"
                           autocomplete="tel" placeholder="۰۹۱۲۳۴۵۶۷۸۹" dir="ltr" required>
                </label>
                <button class="f-btn f-btn--ghost" type="submit">فرستادن کد</button>
            </form>
            <form id="phone-code-form" class="a-account__inline" novalidate hidden>
                <label class="f-field" for="phone-code">
                    <span class="f-field__label">کدی که پیامک شد</span>
                    <input class="f-input" id="phone-code" name="code" type="text" inputmode="numeric"
                           autocomplete="one-time-code" maxlength="6" dir="ltr" required>
                </label>
                <button class="f-btn f-btn--primary" type="submit">تأیید</button>
            </form>
        </div>
    </details>

    <details class="a-row a-row--fold">
        <summary class="a-row__head">
            <span class="a-row__icon" data-hue="difficulty">{$lockIcon}</span>
            <span class="a-row__text"><span class="a-row__label">گذرواژه</span><span class="a-row__hint">عوض کردن گذرواژه</span></span>
            {$chevron}
        </summary>
        <div class="a-row__body">
            <div class="f-notice f-notice--error" id="password-error" hidden>
                <div class="f-notice__body"><p id="password-error-text"></p></div>
            </div>
            <div class="f-notice f-notice--success" id="password-done" hidden>
                <div class="f-notice__body"><p>گذرواژه عوض شد.</p></div>
            </div>

            <form id="password-form" novalidate>
                <label class="f-field" for="new-password">
                    <span class="f-field__label">گذرواژه تازه</span>
                    <input class="f-input" id="new-password" name="new_password" type="password"
                           autocomplete="new-password" required>
                    <span class="f-field__hint">دست‌کم ۸ نویسه.</span>
                </label>
                <label class="f-field" for="repeat-password">
                    <span class="f-field__label">تکرار گذرواژه تازه</span>
                    <input class="f-input" id="repeat-password" name="repeat_password" type="password"
                           autocomplete="new-password" required>
                </label>
                <button class="f-btn f-btn--primary" type="submit" id="password-submit">ثبت گذرواژه</button>
            </form>
        </div>
    </details>
</div>

<div class="a-list a-list--exit">
    <div class="f-notice f-notice--error" id="signout-error" hidden>
        <div class="f-notice__body"><p id="signout-error-text"></p></div>
    </div>
    <button class="a-row a-row__head a-account__signout" type="button" id="signout">
        <span class="a-row__icon" data-hue="danger">{$exitIcon}</span>
        <span class="a-row__text"><span class="a-row__label">خروج از حساب</span><span class="a-row__hint">روی دستگاه مشترک حتماً خارج شو</span></span>
    </button>
</div>
HTML;

        return $this->renderer->render([
            'title' => 'پنل کاربری | فانوس',
            'description' => 'حساب کاربری شما در فانوس.',
            'stylesheets' => ['/assets/web/pages/account.css'],
            'modules' => ['/assets/web/pages/account.js'],
            'viewer' => $viewer,
            'activeNav' => 'account',
        ], $main);
    }

    private function linkRow(string $href, string $icon, string $hue, string $label, string $hint): string
    {
        return '<a class="a-row a-row__head" href="' . $this->renderer->escape($href) . '">'
            . '<span class="a-row__icon" data-hue="' . $hue . '">' . $this->icon($icon) . '</span>'
            . '<span class="a-row__text"><span class="a-row__label">' . $this->renderer->escape($label) . '</span>'
            . '<span class="a-row__hint">' . $this->renderer->escape($hint) . '</span></span>'
            . $this->chevron()
            . '</a>';
    }

    private function icon(string $name): string
    {
        return '<svg viewBox="0 0 24 24" aria-hidden="true">' . self::ICONS[$name] . '</svg>';
    }

    private function chevron(): string
    {
        return '<svg class="a-row__chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>';
    }
}
