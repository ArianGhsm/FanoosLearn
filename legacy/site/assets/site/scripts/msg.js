(function () {
    "use strict";

    function $(id) { return document.getElementById(id); }

    function escapeHtml(v) {
        return String(v == null ? "" : v).replace(/[&<>"']/g, function (c) {
            return {"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]||c;
        });
    }

    function formatDate(v) {
        var d = new Date(String(v || ""));
        if (!isFinite(d.getTime())) return v || "—";
        return d.toLocaleString("fa-IR-u-ca-persian", {
            year:"numeric",month:"2-digit",day:"2-digit",hour:"2-digit",minute:"2-digit",hour12:false
        });
    }

    function setFeedback(text, kind) {
        var el = $("msg-feedback");
        if (!el) return;
        el.className = "msg-feedback" + (kind ? " is-" + kind : "");
        el.textContent = text || "";
        if (text) el.scrollIntoView({ behavior: "smooth", block: "nearest" });
    }

    function copyText(text, btn) {
        var t = String(text || "").trim();
        if (!t) return;
        var done = function (ok) {
            if (!btn) return;
            var prev = btn.textContent;
            btn.textContent = ok ? "کپی شد ✓" : "—";
            setTimeout(function () { btn.textContent = prev; }, 1800);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(t).then(function(){done(true);}).catch(function(){done(false);});
        } else {
            var a = document.createElement("textarea");
            a.value = t; a.style.position = "fixed"; a.style.left = "-9999px";
            document.body.appendChild(a); a.select();
            var ok = false;
            try { ok = document.execCommand("copy"); } catch(_) {}
            a.remove(); done(ok);
        }
    }

    function apiPost(action, body) {
        return fetch("/api/msg_api.php?action=" + encodeURIComponent(action), {
            method: "POST",
            credentials: "same-origin",
            headers: { "Accept": "application/json", "Content-Type": "application/json" },
            body: JSON.stringify(body || {})
        }).then(function(r){
            return r.json().then(function(d){ d.httpStatus = r.status; return d; });
        }).catch(function(){
            return { success: false, error: "ارتباط با سرور برقرار نشد.", httpStatus: 0 };
        });
    }

    function apiGet(action, params) {
        var qs = new URLSearchParams(Object.assign({ action: action }, params || {}));
        return fetch("/api/msg_api.php?" + qs, {
            credentials: "same-origin",
            headers: { "Accept": "application/json" }
        }).then(function(r){
            return r.json().then(function(d){ d.httpStatus = r.status; return d; });
        }).catch(function(){
            return { success: false, error: "ارتباط با سرور برقرار نشد.", httpStatus: 0 };
        });
    }

    var THEME_LABELS = { rose: "رز", blue: "آبی", gold: "طلایی", mint: "نعنایی" };

    function renderCards(cards) {
        var list = $("msg-list");
        if (!list) return;
        if (!cards || !cards.length) {
            list.innerHTML = '<p class="msg-empty">هنوز کارتی ساخته نشده.</p>';
            return;
        }
        list.innerHTML = cards.map(function(c) {
            var url = c.publicUrl ? (location.origin + c.publicUrl) : "";
            var views = Number(c.viewCount || 0).toLocaleString("fa-IR");
            return [
                '<article class="msg-item">',
                '  <div class="msg-item-main">',
                '    <span class="msg-item-title">pov: ' + escapeHtml(c.povLine || "") + '</span>',
                c.recipientName ? '<span class="msg-item-for">برای: ' + escapeHtml(c.recipientName) + '</span>' : '',
                '    <span class="msg-item-meta">',
                '      ' + escapeHtml(THEME_LABELS[c.theme] || c.theme || "") + ' · ',
                '      ساخته: ' + escapeHtml(formatDate(c.createdAt)) + ' · ' + escapeHtml(views) + ' بازدید',
                c.lastViewedAt ? (' · آخرین بازدید: ' + escapeHtml(formatDate(c.lastViewedAt))) : '',
                '    </span>',
                url ? '<div class="msg-item-url"><code>' + escapeHtml(url) + '</code></div>' : '',
                '  </div>',
                '  <div class="msg-item-actions">',
                url ? '<button class="msg-btn msg-btn--copy" data-copy="' + escapeHtml(url) + '">کپی لینک</button>' : '',
                url ? '<a class="msg-btn msg-btn--view" href="' + escapeHtml(c.publicUrl||"") + '" target="_blank" rel="noopener">مشاهده</a>' : '',
                '    <button class="msg-btn msg-btn--danger" data-delete="' + escapeHtml(c.id) + '" data-title="' + escapeHtml(c.povLine||"") + '">حذف</button>',
                '  </div>',
                '</article>'
            ].join("\n");
        }).join("\n");

        list.querySelectorAll("[data-copy]").forEach(function(btn){
            btn.addEventListener("click", function(){ copyText(btn.dataset.copy, btn); });
        });

        list.querySelectorAll("[data-delete]").forEach(function(btn){
            btn.addEventListener("click", function(){
                var id = btn.dataset.delete;
                var title = btn.dataset.title || id;
                if (!confirm("حذف کارت «pov: " + title + "»؟")) return;
                btn.disabled = true;
                apiPost("delete", { id: id }).then(function(res){
                    if (res.success) { loadCards(); }
                    else { btn.disabled = false; alert(res.error || "خطا"); }
                });
            });
        });
    }

    function loadCards() {
        var list = $("msg-list");
        if (list) list.innerHTML = '<p class="msg-empty">در حال بارگذاری…</p>';
        apiGet("list").then(function(res){
            if (res.success) renderCards(res.cards || []);
            else if (list) list.innerHTML = '<p class="msg-empty">خطا: ' + escapeHtml(res.error||"") + '</p>';
        });
    }

    function initOwnerApp() {
        var form       = $("msg-form");
        var submitBtn  = $("msg-submit");
        var refreshBtn = $("msg-refresh");

        if (refreshBtn) {
            refreshBtn.addEventListener("click", loadCards);
        }

        if (form) {
            form.addEventListener("submit", function(e){
                e.preventDefault();
                setFeedback("");

                var povLine       = (($("msg-pov")       && $("msg-pov").value)       || "").trim();
                var recipientName = (($("msg-recipient") && $("msg-recipient").value) || "").trim();
                var theme         = (($("msg-theme")     && $("msg-theme").value)     || "rose");
                var rawLines      = (($("msg-lines")     && $("msg-lines").value)     || "");

                var lines = rawLines.split("\n").map(function(l){ return l.trim(); }).filter(Boolean);

                if (!povLine) { setFeedback("متن POV الزامی است.", "error"); return; }

                if (submitBtn) submitBtn.disabled = true;
                setFeedback("در حال ساخت…", "info");

                apiPost("create", { povLine: povLine, recipientName: recipientName, theme: theme, lines: lines })
                    .then(function(res){
                        if (submitBtn) submitBtn.disabled = false;
                        if (res.success) {
                            var fullUrl = location.origin + (res.publicUrl || "");
                            setFeedback("ساخته شد! لینک: " + fullUrl, "success");
                            copyText(fullUrl, null);
                            form.reset();
                            loadCards();
                        } else {
                            setFeedback(res.error || "خطا در ساخت کارت.", "error");
                        }
                    });
            });
        }

        loadCards();
    }

    function init() {
        if (!window.Dent1402Auth) {
            var g = $("msg-auth-guard");
            if (g) g.textContent = "ماژول احراز هویت بارگذاری نشد.";
            return;
        }
        window.Dent1402Auth.ready().then(function(state){
            var guard = $("msg-auth-guard");
            var app   = $("msg-owner-app");
            if (!state.loggedIn) {
                if (guard) guard.innerHTML = "<p>برای دسترسی باید وارد شوید.</p>";
                window.Dent1402Auth.showLogin && window.Dent1402Auth.showLogin();
                return;
            }
            if ((state.user && state.user.role) !== "owner") {
                if (guard) guard.innerHTML = "<p>دسترسی فقط برای مالک.</p>";
                return;
            }
            if (guard) guard.hidden = true;
            if (app) app.hidden = false;
            initOwnerApp();
        });
    }

    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
    else init();
}());
