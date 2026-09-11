(function () {
    "use strict";

    var ITEM_LABELS = {
        announcement: "اطلاعیه",
        event: "رویداد",
        class_change: "تغییر کلاس",
        deadline: "مهلت",
        task: "تکلیف",
        requirement: "مورد الزامی",
        exam: "امتحان",
        critical_notice: "اطلاعیه مهم",
        service_reminder: "یادآوری"
    };
    var STATE_LABELS = {
        draft: "پیش‌نویس",
        scheduled: "زمان‌بندی‌شده",
        active: "فعال",
        completed: "انجام‌شده",
        cancelled: "لغوشده",
        canceled: "لغوشده",
        archived: "بایگانی‌شده",
        pending: "در انتظار",
        submitted: "ارسال‌شده",
        needs_revision: "نیازمند اصلاح",
        waived: "نیاز نیست"
    };

    function $(id) { return document.getElementById(id); }
    function text(value) { return String(value == null ? "" : value); }
    function esc(value) {
        return text(value).replace(/[&<>"']/g, function (char) {
            return ({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"})[char];
        });
    }
    function itemLabel(value) { return ITEM_LABELS[text(value)] || "مورد کلاس"; }
    function stateLabel(value) { return STATE_LABELS[text(value)] || ""; }
    function commandId(prefix) {
        var value = "";
        if (globalThis.crypto && typeof globalThis.crypto.randomUUID === "function") {
            value = globalThis.crypto.randomUUID().replace(/-/g, "");
        } else {
            value = Date.now().toString(36) + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2);
        }
        return prefix + "_" + value.slice(0, 36);
    }
    function persianDate(value) {
        var raw = text(value).trim();
        if (!raw) return "";
        var date = new Date(raw);
        if (!Number.isFinite(date.getTime())) return raw;
        try {
            return date.toLocaleString("fa-IR-u-ca-persian", {year:"numeric",month:"2-digit",day:"2-digit",hour:"2-digit",minute:"2-digit",hour12:false});
        } catch (_error) {
            return raw;
        }
    }

    function productizeText(raw) {
        var value = text(raw);
        var replacements = [
            [/ClassOps Stage 2/gi, "امور کلاس"],
            [/ClassOps/gi, "امور کلاس"],
            [/canonical/gi, "اصلی"],
            [/structured draft/gi, "پیش‌نویس ساختاریافته"],
            [/Trusted preview/gi, "پیش‌نمایش"],
            [/Runtime capabilities/gi, "وضعیت ارسال و سرویس‌ها"],
            [/Canonical items/gi, "موارد کلاس"],
            [/Selected revision/gi, "مورد انتخاب‌شده"],
            [/Deterministic digests/gi, "خلاصه‌ها"],
            [/ساخت آیتم جدید/g, "ثبت مورد جدید"],
            [/ثبت revision جدید برای آیتم موجود/gi, "ثبت ویرایش جدید"],
            [/revision\s*\d+/gi, ""],
            [/revision/gi, "ویرایش"],
            [/audience hash/gi, ""],
            [/resolution hash/gi, ""],
            [/هشدار audience/gi, "هشدار مخاطبان"],
            [/snapshot مخاطب/gi, "فهرست مخاطبان"],
            [/همان hash/gi, "همان مخاطبان"],
            [/commit انجام می‌شود/gi, "ثبت انجام می‌شود"],
            [/mutation\/send/gi, "ثبت یا ارسال"],
            [/mutation/gi, "ثبت"],
            [/send/gi, "ارسال"],
            [/side effect/gi, "نتیجه"],
            [/zero mutation until confirm/gi, "تا قبل از تأیید چیزی ثبت یا ارسال نمی‌شود"],
            [/resolve\s*→\s*preview\s*→\s*confirm/gi, "پیش‌نمایش → تأیید"],
            [/unknown\/blocked\s*≠\s*success/gi, "وضعیت واقعی هر مسیر نمایش داده می‌شود"],
            [/Foundation/gi, "پایه"],
            [/Audience/gi, "مخاطبان"],
            [/Delivery/gi, "ارسال"],
            [/Tasks\s*\/\s*Requirements/gi, "تکالیف و الزامات"],
            [/Exam\s*\/\s*ACK/gi, "امتحان و تأیید"],
            [/Scheduler/gi, "زمان‌بندی"],
            [/Digest/gi, "خلاصه‌ها"],
            [/Website/gi, "سایت"],
            [/Telegram/gi, "تلگرام"],
            [/Bale/gi, "بله"],
            [/\bAI\b/gi, "هوش مصنوعی"],
            [/private_users/gi, "پیام خصوصی"],
            [/class_group/gi, "گروه کلاس"],
            [/information_channel/gi, "کانال اطلاع‌رسانی"],
            [/available/gi, "فعال"],
            [/unavailable/gi, "غیرفعال"],
            [/configured/gi, "فعال"],
            [/unconfigured/gi, "غیرفعال"],
            [/reminder-only/gi, "فقط یادآوری"],
            [/unknown/gi, "نامشخص"],
            [/blocked/gi, "در دسترس نیست"],
            [/scheduled/gi, "زمان‌بندی‌شده"],
            [/completed/gi, "انجام‌شده"],
            [/active/gi, "فعال"],
            [/draft/gi, "پیش‌نویس"],
            [/ACK/gi, "تأیید"],
            [/Saba/gi, "صبا"],
            [/\btype\b/gi, "نوع"],
            [/\btitle\b/gi, "عنوان"],
            [/\bdescription\b/gi, "توضیحات"],
            [/\blocation\b/gi, "مکان"],
            [/\bimportance\b/gi, "اهمیت"],
            [/\bcourse\b/gi, "درس"],
            [/timing clue/gi, "نشانه زمانی"],
            [/audience clue/gi, "نشانه مخاطب"],
            [/\bdelivery\b/gi, "ارسال"],
            [/\bmodel\b/gi, "مدل"]
        ];
        replacements.forEach(function (entry) { value = value.replace(entry[0], entry[1]); });
        return value.replace(/\s+·\s+·/g, " · ").replace(/\s{2,}/g, " ").trim();
    }

    function localizeDynamicNode(node) {
        if (!node || node.dataset.productRaw === node.textContent) return;
        var before = node.textContent || "";
        var after = productizeText(before);
        if (after && after !== before) node.textContent = after;
        node.dataset.productRaw = node.textContent || "";
    }

    function localizeTextTree(root) {
        if (!root) return;
        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        var nodes = [];
        var current;
        while ((current = walker.nextNode())) nodes.push(current);
        nodes.forEach(function (node) {
            var before = node.nodeValue || "";
            var after = productizeText(before);
            if (after && after !== before) node.nodeValue = after;
        });
    }

    function installCopyObserver() {
        var scalarIds = [
            "classops-access-state", "classops-operation-state", "classops-ai-state",
            "classops-selected-meta", "classops-item-state", "classops-selected-output",
            "classops-digest-output", "classops-stage2-preview", "classops-ai-preview",
            "classops-confirm-text"
        ];
        var scalarNodes = scalarIds.map($).filter(Boolean);
        var treeNodes = [$("classops-capabilities"), $("classops-item-list")].filter(Boolean);
        var observer = new MutationObserver(function () {
            scalarNodes.forEach(localizeDynamicNode);
            treeNodes.forEach(localizeTextTree);
        });
        scalarNodes.forEach(function (node) {
            localizeDynamicNode(node);
            observer.observe(node, {childList:true,subtree:true,characterData:true});
        });
        treeNodes.forEach(function (node) {
            localizeTextTree(node);
            observer.observe(node, {childList:true,subtree:true,characterData:true});
        });
    }

    function installTypeAwareComposer() {
        var type = $("classops-type");
        if (!type) return;
        var course = $("classops-course-title");
        var starts = $("classops-starts-at");
        var due = $("classops-due-at");
        var location = $("classops-location");
        var ack = $("classops-require-ack");
        var saba = $("classops-saba-note");
        function parent(node) { return node && node.closest("label"); }
        function render() {
            var kind = type.value;
            var showCourse = ["event","class_change","deadline","task","requirement","exam"].indexOf(kind) >= 0;
            var showStart = ["event","class_change","exam"].indexOf(kind) >= 0;
            var showDue = ["deadline","task","requirement","exam","service_reminder"].indexOf(kind) >= 0;
            var showLocation = ["event","class_change","exam"].indexOf(kind) >= 0;
            if (parent(course)) parent(course).hidden = !showCourse;
            if (parent(starts)) parent(starts).hidden = !showStart;
            if (parent(due)) parent(due).hidden = !showDue;
            if (parent(location)) parent(location).hidden = !showLocation;
            if (ack) {
                ack.disabled = kind !== "critical_notice";
                ack.checked = kind === "critical_notice";
                if (parent(ack)) parent(ack).hidden = kind !== "critical_notice";
            }
            if (saba) saba.hidden = kind !== "service_reminder";
        }
        type.addEventListener("change", render);
        render();
    }

    function studentCard(item) {
        var kind = itemLabel(item.type);
        var status = stateLabel(item.status);
        var timing = item.timing || {};
        var when = persianDate(timing.dueAt || timing.startsAt || "");
        var task = item.task || null;
        var taskState = task ? stateLabel(task.state) : "";
        var ack = item.ack || null;
        var marker = item.type === "critical_notice" && ack && !ack.acked ? "🔴" : item.type === "exam" ? "📝" : item.type === "task" || item.type === "requirement" ? "✅" : "📌";
        return '<button class="class-operations-student-item" type="button" data-student-item="' + esc(item.id) + '">' +
            '<span class="class-operations-student-item__mark">' + marker + '</span>' +
            '<span class="class-operations-student-item__copy"><strong>' + esc(item.title || kind) + '</strong>' +
            '<small>' + esc(kind + (status ? " · " + status : "") + (when ? " · " + when : "") + (taskState ? " · " + taskState : "")) + '</small></span>' +
            '<span aria-hidden="true">‹</span></button>';
    }

    function studentDetail(item) {
        var timing = item.timing || {};
        var bits = [];
        if (timing.startsAt) bits.push("شروع: " + persianDate(timing.startsAt));
        if (timing.dueAt) bits.push("مهلت: " + persianDate(timing.dueAt));
        if (item.location) bits.push("مکان: " + text(item.location));
        var html = '<div class="class-operations-student-detail__head"><span>' + esc(itemLabel(item.type)) + '</span><h3>' + esc(item.title || itemLabel(item.type)) + '</h3></div>';
        if (item.description) html += '<p>' + esc(item.description) + '</p>';
        if (bits.length) html += '<div class="class-operations-student-detail__meta">' + bits.map(function (bit) { return '<span>' + esc(bit) + '</span>'; }).join("") + '</div>';
        if (item.task) {
            html += '<p class="class-operations-product-note">وضعیت من: <b>' + esc(stateLabel(item.task.state) || "در انتظار") + '</b></p>';
            if (["completed","waived"].indexOf(item.task.state) < 0) {
                html += '<div class="classops-action-row"><button class="classops-button classops-button--secondary" type="button" data-student-task="submitted">ارسال شد</button><button class="classops-button classops-button--primary" type="button" data-student-task="completed">انجام شد</button></div>';
            }
        }
        if (item.ack && !item.ack.acked) {
            html += '<div class="class-operations-product-alert">این اطلاعیه نیازمند تأیید تو است.</div><button class="classops-button classops-button--primary" type="button" data-student-ack>این اطلاعیه را دیدم و تأیید می‌کنم</button>';
        } else if (item.ack && item.ack.acked) {
            html += '<p class="class-operations-product-ok">✅ تأیید این اطلاعیه ثبت شده است.</p>';
        }
        if (item.service) {
            var serviceState = item.service.state || {};
            html += '<p class="class-operations-product-note">وضعیت یادآوری: <b>' + esc(stateLabel(serviceState.state) || "در انتظار") + '</b><br><small>این وضعیت فقط در سایت ثبت می‌شود و انجام واقعی در صبا را تأیید نمی‌کند.</small></p>';
            if (["completed","waived"].indexOf(serviceState.state) < 0) {
                html += '<div class="classops-action-row"><button class="classops-button classops-button--primary" type="button" data-student-service="completed">انجام شد</button><button class="classops-button classops-button--secondary" type="button" data-student-service="waived">نیاز نیست</button></div>';
            }
        }
        return html;
    }

    async function mountStudentSurface() {
        var root = $("classops-student-center");
        var list = $("classops-student-list");
        var detail = $("classops-student-detail");
        var state = $("classops-student-state");
        if (!root || !list || !detail || !state || !window.ClassOpsOps) return false;
        var client = new window.ClassOpsOps.ClassOpsClient();
        var currentItem = null;

        async function loadList() {
            state.textContent = "در حال آماده‌سازی امور کلاس…";
            try {
                var response = await client.request("student-list");
                var data = response && response.data || {};
                var items = Array.isArray(data.items) ? data.items : [];
                root.hidden = false;
                var ownerCenter = $("classops-owner-center");
                if (ownerCenter) ownerCenter.hidden = true;
                var access = $("classops-access-state");
                if (access) access.hidden = true;
                state.textContent = items.length ? items.length.toLocaleString("fa-IR") + " مورد برای حساب شما" : "فعلاً موردی برای شما ثبت نشده است.";
                list.innerHTML = items.length ? items.map(studentCard).join("") : '<p class="classops-empty">وقتی اطلاعیه، تکلیف یا برنامه‌ای برای شما ثبت شود، اینجا نمایش داده می‌شود.</p>';
                return true;
            } catch (error) {
                if (Number(error && error.status) === 403) return false;
                root.hidden = false;
                state.textContent = "این بخش فعلاً در دسترس نیست. چند لحظه بعد دوباره امتحان کن.";
                list.innerHTML = "";
                return true;
            }
        }

        async function openItem(id) {
            detail.hidden = false;
            detail.innerHTML = '<p class="classops-state">در حال دریافت جزئیات…</p>';
            try {
                var response = await client.request("student-get", {query:{id:id}});
                currentItem = response.item || {};
                detail.innerHTML = studentDetail(currentItem);
                detail.scrollIntoView({behavior:"smooth",block:"nearest"});
            } catch (_error) {
                detail.innerHTML = '<p class="classops-state">جزئیات این مورد فعلاً در دسترس نیست.</p>';
            }
        }

        list.addEventListener("click", function (event) {
            var target = event.target.closest("[data-student-item]");
            if (target) openItem(target.getAttribute("data-student-item"));
        });

        detail.addEventListener("click", async function (event) {
            if (!currentItem || !currentItem.id) return;
            var task = event.target.closest("[data-student-task]");
            var ack = event.target.closest("[data-student-ack]");
            var service = event.target.closest("[data-student-service]");
            try {
                if (task && currentItem.task) {
                    await client.request("student-task-transition", {method:"POST", body:{
                        id:currentItem.id,
                        expectedStateRevision:Number(currentItem.task.stateRevision || 0),
                        target:task.getAttribute("data-student-task"),
                        commandId:commandId("webtask"),
                        reason:"student website action"
                    }});
                } else if (ack && currentItem.ack) {
                    await client.request("student-ack", {method:"POST", body:{
                        id:currentItem.id,
                        expectedRevision:Number(currentItem.revision || 0),
                        idempotencyKey:commandId("weback")
                    }});
                } else if (service && currentItem.service) {
                    var serviceState = currentItem.service.state || {};
                    await client.request("student-service-transition", {method:"POST", body:{
                        id:currentItem.id,
                        expectedStateRevision:Number(serviceState.stateRevision || 0),
                        target:service.getAttribute("data-student-service"),
                        commandId:commandId("webservice")
                    }});
                } else {
                    return;
                }
                await openItem(currentItem.id);
                await loadList();
            } catch (_error) {
                var notice = document.createElement("p");
                notice.className = "classops-state";
                notice.textContent = "این تغییر ثبت نشد. صفحه را تازه کن و دوباره امتحان کن.";
                detail.prepend(notice);
            }
        });

        ["tomorrow", "weekly"].forEach(function (kind) {
            var buttonNode = $("classops-student-" + kind);
            if (!buttonNode) return;
            buttonNode.addEventListener("click", async function () {
                var output = $("classops-student-digest");
                if (!output) return;
                output.hidden = false;
                output.textContent = "در حال آماده‌سازی…";
                try {
                    var result = await client.request(kind === "tomorrow" ? "tomorrow-summary" : "weekly-digest");
                    var digest = result && result.digest || {};
                    output.textContent = text(digest.plainText || "موردی ثبت نشده است.");
                } catch (_error) {
                    output.textContent = "خلاصه فعلاً در دسترس نیست.";
                }
            });
        });

        return loadList();
    }

    function installStudentStyles() {
        var style = document.createElement("style");
        style.textContent = ".class-operations-student-list{display:grid;gap:10px}.class-operations-student-item{width:100%;display:flex;align-items:center;gap:11px;text-align:right;border:1px solid var(--border-color,#dbe2ea);border-radius:16px;background:var(--surface-color,#fff);padding:12px 13px;color:inherit;font:inherit;cursor:pointer}.class-operations-student-item__mark{font-size:1.2rem}.class-operations-student-item__copy{display:flex;flex:1;min-width:0;flex-direction:column;gap:3px}.class-operations-student-item__copy strong{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.class-operations-student-item__copy small{color:var(--text-muted,#64748b);line-height:1.55}.class-operations-student-detail{margin-top:14px;border-top:1px solid var(--border-color,#dbe2ea);padding-top:16px}.class-operations-student-detail__head span{color:var(--text-muted,#64748b);font-size:.82rem}.class-operations-student-detail__head h3{margin:4px 0 0}.class-operations-student-detail__meta{display:flex;flex-wrap:wrap;gap:7px;margin:12px 0}.class-operations-student-detail__meta span,.class-operations-product-note,.class-operations-product-alert,.class-operations-product-ok{border-radius:12px;padding:9px 11px;background:color-mix(in srgb,var(--text-color,#172033) 5%,transparent);line-height:1.7}.class-operations-product-alert{background:color-mix(in srgb,#dc2626 8%,transparent);color:#991b1b}.class-operations-product-ok{background:color-mix(in srgb,#059669 9%,transparent);color:#047857}";
        document.head.appendChild(style);
    }

    async function mount() {
        installStudentStyles();
        installTypeAwareComposer();
        installCopyObserver();
        await mountStudentSurface();
    }

    window.ClassOperationsProduct = Object.freeze({mount:mount, productizeText:productizeText});
})();
