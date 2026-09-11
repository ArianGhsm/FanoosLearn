(function () {
    "use strict";

    if (!window.Dent1402Auth) {
        return;
    }

    function $(id) {
        return document.getElementById(id);
    }

    var authApi = window.Dent1402Auth && typeof window.Dent1402Auth === "object" ? window.Dent1402Auth : null;
    var siteApi = window.Dent1402Site && typeof window.Dent1402Site === "object" ? window.Dent1402Site : null;
    var pageCohort = authApi && typeof authApi.resolvePageCohort === "function"
        ? authApi.resolvePageCohort("formsCohort")
        : "main";
    var formsHomePath = authApi && typeof authApi.appendCohortQuery === "function"
        ? authApi.appendCohortQuery("/forms/", pageCohort)
        : "/forms/";

    var boot = $("forms-boot");
    var login = $("forms-login");
    var app = $("forms-app");
    var loginLink = $("forms-login-link");
    var viewerCopy = $("forms-viewer-copy");
    var formsCount = $("forms-count");
    var formsOpenCount = $("forms-open-count");
    var formsResponseCount = $("forms-response-count");
    var tabs = Array.prototype.slice.call(document.querySelectorAll("[data-forms-tab]"));
    var panels = {
        builder: $("forms-builder-panel"),
        list: $("forms-list-panel"),
        responses: $("forms-responses-panel")
    };

    var builder = $("forms-builder");
    var builderTitle = $("forms-builder-title");
    var resetBuilderBtn = $("forms-reset-builder");
    var kindInput = $("forms-kind");
    var templateInput = $("forms-template");
    var titleInput = $("forms-title-input");
    var descriptionInput = $("forms-description-input");
    var statusInput = $("forms-status");
    var audienceInput = $("forms-audience");
    var resultVisibilityInput = $("forms-result-visibility");
    var startAtInput = $("forms-start-at");
    var endAtInput = $("forms-end-at");
    var allowedStudentsInput = $("forms-allowed-students");
    var managerStudentsInput = $("forms-manager-students");
    var allowRepresentativeManageInput = $("forms-allow-representative-manage");
    var allowGuestInput = $("forms-allow-guest");
    var collectGuestNameInput = $("forms-collect-guest-name");
    var collectGuestPhoneInput = $("forms-collect-guest-phone");
    var limitOneInput = $("forms-limit-one");
    var allowEditResponseInput = $("forms-allow-edit-response");
    var allowCreatorSubmitInput = $("forms-allow-creator-submit");
    var anonymousResponsesInput = $("forms-anonymous-responses");
    var exportColumnsRoot = $("forms-export-columns");
    var exportAllFieldsInput = $("forms-export-all-fields");
    var exportFieldIdsInput = $("forms-export-field-ids");
    var fieldsEditor = $("forms-fields-editor");
    var addFieldBtn = $("forms-add-field");
    var saveBtn = $("forms-save");
    var submitTitle = $("forms-submit-title");
    var feedback = $("forms-feedback");

    var reloadBtn = $("forms-reload");
    var formsEmpty = $("forms-empty");
    var formsList = $("forms-list");
    var responsesTitle = $("forms-responses-title");
    var responsesEmpty = $("forms-responses-empty");
    var responsesList = $("forms-responses-list");
    var exportLink = $("forms-export-link");
    var closeResponsesBtn = $("forms-close-responses");
    var toastEl = $("forms-toast");

    var state = {
        viewer: null,
        activeCohort: null,
        canCreate: false,
        forms: [],
        editingId: "",
        fields: [],
        toastTimer: 0,
        loadSessionRequestId: 0,
        loadFormsRequestId: 0,
        loadResponsesRequestId: 0,
        editRequestId: 0
    };

    function parseApiResponse(response) {
        if (siteApi && typeof siteApi.parseJsonResponse === "function") {
            return siteApi.parseJsonResponse(response, response.status >= 500 ? "خطای داخلی سرور رخ داد." : "پاسخ نامعتبر از سرور دریافت شد.");
        }
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
                    error: response.status >= 500 ? "خطای داخلی سرور رخ داد." : "پاسخ نامعتبر از سرور دریافت شد."
                };
            }
            payload.httpStatus = response.status;
            return payload;
        });
    }

    function apiGet(action, params) {
        var query = new URLSearchParams(Object.assign({ action: action, cohort: pageCohort }, params || {}));
        return fetch("/api/forms_api.php?" + query.toString(), {
            method: "GET",
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        }).then(parseApiResponse).catch(function () {
            return { success: false, httpStatus: 0, error: "ارتباط با سرور برقرار نشد." };
        });
    }

    function apiPost(action, payload) {
        var body = new URLSearchParams();
        body.append("action", action);
        body.append("cohort", pageCohort);
        Object.keys(payload || {}).forEach(function (key) {
            body.append(key, payload[key]);
        });
        return fetch("/api/forms_api.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                Accept: "application/json"
            },
            body: body
        }).then(parseApiResponse).catch(function () {
            return { success: false, httpStatus: 0, error: "ارتباط با سرور برقرار نشد." };
        });
    }

    function safeAuthApi() {
        return window.Dent1402Auth && typeof window.Dent1402Auth === "object" ? window.Dent1402Auth : null;
    }

    function consumeUnauthorized(payload) {
        if (siteApi && typeof siteApi.consumeUnauthorized === "function") {
            return !!siteApi.consumeUnauthorized(payload, "نشست شما منقضی شده است.");
        }
        var auth = safeAuthApi();
        if (auth && typeof auth.handleUnauthorizedPayload === "function") {
            try {
                if (auth.handleUnauthorizedPayload(payload, "نشست شما منقضی شده است.")) {
                    return true;
                }
            } catch (_error) {
                // Continue fallback.
            }
        }
        return !!(payload && (payload.loggedOut || payload.httpStatus === 401));
    }

    function showToast(text) {
        if (!toastEl || !text) return;
        toastEl.textContent = text;
        toastEl.classList.add("is-show");
        window.clearTimeout(state.toastTimer);
        state.toastTimer = window.setTimeout(function () {
            toastEl.classList.remove("is-show");
        }, 2200);
    }

    function setFeedback(text, kind) {
        if (!feedback) return;
        feedback.textContent = text || "";
        feedback.className = "forms-feedback" + (kind ? " is-" + kind : "");
    }

    function showStage(name) {
        boot.hidden = name !== "boot";
        login.hidden = name !== "login";
        app.hidden = name !== "app";
    }

    function setTab(name) {
        Object.keys(panels).forEach(function (key) {
            if (panels[key]) {
                panels[key].hidden = key !== name;
            }
        });
        tabs.forEach(function (tab) {
            tab.classList.toggle("is-active", tab.getAttribute("data-forms-tab") === name);
        });
    }

    function formatDateTime(ts) {
        var value = Number(ts || 0);
        if (!value) return "—";
        return new Date(value * 1000).toLocaleString("fa-IR-u-ca-persian", {
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
            hour12: false
        });
    }

    function toDatetimeLocal(ts) {
        var value = Number(ts || 0);
        if (!value) return "";
        var date = new Date(value * 1000);
        var pad = function (item) {
            return String(item).padStart(2, "0");
        };
        return [
            date.getFullYear(),
            "-",
            pad(date.getMonth() + 1),
            "-",
            pad(date.getDate()),
            "T",
            pad(date.getHours()),
            ":",
            pad(date.getMinutes())
        ].join("");
    }

    function toIsoValue(value) {
        var text = String(value || "").trim();
        if (!text) return "";
        var date = new Date(text);
        return Number.isFinite(date.getTime()) ? date.toISOString() : "";
    }

    function activeCohortSupportsRotationGroups() {
        return !!(state.activeCohort && state.activeCohort.supportsRotationGroups);
    }

    function syncAudienceOptions(preferredValue) {
        if (!audienceInput) {
            return;
        }
        var selected = String(preferredValue || audienceInput.value || "link");
        var options = [
            { value: "all-users", label: "همه کاربران سایت" },
            { value: "link", label: "هر کسی که لینک را دارد" }
        ];

        if (activeCohortSupportsRotationGroups()) {
            options.push(
                { value: "rotation-1", label: "فقط روتیشن ۱" },
                { value: "rotation-2", label: "فقط روتیشن ۲" },
                { value: "both-rotations", label: "هر دو روتیشن" }
            );
        } else if (selected === "rotation-1" || selected === "rotation-2" || selected === "both-rotations") {
            selected = "all-users";
        }

        options.push({ value: "custom", label: "فهرست سفارشی" });
        audienceInput.innerHTML = "";
        options.forEach(function (item) {
            var option = document.createElement("option");
            option.value = item.value;
            option.textContent = item.label;
            option.selected = item.value === selected;
            audienceInput.appendChild(option);
        });
        if (!audienceInput.value && audienceInput.options.length) {
            audienceInput.selectedIndex = 0;
        }
    }

    function normalizeDigits(value) {
        if (siteApi && typeof siteApi.normalizeDigits === "function") {
            return siteApi.normalizeDigits(value);
        }
        return String(value || "").replace(/[\u06F0-\u06F9\u0660-\u0669]/g, function (char) {
            var code = char.charCodeAt(0);
            if (code >= 0x06F0 && code <= 0x06F9) return String(code - 0x06F0);
            return String(code - 0x0660);
        });
    }

    function bankNameFromCard(cardNumber) {
        var digits = normalizeDigits(cardNumber).replace(/\D+/g, "");
        var prefix6 = digits.slice(0, 6);
        var prefix4 = digits.slice(0, 4);
        var banks = {
            "603799": "بانک ملی ایران",
            "589210": "بانک سپه",
            "627648": "بانک توسعه صادرات",
            "627961": "بانک صنعت و معدن",
            "603770": "بانک کشاورزی",
            "628023": "بانک مسکن",
            "627760": "پست بانک ایران",
            "502908": "بانک توسعه تعاون",
            "627412": "بانک اقتصاد نوین",
            "622106": "بانک پارسیان",
            "639194": "بانک پارسیان",
            "627884": "بانک پارسیان",
            "502229": "بانک پاسارگاد",
            "639347": "بانک پاسارگاد",
            "627488": "بانک کارآفرین",
            "502910": "بانک کارآفرین",
            "621986": "بانک سامان",
            "639346": "بانک سینا",
            "639607": "بانک سرمایه",
            "636214": "بانک آینده",
            "502806": "بانک شهر",
            "502938": "بانک دی",
            "603769": "بانک صادرات ایران",
            "610433": "بانک ملت",
            "991975": "بانک ملت",
            "627353": "بانک تجارت",
            "585983": "بانک تجارت",
            "589463": "بانک رفاه کارگران",
            "627381": "بانک انصار",
            "639370": "بانک مهر اقتصاد",
            "639599": "بانک قوامین",
            "504172": "بانک رسالت",
            "636949": "بانک حکمت ایرانیان",
            "505416": "بانک گردشگری",
            "505785": "بانک ایران زمین",
            "606373": "بانک قرض الحسنه مهر ایران",
            "505801": "موسسه کوثر"
        };
        return banks[prefix6] || banks[prefix4] || "";
    }

    function selectedExportColumns() {
        if (!exportColumnsRoot) return [];
        return Array.prototype.slice.call(exportColumnsRoot.querySelectorAll("input[type='checkbox']:checked")).map(function (input) {
            return String(input.value || "");
        }).filter(Boolean);
    }

    function setExportSettings(exportSettings) {
        exportSettings = exportSettings || {};
        var columns = Array.isArray(exportSettings.identityColumns) ? exportSettings.identityColumns : [];
        if (!columns.length) {
            columns = ["index", "responseId", "submittedAt", "participantKind", "name", "studentNumber", "roleLabel", "phone"];
        }
        if (exportColumnsRoot) {
            Array.prototype.forEach.call(exportColumnsRoot.querySelectorAll("input[type='checkbox']"), function (input) {
                input.checked = columns.indexOf(String(input.value || "")) !== -1;
            });
        }
        if (exportAllFieldsInput) {
            exportAllFieldsInput.checked = exportSettings.includeAllFields !== false;
        }
        if (exportFieldIdsInput) {
            exportFieldIdsInput.value = Array.isArray(exportSettings.fieldIds) ? exportSettings.fieldIds.join("\n") : "";
        }
    }

    function collectExportSettings() {
        return {
            identityColumns: selectedExportColumns(),
            includeAllFields: exportAllFieldsInput ? !!exportAllFieldsInput.checked : true,
            fieldIds: String(exportFieldIdsInput && exportFieldIdsInput.value || "").split(/[\s,،;]+/).filter(Boolean)
        };
    }

    function formsApiHref(action, params) {
        var query = new URLSearchParams(Object.assign({ action: action, cohort: pageCohort }, params || {}));
        return "/api/forms_api.php?" + query.toString();
    }

    function uniqueFieldId() {
        return "q-" + Date.now().toString(36) + "-" + Math.floor(Math.random() * 1000).toString(36);
    }

    function option(text, index) {
        return {
            id: "opt-" + String(index || 1),
            text: text || "گزینه",
            capacity: 0
        };
    }

    // Generate an option id that never collides with existing ids — even after
    // some options were deleted (so "opt-" + length+1 is unsafe).
    function nextOptionId(options) {
        var maxN = (Array.isArray(options) ? options : []).reduce(function (max, opt) {
            var n = parseInt(String((opt && opt.id) || "").replace(/^opt-/, ""), 10);
            return isNaN(n) ? max : Math.max(max, n);
        }, 0);
        return "opt-" + (maxN + 1);
    }

    function newField(type, label) {
        var field = {
            id: uniqueFieldId(),
            type: type || "short_text",
            label: label || "پرسش",
            help: "",
            required: true,
            options: [],
            rows: [],
            scale: null,
            payment: null,
            receiptPayment: null
        };
        if (["single_choice", "multiple_choice", "dropdown"].indexOf(field.type) !== -1) {
            field.options = [option("گزینه اول", 1), option("گزینه دوم", 2)];
        }
        if (["multiple_choice_grid", "checkbox_grid"].indexOf(field.type) !== -1) {
            field.rows = [option("ردیف اول", 1)];
            field.options = [option("ستون اول", 1), option("ستون دوم", 2)];
        }
        if (field.type === "linear_scale") {
            field.scale = { min: 1, max: 5, minLabel: "", maxLabel: "" };
            field.options = [1, 2, 3, 4, 5].map(function (value) {
                return { id: String(value), text: String(value) };
            });
        }
        if (field.type === "payment") {
            field.payment = { amount: "", gateway: "" };
            field.required = true;
        }
        if (field.type === "receipt_payment") {
            field.receiptPayment = { amount: "", cardNumber: "", cardholder: "", bankName: "" };
            field.required = true;
        }
        return field;
    }

    function blankTemplate() {
        return {
            kind: "form",
            title: "",
            description: "",
            fields: [newField("short_text", "نام و نام خانوادگی")]
        };
    }

    function pollTemplate() {
        return {
            kind: "poll",
            title: "نظرسنجی جدید",
            description: "",
            fields: [newField("single_choice", "سؤال نظرسنجی")]
        };
    }

    function disTemplate() {
        return {
            kind: "form",
            title: "فرم درخواست ایجاد کاربری در DIS",
            description: "قالب عمومی فرم DIS با خروجی Excel و لینک قابل اشتراک.",
            fields: [
                newField("short_text", "نام"),
                newField("short_text", "نام خانوادگی"),
                newField("number", "کد ملی"),
                newField("phone", "شماره تلفن"),
                Object.assign(newField("number", "شماره نظام پزشکی"), { required: false }),
                newField("number", "شماره پرسنلی / شماره دانشجویی"),
                Object.assign(newField("single_choice", "سمت"), {
                    options: ["هیئت علمی", "دندانپزشک", "دانشجو", "کارمند", "رسمی", "قراردادی", "شرکتی"].map(function (text, index) { return option(text, index + 1); })
                }),
                Object.assign(newField("single_choice", "نرم‌افزار"), {
                    options: ["رادیولوژی", "DIS", "پاتولوژی", "داروخانه"].map(function (text, index) { return option(text, index + 1); })
                }),
                Object.assign(newField("multiple_choice", "سمت و سطح دسترسی"), {
                    options: ["مدیر سیستم", "مدیریت درمان", "مدیریت کلینیک", "دندانپزشکان", "مددکاری", "انبار داروخانه", "صندوق", "پذیرش", "جهادگر", "رزیدنت", "دانشجو", "حسابداری", "آموزش", "درآمد", "کارشناس DIS", "سایر"].map(function (text, index) { return option(text, index + 1); })
                }),
                Object.assign(newField("paragraph", "توضیحات"), { required: false })
            ]
        };
    }

    function cloneField(field) {
        return {
            id: String(field.id || uniqueFieldId()),
            type: String(field.type || "short_text"),
            label: String(field.label || "پرسش"),
            help: String(field.help || ""),
            required: !!field.required,
            options: Array.isArray(field.options) ? field.options.map(function (item, index) {
                var cloned = {
                    id: String(item.id || ("opt-" + (index + 1))),
                    text: String(item.text || ""),
                    capacity: Number(item.capacity || 0)
                };
                // Preserve server-reported live usage so the builder can show "X / Y".
                if (typeof item.capacityUsed === "number") {
                    cloned.capacityUsed = item.capacityUsed;
                }
                return cloned;
            }) : [],
            rows: Array.isArray(field.rows) ? field.rows.map(function (item, index) {
                return {
                    id: String(item.id || ("row-" + (index + 1))),
                    text: String(item.text || "")
                };
            }) : [],
            scale: field.scale && typeof field.scale === "object"
                ? {
                    min: Number(field.scale.min || 1),
                    max: Number(field.scale.max || 5),
                    minLabel: String(field.scale.minLabel || ""),
                    maxLabel: String(field.scale.maxLabel || "")
                }
                : null,
            payment: field.payment && typeof field.payment === "object"
                ? {
                    amount: String(field.payment.amount || ""),
                    gateway: String(field.payment.gateway || "")
                }
                : null,
            receiptPayment: field.receiptPayment && typeof field.receiptPayment === "object"
                ? {
                    amount: String(field.receiptPayment.amount || ""),
                    cardNumber: String(field.receiptPayment.cardNumber || ""),
                    cardholder: String(field.receiptPayment.cardholder || ""),
                    bankName: String(field.receiptPayment.bankName || "")
                }
                : null
        };
    }

    function fieldNeedsOptions(type) {
        return ["single_choice", "multiple_choice", "dropdown", "multiple_choice_grid", "checkbox_grid"].indexOf(type) !== -1;
    }

    function applyKindRestrictions() {
        if (kindInput.value !== "poll") return;
        if (!state.fields.length) {
            state.fields = [newField("single_choice", "سؤال نظرسنجی")];
        }
        var choiceField = null;
        state.fields.forEach(function (field) {
            if (!choiceField && ["single_choice", "multiple_choice"].indexOf(field.type) !== -1) {
                choiceField = field;
            }
        });
        if (!choiceField) {
            choiceField = state.fields[0];
            choiceField.type = "single_choice";
        }
        choiceField.required = true;
        if (!choiceField.options || choiceField.options.length < 2) {
            choiceField.options = [option("گزینه اول", 1), option("گزینه دوم", 2)];
        }
    }

    function renderFieldEditor() {
        applyKindRestrictions();
        fieldsEditor.innerHTML = "";
        var fragment = document.createDocumentFragment();
        state.fields.forEach(function (field, index) {
            var card = document.createElement("article");
            card.className = "forms-field-card";

            var top = document.createElement("div");
            top.className = "forms-field-card__top";

            var labelWrap = document.createElement("label");
            labelWrap.className = "forms-field";
            labelWrap.innerHTML = "<span>عنوان پرسش</span>";
            var labelInput = document.createElement("input");
            labelInput.type = "text";
            labelInput.maxLength = 180;
            labelInput.value = field.label || "";
            labelInput.addEventListener("input", function () {
                field.label = labelInput.value;
            });
            labelWrap.appendChild(labelInput);
            top.appendChild(labelWrap);

            var typeWrap = document.createElement("label");
            typeWrap.className = "forms-field";
            typeWrap.innerHTML = "<span>نوع پاسخ</span>";
            var typeSelect = document.createElement("select");
            [
                ["short_text", "متن کوتاه"],
                ["paragraph", "پاراگراف"],
                ["single_choice", "چندگزینه‌ای تک‌انتخاب"],
                ["multiple_choice", "چندگزینه‌ای چندانتخاب"],
                ["dropdown", "فهرست کشویی"],
                ["linear_scale", "مقیاس خطی"],
                ["multiple_choice_grid", "جدول تک‌انتخابی"],
                ["checkbox_grid", "جدول چندانتخابی"],
                ["date", "تاریخ"],
                ["time", "زمان"],
                ["number", "عدد"],
                ["email", "ایمیل"],
                ["phone", "شماره تماس"],
                ["url", "لینک"],
                ["payment", "پرداخت"],
                ["receipt_payment", "پرداخت با رسید"]
            ].forEach(function (item) {
                var optionEl = document.createElement("option");
                optionEl.value = item[0];
                optionEl.textContent = item[1];
                typeSelect.appendChild(optionEl);
            });
            typeSelect.value = field.type || "short_text";
            typeSelect.addEventListener("change", function () {
                field.type = typeSelect.value;
                if (fieldNeedsOptions(field.type) && (!field.options || field.options.length < 2)) {
                    field.options = [option("گزینه اول", 1), option("گزینه دوم", 2)];
                }
                if (["multiple_choice_grid", "checkbox_grid"].indexOf(field.type) !== -1 && (!field.rows || field.rows.length < 1)) {
                    field.rows = [option("ردیف اول", 1)];
                }
                if (field.type === "linear_scale") {
                    field.scale = field.scale || { min: 1, max: 5, minLabel: "", maxLabel: "" };
                }
                if (field.type === "payment") {
                    field.payment = field.payment || { amount: "", gateway: "" };
                    field.required = true;
                }
                if (field.type === "receipt_payment") {
                    field.receiptPayment = field.receiptPayment || { amount: "", cardNumber: "", cardholder: "", bankName: "" };
                    field.required = true;
                }
                renderFieldEditor();
            });
            typeWrap.appendChild(typeSelect);
            top.appendChild(typeWrap);

            var removeBtn = document.createElement("button");
            removeBtn.type = "button";
            removeBtn.className = "forms-btn forms-btn--danger";
            removeBtn.textContent = "حذف";
            removeBtn.hidden = state.fields.length <= 1;
            removeBtn.addEventListener("click", function () {
                state.fields.splice(index, 1);
                renderFieldEditor();
            });
            top.appendChild(removeBtn);
            card.appendChild(top);

            var helpWrap = document.createElement("label");
            helpWrap.className = "forms-field";
            helpWrap.innerHTML = "<span>راهنمای کوتاه</span>";
            var helpInput = document.createElement("input");
            helpInput.type = "text";
            helpInput.maxLength = 500;
            helpInput.value = field.help || "";
            helpInput.addEventListener("input", function () {
                field.help = helpInput.value;
            });
            helpWrap.appendChild(helpInput);
            card.appendChild(helpWrap);

            var requiredLabel = document.createElement("label");
            requiredLabel.className = "forms-toggle";
            var requiredInput = document.createElement("input");
            requiredInput.type = "checkbox";
            requiredInput.checked = !!field.required;
            requiredInput.disabled = false;
            requiredInput.addEventListener("change", function () {
                field.required = requiredInput.checked;
            });
            requiredLabel.appendChild(requiredInput);
            var requiredText = document.createElement("span");
            requiredText.textContent = "پاسخ به این پرسش الزامی باشد";
            requiredLabel.appendChild(requiredText);
            card.appendChild(requiredLabel);

            if (fieldNeedsOptions(field.type)) {
                card.appendChild(renderOptionsEditor(field));
            }
            if (["multiple_choice_grid", "checkbox_grid"].indexOf(field.type) !== -1) {
                card.appendChild(renderRowsEditor(field));
            }
            if (field.type === "linear_scale") {
                card.appendChild(renderScaleEditor(field));
            }
            if (field.type === "payment") {
                card.appendChild(renderPaymentEditor(field));
            }
            if (field.type === "receipt_payment") {
                card.appendChild(renderReceiptPaymentEditor(field));
            }

            fragment.appendChild(card);
        });
        fieldsEditor.appendChild(fragment);
    }

    function renderOptionsEditor(field) {
        var hasCap = ["single_choice", "multiple_choice", "dropdown"].indexOf(field.type) !== -1;
        var wrap = document.createElement("div");
        wrap.className = "forms-options-box";
        field.options = Array.isArray(field.options) ? field.options : [];

        if (hasCap) {
            var capHeader = document.createElement("div");
            capHeader.className = "forms-option-line forms-option-line--has-cap forms-option-cap-header";
            var capHeaderText = document.createElement("span");
            capHeaderText.textContent = "متن گزینه";
            capHeader.appendChild(capHeaderText);
            var capHeaderCap = document.createElement("span");
            capHeaderCap.textContent = "ظرفیت";
            capHeader.appendChild(capHeaderCap);
            var capHeaderUsed = document.createElement("span");
            capHeaderUsed.textContent = "ثبت‌شده";
            capHeader.appendChild(capHeaderUsed);
            capHeader.appendChild(document.createElement("span")); // placeholder for × column
            wrap.appendChild(capHeader);
        }

        field.options.forEach(function (item, index) {
            var row = document.createElement("div");
            row.className = hasCap ? "forms-option-line forms-option-line--has-cap" : "forms-option-line";
            var input = document.createElement("input");
            input.type = "text";
            input.maxLength = 160;
            input.value = item.text || "";
            input.addEventListener("input", function () {
                item.text = input.value;
            });
            row.appendChild(input);
            if (hasCap) {
                var capInput = document.createElement("input");
                capInput.type = "text";
                capInput.inputMode = "numeric";
                capInput.pattern = "[0-9]*";
                capInput.className = "forms-option-cap";
                capInput.placeholder = "∞";
                capInput.value = item.capacity > 0 ? String(item.capacity) : "";
                capInput.title = "ظرفیت (خالی = بدون محدودیت)";
                capInput.addEventListener("input", function () {
                    var normalized = normalizeDigits(capInput.value).replace(/\D+/g, "");
                    if (capInput.value !== normalized) {
                        capInput.value = normalized;
                    }
                    var v = parseInt(normalized, 10);
                    item.capacity = isNaN(v) || v < 0 ? 0 : v;
                });
                row.appendChild(capInput);

                // Show live usage "X / Y" when capacity is set and the server reported it.
                var usage = document.createElement("span");
                usage.className = "forms-option-usage";
                if (Number(item.capacity) > 0 && typeof item.capacityUsed === "number") {
                    usage.textContent = Number(item.capacityUsed).toLocaleString("fa-IR") + " / " + Number(item.capacity).toLocaleString("fa-IR");
                    if (item.capacityUsed >= item.capacity) {
                        usage.classList.add("is-full");
                    }
                } else {
                    usage.textContent = "";
                }
                row.appendChild(usage);
            }
            var remove = document.createElement("button");
            remove.type = "button";
            remove.className = "forms-btn forms-btn--danger";
            remove.textContent = "×";
            remove.addEventListener("click", function () {
                if (field.options.length <= 2) {
                    showToast("حداقل دو گزینه لازم است.");
                    return;
                }
                var hasUsage = (Number(item.capacity) > 0) || (Number(item.capacityUsed) > 0);
                if (hasUsage && !window.confirm("این گزینه ظرفیت یا پاسخ دارد. با حذف آن، گزینه به‌صورت «حذف‌شده» نگه داشته می‌شود تا پاسخ‌های قبلی و شمارش ظرفیت درست بمانند، اما دیگر برای کاربران نمایش داده نمی‌شود. ادامه می‌دهید؟")) {
                    return;
                }
                field.options.splice(index, 1);
                renderFieldEditor();
            });
            row.appendChild(remove);
            wrap.appendChild(row);
        });

        var add = document.createElement("button");
        add.type = "button";
        add.className = "forms-btn";
        add.textContent = "افزودن گزینه";
        add.addEventListener("click", function () {
            if (field.options.length >= 50) {
                showToast("حداکثر ۵۰ گزینه مجاز است.");
                return;
            }
            field.options.push({ id: nextOptionId(field.options), text: "گزینه جدید", capacity: 0 });
            renderFieldEditor();
        });
        wrap.appendChild(add);
        return wrap;
    }

    function renderRowsEditor(field) {
        var wrap = document.createElement("div");
        wrap.className = "forms-options-box";
        var title = document.createElement("small");
        title.className = "forms-muted";
        title.textContent = "ردیف‌های جدول";
        wrap.appendChild(title);
        field.rows = Array.isArray(field.rows) ? field.rows : [];
        field.rows.forEach(function (item, index) {
            var row = document.createElement("div");
            row.className = "forms-option-line";
            var input = document.createElement("input");
            input.type = "text";
            input.maxLength = 160;
            input.value = item.text || "";
            input.addEventListener("input", function () {
                item.text = input.value;
            });
            row.appendChild(input);
            var remove = document.createElement("button");
            remove.type = "button";
            remove.className = "forms-btn forms-btn--danger";
            remove.textContent = "×";
            remove.addEventListener("click", function () {
                if (field.rows.length <= 1) {
                    showToast("حداقل یک ردیف لازم است.");
                    return;
                }
                field.rows.splice(index, 1);
                renderFieldEditor();
            });
            row.appendChild(remove);
            wrap.appendChild(row);
        });
        var add = document.createElement("button");
        add.type = "button";
        add.className = "forms-btn";
        add.textContent = "افزودن ردیف";
        add.addEventListener("click", function () {
            field.rows.push(option("ردیف جدید", field.rows.length + 1));
            renderFieldEditor();
        });
        wrap.appendChild(add);
        return wrap;
    }

    function renderScaleEditor(field) {
        var wrap = document.createElement("div");
        wrap.className = "forms-scale-grid";
        field.scale = field.scale || { min: 1, max: 5, minLabel: "", maxLabel: "" };
        [
            ["min", "حداقل", "number"],
            ["max", "حداکثر", "number"],
            ["minLabel", "برچسب حداقل", "text"],
            ["maxLabel", "برچسب حداکثر", "text"]
        ].forEach(function (item) {
            var label = document.createElement("label");
            label.className = "forms-field";
            label.innerHTML = "<span>" + item[1] + "</span>";
            var input = document.createElement("input");
            input.type = item[2];
            input.value = field.scale[item[0]] == null ? "" : String(field.scale[item[0]]);
            if (item[2] === "number") {
                input.min = "0";
                input.max = "10";
            }
            input.addEventListener("input", function () {
                field.scale[item[0]] = item[2] === "number" ? Number(input.value || "0") : input.value;
            });
            label.appendChild(input);
            wrap.appendChild(label);
        });
        return wrap;
    }

    function renderPaymentEditor(field) {
        var wrap = document.createElement("div");
        wrap.className = "forms-payment-editor";
        field.payment = field.payment || { amount: "", gateway: "" };

        var amountWrap = document.createElement("label");
        amountWrap.className = "forms-field";
        amountWrap.innerHTML = "<span>مبلغ پرداخت (ریال)</span>";
        var amountInput = document.createElement("input");
        amountInput.type = "text";
        amountInput.inputMode = "numeric";
        amountInput.dir = "ltr";
        amountInput.setAttribute("data-latin-digits", "true");
        amountInput.maxLength = 14;
        amountInput.value = field.payment.amount || "";
        amountInput.addEventListener("input", function () {
            field.payment.amount = normalizeDigits(amountInput.value).replace(/\D+/g, "");
            amountInput.value = field.payment.amount;
        });
        amountWrap.appendChild(amountInput);
        wrap.appendChild(amountWrap);

        var gatewayWrap = document.createElement("label");
        gatewayWrap.className = "forms-field";
        gatewayWrap.innerHTML = "<span>درگاه پیش‌فرض سوال</span>";
        var gatewayInput = document.createElement("input");
        gatewayInput.type = "text";
        gatewayInput.maxLength = 60;
        gatewayInput.dir = "ltr";
        gatewayInput.setAttribute("data-latin-digits", "true");
        gatewayInput.placeholder = "اختیاری؛ اگر خالی باشد کاربر درگاه را انتخاب می‌کند";
        gatewayInput.value = field.payment.gateway || "";
        gatewayInput.addEventListener("input", function () {
            field.payment.gateway = gatewayInput.value.trim();
        });
        gatewayWrap.appendChild(gatewayInput);
        wrap.appendChild(gatewayWrap);

        var note = document.createElement("small");
        note.className = "forms-muted";
        note.textContent = "پاسخ این سوال فقط بعد از پرداخت تاییدشده همراه پاسخ فرم ثبت می‌شود.";
        wrap.appendChild(note);
        return wrap;
    }

    function renderReceiptPaymentEditor(field) {
        var wrap = document.createElement("div");
        wrap.className = "forms-payment-editor forms-receipt-editor";
        field.receiptPayment = field.receiptPayment || { amount: "", cardNumber: "", cardholder: "", bankName: "" };

        var amountWrap = document.createElement("label");
        amountWrap.className = "forms-field";
        amountWrap.innerHTML = "<span>مبلغ رسید (ریال)</span>";
        var amountInput = document.createElement("input");
        amountInput.type = "text";
        amountInput.inputMode = "numeric";
        amountInput.dir = "ltr";
        amountInput.setAttribute("data-latin-digits", "true");
        amountInput.maxLength = 14;
        amountInput.value = field.receiptPayment.amount || "";
        amountInput.addEventListener("input", function () {
            field.receiptPayment.amount = normalizeDigits(amountInput.value).replace(/\D+/g, "");
            amountInput.value = field.receiptPayment.amount;
        });
        amountWrap.appendChild(amountInput);
        wrap.appendChild(amountWrap);

        var cardWrap = document.createElement("label");
        cardWrap.className = "forms-field";
        cardWrap.innerHTML = "<span>شماره کارت مقصد</span>";
        var cardInput = document.createElement("input");
        cardInput.type = "text";
        cardInput.inputMode = "numeric";
        cardInput.dir = "ltr";
        cardInput.setAttribute("data-latin-digits", "true");
        cardInput.maxLength = 24;
        cardInput.value = field.receiptPayment.cardNumber || "";
        cardWrap.appendChild(cardInput);
        wrap.appendChild(cardWrap);

        var holderWrap = document.createElement("label");
        holderWrap.className = "forms-field";
        holderWrap.innerHTML = "<span>نام صاحب کارت</span>";
        var holderInput = document.createElement("input");
        holderInput.type = "text";
        holderInput.maxLength = 120;
        holderInput.value = field.receiptPayment.cardholder || "";
        holderInput.addEventListener("input", function () {
            field.receiptPayment.cardholder = holderInput.value.trim();
        });
        holderWrap.appendChild(holderInput);
        wrap.appendChild(holderWrap);

        var bankNote = document.createElement("small");
        bankNote.className = "forms-muted";
        var syncBank = function () {
            field.receiptPayment.cardNumber = normalizeDigits(cardInput.value).replace(/\D+/g, "");
            cardInput.value = field.receiptPayment.cardNumber;
            field.receiptPayment.bankName = bankNameFromCard(field.receiptPayment.cardNumber);
            bankNote.textContent = field.receiptPayment.bankName ? ("بانک تشخیص داده‌شده: " + field.receiptPayment.bankName) : "پس از وارد کردن شماره کارت، بانک صادرکننده نمایش داده می‌شود.";
        };
        cardInput.addEventListener("input", syncBank);
        syncBank();
        wrap.appendChild(bankNote);

        var note = document.createElement("small");
        note.className = "forms-muted";
        note.textContent = "پرداخت‌کننده برای تکمیل این بخش باید تصویر یا PDF رسید را بارگذاری کند.";
        wrap.appendChild(note);
        return wrap;
    }

    function applyTemplate(name) {
        if (pageCohort === "prosthesis-1402" && name === "dis") {
            name = "blank";
        }
        var tpl = name === "dis" ? disTemplate() : (name === "poll" ? pollTemplate() : blankTemplate());
        state.editingId = "";
        kindInput.value = tpl.kind;
        titleInput.value = tpl.title;
        descriptionInput.value = tpl.description;
        statusInput.value = "open";
        syncAudienceOptions("link");
        resultVisibilityInput.value = tpl.kind === "poll" ? "after-submit" : "manager-only";
        startAtInput.value = "";
        endAtInput.value = "";
        allowedStudentsInput.value = "";
        managerStudentsInput.value = "";
        allowRepresentativeManageInput.checked = false;
        allowGuestInput.checked = false;
        collectGuestNameInput.checked = true;
        collectGuestPhoneInput.checked = false;
        limitOneInput.checked = true;
        allowEditResponseInput.checked = false;
        allowCreatorSubmitInput.checked = true;
        anonymousResponsesInput.checked = false;
        setExportSettings({ identityColumns: ["index", "responseId", "submittedAt", "participantKind", "name", "studentNumber", "roleLabel", "phone"], includeAllFields: true, fieldIds: [] });
        state.fields = tpl.fields.map(cloneField);
        builderTitle.textContent = "ساخت فرم جدید";
        submitTitle.textContent = "ذخیره فرم";
        saveBtn.textContent = "ذخیره";
        renderFieldEditor();
        setFeedback("", "");
    }

    function populateBuilder(form) {
        state.editingId = String(form.id || "");
        kindInput.value = String(form.kind || "form");
        templateInput.value = "blank";
        titleInput.value = String(form.title || "");
        descriptionInput.value = String(form.description || "");
        statusInput.value = ["draft", "open", "closed"].indexOf(String(form.status || "")) !== -1 ? String(form.status) : "open";
        var settings = form.settings || {};
        syncAudienceOptions(String(settings.audience || "link"));
        resultVisibilityInput.value = String(settings.resultVisibility || "after-submit");
        startAtInput.value = toDatetimeLocal(settings.startAt);
        endAtInput.value = toDatetimeLocal(settings.endAt);
        allowedStudentsInput.value = Array.isArray(settings.allowedStudents) ? settings.allowedStudents.join("\n") : "";
        managerStudentsInput.value = Array.isArray(settings.managerStudentNumbers) ? settings.managerStudentNumbers.join("\n") : "";
        allowRepresentativeManageInput.checked = !!settings.allowRepresentativeManage;
        allowGuestInput.checked = !!settings.allowGuest;
        collectGuestNameInput.checked = settings.collectGuestName !== false;
        collectGuestPhoneInput.checked = !!settings.collectGuestPhone;
        limitOneInput.checked = settings.limitOneResponse !== false;
        allowEditResponseInput.checked = !!settings.allowEditResponse;
        allowCreatorSubmitInput.checked = settings.allowCreatorSubmit !== false;
        anonymousResponsesInput.checked = !!settings.anonymousResponses;
        setExportSettings(settings.export || {});
        state.fields = Array.isArray(form.fields) ? form.fields.map(cloneField) : [newField()];
        builderTitle.textContent = "ویرایش " + String(form.kindLabel || "فرم");
        submitTitle.textContent = "ذخیره تغییرات";
        saveBtn.textContent = "ذخیره تغییرات";
        renderFieldEditor();
        setFeedback("می‌توانید شرکت‌کنندگان مجاز و تنظیمات را بعد از ساخت هم ویرایش کنید.", "");
        setTab("builder");
    }

    function collectPayload() {
        var title = String(titleInput.value || "").trim();
        if (!title) {
            throw new Error("عنوان را وارد کنید.");
        }
        var fields = state.fields.map(function (field) {
            var next = cloneField(field);
            next.label = String(next.label || "").trim();
            next.help = String(next.help || "").trim();
            if (fieldNeedsOptions(next.type)) {
                next.options = next.options.map(function (item, index) {
                    return {
                        id: String(item.id || ("opt-" + (index + 1))),
                        text: String(item.text || "").trim(),
                        capacity: Number(item.capacity || 0)
                    };
                }).filter(function (item) {
                    return item.text;
                });
            }
            if (["multiple_choice_grid", "checkbox_grid"].indexOf(next.type) !== -1) {
                next.rows = next.rows.map(function (item, index) {
                    return {
                        id: String(item.id || ("row-" + (index + 1))),
                        text: String(item.text || "").trim()
                    };
                }).filter(function (item) {
                    return item.text;
                });
                if (next.rows.length < 1) {
                    throw new Error("برای جدول حداقل یک ردیف لازم است.");
                }
            }
            if (fieldNeedsOptions(next.type) && next.options.length < 2) {
                throw new Error("برای پرسش‌های گزینه‌ای حداقل دو گزینه لازم است.");
            }
            if (next.type === "payment") {
                next.payment = next.payment || { amount: "", gateway: "" };
                next.payment.amount = normalizeDigits(String(next.payment.amount || "")).replace(/\D+/g, "");
                next.payment.gateway = String(next.payment.gateway || "").trim();
                next.required = !!next.required;
                if (!next.payment.amount || Number(next.payment.amount) <= 0) {
                    throw new Error("برای سوال پرداخت باید مبلغ بیشتر از صفر وارد شود.");
                }
            }
            if (next.type === "receipt_payment") {
                next.receiptPayment = next.receiptPayment || { amount: "", cardNumber: "", cardholder: "", bankName: "" };
                next.receiptPayment.amount = normalizeDigits(String(next.receiptPayment.amount || "")).replace(/\D+/g, "");
                next.receiptPayment.cardNumber = normalizeDigits(String(next.receiptPayment.cardNumber || "")).replace(/\D+/g, "");
                next.receiptPayment.cardholder = String(next.receiptPayment.cardholder || "").trim();
                next.receiptPayment.bankName = bankNameFromCard(next.receiptPayment.cardNumber) || String(next.receiptPayment.bankName || "").trim();
                next.required = !!next.required;
                if (!next.receiptPayment.amount || Number(next.receiptPayment.amount) <= 0) {
                    throw new Error("برای سوال پرداخت با رسید باید مبلغ بیشتر از صفر وارد شود.");
                }
                if (next.receiptPayment.cardNumber.length < 16) {
                    throw new Error("برای سوال پرداخت با رسید باید شماره کارت معتبر وارد شود.");
                }
                if (!next.receiptPayment.cardholder) {
                    throw new Error("برای سوال پرداخت با رسید باید نام صاحب کارت وارد شود.");
                }
            }
            return next;
        }).filter(function (field) {
            return field.label;
        });
        if (!fields.length) {
            throw new Error("حداقل یک پرسش معتبر لازم است.");
        }
        return {
            kind: String(kindInput.value || "form"),
            title: title,
            description: String(descriptionInput.value || "").trim(),
            status: String(statusInput.value || "open"),
            settings: {
                audience: String(audienceInput.value || "link"),
                allowedStudents: normalizeDigits(String(allowedStudentsInput.value || "")).split(/[\s,،;]+/).filter(Boolean),
                allowRepresentativeManage: !!allowRepresentativeManageInput.checked,
                managerStudentNumbers: normalizeDigits(String(managerStudentsInput.value || "")).split(/[\s,،;]+/).filter(Boolean),
                allowGuest: !!allowGuestInput.checked,
                collectGuestName: !!collectGuestNameInput.checked,
                collectGuestPhone: !!collectGuestPhoneInput.checked,
                limitOneResponse: !!limitOneInput.checked,
                allowEditResponse: !!allowEditResponseInput.checked,
                allowCreatorSubmit: !!allowCreatorSubmitInput.checked,
                anonymousResponses: !!anonymousResponsesInput.checked,
                export: collectExportSettings(),
                resultVisibility: String(resultVisibilityInput.value || "after-submit"),
                startAt: toIsoValue(startAtInput.value),
                endAt: toIsoValue(endAtInput.value)
            },
            fields: fields
        };
    }

    function updateSummary() {
        var total = state.forms.length;
        var open = 0;
        var responses = 0;
        state.forms.forEach(function (form) {
            if (form.status === "open") open++;
            responses += Number(form.responseCount || 0);
        });
        formsCount.textContent = total.toLocaleString("fa-IR");
        formsOpenCount.textContent = open.toLocaleString("fa-IR");
        formsResponseCount.textContent = responses.toLocaleString("fa-IR");
    }

    function copyText(value) {
        if (!value) return Promise.reject(new Error("empty"));
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(value);
        }
        return new Promise(function (resolve, reject) {
            try {
                var helper = document.createElement("textarea");
                helper.value = value;
                helper.setAttribute("readonly", "");
                helper.style.position = "fixed";
                helper.style.opacity = "0";
                document.body.appendChild(helper);
                helper.select();
                document.execCommand("copy");
                helper.remove();
                resolve();
            } catch (error) {
                reject(error);
            }
        });
    }

    function formHasReceiptPayments(form) {
        // Use the lightweight server-side flag (always present) instead of scanning
        // the full fields array, which is omitted from list responses.
        if (form && typeof form.hasReceiptPaymentFields === "boolean") {
            return form.hasReceiptPaymentFields;
        }
        return Array.isArray(form && form.fields) && form.fields.some(function (field) {
            return field && String(field.type || "") === "receipt_payment";
        });
    }

    async function editForm(formId) {
        var cleanId = String(formId || "");
        if (!cleanId) return;
        var requestId = state.editRequestId + 1;
        state.editRequestId = requestId;
        setFeedback("در حال دریافت نسخه کامل فرم برای ویرایش...", "");
        try {
            var response = await apiGet("get", { formId: cleanId });
            if (requestId !== state.editRequestId) {
                return;
            }
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success || !response.form) {
                throw new Error((response && response.error) || "دریافت فرم برای ویرایش انجام نشد.");
            }
            populateBuilder(response.form);
        } catch (error) {
            if (requestId !== state.editRequestId) {
                return;
            }
            setFeedback(error && error.message ? error.message : "دریافت فرم برای ویرایش انجام نشد.", "error");
            showToast(error && error.message ? error.message : "دریافت فرم برای ویرایش انجام نشد.");
        }
    }

    function renderFormsList() {
        updateSummary();
        formsList.innerHTML = "";
        formsEmpty.hidden = state.forms.length > 0;
        var fragment = document.createDocumentFragment();
        state.forms.forEach(function (form) {
            var item = document.createElement("article");
            item.className = "forms-item";

            var head = document.createElement("div");
            head.className = "forms-item__head";
            var copy = document.createElement("div");
            var title = document.createElement("h3");
            title.textContent = String(form.title || "فرم");
            copy.appendChild(title);
            var meta = document.createElement("p");
            meta.className = "forms-item__meta";
            meta.textContent = [
                String(form.kindLabel || "فرم"),
                String(form.statusLabel || "نامشخص"),
                "پاسخ‌ها: " + Number(form.responseCount || 0).toLocaleString("fa-IR"),
                "دسترسی: " + String(form.settings && form.settings.audienceLabel ? form.settings.audienceLabel : "")
            ].filter(Boolean).join(" • ");
            copy.appendChild(meta);
            head.appendChild(copy);
            var chip = document.createElement("span");
            chip.className = "forms-chip" + (form.status === "open" ? "" : " forms-chip--soft");
            chip.textContent = String(form.statusLabel || "—");
            head.appendChild(chip);
            item.appendChild(head);

            var actions = document.createElement("div");
            actions.className = "forms-actions";

            var open = document.createElement("a");
            open.className = "forms-btn forms-btn--primary";
            open.href = String(form.sharePath || "#");
            open.target = "_blank";
            open.rel = "noopener";
            open.textContent = "باز کردن";
            actions.appendChild(open);

            var copyBtn = document.createElement("button");
            copyBtn.className = "forms-btn";
            copyBtn.type = "button";
            copyBtn.textContent = "کپی لینک";
            copyBtn.addEventListener("click", function () {
                copyText(String(form.shareUrl || "")).then(function () {
                    showToast("لینک کپی شد.");
                }).catch(function () {
                    showToast("کپی لینک انجام نشد.");
                });
            });
            actions.appendChild(copyBtn);

            if (form.permissions && form.permissions.canManage) {
                var edit = document.createElement("button");
                edit.className = "forms-btn";
                edit.type = "button";
                edit.textContent = "ویرایش";
                edit.addEventListener("click", function () {
                    editForm(form.id);
                });
                actions.appendChild(edit);

                var responses = document.createElement("button");
                responses.className = "forms-btn";
                responses.type = "button";
                responses.textContent = "پاسخ‌ها";
                responses.addEventListener("click", function () {
                    loadResponses(form.id);
                });
                actions.appendChild(responses);

                var statusBtn = document.createElement("button");
                statusBtn.className = form.status === "open" ? "forms-btn forms-btn--danger" : "forms-btn";
                statusBtn.type = "button";
                statusBtn.textContent = form.status === "open" ? "بستن" : "فعال‌سازی";
                statusBtn.addEventListener("click", function () {
                    setFormStatus(form.id, form.status === "open" ? "closed" : "open");
                });
                actions.appendChild(statusBtn);

                var exportA = document.createElement("a");
                exportA.className = "forms-btn";
                exportA.href = formsApiHref("export", { mode: "responses", formId: String(form.id || "") });
                exportA.textContent = "Excel پاسخ‌ها";
                actions.appendChild(exportA);

                var summaryA = document.createElement("a");
                summaryA.className = "forms-btn";
                summaryA.href = formsApiHref("export", { mode: "summary", formId: String(form.id || "") });
                summaryA.textContent = "خلاصه Excel";
                actions.appendChild(summaryA);

                var officialA = document.createElement("a");
                officialA.className = "forms-btn";
                officialA.href = formsApiHref("export", { mode: "official", formId: String(form.id || "") });
                officialA.textContent = "خروجی رسمی";
                actions.appendChild(officialA);

                if (formHasReceiptPayments(form)) {
                    var receiptsA = document.createElement("a");
                    receiptsA.className = "forms-btn";
                    receiptsA.href = formsApiHref("exportReceipts", { formId: String(form.id || "") });
                    receiptsA.textContent = "ZIP رسیدها";
                    actions.appendChild(receiptsA);
                }

                if (form.permissions && form.permissions.canDelete) {
                    var deleteBtn = document.createElement("button");
                    deleteBtn.className = "forms-btn forms-btn--danger";
                    deleteBtn.type = "button";
                    deleteBtn.textContent = "حذف کامل";
                    deleteBtn.addEventListener("click", function () {
                        deleteForm(form.id);
                    });
                    actions.appendChild(deleteBtn);
                }
            }

            item.appendChild(actions);
            fragment.appendChild(item);
        });
        formsList.appendChild(fragment);
    }

    async function loadForms() {
        var requestId = state.loadFormsRequestId + 1;
        state.loadFormsRequestId = requestId;
        reloadBtn.disabled = true;
        try {
            var response = await apiGet("list");
            if (requestId !== state.loadFormsRequestId) {
                return;
            }
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                throw new Error((response && response.error) || "بارگذاری فرم‌ها انجام نشد.");
            }
            state.forms = Array.isArray(response.forms) ? response.forms : [];
            state.canCreate = !!response.canCreate;
            renderFormsList();
        } catch (error) {
            if (requestId !== state.loadFormsRequestId) {
                return;
            }
            showToast(error && error.message ? error.message : "بارگذاری انجام نشد.");
        } finally {
            if (requestId === state.loadFormsRequestId) {
                reloadBtn.disabled = false;
            }
        }
    }

    async function saveForm(event) {
        event.preventDefault();
        if (!state.canCreate && !state.editingId) return;
        var payload;
        try {
            payload = collectPayload();
        } catch (error) {
            setFeedback(error.message, "error");
            return;
        }
        saveBtn.disabled = true;
        setFeedback("در حال ذخیره...", "");
        try {
            var postPayload = {
                payload: JSON.stringify(payload)
            };
            var action = state.editingId ? "update" : "create";
            if (state.editingId) {
                postPayload.formId = state.editingId;
            }
            var response = await apiPost(action, postPayload);
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success || !response.form) {
                throw new Error((response && response.error) || "ذخیره فرم انجام نشد.");
            }
            state.editingId = String(response.form.id || "");
            setFeedback((response && response.message) || "ذخیره شد.", "success");
            showToast("فرم ذخیره شد.");
            await loadForms();
            populateBuilder(response.form);
        } catch (error) {
            setFeedback(error && error.message ? error.message : "ذخیره انجام نشد.", "error");
        } finally {
            saveBtn.disabled = false;
        }
    }

    async function setFormStatus(formId, status) {
        try {
            var response = await apiPost("setStatus", {
                formId: String(formId || ""),
                status: String(status || "open")
            });
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                throw new Error((response && response.error) || "تغییر وضعیت انجام نشد.");
            }
            showToast(status === "open" ? "فرم فعال شد." : "فرم بسته شد.");
            await loadForms();
        } catch (error) {
            showToast(error && error.message ? error.message : "تغییر وضعیت انجام نشد.");
        }
    }

    async function deleteForm(formId) {
        if (!formId || !window.confirm("این فرم و همه پاسخ‌های آن حذف شود؟ این کار فقط برای مالک مجاز است و برگشت‌پذیر نیست.")) {
            return;
        }
        try {
            var response = await apiPost("delete", {
                formId: String(formId || "")
            });
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                throw new Error((response && response.error) || "حذف فرم انجام نشد.");
            }
            if (state.editingId === String(formId || "")) {
                applyTemplate("blank");
            }
            responsesList.innerHTML = "";
            responsesEmpty.hidden = false;
            exportLink.hidden = true;
            exportLink.removeAttribute("href");
            showToast("فرم حذف شد.");
            await loadForms();
        } catch (error) {
            showToast(error && error.message ? error.message : "حذف فرم انجام نشد.");
        }
    }

    async function loadResponses(formId) {
        var requestId = state.loadResponsesRequestId + 1;
        state.loadResponsesRequestId = requestId;
        try {
            var response = await apiGet("responses", { formId: String(formId || "") });
            if (requestId !== state.loadResponsesRequestId) {
                return;
            }
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                throw new Error((response && response.error) || "دریافت پاسخ‌ها انجام نشد.");
            }
            renderResponses(response.form || null, response.responses || []);
            setTab("responses");
        } catch (error) {
            if (requestId !== state.loadResponsesRequestId) {
                return;
            }
            showToast(error && error.message ? error.message : "دریافت پاسخ‌ها انجام نشد.");
        }
    }

    function renderResponses(form, responses) {
        responsesList.innerHTML = "";
        responses = Array.isArray(responses) ? responses : [];
        responsesEmpty.hidden = responses.length > 0;
        responsesTitle.textContent = form && form.title ? "پاسخ‌ها: " + form.title : "پاسخ‌ها";
        if (form && form.id) {
            exportLink.hidden = false;
            exportLink.href = formsApiHref("export", { mode: "responses", formId: String(form.id) });
        } else {
            exportLink.hidden = true;
            exportLink.removeAttribute("href");
        }
        state.responsesForm = form || null;
        var canManage = !!(form && form.permissions && form.permissions.canManage);
        var fragment = document.createDocumentFragment();
        responses.forEach(function (response) {
            var card = document.createElement("article");
            card.className = "forms-response-card";
            card.setAttribute("data-response-id", String(response.id || ""));
            var title = document.createElement("h3");
            var identity = response.identity || {};
            title.textContent = String(identity.name || identity.studentNumber || "شرکت‌کننده") + " • " + formatDateTime(response.submittedAt);
            card.appendChild(title);
            var grid = document.createElement("div");
            grid.className = "forms-answer-grid";
            (Array.isArray(response.answers) ? response.answers : []).forEach(function (answer) {
                var item = document.createElement("div");
                item.className = "forms-answer";
                var label = document.createElement("span");
                label.textContent = String(answer.label || "");
                item.appendChild(label);
                var value = document.createElement("strong");
                value.textContent = String(answer.displayValue || "—");
                item.appendChild(value);
                grid.appendChild(item);
            });
            card.appendChild(grid);

            if (canManage) {
                var actions = document.createElement("div");
                actions.className = "forms-response-actions";
                var editBtn = document.createElement("button");
                editBtn.type = "button";
                editBtn.className = "forms-btn";
                editBtn.textContent = "ویرایش";
                editBtn.addEventListener("click", function () {
                    startEditResponse(card, response);
                });
                var delBtn = document.createElement("button");
                delBtn.type = "button";
                delBtn.className = "forms-btn forms-btn--danger";
                delBtn.textContent = "حذف";
                delBtn.addEventListener("click", function () {
                    deleteResponse(response.id);
                });
                actions.appendChild(editBtn);
                actions.appendChild(delBtn);
                card.appendChild(actions);
            }
            fragment.appendChild(card);
        });
        responsesList.appendChild(fragment);
    }

    var EDITABLE_TYPES = ["short_text", "paragraph", "single_choice", "multiple_choice", "dropdown", "linear_scale"];

    function startEditResponse(card, response) {
        var form = state.responsesForm;
        if (!form) return;
        var fieldsById = {};
        (Array.isArray(form.fields) ? form.fields : []).forEach(function (f) {
            fieldsById[String(f.id || "")] = f;
        });
        var grid = card.querySelector(".forms-answer-grid");
        var actions = card.querySelector(".forms-response-actions");
        if (!grid) return;
        grid.innerHTML = "";
        var editors = {};

        (Array.isArray(response.answers) ? response.answers : []).forEach(function (answer) {
            var fieldId = String(answer.fieldId || "");
            var field = fieldsById[fieldId];
            var type = field ? String(field.type || "") : String(answer.type || "");
            var row = document.createElement("div");
            row.className = "forms-answer";
            var label = document.createElement("span");
            label.textContent = String(answer.label || "");
            row.appendChild(label);

            if (!field || EDITABLE_TYPES.indexOf(type) === -1) {
                var ro = document.createElement("strong");
                ro.textContent = String(answer.displayValue || "—") + " (غیرقابل ویرایش)";
                row.appendChild(ro);
                grid.appendChild(row);
                return;
            }

            var options = Array.isArray(field.options) ? field.options : [];
            var current = answer.value;
            if (type === "single_choice" || type === "dropdown" || type === "linear_scale") {
                var sel = document.createElement("select");
                sel.className = "forms-input";
                var empty = document.createElement("option");
                empty.value = ""; empty.textContent = "—";
                sel.appendChild(empty);
                options.forEach(function (opt) {
                    var o = document.createElement("option");
                    o.value = String(opt.id || "");
                    o.textContent = String(opt.text || opt.id || "");
                    if (String(current || "") === String(opt.id || "")) o.selected = true;
                    sel.appendChild(o);
                });
                row.appendChild(sel);
                editors[fieldId] = { type: "single", el: sel };
            } else if (type === "multiple_choice") {
                var box = document.createElement("div");
                box.className = "forms-edit-checks";
                var cur = Array.isArray(current) ? current.map(String) : [];
                var inputs = [];
                options.forEach(function (opt) {
                    var lbl = document.createElement("label");
                    lbl.className = "forms-choice";
                    var inp = document.createElement("input");
                    inp.type = "checkbox";
                    inp.value = String(opt.id || "");
                    if (cur.indexOf(String(opt.id || "")) !== -1) inp.checked = true;
                    lbl.appendChild(inp);
                    var sp = document.createElement("span");
                    sp.textContent = String(opt.text || opt.id || "");
                    lbl.appendChild(sp);
                    box.appendChild(lbl);
                    inputs.push(inp);
                });
                row.appendChild(box);
                editors[fieldId] = { type: "multi", inputs: inputs };
            } else {
                var inp2 = document.createElement(type === "paragraph" ? "textarea" : "input");
                inp2.className = "forms-input";
                inp2.value = String(current == null ? "" : current);
                row.appendChild(inp2);
                editors[fieldId] = { type: "text", el: inp2 };
            }
            grid.appendChild(row);
        });

        if (actions) {
            actions.innerHTML = "";
            var saveBtn = document.createElement("button");
            saveBtn.type = "button";
            saveBtn.className = "forms-btn forms-btn--primary";
            saveBtn.textContent = "ذخیره تغییرات";
            saveBtn.addEventListener("click", function () {
                saveEditResponse(response.id, editors);
            });
            var cancelBtn = document.createElement("button");
            cancelBtn.type = "button";
            cancelBtn.className = "forms-btn";
            cancelBtn.textContent = "انصراف";
            cancelBtn.addEventListener("click", function () {
                loadResponses(form.id);
            });
            actions.appendChild(saveBtn);
            actions.appendChild(cancelBtn);
        }
    }

    async function saveEditResponse(responseId, editors) {
        var form = state.responsesForm;
        if (!form || !responseId) return;
        var answers = {};
        Object.keys(editors).forEach(function (fieldId) {
            var ed = editors[fieldId];
            if (ed.type === "single" || ed.type === "text") {
                answers[fieldId] = String(ed.el.value || "");
            } else if (ed.type === "multi") {
                answers[fieldId] = ed.inputs.filter(function (i) { return i.checked; }).map(function (i) { return String(i.value || ""); });
            }
        });
        try {
            var response = await apiPost("ownerEditResponse", {
                formId: String(form.id || ""),
                responseId: String(responseId),
                answers: JSON.stringify(answers)
            });
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                throw new Error((response && response.error) || "ویرایش پاسخ انجام نشد.");
            }
            showToast("پاسخ ویرایش شد.");
            await loadResponses(form.id);
        } catch (error) {
            showToast(error && error.message ? error.message : "ویرایش پاسخ انجام نشد.");
        }
    }

    async function deleteResponse(responseId) {
        var form = state.responsesForm;
        if (!form || !responseId) return;
        if (!window.confirm("این پاسخ حذف شود؟ این کار برگشت‌پذیر نیست.")) return;
        try {
            var response = await apiPost("deleteResponse", {
                formId: String(form.id || ""),
                responseId: String(responseId)
            });
            if (consumeUnauthorized(response)) return;
            if (!response || !response.success) {
                throw new Error((response && response.error) || "حذف پاسخ انجام نشد.");
            }
            showToast("پاسخ حذف شد.");
            await loadResponses(form.id);
        } catch (error) {
            showToast(error && error.message ? error.message : "حذف پاسخ انجام نشد.");
        }
    }

    async function loadSession() {
        // Deduplication: if onChange fires multiple times in rapid succession,
        // only the last call proceeds — earlier stale calls are discarded.
        var requestId = state.loadSessionRequestId + 1;
        state.loadSessionRequestId = requestId;

        var response = await apiGet("session");
        if (requestId !== state.loadSessionRequestId) {
            return; // Stale — a newer loadSession() call has taken over
        }
        if (!response || !response.success) {
            throw new Error((response && response.error) || "آماده‌سازی انجام نشد.");
        }
        state.viewer = response.viewer || null;
        state.activeCohort = response.activeCohort || (state.viewer && state.viewer.cohort) || null;
        state.canCreate = !!response.canCreate;
        syncAudienceOptions(audienceInput ? audienceInput.value : "link");
        if (viewerCopy) {
            viewerCopy.textContent = state.viewer
                ? ((state.canCreate ? "مدیریت فعال برای " : "فرم‌های فعال برای ") + String(state.viewer.name || "کاربر"))
                : "برای مدیریت فرم‌ها وارد شوید.";
        }
        if (!state.viewer) {
            if (loginLink) {
                loginLink.href = window.Dent1402Auth.loginUrl(formsHomePath);
            }
            showStage("login");
            return;
        }
        showStage("app");
        var endosimCard = document.getElementById("forms-endosim-card");
        if (endosimCard) {
            endosimCard.hidden = !(state.viewer && state.viewer.isOwner);
        }
        if (!state.canCreate) {
            panels.builder.hidden = true;
            tabs.forEach(function (tab) {
                if (tab.getAttribute("data-forms-tab") === "builder") {
                    tab.hidden = true;
                }
            });
            setTab("list");
        } else {
            tabs.forEach(function (tab) { tab.hidden = false; });
            panels.builder.hidden = false;
            setTab((new URLSearchParams(window.location.search).get("tab")) || "builder");
        }
        await loadForms();
    }

    tabs.forEach(function (tab) {
        tab.addEventListener("click", function () {
            setTab(tab.getAttribute("data-forms-tab") || "list");
        });
    });

    if (pageCohort === "prosthesis-1402") {
        var disTemplateOption = templateInput ? templateInput.querySelector('option[value="dis"]') : null;
        if (disTemplateOption) {
            disTemplateOption.remove();
        }
    }

    templateInput.addEventListener("change", function () {
        applyTemplate(templateInput.value);
    });

    kindInput.addEventListener("change", function () {
        if (kindInput.value === "poll") {
            resultVisibilityInput.value = "after-submit";
        }
        renderFieldEditor();
    });

    addFieldBtn.addEventListener("click", function () {
        state.fields.push(newField("short_text", "پرسش جدید"));
        renderFieldEditor();
    });

    resetBuilderBtn.addEventListener("click", function () {
        templateInput.value = "blank";
        applyTemplate("blank");
    });

    builder.addEventListener("submit", saveForm);
    reloadBtn.addEventListener("click", loadForms);
    closeResponsesBtn.addEventListener("click", function () {
        setTab("list");
    });

    applyTemplate("blank");
    showStage("boot");
    window.Dent1402Auth.onChange(function (detail) {
        if (detail && (detail.status === "session-restoring" || detail.status === "logging-out")) {
            showStage("boot");
            return;
        }
        loadSession().catch(function (error) {
            showStage("login");
            showToast(error && error.message ? error.message : "آماده‌سازی انجام نشد.");
        });
    });
})();
