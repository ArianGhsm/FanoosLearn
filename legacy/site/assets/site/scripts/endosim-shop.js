(function () {
    "use strict";

    var CATEGORY = "endodontic_models";
    var siteApi = window.Dent1402Site && typeof window.Dent1402Site === "object"
        ? window.Dent1402Site
        : null;

    var state = {
        items: [],
        quantities: {},      // slug -> quantity
        query: "",
        filter: "all",
        gateways: [],
        defaultGateway: "",
        quoteLoading: false,
        submitting: false
    };

    function $(id) {
        return document.getElementById(id);
    }

    function text(value) {
        return String(value == null ? "" : value).replace(/[&<>"]/g, function (ch) {
            switch (ch) {
                case "&": return "&amp;";
                case "<": return "&lt;";
                case ">": return "&gt;";
                case "\"": return "&quot;";
                default: return ch;
            }
        });
    }

    function money(value) {
        return (Math.max(0, Number(value) || 0)).toLocaleString("fa-IR") + " ریال";
    }

    function faNumber(value) {
        return (Math.max(0, Number(value) || 0)).toLocaleString("fa-IR");
    }

    function normalizeDigits(value) {
        if (siteApi && typeof siteApi.normalizeDigits === "function") {
            return siteApi.normalizeDigits(value);
        }
        return String(value || "")
            .replace(/[۰-۹]/g, function (ch) {
                return String("۰۱۲۳۴۵۶۷۸۹".indexOf(ch));
            })
            .replace(/[٠-٩]/g, function (ch) {
                return String("٠١٢٣٤٥٦٧٨٩".indexOf(ch));
            });
    }

    function normalizePhone(value) {
        if (siteApi && typeof siteApi.normalizePhone === "function") {
            return siteApi.normalizePhone(value);
        }
        var digits = normalizeDigits(value).replace(/\D+/g, "");
        if (!digits) return "";
        if (digits.indexOf("0098") === 0) digits = digits.slice(4);
        else if (digits.indexOf("98") === 0) digits = digits.slice(2);
        if (digits.length === 10 && digits.charAt(0) === "9") digits = "0" + digits;
        return digits;
    }

    function parseJsonResponse(response) {
        if (siteApi && typeof siteApi.parseJsonResponse === "function") {
            return siteApi.parseJsonResponse(response);
        }
        return response.text().then(function (body) {
            var payload = null;
            if (body) {
                try { payload = JSON.parse(body); } catch (_e) { payload = null; }
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

    function apiGet(action, params) {
        var query = new URLSearchParams(Object.assign({ action: action }, params || {}));
        return fetch("/api/payments_api.php?" + query.toString(), {
            method: "GET",
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        }).then(parseJsonResponse).catch(networkErrorResponse);
    }

    function apiPost(action, payload) {
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

    function isUnauthorized(payload) {
        return !!(payload && (payload.loggedOut || payload.httpStatus === 401));
    }

    function redirectToLogin() {
        var returnTo = window.location.pathname + window.location.search;
        window.location.href = "/account/?returnTo=" + encodeURIComponent(returnTo);
    }

    // ---- quantity helpers ----

    function maxQtyFor(item) {
        return Math.max(1, Math.min(99, Number(item && item.maxQuantityPerOrder) || 1));
    }

    function quantityFor(slug) {
        return Math.max(0, Number(state.quantities[slug]) || 0);
    }

    function setQuantity(slug, qty) {
        var item = findItem(slug);
        if (!item) return;
        var max = maxQtyFor(item);
        var next = Math.max(0, Math.min(max, Math.floor(Number(qty) || 0)));
        if (next <= 0) {
            delete state.quantities[slug];
        } else {
            state.quantities[slug] = next;
        }
    }

    function findItem(slug) {
        for (var i = 0; i < state.items.length; i++) {
            if (state.items[i].slug === slug) return state.items[i];
        }
        return null;
    }

    function selectedLines() {
        var lines = [];
        state.items.forEach(function (item) {
            var qty = quantityFor(item.slug);
            if (qty > 0) {
                lines.push({ item: item, quantity: qty, amount: qty * (Number(item.price) || 0) });
            }
        });
        return lines;
    }

    function provisionalTotal() {
        return selectedLines().reduce(function (sum, line) { return sum + line.amount; }, 0);
    }

    function totalCount() {
        return selectedLines().reduce(function (sum, line) { return sum + line.quantity; }, 0);
    }

    // ---- rendering ----

    function specValue(item, label) {
        var specs = Array.isArray(item.specifications) ? item.specifications : [];
        for (var i = 0; i < specs.length; i++) {
            if (specs[i] && String(specs[i].label) === label) {
                return String(specs[i].value || "");
            }
        }
        return "";
    }

    function matchesFilter(item) {
        if (state.filter !== "all") {
            var jaw = specValue(item, "فک");
            var type = specValue(item, "نوع دندان") + " " + specValue(item, "مشخصه");
            if (state.filter === "شیری") {
                if (type.indexOf("شیری") === -1) return false;
            } else if (jaw.indexOf(state.filter) === -1) {
                return false;
            }
        }
        if (state.query) {
            var haystack = (item.title + " " + item.shortDescription + " " +
                specValue(item, "کد محصول")).toLowerCase();
            if (haystack.indexOf(state.query) === -1) return false;
        }
        return true;
    }

    function cardMarkup(item) {
        var code = specValue(item, "کد محصول");
        var qty = quantityFor(item.slug);
        var chips = [specValue(item, "فک"), specValue(item, "مشخصه")]
            .filter(function (v) { return v; })
            .map(function (v) { return '<span class="endosim-card__chip">' + text(v) + "</span>"; })
            .join("");
        return [
            '<article class="endosim-card' + (qty > 0 ? " is-selected" : "") + '" data-endosim-card="' + text(item.slug) + '">',
            '  <div class="endosim-card__media">',
            '    <img loading="lazy" src="' + text(item.heroImage) + '" alt="' + text(item.title) + '">',
            code ? '    <span class="endosim-card__code">' + text(code) + "</span>" : "",
            '  </div>',
            '  <div class="endosim-card__body">',
            '    <h3 class="endosim-card__title">' + text(item.title) + "</h3>",
            '    <div class="endosim-card__chips">' + chips + "</div>",
            '    <div class="endosim-card__price">' + text(money(item.price)) + "</div>",
            '  </div>',
            '  <div class="endosim-card__stepper" data-endosim-stepper="' + text(item.slug) + '">',
            '    <button type="button" class="endosim-step" data-endosim-dec aria-label="کاهش">−</button>',
            '    <input class="endosim-qty" type="text" inputmode="numeric" value="' + faNumber(qty) + '" aria-label="تعداد ' + text(item.title) + '">',
            '    <button type="button" class="endosim-step" data-endosim-inc aria-label="افزایش">+</button>',
            '  </div>',
            "</article>"
        ].join("");
    }

    function renderGrid() {
        var grid = $("endosim-grid");
        var status = $("endosim-status");
        if (!grid) return;

        var visible = state.items.filter(matchesFilter);
        if (!state.items.length) {
            status.hidden = false;
            status.textContent = "هنوز محصولی ثبت نشده است.";
            grid.innerHTML = "";
            return;
        }
        if (!visible.length) {
            status.hidden = false;
            status.textContent = "موردی با این جست‌وجو پیدا نشد.";
            grid.innerHTML = "";
            return;
        }
        status.hidden = true;
        grid.innerHTML = visible.map(cardMarkup).join("");
    }

    function renderBar() {
        var bar = $("endosim-bar");
        if (!bar) return;
        var count = totalCount();
        var cta = $("endosim-bar-cta");
        $("endosim-bar-count").textContent = faNumber(count) + " قلم";
        $("endosim-bar-total").textContent = money(provisionalTotal());
        if (count > 0) {
            bar.hidden = false;
            bar.dataset.empty = "false";
            if (cta) cta.disabled = false;
        } else {
            bar.dataset.empty = "true";
            if (cta) cta.disabled = true;
        }
    }

    function syncCard(slug) {
        var card = document.querySelector('[data-endosim-card="' + cssEscape(slug) + '"]');
        if (!card) return;
        var qty = quantityFor(slug);
        card.classList.toggle("is-selected", qty > 0);
        var input = card.querySelector(".endosim-qty");
        if (input) input.value = faNumber(qty);
    }

    function cssEscape(value) {
        if (window.CSS && typeof window.CSS.escape === "function") {
            return window.CSS.escape(value);
        }
        return String(value).replace(/["\\]/g, "\\$&");
    }

    // ---- checkout ----

    function fillIdentityNote() {
        var note = $("endosim-identity-note");
        if (!note) return;
        var auth = window.Dent1402Auth;
        var user = auth && typeof auth.getCurrentUser === "function" ? auth.getCurrentUser() : null;
        var name = user && user.name ? String(user.name).trim() : "";
        note.textContent = name
            ? "سفارش به نام «" + name + "» ثبت می‌شود."
            : "سفارش به نام حساب شما ثبت می‌شود.";
    }

    function openCheckout() {
        if (!selectedLines().length) return;
        var modal = $("endosim-checkout");
        if (!modal) return;
        modal.hidden = false;
        document.body.classList.add("endosim-modal-open");
        setFeedback("", "");
        fillIdentityNote();
        refreshQuote();
    }

    function closeCheckout() {
        var modal = $("endosim-checkout");
        if (!modal) return;
        modal.hidden = true;
        document.body.classList.remove("endosim-modal-open");
    }

    function setFeedback(message, kind) {
        var node = $("endosim-feedback");
        if (!node) return;
        node.textContent = message || "";
        node.className = "endosim-feedback" + (kind ? " " + kind : "");
    }

    function cartItemsPayload() {
        return selectedLines().map(function (line) {
            return { slug: line.item.slug, quantity: line.quantity };
        });
    }

    function renderSummary(quote) {
        var node = $("endosim-summary");
        if (!node) return;
        var lines = quote && Array.isArray(quote.lines) ? quote.lines : [];
        var rows = lines.map(function (line) {
            return [
                '<div class="endosim-summary__row">',
                '  <span>' + text(line.title) + " × " + faNumber(line.quantity) + "</span>",
                '  <strong>' + text(money(line.amount)) + "</strong>",
                "</div>"
            ].join("");
        }).join("");
        var total = quote ? quote.amount : provisionalTotal();
        node.innerHTML = rows +
            '<div class="endosim-summary__total"><span>مبلغ قابل پرداخت</span><strong>' +
            text(money(total)) + "</strong></div>";
    }

    function renderGateways() {
        var fieldset = $("endosim-gateways");
        var empty = $("endosim-gateways-empty");
        if (!fieldset) return;
        var enabled = state.gateways.filter(function (g) { return g && g.isEnabled; });
        Array.prototype.slice.call(fieldset.querySelectorAll(".endosim-gateway")).forEach(function (n) {
            n.parentNode.removeChild(n);
        });
        if (!enabled.length) {
            if (empty) {
                empty.hidden = false;
                empty.textContent = "درگاه پرداخت فعالی موجود نیست.";
            }
            return;
        }
        if (empty) empty.hidden = true;
        var chosen = state.defaultGateway && enabled.some(function (g) { return g.key === state.defaultGateway; })
            ? state.defaultGateway
            : enabled[0].key;
        enabled.forEach(function (gateway) {
            var label = document.createElement("label");
            label.className = "endosim-gateway";
            label.innerHTML = [
                '<input type="radio" name="endosim_gateway" value="' + text(gateway.key) + '"' +
                    (gateway.key === chosen ? " checked" : "") + ">",
                '<span class="endosim-gateway__icon">' + text(gateway.icon || "💳") + "</span>",
                '<span class="endosim-gateway__label">' + text(gateway.label || gateway.provider || gateway.key) + "</span>"
            ].join("");
            fieldset.appendChild(label);
        });
    }

    function refreshQuote() {
        var lines = cartItemsPayload();
        if (!lines.length) return;
        state.quoteLoading = true;
        renderSummary(null);
        apiPost("quoteCart", { items: JSON.stringify(lines) }).then(function (response) {
            state.quoteLoading = false;
            if (isUnauthorized(response)) { redirectToLogin(); return; }
            if (!response || !response.success || !response.quote) {
                setFeedback((response && response.error) || "محاسبه سبد ممکن نشد.", "is-error");
                return;
            }
            if (response.paymentGateways) {
                state.gateways = Array.isArray(response.paymentGateways.gateways)
                    ? response.paymentGateways.gateways : [];
                state.defaultGateway = String(response.paymentGateways.defaultKey || "");
            }
            renderSummary(response.quote);
            renderGateways();
        });
    }

    function submitOrder(event) {
        event.preventDefault();
        if (state.submitting) return;

        var lines = cartItemsPayload();
        if (!lines.length) { setFeedback("سبد خرید خالی است.", "is-error"); return; }
        var selected = document.querySelector('input[name="endosim_gateway"]:checked');
        if (!selected) { setFeedback("روش پرداخت را انتخاب کنید.", "is-error"); return; }

        state.submitting = true;
        var payBtn = $("endosim-pay");
        if (payBtn) { payBtn.disabled = true; payBtn.textContent = "در حال انتقال به درگاه…"; }
        setFeedback("در حال ایجاد سفارش…", "");

        apiPost("createCartOrder", {
            items: JSON.stringify(lines),
            extraFormData: JSON.stringify({ source: "endosim" }),
            gateway: String(selected.value || "").trim()
        }).then(function (response) {
            if (isUnauthorized(response)) { redirectToLogin(); return; }
            if (!response || !response.success || !response.redirectUrl) {
                state.submitting = false;
                if (payBtn) { payBtn.disabled = false; payBtn.textContent = "پرداخت مبلغ کل"; }
                setFeedback((response && response.error) || "ایجاد سفارش انجام نشد.", "is-error");
                return;
            }
            window.location.href = response.redirectUrl;
        });
    }

    // ---- events ----

    function onGridClick(event) {
        var stepper = event.target.closest("[data-endosim-stepper]");
        if (!stepper) return;
        var slug = stepper.getAttribute("data-endosim-stepper");
        if (event.target.closest("[data-endosim-inc]")) {
            setQuantity(slug, quantityFor(slug) + 1);
        } else if (event.target.closest("[data-endosim-dec]")) {
            setQuantity(slug, quantityFor(slug) - 1);
        } else {
            return;
        }
        syncCard(slug);
        renderBar();
    }

    function onGridInput(event) {
        var input = event.target.closest(".endosim-qty");
        if (!input) return;
        var stepper = input.closest("[data-endosim-stepper]");
        if (!stepper) return;
        var slug = stepper.getAttribute("data-endosim-stepper");
        var raw = normalizeDigits(input.value).replace(/\D+/g, "");
        setQuantity(slug, raw);
        renderBar();
    }

    function onGridBlur(event) {
        var input = event.target.closest && event.target.closest(".endosim-qty");
        if (!input) return;
        var stepper = input.closest("[data-endosim-stepper]");
        if (!stepper) return;
        syncCard(stepper.getAttribute("data-endosim-stepper"));
    }

    function bindEvents() {
        var grid = $("endosim-grid");
        if (grid) {
            grid.addEventListener("click", onGridClick);
            grid.addEventListener("input", onGridInput);
            grid.addEventListener("blur", onGridBlur, true);
        }

        var search = $("endosim-search");
        if (search) {
            search.addEventListener("input", function () {
                state.query = normalizeDigits(search.value || "").trim().toLowerCase();
                renderGrid();
            });
        }

        var filters = $("endosim-filters");
        if (filters) {
            filters.addEventListener("click", function (event) {
                var btn = event.target.closest("[data-endosim-filter]");
                if (!btn) return;
                state.filter = btn.getAttribute("data-endosim-filter") || "all";
                Array.prototype.slice.call(filters.querySelectorAll("[data-endosim-filter]")).forEach(function (n) {
                    n.classList.toggle("is-active", n === btn);
                });
                renderGrid();
            });
        }

        var cta = $("endosim-bar-cta");
        if (cta) cta.addEventListener("click", openCheckout);

        var modal = $("endosim-checkout");
        if (modal) {
            modal.addEventListener("click", function (event) {
                if (event.target.closest("[data-endosim-close]")) {
                    closeCheckout();
                }
            });
        }
        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") closeCheckout();
        });

        var form = $("endosim-form");
        if (form) form.addEventListener("submit", submitOrder);
    }

    function loadItems() {
        apiGet("listPublicItems", {}).then(function (response) {
            if (isUnauthorized(response)) { redirectToLogin(); return; }
            var status = $("endosim-status");
            if (!response || !response.success || !Array.isArray(response.items)) {
                if (status) {
                    status.hidden = false;
                    status.textContent = (response && response.error) || "بارگذاری محصولات ممکن نشد.";
                }
                return;
            }
            state.items = response.items.filter(function (item) {
                return item && item.category === CATEGORY;
            }).sort(function (a, b) {
                var ca = "";
                var cb = "";
                (a.specifications || []).forEach(function (s) { if (s.label === "کد محصول") ca = String(s.value); });
                (b.specifications || []).forEach(function (s) { if (s.label === "کد محصول") cb = String(s.value); });
                return ca.localeCompare(cb);
            });
            renderGrid();
            renderBar();
        });
    }

    function init() {
        bindEvents();
        loadItems();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init, { once: true });
    } else {
        init();
    }
})();
