(function () {
    "use strict";

    var root = document.getElementById("exam-payment-root");
    if (!root) {
        return;
    }

    var params = new URLSearchParams(window.location.search);
    var courseSlug = String(params.get("course") || "").trim();
    var paymentOrderToken = String(params.get("paymentOrderToken") || "").trim();
    var state = {
        loading: false,
        paying: false,
        course: null,
        result: null,
        viewer: null,
        auth: null,
        feedback: "",
        feedbackKind: "",
        discountCode: "",
        quote: null,
        quoteLoading: false
    };

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

    function parseJson(response) {
        return response.text().then(function (text) {
            var payload = null;
            if (text) {
                try {
                    payload = JSON.parse(text);
                } catch (_error) {
                    payload = null;
                }
            }
            if (!payload || typeof payload !== "object") {
                payload = { success: false, error: "پاسخ نامعتبر از سرور دریافت شد." };
            }
            payload.httpStatus = response.status;
            return payload;
        });
    }

    function withCohort(payload) {
        var next = Object.assign({}, payload || {});
        var cohort = String(params.get("cohort") || "").trim();
        if (cohort) {
            next.cohort = cohort;
        }
        return next;
    }

    function networkErrorResponse() {
        return {
            success: false,
            error: "ارتباط با سرور برقرار نشد. اتصال اینترنت خود را بررسی کنید.",
            httpStatus: 0
        };
    }

    function examsGet(action, payload) {
        var query = new URLSearchParams(withCohort(Object.assign({ action: action }, payload || {})));
        query.set("_t", String(Date.now()));
        return fetch("/api/exams_api.php?" + query.toString(), {
            method: "GET",
            cache: "no-store",
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        }).then(parseJson).catch(networkErrorResponse);
    }

    function paymentsGet(action, payload) {
        var query = new URLSearchParams(Object.assign({ action: action }, payload || {}));
        return fetch("/api/payments_api.php?" + query.toString(), {
            method: "GET",
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        }).then(parseJson).catch(networkErrorResponse);
    }

    function paymentsPost(action, payload) {
        return fetch("/api/payments_api.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                Accept: "application/json"
            },
            body: new URLSearchParams(Object.assign({ action: action }, payload || {}))
        }).then(parseJson).catch(networkErrorResponse);
    }

    function formatDateTime(value, fallback) {
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

    function loginHref() {
        if (window.Dent1402Auth && typeof window.Dent1402Auth.loginUrl === "function") {
            return window.Dent1402Auth.loginUrl(window.location.pathname + window.location.search);
        }
        return "/account/";
    }

    function returnPath() {
        var url = new URL(window.location.pathname, window.location.origin);
        url.searchParams.set("course", courseSlug);
        var cohort = String(params.get("cohort") || "").trim();
        if (cohort) {
            url.searchParams.set("cohort", cohort);
        }
        return url.pathname + url.search;
    }

    function formatMoney(value) {
        return (Math.max(0, Number(value) || 0)).toLocaleString("fa-IR") + " ریال";
    }

    function defaultQuote(course) {
        var amount = Math.max(0, Number(course && course.amount || 0));
        return {
            subtotal: amount,
            discountCode: "",
            discountLabel: "",
            discountAmount: 0,
            discountApplied: false,
            amount: amount
        };
    }

    function currentQuote(course) {
        return state.quote && typeof state.quote === "object" ? state.quote : defaultQuote(course);
    }

    function readDiscountCodeInput() {
        var input = document.getElementById("exam-payment-discount-code");
        return String(input && input.value || state.discountCode || "").trim();
    }

    function normalizeDiscountCode(value) {
        return String(value || "").replace(/\s+/g, "").toUpperCase();
    }

    function resultBoxHtml() {
        if (!state.result) {
            return "";
        }
        var success = String(state.result.status || "") === "success";
        return [
            '<section class="exam-payment-result ' + (success ? "is-success" : "is-error") + '">',
            '  <h3>' + escapeHtml(success ? "نتیجه پرداخت" : "وضعیت پرداخت") + "</h3>",
            '  <p>' + escapeHtml(state.result.message || "") + "</p>",
            '  <p>وضعیت: ' + escapeHtml(state.result.statusLabel || "—") + "</p>",
            ((state.result.discountCode || "") || Number(state.result.discountAmount || 0) > 0)
                ? '  <p>کد/مبلغ تخفیف: <span dir="ltr">' + escapeHtml(state.result.discountCode || "—") + "</span> / " + escapeHtml(formatMoney(state.result.discountAmount || 0)) + "</p>"
                : "",
            '  <p>زمان ثبت: ' + escapeHtml(formatDateTime(state.result.createdAt, "—")) + "</p>",
            "  " + (state.result.refId ? '<p>کد مرجع: <span dir="ltr">' + escapeHtml(state.result.refId) + "</span></p>" : ""),
            "</section>"
        ].join("");
    }

    function loginGuardHtml() {
        if (!window.Dent1402Auth || typeof window.Dent1402Auth.renderLoginRequiredGuard !== "function") {
            return '<a class="exam-payment-link" href="' + escapeHtml(loginHref()) + '">ورود به حساب</a>';
        }

        return window.Dent1402Auth.renderLoginRequiredGuard({
            loginHref: loginHref(),
            fallbackHref: "/exams/",
            primaryClass: "exam-payment-btn",
            secondaryClass: "exam-payment-link"
        });
    }

    function discountEditorHtml(course, canPay) {
        var quote = currentQuote(course);
        var hasCodes = !!(course && course.discounts && course.discounts.hasCodes);
        if (!canPay) {
            return "";
        }
        return [
            '<section class="exam-payment-discount">',
            '  <label class="exam-payment-field">',
            '    <span>کد تخفیف</span>',
            '    <span class="exam-payment-field__row">',
            '      <input id="exam-payment-discount-code" class="exam-payment-input" type="text" maxlength="40" autocomplete="off" dir="ltr" data-latin-digits="true" value="' + escapeHtml(state.discountCode || "") + '" placeholder="' + escapeHtml(hasCodes ? "مثلاً EXAM10" : "اختیاری") + '">',
            '      <button id="exam-payment-discount-apply" class="exam-payment-secondary-btn" type="button"' + (state.quoteLoading || state.paying ? " disabled" : "") + '>' + (state.quoteLoading ? "در حال بررسی..." : "اعمال") + "</button>",
            "    </span>",
            "  </label>",
            '  <div class="exam-payment-quote">',
            '    <div><span>مبلغ پایه</span><strong>' + escapeHtml(formatMoney(quote.subtotal || course.amount || 0)) + "</strong></div>",
            '    <div><span>تخفیف</span><strong>' + escapeHtml(formatMoney(quote.discountAmount || 0)) + "</strong></div>",
            '    <div class="is-total"><span>مبلغ نهایی</span><strong>' + escapeHtml(formatMoney(quote.amount || 0)) + "</strong></div>",
            "  </div>",
            '  <p class="exam-payment-hint">' + escapeHtml(hasCodes
                ? "اگر کد مخصوص این درس داری، قبل از پرداخت اعمالش کن. محدودیت مصرف و شماره دانشجویی روی سرور بررسی می‌شود."
                : "اگر از مالک این درس کد تخفیف گرفته‌ای، همین‌جا واردش کن و مبلغ نهایی را دوباره بررسی کن.") + "</p>",
            "</section>"
        ].join("");
    }

    function renderPayment() {
        var course = state.course;
        if (!course) {
            root.innerHTML = '<div class="exam-payment-panel">اطلاعات این درس در دسترس نیست.</div>';
            return;
        }

        var access = course.access || {};
        var features = Array.isArray(course.paymentHighlights) ? course.paymentHighlights : [];
        var canPay = course.paymentMode === "paid" && !access.hasAccess && access.canPurchase && !access.requiresLogin;
        var quote = currentQuote(course);
        var headlineChip = course.paymentMode === "paid"
            ? '<span class="exam-payment-chip">دسترسی یک‌باره برای کل درس</span>'
            : '<span class="exam-payment-chip">این درس رایگان است</span>';
        var actionHtml = "";

        if (course.paymentMode !== "paid") {
            actionHtml = '<div class="exam-payment-action"><a class="exam-payment-btn" href="' + escapeHtml(course.path || "/exams/") + '">ورود به صفحه درس</a></div>';
        } else if (access.hasAccess) {
            actionHtml = '<div class="exam-payment-action"><a class="exam-payment-btn" href="' + escapeHtml(course.path || "/exams/") + '">ورود به آزمون‌های درس</a></div>';
        } else if (access.requiresLogin) {
            actionHtml = '<div class="exam-payment-login">' + loginGuardHtml() + "</div>";
        } else if (access.canPurchase) {
            actionHtml = '<div class="exam-payment-action"><button id="exam-payment-submit" class="exam-payment-btn" type="button"' + (state.paying || state.quoteLoading ? " disabled" : "") + '>' + (state.paying ? "در حال انتقال به درگاه..." : "پرداخت و فعال‌سازی دسترسی") + "</button></div>";
        } else {
            actionHtml = '<div class="exam-payment-action"><a class="exam-payment-btn" href="' + escapeHtml(course.path || "/exams/") + '">بازگشت به درس</a></div>';
        }

        root.innerHTML = [
            '<section class="exam-payment-shell">',
            '  <article class="exam-payment-card">',
            '    <div class="exam-payment-card__inner">',
            '      <div class="exam-payment-head">',
            '        <span class="exam-payment-kicker">فعال‌سازی آزمون‌های درس</span>',
            '        <h2 class="exam-payment-title">' + escapeHtml(course.paymentTitle || course.title || "") + "</h2>",
            '        <p class="exam-payment-copy">' + escapeHtml(course.paymentDescription || "") + "</p>",
            '        <div class="exam-payment-price"><strong>' + escapeHtml(formatMoney(quote.amount || 0)) + '</strong><span>' + escapeHtml(Number(quote.discountAmount || 0) > 0 ? "مبلغ نهایی این حساب بعد از تخفیف" : "یک بار برای تمام آزمون‌های این درس") + '</span>' + (Number(quote.discountAmount || 0) > 0 ? '<em>مبلغ پایه: ' + escapeHtml(formatMoney(quote.subtotal || course.amount || 0)) + '</em>' : '') + "</div>",
            '        <div class="exam-payment-headline">' + headlineChip + (state.viewer && state.viewer.name ? '<span class="exam-payment-user">' + escapeHtml(state.viewer.name) + "</span>" : "") + "</div>",
            "      </div>",
                     discountEditorHtml(course, canPay),
                     actionHtml,
            '      <div class="exam-payment-feedback' + (state.feedbackKind ? " is-" + escapeHtml(state.feedbackKind) : "") + '">' + escapeHtml(state.feedback || "") + "</div>",
                     resultBoxHtml(),
            '      <div class="exam-payment-subactions"><a class="exam-payment-link" href="' + escapeHtml(course.path || "/exams/") + '">بازگشت به صفحه درس</a></div>',
            "    </div>",
            "  </article>",
            '  <aside class="exam-payment-side">',
            '    <section class="exam-payment-panel">',
            '      <h3 class="exam-payment-panel__title">چه چیزی فعال می‌شود؟</h3>',
            '      <p class="exam-payment-panel__copy">بعد از تایید پرداخت، دسترسی همین حساب به همه آزمون‌های این درس باز می‌شود و لازم نیست برای هر جلسه جداگانه پرداخت کنید.</p>',
            '      <div class="exam-payment-features">' + features.map(function (item, index) {
                return [
                    '<article class="exam-payment-feature">',
                    '  <span class="exam-payment-feature__index">' + escapeHtml((index + 1).toLocaleString("fa-IR")) + "</span>",
                    '  <p>' + escapeHtml(item || "") + "</p>",
                    "</article>"
                ].join("");
            }).join("") + "</div>",
            "    </section>",
            '    <section class="exam-payment-panel">',
            '      <h3 class="exam-payment-panel__title">وضعیت فعلی شما</h3>',
            '      <p class="exam-payment-panel__copy">' + escapeHtml(access.hasAccess ? "دسترسی این درس برای حساب شما فعال است." : (access.requiresLogin ? "برای پرداخت باید وارد حساب خود شوید." : (access.canPurchase ? "هنوز دسترسی این درس فعال نشده است." : "در حال حاضر امکان فعال‌سازی این درس برای این حساب وجود ندارد."))) + "</p>",
            '      <div class="exam-payment-headline"><span class="exam-payment-chip">' + escapeHtml(access.unlockLabel || "—") + "</span></div>",
            "    </section>",
            "  </aside>",
            "</section>"
        ].join("");
    }

    function setError(message) {
        root.innerHTML = '<section class="exam-payment-panel"><p class="exam-payment-panel__copy">' + escapeHtml(message || "بارگذاری انجام نشد.") + "</p></section>";
    }

    function loadCourse() {
        if (!courseSlug || state.loading) {
            return Promise.resolve();
        }
        state.loading = true;
        root.innerHTML = '<section class="exam-payment-panel"><p class="exam-payment-panel__copy">در حال بارگذاری اطلاعات پرداخت...</p></section>';
        return examsGet("course", { course: courseSlug }).then(function (payload) {
            if (!payload || !payload.success || !payload.course) {
                throw new Error((payload && payload.error) || "بارگذاری اطلاعات پرداخت انجام نشد.");
            }
            state.course = payload.course;
            state.quote = defaultQuote(payload.course);
            state.discountCode = "";
            state.viewer = payload.viewer || null;
            renderPayment();
        }).catch(function (error) {
            setError(error && error.message ? error.message : "بارگذاری انجام نشد.");
        }).finally(function () {
            state.loading = false;
        });
    }

    function loadOrderResult() {
        if (!paymentOrderToken) {
            return Promise.resolve();
        }
        return paymentsGet("publicOrderResult", { orderToken: paymentOrderToken }).then(function (payload) {
            if (payload && payload.success && payload.order) {
                state.result = payload.order;
                state.feedback = "";
                state.feedbackKind = "";
            }
            if (state.result && String(state.result.status || "") === "success") {
                return loadCourse();
            }
            renderPayment();
            return null;
        }).catch(function () {
            renderPayment();
        });
    }

    function applyDiscountCode() {
        if (!state.course || state.quoteLoading) {
            return;
        }

        state.discountCode = readDiscountCodeInput();
        if (!state.discountCode) {
            state.quote = defaultQuote(state.course);
            state.feedback = "";
            state.feedbackKind = "";
            renderPayment();
            return;
        }

        state.quoteLoading = true;
        state.feedback = "";
        state.feedbackKind = "";
        renderPayment();

        paymentsPost("quoteCollection", {
            token: state.course.collectionToken || "",
            discountCode: state.discountCode
        }).then(function (payload) {
            if (!payload || !payload.success || !payload.quote) {
                throw new Error((payload && payload.error) || "محاسبه مبلغ با کد تخفیف انجام نشد.");
            }
            state.quote = payload.quote;
            state.feedback = Number(payload.quote.discountAmount || 0) > 0 ? "کد تخفیف اعمال شد." : "";
            state.feedbackKind = Number(payload.quote.discountAmount || 0) > 0 ? "success" : "";
        }).catch(function (error) {
            state.quote = defaultQuote(state.course);
            state.feedback = error && error.message ? error.message : "بررسی کد تخفیف انجام نشد.";
            state.feedbackKind = "error";
        }).finally(function () {
            state.quoteLoading = false;
            renderPayment();
        });
    }

    function submitPayment() {
        if (!state.course || state.paying || state.quoteLoading) {
            return;
        }
        state.discountCode = readDiscountCodeInput();
        if (normalizeDiscountCode(state.discountCode) && normalizeDiscountCode(state.discountCode) !== normalizeDiscountCode(state.quote && state.quote.discountCode || "")) {
            state.feedback = "برای این کد، اول مبلغ را با دکمه اعمال دوباره بررسی کن.";
            state.feedbackKind = "error";
            renderPayment();
            return;
        }
        state.paying = true;
        state.feedback = "در حال انتقال به درگاه پرداخت...";
        state.feedbackKind = "";
        renderPayment();

        paymentsPost("createCollectionOrder", {
            token: state.course.collectionToken || "",
            discountCode: state.discountCode,
            returnPath: returnPath()
        }).then(function (payload) {
            if (!payload || !payload.success || !payload.redirectUrl) {
                throw new Error((payload && payload.error) || "ایجاد پرداخت انجام نشد.");
            }
            window.location.href = payload.redirectUrl;
        }).catch(function (error) {
            state.feedback = error && error.message ? error.message : "ایجاد پرداخت انجام نشد.";
            state.feedbackKind = "error";
            state.paying = false;
            renderPayment();
        });
    }

    root.addEventListener("click", function (event) {
        if (event.target && event.target.id === "exam-payment-discount-apply") {
            applyDiscountCode();
            return;
        }
        if (event.target && event.target.id === "exam-payment-submit") {
            submitPayment();
        }
    });

    root.addEventListener("input", function (event) {
        if (!event.target || event.target.id !== "exam-payment-discount-code") {
            return;
        }
        state.discountCode = String(event.target.value || "");
        if (!state.discountCode.trim()) {
            state.quote = defaultQuote(state.course);
            state.feedback = "";
            state.feedbackKind = "";
        }
    });

    root.addEventListener("keydown", function (event) {
        if (!event.target || event.target.id !== "exam-payment-discount-code" || event.key !== "Enter") {
            return;
        }
        event.preventDefault();
        applyDiscountCode();
    });

    function boot(detail) {
        state.auth = detail || null;
        loadCourse().then(loadOrderResult);
    }

    if (window.Dent1402Auth && typeof window.Dent1402Auth.onChange === "function") {
        window.Dent1402Auth.onChange(function (detail) {
            if (!detail || detail.status === "session-restoring" || detail.status === "logging-out") {
                root.innerHTML = '<section class="exam-payment-panel"><p class="exam-payment-panel__copy">در حال بررسی وضعیت ورود...</p></section>';
                return;
            }
            boot(detail);
        });
    } else {
        boot(null);
    }
})();
