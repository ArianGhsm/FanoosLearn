<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * A real 404, with the chrome when we know who is looking.
 *
 * It says the address is wrong rather than implying the feature is missing:
 * the two are very different for someone following an old link, and guessing
 * wrong wastes their time.
 */
final class NotFoundPage
{
    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    public function render(?ViewerContext $viewer): string
    {
        $home = $viewer === null ? '/' : '/app';
        $main = '<div class="f-card f-empty">'
            . '<div class="f-empty__title">این نشانی وجود ندارد</div>'
            . '<p>ممکن است لینک قدیمی باشد یا آدرس اشتباه تایپ شده باشد.</p>'
            . '<p><a class="f-btn f-btn--primary" href="' . $this->renderer->escape($home) . '">بازگشت</a></p>'
            . '</div>';

        return $this->renderer->render([
            'title' => 'پیدا نشد | فانوس',
            'viewer' => $viewer,
        ], $main);
    }
}
