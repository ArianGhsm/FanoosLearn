(function () {
    "use strict";

    function $(id) {
        return document.getElementById(id);
    }

    if (!window.Dent1402Auth) {
        return;
    }

    var stageBoot = $("account-boot");
    var stageLogin = $("account-login");
    var stagePanel = $("account-panel");
    var bootText = $("account-boot-text");

    var loginForm = $("login-form");
    var loginSubmit = $("login-submit");
    var loginFeedback = $("login-feedback");
    var loginCopy = $("account-login-copy");
    var authBrand = $("account-auth-brand");
    var accountFooterBrand = $("account-footer-brand");
    var loginMethodSwitch = $("login-method-switch");
    var loginMethodPasswordBtn = $("login-method-password");
    var loginMethodOtpBtn = $("login-method-otp");
    var loginMethodSignupBtn = $("login-method-signup");
    var loginPasswordResetBtn = $("login-password-reset");
    var loginOtpForm = $("login-otp-form");
    var loginOtpRequestButton = $("login-otp-request");
    var loginOtpSubmitButton = $("login-otp-submit");
    var loginOtpFeedback = $("login-otp-feedback");
    var loginOtpMeta = $("login-otp-meta");
    var loginOtpVerifyGroup = $("login-otp-verify-group");
    var loginPhoneInput = $("login-phone-number");
    var loginPhoneError = $("login-phone-error");
    var loginOtpCodeInput = $("login-otp-code");
    var loginOtpCodeError = $("login-otp-code-error");
    var loginOtpSlots = $("login-otp-slots");
    var loginOtpSummary = $("login-otp-summary");
    var loginOtpPhoneDisplay = $("login-otp-phone-display");
    var loginOtpEditPhoneButton = $("login-otp-edit-phone");
    var externalSignupForm = $("external-signup-form");
    var externalSignupFirstName = $("external-signup-first-name");
    var externalSignupLastName = $("external-signup-last-name");
    var externalSignupPhone = $("external-signup-phone");
    var externalSignupPassword = $("external-signup-password");
    var externalSignupPasswordConfirm = $("external-signup-password-confirm");
    var externalSignupPasswordToggle = $("external-signup-password-toggle");
    var externalSignupPasswordConfirmToggle = $("external-signup-password-confirm-toggle");
    var externalSignupOtpCode = $("external-signup-otp-code");
    var externalSignupRequestButton = $("external-signup-request");
    var externalSignupSubmitButton = $("external-signup-submit");
    var externalSignupFeedback = $("external-signup-feedback");
    var externalSignupMeta = $("external-signup-meta");
    var externalSignupVerifyGroup = $("external-signup-verify-group");
    var externalSignupDetails = $("external-signup-details");
    var externalSignupRequestActions = $("external-signup-request-actions");
    var externalSignupOtpSlots = $("external-signup-otp-slots");
    var externalSignupSummary = $("external-signup-summary");
    var externalSignupPhoneDisplay = $("external-signup-phone-display");
    var externalSignupEditPhoneButton = $("external-signup-edit-phone");
    var passwordResetForm = $("password-reset-form");
    var passwordResetPhone = $("password-reset-phone");
    var passwordResetOtpCode = $("password-reset-otp-code");
    var passwordResetNewPassword = $("password-reset-new-password");
    var passwordResetConfirmPassword = $("password-reset-confirm-password");
    var passwordResetNewPasswordToggle = $("password-reset-new-password-toggle");
    var passwordResetConfirmPasswordToggle = $("password-reset-confirm-password-toggle");
    var passwordResetRequestButton = $("password-reset-request");
    var passwordResetSubmitButton = $("password-reset-submit");
    var passwordResetBackButton = $("password-reset-back");
    var passwordResetFeedback = $("password-reset-feedback");
    var passwordResetMeta = $("password-reset-meta");
    var passwordResetVerifyGroup = $("password-reset-verify-group");

    var profileForm = $("profile-form");
    var profileSubmit = $("profile-submit");
    var profileFeedback = $("profile-feedback");
    var profileAvatarFeedback = $("profile-avatar-feedback");
    var profileAvatarFile = $("profile-avatar-file");
    var profileAvatarClear = $("profile-avatar-clear");
    var accountAvatar = $("account-avatar");
    var accountAvatarImage = $("account-avatar-image");
    var accountAvatarFallback = $("account-avatar-fallback");
    var accountRotation = $("account-rotation");
    var accountDisNumber = $("account-dis-number");
    var profileAvatarPreview = $("profile-avatar-preview");
    var profileAvatarImage = $("profile-avatar-image");
    var profileAvatarFallback = $("profile-avatar-fallback");

    var securityForm = $("security-form");
    var securitySubmit = $("security-submit");
    var securityFeedback = $("security-feedback");

    var logoutSubmit = $("logout-submit");

    var ownerSearch = $("owner-search");
    var ownerSummary = $("owner-summary");
    var ownerFeedback = $("owner-feedback");
    var ownerToolbarTitle = $("owner-toolbar-title");
    var ownerToolbarMeta = $("owner-toolbar-meta");
    var ownerCohortSummary = $("owner-cohort-summary");
    var ownerCohortGrid = $("owner-cohort-grid");
    var ownerCohortSelect = $("owner-cohort-select");
    var ownerCohortFeedback = $("owner-cohort-feedback");
    var ownerTabs = $("owner-tabs");
    var ownerTabPanels = Array.prototype.slice.call(document.querySelectorAll("[data-owner-tab-panel]"));
    var representativeList = $("representative-list");
    var ownerUserList = $("owner-user-list");
    var ownerUserPager = $("owner-user-pager");
    var ownerCreateCohortSection = $("owner-create-cohort-section");
    var ownerCreateCohortForm = $("owner-create-cohort-form");
    var ownerCohortTitle = $("owner-cohort-title");
    var ownerCohortShortTitle = $("owner-cohort-short-title");
    var ownerCohortYear = $("owner-cohort-year");
    var ownerCohortProductType = $("owner-cohort-product-type");
    var ownerCohortNotesMode = $("owner-cohort-notes-mode");
    var ownerCohortServiceNotes = $("owner-cohort-service-notes");
    var ownerCohortServiceForms = $("owner-cohort-service-forms");
    var ownerCohortServiceGrades = $("owner-cohort-service-grades");
    var ownerCohortServiceNavid = $("owner-cohort-service-navid");
    var ownerCohortServiceBuy = $("owner-cohort-service-buy");
    var ownerCohortAllowRepresentative = $("owner-cohort-allow-representative");
    var ownerCreateCohortSubmit = $("owner-create-cohort-submit");
    var ownerCreateCohortFeedback = $("owner-create-cohort-feedback");
    var ownerCreateStudentForm = $("owner-create-student-form");
    var ownerStudentFirstName = $("owner-student-first-name");
    var ownerStudentLastName = $("owner-student-last-name");
    var ownerStudentNumber = $("owner-student-number");
    var ownerStudentPassword = $("owner-student-password");
    var ownerStudentRole = $("owner-student-role");
    var ownerStudentRotationMode = $("owner-student-rotation-mode");
    var ownerStudentRotationId = $("owner-student-rotation-id");
    var ownerStudentGroupNumber = $("owner-student-group-number");
    var ownerCreateStudentSubmit = $("owner-create-student-submit");
    var ownerMarkCampusStudentsButton = $("owner-mark-campus-students");
    var ownerCreateStudentFeedback = $("owner-create-student-feedback");
    var ownerImportUsersForm = $("owner-import-users-form");
    var ownerImportUsersDefaultPassword = $("owner-import-users-default-password");
    var ownerImportUsersText = $("owner-import-users-text");
    var ownerImportUsersFile = $("owner-import-users-file");
    var ownerImportUsersSubmit = $("owner-import-users-submit");
    var ownerImportUsersFeedback = $("owner-import-users-feedback");
    var ownerStatsRefreshButton = $("owner-stats-refresh");
    var ownerStatsFeedback = $("owner-stats-feedback");
    var ownerStatsMeta = $("owner-stats-meta");
    var ownerStatsOverview = $("owner-stats-overview");
    var ownerStatsVisitsChart = $("owner-stats-chart-visits");
    var ownerStatsLoginsChart = $("owner-stats-chart-logins");
    var ownerStatsDownloadsChart = $("owner-stats-chart-downloads");
    var ownerStatsFamilies = $("owner-stats-families");
    var ownerStatsPages = $("owner-stats-pages");
    var ownerStatsDownloads = $("owner-stats-downloads");
    var ownerStatsCohorts = $("owner-stats-cohorts");
    var ownerStatsRecentLogins = $("owner-stats-recent-logins");
    var ownerStatsMethods = $("owner-stats-methods");
    var ownerStatsExams = $("owner-stats-exams");
    var ownerStatsReferences = $("owner-stats-references");
    var ownerStatsRetention = $("owner-stats-retention");
    var ownerStatsFunnel = $("owner-stats-funnel");
    var ownerStatsErrors = $("owner-stats-errors");
    var ownerStatsErrorsMeta = $("owner-stats-errors-meta");
    var ownerGradesCoursesSummary = $("owner-grades-courses-summary");
    var ownerGradesImportForm = $("owner-grades-import-form");
    var ownerGradesImportText = $("owner-grades-import-text");
    var ownerGradesImportFile = $("owner-grades-import-file");
    var ownerGradesImportSubmit = $("owner-grades-import-submit");
    var ownerGradesCourseSelect = $("owner-grades-course-select");
    var ownerGradesDeleteCourseButton = $("owner-grades-delete-course");
    var ownerGradesResetAllButton = $("owner-grades-reset-all");
    var ownerGradesFeedback = $("owner-grades-feedback");
    var ownerUserPanel = $("owner-user-panel");
    var ownerUserPanelBack = $("owner-user-panel-back");
    var ownerUserPanelTitle = $("owner-user-panel-title");
    var ownerUserPanelSubtitle = $("owner-user-panel-subtitle");
    var ownerUserPanelSummary = $("owner-user-panel-summary");
    var ownerUserPanelFeedback = $("owner-user-panel-feedback");
    var ownerUserPanelBody = $("owner-user-panel-body");
    var navidConfigForm = $("navid-config-form");
    var navidOwnerStatus = $("navid-owner-status");
    var navidLoginUrlInput = $("navid-login-url");
    var navidSyncIntervalInput = $("navid-sync-interval");
    var navidCaptchaStrategyInput = $("navid-captcha-strategy");
    var navidUsernameInput = $("navid-username");
    var navidPasswordInput = $("navid-password");
    var navidSyncNowButton = $("navid-sync-now");
    var navidConfigFeedback = $("navid-config-feedback");
    var navidGetCaptchaButton = $("navid-get-captcha");
    var navidCompleteReconnectButton = $("navid-complete-reconnect");
    var navidCaptchaImage = $("navid-captcha-image");
    var navidCaptchaCodeInput = $("navid-captcha-code");
    var navidReconnectFeedback = $("navid-reconnect-feedback");

    var accountHubPanel = $("account-hub-panel");
    var accountSurfaceLayout = $("account-surface-layout");
    var accountPhoneNudge = $("account-phone-nudge");
    var accountPhoneNudgeOpen = $("account-phone-nudge-open");
    var accountPhoneNudgeDismiss = $("account-phone-nudge-dismiss");
    var ownerHubSection = $("account-owner-section");
    var accountInfoRole = $("account-info-role");
    var accountInfoSession = $("account-info-session");
    var accountInfoRotation = $("account-info-rotation");
    var accountInfoDisNumber = $("account-info-dis-number");
    var accountRowProfileMeta = $("account-row-profile-meta");
    var accountRowInfoMeta = $("account-row-info-meta");
    var accountRowOwnerMeta = $("account-row-owner-meta");
    var accountOwnerAppearanceShortcut = $("account-owner-appearance-shortcut");
    var accountOwnerStatsShortcut = $("account-owner-stats-shortcut");
    var accountRowOwnerStatsMeta = $("account-row-owner-stats-meta");
    var accountRowNavidMeta = $("account-row-navid-meta");
    var accountRowPhoneMeta = $("account-row-phone-meta");
    var accountRowNotificationsMeta = $("account-row-notifications-meta");
    var accountNavidAlertCard = $("account-navid-alert-card");
    var accountNotificationAlertKind = $("account-notification-alert-kind");
    var accountNavidAlertTime = $("account-navid-alert-time");
    var accountNavidAlertTitle = $("account-navid-alert-title");
    var accountNavidAlertBody = $("account-navid-alert-body");
    var accountNavidAlertLink = $("account-navid-alert-link");
    var accountNavidAlertMarkRead = $("account-navid-alert-mark-read");
    var surfaceOpeners = Array.prototype.slice.call(document.querySelectorAll("[data-open-surface]"));
    var surfaceBackButtons = Array.prototype.slice.call(document.querySelectorAll("[data-surface-back]"));
    var surfacePanels = Array.prototype.slice.call(document.querySelectorAll(".account-surface-panel[data-surface]"));

    var phoneStatusSummary = $("phone-status-summary");
    var phoneStatusBadge = $("phone-status-badge");
    var phoneNumberState = $("phone-number-state");
    var phoneVerifyState = $("phone-verify-state");
    var phoneLoginState = $("phone-login-state");
    var phoneCurrentNumber = $("phone-current-number");
    var phoneCurrentCaption = $("phone-current-caption");
    var phoneNumberEditButton = $("phone-number-edit");
    var phoneNumberRemoveButton = $("phone-number-remove");
    var phoneManageFeedback = $("phone-manage-feedback");
    var phoneEnrollNumber = $("phone-enroll-number");
    var phoneEnrollCode = $("phone-enroll-code");
    var phoneEnrollOtpSlots = $("phone-enroll-otp-slots");
    var phoneEnrollRequestButton = $("phone-enroll-request");
    var phoneEnrollSubmitButton = $("phone-enroll-submit");
    var phoneEnrollMeta = $("phone-enroll-meta");
    var phoneEnrollFeedback = $("phone-enroll-feedback");
    var phoneLoginEnabledInput = $("phone-login-enabled");
    var phoneLoginSaveButton = $("phone-login-save");
    var phoneLoginToggleHint = $("phone-login-toggle-hint");
    var phoneToggleFeedback = $("phone-toggle-feedback");

    var ownerSmsStatus = $("owner-sms-status");
    var ownerSmsForm = $("owner-sms-form");
    var ownerSmsEnabled = $("owner-sms-enabled");
    var ownerSmsPatternCode = $("owner-sms-pattern-code");
    var ownerSmsApiKey = $("owner-sms-api-key");
    var ownerSmsClearApi = $("owner-sms-clear-api");
    var ownerSmsSenderLine = $("owner-sms-sender-line");
    var ownerSmsDomain = $("owner-sms-domain");
    var ownerSmsCodeParam = $("owner-sms-code-param");
    var ownerSmsTestPhone = $("owner-sms-test-phone");
    var ownerSmsSaveButton = $("owner-sms-save");
    var ownerSmsHealthButton = $("owner-sms-health");
    var ownerSmsFeedback = $("owner-sms-feedback");

    var ownerMediaStatus = $("owner-media-status");
    var ownerMediaRefreshButton = $("owner-media-refresh");
    var ownerMediaCleanupButton = $("owner-media-cleanup");
    var ownerMediaFeedback = $("owner-media-feedback");
    var notificationsSummary = $("notifications-summary");
    var notificationsPrefsCard = $("notifications-prefs-card");
    var notificationsNavidRow = $("notifications-navid-row");
    var notificationsNavidAlertsToggle = $("notifications-navid-alerts");
    var notificationsFormRemindersToggle = $("notifications-form-reminders");
    var notificationsPaymentRemindersToggle = $("notifications-payment-reminders");
    var notificationsDigestEnabledToggle = $("notifications-digest-enabled");
    var notificationsDigestHourInput = $("notifications-digest-hour");
    var notificationsPrefsSaveButton = $("notifications-prefs-save");
    var notificationsPushCard = $("notifications-push-card");
    var notificationsPushToggle = $("notifications-push-toggle");
    var notificationsPushHint = $("notifications-push-hint");
    var notificationsPushStatus = $("notifications-push-status");
    var notificationsPushState = { busy: false };
    var notificationsPrefsHint = $("notifications-prefs-hint");
    var notificationsManagerCard = $("notifications-manager-card");
    var notificationsComposeShell = $("notifications-compose-shell");
    var notificationsBroadcastForm = $("notifications-broadcast-form");
    var notificationsTargetSelect = $("notifications-target");
    var notificationsTitleInput = $("notifications-title");
    var notificationsBodyInput = $("notifications-body");
    var notificationsCtaLabelInput = $("notifications-cta-label");
    var notificationsCtaHrefInput = $("notifications-cta-href");
    var notificationsScheduleInput = $("notifications-schedule-at");
    var notificationsSendSmsInput = $("notifications-send-sms");
    var notificationsBroadcastSubmit = $("notifications-broadcast-submit");
    var notificationsManagerFeedback = $("notifications-manager-feedback");
    var notificationsRefreshButton = $("notifications-refresh");
    var notificationsMarkAllButton = $("notifications-mark-all");
    var notificationsFilters = $("notifications-filters");
    var notificationsFeedback = $("notifications-feedback");
    var notificationsEmpty = $("notifications-empty");
    var notificationsList = $("notifications-list");
    var accountRowBotsMeta = $("account-row-bots-meta");
    var botConnectionsFeedback = $("bot-connections-feedback");
    var botConnectionsList = $("bot-connections-list");
    var botConnectionsRefresh = $("bot-connections-refresh");

    var redirectedAfterLogin = false;
    var pendingReturnTo = safeReturnTo(new URLSearchParams(window.location.search).get("returnTo"));
    var activeSurface = "hub";
    var currentUser = null;
    var ownerState = {
        loading: false,
        creatingCohort: false,
        importingUsers: false,
        savingStudentNumber: "",
        savingPasswordStudentNumber: "",
        savingRotationStudentNumber: "",
        campusMarking: false,
        creatingStudent: false,
        deletingStudentNumber: "",
        clearingExamStudyStudentNumber: "",
        removingPhoneStudentNumber: "",
        loadingGradesStudentNumber: "",
        savingGradeKey: "",
        activeUserPanelStudentNumber: "",
        importingGrades: false,
        deletingGradeCourseKey: "",
        resettingGrades: false,
        activeTab: "users",
        activeCohortKey: "",
        userPage: 1,
        userPageSize: 18,
        viewer: null,
        cohorts: [],
        availableCohorts: [],
        users: [],
        gradePayloadByStudent: {},
        gradeCourses: [],
        rotationCatalog: []
    };
    var navidState = {
        loading: false,
        syncing: false,
        loaded: false,
        ownerStatus: null
    };
    var ownerAnalyticsState = {
        loading: false,
        loaded: false,
        dashboard: null
    };
    var smsState = {
        loading: false,
        status: null
    };
    var mediaState = {
        loading: false,
        status: null
    };
    var notificationsState = {
        loading: false,
        requestToken: 0,
        loadedForUserKey: "",
        summary: null,
        preview: null,
        preferences: null,
        draftPreferences: null,
        manager: null,
        items: [],
        activeFilter: "all",
        savingPrefs: false,
        markingAll: false,
        broadcasting: false,
        markingIds: {},
        snoozingIds: {},
        audienceById: {},
        audienceLoadingIds: {},
        expandedAudienceId: "",
        deletingId: ""
    };
    var botConnectionsState = {
        loading: false,
        loadedForUserKey: "",
        csrfToken: "",
        connections: null,
        disconnectingPlatform: ""
    };
    var loginMode = "otp";
    var loginOtpCooldownUntil = 0;
    var phoneEnrollCooldownUntil = 0;
    var externalSignupCooldownUntil = 0;
    var passwordResetCooldownUntil = 0;
    var loginOtpCooldownTimer = null;
    var phoneEnrollCooldownTimer = null;
    var externalSignupCooldownTimer = null;
    var passwordResetCooldownTimer = null;
    var loginOtpRequesting = false;
    var loginOtpSubmitting = false;
    var loginOtpAutoSubmitQueued = false;
    var externalSignupRequesting = false;
    var externalSignupSubmitting = false;
    var externalSignupAutoSubmitQueued = false;
    var passwordResetRequesting = false;
    var passwordResetSubmitting = false;
    var otpCredentialAbortController = null;
    var profileDraftAvatarUrl = "";
    var profileSaving = false;
    var profileAvatarProcessing = false;
    var loginViewportTickTimer = null;

    function isProsthesisUser(user) {
        return !!(user && user.isProsthesisStudent);
    }

    function loginContextIsProsthesis() {
        var returnTo = String(pendingReturnTo || "");
        if (returnTo.indexOf("/prosthesis-1402/") === 0) {
            return true;
        }
        try {
            var target = new URL(returnTo, window.location.origin);
            if (target.pathname.indexOf("/prosthesis-1402/") === 0) {
                return true;
            }
            return String(target.searchParams.get("cohort") || "").trim().toLowerCase() === "prosthesis-1402";
        } catch (_error) {
            return false;
        }
    }

    function applyAccountBranding(user) {
        var isProsthesis = isProsthesisUser(user) || (!user && loginContextIsProsthesis());
        var shortBrand = isProsthesis ? "ورودی ۱۴۰۲ پروتز" : "ورودی ۱۴۰۲";
        var fullBrand = isProsthesis ? "ورودی ۱۴۰۲ پروتز تهران" : "ورودی ۱۴۰۲ دندانپزشکی تهران";
        if (authBrand) {
            authBrand.textContent = shortBrand;
        }
        if (accountFooterBrand) {
            accountFooterBrand.textContent = fullBrand;
        }
        document.title = isProsthesis
            ? "حساب کاربری | ورودی ۱۴۰۲ پروتز"
            : "حساب کاربری | ورودی ۱۴۰۲ دندانپزشکی";
    }

    function ensureExternalSignupUi() {
        var usernameLabel = document.querySelector('label[for="login-student-number"]');
        var usernameInput = $("login-student-number");
        if (usernameLabel) {
            usernameLabel.textContent = "شماره دانشجویی یا موبایل";
        }
        if (usernameInput) {
            usernameInput.placeholder = "40211272003 یا 09123456789";
            usernameInput.setAttribute("inputmode", "numeric");
        }
        if (!loginMethodSignupBtn && loginMethodSwitch && loginMethodSwitch.parentNode) {
            loginMethodSignupBtn = document.createElement("button");
            loginMethodSignupBtn.type = "button";
            loginMethodSignupBtn.className = "login-signup-prompt";
            loginMethodSignupBtn.id = "login-method-signup";
            loginMethodSignupBtn.innerHTML = 'حساب کاربری ندارید؟ <span>ثبت نام کنید.</span>';
            loginMethodSwitch.parentNode.insertBefore(loginMethodSignupBtn, loginMethodSwitch);
        }
        if (!loginMethodSwitch || !loginOtpForm || externalSignupForm) {
            return;
        }

        externalSignupForm = document.createElement("form");
        externalSignupForm.className = "account-form external-signup-form";
        externalSignupForm.id = "external-signup-form";
        externalSignupForm.hidden = true;
        externalSignupForm.autocomplete = "on";
        externalSignupForm.noValidate = true;
        externalSignupForm.innerHTML = [
            '<div class="otp-auth-panel external-signup-panel">',
            '  <div class="otp-auth-panel__hero">',
            '    <h4>ثبت نام برای آزمون ها</h4>',
            '    <p>برای کاربران خارج از دانشکده، شماره موبایل نام کاربری حساب خواهد بود.</p>',
            '  </div>',
            '  <div class="external-signup-grid" id="external-signup-details">',
            '    <label for="external-signup-first-name">نام</label>',
            '    <input id="external-signup-first-name" name="firstName" type="text" autocomplete="given-name" required>',
            '    <label for="external-signup-last-name">نام خانوادگی</label>',
            '    <input id="external-signup-last-name" name="lastName" type="text" autocomplete="family-name" required>',
            '    <label for="external-signup-phone">شماره موبایل</label>',
            '    <input id="external-signup-phone" name="phoneNumber" type="tel" inputmode="tel" autocomplete="tel" dir="ltr" placeholder="09123456789" data-digit-locale="latin" required>',
            '    <label for="external-signup-password">رمز عبور</label>',
            '    <div class="external-password-field">',
            '      <input id="external-signup-password" name="password" type="password" autocomplete="new-password" minlength="6" required>',
            '      <button class="external-password-toggle" id="external-signup-password-toggle" type="button" aria-controls="external-signup-password" aria-pressed="false">نمایش</button>',
            '    </div>',
            '    <label for="external-signup-password-confirm">تکرار رمز عبور</label>',
            '    <div class="external-password-field">',
            '      <input id="external-signup-password-confirm" name="passwordConfirm" type="password" autocomplete="new-password" minlength="6" required>',
            '      <button class="external-password-toggle" id="external-signup-password-confirm-toggle" type="button" aria-controls="external-signup-password-confirm" aria-pressed="false">نمایش</button>',
            '    </div>',
            '  </div>',
            '  <div class="otp-auth-panel__actions otp-auth-panel__actions--request" id="external-signup-request-actions">',
            '    <button class="shell-action-btn shell-action-btn-primary" id="external-signup-request" type="button">ارسال کد تایید</button>',
            '    <p class="account-inline-meta" id="external-signup-meta"></p>',
            '  </div>',
            '  <div class="external-signup-verify" id="external-signup-verify-group" hidden>',
            '    <div class="otp-verify-head">',
            '      <h4>کد تایید را وارد کن</h4>',
            '      <p id="external-signup-summary">کد تایید ارسال‌شده را وارد کن.</p>',
            '      <strong id="external-signup-phone-display" class="otp-verify-phone" dir="ltr"></strong>',
            '    </div>',
            '    <label for="external-signup-otp-code">کد تایید</label>',
            '    <div id="external-signup-otp-field" class="otp-slot-field" data-otp-field="signup">',
            '      <input id="external-signup-otp-code" name="otpCode" type="text" inputmode="numeric" autocomplete="one-time-code" autocapitalize="off" autocorrect="off" spellcheck="false" enterkeyhint="done" pattern="[0-9]*" maxlength="6" aria-label="کد تایید ثبت نام" aria-describedby="external-signup-summary external-signup-feedback" dir="ltr" data-latin-digits="true" placeholder="کد ۶ رقمی">',
            '      <div id="external-signup-otp-slots" class="otp-slot-field__slots" dir="ltr" aria-hidden="true">',
            '        <span class="otp-slot"></span>',
            '        <span class="otp-slot"></span>',
            '        <span class="otp-slot"></span>',
            '        <span class="otp-slot"></span>',
            '        <span class="otp-slot"></span>',
            '        <span class="otp-slot"></span>',
            '      </div>',
            '    </div>',
            '    <button class="shell-action-btn shell-action-btn-primary" id="external-signup-submit" type="submit" disabled>تکمیل ثبت نام</button>',
            '    <button class="otp-edit-phone-btn" id="external-signup-edit-phone" type="button">بازگشت و ویرایش شماره</button>',
            '  </div>',
            '  <p class="account-feedback" id="external-signup-feedback" role="status" aria-live="polite"></p>',
            '</div>'
        ].join("");
        loginMethodSwitch.parentNode.insertBefore(externalSignupForm, loginMethodSwitch);

        externalSignupFirstName = $("external-signup-first-name");
        externalSignupLastName = $("external-signup-last-name");
        externalSignupPhone = $("external-signup-phone");
        externalSignupPassword = $("external-signup-password");
        externalSignupPasswordConfirm = $("external-signup-password-confirm");
        externalSignupPasswordToggle = $("external-signup-password-toggle");
        externalSignupPasswordConfirmToggle = $("external-signup-password-confirm-toggle");
        externalSignupOtpCode = $("external-signup-otp-code");
        externalSignupRequestButton = $("external-signup-request");
        externalSignupSubmitButton = $("external-signup-submit");
        externalSignupFeedback = $("external-signup-feedback");
        externalSignupMeta = $("external-signup-meta");
        externalSignupVerifyGroup = $("external-signup-verify-group");
        externalSignupDetails = $("external-signup-details");
        externalSignupRequestActions = $("external-signup-request-actions");
        externalSignupOtpSlots = $("external-signup-otp-slots");
        externalSignupSummary = $("external-signup-summary");
        externalSignupPhoneDisplay = $("external-signup-phone-display");
        externalSignupEditPhoneButton = $("external-signup-edit-phone");
    }

    function ensurePasswordResetUi() {
        if (!loginPasswordResetBtn && loginForm && loginForm.parentNode) {
            loginPasswordResetBtn = document.createElement("button");
            loginPasswordResetBtn.type = "button";
            loginPasswordResetBtn.className = "login-signup-prompt login-reset-prompt";
            loginPasswordResetBtn.id = "login-password-reset";
            loginPasswordResetBtn.innerHTML = 'رمز عبور خود را فراموش کرده‌اید؟ <span>بازیابی با کد تایید</span>';
            loginForm.parentNode.insertBefore(loginPasswordResetBtn, loginForm.nextSibling);
        }
        if (!loginMethodSwitch || passwordResetForm) {
            return;
        }

        passwordResetForm = document.createElement("form");
        passwordResetForm.className = "account-form otp-auth-panel password-reset-form";
        passwordResetForm.id = "password-reset-form";
        passwordResetForm.hidden = true;
        passwordResetForm.autocomplete = "on";
        passwordResetForm.noValidate = true;
        passwordResetForm.innerHTML = [
            '<div class="otp-auth-panel__hero password-reset-hero">',
            '  <h4>بازیابی رمز عبور</h4>',
            '  <p>شماره موبایل تاییدشده حساب را وارد کن. بعد از تایید کد، رمز جدید روی همان حساب ذخیره می‌شود.</p>',
            '</div>',
            '<div class="settings-row auth-field otp-login-step otp-login-step--phone">',
            '  <label for="password-reset-phone">شماره موبایل حساب</label>',
            '  <div class="otp-phone-shell" dir="ltr">',
            '    <span class="otp-phone-shell__country" aria-hidden="true">',
            '      <span class="otp-phone-shell__flag">IR</span>',
            '      <span class="otp-phone-shell__prefix">+۹۸</span>',
            '    </span>',
            '    <input id="password-reset-phone" name="phoneNumber" type="tel" inputmode="numeric" autocomplete="tel-national" autocapitalize="off" autocorrect="off" spellcheck="false" enterkeyhint="next" maxlength="14" pattern="(09[0-9۰-۹]{9}|9[0-9۰-۹]{9}|\\+989[0-9۰-۹]{9})" aria-label="شماره موبایل حساب" dir="ltr" data-phone-input="iran" data-display-digits="persian" placeholder="09123456789" required>',
            '  </div>',
            '</div>',
            '<div class="account-inline-actions otp-auth-panel__actions otp-auth-panel__actions--request">',
            '  <button class="shell-action-btn shell-action-btn-primary" id="password-reset-request" type="button">ارسال کد بازیابی</button>',
            '  <p class="account-inline-meta" id="password-reset-meta"></p>',
            '</div>',
            '<div class="password-reset-verify" id="password-reset-verify-group" hidden>',
            '  <label for="password-reset-otp-code">کد تایید</label>',
            '  <input id="password-reset-otp-code" name="otpCode" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" dir="ltr" data-digit-locale="latin" placeholder="کد ۶ رقمی">',
            '  <label for="password-reset-new-password">رمز جدید</label>',
            '  <div class="external-password-field">',
            '    <input id="password-reset-new-password" name="newPassword" type="password" autocomplete="new-password" minlength="6" required>',
            '    <button class="external-password-toggle" id="password-reset-new-password-toggle" type="button" aria-controls="password-reset-new-password" aria-pressed="false">نمایش</button>',
            '  </div>',
            '  <label for="password-reset-confirm-password">تکرار رمز جدید</label>',
            '  <div class="external-password-field">',
            '    <input id="password-reset-confirm-password" name="confirmPassword" type="password" autocomplete="new-password" minlength="6" required>',
            '    <button class="external-password-toggle" id="password-reset-confirm-password-toggle" type="button" aria-controls="password-reset-confirm-password" aria-pressed="false">نمایش</button>',
            '  </div>',
            '  <button class="shell-action-btn shell-action-btn-primary" id="password-reset-submit" type="submit" disabled>ثبت رمز جدید</button>',
            '</div>',
            '<button class="otp-edit-phone-btn password-reset-back" id="password-reset-back" type="button">بازگشت به ورود با رمز عبور</button>',
            '<p class="account-feedback" id="password-reset-feedback" role="status" aria-live="polite"></p>'
        ].join("");
        loginMethodSwitch.parentNode.insertBefore(passwordResetForm, loginMethodSwitch);

        passwordResetPhone = $("password-reset-phone");
        passwordResetOtpCode = $("password-reset-otp-code");
        passwordResetNewPassword = $("password-reset-new-password");
        passwordResetConfirmPassword = $("password-reset-confirm-password");
        passwordResetNewPasswordToggle = $("password-reset-new-password-toggle");
        passwordResetConfirmPasswordToggle = $("password-reset-confirm-password-toggle");
        passwordResetRequestButton = $("password-reset-request");
        passwordResetSubmitButton = $("password-reset-submit");
        passwordResetBackButton = $("password-reset-back");
        passwordResetFeedback = $("password-reset-feedback");
        passwordResetMeta = $("password-reset-meta");
        passwordResetVerifyGroup = $("password-reset-verify-group");
    }

    function updatePasswordToggle(button, input) {
        if (!button || !input) {
            return;
        }
        var visible = input.type === "text";
        button.textContent = visible ? "پنهان" : "نمایش";
        button.setAttribute("aria-pressed", visible ? "true" : "false");
        button.setAttribute("aria-label", visible ? "پنهان کردن رمز عبور" : "نمایش رمز عبور");
    }

    function bindPasswordToggle(button, input) {
        if (!button || !input || button.dataset.passwordToggleBound === "1") {
            return;
        }
        button.dataset.passwordToggleBound = "1";
        updatePasswordToggle(button, input);
        button.addEventListener("click", function () {
            var selectionStart = input.selectionStart;
            var selectionEnd = input.selectionEnd;
            input.type = input.type === "password" ? "text" : "password";
            updatePasswordToggle(button, input);
            input.focus({ preventScroll: true });
            if (typeof selectionStart === "number" && typeof selectionEnd === "number" && input.setSelectionRange) {
                input.setSelectionRange(selectionStart, selectionEnd);
            }
        });
    }

    function syncLoginHeading() {
        if (!loginCopy) {
            return;
        }
        if (loginMode === "signup") {
            loginCopy.textContent = "نام، موبایل و رمز عبور را وارد کن تا با کد تایید ثبت نام کامل شود.";
            return;
        }
        if (loginMode === "reset") {
            loginCopy.textContent = "برای بازیابی، شماره موبایل تاییدشده حساب و رمز جدید را با کد تایید ثبت کن.";
            return;
        }
        loginCopy.textContent = loginMode === "otp"
            ? "شماره موبایل خود را وارد کنید."
            : "شماره دانشجویی و رمز عبور خود را وارد کنید.";
    }

    function safeReturnTo(value) {
        if (!value || typeof value !== "string") {
            return "";
        }

        if (!value.startsWith("/") || value.startsWith("//")) {
            return "";
        }

        return value;
    }

    function showStage(name) {
        stageBoot.hidden = name !== "boot";
        stageLogin.hidden = name !== "login";
        stagePanel.hidden = name !== "panel";
        document.body.classList.toggle("account-stage-login-active", name === "login");
        document.body.classList.toggle("account-stage-panel-active", name === "panel");
        if (name !== "login") {
            document.body.classList.remove("account-keyboard-open", "account-login-input-focus", "account-login-otp-verify-active");
            return;
        }
        queueLoginViewportSync();
    }

    function resetLoginScrollPosition() {
        if (!document.body.classList.contains("account-stage-login-active")) {
            return;
        }
        window.requestAnimationFrame(function () {
            window.scrollTo({ top: 0, left: 0, behavior: "auto" });
        });
    }

    function normalizeSurfaceName(raw) {
        var name = String(raw || "").trim().toLowerCase();
        switch (name) {
            case "profile":
            case "info":
            case "security":
            case "phone":
            case "bots":
            case "owner":
            case "owner-user":
            case "notifications":
                return name;
            default:
                return "hub";
        }
    }

    function hasOwnerAccess() {
        return !!(currentUser && currentUser.isOwner);
    }

    function hasManagementAccess() {
        return !!(currentUser && currentUser.permissions && currentUser.permissions.manageCohort);
    }

    function ownerActiveCohortKey() {
        return String(ownerState.activeCohortKey || (currentUser && currentUser.cohortKey) || "").trim();
    }

    function ownerVisibleCohorts() {
        return Array.isArray(ownerState.cohorts) ? ownerState.cohorts : [];
    }

    function ownerActiveCohortRecord() {
        var target = ownerActiveCohortKey();
        return ownerVisibleCohorts().find(function (cohort) {
            return String(cohort && cohort.key || "") === target;
        }) || null;
    }

    function ownerUserCohortKey(user) {
        return String(user && user.cohortKey || "").trim();
    }

    function ownerUsersInActiveCohort(users) {
        var target = ownerActiveCohortKey();
        return (Array.isArray(users) ? users : []).filter(function (user) {
            return !target || ownerUserCohortKey(user) === target;
        });
    }

    function ownerCanAccessServices() {
        return hasOwnerAccess();
    }

    function ownerCanAccessStats() {
        return hasOwnerAccess();
    }

    function normalizeOwnerTab(value) {
        var tab = String(value || "").trim().toLowerCase();
        return ["users", "representatives", "create", "stats", "services"].indexOf(tab) >= 0 ? tab : "users";
    }

    function updateOwnerTabs() {
        var active = normalizeOwnerTab(ownerState.activeTab);
        if (ownerTabs) {
            Array.prototype.slice.call(ownerTabs.querySelectorAll("[data-owner-tab]")).forEach(function (button) {
                var tabName = normalizeOwnerTab(button.dataset.ownerTab);
                var available = true;
                if (tabName === "services") {
                    available = ownerCanAccessServices();
                } else if (tabName === "stats") {
                    available = ownerCanAccessStats();
                }
                button.hidden = !available;
                if (!available && active === tabName) {
                    active = "users";
                }
                var selected = normalizeOwnerTab(button.dataset.ownerTab) === active;
                button.classList.toggle("is-active", selected);
                button.setAttribute("aria-selected", selected ? "true" : "false");
            });
        }
        ownerTabPanels.forEach(function (panel) {
            var panelTab = normalizeOwnerTab(panel.dataset.ownerTabPanel);
            var available = true;
            if (panelTab === "services") {
                available = ownerCanAccessServices();
            } else if (panelTab === "stats") {
                available = ownerCanAccessStats();
            }
            var selected = panelTab === active && available;
            panel.classList.toggle("is-active", selected);
            panel.hidden = !selected;
        });
        ownerState.activeTab = active;
        if (ownerCreateCohortSection) {
            ownerCreateCohortSection.hidden = !hasOwnerAccess() || active !== "create";
        }
    }

    function setOwnerTab(value) {
        ownerState.activeTab = normalizeOwnerTab(value);
        updateOwnerTabs();
        if (ownerState.activeTab === "stats" && hasOwnerAccess()) {
            loadOwnerAnalytics(false);
        }
    }

    function ownerUserPageSize() {
        if (window.matchMedia && window.matchMedia("(max-width: 720px)").matches) {
            return 8;
        }
        return Math.max(8, Number(ownerState.userPageSize) || 18);
    }

    function accountUserKey(user) {
        var source = user || currentUser || {};
        return String(source.studentNumber || "").trim();
    }

    function canOpenSurface(surface) {
        if (surface === "owner" || surface === "owner-user") {
            return hasManagementAccess();
        }
        return true;
    }

    function surfaceFromHash() {
        var raw = String(window.location.hash || "").replace(/^#/, "");
        if (!raw) {
            return "hub";
        }

        if (raw.indexOf("account-") === 0) {
            raw = raw.slice(8);
        }
        return normalizeSurfaceName(raw);
    }

    function syncSurfaceHash(surface, replace) {
        var suffix = surface === "hub" ? "" : ("#account-" + surface);
        var nextUrl = window.location.pathname + window.location.search + suffix;
        if (replace) {
            window.history.replaceState(null, "", nextUrl);
            return;
        }
        window.history.pushState(null, "", nextUrl);
    }

    function findSurfacePanel(name) {
        var matched = null;
        surfacePanels.some(function (panel) {
            if (panel.dataset.surface === name) {
                matched = panel;
                return true;
            }
            return false;
        });
        return matched;
    }

    function playSurfaceTransition(node, backwards) {
        if (!node) {
            return;
        }

        node.classList.remove("account-surface-enter", "account-surface-enter-back");
        // Force restart so each navigation feels responsive.
        void node.offsetWidth;
        node.classList.add("account-surface-enter");
        if (backwards) {
            node.classList.add("account-surface-enter-back");
        }

        window.setTimeout(function () {
            node.classList.remove("account-surface-enter", "account-surface-enter-back");
        }, 280);
    }

    function openSurface(surface, options) {
        var opts = options || {};
        var target = normalizeSurfaceName(surface);
        if (!canOpenSurface(target)) {
            target = "hub";
        }

        var previous = activeSurface;
        activeSurface = target;
        var showingHub = target === "hub";
        if (accountHubPanel) {
            accountHubPanel.hidden = !showingHub;
        }
        if (accountSurfaceLayout) {
            accountSurfaceLayout.hidden = showingHub;
        }
        surfacePanels.forEach(function (panel) {
            panel.hidden = panel.dataset.surface !== target;
        });
        if (stagePanel) {
            stagePanel.dataset.surface = target;
        }

        if (opts.syncHash !== false) {
            syncSurfaceHash(target, !!opts.replaceHash);
        }

        if (!opts.skipAnimation && stagePanel && !stagePanel.hidden) {
            if (showingHub) {
                playSurfaceTransition(accountHubPanel, previous !== "hub");
            } else {
                playSurfaceTransition(findSurfacePanel(target), previous !== "hub");
            }
        }

        if (!opts.preserveScroll) {
            window.scrollTo(0, 0);
        }

        if (target === "notifications" && currentUser) {
            loadNotifications(true);
        }
        if (target === "bots" && currentUser) {
            loadBotConnections(true);
        }
    }

    function setBootText(text) {
        bootText.textContent = text;
    }

    function setFeedback(node, text, kind, loading) {
        node.className = "account-feedback" + (kind ? " " + kind : "");

        if (loading) {
            node.innerHTML = [
                '<div class="loader">',
                '  <span class="loader-dot"></span>',
                '  <span class="loader-dot"></span>',
                '  <span class="loader-dot"></span>',
                "  <span>" + text + "</span>",
                "</div>"
            ].join("");
            return;
        }

        node.textContent = text || "";
    }

    function setInlineFeedback(node, text, kind, loading) {
        if (!node) {
            return;
        }

        node.className = "account-feedback account-feedback--inline" + (kind ? " " + kind : "");
        if (loading) {
            node.innerHTML = [
                '<div class="loader">',
                '  <span class="loader-dot"></span>',
                '  <span class="loader-dot"></span>',
                '  <span class="loader-dot"></span>',
                "  <span>" + text + "</span>",
                "</div>"
            ].join("");
            return;
        }

        node.textContent = text || "";
    }

    function normalizeDigits(value) {
        var text = String(value || "").trim();
        if (!text) {
            return "";
        }
        return text
            .replace(/[\u06F0-\u06F9]/g, function (ch) {
                return String("\u06F0\u06F1\u06F2\u06F3\u06F4\u06F5\u06F6\u06F7\u06F8\u06F9".indexOf(ch));
            })
            .replace(/[\u0660-\u0669]/g, function (ch) {
                return String("\u0660\u0661\u0662\u0663\u0664\u0665\u0666\u0667\u0668\u0669".indexOf(ch));
            });
    }

    function toPersianDigits(value) {
        return String(value || "").replace(/[0-9]/g, function (ch) {
            return "\u06F0\u06F1\u06F2\u06F3\u06F4\u06F5\u06F6\u06F7\u06F8\u06F9".charAt(Number(ch));
        });
    }

    function normalizedPhone(value) {
        var digits = normalizeDigits(value).replace(/\D+/g, "");
        if (!digits) return "";
        if (digits.indexOf("0098") === 0 && digits.length >= 14) {
            return "0" + digits.slice(4);
        }
        if (digits.indexOf("98") === 0 && digits.length >= 12) {
            return "0" + digits.slice(2);
        }
        if (digits.length === 10 && digits.charAt(0) === "9") {
            return "0" + digits;
        }
        return digits;
    }

    function isValidIranMobile(value) {
        return /^09\d{9}$/.test(normalizedPhone(value));
    }

    function loginOtpLength() {
        if (loginOtpSlots) {
            var slots = loginOtpSlots.querySelectorAll(".otp-slot").length;
            if (slots > 0) {
                return slots;
            }
        }
        var maxLength = loginOtpCodeInput ? Number(loginOtpCodeInput.getAttribute("maxlength")) : 6;
        return Number.isFinite(maxLength) && maxLength > 0 ? maxLength : 6;
    }

    function loginOtpCodeValue() {
        return normalizeDigits(loginOtpCodeInput ? loginOtpCodeInput.value : "").replace(/\D+/g, "").slice(0, loginOtpLength());
    }

    function externalSignupOtpLength() {
        if (externalSignupOtpSlots) {
            var slots = externalSignupOtpSlots.querySelectorAll(".otp-slot").length;
            if (slots > 0) {
                return slots;
            }
        }
        var maxLength = externalSignupOtpCode ? Number(externalSignupOtpCode.getAttribute("maxlength")) : 6;
        return Number.isFinite(maxLength) && maxLength > 0 ? maxLength : 6;
    }

    function externalSignupOtpCodeValue() {
        return normalizeDigits(externalSignupOtpCode ? externalSignupOtpCode.value : "").replace(/\D+/g, "").slice(0, externalSignupOtpLength());
    }

    function setInputInvalid(input, invalid) {
        if (!input) {
            return;
        }
        input.setAttribute("aria-invalid", invalid ? "true" : "false");
        var shell = input.closest ? input.closest(".otp-phone-shell, .otp-slot-field") : null;
        if (shell) {
            shell.classList.toggle("has-error", invalid);
        }
    }

    function setFieldError(input, node, text) {
        var message = String(text || "").trim();
        if (node) {
            node.textContent = message;
        }
        setInputInvalid(input, message !== "");
    }

    function setButtonBusy(button, busy, busyText) {
        if (!button) {
            return;
        }
        if (!button.dataset.defaultText) {
            button.dataset.defaultText = button.textContent || "";
        }
        button.textContent = busy ? busyText : button.dataset.defaultText;
        button.setAttribute("aria-busy", busy ? "true" : "false");
    }

    function escapeHtml(value) {
        return String(value == null ? "" : value).replace(/[&<>"']/g, function (char) {
            switch (char) {
                case "&":
                    return "&amp;";
                case "<":
                    return "&lt;";
                case ">":
                    return "&gt;";
                case '"':
                    return "&quot;";
                case "'":
                    return "&#39;";
                default:
                    return char;
            }
        });
    }

    function focusLoginOtpInput(selectCode) {
        if (!loginOtpCodeInput) {
            return;
        }
        loginOtpCodeInput.focus({ preventScroll: true });
        if (selectCode && loginOtpCodeInput.setSelectionRange) {
            try {
                loginOtpCodeInput.setSelectionRange(0, loginOtpCodeInput.value.length);
            } catch (error) {
                // Some mobile browsers reject selection on managed OTP inputs.
            }
        }
    }

    function updateLoginOtpPhoneDisplay(phoneNumber, maskedPhone) {
        var visiblePhone = ltrMaskedPhone(toPersianDigits(maskedPhone || phoneNumber), "");
        if (loginOtpPhoneDisplay) {
            loginOtpPhoneDisplay.textContent = visiblePhone;
        }
        if (loginOtpSummary) {
            loginOtpSummary.textContent = visiblePhone
                ? "کد تایید برای این شماره ارسال شد."
                : "کد تایید ارسال‌شده را وارد کن.";
        }
    }

    function focusExternalSignupOtpInput(selectCode) {
        if (!externalSignupOtpCode) {
            return;
        }
        externalSignupOtpCode.focus({ preventScroll: true });
        if (selectCode && externalSignupOtpCode.setSelectionRange) {
            try {
                externalSignupOtpCode.setSelectionRange(0, externalSignupOtpCode.value.length);
            } catch (error) {
                // Some mobile browsers reject selection on managed OTP inputs.
            }
        }
    }

    function updateExternalSignupPhoneDisplay(phoneNumber, maskedPhone) {
        var visiblePhone = ltrMaskedPhone(toPersianDigits(maskedPhone || phoneNumber), "");
        if (externalSignupPhoneDisplay) {
            externalSignupPhoneDisplay.textContent = visiblePhone;
        }
        if (externalSignupSummary) {
            externalSignupSummary.textContent = visiblePhone
                ? "کد تایید برای این شماره ارسال شد."
                : "کد تایید ارسال‌شده را وارد کن.";
        }
    }

    function bindNumericInput(input, maxLength) {
        if (!input) {
            return;
        }
        input.addEventListener("input", function () {
            var digits = normalizeDigits(input.value).replace(/\D+/g, "");
            var next = digits;
            if (input.dataset && input.dataset.phoneInput === "iran") {
                next = normalizedPhone(next);
            }
            if (Number.isFinite(maxLength) && maxLength > 0) {
                next = next.slice(0, maxLength);
            }
            if (input.dataset && input.dataset.displayDigits === "persian") {
                next = toPersianDigits(next);
            }
            if (input.value !== next) {
                input.value = next;
            }
        });
    }

    function setNumericDisplayValue(input, value) {
        if (!input) {
            return;
        }
        var next = String(value || "");
        if (input.dataset && input.dataset.displayDigits === "persian") {
            next = toPersianDigits(next);
        }
        input.value = next;
    }

    function stopOtpCredentialRead() {
        if (!otpCredentialAbortController) {
            return;
        }
        otpCredentialAbortController.abort();
        otpCredentialAbortController = null;
    }

    function fillOtpInput(input, value) {
        if (!input) {
            return;
        }
        var maxLength = Number(input.getAttribute("maxlength")) || 6;
        var code = normalizeDigits(value).replace(/\D+/g, "").slice(0, maxLength);
        if (!code) {
            return;
        }
        input.value = code;
        input.dispatchEvent(new Event("input", { bubbles: true }));
        input.focus({ preventScroll: true });
    }

    function startOtpCredentialRead(input) {
        if (!input || !window.isSecureContext || !("OTPCredential" in window) || !window.AbortController || !navigator.credentials) {
            return;
        }

        stopOtpCredentialRead();
        var controller = new AbortController();
        otpCredentialAbortController = controller;

        navigator.credentials.get({
            otp: { transport: ["sms"] },
            signal: controller.signal
        }).then(function (credential) {
            if (otpCredentialAbortController !== controller || !credential || !credential.code) {
                return;
            }
            fillOtpInput(input, credential.code);
        }).catch(function (error) {
            if (error && error.name === "AbortError") {
                return;
            }
        }).finally(function () {
            if (otpCredentialAbortController === controller) {
                otpCredentialAbortController = null;
            }
        });
    }

    function smsHealthStatusLabel(value) {
        var status = String(value || "").trim().toLowerCase();
        if (status === "ok") return "سالم";
        if (status === "error") return "خطادار";
        if (status === "unknown") return "نامشخص";
        return status || "نامشخص";
    }

    function ensureOwnerSmsHealthPhone() {
        if (!ownerSmsTestPhone) {
            return "";
        }
        var normalized = normalizedPhone(ownerSmsTestPhone.value);
        ownerSmsTestPhone.value = normalized;
        return normalized;
    }

    function toNumber(value, fallback) {
        var num = Number(value);
        return Number.isFinite(num) ? num : fallback;
    }

    function nowSeconds() {
        return Math.floor(Date.now() / 1000);
    }

    function secondsRemaining(targetEpoch) {
        var left = Math.max(0, Math.floor(toNumber(targetEpoch, 0) - nowSeconds()));
        return left;
    }

    function formatSeconds(seconds) {
        var total = Math.max(0, Math.floor(toNumber(seconds, 0)));
        var mins = Math.floor(total / 60);
        var secs = total % 60;
        return String(mins).padStart(2, "0") + ":" + String(secs).padStart(2, "0");
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

        var parsed = new Date(numeric);
        return Number.isFinite(parsed.getTime()) ? parsed : null;
    }

    function formatJalaliDateTime(value, fallback, includeSeconds) {
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
            second: includeSeconds ? "2-digit" : undefined,
            hour12: false
        });
    }

    function ltrIsolateText(value) {
        var clean = String(value || "").trim();
        if (!clean) {
            return "";
        }
        return "\u2066" + clean + "\u2069";
    }

    function ltrMaskedPhone(value, fallback) {
        var clean = String(value || "").trim();
        if (!clean) {
            return fallback || "";
        }
        return ltrIsolateText(clean);
    }

    function userDisNumber(user) {
        return String((user && user.disNumber) || "").trim();
    }

    function applyOtpSlots(input, slotsRoot) {
        if (!input || !slotsRoot) {
            return;
        }
        var slots = Array.prototype.slice.call(slotsRoot.querySelectorAll(".otp-slot"));
        if (!slots.length) {
            return;
        }

        var update = function () {
            var digits = normalizeDigits(input.value).replace(/\D+/g, "").slice(0, slots.length);
            slots.forEach(function (slot, index) {
                var ch = digits.charAt(index);
                slot.textContent = ch || "";
                slot.classList.toggle("has-value", !!ch);
                slot.classList.toggle("is-active", index === digits.length && digits.length < slots.length);
            });
            slotsRoot.classList.toggle("is-complete", digits.length === slots.length);
            slotsRoot.classList.toggle("has-error", input.getAttribute("aria-invalid") === "true");
        };

        input.addEventListener("focus", function () {
            slotsRoot.classList.add("is-focused");
            update();
        });
        input.addEventListener("blur", function () {
            slotsRoot.classList.remove("is-focused");
            update();
        });
        input.addEventListener("input", update);
        input.addEventListener("paste", function (event) {
            var clipboard = event.clipboardData || window.clipboardData;
            var text = clipboard && clipboard.getData ? clipboard.getData("text") : "";
            var digits = normalizeDigits(text).replace(/\D+/g, "").slice(0, slots.length);
            if (!digits) {
                return;
            }
            event.preventDefault();
            input.value = digits;
            input.dispatchEvent(new Event("input", { bubbles: true }));
        });
        slotsRoot.addEventListener("click", function () {
            input.focus({ preventScroll: true });
            if (input.setSelectionRange) {
                try {
                    input.setSelectionRange(input.value.length, input.value.length);
                } catch (error) {
                    // The managed input only needs focus; selection can fail on iOS.
                }
            }
        });
        update();
    }

    function parsedPhone(user) {
        var source = user && typeof user === "object" && user.phone && typeof user.phone === "object"
            ? user.phone
            : {};
        return {
            hasNumber: !!source.hasNumber,
            numberMasked: String(source.numberMasked || ""),
            verified: !!source.verified,
            otpLoginEnabled: !!source.otpLoginEnabled,
            canLoginWithOtp: !!source.canLoginWithOtp,
            nudgeDismissedAt: String(source.nudgeDismissedAt || "")
        };
    }

    function setPhonePill(node, text, state) {
        if (!node) return;
        node.textContent = text;
        node.className = "phone-status-pill" + (state ? (" is-" + state) : "");
    }

    function renderPhoneSecurityState(user) {
        var phone = parsedPhone(user || {});
        var maskedPhone = String(phone.numberMasked || "").trim();
        var maskedPhoneLabel = ltrMaskedPhone(maskedPhone, "شماره ثبت‌شده");
        var badgeText = "نیاز به ثبت شماره";
        var badgeState = "warn";
        var summary = "شماره‌ای برای این حساب ثبت نشده است. برای فعال‌سازی ورود پیامکی، شماره را ثبت و تایید کن.";

        if (phone.hasNumber && !phone.verified) {
            badgeText = "در انتظار تایید";
            badgeState = "warn";
            summary = "شماره موبایل ثبت شده ولی هنوز با کد پیامکی تایید نشده است.";
        } else if (phone.hasNumber && phone.verified && !phone.otpLoginEnabled) {
            badgeText = "شماره تایید شده";
            badgeState = "ok";
            summary = "شماره " + maskedPhoneLabel + " تایید شده است؛ ورود با کد تایید هنوز غیرفعال است.";
        } else if (phone.hasNumber && phone.verified && phone.otpLoginEnabled) {
            badgeText = "ورود پیامکی فعال";
            badgeState = "ok";
            summary = "ورود با کد تایید برای " + maskedPhoneLabel + " فعال است.";
        }

        if (phoneStatusBadge) {
            phoneStatusBadge.textContent = badgeText;
            phoneStatusBadge.className = "phone-status-badge is-" + badgeState;
        }
        if (phoneStatusSummary) {
            phoneStatusSummary.textContent = summary;
        }
        setPhonePill(phoneNumberState, phone.hasNumber ? ("شماره " + maskedPhoneLabel) : "شماره ثبت نشده", phone.hasNumber ? "ok" : "warn");
        setPhonePill(phoneVerifyState, phone.verified ? "تایید شده" : "تایید نشده", phone.verified ? "ok" : "warn");
        setPhonePill(phoneLoginState, phone.otpLoginEnabled ? "ورود پیامکی فعال" : "ورود پیامکی غیرفعال", phone.otpLoginEnabled ? "ok" : "warn");

        if (phoneLoginEnabledInput) {
            phoneLoginEnabledInput.checked = !!phone.otpLoginEnabled;
            phoneLoginEnabledInput.disabled = !phone.hasNumber || !phone.verified;
        }
        if (phoneLoginSaveButton) {
            phoneLoginSaveButton.disabled = !phone.hasNumber || !phone.verified;
        }
        if (phoneLoginToggleHint) {
            phoneLoginToggleHint.textContent = phone.hasNumber && phone.verified
                ? "می‌توانی ورود پیامکی را برای همین شماره روشن یا خاموش کنی."
                : "برای فعال‌سازی، ابتدا شماره را با کد پیامکی تایید کن.";
        }
        if (phoneCurrentNumber) {
            phoneCurrentNumber.textContent = phone.hasNumber ? ltrMaskedPhone(maskedPhone, "—") : "—";
        }
        if (phoneCurrentCaption) {
            phoneCurrentCaption.textContent = phone.hasNumber
                ? "برای تغییر شماره، شماره جدید را وارد کن و دوباره تایید بگیر."
                : "هنوز شماره‌ای ثبت نشده است. از کارت پایین برای ثبت شماره استفاده کن.";
        }
        if (phoneNumberEditButton) {
            phoneNumberEditButton.textContent = phone.hasNumber ? "تغییر شماره" : "ثبت شماره";
        }
        if (phoneNumberRemoveButton) {
            phoneNumberRemoveButton.disabled = !phone.hasNumber;
        }
        if (phoneEnrollNumber && !phoneEnrollNumber.value) {
            phoneEnrollNumber.placeholder = "9xxxxxxxxx یا 09xxxxxxxxx";
        }
        if (accountPhoneNudge) {
            accountPhoneNudge.hidden = !shouldShowPhoneNudge(user || {});
        }
    }

    function shouldShowPhoneNudge(user) {
        var phone = parsedPhone(user);
        if (phone.hasNumber) {
            return false;
        }
        return !phone.nudgeDismissedAt;
    }

    function setLoginMode(mode) {
        if (mode === "signup" || mode === "reset") {
            loginMode = mode;
        } else {
            loginMode = mode === "otp" ? "otp" : "password";
        }
        if (stageLogin) {
            stageLogin.dataset.loginMode = loginMode;
        }
        syncLoginHeading();

        if (loginMethodPasswordBtn) {
            var passwordActive = loginMode === "password";
            loginMethodPasswordBtn.classList.toggle("is-active", passwordActive);
            loginMethodPasswordBtn.setAttribute("aria-selected", passwordActive ? "true" : "false");
        }
        if (loginMethodOtpBtn) {
            var otpActive = loginMode === "otp";
            loginMethodOtpBtn.classList.toggle("is-active", otpActive);
            loginMethodOtpBtn.setAttribute("aria-selected", otpActive ? "true" : "false");
        }
        if (loginMethodSignupBtn) {
            loginMethodSignupBtn.hidden = loginMode === "signup" || loginMode === "reset";
        }
        if (loginPasswordResetBtn) {
            loginPasswordResetBtn.hidden = loginMode !== "password";
        }
        if (loginForm) {
            loginForm.hidden = loginMode !== "password";
            setFormControlsEnabled(loginForm, loginMode === "password");
        }
        if (loginOtpForm) {
            loginOtpForm.hidden = loginMode !== "otp";
            setFormControlsEnabled(loginOtpForm, loginMode === "otp");
        }
        if (externalSignupForm) {
            externalSignupForm.hidden = loginMode !== "signup";
            setFormControlsEnabled(externalSignupForm, loginMode === "signup");
        }
        if (passwordResetForm) {
            passwordResetForm.hidden = loginMode !== "reset";
            setFormControlsEnabled(passwordResetForm, loginMode === "reset");
        }

        setLoginOtpVerifyVisible(false);
        setExternalSignupVerifyVisible(false);
        setPasswordResetVerifyVisible(false);
        if (loginMode === "otp") {
            if (loginOtpCodeInput) {
                loginOtpCodeInput.value = "";
                loginOtpCodeInput.dispatchEvent(new Event("input", { bubbles: true }));
            }
            setFieldError(loginPhoneInput, loginPhoneError, "");
            setFieldError(loginOtpCodeInput, loginOtpCodeError, "");
        }

        updateLoginOtpRequestState();
        updateLoginOtpSubmitState();
        updateExternalSignupState();
        resetLoginScrollPosition();
    }

    function setLoginOtpVerifyVisible(visible) {
        if (!loginOtpVerifyGroup) {
            return;
        }
        loginOtpVerifyGroup.hidden = !visible;
        document.body.classList.toggle("account-login-otp-verify-active", !!visible);
        if (loginOtpForm) {
            loginOtpForm.dataset.otpStep = visible ? "verify" : "phone";
        }
        updateLoginOtpRequestState();
        updateLoginOtpSubmitState();
    }

    function setFormControlsEnabled(form, enabled) {
        if (!form) {
            return;
        }
        Array.prototype.slice.call(form.elements || []).forEach(function (control) {
            control.disabled = !enabled;
        });
    }

    function iosLikeDevice() {
        var ua = String(window.navigator.userAgent || "");
        var platform = String(window.navigator.platform || "");
        var touchPoints = Number(window.navigator.maxTouchPoints || 0);
        return /iPhone|iPad|iPod/i.test(ua) || ((/Mac/i.test(platform) || /Macintosh/i.test(ua)) && touchPoints > 1);
    }

    function setViewportScaleLock(active) {
        var meta = document.querySelector('meta[name="viewport"]');
        if (!meta) {
            return;
        }
        var base = meta.dataset.baseViewportContent || meta.getAttribute("content") || "";
        if (!meta.dataset.baseViewportContent) {
            meta.dataset.baseViewportContent = base
                .replace(/,\s*maximum-scale=1\b/g, "")
                .replace(/,\s*user-scalable=no\b/g, "")
                .trim();
        }
        meta.setAttribute("content", meta.dataset.baseViewportContent);
    }

    function isLoginInputElement(node) {
        return !!(node && node.matches && node.matches('#account-login input:not([type="checkbox"]):not([type="radio"]), #account-login textarea, #account-login select'));
    }

    function isLoginInputFocused() {
        return isLoginInputElement(document.activeElement);
    }

    function isViewportKeyboardShifted() {
        if (!window.visualViewport) {
            return false;
        }
        var touchPoints = Number(window.navigator.maxTouchPoints || 0);
        var compactViewport = window.matchMedia && window.matchMedia("(max-width: 820px)").matches;
        if (!compactViewport && touchPoints < 1) {
            return false;
        }
        var vv = window.visualViewport;
        var viewportHeight = Math.max(0, Number(vv.height || 0));
        var viewportOffsetTop = Math.max(0, Number(vv.offsetTop || 0));
        var layoutHeight = Math.max(0, Number(window.innerHeight || document.documentElement.clientHeight || 0));
        var hiddenHeight = layoutHeight - (viewportHeight + viewportOffsetTop);
        return hiddenHeight > 92;
    }

    function syncLoginViewportState() {
        if (!document.body.classList.contains("account-stage-login-active")) {
            return;
        }
        var focused = isLoginInputFocused();
        var keyboardOpen = focused && isViewportKeyboardShifted();
        document.body.classList.toggle("account-login-input-focus", focused);
        document.body.classList.toggle("account-keyboard-open", keyboardOpen);
    }

    function queueLoginViewportSync() {
        if (loginViewportTickTimer) {
            window.clearTimeout(loginViewportTickTimer);
        }
        loginViewportTickTimer = window.setTimeout(syncLoginViewportState, 34);
    }

    function stopLoginOtpCooldownTicker() {
        if (loginOtpCooldownTimer) {
            window.clearInterval(loginOtpCooldownTimer);
            loginOtpCooldownTimer = null;
        }
    }

    function isLoginOtpVerifyVisible() {
        return !!(loginOtpVerifyGroup && !loginOtpVerifyGroup.hidden);
    }

    function updateLoginOtpRequestState() {
        var left = secondsRemaining(loginOtpCooldownUntil);
        var active = left > 0;
        var validPhone = loginPhoneInput ? isValidIranMobile(loginPhoneInput.value) : false;
        if (loginOtpRequestButton) {
            loginOtpRequestButton.disabled = loginMode !== "otp" || loginOtpRequesting || active || !validPhone;
            setButtonBusy(loginOtpRequestButton, loginOtpRequesting, "در حال ارسال...");
        }
        if (loginOtpMeta) {
            loginOtpMeta.textContent = active
                ? ("ارسال مجدد تا " + formatSeconds(left) + " دیگر")
                : (isLoginOtpVerifyVisible()
                    ? "بعد از تکمیل کد، ورود خودکار انجام می‌شود."
                    : (validPhone ? "کد تایید برایت پیامک می‌شود." : ""));
        }
    }

    function updateLoginOtpSubmitState() {
        var complete = loginOtpCodeValue().length === loginOtpLength();
        var visible = isLoginOtpVerifyVisible();
        if (loginOtpSubmitButton) {
            loginOtpSubmitButton.disabled = loginMode !== "otp" || loginOtpSubmitting || !visible || !complete;
            setButtonBusy(loginOtpSubmitButton, loginOtpSubmitting, "در حال ورود...");
        }
    }

    function updateLoginOtpCooldownUi() {
        var active = secondsRemaining(loginOtpCooldownUntil) > 0;
        updateLoginOtpRequestState();
        if (!active) {
            stopLoginOtpCooldownTicker();
        }
    }

    function startLoginOtpCooldown(seconds) {
        loginOtpCooldownUntil = nowSeconds() + Math.max(0, Math.floor(toNumber(seconds, 0)));
        updateLoginOtpCooldownUi();
        if (secondsRemaining(loginOtpCooldownUntil) > 0 && !loginOtpCooldownTimer) {
            loginOtpCooldownTimer = window.setInterval(updateLoginOtpCooldownUi, 1000);
        }
    }

    function stopExternalSignupCooldownTicker() {
        if (externalSignupCooldownTimer) {
            window.clearInterval(externalSignupCooldownTimer);
            externalSignupCooldownTimer = null;
        }
    }

    function externalSignupPayload() {
        return {
            firstName: externalSignupFirstName ? externalSignupFirstName.value.trim() : "",
            lastName: externalSignupLastName ? externalSignupLastName.value.trim() : "",
            phoneNumber: externalSignupPhone ? normalizedPhone(externalSignupPhone.value) : "",
            password: externalSignupPassword ? externalSignupPassword.value : "",
            passwordConfirm: externalSignupPasswordConfirm ? externalSignupPasswordConfirm.value : "",
            otpCode: externalSignupOtpCode ? normalizeDigits(externalSignupOtpCode.value).replace(/\D+/g, "").slice(0, 6) : ""
        };
    }

    function setExternalSignupVerifyVisible(visible) {
        if (externalSignupVerifyGroup) {
            externalSignupVerifyGroup.hidden = !visible;
        }
        if (externalSignupDetails) {
            externalSignupDetails.hidden = !!visible;
        }
        if (externalSignupRequestActions) {
            externalSignupRequestActions.hidden = !!visible;
        }
        if (externalSignupForm) {
            externalSignupForm.dataset.otpStep = visible ? "verify" : "details";
        }
        document.body.classList.toggle("account-login-otp-verify-active", !!visible);
        if (!visible && externalSignupOtpCode) {
            externalSignupOtpCode.value = "";
            externalSignupOtpCode.dispatchEvent(new Event("input", { bubbles: true }));
        }
        setInputInvalid(externalSignupOtpCode, false);
        updateExternalSignupState();
    }

    function isExternalSignupVerifyVisible() {
        return !!(externalSignupVerifyGroup && !externalSignupVerifyGroup.hidden);
    }

    function updateExternalSignupState() {
        var payload = externalSignupPayload();
        var left = secondsRemaining(externalSignupCooldownUntil);
        var coolingDown = left > 0;
        var passwordReady = payload.password.length >= 6 && payload.passwordConfirm.length >= 6 && payload.password === payload.passwordConfirm;
        var readyForOtp = !!(payload.firstName && payload.lastName && isValidIranMobile(payload.phoneNumber) && passwordReady);
        if (externalSignupRequestButton) {
            externalSignupRequestButton.disabled = loginMode !== "signup" || externalSignupRequesting || externalSignupSubmitting || coolingDown || !readyForOtp;
            setButtonBusy(externalSignupRequestButton, externalSignupRequesting, "در حال ارسال...");
        }
        if (externalSignupSubmitButton) {
            externalSignupSubmitButton.disabled = loginMode !== "signup" || externalSignupSubmitting || !isExternalSignupVerifyVisible() || payload.otpCode.length !== 6;
            setButtonBusy(externalSignupSubmitButton, externalSignupSubmitting, "در حال ثبت نام...");
        }
        if (externalSignupMeta) {
            externalSignupMeta.textContent = coolingDown
                ? ("ارسال مجدد تا " + formatSeconds(left) + " دیگر")
                : (isExternalSignupVerifyVisible() ? "کد پیامک شده را وارد کن تا حساب آزمون ساخته شود." : "");
        }
    }

    function updateExternalSignupCooldownUi() {
        var active = secondsRemaining(externalSignupCooldownUntil) > 0;
        updateExternalSignupState();
        if (!active) {
            stopExternalSignupCooldownTicker();
        }
    }

    function startExternalSignupCooldown(seconds) {
        externalSignupCooldownUntil = nowSeconds() + Math.max(0, Math.floor(toNumber(seconds, 0)));
        updateExternalSignupCooldownUi();
        if (secondsRemaining(externalSignupCooldownUntil) > 0 && !externalSignupCooldownTimer) {
            externalSignupCooldownTimer = window.setInterval(updateExternalSignupCooldownUi, 1000);
        }
    }

    function stopPasswordResetCooldownTicker() {
        if (passwordResetCooldownTimer) {
            window.clearInterval(passwordResetCooldownTimer);
            passwordResetCooldownTimer = null;
        }
    }

    function passwordResetPayload() {
        return {
            phoneNumber: passwordResetPhone ? normalizedPhone(passwordResetPhone.value) : "",
            otpCode: passwordResetOtpCode ? normalizeDigits(passwordResetOtpCode.value).replace(/\D+/g, "").slice(0, 6) : "",
            newPassword: passwordResetNewPassword ? passwordResetNewPassword.value : "",
            confirmPassword: passwordResetConfirmPassword ? passwordResetConfirmPassword.value : ""
        };
    }

    function setPasswordResetVerifyVisible(visible) {
        if (passwordResetVerifyGroup) {
            passwordResetVerifyGroup.hidden = !visible;
        }
        if (!visible) {
            if (passwordResetOtpCode) passwordResetOtpCode.value = "";
            if (passwordResetNewPassword) passwordResetNewPassword.value = "";
            if (passwordResetConfirmPassword) passwordResetConfirmPassword.value = "";
        }
        updatePasswordResetState();
    }

    function isPasswordResetVerifyVisible() {
        return !!(passwordResetVerifyGroup && !passwordResetVerifyGroup.hidden);
    }

    function updatePasswordResetState() {
        var payload = passwordResetPayload();
        var left = secondsRemaining(passwordResetCooldownUntil);
        var coolingDown = left > 0;
        var validPhone = isValidIranMobile(payload.phoneNumber);
        var passwordReady = payload.newPassword.length >= 6 && payload.confirmPassword.length >= 6 && payload.newPassword === payload.confirmPassword;
        if (passwordResetRequestButton) {
            passwordResetRequestButton.disabled = loginMode !== "reset" || passwordResetRequesting || passwordResetSubmitting || coolingDown || !validPhone;
            setButtonBusy(passwordResetRequestButton, passwordResetRequesting, "در حال ارسال...");
        }
        if (passwordResetSubmitButton) {
            passwordResetSubmitButton.disabled = loginMode !== "reset" || passwordResetSubmitting || !isPasswordResetVerifyVisible() || payload.otpCode.length !== 6 || !passwordReady;
            setButtonBusy(passwordResetSubmitButton, passwordResetSubmitting, "در حال ثبت رمز...");
        }
        if (passwordResetMeta) {
            passwordResetMeta.textContent = coolingDown
                ? ("ارسال مجدد تا " + formatSeconds(left) + " دیگر")
                : (isPasswordResetVerifyVisible() ? "کد پیامک شده و رمز جدید را وارد کن." : "");
        }
    }

    function updatePasswordResetCooldownUi() {
        var active = secondsRemaining(passwordResetCooldownUntil) > 0;
        updatePasswordResetState();
        if (!active) {
            stopPasswordResetCooldownTicker();
        }
    }

    function startPasswordResetCooldown(seconds) {
        passwordResetCooldownUntil = nowSeconds() + Math.max(0, Math.floor(toNumber(seconds, 0)));
        updatePasswordResetCooldownUi();
        if (secondsRemaining(passwordResetCooldownUntil) > 0 && !passwordResetCooldownTimer) {
            passwordResetCooldownTimer = window.setInterval(updatePasswordResetCooldownUi, 1000);
        }
    }

    function stopPhoneEnrollCooldownTicker() {
        if (phoneEnrollCooldownTimer) {
            window.clearInterval(phoneEnrollCooldownTimer);
            phoneEnrollCooldownTimer = null;
        }
    }

    function updatePhoneEnrollCooldownUi() {
        var left = secondsRemaining(phoneEnrollCooldownUntil);
        var active = left > 0;
        if (phoneEnrollRequestButton) {
            phoneEnrollRequestButton.disabled = active;
        }
        if (phoneEnrollMeta) {
            phoneEnrollMeta.textContent = active
                ? ("ارسال مجدد تا " + formatSeconds(left) + " دیگر")
                : "بعد از ارسال، امکان ارسال دوباره با زمان‌سنج فعال می‌شود.";
        }
        if (!active) {
            stopPhoneEnrollCooldownTicker();
        }
    }

    function startPhoneEnrollCooldown(seconds) {
        phoneEnrollCooldownUntil = nowSeconds() + Math.max(0, Math.floor(toNumber(seconds, 0)));
        updatePhoneEnrollCooldownUi();
        if (secondsRemaining(phoneEnrollCooldownUntil) > 0 && !phoneEnrollCooldownTimer) {
            phoneEnrollCooldownTimer = window.setInterval(updatePhoneEnrollCooldownUi, 1000);
        }
    }

    function resetOtpUi() {
        loginOtpCooldownUntil = 0;
        phoneEnrollCooldownUntil = 0;
        externalSignupCooldownUntil = 0;
        passwordResetCooldownUntil = 0;
        loginOtpRequesting = false;
        loginOtpSubmitting = false;
        loginOtpAutoSubmitQueued = false;
        externalSignupRequesting = false;
        externalSignupSubmitting = false;
        externalSignupAutoSubmitQueued = false;
        passwordResetRequesting = false;
        passwordResetSubmitting = false;
        stopLoginOtpCooldownTicker();
        stopExternalSignupCooldownTicker();
        stopPasswordResetCooldownTicker();
        stopPhoneEnrollCooldownTicker();
        updateLoginOtpCooldownUi();
        updateExternalSignupCooldownUi();
        updatePasswordResetCooldownUi();
        updatePhoneEnrollCooldownUi();
        setLoginOtpVerifyVisible(false);
        setExternalSignupVerifyVisible(false);
        setPasswordResetVerifyVisible(false);
        setFieldError(loginPhoneInput, loginPhoneError, "");
        setFieldError(loginOtpCodeInput, loginOtpCodeError, "");
        stopOtpCredentialRead();
    }

    function avatarLabel(value) {
        var clean = String(value || "").replace(/\s+/g, " ").trim();
        if (!clean) {
            return "؟";
        }

        var parts = clean.split(" ").filter(Boolean);
        var initials = parts.slice(0, 2).map(function (part) {
            return part.charAt(0);
        }).join("");

        return initials || clean.charAt(0);
    }

    function normalizeAvatarUrl(value) {
        var clean = String(value || "").trim();
        if (!clean) {
            return "";
        }

        if (clean.indexOf("data:image/") === 0) {
            return clean;
        }

        if (clean.charAt(0) === "/") {
            return clean;
        }

        if (/^https?:\/\//i.test(clean)) {
            return clean;
        }

        return "";
    }

    function profileAbout(profile) {
        if (!profile || typeof profile !== "object") {
            return "";
        }

        return profile.about || profile.bio || "";
    }

    function renderAvatar(container, imageNode, fallbackNode, avatarUrl, label) {
        if (!container || !imageNode || !fallbackNode) {
            return;
        }

        var safeUrl = normalizeAvatarUrl(avatarUrl);
        fallbackNode.textContent = avatarLabel(label);

        if (!safeUrl) {
            container.dataset.hasAvatar = "0";
            imageNode.hidden = true;
            imageNode.removeAttribute("src");
            imageNode.alt = "";
            return;
        }

        container.dataset.hasAvatar = "1";
        imageNode.hidden = false;
        imageNode.alt = label ? ("تصویر پروفایل " + label) : "تصویر پروفایل";
        imageNode.onerror = function () {
            container.dataset.hasAvatar = "0";
            imageNode.hidden = true;
            imageNode.removeAttribute("src");
        };
        imageNode.src = safeUrl;
    }

    function syncProfileAvatarClearButton() {
        if (!profileAvatarClear) {
            return;
        }

        profileAvatarClear.disabled = !profileDraftAvatarUrl || profileSaving || profileAvatarProcessing;
    }

    function updateIdentityAvatars(name) {
        var label = name || $("profile-name").value || $("account-name").textContent || "";
        renderAvatar(accountAvatar, accountAvatarImage, accountAvatarFallback, profileDraftAvatarUrl, label);
        renderAvatar(profileAvatarPreview, profileAvatarImage, profileAvatarFallback, profileDraftAvatarUrl, label);
        syncProfileAvatarClearButton();
    }

    function setProfileBusy(isBusy) {
        profileSaving = !!isBusy;
        var controlsDisabled = profileSaving || profileAvatarProcessing;
        profileSubmit.disabled = controlsDisabled;
        if (profileAvatarFile) {
            profileAvatarFile.disabled = controlsDisabled;
        }
        syncProfileAvatarClearButton();
    }

    function setProfileAvatarProcessing(isProcessing) {
        profileAvatarProcessing = !!isProcessing;
        setProfileBusy(profileSaving);
    }

    function readFileAsDataUrl(file) {
        return new Promise(function (resolve, reject) {
            var reader = new FileReader();
            reader.onload = function () {
                resolve(String(reader.result || ""));
            };
            reader.onerror = function () {
                reject(new Error("file-read"));
            };
            reader.readAsDataURL(file);
        });
    }

    function imageFileToAvatarDataUrl(file) {
        return readFileAsDataUrl(file).then(function (rawDataUrl) {
            return new Promise(function (resolve, reject) {
                var image = new Image();
                image.onload = function () {
                    var size = 320;
                    var canvas = document.createElement("canvas");
                    canvas.width = size;
                    canvas.height = size;

                    var ctx = canvas.getContext("2d");
                    if (!ctx) {
                        reject(new Error("canvas-context"));
                        return;
                    }

                    var sourceSize = Math.min(image.width, image.height);
                    var sx = Math.max(0, Math.floor((image.width - sourceSize) / 2));
                    var sy = Math.max(0, Math.floor((image.height - sourceSize) / 2));
                    ctx.drawImage(image, sx, sy, sourceSize, sourceSize, 0, 0, size, size);

                    var quality = 0.9;
                    var dataUrl = canvas.toDataURL("image/jpeg", quality);
                    while (dataUrl.length > 390000 && quality > 0.55) {
                        quality -= 0.08;
                        dataUrl = canvas.toDataURL("image/jpeg", quality);
                    }

                    if (dataUrl.length > 390000) {
                        reject(new Error("avatar-too-large"));
                        return;
                    }

                    resolve(dataUrl);
                };
                image.onerror = function () {
                    reject(new Error("avatar-invalid"));
                };
                image.src = rawDataUrl;
            });
        });
    }

    function ownerFeedbackMessage(text, kind, loading) {
        setInlineFeedback(ownerFeedback, text, kind, loading);
        if (ownerUserPanelFeedback && activeSurface === "owner-user") {
            setInlineFeedback(ownerUserPanelFeedback, text, kind, loading);
        }
    }

    function ownerCreateStudentFeedbackMessage(text, kind, loading) {
        setInlineFeedback(ownerCreateStudentFeedback, text, kind, loading);
    }

    function ownerCreateCohortFeedbackMessage(text, kind, loading) {
        setInlineFeedback(ownerCreateCohortFeedback, text, kind, loading);
    }

    function ownerImportUsersFeedbackMessage(text, kind, loading) {
        setInlineFeedback(ownerImportUsersFeedback, text, kind, loading);
    }

    function ownerCohortFeedbackMessage(text, kind, loading) {
        setInlineFeedback(ownerCohortFeedback, text, kind, loading);
    }

    function ownerGradesFeedbackMessage(text, kind, loading) {
        setInlineFeedback(ownerGradesFeedback, text, kind, loading);
    }

    function ownerStatsFeedbackMessage(text, kind, loading) {
        setInlineFeedback(ownerStatsFeedback, text, kind, loading);
    }

    function networkErrorResponse() {
        return {
            success: false,
            error: "ارتباط با سرور برقرار نشد. اتصال اینترنت خود را بررسی کنید.",
            httpStatus: 0
        };
    }

    function request(action, payload) {
        return fetch("/api/auth_api.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                "Accept": "application/json"
            },
            body: new URLSearchParams(Object.assign({ action: action }, payload || {}))
        }).then(function (response) {
            return response.json().catch(function () {
                return {
                    success: false,
                    error: "پاسخ نامعتبر از سرور دریافت شد."
                };
            }).then(function (data) {
                data.httpStatus = response.status;
                return data;
            });
        }).catch(networkErrorResponse);
    }

    function requestFormData(action, formData) {
        var body = formData instanceof FormData ? formData : new FormData();
        body.set("action", action);
        return fetch("/api/auth_api.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Accept": "application/json"
            },
            body: body
        }).then(function (response) {
            return response.json().catch(function () {
                return {
                    success: false,
                    error: "پاسخ نامعتبر از سرور دریافت شد."
                };
            }).then(function (data) {
                data.httpStatus = response.status;
                return data;
            });
        }).catch(networkErrorResponse);
    }

    function botConnectionsGet() {
        return fetch("/api/bot_api.php?action=accountConnections", {
            method: "GET",
            credentials: "same-origin",
            headers: { "Accept": "application/json" }
        }).then(function (response) {
            return response.json().catch(function () {
                return { success: false, error: "پاسخ نامعتبر از سرور دریافت شد." };
            }).then(function (data) {
                data.httpStatus = response.status;
                return data;
            });
        }).catch(networkErrorResponse);
    }

    function botConnectionDisconnect(platform) {
        return fetch("/api/bot_api.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Accept": "application/json",
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                "X-CSRF-Token": botConnectionsState.csrfToken
            },
            body: new URLSearchParams({
                action: "disconnectAccount",
                platform: platform
            })
        }).then(function (response) {
            return response.json().catch(function () {
                return { success: false, error: "پاسخ نامعتبر از سرور دریافت شد." };
            }).then(function (data) {
                data.httpStatus = response.status;
                return data;
            });
        }).catch(networkErrorResponse);
    }

    function botPlatformMeta(platform) {
        return platform === "bale"
            ? { label: "بله", icon: "💬" }
            : { label: "تلگرام", icon: "✈️" };
    }

    function botConnectionCard(connection, platform) {
        var item = connection && typeof connection === "object" ? connection : {};
        var meta = botPlatformMeta(platform);
        var connected = item.connected === true;
        var statusClass = connected ? " is-connected" : "";
        var statusLabel = connected ? "متصل" : "متصل نیست";
        var details = "";
        if (connected) {
            var platformName = String(item.platformDisplayName || "").trim();
            var username = String(item.platformUsername || "").trim();
            var displayName = platformName || "در اتصال قدیمی ثبت نشده";
            var usernameRow = username
                ? '<div><dt>نام کاربری پیام‌رسان</dt><dd><bdi dir="ltr">@' + escapeHtml(username) + "</bdi></dd></div>"
                : "";
            details = [
                '<dl class="bot-connection-details">',
                '<div><dt>نام حساب سایت</dt><dd>' + escapeHtml(item.websiteName || "—") + "</dd></div>",
                '<div><dt>نام اکانت پیام‌رسان</dt><dd>' + escapeHtml(displayName) + "</dd></div>",
                usernameRow,
                '<div><dt>شناسه عددی</dt><dd><bdi dir="ltr">' + escapeHtml(toPersianDigits(item.platformUserId || "—")) + "</bdi></dd></div>",
                '<div><dt>زمان اتصال</dt><dd>' + escapeHtml(formatJalaliDateTime(item.linkedAt, "—", true)) + "</dd></div>",
                "</dl>"
            ].join("");
        } else {
            details = '<p class="bot-connection-card__empty">این حساب سایت هنوز به ربات ' + escapeHtml(meta.label) + " متصل نشده است.</p>";
        }
        var busy = botConnectionsState.disconnectingPlatform === platform;
        var action = connected
            ? '<div class="bot-connection-card__actions"><button class="shell-action-btn bot-connection-card__disconnect" type="button" data-disconnect-bot="' + escapeHtml(platform) + '"' + (busy ? " disabled" : "") + ">" + (busy ? "در حال قطع اتصال…" : "قطع اتصال") + "</button></div>"
            : "";
        return [
            '<article class="bot-connection-card" data-bot-platform="' + escapeHtml(platform) + '">',
            '<div class="bot-connection-card__head">',
            '<div class="bot-connection-card__identity"><span class="bot-connection-card__icon" aria-hidden="true">' + meta.icon + "</span><span><strong>ربات " + escapeHtml(meta.label) + "</strong><small>اتصال مستقل همین پیام‌رسان</small></span></div>",
            '<span class="bot-connection-status' + statusClass + '">' + statusLabel + "</span>",
            "</div>",
            details,
            action,
            "</article>"
        ].join("");
    }

    function renderBotConnections() {
        if (!botConnectionsList) {
            return;
        }
        if (botConnectionsState.loading && !botConnectionsState.connections) {
            botConnectionsList.innerHTML = '<div class="bot-connection-skeleton" aria-label="در حال دریافت وضعیت اتصال"></div><div class="bot-connection-skeleton" aria-hidden="true"></div>';
            return;
        }
        var connections = botConnectionsState.connections || {};
        botConnectionsList.innerHTML = botConnectionCard(connections.telegram, "telegram")
            + botConnectionCard(connections.bale, "bale");
        var connectedCount = [connections.telegram, connections.bale].filter(function (item) {
            return item && item.connected === true;
        }).length;
        if (accountRowBotsMeta) {
            accountRowBotsMeta.textContent = connectedCount
                ? (toPersianDigits(connectedCount) + " پیام‌رسان از ۲ پیام‌رسان متصل است")
                : "تلگرام و بله به این حساب متصل نیستند";
        }
        if (botConnectionsRefresh) {
            botConnectionsRefresh.disabled = botConnectionsState.loading || !!botConnectionsState.disconnectingPlatform;
        }
    }

    async function loadBotConnections(force) {
        var userKey = accountUserKey();
        if (!userKey || botConnectionsState.loading) {
            return;
        }
        if (!force && botConnectionsState.loadedForUserKey === userKey && botConnectionsState.connections) {
            renderBotConnections();
            return;
        }
        botConnectionsState.loading = true;
        setInlineFeedback(botConnectionsFeedback, "در حال دریافت وضعیت اتصال‌ها…", "", true);
        renderBotConnections();
        try {
            var response = await botConnectionsGet();
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                return;
            }
            if (!response || !response.success || response.contractVersion !== "bot-account-connections-v1") {
                throw new Error((response && response.error) || "وضعیت اتصال‌ها دریافت نشد.");
            }
            botConnectionsState.connections = response.connections || {};
            botConnectionsState.csrfToken = String(response.csrfToken || "");
            botConnectionsState.loadedForUserKey = userKey;
            setInlineFeedback(botConnectionsFeedback, "", "");
        } catch (error) {
            setInlineFeedback(botConnectionsFeedback, String(error && error.message || "وضعیت اتصال‌ها دریافت نشد."), "error");
        } finally {
            botConnectionsState.loading = false;
            renderBotConnections();
        }
    }

    async function disconnectBotConnection(platform) {
        var meta = botPlatformMeta(platform);
        var item = botConnectionsState.connections && botConnectionsState.connections[platform];
        if (!item || item.connected !== true || botConnectionsState.disconnectingPlatform) {
            return;
        }
        if (!window.confirm("اتصال ربات " + meta.label + " به حساب سایت قطع شود؟ دسترسی همان ربات فوراً بسته می‌شود.")) {
            return;
        }
        botConnectionsState.disconnectingPlatform = platform;
        setInlineFeedback(botConnectionsFeedback, "در حال قطع اتصال ربات " + meta.label + "…", "", true);
        renderBotConnections();
        try {
            var response = await botConnectionDisconnect(platform);
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                return;
            }
            if (!response || !response.success || response.contractVersion !== "bot-account-connections-v1") {
                throw new Error((response && response.error) || "قطع اتصال انجام نشد.");
            }
            botConnectionsState.loadedForUserKey = "";
            botConnectionsState.connections = null;
            await loadBotConnections(true);
            setInlineFeedback(botConnectionsFeedback, "اتصال ربات " + meta.label + " قطع شد و پیام اطلاع‌رسانی برای همان حساب در صف ارسال قرار گرفت.", "success");
        } catch (error) {
            setInlineFeedback(botConnectionsFeedback, String(error && error.message || "قطع اتصال انجام نشد."), "error");
        } finally {
            botConnectionsState.disconnectingPlatform = "";
            renderBotConnections();
        }
    }

    function requestUsers() {
        var query = new URLSearchParams({ action: "users" });
        var cohortKey = ownerActiveCohortKey();
        if (cohortKey) {
            query.set("cohort", cohortKey);
        }
        return fetch("/api/auth_api.php?" + query.toString(), {
            method: "GET",
            credentials: "same-origin",
            headers: {
                "Accept": "application/json"
            }
        }).then(function (response) {
            return response.json().catch(function () {
                return {
                    success: false,
                    error: "پاسخ نامعتبر از سرور دریافت شد."
                };
            }).then(function (data) {
                data.httpStatus = response.status;
                return data;
            });
        }).catch(networkErrorResponse);
    }

    function analyticsGet(action, payload) {
        var query = new URLSearchParams(Object.assign({ action: action }, payload || {}));
        return fetch("/api/analytics_api.php?" + query.toString(), {
            method: "GET",
            credentials: "same-origin",
            headers: {
                "Accept": "application/json"
            }
        }).then(function (response) {
            return response.json().catch(function () {
                return {
                    success: false,
                    error: "پاسخ نامعتبر از سرور دریافت شد."
                };
            }).then(function (data) {
                data.httpStatus = response.status;
                return data;
            });
        }).catch(networkErrorResponse);
    }

    function navidGet(action) {
        return fetch("/api/navid_api.php?action=" + encodeURIComponent(action), {
            method: "GET",
            credentials: "same-origin",
            headers: {
                "Accept": "application/json"
            }
        }).then(function (response) {
            return response.json().catch(function () {
                return {
                    success: false,
                    error: "پاسخ نامعتبر از سرور دریافت شد."
                };
            }).then(function (data) {
                data.httpStatus = response.status;
                return data;
            });
        }).catch(networkErrorResponse);
    }

    function navidPost(action, payload) {
        return fetch("/api/navid_api.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                "Accept": "application/json"
            },
            body: new URLSearchParams(Object.assign({ action: action }, payload || {}))
        }).then(function (response) {
            return response.json().catch(function () {
                return {
                    success: false,
                    error: "پاسخ نامعتبر از سرور دریافت شد."
                };
            }).then(function (data) {
                data.httpStatus = response.status;
                if (action === "syncNow" || action === "captchaChallenge" || action === "completeReconnect") {
                    navidSyncChallengeVisual((data && data.ownerStatus) || navidState.ownerStatus, data && data.captchaDataUri);
                }
                return data;
            });
        }).catch(networkErrorResponse);
    }

    function consumeUnauthorized(response, fallbackText) {
        var auth = window.Dent1402Auth && typeof window.Dent1402Auth === "object"
            ? window.Dent1402Auth
            : null;

        if (!auth) {
            return false;
        }

        var message = fallbackText || "نشست شما منقضی شده است.";
        try {
            if (typeof auth.handleUnauthorizedPayload === "function") {
                return !!auth.handleUnauthorizedPayload(response, message);
            }
        } catch (_error) {
            // Ignore stale auth surface mismatch and continue fallback.
        }

        if (response && (response.loggedOut || response.httpStatus === 401)) {
            if (typeof auth.markUnauthorized === "function") {
                auth.markUnauthorized((response && response.error) || message);
            }
            return true;
        }

        return false;
    }

    function notificationsUserKey() {
        return accountUserKey(currentUser);
    }

    function notificationsGet(action, params) {
        var query = new URLSearchParams(Object.assign({ action: action }, params || {}));
        return fetch("/api/notifications_api.php?" + query.toString(), {
            method: "GET",
            credentials: "same-origin",
            headers: {
                "Accept": "application/json"
            }
        }).then(function (response) {
            return response.json().catch(function () {
                return {
                    success: false,
                    error: "پاسخ نامعتبر از سرور دریافت شد."
                };
            }).then(function (data) {
                data.httpStatus = response.status;
                return data;
            });
        }).catch(networkErrorResponse);
    }

    function notificationsPost(action, payload) {
        return fetch("/api/notifications_api.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                "Accept": "application/json"
            },
            body: new URLSearchParams(Object.assign({ action: action }, payload || {}))
        }).then(function (response) {
            return response.json().catch(function () {
                return {
                    success: false,
                    error: "پاسخ نامعتبر از سرور دریافت شد."
                };
            }).then(function (data) {
                data.httpStatus = response.status;
                return data;
            });
        }).catch(networkErrorResponse);
    }

    function notificationsResetState() {
        notificationsState.loading = false;
        notificationsState.requestToken = 0;
        notificationsState.loadedForUserKey = "";
        notificationsState.summary = null;
        notificationsState.preview = null;
        notificationsState.preferences = null;
        notificationsState.draftPreferences = null;
        notificationsState.manager = null;
        notificationsState.items = [];
        notificationsState.activeFilter = "all";
        notificationsState.savingPrefs = false;
        notificationsState.markingAll = false;
        notificationsState.broadcasting = false;
        notificationsState.markingIds = {};
        notificationsState.snoozingIds = {};
        notificationsState.audienceById = {};
        notificationsState.audienceLoadingIds = {};
        notificationsState.expandedAudienceId = "";
        notificationsState.deletingId = "";
    }

    function notificationsDispatchSummary(summary) {
        var unreadCount = Math.max(0, Math.floor(toNumber(summary && summary.unreadCount, 0)));
        window.dispatchEvent(new CustomEvent("dent1402:notifications-change", {
            detail: {
                unreadCount: unreadCount,
                preview: notificationsState.preview || null
            }
        }));
    }

    function notificationsNormalizeDigestHourValue(value, fallback) {
        var normalized = Math.floor(toNumber(value, fallback));
        if (!Number.isFinite(normalized)) {
            normalized = Math.floor(toNumber(fallback, 8));
        }
        return Math.max(0, Math.min(23, normalized));
    }

    function notificationsPreferenceSnapshotFromInputs() {
        var base = notificationsState.preferences && typeof notificationsState.preferences === "object"
            ? notificationsState.preferences
            : {};
        return {
            navidAssignmentAlerts: notificationsNavidAlertsToggle
                ? !!notificationsNavidAlertsToggle.checked
                : !!base.navidAssignmentAlerts,
            formReminders: notificationsFormRemindersToggle
                ? !!notificationsFormRemindersToggle.checked
                : !!base.formReminders,
            paymentReminders: notificationsPaymentRemindersToggle
                ? !!notificationsPaymentRemindersToggle.checked
                : !!base.paymentReminders,
            dailyDigestEnabled: notificationsDigestEnabledToggle
                ? !!notificationsDigestEnabledToggle.checked
                : !!base.dailyDigestEnabled,
            dailyDigestHour: notificationsNormalizeDigestHourValue(
                notificationsDigestHourInput ? notificationsDigestHourInput.value : (base.dailyDigestHour || 8),
                base.dailyDigestHour || 8
            )
        };
    }

    function notificationsCurrentPreferenceView() {
        var base = notificationsState.preferences && typeof notificationsState.preferences === "object"
            ? notificationsState.preferences
            : {};
        var draft = notificationsState.draftPreferences && typeof notificationsState.draftPreferences === "object"
            ? notificationsState.draftPreferences
            : null;
        return draft ? Object.assign({}, base, draft) : base;
    }

    function notificationsUpdatePreferenceDraft(shouldRender) {
        notificationsState.draftPreferences = notificationsPreferenceSnapshotFromInputs();
        if (shouldRender) {
            renderNotificationsSurface();
        }
    }

    function notificationsItemIsImportant(item) {
        return !!(item && item.important);
    }

    function notificationsLatestUnreadItemFromItems() {
        return notificationsState.items.find(function (item) {
            return !!(item && item.unread && notificationsItemIsImportant(item));
        }) || notificationsState.items.find(function (item) {
            return !!(item && item.unread);
        }) || null;
    }

    function notificationsLatestUnreadItem() {
        return notificationsLatestUnreadItemFromItems() || (
            notificationsState.preview && notificationsState.preview.unread !== false
                ? notificationsState.preview
                : null
        );
    }

    function notificationsSyncPreview() {
        var previewFromItems = notificationsLatestUnreadItemFromItems();
        if (previewFromItems) {
            notificationsState.preview = previewFromItems;
            return;
        }
        if (!notificationsState.preview || notificationsState.preview.unread === false) {
            notificationsState.preview = null;
        }
    }

    function notificationsApplyResponseMeta(response) {
        if (!response || typeof response !== "object") {
            return;
        }
        notificationsState.summary = response.summary || notificationsState.summary;
        if (response.preferences) {
            notificationsState.preferences = response.preferences;
        }
        if (response.manager) {
            notificationsState.manager = response.manager;
        }
        if (Object.prototype.hasOwnProperty.call(response, "preview")) {
            notificationsState.preview = response.preview && typeof response.preview === "object"
                ? response.preview
                : null;
        }
    }

    function notificationsCompactText(value, fallback, maxLength) {
        var text = String(value || "").replace(/\s+/g, " ").trim();
        if (!text) {
            text = String(fallback || "").trim();
        }
        if (!text || !maxLength || text.length <= maxLength) {
            return text;
        }
        return text.slice(0, Math.max(0, maxLength - 1)).trim() + "…";
    }

    function notificationsItemIsManaged(item) {
        var managerMeta = item && item.manager && typeof item.manager === "object" ? item.manager : {};
        return !!(managerMeta.canInspectAudience || managerMeta.canDelete);
    }

    function notificationsMatchesFilter(item, filterKey) {
        var key = String(filterKey || "all");
        if (key === "unread") {
            return !!(item && item.unread && !item.scheduled);
        }
        if (key === "important") {
            return !!(item && item.unread && !item.scheduled && notificationsItemIsImportant(item));
        }
        if (key === "navid") {
            return !!(item && item.kind === "navid-assignment");
        }
        if (key === "scheduled") {
            return !!(item && item.scheduled);
        }
        if (key === "manager") {
            return notificationsItemIsManaged(item);
        }
        return true;
    }

    function notificationsFilterConfigs(items) {
        var list = Array.isArray(items) ? items : [];
        var configs = [
            { key: "all", label: "همه", count: list.length },
            { key: "unread", label: "خوانده‌نشده", count: list.filter(function (item) { return notificationsMatchesFilter(item, "unread"); }).length },
            { key: "important", label: "مهم", count: list.filter(function (item) { return notificationsMatchesFilter(item, "important"); }).length },
            { key: "navid", label: "نوید", count: list.filter(function (item) { return notificationsMatchesFilter(item, "navid"); }).length },
            { key: "manager", label: "مدیریتی", count: list.filter(function (item) { return notificationsMatchesFilter(item, "manager"); }).length },
            { key: "scheduled", label: "زمان‌بندی", count: list.filter(function (item) { return notificationsMatchesFilter(item, "scheduled"); }).length }
        ];

        return configs.filter(function (config) {
            return config.key === "all" || config.key === "important" || config.count > 0;
        });
    }

    function notificationsNormalizeActiveFilter(items) {
        var available = notificationsFilterConfigs(items).map(function (config) {
            return config.key;
        });
        if (available.indexOf(notificationsState.activeFilter) === -1) {
            notificationsState.activeFilter = "all";
        }
        return notificationsState.activeFilter;
    }

    function notificationsFilteredItems(items) {
        var list = Array.isArray(items) ? items : [];
        var filterKey = notificationsNormalizeActiveFilter(list);
        return list.filter(function (item) {
            return notificationsMatchesFilter(item, filterKey);
        });
    }

    function notificationsBodyHtml(item) {
        var deployBody = notificationsDeployBodyHtml(item);
        if (deployBody) {
            return deployBody;
        }

        var text = String(item && item.body || "").trim();
        if (!text) {
            return "";
        }

        var bodyHtml = escapeHtml(text).replace(/\r?\n/g, "<br>");
        var isLong = text.length > 220 || text.indexOf("\n") >= 0;
        if (!isLong) {
            return '<p class="account-notification-item__body">' + bodyHtml + "</p>";
        }

        return [
            '<details class="account-notification-item__body-shell">',
            '  <summary class="account-notification-item__body-preview">' + escapeHtml(notificationsCompactText(text, "", 180)) + "</summary>",
            '  <p class="account-notification-item__body">' + bodyHtml + "</p>",
            "</details>"
        ].join("");
    }

    function notificationsValidateBroadcastPayload(payload) {
        var data = payload && typeof payload === "object" ? payload : {};
        if (!String(data.targetKey || "").trim()) {
            return "مقصد اعلان را انتخاب کن.";
        }
        if (!String(data.title || "").trim() && !String(data.body || "").trim()) {
            return "عنوان یا متن اعلان را وارد کن.";
        }
        if (String(data.ctaLabel || "").trim() && !String(data.ctaHref || "").trim()) {
            return "وقتی متن دکمه را وارد می‌کنی، مسیر آن را هم مشخص کن.";
        }
        var href = String(data.ctaHref || "").trim();
        if (href && (!/^\/(?!\/)/.test(href))) {
            return "مسیر دکمه باید با / شروع شود.";
        }
        var scheduleAt = String(data.scheduleAt || "").trim();
        if (scheduleAt && Number.isNaN(new Date(scheduleAt).getTime())) {
            return "زمان انتشار معتبر نیست.";
        }
        return "";
    }

    function notificationsDeployMeta(item) {
        if (!item || item.source !== "deploy") {
            return null;
        }

        var meta = item.meta && typeof item.meta === "object" ? item.meta : {};
        var body = String(item.body || "");
        var title = String(item.title || "");
        var version = String(meta.version || "").trim();
        var deployedAt = String(meta.deployedAt || item.effectiveAt || item.createdAt || "").trim();
        var branch = String(meta.branch || "").trim();
        var deployHead = String(meta.deployHead || "").trim();

        if (!version) {
            var titleMatch = title.match(/\b(\d{8}-\d{6})\b/);
            if (titleMatch && titleMatch[1]) {
                version = titleMatch[1];
            }
        }
        if (!version) {
            var bodyVersionMatch = body.match(/^نسخه(?: منتشرشده| فعال)?:\s*(.+)$/m);
            if (bodyVersionMatch && bodyVersionMatch[1]) {
                version = bodyVersionMatch[1].trim();
            }
        }
        if (!branch) {
            var branchMatch = body.match(/^شاخه(?: استقرار)?:\s*(.+)$/m);
            if (branchMatch && branchMatch[1]) {
                branch = branchMatch[1].trim();
            }
        }
        if (!deployHead) {
            var headMatch = body.match(/^(?:HEAD|کد استقرار):\s*(.+)$/mi);
            if (headMatch && headMatch[1]) {
                deployHead = headMatch[1].trim();
            }
        }
        if (!deployedAt) {
            var timeMatch = body.match(/^زمان(?: دقیق)?(?: deploy| استقرار)?(?: \(ایران\))?:\s*(.+)$/m);
            if (timeMatch && timeMatch[1]) {
                deployedAt = timeMatch[1].trim();
            }
        }

        return {
            version: version,
            deployedAt: deployedAt,
            branch: branch,
            deployHead: deployHead
        };
    }

    function notificationsDeploySummaryRows(item) {
        var meta = notificationsDeployMeta(item);
        if (!meta) {
            return [];
        }

        var rows = [];
        if (meta.version) {
            rows.push({
                label: "نسخه",
                value: toPersianDigits(meta.version),
                latin: false
            });
        }
        if (meta.deployedAt) {
            rows.push({
                label: "زمان استقرار",
                value: formatJalaliDateTime(meta.deployedAt, "—", true),
                latin: false
            });
        }
        if (meta.branch) {
            rows.push({
                label: "شاخه",
                value: meta.branch,
                latin: true
            });
        }
        if (meta.deployHead) {
            rows.push({
                label: "کد استقرار",
                value: String(meta.deployHead).slice(0, 12),
                latin: true
            });
        }
        return rows;
    }

    function notificationsDeployPreviewText(item) {
        var meta = notificationsDeployMeta(item);
        if (!meta) {
            return "";
        }

        var parts = [];
        if (meta.version) {
            parts.push("نسخه " + toPersianDigits(meta.version));
        }
        if (meta.deployedAt) {
            parts.push("در " + formatJalaliDateTime(meta.deployedAt, "—", true));
        }
        if (!parts.length) {
            return "گزارش استقرار جدید سایت ثبت شد.";
        }
        return parts.join(" ") + " روی سایت منتشر شد.";
    }

    function notificationsDeployBodyHtml(item) {
        var rows = notificationsDeploySummaryRows(item);
        if (!item || item.source !== "deploy" || !rows.length) {
            return "";
        }

        return [
            '<div class="account-notification-item__deploy-summary">',
            '  <p class="account-notification-item__body">استقرار جدید سایت با موفقیت ثبت شد.</p>',
            '  <div class="account-notification-item__deploy-grid">',
            rows.map(function (row) {
                return [
                    '    <div class="account-notification-item__deploy-row">',
                    '      <span class="account-notification-item__deploy-label">' + escapeHtml(String(row.label || "")) + "</span>",
                    '      <strong class="account-notification-item__deploy-value"' + (row.latin ? ' dir="ltr" data-latin-digits="true"' : "") + ">" + escapeHtml(String(row.value || "")) + "</strong>",
                    "    </div>"
                ].join("");
            }).join(""),
            "  </div>",
            "</div>"
        ].join("");
    }

    function notificationsKindLabel(item) {
        if (item && item.kind === "navid-assignment") {
            return "نوید";
        }
        var source = String(item && item.source || "");
        if (source === "forms") {
            return "فرم";
        }
        if (source === "payments") {
            return "پرداخت";
        }
        if (source === "digest") {
            return "خلاصه روزانه";
        }
        if (item && item.source === "deploy") {
            return "استقرار";
        }
        return "اعلان";
    }

    function notificationsItemDisplayAt(item) {
        if (!item || typeof item !== "object") {
            return "—";
        }
        return formatJalaliDateTime(
            item.scheduled ? (item.publishAt || item.effectiveAt || item.createdAt) : (item.effectiveAt || item.createdAt),
            "—"
        );
    }

    function notificationsPrimaryState(item) {
        if (item && item.scheduled) {
            return {
                text: "زمان‌بندی",
                className: " is-scheduled"
            };
        }
        if (item && item.unread) {
            return {
                text: "جدید",
                className: " is-unread"
            };
        }
        return {
            text: "",
            className: ""
        };
    }

    function notificationsSmsStatusLabel(sms) {
        var status = String(sms && sms.status || "");
        if (!sms || !sms.requested) {
            return "";
        }
        if (status === "pending") return "SMS در صف";
        if (status === "sending") return "در حال SMS";
        if (status === "sent") return "SMS ارسال شد";
        if (status === "partial") return "SMS ناقص";
        if (status === "failed") return "SMS ناموفق";
        return "SMS فعال";
    }

    function notificationsAudienceSummaryText(summary) {
        var total = Math.max(0, Math.floor(toNumber(summary && summary.recipientCount, 0)));
        var viewed = Math.max(0, Math.floor(toNumber(summary && summary.viewedCount, 0)));
        if (total <= 0) {
            return "";
        }
        return viewed.toLocaleString("fa-IR") + " از " + total.toLocaleString("fa-IR") + " دیده‌اند";
    }

    function notificationsRowMetaText(item) {
        if (item && item.source === "deploy") {
            return "گزارش خودکار استقرار سایت";
        }

        var parts = [];
        var senderLabel = String(item && item.senderLabel || "").trim();
        var targetLabel = String(item && item.targetLabel || "").trim();
        if (senderLabel) {
            parts.push(senderLabel);
        }
        if (targetLabel && targetLabel !== "همه ورودی‌ها") {
            parts.push(targetLabel);
        }
        return parts.join(" • ");
    }

    function notificationsFooterNoteText(item, audienceText) {
        var parts = [];
        if (item && item.scheduled) {
            parts.push("انتشار: " + notificationsItemDisplayAt(item));
        }
        if (audienceText) {
            parts.push(audienceText);
        }
        return parts.join(" • ");
    }

    function notificationsComposeContainer() {
        if (notificationsComposeShell) {
            return notificationsComposeShell;
        }
        if (notificationsManagerCard && String(notificationsManagerCard.tagName || "").toUpperCase() === "DETAILS") {
            return notificationsManagerCard;
        }
        return null;
    }

    function notificationsSetComposeOpen(nextOpen) {
        var shell = notificationsComposeContainer();
        if (!shell || typeof shell.open !== "boolean") {
            return;
        }
        shell.open = !!nextOpen;
    }

    function notificationsOverviewPillHtml(label, value, tone) {
        return [
            '<span class="account-notifications-overview__pill"' + (tone ? ' data-tone="' + escapeHtml(tone) + '"' : "") + '>',
            '  <strong>' + escapeHtml(String(value || "—")) + "</strong>",
            '  <span>' + escapeHtml(String(label || "")) + "</span>",
            "</span>"
        ].join("");
    }

    function notificationsOverviewHtml(summary, manager) {
        var unreadCount = Math.max(0, Math.floor(toNumber(summary && summary.unreadCount, 0)));
        var importantUnreadCount = Math.max(0, Math.floor(toNumber(summary && summary.importantUnreadCount, 0)));
        var reminderCount = Math.max(0, Math.floor(toNumber(summary && summary.reminderCount, 0)));
        var visibleCount = Math.max(0, Math.floor(toNumber(summary && summary.visibleCount, 0)));
        var navidCount = Math.max(0, Math.floor(toNumber(summary && summary.navidCount, 0)));
        var scheduledCount = Math.max(0, Math.floor(toNumber(summary && summary.scheduledCount, 0)));
        var latestTitle = String(summary && summary.latestTitle || "").trim();
        var pills = [
            notificationsOverviewPillHtml("جدید", unreadCount.toLocaleString("fa-IR"), unreadCount > 0 ? "warn" : "ok"),
            notificationsOverviewPillHtml("مهم", importantUnreadCount.toLocaleString("fa-IR"), importantUnreadCount > 0 ? "danger" : ""),
            notificationsOverviewPillHtml("یادآور", reminderCount.toLocaleString("fa-IR"), reminderCount > 0 ? "warn" : ""),
            notificationsOverviewPillHtml(manager && manager.canBroadcast ? "در فید" : "قابل‌نمایش", visibleCount.toLocaleString("fa-IR"), ""),
            notificationsOverviewPillHtml("نوید", navidCount.toLocaleString("fa-IR"), "")
        ];
        if (manager && manager.canBroadcast) {
            pills.splice(2, 0, notificationsOverviewPillHtml("در صف", scheduledCount.toLocaleString("fa-IR"), scheduledCount > 0 ? "warn" : ""));
        }

        return [
            '<p class="account-notifications-overview__lead">' + escapeHtml(
                latestTitle
                    ? ("آخرین مورد: " + latestTitle)
                    : (importantUnreadCount > 0
                        ? "اعلان‌های مهم خوانده‌نشده و یادآورها از همین‌جا پیگیری می‌شوند."
                        : (unreadCount > 0 ? "اعلان‌های جدید شما اینجا جمع می‌شوند." : "فید اعلان‌ها جمع‌وجور شد و جزئیات هر مورد فقط هنگام نیاز باز می‌شود."))
            ) + "</p>",
            '<div class="account-notifications-overview__pills">' + pills.join("") + "</div>"
        ].join("");
    }

    function notificationsSmsDetailText(sms) {
        if (!sms || !sms.requested) {
            return "برای این اعلان، ارسال پیامک فعال نبود.";
        }

        var eligible = Math.max(0, Math.floor(toNumber(sms.eligibleCount, 0)));
        var sent = Math.max(0, Math.floor(toNumber(sms.sentCount, 0)));
        var skipped = Math.max(0, Math.floor(toNumber(sms.skippedCount, 0)));
        var statusLabel = notificationsSmsStatusLabel(sms) || "SMS";
        var parts = [statusLabel];
        if (eligible > 0) {
            parts.push("دارای شماره تاییدشده: " + eligible.toLocaleString("fa-IR"));
        }
        if (sent > 0) {
            parts.push("ثبت‌شده: " + sent.toLocaleString("fa-IR"));
        }
        if (skipped > 0) {
            parts.push("بدون شماره تاییدشده: " + skipped.toLocaleString("fa-IR"));
        }
        if (sms.lastMessage) {
            parts.push(String(sms.lastMessage));
        }
        return parts.join(" • ");
    }

    function notificationsAudienceEntryHtml(entry, includeReadAt) {
        var meta = [
            "شماره دانشجویی: " + escapeHtml(String(entry && entry.studentNumber || "—")),
            escapeHtml(String(entry && entry.roleLabel || "دانشجو")),
            escapeHtml(String(entry && entry.cohortLabel || "—"))
        ];
        if (entry && entry.hasVerifiedPhone && entry.phoneMasked) {
            meta.push("شماره تاییدشده: " + escapeHtml(String(entry.phoneMasked)));
        } else {
            meta.push("شماره تاییدشده ندارد");
        }
        if (includeReadAt && entry && entry.readAt) {
            meta.unshift("خوانده در " + escapeHtml(formatJalaliDateTime(entry.readAt, "—")));
        }

        return [
            '<li class="account-notification-audience__item">',
            '  <strong>' + escapeHtml(String(entry && entry.name || "کاربر")) + "</strong>",
            '  <span>' + meta.join(" • ") + "</span>",
            "</li>"
        ].join("");
    }

    function notificationsAudienceColumnHtml(title, items, emptyText, includeReadAt) {
        var list = Array.isArray(items) ? items : [];
        return [
            '<section class="account-notification-audience__column">',
            '  <div class="account-notification-audience__column-head">',
            '    <strong>' + escapeHtml(title) + "</strong>",
            '    <span>' + list.length.toLocaleString("fa-IR") + "</span>",
            "  </div>",
            list.length
                ? ('  <ul class="account-notification-audience__list">' + list.map(function (entry) {
                    return notificationsAudienceEntryHtml(entry, includeReadAt);
                }).join("") + "</ul>")
                : ('  <p class="account-notification-audience__empty">' + escapeHtml(emptyText) + "</p>"),
            "</section>"
        ].join("");
    }

    function notificationsAudiencePanelHtml(item) {
        var id = String(item && item.id || "");
        var panelId = String(item && item.audiencePanelId || ("notification-audience-" + id));
        var managerMeta = item && item.manager && typeof item.manager === "object" ? item.manager : {};
        if (!managerMeta.canInspectAudience) {
            return "";
        }

        var open = notificationsState.expandedAudienceId === id;
        var loading = !!notificationsState.audienceLoadingIds[id];
        var payload = notificationsState.audienceById[id] && typeof notificationsState.audienceById[id] === "object"
            ? notificationsState.audienceById[id]
            : null;
        var summary = payload && payload.summary ? payload.summary : (managerMeta.audienceSummary || {});
        var sms = payload && payload.sms ? payload.sms : (item && item.sms ? item.sms : {});
        var viewed = payload && Array.isArray(payload.viewed) ? payload.viewed : [];
        var pending = payload && Array.isArray(payload.pending) ? payload.pending : [];
        var recipientCount = Math.max(0, Math.floor(toNumber(summary && summary.recipientCount, 0)));
        var viewedCount = Math.max(0, Math.floor(toNumber(summary && summary.viewedCount, 0)));
        var pendingCount = Math.max(0, Math.floor(toNumber(summary && summary.pendingCount, 0)));
        var verifiedPhoneCount = Math.max(0, Math.floor(toNumber(summary && summary.verifiedPhoneCount, 0)));

        return [
            '<section id="' + escapeHtml(panelId) + '" class="account-notification-audience"' + (open ? "" : " hidden") + ' data-notification-audience-panel="' + escapeHtml(id) + '">',
            '  <div class="account-notification-audience__stats">',
            '    <div class="account-notification-audience__stat"><strong>' + recipientCount.toLocaleString("fa-IR") + '</strong><span>مخاطب</span></div>',
            '    <div class="account-notification-audience__stat"><strong>' + viewedCount.toLocaleString("fa-IR") + '</strong><span>دیده‌اند</span></div>',
            '    <div class="account-notification-audience__stat"><strong>' + pendingCount.toLocaleString("fa-IR") + '</strong><span>ندیده‌اند</span></div>',
            '    <div class="account-notification-audience__stat"><strong>' + verifiedPhoneCount.toLocaleString("fa-IR") + '</strong><span>شماره تاییدشده</span></div>',
            "  </div>",
            '  <p class="account-notification-audience__sms">' + escapeHtml(notificationsSmsDetailText(sms)) + "</p>",
            loading
                ? '  <p class="account-notification-audience__hint">در حال دریافت وضعیت مشاهده‌کنندگان...</p>'
                : payload
                    ? ('  <div class="account-notification-audience__columns">'
                        + notificationsAudienceColumnHtml("دیده‌اند", viewed, "هنوز کسی این اعلان را نخوانده است.", true)
                        + notificationsAudienceColumnHtml("ندیده‌اند", pending, "همه مخاطبان این اعلان را دیده‌اند.", false)
                        + "</div>")
                    : '  <p class="account-notification-audience__hint">برای دریافت لیست کامل، دکمه وضعیت مشاهده را باز کن.</p>',
            "</section>"
        ].join("");
    }

    function notificationsHubMetaText() {
        var summary = notificationsState.summary || {};
        var manager = notificationsState.manager || {};
        var unreadCount = Math.max(0, Math.floor(toNumber(summary.unreadCount, 0)));
        var importantUnreadCount = Math.max(0, Math.floor(toNumber(summary.importantUnreadCount, 0)));
        var scheduledCount = Math.max(0, Math.floor(toNumber(summary.scheduledCount, 0)));
        if (notificationsState.loading) {
            return "در حال دریافت اعلان‌ها...";
        }
        if (unreadCount > 0) {
            var latestTitle = String(summary.latestTitle || "").trim();
            var meta = unreadCount.toLocaleString("fa-IR") + " اعلان جدید";
            if (importantUnreadCount > 0) {
                meta += " • " + importantUnreadCount.toLocaleString("fa-IR") + " مورد مهم";
            }
            return meta + (latestTitle ? (" • آخرین مورد: " + latestTitle) : "");
        }

        if (notificationsState.loadedForUserKey) {
            if (manager.canBroadcast && scheduledCount > 0) {
                return scheduledCount.toLocaleString("fa-IR") + " اعلان زمان‌بندی‌شده در صف انتشار است.";
            }
            return "فعلاً اعلان خوانده‌نشده‌ای برای این حساب ثبت نشده است.";
        }

        return "آخرین اعلان‌های این حساب در همین بخش نمایش داده می‌شوند.";
    }

    function renderNotificationsHub() {
        if (accountRowNotificationsMeta) {
            accountRowNotificationsMeta.textContent = notificationsHubMetaText();
        }

        if (!accountNavidAlertCard) {
            return;
        }

        var preview = notificationsLatestUnreadItem();
        accountNavidAlertCard.hidden = !preview;
        if (!preview) {
            return;
        }

        if (accountNotificationAlertKind) {
            accountNotificationAlertKind.textContent = notificationsKindLabel(preview);
        }
        if (accountNavidAlertTime) {
            accountNavidAlertTime.textContent = formatJalaliDateTime(preview.effectiveAt || preview.createdAt, "—");
        }
        if (accountNavidAlertTitle) {
            accountNavidAlertTitle.textContent = preview.title || (preview.kind === "navid-assignment" ? "تکلیف جدید نوید" : "اعلان جدید");
        }
        if (accountNavidAlertBody) {
            accountNavidAlertBody.textContent = preview.source === "deploy"
                ? (notificationsDeployPreviewText(preview) || "گزارش استقرار جدید سایت ثبت شد.")
                : (preview.body || (preview.kind === "navid-assignment"
                ? "برای دیدن جزئیات، بخش تکالیف نوید را باز کن."
                : "برای دیدن جزئیات، اعلان را باز کن."));
        }
        if (accountNavidAlertLink) {
            accountNavidAlertLink.href = String(preview.ctaHref || "/account/#notifications");
            accountNavidAlertLink.dataset.notificationId = String(preview.id || "");
            accountNavidAlertLink.textContent = String(preview.ctaLabel || (preview.kind === "navid-assignment" ? "مشاهده تکالیف" : "مشاهده اعلان"));
        }
        if (accountNavidAlertMarkRead) {
            var previewId = String(preview.id || "").trim();
            var isMarking = !!notificationsState.markingIds[previewId];
            accountNavidAlertMarkRead.hidden = !previewId || preview.unread === false;
            accountNavidAlertMarkRead.disabled = isMarking;
            accountNavidAlertMarkRead.dataset.notificationMark = previewId;
            accountNavidAlertMarkRead.textContent = isMarking ? "در حال ثبت..." : "علامت زده به عنوان خوانده شده";
        }
    }

    function renderNotificationsSurface() {
        var summary = notificationsState.summary || {};
        var storedPreferences = notificationsState.preferences || {};
        var preferences = notificationsCurrentPreferenceView();
        var manager = notificationsState.manager || {};
        var items = Array.isArray(notificationsState.items) ? notificationsState.items : [];
        var filterConfigs = notificationsFilterConfigs(items);
        var filteredItems = notificationsFilteredItems(items);
        var activeFilter = notificationsState.activeFilter;

        if (notificationsSummary) {
            notificationsSummary.innerHTML = notificationsOverviewHtml(summary, manager);
        }

        if (notificationsPrefsCard) {
            var canToggle = !!storedPreferences.canToggleNavidAssignmentAlerts;
            var digestEnabled = !!preferences.dailyDigestEnabled;
            var digestHour = notificationsNormalizeDigestHourValue(preferences.dailyDigestHour, 8);
            notificationsPrefsCard.hidden = false;
            if (notificationsNavidRow) {
                notificationsNavidRow.hidden = !canToggle;
            }
            if (notificationsNavidAlertsToggle) {
                notificationsNavidAlertsToggle.checked = !!preferences.navidAssignmentAlerts;
                notificationsNavidAlertsToggle.disabled = notificationsState.savingPrefs || !canToggle;
            }
            if (notificationsFormRemindersToggle) {
                notificationsFormRemindersToggle.checked = !!preferences.formReminders;
                notificationsFormRemindersToggle.disabled = notificationsState.savingPrefs;
            }
            if (notificationsPaymentRemindersToggle) {
                notificationsPaymentRemindersToggle.checked = !!preferences.paymentReminders;
                notificationsPaymentRemindersToggle.disabled = notificationsState.savingPrefs;
            }
            if (notificationsDigestEnabledToggle) {
                notificationsDigestEnabledToggle.checked = digestEnabled;
                notificationsDigestEnabledToggle.disabled = notificationsState.savingPrefs;
            }
            if (notificationsDigestHourInput) {
                notificationsDigestHourInput.value = String(digestHour);
                notificationsDigestHourInput.disabled = notificationsState.savingPrefs || !digestEnabled;
            }
            if (notificationsPrefsSaveButton) {
                notificationsPrefsSaveButton.disabled = notificationsState.savingPrefs;
            }
            if (notificationsPrefsHint) {
                notificationsPrefsHint.textContent = canToggle
                    ? "وقتی تکلیف جدیدی در نوید بیاید، در حساب کاربری به شما اطلاع داده می‌شود."
                    : "";
            }
        }

        if (notificationsManagerCard) {
            var canBroadcast = !!manager.canBroadcast;
            notificationsManagerCard.hidden = !canBroadcast;
            if (notificationsTargetSelect && canBroadcast) {
                var currentTarget = String(notificationsTargetSelect.value || "");
                var options = Array.isArray(manager.targets) ? manager.targets : [];
                notificationsTargetSelect.innerHTML = options.map(function (target) {
                    var key = String(target && target.key || "");
                    return '<option value="' + escapeHtml(key) + '">' + escapeHtml(String(target && target.label || key || "مقصد")) + "</option>";
                }).join("");
                notificationsTargetSelect.value = options.some(function (target) {
                    return String(target && target.key || "") === currentTarget;
                }) ? currentTarget : String(manager.defaultTargetKey || (options[0] && options[0].key) || "");
                notificationsTargetSelect.disabled = notificationsState.broadcasting;
            }
            if (notificationsBroadcastSubmit) {
                notificationsBroadcastSubmit.disabled = notificationsState.broadcasting;
            }
            var composeContainer = notificationsComposeContainer();
            if (composeContainer) {
                composeContainer.classList.toggle("is-busy", notificationsState.broadcasting);
            }
            if (notificationsTitleInput) notificationsTitleInput.disabled = notificationsState.broadcasting;
            if (notificationsBodyInput) notificationsBodyInput.disabled = notificationsState.broadcasting;
            if (notificationsCtaLabelInput) notificationsCtaLabelInput.disabled = notificationsState.broadcasting;
            if (notificationsCtaHrefInput) notificationsCtaHrefInput.disabled = notificationsState.broadcasting;
            if (notificationsScheduleInput) notificationsScheduleInput.disabled = notificationsState.broadcasting;
            if (notificationsSendSmsInput) notificationsSendSmsInput.disabled = notificationsState.broadcasting;
        }

        if (notificationsRefreshButton) {
            notificationsRefreshButton.disabled = notificationsState.loading;
        }
        if (notificationsMarkAllButton) {
            notificationsMarkAllButton.disabled = notificationsState.markingAll || Math.max(0, Math.floor(toNumber(summary.unreadCount, 0))) <= 0;
        }
        if (notificationsFilters) {
            notificationsFilters.hidden = filterConfigs.length <= 1;
            notificationsFilters.innerHTML = filterConfigs.map(function (config) {
                var key = String(config && config.key || "all");
                return '<button class="account-notifications-filter' + (key === activeFilter ? " is-active" : "") + '" type="button" data-notification-filter="' + escapeHtml(key) + '">' + escapeHtml(String(config && config.label || key)) + ' <span>(' + Math.max(0, Math.floor(toNumber(config && config.count, 0))).toLocaleString("fa-IR") + ")</span></button>";
            }).join("");
        }
        if (notificationsEmpty) {
            notificationsEmpty.hidden = filteredItems.length > 0 || notificationsState.loading;
            notificationsEmpty.textContent = activeFilter === "all"
                ? "اعلانی برای این حساب پیدا نشد."
                : (activeFilter === "important"
                    ? "اعلان مهم خوانده‌نشده‌ای پیدا نشد."
                    : "برای این فیلتر، اعلانی پیدا نشد.");
        }
        if (!notificationsList) {
            return;
        }

        notificationsList.innerHTML = filteredItems.map(function (item) {
            var id = String(item && item.id || "");
            var displayAt = notificationsItemDisplayAt(item);
            var unread = !!(item && item.unread);
            var marking = !!notificationsState.markingIds[id];
            var snoozing = !!notificationsState.snoozingIds[id];
            var ctaHref = String(item && item.ctaHref || "");
            var ctaLabel = String(item && item.ctaLabel || "مشاهده");
            var deleting = notificationsState.deletingId === id;
            var managerMeta = item && item.manager && typeof item.manager === "object" ? item.manager : {};
            var audienceSummary = managerMeta.audienceSummary || {};
            var stateBadge = notificationsPrimaryState(item);
            var sms = item && item.sms ? item.sms : {};
            var smsLabel = notificationsSmsStatusLabel(sms);
            var important = notificationsItemIsImportant(item);
            var canSnooze = !!(item && item.canSnooze && unread && !item.scheduled);
            var canInspect = !!managerMeta.canInspectAudience;
            var canDelete = !!managerMeta.canDelete;
            var audienceOpen = notificationsState.expandedAudienceId === id;
            var audienceLoading = !!notificationsState.audienceLoadingIds[id];
            var audiencePanelId = "notification-audience-" + id;
            var audienceBadgeText = notificationsAudienceSummaryText(audienceSummary);
            var metaText = notificationsRowMetaText(item);
            var footerNote = notificationsFooterNoteText(item, audienceBadgeText);
            var signals = [];
            var actions = [];

            if (stateBadge.text) {
                signals.push('<span class="account-notification-item__badge' + stateBadge.className + '">' + escapeHtml(stateBadge.text) + "</span>");
            }
            if (smsLabel) {
                signals.push('<span class="account-notification-item__badge">' + escapeHtml(smsLabel) + "</span>");
            }
            if (important) {
                signals.push('<span class="account-notification-item__badge is-important">مهم</span>');
            }

            if (ctaHref) {
                actions.push('<a class="shell-action-btn shell-action-btn-primary" href="' + escapeHtml(ctaHref) + '" data-notification-cta="true" data-notification-id="' + escapeHtml(id) + '">' + escapeHtml(ctaLabel) + "</a>");
            }
            if (!item.scheduled && unread) {
                actions.push('<button class="shell-action-btn" type="button" data-notification-mark="' + escapeHtml(id) + '"' + (marking ? " disabled" : "") + ">" + (marking ? "در حال ثبت..." : "خواندم") + "</button>");
            }
            if (canSnooze) {
                actions.push('<button class="shell-action-btn" type="button" data-notification-snooze="24" data-notification-id="' + escapeHtml(id) + '"' + (snoozing ? " disabled" : "") + ">" + (snoozing ? "در حال تعویق..." : "یادآوری فردا") + "</button>");
            }
            if (canInspect) {
                actions.push('<button class="shell-action-btn" type="button" data-notification-audience-toggle="' + escapeHtml(id) + '" aria-expanded="' + (audienceOpen ? "true" : "false") + '" aria-controls="' + escapeHtml(audiencePanelId) + '"' + (audienceLoading ? " disabled" : "") + ">" + (audienceOpen ? "بستن مخاطب‌ها" : "مخاطب‌ها") + "</button>");
            }
            if (canDelete) {
                actions.push('<button class="shell-action-btn shell-action-btn-danger" type="button" data-notification-delete="' + escapeHtml(id) + '"' + (deleting ? " disabled" : "") + ">" + (deleting ? "در حال حذف..." : "حذف") + "</button>");
            }

            return [
                '<article class="account-notification-item' + (unread ? " is-unread" : "") + (item && item.scheduled ? " is-scheduled" : "") + '" data-tone="' + escapeHtml(String(item && item.tone || "accent")) + '" data-notification-id="' + escapeHtml(id) + '">',
                '  <div class="account-notification-item__topline">',
                '    <span class="account-notification-item__eyebrow">' + escapeHtml(notificationsKindLabel(item)) + "</span>",
                signals.length
                    ? ('    <div class="account-notification-item__signals">' + signals.join("") + "</div>")
                    : "",
                "  </div>",
                '  <div class="account-notification-item__head">',
                '    <div class="account-notification-item__copy">',
                '      <h4 class="account-notification-item__title">' + escapeHtml(String(item && item.title || "بدون عنوان")) + "</h4>",
                metaText
                    ? ('      <p class="account-notification-item__meta-line">' + escapeHtml(metaText) + "</p>")
                    : "",
                "    </div>",
                '    <span class="account-notification-item__time">' + escapeHtml(displayAt) + "</span>",
                "  </div>",
                notificationsBodyHtml(item),
                footerNote
                    ? ('  <p class="account-notification-item__note">' + escapeHtml(footerNote) + "</p>")
                    : "",
                actions.length
                    ? ('  <div class="account-notification-item__actions">' + actions.join("") + "</div>")
                    : "",
                notificationsAudiencePanelHtml(Object.assign({}, item, { audiencePanelId: audiencePanelId })),
                "</article>"
            ].join("");
        }).join("");
    }

    function renderNotificationsUi() {
        renderNotificationsHub();
        renderNotificationsSurface();
    }

    function applyNotificationsPayload(payload) {
        var data = payload && typeof payload === "object" ? payload : {};
        notificationsState.summary = data.summary && typeof data.summary === "object" ? data.summary : null;
        notificationsState.preview = data.preview && typeof data.preview === "object" ? data.preview : null;
        notificationsState.preferences = data.preferences && typeof data.preferences === "object" ? data.preferences : null;
        notificationsState.draftPreferences = null;
        notificationsState.manager = data.manager && typeof data.manager === "object" ? data.manager : null;
        notificationsState.items = Array.isArray(data.items) ? data.items : [];
        var validIds = {};
        notificationsState.items.forEach(function (item) {
            var id = String(item && item.id || "").trim();
            if (id) {
                validIds[id] = true;
            }
        });
        Object.keys(notificationsState.audienceById).forEach(function (id) {
            if (!validIds[id]) {
                delete notificationsState.audienceById[id];
            }
        });
        Object.keys(notificationsState.audienceLoadingIds).forEach(function (id) {
            if (!validIds[id]) {
                delete notificationsState.audienceLoadingIds[id];
            }
        });
        Object.keys(notificationsState.markingIds).forEach(function (id) {
            if (!validIds[id]) {
                delete notificationsState.markingIds[id];
            }
        });
        Object.keys(notificationsState.snoozingIds).forEach(function (id) {
            if (!validIds[id]) {
                delete notificationsState.snoozingIds[id];
            }
        });
        if (notificationsState.expandedAudienceId && !notificationsState.items.some(function (item) {
            return String(item && item.id || "") === notificationsState.expandedAudienceId;
        })) {
            notificationsState.expandedAudienceId = "";
        }
        notificationsSyncPreview();
        renderNotificationsUi();
        notificationsDispatchSummary(notificationsState.summary);
    }

    function loadNotifications(force) {
        var userKey = notificationsUserKey();
        if (!userKey) {
            notificationsResetState();
            renderNotificationsUi();
            return Promise.resolve(null);
        }

        if (notificationsState.loading && !force) {
            return Promise.resolve(null);
        }
        if (!force && notificationsState.loadedForUserKey === userKey && notificationsState.items.length) {
            renderNotificationsUi();
            return Promise.resolve(null);
        }

        notificationsState.loading = true;
        notificationsState.loadedForUserKey = userKey;
        var requestToken = ++notificationsState.requestToken;
        setInlineFeedback(notificationsFeedback, "در حال دریافت اعلان‌ها...", "", true);
        renderNotificationsUi();
        return notificationsGet("list", { limit: 60 }).then(function (response) {
            if (requestToken !== notificationsState.requestToken) {
                return null;
            }
            notificationsState.loading = false;
            if (consumeUnauthorized(response, "نشست شما برای خواندن اعلان‌ها منقضی شده است.")) {
                notificationsResetState();
                renderNotificationsUi();
                return null;
            }
            if (!response || response.success !== true || !response.data) {
                setInlineFeedback(notificationsFeedback, (response && response.error) || "خواندن اعلان‌ها انجام نشد.", "error");
                renderNotificationsUi();
                return null;
            }

            setInlineFeedback(notificationsFeedback, "", "");
            applyNotificationsPayload(response.data);
            return response.data;
        }).catch(function () {
            if (requestToken !== notificationsState.requestToken) {
                return null;
            }
            notificationsState.loading = false;
            setInlineFeedback(notificationsFeedback, "اتصال برای دریافت اعلان‌ها برقرار نشد.", "error");
            renderNotificationsUi();
            return null;
        });
    }

    function updateNotificationsReadState(ids, unread) {
        var idSet = {};
        (Array.isArray(ids) ? ids : []).forEach(function (id) {
            var key = String(id || "").trim();
            if (key) {
                idSet[key] = true;
            }
        });

        notificationsState.items = notificationsState.items.map(function (item) {
            var itemId = String(item && item.id || "");
            if (!idSet[itemId]) {
                return item;
            }
            return Object.assign({}, item, { unread: !!unread });
        });
        notificationsSyncPreview();
    }

    function markNotificationsRead(ids) {
        var cleanIds = (Array.isArray(ids) ? ids : []).map(function (id) {
            return String(id || "").trim();
        }).filter(Boolean);
        if (!cleanIds.length) {
            return Promise.resolve(null);
        }

        cleanIds.forEach(function (id) {
            notificationsState.markingIds[id] = true;
        });
        renderNotificationsUi();

        return notificationsPost("markRead", { idsJson: JSON.stringify(cleanIds) }).then(function (response) {
            cleanIds.forEach(function (id) {
                delete notificationsState.markingIds[id];
            });
            if (consumeUnauthorized(response, "نشست شما برای ثبت خواندن اعلان‌ها منقضی شده است.")) {
                notificationsResetState();
                renderNotificationsUi();
                return null;
            }
            if (!response || response.success !== true) {
                setInlineFeedback(notificationsFeedback, (response && response.error) || "ثبت خواندن اعلان انجام نشد.", "error");
                renderNotificationsUi();
                return null;
            }

            notificationsApplyResponseMeta(response);
            updateNotificationsReadState(cleanIds, false);
            setInlineFeedback(notificationsFeedback, "", "");
            renderNotificationsUi();
            notificationsDispatchSummary(notificationsState.summary);
            return response;
        }).catch(function () {
            cleanIds.forEach(function (id) {
                delete notificationsState.markingIds[id];
            });
            setInlineFeedback(notificationsFeedback, "اتصال برای ثبت خواندن اعلان برقرار نشد.", "error");
            renderNotificationsUi();
            return null;
        });
    }

    function markAllNotificationsRead() {
        if (notificationsState.markingAll) {
            return Promise.resolve(null);
        }

        notificationsState.markingAll = true;
        setInlineFeedback(notificationsFeedback, "در حال ثبت خواندن همه اعلان‌ها...", "", true);
        renderNotificationsUi();
        return notificationsPost("markAllRead", {}).then(function (response) {
            notificationsState.markingAll = false;
            if (consumeUnauthorized(response, "نشست شما برای ثبت خواندن اعلان‌ها منقضی شده است.")) {
                notificationsResetState();
                renderNotificationsUi();
                return null;
            }
            if (!response || response.success !== true) {
                setInlineFeedback(notificationsFeedback, (response && response.error) || "ثبت خواندن همه اعلان‌ها انجام نشد.", "error");
                renderNotificationsUi();
                return null;
            }

            notificationsApplyResponseMeta(response);
            notificationsState.items = notificationsState.items.map(function (item) {
                return Object.assign({}, item, { unread: false });
            });
            notificationsSyncPreview();
            setInlineFeedback(notificationsFeedback, "همه اعلان‌ها خوانده‌شده ثبت شدند.", "success");
            renderNotificationsUi();
            notificationsDispatchSummary(notificationsState.summary);
            return response;
        }).catch(function () {
            notificationsState.markingAll = false;
            setInlineFeedback(notificationsFeedback, "اتصال برای ثبت خواندن همه اعلان‌ها برقرار نشد.", "error");
            renderNotificationsUi();
            return null;
        });
    }

    function saveNotificationsPreferences() {
        if (notificationsState.savingPrefs) {
            return Promise.resolve(null);
        }

        var draftPreferences = notificationsPreferenceSnapshotFromInputs();
        notificationsState.draftPreferences = draftPreferences;
        notificationsState.savingPrefs = true;
        setInlineFeedback(notificationsFeedback, "در حال ذخیره تنظیمات اعلان...", "", true);
        renderNotificationsUi();
        return notificationsPost("savePrefs", {
            navidAssignmentAlerts: draftPreferences.navidAssignmentAlerts ? "1" : "0",
            formReminders: draftPreferences.formReminders ? "1" : "0",
            paymentReminders: draftPreferences.paymentReminders ? "1" : "0",
            dailyDigestEnabled: draftPreferences.dailyDigestEnabled ? "1" : "0",
            dailyDigestHour: String(draftPreferences.dailyDigestHour)
        }).then(function (response) {
            notificationsState.savingPrefs = false;
            if (consumeUnauthorized(response, "نشست شما برای ذخیره تنظیمات اعلان منقضی شده است.")) {
                notificationsResetState();
                renderNotificationsUi();
                return null;
            }
            if (!response || response.success !== true) {
                setInlineFeedback(notificationsFeedback, (response && response.error) || "ذخیره تنظیمات اعلان انجام نشد.", "error");
                renderNotificationsUi();
                return null;
            }

            notificationsApplyResponseMeta(response);
            setInlineFeedback(notificationsFeedback, "تنظیمات اعلان ذخیره شد.", "success");
            loadNotifications(true);
            return response;
        }).catch(function () {
            notificationsState.savingPrefs = false;
            setInlineFeedback(notificationsFeedback, "اتصال برای ذخیره تنظیمات اعلان برقرار نشد.", "error");
            renderNotificationsUi();
            return null;
        });
    }

    function snoozeNotification(id, hours) {
        var notificationId = String(id || "").trim();
        var snoozeHours = Math.max(1, Math.floor(toNumber(hours, 24)));
        if (!notificationId || notificationsState.snoozingIds[notificationId]) {
            return Promise.resolve(null);
        }

        notificationsState.snoozingIds[notificationId] = true;
        setInlineFeedback(notificationsFeedback, "در حال ثبت تعویق اعلان...", "", true);
        renderNotificationsUi();
        return notificationsPost("snooze", {
            id: notificationId,
            hours: String(snoozeHours)
        }).then(function (response) {
            delete notificationsState.snoozingIds[notificationId];
            if (consumeUnauthorized(response, "نشست شما برای تعویق اعلان منقضی شده است.")) {
                notificationsResetState();
                renderNotificationsUi();
                return null;
            }
            if (!response || response.success !== true) {
                setInlineFeedback(notificationsFeedback, (response && response.error) || "تعویق اعلان انجام نشد.", "error");
                renderNotificationsUi();
                return null;
            }

            notificationsApplyResponseMeta(response);
            setInlineFeedback(notificationsFeedback, response.message || "اعلان برای بعداً کنار گذاشته شد.", "success");
            loadNotifications(true);
            return response;
        }).catch(function () {
            delete notificationsState.snoozingIds[notificationId];
            setInlineFeedback(notificationsFeedback, "اتصال برای تعویق اعلان برقرار نشد.", "error");
            renderNotificationsUi();
            return null;
        });
    }

    function loadNotificationAudience(id, force) {
        var notificationId = String(id || "").trim();
        if (!notificationId) {
            return Promise.resolve(null);
        }
        if (notificationsState.audienceLoadingIds[notificationId]) {
            return Promise.resolve(null);
        }
        if (!force && notificationsState.audienceById[notificationId]) {
            notificationsState.expandedAudienceId = notificationId;
            renderNotificationsUi();
            return Promise.resolve(notificationsState.audienceById[notificationId]);
        }

        notificationsState.expandedAudienceId = notificationId;
        notificationsState.audienceLoadingIds[notificationId] = true;
        renderNotificationsUi();
        return notificationsGet("audience", { id: notificationId }).then(function (response) {
            delete notificationsState.audienceLoadingIds[notificationId];
            if (consumeUnauthorized(response, "نشست شما برای خواندن وضعیت اعلان منقضی شده است.")) {
                notificationsResetState();
                renderNotificationsUi();
                return null;
            }
            if (!response || response.success !== true || !response.data) {
                setInlineFeedback(notificationsFeedback, (response && response.error) || "خواندن وضعیت این اعلان انجام نشد.", "error");
                renderNotificationsUi();
                return null;
            }

            notificationsState.audienceById[notificationId] = response.data;
            notificationsState.items = notificationsState.items.map(function (item) {
                if (String(item && item.id || "") !== notificationId) {
                    return item;
                }
                var nextManager = Object.assign({}, item && item.manager || {});
                if (response.data.summary) {
                    nextManager.audienceSummary = response.data.summary;
                }
                return Object.assign({}, item, { manager: nextManager });
            });
            setInlineFeedback(notificationsFeedback, "", "");
            renderNotificationsUi();
            return response.data;
        }).catch(function () {
            delete notificationsState.audienceLoadingIds[notificationId];
            setInlineFeedback(notificationsFeedback, "اتصال برای خواندن وضعیت مشاهده‌کنندگان برقرار نشد.", "error");
            renderNotificationsUi();
            return null;
        });
    }

    function toggleNotificationAudience(id) {
        var notificationId = String(id || "").trim();
        if (!notificationId) {
            return;
        }
        if (notificationsState.expandedAudienceId === notificationId) {
            notificationsState.expandedAudienceId = "";
            renderNotificationsUi();
            return;
        }
        loadNotificationAudience(notificationId, false);
    }

    function deleteNotification(id) {
        var notificationId = String(id || "").trim();
        if (!notificationId || notificationsState.deletingId === notificationId) {
            return Promise.resolve(null);
        }

        var item = notificationsState.items.find(function (candidate) {
            return String(candidate && candidate.id || "") === notificationId;
        }) || null;
        var title = String(item && item.title || "این اعلان");
        if (!window.confirm("اعلان \"" + title + "\" حذف شود؟ این کار برای همه مخاطبان همان اعلان اعمال می‌شود.")) {
            return Promise.resolve(null);
        }

        notificationsState.deletingId = notificationId;
        setInlineFeedback(notificationsFeedback, "در حال حذف اعلان...", "", true);
        renderNotificationsUi();
        return notificationsPost("delete", { id: notificationId }).then(function (response) {
            notificationsState.deletingId = "";
            if (consumeUnauthorized(response, "نشست شما برای حذف اعلان منقضی شده است.")) {
                notificationsResetState();
                renderNotificationsUi();
                return null;
            }
            if (!response || response.success !== true) {
                setInlineFeedback(notificationsFeedback, (response && response.error) || "حذف اعلان انجام نشد.", "error");
                renderNotificationsUi();
                return null;
            }

            var deletedId = String(response.deletedId || notificationId);
            notificationsState.items = notificationsState.items.filter(function (entry) {
                return String(entry && entry.id || "") !== deletedId;
            });
            delete notificationsState.audienceById[deletedId];
            delete notificationsState.audienceLoadingIds[deletedId];
            if (notificationsState.expandedAudienceId === deletedId) {
                notificationsState.expandedAudienceId = "";
            }
            notificationsApplyResponseMeta(response);
            notificationsSyncPreview();
            setInlineFeedback(notificationsFeedback, response.message || "اعلان حذف شد.", "success");
            renderNotificationsUi();
            notificationsDispatchSummary(notificationsState.summary);
            return response;
        }).catch(function () {
            notificationsState.deletingId = "";
            setInlineFeedback(notificationsFeedback, "اتصال برای حذف اعلان برقرار نشد.", "error");
            renderNotificationsUi();
            return null;
        });
    }

    function submitNotificationsBroadcast() {
        if (!notificationsBroadcastForm || notificationsState.broadcasting) {
            return Promise.resolve(null);
        }

        var payload = {
            targetKey: (notificationsTargetSelect && notificationsTargetSelect.value.trim()) || "",
            title: (notificationsTitleInput && notificationsTitleInput.value.trim()) || "",
            body: (notificationsBodyInput && notificationsBodyInput.value.trim()) || "",
            ctaLabel: (notificationsCtaLabelInput && notificationsCtaLabelInput.value.trim()) || "",
            ctaHref: (notificationsCtaHrefInput && notificationsCtaHrefInput.value.trim()) || "",
            scheduleAt: (notificationsScheduleInput && notificationsScheduleInput.value.trim()) || "",
            sendSms: notificationsSendSmsInput && notificationsSendSmsInput.checked ? "1" : "0"
        };
        var validationError = notificationsValidateBroadcastPayload(payload);
        if (validationError) {
            notificationsSetComposeOpen(true);
            setFeedback(notificationsManagerFeedback, validationError, "error");
            return Promise.resolve(null);
        }

        notificationsState.broadcasting = true;
        notificationsSetComposeOpen(true);
        setFeedback(notificationsManagerFeedback, payload.scheduleAt ? "در حال زمان‌بندی اعلان..." : "در حال ارسال اعلان...", "", true);
        renderNotificationsUi();
        return notificationsPost("broadcast", payload).then(function (response) {
            notificationsState.broadcasting = false;
            if (consumeUnauthorized(response, "نشست شما برای ارسال اعلان منقضی شده است.")) {
                notificationsResetState();
                renderNotificationsUi();
                return null;
            }
            if (!response || response.success !== true) {
                setFeedback(notificationsManagerFeedback, (response && response.error) || "ارسال اعلان انجام نشد.", "error");
                renderNotificationsUi();
                return null;
            }

            notificationsApplyResponseMeta(response);
            if (response.notification) {
                notificationsState.items = [response.notification].concat(notificationsState.items.filter(function (item) {
                    return String(item && item.id || "") !== String(response.notification.id || "");
                })).slice(0, 60);
                notificationsState.activeFilter = response.notification.scheduled ? "scheduled" : "all";
            }
            notificationsSyncPreview();
            if (notificationsTitleInput) notificationsTitleInput.value = "";
            if (notificationsBodyInput) notificationsBodyInput.value = "";
            if (notificationsCtaLabelInput) notificationsCtaLabelInput.value = "";
            if (notificationsCtaHrefInput) notificationsCtaHrefInput.value = "";
            if (notificationsScheduleInput) notificationsScheduleInput.value = "";
            if (notificationsSendSmsInput) notificationsSendSmsInput.checked = false;
            notificationsSetComposeOpen(false);
            setFeedback(notificationsManagerFeedback, "", "");
            setInlineFeedback(notificationsFeedback, response.message || "اعلان ثبت شد.", "success");
            renderNotificationsUi();
            notificationsDispatchSummary(notificationsState.summary);
            return response;
        }).catch(function () {
            notificationsState.broadcasting = false;
            notificationsSetComposeOpen(true);
            setFeedback(notificationsManagerFeedback, "اتصال برای ارسال اعلان برقرار نشد.", "error");
            renderNotificationsUi();
            return null;
        });
    }

    function handleNotificationCtaNavigation(event, notificationId, href) {
        var targetHref = String(href || "").trim();
        if (!targetHref) {
            return;
        }
        if (event && event.preventDefault) {
            event.preventDefault();
        }
        markNotificationsRead([notificationId]).finally(function () {
            window.location.href = targetHref;
        });
    }

    function renderIdentity(user) {
        var roleLabel = user.roleLabel || "دانشجو";
        var sessionLabel = user.isOwner ? "دسترسی مالک فعال" : (user.canModerateChat ? "دسترسی نماینده فعال" : "نشست فعال");
        var profile = user.profile && typeof user.profile === "object" ? user.profile : {};
        var aboutText = profileAbout(profile);
        var focusText = profile.focusArea || "";
        var contactText = profile.contactHandle || "";
        var disNumber = userDisNumber(user);

        $("account-role-eyebrow").textContent = roleLabel;
        $("account-name").textContent = user.name || "دانشجو";
        $("account-student-number").textContent = "شماره دانشجویی: " + (user.studentNumber || "-");
        $("account-role-badge").textContent = roleLabel;
        $("account-session-badge").textContent = sessionLabel;

        $("profile-name").value = user.name || "";
        $("profile-student-number").value = user.studentNumber || "";
        $("profile-about").value = aboutText;
        $("profile-focus-area").value = focusText;
        $("profile-contact-handle").value = contactText;
        profileDraftAvatarUrl = profile.avatarUrl || "";
        updateIdentityAvatars(user.name || "");
        setInlineFeedback(profileAvatarFeedback, "", "");

        if (accountInfoRole) {
            accountInfoRole.textContent = roleLabel;
        }
        if (accountInfoSession) {
            accountInfoSession.textContent = sessionLabel;
        }
        if (accountInfoDisNumber) {
            accountInfoDisNumber.textContent = disNumber || "—";
        }

        if (accountRowProfileMeta) {
            accountRowProfileMeta.textContent = aboutText || focusText || contactText || "ویرایش آواتار، بیو و راه ارتباطی";
        }
        if (accountRowInfoMeta) {
            accountRowInfoMeta.textContent = [user.studentNumber || "-", roleLabel, disNumber ? ("DIS " + disNumber) : ""].filter(Boolean).join(" • ");
        }

        var phone = parsedPhone(user);
        var phoneLabel = ltrMaskedPhone(phone.numberMasked, "شماره ثبت‌شده");
        if (accountRowPhoneMeta) {
            if (!phone.hasNumber) {
                accountRowPhoneMeta.textContent = "هنوز شماره‌ای ثبت نشده است.";
            } else if (!phone.verified) {
                accountRowPhoneMeta.textContent = "شماره " + phoneLabel + " ثبت شده ولی هنوز تایید نشده است.";
            } else if (phone.otpLoginEnabled) {
                accountRowPhoneMeta.textContent = "ورود با کد تایید فعال است (" + phoneLabel + ").";
            } else {
                accountRowPhoneMeta.textContent = "شماره " + phoneLabel + " تایید شده است ولی ورود پیامکی غیرفعال است.";
            }
        }
        renderPhoneSecurityState(user);

        if (accountRotation) {
            var rotation = user.rotation && typeof user.rotation === "object" ? user.rotation : null;
            var rotationSummary = rotation && rotation.assigned ? String(rotation.summary || "").trim() : "";
            if (rotationSummary) {
                accountRotation.hidden = false;
                accountRotation.textContent = "روتیشن/گروه: " + rotationSummary;
                if (accountInfoRotation) {
                    accountInfoRotation.textContent = rotationSummary;
                }
            } else {
                accountRotation.hidden = true;
                accountRotation.textContent = "";
                if (accountInfoRotation) {
                    accountInfoRotation.textContent = "—";
                }
            }
        } else if (accountInfoRotation) {
            accountInfoRotation.textContent = "—";
        }

        if (accountDisNumber) {
            if (disNumber) {
                accountDisNumber.hidden = false;
                accountDisNumber.textContent = "شماره DIS: " + disNumber;
            } else {
                accountDisNumber.hidden = true;
                accountDisNumber.textContent = "";
            }
        }
    }

    function renderOwnerCohortPicker() {
        var cohorts = ownerVisibleCohorts();
        var active = ownerActiveCohortKey();
        var activeRecord = ownerActiveCohortRecord();

        if (ownerCohortSelect) {
            ownerCohortSelect.innerHTML = "";
            cohorts.forEach(function (cohort) {
                var option = document.createElement("option");
                option.value = String(cohort.key || "");
                option.textContent = String(cohort.title || cohort.shortTitle || cohort.key || "ورودی");
                option.selected = option.value === active;
                ownerCohortSelect.appendChild(option);
            });
            ownerCohortSelect.disabled = ownerState.loading || cohorts.length <= 1;
        }

        if (ownerCohortGrid) {
            ownerCohortGrid.innerHTML = "";
            if (!cohorts.length) {
                ownerCohortGrid.innerHTML = '<div class="owner-empty">ورودی قابل مدیریتی پیدا نشد.</div>';
            } else {
                cohorts.forEach(function (cohort) {
                    var card = document.createElement("button");
                    card.type = "button";
                    card.className = "owner-cohort-card" + (String(cohort.key || "") === active ? " is-active" : "");
                    card.dataset.cohortKey = String(cohort.key || "");
                    card.innerHTML = [
                        "<strong>" + String(cohort.shortTitle || cohort.title || cohort.key || "ورودی") + "</strong>",
                        "<span>" + String(cohort.description || cohort.title || "") + "</span>",
                        "<small>" + [
                            "کاربر " + Math.max(0, Number(cohort.counts && cohort.counts.totalUsers || 0)).toLocaleString("fa-IR"),
                            "نماینده " + Math.max(0, Number(cohort.counts && cohort.counts.representatives || 0)).toLocaleString("fa-IR")
                        ].join(" • ") + "</small>"
                    ].join("");
                    ownerCohortGrid.appendChild(card);
                });
            }
        }

        if (ownerCohortSummary) {
            var activeServices = activeRecord && activeRecord.services && typeof activeRecord.services === "object"
                ? activeRecord.services
                : {};
            var activeServiceLabels = [
                activeServices.notes ? "منابع" : "",
                activeServices.forms ? "فرم" : "",
                activeServices.grades ? "نمره" : "",
                activeServices.navid ? "نوید" : "",
                activeServices.buy ? "خرید" : ""
            ].filter(Boolean);
            ownerCohortSummary.innerHTML = activeRecord ? [
                summaryCard("فعال", String(activeRecord.shortTitle || activeRecord.title || "—"), String(activeRecord.title || ""), "ok"),
                summaryCard("نوع", activeRecord.productType === "prosthesis" ? "پروتز" : (activeRecord.productType === "site-users" ? "کاربران عمومی" : "دندانپزشکی"), "محیط ایزوله همین ورودی"),
                summaryCard("دسترسی", activeRecord.allowRepresentativeManagement ? "نماینده فعال" : "فقط مالک", activeRecord.allowRepresentativeManagement ? "ابزارهای اصلی برای نماینده همین ورودی باز است" : "مدیریت فقط در سطح مالک انجام می‌شود", activeRecord.allowRepresentativeManagement ? "ok" : "warn"),
                summaryCard("کارت‌های خانه", activeServiceLabels.length ? activeServiceLabels.join("، ") : "بدون کارت", activeServiceLabels.length ? "کارت‌های خانه این ورودی از همین تنظیم‌ها ساخته می‌شود" : "برای این ورودی فعلاً کارت خانه فعالی تعریف نشده است")
            ].join("") : "";
        }
    }

    function renderOwnerSummary(users) {
        var visibleUsers = ownerUsersInActiveCohort(users);
        var totalUsers = visibleUsers.length;
        var representatives = visibleUsers.filter(function (user) {
            return user.role === "representative" || user.role === "prosthesis_representative";
        }).length;
        var withGrades = visibleUsers.filter(function (user) {
            return user.hasGrades;
        }).length;
        var withPhone = visibleUsers.filter(function (user) {
            return !!user.hasPhone;
        }).length;
        var readyProfiles = visibleUsers.filter(function (user) {
            return !!user.hasNationalCode && !!user.hasDirectoryPhone;
        }).length;

        ownerSummary.innerHTML = [
            summaryCard("کاربر", totalUsers.toLocaleString("fa-IR"), "کل حساب‌های همین ورودی"),
            summaryCard("نماینده", representatives.toLocaleString("fa-IR"), "دسترسی مدیریتی فعال در این ورودی"),
            summaryCard("ورود پیامکی", withPhone.toLocaleString("fa-IR"), "شماره تاییدشده برای login"),
            summaryCard("پروفایل کامل", readyProfiles.toLocaleString("fa-IR"), "دارای کدملی و تلفن تماس"),
            summaryCard("کارنامه", withGrades.toLocaleString("fa-IR"), "رکورد نمره برای حداقل یک درس", withGrades > 0 ? "ok" : "warn")
        ].join("");

        if (accountRowOwnerMeta) {
            accountRowOwnerMeta.textContent = [
                "کاربر " + totalUsers.toLocaleString("fa-IR"),
                "نماینده " + representatives.toLocaleString("fa-IR"),
                "شماره " + withPhone.toLocaleString("fa-IR"),
                "پروفایل کامل " + readyProfiles.toLocaleString("fa-IR"),
                "درس " + ownerState.gradeCourses.length.toLocaleString("fa-IR")
            ].join(" \u2022 ");
        }
    }

    function summaryCard(label, value, meta, tone) {
        var toneClass = String(tone || "").trim();
        return [
            '<article class="owner-summary-card' + (toneClass ? (" owner-summary-card--" + toneClass) : "") + '">',
            '  <span>' + label + "</span>",
            '  <strong>' + value + "</strong>",
            '  <small>' + meta + "</small>",
            "</article>"
        ].join("");
    }

    function ownerStatsMetric(value) {
        return Math.max(0, Number(value || 0)).toLocaleString("fa-IR");
    }

    function syncOwnerStatsShortcut() {
        if (accountOwnerAppearanceShortcut) {
            accountOwnerAppearanceShortcut.hidden = !hasOwnerAccess();
        }
        if (accountOwnerStatsShortcut) {
            accountOwnerStatsShortcut.hidden = !hasOwnerAccess();
        }
        if (!accountRowOwnerStatsMeta) {
            return;
        }
        if (!hasOwnerAccess()) {
            accountRowOwnerStatsMeta.textContent = "بازدیدها، ورودها، دانلودها و نمودارهای مدیریتی کل سایت";
            return;
        }
        var totals = ownerAnalyticsState.dashboard && ownerAnalyticsState.dashboard.totals ? ownerAnalyticsState.dashboard.totals : null;
        if (totals) {
            accountRowOwnerStatsMeta.textContent = [
                "بازدید ۳۰ روز " + ownerStatsMetric(totals.pageViews30d),
                "ورود ۳۰ روز " + ownerStatsMetric(totals.logins30d),
                "کاربر " + ownerStatsMetric(totals.totalUsers)
            ].join(" • ");
            return;
        }
        if (ownerAnalyticsState.loading) {
            accountRowOwnerStatsMeta.textContent = "در حال آماده‌سازی snapshot آمار سایت...";
            return;
        }
        accountRowOwnerStatsMeta.textContent = "بازدیدها، ورودها، دانلودها و نمودارهای مدیریتی کل سایت";
    }

    function ownerStatsEmptyMarkup(text) {
        return '<div class="owner-stats-empty">' + escapeHtml(text || "داده‌ای برای نمایش وجود ندارد.") + "</div>";
    }

    function ownerStatsShortPath(value) {
        var text = String(value || "").trim();
        if (text.length <= 54) {
            return text;
        }
        return text.slice(0, 26) + "…" + text.slice(-24);
    }

    function renderOwnerStatsOverview(dashboard) {
        if (!ownerStatsOverview) {
            return;
        }
        if (!dashboard || !dashboard.totals) {
            ownerStatsOverview.innerHTML = ownerStatsEmptyMarkup("هنوز آماری برای نمایش ثبت نشده است.");
            return;
        }

        var totals = dashboard.totals || {};
        var seg = dashboard.segments || {};
        var human = seg.human || {};
        var owner = seg.owner || {};
        var bot = seg.bot || {};
        ownerStatsOverview.innerHTML = [
            summaryCard("کل کاربران", ownerStatsMetric(totals.totalUsers), "تعداد فعلی حساب‌های ثبت‌شده در کل سایت", "ok"),
            summaryCard("بازدید کاربران واقعی", ownerStatsMetric(human.pageViews || 0), "بدون احتساب مالک و هوش مصنوعی/ربات‌ها", (human.pageViews || 0) > 0 ? "ok" : ""),
            summaryCard("بازدید مالک", ownerStatsMetric(owner.pageViews || 0), "بازدیدهای حساب مالک (شامل کار هوش مصنوعی با حساب مالک)"),
            summaryCard("بازدید هوش مصنوعی/ربات", ownerStatsMetric(bot.pageViews || 0), "ربات‌ها، خزنده‌ها و ابزارهای هوش مصنوعی", "warn"),
            summaryCard("ورود کاربران واقعی", ownerStatsMetric(human.logins || 0), "ورودهای دانشجویان واقعی (بدون مالک/ربات)", (human.logins || 0) > 0 ? "ok" : ""),
            summaryCard("ورود مالک", ownerStatsMetric(owner.logins || 0), "ورودهای ثبت‌شده با حساب مالک"),
            summaryCard("دانلود کاربران واقعی", ownerStatsMetric(human.downloads || 0), "دانلودهای دانشجویان واقعی (بدون مالک/ربات)"),
            summaryCard("بازدید امروز (کل)", ownerStatsMetric(totals.pageViewsToday), "همه بازدیدها از ابتدای امروز شامل مالک/ربات", totals.pageViewsToday > 0 ? "ok" : ""),
            summaryCard("بازدید ۳۰ روز (کل)", ownerStatsMetric(totals.pageViews30d), "مجموع همه بازدیدهای ۳۰ روز اخیر شامل مالک/ربات"),
            summaryCard("ورود ۳۰ روز (کل)", ownerStatsMetric(totals.logins30d), "مجموع همه loginهای موفق در ۳۰ روز اخیر"),
            summaryCard("دانلود ۳۰ روز (کل)", ownerStatsMetric(totals.downloads30d), "همه کلیک‌های دانلود ۳۰ روز اخیر", totals.downloads30d > 0 ? "ok" : ""),
            summaryCard("بازدیدکننده یکتا", ownerStatsMetric(totals.uniqueVisitors30d), "تعداد visitor یکتای ۳۰ روز اخیر"),
            summaryCard("فایل‌سنتر / HTML", ownerStatsMetric((totals.contentToolsDownloads || 0) + (totals.htmlPageViews || 0)), "دانلودهای فایل‌سنتر + بازدید صفحه‌های HTML uploader", "warn")
        ].join("");
    }

    function renderOwnerStatsChart(node, series, fallbackText) {
        if (!node) {
            return;
        }
        var points = Array.isArray(series) ? series : [];
        if (!points.length) {
            node.innerHTML = ownerStatsEmptyMarkup(fallbackText || "آماری برای این بازه وجود ندارد.");
            return;
        }

        var maxValue = 0;
        var peakIndex = 0;
        var lastActiveIndex = -1;
        points.forEach(function (item) {
            var value = Math.max(0, Number(item && item.value || 0));
            if (value >= maxValue) {
                maxValue = value;
                peakIndex = points.indexOf(item);
            }
            if (value > 0) {
                lastActiveIndex = points.indexOf(item);
            }
        });
        if (!maxValue) {
            node.innerHTML = ownerStatsEmptyMarkup(fallbackText || "در این بازه هنوز مقداری ثبت نشده است.");
            return;
        }

        var lastIndex = Math.max(0, points.length - 1);
        var labelStep = points.length <= 7 ? 1 : (points.length <= 10 ? 2 : 3);

        node.innerHTML = [
            '<div class="owner-stats-chart__bars">',
            points.map(function (item, index) {
                var value = Math.max(0, Number(item && item.value || 0));
                var ratio = value > 0 && maxValue > 0 ? Math.max(10, Math.round((value / maxValue) * 100)) : 0;
                var isFocus = index === peakIndex || (lastActiveIndex >= 0 && index === lastActiveIndex);
                var showValue = value > 0 && (isFocus || value / maxValue >= 0.38);
                var showTick = index === 0 || index === lastIndex || index === peakIndex || index % labelStep === 0;
                var rawLabel = String(item && item.label || "").trim();
                var tickLabel = rawLabel;
                if (rawLabel.indexOf("/") >= 0) {
                    var segments = rawLabel.split("/");
                    tickLabel = String(segments[segments.length - 1] || rawLabel).trim();
                }
                return [
                    '<div class="owner-stats-chart__item' + (isFocus ? " owner-stats-chart__item--focus" : "") + (value <= 0 ? " owner-stats-chart__item--empty" : "") + '" title="' + escapeHtml(String(item.fullLabel || item.label || "")) + " • " + escapeHtml(ownerStatsMetric(value)) + '">',
                    '  <span class="owner-stats-chart__value' + (showValue ? "" : " owner-stats-chart__value--ghost") + '">' + (showValue ? escapeHtml(ownerStatsMetric(value)) : "&nbsp;") + '</span>',
                    '  <span class="owner-stats-chart__bar"><i style="height:' + ratio + '%"></i></span>',
                    '  <small class="owner-stats-chart__tick' + (showTick ? " is-visible" : "") + '">' + escapeHtml(tickLabel) + '</small>',
                    "</div>"
                ].join("");
            }).join(""),
            "</div>"
        ].join("");
    }

    function renderOwnerStatsBars(node, items, valueKey, emptyText) {
        if (!node) {
            return;
        }
        var rows = Array.isArray(items) ? items.filter(function (item) {
            return item && Number(item[valueKey] || 0) > 0;
        }) : [];
        if (!rows.length) {
            node.innerHTML = ownerStatsEmptyMarkup(emptyText || "داده‌ای برای این بخش وجود ندارد.");
            return;
        }

        var maxValue = 0;
        rows.forEach(function (item) {
            maxValue = Math.max(maxValue, Math.max(0, Number(item[valueKey] || 0)));
        });

        node.innerHTML = rows.map(function (item) {
            var value = Math.max(0, Number(item[valueKey] || 0));
            var ratio = maxValue > 0 ? Math.max(6, Math.round((value / maxValue) * 100)) : 0;
            return [
                '<div class="owner-stats-bar-row">',
                '  <div class="owner-stats-bar-row__top">',
                '    <strong>' + escapeHtml(String(item.label || item.title || item.key || "بدون عنوان")) + '</strong>',
                '    <span>' + escapeHtml(ownerStatsMetric(value)) + '</span>',
                "  </div>",
                '  <div class="owner-stats-bar-row__track"><i style="width:' + ratio + '%"></i></div>',
                '  <small>' + escapeHtml(String(item.meta || "")) + '</small>',
                "</div>"
            ].join("");
        }).join("");
    }

    function renderOwnerStatsSimpleTable(node, columns, rows, emptyText) {
        if (!node) {
            return;
        }
        if (!Array.isArray(rows) || !rows.length) {
            node.innerHTML = ownerStatsEmptyMarkup(emptyText || "جدولی برای نمایش وجود ندارد.");
            return;
        }

        var head = [
            '<div class="owner-stats-table__row owner-stats-table__row--head" style="--owner-stats-columns:' + columns.length + ';">',
            columns.map(function (column) {
                return '<span>' + escapeHtml(column.label) + "</span>";
            }).join(""),
            "</div>"
        ].join("");

        var body = rows.map(function (row) {
            return [
                '<div class="owner-stats-table__row" style="--owner-stats-columns:' + columns.length + ';">',
                columns.map(function (column) {
                    var rendered = typeof column.render === "function" ? column.render(row) : row[column.key];
                    return '<span>' + rendered + "</span>";
                }).join(""),
                "</div>"
            ].join("");
        }).join("");

        node.innerHTML = head + body;
    }

    function renderOwnerStatsTables(dashboard) {
        var totals = dashboard && dashboard.totals ? dashboard.totals : {};
        var examStats = dashboard && dashboard.exams ? dashboard.exams : null;
        renderOwnerStatsBars(ownerStatsFamilies, (dashboard && dashboard.families || []).map(function (item) {
            return {
                label: item.label || item.key || "",
                views: item.views || 0,
                meta: "دانلود " + ownerStatsMetric(item.downloads || 0)
            };
        }), "views", "هنوز خانواده مسیر پربازدیدی ثبت نشده است.");

        renderOwnerStatsBars(ownerStatsMethods, (dashboard && dashboard.loginMethods || []).map(function (item) {
            return {
                label: item.label || item.key || "",
                count: item.count || 0,
                meta: "سهم از کل ورودها"
            };
        }), "count", "هنوز login methodای ثبت نشده است.");

        renderOwnerStatsSimpleTable(ownerStatsPages, [
            {
                label: "صفحه",
                render: function (row) {
                    var title = String(row.title || "").trim();
                    var path = ownerStatsShortPath(row.path || "");
                    return '<strong>' + escapeHtml(title || path || "بدون عنوان") + '</strong><small>' + escapeHtml(path) + "</small>";
                }
            },
            {
                label: "بخش",
                render: function (row) {
                    return escapeHtml(String(row.familyLabel || row.family || ""));
                }
            },
            {
                label: "بازدید",
                render: function (row) {
                    return escapeHtml(ownerStatsMetric(row.views || 0));
                }
            },
            {
                label: "آخرین بازدید",
                render: function (row) {
                    return escapeHtml(formatJalaliDateTime(row.lastViewedAt, "—"));
                }
            }
        ], dashboard && dashboard.topPages || [], "هنوز صفحه پربازدیدی ثبت نشده است.");

        renderOwnerStatsSimpleTable(ownerStatsDownloads, [
            {
                label: "منبع / فایل",
                render: function (row) {
                    var label = String(row.label || row.href || "بدون عنوان");
                    var href = String(row.href || "").trim();
                    if (href) {
                        return '<strong>' + escapeHtml(label) + '</strong><small dir="ltr">' + escapeHtml(ownerStatsShortPath(href)) + "</small>";
                    }
                    return '<strong>' + escapeHtml(label) + "</strong>";
                }
            },
            {
                label: "مبدا",
                render: function (row) {
                    return escapeHtml(String(row.sourceLabel || row.sourceFamily || ""));
                }
            },
            {
                label: "تعداد",
                render: function (row) {
                    return escapeHtml(ownerStatsMetric(row.count || 0));
                }
            },
            {
                label: "آخرین استفاده",
                render: function (row) {
                    return escapeHtml(formatJalaliDateTime(row.lastAt, "—"));
                }
            }
        ], dashboard && dashboard.topDownloads || [], "هنوز دانلود/منبعی برای آمار ثبت نشده است.");

        renderOwnerStatsSimpleTable(ownerStatsCohorts, [
            {
                label: "ورودی",
                render: function (row) {
                    return '<strong>' + escapeHtml(String(row.shortTitle || row.title || row.key || "")) + '</strong><small>' + escapeHtml(String(row.title || "")) + "</small>";
                }
            },
            {
                label: "کاربر",
                render: function (row) {
                    return escapeHtml(ownerStatsMetric(row.totalUsers || 0));
                }
            },
            {
                label: "نماینده",
                render: function (row) {
                    return escapeHtml(ownerStatsMetric(row.representatives || 0));
                }
            },
            {
                label: "بازدید ۳۰ روز",
                render: function (row) {
                    return escapeHtml(ownerStatsMetric(row.pageViews30d || 0));
                }
            },
            {
                label: "ورود ۳۰ روز",
                render: function (row) {
                    return escapeHtml(ownerStatsMetric(row.logins30d || 0));
                }
            },
            {
                label: "بخش‌های فعال",
                render: function (row) {
                    var families = Array.isArray(row.topFamilies) ? row.topFamilies : [];
                    if (!families.length) {
                        return "—";
                    }
                    return families.slice(0, 3).map(function (family) {
                        return escapeHtml(String(family.label || family.key || ""));
                    }).join("، ");
                }
            }
        ], dashboard && dashboard.cohorts || [], "هنوز داده cohort-driven برای نمایش وجود ندارد.");

        renderOwnerStatsSimpleTable(ownerStatsRecentLogins, [
            {
                label: "کاربر",
                render: function (row) {
                    var name = String(row.name || "").trim();
                    var studentNumber = String(row.studentNumber || "").trim();
                    return '<strong>' + escapeHtml(name || "بدون نام") + '</strong><small dir="ltr">' + escapeHtml(studentNumber) + "</small>";
                }
            },
            {
                label: "ورودی",
                render: function (row) {
                    return escapeHtml(String(row.cohortLabel || row.cohortKey || "—"));
                }
            },
            {
                label: "نقش",
                render: function (row) {
                    return escapeHtml(String(row.roleLabel || row.role || ""));
                }
            },
            {
                label: "روش ورود",
                render: function (row) {
                    return escapeHtml(String(row.methodLabel || row.method || ""));
                }
            },
            {
                label: "زمان ورود",
                render: function (row) {
                    return escapeHtml(formatJalaliDateTime(row.at, "—"));
                }
            }
        ], dashboard && dashboard.recentLogins || [], "هنوز ورودی برای نمایش ثبت نشده است.");

        if (ownerStatsExams) {
            if (!examStats) {
                ownerStatsExams.innerHTML = ownerStatsEmptyMarkup("\u0647\u0646\u0648\u0632 \u0622\u0645\u0627\u0631\u06cc \u0627\u0632 \u0645\u0627\u0698\u0648\u0644 \u0622\u0632\u0645\u0648\u0646 \u062f\u0631 \u062f\u0633\u062a\u0631\u0633 \u0646\u06cc\u0633\u062a.");
            } else {
                var averagePercent = examStats.averagePercent === null || examStats.averagePercent === undefined
                    ? "\u2014"
                    : (ownerStatsMetric(examStats.averagePercent) + "%");
                ownerStatsExams.innerHTML = [
                    summaryCard("\u062e\u0631\u06cc\u062f\u0627\u0631 \u0622\u0632\u0645\u0648\u0646", ownerStatsMetric(examStats.purchaserCount || 0), ownerStatsMetric(examStats.paidOrderCount || 0) + " \u067e\u0631\u062f\u0627\u062e\u062a \u0645\u0648\u0641\u0642 \u062f\u0631 \u0628\u062e\u0634 \u0622\u0632\u0645\u0648\u0646", (examStats.purchaserCount || 0) > 0 ? "ok" : ""),
                    summaryCard("\u0622\u0632\u0645\u0648\u0646 \u0634\u0631\u0648\u0639\u200c\u0634\u062f\u0647", ownerStatsMetric(examStats.startedCount || 0), "\u0634\u0631\u0648\u0639\u200c\u0647\u0627\u06cc \u062b\u0628\u062a\u200c\u0634\u062f\u0647 \u062f\u0631 examRecords \u0647\u0645\u0647 \u062c\u0644\u0633\u0647\u200c\u0647\u0627", (examStats.startedCount || 0) > 0 ? "ok" : ""),
                    summaryCard("\u06a9\u0644 \u0633\u0648\u0627\u0644\u0627\u062a \u0622\u0632\u0645\u0648\u0646", ownerStatsMetric(examStats.questionCount || 0), ownerStatsMetric(examStats.examCount || 0) + " \u0622\u0632\u0645\u0648\u0646 \u0627\u0632 " + ownerStatsMetric(examStats.courseCount || 0) + " \u062f\u0631\u0633"),
                    summaryCard("\u0645\u0628\u0644\u063a \u067e\u0631\u062f\u0627\u062e\u062a\u200c\u0634\u062f\u0647", ownerStatsMetric(examStats.receivedAmount || 0) + " \u0631\u06cc\u0627\u0644", "\u062c\u0645\u0639 \u067e\u0631\u062f\u0627\u062e\u062a\u200c\u0647\u0627\u06cc \u0645\u0648\u0641\u0642 \u0628\u0631\u0627\u06cc \u0622\u0632\u0645\u0648\u0646\u200c\u0647\u0627", (examStats.receivedAmount || 0) > 0 ? "ok" : ""),
                    summaryCard("\u0628\u0627\u0632\u062f\u06cc\u062f \u0635\u0641\u062d\u0647 \u0622\u0632\u0645\u0648\u0646", ownerStatsMetric(examStats.pageViews || 0), ownerStatsMetric(examStats.pageViews30d || 0) + " \u0628\u0627\u0632\u062f\u06cc\u062f \u062f\u0631 \u06f3\u06f0 \u0631\u0648\u0632 \u0627\u062e\u06cc\u0631", (examStats.pageViews || 0) > 0 ? "ok" : ""),
                    summaryCard("\u0645\u06cc\u0627\u0646\u06af\u06cc\u0646 \u062f\u0631\u0635\u062f", averagePercent, ownerStatsMetric(examStats.submittedCount || 0) + " \u06a9\u0627\u0631\u0646\u0627\u0645\u0647 \u0633\u0646\u062c\u0634\u06cc \u062b\u0628\u062a\u200c\u0634\u062f\u0647", examStats.averagePercent !== null && examStats.averagePercent !== undefined ? "warn" : "")
                ].join("");
            }
        }

        if (ownerStatsReferences) {
            ownerStatsReferences.innerHTML = [
                summaryCard("دانلود فایل‌سنتر", ownerStatsMetric(totals.contentToolsDownloads || 0), "شمارنده backend ماژول فایل‌سنتر", totals.contentToolsDownloads > 0 ? "ok" : ""),
                summaryCard("بازدید paste", ownerStatsMetric((totals.pasteViews || 0) + (totals.pasteRawViews || 0)), "view و raw-view در ماژول paste"),
                summaryCard("بازدید HTML", ownerStatsMetric(totals.htmlPageViews || 0), "viewCount صفحه‌های public HTML uploader", totals.htmlPageViews > 0 ? "warn" : ""),
                summaryCard("دانلود ثبت‌شده جدید", ownerStatsMetric(totals.downloads || 0), "downloadهایی که از tracker جدید جمع شده‌اند", totals.downloads > 0 ? "ok" : "")
            ].join("");
        }

        if (ownerStatsRetention) {
            var retention = dashboard && dashboard.retention ? dashboard.retention : {};
            ownerStatsRetention.innerHTML = [
                summaryCard("نرخ بازگشت", ownerStatsMetric(retention.returnRatePercent || 0) + "%", "کاربرانی که در ۳۰ روز بیش از یک روز وارد شده‌اند", (retention.returnRatePercent || 0) > 0 ? "ok" : ""),
                summaryCard("کاربر بازگشتی", ownerStatsMetric(retention.returning || 0), "ورود در حداقل ۲ روز مجزا"),
                summaryCard("کاربر یک‌باره", ownerStatsMetric(retention.oneTime || 0), "فقط در یک روز وارد شده‌اند", (retention.oneTime || 0) > 0 ? "warn" : ""),
                summaryCard("کاربر یکتا", ownerStatsMetric(retention.uniqueUsers || 0), "کل کاربران واردشده در ۳۰ روز")
            ].join("");
        }

        if (ownerStatsFunnel) {
            var funnel = dashboard && dashboard.funnel ? dashboard.funnel : {};
            ownerStatsFunnel.innerHTML = [
                summaryCard("بازدید صفحه خرید", ownerStatsMetric(funnel.buyViews || 0), "بازدید مسیرهای خرید در ۳۰ روز"),
                summaryCard("سفارش ثبت‌شده", ownerStatsMetric(funnel.ordersCreated || 0), "سفارش‌های ساخته‌شده در ۳۰ روز"),
                summaryCard("پرداخت موفق", ownerStatsMetric(funnel.ordersPaid || 0), ownerStatsMetric(funnel.ordersPending || 0) + " سفارش در انتظار پرداخت", (funnel.ordersPaid || 0) > 0 ? "ok" : ""),
                summaryCard("نرخ تبدیل", ownerStatsMetric(funnel.conversionPercent || 0) + "%", "سهم پرداخت موفق از کل سفارش‌ها", (funnel.conversionPercent || 0) >= 50 ? "ok" : "warn")
            ].join("");
        }

        var errorLog = dashboard && dashboard.errorLog ? dashboard.errorLog : null;
        if (ownerStatsErrorsMeta) {
            ownerStatsErrorsMeta.textContent = errorLog && errorLog.available
                ? ("۲۴ ساعت: " + ownerStatsMetric(errorLog.last24h || 0) + " • ۷ روز: " + ownerStatsMetric(errorLog.last7d || 0) + " • کل ثبت‌شده: " + ownerStatsMetric(errorLog.total || 0))
                : "هنوز خطایی در لاگ سرور ثبت نشده است.";
        }
        if (ownerStatsErrors) {
            renderOwnerStatsSimpleTable(ownerStatsErrors, [
                {
                    label: "زمان",
                    render: function (row) {
                        return escapeHtml(formatJalaliDateTime(row.at, "—"));
                    }
                },
                {
                    label: "نوع",
                    render: function (row) {
                        var type = String(row.type || "");
                        var status = row.status ? ('<small>' + escapeHtml(ownerStatsMetric(row.status)) + "</small>") : "";
                        return escapeHtml(type) + status;
                    }
                },
                {
                    label: "پیام",
                    render: function (row) {
                        var message = String(row.message || "");
                        if (message.length > 120) {
                            message = message.slice(0, 120) + "…";
                        }
                        var location = row.file
                            ? ('<small dir="ltr">' + escapeHtml(ownerStatsShortPath(row.file)) + (row.line ? (":" + row.line) : "") + "</small>")
                            : "";
                        return '<strong>' + escapeHtml(message || "بدون پیام") + "</strong>" + location;
                    }
                },
                {
                    label: "مسیر",
                    render: function (row) {
                        return escapeHtml(String(row.action || ownerStatsShortPath(row.uri || "") || "—"));
                    }
                }
            ], errorLog && errorLog.recent || [], "هنوز خطایی در لاگ سرور ثبت نشده است.");
        }
    }

    function renderOwnerAnalytics() {
        var dashboard = ownerAnalyticsState.dashboard;
        syncOwnerStatsShortcut();
        if (ownerStatsMeta) {
            ownerStatsMeta.textContent = dashboard && dashboard.generatedAt
                ? ("آخرین به‌روزرسانی: " + formatJalaliDateTime(dashboard.generatedAt, "—", true))
                : "آخرین snapshot هنوز بارگذاری نشده است.";
        }

        renderOwnerStatsOverview(dashboard);
        renderOwnerStatsChart(ownerStatsVisitsChart, dashboard && dashboard.charts ? dashboard.charts.pageViews14d : [], "هنوز بازدید روزانه‌ای ثبت نشده است.");
        renderOwnerStatsChart(ownerStatsLoginsChart, dashboard && dashboard.charts ? dashboard.charts.logins14d : [], "هنوز ورود روزانه‌ای ثبت نشده است.");
        renderOwnerStatsChart(ownerStatsDownloadsChart, dashboard && dashboard.charts ? dashboard.charts.downloads14d : [], "هنوز دانلود روزانه‌ای ثبت نشده است.");
        renderOwnerStatsTables(dashboard);

        if (ownerStatsRefreshButton) {
            ownerStatsRefreshButton.disabled = ownerAnalyticsState.loading;
            ownerStatsRefreshButton.textContent = ownerAnalyticsState.loading ? "در حال به‌روزرسانی..." : "به‌روزرسانی";
        }
    }

    function formatBytes(bytes) {
        var size = Math.max(0, Math.floor(toNumber(bytes, 0)));
        if (!size) return "0 B";
        if (size < 1024) return size + " B";
        if (size < (1024 * 1024)) return (size / 1024).toFixed(1) + " KB";
        if (size < (1024 * 1024 * 1024)) return (size / (1024 * 1024)).toFixed(1) + " MB";
        return (size / (1024 * 1024 * 1024)).toFixed(1) + " GB";
    }

    function ownerSmsFeedbackMessage(text, kind, loading) {
        setInlineFeedback(ownerSmsFeedback, text, kind, loading);
    }

    function ownerMediaFeedbackMessage(text, kind, loading) {
        setInlineFeedback(ownerMediaFeedback, text, kind, loading);
    }

    function renderOwnerSmsStatus(status) {
        if (!ownerSmsStatus) return;
        var s = status && typeof status === "object" ? status : {};
        var ready = !!(s.enabled && s.apiKeyConfigured && s.patternConfigured && s.senderLineConfigured);
        var missing = [];
        if (!s.enabled) missing.push("فعال‌سازی سرویس");
        if (!s.apiKeyConfigured) missing.push("API Key");
        if (!s.patternConfigured) missing.push("Pattern Code");
        if (!s.senderLineConfigured) missing.push("لاین ارسال");

        var healthLabel = smsHealthStatusLabel(s.lastHealthStatus || "");
        var healthTone = healthLabel === "سالم" ? "ok" : (healthLabel === "خطادار" ? "danger" : "warn");
        var readinessMeta = ready
            ? "تنظیمات پایه برای ارسال OTP کامل است."
            : ("موارد ناقص: " + missing.join("، "));

        ownerSmsStatus.innerHTML = [
            summaryCard("آمادگی OTP", ready ? "آماده" : "ناقص", readinessMeta, ready ? "ok" : "warn"),
            summaryCard("سرویس", s.enabled ? "فعال" : "غیرفعال", "وضعیت کلی سرویس FarazSMS", s.enabled ? "ok" : "warn"),
            summaryCard("API Key", s.apiKeyConfigured ? "تنظیم شده" : "تنظیم نشده", "کلید API فقط روی سرور نگهداری می‌شود.", s.apiKeyConfigured ? "ok" : "warn"),
            summaryCard("Pattern Code", s.patternConfigured ? "تنظیم شده" : "تنظیم نشده", "کد پترن مخصوص ارسال OTP.", s.patternConfigured ? "ok" : "warn"),
            summaryCard("خط ارسال", s.senderLineConfigured ? (s.senderLine || "تنظیم شده") : "مسیر خدماتی", "در نبود خط اختصاصی، از مسیر خدماتی/اشتراکی استفاده می‌شود."),
            summaryCard("دامنه", s.domainConfigured ? (s.domain || "تنظیم شده") : "تنظیم نشده", "برای مانیتورینگ و اعتبارسنجی درخواست‌ها."),
            summaryCard("آخرین تست سلامت", healthLabel, s.lastHealthMessage || "هنوز تستی اجرا نشده است.", healthTone),
            summaryCard("زمان آخرین تست", s.lastHealthAt || "—", "آخرین زمان health check ثبت‌شده")
        ].join("");

        if (ownerSmsEnabled) {
            ownerSmsEnabled.checked = !!s.enabled;
        }
        if (ownerSmsPatternCode && !ownerSmsPatternCode.value) {
            ownerSmsPatternCode.value = s.patternConfigured ? (ownerSmsPatternCode.value || "") : "";
        }
        if (ownerSmsSenderLine) {
            ownerSmsSenderLine.value = s.senderLine || "";
        }
        if (ownerSmsDomain) {
            ownerSmsDomain.value = s.domain || "";
        }
        if (ownerSmsCodeParam) {
            ownerSmsCodeParam.value = s.codeParam || "code";
        }
        if (ownerSmsTestPhone) {
            var normalized = normalizedPhone(ownerSmsTestPhone.value);
            ownerSmsTestPhone.value = normalized;
        }
    }

    function renderOwnerMediaStatus(media) {
        if (!ownerMediaStatus) return;
        var m = media && typeof media === "object" ? media : {};
        var usagePercent = toNumber(m.usagePercent, 0);
        ownerMediaStatus.innerHTML = [
            summaryCard("مصرف فضای مدیریت‌شده", usagePercent.toFixed(2) + "%", formatBytes(m.usageBytes || 0) + " از " + formatBytes(m.targetBytes || 0)),
            summaryCard("آستانه پاکسازی", toNumber(m.thresholdPercent, 60).toFixed(2) + "%", "وقتی مصرف از این حد عبور کند پاکسازی خودکار اجرا می‌شود"),
            summaryCard("فایل اصلی باقی‌مانده", String(Math.max(0, Math.floor(toNumber(m.originalCount, 0))).toLocaleString("fa-IR")), "تعداد originalهایی که هنوز در دسترس‌اند"),
            summaryCard("فایل پاک‌شده", String(Math.max(0, Math.floor(toNumber(m.purgedCount, 0))).toLocaleString("fa-IR")), "تعداد originalهای منقضی/پاک‌شده"),
            summaryCard("آخرین پاکسازی", m.lastCleanupAt || "—", m.lastCleanupStatus || "unknown"),
            summaryCard("سلامت پاکسازی", m.cleanupHealthy ? "سالم" : "مشکل‌دار", m.lastCleanupError || "بدون خطای ثبت‌شده")
        ].join("");
    }

    async function loadOwnerSmsStatus() {
        if (!currentUser || !currentUser.isOwner) return;
        smsState.loading = true;
        ownerSmsFeedbackMessage("در حال دریافت وضعیت پیامک...", "", true);
        var response = await request("smsStatus", {});
        smsState.loading = false;

        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
            ownerSmsFeedbackMessage("", "");
            return;
        }
        if (!response || !response.success || !response.status) {
            ownerSmsFeedbackMessage((response && response.error) || "خواندن وضعیت پیامک انجام نشد.", "error");
            return;
        }

        smsState.status = response.status;
        renderOwnerSmsStatus(smsState.status);
        ownerSmsFeedbackMessage("", "");
    }

    async function saveOwnerSmsConfig(event) {
        if (event) event.preventDefault();
        if (!currentUser || !currentUser.isOwner || !ownerSmsForm) return;

        ownerSmsFeedbackMessage("در حال ذخیره تنظیمات پیامک...", "", true);
        if (ownerSmsSaveButton) ownerSmsSaveButton.disabled = true;
        if (ownerSmsHealthButton) ownerSmsHealthButton.disabled = true;

        try {
            var response = await request("saveSmsConfig", {
                enabled: ownerSmsEnabled && ownerSmsEnabled.checked ? "1" : "0",
                apiKey: ownerSmsApiKey ? ownerSmsApiKey.value.trim() : "",
                clearApiKey: ownerSmsClearApi && ownerSmsClearApi.checked ? "1" : "0",
                patternCode: ownerSmsPatternCode ? ownerSmsPatternCode.value.trim() : "",
                senderLine: ownerSmsSenderLine ? ownerSmsSenderLine.value.trim() : "",
                domain: ownerSmsDomain ? ownerSmsDomain.value.trim() : "",
                codeParam: ownerSmsCodeParam ? ownerSmsCodeParam.value.trim() : "code"
            });

            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                ownerSmsFeedbackMessage("", "");
                return;
            }
            if (!response || !response.success || !response.status) {
                ownerSmsFeedbackMessage((response && response.error) || "ذخیره تنظیمات پیامک انجام نشد.", "error");
                return;
            }

            smsState.status = response.status;
            renderOwnerSmsStatus(smsState.status);
            if (ownerSmsApiKey) ownerSmsApiKey.value = "";
            if (ownerSmsClearApi) ownerSmsClearApi.checked = false;
            ownerSmsFeedbackMessage(response.message || "تنظیمات پیامک ذخیره شد.", "success");
        } finally {
            if (ownerSmsSaveButton) ownerSmsSaveButton.disabled = false;
            if (ownerSmsHealthButton) ownerSmsHealthButton.disabled = false;
        }
    }

    async function runOwnerSmsHealthCheck() {
        if (!currentUser || !currentUser.isOwner) return;
        ownerSmsFeedbackMessage("در حال بررسی سلامت سرویس پیامک...", "", true);
        if (ownerSmsHealthButton) ownerSmsHealthButton.disabled = true;
        if (ownerSmsSaveButton) ownerSmsSaveButton.disabled = true;

        try {
            var testPhone = ensureOwnerSmsHealthPhone();
            if (!testPhone) {
                ownerSmsFeedbackMessage("برای تست ارسال واقعی، شماره موبایل معتبر را وارد کن.", "error");
                return;
            }
            var response = await request("smsHealthCheck", {
                phoneNumber: testPhone
            });
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                ownerSmsFeedbackMessage("", "");
                return;
            }
            if (response && response.status) {
                smsState.status = response.status;
                renderOwnerSmsStatus(smsState.status);
            }
            if (!response || !response.success) {
                ownerSmsFeedbackMessage((response && response.error) || (response && response.message) || "تست سلامت سرویس پیامکی ناموفق بود.", "error");
                return;
            }
            ownerSmsFeedbackMessage(response.message || "تست سلامت سرویس پیامکی با موفقیت انجام شد.", "success");
        } finally {
            if (ownerSmsHealthButton) ownerSmsHealthButton.disabled = false;
            if (ownerSmsSaveButton) ownerSmsSaveButton.disabled = false;
        }
    }

    async function loadOwnerMediaStatus() {
        if (!currentUser || !currentUser.isOwner) return;
        mediaState.loading = true;
        ownerMediaFeedbackMessage("در حال دریافت وضعیت فضای رسانه...", "", true);
        var response = await fetch("/chat/chat_api.php?action=mediaStatus", {
            method: "GET",
            credentials: "same-origin",
            headers: { "Accept": "application/json" }
        }).then(function (res) {
            return res.json().catch(function () {
                return { success: false, error: "پاسخ نامعتبر از سرور دریافت شد." };
            }).then(function (data) {
                data.httpStatus = res.status;
                return data;
            });
        }).catch(networkErrorResponse);
        mediaState.loading = false;

        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
            ownerMediaFeedbackMessage("", "");
            return;
        }
        if (!response || !response.success || !response.media) {
            ownerMediaFeedbackMessage((response && response.error) || "خواندن وضعیت فضای رسانه انجام نشد.", "error");
            return;
        }

        mediaState.status = response.media;
        renderOwnerMediaStatus(mediaState.status);
        ownerMediaFeedbackMessage("", "");
    }

    async function runOwnerMediaCleanup() {
        if (!currentUser || !currentUser.isOwner) return;
        ownerMediaFeedbackMessage("در حال اجرای پاکسازی فوری...", "", true);
        if (ownerMediaCleanupButton) ownerMediaCleanupButton.disabled = true;
        if (ownerMediaRefreshButton) ownerMediaRefreshButton.disabled = true;

        try {
            var response = await fetch("/chat/chat_api.php", {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                    "Accept": "application/json"
                },
                body: new URLSearchParams({ action: "mediaCleanupNow" })
            }).then(function (res) {
                return res.json().catch(function () {
                    return { success: false, error: "پاسخ نامعتبر از سرور دریافت شد." };
                }).then(function (data) {
                    data.httpStatus = res.status;
                    return data;
                });
            }).catch(networkErrorResponse);

            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                ownerMediaFeedbackMessage("", "");
                return;
            }
            if (!response || !response.success || !response.media) {
                ownerMediaFeedbackMessage((response && response.error) || "پاکسازی فضای رسانه انجام نشد.", "error");
                return;
            }

            mediaState.status = response.media;
            renderOwnerMediaStatus(mediaState.status);
            ownerMediaFeedbackMessage("پاکسازی فوری انجام شد.", "success");
        } finally {
            if (ownerMediaCleanupButton) ownerMediaCleanupButton.disabled = false;
            if (ownerMediaRefreshButton) ownerMediaRefreshButton.disabled = false;
        }
    }

    function userMatchesQuery(user, query) {
        if (!query) {
            return true;
        }

        var normalized = query.trim().toLowerCase();
        if (!normalized) {
            return true;
        }

        var ownerPrivate = user && user.ownerPrivate && typeof user.ownerPrivate === "object" ? user.ownerPrivate : {};
        return String(user.name || "").toLowerCase().indexOf(normalized) !== -1 ||
            String(user.studentNumber || "").indexOf(normalized) !== -1 ||
            String(user.disNumber || "").indexOf(normalized) !== -1 ||
            String(ownerPrivate.nationalCode || "").indexOf(normalized) !== -1 ||
            String(ownerPrivate.directoryPhoneNumber || "").indexOf(normalized) !== -1;
    }

    function renderRepresentatives(users) {
        var items = ownerUsersInActiveCohort(users).filter(function (user) {
            return isRepresentativeRole(user.role);
        });

        if (!items.length) {
            representativeList.innerHTML = '<div class="owner-empty">فعلاً نماینده‌ای تعریف نشده است.</div>';
            return;
        }

        representativeList.innerHTML = "";
        items.forEach(function (user) {
            var article = document.createElement("article");
            article.className = "representative-chip";
            var rotationMeta = ownerRotationMeta(user);
            var helperMeta = [user.studentNumber || "—", ownerRoleMeta(user)];
            if (rotationMeta && rotationMeta !== "بدون روتیشن/گروه") {
                helperMeta.push(rotationMeta);
            }
            article.innerHTML = [
                '<div class="representative-chip__head">',
                "  <strong>" + escapeHtml(user.name || "دانشجو") + "</strong>",
                '  <span class="owner-badge owner-badge--ok">دسترسی مدیریتی فعال</span>',
                "</div>",
                '<span class="representative-chip__meta">' + escapeHtml(helperMeta.join(" • ")) + "</span>",
                '<div class="representative-chip__stats">',
                "  " + buildOwnerBadge(user.hasGrades ? "دارای نمرات" : "بدون نمرات", user.hasGrades ? "ok" : "warn"),
                "  " + buildOwnerBadge(user.hasPhone ? "شماره تاییدشده" : "شماره ثبت نشده", user.hasPhone ? "ok" : "warn"),
                "</div>"
            ].join("");
            representativeList.appendChild(article);
        });
    }

    function formatGradeMaxScore(value) {
        var score = toNumber(value, NaN);
        if (!Number.isFinite(score) || score <= 0) {
            return "نامشخص";
        }
        return score.toLocaleString("fa-IR", { maximumFractionDigits: 2 });
    }

    function renderOwnerGradeManager() {
        var courses = Array.isArray(ownerState.gradeCourses) ? ownerState.gradeCourses : [];
        var totalScores = courses.reduce(function (sum, item) {
            return sum + Math.max(0, Math.floor(toNumber(item.withScore, 0)));
        }, 0);

        if (ownerGradesCoursesSummary) {
            ownerGradesCoursesSummary.innerHTML = [
                summaryCard("درس", courses.length.toLocaleString("fa-IR"), "تعداد درس‌های موجود در کارنامه"),
                summaryCard("نمره ثبت‌شده", totalScores.toLocaleString("fa-IR"), "جمع نمره‌های غیرخالی در همه درس‌ها"),
                summaryCard("Import", ownerState.importingGrades ? "در حال اجرا" : "آماده", "ورودی متنی یا Excel", ownerState.importingGrades ? "warn" : "ok")
            ].join("");
        }

        if (ownerGradesCourseSelect) {
            var selected = ownerGradesCourseSelect.value;
            ownerGradesCourseSelect.innerHTML = "";
            if (!courses.length) {
                var emptyOption = document.createElement("option");
                emptyOption.value = "";
                emptyOption.textContent = "درسی ثبت نشده است";
                ownerGradesCourseSelect.appendChild(emptyOption);
            } else {
                courses.forEach(function (course) {
                    var option = document.createElement("option");
                    option.value = String(course.key || "");
                    option.textContent = String(course.label || "درس") +
                        " - از " + formatGradeMaxScore(course.maxScore) +
                        " - " + Math.max(0, Math.floor(toNumber(course.withScore, 0))).toLocaleString("fa-IR") + " نمره";
                    option.selected = option.value === selected;
                    ownerGradesCourseSelect.appendChild(option);
                });
                if (!ownerGradesCourseSelect.value && ownerGradesCourseSelect.options.length) {
                    ownerGradesCourseSelect.selectedIndex = 0;
                }
            }
            ownerGradesCourseSelect.disabled = ownerState.importingGrades || ownerState.resettingGrades || !courses.length;
        }

        if (ownerGradesImportSubmit) {
            ownerGradesImportSubmit.disabled = ownerState.importingGrades || ownerState.resettingGrades;
            ownerGradesImportSubmit.textContent = ownerState.importingGrades ? "در حال import..." : "Import نمرات";
        }
        if (ownerGradesDeleteCourseButton) {
            var currentCourseKey = ownerGradesCourseSelect ? String(ownerGradesCourseSelect.value || "") : "";
            ownerGradesDeleteCourseButton.disabled = ownerState.importingGrades || ownerState.resettingGrades || !currentCourseKey ||
                ownerState.deletingGradeCourseKey === currentCourseKey;
            ownerGradesDeleteCourseButton.textContent = ownerState.deletingGradeCourseKey
                ? "در حال حذف درس..."
                : "حذف کامل درس از همه کارنامه‌ها";
        }
        if (ownerGradesResetAllButton) {
            ownerGradesResetAllButton.disabled = ownerState.importingGrades || ownerState.resettingGrades || !courses.length;
            ownerGradesResetAllButton.textContent = ownerState.resettingGrades ? "در حال ریست..." : "ریست کامل کارنامه";
        }
        [ownerGradesImportText, ownerGradesImportFile].forEach(function (node) {
            if (node) {
                node.disabled = ownerState.importingGrades || ownerState.resettingGrades;
            }
        });
    }

    function toggleButtonLabel(user) {
        if (user.role === "owner") {
            return "مالک اصلی";
        }
        if (user.role === "prosthesis_representative") {
            return "لغو نماینده پروتز";
        }
        if (user.role === "prosthesis_student" || user.isProsthesisStudent) {
            return "ثبت به‌عنوان نماینده پروتز";
        }

        return user.role === "representative" ? "لغو نماینده" : "ثبت به‌عنوان نماینده";
    }

    function isOwnerUser(user) {
        return !!user && user.role === "owner";
    }

    function isRepresentativeRole(role) {
        return role === "representative" || role === "prosthesis_representative";
    }

    function isProsthesisUser(user) {
        return !!user && (user.role === "prosthesis_student" || user.role === "prosthesis_representative" || user.isProsthesisStudent);
    }

    function ownerRoleMeta(user) {
        if (!user) {
            return "دانشجو";
        }
        return user.roleLabel || (user.role === "representative" ? "نماینده" : (user.role === "owner" ? "مالک" : "دانشجو"));
    }

    function ownerUserPhoneMeta(user) {
        var phone = parsedPhone(user || {});
        if (!phone.hasNumber) {
            return "ثبت نشده";
        }
        var masked = ltrMaskedPhone(phone.numberMasked, "شماره ثبت‌شده");
        if (!phone.verified) {
            return masked + " (تایید نشده)";
        }
        if (phone.otpLoginEnabled) {
            return masked + " (OTP فعال)";
        }
        return masked + " (OTP غیرفعال)";
    }

    function ownerUserNationalCodeMeta(user) {
        var ownerPrivate = user && user.ownerPrivate && typeof user.ownerPrivate === "object" ? user.ownerPrivate : {};
        var nationalCode = String(ownerPrivate.nationalCode || "").trim();
        if (!nationalCode) {
            return "ثبت نشده";
        }
        return ltrIsolateText(nationalCode);
    }

    function ownerDisNumberMeta(user) {
        var disNumber = userDisNumber(user);
        return disNumber ? ltrIsolateText(disNumber) : "—";
    }

    function ownerUserContactPhoneMeta(user) {
        var ownerPrivate = user && user.ownerPrivate && typeof user.ownerPrivate === "object" ? user.ownerPrivate : {};
        var directoryPhone = String(ownerPrivate.directoryPhoneNumber || "").trim();
        if (directoryPhone) {
            return ltrIsolateText(directoryPhone);
        }
        return ownerUserPhoneMeta(user);
    }

    function ownerRotationCatalog() {
        return Array.isArray(ownerState.rotationCatalog) ? ownerState.rotationCatalog : [];
    }

    function ownerCohortSupportsRotationGroups(cohort) {
        return !!(cohort && cohort.supportsRotationGroups);
    }

    function ownerUserSupportsRotationGroups(user) {
        return ownerCohortSupportsRotationGroups(ownerCohortRecordByKey(ownerUserCohortKey(user)));
    }

    function ownerRotationOptions(rotationId) {
        var target = Math.floor(toNumber(rotationId, 0));
        if (!target) {
            return [];
        }
        return ownerRotationCatalog().filter(function (item) {
            return Math.floor(toNumber(item.rotationId, 0)) === target;
        });
    }

    function ownerRotationMode(user) {
        var rotation = user && user.rotation && typeof user.rotation === "object" ? user.rotation : {};
        var source = String(rotation.source || "").trim().toLowerCase();
        if (rotation.isCampus || source === "campus") {
            return "campus";
        }
        if (source === "manual") {
            return "manual";
        }
        return "none";
    }

    function ownerRotationMeta(user) {
        if (!ownerUserSupportsRotationGroups(user)) {
            return "";
        }
        var rotation = user && user.rotation && typeof user.rotation === "object" ? user.rotation : {};
        if (rotation && rotation.assigned && rotation.summary) {
            return String(rotation.summary);
        }
        return "بدون روتیشن/گروه";
    }

    function ownerGradesPayload(studentNumber) {
        var key = String(studentNumber || "");
        if (!key) {
            return null;
        }
        var payload = ownerState.gradePayloadByStudent[key];
        if (!payload || typeof payload !== "object") {
            return null;
        }
        return payload;
    }

    function ownerGradeSaveKey(studentNumber, columnIndex) {
        return String(studentNumber || "") + ":" + String(Math.max(0, Math.floor(toNumber(columnIndex, 0))));
    }

    function ownerUserBusyState(studentNumber) {
        var key = String(studentNumber || "");
        return {
            representative: ownerState.savingStudentNumber === key,
            password: ownerState.savingPasswordStudentNumber === key,
            rotation: ownerState.savingRotationStudentNumber === key,
            gradesLoading: ownerState.loadingGradesStudentNumber === key,
            removingPhone: ownerState.removingPhoneStudentNumber === key,
            clearingExamStudy: ownerState.clearingExamStudyStudentNumber === key,
            deletingUser: ownerState.deletingStudentNumber === key
        };
    }

    function userMeta(user) {
        var parts = [ownerRoleMeta(user)];
        var rotationMeta = ownerRotationMeta(user);
        if (rotationMeta) {
            parts.push(rotationMeta);
        }
        var disNumber = userDisNumber(user);
        if (disNumber) {
            parts.push("DIS " + disNumber);
        }
        return parts.join(" • ");
    }

    function ownerCohortRecordByKey(cohortKey) {
        var target = String(cohortKey || "").trim();
        if (!target) {
            return null;
        }
        return ownerVisibleCohorts().find(function (cohort) {
            return String(cohort && cohort.key || "") === target;
        }) || null;
    }

    function ownerCohortLabelForUser(user) {
        var cohort = ownerCohortRecordByKey(ownerUserCohortKey(user));
        if (!cohort) {
            return "";
        }
        return String(cohort.shortTitle || cohort.title || cohort.key || "").trim();
    }

    function buildOwnerBadge(label, tone) {
        var text = String(label || "").trim();
        if (!text) {
            return "";
        }
        var toneClass = String(tone || "").trim();
        return '<span class="owner-badge' + (toneClass ? (' owner-badge--' + toneClass) : "") + '">' + escapeHtml(text) + "</span>";
    }

    function renderOwnerToolbarMeta(users) {
        var activeCohort = ownerActiveCohortRecord();
        var visibleUsers = ownerUsersInActiveCohort(users);
        var representativeCount = visibleUsers.filter(function (user) {
            return isRepresentativeRole(user.role);
        }).length;
        if (ownerToolbarTitle) {
            ownerToolbarTitle.textContent = activeCohort
                ? ("کاربران " + String(activeCohort.shortTitle || activeCohort.title || "ورودی فعال"))
                : "کاربران ورودی فعال";
        }
        if (ownerToolbarMeta) {
            ownerToolbarMeta.textContent = [
                visibleUsers.length.toLocaleString("fa-IR") + " کاربر",
                representativeCount.toLocaleString("fa-IR") + " نماینده",
                activeCohort && activeCohort.productType === "prosthesis" ? "محیط ایزوله پروتز" : "محیط اصلی دندانپزشکی"
            ].join(" • ");
        }
    }

    function buildOwnerMetaCell(label, value, latinDigits) {
        var item = document.createElement("div");
        item.className = "owner-user-meta-item";

        var title = document.createElement("span");
        title.textContent = label;

        var content = document.createElement("strong");
        content.textContent = value || "—";
        if (latinDigits) {
            content.dataset.latinDigits = "true";
        }

        item.appendChild(title);
        item.appendChild(content);
        return item;
    }

    function buildOwnerUserDetails(user, busyState) {
        var studentNumber = String(user.studentNumber || "");
        var supportsRotation = ownerUserSupportsRotationGroups(user);
        var details = document.createElement("section");
        details.className = "owner-user__details";

        var metaGrid = document.createElement("div");
        metaGrid.className = "owner-user__meta-grid";
        metaGrid.appendChild(buildOwnerMetaCell("\u0646\u0627\u0645", user.name || "\u2014"));
        metaGrid.appendChild(buildOwnerMetaCell("\u0634\u0645\u0627\u0631\u0647 \u062f\u0627\u0646\u0634\u062c\u0648\u06cc\u06cc", studentNumber || "\u2014"));
        metaGrid.appendChild(buildOwnerMetaCell("\u0634\u0645\u0627\u0631\u0647 DIS", ownerDisNumberMeta(user), true));
        metaGrid.appendChild(buildOwnerMetaCell("\u0646\u0642\u0634", ownerRoleMeta(user)));
        if (supportsRotation) {
            metaGrid.appendChild(buildOwnerMetaCell("\u0631\u0648\u062a\u06cc\u0634\u0646/\u06af\u0631\u0648\u0647", ownerRotationMeta(user)));
        }
        metaGrid.appendChild(buildOwnerMetaCell("\u06a9\u062f \u0645\u0644\u06cc", ownerUserNationalCodeMeta(user)));
        metaGrid.appendChild(buildOwnerMetaCell("\u062a\u0644\u0641\u0646", ownerUserContactPhoneMeta(user)));
        details.appendChild(metaGrid);

        var adminGrid = document.createElement("div");
        adminGrid.className = "owner-user__admin-grid";

        var passwordCard = document.createElement("div");
        passwordCard.className = "owner-user-admin-card";
        var passwordTitle = document.createElement("div");
        passwordTitle.className = "owner-user-admin-card__title";
        passwordTitle.textContent = "\u062a\u0639\u06cc\u06cc\u0646/\u062a\u063a\u06cc\u06cc\u0631 \u0631\u0645\u0632 \u0639\u0628\u0648\u0631";
        passwordCard.appendChild(passwordTitle);

        var passwordInput = document.createElement("input");
        passwordInput.type = "password";
        passwordInput.className = "owner-user-inline-input";
        passwordInput.placeholder = "\u062d\u062f\u0627\u0642\u0644 \u06f6 \u06a9\u0627\u0631\u0627\u06a9\u062a\u0631";
        passwordInput.autocomplete = "new-password";
        passwordInput.dataset.ownerPasswordInput = "true";
        passwordInput.dataset.studentNumber = studentNumber;
        passwordInput.disabled = busyState.password || busyState.deletingUser;
        passwordCard.appendChild(passwordInput);

        var passwordBtn = document.createElement("button");
        passwordBtn.type = "button";
        passwordBtn.className = "shell-action-btn shell-action-btn-primary";
        passwordBtn.dataset.ownerAction = "save-password";
        passwordBtn.dataset.studentNumber = studentNumber;
        passwordBtn.disabled = busyState.password || busyState.deletingUser;
        passwordBtn.textContent = busyState.password ? "\u062f\u0631 \u062d\u0627\u0644 \u0630\u062e\u06cc\u0631\u0647..." : "\u0630\u062e\u06cc\u0631\u0647 \u0631\u0645\u0632";
        passwordCard.appendChild(passwordBtn);
        adminGrid.appendChild(passwordCard);

        if (supportsRotation) {
            var rotationCard = document.createElement("div");
            rotationCard.className = "owner-user-admin-card";
            var rotationTitle = document.createElement("div");
            rotationTitle.className = "owner-user-admin-card__title";
            rotationTitle.textContent = "\u062a\u062e\u0635\u06cc\u0635 \u0631\u0648\u062a\u06cc\u0634\u0646/\u06af\u0631\u0648\u0647";
            rotationCard.appendChild(rotationTitle);

            var modeRow = document.createElement("div");
            modeRow.className = "owner-user-inline-row";
            var modeSelect = document.createElement("select");
            modeSelect.className = "owner-user-inline-select";
            modeSelect.dataset.ownerRotationMode = "true";
            modeSelect.dataset.studentNumber = studentNumber;
            modeSelect.disabled = busyState.rotation || busyState.deletingUser;

            var currentMode = ownerRotationMode(user);
            [
                { value: "none", label: "\u0628\u062f\u0648\u0646 \u062a\u062e\u0635\u06cc\u0635" },
                { value: "manual", label: "\u0631\u0648\u062a\u06cc\u0634\u0646/\u06af\u0631\u0648\u0647 \u0645\u0634\u062e\u0635" },
                { value: "campus", label: "\u062f\u0627\u0646\u0634\u062c\u0648\u06cc \u067e\u0631\u062f\u06cc\u0633" }
            ].forEach(function (item) {
                var option = document.createElement("option");
                option.value = item.value;
                option.textContent = item.label;
                option.selected = item.value === currentMode;
                modeSelect.appendChild(option);
            });
            modeRow.appendChild(modeSelect);
            rotationCard.appendChild(modeRow);

            var currentRotationId = Math.floor(toNumber(user && user.rotation ? user.rotation.rotationId : 0, 0));
            var currentGroupNumber = Math.floor(toNumber(user && user.rotation ? user.rotation.groupNumber : 0, 0));

            var fieldsRow = document.createElement("div");
            fieldsRow.className = "owner-user-inline-row owner-user-inline-row--double";

            var rotationSelect = document.createElement("select");
            rotationSelect.className = "owner-user-inline-select";
            rotationSelect.dataset.ownerRotationId = "true";
            rotationSelect.dataset.studentNumber = studentNumber;
            rotationSelect.disabled = currentMode !== "manual" || busyState.rotation || busyState.deletingUser;
            [
                { value: "", label: "\u0627\u0646\u062a\u062e\u0627\u0628 \u0631\u0648\u062a\u06cc\u0634\u0646" },
                { value: "1", label: "\u0631\u0648\u062a\u06cc\u0634\u0646 \u06f1" },
                { value: "2", label: "\u0631\u0648\u062a\u06cc\u0634\u0646 \u06f2" }
            ].forEach(function (item) {
                var option = document.createElement("option");
                option.value = item.value;
                option.textContent = item.label;
                option.selected = item.value !== "" && Number(item.value) === currentRotationId && currentMode === "manual";
                rotationSelect.appendChild(option);
            });
            if (!rotationSelect.value && currentMode === "manual" && (currentRotationId === 1 || currentRotationId === 2)) {
                rotationSelect.value = String(currentRotationId);
            }
            fieldsRow.appendChild(rotationSelect);

            var groupSelect = document.createElement("select");
            groupSelect.className = "owner-user-inline-select";
            groupSelect.dataset.ownerGroupNumber = "true";
            groupSelect.dataset.studentNumber = studentNumber;
            groupSelect.disabled = currentMode !== "manual" || busyState.rotation || busyState.deletingUser;
            var rotationOptions = ownerRotationOptions(currentMode === "manual" ? (rotationSelect.value || currentRotationId) : "");
            if (!rotationOptions.length) {
                var emptyOption = document.createElement("option");
                emptyOption.value = "";
                emptyOption.textContent = "\u0627\u0628\u062a\u062f\u0627 \u0631\u0648\u062a\u06cc\u0634\u0646 \u0631\u0627 \u0627\u0646\u062a\u062e\u0627\u0628 \u06a9\u0646\u06cc\u062f";
                emptyOption.selected = true;
                groupSelect.appendChild(emptyOption);
            } else {
                var placeholder = document.createElement("option");
                placeholder.value = "";
                placeholder.textContent = "\u0627\u0646\u062a\u062e\u0627\u0628 \u06af\u0631\u0648\u0647";
                groupSelect.appendChild(placeholder);
                rotationOptions.forEach(function (item) {
                    var option = document.createElement("option");
                    option.value = String(item.groupNumber);
                    option.textContent = item.groupLabel + (item.groupTitle ? (" (" + item.groupTitle + ")") : "");
                    option.selected = currentMode === "manual" && Number(item.groupNumber) === currentGroupNumber;
                    groupSelect.appendChild(option);
                });
            }
            fieldsRow.appendChild(groupSelect);
            rotationCard.appendChild(fieldsRow);

            var rotationBtn = document.createElement("button");
            rotationBtn.type = "button";
            rotationBtn.className = "shell-action-btn";
            rotationBtn.dataset.ownerAction = "save-rotation";
            rotationBtn.dataset.studentNumber = studentNumber;
            rotationBtn.disabled = busyState.rotation || busyState.deletingUser;
            rotationBtn.textContent = busyState.rotation ? "\u062f\u0631 \u062d\u0627\u0644 \u0630\u062e\u06cc\u0631\u0647..." : "\u0630\u062e\u06cc\u0631\u0647 \u062a\u062e\u0635\u06cc\u0635";
            rotationCard.appendChild(rotationBtn);

            adminGrid.appendChild(rotationCard);
        }
        details.appendChild(adminGrid);

        var learningSummary = user && user.examLearningSummary && typeof user.examLearningSummary === "object"
            ? user.examLearningSummary
            : {};
        var learningCard = document.createElement("section");
        learningCard.className = "owner-user-admin-card owner-user-admin-card--exam-learning";
        var learningTitle = document.createElement("div");
        learningTitle.className = "owner-user-admin-card__title";
        learningTitle.textContent = "داده‌های مطالعه آزمون";
        learningCard.appendChild(learningTitle);
        var learningMeta = document.createElement("p");
        learningMeta.className = "owner-user__hint";
        learningMeta.textContent = [
            Number(learningSummary.attemptCount || 0).toLocaleString("fa-IR") + " تلاش",
            Number(learningSummary.noteCount || 0).toLocaleString("fa-IR") + " یادداشت",
            Number(learningSummary.highlightCount || 0).toLocaleString("fa-IR") + " هایلایت",
            Number(learningSummary.mistakeQuestionCount || 0).toLocaleString("fa-IR") + " سؤال در دفترچه اشتباهات"
        ].join(" • ");
        learningCard.appendChild(learningMeta);
        var clearLearningBtn = document.createElement("button");
        clearLearningBtn.type = "button";
        clearLearningBtn.className = "shell-action-btn shell-action-btn-danger";
        clearLearningBtn.dataset.ownerAction = "clear-exam-study";
        clearLearningBtn.dataset.studentNumber = studentNumber;
        clearLearningBtn.disabled = busyState.clearingExamStudy || busyState.deletingUser
            || (Number(learningSummary.noteCount || 0) + Number(learningSummary.highlightCount || 0)
                + Number(learningSummary.struckOptionCount || 0) + Number(learningSummary.mistakeQuestionCount || 0) <= 0);
        clearLearningBtn.textContent = busyState.clearingExamStudy ? "در حال پاک‌سازی..." : "پاک‌کردن ابزارهای مطالعه";
        learningCard.appendChild(clearLearningBtn);
        details.appendChild(learningCard);

        var actions = document.createElement("div");
        actions.className = "owner-user__actions owner-user__actions--detail";

        var gradesPayload = ownerGradesPayload(studentNumber);
        var loadGradesBtn = document.createElement("button");
        loadGradesBtn.type = "button";
        loadGradesBtn.className = "shell-action-btn";
        loadGradesBtn.dataset.ownerAction = "reload-grades";
        loadGradesBtn.dataset.studentNumber = studentNumber;
        loadGradesBtn.disabled = busyState.gradesLoading || busyState.deletingUser;
        loadGradesBtn.textContent = busyState.gradesLoading
            ? "\u062f\u0631 \u062d\u0627\u0644 \u062f\u0631\u06cc\u0627\u0641\u062a \u06a9\u0627\u0631\u0646\u0627\u0645\u0647..."
            : (gradesPayload ? "\u0628\u0627\u0631\u06af\u0630\u0627\u0631\u06cc \u0645\u062c\u062f\u062f \u06a9\u0627\u0631\u0646\u0627\u0645\u0647" : "\u0646\u0645\u0627\u06cc\u0634 \u06a9\u0627\u0631\u0646\u0627\u0645\u0647");
        actions.appendChild(loadGradesBtn);

        var removePhoneBtn = document.createElement("button");
        removePhoneBtn.type = "button";
        removePhoneBtn.className = "shell-action-btn";
        removePhoneBtn.dataset.ownerAction = "remove-phone";
        removePhoneBtn.dataset.studentNumber = studentNumber;
        removePhoneBtn.disabled = busyState.removingPhone || busyState.deletingUser || isOwnerUser(user) || !user.hasPhone;
        removePhoneBtn.textContent = busyState.removingPhone ? "\u062f\u0631 \u062d\u0627\u0644 \u062d\u0630\u0641 \u0634\u0645\u0627\u0631\u0647..." : "\u062d\u0630\u0641 \u0634\u0645\u0627\u0631\u0647 \u062b\u0628\u062a\u200c\u0634\u062f\u0647";
        actions.appendChild(removePhoneBtn);

        var deleteUserBtn = document.createElement("button");
        deleteUserBtn.type = "button";
        deleteUserBtn.className = "shell-action-btn shell-action-btn-danger";
        deleteUserBtn.dataset.ownerAction = "delete-student";
        deleteUserBtn.dataset.studentNumber = studentNumber;
        deleteUserBtn.disabled = busyState.deletingUser || isOwnerUser(user);
        deleteUserBtn.textContent = busyState.deletingUser ? "\u062f\u0631 \u062d\u0627\u0644 \u062d\u0630\u0641 \u062f\u0627\u0646\u0634\u062c\u0648..." : "\u062d\u0630\u0641 \u06a9\u0627\u0645\u0644 \u062f\u0627\u0646\u0634\u062c\u0648";
        actions.appendChild(deleteUserBtn);

        details.appendChild(actions);

        var gradesWrap = document.createElement("div");
        gradesWrap.className = "owner-user__grades";

        if (busyState.gradesLoading && !gradesPayload) {
            var loadingHint = document.createElement("div");
            loadingHint.className = "owner-user__hint";
            loadingHint.textContent = "\u062f\u0631 \u062d\u0627\u0644 \u062f\u0631\u06cc\u0627\u0641\u062a \u06a9\u0627\u0631\u0646\u0627\u0645\u0647...";
            gradesWrap.appendChild(loadingHint);
            details.appendChild(gradesWrap);
            return details;
        }

        if (!gradesPayload) {
            var emptyHint = document.createElement("div");
            emptyHint.className = "owner-user__hint";
            emptyHint.textContent = "\u0628\u0631\u0627\u06cc \u0645\u0634\u0627\u0647\u062f\u0647 \u0648 \u0648\u06cc\u0631\u0627\u06cc\u0634 \u0646\u0645\u0631\u0627\u062a\u060c \u0631\u0648\u06cc \u00ab\u0646\u0645\u0627\u06cc\u0634 \u06a9\u0627\u0631\u0646\u0627\u0645\u0647\u00bb \u0628\u0632\u0646.";
            gradesWrap.appendChild(emptyHint);
            details.appendChild(gradesWrap);
            return details;
        }

        var gradeList = document.createElement("div");
        gradeList.className = "owner-grade-list";
        var grades = Array.isArray(gradesPayload.grades) ? gradesPayload.grades : [];

        if (!grades.length) {
            var noGrades = document.createElement("div");
            noGrades.className = "owner-user__hint";
            noGrades.textContent = "\u0633\u062a\u0648\u0646 \u0646\u0645\u0631\u0647\u200c\u0627\u06cc \u0628\u0631\u0627\u06cc \u0627\u06cc\u0646 \u062f\u0627\u0646\u0634\u062c\u0648 \u067e\u06cc\u062f\u0627 \u0646\u0634\u062f.";
            gradesWrap.appendChild(noGrades);
            details.appendChild(gradesWrap);
            return details;
        }

        grades.forEach(function (grade) {
            var index = Math.max(0, Math.floor(toNumber(grade.index, 0)));
            var row = document.createElement("div");
            row.className = "owner-grade-row";

            var label = document.createElement("label");
            label.className = "owner-grade-row__label";
            var maxScoreLabel = formatGradeMaxScore(grade.maxScore);
            label.textContent = String(grade.label || ("\u0633\u062a\u0648\u0646 " + index)) +
                (maxScoreLabel !== "نامشخص" ? (" (از " + maxScoreLabel + ")") : "");
            row.appendChild(label);

            var controls = document.createElement("div");
            controls.className = "owner-grade-row__controls";

            var input = document.createElement("input");
            input.type = "text";
            input.inputMode = "decimal";
            input.className = "owner-grade-input";
            input.placeholder = "\u0628\u062f\u0648\u0646 \u0646\u0645\u0631\u0647";
            input.value = String(grade.value == null ? "" : grade.value);
            input.dataset.gradeInput = "true";
            input.dataset.studentNumber = studentNumber;
            input.dataset.columnIndex = String(index);
            input.disabled = busyState.deletingUser || busyState.gradesLoading;
            controls.appendChild(input);

            var saveBtn = document.createElement("button");
            saveBtn.type = "button";
            saveBtn.className = "shell-action-btn shell-action-btn-primary";
            saveBtn.dataset.ownerAction = "save-grade";
            saveBtn.dataset.studentNumber = studentNumber;
            saveBtn.dataset.columnIndex = String(index);
            var isSaving = ownerState.savingGradeKey === ownerGradeSaveKey(studentNumber, index);
            saveBtn.disabled = isSaving || busyState.deletingUser || busyState.gradesLoading;
            saveBtn.textContent = isSaving ? "\u062f\u0631 \u062d\u0627\u0644 \u0630\u062e\u06cc\u0631\u0647..." : "\u0630\u062e\u06cc\u0631\u0647";
            controls.appendChild(saveBtn);

            row.appendChild(controls);
            gradeList.appendChild(row);
        });

        gradesWrap.appendChild(gradeList);
        details.appendChild(gradesWrap);
        return details;
    }

    function findOwnerUser(studentNumber) {
        var target = String(studentNumber || "").trim();
        if (!target) {
            return null;
        }
        return ownerState.users.find(function (item) {
            return String(item.studentNumber || "") === target;
        }) || null;
    }

    function renderOwnerUserPanel() {
        if (!ownerUserPanelBody) {
            return;
        }

        var studentNumber = String(ownerState.activeUserPanelStudentNumber || "").trim();
        var user = findOwnerUser(studentNumber);
        if (!studentNumber || !user) {
            if (ownerUserPanelTitle) {
                ownerUserPanelTitle.textContent = "مدیریت کاربر";
            }
            if (ownerUserPanelSubtitle) {
                ownerUserPanelSubtitle.textContent = "برای مدیریت، یک کاربر را از فهرست انتخاب کن.";
            }
            if (ownerUserPanelSummary) {
                ownerUserPanelSummary.innerHTML = "";
            }
            ownerUserPanelBody.innerHTML = '<div class="owner-empty">برای باز کردن پنل اختصاصی، از فهرست کاربران روی «پنل کاربر» بزن.</div>';
            return;
        }

        var busyState = ownerUserBusyState(studentNumber);
        var gradesPayload = ownerGradesPayload(studentNumber);
        var gradeCount = 0;
        if (gradesPayload && Array.isArray(gradesPayload.grades)) {
            gradeCount = gradesPayload.grades.filter(function (grade) {
                return String(grade && grade.value != null ? grade.value : "").trim() !== "";
            }).length;
        }

        if (ownerUserPanelTitle) {
            ownerUserPanelTitle.textContent = user.name || "دانشجو";
        }
        if (ownerUserPanelSubtitle) {
            ownerUserPanelSubtitle.textContent = "شماره دانشجویی: " + (studentNumber || "—") + " • " + ownerRoleMeta(user);
        }
        if (ownerUserPanelSummary) {
            ownerUserPanelSummary.innerHTML = [
                summaryCard("نقش", ownerRoleMeta(user), user.role === "owner" ? "حساب مالک اصلی" : "سطح دسترسی فعلی"),
                summaryCard("روتیشن/گروه", ownerRotationMeta(user), "تخصیص آموزشی حساب"),
                summaryCard("نمره", gradeCount ? gradeCount.toLocaleString("fa-IR") : "—", gradesPayload ? "نمره‌های ثبت‌شده برای این کاربر" : "کارنامه هنوز بارگذاری نشده"),
                summaryCard("تماس", user.hasPhone ? "دارای شماره" : "بدون شماره", ownerUserContactPhoneMeta(user))
            ].join("");
        }

        ownerUserPanelBody.innerHTML = "";
        ownerUserPanelBody.appendChild(buildOwnerUserDetails(user, busyState));
    }

    function renderUsers(users) {
        var query = ownerSearch.value || "";
        var visibleUsers = ownerUsersInActiveCohort(users).filter(function (user) {
            return userMatchesQuery(user, query);
        });
        renderOwnerToolbarMeta(users);
        var pageSize = ownerUserPageSize();
        var pageCount = Math.max(1, Math.ceil(visibleUsers.length / pageSize));
        ownerState.userPage = Math.max(1, Math.min(pageCount, Number(ownerState.userPage) || 1));
        var pageStart = (ownerState.userPage - 1) * pageSize;
        var pageUsers = visibleUsers.slice(pageStart, pageStart + pageSize);

        if (!visibleUsers.length) {
            ownerUserList.innerHTML = '<div class="owner-empty">کاربری با این جست‌وجو پیدا نشد.</div>';
            if (ownerUserPager) ownerUserPager.innerHTML = "";
            renderOwnerUserPanel();
            return;
        }

        ownerUserList.innerHTML = "";
        pageUsers.forEach(function (user) {
            var studentNumber = String(user.studentNumber || "");
            var busyState = ownerUserBusyState(studentNumber);
            var article = document.createElement("article");
            article.className = "owner-user" + (ownerState.activeUserPanelStudentNumber === studentNumber ? " is-active-panel" : "");

            var head = document.createElement("div");
            head.className = "owner-user__head";

            var copy = document.createElement("div");
            copy.className = "owner-user__copy";
            var strong = document.createElement("strong");
            strong.textContent = user.name || "دانشجو";
            var number = document.createElement("span");
            number.textContent = studentNumber || "—";
            var status = document.createElement("div");
            status.className = "owner-user__status";
            status.innerHTML = [
                buildOwnerBadge(ownerRoleMeta(user), isRepresentativeRole(user.role) ? "ok" : "accent"),
                buildOwnerBadge(ownerRotationMeta(user), ownerRotationMeta(user) === "بدون روتیشن/گروه" ? "warn" : ""),
                buildOwnerBadge(user.hasGrades ? "دارای نمرات" : "بدون نمرات", user.hasGrades ? "ok" : "warn")
            ].join("");
            var meta = document.createElement("small");
            meta.textContent = userMeta(user);
            var quickMeta = document.createElement("div");
            quickMeta.className = "owner-user__meta-strip";
            var identityReady = !!user.hasNationalCode && !!user.hasDirectoryPhone;
            quickMeta.innerHTML = [
                buildOwnerBadge(user.hasPhone ? "OTP فعال" : "بدون OTP", user.hasPhone ? "ok" : "warn"),
                buildOwnerBadge(identityReady ? "پروفایل کامل" : "پروفایل ناقص", identityReady ? "soft" : "warn")
            ].join("");
            copy.appendChild(strong);
            copy.appendChild(number);
            copy.appendChild(status);
            copy.appendChild(meta);
            copy.appendChild(quickMeta);
            head.appendChild(copy);

            var actions = document.createElement("div");
            actions.className = "owner-user__actions";
            var activeCohort = ownerActiveCohortRecord();
            var representativeToggleAllowed = hasOwnerAccess() || !!(activeCohort && activeCohort.allowRepresentativeManagement);

            var representativeBtn = document.createElement("button");
            representativeBtn.type = "button";
            representativeBtn.className = "shell-action-btn" + (isRepresentativeRole(user.role) ? " shell-action-btn-primary" : "");
            representativeBtn.dataset.ownerAction = "toggle-representative";
            representativeBtn.dataset.studentNumber = studentNumber;
            representativeBtn.disabled = isOwnerUser(user) || !representativeToggleAllowed || busyState.representative || busyState.deletingUser;
            representativeBtn.textContent = busyState.representative ? "در حال ذخیره..." : toggleButtonLabel(user);
            actions.appendChild(representativeBtn);

            var panelBtn = document.createElement("button");
            panelBtn.type = "button";
            panelBtn.className = "shell-action-btn";
            panelBtn.dataset.ownerAction = "open-user-panel";
            panelBtn.dataset.studentNumber = studentNumber;
            panelBtn.textContent = "جزئیات";
            actions.appendChild(panelBtn);

            head.appendChild(actions);
            article.appendChild(head);

            ownerUserList.appendChild(article);
        });
        if (ownerUserPager) {
            if (pageCount <= 1) {
                ownerUserPager.innerHTML = '<span>' + visibleUsers.length.toLocaleString("fa-IR") + " کاربر</span>";
            } else {
                ownerUserPager.innerHTML = [
                    '<button class="shell-action-btn" type="button" data-owner-page="' + String(ownerState.userPage - 1) + '"' + (ownerState.userPage <= 1 ? " disabled" : "") + ">قبلی</button>",
                    '<span>صفحه ' + ownerState.userPage.toLocaleString("fa-IR") + " از " + pageCount.toLocaleString("fa-IR") + " • " + visibleUsers.length.toLocaleString("fa-IR") + " کاربر</span>",
                    '<button class="shell-action-btn" type="button" data-owner-page="' + String(ownerState.userPage + 1) + '"' + (ownerState.userPage >= pageCount ? " disabled" : "") + ">بعدی</button>"
                ].join("");
            }
        }
        renderOwnerUserPanel();
    }

    function renderOwnerPanel() {
        renderOwnerCohortPicker();
        updateOwnerTabs();
        renderOwnerSummary(ownerState.users);
        renderOwnerGradeManager();
        renderOwnerToolbarMeta(ownerState.users);
        renderRepresentatives(ownerState.users);
        renderUsers(ownerState.users);
        renderOwnerUserPanel();
        renderOwnerAnalytics();
        if (ownerState.activeTab === "stats" && hasOwnerAccess() && !ownerAnalyticsState.loading && !ownerAnalyticsState.loaded) {
            loadOwnerAnalytics(false);
        }
    }

    async function loadOwnerAnalytics(force) {
        if (!ownerCanAccessStats() || !ownerStatsOverview) {
            return;
        }
        if (ownerAnalyticsState.loading) {
            return;
        }
        if (ownerAnalyticsState.loaded && ownerAnalyticsState.dashboard && !force) {
            renderOwnerAnalytics();
            return;
        }

        ownerAnalyticsState.loading = true;
        ownerStatsFeedbackMessage("در حال بارگذاری آمار سایت...", "", true);
        renderOwnerAnalytics();

        var response = await analyticsGet("ownerDashboard");
        ownerAnalyticsState.loading = false;

        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
            ownerStatsFeedbackMessage("", "");
            renderOwnerAnalytics();
            return;
        }

        if (!response || !response.success || !response.dashboard) {
            ownerStatsFeedbackMessage((response && response.error) || "آمار سایت خوانده نشد.", "error");
            renderOwnerAnalytics();
            return;
        }

        ownerAnalyticsState.dashboard = response.dashboard;
        ownerAnalyticsState.loaded = true;
        ownerStatsFeedbackMessage("", "");
        renderOwnerAnalytics();
    }
    function navidStatusResultLabel(result) {
        switch (result) {
            case "ok":
                return "\u067e\u0627\u06cc\u062f\u0627\u0631";
            case "partial":
                return "\u0646\u0627\u0642\u0635";
            case "running":
                return "\u062f\u0631 \u062d\u0627\u0644 \u0627\u062c\u0631\u0627";
            case "config-updated":
                return "\u062a\u0646\u0638\u06cc\u0645\u0627\u062a \u0628\u0647\u200c\u0631\u0648\u0632 \u0634\u062f";
            case "credentials-missing":
                return "\u0627\u0639\u062a\u0628\u0627\u0631 \u062b\u0628\u062a \u0646\u0634\u062f\u0647";
            case "credentials-invalid":
                return "\u0627\u0639\u062a\u0628\u0627\u0631 \u0646\u0627\u0645\u0639\u062a\u0628\u0631";
            case "reconnect-required":
                return "\u0646\u06cc\u0627\u0632\u0645\u0646\u062f \u0627\u062a\u0635\u0627\u0644 \u0645\u062c\u062f\u062f";
            case "login-failed":
                return "\u062e\u0637\u0627\u06cc \u0648\u0631\u0648\u062f";
            case "dashboard-failed":
                return "\u062e\u0637\u0627\u06cc \u062f\u0627\u0634\u0628\u0648\u0631\u062f";
            case "exception":
                return "\u062e\u0637\u0627\u06cc \u062f\u0627\u062e\u0644\u06cc";
            case "skipped":
                return "\u0641\u0639\u0644\u0627\u064b \u0646\u06cc\u0627\u0632 \u0646\u06cc\u0633\u062a";
            case "already-running":
                return "\u062f\u0631 \u062d\u0627\u0644 \u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc";
            case "lock-failed":
                return "\u062e\u0637\u0627\u06cc \u0642\u0641\u0644 \u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc";
            case "disabled":
                return "\u063a\u06cc\u0631\u0641\u0639\u0627\u0644";
            default:
                return "\u0646\u0627\u0645\u0634\u062e\u0635";
        }
    }

    function navidActionRequiredLabel(action) {
        switch (String(action || "")) {
            case "save-credentials":
                return "\u062b\u0628\u062a \u0627\u0639\u062a\u0628\u0627\u0631 \u0646\u0648\u06cc\u062f";
            case "update-credentials":
                return "\u0628\u0647\u200c\u0631\u0648\u0632\u0631\u0633\u0627\u0646\u06cc \u0627\u0639\u062a\u0628\u0627\u0631 \u0646\u0648\u06cc\u062f";
            case "manual-reconnect":
                return "\u0627\u062a\u0635\u0627\u0644 \u0645\u062c\u062f\u062f \u0628\u0627 \u06a9\u067e\u0686\u0627";
            case "disabled":
                return "\u063a\u06cc\u0631\u0641\u0639\u0627\u0644";
            default:
                return "\u0647\u06cc\u0686";
        }
    }

    function navidFeedbackMessage(text, kind, loading) {
        setInlineFeedback(navidConfigFeedback, text, kind, loading);
    }

    function navidReconnectMessage(text, kind, loading) {
        setInlineFeedback(navidReconnectFeedback, text, kind, loading);
    }

    function navidSyncChallengeVisual(ownerStatus, explicitCaptchaDataUri) {
        if (!navidCaptchaImage) {
            return;
        }

        var state = ownerStatus && typeof ownerStatus === "object" ? (ownerStatus.state || {}) : {};
        var challengeActive = !!state.hasActiveChallenge;
        var captchaDataUri = String(explicitCaptchaDataUri || state.captchaDataUri || "").trim();

        if (challengeActive && captchaDataUri) {
            navidCaptchaImage.hidden = false;
            navidCaptchaImage.src = captchaDataUri;
            return;
        }

        navidCaptchaImage.hidden = true;
        navidCaptchaImage.removeAttribute("src");
    }

    function navidRenderOwnerStatus(ownerStatus) {
        if (!navidOwnerStatus) {
            return;
        }

        if (!ownerStatus || typeof ownerStatus !== "object") {
            navidOwnerStatus.innerHTML = summaryCard("\u0646\u0648\u06cc\u062f", "\u2014", "\u0648\u0636\u0639\u06cc\u062a \u06cc\u06a9\u067e\u0627\u0631\u0686\u0647\u200c\u0633\u0627\u0632\u06cc \u062f\u0631 \u062f\u0633\u062a\u0631\u0633 \u0646\u06cc\u0633\u062a.");
            navidSyncChallengeVisual(null, "");
            if (accountRowNavidMeta) {
                accountRowNavidMeta.textContent = "\u0648\u0636\u0639\u06cc\u062a \u0627\u062a\u0635\u0627\u0644 \u062e\u0648\u0627\u0646\u062f\u0647 \u0646\u0634\u062f\u0647 \u0627\u0633\u062a";
            }
            return;
        }

        var config = ownerStatus.config || {};
        var state = ownerStatus.state || {};
        var session = ownerStatus.session || {};
        var counts = ownerStatus.snapshotCounts || {};
        var actionRequired = String(state.actionRequired || "");
        var statusDetail = String(state.lastError || "").trim();
        var failedCourses = Math.max(0, Math.floor(toNumber(state.lastFailedCourses != null ? state.lastFailedCourses : counts.failedCourses, 0)));
        var lastSuccessAtLabel = formatJalaliDateTime(state.lastSuccessAt, "—");
        var challengeExpiresAtLabel = formatJalaliDateTime(state.challengeExpiresAt, "—");

        if (!statusDetail) {
            if (actionRequired === "save-credentials" || !!state.credentialsMissing) {
                statusDetail = "\u0646\u0627\u0645 \u06a9\u0627\u0631\u0628\u0631\u06cc \u0648 \u0631\u0645\u0632 \u0646\u0648\u06cc\u062f \u0630\u062e\u06cc\u0631\u0647 \u0646\u0634\u062f\u0647 \u0627\u0633\u062a.";
            } else if (actionRequired === "update-credentials" || !!state.credentialsInvalid) {
                statusDetail = "\u0627\u0639\u062a\u0628\u0627\u0631 \u0630\u062e\u06cc\u0631\u0647\u200c\u0634\u062f\u0647 \u0646\u0648\u06cc\u062f \u0646\u0627\u0645\u0639\u062a\u0628\u0631 \u0627\u0633\u062a.";
            } else if (actionRequired === "manual-reconnect" || !!state.requiresReconnect) {
                statusDetail = "\u0646\u0634\u0633\u062a \u0646\u0648\u06cc\u062f \u0646\u06cc\u0627\u0632 \u0628\u0647 \u0627\u062a\u0635\u0627\u0644 \u0645\u062c\u062f\u062f \u062f\u0627\u0631\u062f.";
            } else if (String(state.lastResult || "") === "partial") {
                statusDetail = failedCourses > 0
                    ? ("\u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc \u0646\u0627\u0642\u0635 \u0628\u0648\u062f\u061b " + failedCourses.toLocaleString("fa-IR") + " \u062f\u0631\u0633 \u062f\u0631\u06cc\u0627\u0641\u062a \u0646\u0634\u062f.")
                    : "\u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc \u0646\u0627\u0642\u0635 \u0628\u0648\u062f \u0648 \u062e\u0631\u0648\u062c\u06cc \u062a\u0627\u06cc\u06cc\u062f \u0646\u0634\u062f.";
            } else if (String(state.lastResult || "") === "ok") {
                statusDetail = "\u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc \u0646\u0648\u06cc\u062f \u0633\u0627\u0644\u0645 \u0627\u0633\u062a.";
            } else {
                statusDetail = "\u0628\u062f\u0648\u0646 \u062c\u0632\u0626\u06cc\u0627\u062a \u062e\u0637\u0627";
            }
        }

        navidOwnerStatus.innerHTML = [
            summaryCard("\u0648\u0636\u0639\u06cc\u062a", navidStatusResultLabel(state.lastResult || ""), statusDetail),
            summaryCard("\u0622\u062e\u0631\u06cc\u0646 \u0645\u0648\u0641\u0642", lastSuccessAtLabel, "\u0622\u062e\u0631\u06cc\u0646 \u0632\u0645\u0627\u0646 \u0645\u0648\u0641\u0642\u06cc\u062a \u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc"),
            summaryCard("\u062f\u0631\u0648\u0633 \u0645\u0648\u0641\u0642", String(counts.successfulCourses || 0).toLocaleString("fa-IR"), "\u062a\u0639\u062f\u0627\u062f \u062f\u0631\u0633\u200c\u0647\u0627\u06cc\u06cc \u06a9\u0647 \u062f\u0631 \u0622\u062e\u0631\u06cc\u0646 sync \u0628\u062f\u0648\u0646 \u062e\u0637\u0627 \u06af\u0631\u0641\u062a\u0647 \u0634\u062f\u0646\u062f.", (counts.successfulCourses || 0) > 0 ? "ok" : ""),
            summaryCard("\u062a\u06a9\u0627\u0644\u06cc\u0641 \u0641\u0639\u0644\u06cc", String(counts.assignments || 0).toLocaleString("fa-IR"), "\u0645\u062c\u0645\u0648\u0639 \u062a\u06a9\u0627\u0644\u06cc\u0641 \u0630\u062e\u06cc\u0631\u0647\u200c\u0634\u062f\u0647 \u062f\u0631 snapshot"),
            summaryCard("\u062f\u0631\u0648\u0633 \u0646\u0627\u0645\u0648\u0641\u0642", String(failedCourses).toLocaleString("fa-IR"), "\u062a\u0627 \u0635\u0641\u0631 \u0646\u0634\u062f\u0646 \u0627\u06cc\u0646 \u0645\u0642\u062f\u0627\u0631\u060c \u062e\u0631\u0648\u062c\u06cc \u0646\u0648\u06cc\u062f \u062a\u0627\u06cc\u06cc\u062f \u0646\u0645\u06cc\u200c\u0634\u0648\u062f.", failedCourses > 0 ? "warn" : "ok"),
            summaryCard("\u0627\u0642\u062f\u0627\u0645 \u0644\u0627\u0632\u0645", navidActionRequiredLabel(actionRequired), "\u0627\u0642\u062f\u0627\u0645\u06cc \u06a9\u0647 \u0628\u0631\u0627\u06cc \u067e\u0627\u06cc\u062f\u0627\u0631\u06cc \u0627\u062a\u0635\u0627\u0644 \u0628\u0627\u06cc\u062f \u0627\u0646\u062c\u0627\u0645 \u0634\u0648\u062f."),
            summaryCard("\u062d\u0633\u0627\u0628 \u0630\u062e\u06cc\u0631\u0647\u200c\u0634\u062f\u0647", config.hasCredentials ? (config.usernameMasked || "\u062b\u0628\u062a \u0634\u062f\u0647") : "\u062b\u0628\u062a \u0646\u0634\u062f\u0647", "\u0646\u0627\u0645 \u06a9\u0627\u0631\u0628\u0631\u06cc \u0631\u0645\u0632\u0646\u06af\u0627\u0631\u06cc\u200c\u0634\u062f\u0647 \u062f\u0631 \u0633\u0631\u0648\u0631"),
            summaryCard("\u0648\u0636\u0639\u06cc\u062a \u0646\u0634\u0633\u062a", session.status || "missing", "\u0622\u062e\u0631\u06cc\u0646 \u0648\u0636\u0639\u06cc\u062a \u062f\u0627\u062f\u0647 \u0646\u0634\u0633\u062a \u0646\u0648\u06cc\u062f"),
            summaryCard("\u0627\u0646\u0642\u0636\u0627\u06cc \u0686\u0627\u0644\u0634", challengeExpiresAtLabel, "\u0627\u06af\u0631 \u06a9\u067e\u0686\u0627\u06cc \u062f\u0633\u062a\u06cc \u0641\u0639\u0627\u0644 \u0628\u0627\u0634\u062f\u060c \u0627\u06cc\u0646 \u0632\u0645\u0627\u0646 \u0628\u0631\u0627\u06cc \u062a\u06a9\u0645\u06cc\u0644 \u0622\u0646 \u0627\u0633\u062a.")
        ].join("");

        if (accountRowNavidMeta) {
            accountRowNavidMeta.textContent = [
                navidStatusResultLabel(state.lastResult || ""),
                navidActionRequiredLabel(actionRequired),
                lastSuccessAtLabel === "—" ? "\u0628\u062f\u0648\u0646 \u0632\u0645\u0627\u0646 \u0645\u0648\u0641\u0642" : lastSuccessAtLabel
            ].join(" \u2022 ");
        }

        if (navidLoginUrlInput) {
            navidLoginUrlInput.value = config.loginUrl || "";
        }
        if (navidSyncIntervalInput) {
            navidSyncIntervalInput.value = String(config.syncIntervalMinutes || 30);
        }
        if (navidCaptchaStrategyInput) {
            navidCaptchaStrategyInput.value = config.captchaStrategy || "python_ocr";
        }
        navidSyncChallengeVisual(ownerStatus, "");
    }

    async function loadNavidOwnerStatus() {
        if (!navidOwnerStatus) {
            return;
        }

        navidState.loading = true;
        navidFeedbackMessage("در حال خواندن وضعیت نوید...", "", true);
        navidRenderOwnerStatus(navidState.ownerStatus);

        var response = await navidGet("ownerStatus");
        navidState.loading = false;

        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
            navidFeedbackMessage("", "");
            return;
        }

        if (!response || !response.success || !response.ownerStatus) {
            navidFeedbackMessage((response && response.error) || "وضعیت نوید خوانده نشد.", "error");
            return;
        }

        navidState.ownerStatus = response.ownerStatus;
        navidState.loaded = true;
        navidRenderOwnerStatus(navidState.ownerStatus);
        navidFeedbackMessage("", "");
    }

    async function saveNavidConfig(event) {
        event.preventDefault();
        if (!navidConfigForm) {
            return;
        }

        var payload = {
            enabled: "1",
            loginUrl: (navidLoginUrlInput && navidLoginUrlInput.value.trim()) || "",
            syncIntervalMinutes: (navidSyncIntervalInput && navidSyncIntervalInput.value.trim()) || "30",
            captchaStrategy: (navidCaptchaStrategyInput && navidCaptchaStrategyInput.value) || "python_ocr",
            username: (navidUsernameInput && navidUsernameInput.value.trim()) || "",
            password: (navidPasswordInput && navidPasswordInput.value.trim()) || ""
        };

        navidFeedbackMessage("در حال ذخیره تنظیمات نوید...", "", true);
        if (navidSyncNowButton) {
            navidSyncNowButton.disabled = true;
        }

        try {
            var response = await navidPost("saveConfig", payload);
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                navidFeedbackMessage("", "");
                return;
            }

            if (!response || !response.success) {
                navidFeedbackMessage((response && response.error) || "ذخیره تنظیمات نوید انجام نشد.", "error");
                return;
            }

            navidState.ownerStatus = response.ownerStatus || navidState.ownerStatus;
            navidRenderOwnerStatus(navidState.ownerStatus);
            navidFeedbackMessage(response.message || "تنظیمات نوید ذخیره شد.", "success");
            if (navidPasswordInput) {
                navidPasswordInput.value = "";
            }
        } finally {
            if (navidSyncNowButton) {
                navidSyncNowButton.disabled = false;
            }
        }
    }

    async function syncNavidNow() {
        if (!navidSyncNowButton) {
            return;
        }

        navidSyncNowButton.disabled = true;
        navidFeedbackMessage("در حال همگام‌سازی فوری نوید...", "", true);
        try {
            var response = await navidPost("syncNow", {});
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                navidFeedbackMessage("", "");
                return;
            }

            if (!response || !response.success) {
                navidFeedbackMessage((response && response.message) || (response && response.error) || "همگام‌سازی نوید موفق نشد.", "error");
            } else {
                navidFeedbackMessage(response.message || "همگام‌سازی نوید انجام شد.", "success");
            }

            navidState.ownerStatus = response.ownerStatus || navidState.ownerStatus;
            navidRenderOwnerStatus(navidState.ownerStatus);
            if (!response || !response.success) {
                navidSyncChallengeVisual(navidState.ownerStatus, response && response.captchaDataUri);
                if (response && response.captchaDataUri) {
                    navidReconnectMessage("\u06a9\u067e\u0686\u0627\u06cc \u0646\u0648\u06cc\u062f \u0622\u0645\u0627\u062f\u0647 \u0627\u0633\u062a. \u06a9\u062f \u0631\u0627 \u0648\u0627\u0631\u062f \u06a9\u0646 \u0648 \u0627\u062a\u0635\u0627\u0644 \u0645\u062c\u062f\u062f \u0631\u0627 \u0628\u0632\u0646.", "error");
                }
            }
        } finally {
            navidSyncNowButton.disabled = false;
        }
    }

    async function loadNavidCaptchaChallenge() {
        if (!navidGetCaptchaButton) {
            return;
        }

        navidGetCaptchaButton.disabled = true;
        navidReconnectMessage("در حال دریافت کپچای نوید...", "", true);
        try {
            var response = await navidPost("captchaChallenge", {});
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                navidReconnectMessage("", "");
                return;
            }

            if (!response || !response.success || !response.captchaDataUri) {
                navidReconnectMessage((response && response.error) || "دریافت کپچا انجام نشد.", "error");
                return;
            }

            navidSyncChallengeVisual(response.ownerStatus || navidState.ownerStatus, response.captchaDataUri);
            navidReconnectMessage("کپچا آماده شد. کد را وارد کن و اتصال مجدد را بزن.", "success");
            navidState.ownerStatus = response.ownerStatus || navidState.ownerStatus;
            navidRenderOwnerStatus(navidState.ownerStatus);
        } finally {
            navidGetCaptchaButton.disabled = false;
        }
    }

    async function completeNavidReconnect() {
        if (!navidCompleteReconnectButton) {
            return;
        }

        var captchaCode = (navidCaptchaCodeInput && navidCaptchaCodeInput.value.trim()) || "";
        if (!captchaCode) {
            navidReconnectMessage("کد کپچا را وارد کن.", "error");
            return;
        }

        navidCompleteReconnectButton.disabled = true;
        navidReconnectMessage("در حال اتصال مجدد نوید...", "", true);
        try {
            var response = await navidPost("completeReconnect", { captchaCode: captchaCode });
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                navidReconnectMessage("", "");
                return;
            }

            if (!response || !response.success) {
                navidReconnectMessage((response && response.error) || "اتصال مجدد نوید انجام نشد.", "error");
                return;
            }

            navidReconnectMessage(response.message || "اتصال مجدد نوید انجام شد.", "success");
            if (response && response.captchaDataUri && navidCaptchaImage) {
                navidCaptchaImage.hidden = false;
                navidCaptchaImage.src = response.captchaDataUri;
            }
            if (navidCaptchaCodeInput) {
                navidCaptchaCodeInput.value = "";
            }
            navidState.ownerStatus = response.ownerStatus || navidState.ownerStatus;
            navidRenderOwnerStatus(navidState.ownerStatus);
        } finally {
            navidCompleteReconnectButton.disabled = false;
        }
    }

    async function loadOwnerUsers() {
        ownerState.loading = true;
        ownerFeedbackMessage("در حال گرفتن فهرست کاربران...", "");
        ownerSummary.innerHTML = summaryCard("کاربر", "…", "در حال بارگذاری داده‌های حساب‌ها");
        representativeList.innerHTML = '<div class="owner-empty">در حال خواندن نماینده‌ها...</div>';
        ownerUserList.innerHTML = '<div class="owner-empty">در حال خواندن فهرست کاربران...</div>';
        if (ownerUserPager) ownerUserPager.innerHTML = "";
        if (accountRowOwnerMeta) {
            accountRowOwnerMeta.textContent = "در حال بارگذاری کاربران...";
        }

        var response = await requestUsers();
        ownerState.loading = false;

        if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
            ownerFeedbackMessage("", "");
            return;
        }

        if (!response || !response.success) {
            ownerFeedbackMessage((response && response.error) || "فهرست کاربران گرفته نشد.", "error");
            return;
        }

        ownerState.viewer = response.viewer || ownerState.viewer || currentUser;
        ownerState.cohorts = Array.isArray(response.cohorts) ? response.cohorts : [];
        ownerState.availableCohorts = Array.isArray(response.availableCohorts) ? response.availableCohorts : [];
        ownerState.activeCohortKey = String(response.activeCohortKey || ownerState.activeCohortKey || (currentUser && currentUser.cohortKey) || "");
        ownerState.rotationCatalog = Array.isArray(response.rotationCatalog) ? response.rotationCatalog : [];
        ownerState.gradeCourses = Array.isArray(response.gradeCourses) ? response.gradeCourses : [];
        var nextUsers = Array.isArray(response.users) ? response.users : [];
        var preservedGrades = {};
        nextUsers.forEach(function (user) {
            var key = String(user.studentNumber || "");
            if (!key) return;
            if (ownerState.gradePayloadByStudent[key]) {
                preservedGrades[key] = ownerState.gradePayloadByStudent[key];
            }
        });
        ownerState.users = nextUsers;
        ownerState.gradePayloadByStudent = preservedGrades;
        if (!ownerState.users.some(function (item) { return item.studentNumber === ownerState.activeUserPanelStudentNumber; })) {
            ownerState.activeUserPanelStudentNumber = "";
        }
        ownerFeedbackMessage("", "");
        syncOwnerRoleOptions();
        updateCreateStudentGroupOptions();
        renderOwnerPanel();
    }

    async function setRepresentative(studentNumber, representative) {
        ownerState.savingStudentNumber = studentNumber;
        ownerFeedbackMessage(representative ? "در حال ثبت نماینده..." : "در حال لغو نقش نماینده...", "");
        renderUsers(ownerState.users);

        try {
            var response = await request("setRepresentative", {
                studentNumber: studentNumber,
                representative: representative ? "1" : "0"
            });

            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                ownerFeedbackMessage("", "");
                return;
            }

            if (!response || !response.success || !response.user) {
                ownerFeedbackMessage((response && response.error) || "ذخیره تغییرات انجام نشد.", "error");
                return;
            }

            ownerState.users = ownerState.users.map(function (user) {
                return user.studentNumber === response.user.studentNumber ? Object.assign({}, user, response.user) : user;
            });

            ownerFeedbackMessage(response.message || "تغییرات ذخیره شد.", "success");
            renderOwnerPanel();
        } finally {
            ownerState.savingStudentNumber = "";
            renderOwnerPanel();
        }
    }

    function upsertOwnerUserRecord(nextUser) {
        if (!nextUser || typeof nextUser !== "object") {
            return;
        }
        var targetStudentNumber = String(nextUser.studentNumber || "");
        if (!targetStudentNumber) {
            return;
        }
        ownerState.users = ownerState.users.map(function (user) {
            if (String(user.studentNumber || "") !== targetStudentNumber) {
                return user;
            }
            return Object.assign({}, user, nextUser);
        });
    }

    async function loadOwnerUserGrades(studentNumber, options) {
        var opts = options && typeof options === "object" ? options : {};
        var targetStudentNumber = String(studentNumber || "").trim();
        if (!targetStudentNumber) {
            return null;
        }

        ownerState.loadingGradesStudentNumber = targetStudentNumber;
        if (!opts.silent) {
            ownerFeedbackMessage("در حال دریافت کارنامه کاربر...", "");
        }
        renderUsers(ownerState.users);

        try {
            var response = await request("ownerUserGrades", { studentNumber: targetStudentNumber });
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                if (!opts.silent) {
                    ownerFeedbackMessage("", "");
                }
                return null;
            }
            if (!response || !response.success || !response.grades) {
                ownerFeedbackMessage((response && response.error) || "خواندن کارنامه کاربر انجام نشد.", "error");
                return null;
            }

            ownerState.gradePayloadByStudent[targetStudentNumber] = response.grades;
            var hasGrades = Array.isArray(response.grades.grades) && response.grades.grades.some(function (grade) {
                return String(grade && grade.value != null ? grade.value : "").trim() !== "";
            });
            ownerState.users = ownerState.users.map(function (user) {
                if (String(user.studentNumber || "") !== targetStudentNumber) {
                    return user;
                }
                return Object.assign({}, user, { hasGrades: hasGrades });
            });
            if (!opts.silent) {
                ownerFeedbackMessage("", "");
            }
            renderOwnerPanel();
            return response.grades;
        } finally {
            ownerState.loadingGradesStudentNumber = "";
            renderUsers(ownerState.users);
        }
    }

    function ownerGradeInputValue(studentNumber, columnIndex) {
        var selector = 'input[data-grade-input="true"][data-student-number="' + String(studentNumber || "") + '"][data-column-index="' + String(columnIndex) + '"]';
        var roots = [ownerUserPanelBody, ownerUserList];
        for (var i = 0; i < roots.length; i++) {
            if (!roots[i]) {
                continue;
            }
            var input = roots[i].querySelector(selector);
            if (input) {
                return input.value.trim();
            }
        }
        return "";
    }

    function ownerPasswordInputValue(studentNumber) {
        var input = ownerSelectNode(studentNumber, 'input[data-owner-password-input="true"]');
        return input ? input.value.trim() : "";
    }

    function ownerRotationFormValue(studentNumber) {
        var modeSelect = ownerSelectNode(studentNumber, 'select[data-owner-rotation-mode="true"]');
        var rotationSelect = ownerSelectNode(studentNumber, 'select[data-owner-rotation-id="true"]');
        var groupSelect = ownerSelectNode(studentNumber, 'select[data-owner-group-number="true"]');
        return {
            mode: modeSelect ? String(modeSelect.value || "none") : "none",
            rotationId: rotationSelect ? String(rotationSelect.value || "") : "",
            groupNumber: groupSelect ? String(groupSelect.value || "") : ""
        };
    }

    async function saveOwnerUserPassword(studentNumber, newPassword) {
        var targetStudentNumber = String(studentNumber || "").trim();
        var password = String(newPassword || "").trim();
        if (!targetStudentNumber) {
            return;
        }
        if (!password || password.length < 6) {
            ownerFeedbackMessage("رمز جدید کاربر باید حداقل ۶ کاراکتر باشد.", "error");
            return;
        }

        ownerState.savingPasswordStudentNumber = targetStudentNumber;
        ownerFeedbackMessage("در حال ذخیره رمز کاربر...", "");
        renderUsers(ownerState.users);

        try {
            var response = await request("ownerSetUserPassword", {
                studentNumber: targetStudentNumber,
                newPassword: password
            });
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                ownerFeedbackMessage("", "");
                return;
            }
            if (!response || !response.success || !response.user) {
                ownerFeedbackMessage((response && response.error) || "ذخیره رمز کاربر انجام نشد.", "error");
                return;
            }

            upsertOwnerUserRecord(response.user);
            ownerFeedbackMessage(response.message || "رمز عبور کاربر ذخیره شد.", "success");
            var passwordInputNode = ownerSelectNode(targetStudentNumber, 'input[data-owner-password-input="true"]');
            if (passwordInputNode) {
                passwordInputNode.value = "";
            }
            renderOwnerPanel();
        } finally {
            ownerState.savingPasswordStudentNumber = "";
            renderUsers(ownerState.users);
        }
    }

    async function saveOwnerUserRotation(studentNumber, mode, rotationId, groupNumber) {
        var targetStudentNumber = String(studentNumber || "").trim();
        var rotationMode = String(mode || "none").trim().toLowerCase();
        var normalizedMode = rotationMode === "auto" ? "none" : rotationMode;
        if (!targetStudentNumber) {
            return;
        }

        if (normalizedMode === "manual" && (!rotationId || !groupNumber)) {
            ownerFeedbackMessage("برای تخصیص دستی، روتیشن و گروه را کامل انتخاب کن.", "error");
            return;
        }

        ownerState.savingRotationStudentNumber = targetStudentNumber;
        ownerFeedbackMessage("در حال ذخیره روتیشن/گروه کاربر...", "");
        renderUsers(ownerState.users);

        try {
            var response = await request("ownerSetUserRotation", {
                studentNumber: targetStudentNumber,
                rotationMode: normalizedMode || "none",
                rotationId: rotationId || "",
                groupNumber: groupNumber || ""
            });
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                ownerFeedbackMessage("", "");
                return;
            }
            if (!response || !response.success || !response.user) {
                ownerFeedbackMessage((response && response.error) || "ذخیره روتیشن/گروه انجام نشد.", "error");
                return;
            }

            upsertOwnerUserRecord(response.user);
            ownerFeedbackMessage(response.message || "روتیشن/گروه کاربر ذخیره شد.", "success");
            renderOwnerPanel();
        } finally {
            ownerState.savingRotationStudentNumber = "";
            renderUsers(ownerState.users);
        }
    }

    async function markCampusStudents() {
        if (ownerState.campusMarking) {
            return;
        }

        ownerState.campusMarking = true;
        if (ownerMarkCampusStudentsButton) {
            ownerMarkCampusStudentsButton.disabled = true;
        }
        ownerFeedbackMessage("در حال تبدیل دانشجوهای فاقد گروه/روتیشن به دانشجوی پردیس...", "");
        try {
            var response = await request("ownerMarkCampusStudents", {});
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                ownerFeedbackMessage("", "");
                return;
            }
            if (!response || !response.success) {
                ownerFeedbackMessage((response && response.error) || "تخصیص دانشجوی پردیس انجام نشد.", "error");
                return;
            }

            var updatedUsers = Array.isArray(response.updatedUsers) ? response.updatedUsers : [];
            updatedUsers.forEach(function (item) {
                upsertOwnerUserRecord(item);
            });

            ownerFeedbackMessage((response.message || "تخصیص دانشجوی پردیس انجام شد.") + " (" + Number(response.updatedCount || 0).toLocaleString("fa-IR") + " کاربر)", "success");
            renderOwnerPanel();
        } finally {
            ownerState.campusMarking = false;
            if (ownerMarkCampusStudentsButton) {
                ownerMarkCampusStudentsButton.disabled = ownerState.creatingStudent;
            }
        }
    }

    async function reloadOwnerAfterGradebookMutation() {
        ownerState.gradePayloadByStudent = {};
        await loadOwnerUsers();
        if (ownerState.activeUserPanelStudentNumber) {
            await loadOwnerUserGrades(ownerState.activeUserPanelStudentNumber, { silent: true });
        }
    }

    async function importOwnerGrades(event) {
        if (event) {
            event.preventDefault();
        }
        if (!ownerGradesImportForm || ownerState.importingGrades) {
            return;
        }

        var formData = new FormData();
        var text = ownerGradesImportText ? ownerGradesImportText.value.trim() : "";
        var file = ownerGradesImportFile && ownerGradesImportFile.files ? ownerGradesImportFile.files[0] : null;
        if (!text && !file) {
            ownerGradesFeedbackMessage("متن import یا فایل نمرات را وارد کن.", "error");
            return;
        }
        if (text) {
            formData.set("importText", text);
        }
        if (file) {
            formData.set("gradesFile", file);
        }
        if (ownerActiveCohortKey()) {
            formData.set("cohortKey", ownerActiveCohortKey());
            formData.set("cohort", ownerActiveCohortKey());
        }

        ownerState.importingGrades = true;
        renderOwnerGradeManager();
        ownerGradesFeedbackMessage("در حال import نمرات...", "", true);

        try {
            var response = await requestFormData("ownerImportGrades", formData);
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                ownerGradesFeedbackMessage("", "");
                return;
            }
            if (!response || !response.success) {
                ownerGradesFeedbackMessage((response && response.error) || "Import نمرات انجام نشد.", "error");
                return;
            }

            if (ownerGradesImportText) {
                ownerGradesImportText.value = "";
            }
            if (ownerGradesImportFile) {
                ownerGradesImportFile.value = "";
            }
            ownerState.gradeCourses = Array.isArray(response.courses) ? response.courses : ownerState.gradeCourses;
            ownerGradesFeedbackMessage(
                (response.message || "Import نمرات انجام شد.") + " " +
                Number(response.importedCount || 0).toLocaleString("fa-IR") + " ردیف ثبت شد.",
                "success"
            );
            await reloadOwnerAfterGradebookMutation();
        } finally {
            ownerState.importingGrades = false;
            renderOwnerGradeManager();
        }
    }

    async function deleteOwnerGradeCourse() {
        if (ownerState.deletingGradeCourseKey || ownerState.resettingGrades || !ownerGradesCourseSelect) {
            return;
        }

        var courseKey = String(ownerGradesCourseSelect.value || "");
        if (!courseKey) {
            ownerGradesFeedbackMessage("اول یک درس را انتخاب کن.", "error");
            return;
        }

        var label = ownerGradesCourseSelect.options[ownerGradesCourseSelect.selectedIndex]
            ? ownerGradesCourseSelect.options[ownerGradesCourseSelect.selectedIndex].textContent
            : "درس انتخاب‌شده";
        if (!window.confirm("همه نمرات «" + label + "» برای همه کاربران حذف شود؟")) {
            return;
        }

        ownerState.deletingGradeCourseKey = courseKey;
        renderOwnerGradeManager();
        ownerGradesFeedbackMessage("در حال حذف درس از کارنامه همه کاربران...", "", true);

        try {
            var response = await request("ownerDeleteGradeCourse", { courseKey: courseKey, cohortKey: ownerActiveCohortKey(), cohort: ownerActiveCohortKey() });
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                ownerGradesFeedbackMessage("", "");
                return;
            }
            if (!response || !response.success) {
                ownerGradesFeedbackMessage((response && response.error) || "حذف درس انجام نشد.", "error");
                return;
            }

            ownerState.gradeCourses = Array.isArray(response.courses) ? response.courses : [];
            ownerGradesFeedbackMessage(response.message || "درس حذف شد.", "success");
            await reloadOwnerAfterGradebookMutation();
        } finally {
            ownerState.deletingGradeCourseKey = "";
            renderOwnerGradeManager();
        }
    }

    async function resetOwnerGradebook() {
        if (ownerState.resettingGrades) {
            return;
        }

        var confirmation = window.prompt("برای ریست کامل همه درس‌ها و نمرات، عبارت RESET را وارد کن.");
        if (confirmation !== "RESET") {
            ownerGradesFeedbackMessage("ریست کارنامه لغو شد.", "");
            return;
        }

        ownerState.resettingGrades = true;
        renderOwnerGradeManager();
        ownerGradesFeedbackMessage("در حال ریست کامل کارنامه...", "", true);

        try {
            var response = await request("ownerResetGradebook", { confirm: "RESET", cohortKey: ownerActiveCohortKey(), cohort: ownerActiveCohortKey() });
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                ownerGradesFeedbackMessage("", "");
                return;
            }
            if (!response || !response.success) {
                ownerGradesFeedbackMessage((response && response.error) || "ریست کارنامه انجام نشد.", "error");
                return;
            }

            ownerState.gradeCourses = [];
            ownerGradesFeedbackMessage(response.message || "کارنامه ریست شد.", "success");
            await reloadOwnerAfterGradebookMutation();
        } finally {
            ownerState.resettingGrades = false;
            renderOwnerGradeManager();
        }
    }

    async function saveOwnerUserGrade(studentNumber, columnIndex, gradeValue) {
        var targetStudentNumber = String(studentNumber || "").trim();
        var targetColumnIndex = Math.floor(toNumber(columnIndex, -1));
        if (!targetStudentNumber || targetColumnIndex < 0) {
            return;
        }

        ownerState.savingGradeKey = ownerGradeSaveKey(targetStudentNumber, targetColumnIndex);
        ownerFeedbackMessage("در حال ذخیره نمره...", "");
        renderUsers(ownerState.users);

        try {
            var response = await request("ownerSetUserGrade", {
                studentNumber: targetStudentNumber,
                columnIndex: String(targetColumnIndex),
                gradeValue: String(gradeValue == null ? "" : gradeValue).trim()
            });
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                ownerFeedbackMessage("", "");
                return;
            }
            if (!response || !response.success || !response.grades) {
                ownerFeedbackMessage((response && response.error) || "ذخیره نمره انجام نشد.", "error");
                return;
            }

            ownerState.gradePayloadByStudent[targetStudentNumber] = response.grades;
            upsertOwnerUserRecord(response.user || {});
            ownerFeedbackMessage(response.message || "نمره ذخیره شد.", "success");
            renderOwnerPanel();
        } finally {
            ownerState.savingGradeKey = "";
            renderUsers(ownerState.users);
        }
    }

    async function removeOwnerUserPhone(studentNumber) {
        var targetStudentNumber = String(studentNumber || "").trim();
        if (!targetStudentNumber) {
            return;
        }

        var targetUser = ownerState.users.find(function (item) {
            return String(item.studentNumber || "") === targetStudentNumber;
        }) || null;
        if (!targetUser || !targetUser.hasPhone || isOwnerUser(targetUser)) {
            return;
        }

        var confirmText = "شماره ثبت‌شده کاربر «" + (targetUser.name || targetStudentNumber) + "» حذف شود؟";
        if (!window.confirm(confirmText)) {
            return;
        }

        ownerState.removingPhoneStudentNumber = targetStudentNumber;
        ownerFeedbackMessage("در حال حذف شماره ثبت‌شده کاربر...", "");
        renderUsers(ownerState.users);

        try {
            var response = await request("ownerRemoveUserPhone", { studentNumber: targetStudentNumber });
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                ownerFeedbackMessage("", "");
                return;
            }
            if (!response || !response.success || !response.user) {
                ownerFeedbackMessage((response && response.error) || "حذف شماره کاربر انجام نشد.", "error");
                return;
            }
            upsertOwnerUserRecord(response.user);
            ownerFeedbackMessage(response.message || "شماره کاربر حذف شد.", "success");
            renderOwnerPanel();
        } finally {
            ownerState.removingPhoneStudentNumber = "";
            renderUsers(ownerState.users);
        }
    }

    async function clearOwnerUserExamStudy(studentNumber) {
        var targetStudentNumber = String(studentNumber || "").trim();
        if (!targetStudentNumber || ownerState.clearingExamStudyStudentNumber) {
            return;
        }
        if (!window.confirm("یادداشت‌ها، هایلایت‌ها، گزینه‌های خط‌خورده و دفترچه اشتباهات این کاربر پاک شود؟ تاریخچه تلاش‌ها و کارنامه‌ها حفظ می‌شوند.")) {
            return;
        }
        ownerState.clearingExamStudyStudentNumber = targetStudentNumber;
        ownerUserPanelFeedbackMessage("در حال پاک‌سازی داده‌های مطالعه آزمون...", "");
        renderUsers(ownerState.users);
        try {
            var response = await request("ownerClearUserExamStudy", { studentNumber: targetStudentNumber });
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                return;
            }
            if (!response || !response.success) {
                ownerUserPanelFeedbackMessage((response && response.error) || "پاک‌سازی داده‌های مطالعه انجام نشد.", "error");
                return;
            }
            ownerState.users = ownerState.users.map(function (user) {
                if (String(user.studentNumber || "") !== targetStudentNumber) {
                    return user;
                }
                return Object.assign({}, user, { examLearningSummary: response.examLearningSummary || {} });
            });
            ownerUserPanelFeedbackMessage(response.message || "داده‌های مطالعه آزمون پاک شد.", "success");
        } finally {
            ownerState.clearingExamStudyStudentNumber = "";
            renderUsers(ownerState.users);
        }
    }

    async function deleteOwnerStudentAccount(studentNumber) {
        var targetStudentNumber = String(studentNumber || "").trim();
        if (!targetStudentNumber) {
            return;
        }

        var targetUser = ownerState.users.find(function (item) {
            return String(item.studentNumber || "") === targetStudentNumber;
        }) || null;
        if (!targetUser || isOwnerUser(targetUser)) {
            return;
        }

        var confirmText = "حساب دانشجو «" + (targetUser.name || targetStudentNumber) + "» به‌طور کامل حذف شود؟ این عمل قابل بازگشت نیست.";
        if (!window.confirm(confirmText)) {
            return;
        }

        ownerState.deletingStudentNumber = targetStudentNumber;
        ownerFeedbackMessage("در حال حذف دانشجو...", "");
        renderUsers(ownerState.users);

        try {
            var response = await request("ownerDeleteStudent", { studentNumber: targetStudentNumber });
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                ownerFeedbackMessage("", "");
                return;
            }
            if (!response || !response.success) {
                ownerFeedbackMessage((response && response.error) || "حذف دانشجو انجام نشد.", "error");
                return;
            }

            ownerState.users = ownerState.users.filter(function (item) {
                return String(item.studentNumber || "") !== targetStudentNumber;
            });
            delete ownerState.gradePayloadByStudent[targetStudentNumber];
            if (ownerState.activeUserPanelStudentNumber === targetStudentNumber) {
                ownerState.activeUserPanelStudentNumber = "";
                openSurface("owner", { replaceHash: true });
            }
            ownerFeedbackMessage(response.message || "حساب دانشجو حذف شد.", "success");
            renderOwnerPanel();
        } finally {
            ownerState.deletingStudentNumber = "";
            ownerState.clearingExamStudyStudentNumber = "";
            renderUsers(ownerState.users);
        }
    }

    function openOwnerUserPanel(studentNumber) {
        var targetStudentNumber = String(studentNumber || "").trim();
        if (!targetStudentNumber) {
            return;
        }

        ownerState.activeUserPanelStudentNumber = targetStudentNumber;
        openSurface("owner-user", { replaceHash: false });
        renderUsers(ownerState.users);
        if (!ownerGradesPayload(targetStudentNumber)) {
            loadOwnerUserGrades(targetStudentNumber, { silent: true });
        }
    }

    function ownerSelectNode(studentNumber, selector) {
        var suffix = selector + '[data-student-number="' + String(studentNumber || "") + '"]';
        var roots = [ownerUserPanelBody, ownerUserList];
        for (var i = 0; i < roots.length; i++) {
            if (!roots[i]) {
                continue;
            }
            var node = roots[i].querySelector(suffix);
            if (node) {
                return node;
            }
        }
        return null;
    }

    function syncOwnerGroupOptions(studentNumber) {
        var targetStudentNumber = String(studentNumber || "").trim();
        if (!targetStudentNumber) {
            return;
        }

        var modeSelect = ownerSelectNode(targetStudentNumber, 'select[data-owner-rotation-mode="true"]');
        var rotationSelect = ownerSelectNode(targetStudentNumber, 'select[data-owner-rotation-id="true"]');
        var groupSelect = ownerSelectNode(targetStudentNumber, 'select[data-owner-group-number="true"]');
        if (!modeSelect || !rotationSelect || !groupSelect) {
            return;
        }

        var mode = String(modeSelect.value || "none");
        if (mode !== "manual") {
            rotationSelect.value = "";
        }
        var selectedGroupValue = String(groupSelect.value || "");
        var options = ownerRotationOptions(rotationSelect.value);

        groupSelect.innerHTML = "";
        if (!options.length) {
            var emptyOption = document.createElement("option");
            emptyOption.value = "";
            emptyOption.textContent = "ابتدا روتیشن را انتخاب کنید";
            emptyOption.selected = true;
            groupSelect.appendChild(emptyOption);
        } else {
            var placeholder = document.createElement("option");
            placeholder.value = "";
            placeholder.textContent = "انتخاب گروه";
            groupSelect.appendChild(placeholder);

            options.forEach(function (item) {
                var option = document.createElement("option");
                option.value = String(item.groupNumber);
                option.textContent = item.groupLabel + (item.groupTitle ? (" (" + item.groupTitle + ")") : "");
                option.selected = option.value === selectedGroupValue;
                groupSelect.appendChild(option);
            });
        }

        var disabled = mode !== "manual";
        if (disabled) {
            groupSelect.value = "";
        }
        rotationSelect.disabled = disabled;
        groupSelect.disabled = disabled;
    }

    function updateCreateStudentGroupOptions() {
        if (!ownerStudentGroupNumber || !ownerStudentRotationId || !ownerStudentRotationMode) {
            return;
        }

        var activeCohort = ownerActiveCohortRecord();
        var supportsRotation = ownerCohortSupportsRotationGroups(activeCohort);
        if (!supportsRotation) {
            ownerStudentRotationMode.value = "none";
            ownerStudentRotationId.value = "";
        }
        var mode = String(ownerStudentRotationMode.value || "none");
        if (mode !== "manual") {
            ownerStudentRotationId.value = "";
        }
        var options = ownerRotationOptions(ownerStudentRotationId.value);

        ownerStudentGroupNumber.innerHTML = "";
        if (!options.length) {
            var emptyOption = document.createElement("option");
            emptyOption.value = "";
            emptyOption.textContent = "ابتدا روتیشن را انتخاب کنید";
            emptyOption.selected = true;
            ownerStudentGroupNumber.appendChild(emptyOption);
        } else {
            var placeholder = document.createElement("option");
            placeholder.value = "";
            placeholder.textContent = "انتخاب گروه";
            ownerStudentGroupNumber.appendChild(placeholder);
            options.forEach(function (item) {
                var option = document.createElement("option");
                option.value = String(item.groupNumber);
                option.textContent = item.groupLabel + (item.groupTitle ? (" (" + item.groupTitle + ")") : "");
                ownerStudentGroupNumber.appendChild(option);
            });
        }

        var manual = mode === "manual";
        if (!manual) {
            ownerStudentGroupNumber.value = "";
        }
        if (ownerStudentRotationMode) {
            ownerStudentRotationMode.disabled = ownerState.creatingStudent || !supportsRotation;
        }
        ownerStudentRotationId.disabled = !manual || ownerState.creatingStudent || !supportsRotation;
        ownerStudentGroupNumber.disabled = !manual || ownerState.creatingStudent || !supportsRotation;
        if (ownerMarkCampusStudentsButton) {
            ownerMarkCampusStudentsButton.hidden = !supportsRotation;
        }
    }

    function syncOwnerRoleOptions() {
        if (!ownerStudentRole) {
            return;
        }
        var activeCohort = ownerActiveCohortRecord();
        var isProsthesis = !!(activeCohort && activeCohort.productType === "prosthesis");
        var allowRepresentative = !!(activeCohort && activeCohort.allowRepresentativeManagement);
        var current = String(ownerStudentRole.value || "");
        var options = isProsthesis
            ? [
                { value: "prosthesis_student", label: "دانشجوی پروتز" },
                { value: "prosthesis_representative", label: "نماینده پروتز" }
            ]
            : [
                { value: "student", label: "دانشجو" },
                { value: "representative", label: "نماینده" }
            ];

        ownerStudentRole.innerHTML = "";
        options.forEach(function (item) {
            if (!allowRepresentative && String(item.value).indexOf("representative") >= 0 && !hasOwnerAccess()) {
                return;
            }
            var option = document.createElement("option");
            option.value = item.value;
            option.textContent = item.label;
            option.selected = item.value === current;
            ownerStudentRole.appendChild(option);
        });
        if (!ownerStudentRole.value && ownerStudentRole.options.length) {
            ownerStudentRole.selectedIndex = 0;
        }
    }

    async function setOwnerActiveCohort(cohortKey) {
        var next = String(cohortKey || "").trim();
        if (!next || next === ownerState.activeCohortKey) {
            return;
        }
        ownerState.activeCohortKey = next;
        ownerState.activeUserPanelStudentNumber = "";
        ownerState.userPage = 1;
        ownerCohortFeedbackMessage("در حال بارگذاری ورودی انتخاب‌شده...", "", true);
        syncOwnerRoleOptions();
        updateCreateStudentGroupOptions();
        await loadOwnerUsers();
        ownerCohortFeedbackMessage("", "");
    }

    function ownerCohortServiceInputs() {
        return [
            ownerCohortServiceNotes,
            ownerCohortServiceForms,
            ownerCohortServiceGrades,
            ownerCohortServiceNavid,
            ownerCohortServiceBuy
        ];
    }

    function ownerApplyCohortToggle(node, checked, force) {
        if (!node) {
            return;
        }
        if (!force && node.dataset.userTouched === "1") {
            return;
        }
        node.checked = !!checked;
    }

    function syncOwnerCohortServiceDefaults(force) {
        var productType = ownerCohortProductType ? String(ownerCohortProductType.value || "dentistry") : "dentistry";
        var notesMode = ownerCohortNotesMode ? String(ownerCohortNotesMode.value || "archive") : "archive";
        var isSiteUsers = productType === "site-users";
        ownerApplyCohortToggle(ownerCohortServiceNotes, !isSiteUsers && notesMode !== "none", force);
        ownerApplyCohortToggle(ownerCohortServiceForms, !isSiteUsers, force);
        ownerApplyCohortToggle(ownerCohortServiceGrades, !isSiteUsers, force);
        ownerApplyCohortToggle(ownerCohortServiceNavid, !isSiteUsers, force);
        ownerApplyCohortToggle(ownerCohortServiceBuy, isSiteUsers, force);
        ownerApplyCohortToggle(ownerCohortAllowRepresentative, !isSiteUsers, force);
    }

    function resetOwnerCohortDefaults() {
        ownerCohortServiceInputs().forEach(function (node) {
            if (node) {
                delete node.dataset.userTouched;
            }
        });
        if (ownerCohortAllowRepresentative) {
            delete ownerCohortAllowRepresentative.dataset.userTouched;
        }
        syncOwnerCohortServiceDefaults(true);
    }

    function setCreateCohortBusy(isBusy) {
        ownerState.creatingCohort = !!isBusy;
        [ownerCohortTitle, ownerCohortShortTitle, ownerCohortYear, ownerCohortProductType, ownerCohortNotesMode, ownerCohortAllowRepresentative]
            .concat(ownerCohortServiceInputs())
            .forEach(function (node) {
            if (node) {
                node.disabled = ownerState.creatingCohort;
            }
            });
        if (ownerCreateCohortSubmit) {
            ownerCreateCohortSubmit.disabled = ownerState.creatingCohort;
        }
    }

    async function createCohort(event) {
        if (event) {
            event.preventDefault();
        }
        if (!ownerCreateCohortForm || ownerState.creatingCohort) {
            return;
        }

        var title = ownerCohortTitle ? ownerCohortTitle.value.trim() : "";
        var shortTitle = ownerCohortShortTitle ? ownerCohortShortTitle.value.trim() : "";
        var year = ownerCohortYear ? ownerCohortYear.value.trim() : "";
        if (!title || !year) {
            ownerCreateCohortFeedbackMessage("عنوان و سال ورودی را کامل کن.", "error");
            return;
        }

        setCreateCohortBusy(true);
        ownerCreateCohortFeedbackMessage("در حال ساخت ورودی جدید...", "", true);
        try {
            var response = await request("createCohort", {
                title: title,
                shortTitle: shortTitle,
                year: year,
                productType: ownerCohortProductType ? ownerCohortProductType.value : "dentistry",
                notesMode: ownerCohortNotesMode ? ownerCohortNotesMode.value : "archive",
                allowRepresentativeManagement: ownerCohortAllowRepresentative && ownerCohortAllowRepresentative.checked ? "1" : "0",
                serviceNotes: ownerCohortServiceNotes && ownerCohortServiceNotes.checked ? "1" : "0",
                serviceForms: ownerCohortServiceForms && ownerCohortServiceForms.checked ? "1" : "0",
                serviceGrades: ownerCohortServiceGrades && ownerCohortServiceGrades.checked ? "1" : "0",
                serviceNavid: ownerCohortServiceNavid && ownerCohortServiceNavid.checked ? "1" : "0",
                serviceBuy: ownerCohortServiceBuy && ownerCohortServiceBuy.checked ? "1" : "0"
            });
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                ownerCreateCohortFeedbackMessage("", "");
                return;
            }
            if (!response || !response.success || !response.cohort) {
                ownerCreateCohortFeedbackMessage((response && response.error) || "ساخت ورودی انجام نشد.", "error");
                return;
            }

            ownerCreateCohortForm.reset();
            resetOwnerCohortDefaults();
            ownerCreateCohortFeedbackMessage(response.message || "ورودی جدید ساخته شد.", "success");
            ownerState.activeCohortKey = String(response.cohort.key || ownerState.activeCohortKey || "");
            await loadOwnerUsers();
        } finally {
            setCreateCohortBusy(false);
        }
    }

    function setImportUsersBusy(isBusy) {
        ownerState.importingUsers = !!isBusy;
        [ownerImportUsersDefaultPassword, ownerImportUsersText, ownerImportUsersFile].forEach(function (node) {
            if (node) {
                node.disabled = ownerState.importingUsers;
            }
        });
        if (ownerImportUsersSubmit) {
            ownerImportUsersSubmit.disabled = ownerState.importingUsers;
        }
    }

    async function importCohortUsers(event) {
        if (event) {
            event.preventDefault();
        }
        if (!ownerImportUsersForm || ownerState.importingUsers) {
            return;
        }

        var importText = ownerImportUsersText ? ownerImportUsersText.value.trim() : "";
        var importFile = ownerImportUsersFile && ownerImportUsersFile.files ? ownerImportUsersFile.files[0] : null;
        var defaultPassword = ownerImportUsersDefaultPassword ? ownerImportUsersDefaultPassword.value.trim() : "";
        if (!importText && !importFile) {
            ownerImportUsersFeedbackMessage("متن یا فایل ورود گروهی را وارد کن.", "error");
            return;
        }
        if (!defaultPassword || defaultPassword.length < 6) {
            ownerImportUsersFeedbackMessage("رمز پیش‌فرض باید حداقل ۶ کاراکتر باشد.", "error");
            return;
        }

        setImportUsersBusy(true);
        ownerImportUsersFeedbackMessage("در حال ساخت گروهی کاربران...", "", true);
        try {
            var response;
            if (importFile) {
                var formData = new FormData();
                formData.set("cohortKey", ownerActiveCohortKey());
                formData.set("cohort", ownerActiveCohortKey());
                formData.set("defaultPassword", defaultPassword);
                if (importText) {
                    formData.set("importText", importText);
                }
                formData.set("usersFile", importFile);
                response = await requestFormData("importCohortUsers", formData);
            } else {
                response = await request("importCohortUsers", {
                    cohortKey: ownerActiveCohortKey(),
                    cohort: ownerActiveCohortKey(),
                    defaultPassword: defaultPassword,
                    importText: importText
                });
            }
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                ownerImportUsersFeedbackMessage("", "");
                return;
            }
            if (!response || !response.success) {
                ownerImportUsersFeedbackMessage((response && response.error) || "ورود گروهی انجام نشد.", "error");
                return;
            }

            if (ownerImportUsersText) {
                ownerImportUsersText.value = "";
            }
            if (ownerImportUsersFile) {
                ownerImportUsersFile.value = "";
            }
            ownerImportUsersFeedbackMessage((response.message || "ورود گروهی انجام شد.") + " " + Number(response.count || 0).toLocaleString("fa-IR") + " کاربر.", "success");
            await loadOwnerUsers();
        } finally {
            setImportUsersBusy(false);
        }
    }

    function setCreateStudentBusy(isBusy) {
        ownerState.creatingStudent = !!isBusy;
        if (ownerCreateStudentSubmit) {
            ownerCreateStudentSubmit.disabled = ownerState.creatingStudent;
        }
        if (ownerMarkCampusStudentsButton) {
            ownerMarkCampusStudentsButton.disabled = ownerState.creatingStudent || ownerState.campusMarking;
        }
        [ownerStudentFirstName, ownerStudentLastName, ownerStudentNumber, ownerStudentPassword, ownerStudentRole, ownerStudentRotationMode, ownerStudentRotationId, ownerStudentGroupNumber].forEach(function (node) {
            if (node) {
                node.disabled = ownerState.creatingStudent;
            }
        });
        updateCreateStudentGroupOptions();
    }

    async function createStudentAccount(event) {
        event.preventDefault();
        if (!ownerCreateStudentForm) {
            return;
        }

        var firstName = ownerStudentFirstName ? ownerStudentFirstName.value.trim() : "";
        var lastName = ownerStudentLastName ? ownerStudentLastName.value.trim() : "";
        var studentNumber = ownerStudentNumber ? ownerStudentNumber.value.trim() : "";
        var password = ownerStudentPassword ? ownerStudentPassword.value.trim() : "";
        var role = ownerStudentRole ? String(ownerStudentRole.value || "student") : "student";
        var rotationMode = ownerStudentRotationMode ? String(ownerStudentRotationMode.value || "none") : "none";
        var rotationId = ownerStudentRotationId ? String(ownerStudentRotationId.value || "") : "";
        var groupNumber = ownerStudentGroupNumber ? String(ownerStudentGroupNumber.value || "") : "";
        var cohortKey = ownerActiveCohortKey();

        if (!cohortKey) {
            ownerCreateStudentFeedbackMessage("ابتدا یک ورودی فعال انتخاب کن.", "error");
            return;
        }

        if (!firstName || !lastName || !studentNumber || !password) {
            ownerCreateStudentFeedbackMessage("همه فیلدها را کامل وارد کن.", "error");
            return;
        }

        if (password.length < 6) {
            ownerCreateStudentFeedbackMessage("رمز عبور باید حداقل ۶ کاراکتر باشد.", "error");
            return;
        }

        if (rotationMode === "manual" && (!rotationId || !groupNumber)) {
            ownerCreateStudentFeedbackMessage("برای تخصیص دستی، روتیشن و گروه را انتخاب کن.", "error");
            return;
        }

        setCreateStudentBusy(true);
        ownerCreateStudentFeedbackMessage("در حال ایجاد حساب دانشجو...", "", true);
        try {
            var response = await request("createStudent", {
                firstName: firstName,
                lastName: lastName,
                studentNumber: studentNumber,
                password: password,
                cohortKey: cohortKey,
                cohort: cohortKey,
                role: role,
                rotationMode: rotationMode,
                rotationId: rotationId,
                groupNumber: groupNumber
            });

            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                ownerCreateStudentFeedbackMessage("", "");
                return;
            }

            if (!response || !response.success || !response.user) {
                ownerCreateStudentFeedbackMessage((response && response.error) || "ایجاد حساب دانشجو انجام نشد.", "error");
                return;
            }

            if (ownerCreateStudentForm) {
                ownerCreateStudentForm.reset();
            }
            updateCreateStudentGroupOptions();
            ownerCreateStudentFeedbackMessage(response.message || "حساب دانشجو ایجاد شد.", "success");
            await loadOwnerUsers();
        } finally {
            setCreateStudentBusy(false);
        }
    }

    function phoneEnrollFeedbackMessage(text, kind, loading) {
        setFeedback(phoneEnrollFeedback, text, kind, loading);
    }

    function phoneToggleFeedbackMessage(text, kind, loading) {
        setFeedback(phoneToggleFeedback, text, kind, loading);
    }

    function phoneManageFeedbackMessage(text, kind, loading) {
        setFeedback(phoneManageFeedback, text, kind, loading);
    }

    function applyPhoneDetailsFromCurrentUser() {
        var user = currentUser || {};
        renderPhoneSecurityState(user);
    }

    async function requestLoginOtpCode() {
        if (loginOtpRequesting) return;
        if (!loginPhoneInput) return;
        var phoneNumber = normalizedPhone(loginPhoneInput.value);
        if (!isValidIranMobile(phoneNumber)) {
            setFieldError(loginPhoneInput, loginPhoneError, "شماره موبایل معتبر وارد کن.");
            updateLoginOtpRequestState();
            loginPhoneInput.focus({ preventScroll: true });
            return;
        }
        setNumericDisplayValue(loginPhoneInput, phoneNumber);
        setFieldError(loginPhoneInput, loginPhoneError, "");
        setFieldError(loginOtpCodeInput, loginOtpCodeError, "");
        setLoginOtpVerifyVisible(false);
        if (loginOtpCodeInput) {
            loginOtpCodeInput.value = "";
            loginOtpCodeInput.dispatchEvent(new Event("input", { bubbles: true }));
        }
        loginOtpRequesting = true;
        updateLoginOtpRequestState();
        setFeedback(loginOtpFeedback, "در حال ارسال کد تایید...", "", true);
        try {
            var auth = window.Dent1402Auth;
            var response = await auth.requestLoginOtp(phoneNumber);
            if (!response || !response.success) {
                if (response && response.cooldownSeconds) {
                    startLoginOtpCooldown(response.cooldownSeconds);
                }
                stopOtpCredentialRead();
                setLoginOtpVerifyVisible(false);
                setFeedback(loginOtpFeedback, (response && response.error) || "ارسال کد تایید انجام نشد.", "error");
                return;
            }

            startLoginOtpCooldown(response.cooldownSeconds || 0);
            updateLoginOtpPhoneDisplay(phoneNumber, response && response.phoneMasked);
            setLoginOtpVerifyVisible(true);
            setFeedback(loginOtpFeedback, "", "");
            if (loginOtpCodeInput) {
                focusLoginOtpInput(false);
                startOtpCredentialRead(loginOtpCodeInput);
            }
        } finally {
            loginOtpRequesting = false;
            updateLoginOtpCooldownUi();
            updateLoginOtpSubmitState();
        }
    }

    async function submitOtpLogin(event) {
        if (event && event.preventDefault) {
            event.preventDefault();
        }
        if (loginOtpSubmitting) return;
        if (!loginPhoneInput || !loginOtpCodeInput) return;
        var phoneNumber = normalizedPhone(loginPhoneInput.value);
        var otpCode = loginOtpCodeValue();
        var expectedLength = loginOtpLength();
        if (!isValidIranMobile(phoneNumber)) {
            setFieldError(loginPhoneInput, loginPhoneError, "شماره موبایل معتبر وارد کن.");
            setLoginOtpVerifyVisible(false);
            loginPhoneInput.focus({ preventScroll: true });
            return;
        }
        if (otpCode.length !== expectedLength) {
            setFieldError(loginOtpCodeInput, loginOtpCodeError, "کد تایید را کامل وارد کن.");
            updateLoginOtpSubmitState();
            focusLoginOtpInput(false);
            return;
        }

        setFieldError(loginOtpCodeInput, loginOtpCodeError, "");
        loginOtpSubmitting = true;
        updateLoginOtpSubmitState();
        stopOtpCredentialRead();
        setFeedback(loginOtpFeedback, "در حال ورود با کد تایید...", "", true);
        try {
            var state = await window.Dent1402Auth.loginWithOtp(phoneNumber, otpCode);
            if (!state || !state.loggedIn) {
                setFieldError(loginOtpCodeInput, loginOtpCodeError, "کد تایید صحیح نیست یا منقضی شده است.");
                setFeedback(loginOtpFeedback, (state && state.error) || "ورود با کد تایید انجام نشد.", "error");
                focusLoginOtpInput(true);
                return;
            }
            setFeedback(loginOtpFeedback, "ورود با کد تایید انجام شد.", "success");
        } finally {
            loginOtpSubmitting = false;
            updateLoginOtpSubmitState();
        }
    }

    async function requestExternalSignupOtpCode() {
        if (externalSignupRequesting) return;
        var payload = externalSignupPayload();
        if (!payload.firstName || !payload.lastName) {
            setFeedback(externalSignupFeedback, "نام و نام خانوادگی را کامل وارد کن.", "error");
            updateExternalSignupState();
            return;
        }
        if (!isValidIranMobile(payload.phoneNumber)) {
            setFeedback(externalSignupFeedback, "شماره موبایل معتبر وارد کن.", "error");
            if (externalSignupPhone) externalSignupPhone.focus({ preventScroll: true });
            updateExternalSignupState();
            return;
        }
        if (payload.password.length < 6) {
            setFeedback(externalSignupFeedback, "رمز عبور باید حداقل ۶ کاراکتر باشد.", "error");
            if (externalSignupPassword) externalSignupPassword.focus({ preventScroll: true });
            updateExternalSignupState();
            return;
        }
        if (payload.passwordConfirm.length < 6) {
            setFeedback(externalSignupFeedback, "تکرار رمز عبور را وارد کن.", "error");
            if (externalSignupPasswordConfirm) externalSignupPasswordConfirm.focus({ preventScroll: true });
            updateExternalSignupState();
            return;
        }
        if (payload.password !== payload.passwordConfirm) {
            setFeedback(externalSignupFeedback, "تکرار رمز عبور با رمز عبور یکسان نیست.", "error");
            if (externalSignupPasswordConfirm) externalSignupPasswordConfirm.focus({ preventScroll: true });
            updateExternalSignupState();
            return;
        }

        if (externalSignupPhone) {
            setNumericDisplayValue(externalSignupPhone, payload.phoneNumber);
        }
        setExternalSignupVerifyVisible(false);
        externalSignupRequesting = true;
        updateExternalSignupState();
        setFeedback(externalSignupFeedback, "در حال ارسال کد تایید...", "", true);
        try {
            var response = await window.Dent1402Auth.requestExternalSignupOtp(payload);
            if (!response || !response.success) {
                if (response && response.cooldownSeconds) {
                    startExternalSignupCooldown(response.cooldownSeconds);
                }
                setFeedback(externalSignupFeedback, (response && response.error) || "ارسال کد تایید انجام نشد.", "error");
                return;
            }
            startExternalSignupCooldown(response.cooldownSeconds || 0);
            updateExternalSignupPhoneDisplay(payload.phoneNumber, response && response.phoneMasked);
            setExternalSignupVerifyVisible(true);
            setFeedback(externalSignupFeedback, "", "");
            if (externalSignupOtpCode) {
                focusExternalSignupOtpInput(false);
                startOtpCredentialRead(externalSignupOtpCode);
            }
        } finally {
            externalSignupRequesting = false;
            updateExternalSignupState();
        }
    }

    async function submitExternalSignup(event) {
        if (event && event.preventDefault) {
            event.preventDefault();
        }
        if (externalSignupSubmitting) return;
        var payload = externalSignupPayload();
        if (!isValidIranMobile(payload.phoneNumber) || !payload.firstName || !payload.lastName || payload.password.length < 6 || payload.passwordConfirm.length < 6 || payload.password !== payload.passwordConfirm) {
            setExternalSignupVerifyVisible(false);
            setFeedback(externalSignupFeedback, "اطلاعات ثبت نام را کامل و معتبر وارد کن.", "error");
            return;
        }
        if (payload.otpCode.length !== 6) {
            setInputInvalid(externalSignupOtpCode, true);
            setFeedback(externalSignupFeedback, "کد تایید را کامل وارد کن.", "error");
            focusExternalSignupOtpInput(false);
            return;
        }

        setInputInvalid(externalSignupOtpCode, false);
        externalSignupSubmitting = true;
        updateExternalSignupState();
        stopOtpCredentialRead();
        setFeedback(externalSignupFeedback, "در حال تکمیل ثبت نام...", "", true);
        try {
            var state = await window.Dent1402Auth.completeExternalSignup(payload);
            if (!state || !state.loggedIn) {
                setInputInvalid(externalSignupOtpCode, true);
                setFeedback(externalSignupFeedback, (state && state.error) || "ثبت نام انجام نشد.", "error");
                focusExternalSignupOtpInput(true);
                return;
            }
            setFeedback(externalSignupFeedback, "ثبت نام انجام شد.", "success");
        } finally {
            externalSignupSubmitting = false;
            updateExternalSignupState();
        }
    }

    async function requestPasswordResetOtpCode() {
        if (passwordResetRequesting) return;
        var payload = passwordResetPayload();
        if (!isValidIranMobile(payload.phoneNumber)) {
            setFeedback(passwordResetFeedback, "شماره موبایل معتبر وارد کن.", "error");
            if (passwordResetPhone) passwordResetPhone.focus({ preventScroll: true });
            updatePasswordResetState();
            return;
        }

        if (passwordResetPhone) {
            setNumericDisplayValue(passwordResetPhone, payload.phoneNumber);
        }
        setPasswordResetVerifyVisible(false);
        passwordResetRequesting = true;
        updatePasswordResetState();
        setFeedback(passwordResetFeedback, "در حال ارسال کد بازیابی...", "", true);
        try {
            var response = await window.Dent1402Auth.requestPasswordResetOtp(payload);
            if (!response || !response.success) {
                if (response && response.cooldownSeconds) {
                    startPasswordResetCooldown(response.cooldownSeconds);
                }
                setFeedback(passwordResetFeedback, (response && response.error) || "ارسال کد بازیابی انجام نشد.", "error");
                return;
            }
            startPasswordResetCooldown(response.cooldownSeconds || 0);
            setPasswordResetVerifyVisible(true);
            setFeedback(passwordResetFeedback, "", "");
            if (passwordResetOtpCode) {
                passwordResetOtpCode.focus({ preventScroll: true });
                startOtpCredentialRead(passwordResetOtpCode);
            }
        } finally {
            passwordResetRequesting = false;
            updatePasswordResetState();
        }
    }

    async function submitPasswordReset(event) {
        if (event && event.preventDefault) {
            event.preventDefault();
        }
        if (passwordResetSubmitting) return;
        var payload = passwordResetPayload();
        if (!isValidIranMobile(payload.phoneNumber)) {
            setFeedback(passwordResetFeedback, "شماره موبایل معتبر وارد کن.", "error");
            if (passwordResetPhone) passwordResetPhone.focus({ preventScroll: true });
            return;
        }
        if (payload.otpCode.length !== 6) {
            setFeedback(passwordResetFeedback, "کد تایید را کامل وارد کن.", "error");
            if (passwordResetOtpCode) passwordResetOtpCode.focus({ preventScroll: true });
            updatePasswordResetState();
            return;
        }
        if (payload.newPassword.length < 6) {
            setFeedback(passwordResetFeedback, "رمز جدید باید حداقل ۶ کاراکتر باشد.", "error");
            if (passwordResetNewPassword) passwordResetNewPassword.focus({ preventScroll: true });
            updatePasswordResetState();
            return;
        }
        if (payload.confirmPassword.length < 6) {
            setFeedback(passwordResetFeedback, "تکرار رمز جدید را وارد کن.", "error");
            if (passwordResetConfirmPassword) passwordResetConfirmPassword.focus({ preventScroll: true });
            updatePasswordResetState();
            return;
        }
        if (payload.newPassword !== payload.confirmPassword) {
            setFeedback(passwordResetFeedback, "تکرار رمز جدید با رمز جدید یکسان نیست.", "error");
            if (passwordResetConfirmPassword) passwordResetConfirmPassword.focus({ preventScroll: true });
            updatePasswordResetState();
            return;
        }

        passwordResetSubmitting = true;
        updatePasswordResetState();
        stopOtpCredentialRead();
        setFeedback(passwordResetFeedback, "در حال ثبت رمز جدید...", "", true);
        try {
            var state = await window.Dent1402Auth.resetPasswordWithOtp(payload);
            if (!state || !state.loggedIn) {
                setFeedback(passwordResetFeedback, (state && state.error) || "بازیابی رمز انجام نشد.", "error");
                if (passwordResetOtpCode) passwordResetOtpCode.focus({ preventScroll: true });
                return;
            }
            setFeedback(passwordResetFeedback, "رمز عبور تغییر کرد و وارد حساب شدی.", "success");
        } finally {
            passwordResetSubmitting = false;
            updatePasswordResetState();
        }
    }

    function queueLoginOtpAutoSubmit() {
        if (loginOtpAutoSubmitQueued || loginOtpSubmitting || !isLoginOtpVerifyVisible()) {
            return;
        }
        if (loginOtpCodeValue().length !== loginOtpLength()) {
            return;
        }
        loginOtpAutoSubmitQueued = true;
        window.setTimeout(function () {
            loginOtpAutoSubmitQueued = false;
            if (!loginOtpSubmitting && isLoginOtpVerifyVisible() && loginOtpCodeValue().length === loginOtpLength()) {
                submitOtpLogin();
            }
        }, 80);
    }

    function handleLoginOtpCodeInput() {
        if (!loginOtpCodeInput) {
            return;
        }
        setFieldError(loginOtpCodeInput, loginOtpCodeError, "");
        updateLoginOtpSubmitState();
        queueLoginOtpAutoSubmit();
    }

    function queueExternalSignupAutoSubmit() {
        if (externalSignupAutoSubmitQueued || externalSignupSubmitting || !isExternalSignupVerifyVisible()) {
            return;
        }
        if (externalSignupOtpCodeValue().length !== externalSignupOtpLength()) {
            return;
        }
        externalSignupAutoSubmitQueued = true;
        window.setTimeout(function () {
            externalSignupAutoSubmitQueued = false;
            if (!externalSignupSubmitting && isExternalSignupVerifyVisible() && externalSignupOtpCodeValue().length === externalSignupOtpLength()) {
                submitExternalSignup();
            }
        }, 80);
    }

    function handleExternalSignupOtpCodeInput() {
        if (!externalSignupOtpCode) {
            return;
        }
        setInputInvalid(externalSignupOtpCode, false);
        updateExternalSignupState();
        queueExternalSignupAutoSubmit();
    }

    function handleLoginPhoneInput() {
        if (!loginPhoneInput) {
            return;
        }
        if (!loginPhoneInput.value || isValidIranMobile(loginPhoneInput.value)) {
            setFieldError(loginPhoneInput, loginPhoneError, "");
        }
        updateLoginOtpRequestState();
    }

    function editLoginOtpPhoneNumber() {
        stopOtpCredentialRead();
        setLoginOtpVerifyVisible(false);
        setFieldError(loginOtpCodeInput, loginOtpCodeError, "");
        if (loginOtpCodeInput) {
            loginOtpCodeInput.value = "";
            loginOtpCodeInput.dispatchEvent(new Event("input", { bubbles: true }));
        }
        setFeedback(loginOtpFeedback, "", "");
        if (loginPhoneInput) {
            loginPhoneInput.focus({ preventScroll: true });
        }
    }

    function editExternalSignupPhoneNumber() {
        stopOtpCredentialRead();
        setExternalSignupVerifyVisible(false);
        setFeedback(externalSignupFeedback, "", "");
        if (externalSignupPhone) {
            externalSignupPhone.focus({ preventScroll: true });
        }
    }

    async function requestPhoneEnrollmentOtp() {
        if (!phoneEnrollNumber) return;
        var phoneNumber = normalizedPhone(phoneEnrollNumber.value);
        if (!phoneNumber) {
            phoneEnrollFeedbackMessage("شماره موبایل معتبر وارد کن.", "error");
            return;
        }
        setNumericDisplayValue(phoneEnrollNumber, phoneNumber);
        if (phoneEnrollCode) {
            phoneEnrollCode.value = "";
            phoneEnrollCode.dispatchEvent(new Event("input", { bubbles: true }));
            phoneEnrollCode.focus({ preventScroll: true });
            startOtpCredentialRead(phoneEnrollCode);
        }

        phoneEnrollFeedbackMessage("در حال ارسال کد تایید...", "", true);
        if (phoneEnrollRequestButton) phoneEnrollRequestButton.disabled = true;
        try {
            var response = await window.Dent1402Auth.requestPhoneEnrollOtp(phoneNumber);
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                phoneEnrollFeedbackMessage("نشستت منقضی شد. دوباره وارد شو.", "error");
                return;
            }
            if (!response || !response.success) {
                if (response && response.cooldownSeconds) {
                    startPhoneEnrollCooldown(response.cooldownSeconds);
                }
                stopOtpCredentialRead();
                phoneEnrollFeedbackMessage((response && response.error) || "ارسال کد تایید انجام نشد.", "error");
                return;
            }
            startPhoneEnrollCooldown(response.cooldownSeconds || 0);
            var masked = ltrMaskedPhone(response && response.phoneMasked, "");
            phoneEnrollFeedbackMessage((response.message || "کد تایید ارسال شد.") + (masked ? (" (" + masked + ")") : ""), "success");
            if (phoneEnrollCode) {
                phoneEnrollCode.focus({ preventScroll: true });
            }
        } finally {
            updatePhoneEnrollCooldownUi();
        }
    }

    async function verifyPhoneEnrollment() {
        if (!phoneEnrollNumber || !phoneEnrollCode) return;
        var phoneNumber = normalizedPhone(phoneEnrollNumber.value);
        var otpCode = normalizeDigits(phoneEnrollCode.value).replace(/\D+/g, "");
        if (!phoneNumber || !otpCode) {
            phoneEnrollFeedbackMessage("شماره موبایل و کد تایید را کامل وارد کن.", "error");
            return;
        }

        if (phoneEnrollSubmitButton) phoneEnrollSubmitButton.disabled = true;
        stopOtpCredentialRead();
        phoneEnrollFeedbackMessage("در حال تایید شماره موبایل...", "", true);
        try {
            var response = await window.Dent1402Auth.verifyPhoneEnrollOtp(phoneNumber, otpCode);
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                phoneEnrollFeedbackMessage("نشستت منقضی شد. دوباره وارد شو.", "error");
                return;
            }
            if (!response || !response.success || !response.user) {
                phoneEnrollFeedbackMessage((response && response.error) || "تایید شماره انجام نشد.", "error");
                return;
            }
            currentUser = response.user;
            renderIdentity(response.user);
            applyPhoneDetailsFromCurrentUser();
            if (phoneEnrollCode) {
                phoneEnrollCode.value = "";
            }
            phoneEnrollFeedbackMessage(response.message || "شماره موبایل تایید شد.", "success");
            phoneToggleFeedbackMessage("", "");
        } finally {
            if (phoneEnrollSubmitButton) phoneEnrollSubmitButton.disabled = false;
        }
    }

    async function savePhoneLoginToggle() {
        if (!phoneLoginEnabledInput) return;
        var enabled = !!phoneLoginEnabledInput.checked;
        if (phoneLoginSaveButton) phoneLoginSaveButton.disabled = true;
        phoneToggleFeedbackMessage("در حال ذخیره وضعیت ورود پیامکی...", "", true);
        try {
            var response = await window.Dent1402Auth.setPhoneLoginEnabled(enabled);
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                phoneToggleFeedbackMessage("نشستت منقضی شد. دوباره وارد شو.", "error");
                return;
            }
            if (!response || !response.success || !response.user) {
                phoneToggleFeedbackMessage((response && response.error) || "ذخیره وضعیت ورود پیامکی انجام نشد.", "error");
                return;
            }
            currentUser = response.user;
            renderIdentity(response.user);
            applyPhoneDetailsFromCurrentUser();
            phoneToggleFeedbackMessage(response.message || "وضعیت ورود پیامکی ذخیره شد.", "success");
        } finally {
            if (phoneLoginSaveButton) phoneLoginSaveButton.disabled = false;
        }
    }

    function startPhoneNumberEdit() {
        if (!phoneEnrollNumber) {
            return;
        }
        phoneEnrollNumber.focus({ preventScroll: true });
        phoneEnrollNumber.select();
        phoneManageFeedbackMessage("شماره جدید را وارد کن، کد تایید بگیر و ثبت کن.", "success");
    }

    async function removePhoneNumber() {
        if (!currentUser) {
            return;
        }

        var phone = parsedPhone(currentUser);
        if (!phone.hasNumber) {
            phoneManageFeedbackMessage("شماره‌ای برای حذف ثبت نشده است.", "error");
            return;
        }

        var masked = ltrMaskedPhone(phone.numberMasked, "شماره فعلی");
        var confirmed = window.confirm("شماره " + masked + " از این حساب حذف شود؟");
        if (!confirmed) {
            return;
        }

        if (phoneNumberRemoveButton) {
            phoneNumberRemoveButton.disabled = true;
        }
        phoneManageFeedbackMessage("در حال حذف شماره موبایل...", "", true);
        try {
            var response = await window.Dent1402Auth.removePhoneNumber();
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                phoneManageFeedbackMessage("نشستت منقضی شد. دوباره وارد شو.", "error");
                return;
            }
            if (!response || !response.success || !response.user) {
                phoneManageFeedbackMessage((response && response.error) || "حذف شماره انجام نشد.", "error");
                return;
            }
            currentUser = response.user;
            renderIdentity(response.user);
            applyPhoneDetailsFromCurrentUser();
            if (phoneEnrollNumber) {
                phoneEnrollNumber.value = "";
            }
            if (phoneEnrollCode) {
                phoneEnrollCode.value = "";
                phoneEnrollCode.dispatchEvent(new Event("input", { bubbles: true }));
            }
            phoneEnrollFeedbackMessage("", "");
            phoneToggleFeedbackMessage("", "");
            phoneManageFeedbackMessage(response.message || "شماره موبایل حذف شد.", "success");
        } finally {
            if (phoneNumberRemoveButton) {
                phoneNumberRemoveButton.disabled = false;
            }
        }
    }

    async function dismissPhoneSetupNudge() {
        if (!currentUser) return;
        if (accountPhoneNudgeDismiss) accountPhoneNudgeDismiss.disabled = true;
        try {
            var response = await window.Dent1402Auth.dismissPhoneNudge();
            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                return;
            }
            if (!response || !response.success || !response.user) {
                return;
            }
            currentUser = response.user;
            renderIdentity(response.user);
            applyPhoneDetailsFromCurrentUser();
        } finally {
            if (accountPhoneNudgeDismiss) accountPhoneNudgeDismiss.disabled = false;
        }
    }

    function maybeRedirectAfterLogin(user) {
        if (!pendingReturnTo || redirectedAfterLogin) {
            return;
        }

        if (!user) {
            return;
        }

        redirectedAfterLogin = true;
        setBootText("ورود انجام شد، در حال بازگشت...");
        showStage("boot");

        window.setTimeout(function () {
            window.location.href = pendingReturnTo;
        }, 520);
    }

    function handleAuthState(detail) {
        if (detail.status === "session-restoring") {
            setBootText("در حال بازیابی نشست...");
            showStage("boot");
            return;
        }

        if (detail.status === "logging-in") {
            setFeedback(loginFeedback, "در حال ورود...", "", true);
            showStage("login");
            return;
        }

        if (detail.status === "logging-out") {
            setBootText("در حال خروج از حساب...");
            showStage("boot");
            return;
        }

        if (!detail.loggedIn) {
            var preserveOtpLoginAttempt = detail.status === "login-error" && loginMode === "otp" && loginOtpSubmitting;
            currentUser = null;
            applyAccountBranding(null);
            showStage("login");
            openSurface("hub", { replaceHash: true, preserveScroll: true });
            if (!preserveOtpLoginAttempt) {
                resetOtpUi();
            }
            profileDraftAvatarUrl = "";
            updateIdentityAvatars("");
            if (accountRotation) {
                accountRotation.hidden = true;
                accountRotation.textContent = "";
            }
            if (accountInfoRole) {
                accountInfoRole.textContent = "—";
            }
            if (accountInfoSession) {
                accountInfoSession.textContent = "—";
            }
            if (accountInfoRotation) {
                accountInfoRotation.textContent = "—";
            }
            if (accountRowProfileMeta) {
                accountRowProfileMeta.textContent = "ویرایش آواتار، بیو و راه ارتباطی";
            }
            if (accountRowInfoMeta) {
                accountRowInfoMeta.textContent = "نام، شماره دانشجویی و نوع دسترسی";
            }
            if (accountRowOwnerMeta) {
                accountRowOwnerMeta.textContent = "مدیریت کاربران، نماینده‌ها و ایجاد دانشجو";
            }
            if (accountRowNavidMeta) {
                accountRowNavidMeta.textContent = "وضعیت اتصال و همگام‌سازی نوید";
            }
            if (accountRowPhoneMeta) {
                accountRowPhoneMeta.textContent = "ثبت شماره موبایل، تایید با OTP و فعال‌سازی مسیر دوم ورود";
            }
            if (accountRowNotificationsMeta) {
                accountRowNotificationsMeta.textContent = "اعلان‌های نوید و پیام‌های ارسال‌شده برای این حساب در همین بخش نمایش داده می‌شوند.";
            }
            if (accountRowBotsMeta) {
                accountRowBotsMeta.textContent = "بررسی اتصال تلگرام و بله و مدیریت هر اتصال";
            }
            botConnectionsState.loading = false;
            botConnectionsState.loadedForUserKey = "";
            botConnectionsState.csrfToken = "";
            botConnectionsState.connections = null;
            botConnectionsState.disconnectingPlatform = "";
            setInlineFeedback(botConnectionsFeedback, "", "");
            renderBotConnections();
            if (accountNavidAlertCard) {
                accountNavidAlertCard.hidden = true;
            }
            if (ownerHubSection) {
                ownerHubSection.hidden = true;
            }
            ownerState.users = [];
            ownerState.activeUserPanelStudentNumber = "";
            ownerState.gradePayloadByStudent = {};
            ownerState.gradeCourses = [];
            ownerState.rotationCatalog = [];
            ownerState.savingStudentNumber = "";
            ownerState.savingPasswordStudentNumber = "";
            ownerState.savingRotationStudentNumber = "";
            ownerState.deletingStudentNumber = "";
            ownerState.clearingExamStudyStudentNumber = "";
            ownerState.removingPhoneStudentNumber = "";
            ownerState.loadingGradesStudentNumber = "";
            ownerState.savingGradeKey = "";
            ownerState.importingGrades = false;
            ownerState.importingUsers = false;
            ownerState.creatingCohort = false;
            ownerState.deletingGradeCourseKey = "";
            ownerState.resettingGrades = false;
            ownerState.campusMarking = false;
            ownerState.activeTab = "users";
            ownerState.activeCohortKey = "";
            ownerState.userPage = 1;
            ownerAnalyticsState.loading = false;
            ownerAnalyticsState.loaded = false;
            ownerAnalyticsState.dashboard = null;
            updateOwnerTabs();
            syncOwnerStatsShortcut();
            if (accountPhoneNudge) {
                accountPhoneNudge.hidden = true;
            }
            if (phoneStatusSummary) {
                phoneStatusSummary.textContent = "—";
            }
            if (phoneLoginEnabledInput) {
                phoneLoginEnabledInput.checked = false;
                phoneLoginEnabledInput.disabled = true;
            }
            phoneEnrollFeedbackMessage("", "");
            phoneToggleFeedbackMessage("", "");
            phoneManageFeedbackMessage("", "");
            notificationsResetState();
            renderNotificationsUi();
            setInlineFeedback(notificationsFeedback, "", "");
            setFeedback(notificationsManagerFeedback, "", "");
            if (preserveOtpLoginAttempt) {
                updateLoginOtpRequestState();
                updateLoginOtpSubmitState();
            } else {
                setFeedback(loginOtpFeedback, "", "");
                setLoginMode(loginMode);
            }
            setInlineFeedback(profileAvatarFeedback, "", "");
            setProfileBusy(false);
            if (detail.status === "login-error" || detail.status === "unauthorized") {
                if (preserveOtpLoginAttempt) {
                    setFeedback(loginOtpFeedback, detail.error || "ورود با کد تایید انجام نشد.", "error");
                    setFeedback(loginFeedback, "", "");
                } else {
                    setFeedback(loginFeedback, detail.error || "ورود انجام نشد.", "error");
                }
            } else {
                setFeedback(loginFeedback, "", "");
            }
            return;
        }

        if (!currentUser || currentUser.studentNumber !== detail.user.studentNumber) {
            ownerAnalyticsState.loading = false;
            ownerAnalyticsState.loaded = false;
            ownerAnalyticsState.dashboard = null;
        }
        currentUser = detail.user;
        syncOwnerStatsShortcut();
        applyAccountBranding(detail.user);
        renderIdentity(detail.user);
        applyPhoneDetailsFromCurrentUser();
        showStage("panel");
        openSurface(surfaceFromHash(), { replaceHash: true, preserveScroll: true });
        setProfileBusy(false);
        setFeedback(profileFeedback, "", "");
        setFeedback(securityFeedback, "", "");
        setInlineFeedback(notificationsFeedback, "", "");
        setFeedback(notificationsManagerFeedback, "", "");
        if (accountRowNotificationsMeta) {
            accountRowNotificationsMeta.textContent = "در حال دریافت اعلان‌های این حساب...";
        }
        loadNotifications(false);
        loadBotConnections(false);

        if (hasManagementAccess()) {
            if (ownerHubSection) {
                ownerHubSection.hidden = false;
            }
            if (!ownerState.users.length && !ownerState.loading) {
                loadOwnerUsers();
            } else {
                syncOwnerRoleOptions();
                updateCreateStudentGroupOptions();
                renderOwnerPanel();
            }
            if (detail.user.isOwner) {
                loadOwnerSmsStatus();
                loadOwnerMediaStatus();
            } else {
                smsState.status = null;
                mediaState.status = null;
                renderOwnerSmsStatus(null);
                renderOwnerMediaStatus(null);
            }
        } else {
            if (ownerHubSection) {
                ownerHubSection.hidden = true;
            }
            ownerState.users = [];
            ownerState.activeUserPanelStudentNumber = "";
            ownerState.gradePayloadByStudent = {};
            ownerState.gradeCourses = [];
            ownerState.rotationCatalog = [];
            ownerState.savingStudentNumber = "";
            ownerState.savingPasswordStudentNumber = "";
            ownerState.savingRotationStudentNumber = "";
            ownerState.deletingStudentNumber = "";
            ownerState.removingPhoneStudentNumber = "";
            ownerState.loadingGradesStudentNumber = "";
            ownerState.savingGradeKey = "";
            ownerState.importingGrades = false;
            ownerState.importingUsers = false;
            ownerState.creatingCohort = false;
            ownerState.deletingGradeCourseKey = "";
            ownerState.resettingGrades = false;
            ownerState.campusMarking = false;
            ownerState.activeTab = "users";
            ownerState.activeCohortKey = "";
            ownerState.userPage = 1;
            updateOwnerTabs();
            syncOwnerStatsShortcut();
            setCreateStudentBusy(false);
            ownerCreateStudentFeedbackMessage("", "");
            smsState.status = null;
            mediaState.status = null;
            renderOwnerSmsStatus(null);
            renderOwnerMediaStatus(null);
            ownerSmsFeedbackMessage("", "");
            ownerMediaFeedbackMessage("", "");
            if (activeSurface === "owner" || activeSurface === "owner-user") {
                openSurface("hub", { replaceHash: true, preserveScroll: true });
            }
        }

        maybeRedirectAfterLogin(detail.user);
    }

    surfaceOpeners.forEach(function (node) {
        node.addEventListener("click", function (event) {
            var target = normalizeSurfaceName(node.dataset.openSurface);
            var ownerTabTarget = target === "owner" ? normalizeOwnerTab(node.dataset.ownerTabTarget) : "";
            if (target === "hub") {
                return;
            }

            event.preventDefault();
            if (!canOpenSurface(target)) {
                return;
            }
            openSurface(target, { replaceHash: false });
            if (target === "owner" && ownerTabTarget) {
                setOwnerTab(ownerTabTarget);
            }
        });
    });

    surfaceBackButtons.forEach(function (button) {
        button.addEventListener("click", function (event) {
            event.preventDefault();
            openSurface("hub", { replaceHash: true });
        });
    });

    if (botConnectionsRefresh) {
        botConnectionsRefresh.addEventListener("click", function (event) {
            event.preventDefault();
            loadBotConnections(true);
        });
    }

    if (botConnectionsList) {
        botConnectionsList.addEventListener("click", function (event) {
            var button = event.target && event.target.closest
                ? event.target.closest("[data-disconnect-bot]")
                : null;
            if (!button || !botConnectionsList.contains(button)) {
                return;
            }
            event.preventDefault();
            var platform = String(button.dataset.disconnectBot || "").trim();
            if (platform === "telegram" || platform === "bale") {
                disconnectBotConnection(platform);
            }
        });
    }

    if (accountNavidAlertLink) {
        accountNavidAlertLink.addEventListener("click", function (event) {
            handleNotificationCtaNavigation(event, accountNavidAlertLink.dataset.notificationId, accountNavidAlertLink.href);
        });
    }

    if (accountNavidAlertMarkRead) {
        accountNavidAlertMarkRead.addEventListener("click", function (event) {
            var notificationId = String(accountNavidAlertMarkRead.dataset.notificationMark || "").trim();
            if (!notificationId) {
                return;
            }
            event.preventDefault();
            markNotificationsRead([notificationId]);
        });
    }

    if (notificationsRefreshButton) {
        notificationsRefreshButton.addEventListener("click", function (event) {
            event.preventDefault();
            loadNotifications(true);
        });
    }

    if (notificationsMarkAllButton) {
        notificationsMarkAllButton.addEventListener("click", function (event) {
            event.preventDefault();
            markAllNotificationsRead();
        });
    }

    function setPushStatusMessage(message, isError) {
        if (!notificationsPushStatus) {
            return;
        }
        var text = String(message || "");
        notificationsPushStatus.textContent = text;
        notificationsPushStatus.hidden = text === "";
        notificationsPushStatus.classList.toggle("is-error", !!isError);
    }

    function applyPushStatus(status) {
        if (!notificationsPushCard) {
            return;
        }
        if (!status || !status.supported) {
            notificationsPushCard.hidden = true;
            return;
        }

        notificationsPushCard.hidden = false;
        var serverOff = !status.serverSupported;
        var blocked = status.permission === "denied";

        if (notificationsPushToggle) {
            notificationsPushToggle.checked = !!status.subscribed;
            notificationsPushToggle.disabled = notificationsPushState.busy || serverOff || blocked;
        }

        if (serverOff) {
            setPushStatusMessage("اعلان مرورگر روی سرور هنوز فعال نیست.", true);
        } else if (blocked) {
            setPushStatusMessage("اجازه نمایش اعلان در مرورگر مسدود شده است؛ از تنظیمات مرورگر آن را آزاد کنید.", true);
        } else if (status.subscribed) {
            setPushStatusMessage("اعلان‌های مرورگر روی این دستگاه فعال است.", false);
        } else {
            setPushStatusMessage("", false);
        }
    }

    function refreshPushCard() {
        if (!notificationsPushCard || !window.Dent1402Push) {
            if (notificationsPushCard) {
                notificationsPushCard.hidden = true;
            }
            return;
        }
        if (!window.Dent1402Push.isSupported()) {
            notificationsPushCard.hidden = true;
            return;
        }
        window.Dent1402Push.getStatus().then(applyPushStatus).catch(function () {
            // Leave the card untouched if status lookup fails.
        });
    }

    function handlePushToggleChange() {
        if (!window.Dent1402Push || notificationsPushState.busy) {
            return;
        }
        var wantOn = !!(notificationsPushToggle && notificationsPushToggle.checked);
        notificationsPushState.busy = true;
        if (notificationsPushToggle) {
            notificationsPushToggle.disabled = true;
        }
        setPushStatusMessage(wantOn ? "در حال فعال‌سازی..." : "در حال غیرفعال‌سازی...", false);

        var operation = wantOn ? window.Dent1402Push.subscribe() : window.Dent1402Push.unsubscribe();
        operation.then(function () {
            notificationsPushState.busy = false;
            return window.Dent1402Push.getStatus();
        }).then(function (status) {
            applyPushStatus(status);
            if (wantOn && status && status.subscribed) {
                setPushStatusMessage("اعلان‌های مرورگر روی این دستگاه فعال شد.", false);
            } else if (!wantOn) {
                setPushStatusMessage("اعلان‌های مرورگر روی این دستگاه غیرفعال شد.", false);
            }
        }).catch(function (error) {
            notificationsPushState.busy = false;
            if (notificationsPushToggle) {
                notificationsPushToggle.checked = !wantOn;
                notificationsPushToggle.disabled = false;
            }
            setPushStatusMessage(error && error.message ? error.message : "تغییر وضعیت اعلان مرورگر ناموفق بود.", true);
            refreshPushCard();
        });
    }

    if (notificationsPushToggle) {
        notificationsPushToggle.addEventListener("change", handlePushToggleChange);
    }
    refreshPushCard();

    [
        notificationsNavidAlertsToggle,
        notificationsFormRemindersToggle,
        notificationsPaymentRemindersToggle,
        notificationsDigestEnabledToggle
    ].forEach(function (input) {
        if (!input) {
            return;
        }
        input.addEventListener("change", function () {
            notificationsUpdatePreferenceDraft(!!notificationsDigestEnabledToggle && input === notificationsDigestEnabledToggle);
        });
    });
    if (notificationsDigestHourInput) {
        notificationsDigestHourInput.addEventListener("input", function () {
            notificationsUpdatePreferenceDraft(false);
        });
    }

    if (notificationsPrefsSaveButton) {
        notificationsPrefsSaveButton.addEventListener("click", function (event) {
            event.preventDefault();
            saveNotificationsPreferences();
        });
    }

    if (notificationsBroadcastForm) {
        notificationsBroadcastForm.addEventListener("submit", function (event) {
            event.preventDefault();
            submitNotificationsBroadcast();
        });
    }

    if (notificationsFilters) {
        notificationsFilters.addEventListener("click", function (event) {
            var button = event.target && event.target.closest ? event.target.closest("[data-notification-filter]") : null;
            if (!button) {
                return;
            }
            event.preventDefault();
            notificationsState.activeFilter = String(button.getAttribute("data-notification-filter") || "all").trim() || "all";
            renderNotificationsUi();
        });
    }

    if (notificationsList) {
        notificationsList.addEventListener("click", function (event) {
            var markButton = event.target && event.target.closest ? event.target.closest("[data-notification-mark]") : null;
            if (markButton) {
                event.preventDefault();
                markNotificationsRead([markButton.dataset.notificationMark]);
                return;
            }

            var snoozeButton = event.target && event.target.closest ? event.target.closest("[data-notification-snooze]") : null;
            if (snoozeButton) {
                event.preventDefault();
                snoozeNotification(snoozeButton.getAttribute("data-notification-id"), snoozeButton.getAttribute("data-notification-snooze"));
                return;
            }

            var audienceButton = event.target && event.target.closest ? event.target.closest("[data-notification-audience-toggle]") : null;
            if (audienceButton) {
                event.preventDefault();
                toggleNotificationAudience(audienceButton.getAttribute("data-notification-audience-toggle"));
                return;
            }

            var deleteButton = event.target && event.target.closest ? event.target.closest("[data-notification-delete]") : null;
            if (deleteButton) {
                event.preventDefault();
                deleteNotification(deleteButton.getAttribute("data-notification-delete"));
                return;
            }

            var ctaLink = event.target && event.target.closest ? event.target.closest("[data-notification-cta]") : null;
            if (ctaLink) {
                handleNotificationCtaNavigation(event, ctaLink.dataset.notificationId, ctaLink.getAttribute("href"));
            }
        });
    }

    window.addEventListener("hashchange", function () {
        if (!stagePanel || stagePanel.hidden) {
            return;
        }
        openSurface(surfaceFromHash(), { syncHash: false, preserveScroll: true });
    });

    ensureExternalSignupUi();
    ensurePasswordResetUi();
    bindPasswordToggle(externalSignupPasswordToggle, externalSignupPassword);
    bindPasswordToggle(externalSignupPasswordConfirmToggle, externalSignupPasswordConfirm);
    bindPasswordToggle(passwordResetNewPasswordToggle, passwordResetNewPassword);
    bindPasswordToggle(passwordResetConfirmPasswordToggle, passwordResetConfirmPassword);
    bindNumericInput(loginPhoneInput, 14);
    bindNumericInput(externalSignupPhone, 14);
    bindNumericInput(externalSignupOtpCode, 6);
    bindNumericInput(passwordResetPhone, 14);
    bindNumericInput(passwordResetOtpCode, 6);
    bindNumericInput(phoneEnrollNumber, 14);
    bindNumericInput(ownerSmsTestPhone, 14);
    bindNumericInput(notificationsDigestHourInput, 2);
    bindNumericInput(loginOtpCodeInput, 6);
    bindNumericInput(phoneEnrollCode, 6);
    applyOtpSlots(loginOtpCodeInput, loginOtpSlots);
    applyOtpSlots(externalSignupOtpCode, externalSignupOtpSlots);
    applyOtpSlots(phoneEnrollCode, phoneEnrollOtpSlots);

    if (loginPhoneInput) {
        loginPhoneInput.addEventListener("input", handleLoginPhoneInput);
        loginPhoneInput.addEventListener("keydown", function (event) {
            if (event.key !== "Enter") {
                return;
            }
            event.preventDefault();
            if (isValidIranMobile(loginPhoneInput.value) && !loginOtpRequesting) {
                requestLoginOtpCode();
            }
        });
    }

    if (loginOtpCodeInput) {
        loginOtpCodeInput.addEventListener("input", handleLoginOtpCodeInput);
    }

    if (externalSignupOtpCode) {
        externalSignupOtpCode.addEventListener("input", handleExternalSignupOtpCodeInput);
    }

    if (loginMethodPasswordBtn) {
        loginMethodPasswordBtn.addEventListener("click", function () {
            setLoginMode("password");
            setFeedback(loginOtpFeedback, "", "");
            setFeedback(passwordResetFeedback, "", "");
        });
    }

    if (loginMethodOtpBtn) {
        loginMethodOtpBtn.addEventListener("click", function () {
            setLoginMode("otp");
            setFeedback(loginFeedback, "", "");
            setFeedback(passwordResetFeedback, "", "");
            if (loginPhoneInput) {
                loginPhoneInput.focus({ preventScroll: true });
            }
        });
    }

    if (loginMethodSignupBtn) {
        loginMethodSignupBtn.addEventListener("click", function () {
            setLoginMode("signup");
            setFeedback(loginFeedback, "", "");
            setFeedback(loginOtpFeedback, "", "");
            setFeedback(passwordResetFeedback, "", "");
            if (externalSignupFirstName) {
                externalSignupFirstName.focus({ preventScroll: true });
            }
        });
    }

    if (loginPasswordResetBtn) {
        loginPasswordResetBtn.addEventListener("click", function () {
            setLoginMode("reset");
            setFeedback(loginFeedback, "", "");
            setFeedback(loginOtpFeedback, "", "");
            setFeedback(externalSignupFeedback, "", "");
            if (passwordResetPhone) {
                passwordResetPhone.focus({ preventScroll: true });
            }
        });
    }

    if (loginOtpRequestButton) {
        loginOtpRequestButton.addEventListener("click", requestLoginOtpCode);
    }

    if (loginOtpForm) {
        loginOtpForm.addEventListener("submit", submitOtpLogin);
    }

    if (externalSignupRequestButton) {
        externalSignupRequestButton.addEventListener("click", requestExternalSignupOtpCode);
    }

    if (externalSignupForm) {
        externalSignupForm.addEventListener("submit", submitExternalSignup);
        [externalSignupFirstName, externalSignupLastName, externalSignupPhone, externalSignupPassword, externalSignupPasswordConfirm, externalSignupOtpCode].forEach(function (input) {
            if (!input) {
                return;
            }
            input.addEventListener("input", function () {
                setFeedback(externalSignupFeedback, "", "");
                updateExternalSignupState();
            });
        });
    }

    if (externalSignupEditPhoneButton) {
        externalSignupEditPhoneButton.addEventListener("click", function (event) {
            event.preventDefault();
            editExternalSignupPhoneNumber();
        });
    }

    if (passwordResetRequestButton) {
        passwordResetRequestButton.addEventListener("click", requestPasswordResetOtpCode);
    }

    if (passwordResetForm) {
        passwordResetForm.addEventListener("submit", submitPasswordReset);
        [passwordResetPhone, passwordResetOtpCode, passwordResetNewPassword, passwordResetConfirmPassword].forEach(function (input) {
            if (!input) {
                return;
            }
            input.addEventListener("input", function () {
                setFeedback(passwordResetFeedback, "", "");
                updatePasswordResetState();
            });
        });
    }

    if (passwordResetBackButton) {
        passwordResetBackButton.addEventListener("click", function (event) {
            event.preventDefault();
            setLoginMode("password");
            setFeedback(passwordResetFeedback, "", "");
        });
    }

    if (loginOtpEditPhoneButton) {
        loginOtpEditPhoneButton.addEventListener("click", editLoginOtpPhoneNumber);
    }

    [loginForm, loginOtpForm, externalSignupForm, passwordResetForm].forEach(function (formNode) {
        if (!formNode) {
            return;
        }
        formNode.addEventListener("focusin", function (event) {
            var target = event.target;
            if (!target || !target.matches) {
                return;
            }
            if (!target.matches('input:not([type="checkbox"]):not([type="radio"]), textarea, select')) {
                return;
            }
            setViewportScaleLock(true);
            queueLoginViewportSync();
        });
        formNode.addEventListener("focusout", function () {
            window.setTimeout(function () {
                if (isLoginInputFocused()) {
                    queueLoginViewportSync();
                    return;
                }
                setViewportScaleLock(false);
                queueLoginViewportSync();
            }, 60);
        });
    });

    if (window.visualViewport) {
        window.visualViewport.addEventListener("resize", queueLoginViewportSync, { passive: true });
        window.visualViewport.addEventListener("scroll", queueLoginViewportSync, { passive: true });
    }
    window.addEventListener("resize", queueLoginViewportSync, { passive: true });
    window.addEventListener("orientationchange", queueLoginViewportSync, { passive: true });

    if (phoneEnrollRequestButton) {
        phoneEnrollRequestButton.addEventListener("click", requestPhoneEnrollmentOtp);
    }

    if (phoneEnrollSubmitButton) {
        phoneEnrollSubmitButton.addEventListener("click", verifyPhoneEnrollment);
    }

    if (phoneLoginSaveButton) {
        phoneLoginSaveButton.addEventListener("click", savePhoneLoginToggle);
    }

    if (phoneNumberEditButton) {
        phoneNumberEditButton.addEventListener("click", function (event) {
            event.preventDefault();
            startPhoneNumberEdit();
        });
    }

    if (phoneNumberRemoveButton) {
        phoneNumberRemoveButton.addEventListener("click", function (event) {
            event.preventDefault();
            removePhoneNumber();
        });
    }

    if (accountPhoneNudgeOpen) {
        accountPhoneNudgeOpen.addEventListener("click", function (event) {
            event.preventDefault();
            openSurface("phone", { replaceHash: false });
        });
    }

    if (accountPhoneNudgeDismiss) {
        accountPhoneNudgeDismiss.addEventListener("click", function (event) {
            event.preventDefault();
            dismissPhoneSetupNudge();
        });
    }

    if (ownerSmsForm) {
        ownerSmsForm.addEventListener("submit", saveOwnerSmsConfig);
    }

    if (ownerSmsHealthButton) {
        ownerSmsHealthButton.addEventListener("click", runOwnerSmsHealthCheck);
    }

    if (ownerMediaRefreshButton) {
        ownerMediaRefreshButton.addEventListener("click", loadOwnerMediaStatus);
    }

    if (ownerMediaCleanupButton) {
        ownerMediaCleanupButton.addEventListener("click", runOwnerMediaCleanup);
    }

    loginForm.addEventListener("submit", function (event) {
        event.preventDefault();

        var studentNumber = $("login-student-number").value.trim();
        var password = $("login-password").value.trim();

        if (!studentNumber || !password) {
            setFeedback(loginFeedback, "شماره دانشجویی و رمز عبور را کامل وارد کن.", "error");
            return;
        }

        loginSubmit.disabled = true;
        window.Dent1402Auth.login(studentNumber, password).finally(function () {
            loginSubmit.disabled = false;
        });
    });

    if (profileAvatarFile) {
        profileAvatarFile.addEventListener("change", function () {
            var file = profileAvatarFile.files && profileAvatarFile.files[0];
            if (!file) {
                return;
            }

            if (file.size > (12 * 1024 * 1024)) {
                setInlineFeedback(profileAvatarFeedback, "حجم فایل زیاد است. یک عکس کوچک‌تر انتخاب کن.", "error");
                profileAvatarFile.value = "";
                return;
            }

            setProfileAvatarProcessing(true);
            setInlineFeedback(profileAvatarFeedback, "در حال آماده‌سازی عکس...", "", true);
            imageFileToAvatarDataUrl(file).then(function (avatarDataUrl) {
                profileDraftAvatarUrl = avatarDataUrl;
                updateIdentityAvatars($("profile-name").value || $("account-name").textContent || "");
                setInlineFeedback(profileAvatarFeedback, "عکس آماده شد. برای ثبت نهایی، ذخیره تغییرات را بزن.", "success");
            }).catch(function (error) {
                if (error && error.message === "avatar-too-large") {
                    setInlineFeedback(profileAvatarFeedback, "حجم عکس نهایی بیشتر از حد مجاز است. عکس ساده‌تری انتخاب کن.", "error");
                    return;
                }
                setInlineFeedback(profileAvatarFeedback, "خواندن عکس انجام نشد. دوباره تلاش کن.", "error");
            }).finally(function () {
                setProfileAvatarProcessing(false);
                profileAvatarFile.value = "";
            });
        });
    }

    if (profileAvatarClear) {
        profileAvatarClear.addEventListener("click", function () {
            if (!profileDraftAvatarUrl) {
                return;
            }

            profileDraftAvatarUrl = "";
            updateIdentityAvatars($("profile-name").value || $("account-name").textContent || "");
            setInlineFeedback(profileAvatarFeedback, "عکس پروفایل حذف شد. برای ثبت نهایی، ذخیره تغییرات را بزن.", "success");
        });
    }

    profileForm.addEventListener("submit", async function (event) {
        event.preventDefault();

        setProfileBusy(true);
        setFeedback(profileFeedback, "در حال ذخیره پروفایل...", "", true);

        try {
            var response = await request("updateProfile", {
                about: $("profile-about").value.trim(),
                contactHandle: $("profile-contact-handle").value.trim(),
                focusArea: $("profile-focus-area").value.trim(),
                avatarUrl: profileDraftAvatarUrl
            });

            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                setFeedback(profileFeedback, "نشستت منقضی شد. دوباره وارد شو.", "error");
                return;
            }

            if (!response || !response.success || !response.user) {
                setFeedback(profileFeedback, (response && response.error) || "ذخیره پروفایل انجام نشد.", "error");
                return;
            }

            if (typeof window.Dent1402Auth.patchCurrentUser === "function") {
                window.Dent1402Auth.patchCurrentUser(response.user);
            } else {
                window.Dent1402Auth.bootstrap(true);
            }
            setFeedback(profileFeedback, response.message || "پروفایل ذخیره شد.", "success");
            setInlineFeedback(profileAvatarFeedback, "", "");
        } finally {
            setProfileBusy(false);
        }
    });

    securityForm.addEventListener("submit", async function (event) {
        event.preventDefault();

        var currentPassword = $("security-current-password").value.trim();
        var newPassword = $("security-new-password").value.trim();
        var confirmPassword = $("security-confirm-password").value.trim();

        if (!currentPassword || !newPassword || !confirmPassword) {
            setFeedback(securityFeedback, "همه فیلدهای رمز را کامل کن.", "error");
            return;
        }

        if (newPassword !== confirmPassword) {
            setFeedback(securityFeedback, "رمز جدید و تکرارش یکسان نیست.", "error");
            return;
        }

        securitySubmit.disabled = true;
        setFeedback(securityFeedback, "در حال تغییر رمز...", "", true);

        try {
            var response = await request("changePassword", {
                currentPassword: currentPassword,
                newPassword: newPassword
            });

            if (consumeUnauthorized(response, "نشست شما منقضی شده است.")) {
                setFeedback(securityFeedback, "نشستت منقضی شد. دوباره وارد شو.", "error");
                return;
            }

            if (!response || !response.success) {
                setFeedback(securityFeedback, (response && response.error) || "تغییر رمز انجام نشد.", "error");
                return;
            }

            $("security-current-password").value = "";
            $("security-new-password").value = "";
            $("security-confirm-password").value = "";
            setFeedback(securityFeedback, response.message || "رمز عبور تغییر کرد.", "success");
        } finally {
            securitySubmit.disabled = false;
        }
    });

    logoutSubmit.addEventListener("click", function () {
        logoutSubmit.disabled = true;
        window.Dent1402Auth.logout().finally(function () {
            logoutSubmit.disabled = false;
        });
    });

    if (ownerSearch) {
        ownerSearch.addEventListener("input", function () {
            ownerState.userPage = 1;
            renderUsers(ownerState.users);
        });
    }
    if (ownerTabs) {
        ownerTabs.addEventListener("click", function (event) {
            var button = event.target && event.target.closest ? event.target.closest("[data-owner-tab]") : null;
            if (!button) return;
            setOwnerTab(button.dataset.ownerTab);
        });
    }

    if (ownerStatsRefreshButton) {
        ownerStatsRefreshButton.addEventListener("click", function (event) {
            event.preventDefault();
            loadOwnerAnalytics(true);
        });
    }

    if (ownerUserPager) {
        ownerUserPager.addEventListener("click", function (event) {
            var button = event.target && event.target.closest ? event.target.closest("[data-owner-page]") : null;
            if (!button || button.disabled) return;
            ownerState.userPage = Math.max(1, Number(button.dataset.ownerPage) || 1);
            renderUsers(ownerState.users);
            if (ownerUserList && typeof ownerUserList.scrollIntoView === "function") {
                ownerUserList.scrollIntoView({ block: "start", behavior: "smooth" });
            }
        });
    }

    function handleOwnerActionButton(button) {
        if (!button) {
            return;
        }

        var action = String(button.dataset.ownerAction || "");
        var studentNumber = String(button.dataset.studentNumber || "");
        var user = ownerState.users.find(function (item) {
            return item.studentNumber === studentNumber;
        });

        if (!user) {
            return;
        }

        if (action === "toggle-representative") {
            if (user.role === "owner") {
                return;
            }
            setRepresentative(studentNumber, !isRepresentativeRole(user.role));
            return;
        }

        if (action === "open-user-panel") {
            openOwnerUserPanel(studentNumber);
            return;
        }

        if (action === "reload-grades") {
            loadOwnerUserGrades(studentNumber);
            return;
        }

        if (action === "save-password") {
            saveOwnerUserPassword(studentNumber, ownerPasswordInputValue(studentNumber));
            return;
        }

        if (action === "save-rotation") {
            var values = ownerRotationFormValue(studentNumber);
            saveOwnerUserRotation(studentNumber, values.mode, values.rotationId, values.groupNumber);
            return;
        }

        if (action === "save-grade") {
            var columnIndex = Math.floor(toNumber(button.dataset.columnIndex, -1));
            if (columnIndex < 0) {
                return;
            }
            var gradeValue = ownerGradeInputValue(studentNumber, columnIndex);
            saveOwnerUserGrade(studentNumber, columnIndex, gradeValue);
            return;
        }

        if (action === "remove-phone") {
            removeOwnerUserPhone(studentNumber);
            return;
        }

        if (action === "clear-exam-study") {
            clearOwnerUserExamStudy(studentNumber);
            return;
        }

        if (action === "delete-student") {
            deleteOwnerStudentAccount(studentNumber);
        }
    }

    if (ownerUserList) {
        ownerUserList.addEventListener("click", function (event) {
            handleOwnerActionButton(event.target.closest("button[data-owner-action]"));
        });

        ownerUserList.addEventListener("change", function (event) {
            var target = event.target;
            if (!target || !target.matches) {
                return;
            }

            if (target.matches('select[data-owner-rotation-mode="true"]')) {
                syncOwnerGroupOptions(String(target.dataset.studentNumber || ""));
                return;
            }

            if (target.matches('select[data-owner-rotation-id="true"]')) {
                syncOwnerGroupOptions(String(target.dataset.studentNumber || ""));
            }
        });

        ownerUserList.addEventListener("keydown", function (event) {
            if (event.key !== "Enter") {
                return;
            }
            var input = event.target && event.target.closest
                ? event.target.closest('input[data-grade-input="true"]')
                : null;
            if (!input) {
                return;
            }

            event.preventDefault();
            var studentNumber = String(input.dataset.studentNumber || "");
            var columnIndex = Math.floor(toNumber(input.dataset.columnIndex, -1));
            if (!studentNumber || columnIndex < 0) {
                return;
            }
            saveOwnerUserGrade(studentNumber, columnIndex, input.value.trim());
        });
    }

    if (ownerUserPanelBody) {
        ownerUserPanelBody.addEventListener("click", function (event) {
            handleOwnerActionButton(event.target.closest("button[data-owner-action]"));
        });

        ownerUserPanelBody.addEventListener("change", function (event) {
            var target = event.target;
            if (!target || !target.matches) {
                return;
            }

            if (target.matches('select[data-owner-rotation-mode="true"]')) {
                syncOwnerGroupOptions(String(target.dataset.studentNumber || ""));
                return;
            }

            if (target.matches('select[data-owner-rotation-id="true"]')) {
                syncOwnerGroupOptions(String(target.dataset.studentNumber || ""));
            }
        });

        ownerUserPanelBody.addEventListener("keydown", function (event) {
            if (event.key !== "Enter") {
                return;
            }
            var input = event.target && event.target.closest
                ? event.target.closest('input[data-grade-input="true"]')
                : null;
            if (!input) {
                return;
            }

            event.preventDefault();
            var studentNumber = String(input.dataset.studentNumber || "");
            var columnIndex = Math.floor(toNumber(input.dataset.columnIndex, -1));
            if (!studentNumber || columnIndex < 0) {
                return;
            }
            saveOwnerUserGrade(studentNumber, columnIndex, input.value.trim());
        });
    }

    if (ownerUserPanelBack) {
        ownerUserPanelBack.addEventListener("click", function (event) {
            event.preventDefault();
            openSurface("owner", { replaceHash: true });
        });
    }

    if (ownerCreateStudentForm) {
        ownerCreateStudentForm.addEventListener("submit", createStudentAccount);
    }

    if (ownerCreateCohortForm) {
        ownerCreateCohortForm.addEventListener("submit", createCohort);
        if (ownerCohortProductType) {
            ownerCohortProductType.addEventListener("change", function () {
                syncOwnerCohortServiceDefaults(false);
            });
        }
        if (ownerCohortNotesMode) {
            ownerCohortNotesMode.addEventListener("change", function () {
                syncOwnerCohortServiceDefaults(false);
            });
        }
        ownerCohortServiceInputs().concat([ownerCohortAllowRepresentative]).forEach(function (node) {
            if (!node) {
                return;
            }
            node.addEventListener("change", function () {
                node.dataset.userTouched = "1";
            });
        });
        resetOwnerCohortDefaults();
    }

    if (ownerImportUsersForm) {
        ownerImportUsersForm.addEventListener("submit", importCohortUsers);
    }

    if (ownerGradesImportForm) {
        ownerGradesImportForm.addEventListener("submit", importOwnerGrades);
    }

    if (ownerGradesDeleteCourseButton) {
        ownerGradesDeleteCourseButton.addEventListener("click", function (event) {
            event.preventDefault();
            deleteOwnerGradeCourse();
        });
    }

    if (ownerGradesResetAllButton) {
        ownerGradesResetAllButton.addEventListener("click", function (event) {
            event.preventDefault();
            resetOwnerGradebook();
        });
    }

    if (ownerGradesCourseSelect) {
        ownerGradesCourseSelect.addEventListener("change", renderOwnerGradeManager);
    }

    if (ownerStudentRotationMode) {
        ownerStudentRotationMode.addEventListener("change", updateCreateStudentGroupOptions);
    }

    if (ownerStudentRotationId) {
        ownerStudentRotationId.addEventListener("change", updateCreateStudentGroupOptions);
    }

    if (ownerCohortSelect) {
        ownerCohortSelect.addEventListener("change", function () {
            setOwnerActiveCohort(ownerCohortSelect.value);
        });
    }

    if (ownerCohortGrid) {
        ownerCohortGrid.addEventListener("click", function (event) {
            var button = event.target && event.target.closest ? event.target.closest("[data-cohort-key]") : null;
            if (!button) {
                return;
            }
            event.preventDefault();
            setOwnerActiveCohort(button.dataset.cohortKey || "");
        });
    }

    if (ownerMarkCampusStudentsButton) {
        ownerMarkCampusStudentsButton.addEventListener("click", function (event) {
            event.preventDefault();
            markCampusStudents();
        });
    }

    if (navidConfigForm) {
        navidConfigForm.addEventListener("submit", saveNavidConfig);
    }

    if (navidSyncNowButton) {
        navidSyncNowButton.addEventListener("click", syncNavidNow);
    }

    if (navidGetCaptchaButton) {
        navidGetCaptchaButton.addEventListener("click", loadNavidCaptchaChallenge);
    }

    if (navidCompleteReconnectButton) {
        navidCompleteReconnectButton.addEventListener("click", completeNavidReconnect);
    }

    if (navidCaptchaCodeInput) {
        navidCaptchaCodeInput.addEventListener("keydown", function (event) {
            if (event.key === "Enter") {
                event.preventDefault();
                completeNavidReconnect();
            }
        });
    }

    updateCreateStudentGroupOptions();
    syncOwnerRoleOptions();
    var requestedLoginMode = "";
    try {
        requestedLoginMode = String(new URLSearchParams(window.location.search || "").get("mode") || "").trim().toLowerCase();
    } catch (_error) {
        requestedLoginMode = "";
    }
    setLoginMode(requestedLoginMode === "signup" || requestedLoginMode === "password" || requestedLoginMode === "reset"
        ? requestedLoginMode
        : "otp");
    resetOtpUi();
    window.Dent1402Auth.onChange(handleAuthState);
})();
