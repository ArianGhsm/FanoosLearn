(function () {
    "use strict";

    var appRoot = document.querySelector("[data-exam-app]");
    var dataNode = document.getElementById("exam-data");

    if (!appRoot || !dataNode) {
        return;
    }

    var parsedData;
    try {
        parsedData = JSON.parse(dataNode.textContent || "{}");
    } catch (_error) {
        renderFailure("داده‌های آزمون قابل خواندن نیست.");
        return;
    }

    var exam = normalizeExamData(parsedData);
    if (!exam.questions.length) {
        renderFailure(
            exam.emptyStateMessage || "برای این آزمون هنوز سوالی ثبت نشده است.",
            exam.emptyStateTitle || "سؤال‌های این جلسه به‌زودی اضافه می‌شود"
        );
        return;
    }

    var params = new URLSearchParams(window.location.search);
    var cohortKey = String(params.get("cohort") || "").trim();
    var storageBase = "dent1402:exam:" + window.location.pathname + ":" + cohortKey;
    var assessmentDraftKey = storageBase + ":assessment";
    var learningDraftKey = storageBase + ":learning";
    var guestFlagsKey = storageBase + ":flags";
    var studyDraftKey = storageBase + ":study";
    var selectedMode = normalizeMode(params.get("mode"));
    if (!canUseAssessmentMode() && (!selectedMode || selectedMode === "assessment")) {
        selectedMode = "learning";
    }
    var initialFlags = exam.viewerState.canPersist
        ? exam.viewerState.flaggedQuestionIndexes.slice()
        : restoreFlagIndexes(guestFlagsKey);
    var initialReport = exam.viewerState.assessmentReport;
    var state = {
        feedback: { kind: "", text: "" },
        mode: selectedMode,
        flags: new Set(initialFlags),
        flagsSync: { saving: false, queued: false, timer: 0 },
        study: restoreStudyState(studyDraftKey, exam.viewerState.studyState, exam.questions.length),
        studySync: { saving: false, queued: false, timer: 0 },
        attemptHistory: exam.viewerState.attemptHistory.slice(),
        pendingHighlight: null,
        assessment: restoreAssessmentState(assessmentDraftKey, exam.questions.length, initialReport),
        learning: restoreLearningState(learningDraftKey, exam.questions.length),
        layout: {
            chooserHintExpanded: false,
            questionMapOpen: false,
            finalReviewOpen: false,
            mediaViewer: null
        },
        ownerPanelOpen: false
    };
    var activityTrackedMode = "";
    var layoutFrame = 0;
    var delayedLayoutTimer = 0;
    var timerInterval = 0;
    var timerExpiryHandled = false;
    var BIDI_LTR_RUN_RE = /[\p{Script=Latin}0-9][\p{Script=Latin}0-9/%&+_.:=,\-]*(?:\s+[\p{Script=Latin}0-9][\p{Script=Latin}0-9/%&+_.:=,\-]*)*/gu;

    state.assessment.answers = clampAnswers(state.assessment.answers, exam.questions);
    state.learning.answers = clampAnswers(state.learning.answers, exam.questions);
    state.learning.revealed = clampRevealed(state.learning.revealed, exam.questions.length);
    if (exam.viewerState.canPersist
        && Date.parse(state.study.updatedAt || "") > Date.parse(exam.viewerState.studyState.updatedAt || "")) {
        state.studySync.queued = true;
    }
    syncPendingOfflineAssessment();

    if (!selectedMode) {
        state.mode = null;
    }
    syncModeInUrl();

    appRoot.addEventListener("click", handleClick);
    appRoot.addEventListener("change", handleChange);
    appRoot.addEventListener("input", handleInput);
    document.addEventListener("selectionchange", captureQuestionSelection);
    window.addEventListener("dent1402:offline-queue-change", function () {
        var previousPending = !!state.assessment.queued;
        syncPendingOfflineAssessment();
        if (previousPending !== !!state.assessment.queued) {
            render();
        }
    });
    window.addEventListener("dent1402:offline-queue-success", function (event) {
        var detail = event && event.detail ? event.detail : {};
        var entry = detail.entry || null;
        if (!entry || entry.kind !== "exam-assessment") {
            return;
        }
        if (String(entry.meta && entry.meta.course || "") !== String(exam.courseSlug || "")
            || String(entry.meta && entry.meta.exam || "") !== String(exam.slug || "")) {
            return;
        }
        applyOfflineAssessmentResult(detail.payload || null);
    });
    window.addEventListener("beforeunload", function () {
        flushFlagSync();
        flushStudySync();
    });
    window.addEventListener("resize", scheduleLayoutSync);
    window.addEventListener("orientationchange", scheduleLayoutSync);
    window.addEventListener("load", scheduleLayoutSync);
    window.addEventListener("online", function () {
        updateFocusBarStatus();
        flushStudySync();
    });
    window.addEventListener("offline", updateFocusBarStatus);
    document.addEventListener("keydown", handleDocumentKeydown);

    render();

    window.setTimeout(scheduleLayoutSync, 40);
    window.setTimeout(flushStudySync, 120);
    window.setTimeout(scheduleLayoutSync, 180);
    window.setTimeout(scheduleLayoutSync, 520);

    function renderFailure(message, title) {
        document.body.classList.add("quiz-stage-active");
        var fallbackBackHref = exam && exam.backHref ? exam.backHref : "/exams/";
        var fallbackBackLabel = exam && exam.backLabel ? exam.backLabel : "بازگشت";
        var heading = title || "خطا در بارگذاری آزمون";
        appRoot.innerHTML = [
            '<div class="background-overlay" aria-hidden="true"></div>',
            '<div class="exam-shell">',
            '  <main class="exam-main">',
            '    <section class="exam-stage-shell">',
            '      <div class="exam-stage-scaler">',
            '        <div class="exam-stage-canvas">',
            '          <section class="exam-panel exam-stage exam-stage--message">',
            renderBackLinkMarkup(fallbackBackHref, fallbackBackLabel, "آزمون‌ها"),
            '            <div class="exam-message-card">',
            "              <h1>" + escapeHtml(heading) + "</h1>",
            "              <p>" + escapeHtml(message) + "</p>",
            "            </div>",
            "          </section>",
            "        </div>",
            "      </div>",
            "    </section>",
            "  </main>",
            "</div>"
        ].join("");
        scheduleLayoutSync();
    }

    function render() {
        document.body.classList.add("quiz-stage-active");
        var focusSnapshot = captureFocusSnapshot();
        var existingOwnerPanel = appRoot.querySelector(".exam-owner-panel");
        if (existingOwnerPanel) {
            state.ownerPanelOpen = !!existingOwnerPanel.open;
        }
        appRoot.innerHTML = [
            '<div class="background-overlay" aria-hidden="true"></div>',
            '<div class="exam-shell">',
            '  <main class="exam-main">',
            '    <section class="exam-stage-shell">',
            '      <div class="exam-stage-scaler">',
            '        <div class="exam-stage-canvas">',
            !state.mode || !isModeStarted(state.mode) ? renderLaunchStage() : renderActiveStage(),
            isFocusModeActive() ? "" : renderOwnerInsightsPanel(),
            "        </div>",
            "      </div>",
            "    </section>",
            "  </main>",
            "</div>",
            renderSessionDialog()
        ].join("");

        syncFocusMode();
        syncTimerInterval();
        scheduleLayoutSync();
        restoreFocusSnapshot(focusSnapshot);
        scheduleDialogFocus();
    }

    function renderLaunchStage() {
        var selected = state.mode ? modeDefinition(state.mode) : null;
        var report = state.assessment.report;
        var learningStats = learningTotals();
        var assessmentStats = report ? reportTotals(report) : assessmentDraftTotals();
        var launchMeta = [
            renderMetaChip(formatValue(exam.questions.length) + " سوال", "neutral"),
            state.flags.size ? renderMetaChip(formatValue(state.flags.size) + " نشان‌دار", "flagged") : "",
            report ? renderMetaChip("کارنامه ذخیره‌شده", "success") : ""
        ].filter(Boolean);
        var previewTitle = selected ? selected.title : "اول حالت آزمون را انتخاب کن";
        var previewCopy = selected
            ? selected.description
            : "برای همین جلسه دو مسیر جدا در دسترس است: آموزشی برای پاسخ فوری و سنجشی برای ثبت کارنامه.";
        if (exam.essayOnly || exam.learningOnly) {
            previewTitle = "مرور سوال‌ها";
            previewCopy = "سوال‌ها به‌ترتیب نمایش داده می‌شوند؛ روی دکمه نمایش پاسخ بزن تا کارت توضیح همان مورد را ببینی.";
        }
        var previewStats = [];

        if (state.mode === "assessment") {
            if (report) {
                previewStats.push(renderMiniStat("درصد", formatPercent(report.percent)));
                previewStats.push(renderMiniStat("صحیح", formatValue(report.correct)));
                previewStats.push(renderMiniStat("غلط", formatValue(report.wrong)));
            } else {
                previewStats.push(renderMiniStat("پاسخ‌داده‌شده", formatValue(assessmentStats.answered)));
                previewStats.push(renderMiniStat("بی‌پاسخ", formatValue(assessmentStats.unanswered)));
                previewStats.push(renderMiniStat("کارنامه", exam.viewerState.canPersist ? "فعال" : "نیاز به ورود"));
            }
        } else if (state.mode === "learning") {
            previewStats.push(renderMiniStat("حل‌شده", formatValue(learningStats.answered)));
            previewStats.push(renderMiniStat("باقی‌مانده", formatValue(learningStats.unanswered)));
            previewStats.push(renderMiniStat(exam.essayOnly || exam.learningOnly ? "نمایش پاسخ" : "پاسخ فوری", "فعال"));
        }

        return [
            '<section class="exam-panel exam-stage exam-stage--launch">',
            renderLaunchHeader(launchMeta),
            '  <div class="exam-launch-grid">',
            '    <section class="exam-launch-copy">',
            '      <span class="exam-kicker">شروع آزمون</span>',
            '      <h1 class="exam-stage-title">' + escapeHtml(exam.title) + "</h1>",
            '      <p class="exam-stage-subtitle">' + escapeHtml(exam.subtitle) + "</p>",
            "    </section>",
            '    <section class="exam-launch-panel">',
            '      <div class="exam-launch-panel__head">',
            '        <div>',
            '          <span class="exam-launch-panel__eyebrow">انتخاب حالت</span>',
            '          <h2 class="exam-launch-panel__title">' + escapeHtml(previewTitle) + "</h2>",
            "        </div>",
            '        <div class="exam-mode-pills">',
            canUseAssessmentMode() ? renderModePill("assessment", "سنجشی", "ثبت پاسخ‌ها و کارنامه نهایی", !state.mode) : "",
            renderModePill("learning", exam.essayOnly || exam.learningOnly ? "مرور سوال‌ها" : "آموزشی", exam.essayOnly || exam.learningOnly ? "پاسخ‌ها را قدم‌به‌قدم مرور کن" : "پاسخ و توضیح را همان لحظه ببین", !state.mode),
            "        </div>",
            "      </div>",
            '      <p class="exam-launch-panel__copy">' + escapeHtml(previewCopy) + "</p>",
            previewStats.length ? '<div class="exam-mini-stats">' + previewStats.join("") + "</div>" : "",
            state.layout.chooserHintExpanded && canUseAssessmentMode()
                ? '<div class="exam-note-card">در حالت سنجشی همه سوال‌ها با کارنامه، رتبه و ذخیره نتیجه اجرا می‌شود. در حالت آموزشی پس از هر پاسخ، جواب درست و توضیح همان سوال را می‌بینی.</div>'
                : "",
            renderCustomPracticeBuilder(),
            renderLaunchActions(),
            "    </section>",
            "  </div>",
            "</section>"
        ].join("");
    }

    function renderCustomPracticeBuilder() {
        var topics = uniqueQuestionValues("topic");
        var difficulties = uniqueQuestionValues("difficultyLabel");
        var activeCount = Array.isArray(state.learning.customIndexes) ? state.learning.customIndexes.length : 0;
        return [
            '<details class="exam-custom-builder"' + (activeCount ? " open" : "") + '>',
            '  <summary><span><strong>آزمون شخصی</strong><small>جلسه تمرینی از همین بانک سؤال</small></span><span>' + escapeHtml(activeCount ? formatValue(activeCount) + " سؤال فعال" : "ساخت") + '</span></summary>',
            '  <div class="exam-custom-builder__grid">',
            renderBuilderSelect("custom-topic", "مبحث", [{ value: "all", label: "همه مباحث" }].concat(topics.map(function (value) { return { value: value, label: value }; }))),
            renderBuilderSelect("custom-difficulty", "سختی", [{ value: "all", label: "همه سطوح" }].concat(difficulties.map(function (value) { return { value: value, label: value }; }))),
            renderBuilderSelect("custom-status", "وضعیت", [
                { value: "all", label: "همه سؤال‌ها" },
                { value: "unseen", label: "حل‌نشده‌ها" },
                { value: "mistakes", label: "دفترچه اشتباهات" },
                { value: "flagged", label: "نشان‌دارها" }
            ]),
            '    <label><span>تعداد سؤال</span><input id="custom-count" type="number" inputmode="numeric" min="1" max="' + escapeHtml(String(exam.questions.length)) + '" value="' + escapeHtml(String(Math.min(20, exam.questions.length))) + '" data-digit-locale="latin"></label>',
            '  </div>',
            '  <div class="exam-custom-builder__actions">',
            '    <button class="exam-btn exam-btn--primary" type="button" data-action="build-custom-practice">ساخت و شروع تمرین</button>',
            activeCount ? '    <button class="exam-btn exam-btn--ghost" type="button" data-action="clear-custom-practice">بازگشت به همه سؤال‌ها</button>' : "",
            '  </div>',
            '  <p>این جلسه فقط حالت آموزشی را فیلتر می‌کند و نتیجه سنجشی رسمی آزمون را تغییر نمی‌دهد.</p>',
            '</details>'
        ].join("");
    }

    function renderBuilderSelect(id, label, options) {
        return '<label><span>' + escapeHtml(label) + '</span><select id="' + escapeHtml(id) + '">' + options.map(function (item) {
            return '<option value="' + escapeHtml(item.value) + '">' + escapeHtml(item.label) + '</option>';
        }).join("") + '</select></label>';
    }

    function uniqueQuestionValues(key) {
        return Array.from(new Set(exam.questions.map(function (question) {
            return normalizeText(question[key]);
        }).filter(Boolean))).sort(function (a, b) {
            return a.localeCompare(b, "fa");
        });
    }

    function renderLaunchHeader(launchMeta) {
        return [
            '  <div class="exam-stage-head exam-stage-head--launch">',
            '    <div class="exam-stage-head__main">',
            '      <div class="exam-stage-head__topline">',
            renderBackLinkMarkup(exam.backHref, exam.backLabel),
            renderHeaderIdentity(),
            "      </div>",
            '      <div class="exam-stage-head__copy">',
            '        <div class="exam-meta-strip">' + launchMeta.join("") + "</div>",
            "      </div>",
            "    </div>",
            state.feedback.text ? '<div class="exam-feedback exam-feedback--' + escapeHtml(state.feedback.kind || "neutral") + '">' + escapeHtml(state.feedback.text) + "</div>" : "",
            "  </div>"
        ].join("");
    }

    function headerMetaParts(value) {
        return String(value || "").split("|").map(function (part) {
            return normalizeText(part);
        }).filter(Boolean);
    }

    function headerCourseLabel() {
        var courseTitle = normalizeText(exam.courseTitle);
        if (courseTitle) {
            return courseTitle;
        }

        var eyebrowParts = headerMetaParts(exam.eyebrow);
        if (eyebrowParts.length) {
            return eyebrowParts[0];
        }

        return compactBackTarget(exam.backLabel);
    }

    function headerContextLabel() {
        var eyebrowParts = headerMetaParts(exam.eyebrow);
        if (eyebrowParts.length > 1) {
            return eyebrowParts[1];
        }

        return "";
    }

    function compactBackTarget(label) {
        var text = normalizeText(label);
        if (!text) {
            return "";
        }

        text = text
            .replace(/^بازگشت(?:\s+به)?\s*/u, "")
            .replace(/^فهرست\s*/u, "")
            .replace(/^آزمون(?:‌|\s)*های\s*/u, "")
            .replace(/^آزمون\s*/u, "")
            .trim();

        return normalizeText(text);
    }

    function renderBackLinkMarkup(href, label, targetOverride) {
        var target = normalizeText(targetOverride) || compactBackTarget(label) || headerCourseLabel();
        return [
            '<a class="back-btn exam-back-link" href="' + escapeHtml(href || "/exams/") + '">',
            '  <span class="exam-back-link__icon" aria-hidden="true">→</span>',
            '  <span class="exam-back-link__content">',
            '    <span class="exam-back-link__label">' + renderResponsiveLabel("بازگشت به فهرست", "بازگشت") + "</span>",
            target ? '    <span class="exam-back-link__meta">' + escapeHtml(target) + "</span>" : "",
            "  </span>",
            "</a>"
        ].join("");
    }

    function renderHeaderIdentity() {
        var chips = [];
        var course = headerCourseLabel();
        var context = headerContextLabel();

        if (course) {
            chips.push('<span class="exam-head-chip exam-head-chip--course">' + escapeHtml(course) + "</span>");
        }

        if (context && context !== course) {
            chips.push('<span class="exam-head-chip">' + escapeHtml(context) + "</span>");
        }

        if (!chips.length) {
            return "";
        }

        return '<div class="exam-head-chips">' + chips.join("") + "</div>";
    }

    function renderLaunchActions() {
        var currentMode = state.mode;
        var report = state.assessment.report;
        var assessmentStats = report ? reportTotals(report) : assessmentDraftTotals();
        var learningStats = learningTotals();

        if (!currentMode) {
            return [
                '  <div class="exam-launch-actions">',
                '    <button class="exam-btn exam-btn--primary" type="button" disabled>ابتدا حالت آزمون را انتخاب کن</button>',
                '    <button class="exam-btn exam-btn--ghost" type="button" data-action="toggle-chooser-hint">تفاوت دو حالت</button>',
                "  </div>"
            ].join("");
        }

        if (currentMode === "assessment" && !exam.viewerState.canPersist) {
            return [
                '  <div class="exam-launch-actions">',
                '    <a class="exam-btn exam-btn--primary" href="' + escapeHtml(loginHref()) + '">ورود برای حالت سنجشی</a>',
                '    <button class="exam-btn exam-btn--ghost" type="button" data-action="set-mode" data-mode="learning">رفتن به حالت آموزشی</button>',
                "  </div>"
            ].join("");
        }

        var primaryLabel = "";
        if (currentMode === "assessment") {
            if (report) {
                primaryLabel = "مشاهده کارنامه سنجشی";
            } else if (assessmentStats.answered > 0) {
                primaryLabel = "ادامه آزمون سنجشی";
            } else {
                primaryLabel = "شروع آزمون سنجشی";
            }
        } else if (exam.essayOnly || exam.learningOnly) {
            primaryLabel = learningStats.answered > 0 ? "ادامه مرور سوال‌ها" : "شروع مرور سوال‌ها";
        } else if (learningStats.answered > 0) {
            primaryLabel = "ادامه آزمون آموزشی";
        } else {
            primaryLabel = "شروع آزمون آموزشی";
        }

        return [
            '  <div class="exam-launch-actions">',
            '    <button class="exam-btn exam-btn--primary" type="button" data-action="start-mode"' + (currentMode ? ' data-mode="' + escapeHtml(currentMode) + '"' : "") + ">" + escapeHtml(primaryLabel) + "</button>",
            canUseAssessmentMode() ? '    <button class="exam-btn exam-btn--ghost" type="button" data-action="toggle-chooser-hint">' + escapeHtml(state.layout.chooserHintExpanded ? "بستن توضیح" : "تفاوت دو حالت") + "</button>" : "",
            "  </div>"
        ].join("");
    }

    function renderModePill(mode, label, description, ghostWhenEmpty) {
        var selected = state.mode === mode;
        return [
            '<button class="exam-mode-pill' + (selected ? " is-active" : "") + (ghostWhenEmpty ? " is-neutral" : "") + '" type="button" data-action="set-mode" data-mode="' + escapeHtml(mode) + '" aria-pressed="' + (selected ? "true" : "false") + '">',
            '  <span class="exam-mode-pill__copy"><strong>' + escapeHtml(label) + '</strong><small>' + escapeHtml(description) + '</small></span>',
            '  <span class="exam-mode-pill__indicator" aria-hidden="true">' + (selected ? "✓" : "") + '</span>',
            "</button>"
        ].join("");
    }

    function renderActiveStage() {
        if (state.mode === "assessment") {
            if (!exam.viewerState.canPersist) {
                return renderLaunchStage();
            }
            return state.assessment.report
                ? renderAssessmentReportStage()
                : renderFocusBar() + renderAssessmentDraftStage();
        }

        if (state.mode === "learning") {
            return renderFocusBar() + renderLearningStage();
        }

        return renderLaunchStage();
    }

    function renderFocusBar() {
        var isAssessment = state.mode === "assessment";
        var totals = isAssessment ? assessmentDraftTotals() : learningTotals();
        var currentIndex = isAssessment
            ? ensureAssessmentIndex(assessmentVisibleIndexes(state.assessment.filter))
            : ensureLearningIndex(learningVisibleIndexes());
        var saveStatus = focusSaveStatus();
        var progress = exam.questions.length > 0
            ? Math.round((totals.answered / exam.questions.length) * 100)
            : 0;

        return [
            '<section class="exam-focus-bar" aria-label="وضعیت جلسه آزمون">',
            '  <div class="exam-focus-bar__identity">',
            '    <span class="exam-focus-bar__mode">' + escapeHtml(isAssessment ? "سنجشی" : "آموزشی") + "</span>",
            '    <strong class="exam-focus-bar__question">سؤال ' + escapeHtml(formatValue(currentIndex + 1)) + ' از ' + escapeHtml(formatValue(exam.questions.length)) + "</strong>",
            "  </div>",
            '  <div class="exam-focus-bar__progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + escapeHtml(String(progress)) + '" aria-label="' + escapeHtml(formatValue(progress) + " درصد پیشرفت") + '">',
            '    <span class="exam-focus-bar__progress-copy">' + escapeHtml(formatValue(totals.answered) + " پاسخ") + "</span>",
            '    <span class="exam-focus-bar__progress-track" aria-hidden="true"><span style="inline-size:' + escapeHtml(String(progress)) + '%"></span></span>',
            "  </div>",
            '  <div class="exam-focus-bar__tools">',
            '    <span class="exam-focus-bar__timer" data-exam-timer aria-live="off">' + escapeHtml(timerLabel()) + "</span>",
            '    <span class="exam-focus-bar__save is-' + escapeHtml(saveStatus.tone) + '" data-exam-save-status role="status" aria-live="polite">' + escapeHtml(saveStatus.label) + "</span>",
            '    <button class="exam-focus-bar__button" type="button" data-action="open-question-map" aria-haspopup="dialog">نقشه سؤال‌ها</button>',
            '    <button class="exam-focus-bar__button exam-focus-bar__button--exit" type="button" data-action="leave-focus-mode">خروج</button>',
            "  </div>",
            "</section>"
        ].join("");
    }

    function renderAssessmentDraftStage() {
        var totals = assessmentDraftTotals();
        var visibleIndexes = assessmentVisibleIndexes(state.assessment.filter);
        var currentIndex = ensureAssessmentIndex(visibleIndexes);

        return [
            '<section class="exam-panel exam-stage exam-stage--session exam-stage--assessment">',
            renderSessionHeader({
                mode: "assessment",
                title: "آزمون سنجشی",
                subtitle: "همه سوال‌ها در همین حالت ذخیره می‌شوند و بعد از ثبت، کارنامه جداگانه نمایش داده می‌شود.",
                chips: [
                    renderMetaChip("سوال " + formatValue(currentIndex + 1) + " از " + formatValue(exam.questions.length), "neutral"),
                    state.learning.customLabel ? renderMetaChip(state.learning.customLabel, "success") : "",
                    renderMetaChip("پاسخ‌داده‌شده " + formatValue(totals.answered), "neutral"),
                    renderMetaChip("بی‌پاسخ " + formatValue(totals.unanswered), totals.unanswered ? "warning" : "success"),
                    state.flags.size ? renderMetaChip(formatValue(state.flags.size) + " نشان‌دار", "flagged") : ""
                ],
                statusText: state.flagsSync.saving ? "در حال ذخیره نشان‌دارها..." : state.feedback.text,
                statusKind: state.flagsSync.saving ? "neutral" : state.feedback.kind
            }),
            renderQuestionRail({
                visibleIndexes: visibleIndexes,
                currentIndex: currentIndex,
                prevAction: "assessment-prev",
                nextAction: "assessment-next",
                jumpAction: "jump-to-question",
                stateResolver: assessmentNavState,
                emptyLabel: "نمایش خالی شده است."
            }),
            renderAssessmentDraftCompactPanel(totals, visibleIndexes.length),
            visibleIndexes.length ? [
                '<div class="exam-stage-body">',
                renderAssessmentDraftQuestionCard(currentIndex),
                renderAssessmentDraftSidePanel(totals, visibleIndexes.length),
                "</div>"
            ].join("") : renderStageEmptyState("هیچ سوالی با این فیلتر پیدا نشد."),
            "</section>"
        ].join("");
    }

    function renderAssessmentReportStage() {
        var report = state.assessment.report;
        var totals = reportTotals(report);
        var visibleIndexes = assessmentVisibleIndexes(state.assessment.filter);
        var currentIndex = ensureAssessmentIndex(visibleIndexes);

        return [
            '<section class="exam-panel exam-stage exam-stage--session exam-stage--report">',
            renderSessionHeader({
                mode: "assessment",
                title: "کارنامه سنجشی",
                subtitle: "نتیجه این آزمون ذخیره شده است. از نوار بالایی سوال‌ها را جابه‌جا کن و پاسخ‌ها را مرور کن.",
                chips: [
                    renderMetaChip("درصد " + formatPercent(report.percent), "success"),
                    renderMetaChip("صحیح " + formatValue(totals.correct), "success"),
                    renderMetaChip("غلط " + formatValue(totals.wrong), totals.wrong ? "danger" : "neutral"),
                    renderMetaChip("بی‌پاسخ " + formatValue(totals.unanswered), totals.unanswered ? "warning" : "neutral")
                ],
                statusText: state.feedback.text,
                statusKind: state.feedback.kind
            }),
            renderQuestionRail({
                visibleIndexes: visibleIndexes,
                currentIndex: currentIndex,
                prevAction: "assessment-prev",
                nextAction: "assessment-next",
                jumpAction: "jump-to-question",
                stateResolver: assessmentNavState,
                emptyLabel: "در این فیلتر سوالی باقی نمانده است."
            }),
            renderAssessmentReportCompactPanel(report),
            visibleIndexes.length ? [
                '<div class="exam-stage-body exam-stage-body--report">',
                renderAssessmentReportQuestionCard(currentIndex, report),
                renderAssessmentReportSidePanel(report),
                "</div>"
            ].join("") : renderStageEmptyState("در این فیلتر سوالی برای مرور باقی نمانده است."),
            renderReportInsights(report),
            "</section>"
        ].join("");
    }

    function renderLearningStage() {
        var stats = learningTotals();
        var visibleIndexes = learningVisibleIndexes();
        var currentIndex = ensureLearningIndex(visibleIndexes);

        return [
            '<section class="exam-panel exam-stage exam-stage--session exam-stage--learning">',
            renderSessionHeader({
                mode: "learning",
                title: exam.essayOnly || exam.learningOnly ? "مرور سوال‌ها" : "آزمون آموزشی",
                subtitle: exam.essayOnly || exam.learningOnly
                    ? "روی «نمایش پاسخ تشریحی» بزن تا پاسخ کامل همان سوال بدون خروج از همین صفحه نمایش داده شود."
                    : "بعد از هر پاسخ، جواب درست و توضیح همان سوال بدون خروج از همین صفحه نمایش داده می‌شود.",
                chips: [
                    renderMetaChip("سوال " + formatValue(currentIndex + 1) + " از " + formatValue(exam.questions.length), "neutral"),
                    renderMetaChip("حل‌شده " + formatValue(stats.answered), "success"),
                    renderMetaChip("باقی‌مانده " + formatValue(stats.unanswered), stats.unanswered ? "warning" : "success"),
                    state.flags.size ? renderMetaChip(formatValue(state.flags.size) + " نشان‌دار", "flagged") : ""
                ],
                statusText: state.feedback.text,
                statusKind: state.feedback.kind
            }),
            renderQuestionRail({
                visibleIndexes: visibleIndexes,
                currentIndex: currentIndex,
                prevAction: "learning-prev",
                nextAction: "learning-next",
                jumpAction: "learning-goto",
                stateResolver: learningNavState,
                emptyLabel: "هنوز سوال نشان‌داری برای این نما وجود ندارد."
            }),
            renderLearningCompactPanel(stats, currentIndex),
            visibleIndexes.length ? [
                '<div class="exam-stage-body">',
                renderLearningQuestionCard(currentIndex),
                renderLearningSidePanel(stats, currentIndex, visibleIndexes.length),
                "</div>"
            ].join("") : renderStageEmptyState("هنوز سوالی در این فیلتر باقی نمانده است."),
            "</section>"
        ].join("");
    }

    function renderSessionHeader(config) {
        return [
            '  <div class="exam-stage-head exam-stage-head--session">',
            '    <div class="exam-stage-head__main">',
            '      <div class="exam-stage-head__topline">',
            renderBackLinkMarkup(exam.backHref, exam.backLabel),
            renderHeaderIdentity(),
            "      </div>",
            '      <div class="exam-stage-head__copy">',
            '        <h2 class="exam-stage-title exam-stage-title--compact">' + escapeHtml(exam.title) + "</h2>",
            '        <p class="exam-stage-subtitle exam-stage-subtitle--compact">' + escapeHtml(config.subtitle) + "</p>",
            "      </div>",
            "    </div>",
            '    <div class="exam-stage-head__aside">',
            '      <div class="exam-mode-pills exam-mode-pills--compact">',
            canUseAssessmentMode() ? renderModePill("assessment", "سنجشی", "ثبت کارنامه", false) : "",
            canUseAssessmentMode() ? renderModePill("learning", "آموزشی", "پاسخ فوری", false) : "",
            "      </div>",
            config.statusText ? '<div class="exam-feedback exam-feedback--' + escapeHtml(config.statusKind || "neutral") + '">' + escapeHtml(config.statusText) + "</div>" : "",
            "    </div>",
            "  </div>",
            '  <div class="exam-meta-strip exam-meta-strip--session">' + (config.chips || []).join("") + "</div>"
        ].join("");
    }

    function renderQuestionRail(config) {
        var indexes = config.visibleIndexes || [];
        var railItems = buildRailItems(indexes, config.currentIndex, railWindowSize());
        var currentPosition = indexes.indexOf(config.currentIndex);
        var stateFn = typeof config.stateResolver === "function" ? config.stateResolver : function () {
            return "neutral";
        };
        var trackHtml = railItems.map(function (item) {
            if (item.type === "ellipsis") {
                return '<span class="exam-rail__ellipsis" aria-hidden="true">…</span>';
            }

            return [
                '<button class="exam-rail__pill' + (item.index === config.currentIndex ? " is-active" : "") + (isFlagged(item.index) ? " is-flagged" : "") + '" type="button" data-action="' + escapeHtml(config.jumpAction) + '" data-question-index="' + escapeHtml(String(item.index)) + '" data-state="' + escapeHtml(stateFn(item.index)) + '">',
                escapeHtml(formatValue(item.index + 1)),
                "</button>"
            ].join("");
        }).join("");

        return [
            '  <div class="exam-question-rail">',
            '    <div class="exam-question-rail__meta">',
            '      <strong>' + escapeHtml("شماره سوال") + "</strong>",
            '      <span>' + escapeHtml(indexes.length ? ("نمایش " + formatValue(currentPosition + 1) + " از " + formatValue(indexes.length)) : config.emptyLabel) + "</span>",
            "    </div>",
            '    <div class="exam-question-rail__bar">',
            '      <button class="exam-rail__nav" type="button" data-action="' + escapeHtml(config.prevAction) + '"' + (indexes.length < 2 || currentPosition <= 0 ? " disabled" : "") + ">قبلی</button>",
            '      <div class="exam-question-rail__track">' + trackHtml + "</div>",
            '      <button class="exam-rail__nav" type="button" data-action="' + escapeHtml(config.nextAction) + '"' + (indexes.length < 2 || currentPosition >= indexes.length - 1 ? " disabled" : "") + ">بعدی</button>",
            "    </div>",
            "  </div>"
        ].join("");
    }

    function renderAssessmentDraftQuestionCard(questionIndex) {
        var question = exam.questions[questionIndex];
        var selectedIndex = state.assessment.answers[questionIndex];
        var visibleIndexes = assessmentVisibleIndexes(state.assessment.filter);
        var currentPosition = visibleIndexes.indexOf(questionIndex);

        return [
            '<article class="exam-session-card exam-session-card--question" data-card-state="' + escapeHtml(assessmentDraftState(selectedIndex)) + '">',
            renderQuestionCardHead(questionIndex, assessmentStateLabel(assessmentDraftState(selectedIndex))),
            renderQuestionClinicalContext(question, questionIndex),
            '  <h3 class="exam-question-text" data-question-text="' + escapeHtml(String(questionIndex)) + '">' + richTextWithHighlights(question.question, questionHighlights(questionIndex)) + "</h3>",
            '  <div class="exam-option-grid' + (question.useCompactOptions ? " is-compact" : "") + '" role="radiogroup" aria-label="گزینه‌های سؤال ' + escapeHtml(formatValue(questionIndex + 1)) + '">',
            question.options.map(function (option, optionIndex) {
                return renderAssessmentOption(questionIndex, optionIndex, option, selectedIndex, question.correctIndex, false);
            }).join(""),
            "  </div>",
            renderQuestionStudyTools(questionIndex),
            '  <div class="exam-question-actions">',
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="assessment-prev"' + (currentPosition <= 0 ? " disabled" : "") + ">" + renderResponsiveLabel("\u0633\u0648\u0627\u0644 \u0642\u0628\u0644\u06cc", "\u0642\u0628\u0644\u06cc") + "</button>",
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="toggle-flag" data-question-index="' + escapeHtml(String(questionIndex)) + '">' + renderResponsiveLabel(isFlagged(questionIndex) ? "\u062d\u0630\u0641 \u0646\u0634\u0627\u0646" : "\u0646\u0634\u0627\u0646\u200c\u062f\u0627\u0631 \u06a9\u0646", isFlagged(questionIndex) ? "\u062d\u0630\u0641" : "\u0646\u0634\u0627\u0646") + "</button>",
            '    <button class="exam-btn exam-btn--primary" type="button" data-action="assessment-next"' + (currentPosition >= visibleIndexes.length - 1 ? " disabled" : "") + ">" + renderResponsiveLabel("\u0633\u0648\u0627\u0644 \u0628\u0639\u062f\u06cc", "\u0628\u0639\u062f\u06cc") + "</button>",
            "  </div>",
            "</article>"
        ].join("");
    }

    function renderAssessmentReportQuestionCard(questionIndex, report) {
        var question = exam.questions[questionIndex];
        var selectedIndex = report.answers[questionIndex];
        var visibleIndexes = assessmentVisibleIndexes(state.assessment.filter);
        var currentPosition = visibleIndexes.indexOf(questionIndex);
        var stateName = assessmentReviewState(questionIndex, selectedIndex);

        return [
            '<article class="exam-session-card exam-session-card--question" data-card-state="' + escapeHtml(stateName) + '">',
            renderQuestionCardHead(questionIndex, assessmentStateLabel(stateName)),
            renderQuestionClinicalContext(question, questionIndex),
            '  <h3 class="exam-question-text" data-question-text="' + escapeHtml(String(questionIndex)) + '">' + richTextWithHighlights(question.question, questionHighlights(questionIndex)) + "</h3>",
            '  <div class="exam-option-grid' + (question.useCompactOptions ? " is-compact" : "") + '" role="radiogroup" aria-label="گزینه‌های سؤال ' + escapeHtml(formatValue(questionIndex + 1)) + '">',
            question.options.map(function (option, optionIndex) {
                return renderAssessmentOption(questionIndex, optionIndex, option, selectedIndex, question.correctIndex, true);
            }).join(""),
            "  </div>",
            renderMcqAnswerCard(question, selectedIndex, {
                unresolvedLabel: "کلید قطعی این سؤال در فایل منبع موجود نیست"
            }),
            renderQuestionStudyTools(questionIndex),
            '  <div class="exam-question-actions">',
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="assessment-prev"' + (currentPosition <= 0 ? " disabled" : "") + ">" + renderResponsiveLabel("\u0633\u0648\u0627\u0644 \u0642\u0628\u0644\u06cc", "\u0642\u0628\u0644\u06cc") + "</button>",
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="toggle-flag" data-question-index="' + escapeHtml(String(questionIndex)) + '">' + renderResponsiveLabel(isFlagged(questionIndex) ? "\u062d\u0630\u0641 \u0646\u0634\u0627\u0646" : "\u0646\u0634\u0627\u0646\u200c\u062f\u0627\u0631 \u06a9\u0646", isFlagged(questionIndex) ? "\u062d\u0630\u0641" : "\u0646\u0634\u0627\u0646") + "</button>",
            '    <button class="exam-btn exam-btn--primary" type="button" data-action="assessment-next"' + (currentPosition >= visibleIndexes.length - 1 ? " disabled" : "") + ">" + renderResponsiveLabel("\u0633\u0648\u0627\u0644 \u0628\u0639\u062f\u06cc", "\u0628\u0639\u062f\u06cc") + "</button>",
            "  </div>",
            "</article>"
        ].join("");
    }

    function renderLearningQuestionCard(questionIndex) {
        var question = exam.questions[questionIndex];
        var selectedIndex = state.learning.answers[questionIndex];
        var revealed = state.learning.revealed[questionIndex];
        var visibleIndexes = learningVisibleIndexes();
        var currentPosition = visibleIndexes.indexOf(questionIndex);
        var essayRevealLabel = question.answerRevealLabel || exam.answerRevealLabel || "نمایش پاسخ تشریحی";

        return [
            '<article class="exam-session-card exam-session-card--question" data-card-state="' + escapeHtml(learningNavState(questionIndex)) + '">',
            renderQuestionCardHead(questionIndex, revealed ? learningAnswerLabel(question, selectedIndex) : "در انتظار پاسخ"),
            renderQuestionClinicalContext(question, questionIndex),
            '  <h3 class="exam-question-text" data-question-text="' + escapeHtml(String(questionIndex)) + '">' + richTextWithHighlights(question.question, questionHighlights(questionIndex)) + "</h3>",
            question.isEssay ? "" : [
                '  <div class="exam-option-grid' + (question.useCompactOptions ? " is-compact" : "") + '" role="radiogroup" aria-label="گزینه‌های سؤال ' + escapeHtml(formatValue(questionIndex + 1)) + '">',
                question.options.map(function (option, optionIndex) {
                    return renderLearningOption(questionIndex, optionIndex, option, selectedIndex, question.correctIndex, revealed);
                }).join(""),
                "  </div>"
            ].join(""),
            question.isEssay
                ? (revealed
                    ? renderEssayAnswerCard(question)
                    : '<div class="exam-essay-reveal"><button class="exam-btn exam-btn--primary" type="button" data-action="learning-reveal-essay" data-question-index="' + escapeHtml(String(questionIndex)) + '">' + escapeHtml(essayRevealLabel) + "</button></div>")
                : (revealed ? renderLearningFeedback(question, selectedIndex, questionIndex) : '<div class="exam-note-card">یکی از گزینه‌ها را انتخاب کن تا پاسخ صحیح و توضیح همان سوال نمایش داده شود.</div>'),
            renderQuestionStudyTools(questionIndex),
            '  <div class="exam-question-actions">',
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="learning-prev"' + (currentPosition <= 0 ? " disabled" : "") + ">" + renderResponsiveLabel("\u0633\u0648\u0627\u0644 \u0642\u0628\u0644\u06cc", "\u0642\u0628\u0644\u06cc") + "</button>",
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="toggle-flag" data-question-index="' + escapeHtml(String(questionIndex)) + '">' + renderResponsiveLabel(isFlagged(questionIndex) ? "\u062d\u0630\u0641 \u0646\u0634\u0627\u0646" : "\u0646\u0634\u0627\u0646\u200c\u062f\u0627\u0631 \u06a9\u0646", isFlagged(questionIndex) ? "\u062d\u0630\u0641" : "\u0646\u0634\u0627\u0646") + "</button>",
            '    <button class="exam-btn exam-btn--primary" type="button" data-action="learning-next"' + (currentPosition >= visibleIndexes.length - 1 ? " disabled" : "") + ">" + renderResponsiveLabel("\u0633\u0648\u0627\u0644 \u0628\u0639\u062f\u06cc", "\u0628\u0639\u062f\u06cc") + "</button>",
            "  </div>",
            "</article>"
        ].join("");
    }

    function renderQuestionCardHead(questionIndex, stateLabel) {
        var question = exam.questions[questionIndex];
        return [
            '  <div class="exam-question-card__head">',
            '    <div class="exam-question-card__title-wrap">',
            '      <span class="exam-question-number">سوال ' + escapeHtml(formatValue(questionIndex + 1)) + "</span>",
            '      <span class="exam-question-state">' + escapeHtml(stateLabel) + "</span>",
            "    </div>",
            '    <div class="exam-question-head-badges">',
            question.topic ? '      <span class="exam-question-topic">' + escapeHtml(question.topic) + '</span>' : "",
            question.difficultyLabel ? '      <span class="exam-question-difficulty" data-level="' + escapeHtml(question.difficultyKey) + '">' + escapeHtml(question.difficultyLabel) + '</span>' : "",
            '      <span class="exam-question-flag' + (isFlagged(questionIndex) ? " is-active" : "") + '">' + escapeHtml(isFlagged(questionIndex) ? "نشان‌دار" : "عادی") + "</span>",
            '    </div>',
            "  </div>"
        ].join("");
    }

    function renderQuestionClinicalContext(question, questionIndex) {
        var caseHtml = "";
        if (question.caseContext) {
            caseHtml = [
                '<section class="exam-case-card" aria-label="اطلاعات کیس بالینی">',
                '  <div class="exam-case-card__head"><span>کیس بالینی</span><strong>' + escapeHtml(question.caseContext.title || "شرح بیمار") + '</strong></div>',
                question.caseContext.summary ? '  <p>' + richTextHtml(question.caseContext.summary) + '</p>' : "",
                question.caseContext.facts.length ? '<dl>' + question.caseContext.facts.map(function (fact) {
                    return '<div><dt>' + escapeHtml(fact.label) + '</dt><dd>' + richTextHtml(fact.value) + '</dd></div>';
                }).join("") + '</dl>' : "",
                question.caseContext.alert ? '<div class="exam-case-card__alert">' + richTextHtml(question.caseContext.alert) + '</div>' : "",
                '</section>'
            ].join("");
        }
        var mediaHtml = question.media.length ? '<div class="exam-question-media">' + question.media.map(function (media, mediaIndex) {
            return [
                '<figure class="exam-media-card">',
                '  <button type="button" data-action="open-question-media" data-question-index="' + escapeHtml(String(questionIndex)) + '" data-media-index="' + escapeHtml(String(mediaIndex)) + '" aria-label="نمایش بزرگ ' + escapeHtml(media.alt) + '">',
                '    <img src="' + escapeHtml(media.src) + '" alt="' + escapeHtml(media.alt) + '" loading="lazy" decoding="async">',
                '    <span>نمایش و بزرگ‌نمایی</span>',
                '  </button>',
                media.caption ? '  <figcaption>' + escapeHtml(media.caption) + '</figcaption>' : "",
                '</figure>'
            ].join("");
        }).join("") + '</div>' : "";
        return caseHtml + mediaHtml;
    }

    function renderQuestionStudyTools(questionIndex) {
        var note = String(state.study.notesByQuestion[String(questionIndex)] || "");
        var highlights = questionHighlights(questionIndex);
        var inMistakeNotebook = isMistakeQuestion(questionIndex);
        var badges = [note ? "یادداشت" : "", highlights.length ? "هایلایت" : "", inMistakeNotebook ? "دفترچه اشتباهات" : ""].filter(Boolean);
        return [
            '<details class="exam-study-tools">',
            '  <summary class="exam-study-tools__summary">',
            '    <span>ابزار مطالعه</span>',
            '    <span class="exam-study-tools__badges">' + escapeHtml(badges.join(" • ")) + "</span>",
            "  </summary>",
            '  <div class="exam-study-tools__body">',
            '    <div class="exam-study-tools__actions">',
            '      <button class="exam-btn exam-btn--ghost" type="button" data-action="add-question-highlight" data-question-index="' + escapeHtml(String(questionIndex)) + '">هایلایت متن انتخاب‌شده</button>',
            highlights.length ? '      <button class="exam-btn exam-btn--ghost" type="button" data-action="clear-question-highlights" data-question-index="' + escapeHtml(String(questionIndex)) + '">پاک‌کردن هایلایت‌ها</button>' : "",
            '      <button class="exam-btn ' + (inMistakeNotebook ? "exam-btn--danger" : "exam-btn--ghost") + '" type="button" data-action="toggle-mistake-notebook" data-question-index="' + escapeHtml(String(questionIndex)) + '">' + escapeHtml(inMistakeNotebook ? "حذف از دفترچه اشتباهات" : "افزودن به دفترچه اشتباهات") + "</button>",
            "    </div>",
            '    <label class="exam-study-note"><span>یادداشت شخصی این سؤال</span><textarea data-study-note data-question-index="' + escapeHtml(String(questionIndex)) + '" maxlength="4000" placeholder="نکته، دلیل اشتباه یا چیزی که باید مرور کنی...">' + escapeHtml(note) + "</textarea></label>",
            '    <p class="exam-study-tools__hint">برای هایلایت، بخشی از صورت سؤال را انتخاب کن و سپس دکمه هایلایت را بزن. دکمه × کنار هر گزینه نیز آن را خط می‌زند.</p>',
            "  </div>",
            "</details>"
        ].join("");
    }

    function renderAssessmentDraftSidePanel(totals, visibleCount) {
        return [
            '<aside class="exam-session-card exam-session-card--side">',
            '  <div class="exam-side-section">',
            '    <span class="exam-kicker">وضعیت سنجشی</span>',
            '    <div class="exam-stat-grid">',
            renderStatCard("کل", formatValue(exam.questions.length)),
            renderStatCard("پاسخ‌داده", formatValue(totals.answered)),
            renderStatCard("بی‌پاسخ", formatValue(totals.unanswered)),
            renderStatCard("نشان‌دار", formatValue(state.flags.size)),
            "    </div>",
            "  </div>",
            '  <div class="exam-side-section">',
            '    <span class="exam-side-title">فیلتر نمایش</span>',
            '    <div class="exam-filter-pills">' + renderAssessmentFilterButtons(false) + "</div>",
            '    <p class="exam-side-copy">در حال نمایش ' + escapeHtml(formatValue(visibleCount)) + ' سوال از این نما هستی.</p>',
            "  </div>",
            '  <div class="exam-side-section exam-side-section--actions">',
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="assessment-first-unanswered"' + (totals.unanswered <= 0 ? " disabled" : "") + ">اولین سوال بی‌پاسخ</button>",
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="reset-assessment-draft">پاک‌کردن پاسخ‌ها</button>',
            '    <button class="exam-btn exam-btn--primary" type="button" data-action="submit-assessment"' + (state.assessment.submitting || state.assessment.queued ? " disabled" : "") + ">" + escapeHtml(state.assessment.queued ? "در صف آفلاین" : (state.assessment.submitting ? "در حال ثبت..." : "ثبت آزمون")) + "</button>",
            "  </div>",
            "</aside>"
        ].join("");
    }

    function renderAssessmentReportSidePanel(report) {
        var extraStats = [];
        if (report.showRank) {
            extraStats.push(renderStatCard("رتبه", formatValue(report.rank) + " از " + formatValue(report.participantCount)));
        }
        if (report.overallAveragePercent !== null && report.overallAveragePercent !== undefined) {
            extraStats.push(renderStatCard("میانگین کل", formatPercent(report.overallAveragePercent)));
        }

        return [
            '<aside class="exam-session-card exam-session-card--side exam-session-card--report-side">',
            '  <div class="exam-report-score">',
            '    <span class="exam-report-score__label">درصد این آزمون</span>',
            '    <strong class="exam-report-score__value">' + escapeHtml(formatPercent(report.percent)) + "</strong>",
            '    <span class="exam-report-score__meta">ثبت شده در ' + escapeHtml(formatDateTime(report.submittedAt)) + "</span>",
            "  </div>",
            '  <div class="exam-side-section">',
            '    <div class="exam-stat-grid">',
            renderStatCard("صحیح", formatValue(report.correct)),
            renderStatCard("غلط", formatValue(report.wrong)),
            renderStatCard("بی‌پاسخ", formatValue(report.unanswered)),
            renderStatCard("نشان‌دار", formatValue(state.flags.size)),
            extraStats.join(""),
            "    </div>",
            "  </div>",
            '  <div class="exam-side-section">',
            '    <span class="exam-side-title">فیلتر مرور</span>',
            '    <div class="exam-filter-pills">' + renderAssessmentFilterButtons(true) + "</div>",
            "  </div>",
            '  <div class="exam-side-section exam-side-section--actions">',
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="set-mode" data-mode="learning">رفتن به آموزشی</button>',
            '    <button class="exam-btn exam-btn--danger" type="button" data-action="reset-assessment-report">ریست کارنامه</button>',
            "  </div>",
            "</aside>"
        ].join("");
    }

    function renderReportInsights(report) {
        var topics = Array.isArray(report.topicBreakdown) ? report.topicBreakdown : [];
        var history = state.attemptHistory.slice(0, 12);
        var mistakeCount = state.study.mistakeQuestionIndexes.length;
        var weakTopic = topics.slice().filter(function (topic) {
            return Number(topic && topic.total || 0) > 0;
        }).sort(function (a, b) {
            return Number(a.percent || 0) - Number(b.percent || 0);
        })[0] || null;
        return [
            '<section class="exam-report-insights" aria-label="تحلیل تکمیلی کارنامه">',
            '  <details class="exam-report-insight">',
            '    <summary><span><strong>تحلیل مبحثی</strong><small>' + escapeHtml(formatValue(topics.length) + " مبحث") + "</small></span><span>مشاهده</span></summary>",
            '    <div class="exam-topic-breakdown">' + (topics.length ? topics.map(renderTopicBreakdownRow).join("") : '<p class="exam-report-insight__empty">برای سؤال‌های این آزمون هنوز برچسب مبحثی جزئی ثبت نشده است.</p>') + "</div>",
            "  </details>",
            '  <details class="exam-report-insight">',
            '    <summary><span><strong>تاریخچه تلاش‌ها</strong><small>' + escapeHtml(formatValue(history.length) + " تلاش ذخیره‌شده") + "</small></span><span>مشاهده</span></summary>",
            '    <div class="exam-attempt-history">' + (history.length ? history.map(renderAttemptHistoryCard).join("") : '<p class="exam-report-insight__empty">هنوز تلاش قبلی ثبت نشده است.</p>') + "</div>",
            "  </details>",
            '  <details class="exam-report-insight">',
            '    <summary><span><strong>دفترچه اشتباهات</strong><small>' + escapeHtml(formatValue(mistakeCount) + " سؤال برای مرور") + "</small></span><span>مشاهده</span></summary>",
            '    <div class="exam-mistake-summary"><p>سؤال‌های غلط این تلاش خودکار به دفترچه اضافه می‌شوند و از ابزار هر سؤال هم می‌توانی موارد دلخواه را مدیریت کنی.</p>',
            mistakeCount ? '<button class="exam-btn exam-btn--primary" type="button" data-action="review-mistake-notebook">مرور سؤال‌های دفترچه</button>' : "",
            "    </div>",
            "  </details>",
            '  <details class="exam-report-insight exam-report-insight--recommendation" open>',
            '    <summary><span><strong>پیشنهاد مرور بعدی</strong><small>بر اساس همین کارنامه و سابقه تو</small></span><span>پیشنهاد شخصی</span></summary>',
            '    <div class="exam-next-review">',
            weakTopic ? '<p>اولویت پیشنهادی: مرور <strong>' + escapeHtml(String(weakTopic.label || "مبحث ضعیف‌تر")) + '</strong> با عملکرد ' + escapeHtml(formatPercent(weakTopic.percent)) + '.</p>' : '<p>برای پیشنهاد مبحثی دقیق‌تر، سؤال‌ها باید برچسب مبحث داشته باشند.</p>',
            '      <div>',
            weakTopic ? '<button class="exam-btn exam-btn--primary" type="button" data-action="review-weak-topic" data-topic="' + escapeHtml(String(weakTopic.label || "")) + '">تمرین مبحث ضعیف‌تر</button>' : "",
            mistakeCount ? '<button class="exam-btn exam-btn--ghost" type="button" data-action="review-mistake-notebook">مرور اشتباهات</button>' : "",
            '      </div>',
            '    </div>',
            '  </details>',
            "</section>"
        ].join("");
    }

    function renderTopicBreakdownRow(topic) {
        var percent = clampPercent(topic && topic.percent);
        return [
            '<article class="exam-topic-row">',
            '  <div class="exam-topic-row__head"><strong>' + escapeHtml(String(topic && topic.label || "مبحث")) + '</strong><span>' + escapeHtml(formatPercent(percent)) + "</span></div>",
            '  <span class="exam-topic-row__bar" aria-hidden="true"><span style="inline-size:' + escapeHtml(String(percent)) + '%"></span></span>',
            '  <p>' + escapeHtml(formatValue(topic && topic.correct || 0) + " صحیح • " + formatValue(topic && topic.wrong || 0) + " غلط • " + formatValue(topic && topic.unanswered || 0) + " بی‌پاسخ") + "</p>",
            "</article>"
        ].join("");
    }

    function renderAttemptHistoryCard(attempt, index) {
        var previous = state.attemptHistory[index + 1] || null;
        var delta = previous ? Math.round((Number(attempt.percent || 0) - Number(previous.percent || 0)) * 10) / 10 : null;
        return [
            '<article class="exam-attempt-card">',
            '  <div><span>تلاش ' + escapeHtml(formatValue(state.attemptHistory.length - index)) + '</span><strong>' + escapeHtml(formatPercent(attempt.percent)) + "</strong></div>",
            '  <p>' + escapeHtml(formatDateTime(attempt.submittedAt)) + "</p>",
            '  <small>' + escapeHtml(formatValue(attempt.correct) + " صحیح • " + formatValue(attempt.wrong) + " غلط" + (delta === null ? "" : " • تغییر " + formatSignedPercent(delta))) + "</small>",
            "</article>"
        ].join("");
    }

    function renderLearningSidePanel(stats, currentIndex, visibleCount) {
        return [
            '<aside class="exam-session-card exam-session-card--side">',
            '  <div class="exam-side-section">',
            '    <span class="exam-kicker">' + (exam.essayOnly || exam.learningOnly ? "مرور سوال‌ها" : "مرور آموزشی") + "</span>",
            '    <div class="exam-stat-grid">',
            renderStatCard("حل‌شده", formatValue(stats.answered)),
            renderStatCard("باقی‌مانده", formatValue(stats.unanswered)),
            renderStatCard("نشان‌دار", formatValue(state.flags.size)),
            renderStatCard("جاری", formatValue(currentIndex + 1)),
            "    </div>",
            "  </div>",
            '  <div class="exam-side-section">',
            '    <span class="exam-side-title">فیلتر نمایش</span>',
            '    <div class="exam-filter-pills">',
            renderLearningFilterButton("all", "\u0647\u0645\u0647", learningScopeCount()),
            renderLearningFilterButton("flagged", "\u0646\u0634\u0627\u0646\u200c\u062f\u0627\u0631", state.flags.size),
            renderLearningFilterButton("mistakes", "دفترچه اشتباهات", state.study.mistakeQuestionIndexes.length),
            "    </div>",
            '    <p class="exam-side-copy">در این نما ' + escapeHtml(formatValue(visibleCount)) + ' سوال قابل جابه‌جایی است.</p>',
            "  </div>",
            '  <div class="exam-side-section exam-side-section--actions">',
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="learning-jump-unanswered"' + (stats.unanswered <= 0 ? " disabled" : "") + ">اولین سوال بی‌پاسخ</button>",
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="reset-learning-progress">' + escapeHtml(exam.essayOnly || exam.learningOnly ? "شروع دوباره مرور" : "شروع دوباره آموزشی") + "</button>",
            Array.isArray(state.learning.customIndexes) ? '    <button class="exam-btn exam-btn--ghost" type="button" data-action="clear-custom-practice">پایان تمرین شخصی</button>' : "",
            canUseAssessmentMode() ? '    <button class="exam-btn exam-btn--primary" type="button" data-action="set-mode" data-mode="assessment">\u0631\u0641\u062a\u0646 \u0628\u0647 \u0633\u0646\u062c\u0634\u06cc</button>' : "",
            "  </div>",
            "</aside>"
        ].join("");
    }

    function renderAssessmentDraftCompactPanel(totals, visibleCount) {
        return [
            '<section class="exam-compact-panel exam-compact-panel--assessment" aria-label="' + escapeHtml("\u062e\u0644\u0627\u0635\u0647 \u062d\u0627\u0644\u062a \u0633\u0646\u062c\u0634\u06cc") + '">',
            '  <div class="exam-compact-toolbar">',
            '    <div class="exam-compact-summary">',
            renderCompactMetric("\u0628\u0627\u0642\u06cc", formatValue(totals.unanswered), totals.unanswered ? "warning" : "success"),
            renderCompactMetric("\u0646\u0634\u0627\u0646", formatValue(state.flags.size), state.flags.size ? "flagged" : "neutral"),
            "    </div>",
            '    <button class="exam-btn exam-btn--primary" type="button" data-action="submit-assessment"' + (state.assessment.submitting || state.assessment.queued ? " disabled" : "") + ">" + escapeHtml(state.assessment.queued ? "\u062f\u0631 \u0635\u0641 \u0622\u0641\u0644\u0627\u06cc\u0646" : (state.assessment.submitting ? "\u062f\u0631 \u062d\u0627\u0644 \u062b\u0628\u062a..." : "\u062b\u0628\u062a \u0633\u0646\u062c\u0634\u06cc")) + "</button>",
            '    <details class="exam-compact-tools">',
            '      <summary class="exam-compact-tools__summary">\u0627\u0628\u0632\u0627\u0631\u0647\u0627</summary>',
            '      <div class="exam-compact-tools__body">',
            '        <div class="exam-compact-stats">',
            renderCompactMetric("\u06a9\u0644", formatValue(exam.questions.length), "accent"),
            renderCompactMetric("\u067e\u0627\u0633\u062e", formatValue(totals.answered), totals.answered ? "success" : "neutral"),
            renderCompactMetric("\u0628\u06cc\u200c\u067e\u0627\u0633\u062e", formatValue(totals.unanswered), totals.unanswered ? "warning" : "success"),
            renderCompactMetric("\u0646\u0634\u0627\u0646", formatValue(state.flags.size), state.flags.size ? "flagged" : "neutral"),
            "        </div>",
            '        <div class="exam-compact-filter-row"><div class="exam-filter-pills">' + renderAssessmentFilterButtons(false) + "</div></div>",
            '        <button class="exam-btn exam-btn--ghost" type="button" data-action="assessment-first-unanswered"' + (totals.unanswered <= 0 ? " disabled" : "") + ">\u0627\u0648\u0644\u06cc\u0646 \u0628\u06cc\u200c\u067e\u0627\u0633\u062e</button>",
            '        <button class="exam-btn exam-btn--ghost" type="button" data-action="reset-assessment-draft">\u067e\u0627\u06a9 \u06a9\u0631\u062f\u0646 \u067e\u0627\u0633\u062e\u200c\u0647\u0627</button>',
            '        <span class="exam-compact-note">\u062f\u0631 \u0627\u06cc\u0646 \u0646\u0645\u0627 ' + escapeHtml(formatValue(visibleCount)) + ' \u0633\u0648\u0627\u0644 \u0642\u0627\u0628\u0644 \u0645\u0631\u0648\u0631 \u0627\u0633\u062a.</span>',
            "      </div>",
            "    </details>",
            "  </div>",
            "</section>"
        ].join("");
    }

    function renderAssessmentReportCompactPanel(report) {
        return [
            '<section class="exam-compact-panel exam-compact-panel--report" aria-label="' + escapeHtml("\u062e\u0644\u0627\u0635\u0647 \u06a9\u0627\u0631\u0646\u0627\u0645\u0647") + '">',
            '  <div class="exam-compact-toolbar exam-compact-toolbar--report">',
            '    <div class="exam-compact-summary">',
            renderCompactMetric("\u062f\u0631\u0635\u062f", formatPercent(report.percent), "accent"),
            renderCompactMetric("\u0635\u062d\u06cc\u062d", formatValue(report.correct), "success"),
            "    </div>",
            '    <button class="exam-btn exam-btn--ghost" type="button" data-action="set-mode" data-mode="learning">\u0622\u0645\u0648\u0632\u0634\u06cc</button>',
            "  </div>",
            '  <div class="exam-compact-tools__body exam-compact-tools__body--inline exam-compact-tools__body--report">',
            '    <div class="exam-compact-report-hero">',
            '      <div class="exam-compact-report-hero__copy">',
            '        <span class="exam-compact-report-hero__label">\u06a9\u0627\u0631\u0646\u0627\u0645\u0647 \u0630\u062e\u06cc\u0631\u0647\u200c\u0634\u062f\u0647</span>',
            '        <span class="exam-compact-report-hero__meta">' + escapeHtml(formatDateTime(report.submittedAt)) + "</span>",
            "      </div>",
            '      <strong class="exam-compact-report-hero__value">' + escapeHtml(formatPercent(report.percent)) + "</strong>",
            "    </div>",
            '    <div class="exam-compact-stats">',
            renderCompactMetric("\u0635\u062d\u06cc\u062d", formatValue(report.correct), "success"),
            renderCompactMetric("\u063a\u0644\u0637", formatValue(report.wrong), report.wrong ? "danger" : "neutral"),
            renderCompactMetric("\u0628\u06cc\u200c\u067e\u0627\u0633\u062e", formatValue(report.unanswered), report.unanswered ? "warning" : "neutral"),
            renderCompactMetric("\u0646\u0634\u0627\u0646", formatValue(state.flags.size), state.flags.size ? "flagged" : "neutral"),
            "    </div>",
            '    <div class="exam-compact-filter-row"><div class="exam-filter-pills">' + renderAssessmentFilterButtons(true) + "</div></div>",
            '    <button class="exam-btn exam-btn--danger" type="button" data-action="reset-assessment-report">\u0631\u06cc\u0633\u062a \u06a9\u0627\u0631\u0646\u0627\u0645\u0647</button>',
            "  </div>",
            "</section>"
        ].join("");
    }

    function renderLearningCompactPanel(stats, currentIndex) {
        return [
            '<section class="exam-compact-panel exam-compact-panel--learning" aria-label="' + escapeHtml("\u062e\u0644\u0627\u0635\u0647 \u062d\u0627\u0644\u062a \u0622\u0645\u0648\u0632\u0634\u06cc") + '">',
            '  <div class="exam-compact-toolbar">',
            '    <div class="exam-compact-summary">',
            renderCompactMetric("\u062c\u0627\u0631\u06cc", formatValue(currentIndex + 1), "accent"),
            renderCompactMetric("\u062d\u0644", formatValue(stats.answered), stats.answered ? "success" : "neutral"),
            "    </div>",
            canUseAssessmentMode() ? '    <button class="exam-btn exam-btn--primary" type="button" data-action="set-mode" data-mode="assessment">\u0633\u0646\u062c\u0634\u06cc</button>' : "",
            '    <details class="exam-compact-tools">',
            '      <summary class="exam-compact-tools__summary">\u0627\u0628\u0632\u0627\u0631\u0647\u0627</summary>',
            '      <div class="exam-compact-tools__body">',
            '        <div class="exam-compact-stats">',
            renderCompactMetric("\u062d\u0644\u200c\u0634\u062f\u0647", formatValue(stats.answered), stats.answered ? "success" : "neutral"),
            renderCompactMetric("\u0628\u0627\u0642\u06cc", formatValue(stats.unanswered), stats.unanswered ? "warning" : "success"),
            renderCompactMetric("\u0646\u0634\u0627\u0646", formatValue(state.flags.size), state.flags.size ? "flagged" : "neutral"),
            renderCompactMetric("\u062c\u0627\u0631\u06cc", formatValue(currentIndex + 1), "accent"),
            "        </div>",
            '        <div class="exam-compact-filter-row"><div class="exam-filter-pills">',
            renderLearningFilterButton("all", "\u0647\u0645\u0647", learningScopeCount()),
            renderLearningFilterButton("flagged", "\u0646\u0634\u0627\u0646\u200c\u062f\u0627\u0631", state.flags.size),
            renderLearningFilterButton("mistakes", "دفترچه اشتباهات", state.study.mistakeQuestionIndexes.length),
            "        </div></div>",
            '        <button class="exam-btn exam-btn--ghost" type="button" data-action="learning-jump-unanswered"' + (stats.unanswered <= 0 ? " disabled" : "") + ">\u0627\u0648\u0644\u06cc\u0646 \u0628\u06cc\u200c\u067e\u0627\u0633\u062e</button>",
            '        <button class="exam-btn exam-btn--ghost" type="button" data-action="reset-learning-progress">\u0634\u0631\u0648\u0639 \u062f\u0648\u0628\u0627\u0631\u0647</button>',
            Array.isArray(state.learning.customIndexes) ? '        <button class="exam-btn exam-btn--ghost" type="button" data-action="clear-custom-practice">پایان تمرین شخصی</button>' : "",
            "      </div>",
            "    </details>",
            "  </div>",
            "</section>"
        ].join("");
    }

    function renderCompactMetric(label, value, tone) {
        return [
            '<article class="exam-compact-metric' + (tone ? " is-" + escapeHtml(tone) : "") + '">',
            '  <span class="exam-compact-metric__label">' + escapeHtml(label) + "</span>",
            '  <strong class="exam-compact-metric__value">' + escapeHtml(value) + "</strong>",
            "</article>"
        ].join("");
    }

    function renderResponsiveLabel(fullLabel, compactLabel) {
        return [
            '<span class="exam-label exam-label--full">' + escapeHtml(fullLabel) + "</span>",
            '<span class="exam-label exam-label--compact">' + escapeHtml(compactLabel) + "</span>"
        ].join("");
    }

    function renderAssessmentFilterButtons(reviewMode) {
        var filters = reviewMode
            ? [
                { key: "all", label: "همه" },
                { key: "correct", label: "صحیح" },
                { key: "wrong", label: "غلط" },
                { key: "unanswered", label: "بی‌پاسخ" },
                { key: "flagged", label: "نشان‌دار" },
                { key: "mistakes", label: "دفترچه اشتباهات" }
            ]
            : [
                { key: "all", label: "همه" },
                { key: "answered", label: "پاسخ‌داده" },
                { key: "unanswered", label: "بی‌پاسخ" },
                { key: "flagged", label: "نشان‌دار" }
            ];

        return filters.map(function (item) {
            return '<button class="exam-filter-pill' + (state.assessment.filter === item.key ? " is-active" : "") + '" type="button" data-action="assessment-filter" data-filter="' + escapeHtml(item.key) + '">' + escapeHtml(item.label) + "</button>";
        }).join("");
    }

    function renderLearningFilterButton(filter, label, count) {
        return '<button class="exam-filter-pill' + (state.learning.filter === filter ? " is-active" : "") + '" type="button" data-action="learning-filter" data-filter="' + escapeHtml(filter) + '">' + escapeHtml(label) + ' <span>' + escapeHtml(formatValue(count)) + "</span></button>";
    }

    function renderAssessmentOption(questionIndex, optionIndex, optionText, selectedIndex, correctIndex, reviewMode) {
        var stateName = "neutral";
        var tag = "";
        var resolvedAnswer = hasResolvedCorrectIndex(correctIndex);
        var locked = !reviewMode && timerExpiryHandled && exam.durationMinutes > 0;
        var struck = isOptionStruck(questionIndex, optionIndex);

        if (reviewMode) {
            if (!resolvedAnswer) {
                if (selectedIndex === optionIndex) {
                    stateName = "selected";
                    tag = "انتخاب تو";
                }
            } else if (optionIndex === correctIndex && selectedIndex === optionIndex) {
                stateName = "user-correct";
                tag = "پاسخ درست تو";
            } else if (optionIndex === correctIndex) {
                stateName = "correct";
                tag = "پاسخ درست";
            } else if (selectedIndex === optionIndex) {
                stateName = "user-wrong";
                tag = "انتخاب تو";
            }
        } else if (selectedIndex === optionIndex) {
            stateName = "selected";
        }

        return [
            '<div class="exam-option-row' + (struck ? " is-struck" : "") + '">',
            '<button class="exam-option' + (selectedIndex === optionIndex ? " is-selected" : "") + (reviewMode ? " is-results-mode" : "") + '" type="button" role="radio" aria-checked="' + (selectedIndex === optionIndex ? "true" : "false") + '" data-action="' + (reviewMode || locked ? "noop" : "assessment-answer") + '" data-question-index="' + escapeHtml(String(questionIndex)) + '" data-option-index="' + escapeHtml(String(optionIndex)) + '" data-state="' + escapeHtml(stateName) + '"' + (reviewMode || locked ? " disabled" : "") + ">",
            '  <span class="exam-option-marker">' + escapeHtml(optionLetter(optionIndex)) + "</span>",
            '  <span class="exam-option-copy">' + richTextHtml(optionText) + "</span>",
            tag ? '  <span class="exam-option-tag">' + escapeHtml(tag) + "</span>" : "",
            "</button>",
            '<button class="exam-option-strike" type="button" data-action="toggle-option-strike" data-question-index="' + escapeHtml(String(questionIndex)) + '" data-option-index="' + escapeHtml(String(optionIndex)) + '" aria-pressed="' + (struck ? "true" : "false") + '" aria-label="' + escapeHtml((struck ? "برداشتن خط از" : "خط زدن") + " گزینه " + optionLetter(optionIndex)) + '">' + (struck ? "↶" : "×") + "</button>",
            "</div>"
        ].join("");
    }

    function renderLearningOption(questionIndex, optionIndex, optionText, selectedIndex, correctIndex, revealed) {
        var stateName = "neutral";
        var tag = "";
        var resolvedAnswer = hasResolvedCorrectIndex(correctIndex);
        var struck = isOptionStruck(questionIndex, optionIndex);

        if (revealed) {
            if (!resolvedAnswer) {
                if (selectedIndex === optionIndex) {
                    stateName = "selected";
                    tag = "انتخاب تو";
                }
            } else if (optionIndex === correctIndex && selectedIndex === optionIndex) {
                stateName = "user-correct";
                tag = "پاسخ درست تو";
            } else if (optionIndex === correctIndex) {
                stateName = "correct";
                tag = "پاسخ درست";
            } else if (selectedIndex === optionIndex) {
                stateName = "user-wrong";
                tag = "انتخاب تو";
            }
        } else if (selectedIndex === optionIndex) {
            stateName = "selected";
        }

        return [
            '<div class="exam-option-row' + (struck ? " is-struck" : "") + '">',
            '<button class="exam-option' + (selectedIndex === optionIndex ? " is-selected" : "") + (revealed ? " is-results-mode" : "") + '" type="button" role="radio" aria-checked="' + (selectedIndex === optionIndex ? "true" : "false") + '" data-action="' + (revealed ? "noop" : "learning-answer") + '" data-question-index="' + escapeHtml(String(questionIndex)) + '" data-option-index="' + escapeHtml(String(optionIndex)) + '" data-state="' + escapeHtml(stateName) + '"' + (revealed ? " disabled" : "") + ">",
            '  <span class="exam-option-marker">' + escapeHtml(optionLetter(optionIndex)) + "</span>",
            '  <span class="exam-option-copy">' + richTextHtml(optionText) + "</span>",
            tag ? '  <span class="exam-option-tag">' + escapeHtml(tag) + "</span>" : "",
            "</button>",
            '<button class="exam-option-strike" type="button" data-action="toggle-option-strike" data-question-index="' + escapeHtml(String(questionIndex)) + '" data-option-index="' + escapeHtml(String(optionIndex)) + '" aria-pressed="' + (struck ? "true" : "false") + '" aria-label="' + escapeHtml((struck ? "برداشتن خط از" : "خط زدن") + " گزینه " + optionLetter(optionIndex)) + '">' + (struck ? "↶" : "×") + "</button>",
            "</div>"
        ].join("");
    }

    function renderAnswerMeta(question) {
        if (!question.answerMeta.length) {
            return "";
        }

        return '<div class="exam-answer-card__facts">' + question.answerMeta.map(function (item) {
            return [
                '<article class="exam-answer-card__fact' + (item.tone ? " is-" + escapeHtml(item.tone) : "") + (item.wide ? " is-wide" : "") + '">',
                '  <span class="exam-answer-card__fact-label">' + escapeHtml(item.label) + "</span>",
                '  <div class="exam-answer-card__fact-value">' + richTextHtml(item.value) + "</div>",
                "</article>"
            ].join("");
        }).join("") + "</div>";
    }

    function renderAnswerDetailsContent(question, fallbackHtml) {
        return [
            renderAnswerMeta(question),
            question.answerSections.length
                ? renderAnswerSections(question.answerSections)
                : (question.explanation ? '<div class="exam-answer-card__body">' + richTextHtml(question.explanation) + "</div>" : (fallbackHtml || "")),
            question.reference ? '<p class="exam-answer-card__reference">' + richTextHtml(question.reference) + "</p>" : ""
        ].filter(Boolean).join("");
    }

    function renderAnswerSections(sections) {
        if (!Array.isArray(sections) || !sections.length) {
            return "";
        }

        return sections.map(function (section) {
            return [
                '<div class="exam-answer-card__section' + (section.tone ? " is-" + escapeHtml(section.tone) : "") + '">',
                '  <span class="exam-answer-card__section-label">' + escapeHtml(section.label || "توضیح") + "</span>",
                '  <div class="exam-answer-card__section-copy">' + richTextHtml(section.value || "") + "</div>",
                "</div>"
            ].join("");
        }).join("");
    }

    function renderMcqAnswerCard(question, selectedIndex, options) {
        if (!question.explanation && !question.answerMeta.length && !question.answerSections.length && !question.reference) {
            return "";
        }

        var config = options || {};
        var resolvedAnswer = hasResolvedCorrectIndex(question.correctIndex);
        var isCorrect = resolvedAnswer && selectedIndex === question.correctIndex;
        var cardClassName = "exam-answer-card";
        var label = config.label || "پاسخ تشریحی";
        var fallbackCopy = "";

        if (!resolvedAnswer) {
            label = config.unresolvedLabel || "کلید قطعی این سؤال در فایل منبع موجود نیست";
            cardClassName += " is-warning";
            fallbackCopy = '  <p class="exam-answer-card__copy">' + escapeHtml("برای این سؤال، در فایل منبع پاسخ قطعی ثبت نشده است.") + "</p>";
        } else if (selectedIndex !== null) {
            cardClassName += isCorrect ? " is-correct" : " is-warning";
            label = config.label || (isCorrect ? "پاسخ تو درست بود" : "پاسخ صحیح مشخص شد");
            fallbackCopy = '  <p class="exam-answer-card__copy">' + escapeHtml("پاسخ صحیح این سؤال گزینه " + optionLetter(question.correctIndex) + " است.") + "</p>";
        }

        return [
            '<div class="' + cardClassName + '">',
            '  <span class="exam-answer-card__label">' + escapeHtml(label) + "</span>",
            renderAnswerDetailsContent(question, fallbackCopy),
            renderDistractorAnalysis(question, selectedIndex),
            config.hintHtml || "",
            "</div>"
        ].join("");
    }

    function renderEssayAnswerCard(question) {
        var sections = [];
        var structuredSections = renderAnswerSections(question.answerSections);

        if (question.answerSummary) {
            sections.push([
                '  <div class="exam-answer-card__section">',
                '    <span class="exam-answer-card__section-label">پاسخ</span>',
                '    <div class="exam-answer-card__section-copy">' + richTextHtml(question.answerSummary) + "</div>",
                "  </div>"
            ].join(""));
        }

        if (question.answerDetail) {
            sections.push([
                '  <div class="exam-answer-card__section">',
                '    <span class="exam-answer-card__section-label">پاسخ تشریحی</span>',
                '    <div class="exam-answer-card__section-copy">' + richTextHtml(question.answerDetail) + "</div>",
                "  </div>"
            ].join(""));
        }

        if (structuredSections) {
            sections.push(structuredSections);
        }

        if (!sections.length && question.explanation) {
            sections.push('  <div class="exam-answer-card__section-copy">' + richTextHtml(question.explanation) + "</div>");
        }

        return [
            '<div class="exam-answer-card exam-answer-card--essay">',
            '  <span class="exam-answer-card__label">' + escapeHtml(question.answerCardLabel || exam.answerCardLabel || "پاسخ تشریحی") + "</span>",
            renderAnswerMeta(question),
            sections.join(""),
            question.reference ? '<p class="exam-answer-card__reference">' + richTextHtml(question.reference) + "</p>" : "",
            "</div>"
        ].join("");
    }

    function renderLearningFeedback(question, selectedIndex, questionIndex) {
        return [
            renderMcqAnswerCard(question, selectedIndex, {
                unresolvedLabel: "کلید قطعی این سؤال در فایل منبع موجود نیست",
                hintHtml: isFlagged(questionIndex) ? '<span class="exam-answer-card__hint">این سوال نشان‌دار شده و بعداً سریع پیدایش می‌کنی.</span>' : ""
            })
        ].filter(Boolean).join("");
    }

    function renderDistractorAnalysis(question, selectedIndex) {
        if (!Array.isArray(question.optionRationales) || !question.optionRationales.some(Boolean)) {
            return "";
        }
        var rows = question.options.map(function (option, optionIndex) {
            var rationale = String(question.optionRationales[optionIndex] || "").trim();
            if (!rationale || optionIndex === question.correctIndex) {
                return "";
            }
            return [
                '<article class="exam-distractor-row' + (selectedIndex === optionIndex ? " is-selected" : "") + '">',
                '  <span class="exam-distractor-row__label">گزینه ' + escapeHtml(optionLetter(optionIndex)) + (selectedIndex === optionIndex ? " • انتخاب تو" : "") + "</span>",
                '  <p><strong>' + richTextHtml(option) + "</strong><br>" + richTextHtml(rationale) + "</p>",
                "</article>"
            ].join("");
        }).filter(Boolean);
        if (!rows.length) {
            return "";
        }
        return [
            '<details class="exam-distractor-analysis"' + (selectedIndex !== null && selectedIndex !== question.correctIndex ? " open" : "") + '>',
            '  <summary>چرا گزینه‌های دیگر درست نیستند؟</summary>',
            '  <div class="exam-distractor-analysis__body">' + rows.join("") + "</div>",
            "</details>"
        ].join("");
    }

    function renderStageEmptyState(copy) {
        return [
            '<section class="exam-empty-card">',
            '  <h3>نمایش خالی شد</h3>',
            '  <p>' + escapeHtml(copy) + "</p>",
            '  <button class="exam-btn exam-btn--ghost" type="button" data-action="clear-filters">حذف فیلترها</button>',
            "</section>"
        ].join("");
    }

    function renderSessionDialog() {
        if (state.layout.mediaViewer) {
            return renderMediaViewerDialog();
        }
        if (state.layout.finalReviewOpen) {
            return renderFinalReviewDialog();
        }
        if (state.layout.questionMapOpen) {
            return renderQuestionMapDialog();
        }
        return "";
    }

    function renderMediaViewerDialog() {
        var viewer = state.layout.mediaViewer;
        var question = exam.questions[viewer.questionIndex];
        var media = question && question.media[viewer.mediaIndex];
        if (!media) {
            state.layout.mediaViewer = null;
            return "";
        }
        var transform = "scale(" + viewer.zoom + ") rotate(" + viewer.rotation + "deg)";
        return [
            '<div class="exam-dialog-backdrop exam-dialog-backdrop--media">',
            '  <section class="exam-media-viewer" role="dialog" aria-modal="true" aria-labelledby="exam-media-title" data-session-dialog tabindex="-1">',
            '    <header class="exam-media-viewer__head">',
            '      <div><span>تصویر سؤال ' + escapeHtml(formatValue(viewer.questionIndex + 1)) + '</span><h2 id="exam-media-title">' + escapeHtml(media.caption || media.alt) + '</h2></div>',
            '      <button type="button" data-action="close-media-viewer" aria-label="بستن نمایشگر تصویر">×</button>',
            '    </header>',
            '    <div class="exam-media-viewer__canvas"><img src="' + escapeHtml(media.src) + '" alt="' + escapeHtml(media.alt) + '" style="transform:' + escapeHtml(transform) + '"></div>',
            '    <footer class="exam-media-viewer__tools" aria-label="ابزار تصویر">',
            '      <button type="button" data-action="media-zoom-out"' + (viewer.zoom <= 0.75 ? " disabled" : "") + '>− کوچک‌نمایی</button>',
            '      <output>' + escapeHtml(formatValue(Math.round(viewer.zoom * 100)) + "٪") + '</output>',
            '      <button type="button" data-action="media-zoom-in"' + (viewer.zoom >= 3 ? " disabled" : "") + '>+ بزرگ‌نمایی</button>',
            '      <button type="button" data-action="media-rotate">چرخش ۹۰°</button>',
            '      <button type="button" data-action="media-reset">بازنشانی</button>',
            '    </footer>',
            '  </section>',
            '</div>'
        ].join("");
    }

    function renderQuestionMapDialog() {
        var answered = state.mode === "assessment" ? assessmentDraftTotals().answered : learningTotals().answered;
        return [
            '<div class="exam-dialog-backdrop">',
            '  <section class="exam-session-dialog exam-session-dialog--map" role="dialog" aria-modal="true" aria-labelledby="exam-map-title" data-session-dialog tabindex="-1">',
            '    <header class="exam-session-dialog__head">',
            '      <div><span class="exam-session-dialog__eyebrow">نمای کلی</span><h2 id="exam-map-title">نقشه سؤال‌ها</h2></div>',
            '      <button class="exam-session-dialog__close" type="button" data-action="close-session-dialog" aria-label="بستن نقشه سؤال‌ها">×</button>',
            "    </header>",
            '    <p class="exam-session-dialog__copy">' + escapeHtml(formatValue(answered) + " پاسخ از " + formatValue(exam.questions.length) + " سؤال") + "</p>",
            '    <div class="exam-map-legend" aria-label="راهنمای وضعیت سؤال‌ها">',
            '      <span><i class="is-answered"></i>پاسخ‌داده</span>',
            '      <span><i class="is-unanswered"></i>بی‌پاسخ</span>',
            '      <span><i class="is-flagged"></i>نشان‌دار</span>',
            "    </div>",
            '    <div class="exam-question-map">' + exam.questions.map(function (_question, index) {
                return renderMapQuestionButton(index);
            }).join("") + "</div>",
            '    <footer class="exam-session-dialog__actions">',
            renderFirstUnansweredDialogButton(),
            '      <button class="exam-btn exam-btn--primary" type="button" data-action="close-session-dialog">ادامه آزمون</button>',
            "    </footer>",
            "  </section>",
            "</div>"
        ].join("");
    }

    function renderFinalReviewDialog() {
        var totals = assessmentDraftTotals();
        return [
            '<div class="exam-dialog-backdrop">',
            '  <section class="exam-session-dialog exam-session-dialog--review" role="dialog" aria-modal="true" aria-labelledby="exam-review-title" data-session-dialog tabindex="-1">',
            '    <header class="exam-session-dialog__head">',
            '      <div><span class="exam-session-dialog__eyebrow">پیش از ثبت نهایی</span><h2 id="exam-review-title">مرور وضعیت آزمون</h2></div>',
            '      <button class="exam-session-dialog__close" type="button" data-action="close-session-dialog" aria-label="بازگشت به آزمون">×</button>',
            "    </header>",
            timerExpiryHandled ? '<div class="exam-review-alert">زمان تعیین‌شده آزمون به پایان رسیده است. پاسخ‌های فعلی را مرور و ثبت کن.</div>' : "",
            '    <div class="exam-review-summary">',
            renderReviewMetric("پاسخ‌داده", totals.answered, "answered"),
            renderReviewMetric("بی‌پاسخ", totals.unanswered, totals.unanswered ? "unanswered" : "complete"),
            renderReviewMetric("نشان‌دار", state.flags.size, "flagged"),
            renderReviewMetric(exam.durationMinutes > 0 ? "زمان" : "مدت", timerValue(), "time", true),
            "    </div>",
            '    <div class="exam-question-map exam-question-map--review">' + exam.questions.map(function (_question, index) {
                return renderMapQuestionButton(index);
            }).join("") + "</div>",
            '    <p class="exam-session-dialog__note">بعد از ثبت نهایی، پاسخ‌ها قفل می‌شوند و کارنامه نمایش داده خواهد شد.</p>',
            '    <footer class="exam-session-dialog__actions">',
            totals.unanswered > 0
                ? '      <button class="exam-btn exam-btn--ghost" type="button" data-action="review-first-unanswered">رفتن به اولین بی‌پاسخ</button>'
                : '      <button class="exam-btn exam-btn--ghost" type="button" data-action="close-session-dialog">بازگشت و بررسی</button>',
            '      <button class="exam-btn exam-btn--danger exam-btn--submit-final" type="button" data-action="confirm-submit-assessment"' + (state.assessment.submitting || state.assessment.queued ? " disabled" : "") + ">ثبت نهایی آزمون</button>",
            "    </footer>",
            "  </section>",
            "</div>"
        ].join("");
    }

    function renderReviewMetric(label, value, tone, isText) {
        return [
            '<article class="exam-review-metric is-' + escapeHtml(tone || "neutral") + '">',
            '  <span>' + escapeHtml(label) + "</span>",
            '  <strong>' + escapeHtml(isText ? String(value) : formatValue(value)) + "</strong>",
            "</article>"
        ].join("");
    }

    function renderMapQuestionButton(index) {
        var stateName = questionMapState(index);
        var currentIndex = state.mode === "learning"
            ? state.learning.currentQuestionIndex
            : state.assessment.currentQuestionIndex;
        var className = "exam-map-question is-" + stateName;
        if (isFlagged(index)) {
            className += " is-flagged";
        }
        if (index === currentIndex) {
            className += " is-current";
        }
        return '<button class="' + escapeHtml(className) + '" type="button" data-action="map-jump-question" data-question-index="' + escapeHtml(String(index)) + '" aria-label="سؤال ' + escapeHtml(formatValue(index + 1) + "، " + questionMapStateLabel(stateName) + (isFlagged(index) ? "، نشان‌دار" : "")) + '">' + escapeHtml(formatValue(index + 1)) + "</button>";
    }

    function renderFirstUnansweredDialogButton() {
        var hasUnanswered = state.mode === "assessment"
            ? assessmentDraftTotals().unanswered > 0
            : learningTotals().unanswered > 0;
        if (!hasUnanswered) {
            return "";
        }
        return '      <button class="exam-btn exam-btn--ghost" type="button" data-action="review-first-unanswered">اولین سؤال بی‌پاسخ</button>';
    }

    function questionMapState(index) {
        if (state.mode === "learning") {
            return learningNavState(index) === "unanswered" ? "unanswered" : "answered";
        }
        return assessmentNavState(index) === "unanswered" ? "unanswered" : "answered";
    }

    function questionMapStateLabel(stateName) {
        return stateName === "answered" ? "پاسخ‌داده‌شده" : "بی‌پاسخ";
    }

    function renderMetaChip(text, kind) {
        return '<span class="exam-meta-chip' + (kind ? " is-" + escapeHtml(kind) : "") + '">' + escapeHtml(text) + "</span>";
    }

    function renderMiniStat(label, value) {
        return '<span class="exam-mini-stat"><strong>' + escapeHtml(label) + "</strong><span>" + escapeHtml(value) + "</span></span>";
    }

    function renderStatCard(label, value) {
        return [
            '<article class="exam-stat-card">',
            '  <span class="exam-stat-card__label">' + escapeHtml(label) + "</span>",
            '  <strong class="exam-stat-card__value">' + escapeHtml(value) + "</strong>",
            "</article>"
        ].join("");
    }

    function ownerMetricValue(value, formatter) {
        if (value === null || value === undefined || value === "") {
            return "—";
        }
        if (typeof formatter === "function") {
            return formatter(value);
        }
        return formatValue(value);
    }

    function renderOwnerInsightMetric(label, value, tone, meta) {
        return [
            '<article class="exam-owner-metric' + (tone ? " is-" + escapeHtml(tone) : "") + '">',
            '  <span class="exam-owner-metric__label">' + escapeHtml(label) + "</span>",
            '  <strong class="exam-owner-metric__value">' + escapeHtml(value) + "</strong>",
            meta ? '  <small class="exam-owner-metric__meta">' + escapeHtml(meta) + "</small>" : "",
            "</article>"
        ].join("");
    }

    function renderOwnerParticipantMetric(label, value, tone, formatter) {
        return [
            '<div class="exam-owner-row__metric' + (tone ? " is-" + escapeHtml(tone) : "") + '">',
            '  <span>' + escapeHtml(label) + "</span>",
            '  <strong>' + escapeHtml(ownerMetricValue(value, formatter)) + "</strong>",
            "</div>"
        ].join("");
    }

    function renderOwnerParticipantRow(entry) {
        var flagsCount = Number(entry && entry.flagsCount || 0);
        var metaParts = [
            String(entry && entry.roleLabel || "").trim(),
            entry && entry.lastActivityAt ? ("آخرین فعالیت " + formatDateTime(entry.lastActivityAt)) : ""
        ].filter(Boolean);
        if (flagsCount > 0) {
            metaParts.push("نشان‌دار " + formatValue(flagsCount));
        }

        return [
            '<article class="exam-owner-row">',
            '  <div class="exam-owner-row__identity">',
            '    <div class="exam-owner-row__name-wrap">',
            '      <strong class="exam-owner-row__name">' + escapeHtml(String(entry && entry.name || "کاربر")) + "</strong>",
            '      <span class="exam-owner-row__type">' + escapeHtml(String(entry && entry.typeLabel || "—")) + "</span>",
            "    </div>",
            '    <span class="exam-owner-row__student" dir="ltr" data-latin-digits="true">' + escapeHtml(String(entry && entry.studentNumber || "—")) + "</span>",
            metaParts.length ? ('    <p class="exam-owner-row__meta">' + escapeHtml(metaParts.join(" • ")) + "</p>") : "",
            "  </div>",
            '  <div class="exam-owner-row__metrics">',
            renderOwnerParticipantMetric("درصد", entry && entry.percent, entry && entry.percent !== null ? "accent" : "", formatPercent),
            renderOwnerParticipantMetric("صحیح", entry && entry.correct, entry && entry.correct !== null ? "success" : ""),
            renderOwnerParticipantMetric("غلط", entry && entry.wrong, entry && entry.wrong !== null ? "danger" : ""),
            renderOwnerParticipantMetric("رتبه", entry && entry.rank, entry && entry.rank !== null ? "warning" : ""),
            renderOwnerParticipantMetric("کل آزمون‌ها", entry && entry.overallExamCount, "soft"),
            renderOwnerParticipantMetric("خرید آزمون", entry && entry.purchasedExamCount, entry && Number(entry.purchasedExamCount || 0) > 0 ? "success" : "soft")
            + "  </div>",
            "</article>"
        ].join("");
    }

    function renderOwnerInsightsPanel() {
        var insights = exam.ownerInsights;
        if (!insights || !insights.canView) {
            return "";
        }

        var summary = insights.summary || {};
        var participants = Array.isArray(insights.participants) ? insights.participants : [];
        var averagePercent = summary.averagePercent === null || summary.averagePercent === undefined
            ? "—"
            : formatPercent(summary.averagePercent);

        return [
            '<details class="exam-owner-panel"' + (state.ownerPanelOpen ? " open" : "") + ">",
            '  <summary class="exam-owner-panel__summary">',
            '    <div class="exam-owner-panel__summary-copy">',
            '      <span class="exam-owner-panel__eyebrow">فقط برای مالک</span>',
            '      <strong class="exam-owner-panel__title">تابلوی شرکت‌کننده‌های این آزمون</strong>',
            '      <span class="exam-owner-panel__meta">' + escapeHtml(formatValue(summary.participantCount || 0) + " نفر • " + formatValue(summary.assessmentCount || 0) + " کارنامه سنجشی") + "</span>",
            "    </div>",
            '    <span class="exam-owner-panel__hint">لیست و رتبه‌بندی</span>',
            "  </summary>",
            '  <div class="exam-owner-panel__body">',
            '    <div class="exam-owner-panel__metrics">',
            renderOwnerInsightMetric("شرکت‌کننده", ownerMetricValue(summary.participantCount || 0), "soft", "شروع‌های ثبت‌شده در این جلسه"),
            renderOwnerInsightMetric("کارنامه سنجشی", ownerMetricValue(summary.assessmentCount || 0), "accent", "فقط ردیف‌های دارای درصد و رتبه"),
            renderOwnerInsightMetric("میانگین درصد", averagePercent, summary.averagePercent !== null && summary.averagePercent !== undefined ? "success" : "soft", "بر اساس کارنامه‌های سنجشی این جلسه"),
            renderOwnerInsightMetric("خرید آزمون", ownerMetricValue(summary.paidParticipantCount || 0), Number(summary.paidParticipantCount || 0) > 0 ? "warning" : "soft", "تعداد شرکت‌کننده‌هایی که در سایت درس‌های آزمون خریده‌اند"),
            "    </div>",
            participants.length
                ? ('    <div class="exam-owner-board">' + participants.map(function (entry) {
                    return renderOwnerParticipantRow(entry);
                }).join("") + "</div>")
                : '    <div class="exam-owner-empty">هنوز شرکت‌کننده‌ای برای این جلسه ثبت نشده است.</div>',
            "  </div>",
            "</details>"
        ].join("");
    }

    function handleClick(event) {
        var actionNode = event.target.closest("[data-action]");
        if (!actionNode) {
            return;
        }

        var action = String(actionNode.getAttribute("data-action") || "").trim();
        if (!action || action === "noop") {
            return;
        }

        if (action === "set-mode") {
            setMode(normalizeMode(actionNode.getAttribute("data-mode")));
            return;
        }
        if (action === "start-mode") {
            startMode(normalizeMode(actionNode.getAttribute("data-mode")) || state.mode);
            return;
        }
        if (action === "toggle-chooser-hint") {
            state.layout.chooserHintExpanded = !state.layout.chooserHintExpanded;
            render();
            return;
        }
        if (action === "open-question-map") {
            state.layout.questionMapOpen = true;
            state.layout.finalReviewOpen = false;
            render();
            return;
        }
        if (action === "open-question-media") {
            openMediaViewer(
                parseIndex(actionNode.getAttribute("data-question-index")),
                parseIndex(actionNode.getAttribute("data-media-index"))
            );
            return;
        }
        if (action === "close-media-viewer") {
            state.layout.mediaViewer = null;
            render();
            return;
        }
        if (action === "media-zoom-in" || action === "media-zoom-out" || action === "media-rotate" || action === "media-reset") {
            updateMediaViewer(action);
            return;
        }
        if (action === "close-session-dialog") {
            closeSessionDialog();
            return;
        }
        if (action === "leave-focus-mode") {
            leaveFocusMode();
            return;
        }
        if (action === "map-jump-question") {
            jumpFromQuestionMap(parseIndex(actionNode.getAttribute("data-question-index")));
            return;
        }
        if (action === "review-first-unanswered") {
            jumpFromDialogToFirstUnanswered();
            return;
        }
        if (action === "confirm-submit-assessment") {
            performAssessmentSubmission();
            return;
        }
        if (action === "toggle-flag") {
            toggleFlag(parseIndex(actionNode.getAttribute("data-question-index")));
            return;
        }
        if (action === "toggle-option-strike") {
            toggleOptionStrike(
                parseIndex(actionNode.getAttribute("data-question-index")),
                parseIndex(actionNode.getAttribute("data-option-index"))
            );
            return;
        }
        if (action === "add-question-highlight") {
            addPendingQuestionHighlight(parseIndex(actionNode.getAttribute("data-question-index")));
            return;
        }
        if (action === "clear-question-highlights") {
            clearQuestionHighlights(parseIndex(actionNode.getAttribute("data-question-index")));
            return;
        }
        if (action === "toggle-mistake-notebook") {
            toggleMistakeNotebook(parseIndex(actionNode.getAttribute("data-question-index")));
            return;
        }
        if (action === "review-mistake-notebook") {
            state.mode = "learning";
            state.learning.filter = "mistakes";
            state.learning.started = true;
            ensureLearningIndex(learningVisibleIndexes());
            persistLearningState();
            render();
            return;
        }
        if (action === "review-weak-topic") {
            startWeakTopicReview(String(actionNode.getAttribute("data-topic") || ""));
            return;
        }
        if (action === "build-custom-practice") {
            buildCustomPractice();
            return;
        }
        if (action === "clear-custom-practice") {
            state.learning.customIndexes = null;
            state.learning.customLabel = "";
            state.learning.filter = "all";
            persistLearningState();
            render();
            return;
        }
        if (action === "review-mistake-notebook-assessment") {
            state.assessment.filter = "mistakes";
            ensureAssessmentIndex(assessmentVisibleIndexes("mistakes"));
            persistAssessmentState();
            render();
            return;
        }
        if (action === "assessment-filter") {
            state.assessment.filter = String(actionNode.getAttribute("data-filter") || "all");
            ensureAssessmentIndex(assessmentVisibleIndexes(state.assessment.filter));
            persistAssessmentState();
            render();
            return;
        }
        if (action === "learning-filter") {
            state.learning.filter = String(actionNode.getAttribute("data-filter") || "all");
            ensureLearningIndex(learningVisibleIndexes());
            persistLearningState();
            render();
            return;
        }
        if (action === "assessment-answer") {
            selectAssessmentAnswer(
                parseIndex(actionNode.getAttribute("data-question-index")),
                parseIndex(actionNode.getAttribute("data-option-index"))
            );
            return;
        }
        if (action === "learning-answer") {
            selectLearningAnswer(
                parseIndex(actionNode.getAttribute("data-question-index")),
                parseIndex(actionNode.getAttribute("data-option-index"))
            );
            return;
        }
        if (action === "learning-reveal-essay") {
            revealEssayAnswer(parseIndex(actionNode.getAttribute("data-question-index")));
            return;
        }
        if (action === "reset-assessment-draft") {
            resetAssessmentDraft();
            return;
        }
        if (action === "submit-assessment") {
            submitAssessment();
            return;
        }
        if (action === "reset-assessment-report") {
            resetAssessmentReport();
            return;
        }
        if (action === "assessment-prev") {
            moveAssessment(-1);
            return;
        }
        if (action === "assessment-next") {
            moveAssessment(1);
            return;
        }
        if (action === "assessment-first-unanswered") {
            jumpAssessmentToFirstUnanswered();
            return;
        }
        if (action === "jump-to-question") {
            jumpToAssessmentQuestion(parseIndex(actionNode.getAttribute("data-question-index")));
            return;
        }
        if (action === "learning-goto") {
            jumpToLearningQuestion(parseIndex(actionNode.getAttribute("data-question-index")));
            return;
        }
        if (action === "learning-next") {
            moveLearning(1);
            return;
        }
        if (action === "learning-prev") {
            moveLearning(-1);
            return;
        }
        if (action === "learning-jump-unanswered") {
            jumpLearningToFirstUnanswered();
            return;
        }
        if (action === "reset-learning-progress") {
            resetLearningProgress();
            return;
        }
        if (action === "clear-filters") {
            state.assessment.filter = "all";
            state.learning.filter = "all";
            persistAssessmentState();
            persistLearningState();
            render();
        }
    }

    function handleChange(_event) {
        return;
    }

    function handleInput(event) {
        var noteNode = event.target && event.target.closest ? event.target.closest("[data-study-note]") : null;
        if (!noteNode) {
            return;
        }
        var questionIndex = parseIndex(noteNode.getAttribute("data-question-index"));
        if (!isValidQuestionIndex(questionIndex)) {
            return;
        }
        var note = String(noteNode.value || "").slice(0, 4000).trimStart();
        if (note) {
            state.study.notesByQuestion[String(questionIndex)] = note;
        } else {
            delete state.study.notesByQuestion[String(questionIndex)];
        }
        touchStudyState();
    }

    function setMode(mode) {
        state.mode = mode;
        clearFeedback();
        syncModeInUrl();
        render();
    }

    function startMode(mode) {
        var normalizedMode = normalizeMode(mode);
        if (!normalizedMode) {
            return;
        }

        state.mode = normalizedMode;
        clearFeedback();
        if (normalizedMode === "assessment") {
            if (!state.assessment.started && assessmentDraftTotals().answered === 0) {
                state.assessment.startedAt = new Date().toISOString();
                timerExpiryHandled = false;
            }
            state.assessment.started = true;
            persistAssessmentState();
        } else {
            if (!state.learning.started && learningTotals().answered === 0) {
                state.learning.startedAt = new Date().toISOString();
            }
            state.learning.started = true;
            persistLearningState();
        }
        syncModeInUrl();
        touchExamActivity(normalizedMode);
        render();
    }

    function leaveFocusMode() {
        closeSessionDialog(false);
        if (state.mode === "assessment") {
            state.assessment.started = false;
            persistAssessmentState();
        } else if (state.mode === "learning") {
            state.learning.started = false;
            persistLearningState();
        }
        render();
    }

    function closeSessionDialog(shouldRender) {
        state.layout.questionMapOpen = false;
        state.layout.finalReviewOpen = false;
        state.layout.mediaViewer = null;
        if (shouldRender !== false) {
            render();
        }
    }

    function jumpFromQuestionMap(questionIndex) {
        closeSessionDialog(false);
        if (state.mode === "learning") {
            state.learning.filter = "all";
            jumpToLearningQuestion(questionIndex);
            return;
        }
        state.assessment.filter = "all";
        jumpToAssessmentQuestion(questionIndex);
    }

    function jumpFromDialogToFirstUnanswered() {
        closeSessionDialog(false);
        if (state.mode === "learning") {
            jumpLearningToFirstUnanswered();
            return;
        }
        jumpAssessmentToFirstUnanswered();
    }

    function isModeStarted(mode) {
        if (mode === "assessment") {
            return !!state.assessment.started;
        }
        if (mode === "learning") {
            return !!state.learning.started;
        }
        return false;
    }

    function syncModeInUrl() {
        var nextParams = new URLSearchParams(window.location.search);
        if (state.mode) {
            nextParams.set("mode", state.mode);
        } else {
            nextParams.delete("mode");
        }

        var nextSearch = nextParams.toString();
        var nextUrl = window.location.pathname + (nextSearch ? "?" + nextSearch : "");
        window.history.replaceState(null, "", nextUrl);
    }

    function selectAssessmentAnswer(questionIndex, optionIndex) {
        if (state.assessment.report || (timerExpiryHandled && exam.durationMinutes > 0) || !isValidQuestionIndex(questionIndex)) {
            return;
        }

        state.assessment.answers[questionIndex] = optionIndex;
        state.assessment.currentQuestionIndex = questionIndex;
        persistAssessmentState();
        render();
    }

    function selectLearningAnswer(questionIndex, optionIndex) {
        if (!isValidQuestionIndex(questionIndex)) {
            return;
        }

        state.learning.answers[questionIndex] = optionIndex;
        state.learning.revealed[questionIndex] = true;
        state.learning.currentQuestionIndex = questionIndex;
        persistLearningState();
        render();
    }

    function revealEssayAnswer(questionIndex) {
        if (!isValidQuestionIndex(questionIndex)) {
            return;
        }

        state.learning.revealed[questionIndex] = true;
        state.learning.currentQuestionIndex = questionIndex;
        persistLearningState();
        render();
    }

    function resetAssessmentDraft() {
        if (state.assessment.report) {
            return;
        }

        if (!window.confirm("تمام پاسخ‌های سنجشی این آزمون پاک شود؟")) {
            return;
        }

        state.assessment.answers = createNullArray(exam.questions.length);
        state.assessment.startedAt = new Date().toISOString();
        timerExpiryHandled = false;
        state.assessment.filter = "all";
        state.assessment.currentQuestionIndex = 0;
        state.assessment.started = true;
        clearFeedback();
        persistAssessmentState();
        render();
    }

    function submitAssessment() {
        if (!exam.viewerState.canPersist || state.assessment.submitting || state.assessment.queued || state.assessment.report) {
            return;
        }

        state.layout.questionMapOpen = false;
        state.layout.finalReviewOpen = true;
        render();
    }

    function performAssessmentSubmission() {
        if (!exam.viewerState.canPersist || state.assessment.submitting || state.assessment.queued || state.assessment.report) {
            return;
        }

        state.assessment.submitting = true;
        state.layout.finalReviewOpen = false;
        clearFeedback();
        render();

        apiPost("submitAssessment", {
            course: exam.courseSlug,
            exam: exam.slug,
            answers: JSON.stringify(state.assessment.answers),
            startedAt: state.assessment.startedAt
        }).then(function (payload) {
            if (payload && payload.httpStatus === 0) {
                var queuedEntry = queueOfflineAssessment({
                    course: exam.courseSlug,
                    exam: exam.slug,
                    answers: JSON.stringify(state.assessment.answers),
                    startedAt: state.assessment.startedAt
                });
                if (!queuedEntry) {
                    throw new Error(payload.error || "ثبت آزمون انجام نشد.");
                }
                state.assessment.queued = String(queuedEntry.id || "");
                state.assessment.submitting = false;
                setFeedback("neutral", "اتصال قطع است. کارنامه در صف آفلاین ماند و بعد از آنلاین شدن ثبت می‌شود.");
                persistAssessmentState();
                render();
                return;
            }
            if (!payload || !payload.success || !payload.report) {
                throw new Error((payload && payload.error) || "ثبت آزمون انجام نشد.");
            }

            state.assessment.report = normalizeReport(payload.report, exam.questions);
            state.attemptHistory = normalizeAttemptHistory(payload.report.attemptHistory || state.attemptHistory);
            if (payload.report.studyState) {
                state.study = mergeStudyStateAfterReport(state.study, payload.report.studyState);
                persistStudyState();
                state.studySync.queued = true;
                scheduleStudySync();
            }
            state.assessment.answers = state.assessment.report.answers.slice();
            state.assessment.filter = "all";
            state.assessment.submitting = false;
            state.assessment.queued = "";
            state.assessment.currentQuestionIndex = 0;
            state.assessment.started = true;
            setFeedback("success", payload.message || "کارنامه این آزمون ذخیره شد.");
            persistAssessmentState();
            render();
        }).catch(function (error) {
            state.assessment.submitting = false;
            setFeedback("error", error && error.message ? error.message : "ثبت آزمون انجام نشد.");
            render();
        });
    }

    function resetAssessmentReport() {
        if (!state.assessment.report) {
            return;
        }

        if (!window.confirm("کارنامه این آزمون پاک شود تا دوباره از اول آزمون بدهی؟")) {
            return;
        }

        apiPost("resetAssessment", {
            course: exam.courseSlug,
            exam: exam.slug
        }).then(function (payload) {
            if (!payload || !payload.success) {
                throw new Error((payload && payload.error) || "ریست کارنامه انجام نشد.");
            }

            state.assessment.report = null;
            state.assessment.answers = createNullArray(exam.questions.length);
            state.assessment.startedAt = new Date().toISOString();
            timerExpiryHandled = false;
            state.assessment.filter = "all";
            state.assessment.currentQuestionIndex = 0;
            state.assessment.started = true;
            setFeedback("success", payload.message || "کارنامه این آزمون ریست شد.");
            persistAssessmentState();
            render();
        }).catch(function (error) {
            setFeedback("error", error && error.message ? error.message : "ریست کارنامه انجام نشد.");
            render();
        });
    }

    function resetLearningProgress() {
        if (!window.confirm("پیشرفت حالت آموزشی از اول شروع شود؟")) {
            return;
        }

        state.learning.answers = createNullArray(exam.questions.length);
        state.learning.revealed = createFalseArray(exam.questions.length);
        state.learning.startedAt = new Date().toISOString();
        state.learning.currentQuestionIndex = 0;
        state.learning.filter = "all";
        state.learning.started = true;
        clearFeedback();
        persistLearningState();
        render();
    }

    function assessmentVisibleIndexes(filter) {
        var indexes = [];
        for (var index = 0; index < exam.questions.length; index++) {
            if (assessmentMatchesFilter(index, filter)) {
                indexes.push(index);
            }
        }
        return indexes;
    }

    function assessmentMatchesFilter(questionIndex, filter) {
        var report = state.assessment.report;
        var selectedIndex = report ? report.answers[questionIndex] : state.assessment.answers[questionIndex];
        var stateName = report
            ? assessmentReviewState(questionIndex, selectedIndex)
            : assessmentDraftState(selectedIndex);

        if (!filter || filter === "all") {
            return true;
        }
        if (filter === "flagged") {
            return isFlagged(questionIndex);
        }
        if (filter === "mistakes") {
            return isMistakeQuestion(questionIndex);
        }
        if (filter === "answered") {
            return selectedIndex !== null;
        }
        if (filter === "unanswered") {
            return stateName === "unanswered";
        }
        if (filter === "correct") {
            return stateName === "correct";
        }
        if (filter === "wrong") {
            return stateName === "wrong";
        }
        return true;
    }

    function ensureAssessmentIndex(visibleIndexes) {
        var indexes = visibleIndexes && visibleIndexes.length ? visibleIndexes : assessmentVisibleIndexes(state.assessment.filter);
        if (!indexes.length) {
            return 0;
        }

        if (indexes.indexOf(state.assessment.currentQuestionIndex) === -1) {
            state.assessment.currentQuestionIndex = indexes[0];
            persistAssessmentState();
        }
        return state.assessment.currentQuestionIndex;
    }

    function moveAssessment(step) {
        var visibleIndexes = assessmentVisibleIndexes(state.assessment.filter);
        var currentIndex = ensureAssessmentIndex(visibleIndexes);
        var currentPosition = visibleIndexes.indexOf(currentIndex);
        if (currentPosition === -1) {
            currentPosition = 0;
        }

        var nextPosition = currentPosition + step;
        if (nextPosition < 0 || nextPosition >= visibleIndexes.length) {
            return;
        }

        state.assessment.currentQuestionIndex = visibleIndexes[nextPosition];
        persistAssessmentState();
        render();
    }

    function jumpAssessmentToFirstUnanswered() {
        var visibleIndexes = assessmentVisibleIndexes("unanswered");
        if (!visibleIndexes.length) {
            return;
        }

        state.assessment.filter = "unanswered";
        state.assessment.currentQuestionIndex = visibleIndexes[0];
        persistAssessmentState();
        render();
    }

    function jumpToAssessmentQuestion(questionIndex) {
        if (!isValidQuestionIndex(questionIndex)) {
            return;
        }

        state.assessment.currentQuestionIndex = questionIndex;
        persistAssessmentState();
        render();
    }

    function learningVisibleIndexes() {
        var indexes = [];
        for (var index = 0; index < exam.questions.length; index++) {
            if (Array.isArray(state.learning.customIndexes) && state.learning.customIndexes.indexOf(index) === -1) {
                continue;
            }
            if (state.learning.filter === "flagged" && !isFlagged(index)) {
                continue;
            }
            if (state.learning.filter === "mistakes" && !isMistakeQuestion(index)) {
                continue;
            }
            indexes.push(index);
        }
        return indexes;
    }

    function buildCustomPractice() {
        var topic = readBuilderValue("custom-topic", "all");
        var difficulty = readBuilderValue("custom-difficulty", "all");
        var status = readBuilderValue("custom-status", "all");
        var countNode = document.getElementById("custom-count");
        var count = Math.max(1, Math.min(exam.questions.length, parseInt(countNode && countNode.value || "20", 10) || 20));
        var indexes = exam.questions.map(function (_question, index) { return index; }).filter(function (index) {
            var question = exam.questions[index];
            if (topic !== "all" && question.topic !== topic) {
                return false;
            }
            if (difficulty !== "all" && question.difficultyLabel !== difficulty) {
                return false;
            }
            if (status === "mistakes" && !isMistakeQuestion(index)) {
                return false;
            }
            if (status === "flagged" && !isFlagged(index)) {
                return false;
            }
            if (status === "unseen" && (state.learning.answers[index] !== null || state.learning.revealed[index])) {
                return false;
            }
            return true;
        });
        if (!indexes.length) {
            state.feedback = { kind: "warning", text: "با این فیلترها سؤالی پیدا نشد؛ یکی از فیلترها را بازتر کن." };
            render();
            return;
        }
        shuffleIndexes(indexes);
        state.learning.customIndexes = indexes.slice(0, count).sort(function (a, b) { return a - b; });
        state.learning.customLabel = [topic !== "all" ? topic : "", difficulty !== "all" ? difficulty : "", status !== "all" ? builderStatusLabel(status) : ""].filter(Boolean).join(" • ") || "تمرین شخصی";
        state.learning.filter = "all";
        state.learning.currentQuestionIndex = state.learning.customIndexes[0];
        state.learning.started = true;
        state.mode = "learning";
        state.feedback = { kind: "success", text: "جلسه تمرینی با " + formatValue(state.learning.customIndexes.length) + " سؤال ساخته شد." };
        persistLearningState();
        syncModeInUrl();
        render();
    }

    function startWeakTopicReview(topic) {
        var indexes = exam.questions.map(function (_question, index) { return index; }).filter(function (index) {
            return exam.questions[index].topic === topic;
        });
        if (!indexes.length) {
            return;
        }
        state.learning.customIndexes = indexes;
        state.learning.customLabel = "مرور مبحث: " + topic;
        state.learning.filter = "all";
        state.learning.currentQuestionIndex = indexes[0];
        state.learning.started = true;
        state.mode = "learning";
        persistLearningState();
        syncModeInUrl();
        render();
    }

    function readBuilderValue(id, fallback) {
        var node = document.getElementById(id);
        return normalizeText(node && node.value) || fallback;
    }

    function builderStatusLabel(status) {
        return { unseen: "حل‌نشده", mistakes: "اشتباهات", flagged: "نشان‌دار" }[status] || "";
    }

    function shuffleIndexes(indexes) {
        for (var index = indexes.length - 1; index > 0; index--) {
            var target = Math.floor(Math.random() * (index + 1));
            var value = indexes[index];
            indexes[index] = indexes[target];
            indexes[target] = value;
        }
    }

    function openMediaViewer(questionIndex, mediaIndex) {
        if (!isValidQuestionIndex(questionIndex) || !exam.questions[questionIndex].media[mediaIndex]) {
            return;
        }
        state.layout.questionMapOpen = false;
        state.layout.finalReviewOpen = false;
        state.layout.mediaViewer = { questionIndex: questionIndex, mediaIndex: mediaIndex, zoom: 1, rotation: 0 };
        render();
    }

    function updateMediaViewer(action) {
        var viewer = state.layout.mediaViewer;
        if (!viewer) {
            return;
        }
        if (action === "media-zoom-in") {
            viewer.zoom = Math.min(3, Math.round((viewer.zoom + 0.25) * 100) / 100);
        } else if (action === "media-zoom-out") {
            viewer.zoom = Math.max(0.75, Math.round((viewer.zoom - 0.25) * 100) / 100);
        } else if (action === "media-rotate") {
            viewer.rotation = (viewer.rotation + 90) % 360;
        } else {
            viewer.zoom = 1;
            viewer.rotation = 0;
        }
        render();
    }

    function ensureLearningIndex(visibleIndexes) {
        var indexes = visibleIndexes && visibleIndexes.length ? visibleIndexes : learningVisibleIndexes();
        if (!indexes.length) {
            return 0;
        }

        if (indexes.indexOf(state.learning.currentQuestionIndex) === -1) {
            state.learning.currentQuestionIndex = indexes[0];
            persistLearningState();
        }
        return state.learning.currentQuestionIndex;
    }

    function moveLearning(step) {
        var visibleIndexes = learningVisibleIndexes();
        var currentIndex = ensureLearningIndex(visibleIndexes);
        var currentPosition = visibleIndexes.indexOf(currentIndex);
        if (currentPosition === -1) {
            currentPosition = 0;
        }

        var nextPosition = currentPosition + step;
        if (nextPosition < 0 || nextPosition >= visibleIndexes.length) {
            return;
        }

        state.learning.currentQuestionIndex = visibleIndexes[nextPosition];
        persistLearningState();
        render();
    }

    function jumpToLearningQuestion(questionIndex) {
        if (!isValidQuestionIndex(questionIndex)) {
            return;
        }

        state.learning.currentQuestionIndex = questionIndex;
        persistLearningState();
        render();
    }

    function jumpLearningToFirstUnanswered() {
        var scope = Array.isArray(state.learning.customIndexes)
            ? state.learning.customIndexes
            : exam.questions.map(function (_question, questionIndex) { return questionIndex; });
        var index = scope.find(function (questionIndex) {
            var question = exam.questions[questionIndex];
            return question.isEssay
                ? !state.learning.revealed[questionIndex]
                : state.learning.answers[questionIndex] === null;
        });
        if (!Number.isInteger(index)) {
            return;
        }

        state.learning.currentQuestionIndex = index;
        state.learning.filter = "all";
        persistLearningState();
        render();
    }

    function isOptionStruck(questionIndex, optionIndex) {
        var options = state.study.struckOptionsByQuestion[String(questionIndex)] || [];
        return options.indexOf(optionIndex) >= 0;
    }

    function questionHighlights(questionIndex) {
        return Array.isArray(state.study.highlightsByQuestion[String(questionIndex)])
            ? state.study.highlightsByQuestion[String(questionIndex)]
            : [];
    }

    function isMistakeQuestion(questionIndex) {
        return state.study.mistakeQuestionIndexes.indexOf(questionIndex) >= 0;
    }

    function toggleOptionStrike(questionIndex, optionIndex) {
        if (!isValidQuestionIndex(questionIndex) || optionIndex < 0 || optionIndex >= exam.questions[questionIndex].options.length) {
            return;
        }
        var key = String(questionIndex);
        var options = Array.isArray(state.study.struckOptionsByQuestion[key])
            ? state.study.struckOptionsByQuestion[key].slice()
            : [];
        var position = options.indexOf(optionIndex);
        if (position >= 0) {
            options.splice(position, 1);
        } else {
            options.push(optionIndex);
            options.sort(function (left, right) { return left - right; });
        }
        if (options.length) {
            state.study.struckOptionsByQuestion[key] = options;
        } else {
            delete state.study.struckOptionsByQuestion[key];
        }
        touchStudyState();
        render();
    }

    function toggleMistakeNotebook(questionIndex) {
        if (!isValidQuestionIndex(questionIndex)) {
            return;
        }
        var indexes = state.study.mistakeQuestionIndexes.slice();
        var position = indexes.indexOf(questionIndex);
        if (position >= 0) {
            indexes.splice(position, 1);
        } else {
            indexes.push(questionIndex);
            indexes.sort(function (left, right) { return left - right; });
        }
        state.study.mistakeQuestionIndexes = indexes;
        touchStudyState();
        render();
    }

    function captureQuestionSelection() {
        var selection = window.getSelection ? window.getSelection() : null;
        if (!selection || selection.rangeCount < 1 || selection.isCollapsed) {
            return;
        }
        var range = selection.getRangeAt(0);
        var anchor = range.commonAncestorContainer.nodeType === 1
            ? range.commonAncestorContainer
            : range.commonAncestorContainer.parentElement;
        var questionNode = anchor && anchor.closest ? anchor.closest("[data-question-text]") : null;
        if (!questionNode || !appRoot.contains(questionNode)) {
            return;
        }
        var questionIndex = parseIndex(questionNode.getAttribute("data-question-text"));
        if (!isValidQuestionIndex(questionIndex)) {
            return;
        }
        var prefix = range.cloneRange();
        prefix.selectNodeContents(questionNode);
        prefix.setEnd(range.startContainer, range.startOffset);
        var start = prefix.toString().length;
        var selectedText = range.toString();
        var end = start + selectedText.length;
        if (!selectedText.trim() || end <= start) {
            return;
        }
        state.pendingHighlight = {
            questionIndex: questionIndex,
            start: start,
            end: end
        };
    }

    function addPendingQuestionHighlight(questionIndex) {
        var pending = state.pendingHighlight;
        if (!pending || pending.questionIndex !== questionIndex || pending.end <= pending.start) {
            setFeedback("neutral", "ابتدا بخشی از صورت سؤال را انتخاب کن.");
            render();
            return;
        }
        var key = String(questionIndex);
        var ranges = questionHighlights(questionIndex).concat([{ start: pending.start, end: pending.end }]);
        state.study.highlightsByQuestion[key] = mergeHighlightRanges(ranges, exam.questions[questionIndex].question.length);
        state.pendingHighlight = null;
        if (window.getSelection) {
            window.getSelection().removeAllRanges();
        }
        clearFeedback();
        touchStudyState();
        render();
    }

    function clearQuestionHighlights(questionIndex) {
        if (!isValidQuestionIndex(questionIndex)) {
            return;
        }
        delete state.study.highlightsByQuestion[String(questionIndex)];
        state.pendingHighlight = null;
        touchStudyState();
        render();
    }

    function mergeHighlightRanges(ranges, textLength) {
        var normalized = (Array.isArray(ranges) ? ranges : []).map(function (item) {
            return {
                start: Math.max(0, Math.min(textLength, Number(item && item.start) || 0)),
                end: Math.max(0, Math.min(textLength, Number(item && item.end) || 0))
            };
        }).filter(function (item) {
            return item.end > item.start;
        }).sort(function (left, right) {
            return left.start - right.start;
        });
        var merged = [];
        normalized.forEach(function (item) {
            var previous = merged[merged.length - 1];
            if (previous && item.start <= previous.end) {
                previous.end = Math.max(previous.end, item.end);
            } else {
                merged.push(item);
            }
        });
        return merged.slice(0, 20);
    }

    function touchStudyState() {
        state.study.updatedAt = new Date().toISOString();
        persistStudyState();
        if (exam.viewerState.canPersist) {
            scheduleStudySync();
        }
        updateFocusBarStatus();
    }

    function persistStudyState() {
        try {
            window.localStorage.setItem(studyDraftKey, JSON.stringify(state.study));
        } catch (_error) {
            return;
        }
    }

    function scheduleStudySync() {
        if (state.studySync.timer) {
            window.clearTimeout(state.studySync.timer);
        }
        state.studySync.queued = true;
        state.studySync.timer = window.setTimeout(function () {
            state.studySync.timer = 0;
            flushStudySync();
        }, 650);
    }

    function flushStudySync() {
        if (!exam.viewerState.canPersist || !state.studySync.queued || state.studySync.saving) {
            return;
        }
        if (typeof navigator !== "undefined" && navigator.onLine === false) {
            updateFocusBarStatus();
            return;
        }
        state.studySync.saving = true;
        state.studySync.queued = false;
        updateFocusBarStatus();
        apiPost("saveStudyState", {
            course: exam.courseSlug,
            exam: exam.slug,
            studyState: JSON.stringify(state.study)
        }).then(function (payload) {
            if (!payload || !payload.success || !payload.studyState) {
                throw new Error((payload && payload.error) || "ذخیره ابزارهای مطالعه انجام نشد.");
            }
            state.study = normalizeStudyState(payload.studyState, exam.questions.length);
            state.studySync.saving = false;
            persistStudyState();
            updateFocusBarStatus();
        }).catch(function (error) {
            state.studySync.saving = false;
            state.studySync.queued = true;
            setFeedback("error", error && error.message ? error.message : "ذخیره ابزارهای مطالعه انجام نشد.");
            updateFocusBarStatus();
        });
    }

    function toggleFlag(questionIndex) {
        if (!isValidQuestionIndex(questionIndex)) {
            return;
        }

        if (state.flags.has(questionIndex)) {
            state.flags.delete(questionIndex);
        } else {
            state.flags.add(questionIndex);
        }

        if (exam.viewerState.canPersist) {
            scheduleFlagSync();
        } else {
            persistGuestFlags();
        }
        clearFeedback();
        render();
    }

    function scheduleFlagSync() {
        if (state.flagsSync.timer) {
            window.clearTimeout(state.flagsSync.timer);
        }

        state.flagsSync.queued = true;
        state.flagsSync.timer = window.setTimeout(function () {
            state.flagsSync.timer = 0;
            flushFlagSync();
        }, 280);
    }

    function flushFlagSync() {
        if (!exam.viewerState.canPersist || !state.flagsSync.queued || state.flagsSync.saving) {
            return;
        }

        state.flagsSync.saving = true;
        state.flagsSync.queued = false;
        render();

        apiPost("saveFlags", {
            course: exam.courseSlug,
            exam: exam.slug,
            mode: state.mode || "view",
            flaggedQuestionIndexes: JSON.stringify(Array.from(state.flags).sort(function (left, right) {
                return left - right;
            }))
        }).then(function (payload) {
            if (!payload || !payload.success) {
                throw new Error((payload && payload.error) || "ذخیره نشان‌دارها انجام نشد.");
            }

            state.flagsSync.saving = false;
            render();
        }).catch(function (error) {
            state.flagsSync.saving = false;
            setFeedback("error", error && error.message ? error.message : "ذخیره نشان‌دارها انجام نشد.");
            render();
        });
    }

    function persistGuestFlags() {
        try {
            window.localStorage.setItem(guestFlagsKey, JSON.stringify(Array.from(state.flags)));
        } catch (_error) {
            return;
        }
    }

    function persistAssessmentState() {
        try {
            window.sessionStorage.setItem(assessmentDraftKey, JSON.stringify({
                answers: state.assessment.report ? state.assessment.report.answers : state.assessment.answers,
                startedAt: state.assessment.startedAt,
                filter: state.assessment.filter,
                currentQuestionIndex: state.assessment.currentQuestionIndex,
                started: state.assessment.started
            }));
        } catch (_error) {
            return;
        }
    }

    function persistLearningState() {
        try {
            window.sessionStorage.setItem(learningDraftKey, JSON.stringify({
                answers: state.learning.answers,
                revealed: state.learning.revealed,
                startedAt: state.learning.startedAt,
                currentQuestionIndex: state.learning.currentQuestionIndex,
                filter: state.learning.filter,
                customIndexes: state.learning.customIndexes,
                customLabel: state.learning.customLabel,
                started: state.learning.started
            }));
        } catch (_error) {
            return;
        }
    }

    function assessmentDraftTotals() {
        var answered = 0;
        state.assessment.answers.forEach(function (answer) {
            if (answer !== null) {
                answered++;
            }
        });

        return {
            answered: answered,
            unanswered: exam.questions.length - answered
        };
    }

    function reportTotals(report) {
        return {
            correct: Number(report.correct || 0),
            wrong: Number(report.wrong || 0),
            unanswered: Number(report.unanswered || 0)
        };
    }

    function learningTotals() {
        var answered = 0;
        var scope = Array.isArray(state.learning.customIndexes)
            ? state.learning.customIndexes
            : exam.questions.map(function (_question, index) { return index; });
        scope.forEach(function (index) {
            var question = exam.questions[index];
            var isDone = question.isEssay ? state.learning.revealed[index] : state.learning.answers[index] !== null;
            if (isDone) {
                answered++;
            }
        });

        return {
            answered: answered,
            unanswered: scope.length - answered
        };
    }

    function learningScopeCount() {
        return Array.isArray(state.learning.customIndexes)
            ? state.learning.customIndexes.length
            : exam.questions.length;
    }

    function assessmentDraftState(selectedIndex) {
        return selectedIndex === null ? "unanswered" : "answered";
    }

    function assessmentReviewState(questionIndex, selectedIndex) {
        if (selectedIndex === null) {
            return "unanswered";
        }
        if (!hasResolvedCorrectIndex(exam.questions[questionIndex].correctIndex)) {
            return "review";
        }
        return selectedIndex === exam.questions[questionIndex].correctIndex ? "correct" : "wrong";
    }

    function assessmentNavState(questionIndex) {
        var report = state.assessment.report;
        var selectedIndex = report ? report.answers[questionIndex] : state.assessment.answers[questionIndex];
        return report
            ? assessmentReviewState(questionIndex, selectedIndex)
            : assessmentDraftState(selectedIndex);
    }

    function learningNavState(questionIndex) {
        var question = exam.questions[questionIndex];
        if (question.isEssay) {
            return state.learning.revealed[questionIndex] ? "answered" : "unanswered";
        }
        var selectedIndex = state.learning.answers[questionIndex];
        if (selectedIndex === null) {
            return "unanswered";
        }
        if (!state.learning.revealed[questionIndex]) {
            return "answered";
        }
        if (!hasResolvedCorrectIndex(question.correctIndex)) {
            return "review";
        }
        return selectedIndex === question.correctIndex ? "correct" : "wrong";
    }

    function assessmentStateLabel(stateName) {
        if (stateName === "correct") {
            return "صحیح";
        }
        if (stateName === "wrong") {
            return "غلط";
        }
        if (stateName === "answered") {
            return "پاسخ داده شده";
        }
        if (stateName === "review") {
            return "بدون کلید";
        }
        return "بی‌پاسخ";
    }

    function learningAnswerLabel(question, selectedIndex) {
        if (question.isEssay) {
            return "پاسخ تشریحی نمایش داده شد";
        }
        if (selectedIndex === null) {
            return "در انتظار پاسخ";
        }
        if (!hasResolvedCorrectIndex(question.correctIndex)) {
            return "بدون کلید قطعی";
        }
        return selectedIndex === question.correctIndex ? "پاسخ درست" : "نیاز به مرور";
    }

    function handleDocumentKeydown(event) {
        if (!event || event.key !== "Escape") {
            return;
        }
        if (state.layout.questionMapOpen || state.layout.finalReviewOpen || state.layout.mediaViewer) {
            event.preventDefault();
            closeSessionDialog();
        }
    }

    function syncFocusMode() {
        var active = isFocusModeActive();
        document.body.classList.toggle("exam-focus-mode", active);
        document.body.classList.toggle("exam-dialog-open", !!(state.layout.questionMapOpen || state.layout.finalReviewOpen || state.layout.mediaViewer));
    }

    function isFocusModeActive() {
        if (state.mode === "assessment") {
            return !!state.assessment.started && !state.assessment.report;
        }
        return state.mode === "learning" && !!state.learning.started;
    }

    function scheduleDialogFocus() {
        if (!state.layout.questionMapOpen && !state.layout.finalReviewOpen && !state.layout.mediaViewer) {
            return;
        }
        window.requestAnimationFrame(function () {
            var dialog = appRoot.querySelector("[data-session-dialog]");
            if (dialog && typeof dialog.focus === "function") {
                dialog.focus({ preventScroll: true });
            }
        });
    }

    function captureFocusSnapshot() {
        var active = document.activeElement;
        if (!active || !appRoot.contains(active) || !active.getAttribute) {
            return null;
        }
        var action = normalizeText(active.getAttribute("data-action"));
        if (!action) {
            return null;
        }
        return {
            action: action,
            questionIndex: normalizeText(active.getAttribute("data-question-index")),
            optionIndex: normalizeText(active.getAttribute("data-option-index")),
            filter: normalizeText(active.getAttribute("data-filter"))
        };
    }

    function restoreFocusSnapshot(snapshot) {
        if (!snapshot || state.layout.questionMapOpen || state.layout.finalReviewOpen) {
            return;
        }
        window.requestAnimationFrame(function () {
            var candidates = appRoot.querySelectorAll('[data-action="' + snapshot.action + '"]');
            for (var index = 0; index < candidates.length; index++) {
                var candidate = candidates[index];
                if (normalizeText(candidate.getAttribute("data-question-index")) !== snapshot.questionIndex
                    || normalizeText(candidate.getAttribute("data-option-index")) !== snapshot.optionIndex
                    || normalizeText(candidate.getAttribute("data-filter")) !== snapshot.filter) {
                    continue;
                }
                if (!candidate.disabled && typeof candidate.focus === "function") {
                    candidate.focus({ preventScroll: true });
                }
                return;
            }
        });
    }

    function syncTimerInterval() {
        if (!isFocusModeActive()) {
            if (timerInterval) {
                window.clearInterval(timerInterval);
                timerInterval = 0;
            }
            return;
        }

        updateTimerText();
        if (!timerInterval) {
            timerInterval = window.setInterval(updateTimerText, 1000);
        }
    }

    function updateTimerText() {
        var timerNode = appRoot.querySelector("[data-exam-timer]");
        if (timerNode) {
            timerNode.textContent = timerLabel();
            timerNode.classList.toggle("is-warning", isTimedAssessment() && timerRemainingSeconds() <= 300);
            timerNode.classList.toggle("is-danger", isTimedAssessment() && timerRemainingSeconds() <= 60);
        }
        updateFocusBarStatus();

        if (state.mode === "assessment" && exam.durationMinutes > 0 && timerRemainingSeconds() <= 0) {
            handleTimerExpiry();
        }
    }

    function updateFocusBarStatus() {
        var statusNode = appRoot.querySelector("[data-exam-save-status]");
        if (!statusNode) {
            return;
        }
        var status = focusSaveStatus();
        statusNode.textContent = status.label;
        statusNode.className = "exam-focus-bar__save is-" + status.tone;
    }

    function focusSaveStatus() {
        if (state.assessment && state.assessment.queued) {
            return { label: "در صف آفلاین", tone: "warning" };
        }
        if (state.flagsSync.saving || state.studySync.saving) {
            return { label: "در حال ذخیره", tone: "saving" };
        }
        if (typeof navigator !== "undefined" && navigator.onLine === false) {
            return { label: "آفلاین؛ ذخیره روی دستگاه", tone: "warning" };
        }
        if (state.studySync.queued) {
            return { label: "در انتظار همگام‌سازی", tone: "warning" };
        }
        return { label: "ذخیره شد", tone: "saved" };
    }

    function timerElapsedSeconds() {
        var sourceStartedAt = state.mode === "learning" ? state.learning.startedAt : state.assessment.startedAt;
        var startedAt = Date.parse(sourceStartedAt || "");
        if (!Number.isFinite(startedAt)) {
            return 0;
        }
        return Math.max(0, Math.floor((Date.now() - startedAt) / 1000));
    }

    function timerRemainingSeconds() {
        if (exam.durationMinutes <= 0) {
            return 0;
        }
        return Math.max(0, Math.round(exam.durationMinutes * 60) - timerElapsedSeconds());
    }

    function timerValue() {
        return formatClock(isTimedAssessment() ? timerRemainingSeconds() : timerElapsedSeconds());
    }

    function timerLabel() {
        return (isTimedAssessment() ? "باقی‌مانده " : "سپری‌شده ") + timerValue();
    }

    function isTimedAssessment() {
        return state.mode === "assessment" && exam.durationMinutes > 0;
    }

    function formatClock(totalSeconds) {
        var seconds = Math.max(0, Math.floor(Number(totalSeconds) || 0));
        var hours = Math.floor(seconds / 3600);
        var minutes = Math.floor((seconds % 3600) / 60);
        var remainder = seconds % 60;
        var parts = hours > 0 ? [hours, minutes, remainder] : [minutes, remainder];
        return parts.map(function (part) {
            return String(part).padStart(2, "0").replace(/[0-9]/g, function (digit) {
                return "۰۱۲۳۴۵۶۷۸۹"[Number(digit)];
            });
        }).join(":");
    }

    function handleTimerExpiry() {
        if (timerExpiryHandled || state.assessment.report || state.assessment.submitting) {
            return;
        }
        timerExpiryHandled = true;
        if (exam.autoSubmitOnExpiry) {
            performAssessmentSubmission();
            return;
        }
        state.layout.questionMapOpen = false;
        state.layout.finalReviewOpen = true;
        render();
    }

    function scheduleLayoutSync() {
        if (layoutFrame) {
            window.cancelAnimationFrame(layoutFrame);
        }
        if (delayedLayoutTimer) {
            window.clearTimeout(delayedLayoutTimer);
        }

        layoutFrame = window.requestAnimationFrame(function () {
            layoutFrame = 0;
            syncShellMetrics();
            syncStageScale();
        });

        delayedLayoutTimer = window.setTimeout(function () {
            delayedLayoutTimer = 0;
            syncShellMetrics();
            syncStageScale();
        }, 90);
    }

    function syncShellMetrics() {
        var header = document.querySelector(".site-header");
        var bottomNav = document.querySelector(".shell-bottom-nav");
        var headerHeight = header ? Math.ceil(header.getBoundingClientRect().height) : 0;
        var bottomHeight = bottomNav ? Math.ceil(bottomNav.getBoundingClientRect().height) : 0;
        var usableHeight = Math.max(360, window.innerHeight - headerHeight - bottomHeight);

        document.body.style.setProperty("--exam-shell-top-offset", headerHeight + "px");
        document.body.style.setProperty("--exam-shell-bottom-offset", bottomHeight + "px");
        document.body.style.setProperty("--exam-shell-usable-height", usableHeight + "px");
    }

    function syncStageScale() {
        var scaler = appRoot.querySelector(".exam-stage-scaler");
        if (!scaler) {
            return;
        }

        scaler.style.setProperty("--exam-stage-scale", "1");
    }

    function railWindowSize() {
        if (window.innerWidth <= 520) {
            return 5;
        }
        if (window.innerWidth <= 880) {
            return 7;
        }
        return 9;
    }

    function buildRailItems(indexes, currentIndex, windowSize) {
        if (!indexes.length) {
            return [];
        }

        var currentPosition = indexes.indexOf(currentIndex);
        if (currentPosition < 0) {
            currentPosition = 0;
        }

        var start = Math.max(0, currentPosition - Math.floor(windowSize / 2));
        var end = start + windowSize - 1;
        if (end >= indexes.length) {
            end = indexes.length - 1;
            start = Math.max(0, end - windowSize + 1);
        }

        var items = [];
        if (start > 0) {
            items.push({ type: "index", index: indexes[0] });
            if (start > 1) {
                items.push({ type: "ellipsis" });
            }
        }

        for (var cursor = start; cursor <= end; cursor++) {
            items.push({ type: "index", index: indexes[cursor] });
        }

        if (end < indexes.length - 1) {
            if (end < indexes.length - 2) {
                items.push({ type: "ellipsis" });
            }
            items.push({ type: "index", index: indexes[indexes.length - 1] });
        }

        return items;
    }

    function setFeedback(kind, text) {
        state.feedback.kind = kind || "neutral";
        state.feedback.text = text || "";
    }

    function clearFeedback() {
        setFeedback("", "");
    }

    function modeDefinition(mode) {
        var items = exam.modes || [];
        for (var index = 0; index < items.length; index++) {
            if (items[index].key === mode) {
                return items[index];
            }
        }
        return {
            key: mode || "",
            title: mode === "learning" ? "آزمون آموزشی" : "آزمون سنجشی",
            tagline: "",
            description: ""
        };
    }

    function isFlagged(questionIndex) {
        return state.flags.has(questionIndex);
    }

    function networkErrorResponse() {
        return {
            success: false,
            error: "ارتباط با سرور برقرار نشد. اتصال اینترنت خود را بررسی کنید.",
            httpStatus: 0
        };
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

    function apiPost(action, payload) {
        var body = new URLSearchParams(withCohort(Object.assign({ action: action }, payload || {})));
        return fetchWithTimeout("/api/exams_api.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                Accept: "application/json"
            },
            body: body.toString()
        }, 20000).then(parseJson).catch(networkErrorResponse);
    }

    function offlineApi() {
        return window.Dent1402Site
            && typeof window.Dent1402Site === "object"
            && window.Dent1402Site.offline
            && typeof window.Dent1402Site.offline === "object"
            ? window.Dent1402Site.offline
            : null;
    }

    function offlineAssessmentDedupeKey() {
        return ["exam-assessment", cohortKey || "main", exam.courseSlug || "", exam.slug || ""].join(":");
    }

    function syncPendingOfflineAssessment() {
        var offline = offlineApi();
        if (!offline || typeof offline.findQueuedEntry !== "function" || !state || !state.assessment) {
            return;
        }
        var dedupeKey = offlineAssessmentDedupeKey();
        var queued = offline.findQueuedEntry(function (entry) {
            return entry && entry.kind === "exam-assessment" && entry.dedupeKey === dedupeKey;
        });
        state.assessment.queued = queued ? String(queued.id || "") : "";
    }

    function queueOfflineAssessment(payload) {
        var offline = offlineApi();
        if (!offline || typeof offline.queueRequest !== "function") {
            return null;
        }
        return offline.queueRequest({
            kind: "exam-assessment",
            url: "/api/exams_api.php",
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                Accept: "application/json"
            },
            body: new URLSearchParams(withCohort(Object.assign({ action: "submitAssessment" }, payload || {}))).toString(),
            dedupeKey: offlineAssessmentDedupeKey(),
            meta: {
                cohort: cohortKey || "main",
                course: exam.courseSlug || "",
                exam: exam.slug || ""
            }
        });
    }

    function applyOfflineAssessmentResult(payload) {
        if (!payload || payload.success !== true || !payload.report) {
            return false;
        }
        state.assessment.report = normalizeReport(payload.report, exam.questions);
        state.attemptHistory = normalizeAttemptHistory(payload.report.attemptHistory || state.attemptHistory);
        if (payload.report.studyState) {
            state.study = mergeStudyStateAfterReport(state.study, payload.report.studyState);
            persistStudyState();
            state.studySync.queued = true;
            scheduleStudySync();
        }
        state.assessment.answers = state.assessment.report.answers.slice();
        state.assessment.filter = "all";
        state.assessment.submitting = false;
        state.assessment.queued = "";
        state.assessment.currentQuestionIndex = 0;
        state.assessment.started = true;
        setFeedback("success", payload.message || "کارنامه آزمون آفلاین روی سرور ذخیره شد.");
        persistAssessmentState();
        render();
        return true;
    }

    function touchExamActivity(mode) {
        var normalizedMode = normalizeMode(mode) || "view";
        if (!exam.viewerState.canPersist || !exam.courseSlug || !exam.slug) {
            return;
        }
        if (normalizedMode === activityTrackedMode) {
            return;
        }

        activityTrackedMode = normalizedMode;
        apiPost("touchExamActivity", {
            course: exam.courseSlug,
            exam: exam.slug,
            mode: normalizedMode
        }).catch(function () {
            return null;
        });
    }

    function withCohort(payload) {
        var next = Object.assign({}, payload || {});
        if (cohortKey) {
            next.cohort = cohortKey;
        }
        return next;
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

    function normalizeExamData(data) {
        var viewerState = isObject(data.viewerState) ? data.viewerState : {};
        var normalizedQuestions = (Array.isArray(data.questions) ? data.questions : []).map(normalizeQuestion).filter(function (question) {
            return question.question && (question.isEssay || question.options.length >= 2);
        });
        var report = normalizeReport(viewerState.assessmentReport, normalizedQuestions);
        var essayOnly = normalizedQuestions.length > 0 && normalizedQuestions.every(function (question) {
            return question.isEssay;
        });
        var hasEssayQuestions = normalizedQuestions.some(function (question) {
            return question.isEssay;
        });

        return {
            essayOnly: essayOnly,
            learningOnly: Boolean(data.learningOnly || data.reviewOnly) || (hasEssayQuestions && !essayOnly),
            slug: normalizeText(data.slug),
            courseSlug: normalizeText(data.courseSlug || data.course || (document.body && document.body.dataset ? document.body.dataset.examsCourse : "")),
            courseTitle: normalizeText(data.courseTitle),
            footerText: normalizeText(data.footerText) || "طراحی شده برای مرور، سنجش و یادگیری مرحله‌ای.",
            backHref: normalizeText(data.backHref) || "/exams/",
            backLabel: normalizeText(data.backLabel) || "بازگشت به آزمون‌ها",
            eyebrow: normalizeText(data.eyebrow) || "آزمون",
            title: normalizeText(data.title) || "آزمون",
            subtitle: normalizeText(data.subtitle) || "پیش از شروع، حالت دلخواهت را انتخاب کن.",
            answerRevealLabel: normalizeText(data.answerRevealLabel || ""),
            answerCardLabel: normalizeText(data.answerCardLabel || ""),
            durationMinutes: Math.min(720, maxNumber(data.durationMinutes || data.timeLimitMinutes, 0)),
            autoSubmitOnExpiry: Boolean(data.autoSubmitOnExpiry),
            modes: Array.isArray(data.modes) ? data.modes : [],
            questions: normalizedQuestions,
            ownerInsights: normalizeOwnerInsights(data.ownerInsights),
            viewerState: {
                canPersist: Boolean(viewerState.canPersist),
                flaggedQuestionIndexes: normalizeFlagIndexes(viewerState.flaggedQuestionIndexes, normalizedQuestions.length),
                assessmentReport: report,
                attemptHistory: normalizeAttemptHistory(viewerState.attemptHistory, normalizedQuestions.length),
                studyState: normalizeStudyState(viewerState.studyState, normalizedQuestions)
            }
        };
    }

    function normalizeOwnerInsights(rawInsights) {
        if (!isObject(rawInsights) || !rawInsights.canView) {
            return null;
        }

        var summary = isObject(rawInsights.summary) ? rawInsights.summary : {};
        return {
            canView: true,
            summary: {
                participantCount: maxNumber(summary.participantCount, 0),
                assessmentCount: maxNumber(summary.assessmentCount, 0),
                paidParticipantCount: maxNumber(summary.paidParticipantCount, 0),
                averagePercent: summary.averagePercent === null || summary.averagePercent === undefined
                    ? null
                    : clampPercent(summary.averagePercent)
            },
            participants: (Array.isArray(rawInsights.participants) ? rawInsights.participants : [])
                .map(normalizeOwnerParticipant)
                .filter(function (entry) {
                    return !!entry;
                })
        };
    }

    function normalizeOwnerParticipant(rawEntry) {
        if (!isObject(rawEntry)) {
            return null;
        }

        return {
            name: normalizeText(rawEntry.name) || "\u06a9\u0627\u0631\u0628\u0631",
            studentNumber: normalizeText(rawEntry.studentNumber),
            roleLabel: normalizeText(rawEntry.roleLabel),
            typeLabel: normalizeText(rawEntry.typeLabel) || "—",
            rank: rawEntry.rank === null || rawEntry.rank === undefined ? null : maxNumber(rawEntry.rank, 0),
            percent: rawEntry.percent === null || rawEntry.percent === undefined ? null : clampPercent(rawEntry.percent),
            correct: rawEntry.correct === null || rawEntry.correct === undefined ? null : maxNumber(rawEntry.correct, 0),
            wrong: rawEntry.wrong === null || rawEntry.wrong === undefined ? null : maxNumber(rawEntry.wrong, 0),
            overallExamCount: maxNumber(rawEntry.overallExamCount, 0),
            purchasedExamCount: maxNumber(rawEntry.purchasedExamCount, 0),
            flagsCount: maxNumber(rawEntry.flagsCount, 0),
            lastActivityAt: normalizeText(rawEntry.lastActivityAt)
        };
    }

    function normalizeQuestion(item) {
        var rawOptions = Array.isArray(item.options) ? item.options.slice(0, 8) : [];
        var options = rawOptions.map(function (option) {
            return normalizeText(option);
        });
        var isEssay = options.length === 0;
        var correctIndex = isEssay ? null : clampCorrectIndex(item.correctIndex, options.length);

        var difficulty = normalizeQuestionDifficulty(item.difficulty || item.level || "");
        return {
            question: stripQuestionNumber(normalizeText(item.question || item.text || "")),
            options: options,
            correctIndex: correctIndex,
            explanation: normalizeText(item.explanation || ""),
            topic: normalizeText(item.topic || item.subject || item.category || ""),
            difficultyKey: difficulty.key,
            difficultyLabel: difficulty.label,
            tags: normalizeQuestionTags(item.tags),
            caseContext: normalizeCaseContext(item.case || item.caseContext || item.clinicalCase),
            media: normalizeQuestionMedia(item.media || item.images || item.image),
            optionRationales: normalizeOptionRationales(item, options),
            isEssay: isEssay,
            answerSummary: normalizeText(item.answerSummary || ""),
            answerDetail: normalizeText(item.answerDetail || ""),
            answerRevealLabel: normalizeText(item.answerRevealLabel || ""),
            answerCardLabel: normalizeText(item.answerCardLabel || ""),
            reference: normalizeText(item.reference || ""),
            answerMeta: normalizeAnswerMeta(item.answerMeta),
            answerSections: normalizeAnswerSections(item.answerSections || item.explanationSections),
            answerResolved: isEssay ? true : hasResolvedCorrectIndex(correctIndex),
            useCompactOptions: shouldUseCompactOptions(options)
        };
    }

    function normalizeQuestionDifficulty(value) {
        var raw = normalizeText(value).toLowerCase();
        var map = {
            "1": { key: "easy", label: "آسان" }, easy: { key: "easy", label: "آسان" }, "آسان": { key: "easy", label: "آسان" },
            "2": { key: "medium", label: "متوسط" }, "3": { key: "medium", label: "متوسط" }, medium: { key: "medium", label: "متوسط" }, "متوسط": { key: "medium", label: "متوسط" },
            "4": { key: "hard", label: "سخت" }, "5": { key: "hard", label: "سخت" }, hard: { key: "hard", label: "سخت" }, "سخت": { key: "hard", label: "سخت" }
        };
        return map[raw] || { key: "", label: "" };
    }

    function normalizeQuestionTags(value) {
        return (Array.isArray(value) ? value : []).map(normalizeText).filter(Boolean).slice(0, 12);
    }

    function normalizeCaseContext(value) {
        if (!isObject(value)) {
            return null;
        }
        var factsSource = Array.isArray(value.facts) ? value.facts : (Array.isArray(value.sections) ? value.sections : []);
        var facts = factsSource.map(function (fact) {
            if (!isObject(fact)) {
                return null;
            }
            var label = normalizeText(fact.label || fact.title);
            var factValue = normalizeText(fact.value || fact.text);
            return label && factValue ? { label: label, value: factValue } : null;
        }).filter(Boolean).slice(0, 12);
        var result = {
            title: normalizeText(value.title || value.label),
            summary: normalizeText(value.summary || value.description || value.patient),
            alert: normalizeText(value.alert || value.warning),
            facts: facts
        };
        return result.title || result.summary || result.alert || result.facts.length ? result : null;
    }

    function normalizeQuestionMedia(value) {
        var list = Array.isArray(value) ? value : (value ? [value] : []);
        return list.map(function (item, index) {
            var raw = typeof item === "string" ? { src: item } : item;
            if (!isObject(raw)) {
                return null;
            }
            var src = normalizeSafeMediaUrl(raw.src || raw.url || raw.path);
            if (!src) {
                return null;
            }
            return {
                src: src,
                alt: normalizeText(raw.alt) || "تصویر بالینی سؤال " + formatValue(index + 1),
                caption: normalizeText(raw.caption || raw.title),
                kind: normalizeText(raw.kind || raw.type || "image")
            };
        }).filter(Boolean).slice(0, 8);
    }

    function normalizeSafeMediaUrl(value) {
        var url = normalizeText(value);
        if (!url || /^(?:javascript|data|vbscript):/iu.test(url)) {
            return "";
        }
        if (url.charAt(0) === "/" || /^https:\/\//iu.test(url)) {
            return url;
        }
        return "";
    }

    function normalizeAnswerMeta(items) {
        if (!Array.isArray(items)) {
            return [];
        }

        return items.map(function (item) {
            if (!isObject(item)) {
                return null;
            }

            var label = normalizeText(item.label || "");
            var value = normalizeText(item.value || "");
            if (!label || !value) {
                return null;
            }

            var tone = normalizeText(item.tone || "");
            return {
                label: label,
                value: value,
                tone: tone,
                wide: Boolean(item.wide)
            };
        }).filter(function (item) {
            return !!item;
        });
    }

    function normalizeReport(rawReport, questions) {
        if (!isObject(rawReport)) {
            return null;
        }

        var totalQuestions = Array.isArray(questions) ? questions.length : 0;
        return {
            attemptId: normalizeText(rawReport.attemptId),
            answers: clampAnswers(Array.isArray(rawReport.answers) ? rawReport.answers : createNullArray(totalQuestions), Array.isArray(questions) ? questions : []),
            totalQuestions: maxNumber(rawReport.totalQuestions, totalQuestions),
            correct: maxNumber(rawReport.correct, 0),
            wrong: maxNumber(rawReport.wrong, 0),
            unanswered: maxNumber(rawReport.unanswered, 0),
            percent: clampPercent(rawReport.percent),
            startedAt: normalizeText(rawReport.startedAt),
            submittedAt: normalizeText(rawReport.submittedAt),
            updatedAt: normalizeText(rawReport.updatedAt),
            rank: rawReport.rank === null || rawReport.rank === undefined ? null : maxNumber(rawReport.rank, 0),
            participantCount: maxNumber(rawReport.participantCount, 0),
            showRank: Boolean(rawReport.showRank),
            overallCompletedExams: maxNumber(rawReport.overallCompletedExams, 0),
            overallAveragePercent: rawReport.overallAveragePercent === null || rawReport.overallAveragePercent === undefined
                ? null
                : clampPercent(rawReport.overallAveragePercent),
            topicBreakdown: normalizeTopicBreakdown(rawReport.topicBreakdown),
            mistakeQuestionIndexes: normalizeFlagIndexes(rawReport.mistakeQuestionIndexes, totalQuestions)
        };
    }

    function normalizeOptionRationales(item, options) {
        var raw = item.optionRationales || item.distractorRationales || item.optionExplanations || [];
        var rationales = options.map(function (_option, index) {
            if (Array.isArray(raw)) {
                return normalizeText(raw[index] || "");
            }
            if (isObject(raw)) {
                return normalizeText(raw[index] || raw[String(index)] || raw[optionLetter(index)] || "");
            }
            return "";
        });
        if (rationales.some(Boolean)) {
            return rationales;
        }
        var explanation = normalizeText(item.explanation || "");
        options.forEach(function (_option, index) {
            var letter = optionLetter(index);
            var pattern = new RegExp("(?:رد\\s*(?:گزینه\\s*)?" + letter + "|گزینه\\s*" + letter + ")\\s*[:：\\-]\\s*([\\s\\S]*?)(?=(?:رد\\s*(?:گزینه\\s*)?[الفبپتثجچحخدذرزژسشصضطظعغفقکگلمنوهی]|گزینه\\s*[الفبپتثجچحخدذرزژسشصضطظعغفقکگلمنوهی])\\s*[:：\\-]|$)", "u");
            var match = explanation.match(pattern);
            if (match && match[1]) {
                rationales[index] = normalizeText(match[1]);
            }
        });
        return rationales;
    }

    function normalizeAnswerSections(value) {
        if (!Array.isArray(value)) {
            return [];
        }

        return value.map(function (item) {
            if (!isObject(item)) {
                return null;
            }

            var label = normalizeText(item.label || item.title || item.heading);
            var sectionValue = normalizeText(item.value || item.text || item.body || item.copy);
            if (!label || !sectionValue) {
                return null;
            }

            return {
                label: label,
                value: sectionValue,
                tone: normalizeText(item.tone || "")
            };
        }).filter(Boolean).slice(0, 16);
    }

    function normalizeTopicBreakdown(items) {
        if (!Array.isArray(items)) {
            return [];
        }
        return items.map(function (item) {
            if (!isObject(item)) {
                return null;
            }
            return {
                label: normalizeText(item.label || item.topic || "مبحث"),
                total: maxNumber(item.total, 0),
                correct: maxNumber(item.correct, 0),
                wrong: maxNumber(item.wrong, 0),
                unanswered: maxNumber(item.unanswered, 0),
                percent: clampPercent(item.percent)
            };
        }).filter(Boolean).slice(0, 80);
    }

    function normalizeAttemptHistory(items, totalQuestions) {
        if (!Array.isArray(items)) {
            return [];
        }
        var questionCount = Number.isFinite(Number(totalQuestions))
            ? Math.max(0, Number(totalQuestions))
            : (exam && Array.isArray(exam.questions) ? exam.questions.length : 0);
        return items.map(function (item) {
            if (!isObject(item)) {
                return null;
            }
            return {
                attemptId: normalizeText(item.attemptId),
                totalQuestions: maxNumber(item.totalQuestions, 0),
                correct: maxNumber(item.correct, 0),
                wrong: maxNumber(item.wrong, 0),
                unanswered: maxNumber(item.unanswered, 0),
                percent: clampPercent(item.percent),
                startedAt: normalizeText(item.startedAt),
                submittedAt: normalizeText(item.submittedAt),
                topicBreakdown: normalizeTopicBreakdown(item.topicBreakdown),
                mistakeQuestionIndexes: normalizeFlagIndexes(item.mistakeQuestionIndexes, questionCount)
            };
        }).filter(Boolean).slice(0, 20);
    }

    function normalizeStudyState(rawState, questionsOrCount) {
        var raw = isObject(rawState) ? rawState : {};
        var questionList = Array.isArray(questionsOrCount)
            ? questionsOrCount
            : (exam && Array.isArray(exam.questions) ? exam.questions : []);
        var limit = Array.isArray(questionsOrCount)
            ? questionsOrCount.length
            : Math.max(0, Number(questionsOrCount) || questionList.length);
        var normalized = {
            notesByQuestion: {},
            struckOptionsByQuestion: {},
            highlightsByQuestion: {},
            mistakeQuestionIndexes: normalizeFlagIndexes(raw.mistakeQuestionIndexes, limit),
            updatedAt: normalizeText(raw.updatedAt) || new Date(0).toISOString()
        };
        ["notesByQuestion", "struckOptionsByQuestion", "highlightsByQuestion"].forEach(function (mapKey) {
            var source = isObject(raw[mapKey]) ? raw[mapKey] : {};
            Object.keys(source).forEach(function (key) {
                var index = parseIndex(key);
                if (index < 0 || index >= limit) {
                    return;
                }
                if (mapKey === "notesByQuestion") {
                    var note = normalizeText(source[key]).slice(0, 4000);
                    if (note) {
                        normalized[mapKey][String(index)] = note;
                    }
                    return;
                }
                if (mapKey === "struckOptionsByQuestion") {
                    var options = Array.isArray(source[key]) ? source[key].map(Number).filter(function (option) {
                        var question = questionList[index] || (exam && exam.questions ? exam.questions[index] : null);
                        return Number.isInteger(option) && option >= 0 && question && option < question.options.length;
                    }) : [];
                    if (options.length) {
                        normalized[mapKey][String(index)] = Array.from(new Set(options)).sort(function (left, right) { return left - right; });
                    }
                    return;
                }
                var targetQuestion = questionList[index] || (exam && exam.questions ? exam.questions[index] : null);
                var ranges = mergeHighlightRanges(source[key], targetQuestion ? targetQuestion.question.length : 0);
                if (ranges.length) {
                    normalized[mapKey][String(index)] = ranges;
                }
            });
        });
        return normalized;
    }

    function restoreStudyState(key, serverState, totalQuestions) {
        var server = normalizeStudyState(serverState, totalQuestions);
        try {
            var local = normalizeStudyState(JSON.parse(window.localStorage.getItem(key) || "null"), totalQuestions);
            return Date.parse(local.updatedAt || "") > Date.parse(server.updatedAt || "") ? local : server;
        } catch (_error) {
            return server;
        }
    }

    function mergeStudyStateAfterReport(localState, serverState) {
        var local = normalizeStudyState(localState, exam.questions.length);
        var server = normalizeStudyState(serverState, exam.questions.length);
        return normalizeStudyState({
            notesByQuestion: local.notesByQuestion,
            struckOptionsByQuestion: local.struckOptionsByQuestion,
            highlightsByQuestion: local.highlightsByQuestion,
            mistakeQuestionIndexes: Array.from(new Set(
                local.mistakeQuestionIndexes.concat(server.mistakeQuestionIndexes)
            )).sort(function (left, right) { return left - right; }),
            updatedAt: new Date().toISOString()
        }, exam.questions.length);
    }

    function restoreAssessmentState(key, totalQuestions, report) {
        var empty = {
            answers: report ? report.answers.slice() : createNullArray(totalQuestions),
            startedAt: new Date().toISOString(),
            filter: "all",
            report: report,
            submitting: false,
            queued: "",
            currentQuestionIndex: 0,
            started: false
        };

        try {
            var saved = JSON.parse(window.sessionStorage.getItem(key) || "null");
            if (!isObject(saved)) {
                return empty;
            }

            if (!report) {
                empty.answers = clampAnswers(Array.isArray(saved.answers) ? saved.answers : empty.answers, exam.questions);
            }
            empty.startedAt = normalizeText(saved.startedAt) || empty.startedAt;
            empty.filter = normalizeAssessmentFilter(saved.filter);
            empty.currentQuestionIndex = clampIndex(saved.currentQuestionIndex, totalQuestions);
            empty.started = Boolean(saved.started);
        } catch (_error) {
            return empty;
        }

        return empty;
    }

    function restoreLearningState(key, totalQuestions) {
        var empty = {
            answers: createNullArray(totalQuestions),
            revealed: createFalseArray(totalQuestions),
            startedAt: new Date().toISOString(),
            currentQuestionIndex: 0,
            filter: "all",
            customIndexes: null,
            customLabel: "",
            started: false
        };

        try {
            var saved = JSON.parse(window.sessionStorage.getItem(key) || "null");
            if (!isObject(saved)) {
                return empty;
            }

            empty.answers = clampAnswers(Array.isArray(saved.answers) ? saved.answers : empty.answers, exam.questions);
            empty.revealed = clampRevealed(Array.isArray(saved.revealed) ? saved.revealed : empty.revealed, totalQuestions);
            empty.startedAt = normalizeText(saved.startedAt) || empty.startedAt;
            empty.currentQuestionIndex = clampIndex(saved.currentQuestionIndex, totalQuestions);
            empty.filter = normalizeLearningFilter(saved.filter);
            empty.customIndexes = Array.isArray(saved.customIndexes)
                ? normalizeFlagIndexes(saved.customIndexes, totalQuestions)
                : null;
            empty.customLabel = normalizeText(saved.customLabel).slice(0, 160);
            if (Array.isArray(empty.customIndexes) && !empty.customIndexes.length) {
                empty.customIndexes = null;
                empty.customLabel = "";
            }
            empty.started = Boolean(saved.started);
        } catch (_error) {
            return empty;
        }

        return empty;
    }

    function restoreFlagIndexes(key) {
        try {
            return normalizeFlagIndexes(JSON.parse(window.localStorage.getItem(key) || "[]"), exam.questions.length);
        } catch (_error) {
            return [];
        }
    }

    function normalizeFlagIndexes(value, totalQuestions) {
        if (!Array.isArray(value)) {
            return [];
        }

        var result = [];
        value.forEach(function (item) {
            var index = parseIndex(item);
            if (index >= 0 && index < totalQuestions && result.indexOf(index) === -1) {
                result.push(index);
            }
        });
        result.sort(function (left, right) {
            return left - right;
        });
        return result;
    }

    function clampAnswers(answers, questions) {
        return createNullArray(questions.length).map(function (_item, index) {
            var answer = Array.isArray(answers) ? answers[index] : null;
            var question = questions[index];
            if (question && question.isEssay) {
                return answer === 0 ? 0 : null;
            }
            var optionCount = question ? question.options.length : 0;
            return Number.isInteger(answer) && answer >= 0 && answer < optionCount ? answer : null;
        });
    }

    function clampRevealed(revealed, totalQuestions) {
        return createFalseArray(totalQuestions).map(function (_item, index) {
            return Array.isArray(revealed) ? Boolean(revealed[index]) : false;
        });
    }

    function clampIndex(value, totalQuestions) {
        var index = parseIndex(value);
        if (index < 0) {
            return 0;
        }
        if (index >= totalQuestions) {
            return Math.max(0, totalQuestions - 1);
        }
        return index;
    }

    function normalizeAssessmentFilter(value) {
        var filter = String(value || "all");
        return ["all", "answered", "unanswered", "flagged", "mistakes", "correct", "wrong"].indexOf(filter) >= 0 ? filter : "all";
    }

    function normalizeLearningFilter(value) {
        var filter = String(value || "all");
        return ["all", "flagged", "mistakes"].indexOf(filter) >= 0 ? filter : "all";
    }

    function normalizeMode(value) {
        var mode = String(value || "").trim().toLowerCase();
        if (mode !== "assessment" && mode !== "learning") {
            return null;
        }
        if (!canUseAssessmentMode() && mode === "assessment") {
            return "learning";
        }
        return mode;
    }

    function canUseAssessmentMode() {
        return !(exam.essayOnly || exam.learningOnly);
    }

    function loginHref() {
        if (window.Dent1402Auth && typeof window.Dent1402Auth.loginUrl === "function") {
            return window.Dent1402Auth.loginUrl(window.location.pathname + window.location.search);
        }
        return "/account/";
    }

    function bidiAwareHtml(text) {
        var source = String(text || "");
        var htmlParts = [];
        var lastIndex = 0;
        var match;

        while ((match = BIDI_LTR_RUN_RE.exec(source)) !== null) {
            var offset = match.index;
            htmlParts.push(escapeHtml(source.slice(lastIndex, offset)).replace(/\n/g, "<br>"));
            htmlParts.push('<bdi dir="ltr" class="exam-bidi-ltr">' + escapeHtml(match[0]) + "</bdi>");
            lastIndex = offset + match[0].length;
        }

        htmlParts.push(escapeHtml(source.slice(lastIndex)).replace(/\n/g, "<br>"));
        BIDI_LTR_RUN_RE.lastIndex = 0;
        return htmlParts.join("");
    }

    function richTextHtml(text) {
        return String(text || "")
            .split(/(\*\*[^*]+\*\*)/g)
            .map(function (part) {
                if (!part) {
                    return "";
                }
                if (part.indexOf("**") === 0 && part.lastIndexOf("**") === part.length - 2) {
                    return "<strong>" + bidiAwareHtml(part.slice(2, -2)) + "</strong>";
                }
                return bidiAwareHtml(part);
            })
            .join("");
    }

    function richTextWithHighlights(text, ranges) {
        var source = String(text || "");
        var normalizedRanges = mergeHighlightRanges(ranges, source.length);
        if (!normalizedRanges.length) {
            return richTextHtml(source);
        }
        var parts = [];
        var cursor = 0;
        normalizedRanges.forEach(function (range) {
            if (range.start > cursor) {
                parts.push(richTextHtml(source.slice(cursor, range.start)));
            }
            parts.push('<mark class="exam-question-highlight">' + richTextHtml(source.slice(range.start, range.end)) + "</mark>");
            cursor = range.end;
        });
        if (cursor < source.length) {
            parts.push(richTextHtml(source.slice(cursor)));
        }
        return parts.join("");
    }

    function shouldUseCompactOptions(options) {
        if (options.length < 2 || options.length > 4) {
            return false;
        }

        return options.every(function (option) {
            return option.length <= 95 && option.indexOf("\n") === -1;
        });
    }

    function normalizeText(value) {
        return String(value || "")
            .replace(/\r\n?/g, "\n")
            .replace(/\u00a0/g, " ")
            .trim();
    }

    function stripQuestionNumber(value) {
        return value.replace(/^\s*[0-9۰-۹]+\s*[\)\-–]\s*/, "");
    }

    function clampCorrectIndex(value, optionCount) {
        var parsed = Number(value);
        if (!Number.isInteger(parsed) || parsed < 0 || parsed >= optionCount) {
            return null;
        }
        return parsed;
    }

    function hasResolvedCorrectIndex(correctIndex) {
        return Number.isInteger(correctIndex) && correctIndex >= 0;
    }

    function createNullArray(length) {
        return new Array(length).fill(null);
    }

    function createFalseArray(length) {
        return new Array(length).fill(false);
    }

    function optionLetter(index) {
        return ["الف", "ب", "ج", "د", "هـ", "و", "ز", "ح"][index] || formatValue(index + 1);
    }

    function formatValue(value) {
        return Number(value || 0).toLocaleString("fa-IR");
    }

    function formatPercent(value) {
        var numeric = clampPercent(value);
        var hasFraction = Math.abs(numeric - Math.round(numeric)) > 0.001;
        return numeric.toLocaleString("fa-IR", {
            minimumFractionDigits: hasFraction ? 1 : 0,
            maximumFractionDigits: 1
        }) + "٪";
    }

    function formatSignedPercent(value) {
        var numeric = Number(value);
        if (!Number.isFinite(numeric)) {
            numeric = 0;
        }
        var rounded = Math.round(numeric * 10) / 10;
        var absolute = Math.abs(rounded);
        var hasFraction = Math.abs(absolute - Math.round(absolute)) > 0.001;
        return (rounded > 0 ? "+" : (rounded < 0 ? "−" : "")) + absolute.toLocaleString("fa-IR", {
            minimumFractionDigits: hasFraction ? 1 : 0,
            maximumFractionDigits: 1
        }) + "٪";
    }

    function formatDateTime(value) {
        var raw = normalizeText(value);
        if (!raw) {
            return "—";
        }

        var parsed = new Date(raw);
        if (!Number.isFinite(parsed.getTime())) {
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

    function clampPercent(value) {
        var numeric = Number(value || 0);
        if (!Number.isFinite(numeric)) {
            return 0;
        }
        if (numeric < 0) {
            return 0;
        }
        if (numeric > 100) {
            return 100;
        }
        return Math.round(numeric * 10) / 10;
    }

    function maxNumber(value, fallback) {
        var numeric = Number(value);
        if (!Number.isFinite(numeric)) {
            return fallback;
        }
        return Math.max(fallback, numeric);
    }

    function parseIndex(value) {
        var parsed = Number(value);
        return Number.isInteger(parsed) ? parsed : -1;
    }

    function isValidQuestionIndex(index) {
        return Number.isInteger(index) && index >= 0 && index < exam.questions.length;
    }

    function isObject(value) {
        return !!value && typeof value === "object" && !Array.isArray(value);
    }

    function escapeHtml(value) {
        return String(value == null ? "" : value).replace(/[&<>"]/g, function (char) {
            switch (char) {
                case "&":
                    return "&amp;";
                case "<":
                    return "&lt;";
                case ">":
                    return "&gt;";
                case '"':
                    return "&quot;";
                default:
                    return char;
            }
        });
    }
})();
