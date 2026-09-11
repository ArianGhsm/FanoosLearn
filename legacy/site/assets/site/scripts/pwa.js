(function () {
    if (window.Dent1402PWA) {
        return;
    }

    var CURRENT_VERSION = "20260907-195131";
    var VERSION_ENDPOINT = "/app-version.json";
    var SERVICE_WORKER_ENDPOINT = "/sw.js";
    var UPDATE_ACK_STORAGE_KEY = "dent1402-pwa-update-ack-version";
    var UPDATE_AUTO_STORAGE_KEY = "dent1402-pwa-update-auto-version";
    var UPDATE_RELOAD_GUARD_STORAGE_KEY = "dent1402-pwa-update-reload-guard-version";
    var UPDATE_CHECK_MIN_INTERVAL = 30000;
    var UPDATE_CHECK_INTERVAL = 300000;
    var UPDATE_APPLY_RELOAD_FALLBACK_MS = 1800;
    var UPDATE_PREPARE_RELOAD_FALLBACK_MS = 8000;
    var VERSION_REQUEST_TIMEOUT_MS = 5000;

    var standaloneQuery = window.matchMedia ? window.matchMedia("(display-mode: standalone)") : null;
    var lastVersionCheckAt = 0;
    var listeners = [];
    var registrationRef = null;
    var bannerEl = null;
    var bannerMessageEl = null;
    var bannerReloadBtn = null;
    var bannerDismissBtn = null;
    var reloadAfterControllerChange = false;
    var updateApplyInFlight = false;
    var updateReloadTimer = 0;
    var periodicCheckTimer = 0;

    var state = {
        installed: isStandaloneMode(),
        canInstall: false,
        deferredPrompt: null,
        isIOS: /iphone|ipad|ipod/i.test(window.navigator.userAgent),
        isOffline: !window.navigator.onLine,
        currentVersion: normalizeVersion(CURRENT_VERSION),
        latestVersion: normalizeVersion(CURRENT_VERSION),
        updateAvailable: false,
        updateDismissed: false
    };

    function readStorage(key) {
        try {
            return String(window.localStorage.getItem(key) || "");
        } catch (_error) {
            return "";
        }
    }

    function writeStorage(key, value) {
        try {
            if (!value) {
                window.localStorage.removeItem(key);
                return;
            }
            window.localStorage.setItem(key, String(value));
        } catch (_error) {
            // Ignore storage failures.
        }
    }

    function acknowledgedVersion() {
        return normalizeVersion(readStorage(UPDATE_ACK_STORAGE_KEY));
    }

    function autoApplyVersion() {
        return normalizeVersion(readStorage(UPDATE_AUTO_STORAGE_KEY));
    }

    function isStandaloneMode() {
        return !!(
            (window.matchMedia && window.matchMedia("(display-mode: standalone)").matches) ||
            window.navigator.standalone === true
        );
    }

    function normalizeVersion(value) {
        var clean = String(value == null ? "" : value).trim();
        return clean || "unknown";
    }

    function snapshot() {
        return {
            installed: state.installed,
            canInstall: state.canInstall,
            isIOS: state.isIOS,
            isOffline: state.isOffline,
            currentVersion: state.currentVersion,
            latestVersion: state.latestVersion,
            updateAvailable: state.updateAvailable
        };
    }

    function shouldShowUpdateBanner() {
        // Releases are applied by the native service-worker lifecycle. Never
        // interrupt page navigation with a version banner or transient toast.
        return false;
    }

    function notify() {
        var detail = snapshot();
        renderUpdateBanner();
        window.dispatchEvent(new CustomEvent("dent1402:pwa-state", { detail: detail }));
        listeners.forEach(function (listener) {
            listener(detail);
        });
    }

    function updateOnlineState() {
        state.isOffline = !window.navigator.onLine;
        notify();
        if (state.isOffline) {
            clearPeriodicChecks();
            return;
        }

        if (!document.hidden) {
            checkForUpdates(true).catch(function () {
                // Keep online recovery silent.
            });
        }
        schedulePeriodicChecks(UPDATE_CHECK_INTERVAL);
    }

    function clearUpdateReloadTimer() {
        if (!updateReloadTimer) {
            return;
        }
        window.clearTimeout(updateReloadTimer);
        updateReloadTimer = 0;
    }

    function clearPeriodicChecks() {
        if (!periodicCheckTimer) {
            return;
        }
        window.clearTimeout(periodicCheckTimer);
        periodicCheckTimer = 0;
    }

    function canRunPeriodicChecks() {
        return !state.isOffline && !document.hidden;
    }

    function scheduleUpdateReload(delayMs) {
        clearUpdateReloadTimer();
        updateReloadTimer = window.setTimeout(function () {
            updateReloadTimer = 0;
            window.location.reload();
        }, Math.max(0, Number(delayMs) || 0));
    }

    function scheduleVersionReload(version, delayMs) {
        var targetVersion = normalizeVersion(version || state.latestVersion);
        if (readStorage(UPDATE_RELOAD_GUARD_STORAGE_KEY) === targetVersion) {
            // A stale HTML document can keep pointing at an older PWA runtime.
            // Reload it at most once per release instead of trapping the user in
            // a reload loop while the browser cache catches up.
            updateApplyInFlight = false;
            writeStorage(UPDATE_AUTO_STORAGE_KEY, "");
            notify();
            return false;
        }

        writeStorage(UPDATE_RELOAD_GUARD_STORAGE_KEY, targetVersion);
        scheduleUpdateReload(delayMs);
        return true;
    }

    function ensureBannerStyle() {
        if (document.getElementById("dent1402-pwa-update-style")) {
            return;
        }
        var style = document.createElement("style");
        style.id = "dent1402-pwa-update-style";
        style.textContent = [
            ".dent1402-pwa-update{position:fixed;inset-inline:1rem;top:calc(env(safe-area-inset-top,0px) + 0.85rem);z-index:9999;display:grid;gap:0.7rem;padding:0.88rem 0.96rem;border-radius:20px;border:1px solid rgba(90,118,150,0.22);background:rgba(15,23,36,0.94);color:#f7fbff;box-shadow:0 22px 42px -30px rgba(7,12,20,0.46);backdrop-filter:blur(18px);opacity:0;pointer-events:none;transform:translateY(-10px);will-change:opacity,transform;transition:opacity var(--motion-fast,180ms) var(--motion-ease-standard,ease),transform var(--motion-base,320ms) var(--motion-ease-soft,ease),box-shadow var(--motion-fast,180ms) var(--motion-ease-standard,ease);}",
            ".dent1402-pwa-update.is-visible{opacity:1;pointer-events:auto;transform:translateY(0);}",
            ".dent1402-pwa-update__copy{display:grid;gap:0.2rem;}",
            ".dent1402-pwa-update__copy strong{font-size:0.92rem;font-weight:850;line-height:1.45;}",
            ".dent1402-pwa-update__copy span{font-size:0.78rem;line-height:1.7;color:rgba(231,241,255,0.82);}",
            ".dent1402-pwa-update__actions{display:flex;align-items:center;justify-content:flex-end;gap:0.44rem;flex-wrap:wrap;}",
            ".dent1402-pwa-update__btn{min-height:38px;padding:0.46rem 0.86rem;border-radius:999px;border:1px solid rgba(255,255,255,0.12);background:rgba(255,255,255,0.08);color:#fff;font:inherit;font-size:0.78rem;font-weight:800;transition:transform var(--motion-fast,180ms) var(--motion-ease-standard,ease),background var(--motion-fast,180ms) var(--motion-ease-standard,ease),border-color var(--motion-fast,180ms) var(--motion-ease-standard,ease),box-shadow var(--motion-base,320ms) var(--motion-ease-soft,ease);}",
            ".dent1402-pwa-update__btn--primary{border-color:rgba(119,188,255,0.45);background:linear-gradient(180deg,#4fa5ff 0%,#2a7fe4 100%);box-shadow:0 16px 28px -20px rgba(79,165,255,0.75);}",
            "html[data-performance-mode=\"lite\"] .dent1402-pwa-update{backdrop-filter:none;box-shadow:0 18px 32px -28px rgba(7,12,20,0.4);}",
            "@media (prefers-reduced-motion: reduce){.dent1402-pwa-update,.dent1402-pwa-update__btn{transition-duration:0.01ms!important;}.dent1402-pwa-update,.dent1402-pwa-update.is-visible{transform:none;}}",
            "@media (max-width: 640px){.dent1402-pwa-update{inset-inline:0.72rem;top:calc(env(safe-area-inset-top,0px) + 0.6rem);padding:0.82rem 0.84rem;border-radius:18px;}.dent1402-pwa-update__actions{justify-content:stretch;}.dent1402-pwa-update__btn{flex:1 1 0;justify-content:center;display:inline-flex;align-items:center;justify-content:center;}}"
        ].join("");
        (document.head || document.documentElement).appendChild(style);
    }

    function mountBanner() {
        if (bannerEl) {
            return bannerEl;
        }
        if (!document.body) {
            return null;
        }

        ensureBannerStyle();

        bannerEl = document.createElement("section");
        bannerEl.className = "dent1402-pwa-update";
        bannerEl.hidden = true;
        bannerEl.setAttribute("aria-live", "polite");
        bannerEl.innerHTML = [
            '<div class="dent1402-pwa-update__copy">',
            "  <strong>نسخه جدید وب‌اپ آماده است</strong>",
            '  <span id="dent1402-pwa-update-message"></span>',
            "</div>",
            '<div class="dent1402-pwa-update__actions">',
            '  <button type="button" class="dent1402-pwa-update__btn" id="dent1402-pwa-update-dismiss">بعداً</button>',
            '  <button type="button" class="dent1402-pwa-update__btn dent1402-pwa-update__btn--primary" id="dent1402-pwa-update-reload">به‌روزرسانی</button>',
            "</div>"
        ].join("");
        document.body.appendChild(bannerEl);

        bannerMessageEl = document.getElementById("dent1402-pwa-update-message");
        bannerReloadBtn = document.getElementById("dent1402-pwa-update-reload");
        bannerDismissBtn = document.getElementById("dent1402-pwa-update-dismiss");

        if (bannerReloadBtn) {
            bannerReloadBtn.addEventListener("click", function () {
                if (updateApplyInFlight) {
                    return;
                }
                updateApplyInFlight = true;
                writeStorage(UPDATE_ACK_STORAGE_KEY, state.latestVersion);
                writeStorage(UPDATE_AUTO_STORAGE_KEY, state.latestVersion);
                renderUpdateBanner();
                applyUpdate().catch(function () {
                    updateApplyInFlight = false;
                    window.location.reload();
                });
            });
        }

        if (bannerDismissBtn) {
            bannerDismissBtn.addEventListener("click", function () {
                writeStorage(UPDATE_ACK_STORAGE_KEY, state.latestVersion);
                renderUpdateBanner();
            });
        }

        return bannerEl;
    }

    function renderUpdateBanner() {
        var mounted = mountBanner();
        if (!mounted) {
            return;
        }

        var visible = shouldShowUpdateBanner();
        mounted.hidden = !visible;
        mounted.classList.toggle("is-visible", visible);

        if (!visible || !bannerMessageEl) {
            return;
        }

        if (updateApplyInFlight) {
            bannerMessageEl.textContent = "به‌روزرسانی در حال آماده‌سازی است و به محض آماده‌شدن، یک‌بار اعمال می‌شود.";
            if (bannerReloadBtn) {
                bannerReloadBtn.disabled = true;
                bannerReloadBtn.textContent = "در حال آماده‌سازی...";
            }
            if (bannerDismissBtn) {
                bannerDismissBtn.disabled = true;
            }
            return;
        }

        if (bannerReloadBtn) {
            bannerReloadBtn.disabled = false;
            bannerReloadBtn.textContent = "به‌روزرسانی";
        }
        if (bannerDismissBtn) {
            bannerDismissBtn.disabled = false;
        }

        if (state.latestVersion && state.latestVersion !== state.currentVersion) {
            bannerMessageEl.textContent = "برای دریافت آخرین تغییرات، وب‌اپ را یک‌بار به‌روزرسانی کن.";
            return;
        }

        bannerMessageEl.textContent = "نسخه جدید دانلود شده و بعد از بازآوری فعال می‌شود.";
    }

    function setVersionState(latestVersion, hasWaitingWorker) {
        var normalizedLatest = normalizeVersion(latestVersion || state.currentVersion);
        state.latestVersion = normalizedLatest;
        // A waiting worker can belong to the exact release already running in
        // this page (for example after its first install or a repeated
        // registration.update()). Worker lifecycle alone is therefore not an
        // update signal. app-version.json is the canonical release signal.
        state.updateAvailable = normalizedLatest !== state.currentVersion;
        if (!state.updateAvailable) {
            state.updateDismissed = false;
            updateApplyInFlight = false;
            clearUpdateReloadTimer();
            writeStorage(UPDATE_ACK_STORAGE_KEY, "");
            writeStorage(UPDATE_AUTO_STORAGE_KEY, "");
            writeStorage(UPDATE_RELOAD_GUARD_STORAGE_KEY, "");
        }
        notify();
    }

    function shouldAutoApplyUpdate() {
        if (!state.updateAvailable || state.isOffline || updateApplyInFlight || reloadAfterControllerChange) {
            return false;
        }

        if (!state.latestVersion || state.latestVersion === state.currentVersion) {
            return false;
        }

        if (document.hidden) {
            return false;
        }

        return autoApplyVersion() !== state.latestVersion;
    }

    function maybeAutoApplyUpdate() {
        if (!shouldAutoApplyUpdate()) {
            return;
        }

        updateApplyInFlight = true;
        writeStorage(UPDATE_AUTO_STORAGE_KEY, state.latestVersion);
        renderUpdateBanner();

        window.setTimeout(function () {
            applyUpdate().catch(function () {
                updateApplyInFlight = false;
                writeStorage(UPDATE_AUTO_STORAGE_KEY, "");
                notify();
            });
        }, 120);
    }

    function parseVersionPayload(payload) {
        if (!payload || typeof payload !== "object") {
            return normalizeVersion(state.latestVersion || state.currentVersion);
        }
        return normalizeVersion(payload.version || payload.currentVersion || state.latestVersion || state.currentVersion);
    }

    function fetchLatestVersion() {
        var controller = typeof AbortController === "function" ? new AbortController() : null;
        var timer = controller ? window.setTimeout(function () {
            controller.abort();
        }, VERSION_REQUEST_TIMEOUT_MS) : 0;
        return fetch(VERSION_ENDPOINT + "?t=" + Date.now(), {
            cache: "no-store",
            credentials: "same-origin",
            signal: controller ? controller.signal : undefined,
            headers: {
                Accept: "application/json"
            }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error("version-unavailable");
            }
            return response.json();
        }).then(function (payload) {
            return parseVersionPayload(payload);
        }).finally(function () {
            if (timer) {
                window.clearTimeout(timer);
            }
        });
    }

    function bindInstallingWorker(worker) {
        if (!worker || worker.__dent1402Bound) {
            return;
        }
        worker.__dent1402Bound = true;
        worker.addEventListener("statechange", function () {
            if (worker.state === "installed" && navigator.serviceWorker.controller) {
                setVersionState(state.latestVersion, true);
            }
        });
    }

    function bindRegistration(registration) {
        if (!registration) {
            return;
        }

        registrationRef = registration;

        if (registration.installing) {
            bindInstallingWorker(registration.installing);
        }

        if (!registration.__dent1402Bound) {
            registration.__dent1402Bound = true;
            registration.addEventListener("updatefound", function () {
                bindInstallingWorker(registration.installing);
            });
        }

        setVersionState(state.latestVersion, !!registration.waiting);
    }

    function registerServiceWorker(version) {
        if (!("serviceWorker" in navigator)) {
            return Promise.resolve(null);
        }

        var targetVersion = normalizeVersion(version || state.latestVersion || state.currentVersion);
        var serviceWorkerUrl = SERVICE_WORKER_ENDPOINT + "?v=" + encodeURIComponent(targetVersion);

        return navigator.serviceWorker.register(serviceWorkerUrl, { updateViaCache: "none" }).then(function (registration) {
            bindRegistration(registration);
            return registration;
        });
    }

    function checkForUpdates(force) {
        var now = Date.now();
        if (!force && now - lastVersionCheckAt < UPDATE_CHECK_MIN_INTERVAL) {
            return Promise.resolve(snapshot());
        }
        lastVersionCheckAt = now;

        return fetchLatestVersion().then(function (latestVersion) {
            state.latestVersion = latestVersion;
            if (!("serviceWorker" in navigator)) {
                setVersionState(latestVersion, false);
                return snapshot();
            }

            return registerServiceWorker(latestVersion).then(function (registration) {
                if (!registration) {
                    setVersionState(latestVersion, false);
                    return snapshot();
                }

                return registration.update().catch(function () {
                    return null;
                }).then(function () {
                    bindRegistration(registration);
                    return snapshot();
                });
            });
        }).catch(function () {
            notify();
            return snapshot();
        });
    }

    function applyUpdate() {
        if (!("serviceWorker" in navigator)) {
            scheduleVersionReload(state.latestVersion, 80);
            return Promise.resolve({ outcome: "reloading" });
        }

        return Promise.resolve(registrationRef || navigator.serviceWorker.getRegistration()).then(function (registration) {
            if (registration) {
                bindRegistration(registration);
            }

            var waitingWorker = registration && registration.waiting ? registration.waiting : null;
            if (waitingWorker) {
                reloadAfterControllerChange = true;
                waitingWorker.postMessage({ type: "SKIP_WAITING" });
                scheduleVersionReload(state.latestVersion, UPDATE_APPLY_RELOAD_FALLBACK_MS);
                return { outcome: "reloading" };
            }

            if (state.latestVersion !== state.currentVersion) {
                // registration.update() can remain pending on a constrained
                // network. Never leave the interface in "preparing" forever.
                scheduleUpdateReload(UPDATE_PREPARE_RELOAD_FALLBACK_MS);
            }

            return checkForUpdates(true).then(function () {
                if (registrationRef && registrationRef.waiting) {
                    reloadAfterControllerChange = true;
                    registrationRef.waiting.postMessage({ type: "SKIP_WAITING" });
                    scheduleVersionReload(state.latestVersion, UPDATE_APPLY_RELOAD_FALLBACK_MS);
                    return { outcome: "reloading" };
                }

                if (state.latestVersion !== state.currentVersion) {
                    scheduleVersionReload(state.latestVersion, 220);
                    return { outcome: "reloading-fallback" };
                }

                updateApplyInFlight = false;
                clearUpdateReloadTimer();
                notify();
                return { outcome: "noop" };
            });
        });
    }

    function promptInstall() {
        if (state.deferredPrompt) {
            state.deferredPrompt.prompt();
            return state.deferredPrompt.userChoice.then(function (choice) {
                if (choice && choice.outcome === "accepted") {
                    state.canInstall = false;
                    state.deferredPrompt = null;
                    notify();
                }
                return choice;
            });
        }

        return Promise.resolve({ outcome: "unavailable" });
    }

    function schedulePeriodicChecks(delayMs) {
        clearPeriodicChecks();
        if (!("serviceWorker" in navigator) || !canRunPeriodicChecks()) {
            return;
        }

        periodicCheckTimer = window.setTimeout(function () {
            periodicCheckTimer = 0;
            checkForUpdates(false).catch(function () {
                // Keep the periodic check quiet.
            }).finally(function () {
                schedulePeriodicChecks(UPDATE_CHECK_INTERVAL);
            });
        }, Math.max(UPDATE_CHECK_MIN_INTERVAL, Number(delayMs) || UPDATE_CHECK_INTERVAL));
    }

    window.Dent1402PWA = {
        getState: snapshot,
        promptInstall: promptInstall,
        applyUpdate: applyUpdate,
        checkForUpdate: checkForUpdates,
        onChange: function (listener) {
            if (typeof listener === "function") {
                listeners.push(listener);
                listener(snapshot());
            }
        }
    };

    window.addEventListener("beforeinstallprompt", function (event) {
        event.preventDefault();
        state.deferredPrompt = event;
        state.canInstall = true;
        notify();
    });

    window.addEventListener("appinstalled", function () {
        state.installed = true;
        state.canInstall = false;
        state.deferredPrompt = null;
        state.updateDismissed = false;
        notify();
        checkForUpdates(true).catch(function () {
            // Keep install flow quiet.
        });
    });

    window.addEventListener("online", updateOnlineState);
    window.addEventListener("offline", updateOnlineState);

    if (standaloneQuery && typeof standaloneQuery.addEventListener === "function") {
        standaloneQuery.addEventListener("change", function () {
            state.installed = isStandaloneMode();
            notify();
        });
    }

    if ("serviceWorker" in navigator) {
        navigator.serviceWorker.addEventListener("controllerchange", function () {
            if (reloadAfterControllerChange) {
                reloadAfterControllerChange = false;
                clearUpdateReloadTimer();
                window.location.reload();
            }
        });
    }

    window.addEventListener("load", function () {
        if ("serviceWorker" in navigator) {
            checkForUpdates(true).catch(function () {
                // Keep initial registration silent in production.
            });
            schedulePeriodicChecks(UPDATE_CHECK_INTERVAL);
        } else {
            fetchLatestVersion().then(function (latestVersion) {
                setVersionState(latestVersion, false);
            }).catch(function () {
                notify();
            });
        }
    });

    window.addEventListener("focus", function () {
        checkForUpdates(false).catch(function () {
            // Silence focus refresh failures.
        });
        schedulePeriodicChecks(UPDATE_CHECK_INTERVAL);
    });

    window.addEventListener("pageshow", function () {
        checkForUpdates(false).catch(function () {
            // Silence bfcache refresh failures.
        });
        schedulePeriodicChecks(UPDATE_CHECK_INTERVAL);
    });

    document.addEventListener("visibilitychange", function () {
        if (document.hidden) {
            clearPeriodicChecks();
            return;
        }
        checkForUpdates(false).catch(function () {
            // Silence visibility refresh failures.
        });
        schedulePeriodicChecks(UPDATE_CHECK_INTERVAL);
    });

    if (!document.body) {
        document.addEventListener("DOMContentLoaded", function () {
            renderUpdateBanner();
        }, { once: true });
    }

    notify();
})();
