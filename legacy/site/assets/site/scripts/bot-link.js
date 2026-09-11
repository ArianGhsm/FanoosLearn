(function () {
    "use strict";

    var token = new URLSearchParams(window.location.search).get("token") || "";
    var loading = document.getElementById("bot-link-loading");
    var login = document.getElementById("bot-link-login");
    var confirmPanel = document.getElementById("bot-link-confirm");
    var result = document.getElementById("bot-link-result");
    var confirmButton = document.getElementById("bot-link-confirm-action");
    var platformLabel = document.getElementById("bot-link-platform");
    var resultTitle = document.getElementById("bot-link-result-title");
    var resultMessage = document.getElementById("bot-link-result-message");
    var loginAction = document.getElementById("bot-link-login-action");
    var csrfToken = "";

    function show(target) {
        [loading, login, confirmPanel, result].forEach(function (node) {
            if (node) node.hidden = node !== target;
        });
    }

    async function jsonRequest(url, options) {
        var response = await fetch(url, Object.assign({ credentials: "same-origin", cache: "no-store" }, options || {}));
        var payload = await response.json().catch(function () { return null; });
        if (!response.ok || !payload || payload.success !== true) {
            throw new Error(payload && payload.error ? payload.error : "درخواست انجام نشد.");
        }
        return payload;
    }

    function fail(message) {
        resultTitle.textContent = "اتصال انجام نشد";
        resultMessage.textContent = message || "لینک نامعتبر یا منقضی است.";
        show(result);
    }

    async function boot() {
        if (!token || token.length > 120) {
            fail("لینک اتصال نامعتبر است.");
            return;
        }
        await window.Dent1402Auth.ready();
        var state = window.Dent1402Auth.getState();
        if (!state.loggedIn || !state.user) {
            loginAction.href = window.Dent1402Auth.loginUrl(window.location.pathname + window.location.search);
            show(login);
            return;
        }
        try {
            var sessions = await jsonRequest("/api/auth_api.php?action=authSessions");
            csrfToken = String(sessions.csrfToken || "");
            var info = await jsonRequest("/api/bot_api.php?action=linkInfo&token=" + encodeURIComponent(token));
            platformLabel.textContent = info.platform === "bale" ? "اتصال حساب بله" : "اتصال حساب تلگرام";
            show(confirmPanel);
        } catch (error) {
            fail(error.message);
        }
    }

    confirmButton.addEventListener("click", async function () {
        if (!csrfToken || confirmButton.disabled) return;
        confirmButton.disabled = true;
        confirmButton.textContent = "در حال اتصال…";
        try {
            var body = new URLSearchParams({ token: token });
            var payload = await jsonRequest("/api/bot_api.php?action=confirmLink", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded", "X-CSRF-Token": csrfToken },
                body: body.toString()
            });
            resultTitle.textContent = "حساب متصل شد";
            resultMessage.textContent = payload.platform === "bale"
                ? "اکنون می‌توانی اطلاعات مجاز حسابت را در ربات بله ببینی."
                : "اکنون می‌توانی اطلاعات مجاز حسابت را در ربات تلگرام ببینی.";
            show(result);
        } catch (error) {
            fail(error.message);
        } finally {
            confirmButton.disabled = false;
            confirmButton.textContent = "تأیید اتصال";
        }
    });

    boot().catch(function () { fail("بررسی اتصال انجام نشد."); });
})();

