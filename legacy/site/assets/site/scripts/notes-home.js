(function () {
    "use strict";

    function $(id) {
        return document.getElementById(id);
    }

    function authApi() {
        return window.Dent1402Auth && typeof window.Dent1402Auth === "object"
            ? window.Dent1402Auth
            : null;
    }

    function siteApi() {
        return window.Dent1402Site && typeof window.Dent1402Site === "object"
            ? window.Dent1402Site
            : null;
    }

    function normalizeNotesCohort(value) {
        var cohort = String(value == null ? "" : value).trim();
        if (!cohort || cohort === "main" || cohort === "1402" || cohort === "dentistry-1402") {
            return "1402";
        }
        if (cohort === "1403" || cohort === "dentistry-1403") {
            return "1403";
        }
        if (cohort === "1404" || cohort === "dentistry-1404") {
            return "1404";
        }
        if (cohort === "prosthesis-1402") {
            return "prosthesis-1402";
        }
        return cohort;
    }

    function resolveCohort() {
        var auth = authApi();
        var cohort = auth && typeof auth.resolvePageCohort === "function"
            ? auth.resolvePageCohort("notesCohort")
            : String(document.body && document.body.dataset ? document.body.dataset.notesCohort || "" : "").trim();
        return normalizeNotesCohort(cohort);
    }

    var pageCohort = resolveCohort();
    if (["1402", "1403", "1404", "prosthesis-1402"].indexOf(pageCohort) === -1) {
        return;
    }

    var list = $("notes-home-list");
    var empty = $("notes-home-empty");
    var manage = $("notes-home-manage");
    var form = $("notes-home-form");
    var feedback = $("notes-home-feedback");
    var submit = $("notes-home-submit");
    var unitManagePanel = $("notes-home-unit-manage");
    var unitManageForm = $("notes-home-unit-form");
    var unitManageFeedback = $("notes-home-unit-feedback");
    var unitManageSubmit = $("notes-home-unit-submit");
    var unitManageHeading = $("notes-home-unit-manage-heading");
    var unitManageLead = $("notes-home-unit-manage-copy");
    var heading = $("notes-home-heading");
    var subheading = $("notes-home-subheading");
    var backLink = $("notes-home-back-link");
    var kicker = $("notes-home-kicker");
    var title = $("notes-home-title");
    var sectionKicker = $("notes-home-section-kicker");
    var sectionTitle = $("notes-home-section-title");
    var sectionCopy = $("notes-home-section-copy");
    var footer = $("notes-home-footer");

    if (!list || !empty) {
        return;
    }

    var state = {
        authKey: "",
        canManage: false,
        deletingTermId: 0,
        editingTermId: 0,
        loadError: "",
        loading: false,
        manageExpanded: false,
        saving: false,
        terms: []
    };

    function manageSupported() {
        return pageCohort === "prosthesis-1402";
    }

    function homeCanManage() {
        return manageSupported() && state.canManage;
    }

    function cohortYearLabel() {
        if (pageCohort === "1403") {
            return "۱۴۰۳";
        }
        if (pageCohort === "1404") {
            return "۱۴۰۴";
        }
        return "۱۴۰۲";
    }

    function toFaDigits(value) {
        return String(value == null ? "" : value).replace(/\d/g, function (digit) {
            return ["۰", "۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹"][Number(digit)] || digit;
        });
    }

    function parseJsonResponse(response) {
        var site = siteApi();
        if (site && typeof site.parseJsonResponse === "function") {
            return site.parseJsonResponse(response);
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

    function consumeUnauthorized(payload, fallbackText) {
        var site = siteApi();
        if (site && typeof site.consumeUnauthorized === "function") {
            return !!site.consumeUnauthorized(payload, fallbackText || "نشست شما منقضی شده است.");
        }
        var auth = authApi();
        if (auth && typeof auth.handleUnauthorizedPayload === "function") {
            return !!auth.handleUnauthorizedPayload(payload, fallbackText || "نشست شما منقضی شده است.");
        }
        return !!(payload && (payload.loggedOut || payload.httpStatus === 401));
    }

    function request(action, method, payload) {
        var options = {
            method: method,
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        };
        var data = Object.assign({ cohort: pageCohort }, payload || {});
        var url = "/api/notes_api.php?action=" + encodeURIComponent(action);

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

    function authSnapshotKey() {
        var auth = authApi();
        if (!auth || typeof auth.getState !== "function") {
            return "anon";
        }
        var snapshot = auth.getState();
        var studentNumber = snapshot && snapshot.user && snapshot.user.studentNumber
            ? String(snapshot.user.studentNumber)
            : "";
        return String(snapshot && snapshot.status ? snapshot.status : "unknown") + ":" + studentNumber;
    }

    function dentalHomeBasePath() {
        if (pageCohort === "1403") {
            return "/notes/1403/";
        }
        if (pageCohort === "1404") {
            return "/notes/1404/";
        }
        return "/notes/";
    }

    function dentalManagePath() {
        return "/notes/term/";
    }

    function dentalRequestedTerm() {
        var params = new URLSearchParams(window.location.search || "");
        var value = Number(params.get("term") || "0");
        return Number.isFinite(value) ? value : 0;
    }

    function dentalRequestedUnitKey() {
        return String(new URLSearchParams(window.location.search || "").get("unit") || "").trim().toLowerCase();
    }

    function dentalBuildUrl(pathname, termValue, unitKey) {
        var url = new URL(pathname, window.location.origin);
        if (termValue > 0) {
            url.searchParams.set("term", String(termValue));
        }
        if (unitKey) {
            url.searchParams.set("unit", unitKey);
        }
        if (pageCohort !== "1402") {
            url.searchParams.set("cohort", pageCohort);
        }
        return url.pathname + (url.search || "");
    }

    function dentalHomeUrl(termValue, unitKey) {
        return dentalBuildUrl(dentalHomeBasePath(), termValue, unitKey);
    }

    function dentalManageUrl(termValue, unitKey) {
        var url = new URL(dentalManagePath(), window.location.origin);
        if (termValue > 0) {
            url.searchParams.set("term", String(termValue));
        }
        if (unitKey) {
            url.searchParams.set("unit", unitKey);
        }
        url.searchParams.set("manage", "1");
        if (pageCohort !== "1402") {
            url.searchParams.set("cohort", pageCohort);
        }
        return url.pathname + (url.search || "");
    }

    function dentalBodyMode(mode) {
        if (!document.body) {
            return;
        }
        document.body.classList.add("notes-curriculum-page");
        document.body.dataset.notesView = mode || "";
    }

    function dentalYearLabel() {
        return toFaDigits(pageCohort);
    }

    function dentalResetList() {
        list.innerHTML = "";
        empty.hidden = true;
    }

    function dentalShowEmpty(message, preserveList) {
        if (!preserveList) {
            list.innerHTML = "";
        }
        empty.hidden = false;
        empty.textContent = message || "داده‌ای برای نمایش پیدا نشد.";
    }

    function dentalSectionText(kickerText, titleText, copyText) {
        if (sectionKicker) {
            sectionKicker.textContent = kickerText || "";
        }
        if (sectionTitle) {
            sectionTitle.textContent = titleText || "";
        }
        if (sectionCopy) {
            sectionCopy.textContent = copyText || "";
        }
    }

    function dentalApplyBaseCopy() {
        var yearLabel = dentalYearLabel();
        if (heading) {
            heading.textContent = "آرشیو منابع ورودی " + yearLabel;
        }
        if (subheading) {
            subheading.textContent = "چینش ترم، دسته و واحد";
        }
        if (backLink) {
            backLink.href = pageCohort === "1402" ? "/app/#resources" : "/app/";
            backLink.textContent = pageCohort === "1402" ? "بازگشت به منابع" : "بازگشت به خانه";
        }
        if (kicker) {
            kicker.textContent = "آرشیو " + yearLabel;
        }
        if (footer) {
            footer.textContent = "ورودی " + yearLabel + " دندانپزشکی تهران";
        }
    }

    function dentalCreate(tagName, className, text) {
        var node = document.createElement(tagName);
        if (className) {
            node.className = className;
        }
        if (text !== undefined && text !== null) {
            node.textContent = text;
        }
        return node;
    }

    function dentalCreateStat(labelText, valueText) {
        var stat = dentalCreate("div", "notes-summary-stat");
        stat.appendChild(dentalCreate("span", "notes-summary-stat__label", labelText));
        stat.appendChild(dentalCreate("strong", "notes-summary-stat__value", valueText));
        return stat;
    }

    function dentalAppendSummary(stats) {
        var shell = dentalCreate("section", "notes-summary-shell");
        var grid = dentalCreate("div", "notes-summary-grid");
        grid.appendChild(dentalCreateStat("ترم", toFaDigits(stats.termCount || 0)));
        grid.appendChild(dentalCreateStat("واحد فعال", toFaDigits(stats.availableUnitCount || 0)));
        grid.appendChild(dentalCreateStat("منبع", toFaDigits(stats.itemCount || 0)));
        shell.appendChild(grid);
        list.appendChild(shell);
    }

    function dentalCreateChip(text, className) {
        return dentalCreate("span", className || "notes-chip", text);
    }

    function dentalCreateActionLink(label, href, muted) {
        var link = dentalCreate("a", muted ? "notes-link-btn notes-link-btn--muted" : "notes-link-btn", label);
        if (muted) {
            link.removeAttribute("href");
            link.setAttribute("aria-disabled", "true");
            link.tabIndex = -1;
        } else {
            link.href = href;
        }
        return link;
    }

    function dentalCompactText(value, fallback, maxLength) {
        var text = String(value || "").replace(/\s+/g, " ").trim();
        if (!text) {
            text = String(fallback || "").trim();
        }
        if (!text || !maxLength || text.length <= maxLength) {
            return text;
        }
        return text.slice(0, Math.max(0, maxLength - 1)).trim() + "…";
    }

    function dentalMetaText(parts) {
        return parts.filter(function (part) {
            return !!String(part || "").trim();
        }).join(" • ");
    }

    function dentalFormatDate(value) {
        var date = new Date(String(value || ""));
        if (Number.isNaN(date.getTime())) {
            return "";
        }
        try {
            return new Intl.DateTimeFormat("fa-IR", {
                month: "short",
                day: "numeric"
            }).format(date);
        } catch (error) {
            return "";
        }
    }

    function dentalApplyResourceInsights(payload) {
        if (payload && payload.resourceInsights) {
            dentalState.resourceInsights = payload.resourceInsights;
        }
    }

    function dentalResourceInsights() {
        return dentalState.resourceInsights && typeof dentalState.resourceInsights === "object"
            ? dentalState.resourceInsights
            : {
                authenticated: false,
                favoriteIds: [],
                favorites: [],
                recent: [],
                latestUpdates: [],
                pending: {},
                inbox: null
            };
    }

    function dentalIsResourceFavorite(item) {
        var itemId = Number(item && item.id || 0);
        var favoriteIds = Array.isArray(dentalResourceInsights().favoriteIds)
            ? dentalResourceInsights().favoriteIds
            : [];
        return favoriteIds.some(function (id) {
            return Number(id || 0) === itemId;
        });
    }

    function dentalSetResourceFeedback(text) {
        dentalState.resourceFeedback = String(text || "");
        var node = $("notes-resource-feedback");
        if (node) {
            node.textContent = dentalState.resourceFeedback;
            node.hidden = !dentalState.resourceFeedback;
        }
    }

    function dentalCreateMiniList(titleText, items, emptyText) {
        var group = dentalCreate("div", "notes-resource-mini-list");
        group.appendChild(dentalCreate("h4", "notes-resource-mini-list__title", titleText));
        var listNode = dentalCreate("div", "notes-resource-mini-list__items");
        var rows = Array.isArray(items) ? items.slice(0, 3) : [];
        if (!rows.length) {
            listNode.appendChild(dentalCreate("p", "notes-resource-mini-list__empty", emptyText));
        } else {
            rows.forEach(function (item) {
                var link = dentalCreate("a", "notes-resource-mini-list__item");
                link.href = item.viewUrl || item.buttonUrl || "#";
                link.textContent = dentalCompactText(item.title || item.itemTitle || "منبع", "", 52);
                listNode.appendChild(link);
            });
        }
        group.appendChild(listNode);
        return group;
    }

    function dentalCreateRequestBox(termData) {
        var form = dentalCreate("form", "notes-resource-request");
        form.setAttribute("data-resource-request-form", "true");
        form.appendChild(dentalCreate("h4", "notes-resource-request__title", "درخواست منبع"));
        var titleInput = dentalCreate("input", "notes-resource-request__input");
        titleInput.type = "text";
        titleInput.name = "title";
        titleInput.maxLength = 180;
        titleInput.placeholder = "عنوان منبع موردنیاز";
        var noteInput = dentalCreate("textarea", "notes-resource-request__input");
        noteInput.name = "note";
        noteInput.maxLength = 800;
        noteInput.placeholder = "توضیح کوتاه";
        var button = dentalCreate("button", "notes-link-btn");
        button.type = "submit";
        button.textContent = dentalState.resourceBusy ? "در حال ثبت..." : "ثبت درخواست";
        button.disabled = !!dentalState.resourceBusy;
        form.appendChild(titleInput);
        form.appendChild(noteInput);
        form.appendChild(button);
        form.dataset.term = String(Number(termData && (termData.term || termData.termNumber) || dentalRequestedTerm() || 0));
        form.dataset.unitKey = String(termData && termData.unitKey || dentalRequestedUnitKey() || "");
        return form;
    }

    function dentalCreateOwnerInbox(insights) {
        var inbox = insights && insights.inbox ? insights.inbox : null;
        var shell = dentalCreate("div", "notes-resource-inbox");
        var entries = [];
        (Array.isArray(inbox && inbox.requests) ? inbox.requests : []).slice(0, 3).forEach(function (item) {
            entries.push({
                type: "request",
                id: item.id,
                title: item.title || item.note || "درخواست منبع",
                meta: item.userName || "دانشجو"
            });
        });
        (Array.isArray(inbox && inbox.reports) ? inbox.reports : []).slice(0, 3).forEach(function (item) {
            entries.push({
                type: "report",
                id: item.id,
                title: item.itemTitle || item.reason || "گزارش لینک",
                meta: item.userName || "دانشجو"
            });
        });
        if (!entries.length) {
            return shell;
        }
        entries.slice(0, 4).forEach(function (entry) {
            var row = dentalCreate("div", "notes-resource-inbox__row");
            var text = dentalCreate("span", "notes-resource-inbox__text");
            text.textContent = entry.title + " • " + entry.meta;
            var button = dentalCreate("button", "notes-resource-action notes-resource-action--muted");
            button.type = "button";
            button.dataset.resourceIssue = "true";
            button.dataset.issueType = entry.type;
            button.dataset.issueId = String(entry.id || "");
            button.textContent = "رسیدگی شد";
            row.appendChild(text);
            row.appendChild(button);
            shell.appendChild(row);
        });
        return shell;
    }

    function dentalAppendResourceInsights(termData) {
        var insights = dentalResourceInsights();
        var shell = dentalCreate("section", "notes-resource-insights");
        var head = dentalCreate("div", "notes-resource-insights__head");
        head.appendChild(dentalCreate("span", "archive-term__kicker", "منابع من"));
        head.appendChild(dentalCreate("h3", "", "پیگیری منابع"));
        var feedbackNode = dentalCreate("p", "notes-manage-feedback");
        feedbackNode.id = "notes-resource-feedback";
        feedbackNode.hidden = !dentalState.resourceFeedback;
        feedbackNode.textContent = dentalState.resourceFeedback;
        head.appendChild(feedbackNode);
        shell.appendChild(head);

        var grid = dentalCreate("div", "notes-resource-insights__grid");
        grid.appendChild(dentalCreateMiniList(
            "علاقه‌مندی‌ها",
            insights.authenticated ? insights.favorites : [],
            insights.authenticated ? "هنوز منبعی ذخیره نشده است." : "برای ذخیره علاقه‌مندی وارد حساب شو."
        ));
        grid.appendChild(dentalCreateMiniList(
            "اخیراً دیده‌شده",
            insights.authenticated ? insights.recent : [],
            insights.authenticated ? "هنوز منبعی باز نکرده‌ای." : "بعد از ورود، بازدیدهای اخیر اینجا می‌آید."
        ));
        grid.appendChild(dentalCreateMiniList(
            "آخرین آپدیت‌ها",
            insights.latestUpdates,
            "آپدیت تازه‌ای ثبت نشده است."
        ));
        shell.appendChild(grid);

        var pending = insights.pending || {};
        if (dentalState.canManage && insights.inbox) {
            var ownerLine = dentalCreate("div", "notes-resource-owner-line");
            ownerLine.appendChild(dentalCreate(
                "span",
                "notes-chip notes-chip--soft",
                "درخواست باز: " + toFaDigits(pending.requests || 0)
            ));
            ownerLine.appendChild(dentalCreate(
                "span",
                "notes-chip notes-chip--soft",
                "گزارش لینک: " + toFaDigits(pending.reports || 0)
            ));
            shell.appendChild(ownerLine);
            shell.appendChild(dentalCreateOwnerInbox(insights));
        }

        if (termData) {
            shell.appendChild(dentalCreateRequestBox(termData));
        }

        list.appendChild(shell);
    }

    function dentalResourceItemTerm(item, fallbackTerm) {
        return Number(item && (item.storageTerm || item.term) || fallbackTerm || dentalRequestedTerm() || 0);
    }

    function dentalTrackResourceOpen(item) {
        var itemId = Number(item && item.id || 0);
        if (!itemId) {
            return;
        }
        request("trackResourceOpen", "POST", {
            itemId: String(itemId),
            term: String(dentalResourceItemTerm(item, 0))
        }).then(function (payload) {
            dentalApplyResourceInsights(payload);
        });
    }

    function dentalAccentClassName(index) {
        var accents = ["is-accent-a", "is-accent-b", "is-accent-c", "is-accent-d"];
        return accents[Math.abs(Number(index) || 0) % accents.length];
    }

    function dentalRowClassName(index, isEmpty) {
        return dentalAccentClassName(index) + (isEmpty ? " is-empty" : "");
    }

    function dentalPreviewText(parts, fallback, maxLength) {
        var preview = parts.filter(function (part) {
            return !!String(part || "").trim();
        }).slice(0, 3).join(" • ");
        return dentalCompactText(preview, fallback, maxLength || 86);
    }

    function dentalTermPreview(term) {
        var titles = [];
        var categories = Array.isArray(term && term.categories) ? term.categories : [];

        categories.forEach(function (category) {
            (Array.isArray(category && category.units) ? category.units : []).forEach(function (unit) {
                if (titles.length >= 3) {
                    return;
                }
                if (Number(unit && unit.itemCount || 0) <= 0) {
                    return;
                }
                var nextTitle = String(unit && unit.title || "").trim();
                if (nextTitle) {
                    titles.push(nextTitle);
                }
            });
        });

        if (titles.length) {
            return titles;
        }

        categories.forEach(function (category) {
            (Array.isArray(category && category.units) ? category.units : []).forEach(function (unit) {
                if (titles.length >= 3) {
                    return;
                }
                var nextTitle = String(unit && unit.title || "").trim();
                if (nextTitle) {
                    titles.push(nextTitle);
                }
            });
        });

        return titles;
    }

    function dentalAppendSimpleGroupHead(section, eyebrowText, titleText, metaText) {
        var head = dentalCreate("div", "catalog-simple-group__head");
        if (eyebrowText) {
            head.appendChild(dentalCreate("span", "catalog-simple-group__eyebrow", eyebrowText));
        }
        head.appendChild(dentalCreate("h3", "catalog-simple-group__title", titleText || ""));
        if (metaText) {
            head.appendChild(dentalCreate("p", "catalog-simple-group__meta", metaText));
        }
        section.appendChild(head);
    }

    function dentalCreateSimpleRow(options) {
        var config = options || {};
        var article = dentalCreate("article", "catalog-simple-row" + (config.rowClassName ? " " + config.rowClassName : ""));
        var link = dentalCreate("a", "catalog-simple-row__link");
        link.href = config.href || "#";
        if (config.analyticsDownload) {
            link.dataset.analyticsDownload = config.analyticsDownload;
        }
        if (config.analyticsLabel) {
            link.dataset.analyticsLabel = config.analyticsLabel;
        }
        if (config.external) {
            link.target = "_blank";
            link.rel = "noopener noreferrer";
        }

        var body = dentalCreate("div", "catalog-simple-row__body");
        if (config.eyebrow || config.status) {
            var topline = dentalCreate("div", "catalog-simple-row__topline");
            if (config.eyebrow) {
                topline.appendChild(dentalCreate("span", "catalog-simple-row__eyebrow", config.eyebrow));
            }
            if (config.status) {
                topline.appendChild(dentalCreate(
                    "span",
                    "catalog-simple-row__status" + (config.statusMuted ? " is-muted" : ""),
                    config.status
                ));
            }
            body.appendChild(topline);
        }

        body.appendChild(dentalCreate("h3", "catalog-simple-row__title", config.title || ""));
        var scheduleLines = Array.isArray(config.scheduleLines) ? config.scheduleLines.filter(Boolean) : [];
        if (scheduleLines.length) {
            var scheduleList = dentalCreate("div", "catalog-simple-row__schedule-list");
            scheduleLines.forEach(function (line) {
                scheduleList.appendChild(dentalCreate("span", "catalog-simple-row__schedule", line));
            });
            body.appendChild(scheduleList);
        }
        if (config.meta) {
            body.appendChild(dentalCreate("p", "catalog-simple-row__meta", config.meta));
        }
        link.appendChild(body);

        var visual = dentalCreate("span", "catalog-simple-row__visual" + (config.visualMuted ? " is-muted" : ""));
        visual.setAttribute("aria-hidden", "true");
        if (config.visualLabel) {
            visual.appendChild(dentalCreate("strong", "", config.visualLabel));
        }
        link.appendChild(visual);

        var tail = dentalCreate("div", "catalog-simple-row__tail");
        if (config.actionLabel) {
            tail.appendChild(dentalCreate("span", "catalog-simple-row__action", config.actionLabel));
        }
        tail.appendChild(dentalCreate("span", "catalog-simple-row__chevron", "‹"));
        link.appendChild(tail);
        article.appendChild(link);
        return article;
    }

    function dentalFinalExamLines(unit) {
        return (Array.isArray(unit && unit.finalExams) ? unit.finalExams : []).map(function (item) {
            return String(item && item.displayLabel ? item.displayLabel : "").trim();
        }).filter(Boolean);
    }

    function dentalAppendTermCards(curriculum) {
        var terms = Array.isArray(curriculum && curriculum.terms) ? curriculum.terms : [];
        var grid = dentalCreate("div", "catalog-simple-stack");

        terms.forEach(function (term, index) {
            var itemCount = Number((term.stats && term.stats.itemCount) || 0);
            var preview = dentalTermPreview(term);
            grid.appendChild(dentalCreateSimpleRow({
                href: dentalHomeUrl(Number(term.number || 0), ""),
                eyebrow: "ترم",
                status: itemCount > 0 ? "" : "بدون منبع",
                statusMuted: itemCount <= 0,
                title: term.label || "ترم",
                meta: dentalPreviewText(
                    preview,
                    itemCount > 0 ? "برای دیدن واحدهای این ترم وارد شو." : "هنوز منبعی برای این ترم ثبت نشده است.",
                    88
                ),
                rowClassName: dentalRowClassName(index, itemCount <= 0),
                visualLabel: toFaDigits(term.number || (index + 1)),
                visualMuted: itemCount <= 0
            }));
        });

        list.appendChild(grid);
    }

    function dentalAppendLegacyTerms(curriculum) {
        var extraTerms = Array.isArray(curriculum && curriculum.extraTerms) ? curriculum.extraTerms : [];
        if (!extraTerms.length) {
            return;
        }

        var shell = dentalCreate("section", "catalog-simple-group");
        dentalAppendSimpleGroupHead(
            shell,
            "آرشیوهای دیگر",
            "منابع خارج از ساختار ۴ تا ۱۲",
            "منابع قدیمی‌تر یا عمومی که هنوز بیرون از ساختار دانشکده نگه‌داری می‌شوند."
        );

        var grid = dentalCreate("div", "catalog-simple-stack");
        extraTerms.forEach(function (termData) {
            grid.appendChild(dentalCreateSimpleRow({
                href: dentalHomeUrl(Number(termData.term || 0), ""),
                eyebrow: "آرشیو",
                status: "",
                title: termData.title || "آرشیو",
                meta: dentalCompactText(
                    termData.description || "",
                    "آرشیو این ترم در صفحه بعدی باز می‌شود.",
                    88
                ),
                rowClassName: dentalRowClassName(termData.term || 0, Number(termData.itemCount || 0) <= 0),
                visualLabel: toFaDigits(termData.term || "?"),
                visualMuted: Number(termData.itemCount || 0) <= 0
            }));
        });

        shell.appendChild(grid);
        list.appendChild(shell);
    }

    function dentalFindMainTerm(curriculum, termNumber) {
        var terms = Array.isArray(curriculum && curriculum.terms) ? curriculum.terms : [];
        for (var index = 0; index < terms.length; index += 1) {
            if (Number(terms[index].number || 0) === Number(termNumber || 0)) {
                return terms[index];
            }
        }
        return null;
    }

    function dentalFindLegacyTerm(curriculum, termNumber) {
        var terms = Array.isArray(curriculum && curriculum.extraTerms) ? curriculum.extraTerms : [];
        for (var index = 0; index < terms.length; index += 1) {
            if (Number(terms[index].term || 0) === Number(termNumber || 0)) {
                return terms[index];
            }
        }
        return null;
    }

    function dentalAppendOverviewActions(termNumber, canManage) {
        var actions = dentalCreate("div", "notes-inline-actions");
        actions.appendChild(dentalCreateActionLink("بازگشت به همه ترم‌ها", dentalHomeUrl(0, ""), false));
        if (canManage && termNumber > 0) {
            actions.appendChild(dentalCreateActionLink("مدیریت این ترم", dentalManageUrl(termNumber, ""), false));
        }
        list.appendChild(actions);
    }

    function dentalAppendTermOverview(termData) {
        var categories = Array.isArray(termData && termData.categories) ? termData.categories : [];
        categories.forEach(function (category) {
            var section = dentalCreate("section", "catalog-simple-group");
            dentalAppendSimpleGroupHead(
                section,
                "دسته",
                category.title || "",
                ""
            );

            var unitList = dentalCreate("div", "catalog-simple-stack");
            var units = Array.isArray(category.units) ? category.units : [];
            units.forEach(function (unit, index) {
                var isEmpty = Number(unit && unit.itemCount || 0) <= 0;
                unitList.appendChild(dentalCreateSimpleRow({
                    href: dentalHomeUrl(Number(termData.number || 0), unit.key || ""),
                    eyebrow: category.title || "واحد",
                    status: isEmpty ? (unit.statusLabel || "بدون منبع") : "",
                    statusMuted: isEmpty,
                    title: unit.title || "واحد",
                    scheduleLines: dentalFinalExamLines(unit),
                    meta: dentalCompactText(
                        unit.description || "",
                        isEmpty ? "هنوز منبعی برای این درس ثبت نشده است." : "برای دیدن منابع این درس وارد شو.",
                        76
                    ),
                    rowClassName: dentalRowClassName(index, isEmpty),
                    visualLabel: toFaDigits(index + 1),
                    visualMuted: isEmpty
                }));
            });
            section.appendChild(unitList);
            list.appendChild(section);
        });
    }

    function dentalAppendResourceActions(termData, canManage, unitKey) {
        var termNumber = Number(termData.term || termData.termNumber || 0);
        var actions = dentalCreate("div", "notes-inline-actions");
        actions.appendChild(dentalCreateActionLink("بازگشت به همه ترم‌ها", dentalHomeUrl(0, ""), false));
        if (termNumber > 0) {
            actions.appendChild(dentalCreateActionLink("بازگشت به " + (termData.termLabel || ("ترم " + toFaDigits(termNumber))), dentalHomeUrl(termNumber, ""), false));
        }
        if (canManage) {
            actions.appendChild(dentalCreateActionLink(unitKey ? "مدیریت کامل این واحد" : "مدیریت کامل این آرشیو", dentalManageUrl(termNumber, unitKey || ""), false));
        }
        list.appendChild(actions);
    }

    function dentalCreateResourceRow(item, index, termData) {
        var termNumber = dentalResourceItemTerm(item, Number(termData && (termData.term || termData.termNumber) || 0));
        var updatedText = dentalFormatDate(item.updatedAt);
        var statusText = updatedText ? ("آپدیت " + updatedText) : "";
        var row = dentalCreateSimpleRow({
            href: item.buttonUrl || "#",
            external: !!item.isExternal,
            eyebrow: item.badge || "منبع",
            status: statusText,
            title: item.title || "بدون عنوان",
            meta: dentalCompactText(
                item.description || "",
                "لینک این منبع از همین ردیف باز می‌شود.",
                108
            ),
            actionLabel: item.buttonLabel || "باز کردن",
            analyticsDownload: "notes-resource",
            analyticsLabel: item.title || item.badge || "منبع",
            rowClassName: dentalRowClassName(index, false),
            visualLabel: toFaDigits(Number(item.version || 1) > 1 ? item.version : index + 1)
        });
        row.classList.add("notes-resource-row");
        row.dataset.resourceItemId = String(item.id || "");
        row.dataset.resourceTerm = String(termNumber || "");

        var link = row.querySelector(".catalog-simple-row__link");
        if (link) {
            link.dataset.resourceOpen = "true";
            link.dataset.itemId = String(item.id || "");
            link.dataset.term = String(termNumber || "");
        }

        var actions = dentalCreate("div", "notes-resource-row__actions");
        var favoriteButton = dentalCreate("button", "notes-resource-action");
        favoriteButton.type = "button";
        favoriteButton.dataset.resourceFavorite = "true";
        favoriteButton.dataset.itemId = String(item.id || "");
        favoriteButton.dataset.term = String(termNumber || "");
        favoriteButton.dataset.favorite = dentalIsResourceFavorite(item) ? "0" : "1";
        favoriteButton.textContent = dentalIsResourceFavorite(item) ? "حذف علاقه‌مندی" : "علاقه‌مندی";
        actions.appendChild(favoriteButton);

        var reportButton = dentalCreate("button", "notes-resource-action notes-resource-action--muted");
        reportButton.type = "button";
        reportButton.dataset.resourceReport = "true";
        reportButton.dataset.itemId = String(item.id || "");
        reportButton.dataset.term = String(termNumber || "");
        reportButton.textContent = "گزارش لینک";
        actions.appendChild(reportButton);

        var versionLabel = dentalCreate(
            "span",
            "notes-resource-version",
            "نسخه " + toFaDigits(Number(item.version || 1))
        );
        actions.appendChild(versionLabel);
        row.appendChild(actions);
        return row;
    }

    function dentalAppendResourceCards(termData, canManage) {
        var items = Array.isArray(termData && termData.items) ? termData.items : [];
        if (!items.length) {
            var emptyMessage = termData.emptyMessage || "برای این بخش هنوز منبعی ثبت نشده است.";
            if (canManage && termData && termData.unitKey) {
                emptyMessage = "برای این واحد هنوز منبعی ثبت نشده است. فرم افزودن سریع همین پایین آماده است.";
            }
            dentalShowEmpty(emptyMessage, true);
            return;
        }

        var wrap = dentalCreate("div", "catalog-simple-stack");
        items.forEach(function (item, index) {
            wrap.appendChild(dentalCreateResourceRow(item, index, termData));
        });
        list.appendChild(wrap);
    }

    function dentalRenderCurriculumHome(curriculum) {
        dentalBodyMode("terms");
        dentalApplyBaseCopy();
        dentalState.downloadHost = null;
        if (title) {
            title.textContent = "ترم موردنظر را برای دیدن منابع انتخاب کن.";
        }
        dentalSectionText(
            "ترم‌های دانشکده",
            "چینش منابع بر اساس ترم و واحد",
            "ابتدا ترم را انتخاب کن، بعد از داخل دسته‌ها وارد واحد هر درس شو."
        );
        document.title = "آرشیو منابع " + dentalYearLabel() + " | ساختار ترم و واحد";
        dentalResetList();
        dentalAppendTermCards(curriculum);
        dentalAppendLegacyTerms(curriculum);
        dentalAppendResourceInsights(null);
        dentalSyncUnitManagePanel(null);
    }

    function dentalRenderTermOverview(curriculum, termData) {
        dentalBodyMode("term");
        dentalApplyBaseCopy();
        dentalState.downloadHost = null;
        if (title) {
            title.textContent = termData.label || ("ترم " + toFaDigits(termData.number || 0));
        }
        dentalSectionText(
            "واحدهای همین ترم",
            termData.label || "ترم",
            "واحد موردنظرت را از بین دسته‌های همین ترم انتخاب کن."
        );
        if (backLink) {
            backLink.href = dentalHomeUrl(0, "");
            backLink.textContent = "بازگشت به همه ترم‌ها";
        }
        document.title = (termData.label || "ترم") + " | آرشیو منابع " + dentalYearLabel();
        dentalResetList();
        dentalAppendOverviewActions(Number(termData.number || 0), !!dentalState.canManage);
        dentalAppendTermOverview(termData);
        dentalAppendResourceInsights({
            term: Number(termData.number || 0),
            termNumber: Number(termData.number || 0),
            unitKey: ""
        });
        dentalSyncUnitManagePanel(null);
    }

    function dentalRenderLegacyTerm(termData) {
        dentalBodyMode("legacy-term");
        dentalApplyBaseCopy();
        dentalState.downloadHost = null;
        if (title) {
            title.textContent = termData.title || ("ترم " + toFaDigits(termData.term || 0));
        }
        dentalSectionText(
            "آرشیو خارج از ساختار",
            termData.title || "آرشیو",
            termData.description || "این آرشیو هنوز خارج از ساختار اصلی ۴ تا ۱۲ نگه‌داری می‌شود."
        );
        if (backLink) {
            backLink.href = dentalHomeUrl(0, "");
            backLink.textContent = "بازگشت به همه ترم‌ها";
        }
        document.title = (termData.title || "آرشیو") + " | آرشیو منابع " + dentalYearLabel();
        dentalResetList();
        dentalAppendResourceActions(termData, !!dentalState.canManage, "");
        dentalAppendResourceCards(termData, !!dentalState.canManage);
        dentalAppendResourceInsights(termData);
        dentalSyncUnitManagePanel(null);
    }

    function dentalRenderUnitDetail(termData) {
        dentalBodyMode("unit");
        dentalApplyBaseCopy();
        var scheduleLines = dentalFinalExamLines(termData);
        var detailDescription = termData.description || "منابع این واحد از همین بخش در دسترس هستند.";
        if (scheduleLines.length) {
            detailDescription = scheduleLines.join(" • ") + " — " + detailDescription;
        }
        if (title) {
            title.textContent = termData.title || "منابع واحد";
        }
        dentalSectionText(
            termData.categoryTitle || "منابع این واحد",
            termData.title || "منابع این واحد",
            detailDescription
        );
        if (backLink) {
            backLink.href = dentalHomeUrl(Number(termData.term || termData.termNumber || 0), "");
            backLink.textContent = "بازگشت به " + (termData.termLabel || ("ترم " + toFaDigits(termData.term || 0)));
        }
        document.title = (termData.title || "منابع واحد") + " | آرشیو منابع " + dentalYearLabel();
        dentalResetList();
        dentalAppendResourceActions(termData, !!dentalState.canManage, termData.unitKey || "");
        dentalAppendResourceCards(termData, !!dentalState.canManage);
        dentalAppendResourceInsights(termData);
        dentalSyncUnitManagePanel(termData);
    }

    function dentalRenderError(message) {
        dentalBodyMode("error");
        dentalApplyBaseCopy();
        dentalState.downloadHost = null;
        if (title) {
            title.textContent = "منابع این بخش پیدا نشد.";
        }
        dentalSectionText("خطا", "امکان نمایش منابع وجود ندارد", message || "در دریافت داده‌ها خطایی رخ داد.");
        dentalShowEmpty(message || "در دریافت داده‌ها خطایی رخ داد.");
        dentalSyncUnitManagePanel(null);
    }

    var dentalState = {
        authKey: "",
        canManage: false,
        curriculum: null,
        unitDetail: null,
        downloadHost: null,
        loading: false,
        resourceBusy: false,
        resourceFeedback: "",
        resourceInsights: null,
        unitSaving: false
    };

    var unitHostTools = null;

    function dentalUnitManageInputs() {
        return {
            badge: $("notes-home-unit-badge"),
            title: $("notes-home-unit-title-input"),
            description: $("notes-home-unit-description"),
            buttonLabel: $("notes-home-unit-button-label"),
            buttonUrl: $("notes-home-unit-button-url")
        };
    }

    function dentalSetUnitManageFeedback(text, kind) {
        if (!unitManageFeedback) {
            return;
        }
        unitManageFeedback.textContent = text || "";
        unitManageFeedback.dataset.kind = kind || "";
        unitManageFeedback.hidden = !text;
    }

    function dentalClearUnitManageForm() {
        var inputs = dentalUnitManageInputs();
        Object.keys(inputs).forEach(function (key) {
            if (inputs[key]) {
                inputs[key].value = "";
            }
        });
        if (unitHostTools && typeof unitHostTools.resetLinkMode === "function") {
            unitHostTools.resetLinkMode();
        }
    }

    function dentalReadUnitManagePayload() {
        var inputs = dentalUnitManageInputs();
        return {
            badge: inputs.badge ? String(inputs.badge.value || "").trim() : "",
            title: inputs.title ? String(inputs.title.value || "").trim() : "",
            description: inputs.description ? String(inputs.description.value || "").trim() : "",
            buttonLabel: inputs.buttonLabel ? String(inputs.buttonLabel.value || "").trim() : "",
            buttonUrl: inputs.buttonUrl ? String(inputs.buttonUrl.value || "").trim() : ""
        };
    }

    function dentalSyncUnitManagePanel(termData) {
        if (!unitManagePanel) {
            return;
        }

        var visible = !!(termData && termData.unitKey && dentalState.canManage);
        unitManagePanel.hidden = !visible;
        dentalSyncUnitHostTools(visible);
        if (!visible) {
            return;
        }

        var unitTitle = String(termData.title || "این واحد").trim() || "این واحد";
        if (unitManageHeading) {
            unitManageHeading.textContent = "افزودن منبع برای " + unitTitle;
        }
        if (unitManageLead) {
            unitManageLead.textContent = "این فرم سریع فقط به همین واحد وصل می‌شود و کارت ثبت‌شده بلافاصله در همین صفحه دیده خواهد شد.";
        }
        if (unitManageSubmit) {
            unitManageSubmit.disabled = !!dentalState.unitSaving;
            unitManageSubmit.textContent = dentalState.unitSaving ? "در حال افزودن..." : "افزودن منبع همین واحد";
        }
    }

    function ensureDentalUnitHostTools() {
        if (unitHostTools || !unitManageForm || !window.Dent1402NotesHostPicker || typeof window.Dent1402NotesHostPicker.create !== "function") {
            return;
        }
        unitHostTools = window.Dent1402NotesHostPicker.create({
            prefix: "notes-home-unit-host",
            manageForm: unitManageForm,
            insertBeforeNode: unitManageSubmit || null,
            request: request,
            handleUnauthorized: function (payload, fallbackText) {
                return consumeUnauthorized(payload, fallbackText);
            },
            getTerm: function () {
                var detail = dentalState.unitDetail || {};
                return Number(detail.term || detail.termNumber || dentalRequestedTerm() || 0);
            },
            getCohort: function () {
                return pageCohort;
            },
            getContext: function () {
                return dentalState.unitDetail || null;
            },
            linkInput: dentalUnitManageInputs().buttonUrl,
            buttonLabelInput: dentalUnitManageInputs().buttonLabel
        });
    }

    function dentalSyncUnitHostTools(canManage) {
        ensureDentalUnitHostTools();
        if (!unitHostTools) {
            return;
        }
        unitHostTools.sync({
            canManage: !!canManage,
            info: dentalState.downloadHost || null
        });
    }

    function dentalApplyCreatedUnitItem(item) {
        if (!dentalState.unitDetail) {
            return;
        }
        var nextId = Number(item && item.id || 0);
        var items = Array.isArray(dentalState.unitDetail.items) ? dentalState.unitDetail.items.slice() : [];
        items = items.filter(function (entry) {
            return Number(entry && entry.id || 0) !== nextId;
        });
        items.unshift(item);
        dentalState.unitDetail.items = items;
    }

    function dentalSubmitUnitManageForm(event) {
        event.preventDefault();
        if (!unitManageForm || dentalState.unitSaving || !dentalState.canManage || !dentalState.unitDetail) {
            return;
        }

        var payload = dentalReadUnitManagePayload();
        if (!payload.buttonUrl) {
            dentalSetUnitManageFeedback("لینک منبع هنوز تنظیم نشده است. یک فایل موجود را انتخاب کن یا فایل جدیدی روی هاست دانلود آپلود کن.", "error");
            return;
        }
        if (!payload.badge || !payload.title || !payload.description || !payload.buttonLabel) {
            dentalSetUnitManageFeedback("همه فیلدهای منبع را کامل وارد کن.", "error");
            return;
        }

        var termData = dentalState.unitDetail;
        var termNumber = Number(termData.term || termData.termNumber || dentalRequestedTerm() || 0);
        var unitKey = String(termData.unitKey || dentalRequestedUnitKey() || "").trim().toLowerCase();
        if (!termNumber || !unitKey) {
            dentalSetUnitManageFeedback("تشخیص واحد این صفحه ممکن نشد.", "error");
            return;
        }

        dentalState.unitSaving = true;
        dentalSyncUnitManagePanel(termData);
        dentalSetUnitManageFeedback("", "");

        request("addItem", "POST", {
            term: String(termNumber),
            unitKey: unitKey,
            badge: payload.badge,
            title: payload.title,
            description: payload.description,
            buttonLabel: payload.buttonLabel,
            buttonUrl: payload.buttonUrl
        }).then(function (response) {
            if (consumeUnauthorized(response, "برای افزودن منبع این واحد باید وارد حساب مجاز شوی.")) {
                throw new Error("برای افزودن منبع این واحد باید وارد حساب مجاز شوی.");
            }
            if (!response || !response.success || !response.item) {
                throw new Error((response && response.error) || "ثبت منبع این واحد انجام نشد.");
            }

            dentalApplyCreatedUnitItem(response.item);
            dentalClearUnitManageForm();
            dentalRenderFromState();
            dentalSetUnitManageFeedback(response.message || "منبع این واحد ثبت شد.", "success");
        }).catch(function (error) {
            dentalSetUnitManageFeedback(error && error.message ? error.message : "افزودن منبع این واحد با خطا مواجه شد.", "error");
        }).finally(function () {
            dentalState.unitSaving = false;
            dentalSyncUnitManagePanel(dentalState.unitDetail || termData);
        });
    }

    function dentalBindUnitManageForm() {
        if (!unitManageForm) {
            return;
        }
        unitManageForm.addEventListener("submit", dentalSubmitUnitManageForm);
    }

    function dentalSubmitResourceRequest(form) {
        if (!form || dentalState.resourceBusy) {
            return;
        }
        var titleInput = form.querySelector("input[name='title']");
        var noteInput = form.querySelector("textarea[name='note']");
        var titleValue = titleInput ? String(titleInput.value || "").trim() : "";
        var noteValue = noteInput ? String(noteInput.value || "").trim() : "";
        if (!titleValue && !noteValue) {
            dentalSetResourceFeedback("عنوان یا توضیح درخواست منبع را وارد کن.");
            return;
        }

        dentalState.resourceBusy = true;
        dentalRenderFromState();
        request("requestResource", "POST", {
            term: form.dataset.term || "",
            unitKey: form.dataset.unitKey || "",
            title: titleValue,
            note: noteValue
        }).then(function (payload) {
            if (consumeUnauthorized(payload, "برای ثبت درخواست منبع باید وارد حساب شوی.")) {
                throw new Error("برای ثبت درخواست منبع باید وارد حساب شوی.");
            }
            if (!payload || !payload.success) {
                throw new Error((payload && payload.error) || "ثبت درخواست منبع انجام نشد.");
            }
            dentalApplyResourceInsights(payload);
            dentalState.resourceFeedback = payload.message || "درخواست منبع ثبت شد.";
            dentalRenderFromState();
        }).catch(function (error) {
            dentalSetResourceFeedback(error && error.message ? error.message : "ثبت درخواست منبع با خطا مواجه شد.");
        }).finally(function () {
            dentalState.resourceBusy = false;
            dentalRenderFromState();
        });
    }

    function dentalBindResourceActions() {
        list.addEventListener("click", function (event) {
            var favoriteButton = event.target && event.target.closest ? event.target.closest("[data-resource-favorite]") : null;
            var reportButton = event.target && event.target.closest ? event.target.closest("[data-resource-report]") : null;
            var issueButton = event.target && event.target.closest ? event.target.closest("[data-resource-issue]") : null;
            var openLink = event.target && event.target.closest ? event.target.closest("[data-resource-open]") : null;

            if (issueButton) {
                event.preventDefault();
                if (dentalState.resourceBusy) {
                    return;
                }
                dentalState.resourceBusy = true;
                request("updateResourceIssueStatus", "POST", {
                    type: issueButton.dataset.issueType || "",
                    id: issueButton.dataset.issueId || "",
                    status: "resolved"
                }).then(function (payload) {
                    if (consumeUnauthorized(payload, "برای مدیریت صندوق منابع باید وارد حساب مجاز شوی.")) {
                        throw new Error("برای مدیریت صندوق منابع باید وارد حساب مجاز شوی.");
                    }
                    if (!payload || !payload.success) {
                        throw new Error((payload && payload.error) || "به‌روزرسانی وضعیت انجام نشد.");
                    }
                    dentalApplyResourceInsights(payload);
                    dentalState.resourceFeedback = payload.message || "وضعیت مورد به‌روزرسانی شد.";
                    dentalRenderFromState();
                }).catch(function (error) {
                    dentalSetResourceFeedback(error && error.message ? error.message : "به‌روزرسانی وضعیت با خطا مواجه شد.");
                }).finally(function () {
                    dentalState.resourceBusy = false;
                    dentalRenderFromState();
                });
                return;
            }

            if (favoriteButton) {
                event.preventDefault();
                if (dentalState.resourceBusy) {
                    return;
                }
                dentalState.resourceBusy = true;
                request("toggleResourceFavorite", "POST", {
                    itemId: favoriteButton.dataset.itemId || "",
                    term: favoriteButton.dataset.term || "",
                    favorite: favoriteButton.dataset.favorite || "1"
                }).then(function (payload) {
                    if (consumeUnauthorized(payload, "برای ذخیره علاقه‌مندی باید وارد حساب شوی.")) {
                        throw new Error("برای ذخیره علاقه‌مندی باید وارد حساب شوی.");
                    }
                    if (!payload || !payload.success) {
                        throw new Error((payload && payload.error) || "تغییر علاقه‌مندی انجام نشد.");
                    }
                    dentalApplyResourceInsights(payload);
                    dentalState.resourceFeedback = payload.message || "علاقه‌مندی به‌روزرسانی شد.";
                    dentalRenderFromState();
                }).catch(function (error) {
                    dentalSetResourceFeedback(error && error.message ? error.message : "تغییر علاقه‌مندی با خطا مواجه شد.");
                }).finally(function () {
                    dentalState.resourceBusy = false;
                    dentalRenderFromState();
                });
                return;
            }

            if (reportButton) {
                event.preventDefault();
                if (dentalState.resourceBusy) {
                    return;
                }
                var note = window.prompt("اگر توضیح کوتاهی درباره مشکل لینک داری بنویس.");
                if (note === null) {
                    return;
                }
                dentalState.resourceBusy = true;
                request("reportResourceLink", "POST", {
                    itemId: reportButton.dataset.itemId || "",
                    term: reportButton.dataset.term || "",
                    reason: "گزارش دانشجو",
                    note: note
                }).then(function (payload) {
                    if (consumeUnauthorized(payload, "برای گزارش لینک باید وارد حساب شوی.")) {
                        throw new Error("برای گزارش لینک باید وارد حساب شوی.");
                    }
                    if (!payload || !payload.success) {
                        throw new Error((payload && payload.error) || "ثبت گزارش لینک انجام نشد.");
                    }
                    dentalApplyResourceInsights(payload);
                    dentalState.resourceFeedback = payload.message || "گزارش لینک ثبت شد.";
                    dentalRenderFromState();
                }).catch(function (error) {
                    dentalSetResourceFeedback(error && error.message ? error.message : "ثبت گزارش لینک با خطا مواجه شد.");
                }).finally(function () {
                    dentalState.resourceBusy = false;
                    dentalRenderFromState();
                });
                return;
            }

            if (openLink) {
                dentalTrackResourceOpen({
                    id: openLink.dataset.itemId || "",
                    term: openLink.dataset.term || ""
                });
            }
        });

        list.addEventListener("submit", function (event) {
            var form = event.target && event.target.closest ? event.target.closest("[data-resource-request-form]") : null;
            if (!form) {
                return;
            }
            event.preventDefault();
            dentalSubmitResourceRequest(form);
        });
    }

    function dentalRenderFromState() {
        var requestedTerm = dentalRequestedTerm();
        var requestedUnitKey = dentalRequestedUnitKey();
        if (requestedUnitKey) {
            if (dentalState.unitDetail) {
                dentalRenderUnitDetail(dentalState.unitDetail);
                return;
            }
            if (!dentalState.loading) {
                dentalRenderError("واحد انتخاب‌شده برای این ترم پیدا نشد.");
            }
            return;
        }

        if (!dentalState.curriculum) {
            if (!dentalState.loading) {
                dentalRenderError("ساختار منابع این ورودی در دسترس نیست.");
            }
            return;
        }

        if (!requestedTerm) {
            dentalRenderCurriculumHome(dentalState.curriculum);
            return;
        }

        var mainTerm = dentalFindMainTerm(dentalState.curriculum, requestedTerm);
        if (mainTerm) {
            dentalRenderTermOverview(dentalState.curriculum, mainTerm);
            return;
        }

        var legacyTerm = dentalFindLegacyTerm(dentalState.curriculum, requestedTerm);
        if (legacyTerm) {
            dentalRenderLegacyTerm(legacyTerm);
            return;
        }

        dentalRenderError("ترم انتخاب‌شده برای این ورودی پیدا نشد.");
    }

    function dentalLoadHomeData() {
        if (dentalState.loading) {
            return Promise.resolve();
        }
        dentalState.loading = true;
        dentalState.curriculum = null;
        dentalState.unitDetail = null;
        dentalState.downloadHost = null;
        dentalState.resourceInsights = null;
        dentalShowEmpty("در حال دریافت ساختار منابع...");
        dentalSetUnitManageFeedback("", "");
        dentalSyncUnitManagePanel(null);
        return request("terms", "GET", {}).then(function (payload) {
            if (consumeUnauthorized(payload, "نشست شما منقضی شده است.")) {
                dentalState.canManage = false;
            }
            if (!payload || !payload.success || !payload.curriculum) {
                throw new Error((payload && payload.error) || "دریافت ساختار منابع ناموفق بود.");
            }
            dentalState.curriculum = payload.curriculum;
            dentalState.canManage = !!payload.canManage;
            dentalApplyResourceInsights(payload);
            dentalRenderFromState();
        }).catch(function (error) {
            dentalRenderError(error && error.message ? error.message : "دریافت ساختار منابع با خطا مواجه شد.");
        }).finally(function () {
            dentalState.loading = false;
        });
    }

    function dentalLoadUnitDetail() {
        if (dentalState.loading) {
            return Promise.resolve();
        }
        dentalState.loading = true;
        dentalState.unitDetail = null;
        dentalState.downloadHost = null;
        dentalState.resourceInsights = null;
        dentalShowEmpty("در حال دریافت منابع این واحد...");
        dentalSetUnitManageFeedback("", "");
        dentalSyncUnitManagePanel(null);
        return request("term", "GET", {
            term: String(dentalRequestedTerm() || 0),
            unit: dentalRequestedUnitKey()
        }).then(function (payload) {
            if (consumeUnauthorized(payload, "نشست شما منقضی شده است.")) {
                dentalState.canManage = false;
            }
            if (!payload || !payload.success || !payload.term) {
                throw new Error((payload && payload.error) || "دریافت منابع این واحد ناموفق بود.");
            }
            dentalState.unitDetail = payload.term;
            dentalState.canManage = !!payload.canManage;
            dentalState.downloadHost = payload.downloadHost || null;
            dentalApplyResourceInsights(payload);
            dentalRenderFromState();
        }).catch(function (error) {
            dentalState.downloadHost = null;
            dentalRenderError(error && error.message ? error.message : "دریافت منابع این واحد با خطا مواجه شد.");
        }).finally(function () {
            dentalState.loading = false;
        });
    }

    function dentalWatchAuthChanges() {
        var auth = authApi();
        if (!auth || typeof auth.onChange !== "function") {
            return;
        }
        auth.onChange(function () {
            var nextKey = authSnapshotKey();
            if (nextKey === dentalState.authKey) {
                return;
            }
            dentalState.authKey = nextKey;
            if (dentalRequestedUnitKey()) {
                dentalLoadUnitDetail();
                return;
            }
            dentalLoadHomeData();
        });
    }

    function bootDentalCurriculumHome() {
        if (manage) {
            manage.hidden = true;
        }
        dentalBindUnitManageForm();
        dentalBindResourceActions();
        dentalState.authKey = authSnapshotKey();
        dentalWatchAuthChanges();
        if (dentalRequestedUnitKey()) {
            dentalLoadUnitDetail();
            return;
        }
        dentalLoadHomeData();
    }

    if (pageCohort !== "prosthesis-1402") {
        bootDentalCurriculumHome();
        return;
    }

    function applyPageCopy() {
        var isProsthesis = pageCohort === "prosthesis-1402";
        var yearLabel = cohortYearLabel();
        if (heading) {
            heading.textContent = isProsthesis ? "آرشیو منابع پروتز ۱۴۰۲" : ("آرشیو منابع ورودی " + yearLabel);
        }
        if (subheading) {
            subheading.textContent = isProsthesis ? "هر ترم در صفحه جداگانه" : "هر ترم در صفحه جداگانه";
        }
        if (backLink) {
            backLink.href = "/app/";
            backLink.textContent = isProsthesis ? "بازگشت به خانه پروتز" : "بازگشت به خانه";
        }
        if (kicker) {
            kicker.textContent = isProsthesis ? "آرشیو پروتز ۱۴۰۲" : ("آرشیو " + yearLabel);
        }
        if (title) {
            title.textContent = isProsthesis
                ? "ترم موردنظر را برای دیدن جزوات و منابع انتخاب کن."
                : "ترم موردنظر را برای دیدن آرشیو منابع انتخاب کن.";
        }
        if (sectionKicker) {
            sectionKicker.textContent = "ترم‌ها";
        }
        if (sectionTitle) {
            sectionTitle.textContent = isProsthesis
                ? "صفحات مستقل ترمی پروتز ۱۴۰۲"
                : ("صفحات مستقل ترمی " + yearLabel);
        }
        if (sectionCopy) {
            sectionCopy.textContent = isProsthesis
                ? "این فهرست از storage پروتز خوانده می‌شود و مدیریت آن در همین صفحه انجام می‌شود."
                : "این فهرست مستقیم از storage مشترک خوانده می‌شود و دیگر به کارت‌های ثابت داخل HTML وابسته نیست.";
        }
        if (footer) {
            footer.textContent = isProsthesis ? "ورودی ۱۴۰۲ پروتز تهران" : ("ورودی " + yearLabel + " دندانپزشکی تهران");
        }
        document.title = isProsthesis
            ? "آرشیو جزوات پروتز ۱۴۰۲ | انتخاب ترم"
            : ("آرشیو منابع " + yearLabel + " | انتخاب ترم");
    }

    function termId(term) {
        return Number(term && (term.id || term.term) || 0);
    }

    function termUrl(term) {
        var id = termId(term);
        var site = siteApi();
        var auth = authApi();
        var base = site && typeof site.buildUrl === "function"
            ? site.buildUrl("/notes/term/", { term: String(id) })
            : "/notes/term/?term=" + encodeURIComponent(String(id));

        if (pageCohort !== "1402") {
            if (auth && typeof auth.appendCohortQuery === "function") {
                return auth.appendCohortQuery(base, pageCohort);
            }
            return base + "&cohort=" + encodeURIComponent(pageCohort);
        }

        return base;
    }

    function setFeedback(text, kind) {
        if (!feedback) {
            return;
        }
        feedback.textContent = text || "";
        feedback.dataset.kind = kind || "";
        feedback.hidden = !text;
    }

    function ensureManageToggle() {
        if (!manage || !manage.firstElementChild) {
            return null;
        }
        var existing = $("notes-home-manage-toggle");
        if (existing) {
            return existing;
        }
        var toggle = document.createElement("button");
        toggle.type = "button";
        toggle.id = "notes-home-manage-toggle";
        toggle.className = "notes-manage-panel__toggle";
        toggle.addEventListener("click", function () {
            state.manageExpanded = !state.manageExpanded;
            render();
        });
        manage.firstElementChild.appendChild(toggle);
        return toggle;
    }

    function syncManagePanel() {
        if (!manage) {
            return;
        }
        if (manage.hidden) {
            if (form) {
                form.hidden = true;
            }
            return;
        }
        var toggle = ensureManageToggle();
        var expanded = !!state.manageExpanded;
        manage.dataset.collapsed = expanded ? "false" : "true";
        if (toggle) {
            toggle.textContent = expanded ? "بستن مدیریت ترم‌ها" : "باز کردن مدیریت ترم‌ها";
            toggle.setAttribute("aria-expanded", expanded ? "true" : "false");
            toggle.setAttribute("aria-controls", "notes-home-form");
        }
        if (form) {
            form.hidden = !expanded;
        }
    }

    function inputs() {
        return {
            title: $("notes-home-term-title"),
            kicker: $("notes-home-term-kicker"),
            description: $("notes-home-term-description"),
            emptyMessage: $("notes-home-term-empty-message")
        };
    }

    function clearForm() {
        var nodes = inputs();
        Object.keys(nodes).forEach(function (key) {
            if (nodes[key]) {
                nodes[key].value = "";
            }
        });
    }

    function fillForm(term) {
        var nodes = inputs();
        if (nodes.title) nodes.title.value = term.title || "";
        if (nodes.kicker) nodes.kicker.value = term.kicker || "";
        if (nodes.description) nodes.description.value = term.description || "";
        if (nodes.emptyMessage) nodes.emptyMessage.value = term.emptyMessage || "";
    }

    function readPayload() {
        var nodes = inputs();
        return {
            title: nodes.title ? String(nodes.title.value || "").trim() : "",
            kicker: nodes.kicker ? String(nodes.kicker.value || "").trim() : "",
            description: nodes.description ? String(nodes.description.value || "").trim() : "",
            emptyMessage: nodes.emptyMessage ? String(nodes.emptyMessage.value || "").trim() : ""
        };
    }

    function findTerm(termIdValue) {
        return state.terms.filter(function (term) {
            return termId(term) === Number(termIdValue || 0);
        })[0] || null;
    }

    function setEditing(term) {
        state.editingTermId = term ? termId(term) : 0;
        if (!term) {
            clearForm();
            setFeedback("", "");
            render();
            return;
        }

        fillForm(term);
        state.manageExpanded = true;
        setFeedback("ترم برای ویرایش آماده شد.", "success");
        render();
        if (manage && typeof manage.scrollIntoView === "function") {
            manage.scrollIntoView({ behavior: "smooth", block: "start" });
        }
    }

    function createChevron() {
        var chevron = document.createElement("span");
        chevron.className = "action-card__chevron";
        chevron.setAttribute("aria-hidden", "true");
        return chevron;
    }

    function createPrimaryLink(term) {
        var link = document.createElement("a");
        link.className = "action-card__primary";
        link.href = termUrl(term);

        var content = document.createElement("span");
        content.className = "card-content";

        var header = document.createElement("span");
        header.className = "card-header";

        var badge = document.createElement("span");
        badge.className = "card-badge";
        badge.textContent = term.kicker || "ترم";

        var titleNode = document.createElement("span");
        titleNode.className = "card-title";
        titleNode.textContent = term.title || "ترم بدون عنوان";

        var desc = document.createElement("span");
        desc.className = "card-desc";
        desc.textContent = term.description || "";

        var visual = document.createElement("span");
        visual.className = "action-card__visual";
        visual.setAttribute("aria-hidden", "true");
        visual.innerHTML = "<strong>" + toFaDigits(termId(term) || "?") + "</strong>";

        header.appendChild(badge);
        header.appendChild(titleNode);
        content.appendChild(header);
        content.appendChild(desc);

        link.appendChild(createChevron());
        link.appendChild(content);
        link.appendChild(visual);
        return link;
    }

    function buildCard(term) {
        if (!homeCanManage()) {
            var publicCard = createPrimaryLink(term);
            publicCard.classList.add("action-card", "action-card--link");
            return publicCard;
        }

        var id = termId(term);
        var card = document.createElement("article");
        card.className = "action-card";
        card.appendChild(createPrimaryLink(term));

        var actions = document.createElement("div");
        actions.className = "notes-card-actions";

        var open = document.createElement("a");
        open.className = "card-btn";
        open.href = termUrl(term);
        open.textContent = "ورود به صفحه";
        actions.appendChild(open);

        var edit = document.createElement("button");
        edit.type = "button";
        edit.className = "notes-card-edit";
        edit.dataset.termEdit = "true";
        edit.dataset.termId = String(id);
        edit.textContent = state.editingTermId === id ? "در حال ویرایش" : "ویرایش";
        edit.disabled = state.saving || state.deletingTermId > 0;
        actions.appendChild(edit);

        var remove = document.createElement("button");
        remove.type = "button";
        remove.className = "notes-card-delete";
        remove.dataset.termDelete = "true";
        remove.dataset.termId = String(id);
        remove.textContent = state.deletingTermId === id ? "در حال حذف..." : "حذف";
        remove.disabled = state.saving || state.deletingTermId === id;
        actions.appendChild(remove);

        card.appendChild(actions);
        return card;
    }

    function render() {
        applyPageCopy();
        list.innerHTML = "";
        var fragment = document.createDocumentFragment();

        if (manage) {
            manage.hidden = !homeCanManage();
        }
        syncManagePanel();
        if (submit) {
            submit.disabled = state.saving;
            submit.textContent = state.saving
                ? "در حال ذخیره..."
                : (state.editingTermId ? "ذخیره تغییرات ترم" : "افزودن ترم");
        }

        if (!state.terms.length) {
            empty.hidden = false;
            if (state.loading) {
                empty.textContent = "در حال دریافت ترم‌ها...";
                return;
            }
            if (state.loadError) {
                empty.textContent = state.loadError;
                return;
            }
            empty.textContent = pageCohort === "prosthesis-1402"
                ? "هنوز ترمی برای پروتز ۱۴۰۲ ثبت نشده است."
                : "هنوز ترمی برای این آرشیو ثبت نشده است.";
            return;
        }

        empty.hidden = true;
        state.terms.forEach(function (term) {
            fragment.appendChild(buildCard(term));
        });
        list.appendChild(fragment);
    }

    function loadTerms(options) {
        if (state.loading) {
            return Promise.resolve();
        }

        state.loading = true;
        state.loadError = "";
        if (!(options && options.silent)) {
            render();
        }

        return request("terms", "GET", {}).then(function (payload) {
            if (consumeUnauthorized(payload, "نشست شما منقضی شده است.")) {
                state.canManage = false;
                render();
                return;
            }
            if (!payload || !payload.success) {
                throw new Error((payload && payload.error) || "دریافت ترم‌ها ناموفق بود.");
            }

            state.terms = Array.isArray(payload.terms) ? payload.terms : [];
            state.canManage = manageSupported() && !!payload.canManage;
            if (state.editingTermId && !findTerm(state.editingTermId)) {
                state.editingTermId = 0;
                clearForm();
            }
            render();
        }).catch(function (error) {
            state.loadError = error && error.message ? error.message : "دریافت ترم‌ها با خطا مواجه شد.";
            state.canManage = false;
            if (manage) {
                manage.hidden = true;
            }
        }).finally(function () {
            state.loading = false;
            render();
        });
    }

    function saveTerm(event) {
        event.preventDefault();
        if (!homeCanManage() || state.saving) {
            return;
        }

        var payload = readPayload();
        if (!payload.title) {
            state.manageExpanded = true;
            syncManagePanel();
            setFeedback("عنوان ترم را وارد کن.", "error");
            return;
        }

        state.saving = true;
        state.manageExpanded = true;
        render();

        var editingId = state.editingTermId;
        var action = editingId ? "editTerm" : "addTerm";
        if (editingId) {
            payload.term = String(editingId);
        }

        request(action, "POST", payload).then(function (response) {
            if (consumeUnauthorized(response, "برای مدیریت این آرشیو باید وارد حساب مجاز شوید.")) {
                throw new Error("برای مدیریت این آرشیو باید وارد حساب مجاز شوید.");
            }
            if (!response || !response.success) {
                throw new Error((response && response.error) || "ذخیره ترم انجام نشد.");
            }

            state.editingTermId = 0;
            state.manageExpanded = true;
            clearForm();
            setFeedback(editingId ? "ترم ویرایش شد." : "ترم جدید اضافه شد.", "success");
            return loadTerms({ silent: true });
        }).catch(function (error) {
            state.manageExpanded = true;
            setFeedback(error && error.message ? error.message : "ذخیره ترم انجام نشد.", "error");
        }).finally(function () {
            state.saving = false;
            render();
        });
    }

    function deleteTerm(termValue) {
        if (!homeCanManage() || state.deletingTermId > 0) {
            return;
        }

        var id = Number(termValue || 0);
        var term = findTerm(id);
        if (!term) {
            return;
        }
        if (!window.confirm("این ترم حذف شود؟")) {
            return;
        }

        state.deletingTermId = id;
        state.manageExpanded = true;
        render();

        request("deleteTerm", "POST", { term: String(id) }).then(function (response) {
            if (consumeUnauthorized(response, "برای مدیریت این آرشیو باید وارد حساب مجاز شوید.")) {
                throw new Error("برای مدیریت این آرشیو باید وارد حساب مجاز شوید.");
            }
            if (!response || !response.success) {
                throw new Error((response && response.error) || "حذف ترم انجام نشد.");
            }

            if (state.editingTermId === id) {
                state.editingTermId = 0;
                clearForm();
            }
            state.manageExpanded = true;
            setFeedback("ترم حذف شد.", "success");
            return loadTerms({ silent: true });
        }).catch(function (error) {
            state.manageExpanded = true;
            setFeedback(error && error.message ? error.message : "حذف ترم انجام نشد.", "error");
        }).finally(function () {
            state.deletingTermId = 0;
            render();
        });
    }

    function bindForm() {
        if (!form) {
            return;
        }
        form.addEventListener("submit", saveTerm);
    }

    function bindList() {
        list.addEventListener("click", function (event) {
            var editButton = event.target.closest("[data-term-edit]");
            if (editButton) {
                event.preventDefault();
                if (!homeCanManage()) {
                    return;
                }
                setEditing(findTerm(Number(editButton.getAttribute("data-term-id") || 0)));
                return;
            }

            var deleteButton = event.target.closest("[data-term-delete]");
            if (!deleteButton) {
                return;
            }

            event.preventDefault();
            if (!homeCanManage()) {
                return;
            }
            deleteTerm(Number(deleteButton.getAttribute("data-term-id") || 0));
        });
    }

    function watchAuthChanges() {
        var auth = authApi();
        if (!auth || typeof auth.onChange !== "function") {
            return;
        }

        auth.onChange(function () {
            var nextKey = authSnapshotKey();
            if (nextKey === state.authKey) {
                return;
            }
            state.authKey = nextKey;
            loadTerms({ silent: true });
        });
    }

    function boot() {
        state.authKey = authSnapshotKey();
        bindForm();
        bindList();
        watchAuthChanges();
        loadTerms({ silent: false });
    }

    boot();
})();
