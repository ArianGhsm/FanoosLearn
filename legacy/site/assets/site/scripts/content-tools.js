(function () {
    "use strict";

    var tool = document.body && document.body.dataset ? String(document.body.dataset.contentTool || "") : "";
    if (!tool) return;

    function $(id) {
        return document.getElementById(id);
    }

    var siteApi = window.Dent1402Site && typeof window.Dent1402Site === "object"
        ? window.Dent1402Site
        : null;

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
        var units = ["بایت", "KB", "MB", "GB", "TB"];
        var index = 0;
        while (bytes >= 1024 && index < units.length - 1) {
            bytes = bytes / 1024;
            index++;
        }
        var formatted = bytes >= 10 || index === 0 ? Math.round(bytes).toLocaleString("fa-IR") : bytes.toFixed(1).toLocaleString("fa-IR");
        return formatted + " " + units[index];
    }

    function formatDate(value, fallback) {
        if (siteApi && typeof siteApi.formatDateTime === "function") {
            return siteApi.formatDateTime(value, fallback);
        }
        var raw = String(value || "").trim();
        if (!raw) return fallback || "—";
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

    function stateLabel(state) {
        switch (String(state || "")) {
            case "active": return "فعال";
            case "hidden": return "مخفی";
            case "expired": return "منقضی";
            case "deleted": return "حذف‌شده";
            default: return "نامشخص";
        }
    }

    function fileKind(file) {
        var mime = String(file && file.mimeType || "").toLowerCase();
        var ext = String(file && file.extension || "").toUpperCase();
        if (mime.indexOf("image/") === 0) return "IMG";
        if (mime.indexOf("video/") === 0) return "VID";
        if (mime.indexOf("audio/") === 0) return "AUD";
        if (mime === "application/pdf") return "PDF";
        if (mime.indexOf("text/") === 0 || mime === "application/json") return "TXT";
        return ext ? ext.slice(0, 4) : "FILE";
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
        var url = "/api/content_tools_api.php";
        var options = {
            method: verb,
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        };
        if (verb === "GET") {
            url += "?" + new URLSearchParams(Object.assign({ action: action }, payload || {})).toString();
        } else {
            options.headers["Content-Type"] = "application/x-www-form-urlencoded; charset=UTF-8";
            options.body = new URLSearchParams(Object.assign({ action: action }, payload || {}));
        }
        return fetch(url, options).then(function (response) {
            if (siteApi && typeof siteApi.parseJsonResponse === "function") {
                return siteApi.parseJsonResponse(response);
            }
            return response.json().catch(function () {
                return { success: false, error: "پاسخ نامعتبر از سرور دریافت شد." };
            }).then(function (data) {
                data.httpStatus = response.status;
                return data;
            });
        }).catch(networkErrorResponse);
    }

    function copyText(value, onDone) {
        var text = String(value || "").trim();
        if (!text) {
            if (onDone) onDone(false);
            return;
        }
        if (navigator.clipboard && typeof navigator.clipboard.writeText === "function") {
            navigator.clipboard.writeText(text).then(function () {
                if (onDone) onDone(true);
            }).catch(function () {
                fallbackCopy(text, onDone);
            });
            return;
        }
        fallbackCopy(text, onDone);
    }

    function fallbackCopy(text, onDone) {
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
        if (onDone) onDone(ok);
    }

    function setFeedback(node, text, kind) {
        if (!node) return;
        node.className = "ct-feedback" + (kind ? (" is-" + kind) : "");
        node.textContent = text || "";
    }

    function consumeUnauthorized(payload, fallbackText) {
        if (siteApi && typeof siteApi.consumeUnauthorized === "function") {
            return !!siteApi.consumeUnauthorized(payload, fallbackText || "نشست شما منقضی شده است.");
        }
        if (!window.Dent1402Auth || !payload) return false;
        if (typeof window.Dent1402Auth.handleUnauthorizedPayload === "function") {
            return !!window.Dent1402Auth.handleUnauthorizedPayload(payload, fallbackText || "نشست شما منقضی شده است.");
        }
        return false;
    }

    function passwordSuffix(password) {
        return password ? ("&password=" + encodeURIComponent(password)) : "";
    }

    function isIOSDevice() {
        var userAgent = String(window.navigator.userAgent || "").toLowerCase();
        if (/iphone|ipad|ipod/.test(userAgent)) {
            return true;
        }
        return userAgent.indexOf("mac") !== -1 && Number(window.navigator.maxTouchPoints || 0) > 1;
    }

    function isStandaloneMode() {
        return !!(
            window.navigator.standalone === true ||
            (window.matchMedia && window.matchMedia("(display-mode: standalone)").matches)
        );
    }

    function shouldUseIosSafariHandoff() {
        return isIOSDevice() && isStandaloneMode();
    }

    function buildSafariHandoffUrl(targetUrl) {
        try {
            var parsed = new URL(String(targetUrl || ""), window.location.href);
            if (!/^https?:$/i.test(parsed.protocol)) {
                return "";
            }
            return "x-safari-" + parsed.protocol + "//" + parsed.host + parsed.pathname + parsed.search + parsed.hash;
        } catch (_error) {
            return "";
        }
    }

    function initOwnerGuard(onReady) {
        var guard = $("ct-auth-guard");
        var app = $("ct-owner-app");
        if (!window.Dent1402Auth || !guard || !app) return;

        window.Dent1402Auth.onChange(function (detail) {
            var loginUrl = window.Dent1402Auth.loginUrl ? window.Dent1402Auth.loginUrl(window.location.pathname + window.location.search) : "/account/";
            if (!detail || detail.status === "session-restoring" || detail.status === "logging-out") {
                app.hidden = true;
                guard.hidden = false;
                guard.innerHTML = "<h2>در حال بررسی حساب</h2><p>وضعیت نشست مشترک سایت خوانده می‌شود.</p>";
                return;
            }
            if (!detail.loggedIn) {
                app.hidden = true;
                guard.hidden = false;
                guard.innerHTML = window.Dent1402Auth.renderLoginRequiredGuard({
                    loginHref: loginUrl,
                    fallbackHref: "/app/",
                    actionsClass: "ct-auth-guard__actions",
                    primaryClass: "ct-btn ct-btn--primary",
                    secondaryClass: "ct-btn"
                });
                window.Dent1402Auth.enhanceLoginGuards(guard);
                return;
            }
            if (!detail.user || !detail.user.isOwner) {
                app.hidden = true;
                guard.hidden = false;
                guard.innerHTML = [
                    "<h2>این بخش مخصوص مالک سایت است</h2>",
                    "<p>کاربران عمومی فقط می‌توانند لینک‌های ساخته‌شده را باز کنند.</p>",
                    '<a class="ct-btn" href="/app/">بازگشت به خانه</a>'
                ].join("");
                return;
            }
            guard.hidden = true;
            guard.innerHTML = "";
            app.hidden = false;
            onReady(detail.user);
        });
    }

    function initFilesOwner() {
        var state = {
            ready: false,
            files: [],
            selected: {},
            type: "all",
            status: "available",
            sort: "newest",
            query: "",
            page: 1,
            perPage: 20,
            queue: []
        };
        var feedback = $("ct-feedback");
        var input = $("ct-upload-input");
        var pick = $("ct-upload-pick");
        var dropzone = $("ct-dropzone");
        var form = $("ct-upload-form");
        var queueNode = $("ct-upload-queue");
        var listNode = $("ct-files-list");
        var pagerNode = $("ct-files-pager");
        var maxUploadBytes = 2 * 1024 * 1024 * 1024;

        function renderStorage(summary) {
            var node = $("ct-storage-box");
            if (!node) return;
            var usedBytes = Number(summary && summary.totalBytes || 0);
            var remainingKnown = !!(summary && summary.remainingKnown);
            var remainingBytes = Number(summary && summary.remainingBytes || 0);
            var denominator = remainingKnown ? Math.max(0, usedBytes + remainingBytes) : 0;
            var percent = denominator > 0 ? Math.max(0, Math.min(100, (usedBytes / denominator) * 100)) : 0;
            var used = formatBytes(usedBytes);
            var remaining = remainingKnown ? formatBytes(remainingBytes) : "نامشخص";
            var totalFiles = Number(summary && summary.totalFiles || 0).toLocaleString("fa-IR");
            var totalDownloads = Number(summary && summary.downloadCount || 0).toLocaleString("fa-IR");
            node.innerHTML = [
                '<div class="ct-storage-head"><span>فضای ابزار</span><strong>' + escapeHtml(used) + "</strong></div>",
                '<div class="ct-storage-meter" aria-label="نمودار مصرف storage"><span style="width:' + percent.toFixed(1) + '%"></span></div>',
                '<div class="ct-storage-stats">',
                "<small>باقی‌مانده قابل‌خواندن: " + escapeHtml(remaining) + "</small>",
                "<small>" + escapeHtml(totalFiles) + " فایل · " + escapeHtml(totalDownloads) + " دانلود</small>",
                "</div>",
                '<small class="ct-storage-note">' + escapeHtml(summary && summary.notice || "وضعیت storage در دسترس نیست.") + "</small>"
            ].join("");
        }

        function currentFileParams() {
            return {
                query: state.query,
                status: state.status,
                type: state.type,
                sort: state.sort,
                page: String(state.page),
                perPage: String(state.perPage)
            };
        }

        async function loadFiles(silent) {
            if (!silent) setFeedback(feedback, "در حال دریافت فایل‌ها...");
            var response = await request("ownerFiles", currentFileParams(), "GET");
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                if (!silent) setFeedback(feedback, (response && response.error) || "دریافت فایل‌ها انجام نشد.", "error");
                return;
            }
            renderStorage(response.summary || {});
            state.files = response.page && Array.isArray(response.page.items) ? response.page.items : [];
            state.pageInfo = response.page || { page: 1, pages: 1, total: 0 };
            renderFiles();
            if (!silent) setFeedback(feedback, "");
        }

        async function loadDashboard() {
            var response = await request("ownerDashboard", {}, "GET");
            if (response && response.success) {
                renderStorage(response.summary || {});
            }
        }

        function renderQueue() {
            if (!queueNode) return;
            if (!state.queue.length) {
                queueNode.innerHTML = "";
                return;
            }
            queueNode.innerHTML = state.queue.map(function (item, index) {
                return [
                    '<article class="ct-upload-item">',
                    '  <strong>' + escapeHtml(item.name) + "</strong>",
                    '  <span class="ct-file-meta">' + escapeHtml(formatBytes(item.size)) + " • " + escapeHtml(item.status || "آماده") + "</span>",
                    '  <div class="ct-progress"><span style="width:' + Math.max(0, Math.min(100, Number(item.progress || 0))).toFixed(1) + '%"></span></div>',
                    item.publicUrl ? ('  <div class="ct-result-card"><div class="ct-result-link">' + escapeHtml(item.publicUrl) + '</div><button class="ct-btn" type="button" data-ct-copy="' + escapeHtml(item.publicUrl) + '">کپی لینک</button></div>') : "",
                    item.status === "queued" ? ('  <button class="ct-btn ct-btn--danger" type="button" data-ct-remove-queue="' + index + '">حذف از صف</button>') : "",
                    "</article>"
                ].join("");
            }).join("");
        }

        function addFiles(files) {
            Array.prototype.forEach.call(files || [], function (file) {
                if (!file) return;
                state.queue.push({ file: file, name: file.name || "file", size: file.size || 0, progress: 0, status: "queued" });
            });
            renderQueue();
        }

        function uploadFiles() {
            var queued = state.queue.filter(function (item) { return item.status === "queued" && item.file; });
            if (!queued.length) {
                setFeedback(feedback, "فایلی در صف آپلود نیست.", "error");
                return;
            }
            var oversized = queued.filter(function (item) { return Number(item.size || 0) > maxUploadBytes; });
            if (oversized.length) {
                setFeedback(feedback, "حجم هر فایل باید حداکثر " + formatBytes(maxUploadBytes) + " باشد.", "error");
                return;
            }
            var body = new FormData();
            body.append("action", "ownerUploadFiles");
            queued.forEach(function (item) {
                item.status = "uploading";
                body.append("files[]", item.file, item.name);
            });
            [["title", "ct-upload-title-input"], ["description", "ct-upload-description-input"], ["folder", "ct-upload-folder-input"], ["tags", "ct-upload-tags-input"], ["expiresAt", "ct-upload-expire-input"], ["downloadLimit", "ct-upload-limit-input"], ["password", "ct-upload-password-input"], ["status", "ct-upload-status-input"]].forEach(function (pair) {
                var el = $(pair[1]);
                body.append(pair[0], el ? el.value : "");
            });
            renderQueue();
            setFeedback(feedback, "در حال آپلود...");
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "/api/content_tools_api.php", true);
            xhr.withCredentials = true;
            xhr.setRequestHeader("Accept", "application/json");
            xhr.upload.onprogress = function (event) {
                if (!event.lengthComputable) return;
                var progress = Math.round((event.loaded / event.total) * 100);
                queued.forEach(function (item) {
                    item.progress = progress;
                });
                renderQueue();
            };
            xhr.onload = function () {
                var response = {};
                try {
                    response = JSON.parse(xhr.responseText || "{}");
                } catch (_error) {
                    response = {
                        success: false,
                        error: xhr.status === 413
                            ? "حجم درخواست از سقف فعلی PHP/هاست بیشتر است. تنظیمات آپلود هاست باید افزایش پیدا کند."
                            : "پاسخ آپلود نامعتبر بود."
                    };
                }
                response.httpStatus = xhr.status;
                if (consumeUnauthorized(response)) return;
                if (!response.success) {
                    queued.forEach(function (item) {
                        item.status = "failed";
                    });
                    renderQueue();
                    setFeedback(feedback, response.error || "آپلود انجام نشد.", "error");
                    return;
                }
                var uploaded = Array.isArray(response.files) ? response.files : [];
                queued.forEach(function (item, index) {
                    item.status = "uploaded";
                    item.progress = 100;
                    if (uploaded[index]) {
                        item.publicUrl = uploaded[index].publicUrl;
                    }
                });
                renderQueue();
                setFeedback(feedback, response.message || "آپلود انجام شد.", "success");
                loadDashboard();
                loadFiles(true);
            };
            xhr.onerror = function () {
                queued.forEach(function (item) {
                    item.status = "failed";
                });
                renderQueue();
                setFeedback(feedback, "ارتباط آپلود قطع شد.", "error");
            };
            xhr.send(body);
        }

        function selectedIds() {
            return Object.keys(state.selected).filter(function (id) { return state.selected[id]; });
        }

        function renderSelectedCount() {
            var node = $("ct-files-selected");
            if (node) node.textContent = selectedIds().length.toLocaleString("fa-IR") + " انتخاب";
        }

        function renderFiles() {
            renderSelectedCount();
            if (!listNode) return;
            if (!state.files.length) {
                listNode.innerHTML = '<div class="ct-empty">فایلی با این فیلتر پیدا نشد.</div>';
                renderPager();
                return;
            }
            listNode.innerHTML = state.files.map(function (file) {
                var stateKey = file.publicState || file.status || "";
                return [
                    '<article class="ct-file-row">',
                    '  <input class="ct-file-select" type="checkbox" data-ct-file-check="' + escapeHtml(file.id) + '"' + (state.selected[file.id] ? " checked" : "") + ' aria-label="انتخاب فایل">',
                    '  <div class="ct-file-icon" aria-hidden="true">' + escapeHtml(fileKind(file)) + "</div>",
                    '  <div>',
                    '    <h3>' + escapeHtml(file.title || file.originalName || "فایل") + "</h3>",
                    '    <div class="ct-file-meta">',
                    '      <span>' + escapeHtml(file.originalName || "") + "</span>",
                    '      <span>' + escapeHtml(formatBytes(file.size)) + "</span>",
                    '      <span>' + escapeHtml(formatDate(file.createdAt)) + "</span>",
                    '      <span>' + Number(file.downloadCount || 0).toLocaleString("fa-IR") + " دانلود</span>",
                    '      <span class="ct-status ct-status--' + escapeHtml(stateKey) + '">' + escapeHtml(stateLabel(stateKey)) + "</span>",
                    file.hasPassword ? "      <span>رمزدار</span>" : "",
                    '    </div>',
                    '  </div>',
                    '  <div class="ct-row-actions">',
                    '    <button class="ct-btn" type="button" data-ct-copy="' + escapeHtml(file.publicUrl) + '">کپی</button>',
                    '    <a class="ct-btn" href="' + escapeHtml(file.publicUrl) + '" target="_blank" rel="noopener">باز کردن</a>',
                    '    <button class="ct-btn" type="button" data-ct-edit-file="' + escapeHtml(file.id) + '">ویرایش</button>',
                    '    <button class="ct-btn ct-btn--danger" type="button" data-ct-single-file-delete="' + escapeHtml(file.id) + '">حذف</button>',
                    '  </div>',
                    "</article>"
                ].join("");
            }).join("");
            renderPager();
        }

        function renderPager() {
            if (!pagerNode) return;
            var info = state.pageInfo || { page: 1, pages: 1 };
            if (Number(info.pages || 1) <= 1) {
                pagerNode.innerHTML = "";
                return;
            }
            var html = [];
            for (var page = 1; page <= Number(info.pages || 1); page++) {
                html.push('<button class="ct-btn' + (page === Number(info.page || 1) ? " is-active" : "") + '" type="button" data-ct-files-page="' + page + '">' + page.toLocaleString("fa-IR") + "</button>");
            }
            pagerNode.innerHTML = html.join("");
        }

        async function bulkFiles(operation, ids) {
            var targets = ids || selectedIds();
            if (!targets.length) {
                setFeedback(feedback, "حداقل یک فایل را انتخاب کنید.", "error");
                return;
            }
            var dangerous = operation === "delete" || operation === "purge";
            var message = operation === "purge"
                ? "فایل فیزیکی فقط از storage مدیریت‌شده همین ابزار حذف می‌شود. ادامه می‌دهید؟"
                : "عملیات روی " + targets.length.toLocaleString("fa-IR") + " فایل انجام شود؟";
            if (dangerous && !window.confirm(message)) return;
            var response = await request("ownerBulkFiles", { operation: operation, ids: JSON.stringify(targets) }, "POST");
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                setFeedback(feedback, (response && response.error) || "عملیات انجام نشد.", "error");
                return;
            }
            state.selected = {};
            if (operation === "delete" || operation === "purge") {
                state.files = state.files.filter(function (item) { return targets.indexOf(item.id) === -1; });
                renderFiles();
            }
            setFeedback(feedback, response.message || "عملیات انجام شد.", "success");
            loadDashboard();
            loadFiles(true);
        }

        async function editFile(id) {
            var file = state.files.find(function (item) { return item.id === id; });
            if (!file) return;
            var title = window.prompt("عنوان فایل", file.title || file.originalName || "");
            if (title === null) return;
            var description = window.prompt("توضیح فایل", file.description || "");
            if (description === null) return;
            var status = window.prompt("وضعیت: active یا hidden", file.status || "active");
            if (status === null) return;
            var response = await request("ownerUpdateFile", {
                id: id,
                title: title,
                description: description,
                tags: (file.tags || []).join(","),
                folder: file.folder || "",
                status: status,
                expiresAt: file.expiresAt || "",
                downloadLimit: String(file.downloadLimit || 0)
            }, "POST");
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                setFeedback(feedback, (response && response.error) || "ویرایش ذخیره نشد.", "error");
                return;
            }
            setFeedback(feedback, "فایل ذخیره شد.", "success");
            loadFiles(true);
        }

        function syncFilters() {
            state.query = $("ct-files-query-top")
                ? $("ct-files-query-top").value
                : ($("ct-files-query") ? $("ct-files-query").value : "");
            state.status = $("ct-files-status") ? $("ct-files-status").value : "available";
            state.sort = $("ct-files-sort") ? $("ct-files-sort").value : "newest";
            state.page = 1;
            loadFiles(false);
        }

        var filterTimer = null;
        function queueFilterSync() {
            window.clearTimeout(filterTimer);
            filterTimer = window.setTimeout(syncFilters, 220);
        }

        initOwnerGuard(function () {
            if (state.ready) return;
            state.ready = true;
            loadDashboard();
            loadFiles(false);
        });

        if (pick) pick.addEventListener("click", function () { if (input) input.click(); });
        if (dropzone) {
            dropzone.addEventListener("click", function () { if (input) input.click(); });
            dropzone.addEventListener("keydown", function (event) {
                if (event.key === "Enter" || event.key === " ") {
                    event.preventDefault();
                    if (input) input.click();
                }
            });
            ["dragenter", "dragover"].forEach(function (name) {
                dropzone.addEventListener(name, function (event) {
                    event.preventDefault();
                    dropzone.classList.add("is-dragover");
                });
            });
            ["dragleave", "drop"].forEach(function (name) {
                dropzone.addEventListener(name, function (event) {
                    event.preventDefault();
                    dropzone.classList.remove("is-dragover");
                });
            });
            dropzone.addEventListener("drop", function (event) {
                addFiles(event.dataTransfer && event.dataTransfer.files);
            });
        }
        if (input) input.addEventListener("change", function () { addFiles(input.files); input.value = ""; });
        if (form) form.addEventListener("submit", function (event) { event.preventDefault(); uploadFiles(); });
        var clear = $("ct-upload-clear");
        if (clear) clear.addEventListener("click", function () {
            state.queue = state.queue.filter(function (item) { return item.status === "uploading"; });
            renderQueue();
        });
        ["ct-files-query", "ct-files-status", "ct-files-sort"].forEach(function (id) {
            var el = $(id);
            if (!el) return;
            el.addEventListener(id === "ct-files-query" ? "input" : "change", function () {
                if (id === "ct-files-query" && $("ct-files-query-top")) $("ct-files-query-top").value = el.value;
                queueFilterSync();
            });
        });
        var topQuery = $("ct-files-query-top");
        if (topQuery) topQuery.addEventListener("input", function () {
            if ($("ct-files-query")) $("ct-files-query").value = topQuery.value;
            queueFilterSync();
        });
        var refresh = $("ct-files-refresh");
        if (refresh) refresh.addEventListener("click", function () { loadDashboard(); loadFiles(false); });
        document.addEventListener("click", function (event) {
            var copy = event.target.closest("[data-ct-copy]");
            if (copy) {
                copyText(copy.getAttribute("data-ct-copy"), function (ok) {
                    setFeedback(feedback, ok ? "کپی شد." : "کپی خودکار انجام نشد.", ok ? "success" : "error");
                });
                return;
            }
            var removeQueue = event.target.closest("[data-ct-remove-queue]");
            if (removeQueue) {
                state.queue.splice(Number(removeQueue.getAttribute("data-ct-remove-queue")), 1);
                renderQueue();
                return;
            }
            var pageBtn = event.target.closest("[data-ct-files-page]");
            if (pageBtn) {
                state.page = Number(pageBtn.getAttribute("data-ct-files-page")) || 1;
                loadFiles(false);
                return;
            }
            var bulk = event.target.closest("[data-ct-bulk-files]");
            if (bulk) {
                bulkFiles(String(bulk.getAttribute("data-ct-bulk-files") || ""));
                return;
            }
            var singleDelete = event.target.closest("[data-ct-single-file-delete]");
            if (singleDelete) {
                bulkFiles("delete", [singleDelete.getAttribute("data-ct-single-file-delete")]);
                return;
            }
            var edit = event.target.closest("[data-ct-edit-file]");
            if (edit) {
                editFile(edit.getAttribute("data-ct-edit-file"));
            }
        });
        document.addEventListener("change", function (event) {
            var check = event.target.closest("[data-ct-file-check]");
            if (check) {
                state.selected[check.getAttribute("data-ct-file-check")] = check.checked;
                renderSelectedCount();
            }
            var nav = event.target.closest("[data-ct-file-filter]");
            if (nav) {
                state.type = nav.getAttribute("data-ct-file-filter") || "all";
                state.page = 1;
                loadFiles(false);
            }
        });
        document.addEventListener("click", function (event) {
            var nav = event.target.closest("[data-ct-file-filter]");
            if (!nav) return;
            document.querySelectorAll("[data-ct-file-filter]").forEach(function (button) { button.classList.remove("is-active"); });
            nav.classList.add("is-active");
            state.type = nav.getAttribute("data-ct-file-filter") || "all";
            state.page = 1;
            loadFiles(false);
        });
    }

    function initPasteOwner() {
        var state = { ready: false, pastes: [], selected: {}, page: 1, perPage: 20, query: "", status: "available", sort: "newest", currentPublicUrl: "" };
        var feedback = $("ct-feedback");

        function params() {
            return {
                query: state.query,
                status: state.status,
                sort: state.sort,
                page: String(state.page),
                perPage: String(state.perPage),
                includeBody: "1"
            };
        }

        async function loadPastes(silent) {
            if (!silent) setFeedback(feedback, "در حال دریافت pasteها...");
            var response = await request("ownerPastes", params(), "GET");
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                if (!silent) setFeedback(feedback, (response && response.error) || "دریافت pasteها انجام نشد.", "error");
                return;
            }
            state.pastes = response.page && Array.isArray(response.page.items) ? response.page.items : [];
            state.pageInfo = response.page || { page: 1, pages: 1, total: 0 };
            renderPasteSummary(response.summary || {});
            renderPastes();
            if (!silent) setFeedback(feedback, "");
        }

        function renderPasteSummary(summary) {
            var node = $("ct-paste-summary");
            if (!node) return;
            var views = Number(summary && summary.pasteViewCount || 0);
            var raw = Number(summary && summary.pasteRawViewCount || 0);
            node.innerHTML = [
                "<article><span>Pasteها</span><strong>" + Number(summary.pasteCount || 0).toLocaleString("fa-IR") + "</strong></article>",
                "<article><span>بازدید صفحه</span><strong>" + views.toLocaleString("fa-IR") + "</strong></article>",
                "<article><span>Raw view</span><strong>" + raw.toLocaleString("fa-IR") + "</strong></article>"
            ].join("");
        }

        function resetForm() {
            ["ct-paste-id", "ct-paste-title-input", "ct-paste-body-input", "ct-paste-expire-input", "ct-paste-password-input"].forEach(function (id) {
                var el = $(id);
                if (el) el.value = "";
            });
            if ($("ct-paste-language-input")) $("ct-paste-language-input").value = "plain";
            if ($("ct-paste-status-input")) $("ct-paste-status-input").value = "active";
            if ($("ct-paste-clear-password-input")) $("ct-paste-clear-password-input").checked = false;
            state.currentPublicUrl = "";
            var copy = $("ct-paste-copy-current");
            if (copy) copy.disabled = true;
        }

        async function savePaste() {
            var body = $("ct-paste-body-input") ? $("ct-paste-body-input").value : "";
            if (!normalizeSpace(body)) {
                setFeedback(feedback, "متن paste خالی است.", "error");
                return;
            }
            var payload = {
                id: $("ct-paste-id") ? $("ct-paste-id").value : "",
                title: $("ct-paste-title-input") ? $("ct-paste-title-input").value : "",
                body: body,
                language: $("ct-paste-language-input") ? $("ct-paste-language-input").value : "plain",
                status: $("ct-paste-status-input") ? $("ct-paste-status-input").value : "active",
                expiresAt: $("ct-paste-expire-input") ? $("ct-paste-expire-input").value : "",
                password: $("ct-paste-password-input") ? $("ct-paste-password-input").value : "",
                clearPassword: $("ct-paste-clear-password-input") && $("ct-paste-clear-password-input").checked ? "1" : "0"
            };
            setFeedback(feedback, "در حال ذخیره paste...");
            var response = await request("ownerSavePaste", payload, "POST");
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                setFeedback(feedback, (response && response.error) || "ذخیره paste انجام نشد.", "error");
                return;
            }
            var paste = response.paste || {};
            if ($("ct-paste-id")) $("ct-paste-id").value = paste.id || "";
            state.currentPublicUrl = paste.publicUrl || "";
            var copy = $("ct-paste-copy-current");
            if (copy) copy.disabled = !state.currentPublicUrl;
            setFeedback(feedback, response.message || "Paste ذخیره شد.", "success");
            loadPastes(true);
        }

        function fillForm(id) {
            var paste = state.pastes.find(function (item) { return item.id === id; });
            if (!paste) return;
            if ($("ct-paste-id")) $("ct-paste-id").value = paste.id || "";
            if ($("ct-paste-title-input")) $("ct-paste-title-input").value = paste.title || "";
            if ($("ct-paste-body-input")) $("ct-paste-body-input").value = paste.body || "";
            if ($("ct-paste-language-input")) $("ct-paste-language-input").value = paste.language || "plain";
            if ($("ct-paste-status-input")) $("ct-paste-status-input").value = paste.status || "active";
            if ($("ct-paste-expire-input")) $("ct-paste-expire-input").value = "";
            if ($("ct-paste-password-input")) $("ct-paste-password-input").value = "";
            if ($("ct-paste-clear-password-input")) $("ct-paste-clear-password-input").checked = false;
            state.currentPublicUrl = paste.publicUrl || "";
            var copy = $("ct-paste-copy-current");
            if (copy) copy.disabled = !state.currentPublicUrl;
            window.scrollTo({ top: 0, behavior: "smooth" });
        }

        function selectedIds() {
            return Object.keys(state.selected).filter(function (id) { return state.selected[id]; });
        }

        function renderSelectedCount() {
            var node = $("ct-pastes-selected");
            if (node) node.textContent = selectedIds().length.toLocaleString("fa-IR") + " انتخاب";
        }

        function renderPastes() {
            renderSelectedCount();
            var node = $("ct-pastes-list");
            if (!node) return;
            if (!state.pastes.length) {
                node.innerHTML = '<div class="ct-empty">Pasteی با این فیلتر پیدا نشد.</div>';
                renderPastePager();
                return;
            }
            node.innerHTML = state.pastes.map(function (paste) {
                var stateKey = paste.publicState || paste.status || "";
                return [
                    '<article class="ct-paste-row">',
                    '  <input class="ct-paste-select" type="checkbox" data-ct-paste-check="' + escapeHtml(paste.id) + '"' + (state.selected[paste.id] ? " checked" : "") + ' aria-label="انتخاب paste">',
                    '  <div class="ct-paste-icon" aria-hidden="true">' + escapeHtml(String(paste.language || "P").slice(0, 3).toUpperCase()) + "</div>",
                    '  <div>',
                    '    <h3>' + escapeHtml(paste.title || "Paste") + "</h3>",
                    '    <div class="ct-paste-meta">',
                    '      <span>' + escapeHtml(paste.language || "plain") + "</span>",
                    '      <span>' + escapeHtml(formatDate(paste.createdAt)) + "</span>",
                    '      <span>' + Number(paste.viewCount || 0).toLocaleString("fa-IR") + " بازدید</span>",
                    '      <span class="ct-status ct-status--' + escapeHtml(stateKey) + '">' + escapeHtml(stateLabel(stateKey)) + "</span>",
                    paste.hasPassword ? "      <span>رمزدار</span>" : "",
                    '    </div>',
                    '  </div>',
                    '  <div class="ct-row-actions">',
                    '    <button class="ct-btn" type="button" data-ct-copy="' + escapeHtml(paste.publicUrl) + '">کپی</button>',
                    '    <a class="ct-btn" href="' + escapeHtml(paste.publicUrl) + '" target="_blank" rel="noopener">نمایش</a>',
                    '    <a class="ct-btn" href="' + escapeHtml(paste.rawUrl) + '" target="_blank" rel="noopener">Raw</a>',
                    '    <button class="ct-btn" type="button" data-ct-edit-paste="' + escapeHtml(paste.id) + '">ویرایش</button>',
                    '    <button class="ct-btn ct-btn--danger" type="button" data-ct-single-paste-delete="' + escapeHtml(paste.id) + '">حذف</button>',
                    '  </div>',
                    "</article>"
                ].join("");
            }).join("");
            renderPastePager();
        }

        function renderPastePager() {
            var node = $("ct-pastes-pager");
            if (!node) return;
            var info = state.pageInfo || { page: 1, pages: 1 };
            if (Number(info.pages || 1) <= 1) {
                node.innerHTML = "";
                return;
            }
            var html = [];
            for (var page = 1; page <= Number(info.pages || 1); page++) {
                html.push('<button class="ct-btn' + (page === Number(info.page || 1) ? " is-active" : "") + '" type="button" data-ct-pastes-page="' + page + '">' + page.toLocaleString("fa-IR") + "</button>");
            }
            node.innerHTML = html.join("");
        }

        async function bulkPastes(operation, ids) {
            var targets = ids || selectedIds();
            if (!targets.length) {
                setFeedback(feedback, "حداقل یک paste را انتخاب کنید.", "error");
                return;
            }
            if (operation === "delete" && !window.confirm("Pasteهای انتخاب‌شده حذف شوند؟")) return;
            var response = await request("ownerBulkPastes", { operation: operation, ids: JSON.stringify(targets) }, "POST");
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                setFeedback(feedback, (response && response.error) || "عملیات انجام نشد.", "error");
                return;
            }
            state.selected = {};
            if (operation === "delete") {
                state.pastes = state.pastes.filter(function (item) { return targets.indexOf(item.id) === -1; });
                renderPastes();
            }
            setFeedback(feedback, response.message || "عملیات انجام شد.", "success");
            loadPastes(true);
        }

        function syncFilters() {
            state.query = $("ct-pastes-query") ? $("ct-pastes-query").value : "";
            state.status = $("ct-pastes-status") ? $("ct-pastes-status").value : "available";
            state.sort = $("ct-pastes-sort") ? $("ct-pastes-sort").value : "newest";
            state.page = 1;
            loadPastes(false);
        }

        var filterTimer = null;
        function queueFilterSync() {
            window.clearTimeout(filterTimer);
            filterTimer = window.setTimeout(syncFilters, 220);
        }

        initOwnerGuard(function () {
            if (state.ready) return;
            state.ready = true;
            loadPastes(false);
        });

        var form = $("ct-paste-form");
        if (form) form.addEventListener("submit", function (event) { event.preventDefault(); savePaste(); });
        var newBtn = $("ct-paste-new");
        if (newBtn) newBtn.addEventListener("click", resetForm);
        var copyCurrent = $("ct-paste-copy-current");
        if (copyCurrent) copyCurrent.addEventListener("click", function () {
            copyText(state.currentPublicUrl, function (ok) {
                setFeedback(feedback, ok ? "لینک paste کپی شد." : "کپی خودکار انجام نشد.", ok ? "success" : "error");
            });
        });
        ["ct-pastes-query", "ct-pastes-status", "ct-pastes-sort"].forEach(function (id) {
            var el = $(id);
            if (!el) return;
            el.addEventListener(id === "ct-pastes-query" ? "input" : "change", queueFilterSync);
        });
        var refresh = $("ct-pastes-refresh");
        if (refresh) refresh.addEventListener("click", function () { loadPastes(false); });
        document.addEventListener("click", function (event) {
            var copy = event.target.closest("[data-ct-copy]");
            if (copy) {
                copyText(copy.getAttribute("data-ct-copy"), function (ok) {
                    setFeedback(feedback, ok ? "کپی شد." : "کپی خودکار انجام نشد.", ok ? "success" : "error");
                });
                return;
            }
            var edit = event.target.closest("[data-ct-edit-paste]");
            if (edit) {
                fillForm(edit.getAttribute("data-ct-edit-paste"));
                return;
            }
            var singleDelete = event.target.closest("[data-ct-single-paste-delete]");
            if (singleDelete) {
                bulkPastes("delete", [singleDelete.getAttribute("data-ct-single-paste-delete")]);
                return;
            }
            var bulk = event.target.closest("[data-ct-bulk-pastes]");
            if (bulk) {
                bulkPastes(String(bulk.getAttribute("data-ct-bulk-pastes") || ""));
                return;
            }
            var pageBtn = event.target.closest("[data-ct-pastes-page]");
            if (pageBtn) {
                state.page = Number(pageBtn.getAttribute("data-ct-pastes-page")) || 1;
                loadPastes(false);
            }
        });
        document.addEventListener("change", function (event) {
            var check = event.target.closest("[data-ct-paste-check]");
            if (check) {
                state.selected[check.getAttribute("data-ct-paste-check")] = check.checked;
                renderSelectedCount();
            }
        });
    }

    function publicToken() {
        var token = document.body && document.body.dataset ? String(document.body.dataset.publicToken || "") : "";
        if (token) return token;
        return new URLSearchParams(window.location.search).get("token") || "";
    }

    function renderUnavailable(root, unavailable, passwordHandler) {
        var needsPassword = unavailable && unavailable.state === "password";
        root.innerHTML = [
            '<div class="ct-unavailable">',
            '<span class="ct-brand-mark" aria-hidden="true">!</span>',
            "<h1>" + escapeHtml(unavailable && unavailable.title || "لینک در دسترس نیست") + "</h1>",
            "<p>" + escapeHtml(unavailable && unavailable.message || "این لینک قابل نمایش نیست.") + "</p>",
            needsPassword ? [
                '<form class="ct-password-form" id="ct-password-form">',
                '<input id="ct-password-input" type="password" autocomplete="current-password" placeholder="رمز لینک">',
                '<button class="ct-btn ct-btn--primary" type="submit">باز کردن</button>',
                "</form>"
            ].join("") : '<a class="ct-btn" href="/app/">بازگشت به خانه</a>',
            "</div>"
        ].join("");
        if (needsPassword) {
            var form = $("ct-password-form");
            if (form) form.addEventListener("submit", function (event) {
                event.preventDefault();
                passwordHandler($("ct-password-input") ? $("ct-password-input").value : "");
            });
        }
    }

    function initPublicFile() {
        var root = $("ct-public-root");
        var token = publicToken();
        var currentPassword = "";
        if (!root) return;

        async function load(password) {
            currentPassword = password || "";
            var payload = { token: token };
            if (currentPassword) payload.password = currentPassword;
            var response = await request("publicFile", payload, currentPassword ? "POST" : "GET");
            if (!response || !response.success) {
                renderUnavailable(root, { title: "خطا در دریافت فایل", message: response && response.error || "لینک فایل خوانده نشد." }, load);
                return;
            }
            if (response.unavailable) {
                renderUnavailable(root, response.unavailable, load);
                return;
            }
            renderFile(response.file || {}, response.previewUrl || "");
        }

        function renderPreview(file, previewUrl) {
            if (!previewUrl) {
                return '<div class="ct-empty">پیش‌نمایش امن برای این فایل فعال نیست.</div>';
            }
            var mime = String(file.mimeType || "").toLowerCase();
            var src = previewUrl + passwordSuffix(currentPassword);
            if (shouldUseIosSafariHandoff() && mime === "application/pdf") {
                var pdfHandoffUrl = buildSafariHandoffUrl(src);
                return [
                    '<div class="ct-handoff-notice ct-handoff-notice--preview">',
                    "  <strong>\u067e\u06cc\u0634\u200c\u0646\u0645\u0627\u06cc\u0634 PDF \u062f\u0631 \u0648\u0628\u200c\u0627\u067e iPhone \u0628\u0647 \u0635\u0648\u0631\u062a \u062f\u0627\u062e\u0644\u06cc \u063a\u06cc\u0631\u0641\u0639\u0627\u0644 \u0627\u0633\u062a</strong>",
                    "  <p>\u0628\u0631\u0627\u06cc \u062c\u0644\u0648\u06af\u06cc\u0631\u06cc \u0627\u0632 \u06af\u06cc\u0631 \u06a9\u0631\u062f\u0646 \u0635\u0641\u062d\u0647\u060c PDF \u0631\u0627 \u062f\u0631 Safari \u0628\u0627\u0632 \u06a9\u0646\u06cc\u062f.</p>",
                    '  <div class="ct-preview-actions">',
                    '    <a class="ct-btn" href="' + escapeHtml(pdfHandoffUrl || src) + '">\u0628\u0627\u0632 \u06a9\u0631\u062f\u0646 PDF \u062f\u0631 Safari</a>',
                    "  </div>",
                    "</div>"
                ].join("");
            }
            if (mime.indexOf("image/") === 0) return '<div class="ct-preview"><img src="' + escapeHtml(src) + '" alt="' + escapeHtml(file.originalName || "file") + '"></div>';
            if (mime.indexOf("video/") === 0) return '<div class="ct-preview"><video src="' + escapeHtml(src) + '" controls playsinline></video></div>';
            if (mime.indexOf("audio/") === 0) return '<div class="ct-preview"><audio src="' + escapeHtml(src) + '" controls></audio></div>';
            if (mime === "application/pdf") return '<div class="ct-preview"><iframe src="' + escapeHtml(src) + '" title="' + escapeHtml(file.originalName || "PDF") + '"></iframe></div>';
            return '<div id="ct-text-preview-host" class="ct-preview"><pre class="ct-text-preview">در حال دریافت پیش‌نمایش متن...</pre></div>';
        }

        function renderFile(file, previewUrl) {
            var downloadUrl = String(file.downloadUrl || "") + passwordSuffix(currentPassword);
            var safariDownloadUrl = shouldUseIosSafariHandoff() ? buildSafariHandoffUrl(downloadUrl) : "";
            var handoffRequired = safariDownloadUrl !== "";
            root.innerHTML = [
                '<div class="ct-public-file-head">',
                '  <div class="ct-public-file-icon" aria-hidden="true">' + escapeHtml(fileKind(file)) + "</div>",
                '  <div class="ct-public-file-copy">',
                '    <span class="ct-kicker">Public File</span>',
                '    <h1>' + escapeHtml(file.title || file.originalName || "فایل") + "</h1>",
                file.description ? ("    <p>" + escapeHtml(file.description) + "</p>") : "",
                "  </div>",
                "</div>",
                '<div class="ct-public-meta">',
                '<div><span>حجم</span><strong>' + escapeHtml(formatBytes(file.size)) + "</strong></div>",
                '<div><span>نوع</span><strong>' + escapeHtml(file.mimeType || "—") + "</strong></div>",
                '<div><span>بارگذاری</span><strong>' + escapeHtml(formatDate(file.createdAt)) + "</strong></div>",
                '<div><span>دانلود</span><strong>' + Number(file.downloadCount || 0).toLocaleString("fa-IR") + "</strong></div>",
                "</div>",
                '<div class="ct-public-actions">',
                '<a class="ct-btn ct-btn--primary" href="' + escapeHtml(downloadUrl) + '">دانلود فایل</a>',
                '<button class="ct-btn" type="button" id="ct-public-copy">کپی لینک</button>',
                "</div>",
                renderPreview(file, previewUrl)
            ].join("");
            var actions = root.querySelector(".ct-public-actions");
            if (handoffRequired && actions) {
                var notice = document.createElement("div");
                notice.className = "ct-handoff-notice";
                notice.innerHTML = [
                    "<strong>\u062f\u0627\u0646\u0644\u0648\u062f \u062f\u0627\u062e\u0644 \u0648\u0628\u200c\u0627\u067e iPhone \u0645\u0645\u06a9\u0646 \u0627\u0633\u062a \u0635\u0641\u062d\u0647 \u0631\u0627 \u06af\u06cc\u0631 \u0628\u06cc\u0646\u062f\u0627\u0632\u062f</strong>",
                    "<p>\u0627\u06cc\u0646 \u062f\u06a9\u0645\u0647 \u0641\u0627\u06cc\u0644 \u0631\u0627 \u062f\u0631 Safari \u0628\u0627\u0632 \u0645\u06cc\u200c\u06a9\u0646\u062f \u062a\u0627 \u062f\u0627\u0646\u0644\u0648\u062f \u062f\u0631 \u0645\u0631\u0648\u0631\u06af\u0631 \u0627\u0646\u062c\u0627\u0645 \u0634\u0648\u062f.</p>"
                ].join("");
                actions.parentNode.insertBefore(notice, actions);

                var downloadAction = actions.querySelector("a.ct-btn--primary");
                if (downloadAction) {
                    downloadAction.href = safariDownloadUrl;
                    downloadAction.textContent = "\u0628\u0627\u0632 \u06a9\u0631\u062f\u0646 \u062f\u0631 Safari \u0648 \u062f\u0627\u0646\u0644\u0648\u062f";
                }

                var copyDownload = document.createElement("button");
                copyDownload.type = "button";
                copyDownload.id = "ct-public-copy-download";
                copyDownload.className = "ct-btn";
                copyDownload.textContent = "\u06a9\u067e\u06cc \u0644\u06cc\u0646\u06a9 \u0645\u0633\u062a\u0642\u06cc\u0645";
                copyDownload.addEventListener("click", function () {
                    copyText(downloadUrl, function () {});
                });
                actions.appendChild(copyDownload);
            }
            var copy = $("ct-public-copy");
            if (copy) copy.addEventListener("click", function () {
                copyText(window.location.href, function () {});
            });
            var mime = String(file.mimeType || "").toLowerCase();
            if (previewUrl && (mime.indexOf("text/") === 0 || mime === "application/json")) {
                fetch(previewUrl + passwordSuffix(currentPassword), { credentials: "same-origin" }).then(function (res) {
                    return res.text();
                }).then(function (text) {
                    var pre = document.querySelector("#ct-text-preview-host pre");
                    if (pre) pre.textContent = text;
                }).catch(function () {
                    var pre = document.querySelector("#ct-text-preview-host pre");
                    if (pre) pre.textContent = "پیش‌نمایش متن دریافت نشد.";
                });
            }
        }

        load("");
    }

    function initPublicPaste() {
        var root = $("ct-public-root");
        var token = publicToken();
        var currentPassword = "";
        if (!root) return;

        async function load(password) {
            currentPassword = password || "";
            var payload = { token: token };
            if (currentPassword) payload.password = currentPassword;
            var response = await request("publicPaste", payload, currentPassword ? "POST" : "GET");
            if (!response || !response.success) {
                renderUnavailable(root, { title: "خطا در دریافت paste", message: response && response.error || "لینک paste خوانده نشد." }, load);
                return;
            }
            if (response.unavailable) {
                renderUnavailable(root, response.unavailable, load);
                return;
            }
            renderPaste(response.paste || {});
        }

        function renderPaste(paste) {
            var rawUrl = String(paste.rawUrl || "") + passwordSuffix(currentPassword);
            root.innerHTML = [
                '<div class="ct-public-paste-head">',
                '  <div class="ct-public-file-icon" aria-hidden="true">' + escapeHtml(String(paste.language || "P").slice(0, 3).toUpperCase()) + "</div>",
                '  <div class="ct-public-paste-copy">',
                '    <span class="ct-kicker">Public Paste</span>',
                '    <h1>' + escapeHtml(paste.title || "Paste") + "</h1>",
                '    <p>' + escapeHtml((paste.language || "plain") + " • " + formatDate(paste.createdAt) + " • " + Number(paste.viewCount || 0).toLocaleString("fa-IR") + " بازدید") + "</p>",
                "  </div>",
                "</div>",
                '<div class="ct-public-actions">',
                '<button class="ct-btn ct-btn--primary" type="button" id="ct-copy-paste-body">کپی متن</button>',
                '<a class="ct-btn" href="' + escapeHtml(rawUrl) + '" target="_blank" rel="noopener">Raw</a>',
                '<button class="ct-btn" type="button" id="ct-copy-paste-link">کپی لینک</button>',
                "</div>",
                '<div class="ct-preview"><pre id="ct-paste-code" class="ct-code-preview" dir="ltr"></pre></div>'
            ].join("");
            var pre = $("ct-paste-code");
            if (pre) pre.textContent = String(paste.body || "");
            var copyBody = $("ct-copy-paste-body");
            if (copyBody) copyBody.addEventListener("click", function () { copyText(String(paste.body || ""), function () {}); });
            var copyLink = $("ct-copy-paste-link");
            if (copyLink) copyLink.addEventListener("click", function () { copyText(window.location.href, function () {}); });
        }

        load("");
    }

    if (tool === "files") {
        initFilesOwner();
    } else if (tool === "paste") {
        initPasteOwner();
    } else if (tool === "public-file") {
        initPublicFile();
    } else if (tool === "public-paste") {
        initPublicPaste();
    }
})();
