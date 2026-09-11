(function () {
    "use strict";

    var root = document.getElementById("exams-home-root");
    if (!root) {
        return;
    }

    var state = {
        loading: false,
        catalog: null,
        viewer: null,
        mode: "",
        termNumber: 0,
        unitKey: "",
        specialtyKey: "",
        referenceKey: ""
    };

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

    function currentParams() {
        return new URLSearchParams(window.location.search);
    }

    function readTermNumber(value) {
        var numeric = Number(String(value || "").trim() || "0");
        if (!Number.isFinite(numeric) || numeric <= 0) {
            return 0;
        }
        return Math.round(numeric);
    }

    function cleanUnitKey(value) {
        return String(value || "").trim().toLowerCase();
    }

    function cleanViewMode(value) {
        var clean = String(value || "").trim().toLowerCase();
        return clean === "term" || clean === "reference" ? clean : "";
    }

    function cleanReferenceKey(value) {
        return String(value || "")
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9_-]+/g, "");
    }

    function readViewFromLocation() {
        var params = currentParams();
        var termNumber = readTermNumber(params.get("term"));
        var unitKey = cleanUnitKey(params.get("unit"));
        var specialtyKey = cleanReferenceKey(params.get("specialty"));
        var referenceKey = cleanReferenceKey(params.get("ref"));
        var mode = cleanViewMode(params.get("mode"));

        if (termNumber > 0 || unitKey) {
            mode = "term";
        } else if ((specialtyKey || referenceKey) && mode !== "term") {
            mode = "reference";
        }

        return {
            mode: mode,
            termNumber: termNumber,
            unitKey: unitKey,
            specialtyKey: specialtyKey,
            referenceKey: referenceKey
        };
    }

    function applyLocationView() {
        var nextView = readViewFromLocation();
        state.mode = nextView.mode;
        state.termNumber = nextView.termNumber;
        state.unitKey = nextView.unitKey;
        state.specialtyKey = nextView.specialtyKey;
        state.referenceKey = nextView.referenceKey;
    }

    function buildViewSearch(view) {
        var nextView = view || {};
        var params = currentParams();
        var mode = cleanViewMode(nextView.mode);
        var termNumber = readTermNumber(nextView.termNumber);
        var unitKey = cleanUnitKey(nextView.unitKey);
        var specialtyKey = cleanReferenceKey(nextView.specialtyKey);
        var referenceKey = cleanReferenceKey(nextView.referenceKey);

        params.delete("mode");
        params.delete("term");
        params.delete("unit");
        params.delete("specialty");
        params.delete("ref");

        if (mode) {
            params.set("mode", mode);
        }
        if (mode === "term" && termNumber > 0) {
            params.set("term", String(termNumber));
            if (unitKey) {
                params.set("unit", unitKey);
            }
        }
        if (mode === "reference") {
            if (specialtyKey) {
                params.set("specialty", specialtyKey);
            }
            if (referenceKey) {
                params.set("ref", referenceKey);
            }
        }
        return params.toString();
    }

    function commitState(nextView, replace) {
        var mode = cleanViewMode(nextView && nextView.mode);
        var nextTerm = mode === "term" ? readTermNumber(nextView && nextView.termNumber) : 0;
        var nextUnitKey = mode === "term" ? cleanUnitKey(nextView && nextView.unitKey) : "";
        var nextSpecialtyKey = mode === "reference" ? cleanReferenceKey(nextView && nextView.specialtyKey) : "";
        var nextReferenceKey = mode === "reference" ? cleanReferenceKey(nextView && nextView.referenceKey) : "";

        if (nextTerm <= 0) {
            nextUnitKey = "";
        }
        if (!nextSpecialtyKey) {
            nextReferenceKey = "";
        }

        state.mode = mode;
        state.termNumber = nextTerm;
        state.unitKey = nextUnitKey;
        state.specialtyKey = nextSpecialtyKey;
        state.referenceKey = nextReferenceKey;

        var search = buildViewSearch({
            mode: state.mode,
            termNumber: state.termNumber,
            unitKey: state.unitKey,
            specialtyKey: state.specialtyKey,
            referenceKey: state.referenceKey
        });
        var target = window.location.pathname + (search ? "?" + search : "");
        if (replace) {
            window.history.replaceState({}, "", target);
        } else {
            window.history.pushState({}, "", target);
        }

        render();
    }

    function commitHome(replace) {
        commitState({ mode: "" }, replace);
    }

    function commitMode(mode, replace) {
        commitState({ mode: cleanViewMode(mode) }, replace);
    }

    function commitView(termNumber, unitKey, replace) {
        commitState({
            mode: "term",
            termNumber: termNumber,
            unitKey: unitKey
        }, replace);
    }

    function commitReferenceView(specialtyKey, referenceKey, replace) {
        commitState({
            mode: "reference",
            specialtyKey: specialtyKey,
            referenceKey: referenceKey
        }, replace);
    }

    function appendCohortPath(path) {
        var target = String(path || "").trim();
        var cohort = String(currentParams().get("cohort") || "").trim();
        if (!target || !cohort || /(?:\?|&)cohort=/.test(target)) {
            return target;
        }
        return target + (target.indexOf("?") === -1 ? "?" : "&") + "cohort=" + encodeURIComponent(cohort);
    }

    function loginHref() {
        if (window.Dent1402Auth && typeof window.Dent1402Auth.loginUrl === "function") {
            return window.Dent1402Auth.loginUrl(window.location.pathname + window.location.search);
        }
        return "/account/";
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
        var query = new URLSearchParams(Object.assign({ action: action }, payload || {}));
        var cohort = String(currentParams().get("cohort") || "").trim();
        if (cohort) {
            query.set("cohort", cohort);
        }
        query.set("_t", String(Date.now()));
        return fetchWithTimeout("/api/exams_api.php?" + query.toString(), {
            method: "GET",
            cache: "no-store",
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        }, 20000).then(parseJson).catch(networkErrorResponse);
    }

    function formatValue(value) {
        return (Math.max(0, Number(value) || 0)).toLocaleString("fa-IR");
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

    function accentClassName(index) {
        var accents = ["is-accent-a", "is-accent-b", "is-accent-c", "is-accent-d"];
        return accents[Math.abs(Number(index) || 0) % accents.length];
    }

    function joinMetaParts(parts) {
        return parts.filter(function (part) {
            return !!String(part || "").trim();
        }).join(" • ");
    }

    function finalExamLinesHtml(finalExams) {
        var items = Array.isArray(finalExams) ? finalExams : [];
        var lines = items.map(function (item) {
            return String(item && item.displayLabel ? item.displayLabel : "").trim();
        }).filter(Boolean);
        if (!lines.length) {
            return "";
        }
        return '<div class="catalog-simple-row__schedule-list">' + lines.map(function (line) {
            return '<span class="catalog-simple-row__schedule">' + escapeHtml(line) + "</span>";
        }).join("") + "</div>";
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
            config.sublineHtml ? config.sublineHtml : "",
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
        var interactiveOpen = "";
        var interactiveClose = "";
        var showChevron = true;

        if (config.type === "button") {
            interactiveOpen = '<button class="catalog-simple-row__button" type="button"' + (config.attrs || "") + ">";
            interactiveClose = "</button>";
        } else if (config.type === "static") {
            interactiveOpen = '<div class="catalog-simple-row__static">';
            interactiveClose = "</div>";
            showChevron = false;
        } else {
            interactiveOpen = '<a class="catalog-simple-row__link" href="' + escapeHtml(config.href || "#") + '">';
            interactiveClose = "</a>";
        }

        return [
            '<article class="catalog-simple-row' + (config.rowClassName ? " " + escapeHtml(config.rowClassName) : "") + '">',
            interactiveOpen,
            '  <div class="catalog-simple-row__body">',
            (config.eyebrow || config.status)
                ? '    <div class="catalog-simple-row__topline">'
                    + (config.eyebrow ? '<span class="catalog-simple-row__eyebrow">' + escapeHtml(config.eyebrow) + "</span>" : "")
                    + (config.status ? '<span class="catalog-simple-row__status' + (config.statusMuted ? " is-muted" : "") + '">' + escapeHtml(config.status) + "</span>" : "")
                    + "</div>"
                : "",
            '    <h3 class="catalog-simple-row__title">' + escapeHtml(config.title || "") + "</h3>",
            config.sublineHtml ? config.sublineHtml : "",
            config.meta
                ? '    <p class="catalog-simple-row__meta">' + escapeHtml(config.meta) + "</p>"
                : "",
            config.extraHtml
                ? '    <div class="catalog-simple-row__extra">' + config.extraHtml + "</div>"
                : "",
            "  </div>",
            '  <span class="catalog-simple-row__visual' + (config.visualMuted ? " is-muted" : "") + '" aria-hidden="true">'
                + (config.visualLabel ? "<strong>" + escapeHtml(config.visualLabel) + "</strong>" : "")
                + "</span>",
            '  <div class="catalog-simple-row__tail">',
            config.actionLabel
                ? '<span class="catalog-simple-row__action' + (showChevron ? "" : " is-muted") + '">' + escapeHtml(config.actionLabel) + "</span>"
                : "",
            showChevron ? '<span class="catalog-simple-row__chevron">‹</span>' : "",
            "  </div>",
            interactiveClose,
            "</article>"
        ].join("");
    }

    function simpleGroupHtml(options, rowsHtml) {
        var config = options || {};
        return [
            '<section class="catalog-simple-group">',
            '  <div class="catalog-simple-group__head">',
            config.eyebrow
                ? '    <span class="catalog-simple-group__eyebrow">' + escapeHtml(config.eyebrow) + "</span>"
                : "",
            '    <h3 class="catalog-simple-group__title">' + escapeHtml(config.title || "") + "</h3>",
            config.meta
                ? '    <p class="catalog-simple-group__meta">' + escapeHtml(config.meta) + "</p>"
                : "",
            "  </div>",
            '  <div class="catalog-simple-stack">' + (rowsHtml || "") + "</div>",
            "</section>"
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
                label: access.requiresLogin ? "نیاز به ورود" : (course.amountLabel || "پولی"),
                className: "exams-status-pill exams-status-pill--paid"
            };
        }
        return {
            label: "رایگان",
            className: "exams-status-pill exams-status-pill--free"
        };
    }

    function courseAction(course) {
        var access = course && course.access ? course.access : {};
        var directLabel = course && course.supportsDirectAttemptableExams ? "مشاهده جلسه‌ها" : "مشاهده بخش‌ها";

        if (course && course.paymentMode === "paid" && !access.hasAccess) {
            if (access.requiresLogin) {
                return {
                    label: "ورود برای ادامه",
                    href: loginHref()
                };
            }
            return {
                label: access.canPurchase ? "فعال‌سازی و ورود" : "مشاهده درس",
                href: access.canPurchase ? appendCohortPath(course.paymentPath || course.path || "/exams/") : appendCohortPath(course.path || "/exams/")
            };
        }

        return {
            label: directLabel,
            href: appendCohortPath(course.path || "/exams/")
        };
    }

    function curriculum() {
        return state.catalog && state.catalog.curriculum && typeof state.catalog.curriculum === "object"
            ? state.catalog.curriculum
            : null;
    }

    function curriculumTerms() {
        var payload = curriculum();
        return payload && Array.isArray(payload.terms) ? payload.terms : [];
    }

    function referenceCatalog() {
        return state.catalog && state.catalog.referenceCatalog && typeof state.catalog.referenceCatalog === "object"
            ? state.catalog.referenceCatalog
            : null;
    }

    function referenceSpecialties() {
        var payload = referenceCatalog();
        return payload && Array.isArray(payload.specialties) ? payload.specialties : [];
    }

    function findReferenceSpecialty(specialtyKey) {
        var cleanKey = cleanReferenceKey(specialtyKey);
        var specialties = referenceSpecialties();
        for (var index = 0; index < specialties.length; index += 1) {
            if (cleanReferenceKey(specialties[index] && specialties[index].key) === cleanKey) {
                return specialties[index];
            }
        }
        return null;
    }

    function findReferenceInSpecialty(specialty, referenceKey) {
        var cleanKey = cleanReferenceKey(referenceKey);
        var references = Array.isArray(specialty && specialty.references) ? specialty.references : [];
        for (var index = 0; index < references.length; index += 1) {
            if (cleanReferenceKey(references[index] && references[index].key) === cleanKey) {
                return references[index];
            }
        }
        return null;
    }

    function findTerm(termNumber) {
        var cleanNumber = readTermNumber(termNumber);
        var terms = curriculumTerms();
        for (var index = 0; index < terms.length; index += 1) {
            if (readTermNumber(terms[index] && terms[index].number) === cleanNumber) {
                return terms[index];
            }
        }
        return null;
    }

    function findUnitInTerm(term, unitKey) {
        var cleanKey = cleanUnitKey(unitKey);
        if (!term || !cleanKey) {
            return null;
        }

        var categories = Array.isArray(term.categories) ? term.categories : [];
        for (var categoryIndex = 0; categoryIndex < categories.length; categoryIndex += 1) {
            var units = Array.isArray(categories[categoryIndex].units) ? categories[categoryIndex].units : [];
            for (var unitIndex = 0; unitIndex < units.length; unitIndex += 1) {
                if (cleanUnitKey(units[unitIndex] && units[unitIndex].key) === cleanKey) {
                    return units[unitIndex];
                }
            }
        }

        return null;
    }

    function selectedTerm() {
        return findTerm(state.termNumber);
    }

    function selectedUnit() {
        var term = selectedTerm();
        if (!term || !state.unitKey) {
            return null;
        }
        return findUnitInTerm(term, state.unitKey);
    }

    function selectedReferenceSpecialty() {
        if (!state.specialtyKey) {
            return null;
        }
        return findReferenceSpecialty(state.specialtyKey);
    }

    function selectedReference() {
        var specialty = selectedReferenceSpecialty();
        if (!specialty || !state.referenceKey) {
            return null;
        }
        return findReferenceInSpecialty(specialty, state.referenceKey);
    }

    function normalizeViewState(replaceHistory) {
        state.mode = cleanViewMode(state.mode);

        if (!state.mode) {
            state.termNumber = 0;
            state.unitKey = "";
            state.specialtyKey = "";
            state.referenceKey = "";
            return;
        }

        if (state.mode === "term") {
            var terms = curriculumTerms();
            if (!terms.length) {
                commitHome(replaceHistory);
                return;
            }

            var term = selectedTerm();
            if (!term && state.termNumber > 0) {
                state.termNumber = 0;
                state.unitKey = "";
                if (replaceHistory) {
                    commitView(0, "", true);
                }
                return;
            }

            if (!state.unitKey) {
                return;
            }

            var unit = selectedUnit();
            if (!unit || cleanUnitKey(unit.entryMode) !== "collections") {
                state.unitKey = "";
                if (replaceHistory) {
                    commitView(state.termNumber, "", true);
                }
            }
            return;
        }

        if (state.mode === "reference") {
            var specialties = referenceSpecialties();
            if (!specialties.length) {
                commitHome(replaceHistory);
                return;
            }

            var specialty = selectedReferenceSpecialty();
            if (!specialty && state.specialtyKey) {
                state.specialtyKey = "";
                state.referenceKey = "";
                if (replaceHistory) {
                    commitReferenceView("", "", true);
                }
                return;
            }

            if (!state.referenceKey) {
                return;
            }

            var reference = selectedReference();
            if (!reference) {
                state.referenceKey = "";
                if (replaceHistory) {
                    commitReferenceView(state.specialtyKey, "", true);
                }
            }
            return;
        }

        commitHome(replaceHistory);
    }

    function homeHeroHtml() {
        var description = compactText(
            state.catalog && state.catalog.description,
            "مسیر ورود به آزمون‌ها را انتخاب کن.",
            96
        );
        return simpleHeroHtml({
            eyebrow: "آزمون‌ها",
            title: "از کدام مسیر وارد می‌شوی؟",
            meta: description
        });
    }

    function modeChoiceHtml(mode, index) {
        var isReference = mode === "reference";
        return simpleRowHtml({
            type: "button",
            attrs: ' data-open-mode="' + escapeHtml(mode) + '"',
            title: isReference ? "رفرنس‌محور" : "ترم‌محور",
            meta: isReference
                ? "اول تخصص را انتخاب کن، بعد رفرنس‌ها و آزمون‌های وصل‌شده را ببین."
                : "از ترم‌ها و واحدهای دانشگاهی وارد آزمون‌های همان درس شو.",
            rowClassName: accentClassName(index),
            visualLabel: isReference ? "رف" : "ترم",
            actionLabel: "انتخاب"
        });
    }

    function modeChooserHtml() {
        return [
            homeHeroHtml(),
            '<section class="catalog-simple-stack">',
            modeChoiceHtml("reference", 0),
            modeChoiceHtml("term", 1),
            "</section>"
        ].join("");
    }

    function termPreview(term) {
        var titles = [];
        var categories = Array.isArray(term && term.categories) ? term.categories : [];

        categories.forEach(function (category) {
            (Array.isArray(category && category.units) ? category.units : []).forEach(function (unit) {
                if (unit && unit.statusKey !== "empty") {
                    titles.push(String(unit.title || "").trim());
                }
            });
        });

        if (!titles.length) {
            categories.forEach(function (category) {
                (Array.isArray(category && category.units) ? category.units : []).forEach(function (unit) {
                    if (titles.length < 3) {
                        titles.push(String(unit.title || "").trim());
                    }
                });
            });
        }

        return titles.slice(0, 3);
    }

    function termCardHtml(term, index) {
        var stats = term && term.stats ? term.stats : {};
        var availableCount = Math.max(0, Number(stats.availableUnitCount || 0));
        var preview = termPreview(term);
        var isEmpty = availableCount <= 0;
        var meta = compactText(
            preview.length ? preview.join(" • ") : "",
            isEmpty ? "هنوز آزمونی برای این ترم ثبت نشده است." : "برای دیدن درس‌های این ترم وارد شو.",
            88
        );

        return simpleRowHtml({
            type: "button",
            attrs: ' data-open-term="' + escapeHtml(term.number) + '"',
            eyebrow: "ترم",
            status: isEmpty ? "بدون آزمون" : "",
            statusMuted: isEmpty,
            title: term.label || "",
            meta: meta,
            rowClassName: accentClassName(index) + (isEmpty ? " is-empty" : ""),
            visualLabel: formatValue(term.number || (index + 1)),
            visualMuted: isEmpty
        });
    }

    function termGridHtml() {
        var terms = curriculumTerms();
        if (!terms.length) {
            return '<div class="exams-card exams-empty">ساختار ترم‌ها هنوز برای این بخش ثبت نشده است.</div>';
        }

        return [
            simpleHeroHtml({
                eyebrow: "ترم‌محور",
                title: "آزمون‌ها بر اساس ترم",
                meta: "ترم را انتخاب کن و از داخل واحدهای همان ترم وارد آزمون‌ها شو.",
                actionsHtml: '<button class="exam-btn exam-btn--ghost" type="button" data-go-home="true">بازگشت به انتخاب مسیر</button>'
            }),
            '<section class="catalog-simple-stack">',
            terms.map(function (term, index) {
                return termCardHtml(term, index);
            }).join(""),
            "</section>"
        ].join("");
    }

    function specialtyPreview(specialty) {
        var titles = [];
        (Array.isArray(specialty && specialty.references) ? specialty.references : []).forEach(function (reference) {
            if (reference && reference.statusKey !== "empty") {
                titles.push(String(reference.title || "").trim());
            }
        });

        if (!titles.length) {
            (Array.isArray(specialty && specialty.references) ? specialty.references : []).forEach(function (reference) {
                if (titles.length < 2) {
                    titles.push(String(reference.title || "").trim());
                }
            });
        }

        return titles.slice(0, 2);
    }

    function specialtyCardHtml(specialty, index) {
        var stats = specialty && specialty.stats ? specialty.stats : {};
        var referenceCount = Math.max(0, Number(stats.referenceCount || 0));
        var availableCount = Math.max(0, Number(stats.availableReferenceCount || 0));
        var preview = specialtyPreview(specialty);
        var meta = compactText(
            preview.length ? preview.join(" • ") : "",
            availableCount > 0
                ? "رفرنس‌های این تخصص را ببین و وارد آزمون‌های فعال شو."
                : "رفرنس این تخصص ثبت شده اما هنوز آزمونی به آن وصل نشده است.",
            88
        );

        return simpleRowHtml({
            type: "button",
            attrs: ' data-open-specialty="' + escapeHtml(specialty.key || "") + '"',
            eyebrow: "تخصص",
            status: availableCount > 0
                ? formatValue(availableCount) + " رفرنس فعال"
                : "بدون آزمون",
            statusMuted: availableCount <= 0,
            title: specialty.title || "",
            meta: referenceCount > 0 ? meta : "هنوز رفرنسی برای این تخصص ثبت نشده است.",
            rowClassName: accentClassName(index) + (availableCount <= 0 ? " is-empty" : ""),
            visualLabel: formatValue(index + 1),
            visualMuted: availableCount <= 0
        });
    }

    function referenceSpecialtyGridHtml() {
        var specialties = referenceSpecialties();
        if (!specialties.length) {
            return '<div class="exams-card exams-empty">فهرست رفرنس‌ها هنوز برای این بخش ثبت نشده است.</div>';
        }

        var payload = referenceCatalog();
        return [
            simpleHeroHtml({
                eyebrow: "رفرنس‌محور",
                title: (payload && payload.title) || "آزمون‌ها بر اساس رفرنس",
                meta: (payload && payload.description) || "ابتدا تخصص را انتخاب کن و بعد وارد رفرنس‌ها شو.",
                actionsHtml: '<button class="exam-btn exam-btn--ghost" type="button" data-go-home="true">بازگشت به انتخاب مسیر</button>'
            }),
            '<section class="catalog-simple-stack">',
            specialties.map(function (specialty, index) {
                return specialtyCardHtml(specialty, index);
            }).join(""),
            "</section>"
        ].join("");
    }

    function sectionHeroHtml(term, unit) {
        var title = unit ? unit.title : (term && term.label) || "آزمون‌ها";
        var description = unit
            ? compactText(unit.description, "یکی از مجموعه‌های همین واحد را باز کن تا جلسه‌ها را ببینی.", 80)
            : ((term && term.stats && Number(term.stats.availableUnitCount || 0) > 0)
                ? "واحد موردنظرت را از بین ردیف‌های همین ترم انتخاب کن."
                : "ساختار این ترم ثبت شده اما هنوز آزمونی به آن وصل نشده است.");

        return simpleHeroHtml({
            eyebrow: unit ? (unit.categoryTitle || "مجموعه آزمون‌ها") : "واحدهای همین ترم",
            title: title,
            sublineHtml: unit ? finalExamLinesHtml(unit.finalExams) : "",
            meta: description,
            actionsHtml: [
                '<button class="exam-btn exam-btn--ghost" type="button" data-open-mode="term">بازگشت به ترم‌ها</button>',
                unit
                    ? '<button class="exam-btn exam-btn--ghost" type="button" data-back-term="' + escapeHtml(term && term.number) + '">بازگشت به ' + escapeHtml(term && term.label || "") + "</button>"
                    : ""
            ].filter(Boolean).join(""),
            secondaryHtml: unit && unit.collectionTitles && unit.collectionTitles.length
                ? '<span class="exams-session-meta">' + escapeHtml(unit.collectionTitles.join(" | ")) + "</span>"
                : ""
        });
    }

    function unitActionConfig(unit) {
        var entryMode = cleanUnitKey(unit && unit.entryMode);
        if (entryMode === "direct") {
            return {
                type: "link",
                href: appendCohortPath(unit.entryHref || "/exams/"),
                actionLabel: unit.entryLabel || "ورود"
            };
        }
        if (entryMode === "collections") {
            return {
                type: "button",
                attrs: ' data-open-unit="' + escapeHtml(unit.key || "") + '"',
                actionLabel: unit.entryLabel || "ورود"
            };
        }
        return {
            type: "static",
            actionLabel: "بدون آزمون"
        };
    }

    function unitCardHtml(unit, index) {
        var note = unit && unit.collectionTitles && unit.collectionTitles.length > 1
            ? unit.collectionTitles.join(" | ")
            : "";
        var action = unitActionConfig(unit);
        var isEmpty = cleanUnitKey(unit && unit.statusKey) === "empty";
        var meta = compactText(
            note || unit.description || "",
            isEmpty ? "هنوز آزمونی برای این درس ثبت نشده است." : "برای دیدن آزمون‌های این درس وارد شو.",
            78
        );

        return simpleRowHtml({
            type: action.type,
            attrs: action.attrs || "",
            href: action.href || "",
            eyebrow: unit.categoryTitle || "واحد",
            status: isEmpty ? (unit.statusLabel || "بدون آزمون") : "",
            statusMuted: isEmpty,
            title: unit.title || "",
            sublineHtml: finalExamLinesHtml(unit.finalExams),
            meta: meta,
            rowClassName: accentClassName(index) + (isEmpty ? " is-empty" : ""),
            visualLabel: formatValue(index + 1),
            visualMuted: isEmpty
        });
    }

    function categoryCardHtml(category) {
        var units = Array.isArray(category && category.units) ? category.units : [];
        if (!units.length) {
            return simpleGroupHtml({
                eyebrow: "دسته",
                title: category.title || "",
                meta: "هنوز واحدی برای این دسته ثبت نشده است."
            }, "");
        }

        return simpleGroupHtml({
            eyebrow: "دسته",
            title: category.title || "",
            meta: ""
        }, units.map(function (unit, index) {
            return unitCardHtml(unit, index);
        }).join(""));
    }

    function termDetailHtml(term) {
        var categories = Array.isArray(term && term.categories) ? term.categories : [];
        return [
            sectionHeroHtml(term, null),
            '<section class="catalog-simple-stack">',
            categories.map(function (category) {
                return categoryCardHtml(category);
            }).join(""),
            "</section>"
        ].join("");
    }

    function referenceMetaParts(reference) {
        var collections = Array.isArray(reference && reference.collections) ? reference.collections : [];
        var parts = [];
        if (reference && reference.editionLabel) {
            parts.push(reference.editionLabel);
        }
        if (reference && Number(reference.year || 0) > 0) {
            parts.push("سال " + formatValue(reference.year));
        }
        if (collections.length > 1) {
            parts.push(formatValue(collections.length) + " مجموعه آزمون");
        } else if (collections.length === 1) {
            parts.push("دارای آزمون فعال");
        } else {
            parts.push("آزمون فعال ندارد");
        }
        return parts;
    }

    function referenceMeta(reference) {
        return joinMetaParts(referenceMetaParts(reference));
    }

    function referenceActionConfig(reference) {
        var entryMode = cleanUnitKey(reference && reference.entryMode);
        if (entryMode === "direct") {
            return {
                type: "link",
                href: appendCohortPath(reference.entryHref || "/exams/"),
                actionLabel: reference.entryLabel || "مشاهده آزمون‌ها"
            };
        }
        if (entryMode === "collections") {
            return {
                type: "button",
                attrs: ' data-open-reference="' + escapeHtml(reference.key || "") + '"',
                actionLabel: reference.entryLabel || "مشاهده مجموعه‌ها"
            };
        }
        return {
            type: "static",
            actionLabel: "بدون آزمون"
        };
    }

    function referenceCardHtml(reference, index) {
        var action = referenceActionConfig(reference);
        var isEmpty = cleanUnitKey(reference && reference.statusKey) === "empty";
        var sourceTitle = String(reference && reference.sourceTitle || "").trim();
        var sourceHtml = sourceTitle
            ? '<span class="exams-reference-source" dir="ltr" lang="en">' + escapeHtml(sourceTitle) + "</span>"
            : "";

        return simpleRowHtml({
            type: action.type,
            attrs: action.attrs || "",
            href: action.href || "",
            eyebrow: "رفرنس",
            status: isEmpty ? (reference.statusLabel || "بدون آزمون") : "",
            statusMuted: isEmpty,
            title: reference.title || "",
            meta: referenceMeta(reference),
            extraHtml: sourceHtml,
            rowClassName: accentClassName(index) + (isEmpty ? " is-empty" : ""),
            visualLabel: formatValue(index + 1),
            visualMuted: isEmpty,
            actionLabel: action.actionLabel || ""
        });
    }

    function referenceCardHtml(reference, index) {
        var action = referenceActionConfig(reference);
        var isEmpty = cleanUnitKey(reference && reference.statusKey) === "empty";
        var persianTitle = String(reference && (reference.title || reference.titleFa || reference.fa) || "").trim();
        var sourceTitle = String(reference && (reference.sourceTitle || reference.englishTitle || reference.titleEn || reference.en || reference.reference || reference.source) || "").trim();
        var statusLabel = isEmpty ? "بدون آزمون" : "دارای آزمون";
        var metaParts = referenceMetaParts(reference).map(function (part) {
            return '<span class="exams-reference-card__meta-item">' + escapeHtml(part) + "</span>";
        }).join("");
        var interactiveOpen = "";
        var interactiveClose = "";

        if (action.type === "button") {
            interactiveOpen = '<button class="exams-reference-card__button" type="button"' + (action.attrs || "") + ">";
            interactiveClose = "</button>";
        } else if (action.type === "static") {
            interactiveOpen = '<div class="exams-reference-card__static">';
            interactiveClose = "</div>";
        } else {
            interactiveOpen = '<a class="exams-reference-card__link" href="' + escapeHtml(action.href || "#") + '">';
            interactiveClose = "</a>";
        }

        return [
            '<article class="exams-reference-card ' + escapeHtml(accentClassName(index)) + (isEmpty ? " is-empty" : "") + '">',
            interactiveOpen,
            '  <div class="exams-reference-card__topline">',
            '    <span class="exams-reference-card__pill">رفرنس</span>',
            '    <span class="exams-reference-card__status' + (isEmpty ? " is-muted" : "") + '">' + escapeHtml(statusLabel) + "</span>",
            "  </div>",
            '  <h3 class="exams-reference-card__title-fa">' + escapeHtml(persianTitle) + "</h3>",
            '  <div class="exams-reference-card__divider" aria-hidden="true"></div>',
            sourceTitle
                ? '  <p class="exams-reference-card__title-en" dir="ltr" lang="en">' + escapeHtml(sourceTitle) + "</p>"
                : "",
            metaParts
                ? '  <div class="exams-reference-card__meta">' + metaParts + "</div>"
                : "",
            interactiveClose,
            "</article>"
        ].join("");
    }

    function referenceListHeroHtml(specialty) {
        var stats = specialty && specialty.stats ? specialty.stats : {};
        var meta = joinMetaParts([
            formatValue(stats.referenceCount || 0) + " رفرنس",
            formatValue(stats.availableReferenceCount || 0) + " رفرنس دارای آزمون"
        ]);

        return simpleHeroHtml({
            eyebrow: "رفرنس‌های تخصص",
            title: specialty && specialty.title || "رفرنس‌ها",
            meta: meta,
            actionsHtml: [
                '<button class="exam-btn exam-btn--ghost" type="button" data-open-mode="reference">بازگشت به تخصص‌ها</button>',
                '<button class="exam-btn exam-btn--ghost" type="button" data-go-home="true">انتخاب مسیر دیگر</button>'
            ].join("")
        });
    }

    function referenceListHtml(specialty) {
        var references = Array.isArray(specialty && specialty.references) ? specialty.references : [];
        return [
            referenceListHeroHtml(specialty),
            references.length
                ? '<section class="catalog-simple-stack">' + references.map(function (reference, index) {
                    return referenceCardHtml(reference, index);
                }).join("") + "</section>"
                : '<div class="exams-card exams-empty">برای این تخصص هنوز رفرنسی ثبت نشده است.</div>'
        ].join("");
    }

    function referenceCollectionsHtml(specialty, reference) {
        var collections = Array.isArray(reference && reference.collections) ? reference.collections : [];
        var sourceTitle = String(reference && reference.sourceTitle || "").trim();
        return [
            simpleHeroHtml({
                eyebrow: specialty && specialty.title || "رفرنس",
                title: reference && reference.title || "آزمون‌های رفرنس",
                meta: referenceMeta(reference),
                actionsHtml: [
                    '<button class="exam-btn exam-btn--ghost" type="button" data-back-specialty="' + escapeHtml(specialty && specialty.key || "") + '">بازگشت به رفرنس‌ها</button>',
                    '<button class="exam-btn exam-btn--ghost" type="button" data-open-mode="reference">بازگشت به تخصص‌ها</button>'
                ].join(""),
                secondaryHtml: sourceTitle
                    ? '<span class="exams-reference-source exams-reference-source--hero" dir="ltr" lang="en">' + escapeHtml(sourceTitle) + "</span>"
                    : ""
            }),
            collections.length
                ? '<section class="catalog-simple-stack">' + collections.map(function (course, index) {
                    return courseCardHtml(course, index);
                }).join("") + "</section>"
                : '<div class="exams-card exams-empty">برای این رفرنس هنوز آزمونی ثبت نشده است.</div>'
        ].join("");
    }

    function courseCardHtml(course, index) {
        var status = statusMeta(course);
        var action = courseAction(course);
        var title = cleanCourseTitle(course.title || "") || String(course.title || "").trim();
        var meta = compactText(
            course.cardDescription || course.heroDescription || "",
            "برای دیدن جلسه‌های این مجموعه وارد شو.",
            80
        );
        var statusText = status.label === "رایگان" ? "" : status.label;

        return simpleRowHtml({
            type: "link",
            href: action.href,
            eyebrow: course.badge || "مجموعه",
            status: statusText,
            title: title,
            sublineHtml: finalExamLinesHtml(course && course.curriculum && course.curriculum.finalExams),
            meta: meta,
            rowClassName: accentClassName(index),
            visualLabel: formatValue(index + 1)
        });
    }

    function unitCollectionsHtml(term, unit) {
        var collections = Array.isArray(unit && unit.collections) ? unit.collections : [];
        return [
            sectionHeroHtml(term, unit),
            collections.length
                ? '<section class="catalog-simple-stack">' + collections.map(function (course, index) {
                    return courseCardHtml(course, index);
                }).join("") + "</section>"
                : '<div class="exams-card exams-empty">برای این واحد هنوز مجموعه‌ای ثبت نشده است.</div>'
        ].join("");
    }

    function renderLegacyCatalog() {
        var catalog = state.catalog;
        var courses = Array.isArray(catalog && catalog.courses) ? catalog.courses : [];
        if (!courses.length) {
            root.innerHTML = '<div class="exams-card exams-empty">هنوز آزمونی برای این بخش ثبت نشده است.</div>';
            return;
        }

        root.innerHTML = [
            '<section class="catalog-simple-stack">',
            courses.map(function (course, index) {
                return courseCardHtml(course, index);
            }).join(""),
            "</section>"
        ].join("");
    }

    function render() {
        if (!state.catalog) {
            root.innerHTML = '<div class="exams-card exams-loading">در حال بارگذاری آزمون‌ها...</div>';
            return;
        }

        var terms = curriculumTerms();
        if (!terms.length) {
            renderLegacyCatalog();
            return;
        }

        normalizeViewState(false);

        if (!state.mode) {
            root.innerHTML = modeChooserHtml();
            return;
        }

        if (state.mode === "reference") {
            var specialty = selectedReferenceSpecialty();
            if (!specialty) {
                root.innerHTML = referenceSpecialtyGridHtml();
                return;
            }

            if (state.referenceKey) {
                var reference = selectedReference();
                if (reference) {
                    root.innerHTML = referenceCollectionsHtml(specialty, reference);
                    return;
                }
            }

            root.innerHTML = referenceListHtml(specialty);
            return;
        }

        if (state.mode !== "term" || !state.termNumber) {
            root.innerHTML = termGridHtml();
            return;
        }

        var term = selectedTerm();
        if (!term) {
            root.innerHTML = termGridHtml();
            return;
        }

        if (state.unitKey) {
            var unit = selectedUnit();
            if (unit && cleanUnitKey(unit.entryMode) === "collections") {
                root.innerHTML = unitCollectionsHtml(term, unit);
                return;
            }
        }

        root.innerHTML = termDetailHtml(term);
    }

    function setLoading() {
        root.innerHTML = '<div class="exams-card exams-loading">در حال بارگذاری آزمون‌ها...</div>';
    }

    function setError(message) {
        root.innerHTML = '<div class="exams-card exams-empty">' + escapeHtml(message || "بارگذاری انجام نشد.") + "</div>";
    }

    function load() {
        if (state.loading) {
            return;
        }

        state.loading = true;
        setLoading();
        apiGet("catalog").then(function (payload) {
            if (!payload || !payload.success || !payload.catalog) {
                throw new Error((payload && payload.error) || "بارگذاری آزمون‌ها انجام نشد.");
            }

            state.catalog = payload.catalog;
            state.viewer = payload.viewer || null;
            normalizeViewState(true);
            render();
        }).catch(function (error) {
            setError(error && error.message ? error.message : "بارگذاری انجام نشد.");
        }).finally(function () {
            state.loading = false;
        });
    }

    root.addEventListener("click", function (event) {
        var homeButton = event.target.closest("[data-go-home]");
        if (homeButton) {
            event.preventDefault();
            commitHome(false);
            return;
        }

        var modeButton = event.target.closest("[data-open-mode]");
        if (modeButton) {
            event.preventDefault();
            commitMode(modeButton.getAttribute("data-open-mode"), false);
            return;
        }

        var termButton = event.target.closest("[data-open-term]");
        if (termButton) {
            event.preventDefault();
            commitView(termButton.getAttribute("data-open-term"), "", false);
            return;
        }

        var backTermButton = event.target.closest("[data-back-term]");
        if (backTermButton) {
            event.preventDefault();
            commitView(backTermButton.getAttribute("data-back-term"), "", false);
            return;
        }

        var unitButton = event.target.closest("[data-open-unit]");
        if (unitButton) {
            event.preventDefault();
            commitView(state.termNumber, unitButton.getAttribute("data-open-unit"), false);
            return;
        }

        var specialtyButton = event.target.closest("[data-open-specialty]");
        if (specialtyButton) {
            event.preventDefault();
            commitReferenceView(specialtyButton.getAttribute("data-open-specialty"), "", false);
            return;
        }

        var referenceButton = event.target.closest("[data-open-reference]");
        if (referenceButton) {
            event.preventDefault();
            commitReferenceView(state.specialtyKey, referenceButton.getAttribute("data-open-reference"), false);
            return;
        }

        var backSpecialtyButton = event.target.closest("[data-back-specialty]");
        if (backSpecialtyButton) {
            event.preventDefault();
            commitReferenceView(backSpecialtyButton.getAttribute("data-back-specialty"), "", false);
        }
    });

    window.addEventListener("popstate", function () {
        applyLocationView();
        render();
    });

    if (window.Dent1402Auth && typeof window.Dent1402Auth.onChange === "function") {
        window.Dent1402Auth.onChange(function (detail) {
            if (!detail || detail.status === "session-restoring" || detail.status === "logging-out") {
                setLoading();
                return;
            }
            applyLocationView();
            load();
        });
    } else {
        applyLocationView();
        load();
    }
})();
