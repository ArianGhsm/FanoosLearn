(function () {
    "use strict";

    function $(id) {
        return document.getElementById(id);
    }

    var authApi = window.Dent1402Auth && typeof window.Dent1402Auth === "object"
        ? window.Dent1402Auth
        : null;
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

    function formatNumber(value) {
        return Number(value || 0).toLocaleString("fa-IR");
    }

    function formatDecimal(value, digits) {
        var number = Number(value);
        if (!Number.isFinite(number)) return "\u2014";
        return number.toLocaleString("fa-IR", {
            minimumFractionDigits: 0,
            maximumFractionDigits: typeof digits === "number" ? digits : 1
        });
    }

    function formatBytes(value) {
        var bytes = Number(value || 0);
        if (!Number.isFinite(bytes) || bytes <= 0) return "۰ بایت";
        var units = ["بایت", "KB", "MB", "GB", "TB"];
        var index = 0;
        while (bytes >= 1024 && index < units.length - 1) {
            bytes = bytes / 1024;
            index += 1;
        }
        var fixed = bytes >= 10 || index === 0 ? Math.round(bytes) : Math.round(bytes * 10) / 10;
        return String(fixed).replace(/\B(?=(\d{3})+(?!\d))/g, ",").replace(/\d/g, function (digit) {
            return "۰۱۲۳۴۵۶۷۸۹"[digit];
        }) + " " + units[index];
    }

    function formatSpeed(value) {
        var bps = Number(value || 0);
        if (!Number.isFinite(bps) || bps <= 0) return "—";
        return formatBytes(bps) + "/ث";
    }

    function formatEta(seconds) {
        var value = Number(seconds);
        if (!Number.isFinite(value) || value < 0) return "—";
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
        if (!Number.isFinite(number)) number = 0;
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

    function hasHostUsageMetrics(hostUsage) {
        if (!hostUsage || typeof hostUsage !== "object") return false;
        return hostUsage.usedBytes != null
            || hostUsage.remainingBytes != null
            || hostUsage.limitBytes != null
            || hostUsage.fileCount != null
            || hostUsage.directoryCount != null
            || hostUsage.entryCount != null
            || hostUsage.managedBytes != null
            || hostUsage.generatedAt;
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

    function folderName(path) {
        var value = String(path || "").trim().replace(/\/+$/, "");
        if (!value) return "ریشه آپلودسنتر";
        var parts = value.split("/");
        return parts[parts.length - 1] || value;
    }

    function summaryIconMarkup(kind) {
        var path = "";
        switch (String(kind || "")) {
            case "files":
                path = '<path d="M7 5.75h6.3l3 3V18.25a1.75 1.75 0 0 1-1.75 1.75h-7.8A1.75 1.75 0 0 1 5 18.25V7.5A1.75 1.75 0 0 1 6.75 5.75Z" stroke="currentColor" stroke-width="1.55" stroke-linejoin="round"/><path d="M13.2 5.75V9.1h3.1" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round"/>';
                break;
            case "links":
                path = '<path d="M8.2 12.8l-1.45 1.45a3.05 3.05 0 0 0 4.32 4.32l2.4-2.4a3.05 3.05 0 0 0 0-4.32M15.8 11.2l1.45-1.45a3.05 3.05 0 0 0-4.32-4.32l-2.4 2.4a3.05 3.05 0 0 0 0 4.32M9.7 14.3l4.6-4.6" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round"/>';
                break;
            case "size":
                path = '<path d="M10 5.6A6.9 6.9 0 1 0 16.4 10H10V5.6Z" stroke="currentColor" stroke-width="1.55" stroke-linejoin="round"/><path d="M12.1 3.9A6.9 6.9 0 0 1 18.1 9.9H12.1V3.9Z" stroke="currentColor" stroke-width="1.55" stroke-linejoin="round"/>';
                break;
            case "space":
                path = '<path d="M6.25 8.15 10 6l3.75 2.15L10 10.3 6.25 8.15Z" stroke="currentColor" stroke-width="1.55" stroke-linejoin="round"/><path d="M6.25 11.95 10 14.1l3.75-2.15M6.25 15.75 10 17.9l3.75-2.15" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round"/>';
                break;
            case "path":
                path = '<path d="M4.9 17.4V7.7A1.7 1.7 0 0 1 6.6 6h3.15l1.35 1.35h4.3a1.7 1.7 0 0 1 1.7 1.7v8.35a1.7 1.7 0 0 1-1.7 1.7H6.6a1.7 1.7 0 0 1-1.7-1.7Z" stroke="currentColor" stroke-width="1.55" stroke-linejoin="round"/>';
                break;
            default:
                path = '<path d="M10 4.35 15.65 7.6v4.8c0 3.1-2.2 5.95-5.65 6.8-3.45-.85-5.65-3.7-5.65-6.8V7.6L10 4.35Z" stroke="currentColor" stroke-width="1.55" stroke-linejoin="round"/><path d="M10 8.3v4.5M7.75 10.55H12.25" stroke="currentColor" stroke-width="1.55" stroke-linecap="round"/>';
                break;
        }
        return '<span class="ctf-summary-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none">' + path + '</svg></span>';
    }

    function summaryCardMarkup(label, valueId, smallId, copy, kind) {
        return [
            '<article class="ctf-summary-card ctf-summary-card--' + escapeHtml(kind || "summary") + '">',
            '  <div class="ctf-summary-card-head">',
            '    <span>' + escapeHtml(label) + '</span>',
                 summaryIconMarkup(kind),
            "  </div>",
            '  <strong id="' + escapeHtml(valueId) + '">\u2014</strong>',
            '  <small id="' + escapeHtml(smallId) + '">' + escapeHtml(copy) + '</small>',
            '</article>'
        ].join("");
    }

    function typeMatches(file, type) {
        var selected = String(type || "all");
        if (selected === "all") return true;
        var mime = String(file && file.mimeType || "").toLowerCase();
        if (selected === "image") return mime.indexOf("image/") === 0;
        if (selected === "video") return mime.indexOf("video/") === 0;
        if (selected === "audio") return mime.indexOf("audio/") === 0;
        if (selected === "pdf") return mime === "application/pdf";
        if (selected === "text") return mime.indexOf("text/") === 0 || mime === "application/json";
        if (selected === "other") {
            return mime.indexOf("image/") !== 0
                && mime.indexOf("video/") !== 0
                && mime.indexOf("audio/") !== 0
                && mime !== "application/pdf"
                && mime.indexOf("text/") !== 0
                && mime !== "application/json";
        }
        return true;
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
        if (!authApi || !payload) return false;
        if (typeof authApi.handleUnauthorizedPayload === "function") {
            return !!authApi.handleUnauthorizedPayload(payload, fallbackText || "نشست شما منقضی شده است.");
        }
        return false;
    }

    function initOwnerGuard(onReady) {
        var guard = $("ct-auth-guard");
        var app = $("ct-owner-app");
        if (!authApi || !guard || !app) return;

        authApi.onChange(function (detail) {
            var loginUrl = authApi.loginUrl ? authApi.loginUrl(window.location.pathname + window.location.search) : "/account/";
            if (!detail || detail.status === "session-restoring" || detail.status === "logging-out") {
                document.body.classList.remove("ctf-ready");
                app.hidden = true;
                guard.hidden = false;
                guard.innerHTML = "<h2>در حال بررسی حساب</h2><p>وضعیت نشست مشترک سایت خوانده می‌شود.</p>";
                return;
            }
            if (!detail.loggedIn) {
                document.body.classList.remove("ctf-ready");
                app.hidden = true;
                guard.hidden = false;
                guard.innerHTML = authApi.renderLoginRequiredGuard({
                    loginHref: loginUrl,
                    fallbackHref: "/app/",
                    actionsClass: "ct-auth-guard__actions",
                    primaryClass: "ct-btn ct-btn--primary",
                    secondaryClass: "ct-btn"
                });
                authApi.enhanceLoginGuards(guard);
                return;
            }
            if (!detail.user || !detail.user.isOwner) {
                document.body.classList.remove("ctf-ready");
                app.hidden = true;
                guard.hidden = false;
                guard.innerHTML = [
                    "<h2>این بخش مخصوص مالک سایت است</h2>",
                    "<p>مدیریت آپلودسنتر و فایل‌های عمومی فقط برای مالک در دسترس است.</p>",
                    '<a class="ct-btn" href="/app/">بازگشت به خانه</a>'
                ].join("");
                return;
            }
            document.body.classList.add("ctf-ready");
            guard.hidden = true;
            guard.innerHTML = "";
            app.hidden = false;
            onReady(detail.user);
        });
    }

    function initFilesCenter() {
        var feedback = $("ct-feedback");
        var uploadInput = $("ct-upload-input");
        var pickButton = $("ct-upload-pick");
        var dropzone = $("ct-dropzone");
        var clearQueueButton = $("ct-upload-clear");
        var submitButton = $("ct-upload-submit");
        var queueNode = $("ct-upload-queue");
        var browserEntries = $("ctf-browser-entries");
        var browserEmpty = $("ctf-browser-empty");
        var browserQuery = $("ctf-browser-query");
        var browserRefresh = $("ctf-browser-refresh");
        var browserCreateFolder = $("ctf-create-folder");
        var breadcrumbs = $("ctf-breadcrumbs");
        var summaryGrid = $("ctf-summary-grid");
        var currentPathLabel = $("ctf-current-path");
        var browserCurrentPathLabel = $("ctf-browser-current-path");
        var currentFolderLabel = $("ctf-current-folder");
        var destinationLabel = $("ctf-upload-destination");
        var linksList = $("ct-files-list");
        var linksPager = $("ct-files-pager");
        var settingsDisclosure = $("ctf-settings-card");
        var compactSettingsMedia = window.matchMedia("(max-width: 780px)");
        var lastCompactSettings = null;

        var state = {
            summary: {},
            downloadHost: {},
            browserPath: "",
            browserEntries: [],
            browserQuery: "",
            browserQueryTimer: 0,
            browserLoading: false,
            queue: [],
            uploadBusy: false,
            uploadResumeTimer: 0,
            queueCounter: 1,
            links: [],
            linkStatus: "available",
            linkType: "all",
            linkSort: "newest",
            linkQuery: "",
            linkQueryTimer: 0,
            linkPage: 1,
            linkPerPage: 20,
            linkSelected: {},
            pageInfo: { page: 1, pages: 1, total: 0 }
        };

        function currentUploadPath() {
            return String(state.browserPath || "");
        }

        function encodeBase64Url(value) {
            var text = String(value == null ? "" : value);
            var binary = "";
            if (window.TextEncoder) {
                var bytes = new TextEncoder().encode(text);
                for (var index = 0; index < bytes.length; index += 1) {
                    binary += String.fromCharCode(bytes[index]);
                }
            } else {
                binary = unescape(encodeURIComponent(text));
            }
            return btoa(binary).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/g, "");
        }

        function syncSettingsDisclosure() {
            if (!settingsDisclosure) return;
            var isCompact = compactSettingsMedia.matches;
            if (lastCompactSettings === null || lastCompactSettings !== isCompact) {
                settingsDisclosure.open = !isCompact;
            }
            lastCompactSettings = isCompact;
        }

        function currentUploadFolderMeta() {
            var manual = $("ct-upload-folder-input");
            var raw = manual ? String(manual.value || "").trim() : "";
            if (raw) return raw.slice(0, 80);
            var path = currentUploadPath();
            return path ? path.slice(0, 80) : "ریشه";
        }

        function uploadMetaPayload() {
            return {
                title: $("ct-upload-title-input") ? $("ct-upload-title-input").value : "",
                description: $("ct-upload-description-input") ? $("ct-upload-description-input").value : "",
                tags: $("ct-upload-tags-input") ? $("ct-upload-tags-input").value : "",
                folder: currentUploadFolderMeta(),
                expiresAt: $("ct-upload-expire-input") ? $("ct-upload-expire-input").value : "",
                downloadLimit: $("ct-upload-limit-input") ? $("ct-upload-limit-input").value : "",
                password: $("ct-upload-password-input") ? $("ct-upload-password-input").value : "",
                status: $("ct-upload-status-input") ? $("ct-upload-status-input").value : "active",
                targetPath: currentUploadPath()
            };
        }

        function resetUploadMeta() {
            ["ct-upload-title-input", "ct-upload-description-input", "ct-upload-tags-input", "ct-upload-folder-input", "ct-upload-expire-input", "ct-upload-limit-input", "ct-upload-password-input"].forEach(function (id) {
                var node = $(id);
                if (node) node.value = "";
            });
            var status = $("ct-upload-status-input");
            if (status) status.value = "active";
        }

        function ensureSummaryCards() {
            if (!summaryGrid || summaryGrid.dataset.ready === "true") return;
            summaryGrid.innerHTML = [
                summaryCardMarkup("تعداد کل فایل‌ها", "ctf-summary-host-files", "ctf-summary-host-files-meta", "اسکن واقعی ریشه Upload Center", "files"),
                summaryCardMarkup("مجموع لینک‌های ثبت‌شده", "ctf-summary-links", "ctf-summary-links-meta", "همه رکوردهای فایل عمومی", "links"),
                summaryCardMarkup("حجم مدیریت‌شده", "ctf-summary-size", "ctf-summary-size-meta", "جمع فایل‌های شناخته‌شده در استور", "size"),
                summaryCardMarkup("باقی‌مانده هاست", "ctf-summary-host-free", "ctf-summary-host-free-meta", "فضای آزاد برای آپلودهای بعدی", "space"),
                summaryCardMarkup("مسیر مقصد آپلودسنتر", "ctf-summary-target", "ctf-summary-target-meta", "مقصد فعلی Live Upload", "path"),
                summaryCardMarkup("پایه هاست دانلود", "ctf-summary-host", "ctf-summary-host-meta", "ریشه انتشار فایل‌های جدید", "host")
            ].join("");
            summaryGrid.dataset.ready = "true";
        }

        function updateSummary(summary) {
            ensureSummaryCards();
            var previousSummary = state.summary && typeof state.summary === "object" ? state.summary : {};
            var previousHostUsage = previousSummary.hostUsage && typeof previousSummary.hostUsage === "object"
                ? previousSummary.hostUsage
                : null;
            var nextSummary = Object.assign({}, previousSummary, summary || {});
            var incomingHostUsage = summary && summary.hostUsage && typeof summary.hostUsage === "object"
                ? summary.hostUsage
                : null;
            if (incomingHostUsage) {
                if (previousHostUsage && hasHostUsageMetrics(previousHostUsage) && !hasHostUsageMetrics(incomingHostUsage)) {
                    nextSummary.hostUsage = Object.assign({}, previousHostUsage, incomingHostUsage, {
                        available: previousHostUsage.available === true ? true : incomingHostUsage.available
                    });
                } else {
                    nextSummary.hostUsage = Object.assign({}, previousHostUsage || {}, incomingHostUsage);
                }
            } else if (previousHostUsage) {
                nextSummary.hostUsage = previousHostUsage;
            }
            state.summary = nextSummary;
            var hostUsage = state.summary.hostUsage && typeof state.summary.hostUsage === "object"
                ? state.summary.hostUsage
                : {};
            var hostAvailable = hostUsage.available === true;
            var emptyMetric = "\u2014";
            var totalLinks = $("ctf-summary-links");
            var totalLinksMeta = $("ctf-summary-links-meta");
            var totalSize = $("ctf-summary-size");
            var totalSizeMeta = $("ctf-summary-size-meta");
            var hostBase = $("ctf-summary-host");
            var hostBaseMeta = $("ctf-summary-host-meta");
            var hostFree = $("ctf-summary-host-free");
            var hostFreeMeta = $("ctf-summary-host-free-meta");
            var hostFiles = $("ctf-summary-host-files");
            var hostFilesMeta = $("ctf-summary-host-files-meta");
            var targetPath = $("ctf-summary-target");
            var targetPathMeta = $("ctf-summary-target-meta");
            if (hostFiles) {
                hostFiles.textContent = hostAvailable
                    ? formatNumber(hostUsage.fileCount || 0)
                    : formatNumber(state.summary.remoteFiles || 0);
            }
            if (hostFilesMeta) {
                hostFilesMeta.textContent = hostAvailable
                    ? (formatNumber(hostUsage.directoryCount || 0) + " پوشه • " + formatNumber(hostUsage.entryCount || 0) + " ورودی")
                    : "شمارش پوشه‌ها و فایل‌های ریشه در دسترس نیست";
            }
            if (totalLinks) {
                totalLinks.textContent = formatNumber(state.summary.totalFiles || 0);
            }
            if (totalLinksMeta) {
                totalLinksMeta.textContent = formatNumber(state.summary.remoteFiles || 0) + " ریموت / "
                    + formatNumber(state.summary.localFiles || 0) + " لوکال • "
                    + formatNumber(state.summary.activeFiles || 0) + " فعال";
            }
            if (totalSize) {
                totalSize.textContent = formatBytes(state.summary.totalBytes || 0);
            }
            if (totalSizeMeta) {
                totalSizeMeta.textContent = (hostAvailable && hostUsage.usedBytes != null
                    ? ("مصرف هاست " + formatBytes(hostUsage.usedBytes))
                    : "مجموع فایل‌های شناخته‌شده")
                    + " • " + formatNumber(state.summary.downloadCount || 0) + " دانلود";
            }
            if (hostFree) {
                hostFree.textContent = hostAvailable && hostUsage.remainingBytes != null ? formatBytes(hostUsage.remainingBytes) : emptyMetric;
            }
            if (hostFreeMeta) {
                hostFreeMeta.textContent = hostAvailable && hostUsage.limitBytes != null
                    ? ("از " + formatBytes(hostUsage.limitBytes) + " کل فضا"
                        + (hostUsage.uploadRemainingBytes != null ? (" • سقف آپلود بعدی " + formatBytes(hostUsage.uploadRemainingBytes)) : ""))
                    : "مانده از quota کل هاست";
            }
            if (targetPath) {
                targetPath.textContent = currentUploadPath() || "/";
            }
            if (targetPathMeta) {
                targetPathMeta.textContent = folderName(currentUploadPath()) + " • مقصد انتخاب‌شده برای Live Upload";
            }
            if (hostBase) {
                hostBase.textContent = String(state.summary.storageRoot || state.downloadHost.baseUrl || emptyMetric);
            }
            if (hostBaseMeta) {
                hostBaseMeta.textContent = (state.summary.localFiles > 0 ? "هاست دانلود + فایل‌های legacy local" : "هاست دانلود")
                    + (hostAvailable && hostUsage.generatedAt
                        ? (" • " + (hostUsage.stale ? "اسکن کش‌شده" : "آخرین اسکن") + " " + formatDate(hostUsage.generatedAt, "اکنون"))
                        : "");
            }
            var notice = $("ctf-summary-notice");
            if (notice) {
                if (hostAvailable && hostUsage.usedBytes != null && hostUsage.remainingBytes != null) {
                    notice.textContent = "مصرف هاست " + formatBytes(hostUsage.usedBytes) + " • باقی‌مانده " + formatBytes(hostUsage.remainingBytes);
                } else {
                    notice.textContent = String(state.summary.notice || "");
                }
            }
        }

        function updateDestinationUi() {
            var path = currentUploadPath();
            if (currentPathLabel) currentPathLabel.textContent = path || "ریشه آپلودسنتر";
            if (browserCurrentPathLabel) browserCurrentPathLabel.textContent = path || "/";
            if (currentFolderLabel) currentFolderLabel.textContent = folderName(path);
            if (destinationLabel) destinationLabel.value = path || "/";
            var summaryTarget = $("ctf-summary-target");
            var summaryTargetMeta = $("ctf-summary-target-meta");
            if (summaryTarget) summaryTarget.textContent = path || "/";
            if (summaryTargetMeta) summaryTargetMeta.textContent = folderName(path) + " • مقصد انتخاب‌شده برای Live Upload";
        }

        function renderBreadcrumbs(items) {
            if (!breadcrumbs) return;
            breadcrumbs.innerHTML = "";
            (items || []).forEach(function (item, index, all) {
                var button = document.createElement("button");
                button.type = "button";
                button.dataset.path = String(item.path || "");
                if (index === all.length - 1) button.className = "is-active";
                button.textContent = String(item.label || "");
                breadcrumbs.appendChild(button);
            });
        }

        function filterBrowserEntries() {
            var query = String(state.browserQuery || "").trim().toLowerCase();
            if (!query) return state.browserEntries.slice();
            return state.browserEntries.filter(function (entry) {
                var name = String(entry && entry.name || "").toLowerCase();
                var path = String(entry && entry.relativePath || "").toLowerCase();
                return name.indexOf(query) !== -1 || path.indexOf(query) !== -1;
            });
        }

        function browserEntryActions(entry) {
            var actions = [];
            if (entry.type === "dir") {
                actions.push('<button class="ct-btn" type="button" data-open-path="' + escapeHtml(entry.relativePath || "") + '">باز کردن</button>');
            } else if (entry.publicUrl) {
                actions.push('<a class="ct-btn" href="' + escapeHtml(entry.publicUrl) + '" target="_blank" rel="noopener">لینک مستقیم</a>');
                actions.push('<button class="ct-btn" type="button" data-copy="' + escapeHtml(entry.publicUrl) + '">کپی لینک</button>');
            }
            actions.push('<button class="ct-btn" type="button" data-rename-path="' + escapeHtml(entry.relativePath || "") + '" data-entry-type="' + escapeHtml(entry.type || "file") + '">تغییر نام</button>');
            actions.push('<button class="ct-btn ct-btn--danger" type="button" data-delete-path="' + escapeHtml(entry.relativePath || "") + '" data-entry-type="' + escapeHtml(entry.type || "file") + '">حذف</button>');
            return actions.join("");
        }

        function renderBrowser() {
            if (!browserEntries || !browserEmpty) return;
            var items = filterBrowserEntries();
            browserEntries.innerHTML = "";
            updateDestinationUi();
            if (!items.length) {
                browserEmpty.hidden = false;
                browserEmpty.textContent = state.browserLoading
                    ? "در حال دریافت محتویات این پوشه..."
                    : "در این مسیر هنوز فایل یا پوشه‌ای وجود ندارد.";
                return;
            }
            browserEmpty.hidden = true;
            items.forEach(function (entry) {
                var meta = [];
                meta.push(entry.type === "dir" ? "پوشه" : (entry.sizeLabel || formatBytes(entry.sizeBytes || 0)));
                if (entry.modifiedAt) meta.push(formatDate(entry.modifiedAt));
                var article = document.createElement("article");
                article.className = "ctf-entry";
                article.dataset.type = String(entry.type || "file");
                article.innerHTML = [
                    '<div class="ctf-entry-main">',
                    '  <div class="ctf-entry-icon">' + escapeHtml(entry.type === "dir" ? "DIR" : fileKind({ mimeType: entry.mimeType || "", extension: entry.name || "" })) + '</div>',
                    '  <div class="ctf-entry-copy">',
                    '    <strong>' + escapeHtml(entry.name || "") + '</strong>',
                    '    <small>' + escapeHtml(meta.join(" • ")) + '</small>',
                    '    <small>' + escapeHtml(String(entry.relativePath || "")) + '</small>',
                    '  </div>',
                    '</div>',
                    '<div class="ctf-entry-actions">' + browserEntryActions(entry) + '</div>'
                ].join("");
                browserEntries.appendChild(article);
            });
        }

        async function loadHostSummary(forceRefresh, silent) {
            var response = await request("ownerDownloadHostSummary", {
                refresh: forceRefresh ? "1" : ""
            }, "GET");
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                if (!silent) {
                    setFeedback(feedback, (response && response.error) || "آمار کامل هاست خوانده نشد.", "error");
                }
                return;
            }
            state.downloadHost = response.downloadHost || state.downloadHost || {};
            updateSummary(response.summary || {});
        }

        async function loadBrowser(path, silent) {
            state.browserLoading = true;
            renderBrowser();
            var response = await request("ownerDownloadHostBrowse", { path: path || "" }, "GET");
            state.browserLoading = false;
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                renderBrowser();
                if (!silent) setFeedback(feedback, (response && response.error) || "فهرست فایل‌ها خوانده نشد.", "error");
                return;
            }
            state.downloadHost = response.downloadHost || {};
            updateSummary(response.summary || {});
            state.browserPath = String(response.browser && response.browser.currentPath || "");
            state.browserEntries = Array.isArray(response.browser && response.browser.entries) ? response.browser.entries : [];
            renderBreadcrumbs(Array.isArray(response.browser && response.browser.breadcrumbs) ? response.browser.breadcrumbs : []);
            renderBrowser();
            if (!silent) setFeedback(feedback, "");
        }

        function queueStateLabel(item) {
            switch (item.status) {
                case "waiting": return "در انتظار تلاش خودکار";
                case "uploading": return "در حال انتقال";
                case "finalizing": return "ثبت روی هاست";
                case "done": return "تکمیل شد";
                case "error": return "با خطا مواجه شد";
                default: return "آماده آپلود";
            }
        }

        function queueProgressPercent(item) {
            var value = Number(item.progress || 0);
            return Math.max(0, Math.min(100, value));
        }

        function uploadStats() {
            var total = state.queue.length;
            var done = state.queue.filter(function (item) { return item.status === "done"; }).length;
            var uploading = state.queue.filter(function (item) { return item.status === "uploading" || item.status === "finalizing"; }).length;
            var waiting = state.queue.filter(function (item) { return item.status === "waiting"; }).length;
            var totalBytes = state.queue.reduce(function (sum, item) { return sum + Number(item.size || 0); }, 0);
            var uploadedBytes = state.queue.reduce(function (sum, item) { return sum + (Number(item.size || 0) * queueProgressPercent(item) / 100); }, 0);
            return {
                total: total,
                done: done,
                uploading: uploading,
                waiting: waiting,
                totalBytes: totalBytes,
                uploadedBytes: uploadedBytes
            };
        }

        function updateQueueSummary() {
            var stats = uploadStats();
            var files = $("ctf-queue-files");
            var transferred = $("ctf-queue-transferred");
            var active = $("ctf-queue-active");
            if (files) files.textContent = formatNumber(stats.total);
            if (transferred) transferred.textContent = formatBytes(stats.uploadedBytes) + " / " + formatBytes(stats.totalBytes);
            if (active) active.textContent = stats.uploading
                ? (formatNumber(stats.uploading) + " در حال انتقال")
                : (stats.waiting
                    ? (formatNumber(stats.waiting) + " در انتظار اتصال")
                    : (stats.done ? (formatNumber(stats.done) + " تکمیل‌شده") : "آماده"));
        }

        function clearQueueResumeTimer() {
            if (!state.uploadResumeTimer) return;
            window.clearTimeout(state.uploadResumeTimer);
            state.uploadResumeTimer = 0;
        }

        function hasQueueWaitingItems() {
            return state.queue.some(function (item) { return item.status === "waiting"; });
        }

        function queueRetryDelayMs(item) {
            var attempts = Math.max(1, Number(item && item.retryCount || 0));
            if (attempts <= 1) return 4000;
            if (attempts === 2) return 7000;
            if (attempts === 3) return 12000;
            return 20000;
        }

        function queueWaitingMessage(item) {
            if (isOffline()) {
                return "اتصال اینترنت قطع شده است. این فایل در صف می‌ماند و بعد از برگشت اتصال خودکار دوباره تلاش می‌شود.";
            }
            if (queueProgressPercent(item) >= 99) {
                return "ارتباط در مرحله نهایی‌سازی قطع شد. به محض پایدار شدن اتصال، آپلود خودکار دوباره تلاش می‌شود.";
            }
            return "ارتباط آپلود دچار اختلال شد. بعد از پایدار شدن اتصال، آپلود خودکار دوباره تلاش می‌شود.";
        }

        function scheduleQueueResume(delayMs) {
            clearQueueResumeTimer();
            if (!hasQueueWaitingItems()) return;
            state.uploadResumeTimer = window.setTimeout(function () {
                state.uploadResumeTimer = 0;
                if (state.uploadBusy || !hasQueueWaitingItems()) return;
                uploadQueue(true);
            }, Math.max(1200, Number(delayMs || 0)));
        }

        function markQueueItemWaiting(item, message) {
            item.xhr = null;
            item.status = "waiting";
            item.error = message;
            item.speedBps = 0;
            item.etaSeconds = NaN;
            item.retryCount = Math.max(0, Number(item.retryCount || 0)) + 1;
            renderQueue();
            scheduleQueueResume(isOffline() ? 2500 : queueRetryDelayMs(item));
            return createUploadSignal("waiting", message);
        }

        function renderQueue() {
            if (!queueNode) return;
            updateQueueSummary();
            if (!state.queue.length) {
                queueNode.innerHTML = '<div class="ct-empty">هنوز فایلی به صف آپلود اضافه نشده است.</div>';
                return;
            }
            queueNode.innerHTML = state.queue.map(function (item) {
                var stats = [
                    '<span>حجم: ' + escapeHtml(formatBytes(item.size)) + '</span>',
                    '<span>پیشرفت: ' + escapeHtml(formatPercent(queueProgressPercent(item))) + '%</span>',
                    '<span>سرعت: ' + escapeHtml(formatSpeed(item.speedBps)) + '</span>',
                    '<span>زمان باقی‌مانده: ' + escapeHtml(formatEta(item.etaSeconds)) + '</span>'
                ];
                if (item.status === "done") {
                    stats[2] = '<span>مسیر: ' + escapeHtml(item.remotePath || item.targetPath || currentUploadPath() || "/") + '</span>';
                    stats[3] = '<span>اتمام: ' + escapeHtml(formatDate(item.completedAt || "", "اکنون")) + '</span>';
                }
                if (item.status === "waiting") {
                    stats[2] = '<span>وضعیت: ' + escapeHtml(item.error || queueWaitingMessage(item)) + '</span>';
                    stats[3] = '<span>تلاش دوباره: به‌محض برگشت اتصال، خودکار دوباره انجام می‌شود.</span>';
                }
                if (item.status === "error") {
                    stats[2] = '<span>خطا: ' + escapeHtml(item.error || "خطای نامشخص") + '</span>';
                    stats[3] = "";
                }
                var actions = [];
                if (item.publicUrl) {
                    actions.push('<button class="ct-btn" type="button" data-copy="' + escapeHtml(item.publicUrl) + '">کپی لینک عمومی</button>');
                }
                if (item.directUrl) {
                    actions.push('<a class="ct-btn" href="' + escapeHtml(item.directUrl) + '" target="_blank" rel="noopener">لینک مستقیم</a>');
                }
                if (item.status === "uploading" || item.status === "finalizing") {
                    actions.push('<button class="ct-btn ct-btn--danger" type="button" data-remove-queue="' + escapeHtml(item.id) + '">لغو آپلود</button>');
                }
                if (item.status === "waiting") {
                    actions.push('<button class="ct-btn ct-btn--danger" type="button" data-remove-queue="' + escapeHtml(item.id) + '">حذف از صف</button>');
                }
                if (item.status === "queued" || item.status === "error" || item.status === "done") {
                    actions.push('<button class="ct-btn ct-btn--danger" type="button" data-remove-queue="' + escapeHtml(item.id) + '">حذف از صف</button>');
                }
                return [
                    '<article class="ctf-queue-item" data-state="' + escapeHtml(item.status || "queued") + '">',
                    '  <div class="ctf-queue-top">',
                    '    <strong class="ctf-queue-name">' + escapeHtml(item.name) + '</strong>',
                    '    <span class="ctf-queue-state">' + escapeHtml(queueStateLabel(item)) + '</span>',
                    '  </div>',
                    '  <div class="ctf-queue-meter"><span style="width:' + queueProgressPercent(item).toFixed(1) + '%"></span></div>',
                    '  <div class="ctf-queue-stats">' + stats.join("") + '</div>',
                    (actions.length ? '<div class="ctf-queue-result">' + actions.join("") + '</div>' : ""),
                    '</article>'
                ].join("");
            }).join("");
        }

        function addFiles(files) {
            Array.prototype.forEach.call(files || [], function (file) {
                if (!file) return;
                state.queue.push({
                    id: "q-" + String(state.queueCounter++),
                    file: file,
                    name: file.name || "file",
                    size: Number(file.size || 0),
                    status: "queued",
                    progress: 0,
                    speedBps: 0,
                    etaSeconds: NaN,
                    publicUrl: "",
                    directUrl: "",
                    remotePath: "",
                    error: "",
                    xhr: null,
                    canceled: false,
                    retryCount: 0,
                    targetPath: "",
                    uploadMeta: null
                });
            });
            renderQueue();
        }

        function removeQueueItem(id) {
            var active = state.queue.filter(function (item) {
                return item.id === id && (item.status === "uploading" || item.status === "finalizing");
            })[0] || null;
            if (active && active.xhr) {
                active.canceled = true;
                active.xhr.abort();
                return;
            }
            state.queue = state.queue.filter(function (item) { return item.id !== id; });
            if (!hasQueueWaitingItems()) {
                clearQueueResumeTimer();
            }
            renderQueue();
        }

        function clearQueue() {
            if (state.uploadBusy) return;
            state.queue = state.queue.filter(function (item) { return item.status === "uploading" || item.status === "finalizing"; });
            if (!hasQueueWaitingItems()) {
                clearQueueResumeTimer();
            }
            renderQueue();
        }

        // Chunk size kept under the download-host WAF slow-upload threshold so each
        // request finishes fast; the whole file is streamed straight to the download
        // host from the browser, never relayed through this (site) host.
        var CT_DIRECT_CHUNK_SIZE = 1024 * 1024;

        function ctDirectError(message, fallback) {
            var err = new Error(message || "direct-upload-failed");
            if (fallback) { err.__ctFallback = true; }
            return err;
        }

        function encodeChunkBase64Url(blob) {
            return new Promise(function (resolve, reject) {
                var reader = new FileReader();
                reader.onerror = function () { reject(ctDirectError("chunk-read-failed", false)); };
                reader.onload = function () {
                    var value = String(reader.result || "");
                    var comma = value.indexOf(",");
                    var encoded = comma >= 0 ? value.slice(comma + 1) : "";
                    if (!encoded) { reject(ctDirectError("chunk-encode-failed", false)); return; }
                    resolve(encoded.replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/g, ""));
                };
                reader.readAsDataURL(blob);
            });
        }

        function sendDirectChunks(plan, item) {
            var baseUrl = String(plan.url || "");
            var total = Number(item.size || (item.file && item.file.size) || 0);
            var chunkSize = Math.max(256 * 1024, Number(plan.chunkBytes || CT_DIRECT_CHUNK_SIZE));
            var chunkCount = Math.max(1, Math.ceil(total / chunkSize));
            var ctype = item.file && item.file.type ? item.file.type : "application/octet-stream";
            var startedAt = Date.now();
            var streamMode = String(plan.mode || "") === "stream";
            return new Promise(function (resolve, reject) {
                function sendChunk(index) {
                    if (item.canceled) { reject(createUploadSignal("canceled", "آپلود توسط کاربر لغو شد.")); return; }
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
                    xhr.timeout = 0;
                    xhr.setRequestHeader("Accept", "application/json");
                    xhr.setRequestHeader("Content-Type", "text/plain; charset=us-ascii");
                    xhr.setRequestHeader("X-Dent-Chunk-Encoding", "base64url");
                    xhr.upload.onprogress = function (event) {
                        if (!event.lengthComputable) return;
                        var loaded = start + Number(event.loaded || 0);
                        var elapsed = Math.max(0.25, (Date.now() - startedAt) / 1000);
                        item.speedBps = loaded / elapsed;
                        item.progress = total > 0 ? Math.min(99.2, (loaded / total) * 100) : item.progress;
                        item.etaSeconds = item.speedBps > 0 && total > loaded ? (total - loaded) / item.speedBps : 0;
                        renderQueue();
                    };
                    xhr.onerror = function () { item.xhr = null; reject(ctDirectError("network", index === 0)); };
                    xhr.ontimeout = function () { item.xhr = null; reject(ctDirectError("timeout", index === 0)); };
                    xhr.onabort = function () {
                        item.xhr = null;
                        reject(item.canceled ? createUploadSignal("canceled", "آپلود توسط کاربر لغو شد.") : ctDirectError("aborted", index === 0));
                    };
                    xhr.onload = function () {
                        item.xhr = null;
                        var resp = {};
                        try { resp = JSON.parse(xhr.responseText || "{}"); } catch (_e) { resp = {}; }
                        if (xhr.status < 200 || xhr.status >= 300 || !resp || resp.success === false) {
                            reject(ctDirectError((resp && resp.error) || ("upload-" + xhr.status), false));
                            return;
                        }
                        if (index + 1 >= chunkCount) { resolve(resp && resp.file ? resp.file : {}); return; }
                        sendChunk(index + 1);
                    };
                    xhr.send(encodedBody);
                    }).catch(reject);
                }
                sendChunk(0);
            });
        }

        function uploadItemDirect(item) {
            return new Promise(function (resolve, reject) {
                var meta = item.uploadMeta && typeof item.uploadMeta === "object"
                    ? Object.assign({}, item.uploadMeta)
                    : uploadMetaPayload();
                meta.fileName = item.name || (item.file && item.file.name) || "file";
                item.uploadMeta = Object.assign({}, meta);
                item.targetPath = String(meta.targetPath || item.targetPath || currentUploadPath() || "");
                item.status = "uploading";
                item.progress = 0;
                item.speedBps = 0;
                item.etaSeconds = NaN;
                item.error = "";
                item.canceled = false;
                renderQueue();

                var fileSize = Number(item.size || (item.file && item.file.size) || 0);
                var rootSeg = String(item.targetPath || "").split("/")[0] || "1402";
                var cohort = ["1402", "1403", "1404", "prosthesis-1402"].indexOf(rootSeg) !== -1 ? rootSeg : "1402";

                fetch("/api/notes_api.php?action=prepareHostUpload", {
                    method: "POST",
                    credentials: "same-origin",
                    headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8", "Accept": "application/json" },
                    body: new URLSearchParams({
                        path: item.targetPath,
                        cohort: cohort,
                        fileName: meta.fileName,
                        fileSize: String(fileSize),
                        mimeType: (item.file && item.file.type) || "application/octet-stream"
                    })
                }).then(function (r) { return r.json().then(function (j) { j.httpStatus = r.status; return j; }); }).then(function (prep) {
                    if (consumeUnauthorized(prep)) { reject(new Error("unauthorized")); return; }
                    var plan = prep && prep.upload;
                    if (!prep || !prep.success || !plan || ["direct", "stream"].indexOf(String(plan.mode || "")) === -1 || !plan.url) {
                        reject(ctDirectError("stream-unavailable", false));
                        return;
                    }
                    sendDirectChunks(plan, item).then(function (gwFile) {
                        var regBody = new URLSearchParams({
                            relativePath: String(plan.relativePath || (gwFile && gwFile.relativePath) || ""),
                            fileName: meta.fileName,
                            bytes: String(fileSize),
                            mimeType: (item.file && item.file.type) || "application/octet-stream",
                            title: meta.title || "",
                            description: meta.description || "",
                            tags: meta.tags || "",
                            folder: meta.folder || "",
                            status: meta.status || "active",
                            expiresAt: meta.expiresAt || "",
                            password: meta.password || "",
                            downloadLimit: String(meta.downloadLimit || "")
                        });
                        fetch("/api/content_tools_api.php?action=ownerRegisterDownloadHostFile", {
                            method: "POST",
                            credentials: "same-origin",
                            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8", "Accept": "application/json" },
                            body: regBody
                        }).then(function (r) { return r.json().then(function (j) { j.httpStatus = r.status; return j; }); }).then(function (reg) {
                            if (consumeUnauthorized(reg)) { reject(new Error("unauthorized")); return; }
                            if (!reg || !reg.success) {
                                item.status = "error";
                                item.error = (reg && reg.error) || "ثبت فایل روی هاست انجام نشد.";
                                item.speedBps = 0;
                                item.etaSeconds = NaN;
                                renderQueue();
                                reject(new Error(item.error));
                                return;
                            }
                            var uploaded = Array.isArray(reg.files) && reg.files[0] ? reg.files[0] : null;
                            item.status = "done";
                            item.progress = 100;
                            item.completedAt = new Date().toISOString();
                            item.speedBps = 0;
                            item.etaSeconds = 0;
                            item.publicUrl = uploaded && uploaded.publicUrl ? String(uploaded.publicUrl) : "";
                            item.directUrl = uploaded && uploaded.directUrl ? String(uploaded.directUrl) : "";
                            item.remotePath = uploaded && uploaded.remoteRelativePath ? String(uploaded.remoteRelativePath) : item.targetPath;
                            renderQueue();
                            resolve(reg);
                        }).catch(function () { reject(ctDirectError("register-failed", false)); });
                    }).catch(reject);
                }).catch(function () { reject(ctDirectError("prepare-failed", true)); });
            });
        }

        function uploadItem(item) {
            // Never fall back to the legacy whole-file request. A failed stream
            // remains failed/retryable instead of staging the complete file on the
            // capacity-limited main host.
            return uploadItemDirect(item);
        }

        function uploadItemViaProxy(item) {
            return new Promise(function (resolve, reject) {
                var meta = item.uploadMeta && typeof item.uploadMeta === "object"
                    ? Object.assign({}, item.uploadMeta)
                    : uploadMetaPayload();
                meta.fileName = item.name || (item.file && item.file.name) || "file";
                item.uploadMeta = Object.assign({}, meta);
                item.targetPath = String(meta.targetPath || item.targetPath || currentUploadPath() || "");

                item.status = "uploading";
                item.progress = 0;
                item.speedBps = 0;
                item.etaSeconds = NaN;
                item.error = "";
                item.canceled = false;
                renderQueue();

                var startedAt = Date.now();
                var xhr = new XMLHttpRequest();
                item.xhr = xhr;
                xhr.open("POST", "/api/content_tools_api.php?action=ownerDownloadHostUpload", true);
                xhr.timeout = 0;
                xhr.withCredentials = true;
                xhr.setRequestHeader("Accept", "application/json");
                xhr.setRequestHeader("Content-Type", item.file && item.file.type ? item.file.type : "application/octet-stream");
                xhr.setRequestHeader("X-Dent-Upload-Name", encodeURIComponent(meta.fileName));
                xhr.setRequestHeader("X-Dent-Upload-Meta", encodeBase64Url(JSON.stringify(meta)));

                xhr.upload.onprogress = function (event) {
                    if (!event.lengthComputable) return;
                    var loaded = Number(event.loaded || 0);
                    var total = Number(event.total || item.size || 0);
                    var elapsed = Math.max(0.25, (Date.now() - startedAt) / 1000);
                    var speed = loaded / elapsed;
                    item.progress = total > 0 ? (loaded / total) * 100 : item.progress;
                    item.speedBps = speed;
                    item.etaSeconds = speed > 0 && total > loaded ? (total - loaded) / speed : 0;
                    if (item.progress >= 99.9) {
                        item.status = "finalizing";
                        item.etaSeconds = 0;
                    }
                    renderQueue();
                };

                xhr.upload.onload = function () {
                    item.progress = 100;
                    item.status = "finalizing";
                    item.etaSeconds = 0;
                    renderQueue();
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
                    if (consumeUnauthorized(response)) {
                        reject(new Error("unauthorized"));
                        return;
                    }
                    if (!response.success) {
                        item.status = "error";
                        item.error = response.error || "آپلود انجام نشد.";
                        item.speedBps = 0;
                        item.etaSeconds = NaN;
                        renderQueue();
                        reject(new Error(item.error));
                        return;
                    }
                    var uploaded = Array.isArray(response.files) && response.files[0] ? response.files[0] : null;
                    item.status = "done";
                    item.progress = 100;
                    item.completedAt = new Date().toISOString();
                    item.speedBps = 0;
                    item.etaSeconds = 0;
                    item.publicUrl = uploaded && uploaded.publicUrl ? String(uploaded.publicUrl) : "";
                    item.directUrl = uploaded && uploaded.directUrl ? String(uploaded.directUrl) : "";
                    item.remotePath = uploaded && uploaded.remoteRelativePath ? String(uploaded.remoteRelativePath) : item.targetPath;
                    renderQueue();
                    resolve(response);
                };

                xhr.onerror = function () {
                    reject(markQueueItemWaiting(item, queueWaitingMessage(item)));
                };

                xhr.ontimeout = function () {
                    reject(markQueueItemWaiting(item, "اتصال آپلود به مشکل خورد. به‌محض پایدار شدن اتصال، دوباره خودکار تلاش می‌شود."));
                };

                xhr.onabort = function () {
                    item.xhr = null;
                    if (item.canceled) {
                        item.status = "error";
                        item.error = "آپلود توسط کاربر لغو شد.";
                        item.speedBps = 0;
                        item.etaSeconds = NaN;
                        renderQueue();
                        reject(createUploadSignal("canceled", item.error));
                        return;
                    }
                    reject(markQueueItemWaiting(item, queueWaitingMessage(item)));
                };

                xhr.send(item.file);
            });
        }

        async function uploadQueue(autoResume) {
            if (state.uploadBusy) return;
            clearQueueResumeTimer();
            var pending = state.queue.filter(function (item) {
                return item.status === "queued" || item.status === "waiting";
            });
            if (!pending.length) {
                if (!autoResume) {
                    setFeedback(feedback, "فایلی برای شروع آپلود در صف نیست.", "error");
                }
                return;
            }
            state.uploadBusy = true;
            submitButton.disabled = true;
            if (!autoResume) {
                setFeedback(feedback, "آپلود روی هاست دانلود شروع شد...", "");
            }
            var successCount = 0;
            var canceledCount = 0;
            var waitingCount = 0;
            for (var i = 0; i < pending.length; i += 1) {
                try {
                    await uploadItem(pending[i]);
                    successCount += 1;
                } catch (error) {
                    if (error && error.code === "waiting") {
                        waitingCount += 1;
                        break;
                    }
                    if (error && (error.code === "canceled" || /لغو/.test(String(error.message || "")))) {
                        canceledCount += 1;
                    }
                }
            }
            state.uploadBusy = false;
            submitButton.disabled = false;
            if (successCount > 0) {
                await loadBrowser(currentUploadPath(), true);
                await loadHostSummary(true, true);
                await loadLinks(true);
            }
            if (waitingCount > 0) {
                setFeedback(
                    feedback,
                    successCount > 0
                        ? (successCount.toLocaleString("fa-IR") + " فایل آپلود شد و بقیه بعد از برگشت اتصال خودکار دوباره تلاش می‌شوند.")
                        : "اتصال آپلود دچار اختلال شد. فایل‌ها در صف می‌مانند و بعد از برگشت اینترنت خودکار دوباره تلاش می‌شود.",
                    ""
                );
                return;
            }
            if (successCount > 0) {
                setFeedback(feedback, successCount.toLocaleString("fa-IR") + " فایل با موفقیت روی هاست دانلود ثبت شد.", "success");
            } else if (canceledCount > 0) {
                setFeedback(feedback, "آپلود فایل از طرف کاربر لغو شد.", "");
            } else {
                setFeedback(feedback, "هیچ فایلی با موفقیت آپلود نشد.", "error");
            }
        }

        function linkParams() {
            return {
                query: state.linkQuery,
                status: state.linkStatus,
                type: state.linkType,
                sort: state.linkSort,
                page: String(state.linkPage),
                perPage: String(state.linkPerPage)
            };
        }

        async function loadLinks(silent) {
            var response = await request("ownerFiles", linkParams(), "GET");
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                if (!silent) setFeedback(feedback, (response && response.error) || "فهرست لینک‌ها خوانده نشد.", "error");
                return;
            }
            updateSummary(response.summary || state.summary);
            state.links = Array.isArray(response.page && response.page.items) ? response.page.items : [];
            state.pageInfo = response.page || { page: 1, pages: 1, total: 0 };
            renderLinks();
        }

        function selectedLinkIds() {
            return Object.keys(state.linkSelected).filter(function (id) { return state.linkSelected[id]; });
        }

        function renderSelectedCount() {
            var node = $("ct-files-selected");
            if (node) node.textContent = selectedLinkIds().length.toLocaleString("fa-IR") + " انتخاب";
        }

        function renderLinks() {
            renderSelectedCount();
            if (!linksList || !linksPager) return;
            if (!state.links.length) {
                linksList.innerHTML = '<div class="ct-empty">لینکی با این فیلتر پیدا نشد.</div>';
                linksPager.innerHTML = "";
                return;
            }
            linksList.innerHTML = state.links.map(function (file) {
                var status = file.publicState || file.status || "";
                var meta = [
                    "<span>" + escapeHtml(file.originalName || "") + "</span>",
                    "<span>" + escapeHtml(formatBytes(file.size || 0)) + "</span>",
                    "<span>" + escapeHtml(formatDate(file.createdAt)) + "</span>",
                    "<span>" + escapeHtml(formatNumber(file.downloadCount || 0)) + " دانلود</span>",
                    '<span class="ct-status ct-status--' + escapeHtml(status) + '">' + escapeHtml(stateLabel(status)) + "</span>",
                    file.storageDriver === "download-host" ? "<span>هاست دانلود</span>" : "<span>لوکال</span>",
                    file.hasPassword ? "<span>رمزد‌ار</span>" : ""
                ].join("");
                var actions = [
                    '<button class="ct-btn" type="button" data-copy="' + escapeHtml(file.publicUrl) + '">کپی لینک</button>',
                    file.directUrl ? ('<button class="ct-btn" type="button" data-copy="' + escapeHtml(file.directUrl) + '">کپی مستقیم</button>') : "",
                    '<a class="ct-btn" href="' + escapeHtml(file.publicUrl) + '" target="_blank" rel="noopener">صفحه فایل</a>',
                    file.directUrl ? ('<a class="ct-btn" href="' + escapeHtml(file.directUrl) + '" target="_blank" rel="noopener">دانلود مستقیم</a>') : "",
                    '<button class="ct-btn" type="button" data-edit-file="' + escapeHtml(file.id) + '">ویرایش</button>',
                    '<button class="ct-btn ct-btn--danger" type="button" data-single-file-delete="' + escapeHtml(file.id) + '">حذف</button>'
                ].join("");
                return [
                    '<article class="ctf-link-row">',
                    '  <input class="ct-file-select" type="checkbox" data-file-check="' + escapeHtml(file.id) + '"' + (state.linkSelected[file.id] ? " checked" : "") + '>',
                    '  <div class="ctf-link-meta">',
                    '    <strong>' + escapeHtml(file.title || file.originalName || "فایل") + '</strong>',
                    '    <div class="ctf-link-meta-line">' + meta + '</div>',
                    '    <small class="ctf-link-url">' + escapeHtml(file.publicUrl || "") + '</small>',
                    '  </div>',
                    '  <div class="ctf-link-actions">' + actions + '</div>',
                    '</article>'
                ].join("");
            }).join("");

            var pages = Number(state.pageInfo.pages || 1);
            if (pages <= 1) {
                linksPager.innerHTML = "";
                return;
            }
            var buttons = [];
            for (var page = 1; page <= pages; page += 1) {
                buttons.push('<button class="ct-btn' + (page === Number(state.pageInfo.page || 1) ? " is-active" : "") + '" type="button" data-links-page="' + page + '">' + page.toLocaleString("fa-IR") + '</button>');
            }
            linksPager.innerHTML = buttons.join("");
        }

        async function bulkFiles(operation, ids) {
            var targets = ids || selectedLinkIds();
            if (!targets.length) {
                setFeedback(feedback, "حداقل یک فایل را انتخاب کنید.", "error");
                return;
            }
            var dangerous = operation === "delete" || operation === "purge";
            var message = operation === "purge"
                ? "فایل اصلی از هاست دانلود یا storage حذف می‌شود. ادامه می‌دهید؟"
                : "عملیات روی " + targets.length.toLocaleString("fa-IR") + " فایل انجام شود؟";
            if (dangerous && !window.confirm(message)) return;
            var response = await request("ownerBulkFiles", {
                operation: operation,
                ids: JSON.stringify(targets)
            }, "POST");
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                setFeedback(feedback, (response && response.error) || "عملیات انجام نشد.", "error");
                return;
            }
            state.linkSelected = {};
            setFeedback(feedback, response.message || "عملیات انجام شد.", "success");
            await loadBrowser(currentUploadPath(), true);
            if (operation === "delete" || operation === "purge") {
                await loadHostSummary(true, true);
            }
            await loadLinks(true);
        }

        async function editFile(id) {
            var file = state.links.find(function (item) { return item.id === id; });
            if (!file) return;
            var title = window.prompt("عنوان فایل", file.title || file.originalName || "");
            if (title === null) return;
            var description = window.prompt("توضیح فایل", file.description || "");
            if (description === null) return;
            var folder = window.prompt("برچسب پوشه/دسته", file.folder || "");
            if (folder === null) return;
            var status = window.prompt("وضعیت لینک: active یا hidden", file.status || "active");
            if (status === null) return;
            var response = await request("ownerUpdateFile", {
                id: file.id,
                title: title,
                description: description,
                folder: folder,
                status: status,
                tags: Array.isArray(file.tags) ? file.tags.join(", ") : ""
            }, "POST");
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                setFeedback(feedback, (response && response.error) || "ویرایش فایل انجام نشد.", "error");
                return;
            }
            setFeedback(feedback, response.message || "فایل به‌روزرسانی شد.", "success");
            await loadLinks(true);
        }

        async function createFolder() {
            var name = window.prompt("نام پوشه جدید", "");
            if (name === null) return;
            name = String(name || "").trim();
            if (!name) {
                setFeedback(feedback, "نام پوشه نمی‌تواند خالی باشد.", "error");
                return;
            }
            var response = await request("ownerDownloadHostCreateDir", {
                parentPath: currentUploadPath(),
                name: name
            }, "POST");
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                setFeedback(feedback, (response && response.error) || "ساخت پوشه انجام نشد.", "error");
                return;
            }
            setFeedback(feedback, response.message || "پوشه جدید ساخته شد.", "success");
            await loadBrowser(currentUploadPath(), true);
            await loadHostSummary(true, true);
        }

        async function renameEntry(path, type) {
            var currentName = folderName(path);
            var newName = window.prompt("نام جدید", currentName);
            if (newName === null) return;
            newName = String(newName || "").trim();
            if (!newName) {
                setFeedback(feedback, "نام جدید معتبر نیست.", "error");
                return;
            }
            var response = await request("ownerDownloadHostRenameEntry", {
                path: path,
                type: type,
                newName: newName
            }, "POST");
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                setFeedback(feedback, (response && response.error) || "تغییر نام انجام نشد.", "error");
                return;
            }
            setFeedback(feedback, response.message || "نام فایل یا پوشه به‌روزرسانی شد.", "success");
            await loadBrowser(currentUploadPath(), true);
            await loadLinks(true);
        }

        async function deleteEntry(path, type) {
            var label = type === "dir" ? "این پوشه و محتویاتش" : "این فایل";
            if (!window.confirm(label + " حذف شود؟")) return;
            var response = await request("ownerDownloadHostDeleteEntry", {
                path: path,
                type: type
            }, "POST");
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                setFeedback(feedback, (response && response.error) || "حذف انجام نشد.", "error");
                return;
            }
            setFeedback(feedback, response.message || "ورودی حذف شد.", "success");
            await loadBrowser(currentUploadPath(), true);
            await loadHostSummary(true, true);
            await loadLinks(true);
        }

        if (pickButton && uploadInput) {
            pickButton.addEventListener("click", function () {
                uploadInput.click();
            });
            uploadInput.addEventListener("change", function () {
                addFiles(uploadInput.files || []);
                uploadInput.value = "";
            });
        }

        if (dropzone && uploadInput) {
            dropzone.addEventListener("click", function () {
                uploadInput.click();
            });
            dropzone.addEventListener("keydown", function (event) {
                if (event.key === "Enter" || event.key === " ") {
                    event.preventDefault();
                    uploadInput.click();
                }
            });
            ["dragenter", "dragover"].forEach(function (eventName) {
                dropzone.addEventListener(eventName, function (event) {
                    event.preventDefault();
                    dropzone.classList.add("is-dragover");
                });
            });
            ["dragleave", "dragend", "drop"].forEach(function (eventName) {
                dropzone.addEventListener(eventName, function (event) {
                    event.preventDefault();
                    dropzone.classList.remove("is-dragover");
                });
            });
            dropzone.addEventListener("drop", function (event) {
                addFiles(event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files : []);
            });
        }

        if (clearQueueButton) {
            clearQueueButton.addEventListener("click", function () {
                clearQueue();
                resetUploadMeta();
            });
        }
        if (submitButton) {
            submitButton.addEventListener("click", function (event) {
                event.preventDefault();
                uploadQueue();
            });
        }
        window.addEventListener("offline", function () {
            if (!state.uploadBusy) return;
            setFeedback(feedback, "اتصال اینترنت قطع شد. آپلودها در صف می‌مانند و بعد از برگشت اتصال خودکار دوباره تلاش می‌شوند.", "");
        });
        window.addEventListener("online", function () {
            if (!hasQueueWaitingItems()) return;
            setFeedback(feedback, "اتصال برگشت. آپلود فایل‌ها خودکار دوباره تلاش می‌شوند.", "");
            scheduleQueueResume(900);
        });
        if (queueNode) {
            queueNode.addEventListener("click", function (event) {
                var copyNode = event.target.closest("[data-copy]");
                if (copyNode) {
                    copyText(copyNode.getAttribute("data-copy"), function (ok) {
                        setFeedback(feedback, ok ? "لینک کپی شد." : "کپی لینک انجام نشد.", ok ? "success" : "error");
                    });
                    return;
                }
                var removeNode = event.target.closest("[data-remove-queue]");
                if (removeNode) {
                    removeQueueItem(removeNode.getAttribute("data-remove-queue") || "");
                }
            });
        }

        if (browserQuery) {
            browserQuery.addEventListener("input", function () {
                state.browserQuery = browserQuery.value || "";
                window.clearTimeout(state.browserQueryTimer);
                state.browserQueryTimer = window.setTimeout(renderBrowser, 150);
            });
        }
        if (browserRefresh) {
            browserRefresh.addEventListener("click", function () {
                loadBrowser(currentUploadPath(), true);
            });
        }
        if (browserCreateFolder) {
            browserCreateFolder.addEventListener("click", function () {
                createFolder();
            });
        }
        if (breadcrumbs) {
            breadcrumbs.addEventListener("click", function (event) {
                var button = event.target.closest("[data-path]");
                if (!button) return;
                loadBrowser(button.getAttribute("data-path") || "", true);
            });
        }
        if (browserEntries) {
            browserEntries.addEventListener("click", function (event) {
                var copyNode = event.target.closest("[data-copy]");
                if (copyNode) {
                    copyText(copyNode.getAttribute("data-copy"), function (ok) {
                        setFeedback(feedback, ok ? "لینک مستقیم کپی شد." : "کپی لینک انجام نشد.", ok ? "success" : "error");
                    });
                    return;
                }
                var openNode = event.target.closest("[data-open-path]");
                if (openNode) {
                    loadBrowser(openNode.getAttribute("data-open-path") || "", true);
                    return;
                }
                var renameNode = event.target.closest("[data-rename-path]");
                if (renameNode) {
                    renameEntry(renameNode.getAttribute("data-rename-path") || "", renameNode.getAttribute("data-entry-type") || "file");
                    return;
                }
                var deleteNode = event.target.closest("[data-delete-path]");
                if (deleteNode) {
                    deleteEntry(deleteNode.getAttribute("data-delete-path") || "", deleteNode.getAttribute("data-entry-type") || "file");
                }
            });
        }

        var filesRefresh = $("ct-files-refresh");
        if (filesRefresh) {
            filesRefresh.addEventListener("click", function () {
                loadLinks(true);
            });
        }
        var filesQuery = $("ct-files-query");
        if (filesQuery) {
            filesQuery.addEventListener("input", function () {
                state.linkQuery = filesQuery.value || "";
                state.linkPage = 1;
                window.clearTimeout(state.linkQueryTimer);
                state.linkQueryTimer = window.setTimeout(function () {
                    loadLinks(true);
                }, 300);
            });
        }
        var filesStatus = $("ct-files-status");
        if (filesStatus) {
            filesStatus.addEventListener("change", function () {
                state.linkStatus = filesStatus.value || "available";
                state.linkPage = 1;
                loadLinks(true);
            });
        }
        var filesType = $("ct-files-type");
        if (filesType) {
            filesType.addEventListener("change", function () {
                state.linkType = filesType.value || "all";
                state.linkPage = 1;
                loadLinks(true);
            });
        }
        var filesSort = $("ct-files-sort");
        if (filesSort) {
            filesSort.addEventListener("change", function () {
                state.linkSort = filesSort.value || "newest";
                state.linkPage = 1;
                loadLinks(true);
            });
        }
        document.querySelectorAll("[data-ct-bulk-files]").forEach(function (button) {
            button.addEventListener("click", function () {
                bulkFiles(button.getAttribute("data-ct-bulk-files") || "");
            });
        });
        if (linksList) {
            linksList.addEventListener("change", function (event) {
                var checkbox = event.target.closest("[data-file-check]");
                if (!checkbox) return;
                state.linkSelected[checkbox.getAttribute("data-file-check") || ""] = !!checkbox.checked;
                renderSelectedCount();
            });
            linksList.addEventListener("click", function (event) {
                var copyNode = event.target.closest("[data-copy]");
                if (copyNode) {
                    copyText(copyNode.getAttribute("data-copy"), function (ok) {
                        setFeedback(feedback, ok ? "لینک کپی شد." : "کپی لینک انجام نشد.", ok ? "success" : "error");
                    });
                    return;
                }
                var editNode = event.target.closest("[data-edit-file]");
                if (editNode) {
                    editFile(editNode.getAttribute("data-edit-file") || "");
                    return;
                }
                var deleteNode = event.target.closest("[data-single-file-delete]");
                if (deleteNode) {
                    bulkFiles("delete", [deleteNode.getAttribute("data-single-file-delete") || ""]);
                }
            });
        }
        if (linksPager) {
            linksPager.addEventListener("click", function (event) {
                var button = event.target.closest("[data-links-page]");
                if (!button) return;
                state.linkPage = Math.max(1, Number(button.getAttribute("data-links-page") || "1"));
                loadLinks(true);
            });
        }
        if (typeof compactSettingsMedia.addEventListener === "function") {
            compactSettingsMedia.addEventListener("change", syncSettingsDisclosure);
        } else if (typeof compactSettingsMedia.addListener === "function") {
            compactSettingsMedia.addListener(syncSettingsDisclosure);
        }
        syncSettingsDisclosure();

        renderQueue();
        updateDestinationUi();
        loadHostSummary(false, true);
        loadBrowser("", true);
        loadLinks(true);
    }

    initOwnerGuard(initFilesCenter);
}());
