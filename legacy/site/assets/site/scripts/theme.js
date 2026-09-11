(function () {
    "use strict";

    var STORAGE_KEY = "dent1402-theme";
    var media = window.matchMedia ? window.matchMedia("(prefers-color-scheme: dark)") : null;
    var listeners = [];
    var manualTheme = "";
    var digitObserver = null;
    var LAUNCH_SPLASH_CLASS = "dent-launch-splash";
    var LAUNCH_SPLASH_LEAVING_CLASS = "dent-launch-splash--leaving";
    var LAUNCH_SPLASH_STYLE_ID = "dent1402-launch-splash-style";
    var LAUNCH_SPLASH_NODE_ID = "dent1402-launch-splash";
    var LAUNCH_SPLASH_MIN_VISIBLE_MS = 220;
    var LAUNCH_SPLASH_MAX_VISIBLE_MS = 420;
    var LAUNCH_SPLASH_FADE_MS = 180;
    var LAUNCH_SPLASH_LOGO_URL = "/assets/images/logo.png?v=20260422-brand1";
    var LAUNCH_SPLASH_COLOR_LIGHT = "#f2f3f5";
    var LAUNCH_SPLASH_COLOR_DARK = "#101827";
    var launchSplashMounted = false;
    var launchSplashNode = null;
    var inputViewportFrame = 0;
    var inputViewportSignature = "";
    var digitLocalizationQueue = [];
    var digitLocalizationQueuedRoots = typeof WeakSet === "function" ? new WeakSet() : null;
    var digitLocalizationScheduled = false;
    var DIGIT_LOCALIZATION_FALLBACK_DELAY_MS = 36;
    var DIGIT_LOCALIZATION_BUDGET_MS = 12;
    var persianDigits = ["۰", "۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹"];
    var CRITICAL_ACCENT_FONTS = [
        "/fonts/AbarHigh-Bold.woff2",
        "/fonts/AbarHigh-ExtraBold.woff2"
    ];
    var ANALYTICS_ENDPOINT = "/api/analytics_api.php";
    var ANALYTICS_VISITOR_STORAGE_KEY = "dent1402-analytics-visitor-id";
    var ANALYTICS_VISIT_STORAGE_KEY = "dent1402-analytics-visit-id";
    var analyticsInitialPageTracked = false;
    var analyticsDownloadSignature = "";
    var analyticsDownloadAt = 0;
    var analyticsDownloadDomains = {
        "dl.dentistry1402tums.ir": true
    };
    var analyticsDownloadExtensions = /\.(pdf|zip|rar|7z|docx?|xlsx?|xls|pptx?|ppt|csv|txt|epub|mp3|mp4|png|jpe?g|webp|gif)$/i;

    function ensureCriticalAccentFontPreloads() {
        var head = document.head || document.documentElement;
        if (!head) {
            return;
        }

        CRITICAL_ACCENT_FONTS.forEach(function (fontUrl) {
            if (head.querySelector('link[rel="preload"][href="' + fontUrl + '"]')) {
                return;
            }

            var link = document.createElement("link");
            link.rel = "preload";
            link.as = "font";
            link.type = "font/woff2";
            link.href = fontUrl;
            link.crossOrigin = "anonymous";
            link.setAttribute("fetchpriority", "high");
            head.appendChild(link);
        });
    }

    ensureCriticalAccentFontPreloads();

    try {
        var stored = window.localStorage.getItem(STORAGE_KEY);
        if (stored === "light" || stored === "dark") {
            manualTheme = stored;
        }
    } catch (error) {
        manualTheme = "";
    }

    function resolvedTheme() {
        if (manualTheme) {
            return manualTheme;
        }

        if (media && media.matches) {
            return "dark";
        }

        return "light";
    }

    function themeColor(theme) {
        return theme === "dark" ? "#0d1420" : "#eef2f7";
    }

    function launchSplashColor(theme) {
        return theme === "dark" ? LAUNCH_SPLASH_COLOR_DARK : LAUNCH_SPLASH_COLOR_LIGHT;
    }

    function currentPath() {
        var path = window.location && window.location.pathname ? String(window.location.pathname) : "/";
        return path || "/";
    }

    function isChatLaunchRoute() {
        return /^\/chat(?:\/|$)/i.test(currentPath());
    }

    function ensureViewportScaleLock() {
        var meta = document.querySelector('meta[name="viewport"]');
        if (!meta) {
            meta = document.createElement("meta");
            meta.name = "viewport";
            (document.head || document.documentElement).appendChild(meta);
        }

        var content = String(meta.getAttribute("content") || "width=device-width, initial-scale=1.0");
        var parts = content.split(",").map(function (part) {
            return part.trim();
        }).filter(function (part) {
            return part && !/^(maximum-scale|user-scalable)\s*=/i.test(part);
        });

        if (!parts.some(function (part) { return /^width\s*=/i.test(part); })) {
            parts.unshift("width=device-width");
        }
        if (!parts.some(function (part) { return /^initial-scale\s*=/i.test(part); })) {
            parts.push("initial-scale=1.0");
        }

        // Keep pinch-zoom available site-wide; iOS auto-zoom is prevented with 16px inputs in CSS.
        meta.setAttribute("content", parts.join(", "));
        meta.dataset.globalScaleLock = "relaxed";
    }

    function isTextInputElement(node) {
        if (!node || !node.matches) {
            return false;
        }

        return node.matches("textarea, select, [contenteditable='true'], [contenteditable=''], input:not([type='checkbox']):not([type='radio']):not([type='range']):not([type='file']):not([type='color']):not([type='button']):not([type='submit']):not([type='reset']):not([type='hidden'])");
    }

    function isTextInputFocused() {
        return isTextInputElement(document.activeElement);
    }

    function isSoftKeyboardOpen() {
        if (!isTextInputFocused()) {
            return false;
        }

        var compactViewport = window.matchMedia && window.matchMedia("(max-width: 980px)").matches;
        var touchPoints = Number(window.navigator.maxTouchPoints || 0);
        if (!compactViewport && touchPoints < 1) {
            return false;
        }

        if (!window.visualViewport) {
            return compactViewport && touchPoints > 0;
        }

        var vv = window.visualViewport;
        var viewportHeight = Math.max(0, Number(vv.height || 0));
        var viewportOffsetTop = Math.max(0, Number(vv.offsetTop || 0));
        var layoutHeight = Math.max(0, Number(window.innerHeight || document.documentElement.clientHeight || 0));
        var hiddenHeight = layoutHeight - (viewportHeight + viewportOffsetTop);
        return hiddenHeight > 92;
    }

    function syncInputViewportState() {
        ensureViewportScaleLock();
        if (!document.body) {
            return;
        }
        var focused = isTextInputFocused();
        var keyboardOpen = isSoftKeyboardOpen();
        var signature = (focused ? "1" : "0") + ":" + (keyboardOpen ? "1" : "0");
        if (signature === inputViewportSignature) {
            return;
        }
        inputViewportSignature = signature;
        document.body.classList.toggle("site-input-focus", focused);
        document.body.classList.toggle("site-keyboard-open", keyboardOpen);
    }

    function queueInputViewportSync() {
        if (inputViewportFrame) {
            return;
        }
        inputViewportFrame = window.requestAnimationFrame(function () {
            inputViewportFrame = 0;
            syncInputViewportState();
        });
    }

    function parseVersionFromUrl(rawUrl) {
        if (!rawUrl) {
            return "";
        }

        try {
            var parsed = new URL(rawUrl, window.location.origin);
            var queryVersion = (parsed.searchParams.get("v") || "").trim();
            if (queryVersion) {
                return queryVersion;
            }
        } catch (error) {
            // Ignore invalid URLs and keep fallback parsing.
        }

        var fallbackMatch = String(rawUrl).match(/[?&]v=([^&#]+)/i);
        return fallbackMatch && fallbackMatch[1] ? decodeURIComponent(fallbackMatch[1]) : "";
    }

    function resolveLaunchBuildLabel() {
        var manifestTag = document.querySelector('link[rel="manifest"]');
        var fromManifest = parseVersionFromUrl(manifestTag && manifestTag.getAttribute("href"));
        if (fromManifest) {
            return fromManifest;
        }

        var pwaScript = document.querySelector('script[src*="/assets/site/scripts/pwa.js"]');
        var fromPwaScript = parseVersionFromUrl(pwaScript && pwaScript.getAttribute("src"));
        if (fromPwaScript) {
            return fromPwaScript;
        }

        var versionMeta = document.querySelector('meta[name="app-version"]');
        if (versionMeta && versionMeta.content) {
            return String(versionMeta.content).trim();
        }

        return "";
    }

    function navigationType() {
        if (window.performance && typeof window.performance.getEntriesByType === "function") {
            var entries = window.performance.getEntriesByType("navigation");
            if (entries && entries.length && entries[0] && entries[0].type) {
                return entries[0].type;
            }
        }

        if (window.performance && window.performance.navigation) {
            if (window.performance.navigation.type === 1) {
                return "reload";
            }

            if (window.performance.navigation.type === 2) {
                return "back_forward";
            }
        }

        return "navigate";
    }

    function isStandaloneDisplayMode() {
        return !!(
            window.navigator.standalone === true ||
            (window.matchMedia && window.matchMedia("(display-mode: standalone)").matches)
        );
    }

    function isSameOriginReferrer() {
        if (!document.referrer) {
            return false;
        }

        try {
            return new URL(document.referrer, window.location.origin).origin === window.location.origin;
        } catch (error) {
            return false;
        }
    }

    function analyticsStorageAvailable(storageName) {
        try {
            return !!window[storageName];
        } catch (error) {
            return false;
        }
    }

    function analyticsReadStorage(storageName, key) {
        if (!analyticsStorageAvailable(storageName)) {
            return "";
        }
        try {
            return String(window[storageName].getItem(key) || "");
        } catch (error) {
            return "";
        }
    }

    function analyticsWriteStorage(storageName, key, value) {
        if (!analyticsStorageAvailable(storageName)) {
            return;
        }
        try {
            window[storageName].setItem(key, String(value || ""));
        } catch (error) {
            // Ignore quota and privacy-mode failures.
        }
    }

    function analyticsRandomId(prefix) {
        var entropy = "";
        if (window.crypto && typeof window.crypto.getRandomValues === "function") {
            var bytes = new Uint8Array(8);
            window.crypto.getRandomValues(bytes);
            entropy = Array.prototype.map.call(bytes, function (byte) {
                return byte.toString(16).padStart(2, "0");
            }).join("");
        } else {
            entropy = Math.random().toString(16).slice(2) + Date.now().toString(16);
        }
        return prefix + "-" + entropy.slice(0, 16);
    }

    function analyticsVisitorId() {
        var existing = analyticsReadStorage("localStorage", ANALYTICS_VISITOR_STORAGE_KEY);
        if (existing) {
            return existing;
        }
        var created = analyticsRandomId("visitor");
        analyticsWriteStorage("localStorage", ANALYTICS_VISITOR_STORAGE_KEY, created);
        return created;
    }

    function analyticsVisitId() {
        var existing = analyticsReadStorage("sessionStorage", ANALYTICS_VISIT_STORAGE_KEY);
        if (existing) {
            return existing;
        }
        var created = analyticsRandomId("visit");
        analyticsWriteStorage("sessionStorage", ANALYTICS_VISIT_STORAGE_KEY, created);
        return created;
    }

    function analyticsPagePath() {
        var path = window.location && window.location.pathname ? String(window.location.pathname) : "/";
        var query = window.location && window.location.search ? String(window.location.search) : "";
        return (path || "/") + query;
    }

    function analyticsNormalizedCohort(value) {
        var clean = String(value == null ? "" : value).trim().toLowerCase();
        if (!clean || clean === "main" || clean === "1402" || clean === "dentistry-1402") {
            return clean ? "dentistry-1402" : "";
        }
        if (clean === "1403" || clean === "dentistry-1403") {
            return "dentistry-1403";
        }
        if (clean === "1404" || clean === "dentistry-1404") {
            return "dentistry-1404";
        }
        if (clean === "site-users" || clean === "siteusers" || clean === "external" || clean === "external-users") {
            return "site-users";
        }
        if (clean === "prosthesis-1402" || clean === "prosthesis1402") {
            return "prosthesis-1402";
        }
        return clean;
    }

    function analyticsResolveCohort(snapshot) {
        if (snapshot && snapshot.user && snapshot.user.cohortKey) {
            return analyticsNormalizedCohort(snapshot.user.cohortKey);
        }

        var params = new URLSearchParams(window.location.search || "");
        var fromQuery = analyticsNormalizedCohort(params.get("cohort") || "");
        if (fromQuery) {
            return fromQuery;
        }

        var body = document.body && document.body.dataset ? document.body.dataset : {};
        var candidates = [
            body.cohort,
            body.cohortKey,
            body.notesCohort,
            body.chatCohort,
            body.examsCohort
        ];
        for (var index = 0; index < candidates.length; index += 1) {
            var normalized = analyticsNormalizedCohort(candidates[index]);
            if (normalized) {
                return normalized;
            }
        }

        var path = currentPath();
        if (path.indexOf("/notes/1403/") === 0) {
            return "dentistry-1403";
        }
        if (path.indexOf("/notes/1404/") === 0) {
            return "dentistry-1404";
        }
        return "";
    }

    function analyticsSnapshot() {
        var auth = window.Dent1402Auth && typeof window.Dent1402Auth === "object" ? window.Dent1402Auth : null;
        var state = auth && typeof auth.getState === "function" ? auth.getState() : null;
        var loggedIn = !!(state && state.loggedIn && state.user);
        return {
            status: state && state.status ? String(state.status) : "unknown",
            loggedIn: loggedIn,
            user: loggedIn ? state.user : null,
            cohort: analyticsResolveCohort(state || null)
        };
    }

    function analyticsAuthPending(snapshot) {
        var status = snapshot && snapshot.status ? String(snapshot.status) : "";
        return status === "session-restoring" || status === "logging-in" || status === "logging-out";
    }

    function analyticsPageFamily(path) {
        var cleanPath = String(path || analyticsPagePath()).split("?")[0] || "/";
        var segments = cleanPath.replace(/^\/+|\/+$/g, "").split("/").filter(Boolean);
        var head = segments.length ? String(segments[0]).toLowerCase() : "home";
        switch (head) {
            case "":
                return "home";
            case "app":
            case "admin":
            case "account":
            case "chat":
            case "exams":
            case "notes":
            case "forms":
            case "grades":
            case "resources":
            case "files":
            case "paste":
            case "navid":
                return head;
            case "buy":
            case "payments":
                return "buy";
            case "html":
            case "html-uploader":
                return "html";
            default:
                return head || "other";
        }
    }

    function analyticsBuildFormData(action, payload) {
        var formData = new FormData();
        formData.append("action", action);
        Object.keys(payload || {}).forEach(function (key) {
            var value = payload[key];
            if (value === undefined || value === null || value === "") {
                return;
            }
            formData.append(key, String(value));
        });
        return formData;
    }

    function analyticsSend(action, payload) {
        var formData = analyticsBuildFormData(action, payload || {});
        if (navigator.sendBeacon) {
            try {
                var sent = navigator.sendBeacon(ANALYTICS_ENDPOINT, formData);
                if (sent) {
                    return Promise.resolve(true);
                }
            } catch (error) {
                // Fall through to fetch keepalive.
            }
        }

        var body = new URLSearchParams();
        body.set("action", action);
        Object.keys(payload || {}).forEach(function (key) {
            var value = payload[key];
            if (value === undefined || value === null || value === "") {
                return;
            }
            body.set(key, String(value));
        });
        return fetch(ANALYTICS_ENDPOINT, {
            method: "POST",
            credentials: "same-origin",
            keepalive: true,
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                "Accept": "application/json"
            },
            body: body
        }).catch(function () {
            return false;
        });
    }

    function analyticsTrackPageView(extraPayload) {
        var snapshot = analyticsSnapshot();
        var payload = Object.assign({
            path: analyticsPagePath(),
            title: document.title || "",
            cohort: snapshot.cohort,
            visitorId: analyticsVisitorId(),
            visitId: analyticsVisitId()
        }, extraPayload || {});
        return analyticsSend("trackPageView", payload);
    }

    function analyticsIsDownloadLike(anchor, url) {
        if (!anchor || !url) {
            return false;
        }
        if (anchor.dataset && anchor.dataset.analyticsDownload) {
            return true;
        }
        if (anchor.hasAttribute("download")) {
            return true;
        }
        if (url.pathname.indexOf("/api/content_tools_api.php") === 0 && /(?:^|&)action=downloadfile/i.test(url.search.slice(1))) {
            return false;
        }
        if (url.pathname.indexOf("/files/f/") === 0) {
            return false;
        }
        if (analyticsDownloadDomains[url.hostname.toLowerCase()]) {
            return true;
        }
        return analyticsDownloadExtensions.test(url.pathname || "");
    }

    function analyticsTrackDownload(anchor, explicitPayload) {
        var payload = explicitPayload || {};
        var href = payload.href || (anchor && anchor.href) || "";
        if (!href) {
            return Promise.resolve(false);
        }

        var snapshot = analyticsSnapshot();
        var label = payload.label;
        if (!label && anchor) {
            label = (anchor.dataset && anchor.dataset.analyticsLabel) || anchor.getAttribute("aria-label") || anchor.textContent || "";
        }
        label = String(label || "").replace(/\s+/g, " ").trim();

        var sourcePath = payload.sourcePath || analyticsPagePath();
        var sourceFamily = payload.sourceFamily || analyticsPageFamily(sourcePath);
        var signature = [href, label, sourcePath].join("|");
        if (signature === analyticsDownloadSignature && Date.now() - analyticsDownloadAt < 1500) {
            return Promise.resolve(false);
        }
        analyticsDownloadSignature = signature;
        analyticsDownloadAt = Date.now();

        return analyticsSend("trackDownload", {
            href: href,
            label: label || "منبع",
            sourcePath: sourcePath,
            sourceFamily: sourceFamily,
            cohort: payload.cohort || snapshot.cohort
        });
    }

    function bindAnalyticsDownloadTracking() {
        document.addEventListener("click", function (event) {
            var anchor = event.target && event.target.closest ? event.target.closest("a[href]") : null;
            if (!anchor) {
                return;
            }

            var resolvedUrl;
            try {
                resolvedUrl = new URL(anchor.href, window.location.origin);
            } catch (error) {
                return;
            }

            if (!analyticsIsDownloadLike(anchor, resolvedUrl)) {
                return;
            }

            analyticsTrackDownload(anchor);
        }, true);
    }

    function scheduleInitialAnalyticsTracking() {
        if (analyticsInitialPageTracked) {
            return;
        }

        var attempts = 0;
        function attempt(force) {
            if (analyticsInitialPageTracked) {
                return;
            }
            attempts += 1;
            var snapshot = analyticsSnapshot();
            if (!force && analyticsAuthPending(snapshot) && attempts < 6) {
                window.setTimeout(function () {
                    attempt(false);
                }, 180);
                return;
            }
            analyticsInitialPageTracked = true;
            analyticsTrackPageView();
        }

        window.setTimeout(function () {
            attempt(false);
        }, 220);
    }

    function shouldShowLaunchSplash() {
        if (isChatLaunchRoute()) {
            return false;
        }

        var navType = navigationType();
        if (navType === "back_forward") {
            return false;
        }

        if (!document.referrer) {
            return true;
        }

        if (isStandaloneDisplayMode() && !isSameOriginReferrer()) {
            return true;
        }

        return !isSameOriginReferrer();
    }

    function ensureLaunchSplashStyle() {
        if (document.getElementById(LAUNCH_SPLASH_STYLE_ID)) {
            return;
        }

        var style = document.createElement("style");
        style.id = LAUNCH_SPLASH_STYLE_ID;
        style.textContent = [
            "html." + LAUNCH_SPLASH_CLASS + ",html." + LAUNCH_SPLASH_CLASS + " body{background:#f2f3f5!important;}",
            "html[data-theme=\"dark\"]." + LAUNCH_SPLASH_CLASS + ",html[data-theme=\"dark\"]." + LAUNCH_SPLASH_CLASS + " body{background:#101827!important;}",
            "#" + LAUNCH_SPLASH_NODE_ID + "{position:fixed;inset:0;z-index:10020;display:grid;grid-template-rows:1fr auto auto;justify-items:center;align-items:center;padding:clamp(2rem,6vh,4rem) 1.4rem calc(1.8rem + env(safe-area-inset-bottom,0px));background:#f2f3f5;color:#111827;opacity:1;pointer-events:none;transition:opacity var(--motion-fast,180ms) var(--motion-ease-soft,cubic-bezier(0.2,0.88,0.24,1));}",
            "html[data-theme=\"dark\"] #" + LAUNCH_SPLASH_NODE_ID + "{background:#101827;color:#edf3ff;}",
            "#" + LAUNCH_SPLASH_NODE_ID + " .dent-launch-splash__center{align-self:center;display:grid;justify-items:center;gap:1.1rem;transform:translateY(4vh);transition:transform var(--motion-base,320ms) var(--motion-ease-soft,cubic-bezier(0.2,0.88,0.24,1)),opacity var(--motion-fast,180ms) var(--motion-ease-soft,cubic-bezier(0.2,0.88,0.24,1));}",
            "#" + LAUNCH_SPLASH_NODE_ID + " .dent-launch-splash__mark{width:clamp(92px,22vw,124px);height:clamp(92px,22vw,124px);display:grid;place-items:center;border-radius:28px;background:rgba(255,255,255,0.72);border:1px solid rgba(127,145,165,0.12);box-shadow:0 18px 44px -34px rgba(17,30,54,0.32);overflow:hidden;}",
            "html[data-theme=\"dark\"] #" + LAUNCH_SPLASH_NODE_ID + " .dent-launch-splash__mark{background:rgba(245,249,255,0.9);border-color:rgba(124,145,168,0.16);box-shadow:0 20px 48px -34px rgba(0,0,0,0.6);}",
            "#" + LAUNCH_SPLASH_NODE_ID + " .dent-launch-splash__mark img{width:82%;height:82%;object-fit:contain;border-radius:20px;}",
            "#" + LAUNCH_SPLASH_NODE_ID + " .dent-launch-splash__progress{width:min(280px,54vw);height:5px;border-radius:999px;background:rgba(22,34,53,0.14);overflow:hidden;}",
            "html[data-theme=\"dark\"] #" + LAUNCH_SPLASH_NODE_ID + " .dent-launch-splash__progress{background:rgba(237,243,255,0.16);}",
            "#" + LAUNCH_SPLASH_NODE_ID + " .dent-launch-splash__progress span{display:block;width:100%;height:100%;border-radius:inherit;background:#2b6df3;transform-origin:right center;animation:dentLaunchProgress 0.68s cubic-bezier(0.2,0.88,0.24,1) both;}",
            "#" + LAUNCH_SPLASH_NODE_ID + " .dent-launch-splash__brand{margin-top:min(11vh,7rem);font-family:var(--font-accent,var(--font-main));font-size:clamp(1rem,4vw,1.35rem);font-weight:900;color:rgba(17,24,39,0.78);}",
            "html[data-theme=\"dark\"] #" + LAUNCH_SPLASH_NODE_ID + " .dent-launch-splash__brand{color:rgba(237,243,255,0.8);}",
            "#" + LAUNCH_SPLASH_NODE_ID + " .dent-launch-splash__build{display:none;}",
            "html." + LAUNCH_SPLASH_CLASS + "." + LAUNCH_SPLASH_LEAVING_CLASS + " #" + LAUNCH_SPLASH_NODE_ID + "{opacity:0;}",
            "html." + LAUNCH_SPLASH_CLASS + "." + LAUNCH_SPLASH_LEAVING_CLASS + " #" + LAUNCH_SPLASH_NODE_ID + " .dent-launch-splash__center{opacity:0;transform:translateY(4vh) scale(0.992);}",
            "@keyframes dentLaunchProgress{0%{transform:scaleX(0.08);opacity:0.68;}100%{transform:scaleX(1);opacity:1;}}",
            "html[data-performance-mode=\"lite\"] #" + LAUNCH_SPLASH_NODE_ID + " .dent-launch-splash__mark{box-shadow:none;border-color:rgba(127,145,165,0.18);}",
            "@media (prefers-reduced-motion: reduce){#" + LAUNCH_SPLASH_NODE_ID + ",#" + LAUNCH_SPLASH_NODE_ID + " .dent-launch-splash__center{transition-duration:0.01ms;transform:none;}#" + LAUNCH_SPLASH_NODE_ID + " .dent-launch-splash__progress span{animation-duration:0.01ms;}}"
        ].join("");

        (document.head || document.documentElement).appendChild(style);
    }

    function primeLaunchLogo() {
        var img = new Image();
        img.decoding = "async";
        img.loading = "eager";
        img.src = LAUNCH_SPLASH_LOGO_URL;
    }

    function createLaunchSplashNode(buildLabel) {
        if (launchSplashNode) {
            return launchSplashNode;
        }

        var splash = document.createElement("div");
        splash.id = LAUNCH_SPLASH_NODE_ID;
        splash.dir = "rtl";
        splash.setAttribute("aria-hidden", "true");
        splash.innerHTML = [
            '<div class="dent-launch-splash__center">',
            '  <div class="dent-launch-splash__mark"><img src="' + LAUNCH_SPLASH_LOGO_URL + '" alt=""></div>',
            '  <div class="dent-launch-splash__progress"><span></span></div>',
            "</div>",
            '<div class="dent-launch-splash__brand">ورودی ۱۴۰۲ دندانپزشکی تهران</div>',
            '<div class="dent-launch-splash__build" data-digit-locale="latin">' + (buildLabel || "") + "</div>"
        ].join("");

        document.documentElement.appendChild(splash);
        launchSplashNode = splash;
        return splash;
    }

    function mountLaunchSplash() {
        if (launchSplashMounted || !shouldShowLaunchSplash()) {
            return;
        }
        launchSplashMounted = true;

        ensureLaunchSplashStyle();
        primeLaunchLogo();

        var root = document.documentElement;
        var splashTheme = resolvedTheme();
        var buildLabel = resolveLaunchBuildLabel();
        createLaunchSplashNode(buildLabel ? "Build " + buildLabel : "");
        root.setAttribute("data-launch-build", buildLabel ? "Build " + buildLabel : "");
        root.classList.add(LAUNCH_SPLASH_CLASS);
        forceLaunchMeta(splashTheme);

        var reducedMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
        var minimalMotion = reducedMotion || prefersLitePerformanceMode();
        var fadeDuration = minimalMotion ? 1 : LAUNCH_SPLASH_FADE_MS;
        var isHiding = false;
        var minElapsed = false;
        var domReady = document.readyState !== "loading";

        function cleanupSplash() {
            root.classList.remove(LAUNCH_SPLASH_CLASS);
            root.classList.remove(LAUNCH_SPLASH_LEAVING_CLASS);
            root.removeAttribute("data-launch-build");
            if (launchSplashNode && launchSplashNode.parentNode) {
                launchSplashNode.parentNode.removeChild(launchSplashNode);
            }
            launchSplashNode = null;
            syncMeta(resolvedTheme());
        }

        function beginHide() {
            if (isHiding) {
                return;
            }

            isHiding = true;
            root.classList.add(LAUNCH_SPLASH_LEAVING_CLASS);
            window.setTimeout(cleanupSplash, fadeDuration + 24);
        }

        function maybeHide() {
            if (minElapsed && domReady) {
                beginHide();
            }
        }

        window.setTimeout(function () {
            minElapsed = true;
            maybeHide();
        }, minimalMotion ? 1 : LAUNCH_SPLASH_MIN_VISIBLE_MS);

        if (!domReady) {
            document.addEventListener("DOMContentLoaded", function () {
                window.requestAnimationFrame(function () {
                    domReady = true;
                    maybeHide();
                });
            }, { once: true });
        } else {
            maybeHide();
        }

        window.setTimeout(beginHide, minimalMotion ? 1 : LAUNCH_SPLASH_MAX_VISIBLE_MS);
        window.addEventListener("pagehide", cleanupSplash, { once: true });
    }

    function syncMeta(theme) {
        var themeMeta = document.querySelector('meta[name="theme-color"]');
        if (themeMeta) {
            themeMeta.setAttribute("content", themeColor(theme));
        }

        var appleStatusBarMeta = document.querySelector('meta[name="apple-mobile-web-app-status-bar-style"]');
        if (appleStatusBarMeta) {
            // Full-bleed status bar in iOS standalone mode prevents white top strips during splash.
            appleStatusBarMeta.setAttribute("content", isStandaloneDisplayMode() ? "black-translucent" : "default");
        }
    }

    function forceLaunchMeta(theme) {
        var color = launchSplashColor(theme);
        var themeMeta = document.querySelector('meta[name="theme-color"]');
        if (themeMeta) {
            themeMeta.setAttribute("content", color);
        }

        var appleStatusBarMeta = document.querySelector('meta[name="apple-mobile-web-app-status-bar-style"]');
        if (appleStatusBarMeta) {
            appleStatusBarMeta.setAttribute("content", "black-translucent");
        }
    }

    function applyTheme(theme) {
        document.documentElement.dataset.theme = theme;
        syncMeta(theme);
    }

    function snapshot() {
        return {
            theme: resolvedTheme(),
            manual: manualTheme || null
        };
    }

    function notify() {
        var detail = snapshot();
        window.dispatchEvent(new CustomEvent("dent1402:theme-change", { detail: detail }));
        listeners.forEach(function (listener) {
            listener(detail);
        });
    }

    function syncButton(button) {
        if (!button) {
            return;
        }

        var theme = resolvedTheme();
        var nextTheme = theme === "dark" ? "light" : "dark";
        var label = nextTheme === "dark" ? "تم تیره" : "تم روشن";
        var iconMarkup = nextTheme === "dark"
            ? '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 14.5A7.5 7.5 0 0 1 9.5 4A8.5 8.5 0 1 0 20 14.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>'
            : '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3.5V5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M12 18.5V20.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M20.5 12H18.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M5.5 12H3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M18.01 5.99L16.59 7.41" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M7.41 16.59L5.99 18.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M18.01 18.01L16.59 16.59" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M7.41 7.41L5.99 5.99" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="12" r="3.6" stroke="currentColor" stroke-width="1.8"/></svg>';

        button.innerHTML = '<span class="theme-toggle-btn__icon">' + iconMarkup + "</span>";
        button.setAttribute("aria-label", label);
        button.setAttribute("title", label);
        button.dataset.themeTarget = nextTheme;
    }

    function syncButtons() {
        document.querySelectorAll("[data-theme-toggle]").forEach(syncButton);
    }

    function toPersianDigits(value) {
        var text = value == null ? "" : String(value);
        if (!/[0-9٠-٩]/.test(text)) {
            return text;
        }

        return text
            .replace(/[0-9]/g, function (digit) {
                return persianDigits[digit.charCodeAt(0) - 48];
            })
            .replace(/[٠-٩]/g, function (digit) {
                return persianDigits[digit.charCodeAt(0) - 1632];
            });
    }

    function shouldSkipDigitLocalization(element) {
        // Messenger preserves user-entered text (chat titles/messages) as-is.
        // We keep chat counters localized in chat.js instead of mutating all text nodes here.
        if (document.body && document.body.classList.contains("chat-page")) {
            return true;
        }

        if (!element) {
            return true;
        }

        if (element.closest("[data-digit-locale='latin'], [data-latin-digits='true']")) {
            return true;
        }

        if (element.closest("script, style, textarea, code, pre, kbd, samp")) {
            return true;
        }

        if (element.isContentEditable || element.closest("[contenteditable='true']")) {
            return true;
        }

        return false;
    }

    function shouldStartDigitLocalization() {
        return !shouldSkipDigitLocalization(document.body);
    }

    function localizeTextNode(node) {
        if (!node || node.nodeType !== Node.TEXT_NODE) {
            return;
        }

        var parent = node.parentElement;
        if (shouldSkipDigitLocalization(parent)) {
            return;
        }

        var nextValue = toPersianDigits(node.nodeValue || "");
        if (nextValue !== node.nodeValue) {
            node.nodeValue = nextValue;
        }
    }

    function localizeAttribute(element, name) {
        if (!element || !element.hasAttribute(name) || shouldSkipDigitLocalization(element)) {
            return;
        }

        var value = element.getAttribute(name);
        var localized = toPersianDigits(value);
        if (localized !== value) {
            element.setAttribute(name, localized);
        }
    }

    function localizeDigits(root) {
        if (!root) {
            return;
        }

        if (root.nodeType === Node.TEXT_NODE) {
            localizeTextNode(root);
            return;
        }

        if (root.nodeType === Node.ELEMENT_NODE) {
            if (shouldSkipDigitLocalization(root)) {
                return;
            }
            localizeAttribute(root, "placeholder");
            localizeAttribute(root, "title");
            localizeAttribute(root, "aria-label");
        }

        var base = root.nodeType === Node.ELEMENT_NODE ? root : document.body;
        if (!base) {
            return;
        }

        var walker = document.createTreeWalker(base, NodeFilter.SHOW_TEXT, null);
        var current = walker.nextNode();
        while (current) {
            localizeTextNode(current);
            current = walker.nextNode();
        }

        if (typeof base.querySelectorAll === "function") {
            base.querySelectorAll("[placeholder], [title], [aria-label]").forEach(function (element) {
                localizeAttribute(element, "placeholder");
                localizeAttribute(element, "title");
                localizeAttribute(element, "aria-label");
            });
        }
    }

    function digitLocalizationShouldYield(startedAt, deadline) {
        if (deadline && typeof deadline.timeRemaining === "function") {
            return deadline.timeRemaining() <= 2;
        }
        return (Date.now() - startedAt) >= DIGIT_LOCALIZATION_BUDGET_MS;
    }

    function flushDigitLocalizationQueue(deadline) {
        digitLocalizationScheduled = false;
        var startedAt = Date.now();
        var nextRoot = null;

        while (digitLocalizationQueue.length) {
            nextRoot = digitLocalizationQueue.shift();
            if (digitLocalizationQueuedRoots && nextRoot && typeof digitLocalizationQueuedRoots.delete === "function") {
                digitLocalizationQueuedRoots.delete(nextRoot);
            }
            localizeDigits(nextRoot);
            if (digitLocalizationQueue.length && digitLocalizationShouldYield(startedAt, deadline)) {
                break;
            }
        }

        if (digitLocalizationQueue.length) {
            scheduleDigitLocalization();
        }
    }

    function scheduleDigitLocalization() {
        if (digitLocalizationScheduled) {
            return;
        }

        digitLocalizationScheduled = true;
        if (typeof window.requestIdleCallback === "function") {
            window.requestIdleCallback(flushDigitLocalizationQueue, { timeout: 160 });
            return;
        }

        window.setTimeout(function () {
            flushDigitLocalizationQueue(null);
        }, DIGIT_LOCALIZATION_FALLBACK_DELAY_MS);
    }

    function queueDigitLocalization(root) {
        if (!root) {
            return;
        }

        if (digitLocalizationQueuedRoots) {
            if (digitLocalizationQueuedRoots.has(root)) {
                return;
            }
            digitLocalizationQueuedRoots.add(root);
        } else if (digitLocalizationQueue.indexOf(root) !== -1) {
            return;
        }

        digitLocalizationQueue.push(root);
        scheduleDigitLocalization();
    }

    function startDigitLocalization() {
        if (!document.body || !shouldStartDigitLocalization()) {
            return;
        }

        queueDigitLocalization(document.body);

        if (!window.MutationObserver || digitObserver) {
            return;
        }

        digitObserver = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (mutation.type === "characterData") {
                    localizeTextNode(mutation.target);
                    return;
                }

                if (mutation.type === "attributes" && mutation.target && mutation.target.nodeType === Node.ELEMENT_NODE) {
                    localizeAttribute(mutation.target, mutation.attributeName);
                    return;
                }

                if (mutation.type === "childList") {
                    mutation.addedNodes.forEach(function (node) {
                        queueDigitLocalization(node);
                    });
                }
            });
        });

        digitObserver.observe(document.body, {
            childList: true,
            subtree: true,
            characterData: true,
            attributes: true,
            attributeFilter: ["placeholder", "title", "aria-label"]
        });
    }

    function persistTheme() {
        try {
            if (manualTheme) {
                window.localStorage.setItem(STORAGE_KEY, manualTheme);
            } else {
                window.localStorage.removeItem(STORAGE_KEY);
            }
        } catch (error) {
            // Ignore storage failures.
        }
    }

    function setTheme(value) {
        manualTheme = value === "light" || value === "dark" ? value : "";
        persistTheme();
        applyTheme(resolvedTheme());
        syncButtons();
        notify();
    }

    function toggleTheme() {
        setTheme(resolvedTheme() === "dark" ? "light" : "dark");
    }

    function ensureButton(container, compact) {
        if (!container || container.querySelector("[data-theme-toggle]")) {
            return;
        }

        var button = document.createElement("button");
        button.type = "button";
        button.className = "theme-toggle-btn" + (compact ? " theme-toggle-btn--compact" : "");
        button.dataset.themeToggle = "true";
        button.addEventListener("click", toggleTheme);
        container.appendChild(button);
        syncButton(button);
    }

    function injectButtons() {
        document.querySelectorAll("[data-theme-toggle-slot]").forEach(function (slot) {
            ensureButton(slot, true);
        });

        var chatActions = document.querySelector(".chat-app__actions");
        if (chatActions) {
            ensureButton(chatActions, true);
            return;
        }

        var headerActions = document.querySelector(".header-actions");
        if (headerActions) {
            ensureButton(headerActions, false);
            return;
        }

        var header = document.querySelector(".site-header");
        if (!header) {
            return;
        }

        var fallback = document.createElement("div");
        fallback.className = "header-actions";
        header.appendChild(fallback);
        ensureButton(fallback, false);
    }

    function handleSystemThemeChange() {
        if (manualTheme) {
            return;
        }

        applyTheme(resolvedTheme());
        syncButtons();
        notify();
    }

    if (media) {
        if (typeof media.addEventListener === "function") {
            media.addEventListener("change", handleSystemThemeChange);
        } else if (typeof media.addListener === "function") {
            media.addListener(handleSystemThemeChange);
        }
    }

    var connection = window.navigator.connection || window.navigator.mozConnection || window.navigator.webkitConnection || null;
    if (connection && typeof connection.addEventListener === "function") {
        connection.addEventListener("change", applyPerformanceMode);
    }

    window.Dent1402Theme = {
        getState: snapshot,
        setTheme: setTheme,
        toggle: toggleTheme,
        onChange: function (listener) {
            if (typeof listener === "function") {
                listeners.push(listener);
                listener(snapshot());
            }
        }
    };

    window.Dent1402Locale = {
        toPersianDigits: toPersianDigits,
        localizeDigits: function (root) {
            localizeDigits(root || document.body);
        }
    };

    window.Dent1402Analytics = {
        trackPageView: analyticsTrackPageView,
        trackDownload: function (payload) {
            return analyticsTrackDownload(null, payload || {});
        },
        getSnapshot: analyticsSnapshot
    };

    function prefersLitePerformanceMode() {
        var connection = window.navigator.connection || window.navigator.mozConnection || window.navigator.webkitConnection || null;
        var deviceMemory = Number(window.navigator.deviceMemory || 0);
        var hardwareConcurrency = Number(window.navigator.hardwareConcurrency || 0);
        return !!(
            (connection && connection.saveData) ||
            (deviceMemory > 0 && deviceMemory <= 2 && hardwareConcurrency > 0 && hardwareConcurrency <= 4)
        );
    }

    function applyPerformanceMode() {
        document.documentElement.dataset.performanceMode = prefersLitePerformanceMode() ? "lite" : "default";
    }

    ensureViewportScaleLock();
    applyTheme(resolvedTheme());
    applyPerformanceMode();
    mountLaunchSplash();

    function boot() {
        injectButtons();
        syncButtons();
        startDigitLocalization();
        queueInputViewportSync();
        bindAnalyticsDownloadTracking();
        scheduleInitialAnalyticsTracking();
        notify();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot, { once: true });
    } else {
        boot();
    }

    document.addEventListener("focusin", queueInputViewportSync, true);
    document.addEventListener("focusout", queueInputViewportSync, true);
    window.addEventListener("resize", queueInputViewportSync, { passive: true });
    window.addEventListener("orientationchange", queueInputViewportSync, { passive: true });
    window.addEventListener("pageshow", queueInputViewportSync, { passive: true });
    if (window.visualViewport) {
        window.visualViewport.addEventListener("resize", queueInputViewportSync, { passive: true });
        window.visualViewport.addEventListener("scroll", queueInputViewportSync, { passive: true });
    }
})();
