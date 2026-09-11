(function () {
    "use strict";

    var shellDisabled = !!(document.body && document.body.dataset.shell === "off");
    var shellHeaderDisabled = !!(document.body && document.body.dataset.shellHeader === "off");
    var modal = null;
    var modalBackdrop = null;
    var pendingExternal = null;
    var navInner = null;
    var navSignature = "";
    var warmedNavigationTargets = Object.create(null);
    var authLinkSeeded = false;
    var headerAccount = {
        root: null,
        trigger: null,
        panel: null,
        avatar: null,
        avatarImage: null,
        avatarText: null,
        triggerTitle: null,
        triggerMeta: null,
        badge: null,
        loggedIn: null,
        loggedOut: null,
        name: null,
        role: null,
        studentNumber: null,
        ownerLink: null,
        loginLink: null,
        signupLink: null,
        themeButton: null,
        themeButtons: [],
        logoutButton: null,
        open: false,
        busy: false
    };
    var pollNavState = {
        pending: false,
        count: 0,
        lastUserKey: "",
        lastFetchedAt: 0
    };
    var navBadgeState = {
        pending: false,
        chatCount: 0,
        notificationCount: 0,
        lastUserKey: "",
        lastFetchedAt: 0
    };
    var notificationBanner = {
        root: null,
        eyebrow: null,
        title: null,
        body: null,
        primary: null,
        dismiss: null,
        markRead: null
    };
    var notificationBannerState = {
        userKey: "",
        preview: null,
        markingId: ""
    };
    var POLL_COUNT_TTL_MS = 45000;
    var NAV_BADGE_TTL_MS = 45000;
    var NOTIFICATION_BANNER_DISMISS_KEY = "dent1402-shell-notification-banner-dismissed";
    var OFFLINE_QUEUE_STORAGE_KEY = "dent1402-offline-queue-v1";
    var OFFLINE_PACK_STORAGE_KEY = "dent1402-offline-packs-v1";
    var OFFLINE_PACK_CACHE = "dent1402-offline-pack-cache-v1";
    var offlineQueueListeners = [];
    var offlineFlushPromise = null;
    var offlineFlushTimer = 0;
    var offlineInitialized = false;
    var offlineQueueState = {
        queue: [],
        flushing: false,
        lastError: "",
        lastFlushAt: ""
    };

    function authApi() {
        return window.Dent1402Auth && typeof window.Dent1402Auth === "object"
            ? window.Dent1402Auth
            : null;
    }

    function readSessionValue(key) {
        try {
            return window.sessionStorage ? String(window.sessionStorage.getItem(key) || "") : "";
        } catch (_error) {
            return "";
        }
    }

    function writeSessionValue(key, value) {
        try {
            if (!window.sessionStorage) {
                return;
            }
            if (value) {
                window.sessionStorage.setItem(key, String(value));
            } else {
                window.sessionStorage.removeItem(key);
            }
        } catch (_error) {
            // Ignore storage failures.
        }
    }

    function scopedPath(path, cohortKey) {
        var auth = authApi();
        if (auth && typeof auth.appendCohortQuery === "function") {
            return auth.appendCohortQuery(path, cohortKey);
        }
        return path;
    }

    function icon(name) {
        var icons = {
            home: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 10.5L12 4L20 10.5V19A1 1 0 0 1 19 20H5A1 1 0 0 1 4 19V10.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9.5 20V13.5H14.5V20" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
            chat: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 18.5L3.8 20L4.7 16.6C3.6 15.3 3 13.7 3 12C3 7.58 7.03 4 12 4C16.97 4 21 7.58 21 12C21 16.42 16.97 20 12 20C10.2 20 8.53 19.53 7 18.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
            forms: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="5" y="4" width="14" height="16" rx="3" stroke="currentColor" stroke-width="1.8"/><path d="M8.5 9H15.5M8.5 12.3H15.5M8.5 15.6H12.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            exam: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 4.5H17A2 2 0 0 1 19 6.5V19.5L12 16.5L5 19.5V6.5A2 2 0 0 1 7 4.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 9H15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M9 12.5H13.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            grades: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 18.5V13.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M12 18.5V9.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M19 18.5V5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M3.5 19.5H20.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            resources: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5.5 5.5H18.5A1.5 1.5 0 0 1 20 7V18.5A1.5 1.5 0 0 1 18.5 20H7A2 2 0 0 1 5 18V6A.5.5 0 0 1 5.5 5.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M8 5.5V17.5A2.5 2.5 0 0 0 10.5 20" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M11 9H16.5M11 12.5H15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            buy: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7.2A2.2 2.2 0 0 1 6.2 5h11.6A2.2 2.2 0 0 1 20 7.2v9.6a2.2 2.2 0 0 1-2.2 2.2H6.2A2.2 2.2 0 0 1 4 16.8z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M4 9.4h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M8 14.2h3.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M14.7 14.2h1.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            polls: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 4.5V12L18.5 15.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 20A8 8 0 1 1 20 12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            account: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 12.25A3.75 3.75 0 1 0 12 4.75A3.75 3.75 0 0 0 12 12.25Z" stroke="currentColor" stroke-width="1.8"/><path d="M5 19.25C5.93 16.74 8.48 15 12 15C15.52 15 18.07 16.74 19 19.25" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>'
        };

        return icons[name] || "";
    }

    function currentPath() {
        var path = window.location.pathname || "/";
        return path.endsWith("/") ? path : path + "/";
    }

    function isActive(item) {
        var path = currentPath();
        return item.active.some(function (prefix) {
            if (item.exact) {
                return path === prefix;
            }
            return path === prefix || path.indexOf(prefix) === 0;
        });
    }

    function useDynamicBranding() {
        return true;
    }

    function authState() {
        if (window.Dent1402Auth && typeof window.Dent1402Auth.getState === "function") {
            return window.Dent1402Auth.getState();
        }
        return {
            status: "logged-out",
            loggedIn: false,
            user: null,
            error: ""
        };
    }

    function siteAppearanceSettings(state) {
        var sourceState = state || authState();
        var topSettings = sourceState && sourceState.siteSettings;
        var userSettings = sourceState && sourceState.user && sourceState.user.siteSettings;
        var appearance = topSettings && topSettings.appearance ? topSettings.appearance : null;
        if (!appearance && userSettings && userSettings.appearance) {
            appearance = userSettings.appearance;
        }
        return appearance && typeof appearance === "object" ? appearance : {};
    }

    function bottomNavSwipeEnabled() {
        var state = authState();
        var appearance = siteAppearanceSettings(state);
        return !!(appearance && appearance.bottomNavSwipeEnabled);
    }

    function applySiteAppearance(state) {
        var appearance = siteAppearanceSettings(state);
        var root = document.documentElement;
        var body = document.body;
        root.classList.toggle("site-bottom-nav-labels-off", appearance.bottomNavLabelsEnabled === false);
        root.classList.toggle("site-bottom-nav-solid", appearance.bottomNavGlassEnabled === false);
        root.classList.toggle("site-appearance-lite-forced", !!appearance.visualEffectsLiteEnabled);
        if (body) {
            body.classList.toggle("site-bottom-nav-labels-off", appearance.bottomNavLabelsEnabled === false);
            body.classList.toggle("site-bottom-nav-solid", appearance.bottomNavGlassEnabled === false);
        }
        if (appearance.visualEffectsLiteEnabled) {
            root.dataset.performanceMode = "lite";
            root.dataset.siteAppearanceForcedLite = "true";
        } else if (root.dataset.siteAppearanceForcedLite === "true") {
            root.dataset.performanceMode = "default";
            delete root.dataset.siteAppearanceForcedLite;
        }
    }

    function authStatus(state) {
        return state && state.status ? state.status : "logged-out";
    }

    function isAuthTransitioning(status) {
        return status === "session-restoring" || status === "logging-in" || status === "logging-out";
    }

    function authLinkHref(isLoggedIn) {
        if (isLoggedIn) {
            return "/account/";
        }

        var returnTo = window.location.pathname + window.location.search + window.location.hash;
        return "/account/?returnTo=" + encodeURIComponent(returnTo);
    }

    function userKey(state) {
        return state && state.loggedIn && state.user && state.user.studentNumber
            ? String(state.user.studentNumber)
            : "";
    }

    function isProsthesisState(state) {
        return !!(state && state.user && state.user.isProsthesisStudent);
    }

    function canUseChatState(state) {
        if (!state || !state.loggedIn || !state.user) {
            return false;
        }
        return state.user.canUseChat !== false && !state.user.isExternalExamUser;
    }

    function brandName(state) {
        return isProsthesisState(state) ? "ورودی ۱۴۰۲ پروتز تهران" : "ورودی ۱۴۰۲ دندانپزشکی تهران";
    }

    function prosthesisRedirectTarget(path) {
        if (path === "/chat/") {
            return scopedPath("/chat/", "prosthesis-1402");
        }
        if (path === "/forms/") {
            return scopedPath("/forms/", "prosthesis-1402");
        }
        if (path === "/forms/fill/") {
            return scopedPath("/forms/fill/", "prosthesis-1402");
        }
        if (path === "/grades/") {
            return scopedPath("/grades/", "prosthesis-1402");
        }
        if (path === "/exams/") {
            return scopedPath("/exams/", "prosthesis-1402");
        }
        if (path === "/notes/") {
            return scopedPath("/notes/", "prosthesis-1402");
        }

        return "";
    }

    function navItems(state) {
        var status = authStatus(state);
        var isPending = isAuthTransitioning(status);
        var canUseChat = state.loggedIn ? canUseChatState(state) : isPending;
        var isProsthesis = isProsthesisState(state);
        var chatBadgeCount = state.loggedIn ? Math.max(0, Number(navBadgeState.chatCount || 0)) : 0;
        var items = [];
        items.push({
            href: "/app/",
            label: "خانه",
            icon: "home",
            active: ["/app/"],
            exact: true
        });

        if (!canUseChat) {
            items.push({ href: "/resources/", label: "منابع", icon: "resources", active: ["/resources/", "/notes/"] });
            items.push({ href: "/exams/", label: "آزمون‌ها", icon: "exam", active: ["/exams/"] });
        } else if (!isProsthesis) {
            items.push({ href: "/exams/", label: "آزمون‌ها", icon: "exam", active: ["/exams/"] });
            items.push({
                href: "/chat/",
                label: "چت",
                icon: "chat",
                active: ["/chat/"],
                badgeCount: chatBadgeCount,
                badgeAriaLabel: "پیام خوانده‌نشده"
            });
        } else {
            items.push({ href: scopedPath("/exams/", "prosthesis-1402"), label: "آزمون‌ها", icon: "exam", active: ["/exams/"] });
            items.push({
                href: scopedPath("/chat/", "prosthesis-1402"),
                label: "چت",
                icon: "chat",
                active: ["/chat/"],
                badgeCount: chatBadgeCount,
                badgeAriaLabel: "پیام خوانده‌نشده"
            });
        }
        return items;
    }

    function applyBranding(state) {
        if (!useDynamicBranding()) {
            return;
        }
        var brand = brandName(state);
        var pageTitle = document.title || "";
        if (pageTitle.indexOf("ورودی ۱۴۰۲ دندانپزشکی تهران") !== -1) {
            document.title = pageTitle.replace(/ورودی ۱۴۰۲ دندانپزشکی تهران/g, brand);
        } else if (pageTitle.indexOf("ورودی ۱۴۰۲ دندانپزشکی") !== -1) {
            document.title = pageTitle.replace(/ورودی ۱۴۰۲ دندانپزشکی/g, isProsthesisState(state) ? "ورودی ۱۴۰۲ پروتز" : "ورودی ۱۴۰۲ دندانپزشکی");
        }

        document.querySelectorAll(".site-header .site-info h1, .site-footer p").forEach(function (node) {
            if (node) {
                node.textContent = brand;
            }
        });
    }

    function maybeRedirectProsthesis(state) {
        if (!isProsthesisState(state)) {
            return false;
        }

        var path = currentPath();
        var target = prosthesisRedirectTarget(path);
        var current = path + (window.location.search || "") + (window.location.hash || "");
        if (!target || target === current) {
            return false;
        }

        window.location.replace(target + (window.location.hash || ""));
        return true;
    }

    function ensureBottomNav() {
        if (shellDisabled || navInner) {
            return;
        }

        var nav = document.createElement("nav");
        nav.className = "shell-bottom-nav";
        nav.setAttribute("aria-label", "ناوبری پایین");

        navInner = document.createElement("div");
        navInner.className = "shell-bottom-nav__inner";

        nav.appendChild(navInner);
        document.body.appendChild(nav);
    }

    function warmBottomNavigation(items) {
        if (!("serviceWorker" in navigator) || !Array.isArray(items)) {
            return;
        }

        var targets = [];
        items.forEach(function (item) {
            if (!item || !item.href || isActive(item)) {
                return;
            }

            try {
                var url = new URL(item.href, window.location.origin);
                if (url.origin !== window.location.origin || warmedNavigationTargets[url.href]) {
                    return;
                }
                warmedNavigationTargets[url.href] = true;
                targets.push(url.href);
            } catch (_urlError) {
                // Invalid navigation items continue through ordinary clicks.
            }
        });

        if (!targets.length) {
            return;
        }

        function postTargets(worker) {
            if (!worker) {
                return;
            }
            targets.forEach(function (url) {
                worker.postMessage({ type: "WARM_PAGE", url: url });
            });
        }

        if (navigator.serviceWorker.controller) {
            postTargets(navigator.serviceWorker.controller);
            return;
        }

        navigator.serviceWorker.ready.then(function (registration) {
            postTargets(registration && registration.active);
        }).catch(function () {
            // Prewarming is optional and must never block shared navigation.
        });
    }

    function renderBottomNav(state) {
        if (shellDisabled) {
            return;
        }

        renderHeaderAccount(state);
        ensureBottomNav();
        var items = navItems(state);
        var signature = JSON.stringify({
            status: authStatus(state),
            path: currentPath(),
            items: items.map(function (item) {
                return {
                    href: item.href,
                    label: item.label,
                    icon: item.icon,
                    active: !!isActive(item),
                    pending: !!item.pending,
                    badgeCount: Number(item.badgeCount || 0)
                };
            })
        });
        if (signature === navSignature) {
            return;
        }
        navSignature = signature;
        navInner.textContent = "";
        navInner.style.setProperty("--nav-count", String(items.length));
        navInner.dataset.authStatus = authStatus(state);

        var fragment = document.createDocumentFragment();

        items.forEach(function (item) {
            var link = document.createElement("a");
            link.className = "shell-bottom-nav__link";
            if (item.pending) {
                link.classList.add("is-pending");
            }
            if (isActive(item)) {
                link.classList.add("is-active");
                link.setAttribute("aria-current", "page");
            }
            link.href = item.href;

            var iconHtml = '<span class="shell-bottom-nav__icon" aria-hidden="true">' + icon(item.icon);
            if (item.badgeCount) {
                var countText = item.badgeCount > 9 ? "۹+" : String(item.badgeCount).replace(/\d/g, function (digit) {
                    return ["۰", "۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹"][Number(digit)] || digit;
                });
                var badgeLabel = item.badgeAriaLabel || (item.label + " جدید");
                iconHtml += '<span class="shell-bottom-nav__badge" aria-label="' + badgeLabel + '">' + countText + "</span>";
            }
            iconHtml += "</span>";

            link.innerHTML = iconHtml + '<span class="shell-bottom-nav__label">' + item.label + "</span>";
            fragment.appendChild(link);
        });
        navInner.appendChild(fragment);
        warmBottomNavigation(items);
    }

    function themeIconMarkup(targetTheme) {
        if (targetTheme === "dark") {
            return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 14.5A7.5 7.5 0 0 1 9.5 4A8.5 8.5 0 1 0 20 14.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        }

        return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3.5V5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M12 18.5V20.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M20.5 12H18.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M5.5 12H3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M18.01 5.99L16.59 7.41" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M7.41 16.59L5.99 18.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M18.01 18.01L16.59 16.59" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M7.41 7.41L5.99 5.99" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="12" r="3.6" stroke="currentColor" stroke-width="1.8"/></svg>';
    }

    function syncShellThemeButton(button) {
        if (!button || !window.Dent1402Theme || typeof window.Dent1402Theme.getState !== "function") {
            return;
        }

        var theme = window.Dent1402Theme.getState().theme;
        var nextTheme = theme === "dark" ? "light" : "dark";
        var label = nextTheme === "dark" ? "تم تیره" : "تم روشن";
        button.innerHTML = '<span class="theme-toggle-btn__icon">' + themeIconMarkup(nextTheme) + "</span>";
        button.dataset.themeTarget = nextTheme;
        button.setAttribute("aria-label", label);
        button.setAttribute("title", label);
    }

    function ensureMinimalThemeButton(actions) {
        if (!actions || !window.Dent1402Theme || typeof window.Dent1402Theme.toggle !== "function") {
            return;
        }

        var header = actions.closest(".site-header");
        var tools = header ? header.querySelector(".shell-header-tools") : null;
        var button = header
            ? header.querySelector("[data-theme-toggle]")
            : actions.querySelector("[data-theme-toggle]");
        if (button) {
            if (tools && button.parentNode !== tools) {
                tools.appendChild(button);
            }
            syncShellThemeButton(button);
            return;
        }

        button = document.createElement("button");
        button.type = "button";
        button.className = "theme-toggle-btn";
        button.dataset.themeToggle = "true";
        button.addEventListener("click", function () {
            window.Dent1402Theme.toggle();
        });
        window.addEventListener("dent1402:theme-change", function () {
            syncShellThemeButton(button);
        });
        (tools || actions).appendChild(button);
        syncShellThemeButton(button);
    }

    function accountMenuIconMarkup(name) {
        var icons = {
            chevron: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 7.5L13.5 12L9 16.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            bell: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6.5 10A5.5 5.5 0 0 1 17.5 10V14.2L19.2 17H4.8L6.5 14.2V10Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9.7 19A2.6 2.6 0 0 0 14.3 19" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            logout: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M10 5H6.8A2.8 2.8 0 0 0 4 7.8V16.2A2.8 2.8 0 0 0 6.8 19H10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M13 8L17 12L13 16M17 12H9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            theme: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 14.5A7.5 7.5 0 0 1 9.5 4A8.5 8.5 0 1 0 20 14.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            admin: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3.8L19 7.4V12.5C19 16.3 16.2 19.2 12 20.4C7.8 19.2 5 16.3 5 12.5V7.4L12 3.8Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9.5 12L11.2 13.7L14.8 10.1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>'
        };
        return icons[name] || "";
    }

    function accountMenuInitials(user) {
        var name = user && user.name ? String(user.name).trim() : "";
        var parts = name.split(/\s+/).filter(Boolean);
        if (parts.length > 1) {
            return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
        }
        if (parts.length === 1) {
            return parts[0].slice(0, 2).toUpperCase();
        }
        return "کا";
    }

    function accountMenuDisplayName(user) {
        if (user && user.name) {
            return String(user.name).trim();
        }
        return "کاربر سایت";
    }

    function closeHeaderAccountMenu(restoreFocus) {
        if (!headerAccount.root || !headerAccount.panel || !headerAccount.trigger) {
            return;
        }
        headerAccount.open = false;
        headerAccount.root.classList.remove("is-open");
        headerAccount.panel.hidden = true;
        headerAccount.trigger.setAttribute("aria-expanded", "false");
        if (restoreFocus) {
            headerAccount.trigger.focus();
        }
    }

    function openHeaderAccountMenu() {
        if (!headerAccount.root || !headerAccount.panel || !headerAccount.trigger) {
            return;
        }
        if (searchState && searchState.open) {
            closeSearch();
        }
        headerAccount.open = true;
        headerAccount.panel.hidden = false;
        headerAccount.root.classList.add("is-open");
        headerAccount.trigger.setAttribute("aria-expanded", "true");
    }

    function toggleHeaderAccountMenu() {
        if (headerAccount.open) {
            closeHeaderAccountMenu(false);
        } else {
            openHeaderAccountMenu();
        }
    }

    function syncHeaderAccountThemeLabel() {
        if (!headerAccount.root) {
            return;
        }
        var theme = window.Dent1402Theme && typeof window.Dent1402Theme.getState === "function"
            ? window.Dent1402Theme.getState().theme
            : (document.documentElement.dataset.theme || "light");
        headerAccount.themeButtons.forEach(function (button) {
            var strong = button.querySelector("strong");
            var small = button.querySelector("small");
            if (strong) {
                strong.textContent = theme === "dark" ? "حالت روشن" : "حالت تیره";
            }
            if (small) {
                small.textContent = theme === "dark" ? "نمای روشن سایت را فعال کن" : "برای محیط کم‌نور مناسب‌تر است";
            }
        });
    }

    function ensureHeaderAccountMenu() {
        if (headerAccount.root || shellHeaderDisabled || document.body.classList.contains("chat-page")) {
            return;
        }
        var header = document.querySelector(".site-header");
        var actions = header ? header.querySelector(".header-actions") : null;
        if (!header || !actions) {
            return;
        }

        var root = document.createElement("div");
        root.className = "shell-account-menu";
        root.innerHTML = [
            '<button class="shell-account-trigger" type="button" aria-haspopup="menu" aria-expanded="false">',
            '  <span class="shell-account-trigger__avatar" aria-hidden="true"><img alt="" hidden><span data-account-avatar-text>کا</span></span>',
            '  <span class="shell-account-trigger__copy"><strong data-account-trigger-title>ورود</strong><small data-account-trigger-meta>حساب کاربری</small></span>',
            '  <span class="shell-account-trigger__chevron" aria-hidden="true">' + accountMenuIconMarkup("chevron") + "</span>",
            '  <span class="shell-account-trigger__badge" aria-label="اعلان خوانده‌نشده" hidden></span>',
            "</button>",
            '<div class="shell-account-panel" role="menu" aria-label="منوی حساب کاربری" hidden>',
            '  <div data-account-logged-in hidden>',
            '    <div class="shell-account-panel__profile">',
            '      <span class="shell-account-panel__avatar" aria-hidden="true" data-account-panel-avatar>کا</span>',
            '      <span class="shell-account-panel__identity"><strong data-account-name>کاربر سایت</strong><small data-account-role>دانشجو</small><small data-account-student-number dir="ltr"></small></span>',
            "    </div>",
            '    <nav class="shell-account-panel__nav" aria-label="دسترسی‌های حساب">',
            '      <a href="/account/" role="menuitem"><span class="shell-account-panel__icon">' + icon("account") + '</span><span><strong>حساب کاربری</strong><small>پروفایل، امنیت و تنظیمات</small></span><span class="shell-account-panel__arrow">' + accountMenuIconMarkup("chevron") + "</span></a>",
            '      <a href="/account/#notifications" role="menuitem"><span class="shell-account-panel__icon">' + accountMenuIconMarkup("bell") + '</span><span><strong>اعلان‌ها</strong><small>پیام‌ها و یادآوری‌های مهم</small></span><span class="shell-account-panel__arrow">' + accountMenuIconMarkup("chevron") + "</span></a>",
            '      <a href="/grades/" role="menuitem"><span class="shell-account-panel__icon">' + icon("grades") + '</span><span><strong>کارنامه و نمرات</strong><small>آخرین وضعیت آموزشی</small></span><span class="shell-account-panel__arrow">' + accountMenuIconMarkup("chevron") + "</span></a>",
            '      <a href="/resources/" role="menuitem"><span class="shell-account-panel__icon">' + icon("resources") + '</span><span><strong>منابع ذخیره‌شده</strong><small>جزوات و موارد مطالعه</small></span><span class="shell-account-panel__arrow">' + accountMenuIconMarkup("chevron") + "</span></a>",
            '      <a href="/admin/" role="menuitem" data-account-owner-link hidden><span class="shell-account-panel__icon">' + accountMenuIconMarkup("admin") + '</span><span><strong>مدیریت سایت</strong><small>وضعیت و ابزارهای مالک</small></span><span class="shell-account-panel__arrow">' + accountMenuIconMarkup("chevron") + "</span></a>",
            "    </nav>",
            '    <div class="shell-account-panel__actions">',
            '      <button type="button" data-account-theme-action role="menuitem"><span class="shell-account-panel__icon">' + accountMenuIconMarkup("theme") + '</span><span><strong>حالت تیره</strong><small>برای محیط کم‌نور مناسب‌تر است</small></span></button>',
            '      <button type="button" class="shell-account-panel__logout" data-account-logout role="menuitem"><span class="shell-account-panel__icon">' + accountMenuIconMarkup("logout") + '</span><span><strong>خروج از حساب</strong><small>پایان نشست روی این دستگاه</small></span></button>',
            "    </div>",
            "  </div>",
            '  <div class="shell-account-panel__guest" data-account-logged-out>',
            '    <span class="shell-account-panel__guest-icon" aria-hidden="true">' + icon("account") + "</span>",
            '    <strong>حساب کاربری</strong>',
            '    <p>برای دیدن نمرات، اعلان‌ها، فرم‌ها و منابع شخصی وارد شو.</p>',
            '    <a class="shell-account-panel__login" href="/account/" role="menuitem" data-account-login>ورود به حساب</a>',
            '    <a class="shell-account-panel__signup" href="/account/?mode=signup" role="menuitem" data-account-signup>ساخت حساب جدید</a>',
            '    <button class="shell-account-panel__guest-theme" type="button" data-account-theme-action role="menuitem"><span>' + accountMenuIconMarkup("theme") + '</span><span><strong>حالت تیره</strong><small>برای محیط کم‌نور مناسب‌تر است</small></span></button>',
            "  </div>",
            "</div>"
        ].join("");

        actions.insertBefore(root, actions.firstChild);
        headerAccount.root = root;
        headerAccount.trigger = root.querySelector(".shell-account-trigger");
        headerAccount.panel = root.querySelector(".shell-account-panel");
        headerAccount.avatar = root.querySelector(".shell-account-trigger__avatar");
        headerAccount.avatarImage = headerAccount.avatar.querySelector("img");
        headerAccount.avatarText = root.querySelector("[data-account-avatar-text]");
        headerAccount.triggerTitle = root.querySelector("[data-account-trigger-title]");
        headerAccount.triggerMeta = root.querySelector("[data-account-trigger-meta]");
        headerAccount.badge = root.querySelector(".shell-account-trigger__badge");
        headerAccount.loggedIn = root.querySelector("[data-account-logged-in]");
        headerAccount.loggedOut = root.querySelector("[data-account-logged-out]");
        headerAccount.name = root.querySelector("[data-account-name]");
        headerAccount.role = root.querySelector("[data-account-role]");
        headerAccount.studentNumber = root.querySelector("[data-account-student-number]");
        headerAccount.ownerLink = root.querySelector("[data-account-owner-link]");
        headerAccount.loginLink = root.querySelector("[data-account-login]");
        headerAccount.signupLink = root.querySelector("[data-account-signup]");
        headerAccount.themeButtons = Array.prototype.slice.call(root.querySelectorAll("[data-account-theme-action]"));
        headerAccount.themeButton = headerAccount.themeButtons[0] || null;
        headerAccount.logoutButton = root.querySelector("[data-account-logout]");

        headerAccount.trigger.addEventListener("click", function (event) {
            event.stopPropagation();
            toggleHeaderAccountMenu();
        });
        headerAccount.panel.addEventListener("click", function (event) {
            var link = event.target.closest("a");
            if (link) {
                closeHeaderAccountMenu(false);
            }
        });
        headerAccount.themeButtons.forEach(function (button) {
            button.addEventListener("click", function () {
                if (window.Dent1402Theme && typeof window.Dent1402Theme.toggle === "function") {
                    window.Dent1402Theme.toggle();
                }
            });
        });
        headerAccount.logoutButton.addEventListener("click", function () {
            var auth = authApi();
            if (headerAccount.busy || !auth || typeof auth.logout !== "function") {
                return;
            }
            headerAccount.busy = true;
            headerAccount.logoutButton.disabled = true;
            headerAccount.logoutButton.querySelector("strong").textContent = "در حال خروج...";
            auth.logout().then(function () {
                window.location.href = "/account/";
            }).catch(function () {
                headerAccount.busy = false;
                headerAccount.logoutButton.disabled = false;
                headerAccount.logoutButton.querySelector("strong").textContent = "خروج از حساب";
            });
        });
        document.addEventListener("click", function (event) {
            if (headerAccount.open && headerAccount.root && !headerAccount.root.contains(event.target)) {
                closeHeaderAccountMenu(false);
            }
        });
        window.addEventListener("dent1402:theme-change", syncHeaderAccountThemeLabel);
        syncHeaderAccountThemeLabel();
    }

    function renderHeaderAccount(state) {
        ensureHeaderAccountMenu();
        if (!headerAccount.root) {
            return;
        }
        var status = authStatus(state);
        var pending = isAuthTransitioning(status);
        var loggedIn = !!(state && state.loggedIn && state.user);
        var user = loggedIn ? state.user : null;
        var displayName = accountMenuDisplayName(user);
        var initials = accountMenuInitials(user);
        var avatarUrl = user && user.profile && user.profile.avatarUrl ? String(user.profile.avatarUrl) : "";
        var unreadCount = loggedIn ? Math.max(0, Number(navBadgeState.notificationCount || 0)) : 0;
        var returnTo = window.location.pathname + window.location.search + window.location.hash;

        headerAccount.root.classList.toggle("is-authenticated", loggedIn);
        headerAccount.root.classList.toggle("is-pending", pending);
        headerAccount.trigger.disabled = pending;
        headerAccount.triggerTitle.textContent = pending ? "در حال بررسی" : (loggedIn ? "حساب من" : "ورود");
        headerAccount.triggerMeta.textContent = loggedIn ? displayName : "حساب کاربری";
        headerAccount.trigger.setAttribute("aria-label", pending ? "در حال بررسی حساب" : (loggedIn ? "باز کردن منوی حساب " + displayName : "ورود یا ساخت حساب"));
        headerAccount.avatarText.textContent = initials;
        headerAccount.avatarText.hidden = !!avatarUrl;
        headerAccount.avatarImage.hidden = !avatarUrl;
        if (avatarUrl && headerAccount.avatarImage.getAttribute("src") !== avatarUrl) {
            headerAccount.avatarImage.src = avatarUrl;
        }
        if (!avatarUrl) {
            headerAccount.avatarImage.removeAttribute("src");
        }

        headerAccount.badge.hidden = unreadCount < 1;
        headerAccount.badge.textContent = unreadCount > 9 ? "۹+" : localeDigits(unreadCount);
        headerAccount.loggedIn.hidden = !loggedIn;
        headerAccount.loggedOut.hidden = loggedIn || pending;
        headerAccount.name.textContent = displayName;
        headerAccount.role.textContent = user && user.roleLabel ? String(user.roleLabel) : (user && user.isOwner ? "مالک سایت" : "دانشجو");
        headerAccount.studentNumber.textContent = user && user.studentNumber ? String(user.studentNumber) : "";
        headerAccount.ownerLink.hidden = !(user && user.isOwner);
        headerAccount.loginLink.href = authLinkHref(false);
        headerAccount.signupLink.href = "/account/?mode=signup&returnTo=" + encodeURIComponent(returnTo);
        if (!loggedIn) {
            headerAccount.busy = false;
            headerAccount.logoutButton.disabled = false;
            headerAccount.logoutButton.querySelector("strong").textContent = "خروج از حساب";
        }
        syncHeaderAccountThemeLabel();
    }

    function createMinimalSiteHeader() {
        if (document.querySelector(".site-header") || shellDisabled || shellHeaderDisabled) {
            return;
        }

        if (document.body.classList.contains("chat-page")) {
            return;
        }

        var header = document.createElement("header");
        header.className = "site-header";
        header.innerHTML = [
            '<div class="logo-area">',
            '  <div class="site-info"><h1>ورودی ۱۴۰۲ دندانپزشکی تهران</h1></div>',
            "</div>",
            '<div class="header-actions"></div>'
        ].join("");

        var overlay = document.querySelector(".background-overlay");
        if (overlay && overlay.parentNode === document.body && overlay.nextSibling) {
            document.body.insertBefore(header, overlay.nextSibling);
            return;
        }

        document.body.insertBefore(header, document.body.firstChild);
    }

    function normalizeSiteHeader() {
        if (shellHeaderDisabled) {
            return;
        }
        createMinimalSiteHeader();

        document.querySelectorAll(".site-header").forEach(function (header) {
            header.classList.add("site-header--minimal");

            var logoArea = header.querySelector(".logo-area");
            if (!logoArea) {
                logoArea = document.createElement("div");
                logoArea.className = "logo-area";
                header.insertBefore(logoArea, header.firstChild);
            }

            logoArea.querySelectorAll(".logo-circle, .badge-unofficial").forEach(function (node) {
                node.remove();
            });

            var siteInfo = logoArea.querySelector(".site-info");
            if (!siteInfo) {
                siteInfo = document.createElement("div");
                siteInfo.className = "site-info";
                logoArea.appendChild(siteInfo);
            }

            var brandLink = logoArea.querySelector(".shell-brand-link");
            if (!brandLink) {
                brandLink = document.createElement("a");
                brandLink.className = "shell-brand-link";
                brandLink.href = "/app/";
                brandLink.setAttribute("aria-label", "رفتن به خانه سایت");
                logoArea.insertBefore(brandLink, siteInfo);
                brandLink.appendChild(siteInfo);
            }
            if (!brandLink.querySelector(".shell-brand-mark")) {
                var brandMark = document.createElement("span");
                brandMark.className = "shell-brand-mark";
                brandMark.setAttribute("aria-hidden", "true");
                brandMark.innerHTML = '<img src="/assets/images/logo.png?v=20260722-200509" alt="">';
                brandLink.insertBefore(brandMark, brandLink.firstChild);
            }

            var title = siteInfo.querySelector("h1");
            if (!title) {
                title = document.createElement("h1");
                siteInfo.insertBefore(title, siteInfo.firstChild);
            }
            if (useDynamicBranding()) {
                title.textContent = brandName(authState());
            }

            siteInfo.querySelectorAll("p").forEach(function (node) {
                node.remove();
            });

            header.querySelectorAll(".site-top-nav, .header-link, [data-auth-link], .badge-unofficial").forEach(function (node) {
                node.remove();
            });

            var actions = header.querySelector(".header-actions");
            if (!actions) {
                actions = document.createElement("div");
                actions.className = "header-actions";
                header.appendChild(actions);
            }

            Array.prototype.slice.call(actions.children).forEach(function (node) {
                if (!node.matches("[data-theme-toggle]")) {
                    node.remove();
                }
            });

            ensureMinimalThemeButton(actions);
        });
    }

    function normalizePageTopbars() {
        document.querySelectorAll(".forms-topbar").forEach(function (topbar) {
            topbar.querySelectorAll('a[href="/app/"], a[href="/account/"]').forEach(function (node) {
                node.remove();
            });

            if (!topbar.querySelector(".forms-icon-btn")) {
                topbar.classList.add("forms-topbar--plain");
            }
        });
    }

    function ensureHeaderAuthLink() {
        authLinkSeeded = true;
    }

    function syncAuthLinks(state) {
        ensureHeaderAuthLink();
        document.querySelectorAll("[data-auth-link]").forEach(function (link) {
            var outLabel = link.dataset.authOutLabel || "ورود";
            var inLabel = link.dataset.authInLabel || "حساب کاربری";
            var useName = link.dataset.authUseName === "true";
            var text = state.loggedIn
                ? (useName && state.user && state.user.name ? state.user.name : inLabel)
                : outLabel;
            var status = authStatus(state);
            var pending = isAuthTransitioning(status);
            link.href = pending ? "/account/" : (state.loggedIn ? "/account/" : authLinkHref(false));
            link.textContent = text;
            link.setAttribute("title", text);
            link.setAttribute("aria-label", text);
            link.classList.toggle("is-authenticated", !!state.loggedIn);
            link.classList.toggle("is-auth-pending", pending);
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

    function isPlainObject(value) {
        return value && typeof value === "object" && !Array.isArray(value);
    }

    function readLocalJson(key, fallback) {
        try {
            var raw = window.localStorage ? window.localStorage.getItem(key) : "";
            if (!raw) {
                return fallback;
            }
            var parsed = JSON.parse(raw);
            return parsed === null || parsed === undefined ? fallback : parsed;
        } catch (_error) {
            return fallback;
        }
    }

    function writeLocalJson(key, value) {
        try {
            if (!window.localStorage) {
                return false;
            }
            if (value === null || value === undefined) {
                window.localStorage.removeItem(key);
                return true;
            }
            window.localStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch (_error) {
            return false;
        }
    }

    function offlineNowIso() {
        return new Date().toISOString();
    }

    function offlineGenerateId(prefix) {
        return String(prefix || "offline") + "-" + Date.now().toString(36) + "-" + Math.random().toString(36).slice(2, 8);
    }

    function offlineNormalizeHeaders(source) {
        var headers = {};
        if (!isPlainObject(source)) {
            return headers;
        }
        Object.keys(source).forEach(function (key) {
            var cleanKey = String(key || "").trim();
            if (!cleanKey) {
                return;
            }
            headers[cleanKey] = String(source[key] == null ? "" : source[key]);
        });
        return headers;
    }

    function offlineNormalizeQueueEntry(raw) {
        if (!isPlainObject(raw)) {
            return null;
        }
        var url = String(raw.url || "").trim();
        if (!url) {
            return null;
        }
        var method = String(raw.method || "POST").trim().toUpperCase();
        if (method !== "GET") {
            method = "POST";
        }
        return {
            id: String(raw.id || offlineGenerateId("queue")),
            kind: String(raw.kind || "request").trim() || "request",
            url: url,
            method: method,
            headers: offlineNormalizeHeaders(raw.headers),
            body: method === "GET" ? "" : String(raw.body || ""),
            dedupeKey: String(raw.dedupeKey || "").trim(),
            meta: isPlainObject(raw.meta) ? raw.meta : {},
            createdAt: String(raw.createdAt || offlineNowIso()),
            attempts: Math.max(0, Math.floor(Number(raw.attempts) || 0)),
            lastAttemptAt: String(raw.lastAttemptAt || ""),
            lastError: String(raw.lastError || "")
        };
    }

    function offlineReadQueue() {
        var raw = readLocalJson(OFFLINE_QUEUE_STORAGE_KEY, []);
        if (!Array.isArray(raw)) {
            return [];
        }
        return raw.map(offlineNormalizeQueueEntry).filter(Boolean);
    }

    function offlineWriteQueue(entries) {
        var next = Array.isArray(entries) ? entries.map(offlineNormalizeQueueEntry).filter(Boolean) : [];
        offlineQueueState.queue = next.slice();
        writeLocalJson(OFFLINE_QUEUE_STORAGE_KEY, next);
        return next;
    }

    function offlineQueueSnapshot() {
        return {
            count: offlineQueueState.queue.length,
            entries: offlineQueueState.queue.slice(),
            flushing: offlineQueueState.flushing,
            lastError: offlineQueueState.lastError,
            lastFlushAt: offlineQueueState.lastFlushAt
        };
    }

    function emitOfflineEvent(name, detail) {
        window.dispatchEvent(new CustomEvent(name, { detail: detail || {} }));
    }

    function emitOfflineQueueChange(extra) {
        var snapshot = offlineQueueSnapshot();
        var detail = Object.assign({ state: snapshot }, extra || {});
        emitOfflineEvent("dent1402:offline-queue-change", detail);
        offlineQueueListeners.forEach(function (listener) {
            try {
                listener(snapshot);
            } catch (_error) {
                // Listener failures must not break queue state updates.
            }
        });
    }

    function emitOfflineQueueResult(name, entry, payload, extra) {
        emitOfflineEvent(name, Object.assign({
            entry: entry,
            payload: payload || null,
            state: offlineQueueSnapshot()
        }, extra || {}));
    }

    function offlineIsNavigatorOnline() {
        return !(window.navigator && window.navigator.onLine === false);
    }

    function offlineShouldDropClientError(status) {
        return status === 400
            || status === 404
            || status === 409
            || status === 410
            || status === 422;
    }

    function offlineParseResponse(response) {
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
                payload = {
                    success: response.ok,
                    rawText: text || ""
                };
            }
            payload.httpStatus = response.status;
            return payload;
        });
    }

    function offlineQueueRequest(config) {
        if (!isPlainObject(config)) {
            throw new Error("offline-queue-config-invalid");
        }
        var entry = offlineNormalizeQueueEntry({
            id: config.id || "",
            kind: config.kind || "request",
            url: config.url || "",
            method: config.method || "POST",
            headers: config.headers || {},
            body: typeof config.body === "string"
                ? config.body
                : (config.body instanceof URLSearchParams ? config.body.toString() : new URLSearchParams(config.body || {}).toString()),
            dedupeKey: config.dedupeKey || "",
            meta: config.meta || {},
            createdAt: offlineNowIso()
        });
        if (!entry) {
            throw new Error("offline-queue-entry-invalid");
        }

        var queue = offlineReadQueue();
        if (entry.dedupeKey) {
            queue = queue.filter(function (current) {
                return !(current.kind === entry.kind && current.dedupeKey === entry.dedupeKey);
            });
        }
        queue.push(entry);
        offlineWriteQueue(queue);
        offlineQueueState.lastError = "";
        emitOfflineQueueChange({ reason: "queued", entry: entry });
        scheduleOfflineQueueFlush(900);
        return entry;
    }

    function offlineFindQueuedEntry(predicate) {
        if (typeof predicate !== "function") {
            return null;
        }
        var queue = offlineReadQueue();
        for (var index = 0; index < queue.length; index += 1) {
            if (predicate(queue[index])) {
                return queue[index];
            }
        }
        return null;
    }

    function scheduleOfflineQueueFlush(delayMs) {
        if (offlineFlushTimer) {
            window.clearTimeout(offlineFlushTimer);
        }
        if (!offlineIsNavigatorOnline()) {
            return;
        }
        offlineFlushTimer = window.setTimeout(function () {
            offlineFlushTimer = 0;
            flushOfflineQueue().catch(function () {
                return null;
            });
        }, Math.max(120, Number(delayMs) || 0));
    }

    function replayOfflineQueueEntry(entry) {
        var requestOptions = {
            method: entry.method,
            credentials: "same-origin",
            cache: "no-store",
            headers: offlineNormalizeHeaders(entry.headers)
        };
        if (!requestOptions.headers.Accept) {
            requestOptions.headers.Accept = "application/json";
        }
        if (entry.method !== "GET" && entry.body) {
            requestOptions.body = entry.body;
        }

        return fetch(entry.url, requestOptions).then(function (response) {
            return offlineParseResponse(response).then(function (payload) {
                if (!response.ok || payload.success === false) {
                    var error = new Error(payload.error || "درخواست صف آفلاین ناموفق بود.");
                    error.httpStatus = response.status;
                    error.payload = payload;
                    throw error;
                }
                return payload;
            });
        });
    }

    function flushOfflineQueue() {
        if (offlineFlushPromise) {
            return offlineFlushPromise;
        }
        if (!offlineIsNavigatorOnline()) {
            return Promise.resolve(offlineQueueSnapshot());
        }

        offlineFlushPromise = (async function () {
            offlineQueueState.queue = offlineReadQueue();
            if (!offlineQueueState.queue.length) {
                offlineQueueState.lastError = "";
                return offlineQueueSnapshot();
            }

            offlineQueueState.flushing = true;
            emitOfflineQueueChange({ reason: "flush-start" });

            while (offlineQueueState.queue.length) {
                var entry = offlineQueueState.queue[0];
                try {
                    var payload = await replayOfflineQueueEntry(entry);
                    offlineQueueState.queue.shift();
                    offlineWriteQueue(offlineQueueState.queue);
                    offlineQueueState.lastError = "";
                    offlineQueueState.lastFlushAt = offlineNowIso();
                    emitOfflineQueueResult("dent1402:offline-queue-success", entry, payload, { dropped: false });
                    emitOfflineQueueChange({ reason: "flush-success", entry: entry, payload: payload });
                } catch (error) {
                    var status = Math.max(0, Math.floor(Number(error && error.httpStatus) || 0));
                    entry.attempts = Math.max(0, Math.floor(Number(entry.attempts) || 0)) + 1;
                    entry.lastAttemptAt = offlineNowIso();
                    entry.lastError = error && error.message ? error.message : "ارسال مورد صف آفلاین انجام نشد.";

                    if (offlineShouldDropClientError(status)) {
                        offlineQueueState.queue.shift();
                        offlineWriteQueue(offlineQueueState.queue);
                        emitOfflineQueueResult("dent1402:offline-queue-failure", entry, error && error.payload ? error.payload : null, {
                            dropped: true,
                            httpStatus: status
                        });
                        emitOfflineQueueChange({ reason: "flush-drop", entry: entry, httpStatus: status });
                        continue;
                    }

                    offlineQueueState.queue[0] = entry;
                    offlineWriteQueue(offlineQueueState.queue);
                    offlineQueueState.lastError = entry.lastError;
                    emitOfflineQueueResult("dent1402:offline-queue-failure", entry, error && error.payload ? error.payload : null, {
                        dropped: false,
                        httpStatus: status
                    });
                    emitOfflineQueueChange({ reason: "flush-error", entry: entry, httpStatus: status });
                    break;
                }
            }

            return offlineQueueSnapshot();
        }()).finally(function () {
            offlineQueueState.flushing = false;
            emitOfflineQueueChange({ reason: "flush-finish" });
            offlineFlushPromise = null;
        });

        return offlineFlushPromise;
    }

    function offlineNormalizePackRecord(raw) {
        if (!isPlainObject(raw)) {
            return null;
        }
        var key = String(raw.key || "").trim();
        if (!key) {
            return null;
        }
        var resources = Array.isArray(raw.resources) ? raw.resources : [];
        return {
            key: key,
            title: String(raw.title || "بسته آفلاین"),
            pageUrl: String(raw.pageUrl || ""),
            updatedAt: String(raw.updatedAt || ""),
            attemptedCount: Math.max(0, Math.floor(Number(raw.attemptedCount) || 0)),
            cachedCount: Math.max(0, Math.floor(Number(raw.cachedCount) || 0)),
            failedCount: Math.max(0, Math.floor(Number(raw.failedCount) || 0)),
            resources: resources.map(function (resource) {
                if (!isPlainObject(resource)) {
                    return null;
                }
                var url = String(resource.url || "").trim();
                if (!url) {
                    return null;
                }
                return {
                    url: url,
                    title: String(resource.title || ""),
                    sourceUrl: String(resource.sourceUrl || "")
                };
            }).filter(Boolean)
        };
    }

    function offlineReadPacks() {
        var raw = readLocalJson(OFFLINE_PACK_STORAGE_KEY, {});
        var packs = {};
        if (!isPlainObject(raw)) {
            return packs;
        }
        Object.keys(raw).forEach(function (key) {
            var record = offlineNormalizePackRecord(raw[key]);
            if (record) {
                packs[record.key] = record;
            }
        });
        return packs;
    }

    function offlineWritePacks(packs) {
        var next = {};
        if (isPlainObject(packs)) {
            Object.keys(packs).forEach(function (key) {
                var record = offlineNormalizePackRecord(packs[key]);
                if (record) {
                    next[record.key] = record;
                }
            });
        }
        writeLocalJson(OFFLINE_PACK_STORAGE_KEY, next);
        return next;
    }

    function offlineSupportsPacks() {
        return typeof window.caches !== "undefined" && window.caches && typeof window.caches.open === "function";
    }

    async function saveOfflinePack(config) {
        if (!offlineSupportsPacks()) {
            throw new Error("ذخیره بسته آفلاین در این مرورگر پشتیبانی نمی‌شود.");
        }
        if (!isPlainObject(config)) {
            throw new Error("پیکربندی بسته آفلاین نامعتبر است.");
        }

        var key = String(config.key || "").trim();
        if (!key) {
            throw new Error("کلید بسته آفلاین مشخص نیست.");
        }

        var resources = (Array.isArray(config.resources) ? config.resources : []).map(function (resource) {
            if (!isPlainObject(resource)) {
                return null;
            }
            var url = String(resource.url || "").trim();
            if (!url) {
                return null;
            }
            return {
                url: url,
                title: String(resource.title || ""),
                sourceUrl: String(resource.sourceUrl || "")
            };
        }).filter(Boolean);
        if (!resources.length) {
            throw new Error("منبعی برای ذخیره آفلاین پیدا نشد.");
        }

        var cache = await window.caches.open(OFFLINE_PACK_CACHE);
        var packs = offlineReadPacks();
        var previous = packs[key];
        var attemptedCount = resources.length;
        var cachedResources = [];
        var failedResources = [];

        for (var index = 0; index < resources.length; index += 1) {
            var resource = resources[index];
            try {
                var response = await fetch(resource.url, {
                    method: "GET",
                    cache: "no-store",
                    credentials: "same-origin",
                    headers: {
                        Accept: "*/*"
                    }
                });
                if (!response.ok) {
                    throw new Error("دریافت منبع با کد " + String(response.status) + " ناموفق بود.");
                }
                await cache.put(resource.url, response.clone());
                cachedResources.push(resource);
            } catch (error) {
                failedResources.push({
                    url: resource.url,
                    title: resource.title,
                    error: error && error.message ? error.message : "ذخیره منبع انجام نشد."
                });
            }
        }

        if (!cachedResources.length) {
            throw new Error(failedResources[0] && failedResources[0].error ? failedResources[0].error : "هیچ منبعی برای آفلاین ذخیره نشد.");
        }

        if (previous && Array.isArray(previous.resources)) {
            var keepMap = {};
            cachedResources.forEach(function (resource) {
                keepMap[resource.url] = true;
            });
            for (var staleIndex = 0; staleIndex < previous.resources.length; staleIndex += 1) {
                var staleResource = previous.resources[staleIndex];
                if (!staleResource || !staleResource.url || keepMap[staleResource.url]) {
                    continue;
                }
                await cache.delete(staleResource.url);
            }
        }

        packs[key] = {
            key: key,
            title: String(config.title || "بسته آفلاین"),
            pageUrl: String(config.pageUrl || ""),
            updatedAt: offlineNowIso(),
            attemptedCount: attemptedCount,
            cachedCount: cachedResources.length,
            failedCount: failedResources.length,
            resources: cachedResources
        };
        offlineWritePacks(packs);

        return {
            pack: packs[key],
            cachedCount: cachedResources.length,
            failedCount: failedResources.length,
            failedResources: failedResources
        };
    }

    async function removeOfflinePack(key) {
        var cleanKey = String(key || "").trim();
        if (!cleanKey) {
            return false;
        }
        var packs = offlineReadPacks();
        var record = packs[cleanKey];
        if (!record) {
            return false;
        }

        if (offlineSupportsPacks()) {
            var cache = await window.caches.open(OFFLINE_PACK_CACHE);
            if (Array.isArray(record.resources)) {
                for (var index = 0; index < record.resources.length; index += 1) {
                    var resource = record.resources[index];
                    if (resource && resource.url) {
                        await cache.delete(resource.url);
                    }
                }
            }
        }

        delete packs[cleanKey];
        offlineWritePacks(packs);
        return true;
    }

    function getOfflinePack(key) {
        var cleanKey = String(key || "").trim();
        if (!cleanKey) {
            return null;
        }
        var packs = offlineReadPacks();
        return packs[cleanKey] || null;
    }

    async function hasOfflineCachedResource(url) {
        if (!offlineSupportsPacks()) {
            return false;
        }
        var cleanUrl = String(url || "").trim();
        if (!cleanUrl) {
            return false;
        }
        var cache = await window.caches.open(OFFLINE_PACK_CACHE);
        var cached = await cache.match(cleanUrl);
        return !!cached;
    }

    async function openOfflineCachedResource(url) {
        if (!offlineSupportsPacks()) {
            return false;
        }
        var cleanUrl = String(url || "").trim();
        if (!cleanUrl) {
            return false;
        }
        var cache = await window.caches.open(OFFLINE_PACK_CACHE);
        var response = await cache.match(cleanUrl);
        if (!response) {
            return false;
        }
        var blob = await response.blob();
        if (!blob || !blob.size) {
            return false;
        }

        var objectUrl = window.URL.createObjectURL(blob);
        var popup = window.open(objectUrl, "_blank", "noopener");
        if (!popup) {
            var link = document.createElement("a");
            link.href = objectUrl;
            link.target = "_blank";
            link.rel = "noopener";
            link.click();
        }
        window.setTimeout(function () {
            window.URL.revokeObjectURL(objectUrl);
        }, 60000);
        return true;
    }

    function onOfflineQueueChange(listener) {
        if (typeof listener !== "function") {
            return function () {};
        }
        offlineQueueListeners.push(listener);
        listener(offlineQueueSnapshot());
        return function () {
            offlineQueueListeners = offlineQueueListeners.filter(function (current) {
                return current !== listener;
            });
        };
    }

    function initOfflineSupport() {
        if (offlineInitialized) {
            return;
        }
        offlineInitialized = true;
        offlineQueueState.queue = offlineReadQueue();

        window.addEventListener("online", function () {
            scheduleOfflineQueueFlush(320);
        });
        window.addEventListener("pageshow", function () {
            scheduleOfflineQueueFlush(700);
        });
        document.addEventListener("visibilitychange", function () {
            if (!document.hidden) {
                scheduleOfflineQueueFlush(520);
            }
        });

        emitOfflineQueueChange({ reason: "init" });
        scheduleOfflineQueueFlush(1200);
    }

    initOfflineSupport();

    window.Dent1402Site = Object.assign({}, window.Dent1402Site || {}, {
        offline: {
            getQueueState: offlineQueueSnapshot,
            findQueuedEntry: offlineFindQueuedEntry,
            queueRequest: offlineQueueRequest,
            flushQueue: flushOfflineQueue,
            onQueueChange: onOfflineQueueChange,
            getPack: getOfflinePack,
            savePack: saveOfflinePack,
            removePack: removeOfflinePack,
            hasCachedResource: hasOfflineCachedResource,
            openCachedResource: openOfflineCachedResource
        }
    });

    function notificationPreviewKey(key, preview) {
        var id = preview && preview.id ? String(preview.id) : "";
        return key && id ? (key + ":" + id) : "";
    }

    function notificationBannerDismissedKey() {
        return readSessionValue(NOTIFICATION_BANNER_DISMISS_KEY);
    }

    function notificationBannerDefaultHref(preview) {
        if (preview && preview.kind === "navid-assignment") {
            return "/navid/";
        }
        return "/account/#notifications";
    }

    function notificationBannerDefaultLabel(preview) {
        if (preview && preview.kind === "navid-assignment") {
            return "مشاهده تکالیف";
        }
        return "مشاهده اعلان";
    }

    function notificationBannerKindLabel(preview) {
        return preview && preview.kind === "navid-assignment" ? "تکلیف جدید نوید" : "اعلان جدید";
    }

    function ensureNotificationBanner() {
        if (notificationBanner.root || !document.body) {
            return notificationBanner.root;
        }

        var banner = document.createElement("section");
        banner.className = "shell-notification-banner";
        banner.hidden = true;
        banner.setAttribute("aria-live", "polite");
        banner.innerHTML = [
            '<div class="shell-notification-banner__copy">',
            '  <span class="shell-notification-banner__eyebrow"></span>',
            '  <strong class="shell-notification-banner__title"></strong>',
            '  <p class="shell-notification-banner__body"></p>',
            "</div>",
            '<div class="shell-notification-banner__actions">',
            '  <div class="shell-notification-banner__actions-main">',
            '    <button type="button" class="shell-action-btn shell-notification-banner__dismiss">بعداً</button>',
            '    <a class="shell-action-btn shell-action-btn-primary shell-notification-banner__primary" href="/account/#notifications">مشاهده اعلان</a>',
            "  </div>",
            '  <button type="button" class="shell-action-btn shell-notification-banner__mark-read">علامت زده به عنوان خوانده شده</button>',
            "</div>"
        ].join("");

        document.body.appendChild(banner);
        notificationBanner.root = banner;
        notificationBanner.eyebrow = banner.querySelector(".shell-notification-banner__eyebrow");
        notificationBanner.title = banner.querySelector(".shell-notification-banner__title");
        notificationBanner.body = banner.querySelector(".shell-notification-banner__body");
        notificationBanner.primary = banner.querySelector(".shell-notification-banner__primary");
        notificationBanner.dismiss = banner.querySelector(".shell-notification-banner__dismiss");
        notificationBanner.markRead = banner.querySelector(".shell-notification-banner__mark-read");

        if (notificationBanner.dismiss) {
            notificationBanner.dismiss.addEventListener("click", function () {
                if (notificationBannerState.markingId) {
                    return;
                }
                var key = notificationPreviewKey(notificationBannerState.userKey, notificationBannerState.preview);
                writeSessionValue(NOTIFICATION_BANNER_DISMISS_KEY, key);
                renderNotificationBanner(authState());
            });
        }

        if (notificationBanner.primary) {
            notificationBanner.primary.addEventListener("click", function (event) {
                if (notificationBannerState.markingId) {
                    event.preventDefault();
                    return;
                }
                var href = notificationBanner.primary.getAttribute("href") || "/account/#notifications";
                var notificationId = notificationBanner.primary.dataset.notificationId || "";
                var dismissKey = notificationPreviewKey(notificationBannerState.userKey, notificationBannerState.preview);
                event.preventDefault();
                writeSessionValue(NOTIFICATION_BANNER_DISMISS_KEY, dismissKey);
                markNotificationReadFromShell(notificationId).finally(function () {
                    window.location.href = href;
                });
            });
        }

        if (notificationBanner.markRead) {
            notificationBanner.markRead.addEventListener("click", function () {
                var notificationId = String(notificationBanner.markRead.dataset.notificationId || "").trim();
                if (!notificationId || notificationBannerState.markingId === notificationId) {
                    return;
                }

                notificationBannerState.markingId = notificationId;
                renderNotificationBanner(authState());
                markNotificationReadFromShell(notificationId).finally(function () {
                    notificationBannerState.markingId = "";
                    renderNotificationBanner(authState());
                });
            });
        }

        return notificationBanner.root;
    }

    function renderNotificationBanner(state) {
        var banner = ensureNotificationBanner();
        if (!banner) {
            return;
        }

        var preview = notificationBannerState.preview;
        var key = notificationPreviewKey(notificationBannerState.userKey || userKey(state), preview);
        var dismissedKey = notificationBannerDismissedKey();
        var isNotificationsSurfaceOpen = currentPath() === "/account/" && (window.location.hash || "") === "#notifications";
        var visible = !!(state && state.loggedIn && preview && key && dismissedKey !== key && !isNotificationsSurfaceOpen);
        var previewId = String(preview && preview.id || "").trim();
        var isMarking = !!(previewId && notificationBannerState.markingId === previewId);

        banner.hidden = !visible;
        banner.classList.toggle("is-visible", visible);
        banner.classList.toggle("is-marking", isMarking);
        if (!visible) {
            return;
        }

        if (notificationBanner.eyebrow) {
            notificationBanner.eyebrow.textContent = notificationBannerKindLabel(preview);
        }
        if (notificationBanner.title) {
            notificationBanner.title.textContent = String(preview.title || (preview.kind === "navid-assignment" ? "تکلیف جدید نوید" : "اعلان جدید"));
        }
        if (notificationBanner.body) {
            notificationBanner.body.textContent = String(preview.body || (preview.kind === "navid-assignment"
                ? "برای دیدن جزئیات، بخش تکالیف نوید را باز کن."
                : "برای دیدن جزئیات، اعلان را باز کن."));
        }
        if (notificationBanner.primary) {
            notificationBanner.primary.textContent = String(preview.ctaLabel || notificationBannerDefaultLabel(preview));
            notificationBanner.primary.href = String(preview.ctaHref || notificationBannerDefaultHref(preview));
            notificationBanner.primary.dataset.notificationId = previewId;
        }
        if (notificationBanner.markRead) {
            notificationBanner.markRead.dataset.notificationId = previewId;
            notificationBanner.markRead.disabled = !previewId || isMarking;
            notificationBanner.markRead.textContent = isMarking ? "در حال ثبت..." : "علامت زده به عنوان خوانده شده";
        }
    }

    function setNotificationBannerPreview(preview, state) {
        notificationBannerState.userKey = userKey(state);
        notificationBannerState.preview = preview && typeof preview === "object" ? preview : null;
        renderNotificationBanner(state);
    }

    function markNotificationReadFromShell(id) {
        var notificationId = String(id || "").trim();
        if (!notificationId) {
            return Promise.resolve(null);
        }

        return window.fetch("/api/notifications_api.php?action=markRead", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                Accept: "application/json"
            },
            body: new URLSearchParams({
                idsJson: JSON.stringify([notificationId])
            })
        }).then(parseJsonResponse).then(function (payload) {
            var state = authState();
            if (consumeUnauthorized(payload, "نشست شما برای خواندن اعلان‌ها منقضی شده است.")) {
                resetNavBadgeState();
                setNotificationBannerPreview(null, state);
                renderBottomNav(state);
                return null;
            }
            if (!payload || payload.success !== true) {
                return null;
            }
            updateNavBadgeState(navBadgeState.chatCount, payload.summary && payload.summary.unreadCount, userKey(state));
            setNotificationBannerPreview(payload.preview || null, state);
            renderBottomNav(state);
            return payload;
        }).catch(function () {
            return null;
        });
    }

    function consumeUnauthorized(payload, fallbackText) {
        var auth = window.Dent1402Auth;
        var message = fallbackText || "نشست شما منقضی شده است.";
        if (!auth || typeof auth !== "object") {
            return false;
        }

        try {
            if (typeof auth.handleUnauthorizedPayload === "function") {
                return !!auth.handleUnauthorizedPayload(payload, message);
            }
        } catch (_error) {
            // Ignore and continue fallback.
        }

        if (payload && (payload.loggedOut || payload.httpStatus === 401)) {
            if (typeof auth.markUnauthorized === "function") {
                auth.markUnauthorized((payload && payload.error) || message);
            }
            return true;
        }

        return false;
    }

    function payloadLooksUnauthorized(payload) {
        var auth = window.Dent1402Auth;
        if (auth && typeof auth.isUnauthorizedPayload === "function") {
            return auth.isUnauthorizedPayload(payload);
        }
        return !!(payload && (payload.loggedOut || payload.httpStatus === 401));
    }

    // A secondary badge endpoint (notifications/chat summary) returning 401 is
    // NOT authoritative. Logging the user out app-wide on its word caused a
    // visible logout/login flicker when one of those endpoints 401'd
    // transiently (e.g. mid-exam). Re-validate against the real session
    // endpoint instead; bootstrap only downgrades to logged-out when the
    // server cleanly confirms it, and keeps the session intact otherwise.
    function revalidateSessionAfterBadge401() {
        var auth = window.Dent1402Auth;
        if (auth && typeof auth.bootstrap === "function") {
            auth.bootstrap(true);
        }
    }

    function shouldRefetchPollCount(state) {
        if (!state.loggedIn) {
            return false;
        }

        var key = userKey(state);
        var now = Date.now();
        if (key !== pollNavState.lastUserKey) {
            return true;
        }
        return (now - pollNavState.lastFetchedAt) > POLL_COUNT_TTL_MS;
    }

    function updatePollCountState(count, key) {
        pollNavState.count = Math.max(0, Number(count) || 0);
        pollNavState.lastFetchedAt = Date.now();
        pollNavState.lastUserKey = key || "";
    }

    function resetPollCountState() {
        pollNavState.pending = false;
        pollNavState.count = 0;
        pollNavState.lastFetchedAt = 0;
        pollNavState.lastUserKey = "";
    }

    function shouldRefetchNavBadges(state) {
        if (!state.loggedIn) {
            return false;
        }

        if (window.navigator && window.navigator.onLine === false) {
            return false;
        }

        var key = userKey(state);
        var now = Date.now();
        if (key !== navBadgeState.lastUserKey) {
            return true;
        }
        return (now - navBadgeState.lastFetchedAt) > NAV_BADGE_TTL_MS;
    }

    function updateNavBadgeState(chatCount, notificationCount, key) {
        navBadgeState.chatCount = Math.max(0, Number(chatCount) || 0);
        navBadgeState.notificationCount = Math.max(0, Number(notificationCount) || 0);
        navBadgeState.lastFetchedAt = Date.now();
        navBadgeState.lastUserKey = key || "";
    }

    function resetNavBadgeState() {
        navBadgeState.pending = false;
        navBadgeState.chatCount = 0;
        navBadgeState.notificationCount = 0;
        navBadgeState.lastFetchedAt = 0;
        navBadgeState.lastUserKey = "";
        notificationBannerState.userKey = "";
        notificationBannerState.preview = null;
    }

    function applyNavBadgeEvent(kind, count) {
        var state = authState();
        if (!state.loggedIn) {
            resetNavBadgeState();
            renderBottomNav(state);
            renderNotificationBanner(state);
            return;
        }

        var key = userKey(state);
        if (key !== navBadgeState.lastUserKey) {
            navBadgeState.chatCount = 0;
            navBadgeState.notificationCount = 0;
        }
        navBadgeState.lastUserKey = key;
        navBadgeState.lastFetchedAt = Date.now();
        if (kind === "chat") {
            navBadgeState.chatCount = Math.max(0, Number(count) || 0);
        } else if (kind === "notifications") {
            navBadgeState.notificationCount = Math.max(0, Number(count) || 0);
        }
        renderBottomNav(state);
        renderNotificationBanner(state);
    }

    function fetchNavBadgeSummary(state) {
        if (!state.loggedIn || navBadgeState.pending) {
            return;
        }

        if (window.navigator && window.navigator.onLine === false) {
            return;
        }

        var key = userKey(state);
        if (key !== navBadgeState.lastUserKey) {
            navBadgeState.chatCount = 0;
            navBadgeState.notificationCount = 0;
            navBadgeState.lastUserKey = key;
            setNotificationBannerPreview(null, state);
            renderBottomNav(state);
        }

        navBadgeState.pending = true;
        Promise.all([
            window.fetch("/api/notifications_api.php?action=summary", {
                credentials: "same-origin",
                headers: {
                    Accept: "application/json"
                }
            }).then(parseJsonResponse),
            window.fetch("/chat/chat_api.php?action=navSummary", {
                credentials: "same-origin",
                headers: {
                    Accept: "application/json"
                }
            }).then(parseJsonResponse)
        ]).then(function (results) {
            var notificationsPayload = results[0] || {};
            var chatPayload = results[1] || {};
            if (
                payloadLooksUnauthorized(notificationsPayload)
                || payloadLooksUnauthorized(chatPayload)
            ) {
                // Throttle so a persistently-401 badge endpoint can't loop
                // (re-validate -> auth-change -> poll -> 401 -> ...), then
                // let the authoritative session check decide the real state.
                navBadgeState.lastFetchedAt = Date.now();
                navBadgeState.lastUserKey = key;
                revalidateSessionAfterBadge401();
                return;
            }

            if (!notificationsPayload.success || !chatPayload.success) {
                return;
            }

            updateNavBadgeState(
                chatPayload.summary && chatPayload.summary.unreadCount,
                notificationsPayload.summary && notificationsPayload.summary.unreadCount,
                key
            );
            setNotificationBannerPreview(notificationsPayload.preview || null, authState());
            renderBottomNav(authState());
        }).catch(function () {
            // Keep the last known counts on transient failures.
        }).finally(function () {
            navBadgeState.pending = false;
        });
    }

    function syncPollEntry(state) {
        resetPollCountState();
        if (!state.loggedIn) {
            resetNavBadgeState();
            if (!shellDisabled) {
                renderBottomNav(state);
            }
            renderNotificationBanner(state);
            return;
        }
        if (!shellDisabled) {
            renderBottomNav(state);
        }
        renderNotificationBanner(state);
        if (shouldRefetchNavBadges(state)) {
            fetchNavBadgeSummary(state);
        }
    }

    function createModal() {
        if (modal && modalBackdrop) {
            return;
        }

        modalBackdrop = document.createElement("div");
        modalBackdrop.className = "shell-modal-backdrop";
        modalBackdrop.style.position = "fixed";
        modalBackdrop.style.inset = "0";
        modalBackdrop.style.zIndex = "340";
        modalBackdrop.style.opacity = "0";
        modalBackdrop.style.pointerEvents = "none";
        modalBackdrop.addEventListener("click", closeModal);

        modal = document.createElement("div");
        modal.className = "shell-modal";
        modal.setAttribute("role", "dialog");
        modal.setAttribute("aria-modal", "true");
        modal.setAttribute("aria-hidden", "true");
        modal.style.position = "fixed";
        modal.style.top = "50%";
        modal.style.left = "50%";
        modal.style.right = "auto";
        modal.style.bottom = "auto";
        modal.style.zIndex = "350";
        modal.style.width = "min(420px, calc(100vw - 2rem))";
        modal.style.maxHeight = "calc(100dvh - env(safe-area-inset-top, 0px) - env(safe-area-inset-bottom, 0px) - 2rem)";
        modal.style.transform = "translate(-50%, calc(-50% + 16px)) scale(0.98)";
        modal.style.opacity = "0";
        modal.style.pointerEvents = "none";
        modal.style.overflowY = "auto";
        modal.style.webkitOverflowScrolling = "touch";
        modal.innerHTML = [
            '<h2 class="shell-modal__title">خروج از سایت</h2>',
            '<p class="shell-modal__desc">این لینک خارج از سایت باز می‌شود.</p>',
            '<div class="shell-modal__host"></div>',
            '<div class="shell-modal__actions">',
            '  <button type="button" class="shell-action-btn" data-shell-cancel>لغو</button>',
            '  <button type="button" class="shell-action-btn shell-action-btn-primary" data-shell-continue>ادامه</button>',
            "</div>"
        ].join("");

        modal.querySelector("[data-shell-cancel]").addEventListener("click", closeModal);
        modal.querySelector("[data-shell-continue]").addEventListener("click", continueExternal);

        document.body.appendChild(modalBackdrop);
        document.body.appendChild(modal);
    }

    function openExternalModal(anchor) {
        createModal();
        pendingExternal = {
            href: anchor.href,
            target: anchor.target
        };

        modal.querySelector(".shell-modal__host").textContent = new URL(anchor.href).host;
        modal.classList.add("is-open");
        modalBackdrop.classList.add("is-open");
        modal.setAttribute("aria-hidden", "false");
        modal.style.opacity = "1";
        modal.style.pointerEvents = "auto";
        modal.style.transform = "translate(-50%, -50%) scale(1)";
        modalBackdrop.style.opacity = "1";
        modalBackdrop.style.pointerEvents = "auto";
    }

    function closeModal() {
        pendingExternal = null;
        if (!modal || !modalBackdrop) {
            return;
        }

        modal.classList.remove("is-open");
        modalBackdrop.classList.remove("is-open");
        modal.setAttribute("aria-hidden", "true");
        modal.style.opacity = "0";
        modal.style.pointerEvents = "none";
        modal.style.transform = "translate(-50%, calc(-50% + 16px)) scale(0.98)";
        modalBackdrop.style.opacity = "0";
        modalBackdrop.style.pointerEvents = "none";
    }

    function continueExternal() {
        if (!pendingExternal) {
            closeModal();
            return;
        }

        var href = pendingExternal.href;
        var target = pendingExternal.target;
        closeModal();

        if (target === "_blank") {
            window.open(href, "_blank", "noopener");
            return;
        }

        window.location.href = href;
    }

    function shouldIntercept(anchor) {
        if (!anchor || anchor.dataset.bypassExternalWarning === "true") {
            return false;
        }

        var href = anchor.getAttribute("href");
        if (!href || href.charAt(0) === "#" || href.indexOf("javascript:") === 0 || href.indexOf("mailto:") === 0 || href.indexOf("tel:") === 0) {
            return false;
        }

        var url = new URL(anchor.href, window.location.origin);
        return url.origin !== window.location.origin;
    }

    function bindExternalLinks() {
        document.addEventListener("click", function (event) {
            var anchor = event.target.closest("a[href]");
            if (!shouldIntercept(anchor)) {
                return;
            }

            event.preventDefault();
            openExternalModal(anchor);
        });
    }

    function bindInstallButtons() {
        var installPromptDismissed = false;

        function currentPwaState() {
            if (window.Dent1402PWA && typeof window.Dent1402PWA.getState === "function") {
                return window.Dent1402PWA.getState();
            }
            return {
                installed: false,
                canInstall: false,
                isIOS: false
            };
        }

        function shouldShowInstallCard(detail) {
            return !!detail && !detail.installed && !installPromptDismissed && (!!detail.canInstall || !!detail.isIOS);
        }

        function updateInstallCards(detail) {
            var visible = shouldShowInstallCard(detail);
            document.querySelectorAll("[data-install-card]").forEach(function (card) {
                card.hidden = !visible;
                card.classList.toggle("is-visible", visible);
            });
        }

        function updateButtons(detail) {
            detail = detail || currentPwaState();
            updateInstallCards(detail);

            var buttons = document.querySelectorAll("[data-install-app]");
            buttons.forEach(function (button) {
                if (detail.installed) {
                    button.hidden = true;
                    return;
                }

                button.hidden = false;
                button.disabled = !detail.canInstall && !detail.isIOS;
                button.textContent = detail.canInstall ? "نصب روی گوشی" : (detail.isIOS ? "راهنمای نصب iOS" : "مرورگر پشتیبانی نمی‌کند");
            });

            var hints = document.querySelectorAll("[data-install-hint]");
            hints.forEach(function (hint) {
                if (detail.installed) {
                    hint.textContent = "نسخه نصب‌شده روی دستگاه فعال است.";
                } else if (detail.canInstall) {
                    hint.textContent = "برای نصب سریع، روی دکمه نصب بزن.";
                } else if (detail.isIOS) {
                    hint.textContent = "در Safari از گزینه اشتراک‌گذاری «Add to Home Screen» استفاده کن.";
                } else {
                    hint.textContent = "این مرورگر در حال حاضر نصب وب‌اپ را پشتیبانی نمی‌کند.";
                }
            });
        }

        document.addEventListener("click", function (event) {
            var dismissButton = event.target.closest("[data-install-dismiss]");
            if (dismissButton) {
                installPromptDismissed = true;
                updateButtons(currentPwaState());
                return;
            }

            var button = event.target.closest("[data-install-app]");
            if (!button || !window.Dent1402PWA) {
                return;
            }

            var detail = currentPwaState();
            window.Dent1402PWA.promptInstall().then(function () {
                if (detail.canInstall) {
                    installPromptDismissed = true;
                    updateButtons(currentPwaState());
                }
            });
        });

        if (window.Dent1402PWA) {
            window.Dent1402PWA.onChange(updateButtons);
        }

        window.addEventListener("dent1402:pwa-state", function (event) {
            updateButtons(event.detail);
        });
    }

    function syncAuthUi(state) {
        if (maybeRedirectProsthesis(state)) {
            return;
        }
        applySiteAppearance(state);
        applyBranding(state);
        syncAuthLinks(state);
        renderHeaderAccount(state);
        syncPollEntry(state);
        updateSearchVisibility(state);
    }

    var searchState = {
        header: null,
        trigger: null,
        panel: null,
        input: null,
        results: null,
        status: null,
        backdrop: null,
        open: false,
        seeded: false,
        timer: 0,
        requestId: 0,
        lastQuery: ""
    };

    function searchIconMarkup() {
        return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.8"/><path d="M16 16L20 20" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
    }

    function closeIconMarkup() {
        return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6L18 18M18 6L6 18" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>';
    }

    function searchCohortParam() {
        try {
            var params = new URLSearchParams(window.location.search || "");
            var cohort = params.get("cohort");
            return cohort ? String(cohort) : "";
        } catch (_error) {
            return "";
        }
    }

    function localeDigits(value) {
        var text = String(value);
        if (window.Dent1402Locale && typeof window.Dent1402Locale.toPersianDigits === "function") {
            return window.Dent1402Locale.toPersianDigits(text);
        }
        return text;
    }

    function setSearchStatus(text) {
        if (searchState.status) {
            searchState.status.textContent = text || "";
        }
    }

    function updateSearchVisibility(state) {
        if (!searchState.trigger) {
            return;
        }
        var show = !!(state && state.loggedIn);
        searchState.trigger.hidden = !show;
        if (!show && searchState.open) {
            closeSearch();
        }
    }

    function openSearch() {
        if (!searchState.panel || searchState.open) {
            return;
        }
        closeHeaderAccountMenu(false);
        searchState.open = true;
        searchState.backdrop.hidden = false;
        searchState.panel.hidden = false;
        document.body.classList.add("shell-search-active");
        if (searchState.header) {
            searchState.header.classList.add("site-header--searching");
        }
        if (searchState.trigger) {
            searchState.trigger.setAttribute("aria-expanded", "true");
        }
        window.requestAnimationFrame(function () {
            searchState.panel.classList.add("is-open");
            searchState.backdrop.classList.add("is-open");
            if (searchState.input) {
                searchState.input.focus();
            }
        });
    }

    function closeSearch() {
        if (!searchState.panel || !searchState.open) {
            return;
        }
        searchState.open = false;
        searchState.panel.classList.remove("is-open");
        searchState.backdrop.classList.remove("is-open");
        document.body.classList.remove("shell-search-active");
        if (searchState.header) {
            searchState.header.classList.remove("site-header--searching");
        }
        if (searchState.trigger) {
            searchState.trigger.setAttribute("aria-expanded", "false");
        }
        window.setTimeout(function () {
            if (!searchState.open) {
                searchState.panel.hidden = true;
                searchState.backdrop.hidden = true;
            }
        }, 200);
    }

    function runSearch(rawValue, immediate) {
        var query = String(rawValue || "").trim();
        if (searchState.timer && immediate) {
            window.clearTimeout(searchState.timer);
            searchState.timer = 0;
        }
        if (query === searchState.lastQuery && !immediate) {
            return;
        }
        searchState.lastQuery = query;
        if (searchState.results) {
            searchState.results.textContent = "";
        }
        if (query.length < 2) {
            setSearchStatus(query.length === 0 ? "نام منبع، درس یا جلسه آزمون را بنویسید." : "حداقل ۲ نویسه وارد کنید.");
            return;
        }
        setSearchStatus("در حال جستجو...");
        var requestId = ++searchState.requestId;
        var url = "/api/search_api.php?action=query&q=" + encodeURIComponent(query);
        var cohort = searchCohortParam();
        if (cohort) {
            url += "&cohort=" + encodeURIComponent(cohort);
        }
        window.fetch(url, {
            method: "GET",
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        }).then(parseJsonResponse).then(function (payload) {
            if (requestId !== searchState.requestId) {
                return;
            }
            if (!payload || payload.success !== true) {
                setSearchStatus(payload && payload.error ? payload.error : "جستجو ناموفق بود.");
                return;
            }
            renderSearchResults(payload);
        }).catch(function () {
            if (requestId === searchState.requestId) {
                setSearchStatus("اتصال برقرار نشد. دوباره تلاش کنید.");
            }
        });
    }

    function renderSearchResults(payload) {
        var results = Array.isArray(payload.results) ? payload.results : [];
        if (!searchState.results) {
            return;
        }
        searchState.results.textContent = "";
        if (results.length === 0) {
            setSearchStatus("نتیجه‌ای برای «" + (payload.query || "") + "» پیدا نشد.");
            return;
        }
        var total = payload.counts && payload.counts.total ? payload.counts.total : results.length;
        setSearchStatus(localeDigits(total) + " نتیجه پیدا شد.");
        results.forEach(function (item) {
            if (!item || !item.href) {
                return;
            }
            var link = document.createElement("a");
            link.className = "shell-search-result";
            link.href = item.href;
            if (item.external) {
                link.target = "_blank";
                link.rel = "noopener noreferrer";
            }
            var badge = document.createElement("span");
            badge.className = "shell-search-result__type shell-search-result__type--" + (item.type || "note");
            badge.textContent = item.typeLabel || "";
            var body = document.createElement("span");
            body.className = "shell-search-result__body";
            var title = document.createElement("strong");
            title.className = "shell-search-result__title";
            title.textContent = item.title || "";
            body.appendChild(title);
            if (item.subtitle) {
                var sub = document.createElement("span");
                sub.className = "shell-search-result__subtitle";
                sub.textContent = item.subtitle;
                body.appendChild(sub);
            }
            link.appendChild(badge);
            link.appendChild(body);
            link.addEventListener("click", closeSearch);
            searchState.results.appendChild(link);
        });
    }

    function ensureHeaderSearch() {
        if (searchState.seeded) {
            return;
        }
        if (shellHeaderDisabled || document.body.classList.contains("chat-page")) {
            return;
        }
        var header = document.querySelector(".site-header");
        if (!header) {
            return;
        }
        searchState.seeded = true;
        searchState.header = header;

        var tools = header.querySelector(".shell-header-tools");
        if (!tools) {
            tools = document.createElement("div");
            tools.className = "shell-header-tools";
            header.appendChild(tools);
        }

        var actions = header.querySelector(".header-actions");
        var themeButton = actions ? actions.querySelector("[data-theme-toggle]") : null;
        if (themeButton) {
            tools.appendChild(themeButton);
        }

        var trigger = document.createElement("button");
        trigger.type = "button";
        trigger.className = "shell-search-trigger";
        trigger.setAttribute("aria-label", "جستجو در سایت");
        trigger.setAttribute("aria-haspopup", "dialog");
        trigger.setAttribute("aria-expanded", "false");
        trigger.innerHTML = searchIconMarkup();
        trigger.hidden = true;
        trigger.addEventListener("click", openSearch);
        tools.appendChild(trigger);
        searchState.trigger = trigger;

        var backdrop = document.createElement("div");
        backdrop.className = "shell-search-backdrop";
        backdrop.hidden = true;
        backdrop.addEventListener("click", closeSearch);
        document.body.appendChild(backdrop);
        searchState.backdrop = backdrop;

        var panel = document.createElement("div");
        panel.className = "shell-search-panel";
        panel.setAttribute("role", "dialog");
        panel.setAttribute("aria-label", "جستجوی سراسری");
        panel.hidden = true;
        panel.innerHTML = [
            '<form class="shell-search-form" role="search" autocomplete="off">',
            '  <span class="shell-search-form__icon" aria-hidden="true">' + searchIconMarkup() + "</span>",
            '  <input type="search" class="shell-search-input" enterkeyhint="search" placeholder="جستجوی منابع و آزمون‌ها..." aria-label="عبارت جستجو">',
            '  <button type="button" class="shell-search-close" aria-label="بستن جستجو">' + closeIconMarkup() + "</button>",
            "</form>",
            '<p class="shell-search-status" aria-live="polite"></p>',
            '<div class="shell-search-results"></div>'
        ].join("");
        document.body.appendChild(panel);
        searchState.panel = panel;
        searchState.input = panel.querySelector(".shell-search-input");
        searchState.results = panel.querySelector(".shell-search-results");
        searchState.status = panel.querySelector(".shell-search-status");

        panel.querySelector(".shell-search-form").addEventListener("submit", function (event) {
            event.preventDefault();
            runSearch(searchState.input.value, true);
        });
        panel.querySelector(".shell-search-close").addEventListener("click", closeSearch);
        searchState.input.addEventListener("input", function () {
            if (searchState.timer) {
                window.clearTimeout(searchState.timer);
            }
            searchState.timer = window.setTimeout(function () {
                runSearch(searchState.input.value, false);
            }, 260);
        });

        updateSearchVisibility(authState());
    }

    function startsOnHorizontalScroller(target) {
        var node = target;
        while (node && node !== document.body && node.nodeType === 1) {
            if (node.closest && node.closest(".shell-bottom-nav")) {
                return true;
            }
            var style = window.getComputedStyle(node);
            var overflowX = style.overflowX;
            if ((overflowX === "auto" || overflowX === "scroll") && node.scrollWidth > node.clientWidth + 4) {
                return true;
            }
            node = node.parentNode;
        }
        return false;
    }

    function initNavPrefetch() {
        if (shellDisabled) {
            return;
        }
        var prefetched = {};
        function prefetch(href) {
            if (!href || href.charAt(0) !== "/" || prefetched[href]) {
                return;
            }
            prefetched[href] = true;
            try {
                var link = document.createElement("link");
                link.rel = "prefetch";
                link.href = href;
                document.head.appendChild(link);
            } catch (err) {
                /* prefetch is best-effort */
            }
        }
        // Warm the next page as soon as the finger lands / pointer enters, so the
        // cross-document view transition has a ready document and section switches
        // feel instant instead of freezing on the snapshot while the page loads.
        ["pointerdown", "touchstart", "pointerenter"].forEach(function (type) {
            document.addEventListener(type, function (event) {
                var target = event.target;
                var link = target && target.closest ? target.closest(".shell-bottom-nav__link") : null;
                if (link) {
                    prefetch(link.getAttribute("href"));
                }
            }, { passive: true, capture: true });
        });
    }

    function initNavSwipe() {
        if (shellDisabled || !("ontouchstart" in window)) {
            return;
        }
        var startX = 0;
        var startY = 0;
        var startedAt = 0;
        var tracking = false;
        var blocked = false;

        document.addEventListener("touchstart", function (event) {
            if (!bottomNavSwipeEnabled()) {
                tracking = false;
                return;
            }
            if (!event.touches || event.touches.length !== 1 || !navInner || !navInner.isConnected) {
                tracking = false;
                return;
            }
            if (searchState && searchState.open) {
                tracking = false;
                return;
            }
            var touch = event.touches[0];
            startX = touch.clientX;
            startY = touch.clientY;
            startedAt = Date.now();
            blocked = startsOnHorizontalScroller(event.target);
            tracking = true;
        }, { passive: true });

        document.addEventListener("touchend", function (event) {
            if (!tracking || blocked) {
                tracking = false;
                return;
            }
            tracking = false;
            var touch = event.changedTouches && event.changedTouches[0];
            if (!touch) {
                return;
            }
            var dx = touch.clientX - startX;
            var dy = touch.clientY - startY;
            if (Date.now() - startedAt > 700) {
                return;
            }
            if (Math.abs(dx) < 72 || Math.abs(dy) > 52 || Math.abs(dx) < Math.abs(dy) * 1.6) {
                return;
            }
            var links = Array.prototype.slice.call(navInner.querySelectorAll(".shell-bottom-nav__link"));
            if (links.length < 2) {
                return;
            }
            var activeIndex = -1;
            for (var i = 0; i < links.length; i++) {
                if (links[i].classList.contains("is-active")) {
                    activeIndex = i;
                    break;
                }
            }
            if (activeIndex === -1) {
                return;
            }
            // DOM order matches RTL visual order (index 0 sits on the right).
            // Match the bottom bar's RTL feel: swiping right moves to the
            // visually-left tab (next index), and swiping left moves right.
            var targetIndex = dx > 0 ? activeIndex + 1 : activeIndex - 1;
            if (targetIndex < 0 || targetIndex >= links.length) {
                return;
            }
            var href = links[targetIndex].getAttribute("href");
            if (href) {
                window.location.href = href;
            }
        }, { passive: true });
    }

    function initShellScrollState() {
        if (!document.body || document.body.classList.contains("chat-page")) {
            return;
        }

        var framePending = false;
        var sync = function () {
            framePending = false;
            document.body.classList.toggle("is-shell-scrolled", window.scrollY > 8);
        };
        var requestSync = function () {
            if (framePending) {
                return;
            }
            framePending = true;
            window.requestAnimationFrame(sync);
        };

        sync();
        window.addEventListener("scroll", requestSync, { passive: true });
    }

    function init() {
        document.body.classList.add("has-app-shell");
        if (shellDisabled) {
            document.body.classList.add("app-shell-hidden");
        }

        bindExternalLinks();
        bindInstallButtons();
        normalizeSiteHeader();
        normalizePageTopbars();
        ensureHeaderSearch();
        applySiteAppearance(authState());
        syncAuthUi(authState());
        initShellScrollState();
        initNavSwipe();
        initNavPrefetch();

        if (window.Dent1402Auth && typeof window.Dent1402Auth.onChange === "function") {
            window.Dent1402Auth.onChange(syncAuthUi);
        }

        window.addEventListener("dent1402:notifications-change", function (event) {
            var detail = event && event.detail ? event.detail : {};
            applyNavBadgeEvent("notifications", detail.unreadCount);
            if (Object.prototype.hasOwnProperty.call(detail, "preview")) {
                setNotificationBannerPreview(detail.preview || null, authState());
            }
        });
        window.addEventListener("dent1402:chat-unread-change", function (event) {
            var detail = event && event.detail ? event.detail : {};
            applyNavBadgeEvent("chat", detail.unreadCount);
        });
        window.addEventListener("focus", function () {
            syncPollEntry(authState());
        });
        window.addEventListener("online", function () {
            syncPollEntry(authState());
        });
        document.addEventListener("visibilitychange", function () {
            if (!document.hidden) {
                syncPollEntry(authState());
            }
        });
        window.setInterval(function () {
            if (document.hidden || (window.navigator && window.navigator.onLine === false)) {
                return;
            }
            syncPollEntry(authState());
        }, NAV_BADGE_TTL_MS);

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") {
                if (headerAccount.open) {
                    closeHeaderAccountMenu(true);
                }
                if (searchState.open) {
                    closeSearch();
                }
                closeModal();
            }
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init, { once: true });
    } else {
        init();
    }
})();
