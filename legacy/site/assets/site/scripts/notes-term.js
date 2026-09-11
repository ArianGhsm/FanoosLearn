(function () {
    "use strict";

    function $(id) {
        return document.getElementById(id);
    }

    var cardsContainer = $("notes-term-cards");
    var emptyBox = $("notes-term-empty");
    var termTitle = $("notes-term-title");
    var termDescription = $("notes-term-description");
    var managePanel = $("notes-term-manage");
    var manageForm = $("notes-term-form");
    var manageFeedback = $("notes-term-feedback");
    var addSubmit = $("notes-term-submit");
    var backLink = $("notes-term-back-link");
    var heroKicker = document.querySelector(".hero-content .greeting-pill");
    var sectionLead = document.querySelector("#notes-term-section .archive-term__head p");
    var manageHeading = managePanel ? managePanel.querySelector(".notes-manage-panel__head h4") : null;
    var manageLead = managePanel ? managePanel.querySelector(".notes-manage-panel__head p") : null;

    if (!cardsContainer || !emptyBox) {
        return;
    }

    var searchParams = new URLSearchParams(window.location.search || "");
    var authApi = window.Dent1402Auth && typeof window.Dent1402Auth === "object" ? window.Dent1402Auth : null;
    var siteApi = window.Dent1402Site && typeof window.Dent1402Site === "object" ? window.Dent1402Site : null;

    function normalizeNotesCohort(value) {
        var clean = String(value == null ? "" : value).trim();
        if (!clean || clean === "main" || clean === "1402" || clean === "dentistry-1402") {
            return "1402";
        }
        if (clean === "1403" || clean === "dentistry-1403") {
            return "1403";
        }
        if (clean === "1404" || clean === "dentistry-1404") {
            return "1404";
        }
        if (clean === "prosthesis-1402") {
            return "prosthesis-1402";
        }
        return clean;
    }

    function isValidTermForCohort(cohortKey, value) {
        if (!Number.isFinite(value)) {
            return false;
        }
        if (cohortKey === "1402") {
            return value >= 4 && value <= 12;
        }
        if (cohortKey === "1403") {
            return value >= 3 && value <= 12;
        }
        if (cohortKey === "1404") {
            return value >= 1 && value <= 12;
        }
        if (cohortKey === "prosthesis-1402") {
            return value > 0;
        }
        return false;
    }

    var cohort = authApi && typeof authApi.resolvePageCohort === "function"
        ? authApi.resolvePageCohort("notesCohort")
        : String(document.body.dataset.notesCohort || searchParams.get("cohort") || "1402");
    cohort = normalizeNotesCohort(cohort);
    var rawTerm = String(document.body.dataset.termNumber || searchParams.get("term") || "");
    var term = Number(rawTerm || "0");
    var requestedUnitKey = String(searchParams.get("unit") || "").trim().toLowerCase();
    var manageRequested = /^(1|true|open)$/i.test(String(searchParams.get("manage") || searchParams.get("add") || "").trim());
    if (["1402", "1403", "1404", "prosthesis-1402"].indexOf(cohort) === -1) {
        return;
    }
    if (!isValidTermForCohort(cohort, term)) {
        return;
    }

    var state = {
        termData: null,
        canManage: false,
        loadError: "",
        loading: false,
        manageExpanded: false,
        manageInitialApplied: false,
        manageFocusPending: false,
        saving: false,
        deletingItemId: 0,
        editingItemId: 0,
        authKey: "",
        downloadHost: null,
        resourceBusy: false,
        resourceFeedback: "",
        resourceInsights: null,
        offlinePackBusy: false,
        offlinePackFeedback: "",
        offlinePackRecord: null,
        uploadBusy: false
    };

    function isCurriculumCohort() {
        return cohort === "1402" || cohort === "1403" || cohort === "1404";
    }

    function isUnitMode() {
        return isCurriculumCohort() && requestedUnitKey !== "";
    }

    function toFaDigits(value) {
        return String(value == null ? "" : value).replace(/\d/g, function (digit) {
            return ["۰", "۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹"][Number(digit)] || digit;
        });
    }

    function cohortHomePath() {
        if (cohort === "1403") {
            return "/notes/1403/";
        }
        if (cohort === "1404") {
            return "/notes/1404/";
        }
        return "/notes/";
    }

    function buildContextUrl(pathname, termValue, unitKey) {
        var url = new URL(pathname, window.location.origin);
        if (Number(termValue || 0) > 0) {
            url.searchParams.set("term", String(termValue));
        }
        if (unitKey) {
            url.searchParams.set("unit", unitKey);
        }
        if (cohort !== "1402") {
            url.searchParams.set("cohort", cohort);
        }
        return url.pathname + (url.search || "");
    }

    function homeUrl(termValue, unitKey) {
        return buildContextUrl(cohortHomePath(), termValue, unitKey);
    }

    function resolveItemStorageTerm(item) {
        var value = Number(item && item.storageTerm ? item.storageTerm : term);
        if (!Number.isFinite(value) || value <= 0) {
            return term;
        }
        return value;
    }

    function applyPagePresentation(termData) {
        var data = termData && typeof termData === "object" ? termData : {};
        var mode = String(data.mode || "");
        var displayTerm = Number(data.termNumber || data.term || term || 0);
        var displayTermLabel = data.termLabel || ("ترم " + toFaDigits(displayTerm));
        var isCurriculumUnit = mode === "curriculum-unit" || isUnitMode();
        var isLegacyTerm = isCurriculumCohort() && !isCurriculumUnit && displayTerm > 0 && displayTerm < 4;

        if (document.body) {
            if (isCurriculumCohort()) {
                document.body.classList.add("notes-curriculum-page");
            }
            document.body.dataset.notesView = isCurriculumUnit ? "unit" : (isLegacyTerm ? "legacy-term" : "term");
        }

        if (heroKicker) {
            heroKicker.textContent = isCurriculumUnit
                ? (data.categoryTitle || displayTermLabel)
                : (data.kicker || displayTermLabel);
        }

        if (sectionLead) {
            if (isCurriculumUnit) {
                sectionLead.textContent = "منابع این واحد در همین صفحه نمایش داده می‌شوند و اگر کارت قدیمی‌تری هم به این درس تعلق داشته باشد، باز هم اینجا دیده می‌شود.";
            } else if (isLegacyTerm) {
                sectionLead.textContent = "این آرشیو هنوز خارج از ساختار اصلی ترم‌های ۴ تا ۱۲ نگه‌داری می‌شود تا هیچ منبع فعلی از دسترس خارج نشود.";
            } else if (isCurriculumCohort()) {
                sectionLead.textContent = "منابع این ترم از دل ساختار دانشکده نمایش داده می‌شوند و از همین صفحه هم قابل مدیریت هستند.";
            } else {
                sectionLead.textContent = "کارت‌های این ترم از سرور بارگذاری می‌شوند و مدیریت آن‌ها فقط از طریق پنل همین صفحه انجام می‌شود.";
            }
        }

        if (manageHeading) {
            manageHeading.textContent = isCurriculumUnit
                ? "مدیریت منابع این واحد"
                : "مدیریت کارت‌های این ترم";
        }
        if (manageLead) {
            manageLead.textContent = isCurriculumUnit
                ? "کارت جدیدی که اینجا ثبت شود، به همین واحد وصل می‌شود و در صفحه عمومی همین درس نمایش داده خواهد شد."
                : "مالک یا مدیر مجاز می‌تواند کارت جدید اضافه کند، کارت‌های موجود را ویرایش کند یا حذف کند.";
        }

        if (backLink) {
            if (isCurriculumCohort()) {
                backLink.href = isCurriculumUnit ? homeUrl(displayTerm, "") : homeUrl(0, "");
                backLink.textContent = isCurriculumUnit
                    ? ("بازگشت به " + displayTermLabel)
                    : "بازگشت به همه ترم‌ها";
            } else if (cohort !== "1402") {
                if (authApi && typeof authApi.appendCohortQuery === "function") {
                    backLink.href = authApi.appendCohortQuery("/notes/", cohort);
                } else {
                    backLink.href = "/notes/?cohort=" + encodeURIComponent(cohort);
                }
            }
        }

        document.title = (data.title || displayTermLabel) + " | آرشیو منابع";
    }

    function authSnapshotKey() {
        var auth = window.Dent1402Auth;
        if (!auth || typeof auth.getState !== "function") {
            return "anon";
        }

        var snapshot = auth.getState();
        var studentNumber = snapshot && snapshot.user && snapshot.user.studentNumber ? String(snapshot.user.studentNumber) : "";
        return (snapshot && snapshot.status ? snapshot.status : "unknown") + ":" + studentNumber;
    }

    function currentViewer() {
        if (!authApi || typeof authApi.getState !== "function") {
            return null;
        }
        var snapshot = authApi.getState();
        return snapshot && snapshot.user ? snapshot.user : null;
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
        return toFaDigits(Math.max(0, Math.min(100, Math.round(number))));
    }

    function formatDate(value) {
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

    function applyResourceInsights(payload) {
        if (payload && payload.resourceInsights) {
            state.resourceInsights = payload.resourceInsights;
        }
    }

    function resourceInsights() {
        return state.resourceInsights && typeof state.resourceInsights === "object"
            ? state.resourceInsights
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

    function isResourceFavorite(item) {
        var itemId = Number(item && item.id || 0);
        var ids = Array.isArray(resourceInsights().favoriteIds) ? resourceInsights().favoriteIds : [];
        return ids.some(function (id) {
            return Number(id || 0) === itemId;
        });
    }

    function setResourceFeedback(text) {
        state.resourceFeedback = String(text || "");
        var node = $("notes-term-resource-feedback");
        if (node) {
            node.textContent = state.resourceFeedback;
            node.hidden = !state.resourceFeedback;
        }
    }

    function resourceItemTerm(item) {
        return Number(item && (item.storageTerm || item.term) || term || 0);
    }

    function offlineApi() {
        return siteApi && siteApi.offline && typeof siteApi.offline === "object" ? siteApi.offline : null;
    }

    function offlinePackKey() {
        return ["notes", cohort, String(term || 0), requestedUnitKey || "term"].join(":");
    }

    function refreshOfflinePackRecord() {
        var offline = offlineApi();
        state.offlinePackRecord = offline && typeof offline.getPack === "function"
            ? offline.getPack(offlinePackKey())
            : null;
        return state.offlinePackRecord;
    }

    function currentOfflinePackResources() {
        var items = state.termData && Array.isArray(state.termData.items) ? state.termData.items : [];
        var seen = {};
        return items.map(function (item) {
            var url = String(item && item.offlinePackUrl || "").trim();
            if (!url || seen[url]) {
                return null;
            }
            seen[url] = true;
            return {
                url: url,
                title: String(item.title || item.badge || "منبع"),
                sourceUrl: String(item.buttonUrl || "")
            };
        }).filter(Boolean);
    }

    function offlinePackTitle() {
        var data = state.termData && typeof state.termData === "object" ? state.termData : {};
        return data.title || termTitle && termTitle.textContent || "بسته منابع";
    }

    function setOfflinePackFeedback(text) {
        state.offlinePackFeedback = String(text || "");
        var node = $("notes-term-offline-pack-feedback");
        if (node) {
            node.textContent = state.offlinePackFeedback;
            node.hidden = !state.offlinePackFeedback;
        }
    }

    function saveOfflineResourcePack() {
        var offline = offlineApi();
        if (!offline || typeof offline.savePack !== "function") {
            setOfflinePackFeedback("ذخیره آفلاین در این مرورگر پشتیبانی نمی‌شود.");
            return;
        }
        var resources = currentOfflinePackResources();
        if (!resources.length) {
            setOfflinePackFeedback("برای این صفحه منبع قابل ذخیره آفلاین پیدا نشد.");
            return;
        }
        if (state.offlinePackBusy) {
            return;
        }

        state.offlinePackBusy = true;
        state.offlinePackFeedback = "در حال ذخیره بسته منابع...";
        renderTerm();
        offline.savePack({
            key: offlinePackKey(),
            title: offlinePackTitle(),
            pageUrl: window.location.pathname + window.location.search,
            resources: resources
        }).then(function (result) {
            state.offlinePackRecord = result && result.pack ? result.pack : refreshOfflinePackRecord();
            var cachedCount = Number(result && result.cachedCount || state.offlinePackRecord && state.offlinePackRecord.cachedCount || 0);
            var failedCount = Number(result && result.failedCount || 0);
            state.offlinePackFeedback = failedCount > 0
                ? "بسته ذخیره شد، اما " + toFaDigits(failedCount) + " فایل دریافت نشد."
                : "بسته منابع با " + toFaDigits(cachedCount) + " فایل برای مطالعه آفلاین ذخیره شد.";
        }).catch(function (error) {
            state.offlinePackFeedback = error && error.message ? error.message : "ذخیره بسته آفلاین انجام نشد.";
        }).finally(function () {
            state.offlinePackBusy = false;
            renderTerm();
        });
    }

    function removeOfflineResourcePack() {
        var offline = offlineApi();
        if (!offline || typeof offline.removePack !== "function") {
            setOfflinePackFeedback("مدیریت بسته آفلاین در این مرورگر پشتیبانی نمی‌شود.");
            return;
        }
        if (state.offlinePackBusy) {
            return;
        }

        state.offlinePackBusy = true;
        state.offlinePackFeedback = "در حال حذف بسته آفلاین...";
        renderTerm();
        offline.removePack(offlinePackKey()).then(function () {
            state.offlinePackRecord = null;
            state.offlinePackFeedback = "بسته آفلاین این صفحه حذف شد.";
        }).catch(function (error) {
            state.offlinePackFeedback = error && error.message ? error.message : "حذف بسته آفلاین انجام نشد.";
        }).finally(function () {
            state.offlinePackBusy = false;
            renderTerm();
        });
    }

    function openOfflineResourceFromItem(item) {
        var offline = offlineApi();
        var url = String(item && item.offlinePackUrl || "").trim();
        if (!offline || typeof offline.openCachedResource !== "function" || !url) {
            setResourceFeedback("این منبع در بسته آفلاین ذخیره نشده است.");
            return;
        }

        offline.openCachedResource(url).then(function (opened) {
            if (!opened) {
                setResourceFeedback("این منبع هنوز در بسته آفلاین ذخیره نشده است.");
                return;
            }
            setResourceFeedback("");
        }).catch(function () {
            setResourceFeedback("بازکردن نسخه آفلاین این منبع انجام نشد.");
        });
    }

    function createMiniList(titleText, items, emptyText) {
        var group = document.createElement("div");
        group.className = "notes-resource-mini-list";
        var heading = document.createElement("h4");
        heading.className = "notes-resource-mini-list__title";
        heading.textContent = titleText;
        group.appendChild(heading);

        var list = document.createElement("div");
        list.className = "notes-resource-mini-list__items";
        var rows = Array.isArray(items) ? items.slice(0, 3) : [];
        if (!rows.length) {
            var empty = document.createElement("p");
            empty.className = "notes-resource-mini-list__empty";
            empty.textContent = emptyText;
            list.appendChild(empty);
        } else {
            rows.forEach(function (item) {
                var link = document.createElement("a");
                link.className = "notes-resource-mini-list__item";
                link.href = item.viewUrl || item.buttonUrl || "#";
                link.textContent = item.title || item.itemTitle || "منبع";
                list.appendChild(link);
            });
        }
        group.appendChild(list);
        return group;
    }

    function createOwnerInbox(insights) {
        var inbox = insights && insights.inbox ? insights.inbox : null;
        var shell = document.createElement("div");
        shell.className = "notes-resource-inbox";
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
        entries.slice(0, 4).forEach(function (entry) {
            var row = document.createElement("div");
            row.className = "notes-resource-inbox__row";
            var text = document.createElement("span");
            text.className = "notes-resource-inbox__text";
            text.textContent = entry.title + " • " + entry.meta;
            var button = document.createElement("button");
            button.type = "button";
            button.className = "notes-resource-action notes-resource-action--muted";
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

    function createOfflinePackPanel() {
        var offline = offlineApi();
        var record = refreshOfflinePackRecord();
        var resources = currentOfflinePackResources();
        var supports = !!(offline && typeof offline.savePack === "function" && typeof offline.removePack === "function");
        var shell = document.createElement("div");
        shell.className = "notes-resource-pack";

        var text = document.createElement("div");
        text.className = "notes-resource-pack__text";
        var title = document.createElement("h4");
        title.className = "notes-resource-pack__title";
        title.textContent = "بسته آفلاین منابع";
        var meta = document.createElement("p");
        meta.className = "notes-resource-pack__meta";
        if (!supports) {
            meta.textContent = "مرورگر فعلی از ذخیره بسته آفلاین پشتیبانی نمی‌کند.";
        } else if (record) {
            var cachedCount = Number(record.cachedCount || (Array.isArray(record.resources) ? record.resources.length : 0));
            var updatedAt = formatDate(record.updatedAt);
            meta.textContent = "ذخیره‌شده: " + toFaDigits(cachedCount) + " فایل" + (updatedAt ? "، آخرین به‌روزرسانی " + updatedAt : "");
        } else {
            meta.textContent = "برای مطالعه بدون اینترنت، فایل‌های این صفحه را در cache مرورگر ذخیره کن.";
        }
        text.appendChild(title);
        text.appendChild(meta);

        var feedback = document.createElement("p");
        feedback.id = "notes-term-offline-pack-feedback";
        feedback.className = "notes-manage-feedback notes-resource-pack__feedback";
        feedback.textContent = state.offlinePackFeedback;
        feedback.hidden = !state.offlinePackFeedback;
        text.appendChild(feedback);

        var actions = document.createElement("div");
        actions.className = "notes-resource-pack__actions";
        var saveButton = document.createElement("button");
        saveButton.type = "button";
        saveButton.className = "notes-link-btn";
        saveButton.dataset.resourcePackSave = "true";
        saveButton.textContent = state.offlinePackBusy ? "در حال ذخیره..." : (record ? "به‌روزرسانی بسته" : "ذخیره آفلاین");
        saveButton.disabled = state.offlinePackBusy || !supports || !resources.length;
        actions.appendChild(saveButton);

        if (record) {
            var removeButton = document.createElement("button");
            removeButton.type = "button";
            removeButton.className = "notes-link-btn notes-link-btn--muted";
            removeButton.dataset.resourcePackRemove = "true";
            removeButton.textContent = state.offlinePackBusy ? "در حال حذف..." : "حذف بسته";
            removeButton.disabled = state.offlinePackBusy || !supports;
            actions.appendChild(removeButton);
        }

        shell.appendChild(text);
        shell.appendChild(actions);
        return shell;
    }

    function ensureResourceInsightsPanel() {
        var existing = $("notes-term-resource-insights");
        if (existing) {
            return existing;
        }
        var panel = document.createElement("section");
        panel.id = "notes-term-resource-insights";
        panel.className = "notes-resource-insights";
        var section = $("notes-term-section");
        if (section && cardsContainer) {
            section.insertBefore(panel, cardsContainer);
        }
        return panel;
    }

    function renderResourceInsightsPanel() {
        var panel = ensureResourceInsightsPanel();
        if (!panel) {
            return;
        }
        panel.innerHTML = "";
        var insights = resourceInsights();
        var head = document.createElement("div");
        head.className = "notes-resource-insights__head";
        var kicker = document.createElement("span");
        kicker.className = "archive-term__kicker";
        kicker.textContent = "منابع من";
        var heading = document.createElement("h3");
        heading.textContent = "پیگیری منابع";
        var feedback = document.createElement("p");
        feedback.id = "notes-term-resource-feedback";
        feedback.className = "notes-manage-feedback";
        feedback.textContent = state.resourceFeedback;
        feedback.hidden = !state.resourceFeedback;
        head.appendChild(kicker);
        head.appendChild(heading);
        head.appendChild(feedback);
        panel.appendChild(head);

        var grid = document.createElement("div");
        grid.className = "notes-resource-insights__grid";
        grid.appendChild(createMiniList(
            "علاقه‌مندی‌ها",
            insights.authenticated ? insights.favorites : [],
            insights.authenticated ? "هنوز منبعی ذخیره نشده است." : "برای ذخیره علاقه‌مندی وارد حساب شو."
        ));
        grid.appendChild(createMiniList(
            "اخیراً دیده‌شده",
            insights.authenticated ? insights.recent : [],
            insights.authenticated ? "هنوز منبعی باز نکرده‌ای." : "بعد از ورود، بازدیدهای اخیر اینجا می‌آید."
        ));
        grid.appendChild(createMiniList("آخرین آپدیت‌ها", insights.latestUpdates, "آپدیت تازه‌ای ثبت نشده است."));
        panel.appendChild(grid);
        panel.appendChild(createOfflinePackPanel());

        if (state.canManage && insights.inbox) {
            var ownerLine = document.createElement("div");
            ownerLine.className = "notes-resource-owner-line";
            var pending = insights.pending || {};
            var requestChip = document.createElement("span");
            requestChip.className = "notes-chip notes-chip--soft";
            requestChip.textContent = "درخواست باز: " + toFaDigits(pending.requests || 0);
            var reportChip = document.createElement("span");
            reportChip.className = "notes-chip notes-chip--soft";
            reportChip.textContent = "گزارش لینک: " + toFaDigits(pending.reports || 0);
            ownerLine.appendChild(requestChip);
            ownerLine.appendChild(reportChip);
            panel.appendChild(ownerLine);
            panel.appendChild(createOwnerInbox(insights));
        }

        var requestForm = document.createElement("form");
        requestForm.className = "notes-resource-request";
        requestForm.dataset.resourceRequestForm = "true";
        requestForm.dataset.term = String(term || 0);
        requestForm.dataset.unitKey = requestedUnitKey || "";
        var requestTitle = document.createElement("h4");
        requestTitle.className = "notes-resource-request__title";
        requestTitle.textContent = "درخواست منبع";
        var input = document.createElement("input");
        input.className = "notes-resource-request__input";
        input.name = "title";
        input.type = "text";
        input.maxLength = 180;
        input.placeholder = "عنوان منبع موردنیاز";
        var textarea = document.createElement("textarea");
        textarea.className = "notes-resource-request__input";
        textarea.name = "note";
        textarea.maxLength = 800;
        textarea.placeholder = "توضیح کوتاه";
        var submit = document.createElement("button");
        submit.className = "notes-link-btn";
        submit.type = "submit";
        submit.textContent = state.resourceBusy ? "در حال ثبت..." : "ثبت درخواست";
        submit.disabled = !!state.resourceBusy;
        requestForm.appendChild(requestTitle);
        requestForm.appendChild(input);
        requestForm.appendChild(textarea);
        requestForm.appendChild(submit);
        panel.appendChild(requestForm);
    }

    function isOffline() {
        return typeof navigator !== "undefined" && navigator && navigator.onLine === false;
    }

    function withContextPayload(payload) {
        var next = Object.assign({ cohort: cohort }, payload || {});
        if (cohort === "1402" || cohort === "1403" || cohort === "1404" || cohort === "prosthesis-1402") {
            var payloadTerm = Number(next.term || term);
            next.term = String(Number.isFinite(payloadTerm) && payloadTerm > 0 ? payloadTerm : term);
        }
        if (isCurriculumCohort() && requestedUnitKey) {
            if (next.unit === undefined) {
                next.unit = requestedUnitKey;
            }
            if (next.unitKey === undefined) {
                next.unitKey = requestedUnitKey;
            }
        }
        return next;
    }

    function request(action, method, payload) {
        var options = {
            method: method,
            credentials: "same-origin",
            headers: {
                Accept: "application/json"
            }
        };
        var requestPayload = withContextPayload(payload);
        var url = "/api/notes_api.php?action=" + encodeURIComponent(action);

        if (method === "GET") {
            Object.keys(requestPayload).forEach(function (key) {
                var value = requestPayload[key];
                if (value !== undefined && value !== null && String(value) !== "") {
                    url += "&" + encodeURIComponent(key) + "=" + encodeURIComponent(String(value));
                }
            });
        } else {
            options.headers["Content-Type"] = "application/x-www-form-urlencoded; charset=UTF-8";
            options.body = new URLSearchParams(Object.assign({ action: action }, requestPayload));
        }

        return fetch(url, options).then(parseJsonResponse).catch(networkErrorResponse);
    }

    function requestFormData(action, formData) {
        var url = "/api/notes_api.php?action=" + encodeURIComponent(action);
        return fetch(url, {
            method: "POST",
            credentials: "same-origin",
            body: formData,
            headers: {
                Accept: "application/json"
            }
        }).then(parseJsonResponse).catch(networkErrorResponse);
    }

    function setFeedback(text, kind) {
        if (!manageFeedback) {
            return;
        }

        manageFeedback.textContent = text || "";
        manageFeedback.dataset.kind = kind || "";
        manageFeedback.hidden = !text;
    }

    function ensureManageToggle() {
        if (!managePanel || !managePanel.firstElementChild) {
            return null;
        }
        var existing = $("notes-term-manage-toggle");
        if (existing) {
            return existing;
        }
        var toggle = document.createElement("button");
        toggle.type = "button";
        toggle.id = "notes-term-manage-toggle";
        toggle.className = "notes-manage-panel__toggle";
        toggle.addEventListener("click", function () {
            state.manageExpanded = !state.manageExpanded;
            renderTerm();
        });
        managePanel.firstElementChild.appendChild(toggle);
        return toggle;
    }

    function syncManagePanel() {
        if (!managePanel) {
            return;
        }
        if (managePanel.hidden) {
            if (manageForm) {
                manageForm.hidden = true;
            }
            return;
        }
        var toggle = ensureManageToggle();
        var expanded = !!state.manageExpanded;
        managePanel.dataset.collapsed = expanded ? "false" : "true";
        if (toggle) {
            toggle.textContent = expanded ? "بستن مدیریت منابع" : "باز کردن مدیریت منابع";
            toggle.setAttribute("aria-expanded", expanded ? "true" : "false");
            toggle.setAttribute("aria-controls", "notes-term-form");
        }
        if (manageForm) {
            manageForm.hidden = !expanded;
        }
    }

    function focusManagePanelIfNeeded() {
        if (!state.manageFocusPending || !managePanel || managePanel.hidden || !state.canManage) {
            return;
        }

        state.manageFocusPending = false;
        if (typeof managePanel.scrollIntoView === "function") {
            managePanel.scrollIntoView({ behavior: "smooth", block: "start" });
        }

        var inputs = formInputs();
        var focusTarget = inputs.title || inputs.badge || null;
        if (focusTarget && typeof focusTarget.focus === "function") {
            focusTarget.focus();
        }
    }

    function clearCards() {
        while (cardsContainer.firstChild) {
            cardsContainer.removeChild(cardsContainer.firstChild);
        }
    }

    function findItem(itemId) {
        var items = state.termData && Array.isArray(state.termData.items) ? state.termData.items : [];
        for (var index = 0; index < items.length; index += 1) {
            if (Number(items[index].id || 0) === Number(itemId || 0)) {
                return items[index];
            }
        }
        return null;
    }

    function createChevron() {
        var chevron = document.createElement("span");
        chevron.className = "action-card__chevron";
        chevron.setAttribute("aria-hidden", "true");
        return chevron;
    }

    function createVisual(kind) {
        var visual = document.createElement("span");
        visual.className = "action-card__visual";
        visual.setAttribute("aria-hidden", "true");
        if (kind === "link") {
            visual.innerHTML = '<svg viewBox="0 0 24 24" fill="none"><path d="M9.4 14.6L14.6 9.4M10.4 7.2H16.8V13.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 6H7.4A2.4 2.4 0 0 0 5 8.4V16.6A2.4 2.4 0 0 0 7.4 19H15.6A2.4 2.4 0 0 0 18 16.6V16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            return visual;
        }
        visual.innerHTML = '<svg viewBox="0 0 24 24" fill="none"><path d="M7 4.8H13.1L17.5 9.1V18A2.2 2.2 0 0 1 15.3 20.2H8.7A2.2 2.2 0 0 1 6.5 18V7A2.2 2.2 0 0 1 8.7 4.8Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M13 4.8V9.2H17.4" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9.4 12.4H14.8M9.4 15.6H13.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
        return visual;
    }

    function createPrimaryLink(item) {
        var link = document.createElement("a");
        link.className = "action-card__primary";
        link.href = item.buttonUrl || "#";
        link.dataset.analyticsDownload = "notes-resource";
        link.dataset.analyticsLabel = item.title || item.badge || "منبع";
        link.dataset.resourceOpen = "true";
        link.dataset.itemId = String(item.id || "");
        link.dataset.term = String(resourceItemTerm(item));
        if (item.offlinePackUrl) {
            link.dataset.offlinePackUrl = String(item.offlinePackUrl || "");
        }
        if (item.isExternal) {
            link.dataset.externalLink = "true";
            link.target = "_blank";
            link.rel = "noopener noreferrer";
        }

        var content = document.createElement("span");
        content.className = "card-content";

        var header = document.createElement("span");
        header.className = "card-header";

        var badge = document.createElement("span");
        badge.className = "card-badge";
        badge.textContent = item.badge || "منبع";

        var title = document.createElement("span");
        title.className = "card-title";
        title.textContent = item.title || "بدون عنوان";

        var desc = document.createElement("span");
        desc.className = "card-desc";
        var updatedText = formatDate(item.updatedAt);
        desc.textContent = item.description || "";
        if (updatedText) {
            desc.textContent += (desc.textContent ? " • " : "") + "آپدیت " + updatedText;
        }

        header.appendChild(badge);
        header.appendChild(title);
        content.appendChild(header);
        content.appendChild(desc);

        link.appendChild(createChevron());
        link.appendChild(content);
        link.appendChild(createVisual(item.isExternal ? "link" : "document"));
        return link;
    }

    function createResourceStudentActions(item) {
        var actions = document.createElement("div");
        actions.className = "notes-resource-row__actions";
        var itemId = String(item.id || "");
        var itemTerm = String(resourceItemTerm(item));

        var favoriteButton = document.createElement("button");
        favoriteButton.type = "button";
        favoriteButton.className = "notes-resource-action";
        favoriteButton.dataset.resourceFavorite = "true";
        favoriteButton.dataset.itemId = itemId;
        favoriteButton.dataset.term = itemTerm;
        favoriteButton.dataset.favorite = isResourceFavorite(item) ? "0" : "1";
        favoriteButton.textContent = isResourceFavorite(item) ? "حذف علاقه‌مندی" : "علاقه‌مندی";
        actions.appendChild(favoriteButton);

        var reportButton = document.createElement("button");
        reportButton.type = "button";
        reportButton.className = "notes-resource-action notes-resource-action--muted";
        reportButton.dataset.resourceReport = "true";
        reportButton.dataset.itemId = itemId;
        reportButton.dataset.term = itemTerm;
        reportButton.textContent = "گزارش لینک";
        actions.appendChild(reportButton);

        var version = document.createElement("span");
        version.className = "notes-resource-version";
        version.textContent = "نسخه " + toFaDigits(Number(item.version || 1));
        actions.appendChild(version);
        return actions;
    }

    function buildCard(item) {
        var itemId = Number(item.id || 0);
        if (!state.canManage) {
            var publicCard = document.createElement("article");
            publicCard.className = "action-card notes-resource-card-shell";
            publicCard.dataset.itemId = String(item.id || "");
            publicCard.appendChild(createPrimaryLink(item));
            publicCard.appendChild(createResourceStudentActions(item));
            return publicCard;
        }

        var card = document.createElement("article");
        card.className = "action-card";
        card.dataset.itemId = String(item.id || "");
        card.appendChild(createPrimaryLink(item));
        card.appendChild(createResourceStudentActions(item));

        var actions = document.createElement("div");
        actions.className = "notes-card-actions";
        var button = document.createElement("a");
        button.className = "card-btn";
        button.href = item.buttonUrl || "#";
        button.textContent = item.buttonLabel || "باز کردن";
        button.dataset.analyticsDownload = "notes-resource";
        button.dataset.analyticsLabel = item.title || item.buttonLabel || "منبع";
        if (item.isExternal) {
            button.dataset.externalLink = "true";
            button.target = "_blank";
            button.rel = "noopener noreferrer";
        }
        actions.appendChild(button);

        var editButton = document.createElement("button");
        editButton.type = "button";
        editButton.className = "notes-card-edit";
        editButton.setAttribute("data-notes-edit", "true");
        editButton.setAttribute("data-item-id", String(item.id || ""));
        editButton.textContent = state.editingItemId === itemId ? "در حال ویرایش" : "ویرایش";
        editButton.disabled = state.saving || state.deletingItemId > 0;
        actions.appendChild(editButton);

        var deleteButton = document.createElement("button");
        deleteButton.type = "button";
        deleteButton.className = "notes-card-delete";
        deleteButton.setAttribute("data-notes-delete", "true");
        deleteButton.setAttribute("data-item-id", String(item.id || ""));
        deleteButton.textContent = state.deletingItemId === itemId ? "در حال حذف..." : "حذف";
        deleteButton.disabled = state.deletingItemId === itemId || state.saving || state.editingItemId === itemId;
        actions.appendChild(deleteButton);

        card.appendChild(actions);
        return card;
    }

    function syncEditUi() {
        if (addSubmit) {
            if (state.saving) {
                addSubmit.textContent = state.editingItemId ? "در حال ذخیره..." : "در حال ثبت...";
            } else {
                addSubmit.textContent = state.editingItemId ? "ذخیره تغییرات" : "افزودن کارت";
            }
            addSubmit.disabled = state.saving;
        }

        var cancelButton = $("notes-term-cancel-edit");
        if (cancelButton) {
            cancelButton.hidden = !state.editingItemId;
            cancelButton.disabled = state.saving;
        }
    }

    function formInputs() {
        return {
            badge: $("notes-term-badge"),
            title: $("notes-term-card-title"),
            description: $("notes-term-card-description"),
            buttonLabel: $("notes-term-button-label"),
            buttonUrl: $("notes-term-button-url")
        };
    }

    function clearForm() {
        var inputs = formInputs();
        Object.keys(inputs).forEach(function (key) {
            if (inputs[key]) {
                inputs[key].value = "";
            }
        });
        if (hostTools && typeof hostTools.resetLinkMode === "function") {
            hostTools.resetLinkMode();
        }
    }

    function fillForm(item) {
        var inputs = formInputs();
        if (inputs.badge) inputs.badge.value = item.badge || "";
        if (inputs.title) inputs.title.value = item.title || "";
        if (inputs.description) inputs.description.value = item.description || "";
        if (inputs.buttonLabel) inputs.buttonLabel.value = item.buttonLabel || "";
        if (inputs.buttonUrl) inputs.buttonUrl.value = item.buttonUrl || "";
        if (hostTools && typeof hostTools.syncFromInput === "function") {
            hostTools.syncFromInput();
        }
    }

    function resetEditMode(keepValues) {
        state.editingItemId = 0;
        if (!keepValues) {
            clearForm();
        }
        setFeedback("", "");
        renderTerm();
    }

    function startEditing(item) {
        if (!item) {
            return;
        }

        state.editingItemId = Number(item.id || 0);
        state.manageExpanded = true;
        fillForm(item);
        setFeedback("کارت برای ویرایش آماده شد.", "success");
        renderTerm();

        var inputs = formInputs();
        if (inputs.title && typeof inputs.title.focus === "function") {
            inputs.title.focus();
        }
        if (managePanel && typeof managePanel.scrollIntoView === "function") {
            managePanel.scrollIntoView({ behavior: "smooth", block: "start" });
        }
    }

    function ensureCancelEditButton() {
        if (!manageForm || !addSubmit || $("notes-term-cancel-edit")) {
            return;
        }

        var cancelButton = document.createElement("button");
        cancelButton.type = "button";
        cancelButton.id = "notes-term-cancel-edit";
        cancelButton.className = "notes-manage-panel__cancel";
        cancelButton.textContent = "انصراف از ویرایش";
        cancelButton.hidden = true;
        cancelButton.addEventListener("click", function () {
            resetEditMode(false);
        });
        addSubmit.insertAdjacentElement("afterend", cancelButton);
    }

    function ensureTermData() {
        if (!state.termData) {
            state.termData = {
                cohort: cohort,
                term: term,
                title: "",
                description: "",
                emptyMessage: "",
                items: []
            };
        }
        if (!Array.isArray(state.termData.items)) {
            state.termData.items = [];
        }
    }

    function applySavedItem(item, isEdit) {
        ensureTermData();
        if (!isEdit) {
            state.termData.items.unshift(item);
            return;
        }

        var replaced = false;
        state.termData.items = state.termData.items.map(function (current) {
            if (Number(current.id || 0) === Number(item.id || 0)) {
                replaced = true;
                return item;
            }
            return current;
        });
        if (!replaced) {
            state.termData.items.unshift(item);
        }
    }

    function handleUnauthorized(payload, fallbackText) {
        var message = fallbackText || "برای مدیریت منابع باید وارد حساب مجاز شوید.";
        if (siteApi && typeof siteApi.consumeUnauthorized === "function") {
            return !!siteApi.consumeUnauthorized(payload, message);
        }
        var auth = window.Dent1402Auth;
        if (!auth || typeof auth.handleUnauthorizedPayload !== "function") {
            return false;
        }
        return auth.handleUnauthorizedPayload(payload, message);
    }

    function escapeHtml(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    var hostTools = null;

    function ensureDownloadHostUi() {
        if (hostTools || !manageForm || !window.Dent1402NotesHostPicker || typeof window.Dent1402NotesHostPicker.create !== "function") {
            return;
        }
        hostTools = window.Dent1402NotesHostPicker.create({
            prefix: "notes-term-host",
            manageForm: manageForm,
            insertBeforeNode: addSubmit || null,
            request: request,
            handleUnauthorized: handleUnauthorized,
            getTerm: function () {
                return term;
            },
            getCohort: function () {
                return cohort;
            },
            getContext: function () {
                return state.termData || null;
            },
            linkInput: formInputs().buttonUrl,
            buttonLabelInput: formInputs().buttonLabel
        });
    }

    function syncDownloadHostUi() {
        ensureDownloadHostUi();
        if (!hostTools) {
            return;
        }
        hostTools.sync({
            canManage: state.canManage,
            info: state.downloadHost || null
        });
    }

    function renderTerm() {
        var termData = state.termData;
        clearCards();
        var fragment = document.createDocumentFragment();
        applyPagePresentation(termData || {
            termNumber: term,
            termLabel: "ترم " + toFaDigits(term),
            mode: isUnitMode() ? "curriculum-unit" : ""
        });

        if (!termData) {
            emptyBox.hidden = false;
            emptyBox.textContent = state.loadError || "داده‌ای برای این ترم دریافت نشد.";
            syncEditUi();
            syncManagePanel();
            syncDownloadHostUi();
            return;
        }

        if (termTitle) {
            termTitle.textContent = termData.title || termTitle.textContent;
        }
        if (termDescription) {
            termDescription.textContent = termData.description || termDescription.textContent;
        }

        renderResourceInsightsPanel();

        var items = Array.isArray(termData.items) ? termData.items : [];
        if (!items.length) {
            emptyBox.hidden = false;
            emptyBox.textContent = termData.emptyMessage || "هنوز منبعی ثبت نشده است.";
        } else {
            emptyBox.hidden = true;
            items.forEach(function (item) {
                fragment.appendChild(buildCard(item));
            });
            cardsContainer.appendChild(fragment);
        }

        if (managePanel) {
            managePanel.hidden = !state.canManage;
        }
        syncEditUi();
        syncManagePanel();
        syncDownloadHostUi();
        focusManagePanelIfNeeded();
    }

    function setSaving(saving) {
        state.saving = !!saving;
        syncEditUi();
        renderTerm();
    }

    function setDeletingItemId(itemId) {
        state.deletingItemId = Number(itemId) || 0;
        renderTerm();
    }

    function loadTerm(options) {
        if (state.loading) {
            return Promise.resolve();
        }

        state.loading = true;
        var silent = options && options.silent;
        if (!silent) {
            emptyBox.hidden = false;
            emptyBox.textContent = "در حال دریافت منابع این ترم...";
        }
        state.loadError = "";
        state.resourceInsights = null;

        return request("term", "GET", {}).then(function (payload) {
            if (handleUnauthorized(payload)) {
                state.canManage = false;
                state.termData = payload.term || state.termData;
                state.downloadHost = payload.downloadHost || null;
                applyResourceInsights(payload);
                renderTerm();
                return;
            }

            if (!payload || !payload.success || !payload.term) {
                throw new Error((payload && payload.error) || "دریافت منابع ناموفق بود.");
            }

            state.termData = payload.term;
            state.canManage = !!payload.canManage;
            state.loadError = "";
            state.downloadHost = payload.downloadHost || null;
            applyResourceInsights(payload);
            if (state.canManage && manageRequested) {
                state.manageExpanded = true;
                state.manageFocusPending = true;
            }
            if (state.canManage && isUnitMode() && !state.manageInitialApplied) {
                state.manageExpanded = true;
                state.manageInitialApplied = true;
            }
            if (state.editingItemId && !findItem(state.editingItemId)) {
                state.editingItemId = 0;
            }
            renderTerm();
        }).catch(function (error) {
            state.termData = null;
            state.loadError = error && error.message ? error.message : "دریافت منابع با خطا مواجه شد.";
            state.canManage = false;
            state.downloadHost = null;
            state.manageFocusPending = false;
            if (managePanel) {
                managePanel.hidden = true;
            }
            renderTerm();
        }).finally(function () {
            state.loading = false;
        });
    }

    function readFormPayload() {
        var inputs = formInputs();
        return {
            badge: inputs.badge ? inputs.badge.value : "",
            title: inputs.title ? inputs.title.value : "",
            description: inputs.description ? inputs.description.value : "",
            buttonLabel: inputs.buttonLabel ? inputs.buttonLabel.value : "",
            buttonUrl: inputs.buttonUrl ? inputs.buttonUrl.value : ""
        };
    }

    function bindManageForm() {
        if (!manageForm) {
            return;
        }

        ensureCancelEditButton();
        ensureDownloadHostUi();

        manageForm.addEventListener("submit", function (event) {
            event.preventDefault();
            if (state.saving) {
                return;
            }

            var payload = readFormPayload();
            if (!payload.buttonUrl.trim()) {
                state.manageExpanded = true;
                syncManagePanel();
                setFeedback("لینک کارت هنوز تنظیم نشده است. یک فایل موجود را انتخاب کن یا فایل تازه‌ای روی هاست دانلود آپلود کن.", "error");
                return;
            }
            if (!payload.badge.trim() || !payload.title.trim() || !payload.description.trim() || !payload.buttonLabel.trim()) {
                state.manageExpanded = true;
                syncManagePanel();
                setFeedback("همه فیلدهای کارت را کامل وارد کنید.", "error");
                return;
            }

            var isEdit = state.editingItemId > 0;
            var action = isEdit ? "editItem" : "addItem";
            if (isEdit) {
                var editingItem = findItem(state.editingItemId);
                payload.itemId = String(state.editingItemId);
                payload.term = String(resolveItemStorageTerm(editingItem));
            }

            state.manageExpanded = true;
            setFeedback("", "");
            setSaving(true);

            request(action, "POST", payload).then(function (response) {
                if (handleUnauthorized(response)) {
                    throw new Error("برای مدیریت منابع باید وارد حساب مجاز شوید.");
                }

                if (!response || !response.success || !response.item) {
                    throw new Error((response && response.error) || "ذخیره کارت منبع انجام نشد.");
                }

                applySavedItem(response.item, isEdit);
                state.editingItemId = 0;
                state.manageExpanded = true;
                clearForm();
                renderTerm();
                setFeedback(response.message || "کارت منبع ذخیره شد.", "success");
            }).catch(function (error) {
                state.manageExpanded = true;
                setFeedback(error && error.message ? error.message : "ذخیره کارت منبع با خطا مواجه شد.", "error");
            }).finally(function () {
                setSaving(false);
            });
        });
    }

    function trackResourceOpenFromNode(node) {
        if (!node) {
            return;
        }
        request("trackResourceOpen", "POST", {
            itemId: node.dataset.itemId || "",
            term: node.dataset.term || String(term)
        }).then(function (payload) {
            applyResourceInsights(payload);
        });
    }

    function submitResourceRequest(form) {
        if (!form || state.resourceBusy) {
            return;
        }
        var titleInput = form.querySelector("input[name='title']");
        var noteInput = form.querySelector("textarea[name='note']");
        var titleValue = titleInput ? String(titleInput.value || "").trim() : "";
        var noteValue = noteInput ? String(noteInput.value || "").trim() : "";
        if (!titleValue && !noteValue) {
            setResourceFeedback("عنوان یا توضیح درخواست منبع را وارد کن.");
            return;
        }

        state.resourceBusy = true;
        renderTerm();
        request("requestResource", "POST", {
            term: form.dataset.term || String(term),
            unitKey: form.dataset.unitKey || requestedUnitKey || "",
            title: titleValue,
            note: noteValue
        }).then(function (payload) {
            if (handleUnauthorized(payload, "برای ثبت درخواست منبع باید وارد حساب شوی.")) {
                throw new Error("برای ثبت درخواست منبع باید وارد حساب مجاز شوید.");
            }
            if (!payload || !payload.success) {
                throw new Error((payload && payload.error) || "ثبت درخواست منبع انجام نشد.");
            }
            applyResourceInsights(payload);
            state.resourceFeedback = payload.message || "درخواست منبع ثبت شد.";
            renderTerm();
        }).catch(function (error) {
            setResourceFeedback(error && error.message ? error.message : "ثبت درخواست منبع با خطا مواجه شد.");
        }).finally(function () {
            state.resourceBusy = false;
            renderTerm();
        });
    }

    function bindResourcePanelActions() {
        var section = $("notes-term-section");
        if (!section) {
            return;
        }
        section.addEventListener("click", function (event) {
            var packSaveButton = event.target && event.target.closest ? event.target.closest("[data-resource-pack-save]") : null;
            var packRemoveButton = event.target && event.target.closest ? event.target.closest("[data-resource-pack-remove]") : null;
            if (packSaveButton) {
                event.preventDefault();
                saveOfflineResourcePack();
                return;
            }
            if (packRemoveButton) {
                event.preventDefault();
                removeOfflineResourcePack();
                return;
            }

            var issueButton = event.target && event.target.closest ? event.target.closest("[data-resource-issue]") : null;
            if (!issueButton) {
                return;
            }
            event.preventDefault();
            if (state.resourceBusy) {
                return;
            }
            state.resourceBusy = true;
            request("updateResourceIssueStatus", "POST", {
                type: issueButton.dataset.issueType || "",
                id: issueButton.dataset.issueId || "",
                status: "resolved"
            }).then(function (payload) {
                if (handleUnauthorized(payload, "برای مدیریت صندوق منابع باید وارد حساب مجاز شوید.")) {
                    throw new Error("برای مدیریت صندوق منابع باید وارد حساب مجاز شوید.");
                }
                if (!payload || !payload.success) {
                    throw new Error((payload && payload.error) || "به‌روزرسانی وضعیت انجام نشد.");
                }
                applyResourceInsights(payload);
                state.resourceFeedback = payload.message || "وضعیت مورد به‌روزرسانی شد.";
                renderTerm();
            }).catch(function (error) {
                setResourceFeedback(error && error.message ? error.message : "به‌روزرسانی وضعیت با خطا مواجه شد.");
            }).finally(function () {
                state.resourceBusy = false;
                renderTerm();
            });
        });
        section.addEventListener("submit", function (event) {
            var form = event.target && event.target.closest ? event.target.closest("[data-resource-request-form]") : null;
            if (!form) {
                return;
            }
            event.preventDefault();
            submitResourceRequest(form);
        });
    }

    function bindCardActions() {
        cardsContainer.addEventListener("click", function (event) {
            var favoriteButton = event.target && event.target.closest ? event.target.closest("[data-resource-favorite]") : null;
            var reportButton = event.target && event.target.closest ? event.target.closest("[data-resource-report]") : null;
            var openLink = event.target && event.target.closest ? event.target.closest("[data-resource-open]") : null;
            var editButton = event.target && event.target.closest ? event.target.closest("[data-notes-edit='true']") : null;
            var deleteButton = event.target && event.target.closest ? event.target.closest("[data-notes-delete='true']") : null;

            if (favoriteButton) {
                event.preventDefault();
                if (state.resourceBusy) {
                    return;
                }
                state.resourceBusy = true;
                request("toggleResourceFavorite", "POST", {
                    itemId: favoriteButton.dataset.itemId || "",
                    term: favoriteButton.dataset.term || String(term),
                    favorite: favoriteButton.dataset.favorite || "1"
                }).then(function (payload) {
                    if (handleUnauthorized(payload, "برای ذخیره علاقه‌مندی باید وارد حساب شوی.")) {
                        throw new Error("برای ذخیره علاقه‌مندی باید وارد حساب مجاز شوید.");
                    }
                    if (!payload || !payload.success) {
                        throw new Error((payload && payload.error) || "تغییر علاقه‌مندی انجام نشد.");
                    }
                    applyResourceInsights(payload);
                    state.resourceFeedback = payload.message || "علاقه‌مندی به‌روزرسانی شد.";
                    renderTerm();
                }).catch(function (error) {
                    setResourceFeedback(error && error.message ? error.message : "تغییر علاقه‌مندی با خطا مواجه شد.");
                }).finally(function () {
                    state.resourceBusy = false;
                    renderTerm();
                });
                return;
            }

            if (reportButton) {
                event.preventDefault();
                if (state.resourceBusy) {
                    return;
                }
                var note = window.prompt("اگر توضیح کوتاهی درباره مشکل لینک داری بنویس.");
                if (note === null) {
                    return;
                }
                state.resourceBusy = true;
                request("reportResourceLink", "POST", {
                    itemId: reportButton.dataset.itemId || "",
                    term: reportButton.dataset.term || String(term),
                    reason: "گزارش دانشجو",
                    note: note
                }).then(function (payload) {
                    if (handleUnauthorized(payload, "برای گزارش لینک باید وارد حساب شوی.")) {
                        throw new Error("برای گزارش لینک باید وارد حساب مجاز شوید.");
                    }
                    if (!payload || !payload.success) {
                        throw new Error((payload && payload.error) || "ثبت گزارش لینک انجام نشد.");
                    }
                    applyResourceInsights(payload);
                    state.resourceFeedback = payload.message || "گزارش لینک ثبت شد.";
                    renderTerm();
                }).catch(function (error) {
                    setResourceFeedback(error && error.message ? error.message : "ثبت گزارش لینک با خطا مواجه شد.");
                }).finally(function () {
                    state.resourceBusy = false;
                    renderTerm();
                });
                return;
            }

            if (openLink) {
                if (isOffline()) {
                    event.preventDefault();
                    openOfflineResourceFromItem(findItem(openLink.dataset.itemId || ""));
                    return;
                }
                trackResourceOpenFromNode(openLink);
            }

            if (editButton) {
                if (!state.canManage || state.saving || state.deletingItemId) {
                    return;
                }

                var editItemId = Number(editButton.getAttribute("data-item-id") || "0");
                if (!Number.isFinite(editItemId) || editItemId <= 0) {
                    return;
                }

                startEditing(findItem(editItemId));
                return;
            }

            if (!deleteButton) {
                return;
            }

            if (!state.canManage || state.saving || state.deletingItemId) {
                return;
            }

            var itemId = Number(deleteButton.getAttribute("data-item-id") || "0");
            if (!Number.isFinite(itemId) || itemId <= 0) {
                return;
            }

            var confirmed = window.confirm("این کارت منبع حذف شود؟");
            if (!confirmed) {
                return;
            }

            state.manageExpanded = true;
            setFeedback("", "");
            setDeletingItemId(itemId);

            request("deleteItem", "POST", {
                itemId: String(itemId),
                term: String(resolveItemStorageTerm(findItem(itemId)))
            }).then(function (response) {
                if (handleUnauthorized(response)) {
                    throw new Error("برای مدیریت منابع باید وارد حساب مجاز شوید.");
                }

                if (!response || !response.success || !response.item) {
                    throw new Error((response && response.error) || "حذف کارت انجام نشد.");
                }

                if (state.termData && Array.isArray(state.termData.items)) {
                    state.termData.items = state.termData.items.filter(function (item) {
                        return Number(item.id || 0) !== itemId;
                    });
                }
                if (state.editingItemId === itemId) {
                    state.editingItemId = 0;
                    clearForm();
                }
                state.manageExpanded = true;
                renderTerm();
                setFeedback(response.message || "کارت منبع حذف شد.", "success");
            }).catch(function (error) {
                state.manageExpanded = true;
                setFeedback(error && error.message ? error.message : "حذف کارت با خطا مواجه شد.", "error");
            }).finally(function () {
                setDeletingItemId(0);
            });
        });
    }

    function watchAuthChanges() {
        var auth = window.Dent1402Auth;
        if (!auth || typeof auth.onChange !== "function") {
            return;
        }

        auth.onChange(function () {
            var nextKey = authSnapshotKey();
            if (nextKey === state.authKey) {
                return;
            }

            state.authKey = nextKey;
            loadTerm({ silent: true });
        });
    }

    function boot() {
        state.authKey = authSnapshotKey();
        bindManageForm();
        bindResourcePanelActions();
        bindCardActions();
        watchAuthChanges();
        loadTerm({ silent: false });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot, { once: true });
    } else {
        boot();
    }
})();
