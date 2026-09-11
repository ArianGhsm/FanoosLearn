(function () {
    "use strict";

    var STATUS = {
        LOGGED_OUT: "logged-out",
        LOGGING_IN: "logging-in",
        LOGIN_ERROR: "login-error",
        SESSION_RESTORING: "session-restoring",
        LOGGED_IN: "logged-in",
        LOGGING_OUT: "logging-out",
        UNAUTHORIZED: "unauthorized"
    };

    var LEGACY_STATUS_MAP = {
        restoring: STATUS.SESSION_RESTORING,
        "logged-in-admin": STATUS.LOGGED_IN,
        "logged-in": STATUS.LOGGED_IN,
        "logged-out": STATUS.LOGGED_OUT,
        "logging-in": STATUS.LOGGING_IN,
        "login-error": STATUS.LOGIN_ERROR,
        "logging-out": STATUS.LOGGING_OUT,
        unauthorized: STATUS.UNAUTHORIZED
    };

    var AUTH_CACHE_KEY = "dent1402_auth_cache_v1";
    var REQUEST_TIMEOUT_MS = 12000;
    var SESSION_RECHECK_DELAY_MS = 600;
    var SESSION_RECHECK_ATTEMPTS = 4;

    var listeners = [];
    var readyResolved = false;
    var readyResolve = null;
    var bootPromise = null;

    function readAuthCache() {
        try {
            var raw = window.localStorage ? window.localStorage.getItem(AUTH_CACHE_KEY) : null;
            if (!raw) {
                return null;
            }

            var parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== "object") {
                return null;
            }

            if (parsed.loggedIn && parsed.user && typeof parsed.user === "object") {
                return {
                    loggedIn: true,
                    user: parsed.user,
                    availableCohorts: Array.isArray(parsed.availableCohorts) ? parsed.availableCohorts : [],
                    siteSettings: parsed.siteSettings && typeof parsed.siteSettings === "object" ? parsed.siteSettings : {}
                };
            }

            if (parsed.loggedIn === false) {
                return {
                    loggedIn: false,
                    user: null,
                    availableCohorts: Array.isArray(parsed.availableCohorts) ? parsed.availableCohorts : [],
                    siteSettings: parsed.siteSettings && typeof parsed.siteSettings === "object" ? parsed.siteSettings : {}
                };
            }

            return null;
        } catch (_error) {
            return null;
        }
    }

    function slimCachedUser(user) {
        if (!user || typeof user !== "object") {
            return null;
        }

        var cached = {};
        [
            "studentNumber",
            "disNumber",
            "name",
            "role",
            "roleLabel",
            "cohortKey",
            "cohort",
            "isOwner",
            "isRepresentative",
            "isExternalExamUser",
            "canUseChat",
            "isProsthesisStudent",
            "isProsthesisRepresentative",
            "canModerateChat",
            "permissions",
            "siteSettings",
            "phone",
            "rotation",
            "createdAt",
            "updatedAt"
        ].forEach(function (key) {
            if (Object.prototype.hasOwnProperty.call(user, key)) {
                cached[key] = clone(user[key]);
            }
        });

        if (user.profile && typeof user.profile === "object") {
            cached.profile = {
                about: user.profile.about || user.profile.bio || "",
                bio: user.profile.bio || user.profile.about || "",
                contactHandle: user.profile.contactHandle || "",
                focusArea: user.profile.focusArea || "",
                hasAvatar: !!user.profile.avatarUrl
            };
        }

        return cached.studentNumber ? cached : null;
    }

    function minimalCachedUser(user) {
        if (!user || typeof user !== "object") {
            return null;
        }

        return {
            studentNumber: user.studentNumber || "",
            name: user.name || user.studentNumber || "",
            role: user.role || "student",
            roleLabel: user.roleLabel || "",
            cohortKey: user.cohortKey || "",
            cohort: user.cohort || null,
            isOwner: !!user.isOwner,
            isRepresentative: !!user.isRepresentative,
            isExternalExamUser: !!user.isExternalExamUser,
            canUseChat: user.canUseChat !== false,
            isProsthesisStudent: !!user.isProsthesisStudent,
            isProsthesisRepresentative: !!user.isProsthesisRepresentative,
            canModerateChat: !!user.canModerateChat,
            permissions: user.permissions || {},
            siteSettings: user.siteSettings || {}
        };
    }

    function writeAuthCache(loggedIn, user, availableCohorts) {
        var cohorts = Array.isArray(availableCohorts) ? clone(availableCohorts) : [];
        var siteSettings = state.siteSettings && typeof state.siteSettings === "object" ? clone(state.siteSettings) : {};
        try {
            if (!window.localStorage) {
                return;
            }

            if (loggedIn && user) {
                window.localStorage.setItem(AUTH_CACHE_KEY, JSON.stringify({
                    loggedIn: true,
                    user: slimCachedUser(user) || minimalCachedUser(user),
                    availableCohorts: cohorts,
                    siteSettings: siteSettings
                }));
            } else {
                window.localStorage.setItem(AUTH_CACHE_KEY, JSON.stringify({
                    loggedIn: false,
                    availableCohorts: [],
                    siteSettings: siteSettings
                }));
            }
        } catch (_error) {
            try {
                if (loggedIn && user && window.localStorage) {
                    window.localStorage.setItem(AUTH_CACHE_KEY, JSON.stringify({
                        loggedIn: true,
                        user: minimalCachedUser(user),
                        availableCohorts: cohorts,
                        siteSettings: siteSettings
                    }));
                }
            } catch (_fallbackError) {
                // Ignore storage errors (private mode, quota, etc.).
            }
        }
    }

    var initialAuthCache = readAuthCache();
    var state = initialAuthCache
        ? {
            status: initialAuthCache.loggedIn ? STATUS.LOGGED_IN : STATUS.LOGGED_OUT,
            loggedIn: initialAuthCache.loggedIn,
            user: initialAuthCache.user,
            availableCohorts: Array.isArray(initialAuthCache.availableCohorts) ? initialAuthCache.availableCohorts : [],
            siteSettings: initialAuthCache.siteSettings && typeof initialAuthCache.siteSettings === "object" ? initialAuthCache.siteSettings : {},
            error: ""
        }
        : {
            status: STATUS.SESSION_RESTORING,
            loggedIn: false,
            user: null,
            availableCohorts: [],
            siteSettings: {},
            error: ""
        };

    var readyPromise = new Promise(function (resolve) {
        readyResolve = resolve;
    });

    function clone(value) {
        return value ? JSON.parse(JSON.stringify(value)) : value;
    }

    function snapshot() {
        return {
            status: state.status,
            loggedIn: state.loggedIn,
            user: clone(state.user),
            availableCohorts: clone(state.availableCohorts),
            siteSettings: clone(state.siteSettings || {}),
            error: state.error || ""
        };
    }

    function normalizeStatus(status, fallback) {
        if (!status || typeof status !== "string") {
            return fallback || STATUS.LOGGED_OUT;
        }

        if (status === STATUS.LOGGED_OUT ||
            status === STATUS.LOGGING_IN ||
            status === STATUS.LOGIN_ERROR ||
            status === STATUS.SESSION_RESTORING ||
            status === STATUS.LOGGED_IN ||
            status === STATUS.LOGGING_OUT ||
            status === STATUS.UNAUTHORIZED) {
            return status;
        }

        return LEGACY_STATUS_MAP[status] || fallback || STATUS.LOGGED_OUT;
    }

    function resolveReady() {
        if (readyResolved) {
            return;
        }

        readyResolved = true;
        readyResolve(snapshot());
    }

    function emit() {
        var detail = snapshot();
        window.dispatchEvent(new CustomEvent("dent1402:auth-change", { detail: detail }));
        listeners.slice().forEach(function (listener) {
            listener(detail);
        });
    }

    function setState(nextState) {
        var status = normalizeStatus(nextState.status, state.status);
        var user = nextState.user ? clone(nextState.user) : null;
        var loggedIn = !!nextState.loggedIn && !!user;
        var availableCohorts = Array.isArray(nextState.availableCohorts) ? clone(nextState.availableCohorts) : [];
        var siteSettings = nextState.siteSettings && typeof nextState.siteSettings === "object" ? clone(nextState.siteSettings) : (state.siteSettings || {});
        if (!availableCohorts.length && user && user.cohort && typeof user.cohort === "object") {
            availableCohorts = [clone(user.cohort)];
        }

        state = {
            status: status,
            loggedIn: loggedIn,
            user: user,
            availableCohorts: availableCohorts,
            siteSettings: siteSettings,
            error: nextState.error || ""
        };

        emit();
    }

    function currentReturnTo() {
        return window.location.pathname + window.location.search + window.location.hash;
    }

    function loginUrl(returnTo) {
        var target = returnTo || currentReturnTo();
        return "/account/?returnTo=" + encodeURIComponent(target);
    }

    function normalizeCohortKey(value) {
        var clean = String(value == null ? "" : value).trim().toLowerCase();
        if (!clean || clean === "main" || clean === "1402") {
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

    function resolvePageCohort(datasetKey) {
        var datasetValue = "";
        if (document.body && document.body.dataset && datasetKey) {
            datasetValue = String(document.body.dataset[datasetKey] || "").trim();
        }
        if (datasetValue) {
            return normalizeCohortKey(datasetValue);
        }

        var query = new URLSearchParams(window.location.search || "");
        var queryValue = String(query.get("cohort") || "").trim();
        if (queryValue) {
            return normalizeCohortKey(queryValue);
        }

        var path = String(window.location.pathname || "");
        if (path.indexOf("/prosthesis-1402/") === 0) {
            return "prosthesis-1402";
        }

        return "main";
    }

    function appendCohortQuery(path, cohortKey) {
        var basePath = String(path || "").trim() || "/";
        var normalized = normalizeCohortKey(cohortKey);
        if (normalized === "main") {
            return basePath;
        }

        return basePath + (basePath.indexOf("?") === -1 ? "?" : "&") + "cohort=" + encodeURIComponent(normalized);
    }

    function escapeHtml(value) {
        return String(value == null ? "" : value).replace(/[&<>"']/g, function (char) {
            switch (char) {
                case "&":
                    return "&amp;";
                case "<":
                    return "&lt;";
                case ">":
                    return "&gt;";
                case '"':
                    return "&quot;";
                default:
                    return "&#39;";
            }
        });
    }

    function guardFallbackHref(fallbackHref) {
        var value = typeof fallbackHref === "string" ? fallbackHref.trim() : "";
        return value || "/app/";
    }

    function sameOriginReferrerPath() {
        var current = currentReturnTo();
        try {
            if (!document.referrer) {
                return "";
            }
            var referrer = new URL(document.referrer, window.location.origin);
            if (referrer.origin !== window.location.origin) {
                return "";
            }
            var path = (referrer.pathname || "/") + (referrer.search || "") + (referrer.hash || "");
            return path && path !== current ? path : "";
        } catch (_error) {
            return "";
        }
    }

    function guardBackHref(fallbackHref) {
        return sameOriginReferrerPath() || guardFallbackHref(fallbackHref);
    }

    function guardBackLabel(fallbackHref) {
        return sameOriginReferrerPath() ? "برگشت به بخش قبلی" : "برگشت به خانه";
    }

    function enhanceLoginGuards(root) {
        var scope = root && typeof root.querySelectorAll === "function" ? root : document;
        Array.prototype.slice.call(scope.querySelectorAll("[data-auth-guard-back]")).forEach(function (node) {
            var parentGuard = node.closest ? node.closest("[data-auth-guard]") : null;
            var fallbackHref = node.getAttribute("data-auth-guard-home")
                || (parentGuard ? parentGuard.getAttribute("data-auth-guard-home") : "")
                || "/app/";
            node.setAttribute("href", guardBackHref(fallbackHref));
            node.textContent = guardBackLabel(fallbackHref);
            if (node.dataset.authGuardBackBound === "true") {
                return;
            }
            node.dataset.authGuardBackBound = "true";
            node.addEventListener("click", function (event) {
                if (!sameOriginReferrerPath()) {
                    return;
                }
                event.preventDefault();
                window.history.back();
            });
        });
    }

    function renderLoginRequiredGuard(options) {
        var settings = options || {};
        var fallbackHref = guardFallbackHref(settings.fallbackHref);
        var loginHref = typeof settings.loginHref === "string" && settings.loginHref.trim()
            ? settings.loginHref.trim()
            : loginUrl(typeof settings.returnTo === "string" && settings.returnTo.trim() ? settings.returnTo.trim() : currentReturnTo());
        var actionsClass = typeof settings.actionsClass === "string" && settings.actionsClass.trim()
            ? " " + settings.actionsClass.trim()
            : "";
        var primaryClass = typeof settings.primaryClass === "string" && settings.primaryClass.trim()
            ? settings.primaryClass.trim()
            : "shell-action-btn shell-action-btn-primary";
        var secondaryClass = typeof settings.secondaryClass === "string" && settings.secondaryClass.trim()
            ? settings.secondaryClass.trim()
            : "shell-action-btn";

        return [
            '<div class="site-login-guard" data-auth-guard data-auth-guard-home="' + escapeHtml(fallbackHref) + '">',
            '  <span class="site-login-guard__eyebrow">ورود لازم است</span>',
            '  <h2 class="site-login-guard__title">برای مشاهده این بخش باید وارد حساب شوید</h2>',
            '  <p class="site-login-guard__text">برای حفظ حقوق دانشجویان، برای مشاهده این بخش باید وارد حساب کاربری خود در سایت شوید. می توانید از سایر بخش های سایت که نیاز به ورود ندارند، استفاده کنید.</p>',
            '  <div class="site-login-guard__actions' + actionsClass + '">',
            '    <a class="' + escapeHtml(primaryClass) + '" href="' + escapeHtml(loginHref) + '">ورود به حساب کاربری</a>',
            '    <a class="' + escapeHtml(secondaryClass) + '" data-auth-guard-back data-auth-guard-home="' + escapeHtml(fallbackHref) + '" href="' + escapeHtml(guardBackHref(fallbackHref)) + '">' + escapeHtml(guardBackLabel(fallbackHref)) + '</a>',
            '  </div>',
            '</div>'
        ].join("");
    }

    async function request(action, method, payload) {
        var requestMethod = method || "GET";
        var url = "/api/auth_api.php";
        var controller = (typeof AbortController !== "undefined") ? new AbortController() : null;
        var timeoutId = controller
            ? setTimeout(function () { controller.abort(); }, REQUEST_TIMEOUT_MS)
            : 0;
        var options = {
            method: requestMethod,
            credentials: "same-origin",
            cache: "no-store",
            headers: {
                "Accept": "application/json"
            },
            signal: controller ? controller.signal : undefined
        };

        if (requestMethod === "GET") {
            url += "?action=" + encodeURIComponent(action) + "&_=" + encodeURIComponent(String(Date.now()));
        } else {
            options.headers["Content-Type"] = "application/x-www-form-urlencoded; charset=UTF-8";
            options.body = new URLSearchParams(Object.assign({ action: action }, payload || {}));
        }

        var response;
        try {
            response = await fetch(url, options);
        } catch (error) {
            return {
                success: false,
                error: "ارتباط با سرور برقرار نشد. اتصال اینترنت خود را بررسی کنید.",
                httpStatus: 0
            };
        } finally {
            if (timeoutId) {
                clearTimeout(timeoutId);
            }
        }

        var data = {};

        try {
            data = await response.json();
        } catch (error) {
            data = {
                success: false,
                error: "Invalid server response."
            };
        }

        data.httpStatus = response.status;
        return data;
    }

    function serverAnsweredCleanly(response) {
        var http = response ? response.httpStatus : 0;
        return !!response && (response.success === true || http === 401);
    }

    function wait(ms) {
        return new Promise(function (resolve) {
            window.setTimeout(resolve, ms);
        });
    }

    function keepCachedAuthenticatedState() {
        if (!state.loggedIn || !state.user) {
            return false;
        }

        setState({
            status: STATUS.LOGGED_IN,
            loggedIn: true,
            user: state.user,
            availableCohorts: state.availableCohorts,
            error: ""
        });
        return true;
    }

    function applyAuthenticatedState(response) {
        var user = response && response.user ? response.user : null;
        var availableCohorts = Array.isArray(response && response.availableCohorts) ? response.availableCohorts : [];
        var siteSettings = response && response.siteSettings && typeof response.siteSettings === "object" ? response.siteSettings : state.siteSettings;

        setState({
            status: STATUS.LOGGED_IN,
            loggedIn: !!user,
            user: user,
            availableCohorts: availableCohorts,
            siteSettings: siteSettings,
            error: ""
        });

        writeAuthCache(!!user, user, availableCohorts);
    }

    function applyLoggedOutState(nextStatus, errorText, nextSiteSettings) {
        var status = normalizeStatus(nextStatus || STATUS.LOGGED_OUT, STATUS.LOGGED_OUT);
        if (status === STATUS.LOGGED_IN) {
            status = STATUS.LOGGED_OUT;
        }
        var siteSettings = nextSiteSettings && typeof nextSiteSettings === "object" ? nextSiteSettings : state.siteSettings;

        setState({
            status: status,
            loggedIn: false,
            user: null,
            availableCohorts: [],
            siteSettings: siteSettings,
            error: errorText || ""
        });

        writeAuthCache(false, null, []);
    }

    function patchCurrentUser(user, nextStatus, nextAvailableCohorts) {
        if (!user) {
            applyLoggedOutState(STATUS.LOGGED_OUT, "");
            return snapshot();
        }

        var availableCohorts = Array.isArray(nextAvailableCohorts) ? nextAvailableCohorts : state.availableCohorts;

        setState({
            status: normalizeStatus(nextStatus || STATUS.LOGGED_IN, STATUS.LOGGED_IN),
            loggedIn: true,
            user: user,
            availableCohorts: availableCohorts,
            siteSettings: state.siteSettings,
            error: ""
        });

        writeAuthCache(true, user, availableCohorts);
        resolveReady();
        return snapshot();
    }

    async function bootstrap(force) {
        if (bootPromise && !force) {
            return bootPromise;
        }

        setState({
            status: STATUS.SESSION_RESTORING,
            loggedIn: state.loggedIn,
            user: state.user,
            availableCohorts: state.availableCohorts,
            siteSettings: state.siteSettings,
            error: ""
        });

        bootPromise = request("me", "GET").then(function (response) {
            // The server only "answers" cleanly with success:true (even the
            // logged-out reply uses success:true) or with a 401. Anything else
            // (httpStatus 0 from an aborted/offline fetch, a 5xx, malformed JSON)
            // is a transient failure that must NOT drop a cached session — this
            // is what caused spurious logouts while switching sections quickly.
            var serverAnswered = serverAnsweredCleanly(response);
            if (response && response.loggedIn && response.user) {
                applyAuthenticatedState(response);
            } else if (serverAnswered) {
                if (state.loggedIn && state.user) {
                    return verifySession({
                        attempts: SESSION_RECHECK_ATTEMPTS,
                        delayMs: SESSION_RECHECK_DELAY_MS
                    }).then(function (result) {
                        if (result === false) {
                            applyLoggedOutState(STATUS.LOGGED_OUT, "", response && response.siteSettings);
                        } else if (result === null) {
                            keepCachedAuthenticatedState();
                        }
                        // result === true means verifySession already applied the
                        // current canonical user payload.
                        resolveReady();
                        return snapshot();
                    });
                }
                applyLoggedOutState(STATUS.LOGGED_OUT, "", response && response.siteSettings);
            } else if (!keepCachedAuthenticatedState()) {
                applyLoggedOutState(STATUS.LOGGED_OUT, "Session restore failed.");
            }

            resolveReady();
            return snapshot();
        }).catch(function () {
            if (!keepCachedAuthenticatedState()) {
                applyLoggedOutState(STATUS.LOGGED_OUT, "Session restore failed.");
            }
            resolveReady();
            return snapshot();
        }).finally(function () {
            bootPromise = null;
        });

        return bootPromise;
    }

    async function login(studentNumber, password) {
        setState({
            status: STATUS.LOGGING_IN,
            loggedIn: false,
            user: null,
            availableCohorts: [],
            error: ""
        });

        var response = await request("login", "POST", {
            studentNumber: studentNumber,
            password: password
        });

        if (response && response.success && response.loggedIn && response.user) {
            applyAuthenticatedState(response);
            resolveReady();
            return snapshot();
        }

        setState({
            status: STATUS.LOGIN_ERROR,
            loggedIn: false,
            user: null,
            availableCohorts: [],
            error: (response && response.error) || "Login failed."
        });

        resolveReady();
        return snapshot();
    }

    async function requestLoginOtp(phoneNumber) {
        return request("requestLoginOtp", "POST", {
            phoneNumber: phoneNumber
        });
    }

    async function loginWithOtp(phoneNumber, otpCode) {
        setState({
            status: STATUS.LOGGING_IN,
            loggedIn: false,
            user: null,
            availableCohorts: [],
            error: ""
        });

        var response = await request("verifyLoginOtp", "POST", {
            phoneNumber: phoneNumber,
            otpCode: otpCode
        });

        if (response && response.success && response.loggedIn && response.user) {
            applyAuthenticatedState(response);
            resolveReady();
            return snapshot();
        }

        setState({
            status: STATUS.LOGIN_ERROR,
            loggedIn: false,
            user: null,
            availableCohorts: [],
            error: (response && response.error) || "OTP login failed."
        });

        resolveReady();
        return snapshot();
    }

    async function requestExternalSignupOtp(payload) {
        return request("requestExternalSignupOtp", "POST", payload || {});
    }

    async function completeExternalSignup(payload) {
        setState({
            status: STATUS.LOGGING_IN,
            loggedIn: false,
            user: null,
            availableCohorts: [],
            error: ""
        });

        var response = await request("verifyExternalSignupOtp", "POST", payload || {});

        if (response && response.success && response.loggedIn && response.user) {
            applyAuthenticatedState(response);
            resolveReady();
            return snapshot();
        }

        setState({
            status: STATUS.LOGIN_ERROR,
            loggedIn: false,
            user: null,
            availableCohorts: [],
            error: (response && response.error) || "Signup failed."
        });

        resolveReady();
        return snapshot();
    }

    async function requestPasswordResetOtp(payload) {
        return request("requestPasswordResetOtp", "POST", payload || {});
    }

    async function resetPasswordWithOtp(payload) {
        setState({
            status: STATUS.LOGGING_IN,
            loggedIn: false,
            user: null,
            availableCohorts: [],
            error: ""
        });

        var response = await request("resetPasswordWithOtp", "POST", payload || {});

        if (response && response.success && response.loggedIn && response.user) {
            applyAuthenticatedState(response);
            resolveReady();
            return snapshot();
        }

        setState({
            status: STATUS.LOGIN_ERROR,
            loggedIn: false,
            user: null,
            availableCohorts: [],
            error: (response && response.error) || "Password reset failed."
        });

        resolveReady();
        return snapshot();
    }

    async function requestPhoneEnrollOtp(phoneNumber) {
        return request("requestPhoneEnrollOtp", "POST", {
            phoneNumber: phoneNumber
        });
    }

    async function verifyPhoneEnrollOtp(phoneNumber, otpCode) {
        var response = await request("verifyPhoneEnrollOtp", "POST", {
            phoneNumber: phoneNumber,
            otpCode: otpCode
        });
        if (response && response.success && response.user) {
            patchCurrentUser(response.user, STATUS.LOGGED_IN);
        }
        return response;
    }

    async function setPhoneLoginEnabled(enabled) {
        var response = await request("setPhoneLoginEnabled", "POST", {
            enabled: enabled ? "1" : "0"
        });
        if (response && response.success && response.user) {
            patchCurrentUser(response.user, STATUS.LOGGED_IN);
        }
        return response;
    }

    async function dismissPhoneNudge() {
        var response = await request("dismissPhoneNudge", "POST", {});
        if (response && response.success && response.user) {
            patchCurrentUser(response.user, STATUS.LOGGED_IN);
        }
        return response;
    }

    async function removePhoneNumber() {
        var response = await request("removePhoneNumber", "POST", {});
        if (response && response.success && response.user) {
            patchCurrentUser(response.user, STATUS.LOGGED_IN);
        }
        return response;
    }

    async function smsStatus() {
        return request("smsStatus", "GET");
    }

    async function saveSmsConfig(payload) {
        return request("saveSmsConfig", "POST", payload || {});
    }

    async function smsHealthCheck(phoneNumber) {
        var payload = {};
        if (typeof phoneNumber === "string" && phoneNumber.trim()) {
            payload.phoneNumber = phoneNumber.trim();
        }
        return request("smsHealthCheck", "POST", payload);
    }

    async function logout() {
        setState({
            status: STATUS.LOGGING_OUT,
            loggedIn: state.loggedIn,
            user: state.user,
            availableCohorts: state.availableCohorts,
            siteSettings: state.siteSettings,
            error: ""
        });

        var response = null;
        try {
            response = await request("logout", "POST", {});
        } finally {
            applyLoggedOutState(STATUS.LOGGED_OUT, "", response && response.siteSettings);
        }

        return snapshot();
    }

    // Canonically re-check the session against `me`. Resolves with:
    //   true  -> server confirms a valid session (state restored to logged-in)
    //   false -> server confirms there is no session
    //   null  -> transient/unknown failure; caller MUST keep the current state
    // This is the single source of truth page scripts use before reacting to a 401.
    function verifySession(options) {
        var settings = options || {};
        var attempts = Math.max(1, Number(settings.attempts || 1) || 1);
        var delayMs = Math.max(0, Number(settings.delayMs || 0) || 0);

        function runAttempt(index) {
            return request("me", "GET").then(function (response) {
                if (response && response.loggedIn && response.user) {
                    applyAuthenticatedState(response);
                    return true;
                }
                if (serverAnsweredCleanly(response)) {
                    if (index + 1 < attempts) {
                        return wait(delayMs).then(function () {
                            return runAttempt(index + 1);
                        });
                    }
                    return false;
                }
                return null;
            }).catch(function () {
                return null;
            });
        }

        return runAttempt(0);
    }

    var unauthorizedRecheckInFlight = false;

    function markUnauthorized(errorText) {
        // A single 401 from a page API call is NOT proof the session is gone.
        // Under session-lock contention or a transient server hiccup a request can
        // briefly return 401 while the real server session is still valid. Re-verify
        // with the canonical `me` endpoint before dropping the session, so a transient
        // 401 from any page script never logs the user out across the site.
        if (state.status !== STATUS.LOGGED_IN || !state.loggedIn) {
            applyLoggedOutState(STATUS.UNAUTHORIZED, errorText || "Authentication required.");
            resolveReady();
            return snapshot();
        }

        if (unauthorizedRecheckInFlight) {
            return snapshot();
        }
        unauthorizedRecheckInFlight = true;

        verifySession({
            attempts: SESSION_RECHECK_ATTEMPTS,
            delayMs: SESSION_RECHECK_DELAY_MS
        }).then(function (result) {
            unauthorizedRecheckInFlight = false;
            if (result === false) {
                applyLoggedOutState(STATUS.UNAUTHORIZED, errorText || "Authentication required.");
                resolveReady();
            }
            // result === true  -> verifySession already restored logged-in state.
            // result === null  -> transient failure; keep the current session.
        });

        return snapshot();
    }

    function isUnauthorizedPayload(payload) {
        if (!payload || typeof payload !== "object") {
            return false;
        }

        return !!payload.loggedOut || payload.httpStatus === 401;
    }

    function handleUnauthorizedPayload(payload, fallbackError) {
        if (!isUnauthorizedPayload(payload)) {
            return false;
        }

        if (state.loggedIn && state.user) {
            markUnauthorized((payload && payload.error) || fallbackError || "Authentication required.");
            if (payload && typeof payload === "object") {
                payload.authRecheckPending = true;
                payload.loggedOut = false;
                payload.httpStatus = 0;
            }
            return false;
        }

        markUnauthorized((payload && payload.error) || fallbackError || "Authentication required.");
        return true;
    }

    function parseJsonResponse(response, invalidMessage) {
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
                    success: false,
                    error: invalidMessage || "پاسخ نامعتبر از سرور دریافت شد."
                };
            }

            payload.httpStatus = response.status;
            return payload;
        });
    }

    function normalizeDigits(value) {
        return String(value || "")
            .replace(/[\u06F0-\u06F9]/g, function (char) {
                return String(char.charCodeAt(0) - 0x06F0);
            })
            .replace(/[\u0660-\u0669]/g, function (char) {
                return String(char.charCodeAt(0) - 0x0660);
            });
    }

    function normalizePhone(value) {
        var digits = normalizeDigits(value).replace(/\D+/g, "");
        if (!digits) {
            return "";
        }
        if (digits.indexOf("0098") === 0) {
            digits = digits.slice(4);
        } else if (digits.indexOf("98") === 0) {
            digits = digits.slice(2);
        }
        if (digits.length === 10 && digits.charAt(0) === "9") {
            digits = "0" + digits;
        }
        return digits;
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

    function buildUrl(path, params) {
        var url = new URL(String(path || "/"), window.location.origin);
        Object.keys(params || {}).forEach(function (key) {
            var value = params[key];
            if (value === undefined || value === null) {
                return;
            }
            var clean = String(value).trim();
            if (clean === "") {
                return;
            }
            url.searchParams.set(key, clean);
        });
        return url.pathname + url.search + url.hash;
    }

    function onChange(listener) {
        if (typeof listener !== "function") {
            return function () {};
        }

        listeners.push(listener);
        listener(snapshot());

        return function () {
            listeners = listeners.filter(function (item) {
                return item !== listener;
            });
        };
    }

    window.Dent1402Auth = {
        STATUS: STATUS,
        bootstrap: bootstrap,
        ready: function () {
            return readyPromise;
        },
        login: login,
        requestLoginOtp: requestLoginOtp,
        loginWithOtp: loginWithOtp,
        requestExternalSignupOtp: requestExternalSignupOtp,
        completeExternalSignup: completeExternalSignup,
        requestPasswordResetOtp: requestPasswordResetOtp,
        resetPasswordWithOtp: resetPasswordWithOtp,
        logout: logout,
        requestPhoneEnrollOtp: requestPhoneEnrollOtp,
        verifyPhoneEnrollOtp: verifyPhoneEnrollOtp,
        setPhoneLoginEnabled: setPhoneLoginEnabled,
        dismissPhoneNudge: dismissPhoneNudge,
        removePhoneNumber: removePhoneNumber,
        smsStatus: smsStatus,
        saveSmsConfig: saveSmsConfig,
        smsHealthCheck: smsHealthCheck,
        markUnauthorized: markUnauthorized,
        verifySession: verifySession,
        isUnauthorizedPayload: isUnauthorizedPayload,
        handleUnauthorizedPayload: handleUnauthorizedPayload,
        getState: snapshot,
        getCurrentUser: function () {
            return clone(state.user);
        },
        onChange: onChange,
        patchCurrentUser: patchCurrentUser,
        loginUrl: loginUrl,
        normalizeCohortKey: normalizeCohortKey,
        resolvePageCohort: resolvePageCohort,
        appendCohortQuery: appendCohortQuery,
        currentReturnTo: currentReturnTo,
        guardBackHref: guardBackHref,
        guardBackLabel: guardBackLabel,
        enhanceLoginGuards: enhanceLoginGuards,
        renderLoginRequiredGuard: renderLoginRequiredGuard
    };

    window.Dent1402Site = Object.assign({}, window.Dent1402Site || {}, {
        buildUrl: buildUrl,
        cohortPath: appendCohortQuery,
        consumeUnauthorized: handleUnauthorizedPayload,
        escapeHtml: escapeHtml,
        formatDateTime: formatDateTime,
        normalizeDigits: normalizeDigits,
        normalizePhone: normalizePhone,
        parseJsonResponse: parseJsonResponse
    });

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", function () {
            enhanceLoginGuards(document);
        }, { once: true });
    } else {
        enhanceLoginGuards(document);
    }

    emit();
    bootstrap(false);
})();
