(function () {
    "use strict";

    if (!window.Dent1402Auth) {
        return;
    }

    var root = document.getElementById("payment-collection-root");
    var params = new URLSearchParams(window.location.search);
    var token = String(params.get("token") || "").trim();
    var paymentOrderToken = String(params.get("paymentOrderToken") || "").trim();
    var state = {
        viewer: null,
        loggedIn: false,
        collection: null,
        result: null,
        loading: false
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

    function parseApiResponse(response) {
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

    function networkErrorResponse() {
        return {
            success: false,
            error: "ارتباط با سرور برقرار نشد. اتصال اینترنت خود را بررسی کنید.",
            httpStatus: 0
        };
    }

    function apiGet(action, payload) {
        var query = new URLSearchParams(Object.assign({ action: action }, payload || {}));
        return fetch("/api/payments_api.php?" + query.toString(), {
            method: "GET",
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        }).then(parseApiResponse).catch(networkErrorResponse);
    }

    function apiPost(action, payload) {
        return fetch("/api/payments_api.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                Accept: "application/json"
            },
            body: new URLSearchParams(Object.assign({ action: action }, payload || {}))
        }).then(parseApiResponse).catch(networkErrorResponse);
    }

    function money(value) {
        return (Math.max(0, Number(value) || 0)).toLocaleString("fa-IR") + " ریال";
    }

    function collectionReturnUrl() {
        var origin = window.location.origin || "";
        return origin + "/payments/pay/?token=" + encodeURIComponent(token);
    }

    function copyText(value) {
        var clean = String(value || "").trim();
        if (!clean) return Promise.reject(new Error("empty"));
        if (navigator.clipboard && typeof navigator.clipboard.writeText === "function") {
            return navigator.clipboard.writeText(clean);
        }
        return new Promise(function (resolve, reject) {
            try {
                var helper = document.createElement("textarea");
                helper.value = clean;
                helper.setAttribute("readonly", "");
                helper.style.position = "fixed";
                helper.style.opacity = "0";
                document.body.appendChild(helper);
                helper.select();
                document.execCommand("copy");
                helper.remove();
                resolve();
            } catch (error) {
                reject(error);
            }
        });
    }

    function normalizeDigits(value) {
        return String(value || "").replace(/[\u06F0-\u06F9\u0660-\u0669]/g, function (char) {
            var code = char.charCodeAt(0);
            if (code >= 0x06F0 && code <= 0x06F9) return String(code - 0x06F0);
            return String(code - 0x0660);
        });
    }

    function userDisplayName() {
        var user = state.viewer || {};
        return String(user.name || "").trim();
    }

    function collectionFlag(collection, key, fallback) {
        if (!collection || typeof collection !== "object" || collection[key] == null) {
            return !!fallback;
        }
        return !!collection[key];
    }

    function payerDefaults(collection) {
        var defaults = collection && collection.payerDefaults && typeof collection.payerDefaults === "object"
            ? collection.payerDefaults
            : {};
        return {
            name: String(defaults.name || userDisplayName() || "").trim(),
            phone: normalizeDigits(defaults.phone || "").replace(/\D+/g, ""),
            studentNumber: normalizeDigits(defaults.studentNumber || "").replace(/\D+/g, "")
        };
    }

    function payerFieldHtml(id, label, type, value, attrs) {
        return [
            '<label class="payments-field">',
            '  <span>' + escapeHtml(label) + '</span>',
            '  <input id="' + escapeHtml(id) + '" type="' + escapeHtml(type || "text") + '" value="' + escapeHtml(value || "") + '" ' + (attrs || "") + ' required>',
            '</label>'
        ].join("");
    }

    function collectionPayerFields(collection) {
        var defaults = payerDefaults(collection);
        var rows = [];
        if (collectionFlag(collection, "collectPayerName", true)) {
            rows.push(payerFieldHtml("payment-collection-name", "نام و نام خانوادگی", "text", defaults.name, 'maxlength="120" autocomplete="name"'));
        }
        if (collectionFlag(collection, "collectPayerPhone", true)) {
            rows.push(payerFieldHtml("payment-collection-phone", "شماره موبایل", "tel", defaults.phone, 'inputmode="tel" dir="ltr" data-latin-digits="true" maxlength="14" autocomplete="tel" placeholder="09xxxxxxxxx"'));
        }
        if (collectionFlag(collection, "collectPayerStudentNumber", false)) {
            rows.push(payerFieldHtml("payment-collection-student-number", "شماره دانشجویی", "text", defaults.studentNumber, 'inputmode="numeric" dir="ltr" data-latin-digits="true" maxlength="20" autocomplete="off"'));
        }

        if (!rows.length && state.loggedIn) {
            return '<div class="buy-alert">مشخصات پرداخت از حساب کاربری شما ثبت می‌شود.</div>';
        }
        if (!rows.length) {
            return '<div class="buy-alert">مالک برای این لینک اطلاعات اضافه‌ای درخواست نکرده است.</div>';
        }
        return rows.join("");
    }

    function renderLogin() {
        var loginUrl = window.Dent1402Auth.loginUrl
            ? window.Dent1402Auth.loginUrl(window.location.pathname + window.location.search)
            : "/account/";
        root.innerHTML = '<section class="buy-auth-required">' + window.Dent1402Auth.renderLoginRequiredGuard({
            loginHref: loginUrl,
            fallbackHref: "/buy/",
            primaryClass: "buy-primary-btn",
            secondaryClass: "buy-secondary-btn"
        }) + "</section>";
        window.Dent1402Auth.enhanceLoginGuards(root);
    }

    function gatewayOptions(collection) {
        var gateways = collection && collection.paymentGateways && Array.isArray(collection.paymentGateways.gateways)
            ? collection.paymentGateways.gateways
            : [];
        var enabled = gateways.filter(function (gateway) {
            return !!gateway.isEnabled;
        });
        if (!enabled.length) {
            return '<div class="buy-alert buy-alert--error">درگاه فعالی برای این پرداخت وجود ندارد.</div>';
        }
        return enabled.map(function (gateway, index) {
            var checked = gateway.isDefault || index === 0;
            return [
                '<label class="buy-gateway-option">',
                '  <input type="radio" name="collection-gateway" value="' + escapeHtml(gateway.key || "") + '"' + (checked ? " checked" : "") + ">",
                '  <span><strong>' + escapeHtml(gateway.label || "پرداخت آنلاین") + "</strong><small>" + escapeHtml(gateway.provider || "") + "</small></span>",
                "</label>"
            ].join("");
        }).join("");
    }

    function renderCollection() {
        var collection = state.collection;
        if (!collection) {
            root.innerHTML = '<div class="buy-empty">لینک پرداخت پیدا نشد.</div>';
            return;
        }
        var result = state.result;
        var resultHtml = "";
        var paid = !!collection.paid || !!(result && result.status === "success");
        if (paid) {
            resultHtml = '<div class="buy-alert buy-alert--success">' + escapeHtml(collection.successMessage || "پرداخت شما تایید شده است.") + "</div>";
        } else if (result && result.status && result.status !== "success") {
            resultHtml = '<div class="buy-alert buy-alert--error">' + escapeHtml(result.message || collection.failureMessage || "پرداخت تایید نشد.") + "</div>";
        }
        var disabled = String(collection.status || "") !== "active" || paid;
        var image = String(collection.imageUrl || "").trim();
        root.innerHTML = [
            '<section class="payment-collection-card">',
            image ? '<div class="payment-collection-media"><img src="' + escapeHtml(image) + '" alt="' + escapeHtml(collection.title || "تصویر پرداخت") + '"></div>' : "",
            '  <div class="payment-collection-card__head">',
            '    <span class="buy-kicker">پرداخت هزینه</span>',
            '    <h2>' + escapeHtml(collection.title || "پرداخت هزینه") + "</h2>",
            '    <p>' + escapeHtml(collection.description || "") + "</p>",
            "  </div>",
            '  <div class="payment-collection-amount">' + escapeHtml(money(collection.amount || 0)) + "</div>",
            resultHtml,
            disabled && !collection.paid ? '<div class="buy-alert buy-alert--error">این لینک پرداخت در حال حاضر فعال نیست.</div>' : "",
            '  <form id="payment-collection-form" class="payment-collection-form" novalidate>',
            collectionPayerFields(collection),
            '    <div class="payment-collection-return"><span>نشانی بازگشت پس از پرداخت</span><code dir="ltr">' + escapeHtml(collectionReturnUrl()) + '</code><button class="forms-copy-mini" type="button" data-copy-collection-return="' + escapeHtml(collectionReturnUrl()) + '" aria-label="کپی نشانی بازگشت">⧉</button><small>پس از پرداخت، روی اتمام پرداخت بزنید و به همین صفحه برگردید تا پیام تایید را ببینید.</small></div>',
            '    <div class="payment-collection-gateways">' + gatewayOptions(collection) + "</div>",
            '    <button class="buy-primary-btn" type="submit"' + (disabled ? " disabled" : "") + '>' + (paid ? "پرداخت شده" : "پرداخت") + "</button>",
            '    <div id="payment-collection-feedback" class="account-feedback account-feedback--inline" aria-live="polite"></div>',
            "  </form>",
            "</section>"
        ].join("");
    }

    function setFeedback(message, kind) {
        var node = document.getElementById("payment-collection-feedback");
        if (!node) return;
        node.textContent = message || "";
        node.className = "account-feedback account-feedback--inline" + (kind ? " " + kind : "");
    }

    async function loadCollection() {
        if (!token) {
            root.innerHTML = '<div class="buy-empty">شناسه لینک پرداخت در آدرس وجود ندارد.</div>';
            return;
        }
        if (state.loading) return;
        state.loading = true;
        root.innerHTML = '<div class="buy-empty">در حال دریافت اطلاعات پرداخت...</div>';
        try {
            var response = await apiGet("publicCollection", { token: token });
            if (response && response.httpStatus === 401) {
                renderLogin();
                return;
            }
            if (!response || !response.success || !response.collection) {
                throw new Error((response && response.error) || "بارگذاری لینک پرداخت انجام نشد.");
            }
            state.collection = response.collection;
            if (paymentOrderToken) {
                var resultResponse = await apiGet("publicOrderResult", { orderToken: paymentOrderToken });
                if (resultResponse && resultResponse.success && resultResponse.order) {
                    state.result = resultResponse.order;
                }
            }
            renderCollection();
        } catch (error) {
            root.innerHTML = '<div class="buy-empty">' + escapeHtml(error && error.message ? error.message : "بارگذاری انجام نشد.") + "</div>";
        } finally {
            state.loading = false;
        }
    }

    async function submitPayment(event) {
        event.preventDefault();
        var form = event.target;
        var button = form.querySelector("button[type='submit']");
        var selectedGateway = form.querySelector("input[name='collection-gateway']:checked");
        var nameInput = document.getElementById("payment-collection-name");
        var phoneInput = document.getElementById("payment-collection-phone");
        var studentNumberInput = document.getElementById("payment-collection-student-number");
        button.disabled = true;
        setFeedback("در حال انتقال به درگاه پرداخت...", "");
        try {
            var response = await apiPost("createCollectionOrder", {
                token: token,
                payerName: nameInput ? String(nameInput.value || "").trim() : "",
                payerPhone: phoneInput ? normalizeDigits(phoneInput.value || "") : "",
                payerStudentNumber: studentNumberInput ? normalizeDigits(studentNumberInput.value || "") : "",
                gateway: selectedGateway ? selectedGateway.value : ""
            });
            if (response && response.httpStatus === 401) {
                renderLogin();
                return;
            }
            if (!response || !response.success || !response.redirectUrl) {
                throw new Error((response && response.error) || "ایجاد پرداخت انجام نشد.");
            }
            setFeedback("نشانی بازگشت ثبت شد؛ در حال انتقال به درگاه پرداخت...", "success");
            window.setTimeout(function () {
                window.location.href = response.redirectUrl;
            }, 500);
        } catch (error) {
            button.disabled = false;
            setFeedback(error && error.message ? error.message : "ایجاد پرداخت انجام نشد.", "error");
        }
    }

    root.addEventListener("click", function (event) {
        var copyButton = event.target && event.target.closest("[data-copy-collection-return]");
        if (!copyButton) return;
        copyText(copyButton.getAttribute("data-copy-collection-return")).then(function () {
            setFeedback("نشانی بازگشت کپی شد.", "success");
        }).catch(function () {
            setFeedback("کپی نشانی بازگشت انجام نشد.", "error");
        });
    });

    root.addEventListener("submit", function (event) {
        if (event.target && event.target.id === "payment-collection-form") {
            submitPayment(event);
        }
    });

    window.Dent1402Auth.onChange(function (detail) {
        if (!detail || detail.status === "session-restoring" || detail.status === "logging-out") {
            root.innerHTML = '<div class="buy-empty">در حال بررسی ورود...</div>';
            return;
        }
        state.loggedIn = !!detail.loggedIn;
        state.viewer = detail.loggedIn ? (detail.user || null) : null;
        loadCollection();
    });
})();
