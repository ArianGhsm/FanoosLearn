<?php

declare(strict_types=1);

?><!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#0b4a45">
    <title>فانوس | فضای یادگیری</title>
    <link rel="stylesheet" href="/assets/app.css">
    <script src="/assets/app.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main-content">رفتن به محتوای اصلی</a>
<div class="ambient ambient-a" aria-hidden="true"></div>
<div class="ambient ambient-b" aria-hidden="true"></div>

<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="/" aria-label="فانوس، صفحه اصلی">
            <span class="brand-mark" aria-hidden="true">ف</span>
            <span class="brand-copy">
                <strong>فانوس</strong>
                <small>فضای یادگیری شما</small>
            </span>
        </a>

        <div class="topbar-actions">
            <div class="workspace-picker" id="workspace-picker" hidden>
                <label for="workspace-select">فضای آموزشی</label>
                <select id="workspace-select" aria-describedby="workspace-hint"></select>
                <span class="visually-hidden" id="workspace-hint">فضای فعال، محتوای قابل مشاهده در صفحه را تعیین می‌کند.</span>
            </div>
            <button class="button button-quiet topbar-logout" id="logout" type="button" hidden>خروج</button>
        </div>
    </div>
</header>

<main class="site-main" id="main-content" tabindex="-1">
    <section class="welcome" id="login-panel" aria-labelledby="welcome-title">
        <div class="welcome-copy">
            <span class="eyebrow">یک حساب، همهٔ فضاهای آموزشی</span>
            <h1 id="welcome-title">مسیر دانشگاه را روشن‌تر ببین.</h1>
            <p>برنامه، نمرات، اطلاعیه‌ها، منابع و دسترسی‌های هر فضای آموزشی در یک نمای امن و منظم.</p>
            <ul class="trust-row" aria-label="ویژگی‌های دسترسی">
                <li>مرزبندی مستقل هر فضای آموزشی</li>
                <li>دسترسی مبتنی بر نقش</li>
                <li>اطلاعات یکپارچه میان کانال‌ها</li>
            </ul>
        </div>

        <form class="login-card" id="login-form" aria-describedby="login-message">
            <div class="login-card-heading">
                <span class="eyebrow">ورود به حساب</span>
                <h2>خوش آمدی</h2>
                <p>با اطلاعات حساب فانوس وارد شو.</p>
            </div>
            <label class="field-label" for="login-identifier">
                <span>ایمیل، موبایل یا شناسه</span>
                <input id="login-identifier" name="identifier" autocomplete="username" inputmode="text" required>
            </label>
            <label class="field-label" for="login-password">
                <span>رمز عبور</span>
                <input id="login-password" name="password" type="password" autocomplete="current-password" required>
            </label>
            <button class="button button-primary login-submit" type="submit">ورود امن</button>
            <p class="form-message" id="login-message" role="status" aria-live="polite"></p>
        </form>
    </section>

    <section class="dashboard" id="dashboard" aria-labelledby="greeting" hidden>
        <header class="dashboard-heading">
            <div class="dashboard-heading-copy">
                <span class="eyebrow">امروز در فانوس</span>
                <h1 id="greeting">فضای یادگیری</h1>
                <p class="workspace-path" id="workspace-path"></p>
            </div>
            <div class="date-chip" id="today" aria-label="تاریخ امروز"></div>
        </header>

        <nav class="module-grid" aria-label="بخش‌های فانوس">
            <button data-view="schedule" type="button">
                <span class="module-icon module-icon-mint" aria-hidden="true"><svg focusable="false"><use href="/assets/web-shell/icons.svg#calendar"></use></svg></span>
                <span class="module-copy"><strong>برنامه</strong><small>کلاس‌ها، آزمون‌ها و مهلت‌ها</small></span>
            </button>
            <button data-view="grades" type="button">
                <span class="module-icon module-icon-amber" aria-hidden="true"><svg focusable="false"><use href="/assets/web-shell/icons.svg#grades"></use></svg></span>
                <span class="module-copy"><strong>نمرات</strong><small>نمرات منتشرشدهٔ حساب شما</small></span>
            </button>
            <button data-view="announcements" type="button">
                <span class="module-icon module-icon-coral" aria-hidden="true"><svg focusable="false"><use href="/assets/web-shell/icons.svg#announcement"></use></svg></span>
                <span class="module-copy"><strong>اطلاعیه‌ها</strong><small>پیام‌های فضای آموزشی فعال</small></span>
            </button>
            <button data-view="academics" type="button">
                <span class="module-icon module-icon-blue" aria-hidden="true"><svg focusable="false"><use href="/assets/web-shell/icons.svg#academics"></use></svg></span>
                <span class="module-copy"><strong>فضای آموزشی</strong><small>ترم، درس و جلسه‌ها</small></span>
            </button>
            <button data-view="resources" type="button">
                <span class="module-icon module-icon-mint" aria-hidden="true"><svg focusable="false"><use href="/assets/web-shell/icons.svg#resources"></use></svg></span>
                <span class="module-copy"><strong>منابع</strong><small>جزوه، خلاصه و بانک سؤال</small></span>
            </button>
            <button data-view="assessments" type="button">
                <span class="module-icon module-icon-violet" aria-hidden="true"><svg focusable="false"><use href="/assets/web-shell/icons.svg#assessment"></use></svg></span>
                <span class="module-copy"><strong>تمرین و آزمون</strong><small>تمرین‌ها و آزمون‌های فعال</small></span>
            </button>
            <button data-view="forms" type="button">
                <span class="module-icon module-icon-slate" aria-hidden="true"><svg focusable="false"><use href="/assets/web-shell/icons.svg#forms"></use></svg></span>
                <span class="module-copy"><strong>فرم‌ها</strong><small>فرم‌های فعال و پاسخ‌ها</small></span>
            </button>
            <button data-view="orders" type="button">
                <span class="module-icon module-icon-gold" aria-hidden="true"><svg focusable="false"><use href="/assets/web-shell/icons.svg#access"></use></svg></span>
                <span class="module-copy"><strong>خرید و دسترسی</strong><small>سفارش‌ها و دسترسی‌های شما</small></span>
            </button>
        </nav>

        <section class="content-panel" aria-labelledby="view-title">
            <div class="content-toolbar">
                <div class="content-heading">
                    <span class="eyebrow">نمای انتخاب‌شده</span>
                    <h2 id="view-title">برنامه و آزمون‌ها</h2>
                </div>
                <form class="search-form" id="search-form" role="search">
                    <label class="visually-hidden" for="workspace-search">جست‌وجو در فضای آموزشی فعال</label>
                    <input id="workspace-search" name="q" minlength="2" placeholder="جست‌وجو در فضای فعال…" enterkeyhint="search">
                    <button class="button button-secondary" type="submit">جست‌وجو</button>
                </form>
            </div>
            <div class="result-list" id="result-list" aria-live="polite" aria-relevant="additions text">
                <div class="empty-state">یکی از بخش‌ها را انتخاب کن.</div>
            </div>
        </section>
    </section>
</main>

<footer class="site-footer">
    <span>FanoosLearn</span>
    <span>مرز دسترسی: فضای آموزشی فعال</span>
</footer>
</body>
</html>
