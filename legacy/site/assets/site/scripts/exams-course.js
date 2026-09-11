(function () {
    "use strict";

    var root = document.getElementById("exams-course-root");
    var body = document.body;
    var courseSlug = body && body.dataset ? String(body.dataset.examsCourse || "").trim() : "";
    if (!root || !courseSlug) {
        return;
    }

    var params = new URLSearchParams(window.location.search);
    var state = {
        loading: false,
        saving: false,
        course: null,
        viewer: null,
        feedback: "",
        feedbackKind: "",
        ownerDraft: null,
        query: "",
        filter: "all",
        filtersOpen: false,
        renderFrame: 0,
        scrollRestoreFrame: 0,
        scrollRestoreTimer: 0
    };

    var FILTERS = [
        { key: "all", label: "همه" },
        { key: "completed", label: "تکمیل‌شده" },
        { key: "in-progress", label: "ادامه" },
        { key: "not-started", label: "شروع‌نشده" },
        { key: "flagged", label: "نشان‌دار" }
    ];

    function escapeHtml(value) {
        return String(value == null ? "" : value).replace(/[&<>"']/g, function (char) {
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
                    return "&#39;";
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

    function appendCohortPath(path) {
        var target = String(path || "").trim();
        var cohort = String(params.get("cohort") || "").trim();
        if (!target || !cohort || /(?:\?|&)cohort=/.test(target)) {
            return target;
        }
        return target + (target.indexOf("?") === -1 ? "?" : "&") + "cohort=" + encodeURIComponent(cohort);
    }

    function networkErrorResponse() {
        return {
            success: false,
            error: "ارتباط با سرور برقرار نشد. اتصال اینترنت خود را بررسی کنید.",
            httpStatus: 0
        };
    }

    function fetchWithTimeout(url, options, timeoutMs) {
        if (typeof AbortController !== "function") {
            return fetch(url, options);
        }
        var controller = new AbortController();
        var requestOptions = Object.assign({}, options || {}, { signal: controller.signal });
        var timer = window.setTimeout(function () {
            controller.abort();
        }, Math.max(1000, Number(timeoutMs) || 20000));
        return fetch(url, requestOptions).finally(function () {
            window.clearTimeout(timer);
        });
    }

    function apiGet(action, payload) {
        var query = new URLSearchParams(withCohort(Object.assign({ action: action }, payload || {})));
        query.set("_t", String(Date.now()));
        return fetchWithTimeout("/api/exams_api.php?" + query.toString(), {
            method: "GET",
            cache: "no-store",
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        }, 20000).then(parseJson).catch(networkErrorResponse);
    }

    function apiPost(action, payload) {
        return fetchWithTimeout("/api/exams_api.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                Accept: "application/json"
            },
            body: new URLSearchParams(withCohort(Object.assign({ action: action }, payload || {})))
        }, 20000).then(parseJson).catch(networkErrorResponse);
    }

    function authSnapshot() {
        if (!window.Dent1402Auth || typeof window.Dent1402Auth.getState !== "function") {
            return null;
        }
        try {
            return window.Dent1402Auth.getState();
        } catch (_error) {
            return null;
        }
    }

    function shouldRecheckCourseAuth(payload) {
        if (!payload || !payload.success || !payload.course || payload.viewer) {
            return false;
        }
        var access = payload.course.access || {};
        if (!access.requiresLogin || access.unlockKey !== "login-required") {
            return false;
        }
        var auth = authSnapshot();
        return !!(auth && auth.loggedIn && auth.user);
    }

    function recheckCourseAuthIfNeeded(payload) {
        if (!shouldRecheckCourseAuth(payload)) {
            return Promise.resolve(payload);
        }
        var authApi = window.Dent1402Auth;
        if (!authApi || typeof authApi.verifySession !== "function") {
            return Promise.resolve(payload);
        }

        return authApi.verifySession({ attempts: 2, delayMs: 300 }).then(function (result) {
            if (result === false) {
                return payload;
            }
            return apiGet("course", { course: courseSlug }).then(function (retryPayload) {
                return retryPayload && retryPayload.success ? retryPayload : payload;
            });
        }).catch(function () {
            return payload;
        });
    }

    function formatValue(value) {
        return (Math.max(0, Number(value) || 0)).toLocaleString("fa-IR");
    }

    function toFaDigitsText(value) {
        return String(value == null ? "" : value).replace(/\d/g, function (digit) {
            return ["۰", "۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹"][Number(digit)] || digit;
        });
    }

    function formatCount(value, noun) {
        return formatValue(value) + " " + noun;
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

    function toDatetimeLocal(value) {
        var raw = String(value || "").trim();
        if (!raw) {
            return "";
        }
        var parsed = new Date(raw);
        if (!Number.isFinite(parsed.getTime())) {
            return "";
        }
        var pad = function (input) {
            return String(input).padStart(2, "0");
        };
        return [
            parsed.getFullYear(),
            pad(parsed.getMonth() + 1),
            pad(parsed.getDate())
        ].join("-") + "T" + [pad(parsed.getHours()), pad(parsed.getMinutes())].join(":");
    }

    function fromDatetimeLocal(value) {
        var raw = String(value || "").trim();
        if (!raw) {
            return "";
        }
        var parsed = new Date(raw);
        if (!Number.isFinite(parsed.getTime())) {
            return "";
        }
        return parsed.toISOString();
    }

    function loginHref() {
        if (window.Dent1402Auth && typeof window.Dent1402Auth.loginUrl === "function") {
            return window.Dent1402Auth.loginUrl(window.location.pathname + window.location.search);
        }
        return "/account/";
    }

    function searchIcon() {
        return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.8"/><path d="M16 16L20 20" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
    }

    function filterIcon() {
        return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M7 12h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M10 17h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
    }

    function layersIcon() {
        return '<span class="exams-course-hero__art" aria-hidden="true"><span></span><span></span><span></span></span>';
    }

    function curriculumBackHref(course) {
        var meta = course && course.curriculum ? course.curriculum : null;
        if (!meta || !meta.termNumber) {
            return "";
        }

        var target = "/exams/?term=" + encodeURIComponent(String(meta.termNumber));
        if (meta.unitHasMultipleCollections && meta.unitKey) {
            target += "&unit=" + encodeURIComponent(String(meta.unitKey));
        }
        return appendCohortPath(target);
    }

    function curriculumContextHtml(course) {
        var meta = course && course.curriculum ? course.curriculum : null;
        if (!meta) {
            return "";
        }

        var items = [];
        if (meta.termLabel) {
            items.push('<span class="exams-session-meta">' + escapeHtml(meta.termLabel) + "</span>");
        }
        if (meta.categoryTitle) {
            items.push('<span class="exams-session-meta">' + escapeHtml(meta.categoryTitle) + "</span>");
        }
        if (meta.unitTitle && String(meta.unitTitle).trim() !== String(course.title || "").trim()) {
            items.push('<span class="exams-session-meta">' + escapeHtml(meta.unitTitle) + "</span>");
        }

        var backHref = curriculumBackHref(course);
        if (backHref) {
            items.push('<a class="exam-btn exam-btn--ghost exams-inline-back" href="' + escapeHtml(backHref) + '">بازگشت به ساختار</a>');
        }

        if (!items.length) {
            return "";
        }

        return '<div class="exams-course-hero__context">' + items.join("") + "</div>";
    }

    function compactText(value, fallback, maxLength) {
        var text = String(value || "").replace(/\s+/g, " ").trim();
        if (!text) {
            text = String(fallback || "").trim();
        }
        if (!text || !maxLength || text.length <= maxLength) {
            return text;
        }

        var sentence = text.split(/[.!؟]/)[0].trim();
        if (sentence && sentence.length <= maxLength) {
            return sentence;
        }

        return text.slice(0, Math.max(0, maxLength - 1)).trim() + "…";
    }

    function cleanCourseTitle(value) {
        return String(value || "")
            .replace(/^آزمون[\s‌]*های[\s‌]+/u, "")
            .replace(/^آزمون[\s‌]+/u, "")
            .trim();
    }

    function cleanSessionTitle(title, label) {
        var cleaned = String(title || "").trim();
        var cleanLabel = String(label || "").trim();
        if (!cleaned || !cleanLabel) {
            return cleaned;
        }

        [
            "سوالات " + cleanLabel + " - ",
            "سوالات " + cleanLabel + "-",
            "آزمون " + cleanLabel + " - ",
            "آزمون " + cleanLabel + "-",
            cleanLabel + " - ",
            cleanLabel + "-"
        ].forEach(function (prefix) {
            if (cleaned.indexOf(prefix) === 0) {
                cleaned = cleaned.slice(prefix.length).trim();
            }
        });

        return cleaned;
    }

    function accentClassName(index) {
        var accents = ["is-accent-a", "is-accent-b", "is-accent-c", "is-accent-d"];
        return accents[Math.abs(Number(index) || 0) % accents.length];
    }

    function joinMetaParts(parts) {
        return parts.filter(function (part) {
            return !!String(part || "").trim();
        }).join(" • ");
    }

    function simpleHeroHtml(options) {
        var config = options || {};
        return [
            '<section class="catalog-simple-hero">',
            config.actionsHtml
                ? '  <div class="catalog-simple-hero__actions">' + config.actionsHtml + "</div>"
                : "",
            '  <div class="catalog-simple-hero__copy">',
            config.eyebrow
                ? '    <span class="catalog-simple-hero__eyebrow">' + escapeHtml(config.eyebrow) + "</span>"
                : "",
            '    <h2 class="catalog-simple-hero__title">' + escapeHtml(config.title || "") + "</h2>",
            config.meta
                ? '    <p class="catalog-simple-hero__meta">' + escapeHtml(config.meta) + "</p>"
                : "",
            config.secondaryHtml
                ? '    <div class="catalog-simple-hero__secondary">' + config.secondaryHtml + "</div>"
                : "",
            "  </div>",
            "</section>"
        ].join("");
    }

    function simpleRowHtml(options) {
        var config = options || {};
        return [
            '<article class="catalog-simple-row' + (config.rowClassName ? " " + escapeHtml(config.rowClassName) : "") + '">',
            '  <a class="catalog-simple-row__link" href="' + escapeHtml(config.href || "#") + '">',
            '    <div class="catalog-simple-row__body">',
            (config.eyebrow || config.status)
                ? '      <div class="catalog-simple-row__topline">'
                    + (config.eyebrow ? '<span class="catalog-simple-row__eyebrow">' + escapeHtml(config.eyebrow) + "</span>" : "")
                    + (config.status ? '<span class="catalog-simple-row__status' + (config.statusMuted ? " is-muted" : "") + '">' + escapeHtml(config.status) + "</span>" : "")
                    + "</div>"
                : "",
            '      <h3 class="catalog-simple-row__title">' + escapeHtml(config.title || "") + "</h3>",
            config.meta
                ? '      <p class="catalog-simple-row__meta">' + escapeHtml(config.meta) + "</p>"
                : "",
            "    </div>",
            '    <span class="catalog-simple-row__visual' + (config.visualMuted ? " is-muted" : "") + '" aria-hidden="true">'
                + (config.visualLabel ? "<strong>" + escapeHtml(config.visualLabel) + "</strong>" : "")
                + "</span>",
            '    <div class="catalog-simple-row__tail">',
            config.actionLabel
                ? '<span class="catalog-simple-row__action">' + escapeHtml(config.actionLabel) + "</span>"
                : "",
            '      <span class="catalog-simple-row__chevron">‹</span>',
            "    </div>",
            "  </a>",
            "</article>"
        ].join("");
    }

    function statusMeta(course) {
        var access = course && course.access ? course.access : {};
        if (course && course.paymentMode === "paid" && access.hasAccess) {
            return {
                label: access.unlockLabel || "باز شده",
                className: "exams-status-pill exams-status-pill--unlocked"
            };
        }
        if (course && course.paymentMode === "paid") {
            return {
                label: course.access && course.access.requiresLogin ? "نیاز به ورود" : (course.amountLabel || "پولی"),
                className: "exams-status-pill exams-status-pill--paid"
            };
        }
        return {
            label: "رایگان",
            className: "exams-status-pill exams-status-pill--free"
        };
    }

    function courseItemNoun(course) {
        if (course && course.supportsDirectAttemptableExams) {
            return "جلسه";
        }
        return "بخش";
    }

    function sessionLastAttempt(progress) {
        var record = progress && progress.assessmentReport ? progress.assessmentReport : null;
        return String((progress && progress.lastAttemptAt) || (record && (record.updatedAt || record.submittedAt)) || "").trim();
    }

    function sessionStatus(session, course) {
        var access = course && course.access ? course.access : {};
        var progress = session && session.viewerProgress ? session.viewerProgress : {};
        var hasReport = !!(progress && progress.hasAssessmentReport && progress.assessmentReport);
        var hasActivity = !!(progress && (progress.hasActivity || progress.hasFlags));

        if (session && session.isLocked) {
            if (access.requiresLogin) {
                return {
                    key: "locked",
                    label: "نیاز به ورود",
                    actionLabel: "ورود",
                    actionHref: loginHref(),
                    hint: "برای دیدن سوال‌ها ابتدا باید وارد حساب کاربری شوی.",
                    className: "exam-session-status exam-session-status--locked"
                };
            }
            return {
                key: "locked",
                label: "نیاز به پرداخت",
                actionLabel: access.canPurchase ? "فعال‌سازی" : "بازگشت",
                actionHref: access.canPurchase ? appendCohortPath(course.paymentPath || session.href || course.path || "/exams/") : appendCohortPath(course.path || "/exams/"),
                hint: access.canPurchase
                    ? "بعد از فعال‌سازی، همه جلسه‌های همین درس از همین‌جا باز می‌شوند."
                    : "فعلاً دسترسی این درس از سمت مدیریت غیرفعال است.",
                className: "exam-session-status exam-session-status--locked"
            };
        }

        if (session && session.comingSoon) {
            return {
                key: "coming-soon",
                label: "به‌زودی",
                actionLabel: "جزئیات",
                actionHref: appendCohortPath(session.path || session.href || course.path || "/exams/"),
                hint: String(session.emptyStateMessage || session.description || "سؤال‌های این جلسه هنوز اضافه نشده‌اند و به‌زودی از همین صفحه فعال می‌شوند."),
                className: "exam-session-status exam-session-status--section"
            };
        }

        if (!session || !session.attemptable) {
            return {
                key: "section",
                label: "بخش",
                actionLabel: "مشاهده",
                actionHref: appendCohortPath((session && (session.path || session.href)) || course.path || "/exams/"),
                hint: "این ردیف یک بخش چندقسمتی است و شروع آزمون از صفحه بعد انجام می‌شود.",
                className: "exam-session-status exam-session-status--section"
            };
        }

        if (hasReport) {
            return {
                key: "completed",
                label: "تکمیل شده",
                actionLabel: "مرور",
                actionHref: appendCohortPath(session.path || session.href || course.path || "/exams/"),
                hint: "کارنامه این جلسه روی حساب شما ذخیره شده است.",
                className: "exam-session-status exam-session-status--completed"
            };
        }

        if (hasActivity) {
            return {
                key: "in-progress",
                label: "ادامه",
                actionLabel: "ادامه",
                actionHref: appendCohortPath(session.path || session.href || course.path || "/exams/"),
                hint: "آخرین فعالیت شما برای این جلسه ذخیره شده است.",
                className: "exam-session-status exam-session-status--progress"
            };
        }

        return {
            key: "not-started",
            label: "شروع",
            actionLabel: "شروع",
            actionHref: appendCohortPath(session.path || session.href || course.path || "/exams/"),
            hint: "انتخاب حالت آزمون داخل صفحه همین جلسه انجام می‌شود.",
            className: "exam-session-status exam-session-status--fresh"
        };
    }

    function createSessionModel(session, course) {
        var progress = session && session.viewerProgress ? session.viewerProgress : null;
        var report = progress && progress.assessmentReport ? progress.assessmentReport : null;
        var status = sessionStatus(session, course);
        var flagsCount = Math.max(0, Number(progress && progress.flagsCount || 0));
        var lastAttemptAt = sessionLastAttempt(progress);
        var label = String(session && session.label || "").trim();
        var title = cleanSessionTitle(
            String(session && session.title || "").trim() || label || "جلسه",
            label
        ) || label || "جلسه";

        return {
            raw: session,
            title: title,
            label: label,
            attemptable: !!(session && session.attemptable),
            questionCount: Math.max(0, Number(session && session.questionCount || 0)),
            flagsCount: flagsCount,
            report: report,
            progress: progress,
            status: status,
            lastAttemptAt: lastAttemptAt,
            searchable: [title, String(session && session.label || ""), String(session && session.slug || "")].join(" ").toLowerCase()
        };
    }

    function filterCounts(items) {
        var counts = {
            all: items.length,
            completed: 0,
            "in-progress": 0,
            "not-started": 0,
            flagged: 0
        };

        items.forEach(function (item) {
            if (counts[item.status.key] !== undefined) {
                counts[item.status.key] += 1;
            }
            if (item.flagsCount > 0) {
                counts.flagged += 1;
            }
        });

        return counts;
    }

    function matchesQuery(item) {
        var query = String(state.query || "").trim().toLowerCase();
        if (!query) {
            return true;
        }
        return item.searchable.indexOf(query) !== -1;
    }

    function matchesFilter(item) {
        if (state.filter === "all") {
            return true;
        }
        if (state.filter === "flagged") {
            return item.flagsCount > 0;
        }
        return item.status.key === state.filter;
    }

    function summaryHtml(course) {
        var access = statusMeta(course);
        var heroTitle = cleanCourseTitle(course.title || course.heroTitle || "") || String(course.title || course.heroTitle || "").trim();
        var heroDescription = compactText(
            course.heroDescription,
            "آزمون هر جلسه و سطح دوم را از همین‌جا می‌بینی.",
            72
        );
        var heroKicker = courseItemNoun(course) === "بخش" ? "انتخاب بخش" : "انتخاب جلسه";

        return simpleHeroHtml({
            eyebrow: heroKicker,
            title: heroTitle,
            meta: heroDescription,
            secondaryHtml: [
                '<span class="' + escapeHtml(access.className) + '">' + escapeHtml(access.label) + "</span>",
                curriculumContextHtml(course)
            ].filter(Boolean).join("")
        });
    }

    function toolbarHtml(items) {
        var counts = filterCounts(items);
        return [
            '<section class="exams-card exams-toolbar-card">',
            '  <div class="exams-toolbar-row">',
            '    <label class="exams-search-field" for="exams-session-search">',
            '      <span class="exams-search-field__icon">' + searchIcon() + "</span>",
            '      <input id="exams-session-search" type="search" inputmode="search" autocomplete="off" aria-label="جستجو در عنوان جلسه" placeholder="جستجو در عنوان جلسه..." value="' + escapeHtml(state.query) + '">',
            "    </label>",
            '    <button class="exams-filter-launch' + (state.filtersOpen ? " is-active" : "") + '" type="button" data-filter-toggle aria-expanded="' + (state.filtersOpen ? "true" : "false") + '">',
            '      <span class="exams-filter-launch__icon">' + filterIcon() + "</span>",
            '      <span>فیلترها</span>',
            "    </button>",
            "  </div>",
            state.filtersOpen
                ? '  <div class="exams-filter-row" role="tablist" aria-label="فیلتر جلسه‌ها">'
                : "",
            state.filtersOpen
                ? FILTERS.map(function (filter) {
                    var isActive = state.filter === filter.key;
                    return [
                        '<button class="exams-filter-chip' + (isActive ? " is-active" : "") + '" type="button" data-session-filter="' + escapeHtml(filter.key) + '" role="tab" aria-selected="' + (isActive ? "true" : "false") + '">',
                        '  <span>' + escapeHtml(filter.label) + "</span>",
                        '  <strong>' + escapeHtml(formatValue(counts[filter.key] || 0)) + "</strong>",
                        "</button>"
                    ].join("");
                }).join("")
                : "",
            state.filtersOpen
                ? "  </div>"
                : "",
            "</section>"
        ].join("");
    }

    function paywallHtml(course) {
        if (!course || course.paymentMode !== "paid" || (course.access && course.access.hasAccess)) {
            return "";
        }

        var access = course.access || {};
        var actionHref = access.requiresLogin
            ? loginHref()
            : appendCohortPath(course.paymentPath || course.path || "/exams/");
        var actionLabel = access.requiresLogin
            ? "ورود برای ادامه"
            : (access.canPurchase ? "فعال‌سازی همه جلسه‌ها" : "بازگشت");
        var note = access.requiresLogin
            ? "برای ذخیره کارنامه و شروع جلسه‌ها ابتدا باید وارد حساب کاربری خودت شوی."
            : (course.paymentDescription || "با یک بار پرداخت، دسترسی همه جلسه‌های این درس برای همین حساب فعال می‌شود.");

        return [
            '<aside class="exams-card exams-access-card">',
            '  <div class="exams-access-card__head">',
            '    <div>',
            '      <span class="exams-kicker">دسترسی به درس</span>',
            '      <h3 class="exams-panel-title">' + escapeHtml(course.amountLabel || "فعال‌سازی درس") + "</h3>",
            "    </div>",
            '    <span class="' + escapeHtml(statusMeta(course).className) + '">' + escapeHtml(statusMeta(course).label) + "</span>",
            "  </div>",
            '  <p class="exams-inline-note">' + escapeHtml(note) + "</p>",
            '  <div class="exams-card-actions">',
            '    <a class="exam-btn exam-btn--primary" href="' + escapeHtml(actionHref) + '">' + escapeHtml(actionLabel) + "</a>",
            course.stats && course.stats.totalOrders
                ? '<span class="exams-session-meta">' + escapeHtml(formatValue(course.stats.totalOrders)) + " سفارش</span>"
                : "",
            "  </div>",
            "</aside>"
        ].join("");
    }

    function feedbackHtml() {
        if (!state.feedback) {
            return '<div class="exams-feedback"></div>';
        }
        return '<div class="exams-feedback is-' + escapeHtml(state.feedbackKind || "") + '">' + escapeHtml(state.feedback) + "</div>";
    }

    function cloneOwnerDiscountCode(entry) {
        var source = entry && typeof entry === "object" ? entry : {};
        return {
            code: String(source.code || ""),
            label: String(source.label || ""),
            type: source.type === "percent" ? "percent" : "fixed",
            amount: source.amount == null ? "" : String(source.amount),
            maxUses: source.maxUses == null ? "" : String(source.maxUses),
            studentNumber: String(source.studentNumber || ""),
            expiresAt: String(source.expiresAt || ""),
            isEnabled: source.isEnabled !== false,
            usedCount: Math.max(0, Number(source.usedCount) || 0),
            remainingUses: source.remainingUses == null ? null : Math.max(0, Number(source.remainingUses) || 0)
        };
    }

    function buildOwnerDraft(course) {
        var ownerSettings = course && course.ownerSettings ? course.ownerSettings : {};
        return {
            paymentMode: String(course && course.paymentMode || "free") === "paid" ? "paid" : "free",
            amount: String(course && course.amount != null ? course.amount : ""),
            discountCodes: Array.isArray(ownerSettings.discountCodes)
                ? ownerSettings.discountCodes.map(cloneOwnerDiscountCode)
                : []
        };
    }

    function resolveOwnerDraft(course) {
        if (!state.ownerDraft) {
            state.ownerDraft = buildOwnerDraft(course);
        }
        return state.ownerDraft;
    }

    function ownerDiscountUsageText(entry) {
        var parts = [];
        var usedCount = Math.max(0, Number(entry && entry.usedCount) || 0);
        if (usedCount > 0) {
            parts.push("مصرف: " + formatValue(usedCount));
        }
        if (entry && entry.remainingUses != null) {
            parts.push("باقی‌مانده: " + formatValue(entry.remainingUses));
        } else if (entry && entry.maxUses) {
            parts.push("سقف مصرف: " + formatValue(entry.maxUses));
        }
        if (entry && entry.studentNumber) {
            parts.push("اختصاصی برای " + entry.studentNumber);
        }
        if (entry && entry.expiresAt) {
            parts.push("انقضا: " + formatDateTime(entry.expiresAt, "—"));
        }
        return parts.join(" | ");
    }

    function ownerDiscountRowHtml(entry, index) {
        var type = entry && entry.type === "percent" ? "percent" : "fixed";
        var usageText = ownerDiscountUsageText(entry);
        return [
            '<article class="exams-owner-discount-row" data-exams-discount-row>',
            '  <label class="exams-owner-label"><span>کد</span><input class="exams-owner-input" data-discount-code type="text" dir="ltr" data-latin-digits="true" maxlength="40" value="' + escapeHtml(entry.code || "") + '" placeholder="EXAM10"></label>',
            '  <label class="exams-owner-label"><span>عنوان</span><input class="exams-owner-input" data-discount-label type="text" maxlength="120" value="' + escapeHtml(entry.label || "") + '" placeholder="تخفیف این درس"></label>',
            '  <label class="exams-owner-label"><span>نوع</span><select class="exams-owner-input" data-discount-type><option value="fixed"' + (type === "fixed" ? " selected" : "") + '>مبلغ ثابت</option><option value="percent"' + (type === "percent" ? " selected" : "") + '>درصد</option></select></label>',
            '  <label class="exams-owner-label"><span>مقدار</span><input class="exams-owner-input" data-discount-amount type="text" inputmode="numeric" dir="ltr" data-latin-digits="true" value="' + escapeHtml(String(entry.amount || "")) + '" placeholder="' + (type === "percent" ? "10" : "30000") + '"></label>',
            '  <label class="exams-owner-label"><span>سقف مصرف</span><input class="exams-owner-input" data-discount-max-uses type="text" inputmode="numeric" dir="ltr" data-latin-digits="true" value="' + escapeHtml(String(entry.maxUses || "")) + '" placeholder="اختیاری"></label>',
            '  <label class="exams-owner-label"><span>شماره دانشجویی اختصاصی</span><input class="exams-owner-input" data-discount-student-number type="text" inputmode="numeric" dir="ltr" data-latin-digits="true" value="' + escapeHtml(entry.studentNumber || "") + '" placeholder="اختیاری"></label>',
            '  <label class="exams-owner-label"><span>انقضا</span><input class="exams-owner-input" data-discount-expires type="datetime-local" value="' + escapeHtml(toDatetimeLocal(entry.expiresAt || "")) + '"></label>',
            '  <label class="exams-owner-discount-toggle"><input data-discount-enabled type="checkbox"' + (entry.isEnabled ? " checked" : "") + '><span>فعال</span></label>',
            '  <button class="exam-btn exam-btn--ghost exams-owner-discount-remove" type="button" data-exams-remove-discount="' + escapeHtml(String(index)) + '">حذف</button>',
            usageText ? '<p class="exams-owner-discount-meta">' + escapeHtml(usageText) + "</p>" : "",
            "</article>"
        ].join("");
    }

    function readOwnerDiscountRows(form) {
        return Array.prototype.slice.call(form.querySelectorAll("[data-exams-discount-row]")).map(function (row) {
            return {
                code: row.querySelector("[data-discount-code]") ? row.querySelector("[data-discount-code]").value : "",
                label: row.querySelector("[data-discount-label]") ? row.querySelector("[data-discount-label]").value : "",
                type: row.querySelector("[data-discount-type]") ? row.querySelector("[data-discount-type]").value : "fixed",
                amount: row.querySelector("[data-discount-amount]") ? row.querySelector("[data-discount-amount]").value : "",
                maxUses: row.querySelector("[data-discount-max-uses]") ? row.querySelector("[data-discount-max-uses]").value : "",
                studentNumber: row.querySelector("[data-discount-student-number]") ? row.querySelector("[data-discount-student-number]").value : "",
                expiresAt: row.querySelector("[data-discount-expires]") ? fromDatetimeLocal(row.querySelector("[data-discount-expires]").value) : "",
                isEnabled: row.querySelector("[data-discount-enabled]") ? row.querySelector("[data-discount-enabled]").checked : true
            };
        });
    }

    function syncOwnerDraftFromForm(form) {
        if (!form) {
            return state.ownerDraft;
        }
        var amountInput = form.querySelector("#exams-owner-amount");
        var modeInput = form.querySelector("input[name='paymentMode']:checked");
        state.ownerDraft = {
            paymentMode: modeInput ? modeInput.value : "free",
            amount: String(amountInput && amountInput.value || ""),
            discountCodes: readOwnerDiscountRows(form)
        };
        return state.ownerDraft;
    }

    function nextOwnerDiscountCode() {
        var draft = state.ownerDraft && Array.isArray(state.ownerDraft.discountCodes)
            ? state.ownerDraft.discountCodes
            : [];
        return {
            code: "EXAM" + String(draft.length + 1),
            label: "",
            type: "percent",
            amount: "10",
            maxUses: "",
            studentNumber: "",
            expiresAt: "",
            isEnabled: true,
            usedCount: 0,
            remainingUses: null
        };
    }

    function ownerPanelHtml(course) {
        if (!course || !course.ownerSettings || !course.ownerSettings.canManage) {
            return "";
        }

        var draft = resolveOwnerDraft(course);
        var isPaid = String(draft.paymentMode || "free") === "paid";
        var openAttr = state.feedback || state.saving ? " open" : "";
        var discountCodes = Array.isArray(draft.discountCodes) ? draft.discountCodes : [];

        return [
            '<details class="exams-card exams-owner-shell"' + openAttr + ">",
            '  <summary class="exams-owner-shell__summary">',
            '    <span class="exams-owner-chip">فقط برای مالک</span>',
            '    <span class="exams-owner-shell__title">تنظیم دسترسی و مبلغ این درس</span>',
            '    <span class="exams-owner-shell__meta">به‌روزرسانی: ' + escapeHtml(formatDateTime(course.ownerSettings.updatedAt, "—")) + "</span>",
            "  </summary>",
            '  <div class="exams-owner-shell__body">',
            '    <p class="exams-inline-note">پرداخت این درس یک‌باره است و بعد از تایید، همه جلسه‌های همین درس برای همان حساب باز می‌شود.</p>',
            '    <form id="exams-owner-form" class="exams-owner-form" novalidate>',
            '      <div class="exams-owner-row">',
            '        <div class="exams-owner-modes">',
            '          <label class="exams-owner-mode"><input type="radio" name="paymentMode" value="free"' + (!isPaid ? " checked" : "") + '> <span>رایگان</span></label>',
            '          <label class="exams-owner-mode"><input type="radio" name="paymentMode" value="paid"' + (isPaid ? " checked" : "") + '> <span>پولی</span></label>',
            "        </div>",
            "      </div>",
            '      <label class="exams-owner-label">',
            "        <span>هزینه این درس</span>",
            '        <input class="exams-owner-input" id="exams-owner-amount" name="amount" type="text" inputmode="numeric" dir="ltr" data-latin-digits="true" value="' + escapeHtml(String(draft.amount || "")) + '" placeholder="مثلاً 450000">',
            "      </label>",
            '      <section class="exams-owner-section">',
            '        <div class="exams-owner-section__head">',
            '          <div><h4>کدهای تخفیف</h4><p>برای این درس می‌توانی تخفیف درصدی یا مبلغ ثابت بسازی، سقف مصرف بگذاری و آن را فقط برای یک شماره دانشجویی فعال کنی.</p></div>',
            '          <button class="exam-btn exam-btn--ghost" type="button" data-exams-add-discount>افزودن کد</button>',
            "        </div>",
            '        <div class="exams-owner-discount-list">' + (discountCodes.length
                ? discountCodes.map(function (entry, index) {
                    return ownerDiscountRowHtml(cloneOwnerDiscountCode(entry), index);
                }).join("")
                : '<div class="exams-owner-discount-empty">هنوز کد تخفیفی برای این درس ثبت نشده است.</div>') + "</div>",
            "      </section>",
            '      <div class="exams-owner-actions">',
            '        <button class="exam-btn exam-btn--primary" type="submit"' + (state.saving ? " disabled" : "") + '>' + (state.saving ? "در حال ذخیره..." : "ذخیره تنظیمات") + "</button>",
            "      </div>",
                     feedbackHtml(),
            "    </form>",
            "  </div>",
            "</details>"
        ].join("");
    }

    function buyerRowHtml(buyer) {
        var name = String((buyer && buyer.payerName) || "").trim() || "بدون نام ثبت‌شده";
        var studentNumber = String((buyer && buyer.payerStudentNumber) || "").trim();
        var amountLabel = String((buyer && buyer.amountLabel) || "").trim();
        var paidAt = formatDateTime(buyer && buyer.paidAt, "—");
        var discountCode = String((buyer && buyer.discountCode) || "").trim();

        return [
            '<article class="exams-buyer-row">',
            '  <div class="exams-buyer-row__main">',
            '    <span class="exams-buyer-row__name">' + escapeHtml(name) + "</span>",
            studentNumber
                ? '    <span class="exams-buyer-row__student">شماره دانشجویی: ' + escapeHtml(studentNumber) + "</span>"
                : "",
            "  </div>",
            '  <div class="exams-buyer-row__meta">',
            amountLabel ? '    <span class="exams-buyer-row__amount">' + escapeHtml(amountLabel) + "</span>" : "",
            '    <span class="exams-buyer-row__date">' + escapeHtml(paidAt) + "</span>",
            discountCode ? '    <span class="exams-buyer-row__discount">کد تخفیف: ' + escapeHtml(discountCode) + "</span>" : "",
            "  </div>",
            "</article>"
        ].join("");
    }

    function ownerBuyersHtml(course) {
        if (!course || !course.ownerSettings || !course.ownerSettings.canManage) {
            return "";
        }

        var buyers = Array.isArray(course.ownerSettings.buyers) ? course.ownerSettings.buyers : [];

        return [
            '<details class="exams-card exams-owner-shell exams-buyers-shell">',
            '  <summary class="exams-owner-shell__summary">',
            '    <span class="exams-owner-chip">فقط برای مالک</span>',
            '    <span class="exams-owner-shell__title">خریداران این درس (' + escapeHtml(formatValue(buyers.length)) + ")</span>",
            "  </summary>",
            '  <div class="exams-owner-shell__body">',
            buyers.length
                ? '<div class="exams-buyers-list">' + buyers.map(buyerRowHtml).join("") + "</div>"
                : '<div class="exams-owner-discount-empty">هنوز کسی این درس را نخریده است.</div>',
            "  </div>",
            "</details>"
        ].join("");
    }

    function sessionMetaText(item) {
        if (item.status.key === "completed") {
            return "کارنامه و مرور پاسخ‌ها داخل همین جلسه باز می‌شود.";
        }
        if (item.status.key === "in-progress") {
            return "ادامه پاسخ‌گویی از همین جلسه انجام می‌شود.";
        }
        if (item.status.key === "not-started") {
            return "شروع آزمون از داخل همین جلسه انجام می‌شود.";
        }
        if (item.status.key === "coming-soon") {
            return compactText(item.status.hint || item.raw && item.raw.description || "", "این جلسه هنوز در حال تکمیل است.", 74);
        }
        return compactText(item.status.hint || "", "", 74);
    }

    function sessionVisualLabel(item, index) {
        var raw = String(item && item.label || "").trim();
        var match = raw.match(/\d+(?:\s*[-/]\s*\d+)*/);
        if (match && match[0]) {
            return toFaDigitsText(match[0].replace(/\s+/g, ""));
        }
        return formatValue(index + 1);
    }

    function sessionCardHtml(item, index) {
        var isLocked = item.status.key === "locked";
        var meta = sessionMetaText(item);

        return simpleRowHtml({
            href: item.status.actionHref,
            eyebrow: item.label || "جلسه",
            status: item.status.label,
            statusMuted: isLocked,
            title: item.title,
            meta: meta,
            rowClassName: accentClassName(index) + (isLocked ? " is-empty" : ""),
            visualLabel: sessionVisualLabel(item, index),
            visualMuted: isLocked
        });
    }

    function emptyStateHtml(message) {
        return '<div class="exams-card exams-empty">' + escapeHtml(message) + "</div>";
    }

    function captureCourseUiSnapshot() {
        var shell = root.querySelector(".exams-course-shell");
        if (!shell) {
            return null;
        }

        var scrollNode = root.querySelector(".exams-course-scroll");
        var searchInput = root.querySelector("#exams-session-search");
        var ownerShell = root.querySelector(".exams-owner-shell");
        var activeElement = document.activeElement;
        var searchHasFocus = !!searchInput && activeElement === searchInput;

        return {
            scrollTop: scrollNode ? scrollNode.scrollTop : 0,
            searchHasFocus: searchHasFocus,
            searchSelectionStart: searchHasFocus && typeof searchInput.selectionStart === "number" ? searchInput.selectionStart : null,
            searchSelectionEnd: searchHasFocus && typeof searchInput.selectionEnd === "number" ? searchInput.selectionEnd : null,
            ownerPanelOpen: !!(ownerShell && ownerShell.open)
        };
    }

    function restoreCourseUiSnapshot(snapshot) {
        if (!snapshot) {
            return;
        }

        if (state.scrollRestoreFrame) {
            window.cancelAnimationFrame(state.scrollRestoreFrame);
            state.scrollRestoreFrame = 0;
        }
        if (state.scrollRestoreTimer) {
            window.clearTimeout(state.scrollRestoreTimer);
            state.scrollRestoreTimer = 0;
        }

        var ownerShell = root.querySelector(".exams-owner-shell");
        if (ownerShell && snapshot.ownerPanelOpen && !state.saving && !state.feedback) {
            ownerShell.open = true;
        }

        var searchInput = root.querySelector("#exams-session-search");
        if (searchInput && snapshot.searchHasFocus) {
            try {
                searchInput.focus({ preventScroll: true });
            } catch (_error) {
                searchInput.focus();
            }
            if (typeof searchInput.setSelectionRange === "function"
                && typeof snapshot.searchSelectionStart === "number"
                && typeof snapshot.searchSelectionEnd === "number") {
                searchInput.setSelectionRange(snapshot.searchSelectionStart, snapshot.searchSelectionEnd);
            }
        }

        var scrollNode = root.querySelector(".exams-course-scroll");
        if (scrollNode) {
            var targetScrollTop = Math.max(0, Number(snapshot.scrollTop) || 0);
            scrollNode.scrollTop = targetScrollTop;
            state.scrollRestoreFrame = window.requestAnimationFrame(function () {
                state.scrollRestoreFrame = 0;
                if (root.contains(scrollNode)) {
                    scrollNode.scrollTop = targetScrollTop;
                }
            });
            state.scrollRestoreTimer = window.setTimeout(function () {
                state.scrollRestoreTimer = 0;
                if (root.contains(scrollNode)) {
                    scrollNode.scrollTop = targetScrollTop;
                }
            }, 90);
        }
    }

    function renderCourse(options) {
        var course = state.course;
        var uiSnapshot = options && options.preserveUi ? captureCourseUiSnapshot() : null;
        if (!course) {
            root.innerHTML = emptyStateHtml("اطلاعات این درس در دسترس نیست.");
            return;
        }

        var rawSessions = Array.isArray(course.exams) ? course.exams : [];
        var items = rawSessions.map(function (session) {
            return createSessionModel(session, course);
        });
        var filteredItems = items.filter(function (item) {
            return matchesQuery(item) && matchesFilter(item);
        });

        root.innerHTML = [
            '<section class="exams-course-shell">',
            '  <div class="exams-course-head">',
                     summaryHtml(course),
            "  </div>",
            '  <div class="exams-course-scroll">',
                     toolbarHtml(items),
            filteredItems.length
                ? '<section class="catalog-simple-stack">' + filteredItems.map(function (item, index) {
                    return sessionCardHtml(item, index);
                }).join("") + "</section>"
                : emptyStateHtml("برای این جستجو یا فیلتر، جلسه‌ای پیدا نشد."),
                     paywallHtml(course),
                     ownerPanelHtml(course),
                     ownerBuyersHtml(course),
            "  </div>",
            "</section>"
        ].join("");

        restoreCourseUiSnapshot(uiSnapshot);
    }

    function scheduleCourseRender() {
        if (state.renderFrame) {
            window.cancelAnimationFrame(state.renderFrame);
        }

        state.renderFrame = window.requestAnimationFrame(function () {
            state.renderFrame = 0;
            renderCourse({ preserveUi: true });
        });
    }

    function setLoading() {
        if (state.renderFrame) {
            window.cancelAnimationFrame(state.renderFrame);
            state.renderFrame = 0;
        }
        root.innerHTML = '<div class="exams-card exams-loading">در حال بارگذاری این درس...</div>';
    }

    function setError(message) {
        if (state.renderFrame) {
            window.cancelAnimationFrame(state.renderFrame);
            state.renderFrame = 0;
        }
        root.innerHTML = emptyStateHtml(message || "بارگذاری انجام نشد.");
    }

    function load() {
        if (state.loading) {
            return;
        }
        state.loading = true;
        setLoading();
        apiGet("course", { course: courseSlug }).then(recheckCourseAuthIfNeeded).then(function (payload) {
            if (!payload || !payload.success || !payload.course) {
                throw new Error((payload && payload.error) || "بارگذاری این درس انجام نشد.");
            }
            state.course = payload.course;
            state.ownerDraft = null;
            state.viewer = payload.viewer || null;
            renderCourse();
        }).catch(function (error) {
            setError(error && error.message ? error.message : "بارگذاری انجام نشد.");
        }).finally(function () {
            state.loading = false;
        });
    }

    root.addEventListener("click", function (event) {
        var addDiscountButton = event.target.closest("[data-exams-add-discount]");
        if (addDiscountButton) {
            var ownerFormForAdd = root.querySelector("#exams-owner-form");
            if (ownerFormForAdd) {
                syncOwnerDraftFromForm(ownerFormForAdd);
            }
            if (!state.ownerDraft) {
                state.ownerDraft = buildOwnerDraft(state.course);
            }
            state.ownerDraft.discountCodes.push(nextOwnerDiscountCode());
            renderCourse({ preserveUi: true });
            return;
        }

        var removeDiscountButton = event.target.closest("[data-exams-remove-discount]");
        if (removeDiscountButton) {
            var ownerFormForRemove = root.querySelector("#exams-owner-form");
            if (ownerFormForRemove) {
                syncOwnerDraftFromForm(ownerFormForRemove);
            }
            if (!state.ownerDraft || !Array.isArray(state.ownerDraft.discountCodes)) {
                return;
            }
            var discountIndex = Number(removeDiscountButton.getAttribute("data-exams-remove-discount"));
            if (Number.isFinite(discountIndex) && discountIndex >= 0) {
                state.ownerDraft.discountCodes.splice(discountIndex, 1);
                renderCourse({ preserveUi: true });
            }
            return;
        }

        var toggleButton = event.target.closest("[data-filter-toggle]");
        if (toggleButton) {
            state.filtersOpen = !state.filtersOpen;
            scheduleCourseRender();
            return;
        }

        var filterButton = event.target.closest("[data-session-filter]");
        if (!filterButton) {
            return;
        }
        state.filter = String(filterButton.getAttribute("data-session-filter") || "all");
        if (window.innerWidth <= 640) {
            state.filtersOpen = false;
        }
        scheduleCourseRender();
    });

    root.addEventListener("input", function (event) {
        var target = event.target;
        if (target && target.closest("#exams-owner-form")) {
            syncOwnerDraftFromForm(target.closest("#exams-owner-form"));
        }
        if (!target || target.id !== "exams-session-search") {
            return;
        }
        state.query = String(target.value || "");
        scheduleCourseRender();
    });

    root.addEventListener("change", function (event) {
        var target = event.target;
        if (target && target.closest("#exams-owner-form")) {
            syncOwnerDraftFromForm(target.closest("#exams-owner-form"));
        }
    });

    root.addEventListener("submit", function (event) {
        if (!event.target || event.target.id !== "exams-owner-form") {
            return;
        }
        event.preventDefault();

        if (!state.course || state.saving) {
            return;
        }

        var form = event.target;
        var draft = syncOwnerDraftFromForm(form) || buildOwnerDraft(state.course);
        var amountValue = String(draft.amount || "").replace(/[^\d]/g, "");
        state.saving = true;
        state.feedback = "";
        state.feedbackKind = "";
        renderCourse({ preserveUi: true });

        apiPost("ownerSaveCourseAccess", {
            course: courseSlug,
            paymentMode: draft.paymentMode || "free",
            amount: amountValue,
            discountCodes: JSON.stringify(Array.isArray(draft.discountCodes) ? draft.discountCodes : [])
        }).then(function (payload) {
            if (!payload || !payload.success || !payload.course) {
                throw new Error((payload && payload.error) || "ذخیره تنظیمات انجام نشد.");
            }
            state.course = payload.course;
            state.ownerDraft = null;
            state.feedback = payload.message || "تنظیمات ذخیره شد.";
            state.feedbackKind = "success";
        }).catch(function (error) {
            state.feedback = error && error.message ? error.message : "ذخیره تنظیمات انجام نشد.";
            state.feedbackKind = "error";
        }).finally(function () {
            state.saving = false;
            renderCourse({ preserveUi: true });
        });
    });

    if (window.Dent1402Auth && typeof window.Dent1402Auth.onChange === "function") {
        window.Dent1402Auth.onChange(function (detail) {
            if (!detail || detail.status === "session-restoring" || detail.status === "logging-out") {
                setLoading();
                return;
            }
            load();
        });
    } else {
        load();
    }
})();
