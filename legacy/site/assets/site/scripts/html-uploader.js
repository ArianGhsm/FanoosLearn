(function () {
    "use strict";

    if (!document.body || !document.body.classList.contains("html-uploader-page")) {
        return;
    }

    var RECENT_KEY = "dent1402-html-uploader-recent";
    var MAX_RECENT = 8;

    function $(id) {
        return document.getElementById(id);
    }

    function escapeHtml(value) {
        return String(value == null ? "" : value).replace(/[&<>"']/g, function (char) {
            switch (char) {
                case "&": return "&amp;";
                case "<": return "&lt;";
                case ">": return "&gt;";
                case "\"": return "&quot;";
                case "'": return "&#39;";
                default: return char;
            }
        });
    }

    function normalizeSpace(value) {
        return String(value == null ? "" : value).replace(/\s+/g, " ").trim();
    }

    function formatBytes(value) {
        var bytes = Number(value || 0);
        if (!Number.isFinite(bytes) || bytes <= 0) return "۰ بایت";
        var units = ["بایت", "KB", "MB"];
        var index = 0;
        while (bytes >= 1024 && index < units.length - 1) {
            bytes = bytes / 1024;
            index++;
        }
        var formatted = bytes >= 10 || index === 0 ? Math.round(bytes).toLocaleString("fa-IR") : bytes.toFixed(1).toLocaleString("fa-IR");
        return formatted + " " + units[index];
    }

    function formatDate(value) {
        var raw = String(value || "").trim();
        if (!raw) return "—";
        var parsed = new Date(raw);
        if (!Number.isFinite(parsed.getTime())) return raw;
        return parsed.toLocaleString("fa-IR-u-ca-persian", {
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
            hour12: false
        });
    }

    function stateLabel(value) {
        switch (String(value || "")) {
            case "active": return "فعال";
            case "hidden": return "مخفی";
            case "expired": return "منقضی";
            case "deleted": return "حذف‌شده";
            default: return "نامشخص";
        }
    }

    function pillClass(value) {
        switch (String(value || "")) {
            case "active": return "hu-pill is-active";
            case "hidden": return "hu-pill is-hidden";
            case "expired": return "hu-pill is-expired";
            case "deleted": return "hu-pill is-deleted";
            default: return "hu-pill";
        }
    }

    function networkErrorResponse() {
        return {
            success: false,
            error: "ارتباط با سرور برقرار نشد. اتصال اینترنت خود را بررسی کنید.",
            httpStatus: 0
        };
    }

    function request(action, body, method) {
        var verb = String(method || "POST").toUpperCase();
        var url = "/api/html_uploader_api.php";
        var options = {
            method: verb,
            credentials: "same-origin",
            headers: {
                Accept: "application/json"
            }
        };
        if (verb === "GET") {
            url += "?" + new URLSearchParams(Object.assign({ action: action }, body || {})).toString();
        } else if (body instanceof FormData) {
            body.append("action", action);
            options.body = body;
        } else {
            options.headers["Content-Type"] = "application/x-www-form-urlencoded; charset=UTF-8";
            options.body = new URLSearchParams(Object.assign({ action: action }, body || {}));
        }
        return fetch(url, options).then(function (response) {
            return response.json().catch(function () {
                return { success: false, error: "پاسخ نامعتبر از سرور دریافت شد." };
            }).then(function (data) {
                data.httpStatus = response.status;
                return data;
            });
        }).catch(networkErrorResponse);
    }

    function copyText(value, callback) {
        var text = String(value || "").trim();
        if (!text) {
            if (callback) callback(false);
            return;
        }
        if (navigator.clipboard && typeof navigator.clipboard.writeText === "function") {
            navigator.clipboard.writeText(text).then(function () {
                if (callback) callback(true);
            }).catch(function () {
                fallbackCopy(text, callback);
            });
            return;
        }
        fallbackCopy(text, callback);
    }

    function fallbackCopy(text, callback) {
        var area = document.createElement("textarea");
        area.value = text;
        area.setAttribute("readonly", "readonly");
        area.style.position = "fixed";
        area.style.insetInlineStart = "-9999px";
        document.body.appendChild(area);
        area.select();
        var ok = false;
        try {
            ok = document.execCommand("copy");
        } catch (_error) {
            ok = false;
        }
        area.remove();
        if (callback) callback(ok);
    }

    function setFeedback(text, kind) {
        var node = $("hu-feedback");
        if (!node) return;
        node.className = "hu-feedback" + (kind ? (" is-" + kind) : "");
        node.textContent = text || "";
    }

    function readRecent() {
        try {
            var parsed = JSON.parse(window.localStorage.getItem(RECENT_KEY) || "[]");
            return Array.isArray(parsed) ? parsed : [];
        } catch (_error) {
            return [];
        }
    }

    function writeRecent(items) {
        try {
            window.localStorage.setItem(RECENT_KEY, JSON.stringify(items));
        } catch (_error) {
            // Ignore storage failures.
        }
    }

    function saveRecent(page) {
        if (!page || !page.publicUrl) return;
        var recent = readRecent().filter(function (item) {
            return item && item.publicUrl !== page.publicUrl;
        });
        recent.unshift({
            title: page.title || page.originalName || "صفحه HTML",
            publicUrl: page.publicUrl,
            createdAt: page.createdAt || "",
            expiresAt: page.expiresAt || "",
            size: Number(page.size || 0)
        });
        writeRecent(recent.slice(0, MAX_RECENT));
        renderRecent();
    }

    function renderResult(page) {
        var box = $("hu-result-box");
        if (!box || !page) return;
        box.hidden = false;
        box.innerHTML = [
            "<h3>" + escapeHtml(page.title || page.originalName || "صفحه HTML") + "</h3>",
            "<p>لینک موقت ساخته شد. این لینک را نگه دارید؛ از داخل ظاهر عمومی سایت جایی نمایش داده نمی‌شود.</p>",
            '<div class="hu-link-field">' + escapeHtml(page.publicUrl || "") + "</div>",
            '<div class="hu-result-actions">',
            '  <a class="hu-btn hu-btn--primary" target="_blank" rel="noopener" href="' + escapeHtml(page.publicUrl || "#") + '">باز کردن صفحه</a>',
            '  <button class="hu-btn" type="button" data-copy-result="' + escapeHtml(page.publicUrl || "") + '">کپی لینک</button>',
            "</div>",
            "<p>انقضا: " + escapeHtml(formatDate(page.expiresAt)) + " • حجم: " + escapeHtml(formatBytes(page.size || 0)) + "</p>"
        ].join("");
    }

    function renderRecent() {
        var node = $("hu-recent-list");
        if (!node) return;
        var recent = readRecent();
        if (!recent.length) {
            node.innerHTML = '<p class="hu-recent-empty">هنوز از این دستگاه لینکی ساخته نشده است.</p>';
            return;
        }
        node.innerHTML = recent.map(function (item) {
            return [
                '<article class="hu-device-item">',
                '  <h3 class="hu-device-item__title">' + escapeHtml(item.title || "صفحه HTML") + "</h3>",
                '  <div class="hu-device-item__meta">',
                '    <span class="hu-pill">ساخته‌شده: ' + escapeHtml(formatDate(item.createdAt)) + "</span>",
                '    <span class="hu-pill">انقضا: ' + escapeHtml(formatDate(item.expiresAt)) + "</span>",
                "  </div>",
                '  <div class="hu-link-field">' + escapeHtml(item.publicUrl || "") + "</div>",
                '  <div class="hu-device-item__actions">',
                '    <a class="hu-btn hu-btn--primary" target="_blank" rel="noopener" href="' + escapeHtml(item.publicUrl || "#") + '">باز کردن</a>',
                '    <button class="hu-btn" type="button" data-copy-recent="' + escapeHtml(item.publicUrl || "") + '">کپی</button>',
                "  </div>",
                "</article>"
            ].join("");
        }).join("");
    }

    function updateFileLabel(file) {
        var node = $("hu-file-label");
        if (!node) return;
        node.textContent = file ? file.name : "فایل HTML را انتخاب کن";
    }

    function resetForm() {
        var form = $("hu-upload-form");
        var input = $("hu-file-input");
        if (form) form.reset();
        if (input) input.value = "";
        updateFileLabel(null);
        setFeedback("", "");
    }

    function bindUploadForm() {
        var form = $("hu-upload-form");
        var input = $("hu-file-input");
        var submit = $("hu-submit-btn");
        var reset = $("hu-reset-btn");

        if (!form || !input || !submit) return;

        input.addEventListener("change", function () {
            updateFileLabel(input.files && input.files[0] ? input.files[0] : null);
        });

        if (reset) {
            reset.addEventListener("click", function () {
                resetForm();
            });
        }

        form.addEventListener("submit", function (event) {
            event.preventDefault();
            var file = input.files && input.files[0] ? input.files[0] : null;
            if (!file) {
                setFeedback("اول یک فایل HTML انتخاب کنید.", "error");
                return;
            }
            var payload = new FormData(form);
            submit.disabled = true;
            setFeedback("در حال ساخت لینک موقت...", "");
            request("uploadPage", payload, "POST").then(function (response) {
                if (!response || !response.success || !response.page) {
                    setFeedback((response && response.error) || "ساخت لینک موقت انجام نشد.", "error");
                    return;
                }
                renderResult(response.page);
                saveRecent(response.page);
                setFeedback(response.message || "صفحه HTML ساخته شد.", "success");
            }).catch(function () {
                setFeedback("در ارتباط با سرور خطا رخ داد.", "error");
            }).finally(function () {
                submit.disabled = false;
            });
        });
    }

    var ownerState = {
        enabled: false,
        loading: false,
        query: "",
        status: "available",
        sort: "newest"
    };

    function renderOwnerSummary(summary) {
        var node = $("hu-owner-summary");
        if (!node || !summary) return;
        node.innerHTML = [
            '<article class="hu-summary-card"><span>کل</span><strong>' + Number(summary.totalPages || 0).toLocaleString("fa-IR") + "</strong></article>",
            '<article class="hu-summary-card"><span>فعال</span><strong>' + Number(summary.activePages || 0).toLocaleString("fa-IR") + "</strong></article>",
            '<article class="hu-summary-card"><span>مخفی</span><strong>' + Number(summary.hiddenPages || 0).toLocaleString("fa-IR") + "</strong></article>",
            '<article class="hu-summary-card"><span>منقضی</span><strong>' + Number(summary.expiredPages || 0).toLocaleString("fa-IR") + "</strong></article>",
            '<article class="hu-summary-card"><span>بازدید</span><strong>' + Number(summary.viewCount || 0).toLocaleString("fa-IR") + "</strong></article>"
        ].join("");
    }

    function renderOwnerList(items) {
        var node = $("hu-owner-list");
        if (!node) return;
        if (!Array.isArray(items) || !items.length) {
            node.innerHTML = '<p class="hu-owner-empty">رکوردی برای این فیلتر پیدا نشد.</p>';
            return;
        }
        node.innerHTML = items.map(function (item) {
            var pageTitle = item.title || item.originalName || "صفحه HTML";
            var expiryText = formatDate(item.expiresAt);
            return [
                '<article class="hu-owner-row" data-owner-id="' + escapeHtml(item.id || "") + '">',
                '  <div class="hu-owner-row__top">',
                '    <div class="hu-owner-row__copy">',
                '      <h3 class="hu-owner-row__title">' + escapeHtml(pageTitle) + "</h3>",
                '      <p class="hu-owner-row__meta">',
                '        <span class="' + escapeHtml(pillClass(item.publicState || item.status)) + '">' + escapeHtml(stateLabel(item.publicState || item.status)) + "</span>",
                '        <span class="hu-pill">بازدید ' + Number(item.viewCount || 0).toLocaleString("fa-IR") + "</span>",
                '        <span class="hu-pill">انقضا: ' + escapeHtml(expiryText) + "</span>",
                "      </p>",
                "    </div>",
                "  </div>",
                '  <p class="hu-owner-row__meta">فایل: ' + escapeHtml(item.originalName || "") + ' • ساخته‌شده: ' + escapeHtml(formatDate(item.createdAt)) + "</p>",
                '  <p class="hu-owner-row__url">' + escapeHtml(item.publicUrl || "") + "</p>",
                '  <div class="hu-owner-row__actions">',
                '    <a class="hu-btn hu-btn--primary" target="_blank" rel="noopener" href="' + escapeHtml(item.publicUrl || "#") + '">باز کردن</a>',
                '    <button class="hu-btn" type="button" data-owner-copy="' + escapeHtml(item.publicUrl || "") + '">کپی لینک</button>',
                '    <button class="hu-btn" type="button" data-owner-op="activate">فعال</button>',
                '    <button class="hu-btn" type="button" data-owner-op="hide">مخفی</button>',
                '    <button class="hu-btn hu-btn--danger" type="button" data-owner-op="delete">حذف لینک</button>',
                '    <button class="hu-btn hu-btn--danger" type="button" data-owner-op="purge">حذف فایل</button>',
                "  </div>",
                "</article>"
            ].join("");
        }).join("");
    }

    function loadOwnerPages() {
        if (!ownerState.enabled || ownerState.loading) return;
        ownerState.loading = true;
        var node = $("hu-owner-list");
        if (node) {
            node.innerHTML = '<p class="hu-owner-empty">در حال دریافت فهرست صفحات HTML...</p>';
        }
        request("ownerPages", {
            page: 1,
            perPage: 100,
            query: ownerState.query,
            status: ownerState.status,
            sort: ownerState.sort
        }, "GET").then(function (response) {
            if (window.Dent1402Auth && typeof window.Dent1402Auth.handleUnauthorizedPayload === "function" && window.Dent1402Auth.handleUnauthorizedPayload(response, "نشست مالک منقضی شده است.")) {
                return;
            }
            if (!response || !response.success) {
                if (node) {
                    node.innerHTML = '<p class="hu-owner-empty">' + escapeHtml((response && response.error) || "دریافت فهرست صفحات HTML انجام نشد.") + "</p>";
                }
                return;
            }
            renderOwnerSummary(response.summary || {});
            renderOwnerList(response.page && response.page.items ? response.page.items : []);
        }).catch(function () {
            if (node) {
                node.innerHTML = '<p class="hu-owner-empty">در ارتباط با سرور خطا رخ داد.</p>';
            }
        }).finally(function () {
            ownerState.loading = false;
        });
    }

    function bindOwnerPanel() {
        var panel = $("hu-owner-panel");
        var query = $("hu-owner-query");
        var status = $("hu-owner-status");
        var sort = $("hu-owner-sort");
        var refresh = $("hu-owner-refresh");
        var list = $("hu-owner-list");
        if (!panel || !query || !status || !sort || !list) return;

        function syncFilters() {
            ownerState.query = normalizeSpace(query.value || "");
            ownerState.status = String(status.value || "available");
            ownerState.sort = String(sort.value || "newest");
            loadOwnerPages();
        }

        query.addEventListener("input", function () {
            syncFilters();
        });
        status.addEventListener("change", syncFilters);
        sort.addEventListener("change", syncFilters);
        if (refresh) refresh.addEventListener("click", loadOwnerPages);

        list.addEventListener("click", function (event) {
            var copyButton = event.target && event.target.closest ? event.target.closest("[data-owner-copy]") : null;
            if (copyButton) {
                copyText(copyButton.getAttribute("data-owner-copy"), function (ok) {
                    setFeedback(ok ? "لینک کپی شد." : "کپی لینک انجام نشد.", ok ? "success" : "error");
                });
                return;
            }

            var actionButton = event.target && event.target.closest ? event.target.closest("[data-owner-op]") : null;
            if (!actionButton) return;
            var row = actionButton.closest("[data-owner-id]");
            var id = row ? String(row.getAttribute("data-owner-id") || "") : "";
            var operation = String(actionButton.getAttribute("data-owner-op") || "");
            if (!id || !operation) return;
            if ((operation === "delete" || operation === "purge") && !window.confirm(operation === "purge" ? "فایل HTML از storage هم حذف شود؟" : "لینک این صفحه HTML حذف شود؟")) {
                return;
            }
            actionButton.disabled = true;
            request("ownerPageAction", {
                id: id,
                operation: operation
            }, "POST").then(function (response) {
                if (window.Dent1402Auth && typeof window.Dent1402Auth.handleUnauthorizedPayload === "function" && window.Dent1402Auth.handleUnauthorizedPayload(response, "نشست مالک منقضی شده است.")) {
                    return;
                }
                if (!response || !response.success) {
                    setFeedback((response && response.error) || "به‌روزرسانی وضعیت صفحه انجام نشد.", "error");
                    return;
                }
                setFeedback(response.message || "وضعیت صفحه HTML به‌روزرسانی شد.", "success");
                loadOwnerPages();
            }).catch(function () {
                setFeedback("در ارتباط با سرور خطا رخ داد.", "error");
            }).finally(function () {
                actionButton.disabled = false;
            });
        });
    }

    function bindResultAndRecentActions() {
        document.addEventListener("click", function (event) {
            var copyButton = event.target && event.target.closest ? event.target.closest("[data-copy-result],[data-copy-recent]") : null;
            if (!copyButton) return;
            var value = copyButton.getAttribute("data-copy-result") || copyButton.getAttribute("data-copy-recent") || "";
            copyText(value, function (ok) {
                setFeedback(ok ? "لینک کپی شد." : "کپی لینک انجام نشد.", ok ? "success" : "error");
            });
        });
    }

    function bootOwnerPanel() {
        if (!window.Dent1402Auth || typeof window.Dent1402Auth.onChange !== "function") {
            return;
        }
        window.Dent1402Auth.onChange(function (detail) {
            var panel = $("hu-owner-panel");
            if (!panel) return;
            var isOwner = !!(detail && detail.loggedIn && detail.user && detail.user.isOwner);
            ownerState.enabled = isOwner;
            panel.hidden = !isOwner;
            if (isOwner) {
                loadOwnerPages();
            }
        });
    }

    bindUploadForm();
    bindOwnerPanel();
    bindResultAndRecentActions();
    renderRecent();
    bootOwnerPanel();
})();
