(function () {
    "use strict";

    var appRoot = document.querySelector("[data-exam-app]");
    var body = document.body;
    var courseSlug = body && body.dataset ? String(body.dataset.examsCourse || "").trim() : "";
    var examSlug = body && body.dataset ? String(body.dataset.examsExam || "").trim() : "";
    var assetVersionQuery = (function () {
        var src = "";
        var currentScript = document.currentScript;
        if (currentScript && typeof currentScript.src === "string" && currentScript.src) {
            src = currentScript.src;
        }
        if (!src) {
            var bootstrapScripts = document.querySelectorAll('script[src*="/assets/site/scripts/exam-bootstrap.js"]');
            if (bootstrapScripts.length > 0) {
                src = bootstrapScripts[bootstrapScripts.length - 1].src || "";
            }
        }
        if (!src) {
            return "";
        }
        try {
            var url = new URL(src, window.location.href);
            var version = String(url.searchParams.get("v") || "").trim();
            return version ? "?v=" + encodeURIComponent(version) : "";
        } catch (_error) {
            return "";
        }
    }());
    if (!appRoot || !courseSlug || !examSlug) {
        return;
    }

    var params = new URLSearchParams(window.location.search);
    var started = false;
    var authRechecked = false;
    var quizRuntimeWarmed = false;

    appRoot.addEventListener("click", function (event) {
        var target = event.target && event.target.closest
            ? event.target.closest("[data-exam-bootstrap-action]")
            : null;
        if (!target || target.dataset.examBootstrapAction !== "retry") {
            return;
        }
        started = false;
        authRechecked = false;
        loadExam();
    });

    function escapeHtml(value) {
        return String(value == null ? "" : value).replace(/[&<>"]/g, function (char) {
            switch (char) {
                case "&": return "&amp;";
                case "<": return "&lt;";
                case ">": return "&gt;";
                case "\"": return "&quot;";
                default: return char;
            }
        });
    }

    function parseJson(response) {
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
                payload = { success: false, error: "پاسخ نامعتبر از سرور دریافت شد." };
            }
            payload.httpStatus = response.status;
            return payload;
        });
    }

    function loginHref() {
        if (window.Dent1402Auth && typeof window.Dent1402Auth.loginUrl === "function") {
            return window.Dent1402Auth.loginUrl(window.location.pathname + window.location.search);
        }
        return "/account/";
    }

    function queryWithCohort() {
        var query = new URLSearchParams({
            action: "exam",
            course: courseSlug,
            exam: examSlug
        });
        var cohort = String(params.get("cohort") || "").trim();
        if (cohort) {
            query.set("cohort", cohort);
        }
        query.set("_t", String(Date.now()));
        return query;
    }

    function renderShell(title, copy, extraHtml) {
        appRoot.innerHTML = [
            '<div class="background-overlay" aria-hidden="true"></div>',
            '<main class="exam-main">',
            '  <section class="exam-panel exam-empty-state">',
            '    <h1>' + escapeHtml(title) + "</h1>",
            '    <p>' + escapeHtml(copy) + "</p>",
                 extraHtml || "",
            "  </section>",
            "</main>"
        ].join("");
    }

    function renderLoading() {
        renderShell("در حال بارگذاری آزمون", "دسترسی و داده‌های آزمون در حال بررسی است.");
    }

    function renderFailure(message) {
        renderShell("بارگذاری آزمون انجام نشد", message || "این آزمون فعلا در دسترس نیست.", [
            '<div class="exam-empty-state__actions">',
            '  <button class="back-btn" type="button" data-exam-bootstrap-action="retry">تلاش دوباره</button>',
            '  <a class="back-btn" href="/exams/">بازگشت به آزمون‌ها</a>',
            '</div>'
        ].join(""));
    }

    function renderLogin() {
        var guard = "";
        if (window.Dent1402Auth && typeof window.Dent1402Auth.renderLoginRequiredGuard === "function") {
            guard = window.Dent1402Auth.renderLoginRequiredGuard({
                loginHref: loginHref(),
                fallbackHref: "/exams/",
                primaryClass: "back-btn",
                secondaryClass: "back-btn"
            });
        } else {
            guard = '<a class="back-btn" href="' + escapeHtml(loginHref()) + '">ورود به حساب</a>';
        }
        renderShell("نیاز به ورود", "برای مشاهده سوال‌های این آزمون باید ابتدا وارد حساب کاربری خود شوید.", guard);
        if (window.Dent1402Auth && typeof window.Dent1402Auth.enhanceLoginGuards === "function") {
            window.Dent1402Auth.enhanceLoginGuards(appRoot);
        }
    }

    function renderPaywall(course) {
        var title = course && course.title ? "این درس نیاز به فعال‌سازی دارد" : "نیاز به پرداخت";
        var copy = course && course.paymentDescription
            ? course.paymentDescription
            : "برای مشاهده سوال‌های این درس، ابتدا باید دسترسی آن را فعال کنید.";
        var button = course && course.paymentPath
            ? '<a class="back-btn" href="' + escapeHtml(course.paymentPath) + '">ورود به صفحه پرداخت این درس</a>'
            : '<a class="back-btn" href="/exams/">بازگشت به آزمون‌ها</a>';
        renderShell(title, copy, button);
    }

    function mountExam(exam) {
        var existing = document.getElementById("exam-data");
        if (existing) {
            existing.remove();
        }

        var node = document.createElement("script");
        node.id = "exam-data";
        node.type = "application/json";
        node.textContent = JSON.stringify(exam || {});
        document.body.appendChild(node);

        var script = document.createElement("script");
        script.src = "/assets/site/scripts/exam-quiz.js" + assetVersionQuery;
        document.body.appendChild(script);
    }

    function warmQuizRuntime() {
        if (quizRuntimeWarmed) {
            return;
        }
        quizRuntimeWarmed = true;
        var preload = document.createElement("link");
        preload.rel = "preload";
        preload.as = "script";
        preload.href = "/assets/site/scripts/exam-quiz.js" + assetVersionQuery;
        document.head.appendChild(preload);
    }

    function fetchWithTimeout(url, options, timeoutMs) {
        if (typeof AbortController !== "function") {
            return fetch(url, options);
        }

        var controller = new AbortController();
        var requestOptions = Object.assign({}, options || {}, { signal: controller.signal });
        var timer = window.setTimeout(function () {
            controller.abort();
        }, Math.max(1000, Number(timeoutMs) || 20000));

        return fetch(url, requestOptions).finally(function () {
            window.clearTimeout(timer);
        });
    }

    function loadExam() {
        if (started) {
            return;
        }
        started = true;
        renderLoading();
        fetchWithTimeout("/api/exams_api.php?" + queryWithCohort().toString(), {
            method: "GET",
            cache: "no-store",
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        }, 20000).then(parseJson).then(function (payload) {
            if (payload && payload.success && payload.exam) {
                mountExam(payload.exam);
                return;
            }
            if (payload && payload.httpStatus === 401) {
                // A single 401 can be a transient session-lock/race hiccup, not a real
                // logout. Re-verify once with the canonical session before showing login.
                var authApi = window.Dent1402Auth;
                if (!authRechecked && authApi && typeof authApi.verifySession === "function") {
                    authRechecked = true;
                    authApi.verifySession().then(function (loggedIn) {
                        if (loggedIn === false) {
                            renderLogin();
                        } else {
                            // Session is actually valid (or check was transient) — retry.
                            started = false;
                            loadExam();
                        }
                    });
                    return;
                }
                renderLogin();
                return;
            }
            if (payload && payload.httpStatus === 403 && payload.course) {
                renderPaywall(payload.course);
                return;
            }
            throw new Error((payload && payload.error) || "بارگذاری آزمون انجام نشد.");
        }).catch(function (error) {
            if (error && error.name === "AbortError") {
                renderFailure("پاسخ سرور بیش از حد طول کشید. دوباره تلاش کنید.");
                return;
            }
            if (error instanceof TypeError) {
                renderFailure("ارتباط با سرور برقرار نشد. اتصال اینترنت خود را بررسی کنید.");
                return;
            }
            renderFailure(error && error.message ? error.message : "بارگذاری آزمون انجام نشد.");
        });
    }

    function boot() {
        renderLoading();
        warmQuizRuntime();
        // The exam endpoint performs the canonical session/permission check on
        // its own. Starting it immediately avoids a full auth-request waterfall;
        // a genuine or transient 401 is still verified through Dent1402Auth.
        loadExam();
    }

    boot();
})();
