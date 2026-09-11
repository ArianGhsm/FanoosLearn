(function () {
    "use strict";

    var SAVED_KEY = "dent1402_buy_saved_slugs";
    var CART_KEY = "dent1402_buy_cart_items";
    var CHECKOUT_KEY = "dent1402_buy_checkout";
    var MARKET_LOCATION = "دانشکده دندانپزشکی تهران";
    var siteApi = window.Dent1402Site && typeof window.Dent1402Site === "object"
        ? window.Dent1402Site
        : null;
    var state = {
        items: [],
        query: "",
        category: "all",
        filter: "all",
        currentItem: null,
        cartQuote: null,
        cartGateways: null,
        cartQuoteError: "",
        cartQuoteLoading: false
    };
    var cartQuoteTimer = null;

    function $(id) {
        return document.getElementById(id);
    }

    function parseJsonResponse(response) {
        if (siteApi && typeof siteApi.parseJsonResponse === "function") {
            return siteApi.parseJsonResponse(response);
        }
        return response.json().catch(function () {
            return {
                success: false,
                error: "پاسخ نامعتبر از سرور دریافت شد."
            };
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

    function normalizePhone(value) {
        if (siteApi && typeof siteApi.normalizePhone === "function") {
            return siteApi.normalizePhone(value);
        }
        var digits = normalizeDigits(value).replace(/\D+/g, "");
        if (!digits) return "";
        if (digits.indexOf("0098") === 0) {
            digits = digits.slice(4);
        } else if (digits.indexOf("98") === 0) {
            digits = digits.slice(2);
        }
        if (digits.length === 10 && digits.charAt(0) === "9") {
            digits = "0" + digits;
        }
        return digits;
    }

    function text(value) {
        return String(value == null ? "" : value).replace(/[&<>"]/g, function (char) {
            switch (char) {
                case "&":
                    return "&amp;";
                case "<":
                    return "&lt;";
                case ">":
                    return "&gt;";
                case "\"":
                    return "&quot;";
                default:
                    return char;
            }
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

    function statusClass(stateKey) {
        switch (String(stateKey || "")) {
            case "active":
            case "success":
                return "is-active";
            case "upcoming":
            case "pending":
            case "full":
                return "is-warn";
            case "failed":
            case "canceled":
            case "expired":
                return "is-error";
            default:
                return "is-muted";
        }
    }

    function setFeedback(node, message, kind) {
        if (!node) return;
        node.className = "buy-feedback" + (kind ? (" " + kind) : "");
        node.textContent = message || "";
    }

    function readParam(name) {
        var params = new URLSearchParams(window.location.search);
        return String(params.get(name) || "").trim();
    }

    function readSlugFromLocation() {
        var slug = readParam("slug");
        if (slug) {
            return slug;
        }
        var parts = String(window.location.pathname || "").split("/").filter(Boolean);
        var itemIndex = parts.indexOf("item");
        if (itemIndex >= 0 && parts.length > itemIndex + 1) {
            return parts[itemIndex + 1];
        }
        return "";
    }

    function savedSlugs() {
        try {
            var parsed = JSON.parse(window.localStorage.getItem(SAVED_KEY) || "[]");
            if (Array.isArray(parsed)) {
                return parsed.map(function (slug) {
                    return String(slug || "").trim();
                }).filter(Boolean);
            }
        } catch (_error) {
            return [];
        }
        return [];
    }

    function writeSavedSlugs(slugs) {
        try {
            window.localStorage.setItem(SAVED_KEY, JSON.stringify(Array.from(new Set(slugs.filter(Boolean)))));
        } catch (_error) {
            // Local save is optional; payment flow must keep working without it.
        }
    }

    function isSaved(slug) {
        return savedSlugs().indexOf(String(slug || "").trim()) >= 0;
    }

    function toggleSaved(slug) {
        var clean = String(slug || "").trim();
        if (!clean) return false;
        var current = savedSlugs();
        var index = current.indexOf(clean);
        if (index >= 0) {
            current.splice(index, 1);
            writeSavedSlugs(current);
            return false;
        }
        current.push(clean);
        writeSavedSlugs(current);
        return true;
    }

    function cartItems() {
        try {
            var parsed = JSON.parse(window.localStorage.getItem(CART_KEY) || "[]");
            if (Array.isArray(parsed)) {
                return parsed.map(function (entry) {
                    return {
                        slug: String(entry && entry.slug || "").trim(),
                        quantity: Math.max(1, Math.min(99, Number(entry && entry.quantity) || 1)),
                        addedAt: String(entry && entry.addedAt || "")
                    };
                }).filter(function (entry) {
                    return entry.slug;
                });
            }
        } catch (_error) {
            return [];
        }
        return [];
    }

    function writeCartItems(items) {
        try {
            window.localStorage.setItem(CART_KEY, JSON.stringify(items || []));
        } catch (_error) {
            // Cart is a convenience layer; checkout still works from the item page.
        }
    }

    function addToCart(slug, quantity, replaceQuantity) {
        var clean = String(slug || "").trim();
        if (!clean) return cartItems();
        var items = cartItems();
        var existing = items.find(function (entry) { return entry.slug === clean; });
        var nextQuantity = Math.max(1, Math.min(99, Number(quantity) || 1));
        if (existing) {
            existing.quantity = replaceQuantity
                ? nextQuantity
                : Math.max(1, Math.min(99, (Number(existing.quantity) || 1) + nextQuantity));
            existing.addedAt = new Date().toISOString();
        } else {
            items.unshift({
                slug: clean,
                quantity: nextQuantity,
                addedAt: new Date().toISOString()
            });
        }
        writeCartItems(items);
        return items;
    }

    function updateCartQuantity(slug, quantity) {
        var clean = String(slug || "").trim();
        var raw = Number(quantity);
        if (!Number.isFinite(raw) || raw < 1) {
            removeFromCart(clean);
            return;
        }
        var next = Math.max(1, Math.min(99, raw));
        var changed = false;
        var items = cartItems().map(function (entry) {
            if (entry.slug === clean) {
                changed = true;
                return {
                    slug: entry.slug,
                    quantity: next,
                    addedAt: entry.addedAt || new Date().toISOString()
                };
            }
            return entry;
        });
        if (changed) {
            writeCartItems(items);
        }
    }

    function removeFromCart(slug) {
        var clean = String(slug || "").trim();
        writeCartItems(cartItems().filter(function (entry) {
            return entry.slug !== clean;
        }));
    }

    function removeCartSlugs(slugs) {
        var remove = Array.isArray(slugs) ? slugs.map(function (slug) {
            return String(slug || "").trim();
        }).filter(Boolean) : [];
        if (!remove.length) {
            return;
        }
        writeCartItems(cartItems().filter(function (entry) {
            return remove.indexOf(entry.slug) < 0;
        }));
    }

    function readCheckoutData() {
        try {
            var parsed = JSON.parse(window.localStorage.getItem(CHECKOUT_KEY) || "{}");
            if (parsed && typeof parsed === "object") {
                return {
                    discountCode: String(parsed.discountCode || ""),
                    payerName: String(parsed.payerName || ""),
                    payerPhone: String(parsed.payerPhone || ""),
                    payerStudentNumber: String(parsed.payerStudentNumber || ""),
                    gateway: String(parsed.gateway || ""),
                    lineExtraFormData: parsed.lineExtraFormData && typeof parsed.lineExtraFormData === "object" ? parsed.lineExtraFormData : {}
                };
            }
        } catch (_error) {
            // Checkout data is local convenience state only.
        }
        return {
            discountCode: "",
            payerName: "",
            payerPhone: "",
            payerStudentNumber: "",
            gateway: "",
            lineExtraFormData: {}
        };
    }

    function writeCheckoutData(data) {
        try {
            window.localStorage.setItem(CHECKOUT_KEY, JSON.stringify(data || {}));
        } catch (_error) {
            // Checkout still works without local persistence.
        }
    }

    function updateCheckoutData(patch) {
        var data = readCheckoutData();
        Object.keys(patch || {}).forEach(function (key) {
            data[key] = patch[key];
        });
        writeCheckoutData(data);
        return data;
    }

    function copyText(value) {
        var clean = String(value || "").trim();
        if (!clean) {
            return Promise.reject(new Error("empty"));
        }
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(clean);
        }
        return new Promise(function (resolve, reject) {
            var input = document.createElement("textarea");
            input.value = clean;
            input.setAttribute("readonly", "readonly");
            input.style.position = "fixed";
            input.style.insetInlineStart = "-9999px";
            document.body.appendChild(input);
            input.select();
            try {
                var ok = document.execCommand("copy");
                document.body.removeChild(input);
                ok ? resolve() : reject(new Error("copy"));
            } catch (error) {
                document.body.removeChild(input);
                reject(error);
            }
        });
    }

    function flashButton(button, value, fallback) {
        if (!button) return;
        var original = button.textContent;
        button.textContent = value;
        window.setTimeout(function () {
            button.textContent = fallback || original;
        }, 1200);
    }

    function shareUrl(url, title, button) {
        var shareData = {
            title: title || document.title,
            text: title || "آیتم خرید",
            url: url
        };
        if (navigator.share) {
            navigator.share(shareData).catch(function () {});
            return;
        }
        copyText(url).then(function () {
            flashButton(button, "✓", "↗");
        }).catch(function () {
            if (button) {
                button.setAttribute("title", "کپی خودکار در این مرورگر پشتیبانی نشد.");
            }
        });
    }

    function gatewayProviderFallback(key) {
        var clean = String(key || "").trim().toLowerCase();
        if (clean === "zibal") {
            return "درگاه زیبال";
        }
        if (clean === "zarinpal") {
            return "درگاه زرین‌پال";
        }
        if (clean === "mock") {
            return "درگاه آزمایشی";
        }
        return "درگاه آنلاین";
    }

    function normalizeGatewayBundle(raw) {
        var source = raw && typeof raw === "object" ? raw : {};
        var rows = Array.isArray(source.gateways) ? source.gateways : [];
        var defaultKey = String(source.defaultKey || "").trim().toLowerCase();
        var gateways = [];
        rows.forEach(function (row) {
            if (!row || typeof row !== "object") {
                return;
            }
            var key = String(row.key || "").trim().toLowerCase();
            if (!key) {
                return;
            }
            gateways.push({
                key: key,
                label: String(row.label || "پرداخت آنلاین"),
                provider: String(row.provider || gatewayProviderFallback(key)),
                icon: String(row.icon || "").trim(),
                isEnabled: !!row.isEnabled,
                isDefault: !!row.isDefault
            });
        });

        if (!defaultKey) {
            var explicit = gateways.find(function (entry) { return entry.isEnabled && entry.isDefault; });
            if (explicit) {
                defaultKey = explicit.key;
            }
        }
        if (!defaultKey) {
            var firstEnabled = gateways.find(function (entry) { return entry.isEnabled; });
            if (firstEnabled) {
                defaultKey = firstEnabled.key;
            }
        }

        return {
            gateways: gateways,
            defaultKey: defaultKey
        };
    }

    function renderGatewayOptions(raw, form, submit) {
        var section = $("buy-gateway-section");
        var root = $("buy-gateway-options");
        if (!section || !root || !form) {
            return {
                hasEnabled: true,
                getSelected: function () { return ""; },
                refreshSubmitText: function () {}
            };
        }

        var bundle = normalizeGatewayBundle(raw);
        var options = bundle.gateways;
        var enabledCount = options.filter(function (entry) { return entry.isEnabled; }).length;

        if (!options.length) {
            section.hidden = true;
            root.innerHTML = "";
            return {
                hasEnabled: true,
                getSelected: function () { return ""; },
                refreshSubmitText: function () {}
            };
        }

        section.hidden = false;
        var selectedOnce = false;
        var firstEnabled = options.find(function (entry) { return entry.isEnabled; });
        var fallbackKey = firstEnabled ? firstEnabled.key : "";
        root.innerHTML = options.map(function (entry, index) {
            var inputId = "buy-gateway-" + entry.key + "-" + String(index);
            var checked = false;
            if (entry.isEnabled && !selectedOnce && bundle.defaultKey && entry.key === bundle.defaultKey) {
                checked = true;
                selectedOnce = true;
            } else if (entry.isEnabled && !selectedOnce && (!bundle.defaultKey || bundle.defaultKey !== fallbackKey) && entry.key === fallbackKey) {
                checked = true;
                selectedOnce = true;
            }
            var disabled = !entry.isEnabled;
            var subtitle = entry.provider || gatewayProviderFallback(entry.key);
            if (disabled) {
                subtitle += " (غیرفعال)";
            }

            return [
                '<label class="buy-gateway-option' + (disabled ? " is-disabled" : "") + '" for="' + text(inputId) + '">',
                '  <input id="' + text(inputId) + '" type="radio" name="buy_gateway" value="' + text(entry.key) + '"' + (checked ? " checked" : "") + (disabled ? " disabled" : "") + ">",
                '  <span class="buy-gateway-option__radio" aria-hidden="true"></span>',
                '  <span class="buy-gateway-option__copy">',
                '    <strong class="buy-gateway-option__title">' + text(entry.label || "پرداخت آنلاین") + "</strong>",
                '    <small class="buy-gateway-option__meta">' + text(subtitle) + "</small>",
                "  </span>",
                '  <span class="buy-gateway-option__badge">' + text(entry.icon || entry.key.toUpperCase()) + "</span>",
                "</label>"
            ].join("");
        }).join("") + '<div class="buy-alert">پس از پرداخت، روی اتمام پرداخت بزنید و به همین سایت برگردید تا پیام تایید را ببینید.</div>';

        function readSelectedInput() {
            return form.querySelector("input[name='buy_gateway']:checked");
        }

        function selectedTitle() {
            var input = readSelectedInput();
            if (!input) {
                return "پرداخت آنلاین";
            }
            var optionNode = input.closest(".buy-gateway-option");
            if (!optionNode) {
                return "پرداخت آنلاین";
            }
            var titleNode = optionNode.querySelector(".buy-gateway-option__title");
            return titleNode ? String(titleNode.textContent || "").trim() || "پرداخت آنلاین" : "پرداخت آنلاین";
        }

        function refreshSubmitText() {
            if (!submit) {
                return;
            }
            if (enabledCount <= 0) {
                submit.textContent = "درگاه فعالی موجود نیست";
                submit.disabled = true;
                return;
            }
            submit.textContent = selectedTitle();
        }

        root.addEventListener("change", function () {
            refreshSubmitText();
        });
        refreshSubmitText();

        return {
            hasEnabled: enabledCount > 0,
            getSelected: function () {
                var input = readSelectedInput();
                return input ? String(input.value || "").trim().toLowerCase() : "";
            },
            refreshSubmitText: refreshSubmitText
        };
    }

    function itemText(item) {
        var specs = Array.isArray(item.specifications) ? item.specifications.map(function (spec) {
            return [spec.label, spec.value].join(" ");
        }).join(" ") : "";
        return [
            item.title,
            item.shortDescription,
            item.fullDescription,
            specs,
            item.slug
        ].join(" ").toLowerCase();
    }

    function itemCategory(item) {
        var direct = String(item && item.category || "").trim();
        if (direct) {
            return direct;
        }
        var haystack = itemText(item);
        if (/مواد|اقلام مصرفی|دستکش|ماسک|گاز|سرنگ|consumable/.test(haystack)) return "consumables";
        if (/جزوه|کتاب|بسته|کلاس|درس|آزمون|امتحان|کوئیز|package|class|exam|quiz/.test(haystack)) return "educational_package";
        if (/رویداد|اردو|همایش|جشن|ثبت‌نام|ثبت نام|event|camp/.test(haystack)) return "event_registration";
        if (/شیلد|ابزار|وسایل|ملزومات|educational|supplies/.test(haystack)) return "educational_supplies";
        return "group_order";
    }

    function categoryLabel(key) {
        switch (key) {
            case "educational_supplies":
                return "ملزومات آموزشی";
            case "consumables":
                return "اقلام مصرفی";
            case "event_registration":
                return "ثبت‌نام رویداد";
            case "educational_package":
                return "بسته آموزشی";
            case "group_order":
                return "سفارش گروهی";
            default:
                return "آیتم مشخص";
        }
    }

    function imageList(item) {
        var output = [];
        var hero = String(item.heroImage || "").trim();
        if (hero) output.push(hero);
        if (Array.isArray(item.gallery)) {
            item.gallery.forEach(function (url) {
                var clean = String(url || "").trim();
                if (clean && output.indexOf(clean) < 0) {
                    output.push(clean);
                }
            });
        }
        return output;
    }

    function itemMeta(item) {
        var meta = [];
        if (item.expiresAt) {
            meta.push("مهلت " + formatDateTime(item.expiresAt, "—"));
        } else if (item.updatedAt) {
            meta.push("به‌روزرسانی " + formatDateTime(item.updatedAt, "—"));
        }
        if (item.remainingCapacity != null) {
            meta.push("باقی‌مانده " + Number(item.remainingCapacity || 0).toLocaleString("fa-IR"));
        }
        meta.push(MARKET_LOCATION);
        return meta.join(" • ");
    }

    function matchesFilters(item) {
        var query = state.query.trim().toLowerCase();
        if (query && itemText(item).indexOf(query) < 0) {
            return false;
        }
        if (state.category === "saved" && !isSaved(item.slug)) {
            return false;
        }
        if (state.category !== "all" && state.category !== "saved" && itemCategory(item) !== state.category) {
            return false;
        }
        var itemState = item.state || {};
        if (state.filter === "payable" && !itemState.isPayable) {
            return false;
        }
        if (state.filter === "limited" && item.capacity == null) {
            return false;
        }
        if (state.filter === "deadline" && !item.expiresAt) {
            return false;
        }
        return true;
    }

    function syncListControls() {
        document.querySelectorAll("[data-buy-category]").forEach(function (button) {
            button.classList.toggle("is-active", String(button.dataset.buyCategory || "") === state.category);
        });
        document.querySelectorAll("[data-buy-filter]").forEach(function (button) {
            button.classList.toggle("is-active", String(button.dataset.buyFilter || "") === state.filter);
        });
        var clear = $("buy-clear-search");
        if (clear) {
            clear.hidden = state.query.trim() === "";
        }
    }

    function renderList(items) {
        var root = $("buy-list-root");
        var countNode = $("buy-list-count");
        if (!root) return;

        var source = Array.isArray(items) ? items : [];
        var visible = source.filter(matchesFilters);
        syncListControls();

        if (countNode) {
            countNode.textContent = visible.length
                ? visible.length.toLocaleString("fa-IR") + " آیتم مطابق فیلتر"
                : "موردی پیدا نشد";
        }

        if (!source.length) {
            root.innerHTML = '<div class="buy-empty">فعلا آیتم فعالی برای خرید یا ثبت‌نام وجود ندارد.</div>';
            return;
        }
        if (!visible.length) {
            root.innerHTML = '<div class="buy-empty">برای این جست‌وجو یا فیلتر، آیتمی پیدا نشد.</div>';
            return;
        }

        root.innerHTML = visible.map(function (item) {
            var itemState = item.state || {};
            var images = imageList(item);
            var hero = images[0] || "";
            var slug = String(item.slug || "");
            var href = "/buy/item/?slug=" + encodeURIComponent(slug);
            var saved = isSaved(slug);
            return [
                '<article class="buy-item-card" data-buy-card="' + text(slug) + '">',
                '  <div class="buy-item-card__body">',
                '    <div class="buy-item-card__top">',
                '      <span class="buy-status ' + statusClass(itemState.key || item.status) + '">' + text(itemState.label || "نامشخص") + "</span>",
                '      <span class="buy-kicker">' + text(categoryLabel(itemCategory(item))) + "</span>",
                "    </div>",
                '    <a href="' + href + '"><h3 class="buy-item-card__title">' + text(item.title || "بدون عنوان") + "</h3></a>",
                '    <p class="buy-item-card__desc">' + text(item.shortDescription || "—") + "</p>",
                '    <strong class="buy-item-card__price">' + text(money(item.price || 0)) + "</strong>",
                '    <p class="buy-item-card__meta">' + text(itemMeta(item)) + "</p>",
                '    <div class="buy-item-card__actions">',
                '      <a class="buy-card-link" href="' + href + '">مشاهده و سفارش</a>',
                '      <button class="buy-card-icon-btn" type="button" data-buy-cart="' + text(slug) + '" aria-label="افزودن به سبد">＋</button>',
                '      <button class="buy-card-icon-btn' + (saved ? " is-saved" : "") + '" type="button" data-buy-save="' + text(slug) + '" aria-label="افزودن به علاقه‌مندی‌ها">' + (saved ? "♥" : "♡") + "</button>",
                '      <button class="buy-card-icon-btn" type="button" data-buy-share="' + text(slug) + '" data-buy-share-title="' + text(item.title || "آیتم خرید") + '" aria-label="اشتراک‌گذاری">↗</button>',
                "    </div>",
                "  </div>",
                '  <a class="buy-item-card__hero" href="' + href + '">',
                hero
                    ? '    <img src="' + text(hero) + '" alt="' + text(item.title || "تصویر آیتم") + '">'
                    : '    <span>بدون تصویر</span>',
                images.length ? '    <span class="buy-item-card__media-badge">' + text(images.length.toLocaleString("fa-IR")) + "</span>" : "",
                "  </a>",
                "</article>"
            ].join("");
        }).join("");
    }

    function bindListControls() {
        var search = $("buy-search-input");
        var clear = $("buy-clear-search");
        var root = $("buy-list-root");

        if (search && !search.dataset.buyBound) {
            search.dataset.buyBound = "1";
            search.addEventListener("input", function () {
                state.query = search.value || "";
                renderList(state.items);
            });
        }
        if (clear && !clear.dataset.buyBound) {
            clear.dataset.buyBound = "1";
            clear.addEventListener("click", function () {
                state.query = "";
                if (search) search.value = "";
                renderList(state.items);
                if (search) search.focus();
            });
        }

        document.querySelectorAll("[data-buy-category]").forEach(function (button) {
            if (button.dataset.buyBound) return;
            button.dataset.buyBound = "1";
            button.addEventListener("click", function () {
                state.category = String(button.dataset.buyCategory || "all");
                renderList(state.items);
            });
        });

        document.querySelectorAll("[data-buy-filter]").forEach(function (button) {
            if (button.dataset.buyBound) return;
            button.dataset.buyBound = "1";
            button.addEventListener("click", function () {
                state.filter = String(button.dataset.buyFilter || "all");
                renderList(state.items);
            });
        });

        if (root && !root.dataset.buyBound) {
            root.dataset.buyBound = "1";
            root.addEventListener("click", function (event) {
                var saveButton = event.target.closest("[data-buy-save]");
                if (saveButton) {
                    var slug = saveButton.getAttribute("data-buy-save");
                    var saved = toggleSaved(slug);
                    saveButton.classList.toggle("is-saved", saved);
                    saveButton.textContent = saved ? "♥" : "♡";
                    if (state.category === "saved") {
                        renderList(state.items);
                    }
                    return;
                }

                var shareButton = event.target.closest("[data-buy-share]");
                if (shareButton) {
                    var shareSlug = shareButton.getAttribute("data-buy-share");
                    var title = shareButton.getAttribute("data-buy-share-title") || "آیتم خرید";
                    var url = window.location.origin + "/buy/item/?slug=" + encodeURIComponent(shareSlug || "");
                    shareUrl(url, title, shareButton);
                    return;
                }

                var cartButton = event.target.closest("[data-buy-cart]");
                if (cartButton) {
                    addToCart(cartButton.getAttribute("data-buy-cart"), 1);
                    flashButton(cartButton, "✓", "＋");
                    renderCartDock(state.items);
                }
            });
        }
    }

    function initOwnerEntry() {
        var entry = $("buy-owner-entry");
        if (!entry || !window.Dent1402Auth || typeof window.Dent1402Auth.getState !== "function") {
            return;
        }
        function sync() {
            var auth = window.Dent1402Auth.getState();
            var user = auth && auth.loggedIn ? auth.user : null;
            entry.hidden = !(user && user.isOwner);
        }
        sync();
        var listen = window.Dent1402Auth.subscribe || window.Dent1402Auth.onChange;
        if (!entry.dataset.buyOwnerBound && typeof listen === "function") {
            entry.dataset.buyOwnerBound = "1";
            listen(sync);
        }
    }

    function renderRequiredFields(schema) {
        var root = $("buy-extra-fields");
        if (!root) return;

        if (!Array.isArray(schema) || !schema.length) {
            root.innerHTML = "";
            return;
        }

        root.innerHTML = schema.map(function (field) {
            var name = String(field.name || "").trim();
            if (!name) {
                return "";
            }

            var type = String(field.type || "text");
            var label = String(field.label || name);
            var placeholder = String(field.placeholder || "");
            var required = !!field.required;
            var maxLength = Number(field.maxLength || 120);
            var options = Array.isArray(field.options) ? field.options : [];
            var attrs = [
                'data-extra-field="true"',
                'data-field-name="' + text(name) + '"',
                'data-field-label="' + text(label) + '"',
                'data-field-required="' + (required ? "1" : "0") + '"'
            ];

            var control = "";
            if (type === "textarea") {
                control = '<textarea ' + attrs.join(" ") + ' maxlength="' + String(Math.max(10, Math.min(1000, maxLength))) + '" placeholder="' + text(placeholder) + '"' + (required ? " required" : "") + "></textarea>";
            } else if (type === "select") {
                var optionsHtml = ['<option value="">انتخاب کنید</option>'];
                options.forEach(function (option) {
                    optionsHtml.push('<option value="' + text(option) + '">' + text(option) + "</option>");
                });
                control = '<select ' + attrs.join(" ") + (required ? " required" : "") + ">" + optionsHtml.join("") + "</select>";
            } else {
                var inputType = type === "tel" ? "tel" : (type === "number" ? "number" : "text");
                var digitAttrs = (inputType === "tel" || inputType === "number") ? ' inputmode="numeric" data-digit-locale="latin"' : "";
                control = '<input ' + attrs.join(" ") + ' type="' + inputType + '"' + digitAttrs + ' maxlength="' + String(Math.max(10, Math.min(1000, maxLength))) + '" placeholder="' + text(placeholder) + '"' + (required ? " required" : "") + ">";
            }

            return [
                '<label class="buy-form__field">',
                '  <span>' + text(label) + (required ? " *" : "") + "</span>",
                control,
                "</label>"
            ].join("");
        }).join("");
    }

    function renderSpecifications(specifications) {
        var root = $("buy-item-specs");
        if (!root) return;

        var specs = Array.isArray(specifications) ? specifications : [];
        if (!specs.length) {
            root.innerHTML = '<div class="buy-empty">جزئیات تکمیلی برای این مورد ثبت نشده است.</div>';
            return;
        }

        root.innerHTML = specs.map(function (spec) {
            return [
                '<div class="buy-spec-row">',
                '  <span>' + text(spec.label || "عنوان") + "</span>",
                '  <strong>' + text(spec.value || "—") + "</strong>",
                "</div>"
            ].join("");
        }).join("");
    }

    function renderPolicy(item) {
        var root = $("buy-item-policy");
        if (!root) return;
        var rows = [
            { label: "مخاطب", value: item.audienceNote || "دانشجویان دندانپزشکی ورودی ۱۴۰۲" },
            { label: "تحویل یا استفاده", value: item.deliveryNote || "دانشگاه علوم پزشکی تهران" },
            { label: "لغو سفارش", value: item.allowCancellation ? "امکان لغو با هماهنگی مالک فعال است." : "لغو توسط خریدار برای این آیتم فعال نیست." },
            { label: "پشتیبانی", value: item.supportNote || "برای پیگیری سفارش با نماینده یا مالک سایت تماس بگیرید." }
        ];
        root.innerHTML = rows.map(function (row) {
            return [
                '<div class="buy-spec-row">',
                '  <span>' + text(row.label) + "</span>",
                '  <strong>' + text(row.value) + "</strong>",
                "</div>"
            ].join("");
        }).join("");
    }

    function ratingText(item) {
        var rating = Number(item && item.ratingAverage) || 0;
        var count = Number(item && item.ratingCount) || 0;
        if (rating <= 0 || count <= 0) {
            return "بدون امتیاز ثبت‌شده";
        }
        return rating.toLocaleString("fa-IR", { maximumFractionDigits: 1 }) + " از ۵ • " + count.toLocaleString("fa-IR") + " امتیاز";
    }

    function renderReviews(item) {
        var line = $("buy-rating-line");
        var countNode = $("buy-review-count");
        var root = $("buy-item-reviews");
        var reviews = Array.isArray(item && item.reviews) ? item.reviews : [];
        if (line) {
            line.textContent = ratingText(item);
        }
        if (countNode) {
            countNode.textContent = reviews.length ? reviews.length.toLocaleString("fa-IR") + " دیدگاه" : "دیدگاهی ثبت نشده";
        }
        if (!root) return;
        if (!reviews.length) {
            root.innerHTML = '<div class="buy-empty">هنوز دیدگاهی برای این آیتم ثبت نشده است.</div>';
            return;
        }
        root.innerHTML = reviews.map(function (review) {
            return [
                '<article class="buy-review-card">',
                '  <div><strong>' + text(review.name || "کاربر") + '</strong><span>' + text((Number(review.rating || 0)).toLocaleString("fa-IR", { maximumFractionDigits: 1 })) + " از ۵</span></div>",
                '  <p>' + text(review.body || "") + "</p>",
                review.created_at ? '  <small>' + text(formatDateTime(review.created_at, "—")) + "</small>" : "",
                "</article>"
            ].join("");
        }).join("");
    }

    function itemQuantityFallback(defaultQuantity) {
        var fallback = Math.max(1, Number(defaultQuantity || 1) || 1);
        var input = $("buy-order-quantity");
        if (!input) {
            return fallback;
        }
        var raw = normalizeDigits(String(input.value || fallback)).replace(/\D+/g, "");
        return Math.max(1, Number(raw) || fallback);
    }

    function localItemQuote(item, quantity) {
        var unitPrice = Number(item && item.price || 0) || 0;
        var normalizedQuantity = Math.max(1, Number(quantity || 1) || 1);
        var subtotal = unitPrice * normalizedQuantity;
        return {
            quantity: normalizedQuantity,
            unitPrice: unitPrice,
            subtotal: subtotal,
            discountAmount: 0,
            amount: subtotal
        };
    }

    function renderPriceBreakdown(quote, item) {
        var root = $("buy-price-breakdown");
        if (!root || !item) return;
        var source = quote || localItemQuote(item, itemQuantityFallback(1));
        var subtotal = Number(source.subtotal || 0) || 0;
        var amount = Number(source.amount || 0) || subtotal;
        var quantity = Math.max(1, Number(source.quantity || 1) || 1);
        var rows = [
            '<div><span>قیمت واحد</span><strong>' + text(money(source.unitPrice || item.price || 0)) + "</strong></div>",
            '<div><span>تعداد</span><strong>' + text(quantity.toLocaleString("fa-IR")) + "</strong></div>"
        ];
        if (quantity > 1 || Number(source.discountAmount || 0) > 0) {
            rows.push('<div><span>جمع قبل از تخفیف</span><strong>' + text(money(subtotal)) + "</strong></div>");
        }
        if ($("buy-discount-code") || Number(source.discountAmount || 0) > 0) {
            rows.push('<div><span>تخفیف</span><strong>' + text(money(source.discountAmount || 0)) + "</strong></div>");
        }
        rows.push('<div class="is-total"><span>مبلغ نهایی</span><strong>' + text(money(amount)) + "</strong></div>");
        root.innerHTML = rows.join("");
        if ($("buy-floating-price")) {
            $("buy-floating-price").textContent = money(amount);
        }
    }

    function openImageModal(url, title) {
        var modal = $("buy-image-modal");
        var img = $("buy-image-modal-img");
        if (!modal || !img || !url) return;
        img.src = url;
        img.alt = title || "تصویر آیتم";
        modal.hidden = false;
        document.body.classList.add("buy-modal-open");
    }

    function bindImageModal() {
        var modal = $("buy-image-modal");
        var close = $("buy-image-modal-close");
        if (!modal || modal.dataset.buyBound) return;
        modal.dataset.buyBound = "1";
        function closeModal() {
            modal.hidden = true;
            document.body.classList.remove("buy-modal-open");
        }
        if (close) {
            close.addEventListener("click", closeModal);
        }
        modal.addEventListener("click", function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });
        window.addEventListener("keydown", function (event) {
            if (event.key === "Escape" && !modal.hidden) {
                closeModal();
            }
        });
    }

    function renderGallery(item) {
        var heroNode = $("buy-item-hero");
        var galleryNode = $("buy-item-gallery");
        var gallery = imageList(item);
        var hero = gallery[0] || "";

        if (heroNode) {
            heroNode.innerHTML = hero
                ? '<img src="' + text(hero) + '" alt="' + text(item.title || "تصویر آیتم") + '">'
                : '<div class="buy-image-placeholder">تصویری ثبت نشده است</div>';
            heroNode.setAttribute("data-gallery-count", gallery.length ? gallery.length.toLocaleString("fa-IR") + " تصویر" : "");
            heroNode.dataset.currentImage = hero;
            if (!heroNode.dataset.buyBound) {
                heroNode.dataset.buyBound = "1";
                heroNode.addEventListener("click", function () {
                    openImageModal(heroNode.dataset.currentImage || "", item.title || "تصویر آیتم");
                });
            }
        }

        if (!galleryNode) {
            return;
        }
        if (!gallery.length) {
            galleryNode.innerHTML = "";
            return;
        }

        galleryNode.innerHTML = gallery.map(function (url, index) {
            return [
                '<button class="buy-gallery__item' + (index === 0 ? " is-active" : "") + '" type="button" data-buy-gallery-item="' + text(url) + '">',
                '  <img src="' + text(url) + '" alt="تصویر گالری">',
                "</button>"
            ].join("");
        }).join("");

        galleryNode.onclick = function (event) {
            var button = event.target.closest("[data-buy-gallery-item]");
            if (!button || !heroNode) {
                return;
            }
            var nextUrl = String(button.getAttribute("data-buy-gallery-item") || "").trim();
            if (!nextUrl) {
                return;
            }
            heroNode.innerHTML = '<img src="' + text(nextUrl) + '" alt="' + text(item.title || "تصویر آیتم") + '">';
            heroNode.dataset.currentImage = nextUrl;
            Array.prototype.slice.call(galleryNode.querySelectorAll("[data-buy-gallery-item]")).forEach(function (node) {
                node.classList.toggle("is-active", node === button);
            });
        };
    }

    function prefillFromAuth() {
        if (!window.Dent1402Auth || typeof window.Dent1402Auth.getState !== "function") {
            return;
        }
        var authState = window.Dent1402Auth.getState();
        var user = authState && authState.loggedIn ? authState.user : null;
        if (!user) {
            return;
        }
        var name = $("buy-payer-name");
        var studentNumber = $("buy-payer-student-number");
        if (name && !String(name.value || "").trim() && user.name) {
            name.value = user.name;
        }
        if (studentNumber && !String(studentNumber.value || "").trim() && user.studentNumber) {
            studentNumber.value = user.studentNumber;
        }
    }

    function updateSaveButton(slug) {
        var save = $("buy-save-item");
        if (!save) return;
        var saved = isSaved(slug);
        save.classList.toggle("is-saved", saved);
        save.textContent = saved ? "♥" : "♡";
    }

    function setFloatingBar(item, payable, submit, form) {
        var bar = $("buy-floating-bar");
        var price = $("buy-floating-price");
        var action = $("buy-floating-submit");
        if (!bar || !price || !action || !item) {
            return;
        }
        price.textContent = money((Number(item.price || 0) || 0) * itemQuantityFallback(1));
        bar.hidden = false;
        action.disabled = !payable || (submit && submit.disabled);
        action.textContent = payable ? "ادامه پرداخت" : "قابل پرداخت نیست";
        if (!action.dataset.buyBound) {
            action.dataset.buyBound = "1";
            action.addEventListener("click", function () {
                if (action.disabled) {
                    if (form) form.scrollIntoView({ behavior: "smooth", block: "center" });
                    return;
                }
                if (form && typeof form.requestSubmit === "function") {
                    form.requestSubmit();
                } else if (form) {
                    form.dispatchEvent(new Event("submit", { cancelable: true, bubbles: true }));
                }
            });
        }
    }

    function currentBuyPage() {
        return document.body && document.body.dataset ? String(document.body.dataset.buyPage || "") : "";
    }

    function renderCartDock(items) {
        var pageKey = currentBuyPage();
        if (pageKey === "cart" || pageKey === "result") {
            return;
        }
        var main = document.querySelector("main.buy-shell");
        if (!main) {
            return;
        }
        var dock = $("buy-cart-dock");
        if (!dock) {
            dock = document.createElement("aside");
            dock.id = "buy-cart-dock";
            dock.className = "buy-cart-dock";
            dock.setAttribute("aria-live", "polite");
            main.appendChild(dock);
        }

        var cart = cartItems();
        if (!cart.length) {
            dock.hidden = true;
            dock.innerHTML = "";
            return;
        }

        var snapshot = cartSnapshot(Array.isArray(items) ? items : state.items);
        var title = snapshot.quantity > 0
            ? snapshot.quantity.toLocaleString("fa-IR") + " عدد در سبد"
            : cart.length.toLocaleString("fa-IR") + " آیتم در سبد";
        var amount = snapshot.subtotal > 0 ? money(snapshot.subtotal) : "محاسبه در سبد";
        var note = snapshot.missing.length
            ? "برخی آیتم‌ها برای محاسبه نهایی باید در سبد بررسی شوند."
            : "جمع سبد تا مرحله پرداخت حفظ می‌شود.";

        dock.hidden = false;
        dock.innerHTML = [
            '<div class="buy-cart-dock__copy">',
            '  <span>' + text(title) + "</span>",
            '  <strong>' + text(amount) + "</strong>",
            '  <small>' + text(note) + "</small>",
            "</div>",
            '<a class="buy-primary-btn" href="/buy/cart/">مشاهده سبد و ادامه</a>'
        ].join("");
    }

    function refreshCartDockCatalog(fallbackItems) {
        var fallback = Array.isArray(fallbackItems) ? fallbackItems : state.items;
        renderCartDock(fallback);
        if (currentBuyPage() !== "item" || !cartItems().length) {
            return;
        }
        apiGet("listPublicItems", {}).then(function (payload) {
            if (payload && payload.success && Array.isArray(payload.items)) {
                state.items = payload.items;
                renderCartDock(state.items);
            }
        }).catch(function () {
            renderCartDock(fallback);
        });
    }

    function bindDetailActions(slug, item, feedback) {
        var share = $("buy-share-item");
        var save = $("buy-save-item");
        var report = $("buy-report-link");
        updateSaveButton(slug);

        if (share && !share.dataset.buyBound) {
            share.dataset.buyBound = "1";
            share.addEventListener("click", function () {
                shareUrl(window.location.href, item ? item.title : "آیتم خرید", share);
            });
        }

        if (save && !save.dataset.buyBound) {
            save.dataset.buyBound = "1";
            save.addEventListener("click", function () {
                var saved = toggleSaved(slug);
                save.classList.toggle("is-saved", saved);
                save.textContent = saved ? "♥" : "♡";
            });
        }

        if (report && !report.dataset.buyBound) {
            report.dataset.buyBound = "1";
            report.addEventListener("click", function () {
                setFeedback(feedback, "برای گزارش مشکل، عنوان آیتم و لینک سفارش را برای نماینده یا مالک سایت ارسال کنید.", "");
                if (feedback) {
                    feedback.scrollIntoView({ behavior: "smooth", block: "center" });
                }
            });
        }
    }

    function showBuyLoginRequired() {
        var main = document.querySelector("main.buy-shell");
        if (!main) {
            return;
        }
        var loginUrl = window.Dent1402Auth && typeof window.Dent1402Auth.loginUrl === "function"
            ? window.Dent1402Auth.loginUrl(window.location.pathname + window.location.search + window.location.hash)
            : "/account/";
        main.innerHTML = '<section class="buy-auth-required">' + window.Dent1402Auth.renderLoginRequiredGuard({
            loginHref: loginUrl,
            fallbackHref: "/buy/",
            primaryClass: "buy-primary-btn",
            secondaryClass: "buy-secondary-btn"
        }) + "</section>";
        window.Dent1402Auth.enhanceLoginGuards(main);
    }

    function startAfterAuth(callback) {
        var auth = window.Dent1402Auth;
        if (!auth || typeof auth.ready !== "function") {
            callback();
            return;
        }
        auth.ready().then(function (detail) {
            if (!detail || !detail.loggedIn) {
                showBuyLoginRequired();
                return;
            }
            callback();
        }).catch(function () {
            showBuyLoginRequired();
        });
    }

    function initListPage() {
        bindListControls();
        initOwnerEntry();
        apiGet("listPublicItems", {}).then(function (payload) {
            if (!payload || !payload.success) {
                state.items = [];
                renderList([]);
                return;
            }
            state.items = Array.isArray(payload.items) ? payload.items : [];
            renderList(state.items);
            renderCartDock(state.items);
        }).catch(function () {
            state.items = [];
            renderList([]);
            renderCartDock([]);
        });
    }

    function cartStep() {
        var allowed = ["cart", "discount", "details", "gateway"];
        var parts = String(window.location.pathname || "").split("/").filter(Boolean);
        var cartIndex = parts.indexOf("cart");
        var pathStep = cartIndex >= 0 && parts.length > cartIndex + 1 ? parts[cartIndex + 1] : "";
        var step = pathStep || readParam("step") || "cart";
        return allowed.indexOf(step) >= 0 ? step : "cart";
    }

    function cartStepUrl(step) {
        var clean = ["cart", "discount", "details", "gateway"].indexOf(step) >= 0 ? step : "cart";
        var url = new URL(window.location.href);
        url.pathname = clean === "cart" ? "/buy/cart/" : "/buy/cart/" + clean + "/";
        url.search = "";
        return url.toString();
    }

    function setCartStep(step) {
        var next = ["cart", "discount", "details", "gateway"].indexOf(step) >= 0 ? step : "cart";
        window.clearTimeout(cartQuoteTimer);
        window.history.pushState({ buyCartStep: next }, "", cartStepUrl(next));
        renderCartCheckout(state.items);
        window.scrollTo({ top: 0, behavior: "smooth" });
    }

    function cartSnapshot(items) {
        var source = Array.isArray(items) ? items : [];
        var bySlug = {};
        source.forEach(function (item) {
            bySlug[String(item.slug || "")] = item;
        });
        var valid = [];
        var missing = [];
        cartItems().forEach(function (entry) {
            var item = bySlug[entry.slug] || null;
            var quantity = Math.max(1, Math.min(99, Number(entry.quantity) || 1));
            if (!item) {
                missing.push(entry);
                return;
            }
            var lineAmount = (Number(item.price) || 0) * quantity;
            valid.push({
                slug: entry.slug,
                quantity: quantity,
                item: item,
                amount: lineAmount
            });
        });
        return {
            lines: valid,
            missing: missing,
            quantity: valid.reduce(function (sum, line) { return sum + line.quantity; }, 0),
            subtotal: valid.reduce(function (sum, line) { return sum + line.amount; }, 0)
        };
    }

    function cartItemsPayload() {
        return cartItems().map(function (entry) {
            return {
                slug: entry.slug,
                quantity: Math.max(1, Math.min(99, Number(entry.quantity) || 1))
            };
        });
    }

    function quoteSource(snapshot) {
        var quote = state.cartQuote && typeof state.cartQuote === "object" ? state.cartQuote : null;
        return quote || {
            lines: snapshot.lines.map(function (line) {
                return {
                    slug: line.slug,
                    title: line.item.title || "آیتم",
                    quantity: line.quantity,
                    unitPrice: line.item.price || 0,
                    subtotal: line.amount,
                    discountAmount: 0,
                    amount: line.amount,
                    requiredFields: Array.isArray(line.item.requiredFields) ? line.item.requiredFields : [],
                    item: line.item
                };
            }),
            quantity: snapshot.quantity,
            subtotal: snapshot.subtotal,
            discountAmount: 0,
            amount: snapshot.subtotal
        };
    }

    function cartStepsHtml(active) {
        var steps = [
            { key: "cart", label: "سبد", meta: "مرور اقلام" },
            { key: "discount", label: "تخفیف", meta: "محاسبه مبلغ" },
            { key: "details", label: "مشخصات", meta: "اطلاعات تحویل" },
            { key: "gateway", label: "پرداخت", meta: "انتخاب درگاه" }
        ];
        var activeIndex = steps.findIndex(function (step) { return step.key === active; });
        return [
            '<nav class="buy-checkout-steps" aria-label="مراحل پرداخت سبد">',
            steps.map(function (step, index) {
                var stateClass = index === activeIndex ? " is-active" : (index < activeIndex ? " is-done" : "");
                return [
                    '<button class="' + stateClass + '" type="button" data-buy-cart-step="' + text(step.key) + '">',
                    '  <span>' + text(Number(index + 1).toLocaleString("fa-IR")) + "</span>",
                    '  <div><strong>' + text(step.label) + "</strong><small>" + text(step.meta) + "</small></div>",
                    "</button>"
                ].join("");
            }).join(""),
            "</nav>"
        ].join("");
    }

    function cartSummaryHtml(snapshot) {
        var source = quoteSource(snapshot);
        var subtotal = source.subtotal == null ? (snapshot.subtotal || 0) : Number(source.subtotal || 0);
        var quantity = source.quantity == null ? (snapshot.quantity || 0) : Number(source.quantity || 0);
        var amount = source.amount == null ? (snapshot.subtotal || 0) : Number(source.amount || 0);
        return [
            '<div class="buy-cart-total">',
            '  <div class="buy-cart-total__head"><span>خلاصه سفارش</span><strong>' + text(Number(quantity).toLocaleString("fa-IR")) + " قلم</strong></div>",
            '  <div><span>جمع آیتم‌ها</span><strong>' + text(money(subtotal)) + "</strong></div>",
            '  <div><span>تخفیف</span><strong>' + text(money(source.discountAmount || 0)) + "</strong></div>",
            '  <div><span>تحویل</span><strong>هماهنگی دانشگاه</strong></div>',
            '  <div class="is-payable"><span>مبلغ قابل پرداخت</span><strong>' + text(money(amount)) + "</strong></div>",
            "</div>"
        ].join("");
    }

    function checkoutFeedbackHtml() {
        if (state.cartQuoteLoading) {
            return '<div class="buy-feedback">در حال محاسبه مبلغ سبد...</div>';
        }
        if (state.cartQuoteError) {
            return '<div class="buy-feedback is-error">' + text(state.cartQuoteError) + "</div>";
        }
        return '<div id="buy-cart-feedback" class="buy-feedback" aria-live="polite"></div>';
    }

    function refreshCartQuote(silent) {
        var snapshot = cartSnapshot(state.items);
        if (!snapshot.lines.length) {
            state.cartQuote = null;
            state.cartGateways = null;
            return Promise.resolve(null);
        }
        var data = readCheckoutData();
        state.cartQuoteLoading = true;
        if (!silent) {
            state.cartQuoteError = "";
            renderCartCheckout(state.items, { skipQuote: true });
        }
        return apiPost("quoteCart", {
            items: JSON.stringify(cartItemsPayload()),
            discountCode: data.discountCode || ""
        }).then(function (response) {
            state.cartQuoteLoading = false;
            if (!response || !response.success || !response.quote) {
                state.cartQuote = null;
                state.cartGateways = response && response.paymentGateways ? response.paymentGateways : state.cartGateways;
                state.cartQuoteError = (response && response.error) || "محاسبه مبلغ سبد انجام نشد.";
                renderCartCheckout(state.items, { skipQuote: true });
                return null;
            }
            state.cartQuote = response.quote;
            state.cartGateways = response.paymentGateways || state.cartGateways;
            state.cartQuoteError = "";
            renderCartCheckout(state.items, { skipQuote: true });
            return response.quote;
        }).catch(function () {
            state.cartQuoteLoading = false;
            state.cartQuote = null;
            state.cartQuoteError = "ارتباط با سرور برای محاسبه سبد برقرار نشد.";
            renderCartCheckout(state.items, { skipQuote: true });
            return null;
        });
    }

    function renderCart(items) {
        var root = $("buy-cart-root");
        var count = $("buy-cart-count");
        if (!root) return;
        var cart = cartItems();
        var snapshot = cartSnapshot(items);
        if (count) {
            count.textContent = cart.length
                ? cart.length.toLocaleString("fa-IR") + " آیتم، " + snapshot.quantity.toLocaleString("fa-IR") + " عدد"
                : "سبد خالی است";
        }
        if (!cart.length) {
            root.innerHTML = '<div class="buy-empty">سبد خرید خالی است. از کاتالوگ، آیتم موردنظر را اضافه کنید.</div>';
            var checkoutRoot = $("buy-cart-checkout");
            if (checkoutRoot) {
                checkoutRoot.innerHTML = "";
            }
            return;
        }
        var bySlug = {};
        (Array.isArray(items) ? items : []).forEach(function (item) {
            bySlug[String(item.slug || "")] = item;
        });
        root.innerHTML = cart.map(function (entry) {
            var item = bySlug[entry.slug] || null;
            var href = "/buy/item/?slug=" + encodeURIComponent(entry.slug) + "&qty=" + encodeURIComponent(String(entry.quantity || 1));
            if (!item) {
                return [
                    '<article class="buy-cart-card">',
                    '  <div><strong>آیتم در دسترس نیست</strong><p dir="ltr">' + text(entry.slug) + "</p></div>",
                    '  <button class="buy-secondary-btn" type="button" data-buy-cart-remove="' + text(entry.slug) + '">حذف</button>',
                    "</article>"
                ].join("");
            }
            var image = imageList(item)[0] || "";
            var lineAmount = (Number(item.price) || 0) * (Number(entry.quantity) || 1);
            return [
                '<article class="buy-cart-card">',
                '  <a class="buy-cart-card__media" href="' + href + '">',
                image ? '<img src="' + text(image) + '" alt="' + text(item.title || "تصویر آیتم") + '">' : '<span>بدون تصویر</span>',
                "  </a>",
                '  <div class="buy-cart-card__body">',
                '    <span class="buy-kicker">' + text(item.categoryLabel || categoryLabel(itemCategory(item))) + "</span>",
                '    <h3><a href="' + href + '">' + text(item.title || "بدون عنوان") + "</a></h3>",
                '    <p>' + text(item.shortDescription || "—") + "</p>",
                '    <strong>' + text(money(lineAmount)) + "</strong>",
                '    <div class="buy-cart-quantity" aria-label="تعداد">',
                '      <button type="button" data-buy-cart-qty="' + text(entry.slug) + '" data-buy-cart-next-qty="' + text(String(Number(entry.quantity || 1) - 1)) + '">−</button>',
                '      <span>' + text(Number(entry.quantity || 1).toLocaleString("fa-IR")) + "</span>",
                '      <button type="button" data-buy-cart-qty="' + text(entry.slug) + '" data-buy-cart-next-qty="' + text(String(Math.min(99, Number(entry.quantity || 1) + 1))) + '">＋</button>',
                "    </div>",
                '    <div class="buy-cart-card__actions">',
                '      <a class="buy-secondary-btn" href="' + href + '">مشاهده آیتم</a>',
                '      <button class="buy-secondary-btn" type="button" data-buy-cart-remove="' + text(entry.slug) + '">حذف از سبد</button>',
                "    </div>",
                "  </div>",
                "</article>"
            ].join("");
        }).join("");
        renderCartCheckout(items);
    }

    function renderCartLineFields(lines, checkoutData) {
        var data = checkoutData || readCheckoutData();
        var extraBySlug = data.lineExtraFormData && typeof data.lineExtraFormData === "object" ? data.lineExtraFormData : {};
        var rows = [];
        (Array.isArray(lines) ? lines : []).forEach(function (line) {
            var fields = Array.isArray(line.requiredFields) ? line.requiredFields : [];
            if (!fields.length) {
                return;
            }
            rows.push('<section class="buy-cart-line-fields"><h4>' + text(line.title || "آیتم") + "</h4>");
            fields.forEach(function (field) {
                var name = String(field.name || "").trim();
                if (!name) {
                    return;
                }
                var label = String(field.label || name);
                var required = !!field.required;
                var maxLength = Math.max(10, Math.min(1000, Number(field.maxLength || 140)));
                var value = String((extraBySlug[line.slug] && extraBySlug[line.slug][name]) || "");
                var attrs = [
                    'data-cart-line-field="true"',
                    'data-slug="' + text(line.slug || "") + '"',
                    'data-field-name="' + text(name) + '"',
                    'data-field-label="' + text(label) + '"',
                    'data-field-required="' + (required ? "1" : "0") + '"'
                ];
                var type = String(field.type || "text");
                var control = "";
                if (type === "textarea") {
                    control = '<textarea ' + attrs.join(" ") + ' maxlength="' + text(String(maxLength)) + '">' + text(value) + "</textarea>";
                } else if (type === "select" && Array.isArray(field.options)) {
                    var options = ['<option value="">انتخاب کنید</option>'];
                    field.options.forEach(function (option) {
                        var selected = String(option) === value ? " selected" : "";
                        options.push('<option value="' + text(option) + '"' + selected + ">" + text(option) + "</option>");
                    });
                    control = '<select ' + attrs.join(" ") + ">" + options.join("") + "</select>";
                } else {
                    var inputType = type === "tel" ? "tel" : (type === "number" ? "number" : "text");
                    var digitAttrs = (inputType === "tel" || inputType === "number") ? ' inputmode="numeric" data-digit-locale="latin"' : "";
                    control = '<input ' + attrs.join(" ") + ' type="' + text(inputType) + '"' + digitAttrs + ' maxlength="' + text(String(maxLength)) + '" value="' + text(value) + '">';
                }
                rows.push([
                    '<label class="buy-form__field">',
                    '  <span>' + text(label) + (required ? " *" : "") + "</span>",
                    control,
                    "</label>"
                ].join(""));
            });
            rows.push("</section>");
        });
        return rows.join("");
    }

    function readLineExtrasFrom(root) {
        var output = {};
        Array.prototype.slice.call(root ? root.querySelectorAll("[data-cart-line-field='true']") : []).forEach(function (field) {
            var slug = String(field.dataset.slug || "").trim();
            var name = String(field.dataset.fieldName || "").trim();
            if (!slug || !name) {
                return;
            }
            if (!output[slug]) {
                output[slug] = {};
            }
            output[slug][name] = String(field.value || "").trim();
        });
        return output;
    }

    function renderCartGateways(bundle) {
        var normalized = normalizeGatewayBundle(bundle || {});
        var enabledCount = normalized.gateways.filter(function (entry) { return entry.isEnabled; }).length;
        if (!normalized.gateways.length) {
            return '<div class="buy-empty">درگاه پرداختی برای این سبد تعریف نشده است.</div>';
        }
        var selectedOnce = false;
        var firstEnabled = normalized.gateways.find(function (entry) { return entry.isEnabled; });
        var fallbackKey = firstEnabled ? firstEnabled.key : "";
        var savedGateway = String(readCheckoutData().gateway || "").trim().toLowerCase();
        return [
            '<div id="buy-cart-gateway-options" class="buy-gateway__list">',
            normalized.gateways.map(function (entry, index) {
                var inputId = "buy-cart-gateway-" + entry.key + "-" + String(index);
                var disabled = !entry.isEnabled;
                var checked = false;
                if (!disabled && !selectedOnce && savedGateway && entry.key === savedGateway) {
                    checked = true;
                    selectedOnce = true;
                } else if (!disabled && !selectedOnce && normalized.defaultKey && entry.key === normalized.defaultKey) {
                    checked = true;
                    selectedOnce = true;
                } else if (!disabled && !selectedOnce && (!normalized.defaultKey || normalized.defaultKey !== fallbackKey) && entry.key === fallbackKey) {
                    checked = true;
                    selectedOnce = true;
                }
                return [
                    '<label class="buy-gateway-option' + (disabled ? " is-disabled" : "") + '" for="' + text(inputId) + '">',
                    '  <input id="' + text(inputId) + '" type="radio" name="buy_cart_gateway" value="' + text(entry.key) + '"' + (checked ? " checked" : "") + (disabled ? " disabled" : "") + ">",
                    '  <span class="buy-gateway-option__radio" aria-hidden="true"></span>',
                    '  <span class="buy-gateway-option__copy">',
                    '    <strong class="buy-gateway-option__title">' + text(entry.label || "پرداخت آنلاین") + "</strong>",
                    '    <small class="buy-gateway-option__meta">' + text(entry.provider || gatewayProviderFallback(entry.key)) + (disabled ? " (غیرفعال)" : "") + "</small>",
                    "  </span>",
                    '  <span class="buy-gateway-option__badge">' + text(entry.icon || entry.key.toUpperCase()) + "</span>",
                    "</label>"
                ].join("");
            }).join(""),
            '<div class="buy-alert">پس از پرداخت، روی اتمام پرداخت بزنید و به همین سایت برگردید تا پیام تایید را ببینید.</div>',
            enabledCount <= 0 ? '<div class="buy-feedback is-error">درگاه فعالی برای پرداخت وجود ندارد.</div>' : "",
            "</div>"
        ].join("");
    }

    function renderCartCheckout(items, options) {
        var root = $("buy-cart-checkout");
        if (!root) {
            return;
        }
        var opts = options || {};
        var snapshot = cartSnapshot(items);
        if (!snapshot.lines.length) {
            root.innerHTML = snapshot.missing.length
                ? '<div class="buy-empty">آیتم‌های موجود در سبد دیگر قابل سفارش نیستند. آن‌ها را حذف کنید و دوباره از کاتالوگ اضافه کنید.</div>'
                : "";
            return;
        }

        var active = cartStep();
        var data = readCheckoutData();
        var quote = quoteSource(snapshot);
        var stepBody = "";
        if (active === "cart") {
            stepBody = [
                '<section class="buy-cart-step">',
                '  <div class="buy-cart-step__head"><span class="buy-kicker">مرحله اول</span><h3>مرور سبد خرید</h3><p class="buy-muted">اقلام، تعداد و مبلغ اولیه را قبل از ورود به اطلاعات پرداخت بررسی کنید.</p></div>',
                cartSummaryHtml(snapshot),
                '  <div class="buy-cart-step-actions">',
                '    <button class="buy-primary-btn" type="button" data-buy-cart-step="discount">ادامه به کد تخفیف</button>',
                '    <a class="buy-secondary-btn" href="/buy/">افزودن آیتم بیشتر</a>',
                "  </div>",
                "</section>"
            ].join("");
        } else if (active === "discount") {
            stepBody = [
                '<form id="buy-cart-discount-form" class="buy-cart-step buy-form" novalidate>',
                '  <div class="buy-cart-step__head"><span class="buy-kicker">مرحله دوم</span><h3>کد تخفیف سبد</h3><p class="buy-muted">اگر کد تخفیف برای یک یا چند آیتم معتبر باشد، مبلغ کل همینجا دوباره محاسبه می‌شود.</p></div>',
                '  <label class="buy-form__field buy-form__field--discount"><span>کد تخفیف</span><span class="buy-discount-apply-row"><input id="buy-cart-discount-code" type="text" maxlength="40" autocomplete="off" dir="ltr" data-digit-locale="latin" value="' + text(data.discountCode || "") + '" placeholder="اختیاری"><button id="buy-cart-discount-apply" class="buy-secondary-btn" type="button">اعمال</button></span></label>',
                cartSummaryHtml(snapshot),
                checkoutFeedbackHtml(),
                '  <div class="buy-cart-step-actions">',
                '    <button class="buy-secondary-btn" type="button" data-buy-cart-step="cart">بازگشت به سبد</button>',
                '    <button class="buy-primary-btn" type="submit">ادامه به مشخصات</button>',
                "  </div>",
                "</form>"
            ].join("");
        } else if (active === "details") {
            var authData = readCheckoutData();
            if (window.Dent1402Auth && typeof window.Dent1402Auth.getState === "function") {
                var authState = window.Dent1402Auth.getState();
                var user = authState && authState.loggedIn ? authState.user : null;
                if (user && !authData.payerName && user.name) authData.payerName = user.name;
                if (user && !authData.payerStudentNumber && user.studentNumber) authData.payerStudentNumber = user.studentNumber;
            }
            stepBody = [
                '<form id="buy-cart-details-form" class="buy-cart-step buy-form" novalidate>',
                '  <div class="buy-cart-step__head"><span class="buy-kicker">مرحله سوم</span><h3>مشخصات پرداخت‌کننده</h3><p class="buy-muted">این اطلاعات برای کل سفارش ثبت می‌شود و تحویل در محدوده اعلام‌شده دانشگاه هماهنگ خواهد شد.</p></div>',
                '  <label class="buy-form__field"><span>نام و نام خانوادگی *</span><input id="buy-cart-payer-name" type="text" maxlength="120" autocomplete="name" value="' + text(authData.payerName || "") + '"></label>',
                '  <label class="buy-form__field"><span>شماره موبایل *</span><input id="buy-cart-payer-phone" type="tel" inputmode="numeric" maxlength="14" autocomplete="tel" dir="ltr" data-digit-locale="latin" value="' + text(authData.payerPhone || "") + '" placeholder="09xxxxxxxxx"></label>',
                '  <label class="buy-form__field"><span>شماره دانشجویی</span><input id="buy-cart-payer-student-number" type="text" inputmode="numeric" maxlength="20" autocomplete="off" dir="ltr" data-digit-locale="latin" value="' + text(authData.payerStudentNumber || "") + '"></label>',
                renderCartLineFields(quote.lines || [], authData),
                cartSummaryHtml(snapshot),
                checkoutFeedbackHtml(),
                '  <div class="buy-cart-step-actions">',
                '    <button class="buy-secondary-btn" type="button" data-buy-cart-step="discount">بازگشت به تخفیف</button>',
                '    <button class="buy-primary-btn" type="submit">ادامه به انتخاب درگاه</button>',
                "  </div>",
                "</form>"
            ].join("");
        } else {
            stepBody = [
                '<form id="buy-cart-gateway-form" class="buy-cart-step buy-form" novalidate>',
                '  <div class="buy-cart-step__head"><span class="buy-kicker">مرحله چهارم</span><h3>انتخاب درگاه پرداخت</h3><p class="buy-muted">درگاه فقط در این مرحله انتخاب می‌شود و مبلغ قابل پرداخت همان جمع نهایی سبد است.</p></div>',
                cartSummaryHtml(snapshot),
                renderCartGateways(state.cartGateways || {}),
                checkoutFeedbackHtml(),
                '  <div class="buy-cart-step-actions">',
                '    <button class="buy-secondary-btn" type="button" data-buy-cart-step="details">بازگشت به مشخصات</button>',
                '    <button id="buy-cart-pay-submit" class="buy-primary-btn" type="submit">پرداخت مبلغ کل سبد</button>',
                "  </div>",
                "</form>"
            ].join("");
        }

        root.innerHTML = cartStepsHtml(active) + stepBody;
        if (active !== "cart" && !opts.skipQuote && (active === "discount" || !state.cartQuote)) {
            refreshCartQuote(true);
        }
    }

    function submitCartOrder(form) {
        var feedback = $("buy-cart-feedback");
        var data = readCheckoutData();
        var selected = form.querySelector("input[name='buy_cart_gateway']:checked");
        if (!selected) {
            setFeedback(feedback, "لطفا روش پرداخت را انتخاب کنید.", "is-error");
            return;
        }
        updateCheckoutData({ gateway: String(selected.value || "").trim() });
        if (!data.payerName || !normalizePhone(data.payerPhone)) {
            setFeedback(feedback, "ابتدا مشخصات پرداخت‌کننده را کامل کنید.", "is-error");
            setCartStep("details");
            return;
        }
        var submit = $("buy-cart-pay-submit");
        if (submit) {
            submit.disabled = true;
            submit.textContent = "در حال انتقال به درگاه...";
        }
        setFeedback(feedback, "در حال ایجاد سفارش سبد خرید...", "");
        apiPost("createCartOrder", {
            items: JSON.stringify(cartItemsPayload()),
            discountCode: data.discountCode || "",
            payerName: data.payerName,
            payerPhone: normalizePhone(data.payerPhone),
            payerStudentNumber: normalizeDigits(data.payerStudentNumber || "").replace(/\D+/g, ""),
            lineExtraFormData: JSON.stringify(data.lineExtraFormData || {}),
            extraFormData: JSON.stringify({ source: "cart" }),
            gateway: String(selected.value || "").trim()
        }).then(function (response) {
            if (!response || !response.success || !response.redirectUrl) {
                if (submit) {
                    submit.disabled = false;
                    submit.textContent = "پرداخت مبلغ کل سبد";
                }
                setFeedback(feedback, (response && response.error) || "ایجاد سفارش سبد خرید انجام نشد.", "is-error");
                return;
            }
            window.location.href = response.redirectUrl;
        }).catch(function () {
            if (submit) {
                submit.disabled = false;
                submit.textContent = "پرداخت مبلغ کل سبد";
            }
            setFeedback(feedback, "ارتباط با سرور برقرار نشد.", "is-error");
        });
    }

    function initCartPage() {
        var root = $("buy-cart-root");
        if (root && !root.dataset.buyBound) {
            root.dataset.buyBound = "1";
            root.addEventListener("click", function (event) {
                var remove = event.target.closest("[data-buy-cart-remove]");
                if (remove) {
                    removeFromCart(remove.getAttribute("data-buy-cart-remove"));
                    state.cartQuote = null;
                    renderCart(state.items);
                    return;
                }

                var qtyButton = event.target.closest("[data-buy-cart-qty]");
                if (!qtyButton) return;
                updateCartQuantity(
                    qtyButton.getAttribute("data-buy-cart-qty"),
                    qtyButton.getAttribute("data-buy-cart-next-qty")
                );
                state.cartQuote = null;
                renderCart(state.items);
            });
        }
        var checkout = $("buy-cart-checkout");
        if (checkout && !checkout.dataset.buyBound) {
            checkout.dataset.buyBound = "1";
            checkout.addEventListener("click", function (event) {
                var discountApply = event.target.closest("#buy-cart-discount-apply");
                if (discountApply) {
                    window.clearTimeout(cartQuoteTimer);
                    updateCheckoutData({ discountCode: $("buy-cart-discount-code") ? $("buy-cart-discount-code").value.trim() : "" });
                    refreshCartQuote(false);
                    return;
                }

                var stepButton = event.target.closest("[data-buy-cart-step]");
                if (!stepButton) {
                    return;
                }
                setCartStep(stepButton.getAttribute("data-buy-cart-step"));
            });
            checkout.addEventListener("input", function (event) {
                if (event.target && event.target.id === "buy-cart-discount-code") {
                    state.cartQuoteError = "";
                    var feedback = $("buy-cart-feedback");
                    if (feedback) {
                        setFeedback(feedback, "برای محاسبه مبلغ، دکمه اعمال را بزنید.", "");
                    }
                    return;
                }
                if (event.target && event.target.matches("[data-cart-line-field='true']")) {
                    var existing = readCheckoutData();
                    updateCheckoutData({
                        lineExtraFormData: Object.assign({}, existing.lineExtraFormData || {}, readLineExtrasFrom(checkout))
                    });
                    return;
                }
                if (!event.target || !event.target.id) {
                    return;
                }
                if (event.target.name === "buy_cart_gateway") {
                    updateCheckoutData({ gateway: String(event.target.value || "").trim() });
                    return;
                }
                if (event.target.id === "buy-cart-payer-name" || event.target.id === "buy-cart-payer-phone" || event.target.id === "buy-cart-payer-student-number") {
                    updateCheckoutData({
                        payerName: $("buy-cart-payer-name") ? $("buy-cart-payer-name").value : readCheckoutData().payerName,
                        payerPhone: $("buy-cart-payer-phone") ? $("buy-cart-payer-phone").value : readCheckoutData().payerPhone,
                        payerStudentNumber: $("buy-cart-payer-student-number") ? $("buy-cart-payer-student-number").value : readCheckoutData().payerStudentNumber
                    });
                }
            });
            checkout.addEventListener("submit", function (event) {
                var form = event.target;
                if (!form) {
                    return;
                }
                if (form.id === "buy-cart-discount-form") {
                    event.preventDefault();
                    window.clearTimeout(cartQuoteTimer);
                    updateCheckoutData({ discountCode: $("buy-cart-discount-code") ? $("buy-cart-discount-code").value.trim() : "" });
                    refreshCartQuote(false).then(function (quote) {
                        if (quote || !state.cartQuoteError) {
                            setCartStep("details");
                        }
                    });
                    return;
                }
                if (form.id === "buy-cart-details-form") {
                    event.preventDefault();
                    var payerName = String(($("buy-cart-payer-name") && $("buy-cart-payer-name").value) || "").trim();
                    var payerPhone = normalizePhone(String(($("buy-cart-payer-phone") && $("buy-cart-payer-phone").value) || ""));
                    var payerStudentNumber = normalizeDigits(String(($("buy-cart-payer-student-number") && $("buy-cart-payer-student-number").value) || "")).replace(/\D+/g, "");
                    if (!payerName) {
                        setFeedback($("buy-cart-feedback"), "نام پرداخت‌کننده الزامی است.", "is-error");
                        return;
                    }
                    if (!payerPhone || payerPhone.length < 10) {
                        setFeedback($("buy-cart-feedback"), "شماره موبایل معتبر وارد کنید.", "is-error");
                        return;
                    }
                    var valid = true;
                    Array.prototype.slice.call(form.querySelectorAll("[data-cart-line-field='true']")).forEach(function (field) {
                        if (!valid) {
                            return;
                        }
                        var required = String(field.dataset.fieldRequired || "0") === "1";
                        var label = String(field.dataset.fieldLabel || "فیلد");
                        if (required && !String(field.value || "").trim()) {
                            setFeedback($("buy-cart-feedback"), "فیلد «" + label + "» الزامی است.", "is-error");
                            valid = false;
                        }
                    });
                    if (!valid) {
                        return;
                    }
                    updateCheckoutData({
                        payerName: payerName,
                        payerPhone: payerPhone,
                        payerStudentNumber: payerStudentNumber,
                        lineExtraFormData: readLineExtrasFrom(form)
                    });
                    setCartStep("gateway");
                    return;
                }
                if (form.id === "buy-cart-gateway-form") {
                    event.preventDefault();
                    submitCartOrder(form);
                }
            });
        }
        window.addEventListener("popstate", function () {
            renderCartCheckout(state.items);
        });
        apiGet("listPublicItems", {}).then(function (payload) {
            state.items = payload && payload.success && Array.isArray(payload.items) ? payload.items : [];
            renderCart(state.items);
        }).catch(function () {
            state.items = [];
            renderCart([]);
        });
    }

    function initItemPage() {
        var slug = readSlugFromLocation();
        var titleNode = $("buy-item-title");
        var shortNode = $("buy-item-short");
        var fullNode = $("buy-item-full");
        var priceNode = $("buy-item-price");
        var stateNode = $("buy-item-state");
        var capacityNode = $("buy-item-capacity");
        var audienceNode = $("buy-item-audience");
        var deliveryNode = $("buy-item-delivery");
        var timeNode = $("buy-item-time");
        var categoryNode = $("buy-item-category-label");
        var form = $("buy-order-form");
        var submit = $("buy-order-submit");
        var addCartButton = $("buy-add-to-cart");
        var quantityInput = $("buy-order-quantity");
        var discountInput = $("buy-discount-code");
        var feedback = $("buy-order-feedback");
        var gatewaySelection = null;
        var quoteState = null;
        var quoteTimer = null;

        function showItemLoadError(title, message) {
            var detail = document.querySelector(".buy-detail");
            if (detail) {
                detail.classList.add("is-error-state");
            }
            if (titleNode) titleNode.textContent = title;
            if (shortNode) shortNode.textContent = message;
            if (form) form.hidden = true;
            var hero = $("buy-item-hero");
            if (hero) {
                hero.innerHTML = '<div class="buy-image-placeholder">اطلاعات آیتم در دسترس نیست.</div>';
                hero.setAttribute("data-gallery-count", "");
            }
            [
                "buy-item-gallery",
                "buy-rating-line",
                "buy-item-time",
                "buy-item-specs",
                "buy-item-policy",
                "buy-item-reviews"
            ].forEach(function (id) {
                var node = $(id);
                if (node) node.innerHTML = "";
            });
            Array.prototype.slice.call(document.querySelectorAll(".buy-meta-grid, .buy-safety, .buy-copy-block, .buy-report-block")).forEach(function (node) {
                node.hidden = true;
            });
        }

        if (!slug) {
            showItemLoadError("آیتم سفارش پیدا نشد", "لینک آیتم معتبر نیست.");
            return;
        }

        apiGet("publicItem", { slug: slug }).then(function (payload) {
            if (!payload || !payload.success || !payload.item) {
                showItemLoadError("آیتم سفارش پیدا نشد", (payload && payload.error) || "لینک آیتم معتبر نیست.");
                bindDetailActions(slug, null, feedback);
                return;
            }

            var item = payload.item;
            state.currentItem = item;
            refreshCartDockCatalog([item]);
            var itemState = item.state || {};
            var payable = !!itemState.isPayable;
            var maxQuantity = Math.max(1, Math.min(Number(item.maxQuantityPerOrder || 1) || 1, Number(item.remainingCapacity || item.maxQuantityPerOrder || 1) || 1));

            function readQuantity() {
                var value = normalizeDigits(quantityInput ? quantityInput.value : "1").replace(/\D+/g, "");
                var parsed = Math.max(1, Math.min(maxQuantity, Number(value) || 1));
                if (quantityInput && String(quantityInput.value || "") !== String(parsed)) {
                    quantityInput.value = String(parsed);
                }
                return parsed;
            }

            function readDiscountCode() {
                return String(discountInput && discountInput.value || "").trim();
            }

            function refreshQuote(silent) {
                var quantity = readQuantity();
                var discountCode = readDiscountCode();
                quoteState = localItemQuote(item, quantity);
                renderPriceBreakdown(quoteState, item);
                if (!payable) {
                    return;
                }
                apiPost("quoteOrder", {
                    slug: item.slug,
                    quantity: quantity,
                    discountCode: discountCode
                }).then(function (response) {
                    if (!response || !response.success || !response.quote) {
                        if (!silent && discountCode) {
                            setFeedback(feedback, (response && response.error) || "محاسبه کد تخفیف انجام نشد.", "is-error");
                        }
                        quoteState = localItemQuote(item, quantity);
                        renderPriceBreakdown(quoteState, item);
                        return;
                    }
                    quoteState = response.quote;
                    renderPriceBreakdown(quoteState, item);
                    if (!silent && discountCode && response.quote.discountApplied) {
                        setFeedback(feedback, "کد تخفیف روی سفارش اعمال شد.", "is-success");
                    }
                }).catch(function () {
                    quoteState = localItemQuote(item, quantity);
                    renderPriceBreakdown(quoteState, item);
                    if (!silent) {
                        setFeedback(feedback, "محاسبه مبلغ نهایی انجام نشد.", "is-error");
                    }
                });
            }

            function scheduleQuote() {
                window.clearTimeout(quoteTimer);
                quoteTimer = window.setTimeout(function () {
                    refreshQuote(false);
                }, 360);
            }

            function updateLocalQuoteAndSchedule() {
                quoteState = localItemQuote(item, readQuantity());
                renderPriceBreakdown(quoteState, item);
                scheduleQuote();
            }

            if (titleNode) titleNode.textContent = item.title || "آیتم سفارش";
            if (shortNode) shortNode.textContent = item.shortDescription || "—";
            if (fullNode) fullNode.textContent = item.fullDescription || "—";
            if (priceNode) priceNode.textContent = money(item.price || 0);
            if (categoryNode) categoryNode.textContent = item.categoryLabel || categoryLabel(itemCategory(item));
            if (stateNode) {
                stateNode.textContent = itemState.label || "نامشخص";
                stateNode.className = "buy-status " + statusClass(itemState.key || item.status);
            }
            if (capacityNode) {
                capacityNode.textContent = item.capacity == null
                    ? "بدون محدودیت ظرفیت"
                    : ("کل " + Number(item.capacity || 0).toLocaleString("fa-IR") + " • باقی‌مانده " + Number(item.remainingCapacity || 0).toLocaleString("fa-IR"));
            }
            if (audienceNode) audienceNode.textContent = item.audienceNote || "دانشجویان دندانپزشکی ورودی ۱۴۰۲";
            if (deliveryNode) deliveryNode.textContent = item.deliveryNote || "دانشگاه علوم پزشکی تهران";
            if (quantityInput) {
                quantityInput.max = String(maxQuantity);
                quantityInput.value = String(Math.min(maxQuantity, Number(readParam("qty") || "1") || 1));
                quantityInput.addEventListener("input", updateLocalQuoteAndSchedule);
                quantityInput.addEventListener("change", updateLocalQuoteAndSchedule);
            }
            if (discountInput) {
                // Do not auto-apply discount on every keystroke — require explicit apply button
                discountInput.addEventListener("input", function () { setFeedback(feedback, ""); renderPriceBreakdown(localItemQuote(item, readQuantity()), item); });
                discountInput.addEventListener("change", function () { setFeedback(feedback, ""); });

                var applyBtn = document.createElement('button');
                applyBtn.type = 'button';
                applyBtn.id = 'buy-discount-apply';
                applyBtn.className = 'buy-secondary-btn';
                applyBtn.textContent = 'اعمال';
                if (discountInput.parentNode) {
                    discountInput.parentNode.insertBefore(applyBtn, discountInput.nextSibling);
                }
                applyBtn.addEventListener('click', function () {
                    window.clearTimeout(quoteTimer);
                    refreshQuote(false);
                });

                if (!item.hasDiscountCodes) {
                    discountInput.placeholder = "کد فعالی تعریف نشده";
                    applyBtn.disabled = true;
                }
            }
            if (timeNode) {
                var timeMeta = [];
                if (item.startsAt) {
                    timeMeta.push("شروع " + formatDateTime(item.startsAt, "—"));
                }
                if (item.expiresAt) {
                    timeMeta.push("پایان " + formatDateTime(item.expiresAt, "—"));
                }
                timeNode.textContent = timeMeta.length ? timeMeta.join(" • ") : "برای این آیتم بازه زمانی ثبت نشده است";
            }

            renderGallery(item);
            renderSpecifications(item.specifications || []);
            renderPolicy(item);
            renderReviews(item);
            renderRequiredFields(item.requiredFields || []);
            renderPriceBreakdown(null, item);
            refreshQuote(true);
            gatewaySelection = renderGatewayOptions(item.paymentGateways || {}, form, submit);
            bindDetailActions(slug, item, feedback);
            bindImageModal();
            prefillFromAuth();

            if (submit) {
                var hasGateway = !!(gatewaySelection && gatewaySelection.hasEnabled);
                submit.disabled = !payable || !hasGateway;
                if (!payable) {
                    submit.textContent = "در حال حاضر قابل پرداخت نیست";
                } else if (!hasGateway) {
                    submit.textContent = "درگاه فعالی موجود نیست";
                } else if (gatewaySelection && typeof gatewaySelection.refreshSubmitText === "function") {
                    gatewaySelection.refreshSubmitText();
                } else {
                    submit.textContent = "پرداخت آنلاین";
                }
            }
            setFeedback(feedback, payable ? "" : (item.statusMessage || "این آیتم در حال حاضر قابل پرداخت نیست."), payable ? "" : "is-error");
            setFloatingBar(item, payable, submit, form);

            if (addCartButton && !addCartButton.dataset.buyBound) {
                addCartButton.dataset.buyBound = "1";
                addCartButton.addEventListener("click", function () {
                    addToCart(item.slug, readQuantity());
                    refreshCartDockCatalog([item]);
                    setFeedback(feedback, "آیتم به سبد خرید اضافه شد. ادامه پرداخت از سبد انجام می‌شود.", "is-success");
                });
            }

            if (!form) {
                return;
            }

            form.onsubmit = function (event) {
                event.preventDefault();
                if (!payable) {
                    return;
                }

                if (String(form.dataset.buyItemRouteCart || "") === "true") {
                    addToCart(item.slug, readQuantity(), true);
                    refreshCartDockCatalog([item]);
                    setFeedback(feedback, "آیتم به سبد اضافه شد. در حال انتقال به پرداخت مرحله‌ای...", "is-success");
                    window.setTimeout(function () {
                        window.location.href = cartStepUrl("discount");
                    }, 120);
                    return;
                }

                var payerName = String(($("buy-payer-name") && $("buy-payer-name").value) || "").trim();
                var payerPhone = normalizePhone(String(($("buy-payer-phone") && $("buy-payer-phone").value) || ""));
                var payerStudentNumber = normalizeDigits(String(($("buy-payer-student-number") && $("buy-payer-student-number").value) || "")).replace(/\D+/g, "");

                if (!payerName) {
                    setFeedback(feedback, "نام پرداخت‌کننده الزامی است.", "is-error");
                    return;
                }
                if (!payerPhone || payerPhone.length < 10) {
                    setFeedback(feedback, "شماره موبایل معتبر وارد کنید.", "is-error");
                    return;
                }

                var extraData = {};
                var valid = true;
                Array.prototype.slice.call(form.querySelectorAll("[data-extra-field='true']")).forEach(function (field) {
                    var key = String(field.dataset.fieldName || "").trim();
                    var required = String(field.dataset.fieldRequired || "0") === "1";
                    var label = String(field.dataset.fieldLabel || key || "فیلد");
                    if (!key) {
                        return;
                    }
                    var value = String(field.value || "").trim();
                    if (required && !value && valid) {
                        setFeedback(feedback, "فیلد «" + label + "» الزامی است.", "is-error");
                        valid = false;
                        return;
                    }
                    extraData[key] = value;
                });

                if (!valid) {
                    return;
                }

                var selectedGateway = gatewaySelection && typeof gatewaySelection.getSelected === "function"
                    ? gatewaySelection.getSelected()
                    : "";
                if (!selectedGateway) {
                    setFeedback(feedback, "لطفا روش پرداخت را انتخاب کنید.", "is-error");
                    return;
                }

                if (submit) submit.disabled = true;
                setFloatingBar(item, payable, submit, form);
                setFeedback(feedback, "در حال انتقال به درگاه پرداخت...", "");

                apiPost("createOrder", {
                    slug: item.slug,
                    quantity: readQuantity(),
                    discountCode: readDiscountCode(),
                    payerName: payerName,
                    payerPhone: payerPhone,
                    payerStudentNumber: payerStudentNumber,
                    extraFormData: JSON.stringify(extraData),
                    gateway: selectedGateway
                }).then(function (response) {
                    if (!response || !response.success || !response.redirectUrl) {
                        if (submit) submit.disabled = false;
                        setFloatingBar(item, payable, submit, form);
                        setFeedback(feedback, (response && response.error) || "ایجاد درخواست پرداخت انجام نشد.", "is-error");
                        return;
                    }
                    window.location.href = response.redirectUrl;
                }).catch(function () {
                    if (submit) submit.disabled = false;
                    setFloatingBar(item, payable, submit, form);
                    setFeedback(feedback, "ارتباط با سرور برقرار نشد.", "is-error");
                });
            };
        }).catch(function () {
            showItemLoadError("دریافت آیتم سفارش انجام نشد", "ارتباط با سرور برقرار نشد.");
            bindDetailActions(slug, null, feedback);
        });
    }

    function bindResultActions(order) {
        var actions = $("buy-result-actions");
        if (!actions || actions.dataset.buyBound) {
            return;
        }
        actions.dataset.buyBound = "1";
        actions.addEventListener("click", function (event) {
            var button = event.target.closest("[data-copy-result]");
            if (!button) return;
            var value = button.getAttribute("data-copy-result") || "";
            copyText(value).then(function () {
                flashButton(button, "کپی شد", "کپی کد رهگیری");
            }).catch(function () {
                button.textContent = "کپی پشتیبانی نشد";
                window.setTimeout(function () {
                    button.textContent = "کپی کد رهگیری";
                }, 1400);
            });
        });

        if (order) {
            actions.dataset.orderId = String(order.id || "");
        }
    }

    function initResultPage() {
        var orderToken = readParam("orderToken");
        var statusNode = $("buy-result-status");
        var titleNode = $("buy-result-title");
        var messageNode = $("buy-result-message");
        var summaryNode = $("buy-result-summary");
        var actionsNode = $("buy-result-actions");

        if (!orderToken) {
            if (titleNode) titleNode.textContent = "اطلاعات پرداخت پیدا نشد";
            if (messageNode) messageNode.textContent = "شناسه سفارش معتبر نیست.";
            return;
        }

        apiGet("publicOrderResult", { orderToken: orderToken }).then(function (payload) {
            if (!payload || !payload.success || !payload.order) {
                if (titleNode) titleNode.textContent = "نتیجه پرداخت در دسترس نیست";
                if (messageNode) messageNode.textContent = (payload && payload.error) || "امکان دریافت وضعیت پرداخت وجود ندارد.";
                return;
            }

            var order = payload.order;
            var item = order.item || null;
            var resultCartItems = Array.isArray(order.cartItems) ? order.cartItems : [];
            if (order.status === "success" && resultCartItems.length) {
                removeCartSlugs(resultCartItems.map(function (entry) { return entry.slug; }));
                writeCheckoutData({});
            }
            if (statusNode) {
                statusNode.textContent = order.statusLabel || "در انتظار";
                statusNode.className = "buy-status " + statusClass(order.status);
            }
            if (titleNode) {
                titleNode.textContent = order.status === "success"
                    ? "پرداخت شما ثبت و تایید شد"
                    : (order.status === "pending" ? "پرداخت در حال بررسی است" : "پرداخت کامل نشد");
            }
            if (messageNode) {
                messageNode.textContent = order.message || "وضعیت پرداخت به‌روزرسانی شد.";
            }
            if (summaryNode) {
                var resultSubtotal = Number(order.subtotal || 0) || ((Number(order.unitPrice || 0) || 0) * (Number(order.quantity || 1) || 1));
                var rows = [
                    { label: "مبلغ نهایی", value: money(order.amount || 0) }
                ];
                if (Number(order.unitPrice || 0) > 0 && !resultCartItems.length) {
                    rows.push({ label: "قیمت واحد", value: money(order.unitPrice || 0) });
                }
                rows.push(
                    { label: "تعداد", value: Number(order.quantity || 1).toLocaleString("fa-IR") },
                    { label: "جمع قبل از تخفیف", value: money(resultSubtotal || order.amount || 0) }
                );
                if (Number(order.discountAmount || 0) > 0) {
                    rows.push({ label: "تخفیف", value: money(order.discountAmount || 0) });
                }
                rows.push(
                    { label: "پرداخت‌کننده", value: order.payerName || "—" },
                    { label: "موبایل", value: order.payerPhone || "—" },
                    { label: "زمان ثبت", value: formatDateTime(order.createdAt, "—") }
                );
                if (item && item.title) {
                    rows.unshift({ label: "آیتم", value: item.title });
                }
                if (resultCartItems.length) {
                    rows.unshift({
                        label: "سبد",
                        value: resultCartItems.length.toLocaleString("fa-IR") + " آیتم / " + Number(order.quantity || 1).toLocaleString("fa-IR") + " عدد"
                    });
                    rows.push({
                        label: "آیتم‌ها",
                        value: resultCartItems.map(function (entry) {
                            return (entry.title || entry.slug || "آیتم") + " × " + Number(entry.quantity || 1).toLocaleString("fa-IR");
                        }).join("، ")
                    });
                }
                if (order.refId) {
                    rows.push({ label: "کد رهگیری", value: order.refId });
                } else if (order.authority) {
                    rows.push({ label: "authority", value: order.authority });
                }
                if (order.verifiedAt) {
                    rows.push({ label: "زمان تایید", value: formatDateTime(order.verifiedAt, "—") });
                }

                summaryNode.innerHTML = rows.map(function (row) {
                    return [
                        '<div class="buy-result-row">',
                        '  <span>' + text(row.label) + "</span>",
                        '  <strong>' + text(row.value) + "</strong>",
                        "</div>"
                    ].join("");
                }).join("");
            }
            if (actionsNode) {
                var ref = String(order.refId || order.authority || "");
                var backHref, backLabel;
                if (resultCartItems.length) {
                    var hasExamItem = resultCartItems.some(function (ci) { return itemCategory(ci) === "educational_package"; });
                    backHref = hasExamItem ? "/exams/" : "/buy/";
                    backLabel = hasExamItem ? "بازگشت به بخش آزمون‌ها" : "بازگشت به خرید";
                } else if (item) {
                    var isExamItem = itemCategory(item) === "educational_package";
                    backHref = isExamItem ? "/exams/" : (item.slug ? "/buy/item/?slug=" + encodeURIComponent(String(item.slug)) : "/buy/");
                    backLabel = isExamItem ? "بازگشت به بخش آزمون‌ها" : "بازگشت به صفحه آیتم";
                } else {
                    backHref = "/buy/";
                    backLabel = "بازگشت به خرید";
                }
                actionsNode.innerHTML = [
                    '<a class="buy-primary-btn" href="' + backHref + '">' + backLabel + "</a>",
                    '<a class="shell-action-btn" href="/buy/">مشاهده سایر آیتم‌ها</a>',
                    ref ? '<button class="shell-action-btn" type="button" data-copy-result="' + text(ref) + '">کپی کد رهگیری</button>' : ""
                ].join("");
            }
            bindResultActions(order);
        }).catch(function () {
            if (titleNode) titleNode.textContent = "نتیجه پرداخت دریافت نشد";
            if (messageNode) messageNode.textContent = "ارتباط با سرور برقرار نشد.";
        });
    }

    var page = document.body && document.body.dataset ? String(document.body.dataset.buyPage || "") : "";
    if (page === "list") {
        startAfterAuth(initListPage);
        return;
    }
    if (page === "item") {
        startAfterAuth(initItemPage);
        return;
    }
    if (page === "cart") {
        startAfterAuth(initCartPage);
        return;
    }
    if (page === "result") {
        startAfterAuth(initResultPage);
    }
})();
