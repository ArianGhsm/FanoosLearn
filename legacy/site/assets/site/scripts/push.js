(function () {
    "use strict";

    if (window.Dent1402Push) {
        return;
    }

    var ENDPOINT = "/api/push_api.php";

    function isSupported() {
        return ("serviceWorker" in navigator)
            && ("PushManager" in window)
            && ("Notification" in window);
    }

    function currentPermission() {
        return ("Notification" in window) ? Notification.permission : "denied";
    }

    function urlBase64ToUint8Array(base64String) {
        var padding = "=".repeat((4 - (base64String.length % 4)) % 4);
        var base64 = (base64String + padding).replace(/-/g, "+").replace(/_/g, "/");
        var rawData = window.atob(base64);
        var outputArray = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; i++) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    function parseJson(response) {
        return response.json().catch(function () {
            return { success: false, error: "پاسخ نامعتبر از سرور دریافت شد." };
        });
    }

    function fetchServerState() {
        return window.fetch(ENDPOINT + "?action=publicKey", {
            method: "GET",
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        }).then(parseJson);
    }

    function postForm(action, fields) {
        var body = new URLSearchParams();
        body.set("action", action);
        Object.keys(fields || {}).forEach(function (key) {
            if (fields[key] !== undefined && fields[key] !== null) {
                body.set(key, String(fields[key]));
            }
        });
        return window.fetch(ENDPOINT + "?action=" + encodeURIComponent(action), {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                Accept: "application/json"
            },
            body: body.toString()
        }).then(parseJson);
    }

    function getRegistration() {
        if (!("serviceWorker" in navigator)) {
            return Promise.resolve(null);
        }
        return navigator.serviceWorker.ready.catch(function () {
            return navigator.serviceWorker.getRegistration();
        });
    }

    function getStatus() {
        if (!isSupported()) {
            return Promise.resolve({
                supported: false,
                serverSupported: false,
                permission: currentPermission(),
                subscribed: false
            });
        }

        return fetchServerState().then(function (payload) {
            var serverSupported = !!(payload && payload.success && payload.supported);
            return getRegistration().then(function (registration) {
                var subscriptionPromise = registration && registration.pushManager
                    ? registration.pushManager.getSubscription()
                    : Promise.resolve(null);
                return subscriptionPromise.then(function (subscription) {
                    return {
                        supported: true,
                        serverSupported: serverSupported,
                        permission: currentPermission(),
                        subscribed: !!subscription && !!(payload && payload.subscribed),
                        hasLocalSubscription: !!subscription,
                        publicKey: payload && payload.publicKey ? String(payload.publicKey) : ""
                    };
                });
            });
        }).catch(function () {
            return {
                supported: true,
                serverSupported: false,
                permission: currentPermission(),
                subscribed: false
            };
        });
    }

    function subscribe() {
        if (!isSupported()) {
            return Promise.reject(new Error("مرورگر شما از اعلان‌های فوری پشتیبانی نمی‌کند."));
        }

        return Promise.resolve(Notification.requestPermission()).then(function (permission) {
            if (permission !== "granted") {
                var permissionError = new Error("برای دریافت اعلان، اجازه نمایش را در مرورگر تایید کنید.");
                permissionError.code = "permission-denied";
                throw permissionError;
            }
            return fetchServerState();
        }).then(function (payload) {
            if (!payload || !payload.success || !payload.supported || !payload.publicKey) {
                throw new Error("اعلان مرورگر روی سرور فعال نیست.");
            }
            var applicationServerKey = urlBase64ToUint8Array(String(payload.publicKey));
            return getRegistration().then(function (registration) {
                if (!registration || !registration.pushManager) {
                    throw new Error("سرویس‌ورکر سایت هنوز آماده نشده است.");
                }
                return registration.pushManager.getSubscription().then(function (existing) {
                    if (existing) {
                        return existing;
                    }
                    return registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: applicationServerKey
                    });
                });
            });
        }).then(function (subscription) {
            return postForm("subscribe", {
                subscription: JSON.stringify(subscription)
            }).then(function (payload) {
                if (!payload || !payload.success) {
                    throw new Error((payload && payload.error) || "ثبت اعلان مرورگر ناموفق بود.");
                }
                return { subscribed: true };
            });
        });
    }

    function unsubscribe() {
        return getRegistration().then(function (registration) {
            var subscriptionPromise = registration && registration.pushManager
                ? registration.pushManager.getSubscription()
                : Promise.resolve(null);
            return subscriptionPromise.then(function (subscription) {
                var endpoint = subscription ? subscription.endpoint : "";
                var removeLocal = subscription
                    ? subscription.unsubscribe().catch(function () { return false; })
                    : Promise.resolve(true);
                return removeLocal.then(function () {
                    return postForm("unsubscribe", { endpoint: endpoint }).then(function () {
                        return { subscribed: false };
                    });
                });
            });
        });
    }

    window.Dent1402Push = {
        isSupported: isSupported,
        currentPermission: currentPermission,
        getStatus: getStatus,
        subscribe: subscribe,
        unsubscribe: unsubscribe
    };
})();
