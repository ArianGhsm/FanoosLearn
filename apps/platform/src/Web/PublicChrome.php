<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * The frame of the pages a signed-out visitor sees: the lantern mark, and
 * the two ways in. The signed-in chrome (PageRenderer::chrome) carries the
 * CSRF token and the navigation, and must never appear on these pages.
 */
final class PublicChrome
{
    /**
     * A lantern -- فانوس -- drawn as a mark rather than a picture: a cap, a
     * glass body and a flame, in the accent. Inline so the brand paints with
     * the first byte of the page and needs no request.
     */
    public const LANTERN = '<svg class="f-lantern" viewBox="0 0 32 32" aria-hidden="true" focusable="false">'
        . '<path d="M12 4.5h8M16 2.5v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/>'
        . '<path d="M10 8.5h12l-1.2 2H11.2z" fill="currentColor"/>'
        . '<rect x="9.5" y="10.5" width="13" height="14" rx="4" fill="currentColor" opacity=".18"/>'
        . '<rect x="9.5" y="10.5" width="13" height="14" rx="4" fill="none" stroke="currentColor" stroke-width="2"/>'
        . '<path class="f-lantern__flame" d="M16 13.5c2 2.2 2.6 3.8 2.6 5a2.6 2.6 0 0 1-5.2 0c0-1.2.6-2.8 2.6-5z" fill="currentColor"/>'
        . '<path d="M11 27.5h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'
        . '</svg>';

    /** @param 'landing'|'login'|'register' $active */
    public static function header(string $active): string
    {
        $login = $active === 'login'
            ? ''
            : '<a class="f-btn f-btn--ghost f-pub__link" href="/login">ورود</a>';
        $register = $active === 'register'
            ? ''
            : '<a class="f-btn f-btn--primary f-pub__link" href="/register">ساخت حساب</a>';

        return '<header class="f-pub">'
            . '<a class="f-pub__brand" href="/">' . self::LANTERN . '<span>فانوس</span></a>'
            . '<span class="f-pub__spacer"></span>'
            . $login . $register
            . '</header>';
    }
}
