(function () {
    "use strict";

    var api = "/api/auth_api.php";
    var state = { csrfToken: "", sessions: [] };
    var status = document.getElementById("sessions-status");
    var count = document.getElementById("auth-session-count");
    var list = document.getElementById("auth-sessions-list");
    var endOthers = document.getElementById("end-other-sessions");

    if (!status || !count || !list || !endOthers) return;

    function faNumber(value) {
        return Number(value || 0).toLocaleString("fa-IR");
    }

    function formatDate(value) {
        if (!value) return "ثبت نشده";
        var source = /^\d+$/.test(String(value)) ? Number(value) * 1000 : value;
        var parsed = new Date(source);
        return Number.isNaN(parsed.getTime())
            ? String(value)
            : parsed.toLocaleString("fa-IR", { dateStyle: "medium", timeStyle: "short" });
    }

    function setStatus(message, isError) {
        status.textContent = message;
        status.classList.toggle("is-error", !!isError);
    }

    async function request(action, method, data) {
        var options = {
            method: method,
            credentials: "same-origin",
            cache: "no-store",
            headers: { Accept: "application/json" }
        };
        if (method !== "GET") {
            options.headers["Content-Type"] = "application/x-www-form-urlencoded; charset=UTF-8";
            options.headers["X-CSRF-Token"] = state.csrfToken;
            options.body = new URLSearchParams(data || {});
        }
        var response = await fetch(api + "?action=" + encodeURIComponent(action), options);
        var payload = await response.json().catch(function () { return {}; });
        if (!response.ok || !payload.success) {
            throw new Error(payload.error || "پاسخ معتبر دریافت نشد.");
        }
        return payload;
    }

    function emptyState() {
        var node = document.createElement("div");
        node.className = "account-sessions-empty";
        node.innerHTML = "<strong>نشست فعالی ثبت نشده است.</strong><span>با ورود بعدی، مرورگر و زمان فعالیت در این بخش دیده می‌شود.</span>";
        list.appendChild(node);
    }

    function sessionLabel(session) {
        return session.label || [session.browser, session.operatingSystem].filter(Boolean).join(" · ") || "نشست ورود";
    }

    function render() {
        list.textContent = "";
        count.textContent = faNumber(state.sessions.length);
        endOthers.disabled = !state.sessions.some(function (session) { return !session.isCurrent; });
        if (!state.sessions.length) emptyState();
        state.sessions.forEach(function (session) {
            var row = document.createElement("article");
            row.className = "account-session" + (session.isCurrent ? " is-current" : "");
            var copy = document.createElement("div");
            var heading = document.createElement("strong");
            heading.textContent = sessionLabel(session);
            var detail = document.createElement("span");
            detail.textContent = "آخرین فعالیت: " + formatDate(session.lastSeenAt);
            var badge = document.createElement("small");
            badge.textContent = session.isCurrent ? "همین دستگاه" : "فعال";
            copy.append(heading, detail, badge);
            row.appendChild(copy);
            if (!session.isCurrent) {
                var button = document.createElement("button");
                button.type = "button";
                button.textContent = "خروج";
                button.addEventListener("click", function () { revokeOne(session.id, button); });
                row.appendChild(button);
            }
            list.appendChild(row);
        });
    }

    async function load() {
        setStatus("در حال دریافت نشست‌های حساب…", false);
        try {
            var payload = await request("authSessions", "GET");
            state.csrfToken = String(payload.csrfToken || "");
            state.sessions = Array.isArray(payload.sessions) ? payload.sessions : [];
            render();
            setStatus("فهرست نشست‌های حساب به‌روز است.", false);
        } catch (error) {
            setStatus(error.message || "نشست‌ها دریافت نشدند.", true);
        }
    }

    async function revokeOne(sessionId, button) {
        if (!window.confirm("این نشست از حساب خارج شود؟")) return;
        button.disabled = true;
        try {
            await request("revokeAuthSession", "POST", { sessionId: sessionId });
            await load();
        } catch (error) {
            button.disabled = false;
            setStatus(error.message, true);
        }
    }

    endOthers.addEventListener("click", async function () {
        if (!window.confirm("همه نشست‌های دیگر از حساب خارج شوند؟")) return;
        endOthers.disabled = true;
        try {
            await request("revokeOtherAuthSessions", "POST", {});
            await load();
        } catch (error) {
            setStatus(error.message, true);
            endOthers.disabled = false;
        }
    });

    load();
}());
