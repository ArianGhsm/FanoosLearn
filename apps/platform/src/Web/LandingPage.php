<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * The public front door, for someone who is not signed in.
 *
 * Deliberately short. It states what FANOOS is and sends the visitor to sign
 * in; it makes no claim about how many people use it, names no university,
 * and shows no numbers it cannot substantiate.
 */
final class LandingPage
{
    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    public function render(): string
    {
        $main = <<<'HTML'
<section class="f-landing__hero">
    <h1>دانشگاهت، مرتب‌تر از همیشه</h1>
    <p class="f-landing__lede">
        فانوس فضای آموزشی کلاس توست: آزمون، جزوه، اطلاعیه و برنامه، همه در یک جا
        و فقط برای کسانی که عضو همان کلاس‌اند.
    </p>
    <p>
        <a class="f-btn f-btn--primary" href="/login">ورود به فانوس</a>
    </p>
</section>

<section class="f-landing__points">
    <div class="f-card">
        <h2>هر کلاس، فضای خودش</h2>
        <p class="f-muted">
            هر ورودی هر دانشگاه فضای جداگانه‌ی خودش را دارد. چیزی از یک کلاس
            به کلاس دیگر نشت نمی‌کند.
        </p>
    </div>
    <div class="f-card">
        <h2>آزمون با پاسخ تشریحی</h2>
        <p class="f-muted">
            تمرین کن و بعد از ثبت، برای هر سؤال ببین چرا گزینه‌ی درست، درست
            است و بقیه چرا نه.
        </p>
    </div>
    <div class="f-card">
        <h2>نماینده‌ی کلاس، دستش باز</h2>
        <p class="f-muted">
            اطلاعیه، تاریخ ترم و عضوگیری را نماینده‌ی همان کلاس اداره می‌کند،
            بدون واسطه.
        </p>
    </div>
</section>
HTML;

        return $this->renderer->render([
            'title' => 'فانوس | فضای آموزشی دانشجو',
            'description' => 'فانوس؛ آزمون، جزوه، اطلاعیه و برنامه‌ی کلاس، در یک فضای اختصاصی برای هر ورودی.',
            'bodyClass' => 'f-landing-page',
            'stylesheets' => ['/assets/web/pages/landing.css'],
        ], $main);
    }
}
