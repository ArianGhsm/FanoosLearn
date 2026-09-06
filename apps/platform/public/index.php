<?php

declare(strict_types=1);

?><!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#103d3a">
    <title>فانوس | فضای یادگیری</title>
    <link rel="stylesheet" href="/assets/app.css">
    <script src="/assets/app.js" defer></script>
</head>
<body>
<div class="ambient ambient-a" aria-hidden="true"></div>
<div class="ambient ambient-b" aria-hidden="true"></div>
<header class="topbar">
    <a class="brand" href="/" aria-label="فانوس، صفحه اصلی">
        <span class="brand-mark" aria-hidden="true">ف</span>
        <span><strong>فانوس</strong><small>فضای یادگیری شما</small></span>
    </a>
    <div class="workspace-picker" id="workspace-picker" hidden>
        <label for="workspace-select">فضای فعال</label>
        <select id="workspace-select"></select>
    </div>
    <button class="quiet-button" id="logout" type="button" hidden>خروج</button>
</header>

<main>
    <section class="welcome" id="login-panel">
        <div class="welcome-copy">
            <span class="eyebrow">یک حساب، همه‌ی فضاها</span>
            <h1>مسیر دانشگاه را روشن‌تر ببین.</h1>
            <p>برنامه، نمره‌ها، اطلاعیه‌ها و منابع هر فضای آموزشی در یک نمای امن و مرتب.</p>
            <div class="trust-row"><span>مرزبندی مستقل هر فضای آموزشی</span><span>دسترسی مبتنی بر نقش</span></div>
        </div>
        <form class="login-card" id="login-form">
            <div><span class="eyebrow">ورود به حساب</span><h2>خوش آمدی</h2></div>
            <label>ایمیل، موبایل یا شناسه<input name="identifier" autocomplete="username" required></label>
            <label>رمز عبور<input name="password" type="password" autocomplete="current-password" required></label>
            <button class="primary-button" type="submit">ورود امن</button>
            <p class="form-message" id="login-message" role="status"></p>
        </form>
    </section>

    <section class="dashboard" id="dashboard" hidden>
        <div class="dashboard-heading">
            <div><span class="eyebrow">امروز در فانوس</span><h1 id="greeting">فضای یادگیری</h1><p id="workspace-path"></p></div>
            <div class="date-chip" id="today"></div>
        </div>
        <nav class="module-grid" aria-label="بخش‌های فانوس">
            <button data-view="schedule"><span class="module-icon mint">تق</span><strong>برنامه و آزمون‌ها</strong><small>کلاس‌ها، امتحان‌ها و مهلت‌ها</small></button>
            <button data-view="grades"><span class="module-icon amber">نم</span><strong>مرکز نمرات</strong><small>فقط نمرات منتشرشده‌ی شما</small></button>
            <button data-view="announcements"><span class="module-icon coral">اع</span><strong>اطلاعیه‌ها</strong><small>پیام‌های فضای فعال</small></button>
            <button data-view="academics"><span class="module-icon blue">در</span><strong>درس‌ها</strong><small>ترم، درس و جلسه</small></button>
            <button data-view="forms"><span class="module-icon violet">فر</span><strong>فرم‌ها</strong><small>فرم‌های فعال و پاسخ‌ها</small></button>
            <button data-view="orders"><span class="module-icon slate">خر</span><strong>خریدهای من</strong><small>وضعیت سفارش و دسترسی</small></button>
        </nav>
        <section class="content-panel">
            <div class="content-toolbar"><div><span class="eyebrow">نمای انتخاب‌شده</span><h2 id="view-title">برنامه و آزمون‌ها</h2></div><form id="search-form"><input name="q" minlength="2" placeholder="جست‌وجو در فضای فعال…"><button type="submit">جست‌وجو</button></form></div>
            <div class="result-list" id="result-list"><div class="empty-state">یکی از بخش‌ها را انتخاب کن.</div></div>
        </section>
    </section>
</main>

<footer><span>FanoosLearn</span><span>مرز دسترسی: فضای فعال</span></footer>
</body>
</html>
