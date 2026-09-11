(function () {
    "use strict";

    if (!window.Dent1402Auth) {
        return;
    }

    function $(id) {
        return document.getElementById(id);
    }

    function normalizeNumeric(value) {
        var normalized = String(value == null ? "" : value).trim();
        if (!normalized) {
            return null;
        }

        normalized = normalized
            .replace(/[\u06F0-\u06F9]/g, function (char) { return String(char.charCodeAt(0) - 0x06F0); })
            .replace(/[\u0660-\u0669]/g, function (char) { return String(char.charCodeAt(0) - 0x0660); })
            .replace(/\u066B/g, ".")
            .replace(/\u066C/g, "")
            .replace(/\u060C/g, ",")
            .replace(/,/g, ".");

        var parsed = Number(normalized);
        return Number.isFinite(parsed) && parsed >= 0 ? parsed : null;
    }

    var flow = $("grades-flow");
    var authStage = $("auth-stage");
    var loadingStage = $("grades-loading");
    var dashboard = $("grades-dashboard");
    var authCard = $("auth-card");
    var authTitle = $("grades-auth-title");
    var authMessage = $("msg");
    var authDescription = authCard ? authCard.querySelector(".site-login-guard__text") : null;
    var dashboardFeedback = $("dashboard-feedback");
    var gradesCountLabel = $("grades-count-label");
    var gradesList = $("grades-list");
    var summaryGrid = $("summary-grid");
    var studentName = $("student-name");
    var studentMeta = $("student-meta");
    var refreshBtn = $("refresh-grades-btn");
    var logoutBtn = $("reset-grades-btn");
    var accountEntryLink = $("account-entry-link");
    var ownerPanel = $("grades-owner-panel");
    var ownerSummary = $("grades-owner-summary");
    var ownerRefreshBtn = $("grades-owner-refresh");
    var ownerImportForm = $("grades-owner-import-form");
    var ownerImportText = $("grades-owner-import-text");
    var ownerImportFile = $("grades-owner-import-file");
    var ownerImportSubmit = $("grades-owner-import-submit");
    var ownerCourseSelect = $("grades-owner-course-select");
    var ownerDeleteCourseBtn = $("grades-owner-delete-course");
    var ownerResetAllBtn = $("grades-owner-reset-all");
    var ownerFeedback = $("grades-owner-feedback");
    var authApi = window.Dent1402Auth && typeof window.Dent1402Auth === "object" ? window.Dent1402Auth : null;
    var siteApi = window.Dent1402Site && typeof window.Dent1402Site === "object" ? window.Dent1402Site : null;
    function normalizeCohortKey(value) {
        if (authApi && typeof authApi.normalizeCohortKey === "function") {
            return authApi.normalizeCohortKey(value);
        }

        var clean = String(value == null ? "" : value).trim().toLowerCase();
        if (!clean || clean === "main" || clean === "1402" || clean === "dentistry-1402") {
            return "main";
        }
        if (clean === "prosthesis" || clean === "prosthesis1402") {
            return "prosthesis-1402";
        }

        clean = clean
            .replace(/[^a-z0-9\-_]+/g, "-")
            .replace(/_+/g, "-")
            .replace(/-+/g, "-")
            .replace(/^-|-$/g, "");

        return clean || "main";
    }

    function resolveInitialPageCohort() {
        var datasetValue = "";
        if (document.body && document.body.dataset) {
            datasetValue = String(document.body.dataset.gradesCohort || "").trim();
        }
        if (datasetValue) {
            return {
                cohort: normalizeCohortKey(datasetValue),
                explicit: true
            };
        }

        var query = new URLSearchParams(window.location.search || "");
        var queryValue = String(query.get("cohort") || "").trim();
        if (queryValue) {
            return {
                cohort: normalizeCohortKey(queryValue),
                explicit: true
            };
        }

        var path = String(window.location.pathname || "");
        if (path.indexOf("/prosthesis-1402/") === 0) {
            return {
                cohort: "prosthesis-1402",
                explicit: true
            };
        }

        return {
            cohort: "main",
            explicit: false
        };
    }

    var initialPageCohort = resolveInitialPageCohort();
    var pageCohort = initialPageCohort.cohort;
    var pageCohortExplicit = initialPageCohort.explicit;
    var defaultAuthTitle = authTitle ? authTitle.textContent : "";
    var defaultAuthDescription = authDescription ? authDescription.textContent : "";
    var defaultAccountEntryLabel = accountEntryLink ? accountEntryLink.textContent : "";

    var currentPayload = null;
    var currentStudentNumber = "";
    var currentUser = null;
    var ownerState = {
        loaded: false,
        loading: false,
        courses: [],
        importing: false,
        deletingCourseKey: "",
        resetting: false
    };

    function userCohort(user) {
        if (!user) {
            return "";
        }
        return normalizeCohortKey(user.cohortKey || "");
    }

    function requestCohort() {
        if (pageCohortExplicit) {
            return pageCohort;
        }

        var activeUserCohort = userCohort(currentUser);
        return activeUserCohort || pageCohort;
    }

    function syncImplicitPageCohortFromUser(user) {
        if (pageCohortExplicit) {
            return;
        }

        var activeUserCohort = userCohort(user);
        if (!activeUserCohort || activeUserCohort === "main" || activeUserCohort === pageCohort) {
            return;
        }

        pageCohort = activeUserCohort;
        if (!window.history || typeof window.history.replaceState !== "function") {
            return;
        }

        try {
            var nextUrl = new URL(window.location.href);
            nextUrl.searchParams.set("cohort", activeUserCohort);
            window.history.replaceState(window.history.state, "", nextUrl.pathname + nextUrl.search + nextUrl.hash);
        } catch (_error) {
            // Ignore URL sync failures and keep using the resolved cohort in requests.
        }
    }

    function setState(state) {
        flow.dataset.authState = state;

        authStage.hidden = state !== "signed-out" && state !== "unauthorized";
        loadingStage.hidden = state !== "restoring" && state !== "loading";
        dashboard.hidden = state !== "ready" && state !== "empty";
    }

    function resetAuthGuardCopy() {
        if (authTitle) {
            authTitle.textContent = defaultAuthTitle;
        }
        if (authDescription) {
            authDescription.textContent = defaultAuthDescription;
        }
        if (accountEntryLink) {
            accountEntryLink.textContent = defaultAccountEntryLabel;
            accountEntryLink.href = window.Dent1402Auth.loginUrl();
        }
    }

    function showAccessDeniedState(message) {
        setState("unauthorized");
        if (authTitle) {
            authTitle.textContent = "دسترسی این ورودی برای حساب شما فعال نیست";
        }
        if (authDescription) {
            authDescription.textContent = message || "برای این حساب فقط بخش نمرات همان ورودی خودت قابل نمایش است.";
        }
        if (accountEntryLink) {
            accountEntryLink.textContent = "رفتن به حساب کاربری";
            accountEntryLink.href = "/account/";
        }
        setAuthMessage("", "", false);
    }

    function setAuthMessage(text, kind, loading) {
        authMessage.className = "feedback" + (kind ? " " + kind : "");

        if (loading) {
            authMessage.innerHTML = [
                '<div class="loader">',
                '  <span class="loader-dot"></span>',
                '  <span class="loader-dot"></span>',
                '  <span class="loader-dot"></span>',
                "  <span>" + text + "</span>",
                "</div>"
            ].join("");
            return;
        }

        authMessage.textContent = text || "";
    }

    function showDashboardFeedback(text, kind) {
        if (!text) {
            dashboardFeedback.hidden = true;
            dashboardFeedback.textContent = "";
            dashboardFeedback.className = "grades-status-banner";
            return;
        }

        dashboardFeedback.hidden = false;
        dashboardFeedback.textContent = text;
        dashboardFeedback.className = "grades-status-banner" + (kind ? " " + kind : "");
    }

    function showOwnerFeedback(text, kind, loading) {
        if (!ownerFeedback) {
            return;
        }

        if (!text) {
            ownerFeedback.hidden = true;
            ownerFeedback.textContent = "";
            ownerFeedback.className = "grades-status-banner";
            return;
        }

        ownerFeedback.hidden = false;
        ownerFeedback.textContent = loading ? text + "..." : text;
        ownerFeedback.className = "grades-status-banner" + (kind ? " " + kind : "");
    }

    function isCurrentUserOwner() {
        return !!(currentUser && currentUser.isOwner);
    }

    function toSafeNumber(value, fallback) {
        var parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : (fallback || 0);
    }

    function ownerSummaryCard(label, value, meta, kind) {
        return [
            '<article class="grades-owner-summary-card' + (kind ? " is-" + kind : "") + '">',
            '  <span>' + label + '</span>',
            '  <strong>' + value + '</strong>',
            '  <small>' + meta + '</small>',
            '</article>'
        ].join("");
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

    async function gradesApiRequest(action, method, payload) {
        var requestMethod = method || "GET";
        var effectiveCohort = requestCohort();
        var requestPayload = Object.assign({}, payload || {});
        var url = "/grades/grades_api.php?action=" + encodeURIComponent(action);
        var options = {
            method: requestMethod,
            credentials: "same-origin",
            headers: {
                "Accept": "application/json"
            }
        };

        if (effectiveCohort) {
            requestPayload.cohort = effectiveCohort;
        }

        if (requestMethod === "GET" && effectiveCohort) {
            url += "&cohort=" + encodeURIComponent(effectiveCohort);
        }
        if (requestMethod !== "GET") {
            options.headers["Content-Type"] = "application/x-www-form-urlencoded; charset=UTF-8";
            options.body = new URLSearchParams(requestPayload);
        }

        var response;
        try {
            response = await fetch(url, options);
        } catch (error) {
            return networkErrorResponse();
        }
        return parseJsonResponse(response);
    }

    async function gradesApiFormRequest(action, formData) {
        var body = formData instanceof FormData ? formData : new FormData();
        var effectiveCohort = requestCohort();
        if (effectiveCohort) {
            body.set("cohort", effectiveCohort);
        }
        var response;
        try {
            response = await fetch("/grades/grades_api.php?action=" + encodeURIComponent(action), {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Accept": "application/json"
                },
                body: body
            });
        } catch (error) {
            return networkErrorResponse();
        }
        return parseJsonResponse(response);
    }

    function summaryCards(result) {
        var numericGrades = [];
        var rankedCount = 0;
        var best = null;

        (result.grades || []).forEach(function (grade) {
            var numeric = normalizeNumeric(grade.value);
            if (numeric !== null) {
                numericGrades.push(numeric);
                if (!best || numeric > best.value) {
                    best = { label: grade.label, value: numeric };
                }
            }
        });

        (result.stats || []).forEach(function (stat) {
            if (stat && stat.rank !== null && stat.rank !== undefined) {
                rankedCount += 1;
            }
        });

        var average = numericGrades.length
            ? (numericGrades.reduce(function (sum, item) { return sum + item; }, 0) / numericGrades.length).toFixed(2)
            : "—";

        return [
            {
                label: "میانگین تو",
                value: average,
                meta: numericGrades.length ? numericGrades.length.toLocaleString("fa-IR") + " نمره ثبت‌شده" : "هنوز نمره‌ای ثبت نشده"
            },
            {
                label: "بیشترین نمره",
                value: best ? best.value.toFixed(2) : "—",
                meta: best ? best.label : "فعلاً خالی"
            },
            {
                label: "رتبه‌های موجود",
                value: rankedCount ? rankedCount.toLocaleString("fa-IR") : "—",
                meta: rankedCount ? "برای بعضی درس‌ها رتبه محاسبه شده" : "رتبه‌ای ثبت نشده"
            },
            {
                label: "شناسه",
                value: currentStudentNumber || "—",
                meta: "شماره دانشجویی"
            }
        ];
    }

    function renderSummary(result) {
        summaryGrid.innerHTML = "";

        summaryCards(result).forEach(function (card) {
            var article = document.createElement("article");
            article.className = "grades-summary-card";
            article.innerHTML =
                '<span class="grades-summary-card__label">' + card.label + "</span>" +
                '<strong class="grades-summary-card__value">' + card.value + "</strong>" +
                '<span class="grades-summary-card__meta">' + card.meta + "</span>";
            summaryGrid.appendChild(article);
        });
    }

    function renderGrades(result) {
        gradesList.innerHTML = "";

        var statsMap = {};
        (result.stats || []).forEach(function (stat) {
            if (stat && stat.label) {
                statsMap[stat.label] = stat;
            }
        });

        var rows = result.grades || [];
        var availableCount = rows.filter(function (grade) {
            return normalizeNumeric(grade.value) !== null;
        }).length;

        gradesCountLabel.textContent = availableCount
            ? availableCount.toLocaleString("fa-IR") + " نمره ثبت شده"
            : "هنوز نمره‌ای ثبت نشده.";

        if (!rows.length || !availableCount) {
            var empty = document.createElement("div");
            empty.className = "grades-empty-state";
            empty.innerHTML =
                "<strong>فعلاً نمره‌ای برای نمایش نیست.</strong>" +
                "<span>اگر تازه امتحان داده‌ای، کمی بعد دوباره صفحه را تازه کن.</span>";
            gradesList.appendChild(empty);
            flow.dataset.authState = "empty";
            return;
        }

        rows.forEach(function (grade) {
            var stat = statsMap[grade.label] || null;
            var numeric = normalizeNumeric(grade.value);
            var meta = [];

            if (stat && stat.classAverage !== null && stat.classAverage !== undefined) {
                meta.push("میانگین کلاس " + Number(stat.classAverage).toFixed(2));
            }

            if (grade.maxScore !== null && grade.maxScore !== undefined && Number.isFinite(Number(grade.maxScore))) {
                meta.push("از " + Number(grade.maxScore).toLocaleString("fa-IR", { maximumFractionDigits: 2 }));
            }

            if (stat && stat.rank !== null && stat.rank !== undefined &&
                stat.totalWithScore !== null && stat.totalWithScore !== undefined) {
                meta.push("رتبه " + stat.rank + " از " + stat.totalWithScore);
            }

            var row = document.createElement("article");
            row.className = "grades-row" + (numeric === null ? " grades-row--empty" : "");
            row.innerHTML =
                '<div class="grades-row__main">' +
                    '<h4>' + grade.label + "</h4>" +
                    '<p>' + (meta.length ? meta.join(" • ") : "هنوز نمره‌ای برای این درس ثبت نشده است.") + "</p>" +
                "</div>" +
                '<div class="grades-row__value">' + ((grade.value === undefined || grade.value === "") ? "—" : grade.value) + "</div>";
            gradesList.appendChild(row);
        });
    }

    function renderOwnerManager() {
        if (!ownerPanel) {
            return;
        }

        var isOwner = isCurrentUserOwner();
        ownerPanel.hidden = !isOwner;
        if (!isOwner) {
            showOwnerFeedback("", "");
            return;
        }

        var courses = Array.isArray(ownerState.courses) ? ownerState.courses : [];
        var totalScores = courses.reduce(function (sum, course) {
            return sum + Math.max(0, Math.floor(toSafeNumber(course.withScore, 0)));
        }, 0);

        if (ownerSummary) {
            ownerSummary.innerHTML = [
                ownerSummaryCard("درس", courses.length.toLocaleString("fa-IR"), "تعداد درس‌های موجود در کارنامه"),
                ownerSummaryCard("نمره ثبت‌شده", totalScores.toLocaleString("fa-IR"), "جمع نمره‌های غیرخالی همه درس‌ها"),
                ownerSummaryCard("وضعیت", ownerState.loading ? "در حال خواندن" : "آماده", ownerState.loaded ? "داده مدیریت به‌روز شده" : "هنوز بارگذاری نشده", ownerState.loading ? "warn" : "ok")
            ].join("");
        }

        if (ownerCourseSelect) {
            var selected = ownerCourseSelect.value;
            ownerCourseSelect.innerHTML = "";
            if (!courses.length) {
                var emptyOption = document.createElement("option");
                emptyOption.value = "";
                emptyOption.textContent = "درسی ثبت نشده است";
                ownerCourseSelect.appendChild(emptyOption);
            } else {
                courses.forEach(function (course) {
                    var option = document.createElement("option");
                    var label = String(course.label || "درس بدون نام");
                    var maxScore = course.maxScore !== null && course.maxScore !== undefined
                        ? " از " + Number(course.maxScore).toLocaleString("fa-IR", { maximumFractionDigits: 2 })
                        : "";
                    option.value = String(course.key || "");
                    option.textContent = label + maxScore + " - " +
                        Math.max(0, Math.floor(toSafeNumber(course.withScore, 0))).toLocaleString("fa-IR") + " نمره";
                    option.selected = option.value === selected;
                    ownerCourseSelect.appendChild(option);
                });
                if (!ownerCourseSelect.value && ownerCourseSelect.options.length) {
                    ownerCourseSelect.selectedIndex = 0;
                }
            }
            ownerCourseSelect.disabled = ownerState.loading || ownerState.importing || ownerState.resetting || !courses.length;
        }

        if (ownerRefreshBtn) {
            ownerRefreshBtn.disabled = ownerState.loading || ownerState.importing || ownerState.resetting;
            ownerRefreshBtn.textContent = ownerState.loading ? "در حال تازه‌سازی..." : "تازه‌سازی مدیریت";
        }
        if (ownerImportSubmit) {
            ownerImportSubmit.disabled = ownerState.loading || ownerState.importing || ownerState.resetting;
            ownerImportSubmit.textContent = ownerState.importing ? "در حال import..." : "Import نمرات";
        }
        if (ownerDeleteCourseBtn) {
            var currentCourseKey = ownerCourseSelect ? String(ownerCourseSelect.value || "") : "";
            ownerDeleteCourseBtn.disabled = ownerState.loading || ownerState.importing || ownerState.resetting ||
                !currentCourseKey || ownerState.deletingCourseKey === currentCourseKey;
            ownerDeleteCourseBtn.textContent = ownerState.deletingCourseKey ? "در حال حذف درس..." : "حذف کامل درس از همه کارنامه‌ها";
        }
        if (ownerResetAllBtn) {
            ownerResetAllBtn.disabled = ownerState.loading || ownerState.importing || ownerState.resetting || !courses.length;
            ownerResetAllBtn.textContent = ownerState.resetting ? "در حال ریست..." : "ریست کامل کارنامه";
        }
        [ownerImportText, ownerImportFile].forEach(function (node) {
            if (node) {
                node.disabled = ownerState.loading || ownerState.importing || ownerState.resetting;
            }
        });
    }

    function renderDashboard(result) {
        currentPayload = result;
        studentName.textContent = result.name || "دانشجو";
        studentMeta.textContent = "شماره دانشجویی: " + (currentStudentNumber || "—");
        renderSummary(result);
        renderGrades(result);
    }

    function ensureSignedOutState(errorText) {
        resetAuthGuardCopy();
        setState(errorText ? "unauthorized" : "signed-out");
        setAuthMessage(errorText || "برای دیدن کارنامه، اول وارد حساب کاربری شو.", errorText ? "error" : "", false);
        if (accountEntryLink) {
            accountEntryLink.href = window.Dent1402Auth.loginUrl();
        }
    }

    async function fetchGrades() {
        var effectiveCohort = requestCohort();
        var url = "/grades/grades_api.php?action=me";
        if (effectiveCohort) {
            url += "&cohort=" + encodeURIComponent(effectiveCohort);
        }
        var response;
        try {
            response = await fetch(url, {
                method: "GET",
                credentials: "same-origin",
                headers: {
                    "Accept": "application/json"
                }
            });
        } catch (error) {
            return networkErrorResponse();
        }

        return parseJsonResponse(response);
    }

    function consumeUnauthorized(response, fallbackText) {
        if (siteApi && typeof siteApi.consumeUnauthorized === "function") {
            return !!siteApi.consumeUnauthorized(response, fallbackText || "نشست شما منقضی شده است. دوباره وارد شوید.");
        }
        var auth = window.Dent1402Auth && typeof window.Dent1402Auth === "object"
            ? window.Dent1402Auth
            : null;
        if (!auth) {
            return false;
        }

        var message = fallbackText || "نشست شما منقضی شده است. دوباره وارد شوید.";
        try {
            if (typeof auth.handleUnauthorizedPayload === "function") {
                return !!auth.handleUnauthorizedPayload(response, message);
            }
        } catch (_error) {
            // Ignore stale auth surface mismatch and continue fallback.
        }

        if (response && (response.loggedOut || response.httpStatus === 401)) {
            if (typeof auth.markUnauthorized === "function") {
                auth.markUnauthorized((response && response.error) || message);
            }
            return true;
        }

        return false;
    }

    async function loadOwnerCatalog(options) {
        if (!isCurrentUserOwner() || ownerState.loading) {
            renderOwnerManager();
            return;
        }

        var silent = !!(options && options.silent);
        ownerState.loading = true;
        if (!silent) {
            showOwnerFeedback("در حال تازه‌سازی مدیریت نمرات", "", true);
        }
        renderOwnerManager();

        try {
            var result = await gradesApiRequest("ownerCatalog", "GET");
            if (consumeUnauthorized(result, "نشست شما منقضی شده است. دوباره وارد شوید.")) {
                showOwnerFeedback("", "");
                return;
            }
            if (!result || !result.success) {
                showOwnerFeedback((result && result.error) || "گرفتن فهرست درس‌ها انجام نشد.", "error");
                return;
            }

            ownerState.courses = Array.isArray(result.courses) ? result.courses : [];
            ownerState.loaded = true;
            if (!silent) {
                showOwnerFeedback("مدیریت نمرات به‌روز شد.", "success");
            }
        } catch (error) {
            console.error(error);
            showOwnerFeedback(error.message || "گرفتن فهرست درس‌ها انجام نشد.", "error");
        } finally {
            ownerState.loading = false;
            renderOwnerManager();
        }
    }

    async function importOwnerGrades(event) {
        if (event) {
            event.preventDefault();
        }
        if (!isCurrentUserOwner() || ownerState.importing) {
            return;
        }

        var text = ownerImportText ? ownerImportText.value.trim() : "";
        var file = ownerImportFile && ownerImportFile.files ? ownerImportFile.files[0] : null;
        if (!text && !file) {
            showOwnerFeedback("متن import یا فایل نمرات را وارد کن.", "error");
            return;
        }

        var formData = new FormData();
        if (text) {
            formData.append("importText", text);
        }
        if (file) {
            formData.append("gradesFile", file);
        }

        ownerState.importing = true;
        showOwnerFeedback("در حال import نمرات", "", true);
        renderOwnerManager();

        try {
            var response = await gradesApiFormRequest("ownerImportGrades", formData);
            if (consumeUnauthorized(response, "نشست شما منقضی شده است. دوباره وارد شوید.")) {
                showOwnerFeedback("", "");
                return;
            }
            if (!response || !response.success) {
                showOwnerFeedback((response && response.error) || "Import نمرات انجام نشد.", "error");
                return;
            }

            ownerState.courses = Array.isArray(response.courses) ? response.courses : ownerState.courses;
            ownerState.loaded = true;
            if (ownerImportText) {
                ownerImportText.value = "";
            }
            if (ownerImportFile) {
                ownerImportFile.value = "";
            }
            showOwnerFeedback(
                (response.message || "Import نمرات انجام شد.") + " " +
                Math.max(0, Math.floor(toSafeNumber(response.importedCount, 0))).toLocaleString("fa-IR") +
                " ردیف پردازش شد.",
                "success"
            );
            await loadGrades();
        } catch (error) {
            console.error(error);
            showOwnerFeedback(error.message || "Import نمرات انجام نشد.", "error");
        } finally {
            ownerState.importing = false;
            renderOwnerManager();
        }
    }

    async function deleteOwnerGradeCourse() {
        if (!isCurrentUserOwner() || ownerState.deletingCourseKey || ownerState.resetting || !ownerCourseSelect) {
            return;
        }

        var courseKey = String(ownerCourseSelect.value || "");
        if (!courseKey) {
            showOwnerFeedback("اول یک درس را انتخاب کن.", "error");
            return;
        }

        var label = ownerCourseSelect.options[ownerCourseSelect.selectedIndex]
            ? ownerCourseSelect.options[ownerCourseSelect.selectedIndex].textContent
            : "درس انتخاب‌شده";
        if (!window.confirm("همه نمرات «" + label + "» برای همه کاربران حذف شود؟")) {
            return;
        }

        ownerState.deletingCourseKey = courseKey;
        showOwnerFeedback("در حال حذف درس از کارنامه همه کاربران", "", true);
        renderOwnerManager();

        try {
            var response = await gradesApiRequest("ownerDeleteGradeCourse", "POST", { courseKey: courseKey });
            if (consumeUnauthorized(response, "نشست شما منقضی شده است. دوباره وارد شوید.")) {
                showOwnerFeedback("", "");
                return;
            }
            if (!response || !response.success) {
                showOwnerFeedback((response && response.error) || "حذف درس انجام نشد.", "error");
                return;
            }

            ownerState.courses = Array.isArray(response.courses) ? response.courses : [];
            ownerState.loaded = true;
            showOwnerFeedback(response.message || "درس حذف شد.", "success");
            await loadGrades();
        } catch (error) {
            console.error(error);
            showOwnerFeedback(error.message || "حذف درس انجام نشد.", "error");
        } finally {
            ownerState.deletingCourseKey = "";
            renderOwnerManager();
        }
    }

    async function resetOwnerGradebook() {
        if (!isCurrentUserOwner() || ownerState.resetting) {
            return;
        }

        var confirmation = window.prompt("برای ریست کامل همه درس‌ها و نمرات، عبارت RESET را وارد کن.");
        if (confirmation !== "RESET") {
            showOwnerFeedback("ریست کارنامه لغو شد.", "");
            return;
        }

        ownerState.resetting = true;
        showOwnerFeedback("در حال ریست کامل کارنامه", "", true);
        renderOwnerManager();

        try {
            var response = await gradesApiRequest("ownerResetGradebook", "POST", { confirm: "RESET" });
            if (consumeUnauthorized(response, "نشست شما منقضی شده است. دوباره وارد شوید.")) {
                showOwnerFeedback("", "");
                return;
            }
            if (!response || !response.success) {
                showOwnerFeedback((response && response.error) || "ریست کارنامه انجام نشد.", "error");
                return;
            }

            ownerState.courses = [];
            ownerState.loaded = true;
            showOwnerFeedback(response.message || "کارنامه ریست شد.", "success");
            await loadGrades();
        } catch (error) {
            console.error(error);
            showOwnerFeedback(error.message || "ریست کارنامه انجام نشد.", "error");
        } finally {
            ownerState.resetting = false;
            renderOwnerManager();
        }
    }

    async function loadGrades() {
        setState("loading");
        showDashboardFeedback("", "");

        try {
            var result = await fetchGrades();

            if (!result || result.error) {
                if (result && result.httpStatus === 403) {
                    showAccessDeniedState((result && result.error) || "برای این حساب فقط نمرات همان ورودی فعال است.");
                    return;
                }
                if (consumeUnauthorized(result, "نشست شما منقضی شده است. دوباره وارد شوید.")) {
                    ensureSignedOutState("نشست شما منقضی شده است. دوباره وارد شوید.");
                    return;
                }

                throw new Error((result && result.error) || "گرفتن نمرات انجام نشد.");
            }

            renderDashboard(result);
            setState(flow.dataset.authState === "empty" ? "empty" : "ready");
            renderOwnerManager();
            if (isCurrentUserOwner() && !ownerState.loaded) {
                loadOwnerCatalog({ silent: true });
            }
            showDashboardFeedback("کارنامه آماده است.", "success");
        } catch (error) {
            console.error(error);
            ensureSignedOutState(error.message || "گرفتن نمرات انجام نشد.");
        }
    }

    async function refreshGrades() {
        refreshBtn.disabled = true;
        showDashboardFeedback("در حال تازه‌سازی نمرات...", "", false);

        try {
            await loadGrades();
        } finally {
            refreshBtn.disabled = false;
        }
    }

    async function logout() {
        logoutBtn.disabled = true;
        await window.Dent1402Auth.logout();
        logoutBtn.disabled = false;
    }

    function handleAuthChange(detail) {
        if (detail.status === "session-restoring" || detail.status === "logging-out") {
            setState("restoring");
            setAuthMessage("در حال بازیابی نشست...", "", true);
            return;
        }

        if (!detail.loggedIn || !detail.user) {
            currentPayload = null;
            currentStudentNumber = "";
            currentUser = null;
            ownerState.loaded = false;
            ownerState.loading = false;
            ownerState.courses = [];
            ownerState.importing = false;
            ownerState.deletingCourseKey = "";
            ownerState.resetting = false;
            gradesList.innerHTML = "";
            summaryGrid.innerHTML = "";
            renderOwnerManager();
            ensureSignedOutState(detail.status === "unauthorized" ? detail.error : "");
            return;
        }

        currentUser = detail.user;
        syncImplicitPageCohortFromUser(detail.user);
        currentStudentNumber = detail.user.studentNumber || "";
        renderOwnerManager();
        if (isCurrentUserOwner() && !ownerState.loaded && !ownerState.loading) {
            loadOwnerCatalog({ silent: true });
        }

        if (!currentPayload || currentPayload.studentNumber !== currentStudentNumber) {
            loadGrades();
        }
    }

    refreshBtn.addEventListener("click", refreshGrades);
    logoutBtn.addEventListener("click", logout);
    if (ownerRefreshBtn) {
        ownerRefreshBtn.addEventListener("click", function () {
            loadOwnerCatalog();
        });
    }
    if (ownerImportForm) {
        ownerImportForm.addEventListener("submit", importOwnerGrades);
    }
    if (ownerDeleteCourseBtn) {
        ownerDeleteCourseBtn.addEventListener("click", function (event) {
            event.preventDefault();
            deleteOwnerGradeCourse();
        });
    }
    if (ownerResetAllBtn) {
        ownerResetAllBtn.addEventListener("click", function (event) {
            event.preventDefault();
            resetOwnerGradebook();
        });
    }
    if (ownerCourseSelect) {
        ownerCourseSelect.addEventListener("change", renderOwnerManager);
    }
    window.Dent1402Auth.onChange(handleAuthChange);
})();

