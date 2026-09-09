<?php
declare(strict_types=1);
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#f7f8f3">
    <title>فانوس | فضای دانشجویی</title>
    <link rel="stylesheet" href="/assets/app.css">
    <link rel="stylesheet" href="/assets/domain-ux.css">
    <link rel="stylesheet" href="/assets/ui-v2/product-shell.css">
    <link rel="stylesheet" href="/assets/ui-v2/product-shell-responsive.css">
    <link rel="stylesheet" href="/assets/ui-v2/product-ui.css">
</head>
<body>
<a class="skip-link" href="#main-content">رفتن به محتوای اصلی</a>

<header class="site-header" aria-label="سربرگ فانوس">
    <div class="site-header__inner">
        <a class="brand" href="#/home" data-route="home" aria-label="فانوس؛ خانه">
            <span class="brand-mark" aria-hidden="true">ف</span>
            <span class="brand-copy"><strong>فانوس</strong><small>فضای آموزشی دانشجو</small></span>
        </a>

        <div class="header-context" id="workspace-picker" hidden>
            <label class="sr-only" for="workspace-select">فضای آموزشی فعال</label>
            <span class="header-context__label" aria-hidden="true">فضای آموزشی</span>
            <select id="workspace-select" class="workspace-select" aria-describedby="workspace-switch-hint"></select>
            <span class="sr-only" id="workspace-switch-hint">تغییر این گزینه، فضای آموزشی فعال حساب را در سرور تغییر می‌دهد.</span>
        </div>

        <div class="header-actions" id="header-actions" hidden>
            <button class="icon-button global-search-trigger" type="button" data-route="search" aria-label="جست‌وجو در فضای آموزشی" title="جست‌وجو">
                <svg aria-hidden="true" focusable="false"><use href="/assets/web-shell/icons.svg#search"></use></svg>
            </button>
            <button class="account-trigger" type="button" data-route="account">
                <span class="account-trigger__avatar" aria-hidden="true">ف</span>
                <span class="account-trigger__copy"><strong id="header-user-name">حساب</strong><small>حساب کاربری</small></span>
            </button>
            <button class="button button-quiet logout-button" id="logout" type="button">خروج</button>
        </div>
    </div>
</header>

<main class="site-main" id="main-content" tabindex="-1">
    <section class="login-shell" id="login-panel" aria-labelledby="login-title">
        <div class="login-intro">
            <span class="login-kicker">FANOOS</span>
            <h1 id="login-title">همه‌چیز دانشگاه، در یک فضای منظم</h1>
            <p>برنامه، درس‌ها، منابع، آزمون‌ها، نمرات و اطلاعیه‌های فضای آموزشی خودت را از یک حساب ببین.</p>
            <ul class="login-points" aria-label="ویژگی‌های فانوس">
                <li>اطلاعات هر فضای آموزشی کاملاً جدا نگه داشته می‌شود.</li>
                <li>دسترسی به منابع و پرداخت‌ها فقط از مسیر تأییدشده سرور انجام می‌شود.</li>
                <li>وب‌سایت، تلگرام و بله از یک حساب و یک منبع داده مشترک استفاده می‌کنند.</li>
            </ul>
        </div>
        <div class="login-card surface-card">
            <div class="login-card__heading">
                <span class="brand-mark brand-mark--large" aria-hidden="true">ف</span>
                <div><h2>ورود به فانوس</h2><p>با شناسه حساب خود وارد شوید.</p></div>
            </div>
            <form id="login-form" novalidate>
                <div class="field">
                    <label for="login-identifier">شناسه</label>
                    <input id="login-identifier" name="identifier" type="text" autocomplete="username" inputmode="text" required>
                </div>
                <div class="field">
                    <label for="login-password">رمز عبور</label>
                    <input id="login-password" name="password" type="password" autocomplete="current-password" required>
                </div>
                <button class="button button-primary button-block" type="submit">ورود</button>
            </form>
            <div class="login-message ui-state" id="login-message" aria-live="polite" role="status"></div>
        </div>
    </section>

    <section class="product-shell" id="dashboard" aria-labelledby="greeting" tabindex="-1" hidden>
        <aside class="desktop-sidebar" aria-label="ناوبری اصلی">
            <nav class="primary-nav" aria-label="بخش‌های فانوس">
                <button class="nav-item" type="button" data-route="home">
                    <svg aria-hidden="true" focusable="false"><use href="/assets/web-shell/icons.svg#home"></use></svg>
                    <span>خانه</span>
                </button>
                <button class="nav-item module-icon" type="button" data-view="academics">
                    <svg aria-hidden="true" focusable="false"><use href="/assets/web-shell/icons.svg#academics"></use></svg>
                    <span>درس‌ها</span>
                </button>
                <button class="nav-item" type="button" data-view="schedule">
                    <svg aria-hidden="true" focusable="false"><use href="/assets/web-shell/icons.svg#calendar"></use></svg>
                    <span>برنامه</span>
                </button>
                <button class="nav-item" type="button" data-view="resources">
                    <svg aria-hidden="true" focusable="false"><use href="/assets/web-shell/icons.svg#resources"></use></svg>
                    <span>یادگیری و منابع</span>
                </button>
                <button class="nav-item" type="button" data-view="assessments">
                    <svg aria-hidden="true" focusable="false"><use href="/assets/web-shell/icons.svg#assessment"></use></svg>
                    <span>آزمون‌ها</span>
                </button>
                <button class="nav-item" type="button" data-view="grades">
                    <svg aria-hidden="true" focusable="false"><use href="/assets/web-shell/icons.svg#grades"></use></svg>
                    <span>نمرات</span>
                </button>
                <button class="nav-item" type="button" data-view="announcements">
                    <svg aria-hidden="true" focusable="false"><use href="/assets/web-shell/icons.svg#announcement"></use></svg>
                    <span>اطلاعیه‌ها</span>
                    <span class="nav-badge" id="announcement-nav-badge" hidden></span>
                </button>
            </nav>

            <nav class="secondary-nav" aria-label="بخش‌های تکمیلی">
                <span class="nav-section-label">بیشتر</span>
                <button class="nav-item nav-item--secondary" type="button" data-view="forms">
                    <svg aria-hidden="true" focusable="false"><use href="/assets/web-shell/icons.svg#forms"></use></svg>
                    <span>فرم‌ها</span>
                </button>
                <button class="nav-item nav-item--secondary" type="button" data-view="orders">
                    <svg aria-hidden="true" focusable="false"><use href="/assets/web-shell/icons.svg#access"></use></svg>
                    <span>خرید و دسترسی</span>
                </button>
                <button class="nav-item nav-item--secondary" id="management-nav" type="button" data-route="management" hidden>
                    <svg aria-hidden="true" focusable="false"><use href="/assets/web-shell/icons.svg#manage"></use></svg>
                    <span>مدیریت</span>
                </button>
            </nav>

            <div class="sidebar-workspace-context">
                <span>فضای فعال</span>
                <strong id="sidebar-workspace-name">—</strong>
                <small id="workspace-path">—</small>
            </div>
        </aside>

        <div class="product-main">
            <header class="page-context">
                <div class="page-context__copy">
                    <p class="eyebrow" id="page-eyebrow">امروز</p>
                    <h1 id="greeting">خانه</h1>
                    <p class="page-context__subtitle" id="page-subtitle">خلاصه وضعیت فضای آموزشی شما</p>
                </div>
                <div class="page-context__meta">
                    <div class="date-chip" id="today" aria-label="تاریخ امروز"></div>
                </div>
            </header>

            <div class="product-toolbar" id="product-toolbar">
                <form id="search-form" class="global-search" role="search">
                    <label class="sr-only" for="search-query">جست‌وجو در فضای آموزشی</label>
                    <svg aria-hidden="true" focusable="false"><use href="/assets/web-shell/icons.svg#search"></use></svg>
                    <input id="search-query" name="q" type="search" autocomplete="off" placeholder="جست‌وجوی درس، منبع، اطلاعیه…">
                    <kbd class="search-hint" aria-hidden="true">/</kbd>
                </form>
                <div class="toolbar-actions" id="toolbar-actions"></div>
            </div>

            <section class="view-frame" aria-labelledby="view-title">
                <div class="view-heading" id="view-heading">
                    <div>
                        <h2 id="view-title">خانه</h2>
                        <p id="view-description">اطلاعات مهم امروز و دسترسی‌های سریع</p>
                    </div>
                    <div id="view-actions" class="view-actions"></div>
                </div>
                <div id="result-list" class="view-content" aria-live="polite" aria-busy="false"></div>
            </section>
        </div>
    </section>
</main>

<nav class="mobile-bottom-nav" id="mobile-bottom-nav" aria-label="ناوبری اصلی موبایل" hidden>
    <button type="button" data-route="home">
        <svg aria-hidden="true" focusable="false"><use href="/assets/web-shell/icons.svg#home"></use></svg><span>خانه</span>
    </button>
    <button type="button" data-view="academics">
        <svg aria-hidden="true" focusable="false"><use href="/assets/web-shell/icons.svg#academics"></use></svg><span>درس‌ها</span>
    </button>
    <button type="button" data-view="schedule">
        <svg aria-hidden="true" focusable="false"><use href="/assets/web-shell/icons.svg#calendar"></use></svg><span>برنامه</span>
    </button>
    <button type="button" data-view="resources">
        <svg aria-hidden="true" focusable="false"><use href="/assets/web-shell/icons.svg#resources"></use></svg><span>منابع</span>
    </button>
    <button type="button" data-route="more" aria-haspopup="dialog" aria-controls="mobile-more-drawer">
        <svg aria-hidden="true" focusable="false"><use href="/assets/web-shell/icons.svg#menu"></use></svg><span>بیشتر</span>
    </button>
</nav>

<div class="drawer-backdrop" id="drawer-backdrop" hidden></div>
<aside class="mobile-drawer" id="mobile-more-drawer" role="dialog" aria-modal="true" aria-labelledby="mobile-more-title" hidden>
    <div class="drawer-handle" aria-hidden="true"></div>
    <div class="drawer-heading">
        <h2 id="mobile-more-title">بیشتر</h2>
        <button class="icon-button" type="button" data-drawer-close aria-label="بستن منو">×</button>
    </div>
    <div class="drawer-grid">
        <button type="button" data-view="assessments"><svg aria-hidden="true"><use href="/assets/web-shell/icons.svg#assessment"></use></svg><span>آزمون‌ها</span></button>
        <button type="button" data-view="grades"><svg aria-hidden="true"><use href="/assets/web-shell/icons.svg#grades"></use></svg><span>نمرات</span></button>
        <button type="button" data-view="announcements"><svg aria-hidden="true"><use href="/assets/web-shell/icons.svg#announcement"></use></svg><span>اطلاعیه‌ها</span></button>
        <button type="button" data-view="forms"><svg aria-hidden="true"><use href="/assets/web-shell/icons.svg#forms"></use></svg><span>فرم‌ها</span></button>
        <button type="button" data-view="orders"><svg aria-hidden="true"><use href="/assets/web-shell/icons.svg#access"></use></svg><span>خرید و دسترسی</span></button>
        <button type="button" data-route="account"><svg aria-hidden="true"><use href="/assets/web-shell/icons.svg#account"></use></svg><span>حساب</span></button>
        <button type="button" id="mobile-management-nav" data-route="management" hidden><svg aria-hidden="true"><use href="/assets/web-shell/icons.svg#manage"></use></svg><span>مدیریت</span></button>
    </div>
</aside>

<div class="toast-region" id="toast-region" aria-live="polite" aria-atomic="true"></div>
<footer class="site-footer"><p>فانوس · زیرساخت آموزشی دانشجو</p></footer>

<script defer src="/assets/domain-ux.js"></script>
<script defer src="/assets/ui-v2/product-core.js"></script>
<script defer src="/assets/ui-v2/product-learning.js"></script>
<script defer src="/assets/ui-v2/product-academic.js"></script>
<script defer src="/assets/ui-v2/product-communication.js"></script>
<script defer src="/assets/ui-v2/product-account.js"></script>
<script defer src="/assets/ui-v2/product-ui.js"></script>
<script defer src="/assets/app.js"></script>
</body>
</html>
