(function () {
    "use strict";

    if (!window.Dent1402Auth) {
        return;
    }

    function $(id) {
        return document.getElementById(id);
    }

    var boot = $("dis-boot");
    var login = $("dis-login");
    var stage = $("dis-stage");
    var loginLink = $("dis-login-link");
    var form = $("dis-form");
    var submitButton = $("dis-submit");
    var feedback = $("dis-feedback");
    var statusKicker = $("dis-status-kicker");
    var statusTitle = $("dis-status-title");
    var statusText = $("dis-status-text");
    var submittedAt = $("dis-submitted-at");
    var manageLink = $("dis-manage-link");
    var toastEl = $("dis-toast");

    var accessOtherToggle = $("dis-access-other-toggle");
    var accessOtherDetail = $("dis-access-other-detail");

    var firstNameInput = $("dis-first-name");
    var lastNameInput = $("dis-last-name");
    var nationalCodeInput = $("dis-national-code");
    var phoneNumberInput = $("dis-phone-number");
    var medicalNumberInput = $("dis-medical-number");
    var personnelNumberInput = $("dis-personnel-number");
    var notesInput = $("dis-notes");

    var currentResponse = null;
    var readonlyMode = false;
    var loadingStatus = false;
    var toastTimer = 0;

    function normalizeDigits(value) {
        return String(value || "").replace(/[\u06F0-\u06F9\u0660-\u0669]/g, function (char) {
            var code = char.charCodeAt(0);
            if (code >= 0x06F0 && code <= 0x06F9) {
                return String(code - 0x06F0);
            }
            return String(code - 0x0660);
        });
    }

    function normalizeNumericInput(value) {
        return normalizeDigits(value).replace(/\D+/g, "");
    }

    function parseTimestampLike(value) {
        var raw = String(value == null ? "" : value).trim();
        if (!raw) {
            return null;
        }

        var direct = new Date(raw);
        if (Number.isFinite(direct.getTime())) {
            return direct;
        }

        var numeric = Number(raw);
        if (!Number.isFinite(numeric)) {
            return null;
        }

        if (Math.abs(numeric) < 1000000000000) {
            numeric = numeric * 1000;
        }

        var fromNumber = new Date(numeric);
        return Number.isFinite(fromNumber.getTime()) ? fromNumber : null;
    }

    function formatJalaliDateTime(value, fallback) {
        var raw = String(value == null ? "" : value).trim();
        if (!raw) {
            return fallback || "—";
        }

        var parsed = parseTimestampLike(raw);
        if (!parsed) {
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

    function showToast(text) {
        if (!toastEl || !text) {
            return;
        }

        toastEl.textContent = text;
        toastEl.classList.add("is-show");
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(function () {
            toastEl.classList.remove("is-show");
        }, 2200);
    }

    function setFeedback(text, kind) {
        if (!feedback) {
            return;
        }

        feedback.textContent = text || "";
        feedback.className = "dis-feedback" + (kind ? " is-" + kind : "");
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

    function networkErrorResponse() {
        return {
            success: false,
            error: "ارتباط با سرور برقرار نشد. اتصال اینترنت خود را بررسی کنید.",
            httpStatus: 0
        };
    }

    function consumeUnauthorized(payload, fallbackText) {
        var auth = window.Dent1402Auth && typeof window.Dent1402Auth === "object"
            ? window.Dent1402Auth
            : null;
        if (!auth) {
            return false;
        }

        try {
            if (typeof auth.handleUnauthorizedPayload === "function") {
                return !!auth.handleUnauthorizedPayload(payload, fallbackText || "نشست شما منقضی شده است.");
            }
        } catch (_error) {
            // Ignore and continue fallback checks.
        }

        if (payload && (payload.loggedOut || payload.httpStatus === 401)) {
            if (typeof auth.markUnauthorized === "function") {
                auth.markUnauthorized((payload && payload.error) || (fallbackText || "نشست شما منقضی شده است."));
            }
            return true;
        }

        return false;
    }

    function apiGet(action) {
        return fetch("/api/dis_request_api.php?action=" + encodeURIComponent(action), {
            method: "GET",
            credentials: "same-origin",
            headers: {
                "Accept": "application/json"
            }
        }).then(parseJsonResponse).catch(networkErrorResponse);
    }

    function apiPost(action, formData) {
        var body = new URLSearchParams();
        body.append("action", action);
        formData.forEach(function (value, key) {
            body.append(key, value);
        });

        return fetch("/api/dis_request_api.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                "Accept": "application/json"
            },
            body: body
        }).then(parseJsonResponse).catch(networkErrorResponse);
    }

    function showStage(name) {
        if (boot) {
            boot.hidden = name !== "boot";
        }
        if (login) {
            login.hidden = name !== "login";
        }
        if (stage) {
            stage.hidden = name !== "form";
        }
    }

    function bindDigitInput(input, maxLength) {
        if (!input) {
            return;
        }

        input.addEventListener("input", function () {
            var next = normalizeNumericInput(input.value);
            if (maxLength > 0) {
                next = next.slice(0, maxLength);
            }
            if (input.value !== next) {
                input.value = next;
            }
        });
    }

    function formElements() {
        return Array.prototype.slice.call(form.querySelectorAll("input, textarea, button"));
    }

    function setReadonlyMode(nextReadonly) {
        readonlyMode = !!nextReadonly;
        if (form) {
            form.classList.toggle("is-readonly", readonlyMode);
        }

        formElements().forEach(function (element) {
            if (element === submitButton) {
                element.disabled = readonlyMode;
                return;
            }
            if (element === accessOtherDetail && accessOtherToggle && !accessOtherToggle.checked && !readonlyMode) {
                element.disabled = true;
                return;
            }
            element.disabled = readonlyMode;
        });
    }

    function selectedValues(name) {
        return Array.prototype.slice.call(form.querySelectorAll('input[name="' + name + '"]:checked'))
            .map(function (input) {
                return String(input.value || "").trim();
            })
            .filter(Boolean);
    }

    function toggleOtherAccessState() {
        if (!accessOtherToggle || !accessOtherDetail) {
            return;
        }

        var enabled = !!accessOtherToggle.checked && !readonlyMode;
        accessOtherDetail.disabled = !enabled;
        if (!accessOtherToggle.checked && !readonlyMode) {
            accessOtherDetail.value = "";
        }
    }

    function setStatusCard(alreadySubmitted, response, formOpen) {
        if (!statusKicker || !statusTitle || !statusText || !submittedAt) {
            return;
        }

        if (alreadySubmitted && response) {
            var fullName = ((response.fields && response.fields.firstName) || "") + " " + ((response.fields && response.fields.lastName) || "");
            statusKicker.textContent = "ثبت نهایی انجام شد";
            statusTitle.textContent = "پاسخ این حساب قبلا ثبت شده است";
            statusText.textContent = "ارسال تکراری برای همین حساب غیرفعال است و پاسخ ثبت‌شده نگهداری می‌شود.";
            submittedAt.textContent = formatJalaliDateTime(response.submittedAt, "—");
            if (fullName.replace(/\s+/g, "").trim()) {
                statusText.textContent = "درخواست ثبت‌شده برای «" + fullName.trim() + "» نگهداری می‌شود و ارسال مجدد فعال نیست.";
            }
            return;
        }

        if (formOpen === false) {
            statusKicker.textContent = "فرم بسته است";
            statusTitle.textContent = "ثبت درخواست در حال حاضر فعال نیست";
            statusText.textContent = "فرم DIS توسط مدیر موقتاً بسته شده است. برای اطلاع از زمان فعال‌شدن مجدد منتظر اطلاع‌رسانی باشید.";
            submittedAt.textContent = "—";
            return;
        }

        statusKicker.textContent = "آماده ثبت";
        statusTitle.textContent = "فرم هنوز برای این حساب ارسال نشده است";
        statusText.textContent = "فیلدهای هویتی (به‌جز شماره نظام پزشکی)، یک سمت، یک نرم افزار و حداقل یک سطح دسترسی را کامل کنید.";
        submittedAt.textContent = "ثبت نشده";
    }

    function setCheckedValues(name, values) {
        var valueList = Array.isArray(values) ? values : [];
        Array.prototype.forEach.call(form.querySelectorAll('input[name="' + name + '"]'), function (input) {
            input.checked = valueList.indexOf(String(input.value || "").trim()) !== -1;
        });
    }

    function fillFormFromResponse(response) {
        if (!response || !response.fields) {
            return;
        }

        var fields = response.fields;
        firstNameInput.value = fields.firstName || "";
        lastNameInput.value = fields.lastName || "";
        nationalCodeInput.value = fields.nationalCode || "";
        phoneNumberInput.value = fields.phoneNumber || "";
        medicalNumberInput.value = fields.medicalNumber || "";
        personnelNumberInput.value = fields.personnelOrStudentNumber || "";
        notesInput.value = fields.notes || "";
        accessOtherDetail.value = fields.otherAccessDetail || "";

        setCheckedValues("positions", fields.positions || []);
        setCheckedValues("software", fields.software || []);
        setCheckedValues("accessLevels[]", fields.accessLevels || []);
        toggleOtherAccessState();
    }

    function applyPrefill(prefill) {
        if (!prefill || typeof prefill !== "object") {
            return;
        }

        if (firstNameInput && !String(firstNameInput.value || "").trim() && prefill.firstName) {
            firstNameInput.value = prefill.firstName;
        }
        if (lastNameInput && !String(lastNameInput.value || "").trim() && prefill.lastName) {
            lastNameInput.value = prefill.lastName;
        }
        if (phoneNumberInput && !String(phoneNumberInput.value || "").trim() && prefill.phoneNumber) {
            phoneNumberInput.value = prefill.phoneNumber;
        }
        if (personnelNumberInput && !String(personnelNumberInput.value || "").trim() && prefill.personnelOrStudentNumber) {
            personnelNumberInput.value = prefill.personnelOrStudentNumber;
        }
    }

    function validateClient() {
        var firstName = String(firstNameInput.value || "").trim();
        var lastName = String(lastNameInput.value || "").trim();
        var nationalCode = normalizeNumericInput(nationalCodeInput.value);
        var phoneNumber = normalizeNumericInput(phoneNumberInput.value);
        var personnelNumber = normalizeNumericInput(personnelNumberInput.value);
        var positions = selectedValues("positions");
        var software = selectedValues("software");
        var accessLevels = selectedValues("accessLevels[]");
        var otherAccessDetailText = String(accessOtherDetail.value || "").trim();

        if (!firstName || !lastName) {
            return "نام و نام خانوادگی الزامی است.";
        }
        if (nationalCode.length !== 10) {
            return "کد ملی باید ۱۰ رقم باشد.";
        }
        if (phoneNumber.length < 10 || phoneNumber.length > 14) {
            return "شماره تلفن نامعتبر است.";
        }
        if (personnelNumber.length < 3) {
            return "شماره پرسنلی / شماره دانشجویی نامعتبر است.";
        }
        if (!positions.length) {
            return "حداقل یک مورد در بخش «سمت» انتخاب کنید.";
        }
        if (!software.length) {
            return "حداقل یک مورد در بخش «نرم افزار» انتخاب کنید.";
        }
        if (!accessLevels.length) {
            return "حداقل یک مورد در بخش «سمت و سطح دسترسی» انتخاب کنید.";
        }
        if (accessLevels.indexOf("سایر") !== -1 && !otherAccessDetailText) {
            return "برای گزینه «سایر» در سطح دسترسی، توضیح کوتاه الزامی است.";
        }

        return "";
    }

    function updateFromStatusPayload(payload) {
        currentResponse = payload && payload.response ? payload.response : null;
        var formOpen = payload && payload.formOpen !== false;
        setStatusCard(!!payload.alreadySubmitted, currentResponse, payload.formOpen);

        if (manageLink) {
            manageLink.hidden = !payload.ownerAccess;
            if (payload.managePath) {
                manageLink.href = payload.managePath;
            }
        }

        if (payload.alreadySubmitted && currentResponse) {
            fillFormFromResponse(currentResponse);
            setReadonlyMode(true);
            setFeedback("پاسخ این حساب قبلا ثبت شده است.", "success");
            return;
        }

        if (!formOpen) {
            setReadonlyMode(true);
            setFeedback("فرم DIS در حال حاضر بسته است و امکان ثبت درخواست وجود ندارد.", "error");
            return;
        }

        setReadonlyMode(false);
        applyPrefill(payload.prefill || {});
        setFeedback("", "");
    }

    function loadStatus() {
        if (loadingStatus) {
            return Promise.resolve();
        }

        loadingStatus = true;
        setFeedback("", "");
        return apiGet("status").then(function (payload) {
            if (consumeUnauthorized(payload, "نشست شما منقضی شده است.")) {
                return;
            }
            if (!payload || !payload.success) {
                throw new Error((payload && payload.error) || "دریافت وضعیت فرم انجام نشد.");
            }

            showStage("form");
            updateFromStatusPayload(payload);
        }).catch(function (error) {
            showStage("form");
            setFeedback(error && error.message ? error.message : "دریافت وضعیت فرم انجام نشد.", "error");
        }).finally(function () {
            loadingStatus = false;
        });
    }

    function handleSubmit(event) {
        event.preventDefault();
        if (readonlyMode) {
            return;
        }

        var validationError = validateClient();
        if (validationError) {
            setFeedback(validationError, "error");
            return;
        }

        setFeedback("در حال ثبت درخواست...", "");
        submitButton.disabled = true;

        apiPost("submit", new FormData(form)).then(function (payload) {
            if (consumeUnauthorized(payload, "نشست شما منقضی شده است.")) {
                return;
            }

            if (!payload || !payload.success) {
                if (payload && payload.httpStatus === 409 && payload.response) {
                    updateFromStatusPayload({
                        alreadySubmitted: true,
                        response: payload.response,
                        ownerAccess: manageLink ? !manageLink.hidden : false
                    });
                }
                throw new Error((payload && payload.error) || "ثبت فرم انجام نشد.");
            }

            currentResponse = payload.response || null;
            updateFromStatusPayload({
                alreadySubmitted: true,
                response: currentResponse,
                ownerAccess: manageLink ? !manageLink.hidden : false
            });
            setFeedback((payload && payload.message) || "درخواست ثبت شد.", "success");
            showToast("درخواست ثبت شد.");
        }).catch(function (error) {
            setFeedback(error && error.message ? error.message : "ثبت فرم انجام نشد.", "error");
        }).finally(function () {
            submitButton.disabled = readonlyMode;
        });
    }

    function handleAuthChange(detail) {
        if (detail.status === "session-restoring" || detail.status === "logging-out") {
            showStage("boot");
            return;
        }

        if (!detail.loggedIn || !detail.user) {
            if (loginLink) {
                loginLink.href = window.Dent1402Auth.loginUrl("/dis-request/");
            }
            showStage("login");
            return;
        }

        showStage("form");
        loadStatus();
    }

    bindDigitInput(nationalCodeInput, 10);
    bindDigitInput(phoneNumberInput, 14);
    bindDigitInput(medicalNumberInput, 20);
    bindDigitInput(personnelNumberInput, 20);

    if (accessOtherToggle) {
        accessOtherToggle.addEventListener("change", toggleOtherAccessState);
    }

    if (form) {
        form.addEventListener("submit", handleSubmit);
    }

    toggleOtherAccessState();
    window.Dent1402Auth.onChange(handleAuthChange);
})();
