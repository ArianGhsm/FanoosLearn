(function () {
    "use strict";

    function $(id) {
        return document.getElementById(id);
    }

    function siteApi() {
        return window.Dent1402Site && typeof window.Dent1402Site === "object"
            ? window.Dent1402Site
            : null;
    }

    function authApi() {
        return window.Dent1402Auth && typeof window.Dent1402Auth === "object"
            ? window.Dent1402Auth
            : null;
    }

    function toFaDigits(value) {
        return String(value == null ? "" : value).replace(/\d/g, function (digit) {
            return ["۰", "۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹"][Number(digit)] || digit;
        });
    }

    function formatNumber(value) {
        var number = Number(value || 0);
        if (!Number.isFinite(number)) {
            number = 0;
        }
        return number.toLocaleString("fa-IR");
    }

    function formatDate(value) {
        var date = new Date(String(value || ""));
        if (Number.isNaN(date.getTime())) {
            return "ثبت نشده";
        }
        try {
            return new Intl.DateTimeFormat("fa-IR", {
                year: "numeric",
                month: "2-digit",
                day: "2-digit",
                hour: "2-digit",
                minute: "2-digit"
            }).format(date);
        } catch (error) {
            return toFaDigits(String(value || ""));
        }
    }

    function parseJsonResponse(response) {
        var site = siteApi();
        if (site && typeof site.parseJsonResponse === "function") {
            return site.parseJsonResponse(response);
        }
        return response.json().catch(function () {
            return { success: false, error: "پاسخ نامعتبر از سرور دریافت شد." };
        }).then(function (payload) {
            payload.httpStatus = response.status;
            return payload;
        });
    }

    function consumeUnauthorized(payload) {
        var site = siteApi();
        if (site && typeof site.consumeUnauthorized === "function") {
            return !!site.consumeUnauthorized(payload, "برای مدیریت ظاهر سایت باید با حساب مالک وارد شوید.");
        }
        var auth = authApi();
        if (auth && typeof auth.handleUnauthorizedPayload === "function") {
            return !!auth.handleUnauthorizedPayload(payload, "برای مدیریت ظاهر سایت باید با حساب مالک وارد شوید.");
        }
        return !!(payload && (payload.loggedOut || payload.httpStatus === 401));
    }

    function requestDashboard() {
        return fetch("/api/admin_api.php?action=dashboard", {
            method: "GET",
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        }).then(parseJsonResponse).catch(function () {
            return {
                success: false,
                error: "ارتباط با سرور برقرار نشد."
            };
        });
    }

    function requestSaveAppearance(settings) {
        var appearance = settings && typeof settings === "object" ? settings : {};
        var body = new FormData();
        body.set("bottomNavSwipeEnabled", appearance.bottomNavSwipeEnabled ? "1" : "0");
        body.set("bottomNavLabelsEnabled", appearance.bottomNavLabelsEnabled ? "1" : "0");
        body.set("bottomNavGlassEnabled", appearance.bottomNavGlassEnabled ? "1" : "0");
        body.set("visualEffectsLiteEnabled", appearance.visualEffectsLiteEnabled ? "1" : "0");
        return fetch("/api/admin_api.php?action=saveAppearance", {
            method: "POST",
            credentials: "same-origin",
            body: body,
            headers: { Accept: "application/json" }
        }).then(parseJsonResponse).catch(function () {
            return {
                success: false,
                error: "ارتباط با سرور برقرار نشد."
            };
        });
    }

    function setText(id, value) {
        var node = $(id);
        if (node) {
            node.textContent = value;
        }
    }

    function setFeedback(text) {
        var node = $("admin-feedback");
        if (!node) {
            return;
        }
        node.textContent = text || "";
        node.hidden = !text;
    }

    function clearNode(node) {
        while (node && node.firstChild) {
            node.removeChild(node.firstChild);
        }
    }

    function createMetricRow(title, meta, value, tone) {
        var row = document.createElement("div");
        row.className = "admin-metric-row";
        if (tone) {
            row.dataset.tone = tone;
        }

        var copy = document.createElement("span");
        copy.className = "admin-metric-row__copy";
        var strong = document.createElement("strong");
        strong.textContent = title;
        var small = document.createElement("small");
        small.textContent = meta || "";
        copy.appendChild(strong);
        copy.appendChild(small);

        var valueNode = document.createElement("span");
        valueNode.className = "admin-metric-row__value";
        valueNode.textContent = value;

        row.appendChild(copy);
        row.appendChild(valueNode);
        return row;
    }

    function renderMetricList(id, rows) {
        var root = $(id);
        if (!root) {
            return;
        }
        clearNode(root);
        (rows || []).forEach(function (row) {
            root.appendChild(createMetricRow(row.title, row.meta, row.value, row.tone));
        });
    }

    function renderErrors(errors) {
        var root = $("admin-error-list");
        if (!root) {
            return;
        }
        clearNode(root);
        var recent = Array.isArray(errors && errors.recent) ? errors.recent : [];
        if (!recent.length) {
            root.appendChild(createMetricRow("خطایی برای نمایش نیست", "error log خالی است یا در دسترس نیست.", "OK", "accent"));
            return;
        }
        recent.forEach(function (entry) {
            var row = document.createElement("div");
            row.className = "admin-log-row";
            var title = document.createElement("strong");
            title.textContent = (entry.message || entry.type || "خطای سرور").slice(0, 220);
            var meta = document.createElement("span");
            meta.className = "admin-log-row__meta";
            meta.textContent = [
                formatDate(entry.at),
                entry.status ? ("HTTP " + toFaDigits(entry.status)) : "",
                entry.uri || "",
                entry.file ? entry.file.split(/[\\/]/).slice(-1)[0] : ""
            ].filter(Boolean).join(" • ");
            row.appendChild(title);
            row.appendChild(meta);
            root.appendChild(row);
        });
    }

    function renderLinks(links) {
        var root = $("admin-links");
        if (!root) {
            return;
        }
        clearNode(root);
        (Array.isArray(links) ? links : []).forEach(function (link) {
            var node = document.createElement("a");
            node.className = "admin-link-row";
            node.href = link.href || "#";
            var strong = document.createElement("strong");
            strong.textContent = link.label || "پنل";
            var small = document.createElement("small");
            small.textContent = groupLabel(link.group || "");
            node.appendChild(strong);
            node.appendChild(small);
            root.appendChild(node);
        });
    }

    function setAppearanceSaving(saving) {
        var button = $("admin-appearance-save");
        var input = $("admin-bottom-nav-swipe");
        if (button) {
            button.disabled = !!saving;
            button.textContent = saving ? "در حال ذخیره..." : "ذخیره ظاهر سایت";
        }
        if (input) {
            input.disabled = !!saving;
        }
        ["admin-bottom-nav-labels", "admin-bottom-nav-glass", "admin-visual-effects-lite"].forEach(function (id) {
            var extraInput = $(id);
            if (extraInput) {
                extraInput.disabled = !!saving;
            }
        });
    }

    function readAppearanceForm() {
        return {
            bottomNavSwipeEnabled: !!($("admin-bottom-nav-swipe") && $("admin-bottom-nav-swipe").checked),
            bottomNavLabelsEnabled: !!($("admin-bottom-nav-labels") && $("admin-bottom-nav-labels").checked),
            bottomNavGlassEnabled: !!($("admin-bottom-nav-glass") && $("admin-bottom-nav-glass").checked),
            visualEffectsLiteEnabled: !!($("admin-visual-effects-lite") && $("admin-visual-effects-lite").checked)
        };
    }

    function renderAppearance(settings, message) {
        var input = $("admin-bottom-nav-swipe");
        var status = $("admin-appearance-status");
        var appearance = settings && typeof settings === "object" ? settings : {};
        var enabled = !!appearance.bottomNavSwipeEnabled;
        if (input) {
            input.checked = enabled;
        }
        [
            ["admin-bottom-nav-labels", appearance.bottomNavLabelsEnabled !== false],
            ["admin-bottom-nav-glass", appearance.bottomNavGlassEnabled !== false],
            ["admin-visual-effects-lite", !!appearance.visualEffectsLiteEnabled]
        ].forEach(function (entry) {
            var extraInput = $(entry[0]);
            if (extraInput) {
                extraInput.checked = !!entry[1];
            }
        });
        if (status) {
            status.textContent = message || (enabled ? "وضعیت فعلی: روشن" : "وضعیت فعلی: خاموش");
        }
    }

    function groupLabel(group) {
        var labels = {
            account: "کاربران و تنظیمات مالک",
            buy: "پرداخت و سفارش",
            forms: "فرم و پاسخ",
            notes: "منابع آموزشی",
            files: "فایل‌های عمومی مالک",
            paste: "متن و raw view",
            html: "صفحه HTML موقت",
            navid: "همگام‌سازی نوید"
        };
        return labels[group] || "پنل تخصصی";
    }

    function renderDashboard(dashboard) {
        var health = dashboard.health || {};
        var storage = health.storage || {};
        var pending = dashboard.pending || {};
        var appVersion = dashboard.appVersion || {};
        var deploy = dashboard.deploy || {};
        var errors = health.errors || {};
        var navid = pending.navid || {};
        var appearance = ((dashboard.appearance || {}).site) || {};

        var navidNeedsAction = !!(navid.requiresReconnect || navid.credentialsMissing || navid.credentialsInvalid);
        var navidStatusLabel = navid.credentialsMissing
            ? "ورود نوید"
            : (navid.credentialsInvalid || navid.requiresReconnect ? "اتصال مجدد" : (navid.enabled ? "فعال" : "خاموش"));
        var hasHealthIssue = !storage.rootWritable || Number(errors.last24h || 0) > 0 || navidNeedsAction;
        setText("admin-health-status", hasHealthIssue ? "نیاز به بررسی" : "پایدار");
        setText("admin-health-meta", storage.rootWritable ? "storage قابل نوشتن است" : "storage قابل نوشتن نیست");
        setText("admin-pending-total", formatNumber(pending.total || 0));
        setText("admin-version", appVersion.version ? toFaDigits(appVersion.version) : "نامشخص");
        setText("admin-version-meta", appVersion.generatedAt ? formatDate(appVersion.generatedAt) : "نسخه ثبت نشده");
        setText("admin-errors-24h", formatNumber(errors.last24h || 0));
        setText("admin-errors-meta", errors.available ? ("کل: " + formatNumber(errors.total || 0)) : "در دسترس نیست");

        var latestNotice = deploy.latestNotice || {};
        renderMetricList("admin-pending-list", [
            {
                title: "سفارش‌های پرداخت در انتظار",
                meta: "نیازمند reconcile یا پیگیری در /buy/manage/",
                value: formatNumber((pending.payments || {}).pendingOrders || 0),
                tone: ((pending.payments || {}).pendingOrders || 0) ? "warn" : "accent"
            },
            {
                title: "رسیدهای فرم آپلودشده",
                meta: "رسیدهایی که ممکن است نیازمند بررسی باشند",
                value: formatNumber((pending.forms || {}).uploadedReceipts || 0),
                tone: ((pending.forms || {}).uploadedReceipts || 0) ? "warn" : "accent"
            },
            {
                title: "درخواست و گزارش منابع",
                meta: "درخواست منبع و لینک خراب در notes",
                value: formatNumber(((pending.notes || {}).resourceRequests || 0) + ((pending.notes || {}).linkReports || 0)),
                tone: (((pending.notes || {}).resourceRequests || 0) + ((pending.notes || {}).linkReports || 0)) ? "warn" : "accent"
            },
            {
                title: "وضعیت نوید",
                meta: navid.lastSuccessAt ? ("آخرین موفق: " + formatDate(navid.lastSuccessAt)) : "sync موفق ثبت نشده",
                value: navidStatusLabel,
                tone: navidNeedsAction ? "danger" : "accent"
            }
        ]);

        renderMetricList("admin-deploy-list", [
            {
                title: "نسخه active PWA",
                meta: appVersion.generatedAt ? ("stamp: " + formatDate(appVersion.generatedAt)) : "app-version.json",
                value: appVersion.version ? toFaDigits(appVersion.version) : "—",
                tone: "accent"
            },
            {
                title: "آخرین اعلان deploy مالک",
                meta: latestNotice.deployedAt ? formatDate(latestNotice.deployedAt) : "اعلان deploy پیدا نشد",
                value: latestNotice.deployHead ? latestNotice.deployHead.slice(0, 8) : "—",
                tone: latestNotice.deployedAt ? "accent" : "warn"
            },
            {
                title: "آخرین تغییر storage منابع",
                meta: "بر اساس mtime فایل‌های notes",
                value: (pending.notes || {}).latestStoreUpdateAt ? formatDate((pending.notes || {}).latestStoreUpdateAt) : "—",
                tone: "accent"
            }
        ]);

        renderMetricList("admin-health-list", [
            {
                title: "PHP",
                meta: "runtime فعلی API",
                value: health.phpVersion || "—",
                tone: "accent"
            },
            {
                title: "Storage root",
                meta: storage.rootExists ? "مسیر storage موجود است" : "مسیر storage پیدا نشد",
                value: storage.rootWritable ? "قابل نوشتن" : "خطا",
                tone: storage.rootWritable ? "accent" : "danger"
            },
            {
                title: "SMS",
                meta: ((health.sms || {}).lastHealthAt ? formatDate((health.sms || {}).lastHealthAt) : "health ثبت نشده"),
                value: (health.sms || {}).enabled ? "فعال" : "خاموش",
                tone: (health.sms || {}).lastHealthStatus === "error" ? "warn" : "accent"
            }
        ]);

        var totals = (dashboard.activity || {}).totals || {};
        renderMetricList("admin-activity-list", [
            { title: "بازدید ۳۰ روز", meta: "کاربران واقعی و ربات‌ها تفکیک در account", value: formatNumber(totals.pageViews30d || 0), tone: "accent" },
            { title: "ورود ۳۰ روز", meta: "loginهای ثبت‌شده", value: formatNumber(totals.logins30d || 0), tone: "accent" },
            { title: "کاربران کل", meta: "همه ورودی‌های فعال", value: formatNumber(totals.totalUsers || 0), tone: "accent" }
        ]);

        var content = dashboard.content || {};
        renderMetricList("admin-content-list", [
            { title: "فایل‌های فعال", meta: "مرکز آپلود مالک", value: formatNumber((content.files || {}).active || 0), tone: "accent" },
            { title: "Pasteها", meta: "تعداد pasteهای ثبت‌شده", value: formatNumber((content.pastes || {}).total || 0), tone: "accent" },
            { title: "HTMLهای فعال", meta: "صفحه‌های public با لینک مستقیم", value: formatNumber((content.html || {}).active || 0), tone: "accent" }
        ]);

        renderErrors(errors);
        renderLinks(dashboard.links || []);
        renderAppearance(appearance);
    }

    function setLoading(loading) {
        var refresh = $("admin-refresh");
        if (refresh) {
            refresh.disabled = !!loading;
            refresh.textContent = loading ? "در حال دریافت..." : "بازیابی تنظیمات";
        }
    }

    function loadDashboard() {
        setLoading(true);
        setFeedback("");
        return requestDashboard().then(function (payload) {
            if (consumeUnauthorized(payload)) {
                return;
            }
            if (!payload || !payload.success || !payload.dashboard) {
                throw new Error((payload && payload.error) || "دریافت تنظیمات ظاهر سایت ناموفق بود.");
            }
            renderDashboard(payload.dashboard);
        }).catch(function (error) {
            setFeedback(error && error.message ? error.message : "دریافت تنظیمات ظاهر سایت با خطا مواجه شد.");
        }).finally(function () {
            setLoading(false);
        });
    }

    function bind() {
        var refresh = $("admin-refresh");
        if (refresh) {
            refresh.addEventListener("click", loadDashboard);
        }
        var form = $("admin-appearance-form");
        if (form) {
            form.addEventListener("submit", function (event) {
                event.preventDefault();
                var settings = readAppearanceForm();
                var enabled = !!settings.bottomNavSwipeEnabled;
                setAppearanceSaving(true);
                renderAppearance({ bottomNavSwipeEnabled: enabled }, "در حال ذخیره...");
                renderAppearance(settings, "در حال ذخیره...");
                requestSaveAppearance(settings).then(function (payload) {
                    if (consumeUnauthorized(payload)) {
                        return;
                    }
                    if (!payload || !payload.success) {
                        throw new Error((payload && payload.error) || "ذخیره تنظیمات ظاهر سایت ناموفق بود.");
                    }
                    var savedSettings = ((payload.appearance || {}).site) || settings;
                    renderAppearance(settings, "تنظیمات ظاهر سایت ذخیره شد.");
                    var auth = authApi();
                    if (auth && typeof auth.verifySession === "function") {
                        auth.verifySession({ attempts: 1 });
                    }
                }).catch(function (error) {
                    renderAppearance({ bottomNavSwipeEnabled: enabled }, error && error.message ? error.message : "ذخیره تنظیمات ظاهر سایت ناموفق بود.");
                }).finally(function () {
                    setAppearanceSaving(false);
                });
            });
        }
    }

    function boot() {
        bind();
        loadDashboard();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot, { once: true });
    } else {
        boot();
    }
})();
