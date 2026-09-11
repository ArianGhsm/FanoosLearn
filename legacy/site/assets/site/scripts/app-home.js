(function () {
    "use strict";

    if (!window.Dent1402Auth) {
        return;
    }

    function $(id) {
        return document.getElementById(id);
    }

    var panel = $("home-identity-panel");
    if (!panel) {
        return;
    }

    var status = $("home-identity-status");
    var title = $("home-identity-title");
    var desc = $("home-identity-desc");
    var meta = $("home-identity-meta");
    var primaryAction = $("home-identity-primary");
    var secondaryAction = $("home-identity-secondary");
    var ownerBadge = $("home-owner-badge");
    var activeExamsPanel = $("home-active-exams");
    var activeExamsList = $("home-active-exams-list");
    var activeExamsCount = activeExamsPanel ? activeExamsPanel.querySelector(".home-active-exams__count") : null;

    var navidPanel = $("home-navid-panel");
    var navidStateText = $("home-navid-state");
    var navidSyncText = $("home-navid-sync");
    var navidUpdates = $("home-navid-updates");
    var navidAssignments = $("home-navid-assignments");
    var authApi = window.Dent1402Auth && typeof window.Dent1402Auth === "object"
        ? window.Dent1402Auth
        : null;

    var navidLoadedFor = "";
    var navidLoadToken = 0;
    var navidLoading = false;
    var activeExamsState = {
        cohortKey: "",
        loading: false,
        requestToken: 0,
        courses: [],
        expiryTimer: 0
    };
    var appHeaderTitle = document.querySelector(".site-header .site-info h1");
    var appFooterTitle = document.querySelector(".site-footer p");
    var homeKicker = document.querySelector(".home-kicker");
    var homeTitle = $("app-home-title");
    var homeServicesTitle = $("home-services-title");
    var quickActionsRoot = document.querySelector(".home-quick-actions");
    var quickActions = Array.prototype.slice.call(document.querySelectorAll("[data-home-quick]"));
    var fallbackPrimaryCohort = {
        key: "dentistry-1402",
        title: "دندانپزشکی ۱۴۰۲",
        shortTitle: "دندان ۱۴۰۲",
        productType: "dentistry",
        notesMode: "terms",
        services: {
            notes: true,
            forms: true,
            grades: true,
            navid: true,
            buy: false,
            activeExamHighlights: true
        },
        routes: {
            notes: "/notes/",
            forms: "/forms/",
            grades: "/grades/",
            navid: "/navid/",
            buy: ""
        }
    };

    function setupHomeScrollState() {
        var frameId = 0;

        function renderScrollState() {
            frameId = 0;
            document.body.classList.toggle("is-home-scrolled", window.scrollY > 8);
        }

        function requestScrollState() {
            if (frameId) {
                return;
            }
            frameId = window.requestAnimationFrame(renderScrollState);
        }

        renderScrollState();
        window.addEventListener("scroll", requestScrollState, { passive: true });
    }

    function applyBranding(isProsthesis) {
        var brand = isProsthesis ? "ورودی ۱۴۰۲ پروتز تهران" : "ورودی ۱۴۰۲ دندانپزشکی تهران";
        if (appHeaderTitle) {
            appHeaderTitle.textContent = brand;
        }
        if (appFooterTitle) {
            appFooterTitle.textContent = brand;
        }
        if (homeKicker) {
            homeKicker.innerHTML = '<i aria-hidden="true"></i> ' + (isProsthesis
                ? "پروتز ۱۴۰۲"
                : "ورودی ۱۴۰۲");
        }
        if (homeTitle) {
            homeTitle.textContent = "خانه";
        }
        if (homeServicesTitle) {
            homeServicesTitle.textContent = isProsthesis ? "دسترسی‌های پروتز" : "دسترسی‌ها";
        }
        if (document.title) {
            document.title = isProsthesis
                ? "خانه دانشجو | ورودی ۱۴۰۲ پروتز"
                : "خانه دانشجو | ورودی ۱۴۰۲ دندانپزشکی";
        }
    }

    function consumeUnauthorized(response, fallbackText) {
        var auth = window.Dent1402Auth && typeof window.Dent1402Auth === "object"
            ? window.Dent1402Auth
            : null;
        if (!auth) {
            return false;
        }

        var message = fallbackText || "\u0646\u0634\u0633\u062a \u0634\u0645\u0627 \u0645\u0646\u0642\u0636\u06cc \u0634\u062f.";
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

    function snippet(value, maxLength) {
        var clean = String(value || "").replace(/\s+/g, " ").trim();
        if (!clean) {
            return "";
        }
        if (clean.length <= maxLength) {
            return clean;
        }
        return clean.slice(0, maxLength - 1).trim() + "\u2026";
    }

    function formatDate(value, fallback) {
        var raw = String(value || "").trim();
        if (!raw) {
            return fallback || "\u2014";
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

    function cohortServices(cohort) {
        var services = cohort && typeof cohort === "object" && cohort.services && typeof cohort.services === "object"
            ? cohort.services
            : {};
        return {
            notes: !!services.notes,
            forms: !!services.forms,
            grades: !!services.grades,
            navid: !!services.navid,
            buy: !!services.buy,
            activeExamHighlights: !!services.activeExamHighlights
        };
    }

    function cohortRoutes(cohort) {
        var key = String(cohort && cohort.key ? cohort.key : "");
        var services = cohortServices(cohort);
        var routes = cohort && typeof cohort === "object" && cohort.routes && typeof cohort.routes === "object"
            ? cohort.routes
            : {};
        var scoped = function (basePath) {
            if (!key || key === "dentistry-1402") {
                return basePath;
            }
            return basePath + "?cohort=" + encodeURIComponent(key);
        };
        var notesFallback = "";
        if (services.notes) {
            if (!key || key === "dentistry-1402") {
                notesFallback = "/notes/";
            } else if (key === "dentistry-1403") {
                notesFallback = "/notes/1403/";
            } else if (key === "dentistry-1404") {
                notesFallback = "/notes/1404/";
            } else {
                notesFallback = "/notes/?cohort=" + encodeURIComponent(key);
            }
        }
        return {
            notes: String(routes.notes || notesFallback || ""),
            forms: services.forms ? String(routes.forms || scoped("/forms/")) : "",
            grades: services.grades ? String(routes.grades || scoped("/grades/")) : "",
            navid: services.navid ? String(routes.navid || "/navid/") : "",
            buy: services.buy ? String(routes.buy || "/buy/") : ""
        };
    }

    function availableHomeCohorts(detail) {
        var cohorts = Array.isArray(detail && detail.availableCohorts) ? detail.availableCohorts.slice() : [];
        if (!cohorts.length && detail && detail.user && detail.user.cohort && typeof detail.user.cohort === "object") {
            cohorts = [detail.user.cohort];
        }
        if (!cohorts.length) {
            cohorts = [fallbackPrimaryCohort];
        }
        return cohorts;
    }

    function activeHomeCohort(detail) {
        var currentKey = String(detail && detail.user && detail.user.cohortKey ? detail.user.cohortKey : "");
        var cohorts = availableHomeCohorts(detail);
        var matched = cohorts.find(function (cohort) {
            return String(cohort && cohort.key ? cohort.key : "") === currentKey;
        });
        return matched || cohorts[0] || fallbackPrimaryCohort;
    }

    function updateQuickActions(detail) {
        var activeCohort = activeHomeCohort(detail);
        var services = cohortServices(activeCohort);
        var routes = cohortRoutes(activeCohort);
        var targets = {
            notes: {
                enabled: services.notes && !!routes.notes,
                href: routes.notes
            },
            forms: {
                enabled: services.forms && !!routes.forms,
                href: routes.forms
            },
            navid: {
                enabled: services.navid && !!routes.navid,
                href: routes.navid
            },
            grades: {
                enabled: services.grades && !!routes.grades,
                href: routes.grades
            },
            chat: { enabled: true, href: "/chat/" },
            account: { enabled: true, href: "/account/" }
        };
        var visibleCount = 0;

        quickActions.forEach(function (link) {
            var key = String(link.getAttribute("data-home-quick") || "");
            var target = targets[key];
            var enabled = !!(target && target.enabled && target.href);
            link.hidden = !enabled;
            if (enabled) {
                link.href = target.href;
                visibleCount += 1;
            }
        });

        if (quickActionsRoot) {
            quickActionsRoot.setAttribute("data-visible-count", String(visibleCount));
        }
    }

    function hideActiveExams() {
        if (activeExamsState.expiryTimer) {
            window.clearTimeout(activeExamsState.expiryTimer);
            activeExamsState.expiryTimer = 0;
        }
        activeExamsState.requestToken += 1;
        activeExamsState.loading = false;
        if (activeExamsPanel) {
            activeExamsPanel.hidden = true;
        }
        if (activeExamsList) {
            activeExamsList.innerHTML = "";
        }
    }

    function activeExamMeta(course) {
        var addedAt = String(course && course.addedAt ? course.addedAt : "").trim();
        if (addedAt) {
            return "افزوده‌شده " + formatDate(addedAt, "");
        }
        var badge = String(course && course.badge ? course.badge : "").trim();
        if (badge) {
            return badge;
        }
        var examCount = Math.max(0, Number(course && course.stats && course.stats.examCount) || 0);
        return examCount > 0 ? examCount.toLocaleString("fa-IR") + " آزمون" : "آماده شروع";
    }

    function activeExamHasPassed(course) {
        var finalExam = course && course.curriculum && course.curriculum.finalExam;
        if (!finalExam) {
            return false;
        }
        var expiresAt = new Date(String(finalExam.expiresAt || ""));
        if (Number.isFinite(expiresAt.getTime())) {
            return Date.now() >= expiresAt.getTime();
        }
        return !!finalExam.hasPassed;
    }

    function scheduleActiveExamExpiry(courses) {
        if (activeExamsState.expiryTimer) {
            window.clearTimeout(activeExamsState.expiryTimer);
            activeExamsState.expiryTimer = 0;
        }

        var now = Date.now();
        var nextExpiry = (Array.isArray(courses) ? courses : []).reduce(function (nearest, course) {
            var finalExam = course && course.curriculum && course.curriculum.finalExam;
            var parsed = new Date(String(finalExam && finalExam.expiresAt ? finalExam.expiresAt : "")).getTime();
            if (!Number.isFinite(parsed) || parsed <= now) {
                return nearest;
            }
            return nearest === 0 || parsed < nearest ? parsed : nearest;
        }, 0);
        if (!nextExpiry) {
            return;
        }

        var maxDelay = 2147483647;
        var delay = Math.min(maxDelay, Math.max(250, nextExpiry - now + 250));
        activeExamsState.expiryTimer = window.setTimeout(function () {
            activeExamsState.expiryTimer = 0;
            renderActiveExams(activeExamsState.courses);
        }, delay);
    }

    function renderActiveExams(courses) {
        if (!activeExamsPanel || !activeExamsList) {
            return;
        }

        var candidateCourses = Array.isArray(courses) ? courses.slice(0, 2) : [];
        var visibleCourses = candidateCourses.filter(function (course) {
            return !activeExamHasPassed(course);
        });
        if (!visibleCourses.length) {
            hideActiveExams();
            return;
        }

        scheduleActiveExamExpiry(candidateCourses);

        activeExamsList.innerHTML = visibleCourses.map(function (course, index) {
            var titleText = String(course && (course.title || course.shortTitle) ? (course.title || course.shortTitle) : "آزمون تازه");
            var descriptionText = snippet(course && (course.cardDescription || course.heroDescription), 92) || "برای مشاهده و شروع آزمون وارد شو.";
            var href = String(course && course.path ? course.path : "/exams/");
            return [
                '<a class="home-active-exam" href="' + safeText(href) + '">',
                '  <span class="home-active-exam__index" aria-hidden="true">' + (index + 1).toLocaleString("fa-IR") + "</span>",
                '  <span class="home-active-exam__copy">',
                '    <span class="home-active-exam__meta">' + safeText(activeExamMeta(course)) + "</span>",
                '    <strong>' + safeText(titleText) + "</strong>",
                '    <small>' + safeText(descriptionText) + "</small>",
                "  </span>",
                '  <span class="home-active-exam__arrow" aria-hidden="true">←</span>',
                "</a>"
            ].join("");
        }).join("");

        if (activeExamsCount) {
            activeExamsCount.textContent = visibleCourses.length.toLocaleString("fa-IR");
            activeExamsCount.setAttribute("aria-label", visibleCourses.length.toLocaleString("fa-IR") + " آزمون فعال");
        }
        activeExamsPanel.hidden = false;
    }

    async function loadActiveExams(cohort) {
        var cohortKey = String(cohort && cohort.key ? cohort.key : "").trim();
        var supportsActiveExamHighlights = cohortServices(cohort).activeExamHighlights || cohortKey === "site-users";
        if (!cohortKey || !supportsActiveExamHighlights) {
            activeExamsState.cohortKey = "";
            activeExamsState.courses = [];
            hideActiveExams();
            return;
        }

        if (activeExamsState.cohortKey === cohortKey && activeExamsState.courses.length) {
            renderActiveExams(activeExamsState.courses);
            return;
        }
        if (activeExamsState.loading && activeExamsState.cohortKey === cohortKey) {
            return;
        }

        activeExamsState.cohortKey = cohortKey;
        activeExamsState.loading = true;
        var ticket = ++activeExamsState.requestToken;

        try {
            var response = await fetch("/api/exams_home_highlights_api.php?cohort=" + encodeURIComponent(cohortKey), {
                method: "GET",
                credentials: "same-origin",
                headers: {
                    Accept: "application/json"
                },
                cache: "no-store"
            });
            var payload = await response.json().catch(function () {
                return null;
            });
            if (ticket !== activeExamsState.requestToken) {
                return;
            }
            if (!response.ok || !payload || !payload.success) {
                activeExamsState.courses = [];
                hideActiveExams();
                return;
            }

            var catalogCourses = Array.isArray(payload.courses) ? payload.courses : [];
            var latestCourses = catalogCourses
                .filter(function (course) {
                    return course && course.path && Number(course.stats && course.stats.examCount) > 0;
                }).slice(0, 2)
                ;
            activeExamsState.courses = latestCourses;
            renderActiveExams(latestCourses);
        } catch (_error) {
            if (ticket === activeExamsState.requestToken) {
                activeExamsState.courses = [];
                hideActiveExams();
            }
        } finally {
            if (ticket === activeExamsState.requestToken) {
                activeExamsState.loading = false;
            }
        }
    }

    function updateHomeAccess(detail) {
        updateQuickActions(detail || null);
    }

    function networkErrorResponse() {
        return {
            success: false,
            error: "ارتباط با سرور برقرار نشد. اتصال اینترنت خود را بررسی کنید.",
            httpStatus: 0
        };
    }

    function navidResultLabel(result) {
        switch (result) {
            case "ok":
                return "\u067e\u0627\u06cc\u062f\u0627\u0631";
            case "running":
                return "\u062f\u0631 \u062d\u0627\u0644 \u0627\u062c\u0631\u0627";
            case "config-updated":
                return "\u062a\u0646\u0638\u06cc\u0645\u0627\u062a \u0628\u0647\u200c\u0631\u0648\u0632 \u0634\u062f";
            case "credentials-missing":
                return "\u0627\u0639\u062a\u0628\u0627\u0631 \u062b\u0628\u062a \u0646\u0634\u062f\u0647";
            case "credentials-invalid":
                return "\u0627\u0639\u062a\u0628\u0627\u0631 \u0646\u0627\u0645\u0639\u062a\u0628\u0631";
            case "reconnect-required":
                return "\u0646\u06cc\u0627\u0632\u0645\u0646\u062f \u0627\u062a\u0635\u0627\u0644 \u0645\u062c\u062f\u062f";
            case "login-failed":
                return "\u0646\u06cc\u0627\u0632\u0645\u0646\u062f \u0627\u062a\u0635\u0627\u0644 \u0645\u062c\u062f\u062f";
            case "dashboard-failed":
                return "\u062e\u0637\u0627\u06cc \u062f\u0627\u0634\u0628\u0648\u0631\u062f";
            case "exception":
                return "\u062e\u0637\u0627\u06cc \u062f\u0627\u062e\u0644\u06cc";
            case "lock-failed":
                return "\u062e\u0637\u0627\u06cc \u0642\u0641\u0644 \u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc";
            case "skipped":
                return "\u0628\u062f\u0648\u0646 \u0646\u06cc\u0627\u0632 \u0628\u0647 \u0628\u0631\u0631\u0633\u06cc \u062c\u062f\u06cc\u062f";
            case "already-running":
                return "\u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc \u062f\u0631 \u062d\u0627\u0644 \u0627\u0646\u062c\u0627\u0645";
            case "disabled":
                return "\u063a\u06cc\u0631\u0641\u0639\u0627\u0644";
            default:
                return "\u0646\u0627\u0645\u0634\u062e\u0635";
        }
    }

    function setIdentityLoggedOut(errorText) {
        applyBranding(false);
        document.querySelectorAll("[data-owner-only]").forEach(function (node) {
            node.hidden = true;
        });
        updateHomeAccess(null);
        panel.dataset.authState = errorText ? "unauthorized" : "logged-out";
        status.textContent = "\u0648\u0631\u0648\u062f \u0644\u0627\u0632\u0645 \u0627\u0633\u062a";
        title.textContent = "\u062d\u0633\u0627\u0628 \u0633\u0631\u0627\u0633\u0631\u06cc\u200c\u0627\u062a \u0631\u0627 \u0641\u0639\u0627\u0644 \u06a9\u0646.";
        desc.textContent = "\u0628\u0627 \u0647\u0645\u0627\u0646 \u0634\u0645\u0627\u0631\u0647 \u062f\u0627\u0646\u0634\u062c\u0648\u06cc\u06cc \u0648 \u0631\u0645\u0632 \u0627\u0632 \u067e\u06cc\u0634\u200c\u062a\u0639\u0631\u06cc\u0641\u200c\u0634\u062f\u0647 \u0648\u0627\u0631\u062f \u0634\u0648 \u062a\u0627 \u0646\u0645\u0631\u0627\u062a\u060c \u0686\u062a \u0648 \u062d\u0633\u0627\u0628 \u06a9\u0627\u0631\u0628\u0631\u06cc\u200c\u0627\u062a \u062f\u0631 \u06a9\u0644 \u0633\u0627\u06cc\u062a \u06cc\u06a9\u067e\u0627\u0631\u0686\u0647 \u0634\u0648\u0646\u062f.";
        meta.textContent = errorText || "\u0646\u0634\u0633\u062a \u062d\u0633\u0627\u0628 \u0631\u0648\u06cc \u0647\u0645\u06cc\u0646 \u062f\u0633\u062a\u06af\u0627\u0647 \u0646\u06af\u0647 \u062f\u0627\u0634\u062a\u0647 \u0645\u06cc\u200c\u0634\u0648\u062f.";
        ownerBadge.hidden = true;
        primaryAction.textContent = "\u0648\u0631\u0648\u062f \u0628\u0647 \u062d\u0633\u0627\u0628";
        primaryAction.href = window.Dent1402Auth.loginUrl("/app/");
        secondaryAction.textContent = "\u0645\u062f\u06cc\u0631\u06cc\u062a \u062d\u0633\u0627\u0628";
        secondaryAction.href = "/account/";
    }

    function setIdentityBoot(message) {
        applyBranding(false);
        document.querySelectorAll("[data-owner-only]").forEach(function (node) {
            node.hidden = true;
        });
        updateHomeAccess(authApi && typeof authApi.getState === "function" ? authApi.getState() : null);
        panel.dataset.authState = "session-restoring";
        status.textContent = "\u062f\u0631 \u062d\u0627\u0644 \u0628\u0627\u0632\u06cc\u0627\u0628\u06cc";
        title.textContent = "\u0646\u0634\u0633\u062a \u062d\u0633\u0627\u0628 \u062f\u0631 \u062d\u0627\u0644 \u0622\u0645\u0627\u062f\u0647\u200c\u0633\u0627\u0632\u06cc \u0627\u0633\u062a.";
        desc.textContent = message || "\u0627\u06af\u0631 \u0642\u0628\u0644\u0627\u064b \u0648\u0627\u0631\u062f \u0634\u062f\u0647 \u0628\u0627\u0634\u06cc\u060c \u0647\u0648\u06cc\u062a\u062a \u0631\u0648\u06cc \u0647\u0645\u06cc\u0646 \u062f\u0633\u062a\u06af\u0627\u0647 \u0628\u0631\u0645\u06cc\u200c\u06af\u0631\u062f\u062f.";
        meta.textContent = "\u0686\u0646\u062f \u0644\u062d\u0638\u0647 \u0635\u0628\u0631 \u06a9\u0646.";
        ownerBadge.hidden = true;
        primaryAction.textContent = "\u062f\u0631 \u062d\u0627\u0644 \u0628\u0631\u0631\u0633\u06cc...";
        primaryAction.href = "/account/";
        secondaryAction.textContent = "\u062d\u0633\u0627\u0628 \u06a9\u0627\u0631\u0628\u0631\u06cc";
        secondaryAction.href = "/account/";
    }

    function setIdentityLoggedIn(user) {
        var isOwner = !!(user && user.isOwner);
        var isProsthesis = !!(user && user.isProsthesisStudent);
        var isExternalExamUser = !!(user && user.isExternalExamUser);
        var currentCohort = user && user.cohort ? user.cohort : fallbackPrimaryCohort;
        var currentServices = cohortServices(currentCohort);
        var currentRoutes = cohortRoutes(currentCohort);
        applyBranding(isProsthesis);
        document.querySelectorAll("[data-owner-only]").forEach(function (node) {
            node.hidden = !isOwner;
        });
        updateHomeAccess(authApi && typeof authApi.getState === "function" ? authApi.getState() : { loggedIn: true, user: user, availableCohorts: [user.cohort || fallbackPrimaryCohort] });
        panel.dataset.authState = "logged-in";
        status.textContent = isOwner ? "\u0645\u0627\u0644\u06a9 \u0633\u0627\u0645\u0627\u0646\u0647" : (user.roleLabel || "\u062d\u0633\u0627\u0628 \u0641\u0639\u0627\u0644");
        title.textContent = (user.name || "\u062f\u0627\u0646\u0634\u062c\u0648") + "\u060c \u062e\u0648\u0634 \u0628\u0631\u06af\u0634\u062a\u06cc.";
        desc.textContent = isOwner
            ? "\u062f\u0633\u062a\u0631\u0633\u06cc \u0645\u062f\u06cc\u0631\u06cc\u062a\u06cc \u0641\u0639\u0627\u0644 \u0627\u0633\u062a \u0648 \u0627\u0632 \u0647\u0645\u06cc\u0646\u200c\u062c\u0627 \u0645\u06cc\u200c\u062a\u0648\u0627\u0646\u06cc \u0686\u062a\u060c \u0646\u0645\u0627\u06cc\u0646\u062f\u0647\u200c\u0647\u0627 \u0648 \u062d\u0633\u0627\u0628\u200c\u0647\u0627 \u0631\u0627 \u0645\u062f\u06cc\u0631\u06cc\u062a \u06a9\u0646\u06cc."
            : (isExternalExamUser
                ? "\u0627\u06cc\u0646 \u062d\u0633\u0627\u0628 \u0628\u0631\u0627\u06cc \u062e\u0631\u06cc\u062f\u060c \u0622\u0632\u0645\u0648\u0646 \u0648 \u067e\u06cc\u06af\u06cc\u0631\u06cc \u0633\u0641\u0627\u0631\u0634\u200c\u0647\u0627\u06cc \u0633\u0627\u06cc\u062a \u0641\u0639\u0627\u0644 \u0627\u0633\u062a."
                : (isProsthesis
                ? "\u0647\u0648\u06cc\u062a\u062a \u062f\u0631 \u0686\u062a\u060c \u0641\u0631\u0645\u200c\u0647\u0627\u060c \u0646\u0645\u0631\u0627\u062a \u0648 \u0645\u0646\u0627\u0628\u0639 \u067e\u0631\u0648\u062a\u0632 \u0628\u0647\u200c\u0635\u0648\u0631\u062a \u062c\u062f\u0627 \u0646\u06af\u0647\u200c\u062f\u0627\u0631\u06cc \u0645\u06cc\u200c\u0634\u0648\u062f."
                : "\u0647\u0648\u06cc\u062a\u062a \u062f\u0631 \u0686\u062a\u060c \u0646\u0645\u0631\u0627\u062a \u0648 \u062d\u0633\u0627\u0628 \u06a9\u0627\u0631\u0628\u0631\u06cc \u0647\u0645\u06af\u0627\u0645 \u0627\u0633\u062a \u0648 \u0644\u0627\u0632\u0645 \u0646\u06cc\u0633\u062a \u0647\u0631 \u0635\u0641\u062d\u0647 \u062c\u062f\u0627\u06af\u0627\u0646\u0647 \u0648\u0627\u0631\u062f \u0634\u0648\u06cc."));
        meta.textContent = "\u0634\u0645\u0627\u0631\u0647 \u062f\u0627\u0646\u0634\u062c\u0648\u06cc\u06cc: " + (user.studentNumber || "-");
        ownerBadge.hidden = !isOwner;
        primaryAction.textContent = isOwner ? "\u067e\u0646\u0644 \u062d\u0633\u0627\u0628 \u0648 \u0645\u062f\u06cc\u0631\u06cc\u062a" : "\u062d\u0633\u0627\u0628 \u06a9\u0627\u0631\u0628\u0631\u06cc";
        primaryAction.href = "/account/";
        if (currentServices.grades && currentRoutes.grades) {
            secondaryAction.textContent = isProsthesis ? "\u0646\u0645\u0631\u0627\u062a \u067e\u0631\u0648\u062a\u0632" : "\u0646\u0645\u0631\u0627\u062a \u0645\u0646";
            secondaryAction.href = currentRoutes.grades;
        } else if (currentServices.buy && currentRoutes.buy) {
            secondaryAction.textContent = "\u062e\u0631\u06cc\u062f \u0648 \u062b\u0628\u062a\u200c\u0646\u0627\u0645";
            secondaryAction.href = currentRoutes.buy;
        } else {
            secondaryAction.textContent = "\u0645\u062f\u06cc\u0631\u06cc\u062a \u062d\u0633\u0627\u0628";
            secondaryAction.href = "/account/";
        }
    }

    function navidSetState(state) {
        if (!navidPanel) {
            return;
        }
        navidPanel.dataset.state = state;
    }

    function navidRenderEmpty(container, message) {
        if (!container) {
            return;
        }
        container.innerHTML = '<div class="portal-navid-empty">' + safeText(message) + "</div>";
    }

    function navidSetBoot(message) {
        if (!navidPanel) {
            return;
        }
        navidSetState("restoring");
        if (navidStateText) {
            navidStateText.textContent = message || "\u062f\u0631 \u062d\u0627\u0644 \u062f\u0631\u06cc\u0627\u0641\u062a \u0648\u0636\u0639\u06cc\u062a \u0646\u0648\u06cc\u062f...";
        }
        if (navidSyncText) {
            navidSyncText.textContent = "\u0622\u062e\u0631\u06cc\u0646 \u0628\u0631\u0631\u0633\u06cc: \u2014";
        }
        navidRenderEmpty(navidUpdates, "\u062f\u0631 \u062d\u0627\u0644 \u0628\u0627\u0631\u06af\u06cc\u0631\u06cc \u0628\u0647\u200c\u0631\u0648\u0632\u0631\u0633\u0627\u0646\u06cc\u200c\u0647\u0627...");
        navidRenderEmpty(navidAssignments, "\u062f\u0631 \u062d\u0627\u0644 \u0628\u0627\u0631\u06af\u06cc\u0631\u06cc \u062a\u06a9\u0627\u0644\u06cc\u0641 \u0641\u0639\u0644\u06cc...");
    }

    function navidSetSignedOut(errorText) {
        if (!navidPanel) {
            return;
        }
        navidSetState(errorText ? "unauthorized" : "signed-out");
        if (navidStateText) {
            navidStateText.textContent = errorText || "\u0628\u0631\u0627\u06cc \u062f\u06cc\u062f\u0646 \u062a\u06a9\u0627\u0644\u06cc\u0641 \u0646\u0648\u06cc\u062f\u060c \u0627\u0648\u0644 \u0648\u0627\u0631\u062f \u062d\u0633\u0627\u0628 \u0634\u0648.";
        }
        if (navidSyncText) {
            navidSyncText.textContent = "\u0622\u062e\u0631\u06cc\u0646 \u0628\u0631\u0631\u0633\u06cc: \u2014";
        }
        navidRenderEmpty(navidUpdates, "\u0628\u062f\u0648\u0646 \u0648\u0631\u0648\u062f\u060c \u0628\u0647\u200c\u0631\u0648\u0632\u0631\u0633\u0627\u0646\u06cc \u0646\u0648\u06cc\u062f \u0646\u0645\u0627\u06cc\u0634 \u062f\u0627\u062f\u0647 \u0646\u0645\u06cc\u200c\u0634\u0648\u062f.");
        navidRenderEmpty(navidAssignments, "\u0628\u0639\u062f \u0627\u0632 \u0648\u0631\u0648\u062f\u060c \u0644\u06cc\u0633\u062a \u062a\u06a9\u0627\u0644\u06cc\u0641 \u0641\u0639\u0644\u06cc \u0627\u06cc\u0646\u062c\u0627 \u0646\u0645\u0627\u06cc\u0634 \u062f\u0627\u062f\u0647 \u0645\u06cc\u200c\u0634\u0648\u062f.");
    }

    function navidSetError(message) {
        if (!navidPanel) {
            return;
        }
        navidSetState("error");
        if (navidStateText) {
            navidStateText.textContent = message || "\u062f\u0631\u06cc\u0627\u0641\u062a \u062f\u0627\u062f\u0647 \u0646\u0648\u06cc\u062f \u0628\u0627 \u062e\u0637\u0627 \u0631\u0648\u0628\u0647\u200c\u0631\u0648 \u0634\u062f.";
        }
        if (navidSyncText) {
            navidSyncText.textContent = "\u0622\u062e\u0631\u06cc\u0646 \u0628\u0631\u0631\u0633\u06cc: \u2014";
        }
        navidRenderEmpty(navidUpdates, "\u0644\u06cc\u0633\u062a \u0628\u0647\u200c\u0631\u0648\u0632\u0631\u0633\u0627\u0646\u06cc\u200c\u0647\u0627 \u0641\u0639\u0644\u0627\u064b \u062f\u0631 \u062f\u0633\u062a\u0631\u0633 \u0646\u06cc\u0633\u062a.");
        navidRenderEmpty(navidAssignments, "\u0644\u06cc\u0633\u062a \u062a\u06a9\u0627\u0644\u06cc\u0641 \u0641\u0639\u0644\u06cc \u0641\u0639\u0644\u0627\u064b \u062f\u0631 \u062f\u0633\u062a\u0631\u0633 \u0646\u06cc\u0633\u062a.");
    }

    function navidFetchFeed() {
        return fetch("/api/navid_api.php?action=feed", {
            method: "GET",
            credentials: "same-origin",
            headers: {
                Accept: "application/json"
            }
        }).then(function (response) {
            return response.json().catch(function () {
                return {
                    success: false,
                    error: "\u067e\u0627\u0633\u062e \u0646\u0627\u0645\u0639\u062a\u0628\u0631 \u0627\u0632 \u0633\u0631\u0648\u0631 \u062f\u0631\u06cc\u0627\u0641\u062a \u0634\u062f."
                };
            }).then(function (data) {
                data.httpStatus = response.status;
                return data;
            });
        }).catch(networkErrorResponse);
    }

    function navidRenderUpdates(items) {
        if (!navidUpdates) {
            return;
        }

        var updates = Array.isArray(items) ? items.slice(0, 3) : [];
        if (!updates.length) {
            navidRenderEmpty(navidUpdates, "\u0628\u0647\u200c\u0631\u0648\u0632\u0631\u0633\u0627\u0646\u06cc \u062a\u0627\u0632\u0647\u200c\u0627\u06cc \u0628\u0631\u0627\u06cc \u0646\u0648\u06cc\u062f \u062b\u0628\u062a \u0646\u0634\u062f\u0647 \u0627\u0633\u062a.");
            return;
        }

        navidUpdates.innerHTML = updates.map(function (item) {
            var eventType = String(item.eventType || "").toLowerCase() === "updated"
                ? "\u0648\u06cc\u0631\u0627\u06cc\u0634 \u062a\u06a9\u0644\u06cc\u0641"
                : "\u062a\u06a9\u0644\u06cc\u0641 \u062c\u062f\u06cc\u062f";
            var titleText = snippet(item.title, 120) || "\u0628\u062f\u0648\u0646 \u0639\u0646\u0648\u0627\u0646";
            var course = snippet(item.courseTitle, 80) || "\u062f\u0631\u0633 \u0646\u0627\u0645\u0634\u062e\u0635";
            var detectedAt = formatDate(item.detectedAt, "\u0632\u0645\u0627\u0646 \u062a\u0634\u062e\u06cc\u0635 \u0646\u0627\u0645\u0634\u062e\u0635");
            return [
                '<article class="portal-navid-item">',
                '  <h4 class="portal-navid-item__title">' + safeText(eventType + ": " + titleText) + "</h4>",
                '  <p class="portal-navid-item__meta">' + safeText(course + " • " + detectedAt) + "</p>",
                "</article>"
            ].join("");
        }).join("");
    }

    function navidRenderAssignments(items) {
        if (!navidAssignments) {
            return;
        }

        var assignments = Array.isArray(items) ? items.slice(0, 6) : [];
        if (!assignments.length) {
            navidRenderEmpty(navidAssignments, "\u062a\u06a9\u0644\u06cc\u0641 \u0641\u0639\u0627\u0644\u06cc \u062f\u0631 \u0627\u06cc\u0646 \u0644\u062d\u0638\u0647 \u067e\u06cc\u062f\u0627 \u0646\u0634\u062f.");
            return;
        }

        navidAssignments.innerHTML = assignments.map(function (item) {
            var titleText = snippet(item.title, 120) || "\u0628\u062f\u0648\u0646 \u0639\u0646\u0648\u0627\u0646";
            var course = snippet(item.courseTitle, 80) || "\u062f\u0631\u0633 \u0646\u0627\u0645\u0634\u062e\u0635";
            var deadline = item.endDateShamsi || formatDate(item.endDateIso, "\u0645\u0647\u0644\u062a \u0646\u0627\u0645\u0634\u062e\u0635");
            var descText = snippet(item.descriptionText, 180) || "\u0628\u0631\u0627\u06cc \u0627\u06cc\u0646 \u062a\u06a9\u0644\u06cc\u0641 \u062a\u0648\u0636\u06cc\u062d\u06cc \u062b\u0628\u062a \u0646\u0634\u062f\u0647 \u0627\u0633\u062a.";
            var filesCount = Array.isArray(item.files) ? item.files.length : 0;
            return [
                '<article class="portal-navid-item">',
                '  <h4 class="portal-navid-item__title">' + safeText(titleText) + "</h4>",
                '  <p class="portal-navid-item__meta">' + safeText(course + " • مهلت: " + deadline + " • فایل: " + filesCount) + "</p>",
                '  <p class="portal-navid-item__desc">' + safeText(descText) + "</p>",
                "</article>"
            ].join("");
        }).join("");
    }

    function navidRenderPayload(payload, currentUser) {
        var data = payload && payload.data ? payload.data : null;
        if (!data || typeof data !== "object") {
            navidSetError("\u062f\u0627\u062f\u0647 \u0646\u0648\u06cc\u062f \u0642\u0627\u0628\u0644 \u062e\u0648\u0627\u0646\u062f\u0646 \u0646\u06cc\u0633\u062a.");
            return;
        }

        var publicStatus = data.publicStatus || {};
        if (!publicStatus.enabled) {
            navidSetState("disabled");
            if (navidStateText) {
                navidStateText.textContent = "\u06cc\u06a9\u067e\u0627\u0631\u0686\u0647\u200c\u0633\u0627\u0632\u06cc \u0646\u0648\u06cc\u062f \u062f\u0631 \u062d\u0627\u0644 \u062d\u0627\u0636\u0631 \u063a\u06cc\u0631\u0641\u0639\u0627\u0644 \u0627\u0633\u062a.";
            }
            if (navidSyncText) {
                navidSyncText.textContent = "\u0622\u062e\u0631\u06cc\u0646 \u0628\u0631\u0631\u0633\u06cc: \u2014";
            }
            navidRenderEmpty(navidUpdates, "\u067e\u0633 \u0627\u0632 \u0641\u0639\u0627\u0644\u200c\u0633\u0627\u0632\u06cc \u0627\u0632 \u067e\u0646\u0644 \u062d\u0633\u0627\u0628\u060c \u0628\u0647\u200c\u0631\u0648\u0632\u0631\u0633\u0627\u0646\u06cc\u200c\u0647\u0627 \u0627\u06cc\u0646\u062c\u0627 \u0646\u0645\u0627\u06cc\u0634 \u062f\u0627\u062f\u0647 \u0645\u06cc\u200c\u0634\u0648\u0646\u062f.");
            navidRenderEmpty(navidAssignments, "\u0628\u0639\u062f \u0627\u0632 \u0641\u0639\u0627\u0644\u200c\u0633\u0627\u0632\u06cc \u0646\u0648\u06cc\u062f\u060c \u062a\u06a9\u0627\u0644\u06cc\u0641 \u0641\u0639\u0644\u06cc \u0627\u06cc\u0646\u062c\u0627 \u0642\u0631\u0627\u0631 \u0645\u06cc\u200c\u06af\u06cc\u0631\u062f.");
            return;
        }

        var actionRequired = String(publicStatus.actionRequired || "");
        var needsCredentials = actionRequired === "save-credentials" || !!publicStatus.credentialsMissing;
        var invalidCredentials = actionRequired === "update-credentials" || !!publicStatus.credentialsInvalid;
        var needsReconnect = actionRequired === "manual-reconnect" || !!publicStatus.requiresReconnect;
        var lastErrorText = snippet(publicStatus.lastError, 160);
        var isOwner = !!(currentUser && currentUser.isOwner);

        if (needsCredentials) {
            navidSetState("needs-credentials");
        } else if (invalidCredentials) {
            navidSetState("credentials-invalid");
        } else if (needsReconnect) {
            navidSetState("reconnect");
        } else if (lastErrorText && String(publicStatus.lastResult || "").trim() !== "ok") {
            navidSetState("warning");
        } else {
            navidSetState("ready");
        }

        var statusLabel = navidResultLabel(publicStatus.lastResult || "");
        if (needsCredentials) {
            statusLabel += isOwner
                ? " - ثبت اعتبار لازم است"
                : " - منتظر ثبت اعتبار مدیر";
        } else if (invalidCredentials) {
            statusLabel += isOwner
                ? " - اعتبار ذخیره‌شده نامعتبر است"
                : " - منتظر بروزرسانی اعتبار مدیر";
        } else if (needsReconnect) {
            statusLabel += isOwner
                ? " - نیاز به اتصال مجدد (از پنل حساب)"
                : " - در انتظار اتصال مجدد مدیر";
        }

        if (navidStateText) {
            navidStateText.textContent = "وضعیت: " + statusLabel;
        }
        if (navidSyncText) {
            navidSyncText.textContent = "آخرین بررسی: " + formatDate(publicStatus.lastSuccessAt || publicStatus.lastSyncAt, "—");
        }

        if (needsCredentials) {
            navidRenderEmpty(
                navidUpdates,
                isOwner
                    ? "اعتبار نوید را از پنل حساب ذخیره کن تا پایش تکالیف فعال شود."
                    : "مدیر هنوز اعتبار نوید را ثبت نکرده است."
            );
            navidRenderEmpty(
                navidAssignments,
                "تا زمان اتصال نوید، تکلیف فعالی برای نمایش در دسترس نیست."
            );
            return;
        }

        if (invalidCredentials) {
            navidRenderEmpty(
                navidUpdates,
                isOwner
                    ? "نام کاربری یا رمز نوید نیاز به بروزرسانی دارد."
                    : "اعتبار نوید توسط مدیر نیاز به بروزرسانی دارد."
            );
            navidRenderEmpty(
                navidAssignments,
                "بعد از بروزرسانی اعتبار، تکالیف فعال اینجا نمایش داده می‌شود."
            );
            return;
        }

        if (needsReconnect) {
            navidRenderEmpty(
                navidUpdates,
                isOwner
                    ? "اتصال نوید نیاز به کپچا و اتصال مجدد دارد."
                    : "اتصال نوید نیاز به اقدام مدیر دارد."
            );
            navidRenderEmpty(
                navidAssignments,
                "بعد از اتصال مجدد، لیست تکالیف دوباره به‌روزرسانی می‌شود."
            );
            return;
        }

        navidRenderUpdates(data.updates);
        navidRenderAssignments(data.currentAssignments);
    }

    async function loadNavidFeed(currentUser) {
        if (!navidPanel || navidLoading) {
            return;
        }

        navidLoading = true;
        var ticket = ++navidLoadToken;
        navidSetBoot("\u062f\u0631 \u062d\u0627\u0644 \u062f\u0631\u06cc\u0627\u0641\u062a \u062a\u06a9\u0627\u0644\u06cc\u0641 \u0646\u0648\u06cc\u062f...");

        try {
            var response = await navidFetchFeed();
            if (ticket !== navidLoadToken) {
                return;
            }

            if (consumeUnauthorized(response, "\u0646\u0634\u0633\u062a \u0634\u0645\u0627 \u0628\u0647 \u067e\u0627\u06cc\u0627\u0646 \u0631\u0633\u06cc\u062f.")) {
                navidLoadedFor = "";
                navidSetSignedOut("\u0646\u0634\u0633\u062a \u0634\u0645\u0627 \u0628\u0647 \u067e\u0627\u06cc\u0627\u0646 \u0631\u0633\u06cc\u062f.");
                return;
            }

            if (!response || !response.success) {
                navidSetError((response && response.error) || "\u062f\u0631\u06cc\u0627\u0641\u062a \u0627\u0637\u0644\u0627\u0639\u0627\u062a \u0646\u0648\u06cc\u062f \u0646\u0627\u0645\u0648\u0641\u0642 \u0628\u0648\u062f.");
                return;
            }

            navidRenderPayload(response, currentUser);
            navidLoadedFor = String(currentUser && currentUser.studentNumber ? currentUser.studentNumber : "_logged");
        } catch (error) {
            if (ticket !== navidLoadToken) {
                return;
            }
            navidSetError((error && error.message) || "\u062f\u0631\u06cc\u0627\u0641\u062a \u0627\u0637\u0644\u0627\u0639\u0627\u062a \u0646\u0648\u06cc\u062f \u0628\u0627 \u062e\u0637\u0627 \u0645\u062a\u0648\u0642\u0641 \u0634\u062f.");
        } finally {
            if (ticket === navidLoadToken) {
                navidLoading = false;
            }
        }
    }

    function sync(detail) {
        if (detail.status === "session-restoring" || detail.status === "logging-out") {
            hideActiveExams();
            setIdentityBoot(detail.status === "logging-out"
                ? "\u062f\u0631 \u062d\u0627\u0644 \u0628\u0633\u062a\u0646 \u0646\u0634\u0633\u062a \u0641\u0639\u0644\u06cc..."
                : "");
            navidSetBoot("\u062f\u0631 \u062d\u0627\u0644 \u0628\u0627\u0632\u06cc\u0627\u0628\u06cc \u0648\u0636\u0639\u06cc\u062a \u0646\u0648\u06cc\u062f...");
            return;
        }

        if (!detail.loggedIn || !detail.user) {
            setIdentityLoggedOut(detail.status === "unauthorized" ? detail.error : "");
            loadActiveExams(fallbackPrimaryCohort);
            navidLoadedFor = "";
            navidSetSignedOut(detail.status === "unauthorized" ? detail.error : "");
            return;
        }

        setIdentityLoggedIn(detail.user);
        loadActiveExams(activeHomeCohort(detail));
        var userKey = String(detail.user.studentNumber || "_logged");
        if (userKey !== navidLoadedFor) {
            loadNavidFeed(detail.user);
        }
    }

    setupHomeScrollState();
    window.Dent1402Auth.onChange(sync);
})();
