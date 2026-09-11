(function () {
    "use strict";

    if (!window.Dent1402Auth) {
        return;
    }

    function $(id) {
        return document.getElementById(id);
    }

    var page = document.body && document.body.dataset ? String(document.body.dataset.buyManagePage || "") : "";
    var mode = document.body && document.body.dataset ? String(document.body.dataset.buyManageMode || "") : "";
    var root = $("payments-manage-root");
    var app = $("payments-manage-app");
    var guard = $("payments-auth-guard");
    var feedbackNode = $("payments-feedback");
    var activeItemSection = "basic";
    var activeGatewaySection = "main";
    var heroPreviewObjectUrl = "";
    var heroPreviewObjectFile = null;
    var siteApi = window.Dent1402Site && typeof window.Dent1402Site === "object"
        ? window.Dent1402Site
        : null;

    if (!page || !root || !app) {
        return;
    }

    var state = {
        currentUser: null,
        loadingDashboard: false,
        dashboardLoaded: false,
        loadingOrders: false,
        summary: null,
        items: [],
        collections: [],
        gateways: [],
        gatewaySettings: null,
        notifications: [],
        orders: [],
        ordersSummary: null,
        orderFilters: {
            itemId: "0",
            source: "",
            method: "",
            formId: "",
            status: "all",
            dateFrom: "",
            dateTo: "",
            query: ""
        },
        orderView: "orders",
        itemQuery: "",
        itemStatus: "all",
        currentOrder: null,
        currentGatewaySnapshot: null
    };

    var SHIELD_SAMPLE = {
        title: "شیلد ابری سامان زر دندان",
        category: "consumables",
        slug: "saman-foam-face-shield",
        price: "1200000",
        status: "active",
        shortDescription: "شیلد محافظ صورت دندانپزشکی با فوم فاصله‌دهنده، مناسب تمرین‌های عملی، لابراتوار و کارهای کلینیکی دانشجویان.",
        fullDescription: "شیلد برای محافظت صورت هنگام کار دندانپزشکی و استفاده در لابراتوار مناسب است، فوم فاصله‌دهنده دارد، قابل استفاده مجدد و قابل شستشو است.",
        heroImage: "/assets/images/buy/saman-foam-face-shield.jpg",
        expiresAt: "2026-05-15T23:59",
        gallery: ["/assets/images/buy/saman-foam-face-shield.jpg"],
        specifications: [
            { label: "کاربرد", value: "دندانپزشکی، لابراتوار و محیط آموزشی" },
            { label: "محتوای بسته", value: "۲ عددی" },
            { label: "ویژگی", value: "قابل استفاده مجدد، قابل شستشو، دارای کش" }
        ],
        requiredFields: [
            { name: "studentNumber", label: "شماره دانشجویی", type: "text", required: true, maxLength: 14 },
            { name: "group", label: "گروه/بخش تحویل", type: "text", required: false, maxLength: 80 }
        ],
        audienceNote: "دانشجویان دندانپزشکی ورودی ۱۴۰۲",
        deliveryNote: "تحویل حضوری در محدوده دانشکده دندانپزشکی دانشگاه علوم پزشکی تهران هماهنگ می‌شود.",
        supportNote: "برای پیگیری سفارش، کد رهگیری پرداخت را برای نماینده یا مالک سایت ارسال کنید.",
        allowCancellation: false,
        maxQuantityPerOrder: "2",
        capacity: "40",
        discountCodes: [
            { code: "SHIELD10", type: "percent", amount: 10, label: "تخفیف تستی دانشجویی", isEnabled: true }
        ],
        ratingAverage: "4.8",
        ratingCount: "12",
        reviews: [
            { name: "دانشجوی ترمیمی", rating: 5, body: "سبک است و فاصله طلق از صورت برای کار طولانی مناسب است." }
        ],
        successMessage: "سفارش شیلد شما با موفقیت ثبت شد و با کد رهگیری قابل پیگیری است.",
        failureMessage: "پرداخت سفارش شیلد تایید نشد؛ در صورت کسر وجه با پشتیبانی تماس بگیرید."
    };

    function parseJsonResponse(response) {
        if (siteApi && typeof siteApi.parseJsonResponse === "function") {
            return siteApi.parseJsonResponse(response);
        }
        return response.json().catch(function () {
            return { success: false, error: "پاسخ نامعتبر از سرور دریافت شد." };
        }).then(function (payload) {
            payload.httpStatus = response.status;
            return payload;
        });
    }

    function networkErrorResponse() {
        return {
            success: false,
            error: "ارتباط با سرور برقرار نشد. اتصال اینترنت خود را بررسی کنید.",
            httpStatus: 0
        };
    }

    function request(action, payload, method) {
        var verb = String(method || "GET").toUpperCase();
        if (verb === "POST") {
            return fetch("/api/payments_api.php", {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                    "Accept": "application/json"
                },
                body: new URLSearchParams(Object.assign({ action: action }, payload || {}))
            }).then(parseJsonResponse).catch(networkErrorResponse);
        }

        var query = new URLSearchParams(Object.assign({ action: action }, payload || {}));
        return fetch("/api/payments_api.php?" + query.toString(), {
            method: "GET",
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        }).then(parseJsonResponse).catch(networkErrorResponse);
    }

    function requestFormData(action, formData) {
        var body = formData instanceof FormData ? formData : new FormData();
        body.set("action", action);
        return fetch("/api/payments_api.php", {
            method: "POST",
            credentials: "same-origin",
            headers: { "Accept": "application/json" },
            body: body
        }).then(parseJsonResponse).catch(networkErrorResponse);
    }

    function consumeUnauthorized(payload, fallbackText) {
        if (siteApi && typeof siteApi.consumeUnauthorized === "function") {
            return !!siteApi.consumeUnauthorized(payload, fallbackText || "نشست شما منقضی شده است.");
        }
        var auth = window.Dent1402Auth;
        if (auth && typeof auth.handleUnauthorizedPayload === "function") {
            return !!auth.handleUnauthorizedPayload(payload, fallbackText || "نشست شما منقضی شده است.");
        }
        return !!(payload && (payload.loggedOut || payload.httpStatus === 401));
    }

    function escapeHtml(value) {
        return String(value == null ? "" : value).replace(/[&<>"]/g, function (char) {
            switch (char) {
                case "&": return "&amp;";
                case "<": return "&lt;";
                case ">": return "&gt;";
                case "\"": return "&quot;";
                default: return char;
            }
        });
    }

    function money(value) {
        return (Math.max(0, Number(value) || 0)).toLocaleString("fa-IR") + " ریال";
    }

    function normalizeDigits(value) {
        if (siteApi && typeof siteApi.normalizeDigits === "function") {
            return siteApi.normalizeDigits(value);
        }
        return String(value || "")
            .replace(/[\u06F0-\u06F9]/g, function (ch) {
                return String("\u06F0\u06F1\u06F2\u06F3\u06F4\u06F5\u06F6\u06F7\u06F8\u06F9".indexOf(ch));
            })
            .replace(/[\u0660-\u0669]/g, function (ch) {
                return String("\u0660\u0661\u0662\u0663\u0664\u0665\u0666\u0667\u0668\u0669".indexOf(ch));
            });
    }

    function formatDateTime(value, fallback) {
        if (siteApi && typeof siteApi.formatDateTime === "function") {
            return siteApi.formatDateTime(value, fallback);
        }
        var raw = String(value || "").trim();
        if (!raw) {
            return fallback || "—";
        }
        var parsed = new Date(raw);
        if (!Number.isFinite(parsed.getTime())) {
            return raw;
        }
        return parsed.toLocaleString("fa-IR-u-ca-persian", {
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
            hour12: false
        });
    }

    function readParam(name) {
        return String(new URLSearchParams(window.location.search).get(name) || "").trim();
    }

    function setFeedback(node, message, kind, loading) {
        if (!node) {
            return;
        }
        node.className = "account-feedback account-feedback--inline" + (kind ? (" " + kind) : "");
        if (loading) {
            node.innerHTML = [
                '<div class="loader">',
                '  <span class="loader-dot"></span>',
                '  <span class="loader-dot"></span>',
                '  <span class="loader-dot"></span>',
                "  <span>" + escapeHtml(message || "") + "</span>",
                "</div>"
            ].join("");
            return;
        }
        node.textContent = message || "";
    }

    function setActions(html) {
        var node = $("payments-manage-actions");
        if (node) {
            node.innerHTML = html || "";
        }
    }

    function setHead(kicker, title, description) {
        if ($("payments-manage-kicker")) $("payments-manage-kicker").textContent = kicker || "";
        if ($("payments-manage-title")) $("payments-manage-title").textContent = title || "";
        if ($("payments-manage-description")) $("payments-manage-description").textContent = description || "";
    }

    function syncNav() {
        var active = page;
        if (page === "item-form") active = "items";
        if (page === "gateway-form") active = "gateways";
        if (page === "order-detail") active = "orders";
        if (page === "collections") active = "collections";
        document.querySelectorAll("[data-manage-nav]").forEach(function (link) {
            link.classList.toggle("is-active", String(link.dataset.manageNav || "") === active);
        });
    }

    function summaryCard(label, value, meta, tone) {
        return [
            '<article class="owner-summary-card' + (tone ? (" owner-summary-card--" + tone) : "") + '">',
            '  <span>' + escapeHtml(label) + "</span>",
            '  <strong>' + escapeHtml(value) + "</strong>",
            '  <small>' + escapeHtml(meta || "") + "</small>",
            "</article>"
        ].join("");
    }

    function statusLabel(status) {
        switch (String(status || "")) {
            case "success": return "موفق";
            case "failed": return "ناموفق";
            case "canceled": return "لغو شده";
            case "expired": return "منقضی شده";
            default: return "در انتظار";
        }
    }

    function statusTone(status) {
        switch (String(status || "")) {
            case "success": return "ok";
            case "failed":
            case "canceled":
            case "expired": return "danger";
            default: return "warn";
        }
    }

    function statusFilterLabel(status) {
        switch (String(status || "")) {
            case "success":
            case "done":
                return "انجام‌شده";
            case "unfinished":
            case "not_done":
                return "انجام‌نشده";
            case "pending":
                return "در انتظار";
            case "problem":
                return "ناموفق";
            case "failed":
                return "ناموفق";
            case "canceled":
                return "لغو شده";
            case "expired":
                return "منقضی";
            default:
                return "همه سوابق";
        }
    }

    function statusFilterTone(status) {
        switch (String(status || "")) {
            case "success":
            case "done":
                return "ok";
            case "problem":
            case "failed":
            case "canceled":
            case "expired":
                return "danger";
            case "unfinished":
            case "not_done":
            case "pending":
                return "warn";
            default:
                return "";
        }
    }

    function itemStateLabel(item) {
        var itemState = item && item.state ? item.state : {};
        return String(itemState.label || item.status || "نامشخص");
    }

    function itemStateTone(item) {
        var key = String(item && item.state ? item.state.key || "" : "");
        if (key === "active") return "ok";
        if (key === "upcoming" || key === "full") return "warn";
        return "danger";
    }

    function itemCategoryLabel(item) {
        return String(item && (item.categoryLabel || item.category) || "سفارش گروهی");
    }

    function gatewayProviderLabel(provider) {
        switch (String(provider || "").trim().toLowerCase()) {
            case "zibal": return "درگاه زیبال";
            case "zarinpal": return "درگاه زرین‌پال";
            case "mock": return "درگاه آزمایشی";
            default: return "درگاه آنلاین";
        }
    }

    function gatewayPublicLabel(provider) {
        return String(provider || "").trim().toLowerCase() === "mock" ? "پرداخت آزمایشی" : "پرداخت آنلاین";
    }

    function absoluteUrl(path) {
        try {
            return new URL(String(path || ""), window.location.origin).href;
        } catch (_error) {
            return String(path || "");
        }
    }

    function prettyJson(value) {
        try {
            return JSON.stringify(value == null ? {} : value, null, 2);
        } catch (_error) {
            return "{}";
        }
    }

    function parseLooseJson(raw, fallback) {
        var text = String(raw || "").trim();
        if (!text) {
            return fallback;
        }
        try {
            return JSON.parse(text);
        } catch (_error) {
            return fallback;
        }
    }

    function readField(id) {
        var node = $(id);
        return node ? String(node.value || "").trim() : "";
    }

    function writeField(id, value) {
        var node = $(id);
        if (node) {
            node.value = value == null ? "" : String(value);
        }
    }

    function safeImageUrl(value) {
        var clean = String(value || "").trim();
        if (!clean) {
            return "";
        }
        if (clean.indexOf("/api/payments_api.php?action=paymentImage&name=") === 0 ||
            clean.indexOf("/assets/images/buy/") === 0 ||
            clean.indexOf("data:image/") === 0) {
            return clean;
        }
        return "";
    }

    function toDatetimeLocal(value) {
        var raw = String(value || "").trim();
        if (!raw) {
            return "";
        }
        var parsed = new Date(raw);
        if (!Number.isFinite(parsed.getTime())) {
            return "";
        }
        return [
            parsed.getFullYear(),
            String(parsed.getMonth() + 1).padStart(2, "0"),
            String(parsed.getDate()).padStart(2, "0")
        ].join("-") + "T" + [
            String(parsed.getHours()).padStart(2, "0"),
            String(parsed.getMinutes()).padStart(2, "0")
        ].join(":");
    }

    function fromDatetimeLocal(value) {
        var raw = String(value || "").trim();
        if (!raw) {
            return "";
        }
        return raw.length === 16 ? raw.replace("T", " ") + ":00" : raw.replace("T", " ");
    }

    function slugifyLatin(value) {
        return String(value || "")
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, "-")
            .replace(/^-+|-+$/g, "")
            .slice(0, 80);
    }

    function selectedFile(input) {
        return input && input.files && input.files.length ? input.files[0] : null;
    }

    function selectedFiles(input) {
        return input && input.files ? Array.prototype.slice.call(input.files) : [];
    }

    function releaseHeroPreviewObjectUrl() {
        if (heroPreviewObjectUrl) {
            URL.revokeObjectURL(heroPreviewObjectUrl);
        }
        heroPreviewObjectUrl = "";
        heroPreviewObjectFile = null;
    }

    function heroFilePreviewUrl(file) {
        if (!file) {
            releaseHeroPreviewObjectUrl();
            return "";
        }
        if (heroPreviewObjectUrl && heroPreviewObjectFile === file) {
            return heroPreviewObjectUrl;
        }
        releaseHeroPreviewObjectUrl();
        heroPreviewObjectFile = file;
        heroPreviewObjectUrl = URL.createObjectURL(file);
        return heroPreviewObjectUrl;
    }

    function galleryValues() {
        var parsed = parseLooseJson(readField("payments-item-gallery"), []);
        if (!Array.isArray(parsed)) {
            return [];
        }
        return parsed.map(safeImageUrl).filter(Boolean);
    }

    function writeGalleryValues(values) {
        writeField("payments-item-gallery", prettyJson(Array.from(new Set((values || []).map(safeImageUrl).filter(Boolean)))));
    }

    function normalizeDiscountCodeEntry(entry) {
        entry = entry && typeof entry === "object" ? entry : {};
        var code = String(entry.code || "").toUpperCase().replace(/\s+/g, "").replace(/[^A-Z0-9_-]+/g, "").slice(0, 40);
        var type = String(entry.type || "fixed").trim().toLowerCase() === "percent" ? "percent" : "fixed";
        var amount = Math.max(0, Number(normalizeDigits(String(entry.amount || "0")).replace(/[^0-9.]+/g, "")) || 0);
        if (type === "percent") {
            amount = Math.min(100, Math.round(amount));
        } else {
            amount = Math.round(amount);
        }
        return {
            code: code,
            type: type,
            amount: amount,
            label: String(entry.label || "").trim().slice(0, 120),
            isEnabled: entry.isEnabled !== false && entry.is_enabled !== false,
            expiresAt: fromDatetimeLocal(toDatetimeLocal(entry.expiresAt || entry.expires_at || ""))
        };
    }

    function discountCodesFromField() {
        var parsed = parseLooseJson(readField("payments-item-discount-codes"), []);
        if (!Array.isArray(parsed)) {
            return [];
        }
        return parsed.map(normalizeDiscountCodeEntry).filter(function (entry) {
            return entry.code && entry.amount > 0;
        });
    }

    function writeDiscountCodes(values, skipRender) {
        var normalized = (Array.isArray(values) ? values : []).map(normalizeDiscountCodeEntry).filter(function (entry) {
            return entry.code && entry.amount > 0;
        });
        writeField("payments-item-discount-codes", prettyJson(normalized));
        if (!skipRender) {
            renderDiscountEditor();
        }
    }

    function readDiscountEditorRows() {
        return Array.prototype.slice.call(document.querySelectorAll("[data-payment-discount-row]")).map(function (row) {
            return normalizeDiscountCodeEntry({
                code: row.querySelector("[data-discount-code]") ? row.querySelector("[data-discount-code]").value : "",
                label: row.querySelector("[data-discount-label]") ? row.querySelector("[data-discount-label]").value : "",
                type: row.querySelector("[data-discount-type]") ? row.querySelector("[data-discount-type]").value : "fixed",
                amount: row.querySelector("[data-discount-amount]") ? row.querySelector("[data-discount-amount]").value : "0",
                expiresAt: row.querySelector("[data-discount-expires]") ? fromDatetimeLocal(row.querySelector("[data-discount-expires]").value) : "",
                isEnabled: row.querySelector("[data-discount-enabled]") ? row.querySelector("[data-discount-enabled]").checked : true
            });
        });
    }

    function syncDiscountEditorToField() {
        writeDiscountCodes(readDiscountEditorRows(), true);
    }

    function renderDiscountEditor() {
        var editor = $("payments-discount-editor");
        if (!editor) {
            return;
        }
        var codes = discountCodesFromField();
        if (!codes.length) {
            editor.innerHTML = '<div class="payments-discount-empty">کد تخفیفی تعریف نشده است.</div>';
            return;
        }
        editor.innerHTML = codes.map(function (entry, index) {
            var type = entry.type === "percent" ? "percent" : "fixed";
            return [
                '<article class="payments-discount-row" data-payment-discount-row>',
                '  <label><span>کد</span><input data-discount-code type="text" dir="ltr" maxlength="40" value="' + escapeHtml(entry.code) + '" placeholder="TUMS10"></label>',
                '  <label><span>عنوان</span><input data-discount-label type="text" maxlength="120" value="' + escapeHtml(entry.label || "") + '" placeholder="تخفیف دانشجویی"></label>',
                '  <label><span>نوع</span><select data-discount-type><option value="fixed"' + (type === "fixed" ? " selected" : "") + '>مبلغ ثابت</option><option value="percent"' + (type === "percent" ? " selected" : "") + '>درصد</option></select></label>',
                '  <label><span>مقدار</span><input data-discount-amount type="text" inputmode="numeric" dir="ltr" data-latin-digits="true" value="' + escapeHtml(String(entry.amount || "")) + '" placeholder="' + (type === "percent" ? "10" : "50000") + '"></label>',
                '  <label><span>انقضا</span><input data-discount-expires type="datetime-local" value="' + escapeHtml(toDatetimeLocal(entry.expiresAt || "")) + '"></label>',
                '  <label class="payments-discount-row__toggle"><input data-discount-enabled type="checkbox"' + (entry.isEnabled ? " checked" : "") + '><span>فعال</span></label>',
                '  <button class="shell-action-btn shell-action-btn-danger" type="button" data-payment-remove-discount="' + escapeHtml(String(index)) + '">حذف</button>',
                "</article>"
            ].join("");
        }).join("");
    }

    function renderLoading(message) {
        root.innerHTML = '<div class="owner-empty">' + escapeHtml(message || "در حال بارگذاری...") + "</div>";
    }

    function renderSummary(summary) {
        if (!summary) {
            return '<div class="owner-summary">' + summaryCard("پرداخت", "—", "آمار هنوز بارگذاری نشده است.") + "</div>";
        }
        return [
            '<div class="owner-summary">',
            summaryCard("آیتم فعال", Number(summary.activeItems || 0).toLocaleString("fa-IR"), "از " + Number(summary.totalItems || 0).toLocaleString("fa-IR") + " آیتم", (summary.activeItems || 0) > 0 ? "ok" : "warn"),
            summaryCard("دریافتی کل", money(summary.totalReceived || 0), "پرداخت‌های تاییدشده", (summary.totalReceived || 0) > 0 ? "ok" : ""),
            summaryCard("پرداخت موفق", Number(summary.totalSuccess || 0).toLocaleString("fa-IR"), "تراکنش verify شده", (summary.totalSuccess || 0) > 0 ? "ok" : ""),
            summaryCard("در انتظار", Number(summary.totalPending || 0).toLocaleString("fa-IR"), "سفارش‌های باز", (summary.totalPending || 0) > 0 ? "warn" : ""),
            summaryCard("نیازمند توجه", Number((summary.totalFailed || 0) + (summary.totalCanceled || 0) + (summary.totalExpired || 0)).toLocaleString("fa-IR"), "ناموفق، لغو یا منقضی", ((summary.totalFailed || 0) + (summary.totalCanceled || 0) + (summary.totalExpired || 0)) > 0 ? "danger" : ""),
            summaryCard("اعلان نخوانده", Number(summary.unreadNotifications || 0).toLocaleString("fa-IR"), "اعلان‌های مالی جدید", (summary.unreadNotifications || 0) > 0 ? "warn" : "ok"),
            "</div>"
        ].join("");
    }

    function syncGatewayPayload(payload) {
        var bundle = null;
        if (payload && payload.gateways && !Array.isArray(payload.gateways) && typeof payload.gateways === "object" && Array.isArray(payload.gateways.gateways)) {
            bundle = payload.gateways;
        } else if (payload && Array.isArray(payload.gateways)) {
            bundle = payload;
        }
        if (!bundle) {
            return;
        }
        state.gatewaySettings = bundle;
        state.gateways = Array.isArray(bundle.gateways) ? bundle.gateways : [];
    }

    async function loadDashboard(silent) {
        if (!state.currentUser || state.loadingDashboard) {
            return;
        }
        state.loadingDashboard = true;
        if (!silent) {
            setFeedback(feedbackNode, "در حال دریافت داده‌های مدیریت خرید...", "", true);
        }
        try {
            var response = await request("ownerDashboard", {}, "GET");
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                return;
            }
            if (!response || !response.success) {
                setFeedback(feedbackNode, (response && response.error) || "دریافت داده‌های مدیریت خرید انجام نشد.", "error");
                return;
            }
            state.dashboardLoaded = true;
            state.summary = response.summary || {};
            state.items = Array.isArray(response.items) ? response.items : [];
            state.collections = Array.isArray(response.collections) ? response.collections : [];
            state.notifications = Array.isArray(response.notifications) ? response.notifications : [];
            syncGatewayPayload(response);
            if (!silent) {
                var reconciliation = response.reconciliation || {};
                var verified = Number(reconciliation.verified || 0);
                setFeedback(
                    feedbackNode,
                    verified > 0 ? (verified.toLocaleString("fa-IR") + " پرداخت در انتظار با استعلام درگاه تایید شد.") : "داده‌های مدیریت خرید به‌روزرسانی شد.",
                    "success"
                );
            }
        } finally {
            state.loadingDashboard = false;
        }
    }

    function currentFilters() {
        return {
            itemId: $("payments-filter-item") ? String($("payments-filter-item").value || "0") : state.orderFilters.itemId,
            source: $("payments-filter-source") ? String($("payments-filter-source").value || "") : state.orderFilters.source,
            method: $("payments-filter-method") ? String($("payments-filter-method").value || "") : state.orderFilters.method,
            formId: $("payments-filter-form") ? String($("payments-filter-form").value || "") : state.orderFilters.formId,
            status: $("payments-filter-status") ? String($("payments-filter-status").value || "all") : state.orderFilters.status,
            dateFrom: $("payments-filter-date-from") ? String($("payments-filter-date-from").value || "") : state.orderFilters.dateFrom,
            dateTo: $("payments-filter-date-to") ? String($("payments-filter-date-to").value || "") : state.orderFilters.dateTo,
            query: $("payments-filter-query") ? String($("payments-filter-query").value || "").trim() : state.orderFilters.query
        };
    }

    async function loadOrders(silent) {
        if (!state.currentUser || state.loadingOrders) {
            return;
        }
        state.loadingOrders = true;
        if (!silent && $("payments-orders-list")) {
            $("payments-orders-list").innerHTML = '<div class="owner-empty">در حال دریافت سفارش‌ها...</div>';
        }
        try {
            var response = await request("ownerOrders", currentFilters(), "GET");
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                return;
            }
            if (!response || !response.success) {
                setFeedback(feedbackNode, (response && response.error) || "دریافت سفارش‌ها انجام نشد.", "error");
                return;
            }
            state.orders = Array.isArray(response.orders) ? response.orders : [];
            state.summary = response.summary || state.summary;
            state.ordersSummary = response.filteredSummary || response.summary || null;
            if (response.filters && typeof response.filters === "object") {
                state.orderFilters = {
                    itemId: String(response.filters.itemId || "0"),
                    source: String(response.filters.source || "") === "all" ? "" : String(response.filters.source || ""),
                    method: String(response.filters.method || "") === "all" ? "" : String(response.filters.method || ""),
                    formId: String(response.filters.formId || ""),
                    status: String(response.filters.status || "all"),
                    dateFrom: String(response.filters.dateFrom || ""),
                    dateTo: String(response.filters.dateTo || ""),
                    query: String(response.filters.query || "")
                };
            }
            renderOrdersSummary();
            renderOrdersList();
            if (!silent && response.reconciliation && Number(response.reconciliation.verified || 0) > 0) {
                setFeedback(feedbackNode, Number(response.reconciliation.verified || 0).toLocaleString("fa-IR") + " پرداخت pending با استعلام درگاه تایید شد.", "success");
            }
        } finally {
            state.loadingOrders = false;
        }
    }

    async function loadOrderDetail(orderId) {
        var cleanId = Number(orderId || 0);
        if (!cleanId) {
            root.innerHTML = '<div class="owner-empty">شناسه سفارش در آدرس معتبر نیست.</div>';
            return;
        }
        renderLoading("در حال دریافت جزئیات سفارش...");
        var response = await request("ownerOrderDetails", { id: cleanId }, "GET");
        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
            return;
        }
        if (!response || !response.success || !response.order) {
            root.innerHTML = '<div class="owner-empty">' + escapeHtml((response && response.error) || "جزئیات سفارش دریافت نشد.") + "</div>";
            return;
        }
        state.currentOrder = response.order;
        state.currentGatewaySnapshot = response.gatewaySnapshot || {};
        renderOrderDetailPage();
    }

    function renderOverviewPage() {
        setHead("داشبورد", "مرور مدیریت خرید", "وضعیت فروش و مسیرهای اصلی مدیریت را کوتاه و قابل اقدام ببینید.");
        setActions([
            '<a class="shell-action-btn shell-action-btn-primary" href="/buy/manage/items/new/">آیتم جدید</a>',
            '<a class="shell-action-btn" href="/buy/manage/orders/">سوابق</a>',
            '<button class="shell-action-btn" type="button" data-payment-reconcile-orders>استعلام pending</button>',
            '<button class="shell-action-btn" type="button" data-payment-refresh>به‌روزرسانی</button>'
        ].join(""));

        var modules = [
            { title: "آیتم‌ها", meta: "ساخت، ویرایش، فعال/غیرفعال، کپی لینک و حذف", href: "/buy/manage/items/", action: "مدیریت آیتم‌ها" },
            { title: "جمع‌آوری هزینه", meta: "تعریف مبلغ ثابت، دریافت لینک پرداخت و خروجی فهرست پرداخت‌کننده‌ها", href: "/payments/manage/", action: "مدیریت لینک‌ها" },
            { title: "درگاه‌ها", meta: "credential، وضعیت فعال، پیش‌فرض و حذف امن", href: "/buy/manage/gateways/", action: "مدیریت درگاه‌ها" },
            { title: "سوابق و خریداران", meta: "پوشه‌های وضعیت، خروجی، حذف سوابق و آمار بازه", href: "/buy/manage/orders/", action: "مدیریت سوابق" }
        ];
        root.innerHTML = [
            renderSummary(state.summary),
            '<section class="buy-manage-module-grid" aria-label="میانبرهای مدیریت">',
            modules.map(function (entry) {
                return [
                    '<article class="buy-manage-module">',
                    '  <div><span class="buy-kicker">بخش</span><h3>' + escapeHtml(entry.title) + "</h3><p>" + escapeHtml(entry.meta) + "</p></div>",
                    '  <a class="shell-action-btn" href="' + escapeHtml(entry.href) + '">' + escapeHtml(entry.action) + "</a>",
                    "</article>"
                ].join("");
            }).join(""),
            "</section>",
            '<section class="owner-block owner-block--payments">',
            '  <div class="owner-block__head"><div><h4>اعلان‌های اخیر پرداخت</h4><p>آخرین تغییرات وضعیت سفارش‌ها و پرداخت‌ها؛ اعلان‌های خوانده‌نشده بالاتر نمایش داده می‌شوند.</p></div><button class="shell-action-btn" type="button" data-payment-read-all-notes>خواندن همه</button></div>',
            '  <div id="payments-notifications-list" class="payments-notifications-list">',
            notificationsHtml(),
            "  </div>",
            "</section>"
        ].join("");
    }

    function notificationsHtml() {
        if (!state.notifications.length) {
            return '<div class="owner-empty">اعلان مالی جدیدی ثبت نشده است.</div>';
        }
        return state.notifications.slice().sort(function (a, b) {
            var aRead = !!String(a.read_at || "").trim();
            var bRead = !!String(b.read_at || "").trim();
            if (aRead !== bRead) return aRead ? 1 : -1;
            return String(b.created_at || "").localeCompare(String(a.created_at || ""));
        }).slice(0, 12).map(function (note) {
            var read = !!String(note.read_at || "").trim();
            return [
                '<article class="payments-note' + (read ? "" : " is-unread") + '">',
                '  <div class="payments-note__head">',
                '    <strong>' + escapeHtml(note.title || "اعلان مالی") + "</strong>",
                '    <span>' + escapeHtml(formatDateTime(note.created_at, "—")) + "</span>",
                "  </div>",
                '  <p>' + escapeHtml(note.body || "—") + "</p>",
                '  <div class="payments-note__actions">',
                note.related_order_id ? '<a class="shell-action-btn" href="/buy/manage/orders/detail/?id=' + encodeURIComponent(String(note.related_order_id)) + '">جزئیات سفارش</a>' : "",
                read ? '<span class="payments-note__state">خوانده شده</span>' : '<button class="shell-action-btn" type="button" data-payment-read-note="' + escapeHtml(note.id) + '">خواندم</button>',
                "  </div>",
                "</article>"
            ].join("");
        }).join("");
    }

    function itemMatchesFilters(item) {
        var query = state.itemQuery.trim().toLowerCase();
        var haystack = [
            item.title,
            item.shortDescription,
            item.slug,
            item.categoryLabel,
            item.category
        ].join(" ").toLowerCase();
        if (query && haystack.indexOf(query) < 0) {
            return false;
        }
        if (state.itemStatus !== "all" && String(item.status || "") !== state.itemStatus) {
            return false;
        }
        return true;
    }

    function renderItemsPage() {
        setHead("کاتالوگ", "لیست آیتم‌ها", "ابتدا آیتم را در لیست پیدا کنید، بعد مشاهده، ویرایش، تغییر وضعیت یا حذف را انجام دهید.");
        setActions([
            '<a class="shell-action-btn shell-action-btn-primary" href="/buy/manage/items/new/">آیتم جدید</a>',
            '<button class="shell-action-btn" type="button" data-payment-refresh>به‌روزرسانی</button>'
        ].join(""));
        root.innerHTML = [
            '<section class="buy-manage-toolbar" aria-label="فیلتر آیتم‌ها">',
            '  <label class="buy-manage-search"><span>جست‌وجو</span><input id="payments-items-search" type="search" value="' + escapeHtml(state.itemQuery) + '" placeholder="عنوان، لینک یا دسته"></label>',
            '  <label class="buy-manage-select"><span>وضعیت</span><select id="payments-items-status-filter"><option value="all">همه وضعیت‌ها</option><option value="active">فعال</option><option value="inactive">غیرفعال</option></select></label>',
            "</section>",
            '<div id="payments-items-grid" class="payments-items-grid buy-manage-list"></div>'
        ].join("");
        if ($("payments-items-status-filter")) {
            $("payments-items-status-filter").value = state.itemStatus;
        }
        renderItemsList();
    }

    function renderItemsList() {
        var node = $("payments-items-grid");
        if (!node) {
            return;
        }
        var visible = state.items.filter(itemMatchesFilters);
        if (!state.items.length) {
            node.innerHTML = '<div class="owner-empty">هنوز آیتمی ساخته نشده است.</div>';
            return;
        }
        if (!visible.length) {
            node.innerHTML = '<div class="owner-empty">با این فیلتر آیتمی پیدا نشد.</div>';
            return;
        }
        node.innerHTML = visible.map(function (item) {
            var publicUrl = absoluteUrl(item.publicUrl || "");
            return [
                '<article class="payments-item-card buy-manage-row">',
                '  <div class="payments-item-card__head">',
                '    <div>',
                '      <span class="payments-pill payments-pill--' + escapeHtml(itemStateTone(item)) + '">' + escapeHtml(itemStateLabel(item)) + "</span>",
                '      <h4>' + escapeHtml(item.title || "بدون عنوان") + "</h4>",
                '      <p>' + escapeHtml(itemCategoryLabel(item)) + ' • <span dir="ltr">' + escapeHtml(item.slug || "") + "</span></p>",
                "    </div>",
                '    <strong>' + escapeHtml(money(item.price || 0)) + "</strong>",
                "  </div>",
                '  <div class="payments-item-card__meta">',
                '    <span>فروخته‌شده: ' + escapeHtml(Number(item.soldCount || 0).toLocaleString("fa-IR")) + "</span>",
                '    <span>حداکثر هر سفارش: ' + escapeHtml(Number(item.maxQuantityPerOrder || 1).toLocaleString("fa-IR")) + "</span>",
                '    <span>مهلت: ' + escapeHtml(formatDateTime(item.expiresAt, "بدون مهلت")) + "</span>",
                '    <span>لینک: <a href="' + escapeHtml(publicUrl || "#") + '" target="_blank" rel="noopener">' + escapeHtml(publicUrl || "—") + "</a></span>",
                "  </div>",
                '  <div class="payments-item-card__actions">',
                '    <a class="shell-action-btn" href="' + escapeHtml(publicUrl || "#") + '" target="_blank" rel="noopener">مشاهده</a>',
                '    <a class="shell-action-btn shell-action-btn-primary" href="/buy/manage/items/edit/?id=' + encodeURIComponent(String(item.id || "")) + '">ویرایش</a>',
                '    <button class="shell-action-btn" type="button" data-payment-copy-link="' + escapeHtml(publicUrl || "") + '">کپی لینک</button>',
                '    <button class="shell-action-btn" type="button" data-payment-toggle-item="' + escapeHtml(item.id) + '" data-payment-enabled="' + (item.status === "active" ? "0" : "1") + '">' + (item.status === "active" ? "غیرفعال‌کردن" : "فعال‌کردن") + "</button>",
                '    <button class="shell-action-btn shell-action-btn-danger" type="button" data-payment-delete-item="' + escapeHtml(item.id) + '">حذف</button>',
                "  </div>",
                "</article>"
            ].join("");
        }).join("");
    }

    function itemFormHtml() {
        return [
            '<form id="payments-item-form" class="account-form payments-item-form payments-item-form--refined buy-manage-form" novalidate>',
            '  <input id="payments-item-id" type="hidden">',
            '  <input id="payments-item-hero-image" type="hidden">',
            '  <textarea id="payments-item-gallery" hidden></textarea>',
            '  <div class="buy-manage-editor">',
            '    <aside class="payments-owner-preview buy-manage-preview" aria-label="پیش‌نمایش آیتم">',
            '      <div class="payments-owner-preview__media" id="payments-preview-image"><span>بدون تصویر</span></div>',
            '      <div class="payments-owner-preview__body">',
            '        <div class="payments-owner-preview__chips"><span id="payments-preview-category">سفارش گروهی</span><span id="payments-preview-status">غیرفعال</span></div>',
            '        <h5 id="payments-preview-title">عنوان آیتم خرید</h5>',
            '        <p id="payments-preview-short">پیش‌نمایش کارت عمومی آیتم.</p>',
            '        <strong id="payments-preview-price">۰ ریال</strong>',
            '        <div id="payments-preview-meta" class="payments-owner-preview__meta"></div>',
            '      </div>',
            '      <div class="payments-owner-preview__actions">',
            '        <button id="payments-fill-shield-sample" class="shell-action-btn" type="button">پر کردن نمونه</button>',
            '        <a class="shell-action-btn" href="/buy/" target="_blank" rel="noopener">کاتالوگ</a>',
            '      </div>',
            '    </aside>',
            '    <div class="buy-manage-editor__main">',
            '      <nav class="buy-manage-subtabs" aria-label="بخش‌های فرم آیتم">',
            itemSectionButton("basic", "اطلاعات اصلی"),
            itemSectionButton("sale", "فروش"),
            itemSectionButton("media", "رسانه"),
            itemSectionButton("rules", "شرایط"),
            itemSectionButton("advanced", "پیشرفته"),
            "      </nav>",
            itemPanelBasic(),
            itemPanelSale(),
            itemPanelMedia(),
            itemPanelRules(),
            itemPanelAdvanced(),
            '      <div class="payments-step-actions buy-manage-savebar">',
            '        <a class="shell-action-btn" href="/buy/manage/items/">بازگشت به لیست</a>',
            '        <button id="payments-item-save" class="shell-action-btn shell-action-btn-primary" type="submit">ذخیره آیتم</button>',
            '      </div>',
            '      <div id="payments-item-form-feedback" class="account-feedback account-feedback--inline" aria-live="polite"></div>',
            "    </div>",
            "  </div>",
            "</form>"
        ].join("");
    }

    function itemSectionButton(key, label) {
        return '<button class="' + (activeItemSection === key ? "is-active" : "") + '" type="button" data-payment-item-section="' + key + '">' + escapeHtml(label) + "</button>";
    }

    function panelAttrs(key) {
        return ' class="payments-form-section buy-manage-panel" data-payment-item-panel="' + key + '"' + (activeItemSection === key ? "" : " hidden");
    }

    function itemPanelBasic() {
        return [
            '<section' + panelAttrs("basic") + '>',
            '  <div class="payments-form-section__head"><span>۱</span><div><h5>عنوان و معرفی</h5><p>اطلاعاتی که کاربر در نگاه اول می‌بیند.</p></div></div>',
            '  <div class="payments-form-grid">',
            field("payments-item-title", "عنوان آیتم", "text", "مثلا شیلد ابری سامان زر دندان", true, "payments-field--full", "140"),
            field("payments-item-slug", "اسلاگ / لینک عمومی", "text", "saman-foam-face-shield", false, "payments-field--full", "120", "ltr", "data-latin-digits=\"true\""),
            selectField("payments-item-category", "دسته‌بندی", [
                ["educational_supplies", "ملزومات آموزشی"],
                ["consumables", "اقلام مصرفی"],
                ["event_registration", "ثبت‌نام رویداد"],
                ["educational_package", "بسته آموزشی"],
                ["group_order", "سفارش گروهی"]
            ]),
            selectField("payments-item-status", "وضعیت انتشار", [["active", "فعال و قابل خرید"], ["inactive", "غیرفعال"]]),
            textareaField("payments-item-short-description", "توضیح کوتاه کارت کالا", "خلاصه کوتاه برای کارت محصول و صفحه جزئیات", "payments-field--full", "460"),
            textareaField("payments-item-full-description", "شرح کامل و شرایط خرید", "شرح کامل آیتم، شرایط خرید، محدودیت‌ها یا محتویات بسته", "payments-field--full", "6000"),
            "  </div>",
            "</section>"
        ].join("");
    }

    function itemPanelSale() {
        return [
            '<section' + panelAttrs("sale") + '>',
            '  <div class="payments-form-section__head"><span>۲</span><div><h5>قیمت، ظرفیت و بازه فروش</h5><p>مقادیر عملیاتی پرداخت و محدودیت سفارش.</p></div></div>',
            '  <div class="payments-form-grid">',
            field("payments-item-price", "قیمت واحد برای درگاه (ریال)", "text", "1200000", true, "", "12", "ltr", "inputmode=\"numeric\" data-latin-digits=\"true\""),
            field("payments-item-max-quantity", "حداکثر تعداد هر سفارش", "text", "1", false, "", "3", "ltr", "inputmode=\"numeric\" data-latin-digits=\"true\""),
            field("payments-item-capacity", "ظرفیت موجودی", "text", "اختیاری", false, "", "8", "ltr", "inputmode=\"numeric\" data-latin-digits=\"true\""),
            field("payments-item-starts-at", "زمان شروع", "datetime-local", "", false),
            field("payments-item-expires-at", "مهلت پرداخت", "datetime-local", "", false),
            switchField("payments-item-allow-cancellation", "لغو با هماهنگی فعال باشد", "اگر فعال شود، متن شرایط لغو در صفحه آیتم نمایش داده می‌شود."),
            "  </div>",
            "</section>"
        ].join("");
    }

    function itemPanelMedia() {
        return [
            '<section' + panelAttrs("media") + '>',
            '  <div class="payments-form-section__head"><span>۳</span><div><h5>تصویر اصلی و گالری</h5><p>تصاویر در storage پرداخت ذخیره می‌شوند و جزو فایل‌های deploy نیستند.</p></div></div>',
            '  <div class="payments-form-grid">',
            '    <div class="payments-field payments-field--full">',
            '      <span class="payments-field-label">تصویر اصلی کالا</span>',
            '      <div class="payments-image-uploader" id="payments-image-uploader">',
            '        <label class="payments-image-drop" for="payments-item-hero-file">',
            '          <input id="payments-item-hero-file" type="file" accept="image/jpeg,image/png,image/webp">',
            '          <img id="payments-item-hero-preview" alt="" hidden>',
            '          <span class="payments-image-drop__icon" aria-hidden="true">+</span>',
            '          <strong>افزودن عکس کالا</strong>',
            '          <small>jpg، png یا webp تا ۵ مگابایت.</small>',
            "        </label>",
            '        <div class="payments-image-toolbar"><button id="payments-item-hero-clear" class="shell-action-btn" type="button">حذف عکس</button><span id="payments-item-hero-status">تصویر اصلی هنوز انتخاب نشده است.</span></div>',
            "      </div>",
            "    </div>",
            '    <div class="payments-field payments-field--full">',
            '      <label for="payments-item-gallery-files">تصاویر گالری</label>',
            '      <div class="payments-gallery-uploader">',
            '        <input id="payments-item-gallery-files" type="file" accept="image/jpeg,image/png,image/webp" multiple>',
            '        <small>تصاویر انتخاب‌شده هنگام ذخیره آیتم آپلود می‌شوند.</small>',
            '        <div id="payments-gallery-list" class="payments-gallery-list"></div>',
            "      </div>",
            "    </div>",
            "  </div>",
            "</section>"
        ].join("");
    }

    function itemPanelRules() {
        return [
            '<section' + panelAttrs("rules") + '>',
            '  <div class="payments-form-section__head"><span>۴</span><div><h5>مخاطب، تحویل و پشتیبانی</h5><p>متن‌های عملیاتی که کاربر برای تصمیم خرید نیاز دارد.</p></div></div>',
            '  <div class="payments-form-grid">',
            field("payments-item-audience-note", "مخاطب آیتم", "text", "دانشجویان دندانپزشکی ورودی ۱۴۰۲", false, "payments-field--full", "220"),
            textareaField("payments-item-delivery-note", "تحویل یا نحوه استفاده", "مثلا تحویل حضوری در دانشکده دندانپزشکی", "payments-field--full", "360"),
            textareaField("payments-item-support-note", "مسیر پشتیبانی", "برای پیگیری، کد رهگیری را برای نماینده یا مالک سایت ارسال کنید.", "payments-field--full", "360"),
            textareaField("payments-item-specifications", "مشخصات قابل نمایش (JSON)", "[{\"label\":\"کاربرد\",\"value\":\"آموزشی\"}]", "payments-field--full", ""),
            "  </div>",
            "</section>"
        ].join("");
    }

    function itemPanelAdvanced() {
        return [
            '<section' + panelAttrs("advanced") + '>',
            '  <div class="payments-form-section__head"><span>۵</span><div><h5>فرم، تخفیف و پیام‌ها</h5><p>گزینه‌های کمتر تکراری در یک بخش مستقل نگه‌داری شده‌اند.</p></div></div>',
            '  <div class="payments-form-grid">',
            textareaField("payments-item-required-fields", "فیلدهای لازم از کاربر (JSON)", "[{\"name\":\"studentNumber\",\"label\":\"شماره دانشجویی\",\"type\":\"text\",\"required\":true}]", "payments-field--full", ""),
            '    <textarea id="payments-item-discount-codes" hidden></textarea>',
            '    <div class="payments-field payments-field--full payments-discount-field">',
            '      <span class="payments-field-label">کدهای تخفیف</span>',
            '      <div id="payments-discount-editor" class="payments-discount-editor"></div>',
            '      <button class="shell-action-btn" type="button" data-payment-add-discount>افزودن کد تخفیف</button>',
            "    </div>",
            field("payments-item-rating-average", "میانگین امتیاز", "text", "4.8", false, "", "4", "ltr", "inputmode=\"decimal\" data-latin-digits=\"true\""),
            field("payments-item-rating-count", "تعداد امتیاز", "text", "24", false, "", "6", "ltr", "inputmode=\"numeric\" data-latin-digits=\"true\""),
            textareaField("payments-item-reviews", "دیدگاه‌های قابل نمایش (JSON)", "[{\"name\":\"دانشجو\",\"rating\":5,\"body\":\"کیفیت مناسب\"}]", "payments-field--full", ""),
            textareaField("payments-item-success-message", "متن موفقیت", "پیام بعد از تایید پرداخت", "payments-field--full", "600"),
            textareaField("payments-item-failure-message", "متن خطا", "پیام پرداخت ناموفق یا لغوشده", "payments-field--full", "600"),
            "  </div>",
            "</section>"
        ].join("");
    }

    function field(id, label, type, placeholder, required, extraClass, maxLength, dir, attrs) {
        return [
            '<div class="payments-field ' + escapeHtml(extraClass || "") + '">',
            '  <label for="' + escapeHtml(id) + '">' + escapeHtml(label) + (required ? ' <span aria-hidden="true">*</span>' : "") + "</label>",
            '  <input id="' + escapeHtml(id) + '" type="' + escapeHtml(type || "text") + '"' + (maxLength ? ' maxlength="' + escapeHtml(maxLength) + '"' : "") + (dir ? ' dir="' + escapeHtml(dir) + '"' : "") + (placeholder ? ' placeholder="' + escapeHtml(placeholder) + '"' : "") + (required ? " required" : "") + (attrs ? " " + attrs : "") + ">",
            "</div>"
        ].join("");
    }

    function textareaField(id, label, placeholder, extraClass, maxLength) {
        return [
            '<div class="payments-field ' + escapeHtml(extraClass || "") + '">',
            '  <label for="' + escapeHtml(id) + '">' + escapeHtml(label) + "</label>",
            '  <textarea id="' + escapeHtml(id) + '"' + (maxLength ? ' maxlength="' + escapeHtml(maxLength) + '"' : "") + (placeholder ? ' placeholder=\'' + escapeHtml(placeholder) + '\'' : "") + "></textarea>",
            "</div>"
        ].join("");
    }

    function selectField(id, label, options) {
        return [
            '<div class="payments-field">',
            '  <label for="' + escapeHtml(id) + '">' + escapeHtml(label) + "</label>",
            '  <select id="' + escapeHtml(id) + '">',
            (options || []).map(function (entry) {
                return '<option value="' + escapeHtml(entry[0]) + '">' + escapeHtml(entry[1]) + "</option>";
            }).join(""),
            "  </select>",
            "</div>"
        ].join("");
    }

    function switchField(id, title, help) {
        return [
            '<label class="payments-switch payments-field--full" for="' + escapeHtml(id) + '">',
            '  <input id="' + escapeHtml(id) + '" type="checkbox">',
            '  <span><strong>' + escapeHtml(title) + "</strong><small>" + escapeHtml(help) + "</small></span>",
            "</label>"
        ].join("");
    }

    function renderItemFormPage() {
        var isEdit = mode === "edit";
        setHead(isEdit ? "ویرایش آیتم" : "آیتم جدید", isEdit ? "ویرایش آیتم خرید" : "ساخت آیتم خرید", "فرم آیتم به بخش‌های کوتاه تقسیم شده تا صفحه اصلی مدیریت شلوغ نشود.");
        setActions([
            '<a class="shell-action-btn" href="/buy/manage/items/">لیست آیتم‌ها</a>',
            isEdit ? '<a id="payments-open-public-item" class="shell-action-btn" href="#" target="_blank" rel="noopener" hidden>مشاهده عمومی</a>' : "",
            !isEdit ? '<button class="shell-action-btn" type="button" data-payment-fill-sample>نمونه</button>' : ""
        ].join(""));
        root.innerHTML = itemFormHtml();
        if (isEdit) {
            var itemId = Number(readParam("id") || 0);
            if (!itemId) {
                setFeedback($("payments-item-form-feedback"), "شناسه آیتم در آدرس معتبر نیست.", "error");
                return;
            }
            fillItemForm(itemId);
        } else {
            resetItemForm();
        }
    }

    function selectedText(id, fallback) {
        var node = $(id);
        if (!node || !node.options || node.selectedIndex < 0) {
            return fallback || "";
        }
        return String(node.options[node.selectedIndex].textContent || fallback || "").trim();
    }

    function firstImageFromForm() {
        var heroFile = selectedFile($("payments-item-hero-file"));
        if (heroFile) {
            return heroFilePreviewUrl(heroFile);
        }
        var hero = safeImageUrl(readField("payments-item-hero-image"));
        if (hero) {
            return hero;
        }
        var gallery = galleryValues();
        return gallery.length ? gallery[0] : "";
    }

    function renderHeroUploader() {
        var heroFileInput = $("payments-item-hero-file");
        var heroPreviewImage = $("payments-item-hero-preview");
        var heroStatus = $("payments-item-hero-status");
        var file = selectedFile(heroFileInput);
        var stored = safeImageUrl(readField("payments-item-hero-image"));
        var src = "";
        var status = "تصویر اصلی هنوز انتخاب نشده است.";
        if (file) {
            src = heroFilePreviewUrl(file);
            status = "تصویر انتخاب شده و با ذخیره آیتم آپلود می‌شود.";
        } else if (stored) {
            releaseHeroPreviewObjectUrl();
            src = stored;
            status = "تصویر اصلی برای این آیتم ثبت شده است.";
        } else {
            releaseHeroPreviewObjectUrl();
        }
        if (heroPreviewImage) {
            if (src) {
                heroPreviewImage.hidden = false;
                heroPreviewImage.src = src;
            } else {
                heroPreviewImage.hidden = true;
                heroPreviewImage.removeAttribute("src");
            }
        }
        if (heroStatus) {
            heroStatus.textContent = status;
        }
    }

    function renderGalleryUploader() {
        var galleryList = $("payments-gallery-list");
        if (!galleryList) {
            return;
        }
        var rows = [];
        galleryValues().forEach(function (url, index) {
            rows.push([
                '<article class="payments-gallery-thumb">',
                '  <img src="' + escapeHtml(url) + '" alt="تصویر گالری">',
                '  <button type="button" data-payment-remove-gallery="' + escapeHtml(index) + '">حذف</button>',
                "</article>"
            ].join(""));
        });
        selectedFiles($("payments-item-gallery-files")).forEach(function (file) {
            rows.push([
                '<article class="payments-gallery-thumb payments-gallery-thumb--pending">',
                '  <span>در انتظار آپلود</span>',
                '  <small>' + escapeHtml(file.name || "تصویر انتخاب‌شده") + "</small>",
                "</article>"
            ].join(""));
        });
        galleryList.innerHTML = rows.length ? rows.join("") : '<div class="payments-gallery-empty">تصویری برای گالری انتخاب نشده است.</div>';
    }

    function updateItemPreview() {
        var title = readField("payments-item-title") || "عنوان آیتم خرید";
        var shortDescription = readField("payments-item-short-description") || "پیش‌نمایش کارت عمومی آیتم.";
        var price = normalizeDigits(readField("payments-item-price")).replace(/\D+/g, "");
        var image = firstImageFromForm();
        var capacity = normalizeDigits(readField("payments-item-capacity")).replace(/\D+/g, "");
        var maxQuantity = normalizeDigits(readField("payments-item-max-quantity")).replace(/\D+/g, "") || "1";
        if ($("payments-preview-image")) {
            $("payments-preview-image").innerHTML = image ? '<img src="' + escapeHtml(image) + '" alt="' + escapeHtml(title) + '">' : "<span>بدون تصویر</span>";
        }
        if ($("payments-preview-category")) $("payments-preview-category").textContent = selectedText("payments-item-category", "سفارش گروهی");
        if ($("payments-preview-status")) $("payments-preview-status").textContent = selectedText("payments-item-status", "غیرفعال");
        if ($("payments-preview-title")) $("payments-preview-title").textContent = title;
        if ($("payments-preview-short")) $("payments-preview-short").textContent = shortDescription;
        if ($("payments-preview-price")) $("payments-preview-price").textContent = money(price || 0);
        if ($("payments-preview-meta")) {
            $("payments-preview-meta").innerHTML = [
                "<span><b>ظرفیت</b>" + escapeHtml(capacity ? Number(capacity).toLocaleString("fa-IR") : "نامحدود") + "</span>",
                "<span><b>حداکثر سفارش</b>" + escapeHtml(Number(maxQuantity).toLocaleString("fa-IR")) + "</span>",
                "<span><b>مهلت</b>" + escapeHtml(readField("payments-item-expires-at") ? formatDateTime(fromDatetimeLocal(readField("payments-item-expires-at")), "بدون مهلت") : "بدون مهلت") + "</span>"
            ].join("");
        }
        renderHeroUploader();
        renderGalleryUploader();
    }

    function resetItemForm() {
        var form = $("payments-item-form");
        if (!form) {
            return;
        }
        form.reset();
        writeField("payments-item-id", "");
        writeField("payments-item-hero-image", "");
        writeField("payments-item-gallery", "[]");
        writeField("payments-item-category", "group_order");
        writeField("payments-item-status", "inactive");
        writeField("payments-item-max-quantity", "1");
        writeField("payments-item-audience-note", "دانشجویان دندانپزشکی ورودی ۱۴۰۲");
        writeField("payments-item-delivery-note", "تحویل یا استفاده در محدوده دانشگاه علوم پزشکی تهران هماهنگ می‌شود.");
        writeField("payments-item-support-note", "برای پیگیری سفارش با نماینده یا مالک سایت تماس بگیرید.");
        writeField("payments-item-specifications", "[]");
        writeField("payments-item-required-fields", "[]");
        writeField("payments-item-discount-codes", "[]");
        writeField("payments-item-reviews", "[]");
        writeField("payments-item-success-message", "پرداخت شما با موفقیت ثبت شد.");
        writeField("payments-item-failure-message", "پرداخت شما ناموفق بود.");
        if ($("payments-item-allow-cancellation")) $("payments-item-allow-cancellation").checked = false;
        activeItemSection = "basic";
        syncItemPanels();
        renderDiscountEditor();
        updateItemPreview();
    }

    function fillItemForm(itemId) {
        var item = state.items.find(function (entry) {
            return Number(entry.id || 0) === Number(itemId || 0);
        });
        if (!item) {
            setFeedback($("payments-item-form-feedback"), "آیتم برای ویرایش پیدا نشد.", "error");
            return;
        }
        writeField("payments-item-id", item.id || "");
        writeField("payments-item-title", item.title || "");
        writeField("payments-item-category", item.category || "group_order");
        writeField("payments-item-slug", item.slug || "");
        writeField("payments-item-price", String(item.price || ""));
        writeField("payments-item-status", item.status || "inactive");
        writeField("payments-item-short-description", item.shortDescription || "");
        writeField("payments-item-full-description", item.fullDescription || "");
        writeField("payments-item-hero-image", item.heroImage || "");
        writeField("payments-item-gallery", prettyJson(item.gallery || []));
        writeField("payments-item-specifications", prettyJson(item.specifications || []));
        writeField("payments-item-required-fields", prettyJson(item.requiredFields || []));
        writeField("payments-item-audience-note", item.audienceNote || "دانشجویان دندانپزشکی ورودی ۱۴۰۲");
        writeField("payments-item-delivery-note", item.deliveryNote || "تحویل یا استفاده در محدوده دانشگاه علوم پزشکی تهران هماهنگ می‌شود.");
        writeField("payments-item-support-note", item.supportNote || "برای پیگیری سفارش با نماینده یا مالک سایت تماس بگیرید.");
        writeField("payments-item-max-quantity", String(item.maxQuantityPerOrder || 1));
        writeField("payments-item-discount-codes", prettyJson(item.discountCodes || []));
        writeField("payments-item-rating-average", item.ratingAverage ? String(item.ratingAverage) : "");
        writeField("payments-item-rating-count", item.ratingCount ? String(item.ratingCount) : "");
        writeField("payments-item-reviews", prettyJson(item.reviews || []));
        writeField("payments-item-success-message", item.successMessage || "پرداخت شما با موفقیت ثبت شد.");
        writeField("payments-item-failure-message", item.failureMessage || "پرداخت شما ناموفق بود.");
        writeField("payments-item-starts-at", toDatetimeLocal(item.startsAt));
        writeField("payments-item-expires-at", toDatetimeLocal(item.expiresAt));
        writeField("payments-item-capacity", item.capacity == null ? "" : String(item.capacity));
        if ($("payments-item-allow-cancellation")) $("payments-item-allow-cancellation").checked = !!item.allowCancellation;
        if ($("payments-open-public-item")) {
            $("payments-open-public-item").hidden = !item.publicUrl;
            $("payments-open-public-item").href = item.publicUrl || "#";
        }
        renderDiscountEditor();
        updateItemPreview();
    }

    function fillShieldSample() {
        resetItemForm();
        writeField("payments-item-title", SHIELD_SAMPLE.title);
        writeField("payments-item-category", SHIELD_SAMPLE.category);
        writeField("payments-item-slug", SHIELD_SAMPLE.slug);
        writeField("payments-item-price", SHIELD_SAMPLE.price);
        writeField("payments-item-status", SHIELD_SAMPLE.status);
        writeField("payments-item-short-description", SHIELD_SAMPLE.shortDescription);
        writeField("payments-item-full-description", SHIELD_SAMPLE.fullDescription);
        writeField("payments-item-hero-image", SHIELD_SAMPLE.heroImage);
        writeField("payments-item-expires-at", SHIELD_SAMPLE.expiresAt);
        writeField("payments-item-gallery", prettyJson(SHIELD_SAMPLE.gallery));
        writeField("payments-item-specifications", prettyJson(SHIELD_SAMPLE.specifications));
        writeField("payments-item-required-fields", prettyJson(SHIELD_SAMPLE.requiredFields));
        writeField("payments-item-audience-note", SHIELD_SAMPLE.audienceNote);
        writeField("payments-item-delivery-note", SHIELD_SAMPLE.deliveryNote);
        writeField("payments-item-support-note", SHIELD_SAMPLE.supportNote);
        writeField("payments-item-max-quantity", SHIELD_SAMPLE.maxQuantityPerOrder);
        writeField("payments-item-capacity", SHIELD_SAMPLE.capacity);
        writeField("payments-item-discount-codes", prettyJson(SHIELD_SAMPLE.discountCodes));
        writeField("payments-item-rating-average", SHIELD_SAMPLE.ratingAverage);
        writeField("payments-item-rating-count", SHIELD_SAMPLE.ratingCount);
        writeField("payments-item-reviews", prettyJson(SHIELD_SAMPLE.reviews));
        writeField("payments-item-success-message", SHIELD_SAMPLE.successMessage);
        writeField("payments-item-failure-message", SHIELD_SAMPLE.failureMessage);
        if ($("payments-item-allow-cancellation")) $("payments-item-allow-cancellation").checked = !!SHIELD_SAMPLE.allowCancellation;
        renderDiscountEditor();
        updateItemPreview();
        setFeedback($("payments-item-form-feedback"), "نمونه داخل فرم قرار گرفت.", "success");
    }

    function syncItemPanels() {
        document.querySelectorAll("[data-payment-item-section]").forEach(function (button) {
            button.classList.toggle("is-active", String(button.dataset.paymentItemSection || "") === activeItemSection);
        });
        document.querySelectorAll("[data-payment-item-panel]").forEach(function (panel) {
            panel.hidden = String(panel.dataset.paymentItemPanel || "") !== activeItemSection;
        });
    }

    async function uploadPaymentImage(file) {
        if (!file) {
            return null;
        }
        if (!/^image\/(jpeg|png|webp)$/i.test(String(file.type || ""))) {
            throw new Error("فرمت تصویر باید jpg، png یا webp باشد.");
        }
        if (Number(file.size || 0) > 5 * 1024 * 1024) {
            throw new Error("حجم تصویر باید کمتر از ۵ مگابایت باشد.");
        }
        var body = new FormData();
        body.append("image", file);
        var response = await requestFormData("ownerUploadImage", body);
        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
            throw new Error("نشست شما منقضی شده است.");
        }
        if (!response || !response.success || !response.image || !response.image.url) {
            throw new Error((response && response.error) || "آپلود تصویر انجام نشد.");
        }
        return response.image;
    }

    async function uploadSelectedImagesBeforeSave() {
        var heroImage = safeImageUrl(readField("payments-item-hero-image"));
        var gallery = galleryValues();
        var heroFileInput = $("payments-item-hero-file");
        var galleryFileInput = $("payments-item-gallery-files");
        var heroFile = selectedFile(heroFileInput);
        var galleryFiles = selectedFiles(galleryFileInput);
        if (heroFile) {
            setFeedback($("payments-item-form-feedback"), "در حال آپلود تصویر اصلی کالا...", "", true);
            var storedHero = await uploadPaymentImage(heroFile);
            heroImage = storedHero.url;
            writeField("payments-item-hero-image", heroImage);
            heroFileInput.value = "";
        }
        if (galleryFiles.length) {
            setFeedback($("payments-item-form-feedback"), "در حال آپلود تصاویر گالری...", "", true);
            for (var index = 0; index < galleryFiles.length; index += 1) {
                var storedGallery = await uploadPaymentImage(galleryFiles[index]);
                gallery.push(storedGallery.url);
            }
            galleryFileInput.value = "";
        }
        gallery = Array.from(new Set(gallery.filter(Boolean)));
        writeGalleryValues(gallery);
        if (!heroImage && gallery.length) {
            heroImage = gallery[0];
            writeField("payments-item-hero-image", heroImage);
        }
        updateItemPreview();
        return { heroImage: heroImage, gallery: gallery };
    }

    async function saveItem(event) {
        event.preventDefault();
        var formFeedback = $("payments-item-form-feedback");
        var titleValue = readField("payments-item-title");
        var priceValue = normalizeDigits(readField("payments-item-price")).replace(/\D+/g, "");
        if (!titleValue) {
            setFeedback(formFeedback, "عنوان آیتم الزامی است.", "error");
            $("payments-item-title").focus();
            return;
        }
        if (!priceValue || Number(priceValue) <= 0) {
            setFeedback(formFeedback, "قیمت واحد باید بیشتر از صفر باشد.", "error");
            $("payments-item-price").focus();
            return;
        }
        var uploadedImages;
        try {
            uploadedImages = await uploadSelectedImagesBeforeSave();
        } catch (error) {
            setFeedback(formFeedback, (error && error.message) || "آپلود تصویر انجام نشد.", "error");
            return;
        }
        if (!uploadedImages.heroImage) {
            setFeedback(formFeedback, "تصویر اصلی کالا الزامی است.", "error");
            activeItemSection = "media";
            syncItemPanels();
            return;
        }

        var payload = {
            id: readField("payments-item-id"),
            title: titleValue,
            category: readField("payments-item-category"),
            slug: readField("payments-item-slug"),
            price: priceValue,
            status: readField("payments-item-status"),
            shortDescription: readField("payments-item-short-description"),
            fullDescription: readField("payments-item-full-description"),
            heroImage: uploadedImages.heroImage,
            gallery: prettyJson(uploadedImages.gallery || []),
            specifications: readField("payments-item-specifications") || "[]",
            requiredFields: readField("payments-item-required-fields") || "[]",
            audienceNote: readField("payments-item-audience-note"),
            deliveryNote: readField("payments-item-delivery-note"),
            supportNote: readField("payments-item-support-note"),
            allowCancellation: $("payments-item-allow-cancellation") && $("payments-item-allow-cancellation").checked ? "1" : "0",
            maxQuantityPerOrder: normalizeDigits(readField("payments-item-max-quantity")).replace(/\D+/g, "") || "1",
            discountCodes: readField("payments-item-discount-codes") || "[]",
            ratingAverage: normalizeDigits(readField("payments-item-rating-average")).replace(/[^0-9.]+/g, ""),
            ratingCount: normalizeDigits(readField("payments-item-rating-count")).replace(/\D+/g, ""),
            reviews: readField("payments-item-reviews") || "[]",
            successMessage: readField("payments-item-success-message"),
            failureMessage: readField("payments-item-failure-message"),
            startsAt: fromDatetimeLocal(readField("payments-item-starts-at")),
            expiresAt: fromDatetimeLocal(readField("payments-item-expires-at")),
            capacity: normalizeDigits(readField("payments-item-capacity")).replace(/\D+/g, "")
        };
        if (!payload.slug) {
            payload.slug = slugifyLatin(payload.title);
        }
        setFeedback(formFeedback, "در حال ذخیره آیتم...", "", true);
        var response = await request("ownerSaveItem", payload, "POST");
        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
            return;
        }
        if (!response || !response.success || !response.item) {
            setFeedback(formFeedback, (response && response.error) || "ذخیره آیتم انجام نشد.", "error");
            return;
        }
        await loadDashboard(true);
        setFeedback(formFeedback, response.message || "آیتم ذخیره شد.", "success");
        if (mode !== "edit") {
            window.location.href = "/buy/manage/items/edit/?id=" + encodeURIComponent(String(response.item.id || ""));
        } else {
            fillItemForm(response.item.id);
        }
    }

    async function toggleItem(itemId, enabled) {
        setFeedback(feedbackNode, enabled ? "در حال فعال‌سازی آیتم..." : "در حال غیرفعال‌سازی آیتم...", "", true);
        var response = await request("ownerToggleItem", { id: itemId, enabled: enabled ? "1" : "0" }, "POST");
        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) return;
        if (!response || !response.success) {
            setFeedback(feedbackNode, (response && response.error) || "تغییر وضعیت آیتم انجام نشد.", "error");
            return;
        }
        await loadDashboard(true);
        renderPage();
        setFeedback(feedbackNode, response.message || "وضعیت آیتم به‌روزرسانی شد.", "success");
    }

    async function deleteItem(itemId) {
        var item = state.items.find(function (entry) {
            return Number(entry.id || 0) === Number(itemId || 0);
        });
        var title = item && item.title ? item.title : "این آیتم";
        if (!window.confirm("آیتم «" + title + "» از کاتالوگ حذف شود؟ سوابق سفارش‌های قبلی حفظ می‌شود.")) {
            return;
        }
        setFeedback(feedbackNode, "در حال حذف آیتم...", "", true);
        var response = await request("ownerDeleteItem", { id: itemId }, "POST");
        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) return;
        if (!response || !response.success) {
            setFeedback(feedbackNode, (response && response.error) || "حذف آیتم انجام نشد.", "error");
            return;
        }
        await loadDashboard(true);
        renderPage();
        setFeedback(feedbackNode, response.message || "آیتم حذف شد.", "success");
    }

    function collectionGatewayOptions(selected) {
        var selectedKey = String(selected || "");
        var rows = ['<option value="">درگاه پیش‌فرض فعال</option>'];
        state.gateways.filter(function (gateway) {
            return !!gateway.isEnabled;
        }).forEach(function (gateway) {
            var key = String(gateway.key || "");
            if (!key) return;
            rows.push('<option value="' + escapeHtml(key) + '"' + (key === selectedKey ? " selected" : "") + '>' + escapeHtml(gateway.label || gateway.providerLabel || key) + "</option>");
        });
        return rows.join("");
    }

    function collectionById(id) {
        var cleanId = Number(id || 0);
        return state.collections.find(function (collection) {
            return Number(collection.id || 0) === cleanId;
        }) || null;
    }

    function collectionSwitch(id, title, help, checked) {
        return [
            '<label class="payments-switch payments-filter-row--full" for="' + escapeHtml(id) + '">',
            '  <input id="' + escapeHtml(id) + '" type="checkbox"' + (checked ? " checked" : "") + '>',
            '  <span><strong>' + escapeHtml(title) + "</strong><small>" + escapeHtml(help) + "</small></span>",
            "</label>"
        ].join("");
    }

    function renderCollectionsPage() {
        setHead("جمع‌آوری هزینه", "لینک‌های پرداخت هزینه", "برای هزینه‌های خارج از کاتالوگ خرید، مبلغ ثابت تعریف کنید و اطلاعات لازم پرداخت‌کننده را تنظیم کنید.");
        setActions([
            '<button class="shell-action-btn shell-action-btn-primary" type="button" data-payment-reset-collection>لینک جدید</button>',
            '<button class="shell-action-btn" type="button" data-payment-refresh>به‌روزرسانی</button>'
        ].join(""));
        root.innerHTML = [
            '<section class="buy-manage-editor buy-manage-editor--collections">',
            '  <form id="payments-collection-form" class="payments-filter-grid payments-filter-grid--refined buy-manage-gateway-form" novalidate>',
            '    <input id="payments-collection-id" type="hidden">',
            '    <input id="payments-collection-image" type="hidden">',
            '    <div class="payments-filter-row payments-filter-row--full"><label for="payments-collection-title">عنوان هزینه</label><input id="payments-collection-title" type="text" maxlength="160" required placeholder="مثلا هزینه روپوش یا اردو"></div>',
            '    <div class="payments-filter-row"><label for="payments-collection-amount">مبلغ (ریال)</label><input id="payments-collection-amount" type="text" inputmode="numeric" dir="ltr" data-latin-digits="true" required placeholder="500000"></div>',
            '    <div class="payments-filter-row"><label for="payments-collection-status">وضعیت</label><select id="payments-collection-status"><option value="active">فعال</option><option value="inactive">غیرفعال</option></select></div>',
            '    <div class="payments-filter-row payments-filter-row--full payments-collection-image-row"><label for="payments-collection-image-file">تصویر اختیاری لینک</label><input id="payments-collection-image-file" type="file" accept="image/jpeg,image/png,image/webp"><div id="payments-collection-image-preview" class="payments-collection-image-preview"><span>بدون تصویر</span></div><small id="payments-collection-image-status">تصویر اختیاری است و در صفحه پرداخت نمایش داده می‌شود.</small></div>',
            '    <div class="payments-filter-row payments-filter-row--full"><label for="payments-collection-gateway">درگاه پیش‌فرض این لینک</label><select id="payments-collection-gateway">' + collectionGatewayOptions("") + "</select></div>",
            collectionSwitch("payments-collection-allow-guest", "پرداخت بدون ورود فعال باشد", "اگر فعال باشد، افراد مهمان هم می‌توانند از این لینک پرداخت کنند.", false),
            collectionSwitch("payments-collection-collect-name", "نام و نام خانوادگی در فرم پرداخت گرفته شود", "برای مهمان‌ها به‌صورت پیش‌فرض روشن بماند؛ کاربران واردشده در صورت خاموش بودن از اطلاعات حساب ثبت می‌شوند.", true),
            collectionSwitch("payments-collection-collect-phone", "شماره موبایل در فرم پرداخت گرفته شود", "اگر خاموش باشد، برای کاربران واردشده شماره ثبت‌شده حساب ذخیره می‌شود و مهمان‌ها شماره وارد نمی‌کنند.", true),
            collectionSwitch("payments-collection-collect-student", "شماره دانشجویی در فرم پرداخت گرفته شود", "برای هزینه‌هایی که پرداخت‌کننده باید با شماره دانشجویی مشخص شود فعال کنید.", false),
            '    <div class="payments-filter-row payments-filter-row--full"><label for="payments-collection-description">توضیح کوتاه</label><textarea id="payments-collection-description" maxlength="1200" rows="4"></textarea></div>',
            '    <div class="payments-filter-row payments-filter-row--full"><label for="payments-collection-success">پیام پرداخت موفق</label><textarea id="payments-collection-success" maxlength="600" rows="3"></textarea></div>',
            '    <div class="payments-filter-row payments-filter-row--full"><label for="payments-collection-failure">پیام پرداخت ناموفق</label><textarea id="payments-collection-failure" maxlength="600" rows="3"></textarea></div>',
            '    <div class="payments-inline-actions buy-manage-savebar"><button class="shell-action-btn shell-action-btn-primary" type="submit">ذخیره لینک</button><button class="shell-action-btn" type="button" data-payment-reset-collection>پاک کردن فرم</button></div>',
            '  </form>',
            '  <div id="payments-collections-list" class="payments-items-grid buy-manage-list"></div>',
            "</section>"
        ].join("");
        resetCollectionForm(false);
        renderCollectionsList();
    }

    function renderCollectionsList() {
        var node = $("payments-collections-list");
        if (!node) return;
        if (!state.collections.length) {
            node.innerHTML = '<div class="owner-empty">هنوز لینک جمع‌آوری هزینه‌ای ساخته نشده است.</div>';
            return;
        }
        node.innerHTML = state.collections.map(function (collection) {
            var publicUrl = absoluteUrl(collection.publicPath || "");
            var enabled = String(collection.status || "") === "active";
            var image = safeImageUrl(collection.imageUrl || "");
            return [
                '<article class="payments-item-card buy-manage-row payments-collection-card">',
                image ? '<div class="payments-collection-thumb"><img src="' + escapeHtml(image) + '" alt="' + escapeHtml(collection.title || "تصویر هزینه") + '"></div>' : "",
                '  <div class="payments-item-card__head">',
                '    <div><span class="payments-pill payments-pill--' + (enabled ? "ok" : "warn") + '">' + (enabled ? "فعال" : "غیرفعال") + "</span><h4>" + escapeHtml(collection.title || "هزینه بدون عنوان") + "</h4><p>" + escapeHtml(collection.description || "لینک پرداخت هزینه") + "</p></div>",
                '    <strong>' + escapeHtml(money(collection.amount || 0)) + "</strong>",
                "  </div>",
                '  <div class="payments-item-card__meta">',
                '    <span>پرداخت موفق: ' + escapeHtml(Number(collection.successCount || 0).toLocaleString("fa-IR")) + "</span>",
                '    <span>دریافتی: ' + escapeHtml(money(collection.receivedAmount || 0)) + "</span>",
                '    <span>پرداخت مهمان: ' + (collection.allowGuestPayments ? "فعال" : "غیرفعال") + "</span>",
                '    <span>لینک: <a href="' + escapeHtml(publicUrl || "#") + '" target="_blank" rel="noopener">' + escapeHtml(publicUrl || "—") + "</a></span>",
                "  </div>",
                '  <div class="payments-item-card__actions">',
                '    <button class="shell-action-btn shell-action-btn-primary" type="button" data-payment-edit-collection="' + escapeHtml(collection.id) + '">ویرایش</button>',
                '    <button class="shell-action-btn" type="button" data-payment-copy-link="' + escapeHtml(publicUrl || "") + '">کپی لینک</button>',
                '    <a class="shell-action-btn" href="/api/payments_api.php?action=ownerCollectionExport&id=' + encodeURIComponent(String(collection.id || "")) + '&format=csv">Excel/CSV</a>',
                '    <a class="shell-action-btn" href="/api/payments_api.php?action=ownerCollectionExport&id=' + encodeURIComponent(String(collection.id || "")) + '&format=txt">متنی</a>',
                '    <a class="shell-action-btn" href="/api/payments_api.php?action=ownerCollectionExport&id=' + encodeURIComponent(String(collection.id || "")) + '&format=json">JSON</a>',
                '    <button class="shell-action-btn shell-action-btn-danger" type="button" data-payment-delete-collection="' + escapeHtml(collection.id) + '">حذف</button>',
                "  </div>",
                "</article>"
            ].join("");
        }).join("");
    }

    function updateCollectionImagePreview() {
        var node = $("payments-collection-image-preview");
        if (!node) return;
        var image = safeImageUrl(readField("payments-collection-image"));
        node.innerHTML = image ? '<img src="' + escapeHtml(image) + '" alt="تصویر لینک پرداخت">' : "<span>بدون تصویر</span>";
    }

    function resetCollectionForm(clearFeedback) {
        if ($("payments-collection-form")) $("payments-collection-form").reset();
        writeField("payments-collection-id", "");
        writeField("payments-collection-image", "");
        writeField("payments-collection-status", "active");
        if ($("payments-collection-gateway")) {
            $("payments-collection-gateway").innerHTML = collectionGatewayOptions("");
        }
        if ($("payments-collection-allow-guest")) $("payments-collection-allow-guest").checked = false;
        if ($("payments-collection-collect-name")) $("payments-collection-collect-name").checked = true;
        if ($("payments-collection-collect-phone")) $("payments-collection-collect-phone").checked = true;
        if ($("payments-collection-collect-student")) $("payments-collection-collect-student").checked = false;
        updateCollectionImagePreview();
        if ($("payments-collection-image-status")) $("payments-collection-image-status").textContent = "تصویر اختیاری است و در صفحه پرداخت نمایش داده می‌شود.";
        if (clearFeedback !== false) {
            setFeedback(feedbackNode, "", "");
        }
    }

    function fillCollectionForm(collectionId) {
        var collection = collectionById(collectionId);
        if (!collection) {
            setFeedback(feedbackNode, "لینک پرداخت برای ویرایش پیدا نشد.", "error");
            return;
        }
        writeField("payments-collection-id", collection.id || "");
        writeField("payments-collection-title", collection.title || "");
        writeField("payments-collection-amount", String(collection.amount || ""));
        writeField("payments-collection-status", collection.status || "inactive");
        if ($("payments-collection-gateway")) {
            $("payments-collection-gateway").innerHTML = collectionGatewayOptions(collection.gateway || "");
        }
        if ($("payments-collection-allow-guest")) $("payments-collection-allow-guest").checked = !!collection.allowGuestPayments;
        if ($("payments-collection-collect-name")) $("payments-collection-collect-name").checked = collection.collectPayerName !== false;
        if ($("payments-collection-collect-phone")) $("payments-collection-collect-phone").checked = collection.collectPayerPhone !== false;
        if ($("payments-collection-collect-student")) $("payments-collection-collect-student").checked = !!collection.collectPayerStudentNumber;
        writeField("payments-collection-description", collection.description || "");
        writeField("payments-collection-success", collection.successMessage || "");
        writeField("payments-collection-failure", collection.failureMessage || "");
        writeField("payments-collection-image", collection.imageUrl || "");
        updateCollectionImagePreview();
        var form = $("payments-collection-form");
        if (form && typeof form.scrollIntoView === "function") {
            form.scrollIntoView({ block: "start", behavior: "smooth" });
        }
    }

    async function saveCollection(event) {
        event.preventDefault();
        var amount = normalizeDigits(readField("payments-collection-amount")).replace(/\D+/g, "");
        var title = readField("payments-collection-title");
        if (!title) {
            setFeedback(feedbackNode, "عنوان هزینه الزامی است.", "error");
            return;
        }
        if (!amount || Number(amount) <= 0) {
            setFeedback(feedbackNode, "مبلغ باید بیشتر از صفر باشد.", "error");
            return;
        }
        setFeedback(feedbackNode, "در حال ذخیره لینک پرداخت...", "", true);
        var imageUrl = safeImageUrl(readField("payments-collection-image"));
        var imageFileInput = $("payments-collection-image-file");
        var imageFile = imageFileInput && imageFileInput.files && imageFileInput.files[0] ? imageFileInput.files[0] : null;
        if (imageFile) {
            try {
                setFeedback(feedbackNode, "در حال آپلود تصویر لینک پرداخت...", "", true);
                var storedImage = await uploadPaymentImage(imageFile);
                imageUrl = storedImage.url || imageUrl;
                writeField("payments-collection-image", imageUrl);
                updateCollectionImagePreview();
            } catch (error) {
                setFeedback(feedbackNode, error && error.message ? error.message : "آپلود تصویر انجام نشد.", "error");
                return;
            }
        }
        setFeedback(feedbackNode, "در حال ذخیره لینک پرداخت...", "", true);
        var response = await request("ownerSaveCollection", {
            id: readField("payments-collection-id"),
            title: title,
            amount: amount,
            imageUrl: imageUrl,
            status: readField("payments-collection-status") || "active",
            gateway: readField("payments-collection-gateway"),
            description: readField("payments-collection-description"),
            successMessage: readField("payments-collection-success"),
            failureMessage: readField("payments-collection-failure"),
            allowGuestPayments: $("payments-collection-allow-guest") && $("payments-collection-allow-guest").checked ? "1" : "0",
            collectPayerName: $("payments-collection-collect-name") && $("payments-collection-collect-name").checked ? "1" : "0",
            collectPayerPhone: $("payments-collection-collect-phone") && $("payments-collection-collect-phone").checked ? "1" : "0",
            collectPayerStudentNumber: $("payments-collection-collect-student") && $("payments-collection-collect-student").checked ? "1" : "0"
        }, "POST");
        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) return;
        if (!response || !response.success) {
            setFeedback(feedbackNode, (response && response.error) || "ذخیره لینک پرداخت انجام نشد.", "error");
            return;
        }
        await loadDashboard(true);
        renderCollectionsPage();
        setFeedback(feedbackNode, response.message || "لینک پرداخت ذخیره شد.", "success");
    }

    async function deleteCollection(collectionId) {
        var collection = collectionById(collectionId);
        var title = collection && collection.title ? collection.title : "این لینک پرداخت";
        if (!window.confirm("لینک «" + title + "» حذف شود؟ سوابق پرداخت تاییدشده حفظ می‌شود.")) {
            return;
        }
        setFeedback(feedbackNode, "در حال حذف لینک پرداخت...", "", true);
        var response = await request("ownerDeleteCollection", { id: collectionId }, "POST");
        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) return;
        if (!response || !response.success) {
            setFeedback(feedbackNode, (response && response.error) || "حذف لینک پرداخت انجام نشد.", "error");
            return;
        }
        await loadDashboard(true);
        renderCollectionsPage();
        setFeedback(feedbackNode, response.message || "لینک پرداخت حذف شد.", "success");
    }

    function renderGatewaysPage() {
        setHead("درگاه", "مدیریت درگاه‌ها", "لیست درگاه‌ها از فرم ساخت و ویرایش جداست؛ حذف درگاه هم همینجا در دسترس است.");
        setActions([
            '<a class="shell-action-btn shell-action-btn-primary" href="/buy/manage/gateways/new/">درگاه جدید</a>',
            '<button class="shell-action-btn" type="button" data-payment-refresh>به‌روزرسانی</button>'
        ].join(""));
        root.innerHTML = '<div id="payments-gateways-list" class="payments-items-grid buy-manage-list"></div>';
        renderGatewaysList();
    }

    function renderGatewaysList() {
        var node = $("payments-gateways-list");
        if (!node) {
            return;
        }
        if (!state.gateways.length) {
            node.innerHTML = '<div class="owner-empty">هنوز درگاه پرداختی اضافه نشده است.</div>';
            return;
        }
        node.innerHTML = state.gateways.map(function (gateway) {
            var configured = !!gateway.isConfigured;
            var enabled = !!gateway.isEnabled;
            var provider = String(gateway.provider || "");
            var statusText = !enabled ? "غیرفعال" : (configured ? "فعال" : "نیازمند کلید");
            var tone = !enabled ? "warn" : (configured ? "ok" : "danger");
            return [
                '<article class="payments-item-card payments-gateway-card buy-manage-row">',
                '  <div class="payments-item-card__head">',
                '    <div>',
                '      <span class="payments-pill payments-pill--' + escapeHtml(tone) + '">' + escapeHtml(statusText) + "</span>",
                '      <h4>' + escapeHtml(gateway.label || "پرداخت آنلاین") + "</h4>",
                '      <p>' + escapeHtml(gateway.providerLabel || gatewayProviderLabel(provider)) + ' • <span dir="ltr">' + escapeHtml(gateway.key || "") + "</span></p>",
                "    </div>",
                gateway.isDefault ? '<strong>پیش‌فرض</strong>' : '<strong>درگاه</strong>',
                "  </div>",
                '  <div class="payments-item-card__meta">',
                '    <span>نوع: ' + escapeHtml(gatewayProviderLabel(provider)) + "</span>",
                '    <span>وضعیت کلید: ' + (configured ? "ثبت شده" : "ثبت نشده") + "</span>",
                '    <span>آخرین ویرایش: ' + escapeHtml(formatDateTime(gateway.updatedAt, "—")) + "</span>",
                "  </div>",
                '  <div class="payments-item-card__actions">',
                '    <a class="shell-action-btn shell-action-btn-primary" href="/buy/manage/gateways/edit/?id=' + encodeURIComponent(String(gateway.id || "")) + '">ویرایش</a>',
                '    <button class="shell-action-btn shell-action-btn-danger" type="button" data-payment-delete-gateway="' + escapeHtml(gateway.id) + '">حذف درگاه</button>',
                "  </div>",
                "</article>"
            ].join("");
        }).join("");
    }

    function gatewayFormHtml() {
        return [
            '<form id="payments-gateway-form" class="payments-filter-grid payments-filter-grid--refined buy-manage-gateway-form" novalidate>',
            '  <input id="payments-gateway-id" type="hidden">',
            '  <nav class="buy-manage-subtabs" aria-label="بخش‌های فرم درگاه">',
            '    <button class="' + (activeGatewaySection === "main" ? "is-active" : "") + '" type="button" data-payment-gateway-section="main">اطلاعات اصلی</button>',
            '    <button class="' + (activeGatewaySection === "advanced" ? "is-active" : "") + '" type="button" data-payment-gateway-section="advanced">URLهای پیشرفته</button>',
            "  </nav>",
            '  <section class="buy-manage-panel" data-payment-gateway-panel="main"' + (activeGatewaySection === "main" ? "" : " hidden") + '>',
            '    <div class="payments-filter-row"><label for="payments-gateway-provider">نوع درگاه</label><select id="payments-gateway-provider"><option value="zibal">زیبال</option><option value="zarinpal">زرین‌پال</option><option value="mock">آزمایشی</option></select></div>',
            '    <div class="payments-filter-row"><label for="payments-gateway-key">کلید داخلی</label><input id="payments-gateway-key" type="text" dir="ltr" maxlength="60" placeholder="zibal-main" data-latin-digits="true"></div>',
            '    <div class="payments-filter-row"><label for="payments-gateway-label">عنوان دکمه پرداخت</label><input id="payments-gateway-label" type="text" maxlength="80" placeholder="پرداخت آنلاین"></div>',
            '    <div class="payments-filter-row"><label for="payments-gateway-provider-label">نام نمایشی درگاه</label><input id="payments-gateway-provider-label" type="text" maxlength="120" placeholder="درگاه زیبال"></div>',
            '    <div class="payments-filter-row payments-filter-row--full"><label for="payments-gateway-merchant">Merchant یا API Key</label><input id="payments-gateway-merchant" type="text" dir="ltr" maxlength="260" autocomplete="off" data-latin-digits="true"></div>',
            switchGateway("payments-gateway-enabled", "درگاه فعال باشد", "درگاه فعال و کامل در مرحله پرداخت نمایش داده می‌شود.", true),
            switchGateway("payments-gateway-default", "درگاه پیش‌فرض باشد", "اگر چند درگاه فعال باشد، این گزینه اول انتخاب می‌شود.", false),
            "  </section>",
            '  <section class="buy-manage-panel" data-payment-gateway-panel="advanced"' + (activeGatewaySection === "advanced" ? "" : " hidden") + '>',
            '    <div class="payments-filter-row payments-filter-row--full"><label for="payments-gateway-request-url">Request URL</label><input id="payments-gateway-request-url" type="url" dir="ltr" maxlength="420" data-latin-digits="true"></div>',
            '    <div class="payments-filter-row payments-filter-row--full"><label for="payments-gateway-verify-url">Verify URL</label><input id="payments-gateway-verify-url" type="url" dir="ltr" maxlength="420" data-latin-digits="true"></div>',
            '    <div class="payments-filter-row payments-filter-row--full"><label for="payments-gateway-start-url">Start URL</label><input id="payments-gateway-start-url" type="url" dir="ltr" maxlength="420" data-latin-digits="true"></div>',
            "  </section>",
            '  <div class="payments-inline-actions buy-manage-savebar">',
            '    <a class="shell-action-btn" href="/buy/manage/gateways/">بازگشت به لیست</a>',
            '    <button class="shell-action-btn shell-action-btn-primary" type="submit">ذخیره درگاه</button>',
            "  </div>",
            "</form>"
        ].join("");
    }

    function switchGateway(id, title, help, checked) {
        return [
            '<label class="payments-switch payments-filter-row--full" for="' + escapeHtml(id) + '">',
            '  <input id="' + escapeHtml(id) + '" type="checkbox"' + (checked ? " checked" : "") + '>',
            '  <span><strong>' + escapeHtml(title) + "</strong><small>" + escapeHtml(help) + "</small></span>",
            "</label>"
        ].join("");
    }

    function renderGatewayFormPage() {
        var isEdit = mode === "edit";
        setHead(isEdit ? "ویرایش درگاه" : "درگاه جدید", isEdit ? "ویرایش درگاه پرداخت" : "ساخت درگاه پرداخت", "فرم درگاه از لیست جداست تا credential و وضعیت‌ها واضح بمانند.");
        setActions('<a class="shell-action-btn" href="/buy/manage/gateways/">لیست درگاه‌ها</a>');
        root.innerHTML = gatewayFormHtml();
        if (isEdit) {
            fillGatewayForm(Number(readParam("id") || 0));
        } else {
            resetGatewayForm();
        }
    }

    function gatewayById(id) {
        var gatewayId = Number(id || 0);
        return state.gateways.find(function (gateway) {
            return Number(gateway.id || 0) === gatewayId;
        }) || null;
    }

    function resetGatewayForm() {
        if ($("payments-gateway-form")) $("payments-gateway-form").reset();
        writeField("payments-gateway-id", "");
        writeField("payments-gateway-provider", "zibal");
        writeField("payments-gateway-label", gatewayPublicLabel("zibal"));
        writeField("payments-gateway-provider-label", gatewayProviderLabel("zibal"));
        if ($("payments-gateway-enabled")) $("payments-gateway-enabled").checked = true;
        if ($("payments-gateway-default")) $("payments-gateway-default").checked = state.gateways.filter(function (entry) { return !!entry.isEnabled; }).length === 0;
    }

    function fillGatewayForm(gatewayId) {
        var gateway = gatewayById(gatewayId);
        if (!gateway) {
            setFeedback(feedbackNode, "درگاه برای ویرایش پیدا نشد.", "error");
            return;
        }
        writeField("payments-gateway-id", gateway.id || "");
        writeField("payments-gateway-provider", gateway.provider || "zibal");
        writeField("payments-gateway-key", gateway.key || "");
        writeField("payments-gateway-label", gateway.label || gatewayPublicLabel(gateway.provider));
        writeField("payments-gateway-provider-label", gateway.providerLabel || gatewayProviderLabel(gateway.provider));
        writeField("payments-gateway-merchant", gateway.merchantId || gateway.apiKey || "");
        writeField("payments-gateway-request-url", gateway.requestUrl || "");
        writeField("payments-gateway-verify-url", gateway.verifyUrl || "");
        writeField("payments-gateway-start-url", gateway.startUrl || "");
        if ($("payments-gateway-enabled")) $("payments-gateway-enabled").checked = !!gateway.isEnabled;
        if ($("payments-gateway-default")) $("payments-gateway-default").checked = !!gateway.isDefault;
    }

    function syncGatewayPanels() {
        document.querySelectorAll("[data-payment-gateway-section]").forEach(function (button) {
            button.classList.toggle("is-active", String(button.dataset.paymentGatewaySection || "") === activeGatewaySection);
        });
        document.querySelectorAll("[data-payment-gateway-panel]").forEach(function (panel) {
            panel.hidden = String(panel.dataset.paymentGatewayPanel || "") !== activeGatewaySection;
        });
    }

    async function saveGateway(event) {
        event.preventDefault();
        var provider = readField("payments-gateway-provider") || "zibal";
        var credential = readField("payments-gateway-merchant");
        var enabled = $("payments-gateway-enabled") ? $("payments-gateway-enabled").checked : true;
        if (enabled && provider !== "mock" && !credential) {
            setFeedback(feedbackNode, "برای فعال‌سازی این درگاه، Merchant یا API Key را وارد کنید.", "error");
            activeGatewaySection = "main";
            syncGatewayPanels();
            return;
        }
        setFeedback(feedbackNode, "در حال ذخیره درگاه پرداخت...", "", true);
        var response = await request("ownerSaveGateway", {
            id: readField("payments-gateway-id"),
            provider: provider,
            key: readField("payments-gateway-key"),
            label: readField("payments-gateway-label"),
            providerLabel: readField("payments-gateway-provider-label"),
            merchantId: credential,
            apiKey: provider === "mock" ? "" : credential,
            requestUrl: readField("payments-gateway-request-url"),
            verifyUrl: readField("payments-gateway-verify-url"),
            startUrl: readField("payments-gateway-start-url"),
            isEnabled: enabled ? "1" : "0",
            isDefault: $("payments-gateway-default") && $("payments-gateway-default").checked ? "1" : "0"
        }, "POST");
        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) return;
        if (!response || !response.success) {
            setFeedback(feedbackNode, (response && response.error) || "ذخیره درگاه انجام نشد.", "error");
            return;
        }
        await loadDashboard(true);
        setFeedback(feedbackNode, response.message || "درگاه ذخیره شد.", "success");
        if (mode !== "edit" && response.gateway) {
            window.location.href = "/buy/manage/gateways/edit/?id=" + encodeURIComponent(String(response.gateway.id || ""));
        } else if (response.gateway) {
            fillGatewayForm(response.gateway.id);
        }
    }

    async function deleteGateway(gatewayId) {
        var gateway = gatewayById(gatewayId);
        var title = gateway && (gateway.label || gateway.key) ? (gateway.label || gateway.key) : "این درگاه";
        if (!window.confirm("درگاه «" + title + "» حذف شود؟ اگر سفارش در انتظار با این درگاه وجود داشته باشد، حذف انجام نمی‌شود.")) {
            return;
        }
        setFeedback(feedbackNode, "در حال حذف درگاه پرداخت...", "", true);
        var response = await request("ownerDeleteGateway", { id: gatewayId }, "POST");
        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) return;
        if (!response || !response.success) {
            setFeedback(feedbackNode, (response && response.error) || "حذف درگاه انجام نشد.", "error");
            return;
        }
        syncGatewayPayload(response);
        await loadDashboard(true);
        renderPage();
        setFeedback(feedbackNode, response.message || "درگاه حذف شد.", "success");
    }

    function breakdownOptions(kind, allLabel, selected) {
        var breakdowns = state.summary && state.summary.breakdowns && typeof state.summary.breakdowns === "object"
            ? state.summary.breakdowns
            : {};
        var rows = Array.isArray(breakdowns[kind]) ? breakdowns[kind] : (breakdowns[kind] && Array.isArray(breakdowns[kind].items) ? breakdowns[kind].items : []);
        var selectedKey = String(selected || "");
        return ['<option value="">' + escapeHtml(allLabel) + "</option>"].concat(rows.map(function (entry) {
            var key = String(entry.key || "");
            if (!key) return "";
            var label = String(entry.label || key) + " (" + Number(entry.successOrders || entry.successCount || 0).toLocaleString("fa-IR") + ")";
            return '<option value="' + escapeHtml(key) + '"' + (key === selectedKey ? " selected" : "") + '>' + escapeHtml(label) + "</option>";
        }).filter(Boolean)).join("");
    }

    function renderOrdersPage() {
        setHead("سوابق", "سوابق خرید و خریداران", "سوابق بر اساس پوشه‌های وضعیت، بازه زمانی و آیتم مدیریت می‌شوند؛ خروجی و پاکسازی هم از همین فیلتر انجام می‌شود.");
        setActions([
            '<button class="shell-action-btn shell-action-btn-primary" type="button" data-payment-reconcile-orders>استعلام گروهی درگاه</button>',
            '<button class="shell-action-btn" type="button" data-payment-export-orders>خروجی CSV</button>',
            '<button class="shell-action-btn" type="button" data-payment-refresh-orders>به‌روزرسانی</button>'
        ].join(""));
        var itemOptions = ['<option value="0">همه آیتم‌ها</option>'].concat(state.items.map(function (item) {
            return '<option value="' + escapeHtml(item.id) + '">' + escapeHtml(item.title || "بدون عنوان") + "</option>";
        }));
        var sourceOptions = breakdownOptions("sources", "همه مسیرهای پرداخت", state.orderFilters.source);
        var methodOptions = breakdownOptions("methods", "همه روش‌های پرداخت", state.orderFilters.method);
        var formOptions = breakdownOptions("forms", "همه فرم‌ها", state.orderFilters.formId);
        root.innerHTML = [
            orderFoldersHtml(),
            '<section class="payments-history-panel" aria-label="فیلتر و عملیات سوابق">',
            '<form id="payments-orders-filter-form" class="payments-filter-grid payments-filter-grid--refined buy-manage-orders-filter" novalidate>',
            '  <div class="payments-filter-row"><label for="payments-filter-item">آیتم</label><select id="payments-filter-item">' + itemOptions.join("") + "</select></div>",
            '  <div class="payments-filter-row"><label for="payments-filter-source">مسیر پرداخت</label><select id="payments-filter-source">' + sourceOptions + "</select></div>",
            '  <div class="payments-filter-row"><label for="payments-filter-method">روش پرداخت</label><select id="payments-filter-method">' + methodOptions + "</select></div>",
            '  <div class="payments-filter-row"><label for="payments-filter-form">فرم</label><select id="payments-filter-form">' + formOptions + "</select></div>",
            '  <div class="payments-filter-row"><label for="payments-filter-date-from">از تاریخ</label><input id="payments-filter-date-from" type="date"></div>',
            '  <div class="payments-filter-row"><label for="payments-filter-date-to">تا تاریخ</label><input id="payments-filter-date-to" type="date"></div>',
            '  <div class="payments-filter-row payments-filter-row--full"><label for="payments-filter-query">جست‌وجو</label><input id="payments-filter-query" type="search" placeholder="نام، شماره، authority یا ref id"></div>',
            '  <div class="payments-inline-actions"><button class="shell-action-btn shell-action-btn-primary" type="submit">اعمال فیلتر</button><button id="payments-filter-reset" class="shell-action-btn" type="button">حذف فیلتر</button></div>',
            "</form>",
            '  <div class="payments-history-actions">',
            '    <div><span class="buy-kicker">نمایش فعلی</span><strong>' + escapeHtml(statusFilterLabel(state.orderFilters.status)) + '</strong></div>',
            '    <div class="payments-inline-actions">',
            '      <button class="shell-action-btn' + (state.orderView === "orders" ? " shell-action-btn-primary" : "") + '" type="button" data-payment-order-view="orders">کارت‌های سفارش</button>',
            '      <button class="shell-action-btn' + (state.orderView === "buyers" ? " shell-action-btn-primary" : "") + '" type="button" data-payment-order-view="buyers">لیست خریداران آیتم‌ها</button>',
            '      <button class="shell-action-btn" type="button" data-payment-reconcile-orders>استعلام گروهی درگاه</button>',
            '      <button class="shell-action-btn" type="button" data-payment-export-orders>خروجی CSV</button>',
            '      <button class="shell-action-btn shell-action-btn-danger" type="button" data-payment-reset-orders>ریست سوابق فیلترشده</button>',
            "    </div>",
            "  </div>",
            "</section>",
            '<div id="payments-orders-summary" class="owner-summary owner-summary--compact"></div>',
            '<div id="payments-orders-list" class="payments-orders-list buy-manage-list"><div class="owner-empty">در حال دریافت سفارش‌ها...</div></div>'
        ].join("");
        $("payments-filter-item").value = state.orderFilters.itemId;
        $("payments-filter-source").value = state.orderFilters.source || "";
        $("payments-filter-method").value = state.orderFilters.method || "";
        $("payments-filter-form").value = state.orderFilters.formId || "";
        $("payments-filter-date-from").value = state.orderFilters.dateFrom;
        $("payments-filter-date-to").value = state.orderFilters.dateTo;
        $("payments-filter-query").value = state.orderFilters.query;
        renderOrdersSummary();
        renderOrdersList();
    }

    function orderFoldersHtml() {
        var summary = state.summary || {};
        var folders = [
            { key: "all", label: "همه", count: summary.totalOrders || 0, meta: "کل سوابق", tone: "" },
            { key: "success", label: "انجام‌شده", count: summary.totalSuccess || 0, meta: money(summary.totalReceived || 0), tone: "ok" },
            { key: "unfinished", label: "انجام‌نشده", count: (summary.totalPending || 0) + (summary.totalFailed || 0) + (summary.totalCanceled || 0) + (summary.totalExpired || 0), meta: money(summary.totalUnfinishedAmount || 0), tone: "warn" },
            { key: "pending", label: "در انتظار", count: summary.totalPending || 0, meta: money(summary.totalPendingAmount || 0), tone: "warn" },
            { key: "problem", label: "ناموفق/لغو", count: (summary.totalFailed || 0) + (summary.totalCanceled || 0) + (summary.totalExpired || 0), meta: money((summary.totalFailedAmount || 0) + (summary.totalCanceledAmount || 0) + (summary.totalExpiredAmount || 0)), tone: "danger" }
        ];
        return [
            '<nav class="buy-manage-folders" aria-label="پوشه‌های سوابق خرید">',
            folders.map(function (folder) {
                var active = String(state.orderFilters.status || "all") === folder.key;
                return [
                    '<button class="buy-manage-folder' + (active ? " is-active" : "") + (folder.tone ? (" is-" + folder.tone) : "") + '" type="button" data-payment-order-folder="' + escapeHtml(folder.key) + '">',
                    '  <span>' + escapeHtml(folder.label) + "</span>",
                    '  <strong>' + escapeHtml(Number(folder.count || 0).toLocaleString("fa-IR")) + "</strong>",
                    '  <small>' + escapeHtml(folder.meta || "") + "</small>",
                    "</button>"
                ].join("");
            }).join(""),
            "</nav>"
        ].join("");
    }

    function breakdownGroupHtml(summary, kind, title) {
        var breakdowns = summary && summary.breakdowns && typeof summary.breakdowns === "object" ? summary.breakdowns : {};
        var rows = Array.isArray(breakdowns[kind]) ? breakdowns[kind] : (breakdowns[kind] && Array.isArray(breakdowns[kind].items) ? breakdowns[kind].items : []);
        if (!rows.length) {
            return "";
        }
        return [
            '<section class="payments-breakdown-group">',
            '<h5>' + escapeHtml(title) + "</h5>",
            rows.slice(0, 6).map(function (entry) {
                return [
                    '<div class="payments-breakdown-row">',
                    '<span>' + escapeHtml(entry.label || entry.key || "—") + "</span>",
                    '<strong>' + escapeHtml(money(entry.receivedAmount || entry.successAmount || 0)) + "</strong>",
                    '<small>' + escapeHtml(Number(entry.successOrders || entry.successCount || 0).toLocaleString("fa-IR")) + " پرداخت</small>",
                    "</div>"
                ].join("");
            }).join(""),
            "</section>"
        ].join("");
    }

    function renderOrdersSummary() {
        var node = $("payments-orders-summary");
        if (!node) {
            return;
        }
        var summary = state.ordersSummary;
        if (!summary) {
            node.innerHTML = "";
            return;
        }
        node.innerHTML = [
            summaryCard("نمایش‌شده", Number(summary.totalOrders || 0).toLocaleString("fa-IR"), "خروجی فیلتر فعلی"),
            summaryCard("گردش فیلتر", money(summary.totalAmount || 0), "جمع مبلغ همه وضعیت‌ها"),
            summaryCard("دریافتی", money(summary.totalReceived || 0), "پرداخت‌های تاییدشده", (summary.totalReceived || 0) > 0 ? "ok" : ""),
            summaryCard("پرداخت‌نشده", money(summary.totalUnfinishedAmount || 0), "در انتظار، ناموفق، لغو یا منقضی", (summary.totalUnfinishedAmount || 0) > 0 ? "warn" : ""),
            '<div class="payments-breakdown-strip">',
            breakdownGroupHtml(summary, "sources", "تفکیک مسیر"),
            breakdownGroupHtml(summary, "methods", "تفکیک روش پرداخت"),
            breakdownGroupHtml(summary, "forms", "تفکیک فرم‌ها"),
            breakdownGroupHtml(summary, "collections", "تفکیک لینک‌های هزینه"),
            "</div>"
        ].join("");
    }

    function renderOrdersList() {
        var node = $("payments-orders-list");
        if (!node) {
            return;
        }
        if (state.orderView === "buyers") {
            renderBuyersList(node);
            return;
        }
        if (!state.orders.length) {
            node.innerHTML = '<div class="owner-empty">سفارشی با این فیلتر پیدا نشد.</div>';
            return;
        }
        node.innerHTML = state.orders.map(function (order) {
            var lines = orderLineRecords(order);
            var lineText = lines.length ? lines.map(function (line) {
                return line.title + " × " + Number(line.quantity || 1).toLocaleString("fa-IR");
            }).join("، ") : (order.itemTitle || "آیتم نامشخص");
            return [
                '<article class="payments-order-card buy-manage-row">',
                '  <div class="payments-order-card__head">',
                '    <div><span class="payments-pill payments-pill--' + escapeHtml(statusTone(order.status)) + '">' + escapeHtml(statusLabel(order.status)) + "</span><h4>" + escapeHtml(order.payerName || "بدون نام") + "</h4><p>" + escapeHtml(lineText) + "</p></div>",
                '    <strong>' + escapeHtml(money(order.amount || 0)) + "</strong>",
                "  </div>",
                '  <div class="payments-order-card__meta">',
                '    <span>تعداد: ' + escapeHtml(Number(order.quantity || 1).toLocaleString("fa-IR")) + "</span>",
                '    <span>مسیر: ' + escapeHtml(order.sourceLabel || order.source || "—") + "</span>",
                '    <span>روش: ' + escapeHtml(order.paymentMethodLabel || order.paymentMethod || "—") + "</span>",
                '    <span>موبایل: ' + escapeHtml(order.payerPhone || "—") + "</span>",
                '    <span>ثبت: ' + escapeHtml(formatDateTime(order.createdAt, "—")) + "</span>",
                '    <span>authority: ' + escapeHtml(order.authority || "—") + "</span>",
                "  </div>",
                '  <div class="payments-order-card__actions">',
                '    <a class="shell-action-btn shell-action-btn-primary" href="/buy/manage/orders/detail/?id=' + encodeURIComponent(String(order.id || "")) + '">جزئیات</a>',
                order.publicToken ? '<a class="shell-action-btn" href="/buy/result/?orderToken=' + encodeURIComponent(String(order.publicToken)) + '" target="_blank" rel="noopener">صفحه نتیجه</a>' : "",
                order.authority && order.status !== "success" ? '<button class="shell-action-btn" type="button" data-payment-verify-order="' + escapeHtml(order.id) + '">استعلام درگاه</button>' : "",
                '    <button class="shell-action-btn shell-action-btn-danger" type="button" data-payment-delete-order="' + escapeHtml(order.id) + '">حذف از سوابق</button>',
                "  </div>",
                "</article>"
            ].join("");
        }).join("");
    }

    function orderLineRecords(order) {
        var itemIdFilter = Number(state.orderFilters.itemId || 0);
        var lines = Array.isArray(order && order.cartItems) ? order.cartItems : [];
        if (!lines.length) {
            return [{
                itemId: Number(order && order.itemId || 0),
                slug: order && order.itemSlug || "",
                title: order && order.itemTitle || "آیتم نامشخص",
                quantity: Number(order && order.quantity || 1),
                amount: Number(order && order.amount || 0),
                unitPrice: Number(order && order.unitPrice || 0)
            }];
        }
        return lines.filter(function (line) {
            return !itemIdFilter || Number(line.itemId || 0) === itemIdFilter;
        }).map(function (line) {
            return {
                itemId: Number(line.itemId || 0),
                slug: String(line.slug || ""),
                title: String(line.title || line.slug || "آیتم"),
                quantity: Number(line.quantity || 1),
                amount: Number(line.amount || 0),
                unitPrice: Number(line.unitPrice || 0)
            };
        });
    }

    function buyerRecords() {
        var rows = [];
        state.orders.forEach(function (order) {
            orderLineRecords(order).forEach(function (line) {
                rows.push({
                    orderId: order.id || "",
                    itemId: line.itemId || 0,
                    itemTitle: line.title || "آیتم",
                    itemSlug: line.slug || "",
                    payerName: order.payerName || "بدون نام",
                    payerPhone: order.payerPhone || "—",
                    payerStudentNumber: order.payerStudentNumber || "—",
                    status: order.status || "pending",
                    quantity: line.quantity || 1,
                    amount: line.amount || order.amount || 0,
                    refId: order.refId || "",
                    authority: order.authority || "",
                    createdAt: order.createdAt || "",
                    verifiedAt: order.verifiedAt || ""
                });
            });
        });
        return rows;
    }

    function renderBuyersList(node) {
        var rows = buyerRecords();
        if (!rows.length) {
            node.innerHTML = '<div class="owner-empty">برای این فیلتر، خریداری در خطوط آیتم‌ها پیدا نشد.</div>';
            return;
        }
        var groups = {};
        rows.forEach(function (row) {
            var key = String(row.itemId || row.itemSlug || row.itemTitle || "item");
            if (!groups[key]) {
                groups[key] = {
                    title: row.itemTitle || "آیتم",
                    slug: row.itemSlug || "",
                    rows: []
                };
            }
            groups[key].rows.push(row);
        });
        node.innerHTML = Object.keys(groups).map(function (key) {
            var group = groups[key];
            var successCount = group.rows.filter(function (row) { return row.status === "success"; }).length;
            return [
                '<section class="payments-buyer-group">',
                '  <div class="payments-buyer-group__head">',
                '    <div><span class="buy-kicker">لیست خروجی آیتم</span><h4>' + escapeHtml(group.title) + "</h4><p>" + escapeHtml(group.slug || "همه خریدهای مطابق فیلتر") + "</p></div>",
                '    <strong>' + escapeHtml(successCount.toLocaleString("fa-IR")) + " موفق از " + escapeHtml(group.rows.length.toLocaleString("fa-IR")) + "</strong>",
                "  </div>",
                '  <div class="payments-buyer-list">',
                group.rows.map(function (row) {
                    return [
                        '<article class="payments-buyer-row">',
                        '  <div><span class="payments-pill payments-pill--' + escapeHtml(statusTone(row.status)) + '">' + escapeHtml(statusLabel(row.status)) + "</span><strong>" + escapeHtml(row.payerName) + "</strong><small>" + escapeHtml(row.payerPhone) + "</small></div>",
                        '  <div><span>تعداد</span><strong>' + escapeHtml(Number(row.quantity || 1).toLocaleString("fa-IR")) + "</strong></div>",
                        '  <div><span>مبلغ خط</span><strong>' + escapeHtml(money(row.amount || 0)) + "</strong></div>",
                        '  <div><span>ثبت</span><strong>' + escapeHtml(formatDateTime(row.createdAt, "—")) + "</strong></div>",
                        '  <a class="shell-action-btn" href="/buy/manage/orders/detail/?id=' + encodeURIComponent(String(row.orderId || "")) + '">سفارش</a>',
                        "</article>"
                    ].join("");
                }).join(""),
                "  </div>",
                "</section>"
            ].join("");
        }).join("");
    }

    function renderOrderDetailPage() {
        var order = state.currentOrder;
        var snapshot = state.currentGatewaySnapshot || {};
        setHead("جزئیات", "جزئیات سفارش #" + (order ? Number(order.id || 0).toLocaleString("fa-IR") : ""), "مبلغ، تعداد، خطوط سبد و وضعیت پرداخت در صفحه مستقل بررسی می‌شود.");
        setActions([
            '<a class="shell-action-btn" href="/buy/manage/orders/">بازگشت به سفارش‌ها</a>',
            order && order.publicToken ? '<a class="shell-action-btn" href="/buy/result/?orderToken=' + encodeURIComponent(String(order.publicToken)) + '" target="_blank" rel="noopener">صفحه نتیجه</a>' : ""
        ].join(""));
        if (!order) {
            root.innerHTML = '<div class="owner-empty">سفارش انتخاب نشده است.</div>';
            return;
        }
        var extras = order.extraFormData && typeof order.extraFormData === "object"
            ? Object.keys(order.extraFormData).map(function (key) {
                return '<div class="payments-detail-row"><span>' + escapeHtml(key) + '</span><strong>' + escapeHtml(order.extraFormData[key]) + "</strong></div>";
            }).join("")
            : "";
        var lineRows = Array.isArray(order.cartItems) && order.cartItems.length ? order.cartItems.map(function (line) {
            return [
                '<article class="buy-order-line">',
                '  <div><strong>' + escapeHtml(line.title || line.slug || "آیتم") + '</strong><span dir="ltr">' + escapeHtml(line.slug || "") + "</span></div>",
                '  <div><span>تعداد</span><strong>' + escapeHtml(Number(line.quantity || 1).toLocaleString("fa-IR")) + "</strong></div>",
                '  <div><span>قیمت واحد</span><strong>' + escapeHtml(money(line.unitPrice || 0)) + "</strong></div>",
                '  <div><span>جمع خط</span><strong>' + escapeHtml(money(line.subtotal || 0)) + "</strong></div>",
                '  <div><span>تخفیف</span><strong>' + escapeHtml(money(line.discountAmount || 0)) + "</strong></div>",
                '  <div><span>مبلغ نهایی خط</span><strong>' + escapeHtml(money(line.amount || 0)) + "</strong></div>",
                "</article>"
            ].join("");
        }).join("") : "";
        root.innerHTML = [
            renderSummary({
                totalItems: 0,
                activeItems: 0,
                totalReceived: order.status === "success" ? order.amount : 0,
                totalSuccess: order.status === "success" ? 1 : 0,
                totalPending: order.status === "pending" ? 1 : 0,
                totalFailed: order.status === "failed" ? 1 : 0,
                totalCanceled: order.status === "canceled" ? 1 : 0,
                totalExpired: order.status === "expired" ? 1 : 0,
                unreadNotifications: 0
            }),
            '<section class="owner-block owner-block--payments">',
            '  <div class="owner-block__head"><h4>اطلاعات سفارش</h4><p>مقادیر مبلغ و تعداد از رکورد ذخیره‌شده سفارش خوانده می‌شود.</p></div>',
            '  <div class="payments-detail-grid">',
            detailRow("پرداخت‌کننده", order.payerName || "—"),
            detailRow("شماره موبایل", order.payerPhone || "—"),
            detailRow("شماره دانشجویی", order.payerStudentNumber || "—"),
            detailRow("وضعیت", statusLabel(order.status)),
            detailRow("مسیر پرداخت", order.sourceLabel || order.source || "—"),
            detailRow("روش پرداخت", order.paymentMethodLabel || order.paymentMethod || "—"),
            detailRow("تعداد کل", Number(order.quantity || 1).toLocaleString("fa-IR")),
            detailRow("قیمت واحد", money(order.unitPrice || 0)),
            detailRow("جمع قبل از تخفیف", money(order.subtotal || order.amount || 0)),
            detailRow("کد/مبلغ تخفیف", (order.discountCode || "—") + " / " + money(order.discountAmount || 0)),
            detailRow("مبلغ نهایی", money(order.amount || 0)),
            detailRow("درگاه", order.gateway || "—"),
            detailRow("authority", order.authority || "—"),
            detailRow("ref id", order.refId || "—"),
            detailRow("ثبت سفارش", formatDateTime(order.createdAt, "—")),
            detailRow("تایید نهایی", formatDateTime(order.verifiedAt, "—")),
            extras,
            "  </div>",
            '  <div class="payments-item-card__actions payments-order-admin-actions">',
            order.authority && order.status !== "success" ? '<button class="shell-action-btn shell-action-btn-primary" type="button" data-payment-verify-order="' + escapeHtml(order.id) + '">استعلام دوباره از درگاه</button>' : "",
            order.status === "pending" ? '<button class="shell-action-btn shell-action-btn-danger" type="button" data-payment-status-update="' + escapeHtml(order.id) + '" data-payment-next-status="canceled">لغو سفارش</button>' : "",
            order.status !== "pending" && order.status !== "success" ? '<button class="shell-action-btn" type="button" data-payment-status-update="' + escapeHtml(order.id) + '" data-payment-next-status="pending">بازگردانی به در انتظار</button>' : "",
            '<button class="shell-action-btn shell-action-btn-danger" type="button" data-payment-delete-order="' + escapeHtml(order.id) + '" data-payment-delete-return="orders">حذف سفارش از سوابق</button>',
            "  </div>",
            "</section>",
            lineRows ? '<section class="owner-block owner-block--payments"><div class="owner-block__head"><h4>خطوط سبد</h4><p>تعداد، قیمت واحد، subtotal و مبلغ نهایی هر آیتم.</p></div><div class="buy-order-lines">' + lineRows + "</div></section>" : "",
            '<section class="owner-block owner-block--payments"><div class="payments-code-block"><h5>اسنپ‌شات پاسخ درگاه</h5><pre>' + escapeHtml(prettyJson(snapshot || {})) + "</pre></div></section>"
        ].join("");
    }

    function detailRow(label, value) {
        return '<div class="payments-detail-row"><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(value) + "</strong></div>";
    }

    async function updateOrderStatus(orderId, status) {
        if (!orderId || !status) {
            return;
        }
        setFeedback(feedbackNode, "در حال به‌روزرسانی وضعیت سفارش...", "", true);
        var response = await request("ownerUpdateOrderStatus", { id: orderId, status: status }, "POST");
        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) return;
        if (!response || !response.success) {
            setFeedback(feedbackNode, (response && response.error) || "به‌روزرسانی وضعیت سفارش انجام نشد.", "error");
            return;
        }
        setFeedback(feedbackNode, response.message || "وضعیت سفارش به‌روزرسانی شد.", "success");
        await loadOrderDetail(orderId);
    }

    async function verifyOrder(orderId) {
        if (!orderId) {
            return;
        }
        setFeedback(feedbackNode, "در حال استعلام وضعیت پرداخت از درگاه...", "", true);
        var response = await request("ownerVerifyOrder", { id: orderId }, "POST");
        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) return;
        if (!response || !response.success) {
            setFeedback(feedbackNode, (response && response.error) || "استعلام درگاه انجام نشد.", "error");
            return;
        }
        await loadDashboard(true);
        if (page === "order-detail") {
            await loadOrderDetail(orderId);
        } else {
            await loadOrders(true);
        }
        setFeedback(feedbackNode, response.message || "استعلام درگاه انجام شد.", "success");
    }

    async function deleteOrder(orderId, returnToOrders) {
        var cleanId = Number(orderId || 0);
        if (!cleanId) {
            return;
        }
        if (!window.confirm("این سفارش از سوابق خرید و اعلان‌های مرتبط حذف می‌شود. ادامه می‌دهید؟")) {
            return;
        }
        setFeedback(feedbackNode, "در حال حذف سفارش از سوابق...", "", true);
        var response = await request("ownerDeleteOrder", { id: cleanId }, "POST");
        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) return;
        if (!response || !response.success) {
            setFeedback(feedbackNode, (response && response.error) || "حذف سفارش انجام نشد.", "error");
            return;
        }
        await loadDashboard(true);
        if (returnToOrders) {
            window.location.href = "/buy/manage/orders/";
            return;
        }
        await loadOrders(true);
        setFeedback(feedbackNode, response.message || "سفارش از سوابق حذف شد.", "success");
    }

    async function resetFilteredOrders() {
        state.orderFilters = currentFilters();
        var count = Number(state.ordersSummary && state.ordersSummary.totalOrders || state.orders.length || 0);
        if (!count) {
            setFeedback(feedbackNode, "در فیلتر فعلی سفارشی برای حذف وجود ندارد.", "error");
            return;
        }
        var typed = window.prompt("برای حذف " + count.toLocaleString("fa-IR") + " سفارش مطابق فیلتر فعلی، عبارت «حذف سوابق» را وارد کنید.");
        if (String(typed || "").trim() !== "حذف سوابق") {
            setFeedback(feedbackNode, "ریست سوابق لغو شد.", "");
            return;
        }
        setFeedback(feedbackNode, "در حال حذف سوابق فیلترشده...", "", true);
        var response = await request("ownerResetOrderHistory", Object.assign({}, state.orderFilters, {
            confirmation: "DELETE_FILTERED_HISTORY"
        }), "POST");
        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) return;
        if (!response || !response.success) {
            setFeedback(feedbackNode, (response && response.error) || "ریست سوابق انجام نشد.", "error");
            return;
        }
        await loadDashboard(true);
        await loadOrders(true);
        setFeedback(feedbackNode, response.message || "سوابق فیلترشده حذف شد.", "success");
    }

    function csvCell(value) {
        var textValue = String(value == null ? "" : value);
        return '"' + textValue.replace(/"/g, '""') + '"';
    }

    function exportOrdersCsv() {
        var rows = buyerRecords();
        if (!rows.length) {
            setFeedback(feedbackNode, "برای خروجی گرفتن، ابتدا فیلتر را طوری تنظیم کنید که سفارشی نمایش داده شود.", "error");
            return;
        }
        var header = [
            "order_id",
            "item_title",
            "item_slug",
            "status",
            "payer_name",
            "payer_phone",
            "student_number",
            "quantity",
            "amount",
            "ref_id",
            "authority",
            "created_at",
            "verified_at"
        ];
        var csvRows = [header.map(csvCell).join(",")].concat(rows.map(function (row) {
            return [
                row.orderId,
                row.itemTitle,
                row.itemSlug,
                statusLabel(row.status),
                row.payerName,
                row.payerPhone,
                row.payerStudentNumber,
                row.quantity,
                row.amount,
                row.refId,
                row.authority,
                row.createdAt,
                row.verifiedAt
            ].map(csvCell).join(",");
        }));
        var blob = new Blob(["\ufeff" + csvRows.join("\r\n")], { type: "text/csv;charset=utf-8" });
        var link = document.createElement("a");
        var stamp = new Date().toISOString().slice(0, 10);
        link.href = URL.createObjectURL(blob);
        link.download = "dent1402-buy-orders-" + stamp + ".csv";
        document.body.appendChild(link);
        link.click();
        window.setTimeout(function () {
            URL.revokeObjectURL(link.href);
            link.remove();
        }, 500);
        setFeedback(feedbackNode, "خروجی CSV سوابق فیلتر فعلی آماده شد.", "success");
    }

    async function markNotificationRead(id) {
        var payload = String(id || "") === "all" ? { all: "1" } : { id: id };
        var response = await request("ownerMarkNotificationRead", payload, "POST");
        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) return;
        if (!response || !response.success) {
            setFeedback(feedbackNode, (response && response.error) || "به‌روزرسانی اعلان انجام نشد.", "error");
            return;
        }
        await loadDashboard(true);
        renderPage();
        setFeedback(feedbackNode, String(id || "") === "all" ? "همه اعلان‌های پرداخت خوانده شد." : "اعلان خوانده شد.", "success");
    }

    async function reconcileOrders() {
        setFeedback(feedbackNode, "در حال استعلام گروهی پرداخت‌های در انتظار از درگاه...", "", true);
        var response = await request("ownerReconcileOrders", { limit: "50" }, "POST");
        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) return;
        if (!response || !response.success) {
            setFeedback(feedbackNode, (response && response.error) || "استعلام گروهی انجام نشد.", "error");
            return;
        }
        var verified = Number(response.reconciliation && response.reconciliation.verified || 0);
        await loadDashboard(true);
        if (page === "orders") {
            renderOrdersPage();
            await loadOrders(true);
        } else {
            renderPage();
        }
        setFeedback(
            feedbackNode,
            response.message || (verified > 0 ? (verified.toLocaleString("fa-IR") + " پرداخت تایید شد.") : "استعلام گروهی انجام شد."),
            verified > 0 ? "success" : ""
        );
    }

    function copyText(value, successText) {
        var clean = String(value || "").trim();
        if (!clean) {
            setFeedback(feedbackNode, "متنی برای کپی وجود ندارد.", "error");
            return;
        }
        if (navigator.clipboard && typeof navigator.clipboard.writeText === "function") {
            navigator.clipboard.writeText(clean).then(function () {
                setFeedback(feedbackNode, successText || "کپی شد.", "success");
            }).catch(function () {
                setFeedback(feedbackNode, "کپی خودکار انجام نشد.", "error");
            });
            return;
        }
        setFeedback(feedbackNode, "مرورگر کپی خودکار را پشتیبانی نمی‌کند.", "error");
    }

    function renderPage() {
        syncNav();
        if (page === "overview") {
            renderOverviewPage();
        } else if (page === "items") {
            renderItemsPage();
        } else if (page === "item-form") {
            renderItemFormPage();
        } else if (page === "collections") {
            renderCollectionsPage();
        } else if (page === "gateways") {
            renderGatewaysPage();
        } else if (page === "gateway-form") {
            renderGatewayFormPage();
        } else if (page === "orders") {
            renderOrdersPage();
        }
    }

    async function renderInitialPage() {
        renderLoading("در حال آماده‌سازی مدیریت خرید...");
        await loadDashboard(true);
        if (page === "order-detail") {
            await loadOrderDetail(readParam("id"));
            return;
        }
        renderPage();
        if (page === "orders") {
            await loadOrders(true);
        }
    }

    function renderGuard(detail) {
        if (!guard || !app) {
            return;
        }
        var loginUrl = window.Dent1402Auth.loginUrl ? window.Dent1402Auth.loginUrl(window.location.pathname + window.location.search) : "/account/";
        if (!detail || !detail.loggedIn) {
            app.hidden = true;
            guard.hidden = false;
            guard.innerHTML = window.Dent1402Auth.renderLoginRequiredGuard({
                loginHref: loginUrl,
                fallbackHref: "/buy/",
                primaryClass: "buy-primary-btn",
                secondaryClass: "buy-secondary-btn"
            });
            window.Dent1402Auth.enhanceLoginGuards(guard);
            return;
        }
        if (!detail.user || !detail.user.isOwner) {
            app.hidden = true;
            guard.hidden = false;
            guard.innerHTML = [
                '<div><span class="buy-kicker">دسترسی محدود</span><h2>این بخش مخصوص مالک سایت است</h2><p>برای خرید عمومی از کاتالوگ و سبد خرید استفاده کنید.</p></div>',
                '<a class="buy-secondary-btn" href="/buy/">بازگشت به خرید</a>'
            ].join("");
            return;
        }
        guard.hidden = true;
        guard.innerHTML = "";
        app.hidden = false;
    }

    function handleAuthState(detail) {
        renderGuard(detail);
        if (!detail || !detail.loggedIn || !detail.user || !detail.user.isOwner) {
            state.currentUser = null;
            return;
        }
        state.currentUser = detail.user;
        if (!state.dashboardLoaded && !state.loadingDashboard) {
            renderInitialPage();
        }
    }

    app.addEventListener("click", function (event) {
        var refresh = event.target.closest("[data-payment-refresh]");
        if (refresh) {
            loadDashboard(false).then(renderPage);
            return;
        }
        var refreshOrders = event.target.closest("[data-payment-refresh-orders]");
        if (refreshOrders) {
            loadOrders(false);
            return;
        }
        var readNote = event.target.closest("[data-payment-read-note]");
        if (readNote) {
            markNotificationRead(readNote.getAttribute("data-payment-read-note"));
            return;
        }
        var readAllNotes = event.target.closest("[data-payment-read-all-notes]");
        if (readAllNotes) {
            markNotificationRead("all");
            return;
        }
        var reconcileButton = event.target.closest("[data-payment-reconcile-orders]");
        if (reconcileButton) {
            reconcileOrders();
            return;
        }
        var orderFolder = event.target.closest("[data-payment-order-folder]");
        if (orderFolder) {
            state.orderFilters.status = String(orderFolder.getAttribute("data-payment-order-folder") || "all");
            renderOrdersPage();
            loadOrders(false);
            return;
        }
        var orderView = event.target.closest("[data-payment-order-view]");
        if (orderView) {
            state.orderView = String(orderView.getAttribute("data-payment-order-view") || "orders") === "buyers" ? "buyers" : "orders";
            renderOrdersPage();
            renderOrdersList();
            return;
        }
        var exportOrders = event.target.closest("[data-payment-export-orders]");
        if (exportOrders) {
            exportOrdersCsv();
            return;
        }
        var resetOrders = event.target.closest("[data-payment-reset-orders]");
        if (resetOrders) {
            resetFilteredOrders();
            return;
        }
        var deleteOrderButton = event.target.closest("[data-payment-delete-order]");
        if (deleteOrderButton) {
            deleteOrder(
                deleteOrderButton.getAttribute("data-payment-delete-order"),
                String(deleteOrderButton.getAttribute("data-payment-delete-return") || "") === "orders"
            );
            return;
        }
        var verifyOrderButton = event.target.closest("[data-payment-verify-order]");
        if (verifyOrderButton) {
            verifyOrder(verifyOrderButton.getAttribute("data-payment-verify-order"));
            return;
        }
        var copyButton = event.target.closest("[data-payment-copy-link]");
        if (copyButton) {
            copyText(copyButton.getAttribute("data-payment-copy-link"), "لینک عمومی کپی شد.");
            return;
        }
        var toggleButton = event.target.closest("[data-payment-toggle-item]");
        if (toggleButton) {
            toggleItem(toggleButton.getAttribute("data-payment-toggle-item"), String(toggleButton.getAttribute("data-payment-enabled")) === "1");
            return;
        }
        var deleteButton = event.target.closest("[data-payment-delete-item]");
        if (deleteButton) {
            deleteItem(deleteButton.getAttribute("data-payment-delete-item"));
            return;
        }
        var resetCollection = event.target.closest("[data-payment-reset-collection]");
        if (resetCollection) {
            resetCollectionForm();
            return;
        }
        var editCollection = event.target.closest("[data-payment-edit-collection]");
        if (editCollection) {
            fillCollectionForm(editCollection.getAttribute("data-payment-edit-collection"));
            return;
        }
        var deleteCollectionButton = event.target.closest("[data-payment-delete-collection]");
        if (deleteCollectionButton) {
            deleteCollection(deleteCollectionButton.getAttribute("data-payment-delete-collection"));
            return;
        }
        var itemSection = event.target.closest("[data-payment-item-section]");
        if (itemSection) {
            activeItemSection = String(itemSection.dataset.paymentItemSection || "basic");
            syncItemPanels();
            return;
        }
        var addDiscount = event.target.closest("[data-payment-add-discount]");
        if (addDiscount) {
            var codes = discountCodesFromField();
            codes.push({ code: "TUMS" + String(codes.length + 1), type: "percent", amount: 10, label: "تخفیف", isEnabled: true, expiresAt: "" });
            writeDiscountCodes(codes);
            return;
        }
        var removeDiscount = event.target.closest("[data-payment-remove-discount]");
        if (removeDiscount) {
            var discountRows = discountCodesFromField();
            var discountIndex = Number(removeDiscount.getAttribute("data-payment-remove-discount"));
            if (Number.isFinite(discountIndex) && discountIndex >= 0) {
                discountRows.splice(discountIndex, 1);
                writeDiscountCodes(discountRows);
            }
            return;
        }
        var gatewaySection = event.target.closest("[data-payment-gateway-section]");
        if (gatewaySection) {
            activeGatewaySection = String(gatewaySection.dataset.paymentGatewaySection || "main");
            syncGatewayPanels();
            return;
        }
        var galleryRemove = event.target.closest("[data-payment-remove-gallery]");
        if (galleryRemove) {
            var values = galleryValues();
            var index = Number(galleryRemove.getAttribute("data-payment-remove-gallery"));
            if (Number.isFinite(index) && index >= 0) {
                values.splice(index, 1);
                writeGalleryValues(values);
                updateItemPreview();
            }
            return;
        }
        var sample = event.target.closest("[data-payment-fill-sample], #payments-fill-shield-sample");
        if (sample) {
            fillShieldSample();
            return;
        }
        var deleteGatewayButton = event.target.closest("[data-payment-delete-gateway]");
        if (deleteGatewayButton) {
            deleteGateway(deleteGatewayButton.getAttribute("data-payment-delete-gateway"));
            return;
        }
        var statusButton = event.target.closest("[data-payment-status-update]");
        if (statusButton) {
            updateOrderStatus(statusButton.getAttribute("data-payment-status-update"), statusButton.getAttribute("data-payment-next-status"));
        }
    });

    root.addEventListener("input", function (event) {
        if (event.target && event.target.id === "payments-items-search") {
            state.itemQuery = String(event.target.value || "");
            renderItemsList();
            return;
        }
        if (event.target && event.target.closest("#payments-discount-editor")) {
            syncDiscountEditorToField();
            return;
        }
        if (event.target && event.target.closest("#payments-item-form")) {
            updateItemPreview();
        }
    });

    root.addEventListener("change", function (event) {
        if (event.target && event.target.id === "payments-items-status-filter") {
            state.itemStatus = String(event.target.value || "all");
            renderItemsList();
            return;
        }
        if (event.target && event.target.closest("#payments-item-form")) {
            if (event.target.closest("#payments-discount-editor")) {
                syncDiscountEditorToField();
            }
            updateItemPreview();
            return;
        }
        if (event.target && event.target.id === "payments-gateway-provider") {
            var provider = readField("payments-gateway-provider") || "zibal";
            if (!$("payments-gateway-label").value.trim()) $("payments-gateway-label").value = gatewayPublicLabel(provider);
            if (!$("payments-gateway-provider-label").value.trim()) $("payments-gateway-provider-label").value = gatewayProviderLabel(provider);
        }
    });

    root.addEventListener("submit", function (event) {
        if (event.target && event.target.id === "payments-item-form") {
            saveItem(event);
            return;
        }
        if (event.target && event.target.id === "payments-gateway-form") {
            saveGateway(event);
            return;
        }
        if (event.target && event.target.id === "payments-collection-form") {
            saveCollection(event);
            return;
        }
        if (event.target && event.target.id === "payments-orders-filter-form") {
            event.preventDefault();
            state.orderFilters = currentFilters();
            loadOrders(false);
        }
    });

    app.addEventListener("click", function (event) {
        if (event.target && event.target.id === "payments-filter-reset") {
            state.orderFilters = { itemId: "0", source: "", method: "", formId: "", status: "all", dateFrom: "", dateTo: "", query: "" };
            renderOrdersPage();
            loadOrders(false);
        }
    });

    window.Dent1402Auth.onChange(handleAuthState);
})();
