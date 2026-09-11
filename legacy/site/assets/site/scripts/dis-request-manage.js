(function () {
    "use strict";

    if (!window.Dent1402Auth) {
        return;
    }

    function $(id) {
        return document.getElementById(id);
    }

    var boot = $("dis-manage-boot");
    var login = $("dis-manage-login");
    var forbidden = $("dis-manage-forbidden");
    var stage = $("dis-manage-stage");
    var loginLink = $("dis-manage-login-link");
    var shareLinkInput = $("dis-share-link");
    var copyShareLinkButton = $("dis-copy-share-link");
    var openPublicFormLink = $("dis-open-public-form");
    var statsRoot = $("dis-manage-stats");
    var submittedList = $("dis-submitted-list");
    var pendingList = $("dis-pending-list");
    var submittedTitle = $("dis-submitted-title");
    var pendingTitle = $("dis-pending-title");
    var detailTitle = $("dis-detail-title");
    var detailMeta = $("dis-detail-meta");
    var detailEmpty = $("dis-detail-empty");
    var detailBody = $("dis-detail-body");
    var detailIdentity = $("dis-detail-identity");
    var detailSections = $("dis-detail-sections");
    var detailApprovals = $("dis-detail-approvals");
    var toastEl = $("dis-manage-toast");

    var exportOfficial = $("dis-export-official");
    var formOpenLabel = $("dis-form-open-label");
    var toggleFormOpenBtn = $("dis-toggle-form-open");

    var activeStudentNumber = "";
    var formIsOpen = true;
    var toastTimer = 0;
    var loadingOverview = false;

    function safeText(value) {
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

    function parseTimestampLike(value) {
        var raw = String(value == null ? "" : value).trim();
        if (!raw) {
            return null;
        }

        var direct = new Date(raw);
        if (Number.isFinite(direct.getTime())) {
            return direct;
        }

        var numeric = Number(raw);
        if (!Number.isFinite(numeric)) {
            return null;
        }

        if (Math.abs(numeric) < 1000000000000) {
            numeric = numeric * 1000;
        }

        var fromNumber = new Date(numeric);
        return Number.isFinite(fromNumber.getTime()) ? fromNumber : null;
    }

    function formatJalaliDateTime(value, fallback) {
        var raw = String(value == null ? "" : value).trim();
        if (!raw) {
            return fallback || "—";
        }

        var parsed = parseTimestampLike(raw);
        if (!parsed) {
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

    function parseJsonResponse(response) {
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
        return fetch("/api/dis_request_api.php?" + query.toString(), {
            method: "GET",
            credentials: "same-origin",
            headers: {
                "Accept": "application/json"
            }
        }).then(parseJsonResponse).catch(networkErrorResponse);
    }

    function apiPost(action, data) {
        var body = new URLSearchParams();
        body.append("action", action);
        Object.keys(data || {}).forEach(function (key) {
            body.append(key, data[key]);
        });
        return fetch("/api/dis_request_api.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                "Accept": "application/json"
            },
            body: body
        }).then(parseJsonResponse).catch(networkErrorResponse);
    }

    function consumeUnauthorized(payload, fallbackText) {
        var auth = window.Dent1402Auth && typeof window.Dent1402Auth === "object"
            ? window.Dent1402Auth
            : null;
        if (!auth) {
            return false;
        }

        try {
            if (typeof auth.handleUnauthorizedPayload === "function") {
                return !!auth.handleUnauthorizedPayload(payload, fallbackText || "نشست شما منقضی شده است.");
            }
        } catch (_error) {
            // Ignore and continue fallback checks.
        }

        if (payload && (payload.loggedOut || payload.httpStatus === 401)) {
            if (typeof auth.markUnauthorized === "function") {
                auth.markUnauthorized((payload && payload.error) || (fallbackText || "نشست شما منقضی شده است."));
            }
            return true;
        }

        return false;
    }

    function showToast(text) {
        if (!toastEl || !text) {
            return;
        }

        toastEl.textContent = text;
        toastEl.classList.add("is-show");
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(function () {
            toastEl.classList.remove("is-show");
        }, 2200);
    }

    function renderFormStatusUi(formOpen) {
        formIsOpen = !!formOpen;
        if (formOpenLabel) {
            formOpenLabel.textContent = formIsOpen
                ? "فرم در حال حاضر باز است — کاربران می‌توانند درخواست ثبت کنند."
                : "فرم در حال حاضر بسته است — ثبت درخواست جدید غیرفعال است.";
        }
        if (toggleFormOpenBtn) {
            toggleFormOpenBtn.disabled = false;
            toggleFormOpenBtn.textContent = formIsOpen ? "بستن فرم" : "باز کردن فرم";
        }
    }

    function handleToggleFormOpen() {
        if (toggleFormOpenBtn) {
            toggleFormOpenBtn.disabled = true;
        }
        var nextOpen = !formIsOpen;
        apiPost("setFormStatus", { open: nextOpen ? "1" : "0" }).then(function (payload) {
            if (consumeUnauthorized(payload, "نشست شما منقضی شده است.")) {
                return;
            }
            if (!payload || !payload.success) {
                throw new Error((payload && payload.error) || "تغییر وضعیت فرم انجام نشد.");
            }
            renderFormStatusUi(payload.formOpen);
            showToast((payload && payload.message) || (nextOpen ? "فرم باز شد." : "فرم بسته شد."));
        }).catch(function (error) {
            showToast(error && error.message ? error.message : "تغییر وضعیت فرم انجام نشد.");
            if (toggleFormOpenBtn) {
                toggleFormOpenBtn.disabled = false;
            }
        });
    }

    function showStage(name) {
        if (boot) {
            boot.hidden = name !== "boot";
        }
        if (login) {
            login.hidden = name !== "login";
        }
        if (forbidden) {
            forbidden.hidden = name !== "forbidden";
        }
        if (stage) {
            stage.hidden = name !== "manage";
        }
    }

    function exportHref(kind) {
        return "/api/dis_request_api.php?action=export&kind=" + encodeURIComponent(kind);
    }

    function setExportLinks() {
        if (exportOfficial) {
            exportOfficial.href = exportHref("owner-official");
        }
    }

    function renderStats(summary) {
        if (!statsRoot) {
            return;
        }

        var cards = [
            {
                value: Number(summary.totalUsers || 0).toLocaleString("fa-IR"),
                label: "کل کاربران",
                meta: "مبنای محاسبه کاربران فعلی سامانه"
            },
            {
                value: Number(summary.submittedCount || 0).toLocaleString("fa-IR"),
                label: "تکمیل‌شده",
                meta: "کاربرانی که فرم را ثبت کرده‌اند"
            },
            {
                value: Number(summary.pendingCount || 0).toLocaleString("fa-IR"),
                label: "تکمیل‌نشده",
                meta: "کاربرانی که هنوز پاسخی ندارند"
            },
            {
                value: String(summary.completionRate == null ? 0 : summary.completionRate).replace(/\./g, "٫") + "٪",
                label: "نرخ تکمیل",
                meta: "نسبت تکمیل‌شده‌ها به کل کاربران"
            }
        ];

        if (summary.latestSubmissionAt) {
            cards.push({
                value: formatJalaliDateTime(summary.latestSubmissionAt, "—"),
                label: "آخرین ثبت",
                meta: "زمان آخرین پاسخ ثبت‌شده"
            });
        }

        if (Array.isArray(summary.orphanedResponses) && summary.orphanedResponses.length) {
            cards.push({
                value: Number(summary.orphanedResponses.length).toLocaleString("fa-IR"),
                label: "پاسخ یتیم",
                meta: "پاسخ‌هایی که کاربرشان اکنون در فهرست اعضا نیست"
            });
        }

        statsRoot.innerHTML = cards.map(function (item) {
            return [
                '<article class="dis-stat-card">',
                '  <strong>' + safeText(item.value) + "</strong>",
                '  <span>' + safeText(item.label) + "</span>",
                '  <span>' + safeText(item.meta) + "</span>",
                "</article>"
            ].join("");
        }).join("");
    }

    function userStatusChip(statusLabel, pending) {
        return '<span class="dis-user-card__status' + (pending ? " is-pending" : "") + '">' + safeText(statusLabel) + "</span>";
    }

    function renderSubmittedList(items) {
        if (!submittedList) {
            return;
        }

        var list = Array.isArray(items) ? items : [];
        if (!list.length) {
            submittedList.innerHTML = '<div class="dis-detail-empty">هنوز پاسخی ثبت نشده است.</div>';
            return;
        }

        submittedList.innerHTML = list.map(function (item) {
            var studentNumber = item.studentNumber || "";
            var isActive = activeStudentNumber && activeStudentNumber === studentNumber;
            var metaBits = [
                userStatusChip(item.statusLabel || "تکمیل شده", false),
                "<span>" + safeText(item.user && item.user.roleLabel ? item.user.roleLabel : "کاربر") + "</span>",
                "<span>" + safeText(formatJalaliDateTime(item.submittedAt, "—")) + "</span>"
            ];
            return [
                '<button class="dis-user-card' + (isActive ? " is-active" : "") + '" type="button" data-response-student="' + safeText(studentNumber) + '">',
                '  <strong>' + safeText(item.fullName || (item.user && item.user.name) || studentNumber || "کاربر") + "</strong>",
                '  <div class="dis-user-card__meta">' + metaBits.join("") + "</div>",
                '  <div class="dis-user-card__meta">' + safeText((item.positionsText || "بدون سمت") + " • " + (item.softwareText || "بدون نرم افزار")) + "</div>",
                '  <div class="dis-user-card__meta">' + safeText(item.accessLevelsText || "بدون سطح دسترسی") + "</div>",
                "</button>"
            ].join("");
        }).join("");
    }

    function renderPendingList(items) {
        if (!pendingList) {
            return;
        }

        var list = Array.isArray(items) ? items : [];
        if (!list.length) {
            pendingList.innerHTML = '<div class="dis-detail-empty">همه کاربران فعلی فرم را تکمیل کرده‌اند.</div>';
            return;
        }

        pendingList.innerHTML = list.map(function (item) {
            var user = item.user || {};
            return [
                '<div class="dis-user-card">',
                '  <strong>' + safeText(user.name || item.studentNumber || "کاربر") + "</strong>",
                '  <div class="dis-user-card__meta">',
                userStatusChip(item.statusLabel || "تکمیل نشده", true),
                "<span>" + safeText(user.roleLabel || "کاربر") + "</span>",
                "<span>" + safeText(user.studentNumber || "—") + "</span>",
                "  </div>",
                "</div>"
            ].join("");
        }).join("");
    }

    function readonlyItem(label, value) {
        return [
            '<div class="dis-readonly-item">',
            '  <span>' + safeText(label) + "</span>",
            '  <strong>' + safeText(value || "—") + "</strong>",
            "</div>"
        ].join("");
    }

    function renderDetail(response) {
        if (!detailTitle || !detailMeta || !detailEmpty || !detailBody || !detailIdentity || !detailSections || !detailApprovals) {
            return;
        }

        if (!response) {
            detailTitle.textContent = "هنوز پاسخی انتخاب نشده است";
            detailMeta.textContent = "با انتخاب یکی از ارسال‌کنندگان، جزئیات همین‌جا نمایش داده می‌شود.";
            detailEmpty.hidden = false;
            detailBody.hidden = true;
            detailIdentity.innerHTML = "";
            detailSections.innerHTML = "";
            detailApprovals.innerHTML = "";
            return;
        }

        var user = response.user || {};
        var fields = response.fields || {};
        var approvals = response.approvals || {};
        var fullName = ((fields.firstName || "") + " " + (fields.lastName || "")).trim() || user.name || response.studentNumber || "کاربر";

        detailTitle.textContent = fullName;
        detailMeta.textContent = "وضعیت: " + (response.statusLabel || "تکمیل شده") + " • " + formatJalaliDateTime(response.submittedAt, "—");
        detailEmpty.hidden = true;
        detailBody.hidden = false;

        detailIdentity.innerHTML = [
            readonlyItem("شناسه کاربر سایت", user.studentNumber || response.studentNumber || ""),
            readonlyItem("نام کاربر سایت", user.name || ""),
            readonlyItem("عنوان نقش", user.roleLabel || ""),
            readonlyItem("زمان ثبت", formatJalaliDateTime(response.submittedAt, "—")),
            readonlyItem("کد ملی", fields.nationalCode || ""),
            readonlyItem("شماره تلفن", fields.phoneNumber || "")
        ].join("");

        detailSections.innerHTML = [
            [
                '<section class="dis-readonly-section">',
                "  <h4>سمت</h4>",
                "  <p>" + safeText((fields.positions || []).join("، ") || "—") + "</p>",
                "</section>"
            ].join(""),
            [
                '<section class="dis-readonly-section">',
                "  <h4>نرم افزار</h4>",
                "  <p>" + safeText((fields.software || []).join("، ") || "—") + "</p>",
                "</section>"
            ].join(""),
            [
                '<section class="dis-readonly-section">',
                "  <h4>سطح دسترسی</h4>",
                "  <p>" + safeText((fields.accessLevels || []).join("، ") || "—") + "</p>",
                "</section>"
            ].join(""),
            [
                '<section class="dis-readonly-section">',
                "  <h4>سایر / توضیح کوتاه</h4>",
                "  <p>" + safeText(fields.otherAccessDetail || "—") + "</p>",
                "</section>"
            ].join(""),
            [
                '<section class="dis-readonly-section">',
                "  <h4>توضیحات</h4>",
                "  <p>" + safeText(fields.notes || "—") + "</p>",
                "</section>"
            ].join("")
        ].join("");

        detailApprovals.innerHTML = Object.keys(approvals).map(function (key) {
            var item = approvals[key] || {};
            var value = String(item.value || "").trim() || "در انتظار بررسی";
            var meta = [];
            meta.push(item.status === "submitted" ? "ثبت‌شده" : "در انتظار اقدام");
            if (item.updatedAt) {
                meta.push(formatJalaliDateTime(item.updatedAt, item.updatedAt));
            }
            return [
                '<div class="dis-approval-row">',
                '  <span class="dis-approval-row__label">' + safeText(item.label || key) + "</span>",
                '  <strong class="dis-approval-row__value">' + safeText(value) + "</strong>",
                '  <span class="dis-approval-row__meta">' + safeText(meta.join(" • ")) + "</span>",
                "</div>"
            ].join("");
        }).join("");
    }

    function loadResponseDetail(studentNumber) {
        if (!studentNumber) {
            return;
        }

        activeStudentNumber = studentNumber;
        return apiGet("ownerResponse", { studentNumber: studentNumber }).then(function (payload) {
            if (consumeUnauthorized(payload, "نشست شما منقضی شده است.")) {
                return;
            }
            if (!payload || !payload.success) {
                throw new Error((payload && payload.error) || "دریافت جزئیات پاسخ انجام نشد.");
            }
            renderDetail(payload.response || null);
            var currentOverview = submittedList && submittedList.dataset.items ? JSON.parse(submittedList.dataset.items) : [];
            renderSubmittedList(currentOverview);
        }).catch(function (error) {
            showToast(error && error.message ? error.message : "دریافت جزئیات پاسخ انجام نشد.");
        });
    }

    function loadOverview() {
        if (loadingOverview) {
            return Promise.resolve();
        }

        loadingOverview = true;
        return apiGet("ownerOverview").then(function (payload) {
            if (consumeUnauthorized(payload, "نشست شما منقضی شده است.")) {
                return;
            }
            if (payload && payload.httpStatus === 403) {
                showStage("forbidden");
                return;
            }
            if (!payload || !payload.success || !payload.overview) {
                throw new Error((payload && payload.error) || "دریافت وضعیت مدیریت فرم انجام نشد.");
            }

            var overview = payload.overview;
            if (shareLinkInput) {
                shareLinkInput.value = overview.shareUrl || "";
            }
            if (openPublicFormLink && overview.sharePath) {
                openPublicFormLink.href = overview.sharePath;
            }

            renderStats(overview.summary || {});
            if (submittedTitle) {
                submittedTitle.textContent = "کاربران ثبت‌کننده (" + Number((overview.summary && overview.summary.submittedCount) || 0).toLocaleString("fa-IR") + ")";
            }
            if (pendingTitle) {
                pendingTitle.textContent = "کاربران بدون پاسخ (" + Number((overview.summary && overview.summary.pendingCount) || 0).toLocaleString("fa-IR") + ")";
            }

            var submittedItems = Array.isArray(overview.submittedUsers) ? overview.submittedUsers : [];
            var pendingItems = Array.isArray(overview.pendingUsers) ? overview.pendingUsers : [];
            if (submittedList) {
                submittedList.dataset.items = JSON.stringify(submittedItems);
            }
            renderFormStatusUi(payload.formOpen !== false);
            renderSubmittedList(submittedItems);
            renderPendingList(pendingItems);
            renderDetail(null);
            showStage("manage");

            if (submittedItems.length) {
                loadResponseDetail(activeStudentNumber || submittedItems[0].studentNumber || "");
            }
        }).catch(function (error) {
            showStage("manage");
            renderDetail(null);
            if (detailEmpty) {
                detailEmpty.textContent = error && error.message ? error.message : "دریافت وضعیت مدیریت فرم انجام نشد.";
            }
        }).finally(function () {
            loadingOverview = false;
        });
    }

    function handleAuthChange(detail) {
        if (detail.status === "session-restoring" || detail.status === "logging-out") {
            showStage("boot");
            return;
        }

        if (!detail.loggedIn || !detail.user) {
            if (loginLink) {
                loginLink.href = window.Dent1402Auth.loginUrl("/dis-request/manage/");
            }
            showStage("login");
            return;
        }

        if (!detail.user.isOwner) {
            showStage("forbidden");
            return;
        }

        showStage("manage");
        loadOverview();
    }

    if (copyShareLinkButton) {
        copyShareLinkButton.addEventListener("click", function () {
            var value = shareLinkInput ? String(shareLinkInput.value || "").trim() : "";
            if (!value) {
                return;
            }

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(value).then(function () {
                    showToast("لینک کپی شد.");
                }).catch(function () {
                    showToast("کپی لینک انجام نشد.");
                });
                return;
            }

            try {
                shareLinkInput.focus();
                shareLinkInput.select();
                document.execCommand("copy");
                showToast("لینک کپی شد.");
            } catch (_error) {
                showToast("کپی لینک انجام نشد.");
            }
        });
    }

    if (submittedList) {
        submittedList.addEventListener("click", function (event) {
            var button = event.target && event.target.closest ? event.target.closest("[data-response-student]") : null;
            if (!button) {
                return;
            }

            var studentNumber = String(button.getAttribute("data-response-student") || "").trim();
            if (!studentNumber) {
                return;
            }

            loadResponseDetail(studentNumber);
        });
    }

    if (toggleFormOpenBtn) {
        toggleFormOpenBtn.addEventListener("click", handleToggleFormOpen);
    }

    setExportLinks();
    renderDetail(null);
    window.Dent1402Auth.onChange(handleAuthChange);
})();
