(function () {
    "use strict";

    if (!window.Dent1402Auth) {
        return;
    }

    function $(id) {
        return document.getElementById(id);
    }

    var authApi = window.Dent1402Auth && typeof window.Dent1402Auth === "object" ? window.Dent1402Auth : null;
    var params = new URLSearchParams(window.location.search);
    var formId = String(params.get("form") || params.get("formId") || "").trim();
    var paymentOrderToken = String(params.get("paymentOrderToken") || "").trim();
    var pageCohort = authApi && typeof authApi.resolvePageCohort === "function"
        ? authApi.resolvePageCohort("formsCohort")
        : "main";
    var fillPath = authApi && typeof authApi.appendCohortQuery === "function"
        ? authApi.appendCohortQuery("/forms/fill/", pageCohort)
        : "/forms/fill/";

    var boot = $("fill-boot");
    var login = $("fill-login");
    var notFound = $("fill-not-found");
    var stage = $("fill-stage");
    var loginLink = $("fill-login-link");
    var refreshBtn = $("fill-refresh");
    var topTitle = $("fill-top-title");
    var topCopy = $("fill-top-copy");
    var kindChip = $("fill-kind");
    var titleEl = $("fill-title");
    var statusChip = $("fill-status");
    var descriptionEl = $("fill-description");
    var formEl = $("fill-form");
    var guestBox = $("fill-guest-box");
    var guestNameInput = $("fill-guest-name");
    var guestPhoneInput = $("fill-guest-phone");
    var fieldsRoot = $("fill-fields");
    var feedback = $("fill-feedback");
    var submitBtn = $("fill-submit");
    var resultsCard = $("fill-results-card");
    var resultsTotal = $("fill-results-total");
    var resultsNote = $("fill-results-note");
    var resultsList = $("fill-results-list");
    var copyLinkBtn = $("fill-copy-link");
    var shareLinkInput = $("fill-share-link");
    var toastEl = $("fill-toast");

    var state = {
        viewer: null,
        form: null,
        toastTimer: 0,
        loadRequestId: 0,
        pendingOfflineSubmissionId: ""
    };

    function parseApiResponse(response) {
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

    function apiGet(action, paramsObj) {
        var query = new URLSearchParams(Object.assign({ action: action, cohort: pageCohort }, paramsObj || {}));
        return fetch("/api/forms_api.php?" + query.toString(), {
            method: "GET",
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        }).then(parseApiResponse).catch(function () {
            return { success: false, httpStatus: 0, error: "ارتباط با سرور برقرار نشد." };
        });
    }

    function apiPost(action, payload) {
        var body = new URLSearchParams(Object.assign({ action: action, cohort: pageCohort }, payload || {}));
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

    function apiPostFormData(action, formData) {
        formData = formData || new FormData();
        formData.append("action", action);
        formData.append("cohort", pageCohort);
        return fetch("/api/forms_api.php", {
            method: "POST",
            credentials: "same-origin",
            headers: { Accept: "application/json" },
            body: formData
        }).then(parseApiResponse).catch(function () {
            return { success: false, httpStatus: 0, error: "ارتباط با سرور برقرار نشد." };
        });
    }

    function paymentApiGet(action, payload) {
        var query = new URLSearchParams(Object.assign({ action: action }, payload || {}));
        return fetch("/api/payments_api.php?" + query.toString(), {
            method: "GET",
            credentials: "same-origin",
            headers: { Accept: "application/json" }
        }).then(parseApiResponse).catch(function () {
            return { success: false, httpStatus: 0, error: "ارتباط با سرور پرداخت برقرار نشد." };
        });
    }

    function offlineApi() {
        return window.Dent1402Site
            && typeof window.Dent1402Site === "object"
            && window.Dent1402Site.offline
            && typeof window.Dent1402Site.offline === "object"
            ? window.Dent1402Site.offline
            : null;
    }

    function showStage(name) {
        boot.hidden = name !== "boot";
        login.hidden = name !== "login";
        notFound.hidden = name !== "not-found";
        stage.hidden = name !== "stage";
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
        feedback.textContent = text || "";
        feedback.className = "forms-feedback" + (kind ? " is-" + kind : "");
    }

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

    function normalizeDigits(value) {
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

    function money(value) {
        return (Math.max(0, Number(value) || 0)).toLocaleString("fa-IR") + " ریال";
    }

    function formReturnUrl() {
        var id = String((state.form && state.form.id) || formId || "").trim();
        var origin = window.location.origin || "";
        return origin + fillPath + "?form=" + encodeURIComponent(id);
    }

    function guestKey() {
        var key = "";
        try {
            key = localStorage.getItem("dent1402_forms_guest_key_" + pageCohort) || "";
            if (!key) {
                key = "guest-" + Date.now().toString(36) + "-" + Math.floor(Math.random() * 1000000).toString(36);
                localStorage.setItem("dent1402_forms_guest_key_" + pageCohort, key);
            }
        } catch (_error) {
            key = "guest-" + Date.now().toString(36) + "-" + Math.floor(Math.random() * 1000000).toString(36);
        }
        return key;
    }

    function formOfflineSubmitDedupeKey(payload) {
        var viewerStudentNumber = state.viewer && state.viewer.studentNumber ? String(state.viewer.studentNumber) : "";
        return [
            "forms-submit",
            pageCohort,
            String((payload && payload.formId) || formId || ""),
            viewerStudentNumber || String((payload && payload.guestKey) || guestKey() || "guest")
        ].join(":");
    }

    function syncPendingOfflineSubmission() {
        var offline = offlineApi();
        if (!offline || typeof offline.findQueuedEntry !== "function") {
            state.pendingOfflineSubmissionId = "";
            return;
        }
        var dedupeKey = formOfflineSubmitDedupeKey({
            formId: String((state.form && state.form.id) || formId || ""),
            guestKey: guestKey()
        });
        var queued = offline.findQueuedEntry(function (entry) {
            return entry && entry.kind === "form-submit" && entry.dedupeKey === dedupeKey;
        });
        state.pendingOfflineSubmissionId = queued ? String(queued.id || "") : "";
    }

    function queueOfflineFormSubmission(payload) {
        var offline = offlineApi();
        if (!offline || typeof offline.queueRequest !== "function") {
            return null;
        }
        return offline.queueRequest({
            kind: "form-submit",
            url: "/api/forms_api.php",
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                Accept: "application/json"
            },
            body: new URLSearchParams(Object.assign({ action: "submit", cohort: pageCohort }, payload || {})).toString(),
            dedupeKey: formOfflineSubmitDedupeKey(payload),
            meta: {
                formId: String((payload && payload.formId) || formId || ""),
                cohort: pageCohort
            }
        });
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

    function optionText(field, optionId) {
        var options = Array.isArray(field.options) ? field.options : [];
        for (var i = 0; i < options.length; i++) {
            if (String(options[i].id) === String(optionId)) {
                return String(options[i].text || optionId);
            }
        }
        return String(optionId);
    }

    function syncChoiceStyles(card) {
        Array.prototype.forEach.call(card.querySelectorAll(".forms-choice"), function (label) {
            var input = label.querySelector("input");
            label.classList.toggle("is-selected", !!(input && input.checked));
        });
    }

    function syncGridStyles(card) {
        Array.prototype.forEach.call(card.querySelectorAll(".forms-grid-choice"), function (label) {
            var input = label.querySelector("input");
            label.classList.toggle("is-selected", !!(input && input.checked));
        });
    }

    function paymentGatewayOptions() {
        var gateways = state.form && state.form.paymentGateways && Array.isArray(state.form.paymentGateways.gateways)
            ? state.form.paymentGateways.gateways
            : [];
        return gateways.filter(function (gateway) {
            return !!gateway.isEnabled;
        });
    }

    function renderQuestion(field) {
        var card = document.createElement("section");
        card.className = "forms-card forms-fill-question";
        card.dataset.fieldId = String(field.id || "");
        card.dataset.fieldType = String(field.type || "short_text");

        var head = document.createElement("div");
        head.className = "forms-card-head";
        var copy = document.createElement("div");
        var title = document.createElement("h2");
        title.textContent = String(field.label || "پرسش") + (field.required ? " *" : "");
        copy.appendChild(title);
        if (field.help) {
            var help = document.createElement("p");
            help.className = "forms-muted";
            help.textContent = String(field.help);
            copy.appendChild(help);
        }
        head.appendChild(copy);
        card.appendChild(head);

        var type = String(field.type || "short_text");
        if (type === "payment") {
            var payment = field.payment || {};
            var status = field.paymentStatus || {};
            var amount = Number((status && status.amount) || payment.amount || 0);
            var paid = !!status.paid;
            var paymentBox = document.createElement("div");
            paymentBox.className = "forms-payment-box" + (paid ? " is-paid" : "");
            paymentBox.innerHTML = [
                '<strong>' + escapeHtml(money(amount)) + "</strong>",
                paid ? '<p>پرداخت این سوال تایید شده است.</p>' : '<p>برای ثبت پاسخ فرم، ابتدا این مبلغ را پرداخت کنید. پس از پرداخت روی اتمام پرداخت بزنید و به همین صفحه برگردید تا پیام تایید را ببینید.</p>',
                !paid ? '<div class="forms-return-note"><span>نشانی بازگشت پس از پرداخت</span><code dir="ltr">' + escapeHtml(formReturnUrl()) + '</code><button class="forms-copy-mini" type="button" data-copy-receipt-value="' + escapeHtml(formReturnUrl()) + '" aria-label="کپی نشانی بازگشت">⧉</button></div>' : ""
            ].join("");
            card.appendChild(paymentBox);
            if (paid) {
                return card;
            }

            var phoneLabel = document.createElement("label");
            phoneLabel.className = "forms-field";
            phoneLabel.innerHTML = "<span>شماره موبایل پرداخت</span>";
            var phoneInput = document.createElement("input");
            phoneInput.type = "tel";
            phoneInput.inputMode = "tel";
            phoneInput.dir = "ltr";
            phoneInput.maxLength = 14;
            phoneInput.setAttribute("data-latin-digits", "true");
            phoneInput.dataset.paymentPhone = "1";
            phoneLabel.appendChild(phoneInput);
            card.appendChild(phoneLabel);

            var gatewayWrap = document.createElement("div");
            gatewayWrap.className = "forms-payment-gateways";
            var gateways = paymentGatewayOptions();
            if (!gateways.length) {
                var noGateway = document.createElement("p");
                noGateway.className = "forms-muted";
                noGateway.textContent = "درگاه فعالی برای پرداخت این سوال وجود ندارد.";
                gatewayWrap.appendChild(noGateway);
            } else {
                gateways.forEach(function (gateway, index) {
                    var label = document.createElement("label");
                    label.className = "forms-choice";
                    var input = document.createElement("input");
                    input.type = "radio";
                    input.name = "payment-gateway-" + String(field.id || "");
                    input.value = String(gateway.key || "");
                    if (gateway.isDefault || index === 0) input.checked = true;
                    label.appendChild(input);
                    var text = document.createElement("span");
                    text.textContent = String(gateway.label || "پرداخت آنلاین");
                    label.appendChild(text);
                    gatewayWrap.appendChild(label);
                });
            }
            card.appendChild(gatewayWrap);
            var button = document.createElement("button");
            button.className = "forms-btn forms-btn--primary";
            button.type = "button";
            button.dataset.payFieldId = String(field.id || "");
            button.textContent = "پرداخت";
            card.appendChild(button);
            var feedbackNode = document.createElement("div");
            feedbackNode.className = "forms-feedback";
            feedbackNode.dataset.paymentFeedback = "1";
            card.appendChild(feedbackNode);
            return card;
        }

        if (type === "receipt_payment") {
            var receipt = field.receiptPayment || {};
            var receiptStatus = field.receiptStatus || {};
            var receiptAmount = Number((receiptStatus && receiptStatus.amount) || receipt.amount || 0);
            var cardNumber = normalizeDigits(String(receipt.cardNumber || "")).replace(/\D+/g, "");
            var bankName = String(receipt.bankName || bankNameFromCard(cardNumber) || "");
            var uploaded = !!receiptStatus.uploaded;
            var fileName = String(receiptStatus.originalName || receiptStatus.fileName || "");
            var receiptBox = document.createElement("div");
            receiptBox.className = "forms-receipt-payment-box" + (uploaded ? " is-uploaded" : "");
            receiptBox.innerHTML = [
                '<div class="forms-receipt-pay-card">',
                '  <div class="forms-receipt-row"><span>مبلغ</span><strong>' + escapeHtml(money(receiptAmount)) + '</strong><button class="forms-copy-mini" type="button" data-copy-receipt-value="' + escapeHtml(String(receiptAmount || "")) + '" aria-label="کپی مبلغ">⧉</button></div>',
                '  <div class="forms-receipt-row"><span>شماره کارت</span><strong dir="ltr">' + escapeHtml(cardNumber || "—") + '</strong><button class="forms-copy-mini" type="button" data-copy-receipt-value="' + escapeHtml(cardNumber) + '" aria-label="کپی شماره کارت">⧉</button></div>',
                '  <div class="forms-receipt-row forms-receipt-row--muted"><span>صاحب کارت</span><strong>' + escapeHtml(receipt.cardholder || "—") + '</strong></div>',
                '  <div class="forms-receipt-bank">' + escapeHtml(bankName || "بانک صادرکننده پس از شناسایی شماره کارت نمایش داده می‌شود.") + "</div>",
                "</div>",
                uploaded ? '<div class="forms-receipt-status">رسید بارگذاری شده است' + (fileName ? (": " + escapeHtml(fileName)) : "") + "</div>" : '<div class="forms-receipt-status">برای تکمیل این بخش، تصویر یا PDF رسید را بارگذاری کنید.</div>'
            ].join("");
            card.appendChild(receiptBox);

            var uploadWrap = document.createElement("div");
            uploadWrap.className = "forms-receipt-upload";
            uploadWrap.innerHTML = [
                '<label class="forms-receipt-file">',
                '  <span>فایل رسید</span>',
                '  <input type="file" accept="image/*,application/pdf" data-receipt-file="1">',
                "</label>",
                '<button class="forms-btn forms-btn--primary" type="button" data-upload-receipt-field-id="' + escapeHtml(String(field.id || "")) + '">' + (uploaded ? "بارگذاری دوباره رسید" : "بارگذاری رسید") + "</button>",
                '<div class="forms-feedback" data-receipt-feedback="1" aria-live="polite"></div>'
            ].join("");
            card.appendChild(uploadWrap);
            return card;
        }

        if (["single_choice", "multiple_choice", "linear_scale"].indexOf(type) !== -1) {
            var inputType = type === "multiple_choice" ? "checkbox" : "radio";
            (Array.isArray(field.options) ? field.options : []).forEach(function (option) {
                var isFull = !!option.capacityFull;
                var cap = Number(option.capacity || 0);
                var remaining = typeof option.capacityRemaining === "number" ? option.capacityRemaining : (cap > 0 ? Math.max(0, cap - Number(option.capacityUsed || 0)) : -1);
                var showCap = cap > 0 && type !== "linear_scale";

                var label = document.createElement("label");
                var labelClass = "forms-choice";
                if (showCap) labelClass += " forms-choice--has-cap";
                if (isFull) labelClass += " is-full";
                label.className = labelClass;

                var input = document.createElement("input");
                input.type = inputType;
                input.name = "answer-" + String(field.id || "");
                input.value = String(option.id || "");
                if (isFull) input.disabled = true;
                input.addEventListener("change", function () {
                    syncChoiceStyles(card);
                });
                label.appendChild(input);

                var text = document.createElement("span");
                text.textContent = String(option.text || option.id || "");
                label.appendChild(text);

                if (showCap) {
                    var badge = document.createElement("span");
                    if (isFull) {
                        badge.className = "forms-cap-badge forms-cap-badge--full";
                        badge.textContent = "پر شد";
                    } else {
                        badge.className = "forms-cap-badge" + (remaining <= 3 ? " forms-cap-badge--low" : "");
                        badge.textContent = String(remaining) + " جا";
                    }
                    label.appendChild(badge);
                }

                card.appendChild(label);
            });
            if (type === "linear_scale" && field.scale) {
                var scaleHelp = document.createElement("p");
                scaleHelp.className = "forms-muted";
                scaleHelp.textContent = [field.scale.minLabel || "", field.scale.maxLabel || ""].filter(Boolean).join(" / ");
                if (scaleHelp.textContent) {
                    card.appendChild(scaleHelp);
                }
            }
            return card;
        }

        if (["multiple_choice_grid", "checkbox_grid"].indexOf(type) !== -1) {
            var gridWrap = document.createElement("div");
            gridWrap.className = "forms-grid-question-wrap";
            var table = document.createElement("table");
            table.className = "forms-grid-question";
            var thead = document.createElement("thead");
            var headRow = document.createElement("tr");
            headRow.appendChild(document.createElement("th"));
            (Array.isArray(field.options) ? field.options : []).forEach(function (option) {
                var th = document.createElement("th");
                th.textContent = String(option.text || option.id || "");
                headRow.appendChild(th);
            });
            thead.appendChild(headRow);
            table.appendChild(thead);

            var tbody = document.createElement("tbody");
            (Array.isArray(field.rows) ? field.rows : []).forEach(function (row) {
                var tr = document.createElement("tr");
                var rowHead = document.createElement("th");
                rowHead.scope = "row";
                rowHead.textContent = String(row.text || row.id || "");
                tr.appendChild(rowHead);
                (Array.isArray(field.options) ? field.options : []).forEach(function (option) {
                    var td = document.createElement("td");
                    var label = document.createElement("label");
                    label.className = "forms-grid-choice";
                    var input = document.createElement("input");
                    input.type = type === "checkbox_grid" ? "checkbox" : "radio";
                    input.name = "answer-" + String(field.id || "") + "-" + String(row.id || "");
                    input.value = String(option.id || "");
                    input.dataset.gridInput = "1";
                    input.dataset.rowId = String(row.id || "");
                    input.addEventListener("change", function () {
                        syncGridStyles(card);
                    });
                    var text = document.createElement("span");
                    text.textContent = String(option.text || option.id || "");
                    label.appendChild(input);
                    label.appendChild(text);
                    td.appendChild(label);
                    tr.appendChild(td);
                });
                tbody.appendChild(tr);
            });
            table.appendChild(tbody);
            gridWrap.appendChild(table);
            card.appendChild(gridWrap);
            return card;
        }

        if (type === "dropdown") {
            var selectWrap = document.createElement("label");
            selectWrap.className = "forms-field";
            var select = document.createElement("select");
            select.dataset.answerInput = "1";
            var empty = document.createElement("option");
            empty.value = "";
            empty.textContent = "انتخاب کنید";
            select.appendChild(empty);
            (Array.isArray(field.options) ? field.options : []).forEach(function (option) {
                var opt = document.createElement("option");
                opt.value = String(option.id || "");
                opt.textContent = String(option.text || option.id || "");
                select.appendChild(opt);
            });
            selectWrap.appendChild(select);
            card.appendChild(selectWrap);
            return card;
        }

        var labelWrap = document.createElement("label");
        labelWrap.className = "forms-field";
        var input;
        if (type === "paragraph") {
            input = document.createElement("textarea");
            input.rows = 5;
        } else {
            input = document.createElement("input");
            input.type = type === "email" ? "email"
                : type === "phone" ? "tel"
                    : type === "number" ? "number"
                        : type === "date" ? "date"
                            : type === "time" ? "time"
                                : type === "url" ? "url"
                                    : "text";
            if (type === "phone" || type === "number") {
                input.dir = "ltr";
                input.setAttribute("data-latin-digits", "true");
            }
        }
        input.dataset.answerInput = "1";
        input.maxLength = type === "paragraph" ? 4000 : 700;
        labelWrap.appendChild(input);
        card.appendChild(labelWrap);
        return card;
    }

    function renderResults(form) {
        var results = form && form.results ? form.results : null;
        if (!form || form.kind !== "poll" || !results) {
            resultsCard.hidden = true;
            return;
        }

        resultsCard.hidden = false;
        resultsList.innerHTML = "";
        if (!results.visible) {
            resultsTotal.textContent = "مخفی";
            resultsNote.textContent = String(results.hiddenReason || "نتایج فعلاً مخفی است.");
            return;
        }

        var total = Number(results.totalResponses || 0);
        resultsTotal.textContent = total.toLocaleString("fa-IR") + " پاسخ";
        resultsNote.textContent = "";
        var fragment = document.createDocumentFragment();
        (Array.isArray(results.options) ? results.options : []).forEach(function (option) {
            var item = document.createElement("div");
            item.className = "forms-results-item";
            var head = document.createElement("div");
            head.className = "forms-results-item__head";
            var title = document.createElement("strong");
            title.textContent = String(option.text || "");
            head.appendChild(title);
            var meta = document.createElement("span");
            meta.textContent = Number(option.count || 0).toLocaleString("fa-IR") + " • " + Number(option.percent || 0).toLocaleString("fa-IR") + "%";
            head.appendChild(meta);
            item.appendChild(head);
            var bar = document.createElement("div");
            bar.className = "forms-result-bar";
            var fill = document.createElement("i");
            fill.style.width = Math.max(0, Math.min(100, Number(option.percent || 0))) + "%";
            bar.appendChild(fill);
            item.appendChild(bar);
            fragment.appendChild(item);
        });
        resultsList.appendChild(fragment);
    }

    function renderForm(payload) {
        var form = payload.form || null;
        state.form = form;
        state.viewer = payload.viewer || null;
        syncPendingOfflineSubmission();
        if (!form) return;

        topTitle.textContent = String(form.title || "فرم");
        topCopy.textContent = String(form.kindLabel || "فرم") + " • " + String(form.statusLabel || "");
        kindChip.textContent = String(form.kindLabel || "فرم");
        titleEl.textContent = String(form.title || "فرم");
        statusChip.textContent = String(form.statusLabel || "—");
        descriptionEl.textContent = String(form.description || "");
        descriptionEl.hidden = !String(form.description || "").trim();
        shareLinkInput.value = String(form.shareUrl || window.location.href);

        var isGuest = !state.viewer;
        var settings = form.settings || {};
        guestBox.hidden = !isGuest;
        guestNameInput.required = isGuest && settings.collectGuestName !== false;
        guestPhoneInput.required = isGuest && !!settings.collectGuestPhone;
        guestNameInput.closest(".forms-field").hidden = !(isGuest && settings.collectGuestName !== false);
        guestPhoneInput.closest(".forms-field").hidden = !(isGuest && !!settings.collectGuestPhone);

        fieldsRoot.innerHTML = "";
        var fragment = document.createDocumentFragment();
        (Array.isArray(form.fields) ? form.fields : []).forEach(function (field) {
            fragment.appendChild(renderQuestion(field));
        });
        fieldsRoot.appendChild(fragment);

        submitBtn.disabled = !(form.permissions && form.permissions.canSubmit) || !!state.pendingOfflineSubmissionId;
        if (submitBtn.disabled) {
            if (state.pendingOfflineSubmissionId) {
                setFeedback("اتصال قطع است و پاسخ این فرم در صف آفلاین مانده است. بعد از برگشت اینترنت، خودکار ارسال می‌شود.", "");
            } else if (form.permissions && form.permissions.alreadySubmitted) {
                setFeedback("پاسخ این فرم قبلاً توسط شما ثبت شده است.", "success");
            } else {
                setFeedback("شما مجاز به پاسخ‌دهی به این فرم نیستید.", "error");
            }
        } else {
            setFeedback("", "");
        }

        // Edit mode: when the form allows editing and the viewer already submitted,
        // restore their previous answers so they can review and change them.
        if (settings.allowEditResponse && form.existingResponse && form.existingResponse.answers) {
            prefillAnswers(form.existingResponse.answers);
            if (!submitBtn.disabled) {
                setFeedback("پاسخ قبلی شما بارگذاری شد. می‌توانید آن را ویرایش و دوباره ثبت کنید.", "");
            }
        }

        renderResults(form);
        showStage("stage");
    }

    function prefillAnswers(answers) {
        if (!answers || typeof answers !== "object" || !state.form) {
            return;
        }
        (Array.isArray(state.form.fields) ? state.form.fields : []).forEach(function (field) {
            var fieldId = String(field.id || "");
            var type = String(field.type || "short_text");
            if (type === "payment" || type === "receipt_payment") {
                return;
            }
            if (!Object.prototype.hasOwnProperty.call(answers, fieldId)) {
                return;
            }
            var answer = answers[fieldId];
            var card = fieldsRoot.querySelector('[data-field-id="' + fieldId.replace(/"/g, "") + '"]');
            if (!card) {
                return;
            }

            if (type === "multiple_choice") {
                var values = Array.isArray(answer) ? answer.map(String) : [];
                Array.prototype.forEach.call(card.querySelectorAll("input[type=checkbox]"), function (input) {
                    if (values.indexOf(String(input.value || "")) !== -1) {
                        input.checked = true;
                    }
                });
            } else if (type === "single_choice" || type === "linear_scale") {
                var single = String(answer == null ? "" : answer);
                Array.prototype.forEach.call(card.querySelectorAll("input[type=radio]"), function (input) {
                    if (String(input.value || "") === single) {
                        input.checked = true;
                    }
                });
            } else if (type === "dropdown") {
                var select = card.querySelector("select[data-answer-input]");
                if (select) {
                    select.value = String(answer == null ? "" : answer);
                }
            } else if (type === "multiple_choice_grid" || type === "checkbox_grid") {
                var gridAnswer = (answer && typeof answer === "object") ? answer : {};
                Array.prototype.forEach.call(card.querySelectorAll("input[data-grid-input]"), function (input) {
                    var rowId = String(input.dataset.rowId || "");
                    var optionValue = String(input.value || "");
                    var rowAnswer = gridAnswer[rowId];
                    if (Array.isArray(rowAnswer)) {
                        if (rowAnswer.map(String).indexOf(optionValue) !== -1) {
                            input.checked = true;
                        }
                    } else if (rowAnswer != null && String(rowAnswer) === optionValue) {
                        input.checked = true;
                    }
                });
            } else {
                var inputEl = card.querySelector("[data-answer-input]");
                if (inputEl) {
                    inputEl.value = String(answer == null ? "" : answer);
                }
            }

            syncChoiceStyles(card);
            syncGridStyles(card);
        });
    }

    function collectAnswers() {
        var answers = {};
        if (!state.form) return answers;
        (Array.isArray(state.form.fields) ? state.form.fields : []).forEach(function (field) {
            var fieldId = String(field.id || "");
            var type = String(field.type || "short_text");
            var card = fieldsRoot.querySelector('[data-field-id="' + fieldId.replace(/"/g, "") + '"]');
            if (!card) return;
            if (type === "payment" || type === "receipt_payment") {
                return;
            }
            if (type === "multiple_choice") {
                answers[fieldId] = Array.prototype.slice.call(card.querySelectorAll("input:checked")).map(function (input) {
                    return String(input.value || "");
                });
                return;
            }
            if (["single_choice", "linear_scale"].indexOf(type) !== -1) {
                var selected = card.querySelector("input:checked");
                answers[fieldId] = selected ? String(selected.value || "") : "";
                return;
            }
            if (type === "dropdown") {
                var select = card.querySelector("select[data-answer-input]");
                answers[fieldId] = select ? String(select.value || "") : "";
                return;
            }
            if (["multiple_choice_grid", "checkbox_grid"].indexOf(type) !== -1) {
                var gridAnswer = {};
                (Array.isArray(field.rows) ? field.rows : []).forEach(function (row) {
                    gridAnswer[String(row.id || "")] = type === "checkbox_grid" ? [] : "";
                });
                Array.prototype.forEach.call(card.querySelectorAll("input[data-grid-input]:checked"), function (input) {
                    var rowId = String(input.dataset.rowId || "");
                    if (!rowId) return;
                    if (type === "checkbox_grid") {
                        if (!Array.isArray(gridAnswer[rowId])) gridAnswer[rowId] = [];
                        gridAnswer[rowId].push(String(input.value || ""));
                    } else {
                        gridAnswer[rowId] = String(input.value || "");
                    }
                });
                answers[fieldId] = gridAnswer;
                return;
            }
            var input = card.querySelector("[data-answer-input]");
            var value = input ? String(input.value || "") : "";
            if (type === "phone" || type === "number") {
                value = normalizeDigits(value);
            }
            answers[fieldId] = value;
        });
        return answers;
    }

    async function loadForm() {
        if (!formId) {
            showStage("not-found");
            return;
        }
        var requestId = state.loadRequestId + 1;
        state.loadRequestId = requestId;
        refreshBtn.disabled = true;
        try {
            var response = await apiGet("get", { form: formId, guestKey: guestKey() });
            if (requestId !== state.loadRequestId) {
                return;
            }
            if (response && response.httpStatus === 401) {
                // A single 401 can be a transient session-lock/race hiccup. Re-verify
                // once before showing the login stage so the user is not bounced out.
                var authApi = window.Dent1402Auth;
                if (!state.authRechecked && authApi && typeof authApi.verifySession === "function") {
                    state.authRechecked = true;
                    authApi.verifySession().then(function (loggedIn) {
                        if (loggedIn === false) {
                            loginLink.href = authApi.loginUrl(window.location.pathname + window.location.search);
                            showStage("login");
                        } else {
                            loadForm();
                        }
                    });
                    return;
                }
                loginLink.href = window.Dent1402Auth.loginUrl(window.location.pathname + window.location.search);
                showStage("login");
                return;
            }
            if (response && response.httpStatus === 404) {
                showStage("not-found");
                return;
            }
            if (!response || !response.success || !response.form) {
                throw new Error((response && response.error) || "بارگذاری فرم انجام نشد.");
            }
            renderForm(response);
            if (paymentOrderToken) {
                var resultResponse = await paymentApiGet("publicOrderResult", { orderToken: paymentOrderToken });
                if (requestId !== state.loadRequestId) {
                    return;
                }
                if (resultResponse && resultResponse.success && resultResponse.order) {
                    setFeedback(String(resultResponse.order.message || ""), resultResponse.order.status === "success" ? "success" : "error");
                }
            }
        } catch (error) {
            if (requestId !== state.loadRequestId) {
                return;
            }
            setFeedback(error && error.message ? error.message : "بارگذاری انجام نشد.", "error");
            showStage("stage");
        } finally {
            if (requestId === state.loadRequestId) {
                refreshBtn.disabled = false;
            }
        }
    }

    async function payFormField(button) {
        if (!state.form || !button) return;
        var fieldId = String(button.dataset.payFieldId || "");
        var card = button.closest(".forms-fill-question");
        var feedbackNode = card ? card.querySelector("[data-payment-feedback]") : null;
        var phoneInput = card ? card.querySelector("[data-payment-phone]") : null;
        var gatewayInput = card ? card.querySelector("input[name='payment-gateway-" + fieldId.replace(/"/g, "") + "']:checked") : null;
        var setPaymentFeedback = function (message, kind) {
            if (!feedbackNode) return;
            feedbackNode.textContent = message || "";
            feedbackNode.className = "forms-feedback" + (kind ? " is-" + kind : "");
        };
        button.disabled = true;
        setPaymentFeedback("در حال ایجاد پرداخت...", "");
        try {
            var response = await apiPost("createPayment", {
                formId: String(state.form.id || formId),
                fieldId: fieldId,
                guestKey: guestKey(),
                payerPhone: normalizeDigits(phoneInput ? phoneInput.value : ""),
                gateway: gatewayInput ? gatewayInput.value : ""
            });
            if (response && response.httpStatus === 401) {
                var authApi = window.Dent1402Auth;
                if (authApi && typeof authApi.verifySession === "function" && (await authApi.verifySession()) !== false) {
                    throw new Error("نشست به‌طور موقت پاسخ نداد. دوباره تلاش کنید.");
                }
                loginLink.href = window.Dent1402Auth.loginUrl(window.location.pathname + window.location.search);
                showStage("login");
                return;
            }
            if (response && response.alreadyPaid) {
                setPaymentFeedback("این پرداخت قبلا تایید شده است.", "success");
                await loadForm();
                return;
            }
            if (!response || !response.success || !response.redirectUrl) {
                throw new Error((response && response.error) || "ایجاد پرداخت انجام نشد.");
            }
            setPaymentFeedback("نشانی بازگشت ثبت شد؛ در حال انتقال به درگاه پرداخت...", "success");
            window.setTimeout(function () {
                window.location.href = response.redirectUrl;
            }, 500);
        } catch (error) {
            button.disabled = false;
            setPaymentFeedback(error && error.message ? error.message : "ایجاد پرداخت انجام نشد.", "error");
        }
    }

    async function uploadReceipt(button) {
        if (!state.form || !button) return;
        var fieldId = String(button.dataset.uploadReceiptFieldId || "");
        var card = button.closest(".forms-fill-question");
        var feedbackNode = card ? card.querySelector("[data-receipt-feedback]") : null;
        var fileInput = card ? card.querySelector("[data-receipt-file]") : null;
        var setReceiptFeedback = function (message, kind) {
            if (!feedbackNode) return;
            feedbackNode.textContent = message || "";
            feedbackNode.className = "forms-feedback" + (kind ? " is-" + kind : "");
        };
        if (!fileInput || !fileInput.files || !fileInput.files[0]) {
            setReceiptFeedback("فایل رسید را انتخاب کنید.", "error");
            return;
        }
        var body = new FormData();
        body.append("formId", String(state.form.id || formId));
        body.append("fieldId", fieldId);
        body.append("guestKey", guestKey());
        body.append("guestName", String(guestNameInput.value || "").trim());
        body.append("guestPhone", normalizeDigits(guestPhoneInput.value || ""));
        body.append("receipt", fileInput.files[0]);
        button.disabled = true;
        setReceiptFeedback("در حال بارگذاری رسید...", "");
        try {
            var response = await apiPostFormData("uploadReceipt", body);
            if (response && response.httpStatus === 401) {
                var authApi = window.Dent1402Auth;
                if (authApi && typeof authApi.verifySession === "function" && (await authApi.verifySession()) !== false) {
                    throw new Error("نشست به‌طور موقت پاسخ نداد. دوباره تلاش کنید.");
                }
                loginLink.href = window.Dent1402Auth.loginUrl(window.location.pathname + window.location.search);
                showStage("login");
                return;
            }
            if (!response || !response.success || !response.receipt) {
                throw new Error((response && response.error) || "آپلود رسید انجام نشد.");
            }
            (Array.isArray(state.form.fields) ? state.form.fields : []).forEach(function (field) {
                if (String(field.id || "") === fieldId) {
                    field.receiptStatus = Object.assign({}, response.receipt, {
                        amount: field.receiptPayment && field.receiptPayment.amount ? field.receiptPayment.amount : 0
                    });
                }
            });
            renderForm({ form: state.form, viewer: state.viewer });
            setFeedback(String(response.message || "رسید بارگذاری شد."), "success");
            showToast("رسید بارگذاری شد.");
        } catch (error) {
            button.disabled = false;
            setReceiptFeedback(error && error.message ? error.message : "آپلود رسید انجام نشد.", "error");
        }
    }

    async function submitForm(event) {
        event.preventDefault();
        if (!state.form || submitBtn.disabled) return;
        submitBtn.disabled = true;
        setFeedback("در حال ثبت پاسخ...", "");
        try {
            var payload = {
                formId: String(state.form.id || formId),
                answers: JSON.stringify(collectAnswers()),
                guestKey: guestKey(),
                guestName: String(guestNameInput.value || "").trim(),
                guestPhone: normalizeDigits(guestPhoneInput.value || "")
            };
            var response = await apiPost("submit", payload);
            if (response && response.httpStatus === 0) {
                var queuedEntry = queueOfflineFormSubmission(payload);
                if (!queuedEntry) {
                    throw new Error(response.error || "ثبت پاسخ انجام نشد.");
                }
                state.pendingOfflineSubmissionId = String(queuedEntry.id || "");
                setFeedback("اتصال قطع است. پاسخ فرم در صف آفلاین ذخیره شد و بعد از آنلاین شدن ارسال می‌شود.", "");
                showToast("پاسخ فرم در صف آفلاین ذخیره شد.");
                renderForm({ form: state.form, viewer: state.viewer });
                return;
            }
            if (response && response.httpStatus === 401) {
                var authApi = window.Dent1402Auth;
                if (authApi && typeof authApi.verifySession === "function" && (await authApi.verifySession()) !== false) {
                    throw new Error("نشست به‌طور موقت پاسخ نداد. دوباره تلاش کنید.");
                }
                loginLink.href = window.Dent1402Auth.loginUrl(window.location.pathname + window.location.search);
                showStage("login");
                return;
            }
            if (!response || !response.success || !response.form) {
                throw new Error((response && response.error) || "ثبت پاسخ انجام نشد.");
            }
            renderForm({ form: response.form, viewer: state.viewer });
            setFeedback(String(response.message || "پاسخ ثبت شد."), "success");
            showToast("پاسخ ثبت شد.");
        } catch (error) {
            setFeedback(error && error.message ? error.message : "ثبت پاسخ انجام نشد.", "error");
        } finally {
            if (state.form && state.form.permissions && state.form.permissions.canSubmit && !state.pendingOfflineSubmissionId) {
                submitBtn.disabled = false;
            }
        }
    }

    copyLinkBtn.addEventListener("click", function () {
        copyText(String(shareLinkInput.value || "")).then(function () {
            showToast("لینک کپی شد.");
        }).catch(function () {
            showToast("کپی لینک انجام نشد.");
        });
    });

    refreshBtn.addEventListener("click", loadForm);
    fieldsRoot.addEventListener("click", function (event) {
        var copyButton = event.target && event.target.closest("[data-copy-receipt-value]");
        if (copyButton) {
            copyText(String(copyButton.getAttribute("data-copy-receipt-value") || "")).then(function () {
                showToast("کپی شد.");
            }).catch(function () {
                showToast("کپی انجام نشد.");
            });
            return;
        }
        var uploadButton = event.target && event.target.closest("[data-upload-receipt-field-id]");
        if (uploadButton) {
            uploadReceipt(uploadButton);
            return;
        }
        var button = event.target && event.target.closest("[data-pay-field-id]");
        if (button) {
            payFormField(button);
        }
    });
    window.addEventListener("dent1402:offline-queue-change", function () {
        var previousPending = !!state.pendingOfflineSubmissionId;
        syncPendingOfflineSubmission();
        if (state.form && previousPending !== !!state.pendingOfflineSubmissionId) {
            renderForm({ form: state.form, viewer: state.viewer });
        }
    });
    window.addEventListener("dent1402:offline-queue-success", function (event) {
        var detail = event && event.detail ? event.detail : {};
        var entry = detail.entry || null;
        if (!entry || entry.kind !== "form-submit") {
            return;
        }
        if (String(entry.meta && entry.meta.formId || "") !== String((state.form && state.form.id) || formId || "")) {
            return;
        }
        state.pendingOfflineSubmissionId = "";
        setFeedback("پاسخ فرم از صف آفلاین به سرور رسید.", "success");
        showToast("پاسخ فرم ارسال شد.");
        loadForm();
    });
    formEl.addEventListener("submit", submitForm);

    showStage("boot");
    var lastAuthStatus = null;
    window.Dent1402Auth.onChange(function (detail) {
        var status = detail && detail.status ? String(detail.status) : null;
        if (status === "session-restoring" || status === "logging-out") {
            showStage("boot");
            lastAuthStatus = null; // Reset so the next stable state always triggers a load
            return;
        }
        // If the form is already rendered and the auth status hasn't changed,
        // skip the reload to avoid wiping the user's partially-filled answers.
        if (state.form !== null && status !== null && status === lastAuthStatus) {
            return;
        }
        lastAuthStatus = status;
        loadForm();
    });
})();
