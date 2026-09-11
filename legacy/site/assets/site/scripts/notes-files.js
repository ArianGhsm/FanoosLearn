(function () {
    "use strict";

    function $(id) {
        return document.getElementById(id);
    }

    var guard = $("ndh-guard");
    var app = $("ndh-app");
    var rootList = $("ndh-root-list");
    var currentPathLabel = $("ndh-current-path-label");
    var createFolderButton = $("ndh-create-folder");
    var refreshButton = $("ndh-refresh");
    var pickFilesButton = $("ndh-pick-files");
    var uploadInput = $("ndh-upload-input");
    var uploadQueue = $("ndh-upload-queue");
    var uploadSubmit = $("ndh-upload-submit");
    var breadcrumbs = $("ndh-breadcrumbs");
    var searchInput = $("ndh-search-input");
    var feedback = $("ndh-feedback");
    var entriesRoot = $("ndh-entries");
    var empty = $("ndh-empty");

    if (!guard || !app || !rootList || !entriesRoot || !empty) {
        return;
    }

    var authApi = window.Dent1402Auth && typeof window.Dent1402Auth === "object" ? window.Dent1402Auth : null;
    var siteApi = window.Dent1402Site && typeof window.Dent1402Site === "object" ? window.Dent1402Site : null;
    var params = new URLSearchParams(window.location.search || "");
    var requestedPath = String(params.get("path") || "").trim();
    var requestedCohort = String(params.get("cohort") || "").trim();
    if (requestedCohort === "main" || requestedCohort === "dentistry-1402") {
        requestedCohort = "1402";
    } else if (requestedCohort === "dentistry-1403") {
        requestedCohort = "1403";
    } else if (requestedCohort === "dentistry-1404") {
        requestedCohort = "1404";
    }

    var state = {
        authKey: "",
        loading: false,
        browseRequestSeq: 0,
        activeBrowseRequestSeq: 0,
        busy: false,
        uploadResumeTimer: 0,
        currentPath: "",
        breadcrumbs: [],
        entries: [],
        filteredEntries: [],
        roots: ["1402", "1403", "1404", "prosthesis-1402"],
        uploadItems: [],
        searchQuery: "",
        searchDebounceTimer: 0,
        scopeCohort: requestedCohort,
        downloadHost: null,
        missingDirectory: false
    };

    function authSnapshotKey() {
        if (!authApi || typeof authApi.getState !== "function") {
            return "anon";
        }
        var snapshot = authApi.getState();
        var studentNumber = snapshot && snapshot.user && snapshot.user.studentNumber
            ? String(snapshot.user.studentNumber)
            : "";
        return String(snapshot && snapshot.status ? snapshot.status : "unknown") + ":" + studentNumber;
    }

    function loginUrl() {
        if (authApi && typeof authApi.loginUrl === "function") {
            return authApi.loginUrl(window.location.pathname + window.location.search);
        }
        return "/account/";
    }

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

    function formatNumber(value) {
        return Number(value || 0).toLocaleString("fa-IR");
    }

    function formatBytes(value) {
        var bytes = Number(value || 0);
        if (!Number.isFinite(bytes) || bytes <= 0) {
            return "۰ بایت";
        }

        var units = ["بایت", "KB", "MB", "GB", "TB"];
        var index = 0;
        while (bytes >= 1024 && index < units.length - 1) {
            bytes = bytes / 1024;
            index += 1;
        }

        var fixed = bytes >= 10 || index === 0
            ? Math.round(bytes)
            : Math.round(bytes * 10) / 10;
        return String(fixed)
            .replace(/\B(?=(\d{3})+(?!\d))/g, ",")
            .replace(/\d/g, function (digit) {
                return "۰۱۲۳۴۵۶۷۸۹"[digit];
            }) + " " + units[index];
    }

    function formatSpeed(value) {
        var bps = Number(value || 0);
        if (!Number.isFinite(bps) || bps <= 0) {
            return "—";
        }
        return formatBytes(bps) + "/ث";
    }

    function formatEta(seconds) {
        var value = Number(seconds);
        if (!Number.isFinite(value) || value < 0) {
            return "—";
        }
        if (value < 60) {
            return Math.max(1, Math.round(value)).toLocaleString("fa-IR") + " ثانیه";
        }

        var minutes = Math.floor(value / 60);
        var remain = Math.round(value % 60);
        if (minutes < 60) {
            return minutes.toLocaleString("fa-IR") + " دقیقه" + (remain ? " و " + remain.toLocaleString("fa-IR") + " ثانیه" : "");
        }

        var hours = Math.floor(minutes / 60);
        minutes = minutes % 60;
        return hours.toLocaleString("fa-IR") + " ساعت" + (minutes ? " و " + minutes.toLocaleString("fa-IR") + " دقیقه" : "");
    }

    function formatPercent(value) {
        var number = Number(value);
        if (!Number.isFinite(number)) {
            number = 0;
        }
        return formatNumber(Math.max(0, Math.min(100, Math.round(number))));
    }

    function isOffline() {
        return typeof navigator !== "undefined" && navigator && navigator.onLine === false;
    }

    function createUploadSignal(code, message) {
        var error = new Error(message || code || "upload");
        error.code = code || "upload";
        return error;
    }

    function request(action, method, payload) {
        var url = "/api/notes_api.php?action=" + encodeURIComponent(action);
        var options = {
            method: method,
            credentials: "same-origin",
            headers: {
                Accept: "application/json"
            }
        };
        var data = Object.assign({}, payload || {});
        if (state.scopeCohort) {
            data.cohort = state.scopeCohort;
        }

        if (method === "GET") {
            Object.keys(data).forEach(function (key) {
                var value = data[key];
                if (value !== undefined && value !== null && String(value) !== "") {
                    url += "&" + encodeURIComponent(key) + "=" + encodeURIComponent(String(value));
                }
            });
        } else {
            options.headers["Content-Type"] = "application/x-www-form-urlencoded; charset=UTF-8";
            options.body = new URLSearchParams(Object.assign({ action: action }, data));
        }

        return fetch(url, options).then(parseJsonResponse).catch(networkErrorResponse);
    }

    function requestFormData(action, formData) {
        var url = "/api/notes_api.php?action=" + encodeURIComponent(action);
        if (state.scopeCohort) {
            formData.set("cohort", state.scopeCohort);
        }
        return fetch(url, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                Accept: "application/json"
            },
            body: formData
        }).then(parseJsonResponse).catch(networkErrorResponse);
    }

    function setFeedback(text, kind) {
        if (!feedback) {
            return;
        }
        feedback.textContent = text || "";
        feedback.dataset.kind = kind || "";
        feedback.hidden = !text;
    }

    function setGuard(kind, title, copy, actionHref, actionLabel) {
        app.hidden = true;
        guard.hidden = false;
        guard.innerHTML = [
            '<div class="notes-manage-panel__head">',
            '  <h4>' + escapeHtml(title || "") + '</h4>',
            '  <p>' + escapeHtml(copy || "") + '</p>',
            '</div>',
            actionHref && actionLabel
                ? '<a class="card-btn" href="' + escapeHtml(actionHref) + '">' + escapeHtml(actionLabel) + '</a>'
                : ""
        ].join("");
        guard.dataset.kind = kind || "";
    }

    function showApp() {
        guard.hidden = true;
        app.hidden = false;
    }

    function escapeHtml(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function formatDate(value) {
        if (!value) {
            return "بدون تاریخ";
        }
        try {
            return new Intl.DateTimeFormat("fa-IR", {
                dateStyle: "short",
                timeStyle: "short"
            }).format(new Date(value));
        } catch (_error) {
            return value;
        }
    }

    function currentScopeRoot() {
        return state.downloadHost && state.downloadHost.scopeRoot
            ? String(state.downloadHost.scopeRoot)
            : "";
    }

    function availableRoots() {
        var scopeRoot = currentScopeRoot();
        var canManageAll = !!(state.downloadHost && state.downloadHost.canManageAllRoots);
        if (scopeRoot && !canManageAll) {
            return [scopeRoot];
        }
        return state.roots.slice();
    }

    function currentRoot() {
        var path = String(state.currentPath || "");
        if (!path) {
            return "";
        }
        return path.split("/")[0] || "";
    }

    function filterEntries() {
        var query = String(state.searchQuery || "").trim().toLowerCase();
        if (!query) {
            state.filteredEntries = state.entries.slice();
            return;
        }
        state.filteredEntries = state.entries.filter(function (entry) {
            return String(entry.name || "").toLowerCase().indexOf(query) !== -1;
        });
    }

    function updateUrl() {
        var next = new URL(window.location.href);
        if (state.currentPath) {
            next.searchParams.set("path", state.currentPath);
        } else {
            next.searchParams.delete("path");
        }
        if (state.scopeCohort) {
            next.searchParams.set("cohort", state.scopeCohort);
        } else {
            next.searchParams.delete("cohort");
        }
        window.history.replaceState({}, "", next.pathname + next.search);
    }

    function renderRoots() {
        rootList.innerHTML = "";
        availableRoots().forEach(function (root) {
            var button = document.createElement("button");
            button.type = "button";
            button.className = "ndh-root-btn" + (currentRoot() === root ? " is-active" : "");
            button.setAttribute("data-root-path", root);
            button.innerHTML = "<span><strong>" + escapeHtml(root) + "</strong><small>مسیر ریشه منابع</small></span>";
            rootList.appendChild(button);
        });
    }

    function renderBreadcrumbs() {
        breadcrumbs.innerHTML = "";
        var items = Array.isArray(state.breadcrumbs) ? state.breadcrumbs : [];
        items.forEach(function (item, index) {
            var button = document.createElement("button");
            button.type = "button";
            button.className = index === items.length - 1 ? "is-active" : "";
            button.setAttribute("data-browse-path", String(item.path || ""));
            button.textContent = String(item.label || "");
            breadcrumbs.appendChild(button);
        });
    }

    function queueStateLabel(item) {
        if (item.status === "waiting") {
            return "در انتظار تلاش خودکار";
        }
        if (item.status === "uploading") {
            return "در حال انتقال";
        }
        if (item.status === "finalizing") {
            return "ثبت روی هاست";
        }
        if (item.status === "done") {
            return "تکمیل شد";
        }
        if (item.status === "error") {
            return "با خطا مواجه شد";
        }
        return "آماده ارسال";
    }

    function queueProgressPercent(item) {
        var value = Number(item.progress || 0);
        if (!Number.isFinite(value)) {
            return 0;
        }
        return Math.max(0, Math.min(100, value));
    }

    function uploadQueueStats() {
        var total = state.uploadItems.length;
        var done = state.uploadItems.filter(function (item) {
            return item.status === "done";
        }).length;
        var active = state.uploadItems.filter(function (item) {
            return item.status === "uploading" || item.status === "finalizing";
        }).length;
        var totalBytes = state.uploadItems.reduce(function (sum, item) {
            return sum + Number(item.size || 0);
        }, 0);
        var transferredBytes = state.uploadItems.reduce(function (sum, item) {
            return sum + Number(item.uploadedBytes || 0);
        }, 0);

        return {
            total: total,
            done: done,
            active: active,
            totalBytes: totalBytes,
            transferredBytes: transferredBytes
        };
    }

    function clearUploadResumeTimer() {
        if (!state.uploadResumeTimer) {
            return;
        }
        window.clearTimeout(state.uploadResumeTimer);
        state.uploadResumeTimer = 0;
    }

    function hasWaitingUploads() {
        return state.uploadItems.some(function (item) {
            return item.status === "waiting";
        });
    }

    function uploadRetryDelayMs(item) {
        var attempts = Math.max(1, Number(item && item.retryCount || 0));
        if (attempts <= 1) {
            return 4000;
        }
        if (attempts === 2) {
            return 7000;
        }
        if (attempts === 3) {
            return 12000;
        }
        return 20000;
    }

    function waitingUploadMessage(item) {
        if (isOffline()) {
            return "اتصال اینترنت قطع شده است. این فایل در صف می‌ماند و بعد از برگشت اتصال خودکار دوباره تلاش می‌شود.";
        }
        if (queueProgressPercent(item) >= 99) {
            return "ارتباط در مرحله نهایی‌سازی قطع شد. به محض پایدار شدن اتصال، آپلود خودکار دوباره تلاش می‌شود.";
        }
        return "ارتباط آپلود دچار اختلال شد. بعد از پایدار شدن اتصال، آپلود خودکار دوباره تلاش می‌شود.";
    }

    function scheduleUploadResume(delayMs) {
        clearUploadResumeTimer();
        if (!hasWaitingUploads()) {
            return;
        }
        state.uploadResumeTimer = window.setTimeout(function () {
            state.uploadResumeTimer = 0;
            if (state.busy || !hasWaitingUploads()) {
                return;
            }
            uploadPendingFiles(true);
        }, Math.max(1200, Number(delayMs || 0)));
    }

    function markUploadWaiting(item, message) {
        item.xhr = null;
        item.status = "waiting";
        item.error = message;
        item.speedBps = 0;
        item.etaSeconds = NaN;
        item.retryCount = Math.max(0, Number(item.retryCount || 0)) + 1;
        renderUploadQueue();
        scheduleUploadResume(isOffline() ? 2500 : uploadRetryDelayMs(item));
        return createUploadSignal("waiting", message);
    }

    function renderUploadQueue() {
        if (!state.uploadItems.length) {
            uploadQueue.innerHTML = '<p class="archive-empty">هنوز فایلی برای آپلود انتخاب نشده است.</p>';
            return;
        }

        var stats = uploadQueueStats();
        var markup = [
            '<div class="ndh-upload-summary">',
            '  <article class="ndh-upload-stat"><strong>' + escapeHtml(formatNumber(stats.total)) + '</strong><small>فایل در صف</small></article>',
            '  <article class="ndh-upload-stat"><strong>' + escapeHtml(formatBytes(stats.transferredBytes)) + '</strong><small>حجم منتقل‌شده</small></article>',
            '  <article class="ndh-upload-stat"><strong>' + escapeHtml(stats.active ? (formatNumber(stats.active) + " فعال") : (hasWaitingUploads() ? (formatNumber(state.uploadItems.filter(function (item) { return item.status === "waiting"; }).length) + " منتظر اتصال") : (stats.done ? (formatNumber(stats.done) + " کامل") : "آماده"))) + '</strong><small>وضعیت فعلی</small></article>',
            "</div>"
        ];

        state.uploadItems.forEach(function (item) {
            var progress = queueProgressPercent(item);
            var statsLine = [
                '<span>انتقال: ' + escapeHtml(formatBytes(item.uploadedBytes || 0)) + ' / ' + escapeHtml(formatBytes(item.size || 0)) + '</span>',
                '<span>پیشرفت: ' + escapeHtml(formatPercent(progress)) + '%</span>',
                '<span>سرعت: ' + escapeHtml(formatSpeed(item.speedBps)) + '</span>',
                '<span>زمان باقی‌مانده: ' + escapeHtml(formatEta(item.etaSeconds)) + '</span>'
            ];

            if (item.status === "done") {
                statsLine[0] = '<span>حجم: ' + escapeHtml(formatBytes(item.size || 0)) + '</span>';
                statsLine[2] = '<span>مسیر: ' + escapeHtml(item.relativePath || state.currentPath || "/") + '</span>';
                statsLine[3] = '<span>اتمام: ' + escapeHtml(formatDate(item.completedAt || "")) + '</span>';
            } else if (item.status === "finalizing") {
                statsLine[3] = '<span>در حال ثبت نهایی روی هاست؛ برای فایل‌های حجیم ممکن است چند دقیقه طول بکشد.</span>';
            } else if (item.status === "waiting") {
                statsLine[2] = '<span>وضعیت: ' + escapeHtml(item.error || waitingUploadMessage(item)) + '</span>';
                statsLine[3] = '<span>تلاش دوباره: به‌محض برگشت اتصال، خودکار دوباره انجام می‌شود.</span>';
            } else if (item.status === "error") {
                statsLine[2] = '<span>خطا: ' + escapeHtml(item.error || "خطای نامشخص") + '</span>';
                statsLine[3] = '<span>ارسال‌شده: ' + escapeHtml(formatBytes(item.uploadedBytes || 0)) + '</span>';
            }

            var actions = [];
            if (item.publicUrl) {
                actions.push('<a href="' + escapeHtml(item.publicUrl) + '" target="_blank" rel="noopener noreferrer">لینک مستقیم</a>');
                actions.push('<button type="button" data-copy-link="' + escapeHtml(item.publicUrl) + '">کپی لینک</button>');
            }
            if (item.status === "uploading" || item.status === "finalizing") {
                actions.push('<button class="is-danger" type="button" data-remove-upload="' + escapeHtml(item.id) + '">لغو آپلود</button>');
            }
            if (item.status !== "uploading" && item.status !== "finalizing") {
                actions.push('<button class="is-danger" type="button" data-remove-upload="' + escapeHtml(item.id) + '">حذف از صف</button>');
            }

            markup.push([
                '<article class="ndh-upload-item" data-state="' + escapeHtml(item.status || "queued") + '">',
                '  <div class="ndh-upload-top">',
                '    <strong>' + escapeHtml(item.file.name) + '</strong>',
                '    <span class="ndh-upload-state">' + escapeHtml(queueStateLabel(item)) + '</span>',
                '  </div>',
                '  <div class="ndh-upload-progress"><span style="width:' + progress.toFixed(1) + '%"></span></div>',
                '  <div class="ndh-upload-stats">' + statsLine.join("") + '</div>',
                actions.length ? ('<div class="ndh-upload-actions">' + actions.join("") + '</div>') : "",
                "</article>"
            ].join(""));
        });

        uploadQueue.innerHTML = markup.join("");
    }

    function entryMeta(entry) {
        var parts = [];
        parts.push(entry.type === "dir" ? "پوشه" : (entry.sizeLabel || "فایل"));
        if (entry.modifiedAt) {
            parts.push(formatDate(entry.modifiedAt));
        }
        return parts.join(" • ");
    }

    function iconLabel(entry) {
        if (entry.type === "dir") {
            return "DIR";
        }
        var name = String(entry.name || "");
        var match = /\.([^.]+)$/.exec(name);
        return match && match[1] ? match[1].slice(0, 3).toUpperCase() : "FILE";
    }

    function renderEntries() {
        filterEntries();
        renderRoots();
        renderBreadcrumbs();
        entriesRoot.innerHTML = "";
        var fragment = document.createDocumentFragment();
        currentPathLabel.textContent = state.currentPath
            ? (state.currentPath + (state.missingDirectory ? " • هنوز ساخته نشده" : ""))
            : "ریشه منابع";

        if (!state.filteredEntries.length) {
            empty.hidden = false;
            empty.textContent = state.loading
                ? "در حال دریافت فایل‌ها..."
                : (state.missingDirectory
                    ? "این پوشه هنوز روی هاست دانلود ساخته نشده است. با اولین آپلود فایل یا ساخت زیرپوشه، مسیر به‌صورت خودکار ایجاد می‌شود."
                    : "در این پوشه هنوز فایل یا پوشه‌ای وجود ندارد.");
            return;
        }

        empty.hidden = true;
        state.filteredEntries.forEach(function (entry) {
            var article = document.createElement("article");
            article.className = "ndh-entry";
            article.dataset.type = String(entry.type || "file");
            article.dataset.path = String(entry.relativePath || "");

            var actions = [];
            if (entry.type === "dir") {
                actions.push('<button class="is-open" type="button" data-browse-path="' + escapeHtml(entry.relativePath || "") + '">باز کردن</button>');
            } else if (entry.publicUrl) {
                actions.push('<a class="is-open" href="' + escapeHtml(entry.publicUrl) + '" target="_blank" rel="noopener noreferrer">لینک مستقیم</a>');
                actions.push('<button type="button" data-copy-link="' + escapeHtml(entry.publicUrl) + '">کپی لینک</button>');
            }
            actions.push('<button type="button" data-rename-path="' + escapeHtml(entry.relativePath || "") + '">تغییر نام</button>');
            actions.push('<button class="is-danger" type="button" data-delete-path="' + escapeHtml(entry.relativePath || "") + '" data-delete-type="' + escapeHtml(entry.type || "file") + '">حذف</button>');

            article.innerHTML = [
                '<div class="ndh-entry-main">',
                '  <div class="ndh-entry-icon">' + escapeHtml(iconLabel(entry)) + '</div>',
                '  <div class="ndh-entry-copy">',
                '    <strong>' + escapeHtml(entry.name || "") + '</strong>',
                '    <small>' + escapeHtml(entryMeta(entry)) + '</small>',
                '  </div>',
                '</div>',
                '<div class="ndh-entry-actions">' + actions.join("") + '</div>'
            ].join("");
            fragment.appendChild(article);
        });
        entriesRoot.appendChild(fragment);
    }

    function loadBrowse(path, options) {
        var requestSeq = state.browseRequestSeq + 1;
        state.browseRequestSeq = requestSeq;
        state.activeBrowseRequestSeq = requestSeq;
        state.loading = true;
        renderEntries();
        if (!(options && options.silent)) {
            setFeedback("", "");
        }

        var nextPath = String(path == null ? "" : path).trim();
        if (!nextPath && currentScopeRoot() && !(state.downloadHost && state.downloadHost.canManageAllRoots)) {
            nextPath = currentScopeRoot();
        }

        return request("downloadHostBrowse", "GET", {
            path: nextPath
        }).then(function (payload) {
            if (requestSeq !== state.activeBrowseRequestSeq) {
                return;
            }
            if (payload && (payload.loggedOut || payload.httpStatus === 401)) {
                setGuard("login", "نیاز به ورود", "برای استفاده از فایل‌منیجر منابع باید وارد حساب مجاز شوید.", loginUrl(), "ورود");
                return;
            }
            if (payload && payload.httpStatus === 403) {
                setGuard("forbidden", "دسترسی مجاز نیست", (payload && payload.error) || "این بخش فقط برای حساب‌های مجاز فعال است.");
                return;
            }
            if (!payload || !payload.success || !payload.browse) {
                throw new Error((payload && payload.error) || "دریافت فایل‌های هاست دانلود ناموفق بود.");
            }

            state.downloadHost = payload.downloadHost || null;
            state.currentPath = String(payload.browse.currentPath || "");
            state.breadcrumbs = Array.isArray(payload.browse.breadcrumbs) ? payload.browse.breadcrumbs : [];
            state.entries = Array.isArray(payload.browse.entries) ? payload.browse.entries : [];
            state.missingDirectory = !!(payload.browse && payload.browse.missingDirectory);
            showApp();
            updateUrl();
            renderEntries();
            if (state.missingDirectory) {
                setFeedback(
                    payload.browse.notice || "این پوشه هنوز ساخته نشده است و با اولین آپلود یا ساخت زیرپوشه ایجاد می‌شود.",
                    ""
                );
            } else if (!(options && options.silent)) {
                setFeedback("", "");
            }
        }).catch(function (error) {
            if (requestSeq !== state.activeBrowseRequestSeq) {
                return;
            }
            state.missingDirectory = false;
            setGuard("error", "خطا در ارتباط با هاست دانلود", error && error.message ? error.message : "فایل‌منیجر با خطا مواجه شد.");
        }).finally(function () {
            if (requestSeq !== state.activeBrowseRequestSeq) {
                return;
            }
            state.loading = false;
            renderEntries();
        });
    }

    function addFilesToQueue(fileList) {
        Array.prototype.forEach.call(fileList || [], function (file) {
            state.uploadItems.push({
                id: Math.random().toString(36).slice(2),
                file: file,
                size: Number(file.size || 0),
                status: "queued",
                progress: 0,
                uploadedBytes: 0,
                speedBps: 0,
                etaSeconds: NaN,
                error: "",
                publicUrl: "",
                relativePath: "",
                completedAt: "",
                xhr: null,
                canceled: false,
                retryCount: 0,
                targetPath: ""
            });
        });
        renderUploadQueue();
    }

    function removeUploadItem(itemId) {
        var active = state.uploadItems.filter(function (item) {
            return item.id === itemId && (item.status === "uploading" || item.status === "finalizing");
        })[0] || null;
        if (active && active.xhr) {
            active.canceled = true;
            active.xhr.abort();
            return;
        }
        state.uploadItems = state.uploadItems.filter(function (item) {
            return item.id !== itemId;
        });
        if (!hasWaitingUploads()) {
            clearUploadResumeTimer();
        }
        renderUploadQueue();
    }

    function prepareUploadRequest(item) {
        return request("prepareHostUpload", "POST", {
            path: String(item && item.targetPath || ""),
            fileName: item && item.file && item.file.name ? String(item.file.name) : "file",
            fileSize: Number(item && (item.size || (item.file && item.file.size)) || 0),
            mimeType: item && item.file && item.file.type ? String(item.file.type) : "application/octet-stream"
        });
    }

    function probeDirectUploadPlan(uploadPlan) {
        return Promise.resolve(uploadPlan);
    }

    function openUploadXhr(xhr, uploadPlan, item) {
        var mode = uploadPlan && uploadPlan.mode ? String(uploadPlan.mode) : "relay";
        var targetUrl = uploadPlan && uploadPlan.url ? String(uploadPlan.url) : "";
        if (!targetUrl) {
            throw createUploadSignal("prepare-error", "آدرس آپلود معتبر نیست.");
        }

        xhr.open("POST", targetUrl, true);
        xhr.withCredentials = mode !== "direct";
        xhr.setRequestHeader("Accept", "application/json");
        xhr.setRequestHeader("Content-Type", item.file && item.file.type ? item.file.type : "application/octet-stream");
        if (mode !== "direct") {
            var originalName = item && item.file && item.file.name ? String(item.file.name) : "file";
            var desiredName = item && item.fileName ? String(item.fileName) : originalName;
            xhr.setRequestHeader("X-Dent-Upload-Name", encodeURIComponent(desiredName));
        }
    }

    function buildUploadRequestBody(uploadPlan, item) {
        return item.file;
    }

    // Large files are split into short chunks so each request finishes well under
    // the download-host WAF's slow-upload threshold (which 403s long single POSTs).
    var DIRECT_CHUNK_THRESHOLD = 1536 * 1024;
    var DIRECT_CHUNK_SIZE = 1024 * 1024;

    function encodeChunkBase64Url(blob) {
        return new Promise(function (resolve, reject) {
            var reader = new FileReader();
            reader.onerror = function () { reject(createUploadSignal("encode-error", "خواندن chunk فایل انجام نشد.")); };
            reader.onload = function () {
                var value = String(reader.result || "");
                var comma = value.indexOf(",");
                var encoded = comma >= 0 ? value.slice(comma + 1) : "";
                if (!encoded) { reject(createUploadSignal("encode-error", "کدگذاری chunk فایل انجام نشد.")); return; }
                resolve(encoded.replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/g, ""));
            };
            reader.readAsDataURL(blob);
        });
    }

    function finishUpload(response, item, resolve, reject) {
        item.xhr = null;
        if (response && (response.loggedOut || response.httpStatus === 401)) {
            item.status = "error";
            item.error = "نشست شما منقضی شده است.";
            item.speedBps = 0;
            item.etaSeconds = NaN;
            renderUploadQueue();
            setGuard("login", "نیاز به ورود", "برای استفاده از فایل‌منیجر منابع باید وارد حساب مجاز شوید.", loginUrl(), "ورود");
            reject(createUploadSignal("prepare-error", item.error));
            return;
        }
        if (response && response.httpStatus === 403) {
            item.status = "error";
            item.error = response.error || "اجازه آپلود فایل در این بخش را ندارید.";
            item.speedBps = 0;
            item.etaSeconds = NaN;
            renderUploadQueue();
            setGuard("forbidden", "دسترسی مجاز نیست", response.error || "این بخش فقط برای حساب‌های مجاز فعال است.");
            reject(createUploadSignal("prepare-error", item.error));
            return;
        }
        if (!response || !response.success || !response.file) {
            item.status = "error";
            item.error = (response && response.error) || "آپلود فایل کامل نشد.";
            item.speedBps = 0;
            item.etaSeconds = NaN;
            renderUploadQueue();
            reject(new Error(item.error));
            return;
        }
        item.status = "done";
        item.progress = 100;
        item.uploadedBytes = Number(item.size || item.uploadedBytes || 0);
        item.speedBps = 0;
        item.etaSeconds = 0;
        item.publicUrl = String(response.file.publicUrl || "");
        item.relativePath = String(response.file.relativePath || response.file.relativeDir || item.targetPath || "");
        item.completedAt = new Date().toISOString();
        renderUploadQueue();
        resolve(response);
    }

    function sendChunkedUpload(uploadPlan, item, startedAt) {
        var baseUrl = String(uploadPlan.url || "");
        var total = Number(item.size || (item.file && item.file.size) || 0);
        var chunkSize = Math.max(256 * 1024, Number(uploadPlan.chunkBytes || DIRECT_CHUNK_SIZE));
        var chunkCount = Math.max(1, Math.ceil(total / chunkSize));
        var ctype = item.file && item.file.type ? item.file.type : "application/octet-stream";
        var streamMode = String(uploadPlan.mode || "") === "stream";

        return new Promise(function (resolve, reject) {
            function sendChunk(index) {
                if (item.canceled) {
                    reject(createUploadSignal("canceled", "آپلود توسط کاربر لغو شد."));
                    return;
                }
                var start = index * chunkSize;
                var end = Math.min(total, start + chunkSize);
                var blob = item.file.slice(start, end);
                var url = baseUrl + (baseUrl.indexOf("?") === -1 ? "?" : "&")
                    + "chunkIndex=" + index + "&chunkCount=" + chunkCount
                            + "&chunkStart=" + start + "&chunkEnd=" + end + "&chunkEncoding=base64url";
                encodeChunkBase64Url(blob).then(function (encodedBody) {
                var xhr = new XMLHttpRequest();
                item.xhr = xhr;
                xhr.open("POST", url, true);
                xhr.withCredentials = streamMode;
                xhr.setRequestHeader("Accept", "application/json");
                xhr.setRequestHeader("Content-Type", "text/plain; charset=us-ascii");
                xhr.setRequestHeader("X-Dent-Chunk-Encoding", "base64url");

                xhr.upload.onprogress = function (event) {
                    if (!event.lengthComputable) return;
                    var loaded = start + Number(event.loaded || 0);
                    var elapsed = Math.max(0.25, (Date.now() - startedAt) / 1000);
                    var speed = loaded / elapsed;
                    item.uploadedBytes = loaded;
                    item.progress = total > 0 ? Math.min(99.2, (loaded / total) * 100) : item.progress;
                    item.speedBps = speed;
                    item.etaSeconds = speed > 0 && total > loaded ? (total - loaded) / speed : 0;
                    renderUploadQueue();
                };

                xhr.onload = function () {
                    var response = {};
                    try { response = JSON.parse(xhr.responseText || "{}"); }
                    catch (_e) { response = { success: false, error: "پاسخ آپلود معتبر نبود." }; }
                    response.httpStatus = xhr.status;
                    item.xhr = null;

                    if (xhr.status === 401 || xhr.status === 403 || !response.success) {
                        // Surface auth/permission/other failures through the shared handler.
                        if (xhr.status === 401) response.loggedOut = true;
                        finishUpload(response, item, function () {}, reject);
                        return;
                    }
                    if (index >= chunkCount - 1) {
                        // Last chunk returns the final file payload.
                        finishUpload(response, item, resolve, reject);
                        return;
                    }
                    item.progress = total > 0 ? Math.min(99.2, (end / total) * 100) : item.progress;
                    renderUploadQueue();
                    sendChunk(index + 1);
                };
                xhr.onerror = function () {
                    item.xhr = null;
                    reject(markUploadWaiting(item, waitingUploadMessage(item)));
                };
                xhr.onabort = function () {
                    item.xhr = null;
                    if (item.canceled) {
                        reject(createUploadSignal("canceled", "آپلود توسط کاربر لغو شد."));
                        return;
                    }
                    reject(markUploadWaiting(item, waitingUploadMessage(item)));
                };
                xhr.send(encodedBody);
                }).catch(reject);
            }
            sendChunk(0);
        });
    }

    function uploadItem(item) {
        return new Promise(function (resolve, reject) {
            var finalizingProgress = 99.2;
            item.targetPath = String(item.targetPath || state.currentPath || "");
            item.status = "uploading";
            item.progress = 0;
            item.uploadedBytes = 0;
            item.speedBps = 0;
            item.etaSeconds = NaN;
            item.error = "";
            item.publicUrl = "";
            item.relativePath = "";
            item.completedAt = "";
            item.canceled = false;
            renderUploadQueue();

            prepareUploadRequest(item).then(function (prepareResponse) {
                if (prepareResponse && (prepareResponse.loggedOut || prepareResponse.httpStatus === 401)) {
                    item.status = "error";
                    item.error = "نشست شما منقضی شده است.";
                    item.speedBps = 0;
                    item.etaSeconds = NaN;
                    item.xhr = null;
                    renderUploadQueue();
                    setGuard("login", "نیاز به ورود", "برای استفاده از فایل‌منیجر منابع باید وارد حساب مجاز شوید.", loginUrl(), "ورود");
                    reject(createUploadSignal("prepare-error", item.error));
                    return;
                }
                if (prepareResponse && prepareResponse.httpStatus === 403) {
                    item.status = "error";
                    item.error = prepareResponse.error || "اجازه آپلود فایل در این بخش را ندارید.";
                    item.speedBps = 0;
                    item.etaSeconds = NaN;
                    item.xhr = null;
                    renderUploadQueue();
                    setGuard("forbidden", "دسترسی مجاز نیست", prepareResponse.error || "این بخش فقط برای حساب‌های مجاز فعال است.");
                    reject(createUploadSignal("prepare-error", item.error));
                    return;
                }
                if (!prepareResponse || !prepareResponse.success || !prepareResponse.upload || !prepareResponse.upload.url) {
                    item.status = "error";
                    item.error = prepareResponse && prepareResponse.error ? prepareResponse.error : "آماده‌سازی آپلود انجام نشد.";
                    item.speedBps = 0;
                    item.etaSeconds = NaN;
                    item.xhr = null;
                    renderUploadQueue();
                    reject(createUploadSignal("prepare-error", item.error));
                    return;
                }

                probeDirectUploadPlan(prepareResponse.upload).then(function (uploadPlan) {
                    var startedAt = Date.now();
                    var planMode = uploadPlan && uploadPlan.mode ? String(uploadPlan.mode) : "relay";
                    var fileSize = Number(item.size || (item.file && item.file.size) || 0);
                    if ((planMode === "stream" || (planMode === "direct" && fileSize > DIRECT_CHUNK_THRESHOLD)) && item.file) {
                        // The stream plan forwards every bounded raw-body chunk to
                        // FTP immediately; no complete file is staged on this host.
                        sendChunkedUpload(uploadPlan, item, startedAt).then(function (response) {
                            // finishUpload already resolved item state; nothing else to do.
                            resolve(response);
                        }).catch(function (error) {
                            reject(error);
                        });
                        return;
                    }
                    var uploadBody = buildUploadRequestBody(uploadPlan, item);
                    item.xhr = new XMLHttpRequest();
                    var xhr = item.xhr;
                    openUploadXhr(xhr, uploadPlan, item);

                    xhr.upload.onprogress = function (event) {
                        if (!event.lengthComputable) {
                            return;
                        }

                        var loaded = Number(event.loaded || 0);
                        var total = Number(event.total || item.size || 0);
                        var elapsed = Math.max(0.25, (Date.now() - startedAt) / 1000);
                        var speed = loaded / elapsed;
                        item.uploadedBytes = loaded;
                        item.progress = total > 0 ? (loaded / total) * 100 : item.progress;
                        item.speedBps = speed;
                        item.etaSeconds = speed > 0 && total > loaded ? (total - loaded) / speed : 0;
                        if (item.progress >= 99.9) {
                            item.status = "finalizing";
                            item.progress = finalizingProgress;
                            item.etaSeconds = 0;
                        }
                        renderUploadQueue();
                    };

                    xhr.upload.onload = function () {
                        item.uploadedBytes = Number(item.size || item.uploadedBytes || 0);
                        item.progress = finalizingProgress;
                        item.status = "finalizing";
                        item.speedBps = 0;
                        item.etaSeconds = 0;
                        renderUploadQueue();
                    };

                    xhr.onload = function () {
                        var response = {};
                        try {
                            response = JSON.parse(xhr.responseText || "{}");
                        } catch (_error) {
                            response = { success: false, error: "پاسخ آپلود معتبر نبود." };
                        }
                        item.xhr = null;
                        response.httpStatus = xhr.status;

                        if (response && (response.loggedOut || response.httpStatus === 401)) {
                            item.status = "error";
                            item.error = "نشست شما منقضی شده است.";
                            item.speedBps = 0;
                            item.etaSeconds = NaN;
                            renderUploadQueue();
                            setGuard("login", "نیاز به ورود", "برای استفاده از فایل‌منیجر منابع باید وارد حساب مجاز شوید.", loginUrl(), "ورود");
                            reject(createUploadSignal("prepare-error", item.error));
                            return;
                        }
                        if (response && response.httpStatus === 403) {
                            item.status = "error";
                            item.error = response.error || "اجازه آپلود فایل در این بخش را ندارید.";
                            item.speedBps = 0;
                            item.etaSeconds = NaN;
                            renderUploadQueue();
                            setGuard("forbidden", "دسترسی مجاز نیست", response.error || "این بخش فقط برای حساب‌های مجاز فعال است.");
                            reject(createUploadSignal("prepare-error", item.error));
                            return;
                        }
                        if (!response || !response.success || !response.file) {
                            item.status = "error";
                            item.error = (response && response.error) || "آپلود فایل کامل نشد.";
                            item.speedBps = 0;
                            item.etaSeconds = NaN;
                            renderUploadQueue();
                            reject(new Error(item.error));
                            return;
                        }

                        item.status = "done";
                        item.progress = 100;
                        item.uploadedBytes = Number(item.size || item.uploadedBytes || 0);
                        item.speedBps = 0;
                        item.etaSeconds = 0;
                        item.publicUrl = String(response.file.publicUrl || "");
                        item.relativePath = String(response.file.relativePath || response.file.relativeDir || item.targetPath || "");
                        item.completedAt = new Date().toISOString();
                        renderUploadQueue();
                        resolve(response);
                    };

                    xhr.onerror = function () {
                        reject(markUploadWaiting(item, waitingUploadMessage(item)));
                    };

                    xhr.onabort = function () {
                        item.xhr = null;
                        if (item.canceled) {
                            item.status = "error";
                            item.error = "آپلود توسط کاربر لغو شد.";
                            item.speedBps = 0;
                            item.etaSeconds = NaN;
                            renderUploadQueue();
                            reject(createUploadSignal("canceled", item.error));
                            return;
                        }
                        reject(markUploadWaiting(item, waitingUploadMessage(item)));
                    };

                    xhr.send(uploadBody);
                }).catch(function (error) {
                    item.status = "error";
                    item.error = error && error.message ? error.message : "ارتباط با هاست دانلود برقرار نشد.";
                    item.speedBps = 0;
                    item.etaSeconds = NaN;
                    item.xhr = null;
                    renderUploadQueue();
                    reject(createUploadSignal("prepare-error", item.error));
                });
            }).catch(function (error) {
                if (error && (error.code === "prepare-error" || error.code === "canceled")) {
                    reject(error);
                    return;
                }
                reject(markUploadWaiting(item, waitingUploadMessage(item)));
            });
        });
    }

    function uploadPendingFiles(autoResume) {
        if (state.busy) {
            return;
        }
        if (!state.currentPath) {
            setFeedback("ابتدا یکی از پوشه‌های منابع را باز کنید و بعد آپلود را شروع کنید.", "error");
            return;
        }
        clearUploadResumeTimer();
        var pending = state.uploadItems.filter(function (item) {
            if (item.status === "queued" || item.status === "waiting") {
                return true;
            }
            return !autoResume && item.status === "error";
        });
        if (!pending.length) {
            if (!autoResume) {
                setFeedback("فایل جدیدی برای آپلود در صف وجود ندارد.", "error");
            }
            return;
        }

        state.busy = true;
        uploadSubmit.disabled = true;
        if (!autoResume) {
            setFeedback("آپلود فایل‌ها به هاست دانلود شروع شد.", "");
        }

        var chain = Promise.resolve();
        var successCount = 0;
        var failedCount = 0;
        var canceledCount = 0;
        var waitingCount = 0;
        pending.forEach(function (item) {
            chain = chain.then(function () {
                return uploadItem(item).then(function () {
                    successCount += 1;
                }).catch(function (error) {
                    if (error && error.code === "waiting") {
                        waitingCount += 1;
                        throw error;
                    }
                    var canceled = error && (error.code === "canceled" || /لغو/.test(String(error.message || "")));
                    if (canceled) {
                        canceledCount += 1;
                    } else {
                        failedCount += 1;
                    }
                    if (error && error.message) {
                        setFeedback(error.message, canceled ? "" : "error");
                    }
                });
            });
        });

        chain.then(function () {
            if (successCount > 0) {
                return loadBrowse(state.currentPath, { silent: true });
            }
            return null;
        }).catch(function (_error) {
        }).finally(function () {
            state.busy = false;
            uploadSubmit.disabled = false;
            if (waitingCount > 0) {
                setFeedback(
                    successCount > 0
                        ? (successCount.toLocaleString("fa-IR") + " فایل آپلود شد و بقیه بعد از برگشت اتصال خودکار دوباره تلاش می‌شوند.")
                        : "اتصال آپلود دچار اختلال شد. فایل‌ها در صف می‌مانند و بعد از برگشت اینترنت خودکار دوباره تلاش می‌شود.",
                    ""
                );
            } else if (successCount > 0 && failedCount === 0) {
                setFeedback(successCount.toLocaleString("fa-IR") + " فایل با موفقیت روی هاست دانلود ثبت شد.", "success");
            } else if (successCount > 0) {
                setFeedback(
                    successCount.toLocaleString("fa-IR") + " فایل آپلود شد و " + failedCount.toLocaleString("fa-IR") + " فایل خطا داشت.",
                    "success"
                );
            } else if (canceledCount > 0) {
                setFeedback("آپلود فایل از طرف کاربر لغو شد.", "");
            } else if (failedCount > 0) {
                setFeedback("هیچ فایلی با موفقیت آپلود نشد.", "error");
            }
        });
    }

    function createFolder() {
        if (state.busy || state.loading) {
            return;
        }
        if (!state.currentPath) {
            setFeedback("برای ساخت پوشه جدید ابتدا یکی از ریشه‌های منابع را باز کنید.", "error");
            return;
        }
        var name = window.prompt("نام پوشه جدید را وارد کنید:");
        if (!name) {
            return;
        }

        state.busy = true;
        request("downloadHostCreateDir", "POST", {
            path: state.currentPath,
            name: name
        }).then(function (response) {
            if (!response || !response.success) {
                throw new Error((response && response.error) || "ساخت پوشه انجام نشد.");
            }
            setFeedback(response.message || "پوشه جدید ساخته شد.", "success");
            return loadBrowse(state.currentPath, { silent: true });
        }).catch(function (error) {
            setFeedback(error && error.message ? error.message : "ساخت پوشه با خطا مواجه شد.", "error");
        }).finally(function () {
            state.busy = false;
        });
    }

    function renameEntry(path) {
        if (!path || state.busy) {
            return;
        }
        var currentName = String(path).split("/").pop() || "";
        var nextName = window.prompt("نام جدید را وارد کنید:", currentName);
        if (!nextName || nextName === currentName) {
            return;
        }

        state.busy = true;
        request("downloadHostRenameEntry", "POST", {
            path: path,
            name: nextName
        }).then(function (response) {
            if (!response || !response.success) {
                throw new Error((response && response.error) || "تغییر نام انجام نشد.");
            }
            setFeedback(response.message || "نام فایل یا پوشه تغییر کرد.", "success");
            return loadBrowse(state.currentPath, { silent: true });
        }).catch(function (error) {
            setFeedback(error && error.message ? error.message : "تغییر نام با خطا مواجه شد.", "error");
        }).finally(function () {
            state.busy = false;
        });
    }

    function deleteEntry(path, type) {
        if (!path || state.busy) {
            return;
        }
        var confirmed = window.confirm("این " + (type === "dir" ? "پوشه" : "فایل") + " حذف شود؟");
        if (!confirmed) {
            return;
        }

        state.busy = true;
        request("downloadHostDeleteEntry", "POST", {
            path: path,
            entryType: type
        }).then(function (response) {
            if (!response || !response.success) {
                throw new Error((response && response.error) || "حذف انجام نشد.");
            }
            setFeedback(response.message || "آیتم انتخابی حذف شد.", "success");
            return loadBrowse(state.currentPath, { silent: true });
        }).catch(function (error) {
            setFeedback(error && error.message ? error.message : "حذف با خطا مواجه شد.", "error");
        }).finally(function () {
            state.busy = false;
        });
    }

    function copyLink(url) {
        if (!url) {
            return;
        }
        if (navigator.clipboard && typeof navigator.clipboard.writeText === "function") {
            navigator.clipboard.writeText(url).then(function () {
                setFeedback("لینک مستقیم فایل کپی شد.", "success");
            }).catch(function () {
                setFeedback("کپی خودکار لینک ممکن نشد.", "error");
            });
            return;
        }
        window.prompt("لینک مستقیم فایل:", url);
    }

    function bindUi() {
        if (pickFilesButton && uploadInput) {
            pickFilesButton.addEventListener("click", function () {
                if (!state.busy) {
                    uploadInput.click();
                }
            });
            uploadInput.addEventListener("change", function () {
                addFilesToQueue(uploadInput.files);
                uploadInput.value = "";
            });
        }

        if (uploadSubmit) {
            uploadSubmit.addEventListener("click", uploadPendingFiles);
        }
        window.addEventListener("offline", function () {
            if (!state.busy) {
                return;
            }
            setFeedback("اتصال اینترنت قطع شد. آپلودها در صف می‌مانند و بعد از برگشت اتصال خودکار دوباره تلاش می‌شوند.", "");
        });
        window.addEventListener("online", function () {
            if (!hasWaitingUploads()) {
                return;
            }
            setFeedback("اتصال برگشت. آپلود فایل‌ها خودکار دوباره تلاش می‌شوند.", "");
            scheduleUploadResume(900);
        });
        if (uploadQueue) {
            uploadQueue.addEventListener("click", function (event) {
                var removeButton = event.target && event.target.closest ? event.target.closest("[data-remove-upload]") : null;
                var copyButton = event.target && event.target.closest ? event.target.closest("[data-copy-link]") : null;
                if (removeButton) {
                    removeUploadItem(removeButton.getAttribute("data-remove-upload") || "");
                    return;
                }
                if (copyButton) {
                    copyLink(copyButton.getAttribute("data-copy-link") || "");
                }
            });
        }

        if (createFolderButton) {
            createFolderButton.addEventListener("click", createFolder);
        }

        if (refreshButton) {
            refreshButton.addEventListener("click", function () {
                loadBrowse(state.currentPath, { silent: true });
            });
        }

        if (searchInput) {
            searchInput.addEventListener("input", function () {
                state.searchQuery = String(searchInput.value || "");
                window.clearTimeout(state.searchDebounceTimer);
                state.searchDebounceTimer = window.setTimeout(renderEntries, 150);
            });
        }

        rootList.addEventListener("click", function (event) {
            var button = event.target && event.target.closest ? event.target.closest("[data-root-path]") : null;
            if (!button) {
                return;
            }
            loadBrowse(button.getAttribute("data-root-path") || "", { silent: false });
        });

        breadcrumbs.addEventListener("click", function (event) {
            var button = event.target && event.target.closest ? event.target.closest("[data-browse-path]") : null;
            if (!button) {
                return;
            }
            loadBrowse(button.getAttribute("data-browse-path") || "", { silent: true });
        });

        entriesRoot.addEventListener("click", function (event) {
            var browseButton = event.target && event.target.closest ? event.target.closest("[data-browse-path]") : null;
            var renameButton = event.target && event.target.closest ? event.target.closest("[data-rename-path]") : null;
            var deleteButton = event.target && event.target.closest ? event.target.closest("[data-delete-path]") : null;
            var copyButton = event.target && event.target.closest ? event.target.closest("[data-copy-link]") : null;

            if (browseButton) {
                loadBrowse(browseButton.getAttribute("data-browse-path") || "", { silent: true });
                return;
            }
            if (renameButton) {
                renameEntry(renameButton.getAttribute("data-rename-path") || "");
                return;
            }
            if (deleteButton) {
                deleteEntry(
                    deleteButton.getAttribute("data-delete-path") || "",
                    deleteButton.getAttribute("data-delete-type") || "file"
                );
                return;
            }
            if (copyButton) {
                copyLink(copyButton.getAttribute("data-copy-link") || "");
            }
        });
    }

    function watchAuthChanges() {
        if (!authApi || typeof authApi.onChange !== "function") {
            return;
        }
        authApi.onChange(function () {
            var nextKey = authSnapshotKey();
            if (nextKey === state.authKey) {
                return;
            }
            state.authKey = nextKey;
            loadBrowse(state.currentPath || requestedPath, { silent: false });
        });
    }

    function boot() {
        state.authKey = authSnapshotKey();
        bindUi();
        renderUploadQueue();
        watchAuthChanges();
        loadBrowse(requestedPath, { silent: false });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot, { once: true });
    } else {
        boot();
    }
})();
